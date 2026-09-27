<?php
// Plano de mesas de punta a punta por HTTP (F1d, revisión previa #133): la app en `php -S` (router_prueba.php) y la API
// de Lemon simulada en otro (lemon_simulador.php), sin red. Desde el 27-sep-2026 (owner) el plano va GRATIS e incluido
// en todos los packs: sin comprar nada, crear mesas, sentar, recargar, cancelación, hoja para el restaurante y catering
// con mesa; /panel/extra con «mesas» = 409; el webhook de un extra (álbum) sigue activando por HTTP; el reembolso de una
// compra vieja del plano no lo apaga; y una web archivada no lo tiene. Uso: php tests/mesas_test.php (sale con 1 si falla)

declare(strict_types=1);

$tmp = sys_get_temp_dir() . '/bodas_mesas_test_' . bin2hex(random_bytes(4));
$web = sys_get_temp_dir() . '/bodas_mesas_web_' . bin2hex(random_bytes(4));
$ls = sys_get_temp_dir() . '/bodas_mesas_ls_' . bin2hex(random_bytes(4));
foreach ([$tmp, $web, $ls] as $d) mkdir($d, 0700, true);
const FIRMA = 'secreto-de-prueba-0123456789abcdef0123';
file_put_contents($tmp . '/secrets.php', '<?php return ' . var_export([
    'pasarela' => 'lemon', 'lemon_api_key' => 'clave-de-prueba', 'lemon_webhook_secret' => FIRMA,
    'lemon_tienda' => '483461', 'lemon_variante' => '2169949', 'lemon_producto' => '1389266', 'lemon_test' => true,
    'lemon_test_ips' => ['127.0.0.1'],   // en modo test solo el estudio compra: esta IP hace de estudio
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
foreach (['core', 'schema', 'render', 'foto', 'alta', 'mapa', 'cortesia', 'estudio', 'borrador', 'invitados', 'creador', 'landing', 'vista_constructor', 'boda', 'galeria', 'proxy'] as $m) {
    require_once $raiz . '/app/' . $m . '.php';
}
$fallos = 0;
$n = 0;
function ok(bool $c, string $q): void { global $fallos, $n; $n++; if (!$c) { $fallos++; echo "FALLA: $q\n"; } }

// ---------------------------------------------------------------- la boda: pagada, con contraseña y 11 respuestas
$slug = 'mesas-prueba';
$host = $slug . '.bodaenlace.com';
$c = config_inicial();
$c['pareja']['nombre1'] = 'Ana'; $c['pareja']['nombre2'] = 'Luis'; $c['pareja']['email'] = 'pareja@example.com';
$c['fecha'] = date('Y-m-d', strtotime('+200 days'));
$c['ceremonia']['lugar'] = 'Ayuntamiento'; $c['ceremonia']['hora'] = '12:00';
$c = normaliza_config($c);
escribe_json(dir_boda($slug) . '/config.json', $c);
escribe_json(dir_boda($slug) . '/pedido.json', ['session_id' => 'ls_900', 'email' => 'pareja@example.com']);
escribe_json(dir_datos('pedidos', 'ls_900.json'), ['session_id' => 'ls_900', 'pasarela' => 'lemon', 'slug' => $slug, 'estado' => 'creada', 'ls' => ['test' => true]]);
escribe_json(panel_fichero($slug), ['hash' => password_hash('clave-de-prueba-1', PASSWORD_DEFAULT), 'gen' => 0]);
$pid = fn() => bin2hex(random_bytes(8));
$per = fn(string $nombre, string $menu, string $alergias = '', string $tipo = 'adulto') => ['id' => $pid(), 'nombre' => $nombre, 'tipo' => $tipo, 'menu' => $menu, 'menu_nombre' => ucfirst($menu), 'alergias' => $alergias];
$resp = fn(string $id, array $ps, bool $banquete = true, string $grupo = '') => ['id' => $id, 'fecha_envio' => date('c'), 'invitados' => $ps, 'asiste_ceremonia' => true,
    'asiste_banquete' => $banquete, 'contacto' => '600000000'] + ($grupo !== '' ? ['grupo' => $grupo] : []);
$A = [$per('Alba Ruiz', 'pescado', 'nueces'), $per('Bruno Ruiz', 'carne'), $per('Clara Ruiz', 'carne'), $per('Dani Ruiz', 'vegetariano')];
$B = [$per('Eva Gil', 'carne'), $per('Fer Gil', 'infantil', 'huevo', 'nino')];
$C = [$per('Gema Sol', 'carne'), $per('Hugo Sol', 'carne', 'marisco'), $per('Ines Sol', 'pescado'), $per('Juan Sol', 'carne')];
$D = [$per('Kike Mar', 'carne')];
escribe_json(dir_boda($slug) . '/guardado/rsvp.json', [$resp('ra', $A), $resp('rb', $B), $resp('rc', $C, true, 'gsol'), $resp('rd', $D, false)]);

// ---------------------------------------------------------------- servidores
$pApp = 18000 + random_int(0, 499);
$pLs = 18500 + random_int(0, 499);
$env = array_merge(getenv(), ['BODAS_TEST_DATA' => $tmp, 'BODAS_TEST_LEMON' => 'http://127.0.0.1:' . $pLs, 'BODAS_TEST_LEMON_DIR' => $ls]);
$log = [1 => ['file', $tmp . '/srv.log', 'a'], 2 => ['file', $tmp . '/srv.log', 'a']];
$srv = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $pApp, '-t', $web, __DIR__ . '/router_prueba.php'], $log, $pp, null, $env);
$sim = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $pLs, '-t', $web, __DIR__ . '/lemon_simulador.php'], $log, $ps, null, $env);
foreach ([$pApp, $pLs] as $p) for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $p); $i++) usleep(100000);

