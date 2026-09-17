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

    public function test_job_adiciona_periodos_liberados_e_agenda_diaria(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-17 12:00:00', 'America/Sao_Paulo'));

        $this->artisan('tutory:liberar-periodos-pdf')
            ->expectsOutputToContain('Adicionados 17 período(s)')
            ->assertSuccessful();

        $this->assertSame(17, RelatorioPdfPeriodo::query()->count());
        $this->assertFalse(
            RelatorioPdfPeriodo::query()->where('year_month', '2026-09')->where('period', '2')->exists()
        );

        Carbon::setTestNow(Carbon::parse('2026-10-01 00:05:00', 'America/Sao_Paulo'));
        $this->artisan('tutory:liberar-periodos-pdf')
            ->expectsOutputToContain('Adicionados 1 período(s)')
            ->assertSuccessful();

        $this->assertTrue(
            RelatorioPdfPeriodo::query()->where('year_month', '2026-09')->where('period', '2')->exists()
        );

        $agenda = (string) file_get_contents(base_path('routes/console.php'));
        $this->assertStringContainsString("Schedule::command('tutory:liberar-periodos-pdf')", $agenda);
        $this->assertStringContainsString("->dailyAt('00:05')", $agenda);

        $workflow = (string) file_get_contents(base_path('.github/workflows/tutory-relatorios.yml'));
        $this->assertStringContainsString('tutory:liberar-periodos-pdf', $workflow);
        $this->assertStringContainsString('5 3 * * *', $workflow);
    }
}
