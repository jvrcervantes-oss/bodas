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
// álbum dentro de límites (vale), idiomas por debajo de su suelo (se ignora: manda el del código) y mesas, que ya va
// incluido en los packs (se ignora: no tiene precio). El plano de mesas pasó a ser gratis el 27-sep-2026 (owner): la
// tubería de compra se prueba con el álbum, que no está a la venta pero usa el mismo webhook, bloqueo y reembolso.
mkdir($tmp . '/padrino', 0700, true);
file_put_contents($tmp . '/padrino/precios.json', json_encode(['extras' => ['album' => 2100, 'idiomas' => 500, 'mesas' => 2100]]));

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
    return array_replace_recursive(['store_id' => 483461, 'currency' => 'EUR', 'total' => $total, 'tax_inclusive' => true, 'tax' => (int) round($total * 21 / 121),
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
ok(precio_extra_cent('album') === 2100, 'precio del Padrino dentro de límites: se usa (21 €)');
ok(precio_extra_cent('mesas') === EXTRAS['mesas']['cent'], 'un precio del Padrino para el plano de mesas (incluido) se ignora');
ok(precio_extra_cent('idiomas') === EXTRAS['idiomas']['cent'], 'precio del Padrino bajo el suelo del extra: se ignora, manda el del código');
ok(precio_extra_cent('dominio') === 2900, 'precio del código del owner (29 €)');
ok(precio_extra_cent('inventado') === 0 && !extra_existe('inventado'), 'clave fuera de la lista: no existe, precio 0');
ok(padrino_precio_extra_error('album', 1900, null) === '', 'álbum a 19 € pasa sus límites (el suelo de los packs, 49 €, lo habría rechazado)');
ok(padrino_precio_extra_error('album', 800, null) !== '' && padrino_precio_extra_error('album', 5000, null) !== '' && padrino_precio_extra_error('album', 1937, null) !== '', 'suelo, techo y céntimos de escaparate por extra');
ok(array_keys(array_filter(EXTRAS, fn($x) => $x['venta'])) === [], 'hoy no se vende ningún extra (el plano de mesas pasó a ir incluido)');
ok(extra_incluido('mesas') && !EXTRAS['mesas']['venta'] && !extra_incluido('album') && !extra_incluido('inventado'), 'el plano de mesas es el único incluido y no está a la venta');
ok(padrino_guarda_precio_extra('mesas', 1900, 'prueba') !== '' && padrino_precio_extra_error('mesas', 1900, null) !== '', 'el Padrino no puede ponerle precio a lo incluido');
// Cambio por el Padrino: paso máximo, historial de precios.json y frecuencia por extra
ok(padrino_guarda_precio_extra('album', 3500, 'prueba') !== '', 'subida de más del 30 % de una vez: rechazada');
ok(padrino_guarda_precio_extra('inventado', 1900, 'prueba') === 'Extra desconocido.', 'el Padrino no puede crear extras');
ok(padrino_guarda_precio_extra('album', 2250, 'prueba') === '', 'cambio válido del álbum');
$pj = lee_json(dir_datos('padrino', 'precios.json'));
ok(($pj['extras']['album'] ?? 0) === 2250 && ($pj['extras']['idiomas'] ?? 0) === 500 && ($pj['extras']['mesas'] ?? 0) === 2100, 'guarda el extra sin tocar los demás');
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
$cu = lemon_cuerpo_checkout(str_repeat('a', 32), 'ana-y-luis', 'p@example.com', precio_extra_cent('album'), 1000000, 'extra', 'album');
ok($cu['data']['attributes']['custom_price'] === 2100, 'custom_price = precio vigente del extra');
ok($cu['data']['attributes']['product_options']['name'] === 'Álbum de invitados — ana-y-luis.bodaenlace.com', 'nombre de producto «<extra> — <slug>.<dominio>»');
ok($cu['data']['attributes']['checkout_data']['custom'] === ['token' => str_repeat('a', 32), 'slug' => 'ana-y-luis', 'producto' => 'bodas', 'tipo' => 'extra', 'clave' => 'album'], 'custom con tipo y clave (solo rastro)');
ok($cu['data']['attributes']['product_options']['redirect_url'] === 'https://ana-y-luis.bodaenlace.com/panel?compra=1', 'un extra sin página propia vuelve a /panel (no a /panel/)');
ok($cu['data']['attributes']['checkout_options']['discount'] === false, 'sin descuentos');
try { lemon_cuerpo_checkout(str_repeat('a', 32), 'x', 'p@example.com', 100, 0, 'extra', 'inventado'); $lanza = false; } catch (LogicException $e) { $lanza = true; }
ok($lanza, 'checkout de un extra fuera de la lista: imposible');

// 3. Activación: webhook ×3 = una sola activación
web_pagada('ana-y-luis', '101');
ok(extra_bloqueo('ana-y-luis', 'album') === '' && !extra_activo('ana-y-luis', 'album'), 'web pagada sin el extra: se puede comprar');
$pm = precio_extra_cent('album');
$t = extra_abierto('ana-y-luis', 'album', $pm);
$tg0 = count(telegramas());
for ($i = 0; $i < 3; $i++) [$st, $r] = lemon_procesa_evento(ev_extra('order_created', '301', $t, 'ana-y-luis', 'album'), lector(['301' => pedido_ls($pm)]));
ok($st === 200 && $r === 'creada', 'extra activado (3 entregas)');
$x = bp('ana-y-luis')['extras']['album'] ?? [];
ok(($x['pedido'] ?? '') === 'ls_301' && ($x['desde'] ?? '') !== '' && extra_activo('ana-y-luis', 'album'), 'extras.album = {desde, pedido} en pedido.json');
ok(count(telegramas()) === $tg0 + 1 && count(correos_con('Asunto: Ya tenéis Álbum de invitados')) === 1, 'un solo aviso al owner y un solo correo a la pareja');
ok(!is_file(extra_fichero_abierta('ana-y-luis', 'album')) && (lee_json(dir_datos('extras', $t . '.json'))['estado'] ?? '') === 'pagada', 'pedido del extra pagado y marca abierta liberada');
$pe = lee_json(dir_datos('pedidos', 'ls_301.json'));
ok(($pe['tipo'] ?? '') === 'extra' && ($pe['clave'] ?? '') === 'album' && ($pe['estado'] ?? '') === 'creada' && ($pe['factura'] ?? 'x') === '', 'registro local: tipo extra, clave, sin factura nuestra');
ok(($pe['aceptacion']['casilla'] ?? '') === 'CASILLA-EXTRA-LITERAL', 'la casilla aceptada queda literal en el pedido');
ok(extra_recibo('ana-y-luis', 'album') === 'https://app.lemonsqueezy.com/my-orders/abc', 'el panel tiene el recibo de LS del extra (Administración #133)');
$cm = correos_con('Asunto: Ya tenéis Álbum de invitados')[0] ?? '';
ok(strpos($cm, '«CASILLA-EXTRA-LITERAL»') !== false && strpos($cm, '27/09/2026 11:20') !== false && strpos($cm, euros($pm) . ', IVA incluido') !== false
    && strpos($cm, 'CONDICIONES DEL SERVICIO') !== false, 'correo del extra: casilla literal, fecha, importe y condiciones íntegras (98.7)');
ok(extra_bloqueo('ana-y-luis', 'album') === 'ya-activo', 'con el extra activo no se vuelve a vender');

// 4. Otro cobro con el mismo token → duplicado; otro pedido con el extra ya activo → duplicado
[, $r] = lemon_procesa_evento(ev_extra('order_created', '302', $t, 'ana-y-luis', 'album'), lector(['302' => pedido_ls($pm)]));
ok($r === 'duplicado', 'segundo cobro del mismo pedido de extra: duplicado');
$t2 = extra_abierto('ana-y-luis', 'album', $pm);
[, $r] = lemon_procesa_evento(ev_extra('order_created', '303', $t2, 'ana-y-luis', 'album'), lector(['303' => pedido_ls($pm)]));
ok($r === 'duplicado' && (bp('ana-y-luis')['extras']['album']['pedido'] ?? '') === 'ls_301', 'extra pagado en una web que ya lo tiene: duplicado, sin tocar el activo');

// 5. Lo que no cuadra no activa nada
web_pagada('mal', '110');
foreach ([['total' => $pm + 100], ['total' => precio_esencial_cent()], ['discount_total' => 200], ['test_mode' => false], ['first_order_item' => ['variant_id' => 1]]] as $i => $cambio) {
    $tm = extra_abierto('mal', 'album', $pm);
    [, $r] = lemon_procesa_evento(ev_extra('order_created', (string) (400 + $i), $tm, 'mal', 'album'), lector([(string) (400 + $i) => pedido_ls($pm, $cambio)]));
    ok($r === 'no-conforme' && !extra_activo('mal', 'album'), 'no-conforme sin activar: ' . json_encode($cambio));
    ok(!is_file(extra_fichero_abierta('mal', 'album')), 'no-conforme libera la marca abierta: ' . json_encode($cambio));
}
// custom_data manipulado: otra clave, otro slug, o tipo extra con el token de otra cosa
$tm = extra_abierto('mal', 'album', $pm);
[, $r] = lemon_procesa_evento(ev_extra('order_created', '420', $tm, 'mal', 'idiomas'), lector(['420' => pedido_ls($pm)]));
$bpm = bp('mal');
ok($r === 'no-conforme' && !extra_activo('mal', 'album') && !extra_activo('mal', 'idiomas') && empty($bpm['extras']), 'custom_data con otra clave: no activa ni esa ni la del pedido');
$tm = extra_abierto('mal', 'album', $pm);
[, $r] = lemon_procesa_evento(ev_extra('order_created', '421', $tm, 'ana-y-luis', 'album'), lector(['421' => pedido_ls($pm)]));
ok($r === 'no-conforme' && !extra_activo('mal', 'album'), 'custom_data con otro slug: no-conforme');
[, $r] = lemon_procesa_evento(ev_extra('order_created', '422', str_repeat('d', 32), 'mal', 'album'), lector(['422' => pedido_ls($pm)]));
ok($r === 'sin-datos' && !extra_activo('mal', 'album'), 'sin pedido de extra en el servidor: sin-datos, nada activo');
$tmj = bin2hex(random_bytes(16));
escribe_json(dir_datos('mejoras', $tmj . '.json'), ['tipo' => 'mejora', 'slug' => 'mal', 'precio_cent' => $pm, 'pasarela' => 'lemon']);
[, $r] = lemon_procesa_evento(ev_extra('order_created', '423', $tmj, 'mal', 'album'), lector(['423' => pedido_ls($pm)]));
ok($r === 'sin-datos' && !extra_activo('mal', 'album') && empty(bp('mal')['atelier']), 'tipo extra con el token de una mejora: nada');
// Un fichero de extra con una clave fuera de la lista (manipulado en disco) tampoco activa
$tx = bin2hex(random_bytes(16));
escribe_json(dir_datos('extras', $tx . '.json'), ['tipo' => 'extra', 'clave' => 'inventado', 'slug' => 'mal', 'precio_cent' => $pm, 'pasarela' => 'lemon']);
[, $r] = lemon_procesa_evento(ev_extra('order_created', '424', $tx, 'mal', 'inventado'), lector(['424' => pedido_ls($pm)]));
ok($r === 'sin-datos' && empty(bp('mal')['extras']), 'clave fuera de la lista en el fichero: sin-datos');
// Sin relectura de la API: 503, nada escrito
$tm = extra_abierto('mal', 'album', $pm);
[$st] = lemon_procesa_evento(ev_extra('order_created', '425', $tm, 'mal', 'album'), lector([]));
ok($st === 503 && !is_file(dir_datos('pedidos', 'ls_425.json')), 'sin relectura: 503 y nada escrito');

// 6. Reembolso: rama propia, desactiva el extra, la web sigue; es final
$tg0 = count(telegramas());
[, $r] = lemon_procesa_evento(ev_extra('order_refunded', '301', $t, 'ana-y-luis', 'album'), lector(['301' => pedido_ls($pm, ['status' => 'refunded', 'refunded' => true, 'refunded_amount' => $pm])]));
ok($r === 'reembolsado' && !extra_activo('ana-y-luis', 'album') && boda_existe('ana-y-luis'), 'reembolso: extra desactivado, la web sigue');
ok(!empty(bp('ana-y-luis')['extras']['album']['baja']), 'la baja queda fechada en el pedido.json (no se borra el rastro)');
$aviso = implode("\n", correos_con('Reembolso total de un extra de boda'));
ok(count(telegramas()) === $tg0 + 1 && strpos($aviso, 'Álbum de invitados') !== false && strpos($aviso, 'se ha desactivado') !== false && strpos($aviso, 'sigue publicada') !== false, 'aviso del reembolso con el mensaje del extra');
[, $r] = lemon_procesa_evento(ev_extra('order_created', '301', $t, 'ana-y-luis', 'album'), lector(['301' => pedido_ls($pm)]));
ok($r === 'reembolsado' && !extra_activo('ana-y-luis', 'album'), 'un order_created posterior no reactiva el extra reembolsado');
ok(extra_bloqueo('ana-y-luis', 'album') === '', 'tras el reembolso se puede volver a comprar');
// El reembolso usa tipo y clave del registro local, no del custom_data (custom manipulado con otra clave)
web_pagada('otra', '120');
$to = extra_abierto('otra', 'album', $pm);
lemon_procesa_evento(ev_extra('order_created', '501', $to, 'otra', 'album'), lector(['501' => pedido_ls($pm)]));
lemon_procesa_evento(ev_extra('order_refunded', '501', $to, 'otra', 'idiomas'), lector(['501' => pedido_ls($pm, ['status' => 'partial_refund', 'refunded' => true, 'refunded_amount' => 500])]));
ok(!extra_activo('otra', 'album') && (lee_json(dir_datos('pedidos', 'ls_501.json'))['estado'] ?? '') === 'creada', 'reembolso parcial de un extra: se desactiva igual (desistimiento proporcional), el pedido no pasa a final');
// Un reembolso de la WEB no toca los extras; uno que la API no confirma no desactiva nada
web_pagada('tercera', '130');
$t3 = extra_abierto('tercera', 'album', $pm);
lemon_procesa_evento(ev_extra('order_created', '601', $t3, 'tercera', 'album'), lector(['601' => pedido_ls($pm)]));
lemon_procesa_evento(ev_extra('order_refunded', '601', $t3, 'tercera', 'album'), lector(['601' => pedido_ls($pm)]));
ok(extra_activo('tercera', 'album'), 'reembolso no confirmado por la API: el extra sigue');

// Corte a mitad de la activación (boda ya marcada, registro aún 'cobrada') + reembolso: se da de baja igual
web_pagada('corte', '140');
$tc = extra_abierto('corte', 'album', $pm);
escribe_json(dir_datos('pedidos', 'ls_701.json'), lemon_pedido_base('ls_701', '701', pedido_ls($pm), 'corte', $tc, '', null) + ['tipo' => 'extra', 'clave' => 'album', 'atelier' => '']);
$bc = bp('corte'); $bc['extras'] = ['album' => ['desde' => date('c'), 'pedido' => 'ls_701']]; escribe_json(dir_boda('corte') . '/pedido.json', $bc);
lemon_procesa_evento(ev_extra('order_refunded', '701', $tc, 'corte', 'album'), lector(['701' => pedido_ls($pm, ['status' => 'refunded'])]));
ok(!extra_activo('corte', 'album'), 'corte a mitad de la activación y reembolso: el extra queda desactivado');
[, $r] = lemon_procesa_evento(ev_extra('order_created', '701', $tc, 'corte', 'album'), lector(['701' => pedido_ls($pm)]));
ok($r === 'reembolsado' && !extra_activo('corte', 'album'), 'el reintento posterior no lo reactiva');
// Corte + reembolso PARCIAL (el registro no pasa a final) + reintento: ni reactiva ni manda «Ya tenéis»
web_pagada('corte2', '150');
$tq = extra_abierto('corte2', 'album', $pm);
escribe_json(dir_datos('pedidos', 'ls_703.json'), lemon_pedido_base('ls_703', '703', pedido_ls($pm), 'corte2', $tq, '', null) + ['tipo' => 'extra', 'clave' => 'album', 'atelier' => '']);
$bq = bp('corte2'); $bq['extras'] = ['album' => ['desde' => date('c'), 'pedido' => 'ls_703']]; escribe_json(dir_boda('corte2') . '/pedido.json', $bq);
lemon_procesa_evento(ev_extra('order_refunded', '703', $tq, 'corte2', 'album'), lector(['703' => pedido_ls($pm, ['status' => 'partial_refund', 'refunded' => true, 'refunded_amount' => 500])]));
$yaTenéis0 = count(correos_con('Ya tenéis'));
[, $r] = lemon_procesa_evento(ev_extra('order_created', '703', $tq, 'corte2', 'album'), lector(['703' => pedido_ls($pm)]));
ok($r === 'reembolsado' && !extra_activo('corte2', 'album') && count(correos_con('Ya tenéis')) === $yaTenéis0, 'corte + reembolso parcial + reintento: sigue desactivado y sin correo de confirmación');
// Reembolso que llega ANTES de la activación: el registro sale como extra (del fichero del servidor), no como web
$tp = extra_abierto('corte', 'album', $pm);
lemon_procesa_evento(ev_extra('order_refunded', '702', $tp, 'corte', 'idiomas'), lector(['702' => pedido_ls($pm, ['status' => 'refunded'])]));
$p702 = lee_json(dir_datos('pedidos', 'ls_702.json')) ?? [];
ok(($p702['tipo'] ?? '') === 'extra' && ($p702['clave'] ?? '') === 'album' && ($p702['estado'] ?? '') === 'reembolsado', 'reembolso antes de la activación: registro de extra con la clave del servidor');
[, $r] = lemon_procesa_evento(ev_extra('order_created', '702', $tp, 'corte', 'album'), lector(['702' => pedido_ls($pm)]));
ok($r === 'reembolsado' && !extra_activo('corte', 'album'), 'y la activación posterior ya no activa nada');

// 7. Web regalada con código: puede comprar extras
escribe_json(dir_boda('regalada') . '/config.json', config_inicial());
escribe_json(dir_boda('regalada') . '/pedido.json', ['session_id' => 'cortesia_abc']);
escribe_json(dir_datos('pedidos', 'cortesia_abc.json'), ['session_id' => 'cortesia_abc', 'estado' => 'creada']);
ok(extra_bloqueo('regalada', 'album') === '', 'web regalada: puede comprar el extra');
escribe_json(dir_boda('regalada') . '/config.json', ['_estado' => 'archivada'] + config_inicial());
ok(extra_bloqueo('regalada', 'album') === 'archivada', 'web archivada: no');
ok(extra_bloqueo('ana-y-luis', 'inventado') === 'sin-extra', 'clave fuera de la lista: no');

// 8. Puertas de venta: en pruebas, sin sesión del estudio ni IP de la lista, no se vende
$_SERVER['REMOTE_ADDR'] = '203.0.113.9';
ok(!extra_venta_abierta() && !extra_disponible('ana-y-luis', 'album'), 'modo test: una IP cualquiera no abre la compra');
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
ok(count($ext) >= 3 && !array_filter($ext, fn($p) => $p['pack'] !== '') && !array_filter($ext, fn($p) => $p['estado'] === 'creada' && $p['extra'] !== 'album'), 'resumen: tipo extra, sin pack, con su clave');
$altas = array_filter($res['pedidos'], fn($p) => $p['tipo'] === 'alta' && !$p['regalo']);
ok(count($altas) === 6, 'resumen: las altas son solo las 6 webs pagadas del test, ningún extra (' . count($altas) . ')');
$bt = array_values(array_filter($res['bodas'], fn($b) => $b['slug'] === 'tercera'))[0] ?? [];
ok(($bt['extras'] ?? null) === ['album'], 'resumen: la boda dice qué extras tiene activos');
ok(array_column($res['precios']['extras'], 'clave') === ['idiomas', 'album', 'dominio'] && isset($res['precios']['extras'][0]['suelo']), 'resumen: precios y límites de los extras, sin el plano de mesas (incluido)');

// 10. El panel del estudio etiqueta el extra como extra, no como web publicada
$h = estudio_pedidos();
ok(strpos($h, 'Extra activado: Álbum de invitados') !== false, 'estudio: «Extra activado: Álbum de invitados»');


// 11. Plano de mesas GRATIS e incluido en todos los packs (owner, 27-sep-2026): sin compra, en toda web en pie y no archivada
web_pagada('incluida', '160');
ok(extra_activo('incluida', 'mesas') && empty(bp('incluida')['extras']), 'web pagada SIN comprar nada: el plano de mesas está activo');
ok(extra_bloqueo('incluida', 'mesas') === 'incluido' && !extra_disponible('incluida', 'mesas'), 'el plano no se ofrece a la venta (bloqueo «incluido»)');
escribe_json(dir_boda('regalo2') . '/config.json', config_inicial());
escribe_json(dir_boda('regalo2') . '/pedido.json', ['session_id' => 'cortesia_def']);
ok(extra_activo('regalo2', 'mesas'), 'web regalada con código: también lo tiene');
ok(!extra_activo('regalada', 'mesas'), 'web archivada: no');
ok(!extra_activo('no-existe', 'mesas') && !extra_activo('../x', 'mesas'), 'boda inexistente o slug inválido: no');
// Compra de prueba de ANTES de que fuera gratis (registro histórico) + su reembolso: no apaga el plano
$bi = bp('incluida'); $bi['extras'] = ['mesas' => ['desde' => '2026-09-27T12:00:00+02:00', 'pedido' => 'ls_801']]; escribe_json(dir_boda('incluida') . '/pedido.json', $bi);
$ti = bin2hex(random_bytes(16));
escribe_json(dir_datos('pedidos', 'ls_801.json'), lemon_pedido_base('ls_801', '801', pedido_ls(1900), 'incluida', $ti, '', null) + ['tipo' => 'extra', 'clave' => 'mesas', 'atelier' => '', 'estado' => 'creada']);
$tg0 = count(telegramas());
[, $r] = lemon_procesa_evento(ev_extra('order_refunded', '801', $ti, 'incluida', 'mesas'), lector(['801' => pedido_ls(1900, ['status' => 'refunded', 'refunded' => true, 'refunded_amount' => 1900])]));
ok($r === 'reembolsado' && extra_activo('incluida', 'mesas'), 'reembolso de una compra vieja del plano: el plano SIGUE activo');
ok(!empty(bp('incluida')['extras']['mesas']['baja']), 'el registro histórico se conserva y queda anotada la devolución');
$av = implode("\n", correos_con('Reembolso total de un extra de boda'));
ok(count(telegramas()) === $tg0 + 1 && strpos($av, 'va incluido en todos los packs') !== false && strpos($av, 'Pedido LS 801') !== false, 'el aviso al owner no dice «desactivado»: dice que va incluido');
$bt = array_values(array_filter(padrino_resumen()['bodas'], fn($b) => $b['slug'] === 'incluida'))[0] ?? [];
ok(($bt['extras'] ?? null) === [], 'resumen del Padrino: el plano incluido no cuenta como extra comprado');
// Un pago del plano que se abrió antes del cambio y se cobra ahora: no se «activa» ni se manda «Ya tenéis», se avisa para devolverlo
$yt0 = count(correos_con('Asunto: Ya tenéis Plano de mesas'));
$tv = extra_abierto('incluida', 'mesas', 1900);
[, $r] = lemon_procesa_evento(ev_extra('order_created', '802', $tv, 'incluida', 'mesas'), lector(['802' => pedido_ls(1900)]));
ok($r === 'duplicado' && count(correos_con('Asunto: Ya tenéis Plano de mesas')) === $yt0 && !is_file(extra_fichero_abierta('incluida', 'mesas')), 'pago del plano abierto antes del cambio: duplicado, sin correo de compra');
ok(strpos(implode("\n", correos_con('Extra pagado en una web que ya lo tenía')), '(va incluido en todos los packs). Devolver este cobro') !== false, 'y el aviso dice que va incluido, para devolverlo');
// Un corte a mitad de una activación vieja del plano tampoco se remata como compra
$bi = bp('incluida'); $bi['extras'] = ['mesas' => ['desde' => date('c'), 'pedido' => 'ls_803']]; escribe_json(dir_boda('incluida') . '/pedido.json', $bi);
$tw = extra_abierto('incluida', 'mesas', 1900);
escribe_json(dir_datos('pedidos', 'ls_803.json'), lemon_pedido_base('ls_803', '803', pedido_ls(1900), 'incluida', $tw, '', null) + ['tipo' => 'extra', 'clave' => 'mesas', 'atelier' => '']);
[, $r] = lemon_procesa_evento(ev_extra('order_created', '803', $tw, 'incluida', 'mesas'), lector(['803' => pedido_ls(1900)]));
ok($r === 'duplicado' && count(correos_con('Asunto: Ya tenéis Plano de mesas')) === $yt0, 'reintento de una activación vieja del plano: duplicado, sin «Ya tenéis»');

// 12. El mismo hecho en todos los sitios donde vive: el plano va incluido (código, condiciones y portada)
$cond = legal_a_texto(documento_legal('condiciones', 'c'));
// legal_a_texto pone los títulos en mayúsculas: se corta por el título exacto, de uno al siguiente
$tramo = function (string $desde, string $hasta) use ($cond): string {
    $a = strpos($cond, $desde); $b = $a === false ? false : strpos($cond, $hasta, $a);
    return $a === false || $b === false ? '' : substr($cond, $a, $b - $a);
};
$c2 = $tramo('2. QUÉ COMPRÁIS', '3. QUIÉN PUEDE COMPRAR');
$c5t = $tramo('5 TER. EXTRAS', '6. PLAZO DE ENTREGA');
ok(strpos($c2, 'Un plano de mesas en el panel') !== false && strpos($c2, 'el plano de mesas van incluidos en los dos packs') !== false, 'condiciones §2: el plano de mesas va incluido');
ok(strpos($c5t, 'Hoy no hay ninguno a la venta') !== false && mb_stripos($c5t, 'plano de mesas, ') === false && strpos($c5t, euros(EXTRAS['mesas']['cent'])) === false, 'condiciones §5 ter: sin extras a la venta y sin el plano con precio');
ok(stripos($cond, 'por ejemplo, el plano de mesas') === false, 'condiciones: el plano ya no sale como ejemplo de extra');
$land = pagina_landing();
$incl = substr($land, (int) strpos($land, 'id="incluye"'), 20000);
$incl = substr($incl, 0, (int) strpos($incl, '</section>'));
ok(strpos($incl, '<h3>Plano de mesas</h3>') !== false && strpos($incl, '<h3>Resumen para el catering</h3>') !== false && substr_count($incl, 'Incluido') >= 2, 'portada: tarjetas del plano y del catering, «Incluido»');
// El plano de la portada es un dibujo (ya no la captura del panel), en dos versiones (apaisado y de pie en móvil): 5 mesas y 44 sillas (8+10+8+10+8) cada una, las alergias marcadas,
// y el marcador de la cuenta atrás lleva su fecha para que landing.js lo refresque
ok(substr_count($incl, 'data-b-mesa ') === 10 && substr_count($incl, 'class="l-b-silla') === 88 && substr_count($incl, 'l-b-silla al') === 8, 'portada: plano dibujado dos veces (apaisado y de pie en móvil), cada uno con 5 mesas, 44 sillas y 4 con alergia');
ok(preg_match('~data-b-fin="\d{9,}"~', $incl) === 1 && strpos($incl, 'data-b-tip') !== false && strpos($incl, 'plano-mesas.webp') === false, 'portada: marcador con fecha, aviso del plano y sin la captura vieja');
ok(strpos($incl, '€') === false, 'portada: la sección de lo incluido no pone precio al plano');

foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
    $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
}
@rmdir($tmp);
echo ($fallos ? 'ROJO' : 'VERDE') . ': ' . ($n - $fallos) . "/$n\n";
exit($fallos ? 1 : 0);
