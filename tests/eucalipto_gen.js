/* Generador de la guirnalda de eucalipto (AxisWorks · Bodas). Determinista: mismos ajustes = mismo dibujo.
   genEucalipto({ seed, w, h, escala, densidad, largo, tono }) → cadena SVG con fondo transparente.
   Misma piel que la guirnalda de «Flores de acuarela»: turbulencia + desplazamiento + borde de pigmento. */
(function (root) {
  var TONOS = {
    plata:  { hojas: ['#8fa79c', '#a9bdb2', '#7f988d', '#b9cbbf', '#6f8b80'], tallo: '#7d8f82', nervio: '#ffffff' },
    salvia: { hojas: ['#7d9577', '#9db396', '#6c8768', '#b7c8ad', '#8aa384'], tallo: '#79896f', nervio: '#ffffff' },
    bosque: { hojas: ['#5d7d70', '#71917f', '#4d6f62', '#88a596', '#3f6154'], tallo: '#5a7266', nervio: '#e9f1ec' }
  };
  function rng(a) { return function () { a |= 0; a = a + 0x6D2B79F5 | 0; var t = Math.imul(a ^ a >>> 15, 1 | a); t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t; return ((t ^ t >>> 14) >>> 0) / 4294967296; }; }
  function f(n) { return n.toFixed(1); }
  // Hoja de eucalipto redonda («moneda»): base puntiaguda en (0,0), punta hacia -y, longitud L.
  function hoja(x, y, ang, L, color, op, nervio) {
    var a = L * 0.46, t = 'translate(' + f(x) + ' ' + f(y) + ') rotate(' + f(ang) + ')';
    return '<g transform="' + t + '"><path d="M0 0C' + f(-a * 1.05) + ' ' + f(-L * 0.12) + ' ' + f(-a * 1.1) + ' ' + f(-L * 0.78) + ' 0 ' + f(-L) +
      'C' + f(a * 1.1) + ' ' + f(-L * 0.78) + ' ' + f(a * 1.05) + ' ' + f(-L * 0.12) + ' 0 0Z" fill="' + color + '" fill-opacity="' + op + '"/>' +
      '<path d="M0 ' + f(-L * 0.06) + 'L0 ' + f(-L * 0.86) + '" stroke="' + nervio + '" stroke-opacity=".38" stroke-width="' + f(Math.max(.6, L * 0.05)) + '" stroke-linecap="round"/></g>';
  }
  function genEucalipto(o) {
    o = o || {};
    var w = o.w || 520, h = o.h || 250, s = o.escala || 1, dens = o.densidad || 1, larg = o.largo || 1;
    var T = TONOS[o.tono] || TONOS.plata, R = rng(o.seed || 1);
    var pal = T.hojas, capas = { atras: '', medio: '', frente: '' };
    var y0 = h * 0.09, ph = R() * 6.28, k = 1.4 + R() * 1.2;
    function yTallo(x) { return y0 + Math.sin(x / w * Math.PI * k + ph) * h * 0.028; }
    // Tallo principal, algo más largo que la imagen para que se corte fuera y no dentro
    var d = '', x;
    for (x = -14 * s; x <= w + 14 * s; x += 14 * s) d += (d ? 'L' : 'M') + f(x) + ' ' + f(yTallo(x));
    capas.medio += '<path d="' + d + '" fill="none" stroke="' + T.tallo + '" stroke-opacity=".8" stroke-width="' + f(1.7 * s) + '" stroke-linecap="round" stroke-linejoin="round"/>';
    function color() { return pal[Math.floor(R() * pal.length)]; }
    // Hojas pegadas al tallo principal
    for (x = -8 * s; x < w + 8 * s; x += (17 / dens) * s * (0.7 + R() * 0.8)) {
      var yy = yTallo(x), lado = R() < .5 ? -1 : 1, L = (14 + R() * 8) * s;
      var arriba = R() < .3, aa = 22 + R() * 38;
      capas.medio += hoja(x, yy, arriba ? lado * aa : 180 + lado * aa, L, color(), (.7 + R() * .2).toFixed(2), T.nervio);
    }
    // Ramas colgantes
    var n = Math.round(w / (31 * s) * dens), i;
    for (i = 0; i < n; i++) {
      var bx = (i + 0.2 + R() * 0.6) / n * w, by = yTallo(bx);
      var largoRama = h * (0.22 + R() * 0.36) * larg * (0.85 + 0.3 * Math.sin(bx / w * Math.PI));
      var deriva = (R() - .5) * 26 * s, curva = (R() - .5) * 30 * s;
      var ex = bx + deriva, ey = by + largoRama, cx = bx + curva, cy = by + largoRama * 0.5;
      var capa = R() < .32 ? 'atras' : 'medio';
      var g = '<path d="M' + f(bx) + ' ' + f(by) + 'Q' + f(cx) + ' ' + f(cy) + ' ' + f(ex) + ' ' + f(ey) + '" fill="none" stroke="' + T.tallo + '" stroke-opacity=".75" stroke-width="' + f(1.25 * s) + '" stroke-linecap="round"/>';
      var nh = Math.max(3, Math.round(largoRama / (13 * s) * dens)), j;
      for (j = 1; j <= nh; j++) {
        var t = j / (nh + 0.3), u = 1 - t;
        var px = u * u * bx + 2 * u * t * cx + t * t * ex, py = u * u * by + 2 * u * t * cy + t * t * ey;
        var tx = 2 * u * (cx - bx) + 2 * t * (ex - cx), ty = 2 * u * (cy - by) + 2 * t * (ey - cy);
        var ang0 = Math.atan2(ty, tx) * 180 / Math.PI + 90;   // dirección de la rama medida desde «arriba»
        var tam = (23 - 10 * t + R() * 6) * s * (capa === 'atras' ? .9 : 1);
        var par = j % 2 === 0 ? 1 : -1;
        var giro = par * (48 + R() * 26);
        g += hoja(px, py, ang0 + giro, tam, color(), (.66 + R() * .26).toFixed(2), T.nervio);
        if (R() < .34) g += hoja(px, py, ang0 - giro * 0.9 + (R() - .5) * 20, tam * .72, color(), (.62 + R() * .24).toFixed(2), T.nervio);
      }
      g += hoja(ex, ey, Math.atan2(ey - cy, ex - cx) * 180 / Math.PI + 90, (16 + R() * 5) * s, color(), '.85', T.nervio);
      capas[capa] += g;
    }
    var id = 'e' + (o.seed || 1);
    return '<svg xmlns="http://www.w3.org/2000/svg" width="' + w + '" height="' + h + '" viewBox="0 0 ' + w + ' ' + h + '"><defs>' +
      '<filter id="w' + id + '" x="-20%" y="-20%" width="140%" height="140%">' +
      '<feTurbulence type="fractalNoise" baseFrequency="0.035" numOctaves="3" seed="55" result="n"/>' +
      '<feDisplacementMap in="SourceGraphic" in2="n" scale="' + f(6 * s) + '" xChannelSelector="R" yChannelSelector="G" result="d"/>' +
      '<feGaussianBlur in="d" stdDeviation="0.6" result="b"/><feMorphology in="b" operator="erode" radius="0.6" result="e"/>' +
      '<feComposite in="b" in2="e" operator="out" result="borde"/>' +
      '<feColorMatrix in="borde" type="matrix" values="0.8 0 0 0 -0.08  0 0.85 0 0 -0.08  0 0 0.85 0 -0.06  0 0 0 0.9 0" result="pig"/>' +
      '<feMerge><feMergeNode in="b"/><feMergeNode in="pig"/></feMerge></filter>' +
      '<filter id="g' + id + '"><feGaussianBlur stdDeviation="' + f(3 * s) + '"/></filter></defs>' +
      '<g filter="url(#w' + id + ')"><g filter="url(#g' + id + ')" opacity=".32">' + capas.atras + '</g>' + capas.medio + capas.frente + '</g></svg>';
  }
  root.genEucalipto = genEucalipto;
  if (typeof module !== 'undefined') module.exports = { genEucalipto: genEucalipto };
})(typeof window !== 'undefined' ? window : globalThis);
