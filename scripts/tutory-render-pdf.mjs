#!/usr/bin/env node
/**
 * Renderiza o Relatório do Coach com o PDFWriter/jsPDF do próprio painel Tutory.
 *
 * Preferência: deixa o jsPDF fazer o download nativo (CDP) — igual ao botão Baixar.
 * Isso evita transferir PDFs grandes (~10MB+) via base64 no page.evaluate, que
 * falha em servidores com pouca memória e caía no page.pdf() sem gráficos.
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

// PDFs oficiais do painel (com gráficos) ficam bem acima disso.
const MIN_BYTES = model === 'progresso' ? 800_000 : 400_000;

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
      // aguarda estabilizar tamanho
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

try {
  const page = await browser.newPage();
  // Paisagem: o botão Baixar do painel bloqueia retrato em alguns modelos
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

  // Captura o download nativo do jsPDF.save()
  const client = await page.createCDPSession();
  await client.send('Page.setDownloadBehavior', {
    behavior: 'allow',
    downloadPath: downloadDir,
  });

  await page.goto(url, { waitUntil: 'networkidle2', timeout: 120000 });
  await page.waitForSelector('#btn_save', { timeout: 60000 });

  if (model === 'progresso') {
    // Espera os gráficos principais do Progresso no Plano pintarem
    await page.waitForFunction(() => {
      const ids = [
        'chart_progresso_principal',
        'chart_progresso_modalidades',
        'chart_top_disciplinas',
        'chart_horas_diarias',
        'chart_tx_acerto',
        'chart_progresso_estudo',
      ];
      let ready = 0;
      for (const id of ids) {
        const c = document.getElementById(id);
        if (c && ((c.width || 0) > 5 || (c.clientWidth || 0) > 5)) {
          ready++;
        }
      }
      const nums = document.querySelectorAll('.row-numbers h5').length;
      return ready >= 3 && nums >= 3;
    }, { timeout: 120000 });
  } else {
    await page.waitForSelector('#chart_questoes_dia', { timeout: 60000 });
    await page.waitForSelector('.main-numbers h3', { timeout: 60000 });
    await page.waitForSelector('#tabela_questoes tbody tr', { timeout: 60000 });
    await page.waitForFunction(() => {
      const c = document.getElementById('chart_questoes_dia');
      const rows = document.querySelectorAll('#tabela_questoes tbody tr');
      const nums = document.querySelectorAll('.main-numbers h3');
      return c && c.width > 10 && c.height > 10 && rows.length > 0 && nums.length >= 3;
    }, { timeout: 60000 });
  }

  await sleep(3000);

  await page.evaluate(() => {
    const canvases = document.querySelectorAll('canvas');
    canvases.forEach((canvas) => {
      try {
        const chart = window.Chart && Chart.getChart ? Chart.getChart(canvas) : null;
        if (chart) {
          if (chart.options) chart.options.animation = false;
          if (typeof chart.stop === 'function') chart.stop();
          if (typeof chart.update === 'function') chart.update('none');
          if (typeof chart.draw === 'function') chart.draw();
        }
      } catch (e) {}
    });
  });

  // Limpa downloads anteriores da pasta temp
  for (const f of fs.readdirSync(downloadDir)) {
    fs.unlinkSync(path.join(downloadDir, f));
  }

  let via = null;
  let filename = path.basename(outAbs);

  // 1) PDFWriter oficial → download nativo (mesmo fluxo do botão Baixar)
  try {
    await page.evaluate((reportModel) => {
      if (typeof PDFWriter === 'undefined' || !PDFWriter.start) {
        throw new Error('PDFWriter não encontrado na página');
      }
      if (reportModel === 'progresso') {
        const ok = !!document.getElementById('chart_progresso_principal')
          || document.querySelectorAll('.row-numbers h5').length > 0;
        if (!ok) throw new Error('Seções incompletas no DOM (progresso)');
      } else {
        const panoramaOk = document.querySelectorAll('.main-numbers h3').length >= 3;
        const assuntosOk = document.querySelectorAll('#tabela_questoes tbody tr').length > 0;
        if (!panoramaOk || !assuntosOk) {
          throw new Error(`Seções incompletas no DOM (panorama=${panoramaOk}, assuntos=${assuntosOk})`);
        }
      }
      PDFWriter.start();
      if (!PDFWriter.doc || typeof PDFWriter.doc.save !== 'function') {
        throw new Error('jsPDF não inicializado após PDFWriter.start()');
      }
      PDFWriter.output();
    }, model);

    const downloaded = await waitForDownload(downloadDir, 120000);
    if (downloaded) {
      const size = fs.statSync(downloaded).size;
      if (size >= MIN_BYTES) {
        fs.copyFileSync(downloaded, outAbs);
        via = 'PDFWriter-download';
        filename = path.basename(downloaded);
      } else {
        throw new Error(`Download PDFWriter muito pequeno (${size} bytes)`);
      }
    } else {
      throw new Error('Timeout aguardando download do PDFWriter');
    }
  } catch (downloadErr) {
    // 2) Fallback: base64 via evaluate (ok para PDFs menores; pode falhar nos grandes)
    try {
      const pdfBase64 = await page.evaluate(async () => {
        if (typeof PDFWriter === 'undefined' || !PDFWriter.start) {
          throw new Error('PDFWriter não encontrado na página');
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
      if (buf.length < MIN_BYTES) {
        throw new Error(`PDF base64 muito pequeno (${buf.length} bytes); downloadErr=${downloadErr}`);
      }
      fs.writeFileSync(outAbs, buf);
      via = 'PDFWriter-base64';
      filename = pdfBase64.filename || filename;
    } catch (base64Err) {
      // 3) Último recurso: NÃO usar page.pdf para progresso/questões —
      // ele imprime o HTML sem os gráficos do PDFWriter (resultado incompleto).
      throw new Error(
        `Falha PDFWriter (download: ${String(downloadErr && downloadErr.message ? downloadErr.message : downloadErr)}; `
        + `base64: ${String(base64Err && base64Err.message ? base64Err.message : base64Err)})`
      );
    }
  }

  const finalSize = fs.statSync(outAbs).size;
  if (finalSize < MIN_BYTES) {
    throw new Error(`PDF final incompleto (${finalSize} bytes < ${MIN_BYTES})`);
  }

  console.log(JSON.stringify({
    ok: true,
    out: outAbs,
    bytes: finalSize,
    filename,
    model,
    via,
  }));
} catch (err) {
  console.error(JSON.stringify({ ok: false, error: String(err && err.message ? err.message : err), model }));
  process.exit(1);
} finally {
  await browser.close();
  cleanupDownloadDir();
}
