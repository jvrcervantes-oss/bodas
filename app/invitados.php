<?php
// Lista de invitados en el panel de la pareja: pegan los nombres y ven quién falta por
// contestar. No cambia el formulario público. Revisión previa #101 (Seguridad + Legal), 25-sep-2026:
//  - Solo nombre y un grupo (≤40): ni contacto ni salud (Legal; la pareja se compromete en el
//    encargo de tratamiento, Anexo II.3). Vive en guardado/ y se borra con el archivado.
//  - El marcado a mano va APARTE de la lista (por id estable de nombre+grupo): volver a pegar
//    la lista no lo pierde.
//  - Todo se pinta con h(); CSRF y tope de tamaño antes de procesar.
declare(strict_types=1);

const INVITADOS_MAX = 1000;
const INVITADOS_MAX_BYTES = 150000;

// Enlace personal por grupo (F1a, revisión previa #133, Seguridad + Legal), 27-sep-2026:
//  - Cada grupo de la lista tiene en `grupos` un `gid` (id estable, lo que guarda la respuesta) y un
//    `token` de random_bytes(16) en base64url (128 bits) para /i/<token>. NO se deriva de inv_id():
//    ese sale de nombre+grupo y cualquiera que conozca los nombres lo recalcularía.
//  - El token se guarda en claro en este fichero porque el panel tiene que enseñar el enlace; vive
//    fuera del webroot, con el resto de guardado/, y se borra con el archivado.
//  - Quien no tiene grupo en la lista es un grupo de una sola persona (el saludo lleva su nombre).
//  - Renombrar un grupo al pegar la lista = grupo nuevo con enlace nuevo; el viejo deja de funcionar.
//  - `abierto` = fecha del primer acceso por el enlace, sin IP (Legal). «Confirmado» NO se guarda
//    aquí: se lee de rsvp.json (respuesta vigente con ese gid), que es donde vive la respuesta.
const INV_TOKEN_RE = '/^[A-Za-z0-9_-]{22}$/';

function inv_fichero(string $slug): string { return dir_boda($slug) . '/guardado/invitados.json'; }
function inv_lee(string $slug): array {
    $d = lee_json(inv_fichero($slug)) ?? [];
    return ['lista' => is_array($d['lista'] ?? null) ? $d['lista'] : [], 'manual' => is_array($d['manual'] ?? null) ? $d['manual'] : [],
        'grupos' => is_array($d['grupos'] ?? null) ? $d['grupos'] : []];
}
function inv_id(string $nombre, string $grupo): string { return substr(sha1(clave_nombre($nombre) . '|' . clave_nombre($grupo)), 0, 12); }

function inv_token_nuevo(): string { return rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '='); }

/** Clave del grupo de una fila de la lista: el grupo escrito, o la propia persona si no tiene. */
function inv_clave_grupo(array $g): string { return (string) ($g['grupo'] ?? '') !== '' ? 'g:' . clave_nombre((string) $g['grupo']) : 'p:' . (string) ($g['id'] ?? ''); }

/** Grupos de la lista en su orden: clave => [nombre para el saludo, nombres de sus personas]. */
function inv_grupos_de(array $lista): array {
    $o = [];
    foreach ($lista as $g) {
        if (!is_array($g) || (string) ($g['nombre'] ?? '') === '') continue;
        $k = inv_clave_grupo($g);
        $o[$k] = $o[$k] ?? ['nombre' => (string) ($g['grupo'] ?? '') !== '' ? (string) $g['grupo'] : (string) $g['nombre'], 'personas' => []];
        $o[$k]['personas'][] = (string) $g['nombre'];
    }
    return $o;
}

/**
 * Deja `grupos` al día con la lista (dentro de un muta_json): grupo nuevo = gid + token nuevos;
 * los que siguen conservan su enlace; los que ya no están se van y su enlace deja de funcionar.
 */
