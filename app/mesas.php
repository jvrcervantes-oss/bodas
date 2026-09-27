<?php
// Plano de mesas (F1d). Nació como extra de pago «mesas»; desde el 27-sep-2026 va GRATIS en todos los packs
// (decisión del owner: EXTRAS['mesas']['incluido'] en app/padrino.php). Encargo encargos/20260927_bodas_servicios_extra.md (repo del
// estudio), revisión previa #133.
//
// EL DATO TIENE UN DUEÑO:
//  · Quién viene, su nombre, su menú y sus alergias → guardado/rsvp.json, leído SIEMPRE por rsvp_vigentes() +
//    personas(). Aquí no se copia nada de eso para pintarlo.
//  · Las mesas y quién se sienta dónde → guardado/mesas.json, dueño este fichero:
//      {mesas: [{id, nombre, plazas}], sitios: {<id de persona>: {mesa, nombre}}}
//    El sitio se guarda por el `id` de persona (Seguridad #133), nunca por nombre ni por posición: una alergia
//    impresa en la mesa equivocada es un riesgo físico. `nombre` es una COPIA CONGELADA al sentarla y solo sirve
//    para una cosa: poder decir «ya no viene: X estaba en la mesa N» cuando ese id desaparece de las respuestas
//    vigentes (canceló, o su grupo reenvió y la respuesta quedó sustituida). Nunca se reasigna solo: la pareja
//    lo ve y decide.
//  · Una persona con id vacío (respuesta muy antigua sin id de registro) no se puede sentar: sale «sin mesa».
//
// PUERTAS: solo con la sesión del panel (rutas_panel), no-store, y SOLO si extra_activo($slug, 'mesas'), comprobado
// en el servidor en el GET y en cada POST. Al ir incluido, es cierto para toda web en pie y no archivada (pagada o
// regalada), sin mirar ninguna compra: una compra de prueba anterior o su reembolso no lo cambian.

declare(strict_types=1);

const MESAS_MAX = 80;
const MESAS_MAX_PLAZAS = 30;
const MAX_BYTES_MESAS = 256 * 1024;
const MESAS_PERSONA_RE = '/^(?:[a-f0-9]{16}|v[a-f0-9]{15})$/';   // el aleatorio de rsvp.json o el derivado de personas()

function mesas_fichero(string $slug): string { return dir_boda($slug) . '/guardado/mesas.json'; }

/** mesas.json normalizado (lo que no tenga forma se ignora; nunca se confía en que el fichero esté bien). */
function mesas_lee(string $slug): array {
    $d = lee_json(mesas_fichero($slug)) ?? [];
    $mesas = [];
    foreach ((array) ($d['mesas'] ?? []) as $m) {
        if (!is_array($m) || !preg_match('/^m[a-f0-9]{8}$/', (string) ($m['id'] ?? ''))) continue;
        $mesas[] = ['id' => (string) $m['id'], 'nombre' => (string) ($m['nombre'] ?? ''), 'plazas' => max(1, min(MESAS_MAX_PLAZAS, (int) ($m['plazas'] ?? 0)))];
    }
    $ids = array_column($mesas, 'id');
    $sitios = [];
    foreach ((array) ($d['sitios'] ?? []) as $pid => $s) {
        if (!is_array($s) || !preg_match(MESAS_PERSONA_RE, (string) $pid) || !in_array((string) ($s['mesa'] ?? ''), $ids, true)) continue;
        $sitios[(string) $pid] = ['mesa' => (string) $s['mesa'], 'nombre' => (string) ($s['nombre'] ?? '')];
    }
    return ['mesas' => $mesas, 'sitios' => $sitios];
}

/**
 * Quién va al banquete ahora, por id: [id => persona + grupo (id de la respuesta)]. Sale de rsvp_vigentes(), así
 * que una respuesta sustituida por un reenvío del grupo ya no está (sus ids tampoco).
 */
