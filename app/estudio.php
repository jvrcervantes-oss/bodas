<?php
// Panel del ESTUDIO (solo el owner) en axisworks.studio/bodas/estudio. 25-sep-2026.
// Revisión previa #96 (Seguridad + Legal):
//  - Sin SSH no se puede sembrar una contraseña en el servidor: el acceso se abre con un token
//    de 256 bits cuyo sha256 va en app/estudio_alta.php (repo público; irreversible), con
//    caducidad y VERSIÓN. Se gasta al usarlo; subir la versión permite rehacer la contraseña
//    si se olvida (por eso quien pueda hacer push manda aquí: 2FA de GitHub obligatorio).
//  - Login: 5 intentos/15 min por IP y, en global, retraso creciente (nunca cierre total:
//    sería regalar a cualquiera un «dejar fuera al owner»).
//  - Sesión: cookie __Host- (una web de pareja en *.axisworks.studio no puede fijarla),
//    guardada en DATA_DIR, 2 h de inactividad y 12 h como máximo; CSRF en todo POST.
//  - Qué ve: bodas, pedidos, facturas y códigos de regalo. De los invitados, SOLO el número
//    de personas confirmadas (Legal: ni por menú ni por alergia, que en una boda pequeña
//    «1 celíaco» señala a alguien). Nunca entra en el panel de una pareja ni exporta.
//  - El enlace del panel se reenvía SOLO al email registrado de la pareja; el estudio no lo ve.
//  - Log de acciones con ids, sin emails ni códigos; se borra a los 12 meses (cron).
declare(strict_types=1);

const ESTUDIO_MIN_CLAVE = 14;
const ESTUDIO_INACTIVIDAD = 2 * 3600;
const ESTUDIO_MAXIMA = 12 * 3600;

function estudio_dir(string ...$p): string { return dir_datos('estudio', ...$p); }
function estudio_url(string $sub = ''): string { return BASE_PATH . '/estudio' . ($sub !== '' ? '/' . $sub : ''); }

