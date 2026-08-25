<?php

namespace App\Services\Tutory;

use Dompdf\Dompdf;

/**
 * Gera um PDF de amostra (mesma casca do relatório quinzenal) para o preview do admin.
 */
final class PdfPreview
{
    public function gerar(?string $chaveFonte = null): string
    {
        $chave = PdfFontes::normalizar($chaveFonte);
        if (in_array($chave, ['times', 'courier'], true)) {
            $chave = PdfFontes::diretorioInter() !== null ? 'inter' : 'dejavu';
        }
        $fontCss = PdfFontes::css($chave);
        $css = RelatorioConsolidadoLayout::css($fontCss, '', PdfFontes::fontFaceCss($chave));
        $rotulo = RelatorioConsolidadoLayout::rotuloPeriodo('1', new \DateTimeImmutable('2026-08-01'));

        $cards = RelatorioConsolidadoLayout::cards([
            ['label' => 'Total de Horas', 'value' => '32h'],
            ['label' => '% de acertos', 'value' => '78,1%'],
            ['label' => 'Questões', 'value' => '248'],
            ['label' => 'Revisões', 'value' => '12'],
        ]);

        $ritmo = RelatorioConsolidadoLayout::grafico(
            RelatorioConsolidadoLayout::TITULO_GRAFICO_PLANEJADAS,
            '<p class="empty">Gráfico omitido neste preview de fonte.</p>',
            RelatorioConsolidadoLayout::LEGENDA_HORAS_ESTUDADAS
        );

        $insights = RelatorioConsolidadoLayout::insights([
            'Média diária 03:16',
            'Exercícios realizados 248',
            'Acertos 194',
            'Taxa de acertos 78,1%',
            'A matéria mais estudada foi Direito Constitucional.',
        ]);

        $questoes = RelatorioConsolidadoLayout::cards([
            ['label' => 'Questões realizadas', 'value' => '248'],
            ['label' => 'Acertos', 'value' => '194'],
            ['label' => 'Percentual de acertos', 'value' => '78,1%'],
        ]);

        $assuntos = RelatorioConsolidadoLayout::tabela(
            ['Disciplina', 'Assunto', 'Taxa de Acertos'],
            [
                ['Direito Administrativo', 'Improbidade administrativa e o dever de probidade na administração pública', '55%'],
                ['Direito Constitucional', 'Organização do Estado', '82%'],
                ['Português', 'Concordância verbal', '71%'],
            ],
            ['numeric' => [2], 'percent_col' => 2]
        );

        $revisoes = RelatorioConsolidadoLayout::tabela(
            ['Disciplina', 'Assunto', 'Revisões'],
            [
                ['Direito Penal', 'Teoria do crime', '3'],
                ['Direito Constitucional', 'Controle de constitucionalidade', '1'],
            ],
            ['numeric' => [2]]
        );

        $historico = RelatorioConsolidadoLayout::tabela(
            ['Data', 'Disciplina', 'Horas brutas', 'Horas líquidas'],
            [
                ['01/08', 'Direito Constitucional', '02:10', '01:48'],
                ['02/08', 'Direito Administrativo', '01:40', '01:22'],
            ],
            ['numeric' => [2, 3]]
        );

        $secoes = RelatorioConsolidadoLayout::alunoNome('Giovanna')
            .RelatorioConsolidadoLayout::secao(
                'Seu desempenho',
                'Pré-edital · Delegado de Polícia — São Paulo',
                $cards,
                'mn-sec-keep'
            )
            .RelatorioConsolidadoLayout::secao('Ritmo de estudos', '', $ritmo)
            .RelatorioConsolidadoLayout::secao('Painel de Insights', '', $insights, 'mn-sec-insights')
            .RelatorioConsolidadoLayout::secao('Desempenho em questões', 'Confira o seu desempenho de questões no período', $questoes)
            .RelatorioConsolidadoLayout::secao('Performance por assunto', 'Amostra de texto com acentuação: ação, ciência, constituição, órgão.', $assuntos, 'mn-sec-table')
            .RelatorioConsolidadoLayout::secao('Revisões no período', '', $revisoes, 'mn-sec-table')
            .RelatorioConsolidadoLayout::secao(
                'Histórico completo',
                RelatorioConsolidadoLayout::INTRO_HISTORICO,
                $historico,
                'mn-sec-table'
            );

        $html = RelatorioConsolidadoLayout::documento($css, $rotulo, $secoes);

        $dompdf = new Dompdf(PdfFontes::opcoesDompdf($chave));
        PdfFontes::aplicarNoDompdf($dompdf);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        RelatorioConsolidadoLayout::aplicarCabecalhoRodape($dompdf, $rotulo);

        return $dompdf->output() ?? '';
    }
}
