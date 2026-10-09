#!/usr/bin/env node
/* Red de estilos computados de los seis diseños Atelier (encargo 20261009_bodas_editor_disenos_atelier, S0).

   Por qué existe: el editor de diseños va a cambiar el CSS Atelier de sitio (literales → var(--…), defectos en un
   bloque body.atelier-x, palancas). El snapshot HTML (tests/fixtures/snap_lib.php) no ve boda.css, y el diff del .css
   cambia por definición. Lo que tiene que quedar igual es lo que el navegador PINTA: esto lo mide.

   Qué hace: renderiza la boda de ejemplo de cada diseño (tests/fixtures/config_ejemplo_<diseño>.json) con el
   generador real (tests/estilos_paginas.php, sin servidor ni DATA_DIR), la sirve en Edge headless bajo un origen
   falso (los /assets salen de esta copia del repo) y vuelca getComputedStyle de todos los elementos con caja y sus
   ::before/::after/::placeholder, en 390 y 1440 px, en todas las páginas y en estos estados:
     · inicio: entrada (sobre) cerrada · entrada abierta (tras pulsar el sello) · la web ya revelada;
     · 390 px: además el menú abierto;
     · cada página: cada elemento que tenga una regla :hover/:focus en boda.css, forzado por CDP (primer elemento
       con caja de cada selector), porque ahí viven colores derivados (hover del acento) que también se parametrizan.
   Las propiedades personalizadas (--*) NO se vuelcan: añadir un --at-x no cambia nada visible, y su valor resuelto
   ya aparece en las propiedades reales que lo usan.

   Formato (texto, una línea por elemento): árbol por sangría; cada línea lleva SOLO las propiedades que difieren
   del padre volcado (o del elemento, en un pseudo). Lo que falta es igual al padre; la raíz <html> va entera. Tres
   recortes sin efecto visible, explicados en volcadoEnPagina(): propiedades lógicas, las que siguen a currentcolor
   (se comparan con el color propio) y los *-origin sin transformación. En los estados :hover/:focus solo van las
   líneas que el estado cambia. La línea base va en gzip (~2 MB de texto por diseño); el diff legible lo da el
   script, y el volcado actual en texto queda en --salida.

   Determinismo: reloj congelado (Date y temporizadores falsos, page.clock), zona y idioma fijos, escala 1, sin
   ratón (los clics van por JS y los :hover por CDP), fuentes cargadas e imágenes completas antes de medir, el
   revelado al hacer scroll lo dispara el IntersectionObserver de verdad (scroll por toda la página y vuelta arriba;
   nunca se pone .in a mano), y las animaciones CSS se llevan a un punto fijo (las finitas, al final; las infinitas,
   pausadas en 0). Cualquier petición que no sea del origen falso o que no exista es un error del arnés.

   Uso (no va en el gate: necesita Edge y playwright-core; tools/test.py solo corre *_test.php):
     NODE_PATH=<carpeta con playwright-core> node tests/estilos_atelier.js [--diseno <clave>] [--actualiza] [--salida <dir>]
   Salida: 0 = idéntico a la línea base (tests/fixtures/estilos/<diseño>.txt.gz) · 1 = difiere (enseña las primeras
   diferencias) · 2 = no ha podido mirar (sin Edge, petición inesperada, fuentes sin cargar, otra versión de Edge).
   El volcado actual se escribe siempre en --salida (por defecto <tmp>/bodas_estilos_actual) para poder compararlo.
   --actualiza regraba la línea base: solo desde un código cuyo aspecto se quiere fijar, nunca para hacer pasar. */
'use strict';
const { chromium } = require('playwright-core');
const fs = require('fs');
const os = require('os');
const path = require('path');
const zlib = require('zlib');
const { execFileSync } = require('child_process');

