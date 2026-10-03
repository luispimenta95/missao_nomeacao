<?php

namespace Tests\Unit;

use App\Mail\EmailRelatorioExecucao;
use App\Models\Aluno;
use App\Services\Tutory\CoachReportDownloader;
use App\Services\Tutory\EnvioResumoExecucaoRelatorio;
use App\Services\Tutory\RelatorioExecucaoPdf;
use App\Services\Tutory\ResumoExecucaoRelatorio;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use ReflectionClass;
use RuntimeException;
use Tests\TestCase;

class ResumoExecucaoRelatorioTest extends TestCase
{
    use RefreshDatabase;

    private string $pasta;

    /** @var list<string> */
    private array $pdfsGerados = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->pasta = sys_get_temp_dir().'/tutory-resumo-'.uniqid('', true);
        mkdir($this->pasta, 0775, true);
    }

    protected function tearDown(): void
    {
        foreach ($this->pdfsGerados as $pdf) {
            if (is_file($pdf)) {
                @unlink($pdf);
            }
        }
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

    public function test_resumo_do_dia_01_10_reproduz_numeros_e_texto(): void
    {
        $resumo = $this->exemploDia01();

        $this->assertSame('3min 37s', $resumo->duracao());
        $this->assertSame([
            ['indicador' => 'Alunos ativos encontrados', 'resultado' => 20],
            ['indicador' => 'PDFs consolidados gerados', 'resultado' => 20],
            ['indicador' => 'Falhas na geração dos PDFs', 'resultado' => 0],
            ['indicador' => 'E-mails enviados', 'resultado' => 15],
            ['indicador' => 'E-mails pulados (recebe_email=false)', 'resultado' => 4],
            ['indicador' => 'Falhas / PDF não localizado no envio', 'resultado' => 3],
            ['indicador' => 'PDFs removidos após o envio', 'resultado' => 20],
        ], $resumo->indicadores());

        $linhas = $resumo->linhasComPdf();
        $this->assertSame(
            ['Edileusa Pires', 'Jaciara Almeida de Souza', 'Lucyana Ramos', 'Samara'],
            array_slice(array_column($linhas, 'nome'), 0, 4)
        );
        $this->assertSame('Aluno Teste', $linhas[array_key_last($linhas)]['nome']);
        $this->assertSame('Sem envio registrado no log', $linhas[array_key_last($linhas)]['email']);
        $this->assertSame('Enviado', collect($linhas)->firstWhere('nome', 'Giovanna')['email']);
        $this->assertSame('Não enviado — recebe_email=false', collect($linhas)->firstWhere('nome', 'Samara')['email']);

        $this->assertSame(
            'Três nomes apareceram na etapa de envio, mas o sistema informou que não encontrou PDF correspondente: '
            .'Isadora Gomes Silva (isadoragomessilva691@gmail.com), '
            .'Nayara Oliveira (nayaradeoliveira.contato@gmail.com) e '
            .'Walder (waldercsantos@gmail.com). '
            .'Esses três não fazem parte dos 20 alunos para os quais os PDFs foram gerados nesta execução.',
            $resumo->divergencias()[0]
        );
        $this->assertStringContainsString('Ponto de atenção: Aluno Teste teve PDF gerado', $resumo->divergencias()[1]);
        $this->assertSame(
            'A geração dos relatórios funcionou integralmente: 20 de 20 PDFs foram gerados, sem falhas. '
            .'O problema está concentrado na etapa de associação/envio dos e-mails. '
            .'A principal hipótese indicada pelo próprio log é divergência entre os nomes do cadastro administrativo e os nomes retornados pelo Tutory. '
            .'Também deve ser verificado por que Aluno Teste não recebeu um status de envio no log.',
            $resumo->conclusao()[0]
        );
    }

    public function test_nome_com_espaco_extra_casa_com_o_envio(): void
    {
        $resumo = new ResumoExecucaoRelatorio(
            inicio: new DateTimeImmutable('2026-10-01 10:30:05'),
            periodoRotulo: '16/09/2026 a 30/09/2026',
            motor: 'Dompdf',
            chaveEnvio: 'tutory.envio.periodo.2.2026-09',
            pasta: '/tmp',
        );
        $resumo->geracoes = [
            ['nome' => 'Laíra  Lacerda', 'sucesso' => true],
        ];
        $resumo->envios = [
            ['nome' => 'Laíra Lacerda', 'email' => 'laira@example.com', 'situacao' => ResumoExecucaoRelatorio::ENVIADO],
        ];

        $this->assertSame([], $resumo->geradosSemEnvio());
        $this->assertSame('Enviado', $resumo->linhasComPdf()[0]['email']);
    }

    public function test_pdf_do_resumo_sai_em_html_e_bytes(): void
    {
        $resumo = $this->exemploDia01();
        $html = (new RelatorioExecucaoPdf)->html($resumo);

        $this->assertStringContainsString('Relatório de Execução — Tutory', $html);
        $this->assertStringContainsString('16/09/2026 a 30/09/2026', $html);
        $this->assertStringContainsString('tutory.envio.periodo.2.2026-09', $html);
        $this->assertStringContainsString('log_download_20261001_103005.txt', $html);
        $this->assertStringContainsString('Isadora Gomes Silva', $html);
        $this->assertStringContainsString('Aluno Teste', $html);

        $bytes = (new RelatorioExecucaoPdf)->bytes($resumo);
        $this->assertStringStartsWith('%PDF', $bytes);
    }

    public function test_envio_vai_para_luis_com_cco(): void
    {
        Mail::fake();
        config([
            'mail.relatorio_execucao_address' => 'luispimenta.contato@gmail.com',
            'mail.bcc.address' => 'nayara@missaonomeacao.com.br',
        ]);

        $resumo = $this->exemploDia01();
        $pdf = (new EnvioResumoExecucaoRelatorio)->enviar($resumo);
        $this->pdfsGerados[] = (string) $pdf;

        $this->assertNotNull($pdf);
        $this->assertFileExists($pdf);
        $this->assertStringStartsWith('%PDF', (string) file_get_contents($pdf));
        $this->assertStringContainsString('tutory-execucao', $pdf);

        Mail::assertSent(EmailRelatorioExecucao::class, function (EmailRelatorioExecucao $mail): bool {
            return $mail->hasTo('luispimenta.contato@gmail.com')
                && $mail->hasBcc('nayara@missaonomeacao.com.br')
                && $mail->subject === 'Relatório de execução Tutory — 01/10/2026';
        });
    }

    public function test_destino_vazio_nao_envia(): void
    {
        Mail::fake();
        config(['mail.relatorio_execucao_address' => '']);

        $this->assertNull((new EnvioResumoExecucaoRelatorio)->enviar($this->exemploDia01()));
        Mail::assertNothingSent();
    }

    public function test_downloader_registra_envio_geracao_e_remocao(): void
    {
        Mail::fake();

        Aluno::create([
            'nome' => 'Giovanna',
            'email' => 'giovanna@example.com',
            'recebe_email' => true,
        ]);
        Aluno::create([
            'nome' => 'Isadora Gomes Silva',
            'email' => 'isadora@example.com',
            'recebe_email' => true,
        ]);

        file_put_contents($this->pasta.'/relatorio_consolidado_20261001_1030_Giovanna_2.pdf', '%PDF-1.4 fake');

        $downloader = new CoachReportDownloader('2', static function (): void {});
        $ref = new ReflectionClass($downloader);
        $ref->getProperty('pastaDownload')->setValue($downloader, $this->pasta);

        $resumo = new ResumoExecucaoRelatorio(
            inicio: new DateTimeImmutable('2026-10-01 10:30:05'),
            periodoRotulo: '16/09/2026 a 30/09/2026',
            motor: 'Dompdf',
            chaveEnvio: 'tutory.envio.periodo.2.2026-09',
            pasta: $this->pasta,
        );
        $ref->getProperty('resumoExecucao')->setValue($downloader, $resumo);

        $registrar = $ref->getMethod('registrarGeracao');
        $registrar->setAccessible(true);
        $registrar->invoke($downloader, [
            ['nome' => 'Giovanna', 'sucesso' => true],
            ['nome' => 'Aluno Teste', 'sucesso' => true],
        ], $this->pasta.'/log_download_20261001_103005.txt');

        $enviar = $ref->getMethod('enviarEmailsDosAlunos');
        $enviar->setAccessible(true);
        $enviar->invoke($downloader);

        $this->assertSame('log_download_20261001_103005.txt', $resumo->logArquivo);
        $this->assertSame(2, $resumo->pdfsGerados());
        $this->assertSame(1, $resumo->emailsEnviados());
        $this->assertSame(1, $resumo->falhasEnvio());
        $this->assertSame(['Aluno Teste'], $resumo->geradosSemEnvio());
        $this->assertSame(1, $resumo->pdfsRemovidos);
        $this->assertFileDoesNotExist($this->pasta.'/relatorio_consolidado_20261001_1030_Giovanna_2.pdf');
    }

    public function test_falha_ao_enviar_o_resumo_nao_interrompe(): void
    {
        Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('smtp fora'));

        $downloader = new CoachReportDownloader('2', static function (): void {});
        $resumo = new ResumoExecucaoRelatorio(
            inicio: new DateTimeImmutable('2026-10-01 10:30:05'),
            periodoRotulo: '16/09/2026 a 30/09/2026',
            motor: 'Dompdf',
            chaveEnvio: 'tutory.envio.periodo.2.2026-09',
            pasta: $this->pasta,
        );
        $ref = new ReflectionClass($downloader);
        $ref->getProperty('resumoExecucao')->setValue($downloader, $resumo);

        $metodo = $ref->getMethod('enviarResumoDaExecucao');
        $metodo->setAccessible(true);
        $metodo->invoke($downloader);

        $this->assertInstanceOf(DateTimeImmutable::class, $resumo->fim);
    }

    private function exemploDia01(): ResumoExecucaoRelatorio
    {
        $resumo = new ResumoExecucaoRelatorio(
            inicio: new DateTimeImmutable('2026-10-01 10:30:05'),
            periodoRotulo: '16/09/2026 a 30/09/2026',
            motor: 'Dompdf',
            chaveEnvio: 'tutory.envio.periodo.2.2026-09',
            pasta: '/home/u317623000/domains/missaonomeacao.com.br/public_html/server/public/pdfs',
            fuso: 'America/Sao_Paulo',
        );
        $resumo->fim = new DateTimeImmutable('2026-10-01 10:33:42');
        $resumo->logArquivo = 'log_download_20261001_103005.txt';
        $resumo->pdfsRemovidos = 20;

        $pulados = ['Edileusa Pires', 'Jaciara Almeida de Souza', 'Lucyana Ramos', 'Samara'];
        $enviados = [
            'Larissa Gomes', 'Taynah Teles', 'Andréa Teresa', 'Amanda Carvalho Vieira',
            'Shirlei', 'Paulinho Marra', 'Georgia', 'Jhullya', 'Laíra Lacerda',
            'Giovanna', 'Andreza', 'Mychel', 'Raissa', 'Karol', 'Talita',
        ];

        $resumo->geracoes = array_map(
            static fn (string $nome): array => ['nome' => $nome, 'sucesso' => true],
            [...$pulados, ...$enviados, 'Aluno Teste']
        );
        $resumo->envios = [
            ['nome' => 'Isadora Gomes Silva', 'email' => 'isadoragomessilva691@gmail.com', 'situacao' => ResumoExecucaoRelatorio::SEM_PDF],
            ['nome' => 'Nayara Oliveira', 'email' => 'nayaradeoliveira.contato@gmail.com', 'situacao' => ResumoExecucaoRelatorio::SEM_PDF],
            ['nome' => 'Walder', 'email' => 'waldercsantos@gmail.com', 'situacao' => ResumoExecucaoRelatorio::SEM_PDF],
        ];
        foreach ($pulados as $nome) {
            $resumo->envios[] = ['nome' => $nome, 'email' => 'aluno@example.com', 'situacao' => ResumoExecucaoRelatorio::PULADO];
        }
        foreach ($enviados as $nome) {
            $resumo->envios[] = ['nome' => $nome, 'email' => 'aluno@example.com', 'situacao' => ResumoExecucaoRelatorio::ENVIADO];
        }

        return $resumo;
    }
}
