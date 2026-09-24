export function swapAmericanDatesInPdf(buf) {
  const original = buf.toString('latin1');
  const pdfLiteral = () => /\((?:\\.|[^\\)])*\)/g;
  const mmdd = () => /(?<!\d[/\-.])\b(\d{1,2})\/(\d{1,2})(?:\/(\d{2,4}))?\b/g;
  const locked = [];
  let s = original.replace(pdfLiteral(), (lit) => {
    const inner = lit.slice(1, -1).replace(/\b(\d{4})[/\-.](\d{1,2})[/\-.](\d{1,2})\b/g, (full, y, m, d) => {
      const ano = parseInt(y, 10);
      const mes = parseInt(m, 10);
      const dia = parseInt(d, 10);
      if (ano < 1900 || ano > 2100 || mes < 1 || mes > 12 || dia < 1 || dia > 31) return full;
      const token = `\x02ISO${locked.length}\x03`;
      locked.push(`${String(dia).padStart(2, '0')}/${String(mes).padStart(2, '0')}/${y}`);
      return token;
    });
    return `(${inner})`;
  });

  const matches = [];
  s.replace(pdfLiteral(), (lit) => {
    const inner = lit.slice(1, -1);
    const re = mmdd();
    let m;
    while ((m = re.exec(inner))) matches.push(m);
    return lit;
  });

  if (matches.length === 0 && locked.length === 0) return buf;

  let americanos = 0;
  let brasileiros = 0;
  const primeiros = [];
  const segundos = [];
  for (const m of matches) {
    const n1 = parseInt(m[1], 10);
    const n2 = parseInt(m[2], 10);
    primeiros.push(n1);
    segundos.push(n2);
    if (n1 <= 12 && n2 > 12) americanos += 1;
    if (n1 > 12 && n2 <= 12) brasileiros += 1;
  }
  const varPrimeiro = new Set(primeiros).size > 1;
  const varSegundo = new Set(segundos).size > 1;
  const forcar = primeiros.length > 0 && (
    americanos > brasileiros
    || (americanos === brasileiros && !varPrimeiro && varSegundo && Math.max(...primeiros) <= 12)
    || (americanos === brasileiros && varPrimeiro && varSegundo && primeiros.length > 1)
  );

  if (forcar) {
    s = s.replace(pdfLiteral(), (lit) => {
      const inner = lit.slice(1, -1).replace(mmdd(), (full, a, b, y) => {
        const n1 = parseInt(a, 10);
        const n2 = parseInt(b, 10);
        if (n1 < 1 || n1 > 31 || n2 < 1 || n2 > 31) return full;
        if (n1 > 12 && n2 <= 12) return full;
        const eAmericano = (n1 <= 12 && n2 > 12) || n1 <= 12;
        if (!eAmericano) return full;
        const dd = String(n2).padStart(2, '0');
        const mm = String(n1).padStart(2, '0');
        return y ? `${dd}/${mm}/${y}` : `${dd}/${mm}`;
      });
      return `(${inner})`;
    });
  }

  for (let i = locked.length - 1; i >= 0; i -= 1) {
    s = s.split(`\x02ISO${i}\x03`).join(locked[i]);
  }
  if (s === original) return buf;
  return Buffer.from(s, 'latin1');
}
