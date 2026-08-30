<?php

namespace Tests\Unit;

use App\Services\Tutory\PdfPreview;
use App\Services\Tutory\RelatorioConsolidadoLayout;
use Tests\TestCase;

class RelatorioConsolidadoLayoutTest extends TestCase
{
    public function test_ordem_fixa_das_secoes(): void
    {
        $this->assertSame([
            'Seu desempenho',
            'Ritmo de estudos',
            'Painel de Insights',
            'Desempenho em questões',
            'Performance por assunto',
            'Revisões no período',
            'Histórico completo',
        ], RelatorioConsolidadoLayout::SECOES);
    }

    public function test_cor_do_percentual_so_no_indicador(): void
    {
        $this->assertSame(RelatorioConsolidadoLayout::VERMELHO, RelatorioConsolidadoLayout::corPercentual(0));
        $this->assertSame(RelatorioConsolidadoLayout::VERMELHO, RelatorioConsolidadoLayout::corPercentual(65));
        $this->assertSame(RelatorioConsolidadoLayout::DOURADO, RelatorioConsolidadoLayout::corPercentual(66));
        $this->assertSame(RelatorioConsolidadoLayout::DOURADO, RelatorioConsolidadoLayout::corPercentual(79.9));
        $this->assertSame(RelatorioConsolidadoLayout::VERDE, RelatorioConsolidadoLayout::corPercentual(80));
        $this->assertSame(RelatorioConsolidadoLayout::VERDE, RelatorioConsolidadoLayout::corPercentual(100));
    }

    public function test_secao_vazia_e_omitida(): void
    {
        $this->assertSame('', RelatorioConsolidadoLayout::secao('Ritmo de estudos', '', ''));
        $html = RelatorioConsolidadoLayout::secao('Seu desempenho', 'Intro', '<p>ok</p>');
        $this->assertStringContainsString('Seu desempenho', $html);
        $this->assertStringContainsString('Intro', $html);
        $this->assertStringContainsString('mn-sec-title', $html);
    }

    public function test_insights_nao_duplicam_titulo_e_preservam_texto(): void
    {
        $html = RelatorioConsolidadoLayout::insights([
            'Painel de Insights',
            'Média de 02:00',
            'A matéria mais estudada foi Direito Constitucional.',
            'SEDES - PORTUGUÊS foi a matéria que você menos estudou',
            '908 exercícios | 765 acertos | 84%',
        ]);
        $this->assertStringNotContainsString('Painel de Insights', $html);
        $this->assertStringNotContainsString('foi a matéria', $html);
        $this->assertStringNotContainsString('A matéria mais estudada foi', $html);
        $this->assertStringNotContainsString('mn-kpis', $html);
        $this->assertStringNotContainsString('kpi-value', $html);
        $this->assertStringContainsString('MÉDIA DIÁRIA', $html);
        $this->assertStringContainsString('02:00', $html);
        $this->assertStringContainsString('MATÉRIA MAIS ESTUDADA', $html);
        $this->assertStringContainsString('Direito Constitucional', $html);
        $this->assertStringContainsString('MATÉRIA MENOS ESTUDADA', $html);
        $this->assertStringContainsString('SEDES - PORTUGUÊS', $html);
        $this->assertStringContainsString('EXERCÍCIOS REALIZADOS', $html);
        $this->assertStringContainsString('908', $html);
        $this->assertStringContainsString('ACERTOS', $html);
        $this->assertStringContainsString('765', $html);
        $this->assertStringContainsString('TAXA DE ACERTOS', $html);
        $this->assertStringContainsString('84%', $html);
        $this->assertSame(6, substr_count($html, 'mn-insight-block'));
        $this->assertStringContainsString('mn-insight-label', $html);
        $this->assertStringContainsString('mn-insight-value', $html);
    }

    public function test_tabela_aplica_cor_so_no_percentual(): void
    {
        $html = RelatorioConsolidadoLayout::tabela(
            ['Disciplina', 'Assunto', 'Taxa de Acertos'],
            [
                ['Direito Administrativo', 'Improbidade administrativa', '55%'],
                ['Direito Constitucional', 'Organização do Estado', '82%'],
            ],
            ['numeric' => [2], 'percent_col' => 2]
        );
        $this->assertStringContainsString('Improbidade administrativa', $html);
        $this->assertStringContainsString('color:'.RelatorioConsolidadoLayout::VERMELHO, $html);
        $this->assertStringContainsString('color:'.RelatorioConsolidadoLayout::VERDE, $html);
        $this->assertStringNotContainsString('background:'.RelatorioConsolidadoLayout::VERMELHO.'">Improbidade', $html);
        $this->assertStringNotContainsString('mn-table-band', $html);
        $this->assertStringContainsString('class="z"', $html);
        $this->assertMatchesRegularExpression('/<td class="num mn-pct">/', $html);
        $this->assertStringContainsString('class="mn-c-assunto"', $html);
        $this->assertStringContainsString('class="mn-assunto"', $html);
        $this->assertStringNotContainsString('…', $html);
        $this->assertStringNotContainsString('ellipsis', $html);
    }

