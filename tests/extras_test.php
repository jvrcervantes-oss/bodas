<?php
// Extras de pago (F1c, revisión previa #133): precio con fuente única y límites propios, activación por webhook
// idempotente y leída del pedido del servidor (nunca de custom_data), reembolso que desactiva, resumen del Padrino.
// Sin red: la relectura del pedido se inyecta, como en lemon_test.php. Uso: php tests/extras_test.php (sale con 1 si falla)

declare(strict_types=1);

$tmp = sys_get_temp_dir() . '/bodas_extras_test_' . bin2hex(random_bytes(4));
mkdir($tmp, 0700, true);
define('SIN_CONFIG_LOCAL', true);
define('DATA_DIR', $tmp);
define('BASE_DOMAIN', 'bodaenlace.com');
define('CREATOR_HOST', 'bodaenlace.com');
define('CREATOR_BASE', '');
define('CORREO_A_FICHERO', true);
define('LEMON_API', 'http://127.0.0.1:9');   // nada debe salir a la red
file_put_contents($tmp . '/secrets.php', '<?php return ' . var_export([
    'pasarela' => 'lemon', 'lemon_api_key' => 'clave-de-prueba', 'lemon_webhook_secret' => 'secreto-de-prueba-0123456789abcdef0123',
    'lemon_tienda' => '483461', 'lemon_variante' => '2169949', 'lemon_producto' => '1389266', 'lemon_test' => true,
    'telegram_token' => 'x', 'telegram_chat' => '1',
    'empresa' => ['titular' => 'Titular De Prueba', 'nif' => '00000000T', 'domicilio' => 'Calle Prueba 1'],
], true) . ';');
// Precio de un extra decidido por el Padrino ANTES de la primera lectura (precio_extra_cent lee una vez por petición):
// mesas dentro de límites (vale), idiomas por debajo de su suelo (se ignora: manda el del código)
mkdir($tmp . '/padrino', 0700, true);
file_put_contents($tmp . '/padrino/precios.json', json_encode(['extras' => ['mesas' => 2100, 'idiomas' => 500]]));

$raiz = dirname(__DIR__);
foreach (['core', 'schema', 'render', 'foto', 'alta', 'mapa', 'cortesia', 'estudio', 'borrador', 'invitados', 'creador', 'landing', 'vista_constructor', 'boda', 'galeria', 'proxy'] as $m) {
    require_once $raiz . '/app/' . $m . '.php';
}
$fallos = 0;
$n = 0;
function ok(bool $c, string $que): void { global $fallos, $n; $n++; if (!$c) { $fallos++; echo "FALLA: $que\n"; } }