$cookie = '';
/** Petición a la app. Devuelve [status, cuerpo, cabeceras]. La cookie de sesión se lleva a mano (la app la marca Secure). */
function pide(string $metodo, string $host, string $ruta, ?array $campos = null, array $extra = []): array {
    global $pApp, $cookie;
    $ch = curl_init('http://127.0.0.1:' . $pApp . $ruta);
    $hdr = array_merge(['Host: ' . $host], $cookie !== '' ? ['Cookie: ' . $cookie] : [], $extra);
    $cab = [];
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => $hdr, CURLOPT_CUSTOMREQUEST => $metodo,
        CURLOPT_HEADERFUNCTION => function ($ch, $l) use (&$cab) { $cab[] = rtrim($l); return strlen($l); }]);
    if ($campos !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($campos['_crudo'] ?? null) ? $campos['_crudo'] : http_build_query($campos));
    $r = (string) curl_exec($ch);
    $st = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    foreach ($cab as $l) if (preg_match('/^Set-Cookie: (bw_[a-f0-9]+=[^;]+)/i', $l, $m) && strpos($m[1], '=deleted') === false) $cookie = $m[1];
    return [$st, $r, implode("\n", $cab)];
}
function csrf_de(string $html): string { return preg_match('/name="csrf" value="([a-f0-9]{32})"/', $html, $m) ? $m[1] : ''; }
function webhook(string $evento, string $id, array $custom): array {
    $cuerpo = json_encode(['meta' => ['event_name' => $evento, 'custom_data' => $custom], 'data' => ['type' => 'orders', 'id' => $id]]);
    return pide('POST', 'bodaenlace.com', '/api/lemon', ['_crudo' => $cuerpo], ['Content-Type: application/json', 'X-Signature: ' . hash_hmac('sha256', $cuerpo, FIRMA)]);
}
function orden_ls(string $id, int $total, array $cambia = []): void {
    global $ls;
    file_put_contents($ls . '/order_' . $id . '.json', json_encode(array_replace_recursive(['store_id' => 483461, 'currency' => 'EUR', 'total' => $total, 'tax' => (int) round($total * 21 / 121),
        'discount_total' => 0, 'status' => 'paid', 'test_mode' => true, 'user_email' => 'pareja@example.com', 'user_name' => 'Ana', 'order_number' => 3001,
        'identifier' => 'u', 'urls' => ['receipt' => 'https://app.lemonsqueezy.com/my-orders/r1'], 'first_order_item' => ['variant_id' => 2169949, 'product_id' => 1389266, 'quantity' => 1]], $cambia)));
}
function plano(): array { global $slug; return lee_json(dir_boda($slug) . '/guardado/mesas.json') ?? []; }

// ---------------------------------------------------------------- 1. sin sesión, login
[$st, , $cab] = pide('GET', $host, '/panel/mesas');
ok($st === 302 && strpos($cab, 'Location: /panel/entrar') !== false, 'sin sesión: /panel/mesas manda al login');
[$st] = pide('POST', $host, '/panel/entrar', ['clave' => 'clave-de-prueba-1']);
ok($st === 302 && $cookie !== '', 'login del panel');

