<?php
// Rutas del creador (CREATOR_HOST): la página del constructor, la vista previa, la
// comprobación del nombre, el pago, el webhook de Stripe y la página de éxito.

declare(strict_types=1);

const CSP_CREADOR = "default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; font-src 'self'; "
    . "script-src 'self'; connect-src 'self'; frame-src 'self'; "
    . "form-action 'self' https://checkout.stripe.com; frame-ancestors 'none'; base-uri 'self'; object-src 'none'";

function rutas_creador(string $ruta, string $metodo): void {
    switch ($ruta) {
        case '':
            header('Content-Security-Policy: ' . CSP_CREADOR);
            echo pagina_landing();
            return;
        case 'crear':
            header('Content-Security-Policy: ' . CSP_CREADOR);
            echo vista_constructor('crear', config_inicial(), '');
            return;
        case 'condiciones': case 'privacidad': case 'aviso-legal':
            header('Content-Security-Policy: ' . CSP_CREADOR);
            $t = ['condiciones' => 'Condiciones de contratación', 'privacidad' => 'Privacidad', 'aviso-legal' => 'Aviso legal'][$ruta];
            $E = empresa();
            ob_start();
            include APP_DIR . '/legal/' . $ruta . '.php';
            echo pagina_simple($t, '<article class="legal">' . ob_get_clean() . '</article>');
            return;
        case 'api/vista-previa':
            api_vista_previa($metodo, url_creador('assets/'));
            return;
        case 'api/nombre':
            api_nombre();
            return;
        case 'api/pagar':
            api_pagar($metodo);
            return;
        case 'api/stripe':
            api_webhook($metodo);
            return;
        case 'listo':
            header('Content-Security-Policy: ' . CSP_CREADOR);
            pagina_listo();
            return;
    }
    no_existe();
}

/** Vista previa: se renderiza con el MISMO generador, no se guarda nada, y solo por POST
 *  (un POST no se puede enlazar: nadie puede montar con esto una página falsa en nuestro dominio). */
function api_vista_previa(string $metodo, string $assets, string $firmaSlug = ''): void {
    if ($metodo !== 'POST') json_response(['ok' => false], 405);
    if (!limite('vp|' . ip_cliente(), 1500, 3600)) json_response(['ok' => false, 'error' => 'Demasiadas peticiones.'], 429);
    $raw = file_get_contents('php://input', false, null, 0, 200000);
    $in = json_decode((string) $raw, true);
    if (!is_array($in)) json_response(['ok' => false], 400);
    $c = normaliza_config($in['config'] ?? []);
    $pagina = clean_str($in['pagina'] ?? '', 40);
    $ctx = ['modo' => 'preview', 'assets' => $assets, 'foto' => '', 'firma_slug' => $firmaSlug, 'intro' => !empty($in['intro']),
        'libro' => $firmaSlug !== '' ? libro_entradas($firmaSlug) : []];
    $html = render_pagina($c, $pagina === 'inicio' ? '' : $pagina, $ctx);
    if ($html === null) $html = render_pagina($c, '', $ctx);
    $paginas = [['inicio', 'Inicio']];
    // [ruta, título, id de la sección]: el creador sincroniza vista previa y configurador por el id
    foreach ($c['secciones'] as $s) if ($s['on']) $paginas[] = [$s['ruta'], $s['titulo'], $s['id']];
    $paginas[] = ['privacidad', 'Privacidad'];
    json_response(['ok' => true, 'html' => $html, 'paginas' => $paginas, 'faltan' => faltan($c)]);
}

function api_nombre(): void {
    if (!limite('nombre|' . ip_cliente(), 300, 3600)) json_response(['ok' => false, 'error' => 'Demasiadas peticiones.'], 429);
    $s = strtolower(clean_str($_GET['s'] ?? '', 60));
    if (!preg_match(SLUG_RE, $s) || strpos($s, '--') !== false) {
        json_response(['ok' => true, 'libre' => false, 'motivo' => 'Entre 3 y 40 caracteres: letras sin tilde, números y guiones.']);
    }
    if (in_array($s, SLUGS_RESERVADOS, true)) json_response(['ok' => true, 'libre' => false, 'motivo' => 'Ese nombre está reservado.']);
    json_response(['ok' => true, 'libre' => slug_libre($s), 'motivo' => slug_libre($s) ? '' : 'Ese nombre ya está cogido.']);
}

