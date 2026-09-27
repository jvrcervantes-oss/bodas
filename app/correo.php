<?php
// Emails del producto, por SMTP autenticado desde hola@bodaenlace.com (ver remitente()). El de bienvenida es además el "soporte
// duradero" que exige la ley: desde el 27-sep (Legal, BOD-6) lleva las condiciones ÍNTEGRAS en el CUERPO,
// sin adjunto (los adjuntos lo mandaban a spam), y repite la petición de ejecución inmediata con la
// pérdida del desistimiento (art. 98.7 TRLGDCU). Si se quita algo de eso, cae la excepción.

declare(strict_types=1);

// TODO el correo del negocio sale de hola@bodaenlace.com (owner, 27-sep-2026: «es el único email para
// todo lo que haga este negocio»), por SMTP autenticado con TLS: el mismo buzón que usa El Padrino. El
// mail() del hosting rechazaba los envíos (medido el 27-sep: «correo no aceptado», BOD-6).
function remitente(): string { return (string) secreto('smtp_usuario', secreto('mail_from', 'hola@' . BASE_DOMAIN)); }

/** Mensaje MIME completo (cabeceras + cuerpo) de texto plano con adjuntos HTML. Puro: se prueba sin red. */
function mensaje_mime(string $from, string $para, string $asunto, string $texto, array $adjuntos): string {
    $b = 'b' . bin2hex(random_bytes(12));
    $cab = "Date: " . date(DATE_RFC2822) . "\r\nFrom: " . marca_comercial_correo() . " <$from>\r\nTo: <$para>\r\nReply-To: $from\r\n"
        . "Subject: =?UTF-8?B?" . base64_encode($asunto) . "?=\r\nMessage-ID: <" . bin2hex(random_bytes(12)) . '@' . BASE_DOMAIN . ">\r\nMIME-Version: 1.0\r\n";
    // Texto en base64: sin líneas de más de 998 bytes ni puntos a principio de línea que corregir
    $parte = "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode(str_replace("\n", "\r\n", str_replace("\r\n", "\n", $texto))));
    if (!$adjuntos) return $cab . $parte;
    $m = $cab . "Content-Type: multipart/mixed; boundary=\"$b\"\r\n\r\n--$b\r\n" . $parte;
    foreach ($adjuntos as $nombre => $html) {
        $nombre = preg_replace('/[^A-Za-z0-9._-]/', '', (string) $nombre);
        $m .= "--$b\r\nContent-Type: text/html; charset=UTF-8; name=\"$nombre\"\r\nContent-Disposition: attachment; filename=\"$nombre\"\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode((string) $html));
    }
    return $m . "--$b--\r\n";
}
/** Nombre que ve el destinatario en «De»: la marca fija, no la de marca.json (Legal #105). */
function marca_comercial_correo(): string { return 'BodaEnlace'; }

/**
 * SMTP con TLS implícito (465) y AUTH LOGIN, sin librerías (no hay composer). Devuelve '' si el
 * servidor aceptó el mensaje, o el motivo del fallo (sin la contraseña).
 */
function smtp_envia(string $para, string $mime): string {
    $host = (string) secreto('smtp_host');
    $usuario = (string) secreto('smtp_usuario');
    $clave = (string) secreto('smtp_clave');
    if ($host === '' || $usuario === '' || $clave === '') return 'SMTP sin configurar';
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host]]);
    $s = @stream_socket_client('ssl://' . $host . ':' . (int) secreto('smtp_puerto', 465), $errno, $errstr, 20, STREAM_CLIENT_CONNECT, $ctx);
    if (!$s) return "conexión: $errstr";
    stream_set_timeout($s, 20);
    $lee = function () use ($s): string {
        $r = '';
        while (($l = fgets($s, 1024)) !== false) { $r .= $l; if (strlen($l) < 4 || $l[3] === ' ') break; }
        return $r;
    };
    $orden = function (string $cmd, string $espera) use ($s, $lee): string {
        if ($cmd !== '') fwrite($s, $cmd . "\r\n");
        $r = $lee();
        return strpos($r, $espera) === 0 ? '' : trim(substr($r, 0, 200));
    };
    // Punto al principio de una línea: se dobla (RFC 5321 §4.5.2). Con base64 no aparece, pero por si acaso
    $datos = preg_replace('/^\./m', '..', $mime);
    $pasos = [['', '220'], ['EHLO ' . BASE_DOMAIN, '250'], ['AUTH LOGIN', '334'], [base64_encode($usuario), '334'], [base64_encode($clave), '235'],
        ['MAIL FROM:<' . $usuario . '>', '250'], ['RCPT TO:<' . $para . '>', '25'], ['DATA', '354'], [rtrim($datos, "\r\n") . "\r\n.", '250']];
    $err = '';
    foreach ($pasos as $i => [$cmd, $esp]) {
        $err = $orden($cmd, $esp);
        if ($err !== '') { $err = 'paso ' . $i . ': ' . $err; break; }
    }
    @fwrite($s, "QUIT\r\n");
    fclose($s);
    return $err;
}

