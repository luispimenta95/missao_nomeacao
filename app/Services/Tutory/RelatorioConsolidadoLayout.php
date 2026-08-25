<?php

namespace App\Services\Tutory;

use Dompdf\Dompdf;
use Throwable;

/**
 * Identidade visual e componentes do relatório quinzenal.
 *
 * Os dados da Tutory entram intocados; só o casco visual é fixo.
 * A paginação é consequência do conteúdo (seções modulares).
 */
final class RelatorioConsolidadoLayout
{
    public const AZUL = '#001D3D';

    public const DOURADO = '#BF8F00';

    public const VERMELHO = '#B42318';

    public const VERDE = '#1F7A3A';

    public const TEXTO = '#1F2937';

    public const TEXTO_SEC = '#4B5563';

    public const BORDA = '#E6E8EC';

    public const ZEBRA = '#F8F9FB';

    public const WHATSAPP_URL = 'https://wa.me/message/I53LOYY2D7CNI1';

    public const CTA_ANALISE = 'Quero adiantar minha análise';

    /**
     * @var list<string>
     */
    public const SECOES = [
        'Seu desempenho',
        'Ritmo de estudos',
        'Painel de Insights',
        'Desempenho em questões',
        'Performance por assunto',
        'Revisões no período',
        'Histórico completo',
    ];

    /**
     * @var array<int, string>
     */
    private const MESES = [
        1 => 'JANEIRO',
        2 => 'FEVEREIRO',
        3 => 'MARÇO',
        4 => 'ABRIL',
        5 => 'MAIO',
        6 => 'JUNHO',
        7 => 'JULHO',
        8 => 'AGOSTO',
        9 => 'SETEMBRO',
        10 => 'OUTUBRO',
        11 => 'NOVEMBRO',
        12 => 'DEZEMBRO',
    ];

    public static function rotuloPeriodo(string $periodo, ?\DateTimeInterface $ref = null): string
    {
        $ref ??= new \DateTimeImmutable('now');
        $mes = self::MESES[(int) $ref->format('n')] ?? mb_strtoupper($ref->format('F'));
        $n = $periodo === '2' ? '2' : '1';

        return $mes.' • PERÍODO '.$n;
    }

    public static function corPercentual(?float $pct): string
    {
        if ($pct === null) {
            return self::TEXTO_SEC;
        }
        if ($pct <= 65) {
            return self::VERMELHO;
        }
        if ($pct < 80) {
            return self::DOURADO;
        }

        return self::VERDE;
    }

    /**
     * @return list<string>
     */
    public static function paletaSeries(): array
    {
        return [self::AZUL, self::DOURADO, '#64748B', self::VERDE];
    }

    public static function secao(string $titulo, string $intro, string $body): string
    {
        if (trim($body) === '') {
            return '';
        }

        $html = '<section class="mn-sec">';
        $html .= '<div class="mn-sec-head">';
        $html .= '<h2 class="mn-sec-title">'.htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8').'</h2>';
        if (trim($intro) !== '') {
            $html .= '<p class="mn-sec-intro">'.htmlspecialchars($intro, ENT_QUOTES, 'UTF-8').'</p>';
        }
        $html .= '</div>';
        $html .= '<div class="mn-sec-body">'.$body.'</div>';
        $html .= '</section>';

        return $html;
    }

    public static function grafico(string $subtitulo, string $imgHtml): string
    {
        if (trim($imgHtml) === '') {
            return '';
        }

        $html = '<div class="mn-chart">';
        if (trim($subtitulo) !== '') {
            $html .= '<p class="mn-chart-title">'.htmlspecialchars($subtitulo, ENT_QUOTES, 'UTF-8').'</p>';
        }
        $html .= $imgHtml;
        $html .= '</div>';

        return $html;
    }

