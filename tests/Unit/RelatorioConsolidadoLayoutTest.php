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
        ]);
        $this->assertStringNotContainsString('Painel de Insights', $html);
        $this->assertStringContainsString('Média de', $html);
        $this->assertStringContainsString('02:00', $html);
        $this->assertStringContainsString('mn-insight-label', $html);
        $this->assertStringContainsString('mn-insight-value', $html);
        $this->assertStringContainsString('Direito Constitucional', $html);
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

            preg_match_all(
                '/<page[\s\S]*?<word[^>]*yMin="([0-9.]+)"[^>]*>Confira<\/word>[\s\S]*?<word[^>]*yMin="([0-9.]+)"[^>]*>DISCIPLINA<\/word>/u',
                $bbox,
                $pares,
                PREG_SET_ORDER
            );
            $this->assertNotEmpty($pares);
            foreach ($pares as $par) {
                $this->assertGreaterThan(
                    30.0,
                    (float) $par[2] - (float) $par[1],
                    'Introdução da seção deve ter folga antes da tabela'
                );
            }
        } finally {
            @unlink($tmp);
        }
    }
}
