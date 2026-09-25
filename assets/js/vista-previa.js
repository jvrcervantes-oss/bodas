// Dentro del iframe de la vista previa (sandbox sin same-origin): los enlaces
// internos piden al creador que cambie de página, y la foto llega por postMessage
// porque todavía no está subida a ningún sitio.
(function () {
  'use strict';
  document.addEventListener('click', function (e) {
    var a = e.target.closest('a');
    if (!a) return;
    var ir = a.getAttribute('data-ir');
    if (ir) {
      e.preventDefault();
      parent.postMessage({ tipo: 'ir', pagina: ir }, '*');
      return;
    }
    var href = a.getAttribute('href') || '';
    // Enlaces externos (mapas, webs de hoteles) sí se abren, en pestaña nueva
    if (/^https?:/i.test(href)) { a.target = '_blank'; return; }
    e.preventDefault();
  });
  window.addEventListener('message', function (e) {
    var d = e.data || {};
    if (d.tipo === 'foto') {
      document.querySelectorAll('img[data-foto]').forEach(function (img) {
        if (typeof d.src === 'string' && /^(data:image\/|blob:)/.test(d.src)) img.src = d.src;
      });
    }
    if (d.tipo === 'scroll' && typeof d.y === 'number') window.scrollTo(0, d.y);
  });
  window.addEventListener('scroll', function () {
    parent.postMessage({ tipo: 'scroll', y: window.scrollY }, '*');
  }, { passive: true });
  // Sin animación de aparición en la vista previa: se vería en blanco al recargar cada tecla
  document.querySelectorAll('.rv').forEach(function (el) { el.classList.add('in'); });
  parent.postMessage({ tipo: 'lista' }, '*');
})();
