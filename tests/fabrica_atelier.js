#!/usr/bin/env node
/* Medida de la «fábrica» de los seis diseños Atelier (encargo 20261009_bodas_editor_disenos_atelier, S2b).

   Por qué existe: la base corregida (sin sombras, radios 0 o pastilla, peso ≤ 500, oro nunca como texto, contraste,
   suelos de tamaño, una CTA rellena por pantalla, nombres largos en la cabecera) se mide en el navegador, sobre lo que
   de verdad se pinta, no leyendo el CSS. Es también el embrión del gate de «Publicar» del editor (S5).

   Qué mide, en 390 y 1440 px, en todas las páginas de la boda de ejemplo de cada diseño (tests/estilos_paginas.php),
   con «reducir movimiento» (la web ya revelada, sin animaciones):
     · sombras: box-shadow ≠ none, y filter: drop-shadow (también es sombra), en cualquier elemento o ::before/::after;
     · radios: cada esquina 0, o al menos la mitad del lado corto (pastilla o círculo);
     · peso: ningún texto por encima de 500;
     · oro: ningún texto del color del oro del diseño (--gold en Cerámica, Lacre y Atardecer; --oro en Cítricos;
       Herbario y Masía no tienen oro: su ranura «gold» es verde y terracota);
     · contraste: cada línea de texto contra los píxeles REALES de detrás (se oculta el texto, se fotografía la
       pantalla y se muestrea bajo cada línea; vale con fondos de imagen, degradados y transparencias). Se toma el
       PEOR píxel (con 2 px de margen hacia dentro). Umbral 4,5 para todo;
     · tamaños en 390: todo texto ≥ 12 px; «cuerpo» (texto propio de 40 caracteres o más, sin versalitas) ≥ 16 px;
     · una CTA rellena por pantalla: botones o enlaces con fondo sólido distinto del de detrás (o degradado), visibles
       al menos a la mitad, contados en ventanas de scroll cada cuarto de pantalla, en todas las páginas; la barra fija
       cuenta en todas;
     · hover: ningún elemento con regla :hover se desplaza en Y ni gana sombra al forzar el :hover (CDP). Ojo: con
       «reducir movimiento», .rv{transform:none !important} tapa el translateY de las tarjetas; por eso el translateY
       de hover lo vigila además tests/atelier_tokens_test.php leyendo el CSS;
     · estructura (portada): el «&» no comparte línea con el primer nombre cuando los nombres parten; el hero lleva la
       CTA de confirmar (mismo destino que la de la cabecera); body[data-accesos="filetes"] y los accesos e itinerario
       sin fondo, sin radio y con filete inferior; y, informativo, las tallas de letra distintas (ley 3);
     · tokens: tinta/papel y blanco/acento ≥ 4,5, acento/papel ≥ 3, con los valores que resuelve la página;
     · cabecera con nombres largos («María de los Ángeles & Juan Francisco»): .nav-names no se corta
       (scrollWidth ≤ clientWidth) ni pisa el botón del menú, el corazón ni la CTA, en 390 y 1440;
     · cajas concretas del anexo S1: la rama de Cítricos no toca ningún texto y su borde izquierdo no se ve (1440);
       los nombres de Herbario guardan ≥ 40 px hasta el borde del pliego y el contenido de su tarjeta va centrado.
       Cabecera, cajas y «&» se miden con tres parejas: la del fixture, «Lucía & Marcos» (la de S1) y los largos.
   Alcance: la web. La entrada (el sobre, #entrada) no entra en sombras ni radios: es una pieza ilustrada en 3D con
   su propia profundidad (decisión de S2b, la misma para los seis); sí entran sus textos en el suelo de 12 px, que
   se miden aparte con la entrada cerrada. Lo que no se puede medir (una línea tapada en todas las posiciones) se
   lista como «no medible»: nunca se salta en silencio.

   Uso (no va en el gate: necesita Edge y playwright-core; tools/test.py solo corre *_test.php):
     NODE_PATH=<carpeta con playwright-core> node tests/fabrica_atelier.js [--diseno <clave>] [--json <fichero>]
       [--capturas <dir>]   además, capturas de página entera (PNG): <diseño>_390, <diseño>_1440, <diseño>_390_largo
                            con «Lucía & Marcos» / «María de los Ángeles & Juan Francisco», y las dos webs Esencial de
                            control (control_esencial_390…) para comprobar que no cambian ni un píxel.
   Salida: 0 = todo en regla · 1 = hay incumplimientos (listados) · 2 = no ha podido medir. */
'use strict';
const { chromium } = require('playwright-core');
const fs = require('fs');
const path = require('path');
const zlib = require('zlib');
const { execFileSync } = require('child_process');

