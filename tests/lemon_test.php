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
    'telegram_token' => 'x', 'telegram_chat' => '1',
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
/** Correos dejados en disco (CORREO_A_FICHERO) cuyo destinatario es $para. */
function correos_a(string $para): int {
    return count(array_filter(glob(dir_datos('correos', '*')) ?: [], fn($f) => strpos((string) file_get_contents($f), "Para: $para\n") === 0));
}
function telegramas(): array { return array_map('file_get_contents', glob(dir_datos('telegram', '*')) ?: []); }
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
ok(correos_a('pareja@example.com') === 1, 'un solo correo de bienvenida');
ok(correos_a('hola@bodaenlace.com') === 1, 'un solo aviso de venta al owner por correo');
$tg = telegramas();
ok(count($tg) === 1 && strpos($tg[0], 'Venta nueva (prueba)') !== false && strpos($tg[0], 'Pedido LS 101') !== false, 'un solo aviso de venta por Telegram, con referencia neutra');
ok(strpos($tg[0], 'ana-y-luis') === false && strpos($tg[0], 'Ana') === false && strpos($tg[0], '@example') === false, 'BOD-17: Telegram sin slug, nombres ni email');
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
ok(strpos($tl, 'Venta y cobro: Lemon Squeezy') !== false && stripos($tl, 'os la regala') === false && strpos($tl, 'Antes de pagar') !== false, 'correo LS: rama propia, sin regalo');
$tr = texto_bienvenida($base + ['factura' => ''], $cfg, 'https://enlace');
ok(stripos($tr, 'os la regala') !== false && strpos($tr, 'Venta y cobro') === false && strpos($tr, 'Desistimiento:') === false, 'correo regalo intacto: sin venta ni desistimiento en el resumen');
// Legal (27-sep, BOD-6): soporte duradero = el CUERPO. Condiciones íntegras dentro, casillas literales, sin adjunto
$acLs = ['fecha' => date('c'), 'version' => textos_legales()['version'], 'condiciones' => textos_legales()['check_condiciones'], 'desistimiento' => textos_legales()['check_desistimiento']];
$tl2 = texto_bienvenida(['slug' => 'x', 'email' => 'a@b.c', 'pasarela' => 'lemon', 'factura' => '', 'aceptacion' => $acLs, 'ls' => ['order_number' => 77], 'importe' => ['total' => 12500]], $cfg, 'https://enlace');
ok(strpos($tl2, '«' . $acLs['condiciones'] . '»') !== false && strpos($tl2, '«' . $acLs['desistimiento'] . '»') !== false, 'bienvenida LS: las dos casillas, literales');
ok(strpos($tl2, 'CONDICIONES DEL SERVICIO') !== false && strpos($tl2, '8. DESISTIMIENTO Y REEMBOLSOS') !== false && strpos($tl2, 'ANEXO II') !== false, 'bienvenida LS: condiciones íntegras en el cuerpo');
ok(strpos($tl2, 'pedido n.º 77') !== false && strpos($tl2, 'versión ' . $acLs['version']) !== false, 'bienvenida LS: número de pedido y versión de las condiciones');
ok(!preg_match('~</?(p|h1|h2|ul|li|a|strong|em)\b~', $tl2), 'bienvenida: sin etiquetas HTML en el texto plano');
correo_bienvenida(['slug' => 'x', 'email' => 'adj@b.c', 'pasarela' => 'lemon', 'factura' => '', 'aceptacion' => $acLs], $cfg, 'https://enlace');
$adj = array_values(array_filter(array_map('file_get_contents', glob(dir_datos('correos', '*')) ?: []), fn($c) => strpos($c, 'Para: adj@b.c') === 0));
ok(count($adj) === 1 && preg_match('/Adjuntos: *$/', rtrim($adj[0])) === 1, 'bienvenida sin ningún adjunto (spam): ' . count($adj));
// Casillas y legales: sin marcadores sin sustituir, vendedor en un solo sitio, sin la promesa de devolución proporcional
foreach (textos_legales() as $k => $v) if (is_string($v)) ok(!preg_match('/\{(titular|marca|vendedor)\}/', $v), "textos.php $k sin marcadores sin sustituir");
ok(strpos(textos_legales()['check_desistimiento'], 'Lemon Squeezy') !== false && strpos(textos_legales()['check_condiciones_regalo'], 'Lemon') === false, 'casilla de pago nombra al vendedor; la de regalo no');
$E = empresa();
foreach (['condiciones', 'privacidad', 'aviso-legal'] as $doc) {
    $h = documento_legal($doc, $doc);
    ok(strpos($h, 'Lemon Squeezy') !== false && strpos($h, 'Stripe') === false && strpos($h, 'axisworks.studio') === false, "$doc nombra a LS, no a Stripe, y el dominio es el del producto");
}
$cond = legal_a_texto(documento_legal('condiciones', 'c'));
ok(stripos($cond, 'parte proporcional a lo ya prestado') !== false && stripos($cond, 'factura simplificada') === false, 'condiciones: avisan del pago proporcional del alojamiento (108.3/108.4) y sin factura propia');
ok(stripos(textos_legales()['check_desistimiento'], 'no se puede pedir') === false && stripos(textos_legales()['check_mejora'], 'no se puede pedir') === false, 'casillas sin renuncia previa al reembolso (art. 10 TRLGDCU)');
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
$correos = correos_a('p@example.com');
[$st, $r] = lemon_procesa_evento(evento('order_created', '700', $t, 'corte'), lector(['700' => pedido_ls($precio)]));
ok($r === 'creada' && !boda_existe('corte-2'), 'reintento tras corte: misma web, sin «-2»');
ok(correos_a('p@example.com') === $correos + 1, 'reintento tras corte: el correo sale');
ok(!empty((lee_json(dir_boda('corte') . '/pedido.json') ?? [])['test']), 'la web de un pedido de prueba queda marcada como test');

