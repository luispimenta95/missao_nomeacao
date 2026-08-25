<?php

namespace Tests\Unit;

use App\Services\Tutory\CoachReportDownloader;
use App\Services\Tutory\RelatorioConsolidadoLayout;
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
        $this->assertStringContainsString('#tabela_revisoes', $src);
        $this->assertStringContainsString('chart_line_comparativo', $src);
        $this->assertStringContainsString('#tabela_horas_liquidas', $src);
        $this->assertStringContainsString('chart_questoes_dia', $src);
        $this->assertStringContainsString('#tabela_questoes', $src);
        $this->assertStringContainsString('chart_horas_diarias', $src);
        $this->assertStringContainsString('labelHoursOnChartVertices', $src);
        $this->assertStringContainsString('.insights-panel', $src);
        $this->assertStringContainsString('mn-sec-title', $src);
        $this->assertStringContainsString('Seu desempenho', $src);
        $this->assertStringContainsString('Ritmo de estudos', $src);
        $this->assertStringContainsString('Painel de Insights', $src);
        $this->assertStringContainsString('Histórico completo', $src);
        $this->assertStringContainsString('--url-desempenho', $src);
        $this->assertStringContainsString('--rotulo-periodo', $src);
        $this->assertStringNotContainsString('chart_horas_estudo', $src);
        $this->assertStringNotContainsString('chart_performance', $src);
        $this->assertStringNotContainsString("htmlFromHeading('h2.section-5')", $src);
        $this->assertStringNotContainsString('extracted.desempenho.twoCol', $src);
        $this->assertStringNotContainsString('extracted.aluno.estudos', $src);
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

    public function test_sem_node_o_motor_do_pdf_e_dompdf(): void
    {
        $downloader = new CoachReportDownloader('1', false, static function (): void {});
        $ref = new ReflectionClass($downloader);
        $bin = $ref->getMethod('binarioNode')->invoke($downloader);
        $pode = $ref->getMethod('podeUsarPuppeteer')->invoke($downloader);

        if ($bin === null) {
            $this->assertFalse($pode);
        } else {
            $this->assertTrue(is_bool($pode));
        }
    }

    public function test_motor_padrao_e_dompdf_sem_npm(): void
    {
        $downloader = new CoachReportDownloader('1', false, static function (): void {});
        $ref = new ReflectionClass($downloader);

        $this->assertFalse($ref->getMethod('podeUsarPuppeteer')->invoke($downloader));
        $this->assertStringContainsString(
            'dompdf',
            strtolower((string) $ref->getMethod('motivoSemPuppeteer')->invoke($downloader))
        );
    }

    public function test_sem_pacote_puppeteer_nao_usa_o_compositor(): void
    {
        $downloader = new CoachReportDownloader('1', false, static function (): void {});
        $ref = new ReflectionClass($downloader);
        $instalado = $ref->getMethod('pacotePuppeteerInstalado')->invoke($downloader);
        $esperado = is_file(base_path('node_modules/puppeteer/package.json'))
            || is_file(base_path('node_modules/puppeteer-core/package.json'));

        $this->assertSame($esperado, $instalado);
        if (! $instalado) {
            $this->assertFalse($ref->getMethod('podeUsarPuppeteer')->invoke($downloader));
            $motivo = (string) $ref->getMethod('motivoSemPuppeteer')->invoke($downloader);
            $this->assertNotSame('', $motivo);
        }
    }

    public function test_nao_aplica_marca_dagua_diagonal(): void
    {
        $php = (string) file_get_contents((new ReflectionClass(CoachReportDownloader::class))->getFileName());
        $this->assertStringNotContainsString('aplicarMarcaDaguaPdf', $php);
        $this->assertStringContainsString('aplicarCabecalhoRodape', $php);
        $this->assertSame('AGOSTO/PERÍODO 1', RelatorioConsolidadoLayout::rotuloPeriodo('1', new \DateTimeImmutable('2026-08-10')));
        $this->assertSame('AGOSTO/PERÍODO 2', RelatorioConsolidadoLayout::rotuloPeriodo('2', new \DateTimeImmutable('2026-08-20')));
        $this->assertSame('MISSÃO NOMEAÇÃO •', RelatorioConsolidadoLayout::textoCabecalhoEsquerdo());
    }

    public function test_css_do_consolidado_nao_deixa_bloco_cinza_no_rodape(): void
    {
        $downloader = new CoachReportDownloader('1', false, static function (): void {});
        $ref = new ReflectionClass($downloader);
        $css = $ref->getMethod('cssPdfConsolidado')->invoke($downloader, 'DejaVu Sans', '');

        $this->assertStringContainsString('@page{margin:27mm 16mm 16mm 16mm;}', $css);
        $this->assertStringContainsString('background:#ffffff', $css);
        $this->assertStringNotContainsString('#F5F5F5', $css);
        $this->assertStringNotContainsString('border-radius', $css);
    }

    public function test_consolidado_nao_inclui_desempenho_nem_historico_de_metas(): void
    {
        $php = (string) file_get_contents((new ReflectionClass(CoachReportDownloader::class))->getFileName());
        $this->assertStringNotContainsString('<h2>Histórico de Metas</h2>', $php);
        $this->assertStringNotContainsString('Performance por Área', $php);
        $this->assertStringNotContainsString('Horas de estudo', $php);
        $this->assertStringNotContainsString('chartDesempenhoHorasEstudo', $php);
        $this->assertStringNotContainsString('chartDesempenhoPerformance', $php);
        $this->assertStringContainsString('Revisões no período', $php);
        $this->assertStringContainsString('montarHtmlMetricasDesempenho', $php);
        $this->assertStringContainsString('Ritmo de estudos', $php);
        $this->assertStringContainsString('Histórico completo', $php);
        $this->assertStringContainsString('INTRO_HISTORICO', $php);
        $this->assertStringContainsString('TITULO_GRAFICO_PLANEJADAS', $php);
        $this->assertStringNotContainsString('Horas planejadas × horas (brutas) estudadas', $php);
        $this->assertStringNotContainsString("' (brutas)'", $php);
    }

    public function test_grafico_de_horas_diarias_tem_rotulos_nos_vertices(): void
    {
        $php = (string) file_get_contents((new ReflectionClass(CoachReportDownloader::class))->getFileName());
        $this->assertStringContainsString('__DATALABEL_HOURS__', $php);
        $this->assertStringContainsString('__DATALABEL_VALUE__', $php);
        $this->assertStringContainsString('$isHours ? \'__DATALABEL_HOURS__\' : \'__DATALABEL_VALUE__\'', $php);

        $script = (string) file_get_contents(base_path('scripts/tutory-compose-pdf.mjs'));
        $this->assertStringContainsString('labelHoursOnChartVertices', $script);
        $this->assertStringContainsString('chart_horas_diarias', $script);
        $this->assertStringContainsString('return `${txt}h`', $script);
        $this->assertStringNotContainsString('stripPercentFromHoursCharts', $script);
        $this->assertStringContainsString('Horas planejadas × horas estudadas', $script);
        $this->assertStringContainsString('Horas estudadas = horas brutas registradas.', $script);
        $this->assertStringNotContainsString('Horas planejadas × horas (brutas) estudadas', $script);
        $this->assertStringContainsString('MISSÃO NOMEAÇÃO •', $script);
        $this->assertStringContainsString("top: '27mm'", $script);
        $this->assertStringContainsString("right: '16mm'", $script);
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

            $pdftotext = trim((string) shell_exec('command -v pdftotext 2>/dev/null'));
            if ($pdftotext !== '') {
                $txt = (string) shell_exec(escapeshellcmd($pdftotext).' '.escapeshellarg($destino).' -');
                $this->assertStringContainsString('MISSÃO', $txt);
                $this->assertStringContainsString('NOMEAÇÃO', $txt);
                $this->assertStringContainsString('GIOVANNA', $txt);
                $this->assertStringContainsStringIgnoringCase('Total de Horas', $txt);
                $this->assertStringContainsString('Seu desempenho', $txt);
                $this->assertStringContainsString('Painel de Insights', $txt);
                $this->assertStringContainsString('Desempenho em questões', $txt);
                $this->assertStringContainsString('Performance por assunto', $txt);
                $this->assertStringContainsString('Revisões no período', $txt);
                $this->assertStringContainsString('Histórico completo', $txt);
                $this->assertStringContainsString('Confira o histórico completo de horas cronometradas no período.', $txt);
                $this->assertStringNotContainsString('Histórico de Metas', $txt);
                $this->assertStringNotContainsString('Performance por Área', $txt);
                $this->assertStringNotContainsString('Horas de estudo', $txt);
                $this->assertStringNotContainsString('Breve Panorama', $txt);
                $posNome = mb_strpos($txt, 'GIOVANNA');
                $posDes = mb_strpos($txt, 'Seu desempenho');
                $posInsights = mb_strpos($txt, 'Painel de Insights');
                $posQuestoes = mb_strpos($txt, 'Desempenho em questões');
                $posAssuntos = mb_strpos($txt, 'Performance por assunto');
                $posRevisoes = mb_strpos($txt, 'Revisões no período');
                $posHist = mb_strpos($txt, 'Histórico completo');
                $this->assertNotFalse($posNome);
                $this->assertNotFalse($posDes);
                $this->assertGreaterThan($posNome, $posDes);
                $this->assertGreaterThan($posDes, $posInsights);
                $this->assertGreaterThan($posInsights, $posQuestoes);
                $this->assertGreaterThan($posQuestoes, $posAssuntos);
                $this->assertGreaterThan($posAssuntos, $posRevisoes);
                $this->assertGreaterThan($posRevisoes, $posHist);
            }
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
