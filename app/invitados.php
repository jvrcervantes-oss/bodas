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

function inv_fichero(string $slug): string { return dir_boda($slug) . '/guardado/invitados.json'; }
function inv_lee(string $slug): array {
    $d = lee_json(inv_fichero($slug)) ?? [];
    return ['lista' => is_array($d['lista'] ?? null) ? $d['lista'] : [], 'manual' => is_array($d['manual'] ?? null) ? $d['manual'] : []];
}
function inv_id(string $nombre, string $grupo): string { return substr(sha1(clave_nombre($nombre) . '|' . clave_nombre($grupo)), 0, 12); }

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
    foreach (lee_json(dir_boda($slug) . '/guardado/rsvp.json') ?? [] as $r) {
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
        });
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
    return $o . '<details class="inv-editar"><summary>Editar la lista</summary>' . $nota . $form . '</details></section>';
}