// 11. Avisos de estados raros también llegan al owner (correo + Telegram)
$antes = count(telegramas());
$t = pendiente('raro', $precio);
lemon_procesa_evento(evento('order_created', '800', $t, 'raro'), lector(['800' => pedido_ls($precio - 1)]));
ok(count(telegramas()) === $antes + 1 && strpos(implode("\n", telegramas()), 'no cuadra') !== false, 'no-conforme avisa por Telegram');

// 12. Correo MIME: asunto codificado, adjunto en base64, sin saltos que inyecten cabeceras
$mime = mensaje_mime('hola@bodaenlace.com', 'a@b.c', "Asunto\r\nBcc: x@y.z", "Hola\n.\nfin", ['condiciones.html' => '<p>x</p>']);
ok(strpos($mime, "\r\nBcc:") === false, 'el asunto no inyecta cabeceras');
ok(strpos($mime, 'From: BodaEnlace <hola@bodaenlace.com>') !== false && strpos($mime, 'filename="condiciones.html"') !== false, 'remitente de la marca y adjunto');
ok(!envia_correo("a@b.c\r\nBcc: x@y.z", 'x', 'y'), 'destinatario con salto de línea rechazado');

// 13. Menú del banquete: opcional, con tope, escapado y en la página de confirmación (y en el ZIP)
$c = config_inicial();
foreach ($c['secciones'] as &$s) if ($s['tipo'] === 'rsvp') $s['datos']['banquete'] = "Aperitivo: croquetas <script>alert(1)</script>\nPrincipal: lubina\nBarra libre\n" . str_repeat('x', 3000);
unset($s);
$n2 = normaliza_config($c);
$b = array_values(array_filter($n2['secciones'], fn($s) => $s['tipo'] === 'rsvp'))[0]['datos']['banquete'];
ok(mb_strlen($b) === MAX_BANQUETE, 'banquete con tope de caracteres');
$html = bloque_banquete($b);
ok(strpos($html, '<script>') === false && strpos($html, '&lt;script&gt;') !== false, 'banquete escapado');
ok(strpos($html, '<h3>Aperitivo</h3>') !== false && strpos($html, '<h3>Principal</h3>') !== false && strpos($html, '<p>Barra libre</p>') !== false, 'una línea por momento');
ok(bloque_banquete('') === '', 'sin banquete no se pinta nada');
$rs = array_values(array_filter($n2['secciones'], fn($s) => $s['tipo'] === 'rsvp'))[0];
ok(strpos(pagina_seccion($n2, $rs, ['modo' => 'zip', 'assets' => 'assets/', 'foto' => '', 'slug' => 'x']), 'EL BANQUETE') !== false, 'el banquete sale en el ZIP');
$vacio = normaliza_config(config_inicial());
ok(array_values(array_filter($vacio['secciones'], fn($s) => $s['tipo'] === 'rsvp'))[0]['datos']['banquete'] === '', 'banquete vacío por defecto');

