<?php

namespace App\Services\Desempenho;

use App\Models\Aluno;
use App\Models\FaixaDesempenho;
use App\Models\UsoTextoDesempenho;

/**
 * Marca, para cada aluno, o texto canônico da última faixa como já usado.
 * A próxima quinzena na mesma faixa parte para o texto seguinte.
 */
class RegistroInicialTextoFaixa
{
    public function registrarTodos(): int
    {
        $consulta = new FaixaAnteriorDoAluno;
        $gravados = 0;

        Aluno::query()->orderBy('id')->chunk(100, function ($alunos) use ($consulta, &$gravados): void {
            foreach ($alunos as $aluno) {
                $gravados += $this->registrar($aluno, $consulta);
            }
        });

        return $gravados;
    }

    public function registrar(Aluno $aluno, ?FaixaAnteriorDoAluno $consulta = null): int
    {
        $consulta ??= new FaixaAnteriorDoAluno;
        $periodo = trim((string) $aluno->metricas_periodo);
        $periodo = $periodo === '' ? null : $periodo;
        $gravados = 0;

        foreach ($consulta->codigos($aluno) as $eixo => $codigos) {
            foreach ($codigos as $codigo) {
                $faixa = FaixaDesempenho::query()
                    ->where('codigo', $codigo)
                    ->whereHas('eixo', static fn ($q) => $q->where('codigo', $eixo))
                    ->first();
                if ($faixa === null) {
                    continue;
                }

                $faixa->garantirTextoCanonico();
                $texto = $faixa->textos()->where('canonico', true)->first();
                if ($texto === null) {
                    continue;
                }

                $jaExiste = UsoTextoDesempenho::query()
                    ->where('aluno_id', $aluno->id)
                    ->where('faixa_desempenho_id', $faixa->id)
                    ->exists();
                if ($jaExiste) {
                    continue;
                }

                UsoTextoDesempenho::query()->create([
                    'aluno_id' => $aluno->id,
                    'faixa_desempenho_id' => $faixa->id,
                    'texto_faixa_desempenho_id' => $texto->id,
                    'eixo_codigo' => $eixo,
                    'ciclo' => 1,
                    'periodo' => $periodo,
                ]);
                $gravados++;
            }
        }

        return $gravados;
    }
}
