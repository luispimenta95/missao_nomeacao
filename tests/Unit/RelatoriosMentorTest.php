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

    public function test_binario_node_nao_quebra_quando_o_path_e_minimo(): void
    {
        $downloader = new CoachReportDownloader('1', false, static function (): void {});
        $ref = new ReflectionClass($downloader);
        $bin = $ref->getMethod('binarioNode')->invoke($downloader);

        $this->assertTrue($bin === null || is_string($bin));
        if (is_string($bin)) {
            $this->assertFileExists($bin);
        }
    }

    public function test_fallback_dompdf_gera_pdf_consolidado_a_partir_dos_htmls(): void
    {
        $pasta = sys_get_temp_dir().'/tutory-consolidado-'.uniqid('', true);
        mkdir($pasta, 0775, true);
        $destino = $pasta.'/relatorio_consolidado_20260818_2300_Giovanna_1.pdf';

        $htmls = [
            'desempenho' => '<html><body>
                <div class="main-header-card">
                    <div class="title-section"><h1>Seu desempenho</h1><p>Veja seu desempenho</p></div>
                    <div class="aluno-details"><h4>Giovanna</h4><p>Curso X</p></div>
                </div>
                <div class="metrics-grid">
                    <div class="metric-card"><p class="metric-label">Total de Horas</p><p class="metric-value">10h</p></div>
                    <div class="metric-card"><p class="metric-label">% de acertos</p><p class="metric-value">80%</p></div>
                </div>
                <script>var chartData = {horasEstudo:{labels:[],horas:[],mediaTopAlunos:{}},performance:{disciplinas:[],valores:[]}};</script>
            </body></html>',
            'aluno' => '<html><body>
                <h2 class="section-5">Histórico de Metas</h2>
                <p>Metas do período</p>
                <table id="tabela_estudos"><thead><tr><td>Disciplina</td></tr></thead><tbody></tbody></table>
                <h2 class="section-4">Revisões no Período</h2>
                <p>Revisões</p>
                <table id="tabela_revisoes"><thead><tr><td>Disciplina</td></tr></thead><tbody></tbody></table>
            </body></html>',
            'horas-liquidas' => '<html><body>
                <h2 class="section-2">Desempenho ao longo do Tempo</h2>
                <p>Comparativo</p>
                <canvas id="chart_line_comparativo"></canvas>
                <h2 class="section-4">Histórico</h2>
                <table id="tabela_horas_liquidas"><thead><tr><td>Disciplina</td></tr></thead><tbody></tbody></table>
            </body></html>',
            'questoes' => '<html><body>
                <h2 class="section-1">Breve Panorama</h2>
                <p>Questões</p>
                <div class="main-numbers"><p>questões</p><h3>10</h3></div>
                <div class="main-numbers"><p>acertos</p><h3>8</h3></div>
                <div class="main-numbers"><p>% acertos</p><h3>80%</h3></div>
                <h2 class="section-4">Performance por assunto</h2>
                <table id="tabela_questoes"><thead><tr><td>Disciplina</td><td>Assunto</td><td>Taxa</td></tr></thead>
                <tbody><tr><td>Português</td><td>Sintaxe</td><td>80%</td></tr></tbody></table>
            </body></html>',
            'progresso' => '<html><body>
                <h2 class="section-3">Motivação</h2>
                <p class="section-3-1">Horas por dia</p>
                <div class="insights-panel"><h6>Painel de Insights</h6><p>Média de 02:00</p></div>
            </body></html>',
        ];

        try {
            $downloader = new CoachReportDownloader('1', false, static function (): void {});
            $ref = new ReflectionClass($downloader);
            $ok = $ref->getMethod('gerarPdfConsolidadoDoHtml')->invoke($downloader, 'Giovanna', $htmls, $destino);

            $this->assertTrue($ok);
            $this->assertFileExists($destino);
            $this->assertGreaterThan(500, filesize($destino));
            $this->assertSame('%PDF', substr((string) file_get_contents($destino), 0, 4));
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