function inv_sincroniza_grupos(array &$d): void {
    $viejos = is_array($d['grupos'] ?? null) ? $d['grupos'] : [];
    $nuevos = [];
    foreach (array_keys(inv_grupos_de(is_array($d['lista'] ?? null) ? $d['lista'] : [])) as $k) {
        $v = $viejos[$k] ?? null;
        $nuevos[$k] = is_array($v) && preg_match('/^[a-f0-9]{12}$/', (string) ($v['gid'] ?? '')) && preg_match(INV_TOKEN_RE, (string) ($v['token'] ?? ''))
            ? $v : ['gid' => bin2hex(random_bytes(6)), 'token' => inv_token_nuevo(), 'abierto' => ''];
    }
    $d['grupos'] = $nuevos;
}

/** Listas guardadas antes del 27-sep no tienen enlaces: se crean la primera vez que la pareja abre el panel. */
function inv_asegura_grupos(string $slug): void {
    $inv = inv_lee($slug);
    if (!$inv['lista'] || !array_diff_key(inv_grupos_de($inv['lista']), $inv['grupos'])) return;
    muta_json(inv_fichero($slug), function (array &$d) { inv_sincroniza_grupos($d); });
}

/**
 * El grupo de un token, o null. Única puerta de /i/<token> y del POST de confirmación: el grupo
 * se decide aquí y nunca por un campo del formulario. Comparación en tiempo constante.
 */
function inv_grupo_por_token(string $slug, string $tok): ?array {
    if (!preg_match(INV_TOKEN_RE, $tok)) return null;
    $inv = inv_lee($slug);
    $grupos = inv_grupos_de($inv['lista']);
    foreach ($inv['grupos'] as $k => $e) {
        if (!is_array($e) || !isset($grupos[$k]) || !hash_equals((string) ($e['token'] ?? ''), $tok)) continue;
        return ['gid' => (string) $e['gid'], 'nombre' => $grupos[$k]['nombre'], 'personas' => array_slice($grupos[$k]['personas'], 0, MAX_INVITADOS),
            'abierto' => (string) ($e['abierto'] ?? '')];
    }
    return null;
}

/** Primer acceso por el enlace: solo la fecha, una vez. Quien llama ya comprobó que estaba vacía (no se reescribe el fichero en cada visita). */
function inv_marca_abierto(string $slug, string $gid): void {
    muta_json(inv_fichero($slug), function (array &$d) use ($gid) {
        foreach ($d['grupos'] ?? [] as $k => $e) {
            if (is_array($e) && ($e['gid'] ?? '') === $gid && (string) ($e['abierto'] ?? '') === '') $d['grupos'][$k]['abierto'] = date('c');
        }
    });
}

/** Una persona por línea; «nombre; grupo» o dos columnas pegadas desde Excel (tabulador). */
function inv_parsea(string $texto): array {
    $o = [];
    foreach (preg_split('/\r\n|\r|\n/', $texto) ?: [] as $l) {
        $partes = preg_split('/\t|;/', $l, 2) ?: [];
        $nombre = clean_str($partes[0] ?? '', 120);
        $grupo = clean_str($partes[1] ?? '', 40);
        if ($nombre === '') continue;
        $id = inv_id($nombre, $grupo);
        if (!isset($o[$id])) $o[$id] = ['id' => $id, 'nombre' => $nombre, 'grupo' => $grupo];
        if (count($o) >= INVITADOS_MAX) break;
    }
    return array_values($o);
}

/** Palabras de un nombre sin tildes ni mayúsculas. */
function inv_palabras(string $n): array { return array_values(array_filter(explode(' ', clave_nombre($n)))); }

/**
 * Cruza la lista con las respuestas: coincide si el nombre es igual o si uno contiene todas las
 * palabras del otro y el más corto tiene al menos dos («Ana García» ~ «Ana García López»).
 * Devuelve [filas con estado, resumen].
 */
