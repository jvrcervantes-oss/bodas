#!/usr/bin/env node
/* Cuenta atrás con el reloj trucado (owner, 10-oct-2026: «Fichas de papel» de Cítricos; revisor del mismo día).

   Por qué existe: la lógica de initCountdown (assets/js/boda.js) depende de la hora del invitado, y su efecto en la
   maquetación también: en Cítricos las fichas van a caballo entre la portada y la carta, que se sube media ficha; el
   día de la boda sale «¡Hoy es el día!» y al día siguiente el bloque se oculta y la carta tiene que volver a su sitio
   (con el selector de hermano sin :not([hidden]) se subía sobre la foto: lo cazó el revisor, no un test).

   Qué hace: renderiza la boda de ejemplo de Cítricos y la de Lacre (control: cuenta atrás de siempre en «Guardad la
   fecha», sin semanas ni aviso) con el generador real (tests/estilos_paginas.php), la sirve en Edge headless bajo un
   origen falso con el reloj fijado en cada momento, y comprueba cifras, qué se ve y que nada se monta encima de nada,
   en 390 y 1440 px. La boda de ejemplo es el 14-jun-2031 a las 12:30 (tests/fixtures/config_ejemplo_*.json); el
   navegador va en hora de Madrid y los momentos llevan su desfase (+02:00), para no depender de la zona de la máquina.

   Uso (no va en el gate: necesita Edge y playwright-core; tools/test.py solo corre *_test.php):
     NODE_PATH=<carpeta con playwright-core> node tests/cuenta_atras.js
   Salida: 0 = todo bien · 1 = algún caso falla (dice cuál y qué midió) · 2 = no ha podido mirar (sin Edge, sin
   playwright-core, el generador falla). */
'use strict';
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

let chromium;
try { ({ chromium } = require('playwright-core')); } catch (e) { console.error('NO HE PODIDO MIRAR: falta playwright-core (NODE_PATH)'); process.exit(2); }
const EDGE = ['C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe', 'C:/Program Files/Microsoft/Edge/Application/msedge.exe',
  '/usr/bin/microsoft-edge', '/Applications/Microsoft Edge.app/Contents/MacOS/Microsoft Edge'].find((f) => fs.existsSync(f));
if (!EDGE) { console.error('NO HE PODIDO MIRAR: no encuentro Edge'); process.exit(2); }

const RAIZ = path.dirname(__dirname);
const ORIGEN = 'http://boda-ejemplo.test';
const TIPOS = { css: 'text/css', js: 'application/javascript', woff2: 'font/woff2', webp: 'image/webp', svg: 'image/svg+xml', png: 'image/png', jpg: 'image/jpeg' };
let datos;
try {
  datos = JSON.parse(execFileSync('php', [path.join(RAIZ, 'tests/estilos_paginas.php')], { cwd: RAIZ, maxBuffer: 64 << 20 }).toString('utf8'));
} catch (e) { console.error('NO HE PODIDO MIRAR: tests/estilos_paginas.php falla: ' + e.message); process.exit(2); }
const FOTO = fs.readFileSync(path.join(RAIZ, 'assets/img/atelier/muestra-lacre.webp'));

function servidor(paginas) {
  return (route) => {
    const p = decodeURIComponent(new URL(route.request().url()).pathname);
    if (p.startsWith('/assets/')) {
      const f = path.join(RAIZ, p);
      return fs.existsSync(f) ? route.fulfill({ status: 200, contentType: TIPOS[path.extname(f).slice(1)] || 'application/octet-stream', body: fs.readFileSync(f) })
        : route.fulfill({ status: 404, body: '' });
    }
    if (p === '/foto') return route.fulfill({ status: 200, contentType: 'image/webp', body: FOTO });
    const ruta = p.slice(1);
    if (Object.prototype.hasOwnProperty.call(paginas, ruta)) return route.fulfill({ status: 200, contentType: 'text/html; charset=utf-8', body: paginas[ruta] });
    return route.fulfill({ status: 404, body: '' });
  };
}

/* Lo que se mide en la página, en coordenadas de documento */
function medir() {
  const caja = (s) => {
    const e = document.querySelector(s);
    if (!e) return null;
    const b = e.getBoundingClientRect();
    return { top: b.top + scrollY, bot: b.bottom + scrollY, visible: b.height > 0 && getComputedStyle(e).display !== 'none' };
  };
  const L = document.querySelector('.letter');
  const root = document.getElementById('countdown');
  return {
    hero: caja('.hero'), cuenta: caja('.cit-cuenta'), countdown: caja('#countdown'), hoy: caja('[data-c-hoy]'), letter: caja('.letter'),
    padLetter: L ? getComputedStyle(L).paddingTop : '',
    enSaveBand: !!(root && root.closest('.save-band')),
    cifras: root ? [...root.querySelectorAll('[data-c]')].map((b) => b.textContent).join('|') : '',
  };
}

