<?php
// Cobro por Lemon Squeezy (LS), Merchant of Record: LS es el vendedor, emite su factura al
// comprador y liquida el IVA. Encargo `encargos/20260926_bodas_lemonsqueezy.md` (repo del
// estudio) y revisión previa #109 (Seguridad, Legal, Administración).
//
// POR QUÉ ASÍ:
//  · El importe sale SIEMPRE del servidor: `custom_price` = el precio congelado en meta.json al
//    abrir el pedido. Del navegador solo llega el token del pedido pendiente.
//  · La firma del webhook solo demuestra que el evento viene de LS. La decisión se toma con el
//    pedido que se vuelve a pedir a la API (`GET /v1/orders/{id}`) y se compara con meta.json:
//    tienda, producto, variante, moneda, cantidad, total == precio congelado, sin descuento,
//    `paid` y el modo (test/live) esperado. Si algo no cuadra: «no-conforme», sin alta y aviso.
//  · Doble llave de idempotencia: `pedidos/ls_<order_id>.json` (el mismo pedido llega N veces) y
//    `ls_tokens/<token>.json` (dos pedidos distintos para el mismo token → el segundo es
//    «duplicado», cobrado sin web y con aviso para devolverlo). El índice de tokens vive FUERA de
//    `pedidos/` porque el panel del estudio y el resumen del Padrino recorren esa carpeta.
//  · «reembolsado», «no-conforme» y «duplicado» son finales: un evento posterior no los reabre.
//  · Tienda, variante y modo se leen de secrets.php SIN valor por defecto: los ids de test y de
//    live son distintos, y una configuración a medias tiene que fallar cerrada (no a la venta).
//  · Sin factura `BODA-`: con un Merchant of Record el vendedor es LS (Administración #109).

declare(strict_types=1);

defined('LEMON_API') || define('LEMON_API', 'https://api.lemonsqueezy.com');
const LEMON_CADUCIDAD_S = 1800;   // igual que la sesión de Stripe: la reserva del nombre dura lo mismo
/** Estados de un pedido LS que ningún evento posterior puede cambiar (salvo el reembolso, que se anota). */
const LEMON_FINALES = ['creada', 'reembolsado', 'no-conforme', 'duplicado', 'sin-datos'];

/** Pasarela activa: la elige secrets.php. Stripe queda en el código, apagado salvo que se pida. */
function pasarela(): string { return secreto('pasarela', 'lemon') === 'stripe' ? 'stripe' : 'lemon'; }
/** Nota bajo el botón de pagar. Los textos legales con LS como vendedor son de Legal (encargo aparte). */
function nota_pago(): string {
    return pasarela() === 'lemon' ? 'Pago seguro con Lemon Squeezy, que os enviará el recibo por email.' : 'Pago seguro con Stripe. Recibiréis la factura por email.';
}

/** Configuración de LS completa (clave, secreto de firma, tienda y variante). Si falta algo, no se vende. */
function lemon_configurada(): bool {
    foreach (['lemon_api_key', 'lemon_webhook_secret', 'lemon_tienda', 'lemon_variante', 'lemon_producto'] as $k) {
        if ((string) secreto($k) === '') return false;
    }
    return true;
}
/** Modo esperado de los pedidos. Por defecto TEST: pasar a live es decisión del owner (hard stop). */
function lemon_test(): bool { return secreto('lemon_test', true) !== false; }

/** Llamada a la API (JSON:API). Devuelve [status, cuerpo decodificado]. Nunca lanza. */
function lemon_api(string $metodo, string $ruta, ?array $cuerpo = null): array {
    $key = (string) secreto('lemon_api_key');
    if ($key === '') return [0, ['errors' => [['detail' => 'Lemon Squeezy sin configurar']]]];
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => rtrim(LEMON_API, '/') . $ruta,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Accept: application/vnd.api+json', 'Content-Type: application/vnd.api+json', 'Authorization: Bearer ' . $key],
    ]);
    if ($metodo === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($cuerpo ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false) {
        registra('lemon: fallo de red', ['ruta' => $ruta, 'error' => $err]);
        return [0, ['errors' => [['detail' => 'Sin conexión con Lemon Squeezy']]]];
    }
    $d = json_decode((string) $raw, true);
    return [$status, is_array($d) ? $d : []];
}

