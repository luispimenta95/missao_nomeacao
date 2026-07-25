#!/usr/bin/env node
/**
 * Renderiza o Relatório do Coach com o PDFWriter/jsPDF do próprio painel Tutory.
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
import path from 'node:path';
import puppeteer from 'puppeteer';

function arg(name, fallback = null) {
  const idx = process.argv.indexOf(`--${name}`);
  if (idx === -1) return fallback;
  return process.argv[idx + 1] ?? fallback;
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

fs.mkdirSync(path.dirname(path.resolve(out)), { recursive: true });

const browser = await puppeteer.launch({
  headless: true,
  args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'],
});

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

  await page.goto(url, { waitUntil: 'networkidle2', timeout: 120000 });

  await page.waitForSelector('#btn_save', { timeout: 60000 });

  if (model === 'progresso') {
    await page.waitForFunction(() => {
      const c = document.getElementById('chart_progresso_principal');
      const nums = document.querySelectorAll('.row-numbers h5');
      const hasTitle = !!document.querySelector('h1, h2');
      const chartReady = c && ((c.width || 0) > 0 || (c.clientWidth || 0) > 0);
      return hasTitle && (chartReady || nums.length > 0);
    }, { timeout: 90000 });
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

  await new Promise((r) => setTimeout(r, 2500));

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

  let pdfBase64 = null;
  let writerError = null;
  try {
    pdfBase64 = await page.evaluate(async (reportModel) => {
      if (typeof PDFWriter === 'undefined' || !PDFWriter.start) {
        throw new Error('PDFWriter não encontrado na página');
      }

      if (reportModel === 'progresso') {
        const progressoOk = !!document.getElementById('chart_progresso_principal')
          || document.querySelectorAll('.row-numbers h5').length > 0;
        if (!progressoOk) {
          throw new Error('Seções incompletas no DOM (progresso)');
        }
      } else {
        const panoramaOk = document.querySelectorAll('.main-numbers h3').length >= 3;
        const assuntosOk = document.querySelectorAll('#tabela_questoes tbody tr').length > 0;
        if (!panoramaOk || !assuntosOk) {
          throw new Error(`Seções incompletas no DOM (panorama=${panoramaOk}, assuntos=${assuntosOk})`);
        }
      }

      return await new Promise((resolve, reject) => {
        const timeout = setTimeout(() => reject(new Error('Timeout gerando PDF')), 120000);

        try {
          PDFWriter.start();
          if (!PDFWriter.doc || typeof PDFWriter.doc.save !== 'function') {
            clearTimeout(timeout);
            reject(new Error('jsPDF não inicializado após PDFWriter.start()'));
            return;
          }

          PDFWriter.doc.save = function patchedSave(filename) {
            try {
              const dataUri = this.output('datauristring');
              const base64 = dataUri.split(',')[1] || '';
              clearTimeout(timeout);
              resolve({ filename: filename || 'relatorio.pdf', base64, via: 'PDFWriter' });
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
    }, model);
  } catch (err) {
    writerError = String(err && err.message ? err.message : err);
  }

  if (pdfBase64 && pdfBase64.base64) {
    const buf = Buffer.from(pdfBase64.base64, 'base64');
    fs.writeFileSync(out, buf);
    console.log(JSON.stringify({
      ok: true,
      out,
      bytes: buf.length,
      filename: pdfBase64.filename,
      model,
      via: 'PDFWriter',
    }));
  } else {
    // Fallback: print da página (ainda via Chromium) quando PDFWriter falha
    await page.emulateMediaType('screen');
    const buf = await page.pdf({
      path: out,
      format: 'A4',
      printBackground: true,
      margin: { top: '12mm', bottom: '12mm', left: '10mm', right: '10mm' },
    });
    if (!buf || buf.length < 500) {
      throw new Error(writerError || 'Falha ao gerar PDF (PDFWriter e page.pdf)');
    }
    console.log(JSON.stringify({
      ok: true,
      out,
      bytes: buf.length,
      filename: path.basename(out),
      model,
      via: 'page.pdf',
      writerError,
    }));
  }
} catch (err) {
  console.error(JSON.stringify({ ok: false, error: String(err && err.message ? err.message : err), model }));
  process.exit(1);
} finally {
  await browser.close();
}