// 14. Mejora Esencial → Atelier: importe del servidor, validación, idempotencia y estados finales
$pm = precio_mejora_cent();
ok($pm === precio_atelier_cent() - precio_esencial_cent() && $pm > 0, 'mejora = diferencia de packs al precio vigente');
$cm = lemon_cuerpo_checkout(str_repeat('b', 32), 'corte', 'p@example.com', $pm, 1000000, 'mejora');
ok($cm['data']['attributes']['custom_price'] === $pm && $cm['data']['attributes']['checkout_data']['custom']['tipo'] === 'mejora', 'checkout de mejora con importe del servidor y tipo');
ok($cm['data']['attributes']['checkout_options']['discount'] === false, 'mejora sin descuentos');
ok($cm['data']['attributes']['product_options']['redirect_url'] === 'https://corte.bodaenlace.com/panel/editar?mejora=1', 'la mejora vuelve al panel');
ok(!isset($cu['data']['attributes']['checkout_data']['custom']['tipo']), 'el alta no lleva tipo');
ok(mejora_bloqueo('corte') === '', 'boda Esencial pagada: se puede mejorar');
ok(mejora_bloqueo('ana-y-luis') === 'sin-pedido', 'boda reembolsada: no se puede mejorar');
function mejora_abierta(string $slug, int $precio): string {
    $t = bin2hex(random_bytes(16));
    escribe_json(dir_datos('mejoras', $t . '.json'), ['tipo' => 'mejora', 'slug' => $slug, 'creado' => time(), 'precio_cent' => $precio, 'pasarela' => 'lemon', 'estado' => 'abierta',
        'aceptacion' => ['fecha' => '2026-09-27T10:15:00+02:00', 'version' => 'x', 'desistimiento' => 'CASILLA-MEJORA-LITERAL', 'vendedor' => 'Lemon Squeezy', 'precio_cent' => $precio]]);
    return $t;
}
function ev_mejora(string $id, string $tok, string $slug): array {
    $e = evento('order_created', $id, $tok, $slug);
    $e['meta']['custom_data']['tipo'] = 'mejora';
    return $e;
}
// Importe manipulado (el precio de alta en vez de la diferencia) → no-conforme, sin Atelier
$tm = mejora_abierta('corte', $pm);
[$st, $r] = lemon_procesa_evento(ev_mejora('900', $tm, 'corte'), lector(['900' => pedido_ls($pm + 100)]));
ok($r === 'no-conforme' && empty((lee_json(dir_boda('corte') . '/pedido.json') ?? [])['atelier']), 'mejora con importe distinto: no-conforme y sin Atelier');
// Buena, entregada 3 veces → un solo cambio, un correo a la pareja, un Telegram
$tm = mejora_abierta('corte', $pm);
$tgAntes = count(telegramas());
$paAntes = correos_a('pareja@example.com');
for ($i = 0; $i < 3; $i++) [$st, $r] = lemon_procesa_evento(ev_mejora('901', $tm, 'corte'), lector(['901' => pedido_ls($pm)]));
ok($r === 'creada', 'mejora aplicada (3 entregas)');
$bpc = lee_json(dir_boda('corte') . '/pedido.json') ?? [];
ok(!empty($bpc['atelier']) && ($bpc['mejora']['session_id'] ?? '') === 'ls_901', 'la boda pasa a Atelier y guarda qué pedido la mejoró');
ok(count(telegramas()) === $tgAntes + 1 && correos_a('pareja@example.com') === $paAntes + 1, 'un solo aviso por mejora (pareja + Telegram)');
// Art. 98.7: el correo de la mejora repite literal la casilla aceptada, con su fecha, el importe y el vendedor
$cm = array_values(array_filter(array_map('file_get_contents', glob(dir_datos('correos', '*')) ?: []), fn($t) => strpos($t, 'Asunto: Ya tenéis el Pack Atelier') !== false));
$cm = end($cm) ?: '';
ok(strpos($cm, '«CASILLA-MEJORA-LITERAL»') !== false && strpos($cm, '27/09/2026 10:15') !== false && strpos($cm, euros($pm) . ', IVA incluido') !== false && strpos($cm, 'Lemon Squeezy') !== false,
    'correo de la mejora: casilla literal, fecha, importe y vendedor');
