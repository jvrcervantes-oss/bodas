<?php
// SOLO pruebas (tests/pagar_http_test.php): router de `php -S` que monta la app contra un DATA_DIR
// temporal, sin red y con el correo a disco. El .htaccess devuelve 404 a tests/: nunca se sirve.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
define('SIN_CONFIG_LOCAL', true);
define('SIN_RED', true);
define('DATA_DIR', (string) getenv('BODAS_TEST_DATA'));
define('BASE_DOMAIN', 'bodaenlace.com');
define('CREATOR_HOST', 'bodaenlace.com');
define('CREATOR_BASE', '');
define('CORREO_A_FICHERO', true);
define('LEMON_API', 'http://127.0.0.1:9');
$_SERVER['SCRIPT_NAME'] = '/index.php';
require __DIR__ . '/../index.php';
