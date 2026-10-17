<?php
// Compra de prueba de 1 € del estudio (BOD-62, revisión previa #422) de punta a punta por HTTP: la app en `php -S`
// (router_prueba.php) y Lemon simulado (lemon_simulador.php), en LIVE. Un comprador nunca recibe la cookie del estudio
// ni el precio de prueba; armada en el panel, solo esa sesión y ese nombre pagan 1 €, una vez; caducada, de otra sesión
// u otro nombre = 409 (nunca 125 € en silencio); el alta y la bienvenida salen, el Padrino no la cuenta y el reembolso
// la archiva. Tope de 3 armados. Uso: php tests/prueba_pago_test.php (sale con 1 si falla)

declare(strict_types=1);

$tmp = sys_get_temp_dir() . '/bodas_prueba_test_' . bin2hex(random_bytes(4));
$web = sys_get_temp_dir() . '/bodas_prueba_web_' . bin2hex(random_bytes(4));
$ls = sys_get_temp_dir() . '/bodas_prueba_ls_' . bin2hex(random_bytes(4));
foreach ([$tmp, $web, $ls] as $d) mkdir($d, 0700, true);
const FIRMA = 'secreto-de-prueba-0123456789abcdef0123';
const CLAVE_ESTUDIO = 'clave-del-estudio-de-prueba';
file_put_contents($tmp . '/secrets.php', '<?php return ' . var_export([
    'pasarela' => 'lemon', 'lemon_api_key' => 'clave-de-prueba', 'lemon_webhook_secret' => FIRMA,
    'lemon_tienda' => '483461', 'lemon_variante' => '2184831', 'lemon_producto' => '1399168', 'lemon_test' => false,
    'telegram_token' => 'x', 'telegram_chat' => '1',
    'empresa' => ['titular' => 'Titular de Prueba', 'nif' => '00000000T', 'domicilio' => 'Calle de Prueba 1, Madrid'],
], true) . ';');
define('SIN_CONFIG_LOCAL', true);
define('DATA_DIR', $tmp);
define('BASE_DOMAIN', 'bodaenlace.com');
define('CREATOR_HOST', 'bodaenlace.com');
define('CREATOR_BASE', '');
define('CORREO_A_FICHERO', true);
$raiz = dirname(__DIR__);
foreach (['core', 'schema', 'render', 'foto', 'alta', 'mapa', 'cortesia', 'estudio'] as $m) require_once $raiz . '/app/' . $m . '.php';
asegura_dir(estudio_dir());
escribe_json(estudio_dir('clave.json'), ['hash' => password_hash(CLAVE_ESTUDIO, PASSWORD_DEFAULT), 'gen' => 0]);

$fallos = 0;
function ok(bool $c, string $q): void { global $fallos; if (!$c) { $fallos++; echo "FALLA: $q\n"; } else echo "ok: $q\n"; }

$pApp = 18000 + random_int(0, 499);
$pLs = 18500 + random_int(0, 499);
$env = array_merge(getenv(), ['BODAS_TEST_DATA' => $tmp, 'BODAS_TEST_LEMON' => 'http://127.0.0.1:' . $pLs, 'BODAS_TEST_LEMON_DIR' => $ls]);
$log = [1 => ['file', $tmp . '/srv.log', 'a'], 2 => ['file', $tmp . '/srv.log', 'a']];
$srv = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $pApp, '-t', $web, __DIR__ . '/router_prueba.php'], $log, $pp, null, $env);
$sim = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $pLs, '-t', $web, __DIR__ . '/lemon_simulador.php'], $log, $ps, null, $env);
foreach ([$pApp, $pLs] as $p) for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $p); $i++) usleep(100000);