/**
 * Cuerpo del checkout. Función pura (se prueba sin red): el importe es el argumento que el
 * servidor ha congelado en meta.json, sin descuentos y con la variante de secrets.php.
 */
function lemon_cuerpo_checkout(string $token, string $slug, string $email, int $precioCent, int $ahora): array {
    return ['data' => [
        'type' => 'checkouts',
        'attributes' => [
            'custom_price' => $precioCent,
            'product_options' => [
                'name' => 'Web de boda — ' . $slug . '.' . BASE_DOMAIN,
                'description' => 'Creación y alojamiento hasta ' . MESES_ALOJAMIENTO . ' meses después de la boda.',
                'enabled_variants' => [(int) secreto('lemon_variante')],
                'redirect_url' => url_creador('listo?t=' . $token),
            ],
            'checkout_options' => ['discount' => false, 'quantity' => 1],
            'checkout_data' => [
                'email' => $email,
                // Marca de propiedad y enlace con el pedido pendiente (cadenas: LS las devuelve en meta.custom_data)
                'custom' => ['token' => $token, 'slug' => $slug, 'producto' => PRODUCTO],
            ],
            'expires_at' => gmdate('Y-m-d\TH:i:s\Z', $ahora + LEMON_CADUCIDAD_S),
        ],
        'relationships' => [
            'store' => ['data' => ['type' => 'stores', 'id' => (string) secreto('lemon_tienda')]],
            'variant' => ['data' => ['type' => 'variants', 'id' => (string) secreto('lemon_variante')]],
        ],
    ]];
}

/** Crea el checkout. Devuelve la URL de pago o '' si LS no la ha dado. */
function lemon_crea_checkout(string $token, string $slug, string $email, int $precioCent): string {
    [$st, $d] = lemon_api('POST', '/v1/checkouts', lemon_cuerpo_checkout($token, $slug, $email, $precioCent, time()));
    $url = (string) ($d['data']['attributes']['url'] ?? '');
    if ($st !== 201 || strpos($url, 'https://') !== 0) {
        registra('lemon: no se pudo crear el checkout', ['status' => $st, 'error' => (string) ($d['errors'][0]['detail'] ?? '')]);
        return '';
    }
    return $url;
}

/** Firma X-Signature: HMAC-SHA256 hexadecimal del cuerpo crudo. Sin secreto no hay firma buena. */
function lemon_firma_ok(string $payload, string $firma): bool {
    $secret = (string) secreto('lemon_webhook_secret');
    if ($secret === '' || $firma === '' || !ctype_xdigit($firma)) return false;
    return hash_equals(hash_hmac('sha256', $payload, $secret), strtolower($firma));
}

/** Atributos del pedido leídos de la API, o null si no se ha podido leer (el webhook pide reintento). */
function lemon_lee_pedido(string $id): ?array {
    if (!preg_match('/^\d{1,15}$/', $id)) return null;
    [$st, $d] = lemon_api('GET', '/v1/orders/' . $id);
    if ($st !== 200 || ($d['data']['type'] ?? '') !== 'orders' || (string) ($d['data']['id'] ?? '') !== $id) return null;
    return (array) ($d['data']['attributes'] ?? []);
}

/**
 * Motivos por los que un pedido NO cuadra con lo que vendimos (vacío = conforme). Pura: se prueba sin red.
 * $meta = pendientes/<token>/meta.json (precio congelado y slug); $custom = meta.custom_data del evento.
 */