function mesas_banquete(string $slug): array {
    $o = [];
    foreach (rsvp_vigentes($slug) as $i => $r) {
        if (empty($r['asiste_banquete'])) continue;
        foreach (personas($r) as $p) {
            $p['grupo'] = (string) ($r['id'] ?? ('r' . $i));
            if ($p['id'] === '') { $o['_sin_id_' . count($o)] = $p; continue; }   // no se puede sentar: «sin mesa»
            $o[$p['id']] = $p;
        }
    }
    return $o;
}

/**
 * Estado del plano: cada mesa con las personas que se sientan (resueltas por id contra las vigentes), quién va al
 * banquete sin mesa, y los avisos de quien estaba sentado y ya no viene. Una sola función para la página, la hoja
 * impresa y el catering: lo que se imprime es lo que se ve.
 */
function mesas_estado(string $slug): array {
    $plano = mesas_lee($slug);
    $ban = mesas_banquete($slug);
    $mesas = [];
    foreach ($plano['mesas'] as $n => $m) $mesas[$m['id']] = $m + ['n' => $n + 1, 'personas' => []];
    $sinMesa = array_values(array_filter($ban, fn($p, $k) => !isset($plano['sitios'][$k]), ARRAY_FILTER_USE_BOTH));
    // Si su grupo volvió a responder, la persona llega con un id nuevo y sale «sin mesa»: se dice así (no «ya no
    // viene», que sería falso), pero tampoco se la sienta sola — el nombre no identifica a nadie con seguridad
    $nombresSin = array_flip(array_map(fn($p) => clave_nombre($p['nombre']), $sinMesa));
    $avisos = [];
    foreach ($plano['sitios'] as $pid => $s) {
        if (isset($ban[$pid])) { $mesas[$s['mesa']]['personas'][] = $ban[$pid]; continue; }
        $m = $mesas[$s['mesa']];
        $avisos[] = ['id' => (string) $pid, 'nombre' => $s['nombre'], 'mesa' => mesa_nombre($m), 'respondio' => isset($nombresSin[clave_nombre($s['nombre'])])];
    }
    return ['mesas' => array_values($mesas), 'sin_mesa' => $sinMesa, 'avisos' => $avisos];
}

/** Mesa de cada persona sentada, por id (para la columna Mesa del catering). Vacío si el plano no está disponible (web archivada). */
function mesas_de_personas(string $slug): array {
    if (!extra_activo($slug, 'mesas')) return [];
    $o = [];
    foreach (mesas_estado($slug)['mesas'] as $m) foreach ($m['personas'] as $p) $o[$p['id']] = $m['nombre'] !== '' ? $m['nombre'] : 'Mesa ' . $m['n'];
    return $o;
}

/** Nombre visible de una mesa. */
function mesa_nombre(array $m): string { return $m['nombre'] !== '' ? $m['nombre'] : 'Mesa ' . $m['n']; }

/**
 * Aplica una acción al plano ya leído (función pura sobre el array: se prueba sin servidor). $ban = mesas_banquete().
 * Devuelve '' si se hizo, o el código de error. Todo o nada: si un grupo no cabe, no se sienta a nadie.
 */
