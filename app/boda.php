<?php
// Rutas de la web de una boda (<slug>.BASE_DOMAIN): páginas, formularios de
// invitados y panel privado de la pareja.

declare(strict_types=1);

const CSP_BODA = "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; font-src 'self'; script-src 'self'; "
    . "connect-src 'self'; frame-src https://maps.google.com https://www.google.com; form-action 'self'; "
    . "frame-ancestors 'none'; base-uri 'none'; object-src 'none'";

// Topes por boda: una boda no puede llenar el disco que comparte con las demás
const MAX_BYTES_RSVP = 3 * 1024 * 1024;
const MAX_BYTES_CANCIONES = 512 * 1024;
const MAX_INVITADOS = 15;

function ctx_live(string $slug): array {
    $f = dir_boda($slug) . '/foto.webp';
    return ['modo' => 'live', 'assets' => '/assets/', 'slug' => $slug, 'foto' => is_file($f) ? '/foto?v=' . filemtime($f) : ''];
}

function rutas_boda(string $slug, string $ruta, string $metodo): void {
    $c = config_boda($slug);
    if (!$c) no_existe();
    header('Content-Security-Policy: ' . CSP_BODA);
    $archivada = ($c['_estado'] ?? '') === 'archivada';

    if (strpos($ruta, 'panel') === 0) { rutas_panel($slug, $c, $ruta, $metodo); return; }
    if ($ruta === 'foto') { sirve_foto(dir_boda($slug) . '/foto.webp'); }
    if ($archivada) {
        if ($ruta === 'privacidad') { echo render_pagina($c, 'privacidad', ctx_live($slug)); return; }
        if ($ruta !== '') { header('Location: /', true, 302); exit; }
        echo render_archivada($c, ctx_live($slug));
        return;
    }
    if ($ruta === 'api/rsvp') { api_rsvp($slug, $c, $metodo); return; }
    if ($ruta === 'api/musica') { api_musica($slug, $c, $metodo); return; }
    if ($ruta === 'boda.ics' && $c['fecha'] !== '') {
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="boda.ics"');
        echo ics($c);
        return;
    }
    if ($metodo !== 'GET' && $metodo !== 'HEAD') { http_response_code(405); exit; }
    $html = render_pagina($c, $ruta, ctx_live($slug));
    if ($html === null) no_existe();
    echo $html;
}

// ---------------------------------------------------------------- invitados

