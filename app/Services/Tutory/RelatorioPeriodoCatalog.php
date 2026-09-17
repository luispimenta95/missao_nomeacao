<?php

namespace App\Services\Tutory;

use App\Models\RelatorioPdfPeriodo;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Períodos de PDF que o admin pode gerar (contingência).
 *
 * Mesmas janelas do job oficial:
 * - Período 1 do mês M: dias 01–15, liberado a partir do dia 16 de M.
 * - Período 2 do mês M: dia 16–fim, liberado a partir do dia 1 de M+1.
 *
 * O mais antigo é janeiro/2026 período 1. Datas futuras nunca entram.
 */
class RelatorioPeriodoCatalog
{
    public const INICIO = '2026-01-01';

    /**
     * @return list<array{chave: string, year_month: string, period: string, label: string, unlocked_at: DateTimeImmutable, inicio: string, fim: string}>
     */
    public function listar(?DateTimeInterface $agora = null): array
    {
        $agora = $this->agora($agora);
        $inicio = new DateTimeImmutable(self::INICIO, $agora->getTimezone());
        $limiteMes = $agora->modify('first day of this month')->setTime(0, 0, 0);
        $out = [];

        for ($mes = $inicio; $mes <= $limiteMes; $mes = $mes->modify('first day of next month')) {
            foreach (['1', '2'] as $period) {
                if (! $this->estaLiberado($mes, $period, $agora)) {
                    continue;
                }
                $out[] = $this->montar($mes, $period);
            }
        }

        return $out;
    }

    /**
     * @return array{chave: string, year_month: string, period: string, label: string, unlocked_at: DateTimeImmutable, inicio: string, fim: string}|null
     */
    public function encontrar(string $chave, ?DateTimeInterface $agora = null): ?array
    {
        foreach ($this->listar($agora) as $item) {
            if ($item['chave'] === $chave) {
                return $item;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public function chaves(?DateTimeInterface $agora = null): array
    {
        return array_column($this->listar($agora), 'chave');
    }

    public function estaLiberado(DateTimeInterface $mes, string $period, ?DateTimeInterface $agora = null): bool
    {
        $agora = $this->agora($agora);
        $mes = $this->primeiroDiaDoMes($mes, $agora->getTimezone());
        $inicio = new DateTimeImmutable(self::INICIO, $agora->getTimezone());
        if ($mes < $inicio) {
            return false;
        }
        if (! in_array($period, ['1', '2'], true)) {
            return false;
        }

        return $agora >= $this->momentoLiberacao($mes, $period);
    }

    /**
     * @return array{0: string, 1: string} Y-m-d
     */
    public function datasIso(DateTimeInterface $mes, string $period): array
    {
        $mes = $this->primeiroDiaDoMes($mes);
        if ($period === '1') {
            return [$mes->format('Y-m-01'), $mes->format('Y-m-15')];
        }
        $ultimo = (int) $mes->format('t');

        return [$mes->format('Y-m-16'), $mes->format('Y-m-').str_pad((string) $ultimo, 2, '0', STR_PAD_LEFT)];
    }

    public static function chave(string $yearMonth, string $period): string
    {
        return $yearMonth.'|'.$period;
    }

    public function sincronizar(?DateTimeInterface $agora = null): int
    {
        $novos = 0;
        foreach ($this->listar($agora) as $item) {
            $row = RelatorioPdfPeriodo::query()->firstOrCreate(
                [
                    'year_month' => $item['year_month'],
                    'period' => $item['period'],
                ],
                [
                    'label' => $item['label'],
                    'unlocked_at' => $item['unlocked_at'],
                ],
            );
            if ($row->wasRecentlyCreated) {
                $novos++;
            }
        }

        return $novos;
    }

    /**
     * @return array{chave: string, year_month: string, period: string, label: string, unlocked_at: DateTimeImmutable, inicio: string, fim: string}
     */
    private function montar(DateTimeImmutable $mes, string $period): array
    {
        [$inicio, $fim] = $this->datasIso($mes, $period);
        $iniBr = DateTimeImmutable::createFromFormat('Y-m-d', $inicio)?->format('d/m/Y') ?? $inicio;
        $fimBr = DateTimeImmutable::createFromFormat('Y-m-d', $fim)?->format('d/m/Y') ?? $fim;
        $yearMonth = $mes->format('Y-m');

        return [
            'chave' => self::chave($yearMonth, $period),
            'year_month' => $yearMonth,
            'period' => $period,
            'label' => RelatorioConsolidadoLayout::rotuloPeriodo($period, $mes).' ('.$iniBr.' a '.$fimBr.')',
            'unlocked_at' => $this->momentoLiberacao($mes, $period),
            'inicio' => $inicio,
            'fim' => $fim,
        ];
    }

    private function momentoLiberacao(DateTimeImmutable $mes, string $period): DateTimeImmutable
    {
        if ($period === '1') {
            return $mes->setDate((int) $mes->format('Y'), (int) $mes->format('n'), 16)->setTime(0, 0, 0);
        }

        return $mes->modify('first day of next month')->setTime(0, 0, 0);
    }

    private function primeiroDiaDoMes(DateTimeInterface $mes, ?DateTimeZone $tz = null): DateTimeImmutable
    {
        $dt = $mes instanceof DateTimeImmutable
            ? $mes
            : DateTimeImmutable::createFromInterface($mes);
        if ($tz !== null) {
            $dt = $dt->setTimezone($tz);
        }

        return $dt->modify('first day of this month')->setTime(0, 0, 0);
    }

    private function agora(?DateTimeInterface $ref = null): DateTimeImmutable
    {
        $tz = new DateTimeZone((string) config('app.timezone'));
        if ($ref === null) {
            return new DateTimeImmutable('now', $tz);
        }

        return new DateTimeImmutable($ref->format('Y-m-d H:i:s'), $tz);
    }
}