function lemon_motivos_no_conforme(array $o, array $meta, array $custom): array {
    $m = [];
    $item = (array) ($o['first_order_item'] ?? []);
    if ((string) ($o['store_id'] ?? '') !== (string) secreto('lemon_tienda')) $m[] = 'tienda';
    if ((string) ($item['variant_id'] ?? '') !== (string) secreto('lemon_variante')) $m[] = 'variante';
    if ((string) ($item['product_id'] ?? '') !== (string) secreto('lemon_producto')) $m[] = 'producto';
    if ((int) ($item['quantity'] ?? 1) !== 1) $m[] = 'cantidad';
    if (strtoupper((string) ($o['currency'] ?? '')) !== 'EUR') $m[] = 'moneda';
    if (!isset($meta['precio_cent']) || (int) ($o['total'] ?? -1) !== (int) $meta['precio_cent']) $m[] = 'total';
    if ((int) ($o['discount_total'] ?? -1) !== 0) $m[] = 'descuento';
    if (($o['status'] ?? '') !== 'paid') $m[] = 'estado';
    if ((bool) ($o['test_mode'] ?? !lemon_test()) !== lemon_test()) $m[] = 'modo';
    if (($meta['pasarela'] ?? '') !== 'lemon') $m[] = 'pasarela';
    if ((string) ($custom['slug'] ?? '') !== (string) ($meta['slug'] ?? '')) $m[] = 'slug';
    return $m;
}

/**
 * Procesa un evento YA firmado. $leePedido(id) → atributos del pedido desde la API (inyectado para
 * las pruebas). Devuelve [status HTTP, detalle]: 5xx hace que LS reintente la entrega.
 */
function lemon_procesa_evento(array $ev, callable $leePedido): array {
    $nombre = (string) ($ev['meta']['event_name'] ?? '');
    $custom = (array) ($ev['meta']['custom_data'] ?? []);
    if (($ev['data']['type'] ?? '') !== 'orders' || !in_array($nombre, ['order_created', 'order_refunded'], true)) return [200, 'ignorado'];
    // La tienda es solo de BodaEnlace, pero la marca de propiedad se comprueba igual (lección B2K→Sumba)
    if (($custom['producto'] ?? '') !== PRODUCTO) return [200, 'ajeno'];
    $id = (string) ($ev['data']['id'] ?? '');
    $o = preg_match('/^\d{1,15}$/', $id) ? $leePedido($id) : null;
    if ($o === null) {
        registra('lemon: no se pudo releer el pedido', ['id' => $id, 'evento' => $nombre]);
        return [503, 'sin-lectura'];
    }
    if ($nombre === 'order_refunded') return [200, lemon_reembolso($id, $o, $custom)];
    return [200, (string) (lemon_alta($id, $o, $custom)['estado'] ?? '')];
}