// ---------------------------------------------------------------- 2. sin comprar nada: el plano ya está, precio en ningún sitio
[$st, $h, $cab] = pide('GET', $host, '/panel/mesas');
$csrf = csrf_de($h);
ok($st === 200 && strpos($h, 'Crear mesa') !== false && strpos($h, 'Comprar por') === false && strpos($h, '€') === false && strpos($h, 'data-extra-compra') === false, 'sin compra: la herramienta, sin precio ni compra');
ok(stripos($cab, 'Cache-Control: private, no-store') !== false, '/panel/mesas con no-store');
ok(strpos($h, 'Recibo de la compra') === false, 'sin enlace a un recibo de compra');
[, $hc] = pide('GET', $host, '/panel/catering');
ok(strpos($hc, '<th>Mesa</th>') === false, 'sin ninguna mesa creada: catering sin columna Mesa');
[, $hp] = pide('GET', $host, '/panel');
ok(strpos($hp, 'href="/panel/mesas"') !== false, 'el panel enlaza al plano de mesas');

// ---------------------------------------------------------------- 3. el plano no se vende: /panel/extra
[$st] = pide('POST', $host, '/panel/extra', ['csrf' => $csrf, 'clave' => 'fiesta', 'acepto_extra' => 'si']);
ok($st === 400, 'clave fuera de la lista: 400 (' . $st . ')');
[$st] = pide('POST', $host, '/panel/extra', ['clave' => 'mesas', 'acepto_extra' => 'si']);
ok($st === 403, 'sin CSRF: 403');
[$st, $j] = pide('POST', $host, '/panel/extra', ['csrf' => $csrf, 'clave' => 'mesas', 'acepto_extra' => 'si', 'precio_cent' => 1]);
ok($st === 409 && strpos((string) $j, 'no est') !== false && !glob(dir_datos('extras', '*.json')) && !is_file($ls . '/checkout_1.json'), 'el plano de mesas: 409 «no está a la venta», sin pedido ni checkout (' . $st . ')');
[$st] = pide('POST', $host, '/panel/extra', ['csrf' => $csrf, 'clave' => 'idiomas', 'acepto_extra' => 'si']);
ok($st === 409, 'extra de la lista que aún no se vende: 409');
// (el 422 de «sin la casilla» ya no se alcanza por HTTP: ningún extra está a la venta; su tubería la cubre extras_test.php)

// ---------------------------------------------------------------- 4. webhook ×3 por HTTP de un extra (álbum): una sola activación
// Sin nada a la venta, el pedido del servidor se escribe como lo dejaría panel_extra(): la tubería del webhook sigue viva
$tok = bin2hex(random_bytes(16));
escribe_json(dir_datos('extras', $tok . '.json'), ['tipo' => 'extra', 'clave' => 'album', 'slug' => $slug, 'creado' => time(), 'precio_cent' => 1900, 'pasarela' => 'lemon', 'estado' => 'abierta',
    'aceptacion' => ['fecha' => date('c'), 'version' => textos_legales()['version'], 'casilla' => texto_extra(), 'extra' => EXTRAS['album']['nombre'], 'vendedor' => 'Lemon Squeezy', 'precio_cent' => 1900]]);
$custom = ['token' => $tok, 'slug' => $slug, 'producto' => 'bodas', 'tipo' => 'extra', 'clave' => 'album'];
orden_ls('7001', 1900);
$tg0 = count(glob(dir_datos('telegram', '*')) ?: []);
$res = [];
for ($i = 0; $i < 3; $i++) { [$st, $r] = webhook('order_created', '7001', $custom); $res[] = $st . ':' . ((json_decode($r, true) ?? [])['resultado'] ?? ''); }
ok($res === ['200:creada', '200:creada', '200:creada'], 'webhook ×3: ' . implode(' ', $res));
$bp = lee_json(dir_boda($slug) . '/pedido.json') ?? [];
ok(($bp['extras']['album']['pedido'] ?? '') === 'ls_7001' && count(glob(dir_datos('telegram', '*')) ?: []) === $tg0 + 1, 'extras.album activo una sola vez (un aviso)');
[$st, $r] = pide('POST', 'bodaenlace.com', '/api/lemon', ['_crudo' => '{"meta":{}}'], ['X-Signature: 00']);
ok($st === 401, 'webhook sin firma buena: 401');