const arg = (k, d) => { const i = process.argv.indexOf(k); return i > 0 ? process.argv[i + 1] : d; };
const RAIZ = path.resolve(__dirname, '..');
const SOLO = arg('--diseno', null);
const JSON_OUT = arg('--json', null);
const CAPTURAS = arg('--capturas', null);
const EDGE = process.env.ARNES_EDGE || 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe';
const PHP = process.env.ARNES_PHP || 'php';
const ORIGEN = 'http://boda-ejemplo.test';
const T0 = new Date('2026-10-01T10:00:00+02:00');
const ANCHOS = [[390, 844], [1440, 900]];
const LARGO = 'María de los Ángeles|Juan Francisco';
const FOTO = fs.readFileSync(path.join(RAIZ, 'assets/img/atelier/muestra-lacre.webp'));
const ORO = { ceramica: '--gold', lacre: '--gold', atardecer: '--gold', citricos: '--oro' };
const TIPOS = { css: 'text/css', js: 'application/javascript', woff2: 'font/woff2', webp: 'image/webp', svg: 'image/svg+xml',
  png: 'image/png', jpg: 'image/jpeg', jpeg: 'image/jpeg', mp4: 'video/mp4', ico: 'image/x-icon' };
class NoMira extends Error {}
const espera = (ms) => new Promise((r) => setTimeout(r, ms));

/* ---------- color ---------- */
const lin = (c) => { c /= 255; return c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4; };
const lum = ([r, g, b]) => 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b);
const contraste = (a, b) => { const x = lum(a), y = lum(b); return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05); };
const r2 = (x) => Math.round(x * 100) / 100;

/* ---------- PNG (8 bits, RGB/RGBA, sin entrelazar: lo que da Chromium) ---------- */
function png(buf) {
  let p = 8, w = 0, h = 0, tipo = 0;
  const idat = [];
  while (p < buf.length) {
    const len = buf.readUInt32BE(p), t = buf.toString('ascii', p + 4, p + 8), d = buf.subarray(p + 8, p + 8 + len);
    if (t === 'IHDR') { w = d.readUInt32BE(0); h = d.readUInt32BE(4); tipo = d[9]; if (d[8] !== 8 || d[12] !== 0) throw new NoMira('PNG no soportado'); }
    else if (t === 'IDAT') idat.push(d);
    p += 12 + len;
  }
  const bpp = tipo === 6 ? 4 : tipo === 2 ? 3 : 0;
  if (!bpp) throw new NoMira('PNG de tipo ' + tipo);
  const raw = zlib.inflateSync(Buffer.concat(idat));
  const fila = w * bpp, out = Buffer.alloc(fila * h);
  for (let y = 0; y < h; y++) {
    const f = raw[y * (fila + 1)], s = y * (fila + 1) + 1, o = y * fila;
    for (let x = 0; x < fila; x++) {
      const a = x >= bpp ? out[o + x - bpp] : 0, b = y ? out[o - fila + x] : 0, c = x >= bpp && y ? out[o - fila + x - bpp] : 0;
      let v = raw[s + x];
      if (f === 1) v += a; else if (f === 2) v += b; else if (f === 3) v += (a + b) >> 1;
      else if (f === 4) { const pp = a + b - c, pa = Math.abs(pp - a), pb = Math.abs(pp - b), pc = Math.abs(pp - c); v += pa <= pb && pa <= pc ? a : pb <= pc ? b : c; }
      out[o + x] = v & 255;
    }
  }
  return { w, h, px: (x, y) => { const i = (y * w + x) * bpp; return [out[i], out[i + 1], out[i + 2]]; } };
}

/* ---------- servidor falso (como tests/estilos_atelier.js) ---------- */
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
  if (p === '/api/musica') return route.fulfill({ status: 200, contentType: 'application/json', body: '{"ok":true,"canciones":[{"id":"c1","cancion":"Canción de prueba","artista":"Grupo inventado","votos":3}]}' });
  if (p === '/favicon.ico') return route.fulfill({ status: 404, body: '' });
  const ruta = p.slice(1);
  if (Object.prototype.hasOwnProperty.call(paginas, ruta)) return route.fulfill({ status: 200, contentType: 'text/html; charset=utf-8', body: paginas[ruta] });
  errores.push('petición inesperada: ' + u.pathname);
  return route.fulfill({ status: 404, body: '' });
}
async function cargado(page, donde) {
  for (let i = 0; i < 100; i++) {
    const r = await page.evaluate(async () => {
      await document.fonts.ready;
      return { f: [...document.fonts].filter((f) => f.status === 'loading').length, i: [...document.images].filter((im) => !im.complete).length,
        malas: [...document.fonts].filter((f) => f.status === 'error').map((f) => f.family) };
    });
    if (r.malas.length) throw new NoMira(donde + ': fuentes que no cargan: ' + r.malas.join(', '));
    if (!r.f && !r.i) return;
    await espera(50);
  }
  throw new NoMira(donde + ': fuentes o imágenes sin cargar');
}
const genera = (extra) => JSON.parse(execFileSync(PHP, [path.join(__dirname, 'estilos_paginas.php'), ...extra], { cwd: RAIZ, maxBuffer: 64 << 20 }).toString('utf8'));

