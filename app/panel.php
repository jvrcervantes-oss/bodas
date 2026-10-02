<?php
// Panel de la pareja: carcasa y secciones (rediseño del 27-sep-2026, aprobado por el owner sobre el artifact
// «Panel de BodaEnlace»: «está perfecto, impleméntalo»). Encargo encargos/20260927_bodas_servicios_extra.md.
//
// QUÉ CAMBIÓ Y QUÉ NO:
//  · El panel deja de vestirse con el tema de la boda (tema_css) y lleva la marca de BodaEnlace, como el creador:
//    assets/marca.css (tokens y fuentes locales) + assets/panel.css. Nada de terceros: la CSP de la boda sigue igual.
//  · Cada sección es una página con URL propia (/panel, /panel/invitados…): se puede enlazar, recargar y volver atrás.
//  · NO hay lógica de datos nueva: todo sale de las funciones de siempre (rsvp_vigentes, personas, panel_datos,
//    inv_cruza, mesas_estado, libro_entradas…). Las acciones siguen en sus POST de siempre, con su CSRF.
//  · La carcasa nunca pinta nombres de invitados (solo cuentas): lo que se ve de una web archivada es la carcasa vacía.
//
// Contraste (≥ 4,5:1, exigido en el encargo): los grises y los colores de las etiquetas del artifact no llegaban
// (--ink-3 #8a8179 da 3,7:1 sobre blanco). panel.css usa tonos más oscuros para el texto pequeño; mismo aspecto.

declare(strict_types=1);

/** Secciones del menú: clave => [ruta bajo /panel, rótulo, grupo del menú lateral]. El orden es el del menú. */
const PANEL_SECCIONES = [
    'inicio' => ['', 'Inicio', 'La boda'],
    'invitados' => ['invitados', 'Invitados', 'La boda'],
    'respuestas' => ['respuestas', 'Respuestas', 'La boda'],
    'mesas' => ['mesas', 'Plano de mesas', 'Organizar'],
    'catering' => ['catering', 'Catering', 'Organizar'],
    'musica' => ['musica', 'Música', 'Organizar'],
    'galeria' => ['galeria', 'Galería y libro', 'Recuerdos'],
    'descargas' => ['descargas', 'Descargas y cuenta', 'Recuerdos'],
];
/** Barra inferior del móvil: lo demás cuelga de «Más». */
const PANEL_BARRA = ['inicio' => 'Inicio', 'invitados' => 'Invitados', 'respuestas' => 'Respuestas', 'mesas' => 'Mesas', 'mas' => 'Más'];
const PANEL_EN_MAS = ['catering', 'musica', 'galeria', 'descargas', 'mas'];

/** Iconos del panel (trazo, 24×24), los del artifact. Marcado fijo: ningún dato entra aquí. */
const PANEL_ICONOS = [
    'inicio' => '<path d="M3 11l9-7 9 7v9a1 1 0 01-1 1h-5v-6h-6v6H4a1 1 0 01-1-1z"/>',
    'invitados' => '<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0113 0M16 4.5a3.5 3.5 0 010 7M21.5 20a6.5 6.5 0 00-4-6"/>',
    'respuestas' => '<path d="M4 6h16M4 12h16M4 18h10"/><path d="M17 16l2 2 3-4"/>',
    'mesas' => '<circle cx="12" cy="12" r="4"/><circle cx="12" cy="3.5" r="1.5"/><circle cx="12" cy="20.5" r="1.5"/><circle cx="3.5" cy="12" r="1.5"/><circle cx="20.5" cy="12" r="1.5"/>',
    'catering' => '<path d="M4 3v8a3 3 0 003 3v7M7 3v5M10 3v8a3 3 0 01-3 3M17 3c-2 2-2 6 0 8v10"/>',
    'musica' => '<path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/>',
    'galeria' => '<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M21 16l-5-5-9 9"/>',
    'descargas' => '<path d="M12 3v12M7 10l5 5 5-5M4 21h16"/>',
    'mas' => '<circle cx="5" cy="12" r="1.5"/><circle cx="12" cy="12" r="1.5"/><circle cx="19" cy="12" r="1.5"/>',
    'editar' => '<path d="M12 20h9M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4z"/>',
    'ojo' => '<path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
    'buscar' => '<circle cx="11" cy="11" r="7"/><path d="M20 20l-4-4"/>',
    'salir' => '<path d="M15 4h4a1 1 0 011 1v14a1 1 0 01-1 1h-4M10 17l5-5-5-5M15 12H3"/>',
    'libro' => '<path d="M4 4h6a3 3 0 013 3v13a2 2 0 00-2-2H4zM20 4h-6a3 3 0 00-3 3v13a2 2 0 012-2h7z"/>',
    'recibo' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2zM9 8h6M9 12h6"/>',
    'llave' => '<circle cx="8" cy="15" r="4"/><path d="M11 12l9-9M17 6l3 3"/>',
    'hoja' => '<path d="M6 3h9l5 5v13H6zM14 3v6h6M9 13h8M9 17h6"/>',
];

function p_ico(string $k): string { return '<svg class="i" viewBox="0 0 24 24" aria-hidden="true">' . (PANEL_ICONOS[$k] ?? '') . '</svg>'; }
function panel_href(string $sec): string { $r = PANEL_SECCIONES[$sec][0] ?? $sec; return '/panel' . ($r !== '' ? '/' . $r : ''); }

