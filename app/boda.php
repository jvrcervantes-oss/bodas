<?php
// Rutas de la web de una boda (<slug>.BASE_DOMAIN): páginas, formularios de
// invitados y panel privado de la pareja.

declare(strict_types=1);

// Extras de pago y plano de mesas (F1c/F1d). Se cargan aquí, con el panel que los usa: así los tests que
// cargan boda.php (catering, por ejemplo) los tienen sin tocar su lista de módulos
require_once __DIR__ . '/extras.php';
require_once __DIR__ . '/mesas.php';
require_once __DIR__ . '/panel.php';   // carcasa y secciones del panel de la pareja (rediseño 27-sep-2026)

const CSP_BODA = "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; font-src 'self'; script-src 'self'; "
    . "connect-src 'self'; frame-src 'none'; form-action 'self'; "
    . "frame-ancestors 'none'; base-uri 'none'; object-src 'none'";

// Topes por boda: una boda no puede llenar el disco que comparte con las demás
const MAX_BYTES_RSVP = 3 * 1024 * 1024;
const MAX_BYTES_CANCIONES = 512 * 1024;
const MAX_INVITADOS = 15;

function ctx_live(string $slug): array {
    $f = dir_boda($slug) . '/foto.webp';
    $m = mapa_de($slug);
    return ['modo' => 'live', 'assets' => '/assets/', 'slug' => $slug, 'foto' => is_file($f) ? '/foto?v=' . filemtime($f) : '',
        'mapa' => $m ? ['src' => '/mapa.webp?v=' . substr($m['id'], 0, 8), 'pines' => $m['pines']] : null];
}

function rutas_boda(string $slug, string $ruta, string $metodo): void {
    $c = config_boda($slug);
    if (!$c) no_existe();
    header('Content-Security-Policy: ' . CSP_BODA);
    $archivada = ($c['_estado'] ?? '') === 'archivada';

    if (strpos($ruta, 'panel') === 0) { rutas_panel($slug, $c, $ruta, $metodo); return; }
    if ($ruta === 'foto') { sirve_foto(dir_boda($slug) . '/foto.webp'); }
    if ($ruta === 'mapa.webp') { sirve_mapa($slug); }
    if ($archivada) {
        if ($ruta === 'privacidad') { echo render_pagina($c, 'privacidad', ctx_live($slug)); return; }
        if ($ruta !== '') { header('Location: /', true, 302); exit; }
        echo render_archivada($c, ctx_live($slug));
        return;
    }
    if ($ruta === 'api/rsvp') { api_rsvp($slug, $c, $metodo); return; }
    // Enlace personal de un grupo (F1a, Seguridad #133). Un token con otra forma cae abajo en el
    // mismo no_existe() que cualquier ruta que no existe.
    if (preg_match('~^i/([A-Za-z0-9_-]{22})$~', $ruta, $m)) { pagina_grupo($slug, $c, $m[1], $metodo); return; }
    if ($ruta === 'api/libro') { api_libro($slug, $c, $metodo); return; }
    if ($ruta === 'acceso') { api_acceso($slug, $c, $metodo); return; }
    if (preg_match('~^g/([a-f0-9]{16})\.webp$~', $ruta, $m)) { sirve_galeria($slug, $c, $m[1]); }
    if (preg_match('~^l/([a-f0-9]{16})\.webp$~', $ruta, $m)) { sirve_libro_foto($slug, $c, $m[1]); }
    if ($ruta === 'api/musica') { api_musica($slug, $c, $metodo); return; }
    if ($ruta === 'boda.ics' && $c['fecha'] !== '') {
        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: attachment; filename="boda.ics"');
        echo ics($c);
        return;
    }
    if ($metodo !== 'GET' && $metodo !== 'HEAD') { http_response_code(405); exit; }
    // Galería y libro: detrás del código de la boda (owner, 25-sep)
    $sec = seccion_por_ruta($c, $ruta);
    if ($sec && in_array($sec['tipo'], ['galeria', 'libro'], true)) {
        header('Cache-Control: private, no-store');
        if (!acceso_ok($slug, $c) && !panel_autenticado($slug)) { echo render_codigo($c, $sec, ctx_live($slug), (string) ($_GET['codigo'] ?? '')); return; }
    }
    $html = render_pagina($c, $ruta, ctx_live($slug) + ['libro' => $sec && $sec['tipo'] === 'libro' ? libro_entradas($slug) : []]);
    if ($html === null) no_existe();
    echo $html;
}

// ---------------------------------------------------------------- invitados

