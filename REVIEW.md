# REVIEW.md — Bodas (creador de webs de boda, panel de la pareja, pagos)
Lo lee el `revisor-codigo` (y cada `buscador-fallos`) **antes que el diff**: lo que ya ha fallado en ESTE repo y por qué es grave aquí.
No copia normas: cada línea dice qué pasó, cuándo y dónde está la regla (las del estudio viven en el repo de la agencia).
Grave = las 5 clases del revisor. Cuenta como código: quitar una línea pasa por el revisor. Tope 40 líneas. No se sirve: el `.htaccess` manda todo lo que no es `assets/` a `index.php`.

## Pagos (Lemon Squeezy)
- **IVA y total con `tax_inclusive`** (dinero): lo cazó la revisión (arreglo #2: aviso con desglose). 30-sep, 6ae5eff.
- **Datos de cobro que se descartan sin avisar** (dinero): el IBAN o la URL que no validaba se perdía en silencio. 30-sep, 6ae5eff. Un dato de dinero que no se guarda se dice en pantalla.

## Sesión y formularios del panel
- **Comprimir HTML que lleva el token CSRF** (seguridad): con texto de los invitados al lado es la condición de BREACH; solo se comprimen SVG, CSS y JS. 29-sep, 8c6f2e8.
- **Frenar los POST de otra web sin probar los legítimos** (deja de funcionar): con `Referrer-Policy: no-referrer` el panel rechazaba el login, recuperar y elegir contraseña de la propia pareja. 16-oct, CAMBIOS sobre cff43ab; arreglo 94b8e69.
- **La sesión del editor** (seguridad + deja de funcionar): el 401 y la renovación del CSRF pidieron arreglos del revisor y de Seguridad. 1-oct, b33249f.

## El creador (`crear.js`, `creador.php`)
- **Enganchar por la clase de estilo** (datos, máxima «enganche por identificador»): `.c-codigo input` cogía también el código de la galería y machacaba el código de regalo; lo cazó un usuario. 11-oct, 9913945. Se engancha por `data-*` (`data-codigo-invitados`), nunca por la clase que da el estilo (`contexto/patrones_tecnicos.md` → «Enganche por identificador estable»).

## Tests
- **Un test que levanta `php -S` y no lo cierra** (deja de funcionar): el servidor huérfano dejó el pre-push colgado horas sin una línea. 2-oct (`tools/predeploy.py` de la agencia → `_corre`). Un test que levanta un servidor lo cierra pase lo que pase.