    /**
     * @param  list<array{label: string, value: string, destaque?: bool}>  $items
     */
    public static function cards(array $items): string
    {
        $items = array_values(array_filter(
            $items,
            static fn (array $i): bool => trim((string) ($i['label'] ?? '').($i['value'] ?? '')) !== ''
        ));
        if ($items === []) {
            return '';
        }

        $n = count($items);
        $cols = $n <= 4 ? max($n, 1) : ($n <= 6 ? 3 : 4);
        $linhas = array_chunk($items, $cols);
        $html = '<table class="mn-kpis"><tbody>';
        foreach ($linhas as $linha) {
            $html .= '<tr>';
            $span = $cols - count($linha);
            foreach ($linha as $i => $item) {
                $destaque = ! empty($item['destaque']);
                $colspan = ($i === count($linha) - 1 && $span > 0) ? ' colspan="'.($span + 1).'"' : '';
                $cls = $destaque ? ' class="kpi kpi-hot"' : ' class="kpi"';
                $html .= '<td'.$cls.$colspan.'>'
                    .'<div class="kpi-label">'.htmlspecialchars((string) $item['label'], ENT_QUOTES, 'UTF-8').'</div>'
                    .'<div class="kpi-value">'.htmlspecialchars((string) $item['value'], ENT_QUOTES, 'UTF-8').'</div>'
                    .'</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     * @param  array{numeric?: list<int>, percent_col?: int|null, class?: string}  $opts
     */
    public static function tabela(array $headers, array $rows, array $opts = []): string
    {
        if ($rows === [] && $headers === []) {
            return '';
        }

        $numeric = array_fill_keys($opts['numeric'] ?? [], true);
        $percentCol = $opts['percent_col'] ?? null;
        $class = $opts['class'] ?? 'mn-table';

        $html = '<table class="'.$class.'"><thead><tr>';
        foreach ($headers as $i => $h) {
            $align = isset($numeric[$i]) || $i === $percentCol ? ' class="num"' : '';
            $html .= '<th'.$align.'>'.htmlspecialchars($h, ENT_QUOTES, 'UTF-8').'</th>';
        }
        $html .= '</tr></thead><tbody>';

        foreach ($rows as $r => $cols) {
            $zebra = $r % 2 === 1 ? ' class="z"' : '';
            $html .= '<tr'.$zebra.'>';
            foreach ($cols as $i => $cell) {
                if ($i === $percentCol) {
                    $html .= self::celulaPercentual($cell);

                    continue;
                }
                $align = isset($numeric[$i]) ? ' class="num"' : '';
                $html .= '<td'.$align.'>'.htmlspecialchars($cell, ENT_QUOTES, 'UTF-8').'</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table>';

        return $html;
    }

    /**
     * Transforma os parágrafos originais do Painel de Insights em cards + linhas,
     * sem reescrever os textos da Tutory.
     *
     * @param  list<string>  $paragrafos
     */
    public static function insights(array $paragrafos): string
    {
        $cards = [];
        $textos = [];

        foreach ($paragrafos as $p) {
            $p = trim(preg_replace('/\s+/u', ' ', (string) $p) ?? '');
            if ($p === '' || preg_match('/painel de insights/iu', $p)) {
                continue;
            }

            if (mb_strlen($p) <= 96 && preg_match('/^(.*?)(\d{1,2}:\d{2}|\d{1,3}(?:[.,]\d+)?%|\d{1,5})\s*$/u', $p, $m)) {
                $label = trim($m[1], " \t:-–—.");
                if ($label !== '') {
                    $cards[] = ['label' => $label, 'value' => $m[2]];

                    continue;
                }
            }
            $textos[] = $p;
        }

        $html = '';
        if ($cards !== []) {
            $html .= self::cards($cards);
        }
        foreach ($textos as $t) {
            $html .= '<p class="mn-insight">'.htmlspecialchars($t, ENT_QUOTES, 'UTF-8').'</p>';
        }

        return $html;
    }

    public static function alunoBloco(string $nome, string $curso): string
    {
        $html = '<div class="mn-aluno">';
        if ($nome !== '') {
            $html .= '<p class="mn-aluno-nome">'.htmlspecialchars($nome, ENT_QUOTES, 'UTF-8').'</p>';
        }
        if ($curso !== '') {
            $html .= '<p class="mn-aluno-curso">'.htmlspecialchars($curso, ENT_QUOTES, 'UTF-8').'</p>';
        }
        $html .= '</div>';

        return $nome === '' && $curso === '' ? '' : $html;
    }

    public static function css(string $fontCss, string $pieCss = '', string $fontFace = ''): string
    {
        $azul = self::AZUL;
        $ouro = self::DOURADO;
        $texto = self::TEXTO;
        $sec = self::TEXTO_SEC;
        $borda = self::BORDA;
        $zebra = self::ZEBRA;

        return <<<CSS
{$fontFace}
@page{margin:20mm 20mm 18mm 20mm;}
html,body{font-family: {$fontCss}; font-size:11pt; color:{$texto}; margin:0; padding:0; background:#ffffff; line-height:1.45;}
h1,h2,h3,h4,h5,h6,p,table,img,section,div{margin:0; padding:0;}
.mn-sec{margin:0 0 28px;}
.mn-sec-head{page-break-after:avoid; page-break-inside:avoid;}
.mn-sec-title{font-size:13.5pt; font-weight:700; color:{$azul}; letter-spacing:-0.01em; padding:0 0 0 10px; border-left:3px solid {$ouro}; line-height:1.2;}
.mn-sec-intro{font-size:9pt; color:{$sec}; margin:7px 0 0; padding-left:13px; page-break-after:avoid;}
.mn-sec-body{margin-top:14px;}
.mn-aluno{margin:0 0 16px;}
.mn-aluno-nome{font-size:16pt; font-weight:700; color:{$azul}; line-height:1.15;}
.mn-aluno-curso{font-size:9.5pt; color:{$sec}; margin-top:4px;}
.mn-kpis{width:100%; border-collapse:separate; border-spacing:12px 0; table-layout:fixed; margin:0 0 4px;}
.mn-kpis td.kpi{background:#ffffff; border:1px solid {$borda}; padding:16px 14px 18px; vertical-align:top;}
.mn-kpis td.kpi-hot{border-left:3px solid {$ouro};}
.kpi-label{font-size:8pt; font-weight:500; color:{$sec}; letter-spacing:0.04em; text-transform:uppercase; margin:0 0 8px; line-height:1.25;}
.kpi-value{font-size:16pt; font-weight:700; color:{$azul}; line-height:1.1;}
.mn-chart{margin:0 0 18px; page-break-inside:avoid;}
.mn-chart-title{font-size:9.5pt; font-weight:600; color:{$azul}; margin:0 0 10px;}
.chart{width:100%; max-width:700px; max-height:280px; margin:0 0 4px;}
{$pieCss}
.mn-table{width:100%; border-collapse:collapse; font-size:9pt;}
.mn-table thead{display:table-header-group;}
.mn-table th{background:{$azul}; color:#ffffff; font-weight:600; font-size:8pt; letter-spacing:0.03em; text-transform:uppercase; padding:9px 11px; text-align:left; vertical-align:middle;}
.mn-table th.num,.mn-table td.num{text-align:right; white-space:nowrap;}
.mn-table td{border-bottom:1px solid #EEF0F3; padding:9px 11px; vertical-align:top; color:{$texto}; word-wrap:break-word;}
.mn-table tr.z td{background:{$zebra};}
.mn-table tr{page-break-inside:avoid;}
.pct{font-weight:600; font-size:9.5pt;}
.bar-track{display:block; height:3px; background:#EEF0F3; margin-top:6px; width:72px; overflow:hidden;}
.bar-fill{display:block; height:3px;}
.mn-insight{font-size:9.5pt; color:{$texto}; margin:0 0 8px; padding:8px 0 8px 12px; border-left:2px solid {$ouro};}
.empty{color:#6B7280; text-align:left; font-size:9.5pt; padding:8px 0;}
.keep{page-break-inside:avoid;}
.keep-start{page-break-inside:avoid;}
CSS;
    }

    public static function aplicarCabecalhoRodape(Dompdf $dompdf, string $rotuloPeriodo): void
    {
        try {
            $canvas = $dompdf->getCanvas();
            $metrics = $dompdf->getFontMetrics();
            $bold = $metrics->getFont('Inter', 'bold')
                ?: $metrics->getFont('DejaVu Sans', 'bold')
                ?: $metrics->getFont('DejaVu Sans', 'normal');
            $regular = $metrics->getFont('Inter', 'normal')
                ?: $metrics->getFont('DejaVu Sans', 'normal');
            if (! is_string($bold) || $bold === '' || ! is_string($regular) || $regular === '') {
                return;
            }

            $pageW = $canvas->get_width();
            $pageH = $canvas->get_height();
            $left = 56.7;
            $right = $pageW - 56.7;
            $azul = self::hexToRgb(self::AZUL);
            $ouro = self::hexToRgb(self::DOURADO);
            $cinza = self::hexToRgb(self::TEXTO_SEC);

            $canvas->page_text($left, 22, 'MISSÃO NOMEAÇÃO', $bold, 8.0, $azul);
            $rotuloW = $metrics->getTextWidth($rotuloPeriodo, $regular, 8.0);
            $canvas->page_text($right - $rotuloW, 22, $rotuloPeriodo, $regular, 8.0, $cinza);
            $canvas->page_line($left, 34.5, $right, 34.5, $ouro, 0.6);

            $pag = 'Página {PAGE_NUM} de {PAGE_COUNT}';
            $pagW = $metrics->getTextWidth('Página 00 de 00', $regular, 7.5);
            $canvas->page_text($right - $pagW, $pageH - 24, $pag, $regular, 7.5, $cinza);
        } catch (Throwable) {
            // Relatório segue válido sem chrome de página.
        }
    }

    /**
     * @return array{0?: float, 1?: float, 2?: float}
     */
    private static function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        return [
            hexdec(substr($hex, 0, 2)) / 255,
            hexdec(substr($hex, 2, 2)) / 255,
            hexdec(substr($hex, 4, 2)) / 255,
        ];
    }

    private static function celulaPercentual(string $taxa): string
    {
        $n = self::parsePct($taxa);
        $cor = self::corPercentual($n);
        $html = '<td class="num"><span class="pct" style="color:'.htmlspecialchars($cor, ENT_QUOTES, 'UTF-8').';">'
            .htmlspecialchars($taxa, ENT_QUOTES, 'UTF-8').'</span>';
        if ($n !== null) {
            $w = max(0, min(100, $n));
            $html .= '<span class="bar-track"><span class="bar-fill" style="width:'.$w.'%;background:'
                .htmlspecialchars($cor, ENT_QUOTES, 'UTF-8').';"></span></span>';
        }
        $html .= '</td>';

        return $html;
    }

    private static function parsePct(string $taxa): ?float
    {
        $t = str_replace(['%', ' '], '', trim($taxa));
        $t = str_replace(',', '.', $t);
        if (! is_numeric($t)) {
            return null;
        }

        return (float) $t;
    }
}