const FESTON = 10;
/* Cifras sin los segundos: cargar la página corre el reloj algún segundo de verdad; los momentos van medio minuto
   después del minuto redondo para que ese segundo no cambie los minutos. */
const sinSeg = (c) => c.split('|').slice(0, -1).join('|');   // el festón de la carta asoma 10 px por encima de su caja (boda.css, .letter::before)
const CASOS = [
  // [diseño, momento, cuándo, comprobaciones(m, ancho) → lista de fallos]
  ['citricos', '13 d 2 h 29 min antes', '2031-06-01T10:00:30+02:00', (m) => [
    sinSeg(m.cifras) !== '01|06|02|29' && `cifras ${m.cifras} (esperaba 01|06|02|29|…: semanas y días sueltos)`,
    !(m.countdown && m.countdown.visible) && 'las fichas no se ven',
    m.hoy && m.hoy.visible && 'el aviso del día se ve antes de tiempo',
    m.cuenta && m.hero && m.cuenta.top < m.hero.bot - 1 && 'las fichas se montan sobre la portada',
    m.cuenta && m.letter && !(m.letter.top - FESTON < m.cuenta.bot && m.letter.top > m.cuenta.top) && 'el festón de la carta no queda bajo las fichas',
  ]],
  ['citricos', '20 d antes', '2031-05-25T12:29:30+02:00', (m) => [
    sinSeg(m.cifras) !== '02|06|00|00' && `cifras ${m.cifras} (esperaba 02|06|00|00|…)`,
  ]],
  ['citricos', 'el día, tras la ceremonia', '2031-06-14T18:00:00+02:00', (m) => [
    m.countdown && m.countdown.visible && 'las fichas siguen a la vista',
    !(m.hoy && m.hoy.visible) && 'no se ve «¡Hoy es el día!»',
    m.hoy && m.hero && m.hoy.top < m.hero.bot - 1 && 'el aviso se monta sobre la portada',
  ]],
  ['citricos', 'el día siguiente', '2031-06-15T10:00:00+02:00', (m, ancho) => [
    m.cuenta && m.cuenta.visible && 'el bloque de la cuenta atrás sigue a la vista',
    m.letter && m.hero && m.letter.top - FESTON < m.hero.bot - 1 && `la carta (festón en ${Math.round(m.letter.top - FESTON)}) se sube sobre la portada (acaba en ${Math.round(m.hero.bot)})`,
    m.padLetter !== (ancho > 900 ? '80px' : '60px') && `la carta conserva el relleno de las fichas (${m.padLetter})`,
  ]],
  ['lacre', '13 d antes (control)', '2031-06-01T10:00:30+02:00', (m) => [
    sinSeg(m.cifras) !== '13|02|29' && `cifras ${m.cifras} (esperaba 13|02|29|…)`,
    !m.enSaveBand && 'la cuenta atrás no está en «Guardad la fecha»',
    (m.cuenta || m.hoy) && 'lleva piezas de Cítricos',
  ]],
  ['lacre', 'pasada la boda (control)', '2031-06-20T10:00:00+02:00', (m) => [
    m.cifras !== '0|0|0|0' && `cifras ${m.cifras} (esperaba 0|0|0|0)`,
  ]],
];

(async () => {
  const browser = await chromium.launch({ executablePath: EDGE, headless: true });
  let fallos = 0;
  for (const ancho of [390, 1440]) {
    for (const [diseno, momento, cuando, comprueba] of CASOS) {
      const d = datos.disenos[diseno];
      if (!d) { console.error('NO HE PODIDO MIRAR: el generador no trae «' + diseno + '»'); process.exit(2); }
      const ctx = await browser.newContext({ viewport: { width: ancho, height: ancho > 900 ? 900 : 844 }, reducedMotion: 'reduce', locale: 'es-ES', timezoneId: 'Europe/Madrid' });
      await ctx.route('**/*', servidor(d.paginas));
      const page = await ctx.newPage();
      const errores = [];
      page.on('pageerror', (e) => errores.push(String(e)));
      await page.clock.install({ time: new Date(cuando) });
      await page.goto(ORIGEN + '/', { waitUntil: 'load' });
      await page.clock.runFor(200);
      await page.evaluate(() => document.fonts.ready);
      const m = await page.evaluate(medir);
      const mal = comprueba(m, ancho).filter(Boolean).concat(errores.map((e) => 'error en la página: ' + e));
      if (mal.length) fallos++;
      console.log((mal.length ? 'FALLA ' : 'ok    ') + `${diseno} · ${ancho} px · ${momento}` + (mal.length ? '\n        ' + mal.join('\n        ') : ''));
      await ctx.close();
    }
  }
  await browser.close();
  console.log(fallos ? `ROJO: ${fallos} caso(s)` : 'VERDE');
  process.exit(fallos ? 1 : 0);
})().catch((e) => { console.error('NO HE PODIDO MIRAR: ' + (e && e.stack || e)); process.exit(2); });