function api_pagar(string $metodo): void {
    if ($metodo !== 'POST') json_response(['ok' => false], 405);
    if (!limite('pagar|' . ip_cliente(), 20, 3600)) json_response(['ok' => false, 'error' => 'Demasiados intentos. Prueba dentro de un rato.'], 429);
    // En LIVE no se vende sin los datos del titular en los textos legales y la factura
    // Sin el Tax Rate, Stripe cobraría 100 € con un botón que dice 121 €: no se abre el pago.
    if (secreto('stripe_tax_rate') === '') {
        registra('ALERTA pago bloqueado: falta stripe_tax_rate');
        json_response(['ok' => false, 'error' => 'La venta está en pausa un momento. Vuelve a intentarlo más tarde.'], 503);
    }
    if (stripe_modo_live() && !empresa_completa()) {
        registra('ALERTA pago bloqueado: faltan datos del titular');
        json_response(['ok' => false, 'error' => 'La venta está en pausa un momento. Vuelve a intentarlo más tarde.'], 503);
    }
    if (($_POST['acepto_condiciones'] ?? '') !== 'si' || ($_POST['acepto_desistimiento'] ?? '') !== 'si') {
        json_response(['ok' => false, 'error' => 'Marca las dos casillas para continuar.'], 422);
    }
    $c = normaliza_config(json_decode((string) ($_POST['config'] ?? ''), true));
    $f = faltan($c);
    if ($f) json_response(['ok' => false, 'error' => 'Faltan datos.', 'faltan' => $f], 422);
    $slug = strtolower(clean_str($_POST['slug'] ?? '', 60));
    if (!slug_valido($slug)) json_response(['ok' => false, 'error' => 'El nombre de la web no es válido.'], 422);

    asegura_dir(dir_datos('pendientes'));
    if (count(glob(dir_datos('pendientes', '*'), GLOB_ONLYDIR) ?: []) > 500) {
        registra('ALERTA demasiados pedidos pendientes');
        json_response(['ok' => false, 'error' => 'Ahora mismo no podemos atenderte. Prueba en unos minutos.'], 503);
    }
    $token = bin2hex(random_bytes(16));
    $pend = dir_datos('pendientes', $token);

    // Reserva del nombre dentro del cerrojo: dos parejas no pueden pagar el mismo a la vez
    $reservado = con_cerrojo(function () use ($slug, $token) {
        if (!slug_libre($slug)) return false;
        escribe_json(dir_datos('reservas', $slug . '.json'), ['token' => $token, 'hasta' => time() + 1860]);
        return true;
    });
    if (!$reservado) json_response(['ok' => false, 'error' => 'Ese nombre de web ya está cogido. Elige otro.', 'faltan' => ['slug' => 'Ese nombre ya está cogido.']], 409);

    asegura_dir($pend);
    $fr = guarda_foto('foto', $pend . '/foto.webp');
    if ($fr !== '' && $fr !== 'sin-foto') { borra_arbol($pend); @unlink(dir_datos('reservas', $slug . '.json')); json_response(['ok' => false, 'error' => $fr], 422); }
    $c['foto'] = $fr === '';
    escribe_json($pend . '/config.json', $c);
    $L = textos_legales();
    escribe_json($pend . '/meta.json', ['slug' => $slug, 'creado' => time(), 'aceptacion' => [
        'fecha' => date('c'), 'version' => $L['version'] ?? '', 'condiciones' => $L['check_condiciones'] ?? '', 'desistimiento' => $L['check_desistimiento'] ?? '',
    ]]);

    [$st, $s] = stripe_crea_checkout($token, $slug, $c['pareja']['email'], $c['atelier']);
    if ($st !== 200 || empty($s['url'])) {
        registra('stripe: no se pudo crear la sesión', ['status' => $st, 'error' => $s['error']['message'] ?? '']);
        borra_arbol($pend);
        @unlink(dir_datos('reservas', $slug . '.json'));
        json_response(['ok' => false, 'error' => 'No hemos podido abrir el pago. Inténtalo de nuevo.'], 502);
    }
    json_response(['ok' => true, 'url' => $s['url']]);
}

