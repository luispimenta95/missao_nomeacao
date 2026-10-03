<?php

namespace App\Services\Desempenho;

use App\Models\Aluno;
use App\Models\FaixaDesempenho;
use App\Models\TextoFaixaDesempenho;
use App\Models\UsoTextoDesempenho;
use Illuminate\Support\Collection;

/**
 * Escolhe o texto da faixa no dia do relatório.
 *
 * A consulta ao que já foi usado só ocorre quando a faixa atual é a mesma
 * da última gravada no aluno. Se a faixa mudou, entra o texto canônico:
 * o da faixa nova já é outro.
 * Dentro da mesma faixa, percorre os textos ativos até esgotar e recomeça.
 * O mesmo período reaproveita o texto já registrado.
 */
class SeletorTextoFaixa
{
    /**
     * @return array{texto: string, texto_faixa_id: int|null}
     */
    public function escolher(
        FaixaDesempenho $faixa,
        string $eixoCodigo,
        ?Aluno $aluno,
        bool $mesmaFaixa,
        ?string $periodo,
    ): array {
        $textos = $this->textosAtivos($faixa);
        $canonico = $textos->firstWhere('canonico', true) ?? $textos->first();

        if ($canonico === null) {
            return [
                'texto' => (string) $faixa->texto_email,
                'texto_faixa_id' => null,
            ];
        }

        if ($aluno === null || $aluno->id === null) {
            return [
                'texto' => $canonico->texto,
                'texto_faixa_id' => $canonico->id,
            ];
        }

        $periodo = trim((string) $periodo);
        $periodo = $periodo === '' ? null : $periodo;

        if ($periodo !== null) {
            $usoDoPeriodo = UsoTextoDesempenho::query()
                ->where('aluno_id', $aluno->id)
                ->where('faixa_desempenho_id', $faixa->id)
                ->where('periodo', $periodo)
                ->latest('id')
                ->first();
            if ($usoDoPeriodo !== null) {
                $jaEscolhido = $textos->firstWhere('id', $usoDoPeriodo->texto_faixa_desempenho_id)
                    ?? TextoFaixaDesempenho::query()->find($usoDoPeriodo->texto_faixa_desempenho_id);
                if ($jaEscolhido !== null) {
                    return [
                        'texto' => $jaEscolhido->texto,
                        'texto_faixa_id' => $jaEscolhido->id,
                    ];
                }
            }
        }

        if (! $mesmaFaixa) {
            $this->registrar($aluno, $faixa, $canonico, $eixoCodigo, $this->proximoCiclo($aluno, $faixa), $periodo);

            return [
                'texto' => $canonico->texto,
                'texto_faixa_id' => $canonico->id,
            ];
        }

        $ciclo = $this->cicloAtual($aluno, $faixa);
        $usados = $ciclo === 0
            ? collect()
            : UsoTextoDesempenho::query()
                ->where('aluno_id', $aluno->id)
                ->where('faixa_desempenho_id', $faixa->id)
                ->where('ciclo', $ciclo)
                ->pluck('texto_faixa_desempenho_id');

        $proximo = $textos->first(
            static fn (TextoFaixaDesempenho $texto): bool => ! $usados->contains($texto->id)
        );

        if ($proximo === null) {
            $ciclo = $this->proximoCiclo($aluno, $faixa);
            $proximo = $canonico;
        } elseif ($ciclo === 0) {
            $ciclo = 1;
        }

        $this->registrar($aluno, $faixa, $proximo, $eixoCodigo, $ciclo, $periodo);

        return [
            'texto' => $proximo->texto,
            'texto_faixa_id' => $proximo->id,
        ];
    }

    /**
     * @return Collection<int, TextoFaixaDesempenho>
     */
    private function textosAtivos(FaixaDesempenho $faixa): Collection
    {
        $textos = $faixa->relationLoaded('textos')
            ? $faixa->textos
            : $faixa->textos()->get();

        return $textos
            ->filter(static fn (TextoFaixaDesempenho $texto): bool => $texto->ativo)
            ->sortBy([
                ['ordem', 'asc'],
                ['id', 'asc'],
            ])
            ->values();
    }

    private function cicloAtual(Aluno $aluno, FaixaDesempenho $faixa): int
    {
        return (int) UsoTextoDesempenho::query()
            ->where('aluno_id', $aluno->id)
            ->where('faixa_desempenho_id', $faixa->id)
            ->max('ciclo');
    }

    private function proximoCiclo(Aluno $aluno, FaixaDesempenho $faixa): int
    {
        $atual = $this->cicloAtual($aluno, $faixa);

        return $atual === 0 ? 1 : $atual + 1;
    }

    private function registrar(
        Aluno $aluno,
        FaixaDesempenho $faixa,
        TextoFaixaDesempenho $texto,
        string $eixoCodigo,
        int $ciclo,
        ?string $periodo,
    ): void {
        UsoTextoDesempenho::query()->create([
            'aluno_id' => $aluno->id,
            'faixa_desempenho_id' => $faixa->id,
            'texto_faixa_desempenho_id' => $texto->id,
            'eixo_codigo' => $eixoCodigo,
            'ciclo' => $ciclo,
            'periodo' => $periodo,
        ]);
    }
}