    public function test_tabela_destina_espaco_restante_ao_assunto(): void
    {
        $performance = RelatorioConsolidadoLayout::tabela(
            ['Disciplina', 'Assunto', 'Taxa de Acertos'],
            [
                ['SEDES - ADMINISTRAÇÃO DE RECURSOS DE MATERIAIS', 'Improbidade administrativa e o dever de probidade na administração pública', '55%'],
            ],
            ['numeric' => [2], 'percent_col' => 2]
        );
        $larguras = $this->largurasColgroup($performance);
        $this->assertSame(['disciplina', 'assunto', 'pct'], array_keys($larguras));
        $this->assertGreaterThan($larguras['disciplina'], $larguras['assunto']);
        $this->assertGreaterThan($larguras['pct'], $larguras['assunto']);
        $this->assertLessThan($larguras['disciplina'], $larguras['pct']);
        $this->assertSame(100, array_sum($larguras));

        $revisoes = RelatorioConsolidadoLayout::tabela(
            ['Disciplina revisada', 'Assunto revisado', 'Revisões no período'],
            [['Direito Penal', 'Teoria do crime', '3']],
            ['numeric' => [2]]
        );
        $largurasRev = $this->largurasColgroup($revisoes);
        $this->assertGreaterThan($largurasRev['disciplina'], $largurasRev['assunto']);
        $this->assertGreaterThan($largurasRev['num'], $largurasRev['assunto']);
        $this->assertStringContainsString('Teoria do crime', $revisoes);

        $historico = RelatorioConsolidadoLayout::tabela(
            ['Disciplina', 'Assunto', 'Modalidade', 'Horas'],
            [[
                'SEDES - ADMINISTRAÇÃO DE RECURSOS DE MATERIAIS',
                'Inventário e controle de estoque de materiais permanentes e de consumo',
                'Cadernos de Exercícios (Múltipla Escolha)',
                '01:12:40',
            ]],
            ['numeric' => [3]]
        );
        $largurasHist = $this->largurasColgroup($historico);
        $this->assertGreaterThan($largurasHist['disciplina'], $largurasHist['assunto']);
        $this->assertGreaterThan($largurasHist['modalidade'], $largurasHist['assunto']);
        $this->assertGreaterThan($largurasHist['horas'], $largurasHist['assunto']);
        $this->assertLessThanOrEqual($largurasHist['modalidade'], $largurasHist['horas']);
        $this->assertStringContainsString('class="num mn-horas"', $historico);
        $this->assertStringContainsString('01:12:40', $historico);
        $this->assertStringContainsString('Cadernos de Exercícios (Múltipla Escolha)', $historico);
        $this->assertStringContainsString('class="mn-mod"', $historico);
    }

    public function test_corpo_das_tabelas_alinha_sem_alterar_cabecalho(): void
    {
        $historico = RelatorioConsolidadoLayout::tabela(
            ['Disciplina', 'Assunto', 'Modalidade', 'Horas'],
            [['Direito Penal', 'Teoria do crime', 'Videoaula', '01:12:40']],
            ['numeric' => [3]]
        );
        $this->assertMatchesRegularExpression('/<th class="mn-disc" style="width:\d+%">Disciplina<\/th>/', $historico);
        $this->assertStringContainsString('<td class="mn-disc">Direito Penal</td>', $historico);
        $this->assertStringContainsString('<td class="mn-assunto">Teoria do crime</td>', $historico);
        $this->assertStringContainsString('<td class="mn-mod">Videoaula</td>', $historico);
        $this->assertStringContainsString('<td class="num mn-horas">01:12:40</td>', $historico);

        $css = RelatorioConsolidadoLayout::css("'Inter'", '');
        $this->assertStringContainsString('.mn-table th{background:#001D3D; color:#ffffff; font-weight:600; font-size:9pt; letter-spacing:0.03em; text-transform:uppercase; padding:9px 11px; text-align:left; vertical-align:middle; white-space:normal;}', $css);
        $this->assertDoesNotMatchRegularExpression('/\.mn-table th\{[^}]*text-align:center/', $css);
        $this->assertDoesNotMatchRegularExpression('/\.mn-table tbody td\{[^}]*padding-top:\d{2}/', $css);
    }

