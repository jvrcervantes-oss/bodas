#!/usr/bin/env node
/* Foto «Fundida» a su proporción (aviso de usuario, 15-oct-2026: en ordenador se cortaba).

   Qué mide: con fotos 3:4, 3:2, 1:1, 9:16 y 2:1, en 1440×900, 1920×1080, 1280×720 y 390×844, CON barras de scroll
   visibles (playwright las oculta por defecto y así un recorte del ~1 % no se ve), que la caja de la foto tenga la
   proporción de la foto (acotada a 0,66–2 como foto_ar() en app/boda.php) y que no haya scroll horizontal. Dos pasadas:
   - «servidor»: el HTML trae --ar y la caja se mide ANTES de que llegue la foto (la foto se retiene): sin salto;
   - «navegador»: el HTML no trae --ar y boda.js la mide al cargar la foto (vista previa sin medida).

   Uso (no va en el gate: necesita Edge y playwright-core; tools/test.py solo corre *_test.php):
     NODE_PATH=<carpeta con playwright-core> node tests/fundida_foto.js
   Salida: 0 = todo bien · 1 = algún caso falla (dice cuál y qué midió) · 2 = no ha podido mirar. */
'use strict';
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

let chromium;
try { ({ chromium } = require('playwright-core')); } catch (e) { console.error('NO HE PODIDO MIRAR: falta playwright-core (NODE_PATH)'); process.exit(2); }
const EDGE = [process.env.ARNES_EDGE || '', 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', 'C:/Program Files/Microsoft/Edge/Application/msedge.exe',
  '/usr/bin/microsoft-edge', '/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge'].find((f) => f && fs.existsSync(f));
if (!EDGE) { console.error('NO HE PODIDO MIRAR: no encuentro Edge'); process.exit(2); }

const RAIZ = path.dirname(__dirname);
const TIPOS = { css: 'text/css', js: 'application/javascript', woff2: 'font/woff2', webp: 'image/webp', svg: 'image/svg+xml', png: 'image/png' };
const FOTOS = { '3:4': [900, 1200], '3:2': [1400, 933], '1:1': [1000, 1000], '9:16': [720, 1280], '2:1': [1600, 800] };
const PANTALLAS = [[1440, 900], [1920, 1080], [1280, 720], [390, 844]];
const acota = (r) => Math.min(2, Math.max(0.66, r));
const svg = ([w, h]) => `<svg xmlns="http://www.w3.org/2000/svg" width="${w}" height="${h}"><rect width="${w}" height="${h}" fill="#c98"/></svg>`;

function html(ar) {
  try { return execFileSync('php', [path.join(__dirname, 'fundida_paginas.php'), ar], { cwd: RAIZ, maxBuffer: 64 << 20 }).toString('utf8'); }
  catch (e) { console.error('NO HE PODIDO MIRAR: tests/fundida_paginas.php falla: ' + e.message); process.exit(2); }
}

(async () => {
  const b = await chromium.launch({ executablePath: EDGE, ignoreDefaultArgs: ['--hide-scrollbars'] });
  let fallos = 0;
  for (const pasada of ['servidor', 'navegador']) {
    for (const [nom, wh] of Object.entries(FOTOS)) {
      const esperado = acota(wh[0] / wh[1]);
      const pagina = html(pasada === 'servidor' ? esperado.toFixed(4) : '');
      for (const [vw, vh] of PANTALLAS) {
        const ctx = await b.newContext({ viewport: { width: vw, height: vh } });
        const p = await ctx.newPage();
        let suelta = null;
        const retenida = new Promise((r) => { suelta = r; });
        await p.route('**/*', async (r) => {
          const u = new URL(r.request().url());
          if (u.pathname === '/') return r.fulfill({ body: pagina, contentType: 'text/html' });
          if (u.pathname === '/foto') { if (pasada === 'servidor') await retenida; return r.fulfill({ body: svg(wh), contentType: 'image/svg+xml' }); }
          const f = path.join(RAIZ, u.pathname);
          if (fs.existsSync(f) && fs.statSync(f).isFile()) return r.fulfill({ body: fs.readFileSync(f), contentType: TIPOS[path.extname(f).slice(1)] || 'application/octet-stream' });
          return r.fulfill({ status: 404, body: '' });
        });
        await p.goto('http://boda-ejemplo.test/', { waitUntil: pasada === 'servidor' ? 'domcontentloaded' : 'load' });
        await p.waitForTimeout(300);
        const m = await p.evaluate(() => {
          const ph = document.querySelector('.mf-fundida .mat-photo').getBoundingClientRect();
          return { w: ph.width, h: ph.height, scrollX: document.documentElement.scrollWidth - document.documentElement.clientWidth, barra: innerWidth - document.documentElement.clientWidth };
        });
        if (suelta) suelta();
        const desvio = Math.abs(m.w / m.h - esperado) / esperado;
        const ok = desvio < 0.005 && m.scrollX <= 0;
        if (!ok) fallos++;
        console.log(`${ok ? 'OK ' : 'MAL'} ${pasada.padEnd(9)} ${nom.padEnd(5)} ${vw}x${vh}  caja ${m.w.toFixed(0)}x${m.h.toFixed(0)}  desvío ${(desvio * 100).toFixed(2)} %  scrollX ${m.scrollX}  barra ${m.barra}px`);
        await ctx.close();
      }
    }
  }
  await b.close();
  process.exit(fallos ? 1 : 0);
})();