// Alta de una web pagada por LS, como en lemon_test (pendiente + webhook)
function web_pagada(string $slug, string $id): void {
    $tok = bin2hex(random_bytes(16));
    $c = config_inicial();
    $c['pareja']['nombre1'] = 'Ana'; $c['pareja']['nombre2'] = 'Luis'; $c['pareja']['email'] = 'pareja@example.com';
    $c['fecha'] = date('Y-m-d', strtotime('+200 days'));
    escribe_json(dir_datos('pendientes', $tok, 'config.json'), $c);
    escribe_json(dir_datos('pendientes', $tok, 'meta.json'), ['slug' => $slug, 'creado' => time(), 'precio_cent' => precio_esencial_cent(), 'pasarela' => 'lemon', 'aceptacion' => ['fecha' => date('c'), 'version' => 'x']]);
    lemon_procesa_evento(['meta' => ['event_name' => 'order_created', 'custom_data' => ['token' => $tok, 'slug' => $slug, 'producto' => 'bodas']], 'data' => ['type' => 'orders', 'id' => $id]],
        fn($i) => pedido_ls(precio_esencial_cent()));
}
function pedido_ls(int $total, array $cambia = []): array {
    return array_replace_recursive(['store_id' => 483461, 'currency' => 'EUR', 'total' => $total, 'tax' => (int) round($total * 21 / 121),
        'discount_total' => 0, 'status' => 'paid', 'test_mode' => true, 'user_email' => 'pareja@example.com', 'user_name' => 'Ana',
        'order_number' => 2001, 'identifier' => 'uuid', 'urls' => ['receipt' => 'https://app.lemonsqueezy.com/my-orders/abc'],
        'first_order_item' => ['variant_id' => 2169949, 'product_id' => 1389266, 'quantity' => 1]], $cambia);
}
/** Pedido de extra abierto como lo deja panel_extra(): fichero del servidor con clave, boda y precio congelado. */
function extra_abierto(string $slug, string $clave, int $precio): string {
    $t = bin2hex(random_bytes(16));
    escribe_json(dir_datos('extras', $t . '.json'), ['tipo' => 'extra', 'clave' => $clave, 'slug' => $slug, 'creado' => time(), 'precio_cent' => $precio, 'pasarela' => 'lemon', 'estado' => 'abierta',
        'aceptacion' => ['fecha' => '2026-09-27T11:20:00+02:00', 'version' => textos_legales()['version'], 'casilla' => 'CASILLA-EXTRA-LITERAL', 'extra' => EXTRAS[$clave]['nombre'], 'vendedor' => 'Lemon Squeezy', 'precio_cent' => $precio]]);
    escribe_json(extra_fichero_abierta($slug, $clave), ['token' => $t, 'hasta' => time() + 1800]);
    return $t;
}
function ev_extra(string $nombre, string $id, string $tok, string $slug, string $clave): array {
    return ['meta' => ['event_name' => $nombre, 'custom_data' => ['token' => $tok, 'slug' => $slug, 'producto' => 'bodas', 'tipo' => 'extra', 'clave' => $clave]],
        'data' => ['type' => 'orders', 'id' => $id]];
}
function lector(array $p): callable { return fn(string $id) => $p[$id] ?? null; }
function bp(string $slug): array { return lee_json(dir_boda($slug) . '/pedido.json') ?? []; }
function telegramas(): array { return array_map('file_get_contents', glob(dir_datos('telegram', '*')) ?: []); }
function correos_con(string $txt): array { return array_values(array_filter(array_map('file_get_contents', glob(dir_datos('correos', '*')) ?: []), fn($c) => strpos($c, $txt) !== false)); }

// 1. Precio: fuente única, límites propios por extra, lista cerrada
ok(precio_extra_cent('mesas') === 2100, 'precio del Padrino dentro de límites: se usa (21 €)');
ok(precio_extra_cent('idiomas') === EXTRAS['idiomas']['cent'], 'precio del Padrino bajo el suelo del extra: se ignora, manda el del código');
ok(precio_extra_cent('album') === 1900 && precio_extra_cent('dominio') === 2900, 'precios del código del owner (19 y 29 €)');
ok(precio_extra_cent('inventado') === 0 && !extra_existe('inventado'), 'clave fuera de la lista: no existe, precio 0');
ok(padrino_precio_extra_error('mesas', 1900, null) === '' && padrino_precio_extra_error('mesas', 1900, null) === padrino_precio_extra_error('mesas', 1900, null), 'mesas a 19 € pasa sus límites (el suelo de los packs, 49 €, lo habría rechazado)');
ok(padrino_precio_extra_error('mesas', 800, null) !== '' && padrino_precio_extra_error('mesas', 5000, null) !== '' && padrino_precio_extra_error('mesas', 1937, null) !== '', 'suelo, techo y céntimos de escaparate por extra');
ok(array_keys(array_filter(EXTRAS, fn($x) => $x['venta'])) === ['mesas'], 'en esta tanda solo se vende el plano de mesas');
// Cambio por el Padrino: paso máximo, historial de precios.json y frecuencia por extra
ok(padrino_guarda_precio_extra('mesas', 3500, 'prueba') !== '', 'subida de más del 30 % de una vez: rechazada');
ok(padrino_guarda_precio_extra('inventado', 1900, 'prueba') === 'Extra desconocido.', 'el Padrino no puede crear extras');
ok(padrino_guarda_precio_extra('album', 2250, 'prueba') === '', 'cambio válido del álbum');
$pj = lee_json(dir_datos('padrino', 'precios.json'));
ok(($pj['extras']['album'] ?? 0) === 2250 && ($pj['extras']['mesas'] ?? 0) === 2100, 'guarda el extra sin tocar los demás');
$ha = array_values(array_filter($pj['historial'] ?? [], fn($h) => ($h['extra'] ?? '') === 'album'));
ok(count($ha) === 2 && $ha[0]['cent'] === 1900 && $ha[1]['cent'] === 2250 && $ha[1]['motivo'] === 'prueba', 'historial: precio inicial + cambio, con motivo (Ómnibus)');
ok(padrino_guarda_precio_extra('album', 2290, 'otra vez') !== '' && padrino_guarda_precio_extra('dominio', 2900, 'otro extra') === '', 'un cambio por semana POR extra: el álbum espera, el dominio no');
// Un cambio de packs después no borra lo decidido para los extras (precios.json se reescribía con 4 claves)
$pv = padrino_precios();
ok(padrino_guarda_precios($pv['esencial_cent'], $pv['atelier_cent'] + 100, 'packs') === '', 'cambio de packs válido');
$pj = lee_json(dir_datos('padrino', 'precios.json'));
ok(($pj['extras']['album'] ?? 0) === 2250 && isset($pj['extras_desde']['album']), 'el cambio de packs conserva los precios de los extras');
ok(count(array_filter($pj['historial'], fn($h) => isset($h['esencial_cent']) && $h['desde'] === '')) === 1, 'el historial de packs arranca con su precio inicial aunque ya hubiera entradas de extras');