function inv_cruza(string $slug, array $c): array {
    $inv = inv_lee($slug);
    $resp = [];
    foreach (rsvp_vigentes($slug) as $r) {   // sin las sustituidas: un grupo que corrigió cuenta una vez
        $viene = !empty($r['asiste_ceremonia']) || !empty($r['asiste_banquete']);
        foreach (personas($r) as $p) $resp[] = [inv_palabras((string) $p['nombre']), $viene];
    }
    $filas = [];
    $res = ['viene' => 0, 'no' => 0, 'pend' => 0];
    foreach ($inv['lista'] as $g) {
        $pg = inv_palabras($g['nombre']);
        $auto = 'pend';
        foreach ($resp as [$pr, $viene]) {
            $corto = count($pg) <= count($pr) ? $pg : $pr;
            $largo = $corto === $pg ? $pr : $pg;
            if ($pg === $pr || (count($corto) >= 2 && !array_diff($corto, $largo))) { $auto = $viene ? 'viene' : 'no'; if ($viene) break; }
        }
        $manual = (string) ($inv['manual'][$g['id']] ?? '');
        $estado = in_array($manual, ['viene', 'no', 'pend'], true) ? $manual : $auto;
        $res[$estado]++;
        $filas[] = $g + ['estado' => $estado, 'manual' => $manual !== ''];
    }
    $orden = ['pend' => 0, 'no' => 1, 'viene' => 2];
    usort($filas, fn($a, $b) => [$orden[$a['estado']], $a['grupo'], $a['nombre']] <=> [$orden[$b['estado']], $b['grupo'], $b['nombre']]);
    return [$filas, $res];
}

function panel_invitados_accion(string $slug, string $metodo): void {
    if ($metodo !== 'POST' || !panel_csrf_ok()) { http_response_code(403); exit; }
    $acc = (string) ($_POST['accion'] ?? '');
    if ($acc === 'lista') {
        $texto = (string) ($_POST['lista'] ?? '');
        if (strlen($texto) > INVITADOS_MAX_BYTES) $texto = substr($texto, 0, INVITADOS_MAX_BYTES);
        $lista = inv_parsea($texto);
        muta_json(inv_fichero($slug), function (array &$d) use ($lista) {
            $d['lista'] = $lista;
            // Las marcas a mano de quien sigue en la lista se conservan; las de quien ya no está, fuera
            $ids = array_column($lista, 'id');
            $d['manual'] = array_intersect_key(is_array($d['manual'] ?? null) ? $d['manual'] : [], array_flip($ids));
            inv_sincroniza_grupos($d);
        });
    } elseif ($acc === 'rotar') {
        // Enlace nuevo para un grupo (se reenvió a quien no debía, o lo pidió el grupo): el anterior
        // deja de funcionar al momento. La respuesta que ya dieron sigue (va por gid, no por token).
        $gid = (string) ($_POST['gid'] ?? '');
        if (preg_match('/^[a-f0-9]{12}$/', $gid)) {
            muta_json(inv_fichero($slug), function (array &$d) use ($gid) {
                foreach ($d['grupos'] ?? [] as $k => $e) {
                    if (is_array($e) && ($e['gid'] ?? '') === $gid) { $d['grupos'][$k]['token'] = inv_token_nuevo(); $d['grupos'][$k]['abierto'] = ''; }
                }
            });
        }
        header('Location: /panel#enlaces', true, 303);
        exit;
    } elseif ($acc === 'marcar') {
        $id = (string) ($_POST['id'] ?? '');
        $estado = (string) ($_POST['estado'] ?? '');
        if (preg_match('/^[a-f0-9]{12}$/', $id)) {
            muta_json(inv_fichero($slug), function (array &$d) use ($id, $estado) {
                if (!in_array($id, array_column($d['lista'] ?? [], 'id'), true)) return;
                if (in_array($estado, ['viene', 'no', 'pend'], true)) $d['manual'][$id] = $estado; else unset($d['manual'][$id]);
            });
        }
    }
    header('Location: /panel#invitados', true, 303);
    exit;
}

