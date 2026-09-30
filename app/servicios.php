<?php
// Servicios de pago sobre una web YA publicada: la mejora a Atelier (app/lemon.php) y los extras (app/extras.php).
// Un solo camino de compra y de activación para los dos (BOD-23, 27-sep-2026): antes extras.php copiaba ~200 líneas de
// la mejora, y el álbum, los idiomas y el dominio las habrían copiado otra vez.
//
// Lo común (revisiones #109 y #133), escrito UNA vez aquí:
//  · Compra: CSRF, límite, puertas de venta, casilla de Legal, importe del SERVIDOR congelado en su pedido
//    (<dir>/<token>.json) y una sola compra abierta por boda y servicio (<dir>/abierta_*.json).
//  · Activación por webhook: idempotente por pedido de LS (estados finales) y por token (ls_tokens: un segundo cobro
//    es «duplicado»), qué y de qué boda se lee del pedido del servidor y nunca de `custom_data`, el pedido de LS se
//    relee y se compara (lemon_motivos_no_conforme), el registro local se escribe ANTES de tocar la boda, y un
//    reintento tras un corte remata el cierre sin duplicar.
//
// Lo que cambia entre servicios va en su ficha (servicio_pago) y NO se aplana, porque es legal, no accidental:
//  · mejora = art. 103.m (pierde el desistimiento; casilla `check_mejora`, guardada como `desistimiento`);
//  · extra  = servicio 103.a/108.3 (desistible con prorrata; casilla `check_extra`, guardada como `casilla`), con la
//    clave del custom_data comprobada contra la del servidor y el caso «reembolsado entre el corte y el reintento».
// Los nombres de disco (mejoras/, extras/, pedido.json.mejora, pedido.json.extras) son los de siempre: hay pedidos
// vivos en producción. El reembolso (lemon_reembolso) no pasa por aquí: se cierra aparte (BOD-21).

declare(strict_types=1);

/**
 * Ficha de un servicio de pago, o null si no existe. $clave solo para 'extra' (y tiene que estar en EXTRAS).
 * Cada ficha dice dónde guarda sus pedidos, cómo se bloquea, cuánto cuesta, qué casilla acepta la pareja, cómo se
 * aplica en pedido.json y con qué correo se confirma.
 */
