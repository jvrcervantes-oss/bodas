<?php
// Plantilla HTML de los correos a la pareja (diseño aprobado por el owner, artifact «Correos de BodaEnlace», 27-sep-2026).
// Va SIEMPRE junto a su versión en texto plano (multipart/alternative, ver mensaje_mime): la plana es la que ya
// existía y no cambia; esta es la misma información con diseño. Una sola función, correo_html(), para los cuatro.
//
// Reglas de correo (no de navegador): tablas, estilos en línea, 600 px, sin imágenes ni fuentes web (Georgia/Arial),
// sin recursos externos. El logo es texto + un SVG que Gmail y Outlook quitan: el texto basta solo. El modo oscuro y
// el móvil van por clases con !important en el <style> (lo único que gana a un estilo en línea); Gmail ignora el
// prefers-color-scheme y oscurece por su cuenta. Todo texto variable pasa por h(); el único HTML de confianza que
// entra sin escapar son las condiciones, que salen de nuestra propia plantilla legal (correo_legal_html).
//
// Contraste: tres colores del artifact se oscurecieron un punto para llegar a 4,5:1 sobre el fondo (botón #9c5f5c,
// antetítulo #7a6444, letra pequeña #6f675f). La composición y la paleta son las del artifact.

declare(strict_types=1);

const CORREO_F_SERIF = "Georgia,'Times New Roman',serif";
const CORREO_F_SANS = 'Arial,Helvetica,sans-serif';

/**
 * Documento HTML completo de un correo. $bloques, en orden (claves opcionales salvo 'tipo'):
 *  hero   → ok (bool, marca ✓), kicker, titulo [antes, destacado, después], texto, url, boton [texto, href], nota, enlace
 *  pasos  → items (string, o lista de [texto, negrita])
 *  caja   → titulo, filas [[etiqueta, valor], ...]
 *  aviso  → fuerte, texto
 *  citas  → items (textos literales)
 *  texto  → texto
 *  legal  → html (salida de correo_legal_html)
 * $previa es el texto que el buzón enseña junto al asunto.
 */
function correo_html(array $bloques, string $previa = ''): string {
    $filas = '<tr><td class="m-cab" align="center" bgcolor="#fcf4f2" style="padding:30px 24px 22px;background:#fcf4f2;text-align:center;border-radius:14px 14px 0 0">' . correo_logo() . '</td></tr>';
    foreach ($bloques as $b) $filas .= correo_bloque($b);
    $E = empresa_publica();
    $mail = (string) ($E['email'] ?? '');
    $filas .= '<tr><td class="m-pad m-pie" align="center" style="padding:22px 32px 30px;text-align:center;font-family:' . CORREO_F_SANS . ';font-size:12px;line-height:1.6;color:#6f675f">'
        . ($mail !== '' ? '¿Dudas? Respondednos a este correo o escribid a <a class="m-enl" href="mailto:' . h($mail) . '" style="color:#7e4744">' . h($mail) . '</a><br>' : '')
        . h(marca_comercial_correo()) . ' · un producto de AxisWorks</td></tr>';

    return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1"><meta name="x-apple-disable-message-reformatting">'
        . '<meta name="color-scheme" content="light dark"><meta name="supported-color-schemes" content="light dark">'
        . '<title>' . h(marca_comercial_correo()) . '</title><style>' . correo_css() . '</style></head>'
        . '<body class="m-fondo" style="margin:0;padding:0;background:#efe8e0;-webkit-text-size-adjust:100%">'
        . ($previa !== '' ? '<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;color:#efe8e0">' . h($previa) . '</div>' : '')
        . '<table role="presentation" class="m-fondo" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#efe8e0" style="background:#efe8e0">'
        . '<tr><td align="center" class="m-marco" style="padding:24px 12px">'
        . '<table role="presentation" class="m-ext" width="600" cellpadding="0" cellspacing="0" border="0" bgcolor="#fbf8f5" style="width:600px;max-width:600px;background:#fbf8f5;border-radius:14px;color:#2a2624">'
        . $filas . '</table></td></tr></table></body></html>';
}

