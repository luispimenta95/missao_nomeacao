<?php

namespace App\Services\Desempenho;

use App\Models\Aluno;
use App\Models\EixoDesempenho;
use App\Models\FaixaDesempenho;
use App\Services\Acompanhamento\CatalogoFaixas;
use Illuminate\Support\Collection;

/**
 * Última faixa gravada no aluno, no dia em que o relatório vai rodar.
 * Constância, volume e percentual têm um código. Assunto pode ter mais de um.
 */
class FaixaAnteriorDoAluno
{
    /** @var Collection<int, FaixaDesempenho>|null */
    private ?Collection $faixas = null;

    /**
     * @return array<string, list<string>>
     */
    public function codigos(Aluno $aluno): array
    {
        return [
            EixoDesempenho::CONSTANCIA => $this->um(
                EixoDesempenho::CONSTANCIA,
                $aluno->last_performance_codigo,
                $aluno->last_performance,
            ),
            EixoDesempenho::VOLUME_QUESTOES => $this->um(
                EixoDesempenho::VOLUME_QUESTOES,
                $aluno->last_question_volume_codigo,
                $aluno->last_question_volume,
            ),
            EixoDesempenho::PERCENTUAL_ACERTOS => $this->um(
                EixoDesempenho::PERCENTUAL_ACERTOS,
                $aluno->last_accuracy_rate_codigo,
                $aluno->last_accuracy_rate,
            ),
            EixoDesempenho::ASSUNTO => $this->assuntos($aluno),
        ];
    }

    public function mesma(Aluno $aluno, string $eixo, string $codigoAtual): bool
    {
        return in_array($codigoAtual, $this->codigos($aluno)[$eixo] ?? [], true);
    }

    /**
     * @return list<string>
     */
    private function um(string $eixo, ?string $codigo, ?string $nome): array
    {
        $resolvido = $this->codigo($eixo, $codigo, $nome);

        return $resolvido === null ? [] : [$resolvido];
    }

    /**
     * @return list<string>
     */
    private function assuntos(Aluno $aluno): array
    {
        $codigos = [];
        $detalhe = is_array($aluno->assuntos_detalhe) ? $aluno->assuntos_detalhe : [];

        foreach ($detalhe as $item) {
            if (! is_array($item)) {
                continue;
            }
            $codigo = $this->codigo(
                EixoDesempenho::ASSUNTO,
                isset($item['faixa']) ? (string) $item['faixa'] : null,
                isset($item['faixa_nome']) ? (string) $item['faixa_nome'] : null,
            );
            if ($codigo !== null) {
                $codigos[] = $codigo;
            }
        }

        if ($codigos !== []) {
            return array_values(array_unique($codigos));
        }

        $subjects = trim((string) $aluno->last_subjects);
        if ($subjects === '' || mb_strtolower($subjects) === 'sem pontos de atenção') {
            return [];
        }

        foreach (preg_split('/\s*·\s*/u', $subjects) ?: [] as $parte) {
            $codigo = $this->codigo(EixoDesempenho::ASSUNTO, null, $parte);
            if ($codigo !== null) {
                $codigos[] = $codigo;
            }
        }

        return array_values(array_unique($codigos));
    }

    private function codigo(string $eixo, ?string $codigo, ?string $nome): ?string
    {
        $codigo = trim((string) $codigo);
        $nome = trim((string) $nome);
        $doEixo = $this->faixas()->filter(
            static fn (FaixaDesempenho $faixa): bool => $faixa->eixo?->codigo === $eixo
        );

        if ($codigo !== '') {
            $porCodigo = $doEixo->firstWhere('codigo', $codigo);
            if ($porCodigo !== null) {
                return $porCodigo->codigo;
            }
        }

        $catalogo = CatalogoFaixas::resolverCodigo(
            $eixo,
            $codigo !== '' ? $codigo : null,
            $nome !== '' ? $nome : null,
        );
        if ($catalogo !== null && $doEixo->firstWhere('codigo', $catalogo) !== null) {
            return $catalogo;
        }

        if ($nome === '') {
            return null;
        }

        $alvo = mb_strtolower($nome);
        $porNome = $doEixo->first(
            static fn (FaixaDesempenho $faixa): bool => mb_strtolower($faixa->nome) === $alvo
        );

        return $porNome?->codigo;
    }

    /**
     * @return Collection<int, FaixaDesempenho>
     */
    private function faixas(): Collection
    {
        return $this->faixas ??= FaixaDesempenho::query()->with('eixo')->get();
    }
}
