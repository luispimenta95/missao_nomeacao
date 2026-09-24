import { funcoesDatasPagina } from './datas-pagina.mjs';
import { avaliarNaPagina } from '../avaliar-pagina.mjs';
import { labelHoursOnChartVertices, stripPercentFromHoursCharts } from './graficos-pagina.mjs';

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms));
}

async function abrirSessao(browser, { url, cookieHeader, token, downloadDir }) {
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
  return page;
}

async function esperarModelo(page, model) {
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

      function graficosDoPanorama() {
        return chartReady('chart_progresso_principal', 1)
          && chartReady('chart_progresso_modalidades', 4)
          && chartReady('chart_top_disciplinas', 1)
          && chartReady('chart_pizza_modalidades', 1)
          && chartReady('chart_horas_diarias', 7)
          && chartReady('chart_tx_acerto', 2);
      }
      function graficosDasQuestoes() {
        return chartReady('chart_bar_questoes_disciplina', 1)
          && chartReady('chart_pizza_questoes', 1)
          && chartReady('chart_linha_evolucao_questoes', 2)
          && chartReady('chart_progresso_estudo', 1)
          && chartReady('chart_progresso_resumo', 1)
          && chartReady('chart_progresso_revisao', 1)
          && chartReady('chart_progresso_exercicio', 1);
      }
      const nums = document.querySelectorAll('.row-numbers h5').length;
      const questions = document.querySelectorAll('.row-questions .col-6, .row-questions [class*="col-"]').length;
      return nums >= 3 && questions >= 2 && graficosDoPanorama() && graficosDasQuestoes();
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
}

async function fotografarGraficos(page, model) {
  // Datas no padrão brasileiro (DD/MM) antes de congelar os gráficos
  await avaliarNaPagina(page, funcoesDatasPagina);

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
      } catch {}
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
      } catch {}
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
  return { frozen, chartSnapshot };
}

export async function prepararPagina(browser, options) {
  const page = await abrirSessao(browser, options);
  await esperarModelo(page, options.model);
  const { frozen, chartSnapshot } = await fotografarGraficos(page, options.model);
  return { page, frozen, chartSnapshot };
}