/**
 * Bots que piden la página para pintar la vista previa del enlace en cuanto la pareja lo manda
 * (WhatsApp, Telegram, iMessage, que se hace pasar por facebookexternalhit…). No abren nada: sin
 * este filtro todos los grupos saldrían «Abierto» nada más enviarles el mensaje. Es un filtro por
 * User-Agent, así que la fecha de primer acceso es orientativa, no una prueba.
 */
function es_previsualizador(): bool {
    return (bool) preg_match('~WhatsApp|facebookexternalhit|Facebot|meta-externalagent|TelegramBot|Twitterbot|Slackbot|Discordbot|LinkedInBot|SkypeUriPreview|Applebot|Googlebot|bingbot|Pinterest|redditbot|Embedly|Iframely|vkShare~i',
        (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
}

/**
 * /i/<token>: la web normal abierta en la confirmación, con el saludo al grupo y sus nombres ya
 * escritos (editables). El grupo sale SOLO del token (app/invitados.php); nunca se enseña nada de lo
 * que ya contestaron (Legal #133: quien tenga el enlace no ve alergias ni datos de otros).
 * Token desconocido = el mismo no_existe() que /no-existe, con las mismas cabeceras: las propias de
 * esta ruta se ponen solo después de resolverlo.
 */
function pagina_grupo(string $slug, array $c, string $tok, string $metodo): void {
    if ($metodo !== 'GET' && $metodo !== 'HEAD') { http_response_code(405); exit; }   // como cualquier otra página
    $rsvp = seccion_tipo($c, 'rsvp');
    $g = $rsvp ? inv_grupo_por_token($slug, $tok) : null;
    if (!$g) no_existe();
    header('Referrer-Policy: no-referrer');   // el token está en la ruta: que no viaje a ningún enlace de fuera
    header('Cache-Control: private, no-store');   // lleva los nombres del grupo
    // Primer acceso = fecha, sin IP ni nada más (Seguridad/Legal #133). No cuentan los bots de vista
    // previa ni la propia pareja probando el enlace desde el panel.
    if ($metodo === 'GET' && $g['abierto'] === '' && !es_previsualizador() && !panel_sesion_presente($slug)) inv_marca_abierto($slug, $g['gid']);
    $ctx = ctx_live($slug) + [
        'grupo' => ['nombre' => $g['nombre'], 'personas' => $g['personas'], 'token' => $tok],
        // Los botones «Confirmar» de esta página siguen en el enlace del grupo, no en /<rsvp> sin token
        'rsvp_ruta' => $rsvp['ruta'], 'rsvp_href' => '/i/' . $tok,
    ];
    $html = render_pagina($c, $rsvp['ruta'], $ctx);
    if ($html === null) no_existe();
    echo $html;
}

function api_rsvp(string $slug, array $c, string $metodo): void {
    if ($metodo !== 'POST') json_response(['ok' => false, 'error' => 'Método no permitido'], 405);
    $s = seccion_tipo($c, 'rsvp');
    if (!$s) json_response(['ok' => false, 'error' => 'Esta web no recoge confirmaciones.'], 404);
    if (clean_str($_POST['web'] ?? '') !== '') json_response(['ok' => true, 'personas' => 1]); // honeypot
    if (!limite('rsvp|' . $slug . '|' . ip_cliente(), 20, 3600)) json_response(['ok' => false, 'error' => 'Demasiados envíos seguidos. Prueba dentro de un rato.'], 429);
    // Enlace de grupo: el grupo lo decide el token, nunca un campo «grupo» del formulario (Seguridad #133).
    // Un token que ya no vale (rotado, o la pareja quitó el grupo) se rechaza: guardarlo sin grupo
    // dejaría una respuesta que un reenvío ya no podría sustituir.
    $grupo = null;
    $tok = (string) ($_POST['i'] ?? '');
    if ($tok !== '') {
        $grupo = inv_grupo_por_token($slug, $tok);
        if (!$grupo) json_response(['ok' => false, 'error' => 'Este enlace ya no es válido. Pedid a los novios el enlace nuevo.'], 404);
    }

    $menus = array_values(array_filter($s['datos']['menus'], fn($m) => $m['nombre'] !== ''));
    if (!$menus) $menus = [menu_nuevo('general', 'Menú')];
    $porId = array_column($menus, null, 'id');
    $defNino = (array_values(array_filter($menus, fn($m) => $m['infantil']))[0] ?? $menus[0])['id'];
    $defAdulto = (array_values(array_filter($menus, fn($m) => !$m['infantil']))[0] ?? $menus[0])['id'];
    $invitados = [];
    $raw = $_POST['invitados'] ?? null;
    if (!is_array($raw)) json_response(['ok' => false, 'error' => 'Falta el nombre.']);
    foreach (array_slice(array_values($raw), 0, MAX_INVITADOS) as $g) {
        if (!is_array($g)) continue;
        $nombre = clean_str($g['nombre'] ?? '', 120);
        if ($nombre === '') json_response(['ok' => false, 'error' => 'Falta el nombre de alguno de los invitados.']);
        $tipo = ($g['tipo'] ?? '') === 'nino' ? 'nino' : 'adulto';
        $menu = clean_str($g['menu'] ?? '', 20);
        if (!isset($porId[$menu])) $menu = $tipo === 'nino' ? $defNino : $defAdulto;
        // Se guarda el id (manda) y una copia del nombre: si la pareja borra ese menú
        // después, el panel sigue sabiendo qué eligió esta persona
        // `id` propio de cada persona (Seguridad #133): mesas y catering la señalan por él, nunca por
        // nombre ni por posición, para que una alergia no acabe impresa en la mesa de otro
        $invitados[] = ['id' => bin2hex(random_bytes(8)), 'nombre' => $nombre, 'tipo' => $tipo, 'menu' => $menu, 'menu_nombre' => $porId[$menu]['nombre'],
            'alergias' => clean_str($g['alergias'] ?? '', 300)];
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
        'necesita_bus' => pregunta_bus($c) && ($_POST['necesita_bus'] ?? '') === 'si',
        'contacto' => $contacto,
        'cancion' => clean_str($_POST['cancion'] ?? '', 150),
        'consentimientos' => ['alergias' => $hayAlergias, 'acompanantes' => count($invitados) > 1, 'version' => $L['version'] ?? ''],
    ];
    if ($grupo) $rec['grupo'] = $grupo['gid'];   // id estable del grupo, no el token: rotar el enlace no rompe el vínculo
    // Un reenvío por el mismo enlace de grupo sustituye al anterior: se añade el registro nuevo y el
    // viejo queda marcado `sustituido` (no se borra: es lo que el invitado mandó). Sus alergias sí se
    // vacían: un dato de salud sin uso no se guarda (RGPD 5.1.c; Legal, 27-sep). Todo en la MISMA
    // escritura del mismo fichero: o queda todo o nada.
    $ok = muta_json(dir_boda($slug) . '/guardado/rsvp.json', function (array &$d) use ($rec) { rsvp_anade($d, $rec); return true; }, MAX_BYTES_RSVP);
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

    // Secciones del panel: páginas GET con la carcasa (app/panel.php). Las que además reciben una acción
    // (invitados, galería) la mandan por POST a su función de siempre, con su CSRF.
    $paginas = ['' => ['inicio', 'panel_inicio'], 'invitados' => ['invitados', 'panel_invitados'], 'respuestas' => ['respuestas', 'panel_respuestas'],
        'musica' => ['musica', 'panel_musica'], 'galeria' => ['galeria', 'panel_galeria'], 'descargas' => ['descargas', 'panel_descargas'], 'mas' => ['mas', null]];
    if (isset($paginas[$sub]) && ($metodo === 'GET' || $metodo === 'HEAD')) {
        [$sec, $fn] = $paginas[$sub];
        $titulo = $sec === 'inicio' ? (nombres($c) !== '' ? nombres($c) : 'Inicio') : ($sec === 'mas' ? 'Más' : PANEL_SECCIONES[$sec][1]);
        echo panel_pagina($slug, $c, $sec, $titulo, $fn ? $fn($slug, $c) : panel_mas(), ['qr' => $sec === 'inicio']);
        return;
    }

    switch ($sub) {
        case 'invitados': panel_invitados_accion($slug, $metodo); return;
        case 'respuestas/misma': panel_respuestas_misma($slug, $c, $metodo); return;   // BOD-22 (app/panel.php)
        case 'galeria':   // POST = subir una foto (la usa el editor); GET/HEAD = la página, arriba
            if ($metodo !== 'POST') { header('Allow: GET, HEAD, POST'); http_response_code(405); exit; }
            panel_galeria_subir($slug); return;
        case '': case 'respuestas': case 'musica': case 'descargas': case 'mas': header('Allow: GET, HEAD'); http_response_code(405); exit;
        case 'excel': panel_excel($slug, $c); return;
        case 'catering':
            if ($metodo !== 'GET' && $metodo !== 'HEAD') { header('Allow: GET, HEAD'); http_response_code(405); exit; }
            panel_catering($slug, $c); return;
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
        case 'mejora': panel_mejora($slug, $metodo); return;   // Esencial → Atelier (app/lemon.php)
        case 'extra': panel_extra($slug, $metodo); return;     // compra de un extra de pago (app/extras.php)
        case 'mesas': panel_mesas($slug, $c, $metodo); return;   // plano de mesas (app/mesas.php), incluido en todos los packs
        case 'mesas/imprimir': panel_mesas_imprimir($slug, $c); return;
        case 'vista-previa': api_vista_previa($metodo, url_boda($slug, 'assets/'), $slug); return;
        case 'libro': if ($metodo !== 'POST') no_existe(); panel_libro_accion($slug); return;
    }
    no_existe();
}

// En el panel la vista previa pide sus assets al propio subdominio
const CSP_CREADOR_PANEL = "default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; font-src 'self'; "
    . "script-src 'self'; connect-src 'self'; frame-src 'self'; "
    . "form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'";

function panel_entrar(string $slug, array $c, string $metodo): void {
    $error = '';
    if ($metodo === 'POST') {
        $error = panel_login($slug, (string) ($_POST['clave'] ?? ''));
        if ($error === '') { header('Location: /panel'); exit; }
    }
    $sinClave = !panel_tiene_clave($slug);
    echo panel_acceso_marco($c, 'Panel privado', '<h1>Entrar al panel</h1>'
        . ($sinClave ? '<p class="sub">Todavía no habéis elegido contraseña. Usad el enlace del email de bienvenida o pedid uno nuevo.</p>' : '')
        . '<form method="post" class="form"><div class="campo"><label for="clave">Contraseña</label><input type="password" id="clave" name="clave" autocomplete="current-password" required autofocus></div>'
        . ($error !== '' ? '<p class="error" role="alert">' . h($error) . '</p>' : '')
        . '<button type="submit" class="btn b-rosa btn-ancho">Entrar</button></form>'
        . '<p class="aux"><a href="/panel/recuperar">He olvidado la contraseña</a> · <a href="/">Ver la web</a></p>');
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
    echo panel_acceso_marco($c, 'Elegir contraseña', '<h1>Elegid vuestra contraseña</h1>'
        . '<form method="post" class="form"><input type="hidden" name="t" value="' . h($tok) . '">'
        . '<div class="campo"><label for="clave">Contraseña nueva (mínimo ' . PANEL_MIN_CLAVE . ' caracteres)</label><input type="password" id="clave" name="clave" minlength="' . PANEL_MIN_CLAVE . '" autocomplete="new-password" required autofocus></div>'
        . '<div class="campo"><label for="clave2">Repetidla</label><input type="password" id="clave2" name="clave2" minlength="' . PANEL_MIN_CLAVE . '" autocomplete="new-password" required></div>'
        . ($error !== '' ? '<p class="error" role="alert">' . h($error) . '</p>' : '')
        . '<button type="submit" class="btn b-rosa btn-ancho">Guardar y entrar</button></form>'
        . '<p class="aux"><a href="/panel/recuperar">Pedir otro enlace</a></p>');
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
    echo panel_acceso_marco($c, 'Recuperar acceso', '<h1>Recuperar acceso</h1>'
        . '<p class="sub">Escribid el email de contacto de la web o el que usasteis al pagar y os mandamos un enlace para elegir contraseña.</p>'
        . '<form method="post" class="form"><div class="campo"><label for="email">Email</label><input type="email" id="email" name="email" autocomplete="email" required></div>'
        . ($msg !== '' ? '<p class="ok" role="status">' . h($msg) . '</p>' : '')
        . '<button type="submit" class="btn b-rosa btn-ancho">Enviar enlace</button></form>'
        . '<p class="aux"><a href="/panel">Volver</a></p>');
}

/**
 * Única lectura de quién viene (igual que personas() de EduCora). Cada persona sale con su `id`: el
 * aleatorio que recibe al guardarse (desde el 27-sep-2026, Seguridad #133) o, en respuestas de
 * antes, uno derivado del id del registro y de su posición, con «v» delante (no es hex): así nunca se confunde con uno real.
 * Es estable porque una respuesta guardada no se reescribe nunca (un reenvío añade otra). Sin id
 * de registro no hay nada estable de donde sacarlo: '' («sin mesa» cuando existan las mesas).
 */
function personas(array $r): array {
    $o = [];
    foreach (array_values(array_filter((array) ($r['invitados'] ?? []), 'is_array')) as $i => $g) {
        $id = (string) ($g['id'] ?? '');
        // Un id derivado («v…») también vale guardado: es el que hereda un reenvío de una respuesta de antes (BOD-24)
        if (!preg_match('/^([a-f0-9]{16}|v[a-f0-9]{15})$/', $id)) $id = (string) ($r['id'] ?? '') !== '' ? 'v' . substr(sha1((string) $r['id'] . '|' . $i), 0, 15) : '';
        $o[] = ['id' => $id, 'nombre' => (string) ($g['nombre'] ?? ''), 'tipo' => ($g['tipo'] ?? '') === 'nino' ? 'nino' : 'adulto',
            'menu' => (string) ($g['menu'] ?? ''), 'menu_nombre' => (string) ($g['menu_nombre'] ?? ''), 'alergias' => (string) ($g['alergias'] ?? '')];
    }
    return $o;
}

/**
 * Las respuestas que cuentan: todas menos las que un reenvío del mismo enlace de grupo sustituyó.
 * TODA cuenta de personas (panel, Excel, catering, lista de invitados, estudio, Padrino) lee por aquí;
 * leer rsvp.json a pelo contaría dos veces a un grupo que corrigió su respuesta.
 */
/**
 * Añade una respuesta; si es de un grupo, marca sustituida la vigente de ese grupo y vacía sus alergias.
 * Se llama dentro del muta_json (bloqueo) del POST: candidatas, herencia y sustitución van en la misma escritura.
 *
 * Herencia del id (BOD-24, Seguridad 27-sep-2026): quien vuelve a responder por el enlace de su grupo conserva el
 * `id` de cada persona cuyo nombre (clave_nombre) sale UNA sola vez entre las respuestas vigentes de ESE grupo y una
 * sola vez en la nueva; así sigue sentada en su mesa. Si hay dos iguales en un lado o en otro, id nuevo («sin mesa»:
 * mejor resentar que sentar a otro). Nunca se hereda de una respuesta sin `grupo` ni de otro grupo, y el id lo pone
 * solo el servidor: api_rsvp genera siempre ids nuevos y nunca lee uno del formulario.
 */
function rsvp_anade(array &$d, array $rec): void {
    if (isset($rec['grupo'])) {
        $viejos = [];
        foreach ($d as $r) {
            if (!is_array($r) || ($r['grupo'] ?? '') !== $rec['grupo'] || !empty($r['sustituido'])) continue;
            foreach (personas($r) as $p) if ($p['id'] !== '') $viejos[clave_nombre($p['nombre'])][] = $p['id'];
        }
        $nuevos = array_count_values(array_map(fn($p) => clave_nombre((string) ($p['nombre'] ?? '')), (array) $rec['invitados']));
        foreach ((array) $rec['invitados'] as $j => $p) {
            $k = clave_nombre((string) ($p['nombre'] ?? ''));
            if ($k !== '' && ($nuevos[$k] ?? 0) === 1 && count($viejos[$k] ?? []) === 1) $rec['invitados'][$j]['id'] = $viejos[$k][0];
        }
        foreach ($d as $k => $r) {
            if (!is_array($r) || ($r['grupo'] ?? '') !== $rec['grupo'] || !empty($r['sustituido'])) continue;
            $d[$k]['sustituido'] = $rec['id'];
            foreach ((array) ($r['invitados'] ?? []) as $j => $p) if (is_array($p)) $d[$k]['invitados'][$j]['alergias'] = '';
        }
    }
    $d[] = $rec;
}

/**
 * La pareja marca desde el panel que una respuesta de la confirmación general es la misma que la que dio un grupo por su
 * enlace (BOD-22, Seguridad 27-sep-2026: decisión humana con sesión, nunca automática por nombres, porque /rsvp está
 * abierta a cualquiera y sustituir borra alergias). Se llama dentro del muta_json del POST. La general queda sustituida
 * por la del grupo, con quién y cuándo, y pierde sus alergias (RGPD 5.1.c); a la del grupo no se le copia nada: es del
 * grupo, no de la pareja. Devuelve '' si lo hizo; si no, el motivo, sin tocar nada.
 */
function rsvp_misma(array &$d, string $general, string $grupo): string {
    $ig = $ic = null;
    foreach ($d as $k => $r) {
        if (!is_array($r) || !is_string($r['id'] ?? null) || $r['id'] === '') continue;
        if ($r['id'] === $general) $ig = $k;
        if ($r['id'] === $grupo) $ic = $k;
    }
    if ($ig === null || $ic === null || $ig === $ic) return 'no-existe';
    if (!empty($d[$ig]['sustituido']) || (string) ($d[$ig]['grupo'] ?? '') !== '') return 'general';
    if (!empty($d[$ic]['sustituido']) || (string) ($d[$ic]['grupo'] ?? '') === '') return 'grupo';
    $d[$ig]['sustituido'] = $grupo;
    $d[$ig]['sustituido_por'] = 'pareja';
    $d[$ig]['sustituido_fecha'] = date('c');
    foreach ((array) ($d[$ig]['invitados'] ?? []) as $j => $p) if (is_array($p)) $d[$ig]['invitados'][$j]['alergias'] = '';
    return '';
}

/** Para cada respuesta general vigente, las vigentes de un grupo con algún nombre en común: [id general => [id grupo, …]]. */
function rsvp_posibles_mismas(array $vigentes): array {
    $o = [];
    foreach ($vigentes as $g) {
        if ((string) ($g['grupo'] ?? '') !== '' || (string) ($g['id'] ?? '') === '') continue;
        $nom = array_map(fn($p) => clave_nombre($p['nombre']), personas($g));
        foreach ($vigentes as $r) {
            if ((string) ($r['grupo'] ?? '') === '' || (string) ($r['id'] ?? '') === '') continue;
            if (array_intersect($nom, array_map(fn($p) => clave_nombre($p['nombre']), personas($r)))) $o[$g['id']][] = $r['id'];
        }
    }
    return $o;
}

function rsvp_vigentes(string $slug): array {
    return array_values(array_filter(lee_json(dir_boda($slug) . '/guardado/rsvp.json') ?? [], fn($r) => is_array($r) && empty($r['sustituido'])));
}
function clave_nombre(string $n): string {
    $n = mb_strtolower(trim((string) preg_replace('/\s+/', ' ', $n)), 'UTF-8');
    return strtr($n, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
}

function panel_datos(string $slug, array $c): array {
    $rsvps = rsvp_vigentes($slug);
    $st = ['personas' => 0, 'adultos' => 0, 'ninos' => 0, 'ceremonia' => 0, 'banquete' => 0, 'bus' => 0];
    $menus = [];
    foreach (menus_de($c) as $m) $menus[$m['id']] = ['nombre' => $m['nombre'], 'n' => 0];
    $vistos = [];
    foreach ($rsvps as $i => $r) {
        $ps = personas($r);
        $n = count($ps);
        $st['personas'] += $n;
        foreach ($ps as $p) {
            $st[$p['tipo'] === 'nino' ? 'ninos' : 'adultos']++;
            if (!empty($r['asiste_banquete'])) {
                $menus[$p['menu']] = $menus[$p['menu']] ?? ['nombre' => nombre_menu($c, $p['menu'], $p['menu_nombre']) . ' (ya no se ofrece)', 'n' => 0];
                $menus[$p['menu']]['n']++;
            }
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

/** Excel: una fila por persona. `;` + BOM para el Excel en español; celdas = + - @ neutralizadas (las escribe un invitado). */
function panel_excel(string $slug, array $c): void {
    [$rsvps, , , $rep] = panel_datos($slug, $c);
    $celda = fn($v) => (($v = (string) $v) !== '' && strpbrk($v[0], "=+-@\t\r") !== false) ? "'" . $v : $v;
    $sino = fn($v) => !empty($v) ? 'Sí' : 'No';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="confirmaciones-' . $slug . '-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Grupo', 'Nombre', 'Tipo', 'Menú', 'Alergias', 'Ceremonia', 'Banquete', 'Bus', 'Contacto', 'Canción', 'Enviado', 'Nombre repetido'], ';');
    foreach ($rsvps as $i => $r) {
        foreach (personas($r) as $p) {
            fputcsv($out, [$i + 1, $celda($p['nombre']), $p['tipo'] === 'nino' ? 'niño/a' : 'adulto', $celda(nombre_menu($c, $p['menu'], $p['menu_nombre'])), $celda($p['alergias']),
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
 * Resumen para el catering (F1b): la sección del panel que se imprime y se le da al restaurante. Solo con la
 * sesión del panel (rutas_panel ya la exige) y sin caché en ningún sitio: lleva nombres con sus
 * alergias, que son datos de salud (art. 9 RGPD). Cuenta a quienes van al BANQUETE con la misma
 * cuenta por menú que el panel (panel_datos, sin respuestas sustituidas): el Excel filtrado por
 * Banquete = Sí da lo mismo. La columna Mesa sale en cuanto la pareja ha creado alguna mesa (el plano va
 * incluido en todos los packs desde el 27-sep-2026) y se rellena por el `id` de persona (mesas_de_personas),
 * nunca por nombre. Al imprimir, panel.css esconde el menú y deja solo la hoja.
 */
function panel_catering(string $slug, array $c): void {
    header('Cache-Control: private, no-store');
    [$rsvps, , $menus] = panel_datos($slug, $c);
    $t = ['total' => 0, 'adultos' => 0, 'ninos' => 0];
    $alergias = [];
    foreach ($rsvps as $r) {
        if (empty($r['asiste_banquete'])) continue;
        foreach (personas($r) as $p) {
            $t['total']++;
            $t[$p['tipo'] === 'nino' ? 'ninos' : 'adultos']++;
            if ($p['alergias'] !== '') $alergias[] = $p + ['menu_txt' => nombre_menu($c, $p['menu'], $p['menu_nombre'])];
        }
    }
    usort($alergias, fn($a, $b) => clave_nombre($a['nombre']) <=> clave_nombre($b['nombre']));
    $conMesa = extra_activo($slug, 'mesas') && mesas_lee($slug)['mesas'] !== [];
    $mesaDe = $conMesa ? mesas_de_personas($slug) : [];
    $lugar = (string) ($c['convite']['lugar'] ?? '');
    $cuando = trim(($c['fecha'] !== '' ? fecha_larga($c['fecha'], false) : '') . ($lugar !== '' ? ' · ' . $lugar : ''), ' ·');
    // Texto para pegar en un correo al restaurante: las mismas cifras que la tabla
    $txt = ['Resumen para el catering — ' . nombres($c) . ($cuando !== '' ? ' (' . $cuando . ')' : ''), 'En el banquete: ' . $t['total'] . ' (' . $t['adultos'] . ' adultos, ' . $t['ninos'] . ' niños/as)', '', 'Por menú:'];
    foreach ($menus as $m) $txt[] = '- ' . $m['nombre'] . ': ' . (int) $m['n'];
    $txt[] = '';
    $txt[] = 'Alergias e intolerancias:';
    foreach ($alergias as $p) $txt[] = '- ' . $p['nombre'] . ($conMesa ? ' (' . ($mesaDe[$p['id']] ?? 'sin mesa') . ')' : '') . ' · ' . $p['menu_txt'] . ': ' . $p['alergias'];
    if (!$alergias) $txt[] = '- Ninguna';
    // La hoja impresa envejece: se dice a qué hora se sacó para que nadie cocine con una vieja
    $o = '<p class="hoja-cab">Resumen para el catering · ' . h(nombres($c)) . ($cuando !== '' ? ' · ' . h($cuando) : '')
        . '<br>Datos del ' . h(date('d/m/Y')) . ' a las ' . h(date('H:i')) . '. Si llegan más confirmaciones, volved a imprimirlo.</p>';
    $o .= '<div class="rej r-12 catering"><section class="card">' . panel_card_cab('Resumen para el catering', 'Lo que os pedirá el restaurante. Solo cuenta a quienes van al banquete; si un grupo corrigió su respuesta, vale la última.',
        '<span class="chip incl">Incluido</span>');
    $o .= '<div class="kpis kpis-4 tab">';
    foreach ([['total', 'en el banquete'], ['adultos', 'adultos'], ['ninos', 'niños/as']] as [$k, $tx]) $o .= '<div class="kpi"><b>' . $t[$k] . '</b><span>' . $tx . '</span></div>';
    $o .= '<div class="kpi mal"><b>' . count($alergias) . '</b><span>con alergias</span></div></div>';
    $o .= '<h3 class="h3">Por menú</h3><div class="tabla-w"><table class="t t-corta"><thead><tr><th>Menú</th><th class="num">Personas</th></tr></thead><tbody>';
    foreach ($menus as $m) $o .= '<tr><td>' . h($m['nombre']) . '</td><td class="num">' . (int) $m['n'] . '</td></tr>';
    $o .= '<tr class="catering-total"><td>Total</td><td>' . array_sum(array_column($menus, 'n')) . '</td></tr></tbody></table></div></section>';
    $o .= '<section class="card">' . panel_card_cab('Alergias e intolerancias', 'Con su mesa, para que la cocina lo tenga claro.')
        . '<div class="tabla-w"><table class="t t-corta"><thead><tr><th>Nombre</th>' . ($conMesa ? '<th>Mesa</th>' : '') . '<th>Menú</th><th>Alergias</th></tr></thead><tbody>';
    if (!$alergias) $o .= '<tr><td colspan="' . ($conMesa ? 4 : 3) . '" class="vacio">Nadie de los que van al banquete ha indicado alergias.</td></tr>';
    foreach ($alergias as $p) {
        $o .= '<tr><td>' . h($p['nombre']) . ($p['tipo'] === 'nino' ? ' <span class="muted">(niño/a)</span>' : '') . '</td>'
            . ($conMesa ? '<td class="catering-mesa">' . h($mesaDe[$p['id']] ?? 'Sin mesa') . '</td>' : '') . '<td>' . h($p['menu_txt']) . '</td>'
            . '<td class="alergia">' . h($p['alergias']) . '</td></tr>';
    }
    $o .= '</tbody></table></div><div class="fila-bot no-print"><button type="button" class="btn b-osc" data-imprimir>Imprimir / guardar PDF</button>'
        . '<button type="button" class="btn b-papel" data-copiar-texto="' . h(implode("\n", $txt)) . '">Copiar como texto</button><span class="copiado" role="status" hidden>Copiado</span></div></section></div>';
    echo panel_pagina($slug, $c, 'catering', 'Catering', $o);
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
    $mapa = mapa_de($slug);
    $ctx = ['modo' => 'zip', 'assets' => 'assets/', 'slug' => $slug, 'foto' => is_file($foto) ? 'foto.webp' : '',
        'mapa' => $mapa ? ['src' => 'mapa.webp', 'pines' => $mapa['pines']] : null];
    if ($mapa) {
        $z->addFile(dir_datos('mapas', $mapa['id'] . '.webp'), 'mapa.webp');
        $z->addFromString('LICENCIA-MAPA.txt', "El mapa (mapa.webp) está hecho con datos de OpenStreetMap.\n© Colaboradores de OpenStreetMap — https://www.openstreetmap.org/copyright\n");
    }
    $z->addFromString('index.html', (string) render_pagina($c, '', $ctx));
    foreach ($c['secciones'] as $s) if ($s['on']) $z->addFromString($s['ruta'] . '.html', (string) render_pagina($c, $s['ruta'], $ctx));
    // La galería sí va en el ZIP (son fotos de la pareja); el libro no (contenido de terceros, Legal #87)
    $g = seccion_tipo($c, 'galeria');
    foreach ($g ? $g['datos']['fotos'] : [] as $f) {
        $file = dir_galeria($slug) . '/' . $f['id'] . '.webp';
        if (is_file($file)) $z->addFile($file, 'galeria/' . $f['id'] . '.webp');
    }
    $z->addFromString('privacidad.html', (string) render_pagina($c, 'privacidad', $ctx));
    if ($c['fecha'] !== '') $z->addFromString('boda.ics', ics($c));
    if (is_file($foto)) $z->addFile($foto, 'foto.webp');
    $z->addFile(WEB_DIR . '/assets/boda.css', 'assets/boda.css');
    $z->addFile(WEB_DIR . '/assets/js/boda.js', 'assets/js/boda.js');
    $z->addFile(WEB_DIR . '/assets/img/eucalipto.webp', 'assets/img/eucalipto.webp');
    foreach (glob(WEB_DIR . '/assets/img/deco/*.svg') ?: [] as $f) $z->addFile($f, 'assets/img/deco/' . basename($f));
    // Solo las libres (OFL) y con su licencia al lado; las de autor (fonts/premium/) no se entregan nunca
    foreach (glob(WEB_DIR . '/assets/fonts/*.woff2') ?: [] as $f) $z->addFile($f, 'assets/fonts/' . basename($f));
    $z->addFile(WEB_DIR . '/assets/fonts/LICENCIAS-OFL.txt', 'assets/fonts/LICENCIAS-OFL.txt');
    foreach (glob(WEB_DIR . '/assets/img/atelier/*.{webp,svg}', GLOB_BRACE) ?: [] as $f) $z->addFile($f, 'assets/img/atelier/' . basename($f));
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
    $crudo = json_decode((string) ($_POST['config'] ?? ''), true);
    $c = normaliza_config($crudo);
    $f = faltan($c, $crudo, faltan_panel($slug));
    if ($f) json_response(['ok' => false, 'error' => 'Faltan datos.', 'faltan' => $f], 422);
    if ($c['atelier'] !== '' && empty((lee_json(dir_boda($slug) . '/pedido.json') ?? [])['atelier'])) {
        json_response(['ok' => false, 'error' => 'Los diseños Atelier necesitan el Pack Atelier: podéis pasar a él desde el paso Estilo.'], 422);
    }
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
    $c = galeria_filtra_existentes($slug, $c);
    escribe_json($d . '/config.json', $c);
    galeria_limpia_huerfanas($slug, $c);
    // El mapa se rehace aquí (con sesión), nunca desde la vista previa anónima (revisión previa #92)
    $mapa = mapa_actualiza($slug, $c);
    json_response(['ok' => true, 'mapa' => $mapa]);
}