// 2. Checkout del extra: importe del servidor, nombre de producto y vuelta al panel del extra
$cu = lemon_cuerpo_checkout(str_repeat('a', 32), 'ana-y-luis', 'p@example.com', precio_extra_cent('mesas'), 1000000, 'extra', 'mesas');
ok($cu['data']['attributes']['custom_price'] === 2100, 'custom_price = precio vigente del extra');
ok($cu['data']['attributes']['product_options']['name'] === 'Plano de mesas — ana-y-luis.bodaenlace.com', 'nombre de producto «<extra> — <slug>.<dominio>»');
ok($cu['data']['attributes']['checkout_data']['custom'] === ['token' => str_repeat('a', 32), 'slug' => 'ana-y-luis', 'producto' => 'bodas', 'tipo' => 'extra', 'clave' => 'mesas'], 'custom con tipo y clave (solo rastro)');
ok($cu['data']['attributes']['product_options']['redirect_url'] === 'https://ana-y-luis.bodaenlace.com/panel/mesas?compra=1', 'vuelve a /panel/mesas');
ok($cu['data']['attributes']['checkout_options']['discount'] === false, 'sin descuentos');
try { lemon_cuerpo_checkout(str_repeat('a', 32), 'x', 'p@example.com', 100, 0, 'extra', 'inventado'); $lanza = false; } catch (LogicException $e) { $lanza = true; }
ok($lanza, 'checkout de un extra fuera de la lista: imposible');

