#!/usr/bin/env node
/* Tarjetas y pantallas de la Colección Atelier para la landing (owner, 10-oct-2026: «Mesa del atelier», del artifact
   «Atelier papelería BodaEnlace», https://claude.ai/artifact/DtFXcxyAqmVUyGDiTgd56m).

   Por qué existe: la sección Atelier de la landing enseña cada diseño como una invitación de papel sobre una mesa de lino
   y, al lado, la web en un móvil. Las dos imágenes salen de la plantilla real (render_pagina, vía tests/estilos_paginas.php),
   no de una copia a mano de boda.css: si un diseño cambia, se vuelve a correr esto y la landing queda al día.

   Qué escribe, por diseño, con la boda de muestra de siempre (la de las muestra-*.webp: Lucía & Marcos, 12-06-2027,
   Iglesia de Santa Ana, Sevilla; Cítricos: «Nos casamos» y Valencia), sin hashtag:
     · tarjeta-<k>.webp: la portada (.hero-left) en el ordenador (1440 px, donde los nombres parten en dos líneas y la
       tarjeta sale vertical; a 390 caben en una y sale apaisada), a 2x, sin botones, enlaces ni cuenta atrás (son de la
       web, no de la invitación). Lacre, Cerámica, Herbario y Masía con transparencia (el borde rasgado necesita el alfa);
       Atardecer con 16 px de su acuarela alrededor (la acuarela es el fondo de la página, no de la tarjeta); Cítricos,
       que no tiene tarjeta, a 390 px (en el ordenador los limones quedan lejos del texto), con su papel y la rama de
       limones, de la portada hasta 48 px por debajo del último texto.
     · movil-<k>.webp: la pantalla de un móvil de 390 × 802 (9:18,5) al abrir la web, con su cabecera y su barra. Sin
       foto de la pareja (no hay una de verdad que enseñar, y la de los arneses es una captura de la web): portada sola.
   Las muestra-*.webp no se tocan: las usan el constructor (crear.js) y los arneses como foto fija.
   Comprueba que en la tarjeta no queda ningún enlace ni botón visible: si un diseño cambia el nombre de los suyos, para.

   Uso (no va en el gate: necesita Edge y playwright-core; tools/test.py solo corre *_test.php):
     NODE_PATH=<carpeta con playwright-core> node tests/tarjetas_atelier.js [--diseno <clave>] [--salida <dir>]
   Por defecto escribe en assets/img/atelier/. Salida: 0 = hecho · 2 = no ha podido. */
'use strict';
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

let chromium;
try { ({ chromium } = require('playwright-core')); } catch (e) { console.error('NO HE PODIDO: falta playwright-core (NODE_PATH)'); process.exit(2); }
const EDGE = [process.env.ARNES_EDGE, 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', 'C:/Program Files/Microsoft/Edge/Application/msedge.exe',
  '/usr/bin/microsoft-edge', '/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge'].find((f) => f && fs.existsSync(f));
if (!EDGE) { console.error('NO HE PODIDO: no encuentro Edge'); process.exit(2); }

const arg = (k, d) => { const i = process.argv.indexOf(k); return i > 0 ? process.argv[i + 1] : d; };
const RAIZ = path.resolve(__dirname, '..');
const SOLO = arg('--diseno', null);
const SALIDA = path.resolve(arg('--salida', path.join(RAIZ, 'assets/img/atelier')));
const PHP = process.env.ARNES_PHP || 'php';
const ORIGEN = 'http://boda-ejemplo.test';
const T0 = new Date('2026-10-01T10:00:00+02:00');
const FOTO = fs.readFileSync(path.join(RAIZ, 'assets/img/atelier/muestra-lacre.webp'));   // queda por debajo de lo que se ve
const TIPOS = { css: 'text/css', js: 'application/javascript', woff2: 'font/woff2', webp: 'image/webp', svg: 'image/svg+xml',
  png: 'image/png', jpg: 'image/jpeg', jpeg: 'image/jpeg', mp4: 'video/mp4', ico: 'image/x-icon' };
class NoPuedo extends Error {}

// La boda de muestra. Cítricos lleva su propio saludo y su ciudad, como en su muestra.
const MUESTRA = { fecha: '2027-06-12', ciudad: 'Sevilla', ceremonia: { lugar: 'Iglesia de Santa Ana' }, portada: { hashtag: '' } };
const MUESTRA_CITRICOS = { fecha: '2027-06-12', ciudad: 'Valencia', ceremonia: { lugar: 'Iglesia de Santa Ana' }, portada: { hashtag: '', invitacion: 'Nos casamos' } };
// Cómo se recorta cada tarjeta (ver cabecera)
const MODO = { lacre: ['alfa', 1440], ceramica: ['alfa', 1440], herbario: ['alfa', 1440], masia: ['alfa', 1440], atardecer: ['acuarela', 1440],
  citricos: ['papel', 390] };
