// Web de boda (plantilla del producto). Base: assets/js/main.js de EduCora.
// Todo lo propio de cada boda llega por atributos data-* del HTML que genera
// app/render.php; aquí no hay ni una fecha ni un nombre.
(function () {
  'use strict';
  var PREVIA = document.body.classList.contains('is-preview');

  // ---------- Reveal on scroll ----------
  function initReveal() {
    var els = document.querySelectorAll('.rv');
    if (!els.length) return;
    if (!('IntersectionObserver' in window)) {
      els.forEach(function (el) { el.classList.add('in'); });
      return;
    }
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('in');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.15 });
    els.forEach(function (el) { io.observe(el); });
  }

  // ---------- Menú a pantalla completa (móvil) ----------
  // .site-nav.open muestra #siteMenu; se cierra con la X, al elegir un
  // enlace o con Escape, y mientras está abierto el fondo no hace scroll.
  function initNav() {
    var nav = document.querySelector('.site-nav');
    if (!nav) return;
    var btn = nav.querySelector('.nav-toggle');
    if (!btn) return;
    var closeBtn = nav.querySelector('[data-nav-close]');
    function setOpen(open) {
      nav.classList.toggle('open', open);
      document.body.classList.toggle('nav-lock', open);
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (open && closeBtn) closeBtn.focus();
      if (!open) btn.focus();
    }
    btn.addEventListener('click', function () { setOpen(!nav.classList.contains('open')); });
    if (closeBtn) closeBtn.addEventListener('click', function () { setOpen(false); });
    nav.querySelectorAll('#siteMenu a').forEach(function (a) {
      a.addEventListener('click', function () {
        nav.classList.remove('open');
        document.body.classList.remove('nav-lock');
      });
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && nav.classList.contains('open')) setOpen(false);
    });
  }

  // ---------- Confirmación por grupo: una ficha por persona ----------
  // La ficha 0 ("Tú") está en el HTML; el resto se clona de #guestTpl. Los `name`
  // se renumeran (invitados[i][campo]) al añadir o quitar; los `id` salen de un
  // contador que nunca se reutiliza, así un label nunca apunta a otra ficha.
  function initGuests() {
    var list = document.getElementById('guestList');
    var tpl = document.getElementById('guestTpl');
    var form = document.getElementById('rsvpForm');
    if (!list || !tpl || !form) return;
    var submit = document.getElementById('rsvpSubmit');
    var menuDef = { adulto: form.getAttribute('data-menu-adulto'), nino: form.getAttribute('data-menu-nino') };
    var uid = 1;
    var MAX = 15;
    var counters = { adulto: 0, nino: 0 };

    function renumber() {
      var cards = list.querySelectorAll('.guest');
      var n = { adulto: 0, nino: 0 };
      cards.forEach(function (card, i) {
        card.querySelectorAll('[data-f]').forEach(function (el) {
          el.name = 'invitados[' + i + '][' + el.getAttribute('data-f') + ']';
        });
        if (i === 0) return;
        var kind = card.getAttribute('data-kind');
        n[kind]++;
        card.querySelector('.guest-tag').textContent = (kind === 'nino' ? 'Niño/a ' : 'Adulto ') + n[kind];
      });
      if (submit) submit.textContent = cards.length > 1 ? '¡Allí estaremos!' : '¡Allí estaré!';
      document.querySelectorAll('[data-add-guest]').forEach(function (b) { b.disabled = cards.length >= MAX; });
      legal();
    }

    // Casillas legales: la de acompañantes aparece con más de una persona, la de
    // alergias en cuanto alguien escribe una. El servidor exige lo mismo.
    var boxGrupo = form.querySelector('[data-si-grupo]');
    var boxAlerg = form.querySelector('[data-si-alergias]');
    function legal() {
      var n = list.querySelectorAll('.guest').length;
      var alg = Array.prototype.some.call(list.querySelectorAll('[data-f="alergias"]'), function (i) { return i.value.trim() !== ''; });
      if (boxGrupo) boxGrupo.hidden = n < 2;
      if (boxAlerg) boxAlerg.hidden = !alg;
    }
    list.addEventListener('input', legal);

    function add(kind) {
      if (list.querySelectorAll('.guest').length >= MAX) return;
      var card = tpl.content.firstElementChild.cloneNode(true);
      var id = 'g' + (uid++);
      card.setAttribute('data-kind', kind);
      card.querySelector('[data-f="tipo"]').value = kind;
      card.querySelectorAll('[data-id]').forEach(function (el) { el.id = id + '-' + el.getAttribute('data-id'); });
      card.querySelectorAll('[data-for]').forEach(function (el) { el.htmlFor = id + '-' + el.getAttribute('data-for'); });
      var def = card.querySelector('input[type="radio"][data-f="menu"][value="' + menuDef[kind] + '"]');
      if (def) { def.checked = true; def.defaultChecked = true; }
      card.querySelector('.guest-remove').addEventListener('click', function () {
        card.remove();
        renumber();
        var last = list.querySelectorAll('.guest');
        last[last.length - 1].querySelector('[data-f="nombre"]').focus();
      });
      list.appendChild(card);
      renumber();
      card.querySelector('[data-f="nombre"]').focus();
    }

    document.querySelectorAll('[data-add-guest]').forEach(function (b) {
      b.addEventListener('click', function () { add(b.getAttribute('data-add-guest')); });
    });
    // Tras enviar, form.reset() dispara 'reset': se quitan las fichas añadidas
    form.addEventListener('reset', function () {
      setTimeout(function () {
        list.querySelectorAll('.guest').forEach(function (c, i) { if (i > 0) c.remove(); });
        renumber();
      }, 0);
    });
    renumber();
  }

  // ---------- Añadir al calendario ----------
  function initCalendar() {
    var btn = document.getElementById('calBtn');
    var box = document.getElementById('calOptions');
    if (!btn || !box) return;
    btn.addEventListener('click', function () {
      var open = box.hasAttribute('hidden');
      if (open) box.removeAttribute('hidden'); else box.setAttribute('hidden', '');
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
  }

  // ---------- Countdown real a la fecha de la boda ----------
  // Ceremonia: 01/05/2027 12:00 (hora local del navegador del invitado)
  function initCountdown() {
    var root = document.getElementById('countdown');
    if (!root) return;
    var obj = root.getAttribute('data-objetivo') || '';
    var m = obj.match(/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})/);
    if (!m) return;
    // Hora local del navegador del invitado (igual que EduCora)
    var target = new Date(+m[1], +m[2] - 1, +m[3], +m[4], +m[5], 0).getTime();
    var days = root.querySelector('[data-c="days"]');
    var hours = root.querySelector('[data-c="hours"]');
    var mins = root.querySelector('[data-c="mins"]');
    var secs = root.querySelector('[data-c="secs"]');

    function tick() {
      var diff = target - Date.now();
      if (diff <= 0) {
        days.textContent = hours.textContent = mins.textContent = secs.textContent = '0';
        clearInterval(timer);
        return;
      }
      var d = Math.floor(diff / 86400000);
      var h = Math.floor((diff % 86400000) / 3600000);
      var m = Math.floor((diff % 3600000) / 60000);
      var s = Math.floor((diff % 60000) / 1000);
      function two(n) { return n < 10 ? '0' + n : String(n); }
      days.textContent = two(d);
      hours.textContent = two(h);
      mins.textContent = two(m);
      secs.textContent = two(s);
    }
    tick();
    var timer = setInterval(tick, 1000);
  }

  // ---------- Pestañas (Información) ----------
  function initTabs() {
    var tabGroups = document.querySelectorAll('[data-tabs]');
    tabGroups.forEach(function (group) {
      var buttons = group.querySelectorAll('.tab-btn');
      buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
          var targetId = btn.getAttribute('data-target');
          buttons.forEach(function (b) { b.setAttribute('aria-selected', 'false'); });
          btn.setAttribute('aria-selected', 'true');
          group.querySelectorAll('.tab-panel').forEach(function (p) { p.classList.remove('active'); });
          document.getElementById(targetId).classList.add('active');
          history.replaceState(null, '', '#' + targetId);
        });
      });
      var fromHash = location.hash ? location.hash.slice(1) : null;
      if (fromHash) {
        var match = group.querySelector('[data-target="' + fromHash + '"]');
        if (match) match.click();
      }
    });
  }

  // ---------- Validación + envío de formularios (fetch a api/*.php) ----------
  function showFieldError(field, msg) {
    var wrap = field.closest('.field');
    var err = wrap.querySelector('.field-error');
    if (!err) {
      err = document.createElement('div');
      err.className = 'field-error';
      wrap.appendChild(err);
    }
    err.textContent = msg;
  }
  function clearFieldError(field) {
    var wrap = field.closest('.field');
    var err = wrap.querySelector('.field-error');
    if (err) err.textContent = '';
  }
  var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  var PHONE_RE = /^[0-9+\s()-]{6,20}$/;

  function initAjaxForm(formId, endpoint, opts) {
    var form = document.getElementById(formId);
    if (!form) return;
    var msg = form.querySelector('.form-msg');
    var submitBtn = form.querySelector('button[type="submit"]');

    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var valid = true;

      form.querySelectorAll('[required]').forEach(function (field) {
        clearFieldError(field);
        if (field.type === 'checkbox' || field.type === 'radio') return;
        if (!field.value.trim()) {
          showFieldError(field, 'Este campo es obligatorio.');
          valid = false;
        }
      });

      var emailField = form.querySelector('[data-validate="email-or-phone"]');
      if (emailField) {
        var v = emailField.value.trim();
        clearFieldError(emailField);
        if (!v) {
          showFieldError(emailField, 'Indica un teléfono o un email de contacto.');
          valid = false;
        } else if (v.indexOf('@') > -1 && !EMAIL_RE.test(v)) {
          showFieldError(emailField, 'Ese email no parece válido.');
          valid = false;
        } else if (v.indexOf('@') === -1 && !PHONE_RE.test(v)) {
          showFieldError(emailField, 'Ese teléfono no parece válido.');
          valid = false;
        }
      }

      form.querySelectorAll('[data-si-grupo]:not([hidden]) input, [data-si-alergias]:not([hidden]) input').forEach(function (cb) {
        clearFieldError(cb);
        if (!cb.checked) { showFieldError(cb, 'Marca esta casilla para poder enviar.'); valid = false; }
      });

      if (!valid) {
        if (submitBtn) {
          submitBtn.classList.remove('shake');
          void submitBtn.offsetWidth;
          submitBtn.classList.add('shake');
        }
        return;
      }

      var data = new FormData(form);
      // honeypot: si viene relleno, es un bot — se descarta en silencio, sin avisar al bot
      if (data.get('web') || data.get('nickname')) return;
      if (PREVIA) { if (msg) msg.textContent = 'Esto es la vista previa: el formulario funcionará en vuestra web publicada.'; return; }

      if (submitBtn) submitBtn.disabled = true;
      if (msg) msg.textContent = '';

      fetch(endpoint, { method: 'POST', body: data })
        .then(function (r) { return r.json(); })
        .then(function (json) {
          if (submitBtn) submitBtn.disabled = false;
          if (json.ok) {
            form.reset();
            if (opts && opts.onSuccess) opts.onSuccess(json);
          } else {
            if (msg) msg.textContent = json.error || 'No se ha podido enviar. Inténtalo de nuevo.';
          }
        })
        .catch(function () {
          if (submitBtn) submitBtn.disabled = false;
          if (msg) msg.textContent = 'No se ha podido enviar. Comprueba tu conexión e inténtalo de nuevo.';
        });
    });
  }

  function initModal(modalId) {
    var overlay = document.getElementById(modalId);
    if (!overlay) return { open: function () {}, close: function () {} };
    var closeBtns = overlay.querySelectorAll('[data-close-modal]');
    closeBtns.forEach(function (btn) {
      btn.addEventListener('click', function () { overlay.classList.remove('open'); });
    });
    overlay.addEventListener('click', function (e) {
      if (e.target === overlay) overlay.classList.remove('open');
    });
    return {
      open: function () { overlay.classList.add('open'); },
      close: function () { overlay.classList.remove('open'); }
    };
  }

  // ---------- Música: votar sin recargar ----------
  function bindVoteButton(btn) {
    btn.addEventListener('click', function () {
      var id = btn.getAttribute('data-song-id');
      btn.disabled = true;
      fetch('/api/musica', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=vote&id=' + encodeURIComponent(id)
      })
        .then(function (r) { return r.json(); })
        .then(function (json) {
          btn.disabled = false;
          if (!json.ok && json.error) { btn.title = json.error; btn.disabled = true; return; }
          if (json.ok) {
            var countEl = btn.closest('.song-card').querySelector('.count');
            countEl.textContent = json.votes;
          }
        })
        .catch(function () { btn.disabled = false; });
    });
  }
  // ---------- Música: cargar la lista real desde el servidor ----------
  function buildSongCard(song) {
    var card = document.createElement('div');
    card.className = 'song-card';

    var meta = document.createElement('div');
    meta.className = 'meta';
    var b = document.createElement('b');
    b.textContent = song.cancion;
    var span = document.createElement('span');
    span.textContent = song.artista;
    meta.appendChild(b);
    meta.appendChild(span);

    var votes = document.createElement('div');
    votes.className = 'song-votes';
    var count = document.createElement('span');
    count.className = 'count';
    count.textContent = song.votos;
    var voteBtn = document.createElement('button');
    voteBtn.type = 'button';
    voteBtn.className = 'vote-btn';
    voteBtn.setAttribute('data-song-id', song.id);
    voteBtn.setAttribute('aria-label', 'Votar por ' + song.cancion);
    voteBtn.textContent = '+';
    votes.appendChild(count);
    votes.appendChild(voteBtn);

    card.appendChild(meta);
    card.appendChild(votes);
    bindVoteButton(voteBtn);
    return card;
  }
  function loadSongList() {
    var list = document.getElementById('songList');
    if (!list || PREVIA) return;
    var empty = document.getElementById('songListEmpty');
    fetch('/api/musica?action=list')
      .then(function (r) { return r.json(); })
      .then(function (json) {
        if (!json.ok || !json.canciones || !json.canciones.length) return;
        if (empty) empty.remove();
        json.canciones
          .slice()
          .sort(function (a, b) { return b.votos - a.votos; })
          .forEach(function (song) { list.appendChild(buildSongCard(song)); });
      })
      .catch(function () {
        // Un catch mudo aquí dejaría el mensaje "todavía no hay canciones"
        // puesto por defecto, que es indistinguible de un fallo de red real.
        if (empty) empty.textContent = 'No se ha podido cargar la lista de canciones. Recarga la página.';
      });
  }

  // ---------- Copiar IBAN ----------
  function initCopy() {
    document.querySelectorAll('[data-copiar]').forEach(function (b) {
      b.addEventListener('click', function () {
        var t = b.getAttribute('data-copiar');
        var ok = function () { var o = b.textContent; b.textContent = '¡Copiado!'; setTimeout(function () { b.textContent = o; }, 1800); };
        if (navigator.clipboard) navigator.clipboard.writeText(t).then(ok, function () {});
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initNav();
    initCalendar();
    initGuests();
    initReveal();
    initCountdown();
    initTabs();
    loadSongList();

    var rsvpModal = initModal('rsvpSuccessModal');
    initCopy();
    initAjaxForm('rsvpForm', '/api/rsvp', { onSuccess: function (json) {
      var h = document.querySelector('#rsvpSuccessModal h2');
      if (h) h.textContent = json && json.personas > 1 ? '¡Apuntados los ' + json.personas + '!' : '¡Apuntado!';
      rsvpModal.open();
    } });

    var musicMsg = document.getElementById('musicAddMsg');
    initAjaxForm('musicForm', '/api/musica', {
      onSuccess: function () {
        if (musicMsg) {
          musicMsg.style.color = 'var(--accent)';
          musicMsg.textContent = '¡Temazo añadido! Ya está en la lista.';
        }
        location.reload();
      }
    });
  });
})();
