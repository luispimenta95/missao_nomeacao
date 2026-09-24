export function aplicarDatasBrasileiras() {
  function pareceAmericano(texto) {
    const re = /(?<!\d[/\-.])\b(\d{1,2})\/(\d{1,2})(?:\/(\d{2,4}))?\b/g;
    let m;
    const primeiros = [];
    const segundos = [];
    let americanos = 0;
    let brasileiros = 0;
    while ((m = re.exec(texto))) {
      const n1 = parseInt(m[1], 10);
      const n2 = parseInt(m[2], 10);
      primeiros.push(n1);
      segundos.push(n2);
      if (n1 <= 12 && n2 > 12) americanos += 1;
      if (n1 > 12 && n2 <= 12) brasileiros += 1;
    }
    if (primeiros.length === 0) return false;
    if (americanos > brasileiros) return true;
    if (brasileiros > americanos) return false;
    const varPrimeiro = new Set(primeiros).size > 1;
    const varSegundo = new Set(segundos).size > 1;
    if (!varPrimeiro && varSegundo && Math.max(...primeiros) <= 12) return true;
    if (varPrimeiro && !varSegundo && Math.max(...segundos) <= 12) return false;
    if (primeiros.length === 1) return false;
    return true;
  }

  function converterBarras(texto, forcarCurto, forcarCompleto) {
    return texto.replace(/(?<!\d[/\-.])\b(\d{1,2})\/(\d{1,2})(?:\/(\d{2,4}))?\b/g, (full, a, b, y) => {
      const n1 = parseInt(a, 10);
      const n2 = parseInt(b, 10);
      if (n1 < 1 || n1 > 31 || n2 < 1 || n2 > 31) return full;
      if (n1 > 12 && n2 <= 12) return full;
      const anoCompleto = typeof y === 'string' && y.length >= 4;
      const forcar = anoCompleto ? forcarCompleto : forcarCurto;
      const eAmericano = (n1 <= 12 && n2 > 12) || (forcar && n1 <= 12);
      if (!eAmericano) return full;
      const dd = String(n2).padStart(2, '0');
      const mm = String(n1).padStart(2, '0');
      return y ? `${dd}/${mm}/${y}` : `${dd}/${mm}`;
    });
  }

  const mesesEn = {
    january: 1, jan: 1, february: 2, feb: 2, march: 3, mar: 3,
    april: 4, apr: 4, may: 5, june: 6, jun: 6, july: 7, jul: 7,
    august: 8, aug: 8, september: 9, sept: 9, sep: 9,
    october: 10, oct: 10, november: 11, nov: 11, december: 12, dec: 12,
  };

  function converterMesesIngles(texto) {
    return texto.replace(
      /\b(January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|Jun|Jul|Aug|Sept|Sep|Oct|Nov|Dec)\.?\s+(\d{1,2})(?:,?\s+(\d{2,4}))?\b/gi,
      (full, mon, day, year) => {
        const mes = mesesEn[String(mon).toLowerCase().replace('.', '')];
        if (!mes) return full;
        const dd = String(parseInt(day, 10)).padStart(2, '0');
        const mm = String(mes).padStart(2, '0');
        return year ? `${dd}/${mm}/${year}` : `${dd}/${mm}`;
      },
    );
  }

  function converterIso(texto, locked) {
    return texto.replace(/\b(\d{4})[/\-.](\d{1,2})[/\-.](\d{1,2})\b/g, (full, y, m, d) => {
      const ano = parseInt(y, 10);
      const mes = parseInt(m, 10);
      const dia = parseInt(d, 10);
      if (ano < 1900 || ano > 2100 || mes < 1 || mes > 12 || dia < 1 || dia > 31) return full;
      const br = `${String(dia).padStart(2, '0')}/${String(mes).padStart(2, '0')}/${y}`;
      if (!locked) return br;
      const token = `\uE000ISO${locked.length}\uE001`;
      locked.push(br);
      return token;
    });
  }

  function textoParaBr(texto, forcar) {
    if (typeof texto === 'string' && texto !== '') {
      const locked = [];
      const iso = converterIso(texto, locked);
      const local = pareceAmericano(iso);
      const forcarCurto = forcar ?? local;
      let out = converterMesesIngles(converterBarras(iso, forcarCurto, local));
      for (let i = locked.length - 1; i >= 0; i -= 1) {
        out = out.split(`\uE000ISO${i}\uE001`).join(locked[i]);
      }
      return out;
    }
    return texto;
  }

  function dataParaBr(d) {
    if (!(d instanceof Date) || Number.isNaN(d.getTime())) return null;
    const dd = String(d.getDate()).padStart(2, '0');
    const mm = String(d.getMonth() + 1).padStart(2, '0');
    return `${dd}/${mm}/${d.getFullYear()}`;
  }

  function converterValor(valor, forcar) {
    const deData = dataParaBr(valor);
    if (deData) return deData;
    if (typeof valor === 'string') return textoParaBr(valor, forcar);
    if (Array.isArray(valor)) return valor.map((v) => converterValor(v, forcar));
    return valor;
  }

  function aceitarTexto(node) {
    const p = node.parentElement;
    if (!p) return NodeFilter.FILTER_REJECT;
    const tag = p.tagName;
    if (tag === 'SCRIPT' || tag === 'STYLE' || tag === 'NOSCRIPT') {
      return NodeFilter.FILTER_REJECT;
    }
    return NodeFilter.FILTER_ACCEPT;
  }

  function coletarTextos() {
    const partes = [];
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, { acceptNode: aceitarTexto });
    while (walker.nextNode()) partes.push(walker.currentNode.nodeValue || '');
    if (window.Chart && Chart.instances) {
      for (const k of Object.keys(Chart.instances)) {
        const inst = Chart.instances[k];
        const chart = inst.chart || inst;
        const labels = chart && chart.data && chart.data.labels ? chart.data.labels : [];
        for (const label of labels) {
          if (Array.isArray(label)) partes.push(label.join(' '));
          else if (label != null) partes.push(String(label));
        }
      }
    }
    return partes.join(' | ');
  }

  const jaAplicado = document.documentElement.getAttribute('data-br-dates') === '1';
  document.documentElement.setAttribute('data-br-dates', '1');
  const forcar = pareceAmericano(coletarTextos());

  if (!jaAplicado) {
    const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT, { acceptNode: aceitarTexto });
    const nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);
    for (const n of nodes) {
      const next = textoParaBr(n.nodeValue || '', forcar);
      if (next !== n.nodeValue) n.nodeValue = next;
    }
  }

  if (window.Chart && Chart.instances) {
    for (const k of Object.keys(Chart.instances)) {
      const inst = Chart.instances[k];
      const chart = inst.chart || inst;
      if (!chart || !chart.data || !chart.data.labels) continue;
      chart.data.labels = converterValor(chart.data.labels, forcar);
      try {
        if (chart.options) chart.options.animation = false;
        if (typeof chart.update === 'function') chart.update(0);
      } catch (e) {}
    }
  }

  return { forcar };
}