const ANCHO_TARJETA = 520;   // px del webp: la tarjeta se ve a 248 px CSS como mucho (2x)
const ANCHO_MOVIL = 360;     // px del webp: la pantalla se ve a 152 px CSS como mucho
// Lo que en la portada es web y no invitación: botones, enlaces, la etiqueta de Herbario y la cuenta atrás de Cítricos
const SIN_WEB = `.cit-nav, .tal-nav, .herb-label, .herb-btn, .lac-btn, .lac-nav, .mas-btn, .atd-btn, .atd-nav, .cit-cuenta { display: none !important; }
  .site-nav, .tab-bar { visibility: hidden !important; }`;
const SOLO_TARJETA = `html, body { background: transparent !important; }
  body :not(.hero-left):not(.hero-left *) { visibility: hidden !important; }
  .hero-left { visibility: visible !important; }`;

const genera = (datos) => JSON.parse(execFileSync(PHP, [path.join(__dirname, 'estilos_paginas.php'), '--nombres', 'Lucía|Marcos', '--datos', JSON.stringify(datos)],
  { cwd: RAIZ, maxBuffer: 64 << 20 }).toString('utf8'));

function sirve(route, paginas, errores) {
  const u = new URL(route.request().url());
  if (u.origin !== ORIGEN) { errores.push('petición a otro origen: ' + u.href); return route.abort(); }
  const p = decodeURIComponent(u.pathname);
  if (p.startsWith('/assets/')) {
    const f = path.join(RAIZ, p);
    if (f.startsWith(path.join(RAIZ, 'assets') + path.sep) && fs.existsSync(f) && fs.statSync(f).isFile()) {
      return route.fulfill({ status: 200, contentType: TIPOS[path.extname(f).slice(1).toLowerCase()] || 'application/octet-stream', body: fs.readFileSync(f) });
    }
    errores.push('asset que no existe: ' + p);
    return route.fulfill({ status: 404, body: '' });
  }
  if (p === '/foto') return route.fulfill({ status: 200, contentType: 'image/webp', body: FOTO });
  if (p === '/favicon.ico') return route.fulfill({ status: 404, body: '' });
  const ruta = p.slice(1);
  if (Object.prototype.hasOwnProperty.call(paginas, ruta)) return route.fulfill({ status: 200, contentType: 'text/html; charset=utf-8', body: paginas[ruta] });
  errores.push('petición inesperada: ' + u.pathname);
  return route.fulfill({ status: 404, body: '' });
}

async function abre(browser, paginas, ancho, alto, css) {
  const errores = [];
  const ctx = await browser.newContext({ viewport: { width: ancho, height: alto }, deviceScaleFactor: 2, locale: 'es-ES', timezoneId: 'Europe/Madrid',
    reducedMotion: 'reduce', colorScheme: 'light' });
  await ctx.route('**/*', (r) => sirve(r, paginas, errores));
  await ctx.clock.install({ time: T0 });
  await ctx.clock.pauseAt(T0);
  const page = await ctx.newPage();
  await page.goto(ORIGEN + '/', { waitUntil: 'load' });
  if (css) await page.addStyleTag({ content: css });
  for (let i = 0; ; i++) {
    const r = await page.evaluate(async () => {
      await document.fonts.ready;
      return { f: [...document.fonts].filter((f) => f.status === 'loading').length, i: [...document.images].filter((im) => !im.complete).length,
        malas: [...document.fonts].filter((f) => f.status === 'error').map((f) => f.family) };
    });
    if (r.malas.length) throw new NoPuedo('fuentes que no cargan: ' + r.malas.join(', '));
    if (!r.f && !r.i) break;
    if (i > 100) throw new NoPuedo('fuentes o imágenes sin cargar');
    await page.waitForTimeout(50);
  }
  if (errores.length) throw new NoPuedo(errores.slice(0, 3).join(' | '));
  return { ctx, page };
}

// PNG → WebP del ancho pedido, con el propio navegador (sin dependencias): canvas.toDataURL conserva el alfa
async function webp(browser, png, ancho, calidad) {
  const ctx = await browser.newContext();
  try {
    const page = await ctx.newPage();
    const b64 = await page.evaluate(async ({ src, ancho, calidad }) => {
      const im = new Image(); im.src = src; await im.decode();
      const alto = Math.round(im.naturalHeight * ancho / im.naturalWidth);
      const c = document.createElement('canvas'); c.width = ancho; c.height = alto;
      const g = c.getContext('2d'); g.imageSmoothingQuality = 'high'; g.drawImage(im, 0, 0, ancho, alto);
      return c.toDataURL('image/webp', calidad).split(',')[1];
    }, { src: 'data:image/png;base64,' + png.toString('base64'), ancho, calidad });
    return Buffer.from(b64, 'base64');
  } finally { await ctx.close(); }
}

