<?php
// Código de galería/libro y código de regalo, cada uno en su sitio. Existe porque hasta el 11-oct-2026 campoCodigo()
// (assets/js/crear.js) copiaba lo escrito a todos los «.c-codigo input» para tener galería y libro iguales, y el código
// de regalo de «Publicar» (#codigo en vista_constructor.php) lleva esa misma clase de estilo: al poner el código de la
// galería se machacaba el de regalo (lo avisó un usuario). Ahora se sincroniza por data-codigo-invitados.
// Comprobado en Edge headless contra el creador local (antes: regalo = código de galería; ahora: se conserva).
// Uso: php tests/codigo_invitados_test.php (sale con 1 si falla)

declare(strict_types=1);

$raiz = dirname(__DIR__);
$js = (string) file_get_contents($raiz . '/assets/js/crear.js');
$vista = (string) file_get_contents($raiz . '/app/vista_constructor.php');
$fallos = 0;
function ok(bool $c, string $q): void { global $fallos; if (!$c) { $fallos++; echo "FALLA: $q\n"; } }

ok(preg_match('/<input id="codigo"/', $vista) === 1, 'el código de regalo sigue siendo #codigo en vista_constructor.php (si cambia, revisar este test)');
ok(preg_match('/function campoCodigo\(\)\s*\{(.*?)\n  \}/s', $js, $m) === 1, 'existe campoCodigo() en crear.js');
$cuerpo = $m[1] ?? '';
ok(strpos($cuerpo, "'data-codigo-invitados'") !== false, 'el campo de galería/libro lleva data-codigo-invitados');
ok(strpos($cuerpo, "querySelectorAll('[data-codigo-invitados]')") !== false, 'la sincronización va por data-codigo-invitados');
// Ningún selector de sincronización por clase compartida en todo el creador
ok(preg_match('/querySelectorAll\([\'"]\.c-codigo\b/', $js) === 0, 'nada recorre «.c-codigo …»: esa clase la comparte el código de regalo');

echo $fallos ? "$fallos fallo(s)\n" : "OK codigo_invitados\n";
exit($fallos ? 1 : 0);
