<?php
// Punto de entrada único. El .htaccess manda aquí todo lo que no es un fichero de
// /assets. El Host decide si esto es el creador o la web de una boda: el slug se
// valida con una expresión cerrada ANTES de tocar el disco, y si la carpeta no
// existe es un 404, nunca una boda por defecto (Seguridad, #81).

declare(strict_types=1);

require __DIR__ . '/app/core.php';
require __DIR__ . '/app/schema.php';
require __DIR__ . '/app/render.php';
require __DIR__ . '/app/foto.php';
require __DIR__ . '/app/alta.php';
require __DIR__ . '/app/creador.php';
require __DIR__ . '/app/boda.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header('X-Robots-Tag: noindex, nofollow');

$host = strtolower((string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')));
$ruta = trim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH), '/');
$metodo = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
    if ($host === CREATOR_HOST) {
        rutas_creador($ruta, $metodo);
    } else {
        $sufijo = '.' . BASE_DOMAIN;
        $slug = substr($host, -strlen($sufijo)) === $sufijo ? substr($host, 0, -strlen($sufijo)) : '';
        if ($slug === '' || !slug_valido($slug) || !boda_existe($slug)) no_existe();
        rutas_boda($slug, $ruta, $metodo);
    }
} catch (Throwable $e) {
    registra('ERROR ' . $e->getMessage(), ['ruta' => $ruta, 'donde' => basename($e->getFile()) . ':' . $e->getLine()]);
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Algo ha fallado. Inténtalo de nuevo en un momento.';
}

function no_existe(): void {
    http_response_code(404);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="robots" content="noindex"><title>No encontrada</title>'
        . '<style>body{font:16px/1.6 system-ui,sans-serif;display:grid;place-items:center;min-height:90vh;margin:0;background:#F8F9FA;color:#191C1D;text-align:center}</style></head>'
        . '<body><div><h1 style="font-weight:400">Esta página no existe</h1><p><a href="' . h(url_creador()) . '">Crea la web de tu boda</a></p></div></body></html>';
    exit;
}
