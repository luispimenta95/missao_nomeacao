<?php

namespace App\Services\Acompanhamento;

use App\Enums\AcaoAcompanhamento;
use App\Enums\FiltroParametroAcompanhamento;
use App\Enums\PapelFaixa;
use App\Enums\SituacaoAcompanhamento;
use App\Enums\TipoMotivoAcompanhamento;
use App\Models\Aluno;
use App\Models\EixoDesempenho;
use DateTimeInterface;
use Illuminate\Support\Collection;

final class MontadorPainelAcompanhamento
{
    public function __construct(private ClassificadorAcaoAcompanhamento $classificador) {}

    /**
     * @param  iterable<Aluno>  $alunos
     * @return Collection<int, LinhaPainel>
     */
    public function linhas(iterable $alunos, DateTimeInterface $hoje): Collection
    {
        $linhas = [];
        foreach ($alunos as $aluno) {
            $linha = $this->linha($aluno, $hoje);
            if ($linha !== null) {
                $linhas[] = $linha;
            }
        }

        usort($linhas, static function (LinhaPainel $a, LinhaPainel $b): int {
            $prioridade = $b->ficha->acao->prioridade() <=> $a->ficha->acao->prioridade();
            if ($prioridade !== 0) {
                return $prioridade;
            }

            return strcasecmp($a->aluno->nome, $b->aluno->nome);
        });

        return collect($linhas)->values();
    }

    public function linha(Aluno $aluno, DateTimeInterface $hoje): ?LinhaPainel
    {
        $ctx = ContextoAcompanhamento::fromAluno($aluno, $hoje);
        $ficha = $this->classificador->classificar($ctx);
        $somenteAgenda = false;

        if ($ficha === null) {
            $agendada = $this->fichaSomenteAgenda($aluno, $hoje, $ctx);
            if ($agendada === null) {
                return null;
            }
            $ficha = $agendada;
            $somenteAgenda = true;
        }

        $situacao = SituacaoAcompanhamento::Pendente;
        if (! $somenteAgenda && $aluno->acao_resolvida_assinatura !== null && $aluno->acao_resolvida_assinatura === $ficha->assinatura()) {
            $situacao = SituacaoAcompanhamento::Concluida;
        }

        $assuntos = array_values(array_filter(
            $ctx->assuntos,
            static fn (array $assunto): bool => CatalogoFaixas::papel(EixoDesempenho::ASSUNTO, $assunto['faixa']) === PapelFaixa::Baixa
        ));

        return new LinhaPainel(
            aluno: $aluno,
            ficha: $ficha,
            situacao: $situacao,
            iniciais: TextoAcompanhamento::iniciais($aluno->nome),
            ultimoContato: TextoAcompanhamento::ultimoContato($aluno->ultimo_contato_em, $hoje),
            proximoContato: TextoAcompanhamento::proximoContato($aluno->proximo_contato_em, $hoje),
            proximoEhHoje: $ctx->contatoProgramadoHoje,
            somenteAgenda: $somenteAgenda,
            assuntos: $assuntos,
        );
    }

    private function fichaSomenteAgenda(Aluno $aluno, DateTimeInterface $hoje, ContextoAcompanhamento $ctx): ?FichaAcompanhamento
    {
        $proximo = $aluno->proximo_contato_em;
        if ($proximo === null || $proximo->toDateString() <= $hoje->format('Y-m-d')) {
            return null;
        }

        $motivo = new MotivoAcompanhamento(
            TipoMotivoAcompanhamento::ContatoAgendado,
            AcaoAcompanhamento::MarcarPresenca,
            'Acompanhamento agendado para '.$proximo->format('d/m/Y'),
            false,
            FiltroParametroAcompanhamento::Contato,
            0,
        );

        return new FichaAcompanhamento(
            AcaoAcompanhamento::MarcarPresenca,
            [$motivo],
            $this->classificador->evolucoes($ctx),
        );
    }
}
