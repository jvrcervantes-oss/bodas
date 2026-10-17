<?php
// Alta de una boda tras el pago. La llaman el webhook de Stripe y la página de
// éxito (por si el webhook tarda): las dos pasan por aquí y el resultado es el mismo
// aunque lleguen diez veces — pedidos/<session_id>.json es la marca de "ya hecho".
// La factura se emite aunque la web falle: con cobro anticipado el IVA se devenga
// al cobrar (Administración, revisión previa #81).

declare(strict_types=1);

require_once __DIR__ . '/stripe.php';
require_once __DIR__ . '/lemon.php';
require_once __DIR__ . '/factura.php';
require_once __DIR__ . '/correo.php';
require_once __DIR__ . '/panel_auth.php';

function dir_boda(string $slug): string { return dir_datos('bodas', $slug); }
function config_boda(string $slug): ?array {
    $c = lee_json(dir_boda($slug) . '/config.json');
    return $c ? normaliza_config($c) + ['_estado' => $c['_estado'] ?? 'activa'] : null;
}
function boda_existe(string $slug): bool { return is_file(dir_boda($slug) . '/config.json'); }

/** ¿Se puede vender este nombre ahora? Reservado por otra sesión viva = no. */
function slug_libre(string $slug, string $token = ''): bool {
    if (!slug_valido($slug) || boda_existe($slug)) return false;
    $r = lee_json(dir_datos('reservas', $slug . '.json'));
    return !$r || ($r['hasta'] ?? 0) < time() || ($token !== '' && ($r['token'] ?? '') === $token);
}

/**
 * Identificador NO reversible de quien reserva un nombre: HMAC con la clave de la app de IP + email normalizado.
 * Ni la IP ni el email se guardan en claro. Sirve solo para reconocer que quien vuelve del checkout con el
 * botón «atrás» es la misma persona (mismo email desde la misma conexión); no es una credencial.
 */
function reservante_id(string $ip, string $email): string {
    $email = strtolower(trim($email));
    if ($email === '') return '';
    return hash_hmac('sha256', 'reservante|' . $ip . '|' . $email, clave_app());
}

/**
 * Dentro del cerrojo: ¿puede $reservante sustituir la reserva vigente de $slug? Solo si la hizo él, la web no
 * existe, y su pedido anterior sigue sin cobrar (ni token atado a un pedido de LS ni web de regalo). En ese
 * caso la reserva pasa al pedido nuevo (el pendiente anterior se deja caducar; ver abajo).
 */
function reserva_reemplazable(string $slug, string $reservante): bool {
    if ($reservante === '' || !slug_valido($slug) || boda_existe($slug)) return false;
    $r = lee_json(dir_datos('reservas', $slug . '.json'));
    if (!$r || ($r['hasta'] ?? 0) < time()) return false;
    $prev = (string) ($r['reservante'] ?? '');
    $viejo = (string) ($r['token'] ?? '');
    if ($prev === '' || !hash_equals($prev, $reservante) || !preg_match('/^[a-f0-9]{32}$/', $viejo)) return false;
    // Ya cobrado o en proceso de cobro: nunca se pisa
    if (is_file(dir_datos('ls_tokens', $viejo . '.json')) || is_file(dir_datos('pedidos', 'cortesia_' . $viejo . '.json'))) return false;
    $meta = lee_json(dir_datos('pendientes', $viejo, 'meta.json'));
    if ($meta && (($meta['estado'] ?? '') === 'pagada' || ($meta['slug'] ?? $slug) !== $slug)) return false;
    // El pendiente anterior NO se borra: su checkout sigue pudiendo pagarse hasta 30 min y, si el aviso de LS llega tarde, tiene
    // que encontrar su meta.json (sin él sería «sin-datos»: cobrado y sin web). Lo caduca el cron. Lo que cambia es la reserva:
    // pasa al pedido nuevo. Si pagaran los dos, el segundo se publica como «-2» (dos cobros, dos webs, nada cobrado sin web).
    return true;
}