const arg = (k, d) => { const i = process.argv.indexOf(k); return i > 0 ? process.argv[i + 1] : d; };
const RAIZ = path.resolve(__dirname, '..');
const BASE = path.join(__dirname, 'fixtures', 'estilos');
const SALIDA = path.resolve(arg('--salida', path.join(os.tmpdir(), 'bodas_estilos_actual')));
const ACTUALIZA = process.argv.includes('--actualiza');
const SOLO = arg('--diseno', null);
const EDGE = process.env.ARNES_EDGE || 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
const PHP = process.env.ARNES_PHP || 'php';
const ORIGEN = 'http://boda-ejemplo.test';
const T0 = new Date('2026-10-01T10:00:00+02:00');   // la boda de ejemplo es el 14-jun-2031: cuenta atrás positiva y fija
const ANCHOS = [[390, 844], [1440, 900]];
const FOTO = fs.readFileSync(path.join(RAIZ, 'assets/img/atelier/muestra-lacre.webp'));   // foto fija de la pareja
const CANCIONES = { ok: true, canciones: [
  { id: 'c1', cancion: 'Canción de prueba', artista: 'Grupo inventado', votos: 3 },
  { id: 'c2', cancion: 'Otra canción', artista: 'Solista inventada', votos: 1 },
] };
const TIPOS = { css: 'text/css', js: 'application/javascript', woff2: 'font/woff2', webp: 'image/webp', svg: 'image/svg+xml',
  png: 'image/png', jpg: 'image/jpeg', jpeg: 'image/jpeg', mp4: 'video/mp4', ico: 'image/x-icon' };

class NoMira extends Error {}   // salida 2: el arnés no ha podido medir (no es una regresión)
const espera = (ms) => new Promise((r) => setTimeout(r, ms));

/* ---------- servidor falso ---------- */
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
  if (p === '/api/musica' && u.searchParams.get('action') === 'list') return route.fulfill({ status: 200, contentType: 'application/json', body: JSON.stringify(CANCIONES) });
  if (p === '/favicon.ico') return route.fulfill({ status: 404, body: '' });
  const ruta = p.slice(1);
  if (Object.prototype.hasOwnProperty.call(paginas, ruta)) return route.fulfill({ status: 200, contentType: 'text/html; charset=utf-8', body: paginas[ruta] });
  errores.push('petición inesperada: ' + u.pathname + u.search);
  return route.fulfill({ status: 404, body: '' });
}

/* ---------- en la página ---------- */
// Animaciones CSS a un punto fijo. Corren en el reloj real (no en el falso), así que se fijan justo antes de medir.
async function asienta(page) {
  for (let i = 0; i < 3; i++) {
    await page.evaluate(() => {
      for (const a of document.getAnimations()) {
        const t = a.effect && a.effect.getComputedTiming();
        if (!t) continue;
        if (t.endTime === Infinity || t.activeDuration === Infinity) { a.pause(); a.currentTime = 0; } else a.finish();
      }
    });
    await espera(30);
  }
}

// Fuentes cargadas e imágenes completas. Si no llegan, no se mide (salida 2).
async function cargado(page, donde) {
  for (let i = 0; i < 100; i++) {
    const r = await page.evaluate(async () => {
      await document.fonts.ready;
      const fuentes = [...document.fonts].filter((f) => f.status === 'loading').length;
      const malas = [...document.fonts].filter((f) => f.status === 'error').map((f) => f.family);
      const imgs = [...document.images].filter((im) => !im.complete).length;
      return { fuentes, malas, imgs };
    });
    if (r.malas.length) throw new NoMira(donde + ': fuentes que no cargan: ' + r.malas.join(', '));
    if (!r.fuentes && !r.imgs) return;
    await espera(50);
  }
  throw new NoMira(donde + ': fuentes o imágenes sin terminar de cargar');
}

