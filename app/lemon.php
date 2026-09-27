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
function lemon_cuerpo_checkout(string $token, string $slug, string $email, int $precioCent, int $ahora, string $tipo = 'alta'): array {
    $mejora = $tipo === 'mejora';
    // Marca de propiedad y enlace con el pedido pendiente (cadenas: LS las devuelve en meta.custom_data)
    $custom = ['token' => $token, 'slug' => $slug, 'producto' => PRODUCTO] + ($mejora ? ['tipo' => 'mejora'] : []);
    return ['data' => [
        'type' => 'checkouts',
        'attributes' => [
            'custom_price' => $precioCent,
            'product_options' => [
                'name' => ($mejora ? 'Mejora a Pack Atelier — ' : 'Web de boda — ') . $slug . '.' . BASE_DOMAIN,
                'description' => $mejora ? 'Diseños Atelier con su entrada animada, para vuestra web ya publicada.'
                    : 'Creación y alojamiento hasta ' . MESES_ALOJAMIENTO . ' meses después de la boda.',
                'enabled_variants' => [(int) secreto('lemon_variante')],
                // La mejora vuelve al panel de la boda (sesión propia); el alta, a /listo del creador
                'redirect_url' => $mejora ? url_boda($slug, 'panel/editar?mejora=1') : url_creador('listo?t=' . $token),
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
function lemon_crea_checkout(string $token, string $slug, string $email, int $precioCent, string $tipo = 'alta'): string {
    [$st, $d] = lemon_api('POST', '/v1/checkouts', lemon_cuerpo_checkout($token, $slug, $email, $precioCent, time(), $tipo));
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
    if (($custom['tipo'] ?? '') === 'mejora') return [200, (string) (lemon_mejora($id, $o, $custom)['estado'] ?? '')];
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
            'test' => (bool) ($o['test_mode'] ?? false)],
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
            avisa_estudio('Pago de web de boda que no cuadra', "Pedido LS $id ($slug) cobrado pero no cuadra con lo vendido: " . implode(', ', $motivos) . ". No se ha creado la web. Revisar en Lemon Squeezy y devolver o publicar a mano.", 'Pedido LS ' . $id);
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
    return pasarela() === 'lemon' && lemon_configurada() && lemon_checkout_permitido() && empresa_completa()
        && texto_mejora() !== '' && precio_mejora_cent() > 0 && mejora_bloqueo($slug) === '';
}
/** Casilla que acepta la pareja al pagar la mejora. SOLO la de Legal (`check_mejora`): la del alta dice
 *  «que cree y publique nuestra web», falso para una web ya publicada (revisor, 27-sep). Sin ella no se vende. */
function texto_mejora(): string { return (string) (textos_legales()['check_mejora'] ?? ''); }

/** POST /panel/mejora (sesión del panel y CSRF ya exigidos por rutas_panel + aquí). Devuelve la URL de pago. */
function panel_mejora(string $slug, string $metodo): void {
    if ($metodo !== 'POST') json_response(['ok' => false], 405);
    if (!panel_csrf_ok()) json_response(['ok' => false, 'error' => 'La sesión ha caducado. Recarga la página.'], 403);
    if (!limite('mejora|' . $slug, 10, 3600, true)) json_response(['ok' => false, 'error' => 'Demasiados intentos. Prueba dentro de un rato.'], 429);
    // En modo test, el mismo candado que el alta: solo el estudio (sesión o IP) abre el pago
    if (pasarela() !== 'lemon' || !lemon_configurada() || !lemon_checkout_permitido() || !empresa_completa() || texto_mejora() === '') {
        json_response(['ok' => false, 'error' => 'La mejora todavía no está disponible. Escribidnos y os ayudamos.'], 503);
    }
    $L = textos_legales();
    if (($_POST['acepto_mejora'] ?? '') !== 'si') json_response(['ok' => false, 'error' => 'Marca la casilla para continuar.'], 422);
    $precio = precio_mejora_cent();
    if ($precio <= 0) { registra('ALERTA mejora con importe no positivo', ['precio' => $precio]); json_response(['ok' => false, 'error' => 'La mejora no está disponible ahora mismo.'], 503); }
    $tok = bin2hex(random_bytes(16));
    // Una sola mejora abierta por boda: la marca vive lo mismo que el checkout
    $motivo = con_cerrojo(function () use ($slug, $tok, $precio, $L) {
        $m = mejora_bloqueo($slug);
        if ($m !== '') return $m;
        $fa = dir_datos('mejoras', 'abierta_' . $slug . '.json');
        if ((lee_json($fa)['hasta'] ?? 0) > time()) return 'abierta';
        escribe_json(dir_datos('mejoras', $tok . '.json'), ['tipo' => 'mejora', 'slug' => $slug, 'creado' => time(), 'precio_cent' => $precio,
            'pasarela' => 'lemon', 'estado' => 'abierta', 'aceptacion' => ['fecha' => date('c'), 'version' => $L['version'] ?? '',
                // Lo que se enseñó y aceptó, literal, y quién vendía (Legal, BOD-20): el correo lo repite (art. 98.7)
                'desistimiento' => texto_mejora(), 'vendedor' => (string) ($L['vendedor'] ?? ''), 'precio_cent' => $precio]]);
        escribe_json($fa, ['token' => $tok, 'hasta' => time() + LEMON_CADUCIDAD_S + 60]);
        return '';
    });
    $err = ['ya-atelier' => 'Vuestra web ya tiene el Pack Atelier.', 'abierta' => 'Ya hay un pago de la mejora abierto. Terminadlo o esperad media hora.',
        'archivada' => 'Esta web ya está archivada.', 'sin-pedido' => 'Esta web no admite la mejora. Escribidnos y lo vemos.', 'sin-boda' => 'Esta web no existe.'];
    if ($motivo !== '') json_response(['ok' => false, 'error' => $err[$motivo] ?? 'No se puede ahora.'], 409);
    $bp = lee_json(dir_boda($slug) . '/pedido.json') ?? [];
    $email = (string) ($bp['email'] ?? '') ?: (string) ((lee_json(dir_boda($slug) . '/config.json') ?? [])['pareja']['email'] ?? '');
    $url = lemon_crea_checkout($tok, $slug, $email, $precio, 'mejora');
    if ($url === '') {
        con_cerrojo(function () use ($slug, $tok) {
            @unlink(dir_datos('mejoras', $tok . '.json'));
            if ((lee_json(dir_datos('mejoras', 'abierta_' . $slug . '.json'))['token'] ?? '') === $tok) @unlink(dir_datos('mejoras', 'abierta_' . $slug . '.json'));
        });
        json_response(['ok' => false, 'error' => 'No hemos podido abrir el pago. Inténtalo de nuevo.'], 502);
    }
    json_response(['ok' => true, 'url' => $url]);
}

/**
 * Correo de la mejora: soporte duradero de lo aceptado (art. 98.7 TRLGDCU). Repite LITERAL la casilla que se
 * enseñó y guardó al pagar (meta.aceptacion), con su fecha, el importe cobrado y quién vendía.
 */
function texto_mejora_correo(string $slug, array $ped, array $meta): string {
    $a = (array) ($meta['aceptacion'] ?? []);
    $fecha = ($a['fecha'] ?? '') !== '' ? date('d/m/Y H:i', strtotime((string) $a['fecha'])) : '';
    $vend = (string) ($a['vendedor'] ?? '') !== '' ? (string) $a['vendedor'] : 'Lemon Squeezy';
    return "¡Hecho! Vuestra web ya tiene el Pack Atelier.\n\n"
        . "Elegid el diseño que queráis desde vuestro panel, y cambiadlo cuantas veces queráis:\n" . url_boda($slug, 'panel/editar') . "\n\n"
        . 'Importe: ' . euros((int) ($ped['importe']['total'] ?? 0)) . ', IVA incluido. Lo cobra ' . $vend . ', que es quien os lo vende'
        . (($ped['ls']['order_number'] ?? 0) ? ' (pedido n.º ' . $ped['ls']['order_number'] . ')' : '') . ", y os ha enviado su recibo por email.\n\n"
        . 'Al pagar' . ($fecha !== '' ? " ($fecha)" : '') . " marcasteis lo siguiente:\n«" . (string) ($a['desistimiento'] ?? '') . "»\n\n"
        . "Cualquier duda: " . empresa()['email'] . "\n\n" . marca_comercial_correo();
}

/** Mejora cobrada: valida contra su pedido congelado y marca la boda como Atelier. Idempotente, bajo el cerrojo. */
function lemon_mejora(string $id, array $o, array $custom): array {
    $sid = 'ls_' . $id;
    $fPedido = dir_datos('pedidos', $sid . '.json');
    return con_cerrojo(function () use ($id, $o, $custom, $sid, $fPedido) {
        $ped = lee_json($fPedido);
        if ($ped && in_array($ped['estado'] ?? '', LEMON_FINALES, true)) return $ped;
        $token = (string) ($custom['token'] ?? '');
        $tokOk = (bool) preg_match('/^[a-f0-9]{32}$/', $token);
        $fTok = $tokOk ? dir_datos('ls_tokens', $token . '.json') : '';
        $fMeta = $tokOk ? dir_datos('mejoras', $token . '.json') : '';
        $meta = $fMeta !== '' ? lee_json($fMeta) : null;
        $slug = (string) ($meta['slug'] ?? $custom['slug'] ?? '');
        $ped = $ped ?: lemon_pedido_base($sid, $id, $o, $slug, $token, '', $meta['aceptacion'] ?? null) + ['tipo' => 'mejora', 'atelier' => ''];
        $aviso = function (string $estado, string $asunto, string $texto) use (&$ped, $fPedido, $slug, $token) {
            $fa = dir_datos('mejoras', 'abierta_' . $slug . '.json');
            if (slug_valido($slug) && (lee_json($fa)['token'] ?? '') === $token) @unlink($fa);
            $ped['estado'] = $estado;
            escribe_json($fPedido, $ped);
            avisa_estudio($asunto, $texto, 'Pedido LS ' . $ped['ls']['order_id']);
            return $ped;
        };
        $previo = $fTok !== '' ? (string) ((lee_json($fTok) ?? [])['order_id'] ?? '') : '';
        if ($previo !== '' && $previo !== $id) {
            $ped['duplicado_de'] = 'ls_' . $previo;
            return $aviso('duplicado', 'Pago duplicado de una mejora a Atelier', "Pedido LS $id ($slug): esa mejora ya se pagó con el pedido LS $previo. No se ha cambiado nada. Devolver este cobro desde Lemon Squeezy.");
        }
        if ($fTok !== '' && $previo === '') escribe_json($fTok, ['order_id' => $id, 'creado' => date('c'), 'tipo' => 'mejora']);
        if (!$meta || ($meta['tipo'] ?? '') !== 'mejora') {
            return $aviso('sin-datos', 'Pago de mejora sin pedido', "Pedido LS $id ($slug): cobrada una mejora a Atelier sin su pedido en el servidor. No se ha cambiado nada. Revisar.");
        }
        $motivos = lemon_motivos_no_conforme($o, $meta, $custom);
        if ($motivos) {
            $ped['motivos'] = $motivos;
            return $aviso('no-conforme', 'Pago de mejora que no cuadra', "Pedido LS $id ($slug) cobrado pero no cuadra con la mejora: " . implode(', ', $motivos) . ". No se ha cambiado nada. Revisar en Lemon Squeezy.");
        }
        // Reintento tras un corte: la boda ya quedó marcada por ESTE pedido → se remata el cierre (no es un duplicado)
        $bp = lee_json(dir_boda($slug) . '/pedido.json') ?? [];
        $yaAplicada = ($bp['mejora']['session_id'] ?? '') === $sid;
        $bloqueo = $yaAplicada ? '' : mejora_bloqueo($slug);
        if ($bloqueo === 'ya-atelier') {
            return $aviso('duplicado', 'Mejora pagada en una web que ya era Atelier', "Pedido LS $id ($slug): la web ya tenía el Pack Atelier. Devolver este cobro desde Lemon Squeezy.");
        }
        if ($bloqueo !== '') {
            $ped['motivos'] = [$bloqueo];
            return $aviso('no-conforme', 'Mejora pagada en una web que no la admite', "Pedido LS $id ($slug): $bloqueo. No se ha cambiado nada. Revisar.");
        }
        // Aplicar: la boda puede usar cualquier diseño Atelier desde el panel, sin límite de cambios
        if (!$yaAplicada) {
            $bp['atelier'] = true;
            $bp['mejora'] = ['session_id' => $sid, 'fecha' => date('c'), 'importe_cent' => (int) ($o['total'] ?? 0)];
            escribe_json(dir_boda($slug) . '/pedido.json', $bp);
        }
        $meta['estado'] = 'pagada';
        $meta['session_id'] = $sid;
        escribe_json($fMeta, $meta);
        $fa = dir_datos('mejoras', 'abierta_' . $slug . '.json');
        if ((lee_json($fa)['token'] ?? '') === $token) @unlink($fa);
        if ($ped['email'] === '') $ped['email'] = (string) ($bp['email'] ?? '');
        envia_o_encola(['tipo' => 'correo', 'para' => $ped['email'], 'asunto' => 'Ya tenéis el Pack Atelier', 'texto' => texto_mejora_correo($slug, $ped, $meta)]);
        avisa_estudio('Mejora a Atelier' . (!empty($ped['ls']['test']) ? ' (prueba)' : ''),
            'Web: ' . url_boda($slug) . "\nImporte: " . euros((int) ($ped['importe']['total'] ?? 0)) . ' (IVA ' . euros((int) ($ped['importe']['iva'] ?? 0)) . ")\n"
            . 'Pago: Lemon Squeezy #' . ($ped['ls']['order_number'] ?? '') . (!empty($ped['ls']['test']) ? ' (PRUEBA, modo test)' : '') . "\nComprador: " . $ped['email'], 'Pedido LS ' . $id);
        $ped['estado'] = 'creada';
        escribe_json($fPedido, $ped);
        registra('mejora a Atelier aplicada', ['slug' => $slug, 'sid' => $sid]);
        return $ped;
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
        $aplicado = ($ped['estado_previo'] ?? $ped['estado']) === 'creada';
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
    if (($ped['estado'] ?? '') === 'reembolsado') { echo pagina_simple('Pago devuelto', '<p>Este pago se ha devuelto. Para cualquier duda, escríbenos a ' . h(empresa()['email']) . '.</p>'); return; }
    echo pagina_simple('Pago recibido', '<p>Hemos recibido el pago, pero tenemos que revisarlo antes de publicar la web. Ya nos ha llegado el aviso y te escribimos en breve a ' . h((string) ($ped['email'] ?? '')) . '.</p>');
}
