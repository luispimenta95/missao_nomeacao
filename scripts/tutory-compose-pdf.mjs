#!/usr/bin/env node
/**
 * Compõe um único PDF a partir das seções pedidas dos 5 relatórios do Coach.
 *
 * Não redesenha os relatórios: abre cada URL oficial, espera os gráficos reais,
 * rasteriza canvases (Chart.js / ECharts) e recorta só os blocos solicitados.
 * O casco visual é o CSS moderno do relatório Desempenho (NOVO).
 *
 * Uso:
 *   node scripts/tutory-compose-pdf.mjs \
 *     --out "/path/relatorio.pdf" \
 *     --url-desempenho "https://admin.tutory.com.br/documentos/relatorios/desempenho?key=..." \
 *     --url-aluno "https://..." \
 *     --url-horas-liquidas "https://..." \
 *     --url-questoes "https://..." \
 *     --url-progresso "https://..." \
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

const out = arg('out');
const urls = {
  desempenho: arg('url-desempenho'),
  aluno: arg('url-aluno'),
  'horas-liquidas': arg('url-horas-liquidas'),
  questoes: arg('url-questoes'),
  progresso: arg('url-progresso'),
};
const cookieHeader = arg('cookie', '');
const token = arg('token', '');

if (!out || Object.values(urls).some((u) => !u)) {
  console.error(
    'Uso: node scripts/tutory-compose-pdf.mjs --out FILE'
    + ' --url-desempenho URL --url-aluno URL --url-horas-liquidas URL'
    + ' --url-questoes URL --url-progresso URL [--cookie PHPSESSID=..] [--token TOKEN]',
  );
  process.exit(1);
}

const outAbs = path.resolve(out);
fs.mkdirSync(path.dirname(outAbs), { recursive: true });

/**
 * Converte datas MM/DD (EUA) para DD/MM (BR) no DOM e eixos dos gráficos.
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

function stripPercentFromHoursCharts() {
  const hoursIds = new Set(['chart_horas_diarias']);
  if (!window.Chart || !Chart.instances) return 0;
  let n = 0;
  for (const k of Object.keys(Chart.instances)) {
    const inst = Chart.instances[k];
    const chart = inst.chart || inst;
    const canvas = inst.canvas || (chart && chart.canvas) || (inst.ctx && inst.ctx.canvas);
    const id = canvas && canvas.id ? canvas.id : '';
    if (!hoursIds.has(id) || !chart || !chart.options) continue;
    chart.options.plugins = chart.options.plugins || {};
    chart.options.plugins.datalabels = Object.assign({}, chart.options.plugins.datalabels || {}, {
      display: false,
      formatter() { return ''; },
    });
    chart.options.datalabels = Object.assign({}, chart.options.datalabels || {}, {
      display: false,
      formatter() { return ''; },
    });
    n += 1;
  }
  return n;
}

const COMPOSER_CSS = `
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');

html, body {
  padding-top: 0 !important;
  padding-bottom: 24px !important;
}
.report-top-bar,
.actions,
#btn_save,
#btn_download,
#btn_whatsapp_link,
#theme_toggle,
.no-print {
  display: none !important;
}
.mn-unified {
  max-width: 1100px;
  margin: 0 auto;
  padding: 12px 16px 32px;
}
.mn-kicker {
  font-size: 12px;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  color: var(--texto-secundario, #6B7280);
  margin: 28px 0 12px;
}
.mn-kicker:first-child {
  margin-top: 0;
}
.mn-legacy {
  background: var(--bg-card, #fff);
  border-radius: var(--radius-xl, 20px);
  box-shadow: var(--sombra-card, 0 2px 8px rgba(0,0,0,0.08));
  padding: 20px 22px;
  margin-bottom: 20px;
  color: var(--texto-primario, #1F2937);
}
.mn-legacy h2 {
  font-size: 18px;
  font-weight: 700;
  margin: 0 0 8px;
  padding-left: 12px;
  border-left: 4px solid var(--cor-destaque, #F4B942);
  color: var(--texto-primario, #1F2937);
}
.mn-legacy h2 + p,
.mn-legacy p[class^="section-"] {
  color: var(--texto-secundario, #6B7280);
  font-size: 14px;
  margin: 0 0 14px;
}
.mn-legacy table {
  width: 100%;
  border-collapse: collapse;
}
.mn-legacy img {
  max-width: 100%;
  height: auto;
  display: block;
}
.mn-legacy .row {
  display: flex;
  flex-wrap: wrap;
  margin-left: -8px;
  margin-right: -8px;
}
.mn-legacy .col-4,
.mn-legacy .col-6,
.mn-legacy .col-2 {
  padding: 8px;
  box-sizing: border-box;
}
.mn-legacy .col-4 { width: 33.333%; }
.mn-legacy .col-6 { width: 50%; }
.mn-legacy .col-2 { width: 16.666%; }
.mn-legacy .main-numbers {
  background: var(--bg-card, #fff);
  border: 1px solid var(--borda-cor, #E5E7EB);
  border-radius: 12px;
  padding: 12px 8px;
  text-align: center;
  margin: 0;
}
.mn-legacy .main-numbers p {
  font-weight: 500;
  color: var(--texto-secundario, #6B7280);
  font-size: 12px;
  margin: 0;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}
.mn-legacy .main-numbers h3 {
  font-weight: 700;
  color: var(--texto-primario, #1F2937);
  font-size: 22px;
  margin: 6px 0 0;
}
.mn-empty {
  color: var(--texto-terciario, #9CA3AF);
  font-size: 13px;
  text-align: center;
  padding: 12px 8px;
  margin: 0;
}
.mn-insights-wrap {
  background: var(--bg-card, #fff);
  border: 3px solid var(--cor-principal, #3264ff);
  border-radius: var(--radius-xl, 20px);
  box-shadow: 0 10px 32px rgba(50, 100, 255, 0.18);
  padding: 28px 28px 24px;
  margin: 8px 0 28px;
  page-break-inside: avoid;
}
.mn-insights-wrap .insights-panel {
  background: transparent;
  padding: 0;
  margin: 0;
}
.mn-insights-wrap h6 {
  font-size: 22px;
  font-weight: 700;
  color: var(--cor-principal, #3264ff);
  margin: 0 0 16px;
  letter-spacing: -0.02em;
}
.mn-insights-wrap p {
  font-size: 16px;
  line-height: 1.55;
  color: var(--texto-primario, #1F2937);
  margin: 0 0 10px;
  font-weight: 400;
}
@media (max-width: 768px) {
  .mn-legacy .col-4,
  .mn-legacy .col-6,
  .mn-legacy .col-2 {
    width: 100%;
  }
  .metrics-grid,
  .two-col-grid {
    grid-template-columns: 1fr !important;
  }
}
@media print {
  .mn-insights-wrap,
  .main-header-card,
  .metrics-grid,
  .two-col-grid {
    page-break-inside: avoid;
  }
  .mn-legacy table { page-break-inside: auto; }
}
`;

async function launchBrowser() {
  return puppeteer.launch({
    headless: true,
    args: [
      '--no-sandbox',
      '--disable-setuid-sandbox',
      '--disable-dev-shm-usage',
      '--lang=pt-BR',
    ],
  });
}

async function preparePage(browser) {
  const page = await browser.newPage();
  await page.setViewport({ width: 1400, height: 900, deviceScaleFactor: 2 });
  await page.emulateTimezone('America/Sao_Paulo');
  await page.evaluateOnNewDocument(() => {
    Object.defineProperty(navigator, 'language', { get: () => 'pt-BR' });
    Object.defineProperty(navigator, 'languages', { get: () => ['pt-BR', 'pt'] });
    try {
      localStorage.setItem('theme', 'light');
    } catch (e) {}
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

  const extraHeaders = { 'Accept-Language': 'pt-BR,pt;q=0.9,en;q=0.8' };
  if (token) extraHeaders.Authorization = `Bearer ${token}`;
  await page.setExtraHTTPHeaders(extraHeaders);
  return page;
}

async function gotoReport(page, url) {
  await page.goto(url, { waitUntil: 'networkidle2', timeout: 120000 });
  await page.waitForSelector('#btn_save, #btn_download, .report-container, .report-aluno', {
    timeout: 60000,
  });
}

async function prepareCharts(page) {
  await page.evaluate(aplicarDatasBrasileiras);
  await page.evaluate(stripPercentFromHoursCharts);
  await page.evaluate(() => {
    if (!window.Chart || !Chart.instances) return;
    for (const k of Object.keys(Chart.instances)) {
      const inst = Chart.instances[k];
      const chart = inst.chart || inst;
      try {
        if (chart.options) chart.options.animation = false;
        if (typeof chart.stop === 'function') chart.stop();
        if (typeof chart.update === 'function') chart.update(0);
      } catch (e) {}
    }
  });
  await sleep(800);
}

async function extractDesempenho(page) {
  await gotoReport(page, urls.desempenho);
  await page.waitForFunction(() => {
    if (!document.querySelector('.main-header-card') || !document.querySelector('.metrics-grid')) {
      return false;
    }
    const ids = ['chart_horas_estudo', 'chart_performance'];
    return ids.every((id) => {
      const el = document.getElementById(id);
      if (!el) return false;
      const canvas = el.querySelector('canvas');
      return canvas && (canvas.width || 0) > 10;
    });
  }, { timeout: 120000 });
  await prepareCharts(page);

  const css = await page.evaluate(() => (
    Array.from(document.querySelectorAll('style')).map((s) => s.textContent || '').join('\n')
  ));

  return page.evaluate(() => {
    function rasterizeRoot(root) {
      if (!root) return;
      if (typeof echarts !== 'undefined') {
        const nodes = root.querySelectorAll
          ? Array.from(root.querySelectorAll('div[id^="chart_"], .chart-container'))
          : [];
        if (root.id && String(root.id).startsWith('chart_')) nodes.push(root);
        for (const el of nodes) {
          try {
            const inst = echarts.getInstanceByDom(el);
            if (inst && typeof inst.getDataURL === 'function') {
              const img = document.createElement('img');
              img.src = inst.getDataURL({ type: 'png', pixelRatio: 2, backgroundColor: 'transparent' });
              img.alt = el.id || 'gráfico';
              img.style.width = '100%';
              img.style.height = 'auto';
              img.style.display = 'block';
              el.innerHTML = '';
              el.appendChild(img);
            }
          } catch (e) {}
        }
      }
      const canvases = root.querySelectorAll ? Array.from(root.querySelectorAll('canvas')) : [];
      for (const canvas of canvases) {
        try {
          if ((canvas.width || 0) < 4) continue;
          const img = document.createElement('img');
          img.src = canvas.toDataURL('image/png');
          img.alt = canvas.id || 'gráfico';
          img.style.maxWidth = '100%';
          img.style.width = '100%';
          img.style.height = 'auto';
          canvas.replaceWith(img);
        } catch (e) {}
      }
    }
    function htmlOf(sel) {
      const el = document.querySelector(sel);
      if (!el) return '';
      rasterizeRoot(el);
      return el.outerHTML;
    }
    const grids = Array.from(document.querySelectorAll('.two-col-grid'));
    const lastGrid = grids.length ? grids[grids.length - 1] : null;
    if (lastGrid) rasterizeRoot(lastGrid);
    return {
      header: htmlOf('.main-header-card'),
      metrics: htmlOf('.metrics-grid'),
      twoCol: lastGrid ? lastGrid.outerHTML : '',
    };
  }).then((parts) => ({ ...parts, css }));
}

async function extractAluno(page) {
  await gotoReport(page, urls.aluno);
  await page.waitForSelector('#tabela_estudos, h2.section-5', { timeout: 60000 });
  await prepareCharts(page);
  return page.evaluate(() => {
    function rasterizeRoot(root) {
      if (!root) return;
      const canvases = root.querySelectorAll ? Array.from(root.querySelectorAll('canvas')) : [];
      for (const canvas of canvases) {
        try {
          if ((canvas.width || 0) < 4) continue;
          const img = document.createElement('img');
          img.src = canvas.toDataURL('image/png');
          img.style.maxWidth = '100%';
          img.style.width = '100%';
          canvas.replaceWith(img);
        } catch (e) {}
      }
    }
    function htmlFromHeading(selector) {
      const h = document.querySelector(selector);
      if (!h) return '';
      const wrap = document.createElement('div');
      let n = h;
      while (n) {
        if (n !== h && n.tagName === 'H2') break;
        rasterizeRoot(n);
        wrap.appendChild(n.cloneNode(true));
        n = n.nextElementSibling;
      }
      return wrap.innerHTML;
    }
    const estudos = htmlFromHeading('h2.section-5');
    const revisoes = htmlFromHeading('h2.section-4');
    const revisoesRows = document.querySelectorAll('#tabela_revisoes tbody tr').length;
    return { estudos, revisoes, revisoesRows };
  });
}

async function extractHoras(page) {
  await gotoReport(page, urls['horas-liquidas']);
  await page.waitForSelector('#chart_line_comparativo, h2.section-2', { timeout: 60000 });
  await page.waitForSelector('#tabela_horas_liquidas, h2.section-4', { timeout: 60000 });
  await page.waitForFunction(() => {
    const canvas = document.getElementById('chart_line_comparativo');
    const table = document.getElementById('tabela_horas_liquidas');
    return canvas && table && ((canvas.width || 0) > 10 || (window.Chart && Chart.instances));
  }, { timeout: 120000 });
  await prepareCharts(page);
  return page.evaluate(() => {
    function rasterizeRoot(root) {
      if (!root) return;
      const canvases = root.querySelectorAll ? Array.from(root.querySelectorAll('canvas')) : [];
      if (root.tagName === 'CANVAS') canvases.push(root);
      for (const canvas of canvases) {
        try {
          if ((canvas.width || 0) < 4) continue;
          const img = document.createElement('img');
          img.src = canvas.toDataURL('image/png');
          img.style.maxWidth = '100%';
          img.style.width = '100%';
          canvas.replaceWith(img);
        } catch (e) {}
      }
    }
    function htmlFromHeading(selector) {
      const h = document.querySelector(selector);
      if (!h) return '';
      const wrap = document.createElement('div');
      let n = h;
      while (n) {
        if (n !== h && n.tagName === 'H2') break;
        rasterizeRoot(n);
        wrap.appendChild(n.cloneNode(true));
        n = n.nextElementSibling;
      }
      return wrap.innerHTML;
    }
    return {
      tempo: htmlFromHeading('h2.section-2'),
      historico: htmlFromHeading('h2.section-4'),
    };
  });
}

async function extractQuestoes(page) {
  await gotoReport(page, urls.questoes);
  await page.waitForSelector('#chart_questoes_dia, .main-numbers, h2.section-1', { timeout: 60000 });
  await page.waitForSelector('#tabela_questoes, h2.section-4', { timeout: 60000 });
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
    const nums = document.querySelectorAll('.main-numbers h3').length;
    return nums >= 1 && (labels >= 1 || document.getElementById('chart_questoes_dia'));
  }, { timeout: 60000 });
  await prepareCharts(page);
  return page.evaluate(() => {
    function rasterizeRoot(root) {
      if (!root) return;
      const canvases = root.querySelectorAll ? Array.from(root.querySelectorAll('canvas')) : [];
      if (root.tagName === 'CANVAS') canvases.push(root);
      for (const canvas of canvases) {
        try {
          if ((canvas.width || 0) < 4) continue;
          const img = document.createElement('img');
          img.src = canvas.toDataURL('image/png');
          img.style.maxWidth = '100%';
          img.style.width = '100%';
          canvas.replaceWith(img);
        } catch (e) {}
      }
    }
    function htmlFromHeading(selector) {
      const h = document.querySelector(selector);
      if (!h) return '';
      const wrap = document.createElement('div');
      let n = h;
      while (n) {
        if (n !== h && n.tagName === 'H2') break;
        rasterizeRoot(n);
        wrap.appendChild(n.cloneNode(true));
        n = n.nextElementSibling;
      }
      return wrap.innerHTML;
    }
    return {
      panorama: htmlFromHeading('h2.section-1'),
      assuntos: htmlFromHeading('h2.section-4'),
    };
  });
}

async function extractProgresso(page) {
  await gotoReport(page, urls.progresso);
  await page.waitForSelector('h2.section-3, #chart_horas_diarias, .insights-panel', { timeout: 60000 });
  await page.waitForFunction(() => {
    const canvas = document.getElementById('chart_horas_diarias');
    const panel = document.querySelector('.insights-panel');
    if (!panel) return false;
    if (!window.Chart || !Chart.instances) return canvas != null;
    return canvas && ((canvas.width || 0) > 10);
  }, { timeout: 120000 });
  await prepareCharts(page);
  return page.evaluate(() => {
    function rasterizeRoot(root) {
      if (!root) return;
      const canvases = root.querySelectorAll ? Array.from(root.querySelectorAll('canvas')) : [];
      if (root.tagName === 'CANVAS') canvases.push(root);
      for (const canvas of canvases) {
        try {
          if ((canvas.width || 0) < 4) continue;
          const img = document.createElement('img');
          img.src = canvas.toDataURL('image/png');
          img.style.maxWidth = '100%';
          img.style.width = '100%';
          canvas.replaceWith(img);
        } catch (e) {}
      }
    }
    const h = document.querySelector('h2.section-3');
    const wrap = document.createElement('div');
    if (h) {
      let n = h;
      while (n) {
        if (n !== h && (n.tagName === 'H2' || n.classList.contains('insights-panel'))) break;
        rasterizeRoot(n);
        wrap.appendChild(n.cloneNode(true));
        n = n.nextElementSibling;
      }
    }
    const panel = document.querySelector('.insights-panel');
    if (panel) rasterizeRoot(panel);
    return {
      motivacao: wrap.innerHTML,
      insights: panel ? panel.outerHTML : '',
    };
  });
}

function block(kicker, inner, extraClass = 'mn-legacy') {
  if (!inner || !String(inner).trim()) return '';
  const kickerHtml = kicker ? `<p class="mn-kicker">${kicker}</p>` : '';
  return `${kickerHtml}<section class="${extraClass}">${inner}</section>`;
}

function buildHtml(extracted) {
  const desempenhoCss = extracted.desempenho.css || '';
  const parts = [];
  parts.push(block('', `${extracted.desempenho.header}${extracted.desempenho.metrics}${extracted.desempenho.twoCol}`, 'mn-desempenho'));
  parts.push(block('Estudos', extracted.aluno.estudos));
  if (extracted.aluno.revisoes && extracted.aluno.revisoes.trim()) {
    let revisoesHtml = extracted.aluno.revisoes;
    if (extracted.aluno.revisoesRows === 0 && !/mn-empty/.test(revisoesHtml)) {
      revisoesHtml += '<p class="mn-empty">Nenhuma revisão registrada neste período.</p>';
    }
    parts.push(block('Revisões no período', revisoesHtml));
  }
  parts.push(block('Horas líquidas · desempenho ao longo do tempo', extracted.horas.tempo));
  parts.push(block('Horas líquidas · histórico', extracted.horas.historico));
  parts.push(block('Questões', `${extracted.questoes.panorama}`));
  parts.push(block('Performance por assunto', extracted.questoes.assuntos));
  parts.push(block('Progresso no plano · motivação', extracted.progresso.motivacao));
  parts.push(block('', extracted.progresso.insights, 'mn-insights-wrap'));

  return `<!DOCTYPE html>
<html lang="pt-BR" data-theme="light">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Relatório consolidado</title>
  <link rel="stylesheet" href="https://static.tutory.com.br/vendor/bootstrap/bootstrap.4.5.0.min.css" />
  <link rel="stylesheet" href="https://use.fontawesome.com/releases/v5.13.1/css/all.css" />
  <style>${desempenhoCss}</style>
  <style>${COMPOSER_CSS}</style>
</head>
<body class="report-container">
  <div class="mn-unified">
    ${parts.join('\n')}
  </div>
</body>
</html>`;
}

const browser = await launchBrowser();
try {
  const page = await preparePage(browser);
  const extracted = {
    desempenho: await extractDesempenho(page),
    aluno: await extractAluno(page),
    horas: await extractHoras(page),
    questoes: await extractQuestoes(page),
    progresso: await extractProgresso(page),
  };

  const missing = [];
  if (!extracted.desempenho.header || !extracted.desempenho.metrics) missing.push('desempenho');
  if (!extracted.aluno.estudos) missing.push('estudos');
  if (!extracted.horas.tempo || !extracted.horas.historico) missing.push('horas-liquidas');
  if (!extracted.questoes.panorama || !extracted.questoes.assuntos) missing.push('questoes');
  if (!extracted.progresso.motivacao || !extracted.progresso.insights) missing.push('progresso');
  if (missing.length) {
    throw new Error(`Seções obrigatórias ausentes: ${missing.join(', ')}`);
  }

  const html = buildHtml(extracted);
  const tmpHtml = path.join(os.tmpdir(), `tutory-consolidado-${process.pid}.html`);
  fs.writeFileSync(tmpHtml, html, 'utf8');

  await page.setViewport({ width: 1200, height: 1600, deviceScaleFactor: 1 });
  await page.setContent(html, { waitUntil: 'networkidle0', timeout: 120000 });
  await sleep(500);
  await page.pdf({
    path: outAbs,
    format: 'A4',
    printBackground: true,
    margin: { top: '12mm', right: '10mm', bottom: '14mm', left: '10mm' },
  });
  try { fs.unlinkSync(tmpHtml); } catch (_) {}

  const bytes = fs.statSync(outAbs).size;
  if (bytes < 2000) {
    throw new Error(`PDF consolidado vazio (${bytes} bytes)`);
  }

  console.log(JSON.stringify({
    ok: true,
    out: outAbs,
    bytes,
    via: 'compose-print',
    revisoesRows: extracted.aluno.revisoesRows,
    sections: {
      desempenhoHeader: Boolean(extracted.desempenho.header),
      desempenhoMetrics: Boolean(extracted.desempenho.metrics),
      desempenhoTwoCol: Boolean(extracted.desempenho.twoCol),
      estudos: Boolean(extracted.aluno.estudos),
      revisoes: Boolean(extracted.aluno.revisoes),
      horasTempo: Boolean(extracted.horas.tempo),
      horasHistorico: Boolean(extracted.horas.historico),
      questoesPanorama: Boolean(extracted.questoes.panorama),
      questoesAssuntos: Boolean(extracted.questoes.assuntos),
      motivacao: Boolean(extracted.progresso.motivacao),
      insights: Boolean(extracted.progresso.insights),
    },
  }));
} catch (err) {
  console.error(JSON.stringify({
    ok: false,
    error: String(err && err.message ? err.message : err),
    model: 'consolidado',
  }));
  process.exit(1);
} finally {
  await browser.close();
}