/** «12 jun 2027» */
function panel_fecha_corta(string $ymd): string {
    $t = DateTimeImmutable::createFromFormat('!Y-m-d', $ymd);
    return $t ? (int) $t->format('j') . ' ' . mb_substr(MESES[(int) $t->format('n') - 1], 0, 3, 'UTF-8') . ' ' . $t->format('Y') : '';
}
function panel_mayuscula(string $s): string { return mb_strtoupper(mb_substr($s, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($s, 1, null, 'UTF-8'); }

/** «hace 2 horas», «ayer», «hace 5 días» o la fecha. */
function panel_hace(int $t): string {
    $d = time() - $t;
    if ($d < 3600) return $d < 120 ? 'hace un momento' : 'hace ' . intdiv($d, 60) . ' minutos';
    if ($d < 86400) return intdiv($d, 3600) === 1 ? 'hace una hora' : 'hace ' . intdiv($d, 3600) . ' horas';
    $dias = (int) (new DateTimeImmutable('@' . $t))->setTimezone(new DateTimeZone(date_default_timezone_get()))->setTime(0, 0)->diff(new DateTimeImmutable('today'))->days;
    if ($dias <= 1) return 'ayer';
    return $dias < 30 ? 'hace ' . $dias . ' días' : date('d/m/Y', $t);
}

/** Iniciales de un nombre de persona o de grupo (máx. 2 letras). */
function panel_iniciales(string $n): string {
    $todas = array_values(array_filter(preg_split('/\s+/u', trim($n)) ?: [], fn($x) => $x !== ''));
    $p = array_values(array_filter($todas, fn($x) => mb_strlen($x, 'UTF-8') > 2)) ?: $todas;   // «Familia de Lucía» → FL, no FD
    $o = '';
    foreach (array_slice($p, 0, 2) as $x) $o .= mb_strtoupper(mb_substr($x, 0, 1, 'UTF-8'), 'UTF-8');
    return $o !== '' ? $o : '·';
}

/** Pack de la boda para la tarjeta de la pareja (sin precio: el panel no enseña importes). */
function panel_pack(string $slug): string {
    return !empty((lee_json(dir_boda($slug) . '/pedido.json') ?? [])['atelier']) ? 'Pack Atelier' : 'Pack Esencial';
}

/** gid => nombre del grupo de la lista (para poner nombre a una respuesta llegada por su enlace). */
function panel_grupos_por_gid(string $slug): array {
    $inv = inv_lee($slug);
    $nombres = inv_grupos_de($inv['lista']);
    $o = [];
    foreach ($inv['grupos'] as $k => $e) if (is_array($e) && isset($nombres[$k])) $o[(string) ($e['gid'] ?? '')] = $nombres[$k]['nombre'];
    return $o;
}

// ---------------------------------------------------------------- carcasa

/**
 * Página del panel con la carcasa: menú lateral (escritorio), cabecera, contenido y barra inferior (móvil).
 * $o: 'qr' => carga el generador de QR (solo Inicio).
 */
function panel_pagina(string $slug, array $c, string $sec, string $titulo, string $cuerpo, array $o = []): string {
    header('Cache-Control: private, no-store');   // lleva datos de invitados (alergias = art. 9 RGPD)
    $pend = 0;
    $inv = inv_lee($slug);
    if ($inv['lista']) $pend = inv_cruza($slug, $c)[1]['pend'];
    $nav = '';
    $grupo = '';
    foreach (PANEL_SECCIONES as $k => [$ruta, $rot, $g]) {
        if ($g !== $grupo) { $grupo = $g; $nav .= '<p class="grupo">' . h($g) . '</p>'; }
        $nav .= '<a href="' . h(panel_href($k)) . '"' . ($k === $sec ? ' aria-current="page"' : '') . '>' . p_ico($k) . '<span>' . h($rot) . '</span>'
            . ($k === 'invitados' && $pend > 0 ? '<span class="cuenta" title="Sin contestar">' . $pend . '<span class="vh"> sin contestar</span></span>' : '') . '</a>';
    }
    $barra = '';
    foreach (PANEL_BARRA as $k => $rot) {
        $on = $k === $sec || ($k === 'mas' && in_array($sec, PANEL_EN_MAS, true));
        $barra .= '<a href="' . h($k === 'mas' ? '/panel/mas' : panel_href($k)) . '"' . ($on ? ' aria-current="page"' : '') . '>' . p_ico($k) . '<span>' . h($rot) . '</span></a>';
    }
    $n1 = (string) ($c['pareja']['nombre1'] ?? '');
    $n2 = (string) ($c['pareja']['nombre2'] ?? '');
    $ini = mb_strtoupper(mb_substr($n1, 0, 1, 'UTF-8') . mb_substr($n2, 0, 1, 'UTF-8'), 'UTF-8');
    $cuando = trim(panel_fecha_corta((string) ($c['fecha'] ?? '')) . ' · ' . panel_pack($slug), ' ·');
    $scripts = ($o['qr'] ?? false) ? '<script src="/assets/js/vendor/qrcode.js?v=' . h(ASSETS_V) . '" defer></script>' : '';
    $scripts .= '<script src="/assets/js/panel.js?v=' . h(ASSETS_V) . '" defer></script>';
    return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . h($titulo) . ' — ' . h(nombres($c) !== '' ? nombres($c) : MARCA) . '</title><meta name="robots" content="noindex, nofollow">' . favicon_links('')
        . '<link rel="stylesheet" href="/assets/marca.css?v=' . h(ASSETS_V) . '"><link rel="stylesheet" href="/assets/panel.css?v=' . h(ASSETS_V) . '">' . $scripts . '</head>'
        . '<body class="p-body"><a class="saltar" href="#contenido">Saltar al contenido</a><div class="app">'
        . '<aside class="rail no-print" aria-label="Menú del panel">' . logo_marca('marca', '/panel')
        . '<div class="pareja"><span class="ini" aria-hidden="true">' . h($ini !== '' ? $ini : '·') . '</span><div><b>' . h(nombres($c) !== '' ? nombres($c) : 'Vuestra boda') . '</b><small>' . h($cuando) . '</small></div></div>'
        . '<nav class="nav" aria-label="Secciones del panel">' . $nav . '</nav>'
        . '<div class="rail-pie"><a href="/panel/salir">' . p_ico('salir') . 'Salir</a></div></aside>'
        . '<div class="main"><header class="top no-print"><h1>' . h($titulo) . '</h1>'
        . '<a class="btn b-papel" href="/panel/editar">' . p_ico('editar') . '<span>Editar la web</span></a>'
        . '<a class="btn b-rosa" href="' . h(url_boda($slug)) . '" target="_blank" rel="noopener">' . p_ico('ojo') . '<span>Ver mi web</span></a></header>'
        . '<main class="cont" id="contenido" tabindex="-1">' . $cuerpo . '</main></div></div>'
        . '<nav class="tabbar no-print" aria-label="Secciones">' . $barra . '</nav></body></html>';
}

/** Páginas sin sesión (entrar, elegir contraseña, recuperar): la marca, sin menú. */
function panel_acceso_marco(array $c, string $titulo, string $cuerpo): string {
    header('Cache-Control: private, no-store');
    return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . h($titulo) . ' — ' . h(nombres($c) !== '' ? nombres($c) : MARCA) . '</title><meta name="robots" content="noindex, nofollow">' . favicon_links('')
        . '<link rel="stylesheet" href="/assets/marca.css?v=' . h(ASSETS_V) . '"><link rel="stylesheet" href="/assets/panel.css?v=' . h(ASSETS_V) . '"></head>'
        . '<body class="p-body p-acceso"><main class="acceso" id="contenido">' . logo_marca('marca', '/')
        . '<div class="card acceso-card"><p class="over">Panel de ' . h(nombres($c) !== '' ? nombres($c) : 'vuestra boda') . '</p>' . $cuerpo . '</div></main></body></html>';
}

/** Hojas para imprimir (plano de mesas): la marca, sin menú, con el botón de imprimir. */
function panel_hoja_marco(array $c, string $titulo, string $cuerpo): string {
    header('Cache-Control: private, no-store');
    return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . h($titulo) . ' — ' . h(nombres($c)) . '</title><meta name="robots" content="noindex, nofollow">' . favicon_links('')
        . '<link rel="stylesheet" href="/assets/marca.css?v=' . h(ASSETS_V) . '"><link rel="stylesheet" href="/assets/panel.css?v=' . h(ASSETS_V) . '">'
        . '<script src="/assets/js/panel.js?v=' . h(ASSETS_V) . '" defer></script></head>'
        . '<body class="p-body p-hoja"><main class="hoja" id="contenido">' . $cuerpo . '</main></body></html>';
}

/** Cabecera de tarjeta: título, texto y, a la derecha, lo que se pase (botón o etiqueta). */
/** $titulo y $sub son texto (se escapan aquí); $dcha es marcado ya hecho (un botón, una etiqueta). */
function panel_card_cab(string $titulo, string $sub = '', string $dcha = '', string $id = ''): string {
    return '<div class="card-cab"><div><h2' . ($id !== '' ? ' id="' . h($id) . '"' : '') . '>' . h($titulo) . '</h2>' . ($sub !== '' ? '<p class="sub">' . h($sub) . '</p>' : '') . '</div>' . $dcha . '</div>';
}

/** Filtros como enlaces (URL real, funcionan sin JS). $ops: clave => rótulo. */
function panel_filtros(string $base, array $ops, string $actual, array $extra = []): string {
    $o = '';
    foreach ($ops as $k => $rot) {
        $q = array_filter(['f' => $k === array_key_first($ops) ? '' : $k] + $extra, fn($v) => $v !== '');
        $o .= '<a href="' . h($base . ($q ? '?' . http_build_query($q) : '')) . '"' . ($k === $actual ? ' aria-current="true"' : '') . '>' . h($rot) . '</a>';
    }
    return $o;
}

// ---------------------------------------------------------------- inicio

function panel_inicio(string $slug, array $c): string {
    [$rsvps, $st, , $rep] = panel_datos($slug, $c);
    $vienen = 0; $noVienen = 0; $ninos = 0; $alergias = 0;
    foreach ($rsvps as $r) {
        $viene = !empty($r['asiste_ceremonia']) || !empty($r['asiste_banquete']);
        foreach (personas($r) as $p) {
            if (!$viene) { $noVienen++; continue; }
            $vienen++;
            if ($p['tipo'] === 'nino') $ninos++;
            if ($p['alergias'] !== '') $alergias++;
        }
    }
    $inv = inv_lee($slug);
    $hayLista = (bool) $inv['lista'];
    $res = $hayLista ? inv_cruza($slug, $c)[1] : null;
    $rsvpOn = (bool) seccion_tipo($c, 'rsvp');
    if ($rsvpOn) inv_asegura_grupos($slug);
    $sinAbrir = 0;
    if ($rsvpOn && $hayLista) {
        $inv = inv_lee($slug);
        $conf = array_flip(array_filter(array_map(fn($r) => (string) ($r['grupo'] ?? ''), $rsvps)));
        foreach (inv_grupos_de($inv['lista']) as $k => $g) {
            $e = $inv['grupos'][$k] ?? null;
            if (is_array($e) && !isset($conf[$e['gid']]) && (string) ($e['abierto'] ?? '') === '') $sinAbrir++;
        }
    }

    // Anillo: con lista, % de la lista que ya ha contestado; sin lista, % de quienes vienen entre los que contestaron
    if ($hayLista) {
        $total = array_sum($res);
        $pct = $total ? (int) round(($res['viene'] + $res['no']) / $total * 100) : 0;
        $anilloTxt = 'han respondido';
        $frase = ($res['viene'] + $res['no']) > 0 ? 'Ya han contestado ' . ($res['viene'] + $res['no']) . ' de ' . $total . ' invitados.' : 'Todavía no ha contestado nadie de vuestra lista.';
    } else {
        $pct = $st['personas'] ? (int) round($vienen / $st['personas'] * 100) : 0;
        $anilloTxt = $st['personas'] ? 'de los que contestan vienen' : 'aún sin respuestas';
        $frase = $st['personas'] ? $st['personas'] . ($st['personas'] === 1 ? ' persona ha contestado.' : ' personas han contestado.') . ' Pegad vuestra lista de invitados y os diremos quién falta.'
            : 'Todavía no ha contestado nadie. Compartid vuestra web para empezar.';
    }
    $archivada = ($c['_estado'] ?? '') === 'archivada';
    $lugar = (string) ($c['convite']['lugar'] ?? '') ?: (string) ($c['ceremonia']['lugar'] ?? '');
    $over = trim(panel_mayuscula(fecha_larga((string) $c['fecha'])) . ($lugar !== '' ? ' · ' . $lugar : ''), ' ·');
    $dias = null;
    $t = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $c['fecha']);
    if ($t) { $d = (new DateTimeImmutable('today'))->diff($t); $dias = $d->invert ? -$d->days : $d->days; }
    $cifra = $dias === null ? '' : ($dias > 0 ? '<div class="dias tab">' . $dias . '<small>' . ($dias === 1 ? 'día' : 'días') . '</small></div>'
        : ($dias === 0 ? '<div class="dias">¡Hoy!</div>' : '<div class="dias dias-txt">Ya os habéis casado</div>'));
    $estado = $archivada ? 'Vuestra web está archivada: las respuestas ya se han borrado.' : 'Vuestra web está publicada. ' . $frase;
    $acc = $sinAbrir > 0 ? '<a class="btn b-osc" href="/panel/invitados?f=sin">Mandar enlaces pendientes</a>' : '<a class="btn b-osc" href="/panel/invitados">Ver invitados</a>';
    $o = '';
    if (($_GET['compra'] ?? '') === '1') $o .= '<p class="aviso" role="status">Estamos confirmando el pago. Recargad esta página en un minuto; os llegará también un correo.</p>';
    $o .= '<section class="hero" aria-label="Resumen"><div>' . ($over !== '' ? '<span class="over">' . h($over) . '</span>' : '') . $cifra
        . '<p>' . h($estado) . '</p><div class="acc">' . $acc . '<a class="btn b-papel" href="/panel/respuestas">Ver respuestas</a></div></div>'
        . '<div class="anillo" role="img" aria-label="' . h($pct . ' % ' . $anilloTxt) . '"><svg viewBox="0 0 150 150" aria-hidden="true"><defs><linearGradient id="arcoG"><stop offset="0" stop-color="#c5a059"/><stop offset="1" stop-color="#a86b68"/></linearGradient></defs>'
        . '<circle class="f" cx="75" cy="75" r="60" pathLength="100"/><circle class="a" cx="75" cy="75" r="60" pathLength="100" stroke="url(#arcoG)" style="stroke-dashoffset:' . (100 - $pct) . '"/></svg>'
        . '<div class="t"><div><b class="tab">' . $pct . '%</b><span>' . h($anilloTxt) . '</span></div></div></div></section>';

    $kpi = fn(string $cls, string $n, string $t) => '<div class="kpi ' . $cls . '"><b>' . h($n) . '</b><span>' . h($t) . '</span></div>';
    $o .= '<div class="kpis tab">' . $kpi('ok', (string) $vienen, 'vienen') . $kpi('mal', (string) $noVienen, 'no vienen')
        . $kpi('warn', $hayLista ? (string) $res['pend'] : '—', 'sin responder') . $kpi('', (string) $ninos, $ninos === 1 ? 'niño/a' : 'niños/as')
        . $kpi('', (string) $alergias, 'con alergias')
        . ((pregunta_bus($c) || $st['bus'] > 0) ? $kpi('', (string) $st['bus'], 'en autobús') : '') . '</div>';

    // Lo que os queda: cada paso se tacha solo, leyendo el estado real
    $pasos = [['Publicar la web', true, '', '']];
    $pasos[] = ['Pegar la lista de invitados', $hayLista, '/panel/invitados?editar=1#lista', 'Añadir'];
    if ($rsvpOn && $hayLista) $pasos[] = [$sinAbrir ? 'Mandar su enlace a ' . $sinAbrir . ($sinAbrir === 1 ? ' grupo que no lo ha abierto' : ' grupos que no lo han abierto') : 'Mandar su enlace a cada grupo', $sinAbrir === 0, '/panel/invitados?f=sin', 'Mandar'];
    $pasos[] = ['Poner vuestra foto en la portada', is_file(dir_boda($slug) . '/foto.webp'), '/panel/editar', 'Poner'];
    if (extra_activo($slug, 'mesas')) {
        $E = mesas_estado($slug);
        $enBanquete = count($E['sin_mesa']) + array_sum(array_map(fn($m) => count($m['personas']), $E['mesas']));
        $pasos[] = [$enBanquete ? 'Sentar a los invitados en sus mesas' . ($E['sin_mesa'] ? ' (' . count($E['sin_mesa']) . ' sin mesa)' : '') : 'Sentar a los invitados en sus mesas (cuando confirmen)',
            $enBanquete > 0 && !$E['sin_mesa'] && !$E['avisos'], '/panel/mesas', $E['mesas'] ? 'Seguir' : 'Empezar'];
    }
    $gal = seccion_tipo($c, 'galeria');
    if ($gal && !$archivada) $pasos[] = ['Subir vuestras fotos a la galería', (bool) $gal['datos']['fotos'], '/panel/editar', 'Subir'];
    $lp = '';
    $i = 0;
    foreach ($pasos as [$txt, $hecho, $href, $bot]) {
        $i++;
        $lp .= '<li' . ($hecho ? ' class="hecho"' : '') . '><span class="c" aria-hidden="true">' . ($hecho ? '✓' : $i) . '</span><span>' . h($txt) . ($hecho ? '<span class="vh"> (hecho)</span>' : '') . '</span>'
            . (!$hecho && $href !== '' ? '<a class="btn b-sm b-papel" href="' . h($href) . '">' . h($bot) . '</a>' : '') . '</li>';
    }
    $o .= '<div class="rej r-12"><section class="card">' . panel_card_cab('Lo que os queda', 'En orden. Se tachan solos.') . '<ul class="pasos-lista">' . $lp . '</ul></section>'
        . '<div class="rej">' . bloque_compartir($slug, $c) . panel_ultimo($slug, $rsvps) . '</div></div>';
    if ($rep) $o .= '<p class="aviso">Hay nombres que aparecen en más de una respuesta. Miradlo en <a href="/panel/respuestas">Respuestas</a>: puede que alguien haya confirmado dos veces.</p>';
    return $o;
}

/** Compartir la web: enlace, QR para las invitaciones en papel y mensaje de WhatsApp ya escrito. */
function bloque_compartir(string $slug, array $c): string {
    $url = url_boda($slug);
    $msg = '¡Nos casamos! Aquí tenéis toda la información de nuestra boda y la confirmación de asistencia: ' . $url;
    if ((seccion_tipo($c, 'galeria') || seccion_tipo($c, 'libro')) && ($c['codigo'] ?? '') !== '') $msg .= "\nPara la galería y el libro de invitados, el código es " . $c['codigo'] . '.';
    return '<section class="card" id="compartir">' . panel_card_cab('Compartir la web', 'Para quien no esté en vuestra lista, y el QR para las invitaciones en papel.')
        . '<div class="compartir"><div id="qr" class="qr" data-url="' . h($url) . '" data-slug="' . h($slug) . '"></div><div class="compartir-txt">'
        . '<div class="url"><span>' . h(preg_replace('~^https?://~', '', rtrim($url, '/'))) . '</span><button type="button" class="btn b-sm b-papel" data-copiar-enlace="' . h($url) . '">Copiar</button><span class="copiado" role="status" hidden>Copiado</span></div>'
        . '<div class="fila-bot"><a class="btn b-sm b-osc" id="btnWa" href="https://wa.me/?text=' . h(rawurlencode($msg)) . '" target="_blank" rel="noopener">WhatsApp</a>'
        . '<button type="button" class="btn b-sm b-papel" data-qr-png>QR en PNG</button><button type="button" class="btn b-sm b-papel" data-qr-svg>QR para imprenta (SVG)</button></div>'
        . '<details class="detalle"><summary>Cambiar el mensaje de WhatsApp</summary><label for="msgWa" class="vh">Mensaje para WhatsApp</label><textarea id="msgWa" rows="4">' . h($msg) . '</textarea></details>'
        . '</div></div></section>';
}

/** Lo último que ha pasado: respuestas, enlaces abiertos, canciones propuestas y mensajes del libro, con su fecha real. */
function panel_ultimo(string $slug, array $rsvps): string {
    $ev = [];
    $gnom = panel_grupos_por_gid($slug);
    foreach ($rsvps as $r) {
        $t = strtotime((string) ($r['fecha_envio'] ?? ''));
        $ps = personas($r);
        if (!$t || !$ps) continue;
        $viene = !empty($r['asiste_ceremonia']) || !empty($r['asiste_banquete']);
        $quien = $gnom[(string) ($r['grupo'] ?? '')] ?? ($ps[0]['nombre'] . (count($ps) > 1 ? ' y ' . (count($ps) - 1) . ' más' : ''));
        $ev[] = [$t, $viene ? 'ok' : 'mal', panel_iniciales($quien), $quien . ($viene ? (count($ps) > 1 ? ' confirmaron' : ' confirmó') : (count($ps) > 1 ? ' no vienen' : ' no viene'))
            . ($viene ? ' · ' . count($ps) . (count($ps) === 1 ? ' persona' : ' personas') : '')];
    }
    $inv = inv_lee($slug);
    foreach (inv_grupos_de($inv['lista']) as $k => $g) {
        $a = (string) (($inv['grupos'][$k] ?? [])['abierto'] ?? '');
        if ($a !== '' && ($t = strtotime($a))) $ev[] = [$t, 'warn', panel_iniciales($g['nombre']), $g['nombre'] . ' abrió su enlace'];
    }
    foreach (lee_json(dir_boda($slug) . '/guardado/canciones.json') ?? [] as $s) {
        if (is_array($s) && ($t = strtotime((string) ($s['fecha'] ?? '')))) $ev[] = [$t, 'rosa', '♪', '«' . ($s['cancion'] ?? '') . '» propuesta' . ((int) ($s['votos'] ?? 0) ? ' · ' . (int) $s['votos'] . ' votos' : '')];
    }
    foreach (libro_entradas($slug) as $e) {
        if (($t = strtotime((string) ($e['fecha'] ?? '')))) $ev[] = [$t, '', panel_iniciales((string) $e['nombre']), $e['nombre'] . ' dejó un mensaje en el libro'];
    }
    usort($ev, fn($a, $b) => $b[0] <=> $a[0]);
    $li = '';
    foreach (array_slice($ev, 0, 5) as [$t, $cls, $ini, $txt]) {
        $li .= '<li><span class="av ' . $cls . '" aria-hidden="true">' . h($ini) . '</span><div>' . h($txt) . '<small>' . h(panel_hace($t)) . '</small></div></li>';
    }
    return '<section class="card">' . panel_card_cab('Lo último') . ($li !== '' ? '<ul class="feed">' . $li . '</ul>' : '<p class="sub">Aquí iréis viendo las respuestas, los enlaces que se abren y las canciones que proponen.</p>') . '</section>';
}

// ---------------------------------------------------------------- invitados

/** Grupos de la lista con su estado (Confirmado / No vienen / Abierto / Sin abrir), su enlace y sus menús. */
function panel_grupos(string $slug, array $c): array {
    if (!seccion_tipo($c, 'rsvp')) return [];
    inv_asegura_grupos($slug);
    $inv = inv_lee($slug);
    $porGid = [];
    foreach (rsvp_vigentes($slug) as $r) if (($r['grupo'] ?? '') !== '') $porGid[(string) $r['grupo']] = $r;
    $o = [];
    foreach (inv_grupos_de($inv['lista']) as $k => $g) {
        $e = $inv['grupos'][$k] ?? null;
        if (!is_array($e)) continue;
        $r = $porGid[$e['gid']] ?? null;
        $menus = [];
        if ($r) {
            $viene = !empty($r['asiste_ceremonia']) || !empty($r['asiste_banquete']);
            $estado = $viene ? 'conf' : 'no';
            if ($viene) foreach (personas($r) as $p) { $m = nombre_menu($c, $p['menu'], $p['menu_nombre']); $menus[$m] = ($menus[$m] ?? 0) + 1; }
        } else {
            $estado = (string) ($e['abierto'] ?? '') !== '' ? 'abierto' : 'sin';
        }
        $o[] = ['gid' => $e['gid'], 'token' => $e['token'], 'nombre' => $g['nombre'], 'personas' => $g['personas'], 'estado' => $estado,
            'abierto' => (string) ($e['abierto'] ?? ''), 'menus' => $menus];
    }
    return $o;
}

const PANEL_EST_GRUPO = ['sin' => ['Sin abrir', ''], 'abierto' => ['Abierto, sin responder', 'warn'], 'conf' => ['Confirmado', 'ok'], 'no' => ['No vienen', 'mal']];

function panel_invitados(string $slug, array $c): string {
    $inv = inv_lee($slug);
    $csrf = '<input type="hidden" name="csrf" value="' . h(panel_csrf()) . '">';
    $texto = implode("\n", array_map(fn($g) => $g['nombre'] . ($g['grupo'] !== '' ? '; ' . $g['grupo'] : ''), $inv['lista']));
    $nota = '<p class="sub">Solo nombres y, si queréis, un grupo (por ejemplo «Familia de Lucía»). Ni teléfonos ni alergias: eso ya lo dejan ellos al confirmar. Esta lista solo la veis vosotros y se borra junto con las respuestas.</p>';
    $form = '<form method="post" action="/panel/invitados" class="form-lista">' . $csrf . '<input type="hidden" name="accion" value="lista">'
        . '<label for="invLista">Una persona por línea. Para el grupo, un punto y coma: <i>Ana García; Familia de Lucía</i>. También podéis pegar dos columnas de Excel.</label>'
        . '<textarea id="invLista" name="lista" rows="9" maxlength="' . INVITADOS_MAX_BYTES . '" spellcheck="false">' . h($texto) . '</textarea>'
        . '<div class="fila-bot"><button class="btn b-rosa" type="submit">Guardar lista</button></div></form>';
    if (!$inv['lista']) {
        return '<section class="card" id="lista">' . panel_card_cab('Vuestra lista de invitados', 'Pegad aquí a quién habéis invitado: os diremos quién falta por contestar y cada grupo tendrá su enlace personal.') . $nota . $form . '</section>';
    }
    $q = clean_str($_GET['q'] ?? '', 60);
    $kq = clave_nombre($q);
    $coincide = fn(string ...$t) => $kq === '' || (bool) array_filter($t, fn($x) => strpos(clave_nombre($x), $kq) !== false);
    $o = '';

    // Grupos con su enlace personal
    $G = panel_grupos($slug, $c);
    if ($G) {
        $f = (string) ($_GET['f'] ?? '');
        if (!isset(PANEL_EST_GRUPO[$f])) $f = 'todos';
        $cuenta = array_count_values(array_column($G, 'estado'));
        $ops = ['todos' => 'Todos · ' . count($G)];
        foreach (PANEL_EST_GRUPO as $k => [$rot]) $ops[$k] = explode(',', $rot)[0] . ' · ' . ($cuenta[$k] ?? 0);
        $filas = '';
        foreach ($G as $g) {
            if (($f !== 'todos' && $g['estado'] !== $f) || !$coincide($g['nombre'], ...$g['personas'])) continue;
            [$et, $cls] = PANEL_EST_GRUPO[$g['estado']];
            if ($g['estado'] === 'abierto') $et = 'Abierto el ' . date('d/m/Y', (int) strtotime($g['abierto'])) . ', sin responder';
            $url = url_boda($slug, 'i/' . $g['token']);
            $msg = '¡Hola, ' . $g['nombre'] . '! Nos casamos y nos encantaría que vinierais. En este enlace tenéis toda la información y podéis confirmar: ' . $url;
            $menus = $g['menus'] ? implode(' · ', array_map(fn($m, $n) => $m . ($n > 1 ? ' ×' . $n : ''), array_keys($g['menus']), $g['menus'])) : '—';
            $filas .= '<tr><td><b>' . h($g['nombre']) . '</b><div class="muted">' . count($g['personas']) . (count($g['personas']) === 1 ? ' persona' : ' personas')
                . (count($g['personas']) > 1 || $g['personas'][0] !== $g['nombre'] ? ': ' . h(implode(', ', $g['personas'])) : '') . '</div><code class="enl-url">' . h($url) . '</code></td>'
                . '<td><span class="chip ' . $cls . '">' . h($et) . '</span></td><td class="muted">' . h($menus) . '</td>'
                . '<td><div class="acc"><button type="button" class="btn b-sm b-papel" data-copiar-enlace="' . h($url) . '">Copiar enlace</button><span class="copiado" role="status" hidden>Copiado</span>'
                . '<a class="btn b-sm b-osc" href="https://wa.me/?text=' . h(rawurlencode($msg)) . '" target="_blank" rel="noopener">WhatsApp</a>'
                . '<form method="post" action="/panel/invitados">' . $csrf . '<input type="hidden" name="accion" value="rotar"><input type="hidden" name="gid" value="' . h($g['gid']) . '">'
                . '<button class="btn b-sm b-papel" data-confirmar="Se crea un enlace nuevo y el que ya mandasteis deja de funcionar. ¿Seguimos?" title="Crea un enlace nuevo; el anterior deja de funcionar">Cambiar enlace</button></form></div></td></tr>';
        }
        $o .= '<section class="card" id="enlaces">' . panel_card_cab('Vuestra lista', 'Cada grupo tiene su enlace personal: al abrirlo, la web le saluda por su nombre y ya trae sus nombres escritos. Si confirman dos veces, vale la última.',
                '<a class="btn b-rosa" href="/panel/invitados?editar=1#lista">Añadir invitados</a>')
            . '<div class="filtros"><nav class="chips" aria-label="Filtrar por estado">' . panel_filtros('/panel/invitados', $ops, $f, ['q' => $q]) . '</nav>'
            . '<form class="buscar" method="get" action="/panel/invitados" role="search">' . p_ico('buscar') . ($f !== 'todos' ? '<input type="hidden" name="f" value="' . h($f) . '">' : '')
            . '<label for="q" class="vh">Buscar grupo o persona</label><input id="q" name="q" type="search" value="' . h($q) . '" placeholder="Buscar grupo o persona"></form></div>'
            . ($filas !== '' ? '<div class="tabla-w"><table class="t t-grupos"><thead><tr><th>Grupo</th><th>Estado</th><th>Menús</th><th><span class="vh">Enlace</span></th></tr></thead><tbody>' . $filas . '</tbody></table></div>'
                : '<p class="vacio">Ningún grupo con ese filtro.</p>')
            . '<p class="nota">«Abierto» es orientativo: se apunta la primera vez que alguien abre el enlace, sin guardar nada más, y no cuentan las vistas previas de WhatsApp ni cuando lo abrís vosotros desde aquí.</p></section>';
    }

    // Persona a persona: quién falta por contestar, cruzado por nombre con las respuestas
    [$filasP, $res] = inv_cruza($slug, $c);
    $pend = array_values(array_filter($filasP, fn($x) => $x['estado'] === 'pend'));
    $etq = ['pend' => ['Sin contestar', 'warn'], 'no' => ['No viene', 'mal'], 'viene' => ['Viene', 'ok']];
    $tb = '';
    foreach ($filasP as $x) {
        if (!$coincide($x['nombre'], $x['grupo'])) continue;
        $botones = '';
        foreach (['viene' => 'Viene', 'no' => 'No viene', 'pend' => 'Sin contestar'] as $k => $t) {
            if ($k !== $x['estado']) $botones .= '<button class="btn b-sm b-papel" name="estado" value="' . $k . '">' . $t . '</button>';
        }
        if ($x['manual']) $botones .= '<button class="btn b-sm b-papel" name="estado" value="auto" title="Volver a lo que digan las confirmaciones">Automático</button>';
        $tb .= '<tr><td><b>' . h($x['nombre']) . '</b></td><td class="muted">' . h($x['grupo']) . '</td><td><span class="chip ' . $etq[$x['estado']][1] . '">' . $etq[$x['estado']][0] . '</span>' . ($x['manual'] ? ' <span class="muted">(a mano)</span>' : '') . '</td>'
            . '<td><form method="post" action="/panel/invitados" class="acc">' . $csrf . '<input type="hidden" name="accion" value="marcar"><input type="hidden" name="id" value="' . h($x['id']) . '">' . $botones . '</form></td></tr>';
    }
    // Plegada (el artifact solo enseña los grupos): se abre sola al buscar, sin enlaces por grupo, o al volver de corregir (#personas, panel.js)
    $abierta = $q !== '' || !$G;
    $o .= '<details class="card detalle-card" id="personas"' . ($abierta ? ' open' : '') . '><summary><span><span class="h2">Persona a persona</span>'
        . '<span class="sub">' . count($filasP) . ' invitados · ' . $res['pend'] . ' sin contestar. Se cruza por nombre con las confirmaciones: si alguien contestó con otro nombre o por teléfono, corregidlo a mano.</span></span></summary>'
        . ($pend ? '<p class="fila-bot"><button type="button" class="btn b-papel" data-copiar-pendientes="' . h(implode("\n", array_column($pend, 'nombre'))) . '">Copiar los que faltan</button><span class="copiado inv-copiado" role="status" hidden>Copiado</span></p>' : '')
        . '<div class="kpis kpis-4 tab"><div class="kpi"><b>' . count($filasP) . '</b><span>invitados</span></div><div class="kpi ok"><b>' . $res['viene'] . '</b><span>vienen</span></div>'
        . '<div class="kpi mal"><b>' . $res['no'] . '</b><span>no vienen</span></div><div class="kpi warn"><b>' . $res['pend'] . '</b><span>sin contestar</span></div></div>'
        . ($tb !== '' ? '<div class="tabla-w"><table class="t"><thead><tr><th>Nombre</th><th>Grupo</th><th>Estado</th><th>Corregir</th></tr></thead><tbody>' . $tb . '</tbody></table></div>' : '<p class="vacio">Nadie con esa búsqueda.</p>')
        . '</details>';

    $o .= '<details class="card detalle-card" id="lista"' . (($_GET['editar'] ?? '') === '1' ? ' open' : '') . '><summary><span class="h2">Añadir o editar la lista</span></summary>' . $nota . $form . '</details>';
    return $o;
}

// ---------------------------------------------------------------- respuestas

const PANEL_FILTROS_RESP = ['todas' => 'Todas', 'banquete' => 'Banquete', 'alergias' => 'Con alergias', 'bus' => 'Autobús', 'no' => 'No vienen'];

function panel_respuestas(string $slug, array $c): string {
    [$rsvps, $st, , $rep] = panel_datos($slug, $c);
    $bus = pregunta_bus($c) || (bool) array_filter($rsvps, fn($r) => !empty($r['necesita_bus']));
    $f = (string) ($_GET['f'] ?? '');
    if (!isset(PANEL_FILTROS_RESP[$f]) || ($f === 'bus' && !$bus)) $f = 'todas';
    $pasa = function (array $r, string $f): bool {
        $viene = !empty($r['asiste_ceremonia']) || !empty($r['asiste_banquete']);
        switch ($f) {
            case 'banquete': return !empty($r['asiste_banquete']);
            case 'alergias': return (bool) array_filter(personas($r), fn($p) => $p['alergias'] !== '');
            case 'bus': return !empty($r['necesita_bus']);
            case 'no': return !$viene;
        }
        return true;
    };
    $ops = [];
    foreach (PANEL_FILTROS_RESP as $k => $rot) {
        if ($k === 'bus' && !$bus) continue;
        $ops[$k] = $rot . ' · ' . count(array_filter($rsvps, fn($r) => $pasa($r, $k)));
    }
    $gnom = panel_grupos_por_gid($slug);
    $ultima = $rsvps ? max(array_map(fn($r) => (int) strtotime((string) ($r['fecha_envio'] ?? '')), $rsvps)) : 0;
    $sub = $st['personas'] ? $st['personas'] . ($st['personas'] === 1 ? ' persona ha' : ' personas han') . ' contestado en ' . count($rsvps) . (count($rsvps) === 1 ? ' respuesta' : ' respuestas')
        . ($ultima ? ' · la última, ' . panel_hace($ultima) : '') . '.' : 'Todavía no ha contestado nadie.';
    $o = '<section class="card">' . panel_card_cab('Respuestas', $sub, '<a class="btn b-papel" href="/panel/excel">' . p_ico('descargas') . 'Excel</a>');
    $o .= '<div class="cifras tab">';
    foreach ([['personas', 'personas'], ['adultos', 'adultos'], ['ninos', 'niños/as'], ['ceremonia', 'a la ceremonia'], ['banquete', 'al banquete']] as [$k, $t]) $o .= '<span><b>' . $st[$k] . '</b> ' . $t . '</span>';
    if ($bus) $o .= '<span><b>' . $st['bus'] . '</b> en autobús</span>';
    $o .= '</div>';
    if ($rep) $o .= '<p class="aviso">Hay nombres que aparecen en más de una respuesta (marcados con «repetido»). Puede que alguien haya confirmado dos veces.</p>';
    $mismas = rsvp_posibles_mismas($rsvps);
    $o .= panel_misma_confirmar($c, $rsvps, $gnom);
    $o .= '<nav class="filtros chips" aria-label="Filtrar respuestas">' . panel_filtros('/panel/respuestas', $ops, $f) . '</nav>';
    $filas = '';
    foreach (array_reverse($rsvps) as $r) {
        if (!$pasa($r, $f)) continue;
        $cer = !empty($r['asiste_ceremonia']);
        $ban = !empty($r['asiste_banquete']);
        $asis = $cer && $ban ? 'Ceremonia y banquete' : ($cer ? 'Solo ceremonia' : ($ban ? 'Solo banquete' : ''));
        $quien = $menu = $alerg = '';
        foreach (personas($r) as $p) {
            $quien .= '<div class="persona"><b>' . h($p['nombre']) . '</b>' . ($p['tipo'] === 'nino' ? '<span class="muted"> · niño/a</span>' : '')
                . (in_array(clave_nombre($p['nombre']), $rep, true) ? '<span class="rep"> · repetido</span>' : '') . '</div>';
            $menu .= '<div>' . h(nombre_menu($c, $p['menu'], $p['menu_nombre'])) . '</div>';
            $alerg .= '<div class="alergia">' . ($p['alergias'] !== '' ? h($p['alergias']) : '&nbsp;') . '</div>';
        }
        // Respuesta general con nombres de un grupo que respondió por su enlace: la pareja decide si es la misma (BOD-22)
        foreach ($mismas[(string) ($r['id'] ?? '')] ?? [] as $idg) {
            $rg = array_values(array_filter($rsvps, fn($x) => ($x['id'] ?? '') === $idg))[0] ?? [];
            $quien .= '<div class="misma"><a href="/panel/respuestas?' . h(http_build_query(['misma' => $r['id'], 'con' => $idg])) . '#misma">¿Es la misma que la de «'
                . h($gnom[(string) ($rg['grupo'] ?? '')] ?? 'su grupo') . '»?</a></div>';
        }
        $g = $gnom[(string) ($r['grupo'] ?? '')] ?? '';
        $hueco = $g !== '' ? '<div class="muted" aria-hidden="true">&nbsp;</div>' : '';   // menú y alergia a la altura de su persona
        $filas .= '<tr><td>' . ($g !== '' ? '<div class="muted">' . h($g) . '</div>' : '') . $quien . '</td>'
            . '<td>' . ($asis !== '' ? h($asis) : '<span class="chip mal">No vienen</span>') . '</td><td class="muted">' . $hueco . $menu . '</td><td>' . $hueco . $alerg . '</td>'
            . ($bus ? '<td>' . (!empty($r['necesita_bus']) ? 'Sí' : 'No') . '</td>' : '')
            . '<td>' . h($r['contacto'] ?? '') . (($r['cancion'] ?? '') !== '' ? '<div class="muted">♪ ' . h($r['cancion']) . '</div>' : '') . '</td>'
            . '<td class="muted">' . h(isset($r['fecha_envio']) ? date('d/m/Y H:i', (int) strtotime($r['fecha_envio'])) : '') . '</td></tr>';
    }
    if ($filas === '') $o .= '<p class="vacio">' . ($rsvps ? 'Ninguna respuesta con ese filtro.' : 'Todavía no hay confirmaciones.') . '</p>';
    else $o .= '<div class="tabla-w"><table class="t"><thead><tr><th>Quién</th><th>Asistencia</th><th>Menú</th><th>Alergias</th>' . ($bus ? '<th>Bus</th>' : '') . '<th>Contacto</th><th>Enviado</th></tr></thead><tbody>' . $filas . '</tbody></table></div>';
    return $o . '<p class="nota">Si un grupo corrigió su respuesta por su enlace, vale la última. Las respuestas se borran el ' . h(fecha_larga(fecha_borrado((string) $c['fecha']), false)) . '.</p></section>';
}

/**
 * Confirmación de «es la misma respuesta» (BOD-22, Seguridad 27-sep-2026): enseña las dos respuestas con sus alergias
 * lado a lado y avisa si la general trae alergias que la del grupo no, porque al confirmar se borran. Solo con dos
 * respuestas vigentes, la primera sin grupo y la segunda con grupo; si no, no enseña nada. El POST lo vuelve a comprobar.
 */
function panel_misma_confirmar(array $c, array $rsvps, array $gnom): string {
    $idG = (string) ($_GET['misma'] ?? '');
    $idC = (string) ($_GET['con'] ?? '');
    if ($idG === '' || $idC === '') return '';
    $porId = array_column(array_filter($rsvps, fn($r) => is_string($r['id'] ?? null)), null, 'id');
    $gen = $porId[$idG] ?? null;
    $gru = $porId[$idC] ?? null;
    if (!$gen || !$gru || (string) ($gen['grupo'] ?? '') !== '' || (string) ($gru['grupo'] ?? '') === '') return '';
    $lista = function (array $r): string {
        $o = '<ul class="misma-lista">';
        foreach (personas($r) as $p) $o .= '<li><b>' . h($p['nombre']) . '</b>' . ($p['alergias'] !== '' ? ' · alergias: ' . h($p['alergias']) : ' · sin alergias') . '</li>';
        return $o . '</ul>';
    };
    // Alergias de la general que la del grupo no trae para esa persona (por nombre y por TEXTO: «marisco» en la general y
    // «gluten» en la del grupo también se pierde, revisor 27-sep): al confirmar se borran
    $delGrupo = [];
    foreach (personas($gru) as $p) $delGrupo[clave_nombre($p['nombre'])] = ($delGrupo[clave_nombre($p['nombre'])] ?? '') . ' ' . clave_nombre($p['alergias']);
    $pierde = array_filter(personas($gen), fn($p) => $p['alergias'] !== ''
        && !str_contains($delGrupo[clave_nombre($p['nombre'])] ?? '', clave_nombre($p['alergias'])));
    $grupo = $gnom[(string) $gru['grupo']] ?? 'su grupo';
    $o = '<section class="card misma-card" id="misma"><h2 class="h2">¿Es la misma respuesta?</h2>'
        . '<p>Si lo es, cuenta solo la que «' . h($grupo) . '» mandó por su enlace, y la otra deja de contar en el panel, el Excel, el catering y el plano.</p>'
        . '<div class="rej r-12"><div><h3 class="h3">Por la confirmación general</h3>' . $lista($gen) . '</div><div><h3 class="h3">Por el enlace de «' . h($grupo) . '»</h3>' . $lista($gru) . '</div></div>';
    if ($pierde) {
        $o .= '<p class="aviso">Ojo: la respuesta general trae alergias que la del enlace no trae ('
            . h(implode(', ', array_map(fn($p) => $p['nombre'] . ': ' . $p['alergias'], $pierde)))
            . '). Al confirmar se borran. Si siguen siendo ciertas, pedid al grupo que vuelva a responder por su enlace con ellas.</p>';
    }
    return $o . '<form method="post" action="/panel/respuestas/misma" class="fila-bot"><input type="hidden" name="csrf" value="' . h(panel_csrf()) . '">'
        . '<input type="hidden" name="general" value="' . h($idG) . '"><input type="hidden" name="grupo" value="' . h($idC) . '">'
        . '<button type="submit" class="btn">Sí, es la misma</button> <a class="btn b-papel" href="/panel/respuestas">No, dejar las dos</a></form></section>';
}

/** POST /panel/respuestas/misma (sesión ya exigida por rutas_panel; CSRF aquí). Todo se comprueba dentro del bloqueo. */
function panel_respuestas_misma(string $slug, array $c, string $metodo): void {
    if ($metodo !== 'POST') { header('Allow: POST'); http_response_code(405); exit; }
    if (!panel_csrf_ok()) { http_response_code(403); exit; }
    $general = (string) ($_POST['general'] ?? '');
    $grupo = (string) ($_POST['grupo'] ?? '');
    $motivo = 'no-existe';
    if (preg_match('/^[A-Za-z0-9_-]{1,40}$/', $general) && preg_match('/^[A-Za-z0-9_-]{1,40}$/', $grupo)) {
        $motivo = muta_json(dir_boda($slug) . '/guardado/rsvp.json', fn(array &$d) => rsvp_misma($d, $general, $grupo)) ?? 'disco';
    }
    if ($motivo !== '') {
        http_response_code(409);
        echo panel_pagina($slug, $c, 'respuestas', PANEL_SECCIONES['respuestas'][1], '<section class="card"><p>Esas respuestas ya no se pueden juntar: puede que una ya no cuente o que el grupo haya vuelto a responder.</p>'
            . '<p><a class="btn b-papel" href="/panel/respuestas">Volver a Respuestas</a></p></section>');
        return;
    }
    registra('respuesta general marcada como la misma que la de un grupo', ['slug' => $slug]);
    header('Location: /panel/respuestas', true, 303);
}

// ---------------------------------------------------------------- música

/** Clave para reconocer la misma canción aunque cambien mayúsculas, tildes o espacios. Los alfabetos que iconv no pasa a ASCII
 *  (cirílico, CJK…) se comparan tal cual en minúsculas: sin esto su clave sería vacía y quitar una bloquearía todas las del artista. */
function cancion_clave(string $artista, string $cancion): string {
    $n = function (string $t): string {
        $a = preg_replace('/[^a-z0-9]+/', '', strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $t)));
        return $a !== '' ? $a : preg_replace('/[^\p{L}\p{N}]+/u', '', mb_strtolower($t, 'UTF-8'));
    };
    return $n($artista) . '|' . $n($cancion);
}

