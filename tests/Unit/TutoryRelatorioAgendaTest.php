<?php

namespace Tests\Unit;

use App\Models\Configuracao;
use App\Services\Tutory\TutoryRelatorioAgenda;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TutoryRelatorioAgendaTest extends TestCase
{
    use RefreshDatabase;

    public function test_chave_do_periodo_1_usa_o_mes_corrente(): void
    {
        $ref = new DateTimeImmutable('2026-09-16 10:30:00');

        $this->assertSame(
            'tutory.envio.periodo.1.2026-09',
            TutoryRelatorioAgenda::chaveEnvio('1', $ref)
        );
    }

    public function test_chave_do_periodo_2_no_dia_1_usa_o_mes_anterior(): void
    {
        $ref = new DateTimeImmutable('2026-10-01 10:30:00');

        $this->assertSame(
            'tutory.envio.periodo.2.2026-09',
            TutoryRelatorioAgenda::chaveEnvio('2', $ref)
        );
    }

    public function test_se_pendente_pula_quando_ja_concluiu(): void
    {
        $agenda = new TutoryRelatorioAgenda;
        $ref = new DateTimeImmutable('2026-09-16 12:00:00');
        $agenda->marcarConcluido('1', $ref);

        $this->assertFalse($agenda->deveExecutar('1', $ref));
        $this->assertStringStartsWith('done:', (string) Configuracao::valor(
            TutoryRelatorioAgenda::chaveEnvio('1', $ref)
        ));
    }

    public function test_lock_stale_libera_nova_execucao(): void
    {
        $agenda = new TutoryRelatorioAgenda;
        $ref = new DateTimeImmutable('2026-09-16 10:30:00');
        Configuracao::definir(
            TutoryRelatorioAgenda::chaveEnvio('1', $ref),
            'running:'.(new DateTimeImmutable('-4 hours'))->format(DateTimeImmutable::ATOM)
        );

        $this->assertTrue($agenda->deveExecutar('1', $ref));
    }
}
