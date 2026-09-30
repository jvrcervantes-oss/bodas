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

require_once __DIR__ . '/servicios.php';   // camino común de la mejora y los extras (BOD-23)

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
/**
 * ¿Puede esta petición abrir un checkout? En modo TEST el pago es con la tarjeta de prueba pública
 * de LS, así que abrirlo a cualquiera sería regalar webs de verdad (revisor, 27-sep-2026): solo con
 * la sesión del panel del estudio o desde una IP de `lemon_test_ips` (secrets.php). En live, todos.
 */
function lemon_checkout_permitido(): bool {
    if (!lemon_test()) return true;
    if (in_array(ip_cliente(), (array) secreto('lemon_test_ips', []), true)) return true;
    return function_exists('estudio_dentro') && estudio_dentro();
}

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
function lemon_cuerpo_checkout(string $token, string $slug, string $email, int $precioCent, int $ahora, string $tipo = 'alta', string $clave = ''): array {
    $mejora = $tipo === 'mejora';
    // Extra de pago (plano de mesas…): la clave va en custom_data solo como rastro. Qué se activa lo decide
    // el fichero del servidor extras/<token>.json, nunca este campo (Seguridad #133)
    $extra = $tipo === 'extra' && extra_existe($clave) ? EXTRAS[$clave] : null;
    if ($tipo === 'extra' && !$extra) throw new LogicException('Extra desconocido: ' . $clave);
    // Marca de propiedad y enlace con el pedido pendiente (cadenas: LS las devuelve en meta.custom_data)
    $custom = ['token' => $token, 'slug' => $slug, 'producto' => PRODUCTO] + ($mejora ? ['tipo' => 'mejora'] : []) + ($extra ? ['tipo' => 'extra', 'clave' => $clave] : []);
    return ['data' => [
        'type' => 'checkouts',
        'attributes' => [
            'custom_price' => $precioCent,
            'product_options' => [
                'name' => ($extra ? $extra['nombre'] . ' — ' : ($mejora ? 'Mejora a Pack Atelier — ' : 'Web de boda — ')) . $slug . '.' . BASE_DOMAIN,
                'description' => $extra ? $extra['desc'] : ($mejora ? 'Diseños Atelier con su entrada animada, para vuestra web ya publicada.'
                    : 'Creación y alojamiento hasta ' . MESES_ALOJAMIENTO . ' meses después de la boda.'),
                'enabled_variants' => [(int) secreto('lemon_variante')],
                // La mejora y los extras vuelven al panel de la boda (sesión propia); el alta, a /listo del creador
                'redirect_url' => $extra ? url_boda($slug, ($extra['panel'] !== '' ? 'panel/' . $extra['panel'] : 'panel') . '?compra=1')
                    : ($mejora ? url_boda($slug, 'panel/editar?mejora=1') : url_creador('listo?t=' . $token)),
            ],
            'checkout_options' => ['discount' => false, 'quantity' => 1],
            'checkout_data' => [
                'email' => $email,
                'custom' => $custom,
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
function lemon_crea_checkout(string $token, string $slug, string $email, int $precioCent, string $tipo = 'alta', string $clave = ''): string {
    [$st, $d] = lemon_api('POST', '/v1/checkouts', lemon_cuerpo_checkout($token, $slug, $email, $precioCent, time(), $tipo, $clave));
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
 * ¿Lo cobrado cuadra con el precio congelado? La tienda vende con IVA INCLUIDO y `custom_price` = ese precio,
 * pero el desglose lo decide LS por país del comprador. Dos formas válidas, y en ambas el cliente nunca ha
 * pagado menos del precio: (a) `tax_inclusive` y total == precio; (b) sin `tax_inclusive`, subtotal == precio y
 * total == subtotal + IVA. Cualquier otra combinación (total mayor sin explicar, campos que faltan) NO cuadra.
 */
function lemon_importe_conforme(array $o, int $precioCent): bool {
    foreach (['total', 'subtotal', 'tax'] as $k) {
        if (isset($o[$k]) && !is_int($o[$k]) && !(is_string($o[$k]) && preg_match('/^-?\d{1,12}$/', $o[$k]))) return false;
    }
    if (!isset($o['total'])) return false;
    $total = (int) $o['total'];
    // `tax_inclusive` es un booleano documentado de la API (docs.lemonsqueezy.com, objeto Order, verificado 30-sep-2026). Si un día
    // faltara, no se cae en «todo no-conforme»: total == precio también es un pago que nunca baja del precio congelado
    if (!array_key_exists('tax_inclusive', $o) || filter_var($o['tax_inclusive'], FILTER_VALIDATE_BOOLEAN)) {
        if ($total === $precioCent) return true;
        if (array_key_exists('tax_inclusive', $o)) return false;
    }
    if (!isset($o['subtotal'], $o['tax'])) return false;
    $sub = (int) $o['subtotal'];
    return $sub === $precioCent && (int) $o['tax'] >= 0 && $total === $sub + (int) $o['tax'];
}
/** Importes del pedido para el aviso al estudio (sin datos personales): lo justo para diagnosticar un «total». */
function lemon_diag_importes(array $o): string {
    $v = fn(string $k): string => isset($o[$k]) && (is_int($o[$k]) || is_string($o[$k]) && preg_match('/^-?\d{1,12}$/', $o[$k])) ? (string) $o[$k] : '?';
    $inc = array_key_exists('tax_inclusive', $o) ? (filter_var($o['tax_inclusive'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false') : '?';
    return 'Importes (céntimos): subtotal=' . $v('subtotal') . ', tax=' . $v('tax') . ', total=' . $v('total') . ', tax_inclusive=' . $inc . '.';
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
    if (!isset($meta['precio_cent']) || !lemon_importe_conforme($o, (int) $meta['precio_cent'])) $m[] = 'total';
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
    if (($custom['tipo'] ?? '') === 'mejora') return [200, (string) (lemon_mejora($id, $o, $custom)['estado'] ?? '')];
    // Extra de pago (app/extras.php): el tipo solo elige la rama; qué extra, de qué boda y a qué precio lo dice extras/<token>.json
    if (($custom['tipo'] ?? '') === 'extra') return [200, (string) (lemon_extra($id, $o, $custom)['estado'] ?? '')];
    return [200, (string) (lemon_alta($id, $o, $custom)['estado'] ?? '')];
}

/** Registro local de un pedido LS (alta o mejora): importes del propio pedido, sin factura nuestra. */
function lemon_pedido_base(string $sid, string $id, array $o, string $slug, string $token, string $emailRespaldo, $aceptacion): array {
    $total = (int) ($o['total'] ?? 0);
    $iva = (int) ($o['tax'] ?? 0);
    $email = (string) ($o['user_email'] ?? '');
    return [
        'session_id' => $sid, 'pasarela' => 'lemon', 'slug' => $slug, 'token' => $token, 'creado' => date('c'),
        'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : $emailRespaldo,
        'nombre' => (string) ($o['user_name'] ?? ''),
        // Importes del propio pedido (IVA según el país del comprador; lo liquida LS, no nosotros)
        'importe' => ['base' => $total - $iva, 'iva' => $iva, 'total' => $total],
        'ls' => ['order_id' => $id, 'order_number' => (int) ($o['order_number'] ?? 0), 'identifier' => (string) ($o['identifier'] ?? ''),
            'moneda' => (string) ($o['currency'] ?? ''), 'iva_nombre' => (string) ($o['tax_name'] ?? ''), 'iva_pct' => (string) ($o['tax_rate'] ?? ''),
            'test' => (bool) ($o['test_mode'] ?? false),
            // Recibo de LS: el panel lleva a él aunque la web no tenga factura nuestra (Administración #133)
            'recibo' => str_starts_with((string) ($o['urls']['receipt'] ?? ''), 'https://') ? (string) $o['urls']['receipt'] : ''],
        'aceptacion' => $aceptacion,
        'factura' => '',   // Merchant of Record: la factura la emite LS (Administración #109)
        'estado' => 'cobrada',
    ];
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
        $ped = $ped ?: lemon_pedido_base($sid, $id, $o, $slug, $token, (string) ($cfg['pareja']['email'] ?? ''), $meta['aceptacion'] ?? null)
            + ['atelier' => isset(ATELIER[$cfg['atelier'] ?? '']) ? (string) $cfg['atelier'] : ''];

        // Llave 2: un token ya atado a OTRO pedido → este cobro sobra (se avisa para devolverlo)
        $previo = $fTok !== '' ? (string) ((lee_json($fTok) ?? [])['order_id'] ?? '') : '';
        if ($previo !== '' && $previo !== $id) {
            $ped['estado'] = 'duplicado';
            $ped['duplicado_de'] = 'ls_' . $previo;
            escribe_json($fPedido, $ped);
            avisa_estudio('Pago duplicado de una web de boda', "Pedido LS $id ({$slug}): el mismo pedido del creador ya se pagó con el pedido LS $previo. No se ha creado otra web. Devolver este cobro desde Lemon Squeezy.", 'Pedido LS ' . $id);
            return $ped;
        }
        // El token queda atado a este pedido sea cual sea el resultado: /listo lo lee y un segundo cobro es «duplicado»
        if ($fTok !== '' && $previo === '') escribe_json($fTok, ['order_id' => $id, 'creado' => date('c')]);
        if (!$meta) {
            // Sin pendiente no hay precio congelado con el que comparar: ni se valida ni se crea nada
            $ped['estado'] = 'sin-datos';
            escribe_json($fPedido, $ped);
            avisa_estudio('Pago de web de boda sin datos del creador', "Pedido LS $id ($slug). Cobrado; la web no se ha podido crear. Contactar con {$ped['email']}.", 'Pedido LS ' . $id);
            return $ped;
        }
        $motivos = lemon_motivos_no_conforme($o, $meta, $custom);
        if ($motivos) {
            $ped['estado'] = 'no-conforme';
            $ped['motivos'] = $motivos;
            escribe_json($fPedido, $ped);
            avisa_estudio('Pago de web de boda que no cuadra', "Pedido LS $id ($slug) cobrado pero no cuadra con lo vendido: " . implode(', ', $motivos) . ". " . lemon_diag_importes($o) . " No se ha creado la web. Revisar en Lemon Squeezy y devolver o publicar a mano.", 'Pedido LS ' . $id);
            return $ped;
        }
        $contar = ($ped['estado'] ?? '') === 'cobrada' && empty($ped['_analitica']);
        $ped['_analitica'] = 1;
        escribe_json($fPedido, $ped);
        if ($contar) analitica_evento('alta');   // una vez por pedido: la marca ya está escrita antes de contar
        return alta_publica($ped, $pend, $meta, $fPedido, $sid, $token, $slug);
    });
}

// ------------------------------------------------------------ mejora Esencial → Atelier (owner, 27-sep-2026)
// La pareja con Pack Esencial (pagado o regalado) paga desde su panel la diferencia al precio VIGENTE,
// calculada aquí y congelada en su propio pedido (mejoras/<token>.json) al abrir el checkout. Mismos
// guardas que el alta: importe del servidor, sin descuentos, relectura y validación del pedido de LS,
// idempotencia por order_id y por token, estados finales. Con Atelier pagado, el panel deja cambiar de
// diseño sin límite (panel_guardar mira pedido.json.atelier); volver al Esencial no devuelve nada.

/** Importe de la mejora: la diferencia entre los dos packs al precio vigente (lo fija El Padrino). */
function precio_mejora_cent(): int { return precio_atelier_cent() - precio_esencial_cent(); }

/** '' si esta boda puede pasar a Atelier; si no, el motivo (ya-atelier, archivada, sin-pedido). */
function mejora_bloqueo(string $slug): string {
    if (!slug_valido($slug) || !boda_existe($slug)) return 'sin-boda';
    $bp = lee_json(dir_boda($slug) . '/pedido.json') ?? [];
    if (!empty($bp['atelier'])) return 'ya-atelier';
    if (((lee_json(dir_boda($slug) . '/config.json') ?? [])['_estado'] ?? '') === 'archivada') return 'archivada';
    // Solo webs pagadas o regaladas que siguen en pie: un pedido reembolsado, duplicado o a medias no mejora
    $sid = (string) ($bp['session_id'] ?? '');
    $ped = preg_match('/^[A-Za-z0-9_]{3,120}$/', $sid) ? lee_json(dir_datos('pedidos', $sid . '.json')) : null;
    if (!$ped || ($ped['estado'] ?? '') !== 'creada') return 'sin-pedido';
    return '';
}

/** ¿Se puede ofrecer la mejora en este panel ahora? (la vista del constructor lo enseña o no) */
function mejora_disponible(string $slug): bool {
    return servicio_venta_abierta(texto_mejora()) && precio_mejora_cent() > 0 && mejora_bloqueo($slug) === '';
}
/** Casilla que acepta la pareja al pagar la mejora. SOLO la de Legal (`check_mejora`): la del alta dice
 *  «que cree y publique nuestra web», falso para una web ya publicada (revisor, 27-sep). Sin ella no se vende. */
function texto_mejora(): string { return (string) (textos_legales()['check_mejora'] ?? ''); }

/** POST /panel/mejora (sesión del panel ya exigida por rutas_panel; CSRF aquí). Compra por el camino común (app/servicios.php). */
function panel_mejora(string $slug, string $metodo): void {
    if ($metodo !== 'POST') json_response(['ok' => false], 405);
    if (!panel_csrf_ok()) json_response(['ok' => false, 'error' => 'La sesión ha caducado. Recarga la página.'], 403);
    servicio_compra(servicio_pago('mejora'), $slug);
}

/**
 * Correo de la mejora: el soporte duradero de ESTA compra (arts. 97.1 y 98.7 TRLGDCU; Legal, 27-sep). No se
 * apoya en la bienvenida: lleva su propio resumen (quién presta, quién vende, qué, cuánto, hasta cuándo,
 * desistimiento, garantía), la casilla aceptada LITERAL con fecha y versión, y las condiciones ÍNTEGRAS al
 * final. El enlace a /condiciones es solo una comodidad (enseña siempre la vigente). Si se quita algo de
 * esto, cae la excepción del art. 103.m y la mejora vuelve a ser desistible.
 */
function texto_mejora_correo(string $slug, array $ped, array $meta): string {
    return compra_texto(datos_mejora_correo($slug, $ped, $meta));
}

/** Datos del correo de la mejora, UNA vez (las alertas se registran una sola vez); los pintan compra_texto y compra_html. */
function datos_mejora_correo(string $slug, array $ped, array $meta): array {
    $L = textos_legales();
    $E = empresa_publica();
    $a = (array) ($meta['aceptacion'] ?? []);
    $fecha = ($a['fecha'] ?? '') !== '' ? date('d/m/Y H:i', strtotime((string) $a['fecha'])) : '';
    // El vendedor que se aceptó; si no quedó guardado, el de la fuente única de Legal (nunca escrito a mano)
    $vend = (string) ($a['vendedor'] ?? '') !== '' ? (string) $a['vendedor'] : (string) ($L['vendedor'] ?? '');
    $num = (int) ($ped['ls']['order_number'] ?? 0);
    $total = (int) ($ped['importe']['total'] ?? 0);
    $casilla = (string) ($a['desistimiento'] ?? '');
    $cfg = lee_json(dir_boda($slug) . '/config.json') ?? [];
    $borrado = fecha_larga(fecha_borrado((string) ($cfg['fecha'] ?? '')), false);
    // Las condiciones que van en el cuerpo son las vigentes al enviar: si la versión cambió desde la aceptación, se deja rastro
    if (($a['version'] ?? '') !== '' && $a['version'] !== ($L['version'] ?? '')) {
        registra('ALERTA correo de mejora con condiciones de otra versión', ['slug' => $slug, 'aceptada' => $a['version'], 'enviada' => $L['version'] ?? '']);
    }
    // Sin la casilla literal no queda probado el consentimiento del 103.m: se avisa, y no se escribe una línea vacía
    if ($casilla === '') {
        registra('ALERTA mejora sin casilla 103.m', ['slug' => $slug]);
        avisa_estudio('Mejora pagada sin la casilla de desistimiento guardada', "Mejora de $slug: la aceptación no guarda la casilla del art. 103.m. Revisar con Legal.", aviso_ref((string) ($ped['session_id'] ?? '')));
    }
    $donde = url_boda($slug, 'panel/editar');
    return ['titular' => '¡Hecho! Vuestra web ya tiene el Pack Atelier.',
        'donde_intro' => 'Elegid el diseño que queráis desde vuestro panel, y cambiadlo cuantas veces queráis:', 'donde' => $donde, 'que' => 'la mejora',
        'resumen' => [
            ['Servicio', correo_linea_servicio($E)],
            ['Qué', 'paso de Pack Esencial a Pack Atelier en ' . url_boda($slug) . '.'],
            ['Venta y cobro', "$vend, que es quien os la vende (vendedor final)" . ($num > 0 ? ", pedido n.º $num" : '') . ($total > 0 ? ', ' . euros($total) . ', IVA incluido' : '')
                . ". El recibo y la factura os los envía $vend en otro correo."],
            ['Duración', 'podéis usar y cambiar los diseños Atelier mientras la web esté alojada' . ($borrado !== '' ? ", hasta el $borrado" : '') . '. La mejora no alarga el alojamiento.'],
            ['Desistimiento', 'lo perdisteis al activarse la mejora, porque así lo pedisteis antes de pagar (casilla de abajo).'],
            ['Garantía', 'los diseños tienen que funcionar como se describen durante todo el alojamiento; si algo falla, lo arreglamos sin coste (apartado 9).'],
            ['Condiciones del servicio', 'van completas al final de este correo' . (($a['version'] ?? '') !== '' ? ' (versión ' . $a['version'] . ')' : '')
                . '. También están en ' . url_creador('condiciones') . ' (esa página enseña siempre la versión vigente; la vuestra es la de este correo).'],
            ['Dudas y reclamaciones', (string) $E['email']],
        ],
        'marcasteis' => 'Antes de pagar' . ($fecha !== '' ? " ($fecha, hora de España)" : '') . ' marcasteis lo siguiente'
            . (($a['version'] ?? '') !== '' ? ' (condiciones, versión ' . $a['version'] . ')' : '') . ':',
        'casilla' => $casilla,
        'condiciones' => documento_legal('condiciones', 'Condiciones del servicio'),
        'hero' => ['kicker' => 'Pack Atelier', 'titulo' => ['Los diseños ', 'Atelier', ', desbloqueados'],
            'texto' => 'Elegid el diseño que queráis desde vuestro panel, y cambiadlo cuantas veces queráis.',
            'boton' => ['Elegir diseño', $donde], 'enlace' => $donde]];
}

/** Mejora cobrada: valida contra su pedido congelado y marca la boda como Atelier (camino común, app/servicios.php). */
function lemon_mejora(string $id, array $o, array $custom): array { return lemon_servicio('mejora', $id, $o, $custom); }

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
        // Reembolso de un extra que llega antes de su activación: tipo, clave y boda se toman del pedido del SERVIDOR
        // (extras/<token>.json), nunca del custom_data; así no se cuenta como una web reembolsada (Administración #133)
        if (($ped['estado'] ?? '') === 'sin-alta' && empty($ped['tipo']) && preg_match('/^[a-f0-9]{32}$/', (string) ($custom['token'] ?? ''))) {
            $mx = lee_json(dir_datos('extras', $custom['token'] . '.json'));
            if ($mx && ($mx['tipo'] ?? '') === 'extra' && extra_existe((string) ($mx['clave'] ?? ''))) {
                $ped['tipo'] = 'extra';
                $ped['clave'] = (string) $mx['clave'];
                $ped['slug'] = (string) ($mx['slug'] ?? '');
            }
        }
        $ped['reembolso'] = ['fecha' => date('c'), 'total' => $total, 'importe_cent' => (int) ($o['refunded_amount'] ?? 0)];
        // Final siempre que sea total o que llegue antes que el alta (un 'sin-alta' dejaría publicar después)
        if ($total || $ped['estado'] === 'sin-alta') {
            $ped['estado_previo'] = (string) ($ped['estado'] ?? '');
            $ped['estado'] = 'reembolsado';
        }
        escribe_json($fPedido, $ped);
        $aplicado = ($ped['estado_previo'] ?? $ped['estado']) === 'creada';
        // Extra de pago: tipo y clave salen del registro que escribió la activación (desde extras/<token>.json),
        // nunca del custom_data del evento. Rama propia (Seguridad #133): el extra se desactiva y la web sigue.
        // Se desactiva con CUALQUIER reembolso, también parcial: en un extra el parcial normal es el desistimiento
        // proporcional (Legal #133), y dejarlo activo obligaría al owner a quitarlo a mano.
        if (($ped['tipo'] ?? '') === 'extra') {
            // Sin mirar $aplicado: extra_baja solo actúa si la boda tiene el extra activo POR ESTE pedido, y así cubre
            // también un corte a mitad de la activación (boda marcada, registro aún en 'cobrada')
            $baja = extra_baja((string) ($ped['slug'] ?? ''), (string) ($ped['clave'] ?? ''), $sid);
            $nombre = extra_existe((string) ($ped['clave'] ?? '')) ? EXTRAS[$ped['clave']]['nombre'] : 'extra';
            // Uno que hoy va incluido en los packs (plano de mesas) queda anotado como baja en pedido.json, pero la web lo
            // sigue teniendo: extra_activo() no mira la compra. El aviso no puede decir «desactivado»
            $txt = $baja && extra_incluido((string) $ped['clave']) ? " Era el extra «{$nombre}» de " . url_boda((string) $ped['slug'])
                    . ': ahora va incluido en todos los packs, así que la web lo sigue teniendo. La compra queda anotada como devuelta; no hay nada más que hacer.'
                : ($baja ? " Era el extra «{$nombre}» de " . url_boda((string) $ped['slug']) . ': se ha desactivado en su panel. La web sigue publicada.'
                    . ($total ? '' : ' Era un reembolso PARCIAL: si fue un gesto comercial y no un desistimiento, el extra habrá que volver a activarlo.')
                : ($aplicado ? " Era el extra «{$nombre}» de " . url_boda((string) $ped['slug']) . ': ya no estaba activo por este pedido; no se ha tocado nada.'
                    : ' Era un extra que no llegó a activarse: no hay nada que desactivar.'));
            avisa_estudio('Reembolso ' . ($total ? 'total' : 'parcial') . ' de un extra de boda', "Pedido LS $id ({$ped['slug']})." . $txt, 'Pedido LS ' . $id);
            return (string) $ped['estado'];
        }
        $web = !$aplicado ? '' : (($ped['tipo'] ?? '') === 'mejora'
            ? ' Era la mejora a Pack Atelier de ' . url_boda((string) $ped['slug']) . ': la boda conserva el Atelier; decide si quitarlo.'
            : ' La web ' . url_boda((string) $ped['slug']) . ' sigue publicada: decide si retirarla.');
        avisa_estudio('Reembolso ' . ($total ? 'total' : 'parcial') . ' de una web de boda', "Pedido LS $id ({$ped['slug']})." . $web, 'Pedido LS ' . $id);
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

/** Segundos que /listo se recarga sola esperando el aviso de LS; pasado ese plazo deja de insistir y da el buzón. */
const LEMON_ESPERA_RECARGA_S = 300;
const LEMON_RECARGA_CADA_S = 4;

/**
 * Pantalla de «confirmando el pago» (el pedido pendiente existe y el webhook aún no ha llegado). Se recarga sola cada
 * pocos segundos con <meta http-equiv="refresh"> (la CSP del creador no bloquea eso, y la página no ejecuta JS). No
 * para siempre: a los LEMON_ESPERA_RECARGA_S de la primera vez que se enseña, deja de recargar y da el buzón. El
 * instante lo guarda el servidor (pendientes/<token>/listo.json); nada de eso viene del navegador.
 */
function listo_espera_html(string $token): string {
    // El plazo cuenta desde la PRIMERA vez que se enseña esta pantalla (marca del servidor en el pendiente), no desde que se
    // abrió el pedido: quien tarda 4 min en rellenar la tarjeta no puede llegar aquí con el plazo ya gastado
    $marca = dir_datos('pendientes', $token, 'listo.json');
    $desde = (int) ((lee_json($marca) ?? [])['t'] ?? 0);
    if ($desde <= 0) { $desde = time(); escribe_json($marca, ['t' => $desde]); }
    if (time() - $desde < LEMON_ESPERA_RECARGA_S) {
        return pagina_simple('Confirmando el pago', '<p>Estamos confirmando el pago. Esta página se actualiza sola: en cuanto llegue, veréis vuestra web. También os llegará un email.</p>',
            true, '<meta http-equiv="refresh" content="' . LEMON_RECARGA_CADA_S . '">');
    }
    return pagina_simple('Confirmando el pago', '<p>Todavía no hemos recibido la confirmación del pago. Si se ha completado, suele tardar unos minutos: recargad esta página o esperad el email. '
        . 'Si tarda más, escribidnos a ' . h(empresa()['email']) . ' y lo resolvemos.</p>');
}

/** /listo?t=<token>: SOLO el pedido local (los ids de LS son secuenciales y no vienen del navegador). */
function listo_lemon(string $token): void {
    $idx = lee_json(dir_datos('ls_tokens', $token . '.json'));
    $oid = (string) ($idx['order_id'] ?? '');
    $ped = preg_match('/^\d{1,15}$/', $oid) ? lee_json(dir_datos('pedidos', 'ls_' . $oid . '.json')) : null;
    if (!$ped) {
        // Aún sin aviso de LS: si el pedido pendiente existe, se está confirmando; si no, no es nuestro
        if (is_dir(dir_datos('pendientes', $token))) {
            echo listo_espera_html($token);
        } else {
            echo pagina_simple('Pago no encontrado', '<p>No encontramos este pago. Si te han cobrado, escríbenos a ' . h(empresa()['email']) . '.</p>');
        }
        return;
    }
    if (($ped['estado'] ?? '') === 'creada') { listo_muestra($ped); return; }
    if (($ped['estado'] ?? '') === 'reembolsado') { echo pagina_simple('Pago devuelto', '<p>Este pago se ha devuelto. Para cualquier duda, escríbenos a ' . h(empresa()['email']) . '.</p>'); return; }
    echo pagina_simple('Pago recibido', '<p>Hemos recibido el pago, pero tenemos que revisarlo antes de publicar la web. Ya nos ha llegado el aviso y te escribimos en breve a ' . h((string) ($ped['email'] ?? '')) . '.</p>');
}