/** Contexto de fecha para faltan() al editar una boda ya contratada desde el panel. */
function faltan_panel(string $slug): array {
    $c = lee_json(dir_boda($slug) . '/config.json') ?? [];
    $p = lee_json(dir_boda($slug) . '/pedido.json') ?? [];
    return ['guardada' => (string) ($c['fecha'] ?? ''), 'pago' => (string) ($p['fecha_pago'] ?? '')];
}

/** Reserva un nombre 30 min para $token, bajo el cerrojo: libre, o reemplazando la reserva sin cobrar del mismo reservante. */
function reserva_toma(string $slug, string $token, string $reservante): bool {
    return con_cerrojo(function () use ($slug, $token, $reservante) {
        if (!slug_libre($slug) && !reserva_reemplazable($slug, $reservante)) return false;
        escribe_json(dir_datos('reservas', $slug . '.json'), ['token' => $token, 'hasta' => time() + 1860, 'reservante' => $reservante]);
        return true;
    });
}

/** Bloqueo global corto para las altas: dos entregas simultáneas del webhook no crean dos webs. */
function con_cerrojo(callable $fn) {
    asegura_dir(dir_datos('locks'));
    $fp = fopen(dir_datos('locks', 'alta.lock'), 'c');
    flock($fp, LOCK_EX);
    try { return $fn(); } finally { flock($fp, LOCK_UN); fclose($fp); }
}

/**
 * Da de alta la boda de una sesión cobrada. Devuelve el pedido (array) o null si la
 * sesión no es nuestra o no está pagada.
 */
function alta_desde_sesion(array $s): ?array {
    if (!sesion_pagada_nuestra($s)) return null;
    $sid = (string) $s['id'];
    $fPedido = dir_datos('pedidos', $sid . '.json');
    return con_cerrojo(function () use ($s, $sid, $fPedido) {
        $ped = lee_json($fPedido);
        if ($ped && ($ped['estado'] ?? '') === 'creada') return $ped;

        $token = (string) ($s['client_reference_id'] ?? '');
        $pend = preg_match('/^[a-f0-9]{32}$/', $token) ? dir_datos('pendientes', $token) : '';
        $meta = $pend !== '' ? lee_json($pend . '/meta.json') : null;
        $slug = (string) ($s['metadata']['slug'] ?? '');
        $ped = $ped ?: [
            'session_id' => $sid, 'slug' => $slug, 'token' => $token, 'creado' => date('c'),
            'email' => (string) ($s['customer_details']['email'] ?? $s['customer_email'] ?? ''),
            'nombre' => (string) ($s['customer_details']['name'] ?? ''),
            'pais_facturacion' => (string) ($s['customer_details']['address']['country'] ?? ''),
            'pais_tarjeta' => (string) ($s['payment_intent']['latest_charge']['payment_method_details']['card']['country'] ?? ''),
            // IVA incluido: el subtotal de Stripe ya lleva la cuota dentro; base = total - cuota
            'importe' => ['base' => (int) ($s['amount_total'] ?? 0) - (int) ($s['total_details']['amount_tax'] ?? 0),
                'iva' => (int) ($s['total_details']['amount_tax'] ?? 0), 'total' => (int) ($s['amount_total'] ?? 0)],
            'aceptacion' => $meta['aceptacion'] ?? null,
            'estado' => 'cobrada',
        ];
        // El importe cobrado manda en la factura; si no cuadra con el precio, se avisa (no se "arregla").
        // Diseño Atelier comprado: lo dice la sesión de Stripe (se marcó al crearla); el pendiente, de respaldo
        $cfgPend = ($pend !== '' ? lee_json($pend . '/config.json') : null) ?? [];
        $at = (string) ($s['metadata']['atelier'] ?? $cfgPend['atelier'] ?? '');
        $ped['atelier'] = isset(ATELIER[$at]) ? $at : '';
        // Contra el precio congelado en el pedido (El Padrino puede cambiar el vigente con el pago abierto)
        $esperado = (int) ($meta['precio_cent'] ?? precio_total_cent($ped));
        if ($ped['importe']['total'] !== $esperado || $ped['importe']['base'] !== $esperado - iva_de($esperado)) {
            registra('ALERTA importe cobrado distinto del precio', ['sid' => $sid, 'importe' => $ped['importe']]);
        }
        if (empty($ped['factura'])) {
            analitica_evento('alta');   // una vez por pedido: la factura solo se emite la primera
            $ped['factura'] = emite_factura($ped);
            escribe_json($fPedido, $ped);
        }

        return alta_publica($ped, $pend, $meta, $fPedido, $sid, $token, $slug);
    });
}

