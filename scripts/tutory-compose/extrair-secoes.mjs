import { funcoesDatasCompose, funcoesHorasCompose } from './pagina-datas-horas.mjs';
import { avaliarNaPagina } from '../avaliar-pagina.mjs';

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms));
}

export async function gotoReport(page, url) {
  await page.goto(url, { waitUntil: 'domcontentloaded', timeout: 120000 });
  await page.waitForSelector('#btn_save, #btn_download, .report-container, .report-aluno', {
    timeout: 60000,
  });
}

export async function prepareCharts(page) {
  await avaliarNaPagina(page, funcoesDatasCompose);
  await avaliarNaPagina(page, funcoesHorasCompose);
  await page.evaluate(() => {
    if (!window.Chart || !Chart.instances) return;
    for (const k of Object.keys(Chart.instances)) {
      const inst = Chart.instances[k];
      const chart = inst.chart || inst;
      try {
        if (chart.options) chart.options.animation = false;
        if (typeof chart.stop === 'function') chart.stop();
        if (typeof chart.update === 'function') chart.update(0);
      } catch {}
    }
  });
  await sleep(800);
}

export async function extractDesempenho(page, urls) {
  await gotoReport(page, urls.desempenho);
  await page.waitForFunction(() => (
    Boolean(document.querySelector('.main-header-card') && document.querySelector('.metrics-grid'))
  ), { timeout: 120000 });

  const css = await page.evaluate(() => (
    Array.from(document.querySelectorAll('style')).map((s) => s.textContent || '').join('\n')
  ));

  return page.evaluate(() => {
    function htmlOf(sel) {
      const el = document.querySelector(sel);
      return el ? el.outerHTML : '';
    }
    function classifyMetricValues(root) {
      if (!root) return '';
      const clone = root.cloneNode(true);
      clone.querySelectorAll('.metric-value, .main-numbers h3').forEach((el) => {
        const len = (el.textContent || '').trim().length;
        el.classList.add(len <= 3 ? 'kpi-v-a' : len <= 4 ? 'kpi-v-b' : len <= 5 ? 'kpi-v-c' : len <= 6 ? 'kpi-v-d' : 'kpi-v-e');
      });
      return clone.outerHTML;
    }
    return {
      nome: (document.querySelector('.aluno-details h4') || {}).textContent?.trim() || '',
      curso: (document.querySelector('.aluno-details p') || {}).textContent?.trim() || '',
      header: htmlOf('.main-header-card'),
      metrics: classifyMetricValues(document.querySelector('.metrics-grid')) || htmlOf('.metrics-grid'),
    };
  }).then((parts) => ({ ...parts, css }));
}

export async function extractAluno(page, urls) {
  await gotoReport(page, urls.aluno);
  await page.waitForSelector('#tabela_revisoes, h2.section-4, #tabela_estudos, h2.section-5', { timeout: 60000 });
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
        } catch {}
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
    const revisoes = htmlFromHeading('h2.section-4');
    const revisoesRows = document.querySelectorAll('#tabela_revisoes tbody tr').length;
    return { revisoes, revisoesRows };
  });
}

export async function extractHoras(page, urls) {
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
        } catch {}
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
    const tabelaHist = document.getElementById('tabela_horas_liquidas');
    return {
      tempo: htmlFromHeading('h2.section-2'),
      historico: tabelaHist ? tabelaHist.outerHTML : htmlFromHeading('h2.section-4'),
    };
  });
}

export async function extractQuestoes(page, urls) {
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
        } catch {}
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
      wrap.querySelectorAll('.metric-value, .main-numbers h3').forEach((el) => {
        const len = (el.textContent || '').trim().length;
        el.classList.add(len <= 3 ? 'kpi-v-a' : len <= 4 ? 'kpi-v-b' : len <= 5 ? 'kpi-v-c' : len <= 6 ? 'kpi-v-d' : 'kpi-v-e');
      });
      return wrap.innerHTML;
    }
    return {
      panorama: htmlFromHeading('h2.section-1'),
      assuntos: htmlFromHeading('h2.section-4'),
    };
  });
}

export async function extractProgresso(page, urls) {
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
        } catch {}
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