function estudio_sesion(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    asegura_dir(estudio_dir('sesiones'));
    session_save_path(estudio_dir('sesiones'));
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', (string) ESTUDIO_MAXIMA);
    $https = SCHEME === 'https';
    session_name($https ? '__Host-bwe' : 'bwe');   // __Host- exige Secure y Path=/
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict']);
    session_start();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

function estudio_clave(): array { return lee_json(estudio_dir('clave.json')) ?? []; }

function estudio_dentro(): bool {
    estudio_sesion();
    $s = $_SESSION;
    if (empty($s['ok'])) return false;
    $ahora = time();
    if (($s['desde'] ?? 0) + ESTUDIO_MAXIMA < $ahora || ($s['ultimo'] ?? 0) + ESTUDIO_INACTIVIDAD < $ahora
        || ($s['gen'] ?? -1) !== (estudio_clave()['gen'] ?? 0)) {
        $_SESSION = ['csrf' => bin2hex(random_bytes(16))];
        return false;
    }
    $_SESSION['ultimo'] = $ahora;
    return true;
}

function estudio_csrf_ok(): bool {
    $t = (string) ($_POST['csrf'] ?? '');
    return $t !== '' && hash_equals((string) ($_SESSION['csrf'] ?? ''), $t);
}
function estudio_csrf_campo(): string { return '<input type="hidden" name="csrf" value="' . h($_SESSION['csrf'] ?? '') . '">'; }

/** Acciones del estudio: ids y resultado, nunca emails, códigos ni tokens. */
function estudio_log(string $accion, array $datos = []): void {
    asegura_dir(estudio_dir());
    file_put_contents(estudio_dir('log-' . date('Y-m') . '.jsonl'),
        json_encode(['fecha' => date('c'), 'accion' => $accion, 'ip' => substr(hash('sha256', ip_cliente()), 0, 12)] + $datos, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
}

function estudio_hash_clave(string $clave): string {
    return defined('PASSWORD_ARGON2ID') ? password_hash($clave, PASSWORD_ARGON2ID) : password_hash($clave, PASSWORD_BCRYPT);
}

/** El token de alta vigente (repo) o null. */
function estudio_alta_vigente(string $tok): ?array {
    $f = APP_DIR . '/estudio_alta.php';
    $a = is_file($f) ? require $f : null;
    if (!is_array($a) || !preg_match('/^[a-f0-9]{64}$/', $tok)) return null;
    if (!hash_equals((string) ($a['sha256'] ?? ''), hash('sha256', $tok))) return null;
    if (($a['caduca'] ?? '') < date('Y-m-d')) return null;
    if ((int) ($a['version'] ?? 0) <= (int) (estudio_clave()['version_alta'] ?? 0)) return null;   // ya gastado
    return $a;
}

function rutas_estudio(string $sub, string $metodo): void {
    cabeceras_privadas();
    header('X-Frame-Options: DENY');
    header('Content-Security-Policy: ' . CSP_CREADOR);
    if ($sub === 'alta') { estudio_alta($metodo); return; }
    if ($sub === 'salir') {
        estudio_sesion();
        estudio_log('salir');
        $_SESSION = [];
        session_destroy();
        header('Location: ' . estudio_url(), true, 303);
        return;
    }
    if (!estudio_dentro()) { estudio_login($metodo); return; }
    if ($metodo === 'POST' && !estudio_csrf_ok()) { http_response_code(403); echo estudio_pagina('Sesión caducada', '<p>Vuelve a cargar la página.</p>'); return; }
    switch ($sub) {
        case '': echo estudio_pagina('Bodas', estudio_bodas($metodo)); return;
        case 'pedidos': echo estudio_pagina('Pedidos', estudio_pedidos()); return;
        case 'codigos': echo estudio_pagina('Códigos de regalo', estudio_codigos($metodo)); return;
        case 'guias': echo estudio_pagina('Guías del Padrino', estudio_guias($metodo)); return;
        case 'factura':
            $html = render_factura(clean_str($_GET['n'] ?? '', 40));
            if ($html === null) { http_response_code(404); echo estudio_pagina('Factura', '<p>No existe.</p>'); return; }
            header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'");
            echo $html;
            return;
    }
    http_response_code(404);
    echo estudio_pagina('No existe', '<p>Esta página no existe.</p>');
}

function estudio_login(string $metodo): void {
    $error = '';
    if ($metodo === 'POST') {
        if (!estudio_csrf_ok()) $error = 'La página ha caducado. Vuelve a intentarlo.';
        elseif (!limite('estudio-login|' . ip_cliente(), 5, 900, true)) $error = 'Demasiados intentos. Prueba dentro de 15 minutos.';
        else {
            // Retraso global creciente con los fallos de la última hora (sin cerrar nunca del todo)
            $fallos = muta_json(estudio_dir('fallos.json'), function (array &$d) {
                $d['t'] = array_values(array_filter($d['t'] ?? [], fn($t) => $t > time() - 3600));
                return count($d['t']);
            });
            if (is_int($fallos) && $fallos > 10) usleep((int) min(3e6, ($fallos - 10) * 2e5));
            $clave = (string) ($_POST['clave'] ?? '');
            $hash = (string) (estudio_clave()['hash'] ?? '');
            if ($hash !== '' && strlen($clave) <= 256 && password_verify($clave, $hash)) {
                session_regenerate_id(true);
                $_SESSION = ['ok' => true, 'desde' => time(), 'ultimo' => time(), 'gen' => estudio_clave()['gen'] ?? 0, 'csrf' => bin2hex(random_bytes(16))];
                estudio_log('entrar');
                header('Location: ' . estudio_url(), true, 303);
                return;
            }
            muta_json(estudio_dir('fallos.json'), function (array &$d) { $d['t'][] = time(); });
            estudio_log('entrar-fallo');
            $error = 'Contraseña incorrecta.';
        }
    }
    $sinClave = (string) (estudio_clave()['hash'] ?? '') === '';
    echo estudio_pagina('Estudio', ($sinClave ? '<p class="est-aviso">Aún no hay contraseña. Ábrela con el enlace de alta que te dio el estudio.</p>' : '')
        . '<form method="post" class="est-login" action="' . h(estudio_url()) . '">' . estudio_csrf_campo()
        . '<label class="c-campo"><span>Contraseña</span><input type="password" name="clave" autocomplete="current-password" required autofocus></label>'
        . ($error !== '' ? '<p class="est-error">' . h($error) . '</p>' : '')
        . '<button class="b-btn b-dark" type="submit">Entrar</button></form>', false);
}

function estudio_alta(string $metodo): void {
    estudio_sesion();
    $tok = (string) ($metodo === 'POST' ? ($_POST['t'] ?? '') : ($_GET['t'] ?? ''));
    if (!limite('estudio-alta|' . ip_cliente(), 10, 3600, true) || !estudio_alta_vigente($tok)) {
        http_response_code(404);
        echo estudio_pagina('Enlace no válido', '<p>Este enlace ya se ha usado o ha caducado.</p>', false);
        return;
    }
    $error = '';
    if ($metodo === 'POST') {
        $c1 = (string) ($_POST['clave'] ?? '');
        $c2 = (string) ($_POST['clave2'] ?? '');
        $bcrypt = !defined('PASSWORD_ARGON2ID');
        if (!estudio_csrf_ok()) $error = 'La página ha caducado. Vuelve a intentarlo.';
        elseif (mb_strlen($c1, 'UTF-8') < ESTUDIO_MIN_CLAVE) $error = 'Mínimo ' . ESTUDIO_MIN_CLAVE . ' caracteres.';
        elseif ($bcrypt && strlen($c1) > 72) $error = 'Máximo 72 caracteres.';
        elseif ($c1 !== $c2) $error = 'Las dos contraseñas no coinciden.';
        else {
            // Dentro del cerrojo: dos peticiones a la vez no gastan dos veces el mismo token. La versión
            // se compara con $d (ya bloqueado): volver a leer clave.json aquí dentro se bloquearía a sí mismo.
            $a = estudio_alta_vigente($tok);
            $ok = $a && muta_json(estudio_dir('clave.json'), function (array &$d) use ($a, $c1) {
                if ((int) $a['version'] <= (int) ($d['version_alta'] ?? 0)) return false;
                $d = ['hash' => estudio_hash_clave($c1), 'gen' => ($d['gen'] ?? 0) + 1, 'version_alta' => (int) $a['version'], 'desde' => date('c')];
                return true;
            });
            if ($ok === true) {
                session_regenerate_id(true);
                $_SESSION = ['ok' => true, 'desde' => time(), 'ultimo' => time(), 'gen' => estudio_clave()['gen'] ?? 0, 'csrf' => bin2hex(random_bytes(16))];
                estudio_log('alta-clave');
                header('Location: ' . estudio_url(), true, 303);
                return;
            }
            $error = 'Este enlace ya se ha usado.';
        }
    }
    echo estudio_pagina('Contraseña del estudio', '<p>Elige la contraseña del panel del estudio (mínimo ' . ESTUDIO_MIN_CLAVE . ' caracteres). Este enlace sirve una sola vez.</p>'
        . '<form method="post" class="est-login" action="' . h(estudio_url('alta')) . '">' . estudio_csrf_campo() . '<input type="hidden" name="t" value="' . h($tok) . '">'
        . '<label class="c-campo"><span>Contraseña</span><input type="password" name="clave" autocomplete="new-password" minlength="' . ESTUDIO_MIN_CLAVE . '" required></label>'
        . '<label class="c-campo"><span>Repítela</span><input type="password" name="clave2" autocomplete="new-password" required></label>'
        . ($error !== '' ? '<p class="est-error">' . h($error) . '</p>' : '')
        . '<button class="b-btn b-dark" type="submit">Guardar contraseña</button></form>', false);
}

/** Todas las bodas, con lo justo para gestionarlas. De los invitados, solo cuántos han confirmado. */
function estudio_bodas(string $metodo): string {
    $msg = '';
    if ($metodo === 'POST' && ($_POST['accion'] ?? '') === 'reenviar') $msg = estudio_reenviar(strtolower(clean_str($_POST['slug'] ?? '', 60)));
    $filas = [];
    foreach (glob(dir_datos('bodas', '*'), GLOB_ONLYDIR) ?: [] as $d) {
        $slug = basename($d);
        if (!slug_valido($slug)) continue;
        $cfg = lee_json($d . '/config.json');
        if (!$cfg) continue;
        $c = normaliza_config($cfg);
        $ped = lee_json($d . '/pedido.json') ?? [];
        $arch = ($cfg['_estado'] ?? '') === 'archivada';
        $personas = 0;
        if (!$arch) foreach (lee_json($d . '/guardado/rsvp.json') ?? [] as $r) $personas += count(personas($r));
        $origen = str_starts_with((string) ($ped['session_id'] ?? ''), 'cortesia_') ? 'Regalo' : (($ped['factura'] ?? '') !== '' ? 'Pagada' : '—');
        // El código usado vive en el pedido completo (pedidos/<id>.json), no en la copia de la boda
        if ($origen === 'Regalo') $ped['cortesia'] = (string) ((lee_json(dir_datos('pedidos', basename((string) $ped['session_id']) . '.json')) ?? [])['cortesia'] ?? '');
        $filas[] = ['slug' => $slug, 'c' => $c, 'ped' => $ped, 'arch' => $arch, 'personas' => $personas, 'origen' => $origen, 'creado' => (string) ($ped['creado'] ?? '')];
    }
    usort($filas, fn($a, $b) => strcmp($b['creado'], $a['creado']));
    $activas = count(array_filter($filas, fn($f) => !$f['arch']));
    $o = ($msg !== '' ? '<p class="est-ok">' . h($msg) . '</p>' : '')
        . '<p class="est-resumen"><b>' . count($filas) . '</b> bodas · <b>' . $activas . '</b> activas · <b>' . count(array_filter($filas, fn($f) => $f['origen'] === 'Regalo')) . '</b> regaladas</p>';
    if (!$filas) return $o . '<p>Todavía no hay ninguna boda publicada.</p>';
    $o .= '<div class="est-tabla-wrap"><table class="est-tabla"><thead><tr><th>Web</th><th>Pareja</th><th>Boda</th><th>Pack</th><th>Origen</th><th>Confirmados</th><th>Se borra</th><th></th></tr></thead><tbody>';
    foreach ($filas as $f) {
        $c = $f['c'];
        $web = '<a href="' . h(url_boda($f['slug'])) . '" target="_blank" rel="noopener">' . h($f['slug']) . '</a>';
        if ($f['arch']) {
            // Legal: cumplida la fecha de borrado, la fila queda sin datos de la pareja
            $o .= '<tr class="est-archivada"><td>' . $web . '</td><td colspan="5">Archivada</td><td>' . h(fecha_corta(fecha_borrado($c['fecha']))) . '</td><td></td></tr>';
            continue;
        }
        $email = $c['pareja']['email'] !== '' ? $c['pareja']['email'] : (string) ($f['ped']['email'] ?? '');
        $origen = $f['origen'] === 'Regalo' ? 'Regalo ' . h((string) ($f['ped']['cortesia'] ?? '')) : ($f['origen'] === 'Pagada' ? 'Pagada · <a href="' . h(estudio_url('factura') . '?n=' . rawurlencode($f['ped']['factura'])) . '" target="_blank">' . h($f['ped']['factura']) . '</a>' : '—');
        $o .= '<tr><td>' . $web . '</td><td>' . h(nombres($c) ?: '—') . ($email !== '' ? '<br><small>' . h($email) . '</small>' : '') . '</td>'
            . '<td>' . h($c['fecha'] !== '' ? fecha_corta($c['fecha']) : '—') . '</td><td>' . ($c['atelier'] !== '' ? 'Atelier · ' . h(ATELIER[$c['atelier']]['nombre']) : 'Esencial') . '</td>'
            . '<td>' . $origen . '</td><td class="est-num">' . $f['personas'] . '</td><td>' . h($c['fecha'] !== '' ? fecha_corta(fecha_borrado($c['fecha'])) : '—') . '</td>'
            . '<td><form method="post" action="' . h(estudio_url()) . '">' . estudio_csrf_campo() . '<input type="hidden" name="accion" value="reenviar"><input type="hidden" name="slug" value="' . h($f['slug']) . '">'
            . '<button class="c-link" type="submit" title="Manda a su email registrado un enlace nuevo para entrar en su panel">Reenviar acceso</button></form></td></tr>';
    }
    return $o . '</tbody></table></div><p class="est-nota">«Confirmados» es el número de personas que han respondido que vienen. Los datos de los invitados solo los ve la pareja.</p>';
}

function fecha_corta(string $ymd): string {
    $t = strtotime($ymd);
    return $t ? date('d/m/Y', $t) : '—';
}

/** Enlace nuevo del panel, solo al email registrado, y anula los anteriores. */
function estudio_reenviar(string $slug): string {
    if (!slug_valido($slug) || !boda_existe($slug)) return 'Esa boda no existe.';
    if (!limite('estudio-reenvio|' . $slug, 3, 86400, true)) return 'Ya se han reenviado 3 accesos hoy a esta boda. Prueba mañana.';
    $c = config_boda($slug);
    $email = (string) (($c['pareja']['email'] ?? '') ?: ((lee_json(dir_boda($slug) . '/pedido.json') ?? [])['email'] ?? ''));
    if ($email === '') return 'Esta boda no tiene email registrado.';
    muta_json(panel_fichero($slug), function (array &$p) { $p['enlaces'] = []; });
    $ok = envia_correo($email, 'Acceso a vuestro panel de boda',
        "Hola:\n\nOs mandamos un enlace nuevo para entrar en el panel de " . url_boda($slug) . " y elegir vuestra contraseña:\n\n"
        . panel_nuevo_enlace($slug) . "\n\nSirve una sola vez y caduca en 14 días. Los enlaces anteriores ya no funcionan.\n\nAxisWorks");
    estudio_log('reenviar-acceso', ['slug' => $slug, 'enviado' => $ok]);
    return $ok ? 'Enlace enviado al email registrado de la pareja.' : 'No se ha podido enviar el email. Revisa el correo del servidor (BOD-6).';
}

function estudio_pedidos(): string {
    $peds = [];
    foreach (glob(dir_datos('pedidos', '*.json')) ?: [] as $f) { $p = lee_json($f); if ($p) $peds[] = $p; }
    usort($peds, fn($a, $b) => strcmp((string) ($b['creado'] ?? ''), (string) ($a['creado'] ?? '')));
    if (!$peds) return '<p>Todavía no hay pedidos.</p>';
    $total = array_sum(array_map(fn($p) => (int) ($p['importe']['total'] ?? 0), $peds));
    $o = '<p class="est-resumen"><b>' . count($peds) . '</b> pedidos · <b>' . h(euros($total)) . '</b> cobrados (IVA incluido)</p>'
        . '<div class="est-tabla-wrap"><table class="est-tabla"><thead><tr><th>Fecha</th><th>Web</th><th>Estado</th><th>Importe</th><th>Factura / código</th></tr></thead><tbody>';
    foreach ($peds as $p) {
        $esRegalo = str_starts_with((string) ($p['session_id'] ?? ''), 'cortesia_');
        $doc = ($p['factura'] ?? '') !== '' ? '<a href="' . h(estudio_url('factura') . '?n=' . rawurlencode($p['factura'])) . '" target="_blank">' . h($p['factura']) . '</a>' : ($esRegalo ? 'Regalo ' . h((string) ($p['cortesia'] ?? '')) : '—');
        $estado = ['creada' => 'Publicada', 'cobrada' => 'Cobrada, sin publicar', 'sin-datos' => '⚠ Cobrada sin datos', 'cortesia' => 'Regalo en curso'][$p['estado'] ?? ''] ?? (string) ($p['estado'] ?? '');
        $o .= '<tr><td>' . h(isset($p['creado']) ? date('d/m/Y H:i', strtotime($p['creado'])) : '—') . '</td><td>' . h((string) ($p['slug'] ?? '')) . '</td>'
            . '<td>' . h($estado) . '</td><td class="est-num">' . h(euros((int) ($p['importe']['total'] ?? 0))) . '</td><td>' . $doc . '</td></tr>';
    }
    return $o . '</tbody></table></div>';
}

function estudio_codigos(string $metodo): string {
    $aviso = '';
    $nuevo = '';
    if ($metodo === 'POST') {
        $acc = (string) ($_POST['accion'] ?? '');
        if ($acc === 'crear') {
            $usos = max(1, min(20, (int) ($_POST['usos'] ?? 1)));
            $cad = norm_fecha($_POST['caduca'] ?? '');
            if ($cad === '' || $cad < date('Y-m-d')) $cad = date('Y-m-d', strtotime('+6 months'));
            [$id, $nuevo] = cortesia_crea($usos, !empty($_POST['atelier']), $cad, clean_str($_POST['nota'] ?? '', 60));
            estudio_log('crear-codigo', ['id' => $id]);
        } elseif ($acc === 'anular') {
            $id = clean_str($_POST['id'] ?? '', 12);
            $aviso = cortesia_anula($id) ? 'Código ' . $id . ' anulado.' : 'No se ha encontrado ese código.';
            estudio_log('anular-codigo', ['id' => $id]);
        }
    }
    $o = $nuevo !== '' ? '<div class="est-codigo-nuevo"><span>Código nuevo — cópialo ahora, no se vuelve a enseñar:</span><b>' . h($nuevo) . '</b></div>' : '';
    $o .= $aviso !== '' ? '<p class="est-ok">' . h($aviso) . '</p>' : '';
    $o .= '<form method="post" class="est-crear" action="' . h(estudio_url('codigos')) . '">' . estudio_csrf_campo() . '<input type="hidden" name="accion" value="crear">'
        . '<label class="c-campo"><span>Usos</span><input type="number" name="usos" min="1" max="20" value="1"></label>'
        . '<label class="c-campo"><span>Caduca</span><input type="date" name="caduca" value="' . h(date('Y-m-d', strtotime('+6 months'))) . '"></label>'
        . '<label class="c-campo est-nota-campo"><span>Nota <small>(sin apellidos ni contacto, p. ej. «regalo amigos sept-26»)</small></span><input name="nota" maxlength="60"></label>'
        . '<label class="c-check"><input type="checkbox" name="atelier" value="1" checked> <span>Vale también para diseños Atelier</span></label>'
        . '<button class="b-btn b-dark" type="submit">Crear código</button></form>';
    $filas = cortesia_listado();
    if (!$filas) return $o . '<p>No hay códigos.</p>';
    $o .= '<div class="est-tabla-wrap"><table class="est-tabla"><thead><tr><th>Código</th><th>Pack</th><th>Usos</th><th>Caduca</th><th>Nota</th><th>Estado</th><th></th></tr></thead><tbody>';
    foreach ($filas as $f) {
        $o .= '<tr' . ($f['estado'] !== 'Activo' ? ' class="est-apagado"' : '') . '><td>' . h($f['id']) . '</td><td>' . ($f['atelier'] ? 'Esencial y Atelier' : 'Esencial') . '</td>'
            . '<td class="est-num">' . $f['usados'] . ' / ' . $f['usos'] . '</td><td>' . h(fecha_corta($f['caduca'])) . '</td><td>' . h($f['nota']) . '</td><td>' . h($f['estado']) . '</td>'
            . '<td>' . ($f['estado'] === 'Activo' ? '<form method="post" action="' . h(estudio_url('codigos')) . '">' . estudio_csrf_campo() . '<input type="hidden" name="accion" value="anular"><input type="hidden" name="id" value="' . h($f['id']) . '"><button class="c-link c-link-mal" type="submit">Anular</button></form>' : '') . '</td></tr>';
    }
    return $o . '</tbody></table></div>';
}

function estudio_pagina(string $titulo, string $cuerpo, bool $menu = true): string {
    $nav = $menu ? '<nav class="est-nav"><a href="' . h(estudio_url()) . '">Bodas</a><a href="' . h(estudio_url('pedidos')) . '">Pedidos</a><a href="' . h(estudio_url('codigos')) . '">Códigos de regalo</a><a href="' . h(estudio_url('guias')) . '">Guías</a><a href="' . h(estudio_url('salir')) . '">Salir</a></nav>' : '';
    return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . h($titulo) . ' — Estudio · ' . h(marca()) . '</title><meta name="robots" content="noindex, nofollow">'
        . '<link rel="stylesheet" href="' . BASE_PATH . '/assets/marca.css?v=' . h(ASSETS_V) . '"><link rel="stylesheet" href="' . BASE_PATH . '/assets/crear.css?v=' . h(ASSETS_V) . '"></head><body class="simple estudio">'
        . '<header class="s-top est-top"><a class="c-marca" href="' . h(estudio_url()) . '">' . il('flor') . '<span>Estudio · ' . h(marca()) . '</span></a>' . $nav . '</header>'
        . '<main class="simple-main est-main"><h1>' . h($titulo) . '</h1>' . $cuerpo . '</main></body></html>';
}
