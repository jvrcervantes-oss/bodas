<?php
// Extras de pago de una boda ya publicada (plano de mesas; después álbum, idiomas y dominio). Encargo
// encargos/20260927_bodas_servicios_extra.md (repo del estudio), revisión previa #133.
//
// POR QUÉ ASÍ — calco de la «mejora a Atelier» de app/lemon.php, que ya pasó la revisión #109:
//  · La lista de extras, su precio y sus límites viven en app/padrino.php (EXTRAS, precio_extra_cent): una
//    sola fuente, la misma que la de los packs. Aquí nunca se escribe un precio.
//  · La compra abre un pedido del SERVIDOR (extras/<token>.json) con la clave, la boda y el precio congelado.
//    El checkout de Lemon cobra ese importe (`custom_price`); del navegador solo llega la clave, que se
//    comprueba contra la lista cerrada (fuera de ella → 400).
//  · El webhook decide qué activar leyendo extras/<token>.json, NUNCA `custom_data` (Seguridad #133): si el
//    custom_data trae otra clave u otra boda, el pedido es «no-conforme» y no se activa nada. El pedido se
//    vuelve a pedir a la API y se compara como el de la mejora (importe == congelado, sin descuento, modo…).
//  · `pedido.json.extras.<clave> = {desde, pedido}` lo escribe SOLO lemon_extra() y lo da de baja SOLO el
//    reembolso (extra_baja). panel_guardar escribe config.json y no puede tocarlo. Cada página y cada POST de
//    un extra lo comprueba en el servidor (extra_activo).
//  · Idempotente como la mejora: por pedido de LS (pedidos/ls_<id>.json, estados finales) y por token
//    (ls_tokens/<token>.json: un segundo cobro del mismo pedido es «duplicado» y se avisa para devolverlo).

declare(strict_types=1);

/** Casilla que acepta la pareja al comprar un extra. SOLO la de Legal (`check_extra`); sin ella no se vende. */
function texto_extra(): string { return (string) (textos_legales()['check_extra'] ?? ''); }

/** ¿Tiene la boda este extra activo ahora? Lectura del servidor: la única que vale para abrir su página o su POST. */
function extra_activo(string $slug, string $clave): bool {
    return slug_valido($slug) && extra_activo_en(lee_json(dir_boda($slug) . '/pedido.json') ?? [], $clave);
}

/**
 * '' si esta boda puede recibir el extra; si no, el motivo. Mismo criterio que mejora_bloqueo(): webs pagadas o
 * regaladas que siguen en pie (una web regalada con código también compra extras, Administración #133).
 */
function extra_bloqueo(string $slug, string $clave): string {
    if (!extra_existe($clave)) return 'sin-extra';
    if (!slug_valido($slug) || !boda_existe($slug)) return 'sin-boda';
    $bp = lee_json(dir_boda($slug) . '/pedido.json') ?? [];
    if (extra_activo_en($bp, $clave)) return 'ya-activo';
    if (((lee_json(dir_boda($slug) . '/config.json') ?? [])['_estado'] ?? '') === 'archivada') return 'archivada';
    $sid = (string) ($bp['session_id'] ?? '');
    $ped = preg_match('/^[A-Za-z0-9_]{3,120}$/', $sid) ? lee_json(dir_datos('pedidos', $sid . '.json')) : null;
    if (!$ped || ($ped['estado'] ?? '') !== 'creada') return 'sin-pedido';
    return '';
}

/** Las mismas puertas de venta que la mejora: en pruebas (modo test de LS) solo el estudio abre el pago. */
function extra_venta_abierta(): bool {
    return pasarela() === 'lemon' && lemon_configurada() && lemon_checkout_permitido() && empresa_completa() && texto_extra() !== '';
}

/** ¿Se puede ofrecer la compra de este extra en este panel ahora? */
function extra_disponible(string $slug, string $clave): bool {
    return extra_existe($clave) && EXTRAS[$clave]['venta'] && extra_venta_abierta() && precio_extra_cent($clave) > 0 && extra_bloqueo($slug, $clave) === '';
}

/** Fichero de la marca «hay un pago de este extra abierto» (uno por boda y extra, vive lo que el checkout). */
function extra_fichero_abierta(string $slug, string $clave): string { return dir_datos('extras', 'abierta_' . $slug . '_' . $clave . '.json'); }

