// Landing: el spot del hero no arranca solo si el visitante pidió menos movimiento
// (va en fichero porque la CSP de la landing no deja scripts en línea).
(() => {
  const v = document.querySelector('.l-spot');
  if (!v || !matchMedia('(prefers-reduced-motion: reduce)').matches) return;
  v.removeAttribute('autoplay');
  v.pause();
  v.controls = true;
})();