/** Petición a la app con la cookie del estudio que se le pase (a mano: la app la marca Secure). [status, cuerpo, cookie nueva] */
function pide(string $metodo, string $ruta, ?array $campos = null, string $cookie = '', array $extra = []): array {
    global $pApp;
    $ch = curl_init('http://127.0.0.1:' . $pApp . $ruta);
    $cab = [];
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_CUSTOMREQUEST => $metodo,
        CURLOPT_HTTPHEADER => array_merge(['Host: bodaenlace.com'], $cookie !== '' ? ['Cookie: ' . $cookie] : [], $extra),
        CURLOPT_HEADERFUNCTION => function ($ch, $l) use (&$cab) { $cab[] = rtrim($l); return strlen($l); }]);
    if ($campos !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($campos['_crudo'] ?? null) ? $campos['_crudo'] : http_build_query($campos));
    $r = (string) curl_exec($ch);
    $st = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $nueva = '';
    foreach ($cab as $l) if (preg_match('/^Set-Cookie: (__Host-bwe=[^;]+)/i', $l, $m) && strpos($m[1], '=deleted') === false) $nueva = $m[1];
    return [$st, $r, $nueva];
}
function csrf_de(string $html): string { return preg_match('/name="csrf" value="([a-f0-9]{32})"/', $html, $m) ? $m[1] : ''; }
/** Sesión nueva del estudio: GET (cookie + csrf) y POST de la contraseña (regenera el id). Devuelve la cookie. */
function entra_estudio(): string {
    [, $h, $c] = pide('GET', '/estudio');
    [$st, , $c2] = pide('POST', '/estudio', ['csrf' => csrf_de($h), 'clave' => CLAVE_ESTUDIO], $c);
    return $st === 303 && $c2 !== '' ? $c2 : '';
}
function arma(string $cookie, string $slug): string {
    [, $h] = pide('GET', '/estudio/padrino', null, $cookie);   // el csrf de la sesión, aunque la página de prueba ya no pinte el formulario
    [, $h] = pide('POST', '/estudio/prueba-pago', ['csrf' => csrf_de($h), 'accion' => 'armar', 'slug' => $slug], $cookie);
    return $h;
}
function pagar(string $slug, string $cookie = ''): array {
    $c = config_inicial();
    $c['pareja']['nombre1'] = 'Prueba'; $c['pareja']['nombre2'] = 'Estudio'; $c['pareja']['email'] = 'owner@example.com';
    $c['fecha'] = date('Y-m-d', strtotime('+200 days'));
    $c['ceremonia']['lugar'] = 'Ayuntamiento'; $c['ceremonia']['hora'] = '12:00';
    [$st, $r, $nueva] = pide('POST', '/api/pagar', ['config' => json_encode($c), 'slug' => $slug, 'acepto_condiciones' => 'si', 'acepto_desistimiento' => 'si'], $cookie);
    return [$st, json_decode($r, true) ?? ['crudo' => substr($r, 0, 300)], $nueva];
}
/** Cuerpo del último checkout que recibió el Lemon simulado. */
function ultimo_checkout(): array {
    global $ls;
    $f = glob($ls . '/checkout_*.json') ?: [];
    usort($f, fn($a, $b) => (int) preg_replace('/\D/', '', basename($a)) <=> (int) preg_replace('/\D/', '', basename($b)));
    return $f ? (json_decode((string) file_get_contents(end($f)), true) ?? []) : [];
}
function webhook(string $evento, string $id, array $custom): array {
    $cuerpo = json_encode(['meta' => ['event_name' => $evento, 'custom_data' => $custom], 'data' => ['type' => 'orders', 'id' => $id]]);
    return pide('POST', '/api/lemon', ['_crudo' => $cuerpo], '', ['Content-Type: application/json', 'X-Signature: ' . hash_hmac('sha256', $cuerpo, FIRMA)]);
}
function orden_ls(string $id, int $total, array $cambia = []): void {
    global $ls;
    file_put_contents($ls . '/order_' . $id . '.json', json_encode(array_replace_recursive(['store_id' => 483461, 'currency' => 'EUR', 'total' => $total, 'tax_inclusive' => true,
        'tax' => (int) round($total * 21 / 121), 'discount_total' => 0, 'status' => 'paid', 'test_mode' => false, 'user_email' => 'owner@example.com', 'user_name' => 'Owner',
        'order_number' => 4001, 'identifier' => 'u', 'urls' => ['receipt' => 'https://app.lemonsqueezy.com/my-orders/r1'],
        'first_order_item' => ['variant_id' => 2184831, 'product_id' => 1399168, 'quantity' => 1]], $cambia)));
}
function correos_a(string $para): int {
    return count(array_filter(glob(dir_datos('correos', '*')) ?: [], fn($f) => strpos((string) file_get_contents($f), "Para: $para\n") === 0));
}

