<?php

namespace App\Http\Controllers;

use App\Enums\AcaoAcompanhamento;
use App\Enums\DecisaoAgendamento;
use App\Enums\FocoAcompanhamento;
use App\Enums\LimiteAcompanhamento;
use App\Enums\SituacaoAcompanhamento;
use App\Enums\TipoMotivoAcompanhamento;
use App\Models\Aluno;
use App\Services\Acompanhamento\ConsultaDashboard;
use App\Services\Acompanhamento\ContextoAcompanhamento;
use App\Services\Acompanhamento\LinhaPainel;
use App\Services\Acompanhamento\MontadorPainelAcompanhamento;
use App\Services\Acompanhamento\TextoAcompanhamento;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    private const POR_PAGINA = 8;

    public function __construct(private MontadorPainelAcompanhamento $montador) {}

    public function index(Request $request)
    {
        $hoje = now();
        $alunos = Aluno::query()
            ->orderBy('nome')
            ->get()
            ->reject(fn (Aluno $aluno) => $aluno->isTeacher())
            ->values();
        $linhas = $this->montador->linhas($alunos, $hoje);
        $consulta = ConsultaDashboard::fromRequest($request);
        $resumo = $this->resumo($alunos, $linhas, $hoje);
        [$visiveis, $contagens] = $this->aplicarFiltros($linhas, $consulta);
        $paginacao = $this->paginar($visiveis, $consulta);
        $aberta = $consulta->alunoId === null
            ? null
            : $linhas->first(fn (LinhaPainel $linha) => $linha->aluno->id === $consulta->alunoId);
        $contatos = $aberta
            ? $aberta->aluno->contatos()->limit(20)->get()
            : collect();

        return view('admin.dashboard', [
            'resumo' => $resumo,
            'linhas' => $paginacao['linhas'],
            'paginacao' => $paginacao,
            'contagens' => $contagens,
            'consulta' => $consulta,
            'aberta' => $aberta,
            'contatos' => $contatos,
            'dataExtenso' => TextoAcompanhamento::dataPorExtenso($hoje),
            'primeiroNome' => TextoAcompanhamento::primeiroNome((string) $request->user()?->name),
            'iniciaisUsuario' => TextoAcompanhamento::iniciais((string) $request->user()?->name),
        ]);
    }

    public function storeContato(Request $request, Aluno $aluno)
    {
        $dados = $request->validate([
            'observacao' => ['nullable', 'string', 'max:2000'],
        ]);

        $agora = now();
        $observacao = trim((string) ($dados['observacao'] ?? ''));
        $aluno->ultimo_contato_em = $agora;
        if ($observacao !== '') {
            $aluno->ultima_observacao = $observacao;
        }
        if ($aluno->proximo_contato_em !== null && $aluno->proximo_contato_em->toDateString() <= $agora->toDateString()) {
            $aluno->proximo_contato_em = null;
        }

        $linha = $this->montador->linha($aluno, $agora);
        if ($linha !== null && ! $linha->somenteAgenda) {
            $aluno->acao_resolvida = $linha->ficha->acao;
            $aluno->acao_resolvida_assinatura = $linha->ficha->assinatura();
        } else {
            $aluno->acao_resolvida = null;
            $aluno->acao_resolvida_assinatura = null;
        }

        $aluno->save();
        $aluno->contatos()->create([
            'user_id' => $request->user()?->id,
            'observacao' => $observacao !== '' ? $observacao : null,
            'ocorrido_em' => $agora,
        ]);

        return redirect()
            ->route('admin.dashboard.contatos.agendar', $aluno)
            ->with('success', 'Contato registrado.');
    }

    public function agendarProximo(Aluno $aluno)
    {
        $hoje = now();
        $dataPadrao = LimiteAcompanhamento::DiasSemContato->dataContandoHoje($hoje);

        return view('admin.dashboard.agendar-proximo', [
            'aluno' => $aluno,
            'iniciais' => TextoAcompanhamento::iniciais($aluno->nome),
            'dataPadraoTexto' => $dataPadrao->format('d/m/Y'),
            'minData' => $hoje->toDateString(),
        ]);
    }

    public function storeAgendarProximo(Request $request, Aluno $aluno)
    {
        $dados = $request->validate([
            'decisao' => ['required', Rule::enum(DecisaoAgendamento::class)],
            'proximo_contato_em' => ['exclude_unless:decisao,sim', 'required', 'date', 'after_or_equal:today'],
        ], [
            'decisao.required' => 'Escolha se deseja informar a data do próximo contato.',
            'proximo_contato_em.required' => 'Informe a data do próximo contato.',
            'proximo_contato_em.after_or_equal' => 'A data do próximo contato precisa ser hoje ou uma data futura.',
        ]);

        $decisao = DecisaoAgendamento::from($dados['decisao']);
        $data = $decisao === DecisaoAgendamento::Sim
            ? Carbon::parse($dados['proximo_contato_em'])->toDateString()
            : LimiteAcompanhamento::DiasSemContato->dataContandoHoje(now())->toDateString();

        $aluno->proximo_contato_em = $data;
        $aluno->save();

        return redirect()
            ->route('admin.dashboard', ['aluno' => $aluno->id])
            ->with('success', 'Próximo contato de '.$aluno->nome.' agendado para '.Carbon::parse($data)->format('d/m/Y').'.');
    }

    public function storeAgenda(Request $request, Aluno $aluno)
    {
        if ($request->boolean('limpar')) {
            $aluno->proximo_contato_em = null;
            $aluno->save();

            return redirect()
                ->back()
                ->with('success', 'Agendamento cancelado.');
        }

        $dados = $request->validate([
            'proximo_contato_em' => ['required', 'date'],
        ]);

        $aluno->proximo_contato_em = $dados['proximo_contato_em'];
        $aluno->save();

        return redirect()
            ->back()
            ->with('success', 'Acompanhamento agendado.');
    }

    /**
     * @param  Collection<int, Aluno>  $alunos
     * @param  Collection<int, LinhaPainel>  $linhas
     * @return array<string, int>
     */
    private function resumo(Collection $alunos, Collection $linhas, \DateTimeInterface $hoje): array
    {
        $limite = LimiteAcompanhamento::DiasSemContato;
        $hojeDia = $hoje->format('Y-m-d');

        return [
            'ativos' => $alunos->filter(fn (Aluno $aluno) => $aluno->ativo)->count(),
            'inativos' => $alunos->filter(fn (Aluno $aluno) => ! $aluno->ativo)->count(),
            'total' => $alunos->count(),
            'contatados' => $alunos->filter(function (Aluno $aluno) use ($hoje, $limite) {
                return $aluno->ultimo_contato_em !== null
                    && ! $limite->excedido(ContextoAcompanhamento::diasDesde($aluno->ultimo_contato_em, $hoje));
            })->count(),
            'sem_contato' => $alunos->filter(function (Aluno $aluno) use ($hoje, $limite) {
                $referencia = $aluno->ultimo_contato_em ?? $aluno->created_at;

                return $limite->excedido(ContextoAcompanhamento::diasDesde($referencia, $hoje));
            })->count(),
            'programados' => $alunos->filter(function (Aluno $aluno) use ($hojeDia) {
                return $aluno->proximo_contato_em !== null
                    && $aluno->proximo_contato_em->toDateString() >= $hojeDia;
            })->count(),
            'intervencoes' => $linhas->filter(fn (LinhaPainel $linha) => $linha->ficha->acao === AcaoAcompanhamento::Intervir
                && $linha->situacao === SituacaoAcompanhamento::Pendente)->count(),
            'agenda_hoje' => $linhas->filter(fn (LinhaPainel $linha) => $linha->proximoEhHoje)->count(),
            'sem_contato_acao' => $linhas->filter(fn (LinhaPainel $linha) => $linha->ficha->tem(TipoMotivoAcompanhamento::SemContato)
                && $linha->situacao === SituacaoAcompanhamento::Pendente)->count(),
            'evolucoes' => $linhas->filter(fn (LinhaPainel $linha) => $linha->ficha->acao === AcaoAcompanhamento::Parabenizar
                && $linha->situacao === SituacaoAcompanhamento::Pendente)->count(),
        ];
    }

    /**
     * @param  Collection<int, LinhaPainel>  $linhas
     * @return array{0: Collection<int, LinhaPainel>, 1: array<string, int>}
     */
    private function aplicarFiltros(Collection $linhas, ConsultaDashboard $consulta): array
    {
        $base = $linhas;
        if ($consulta->foco !== FocoAcompanhamento::Agenda) {
            $base = $base->reject(fn (LinhaPainel $linha) => $linha->somenteAgenda)->values();
        }

        if ($consulta->busca !== '') {
            $termo = mb_strtolower($consulta->busca);
            $base = $base->filter(fn (LinhaPainel $linha) => str_contains(mb_strtolower($linha->aluno->nome), $termo))->values();
        }

        if ($consulta->parametro !== null) {
            $parametro = $consulta->parametro;
            $base = $base->filter(function (LinhaPainel $linha) use ($parametro) {
                foreach ($linha->ficha->motivos as $motivo) {
                    if ($motivo->filtro === $parametro) {
                        return true;
                    }
                }

                return false;
            })->values();
        }

        if ($consulta->foco !== null) {
            $foco = $consulta->foco;
            $base = $base->filter(function (LinhaPainel $linha) use ($foco) {
                return match ($foco) {
                    FocoAcompanhamento::Intervir => $linha->ficha->acao === AcaoAcompanhamento::Intervir
                        && $linha->situacao === SituacaoAcompanhamento::Pendente,
                    FocoAcompanhamento::AgendaHoje => $linha->proximoEhHoje,
                    FocoAcompanhamento::Agenda => $linha->aluno->proximo_contato_em !== null || $linha->somenteAgenda,
                    FocoAcompanhamento::SemContato => $linha->ficha->tem(TipoMotivoAcompanhamento::SemContato),
                    FocoAcompanhamento::Parabenizar => $linha->ficha->acao === AcaoAcompanhamento::Parabenizar
                        && $linha->situacao === SituacaoAcompanhamento::Pendente,
                };
            })->values();
        }

        $contagens = [
            'pendentes' => $base->filter(fn (LinhaPainel $linha) => $linha->situacao === SituacaoAcompanhamento::Pendente)->count(),
            'concluidas' => $base->filter(fn (LinhaPainel $linha) => $linha->situacao === SituacaoAcompanhamento::Concluida)->count(),
            'todas' => $base->count(),
        ];

        $naSituacao = $base
            ->filter(fn (LinhaPainel $linha) => $consulta->situacao->aceita($linha->situacao))
            ->values();

        foreach (AcaoAcompanhamento::cases() as $acao) {
            $contagens[$acao->value] = $naSituacao
                ->filter(fn (LinhaPainel $linha) => $linha->ficha->acao === $acao)
                ->count();
        }

        $visiveis = $consulta->acao === null
            ? $naSituacao
            : $naSituacao->filter(fn (LinhaPainel $linha) => $linha->ficha->acao === $consulta->acao)->values();

        return [$visiveis, $contagens];
    }

    /**
     * @param  Collection<int, LinhaPainel>  $linhas
     * @return array{linhas: Collection<int, LinhaPainel>, de: int, ate: int, total: int, pagina: int, paginas: int}
     */
    private function paginar(Collection $linhas, ConsultaDashboard $consulta): array
    {
        $total = $linhas->count();
        $paginas = max(1, (int) ceil($total / self::POR_PAGINA));
        $pagina = min($consulta->pagina, $paginas);
        $fatia = $linhas->slice(($pagina - 1) * self::POR_PAGINA, self::POR_PAGINA)->values();

        return [
            'linhas' => $fatia,
            'de' => $total === 0 ? 0 : (($pagina - 1) * self::POR_PAGINA) + 1,
            'ate' => min($total, $pagina * self::POR_PAGINA),
            'total' => $total,
            'pagina' => $pagina,
            'paginas' => $paginas,
        ];
    }
}