function bloque_invitados(string $slug, array $c): string {
    $inv = inv_lee($slug);
    $csrf = '<input type="hidden" name="csrf" value="' . h(panel_csrf()) . '">';
    $nota = '<p class="panel-nota">Solo nombres y, si queréis, un grupo (por ejemplo «Familia de Lucía»). Ni teléfonos ni alergias: eso ya lo dejan ellos al confirmar. Esta lista solo la veis vosotros y se borra junto con las respuestas.</p>';
    $texto = implode("\n", array_map(fn($g) => $g['nombre'] . ($g['grupo'] !== '' ? '; ' . $g['grupo'] : ''), $inv['lista']));
    $form = '<form method="post" action="/panel/invitados" class="inv-form">' . $csrf . '<input type="hidden" name="accion" value="lista">'
        . '<label for="invLista" class="inv-label">Una persona por línea. Para el grupo, un punto y coma: <i>Ana García; Familia de Lucía</i>. También podéis pegar dos columnas de Excel.</label>'
        . '<textarea id="invLista" name="lista" rows="8" maxlength="' . INVITADOS_MAX_BYTES . '" spellcheck="false">' . h($texto) . '</textarea>'
        . '<div class="form-actions"><button class="btn" type="submit">Guardar lista</button></div></form>';
    $o = '<section class="section" id="invitados"><h2 class="panel-h2">Lista de invitados</h2>';
    if (!$inv['lista']) return $o . '<p>Pegad aquí a quién habéis invitado y os diremos quién falta por contestar.</p>' . $nota . $form . '</section>';

    [$filas, $res] = inv_cruza($slug, $c);
    $pend = array_values(array_filter($filas, fn($f) => $f['estado'] === 'pend'));
    $o .= '<div class="stat-row"><div class="stat"><b>' . count($filas) . '</b><span>Invitados</span></div><div class="stat"><b>' . $res['viene'] . '</b><span>Vienen</span></div>'
        . '<div class="stat"><b>' . $res['no'] . '</b><span>No vienen</span></div><div class="stat stat-pend"><b>' . $res['pend'] . '</b><span>Sin contestar</span></div></div>';
    if ($pend) {
        $o .= '<p><button type="button" class="btn btn-soft" data-copiar-pendientes="' . h(implode("\n", array_column($pend, 'nombre'))) . '">Copiar los que faltan</button> <span class="panel-nota inv-copiado" hidden>Copiado</span></p>';
    }
    $etq = ['pend' => 'Sin contestar', 'no' => 'No viene', 'viene' => 'Viene'];
    $o .= '<div class="table-wrap"><table class="inv-tabla"><thead><tr><th>Nombre</th><th>Grupo</th><th>Estado</th><th>Corregir</th></tr></thead><tbody>';
    foreach ($filas as $f) {
        $botones = '';
        foreach (['viene' => 'Viene', 'no' => 'No viene', 'pend' => 'Sin contestar'] as $k => $t) {
            if ($k !== $f['estado']) $botones .= '<button class="inv-btn" name="estado" value="' . $k . '">' . $t . '</button>';
        }
        if ($f['manual']) $botones .= '<button class="inv-btn" name="estado" value="auto" title="Volver a lo que digan las confirmaciones">Automático</button>';
        $o .= '<tr class="inv-' . $f['estado'] . '"><td>' . h($f['nombre']) . '</td><td>' . h($f['grupo']) . '</td>'
            . '<td><span class="inv-estado">' . $etq[$f['estado']] . '</span>' . ($f['manual'] ? ' <span class="muted">(a mano)</span>' : '') . '</td>'
            . '<td><form method="post" action="/panel/invitados" class="inv-marcar">' . $csrf . '<input type="hidden" name="accion" value="marcar"><input type="hidden" name="id" value="' . h($f['id']) . '">' . $botones . '</form></td></tr>';
    }
    $o .= '</tbody></table></div><p class="panel-nota">Se cruza por nombre con las confirmaciones. Si alguien contestó con otro nombre o por teléfono, corregidlo a mano.</p>';
    return $o . '<details class="inv-editar"><summary>Editar la lista</summary>' . $nota . $form . '</details></section>' . bloque_enlaces($slug, $c, $csrf);
}