/** POST /panel/musica/quitar (sesión ya exigida por rutas_panel; CSRF aquí). Apunta primero su clave para que no vuelvan a proponerla y después la quita. */
function panel_musica_quitar(string $slug, array $c, string $metodo): void {
    if ($metodo !== 'POST') { header('Allow: POST'); http_response_code(405); exit; }
    if (!panel_csrf_ok()) { http_response_code(403); exit; }
    $id = (string) ($_POST['id'] ?? '');
    if (preg_match('/^[A-Za-z0-9_-]{1,40}$/', $id)) {
        $dir = dir_boda($slug) . '/guardado/';
        $quitar = null;
        foreach (lee_json($dir . 'canciones.json') ?? [] as $r) if (is_array($r) && ($r['id'] ?? '') === $id) $quitar = $r;
        if ($quitar !== null) {
            $k = cancion_clave((string) ($quitar['artista'] ?? ''), (string) ($quitar['cancion'] ?? ''));
            // Primero se apunta: si no cabe o falla el disco, la canción se queda en vez de quitarse sin bloquear
            $apuntada = muta_json($dir . 'canciones_quitadas.json', function (array &$d) use ($k) {
                if (in_array($k, $d, true)) return true;
                if (count($d) >= 2000) return false;
                $d[] = $k; return true;
            }, 131072);
            if ($apuntada === true) {
                muta_json($dir . 'canciones.json', function (array &$d) use ($id) {
                    $d = array_values(array_filter($d, fn($r) => !(is_array($r) && ($r['id'] ?? '') === $id)));
                    return true;
                }, MAX_BYTES_CANCIONES);
                registra('canción quitada desde el panel', ['slug' => $slug]);
            }
        }
    }
    header('Location: /panel/musica', true, 303);
}