/* ---------- en la página ---------- */
// Marca cada elemento con texto propio (data-fab) y devuelve lo que se mide sin píxeles.
function inventario(oroVar) {
  const ORIG_W = innerWidth;
  const sel = (el) => { const p = []; for (let e = el; e && e !== document.body && p.length < 4; e = e.parentElement) p.unshift(e.localName + (e.classList.length ? '.' + [...e.classList].slice(0, 2).join('.') : '')); return p.join(' > '); };
  const rgb = (s) => { const m = s.match(/[\d.]+/g); return m ? m.slice(0, 4).map(Number) : [0, 0, 0, 0]; };
  const fueraDeAlcance = (el) => !!el.closest('#entrada, .marca-previa, [hidden]');
  const raiz = getComputedStyle(document.documentElement);
  const v = (n) => raiz.getPropertyValue(n).trim();
  const hexA = (h) => { h = h.replace('#', ''); if (h.length === 3) h = [...h].map((c) => c + c).join(''); return [0, 2, 4].map((i) => parseInt(h.slice(i, i + 2), 16)); };
  const oro = oroVar && v(oroVar) ? hexA(v(oroVar)) : null;
  const res = { textos: [], sombras: [], radios: [], pesos: [], oro: [], dropShadow: [], ctas: [], tokens: {} };
  // Tokens que resuelve la página
  const tok = { papel: v('--sage'), tinta: v('--tinta') || v('--ink'), acento: v('--accent'), acentoHover: v('--accent-hover') };
  res.tokens = tok;
  let n = 0;
  for (const el of document.querySelectorAll('body *')) {
    if (fueraDeAlcance(el)) continue;
    const cs = getComputedStyle(el);
    if (!el.getClientRects().length || cs.visibility === 'hidden') continue;
    for (const ps of [null, '::before', '::after']) {
      const c = ps ? getComputedStyle(el, ps) : cs;
      if (ps && (c.content === 'none' || c.content === 'normal')) continue;
      if (c.boxShadow !== 'none') res.sombras.push(sel(el) + (ps || '') + ' → ' + c.boxShadow);
      if (/drop-shadow/.test(c.filter)) res.dropShadow.push(sel(el) + (ps || '') + ' → ' + c.filter);
      if (!ps) {
        const r = el.getBoundingClientRect();
        const lado = Math.min(r.width, r.height);
        for (const esq of ['borderTopLeftRadius', 'borderTopRightRadius', 'borderBottomRightRadius', 'borderBottomLeftRadius']) {
          const val = c[esq];
          if (val === '0px') continue;
          const nums = val.split(' ').map((x) => x.endsWith('%') ? parseFloat(x) / 100 * (esq.includes('Top') || esq.includes('Bottom') ? r.width : r.height) : parseFloat(x));
          if (Math.min(...nums) + 0.5 >= lado / 2) continue;   // pastilla o círculo
          res.radios.push(sel(el) + ' ' + esq.replace('border', '').replace('Radius', '') + ' ' + val + ' (caja ' + Math.round(r.width) + '×' + Math.round(r.height) + ')');
          break;
        }
      }
    }
    // Texto propio (nodos de texto hijos directos, no vacíos)
    const propios = [...el.childNodes].filter((t) => t.nodeType === 3 && t.textContent.trim());
    if (!propios.length || el.closest('svg, [aria-hidden="true"]')) continue;
    const texto = propios.map((t) => t.textContent).join(' ').replace(/\s+/g, ' ').trim();
    let op = 1;
    for (let e = el; e; e = e.parentElement) op *= parseFloat(getComputedStyle(e).opacity);
    const rects = [];
    for (const t of propios) {
      const rg = document.createRange(); rg.selectNodeContents(t);
      for (const q of rg.getClientRects()) if (q.width > 1 && q.height > 1) rects.push([q.left, q.top + scrollY, q.width, q.height]);
    }
    if (!rects.length) continue;
    const id = String(n++);
    el.setAttribute('data-fab', id);
    const peso = parseInt(cs.fontWeight, 10);
    const color = rgb(cs.color);
    const fijo = !!el.closest('.site-nav, .tab-bar');
    res.textos.push({ id, sel: sel(el), texto: texto.slice(0, 40), tam: parseFloat(cs.fontSize), peso, color, op,
      mayus: cs.textTransform === 'uppercase', largo: texto.length, rects, fijo });
    if (peso > 500) res.pesos.push(sel(el) + ' «' + texto.slice(0, 24) + '» peso ' + peso);
    if (oro && Math.abs(color[0] - oro[0]) + Math.abs(color[1] - oro[1]) + Math.abs(color[2] - oro[2]) < 12) res.oro.push(sel(el) + ' «' + texto.slice(0, 24) + '»');
  }
  // CTAs rellenas: fondo sólido que se distingue del de detrás, o degradado
  const fondoDetras = (el) => {
    for (let e = el.parentElement; e; e = e.parentElement) {
      const c = getComputedStyle(e);
      if (c.backgroundImage !== 'none' && !/gradient/.test(c.backgroundImage)) return null;   // imagen: se compara con el papel
      const b = rgb(c.backgroundColor);
      if ((b[3] ?? 1) > 0.3) return b;
    }
    return null;
  };
  for (const el of document.querySelectorAll('a, button')) {
    if (fueraDeAlcance(el) || !el.getClientRects().length || !el.textContent.trim()) continue;
    const c = getComputedStyle(el);
    const b = rgb(c.backgroundColor);
    const deg = /gradient/.test(c.backgroundImage);
    if (!deg && (b[3] ?? 1) < 0.5) continue;
    const r = el.getBoundingClientRect();
    res.ctas.push({ sel: sel(el), texto: el.textContent.trim().slice(0, 24), fondo: b, deg, detras: fondoDetras(el),
      fijo: !!el.closest('.site-nav, .tab-bar'), rect: [r.left, r.top + scrollY, r.width, r.height] });
  }
  res.ancho = ORIG_W;
  res.alto = document.documentElement.scrollHeight;
  return res;
}

