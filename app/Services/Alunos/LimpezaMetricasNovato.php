<?php

namespace App\Services\Alunos;

use App\Models\Aluno;
use Carbon\Carbon;
use DateTimeInterface;

/**
 * Tira da base as faixas gravadas quando o aluno ainda era novato.
 * O envio de 01/10/2026 (período 2026-09-2) é o caso que motivou a regra.
 */
class LimpezaMetricasNovato
{
    public const PERIODO_ENVIO_2026_10_01 = '2026-09-2';

    public function executar(?DateTimeInterface $hoje = null): int
    {
        $hoje = $hoje ?? now();
        $diaDoEnvio = Carbon::parse('2026-10-01', 'America/Sao_Paulo');
        $atualizados = 0;

        foreach (Aluno::query()->orderBy('id')->get() as $aluno) {
            $falsoNoEnvio = $aluno->ehNovato($diaDoEnvio)
                && $aluno->metricas_periodo === self::PERIODO_ENVIO_2026_10_01;
            if (! $aluno->ehNovato($hoje) && ! $falsoNoEnvio) {
                continue;
            }
            if (! $this->temMetrica($aluno)) {
                continue;
            }

            $aluno->limparMetricasDesempenho();
            $aluno->save();
            $atualizados++;
        }

        return $atualizados;
    }

    private function temMetrica(Aluno $aluno): bool
    {
        foreach (Aluno::COLUNAS_METRICAS as $coluna) {
            $valor = $aluno->{$coluna};
            if ($valor === null || $valor === '' || $valor === []) {
                continue;
            }

            return true;
        }

        return false;
    }
}