function mesas_aplica(array &$d, string $accion, array $in, array $ban): string {
    $d['mesas'] = array_values(array_filter((array) ($d['mesas'] ?? []), 'is_array'));
    $d['sitios'] = (array) ($d['sitios'] ?? []);
    $idx = [];
    foreach ($d['mesas'] as $k => $m) $idx[(string) ($m['id'] ?? '')] = $k;
    $mid = (string) ($in['mesa'] ?? '');
    // Ocupadas = sentadas que siguen viniendo: quien ya no viene sale en el aviso, no quita sitio
    $ocupadas = function (string $mesa) use (&$d, $ban): int {
        $n = 0;
        foreach ($d['sitios'] as $pid => $s) if (($s['mesa'] ?? '') === $mesa && isset($ban[$pid])) $n++;
        return $n;
    };
    $nombre = clean_str($in['nombre'] ?? '', 40);
    $plazas = (int) ($in['plazas'] ?? 0);
    switch ($accion) {
        case 'crear':
            if (count($d['mesas']) >= MESAS_MAX) return 'max-mesas';
            if ($plazas < 1 || $plazas > MESAS_MAX_PLAZAS) return 'plazas';
            $d['mesas'][] = ['id' => 'm' . bin2hex(random_bytes(4)), 'nombre' => $nombre, 'plazas' => $plazas];
            return '';
        case 'editar':
            if (!isset($idx[$mid])) return 'sin-mesa';
            if ($plazas < 1 || $plazas > MESAS_MAX_PLAZAS) return 'plazas';
            if ($plazas < $ocupadas($mid)) return 'plazas-ocupadas';
            $d['mesas'][$idx[$mid]]['nombre'] = $nombre;
            $d['mesas'][$idx[$mid]]['plazas'] = $plazas;
            return '';
        case 'borrar':
            if (!isset($idx[$mid])) return 'sin-mesa';
            array_splice($d['mesas'], $idx[$mid], 1);
            // Quien estaba sentado vuelve a «sin mesa» (y quien ya no venía deja de avisar: su mesa ya no existe)
            foreach ($d['sitios'] as $pid => $s) if (($s['mesa'] ?? '') === $mid) unset($d['sitios'][$pid]);
            return '';
        case 'sentar':
            if (!isset($idx[$mid])) return 'sin-mesa';
            $ids = array_values(array_unique(array_map('strval', array_filter((array) ($in['personas'] ?? []), 'is_string'))));
            if (!$ids) return 'sin-personas';
            if (count($ids) > MESAS_MAX_PLAZAS) return 'no-caben';
            foreach ($ids as $pid) if (!preg_match(MESAS_PERSONA_RE, $pid) || !isset($ban[$pid])) return 'persona';
            $nuevas = count(array_filter($ids, fn($pid) => ($d['sitios'][$pid]['mesa'] ?? '') !== $mid));
            if ($ocupadas($mid) + $nuevas > (int) $d['mesas'][$idx[$mid]]['plazas']) return 'no-caben';
            foreach ($ids as $pid) $d['sitios'][$pid] = ['mesa' => $mid, 'nombre' => (string) $ban[$pid]['nombre']];
            return '';
        case 'levantar':   // quitar a alguien de su mesa, o dar por visto el aviso de quien ya no viene
            $pid = (string) ($in['persona'] ?? '');
            if (!isset($d['sitios'][$pid])) return 'persona';
            unset($d['sitios'][$pid]);
            return '';
    }
    return 'accion';
}

const MESAS_ERRORES = ['max-mesas' => 'Como máximo ' . MESAS_MAX . ' mesas.', 'plazas' => 'Entre 1 y ' . MESAS_MAX_PLAZAS . ' plazas por mesa.',
    'plazas-ocupadas' => 'Hay más personas sentadas que esas plazas. Levantad a alguien primero.', 'sin-mesa' => 'Esa mesa ya no existe.',
    'sin-personas' => 'Elegid primero a quién sentar.', 'persona' => 'Alguien de la selección ya no va al banquete. Recargad la página.',
    'no-caben' => 'No caben todos en esa mesa.', 'accion' => 'No se ha podido hacer.', 'lleno' => 'El plano es demasiado grande.'];