/** POST /panel/extra (sesión del panel ya exigida por rutas_panel; CSRF aquí). Devuelve la URL de pago. */
function panel_extra(string $slug, string $metodo): void {
    if ($metodo !== 'POST') json_response(['ok' => false], 405);
    if (!panel_csrf_ok()) json_response(['ok' => false, 'error' => 'La sesión ha caducado. Recarga la página.'], 403);
    $clave = (string) ($_POST['clave'] ?? '');
    if (!extra_existe($clave)) json_response(['ok' => false, 'error' => 'Ese extra no existe.'], 400);
    if (!EXTRAS[$clave]['venta']) json_response(['ok' => false, 'error' => 'Este extra todavía no está a la venta.'], 409);
    if (!limite('extra|' . $slug, 10, 3600, true)) json_response(['ok' => false, 'error' => 'Demasiados intentos. Prueba dentro de un rato.'], 429);
    if (!extra_venta_abierta()) json_response(['ok' => false, 'error' => 'Este extra todavía no está disponible. Escribidnos y os ayudamos.'], 503);
    if (($_POST['acepto_extra'] ?? '') !== 'si') json_response(['ok' => false, 'error' => 'Marca la casilla para continuar.'], 422);
    $precio = precio_extra_cent($clave);
    if ($precio <= 0) { registra('ALERTA extra con importe no positivo', ['clave' => $clave, 'precio' => $precio]); json_response(['ok' => false, 'error' => 'Este extra no está disponible ahora mismo.'], 503); }
    $L = textos_legales();
    $tok = bin2hex(random_bytes(16));
    // Un solo pago abierto por boda y extra; el pedido del servidor guarda la clave, la boda y el precio congelado
    $motivo = con_cerrojo(function () use ($slug, $clave, $tok, $precio, $L) {
        $m = extra_bloqueo($slug, $clave);
        if ($m !== '') return $m;
        $fa = extra_fichero_abierta($slug, $clave);
        if ((lee_json($fa)['hasta'] ?? 0) > time()) return 'abierta';
        escribe_json(dir_datos('extras', $tok . '.json'), ['tipo' => 'extra', 'clave' => $clave, 'slug' => $slug, 'creado' => time(), 'precio_cent' => $precio,
            'pasarela' => 'lemon', 'estado' => 'abierta', 'aceptacion' => ['fecha' => date('c'), 'version' => $L['version'] ?? '',
                // Lo que se enseñó y aceptó, literal, qué extra y quién vendía: el correo del extra lo repite (art. 98.7)
                'casilla' => texto_extra(), 'extra' => EXTRAS[$clave]['nombre'], 'vendedor' => (string) ($L['vendedor'] ?? ''), 'precio_cent' => $precio]]);
        escribe_json($fa, ['token' => $tok, 'hasta' => time() + LEMON_CADUCIDAD_S + 60]);
        return '';
    });
    $err = ['ya-activo' => 'Vuestra web ya tiene este extra.', 'abierta' => 'Ya hay un pago de este extra abierto. Terminadlo o esperad media hora.',
        'archivada' => 'Esta web ya está archivada.', 'sin-pedido' => 'Esta web no admite extras. Escribidnos y lo vemos.', 'sin-boda' => 'Esta web no existe.'];
    if ($motivo !== '') json_response(['ok' => false, 'error' => $err[$motivo] ?? 'No se puede ahora.'], 409);
    $bp = lee_json(dir_boda($slug) . '/pedido.json') ?? [];
    $email = (string) ($bp['email'] ?? '') ?: (string) ((lee_json(dir_boda($slug) . '/config.json') ?? [])['pareja']['email'] ?? '');
    $url = lemon_crea_checkout($tok, $slug, $email, $precio, 'extra', $clave);
    if ($url === '') {
        con_cerrojo(function () use ($slug, $clave, $tok) {
            @unlink(dir_datos('extras', $tok . '.json'));
            $fa = extra_fichero_abierta($slug, $clave);
            if ((lee_json($fa)['token'] ?? '') === $tok) @unlink($fa);
        });
        json_response(['ok' => false, 'error' => 'No hemos podido abrir el pago. Inténtalo de nuevo.'], 502);
    }
    json_response(['ok' => true, 'url' => $url]);
}