// 3. Activación: webhook ×3 = una sola activación
web_pagada('ana-y-luis', '101');
ok(extra_bloqueo('ana-y-luis', 'mesas') === '' && !extra_activo('ana-y-luis', 'mesas'), 'web pagada sin el extra: se puede comprar');
$pm = precio_extra_cent('mesas');
$t = extra_abierto('ana-y-luis', 'mesas', $pm);
$tg0 = count(telegramas());
for ($i = 0; $i < 3; $i++) [$st, $r] = lemon_procesa_evento(ev_extra('order_created', '301', $t, 'ana-y-luis', 'mesas'), lector(['301' => pedido_ls($pm)]));
ok($st === 200 && $r === 'creada', 'extra activado (3 entregas)');
$x = bp('ana-y-luis')['extras']['mesas'] ?? [];
ok(($x['pedido'] ?? '') === 'ls_301' && ($x['desde'] ?? '') !== '' && extra_activo('ana-y-luis', 'mesas'), 'extras.mesas = {desde, pedido} en pedido.json');
ok(count(telegramas()) === $tg0 + 1 && count(correos_con('Asunto: Ya tenéis Plano de mesas')) === 1, 'un solo aviso al owner y un solo correo a la pareja');
ok(!is_file(extra_fichero_abierta('ana-y-luis', 'mesas')) && (lee_json(dir_datos('extras', $t . '.json'))['estado'] ?? '') === 'pagada', 'pedido del extra pagado y marca abierta liberada');
$pe = lee_json(dir_datos('pedidos', 'ls_301.json'));
ok(($pe['tipo'] ?? '') === 'extra' && ($pe['clave'] ?? '') === 'mesas' && ($pe['estado'] ?? '') === 'creada' && ($pe['factura'] ?? 'x') === '', 'registro local: tipo extra, clave, sin factura nuestra');
ok(($pe['aceptacion']['casilla'] ?? '') === 'CASILLA-EXTRA-LITERAL', 'la casilla aceptada queda literal en el pedido');
ok(extra_recibo('ana-y-luis', 'mesas') === 'https://app.lemonsqueezy.com/my-orders/abc', 'el panel tiene el recibo de LS del extra (Administración #133)');
$cm = correos_con('Asunto: Ya tenéis Plano de mesas')[0] ?? '';
ok(strpos($cm, '«CASILLA-EXTRA-LITERAL»') !== false && strpos($cm, '27/09/2026 11:20') !== false && strpos($cm, euros($pm) . ', IVA incluido') !== false
    && strpos($cm, 'CONDICIONES DEL SERVICIO') !== false, 'correo del extra: casilla literal, fecha, importe y condiciones íntegras (98.7)');
ok(extra_bloqueo('ana-y-luis', 'mesas') === 'ya-activo', 'con el extra activo no se vuelve a vender');

// 4. Otro cobro con el mismo token → duplicado; otro pedido con el extra ya activo → duplicado
[, $r] = lemon_procesa_evento(ev_extra('order_created', '302', $t, 'ana-y-luis', 'mesas'), lector(['302' => pedido_ls($pm)]));
ok($r === 'duplicado', 'segundo cobro del mismo pedido de extra: duplicado');
$t2 = extra_abierto('ana-y-luis', 'mesas', $pm);
[, $r] = lemon_procesa_evento(ev_extra('order_created', '303', $t2, 'ana-y-luis', 'mesas'), lector(['303' => pedido_ls($pm)]));
ok($r === 'duplicado' && (bp('ana-y-luis')['extras']['mesas']['pedido'] ?? '') === 'ls_301', 'extra pagado en una web que ya lo tiene: duplicado, sin tocar el activo');