/** /panel/mesas: GET = la página; POST = una acción (CSRF + plano disponible, o 403). */
function panel_mesas(string $slug, array $c, string $metodo): void {
    header('Cache-Control: private, no-store');   // lleva nombres, menús y alergias (art. 9 RGPD)
    if ($metodo === 'POST') {
        if (!panel_csrf_ok()) { http_response_code(403); exit('La sesión ha caducado. Recarga la página.'); }
        // Cada POST lo comprueba en el servidor: la página se pudo abrir antes de archivar la web (Seguridad #133)
        if (!extra_activo($slug, 'mesas')) { http_response_code(403); exit('El plano de mesas no está activo en esta web.'); }
        if (!limite('mesas|' . $slug, 600, 3600)) { header('Location: /panel/mesas?e=accion', true, 303); exit; }
        $ban = mesas_banquete($slug);
        $in = ['mesa' => $_POST['mesa'] ?? '', 'nombre' => $_POST['nombre'] ?? '', 'plazas' => $_POST['plazas'] ?? 0,
            'personas' => $_POST['personas'] ?? [], 'persona' => $_POST['persona'] ?? ''];
        $accion = (string) ($_POST['accion'] ?? '');
        $r = muta_json(mesas_fichero($slug), fn(array &$d) => mesas_aplica($d, $accion, $in, $ban), MAX_BYTES_MESAS);
        $e = $r === null ? 'lleno' : (string) $r;
        header('Location: /panel/mesas' . ($e !== '' ? '?e=' . rawurlencode($e) : ''), true, 303);
        exit;
    }
    if ($metodo !== 'GET' && $metodo !== 'HEAD') { http_response_code(405); exit; }
    if (!extra_activo($slug, 'mesas')) {
        echo panel_pagina($slug, $c, 'mesas', 'Plano de mesas', '<section class="card">' . panel_card_cab('Plano de mesas', 'El plano de mesas no está disponible en esta web.') . '</section>');
        return;
    }
    echo panel_pagina($slug, $c, 'mesas', 'Plano de mesas', mesas_herramienta($slug));
}

/**
 * Sillas alrededor de una mesa redonda: ocupadas (dorado), con alergia (rosa), libres (blanco). Solo dibujo
 * (aria-hidden): la lista de quién se sienta va debajo, en texto. Con muchas plazas las sillas encogen y pierden
 * las iniciales para no montarse unas sobre otras.
 */
function mesa_sillas(array $m, int $radio = 70, int $centro = 60): string {
    $n = max(1, (int) $m['plazas']);
    $tam = (int) max(10, min(24, floor(2 * M_PI * $radio / $n) - 4));
    $o = '';
    for ($i = 0; $i < $n; $i++) {
        $a = $i / $n * M_PI * 2 - M_PI / 2;
        $p = $m['personas'][$i] ?? null;
        $cls = $p ? ($p['alergias'] !== '' ? ' a' : ' o') : '';
        $o .= '<span class="silla' . $cls . '" style="left:' . round($centro + cos($a) * $radio, 1) . 'px;top:' . round($centro + sin($a) * $radio, 1) . 'px;width:' . $tam . 'px;height:' . $tam . 'px;margin:-' . ($tam / 2) . 'px 0 0 -' . ($tam / 2) . 'px">'
            . ($p && $tam >= 20 ? h(panel_iniciales($p['nombre'])) : '') . '</span>';
    }
    return $o;
}