/** Alta de la web de un pedido LS cobrado. Idempotente por pedido y por token, bajo el cerrojo global. */
function lemon_alta(string $id, array $o, array $custom): array {
    $sid = 'ls_' . $id;
    $fPedido = dir_datos('pedidos', $sid . '.json');
    return con_cerrojo(function () use ($id, $o, $custom, $sid, $fPedido) {
        $ped = lee_json($fPedido);
        if ($ped && in_array($ped['estado'] ?? '', LEMON_FINALES, true)) return $ped;

        $token = (string) ($custom['token'] ?? '');
        $tokOk = (bool) preg_match('/^[a-f0-9]{32}$/', $token);
        $fTok = $tokOk ? dir_datos('ls_tokens', $token . '.json') : '';
        $pend = $tokOk ? dir_datos('pendientes', $token) : '';
        $meta = $pend !== '' ? lee_json($pend . '/meta.json') : null;
        $cfg = $pend !== '' ? (lee_json($pend . '/config.json') ?? []) : [];
        $slug = (string) ($meta['slug'] ?? $custom['slug'] ?? '');
        $total = (int) ($o['total'] ?? 0);
        $iva = (int) ($o['tax'] ?? 0);
        $email = (string) ($o['user_email'] ?? '');
        $ped = $ped ?: [
            'session_id' => $sid, 'pasarela' => 'lemon', 'slug' => $slug, 'token' => $token, 'creado' => date('c'),
            'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : (string) ($cfg['pareja']['email'] ?? ''),
            'nombre' => (string) ($o['user_name'] ?? ''),
            // Importes del propio pedido (IVA según el país del comprador; lo liquida LS, no nosotros)
            'importe' => ['base' => $total - $iva, 'iva' => $iva, 'total' => $total],
            'ls' => ['order_id' => $id, 'order_number' => (int) ($o['order_number'] ?? 0), 'identifier' => (string) ($o['identifier'] ?? ''),
                'moneda' => (string) ($o['currency'] ?? ''), 'iva_nombre' => (string) ($o['tax_name'] ?? ''), 'iva_pct' => (string) ($o['tax_rate'] ?? ''),
                'test' => (bool) ($o['test_mode'] ?? false)],
            'aceptacion' => $meta['aceptacion'] ?? null,
            'atelier' => isset(ATELIER[$cfg['atelier'] ?? '']) ? (string) $cfg['atelier'] : '',
            'factura' => '',   // Merchant of Record: la factura la emite LS (Administración #109)
            'estado' => 'cobrada',
        ];

        // Llave 2: un token ya atado a OTRO pedido → este cobro sobra (se avisa para devolverlo)
        $previo = $fTok !== '' ? (string) ((lee_json($fTok) ?? [])['order_id'] ?? '') : '';
        if ($previo !== '' && $previo !== $id) {
            $ped['estado'] = 'duplicado';
            $ped['duplicado_de'] = 'ls_' . $previo;
            escribe_json($fPedido, $ped);
            avisa_estudio('Pago duplicado de una web de boda', "Pedido LS $id ({$slug}): el mismo pedido del creador ya se pagó con el pedido LS $previo. No se ha creado otra web. Devolver este cobro desde Lemon Squeezy.");
            return $ped;
        }
        // El token queda atado a este pedido sea cual sea el resultado: /listo lo lee y un segundo cobro es «duplicado»
        if ($fTok !== '' && $previo === '') escribe_json($fTok, ['order_id' => $id, 'creado' => date('c')]);
        if (!$meta) {
            // Sin pendiente no hay precio congelado con el que comparar: ni se valida ni se crea nada
            $ped['estado'] = 'sin-datos';
            escribe_json($fPedido, $ped);
            avisa_estudio('Pago de web de boda sin datos del creador', "Pedido LS $id ($slug). Cobrado; la web no se ha podido crear. Contactar con {$ped['email']}.");
            return $ped;
        }
        $motivos = lemon_motivos_no_conforme($o, $meta, $custom);
        if ($motivos) {
            $ped['estado'] = 'no-conforme';
            $ped['motivos'] = $motivos;
            escribe_json($fPedido, $ped);
            avisa_estudio('Pago de web de boda que no cuadra', "Pedido LS $id ($slug) cobrado pero no cuadra con lo vendido: " . implode(', ', $motivos) . ". No se ha creado la web. Revisar en Lemon Squeezy y devolver o publicar a mano.");
            return $ped;
        }
        if (($ped['estado'] ?? '') === 'cobrada' && empty($ped['_analitica'])) {
            analitica_evento('alta');   // una vez por pedido
            $ped['_analitica'] = 1;
        }
        escribe_json($fPedido, $ped);
        return alta_publica($ped, $pend, $meta, $fPedido, $sid, $token, $slug);
    });
}

