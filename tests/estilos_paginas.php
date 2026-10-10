<?php
// SOLO pruebas: lo llama tests/estilos_atelier.js. Escribe en stdout un JSON con el HTML «live» de cada página de la
// boda de ejemplo de cada diseño Atelier (tests/fixtures/config_ejemplo_<diseño>.json), con el mismo contexto que
// el snapshot HTML (tests/fixtures/snap_lib.php). Sin red, sin servidor y sin DATA_DIR real: carpeta temporal propia
// que se borra al salir. No termina en _test.php a propósito: tools/test.py no debe correrlo solo.
// Uso: php tests/estilos_paginas.php   (el .htaccess devuelve 404 a tests/: nunca se sirve)
// Opciones (las usa tests/fabrica_atelier.js; sin ellas la salida es la de siempre, que es la que mide el arnés):
//   --nombres "Uno|Dos"  cambia los nombres de la pareja (la previa de nombres largos y las capturas)
//   --ajustes '<json>'   ajustes de diseño (S3, app/disenos.php) pasados por ctx a todos los diseños: mide el arnés con las palancas movidas
//   --control            añade las dos webs Esencial de los snapshots (esencial, foto_papel), con su nombre como clave:
//                        sirven de control de que un cambio Atelier no toca el Esencial
//   --datos '<json>'     mezcla esos datos en la config de cada diseño (array_replace_recursive), después de --nombres:
//                        la boda de muestra de la landing (tests/tarjetas_atelier.js) cambia fecha, ciudad, lugar y hashtag

declare(strict_types=1);

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$opt = getopt('', ['nombres:', 'control', 'ajustes:', 'datos:']);
$nombres = isset($opt['nombres']) ? explode('|', (string) $opt['nombres'], 2) + [1 => ''] : null;
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
    $control = in_array($nom, ['esencial', 'foto_papel'], true);
    if (strpos($nom, 'ejemplo_') !== 0 && !($control && isset($opt['control']))) continue;
    $c = json_decode((string) file_get_contents(__DIR__ . "/fixtures/config_$nom.json"), true);
    if ($nombres) { $c['pareja']['nombre1'] = $nombres[0]; $c['pareja']['nombre2'] = $nombres[1]; }
    if (isset($opt['datos'])) $c = array_replace_recursive($c, json_decode((string) $opt['datos'], true) ?: []);
    $c = normaliza_config($c);
    $ctx = ['modo' => 'live', 'assets' => '/assets/', 'slug' => 'prueba-snap', 'foto' => !empty($c['foto']) ? '/foto?v=1' : '', 'mapa' => null];
    if (isset($opt['ajustes']) && !$control) $ctx['ajustes_diseno'] = json_decode((string) $opt['ajustes'], true) ?: [];
    $rutas = ['', 'privacidad'];
    foreach ($c['secciones'] as $s) if ($s['on']) $rutas[] = $s['ruta'];
    $paginas = [];
    foreach ($rutas as $r) $paginas[$r] = str_replace(chr(13), '', (string) render_pagina($c, $r, $ctx));
    $out['disenos'][$control ? $nom : $c['atelier']] = ['rutas' => $rutas, 'paginas' => $paginas];
}
$borra = function (string $d) use (&$borra): void {
    foreach (scandir($d) ?: [] as $f) if ($f !== '.' && $f !== '..') is_dir("$d/$f") ? $borra("$d/$f") : unlink("$d/$f");
    rmdir($d);
};
$borra($tmp);
if (count(array_intersect_key($out['disenos'], ATELIER)) !== count(ATELIER)) { fwrite(STDERR, 'faltan diseños en SNAP_CONFIGS: hay ' . count($out['disenos']) . ' de ' . count(ATELIER) . "\n"); exit(2); }
echo json_encode($out, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
