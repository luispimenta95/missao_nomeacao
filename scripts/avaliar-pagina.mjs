export function avaliarNaPagina(page, fns, ...args) {
  const source = fns.map((fn) => fn.toString()).join('\n');
  const entry = fns[fns.length - 1].name;
  const params = args.map((_, i) => `a${i}`);
  const wrapped = new Function(
    ...params,
    `${source}\nreturn ${entry}(${params.join(', ')});`,
  );
  return page.evaluate(wrapped, ...args);
}
