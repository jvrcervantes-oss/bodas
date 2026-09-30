<?php
// Pruebas del guarda `GET api/padrino/pedidos` (subtarea 2 de encargos/20260927_trabajador_autonomo.md, Seguridad #174):
// el Tesorero del Padrino lee los pedidos de Lemon Squeezy por aquí porque la clave de LS no tiene alcance de solo
// lectura y no sale de este servidor. Sin red: la API de LS se inyecta. Datos de prueba inventados (repo público).
// Uso: php tests/padrino_pedidos_test.php  (sale con 1 si algo falla). El .htaccess devuelve 404 a tests/.

declare(strict_types=1);

$tmp = sys_get_temp_dir() . '/bodas_pedidos_test_' . bin2hex(random_bytes(4));
mkdir($tmp, 0700, true);
define('SIN_CONFIG_LOCAL', true);
define('DATA_DIR', $tmp);
define('BASE_DOMAIN', 'bodaenlace.com');
define('CREATOR_HOST', 'bodaenlace.com');
define('CREATOR_BASE', '');
define('CORREO_A_FICHERO', true);
define('LEMON_API', 'http://127.0.0.1:9');   // nada debe salir a la red
file_put_contents($tmp . '/secrets.php', '<?php return ' . var_export([
    'pasarela' => 'lemon', 'lemon_api_key' => 'clave-de-prueba', 'lemon_tienda' => '483461', 'lemon_test' => false,
], true) . ';');

$raiz = dirname(__DIR__);
foreach (['core', 'schema', 'render', 'foto', 'alta', 'mapa', 'cortesia', 'estudio', 'borrador', 'invitados', 'creador', 'landing', 'vista_constructor', 'boda', 'galeria', 'proxy'] as $m) {
    require_once $raiz . '/app/' . $m . '.php';
}

$fallos = 0;
$n = 0;
function ok(bool $c, string $que): void {
    global $fallos, $n;
    $n++;
    if (!$c) { $fallos++; echo "FALLA: $que\n"; }
}
function lanza(callable $f, string $contiene, string $que): void {
    try { $f(); ok(false, $que . ' (no lanzó)'); }
    catch (RuntimeException $e) { ok(strpos($e->getMessage(), $contiene) !== false, $que . ' (' . $e->getMessage() . ')'); }
}

/** Un pedido de LS con todo lo que trae la API real, datos personales incluidos, para ver que no salen. */
function orden(int $id, array $cambia = []): array {
    return ['type' => 'orders', 'id' => (string) $id, 'attributes' => array_replace([
        'store_id' => 483461, 'customer_id' => 77, 'identifier' => 'uuid-' . $id, 'order_number' => $id,
        'user_name' => 'Nombre Inventado', 'user_email' => 'comprador' . $id . '@example.com',
        'currency' => 'EUR', 'currency_rate' => '1.08', 'subtotal' => 10331, 'setup_fee' => 0, 'discount_total' => 0,
        'tax' => 2169, 'total' => 12500, 'refunded_amount' => 0, 'tax_name' => 'VAT', 'tax_rate' => '21.00', 'tax_inclusive' => true,
        'status' => 'paid', 'refunded' => false, 'refunded_at' => null, 'test_mode' => false,
        'affiliate_id' => null, 'referral_amount' => null,
        'urls' => ['receipt' => 'https://app.lemonsqueezy.com/my-orders/firmado?signature=abc'],
        'first_order_item' => ['product_name' => 'Web de boda', 'variant_id' => 2169949],
        'created_at' => '2026-10-01T10:00:00.000000Z', 'updated_at' => '2026-10-01T10:00:00.000000Z',
    ], $cambia)];
}
/** API falsa: páginas fijas y apunta las rutas pedidas. */
function api_de(array $paginas, int $total, ?int $ultima = null, array &$rutas = []): callable {
    return function (string $metodo, string $ruta) use ($paginas, $total, $ultima, &$rutas) {
        $rutas[] = [$metodo, $ruta];
        parse_str((string) parse_url($ruta, PHP_URL_QUERY), $q);
        $p = (int) ($q['page']['number'] ?? 0);
        if (!isset($paginas[$p - 1])) return [404, []];
        if ($paginas[$p - 1] === 'error') return [500, []];
        return [200, ['data' => $paginas[$p - 1], 'meta' => ['page' => ['currentPage' => $p, 'lastPage' => $ultima ?? count($paginas), 'total' => $total, 'perPage' => 100]]]];
    };
}
$lote = fn(int $desde, int $cuantos) => array_map(fn($i) => orden($i), range($desde, $desde + $cuantos - 1));