/**
 * Extra cobrado: valida contra su pedido congelado y lo activa en la boda. Idempotente, bajo el cerrojo global
 * (el mismo que el alta y la mejora: ninguna de las tres pisa el pedido.json de otra).
 */
function lemon_extra(string $id, array $o, array $custom): array {
    $sid = 'ls_' . $id;
    $fPedido = dir_datos('pedidos', $sid . '.json');
    return con_cerrojo(function () use ($id, $o, $custom, $sid, $fPedido) {
        $ped = lee_json($fPedido);
        if ($ped && in_array($ped['estado'] ?? '', LEMON_FINALES, true)) return $ped;
        $token = (string) ($custom['token'] ?? '');
        $tokOk = (bool) preg_match('/^[a-f0-9]{32}$/', $token);
        $fTok = $tokOk ? dir_datos('ls_tokens', $token . '.json') : '';
        $fMeta = $tokOk ? dir_datos('extras', $token . '.json') : '';
        $meta = $fMeta !== '' ? lee_json($fMeta) : null;
        // Qué extra y de qué boda: del fichero del servidor. El custom_data solo sirve para el aviso si no hay fichero
        $clave = (string) ($meta['clave'] ?? '');
        $slug = (string) ($meta['slug'] ?? $custom['slug'] ?? '');
        $ped = $ped ?: lemon_pedido_base($sid, $id, $o, $slug, $token, '', $meta['aceptacion'] ?? null)
            + ['tipo' => 'extra', 'clave' => extra_existe($clave) ? $clave : '', 'atelier' => ''];
        $aviso = function (string $estado, string $asunto, string $texto) use (&$ped, $fPedido, $slug, $clave, $token, $id) {
            if (slug_valido($slug) && extra_existe($clave)) {
                $fa = extra_fichero_abierta($slug, $clave);
                if ((lee_json($fa)['token'] ?? '') === $token) @unlink($fa);
            }
            $ped['estado'] = $estado;
            escribe_json($fPedido, $ped);
            avisa_estudio($asunto, $texto, 'Pedido LS ' . $id);
            return $ped;
        };
        $previo = $fTok !== '' ? (string) ((lee_json($fTok) ?? [])['order_id'] ?? '') : '';
        if ($previo !== '' && $previo !== $id) {
            $ped['duplicado_de'] = 'ls_' . $previo;
            return $aviso('duplicado', 'Pago duplicado de un extra', "Pedido LS $id ($slug): ese extra ya se pagó con el pedido LS $previo. No se ha activado nada. Devolver este cobro desde Lemon Squeezy.");
        }
        if ($fTok !== '' && $previo === '') escribe_json($fTok, ['order_id' => $id, 'creado' => date('c'), 'tipo' => 'extra']);
        if (!$meta || ($meta['tipo'] ?? '') !== 'extra' || !extra_existe($clave)) {
            return $aviso('sin-datos', 'Pago de extra sin pedido', "Pedido LS $id ($slug): cobrado un extra sin su pedido en el servidor. No se ha activado nada. Revisar.");
        }
        $motivos = lemon_motivos_no_conforme($o, $meta, $custom);
        // La clave del custom_data tiene que ser la del pedido del servidor: si no, alguien la ha cambiado
        if ((string) ($custom['clave'] ?? '') !== $clave) $motivos[] = 'clave';
        if ($motivos) {
            $ped['motivos'] = $motivos;
            return $aviso('no-conforme', 'Pago de extra que no cuadra', "Pedido LS $id ($slug) cobrado pero no cuadra con el extra «" . EXTRAS[$clave]['nombre'] . '»: ' . implode(', ', $motivos) . '. No se ha activado nada. Revisar en Lemon Squeezy.');
        }
        // Reintento tras un corte: la boda ya quedó marcada por ESTE pedido → se remata el cierre (no es un duplicado)
        $bp = lee_json(dir_boda($slug) . '/pedido.json') ?? [];
        $yaAplicado = (string) ($bp['extras'][$clave]['pedido'] ?? '') === $sid;
        // ...salvo que entre el corte y el reintento llegara el reembolso: ni «Ya tenéis» ni reactivar (revisor, 27-sep)
        if ($yaAplicado && !empty($bp['extras'][$clave]['baja'])) {
            return $aviso('reembolsado', 'Extra reembolsado antes de rematar la activación', "Pedido LS $id ($slug): «" . EXTRAS[$clave]['nombre'] . '» se reembolsó antes de terminar la activación. Sigue desactivado; no se ha mandado confirmación.');
        }
        $bloqueo = $yaAplicado ? '' : extra_bloqueo($slug, $clave);
        if ($bloqueo === 'ya-activo') {
            return $aviso('duplicado', 'Extra pagado en una web que ya lo tenía', "Pedido LS $id ($slug): la web ya tenía «" . EXTRAS[$clave]['nombre'] . '». Devolver este cobro desde Lemon Squeezy.');
        }
        if ($bloqueo !== '') {
            $ped['motivos'] = [$bloqueo];
            return $aviso('no-conforme', 'Extra pagado en una web que no lo admite', "Pedido LS $id ($slug): $bloqueo. No se ha activado nada. Revisar.");
        }
        // El registro local (tipo y clave) se escribe ANTES de tocar la boda (code-review): si la petición muere entre
        // las dos escrituras y llega un reembolso, lemon_reembolso encuentra el pedido como extra y lo da de baja
        escribe_json($fPedido, $ped);
        if (!$yaAplicado) {
            // Único sitio que escribe extras.<clave> (Seguridad #133). Se lee y se reescribe el pedido.json entero
            $bp['extras'] = (array) ($bp['extras'] ?? []);
            $bp['extras'][$clave] = ['desde' => date('c'), 'pedido' => $sid];
            escribe_json(dir_boda($slug) . '/pedido.json', $bp);
        }
        $meta['estado'] = 'pagada';
        $meta['session_id'] = $sid;
        escribe_json($fMeta, $meta);
        $fa = extra_fichero_abierta($slug, $clave);
        if ((lee_json($fa)['token'] ?? '') === $token) @unlink($fa);
        if ($ped['email'] === '') $ped['email'] = (string) ($bp['email'] ?? '');
        $dc = datos_extra_correo($slug, $clave, $ped, $meta);
        envia_o_encola(['tipo' => 'correo', 'para' => $ped['email'], 'asunto' => 'Ya tenéis ' . EXTRAS[$clave]['nombre'], 'texto' => compra_texto($dc), 'html' => compra_html($dc)]);
        avisa_estudio('Extra «' . EXTRAS[$clave]['nombre'] . '»' . (!empty($ped['ls']['test']) ? ' (prueba)' : ''),
            'Web: ' . url_boda($slug) . "\nImporte: " . euros((int) ($ped['importe']['total'] ?? 0)) . ' (IVA ' . euros((int) ($ped['importe']['iva'] ?? 0)) . ")\n"
            . 'Pago: Lemon Squeezy #' . ($ped['ls']['order_number'] ?? '') . (!empty($ped['ls']['test']) ? ' (PRUEBA, modo test)' : '') . "\nComprador: " . $ped['email'], 'Pedido LS ' . $id);
        $ped['estado'] = 'creada';
        escribe_json($fPedido, $ped);
        registra('extra activado', ['slug' => $slug, 'clave' => $clave, 'sid' => $sid]);
        return $ped;
    });
}

