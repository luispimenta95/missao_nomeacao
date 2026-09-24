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
      } catch (e) {}
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
  const hideIds = new Set([
    'chart_top_disciplinas',
    'chart_pizza_modalidades',
  ]);
  if (!window.Chart || !Chart.instances) return 0;
  let n = 0;
  for (const k of Object.keys(Chart.instances)) {
    const inst = Chart.instances[k];
    const chart = inst.chart || inst;
    const canvas = inst.canvas || (chart && chart.canvas) || (inst.ctx && inst.ctx.canvas);
    const id = canvas && canvas.id ? canvas.id : '';
    if (!hideIds.has(id) || !chart || !chart.options) continue;

    chart.options.plugins = chart.options.plugins || {};
    chart.options.plugins.datalabels = Object.assign({}, chart.options.plugins.datalabels || {}, {
      display: false,
      formatter: function () {
        return '';
      },
    });
    // Plugin datalabels no Chart.js v2 também lê options.datalabels
    chart.options.datalabels = Object.assign({}, chart.options.datalabels || {}, {
      display: false,
      formatter: function () {
        return '';
      },
    });

    const scales = chart.options.scales || {};
    for (const key of ['xAxes', 'yAxes']) {
      const axes = scales[key];
      if (!Array.isArray(axes)) continue;
      for (const axis of axes) {
        if (!axis || !axis.ticks) continue;
        if (typeof axis.ticks.callback === 'function') {
          axis.ticks.callback = function (value) {
            return value;
          };
        }
      }
    }
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

  let n = 0;
  for (const k of Object.keys(Chart.instances)) {
    const inst = Chart.instances[k];
    const chart = inst.chart || inst;
    const canvas = inst.canvas || (chart && chart.canvas) || (inst.ctx && inst.ctx.canvas);
    const id = canvas && canvas.id ? canvas.id : '';
    if (!hoursIds.has(id) || !chart || !chart.options) continue;

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
    n += 1;
  }
  return n;
}
