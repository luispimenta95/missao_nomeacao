<?php

namespace App\Services\Tutory;

use App\Models\Configuracao;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Impede um segundo envio do mesmo período no mesmo mês
 * (cron da Hostinger + GitHub Action + execução manual).
 */
class TutoryRelatorioAgenda
{
    public static function chaveEnvio(string $periodo, ?DateTimeInterface $ref = null): string
    {
        $mes = self::mesDoPeriodo($periodo, $ref);

        return 'tutory.envio.periodo.'.$periodo.'.'.$mes->format('Y-m');
    }

    public static function mesDoPeriodo(string $periodo, ?DateTimeInterface $ref = null): DateTimeImmutable
    {
        $hoje = $ref instanceof DateTimeImmutable
            ? $ref
            : ($ref instanceof DateTimeInterface
                ? DateTimeImmutable::createFromInterface($ref)
                : new DateTimeImmutable('now'));

        if ($periodo === '2' && (int) $hoje->format('j') < 16) {
            return $hoje->modify('first day of last month');
        }

        return $hoje;
    }

    public function deveExecutar(string $periodo, ?DateTimeInterface $ref = null): bool
    {
        $valor = Configuracao::valor(self::chaveEnvio($periodo, $ref));
        if ($valor === null || $valor === '') {
            return true;
        }
        if (str_starts_with($valor, 'done:')) {
            return false;
        }
        if (str_starts_with($valor, 'running:')) {
            $inicio = strtotime(substr($valor, 8));

            return $inicio === false || (time() - $inicio) >= 3 * 3600;
        }

        return true;
    }

    public function marcarInicio(string $periodo, ?DateTimeInterface $ref = null): void
    {
        Configuracao::definir(
            self::chaveEnvio($periodo, $ref),
            'running:'.(new DateTimeImmutable('now'))->format(DateTimeImmutable::ATOM)
        );
    }

    public function marcarConcluido(string $periodo, ?DateTimeInterface $ref = null): void
    {
        Configuracao::definir(
            self::chaveEnvio($periodo, $ref),
            'done:'.(new DateTimeImmutable('now'))->format(DateTimeImmutable::ATOM)
        );
    }
}