function correo_css(): string {
    return 'body,table,td{-ms-text-size-adjust:100%}table{border-collapse:collapse}a{text-decoration:underline}'
        . '@media (max-width:620px){'
        . '.m-marco{padding:12px 8px!important}.m-ext{width:100%!important}'
        . '.m-pad{padding-left:20px!important;padding-right:20px!important}.m-h1{font-size:26px!important}'
        . '.m-et,.m-val{display:block!important;width:auto!important;text-align:left!important}.m-et{padding-bottom:0!important}.m-val{padding-top:2px!important;border-top:0!important}}'
        . '@media (prefers-color-scheme:dark){'
        . '.m-fondo{background:#1c1a19!important}.m-ext{background:#22201f!important;color:#efe8e0!important}.m-cab{background:#2b2624!important}'
        . '.m-tinta{color:#f5efe9!important}.m-tinta2{color:#cfc6bd!important}.m-rosa{color:#e0a39f!important}.m-kick{color:#c9ad7e!important}'
        . '.m-caja,.m-url{background:#2b2826!important;border-color:#3a3533!important}.m-url,.m-url a{color:#e9b9b5!important}'
        . '.m-linea{border-color:#3a3533!important}.m-aviso{background:#2f2826!important;color:#cfc6bd!important}'
        . '.m-num{background:#3a2c2b!important;color:#e9b9b5!important}.m-ok{background:#26352c!important;color:#9cc7ab!important}'
        . '.m-legal,.m-legal p,.m-legal li,.m-pie,.m-nota,.m-et{color:#a39a91!important}.m-legal h1,.m-legal h2{color:#cfc6bd!important}'
        . '.m-enl,.m-legal a{color:#e9b9b5!important}.m-sep{border-color:#3a3533!important}}';
}

/** Logo: dos anillos en SVG + el nombre como texto hermano (si el cliente quita el SVG, queda el texto). */
function correo_logo(): string {
    $dom = BASE_DOMAIN;
    $p = strpos($dom, '.');
    $nombre = $p === false ? $dom : substr($dom, 0, $p);
    $tld = $p === false ? '' : substr($dom, $p);
    return '<a href="' . h(url_creador()) . '" class="m-tinta" style="font-family:' . CORREO_F_SERIF . ';font-size:18px;color:#2a2624;text-decoration:none">'
        . '<svg width="26" height="23" viewBox="0 0 26 23" aria-hidden="true" style="vertical-align:-5px;margin-right:6px">'
        . '<circle cx="9" cy="14" r="7.5" fill="none" stroke="#a86b68" stroke-width="1.6"/><circle cx="17" cy="14" r="7.5" fill="none" stroke="#a86b68" stroke-width="1.6"/>'
        . '<path d="M13 1.5l2 2.5-2 2.5-2-2.5z" fill="#a86b68"/></svg>'
        . h($nombre) . '<em class="m-rosa" style="color:#a86b68;font-style:italic">' . h($tld) . '</em></a>';
}

/** Texto escapado con las direcciones https:// convertidas en enlace (las de nuestro propio dominio y las de Legal). */
function correo_enlaza(string $t): string {
    return (string) preg_replace_callback('~https://[^\s<>"()]*[^\s<>"().,;:]~', fn($m) => '<a class="m-enl" href="' . $m[0] . '" style="color:#7e4744;word-break:break-word;overflow-wrap:anywhere">' . $m[0] . '</a>', h($t));
}

