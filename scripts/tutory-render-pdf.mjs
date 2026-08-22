#!/usr/bin/env node
/**
 * Renderiza o Relatório do Coach com o PDFWriter/jsPDF do próprio painel Tutory.
 *
 * Preferência: download nativo do jsPDF (CDP) — igual ao botão Baixar.
 * O painel usa Chart.js v2 (Chart.instances), não Chart.getChart.
 *
 * Uso:
 *   node scripts/tutory-render-pdf.mjs \
 *     --url "https://admin.tutory.com.br/documentos/relatorios/questoes?key=..." \
 *     --out "/path/relatorio.pdf" \
 *     [--model questoes|progresso|aluno|horas-liquidas|desempenho] \
 *     [--cookie "PHPSESSID=..."] \
 *     [--token "BearerToken"]
 */

import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import puppeteer from 'puppeteer';

function arg(name, fallback = null) {
  const idx = process.argv.indexOf(`--${name}`);
  if (idx === -1) return fallback;
  return process.argv[idx + 1] ?? fallback;
}

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms));
}

const url = arg('url');
const out = arg('out');
const model = (arg('model', 'questoes') || 'questoes').toLowerCase();
const cookieHeader = arg('cookie', '');
const token = arg('token', '');

if (!url || !out) {
  console.error('Uso: node scripts/tutory-render-pdf.mjs --url URL --out FILE [--model questoes|progresso|aluno|horas-liquidas|desempenho] [--cookie PHPSESSID=..] [--token TOKEN]');
  process.exit(1);
}

const outAbs = path.resolve(out);
fs.mkdirSync(path.dirname(outAbs), { recursive: true });

const downloadDir = fs.mkdtempSync(path.join(os.tmpdir(), 'tutory-pdf-'));

const browser = await puppeteer.launch({
  headless: true,
  args: [
    '--no-sandbox',
    '--disable-setuid-sandbox',
    '--disable-dev-shm-usage',
    '--lang=pt-BR',
  ],
});

function swapAmericanDatesInPdf(buf) {
  const original = buf.toString('latin1');
  const pdfLiteral = () => /\((?:\\.|[^\\)])*\)/g;
  const mmdd = () => /(?<!\d[/\-.])\b(\d{1,2})\/(\d{1,2})(?:\/(\d{2,4}))?\b/g;
  const locked = [];
  let s = original.replace(pdfLiteral(), (lit) => {
    const inner = lit.slice(1, -1).replace(/\b(\d{4})[/\-.](\d{1,2})[/\-.](\d{1,2})\b/g, (full, y, m, d) => {
      const ano = parseInt(y, 10);
      const mes = parseInt(m, 10);
      const dia = parseInt(d, 10);
      if (ano < 1900 || ano > 2100 || mes < 1 || mes > 12 || dia < 1 || dia > 31) return full;
      const token = `\x02ISO${locked.length}\x03`;
      locked.push(`${String(dia).padStart(2, '0')}/${String(mes).padStart(2, '0')}/${y}`);
      return token;
    });
    return `(${inner})`;
  });

  const matches = [];
  s.replace(pdfLiteral(), (lit) => {
    const inner = lit.slice(1, -1);
    const re = mmdd();
    let m;
    while ((m = re.exec(inner))) matches.push(m);
    return lit;
  });

  if (matches.length === 0 && locked.length === 0) return buf;

  let americanos = 0;
  let brasileiros = 0;
  const primeiros = [];
  const segundos = [];
  for (const m of matches) {
    const n1 = parseInt(m[1], 10);
    const n2 = parseInt(m[2], 10);
    primeiros.push(n1);
    segundos.push(n2);
    if (n1 <= 12 && n2 > 12) americanos += 1;
    if (n1 > 12 && n2 <= 12) brasileiros += 1;
  }
  const varPrimeiro = new Set(primeiros).size > 1;
  const varSegundo = new Set(segundos).size > 1;
  const forcar = primeiros.length > 0 && (
    americanos > brasileiros
    || (americanos === brasileiros && !varPrimeiro && varSegundo && Math.max(...primeiros) <= 12)
    || (americanos === brasileiros && varPrimeiro && varSegundo && primeiros.length > 1)
  );

  if (forcar) {
    s = s.replace(pdfLiteral(), (lit) => {
      const inner = lit.slice(1, -1).replace(mmdd(), (full, a, b, y) => {
        const n1 = parseInt(a, 10);
        const n2 = parseInt(b, 10);
        if (n1 < 1 || n1 > 31 || n2 < 1 || n2 > 31) return full;
        if (n1 > 12 && n2 <= 12) return full;
        const eAmericano = (n1 <= 12 && n2 > 12) || n1 <= 12;
        if (!eAmericano) return full;
        const dd = String(n2).padStart(2, '0');
        const mm = String(n1).padStart(2, '0');
        return y ? `${dd}/${mm}/${y}` : `${dd}/${mm}`;
      });
      return `(${inner})`;
    });
  }

  for (let i = locked.length - 1; i >= 0; i -= 1) {
    s = s.split(`\x02ISO${i}\x03`).join(locked[i]);
  }
  if (s === original) return buf;
  return Buffer.from(s, 'latin1');
}