// Scroll por toda la página para que el IntersectionObserver revele lo que tenga que revelar, y vuelta arriba.
async function recorre(page, donde) {
  const alto = await page.evaluate(() => document.documentElement.scrollHeight);
  const paso = Math.max(200, Math.floor(page.viewportSize().height / 3));
  // En cada parada se espera a que el observador haya revelado lo que está a la vista: el IntersectionObserver solo
  // mira en cada fotograma, y con la máquina cargada un scroll rápido se salta elementos que nunca vuelven a verse.
  const pendientesALaVista = () => [...document.querySelectorAll('.rv:not(.in)')].filter((e) => {
    const r = e.getBoundingClientRect();
    if (!r.height || !r.width) return false;
    const vis = Math.min(r.bottom, innerHeight) - Math.max(r.top, 0);
    return vis / r.height >= 0.25 || vis >= innerHeight * 0.5;
  }).length;
  for (let y = 0; y <= alto + paso; y += paso) {
    await page.evaluate((v) => window.scrollTo(0, v), y);
    for (let i = 0; i < 80 && (await page.evaluate(pendientesALaVista)); i++) await espera(25);
  }
  for (let i = 0; i < 60; i++) {
    const faltan = await page.evaluate(() => [...document.querySelectorAll('.rv:not(.in)')].filter((e) => e.getClientRects().length).length);
    if (!faltan) break;
    if (i === 59) throw new NoMira(donde + ': ' + faltan + ' elementos .rv sin revelar tras recorrer la página');
    await espera(50);
  }
  await page.evaluate(() => window.scrollTo(0, 0));
  await espera(50);
}

// Volcado de un subárbol (o del documento entero). Devuelve líneas de texto.
function volcadoEnPagina(sel) {
  const ORIG = location.origin;
  const limpia = (v) => v.split(ORIG).join('').replace(/\?v=[0-9a-f]{8}/g, '?v=X');
  // Sin pérdida y sin ruido:
  //  · las propiedades lógicas (inline-size, margin-block-start, border-inline-end-color…) son, en el valor
  //    computado, una copia de las físicas según writing-mode y direction, que sí se vuelcan: fuera;
  //  · las que por defecto son currentcolor se comparan con el `color` del propio elemento, no con el padre: así
  //    un borde que deja de seguir al color de su texto sale, y uno que lo sigue no repite el color en cada línea.
  const LOGICA = /^(?:(?:min-|max-)?(?:block|inline)-size|(?:margin|padding|inset|scroll-margin|scroll-padding)-(?:block|inline)-(?:start|end)|border-(?:block|inline)-(?:start|end)-(?:color|style|width)|border-(?:start|end)-(?:start|end)-radius|overflow-(?:block|inline)|overscroll-behavior-(?:block|inline)|contain-intrinsic-(?:block|inline)-size)$/;
  const SIGUE_COLOR = new Set(['border-top-color', 'border-right-color', 'border-bottom-color', 'border-left-color', 'outline-color',
    'text-decoration-color', 'text-emphasis-color', '-webkit-text-fill-color', '-webkit-text-stroke-color', 'column-rule-color', 'row-rule-color', 'caret-color']);
  const mapa = (cs) => { const m = {}; for (let i = 0; i < cs.length; i++) { const p = cs[i]; if (!p.startsWith('--') && !LOGICA.test(p)) m[p] = limpia(cs.getPropertyValue(p)); } return m; };
  //  · transform-origin sin ninguna transformación, y perspective-origin sin perspective, no pintan nada (y valen
  //    la mitad del tamaño de cada caja: ruido en cada línea). Si un estado o una animación pone la transformación,
  //    vuelven a salir.
  const SIN_EFECTO = (p, m) => (p === 'transform-origin' && m.transform === 'none' && m.rotate === 'none' && m.scale === 'none'
    && m.translate === 'none' && (m['offset-path'] || 'none') === 'none') || (p === 'perspective-origin' && m.perspective === 'none');
  const difiere = (m, base) => Object.keys(m).sort()
    .filter((p) => !SIN_EFECTO(p, m) && (SIGUE_COLOR.has(p) ? m[p] !== m.color : !base || base[p] !== m[p]))
    .map((p) => p + ':' + m[p]).join(';');
  const indice = (el) => (el.parentElement ? Array.prototype.indexOf.call(el.parentElement.children, el) + 1 : 1);
  const seg = (el) => el.localName + (el.id ? '#' + el.id : '') + (el.classList.length ? '.' + [...el.classList].join('.') : '') + '@' + indice(el);
  const lineas = [];
  function visita(el, base, prof) {
    const cs = getComputedStyle(el);
    if (cs.display !== 'contents' && !el.getClientRects().length) return;   // sin caja: no se pinta (ni sus hijos)
    const m = mapa(cs);
    const sangria = '  '.repeat(prof);
    lineas.push(sangria + seg(el) + ' {' + difiere(m, base) + '}');
    const pseudos = ['::before', '::after'];
    if (el.hasAttribute('placeholder')) pseudos.push('::placeholder');
    for (const ps of pseudos) {
      const pc = getComputedStyle(el, ps);
      if (ps !== '::placeholder' && (pc.content === 'none' || pc.content === 'normal')) continue;
      lineas.push(sangria + '  ' + ps + ' {' + difiere(mapa(pc), m) + '}');
    }
    for (const h of el.children) visita(h, m, prof + 1);
  }
  const raiz = sel ? document.querySelector(sel) : document.documentElement;
  if (!raiz) return null;
  if (raiz === document.documentElement) visita(raiz, null, 0);
  else {
    const camino = [];
    for (let a = raiz.parentElement; a; a = a.parentElement) camino.unshift(seg(a));
    lineas.push('@ ' + camino.join(' > '));
    visita(raiz, mapa(getComputedStyle(raiz.parentElement)), 0);
  }
  return lineas;
}
async function vuelca(page, sel, donde) {
  const l = await page.evaluate(volcadoEnPagina, sel || null);
  if (!l) throw new NoMira(donde + ': no encuentro ' + sel);
  return l;
}