/**
 * Lo común a toda alta (pagada o de cortesía): crea la web con el pedido pendiente, el enlace
 * del panel y el email. Llamar SOLO dentro de con_cerrojo().
 */
function alta_publica(array $ped, string $pend, ?array $meta, string $fPedido, string $sid, string $token, string $slug): array {
    if (!$meta || !is_file($pend . '/config.json')) {
        registra('ALERTA pago sin pedido pendiente', ['sid' => $sid, 'slug' => $slug]);
        $ped['estado'] = 'sin-datos';
        escribe_json($fPedido, $ped);
        avisa_estudio('Pago de web de boda sin datos del creador', "Pedido $sid ($slug). Cobrado" . (($ped['factura'] ?? '') !== '' ? " y facturado ({$ped['factura']})" : '') . "; la web no se ha podido crear. Contactar con {$ped['email']}.", aviso_ref($sid));
        return $ped;
    }

    // Si el nombre lo ha cogido otro (reserva caducada y pago tardío), se busca uno libre.
    if (!slug_valido($slug) || (boda_existe($slug) && (lee_json(dir_boda($slug) . '/pedido.json')['session_id'] ?? '') !== $sid)) {
        $base = slug_valido($slug) ? $slug : 'boda';
        for ($i = 2; $i < 100 && !slug_libre($base . '-' . $i, $token); $i++);
        registra('slug ocupado al dar de alta, se usa otro', ['sid' => $sid, 'pedido' => $slug, 'nuevo' => $base . '-' . $i]);
        $slug = $base . '-' . $i;
        $ped['slug'] = $slug;
    }

    // Orden pensado para que un corte a mitad se pueda reintentar (revisor, 27-sep-2026):
    //  1. pedido.json de la boda ANTES que config.json: si el reintento encuentra la web, la reconoce
    //     como suya por el session_id y no abre otra con «-2»;
    //  2. 'creada' DESPUÉS del correo: un corte antes del envío reintenta y el correo sale (como mucho,
    //     dos veces); al revés, no saldría nunca;
    //  3. el pendiente se borra al final: sin él, un reintento no tendría datos (quedaría «sin-datos»).
    $d = dir_boda($slug);
    asegura_dir($d . '/guardado');
    $cfg = lee_json($pend . '/config.json');
    $cfg['_estado'] = 'activa';
    // atelier: la boda pagó un diseño Atelier y puede usar cualquiera de la colección desde el panel
    escribe_json($d . '/pedido.json', ['session_id' => $sid, 'pasarela' => (string) ($ped['pasarela'] ?? ''), 'test' => !empty($ped['ls']['test']),
        'factura' => $ped['factura'], 'email' => $ped['email'], 'creado' => date('c'), 'atelier' => $ped['atelier'] !== '',
        // Fecha con la que se contrató: tope para mover la fecha desde el panel (faltan(), MAX_MOVER_FECHA_DIAS)
        'fecha_pago' => (string) ($cfg['fecha'] ?? '')]);
    escribe_json($d . '/config.json', $cfg);
    if (is_file($pend . '/foto.webp')) rename($pend . '/foto.webp', $d . '/foto.webp');
    mapa_actualiza($slug, normaliza_config($cfg)); // si falla, queda pendiente para el cron
    $enlace = panel_nuevo_enlace($slug);
    correo_bienvenida($ped, $cfg, $enlace);
    $ped['estado'] = 'creada';
    escribe_json($fPedido, $ped);
    @unlink(dir_datos('reservas', $ped['slug'] . '.json'));
    borra_arbol($pend);
    // Aviso al owner de cada venta (correo + Telegram). Tras 'creada': un reintento no lo repite
    if (tipo_pago($ped) !== 'regalo') avisa_estudio('Venta nueva' . (!empty($ped['ls']['test']) ? ' (prueba)' : ''), texto_venta($ped), aviso_ref($sid));
    registra('boda creada', ['slug' => $slug, 'sid' => $sid, 'factura' => $ped['factura']]);
    return $ped;
}

