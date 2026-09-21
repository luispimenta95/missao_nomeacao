<?php

namespace App\Services\Acompanhamento;

use App\Enums\AcaoAcompanhamento;
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

        usort($linhas, static function (LinhaPainel $a, LinhaPainel $b): int {
            $prioridade = $b->ficha->acao->prioridade() <=> $a->ficha->acao->prioridade();
            if ($prioridade !== 0) {
                return $prioridade;
            }

            return strcasecmp($a->aluno->nome, $b->aluno->nome);
        });

        return collect($linhas)->values();
    }

    public function linha(Aluno $aluno, DateTimeInterface $hoje): LinhaPainel
    {
        $ctx = ContextoAcompanhamento::fromAluno($aluno, $hoje);
        $ficha = $this->classificador->classificar($ctx) ?? $this->fichaEmDia($ctx);

        $situacao = SituacaoAcompanhamento::Pendente;
        if ($aluno->acao_resolvida_assinatura !== null && $aluno->acao_resolvida_assinatura === $ficha->assinatura()) {
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
            somenteAgenda: false,
            assuntos: $assuntos,
        );
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
}