function correo_bloque(array $b): string {
    $sans = 'font-family:' . CORREO_F_SANS . ';';
    switch ($b['tipo'] ?? '') {
        case 'hero':
            $o = '';
            if (!empty($b['ok'])) {
                $o .= '<table role="presentation" align="center" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto 10px"><tr>'
                    . '<td class="m-ok" width="44" height="44" align="center" valign="middle" bgcolor="#e3ede6" style="width:44px;height:44px;border-radius:22px;background:#e3ede6;color:#4f7a60;' . $sans . 'font-size:20px;font-weight:700;line-height:44px;text-align:center">&#10003;</td></tr></table>';
            }
            if (($b['kicker'] ?? '') !== '') {
                $o .= '<p class="m-kick" style="margin:0 0 10px;' . $sans . 'font-size:11px;font-weight:700;letter-spacing:.16em;text-transform:uppercase;color:#7a6444">' . h($b['kicker']) . '</p>';
            }
            [$antes, $dest, $despues] = array_pad((array) ($b['titulo'] ?? []), 3, '');
            $o .= '<h1 class="m-h1 m-tinta" style="margin:0 0 12px;font-family:' . CORREO_F_SERIF . ';font-weight:400;font-size:30px;line-height:1.15;color:#2a2624">'
                . h($antes) . ($dest !== '' ? '<em class="m-rosa" style="color:#a86b68;font-style:italic">' . h($dest) . '</em>' : '') . h($despues) . '</h1>';
            if (($b['texto'] ?? '') !== '') {
                $o .= '<p class="m-tinta2" style="margin:0 0 16px;' . $sans . 'font-size:15px;line-height:1.6;color:#5e5751">' . correo_enlaza((string) $b['texto']) . '</p>';
            }
            if (($b['url'] ?? '') !== '') {
                $u = (string) $b['url'];
                $o .= '<table role="presentation" align="center" cellpadding="0" cellspacing="0" border="0" style="margin:4px auto 18px"><tr>'
                    . '<td class="m-url" bgcolor="#ffffff" style="background:#ffffff;border:1px solid #ebe4dc;border-radius:999px;padding:6px 14px;' . $sans . 'font-size:14px;word-break:break-word;overflow-wrap:anywhere">'
                    . '<a href="' . h($u) . '" style="color:#7e4744;text-decoration:none">' . h(preg_replace('~^https?://~', '', rtrim($u, '/'))) . '</a></td></tr></table>';
            }
            if (!empty($b['boton'])) {
                [$txt, $href] = $b['boton'];
                // Botón «bulletproof»: la celda lleva el color (Outlook ignora el padding del <a>), el <a> el área de clic
                $o .= '<table role="presentation" align="center" cellpadding="0" cellspacing="0" border="0" style="margin:0 auto"><tr>'
                    . '<td align="center" bgcolor="#9c5f5c" style="background:#9c5f5c;border-radius:999px">'
                    . '<a href="' . h($href) . '" target="_blank" style="display:inline-block;padding:14px 28px;border:1px solid #9c5f5c;border-radius:999px;'
                    . $sans . 'font-size:15px;font-weight:700;line-height:1.2;color:#ffffff;text-decoration:none">' . h($txt) . '</a></td></tr></table>';
            }
            if (($b['nota'] ?? '') !== '') {
                $o .= '<p class="m-nota" style="margin:10px 0 0;' . $sans . 'font-size:12px;line-height:1.5;color:#6f675f">' . h($b['nota']) . '</p>';
            }
            if (($b['enlace'] ?? '') !== '') {
                $o .= '<p class="m-nota" style="margin:8px 0 0;' . $sans . 'font-size:12px;line-height:1.5;color:#6f675f">Si el botón no funciona, copiad este enlace en el navegador:<br>'
                    . '<a class="m-enl" href="' . h($b['enlace']) . '" style="color:#7e4744;word-break:break-word;overflow-wrap:anywhere">' . h($b['enlace']) . '</a></p>';
            }
            return '<tr><td class="m-pad" align="center" style="padding:30px 32px 20px;text-align:center">' . $o . '</td></tr>';

        case 'pasos':
            $o = '';
            foreach (array_values((array) ($b['items'] ?? [])) as $i => $it) {
                $txt = '';
                foreach (is_array($it) ? $it : [[$it, false]] as [$seg, $neg]) $txt .= $neg ? '<b class="m-tinta" style="color:#2a2624">' . h($seg) . '</b>' : h($seg);
                $o .= '<tr><td width="36" valign="top" style="width:36px;padding:0 0 10px">'
                    . '<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td class="m-num" width="24" height="24" align="center" valign="middle" bgcolor="#f9e5e3" '
                    . 'style="width:24px;height:24px;border-radius:12px;background:#f9e5e3;color:#9c5f5c;' . $sans . 'font-size:12px;font-weight:700;line-height:24px;text-align:center">' . ($i + 1) . '</td></tr></table></td>'
                    . '<td class="m-tinta2" valign="top" style="padding:2px 0 10px;' . $sans . 'font-size:14px;line-height:1.55;color:#5e5751">' . $txt . '</td></tr>';
            }
            return '<tr><td class="m-pad" style="padding:24px 32px 14px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">' . $o . '</table></td></tr>';

        case 'caja':
            $o = '';
            foreach (array_values((array) ($b['filas'] ?? [])) as $i => [$et, $val]) {
                $borde = $i === 0 ? '' : 'border-top:1px solid #f1ebe4;';
                $o .= '<tr><td class="m-et m-linea" width="130" valign="top" style="width:130px;padding:8px 12px 8px 0;' . $borde . $sans . 'font-size:13px;line-height:1.5;color:#6f675f">' . h($et) . '</td>'
                    . '<td class="m-val m-linea m-tinta" valign="top" style="padding:8px 0;' . $borde . $sans . 'font-size:14px;line-height:1.5;color:#2a2624">' . correo_enlaza(correo_mayuscula((string) $val)) . '</td></tr>';
            }
            return '<tr><td class="m-pad" style="padding:16px 32px 24px"><table role="presentation" class="m-caja" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="background:#ffffff;border:1px solid #ebe4dc;border-radius:12px">'
                . '<tr><td style="padding:18px 20px">'
                . (($b['titulo'] ?? '') !== '' ? '<p class="m-kick" style="margin:0 0 8px;' . $sans . 'font-size:11px;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:#7a6444">' . h($b['titulo']) . '</p>' : '')
                . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">' . $o . '</table></td></tr></table></td></tr>';

        case 'aviso':
            return '<tr><td class="m-pad" style="padding:0 32px 12px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
                . '<td class="m-aviso" bgcolor="#fcf4f2" style="background:#fcf4f2;border-radius:10px;padding:12px 14px;' . $sans . 'font-size:13px;line-height:1.55;color:#5e5751">'
                . (($b['fuerte'] ?? '') !== '' ? '<b>' . h($b['fuerte']) . '</b> ' : '') . correo_enlaza((string) ($b['texto'] ?? '')) . '</td></tr></table></td></tr>';

        case 'citas':
            $o = '';
            foreach ((array) ($b['items'] ?? []) as $c) {
                if ((string) $c === '') continue;
                $o .= '<p class="m-tinta2" style="margin:8px 0;padding:4px 0 4px 14px;border-left:3px solid #e8dac5;font-family:' . CORREO_F_SERIF . ';font-style:italic;font-size:14px;line-height:1.55;color:#5e5751">«' . h($c) . '»</p>';
            }
            return $o === '' ? '' : '<tr><td class="m-pad" style="padding:0 32px 12px">' . $o . '</td></tr>';

        case 'texto':
            return '<tr><td class="m-pad m-tinta2" style="padding:4px 32px 12px;' . $sans . 'font-size:13px;line-height:1.6;color:#5e5751">' . correo_enlaza((string) ($b['texto'] ?? '')) . '</td></tr>';

        case 'legal':
            return '<tr><td class="m-pad" style="padding:8px 32px 8px"><table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
                . '<td class="m-legal m-sep" style="border-top:1px solid #ebe4dc;padding-top:14px;' . $sans . 'font-size:12px;line-height:1.6;color:#6f675f">'
                . (string) ($b['html'] ?? '') . '</td></tr></table></td></tr>';
    }
    return '';
}