/** Envía texto plano + adjuntos HTML. Devuelve true si el servidor lo aceptó. Nunca lanza. */
function envia_correo(string $para, string $asunto, string $texto, array $adjuntos = []): bool {
    if (!filter_var($para, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n<>]/', $para)) return false;
    if (defined('CORREO_A_FICHERO')) {   // desarrollo local y pruebas: se deja en disco en vez de enviar
        if (defined('CORREO_FALLA')) return false;   // pruebas: servidor de correo caído
        asegura_dir(dir_datos('correos'));
        file_put_contents(dir_datos('correos', date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.txt'),
            "Para: $para\nAsunto: $asunto\n\n$texto\n\nAdjuntos: " . implode(', ', array_keys($adjuntos)));
        return !defined('CORREO_FALLA');
    }
    try {
        $err = smtp_envia($para, mensaje_mime(remitente(), $para, $asunto, $texto, $adjuntos));
    } catch (Throwable $e) {
        $err = 'excepción: ' . $e->getMessage();
    }
    if ($err !== '') registra('correo no aceptado', ['para' => $para, 'asunto' => $asunto, 'motivo' => $err]);
    return $err === '';
}

/**
 * Envío que, si falla, queda en DATA_DIR/cola_avisos/ para que el cron lo reintente. Un aviso que no sale
 * nunca rompe el alta, pero tampoco se pierde en silencio (owner, 27-sep-2026).
 */
function envia_o_encola(array $aviso): bool {
    $ok = aviso_envia($aviso);
    if (!$ok) encola_aviso($aviso);
    return $ok;
}
function encola_aviso(array $aviso): void {
    $aviso += ['intentos' => 1, 'creado' => date('c')];
    escribe_json(dir_datos('cola_avisos', date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.json'), $aviso);
    registra('aviso encolado para reintento', ['tipo' => $aviso['tipo'] ?? '', 'asunto' => $aviso['asunto'] ?? '']);
}
/** Marca del enlace del panel en una bienvenida encolada: el enlace real NUNCA se guarda en disco. */
const ENLACE_PANEL_MARCA = '{{ENLACE_PANEL}}';
function aviso_envia(array $a): bool {
    if (($a['tipo'] ?? '') === 'telegram') return telegram_envia((string) ($a['texto'] ?? ''));
    $texto = (string) ($a['texto'] ?? '');
    // Bienvenida reintentada: el enlace de un solo uso se genera AHORA (en disco solo va su sha256,
    // panel_auth.php) y con sus 14 días de vida enteros
    $slug = (string) ($a['enlace_slug'] ?? '');
    if ($slug !== '' && strpos($texto, ENLACE_PANEL_MARCA) !== false) {
        if (!slug_valido($slug) || !boda_existe($slug)) return true;   // la boda ya no existe: nada que mandar
        $texto = str_replace(ENLACE_PANEL_MARCA, panel_nuevo_enlace($slug), $texto);
    }
    return envia_correo((string) ($a['para'] ?? ''), (string) ($a['asunto'] ?? ''), $texto, (array) ($a['adjuntos'] ?? []));
}
/** Cron: reintenta la cola. Tras 20 intentos (≈ 20 días con el cron diario) se aparta y queda en el log. */
function cola_avisos_reintenta(): array {
    $n = ['enviados' => 0, 'pendientes' => 0, 'descartados' => 0];
    foreach (glob(dir_datos('cola_avisos', '*.json')) ?: [] as $f) {
        $a = lee_json($f);
        if (!$a) { @unlink($f); continue; }
        if (aviso_envia($a)) { @unlink($f); $n['enviados']++; continue; }
        $a['intentos'] = (int) ($a['intentos'] ?? 1) + 1;
        if ($a['intentos'] >= 20) {
            registra('ALERTA aviso descartado tras 20 intentos', ['tipo' => $a['tipo'] ?? '', 'asunto' => $a['asunto'] ?? '']);
            @unlink($f);
            $n['descartados']++;
            continue;
        }
        escribe_json($f, $a);
        $n['pendientes']++;
    }
    return $n;
}

/** Telegram del owner con el bot del Padrino: SOLO sendMessage (el getUpdates es del Padrino). */
function telegram_envia(string $texto): bool {
    $token = (string) secreto('telegram_token');
    $chat = (string) secreto('telegram_chat');
    if ($token === '' || $chat === '') { registra('telegram sin configurar: aviso solo por correo'); return true; }   // no se encola
    if (defined('CORREO_A_FICHERO')) {
        if (defined('CORREO_FALLA')) return false;
        asegura_dir(dir_datos('telegram'));
        file_put_contents(dir_datos('telegram', date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.txt'), $texto);
        return true;
    }
    $ch = curl_init('https://api.telegram.org/bot' . $token . '/sendMessage');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15,
        CURLOPT_POSTFIELDS => http_build_query(['chat_id' => $chat, 'text' => mb_substr($texto, 0, 3500, 'UTF-8'), 'disable_web_page_preview' => 'true'])]);
    $raw = curl_exec($ch);
    $st = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $ok = $st === 200 && (json_decode((string) $raw, true)['ok'] ?? false) === true;
    // Nunca se registra la URL: lleva el token del bot
    if (!$ok) registra('telegram no aceptado', ['status' => $st]);
    return $ok;
}

function documento_legal(string $cual, string $titulo): string {
    $E = empresa();
    ob_start();
    include __DIR__ . '/legal/' . $cual . '.php';
    $cuerpo = ob_get_clean();
    return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>' . h($titulo) . '</title>'
        . '<style>body{font:15px/1.6 system-ui,sans-serif;max-width:720px;margin:40px auto;padding:0 16px;color:#191C1D}</style></head><body>'
        . $cuerpo . '</body></html>';
}

/**
 * Cómo se pagó: 'factura' (Stripe, factura BODA- propia), 'lemon' (Lemon Squeezy es el vendedor y
 * factura él) o 'regalo' (código de cortesía). Se decide por la pasarela, NUNCA por «no hay
 * factura»: una venta por LS no lleva factura nuestra y caería en el texto del regalo (Administración #109).
 */
function tipo_pago(array $ped): string {
    if (($ped['pasarela'] ?? '') === 'lemon') return 'lemon';
    return ($ped['factura'] ?? '') !== '' ? 'factura' : 'regalo';
}

/**
 * HTML de un texto legal a texto plano, para el cuerpo del correo. Puro: se prueba sin red.
 * Los legales solo usan h1/h2/p/ul/li/a/strong/em: títulos en mayúsculas, viñetas con guion y
 * cada enlace con su dirección entre paréntesis (en texto plano se perdería).
 */
function legal_a_texto(string $html): string {
    if (preg_match('~<body[^>]*>(.*)</body>~si', $html, $m)) $html = $m[1];
    $html = str_replace("\r", "", $html);
    $t = (string) preg_replace('~<a\s[^>]*href="([^"]*)"[^>]*>(.*?)</a>~si', '$2 ($1)', $html);
    $t = (string) preg_replace_callback('~<h[12][^>]*>(.*?)</h[12]>~si', fn($m) => "\n\n" . mb_strtoupper(trim(strip_tags($m[1])), 'UTF-8') . "\n", $t);
    $t = (string) preg_replace('~</li>\s*~i', '', $t);
    $t = (string) preg_replace('~<li[^>]*>~i', "\n- ", $t);
    $t = (string) preg_replace('~</(p|ul)>~i', "\n", $t);
    $t = html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = (string) preg_replace("~[ \t]+~", ' ', $t);
    $t = (string) preg_replace("~ *\n *~", "\n", $t);
    return trim((string) preg_replace("~\n{3,}~", "\n\n", $t));
}

/**
 * Cuerpo del correo de bienvenida (separado del envío para poder probarlo).
 *
 * SOPORTE DURADERO (Legal, 27-sep-2026, BOD-6): este cuerpo ES la confirmación del art. 98.7 TRLGDCU.
 * Sin adjuntos: cualquier adjunto mandaba el correo a spam (6 variantes probadas). Un ENLACE a las
 * condiciones no es soporte duradero (TJUE C-49/11, Content Services), así que todo va DENTRO del
 * cuerpo: 1) el texto literal de las casillas con fecha y versión (sin él, la excepción del 103.m no
 * vale y el desistimiento sigue vivo); 2) la información del art. 97.1 (quién presta, quién vende,
 * qué, cuánto, hasta cuándo, garantía, reclamaciones); 3) las condiciones íntegras en texto plano.
 * El enlace a /condiciones es solo una comodidad. Si se quita algo de esto, cae la excepción.
 */
function texto_bienvenida(array $ped, array $cfg, string $enlace): string {
    $L = textos_legales();
    $E = empresa();
    $url = url_boda($ped['slug']);
    $borrado = fecha_larga(fecha_borrado((string) ($cfg['fecha'] ?? '')), false);
    $acept = (array) ($ped['aceptacion'] ?? []);
    $fecha = (string) ($acept['fecha'] ?? '');
    $tipo = tipo_pago($ped);
    $vend = (string) ($L['vendedor'] ?? '');
    $ls = (array) ($ped['ls'] ?? []);
    $pack = ($ped['atelier'] ?? '') !== '' ? 'Pack Atelier' : 'Pack Esencial';
    $total = (int) ($ped['importe']['total'] ?? 0);
    $num = (int) ($ls['order_number'] ?? 0);
    // Las condiciones que van en el cuerpo son las vigentes al enviar: si la versión cambió entre la
    // aceptación y el pago, se deja rastro para revisarlo a mano (revisor, 27-sep)
    if (($acept['version'] ?? '') !== '' && $acept['version'] !== ($L['version'] ?? '')) {
        registra('ALERTA bienvenida con condiciones de otra versión', ['slug' => (string) $ped['slug'], 'aceptada' => $acept['version'], 'enviada' => $L['version'] ?? '']);
    }

    return "¡Vuestra web de boda ya está publicada!\n\n"
        . "Dirección: $url\n\n"
        . "Para entrar en vuestro panel (respuestas de invitados, Excel, editar la web y descargar el ZIP), elegid vuestra contraseña con este enlace. Sirve una sola vez y caduca en 14 días:\n$enlace\n\n"
        . "La web y las respuestas de vuestros invitados se mantienen hasta el $borrado. Ese día se borran las respuestas y la web pasa a una página de agradecimiento. Exportad el Excel antes si queréis conservarlas.\n\n"
        . "Guardad este correo: es la confirmación de vuestro contrato.\n\n"
        . "RESUMEN DE LO CONTRATADO\n"
        . '- Servicio: ' . marca() . ', un producto de AxisWorks, que presta ' . $E['titular'] . ($E['nif'] !== '' ? ' (NIF ' . $E['nif'] . ')' : '') . ($E['domicilio'] !== '' ? ', ' . $E['domicilio'] : '') . ".\n"
        . "- Qué: $pack, web de boda publicada en $url, alojada hasta el $borrado.\n"
        . ['factura' => '- Pago: ' . euros($total) . ", IVA incluido. Factura: {$ped['factura']} (adjunta).\n",
            'lemon' => "- Venta y cobro: $vend, que es quien os la vende (vendedor final)" . ($num > 0 ? ", pedido n.º $num" : '') . ($total > 0 ? ', ' . euros($total) . ', IVA incluido' : '') . ". El recibo y la factura os los envía $vend en otro correo.\n",
            'regalo' => '- Pago: ninguno. Esta web os la regala ' . marca() . ".\n"][$tipo]
        . ($tipo !== 'regalo' ? "- Desistimiento: sobre la creación y publicación de la web lo perdisteis al publicarse, porque así lo pedisteis antes de pagar (casilla de abajo). Del alojamiento podéis desistir hasta 14 días después de la compra pagando la parte ya prestada (apartado 8 de las condiciones). Después, no hay reembolsos por cambio de opinión.\n" : '')
        . "- Garantía: la web tiene que funcionar como se describe durante todo el alojamiento; si algo falla, lo arreglamos sin coste (apartado 9).\n"
        . "- Dudas y reclamaciones: {$E['email']}\n\n"
        . ($tipo !== 'regalo' ? 'Antes de pagar' : 'Al publicar') . ($fecha !== '' ? ' (' . date('d/m/Y H:i', strtotime($fecha)) . ')' : '')
        . ' marcasteis lo siguiente' . (($acept['version'] ?? '') !== '' ? ' (condiciones, versión ' . $acept['version'] . ')' : '') . ":\n"
        . '«' . (string) ($acept['condiciones'] ?? '') . "»\n"
        . (($acept['desistimiento'] ?? '') !== '' ? '«' . $acept['desistimiento'] . "»\n" : '') . "\n"
        . 'Las condiciones completas van al final de este correo. También están en ' . url_creador('condiciones') . " (esa página enseña siempre la versión vigente; la vuestra es la de este correo).\n\n"
        . "Cualquier duda: {$E['email']}\n\n" . marca_comercial_correo() . "\n\n"
        . str_repeat('=', 40) . "\n\n"
        . legal_a_texto(documento_legal('condiciones', 'Condiciones del servicio')) . "\n";
}

function correo_bienvenida(array $ped, array $cfg, string $enlace): void {
    $url = url_boda($ped['slug']);
    $texto = texto_bienvenida($ped, $cfg, $enlace);
    // Sin adjunto de las condiciones: van enteras en el cuerpo (Legal, 27-sep, BOD-6). Solo una venta
    // por Stripe, hoy apagada, lleva su factura BODA- adjunta.
    $adjuntos = [];
    if (($ped['factura'] ?? '') !== '') $adjuntos[$ped['factura'] . '.html'] = (string) render_factura($ped['factura']);
    $aviso = ['tipo' => 'correo', 'para' => $ped['email'], 'asunto' => 'Vuestra web de boda: ' . preg_replace('~^https?://~', '', rtrim($url, '/')), 'texto' => $texto, 'adjuntos' => $adjuntos];
    if (aviso_envia($aviso)) return;
    // A la cola SIN el enlace del panel (revisor, 27-sep): se regenera al reintentar
    encola_aviso(['texto' => texto_bienvenida($ped, $cfg, ENLACE_PANEL_MARCA), 'enlace_slug' => (string) $ped['slug']] + $aviso);
}

/** Resumen de una venta para el owner: pedido y pack, nunca datos de invitados. */
function texto_venta(array $ped): string {
    $ls = (array) ($ped['ls'] ?? []);
    return "Web publicada: " . url_boda((string) $ped['slug']) . "\n"
        . 'Pack: ' . (($ped['atelier'] ?? '') !== '' ? 'Atelier (' . (ATELIER[$ped['atelier']]['nombre'] ?? $ped['atelier']) . ')' : 'Esencial') . "\n"
        . 'Importe: ' . euros((int) ($ped['importe']['total'] ?? 0)) . ' (IVA ' . euros((int) ($ped['importe']['iva'] ?? 0)) . ")\n"
        . 'Pago: ' . (($ped['pasarela'] ?? '') === 'lemon' ? 'Lemon Squeezy #' . ($ls['order_number'] ?? '') . (!empty($ls['test']) ? ' (PRUEBA, modo test)' : '') : (string) ($ped['factura'] ?? '')) . "\n"
        . 'Comprador: ' . (string) ($ped['email'] ?? '') . "\n"
        . 'Panel del estudio: ' . url_creador('estudio');
}

function correo_enlace_panel(string $slug, string $email, string $enlace): void {
    envia_correo($email, 'Acceso a vuestro panel de boda', "Hola:\n\nAlguien ha pedido un enlace para elegir una nueva contraseña del panel de " . url_boda($slug) . ".\n\n$enlace\n\nSirve una sola vez y caduca en 14 días. Si no lo habéis pedido vosotros, ignorad este email: vuestra contraseña actual sigue funcionando.\n\n" . marca_comercial_correo());
}

/**
 * Aviso al owner por correo (buzón de la marca, con todo el detalle) y por Telegram. Si alguno falla, a la
 * cola del cron. $titulo NUNCA lleva el slug: el nombre de la web suele ser el de la pareja (dato personal) y
 * Telegram es un tercero (BOD-17). A Telegram solo va el título, una referencia neutra y dónde mirar.
 */
function avisa_estudio(string $titulo, string $texto, string $ref = ''): void {
    registra('AVISO ESTUDIO: ' . $titulo, ['ref' => $ref, 'texto' => $texto]);
    envia_o_encola(['tipo' => 'correo', 'para' => empresa()['email'], 'asunto' => '[BodaEnlace] ' . $titulo . ($ref !== '' ? ' · ' . $ref : ''), 'texto' => $texto]);
    envia_o_encola(['tipo' => 'telegram', 'texto' => 'BodaEnlace · ' . $titulo . ($ref !== '' ? "\n" . $ref : '')
        . "\n\nDetalle en el correo de " . empresa()['email'] . ' y en ' . url_creador('estudio')]);
}
/** Referencia neutra de un pedido para los avisos: el nº de LS, o un resumen del id (el de un regalo lleva el token de /listo). */
function aviso_ref(string $sid): string {
    return preg_match('/^ls_(\d{1,15})$/', $sid, $m) ? 'Pedido LS ' . $m[1] : 'Pedido ' . substr(hash('sha256', $sid), 0, 8);
}
