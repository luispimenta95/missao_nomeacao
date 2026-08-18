<?php

namespace Tests\Unit;

use App\Services\Tutory\CoachReportDownloader;
use ReflectionClass;
use Tests\TestCase;

class RelatoriosMentorTest extends TestCase
{
    public function test_relatorios_cobre_todos_os_modelos_do_menu_do_mentor(): void
    {
        $ref = new ReflectionClass(CoachReportDownloader::class);
        $relatorios = $ref->getConstant('RELATORIOS');

        $this->assertSame(
            ['desempenho', 'aluno', 'horas-liquidas', 'questoes', 'progresso'],
            array_column($relatorios, 'model')
        );
        $this->assertSame(
            ['Desempenho', 'Estudos', 'Horas Líquidas', 'Desempenho em Questões', 'Progresso do plano'],
            array_column($relatorios, 'nome')
        );
    }

    public function test_extrai_modelo_com_hifen_do_nome_do_arquivo(): void
    {
        $meta = $this->chamar('extrairMetaDoArquivoPdf', 'relatorio_horas-liquidas_20260815_1200_Giovanna_1.pdf');

        $this->assertSame('horas-liquidas', $meta['model']);
        $this->assertSame('Giovanna', $meta['nome']);
    }

    public function test_extrai_modelo_consolidado_do_nome_do_arquivo(): void
    {
        $meta = $this->chamar('extrairMetaDoArquivoPdf', 'relatorio_consolidado_20260818_2230_Giovanna_1.pdf');

        $this->assertSame('consolidado', $meta['model']);
        $this->assertSame('Giovanna', $meta['nome']);
    }

    public function test_encontra_apenas_o_pdf_consolidado_do_aluno(): void
    {
        $pasta = sys_get_temp_dir().'/tutory-relatorios-'.uniqid('', true);
        mkdir($pasta, 0775, true);

        try {
            foreach (['desempenho', 'aluno', 'horas-liquidas', 'questoes', 'progresso'] as $model) {
                file_put_contents(
                    $pasta.'/relatorio_'.$model.'_20260815_1200_Giovanna_1.pdf',
                    '%PDF-1.4 fake'
                );
            }
            file_put_contents(
                $pasta.'/relatorio_consolidado_20260818_2230_Giovanna_1.pdf',
                '%PDF-1.4 consolidado'
            );

            $downloader = new CoachReportDownloader('1', false, static function (): void {});
            $ref = new ReflectionClass($downloader);
            $ref->getProperty('pastaDownload')->setValue($downloader, $pasta);

            $pdfs = $ref->getMethod('encontrarPdfsAluno')->invoke($downloader, 'Giovanna');
            $arquivos = array_map('basename', $pdfs);

            $this->assertCount(1, $pdfs);
            $this->assertSame(['relatorio_consolidado_20260818_2230_Giovanna_1.pdf'], $arquivos);
        } finally {
            foreach (scandir($pasta) ?: [] as $arquivo) {
                if ($arquivo === '.' || $arquivo === '..') {
                    continue;
                }
                @unlink($pasta.'/'.$arquivo);
            }
            @rmdir($pasta);
        }
    }

    public function test_script_de_composicao_extrai_as_secoes_pedidas(): void
    {
        $script = base_path('scripts/tutory-compose-pdf.mjs');
        $this->assertFileExists($script);
        $src = (string) file_get_contents($script);

        $this->assertStringContainsString('.main-header-card', $src);
        $this->assertStringContainsString('.metrics-grid', $src);
        $this->assertStringContainsString('chart_horas_estudo', $src);
        $this->assertStringContainsString('chart_performance', $src);
        $this->assertStringContainsString('h2.section-5', $src);
        $this->assertStringContainsString('#tabela_estudos', $src);
        $this->assertStringContainsString('#tabela_revisoes', $src);
        $this->assertStringContainsString('chart_line_comparativo', $src);
        $this->assertStringContainsString('#tabela_horas_liquidas', $src);
        $this->assertStringContainsString('chart_questoes_dia', $src);
        $this->assertStringContainsString('#tabela_questoes', $src);
        $this->assertStringContainsString('chart_horas_diarias', $src);
        $this->assertStringContainsString('.insights-panel', $src);
        $this->assertStringContainsString('mn-insights-wrap', $src);
        $this->assertStringContainsString('--url-desempenho', $src);
        $this->assertStringNotContainsString('chart_panorama', $src);
        $this->assertStringNotContainsString('chart_bolha_questoes', $src);
        $this->assertStringNotContainsString('chart_pie_horas_disciplina', $src);
    }

    /**
     * @return array{model: string, nome: string}|null
     */
    private function chamar(string $metodo, string $arquivo): mixed
    {
        $downloader = new CoachReportDownloader('1', false, static function (): void {});
        $ref = new ReflectionClass($downloader);

        return $ref->getMethod($metodo)->invoke($downloader, $arquivo);
    }
}
