<?php
// Pruebas del cobro por Lemon Squeezy (encargo 20260926_bodas_lemonsqueezy, rev. previa #109).
// Sin red: la relectura del pedido se inyecta. Uso: php tests/lemon_test.php  (sale con 1 si algo falla)
// El .htaccess devuelve 404 a tests/: esto nunca se sirve por la web.

declare(strict_types=1);

$tmp = sys_get_temp_dir() . '/bodas_lemon_test_' . bin2hex(random_bytes(4));
mkdir($tmp, 0700, true);
define('SIN_CONFIG_LOCAL', true);
define('DATA_DIR', $tmp);
define('BASE_DOMAIN', 'bodaenlace.com');
define('CREATOR_HOST', 'bodaenlace.com');
define('CREATOR_BASE', '');
define('CORREO_A_FICHERO', true);
define('LEMON_API', 'http://127.0.0.1:9');   // nada debe salir a la red: si algo lo intenta, falla
const SECRETO_FIRMA = 'secreto-de-prueba-0123456789abcdef0123';
file_put_contents($tmp . '/secrets.php', '<?php return ' . var_export([
    'pasarela' => 'lemon', 'lemon_api_key' => 'clave-de-prueba', 'lemon_webhook_secret' => SECRETO_FIRMA,
    'lemon_tienda' => '483461', 'lemon_variante' => '2169949', 'lemon_producto' => '1389266', 'lemon_test' => true,
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

/** Pedido pendiente como lo deja api_pagar: config + meta con el precio congelado. */
function pendiente(string $slug, int $precio): string {
    $tok = bin2hex(random_bytes(16));
    $c = config_inicial();
    $c['pareja']['nombre1'] = 'Ana';
    $c['pareja']['nombre2'] = 'Luis';
    $c['pareja']['email'] = 'pareja@example.com';
    $c['fecha'] = date('Y-m-d', strtotime('+200 days'));
    escribe_json(dir_datos('pendientes', $tok, 'config.json'), $c);
    escribe_json(dir_datos('pendientes', $tok, 'meta.json'), ['slug' => $slug, 'creado' => time(), 'precio_cent' => $precio, 'pasarela' => 'lemon',
        'aceptacion' => ['fecha' => date('c'), 'version' => 'x', 'condiciones' => 'c', 'desistimiento' => 'd', 'cortesia' => '']]);
    escribe_json(dir_datos('reservas', $slug . '.json'), ['token' => $tok, 'hasta' => time() + 1800]);
    return $tok;
}
function pedido_ls(int $total, array $cambia = []): array {
    return array_replace_recursive(['store_id' => 483461, 'currency' => 'EUR', 'total' => $total, 'tax' => (int) round($total * 21 / 121),
        'discount_total' => 0, 'status' => 'paid', 'test_mode' => true, 'user_email' => 'pareja@example.com', 'user_name' => 'Ana',
        'order_number' => 1001, 'identifier' => 'uuid', 'first_order_item' => ['variant_id' => 2169949, 'product_id' => 1389266, 'quantity' => 1]], $cambia);
}
function evento(string $nombre, string $id, string $tok, string $slug): array {
    return ['meta' => ['event_name' => $nombre, 'test_mode' => true, 'custom_data' => ['token' => $tok, 'slug' => $slug, 'producto' => 'bodas']],
        'data' => ['type' => 'orders', 'id' => $id, 'attributes' => []]];
}
function lector(array $pedidos): callable { return fn(string $id) => $pedidos[$id] ?? null; }
function facturas_emitidas(): int { return count(glob(dir_datos('facturas', 'BODA-*.json')) ?: []) + (is_file(dir_datos('facturas', 'contador.json')) ? 1 : 0); }

$precio = precio_esencial_cent();

// 1. Checkout: importe y variante del servidor, sin descuentos, marca de propiedad y caducidad de 30 min
$cu = lemon_cuerpo_checkout(str_repeat('a', 32), 'ana-y-luis', 'p@example.com', $precio, 1000000);
ok($cu['data']['attributes']['custom_price'] === $precio, 'custom_price = precio del servidor');
ok($cu['data']['attributes']['checkout_options']['discount'] === false, 'checkout sin campo de descuento');
ok($cu['data']['attributes']['product_options']['enabled_variants'] === [2169949], 'solo la variante de secrets');
ok($cu['data']['relationships']['store']['data']['id'] === '483461', 'tienda de secrets');
ok($cu['data']['attributes']['checkout_data']['custom'] === ['token' => str_repeat('a', 32), 'slug' => 'ana-y-luis', 'producto' => 'bodas'], 'custom con token, slug y producto');
ok($cu['data']['attributes']['expires_at'] === gmdate('Y-m-d\TH:i:s\Z', 1000000 + 1800), 'caduca a los 30 min');
ok($cu['data']['attributes']['product_options']['redirect_url'] === 'https://bodaenlace.com/listo?t=' . str_repeat('a', 32), 'redirect a /listo?t=');

// 2. Firma
$cuerpo = '{"meta":{}}';
ok(lemon_firma_ok($cuerpo, hash_hmac('sha256', $cuerpo, SECRETO_FIRMA)), 'firma buena');
ok(!lemon_firma_ok($cuerpo, hash_hmac('sha256', $cuerpo . ' ', SECRETO_FIRMA)), 'firma de otro cuerpo');
ok(!lemon_firma_ok($cuerpo, 'deadbeef'), 'firma inventada');
ok(!lemon_firma_ok($cuerpo, ''), 'sin firma');

// 3. Alta: el mismo pedido entregado 3 veces → UNA web, sin factura BODA-
$tok = pendiente('ana-y-luis', $precio);
$lee = lector(['101' => pedido_ls($precio)]);
for ($i = 0; $i < 3; $i++) [$st, $r] = lemon_procesa_evento(evento('order_created', '101', $tok, 'ana-y-luis'), $lee);
ok($st === 200 && $r === 'creada', 'alta creada (3 entregas)');
ok(boda_existe('ana-y-luis'), 'la web existe');
ok(count(glob(dir_datos('bodas', '*'), GLOB_ONLYDIR) ?: []) === 1, 'una sola web tras 3 entregas');
ok(count(glob(dir_datos('correos', '*')) ?: []) === 1, 'un solo correo de bienvenida');
ok(facturas_emitidas() === 0, 'venta LS: ninguna factura BODA-');
$ped = lee_json(dir_datos('pedidos', 'ls_101.json'));
ok(($ped['factura'] ?? 'x') === '' && ($ped['pasarela'] ?? '') === 'lemon', 'pedido LS sin factura y con pasarela');
ok(($ped['importe']['total'] ?? 0) === $precio, 'importe del pedido LS');
ok((lee_json(dir_datos('ls_tokens', $tok . '.json'))['order_id'] ?? '') === '101', 'token atado al pedido');
ok(!is_dir(dir_datos('pendientes', $tok)), 'pendiente consumido');

// 4. Segundo pedido con el mismo token → duplicado, sin otra web
[$st, $r] = lemon_procesa_evento(evento('order_created', '102', $tok, 'ana-y-luis'), lector(['102' => pedido_ls($precio)]));
ok($r === 'duplicado', 'segundo cobro del mismo token = duplicado');
ok(count(glob(dir_datos('bodas', '*'), GLOB_ONLYDIR) ?: []) === 1, 'el duplicado no crea web');

// 5. Importe manipulado, descuento, otra variante, modo live → no-conforme, sin web
foreach ([['total' => $precio - 100], ['discount_total' => 500], ['first_order_item' => ['variant_id' => 1]], ['test_mode' => false],
          ['store_id' => 1], ['currency' => 'USD'], ['status' => 'pending'], ['first_order_item' => ['quantity' => 2]]] as $i => $cambio) {
    $slug = 'mal-' . $i;
    $t = pendiente($slug, $precio);
    [$st, $r] = lemon_procesa_evento(evento('order_created', (string) (200 + $i), $t, $slug), lector([(string) (200 + $i) => pedido_ls($precio, $cambio)]));
    ok($r === 'no-conforme' && !boda_existe($slug), 'no-conforme: ' . json_encode($cambio));
    // Estado final: otra entrega del mismo pedido (ya correcto) no lo reabre
    [$st, $r] = lemon_procesa_evento(evento('order_created', (string) (200 + $i), $t, $slug), lector([(string) (200 + $i) => pedido_ls($precio)]));
    ok($r === 'no-conforme' && !boda_existe($slug), 'no-conforme es final: ' . json_encode($cambio));
}
// custom_data con otro slug (manipulado) → no-conforme
$t = pendiente('slug-bueno', $precio);
[$st, $r] = lemon_procesa_evento(evento('order_created', '300', $t, 'otro-slug'), lector(['300' => pedido_ls($precio)]));
ok($r === 'no-conforme', 'slug de custom distinto del pedido = no-conforme');

// 6. Sin relectura de la API → 503 (LS reintenta), sin tocar nada; evento ajeno → 200 ignorado
$t = pendiente('sin-api', $precio);
[$st, $r] = lemon_procesa_evento(evento('order_created', '400', $t, 'sin-api'), lector([]));
ok($st === 503 && !is_file(dir_datos('pedidos', 'ls_400.json')), 'sin relectura: 503 y nada escrito');
$ev = evento('order_created', '401', $t, 'sin-api');
$ev['meta']['custom_data']['producto'] = 'otro';
ok(lemon_procesa_evento($ev, lector(['401' => pedido_ls($precio)]))[1] === 'ajeno', 'producto ajeno se ignora');

// 7. Reembolso: marca, avisa y NO borra la web; es final
[$st, $r] = lemon_procesa_evento(evento('order_refunded', '101', $tok, 'ana-y-luis'), lector(['101' => pedido_ls($precio, ['status' => 'refunded', 'refunded' => true])]));
ok($r === 'reembolsado', 'reembolso marcado');
ok(boda_existe('ana-y-luis'), 'el reembolso no borra la web');
[$st, $r] = lemon_procesa_evento(evento('order_created', '101', $tok, 'ana-y-luis'), $lee);
ok($r === 'reembolsado', 'un order_created posterior no reabre el reembolso');
// Evento de reembolso cuya relectura NO dice reembolsado → no se marca
$t = pendiente('falso-reembolso', $precio);
lemon_procesa_evento(evento('order_created', '500', $t, 'falso-reembolso'), lector(['500' => pedido_ls($precio)]));
[$st, $r] = lemon_procesa_evento(evento('order_refunded', '500', $t, 'falso-reembolso'), lector(['500' => pedido_ls($precio)]));
ok($r === 'sin-reembolso' && (lee_json(dir_datos('pedidos', 'ls_500.json'))['estado'] ?? '') === 'creada', 'reembolso no confirmado por la API no marca');
// Reembolso que llega ANTES del alta → final: el alta posterior no publica
$t = pendiente('reembolso-antes', $precio);
lemon_procesa_evento(evento('order_refunded', '600', $t, 'reembolso-antes'), lector(['600' => pedido_ls($precio, ['status' => 'refunded'])]));
[$st, $r] = lemon_procesa_evento(evento('order_created', '600', $t, 'reembolso-antes'), lector(['600' => pedido_ls($precio)]));
ok($r === 'reembolsado' && !boda_existe('reembolso-antes'), 'reembolso previo al alta bloquea el alta');

// 8. Administración: factura imposible para LS; correo con su tercera rama (no el texto del regalo)
try { emite_factura(['pasarela' => 'lemon']); $lanza = false; } catch (LogicException $e) { $lanza = true; }
ok($lanza, 'emite_factura se niega con una venta LS');
ok(facturas_emitidas() === 0, 'ninguna factura en todo el recorrido');
$cfg = config_inicial();
$cfg['fecha'] = date('Y-m-d', strtotime('+200 days'));
$base = ['slug' => 'x', 'email' => 'a@b.c', 'aceptacion' => ['fecha' => date('c'), 'desistimiento' => 'd']];
$tl = texto_bienvenida($base + ['pasarela' => 'lemon', 'factura' => ''], $cfg, 'https://enlace');
ok(strpos($tl, 'Lemon Squeezy') !== false && stripos($tl, 'regala') === false && strpos($tl, 'Al comprar') !== false, 'correo LS: rama propia, sin regalo');
$tr = texto_bienvenida($base + ['factura' => ''], $cfg, 'https://enlace');
ok(stripos($tr, 'regala') !== false && strpos($tr, 'Lemon') === false, 'correo regalo intacto');
$tf = texto_bienvenida($base + ['factura' => 'BODA-2026-0001'], $cfg, 'https://enlace');
ok(strpos($tf, 'BODA-2026-0001') !== false && stripos($tf, 'regala') === false, 'correo Stripe con factura intacto');

// 9. Pasarela: por defecto Lemon; configuración incompleta = no a la venta
ok(pasarela() === 'lemon' && lemon_configurada() && lemon_test(), 'pasarela lemon configurada en test');
// Candado del modo test: una IP cualquiera sin sesión del estudio no abre el pago (tarjeta de prueba = web gratis)
$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
ok(!lemon_checkout_permitido(), 'modo test: IP desconocida sin sesión del estudio no abre checkout');

// 10. Corte a mitad del alta: la web ya escrita (pedido.json + config.json) y el pedido en 'cobrada'.
//     El reintento la reconoce como suya: nada de «-2», y el correo acaba saliendo.
$t = pendiente('corte', $precio);
$ped = ['session_id' => 'ls_700', 'pasarela' => 'lemon', 'slug' => 'corte', 'token' => $t, 'creado' => date('c'), 'email' => 'p@example.com',
    'nombre' => '', 'importe' => ['base' => 0, 'iva' => 0, 'total' => $precio], 'ls' => ['test' => true], 'aceptacion' => null, 'atelier' => '', 'factura' => '', 'estado' => 'cobrada'];
escribe_json(dir_datos('pedidos', 'ls_700.json'), $ped);
escribe_json(dir_datos('ls_tokens', $t . '.json'), ['order_id' => '700']);
escribe_json(dir_boda('corte') . '/pedido.json', ['session_id' => 'ls_700']);
escribe_json(dir_boda('corte') . '/config.json', lee_json(dir_datos('pendientes', $t, 'config.json')));
$correos = count(glob(dir_datos('correos', '*')) ?: []);
[$st, $r] = lemon_procesa_evento(evento('order_created', '700', $t, 'corte'), lector(['700' => pedido_ls($precio)]));
ok($r === 'creada' && !boda_existe('corte-2'), 'reintento tras corte: misma web, sin «-2»');
ok(count(glob(dir_datos('correos', '*')) ?: []) === $correos + 1, 'reintento tras corte: el correo sale');
ok(!empty((lee_json(dir_boda('corte') . '/pedido.json') ?? [])['test']), 'la web de un pedido de prueba queda marcada como test');

// Limpieza
borra_arbol_test($tmp);
function borra_arbol_test(string $d): void {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    @rmdir($d);
}
echo ($fallos ? "ROJO" : "VERDE") . ": " . ($n - $fallos) . "/$n\n";
exit($fallos ? 1 : 0);
