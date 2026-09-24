export const COMPOSER_CSS = `
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap');

:root {
  --mn-azul: #001D3D;
  --mn-ouro: #BF8F00;
  --mn-texto: #1F2937;
  --mn-sec: #4B5563;
  --mn-borda: #E6E8EC;
  --mn-zebra: #F8F9FB;
}
html, body {
  padding-top: 0 !important;
  padding-bottom: 8px !important;
  font-family: Inter, "DejaVu Sans", Helvetica, Arial, sans-serif !important;
  color: var(--mn-texto);
  background: #fff !important;
}
.report-top-bar,
.actions,
#btn_save,
#btn_download,
#btn_whatsapp_link,
#theme_toggle,
.no-print,
.watermark,
[class*="watermark"],
.marca-dagua {
  display: none !important;
}
.mn-unified {
  max-width: 1100px;
  margin: 0 auto;
  padding: 8px 0 16px;
}
.mn-sec {
  margin: 0 0 28px;
  break-inside: auto;
}
.mn-sec-head {
  break-after: avoid;
  page-break-after: avoid;
  margin: 0;
}
.mn-sec-title {
  font-size: 16.5pt;
  font-weight: 700;
  color: var(--mn-azul);
  margin: 0;
  padding: 0 0 0 12px;
  border-left: 3.5px solid var(--mn-ouro);
  line-height: 1.2;
}
.mn-sec-intro {
  font-size: 10.5pt;
  color: var(--mn-sec);
  margin: 7px 0 0;
  padding-left: 16px;
}
.mn-sec-body { margin-top: 14px; }
.mn-sec-keep { break-inside: avoid; page-break-inside: avoid; }
.mn-sec-insights { break-inside: avoid; page-break-inside: avoid; }
.mn-sec-table .mn-sec-head { break-after: avoid; page-break-after: avoid; }
.mn-sec-body h1,
.mn-sec-body h2,
.mn-sec-body h6,
.mn-kicker { display: none !important; }
.mn-aluno-nome {
  font-size: 26pt;
  font-weight: 700;
  color: var(--mn-azul);
  margin: 6px 0 18px;
  text-transform: uppercase;
  letter-spacing: -0.02em;
  line-height: 1.1;
  break-after: avoid;
  page-break-after: avoid;
}
.title-section h1, .aluno-details h4 {
  font-size: 26pt;
  font-weight: 700;
  color: var(--mn-azul);
  margin: 0 0 4px;
  text-transform: uppercase;
}
.aluno-details p, .title-section p {
  font-size: 10.5pt;
  color: var(--mn-sec);
  margin: 0;
}
.metrics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 14px;
  margin-top: 14px;
}
.metric-card, .main-numbers {
  background: #fff;
  border: 1.5pt solid var(--mn-ouro);
  border-radius: 9px;
  padding: 18px;
  box-shadow: none !important;
  text-align: left;
  margin: 0;
}
.metric-label, .main-numbers p {
  display: block;
  font-size: 8pt;
  font-weight: 600;
  color: var(--mn-sec);
  letter-spacing: 0.04em;
  text-transform: uppercase;
  margin: 0 0 4px;
  padding: 0;
  line-height: 1.12;
  text-align: left;
}
.metric-value, .main-numbers h3 {
  display: block;
  font-size: 22pt;
  font-weight: 700;
  color: var(--mn-azul);
  margin: 4px 0 0;
  line-height: 0.92;
  text-align: left;
  padding: 0;
  white-space: nowrap;
  letter-spacing: -0.04em;
}
.metric-value.kpi-v-a, .main-numbers h3.kpi-v-a { font-size: 24pt; }
.metric-value.kpi-v-b, .main-numbers h3.kpi-v-b { font-size: 22pt; }
.metric-value.kpi-v-c, .main-numbers h3.kpi-v-c { font-size: 20pt; }
.metric-value.kpi-v-d, .main-numbers h3.kpi-v-d { font-size: 18pt; }
.metric-value.kpi-v-e, .main-numbers h3.kpi-v-e { font-size: 16pt; }
.mn-legacy .row {
  display: flex;
  flex-wrap: wrap;
  gap: 14px;
  margin: 0;
}
.mn-legacy .col-4, .mn-legacy .col-6, .mn-legacy .col-2 {
  padding: 0;
  box-sizing: border-box;
  flex: 1 1 160px;
}
.mn-chart-title {
  font-size: 11pt;
  font-weight: 600;
  color: var(--mn-azul);
  margin: 0 0 8px;
}
.mn-chart-note {
  font-size: 9.5pt;
  font-weight: 400;
  color: var(--mn-sec);
  margin: 0 0 12px;
}
.mn-chart { margin: 8px 0 16px; break-inside: avoid; page-break-inside: avoid; }
.mn-sec-body table {
  width: 100%;
  max-width: 100%;
  border-collapse: collapse;
  table-layout: fixed;
  font-size: 9pt;
  margin-top: 0;
  border: 1.25pt solid var(--mn-azul);
}
.mn-sec-body thead { display: table-header-group; }
.mn-sec-body thead td, .mn-sec-body thead th {
  background: var(--mn-azul) !important;
  color: #fff !important;
  font-weight: 600;
  font-size: 9pt;
  letter-spacing: 0.03em;
  text-transform: uppercase;
  padding: 9px 11px;
  text-align: left;
  height: auto;
  white-space: normal;
  border: 1.25pt solid var(--mn-azul);
}
.mn-sec-body tbody td {
  border: 1.25pt solid var(--mn-azul);
  padding: 9px 11px;
  vertical-align: middle;
  word-wrap: break-word;
  overflow-wrap: break-word;
  line-height: 1.15;
  height: auto;
  white-space: normal;
  font-size: 9pt;
}
.mn-sec-body td.num {
  text-align: right;
  white-space: nowrap;
}
.mn-sec-body td.mn-horas {
  white-space: nowrap;
}
.mn-sec-body th.num {
  text-align: right;
}
.mn-sec-body th.mn-disc,
.mn-sec-body th.mn-assunto,
.mn-sec-body th.mn-mod,
.mn-sec-body th.mn-horas,
.mn-sec-body th.mn-qtd,
.mn-sec-body th.mn-pct { text-align: center; vertical-align: middle; }
.mn-sec-body tbody td { vertical-align: middle; }
.mn-sec-body tbody td.mn-disc,
.mn-sec-body tbody td.mn-mod,
.mn-sec-body tbody td.mn-horas,
.mn-sec-body tbody td.num { text-align: center; vertical-align: middle; }
.mn-sec-body tbody td.mn-assunto { text-align: center; vertical-align: middle; }
.mn-sec-body tbody td.mn-pct { text-align: center; }
.mn-sec-body tbody tr:nth-child(even) td { background: var(--mn-zebra); }
.mn-sec-body tbody tr { break-inside: avoid; page-break-inside: avoid; }
.mn-sec-body img {
  max-width: 100%;
  height: auto;
  display: block;
}
.mn-empty {
  color: #6B7280;
  font-size: 13px;
  text-align: left;
  padding: 8px 0;
  margin: 0;
}
.insights-panel {
  background: transparent !important;
  border: 0 !important;
  box-shadow: none !important;
  padding: 0 !important;
}
.mn-kpis {
  width: 100%;
  border-collapse: separate;
  border-spacing: 14px 14px;
  table-layout: fixed;
  margin: 0 0 8px;
  border: 0 !important;
}
.mn-sec-body .mn-kpis td.kpi {
  background: #fff;
  border: 1.5pt solid var(--mn-ouro) !important;
  border-radius: 9px;
  padding: 18px;
  vertical-align: middle;
}
.mn-sec-body .mn-kpis td.kpi.kpi-num { vertical-align: top; }
.kpi-inner {
  width: 100%;
  border-collapse: collapse !important;
  table-layout: fixed;
  margin: 0 !important;
  padding: 0;
  border: 0 !important;
}
.kpi-inner td {
  border: 0 !important;
  padding: 0 !important;
  vertical-align: middle !important;
  background: transparent !important;
}
.kpi-row {
  display: table;
  width: 100%;
  table-layout: fixed;
  margin: 0;
  padding: 0;
  border: 0 !important;
}
.kpi-stack { display: block; width: 100%; margin: 0; padding: 0; }
.kpi-label {
  font-size: 9.5pt;
  font-weight: 600;
  color: var(--mn-sec);
  letter-spacing: 0.04em;
  text-transform: uppercase;
  margin: 0;
  line-height: 1.15;
  text-align: left;
}
.kpi-value {
  font-size: 21pt;
  font-weight: 700;
  color: var(--mn-azul);
  line-height: 0.95;
  text-align: left;
  word-wrap: break-word;
  padding: 0;
  white-space: nowrap;
  letter-spacing: -0.03em;
}
.kpi-num .kpi-label {
  display: block;
  width: auto;
  max-width: 92%;
  margin: 0 0 2px;
  padding: 0;
  font-size: 8pt;
  line-height: 1.12;
  font-weight: 600;
}
.kpi-num .kpi-value {
  display: block;
  width: auto;
  max-width: 100%;
  margin: 4px 0 0;
  padding: 0;
  font-weight: 700;
  text-align: left;
  white-space: nowrap;
  letter-spacing: -0.04em;
  line-height: 0.92;
}
.kpi-num .kpi-v-a { font-size: 24pt; }
.kpi-num .kpi-v-b { font-size: 22pt; }
.kpi-num .kpi-v-c { font-size: 20pt; }
.kpi-num .kpi-v-d { font-size: 18pt; }
.kpi-num .kpi-v-e { font-size: 16pt; }
.kpi-compact .kpi-label { font-size: 8pt; line-height: 1.12; }
.kpi-compact .kpi-v-a { font-size: 20pt; }
.kpi-compact .kpi-v-b { font-size: 18pt; }
.kpi-compact .kpi-v-c { font-size: 16.5pt; }
.kpi-compact .kpi-v-d { font-size: 15pt; }
.kpi-compact .kpi-v-e { font-size: 13.5pt; }
.kpi-text .kpi-label { margin: 0 0 6px; line-height: 1.20; }
.kpi-long {
  font-size: 11.5pt;
  font-weight: 700;
  line-height: 1.20;
  text-align: left;
  padding: 0;
  white-space: normal;
}
@media print {
  .mn-sec-head, .mn-chart, .metric-card, .main-header-card {
    break-inside: avoid;
    page-break-inside: avoid;
  }
  .mn-sec-body table { page-break-inside: auto; }
}
`;