/**
 * Las condiciones (documento_legal) en letra pequeña para el cuerpo HTML. Solo AÑADE un style a las etiquetas que usa la
 * plantilla legal (h1/h2/p/ul/li/a): el texto no cambia ni una letra, y legal_a_texto() de esto da lo mismo que del
 * original (lo comprueba tests/correo_html_test.php). Es el soporte duradero: nada se resume ni se pliega.
 */
function correo_legal_html(string $doc): string {
    if (preg_match('~<body[^>]*>(.*)</body>~si', $doc, $m)) $doc = $m[1];
    $s = 'font-family:' . CORREO_F_SANS . ';';
    $est = [
        'h1' => $s . 'margin:0 0 8px;font-size:13px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#5e5751',
        'h2' => $s . 'margin:14px 0 4px;font-size:12px;font-weight:700;letter-spacing:.04em;color:#5e5751',
        'p' => $s . 'margin:0 0 8px;font-size:12px;line-height:1.6;color:#6f675f',
        'ul' => 'margin:0 0 8px;padding-left:18px',
        'li' => $s . 'margin:0 0 4px;font-size:12px;line-height:1.6;color:#6f675f',
        'a' => 'color:#7e4744;word-break:break-word;overflow-wrap:anywhere',
    ];
    return (string) preg_replace_callback('~<(h1|h2|p|ul|li|a)\b([^>]*)>~i', fn($m) => '<' . $m[1] . $m[2] . ' style="' . $est[strtolower($m[1])] . '">', $doc);
}

/** Filas del resumen en texto plano: «- Etiqueta: valor». La MISMA lista pinta la caja del HTML. */
function correo_resumen_texto(array $filas): string {
    return implode('', array_map(fn($f) => '- ' . $f[0] . ': ' . $f[1] . "\n", $filas));
}

/** «Servicio: …» del resumen, igual en los tres correos de compra. */
function correo_linea_servicio(array $E): string {
    return marca() . ', un producto de AxisWorks' . (titular_identificado($E)
        ? ', que presta ' . $E['titular'] . (($E['nif'] ?? '') !== '' ? ' (NIF ' . $E['nif'] . ')' : '') . (($E['domicilio'] ?? '') !== '' ? ', ' . $E['domicilio'] : '') . '.'
        : '. Está en pruebas: los datos de quien lo presta se publicarán antes de abrir la venta.');
}

/** Primera letra en mayúscula, solo para la caja del HTML (en el texto plano el valor sigue a «Etiqueta: »). */
function correo_mayuscula(string $t): string {
    // Una dirección de correo o web se deja tal cual: «Hola@…» en el soporte duradero sería un dato mal escrito (code-review)
    if (filter_var($t, FILTER_VALIDATE_EMAIL) || preg_match('~^https?://~i', $t)) return $t;
    return mb_strtoupper(mb_substr($t, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($t, 1, null, 'UTF-8');
}
