<?php

namespace Tests\Unit;

use App\Mail\EmailBoasVindasNovato;
use App\Mail\EmailRelatorioCoach;
use App\Models\Aluno;
use App\Models\Configuracao;
use App\Services\Alunos\LimpezaMetricasNovato;
use App\Services\Alunos\TextoBoasVindasNovato;
use App\Services\Tutory\CoachReportDownloader;
use App\Services\Tutory\ResumoExecucaoRelatorio;
use Carbon\Carbon;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use ReflectionClass;
use Tests\TestCase;

class AlunoNovatoTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_menos_de_15_dias_e_novato_e_o_15o_dia_nao(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-03 12:00:00', 'America/Sao_Paulo'));

        $novato = new Aluno;
        $novato->created_at = Carbon::parse('2026-09-19 00:30:00', 'America/Sao_Paulo');
        $this->assertTrue($novato->ehNovato());

        $noLimite = new Aluno;
        $noLimite->created_at = Carbon::parse('2026-09-18 23:00:00', 'America/Sao_Paulo');
        $this->assertFalse($noLimite->ehNovato());
    }

    public function test_limpeza_tira_faixas_falsas_do_envio_de_01_10_e_de_quem_ainda_e_novato(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-20 12:00:00', 'America/Sao_Paulo'));

        $aindaNovato = $this->aluno('Lia Nova', '2026-10-10 09:00:00', '2026-10-1');
        $falsoNoEnvio = $this->aluno('Caio Recente', '2026-09-20 09:00:00', '2026-09-2');
        $veterano = $this->aluno('Dora Antiga', '2026-08-01 09:00:00', '2026-09-2');

        $this->assertSame(2, (new LimpezaMetricasNovato)->executar());

        $this->assertNull($aindaNovato->fresh()->last_performance);
        $this->assertNull($falsoNoEnvio->fresh()->metricas_periodo);
        $this->assertSame('Crítico', $veterano->fresh()->last_performance);
        $this->assertSame('2026-09-2', $veterano->fresh()->metricas_periodo);
    }

    public function test_novato_recebe_boas_vindas_sem_pdf_e_sem_gravar_metrica(): void
    {
        Mail::fake();
        Configuracao::definir(TextoBoasVindasNovato::CHAVE, 'Olá, {NOME}! Bem-vinda.');

        $aluno = Aluno::create([
            'nome' => 'Ana Novata',
            'email' => 'ana@example.com',
            'recebe_email' => true,
            'last_performance' => 'Crítico',
            'last_performance_codigo' => 'critico',
        ]);
        $aluno->forceFill(['created_at' => now()->subDays(3)])->save();

        $pasta = sys_get_temp_dir().'/tutory-novato-'.uniqid('', true);
        mkdir($pasta, 0775, true);
        $pdf = $pasta.'/relatorio_consolidado_20261001_1030_Ana_Novata_2.pdf';
        file_put_contents($pdf, '%PDF-1.4 fake');

        $downloader = new CoachReportDownloader('2', static function (): void {});
        $ref = new ReflectionClass($downloader);
        $ref->getProperty('pastaDownload')->setValue($downloader, $pasta);
        $resumo = new ResumoExecucaoRelatorio(
            inicio: new DateTimeImmutable('2026-10-01 10:30:05'),
            periodoRotulo: '16/09/2026 a 30/09/2026',
            motor: 'Dompdf',
            chaveEnvio: 'tutory.envio.periodo.2.2026-09',
            pasta: $pasta,
        );
        $ref->getProperty('resumoExecucao')->setValue($downloader, $resumo);

        $metodo = $ref->getMethod('enviarEmailsDosAlunos');
        $metodo->setAccessible(true);
        $metodo->invoke($downloader);

        Mail::assertSent(EmailBoasVindasNovato::class, function (EmailBoasVindasNovato $mail): bool {
            return $mail->hasTo('ana@example.com')
                && $mail->hasBcc('nayara@missaonomeacao.com.br');
        });
        Mail::assertNotSent(EmailRelatorioCoach::class);
        $this->assertSame('Crítico', $aluno->fresh()->last_performance);
        $this->assertSame(ResumoExecucaoRelatorio::BOAS_VINDAS, $resumo->envios[0]['situacao'] ?? null);
        $this->assertFileDoesNotExist($pdf);
        @rmdir($pasta);
    }

    private function aluno(string $nome, string $criadoEm, string $periodo): Aluno
    {
        $aluno = Aluno::create([
            'nome' => $nome,
            'email' => fake()->unique()->safeEmail(),
            'recebe_email' => true,
            'last_performance' => 'Crítico',
            'last_performance_codigo' => 'critico',
            'metricas_periodo' => $periodo,
        ]);
        $aluno->forceFill([
            'created_at' => $criadoEm,
            'updated_at' => $criadoEm,
        ])->save();

        return $aluno->fresh();
    }
}
