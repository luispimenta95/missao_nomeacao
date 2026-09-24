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
    if (typeof texto !== 'string' || texto === '') return texto;
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

  let charts = 0;
  if (window.Chart && Chart.instances) {
    for (const k of Object.keys(Chart.instances)) {
      const inst = Chart.instances[k];
      const chart = inst.chart || inst;
      if (!chart || !chart.data || !chart.data.labels) continue;
      chart.data.labels = converterValor(chart.data.labels, forcar);
      const scales = chart.options && chart.options.scales ? chart.options.scales : {};
      for (const key of ['xAxes', 'yAxes']) {
        const axes = scales[key];
        if (!Array.isArray(axes)) continue;
        for (const axis of axes) {
          if (axis.time && axis.time.displayFormats) {
            for (const k of Object.keys(axis.time.displayFormats)) {
              const v = String(axis.time.displayFormats[k]);
              axis.time.displayFormats[k] = v
                .replace(/YYYY\/MM\/DD/g, 'DD/MM/YYYY')
                .replace(/YYYY-MM-DD/g, 'DD/MM/YYYY')
                .replace(/yyyy\/mm\/dd/g, 'DD/MM/YYYY')
                .replace(/MM\/DD/g, 'DD/MM')
                .replace(/M\/D/g, 'D/M');
            }
          }
          if (!axis.ticks) axis.ticks = {};
          const orig = axis.ticks.callback;
          axis.ticks.callback = function patchedTick(value, index, values) {
            const asDate = dataParaBr(value instanceof Date ? value : null);
            if (asDate) return asDate.slice(0, 5);
            const raw = orig ? orig.call(this, value, index, values) : value;
            const txt = String(raw);
            return textoParaBr(txt, pareceAmericano(txt) || forcar);
          };
        }
      }
      try {
        if (typeof chart.update === 'function') chart.update(0);
      } catch (e) {}
      charts += 1;
    }
  }

  function patchText(proto) {
    if (!proto || typeof proto.text !== 'function' || proto.__brDates) return;
    const orig = proto.text;
    proto.text = function patchedText(text, ...args) {
      const joined = typeof text === 'string'
        ? text
        : (Array.isArray(text) ? text.map((t) => (t == null ? '' : String(t))).join(' | ') : '');
      const usar = forcar || pareceAmericano(joined);
      return orig.call(this, converterValor(text, usar), ...args);
    };
    proto.__brDates = true;
  }
  const ctors = [];
  if (typeof jsPDF === 'function') ctors.push(jsPDF);
  if (typeof window.jsPDF === 'function') ctors.push(window.jsPDF);
  if (window.jspdf && typeof window.jspdf.jsPDF === 'function') ctors.push(window.jspdf.jsPDF);
  for (const ctor of ctors) {
    patchText(ctor.API);
    patchText(ctor.prototype);
  }
  if (window.moment && typeof window.moment.locale === 'function') {
    window.moment.locale('pt-br');
  }
  function patchFormatFn(obj) {
    if (!obj || typeof obj.format !== 'function' || obj.__brDates) return;
    const orig = obj.format;
    obj.format = function patchedFormat(fmt, ...rest) {
      if (typeof fmt === 'string') {
        fmt = fmt
          .replace(/YYYY\/MM\/DD/g, 'DD/MM/YYYY')
          .replace(/YYYY-MM-DD/g, 'DD/MM/YYYY')
          .replace(/yyyy\/mm\/dd/g, 'DD/MM/YYYY')
          .replace(/yyyy-mm-dd/g, 'DD/MM/YYYY')
          .replace(/YYYY\/M\/D/g, 'D/M/YYYY')
          .replace(/YYYY\/MM/g, 'MM/YYYY')
          .replace(/MM\/DD\/YYYY/g, 'DD/MM/YYYY')
          .replace(/MM\/DD/g, 'DD/MM');
      }
      return orig.call(this, fmt, ...rest);
    };
    obj.__brDates = true;
  }
  if (window.moment) {
    patchFormatFn(window.moment.fn);
    patchFormatFn(window.moment.prototype);
  }
  if (window.dayjs && window.dayjs.prototype) {
    patchFormatFn(window.dayjs.prototype);
  }

  if (typeof PDFWriter !== 'undefined') {
    const alvos = [];
    if (typeof PDFWriter.start === 'function') alvos.push(['start', PDFWriter.start]);
    for (const nome of Object.keys(PDFWriter)) {
      if (typeof PDFWriter[nome] === 'function' && nome !== 'start') {
        alvos.push([nome, PDFWriter[nome]]);
      }
    }
    for (const [nome, fn] of alvos) {
      let src = '';
      try { src = fn.toString(); } catch (e) { continue; }
      const next = src
        .replace(/['"]YYYY\/M\/D['"]/g, "'D/M/YYYY'")
        .replace(/['"]yyyy\/M\/d['"]/g, "'d/M/yyyy'")
        .replace(/['"]YYYY\/MM\/DD['"]/g, "'DD/MM/YYYY'")
        .replace(/['"]YYYY-MM-DD['"]/g, "'DD/MM/YYYY'")
        .replace(/['"]yyyy\/mm\/dd['"]/g, "'dd/mm/yyyy'")
        .replace(/['"]yyyy-mm-dd['"]/g, "'dd/mm/yyyy'")
        .replace(/['"]YYYY\/MM['"]/g, "'MM/YYYY'")
        .replace(/['"]MM\/DD\/YYYY['"]/g, "'DD/MM/YYYY'")
        .replace(/['"]MM\/DD\/YY['"]/g, "'DD/MM/YY'")
        .replace(/['"]MM\/DD['"]/g, "'DD/MM'")
        .replace(/['"]M\/D\/YYYY['"]/g, "'D/M/YYYY'")
        .replace(/['"]en-US['"]/g, "'pt-BR'");
      if (next !== src) {
        try { PDFWriter[nome] = eval('(' + next + ')'); } catch (e) {}
      }
    }
  }

  return { forcar, charts };
}
