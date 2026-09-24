import { COMPOSER_CSS } from './relatorio-css.mjs';

export function headerPeriodoHtml(rotulo) {
  return String(rotulo || '')
    .replace(/</g, '')
    .replace(/\s+[•/]\s+/g, ' - ');
}

export function escapeHtml(value) {
  return String(value).replace(/[&<>"']/g, (ch) => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
  }[ch]));
}

export function normalizeHeader(text) {
  return String(text || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '');
}

export function columnRole(header) {
  const h = normalizeHeader(header);
  if (h.includes('taxa') || (h.includes('acerto') && h.includes('percent'))) return 'pct';
  if (h.includes('assunto')) return 'assunto';
  if (h.includes('modalidade')) return 'modalidade';
  if (h.includes('disciplina')) return 'disciplina';
  if (h.includes('hora')) return 'horas';
  if (h === 'data' || h === 'dia' || h.startsWith('data')) return 'data';
  if (h.includes('revis') || /^[\d.,:%h\s:]+$/i.test(String(header || ''))) return 'num';
  return 'texto';
}

export function columnWidths(roles) {
    const min = {
      horas: 13, num: 13, pct: 16, data: 9, modalidade: 16, disciplina: 20, texto: 12, assunto: 0,
    };
  const w = roles.map((role) => (role === 'assunto' ? 0 : (min[role] || min.texto)));
  let used = w.reduce((a, b) => a + b, 0);
  const assuntoIdx = roles.map((role, i) => (role === 'assunto' ? i : -1)).filter((i) => i >= 0);
  if (assuntoIdx.length && used > 62) {
    const fator = 62 / used;
    used = 0;
    roles.forEach((role, i) => {
      if (role === 'assunto') return;
      w[i] = Math.max(8, Math.round(w[i] * fator));
      used += w[i];
    });
  }
  let rest = 100 - used;
  if (assuntoIdx.length) {
    const base = Math.floor(rest / assuntoIdx.length);
    assuntoIdx.forEach((i, k) => {
      w[i] = base + (k === 0 ? rest - base * assuntoIdx.length : 0);
    });
    const maxOther = Math.max(0, ...w.filter((_, i) => roles[i] !== 'assunto'));
    const piso = { num: 11, pct: 14, data: 8, modalidade: 13, disciplina: 16, texto: 10 };
    assuntoIdx.forEach((idx) => {
      let need = Math.max(0, maxOther + 6 - w[idx]);
      ['texto', 'modalidade', 'disciplina', 'data', 'num', 'pct'].forEach((role) => {
        roles.forEach((r, i) => {
          if (r !== role || need <= 0) return;
          const disp = w[i] - (piso[role] || 8);
          if (disp <= 0) return;
          const take = Math.min(disp, need);
          w[i] -= take;
          w[idx] += take;
          need -= take;
        });
      });
    });
  } else {
    const dest = ['disciplina', 'texto', 'modalidade'].map((role) => roles.indexOf(role)).find((i) => i >= 0);
    if (dest >= 0) w[dest] += rest;
    else if (w.length) w[0] += rest;
  }
  const sum = w.reduce((a, b) => a + b, 0);
  if (sum !== 100 && w.length) w[w.length - 1] += 100 - sum;
  return w;
}

export function classForRole(role) {
  switch (role) {
    case 'horas': return 'num mn-horas';
    case 'pct': return 'num mn-pct';
    case 'num':
    case 'data': return 'num mn-qtd';
    case 'assunto': return 'mn-assunto';
    case 'disciplina': return 'mn-disc';
    case 'modalidade': return 'mn-mod';
    default: return '';
  }
}

export function injectTableColgroups(html) {
  if (!html) return html;
  return String(html).replace(/<table\b[^>]*>[\s\S]*?<\/table>/gi, (table) => {
    if (/<colgroup/i.test(table)) return table;
    const row = table.match(/<tr\b[^>]*>([\s\S]*?)<\/tr>/i);
    if (!row) return table;
    const headers = [];
    const cellRe = /<t[dh]\b[^>]*>([\s\S]*?)<\/t[dh]>/gi;
    let cell;
    while ((cell = cellRe.exec(row[1]))) {
      headers.push(cell[1].replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim());
    }
    if (!headers.length) return table;
    const roles = headers.map(columnRole);
    const widths = columnWidths(roles);
    const cols = roles.map((role, i) => `<col class="mn-c-${role}" style="width:${widths[i]}%">`).join('');
    let out = table.replace(/<table\b[^>]*>/i, (open) => `${open}<colgroup>${cols}</colgroup>`);
    out = out.replace(/<tr\b[^>]*>[\s\S]*?<\/tr>/gi, (tr) => {
      let col = 0;
      return tr.replace(/<(t[dh])\b([^>]*)>/gi, (full, tag, attrs) => {
        const role = roles[col] || 'texto';
        col += 1;
        const cls = classForRole(role);
        if (!cls) return full;
        if (/\bclass\s*=/.test(attrs)) {
          return `<${tag}${attrs.replace(/class=(['"])/i, `class=$1${cls} `)}>`;
        }
        return `<${tag} class="${cls}"${attrs}>`;
      });
    });
    return out;
  });
}

