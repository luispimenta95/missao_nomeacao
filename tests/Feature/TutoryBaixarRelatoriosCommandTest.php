<?php

namespace Tests\Feature;

use App\Models\Configuracao;
use App\Services\Tutory\TutoryRelatorioAgenda;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TutoryBaixarRelatoriosCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_se_pendente_nao_dispara_quando_periodo_ja_foi_enviado(): void
    {
        $chave = TutoryRelatorioAgenda::chaveEnvio('1');
        Configuracao::definir($chave, 'done:2026-09-16T12:00:00-03:00');

        $this->artisan('tutory:baixar-relatorios', [
            '--periodo' => '1',
            '--se-pendente' => true,
        ])
            ->expectsOutputToContain('Período já enviado ou em andamento')
            ->assertSuccessful();
    }

    public function test_scheduler_status_lista_os_jobs(): void
    {
        $this->artisan('tutory:scheduler-status')
            ->expectsOutputToContain('APP_TIMEZONE')
            ->expectsOutputToContain('tutory:baixar-relatorios')
            ->assertSuccessful();
    }
}
