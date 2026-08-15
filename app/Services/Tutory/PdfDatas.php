<?php

namespace App\Services\Tutory;

/**
 * Converte datas do padrão americano (MM/DD) para o brasileiro (DD/MM) nos PDFs.
 */
final class PdfDatas
{
    /**
     * @var array<string, int>
     */
    private const MESES_EN = [
        'january' => 1,
        'jan' => 1,
        'february' => 2,
        'feb' => 2,
        'march' => 3,
        'mar' => 3,
        'april' => 4,
        'apr' => 4,
        'may' => 5,
        'june' => 6,
        'jun' => 6,
        'july' => 7,
        'jul' => 7,
        'august' => 8,
        'aug' => 8,
        'september' => 9,
        'sept' => 9,
        'sep' => 9,
        'october' => 10,
        'oct' => 10,
        'november' => 11,
        'nov' => 11,
        'december' => 12,
        'dec' => 12,
    ];

    public static function textoParaBr(string $texto, ?bool $forcarAmericano = null): string
    {
        $forcar = $forcarAmericano ?? self::pareceAmericano($texto);
        $texto = self::converterBarras($texto, $forcar);
        $texto = self::converterMesesIngles($texto);
        $texto = self::converterIso($texto);

        return $texto;
    }

    /**
     * @param  list<mixed>  $labels
     * @return list<mixed>
     */
    public static function listaParaBr(array $labels): array
    {
        $trechos = [];
        foreach ($labels as $label) {
            if (is_scalar($label)) {
                $trechos[] = (string) $label;
            }
        }
        $forcar = self::pareceAmericano(implode(' | ', $trechos));

        $saida = [];
        foreach ($labels as $label) {
            if (! is_scalar($label)) {
                $saida[] = $label;

                continue;
            }
            $saida[] = self::textoParaBr((string) $label, $forcar);
        }

        return $saida;
    }

    public static function pareceAmericano(string $texto): bool
    {
        if (! preg_match_all('/\b(\d{1,2})\/(\d{1,2})(?:\/(\d{2,4}))?\b/', $texto, $matches, PREG_SET_ORDER)) {
            return false;
        }

        $americanos = 0;
        $brasileiros = 0;
        $primeiros = [];
        $segundos = [];

        foreach ($matches as $m) {
            $n1 = (int) $m[1];
            $n2 = (int) $m[2];
            $primeiros[] = $n1;
            $segundos[] = $n2;
            if ($n1 <= 12 && $n2 > 12) {
                $americanos++;
            }
            if ($n1 > 12 && $n2 <= 12) {
                $brasileiros++;
            }
        }

        if ($americanos > $brasileiros) {
            return true;
        }
        if ($brasileiros > $americanos) {
            return false;
        }

        $varPrimeiro = count(array_unique($primeiros)) > 1;
        $varSegundo = count(array_unique($segundos)) > 1;

        // MM/DD: mês fixo, dia varia (08/01, 08/02, 08/03)
        if (! $varPrimeiro && $varSegundo && max($primeiros) <= 12) {
            return true;
        }
        // DD/MM: dia varia, mês fixo (01/08, 02/08, 03/08)
        if ($varPrimeiro && ! $varSegundo && max($segundos) <= 12) {
            return false;
        }

        // Um único valor ambíguo (01/08): não inverter
        if (count($matches) === 1) {
            return false;
        }

        // Vários ambíguos sem padrão: o PDF do Tutory usa MM/DD
        return true;
    }

    private static function converterBarras(string $texto, bool $forcarAmericano): string
    {
        $convertido = preg_replace_callback(
            '/\b(\d{1,2})\/(\d{1,2})(?:\/(\d{2,4}))?\b/',
            static function (array $m) use ($forcarAmericano): string {
                $n1 = (int) $m[1];
                $n2 = (int) $m[2];
                $ano = $m[3] ?? null;

                if ($n1 < 1 || $n1 > 31 || $n2 < 1 || $n2 > 31) {
                    return $m[0];
                }

                $jaBrasileiro = $n1 > 12 && $n2 <= 12;
                if ($jaBrasileiro) {
                    return $m[0];
                }

                $eAmericano = ($n1 <= 12 && $n2 > 12) || ($forcarAmericano && $n1 <= 12);
                if (! $eAmericano) {
                    return $m[0];
                }

                $dia = str_pad((string) $n2, 2, '0', STR_PAD_LEFT);
                $mes = str_pad((string) $n1, 2, '0', STR_PAD_LEFT);

                return $ano !== null ? "{$dia}/{$mes}/{$ano}" : "{$dia}/{$mes}";
            },
            $texto
        );

        return is_string($convertido) ? $convertido : $texto;
    }

    private static function converterIso(string $texto): string
    {
        $convertido = preg_replace_callback(
            '/\b(\d{4})-(\d{2})-(\d{2})\b/',
            static function (array $m): string {
                return $m[3].'/'.$m[2].'/'.$m[1];
            },
            $texto
        );

        return is_string($convertido) ? $convertido : $texto;
    }

    private static function converterMesesIngles(string $texto): string
    {
        $convertido = preg_replace_callback(
            '/\b(January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|Jun|Jul|Aug|Sept|Sep|Oct|Nov|Dec)\.?\s+(\d{1,2})(?:,?\s+(\d{2,4}))?\b/i',
            static function (array $m): string {
                $mes = self::MESES_EN[strtolower(rtrim($m[1], '.'))] ?? null;
                if ($mes === null) {
                    return $m[0];
                }
                $dia = str_pad((string) ((int) $m[2]), 2, '0', STR_PAD_LEFT);
                $mm = str_pad((string) $mes, 2, '0', STR_PAD_LEFT);
                $ano = $m[3] ?? null;

                return $ano !== null ? "{$dia}/{$mm}/{$ano}" : "{$dia}/{$mm}";
            },
            $texto
        );

        return is_string($convertido) ? $convertido : $texto;
    }
}