    public function test_css_tabelas_e_espacamentos_seguem_o_comando(): void
    {
        $css = RelatorioConsolidadoLayout::css("'Inter'", '');
        $this->assertStringContainsString('@page{margin:34mm 16mm 18mm 16mm;}', $css);
        $this->assertStringContainsString('.mn-sec{margin:0 0 28px;}', $css);
        $this->assertStringContainsString('.mn-sec-intro{font-size:10.5pt; font-weight:400; color:#4B5563; margin:7px 0 0;', $css);
        $this->assertStringContainsString('.mn-sec-body{margin-top:14px;}', $css);
        $this->assertStringContainsString('border-spacing:14px 0', $css);
        $this->assertStringContainsString('.mn-kpis td.kpi{background:#ffffff; border:1px solid #E6E8EC; padding:18px 18px;', $css);
        $this->assertStringContainsString('.mn-chart{margin:8px 0 16px;', $css);
        $this->assertStringContainsString('.mn-table{width:100%; max-width:100%; border-collapse:collapse; font-size:9pt; table-layout:fixed; margin:0;}', $css);
        $this->assertStringContainsString('padding:9px 11px', $css);
        $this->assertStringContainsString('height:auto', $css);
        $this->assertStringContainsString('.mn-table tr{page-break-inside:avoid;', $css);
        $this->assertStringContainsString('.mn-table tbody tr:first-child,.mn-table tbody tr:nth-child(2){page-break-before:avoid;}', $css);
        $this->assertStringContainsString('white-space:nowrap', $css);
        $this->assertStringContainsString('.mn-table td.mn-horas{white-space:nowrap;}', $css);
        $this->assertStringContainsString('.mn-table td{border-bottom:1px solid #EEF0F3; padding:9px 11px; vertical-align:middle;', $css);
        $this->assertStringContainsString('.mn-table td.mn-assunto{text-align:justify;}', $css);
        $this->assertStringContainsString('.mn-table td.mn-pct{text-align:right;}', $css);
        $this->assertStringContainsString('.mn-table th{background:#001D3D; color:#ffffff; font-weight:600; font-size:9pt; letter-spacing:0.03em; text-transform:uppercase; padding:9px 11px; text-align:left; vertical-align:middle; white-space:normal;}', $css);
        $this->assertStringNotContainsString('width:18%', $css);
        $this->assertStringNotContainsString('width:1%', $css);
        $this->assertStringNotContainsString('text-overflow:ellipsis', $css);
        $this->assertStringNotContainsString('mn-table-band', $css);
        $this->assertStringNotContainsString('padding-top:56px', $css);
        $this->assertStringNotContainsString('#F5F5F5', $css);
        $this->assertStringNotContainsString('border-radius', $css);
        $this->assertDoesNotMatchRegularExpression('/html,body\{[^}]*margin:0/', $css);
    }

    public function test_css_nao_usa_border_radius_nem_fundo_cinza_de_rodape(): void
    {
        $css = RelatorioConsolidadoLayout::css("'Inter'", '');
        $this->assertStringContainsString('@page{margin:34mm 16mm 18mm 16mm;}', $css);
        $this->assertStringContainsString('background:#ffffff', $css);
        $this->assertStringNotContainsString('#F5F5F5', $css);
        $this->assertStringNotContainsString('border-radius', $css);
        $this->assertStringNotContainsString('Montserrat', $css);
        $this->assertStringNotContainsString('kpi-hot', $css);
        $this->assertDoesNotMatchRegularExpression('/html,body\{[^}]*margin:0/', $css);
    }

    public function test_cards_se_reorganizam_conforme_a_quantidade(): void
    {
        $quatro = RelatorioConsolidadoLayout::cards([
            ['label' => 'A', 'value' => '1'],
            ['label' => 'B', 'value' => '2'],
            ['label' => 'C', 'value' => '3'],
            ['label' => 'D', 'value' => '4'],
        ]);
        $this->assertSame(1, substr_count($quatro, '<tr>'));
        $tres = RelatorioConsolidadoLayout::cards([
            ['label' => 'A', 'value' => '1'],
            ['label' => 'B', 'value' => '2'],
            ['label' => 'C', 'value' => '3'],
        ]);
        $this->assertSame(1, substr_count($tres, '<tr>'));
        $this->assertSame(3, substr_count($tres, '<td class="kpi'));
        $this->assertStringNotContainsString('kpi-hot', $quatro);
        $this->assertStringNotContainsString('kpi-hot', $tres);
    }

