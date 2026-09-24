import {
  converterValor,
  pareceAmericano,
  coletarTextos,
  reescreverTextos,
  funcoesDatasPagina,
} from '../tutory-render/datas-pagina.mjs';

function congelarGrafico(chart) {
  try {
    if (chart.options) chart.options.animation = false;
    if (typeof chart.update === 'function') chart.update(0);
  } catch {}
}

function atualizarGraficosCompose(forcar) {
  if (!window.Chart || !Chart.instances) return;
  for (const k of Object.keys(Chart.instances)) {
    const inst = Chart.instances[k];
    const chart = inst.chart || inst;
    if (!chart || !chart.data || !chart.data.labels) continue;
    chart.data.labels = converterValor(chart.data.labels, forcar);
    congelarGrafico(chart);
  }
}

export function aplicarDatasBrasileiras() {
  const jaAplicado = document.documentElement.getAttribute('data-br-dates') === '1';
  document.documentElement.setAttribute('data-br-dates', '1');
  const forcar = pareceAmericano(coletarTextos());
  if (!jaAplicado) reescreverTextos(forcar);
  atualizarGraficosCompose(forcar);
  return { forcar };
}

const ajudantesDeData = funcoesDatasPagina.filter((fn) => fn.name !== 'aplicarDatasBrasileiras');

export const funcoesDatasCompose = [
  ...ajudantesDeData,
  congelarGrafico,
  atualizarGraficosCompose,
  aplicarDatasBrasileiras,
];

export { labelHoursOnChartVertices, funcoesHorasCompose } from './rotulos-horas.mjs';