/**
 * Alta con un código de cortesía (100 %, sin Stripe). Idempotente por token: la página de
 * éxito puede recargarse sin crear otra web ni gastar otro uso del código.
 */
function alta_cortesia(string $token, string $hash, array $cod): ?array {
    $sid = 'cortesia_' . $token;
    $fPedido = dir_datos('pedidos', $sid . '.json');
    return con_cerrojo(function () use ($token, $hash, $cod, $sid, $fPedido) {
        $ped = lee_json($fPedido);
        if ($ped && ($ped['estado'] ?? '') === 'creada') return $ped;
        $pend = dir_datos('pendientes', $token);
        $meta = lee_json($pend . '/meta.json');
        $cfg = lee_json($pend . '/config.json');
        if (!$meta || !$cfg) return null;
        if (!cortesia_consume($hash, $cod, $sid)) return ['estado' => 'agotado'];
        $slug = (string) ($meta['slug'] ?? '');
        $ped = $ped ?: [
            'session_id' => $sid, 'slug' => $slug, 'token' => $token, 'creado' => date('c'),
            'email' => (string) ($cfg['pareja']['email'] ?? ''), 'nombre' => nombres(normaliza_config($cfg)),
            'importe' => ['base' => 0, 'iva' => 0, 'total' => 0], 'aceptacion' => $meta['aceptacion'] ?? null,
            'estado' => 'cortesia', 'cortesia' => (string) ($cod['id'] ?? ''), 'factura' => '',
            'atelier' => isset(ATELIER[$cfg['atelier'] ?? '']) ? $cfg['atelier'] : '',
        ];
        escribe_json($fPedido, $ped);
        $ped = alta_publica($ped, $pend, $meta, $fPedido, $sid, $token, $slug);
        cortesia_registra(['codigo' => $cod['id'] ?? '', 'hash' => substr($hash, 0, 12), 'slug' => $ped['slug'], 'pareja' => $ped['nombre'],
            'email' => $ped['email'], 'pack' => $ped['atelier'] !== '' ? 'Atelier' : 'Esencial', 'precio_catalogo_cent' => precio_total_cent($ped),
            'fin_alojamiento' => fecha_borrado((string) ($cfg['fecha'] ?? '')), 'ip' => ip_cliente(), 'estado' => $ped['estado'] ?? '',
            // Para el gestor (BOD-4, Administración 29-sep): si el canje fue en pruebas (amigos que prueban) o con cobro real, y el porqué del código
            'modo' => titular_oculto() ? 'pruebas' : 'real', 'motivo' => (string) ($cod['nota'] ?? '')]);
        return $ped;
    });
}

function borra_arbol(string $d): void {
    if ($d === '' || !is_dir($d) || strpos(realpath($d) ?: '', realpath(DATA_DIR) ?: "\0") !== 0) return;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    @rmdir($d);
}