// Líneas visibles y no tapadas en la ventana actual: [id, x, y, w, h] en coordenadas de la ventana
function lineasALaVista(ids) {
  const out = [];
  for (const id of ids) {
    const el = document.querySelector('[data-fab="' + id + '"]');
    if (!el) continue;
    for (const t of el.childNodes) {
      if (t.nodeType !== 3 || !t.textContent.trim()) continue;
      const rg = document.createRange(); rg.selectNodeContents(t);
      for (const q of rg.getClientRects()) {
        if (q.width < 2 || q.height < 2 || q.top < 0 || q.bottom > innerHeight || q.left < 0 || q.right > innerWidth) continue;
        // Sin tapar en el centro ni en las cuatro esquinas (una barra fija puede tapar media línea)
        const puntos = [[q.left + q.width / 2, q.top + q.height / 2], [q.left + 2, q.top + 2], [q.right - 2, q.top + 2], [q.left + 2, q.bottom - 2], [q.right - 2, q.bottom - 2]];
        const libre = puntos.every(([px, py]) => { const e = document.elementFromPoint(px, py); return e && (e === el || el.contains(e) || e.contains(el)); });
        if (!libre) continue;
        out.push([id, q.left, q.top, q.width, q.height]);
      }
    }
  }
  return out;
}

const OCULTA = '*, *::before, *::after{ color: transparent !important; -webkit-text-fill-color: transparent !important; text-shadow: none !important; caret-color: transparent !important; }';

async function contrastes(page, inv, alto) {
  const peor = new Map();   // id → { c, mediana }
  const vh = page.viewportSize().height;
  const ids = inv.textos.map((t) => t.id);
  for (let y = 0; ; y += Math.floor(vh * 0.5)) {
    await page.evaluate((v) => window.scrollTo(0, v), y);
    await espera(30);
    const lineas = await page.evaluate(lineasALaVista, ids);
    const h = await page.addStyleTag({ content: OCULTA });
    const img = png(await page.screenshot({ type: 'png' }));
    await h.evaluate((n) => n.remove());
    for (const [id, x, yy, w, hh] of lineas) {
      const t = inv.textos[+id];
      const a = (t.color[3] ?? 1) * t.op;
      const vals = [];
      const x0 = Math.ceil(x + 2), x1 = Math.floor(x + w - 2), y0 = Math.ceil(yy + 2), y1 = Math.floor(yy + hh - 2);
      if (x1 < x0 || y1 < y0) continue;
      const sx = Math.max(1, Math.floor((x1 - x0) / 24)), sy = Math.max(1, Math.floor((y1 - y0) / 6));
      for (let py = y0; py <= y1; py += sy) for (let px = x0; px <= x1; px += sx) {
        if (px >= img.w || py >= img.h) continue;
        const bg = img.px(px, py);
        const tx = [0, 1, 2].map((i) => bg[i] + a * (t.color[i] - bg[i]));
        vals.push(contraste(tx, bg));
      }
      if (!vals.length) continue;
      vals.sort((p, q) => p - q);
      const r = { c: vals[0], med: vals[vals.length >> 1] };
      const prev = peor.get(id);
      if (!prev || r.c < prev.c) peor.set(id, r);
    }
    if (y + vh >= alto) break;
  }
  await page.evaluate(() => window.scrollTo(0, 0));
  return peor;
}

function ctasPorPantalla(inv, vh) {
  const llenas = inv.ctas.filter((c) => {
    if (c.deg) return true;
    const detras = c.detras || null;
    const papel = inv.tokens.papel;
    const ref = detras ? detras.slice(0, 3) : (papel.startsWith('#') ? [1, 3, 5].map((i) => parseInt(papel.slice(i, i + 2), 16)) : [255, 255, 255]);
    return contraste(c.fondo.slice(0, 3), ref) >= 1.6;
  });
  let max = 0, donde = [];
  for (let y = 0; y < inv.alto; y += Math.floor(vh / 4)) {
    const vistas = llenas.filter((c) => {
      if (c.fijo) return true;
      const [, top, , h] = c.rect;
      const vis = Math.min(top + h, y + vh) - Math.max(top, y);
      return vis >= h / 2;
    });
    if (vistas.length > max) { max = vistas.length; donde = vistas.map((c) => c.sel + ' «' + c.texto + '»'); }
  }
  return { max, donde, llenas: llenas.length };
}

