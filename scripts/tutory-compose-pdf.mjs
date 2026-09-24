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
import { buildHtml, headerPeriodoHtml } from './tutory-compose/montar-html.mjs';
import {
  extractAluno,
  extractDesempenho,
  extractHoras,
  extractProgresso,
  extractQuestoes,
} from './tutory-compose/extrair-secoes.mjs';

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
const rotuloPeriodo = arg('rotulo-periodo', '');
const cookieHeader = arg('cookie', '');
const token = arg('token', '');

if (!out || Object.values(urls).some((u) => !u)) {
  console.error(
    'Uso: node scripts/tutory-compose-pdf.mjs --out FILE'
    + ' --url-desempenho URL --url-aluno URL --url-horas-liquidas URL'
    + ' --url-questoes URL --url-progresso URL [--cookie PHPSESSID=..] [--token TOKEN] [--rotulo-periodo TEXTO]',
  );
  process.exit(1);
}

const outAbs = path.resolve(out);
fs.mkdirSync(path.dirname(outAbs), { recursive: true });

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

  await page.setRequestInterception(true);
  page.on('request', (req) => {
    const u = req.url();
    if (/googletagmanager|google-analytics|gtag\/js|facebook\.net|hotjar/i.test(u)) {
      req.abort().catch(() => {});
      return;
    }
    req.continue().catch(() => {});
  });

  return page;
}

const browser = await launchBrowser();
try {
  const page = await preparePage(browser);
  const extracted = {
    desempenho: await extractDesempenho(page, urls),
    aluno: await extractAluno(page, urls),
    horas: await extractHoras(page, urls),
    questoes: await extractQuestoes(page, urls),
    progresso: await extractProgresso(page, urls),
  };

  const missing = [];
  if (!extracted.desempenho.metrics || (!extracted.desempenho.header && !extracted.desempenho.nome)) {
    missing.push('desempenho');
  }
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
  await page.setContent(html, { waitUntil: 'load', timeout: 60000 });
  await sleep(500);
  await page.pdf({
    path: outAbs,
    format: 'A4',
    printBackground: true,
    displayHeaderFooter: true,
    headerTemplate: `<div style="font-family:Inter,'DejaVu Sans',Helvetica,sans-serif;font-size:8px;width:100%;padding:8px 16mm 8px;color:#001D3D;display:flex;justify-content:space-between;align-items:center;border-bottom:0.6px solid #BF8F00;box-sizing:border-box;">
      <span style="font-weight:700;">MISSÃO NOMEAÇÃO</span>
      <span style="color:#4B5563;display:flex;align-items:center;">${headerPeriodoHtml(rotuloPeriodo)}</span>
    </div>`,
    footerTemplate: `<div style="font-family:Inter,'DejaVu Sans',Helvetica,sans-serif;font-size:7.5px;width:100%;padding:0 16mm;color:#4B5563;text-align:right;">
      Página <span class="pageNumber"></span> de <span class="totalPages"></span>
    </div>`,
    margin: { top: '34mm', right: '16mm', bottom: '18mm', left: '16mm' },
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

