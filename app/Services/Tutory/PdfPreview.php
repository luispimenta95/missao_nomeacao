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
        $fontCss = PdfFontes::css($chave);
        $css = RelatorioConsolidadoLayout::css($fontCss, '', PdfFontes::fontFaceCss($chave));
        $rotulo = RelatorioConsolidadoLayout::rotuloPeriodo('1', new \DateTimeImmutable('2026-08-01'));

        $desempenho = RelatorioConsolidadoLayout::alunoBloco(
            'Giovanna',
            'Pré-edital · Delegado de Polícia — São Paulo'
        ).RelatorioConsolidadoLayout::cards([
            ['label' => 'Total de Horas', 'value' => '32h'],
            ['label' => '% de acertos', 'value' => '78,1%'],
            ['label' => 'Questões', 'value' => '248'],
            ['label' => 'Revisões', 'value' => '12'],
        ]);

        $ritmo = RelatorioConsolidadoLayout::grafico(
            'Horas planejadas × horas (brutas) estudadas',
            '<p class="empty">Gráfico omitido neste preview de fonte.</p>'
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
            ['label' => 'Percentual de acertos', 'value' => '78,1%', 'destaque' => true],
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

        $secoes = RelatorioConsolidadoLayout::secao('Seu desempenho', '', $desempenho)
            .RelatorioConsolidadoLayout::secao('Ritmo de estudos', '', $ritmo)
            .RelatorioConsolidadoLayout::secao('Painel de Insights', '', $insights)
            .RelatorioConsolidadoLayout::secao('Desempenho em questões', 'Confira o seu desempenho de questões no período', $questoes)
            .RelatorioConsolidadoLayout::secao('Performance por assunto', 'Amostra de texto com acentuação: ação, ciência, constituição, órgão.', $assuntos)
            .RelatorioConsolidadoLayout::secao('Revisões no período', '', $revisoes)
            .RelatorioConsolidadoLayout::secao('Histórico completo', '', $historico);

        $html = <<<HTML
<!DOCTYPE html>
<html><head><meta charset="utf-8"><style>
{$css}
</style></head><body>
{$secoes}
</body></html>
HTML;

        $dompdf = new Dompdf(PdfFontes::opcoesDompdf($chave));
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        RelatorioConsolidadoLayout::aplicarCabecalhoRodape($dompdf, $rotulo);

        return $dompdf->output() ?? '';
    }
}