function servicio_pago(string $tipo, string $clave = ''): ?array {
    if ($tipo === 'mejora') return [
        'tipo' => 'mejora', 'clave' => '', 'dir' => servicio_dir('mejora'), 'limite' => 'mejora', 'post' => 'acepto_mejora', 'campo_casilla' => 'desistimiento',
        'bloqueo' => fn(string $slug) => mejora_bloqueo($slug),
        'precio' => fn() => precio_mejora_cent(),
        'casilla' => fn() => texto_mejora(),
        'meta' => [],
        'aceptacion' => [],
        'errores' => ['ya-atelier' => 'Vuestra web ya tiene el Pack Atelier.', 'abierta' => 'Ya hay un pago de la mejora abierto. Terminadlo o esperad media hora.',
            'archivada' => 'Esta web ya está archivada.', 'sin-pedido' => 'Esta web no admite la mejora. Escribidnos y lo vemos.', 'sin-boda' => 'Esta web no existe.'],
        'no_disponible' => 'La mejora todavía no está disponible. Escribidnos y os ayudamos.',
        'sin_importe' => 'La mejora no está disponible ahora mismo.',
        'que' => 'la mejora',   // en los avisos al estudio
        // Reintento tras un corte: ¿la boda ya quedó marcada por ESTE pedido?
        'aplicado' => fn(array $bp, string $sid) => ($bp['mejora']['session_id'] ?? '') === $sid,
        'baja_tras_corte' => fn(array $bp) => false,   // el reembolso de una mejora no toca la boda (decide el owner)
        'rematable' => true,
        'ya_lo_tiene' => ['ya-atelier' => ['Mejora pagada en una web que ya era Atelier', 'la web ya tenía el Pack Atelier.']],
        // Aplicar: la boda puede usar cualquier diseño Atelier desde el panel, sin límite de cambios
        'aplica' => function (array &$bp, string $sid, array $o) {
            $bp['atelier'] = true;
            $bp['mejora'] = ['session_id' => $sid, 'fecha' => date('c'), 'importe_cent' => (int) ($o['total'] ?? 0)];
        },
        'correo' => fn(string $slug, array $ped, array $meta) => datos_mejora_correo($slug, $ped, $meta),
        'asunto' => 'Ya tenéis el Pack Atelier',
        'aviso' => 'Mejora a Atelier',
        'registro' => 'mejora a Atelier aplicada',
    ];
    if ($tipo !== 'extra' || !extra_existe($clave)) return null;
    $x = EXTRAS[$clave];
    return [
        'tipo' => 'extra', 'clave' => $clave, 'dir' => servicio_dir('extra'), 'limite' => 'extra', 'post' => 'acepto_extra', 'campo_casilla' => 'casilla',
        'bloqueo' => fn(string $slug) => extra_bloqueo($slug, $clave),
        'precio' => fn() => precio_extra_cent($clave),
        'casilla' => fn() => texto_extra(),
        'meta' => ['clave' => $clave],
        'aceptacion' => ['extra' => $x['nombre']],
        'errores' => ['ya-activo' => 'Vuestra web ya tiene este extra.', 'abierta' => 'Ya hay un pago de este extra abierto. Terminadlo o esperad media hora.',
            'archivada' => 'Esta web ya está archivada.', 'sin-pedido' => 'Esta web no admite extras. Escribidnos y lo vemos.', 'sin-boda' => 'Esta web no existe.'],
        'no_disponible' => 'Este extra todavía no está disponible. Escribidnos y os ayudamos.',
        'sin_importe' => 'Este extra no está disponible ahora mismo.',
        'que' => 'el extra «' . $x['nombre'] . '»',
        'aplicado' => fn(array $bp, string $sid) => (string) ($bp['extras'][$clave]['pedido'] ?? '') === $sid,
        // ...salvo que entre el corte y el reintento llegara el reembolso: ni «Ya tenéis» ni reactivar (revisor, 27-sep)
        'baja_tras_corte' => fn(array $bp) => !empty($bp['extras'][$clave]['baja']),
        // Un extra que ya va incluido nunca se remata como compra, ni siquiera tras un corte: se devuelve
        'rematable' => !extra_incluido($clave),
        'ya_lo_tiene' => [
            'ya-activo' => ['Extra pagado en una web que ya lo tenía', 'la web ya tenía «' . $x['nombre'] . '».'],
            // Un pago que se abrió antes de que el extra pasara a ir gratis en los packs. Nada que activar: se devuelve
            'incluido' => ['Extra pagado en una web que ya lo tenía', 'la web ya tenía «' . $x['nombre'] . '» (va incluido en todos los packs).'],
        ],
        // Único sitio que escribe extras.<clave> (Seguridad #133)
        'aplica' => function (array &$bp, string $sid, array $o) use ($clave) {
            $bp['extras'] = (array) ($bp['extras'] ?? []);
            $bp['extras'][$clave] = ['desde' => date('c'), 'pedido' => $sid];
        },
        'correo' => fn(string $slug, array $ped, array $meta) => datos_extra_correo($slug, $clave, $ped, $meta),
        'asunto' => 'Ya tenéis ' . $x['nombre'],
        'aviso' => 'Extra «' . $x['nombre'] . '»',
        'registro' => 'extra activado',
    ];
}

/** Carpeta de los pedidos de cada tipo de servicio (la ficha y el webhook, que la necesita antes de saber la clave). */
function servicio_dir(string $tipo): string { return $tipo === 'mejora' ? 'mejoras' : 'extras'; }

/** Marca «hay un pago de este servicio abierto en esta boda» (vive lo que el checkout). */
function servicio_fichero_abierta(array $s, string $slug): string {
    return dir_datos($s['dir'], 'abierta_' . $slug . ($s['clave'] !== '' ? '_' . $s['clave'] : '') . '.json');
}

