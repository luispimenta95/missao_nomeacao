<?php

namespace App\Services\Acompanhamento;

use App\Enums\AcaoAcompanhamento;
use App\Enums\FiltroParametroAcompanhamento;
use App\Enums\LimiteAcompanhamento;
use App\Enums\PapelFaixa;
use App\Enums\ParametroAcompanhamento;
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

        usort($linhas, static fn (LinhaPainel $a, LinhaPainel $b): int => strcasecmp($a->aluno->nome, $b->aluno->nome));

        return collect($linhas)->values();
    }

    public function linha(Aluno $aluno, DateTimeInterface $hoje): LinhaPainel
    {
        $ctx = ContextoAcompanhamento::fromAluno($aluno, $hoje);
        $ficha = $this->fichaPorMetricas($ctx);
        $situacao = SituacaoAcompanhamento::Pendente;
        $novato = $aluno->ehNovato($hoje);

        // Cadastro com menos de 15 dias e sem contato neste ciclo: a ação é
        // Aluno novato e as faixas do relatório não disputam. Com contato,
        // segue o mesmo ciclo dos demais: Ok até a data agendada e Marcar
        // presença se ela passar sem outro contato. A frase do cadastro
        // permanece como observação na ficha.
        if ($novato && $aluno->acao_resolvida === null) {
            $ficha = $this->fichaNovato($aluno, $hoje);
        } elseif ($aluno->acao_resolvida !== null) {
            // O novato não leva evolução nem motivo das faixas para o ciclo do contato.
            $base = $novato ? new FichaAcompanhamento(AcaoAcompanhamento::Ok, [], []) : $ficha;
            if ($this->agendamentoVencidoSemContato($aluno, $hoje)) {
                $ficha = $this->fichaMarcarPresencaPorAgendamento($aluno, $base);
            } else {
                $ficha = $this->fichaAcoesConcluidas($base);
                $situacao = SituacaoAcompanhamento::Concluida;
            }
        } elseif (! $aluno->ativo) {
            $ficha = new FichaAcompanhamento(
                AcaoAcompanhamento::RestabelecerContato,
                $ficha->motivos,
                $ficha->evolucoes,
            );
        }

        $assuntos = $novato ? [] : array_values(array_filter(
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
            somenteAgenda: false,
            assuntos: $assuntos,
            observacaoTempoSemContato: $this->observacaoTempoSemContato($ctx),
            observacaoNovato: $novato && $aluno->acao_resolvida !== null
                ? $this->textoNovato($aluno, $hoje)
                : null,
        );
    }

    public function assinaturaDasMetricas(Aluno $aluno, DateTimeInterface $hoje): string
    {
        return $this->fichaPorMetricas(ContextoAcompanhamento::fromAluno($aluno, $hoje))->assinatura();
    }

    private function fichaNovato(Aluno $aluno, DateTimeInterface $hoje): FichaAcompanhamento
    {
        return new FichaAcompanhamento(
            AcaoAcompanhamento::AlunoNovato,
            [new MotivoAcompanhamento(
                TipoMotivoAcompanhamento::Novato,
                AcaoAcompanhamento::AlunoNovato,
                $this->textoNovato($aluno, $hoje),
                false,
                null,
                0,
            )],
            [],
        );
    }

    private function textoNovato(Aluno $aluno, DateTimeInterface $hoje): string
    {
        $dias = ContextoAcompanhamento::diasDesde($aluno->created_at, $hoje);
        $quando = $dias === 0
            ? 'Cadastro hoje'
            : 'Cadastro há '.$dias.' '.($dias === 1 ? 'dia' : 'dias');

        return $quando.'. Menos de '.Aluno::DIAS_NOVATO.' dias: as métricas do relatório não são analisadas.';
    }

    private function fichaEmDia(ContextoAcompanhamento $ctx): FichaAcompanhamento
    {
        $partes = [];
        foreach (ParametroAcompanhamento::cases() as $parametro) {
            $nome = $ctx->nomeAtual($parametro);
            if ($nome !== null && $nome !== '') {
                $partes[] = $parametro->rotulo().': '.$nome;
            }
        }

        $motivo = new MotivoAcompanhamento(
            TipoMotivoAcompanhamento::Panorama,
            AcaoAcompanhamento::Ok,
            $partes === [] ? 'Sem faixa registrada no último relatório' : implode(' · ', $partes),
            false,
            null,
            0,
        );

        return new FichaAcompanhamento(
            AcaoAcompanhamento::Ok,
            [$motivo],
            $this->classificador->evolucoes($ctx),
        );
    }

    private function observacaoTempoSemContato(ContextoAcompanhamento $ctx): ?string
    {
        if (! LimiteAcompanhamento::DiasSemContato->excedido($ctx->diasSemContato)) {
            return null;
        }

        return $ctx->diasSemContato.' dias sem contato';
    }

    private function fichaPorMetricas(ContextoAcompanhamento $ctx): FichaAcompanhamento
    {
        return $this->classificador->classificar($ctx) ?? $this->fichaEmDia($ctx);
    }

    private function fichaAcoesConcluidas(FichaAcompanhamento $base): FichaAcompanhamento
    {
        return new FichaAcompanhamento(
            AcaoAcompanhamento::Ok,
            [new MotivoAcompanhamento(
                TipoMotivoAcompanhamento::Panorama,
                AcaoAcompanhamento::Ok,
                'Ações do mentor concluídas neste acompanhamento',
                false,
                null,
                0,
            )],
            $base->evolucoes,
        );
    }

    private function fichaMarcarPresencaPorAgendamento(Aluno $aluno, FichaAcompanhamento $base): FichaAcompanhamento
    {
        $data = $aluno->proximo_contato_em?->format('d/m/Y') ?? '';

        return new FichaAcompanhamento(
            AcaoAcompanhamento::MarcarPresenca,
            [new MotivoAcompanhamento(
                TipoMotivoAcompanhamento::ContatoAgendado,
                AcaoAcompanhamento::MarcarPresenca,
                'Nenhum contato registrado após a data agendada ('.$data.')',
                false,
                FiltroParametroAcompanhamento::Contato,
                0,
            )],
            $base->evolucoes,
        );
    }

    /**
     * A data de hoje passou da data agendada e nenhum contato foi registrado
     * nesse dia ou depois.
     */
    private function agendamentoVencidoSemContato(Aluno $aluno, DateTimeInterface $hoje): bool
    {
        $agendado = $aluno->proximo_contato_em;
        if ($agendado === null) {
            return false;
        }

        $agendadoDia = $agendado->toDateString();
        if ($hoje->format('Y-m-d') <= $agendadoDia) {
            return false;
        }

        $ultimo = $aluno->ultimo_contato_em;
        if ($ultimo === null) {
            return true;
        }

        return $ultimo->format('Y-m-d') < $agendadoDia;
    }
}