// Elementos con una regla :hover/:focus en boda.css: [selector sin el estado, estados a forzar], uno por elemento.
function objetivosEnPagina() {
  const ESTADO = /:(hover|focus-visible|focus-within|focus)(?![\w-])/g;
  const reglas = [];
  const recoge = (lista) => { for (const r of lista) { if (r.cssRules && !r.selectorText) recoge(r.cssRules); if (r.selectorText) reglas.push(r.selectorText); } };
  for (const h of document.styleSheets) { try { recoge(h.cssRules); } catch (e) { /* MUDO A PROPOSITO: hoja de otro origen; aquí no hay ninguna (todo sale de /assets) */ } }
  const porElemento = new Map();
  const sinMedir = [];   // lo que este arnés no sabe forzar: se avisa, nunca se calla (sería cobertura perdida a ciegas)
  for (const texto of reglas) {
    ESTADO.lastIndex = 0;
    if (!ESTADO.test(texto)) continue;
    if (/\([^)]*,/.test(texto)) { sinMedir.push(texto); continue; }   // :is(a, b) y similares: partir por comas los rompe
    for (const s of texto.split(',')) {
      ESTADO.lastIndex = 0;
      const m = ESTADO.exec(s);
      if (!m || s.includes('::-webkit')) continue;
      // El compuesto que lleva el estado acaba en el siguiente combinador: ese es el elemento a forzar
      let fin = m.index;
      while (fin < s.length && !/[\s>+~]/.test(s[fin])) fin++;
      const base = s.slice(0, fin).replace(ESTADO, '').replace(/::?(before|after|placeholder)$/, '').trim();
      const estados = (s.slice(0, fin).match(ESTADO) || []).map((x) => x.slice(1));
      let el = null;
      // Un «:focus-visible{…}» suelto vale para cualquier cosa enfocable: se prueba en la primera
      const sel = base === '' || base === '*' ? 'a[href], button, input, select, textarea' : base;
      try { el = [...document.querySelectorAll(sel)].find((e) => e.getClientRects().length); } catch (e) { sinMedir.push(s.trim()); continue; }
      if (!el) continue;
      const prev = porElemento.get(el) || new Set();
      for (const e of estados) prev.add(e);
      porElemento.set(el, prev);
    }
  }
  // Un id estable por elemento para encontrarlo desde fuera, en orden del documento
  const todos = [...porElemento.keys()].sort((a, b) => (a.compareDocumentPosition(b) & Node.DOCUMENT_POSITION_FOLLOWING ? -1 : 1));
  return { sinMedir, objetivos: todos.map((el, i) => {
    el.setAttribute('data-estilos-objetivo', String(i));
    return { i, estados: [...porElemento.get(el)].sort() };
  }) };
}
const SIN_MEDIR = new Set();

