export const browserChartUtils = () => {
  function findChartByCanvasId(id) {
    if (!window.Chart || !Chart.instances) return null;
    for (const k of Object.keys(Chart.instances)) {
      const inst = Chart.instances[k];
      const canvas = inst.canvas || (inst.chart && inst.chart.canvas) || (inst.ctx && inst.ctx.canvas);
      if (canvas && canvas.id === id) {
        return inst.chart || inst;
      }
    }
    return null;
  }

  function freezeAllCharts() {
    if (!window.Chart || !Chart.instances) return 0;
    let n = 0;
    for (const k of Object.keys(Chart.instances)) {
      const inst = Chart.instances[k];
      const chart = inst.chart || inst;
      try {
        if (chart.options) chart.options.animation = false;
        if (typeof chart.stop === 'function') chart.stop();
        // Chart.js v2: update(0) redesenha sem animação
        if (typeof chart.update === 'function') chart.update(0);
        n++;
      } catch {}
    }
    return n;
  }

  function labelCount(id) {
    const ch = findChartByCanvasId(id);
    return ch && ch.data && ch.data.labels ? ch.data.labels.length : 0;
  }

  return { findChartByCanvasId, freezeAllCharts, labelCount };
};

/** Remove rótulos de % em gráficos de horas (pizza/barras). O diário ganha horas nos vértices. */
export function stripPercentFromHoursCharts() {
  function ocultarRotuloDePercentual(chart) {
    const vazio = { display: false, formatter() { return ''; } };
    chart.options.plugins = chart.options.plugins || {};
    chart.options.plugins.datalabels = Object.assign({}, chart.options.plugins.datalabels || {}, vazio);
    chart.options.datalabels = Object.assign({}, chart.options.datalabels || {}, vazio);
  }

  function limparEixoDeHoras(chart) {
    const scales = chart.options.scales || {};
    for (const key of ['xAxes', 'yAxes']) {
      const axes = scales[key];
      if (!Array.isArray(axes)) continue;
      for (const axis of axes) {
        if (!axis || !axis.ticks || typeof axis.ticks.callback !== 'function') continue;
        axis.ticks.callback = function (value) { return value; };
      }
    }
  }

  function idDoCanvas(inst, chart) {
    const canvas = inst.canvas || (chart && chart.canvas) || (inst.ctx && inst.ctx.canvas);
    return canvas && canvas.id ? canvas.id : '';
  }

  const hideIds = new Set(['chart_top_disciplinas', 'chart_pizza_modalidades']);
  if (!window.Chart || !Chart.instances) return 0;
  let n = 0;
  for (const k of Object.keys(Chart.instances)) {
    const inst = Chart.instances[k];
    const chart = inst.chart || inst;
    const id = idDoCanvas(inst, chart);
    if (!hideIds.has(id) || !chart || !chart.options) continue;
    ocultarRotuloDePercentual(chart);
    limparEixoDeHoras(chart);
    n += 1;
  }
  return n;
}

export function labelHoursOnChartVertices() {
  const hoursIds = new Set(['chart_horas_diarias']);
  if (!window.Chart || !Chart.instances) return 0;

  function formatHourLabel(value) {
    const raw = (value && typeof value === 'object' && 'y' in value) ? value.y : value;
    const num = Number(raw);
    if (!Number.isFinite(num)) return '';
    const rounded = Math.round(num * 10) / 10;
    if (rounded === 0) return '';
    const txt = Number.isInteger(rounded)
      ? String(rounded)
      : String(rounded).replace('.', ',');
    return `${txt}h`;
  }

  function rotularGrafico(chart) {
    const labels = {
      display: true,
      clamp: true,
      clip: false,
      color: '#111827',
      backgroundColor: 'rgba(255,255,255,0.85)',
      borderRadius: 3,
      padding: { top: 1, right: 3, bottom: 1, left: 3 },
      font: { size: 9, weight: 'bold' },
      offset: 4,
      formatter: formatHourLabel,
      align(ctx) {
        return ctx.datasetIndex === 0 ? 'end' : 'start';
      },
      anchor(ctx) {
        return ctx.datasetIndex === 0 ? 'end' : 'start';
      },
    };

    chart.options.plugins = chart.options.plugins || {};
    chart.options.plugins.datalabels = Object.assign({}, chart.options.plugins.datalabels || {}, labels);
    chart.options.datalabels = Object.assign({}, chart.options.datalabels || {}, labels);

    chart.options.layout = chart.options.layout || {};
    const padding = chart.options.layout.padding;
    if (typeof padding === 'number') {
      chart.options.layout.padding = {
        top: Math.max(padding, 18),
        right: padding,
        bottom: Math.max(padding, 18),
        left: padding,
      };
    } else {
      const base = padding && typeof padding === 'object' ? padding : {};
      chart.options.layout.padding = Object.assign({}, base, {
        top: Math.max(Number(base.top) || 0, 18),
        bottom: Math.max(Number(base.bottom) || 0, 18),
      });
    }
  }

  function idDoCanvas(inst, chart) {
    const canvas = inst.canvas || (chart && chart.canvas) || (inst.ctx && inst.ctx.canvas);
    return canvas && canvas.id ? canvas.id : '';
  }

  let n = 0;
  for (const k of Object.keys(Chart.instances)) {
    const inst = Chart.instances[k];
    const chart = inst.chart || inst;
    const id = idDoCanvas(inst, chart);
    if (!hoursIds.has(id) || !chart || !chart.options) continue;
    rotularGrafico(chart);
    n += 1;
  }
  return n;
}
