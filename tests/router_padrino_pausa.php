<?php
// SOLO pruebas (tests/padrino_pausa_test.php): como router_prueba.php, pero fija los hashes de los tokens del Padrino
// (los del repo no sirven: su token en claro solo vive en Railway). El .htaccess devuelve 404 a tests/: nunca se sirve.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
define('SCHEME', 'http');   // la cookie de sesión del estudio es Secure con https: curl por http no la devolvería
define('PADRINO_HASHES_PRUEBA', (array) json_decode((string) getenv('BODAS_TEST_HASHES'), true));
require __DIR__ . '/router_prueba.php';