function panel_musica(string $slug, array $c): string {
    $canciones = array_values(array_filter(lee_json(dir_boda($slug) . '/guardado/canciones.json') ?? [], 'is_array'));
    usort($canciones, fn($a, $b) => ((int) ($b['votos'] ?? 0)) <=> ((int) ($a['votos'] ?? 0)));
    $max = max(1, ...array_map(fn($s) => (int) ($s['votos'] ?? 0), $canciones ?: [[]]));
    $pedidas = [];
    foreach (rsvp_vigentes($slug) as $r) if (($r['cancion'] ?? '') !== '') $pedidas[] = [(string) $r['cancion'], (string) (personas($r)[0]['nombre'] ?? '')];
    $dj = [];
    foreach ($canciones as $i => $s) $dj[] = ($i + 1) . '. ' . ($s['cancion'] ?? '') . ' — ' . ($s['artista'] ?? '') . ' (' . (int) ($s['votos'] ?? 0) . ' votos)';
    foreach ($pedidas as [$can]) $dj[] = '· ' . $can;
    $on = (bool) seccion_tipo($c, 'musica');
    $o = '<section class="card">' . panel_card_cab('Música', $on ? 'Canciones que proponen y votan vuestros invitados en la web.' : 'La sección Música no está activa en vuestra web. Podéis activarla en Editar la web → Más secciones.',
        $dj ? '<span class="fila-bot"><button type="button" class="btn b-papel" data-copiar-texto="' . h(implode("\n", $dj)) . '">Copiar lista para el DJ</button><span class="copiado" role="status" hidden>Copiada</span></span>' : '');
    if (!$canciones) $o .= '<p class="vacio">Todavía no hay canciones propuestas.</p>';
    foreach ($canciones as $i => $s) {
        $v = (int) ($s['votos'] ?? 0);
        $o .= '<div class="cancion"><span class="n">' . ($i + 1) . '</span><div><b>' . h($s['cancion'] ?? '') . '</b><small>' . h($s['artista'] ?? '') . '</small></div>'
            . '<div class="barra" aria-hidden="true"><i style="width:' . round($v / $max * 100) . '%"></i></div><span class="tab">' . $v . '<span class="vh"> votos</span></span>'
            . '<form method="post" action="/panel/musica/quitar" class="quitar"><input type="hidden" name="csrf" value="' . h(panel_csrf()) . '"><input type="hidden" name="id" value="' . h((string) ($s['id'] ?? '')) . '">'
            . '<button type="submit" class="btn b-sm b-papel" aria-label="Quitar ' . h(($s['cancion'] ?? '') . ' — ' . ($s['artista'] ?? '')) . '" data-confirmar="¿Quitar esta canción de la lista? Los invitados no podrán volver a proponerla.">Quitar</button></form></div>';
    }
    $o .= '</section>';
    if ($pedidas) {
        $o .= '<section class="card">' . panel_card_cab('Pedidas al confirmar', 'La canción que escribieron en su respuesta.') . '<ul class="lista-simple">';
        foreach ($pedidas as [$can, $quien]) $o .= '<li><b>' . h($can) . '</b>' . ($quien !== '' ? '<small>' . h($quien) . '</small>' : '') . '</li>';
        $o .= '</ul></section>';
    }
    return $o;
}