// ---------------------------------------------------------------- 5. crear mesas, sentar a 10, recargar
[$st, $h] = pide('GET', $host, '/panel/mesas');
ok($st === 200 && strpos($h, 'Crear mesa') !== false, 'la herramienta sigue ahí');
ok(substr_count($h, 'data-persona=') === 10 && strpos($h, 'Kike Mar') === false, 'sin mesa: los 10 que van al banquete (no el que no va)');
pide('POST', $host, '/panel/mesas', ['csrf' => $csrf, 'accion' => 'crear', 'nombre' => 'Familia', 'plazas' => 6]);
[$st, , $cab] = pide('POST', $host, '/panel/mesas', ['csrf' => $csrf, 'accion' => 'crear', 'nombre' => '', 'plazas' => 5]);
ok($st === 303 && strpos($cab, 'Location: /panel/mesas') !== false, 'crear mesa: 303 a la página');
[$m1, $m2] = array_column(plano()['mesas'] ?? [[], []], 'id') + [null, null];
$ids = fn(array $g) => array_column($g, 'id');
$sienta = fn(array $personas, string $mesa) => pide('POST', $host, '/panel/mesas', ['csrf' => $csrf, 'accion' => 'sentar', 'mesa' => $mesa, 'personas' => $personas]);
[$st, , $cab] = $sienta($ids($A), $m1);
ok($st === 303 && strpos($cab, '?e=') === false, 'todo el grupo A (4) a la mesa 1');
$sienta($ids($B), $m1);
$sienta($ids($C), $m2);
[, , $cab] = $sienta($ids($A), $m2);
ok(strpos($cab, '?e=no-caben') !== false && count(array_filter(plano()['sitios'], fn($s) => $s['mesa'] === $m1)) === 6, 'mover a 4 a una mesa con 1 libre: no caben, no se mueve a nadie');
[, , $cab] = $sienta($ids($D), $m2);
ok(strpos($cab, '?e=persona') !== false, 'sentar a quien no va al banquete: rechazado');
[, , $cab] = $sienta(['zzzz'], $m2);
ok(strpos($cab, '?e=persona') !== false, 'un id inventado: rechazado');
[, , $cab] = pide('POST', $host, '/panel/mesas', ['csrf' => $csrf, 'accion' => 'editar', 'mesa' => $m1, 'nombre' => 'Familia', 'plazas' => 4]);
ok(strpos($cab, '?e=plazas-ocupadas') !== false, 'bajar plazas por debajo de los sentados: rechazado');
// Recargar: siguen donde estaban, resueltos por id
[, $h] = pide('GET', $host, '/panel/mesas');
$pl = plano();
ok(count($pl['sitios']) === 10 && !array_diff($ids($A), array_keys(array_filter($pl['sitios'], fn($s) => $s['mesa'] === $m1))), '10 sentados en 2 mesas; mesas.json por id de persona');
ok(strpos($h, '6 / 6') !== false && strpos($h, '4 / 5') !== false && substr_count($h, 'class="mesa-grupo"') === 0, 'al recargar: 6/6 y 4/5, nadie sin mesa');
ok(strpos($h, 'Mesa 2') !== false, 'una mesa sin nombre sale como «Mesa 2»');

// ---------------------------------------------------------------- 6. catering con Mesa (por id) y hoja para el restaurante
[, $hc] = pide('GET', $host, '/panel/catering');
ok(strpos($hc, '<th>Mesa</th>') !== false, 'catering: vuelve la columna Mesa');
ok(preg_match('~Alba Ruiz</td><td class="catering-mesa">Familia</td>~', $hc) === 1 && preg_match('~Hugo Sol</td><td class="catering-mesa">Mesa 2</td>~', $hc) === 1
    && preg_match('~Fer Gil <span class="muted">\(niño/a\)</span></td><td class="catering-mesa">Familia</td>~', $hc) === 1, 'catering: la mesa de cada alergia, correcta');
[$st, $hi, $cab] = pide('GET', $host, '/panel/mesas/imprimir');
$bloques = preg_split('~<div class="mesa-hoja">~', $hi);
$b1 = (string) (array_values(array_filter($bloques, fn($b) => strpos($b, '<h2 class="panel-h2">Familia') === 0))[0] ?? '');
$b2 = (string) (array_values(array_filter($bloques, fn($b) => strpos($b, '<h2 class="panel-h2">Mesa 2') === 0))[0] ?? '');
ok($st === 200 && stripos($cab, 'no-store') !== false && strpos($hi, 'data-imprimir') !== false, 'hoja para el restaurante: 200, no-store, botón de imprimir');
ok(strpos($b1, 'Alba Ruiz') !== false && strpos($b1, 'nueces') !== false && strpos($b1, 'Pescado') !== false && strpos($b1, 'huevo') !== false && strpos($b1, 'Hugo Sol') === false, 'hoja: la mesa 1 con sus personas, menús y alergias');
ok(strpos($b2, 'Hugo Sol') !== false && strpos($b2, 'marisco') !== false && strpos($b2, 'Alba Ruiz') === false && strpos($b2, '4 personas') !== false, 'hoja: la mesa 2 con las suyas');