function api_rsvp(string $slug, array $c, string $metodo): void {
    if ($metodo !== 'POST') json_response(['ok' => false, 'error' => 'Método no permitido'], 405);
    $s = seccion_tipo($c, 'rsvp');
    if (!$s) json_response(['ok' => false, 'error' => 'Esta web no recoge confirmaciones.'], 404);
    if (clean_str($_POST['web'] ?? '') !== '') json_response(['ok' => true, 'personas' => 1]); // honeypot
    if (!limite('rsvp|' . $slug . '|' . ip_cliente(), 20, 3600)) json_response(['ok' => false, 'error' => 'Demasiados envíos seguidos. Prueba dentro de un rato.'], 429);

    $menus = $s['datos']['menus'];
    $invitados = [];
    $raw = $_POST['invitados'] ?? null;
    if (!is_array($raw)) json_response(['ok' => false, 'error' => 'Falta el nombre.']);
    foreach (array_slice(array_values($raw), 0, MAX_INVITADOS) as $g) {
        if (!is_array($g)) continue;
        $nombre = clean_str($g['nombre'] ?? '', 120);
        if ($nombre === '') json_response(['ok' => false, 'error' => 'Falta el nombre de alguno de los invitados.']);
        $tipo = ($g['tipo'] ?? '') === 'nino' ? 'nino' : 'adulto';
        $menu = clean_str($g['menu'] ?? '', 20);
        if (!in_array($menu, $menus, true)) $menu = ($tipo === 'nino' && in_array('infantil', $menus, true)) ? 'infantil' : $menus[0];
        $invitados[] = ['nombre' => $nombre, 'tipo' => $tipo, 'menu' => $menu, 'alergias' => clean_str($g['alergias'] ?? '', 300)];
    }
    if (!$invitados) json_response(['ok' => false, 'error' => 'Falta el nombre.']);

    // Alergias = dato de salud (art. 9 RGPD): sin consentimiento explícito no se guardan.
    // Datos de acompañantes: quien confirma declara tener su permiso (Legal, #81).
    $hayAlergias = (bool) array_filter($invitados, fn($i) => $i['alergias'] !== '');
    if ($hayAlergias && ($_POST['consent_alergias'] ?? '') !== 'si') {
        json_response(['ok' => false, 'error' => 'Para guardar las alergias necesitamos que marques la casilla de consentimiento.']);
    }
    if (count($invitados) > 1 && ($_POST['consent_acompanantes'] ?? '') !== 'si') {
        json_response(['ok' => false, 'error' => 'Marca la casilla que confirma que tienes permiso de tus acompañantes.']);
    }

    $contacto = clean_str($_POST['contacto'] ?? '', 120);
    if ($contacto === '') json_response(['ok' => false, 'error' => 'Falta un teléfono o email de contacto.']);
    $esEmail = strpos($contacto, '@') !== false;
    if ($esEmail && !filter_var($contacto, FILTER_VALIDATE_EMAIL)) json_response(['ok' => false, 'error' => 'El email no parece válido.']);
    if (!$esEmail && !preg_match('/^[0-9+\s()-]{6,20}$/', $contacto)) json_response(['ok' => false, 'error' => 'El teléfono no parece válido.']);

    $L = textos_legales();
    $rec = [
        'id' => bin2hex(random_bytes(8)),
        'fecha_envio' => date('c'),
        'invitados' => $invitados,
        'asiste_ceremonia' => ($_POST['asiste_ceremonia'] ?? '') === 'si',
        'asiste_banquete' => ($_POST['asiste_banquete'] ?? '') === 'si',
        'necesita_bus' => !empty($s['datos']['bus']) && ($_POST['necesita_bus'] ?? '') === 'si',
        'contacto' => $contacto,
        'cancion' => clean_str($_POST['cancion'] ?? '', 150),
        'consentimientos' => ['alergias' => $hayAlergias, 'acompanantes' => count($invitados) > 1, 'version' => $L['version'] ?? ''],
    ];
    $ok = muta_json(dir_boda($slug) . '/guardado/rsvp.json', function (array &$d) use ($rec) { $d[] = $rec; return true; }, MAX_BYTES_RSVP);
    if ($ok !== true) {
        registra('rsvp no guardado (tope o disco)', ['slug' => $slug]);
        json_response(['ok' => false, 'error' => 'No se ha podido guardar. Avisa a los novios, por favor.'], 507);
    }
    json_response(['ok' => true, 'personas' => count($invitados)]);
}

function api_musica(string $slug, array $c, string $metodo): void {
    if (!seccion_tipo($c, 'musica')) json_response(['ok' => false], 404);
    $f = dir_boda($slug) . '/guardado/canciones.json';
    $accion = (string) ($_GET['action'] ?? $_POST['action'] ?? 'add');
    if ($accion === 'list' && $metodo === 'GET') {
        $l = array_map(fn($r) => ['id' => $r['id'], 'artista' => $r['artista'], 'cancion' => $r['cancion'], 'votos' => (int) ($r['votos'] ?? 0)], lee_json($f) ?? []);
        json_response(['ok' => true, 'canciones' => $l]);
    }
    if ($metodo !== 'POST') json_response(['ok' => false], 405);
    if ($accion === 'vote') {
        $id = clean_str($_POST['id'] ?? '', 20);
        // Un voto por canción y conexión: sin esto, un bucle infla la lista
        if (!limite('voto|' . $slug . '|' . $id . '|' . ip_cliente(), 1, 86400 * 365)) json_response(['ok' => false, 'error' => 'Ya has votado esta canción.']);
        if (!limite('votos|' . $slug . '|' . ip_cliente(), 60, 3600)) json_response(['ok' => false, 'error' => 'Demasiados votos seguidos.'], 429);
        $v = muta_json($f, function (array &$d) use ($id) {
            foreach ($d as &$r) if (($r['id'] ?? '') === $id) { $r['votos'] = (int) ($r['votos'] ?? 0) + 1; return $r['votos']; }
            return null;
        }, MAX_BYTES_CANCIONES);
        if (!is_int($v)) json_response(['ok' => false, 'error' => 'No se ha encontrado esa canción.'], 404);
        json_response(['ok' => true, 'votes' => $v]);
    }
    if (clean_str($_POST['web'] ?? '') !== '') json_response(['ok' => true]);
    if (!limite('cancion|' . $slug . '|' . ip_cliente(), 15, 3600)) json_response(['ok' => false, 'error' => 'Demasiadas canciones seguidas. Deja sitio a los demás.'], 429);
    $artista = clean_str($_POST['artista'] ?? '', 120);
    $cancion = clean_str($_POST['cancion'] ?? '', 120);
    if ($artista === '' || $cancion === '') json_response(['ok' => false, 'error' => 'Indica artista y canción.']);
    $rec = ['id' => bin2hex(random_bytes(8)), 'fecha' => date('c'), 'artista' => $artista, 'cancion' => $cancion, 'votos' => 0];
    $ok = muta_json($f, function (array &$d) use ($rec) { $d[] = $rec; return true; }, MAX_BYTES_CANCIONES);
    if ($ok !== true) json_response(['ok' => false, 'error' => 'La lista está llena.'], 507);
    json_response(['ok' => true]);
}