/** Libera la marca de pago abierto si es de ESTE token (una compra posterior no se toca). */
function servicio_libera_abierta(array $s, string $slug, string $token): void {
    if (!slug_valido($slug)) return;
    $fa = servicio_fichero_abierta($s, $slug);
    if ((lee_json($fa)['token'] ?? '') === $token) @unlink($fa);
}

/** Puertas de venta comunes: en pruebas (modo test de LS) solo el estudio abre el pago; sin la casilla de Legal no se vende. */
function servicio_venta_abierta(string $casilla): bool {
    return pasarela() === 'lemon' && lemon_configurada() && lemon_checkout_permitido() && empresa_completa() && $casilla !== '';
}

/**
 * POST de compra desde el panel (sesión del panel ya exigida por rutas_panel; CSRF, límite y puertas aquí).
 * Abre el pedido del servidor con el precio congelado y devuelve la URL de pago de Lemon.
 */
function servicio_compra(array $s, string $slug): void {
    if (!limite($s['limite'] . '|' . $slug, 10, 3600, true)) json_response(['ok' => false, 'error' => 'Demasiados intentos. Prueba dentro de un rato.'], 429);
    if (!servicio_venta_abierta(($s['casilla'])())) json_response(['ok' => false, 'error' => $s['no_disponible']], 503);
    if (($_POST[$s['post']] ?? '') !== 'si') json_response(['ok' => false, 'error' => 'Marca la casilla para continuar.'], 422);
    $precio = ($s['precio'])();
    if ($precio <= 0) {
        registra('ALERTA ' . $s['tipo'] . ' con importe no positivo', ['clave' => $s['clave'], 'precio' => $precio]);
        json_response(['ok' => false, 'error' => $s['sin_importe']], 503);
    }
    $L = textos_legales();
    $tok = bin2hex(random_bytes(16));
    // Un solo pago abierto por boda y servicio; el pedido del servidor guarda qué, de qué boda y el precio congelado
    $motivo = con_cerrojo(function () use ($s, $slug, $tok, $precio, $L) {
        $m = ($s['bloqueo'])($slug);
        if ($m !== '') return $m;
        $fa = servicio_fichero_abierta($s, $slug);
        if ((lee_json($fa)['hasta'] ?? 0) > time()) return 'abierta';
        escribe_json(dir_datos($s['dir'], $tok . '.json'), ['tipo' => $s['tipo']] + $s['meta'] + ['slug' => $slug, 'creado' => time(), 'precio_cent' => $precio,
            'pasarela' => 'lemon', 'estado' => 'abierta', 'aceptacion' => ['fecha' => date('c'), 'version' => $L['version'] ?? '',
                // Lo que se enseñó y aceptó, literal, y quién vendía: el correo de la compra lo repite (art. 98.7)
                $s['campo_casilla'] => ($s['casilla'])()] + $s['aceptacion'] + ['vendedor' => (string) ($L['vendedor'] ?? ''), 'precio_cent' => $precio]]);
        escribe_json($fa, ['token' => $tok, 'hasta' => time() + LEMON_CADUCIDAD_S + 60]);
        return '';
    });
    if ($motivo !== '') json_response(['ok' => false, 'error' => $s['errores'][$motivo] ?? 'No se puede ahora.'], 409);
    $bp = lee_json(dir_boda($slug) . '/pedido.json') ?? [];
    $email = (string) ($bp['email'] ?? '') ?: (string) ((lee_json(dir_boda($slug) . '/config.json') ?? [])['pareja']['email'] ?? '');
    $url = lemon_crea_checkout($tok, $slug, $email, $precio, $s['tipo'], $s['clave']);
    if ($url === '') {
        con_cerrojo(function () use ($s, $slug, $tok) {
            @unlink(dir_datos($s['dir'], $tok . '.json'));
            servicio_libera_abierta($s, $slug, $tok);
        });
        json_response(['ok' => false, 'error' => 'No hemos podido abrir el pago. Inténtalo de nuevo.'], 502);
    }
    json_response(['ok' => true, 'url' => $url]);
}

