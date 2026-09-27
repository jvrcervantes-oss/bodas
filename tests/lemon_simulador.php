<?php
// SOLO pruebas (tests/mesas_test.php): simulador de la API de Lemon Squeezy para `php -S`, sin red. Contesta lo
// justo que usa app/lemon.php: crear un checkout (guarda el cuerpo recibido para comprobarlo) y releer un
// pedido (lo lee de un fichero que escribe la prueba). El .htaccess devuelve 404 a tests/: nunca se sirve.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
$dir = (string) getenv('BODAS_TEST_LEMON_DIR');
$ruta = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
header('Content-Type: application/vnd.api+json');
if (($_SERVER['HTTP_AUTHORIZATION'] ?? '') !== 'Bearer clave-de-prueba') { http_response_code(401); echo '{"errors":[{"detail":"sin clave"}]}'; exit; }
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ruta === '/v1/checkouts') {
    $n = count(glob($dir . '/checkout_*.json') ?: []) + 1;
    file_put_contents($dir . '/checkout_' . $n . '.json', (string) file_get_contents('php://input'));
    http_response_code(201);
    echo json_encode(['data' => ['type' => 'checkouts', 'id' => (string) $n, 'attributes' => ['url' => 'https://lemon.test/checkout/' . $n]]]);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'GET' && preg_match('~^/v1/orders/(\d+)$~', $ruta, $m) && is_file($dir . '/order_' . $m[1] . '.json')) {
    echo json_encode(['data' => ['type' => 'orders', 'id' => $m[1], 'attributes' => json_decode((string) file_get_contents($dir . '/order_' . $m[1] . '.json'), true)]]);
    exit;
}
http_response_code(404);
echo '{"errors":[{"detail":"no existe"}]}';