// ---------------------------------------------------------------- panel

function rutas_panel(string $slug, array $c, string $ruta, string $metodo): void {
    cabeceras_privadas();
    $sub = trim(substr($ruta, 5), '/');
    if ($sub === 'entrar') { panel_entrar($slug, $c, $metodo); return; }
    if ($sub === 'clave') { panel_clave($slug, $c, $metodo); return; }
    if ($sub === 'recuperar') { panel_recuperar($slug, $c, $metodo); return; }
    if ($sub === 'salir') { panel_sal($slug); header('Location: /panel/entrar'); exit; }
    if (!panel_autenticado($slug)) { header('Location: /panel/entrar'); exit; }

    switch ($sub) {
        case '': echo panel_inicio($slug, $c); return;
        case 'excel': panel_excel($slug, $c); return;
        case 'zip': panel_zip($slug, $c); return;
        case 'factura':
            $p = lee_json(dir_boda($slug) . '/pedido.json') ?? [];
            $html = render_factura((string) ($p['factura'] ?? ''));
            if ($html === null) no_existe();
            header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'");
            echo $html;
            return;
        case 'editar':
            header('Content-Security-Policy: ' . CSP_CREADOR_PANEL);
            echo vista_constructor('editar', $c, $slug, panel_csrf());
            return;
        case 'guardar': panel_guardar($slug, $c, $metodo); return;
        case 'vista-previa': api_vista_previa($metodo, url_boda($slug, 'assets/')); return;
    }
    no_existe();
}

// En el panel la vista previa pide sus assets al propio subdominio
const CSP_CREADOR_PANEL = "default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; font-src 'self'; "
    . "script-src 'self'; connect-src 'self'; frame-src 'self' https://maps.google.com https://www.google.com; "
    . "form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'";

function panel_marco(array $c, string $titulo, string $cuerpo, bool $ancho = false): string {
    return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . h($titulo) . ' — ' . h(nombres($c)) . '</title><meta name="robots" content="noindex, nofollow">'
        . '<link rel="stylesheet" href="/assets/boda.css?v=' . h(ASSETS_V) . '"><style>' . tema_css($c) . '</style></head>'
        . '<body class="panel-body"><main><div class="wrap' . ($ancho ? ' wrap-ancho' : '') . '">' . $cuerpo . '</div></main></body></html>';
}

function panel_entrar(string $slug, array $c, string $metodo): void {
    $error = '';
    if ($metodo === 'POST') {
        $error = panel_login($slug, (string) ($_POST['clave'] ?? ''));
        if ($error === '') { header('Location: /panel'); exit; }
    }
    $sinClave = !panel_tiene_clave($slug);
    echo panel_marco($c, 'Panel privado', '<section class="section panel-login"><h1>Panel privado</h1><hr class="divider">'
        . ($sinClave ? '<p class="lede">Todavía no habéis elegido contraseña. Usad el enlace del email de bienvenida o pedid uno nuevo.</p>' : '')
        . '<form method="post" class="stack"><div class="field"><label for="clave">Contraseña</label><input type="password" id="clave" name="clave" autocomplete="current-password" required autofocus></div>'
        . ($error !== '' ? '<div class="form-msg">' . h($error) . '</div>' : '')
        . '<div class="form-actions"><button type="submit" class="btn">Entrar</button></div></form>'
        . '<p class="panel-aux"><a href="/panel/recuperar">He olvidado la contraseña</a> · <a href="/">Ver la web</a></p></section>');
}