async function hoverDesplaza(page, cdp) {
  // Elementos con regla :hover (como el arnés de S0): se fuerza el :hover y se mira si se mueve en Y o gana sombra
  const objetivos = await page.evaluate(() => {
    const reglas = [];
    const recoge = (l) => { for (const r of l) { if (r.cssRules && !r.selectorText) recoge(r.cssRules); if (r.selectorText) reglas.push(r.selectorText); } };
    for (const h of document.styleSheets) { try { recoge(h.cssRules); } catch (e) { /* MUDO A PROPOSITO: no hay hojas de otro origen (todo sale de /assets) */ } }
    const els = new Set();
    for (const s of reglas.join(',').split(',')) {
      if (!/:hover/.test(s) || /\(/.test(s)) continue;
      const base = s.replace(/:hover.*$/, '').trim();
      if (!base) continue;
      try { for (const e of document.querySelectorAll(base)) if (e.getClientRects().length && !e.closest('#entrada')) els.add(e); } catch (e) { /* MUDO A PROPOSITO: selector que querySelectorAll no entiende; se cuenta abajo */ }
    }
    let i = 0;
    return [...els].map((e) => { e.setAttribute('data-fab-h', String(i)); return i++; });
  });
  const { root } = await cdp.send('DOM.getDocument', { depth: 0 });
  const malos = [];
  for (const i of objetivos) {
    const s = '[data-fab-h="' + i + '"]';
    const { nodeId } = await cdp.send('DOM.querySelector', { nodeId: root.nodeId, selector: s });
    if (!nodeId) continue;
    const antes = await page.evaluate((q) => { const c = getComputedStyle(document.querySelector(q)); return [c.transform, c.translate, c.boxShadow]; }, s);
    await cdp.send('CSS.forcePseudoState', { nodeId, forcedPseudoClasses: ['hover'] });
    const durante = await page.evaluate((q) => { const e = document.querySelector(q); const c = getComputedStyle(e); return [c.transform, c.translate, c.boxShadow, e.localName + '.' + [...e.classList].slice(0, 2).join('.')]; }, s);
    await cdp.send('CSS.forcePseudoState', { nodeId, forcedPseudoClasses: [] });
    const ty = (t) => { const m = t.match(/matrix\(([^)]+)\)/); return m ? parseFloat(m[1].split(',')[5]) : 0; };
    const tyT = (t) => (t && t !== 'none' ? parseFloat(t.split(' ')[1] || '0') : 0);
    if (ty(durante[0]) !== ty(antes[0]) || tyT(durante[1]) !== tyT(antes[1])) malos.push(durante[3] + ' se desplaza en Y al pasar el ratón');
    if (durante[2] !== antes[2] && durante[2] !== 'none') malos.push(durante[3] + ' gana sombra al pasar el ratón');
  }
  return malos;
}

async function cajas(page, clave, ancho) {
  return page.evaluate(([clave, ancho]) => {
    const malos = [];
    const R = (e) => e.getBoundingClientRect();
    const cruza = (a, b) => a.left < b.right && b.left < a.right && a.top < b.bottom && b.top < a.bottom;
    if (clave === 'citricos' && ancho > 900) {
      const esq = document.querySelector('.hero .esq');
      if (esq) {
        const r = R(esq);
        for (const el of document.querySelectorAll('.hero-left [data-fab]')) {
          for (const t of el.childNodes) {
            if (t.nodeType !== 3 || !t.textContent.trim()) continue;
            const rg = document.createRange(); rg.selectNodeContents(t);
            for (const q of rg.getClientRects()) if (cruza(q, r)) malos.push('la rama pisa «' + t.textContent.trim().slice(0, 30) + '»');
          }
        }
        if (r.left > 0.5) malos.push('el borde izquierdo de la rama se ve (left ' + Math.round(r.left) + ' px)');
      }
    }
    if (clave === 'herbario') {
      const hl = document.querySelector('.hero-left'), n = document.querySelector('.hero-names');
      if (hl && n) {
        const a = R(hl);
        const rg = document.createRange(); rg.selectNodeContents(n);
        for (const q of rg.getClientRects()) {
          const m = Math.min(q.left - a.left, a.right - q.right);
          if (m < 40) malos.push('los nombres quedan a ' + Math.round(m) + ' px del borde del pliego');
        }
        const cx = a.left + a.width / 2;
        for (const s of ['.hero-names', '.herb-lugar', '.herb-label', '.herb-btn', '.hero-date .kicker']) {
          const e = document.querySelector(s);
          if (!e) continue;
          let q = R(e);
          if (getComputedStyle(e).display === 'block' && e.firstChild) { const rg2 = document.createRange(); rg2.selectNodeContents(e); q = rg2.getBoundingClientRect(); }
          if (Math.abs(q.left + q.width / 2 - cx) > 3) malos.push(s + ' no va centrado en el pliego (' + Math.round(q.left + q.width / 2 - cx) + ' px)');
        }
      }
    }
    return malos;
  }, [clave, ancho]);
}