// ---------------------------------------------------------------- 1. un comprador: precio normal y sin cookie del estudio
[$st, $j, $c] = pagar('pareja-normal');
$co = ultimo_checkout();
ok($st === 200 && !empty($j['ok']), 'sin estudio: el pago se abre (' . $st . ')');
ok(($co['data']['attributes']['custom_price'] ?? 0) === precio_total_cent(), 'sin estudio: precio normal (' . ($co['data']['attributes']['custom_price'] ?? '?') . ')');
ok($c === '', 'sin armado: el comprador no recibe la cookie del estudio');

// ---------------------------------------------------------------- 2. armada: un comprador sin sesión sigue igual
$est = entra_estudio();
ok($est !== '', 'login del estudio');
$h = arma($est, 'prueba-uno');
ok(strpos($h, 'Armada para «prueba-uno»') !== false, 'se arma la compra de prueba');
ok(is_file(prueba_pago_fichero()), 'armado guardado en el servidor');
[$st, , $c] = pagar('prueba-uno');
ok($st === 200 && (ultimo_checkout()['data']['attributes']['custom_price'] ?? 0) === precio_total_cent(), 'armada, pero sin sesión del estudio: precio normal');
ok($c === '', 'armada: un comprador sigue sin recibir la cookie del estudio');
ok(is_file(prueba_pago_fichero()), 'el comprador no gasta el armado');
@unlink(dir_datos('reservas', 'prueba-uno.json'));   // la reserva del comprador de arriba (la soltaría la caducidad)

// ---------------------------------------------------------------- 3. otro nombre de web: 409, sin cobrar el precio entero
$antes = count(glob($ls . '/checkout_*.json') ?: []);
[$st, $j] = pagar('otra-web', $est);
ok($st === 409 && strpos((string) ($j['error'] ?? ''), 'prueba-uno') !== false, 'estudio + otro nombre: 409 (' . $st . ')');
ok(count(glob($ls . '/checkout_*.json') ?: []) === $antes, 'el 409 no abre ningún pago');
ok(is_file(prueba_pago_fichero()), 'el 409 no gasta el armado');

// ---------------------------------------------------------------- 4. la buena: 1 €, una vez
[$st, $j] = pagar('prueba-uno', $est);
$co = ultimo_checkout();
ok($st === 200 && !empty($j['ok']), 'estudio + nombre armado: el pago se abre (' . $st . ' ' . json_encode($j) . ')');
ok(($co['data']['attributes']['custom_price'] ?? 0) === PRUEBA_PAGO_CENT, 'estudio + nombre armado: 1 € (' . ($co['data']['attributes']['custom_price'] ?? '?') . ')');
ok(!is_file(prueba_pago_fichero()) && count(glob(estudio_dir('prueba_pago_gastada_*.json')) ?: []) === 1, 'el armado se gasta al usarlo');
$custom = (array) ($co['data']['attributes']['checkout_data']['custom'] ?? []);
$tok = (string) ($custom['token'] ?? '');
$meta = lee_json(dir_datos('pendientes', $tok, 'meta.json')) ?? [];
ok(!empty($meta['prueba']) && ($meta['precio_cent'] ?? 0) === PRUEBA_PAGO_CENT, 'meta.json del servidor: prueba y precio congelado de 1 €');
ok(!array_key_exists('prueba', $custom), 'la marca no viaja a LS: solo vive en el servidor');
[$st, , ] = pagar('prueba-dos', $est);
ok($st === 200 && (ultimo_checkout()['data']['attributes']['custom_price'] ?? 0) === precio_total_cent(), 'gastada: el siguiente pago del estudio vuelve al precio normal');
$log = (string) @file_get_contents(estudio_dir('log-' . date('Y-m') . '.jsonl'));
ok(strpos($log, '"prueba-pago-armada"') !== false && strpos($log, '"prueba-pago-gastada"') !== false, 'armado y consumo, en el log del estudio');

