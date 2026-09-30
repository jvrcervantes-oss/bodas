/* Ramos de esquina para la foto de portada (AxisWorks · Bodas). Un ramo nace en (0,0), mirando hacia +x, y se
   entrega en DOS capas: «atras» (tallos y hojas de la base, que quedan detrás del marco de la foto) y «delante»
   (flores y hojas, que asoman por encima del borde). Coordenadas en px de diseño. Mismo pincel de acuarela que las
   guirnaldas (primitivas copiadas del artifact «Flores de la guirnalda»).
   ramo({ deco: 'flores'|'eucalipto', seed, L }) → { atras, delante } (cadenas SVG, sin filtro ni envoltorio). */
(function (root) {
  var PAL = { rosa: ['#f2c3c2', '#e9a9ab', '#dc8f95', '#f7d8d4'], centro: '#c7737b', crema: ['#fbe7cf', '#f4d6b3'], hoja: ['#a9bf9f', '#8faa88', '#7b977a', '#c3d3b8'], tallo: '#7d9577' };
  var EU = { hojas: ['#8fa79c', '#a9bdb2', '#7f988d', '#b9cbbf', '#6f8b80'], tallo: '#7d8f82' };
  function rng(a) { return function () { a |= 0; a = a + 0x6D2B79F5 | 0; var t = Math.imul(a ^ a >>> 15, 1 | a); t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t; return ((t ^ t >>> 14) >>> 0) / 4294967296; }; }
  function f(n) { return n.toFixed(1); }
  function petalo(cx, cy, a, l, w, fill, op) {
    var c = Math.cos(a), s = Math.sin(a), px = -s, py = c, tx = cx + c * l, ty = cy + s * l;
    var c1x = cx + c * l * .35 + px * w, c1y = cy + s * l * .35 + py * w, c2x = cx + c * l * 1.05 + px * w * .7, c2y = cy + s * l * 1.05 + py * w * .7;
    var c3x = cx + c * l * 1.05 - px * w * .7, c3y = cy + s * l * 1.05 - py * w * .7, c4x = cx + c * l * .35 - px * w, c4y = cy + s * l * .35 - py * w;
    return '<path d="M' + f(cx) + ' ' + f(cy) + 'C' + f(c1x) + ' ' + f(c1y) + ' ' + f(c2x) + ' ' + f(c2y) + ' ' + f(tx) + ' ' + f(ty) + 'C' + f(c3x) + ' ' + f(c3y) + ' ' + f(c4x) + ' ' + f(c4y) + ' ' + f(cx) + ' ' + f(cy) + 'Z" fill="' + fill + '" fill-opacity="' + op + '"/>';
  }
  function ramo(o) {
    var R = rng(o.seed || 1), L = o.L || 150, eu = o.deco === 'eucalipto', atras = '', delante = '';
    function r(a, b) { return a + R() * (b - a); }
    function elige(a) { return a[Math.floor(R() * a.length)]; }
    var lado = R() < .5 ? -1 : 1, curv = lado * r(.06, .14);
    function P(t) { return [t * L, Math.sin(t * Math.PI) * curv * L]; }
    function tangente(t) { var a = P(Math.max(0, t - .02)), b = P(Math.min(1, t + .02)); return Math.atan2(b[1] - a[1], b[0] - a[0]); }
    function tallo(pts, op, ancho, col) { return '<path d="M' + pts.map(function (p) { return f(p[0]) + ' ' + f(p[1]); }).join('L') + '" fill="none" stroke="' + col + '" stroke-opacity="' + op + '" stroke-width="' + ancho + '" stroke-linecap="round" stroke-linejoin="round"/>'; }
    // hoja: en el ramo de flores, alargada verde; en el de eucalipto, redonda con nervio
    function hoja(x, y, a, l) {
      if (eu) {
        var col = elige(EU.hojas), q = l * .46;
        return '<g transform="translate(' + f(x) + ' ' + f(y) + ') rotate(' + f(a * 180 / Math.PI + 90) + ')"><path d="M0 0C' + f(-q * 1.05) + ' ' + f(-l * .12) + ' ' + f(-q * 1.1) + ' ' + f(-l * .78) + ' 0 ' + f(-l) + 'C' + f(q * 1.1) + ' ' + f(-l * .78) + ' ' + f(q * 1.05) + ' ' + f(-l * .12) + ' 0 0Z" fill="' + col + '" fill-opacity="' + (.7 + R() * .22).toFixed(2) + '"/><path d="M0 ' + f(-l * .06) + 'L0 ' + f(-l * .86) + '" stroke="#fff" stroke-opacity=".38" stroke-width="' + f(Math.max(.6, l * .05)) + '" stroke-linecap="round"/></g>';
      }
      return petalo(x, y, a, l, l * .32, elige(PAL.hoja), .62) + '<path d="M' + f(x) + ' ' + f(y) + 'L' + f(x + Math.cos(a) * l * .85) + ' ' + f(y + Math.sin(a) * l * .85) + '" stroke="#fff" stroke-opacity=".35" stroke-width="1"/>';
    }
    function rosa(x, y, t) {
      var col = elige(PAL.rosa), o = '<circle cx="' + f(x) + '" cy="' + f(y) + '" r="' + f(t * 1.05) + '" fill="' + col + '" fill-opacity=".35"/>', k;
      for (k = 0; k < 7; k++) o += petalo(x, y, k / 7 * Math.PI * 2 + r(-.2, .2), t * r(.85, 1.05), t * .62, col, .5);
      for (k = 0; k < 5; k++) o += petalo(x, y, k / 5 * Math.PI * 2 + r(0, 1), t * r(.55, .7), t * .45, elige(PAL.rosa), .55);
      return o + '<circle cx="' + f(x + r(-1, 1)) + '" cy="' + f(y + r(-1, 1)) + '" r="' + f(t * .32) + '" fill="' + PAL.centro + '" fill-opacity=".55"/>';
    }
    function flor(x, y, t) { var col = elige(PAL.crema), a0 = r(0, 1), o = '', k; for (k = 0; k < 5; k++) o += petalo(x, y, a0 + k / 5 * Math.PI * 2, t, t * .7, col, .6); return o + '<circle cx="' + f(x) + '" cy="' + f(y) + '" r="' + f(t * .22) + '" fill="#d9a766" fill-opacity=".8"/>'; }
    var colT = eu ? EU.tallo : PAL.tallo, esc = L / 150;
    // Rama: un tallo curvo con hojas alternas. La base (t < corte) queda detrás del marco; el resto, por delante.
    function rama(a0, len, curv, nHojas, tam, corte, conDetalle) {
      var ca = Math.cos(a0), sa = Math.sin(a0);
      function Q(t) { var d = Math.sin(t * Math.PI) * curv * len; return [ca * t * len - sa * d, sa * t * len + ca * d]; }
      function ang(t) { var a = Q(Math.max(0, t - .03)), b = Q(Math.min(1, t + .03)); return Math.atan2(b[1] - a[1], b[0] - a[0]); }
      var pts = [], t, k;
      for (t = 0; t <= 1.001; t += .1) pts.push(Q(t));
      var nb = Math.round(corte * 10) + 1;
      atras += tallo(pts.slice(0, nb), .78, 1.6, colT); delante += tallo(pts.slice(Math.max(0, nb - 2)), .78, 1.5, colT);
      for (k = 0; k < nHojas; k++) {
        var tt = .06 + (k / (nHojas - 1)) * .92, p = Q(tt), s = k % 2 ? 1 : -1;
        var l = tam * r(.85, 1.15) * (1.08 - tt * .55), h = hoja(p[0], p[1], ang(tt) + s * r(.5, 1.05), l);
        if (tt < corte) atras += h; else delante += h;
      }
      // hoja terminal
      var pe = Q(1); delante += hoja(pe[0], pe[1], ang(1) + r(-.2, .2), tam * .6);
      return { Q: Q, ang: ang };
    }
    var m = rama(0, L, curv, eu ? 12 : 11, (eu ? 27 : 26) * esc, .34, true);
    // dos tallos más en abanico, algo más cortos, para que sea un ramo y no una rama
    var m2 = rama(-lado * .42, L * .8, -curv * 1.2, eu ? 9 : 8, (eu ? 24 : 22) * esc, .3);
    var m3 = rama(lado * .38, L * .72, curv * 1.4, eu ? 8 : 7, (eu ? 22 : 20) * esc, .3);
    if (!eu) {
      // racimo de flores en la base: la grande queda sobre el borde del marco
      var pg = m.Q(.2);
      delante += rosa(pg[0] + r(-2, 2), pg[1] + r(-3, 3), 29 * esc);
      var pa = m.Q(.34); delante += rosa(pa[0] + r(-2, 4), pa[1] + lado * 15 * esc, 21 * esc);
      var pb = m2.Q(.4); delante += rosa(pb[0], pb[1], 18 * esc);
      var pc = m3.Q(.5); delante += rosa(pc[0], pc[1], 15 * esc);
      var pd = m.Q(.58); delante += rosa(pd[0], pd[1] - lado * 9 * esc, 12 * esc);
      var k2; for (k2 = 0; k2 < 6; k2++) { var pp = [m, m2, m3][k2 % 3].Q(r(.45, .98)); delante += flor(pp[0] + r(-8, 8), pp[1] + r(-8, 8), r(6, 9) * esc); }
    }
    return { atras: atras, delante: delante };
  }
  root.ramoEsquina = ramo;
  if (typeof module !== 'undefined') module.exports = { ramo: ramo };
})(typeof window !== 'undefined' ? window : globalThis);
