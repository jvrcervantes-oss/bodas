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
    pintaCabecera();
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
  // ------------------------------------------------------------ Colección Atelier (+50 €)
  // Un diseño Atelier trae su paleta, letras e ilustración: mientras hay uno elegido, las
  // opciones de estilo propio se atenúan. El precio lo calcula el servidor; aquí solo se enseña.
  var atelierEl = document.getElementById('atelier');
  var propioEl = document.getElementById('estiloPropio');
  function pintaAtelier() {
    atelierEl.querySelectorAll('input').forEach(function (r) { r.checked = (st.atelier || '') === r.value; });
    propioEl.classList.toggle('is-off', !!st.atelier);
    var t = document.getElementById('precioTotal');
    if (t) {
      t.textContent = st.atelier ? D.precio.totalAtelier : D.precio.total;
      document.getElementById('precioDesglose').textContent = (st.atelier ? 'Pack Atelier' : 'Pack Esencial') + ' · IVA incluido · pago único';
      var pb = document.getElementById('pagar'); if (pb && !pb.disabled) pb.textContent = 'Pagar ' + t.textContent;
    }
  }
  function tarjetaAtelier(k, a) {
    var r = el('input', { type: 'radio', name: 'atelier', value: k, disabled: k !== '' && !D.atelierPermitido });
    r.addEventListener('change', function () { st.atelier = k; pintaAtelier(); cambio(); });
    var muestra = k ? el('img', { class: 'c-atelier-img', src: '/assets/img/atelier/muestra-' + k + '.webp', alt: '', loading: 'lazy' })
      : el('span', { class: 'c-atelier-img c-atelier-propio', text: 'Aa' });
    return el('label', { class: 'c-atelier-card' + (r.disabled ? ' is-bloq' : '') }, [r, muestra,
      el('span', { class: 'c-atelier-txt' }, [
        el('small', { text: a.categoria }),
        el('b', { text: a.nombre }),
        el('span', { text: a.desc })
      ])]);
  }
  var pedido = (location.search.match(/[?&]atelier=([a-z]+)/) || [])[1];
  if (MODO === 'crear' && pedido && D.atelier[pedido]) st.atelier = pedido;
  atelierEl.appendChild(tarjetaAtelier('', { nombre: 'Vuestro estilo', categoria: 'Incluido', desc: 'Paleta, letra y adornos a vuestro gusto.' }));
  Object.keys(D.atelier).forEach(function (k) { atelierEl.appendChild(tarjetaAtelier(k, D.atelier[k])); });
  if (!D.atelierPermitido) atelierEl.appendChild(el('p', { class: 'c-ayuda c-atelier-nota', text: 'Los diseños Atelier se eligen al crear la web. Si queréis cambiar a uno, escribidnos.' }));
  pintaAtelier();

  var temasEl = document.getElementById('temas');
  Object.keys(D.temas).forEach(function (k) {
    var tm = D.temas[k];
    var r = el('input', { type: 'radio', name: 'tema', value: k });
    r.checked = st.tema === k;
    r.addEventListener('change', function () { st.tema = k; cambio(); });
    var sw = el('span', { class: 'c-tema-sw' });
    sw.style.background = tm.color;
    var muestra = el('span', { class: 'c-tema-muestra', text: 'Aa' });
    muestra.style.background = tm.fondo;
    muestra.style.color = tm.titulo;
    temasEl.appendChild(el('label', { class: 'c-tema' }, [r, muestra, el('span', { class: 'c-tema-nom' }, [sw, el('span', { text: tm.nombre })])]));
  });

  // ------------------------------------------------------------ tipografía
  var fuentesEl = document.getElementById('fuentes');
  Object.keys(D.fuentes).forEach(function (k) {
    var f = D.fuentes[k];
    var r = el('input', { type: 'radio', name: 'fuente', value: k });
    r.checked = (st.fuente || 'clasica') === k;
    r.addEventListener('change', function () { st.fuente = k; cambio(); });
    var muestra = el('span', { class: 'c-fuente-muestra', text: 'Lucía & Marcos' });
    muestra.style.fontFamily = f.nombres;
    muestra.style.fontStyle = f.estilo;
    fuentesEl.appendChild(el('label', { class: 'c-fuente' }, [r, muestra, el('small', { text: f.nombre })]));
  });

  // ------------------------------------------------------------ convite en el mismo sitio
  var convMismo = document.getElementById('convMismo');
  function pintaMismo() {
    document.querySelectorAll('[data-si-otro-sitio]').forEach(function (n) { n.hidden = !!st.convite.mismo; });
  }
  convMismo.checked = !!st.convite.mismo;
  convMismo.addEventListener('change', function () { st.convite.mismo = convMismo.checked; pintaMismo(); cambio(); });
  pintaMismo();

  // ------------------------------------------------------------ decoración
  var decosEl = document.getElementById('decos');
  Object.keys(D.decoraciones).forEach(function (k) {
    var dc = D.decoraciones[k];
    var r = el('input', { type: 'radio', name: 'deco', value: k });
    r.checked = (st.decoracion || 'eucalipto') === k;
    r.addEventListener('change', function () { st.decoracion = k; cambio(); });
    decosEl.appendChild(el('label', { class: 'c-deco' }, [r, el('span', { class: 'c-deco-muestra c-deco-' + k }),
      el('span', { class: 'c-deco-txt' }, [el('b', { text: dc.nombre }), el('small', { text: dc.desc })])]));
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
    document.getElementById('fotoNom').textContent = hay ? 'Foto de portada elegida' : 'Sin foto';
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
  var idNuevo = function (p) { return p + Math.random().toString(36).slice(2, 8); };

  // Borradores guardados antes del 25-sep: menús como claves fijas y el autobús en la
  // confirmación. Se pasan al formato nuevo (mismos ids) para que el editor los entienda.
  (function migraBorrador() {
    var r = st.secciones.filter(function (x) { return x.tipo === 'rsvp'; })[0];
    if (r) { r.on = true; st.secciones = [r].concat(st.secciones.filter(function (x) { return x !== r; })); }
    var ANT = { carne: 'Carne', pescado: 'Pescado', vegetariano: 'Vegetariano', vegano: 'Vegano', infantil: 'Infantil' };
    var busAntiguo = null;
    st.secciones.forEach(function (x) {
      if (x.tipo === 'rsvp') {
        x.datos.menus = (x.datos.menus || []).map(function (m) {
          return typeof m === 'string' ? { id: m, nombre: ANT[m] || m, descripcion: '', infantil: m === 'infantil' } : m;
        });
        if ('bus' in x.datos) { busAntiguo = !!x.datos.bus; delete x.datos.bus; }
      }
    });
    st.secciones.forEach(function (x) {
      if (x.tipo === 'transporte') {
        x.datos.trayectos = x.datos.trayectos || [];
        if (typeof x.datos.preguntar !== 'boolean') x.datos.preguntar = busAntiguo === null ? true : busAntiguo;
      }
    });
  })();

  // Lista editable genérica (menús, trayectos): cada fila con subir/bajar/quitar
  function listaEditable(arr, opts) {
    var caja = el('div', { class: 'c-lista' });
    function pinta() {
      caja.textContent = '';
      arr.forEach(function (it, i) {
        var mover = function (d) { return function () { var j = i + d; if (j < 0 || j >= arr.length) return; var x = arr[i]; arr[i] = arr[j]; arr[j] = x; pinta(); cambio(); }; };
        var cab = el('b', { text: opts.titulo(it, i) });
        var refresca = function () { cab.textContent = opts.titulo(it, i); };
        caja.appendChild(el('div', { class: 'c-item' }, [
          el('div', { class: 'c-item-cab' }, [
            cab,
            el('span', { class: 'c-item-acc' }, [
              el('button', { type: 'button', class: 'c-mini', 'aria-label': 'Subir', disabled: i === 0, text: '↑', onclick: mover(-1) }),
              el('button', { type: 'button', class: 'c-mini', 'aria-label': 'Bajar', disabled: i === arr.length - 1, text: '↓', onclick: mover(1) }),
              (arr.length > (opts.min || 0)) ? el('button', { type: 'button', class: 'c-link c-link-mal', text: 'Quitar', onclick: function () { arr.splice(i, 1); pinta(); cambio(); } }) : null
            ])
          ])
        ].concat(opts.campos(it, refresca))));
      });
      if (arr.length < opts.max) caja.appendChild(el('button', { type: 'button', class: 'b-btn b-paper c-btn-sm', text: opts.anadir, onclick: function () {
        arr.push(opts.nuevo()); pinta(); cambio();
        var ins = caja.querySelectorAll('.c-item'); var ult = ins[ins.length - 1]; var f = ult && ult.querySelector('input');
        if (f) f.focus();
      } }));
    }
    pinta();
    return caja;
  }
  function campoDe(obj, k, etiqueta, opts) {
    opts = opts || {};
    var inp = el(opts.area ? 'textarea' : 'input', { maxlength: opts.max || 200, rows: opts.area ? (opts.rows || 2) : null, type: opts.type || null, placeholder: opts.ph || null });
    inp.value = obj[k] || '';
    inp.addEventListener('input', function () { obj[k] = inp.value; if (opts.alCambiar) opts.alCambiar(); cambio(); });
    return el('label', { class: 'c-campo' + (opts.clase ? ' ' + opts.clase : '') }, [el('span', { text: etiqueta }), inp]);
  }

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
    tit.addEventListener('input', function () { s.titulo = tit.value; var n = li(s); if (n) n.querySelector('.c-sec-nombre').textContent = tit.value || D.secciones[s.tipo].titulo; cambio(); });
    w.appendChild(el('label', { class: 'c-campo' }, [el('span', { text: s.tipo === 'libre' ? 'Título' : 'Nombre en el menú' }), tit]));
    switch (s.tipo) {
      case 'rsvp':
        w.appendChild(campoTexto(s, 'texto', 'Texto de introducción', { area: true, max: 600, rows: 3 }));
        w.appendChild(el('fieldset', { class: 'c-fs' }, [
          el('legend', { text: 'Menús que ofrecéis' }),
          el('p', { class: 'c-ayuda', text: 'Cada invitado elige uno. Si describís los platos, también salen en «Información».' }),
          listaEditable(s.datos.menus, {
            max: D.maxMenus, min: 1, anadir: '+ Añadir menú',
            titulo: function (m, i) { return m.nombre || 'Menú ' + (i + 1); },
            nuevo: function () { return { id: idNuevo('m'), nombre: '', descripcion: '', infantil: false }; },
            campos: function (m, refresca) {
              return [
                campoDe(m, 'nombre', 'Nombre', { max: 40, ph: 'Ej.: Sin gluten', alCambiar: refresca }),
                campoDe(m, 'descripcion', 'Platos (opcional)', { area: true, max: 300, ph: 'Entrante, principal y postre' }),
                casilla(m.infantil, 'Es el menú de los niños (sale marcado por defecto para ellos)', function (v) { m.infantil = v; })
              ];
            }
          })
        ]));
        w.appendChild(casilla(s.datos.asistencia, 'Preguntar si van a la ceremonia, al banquete o a los dos', function (v) { s.datos.asistencia = v; }));
        var tr = st.secciones.filter(function (x) { return x.tipo === 'transporte'; })[0];
        if (tr) w.appendChild(el('p', { class: 'c-ayuda' }, [
          document.createTextNode('La pregunta del autobús se configura en '),
          el('button', { type: 'button', class: 'c-link', text: tr.titulo || 'Transporte', onclick: function () { abierta = tr.id; muestraTab('secciones'); pintaSecciones(); var n = li(tr); if (n) n.scrollIntoView({ behavior: 'smooth', block: 'start' }); } }),
          document.createTextNode('.')
        ]));
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
          if (s.datos.hoteles.length < 8) lista.appendChild(el('button', { type: 'button', class: 'b-btn b-paper c-btn-sm', text: '+ Añadir hotel', onclick: function () { s.datos.hoteles.push({ nombre: '', zona: '', web: '', telefono: '', nota: '' }); pinta(); cambio(); } }));
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
      case 'transporte':
        w.appendChild(campoTexto(s, 'texto', 'Introducción (opcional)', { area: true, max: 2000, rows: 3 }));
        w.appendChild(el('fieldset', { class: 'c-fs' }, [
          el('legend', { text: 'Trayectos' }),
          listaEditable(s.datos.trayectos, {
            max: D.maxTrayectos, anadir: '+ Añadir trayecto',
            titulo: function (t, i) { return t.titulo || 'Trayecto ' + (i + 1); },
            nuevo: function () { return { titulo: '', salida: '', hora: '', llegada: '', nota: '' }; },
            campos: function (t, refresca) {
              return [
                el('div', { class: 'c-fila' }, [campoDe(t, 'titulo', 'Nombre', { max: 60, ph: 'Ida a la finca', clase: 'c-crece', alCambiar: refresca }), campoDe(t, 'hora', 'Hora', { type: 'time', clase: 'c-hora' })]),
                campoDe(t, 'salida', 'Punto de salida', { max: 140, ph: 'Puerta de la iglesia' }),
                campoDe(t, 'llegada', 'Llegada (opcional)', { max: 140 }),
                campoDe(t, 'nota', 'Nota (opcional)', { area: true, max: 300, ph: 'Vuelta a las 2:00 y a las 4:00' })
              ];
            }
          })
        ]));
        w.appendChild(casilla(s.datos.preguntar, 'Preguntar en la confirmación si necesitan autobús', function (v) { s.datos.preguntar = v; }));
        break;
      case 'galeria':
        w.appendChild(campoCodigo());
        w.appendChild(campoTexto(s, 'texto', 'Introducción (opcional)', { area: true, max: 600, rows: 2 }));
        w.appendChild(editorGaleria(s));
        break;
      case 'libro':
        w.appendChild(campoCodigo());
        w.appendChild(campoTexto(s, 'texto', 'Introducción', { area: true, max: 600, rows: 2 }));
        w.appendChild(casilla(s.datos.fotos, 'Los invitados pueden subir una foto con su mensaje', function (v) { s.datos.fotos = v; }));
        w.appendChild(el('p', { class: 'c-ayuda', text: 'Los mensajes se publican al momento. Desde vuestro panel podéis ocultar o borrar cualquiera.' }));
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

  var ICO = {
    rsvp: 'M3 6h18v12H3zM3 7l9 6 9-6', informacion: 'M12 21s-6-5.3-6-10a6 6 0 0 1 12 0c0 4.7-6 10-6 10zM12 13a2 2 0 1 0 0-4 2 2 0 0 0 0 4z',
    hoteles: 'M3 18V7M3 14h18v4M21 14v-2a3 3 0 0 0-3-3h-7v5', transporte: 'M4 3h16v14H4zM4 11h16M7 17v3M17 17v3',
    regalos: 'M3 8h18v4H3zM5 12v8h14v-8M12 8v12', musica: 'M9 18V5l11-2v13M9 18a3 3 0 1 1-6 0 3 3 0 0 1 6 0zM20 16a3 3 0 1 1-6 0 3 3 0 0 1 6 0z',
    dresscode: 'M12 7a2 2 0 1 1 2-2c0 1-2 1.5-2 3M12 8 3 16h18z', libre: 'M5 4h14v16H5zM8 8h8M8 12h8M8 16h5',
    galeria: 'M3 5h18v14H3zM3 15l5-5 4 4 3-3 6 6', libro: 'M4 4h11a3 3 0 0 1 3 3v13H7a3 3 0 0 1-3-3zM8 9h6M8 13h4'
  };
  function icono(tipo) {
    var n = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    n.setAttribute('viewBox', '0 0 24 24'); n.setAttribute('class', 'i i-sm c-sec-ico'); n.setAttribute('aria-hidden', 'true');
    var p = document.createElementNS('http://www.w3.org/2000/svg', 'path'); p.setAttribute('d', ICO[tipo] || ICO.libre);
    n.appendChild(p);
    return n;
  }
  // Código de acceso a galería y libro: uno por boda, compartido por las dos secciones
  function campoCodigo() {
    var inp = el('input', { maxlength: 20, placeholder: 'Ej.: 1205 o LUCIAYMARCOS', autocomplete: 'off', spellcheck: 'false' });
    inp.value = st.codigo || '';
    inp.addEventListener('input', function () {
      inp.value = inp.value.replace(/[^A-Za-z0-9-]/g, '');
      st.codigo = inp.value;
      document.querySelectorAll('.c-codigo input').forEach(function (o) { if (o !== inp) o.value = inp.value; });
      cambio();
    });
    return el('label', { class: 'c-campo c-codigo' }, [el('span', { text: 'Código para los invitados' }), inp,
      el('small', { text: 'Ponedlo en la invitación. Lo piden la galería y el libro, para que solo entren vuestros invitados. Mínimo 4 caracteres.' })]);
  }

  // Galería: solo se sube desde el panel (tras publicar); en el creador no se sube nada
  function editorGaleria(s) {
    var caja = el('div', { class: 'c-galeria' });
    if (MODO !== 'editar') {
      caja.appendChild(el('p', { class: 'c-aviso', text: 'Las fotos de la galería se suben desde vuestro panel en cuanto publiquéis la web.' }));
      return caja;
    }
    var aviso = el('p', { class: 'c-nota', 'aria-live': 'polite' });
    var consent = null;
    if (!s.datos.consentido) {
      consent = el('input', { type: 'checkbox' });
      caja.appendChild(el('label', { class: 'c-check' }, [consent, el('span', { text: D.checkGaleria })]));
    }
    var lista = el('div', { class: 'c-galeria-lista' });
    function pinta() {
      lista.textContent = '';
      s.datos.fotos.forEach(function (f, i) {
        var pie = el('input', { maxlength: 140, placeholder: 'Pie de foto (opcional)' });
        pie.value = f.pie || '';
        pie.addEventListener('input', function () { f.pie = pie.value; cambio(); });
        var mover = function (d) { return function () { var j = i + d; if (j < 0 || j >= s.datos.fotos.length) return; var x = s.datos.fotos[i]; s.datos.fotos[i] = s.datos.fotos[j]; s.datos.fotos[j] = x; pinta(); cambio(); }; };
        lista.appendChild(el('div', { class: 'c-galeria-item' }, [
          el('img', { src: '/g/' + f.id + '.webp?t=' + (f.t || ''), alt: '' }),
          pie,
          el('span', { class: 'c-item-acc' }, [
            el('button', { type: 'button', class: 'c-mini', 'aria-label': 'Antes', disabled: i === 0, text: '←', onclick: mover(-1) }),
            el('button', { type: 'button', class: 'c-mini', 'aria-label': 'Después', disabled: i === s.datos.fotos.length - 1, text: '→', onclick: mover(1) }),
            el('button', { type: 'button', class: 'c-link c-link-mal', text: 'Quitar', onclick: function () { s.datos.fotos.splice(i, 1); pinta(); cambio(); aviso.textContent = 'Pulsad «Guardar» para quitarla de la web.'; } })
          ])
        ]));
      });
    }
    var input = el('input', { type: 'file', accept: 'image/jpeg,image/png,image/webp', multiple: true, hidden: true });
    input.addEventListener('change', function () {
      var files = Array.prototype.slice.call(input.files);
      input.value = '';
      if (consent && !consent.checked) { aviso.textContent = 'Marcad antes la casilla de permisos.'; return; }
      (function sube() {
        var f = files.shift();
        if (!f) { aviso.textContent = 'Fotos subidas. Ya están en la web.'; return; }
        if (s.datos.fotos.length >= D.maxGaleria) { aviso.textContent = 'La galería admite ' + D.maxGaleria + ' fotos como máximo.'; return; }
        aviso.textContent = 'Subiendo ' + f.name + '…';
        var fd = new FormData();
        fd.append('csrf', D.csrf); fd.append('foto', f); fd.append('consentido', 'si');
        fetch('/panel/galeria', { method: 'POST', body: fd }).then(function (r) { return r.json(); }).then(function (j) {
          if (!j.ok) { aviso.textContent = j.error || 'No se ha podido subir.'; return; }
          s.datos.consentido = true;
          s.datos.fotos.push({ id: j.id, pie: '', t: (j.src.split('t=')[1] || '') });
          pinta(); cambio(); sube();
        }).catch(function () { aviso.textContent = 'Sin conexión. Inténtalo de nuevo.'; });
      })();
    });
    caja.appendChild(lista);
    caja.appendChild(el('label', { class: 'b-btn b-paper c-btn-sm' }, [document.createTextNode('+ Subir fotos'), input]));
    caja.appendChild(aviso);
    pinta();
    return caja;
  }

  function li(s) { return secEl.querySelector('[data-id="' + s.id + '"]'); }

  function pintaSecciones() {
    secEl.textContent = '';
    // La confirmación tiene su propio paso; aquí solo las opcionales, sin reordenar (owner, 25-sep:
    // «hazlo sencillo»). El orden del menú de la web es el de esta lista.
    st.secciones.forEach(function (s) {
      if (s.tipo === 'rsvp') return;
      var on = el('input', { type: 'checkbox', class: 'c-switch', 'aria-label': 'Mostrar ' + s.titulo });
      on.checked = s.on !== false;
      on.addEventListener('change', function () { s.on = on.checked; item.classList.toggle('is-off', !s.on); cambio(); });
      var abrir = el('button', { type: 'button', class: 'c-sec-abrir', 'aria-expanded': abierta === s.id ? 'true' : 'false' }, [
        icono(s.tipo),
        el('span', { class: 'c-sec-nombre', text: s.titulo || D.secciones[s.tipo].titulo }),
        s.tipo === 'libre' ? el('span', { class: 'c-sec-tipo', text: 'propia' }) : null
      ]);
      abrir.addEventListener('click', function () {
        abierta = abierta === s.id ? null : s.id;
        pintaSecciones();
        if (abierta) {
          irAPagina(rutaDe[s.id]);   // la vista previa enseña la página que se edita
          // y el editor se desplaza a la sección abierta para verla entera (owner, 25-sep)
          var n = li(s);
          if (n) n.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });
      var item = el('li', { class: 'c-sec' + (s.on === false ? ' is-off' : ''), 'data-id': s.id }, [
        el('div', { class: 'c-sec-fila' }, [abrir, on]),
        abierta === s.id ? editorDe(s) : null
      ]);
      secEl.appendChild(item);
    });
    var libres = st.secciones.filter(function (s) { return s.tipo === 'libre'; }).length;
    document.getElementById('anadirLibre').disabled = libres >= D.maxLibres;
    pintaRsvp();
  }
  // La confirmación se edita en su propio paso (se repinta solo si no se está escribiendo en él)
  var rsvpEl = document.getElementById('rsvpEditor');
  function pintaRsvp() {
    if (rsvpEl.contains(document.activeElement)) return;
    var r = st.secciones.filter(function (x) { return x.tipo === 'rsvp'; })[0];
    rsvpEl.textContent = '';
    if (r) rsvpEl.appendChild(editorDe(r));
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
  var rutaDe = {}, idDe = {};   // id de sección <-> ruta de su página (del último render)
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
        rutaDe = {}; idDe = {};
        j.paginas.forEach(function (p) {
          if (p[2]) { rutaDe[p[2]] = p[0]; idDe[p[0]] = p[2]; }
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
    if (d.tipo === 'ir' && typeof d.pagina === 'string') { irAPagina(d.pagina); sigueEnEditor(d.pagina); }
  });
  paginaSel.addEventListener('change', function () { irAPagina(paginaSel.value); sigueEnEditor(paginaSel.value); });

  function irAPagina(ruta) {
    if (!ruta || ruta === pagina) return;
    pagina = ruta; scrollY = 0; previa();
  }
  // Navegar en la vista previa mueve el configurador a lo que se está viendo: la página de
  // una sección abre esa sección; la portada lleva a «Portada y estilo» (salvo que ya se
  // esté en una pestaña de la portada). En móvil no se cambia de vista: solo se deja listo.
  function sigueEnEditor(ruta) {
    var id = idDe[ruta];
    var sRsvp = st.secciones.filter(function (x) { return x.tipo === 'rsvp'; })[0];
    if (id && sRsvp && id === sRsvp.id) {
      muestraTab('rsvp', true);
    } else if (id) {
      abierta = id;
      muestraTab('secciones', true);
      pintaSecciones();
      var n = li({ id: id });
      if (n) {
        if (document.body.getAttribute('data-ver') !== 'previa') n.scrollIntoView({ behavior: 'smooth', block: 'start' });
        n.classList.remove('c-destaca'); void n.offsetWidth; n.classList.add('c-destaca');
      }
    } else if (ruta === 'inicio') {
      var actual = document.querySelector('[data-tab][aria-selected="true"]');
      var k = actual && actual.getAttribute('data-tab');
      if (['estilo', 'pareja', 'lugares', 'portada'].indexOf(k) < 0) muestraTab('portada', true);
    }
  }

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
      ajustaMarco();
      window.scrollTo(0, 0);
    });
  });

  // ------------------------------------------------------------ pestañas
  var tabs = document.querySelectorAll('[data-tab]');
  function muestraTab(k, sinCambiarVista) {
    tabs.forEach(function (t) { t.setAttribute('aria-selected', t.getAttribute('data-tab') === k ? 'true' : 'false'); });
    document.querySelectorAll('[data-panel]').forEach(function (p) { p.hidden = p.getAttribute('data-panel') !== k; });
    if (!sinCambiarVista) document.body.setAttribute('data-ver', 'editor');
  }
  // Y al revés: cambiar de pestaña lleva la vista previa a la página que esa pestaña edita
  function paginaDeTab(k) {
    if (k === 'lugares') { var inf = st.secciones.filter(function (x) { return x.tipo === 'informacion' && x.on !== false; })[0]; return inf && rutaDe[inf.id] || 'inicio'; }
    if (k === 'secciones') return abierta && rutaDe[abierta] || null;
    if (k === 'rsvp') { var r = st.secciones.filter(function (x) { return x.tipo === 'rsvp'; })[0]; return r && rutaDe[r.id] || null; }
    if (k === 'estilo' || k === 'pareja' || k === 'portada') return 'inicio';
    return null;
  }
  // Pestañas de arriba y pasos del carril (al crear) manejan lo mismo
  tabs.forEach(function (t) {
    t.addEventListener('click', function () { var k = t.getAttribute('data-tab'); muestraTab(k); irAPagina(paginaDeTab(k)); });
  });
  var tabsArriba = document.querySelectorAll('[role="tab"][data-tab]');
  tabsArriba.forEach(function (t, i) {
    t.addEventListener('keydown', function (e) {
      var d = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0;
      if (!d) return;
      var n = tabsArriba[(i + d + tabsArriba.length) % tabsArriba.length];
      n.focus(); n.click();
    });
  });
  var irPublicar = document.getElementById('irPublicar');
  if (irPublicar) irPublicar.addEventListener('click', function () {
    muestraTab('publicar');
    var destino = [document.querySelector('.c-paso[data-tab="publicar"]'), document.getElementById('tab-publicar')].filter(function (x) { return x && x.offsetParent; })[0];
    if (destino) destino.focus();
  });
  var guardarTop = document.getElementById('guardarTop');
  if (guardarTop) guardarTop.addEventListener('click', function () { document.getElementById('guardar').click(); });

  // Nombres en la tarjeta del proyecto y en el carril; la URL en la barra de la vista previa
  var MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
  function pintaCabecera() {
    var a = (st.pareja.nombre1 || '').trim(), b = (st.pareja.nombre2 || '').trim();
    var nom = a && b ? a + ' & ' + b : (a || b);
    document.getElementById('proyNom').textContent = nom ? 'Boda de ' + nom : (MODO === 'editar' ? 'Vuestra web' : 'Estudio de edición');
    document.getElementById('railNom').textContent = nom || 'Vuestra boda';
    document.getElementById('railIni').textContent = ((a[0] || '') + (b[0] || '')).toUpperCase() || '·';
    var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(st.fecha || '');
    if (m) document.getElementById('railFecha').textContent = (+m[3]) + ' de ' + MESES[+m[2] - 1] + ' de ' + m[1];
  }
  function pintaUrl() {
    var v = MODO === 'editar' ? D.slug : (slugEl && slugEl.value) || 'vuestra-web';
    document.getElementById('urlPrevia').textContent = v + '.' + D.dominio;
  }

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
    // El aviso sale junto al botón (Publicar / Guardar); los campos quedan marcados en su pestaña
  }

  // ------------------------------------------------------------ nombre de la web
  var slugEl = document.getElementById('slug');
  var slugEstado = document.getElementById('slugEstado');
  var slugT;
  function sugiereSlug() {
    if (!slugEl || slugTocado) return;
    var a = slugify(st.pareja.nombre1), b = slugify(st.pareja.nombre2);
    slugEl.value = a && b ? a + '-y-' + b : (a || b);
    pintaUrl();
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
      pintaUrl();
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
  pintaCabecera();
  pintaUrl();
  previa();
})();
