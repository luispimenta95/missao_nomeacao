<?php

namespace App\Services\Tutory;

/**
 * Converte datas MM/DD, YYYY/MM/DD e YYYY-MM-DD para o padrão brasileiro (DD/MM/YYYY) nos PDFs.
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
        $bloqueados = [];
        $texto = self::converterIso($texto, $bloqueados);
        $local = self::pareceAmericano($texto);
        // forcar vale para MM/DD curto (eixo). Data com ano só inverte se ESTE trecho for americano.
        $forcarCurto = $forcarAmericano ?? $local;
        $texto = self::converterBarras($texto, $forcarCurto, $local);
        $texto = self::converterMesesIngles($texto);

        return self::restaurarBloqueios($texto, $bloqueados);
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
        if (! preg_match_all('/(?<!\d[\/\-.])\b(\d{1,2})\/(\d{1,2})(?:\/(\d{2,4}))?\b/', $texto, $matches, PREG_SET_ORDER)) {
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

    private static function converterBarras(string $texto, bool $forcarCurto, bool $forcarCompleto): string
    {
        $convertido = preg_replace_callback(
            '/(?<!\d[\/\-.])\b(\d{1,2})\/(\d{1,2})(?:\/(\d{2,4}))?\b/',
            static function (array $m) use ($forcarCurto, $forcarCompleto): string {
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

                $anoCompleto = is_string($ano) && strlen($ano) >= 4;
                $forcar = $anoCompleto ? $forcarCompleto : $forcarCurto;
                $eAmericano = ($n1 <= 12 && $n2 > 12) || ($forcar && $n1 <= 12);
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

    /**
     * YYYY/MM/DD, YYYY-MM-DD e YYYY.MM.DD → DD/MM/YYYY.
     * Trava o resultado para o conversor MM/DD não inverter de novo (01/08/2026 → 08/01/2026).
     *
     * @param  list<string>  $bloqueados
     */
    private static function converterIso(string $texto, array &$bloqueados): string
    {
        $convertido = preg_replace_callback(
            '/\b(\d{4})[\/\-.](\d{1,2})[\/\-.](\d{1,2})\b/',
            static function (array $m) use (&$bloqueados): string {
                $ano = (int) $m[1];
                $mes = (int) $m[2];
                $dia = (int) $m[3];
                if ($ano < 1900 || $ano > 2100 || $mes < 1 || $mes > 12 || $dia < 1 || $dia > 31) {
                    return $m[0];
                }

                $br = str_pad((string) $dia, 2, '0', STR_PAD_LEFT)
                    .'/'.str_pad((string) $mes, 2, '0', STR_PAD_LEFT)
                    .'/'.$m[1];
                $token = self::tokenIso(count($bloqueados));
                $bloqueados[] = $br;

                return $token;
            },
            $texto
        );

        return is_string($convertido) ? $convertido : $texto;
    }

    /**
     * @param  list<string>  $bloqueados
     */
    private static function restaurarBloqueios(string $texto, array $bloqueados): string
    {
        for ($i = count($bloqueados) - 1; $i >= 0; $i--) {
            $texto = str_replace(self::tokenIso($i), $bloqueados[$i], $texto);
        }

        return $texto;
    }

    private static function tokenIso(int $indice): string
    {
        return "\u{E000}ISO{$indice}\u{E001}";
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
