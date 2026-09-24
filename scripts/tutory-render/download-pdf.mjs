import fs from 'node:fs';
import path from 'node:path';

function sleep(ms) {
  return new Promise((r) => setTimeout(r, ms));
}

export function cleanupDownloadDir(downloadDir) {
  try {
    for (const f of fs.readdirSync(downloadDir)) {
      fs.unlinkSync(path.join(downloadDir, f));
    }
    fs.rmdirSync(downloadDir);
  } catch {}
}

export async function waitForDownload(dir, timeoutMs = 120000) {
  const start = Date.now();
  while (Date.now() - start < timeoutMs) {
    const files = fs.readdirSync(dir).filter((f) => f.endsWith('.pdf') && !f.endsWith('.crdownload'));
    if (files.length > 0) {
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