// Líneas de un subárbol → [camino desde su raíz, propiedades]
function conCamino(lineas) {
  const pila = [];
  return lineas.filter((l) => !l.startsWith('@ ')).map((l) => {
    const prof = (l.length - l.trimStart().length) / 2;
    const cuerpo = l.trimStart();
    const k = cuerpo.indexOf(' {');
    pila.length = prof;
    pila[prof] = cuerpo.slice(0, k);
    return [pila.join(' > '), cuerpo.slice(k + 2, -1)];
  });
}

async function estadosForzados(page, cdp, donde, salida) {
  const { objetivos, sinMedir } = await page.evaluate(objetivosEnPagina);
  for (const x of sinMedir) SIN_MEDIR.add(x);
  const { root } = await cdp.send('DOM.getDocument', { depth: 0 });
  // Grupos: :hover por un lado, :focus/:focus-visible/:focus-within por otro (son interacciones distintas)
  for (const o of objetivos) {
    const grupos = [];
    if (o.estados.includes('hover')) grupos.push(['hover']);
    const foco = o.estados.filter((e) => e.startsWith('focus'));
    if (foco.length) grupos.push(foco.includes('focus-within') ? ['focus-within'] : ['focus', 'focus-visible']);
    const sel = '[data-estilos-objetivo="' + o.i + '"]';
    const { nodeId } = await cdp.send('DOM.querySelector', { nodeId: root.nodeId, selector: sel });
    if (!nodeId) throw new NoMira(donde + ': objetivo ' + sel + ' perdido');
    const lineas0 = await vuelca(page, sel, donde);
    const quieto = conCamino(lineas0);
    const donde0 = (lineas0[0].startsWith('@ ') ? lineas0[0].slice(2) + ' > ' : '') + quieto[0][0];   // camino completo: varios objetivos pueden llamarse igual (a@1)
    for (const g of grupos) {
      await cdp.send('CSS.forcePseudoState', { nodeId, forcedPseudoClasses: g });
      await asienta(page);
      const forzado = conCamino(await vuelca(page, sel, donde));
      await cdp.send('CSS.forcePseudoState', { nodeId, forcedPseudoClasses: [] });
      await asienta(page);
      // Solo lo que el estado cambia (lo demás ya está en «pagina»); si cambia la estructura, el subárbol entero
      const cambia = forzado.length !== quieto.length || forzado.some((x, j) => x[0] !== quieto[j][0])
        ? forzado : forzado.filter((x, j) => x[1] !== quieto[j][1]);
      salida.push('### ' + donde0 + ' :' + g.join(':'), ...cambia.map(([c, cuerpo]) => c + ' {' + cuerpo + '}'));
    }
  }
}