function mesas_herramienta(string $slug): string {
    $E = mesas_estado($slug);
    $csrf = h(panel_csrf());
    $o = '';
    $e = (string) ($_GET['e'] ?? '');
    if ($e !== '') $o .= '<p class="aviso" role="alert">' . h(MESAS_ERRORES[$e] ?? 'No se ha podido hacer.') . '</p>';
    $sentados = array_sum(array_map(fn($m) => count($m['personas']), $E['mesas']));
    $plazas = array_sum(array_column($E['mesas'], 'plazas'));
    $sinId = array_filter($E['sin_mesa'], fn($p) => $p['id'] === '');
    $rep = mesas_repetidos($E);

    // Formulario único para sentar: el JS lo rellena con la selección y la mesa tocada
    $o .= '<form method="post" action="/panel/mesas" id="form-sentar" hidden><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="accion" value="sentar"><input type="hidden" name="mesa" value=""></form>';

    // Salón: una mesa redonda por mesa, con sus sillas. Tocar la mesa = sentar ahí a quien esté elegido
    $salon = '';
    foreach ($E['mesas'] as $m) {
        $libres = $m['plazas'] - count($m['personas']);
        $conAlergia = count(array_filter($m['personas'], fn($p) => $p['alergias'] !== ''));
        $salon .= '<div class="mesa" data-mesa="' . h($m['id']) . '">'
            . '<button type="button" class="m-circ mesa-destino" data-sentar="' . h($m['id']) . '" disabled aria-label="Sentar aquí: ' . h(mesa_nombre($m)) . ($libres > 0 ? '' : ' (llena)') . '">'
            . '<b>' . h(mesa_nombre($m)) . '</b><span class="m-sentar" aria-hidden="true">Sentar aquí' . ($libres > 0 ? '' : ' (llena)') . '</span>' . mesa_sillas($m) . '</button>'
            . '<small class="tab">' . count($m['personas']) . ' / ' . (int) $m['plazas'] . ($conAlergia ? ' · ' . $conAlergia . ' con alergia' : '') . '</small>'
            . '<ul class="mesa-personas">';
        foreach ($m['personas'] as $p) {
            $salon .= '<li>' . mesas_boton_persona($p, $rep) . '<form method="post" action="/panel/mesas" class="mesa-quitar"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="accion" value="levantar">'
                . '<input type="hidden" name="persona" value="' . h($p['id']) . '"><button class="mesa-x" aria-label="Quitar a ' . h($p['nombre']) . ' de la mesa">×</button></form></li>';
        }
        $salon .= '</ul><details class="mesa-editar"><summary>Cambiar</summary>'
            . '<form method="post" action="/panel/mesas" class="form"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="accion" value="editar"><input type="hidden" name="mesa" value="' . h($m['id']) . '">'
            . '<div class="campo"><label for="n-' . h($m['id']) . '">Nombre</label><input type="text" id="n-' . h($m['id']) . '" name="nombre" maxlength="40" value="' . h($m['nombre']) . '" placeholder="Mesa ' . (int) $m['n'] . '"></div>'
            . '<div class="campo"><label for="p-' . h($m['id']) . '">Plazas</label><input type="number" id="p-' . h($m['id']) . '" name="plazas" min="1" max="' . MESAS_MAX_PLAZAS . '" value="' . (int) $m['plazas'] . '" required></div>'
            . '<button class="btn b-sm b-papel">Guardar</button></form>'
            . '<form method="post" action="/panel/mesas"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="accion" value="borrar"><input type="hidden" name="mesa" value="' . h($m['id']) . '">'
            . '<button class="btn b-sm b-papel" data-confirmar="¿Borrar esta mesa? Quien estaba sentado vuelve a «Sin mesa».">Borrar la mesa</button></form></details></div>';
    }
    $nueva = '<form method="post" action="/panel/mesas" class="mesa-nueva"><input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="accion" value="crear">'
        . '<div class="campo"><label for="mesa-nombre">Mesa nueva</label><input type="text" id="mesa-nombre" name="nombre" maxlength="40" placeholder="Mesa ' . (count($E['mesas']) + 1) . '"></div>'
        . '<div class="campo campo-corto"><label for="mesa-plazas">Plazas</label><input type="number" id="mesa-plazas" name="plazas" min="1" max="' . MESAS_MAX_PLAZAS . '" value="10" required></div>'
        . '<button class="btn b-papel">Crear mesa</button></form>';
    $o .= '<div class="rej r-12 mesas"><section class="card" id="plano">'
        . panel_card_cab('Plano de mesas', 'Tocad a una persona (o «Todo el grupo») y luego la mesa donde se sienta. Tocad a alguien ya sentado para cambiarlo de mesa.', '<span class="chip incl">Incluido en vuestro pack</span>')
        . '<div class="cifras tab"><span><b>' . $sentados . '</b> sentados</span><span><b>' . count($E['sin_mesa']) . '</b> sin mesa</span><span><b>' . count($E['mesas']) . '</b> mesas</span><span><b>' . $plazas . '</b> plazas</span></div>'
        . ($salon !== '' ? '<div class="salon">' . $salon . '</div>' : '<p class="vacio">Todavía no hay mesas. Cread la primera aquí abajo.</p>')
        . $nueva . '<div class="fila-bot"><a class="btn b-osc" href="/panel/mesas/imprimir">' . p_ico('hoja') . 'Hoja para el restaurante</a></div></section>';

    // Sin mesa, agrupados por respuesta (un grupo = los que confirmaron juntos), y los avisos
    $o .= '<section class="card sin-mesa">' . panel_card_cab('Sin mesa', h(count($E['sin_mesa']) ? count($E['sin_mesa']) . (count($E['sin_mesa']) === 1 ? ' persona que va' : ' personas que van') . ' al banquete.' : 'Todos los que van al banquete tienen mesa.'));
    // Quien estaba sentado y ya no viene: se dice, y la pareja lo quita (nunca se reasigna solo)
    foreach ($E['avisos'] as $a) {
        $o .= '<form method="post" action="/panel/mesas" class="aviso mesa-aviso"><span>' . ($a['respondio']
                ? 'Ha vuelto a responder: <b>' . h($a['nombre']) . '</b> estaba en <b>' . h($a['mesa']) . '</b> y ahora sale en «Sin mesa». Volved a sentarle.'
                : 'Ya no viene: <b>' . h($a['nombre']) . '</b> estaba en <b>' . h($a['mesa']) . '</b>.') . '</span>'
            . '<input type="hidden" name="csrf" value="' . $csrf . '"><input type="hidden" name="accion" value="levantar"><input type="hidden" name="persona" value="' . h($a['id']) . '">'
            . '<button class="btn b-sm b-papel">Quitar del plano</button></form>';
    }
    $grupos = [];
    foreach ($E['sin_mesa'] as $p) $grupos[$p['grupo']][] = $p;
    $o .= '<div class="mesa-sin">';
    foreach ($grupos as $gid => $ps) {
        $conId = array_filter($ps, fn($p) => $p['id'] !== '');
        $o .= '<div class="mesa-grupo">' . (count($conId) > 1 ? '<button type="button" class="todo-grupo" data-grupo-sel="' . h($gid) . '">Todo el grupo (' . count($conId) . ')</button>' : '');
        foreach ($ps as $p) $o .= mesas_boton_persona($p, $rep);
        $o .= '</div>';
    }
    $o .= '</div>' . ($sinId ? '<p class="nota">Las personas en gris respondieron antes de que existiera el plano y no se pueden sentar: pedidles que vuelvan a confirmar.</p>' : '')
        . '<p class="nota leyenda"><span class="silla o" aria-hidden="true"></span> sentado <span class="silla a" aria-hidden="true"></span> con alergia <span class="silla" aria-hidden="true"></span> libre</p></section></div>';
    return $o;
}