    public function test_cabecalho_e_hierarquia_de_abertura(): void
    {
        $this->assertSame('MISSÃO NOMEAÇÃO •', RelatorioConsolidadoLayout::textoCabecalhoEsquerdo());
        $this->assertSame(
            'AGOSTO/PERÍODO 1',
            RelatorioConsolidadoLayout::rotuloPeriodo('1', new \DateTimeImmutable('2026-08-10'))
        );
        $this->assertSame(
            RelatorioConsolidadoLayout::INTRO_HISTORICO,
            'Confira o histórico completo de horas cronometradas no período.'
        );
        $this->assertSame(
            RelatorioConsolidadoLayout::TITULO_GRAFICO_PLANEJADAS,
            'Horas planejadas × horas estudadas'
        );
        $this->assertStringNotContainsString('(brutas)', RelatorioConsolidadoLayout::TITULO_GRAFICO_PLANEJADAS);

        $html = RelatorioConsolidadoLayout::alunoNome('Giovanna')
            .RelatorioConsolidadoLayout::secao('Seu desempenho', 'Curso X', '<p>ok</p>', 'mn-sec-keep');
        $this->assertStringContainsString('GIOVANNA', $html);
        $posNome = strpos($html, 'GIOVANNA');
        $posTitulo = strpos($html, 'Seu desempenho');
        $this->assertNotFalse($posNome);
        $this->assertNotFalse($posTitulo);
        $this->assertLessThan($posTitulo, $posNome);
        $this->assertStringContainsString('mn-sec-keep', $html);
        $this->assertStringContainsString('Curso X', $html);
    }

    public function test_nome_e_tabelas_nao_invadem_a_faixa_do_cabecalho(): void
    {
        $pdftotext = trim((string) shell_exec('command -v pdftotext 2>/dev/null'));
        if ($pdftotext === '') {
            $this->markTestSkipped('pdftotext ausente');
        }

        $pdf = (new PdfPreview)->gerar('dejavu');
        $tmp = sys_get_temp_dir().'/mn-spacing-'.uniqid('', true).'.pdf';
        file_put_contents($tmp, $pdf);

        try {
            $bbox = (string) shell_exec(escapeshellcmd($pdftotext).' -bbox '.escapeshellarg($tmp).' -');
            $this->assertMatchesRegularExpression(
                '/<word[^>]*yMin="([0-9.]+)"[^>]*>GIOVANNA<\/word>/',
                $bbox
            );
            preg_match('/<word[^>]*yMin="([0-9.]+)"[^>]*>MISSÃO<\/word>/', $bbox, $cab);
            preg_match('/<word[^>]*yMin="([0-9.]+)"[^>]*>GIOVANNA<\/word>/', $bbox, $nome);
            $this->assertNotEmpty($cab);
            $this->assertNotEmpty($nome);
            $yCab = (float) $cab[1];
            $yNome = (float) $nome[1];
            $this->assertLessThan(40.0, $yCab);
            $this->assertGreaterThan(90.0, $yNome);
            $this->assertGreaterThan($yCab + 50.0, $yNome);

            $pares = [];
            preg_match_all(
                '/<word[^>]*yMin="([0-9.]+)"[^>]*>Amostra<\/word>[\s\S]*?<word[^>]*yMin="([0-9.]+)"[^>]*>DISCIPLINA<\/word>/u',
                $bbox,
                $assunto,
                PREG_SET_ORDER
            );
            preg_match_all(
                '/<word[^>]*yMin="([0-9.]+)"[^>]*>cronometradas<\/word>[\s\S]*?<word[^>]*yMin="([0-9.]+)"[^>]*>DISCIPLINA<\/word>/u',
                $bbox,
                $historico,
                PREG_SET_ORDER
            );
            $pares = array_merge($assunto, $historico);
            $this->assertNotEmpty($pares);
            foreach ($pares as $par) {
                $delta = (float) $par[2] - (float) $par[1];
                $this->assertGreaterThan(
                    18.0,
                    $delta,
                    'Texto secundário não pode grudar no cabeçalho da tabela'
                );
                $this->assertLessThan(
                    55.0,
                    $delta,
                    'Folga intro→tabela não deve virar faixa vazia'
                );
            }
        } finally {
            @unlink($tmp);
        }
    }

    /**
     * @return array<string, int>
     */
    private function largurasColgroup(string $html): array
    {
        preg_match_all('/<col class="mn-c-([a-z]+)"[^>]*width:(\d+)%/', $html, $m, PREG_SET_ORDER);
        $out = [];
        foreach ($m as $col) {
            $out[$col[1]] = (int) $col[2];
        }

        return $out;
    }
}
