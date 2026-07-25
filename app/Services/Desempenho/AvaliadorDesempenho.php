<?php

namespace App\Services\Desempenho;

use App\Models\NivelDesempenho;
use App\Models\RegraDesempenho;
use Illuminate\Support\Collection;

class AvaliadorDesempenho
{
    /**
     * Avalia métricas do panorama de Progresso do plano.
     *
     * @param  array<string, float|int|null>  $metricas  chaves: horas_brutas, horas_liquidas, dias, semanas, pct_questoes
     * @return array{nivel: NivelDesempenho|null, metricas: array<string, float|null>, motivo: string}
     */
    public function avaliar(array $metricas): array
    {
        $metricas = $this->normalizarMetricas($metricas);

        /** @var Collection<int, NivelDesempenho> $niveis */
        $niveis = NivelDesempenho::query()
            ->where('ativo', true)
            ->with(['regras.criterio'])
            ->orderBy('ordem')
            ->orderBy('id')
            ->get();

        if ($niveis->isEmpty()) {
            return [
                'nivel' => null,
                'metricas' => $metricas,
                'motivo' => 'Nenhum nível de desempenho ativo cadastrado.',
            ];
        }

        foreach ($niveis as $nivel) {
            if ($nivel->regras->isEmpty()) {
                continue;
            }

            if ($this->nivelAtende($nivel, $metricas)) {
                return [
                    'nivel' => $nivel,
                    'metricas' => $metricas,
                    'motivo' => 'Nível correspondente encontrado.',
                ];
            }
        }

        return [
            'nivel' => null,
            'metricas' => $metricas,
            'motivo' => 'Nenhuma regra de desempenho foi atendida.',
        ];
    }

    /**
     * @param  array<string, mixed>  $metricas
     * @return array<string, float|null>
     */
    public function normalizarMetricas(array $metricas): array
    {
        $out = [];
        foreach (['horas_brutas', 'horas_liquidas', 'dias', 'semanas', 'pct_questoes'] as $codigo) {
            $out[$codigo] = array_key_exists($codigo, $metricas)
                ? $this->paraNumero($metricas[$codigo])
                : null;
        }

        return $out;
    }

    /**
     * Converte valores do relatório (ex.: "693:45", "84%", "140") em número.
     */
    public function paraNumero(mixed $valor): ?float
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        if (is_int($valor) || is_float($valor)) {
            return (float) $valor;
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return null;
        }

        // HH:MM → horas decimais
        if (preg_match('/^(\d+):(\d{1,2})$/', $texto, $m)) {
            return ((int) $m[1]) + (((int) $m[2]) / 60);
        }

        $texto = str_replace(['%', ' '], '', $texto);
        $texto = str_replace(',', '.', $texto);
        if (! is_numeric($texto)) {
            return null;
        }

        return (float) $texto;
    }

    /**
     * @param  array<string, float|null>  $metricas
     */
    private function nivelAtende(NivelDesempenho $nivel, array $metricas): bool
    {
        foreach ($nivel->regras as $regra) {
            if (! $this->regraAtende($regra, $metricas)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<string, float|null>  $metricas
     */
    private function regraAtende(RegraDesempenho $regra, array $metricas): bool
    {
        $codigo = $regra->criterio?->codigo;
        if ($codigo === null || ! array_key_exists($codigo, $metricas)) {
            return false;
        }

        $atual = $metricas[$codigo];
        if ($atual === null) {
            return false;
        }

        $min = $regra->valor_min;
        $max = $regra->valor_max;

        return match ($regra->operador) {
            '>=' => $min !== null && $atual >= $min,
            '>' => $min !== null && $atual > $min,
            '<=' => $min !== null && $atual <= $min,
            '<' => $min !== null && $atual < $min,
            '=' => $min !== null && abs($atual - $min) < 0.0001,
            'between' => $min !== null && $max !== null && $atual >= $min && $atual <= $max,
            default => false,
        };
    }
}