function cleanupDownloadDir() {
  try {
    for (const f of fs.readdirSync(downloadDir)) {
      fs.unlinkSync(path.join(downloadDir, f));
    }
    fs.rmdirSync(downloadDir);
  } catch (_) {}
}

async function waitForDownload(dir, timeoutMs = 120000) {
  const start = Date.now();
  while (Date.now() - start < timeoutMs) {
    const files = fs.readdirSync(dir).filter((f) => f.endsWith('.pdf') && !f.endsWith('.crdownload'));
    if (files.length > 0) {
      const file = path.join(dir, files[0]);
      let last = -1;
      for (let i = 0; i < 20; i++) {
        const size = fs.statSync(file).size;
        if (size > 500 && size === last) {
          return file;
        }
        last = size;
        await sleep(250);
      }
      if (fs.statSync(file).size > 500) {
        return file;
      }
    }
    await sleep(300);
  }
  return null;
}

/** Rodado no browser: Chart.js v2 helpers */
const browserChartUtils = () => {
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
function stripPercentFromHoursCharts() {
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

function labelHoursOnChartVertices() {
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

/**
 * Converte datas MM/DD (EUA) para DD/MM (BR) no DOM, eixos dos gráficos e texto do jsPDF.
 * Função auto-contida para page.evaluate().
 */
function aplicarDatasBrasileiras() {
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

try {
  const page = await browser.newPage();
  await page.setViewport({ width: 1400, height: 900, deviceScaleFactor: 1 });
  await page.emulateTimezone('America/Sao_Paulo');
  await page.evaluateOnNewDocument(() => {
    Object.defineProperty(navigator, 'language', { get: () => 'pt-BR' });
    Object.defineProperty(navigator, 'languages', { get: () => ['pt-BR', 'pt'] });
    // O PDFWriter formata Date com locale da página; forçar pt-BR mesmo se passar en-US.
    const origDate = Date.prototype.toLocaleDateString;
    Date.prototype.toLocaleDateString = function toLocaleDateStringBr(_locales, options) {
      return origDate.call(this, 'pt-BR', options);
    };
    const origStr = Date.prototype.toLocaleString;
    Date.prototype.toLocaleString = function toLocaleStringBr(_locales, options) {
      return origStr.call(this, 'pt-BR', options);
    };
    const OrigDTF = Intl.DateTimeFormat;
    Intl.DateTimeFormat = function DateTimeFormatBr(locales, options) {
      return new OrigDTF('pt-BR', options);
    };
    Intl.DateTimeFormat.prototype = OrigDTF.prototype;
    if (typeof OrigDTF.supportedLocalesOf === 'function') {
      Intl.DateTimeFormat.supportedLocalesOf = OrigDTF.supportedLocalesOf.bind(OrigDTF);
    }
  });

  if (cookieHeader) {
    const cookies = cookieHeader.split(';').map((part) => {
      const [name, ...rest] = part.trim().split('=');
      return {
        name: name.trim(),
        value: rest.join('=').trim(),
        domain: 'admin.tutory.com.br',
        path: '/',
      };
    }).filter((c) => c.name && c.value);
    if (cookies.length) {
      await page.setCookie(...cookies);
    }
  }

  const extraHeaders = {
    'Accept-Language': 'pt-BR,pt;q=0.9,en;q=0.8',
  };
  if (token) {
    extraHeaders.Authorization = `Bearer ${token}`;
  }
  await page.setExtraHTTPHeaders(extraHeaders);

  const client = await page.createCDPSession();
  await client.send('Page.setDownloadBehavior', {
    behavior: 'allow',
    downloadPath: downloadDir,
  });

  await page.goto(url, { waitUntil: 'networkidle2', timeout: 120000 });
  await page.waitForSelector('#btn_save, #btn_download', { timeout: 60000 });

  if (model === 'progresso') {
    // Espera DADOS de TODOS os gráficos usados pelo PDFWriter (págs 1–9).
    // Sem isso, pág 2 (Panorama), 4 (taxa) e 5 (pizza/evolução) saem vazias ou agregadas.
    await page.waitForFunction(() => {
      if (!window.Chart || !Chart.instances) return false;

      function findChartByCanvasId(id) {
        for (const k of Object.keys(Chart.instances)) {
          const inst = Chart.instances[k];
          const canvas = inst.canvas || (inst.chart && inst.chart.canvas) || (inst.ctx && inst.ctx.canvas);
          if (canvas && canvas.id === id) return inst.chart || inst;
        }
        return null;
      }

      function chartReady(id, minLabels) {
        const ch = findChartByCanvasId(id);
        if (!ch || !ch.data || !ch.data.labels) return false;
        const canvas = ch.canvas || (ch.chart && ch.chart.canvas);
        if (!canvas || (canvas.width || 0) < 10) return false;
        return ch.data.labels.length >= minLabels;
      }

      const nums = document.querySelectorAll('.row-numbers h5').length;
      const questions = document.querySelectorAll('.row-questions .col-6, .row-questions [class*="col-"]').length;

      return nums >= 3
        && questions >= 2
        && chartReady('chart_progresso_principal', 1)
        && chartReady('chart_progresso_modalidades', 4)
        && chartReady('chart_top_disciplinas', 1) // pág 2
        && chartReady('chart_pizza_modalidades', 1) // pág 2
        && chartReady('chart_horas_diarias', 7) // pág 3 diário
        && chartReady('chart_tx_acerto', 2) // pág 4
        && chartReady('chart_bar_questoes_disciplina', 1) // pág 4
        && chartReady('chart_pizza_questoes', 1) // pág 5
        && chartReady('chart_linha_evolucao_questoes', 2) // pág 5
        && chartReady('chart_progresso_estudo', 1)
        && chartReady('chart_progresso_resumo', 1)
        && chartReady('chart_progresso_revisao', 1)
        && chartReady('chart_progresso_exercicio', 1);
    }, { timeout: 120000 });
  } else if (model === 'aluno') {
    await page.waitForFunction(() => {
      if (!window.Chart || !Chart.instances) return false;
      function findChartByCanvasId(id) {
        for (const k of Object.keys(Chart.instances)) {
          const inst = Chart.instances[k];
          const canvas = inst.canvas || (inst.chart && inst.chart.canvas) || (inst.ctx && inst.ctx.canvas);
          if (canvas && canvas.id === id) return inst.chart || inst;
        }
        return null;
      }
      function chartReady(id) {
        const ch = findChartByCanvasId(id);
        if (!ch) return false;
        const canvas = ch.canvas || (ch.chart && ch.chart.canvas);
        return canvas && (canvas.width || 0) >= 10;
      }
      return chartReady('chart_top_disciplina')
        && chartReady('chart_pie_modalidade')
        && chartReady('chart_tempo_dia')
        && chartReady('chart_acertos')
        && chartReady('chart_questoes_disciplina')
        && chartReady('chart_pie_questoes')
        && document.querySelectorAll('.row-numbers h5').length >= 1;
    }, { timeout: 120000 });
  } else if (model === 'horas-liquidas') {
    await page.waitForFunction(() => {
      if (!window.Chart || !Chart.instances) return false;
      function findChartByCanvasId(id) {
        for (const k of Object.keys(Chart.instances)) {
          const inst = Chart.instances[k];
          const canvas = inst.canvas || (inst.chart && inst.chart.canvas) || (inst.ctx && inst.ctx.canvas);
          if (canvas && canvas.id === id) return inst.chart || inst;
        }
        return null;
      }
      function chartReady(id) {
        const ch = findChartByCanvasId(id);
        if (!ch) return false;
        const canvas = ch.canvas || (ch.chart && ch.chart.canvas);
        return canvas && (canvas.width || 0) >= 10;
      }
      return chartReady('chart_pie_horas_disciplina')
        && chartReady('chart_line_comparativo')
        && chartReady('chart_line_progresso_disciplinas')
        && document.getElementById('tabela_horas_liquidas');
    }, { timeout: 120000 });
  } else if (model === 'desempenho') {
    await page.waitForFunction(() => {
      if (typeof echarts === 'undefined' || typeof html2canvas !== 'function') return false;
      if (!document.getElementById('btn_download')) return false;
      if (!document.querySelector('.main-header-card') || !document.querySelector('.metrics-grid')) return false;
      const ids = [
        'chart_panorama',
        'chart_progresso_mensal',
        'chart_modalidades',
        'chart_progresso_disciplina',
        'chart_horas_estudo',
        'chart_performance',
      ];
      return ids.every((id) => {
        const el = document.getElementById(id);
        if (!el) return false;
        const canvas = el.querySelector('canvas');
        return canvas && (canvas.width || 0) > 10;
      });
    }, { timeout: 120000 });
  } else {
    await page.waitForSelector('#chart_questoes_dia', { timeout: 60000 });
    await page.waitForSelector('.main-numbers h3', { timeout: 60000 });
    await page.waitForSelector('#tabela_questoes tbody tr', { timeout: 60000 });
    await page.waitForFunction(() => {
      if (!window.Chart || !Chart.instances) return false;
      function findChartByCanvasId(id) {
        for (const k of Object.keys(Chart.instances)) {
          const inst = Chart.instances[k];
          const canvas = inst.canvas || (inst.chart && inst.chart.canvas) || (inst.ctx && inst.ctx.canvas);
          if (canvas && canvas.id === id) return inst.chart || inst;
        }
        return null;
      }
      const c = findChartByCanvasId('chart_questoes_dia');
      const labels = c && c.data && c.data.labels ? c.data.labels.length : 0;
      const rows = document.querySelectorAll('#tabela_questoes tbody tr');
      const nums = document.querySelectorAll('.main-numbers h3');
      return labels >= 1 && rows.length > 0 && nums.length >= 3;
    }, { timeout: 60000 });
  }

  // Datas no padrão brasileiro (DD/MM) antes de congelar os gráficos
  await page.evaluate(aplicarDatasBrasileiras);

  // Sem % em pizza/barras de horas; horas nos vértices do gráfico diário
  await page.evaluate(stripPercentFromHoursCharts);
  await page.evaluate(labelHoursOnChartVertices);

  // Congela Chart.js v2 e redesenha frames finais (getChart não existe no v2 do painel)
  const frozen = await page.evaluate(() => {
    if (!window.Chart || !Chart.instances) return 0;
    let n = 0;
    for (const k of Object.keys(Chart.instances)) {
      const inst = Chart.instances[k];
      const chart = inst.chart || inst;
      try {
        if (chart.options) chart.options.animation = false;
        if (typeof chart.stop === 'function') chart.stop();
        if (typeof chart.update === 'function') chart.update(0);
        n++;
      } catch (e) {}
    }
    return n;
  });

  await sleep(1500);

  await page.evaluate(() => {
    if (!window.Chart || !Chart.instances) return;
    for (const k of Object.keys(Chart.instances)) {
      const inst = Chart.instances[k];
      const chart = inst.chart || inst;
      try {
        if (chart.options) chart.options.animation = false;
        if (typeof chart.update === 'function') chart.update(0);
      } catch (e) {}
    }
  });

  const chartSnapshot = await page.evaluate(() => {
    function findChartByCanvasId(id) {
      if (!window.Chart || !Chart.instances) return null;
      for (const k of Object.keys(Chart.instances)) {
        const inst = Chart.instances[k];
        const canvas = inst.canvas || (inst.chart && inst.chart.canvas) || (inst.ctx && inst.ctx.canvas);
        if (canvas && canvas.id === id) return inst.chart || inst;
      }
      return null;
    }
    const snap = {};
    for (const id of [
      'chart_horas_diarias',
      'chart_progresso_principal',
      'chart_progresso_modalidades',
      'chart_top_disciplinas',
      'chart_pizza_modalidades',
      'chart_tx_acerto',
      'chart_bar_questoes_disciplina',
      'chart_pizza_questoes',
      'chart_linha_evolucao_questoes',
      'chart_progresso_estudo',
      'chart_questoes_dia',
      'chart_top_disciplina',
      'chart_pie_modalidade',
      'chart_tempo_dia',
      'chart_acertos',
      'chart_questoes_disciplina',
      'chart_pie_questoes',
      'chart_pie_horas_disciplina',
      'chart_line_comparativo',
      'chart_line_progresso_disciplinas',
    ]) {
      const ch = findChartByCanvasId(id);
      if (!ch || !ch.data) continue;
      const canvas = ch.canvas || (ch.chart && ch.chart.canvas);
      snap[id] = {
        labels: (ch.data.labels || []).length,
        firstLabels: (ch.data.labels || []).slice(0, 3),
        ds0: ch.data.datasets && ch.data.datasets[0]
          ? (ch.data.datasets[0].data || []).slice(0, 5)
          : null,
        width: canvas ? canvas.width : 0,
      };
    }
    return snap;
  });

  if (model === 'progresso') {
    const required = {
      chart_horas_diarias: 7,
      chart_top_disciplinas: 1,
      chart_pizza_modalidades: 1,
      chart_tx_acerto: 2,
      chart_pizza_questoes: 1,
      chart_linha_evolucao_questoes: 2,
    };
    for (const [id, min] of Object.entries(required)) {
      const labels = chartSnapshot[id]?.labels || 0;
      const width = chartSnapshot[id]?.width || 0;
      if (labels < min || width < 10) {
        throw new Error(`${id} incompleto (labels=${labels}, width=${width}, minLabels=${min})`);
      }
    }
  }

  for (const f of fs.readdirSync(downloadDir)) {
    fs.unlinkSync(path.join(downloadDir, f));
  }

  let via = null;
  let filename = path.basename(outAbs);

  try {
    await page.evaluate(aplicarDatasBrasileiras);
    if (model === 'desempenho') {
      await page.evaluate(() => {
        const btn = document.getElementById('btn_download');
        if (!btn) {
          throw new Error('btn_download não encontrado na página de Desempenho');
        }
        btn.click();
      });
    } else {
      await page.evaluate(stripPercentFromHoursCharts);
      await page.evaluate(labelHoursOnChartVertices);
      await page.evaluate((reportModel) => {
      if (typeof PDFWriter === 'undefined' || !PDFWriter.start) {
        throw new Error('PDFWriter não encontrado na página');
      }

      function findChartByCanvasId(id) {
        if (!window.Chart || !Chart.instances) return null;
        for (const k of Object.keys(Chart.instances)) {
          const inst = Chart.instances[k];
          const canvas = inst.canvas || (inst.chart && inst.chart.canvas) || (inst.ctx && inst.ctx.canvas);
          if (canvas && canvas.id === id) return inst.chart || inst;
        }
        return null;
      }

      if (reportModel === 'progresso') {
        const horas = findChartByCanvasId('chart_horas_diarias');
        const horasLabels = horas && horas.data && horas.data.labels ? horas.data.labels.length : 0;
        if (horasLabels < 7) {
          throw new Error(`chart_horas_diarias incompleto (labels=${horasLabels}, esperado >= 7 diários)`);
        }
        const top = findChartByCanvasId('chart_top_disciplinas');
        if (!top || !top.data || !top.data.labels || top.data.labels.length < 1) {
          throw new Error('chart_top_disciplinas ausente (página 2 Panorama)');
        }
        const tx = findChartByCanvasId('chart_tx_acerto');
        if (!tx || !tx.data || !tx.data.labels || tx.data.labels.length < 2) {
          throw new Error('chart_tx_acerto incompleto (página 4)');
        }
      } else if (reportModel === 'questoes') {
        const panoramaOk = document.querySelectorAll('.main-numbers h3').length >= 3;
        const assuntosOk = document.querySelectorAll('#tabela_questoes tbody tr').length > 0;
        if (!panoramaOk || !assuntosOk) {
          throw new Error(`Seções incompletas no DOM (panorama=${panoramaOk}, assuntos=${assuntosOk})`);
        }
      }

      // freeze before snapshot into jsPDF
      if (window.Chart && Chart.instances) {
        for (const k of Object.keys(Chart.instances)) {
          const inst = Chart.instances[k];
          const chart = inst.chart || inst;
          try {
            if (chart.options) chart.options.animation = false;
            if (typeof chart.update === 'function') chart.update(0);
          } catch (e) {}
        }
      }

      // Bug do painel: PDFWriter usa section-4-3 duas vezes na pág 5;
      // a evolução deve usar section-4-4 ("Por fim, vamos analisar...").
      if (reportModel === 'progresso' && typeof PDFWriter.start === 'function') {
        let src = PDFWriter.start.toString();
        let seen = 0;
        src = src.replace(/\$\(\s*['"]\.section-4-3['"]\s*\)/g, (m) => {
          seen += 1;
          return seen === 2 ? "$('.section-4-4')" : m;
        });
        if (seen >= 2) {
          // eslint-disable-next-line no-eval
          PDFWriter.start = eval('(' + src + ')');
        }
      }

      // addChart seguro: não aborta o PDF inteiro se um canvas ainda estiver 0x0
      if (typeof PDFWriter.addChart === 'function' && !PDFWriter.__safeAddChart) {
        const originalAddChart = PDFWriter.addChart.bind(PDFWriter);
        PDFWriter.addChart = function safeAddChart(chart, y) {
          if (!chart || !(chart.width > 0) || !(chart.height > 0)) {
            return 0;
          }
          try {
            return originalAddChart(chart, y);
          } catch (e) {
            return 0;
          }
        };
        PDFWriter.__safeAddChart = true;
      }

      PDFWriter.start();
      if (!PDFWriter.doc || typeof PDFWriter.doc.save !== 'function') {
        throw new Error('jsPDF não inicializado após PDFWriter.start()');
      }
      PDFWriter.output();
    }, model);
    }

    const downloaded = await waitForDownload(downloadDir, 120000);
    if (!downloaded) {
      throw new Error('Timeout aguardando download do PDFWriter');
    }
    fs.copyFileSync(downloaded, outAbs);
    via = model === 'desempenho' ? 'html2canvas-download' : 'PDFWriter-download';
    filename = path.basename(downloaded);
  } catch (downloadErr) {
    // Fallback base64 (NÃO usar page.pdf — gráficos saem errados)
    try {
      await page.evaluate(aplicarDatasBrasileiras);
      let pdfBase64;
      if (model === 'desempenho') {
        pdfBase64 = await page.evaluate(async () => {
          const JsPDF = window.jspdf && window.jspdf.jsPDF;
          if (!JsPDF) {
            throw new Error('jsPDF UMD não encontrado na página de Desempenho');
          }
          return await new Promise((resolve, reject) => {
            const timeout = setTimeout(() => reject(new Error('Timeout gerando PDF desempenho base64')), 120000);
            const proto = JsPDF.prototype;
            const origSave = proto.save;
            proto.save = function patchedSave(name) {
              try {
                const dataUri = this.output('datauristring');
                const base64 = dataUri.split(',')[1] || '';
                proto.save = origSave;
                clearTimeout(timeout);
                resolve({ filename: name || 'desempenho.pdf', base64 });
              } catch (err) {
                proto.save = origSave;
                clearTimeout(timeout);
                reject(err);
              }
            };
            const btn = document.getElementById('btn_download');
            if (!btn) {
              proto.save = origSave;
              clearTimeout(timeout);
              reject(new Error('btn_download não encontrado'));
              return;
            }
            btn.click();
          });
        });
      } else {
        await page.evaluate(stripPercentFromHoursCharts);
        await page.evaluate(labelHoursOnChartVertices);
        pdfBase64 = await page.evaluate(async () => {
        if (typeof PDFWriter === 'undefined' || !PDFWriter.start) {
          throw new Error('PDFWriter não encontrado na página');
        }
        if (window.Chart && Chart.instances) {
          for (const k of Object.keys(Chart.instances)) {
            const inst = Chart.instances[k];
            const chart = inst.chart || inst;
            try {
              if (chart.options) chart.options.animation = false;
              if (typeof chart.update === 'function') chart.update(0);
            } catch (e) {}
          }
        }
        return await new Promise((resolve, reject) => {
          const timeout = setTimeout(() => reject(new Error('Timeout gerando PDF base64')), 120000);
          try {
            PDFWriter.start();
            if (!PDFWriter.doc || typeof PDFWriter.doc.save !== 'function') {
              clearTimeout(timeout);
              reject(new Error('jsPDF não inicializado'));
              return;
            }
            PDFWriter.doc.save = function patchedSave(name) {
              try {
                const dataUri = this.output('datauristring');
                const base64 = dataUri.split(',')[1] || '';
                clearTimeout(timeout);
                resolve({ filename: name || 'relatorio.pdf', base64 });
              } catch (err) {
                clearTimeout(timeout);
                reject(err);
              }
            };
            PDFWriter.output();
          } catch (err) {
            clearTimeout(timeout);
            reject(err);
          }
        });
      });
      }

      const buf = Buffer.from(pdfBase64.base64, 'base64');
      if (buf.length < 500) {
        throw new Error(`PDF base64 vazio (${buf.length} bytes)`);
      }
      fs.writeFileSync(outAbs, buf);
      via = model === 'desempenho' ? 'html2canvas-base64' : 'PDFWriter-base64';
      filename = pdfBase64.filename || filename;
    } catch (base64Err) {
      throw new Error(
        `Falha PDFWriter (download: ${String(downloadErr && downloadErr.message ? downloadErr.message : downloadErr)}; `
        + `base64: ${String(base64Err && base64Err.message ? base64Err.message : base64Err)})`
      );
    }
  }

  const finalBuf0 = fs.readFileSync(outAbs);
  if (finalBuf0.length < 500) {
    throw new Error(`PDF final vazio (${finalBuf0.length} bytes)`);
  }
  const finalBuf = swapAmericanDatesInPdf(finalBuf0);
  if (finalBuf !== finalBuf0) {
    fs.writeFileSync(outAbs, finalBuf);
  }

  // Conteúdo oficial do painel vem do jsPDF. page.pdf do Chromium não tem jsPDF e altera gráficos.
  const hasJsPdf = finalBuf.includes(Buffer.from('jsPDF'));
  const imageCount = (finalBuf.toString('latin1').match(/\/Subtype\s*\/Image/g) || []).length;
  if (!hasJsPdf) {
    throw new Error(`PDF sem jsPDF (provável captura incompleta; images=${imageCount})`);
  }

  console.log(JSON.stringify({
    ok: true,
    out: outAbs,
    bytes: finalBuf.length,
    filename,
    model,
    via,
    chartsFrozen: frozen,
    chartSnapshot,
    hasJsPdf,
    imageCount,
  }));
} catch (err) {
  console.error(JSON.stringify({ ok: false, error: String(err && err.message ? err.message : err), model }));
  process.exit(1);
} finally {
  await browser.close();
  cleanupDownloadDir();
}
