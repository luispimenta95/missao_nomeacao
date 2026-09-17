<?php

namespace Tests\Unit;

use App\Models\Configuracao;
use App\Models\RelatorioPdfPeriodo;
use App\Services\Tutory\RelatorioPeriodoCatalog;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RelatorioPeriodoCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_em_17_de_setembro_o_combo_tem_12_periodos_sem_p2_do_mes(): void
    {
        $catalog = new RelatorioPeriodoCatalog;
        $agora = new DateTimeImmutable('2026-09-17 12:00:00');
        $chaves = $catalog->chaves($agora);

        $this->assertCount(12, $chaves);
        $this->assertSame('2026-03|2', $chaves[0]);
        $this->assertContains('2026-09|1', $chaves);
        $this->assertNotContains('2026-01|1', $chaves);
        $this->assertNotContains('2026-03|1', $chaves);
        $this->assertNotContains('2026-09|2', $chaves);
        $this->assertNotContains('2026-10|1', $chaves);
        $this->assertSame('2026-09|1', $chaves[array_key_last($chaves)]);
        $this->assertFalse($catalog->estaLiberado(new DateTimeImmutable('2026-09-01'), '2', $agora));
        $this->assertTrue($catalog->estaLiberado(new DateTimeImmutable('2026-09-01'), '1', $agora));
    }

    public function test_em_1_de_outubro_entra_p2_de_setembro_e_sai_o_mais_antigo(): void
    {
        $catalog = new RelatorioPeriodoCatalog;
        $agora = new DateTimeImmutable('2026-10-01 00:00:00');
        $chaves = $catalog->chaves($agora);

        $this->assertCount(12, $chaves);
        $this->assertSame('2026-04|1', $chaves[0]);
        $this->assertContains('2026-09|2', $chaves);
        $this->assertNotContains('2026-03|2', $chaves);
        $this->assertNotContains('2026-10|1', $chaves);
        $this->assertSame('2026-09|2', $chaves[array_key_last($chaves)]);
    }

    public function test_antes_de_16_de_janeiro_de_2026_nao_ha_periodo(): void
    {
        $catalog = new RelatorioPeriodoCatalog;

        $this->assertSame([], $catalog->chaves(new DateTimeImmutable('2026-01-15 23:59:59')));
        $this->assertSame(['2026-01|1'], $catalog->chaves(new DateTimeImmutable('2026-01-16 00:00:00')));
        $this->assertSame([], $catalog->chaves(new DateTimeImmutable('2025-12-31 23:59:59')));
    }

    public function test_janela_de_meses_e_personalizavel(): void
    {
        Configuracao::definir(RelatorioPeriodoCatalog::CONFIG_CHAVE, '3');
        $this->assertSame(3, RelatorioPeriodoCatalog::mesesVisiveis());
        $this->assertSame(6, RelatorioPeriodoCatalog::limiteLinhas());

        $catalog = new RelatorioPeriodoCatalog;
        $chaves = $catalog->chaves(new DateTimeImmutable('2026-09-17 12:00:00'));
        $this->assertCount(6, $chaves);
        $this->assertSame('2026-06|2', $chaves[0]);
        $this->assertSame('2026-09|1', $chaves[array_key_last($chaves)]);
    }

    public function test_datas_do_periodo_batem_com_o_job_oficial(): void
    {
        $catalog = new RelatorioPeriodoCatalog;
        $setembro = new DateTimeImmutable('2026-09-01');

        $this->assertSame(['2026-09-01', '2026-09-15'], $catalog->datasIso($setembro, '1'));
        $this->assertSame(['2026-09-16', '2026-09-30'], $catalog->datasIso($setembro, '2'));
    }

    public function test_quinzena_do_dia_16_e_p1_do_mes_e_dia_1_e_p2_do_anterior(): void
    {
        $catalog = new RelatorioPeriodoCatalog;

        $p1 = $catalog->periodoDaQuinzena(new DateTimeImmutable('2026-09-16 00:05:00'));
        $this->assertSame('2026-09|1', $p1['chave'] ?? null);

        $p2 = $catalog->periodoDaQuinzena(new DateTimeImmutable('2026-10-01 00:05:00'));
        $this->assertSame('2026-09|2', $p2['chave'] ?? null);

        $this->assertNull($catalog->periodoDaQuinzena(new DateTimeImmutable('2026-09-17 12:00:00')));
    }

    public function test_sincronizar_insere_a_janela_e_apaga_o_que_sai(): void
    {
        $catalog = new RelatorioPeriodoCatalog;
        $agora = new DateTimeImmutable('2026-09-17 12:00:00');

        $this->assertSame(['inseridos' => 12, 'removidos' => 0], $catalog->sincronizar($agora));
        $this->assertSame(['inseridos' => 0, 'removidos' => 0], $catalog->sincronizar($agora));
        $this->assertSame(12, RelatorioPdfPeriodo::query()->count());
        $this->assertFalse(
            RelatorioPdfPeriodo::query()->where('year_month', '2026-09')->where('period', '2')->exists()
        );
        $this->assertFalse(
            RelatorioPdfPeriodo::query()->where('year_month', '2026-01')->where('period', '1')->exists()
        );

        $outubro = $catalog->sincronizar(new DateTimeImmutable('2026-10-01 00:00:00'));
        $this->assertSame(1, $outubro['inseridos']);
        $this->assertSame(1, $outubro['removidos']);
        $this->assertTrue(
            RelatorioPdfPeriodo::query()->where('year_month', '2026-09')->where('period', '2')->exists()
        );
        $this->assertFalse(
            RelatorioPdfPeriodo::query()->where('year_month', '2026-03')->where('period', '2')->exists()
        );
        $this->assertSame(12, RelatorioPdfPeriodo::query()->count());
    }
}