// 5. Lo que no cuadra no activa nada
web_pagada('mal', '110');
foreach ([['total' => $pm + 100], ['total' => precio_esencial_cent()], ['discount_total' => 200], ['test_mode' => false], ['first_order_item' => ['variant_id' => 1]]] as $i => $cambio) {
    $tm = extra_abierto('mal', 'mesas', $pm);
    [, $r] = lemon_procesa_evento(ev_extra('order_created', (string) (400 + $i), $tm, 'mal', 'mesas'), lector([(string) (400 + $i) => pedido_ls($pm, $cambio)]));
    ok($r === 'no-conforme' && !extra_activo('mal', 'mesas'), 'no-conforme sin activar: ' . json_encode($cambio));
    ok(!is_file(extra_fichero_abierta('mal', 'mesas')), 'no-conforme libera la marca abierta: ' . json_encode($cambio));
}
// custom_data manipulado: otra clave, otro slug, o tipo extra con el token de otra cosa
$tm = extra_abierto('mal', 'mesas', $pm);
[, $r] = lemon_procesa_evento(ev_extra('order_created', '420', $tm, 'mal', 'idiomas'), lector(['420' => pedido_ls($pm)]));
$bpm = bp('mal');
ok($r === 'no-conforme' && !extra_activo('mal', 'mesas') && !extra_activo('mal', 'idiomas') && empty($bpm['extras']), 'custom_data con otra clave: no activa ni esa ni la del pedido');
$tm = extra_abierto('mal', 'mesas', $pm);
[, $r] = lemon_procesa_evento(ev_extra('order_created', '421', $tm, 'ana-y-luis', 'mesas'), lector(['421' => pedido_ls($pm)]));
ok($r === 'no-conforme' && !extra_activo('mal', 'mesas'), 'custom_data con otro slug: no-conforme');
[, $r] = lemon_procesa_evento(ev_extra('order_created', '422', str_repeat('d', 32), 'mal', 'mesas'), lector(['422' => pedido_ls($pm)]));
ok($r === 'sin-datos' && !extra_activo('mal', 'mesas'), 'sin pedido de extra en el servidor: sin-datos, nada activo');
$tmj = bin2hex(random_bytes(16));
escribe_json(dir_datos('mejoras', $tmj . '.json'), ['tipo' => 'mejora', 'slug' => 'mal', 'precio_cent' => $pm, 'pasarela' => 'lemon']);
[, $r] = lemon_procesa_evento(ev_extra('order_created', '423', $tmj, 'mal', 'mesas'), lector(['423' => pedido_ls($pm)]));
ok($r === 'sin-datos' && !extra_activo('mal', 'mesas') && empty(bp('mal')['atelier']), 'tipo extra con el token de una mejora: nada');
// Un fichero de extra con una clave fuera de la lista (manipulado en disco) tampoco activa
$tx = bin2hex(random_bytes(16));
escribe_json(dir_datos('extras', $tx . '.json'), ['tipo' => 'extra', 'clave' => 'inventado', 'slug' => 'mal', 'precio_cent' => $pm, 'pasarela' => 'lemon']);
[, $r] = lemon_procesa_evento(ev_extra('order_created', '424', $tx, 'mal', 'inventado'), lector(['424' => pedido_ls($pm)]));
ok($r === 'sin-datos' && empty(bp('mal')['extras']), 'clave fuera de la lista en el fichero: sin-datos');
// Sin relectura de la API: 503, nada escrito
$tm = extra_abierto('mal', 'mesas', $pm);
[$st] = lemon_procesa_evento(ev_extra('order_created', '425', $tm, 'mal', 'mesas'), lector([]));
ok($st === 503 && !is_file(dir_datos('pedidos', 'ls_425.json')), 'sin relectura: 503 y nada escrito');

// 6. Reembolso: rama propia, desactiva el extra, la web sigue; es final
$tg0 = count(telegramas());
[, $r] = lemon_procesa_evento(ev_extra('order_refunded', '301', $t, 'ana-y-luis', 'mesas'), lector(['301' => pedido_ls($pm, ['status' => 'refunded', 'refunded' => true, 'refunded_amount' => $pm])]));
ok($r === 'reembolsado' && !extra_activo('ana-y-luis', 'mesas') && boda_existe('ana-y-luis'), 'reembolso: extra desactivado, la web sigue');
ok(!empty(bp('ana-y-luis')['extras']['mesas']['baja']), 'la baja queda fechada en el pedido.json (no se borra el rastro)');
$aviso = implode("\n", correos_con('Reembolso total de un extra de boda'));
ok(count(telegramas()) === $tg0 + 1 && strpos($aviso, 'Plano de mesas') !== false && strpos($aviso, 'se ha desactivado') !== false && strpos($aviso, 'sigue publicada') !== false, 'aviso del reembolso con el mensaje del extra');
[, $r] = lemon_procesa_evento(ev_extra('order_created', '301', $t, 'ana-y-luis', 'mesas'), lector(['301' => pedido_ls($pm)]));
ok($r === 'reembolsado' && !extra_activo('ana-y-luis', 'mesas'), 'un order_created posterior no reactiva el extra reembolsado');
ok(extra_bloqueo('ana-y-luis', 'mesas') === '', 'tras el reembolso se puede volver a comprar');
// El reembolso usa tipo y clave del registro local, no del custom_data (custom manipulado con otra clave)
web_pagada('otra', '120');
$to = extra_abierto('otra', 'mesas', $pm);
lemon_procesa_evento(ev_extra('order_created', '501', $to, 'otra', 'mesas'), lector(['501' => pedido_ls($pm)]));
lemon_procesa_evento(ev_extra('order_refunded', '501', $to, 'otra', 'idiomas'), lector(['501' => pedido_ls($pm, ['status' => 'partial_refund', 'refunded' => true, 'refunded_amount' => 500])]));
ok(!extra_activo('otra', 'mesas') && (lee_json(dir_datos('pedidos', 'ls_501.json'))['estado'] ?? '') === 'creada', 'reembolso parcial de un extra: se desactiva igual (desistimiento proporcional), el pedido no pasa a final');
// Un reembolso de la WEB no toca los extras; uno que la API no confirma no desactiva nada
web_pagada('tercera', '130');
$t3 = extra_abierto('tercera', 'mesas', $pm);
lemon_procesa_evento(ev_extra('order_created', '601', $t3, 'tercera', 'mesas'), lector(['601' => pedido_ls($pm)]));
lemon_procesa_evento(ev_extra('order_refunded', '601', $t3, 'tercera', 'mesas'), lector(['601' => pedido_ls($pm)]));
ok(extra_activo('tercera', 'mesas'), 'reembolso no confirmado por la API: el extra sigue');