/**
 * Reembolso de un extra: lo da de baja en la boda si seguía activo POR ESTE pedido. Devuelve si lo ha hecho.
 * Se llama desde lemon_reembolso, que ya tiene el cerrojo global (no se vuelve a pedir: flock no es reentrante).
 */
function extra_baja(string $slug, string $clave, string $sid): bool {
    if (!slug_valido($slug) || !extra_existe($clave) || !boda_existe($slug)) return false;
    $f = dir_boda($slug) . '/pedido.json';
    $bp = lee_json($f) ?? [];
    $e = ((array) ($bp['extras'] ?? []))[$clave] ?? null;
    if (!is_array($e) || (string) ($e['pedido'] ?? '') !== $sid || !empty($e['baja'])) return false;
    $bp['extras'][$clave]['baja'] = date('c');
    escribe_json($f, $bp);
    registra('extra dado de baja por reembolso', ['slug' => $slug, 'clave' => $clave, 'sid' => $sid]);
    return true;
}

/** URL del recibo de Lemon del extra activo (o ''), para enlazarlo desde su página del panel. */
function extra_recibo(string $slug, string $clave): string {
    $e = ((array) ((lee_json(dir_boda($slug) . '/pedido.json') ?? [])['extras'] ?? []))[$clave] ?? [];
    $sid = (string) ($e['pedido'] ?? '');
    if (!preg_match('/^ls_\d{1,15}$/', $sid)) return '';
    $r = (string) ((lee_json(dir_datos('pedidos', $sid . '.json')) ?? [])['ls']['recibo'] ?? '');
    return str_starts_with($r, 'https://') ? $r : '';
}

