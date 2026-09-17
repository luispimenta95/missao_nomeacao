<?php

namespace Tests\Feature;

use App\Models\RelatorioPdfPeriodo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class LiberarPeriodosPdfCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_job_insere_e_remove_na_janela_nos_dias_1_e_16(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-16 00:05:00', 'America/Sao_Paulo'));

        $this->artisan('tutory:liberar-periodos-pdf')
            ->expectsOutputToContain('Quinzena de hoje: 2026-09|1')
            ->expectsOutputToContain('Inseridos: 12')
            ->assertSuccessful();

        $this->assertSame(12, RelatorioPdfPeriodo::query()->count());
        $this->assertFalse(
            RelatorioPdfPeriodo::query()->where('year_month', '2026-09')->where('period', '2')->exists()
        );
        $this->assertFalse(
            RelatorioPdfPeriodo::query()->where('year_month', '2026-01')->where('period', '1')->exists()
        );

        Carbon::setTestNow(Carbon::parse('2026-10-01 00:05:00', 'America/Sao_Paulo'));
        $this->artisan('tutory:liberar-periodos-pdf')
            ->expectsOutputToContain('Quinzena de hoje: 2026-09|2')
            ->assertSuccessful();

        $this->assertTrue(
            RelatorioPdfPeriodo::query()->where('year_month', '2026-09')->where('period', '2')->exists()
        );
        $this->assertFalse(
            RelatorioPdfPeriodo::query()->where('year_month', '2026-03')->where('period', '2')->exists()
        );
        $this->assertSame(12, RelatorioPdfPeriodo::query()->count());

        $agenda = (string) file_get_contents(base_path('routes/console.php'));
        $this->assertStringContainsString("Schedule::command('tutory:liberar-periodos-pdf')", $agenda);
        $this->assertStringContainsString('->monthlyOn(1, \'00:05\')', $agenda);
        $this->assertStringContainsString('->monthlyOn(16, \'00:05\')', $agenda);
        $this->assertStringNotContainsString("->dailyAt('00:05')", $agenda);

        $workflow = (string) file_get_contents(base_path('.github/workflows/tutory-relatorios.yml'));
        $this->assertStringContainsString('tutory:liberar-periodos-pdf', $workflow);
        $this->assertStringContainsString('5 3 1,16 * *', $workflow);
        $this->assertStringNotContainsString('"5 3 * * *"', $workflow);
    }
}