/** Nombres que salen más de una vez entre sentados y sin mesa: un grupo que respondió por la
 *  confirmación general y luego por su enlace deja dos respuestas vigentes (EST: fila BOD-22). */
function mesas_repetidos(array $E): array {
    return array_keys(array_filter(array_count_values(array_map(fn($p) => clave_nombre($p['nombre']),
        array_merge($E['sin_mesa'], ...array_column($E['mesas'], 'personas')))), fn($n) => $n > 1));
}

function mesas_boton_persona(array $p, array $rep): string {
    if ($p['id'] === '') return '<span class="mesa-persona is-sin-id">' . h($p['nombre']) . '</span>';
    return '<button type="button" class="mesa-persona" aria-pressed="false" data-persona="' . h($p['id']) . '" data-grupo="' . h($p['grupo']) . '">' . h($p['nombre'])
        . ($p['tipo'] === 'nino' ? ' <span class="muted">(niño/a)</span>' : '') . ($p['alergias'] !== '' ? ' <span class="alergia">· alergia</span>' : '')
        . (in_array(clave_nombre($p['nombre']), $rep, true) ? ' <span class="rep">repetido</span>' : '') . '</button>';
}

/** /panel/mesas/imprimir: la hoja para el restaurante. Cada mesa con sus personas, su menú y sus alergias. */
function panel_mesas_imprimir(string $slug, array $c): void {
    header('Cache-Control: private, no-store');
    if (!extra_activo($slug, 'mesas')) { header('Location: /panel/mesas'); exit; }
    $E = mesas_estado($slug);
    $lugar = (string) ($c['convite']['lugar'] ?? '');
    $cuando = trim(($c['fecha'] !== '' ? fecha_larga($c['fecha'], false) : '') . ($lugar !== '' ? ' · ' . $lugar : ''), ' ·');
    $o = '<header class="hoja-top"><div><p class="over">Plano de mesas</p><h1>' . h(nombres($c)) . '</h1>'
        . ($cuando !== '' ? '<p>' . h($cuando) . '</p>' : '')
        // La hoja impresa envejece: se dice a qué hora se sacó (como el resumen para el catering)
        . '<p class="sub">Datos del ' . h(date('d/m/Y')) . ' a las ' . h(date('H:i')) . '. Si llegan más confirmaciones o cambiáis el plano, volved a imprimirlo.</p></div>'
        . '<nav class="fila-bot no-print"><button type="button" class="btn b-osc" data-imprimir>Imprimir / guardar PDF</button><a class="btn b-papel" href="/panel/mesas">Volver al plano</a></nav></header>';
    $rep = mesas_repetidos($E);
    if ($rep) {
        $o .= '<p class="panel-aviso aviso-repetido">Atención: hay personas que aparecen dos veces porque respondieron dos veces'
            . ' (marcadas «repetido»). Comprobad con ellas su menú y sus alergias antes de dar esta hoja al restaurante.</p>';
    }
    if ($E['avisos']) {
        $o .= '<p class="panel-aviso no-print">Hay ' . count($E['avisos']) . ' persona(s) en el plano que ya no vienen. No salen en la hoja; quitadlas del plano cuando lo veáis.</p>';
    }
    $o .= '<section class="catering">';
    foreach ($E['mesas'] as $m) {
        $menus = [];
        foreach ($m['personas'] as $p) { $k = nombre_menu($c, $p['menu'], $p['menu_nombre']); $menus[$k] = ($menus[$k] ?? 0) + 1; }
        $o .= '<div class="mesa-hoja"><h2 class="panel-h2">' . h(mesa_nombre($m)) . ' <span class="muted">· ' . count($m['personas']) . ' personas</span></h2>'
            . ($menus ? '<p class="sub mesa-menus">' . h(implode(' · ', array_map(fn($k, $n) => "$n $k", array_keys($menus), $menus))) . '</p>' : '')
            . '<div class="tabla-w"><table class="t t-corta"><thead><tr><th>Nombre</th><th>Menú</th><th>Alergias</th></tr></thead><tbody>';
        if (!$m['personas']) $o .= '<tr><td colspan="3" class="vacio">Mesa vacía.</td></tr>';
        foreach ($m['personas'] as $p) {
            $o .= '<tr><td>' . h($p['nombre']) . ($p['tipo'] === 'nino' ? ' <span class="muted">(niño/a)</span>' : '')
                . (in_array(clave_nombre($p['nombre']), $rep, true) ? ' <strong class="rep">repetido</strong>' : '') . '</td><td>' . h(nombre_menu($c, $p['menu'], $p['menu_nombre'])) . '</td>'
                . '<td class="alergia">' . h($p['alergias']) . '</td></tr>';
        }
        $o .= '</tbody></table></div></div>';
    }
    if ($E['sin_mesa']) {
        $o .= '<div class="mesa-hoja"><h2 class="panel-h2">Sin mesa <span class="muted">· ' . count($E['sin_mesa']) . ' personas</span></h2>'
            . '<p class="sub">Sus alergias están en el resumen para el catering.</p><div class="tabla-w"><table class="t t-corta"><thead><tr><th>Nombre</th><th>Menú</th></tr></thead><tbody>';
        foreach ($E['sin_mesa'] as $p) {
            $o .= '<tr><td>' . h($p['nombre']) . (in_array(clave_nombre($p['nombre']), $rep, true) ? ' <strong class="rep">repetido</strong>' : '')
                . '</td><td>' . h(nombre_menu($c, $p['menu'], $p['menu_nombre'])) . '</td></tr>';
        }
        $o .= '</tbody></table></div></div>';
    }
    $o .= '<p class="nota">Solo aparecen quienes van al banquete. Si un grupo corrigió su respuesta, vale la última.</p></section>';
    echo panel_hoja_marco($c, 'Plano de mesas', $o);
}
