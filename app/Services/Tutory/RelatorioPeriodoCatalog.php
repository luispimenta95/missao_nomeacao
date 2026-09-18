<?php

namespace App\Services\Tutory;

use App\Models\Configuracao;
use App\Models\RelatorioPdfPeriodo;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Períodos de PDF que o admin pode gerar (contingência).
 *
 * Mesmas janelas do job oficial:
 * - Período 1 do mês M: dias 01–15, liberado a partir do dia 16 de M.
 * - Período 2 do mês M: dia 16–fim, liberado a partir do dia 1 de M+1.
 *
 * O combo guarda só os 2 períodos dos últimos N meses (padrão 12 linhas).
 * O mais antigo possível continua sendo janeiro/2026 período 1.
 */
class RelatorioPeriodoCatalog
{
    public const INICIO = '2026-01-01';

    public const CONFIG_CHAVE = 'pdf_contingencia_meses';

    public const MESES_PADRAO = 6;

    public const MESES_MIN = 1;

    /**
     * Meses desde janeiro/2026 até o mês corrente.
     * Em setembro/2026 = 8; em outubro/2026 = 9; e assim por diante.
     */
    public static function mesesMaximos(?DateTimeInterface $agora = null): int
    {
        $tz = new DateTimeZone((string) config('app.timezone'));
        if ($agora === null) {
            $now = now()->timezone($tz);
            $agora = new DateTimeImmutable($now->toDateTimeString(), $tz);
        } elseif (! $agora instanceof DateTimeImmutable) {
            $agora = DateTimeImmutable::createFromInterface($agora);
        }
        $agora = $agora->setTimezone($tz)->modify('first day of this month')->setTime(0, 0, 0);
        $inicio = new DateTimeImmutable(self::INICIO, $tz);
        if ($agora <= $inicio) {
            return self::MESES_MIN;
        }
        $diff = $inicio->diff($agora);

        return max(self::MESES_MIN, $diff->y * 12 + $diff->m);
    }

    public static function mesesVisiveis(): int
    {
        $padrao = max(self::MESES_MIN, min(self::mesesMaximos(), self::MESES_PADRAO));
        try {
            if (! class_exists(Configuracao::class) || ! Schema::hasTable('configuracoes')) {
                return $padrao;
            }
            $valor = Configuracao::valor(self::CONFIG_CHAVE);
        } catch (Throwable) {
            return $padrao;
        }

        if ($valor === null || $valor === '' || ! is_numeric($valor)) {
            return $padrao;
        }

        return max(self::MESES_MIN, min(self::mesesMaximos(), (int) $valor));
    }

    public static function limiteLinhas(?int $meses = null): int
    {
        return ($meses ?? self::mesesVisiveis()) * 2;
    }

    /**
     * @return list<array{chave: string, year_month: string, period: string, label: string, unlocked_at: DateTimeImmutable, inicio: string, fim: string}>
     */
    public function listar(?DateTimeInterface $agora = null): array
    {
        $todos = $this->todosLiberados($agora);
        $limite = self::limiteLinhas();
        if (count($todos) <= $limite) {
            return $todos;
        }

        return array_values(array_slice($todos, -$limite));
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

    /**
     * Insere os períodos da janela e apaga os que saíram dela.
     *
     * @return array{inseridos: int, removidos: int}
     */
    public function sincronizar(?DateTimeInterface $agora = null): array
    {
        $desejados = $this->listar($agora);
        $chaves = [];
        $inseridos = 0;

        foreach ($desejados as $item) {
            $chaves[] = $item['chave'];
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
                $inseridos++;
            } elseif ($row->label !== $item['label']) {
                $row->label = $item['label'];
                $row->unlocked_at = $item['unlocked_at'];
                $row->save();
            }
        }

        $removidos = 0;
        foreach (RelatorioPdfPeriodo::query()->get() as $row) {
            $chave = self::chave((string) $row->year_month, (string) $row->period);
            if (in_array($chave, $chaves, true)) {
                continue;
            }
            $row->delete();
            $removidos++;
        }

        return [
            'inseridos' => $inseridos,
            'removidos' => $removidos,
        ];
    }

    /**
     * Dia 16 → período 1 do mês atual. Dia 1 → período 2 do mês anterior.
     *
     * @return array{chave: string, year_month: string, period: string, label: string, unlocked_at: DateTimeImmutable, inicio: string, fim: string}|null
     */
    public function periodoDaQuinzena(?DateTimeInterface $agora = null): ?array
    {
        $agora = $this->agora($agora);
        $dia = (int) $agora->format('j');
        if ($dia === 16) {
            $mes = $agora->modify('first day of this month')->setTime(0, 0, 0);

            return $this->estaLiberado($mes, '1', $agora) ? $this->montar($mes, '1') : null;
        }
        if ($dia === 1) {
            $mes = $agora->modify('first day of last month')->setTime(0, 0, 0);

            return $this->estaLiberado($mes, '2', $agora) ? $this->montar($mes, '2') : null;
        }

        return null;
    }

    /**
     * @return list<array{chave: string, year_month: string, period: string, label: string, unlocked_at: DateTimeImmutable, inicio: string, fim: string}>
     */
    private function todosLiberados(?DateTimeInterface $agora = null): array
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
            $now = now()->timezone($tz);

            return new DateTimeImmutable($now->toDateTimeString(), $tz);
        }

        return new DateTimeImmutable($ref->format('Y-m-d H:i:s'), $tz);
    }
}