function panel_clave(string $slug, array $c, string $metodo): void {
    $tok = clean_str($_GET['t'] ?? $_POST['t'] ?? '', 60);
    $error = '';
    if ($metodo === 'POST') {
        $a = (string) ($_POST['clave'] ?? '');
        $b = (string) ($_POST['clave2'] ?? '');
        $error = $a !== $b ? 'Las dos contraseñas no coinciden.' : panel_fija_clave($slug, $tok, $a);
        if ($error === '') { header('Location: /panel'); exit; }
    } elseif (!panel_enlace_valido($slug, $tok)) {
        $error = 'Este enlace ya se ha usado o ha caducado. Pide otro desde «He olvidado la contraseña».';
    }
    echo panel_marco($c, 'Elegir contraseña', '<section class="section panel-login"><h1>Elegid vuestra contraseña</h1><hr class="divider">'
        . '<form method="post" class="stack"><input type="hidden" name="t" value="' . h($tok) . '">'
        . '<div class="field"><label for="clave">Contraseña nueva (mínimo ' . PANEL_MIN_CLAVE . ' caracteres)</label><input type="password" id="clave" name="clave" minlength="' . PANEL_MIN_CLAVE . '" autocomplete="new-password" required autofocus></div>'
        . '<div class="field"><label for="clave2">Repetidla</label><input type="password" id="clave2" name="clave2" minlength="' . PANEL_MIN_CLAVE . '" autocomplete="new-password" required></div>'
        . ($error !== '' ? '<div class="form-msg">' . h($error) . '</div>' : '')
        . '<div class="form-actions"><button type="submit" class="btn">Guardar y entrar</button></div></form>'
        . '<p class="panel-aux"><a href="/panel/recuperar">Pedir otro enlace</a></p></section>');
}

/** Siempre la misma respuesta, exista o no el email: no se confirma a nadie qué email tiene la pareja. */
function panel_recuperar(string $slug, array $c, string $metodo): void {
    $msg = '';
    if ($metodo === 'POST') {
        $email = strtolower(clean_str($_POST['email'] ?? '', 160));
        $p = lee_json(dir_boda($slug) . '/pedido.json') ?? [];
        $validos = array_filter([strtolower($c['pareja']['email']), strtolower((string) ($p['email'] ?? ''))]);
        if (limite('recuperar|' . $slug, 5, 3600) && limite('recuperar-ip|' . ip_cliente(), 10, 3600) && in_array($email, $validos, true)) {
            correo_enlace_panel($slug, $email, panel_nuevo_enlace($slug));
        }
        $msg = 'Si ese email es el de la pareja o el de la compra, os acaba de llegar un enlace. Mirad también en spam.';
    }
    echo panel_marco($c, 'Recuperar acceso', '<section class="section panel-login"><h1>Recuperar acceso</h1><hr class="divider">'
        . '<p class="lede">Escribid el email de contacto de la web o el que usasteis al pagar.</p>'
        . '<form method="post" class="stack"><div class="field"><label for="email">Email</label><input type="email" id="email" name="email" autocomplete="email" required></div>'
        . ($msg !== '' ? '<p class="form-ok">' . h($msg) . '</p>' : '')
        . '<div class="form-actions"><button type="submit" class="btn">Enviar enlace</button></div></form>'
        . '<p class="panel-aux"><a href="/panel/entrar">Volver</a></p></section>');
}