/**
 * Correo del extra: el soporte duradero de ESTA compra (arts. 97.1 y 98.7 TRLGDCU), calcado del de la mejora: resumen
 * propio, la casilla aceptada LITERAL con fecha y versión, y las condiciones ÍNTEGRAS al final.
 */
function texto_extra_correo(string $slug, string $clave, array $ped, array $meta): string {
    return compra_texto(datos_extra_correo($slug, $clave, $ped, $meta));
}

/** Datos del correo del extra, UNA vez (las alertas se registran una sola vez); los pintan compra_texto y compra_html. */
function datos_extra_correo(string $slug, string $clave, array $ped, array $meta): array {
    $L = textos_legales();
    $E = empresa_publica();
    $x = EXTRAS[$clave];
    $a = (array) ($meta['aceptacion'] ?? []);
    $fecha = ($a['fecha'] ?? '') !== '' ? date('d/m/Y H:i', strtotime((string) $a['fecha'])) : '';
    $vend = (string) ($a['vendedor'] ?? '') !== '' ? (string) $a['vendedor'] : (string) ($L['vendedor'] ?? '');
    $num = (int) ($ped['ls']['order_number'] ?? 0);
    $total = (int) ($ped['importe']['total'] ?? 0);
    $casilla = (string) ($a['casilla'] ?? '');
    $cfg = lee_json(dir_boda($slug) . '/config.json') ?? [];
    $borrado = fecha_larga(fecha_borrado((string) ($cfg['fecha'] ?? '')), false);
    if (($a['version'] ?? '') !== '' && $a['version'] !== ($L['version'] ?? '')) {
        registra('ALERTA correo de extra con condiciones de otra versión', ['slug' => $slug, 'aceptada' => $a['version'], 'enviada' => $L['version'] ?? '']);
    }
    if ($casilla === '') {
        registra('ALERTA extra sin casilla', ['slug' => $slug, 'clave' => $clave]);
        avisa_estudio('Extra pagado sin la casilla guardada', "Extra $clave de $slug: la aceptación no guarda la casilla. Revisar con Legal.", aviso_ref((string) ($ped['session_id'] ?? '')));
    }
    $donde = $x['panel'] !== '' ? url_boda($slug, 'panel/' . $x['panel']) : url_boda($slug, 'panel');
    $titular = '¡Hecho! Vuestra web ya tiene «' . $x['nombre'] . '».';
    return ['titular' => $titular, 'donde_intro' => 'Lo tenéis en vuestro panel:', 'donde' => $donde, 'que' => 'la compra',
        'resumen' => [
            ['Servicio', correo_linea_servicio($E)],
            ['Qué', $x['nombre'] . ' para ' . url_boda($slug) . '. ' . $x['desc']],
            ['Venta y cobro', "$vend, que es quien os lo vende (vendedor final)" . ($num > 0 ? ", pedido n.º $num" : '') . ($total > 0 ? ', ' . euros($total) . ', IVA incluido' : '')
                . ". El recibo y la factura os los envía $vend en otro correo."],
            ['Duración', 'podéis usarlo mientras la web esté alojada' . ($borrado !== '' ? ", hasta el $borrado" : '') . '. El extra no alarga el alojamiento.'],
            // Condiciones, apartado 5 ter (Legal #133): servicio (103.a), desistible en 14 días pagando lo prestado (108.3) porque
            // la pareja pidió que empezara ya. La base del cálculo es la misma duración que declara la línea anterior.
            ['Desistimiento', 'podéis desistir de este extra en los 14 días siguientes a la compra, escribiéndonos a ' . $E['email'] . '. '
                . 'Como pedisteis que se activara ya, pagaréis la parte proporcional a lo ya prestado, por días entre la compra y la fecha de borrado de la web'
                . ($borrado !== '' ? " ($borrado)" : '') . "; el resto os lo devuelve $vend por el mismo medio de pago en un máximo de 14 días. "
                . 'Al desistir, el extra se desactiva y la web sigue publicada (apartado 5 ter).'],
            ['Garantía', 'tiene que funcionar como se describe durante todo el alojamiento; si algo falla, lo arreglamos sin coste (apartado 9).'],
            ['Condiciones del servicio', 'van completas al final de este correo' . (($a['version'] ?? '') !== '' ? ' (versión ' . $a['version'] . ')' : '')
                . '. También están en ' . url_creador('condiciones') . ' (esa página enseña siempre la versión vigente; la vuestra es la de este correo).'],
            ['Dudas y reclamaciones', (string) $E['email']],
        ],
        'marcasteis' => 'Antes de pagar' . ($fecha !== '' ? " ($fecha, hora de España)" : '') . ' marcasteis lo siguiente'
            . (($a['version'] ?? '') !== '' ? ' (condiciones, versión ' . $a['version'] . ')' : '') . ':',
        'casilla' => $casilla,
        'condiciones' => documento_legal('condiciones', 'Condiciones del servicio'),
        'hero' => ['kicker' => 'Extra activado', 'titulo' => ['Ya tenéis ', $x['nombre'], ''], 'texto' => $x['desc'],
            'boton' => ['Abrirlo en el panel', $donde], 'enlace' => $donde]];
}

