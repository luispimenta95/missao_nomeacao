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
import { swapAmericanDatesInPdf } from './tutory-render/pdf-datas-americanas.mjs';
import { prepararPagina } from './tutory-render/preparar-pagina.mjs';
import {
  aplicarDatasBrasileiras,
} from './tutory-render/datas-pagina.mjs';
import {
  labelHoursOnChartVertices,
  stripPercentFromHoursCharts,
} from './tutory-render/graficos-pagina.mjs';
import { cleanupDownloadDir, waitForDownload } from './tutory-render/download-pdf.mjs';

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

try {
  const { page, frozen, chartSnapshot } = await prepararPagina(browser, {
    url,
    model,
    cookieHeader,
    token,
    downloadDir,
  });

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
  cleanupDownloadDir(downloadDir);
}