// Lo que el criterio de S2b pide y no es un estilo suelto: «&» aparte del primer nombre, la CTA de confirmar en el hero,
// accesos en filetes (sin tarjeta) y cuántas tallas de letra distintas hay (ley 3: escala con saltos, no escalera).
async function estructura(page, ancho) {
  return page.evaluate((ancho) => {
    const malos = [];
    // «&» (o «y») separado del primer nombre: si los nombres ocupan más de una línea, el conector no comparte línea
    // con la última letra del primer nombre
    const h = document.querySelector('.hero-names');
    if (h) {
      const letras = [];
      const recorre = (n) => { for (const c of n.childNodes) { if (c.nodeType === 3) { for (let i = 0; i < c.textContent.length; i++) letras.push([c, i]); } else recorre(c); } };
      recorre(h);
      const rect = ([n, i]) => { const r = document.createRange(); r.setStart(n, i); r.setEnd(n, i + 1); return r.getBoundingClientRect(); };
      const txt = letras.map(([n, i]) => n.textContent[i]).join('');
      const m = txt.match(/\s(&|y)\s/);
      if (m) {
        const iAmp = m.index + 1;
        let iFin = m.index; while (iFin > 0 && /\s/.test(txt[iFin])) iFin--;
        const tops = new Set(letras.filter(([n, i]) => !/\s/.test(n.textContent[i])).map((l) => Math.round(rect(l).top / 4)));
        const a = rect(letras[iAmp]), f = rect(letras[iFin]);
        if (tops.size > 1 && Math.abs(a.top - f.top) < a.height / 2) malos.push('el «' + m[1] + '» va pegado al primer nombre');
      }
    }
    // CTA de confirmar en el hero: el mismo destino que el botón de confirmar de la cabecera
    const cta = document.querySelector('.nav-bar .nav-cta');
    const destino = cta && cta.getAttribute('href');
    if (destino && !document.querySelector('.hero a[href="' + destino + '"]')) malos.push('el hero no lleva la CTA de confirmar');
    // Accesos en filetes: tarjetas de accesos y de itinerario sin fondo, sin radio y con filete inferior
    const filetes = document.body.getAttribute('data-accesos') === 'filetes';
    let tarjetas = 0;
    for (const el of document.querySelectorAll('.quick .q-card:not(.q-rsvp), .event-card')) {
      const c = getComputedStyle(el);
      const fondo = c.backgroundColor.match(/[\d.]+/g) || [];
      const conFondo = (fondo.length === 4 ? +fondo[3] : 1) > 0.05 && c.backgroundColor !== 'transparent';
      if (conFondo || parseFloat(c.borderTopLeftRadius) > 0 || parseFloat(c.borderBottomWidth) < 1) tarjetas++;
    }
    if (!filetes) malos.push('body sin data-accesos="filetes"');
    if (tarjetas) malos.push(tarjetas + ' accesos siguen siendo tarjetas (fondo, radio o sin filete)');
    // Tallas distintas en la portada (solo informativo: la escala la mira el ojo, no un umbral)
    const tallas = new Set();
    for (const el of document.querySelectorAll('body *')) {
      if (el.closest('#entrada, svg, [aria-hidden="true"]') || !el.getClientRects().length) continue;
      if (![...el.childNodes].some((t) => t.nodeType === 3 && t.textContent.trim())) continue;
      tallas.add(Math.round(parseFloat(getComputedStyle(el).fontSize) * 2) / 2);
    }
    return { malos, tallas: [...tallas].sort((a, b) => a - b) };
  }, ancho);
}

async function cabecera(page) {
  return page.evaluate(() => {
    const n = document.querySelector('.nav-bar .nav-names');
    if (!n) return ['no hay .nav-names'];
    const malos = [];
    const r = n.getBoundingClientRect();
    if (n.scrollWidth > n.clientWidth + 1) malos.push('nombres recortados (' + n.scrollWidth + ' > ' + n.clientWidth + ' px)');
    if (r.left < 0 || r.right > innerWidth) malos.push('nombres fuera de la pantalla');
    for (const s of ['.nav-toggle', '.nav-heart', '.nav-cta']) {
      const o = document.querySelector('.nav-bar ' + s);
      if (!o || !o.getClientRects().length || getComputedStyle(o).display === 'none') continue;
      const q = o.getBoundingClientRect();
      if (r.left < q.right && q.left < r.right && r.top < q.bottom && q.top < r.bottom) malos.push('los nombres pisan ' + s);
    }
    const fs = parseFloat(getComputedStyle(n).fontSize);
    if (fs < 12) malos.push('nombres a ' + fs + ' px');
    const brand = n.closest('.nav-brand');
    if (brand && brand.scrollWidth > brand.clientWidth + 1) malos.push('la marca desborda (' + brand.scrollWidth + ' > ' + brand.clientWidth + ')');
    return malos;
  });
}

async function nuevaPagina(browser, ancho, alto, paginas) {
  const errores = [];
  const ctx = await browser.newContext({ viewport: { width: ancho, height: alto }, deviceScaleFactor: 1, locale: 'es-ES', timezoneId: 'Europe/Madrid',
    reducedMotion: 'reduce', colorScheme: 'light' });
  await ctx.route('**/*', (r) => sirve(r, paginas, errores));
  await ctx.clock.install({ time: T0 });
  await ctx.clock.pauseAt(T0);
  return { ctx, errores };
}

