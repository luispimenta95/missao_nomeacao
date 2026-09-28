<?php

namespace Tests\Unit;

use App\Models\Configuracao;
use App\Services\Tutory\TutoryAgendaDoDia;
use App\Services\Tutory\TutoryAgendaExecutor;
use App\Services\Tutory\TutoryRelatorioAgenda;
use App\Services\Tutory\TutorySchedulerKick;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TutoryAgendaDoDiaTest extends TestCase
{
    use RefreshDatabase;

    private DateTimeZone $fuso;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fuso = new DateTimeZone('America/Sao_Paulo');
    }

    public function test_sync_abre_as_6_e_continua_devido_o_resto_do_dia(): void
    {
        $agenda = new TutoryAgendaDoDia;

        $this->assertNotContains('sincronizar-alunos', $this->ids($agenda, '2026-09-28 05:59:00'));
        $this->assertContains('sincronizar-alunos', $this->ids($agenda, '2026-09-28 06:00:00'));
        $this->assertContains('sincronizar-alunos', $this->ids($agenda, '2026-09-28 11:25:00'));
        $this->assertNotContains('relatorio-1', $this->ids($agenda, '2026-09-28 11:25:00'));
        $this->assertNotContains('liberar-periodos', $this->ids($agenda, '2026-09-28 11:25:00'));
    }

    public function test_relatorio_periodo_1_na_janela_e_na_retentativa(): void
    {
        $agenda = new TutoryAgendaDoDia;

        $this->assertNotContains('relatorio-1', $this->ids($agenda, '2026-09-16 10:29:00'));
        $this->assertContains('relatorio-1', $this->ids($agenda, '2026-09-16 10:30:00'));
        $this->assertContains('relatorio-1', $this->ids($agenda, '2026-09-16 22:59:00'));
        $this->assertNotContains('relatorio-1', $this->ids($agenda, '2026-09-16 23:00:00'));
        $this->assertNotContains('relatorio-1', $this->ids($agenda, '2026-09-17 10:59:00'));
        $this->assertContains('relatorio-1', $this->ids($agenda, '2026-09-17 11:00:00'));
        $this->assertNotContains('relatorio-1', $this->ids($agenda, '2026-09-17 23:00:00'));
    }

    public function test_relatorio_periodo_2_e_liberacao_no_dia_1(): void
    {
        $agenda = new TutoryAgendaDoDia;

        $meiaNoite = $this->ids($agenda, '2026-10-01 00:05:00');
        $this->assertContains('liberar-periodos', $meiaNoite);
        $this->assertNotContains('sincronizar-alunos', $meiaNoite);
        $this->assertNotContains('relatorio-2', $meiaNoite);

        $dezEMeia = $this->ids($agenda, '2026-10-01 10:30:00');
        $this->assertContains('liberar-periodos', $dezEMeia);
        $this->assertContains('sincronizar-alunos', $dezEMeia);
        $this->assertContains('relatorio-2', $dezEMeia);

        $this->assertNotContains('relatorio-2', $this->ids($agenda, '2026-10-02 10:59:00'));
        $this->assertContains('relatorio-2', $this->ids($agenda, '2026-10-02 12:00:00'));
        $this->assertNotContains('relatorio-2', $this->ids($agenda, '2026-10-02 23:00:00'));
    }

    public function test_executor_roda_o_sync_uma_vez_por_dia(): void
    {
        $executor = new TutoryAgendaExecutor;
        $quando = new DateTimeImmutable('2026-09-28 11:25:00', $this->fuso);
        $rodados = [];

        $executor->executar($quando, function (string $comando, array $argumentos) use (&$rodados): int {
            $rodados[] = [$comando, $argumentos];

            return 0;
        });
        $executor->executar($quando, function (string $comando, array $argumentos) use (&$rodados): int {
            $rodados[] = [$comando, $argumentos];

            return 0;
        });

        $this->assertSame([
            ['tutory:sincronizar-alunos', []],
        ], $rodados);
        $this->assertStringStartsWith(
            'done:',
            (string) Configuracao::valor('tutory.job.sincronizar-alunos.2026-09-28')
        );
        $this->assertNotNull(Configuracao::valor(TutoryAgendaDoDia::HEARTBEAT));
    }

    public function test_executor_repete_o_sync_se_a_tentativa_falhou(): void
    {
        $executor = new TutoryAgendaExecutor;
        $quando = new DateTimeImmutable('2026-09-28 06:00:00', $this->fuso);
        $tentativas = 0;

        $executor->executar($quando, function () use (&$tentativas): int {
            $tentativas++;

            return 1;
        });
        $primeira = $executor->executar($quando, function () use (&$tentativas): int {
            $tentativas++;

            return 0;
        });

        $this->assertSame(2, $tentativas);
        $this->assertSame(['sincronizar-alunos'], $primeira['executados']);
    }

    public function test_executor_nao_repete_sync_em_andamento(): void
    {
        Configuracao::definir(
            'tutory.job.sincronizar-alunos.2026-09-28',
            'running:'.(new DateTimeImmutable('now'))->format(DateTimeImmutable::ATOM)
        );
        $executor = new TutoryAgendaExecutor;
        $rodados = 0;

        $resultado = $executor->executar(
            new DateTimeImmutable('2026-09-28 11:25:00', $this->fuso),
            function () use (&$rodados): int {
                $rodados++;

                return 0;
            }
        );

        $this->assertSame(0, $rodados);
        $this->assertSame(['sincronizar-alunos'], $resultado['pulados']);
    }

    public function test_executor_retoma_sync_com_running_velho(): void
    {
        Configuracao::definir(
            'tutory.job.sincronizar-alunos.2026-09-28',
            'running:'.(new DateTimeImmutable('-2 hours'))->format(DateTimeImmutable::ATOM)
        );
        $executor = new TutoryAgendaExecutor;
        $rodados = 0;

        $executor->executar(
            new DateTimeImmutable('2026-09-28 11:25:00', $this->fuso),
            function () use (&$rodados): int {
                $rodados++;

                return 0;
            }
        );

        $this->assertSame(1, $rodados);
    }

    public function test_executor_pula_relatorio_que_ja_foi_enviado(): void
    {
        $quando = new DateTimeImmutable('2026-09-16 15:00:00', $this->fuso);
        (new TutoryRelatorioAgenda)->marcarConcluido('1', $quando);

        $executor = new TutoryAgendaExecutor;
        $rodados = [];
        $resultado = $executor->executar($quando, function (string $comando) use (&$rodados): int {
            $rodados[] = $comando;

            return 0;
        });

        $this->assertNotContains('tutory:baixar-relatorios', $rodados);
        $this->assertContains('relatorio-1', $resultado['pulados']);
        $this->assertContains('tutory:sincronizar-alunos', $rodados);
    }

    public function test_visita_nao_dispara_geracao_de_pdf(): void
    {
        $rodados = [];
        (new TutoryAgendaExecutor)->executar(
            new DateTimeImmutable('2026-09-16 15:00:00', $this->fuso),
            function (string $comando) use (&$rodados): int {
                $rodados[] = $comando;

                return 0;
            },
            false,
        );

        $this->assertContains('tutory:sincronizar-alunos', $rodados);
        $this->assertContains('tutory:liberar-periodos-pdf', $rodados);
        $this->assertNotContains('tutory:baixar-relatorios', $rodados);

        $provider = (string) file_get_contents(base_path('app/Providers/AppServiceProvider.php'));
        $this->assertStringContainsString('TutorySchedulerKick', $provider);
        $kick = (string) file_get_contents(base_path('app/Services/Tutory/TutorySchedulerKick.php'));
        $this->assertStringContainsString('--sem-relatorios', $kick);
    }

    public function test_visita_dispara_o_scheduler_sem_rodar_sync_antes_das_6(): void
    {
        $this->travelTo(new DateTimeImmutable('2026-09-15 05:00:00', $this->fuso));

        app(TutorySchedulerKick::class)->disparar();
        app(TutorySchedulerKick::class)->disparar();

        $this->assertNotNull(Configuracao::valor(TutoryAgendaDoDia::HEARTBEAT));
        $this->assertNull(Configuracao::valor('tutory.job.sincronizar-alunos.2026-09-15'));
    }

    public function test_comando_ocioso_nao_dispara_sync(): void
    {
        $this->travelTo(new DateTimeImmutable('2026-09-15 05:00:00', $this->fuso));

        $this->artisan('tutory:executar-agendados')->assertSuccessful();

        $this->assertNotNull(Configuracao::valor(TutoryAgendaDoDia::HEARTBEAT));
        $this->assertNull(Configuracao::valor('tutory.job.sincronizar-alunos.2026-09-15'));
    }

    /**
     * @return list<string>
     */
    private function ids(TutoryAgendaDoDia $agenda, string $quando): array
    {
        return array_column($agenda->devidos(new DateTimeImmutable($quando, $this->fuso)), 'id');
    }
}