// Corte a mitad de la activación (boda ya marcada, registro aún 'cobrada') + reembolso: se da de baja igual
web_pagada('corte', '140');
$tc = extra_abierto('corte', 'mesas', $pm);
escribe_json(dir_datos('pedidos', 'ls_701.json'), lemon_pedido_base('ls_701', '701', pedido_ls($pm), 'corte', $tc, '', null) + ['tipo' => 'extra', 'clave' => 'mesas', 'atelier' => '']);
$bc = bp('corte'); $bc['extras'] = ['mesas' => ['desde' => date('c'), 'pedido' => 'ls_701']]; escribe_json(dir_boda('corte') . '/pedido.json', $bc);
lemon_procesa_evento(ev_extra('order_refunded', '701', $tc, 'corte', 'mesas'), lector(['701' => pedido_ls($pm, ['status' => 'refunded'])]));
ok(!extra_activo('corte', 'mesas'), 'corte a mitad de la activación y reembolso: el extra queda desactivado');
[, $r] = lemon_procesa_evento(ev_extra('order_created', '701', $tc, 'corte', 'mesas'), lector(['701' => pedido_ls($pm)]));
ok($r === 'reembolsado' && !extra_activo('corte', 'mesas'), 'el reintento posterior no lo reactiva');
// Corte + reembolso PARCIAL (el registro no pasa a final) + reintento: ni reactiva ni manda «Ya tenéis»
web_pagada('corte2', '150');
$tq = extra_abierto('corte2', 'mesas', $pm);
escribe_json(dir_datos('pedidos', 'ls_703.json'), lemon_pedido_base('ls_703', '703', pedido_ls($pm), 'corte2', $tq, '', null) + ['tipo' => 'extra', 'clave' => 'mesas', 'atelier' => '']);
$bq = bp('corte2'); $bq['extras'] = ['mesas' => ['desde' => date('c'), 'pedido' => 'ls_703']]; escribe_json(dir_boda('corte2') . '/pedido.json', $bq);
lemon_procesa_evento(ev_extra('order_refunded', '703', $tq, 'corte2', 'mesas'), lector(['703' => pedido_ls($pm, ['status' => 'partial_refund', 'refunded' => true, 'refunded_amount' => 500])]));
$yaTenéis0 = count(correos_con('Ya tenéis'));
[, $r] = lemon_procesa_evento(ev_extra('order_created', '703', $tq, 'corte2', 'mesas'), lector(['703' => pedido_ls($pm)]));
ok($r === 'reembolsado' && !extra_activo('corte2', 'mesas') && count(correos_con('Ya tenéis')) === $yaTenéis0, 'corte + reembolso parcial + reintento: sigue desactivado y sin correo de confirmación');
// Reembolso que llega ANTES de la activación: el registro sale como extra (del fichero del servidor), no como web
$tp = extra_abierto('corte', 'mesas', $pm);
lemon_procesa_evento(ev_extra('order_refunded', '702', $tp, 'corte', 'idiomas'), lector(['702' => pedido_ls($pm, ['status' => 'refunded'])]));
$p702 = lee_json(dir_datos('pedidos', 'ls_702.json')) ?? [];
ok(($p702['tipo'] ?? '') === 'extra' && ($p702['clave'] ?? '') === 'mesas' && ($p702['estado'] ?? '') === 'reembolsado', 'reembolso antes de la activación: registro de extra con la clave del servidor');
[, $r] = lemon_procesa_evento(ev_extra('order_created', '702', $tp, 'corte', 'mesas'), lector(['702' => pedido_ls($pm)]));
ok($r === 'reembolsado' && !extra_activo('corte', 'mesas'), 'y la activación posterior ya no activa nada');

