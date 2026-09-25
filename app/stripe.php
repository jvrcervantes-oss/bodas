<?php
// Stripe sin SDK (mismo patrón que B2K/checkout.php: API REST por curl).
// La firma del webhook se verifica con el algoritmo documentado que implementa
// constructEvent del SDK: HMAC-SHA256 de "t.payload", comparación en tiempo
// constante contra cada v1 y tolerancia de 300 s (nunca 0).

declare(strict_types=1);

const STRIPE_VERSION = '2024-06-20';
const STRIPE_TOLERANCIA = 300;

/** Llamada a la API. Devuelve [status, cuerpo decodificado]. Nunca lanza. */
function stripe_api(string $metodo, string $ruta, array $params = []): array {
    $key = (string) secreto('stripe_secret');
    if ($key === '') return [0, ['error' => ['message' => 'Stripe sin configurar']]];
    $url = rtrim(STRIPE_API, '/') . $ruta;
    $ch = curl_init();
    $q = http_build_query($params);
    if ($metodo === 'GET' && $q !== '') $url .= '?' . $q;
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_USERPWD => $key . ':',
        CURLOPT_HTTPHEADER => ['Stripe-Version: ' . STRIPE_VERSION],
    ]);
    if ($metodo === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $q);
    }
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($raw === false) {
        registra('stripe: fallo de red', ['ruta' => $ruta, 'error' => $err]);
        return [0, ['error' => ['message' => 'Sin conexión con Stripe']]];
    }
    $d = json_decode((string) $raw, true);
    return [$status, is_array($d) ? $d : []];
}

/** Devuelve el evento decodificado si la firma es buena, o null. */
function stripe_verifica_webhook(string $payload, string $cabecera, ?int $ahora = null): ?array {
    $secret = (string) secreto('stripe_webhook_secret');
    if ($secret === '' || $cabecera === '') return null;
    $t = null;
    $firmas = [];
    foreach (explode(',', $cabecera) as $parte) {
        [$k, $v] = array_pad(explode('=', trim($parte), 2), 2, '');
        if ($k === 't' && ctype_digit($v)) $t = (int) $v;
        if ($k === 'v1' && $v !== '') $firmas[] = $v;
    }
    if ($t === null || !$firmas) return null;
    if (abs(($ahora ?? time()) - $t) > STRIPE_TOLERANCIA) return null;
    $esperada = hash_hmac('sha256', $t . '.' . $payload, $secret);
    foreach ($firmas as $f) {
        if (hash_equals($esperada, $f)) {
            $ev = json_decode($payload, true);
            return is_array($ev) ? $ev : null;
        }
    }
    return null;
}

/**
 * Crea la Checkout Session. El importe sale de las constantes del servidor; del
 * navegador solo llega el token del pedido pendiente.
 */
function stripe_crea_checkout(string $token, string $slug, string $email, string $atelier = ''): array {
    $taxRate = (string) secreto('stripe_tax_rate'); // Tax Rate 21 % INCLUSIVE creado en el dashboard
    $p = [
        'mode' => 'payment',
        'locale' => 'es',
        'customer_email' => $email,
        'billing_address_collection' => 'required',   // prueba de residencia para el IVA (Administración, 25-sep)
        'client_reference_id' => $token,
        'expires_at' => time() + 1800,                 // la reserva del nombre caduca con la sesión
        'success_url' => url_creador('listo?sid={CHECKOUT_SESSION_ID}'),
        'cancel_url' => url_creador('crear?cancelado=1'),
        'line_items[0][quantity]' => 1,
        'line_items[0][price_data][currency]' => 'eur',
        // IVA incluido: el Tax Rate de secrets.php tiene que ser INCLUSIVE (BOD-3)
        'line_items[0][price_data][unit_amount]' => precio_esencial_cent(),
        'line_items[0][price_data][tax_behavior]' => 'inclusive',
        'line_items[0][price_data][product_data][name]' => 'Web de boda — ' . $slug . '.' . BASE_DOMAIN,
        'line_items[0][price_data][product_data][description]' => 'Creación y alojamiento hasta ' . MESES_ALOJAMIENTO . ' meses después de la boda',
        'payment_intent_data[description]' => 'Web de boda ' . $slug,
        'payment_intent_data[metadata][producto]' => PRODUCTO,
        'payment_intent_data[metadata][slug]' => $slug,
        'payment_method_options[card][request_three_d_secure]' => 'any',
        // Marca de propiedad: la cuenta de Stripe es compartida entre proyectos del
        // estudio y un webhook recibe los eventos de TODA la cuenta (incidente B2K→Sumba, 1-sep-2026).
        'metadata[producto]' => PRODUCTO,
        'metadata[slug]' => $slug,
        'metadata[bot]' => PRODUCTO,
    ];
    if ($taxRate !== '') $p['line_items[0][tax_rates][0]'] = $taxRate;
    if ($atelier !== '' && isset(ATELIER[$atelier])) {
        $p += [
            'line_items[1][quantity]' => 1,
            'line_items[1][price_data][currency]' => 'eur',
            'line_items[1][price_data][unit_amount]' => precio_atelier_cent() - precio_esencial_cent(),
            'line_items[1][price_data][tax_behavior]' => 'inclusive',
            'line_items[1][price_data][product_data][name]' => 'Diseño Atelier «' . ATELIER[$atelier]['nombre'] . '»',
            'metadata[atelier]' => $atelier,
        ];
        if ($taxRate !== '') $p['line_items[1][tax_rates][0]'] = $taxRate;
    }
    return stripe_api('POST', '/v1/checkout/sessions', $p);
}

/** Recupera una sesión con el cargo expandido (país de la tarjeta para el IVA). */
function stripe_lee_sesion(string $sid): ?array {
    if (!preg_match('/^cs_[A-Za-z0-9_]{10,200}$/', $sid)) return null;
    [$st, $s] = stripe_api('GET', '/v1/checkout/sessions/' . $sid, ['expand' => ['payment_intent.latest_charge']]);
    if ($st !== 200 || ($s['object'] ?? '') !== 'checkout.session') return null;
    return $s;
}

/** ¿Esta sesión es nuestra y está cobrada? */
function sesion_pagada_nuestra(array $s): bool {
    return ($s['metadata']['producto'] ?? '') === PRODUCTO
        && ($s['payment_status'] ?? '') === 'paid'
        && ($s['currency'] ?? '') === 'eur';
}
