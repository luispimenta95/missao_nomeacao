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
 *     [--model questoes|progresso] \
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
  console.error('Uso: node scripts/tutory-render-pdf.mjs --url URL --out FILE [--model questoes|progresso] [--cookie PHPSESSID=..] [--token TOKEN]');
  process.exit(1);
}

const outAbs = path.resolve(out);
fs.mkdirSync(path.dirname(outAbs), { recursive: true });

const downloadDir = fs.mkdtempSync(path.join(os.tmpdir(), 'tutory-pdf-'));

const browser = await puppeteer.launch({
  headless: true,
  args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'],
});

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

try {
  const page = await browser.newPage();
  await page.setViewport({ width: 1400, height: 900, deviceScaleFactor: 1 });

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

  if (token) {
    await page.setExtraHTTPHeaders({
      Authorization: `Bearer ${token}`,
    });
  }

  const client = await page.createCDPSession();
  await client.send('Page.setDownloadBehavior', {
    behavior: 'allow',
    downloadPath: downloadDir,
  });

  await page.goto(url, { waitUntil: 'networkidle2', timeout: 120000 });
  await page.waitForSelector('#btn_save', { timeout: 60000 });

  if (model === 'progresso') {
    // Espera DADOS dos gráficos (Chart.js v2).
    // chart_horas_diarias com 1 label agregado = conteúdo diferente do download manual.
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

      const horas = findChartByCanvasId('chart_horas_diarias');
      const principal = findChartByCanvasId('chart_progresso_principal');
      const modalidades = findChartByCanvasId('chart_progresso_modalidades');
      const estudo = findChartByCanvasId('chart_progresso_estudo');

      const horasLabels = horas && horas.data && horas.data.labels ? horas.data.labels.length : 0;
      const principalOk = !!(principal && principal.data && principal.data.datasets && principal.data.datasets[0]);
      const modalidadesOk = !!(modalidades && modalidades.data && modalidades.data.labels && modalidades.data.labels.length >= 4);
      const estudoOk = !!(estudo && estudo.data && estudo.data.labels && estudo.data.labels.length >= 1);
      const nums = document.querySelectorAll('.row-numbers h5').length;

      return horasLabels >= 7 && principalOk && modalidadesOk && estudoOk && nums >= 3;
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
      'chart_progresso_estudo',
      'chart_questoes_dia',
    ]) {
      const ch = findChartByCanvasId(id);
      if (!ch || !ch.data) continue;
      snap[id] = {
        labels: (ch.data.labels || []).length,
        firstLabels: (ch.data.labels || []).slice(0, 3),
        ds0: ch.data.datasets && ch.data.datasets[0]
          ? (ch.data.datasets[0].data || []).slice(0, 5)
          : null,
      };
    }
    return snap;
  });

  if (model === 'progresso') {
    const horasLabels = chartSnapshot.chart_horas_diarias?.labels || 0;
    if (horasLabels < 7) {
      throw new Error(`chart_horas_diarias ainda agregado/incompleto (labels=${horasLabels})`);
    }
  }

  for (const f of fs.readdirSync(downloadDir)) {
    fs.unlinkSync(path.join(downloadDir, f));
  }

  let via = null;
  let filename = path.basename(outAbs);

  try {
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
      } else {
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

      PDFWriter.start();
      if (!PDFWriter.doc || typeof PDFWriter.doc.save !== 'function') {
        throw new Error('jsPDF não inicializado após PDFWriter.start()');
      }
      PDFWriter.output();
    }, model);

    const downloaded = await waitForDownload(downloadDir, 120000);
    if (!downloaded) {
      throw new Error('Timeout aguardando download do PDFWriter');
    }
    fs.copyFileSync(downloaded, outAbs);
    via = 'PDFWriter-download';
    filename = path.basename(downloaded);
  } catch (downloadErr) {
    // Fallback base64 do mesmo PDFWriter (NÃO usar page.pdf — gráficos saem errados)
    try {
      const pdfBase64 = await page.evaluate(async () => {
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

      const buf = Buffer.from(pdfBase64.base64, 'base64');
      if (buf.length < 500) {
        throw new Error(`PDF base64 vazio (${buf.length} bytes)`);
      }
      fs.writeFileSync(outAbs, buf);
      via = 'PDFWriter-base64';
      filename = pdfBase64.filename || filename;
    } catch (base64Err) {
      throw new Error(
        `Falha PDFWriter (download: ${String(downloadErr && downloadErr.message ? downloadErr.message : downloadErr)}; `
        + `base64: ${String(base64Err && base64Err.message ? base64Err.message : base64Err)})`
      );
    }
  }

  const finalBuf = fs.readFileSync(outAbs);
  if (finalBuf.length < 500) {
    throw new Error(`PDF final vazio (${finalBuf.length} bytes)`);
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