/**
 * Página del panel de un extra que la boda NO tiene: qué hace, cuánto cuesta (precio_extra_cent, nunca a mano) y,
 * si se vende ahora, la casilla y el botón de compra. La compra la hace assets/js/panel.js contra /panel/extra.
 */
function extra_presentacion(string $slug, string $clave, string $queHace): string {
    $x = EXTRAS[$clave];
    $L = textos_legales();
    $o = '<section class="section extra-oferta"><h2 class="panel-h2">' . h($x['nombre']) . '</h2>' . $queHace
        . '<p class="extra-precio"><b>' . h(euros(precio_extra_cent($clave))) . '</b> <span class="muted">IVA incluido, pago único</span></p>';
    if (($_GET['compra'] ?? '') === '1') {
        $o .= '<p class="panel-aviso">Estamos confirmando el pago. Recargad esta página en un minuto; os llegará también un correo.</p>';
    }
    if (extra_disponible($slug, $clave)) {
        $o .= '<form class="stack extra-compra" data-extra-compra>'
            . '<input type="hidden" name="csrf" value="' . h(panel_csrf()) . '"><input type="hidden" name="clave" value="' . h($clave) . '">'
            . '<label class="extra-check"><input type="checkbox" name="acepto_extra" value="si" required> <span>' . h(texto_extra()) . ' '
            // Los enlaces van junto a la casilla porque ella solo nombra la marca (mismo criterio que check_mejora)
            . '<a href="' . h(url_creador('condiciones')) . '" target="_blank" rel="noopener">Condiciones del servicio</a> · '
            . '<a href="' . h((string) ($L['vendedor_terminos'] ?? '')) . '" target="_blank" rel="noopener">Condiciones de compra de ' . h((string) ($L['vendedor'] ?? '')) . '</a></span></label>'
            . '<div class="form-actions"><button type="submit" class="btn">Comprar por ' . h(euros(precio_extra_cent($clave))) . '</button></div>'
            . '<p class="form-msg" data-extra-msg hidden></p>'
            . '<p class="panel-nota">' . h(nota_pago()) . '</p></form>';
    } else {
        $b = extra_bloqueo($slug, $clave);
        $o .= '<p class="panel-nota">' . h($b === 'archivada' ? 'Esta web ya está archivada.'
            : 'Este extra todavía no se puede comprar desde aquí. Escribidnos a ' . empresa_publica()['email'] . ' y os ayudamos.') . '</p>';
    }
    return $o . '</section>';
}
