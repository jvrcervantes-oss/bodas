// Landing. Va en fichero y al final del body porque la CSP de la landing no deja scripts en línea.
// Motion (28-sep-2026): cabecera que se recoge, confirmaciones de ejemplo, revelado de secciones y
// paletas del constructor. Sin este fichero la página se ve entera y quieta; con «reducir movimiento»
// no se esconde nada ni rota nada solo (las transiciones las apaga marca.css).
(() => {
  const menos = matchMedia('(prefers-reduced-motion: reduce)').matches;
  const hayIO = 'IntersectionObserver' in window;

  // El spot del hero no arranca solo si el visitante pidió menos movimiento
  const v = document.querySelector('.l-spot');
  if (v && menos) {
    v.removeAttribute('autoplay');
    v.pause();
    v.controls = true;
  }

  // Cabecera: se recoge al pasar de 120 px y se abre por debajo de 40 (histéresis: sin parpadeo en el umbral)
  const top = document.querySelector('.l-top');
  if (top) {
    let rec = false, pend = false;
    const mira = () => {
      pend = false;
      const y = window.scrollY;
      if (!rec && y > 120) { rec = true; top.classList.add('recogida'); }
      else if (rec && y < 40) { rec = false; top.classList.remove('recogida'); }
    };
    addEventListener('scroll', () => { if (!pend) { pend = true; requestAnimationFrame(mira); } }, { passive: true });
    mira();
  }

  // Menú: sección activa. Engancha por el href (#id) de cada enlace; marca .on y aria-current en los dos menús y mueve la raya
  const navs = [...document.querySelectorAll('.l-nav a[href^="#"], .l-hoja a[href^="#"]')];
  const raya = document.querySelector('.l-nav-raya');
  const secs = [...new Set(navs.map(a => a.getAttribute('href').slice(1)))].map(id => document.getElementById(id)).filter(Boolean);
  if (secs.length) {
    let pendS = false, actual;
    const marcaSec = () => {
      pendS = false;
      const y = (top ? top.offsetHeight : 0) + innerHeight * 0.25;
      let cur = null;
      for (const s of secs) if (s.getBoundingClientRect().top <= y) cur = s;
      if (cur && cur.getBoundingClientRect().bottom < y) cur = null;   // pasada la última sección (cierre y pie): ninguna activa
      const id = cur ? cur.id : null;
      if (id === actual) return;
      actual = id;
      navs.forEach(a => {
        const on = a.getAttribute('href') === '#' + id;
        a.classList.toggle('on', on);
        if (on) a.setAttribute('aria-current', 'location'); else a.removeAttribute('aria-current');
      });
      const on = document.querySelector('.l-nav a.on');
      if (raya) { if (on) { raya.style.left = on.offsetLeft + 'px'; raya.style.width = on.offsetWidth + 'px'; raya.style.opacity = 1; } else raya.style.opacity = 0; }
    };
    addEventListener('scroll', () => { if (!pendS) { pendS = true; requestAnimationFrame(marcaSec); } }, { passive: true });
    addEventListener('resize', () => { actual = undefined; if (!pendS) { pendS = true; requestAnimationFrame(marcaSec); } }, { passive: true });
    marcaSec();
  }

  // Menú de pantallas estrechas: el botón abre y cierra la hoja de secciones; se cierra al elegir una, con Escape o al ensanchar
  const hamb = document.querySelector('[data-menu-movil]');
  const hoja = hamb && document.getElementById(hamb.getAttribute('aria-controls'));
  if (hamb && hoja) {
    hamb.hidden = false;
    const abre = si => { hamb.setAttribute('aria-expanded', String(si)); hoja.hidden = !si; };
    hamb.addEventListener('click', () => abre(hoja.hidden));
    hoja.addEventListener('click', e => { if (e.target.closest('a')) abre(false); });
    document.addEventListener('click', e => { if (!hoja.hidden && !hoja.contains(e.target) && !hamb.contains(e.target)) abre(false); });
    addEventListener('keydown', e => { if (e.key === 'Escape' && !hoja.hidden) { abre(false); hamb.focus(); } });
    matchMedia('(min-width: 1081px)').addEventListener('change', e => { if (e.matches) abre(false); });
  }

  // Tarjeta «Confirmación recibida» del hero: va cambiando de ejemplo mientras el hero está a la vista.
  // Son datos de ejemplo, como el vídeo que lleva el rótulo «Ejemplo»; la tarjeta es aria-hidden.
  const flota = document.querySelector('.l-flota-a');
  const ft = flota && flota.querySelector('.l-flota-t');
  const fs = flota && flota.querySelector('.l-flota-s');
  if (flota && ft && fs && !menos && hayIO) {
    const ejemplos = [
      [ft.textContent, fs.textContent],
      ['Familia Ortega · 4 personas', 'Autobús de vuelta'],
      ['Jorge y Marta', 'Menú vegetariano · sin lactosa'],
      ['Carmen', 'Menú de pescado · sin marisco'],
    ];
    let i = 0, t = null, quieta = false;
    // se para mientras el ratón está sobre el vídeo y las tarjetas (WCAG 2.2.2); no hay nada enfocable ahí mientras rota
    const zona = flota.closest('.l-hero-vis') || flota;
    zona.addEventListener('pointerenter', () => { quieta = true; });
    zona.addEventListener('pointerleave', () => { quieta = false; });
    const siguiente = () => {
      if (document.hidden || quieta) return;
      flota.classList.add('cambia');
      setTimeout(() => {
        i = (i + 1) % ejemplos.length;
        ft.textContent = ejemplos[i][0];
        fs.textContent = ejemplos[i][1];
        flota.classList.remove('cambia');
      }, 380);
    };
    new IntersectionObserver(es => {
      clearInterval(t);
      // la primera vuelta espera a que termine la entrada del hero
      if (es[0].isIntersecting) t = setInterval(siguiente, 3400);
    }).observe(flota);
  }

  // Revelado de secciones: titular y luego sus piezas, 90 ms entre una y otra, una sola vez.
  // Solo se esconde lo que está por debajo de la pantalla al cargar; lo visible no parpadea.
  if (!menos && hayIO) {
    const grupos = [
      ['.l-sec-cab', null],
      ['.l-bento', '.l-bc'],
      ['.l-pasos-lista', 'li'],
      ['.l-demo', null],
      ['.l-at-caja', null],
      ['.l-packs', '.l-precio-card'],
      ['.l-faq-col', '.l-faq-item'],
      ['.l-final', null],
    ];
    const alto = innerHeight;
    const io = new IntersectionObserver(es => es.forEach(e => {
      if (!e.isIntersecting) return;
      const el = e.target;
      io.unobserve(el);
      el.classList.add('in');
      // al acabar se quitan las clases: las tarjetas recuperan su propia transición de hover
      const fin = () => el.classList.remove('rv', 'in');
      el.addEventListener('transitionend', ev => { if (ev.target === el && ev.propertyName === 'opacity') fin(); });
      setTimeout(fin, 1400 + (parseFloat(el.style.getPropertyValue('--rv-d')) || 0) * 1000);
    }), { rootMargin: '0px 0px -8% 0px' });
    const marca = (el, n) => {
      if (el.classList.contains('rv') || el.getBoundingClientRect().top < alto) return;
      el.classList.add('rv');
      el.style.setProperty('--rv-d', Math.min(n, 5) * 0.09 + 's');
      io.observe(el);
    };
    grupos.forEach(([cont, hijo]) => document.querySelectorAll('.l main ' + cont).forEach(c => {
      if (!hijo) { marca(c, 0); return; }
      c.querySelectorAll(':scope > ' + hijo).forEach((h, n) => marca(h, n));
    }));
  }

  // «Todo en una web»: las escenas de ejemplo se pueden tocar. Todo va enganchado por data-b-*, nunca por texto ni por clase de estilo.
  // Sin JS las escenas se ven enteras y quietas (el marcador ya viene calculado desde el servidor).
  document.querySelectorAll('.l-b-chips').forEach(g => g.addEventListener('click', e => {
    const b = e.target.closest('[data-b-chip]');
    if (!b) return;
    g.querySelectorAll('[data-b-chip]').forEach(x => x.setAttribute('aria-pressed', String(x === b)));
  }));
  document.querySelectorAll('[data-b-voto]').forEach(b => b.addEventListener('click', () => {
    const on = b.getAttribute('aria-pressed') === 'true';
    b.setAttribute('aria-pressed', String(!on));
    b.querySelector('span').textContent = (+b.dataset.n) + (on ? 0 : 1);
  }));
  const plano = document.querySelector('.l-b-plano');
  const tip = document.querySelector('[data-b-tip]');
  if (plano && tip) plano.addEventListener('click', e => {
    const m = e.target.closest('[data-b-mesa]');
    if (!m) return;
    plano.querySelectorAll('[data-b-mesa]').forEach(x => x.classList.toggle('on', x === m));
    const [nom, ...resto] = m.dataset.bTexto.split(' · ');
    tip.textContent = '';
    const b = document.createElement('b');
    b.textContent = nom;
    tip.append(b, resto.length ? ' · ' + resto.join(' · ') : '');
  });
  const marc = document.querySelector('[data-b-fin]');
  if (marc) {
    const dias = marc.querySelector('[data-b-d]'), horas = marc.querySelector('[data-b-h]'), mins = marc.querySelector('[data-b-m]');
    const fin = +marc.dataset.bFin * 1000;
    const tic = () => {
      const t = Math.max(0, fin - Date.now());
      dias.textContent = Math.floor(t / 864e5);
      horas.textContent = Math.floor(t % 864e5 / 36e5);
      mins.textContent = Math.floor(t % 36e5 / 6e4);
    };
    tic();
    setInterval(() => { if (!document.hidden) tic(); }, 20000);
  }

  // Colección Atelier: cada miniatura enseña su diseño en grande. Los seis paneles ya están en el HTML; aquí solo se cambia cuál se ve
  const atCaja = document.querySelector('[data-atelier-caja]');
  if (atCaja) atCaja.addEventListener('click', e => {
    const b = e.target.closest('[data-atelier]');
    if (!b) return;
    atCaja.querySelectorAll('[data-atelier-panel]').forEach(p => { p.hidden = p.dataset.atelierPanel !== b.dataset.atelier; });
    atCaja.querySelectorAll('[data-atelier]').forEach(x => x.setAttribute('aria-pressed', String(x === b)));
  });

  // Chat del Paso 3: el mensaje de la pareja y la respuesta entran uno tras otro, una vez, al llegar. Solo si está por debajo de la pantalla al cargar.
  const wa = document.querySelector('.l-wa');
  if (wa && !menos && hayIO && wa.getBoundingClientRect().top >= innerHeight) {
    wa.classList.add('rv-wa');
    const ow = new IntersectionObserver(es => {
      if (!es[0].isIntersecting) return;
      ow.disconnect();
      wa.classList.add('in');
    }, { rootMargin: '0px 0px -15% 0px' });
    ow.observe(wa);
  }

  // Guirnalda de «Cómo funciona»: la acuarela aparece y baja un poco una vez, al llegar. Solo si está por debajo de la pantalla al cargar.
  const guir = document.querySelector('.l-guirnalda');
  if (guir && !menos && hayIO && guir.getBoundingClientRect().top >= innerHeight) {
    guir.classList.add('rv-g');
    const ob = new IntersectionObserver(es => {
      if (!es[0].isIntersecting) return;
      ob.disconnect();
      guir.classList.add('in');
    }, { rootMargin: '0px 0px -10% 0px' });
    ob.observe(guir);
  }

  // Constructor: cada paleta enseña la captura de la web de ejemplo en esa paleta, con fundido.
  // Rotan solas mientras el bloque está a la vista, hasta que el visitante toca una.
  const pal = document.querySelector('.l-paletas');
  const previa = document.querySelector('.l-demo-previa');
  if (pal && previa) {
    const botones = [...pal.querySelectorAll('button[data-tema]')];
    const base = pal.dataset.base;
    let actual = botones.findIndex(b => b.classList.contains('on'));
    let rota = null, parar = false, ocupado = false, pedida = null, quieta = false;
    // al acabar un cambio se aplica el último clic que llegó en medio (si no, el botón parecía no responder)
    const libera = () => {
      ocupado = false;
      if (pedida !== null) { const p = pedida; pedida = null; pon(p); }
    };
    const pon = n => {
      // una sola transición a la vez: dos seguidas dejaban dos capturas apiladas
      if (ocupado) { pedida = n; return; }
      if (n === actual) return;
      const b = botones[n];
      const vieja = previa.querySelector('img');
      if (!vieja) return;
      ocupado = true;
      const nueva = document.createElement('img');
      nueva.className = 'l-previa-nueva';
      nueva.alt = vieja.alt;
      nueva.width = 1280;
      nueva.height = 800;
      nueva.src = base + b.dataset.tema + '.webp';
      // si la captura no carga (tema nuevo sin captura), se queda la anterior
      nueva.addEventListener('error', libera);
      nueva.addEventListener('load', () => {
        actual = n;
        botones.forEach(x => { const on = x === b; x.classList.toggle('on', on); x.setAttribute('aria-pressed', on ? 'true' : 'false'); });
        vieja.after(nueva);
        requestAnimationFrame(() => requestAnimationFrame(() => nueva.classList.add('in')));
        setTimeout(() => { vieja.remove(); nueva.classList.remove('l-previa-nueva', 'in'); libera(); }, 650);
      });
    };
    botones.forEach((b, n) => b.addEventListener('click', () => { clearInterval(rota); rota = null; parar = true; pon(n); }));
    // la rotación se para mientras el ratón o el foco están en el bloque (WCAG 2.2.2)
    const demo = previa.closest('.l-demo') || previa;
    // dos marcas: si el foco sigue dentro, que el ratón salga no reanuda la rotación
    let encima = false, enfocada = false;
    const sync = () => { quieta = encima || enfocada; };
    demo.addEventListener('pointerenter', () => { encima = true; sync(); });
    demo.addEventListener('pointerleave', () => { encima = false; sync(); });
    demo.addEventListener('focusin', () => { enfocada = true; sync(); });
    demo.addEventListener('focusout', e => { enfocada = demo.contains(e.relatedTarget); sync(); });
    if (!menos && hayIO) {
      new IntersectionObserver(es => {
        clearInterval(rota);
        if (es[0].isIntersecting && !parar) rota = setInterval(() => { if (!document.hidden && !quieta) pon((actual + 1) % botones.length); }, 2600);
      }, { threshold: .4 }).observe(previa);
    }
  }
})();
