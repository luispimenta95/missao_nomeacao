<?php

namespace Tests\Unit;

use App\Mail\EmailRelatorioCoach;
use App\Models\Aluno;
use App\Services\Tutory\CoachReportDownloader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use ReflectionClass;
use Tests\TestCase;

class RemoverPdfsAposEmailTest extends TestCase
{
    use RefreshDatabase;

    private string $pasta;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pasta = sys_get_temp_dir().'/tutory-pdfs-'.uniqid('', true);
        mkdir($this->pasta, 0775, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->pasta)) {
            foreach (scandir($this->pasta) ?: [] as $arquivo) {
                if ($arquivo === '.' || $arquivo === '..') {
                    continue;
                }
                @unlink($this->pasta.'/'.$arquivo);
            }
            @rmdir($this->pasta);
        }
        parent::tearDown();
    }

    public function test_remove_pdfs_e_metricas_e_mantem_log(): void
    {
        $pdf = $this->pasta.'/relatorio_questoes_20260815_1200_Giovanna_1.pdf';
        $sidecar = $this->pasta.'/relatorio_questoes_20260815_1200_Giovanna_1.metricas.json';
        $log = $this->pasta.'/log_download_20260815_120000.txt';
        file_put_contents($pdf, '%PDF-1.4 fake');
        file_put_contents($sidecar, '{}');
        file_put_contents($log, 'ok');

        $this->chamar('removerPdfsBaixados', $this->downloader());

        $this->assertFileDoesNotExist($pdf);
        $this->assertFileDoesNotExist($sidecar);
        $this->assertFileExists($log);
    }

    public function test_apaga_pdfs_depois_de_enviar_email(): void
    {
        Mail::fake();

        $aluno = Aluno::create([
            'nome' => 'Giovanna',
            'email' => 'giovanna@example.com',
            'recebe_email' => true,
        ]);

        $pdfQuestoes = $this->pasta.'/relatorio_questoes_20260815_1200_Giovanna_1.pdf';
        $pdfProgresso = $this->pasta.'/relatorio_progresso_20260815_1200_Giovanna_1.pdf';
        file_put_contents($pdfQuestoes, '%PDF-1.4 fake');
        file_put_contents($pdfProgresso, '%PDF-1.4 fake');

        $this->chamar('enviarEmailsDosAlunos', $this->downloader());

        Mail::assertSent(EmailRelatorioCoach::class, function (EmailRelatorioCoach $mail) use ($aluno): bool {
            return $mail->hasTo($aluno->email);
        });
        $this->assertFileDoesNotExist($pdfQuestoes);
        $this->assertFileDoesNotExist($pdfProgresso);
    }

    public function test_apaga_pdfs_mesmo_quando_email_e_pulado(): void
    {
        Mail::fake();

        Aluno::create([
            'nome' => 'Giovanna',
            'email' => 'giovanna@example.com',
            'recebe_email' => false,
        ]);

        $pdf = $this->pasta.'/relatorio_questoes_20260815_1200_Giovanna_1.pdf';
        file_put_contents($pdf, '%PDF-1.4 fake');

        $this->chamar('enviarEmailsDosAlunos', $this->downloader());

        Mail::assertNothingSent();
        $this->assertFileDoesNotExist($pdf);
    }

    public function test_apaga_pdfs_quando_nao_ha_aluno_no_admin(): void
    {
        $pdf = $this->pasta.'/relatorio_questoes_20260815_1200_Giovanna_1.pdf';
        file_put_contents($pdf, '%PDF-1.4 fake');

        $this->chamar('enviarEmailsDosAlunos', $this->downloader());

        $this->assertFileDoesNotExist($pdf);
    }

    private function downloader(): CoachReportDownloader
    {
        $downloader = new CoachReportDownloader('1', false, static function (): void {});
        $ref = new ReflectionClass($downloader);
        $ref->getProperty('pastaDownload')->setValue($downloader, $this->pasta);

        return $downloader;
    }

    private function chamar(string $metodo, CoachReportDownloader $downloader): void
    {
        $ref = new ReflectionClass($downloader);
        $ref->getMethod($metodo)->invoke($downloader);
    }
}
