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
  // El iframe se recarga entero en cada cambio: hasta que baja la letra elegida se vería la de reserva
  // y luego el salto. El cuerpo va oculto (render.php) hasta que las fuentes estén listas, con tope de 2,5 s.
  // «lista» se avisa después, para que el scroll se restaure sobre la maqueta definitiva.
  var avisada = false;
  function lista() {
    if (avisada) return;
    avisada = true;
    document.body.style.visibility = 'visible';
    parent.postMessage({ tipo: 'lista' }, '*');
  }
  setTimeout(lista, 2500);
  if (document.fonts && document.fonts.ready) {
    // load = CSS e imágenes ya aplicados, así que fonts.ready ya sabe qué letras se usan
    var espera = function () { document.fonts.ready.then(lista, lista); };
    if (document.readyState === 'complete') espera(); else window.addEventListener('load', espera);
  } else lista();
})();
