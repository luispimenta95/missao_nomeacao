#!/usr/bin/env node
/**
 * Renderiza o Relatório do Coach com o PDFWriter/jsPDF do próprio painel Tutory.
 *
 * Uso:
 *   node scripts/tutory-render-pdf.mjs \
 *     --url "https://admin.tutory.com.br/documentos/relatorios/questoes?key=..." \
 *     --out "/path/relatorio.pdf" \
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
const cookieHeader = arg('cookie', '');
const token = arg('token', '');

if (!url || !out) {
  console.error('Uso: node scripts/tutory-render-pdf.mjs --url URL --out FILE [--cookie PHPSESSID=..] [--token TOKEN]');
  process.exit(1);
}

fs.mkdirSync(path.dirname(path.resolve(out)), { recursive: true });

const browser = await puppeteer.launch({
  headless: true,
  args: ['--no-sandbox', '--disable-setuid-sandbox', '--disable-dev-shm-usage'],
});

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

  await page.goto(url, { waitUntil: 'networkidle2', timeout: 120000 });

  await page.waitForSelector('#btn_save', { timeout: 60000 });
  await page.waitForSelector('#chart_questoes_dia', { timeout: 60000 });

  // Espera Chart.js pintar e congela animações (igual ao script Selenium)
  await page.waitForFunction(() => {
    const c = document.getElementById('chart_questoes_dia');
    return c && c.width > 10 && c.height > 10;
  }, { timeout: 60000 });

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

  // Intercepta jsPDF.save para capturar o ArrayBuffer
  const pdfBase64 = await page.evaluate(async () => {
    if (typeof PDFWriter === 'undefined' || !PDFWriter.start) {
      throw new Error('PDFWriter não encontrado na página');
    }

    return await new Promise((resolve, reject) => {
      const timeout = setTimeout(() => reject(new Error('Timeout gerando PDF')), 90000);

      try {
        PDFWriter.start();
        if (!PDFWriter.doc || typeof PDFWriter.doc.save !== 'function') {
          clearTimeout(timeout);
          reject(new Error('jsPDF não inicializado após PDFWriter.start()'));
          return;
        }

        const originalSave = PDFWriter.doc.save.bind(PDFWriter.doc);
        PDFWriter.doc.save = function patchedSave(filename) {
          try {
            const dataUri = this.output('datauristring');
            const base64 = dataUri.split(',')[1] || '';
            clearTimeout(timeout);
            resolve({ filename: filename || 'relatorio.pdf', base64 });
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
  fs.writeFileSync(out, buf);
  console.log(JSON.stringify({
    ok: true,
    out,
    bytes: buf.length,
    filename: pdfBase64.filename,
  }));
} catch (err) {
  console.error(JSON.stringify({ ok: false, error: String(err && err.message ? err.message : err) }));
  process.exit(1);
} finally {
  await browser.close();
}