/* ---------- un diseño ---------- */
async function disena(browser, clave, datos) {
  const out = [];
  for (const [ancho, alto] of ANCHOS) {
    const errores = [];
    const ctx = await browser.newContext({ viewport: { width: ancho, height: alto }, deviceScaleFactor: 1, locale: 'es-ES',
      timezoneId: 'Europe/Madrid', reducedMotion: 'no-preference', colorScheme: 'light', hasTouch: false, isMobile: false });
    await ctx.route('**/*', (r) => sirve(r, datos.paginas, errores));
    await ctx.clock.install({ time: T0 });
    await ctx.clock.pauseAt(T0);
    try {
      for (const ruta of datos.rutas) {
        const donde = clave + ' ' + ancho + ' /' + ruta;
        const page = await ctx.newPage();   // pestaña nueva: sessionStorage vacío, la entrada sale
        const cdp = await ctx.newCDPSession(page);
        await cdp.send('DOM.enable'); await cdp.send('CSS.enable');
        await page.goto(ORIGEN + '/' + ruta, { waitUntil: 'load' });
        await cargado(page, donde);
        const cab = (estado) => '## ' + ancho + ' /' + ruta + ' ' + estado;
        if (ruta === '') {
          if (!(await page.$('#entrada'))) throw new NoMira(donde + ': la entrada no sale (¿reducir movimiento?)');
          await asienta(page);
          out.push(cab('entrada-cerrada'), ...(await vuelca(page, '#entrada', donde)));
          await page.evaluate(() => document.querySelector('.en-sello').click());   // por JS: sin ratón, sin :hover que se quede
          await asienta(page);
          out.push(cab('entrada-abierta'), ...(await vuelca(page, '#entrada', donde)));
          await ctx.clock.runFor(5000);   // boda.js: 'sale' a los 2,5-2,9 s y la quita 1,3 s después
          if (await page.$('#entrada')) throw new NoMira(donde + ': la entrada no se ha ido tras abrirla');
        }
        await recorre(page, donde);
        await cargado(page, donde);
        await asienta(page);
        out.push(cab('pagina'), ...(await vuelca(page, null, donde)));
        const forzados = [];
        await estadosForzados(page, cdp, donde, forzados);
        out.push(cab('interaccion'), ...forzados);
        if (ruta === '' && ancho < 900) {
          await page.evaluate(() => document.querySelector('.nav-toggle').click());
          await asienta(page);
          out.push(cab('menu-abierto'), ...(await vuelca(page, '.site-nav', donde)));
        }
        await page.close();
      }
    } finally {
      await ctx.close();
    }
    if (errores.length) throw new NoMira(clave + ' ' + ancho + ': ' + [...new Set(errores)].slice(0, 5).join(' | '));
  }
  return out;
}

/* ---------- comparar ---------- */
function indexa(texto) {
  const m = new Map();
  let sec = '', sub = '';
  const pila = [];
  for (const l of texto.split('\n')) {
    if (l.startsWith('## ')) { sec = l.slice(3); sub = ''; pila.length = 0; continue; }
    if (l.startsWith('### ')) { sub = l.slice(4); pila.length = 0; continue; }
    if (l.startsWith('#') || !l) continue;
    if (l.startsWith('@ ')) { pila.length = 0; continue; }
    const prof = (l.length - l.trimStart().length) / 2;
    const cuerpo = l.trimStart();
    const k = cuerpo.indexOf(' {');
    pila.length = prof;
    pila[prof] = cuerpo.slice(0, k);
    let clave = sec + (sub ? ' ' + sub : '') + ' :: ' + pila.join(' > ');
    while (m.has(clave)) clave += '+';
    m.set(clave, cuerpo.slice(k + 2, -1));
  }
  return m;
}
const props = (s) => new Map((s ? s.split(/;(?=[a-z-]+:)/) : []).map((x) => { const i = x.indexOf(':'); return [x.slice(0, i), x.slice(i + 1)]; }));
function difiere(antes, ahora, max) {
  const a = indexa(antes), b = indexa(ahora);
  const dif = [];
  for (const [k, v] of a) {
    if (!b.has(k)) { dif.push('  - falta ' + k); continue; }
    const w = b.get(k);
    if (v === w) continue;
    const pa = props(v), pb = props(w);
    for (const p of new Set([...pa.keys(), ...pb.keys()])) {
      if (pa.get(p) !== pb.get(p)) dif.push('  ~ ' + k + ' · ' + p + ': ' + (pa.has(p) ? pa.get(p) : '(como el padre)') + ' → ' + (pb.has(p) ? pb.get(p) : '(como el padre)'));
    }
  }
  for (const k of b.keys()) if (!a.has(k)) dif.push('  + sobra ' + k);
  return { n: dif.length, muestra: dif.slice(0, max) };
}

