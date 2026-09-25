// Panel de la pareja: compartir la web (QR para las invitaciones en papel y mensaje de WhatsApp)
// y copiar la lista de invitados que faltan por contestar. El QR se dibuja aquí, en el navegador,
// con qrcode-generator (assets/js/vendor/qrcode.js): nada sale a servicios de terceros.
(function () {
  'use strict';

  function copia(texto, aviso) {
    function ok() { if (aviso) { aviso.hidden = false; setTimeout(function () { aviso.hidden = true; }, 2000); } }
    if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(texto).then(ok, function () {});
  }

  // ------------------------------------------------------------ QR
  var cajaQr = document.getElementById('qr');
  var qr = null;
  if (cajaQr && window.qrcode) {
    qr = window.qrcode(0, 'M');
    qr.addData(cajaQr.getAttribute('data-url'));
    qr.make();
  }
  // SVG propio (módulos oscuros como un único trazo), con margen de 4 módulos como pide el estándar
  function svgQr(tam) {
    var n = qr.getModuleCount(), m = 4, total = n + m * 2, d = '';
    for (var r = 0; r < n; r++) for (var c = 0; c < n; c++) if (qr.isDark(r, c)) d += 'M' + (c + m) + ' ' + (r + m) + 'h1v1h-1z';
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + total + ' ' + total + '"' + (tam ? ' width="' + tam + '" height="' + tam + '"' : '') +
      ' shape-rendering="crispEdges"><rect width="100%" height="100%" fill="#fff"/><path d="' + d + '" fill="#1a1a1a"/></svg>';
  }
  function descarga(blob, nombre) {
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = nombre;
    document.body.appendChild(a);
    a.click();
    setTimeout(function () { URL.revokeObjectURL(a.href); a.remove(); }, 1000);
  }
  if (qr) {
    var img = document.createElement('img');
    img.src = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svgQr(0));
    img.alt = 'Código QR que abre vuestra web';
    cajaQr.appendChild(img);
    var nombre = 'qr-' + (cajaQr.getAttribute('data-slug') || 'boda');
    var bSvg = document.querySelector('[data-qr-svg]');
    if (bSvg) bSvg.addEventListener('click', function () { descarga(new Blob([svgQr(0)], { type: 'image/svg+xml' }), nombre + '.svg'); });
    var bPng = document.querySelector('[data-qr-png]');
    if (bPng) bPng.addEventListener('click', function () {
      var n = qr.getModuleCount() + 8, px = Math.floor(1200 / n), cv = document.createElement('canvas');
      cv.width = cv.height = n * px;
      var cx = cv.getContext('2d');
      cx.fillStyle = '#fff'; cx.fillRect(0, 0, cv.width, cv.height); cx.fillStyle = '#1a1a1a';
      for (var r = 0; r < n - 8; r++) for (var c = 0; c < n - 8; c++) if (qr.isDark(r, c)) cx.fillRect((c + 4) * px, (r + 4) * px, px, px);
      cv.toBlob(function (b) { descarga(b, nombre + '.png'); }, 'image/png');
    });
  }

  // ------------------------------------------------------------ WhatsApp: el enlace sigue al mensaje
  var msg = document.getElementById('msgWa'), wa = document.getElementById('btnWa');
  if (msg && wa) msg.addEventListener('input', function () { wa.href = 'https://wa.me/?text=' + encodeURIComponent(msg.value); });

  document.querySelectorAll('[data-copiar-enlace]').forEach(function (b) {
    b.addEventListener('click', function () { copia(b.getAttribute('data-copiar-enlace'), b.parentNode.querySelector('.copiado')); });
  });
  document.querySelectorAll('[data-copiar-pendientes]').forEach(function (b) {
    b.addEventListener('click', function () { copia(b.getAttribute('data-copiar-pendientes'), document.querySelector('.inv-copiado')); });
  });
})();