// ---------------------------------------------------------------- galería y libro

function panel_galeria(string $slug, array $c): string {
    $gi = null;
    foreach ($c['secciones'] as $s) if ($s['tipo'] === 'galeria') $gi = $s;
    $on = $gi && $gi['on'];
    $fotos = $on ? $gi['datos']['fotos'] : [];
    $codigo = (string) ($c['codigo'] ?? '');
    $o = '<div class="rej r-12"><section class="card" id="galeria">';
    if (($c['_estado'] ?? '') === 'archivada') {
        $o .= panel_card_cab('Galería', 'Vuestra web está archivada: la galería y el libro ya no se ven ni admiten fotos nuevas.');
    } elseif (!$on) {
        $o .= panel_card_cab('Galería', 'La galería no está activa en vuestra web. Podéis activarla en Editar la web → Más secciones y volver aquí a subir las fotos.',
            '<a class="btn b-papel" href="/panel/editar">Editar la web</a>');
    } else {
        // Subir, ordenar y quitar fotos se hace en el editor, que es quien guarda el config entero: una subida desde
        // aquí la borraría el «Guardar» de una pestaña del editor abierta antes (revisión de código, 27-sep-2026)
        $o .= panel_card_cab('Galería', count($fotos) . ' de ' . MAX_GALERIA . ' fotos' . ($codigo !== '' ? ' · protegida con el código «' . $codigo . '»' : ''),
            '<a class="btn b-rosa" href="/panel/editar">Subir fotos</a>');
        $o .= '<p class="sub">Las fotos se suben, se ordenan y llevan su pie en <a href="/panel/editar">Editar la web</a> → Más secciones → Galería.</p>';
        if (!$fotos) $o .= '<p class="vacio">Todavía no hay fotos. Subid las vuestras: los invitados las verán con el código de la boda.</p>';
        else {
            $o .= '<div class="fotos">';
            foreach ($fotos as $i => $f) {
                $o .= '<figure class="foto"><img src="/g/' . h($f['id']) . '.webp" alt="' . h($f['pie'] !== '' ? $f['pie'] : 'Foto ' . ($i + 1) . ' de la galería') . '" loading="lazy" decoding="async">'
                    . ($i === 0 ? '<span class="et">Portada</span>' : '') . '</figure>';
            }
            $o .= '</div>';
        }
    }
    $o .= '</section>';

    $libro = libro_entradas($slug);
    $o .= '<section class="card" id="libro">';
    if (!seccion_tipo($c, 'libro') && !$libro) {
        $o .= panel_card_cab('Libro de invitados', 'El libro no está activo en vuestra web. Podéis activarlo en Editar la web → Más secciones.');
    } else {
        $vis = count(array_filter($libro, fn($e) => empty($e['oculto'])));
        $o .= panel_card_cab('Libro de invitados', (count($libro) . (count($libro) === 1 ? ' mensaje' : ' mensajes') . ($vis !== count($libro) ? ' · ' . (count($libro) - $vis) . ' oculto(s)' : '')
            . '. Se publican al momento: podéis ocultar o borrar cualquiera; si alguien os pide retirar algo, hacedlo aquí.'));
        if (!$libro) $o .= '<p class="vacio">Todavía no hay mensajes.</p>';
        $csrf = h(panel_csrf());
        foreach (array_reverse($libro) as $e) {
            $oc = !empty($e['oculto']);
            $o .= '<article class="msg' . ($oc ? ' is-oculto' : '') . '">'
                . (!empty($e['foto']) ? '<img src="/l/' . h($e['foto']) . '.webp?t=' . h(firma_img($slug, (string) $e['foto'])) . '" alt="Foto que dejó ' . h($e['nombre']) . '" loading="lazy">' : '')
                . '<div>' . parrafos((string) $e['mensaje']) . '<small>' . h($e['nombre']) . ' · ' . h(panel_hace((int) strtotime((string) $e['fecha']))) . ($oc ? ' · oculto' : '') . '</small>'
                . '<form method="post" action="/panel/libro" class="acc"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="id" value="' . h($e['id']) . '">'
                . '<button class="btn b-sm b-papel" name="accion" value="' . ($oc ? 'mostrar' : 'ocultar') . '">' . ($oc ? 'Mostrar' : 'Ocultar') . '</button>'
                . '<button class="btn b-sm b-papel" name="accion" value="borrar" data-confirmar="¿Borrar este mensaje? No se puede deshacer.">Borrar</button></form></div></article>';
        }
    }
    return $o . '</section></div>';
}