function api_webhook(string $metodo): void {
    if ($metodo !== 'POST') json_response(['ok' => false], 405);
    $payload = (string) file_get_contents('php://input', false, null, 0, 1000000);
    $ev = stripe_verifica_webhook($payload, (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''));
    if (!$ev) json_response(['ok' => false], 400);
    $tipo = (string) ($ev['type'] ?? '');
    $obj = $ev['data']['object'] ?? [];
    // Eventos de otros proyectos de la cuenta compartida: 200 y fuera, sin tocar nada
    if (($obj['metadata']['producto'] ?? '') !== PRODUCTO) json_response(['ok' => true, 'ignorado' => true]);
    if (in_array($tipo, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
        // Se vuelve a pedir la sesión a Stripe: la verdad es la API, no el cuerpo recibido
        $s = stripe_lee_sesion((string) ($obj['id'] ?? ''));
        if ($s && sesion_pagada_nuestra($s)) alta_desde_sesion($s);
    } elseif ($tipo === 'checkout.session.expired') {
        $tok = (string) ($obj['client_reference_id'] ?? '');
        if (preg_match('/^[a-f0-9]{32}$/', $tok)) {
            borra_arbol(dir_datos('pendientes', $tok));
            $slug = (string) ($obj['metadata']['slug'] ?? '');
            $r = slug_valido($slug) ? lee_json(dir_datos('reservas', $slug . '.json')) : null;
            if ($r && ($r['token'] ?? '') === $tok) @unlink(dir_datos('reservas', $slug . '.json'));
        }
    }
    json_response(['ok' => true]);
}

function pagina_listo(): void {
    cabeceras_privadas();
    // Cada visita consulta la API de Stripe: sin límite sería un amplificador gratis
    if (!limite('listo|' . ip_cliente(), 30, 3600)) { http_response_code(429); echo pagina_simple('Demasiadas visitas', '<p>Espera un rato y vuelve a cargar la página.</p>'); return; }
    $sid = clean_str($_GET['sid'] ?? '', 250);
    $s = stripe_lee_sesion($sid);
    if (!$s || ($s['metadata']['producto'] ?? '') !== PRODUCTO) { echo pagina_simple('Pago no encontrado', '<p>No encontramos este pago. Si te han cobrado, escríbenos a ' . h(empresa()['email']) . '.</p>'); return; }
    if (!sesion_pagada_nuestra($s)) { echo pagina_simple('Pago pendiente', '<p>Stripe todavía no ha confirmado el pago. Recarga esta página en un minuto; te llegará también un email.</p>'); return; }
    $ped = alta_desde_sesion($s);
    if (!$ped || ($ped['estado'] ?? '') !== 'creada') {
        echo pagina_simple('Pago recibido', '<p>Hemos recibido el pago, pero no hemos podido crear la web automáticamente. Ya nos ha llegado el aviso y te escribimos en breve a ' . h($ped['email'] ?? '') . '.</p>');
        return;
    }
    $slug = $ped['slug'];
    $url = url_boda($slug);
    $enlace = '';
    // El enlace para elegir contraseña se enseña UNA vez aquí (y siempre va por email).
    // Una URL /listo reenviada o del historial ya no lo muestra.
    $mostrar = con_cerrojo(function () use ($ped) {
        $f = dir_datos('pedidos', $ped['session_id'] . '.json');
        $p = lee_json($f) ?? [];
        if (!empty($p['enlace_mostrado'])) return false;
        $p['enlace_mostrado'] = date('c');
        escribe_json($f, $p);
        return true;
    });
    if ($mostrar && !panel_tiene_clave($slug)) $enlace = panel_nuevo_enlace($slug);
    $o = '<p class="lede">Vuestra web ya está publicada en:</p><p><a class="btn" href="' . h($url) . '" target="_blank" rel="noopener">' . h(preg_replace('~^https?://~', '', rtrim($url, '/'))) . '</a></p>';
    if ($enlace !== '') {
        $o .= '<p>Ahora elegid la contraseña de vuestro panel, desde donde veréis las respuestas, editaréis la web y descargaréis el ZIP:</p><p><a class="btn btn-sec" href="' . h($enlace) . '">Elegir contraseña</a></p>';
    }
    $o .= '<p class="nota">Os hemos enviado un email a ' . h($ped['email']) . ' con el enlace del panel, la factura (' . h($ped['factura']) . ') y las condiciones.</p>';
    echo pagina_simple('¡Enhorabuena!', $o);
}

/** Página sencilla del creador (legales, éxito). */
function pagina_simple(string $titulo, string $cuerpo): string {
    return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . h($titulo) . ' — ' . h(MARCA) . '</title><meta name="robots" content="noindex">'
        . '<link rel="stylesheet" href="/assets/marca.css?v=' . h(ASSETS_V) . '"><link rel="stylesheet" href="/assets/crear.css?v=' . h(ASSETS_V) . '"></head><body class="simple">'
        . '<header class="s-top"><a class="c-marca" href="/">' . il('flor') . '<span>' . h(MARCA) . '</span></a></header>'
        . '<main class="simple-main"><h1>' . h($titulo) . '</h1>' . $cuerpo . '</main>' . pie_creador() . '</body></html>';
}

function pie_creador(): string {
    return '<footer class="c-pie"><a href="/condiciones">Condiciones</a><a href="/privacidad">Privacidad</a><a href="/aviso-legal">Aviso legal</a><span>' . h(empresa()['email']) . '</span></footer>';
}