/** Única lectura de quién viene (igual que personas() de EduCora). */
function personas(array $r): array {
    return array_map(fn($g) => ['nombre' => (string) ($g['nombre'] ?? ''), 'tipo' => ($g['tipo'] ?? '') === 'nino' ? 'nino' : 'adulto',
        'menu' => (string) ($g['menu'] ?? ''), 'alergias' => (string) ($g['alergias'] ?? '')],
        array_values(array_filter((array) ($r['invitados'] ?? []), 'is_array')));
}
function clave_nombre(string $n): string {
    $n = mb_strtolower(trim((string) preg_replace('/\s+/', ' ', $n)), 'UTF-8');
    return strtr($n, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
}

function panel_datos(string $slug): array {
    $rsvps = lee_json(dir_boda($slug) . '/guardado/rsvp.json') ?? [];
    $st = ['personas' => 0, 'adultos' => 0, 'ninos' => 0, 'ceremonia' => 0, 'banquete' => 0, 'bus' => 0];
    $menus = array_fill_keys(array_keys(MENUS), 0);
    $vistos = [];
    foreach ($rsvps as $i => $r) {
        $ps = personas($r);
        $n = count($ps);
        $st['personas'] += $n;
        foreach ($ps as $p) {
            $st[$p['tipo'] === 'nino' ? 'ninos' : 'adultos']++;
            if (!empty($r['asiste_banquete']) && isset($menus[$p['menu']])) $menus[$p['menu']]++;
            $k = clave_nombre($p['nombre']);
            if ($k !== '') $vistos[$k][$i] = true;
        }
        if (!empty($r['asiste_ceremonia'])) $st['ceremonia'] += $n;
        if (!empty($r['asiste_banquete'])) $st['banquete'] += $n;
        if (!empty($r['necesita_bus'])) $st['bus'] += $n;
    }
    $rep = array_keys(array_filter($vistos, fn($x) => count($x) > 1));
    return [$rsvps, $st, $menus, $rep];
}

function panel_inicio(string $slug, array $c): string {
    [$rsvps, $st, $menus, $rep] = panel_datos($slug);
    $canciones = lee_json(dir_boda($slug) . '/guardado/canciones.json') ?? [];
    usort($canciones, fn($a, $b) => ($b['votos'] ?? 0) <=> ($a['votos'] ?? 0));
    $rs = seccion_tipo($c, 'rsvp');
    $menusOn = $rs ? $rs['datos']['menus'] : array_keys(MENUS);
    $sino = fn($v) => !empty($v) ? '<span class="yes">Sí</span>' : '<span class="no">No</span>';
    $o = '<header class="panel-head"><div><span class="kicker">Panel privado</span><h1>' . h(nombres($c)) . '</h1>'
        . '<p><a href="/" target="_blank" rel="noopener">' . h(preg_replace('~^https?://~', '', rtrim(url_boda($slug), '/'))) . '</a> · se mantiene hasta el ' . h(fecha_larga(fecha_borrado($c['fecha']), false)) . '</p></div>'
        . '<nav class="panel-acc"><a class="btn" href="/panel/editar">Editar la web</a><a class="btn btn-soft" href="/panel/excel">Descargar Excel</a>'
        . '<a class="btn btn-soft" href="/panel/zip">Descargar ZIP</a><a class="btn btn-soft" href="/panel/factura" target="_blank" rel="noopener">Factura</a>'
        . '<a class="panel-salir" href="/panel/salir">Salir</a></nav></header>';
    $o .= '<section class="section"><div class="stat-row">';
    foreach ([['personas', 'personas'], ['adultos', 'adultos'], ['ninos', 'niños/as'], ['ceremonia', 'van a ceremonia'], ['banquete', 'van a banquete']] as [$k, $t]) {
        $o .= '<div class="stat"><b>' . $st[$k] . '</b><span>' . $t . '</span></div>';
    }
    if ($rs && $rs['datos']['bus']) $o .= '<div class="stat"><b>' . $st['bus'] . '</b><span>necesitan bus</span></div>';
    $o .= '<div class="stat"><b>' . count($rsvps) . '</b><span>respuestas</span></div></div>';
    $o .= '<p class="panel-nota">Menús de quienes van al banquete</p><div class="stat-row">';
    foreach ($menusOn as $m) $o .= '<div class="stat"><b>' . $menus[$m] . '</b><span>' . h(mb_strtolower(MENUS[$m], 'UTF-8')) . '</span></div>';
    $o .= '</div></section>';

    $o .= '<section class="section"><h2 class="panel-h2">Confirmaciones</h2>';
    if ($rep) $o .= '<p class="panel-aviso">Hay nombres que aparecen en más de una respuesta (marcados con «repetido»). Puede que alguien haya confirmado dos veces.</p>';
    $o .= '<div class="table-wrap"><table><thead><tr><th>Quién viene</th><th>Ceremonia</th><th>Banquete</th>' . ($rs && $rs['datos']['bus'] ? '<th>Bus</th>' : '') . '<th>Contacto</th><th>Canción</th><th>Enviado</th></tr></thead><tbody>';
    if (!$rsvps) $o .= '<tr><td colspan="7" class="vacio">Todavía no hay confirmaciones.</td></tr>';
    foreach (array_reverse($rsvps) as $r) {
        $o .= '<tr><td>';
        foreach (personas($r) as $p) {
            $o .= '<div class="persona"><b>' . h($p['nombre']) . '</b>'
                . (in_array(clave_nombre($p['nombre']), $rep, true) ? '<span class="rep"> · repetido</span>' : '')
                . ($p['tipo'] === 'nino' ? '<span class="muted"> · niño/a</span>' : '')
                . '<span class="muted"> · ' . h(MENUS[$p['menu']] ?? $p['menu']) . '</span>'
                . ($p['alergias'] !== '' ? '<br><span class="alergia">Alergias: ' . h($p['alergias']) . '</span>' : '') . '</div>';
        }
        $o .= '</td><td>' . $sino($r['asiste_ceremonia'] ?? false) . '</td><td>' . $sino($r['asiste_banquete'] ?? false) . '</td>'
            . ($rs && $rs['datos']['bus'] ? '<td>' . $sino($r['necesita_bus'] ?? false) . '</td>' : '')
            . '<td>' . h($r['contacto'] ?? '') . '</td><td>' . h($r['cancion'] ?? '') . '</td>'
            . '<td>' . h(isset($r['fecha_envio']) ? date('d/m/Y H:i', strtotime($r['fecha_envio'])) : '') . '</td></tr>';
    }
    $o .= '</tbody></table></div></section>';

    if (seccion_tipo($c, 'musica')) {
        $o .= '<section class="section"><h2 class="panel-h2">Canciones propuestas</h2><div class="table-wrap"><table><thead><tr><th>Canción</th><th>Artista</th><th>Votos</th></tr></thead><tbody>';
        if (!$canciones) $o .= '<tr><td colspan="3" class="vacio">Todavía no hay canciones.</td></tr>';
        foreach ($canciones as $s) $o .= '<tr><td>' . h($s['cancion'] ?? '') . '</td><td>' . h($s['artista'] ?? '') . '</td><td>' . (int) ($s['votos'] ?? 0) . '</td></tr>';
        $o .= '</tbody></table></div></section>';
    }
    $o .= '<p class="panel-nota">Las respuestas de vuestros invitados se borran el ' . h(fecha_larga(fecha_borrado($c['fecha']), false)) . '. El Excel que descarguéis queda bajo vuestra responsabilidad.</p>';
    return panel_marco($c, 'Panel privado', $o, true);
}

/** Excel: una fila por persona. `;` + BOM para el Excel en español; celdas = + - @ neutralizadas (las escribe un invitado). */
function panel_excel(string $slug, array $c): void {
    [$rsvps, , , $rep] = panel_datos($slug);
    $celda = fn($v) => (($v = (string) $v) !== '' && strpbrk($v[0], "=+-@\t\r") !== false) ? "'" . $v : $v;
    $sino = fn($v) => !empty($v) ? 'Sí' : 'No';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="confirmaciones-' . $slug . '-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Grupo', 'Nombre', 'Tipo', 'Menú', 'Alergias', 'Ceremonia', 'Banquete', 'Bus', 'Contacto', 'Canción', 'Enviado', 'Nombre repetido'], ';');
    foreach ($rsvps as $i => $r) {
        foreach (personas($r) as $p) {
            fputcsv($out, [$i + 1, $celda($p['nombre']), $p['tipo'] === 'nino' ? 'niño/a' : 'adulto', MENUS[$p['menu']] ?? $p['menu'], $celda($p['alergias']),
                $sino($r['asiste_ceremonia'] ?? null), $sino($r['asiste_banquete'] ?? null), $sino($r['necesita_bus'] ?? null),
                $celda($r['contacto'] ?? ''), $celda($r['cancion'] ?? ''),
                isset($r['fecha_envio']) ? date('d/m/Y H:i', strtotime($r['fecha_envio'])) : '',
                in_array(clave_nombre($p['nombre']), $rep, true) ? 'Sí' : ''], ';');
        }
    }
    fclose($out);
    exit;
}

/**
 * ZIP con el HTML estático, generado por el MISMO render_pagina(). Solo entra lo que
 * se lista aquí: nunca la carpeta guardado/ (datos de invitados).
 */
function panel_zip(string $slug, array $c): void {
    if (!class_exists('ZipArchive')) { http_response_code(500); exit('ZIP no disponible'); }
    $tmp = tempnam(sys_get_temp_dir(), 'bz');
    $z = new ZipArchive();
    $z->open($tmp, ZipArchive::OVERWRITE);
    $foto = dir_boda($slug) . '/foto.webp';
    $ctx = ['modo' => 'zip', 'assets' => 'assets/', 'slug' => $slug, 'foto' => is_file($foto) ? 'foto.webp' : ''];
    $z->addFromString('index.html', (string) render_pagina($c, '', $ctx));
    foreach ($c['secciones'] as $s) if ($s['on']) $z->addFromString($s['ruta'] . '.html', (string) render_pagina($c, $s['ruta'], $ctx));
    $z->addFromString('privacidad.html', (string) render_pagina($c, 'privacidad', $ctx));
    if ($c['fecha'] !== '') $z->addFromString('boda.ics', ics($c));
    if (is_file($foto)) $z->addFile($foto, 'foto.webp');
    $z->addFile(WEB_DIR . '/assets/boda.css', 'assets/boda.css');
    $z->addFile(WEB_DIR . '/assets/js/boda.js', 'assets/js/boda.js');
    $z->addFile(WEB_DIR . '/assets/img/eucalipto.webp', 'assets/img/eucalipto.webp');
    foreach (glob(WEB_DIR . '/assets/fonts/*.woff2') ?: [] as $f) $z->addFile($f, 'assets/fonts/' . basename($f));
    $z->close();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="web-boda-' . $slug . '.zip"');
    header('Content-Length: ' . filesize($tmp));
    readfile($tmp);
    @unlink($tmp);
    exit;
}

function panel_guardar(string $slug, array $actual, string $metodo): void {
    if ($metodo !== 'POST') json_response(['ok' => false], 405);
    if (!panel_csrf_ok()) json_response(['ok' => false, 'error' => 'La sesión ha caducado. Recarga la página.'], 403);
    if (!limite('guardar|' . $slug, 120, 3600)) json_response(['ok' => false, 'error' => 'Demasiados cambios seguidos.'], 429);
    $c = normaliza_config(json_decode((string) ($_POST['config'] ?? ''), true));
    $f = faltan($c);
    if ($f) json_response(['ok' => false, 'error' => 'Faltan datos.', 'faltan' => $f], 422);
    $d = dir_boda($slug);
    if (($_POST['quitar_foto'] ?? '') === 'si') @unlink($d . '/foto.webp');
    $fr = guarda_foto('foto', $d . '/foto.webp');
    if ($fr !== '' && $fr !== 'sin-foto') json_response(['ok' => false, 'error' => $fr], 422);
    $c['foto'] = is_file($d . '/foto.webp');
    // Copia de la versión anterior (5 como máximo) por si la pareja se arrepiente
    asegura_dir($d . '/historial');
    @copy($d . '/config.json', $d . '/historial/' . date('Ymd-His') . '.json');
    $h = glob($d . '/historial/*.json') ?: [];
    sort($h);
    foreach (array_slice($h, 0, max(0, count($h) - 5)) as $viejo) @unlink($viejo);
    $c['_estado'] = $actual['_estado'] ?? 'activa';
    escribe_json($d . '/config.json', $c);
    json_response(['ok' => true]);
}