// 7. Web regalada con código: puede comprar extras
escribe_json(dir_boda('regalada') . '/config.json', config_inicial());
escribe_json(dir_boda('regalada') . '/pedido.json', ['session_id' => 'cortesia_abc']);
escribe_json(dir_datos('pedidos', 'cortesia_abc.json'), ['session_id' => 'cortesia_abc', 'estado' => 'creada']);
ok(extra_bloqueo('regalada', 'mesas') === '', 'web regalada: puede comprar el extra');
escribe_json(dir_boda('regalada') . '/config.json', ['_estado' => 'archivada'] + config_inicial());
ok(extra_bloqueo('regalada', 'mesas') === 'archivada', 'web archivada: no');
ok(extra_bloqueo('ana-y-luis', 'inventado') === 'sin-extra', 'clave fuera de la lista: no');

// 8. Puertas de venta: en pruebas, sin sesión del estudio ni IP de la lista, no se vende
$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
ok(!extra_venta_abierta() && !extra_disponible('ana-y-luis', 'mesas'), 'modo test: una IP cualquiera no abre la compra');
ok(texto_extra() === (string) (textos_legales()['check_extra'] ?? '') && texto_extra() !== '' && strpos(texto_extra(), marca()) !== false, 'casilla del extra: la de textos.php (check_extra), con la marca');
// Legal #133: ningún extra se vende sin su texto en las condiciones (apartado 5 ter) en el mismo diff. Poner
// 'venta' => true sin que Legal lo añada al 5 ter pone este test en rojo.
$cond5ter = legal_a_texto(documento_legal('condiciones', 'c'));
$cond5ter = substr($cond5ter, (int) stripos($cond5ter, '5 ter. Extras'), 4000);
foreach (EXTRAS as $k => $x) {
    if (!empty($x['venta'])) ok(stripos($cond5ter, $x['nombre']) !== false && strpos($cond5ter, euros(precio_extra_cent($k))) !== false, "extra a la venta «{$x['nombre']}»: está en el 5 ter con su precio");
}

// 9. El Padrino: un extra no es una venta nueva ni tiene pack
$res = padrino_resumen();
$ext = array_values(array_filter($res['pedidos'], fn($p) => $p['tipo'] === 'extra'));
ok(count($ext) >= 3 && !array_filter($ext, fn($p) => $p['pack'] !== '') && !array_filter($ext, fn($p) => $p['estado'] === 'creada' && $p['extra'] !== 'mesas'), 'resumen: tipo extra, sin pack, con su clave');
$altas = array_filter($res['pedidos'], fn($p) => $p['tipo'] === 'alta' && !$p['regalo']);
ok(count($altas) === 6, 'resumen: las altas son solo las 6 webs pagadas del test, ningún extra (' . count($altas) . ')');
$bt = array_values(array_filter($res['bodas'], fn($b) => $b['slug'] === 'tercera'))[0] ?? [];
ok(($bt['extras'] ?? null) === ['mesas'], 'resumen: la boda dice qué extras tiene activos');
ok(($res['precios']['extras'][0]['clave'] ?? '') === 'mesas' && isset($res['precios']['extras'][0]['suelo']), 'resumen: precios y límites de los extras');

// 10. El panel del estudio etiqueta el extra como extra, no como web publicada
$h = estudio_pedidos();
ok(strpos($h, 'Extra activado: Plano de mesas') !== false, 'estudio: «Extra activado: Plano de mesas»');

foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
    $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
}
@rmdir($tmp);
echo ($fallos ? 'ROJO' : 'VERDE') . ': ' . ($n - $fallos) . "/$n\n";
exit($fallos ? 1 : 0);