// ---------------------------------------------------------------- 5. el aviso de pago: alta, bienvenida, Padrino sin contarla
orden_ls('9001', PRUEBA_PAGO_CENT);
[$st] = webhook('order_created', '9001', $custom);
$ped = lee_json(dir_datos('pedidos', 'ls_9001.json')) ?? [];
ok($st === 200 && ($ped['estado'] ?? '') === 'creada', 'webhook de 1 €: la web se publica (' . $st . ' ' . ($ped['estado'] ?? '?') . ' ' . implode(',', (array) ($ped['motivos'] ?? [])) . ')');
ok(!empty($ped['prueba']), 'pedido marcado como prueba');
ok(!empty((lee_json(dir_boda('prueba-uno') . '/pedido.json') ?? [])['prueba']), 'pedido.json de la boda marcado como prueba');
ok(correos_a('owner@example.com') === 1, 'sale el correo de bienvenida');
$res = padrino_resumen();
ok(!in_array('prueba-uno', array_column($res['bodas'], 'slug'), true), 'el Padrino no ve la boda de prueba');
$pp = array_values(array_filter($res['pedidos'], fn($p) => ($p['total_cent'] ?? 0) === PRUEBA_PAGO_CENT));
ok(count($pp) === 1 && $pp[0]['test'] === true, 'el pedido de 1 € le llega al Padrino como prueba');

// ---------------------------------------------------------------- 6. el reembolso total la archiva
orden_ls('9001', PRUEBA_PAGO_CENT, ['status' => 'refunded', 'refunded' => true, 'refunded_amount' => PRUEBA_PAGO_CENT]);
[$st] = webhook('order_refunded', '9001', $custom);
ok($st === 200 && ((lee_json(dir_boda('prueba-uno') . '/config.json') ?? [])['_estado'] ?? '') === 'archivada', 'reembolso total: la web de prueba queda archivada');
ok(((lee_json(dir_datos('pedidos', 'ls_9001.json')) ?? [])['estado'] ?? '') === 'reembolsado', 'y el pedido, reembolsado');

// ---------------------------------------------------------------- 7. caducada y de otra sesión: 409
arma($est, 'prueba-tres');
$a = lee_json(prueba_pago_fichero());
$a['hasta'] = time() - 1;
escribe_json(prueba_pago_fichero(), $a);
[$st, $j] = pagar('prueba-tres', $est);
ok($st === 409 && strpos((string) ($j['error'] ?? ''), 'caducado') !== false && !is_file(prueba_pago_fichero()), 'caducada: 409 y se borra el armado');
arma($est, 'prueba-cuatro');
$otra = entra_estudio();
[$st, $j] = pagar('prueba-cuatro', $otra);
ok($st === 409 && strpos((string) ($j['error'] ?? ''), 'otra sesión') !== false, 'armada en otra sesión del estudio: 409');

// ---------------------------------------------------------------- 8. tope de 3
$h = arma($est, 'prueba-cinco');
ok(strpos($h, 'Ya se han armado las 3') !== false, 'el cuarto armado se rechaza');
ok(((lee_json(prueba_pago_fichero()) ?? [])['slug'] ?? '') === 'prueba-cuatro', 'y no pisa el armado vigente');

proc_terminate($srv);
proc_terminate($sim);
echo $fallos ? "\n$fallos FALLO(S)\n" : "\nTodo bien.\n";
exit($fallos ? 1 : 0);