/** Reembolso: se marca el pedido y se avisa. La web NO se borra sola (decide el owner). */
function lemon_reembolso(string $id, array $o, array $custom): string {
    $total = ($o['status'] ?? '') === 'refunded';
    if (!$total && ($o['status'] ?? '') !== 'partial_refund' && empty($o['refunded'])) return 'sin-reembolso';
    $sid = 'ls_' . $id;
    $fPedido = dir_datos('pedidos', $sid . '.json');
    return con_cerrojo(function () use ($id, $o, $custom, $sid, $fPedido, $total) {
        $ped = lee_json($fPedido);
        $ya = $ped['reembolso']['fecha'] ?? '';
        if ($ped && $ya !== '' && ($ped['reembolso']['total'] ?? false) === $total) return (string) $ped['estado'];
        // Sin registro previo (el reembolso llega antes que el alta) se crea igual: así el order_created
        // que llegue después encuentra un estado final y no publica nada
        $ped = $ped ?: ['session_id' => $sid, 'pasarela' => 'lemon', 'slug' => (string) ($custom['slug'] ?? ''), 'token' => (string) ($custom['token'] ?? ''),
            'creado' => date('c'), 'email' => (string) ($o['user_email'] ?? ''), 'factura' => '', 'importe' => ['base' => 0, 'iva' => 0, 'total' => 0], 'estado' => 'sin-alta'];
        $ped['reembolso'] = ['fecha' => date('c'), 'total' => $total, 'importe_cent' => (int) ($o['refunded_amount'] ?? 0)];
        // Final siempre que sea total o que llegue antes que el alta (un 'sin-alta' dejaría publicar después)
        if ($total || $ped['estado'] === 'sin-alta') {
            $ped['estado_previo'] = (string) ($ped['estado'] ?? '');
            $ped['estado'] = 'reembolsado';
        }
        escribe_json($fPedido, $ped);
        $web = ($ped['estado_previo'] ?? $ped['estado']) === 'creada' ? ' La web ' . url_boda((string) $ped['slug']) . ' sigue publicada: decide si retirarla.' : '';
        avisa_estudio('Reembolso ' . ($total ? 'total' : 'parcial') . ' de una web de boda', "Pedido LS $id ({$ped['slug']})." . $web);
        return (string) $ped['estado'];
    });
}

/** Webhook POST /api/lemon. */
function api_lemon(string $metodo): void {
    if ($metodo !== 'POST') json_response(['ok' => false], 405);
    $payload = (string) file_get_contents('php://input', false, null, 0, 1000000);
    if (!lemon_firma_ok($payload, (string) ($_SERVER['HTTP_X_SIGNATURE'] ?? ''))) {
        registra('lemon: firma rechazada', ['ip' => ip_cliente()]);
        json_response(['ok' => false], 401);
    }
    $ev = json_decode($payload, true);
    if (!is_array($ev)) json_response(['ok' => false], 400);
    [$st, $det] = lemon_procesa_evento($ev, 'lemon_lee_pedido');
    json_response(['ok' => $st < 300, 'resultado' => $det], $st);
}

/** /listo?t=<token>: SOLO el pedido local (los ids de LS son secuenciales y no vienen del navegador). */
function listo_lemon(string $token): void {
    $idx = lee_json(dir_datos('ls_tokens', $token . '.json'));
    $oid = (string) ($idx['order_id'] ?? '');
    $ped = preg_match('/^\d{1,15}$/', $oid) ? lee_json(dir_datos('pedidos', 'ls_' . $oid . '.json')) : null;
    if (!$ped) {
        // Aún sin aviso de LS: si el pedido pendiente existe, se está confirmando; si no, no es nuestro
        if (is_dir(dir_datos('pendientes', $token))) {
            echo pagina_simple('Confirmando el pago', '<p>Estamos esperando la confirmación del pago. Recarga esta página en un minuto; te llegará también un email.</p>');
        } else {
            echo pagina_simple('Pago no encontrado', '<p>No encontramos este pago. Si te han cobrado, escríbenos a ' . h(empresa()['email']) . '.</p>');
        }
        return;
    }
    if (($ped['estado'] ?? '') === 'creada') { listo_muestra($ped); return; }
    echo pagina_simple('Pago recibido', '<p>Hemos recibido el pago, pero tenemos que revisarlo antes de publicar la web. Ya nos ha llegado el aviso y te escribimos en breve a ' . h((string) ($ped['email'] ?? '')) . '.</p>');
}