// ── 1. Dos páginas: todos los pedidos, lista cerrada, sin datos personales
$rutas = [];
$r = padrino_pedidos(api_de([$lote(1000, 100), $lote(1100, 50)], 150, null, $rutas));
ok($r['ok'] === true && count($r['pedidos']) === 150, 'dos páginas: 150 pedidos');
ok(count($rutas) === 2 && $rutas[0][0] === 'GET', 'dos peticiones GET, nada más');
parse_str((string) parse_url($rutas[0][1], PHP_URL_QUERY), $q);
ok(parse_url($rutas[0][1], PHP_URL_PATH) === '/v1/orders', 'lee /v1/orders');
ok(($q['filter']['store_id'] ?? '') === '483461' && ($q['page']['size'] ?? '') === '100', 'filtra por la tienda de secrets.php y pide 100 por página');
$claves = ['id', 'created_at', 'currency', 'subtotal', 'discount_total', 'setup_fee', 'tax', 'total', 'refunded_amount', 'status',
    'refunded', 'refunded_at', 'test_mode', 'tax_rate', 'tax_inclusive', 'affiliate', 'referral_amount'];
ok(array_keys($r['pedidos'][0]) === $claves, 'lista cerrada de campos, en este orden');
$json = json_encode($r);
ok(strpos($json, '@') === false, 'ni un email en la respuesta');
foreach (['Nombre Inventado', 'uuid-', 'signature', 'customer_id', 'identifier', 'user_', 'urls', 'first_order_item', 'currency_rate'] as $x) {
    ok(strpos($json, $x) === false, 'no sale ' . $x);
}
$p = $r['pedidos'][0];
ok($p['id'] === '1000' && $p['total'] === 12500 && $p['tax'] === 2169 && $p['test_mode'] === false && $p['status'] === 'paid', 'cifras del pedido tal cual');

// ── 2. Todo o nada
lanza(fn() => padrino_pedidos(api_de([$lote(1, 100), 'error'], 150)), 'página 2', 'una página que falla: error, no lista a medias');
lanza(fn() => padrino_pedidos(api_de([[orden(1, ['store_id' => 999])]], 1)), 'otra tienda', 'pedido de otra tienda: error');
lanza(fn() => padrino_pedidos(api_de([[orden(1), orden(2)], [orden(2)]], 3)), 'faltan', 'menos pedidos únicos que el total de LS: error');
lanza(fn() => padrino_pedidos(api_de([[['type' => 'orders', 'id' => 'x', 'attributes' => []]]], 1)), 'sin id', 'id que no es un número: error');
lanza(fn() => padrino_pedidos(api_de(array_fill(0, 51, [orden(1)]), 51)), 'páginas', 'más de 50 páginas: error, no lista cortada');
lanza(fn() => padrino_pedidos(fn() => [200, ['data' => [orden(1)]]]), 'paginación', 'sin meta de paginación: error');

// ── 3. Un pedido nuevo que desplaza la paginación no se cuenta dos veces
$r = padrino_pedidos(api_de([[orden(3), orden(2)], [orden(2), orden(1)]], 3));
ok(count($r['pedidos']) === 3, 'duplicado entre páginas: se cuenta una vez');

// ── 4. Tienda sin pedidos
ok(padrino_pedidos(api_de([[]], 0, 1))['pedidos'] === [], 'tienda vacía (lastPage 1): lista vacía');
ok(padrino_pedidos(api_de([[]], 0, 0))['pedidos'] === [], 'tienda vacía (lastPage 0): lista vacía');

// ── 5. Lo que no tiene la forma esperada sale null, nunca texto libre
$raro = padrino_pedidos(api_de([[orden(5, ['test_mode' => 'no', 'status' => 'Paid <b>', 'currency' => 'eur', 'total' => '12.5',
    'created_at' => 'ayer', 'tax_rate' => 'veintiuno', 'affiliate_id' => 12, 'referral_amount' => 300])]], 1))['pedidos'][0];
ok($raro['test_mode'] === null && $raro['status'] === null && $raro['currency'] === null && $raro['total'] === null
    && $raro['created_at'] === null && $raro['tax_rate'] === null, 'valores raros -> null');
ok($raro['affiliate'] === true && $raro['referral_amount'] === 300, 'afiliado y su comisión');

// ── 6. La ruta: GET con el token de lectura, límite propio y 502 si LS falla (sobre el fuente)
$fuente = (string) file_get_contents($raiz . '/app/padrino.php');
ok(preg_match("/case 'pedidos':\\s*\\n\\s*if \\(\\\$metodo !== 'GET'\\)/", $fuente) === 1, 'pedidos solo por GET');
ok(strpos($fuente, "\$escritura = in_array(\$sub, ['precios', 'marca', 'campanas'], true);") !== false, 'pedidos no es de escritura: token de lectura');
ok(strpos($fuente, "limite('padrino-pedidos|'") !== false && strpos($fuente, "json_response(['ok' => false, 'error' => 'lemon'], 502)") !== false,
    'límite propio y 502 sin lista a medias');

array_map('unlink', glob($tmp . '/{,*/,*/*/}*', GLOB_BRACE) ?: []);
echo ($fallos ? "ROJO: $fallos de $n fallan\n" : "VERDE: $n/$n\n");
exit($fallos ? 1 : 0);
