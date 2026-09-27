// Panel de la pareja (app/panel.php): compartir la web (QR para las invitaciones en papel y mensaje de WhatsApp),
// copiar enlaces y listas, plano de mesas y subir fotos a la galería. El QR se dibuja aquí, en el navegador,
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
  // Resumen para el catering: la CSP no deja onclick en línea, así que el botón de imprimir va aquí
  document.querySelectorAll('[data-imprimir]').forEach(function (b) {
    b.addEventListener('click', function () { window.print(); });
  });
  // Texto ya preparado en el servidor (lista para el DJ, resumen para el catering)
  document.querySelectorAll('[data-copiar-texto]').forEach(function (b) {
    b.addEventListener('click', function () { copia(b.getAttribute('data-copiar-texto'), b.parentNode.querySelector('.copiado')); });
  });
  document.querySelectorAll('[data-copiar-pendientes]').forEach(function (b) {
    b.addEventListener('click', function () { copia(b.getAttribute('data-copiar-pendientes'), document.querySelector('.inv-copiado')); });
  });
  // Botones que borran algo: confirmación antes de enviar su formulario (la CSP no deja onsubmit en línea)
  document.querySelectorAll('[data-confirmar]').forEach(function (b) {
    b.addEventListener('click', function (e) { if (!window.confirm(b.getAttribute('data-confirmar'))) e.preventDefault(); });
  });

  // ------------------------------------------------------------ plano de mesas (app/mesas.php)
  // Tocar persona(s) y luego la mesa: funciona igual con el dedo que con el ratón, sin arrastrar. La selección
  // solo vive en la página; lo que se guarda lo decide el servidor (ids que siguen viniendo, plazas libres).
  var formSentar = document.getElementById('form-sentar');
  if (formSentar) {
    var elegidas = {};
    var destinos = document.querySelectorAll('[data-sentar]');
    function pinta() {
      var n = Object.keys(elegidas).length;
      document.querySelectorAll('[data-persona]').forEach(function (b) { b.setAttribute('aria-pressed', elegidas[b.getAttribute('data-persona')] ? 'true' : 'false'); });
      destinos.forEach(function (d) { d.disabled = n === 0; });
    }
    document.querySelectorAll('[data-persona]').forEach(function (b) {
      b.addEventListener('click', function () {
        var id = b.getAttribute('data-persona');
        if (elegidas[id]) delete elegidas[id]; else elegidas[id] = true;
        pinta();
      });
    });
    document.querySelectorAll('[data-grupo-sel]').forEach(function (b) {
      b.addEventListener('click', function () {
        var g = b.getAttribute('data-grupo-sel');
        document.querySelectorAll('.mesa-sin [data-persona]').forEach(function (p) { if (p.getAttribute('data-grupo') === g) elegidas[p.getAttribute('data-persona')] = true; });
        pinta();
      });
    });
    destinos.forEach(function (d) {
      d.addEventListener('click', function () {
        var ids = Object.keys(elegidas);
        if (!ids.length) return;
        formSentar.querySelector('[name="mesa"]').value = d.getAttribute('data-sentar');
        formSentar.querySelectorAll('[name="personas[]"]').forEach(function (i) { i.remove(); });
        ids.forEach(function (id) {
          var i = document.createElement('input');
          i.type = 'hidden'; i.name = 'personas[]'; i.value = id;
          formSentar.appendChild(i);
        });
        formSentar.submit();
      });
    });
    pinta();
  }

  // ------------------------------------------------------------ galería: subir fotos (POST /panel/galeria, el de siempre)
  // Una foto por petición, en orden; el servidor la comprueba y la recodifica. Al acabar se recarga la página.
  var subir = document.querySelector('[data-galeria-subir]');
  if (subir) {
    var aviso = document.querySelector('[data-galeria-msg]'), consent = document.querySelector('[data-galeria-consent]');
    subir.addEventListener('change', function () {
      var files = Array.prototype.slice.call(subir.files), hechas = 0;
      subir.value = '';
      if (consent && !consent.checked) { aviso.textContent = 'Marcad antes la casilla de permisos.'; return; }
      (function sube() {
        var f = files.shift();
        if (!f) { aviso.textContent = hechas ? 'Fotos subidas. Ya están en la web.' : ''; if (hechas) window.location.reload(); return; }
        aviso.textContent = 'Subiendo ' + f.name + '…';
        var fd = new FormData();
        fd.append('csrf', subir.getAttribute('data-csrf')); fd.append('foto', f); fd.append('consentido', 'si');
        fetch('/panel/galeria', { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); }).then(function (j) {
          if (!j.ok) { aviso.textContent = j.error || 'No se ha podido subir.'; if (hechas) setTimeout(function () { window.location.reload(); }, 2500); return; }
          hechas++; sube();
        }).catch(function () { aviso.textContent = 'Sin conexión. Inténtalo de nuevo.'; });
      })();
    });
  }

  // ------------------------------------------------------------ compra de un extra (app/extras.php → /panel/extra)
  // El importe lo pone el servidor: aquí solo se manda la clave, la casilla y el CSRF, y se va a la URL de pago
  document.querySelectorAll('[data-extra-compra]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      e.preventDefault();
      var msg = f.querySelector('[data-extra-msg]'), btn = f.querySelector('button[type="submit"]');
      btn.disabled = true;
      fetch('/panel/extra', { method: 'POST', body: new FormData(f), credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (j) {
          if (j.ok && String(j.url || '').indexOf('https://') === 0) { window.location.href = j.url; return; }
          msg.textContent = j.error || 'No se ha podido abrir el pago.'; msg.hidden = false; btn.disabled = false;
        })
        .catch(function () { msg.textContent = 'Sin conexión. Inténtalo de nuevo.'; msg.hidden = false; btn.disabled = false; });
    });
  });
})();