/* ---------- un diseño ---------- */
async function mide(browser, clave, datos, nombresAlt) {
  const informe = { clave, anchos: {} };
  for (const [ancho, alto] of ANCHOS) {
    const { ctx, errores } = await nuevaPagina(browser, ancho, alto, datos.paginas);
    const r = { sombras: [], radios: [], pesos: [], oro: [], dropShadow: [], contraste: [], noMedible: [], tam: [], ctas: { max: 0, donde: [] }, hover: [], cajas: [],
      estructura: [], cabecera: [], tallas: [], tokens: null, minContraste: 99 };
    try {
      for (const ruta of datos.rutas) {
        const donde = clave + ' ' + ancho + ' /' + ruta;
        const page = await ctx.newPage();
        const cdp = await ctx.newCDPSession(page);
        await cdp.send('DOM.enable'); await cdp.send('CSS.enable');
        await page.goto(ORIGEN + '/' + ruta, { waitUntil: 'load' });
        await cargado(page, donde);
        const inv = await page.evaluate(inventario, ORO[clave] || null);
        const pre = (x) => '/' + ruta + ' ' + x;
        r.sombras.push(...inv.sombras.map(pre)); r.radios.push(...inv.radios.map(pre)); r.pesos.push(...inv.pesos.map(pre));
        r.oro.push(...inv.oro.map(pre)); r.dropShadow.push(...inv.dropShadow.map(pre));
        const c = ctasPorPantalla(inv, alto);
        if (c.max > r.ctas.max) r.ctas = { max: c.max, donde: c.donde.map(pre) };
        if (ruta === '') {
          r.tokens = inv.tokens;
          r.cajas.push(...(await cajas(page, clave, ancho)));
          const e = await estructura(page, ancho);
          r.estructura.push(...e.malos);
          r.tallas = e.tallas;
        }
        const peor = await contrastes(page, inv, inv.alto);
        for (const t of inv.textos) {
          const p = peor.get(t.id);
          if (!p) { r.noMedible.push(pre(t.sel + ' «' + t.texto + '»')); continue; }
          r.minContraste = Math.min(r.minContraste, p.c);
          if (p.c < 4.5) r.contraste.push(pre(t.sel + ' «' + t.texto + '» ' + r2(p.c) + ' (mediana ' + r2(p.med) + ', ' + t.tam + ' px)'));
          if (ancho < 900) {
            const cuerpo = t.largo >= 40 && !t.mayus && !t.fijo;
            if (t.tam < 12 || (cuerpo && t.tam < 16)) r.tam.push(pre(t.sel + ' «' + t.texto + '» ' + t.tam + ' px' + (cuerpo ? ' (cuerpo)' : '')));
          }
        }
        r.hover.push(...(await hoverDesplaza(page, cdp)).map(pre));
        await page.close();
      }
      // La entrada cerrada: solo los suelos de tamaño de sus textos (390)
      if (ancho < 900) {
        const c2 = await browser.newContext({ viewport: { width: ancho, height: alto }, deviceScaleFactor: 1, reducedMotion: 'no-preference' });
        const err2 = [];
        await c2.route('**/*', (q) => sirve(q, datos.paginas, err2));
        const p2 = await c2.newPage();
        await p2.goto(ORIGEN + '/', { waitUntil: 'load' });
        await cargado(p2, clave + ' entrada');
        const chicos = await p2.evaluate(() => [...document.querySelectorAll('#entrada *')].filter((e) => [...e.childNodes].some((t) => t.nodeType === 3 && t.textContent.trim()) && e.getClientRects().length)
          .map((e) => [e.className, parseFloat(getComputedStyle(e).fontSize), e.textContent.trim().slice(0, 24)]).filter((x) => x[1] < 12));
        r.tam.push(...chicos.map(([c, f, t]) => '#entrada .' + c + ' «' + t + '» ' + f + ' px'));
        await c2.close();
      }
    } finally { await ctx.close(); }
    if (errores.length) throw new NoMira(clave + ' ' + ancho + ': ' + [...new Set(errores)].slice(0, 4).join(' | '));
    // Cabecera, cajas y «&» con otros nombres: «Lucía & Marcos» (la boda de las capturas de S1) y los largos
    for (const [etiqueta, alt] of Object.entries(nombresAlt)) {
      const { ctx: cl, errores: el } = await nuevaPagina(browser, ancho, alto, alt.paginas);
      try {
        const pl = await cl.newPage();
        await pl.goto(ORIGEN + '/', { waitUntil: 'load' });
        await cargado(pl, clave + ' ' + etiqueta);
        await pl.evaluate(inventario, null);   // marca data-fab: cajas() lo usa
        const pre = (x) => '«' + etiqueta + '» ' + x;
        r.cabecera.push(...(await cabecera(pl)).map(pre));
        r.cajas.push(...(await cajas(pl, clave, ancho)).map(pre));
        r.estructura.push(...(await estructura(pl, ancho)).malos.filter((x) => /«/.test(x)).map(pre));
      } finally { await cl.close(); }
      if (el.length) throw new NoMira(clave + ' ' + etiqueta + ': ' + el.slice(0, 3).join(' | '));
    }
    const hex = (s) => s.startsWith('#') ? [1, 3, 5].map((i) => parseInt(s.slice(i, i + 2), 16)) : null;
    const tk = r.tokens;
    if (tk && hex(tk.papel) && hex(tk.acento)) {
      r.tokenPares = { 'tinta/papel': r2(contraste(hex(tk.tinta) || [25, 28, 29], hex(tk.papel))), 'blanco/acento': r2(contraste([255, 255, 255], hex(tk.acento))),
        'blanco/acento-hover': r2(contraste([255, 255, 255], hex(tk.acentoHover))), 'acento/papel': r2(contraste(hex(tk.acento), hex(tk.papel))) };
    }
    informe.anchos[ancho] = r;
  }
  return informe;
}

