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

/** ¿Son la misma persona por nombre? Igual, o uno contiene todas las palabras del otro y el más corto tiene al menos `$minimo`. */
function inv_coincide(array $a, array $b, int $minimo = 2): bool {
    $corto = count($a) <= count($b) ? $a : $b;
    $largo = $corto === $a ? $b : $a;
    return $a === $b || (count($corto) >= $minimo && !array_diff($corto, $largo));
}

/**
 * Lo que trajo cada respuesta llegada por el enlace de un grupo de la lista (la lista dice quién está invitado; la
 * respuesta puede traer más gente: «Marta Gil» confirma y añade a su hijo):
 *   gid => ['viene', 'nombre' (del grupo), 'pares' => [id de fila de la lista => true], 'extras' => [nombres añadidos]]
 *  - Primero se empareja por nombre (como inv_cruza). Lo que sobra se empareja con las filas libres del MISMO grupo si el
 *    nombre cabe dentro del otro aunque sea de una sola palabra («Marta» ≈ «Marta Gil»): llegó por su enlace.
 *  - Lo que aún sobra es una persona añadida al confirmar. Si su nombre ya está en la lista en otro grupo, es esa
 *    persona y no se duplica. Solo respuestas con grupo: la confirmación general es abierta y no dice a quién se invitó.
 */
function inv_por_enlace(string $slug): array {
    $inv = inv_lee($slug);
    $grupos = inv_grupos_de($inv['lista']);
    $filasDe = [];
    $todas = [];
    foreach ($inv['lista'] as $g) { $filasDe[inv_clave_grupo($g)][] = $g; $todas[] = inv_palabras((string) $g['nombre']); }
    $porGid = [];
    foreach (rsvp_vigentes($slug) as $r) if (($r['grupo'] ?? '') !== '') $porGid[(string) $r['grupo']] = $r;
    $o = [];
    foreach ($inv['grupos'] as $k => $e) {
        $gid = is_array($e) ? (string) ($e['gid'] ?? '') : '';
        if ($gid === '' || !isset($porGid[$gid], $grupos[$k])) continue;
        $r = $porGid[$gid];
        $libres = $filasDe[$k] ?? [];
        $sobran = [];
        foreach (personas($r) as $p) {
            $pr = inv_palabras((string) $p['nombre']);
            $hit = null;
            foreach ($libres as $i => $g) if (inv_coincide(inv_palabras((string) $g['nombre']), $pr)) { $hit = $i; break; }
            if ($hit !== null) unset($libres[$hit]); else $sobran[] = (string) $p['nombre'];
        }
        $pares = [];
        $extras = [];
        foreach ($sobran as $nombre) {
            $pr = inv_palabras($nombre);
            $hit = null;
            foreach ($libres as $i => $g) if (inv_coincide(inv_palabras((string) $g['nombre']), $pr, 1)) { $hit = $i; break; }
            if ($hit !== null) { $pares[(string) $libres[$hit]['id']] = true; unset($libres[$hit]); continue; }
            $enLista = false;
            foreach ($todas as $pg) if (inv_coincide($pg, $pr)) { $enLista = true; break; }
            if (!$enLista && trim($nombre) !== '') $extras[] = $nombre;
        }
        $o[$gid] = ['viene' => !empty($r['asiste_ceremonia']) || !empty($r['asiste_banquete']), 'nombre' => $grupos[$k]['nombre'], 'pares' => $pares, 'extras' => $extras];
    }
    return $o;
}

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
    $enlace = inv_por_enlace($slug);
    $par = [];   // fila de la lista → si viene, según la respuesta que llegó por el enlace de su grupo
    foreach ($enlace as $x) foreach ($x['pares'] as $id => $_) $par[$id] = $x['viene'];
    foreach ($inv['lista'] as $g) {
        $pg = inv_palabras($g['nombre']);
        $auto = 'pend';
        foreach ($resp as [$pr, $viene]) {
            if (inv_coincide($pg, $pr)) { $auto = $viene ? 'viene' : 'no'; if ($viene) break; }
        }
        if ($auto === 'pend' && isset($par[$g['id']])) $auto = $par[$g['id']] ? 'viene' : 'no';
        $manual = (string) ($inv['manual'][$g['id']] ?? '');
        $estado = in_array($manual, ['viene', 'no', 'pend'], true) ? $manual : $auto;
        $res[$estado]++;
        $filas[] = $g + ['estado' => $estado, 'manual' => $manual !== ''];
    }
    // Quien se añadió al confirmar por el enlace de un grupo: sale en la lista, con el grupo que lo trajo (sin corrección a mano: no está en la lista)
    foreach ($enlace as $gid => $x) {
        foreach ($x['extras'] as $nombre) {
            $estado = $x['viene'] ? 'viene' : 'no';
            $res[$estado]++;
            $filas[] = ['id' => substr(sha1('x|' . $gid . '|' . clave_nombre($nombre)), 0, 12), 'nombre' => $nombre, 'grupo' => (string) $x['nombre'], 'estado' => $estado, 'manual' => false, 'extra' => true];
        }
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
        header('Location: /panel/invitados#enlaces', true, 303);
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
    header('Location: /panel/invitados' . ($acc === 'marcar' ? '#personas' : ''), true, 303);
    exit;
}