ok((lee_json(dir_datos('mejoras', $tm . '.json'))['estado'] ?? '') === 'pagada', 'el pedido de mejora queda pagado');
ok(facturas_emitidas() === 0, 'la mejora tampoco emite factura BODA-');
// Otro cobro con el mismo token → duplicado; y una mejora nueva sobre una web ya Atelier → duplicado
[$st, $r] = lemon_procesa_evento(ev_mejora('902', $tm, 'corte'), lector(['902' => pedido_ls($pm)]));
ok($r === 'duplicado', 'segundo cobro de la misma mejora: duplicado');
$tm2 = mejora_abierta('corte', $pm);
[$st, $r] = lemon_procesa_evento(ev_mejora('903', $tm2, 'corte'), lector(['903' => pedido_ls($pm)]));
ok($r === 'duplicado', 'mejora pagada en una web que ya es Atelier: duplicado');
ok(mejora_bloqueo('corte') === 'ya-atelier', 'una web Atelier ya no ofrece la mejora');
// Sin pedido de mejora congelado → sin-datos, nada cambia
[$st, $r] = lemon_procesa_evento(ev_mejora('904', str_repeat('c', 32), 'corte'), lector(['904' => pedido_ls($pm)]));
ok($r === 'sin-datos', 'mejora sin su pedido en el servidor: sin-datos');

// 15. Mejora: corte a mitad, reembolso, marca abierta, resumen del Padrino y texto de Legal
// Corte entre marcar la boda y cerrar el pedido: el reintento remata (no es un duplicado) y el aviso sale
$t = pendiente('corte-mejora', $precio);
lemon_procesa_evento(evento('order_created', '950', $t, 'corte-mejora'), lector(['950' => pedido_ls($precio)]));
$tm = mejora_abierta('corte-mejora', $pm);
$bp = lee_json(dir_boda('corte-mejora') . '/pedido.json');
escribe_json(dir_boda('corte-mejora') . '/pedido.json', $bp + ['atelier' => true, 'mejora' => ['session_id' => 'ls_951']]);
$bp2 = lee_json(dir_boda('corte-mejora') . '/pedido.json'); $bp2['atelier'] = true; escribe_json(dir_boda('corte-mejora') . '/pedido.json', $bp2);
escribe_json(dir_datos('pedidos', 'ls_951.json'), lemon_pedido_base('ls_951', '951', pedido_ls($pm), 'corte-mejora', $tm, '', null) + ['tipo' => 'mejora', 'atelier' => '']);
escribe_json(dir_datos('ls_tokens', $tm . '.json'), ['order_id' => '951']);
$tgAntes = count(telegramas());
[$st, $r] = lemon_procesa_evento(ev_mejora('951', $tm, 'corte-mejora'), lector(['951' => pedido_ls($pm)]));
ok($r === 'creada' && count(telegramas()) === $tgAntes + 1, 'mejora cortada a medias: el reintento la remata y avisa');
// Rechazada (no-conforme) libera la marca abierta: la pareja puede reintentar sin esperar
$t = pendiente('marca', $precio);
lemon_procesa_evento(evento('order_created', '960', $t, 'marca'), lector(['960' => pedido_ls($precio)]));
$tm = mejora_abierta('marca', $pm);
escribe_json(dir_datos('mejoras', 'abierta_marca.json'), ['token' => $tm, 'hasta' => time() + 1800]);
lemon_procesa_evento(ev_mejora('961', $tm, 'marca'), lector(['961' => pedido_ls($pm - 1)]));
ok(!is_file(dir_datos('mejoras', 'abierta_marca.json')), 'mejora rechazada libera la marca abierta');
// Reembolso de una mejora: el aviso dice que la boda conserva el Atelier
$tgAntes = count(telegramas());
lemon_procesa_evento(evento('order_refunded', '901', '', 'corte'), lector(['901' => pedido_ls($pm, ['status' => 'refunded'])]));
$tgs = telegramas();
$aOwner = implode("\n", array_map('file_get_contents', array_filter(glob(dir_datos('correos', '*')) ?: [], fn($f) => strpos((string) file_get_contents($f), 'Para: hola@bodaenlace.com') === 0)));
ok(count($tgs) === $tgAntes + 1 && strpos($aOwner, 'conserva el Atelier') !== false, 'reembolso de mejora: aviso propio (correo al owner)');
ok(!array_filter($tgs, fn($t) => preg_match('/corte|ana-y-luis|marca\.bodaenlace|@example/', $t)), 'BOD-17: ningún Telegram lleva slug ni email');
// El Padrino ve la mejora como mejora, no como venta nueva de Esencial
$pr = array_values(array_filter(padrino_resumen()['pedidos'], fn($p) => $p['tipo'] === 'mejora'));
ok(count($pr) >= 2 && !array_filter($pr, fn($p) => $p['pack'] !== 'atelier'), 'resumen del Padrino: tipo mejora, pack atelier');
// Sin la casilla de Legal propia de la mejora, no se ofrece (la del alta dice «que cree y publique»)
ok(texto_mejora() === (string) (textos_legales()['check_mejora'] ?? ''), 'la casilla de la mejora es solo la de Legal (check_mejora), nunca la del alta');

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