export function insightValue(value) {
  return String(value || '').trim().replace(/[ \t.]+$/, '');
}

export function parseInsightPart(text) {
  const t = String(text || '').trim();
  if (!t) return null;
  const narrativas = [
    [/^(?:a\s+)?mat[eé]ria mais estudada\s+(?:foi|é|:)\s+(.+)$/i, 'MATÉRIA MAIS ESTUDADA'],
    [/^(?:a\s+)?mat[eé]ria menos estudada\s+(?:foi|é|:)\s+(.+)$/i, 'MATÉRIA MENOS ESTUDADA'],
    [/^(?:a\s+)?mat[eé]ria com maior solicita[cç][aã]o de tempo extra\s+(?:foi|é|:)\s+(.+)$/i, 'MATÉRIA COM MAIOR SOLICITAÇÃO DE TEMPO EXTRA'],
    [/^(?:.*?maior solicita[cç][aã]o de\s+)?tempo extra\s+(?:foi|é|:)\s+(.+)$/i, 'MATÉRIA COM MAIOR SOLICITAÇÃO DE TEMPO EXTRA'],
    [/^(.+?)\s+foi\s+a\s+mat[eé]ria\s+(?:que\s+voc[eê]\s+)?mais\s+estudou/i, 'MATÉRIA MAIS ESTUDADA'],
    [/^(.+?)\s+foi\s+a\s+mat[eé]ria\s+(?:que\s+voc[eê]\s+)?menos\s+estudou/i, 'MATÉRIA MENOS ESTUDADA'],
  ];
  for (const [re, label] of narrativas) {
    const m = t.match(re);
    if (m) return [label, insightValue(m[1])];
  }
  const metricas = [
    [/^m[eé]dia(?:\s+di[aá]ria|\s+de)?\s+(\d{1,2}:\d{2}(?::\d{2})?)\s*$/i, 'MÉDIA DIÁRIA'],
    [/^exerc[ií]cios(?:\s+realizados)?\s+(\d{1,6})\s*$/i, 'EXERCÍCIOS REALIZADOS'],
    [/^(\d{1,6})\s+exerc[ií]cios(?:\s+realizados)?\s*$/i, 'EXERCÍCIOS REALIZADOS'],
    [/^acertos\s+(\d{1,6})\s*$/i, 'ACERTOS'],
    [/^(\d{1,6})\s+acertos\s*$/i, 'ACERTOS'],
    [/^taxa(?:\s+de)?\s+acertos\s+(\d+(?:[.,]\d+)?%?)\s*$/i, 'TAXA DE ACERTOS'],
    [/^(\d+(?:[.,]\d+)?%)\s*$/, 'TAXA DE ACERTOS'],
  ];
  for (const [re, label] of metricas) {
    const m = t.match(re);
    if (m) return [label, insightValue(m[1])];
  }
  const generic = t.match(/^(.+?)\s+(\d{1,2}:\d{2}(?::\d{2})?|\d{1,3}(?:[.,]\d+)?%|\d{1,6})\s*$/);
  if (generic && generic[1].trim()) {
    return [generic[1].trim().replace(/^[:\-–—.\s]+|[:\-–—.\s]+$/g, '').toUpperCase(), insightValue(generic[2])];
  }
  return null;
}

export function isNumericKpiValue(value) {
  const v = String(value || '').trim();
  return /^(?:\d{1,4}:\d{2}(?::\d{2})?|\d{1,3}(?:[.,]\d+)?\s*%|\d{1,6}(?:[.,]\d+)?\s*h(?:oras?)?|\d{1,6})$/i.test(v);
}

export function kpiSizeClass(value) {
  const len = String(value || '').trim().length;
  if (len <= 3) return 'kpi-v-a';
  if (len <= 4) return 'kpi-v-b';
  if (len <= 5) return 'kpi-v-c';
  if (len <= 6) return 'kpi-v-d';
  return 'kpi-v-e';
}

export function kpiCellHtml(label, value, span, cols) {
  const labelHtml = escapeHtml(label);
  const valueHtml = escapeHtml(value);
  if (isNumericKpiValue(value)) {
    const compact = cols >= 4;
    return `<td class="kpi kpi-num${compact ? ' kpi-compact' : ''}"${span}><div class="kpi-label">${labelHtml}</div><div class="kpi-value ${kpiSizeClass(value)}">${valueHtml}</div></td>`;
  }
  return `<td class="kpi kpi-text"${span}><div class="kpi-stack"><div class="kpi-label">${labelHtml}</div><div class="kpi-value kpi-long">${valueHtml}</div></div></td>`;
}