/** Rótulos permanentes nos gráficos de barras agrupadas (Chart.js v2). */
export function labelHoursOnChartVertices() {
  const chartIds = {
    chart_horas_diarias: 'hours',
    chart_line_comparativo: 'hours',
    chart_questoes_dia: 'count',
  };
  if (!window.Chart || !Chart.instances) return 0;

  function formatHourLabel(value) {
    if (value == null || value === '') return '';
    const raw = (value && typeof value === 'object' && 'y' in value) ? value.y : value;
    const num = Number(raw);
    if (!Number.isFinite(num)) return '';
    const rounded = Math.round(num * 10) / 10;
    const txt = Number.isInteger(rounded)
      ? String(rounded)
      : String(rounded).replace('.', ',');
    return `${txt}h`;
  }

  function formatCountLabel(value) {
    if (value == null || value === '') return '';
    const raw = (value && typeof value === 'object' && 'y' in value) ? value.y : value;
    const num = Number(raw);
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

  let applied = 0;
  for (const k of Object.keys(Chart.instances)) {
    const inst = Chart.instances[k];
    const chart = inst.chart || inst;
    const canvas = inst.canvas || (chart && chart.canvas) || (inst.ctx && inst.ctx.canvas);
    const id = canvas && canvas.id ? canvas.id : '';
    const kind = chartIds[id];
    if (!kind || !chart || !chart.options) continue;

    const nLabels = (chart.data && chart.data.labels) ? chart.data.labels.length : 1;
    const cat = nLabels >= 16 ? 0.62 : (nLabels >= 13 ? 0.68 : 0.72);
    const barPct = 0.88;
    const tickSize = nLabels >= 16 ? 9 : 10;
    if (chart.config) chart.config.type = 'bar';
    chart.type = 'bar';
    if (chart.data && Array.isArray(chart.data.datasets)) {
      chart.data.datasets.forEach((ds, i) => {
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
      });
    }
    chart.options.scales = chart.options.scales || {};
    chart.options.scales.xAxes = [{
      stacked: false,
      categoryPercentage: cat,
      barPercentage: barPct,
      gridLines: { display: false, drawBorder: false },
      ticks: {
        autoSkip: false,
        maxTicksLimit: Math.max(nLabels, 1),
        maxRotation: 0,
        minRotation: 0,
        fontSize: tickSize,
        fontColor: '#001D3D',
        fontStyle: '600',
      },
    }];
    const yTicks = { beginAtZero: true, fontSize: tickSize, fontColor: '#001D3D', fontStyle: '600' };
    if (kind === 'hours') {
      let maxH = 0;
      (chart.data.datasets || []).forEach((ds) => {
        (ds.data || []).forEach((v) => {
          const n = Number(v && typeof v === 'object' && 'y' in v ? v.y : v);
          if (Number.isFinite(n) && n > maxH) maxH = n;
        });
      });
      let yTop = 10;
      if (maxH > 10) {
        yTop = Math.max(10, Math.ceil(maxH / 2) * 2);
        if (maxH >= yTop) yTop += 2;
      }
      yTicks.max = yTop;
      yTicks.stepSize = 2;
    }
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
      ticks: yTicks,
    }];
    chart.options.legend = Object.assign({}, chart.options.legend || {}, {
      display: true,
      position: 'top',
      labels: { boxWidth: 10, fontSize: 10, fontColor: '#001D3D', fontStyle: '600', padding: 12 },
    });
    chart.options.cornerRadius = 3;

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

    chart.options.layout = chart.options.layout || {};
    const padding = chart.options.layout.padding;
    if (typeof padding === 'number') {
      chart.options.layout.padding = {
        top: Math.max(padding, 16),
        right: padding,
        bottom: Math.max(padding, 12),
        left: padding,
      };
    } else {
      const base = padding && typeof padding === 'object' ? padding : {};
      chart.options.layout.padding = Object.assign({}, base, {
        top: Math.max(Number(base.top) || 0, 16),
        bottom: Math.max(Number(base.bottom) || 0, 12),
      });
    }
    applied += 1;
  }
  return applied;
}
