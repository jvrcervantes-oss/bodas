<?php
// SOLO pruebas: lo llama tests/estilos_atelier.js. Escribe en stdout un JSON con el HTML «live» de cada página de la
// boda de ejemplo de cada diseño Atelier (tests/fixtures/config_ejemplo_<diseño>.json), con el mismo contexto que
// el snapshot HTML (tests/fixtures/snap_lib.php). Sin red, sin servidor y sin DATA_DIR real: carpeta temporal propia
// que se borra al salir. No termina en _test.php a propósito: tools/test.py no debe correrlo solo.
// Uso: php tests/estilos_paginas.php   (el .htaccess devuelve 404 a tests/: nunca se sirve)

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$tmp = sys_get_temp_dir() . '/bodas_estilos_' . bin2hex(random_bytes(4));
mkdir($tmp, 0700, true);
define('SIN_CONFIG_LOCAL', true);
define('DATA_DIR', $tmp);
define('BASE_DOMAIN', 'bodaenlace.com');
define('CREATOR_HOST', 'bodaenlace.com');
define('CREATOR_BASE', '');
// Mismos datos de empresa inventados y mismos módulos que tests/paquete_test.php (la privacidad los pinta)
file_put_contents($tmp . '/secrets.php', '<?php return ' . var_export(['pasarela' => 'lemon', 'empresa' => ['titular' => 'Titular De Prueba', 'nif' => '00000000T', 'domicilio' => 'Calle Prueba 1']], true) . ';');
$raiz = dirname(__DIR__);
foreach (['core', 'schema', 'render', 'foto', 'alta', 'mapa', 'cortesia', 'estudio', 'borrador', 'invitados', 'creador', 'landing', 'vista_constructor', 'boda', 'galeria', 'proxy'] as $m) {
    require_once $raiz . '/app/' . $m . '.php';
}
require_once __DIR__ . '/fixtures/snap_lib.php';

$out = ['assets_v' => ASSETS_V, 'disenos' => []];
foreach (SNAP_CONFIGS as $nom) {
    if (strpos($nom, 'ejemplo_') !== 0) continue;
    $c = normaliza_config(json_decode((string) file_get_contents(__DIR__ . "/fixtures/config_$nom.json"), true));
    $ctx = ['modo' => 'live', 'assets' => '/assets/', 'slug' => 'prueba-snap', 'foto' => !empty($c['foto']) ? '/foto?v=1' : '', 'mapa' => null];
    $rutas = ['', 'privacidad'];
    foreach ($c['secciones'] as $s) if ($s['on']) $rutas[] = $s['ruta'];
    $paginas = [];
    foreach ($rutas as $r) $paginas[$r] = str_replace(chr(13), '', (string) render_pagina($c, $r, $ctx));
    $out['disenos'][$c['atelier']] = ['rutas' => $rutas, 'paginas' => $paginas];
}
$borra = function (string $d) use (&$borra): void {
    foreach (scandir($d) ?: [] as $f) if ($f !== '.' && $f !== '..') is_dir("$d/$f") ? $borra("$d/$f") : unlink("$d/$f");
    rmdir($d);
};
$borra($tmp);
if (count($out['disenos']) !== count(ATELIER)) { fwrite(STDERR, 'faltan diseños en SNAP_CONFIGS: hay ' . count($out['disenos']) . ' de ' . count(ATELIER) . "\n"); exit(2); }
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
