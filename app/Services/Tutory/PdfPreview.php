<?php

namespace App\Services\Tutory;

use Dompdf\Dompdf;

/**
 * Gera um PDF de amostra (mesma casca do relatório) para o preview do admin.
 */
final class PdfPreview
{
    public function gerar(?string $chaveFonte = null): string
    {
        $chave = PdfFontes::normalizar($chaveFonte);
        $fontCss = PdfFontes::css($chave);
        $logoHtml = '<div class="logo"><div class="l1">MISSÃO</div><div class="l2">NOMEAÇÃO</div></div>';

        $html = <<<HTML
<!DOCTYPE html>
<html><head><meta charset="utf-8"><style>
body{font-family: {$fontCss}; font-size:12px; color:#222; margin:24px;}
.logo{text-align:center; margin:0 0 14px; color:#000; font-family: {$fontCss}; font-weight:bold; line-height:0.95;}
.logo .l1{font-size:18pt; letter-spacing:2pt;}
.logo .l2{font-size:16pt; letter-spacing:1pt;}
h1{font-size:20px; margin:0 0 6px; text-align:center;}
.periodo{color:#555; margin-bottom:14px; text-align:center;}
.aluno{margin:10px 0 18px;}
.aluno h2{margin:0 0 4px; font-size:16px;}
.section{margin:22px 0 8px; padding-left:10px; border-left:4px solid #00aced; font-size:15px;}
.section-desc{color:#555; margin:0 0 12px;}
.panorama{width:100%; border-collapse:separate; border-spacing:10px 0; margin:6px 0 14px; table-layout:fixed;}
.panorama td{
  width:33%;
  border:0.4pt solid #cdcdcd;
  padding:6pt 8pt 10pt;
  height:52pt;
  vertical-align:top;
  background:#ffffff;
}
.panorama .label{color:#888; font-size:8pt; margin:0 0 8pt; line-height:1.1;}
.panorama .value{font-size:12pt; font-weight:bold; color:#000000; margin:0; line-height:1.1;}
.assuntos{width:100%; border-collapse:collapse; margin-top:8px; font-size:11px;}
.assuntos thead td{background:#00aced; color:#fff; font-weight:bold; padding:8px;}
.assuntos tbody td{border-bottom:1px solid #eee; padding:8px; vertical-align:top;}
.rule{border:0;border-top:1px solid #eaeaea; margin:12px 0;}
.nota{color:#555; font-size:10px; margin-top:18px;}
</style></head><body>
{$logoHtml}
<h1>Relatório de Questões</h1>
<div class="periodo">Período do relatório: de 01/08/2026 a 15/08/2026</div>
<hr class="rule" />
<div class="aluno">
  <h2>Giovanna</h2>
  <div class="periodo" style="text-align:left;margin:0;">Pré-edital · Delegado de Polícia — São Paulo</div>
</div>

<h2 class="section">Breve Panorama</h2>
<p class="section-desc">Confira o seu desempenho de questões no período</p>
<table class="panorama"><tr>
  <td><div class="label">Questões realizadas</div><div class="value">248</div></td>
  <td><div class="label">Percentual de acertos</div><div class="value">78,1%</div></td>
  <td><div class="label">Horas estudadas</div><div class="value">32h</div></td>
</tr></table>

<h2 class="section">Performance por assunto</h2>
<p class="section-desc">Amostra de texto com acentuação: ação, ciência, constituição, órgão.</p>
<table class="assuntos"><thead><tr>
  <td>Disciplina</td><td>Assunto</td><td>Taxa de Acertos</td>
</tr></thead><tbody>
  <tr><td>Direito Administrativo</td><td>Improbidade administrativa</td><td>55%</td></tr>
  <tr><td>Direito Constitucional</td><td>Organização do Estado</td><td>82%</td></tr>
  <tr><td>Português</td><td>Concordância verbal</td><td>71%</td></tr>
</tbody></table>
<p class="nota">Preview da fonte do PDF (não é o relatório completo do aluno).</p>
</body></html>
HTML;

        $dompdf = new Dompdf(PdfFontes::opcoesDompdf($chave));
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output() ?? '';
    }
}