// ---------------------------------------------------------------- 7. cancelación: el grupo Sol reenvía sin Juan → aviso, nadie se reasigna solo
$C2 = [$per('Gema Sol', 'carne'), $per('Hugo Sol', 'carne', 'marisco'), $per('Ines Sol', 'pescado')];
muta_json(dir_boda($slug) . '/guardado/rsvp.json', function (array &$d) use ($resp, $C2) { rsvp_anade($d, $resp('rc2', $C2, true, 'gsol')); });
$antes = plano();
[, $h] = pide('GET', $host, '/panel/mesas');
ok(strpos($h, 'Ya no viene: <b>Juan Sol</b> estaba en <b>Mesa 2</b>') !== false, 'aviso: «Ya no viene: Juan Sol estaba en la Mesa 2»');
ok(substr_count($h, 'Ha vuelto a responder') === 3, 'los otros tres del grupo: «ha vuelto a responder», no «ya no viene»');
ok(plano() == $antes && substr_count($h, 'class="mesa-grupo"') === 1, 'nadie se reasigna solo: el plano no cambia y los 3 salen sin mesa');
[, $hc] = pide('GET', $host, '/panel/catering');
ok(preg_match('~Hugo Sol</td><td class="catering-mesa">Sin mesa</td>~', $hc) === 1, 'catering: la respuesta nueva de Hugo sale «Sin mesa» (no hereda la mesa por el nombre)');
[, $hi] = pide('GET', $host, '/panel/mesas/imprimir');
ok(strpos($hi, 'Juan Sol') === false && strpos($hi, 'ya no vienen') !== false, 'hoja: quien ya no viene no sale, y se avisa en pantalla');
pide('POST', $host, '/panel/mesas', ['csrf' => $csrf, 'accion' => 'levantar', 'persona' => $C[3]['id']]);
[, $h] = pide('GET', $host, '/panel/mesas');
ok(strpos($h, 'Juan Sol') === false, '«Quitar del plano» cierra el aviso');

// ---------------------------------------------------------------- 7b. respuesta general + la del enlace: «repetido» también al imprimir
muta_json(dir_boda($slug) . '/guardado/rsvp.json', function (array &$d) use ($resp, $per) { rsvp_anade($d, $resp('rgen', [$per('Hugo Sol', 'pescado', 'gluten')], true)); });
[, $hi] = pide('GET', $host, '/panel/mesas/imprimir');
ok(strpos($hi, 'aviso-repetido') !== false && strpos($hi, 'class="panel-aviso aviso-repetido"') !== false && substr_count($hi, 'class="rep">repetido') >= 2,
    'hoja: quien respondió dos veces sale «repetido» y hay un aviso que también se imprime');
$sin = (string) (array_values(array_filter(preg_split('~<div class="mesa-hoja">~', $hi), fn($b) => strpos($b, '<h2 class="panel-h2">Sin mesa') === 0))[0] ?? '');
ok($sin !== '' && strpos($sin, '<th>Alergias</th>') === false && strpos($sin, 'gluten') === false, 'hoja: «Sin mesa» sin alergias (van en el resumen para el catering)');

// ---------------------------------------------------------------- 8. el panel no puede escribir extras
$f = dir_boda($slug) . '/pedido.json';
$h0 = hash_file('sha256', $f);
[$st] = pide('POST', $host, '/panel/guardar', ['csrf' => $csrf, 'config' => json_encode($c + ['extras' => ['idiomas' => ['desde' => date('c'), 'pedido' => 'ls_1']], 'atelier' => ''])]);
ok($st === 200 && hash_file('sha256', $f) === $h0 && !extra_activo($slug, 'idiomas'), 'panel_guardar (200) no toca pedido.json: extras solo desde el webhook');