/**
 * Servicio cobrado (webhook order_created con custom_data.tipo = mejora | extra): valida contra su pedido congelado
 * y lo aplica en la boda. Idempotente, bajo el cerrojo global (el mismo que el alta: nadie pisa el pedido.json de otro).
 */
function lemon_servicio(string $tipo, string $id, array $o, array $custom): array {
    $sid = 'ls_' . $id;
    $fPedido = dir_datos('pedidos', $sid . '.json');
    return con_cerrojo(function () use ($tipo, $id, $o, $custom, $sid, $fPedido) {
        $ped = lee_json($fPedido);
        if ($ped && in_array($ped['estado'] ?? '', LEMON_FINALES, true)) return $ped;
        $dir = servicio_dir($tipo);
        $token = (string) ($custom['token'] ?? '');
        $tokOk = (bool) preg_match('/^[a-f0-9]{32}$/', $token);
        $fTok = $tokOk ? dir_datos('ls_tokens', $token . '.json') : '';
        $fMeta = $tokOk ? dir_datos($dir, $token . '.json') : '';
        $meta = $fMeta !== '' ? lee_json($fMeta) : null;
        // Qué servicio y de qué boda: del fichero del servidor. El custom_data solo sirve para el aviso si no hay fichero
        $s = servicio_pago($tipo, (string) ($meta['clave'] ?? ''));
        $slug = (string) ($meta['slug'] ?? $custom['slug'] ?? '');
        $ped = $ped ?: lemon_pedido_base($sid, $id, $o, $slug, $token, '', $meta['aceptacion'] ?? null)
            + ['tipo' => $tipo] + ($tipo === 'extra' ? ['clave' => $s ? $s['clave'] : ''] : []) + ['atelier' => ''];
        $un = $tipo === 'mejora' ? 'una mejora a Atelier' : 'un extra';
        $nada = $tipo === 'mejora' ? 'No se ha cambiado nada.' : 'No se ha activado nada.';
        $aviso = function (string $estado, string $asunto, string $texto) use (&$ped, $fPedido, $s, $slug, $token, $id) {
            if ($s) servicio_libera_abierta($s, $slug, $token);
            $ped['estado'] = $estado;
            escribe_json($fPedido, $ped);
            avisa_estudio($asunto, $texto, 'Pedido LS ' . $id);
            return $ped;
        };
        $previo = $fTok !== '' ? (string) ((lee_json($fTok) ?? [])['order_id'] ?? '') : '';
        if ($previo !== '' && $previo !== $id) {
            $ped['duplicado_de'] = 'ls_' . $previo;
            return $aviso('duplicado', "Pago duplicado de $un", "Pedido LS $id ($slug): " . ($tipo === 'mejora' ? 'esa mejora' : 'ese extra')
                . " ya se pagó con el pedido LS $previo. $nada Devolver este cobro desde Lemon Squeezy.");
        }
        if ($fTok !== '' && $previo === '') escribe_json($fTok, ['order_id' => $id, 'creado' => date('c'), 'tipo' => $tipo]);
        if (!$meta || ($meta['tipo'] ?? '') !== $tipo || !$s) {
            return $aviso('sin-datos', ($tipo === 'mejora' ? 'Pago de mejora' : 'Pago de extra') . ' sin pedido', "Pedido LS $id ($slug): cobrado $un sin su pedido en el servidor. $nada Revisar.");
        }
        $motivos = lemon_motivos_no_conforme($o, $meta, $custom);
        // La clave del custom_data tiene que ser la del pedido del servidor: si no, alguien la ha cambiado
        if ($tipo === 'extra' && (string) ($custom['clave'] ?? '') !== $s['clave']) $motivos[] = 'clave';
        if ($motivos) {
            $ped['motivos'] = $motivos;
            return $aviso('no-conforme', ($tipo === 'mejora' ? 'Pago de mejora' : 'Pago de extra') . ' que no cuadra',
                "Pedido LS $id ($slug) cobrado pero no cuadra con {$s['que']}: " . implode(', ', $motivos) . ". " . lemon_diag_importes($o) . " $nada Revisar en Lemon Squeezy.");
        }
        // Reintento tras un corte: la boda ya quedó marcada por ESTE pedido → se remata el cierre (no es un duplicado)
        $bp = lee_json(dir_boda($slug) . '/pedido.json') ?? [];
        $yaAplicado = ($s['aplicado'])($bp, $sid);
        if ($yaAplicado && ($s['baja_tras_corte'])($bp)) {
            return $aviso('reembolsado', 'Compra reembolsada antes de rematar la activación', "Pedido LS $id ($slug): {$s['que']} se reembolsó antes de terminar la activación. Sigue desactivado; no se ha mandado confirmación.");
        }
        $bloqueo = $yaAplicado && $s['rematable'] ? '' : ($s['bloqueo'])($slug);
        if (isset($s['ya_lo_tiene'][$bloqueo])) {
            [$asunto, $que] = $s['ya_lo_tiene'][$bloqueo];
            return $aviso('duplicado', $asunto, "Pedido LS $id ($slug): $que Devolver este cobro desde Lemon Squeezy.");
        }
        if ($bloqueo !== '') {
            $ped['motivos'] = [$bloqueo];
            return $aviso('no-conforme', ($tipo === 'mejora' ? 'Mejora pagada' : 'Extra pagado') . ' en una web que no lo admite', "Pedido LS $id ($slug): $bloqueo. $nada Revisar.");
        }
        // El registro local (tipo y clave) se escribe ANTES de tocar la boda (code-review): si la petición muere entre
        // las dos escrituras y llega un reembolso, lemon_reembolso encuentra el pedido con su tipo
        escribe_json($fPedido, $ped);
        if (!$yaAplicado) {
            ($s['aplica'])($bp, $sid, $o);   // se lee y se reescribe el pedido.json entero
            escribe_json(dir_boda($slug) . '/pedido.json', $bp);
        }
        $meta['estado'] = 'pagada';
        $meta['session_id'] = $sid;
        escribe_json($fMeta, $meta);
        servicio_libera_abierta($s, $slug, $token);
        if ($ped['email'] === '') $ped['email'] = (string) ($bp['email'] ?? '');
        $dc = ($s['correo'])($slug, $ped, $meta);
        envia_o_encola(['tipo' => 'correo', 'para' => $ped['email'], 'asunto' => $s['asunto'], 'texto' => compra_texto($dc), 'html' => compra_html($dc)]);
        avisa_estudio($s['aviso'] . (!empty($ped['ls']['test']) ? ' (prueba)' : ''),
            'Web: ' . url_boda($slug) . "\nImporte: " . euros((int) ($ped['importe']['total'] ?? 0)) . ' (IVA ' . euros((int) ($ped['importe']['iva'] ?? 0)) . ")\n"
            . 'Pago: Lemon Squeezy #' . ($ped['ls']['order_number'] ?? '') . (!empty($ped['ls']['test']) ? ' (PRUEBA, modo test)' : '') . "\nComprador: " . $ped['email'], 'Pedido LS ' . $id);
        $ped['estado'] = 'creada';
        escribe_json($fPedido, $ped);
        registra($s['registro'], ['slug' => $slug, 'clave' => $s['clave'], 'sid' => $sid]);
        return $ped;
    });
}

/** URL del recibo de Lemon de un pedido nuestro (ls_<id>), o ''. Una sola lectura para el panel y los extras. */
function recibo_ls(string $sid): string {
    if (!preg_match('/^ls_\d{1,15}$/', $sid)) return '';
    $r = (string) ((lee_json(dir_datos('pedidos', $sid . '.json')) ?? [])['ls']['recibo'] ?? '');
    return str_starts_with($r, 'https://') ? $r : '';
}
