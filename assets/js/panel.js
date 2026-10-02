// Panel de la pareja (app/panel.php): compartir la web (QR para las invitaciones en papel y mensaje de WhatsApp),
// copiar enlaces y listas y plano de mesas. El QR se dibuja aquí, en el navegador,
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

  // Volver a una parte plegada (#personas, #lista…) la abre
  if (location.hash.length > 1) {
    var destino = document.getElementById(location.hash.slice(1));
    if (destino && destino.tagName === 'DETAILS') destino.open = true;
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
    // Sentar a estas personas en esta mesa (lo mismo toquen o arrastren: el servidor decide si caben)
    function sienta(ids, mesaId) {
      if (!ids.length) return;
      formSentar.querySelector('[name="mesa"]').value = mesaId;
      formSentar.querySelectorAll('[name="personas[]"]').forEach(function (i) { i.remove(); });
      ids.forEach(function (id) {
        var i = document.createElement('input');
        i.type = 'hidden'; i.name = 'personas[]'; i.value = id;
        formSentar.appendChild(i);
      });
      formSentar.submit();
    }
    destinos.forEach(function (d) {
      d.addEventListener('click', function () { sienta(Object.keys(elegidas), d.getAttribute('data-sentar')); });
    });
    pinta();

    // Arrastrar y soltar con ratón o dedo (Pointer Events): una persona, o «Todo el grupo», hasta una mesa; también de una
    // mesa a otra. Si la persona arrastrada está entre las elegidas, van todas las elegidas. Tocar sin mover sigue eligiendo.
    var arr = null, acabaDeArrastrar = false;
    function idsDeArrastre(o) {
      if (o.hasAttribute('data-grupo-sel')) {
        var g = o.getAttribute('data-grupo-sel'), r = [];
        document.querySelectorAll('.mesa-sin [data-persona]').forEach(function (p) { if (p.getAttribute('data-grupo') === g) r.push(p.getAttribute('data-persona')); });
        return r;
      }
      var id = o.getAttribute('data-persona'), sel = Object.keys(elegidas);
      return elegidas[id] && sel.length > 1 ? sel : [id];
    }
    function mesaBajo(x, y) { var e = document.elementFromPoint(x, y); return e && e.closest ? e.closest('.mesa') : null; }
    function marcaDestino(m) {
      document.querySelectorAll('.mesa.es-destino').forEach(function (x) { if (x !== m) x.classList.remove('es-destino'); });
      if (m) m.classList.add('es-destino');
    }
    function termina(ev) {
      document.removeEventListener('pointermove', mueve);
      document.removeEventListener('pointerup', termina);
      document.removeEventListener('pointercancel', termina);
      if (!arr) return;
      var a = arr; arr = null;
      if (!a.activo) return;
      acabaDeArrastrar = true; setTimeout(function () { acabaDeArrastrar = false; }, 0);   // el click que sigue al soltar no cuenta
      a.fantasma.remove(); document.body.classList.remove('arrastrando'); marcaDestino(null);
      if (ev.type !== 'pointerup') return;
      var m = mesaBajo(ev.clientX, ev.clientY);
      if (m && m !== a.origen.closest('.mesa')) sienta(a.ids, m.getAttribute('data-mesa'));
    }
    function mueve(ev) {
      if (!arr) return;
      if (!arr.activo) {
        if (Math.abs(ev.clientX - arr.x) + Math.abs(ev.clientY - arr.y) < 8) return;
        arr.activo = true;
        arr.ids = idsDeArrastre(arr.origen);
        arr.fantasma = document.createElement('div');
        arr.fantasma.className = 'arrastre-fantasma';
        arr.fantasma.textContent = arr.ids.length > 1 ? arr.ids.length + ' personas' : arr.origen.textContent.replace(/\s+/g, ' ').trim();
        document.body.appendChild(arr.fantasma); document.body.classList.add('arrastrando');
      }
      arr.fantasma.style.left = (ev.clientX + 12) + 'px'; arr.fantasma.style.top = (ev.clientY + 12) + 'px';
      marcaDestino(mesaBajo(ev.clientX, ev.clientY));
      if (ev.clientY < 60) window.scrollBy(0, -14); else if (ev.clientY > window.innerHeight - 60) window.scrollBy(0, 14);
    }
    document.querySelectorAll('.mesa-persona[data-persona], .todo-grupo[data-grupo-sel]').forEach(function (o) {
      o.addEventListener('pointerdown', function (ev) {
        if ((ev.pointerType === 'mouse' && ev.button !== 0) || arr) return;
        arr = { origen: o, x: ev.clientX, y: ev.clientY, activo: false };
        document.addEventListener('pointermove', mueve);
        document.addEventListener('pointerup', termina);
        document.addEventListener('pointercancel', termina);
      });
      o.addEventListener('dragstart', function (ev) { ev.preventDefault(); });
    });
    document.addEventListener('click', function (ev) { if (acabaDeArrastrar) { ev.stopPropagation(); ev.preventDefault(); } }, true);
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