/* ---------- main ---------- */
(async () => {
  const t0 = Date.now();
  if (!fs.existsSync(EDGE)) throw new NoMira('no encuentro Edge en ' + EDGE + ' (ARNES_EDGE)');
  let datos;
  try {
    datos = JSON.parse(execFileSync(PHP, [path.join(__dirname, 'estilos_paginas.php')], { cwd: RAIZ, maxBuffer: 64 << 20 }).toString('utf8'));
  } catch (e) { throw new NoMira('tests/estilos_paginas.php ha fallado: ' + (e.stderr || e.message)); }
  const claves = Object.keys(datos.disenos).filter((k) => !SOLO || k === SOLO);
  if (!claves.length) throw new NoMira('no hay diseño «' + SOLO + '»');
  const pw = require('playwright-core/package.json').version;
  const browser = await chromium.launch({ executablePath: EDGE, headless: true, args: ['--font-render-hinting=none', '--disable-lcd-text', '--hide-scrollbars'] });
  const cabecera = '# Estilos computados de la boda de ejemplo de un diseño Atelier. Generado por tests/estilos_atelier.js: no se edita a mano.\n'
    + '# motor: Edge ' + browser.version() + ' · playwright-core ' + pw + '\n';
  fs.mkdirSync(SALIDA, { recursive: true });
  let fallos = 0;
  try {
    const res = await Promise.all(claves.map((k) => disena(browser, k, datos.disenos[k]).then((l) => [k, l])));
    for (const [k, lineas] of res) {
      const texto = cabecera + lineas.join('\n') + '\n';
      fs.writeFileSync(path.join(SALIDA, k + '.txt'), texto);
      const f = path.join(BASE, k + '.txt.gz');   // ~2 MB de texto por diseño: en gzip (~200 KB); el diff legible lo da este script
      if (ACTUALIZA) {
        fs.mkdirSync(BASE, { recursive: true });
        fs.writeFileSync(f, zlib.gzipSync(Buffer.from(texto, 'utf8'), { level: 9 }));
        console.log('línea base regrabada: ' + k + ' (' + lineas.length + ' líneas, ' + Math.round(texto.length / 1024) + ' KB; '
          + Math.round(fs.statSync(f).size / 1024) + ' KB en gzip)');
        continue;
      }
      if (!fs.existsSync(f)) throw new NoMira('no hay línea base de ' + k + ': node tests/estilos_atelier.js --actualiza desde el código bueno');
      const antes = zlib.gunzipSync(fs.readFileSync(f)).toString('utf8');
      const motorAntes = antes.split('\n')[1], motorAhora = texto.split('\n')[1];
      if (motorAntes !== motorAhora) throw new NoMira('la línea base es de otro motor («' + motorAntes + '» y ahora «' + motorAhora + '»): regrabarla con --actualiza desde el código SIN cambios, nunca desde el que se prueba');
      if (antes === texto) { console.log('igual: ' + k + ' (' + lineas.length + ' líneas)'); continue; }
      fallos++;
      const d = difiere(antes, texto, 25);
      console.log('DIFIERE: ' + k + ' (' + d.n + ' diferencias; volcado actual en ' + path.join(SALIDA, k + '.txt') + ')');
      for (const x of d.muestra) console.log(x);
      if (d.n > d.muestra.length) console.log('  … y ' + (d.n - d.muestra.length) + ' más');
    }
  } finally {
    await browser.close();
  }
  if (SIN_MEDIR.size) console.error('AVISO: ' + SIN_MEDIR.size + ' selector(es) :hover/:focus que el arnés no sabe forzar (no medidos): ' + [...SIN_MEDIR].join(' | '));
  console.log((fallos ? 'ROJO: ' + fallos + ' diseño(s) difieren' : 'VERDE') + ' · ' + claves.length + ' diseño(s) · ' + ((Date.now() - t0) / 1000).toFixed(1) + ' s');
  process.exit(fallos ? 1 : 0);
})().catch((e) => {
  console.error((e instanceof NoMira ? 'NO HE PODIDO MIRAR: ' : 'ERROR DEL ARNÉS: ') + (e.message || e));
  process.exit(2);
});
