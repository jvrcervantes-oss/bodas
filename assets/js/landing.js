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
      ['.l-cards', '.l-card'],
      ['.l-pasos-lista', 'li'],
      ['.l-demo', null],
      ['.l-atelier-grid', '.l-atelier-card'],
      ['.l-confianza-lista', null],
      ['.l-packs', '.l-precio-card'],
      ['.l-faq', '.l-faq-item'],
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
