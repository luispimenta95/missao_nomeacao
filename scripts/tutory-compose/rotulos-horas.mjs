function formatHourLabel(value) {
  if (value == null || value === '') return '';
  const raw = valorBruto(value);
  const num = Number(raw);
  if (!Number.isFinite(num)) return '';
  const rounded = Math.round(num * 10) / 10;
  const txt = Number.isInteger(rounded)
    ? String(rounded)
    : String(rounded).replace('.', ',');
  return `${txt}h`;
}

function valorBruto(value) {
  if (value && typeof value === 'object' && 'y' in value) return value.y;
  return value;
}

function formatCountLabel(value) {
  if (value == null || value === '') return '';
  const num = Number(valorBruto(value));
  if (!Number.isFinite(num)) return '';
  return String(Math.round(num));
}

function corSerie(label, index) {
  const h = String(label || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z]+/g, '');
  if (h.includes('erro') || h.includes('liquid') || h.includes('estudad')) return '#BF8F00';
  if (h.includes('acerto') || h.includes('bruta') || h.includes('planejad')) return '#001D3D';
  return index % 2 === 0 ? '#001D3D' : '#BF8F00';
}

function idDoCanvas(inst, chart) {
  const canvas = inst.canvas || (chart && chart.canvas) || (inst.ctx && inst.ctx.canvas);
  return canvas && canvas.id ? canvas.id : '';
}

function pintarSerie(ds, i, cat, barPct) {
  const cor = corSerie(ds.label, i);
  ds.type = 'bar';
  ds.fill = false;
  ds.backgroundColor = cor;
  ds.borderColor = cor;
  ds.borderWidth = 0;
  ds.categoryPercentage = cat;
  ds.barPercentage = barPct;
  ds.borderRadius = 3;
  ds.borderSkipped = 'bottom';
  ds.datalabels = { align: 'end', anchor: 'end', offset: i === 1 ? 8 : 2 };
}

function aplicarBarras(chart, cat, barPct) {
  if (!chart.data || !Array.isArray(chart.data.datasets)) return;
  chart.data.datasets.forEach((ds, i) => pintarSerie(ds, i, cat, barPct));
}

function maiorValor(chart) {
  let maxH = 0;
  (chart.data.datasets || []).forEach((ds) => {
    (ds.data || []).forEach((v) => {
      const n = Number(valorBruto(v));
      if (Number.isFinite(n) && n > maxH) maxH = n;
    });
  });
  return maxH;
}

function topoDoEixoY(chart) {
  const maxH = maiorValor(chart);
  if (maxH <= 10) return 10;
  let yTop = Math.max(10, Math.ceil(maxH / 2) * 2);
  if (maxH >= yTop) yTop += 2;
  return yTop;
}

function ticksDeHoras(chart, tickSize, kind) {
  const yTicks = { beginAtZero: true, fontSize: tickSize, fontColor: '#001D3D', fontStyle: '600' };
  if (kind !== 'hours') return yTicks;
  yTicks.max = topoDoEixoY(chart);
  yTicks.stepSize = 2;
  return yTicks;
}

function medidasDoGrafico(chart) {
  const nLabels = (chart.data && chart.data.labels) ? chart.data.labels.length : 1;
  const cat = nLabels >= 16 ? 0.62 : (nLabels >= 13 ? 0.68 : 0.72);
  return { nLabels, cat, barPct: 0.88, tickSize: nLabels >= 16 ? 9 : 10 };
}

function definirEixos(chart, medidas, kind) {
  chart.options.scales = chart.options.scales || {};
  chart.options.scales.xAxes = [{
    stacked: false,
    categoryPercentage: medidas.cat,
    barPercentage: medidas.barPct,
    gridLines: { display: false, drawBorder: false },
    ticks: {
      autoSkip: false,
      maxTicksLimit: Math.max(medidas.nLabels, 1),
      maxRotation: 0,
      minRotation: 0,
      fontSize: medidas.tickSize,
      fontColor: '#001D3D',
      fontStyle: '600',
    },
  }];
  chart.options.scales.yAxes = [{
    stacked: false,
    gridLines: { color: 'rgba(0, 0, 0, 0.05)', drawBorder: false, lineWidth: 0.4, zeroLineColor: 'rgba(0, 0, 0, 0.05)', drawTicks: false },
    scaleLabel: {
      display: true,
      labelString: kind === 'hours' ? 'Horas' : 'Questões',
      fontColor: '#001D3D',
      fontStyle: '600',
      fontSize: 10,
    },
    ticks: ticksDeHoras(chart, medidas.tickSize, kind),
  }];
}

function aplicarRotulos(chart, kind) {
  const labels = {
    display: true,
    clamp: true,
    clip: false,
    color: '#001D3D',
    backgroundColor: null,
    borderRadius: 0,
    padding: 0,
    font: { size: 7, weight: '600' },
    offset: 2,
    formatter: kind === 'hours' ? formatHourLabel : formatCountLabel,
    align: 'end',
    anchor: 'end',
  };
  chart.options.plugins = chart.options.plugins || {};
  chart.options.plugins.datalabels = Object.assign({}, chart.options.plugins.datalabels || {}, labels);
  chart.options.datalabels = Object.assign({}, chart.options.datalabels || {}, labels);
}

function aplicarPadding(chart) {
  chart.options.layout = chart.options.layout || {};
  const padding = chart.options.layout.padding;
  if (typeof padding === 'number') {
    chart.options.layout.padding = {
      top: Math.max(padding, 16),
      right: padding,
      bottom: Math.max(padding, 12),
      left: padding,
    };
    return;
  }
  const base = padding && typeof padding === 'object' ? padding : {};
  chart.options.layout.padding = Object.assign({}, base, {
    top: Math.max(Number(base.top) || 0, 16),
    bottom: Math.max(Number(base.bottom) || 0, 12),
  });
}

function rotularGrafico(chart, kind) {
  const medidas = medidasDoGrafico(chart);
  if (chart.config) chart.config.type = 'bar';
  chart.type = 'bar';
  aplicarBarras(chart, medidas.cat, medidas.barPct);
  definirEixos(chart, medidas, kind);
  chart.options.legend = Object.assign({}, chart.options.legend || {}, {
    display: true,
    position: 'top',
    labels: { boxWidth: 10, fontSize: 10, fontColor: '#001D3D', fontStyle: '600', padding: 12 },
  });
  chart.options.cornerRadius = 3;
  aplicarRotulos(chart, kind);
  aplicarPadding(chart);
}

/** Rótulos permanentes nos gráficos de barras agrupadas (Chart.js v2). */
export function labelHoursOnChartVertices() {
  const chartIds = {
    chart_horas_diarias: 'hours',
    chart_line_comparativo: 'hours',
    chart_questoes_dia: 'count',
  };
  if (!window.Chart || !Chart.instances) return 0;
  let applied = 0;
  for (const k of Object.keys(Chart.instances)) {
    const inst = Chart.instances[k];
    const chart = inst.chart || inst;
    const kind = chartIds[idDoCanvas(inst, chart)];
    if (!kind || !chart || !chart.options) continue;
    rotularGrafico(chart, kind);
    applied += 1;
  }
  return applied;
}

export const funcoesHorasCompose = [
  valorBruto, formatHourLabel, formatCountLabel, corSerie, idDoCanvas, pintarSerie,
  aplicarBarras, maiorValor, topoDoEixoY, ticksDeHoras, medidasDoGrafico, definirEixos,
  aplicarRotulos, aplicarPadding, rotularGrafico, labelHoursOnChartVertices,
];