async function captura(browser, datos, ancho, alto, fichero) {
  const { ctx, errores } = await nuevaPagina(browser, ancho, alto, datos.paginas);
  try {
    const page = await ctx.newPage();
    await page.goto(ORIGEN + '/', { waitUntil: 'load' });
    await cargado(page, fichero);
    await page.screenshot({ path: fichero, fullPage: true, type: 'png' });
  } finally { await ctx.close(); }
  if (errores.length) throw new NoMira(fichero + ': ' + errores.slice(0, 3).join(' | '));
}

(async () => {
  const t0 = Date.now();
  if (!fs.existsSync(EDGE)) throw new NoMira('no encuentro Edge en ' + EDGE);
  let normal, largo, lucia;
  try { normal = genera([]); largo = genera(['--nombres', LARGO]); lucia = genera(['--nombres', 'Lucía|Marcos']); }
  catch (e) { throw new NoMira('tests/estilos_paginas.php ha fallado: ' + (e.stderr || e.message)); }
  const claves = Object.keys(normal.disenos).filter((k) => !SOLO || k === SOLO);
  const browser = await chromium.launch({ executablePath: EDGE, headless: true, args: ['--font-render-hinting=none', '--disable-lcd-text', '--hide-scrollbars'] });
  let fallos = 0;
  const todo = {};
  try {
    if (CAPTURAS) {
      fs.mkdirSync(CAPTURAS, { recursive: true });
      const lm = genera(['--nombres', 'Lucía|Marcos', '--control']);
      const ll = genera(['--nombres', LARGO]);
      for (const k of claves) {
        await captura(browser, lm.disenos[k], 390, 844, path.join(CAPTURAS, k + '_390.png'));
        await captura(browser, lm.disenos[k], 1440, 900, path.join(CAPTURAS, k + '_1440.png'));
        await captura(browser, ll.disenos[k], 390, 844, path.join(CAPTURAS, k + '_390_largo.png'));
      }
      for (const k of ['esencial', 'foto_papel']) for (const [w, h] of ANCHOS) await captura(browser, lm.disenos[k], w, h, path.join(CAPTURAS, 'control_' + k + '_' + w + '.png'));
      console.log('capturas en ' + CAPTURAS);
    }
    const res = await Promise.all(claves.map((k) => mide(browser, k, normal.disenos[k], { 'Lucía & Marcos': lucia.disenos[k], largos: largo.disenos[k] })));
    for (const inf of res) {
      todo[inf.clave] = inf;
      console.log('\n=== ' + inf.clave);
      for (const [w, r] of Object.entries(inf.anchos)) {
        const lista = { sombras: r.sombras, radios: r.radios, 'peso > 500': r.pesos, 'texto en oro': r.oro, 'contraste < 4,5': r.contraste,
          'tamaño bajo el suelo': r.tam, 'hover que mueve o da sombra': r.hover, 'cabecera con nombres largos': r.cabecera, cajas: r.cajas,
          'drop-shadow (también es sombra, ley 1)': r.dropShadow, estructura: r.estructura };
        const bad = Object.entries(lista).filter(([, v]) => v && v.length);
        const ctaMal = r.ctas && r.ctas.max > 1;
        const tokMal = r.tokenPares && (r.tokenPares['tinta/papel'] < 4.5 || r.tokenPares['blanco/acento'] < 4.5 || r.tokenPares['acento/papel'] < 3);
        if (bad.length || ctaMal || tokMal) fallos++;
        console.log('-- ' + w + ' px · contraste mínimo ' + r2(r.minContraste) + ' · CTAs rellenas por pantalla ' + (r.ctas ? r.ctas.max : '?')
          + ' · tokens ' + JSON.stringify(r.tokenPares || {}) + (r.noMedible.length ? ' · no medibles ' + r.noMedible.length : '')
          + ' · ' + r.tallas.length + ' tallas en la portada (' + r.tallas.join(' ') + ')');
        if (ctaMal) console.log('   CTAs a la vez: ' + r.ctas.donde.join(' | '));
        for (const [k, v] of bad) { console.log('   ' + k + ' (' + v.length + '):'); for (const x of [...new Set(v)].slice(0, 12)) console.log('     ' + x); if (new Set(v).size > 12) console.log('     … y ' + (new Set(v).size - 12) + ' más'); }
        if (r.noMedible.length) for (const x of [...new Set(r.noMedible)].slice(0, 6)) console.log('   no medible: ' + x);
      }
    }
  } finally { await browser.close(); }
  if (JSON_OUT) fs.writeFileSync(JSON_OUT, JSON.stringify(todo, null, 1));
  console.log('\n' + (fallos ? 'ROJO: ' + fallos + ' combinaciones diseño/ancho con incumplimientos' : 'VERDE') + ' · ' + ((Date.now() - t0) / 1000).toFixed(1) + ' s');
  process.exit(fallos ? 1 : 0);
})().catch((e) => {
  console.error((e instanceof NoMira ? 'NO HE PODIDO MEDIR: ' : 'ERROR: ') + (e.stack || e.message || e));
  process.exit(2);
});
