<?php
// Extras de pago de una boda ya publicada (álbum, idiomas y dominio, por fases; hoy NINGUNO a la venta). El plano de
// mesas nació aquí como el primero y desde el 27-sep-2026 va incluido en todos los packs (EXTRAS['mesas']['incluido']):
// extra_activo() lo abre para toda web en pie sin mirar compras. Encargo
// encargos/20260927_bodas_servicios_extra.md (repo del estudio), revisión previa #133.
//
// POR QUÉ ASÍ — la compra y la activación van por el camino común de app/servicios.php, el mismo que la «mejora a
// Atelier» (revisiones #109 y #133; unificado en BOD-23 para que el álbum, los idiomas y el dominio no lo copien):
//  · La lista de extras, su precio y sus límites viven en app/padrino.php (EXTRAS, precio_extra_cent): una
//    sola fuente, la misma que la de los packs. Aquí nunca se escribe un precio.
//  · Del navegador solo llega la clave, que se comprueba contra la lista cerrada (fuera de ella → 400). El webhook
//    decide qué activar leyendo extras/<token>.json, NUNCA `custom_data` (Seguridad #133).
//  · `pedido.json.extras.<clave> = {desde, pedido}` lo escribe SOLO lemon_servicio() y lo da de baja SOLO el
//    reembolso (extra_baja). panel_guardar escribe config.json y no puede tocarlo. Cada página y cada POST de
//    un extra lo comprueba en el servidor (extra_activo).
//  · Lo propio de un extra está en su ficha (servicio_pago): casilla `check_extra` (servicio desistible, 5 ter),
//    la clave del custom_data comprobada contra la del servidor y el «reembolsado entre el corte y el reintento».

declare(strict_types=1);

/** Casilla que acepta la pareja al comprar un extra. SOLO la de Legal (`check_extra`); sin ella no se vende. */
function texto_extra(): string { return (string) (textos_legales()['check_extra'] ?? ''); }

/**
 * ¿Tiene la boda este extra activo ahora? Lectura del servidor: la única que vale para abrir su página o su POST.
 * Un extra incluido en los packs (plano de mesas, owner 27-sep-2026) está activo en TODA web en pie y no archivada,
 * pagada o regalada, sin mirar pedido.json: así una compra de prueba anterior, o su reembolso, no lo enciende ni lo apaga.
 */
function extra_activo(string $slug, string $clave): bool {
    if (!slug_valido($slug)) return false;
    if (extra_incluido($clave)) return boda_existe($slug) && ((lee_json(dir_boda($slug) . '/config.json') ?? [])['_estado'] ?? '') !== 'archivada';
    return extra_activo_en(lee_json(dir_boda($slug) . '/pedido.json') ?? [], $clave);
}

/**
 * '' si esta boda puede recibir el extra; si no, el motivo. Mismo criterio que mejora_bloqueo(): webs pagadas o
 * regaladas que siguen en pie (una web regalada con código también compra extras, Administración #133).
 */
function extra_bloqueo(string $slug, string $clave): string {
    if (!extra_existe($clave)) return 'sin-extra';
    if (extra_incluido($clave)) return 'incluido';   // va en todos los packs: no se compra
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
    return servicio_venta_abierta(texto_extra());
}

/** ¿Se puede ofrecer la compra de este extra en este panel ahora? */
function extra_disponible(string $slug, string $clave): bool {
    return extra_existe($clave) && EXTRAS[$clave]['venta'] && extra_venta_abierta() && precio_extra_cent($clave) > 0 && extra_bloqueo($slug, $clave) === '';
}

/** Fichero de la marca «hay un pago de este extra abierto» (uno por boda y extra, vive lo que el checkout). */
function extra_fichero_abierta(string $slug, string $clave): string { return servicio_fichero_abierta(servicio_pago('extra', $clave), $slug); }

/** POST /panel/extra (sesión del panel ya exigida por rutas_panel; CSRF aquí). Compra por el camino común (app/servicios.php). */
function panel_extra(string $slug, string $metodo): void {
    if ($metodo !== 'POST') json_response(['ok' => false], 405);
    if (!panel_csrf_ok()) json_response(['ok' => false, 'error' => 'La sesión ha caducado. Recarga la página.'], 403);
    $clave = (string) ($_POST['clave'] ?? '');
    if (!extra_existe($clave)) json_response(['ok' => false, 'error' => 'Ese extra no existe.'], 400);
    if (!EXTRAS[$clave]['venta']) json_response(['ok' => false, 'error' => 'Este extra todavía no está a la venta.'], 409);
    servicio_compra(servicio_pago('extra', $clave), $slug);
}

/** Extra cobrado: valida contra su pedido congelado y lo activa en la boda (camino común, app/servicios.php). */
function lemon_extra(string $id, array $o, array $custom): array { return lemon_servicio('extra', $id, $o, $custom); }

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
    return recibo_ls((string) ((((array) ((lee_json(dir_boda($slug) . '/pedido.json') ?? [])['extras'] ?? []))[$clave] ?? [])['pedido'] ?? ''));
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
 * SIN LLAMADOR hoy (el plano de mesas era el único y pasó a ir incluido): se conserva como la cáscara de venta del próximo
 * extra (álbum, F2), junto con panel_extra y el [data-extra-compra] de assets/js/panel.js. Si el álbum no la usa, se borra.
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
