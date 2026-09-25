<?php
// Emails del producto. mail() del hosting con remitente del dominio (SPF de Hostinger
// ya publicado para axisworks.studio). El de bienvenida es además el "soporte
// duradero" que exige la ley (Legal, 25-sep): lleva ADJUNTAS las condiciones y la
// factura, y repite la petición de ejecución inmediata con la pérdida del
// desistimiento (art. 98.7 TRLGDCU). Si se quita algo de eso, cae la excepción.

declare(strict_types=1);

function remitente(): string { return (string) secreto('mail_from', 'hello@' . BASE_DOMAIN); }

/** Envía texto plano + adjuntos HTML. Devuelve true si el MTA lo aceptó. */
function envia_correo(string $para, string $asunto, string $texto, array $adjuntos = []): bool {
    if (!filter_var($para, FILTER_VALIDATE_EMAIL)) return false;
    if (defined('CORREO_A_FICHERO')) {   // desarrollo local: se deja en disco en vez de enviar
        asegura_dir(dir_datos('correos'));
        file_put_contents(dir_datos('correos', date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.txt'),
            "Para: $para\nAsunto: $asunto\n\n$texto\n\nAdjuntos: " . implode(', ', array_keys($adjuntos)));
        return true;
    }
    $from = remitente();
    $b = 'b' . bin2hex(random_bytes(12));
    $cab = "From: AxisWorks <$from>\r\nReply-To: $from\r\nMIME-Version: 1.0\r\n";
    if (!$adjuntos) {
        $cab .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit";
        $cuerpo = $texto;
    } else {
        $cab .= "Content-Type: multipart/mixed; boundary=\"$b\"";
        $cuerpo = "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n$texto\r\n";
        foreach ($adjuntos as $nombre => $html) {
            $cuerpo .= "--$b\r\nContent-Type: text/html; charset=UTF-8; name=\"$nombre\"\r\nContent-Disposition: attachment; filename=\"$nombre\"\r\nContent-Transfer-Encoding: base64\r\n\r\n"
                . chunk_split(base64_encode($html)) . "\r\n";
        }
        $cuerpo .= "--$b--";
    }
    $ok = @mail($para, '=?UTF-8?B?' . base64_encode($asunto) . '?=', $cuerpo, $cab, '-f' . $from);
    if (!$ok) registra('correo no aceptado', ['para' => $para, 'asunto' => $asunto]);
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

function correo_bienvenida(array $ped, array $cfg, string $enlace): void {
    $L = textos_legales();
    $url = url_boda($ped['slug']);
    $borrado = fecha_larga(fecha_borrado((string) ($cfg['fecha'] ?? '')), false);
    $acept = $ped['aceptacion']['fecha'] ?? '';
    $texto = "¡Vuestra web de boda ya está publicada!\n\n"
        . "Dirección: $url\n\n"
        . "Para entrar en vuestro panel (respuestas de invitados, Excel, editar la web y descargar el ZIP), elegid vuestra contraseña con este enlace. Sirve una sola vez y caduca en 14 días:\n$enlace\n\n"
        . "La web y las respuestas de vuestros invitados se mantienen hasta el $borrado. Ese día se borran las respuestas y la web pasa a una página de agradecimiento. Exportad el Excel antes si queréis conservarlas.\n\n"
        . "Factura: {$ped['factura']} (adjunta).\n"
        . "Condiciones de contratación y encargo de tratamiento: adjuntas.\n\n"
        . "Al comprar" . ($acept !== '' ? ' (' . date('d/m/Y H:i', strtotime($acept)) . ')' : '') . " marcasteis lo siguiente:\n"
        . '«' . ($L['check_condiciones'] ?? '') . "»\n"
        . '«' . ($L['check_desistimiento'] ?? '') . "»\n\n"
        . "Cualquier duda: " . empresa()['email'] . "\n\nAxisWorks";
    envia_correo($ped['email'], 'Vuestra web de boda: ' . preg_replace('~^https?://~', '', rtrim($url, '/')), $texto, [
        'condiciones.html' => documento_legal('condiciones', 'Condiciones de contratación'),
        $ped['factura'] . '.html' => (string) render_factura($ped['factura']),
    ]);
}

function correo_enlace_panel(string $slug, string $email, string $enlace): void {
    envia_correo($email, 'Acceso a vuestro panel de boda', "Hola:\n\nAlguien ha pedido un enlace para elegir una nueva contraseña del panel de " . url_boda($slug) . ".\n\n$enlace\n\nSirve una sola vez y caduca en 14 días. Si no lo habéis pedido vosotros, ignorad este email: vuestra contraseña actual sigue funcionando.\n\nAxisWorks");
}

function avisa_estudio(string $asunto, string $texto): void {
    registra('AVISO ESTUDIO: ' . $asunto, ['texto' => $texto]);
    envia_correo(empresa()['email'], '[Bodas] ' . $asunto, $texto);
}
