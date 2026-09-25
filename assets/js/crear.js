// Creador de webs de boda. El estado es UN objeto (el config.json de la boda);
// el formulario lo edita y la vista previa lo pide renderizado al servidor, que usa
// el mismo generador que la web publicada. Nada de HTML se construye aquí con
// textos de la pareja: todo va por textContent / value.
(function () {
  'use strict';

  var D = JSON.parse(document.getElementById('datos').textContent);
  var MODO = D.modo;                         // 'crear' | 'editar'
  var CLAVE = 'boda_borrador_v1';
  var URL_PREVIA = MODO === 'editar' ? '/panel/vista-previa' : '/api/vista-previa';
  var st = D.config;
  var foto = { blob: null, src: '', quitar: false };
  var slugTocado = false;

  function guarda(k, v) { try { localStorage.setItem(k, v); } catch (e) { /* modo privado o lleno */ } }
  function lee(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }

  if (MODO === 'crear') {
    var b = lee(CLAVE);
    if (b) { try { var o = JSON.parse(b); if (o && o.config) { st = o.config; slugTocado = !!o.slugTocado; if (o.slug) document.getElementById('slug').value = o.slug; if (o.foto) foto.src = o.foto; } } catch (e) {} }
  }

  // ------------------------------------------------------------ utilidades
  function el(tag, attrs, hijos) {
    var n = document.createElement(tag);
    Object.keys(attrs || {}).forEach(function (k) {
      if (k === 'text') n.textContent = attrs[k];
      else if (k === 'class') n.className = attrs[k];
      else if (k.slice(0, 2) === 'on') n.addEventListener(k.slice(2), attrs[k]);
      else if (attrs[k] === true) n.setAttribute(k, '');
      else if (attrs[k] !== false && attrs[k] != null) n.setAttribute(k, attrs[k]);
    });
    (hijos || []).forEach(function (h) { if (h) n.appendChild(h); });
    return n;
  }
  function get(o, path) { return path.split('.').reduce(function (a, k) { return a == null ? a : a[k]; }, o); }
  function set(o, path, v) {
    var ks = path.split('.'), last = ks.pop();
    ks.reduce(function (a, k) { return a[k] = a[k] || {}; }, o)[last] = v;
  }
  function slugify(s) {
    return (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/ñ/g, 'n')
      .replace(/&/g, 'y').replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 40).replace(/-+$/, '');
  }
  var t;
  function cambio() {
    clearTimeout(t);
    t = setTimeout(function () { persiste(); previa(); }, 350);
  }
  function persiste() {
    if (MODO !== 'crear') return;
    guarda(CLAVE, JSON.stringify({ config: st, slug: slugEl ? slugEl.value : '', slugTocado: slugTocado, foto: foto.src.length < 1500000 ? foto.src : '' }));
  }

  // ------------------------------------------------------------ campos simples
  document.querySelectorAll('[data-k]').forEach(function (inp) {
    var k = inp.getAttribute('data-k');
    inp.value = get(st, k) || '';
    inp.addEventListener('input', function () {
      set(st, k, inp.value);
      if (/^pareja\.nombre/.test(k)) sugiereSlug();
      cambio();
    });
  });

  // ------------------------------------------------------------ temas
  var temasEl = document.getElementById('temas');
  Object.keys(D.temas).forEach(function (k) {
    var tm = D.temas[k];
    var r = el('input', { type: 'radio', name: 'tema', value: k });
    r.checked = st.tema === k;
    r.addEventListener('change', function () { st.tema = k; cambio(); });
    var sw = el('span', { class: 'c-tema-sw' });
    sw.style.background = tm.color;
    sw.style.boxShadow = 'inset 0 0 0 6px ' + tm.fondo;
    temasEl.appendChild(el('label', { class: 'c-tema' }, [r, sw, el('span', { text: tm.nombre })]));
  });

  // ------------------------------------------------------------ foto
  var fotoInput = document.getElementById('fotoInput');
  var fotoImg = document.getElementById('fotoImg');
  var fotoVacia = document.getElementById('fotoVacia');
  var fotoQuitar = document.getElementById('fotoQuitar');
  function pintaFoto() {
    var hay = !!foto.src;
    fotoImg.hidden = !hay;
    fotoVacia.hidden = hay;
    fotoQuitar.hidden = !hay;
    if (hay) fotoImg.src = foto.src;
    st.foto = hay;
  }
  // Se reduce y convierte a WebP aquí (norma del estudio) antes de subirla; el
  // servidor la vuelve a comprobar y recodificar igualmente.
  fotoInput.addEventListener('change', function () {
    var f = fotoInput.files && fotoInput.files[0];
    if (!f) return;
    if (!/^image\/(jpeg|png|webp)$/.test(f.type)) { alert('La foto tiene que ser JPG, PNG o WebP.'); return; }
    var img = new Image();
    var url = URL.createObjectURL(f);
    img.onload = function () {
      var max = 1400, k = Math.min(1, max / Math.max(img.width, img.height));
      var c = document.createElement('canvas');
      c.width = Math.round(img.width * k); c.height = Math.round(img.height * k);
      c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
      URL.revokeObjectURL(url);
      c.toBlob(function (blob) {
        if (!blob) { alert('No hemos podido leer la foto.'); return; }
        foto.blob = blob; foto.quitar = false;
        var fr = new FileReader();
        fr.onload = function () { foto.src = fr.result; pintaFoto(); cambio(); };
        fr.readAsDataURL(blob);
      }, 'image/webp', 0.85);
    };
    img.onerror = function () { URL.revokeObjectURL(url); alert('No hemos podido leer la foto.'); };
    img.src = url;
    fotoInput.value = '';
  });
  fotoQuitar.addEventListener('click', function () { foto = { blob: null, src: '', quitar: true }; pintaFoto(); cambio(); });

  if (MODO === 'editar' && D.fotoUrl) {
    fetch(D.fotoUrl).then(function (r) { return r.ok ? r.blob() : null; }).then(function (b) {
      if (!b) return;
      var fr = new FileReader();
      fr.onload = function () { foto.src = fr.result; pintaFoto(); enviaFoto(); };
      fr.readAsDataURL(b);
    }).catch(function () {});
  }

  // ------------------------------------------------------------ secciones
  var secEl = document.getElementById('secciones');
  var abierta = null;
  var MENUS = D.menus;

  function campoTexto(s, clave, etiqueta, opts) {
    opts = opts || {};
    var inp = el(opts.area ? 'textarea' : 'input', { maxlength: opts.max || 2000, rows: opts.area ? (opts.rows || 4) : null, type: opts.type || null, placeholder: opts.ph || null });
    inp.value = s.datos[clave] || '';
    inp.addEventListener('input', function () { s.datos[clave] = inp.value; cambio(); });
    return el('label', { class: 'c-campo' }, [el('span', { text: etiqueta }), inp, opts.ayuda ? el('small', { text: opts.ayuda }) : null]);
  }
  function casilla(checked, texto, fn) {
    var c = el('input', { type: 'checkbox' });
    c.checked = !!checked;
    c.addEventListener('change', function () { fn(c.checked); cambio(); });
    return el('label', { class: 'c-check' }, [c, el('span', { text: texto })]);
  }

  function editorDe(s) {
    var w = el('div', { class: 'c-sec-edit' });
    var tit = el('input', { maxlength: 40 });
    tit.value = s.titulo;
    tit.addEventListener('input', function () { s.titulo = tit.value; li(s).querySelector('.c-sec-nombre').textContent = tit.value || D.secciones[s.tipo].titulo; cambio(); });
    w.appendChild(el('label', { class: 'c-campo' }, [el('span', { text: s.tipo === 'libre' ? 'Título' : 'Nombre en el menú' }), tit]));
    switch (s.tipo) {
      case 'rsvp':
        w.appendChild(campoTexto(s, 'texto', 'Texto de introducción', { area: true, max: 600, rows: 3 }));
        var menus = el('div', { class: 'c-checks' });
        Object.keys(MENUS).forEach(function (m) {
          menus.appendChild(casilla(s.datos.menus.indexOf(m) > -1, MENUS[m], function (on) {
            s.datos.menus = Object.keys(MENUS).filter(function (x) { return x === m ? on : s.datos.menus.indexOf(x) > -1; });
          }));
        });
        w.appendChild(el('fieldset', { class: 'c-fs' }, [el('legend', { text: 'Menús que ofrecéis' }), menus]));
        w.appendChild(casilla(s.datos.asistencia, 'Preguntar si van a la ceremonia, al banquete o a los dos', function (v) { s.datos.asistencia = v; }));
        w.appendChild(casilla(s.datos.bus, 'Preguntar si necesitan autobús', function (v) { s.datos.bus = v; }));
        w.appendChild(campoTexto(s, 'fecha_limite', 'Fecha límite para confirmar (opcional)', { type: 'date' }));
        break;
      case 'hoteles':
        w.appendChild(campoTexto(s, 'texto', 'Introducción', { area: true, max: 800, rows: 2 }));
        var lista = el('div', { class: 'c-hoteles' });
        var pinta = function () {
          lista.textContent = '';
          s.datos.hoteles.forEach(function (ho, i) {
            var f = function (k, et, max, ph) {
              var inp = el('input', { maxlength: max, placeholder: ph || null });
              inp.value = ho[k] || '';
              inp.addEventListener('input', function () { ho[k] = inp.value; cambio(); });
              return el('label', { class: 'c-campo' }, [el('span', { text: et }), inp]);
            };
            var nota = el('textarea', { maxlength: 400, rows: 2 });
            nota.value = ho.nota || '';
            nota.addEventListener('input', function () { ho.nota = nota.value; cambio(); });
            lista.appendChild(el('div', { class: 'c-hotel' }, [
              el('div', { class: 'c-hotel-cab' }, [el('b', { text: 'Hotel ' + (i + 1) }),
                el('button', { type: 'button', class: 'c-link', text: 'Quitar', onclick: function () { s.datos.hoteles.splice(i, 1); pinta(); cambio(); } })]),
              f('nombre', 'Nombre', 100), f('zona', 'Zona o distancia', 100, 'A 5 min de la finca'),
              el('div', { class: 'c-fila' }, [f('web', 'Web', 300, 'https://'), f('telefono', 'Teléfono', 40)]),
              el('label', { class: 'c-campo' }, [el('span', { text: 'Notas (precio, código de descuento…)' }), nota])
            ]));
          });
          if (s.datos.hoteles.length < 8) lista.appendChild(el('button', { type: 'button', class: 'c-btn c-btn-sec', text: '+ Añadir hotel', onclick: function () { s.datos.hoteles.push({ nombre: '', zona: '', web: '', telefono: '', nota: '' }); pinta(); cambio(); } }));
        };
        pinta();
        w.appendChild(lista);
        break;
      case 'regalos':
        w.appendChild(campoTexto(s, 'texto', 'Texto', { area: true, max: 800, rows: 3 }));
        w.appendChild(campoTexto(s, 'titular', 'Titular de la cuenta', { max: 120 }));
        w.appendChild(campoTexto(s, 'iban', 'IBAN', { max: 50, ph: 'ES00 0000 0000 0000 0000 0000', ayuda: 'Comprobamos que el IBAN sea válido: si tiene un error, no se publica.' }));
        w.appendChild(campoTexto(s, 'otro', 'Otras formas de regalo (opcional)', { area: true, max: 600, rows: 2 }));
        break;
      case 'informacion':
        w.appendChild(el('p', { class: 'c-ayuda', text: 'Muestra la ceremonia y el convite con su mapa (los datos del paso 2).' }));
        w.appendChild(campoTexto(s, 'texto', 'Texto adicional (aparcamiento, accesos…)', { area: true, max: 2000 }));
        break;
      default:
        w.appendChild(campoTexto(s, 'texto', 'Texto', { area: true, max: 2000, rows: 5 }));
    }
    if (s.tipo === 'libre') {
      w.appendChild(el('button', { type: 'button', class: 'c-link c-borrar', text: 'Eliminar esta sección', onclick: function () {
        st.secciones = st.secciones.filter(function (x) { return x !== s; });
        pintaSecciones(); cambio();
      } }));
    }
    return w;
  }

  function li(s) { return secEl.querySelector('[data-id="' + s.id + '"]'); }

  function pintaSecciones() {
    secEl.textContent = '';
    st.secciones.forEach(function (s, i) {
      var on = el('input', { type: 'checkbox', class: 'c-switch', 'aria-label': 'Mostrar ' + s.titulo });
      on.checked = s.on !== false;
      on.addEventListener('change', function () { s.on = on.checked; item.classList.toggle('is-off', !s.on); cambio(); });
      var mueve = function (d) {
        return function () {
          var j = i + d;
          if (j < 0 || j >= st.secciones.length) return;
          var x = st.secciones[i]; st.secciones[i] = st.secciones[j]; st.secciones[j] = x;
          pintaSecciones(); cambio();
          var b = li(x).querySelector(d < 0 ? '[data-sube]' : '[data-baja]');
          if (b && !b.disabled) b.focus();
        };
      };
      var abrir = el('button', { type: 'button', class: 'c-sec-abrir', 'aria-expanded': abierta === s.id ? 'true' : 'false' }, [
        el('span', { class: 'c-sec-nombre', text: s.titulo || D.secciones[s.tipo].titulo }),
        s.tipo === 'libre' ? el('span', { class: 'c-sec-tipo', text: 'propia' }) : null
      ]);
      abrir.addEventListener('click', function () { abierta = abierta === s.id ? null : s.id; pintaSecciones(); });
      var item = el('li', { class: 'c-sec' + (s.on === false ? ' is-off' : ''), 'data-id': s.id }, [
        el('div', { class: 'c-sec-fila' }, [
          on, abrir,
          el('div', { class: 'c-orden' }, [
            el('button', { type: 'button', 'data-sube': true, 'aria-label': 'Subir', disabled: i === 0, text: '↑', onclick: mueve(-1) }),
            el('button', { type: 'button', 'data-baja': true, 'aria-label': 'Bajar', disabled: i === st.secciones.length - 1, text: '↓', onclick: mueve(1) })
          ])
        ]),
        abierta === s.id ? editorDe(s) : null
      ]);
      secEl.appendChild(item);
    });
    var libres = st.secciones.filter(function (s) { return s.tipo === 'libre'; }).length;
    document.getElementById('anadirLibre').disabled = libres >= D.maxLibres;
  }
  document.getElementById('anadirLibre').addEventListener('click', function () {
    var id = 's' + Math.random().toString(36).slice(2, 8);
    st.secciones.push({ id: id, tipo: 'libre', on: true, titulo: 'Nueva sección', datos: { texto: '' } });
    abierta = id;
    pintaSecciones(); cambio();
    var n = li({ id: id }).querySelector('.c-sec-edit input');
    if (n) { n.focus(); n.select(); }
  });

  // ------------------------------------------------------------ vista previa
  var iframe = document.getElementById('previa');
  var paginaSel = document.getElementById('paginaSel');
  var marco = document.getElementById('marco');
  var pagina = 'inicio';
  var scrollY = 0;
  var pideN = 0;

  function previa() {
    var n = ++pideN;
    fetch(URL_PREVIA, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ config: st, pagina: pagina }) })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (n !== pideN || !j.ok) return;
        iframe.srcdoc = j.html;
        paginaSel.textContent = '';
        var existe = false;
        j.paginas.forEach(function (p) {
          var o = el('option', { value: p[0], text: p[1] });
          if (p[0] === pagina) { o.selected = true; existe = true; }
          paginaSel.appendChild(o);
        });
        if (!existe) pagina = 'inicio';
        pintaFaltan(j.faltan || {});
      })
      .catch(function () {});
  }
  function enviaFoto() {
    if (iframe.contentWindow) iframe.contentWindow.postMessage({ tipo: 'foto', src: foto.src }, '*');
  }
  window.addEventListener('message', function (e) {
    if (e.source !== iframe.contentWindow) return;
    var d = e.data || {};
    if (d.tipo === 'lista') {
      if (foto.src) enviaFoto();
      iframe.contentWindow.postMessage({ tipo: 'scroll', y: scrollY }, '*');
    }
    if (d.tipo === 'scroll' && typeof d.y === 'number') scrollY = d.y;
    if (d.tipo === 'ir' && typeof d.pagina === 'string') { pagina = d.pagina; scrollY = 0; previa(); }
  });
  paginaSel.addEventListener('change', function () { pagina = paginaSel.value; scrollY = 0; previa(); });

  // Móvil / escritorio: el escritorio se pinta a 1280 px y se escala al hueco
  function ajustaMarco() {
    if (marco.getAttribute('data-disp') !== 'escritorio') { iframe.style.transform = ''; iframe.style.width = ''; iframe.style.height = ''; return; }
    var k = Math.min(1, marco.clientWidth / 1280);
    iframe.style.width = '1280px';
    iframe.style.height = (marco.clientHeight / k) + 'px';
    iframe.style.transform = 'scale(' + k + ')';
  }
  document.querySelectorAll('[data-disp]').forEach(function (b) {
    if (b === marco) return;
    b.addEventListener('click', function () {
      marco.setAttribute('data-disp', b.getAttribute('data-disp'));
      document.querySelectorAll('button[data-disp]').forEach(function (x) { x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
      ajustaMarco();
    });
  });
  window.addEventListener('resize', ajustaMarco);

  // Pantalla estrecha: editor o vista previa, uno cada vez
  document.querySelectorAll('[data-ver]').forEach(function (b) {
    b.addEventListener('click', function () {
      document.body.setAttribute('data-ver', b.getAttribute('data-ver'));
      document.querySelectorAll('[data-ver]').forEach(function (x) { if (x.tagName === 'BUTTON') x.setAttribute('aria-pressed', x === b ? 'true' : 'false'); });
      ajustaMarco();
    });
  });

  // ------------------------------------------------------------ lo que falta
  var faltanEl = document.getElementById('faltan');
  var ultimasFaltas = {};
  function pintaFaltan(f) {
    ultimasFaltas = f;
    document.querySelectorAll('.c-mal').forEach(function (n) { n.classList.remove('c-mal'); });
    Object.keys(f).forEach(function (k) {
      var inp = document.querySelector('[data-k="' + k + '"]');
      if (inp && inp.value) inp.closest('.c-campo').classList.add('c-mal');
    });
  }
  function muestraFaltan(f, extra) {
    faltanEl.textContent = '';
    Object.keys(f).forEach(function (k) { faltanEl.appendChild(el('li', { text: f[k] })); });
    (extra || []).forEach(function (m) { faltanEl.appendChild(el('li', { text: m })); });
    Object.keys(f).forEach(function (k) {
      var inp = document.querySelector('[data-k="' + k + '"]');
      if (inp) inp.closest('.c-campo').classList.add('c-mal');
    });
    var primero = Object.keys(f).map(function (k) { return document.querySelector('[data-k="' + k + '"]'); }).filter(Boolean)[0];
    if (primero) {
      var det = primero.closest('details');
      if (det) det.open = true;
    }
  }

  // ------------------------------------------------------------ nombre de la web
  var slugEl = document.getElementById('slug');
  var slugEstado = document.getElementById('slugEstado');
  var slugT;
  function sugiereSlug() {
    if (!slugEl || slugTocado) return;
    var a = slugify(st.pareja.nombre1), b = slugify(st.pareja.nombre2);
    slugEl.value = a && b ? a + '-y-' + b : (a || b);
    compruebaSlug();
  }
  function compruebaSlug() {
    clearTimeout(slugT);
    var v = slugEl.value;
    if (!v) { slugEstado.textContent = ''; slugEstado.className = ''; return; }
    slugT = setTimeout(function () {
      fetch('/api/nombre?s=' + encodeURIComponent(v)).then(function (r) { return r.json(); }).then(function (j) {
        if (slugEl.value !== v) return;
        slugEstado.textContent = j.libre ? '✓ Disponible' : (j.motivo || 'No disponible');
        slugEstado.className = j.libre ? 'ok' : 'mal';
      }).catch(function () {});
    }, 400);
  }
  if (slugEl) {
    slugEl.addEventListener('input', function () {
      // Mientras se escribe se deja el guion final; se recorta al salir del campo
      var limpio = slugEl.value.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9-]+/g, '-').replace(/-{2,}/g, '-').replace(/^-/, '').slice(0, 40);
      if (limpio !== slugEl.value) slugEl.value = limpio;
      slugTocado = slugEl.value !== '';
      persiste();
      compruebaSlug();
    });
    slugEl.addEventListener('blur', function () { slugEl.value = slugify(slugEl.value); compruebaSlug(); });
    if (slugEl.value) compruebaSlug(); else sugiereSlug();
  }

  // ------------------------------------------------------------ pagar / guardar
  var pagar = document.getElementById('pagar');
  if (pagar) pagar.addEventListener('click', function () {
    var extra = [];
    if (!slugEl.value || slugEstado.className === 'mal') extra.push('Elegid una dirección libre para vuestra web.');
    var c1 = document.getElementById('aceptoCond'), c2 = document.getElementById('aceptoDes');
    if (!c1.checked || !c2.checked) extra.push('Marcad las dos casillas para continuar.');
    if (Object.keys(ultimasFaltas).length || extra.length) { muestraFaltan(ultimasFaltas, extra); return; }
    var fd = new FormData();
    fd.append('config', JSON.stringify(st));
    fd.append('slug', slugEl.value);
    fd.append('acepto_condiciones', 'si');
    fd.append('acepto_desistimiento', 'si');
    if (foto.src) fd.append('foto', foto.blob || dataUrlABlob(foto.src), 'foto.webp');
    pagar.disabled = true;
    var txt = pagar.textContent;
    pagar.textContent = 'Abriendo el pago…';
    fetch('/api/pagar', { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (j.ok && j.url) { location.href = j.url; return; }
        pagar.disabled = false; pagar.textContent = txt;
        muestraFaltan(j.faltan || {}, [j.error || 'No se ha podido abrir el pago.']);
      })
      .catch(function () { pagar.disabled = false; pagar.textContent = txt; muestraFaltan({}, ['Sin conexión. Inténtalo de nuevo.']); });
  });

  var guardar = document.getElementById('guardar');
  if (guardar) guardar.addEventListener('click', function () {
    var est = document.getElementById('guardarEstado');
    if (Object.keys(ultimasFaltas).length) { muestraFaltan(ultimasFaltas); return; }
    faltanEl.textContent = '';
    var fd = new FormData();
    fd.append('config', JSON.stringify(st));
    fd.append('csrf', D.csrf);
    if (foto.blob) fd.append('foto', foto.blob, 'foto.webp');
    if (foto.quitar) fd.append('quitar_foto', 'si');
    guardar.disabled = true;
    est.textContent = 'Guardando…';
    fetch('/panel/guardar', { method: 'POST', body: fd })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        guardar.disabled = false;
        if (j.ok) { est.textContent = 'Guardado. Ya está publicado.'; foto.blob = null; foto.quitar = false; return; }
        est.textContent = '';
        muestraFaltan(j.faltan || {}, [j.error || 'No se ha podido guardar.']);
      })
      .catch(function () { guardar.disabled = false; est.textContent = 'Sin conexión. Inténtalo de nuevo.'; });
  });

  function dataUrlABlob(u) {
    var p = u.split(','), bin = atob(p[1]), a = new Uint8Array(bin.length);
    for (var i = 0; i < bin.length; i++) a[i] = bin.charCodeAt(i);
    return new Blob([a], { type: (p[0].match(/:(.*?);/) || [])[1] || 'image/webp' });
  }

  if (/[?&]cancelado=1/.test(location.search)) muestraFaltan({}, ['Pago cancelado. Vuestro borrador sigue aquí.']);

  pintaFoto();
  pintaSecciones();
  previa();
})();
