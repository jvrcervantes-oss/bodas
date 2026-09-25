<?php
// Alta de una boda tras el pago. La llaman el webhook de Stripe y la página de
// éxito (por si el webhook tarda): las dos pasan por aquí y el resultado es el mismo
// aunque lleguen diez veces — pedidos/<session_id>.json es la marca de "ya hecho".
// La factura se emite aunque la web falle: con cobro anticipado el IVA se devenga
// al cobrar (Administración, revisión previa #81).

declare(strict_types=1);

require_once __DIR__ . '/stripe.php';
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
        if ($ped['importe']['total'] !== precio_total_cent($ped) || $ped['importe']['base'] !== precio_base_cent($ped)) {
            registra('ALERTA importe cobrado distinto del precio', ['sid' => $sid, 'importe' => $ped['importe']]);
        }
        if (empty($ped['factura'])) {
            $ped['factura'] = emite_factura($ped);
            escribe_json($fPedido, $ped);
        }

        if (!$meta || !is_file($pend . '/config.json')) {
            registra('ALERTA pago sin pedido pendiente', ['sid' => $sid, 'slug' => $slug]);
            $ped['estado'] = 'sin-datos';
            escribe_json($fPedido, $ped);
            avisa_estudio('Pago de web de boda sin datos del creador', "Sesión $sid ($slug). Cobrado y facturado ({$ped['factura']}); la web no se ha podido crear. Contactar con {$ped['email']}.");
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

        $d = dir_boda($slug);
        asegura_dir($d . '/guardado');
        $cfg = lee_json($pend . '/config.json');
        $cfg['_estado'] = 'activa';
        escribe_json($d . '/config.json', $cfg);
        if (is_file($pend . '/foto.webp')) rename($pend . '/foto.webp', $d . '/foto.webp');
        // atelier: la boda pagó un diseño Atelier y puede usar cualquiera de la colección desde el panel
        escribe_json($d . '/pedido.json', ['session_id' => $sid, 'factura' => $ped['factura'], 'email' => $ped['email'], 'creado' => date('c'), 'atelier' => $ped['atelier'] !== '']);
        $enlace = panel_nuevo_enlace($slug);
        $ped['estado'] = 'creada';
        escribe_json($fPedido, $ped);
        @unlink(dir_datos('reservas', $ped['slug'] . '.json'));
        borra_arbol($pend);

        correo_bienvenida($ped, $cfg, $enlace);
        registra('boda creada', ['slug' => $slug, 'sid' => $sid, 'factura' => $ped['factura']]);
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