// ---------------------------------------------------------------- 9. reembolsos: el del álbum lo apaga; el de una compra VIEJA del plano, no
orden_ls('7001', 1900, ['status' => 'refunded', 'refunded' => true, 'refunded_amount' => 1900]);
[$st, $r] = webhook('order_refunded', '7001', $custom);
ok($st === 200 && ((json_decode($r, true) ?? [])['resultado'] ?? '') === 'reembolsado' && !extra_activo($slug, 'album') && extra_activo($slug, 'mesas'), 'reembolso del álbum: se apaga el álbum, el plano sigue');
// Compra de prueba del plano de cuando era de pago (registro histórico) y su reembolso ahora
muta_json(dir_boda($slug) . '/pedido.json', function (array &$d) { $d['extras']['mesas'] = ['desde' => '2026-09-27T12:00:00+02:00', 'pedido' => 'ls_7002']; });
$tokv = bin2hex(random_bytes(16));
escribe_json(dir_datos('pedidos', 'ls_7002.json'), ['session_id' => 'ls_7002', 'pasarela' => 'lemon', 'slug' => $slug, 'estado' => 'creada', 'tipo' => 'extra', 'clave' => 'mesas', 'atelier' => '',
    'token' => $tokv, 'email' => 'pareja@example.com', 'importe' => ['total' => 1900, 'iva' => 330], 'ls' => ['test' => true]]);
orden_ls('7002', 1900, ['status' => 'refunded', 'refunded' => true, 'refunded_amount' => 1900]);
[$st, $r] = webhook('order_refunded', '7002', ['token' => $tokv, 'slug' => $slug, 'producto' => 'bodas', 'tipo' => 'extra', 'clave' => 'mesas']);
ok($st === 200 && ((json_decode($r, true) ?? [])['resultado'] ?? '') === 'reembolsado', 'reembolso de la compra vieja del plano: procesado (' . $st . ' ' . $r . ')');
ok(!empty((lee_json(dir_boda($slug) . '/pedido.json') ?? [])['extras']['mesas']['baja']) && extra_activo($slug, 'mesas'), 'queda anotada la devolución y el plano SIGUE activo');
[$st, $h] = pide('GET', $host, '/panel/mesas');
ok($st === 200 && strpos($h, 'Crear mesa') !== false && strpos($h, 'Comprar por') === false, 'tras ese reembolso: la herramienta sigue, sin compra');
[$st, , $cab] = pide('POST', $host, '/panel/mesas', ['csrf' => $csrf, 'accion' => 'crear', 'nombre' => 'Amigos', 'plazas' => 8]);
ok($st === 303 && strpos($cab, '?e=') === false && count(plano()['mesas'] ?? []) === 3, 'tras ese reembolso: se sigue pudiendo crear mesas');
[, $hc] = pide('GET', $host, '/panel/catering');
ok(strpos($hc, '<th>Mesa</th>') !== false, 'tras ese reembolso: el catering conserva la columna Mesa');
[$st] = pide('GET', $host, '/');
ok($st === 200, 'la web de la boda sigue publicada');
$pr = array_values(array_filter(padrino_resumen()['pedidos'], fn($p) => $p['tipo'] === 'extra'));
ok(count($pr) === 2 && !array_filter($pr, fn($p) => $p['pack'] !== ''), 'Padrino: los extras no son altas ni tienen pack');

// ---------------------------------------------------------------- 10. web archivada: sin plano (en el servidor, GET y POST)
muta_json(dir_boda($slug) . '/config.json', function (array &$d) { $d['_estado'] = 'archivada'; });
[$st, $h] = pide('GET', $host, '/panel/mesas');
ok(!extra_activo($slug, 'mesas') && strpos($h, 'Crear mesa') === false && strpos($h, 'Alba Ruiz') === false, 'archivada: /panel/mesas sin herramienta ni nombres (' . $st . ')');
[$st] = pide('POST', $host, '/panel/mesas', ['csrf' => $csrf, 'accion' => 'crear', 'nombre' => 'X', 'plazas' => 8]);
ok($st !== 303 && count(plano()['mesas'] ?? []) === 3, 'archivada: el POST no escribe (' . $st . ')');
[$st, , $cab] = pide('GET', $host, '/panel/mesas/imprimir');
ok($st !== 200, 'archivada: la hoja para el restaurante no se abre (' . $st . ')');

proc_terminate($srv); proc_close($srv);
proc_terminate($sim); proc_close($sim);
foreach ([$tmp, $web, $ls] as $d) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $x) {
        $x->isDir() ? @rmdir($x->getPathname()) : @unlink($x->getPathname());
    }
    @rmdir($d);
}
echo ($fallos ? 'ROJO' : 'VERDE') . ': ' . ($n - $fallos) . "/$n\n";
exit($fallos ? 1 : 0);