// ---------------------------------------------------------------- descargas y cuenta

function panel_descargas(string $slug, array $c): string {
    $p = lee_json(dir_boda($slug) . '/pedido.json') ?? [];
    $d = fn(string $ic, string $t, string $s, string $href, string $bot, bool $fuera = false) => '<div class="descarga"><span class="ic" aria-hidden="true">' . p_ico($ic) . '</span><div><b>' . h($t) . '</b><small>' . h($s) . '</small></div>'
        . '<a class="btn b-sm b-papel" href="' . h($href) . '"' . ($fuera ? ' target="_blank" rel="noopener"' : '') . '>' . h($bot) . '<span class="vh">: ' . h($t) . '</span></a></div>';
    $o = '<div class="rej r-12"><div class="rej">';
    $o .= $d('descargas', 'Excel de respuestas', 'Todo lo que han contestado vuestros invitados, una fila por persona', '/panel/excel', 'Descargar');
    $o .= $d('galeria', 'Vuestra web en ZIP', 'Para guardarla o publicarla donde queráis (sin los datos de los invitados)', '/panel/zip', 'Descargar');
    if (extra_activo($slug, 'mesas')) $o .= $d('hoja', 'Hoja para el restaurante', 'Cada mesa con sus personas, sus menús y sus alergias', '/panel/mesas/imprimir', 'Abrir');
    if ((string) ($p['factura'] ?? '') !== '') $o .= $d('recibo', 'Factura', panel_pack($slug), '/panel/factura', 'Ver', true);
    $recibo = recibo_ls((string) ($p['session_id'] ?? ''));
    if ($recibo !== '') $o .= $d('recibo', 'Recibo de la compra', panel_pack($slug), $recibo, 'Ver', true);
    $o .= '</div><section class="card">' . panel_card_cab('Vuestra cuenta');
    $borra = fecha_borrado((string) $c['fecha']);
    $o .= '<p>' . h(panel_pack($slug)) . ($borra !== '' ? '. La web y las respuestas se guardan hasta el ' . h(fecha_larga($borra, false)) . ': ese día se borran y la web pasa a una página de agradecimiento.' : '.') . '</p>'
        . '<p class="sub">El Excel que descarguéis queda bajo vuestra responsabilidad.</p>';
    if (function_exists('mejora_disponible') && mejora_disponible($slug)) {
        $o .= '<p><a class="btn b-rosa" href="/panel/editar">Pasar al Pack Atelier</a></p><p class="sub">La mejora se hace en Editar la web → Estilo, con los diseños ilustrados.</p>';
    }
    $o .= '<div class="fila-bot"><a class="btn b-papel" href="/panel/recuperar">' . p_ico('llave') . 'Cambiar contraseña</a><a class="btn b-papel" href="/panel/salir">' . p_ico('salir') . 'Salir</a></div>'
        . '<p class="nota">Para cambiar la contraseña os mandamos un enlace al email de la boda o al de la compra.</p></section></div>';
    return $o;
}

function panel_mas(): string {
    $o = '<div class="rej">';
    foreach ([['catering', 'Menús y alergias para el restaurante'], ['musica', 'Canciones y votos'], ['galeria', 'Fotos y mensajes del libro'], ['descargas', 'Excel, ZIP, factura y contraseña']] as [$k, $s]) {
        $o .= '<a class="descarga enlace" href="' . h(panel_href($k)) . '"><span class="ic" aria-hidden="true">' . p_ico($k) . '</span><div><b>' . h(PANEL_SECCIONES[$k][1]) . '</b><small>' . h($s) . '</small></div></a>';
    }
    return $o . '<a class="descarga enlace" href="/panel/editar"><span class="ic" aria-hidden="true">' . p_ico('editar') . '</span><div><b>Editar la web</b><small>Textos, estilo y secciones</small></div></a></div>';
}
