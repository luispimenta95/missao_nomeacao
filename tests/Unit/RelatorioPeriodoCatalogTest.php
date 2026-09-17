<?php

namespace Tests\Unit;

use App\Models\RelatorioPdfPeriodo;
use App\Services\Tutory\RelatorioPeriodoCatalog;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelatorioPeriodoCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_em_17_de_setembro_nao_libera_periodo_2_do_mes(): void
    {
        $catalog = new RelatorioPeriodoCatalog;
        $agora = new DateTimeImmutable('2026-09-17 12:00:00');
        $chaves = $catalog->chaves($agora);

        $this->assertContains('2026-01|1', $chaves);
        $this->assertContains('2026-09|1', $chaves);
        $this->assertNotContains('2026-09|2', $chaves);
        $this->assertNotContains('2026-10|1', $chaves);
        $this->assertSame('2026-09|1', $chaves[array_key_last($chaves)]);
        $this->assertCount(17, $chaves);
    }

    public function test_em_1_de_outubro_libera_periodo_2_de_setembro(): void
    {
        $catalog = new RelatorioPeriodoCatalog;
        $agora = new DateTimeImmutable('2026-10-01 00:00:00');
        $chaves = $catalog->chaves($agora);

        $this->assertContains('2026-09|2', $chaves);
        $this->assertNotContains('2026-10|1', $chaves);
        $this->assertSame('2026-09|2', $chaves[array_key_last($chaves)]);
        $this->assertCount(18, $chaves);
    }

    public function test_antes_de_16_de_janeiro_de_2026_nao_ha_periodo(): void
    {
        $catalog = new RelatorioPeriodoCatalog;

        $this->assertSame([], $catalog->chaves(new DateTimeImmutable('2026-01-15 23:59:59')));
        $this->assertSame(['2026-01|1'], $catalog->chaves(new DateTimeImmutable('2026-01-16 00:00:00')));
        $this->assertSame([], $catalog->chaves(new DateTimeImmutable('2025-12-31 23:59:59')));
    }

    public function test_datas_do_periodo_batem_com_o_job_oficial(): void
    {
        $catalog = new RelatorioPeriodoCatalog;
        $setembro = new DateTimeImmutable('2026-09-01');

        $this->assertSame(['2026-09-01', '2026-09-15'], $catalog->datasIso($setembro, '1'));
        $this->assertSame(['2026-09-16', '2026-09-30'], $catalog->datasIso($setembro, '2'));
    }

    public function test_sincronizar_grava_so_periodos_liberados(): void
    {
        $catalog = new RelatorioPeriodoCatalog;
        $agora = new DateTimeImmutable('2026-09-17 12:00:00');

        $this->assertSame(17, $catalog->sincronizar($agora));
        $this->assertSame(0, $catalog->sincronizar($agora));
        $this->assertSame(17, RelatorioPdfPeriodo::query()->count());
        $this->assertFalse(
            RelatorioPdfPeriodo::query()->where('year_month', '2026-09')->where('period', '2')->exists()
        );

        $this->assertSame(1, $catalog->sincronizar(new DateTimeImmutable('2026-10-01 00:00:00')));
        $this->assertTrue(
            RelatorioPdfPeriodo::query()->where('year_month', '2026-09')->where('period', '2')->exists()
        );
    }
}
