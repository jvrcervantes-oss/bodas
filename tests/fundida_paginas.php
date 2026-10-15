<?php
// SOLO pruebas: lo llama tests/fundida_foto.js. Escribe en stdout el HTML «live» de la portada de la boda de ejemplo
// (tests/fixtures/config_esencial.json) con la foto en estilo «Fundida» y la proporción que se le pase (o sin ella).
// Uso: php tests/fundida_paginas.php <foto_ar|''>   (el .htaccess devuelve 404 a tests/: nunca se sirve)
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$tmp = sys_get_temp_dir() . '/bodas_fundida_' . bin2hex(random_bytes(4));
mkdir($tmp, 0700, true);
define('SIN_CONFIG_LOCAL', true);
define('DATA_DIR', $tmp);
define('BASE_DOMAIN', 'bodaenlace.com');
define('CREATOR_HOST', 'bodaenlace.com');
define('CREATOR_BASE', '');
file_put_contents($tmp . '/secrets.php', '<?php return ' . var_export(['pasarela' => 'lemon', 'empresa' => ['titular' => 'Titular De Prueba', 'nif' => '00000000T', 'domicilio' => 'Calle Prueba 1']], true) . ';');
$raiz = dirname(__DIR__);
foreach (['core', 'schema', 'render', 'foto', 'alta', 'mapa', 'cortesia', 'estudio', 'borrador', 'invitados', 'creador', 'landing', 'vista_constructor', 'boda', 'galeria', 'proxy'] as $m) {
    require_once $raiz . '/app/' . $m . '.php';
}
$c = json_decode((string) file_get_contents(__DIR__ . '/fixtures/config_esencial.json'), true);
$c['foto'] = true; $c['foto_estilo'] = 'fundida'; $c['atelier'] = '';
echo render_pagina(normaliza_config($c), '', ['modo' => 'live', 'assets' => '/assets/', 'slug' => 'prueba', 'foto' => '/foto?v=1', 'mapa' => null, 'foto_ar' => (string) ($argv[1] ?? '')]);
array_map('unlink', glob($tmp . '/*') ?: []);
@rmdir($tmp);