async function tarjeta(browser, clave, paginas) {
  if (!MODO[clave]) throw new NoPuedo(clave + ': diseño sin modo de recorte en MODO');
  const [modo, ancho] = MODO[clave];
  const { ctx, page } = await abre(browser, paginas, ancho, ancho > 900 ? 900 : 844, SIN_WEB + (modo === 'alfa' ? SOLO_TARJETA : ''));
  try {
    const m = await page.evaluate(() => {
      const hl = document.querySelector('.hero-left');
      if (!hl) return null;
      const se = (e) => e.getClientRects().length && getComputedStyle(e).visibility !== 'hidden';
      const r = hl.getBoundingClientRect();
      const textos = [...hl.children].filter((e) => se(e) && !e.classList.contains('atelier-arte')).map((e) => e.getBoundingClientRect().bottom);
      const n = getComputedStyle(document.querySelector('.hero-names'));
      return { x: r.x + scrollX, y: r.y + scrollY, w: r.width, h: r.height, fin: Math.max(...textos) + scrollY,
        web: [...hl.querySelectorAll('a, button, input, select')].filter(se).map((e) => e.outerHTML.slice(0, 90)),
        nombres: { familia: n.fontFamily, estilo: n.fontStyle, color: n.color } };
    });
    if (!m) throw new NoPuedo(clave + ': la portada no tiene .hero-left');
    if (m.web.length) throw new NoPuedo(clave + ': quedan enlaces o botones en la tarjeta (¿clase nueva? añádela a SIN_WEB): ' + m.web.join(' | '));
    const clip = modo === 'alfa' ? { x: m.x - 8, y: m.y - 8, width: m.w + 16, height: m.h + 16 }
      : modo === 'acuarela' ? { x: m.x - 16, y: m.y - 16, width: m.w + 32, height: m.h + 32 }
      : { x: m.x, y: m.y, width: m.w, height: m.fin + 48 - m.y };
    clip.x = Math.max(0, clip.x); clip.y = Math.max(0, clip.y);
    clip.width = Math.min(clip.width, ancho - clip.x);
    const png = await page.screenshot({ type: 'png', clip, fullPage: true, omitBackground: modo === 'alfa' });
    const out = await webp(browser, png, ANCHO_TARJETA, 0.86);
    fs.writeFileSync(path.join(SALIDA, 'tarjeta-' + clave + '.webp'), out);
    return { clip: [clip.width, clip.height].map(Math.round).join('×'), bytes: out.length, nombres: m.nombres };
  } finally { await ctx.close(); }
}

async function movil(browser, clave, paginas) {
  const { ctx, page } = await abre(browser, paginas, 390, 802, '');
  try {
    const png = await page.screenshot({ type: 'png' });
    const out = await webp(browser, png, ANCHO_MOVIL, 0.82);
    fs.writeFileSync(path.join(SALIDA, 'movil-' + clave + '.webp'), out);
    return { bytes: out.length };
  } finally { await ctx.close(); }
}

(async () => {
  let gen, genCit, genMovil, genMovilCit;
  try {
    gen = genera(MUESTRA); genCit = genera(MUESTRA_CITRICOS);
    genMovil = genera({ ...MUESTRA, foto: false }); genMovilCit = genera({ ...MUESTRA_CITRICOS, foto: false });
  }
  catch (e) { throw new NoPuedo('tests/estilos_paginas.php ha fallado: ' + (e.stderr || e.message)); }
  const claves = Object.keys(gen.disenos).filter((k) => !SOLO || k === SOLO);
  if (!claves.length) throw new NoPuedo('no hay diseño ' + SOLO);
  fs.mkdirSync(SALIDA, { recursive: true });
  const browser = await chromium.launch({ executablePath: EDGE, headless: true, args: ['--font-render-hinting=none', '--disable-lcd-text', '--hide-scrollbars'] });
  try {
    for (const k of claves) {
      const t = await tarjeta(browser, k, (k === 'citricos' ? genCit : gen).disenos[k].paginas);
      const mv = await movil(browser, k, (k === 'citricos' ? genMovilCit : genMovil).disenos[k].paginas);
      console.log(k.padEnd(10) + ' tarjeta ' + t.clip + ' → ' + (t.bytes / 1024).toFixed(1) + ' KB · móvil ' + (mv.bytes / 1024).toFixed(1)
        + ' KB · nombres: ' + t.nombres.familia + ' ' + t.nombres.estilo + ' ' + t.nombres.color);
    }
  } finally { await browser.close(); }
  console.log('escritas en ' + path.relative(RAIZ, SALIDA).replace(/\\/g, '/'));
})().catch((e) => {
  console.error((e instanceof NoPuedo ? 'NO HE PODIDO: ' : 'ERROR: ') + (e.stack || e.message || e));
  process.exit(2);
});