/**
 * Un enlace por grupo: copiar, WhatsApp con el mensaje escrito, estado y rotar. Estado:
 * «Confirmado» si hay una respuesta vigente llegada por su enlace (rsvp.json, por gid);
 * «Abierto» si alguien lo abrió (fecha del primer acceso); si no, «Sin abrir».
 */
function bloque_enlaces(string $slug, array $c, string $csrf): string {
    if (!seccion_tipo($c, 'rsvp')) return '';
    inv_asegura_grupos($slug);
    $inv = inv_lee($slug);
    $grupos = inv_grupos_de($inv['lista']);
    if (!$grupos) return '';
    $conf = [];
    foreach (rsvp_vigentes($slug) as $r) {
        if (($r['grupo'] ?? '') !== '') $conf[(string) $r['grupo']] = !empty($r['asiste_ceremonia']) || !empty($r['asiste_banquete']);
    }
    $o = '<section class="section" id="enlaces"><h2 class="panel-h2">Enlace personal por grupo</h2>'
        . '<p class="panel-nota">Cada grupo abre la web con su saludo y sus nombres ya escritos. Mandad a cada uno el suyo: quien tenga el enlace puede confirmar por ese grupo, y si confirman dos veces vale la última.</p>'
        . '<div class="table-wrap"><table class="inv-tabla enl-tabla"><thead><tr><th>Grupo</th><th>Estado</th><th>Enlace</th></tr></thead><tbody>';
    foreach ($grupos as $k => $g) {
        $e = $inv['grupos'][$k] ?? null;
        if (!is_array($e)) continue;
        $url = url_boda($slug, 'i/' . $e['token']);
        if (isset($conf[$e['gid']])) $est = ['viene', 'Confirmado' . ($conf[$e['gid']] ? '' : ' · no vienen')];
        elseif ((string) ($e['abierto'] ?? '') !== '') $est = ['no', 'Abierto el ' . date('d/m/Y', (int) strtotime((string) $e['abierto']))];
        else $est = ['pend', 'Sin abrir'];
        $msg = '¡Hola, ' . $g['nombre'] . '! Nos casamos y nos encantaría que vinierais. En este enlace tenéis toda la información y podéis confirmar: ' . $url;
        $o .= '<tr class="inv-' . $est[0] . '"><td><b>' . h($g['nombre']) . '</b><br><span class="muted">' . h(implode(', ', $g['personas'])) . '</span>'
            // El enlace entero solo en escritorio: en el móvil ensancha la tabla y ya está en «Copiar»
            . '<br><code class="enl-url">' . h($url) . '</code></td>'
            . '<td><span class="inv-estado">' . h($est[1]) . '</span></td>'
            . '<td><div class="inv-marcar"><button type="button" class="inv-btn" data-copiar-enlace="' . h($url) . '">Copiar</button><span class="panel-nota copiado" hidden>Copiado</span>'
            . '<a class="inv-btn" href="https://wa.me/?text=' . h(rawurlencode($msg)) . '" target="_blank" rel="noopener">WhatsApp</a>'
            . '<form method="post" action="/panel/invitados" class="inv-marcar">' . $csrf . '<input type="hidden" name="accion" value="rotar"><input type="hidden" name="gid" value="' . h($e['gid']) . '">'
            . '<button class="inv-btn" title="Crea un enlace nuevo; el anterior deja de funcionar">Cambiar enlace</button></form></div></td></tr>';
    }
    return $o . '</tbody></table></div><p class="panel-nota">«Abierto» es orientativo: se apunta la primera vez que alguien abre el enlace (sin guardar nada más), sin contar las vistas previas de WhatsApp ni cuando lo abrís vosotros desde aquí. «Cambiar enlace» deja sin servicio el que ya mandasteis.</p></section>';
}