export function formatInsightsHtml(html) {
  if (!html) return '';
  if (/mn-kpis/.test(html) && /kpi-value/.test(html)) return html;
  const texts = [];
  const re = /<p\b[^>]*>([\s\S]*?)<\/p>/gi;
  let m;
  while ((m = re.exec(html))) {
    const t = m[1].replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
    if (t && !/painel de insights/i.test(t)) texts.push(t);
  }
  if (!texts.length) {
    const plain = String(html).replace(/<[^>]+>/g, ' ').replace(/\s+/g, ' ').trim();
    if (plain && !/painel de insights/i.test(plain)) texts.push(plain);
  }
  const items = [];
  texts.forEach((text) => {
    const parts = /[|•]/.test(text)
      ? text.split(/\s*[|•]\s*/).map((p) => p.trim()).filter(Boolean)
      : [text];
    parts.forEach((part) => {
      const par = parseInsightPart(part);
      if (!par) return;
      items.push(par);
    });
  });
  if (!items.length) return html;
  const cols = items.length <= 3 ? Math.max(items.length, 1) : 3;
  let out = '<table class="mn-kpis"><tbody>';
  for (let i = 0; i < items.length; i += cols) {
    const row = items.slice(i, i + cols);
    out += '<tr>';
    row.forEach((item, idx) => {
      const span = (idx === row.length - 1 && row.length < cols) ? ` colspan="${cols - row.length + 1}"` : '';
      out += kpiCellHtml(item[0], item[1], span, cols);
    });
    out += '</tr>';
  }
  out += '</tbody></table>';
  return out;
}

export function block(title, inner, extraClass = 'mn-legacy', intro = '') {
  if (!inner || !String(inner).trim()) return '';
  const introHtml = intro ? `<p class="mn-sec-intro">${escapeHtml(intro)}</p>` : '';
  return `<section class="mn-sec ${extraClass}">
    <div class="mn-sec-head"><h2 class="mn-sec-title">${title}</h2>${introHtml}</div>
    <div class="mn-sec-body">${inner}</div>
  </section>`;
}

export function chartBlock(subtitle, inner, note = '') {
  if (!inner || !String(inner).trim()) return '';
  const title = subtitle ? `<p class="mn-chart-title">${subtitle}</p>` : '';
  const noteHtml = note ? `<p class="mn-chart-note">${note}</p>` : '';
  return `<div class="mn-chart">${title}${noteHtml}${inner}</div>`;
}

export function buildHtml(extracted) {
  const parts = [];
  const nome = extracted.desempenho.nome
    ? `<p class="mn-aluno-nome">${escapeHtml(extracted.desempenho.nome)}</p>`
    : (extracted.desempenho.header || '');
  parts.push(nome + block(
    'Seu desempenho',
    extracted.desempenho.metrics || '',
    'mn-sec-keep',
    extracted.desempenho.curso || '',
  ));
  const ritmo = chartBlock('Horas brutas × horas líquidas', extracted.horas.tempo)
    + chartBlock(
      'Horas planejadas × horas estudadas',
      extracted.progresso.motivacao,
      'Horas estudadas = horas brutas registradas.',
    );
  parts.push(block('Ritmo de estudos', ritmo));
  parts.push(block('Painel de Insights', formatInsightsHtml(extracted.progresso.insights || ''), 'mn-sec-insights'));
  parts.push(block('Desempenho em questões', extracted.questoes.panorama || ''));
  parts.push(block('Performance por assunto', injectTableColgroups(extracted.questoes.assuntos || ''), 'mn-sec-table'));
  if (extracted.aluno.revisoes && extracted.aluno.revisoes.trim()) {
    let revisoesHtml = extracted.aluno.revisoes;
    if (extracted.aluno.revisoesRows === 0 && !/mn-empty/.test(revisoesHtml)) {
      revisoesHtml += '<p class="mn-empty">Nenhuma revisão registrada neste período.</p>';
    }
    parts.push(block('Revisões no período', injectTableColgroups(revisoesHtml), 'mn-sec-table'));
  }
  parts.push(block(
    'Histórico completo',
    injectTableColgroups(extracted.horas.historico || ''),
    'mn-sec-table',
    'Confira o histórico completo de horas cronometradas no período.',
  ));

  return `<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Relatório consolidado</title>
  <link rel="stylesheet" href="https://static.tutory.com.br/vendor/bootstrap/bootstrap.4.5.0.min.css" />
  <style>${COMPOSER_CSS}</style>
</head>
<body class="report-container">
  <div class="mn-unified">
    ${parts.join('\n')}
  </div>
</body>
</html>`;
}
