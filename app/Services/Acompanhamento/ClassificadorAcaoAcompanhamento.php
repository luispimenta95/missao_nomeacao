<?php

namespace App\Services\Acompanhamento;

use App\Enums\AcaoAcompanhamento;
use App\Enums\FiltroParametroAcompanhamento;
use App\Enums\LimiteAcompanhamento;
use App\Enums\PapelFaixa;
use App\Enums\ParametroAcompanhamento;
use App\Enums\TendenciaFaixa;
use App\Enums\TipoMotivoAcompanhamento;
use App\Models\EixoDesempenho;

final class ClassificadorAcaoAcompanhamento
{
    public function classificar(ContextoAcompanhamento $ctx): ?FichaAcompanhamento
    {
        $evolucoes = $this->evolucoes($ctx);
        $motivos = [];
        $ordem = 0;

        if (CatalogoFaixas::papel(EixoDesempenho::CONSTANCIA, $ctx->constanciaAtual) === PapelFaixa::Critica) {
            $motivos[] = $this->motivo(
                TipoMotivoAcompanhamento::ConstanciaCritica,
                'Constância crítica',
                FiltroParametroAcompanhamento::Constancia,
                $ordem++,
            );
        }

        if (CatalogoFaixas::papel(EixoDesempenho::VOLUME_QUESTOES, $ctx->volumeAtual) === PapelFaixa::Critica) {
            $motivos[] = $this->motivo(
                TipoMotivoAcompanhamento::VolumeCritico,
                'Volume de questões crítico',
                FiltroParametroAcompanhamento::VolumeQuestoes,
                $ordem++,
            );
        }

        if (CatalogoFaixas::papel(EixoDesempenho::PERCENTUAL_ACERTOS, $ctx->desempenhoAtual) === PapelFaixa::Baixa) {
            $motivos[] = $this->motivo(
                TipoMotivoAcompanhamento::DesempenhoBaixo,
                'Desempenho em questões: baixo',
                FiltroParametroAcompanhamento::DesempenhoQuestoes,
                $ordem++,
            );
        }

        foreach ($ctx->assuntos as $assunto) {
            if (CatalogoFaixas::papel(EixoDesempenho::ASSUNTO, $assunto['faixa']) !== PapelFaixa::Baixa) {
                continue;
            }
            $rotulo = $assunto['disciplina'] !== ''
                ? $assunto['disciplina'].' · '.$assunto['assunto']
                : $assunto['assunto'];
            $motivos[] = $this->motivo(
                TipoMotivoAcompanhamento::AssuntoBaixo,
                $rotulo.': desempenho baixo',
                FiltroParametroAcompanhamento::Assunto,
                $ordem++,
                ponteProtocoloResgate: true,
            );
        }

        if (LimiteAcompanhamento::DiasSemContato->excedido($ctx->diasSemContato)) {
            $motivos[] = $this->motivo(
                TipoMotivoAcompanhamento::SemContato,
                $ctx->diasSemContato.' dias sem contato',
                FiltroParametroAcompanhamento::Contato,
                $ordem++,
            );
        }

        if ($ctx->contatoProgramadoHoje) {
            $motivos[] = $this->motivo(
                TipoMotivoAcompanhamento::ContatoProgramado,
                'Contato programado para hoje',
                FiltroParametroAcompanhamento::Contato,
                $ordem++,
            );
        }

        if ($this->podeParabenizar($evolucoes)) {
            foreach ($evolucoes as $evolucao) {
                if ($evolucao->tendencia !== TendenciaFaixa::Melhorou) {
                    continue;
                }
                $motivos[] = $this->motivo(
                    TipoMotivoAcompanhamento::Evolucao,
                    $evolucao->parametro->rotulo().' evoluiu: '.$evolucao->anterior.' → '.$evolucao->atual,
                    $evolucao->parametro->filtro(),
                    $ordem++,
                );
            }
        }

        if ($motivos === []) {
            return null;
        }

        usort($motivos, static function (MotivoAcompanhamento $a, MotivoAcompanhamento $b): int {
            $prioridade = $b->acao->prioridade() <=> $a->acao->prioridade();
            if ($prioridade !== 0) {
                return $prioridade;
            }

            return $a->ordem <=> $b->ordem;
        });

        return new FichaAcompanhamento($this->acaoVencedora($motivos), $motivos, $evolucoes);
    }

    /**
     * @return list<EvolucaoParametro>
     */
    public function evolucoes(ContextoAcompanhamento $ctx): array
    {
        $lista = [];
        foreach (ParametroAcompanhamento::cases() as $parametro) {
            $lista[] = new EvolucaoParametro(
                $parametro,
                $ctx->nomeAnterior($parametro),
                $ctx->nomeAtual($parametro),
                CatalogoFaixas::tendencia(
                    $parametro,
                    $ctx->codigoAnterior($parametro),
                    $ctx->codigoAtual($parametro),
                ),
            );
        }

        return $lista;
    }

    /**
     * @param  list<EvolucaoParametro>  $evolucoes
     */
    private function podeParabenizar(array $evolucoes): bool
    {
        $melhorou = false;
        foreach ($evolucoes as $evolucao) {
            if ($evolucao->tendencia === TendenciaFaixa::Piorou) {
                return false;
            }
            if ($evolucao->tendencia === TendenciaFaixa::Melhorou) {
                $melhorou = true;
            }
        }

        return $melhorou;
    }

    /**
     * @param  list<MotivoAcompanhamento>  $motivos
     */
    private function acaoVencedora(array $motivos): AcaoAcompanhamento
    {
        $vencedora = AcaoAcompanhamento::Parabenizar;
        foreach ($motivos as $motivo) {
            if ($motivo->acao->prioridade() > $vencedora->prioridade()) {
                $vencedora = $motivo->acao;
            }
        }

        return $vencedora;
    }

    private function motivo(
        TipoMotivoAcompanhamento $tipo,
        string $texto,
        ?FiltroParametroAcompanhamento $filtro,
        int $ordem,
        bool $ponteProtocoloResgate = false,
    ): MotivoAcompanhamento {
        return new MotivoAcompanhamento(
            $tipo,
            $tipo->acao(),
            $texto,
            $ponteProtocoloResgate,
            $filtro,
            $ordem,
        );
    }
}
