<?php
// Enlace personal por grupo y cuentas sin sustituidas (F1a/F1b, revisión previa #133, 27-sep-2026).
// Sin red ni servidor: llama a las funciones sobre un DATA_DIR temporal.
// Uso: php tests/enlace_grupo_test.php  (sale con 1 si algo falla)

declare(strict_types=1);

$tmp = sys_get_temp_dir() . '/bodas_grupo_test_' . bin2hex(random_bytes(4));
define('SIN_CONFIG_LOCAL', true);
define('DATA_DIR', $tmp);
define('BASE_DOMAIN', 'bodaenlace.com');
define('CREATOR_HOST', 'bodaenlace.com');
define('CREATOR_BASE', '');
$raiz = dirname(__DIR__);
foreach (['core', 'schema', 'render', 'foto', 'alta', 'mapa', 'cortesia', 'estudio', 'borrador', 'invitados', 'creador', 'landing', 'vista_constructor', 'boda', 'galeria', 'proxy'] as $m) {
    require_once $raiz . '/app/' . $m . '.php';
}
$fallos = 0;
function ok(bool $c, string $q): void { global $fallos; if (!$c) { $fallos++; echo "FALLA: $q\n"; } else echo "ok: $q\n"; }

$slug = 'grupo-test';
asegura_dir(dir_boda($slug) . '/guardado');

// --- personas(): id propio, id derivado estable para lo antiguo, '' sin id de registro
$viejo = ['id' => 'aaaa000011112222', 'invitados' => [['nombre' => 'Ana'], ['nombre' => 'Leo']]];
$p1 = personas($viejo);
ok($p1[0]['id'] !== '' && !preg_match('/^[a-f0-9]{16}$/', $p1[0]['id']) && $p1[0]['id'] !== $p1[1]['id'], 'respuesta antigua: id derivado, nunca confundible con uno aleatorio, distinto por persona');
ok(personas($viejo)[1]['id'] === $p1[1]['id'], 'el id derivado es estable entre lecturas');
ok(personas(['invitados' => [['nombre' => 'X']]])[0]['id'] === '', 'sin id de registro: id vacío (sin mesa)');
ok(personas(['id' => 'r', 'invitados' => [['id' => 'abcdef0123456789', 'nombre' => 'Y']]])[0]['id'] === 'abcdef0123456789', 'id aleatorio guardado se conserva');

// --- lista con 3 grupos (uno de una persona sin grupo) → 3 tokens de 22 caracteres
muta_json(inv_fichero($slug), function (array &$d) {
    $d['lista'] = inv_parsea("Ana Pérez; Familia Lucía\nCarlos Pérez; Familia Lucía\nPedro Ruiz; Amigos\nMarta Gil");
    inv_sincroniza_grupos($d);
});
$inv = inv_lee($slug);
ok(count($inv['grupos']) === 3, '3 grupos → 3 enlaces');
ok(count(array_filter($inv['grupos'], fn($g) => preg_match(INV_TOKEN_RE, $g['token']))) === 3, 'tokens base64url de 22 caracteres (128 bits)');
$tokFam = $inv['grupos']['g:familia lucia']['token'];
$g = inv_grupo_por_token($slug, $tokFam);
ok($g !== null && $g['nombre'] === 'Familia Lucía' && $g['personas'] === ['Ana Pérez', 'Carlos Pérez'], 'el token resuelve su grupo con sus nombres');
ok(inv_grupo_por_token($slug, 'AAAAAAAAAAAAAAAAAAAAAA') === null, 'token inventado: nada');
ok(inv_grupo_por_token($slug, $inv['grupos']['p:' . inv_id('Marta Gil', '')]['token'])['nombre'] === 'Marta Gil', 'sin grupo: grupo de una persona con su nombre');

// --- volver a pegar la lista conserva los enlaces de los grupos que siguen
muta_json(inv_fichero($slug), function (array &$d) {
    $d['lista'] = inv_parsea("Ana Pérez; Familia Lucía\nCarlos Pérez; Familia Lucía\nPedro Ruiz; Amigos\nMarta Gil\nNuevo; Primos");
    inv_sincroniza_grupos($d);
});
ok(inv_lee($slug)['grupos']['g:familia lucia']['token'] === $tokFam, 'volver a pegar la lista no cambia los enlaces');

// --- primer acceso: solo fecha, una vez
inv_marca_abierto($slug, $g['gid']);
$a1 = inv_lee($slug)['grupos']['g:familia lucia']['abierto'];
ok($a1 !== '' && array_keys(inv_lee($slug)['grupos']['g:familia lucia']) === ['gid', 'token', 'abierto'], 'primer acceso guarda solo la fecha');

// --- sustituidos: dos respuestas del mismo grupo = una cuenta
$rec = fn(string $id, int $n, string $grupo = '') => ['id' => $id, 'asiste_banquete' => true, 'asiste_ceremonia' => true]
    + ($grupo !== '' ? ['grupo' => $grupo] : [])
    + ['invitados' => array_map(fn($i) => ['id' => bin2hex(random_bytes(8)), 'nombre' => "P$i", 'tipo' => 'adulto', 'menu' => 'general', 'menu_nombre' => 'Menú', 'alergias' => $i === 0 ? 'nueces' : ''], range(0, $n - 1))];
escribe_json(dir_boda($slug) . '/guardado/rsvp.json', [$rec('r1', 2, $g['gid']) + ['sustituido' => 'r2'], $rec('r2', 3, $g['gid']), $rec('r3', 1)]);
$v = rsvp_vigentes($slug);
ok(count($v) === 2 && array_sum(array_map(fn($r) => count(personas($r)), $v)) === 4, 'rsvp_vigentes deja fuera la sustituida (4 personas, no 6)');
// rsvp_anade: un reenvío del grupo marca la anterior y le vacía las alergias (Legal, RGPD 5.1.c)
$d = [$rec('a1', 2, $g['gid']), $rec('a2', 1)];
rsvp_anade($d, $rec('a3', 2, $g['gid']));
ok(($d[0]['sustituido'] ?? '') === 'a3' && implode('', array_column($d[0]['invitados'], 'alergias')) === '' && empty($d[1]['sustituido'])
    && $d[2]['invitados'][0]['alergias'] === 'nueces', 'reenvío: anterior sustituida y sin alergias; otros grupos y la nueva intactos');

// --- BOD-24 (Seguridad 27-sep): el reenvío del grupo conserva el id de quien sale UNA vez con ese nombre en el grupo
$pp = fn(string $nombre, string $id = '') => ['id' => $id !== '' ? $id : bin2hex(random_bytes(8)), 'nombre' => $nombre, 'tipo' => 'adulto', 'menu' => 'general', 'menu_nombre' => 'Menú', 'alergias' => ''];
$rr = fn(string $id, array $ps, string $grupo = '') => ['id' => $id, 'asiste_banquete' => true] + ($grupo !== '' ? ['grupo' => $grupo] : []) + ['invitados' => $ps];
$d = [$rr('h1', [$pp('Ana Pérez', 'aaaaaaaaaaaaaaa1'), $pp('Leo', 'aaaaaaaaaaaaaaa2')], 'g1')];
rsvp_anade($d, $rr('h2', [$pp('ana perez'), $pp('Leo'), $pp('Mía')], 'g1'));
ok(array_column($d[1]['invitados'], 'id') === ['aaaaaaaaaaaaaaa1', 'aaaaaaaaaaaaaaa2', $d[1]['invitados'][2]['id']] && $d[1]['invitados'][2]['id'] !== 'aaaaaaaaaaaaaaa1',
    '(a) reenvío con los mismos nombres (sin tildes ni mayúsculas): mismos ids; la persona nueva, id nuevo');
$d = [$rr('h1', [$pp('Ana', 'bbbbbbbbbbbbbbb1'), $pp('Ana', 'bbbbbbbbbbbbbbb2'), $pp('Leo', 'bbbbbbbbbbbbbbb3')], 'g1')];
rsvp_anade($d, $rr('h2', [$pp('Ana'), $pp('Leo'), $pp('Leo')], 'g1'));
ok(!array_intersect(array_column($d[1]['invitados'], 'id'), ['bbbbbbbbbbbbbbb1', 'bbbbbbbbbbbbbbb2', 'bbbbbbbbbbbbbbb3']), '(b) dos «Ana» en la vieja o dos «Leo» en la nueva: id nuevo, no se adivina');
$viejaV = ['id' => 'rv1', 'grupo' => 'g1', 'invitados' => [['nombre' => 'Ana'], ['nombre' => 'Leo']]];
$idV = personas($viejaV)[0]['id'];
$d = [$viejaV];
rsvp_anade($d, $rr('h2', [$pp('Ana')], 'g1'));
ok(str_starts_with($idV, 'v') && $d[1]['invitados'][0]['id'] === $idV && personas($d[1])[0]['id'] === $idV, '(c) respuesta de antes con id «v…»: se hereda y personas() lo conserva');
$d = [$rr('h1', [$pp('Ana', 'ccccccccccccccc1')], 'g1'), $rr('h3', [$pp('Leo', 'ccccccccccccccc2')]), $rr('h4', [$pp('Mía', 'ccccccccccccccc3')], 'g2')];
rsvp_anade($d, $rr('h5', [$pp('Leo'), $pp('Mía')], 'g1'));
ok(!array_intersect(array_column($d[3]['invitados'], 'id'), ['ccccccccccccccc2', 'ccccccccccccccc3']) && empty($d[1]['sustituido']) && empty($d[2]['sustituido'])
    && $d[1]['invitados'][0]['id'] === 'ccccccccccccccc2', '(e) nunca se hereda de una general ni de otro grupo, y esas quedan intactas');
$d = [$rr('h1', [$pp('Ana', 'ddddddddddddddd1')], 'g1'), $rr('h2', [$pp('Ana', 'ddddddddddddddd2')], 'g1')];   // dos vigentes del grupo (datos de antes del arreglo)
rsvp_anade($d, $rr('h3', [$pp('Ana')], 'g1'));
ok(!in_array($d[2]['invitados'][0]['id'], ['ddddddddddddddd1', 'ddddddddddddddd2'], true) && $d[0]['sustituido'] === 'h3' && $d[1]['sustituido'] === 'h3', 'candidatas = todas las vigentes del grupo juntas: «Ana» en dos, ambigua');

// --- BOD-22 (Seguridad 27-sep): «es la misma respuesta», solo general vigente sin grupo + del grupo vigente
$base = [$rr('m1', [$pp('Ana') + ['alergias' => 'gluten']]), $rr('m2', [$pp('Ana')], 'g1'), $rr('m3', [$pp('Leo')], 'g1') + ['sustituido' => 'm2'], $rr('m4', [$pp('Mía')])];
$d = $base; $r = rsvp_misma($d, 'm1', 'm2');
ok($r === '' && $d[0]['sustituido'] === 'm2' && $d[0]['sustituido_por'] === 'pareja' && ($d[0]['sustituido_fecha'] ?? '') !== '' && $d[0]['invitados'][0]['alergias'] === ''
    && $d[1] === $base[1], 'misma: la general queda sustituida por la del grupo, anotado quién y cuándo, sin alergias; la del grupo intacta');
foreach ([['m1', 'm3', 'la del grupo ya sustituida'], ['m2', 'm1', 'la «general» con grupo'], ['m1', 'm4', 'la otra sin grupo'], ['m1', 'x9', 'id que no está'], ['m1', 'm1', 'la misma dos veces']] as [$a1, $a2, $q]) {
    $d = $base; ok(rsvp_misma($d, $a1, $a2) !== '' && $d === $base, "misma rechazada sin tocar nada: $q");
}
$d = $base; rsvp_misma($d, 'm1', 'm2');
ok(rsvp_misma($d, 'm1', 'm2') !== '', 'misma: una general ya sustituida no se vuelve a juntar');
ok(rsvp_posibles_mismas([$base[0], $base[1], $base[3]]) === ['m1' => ['m2']], 'posibles: solo la general con un nombre del grupo');

$c = config_inicial();
[, $st, $menus] = panel_datos($slug, $c);
ok($st['personas'] === 4 && array_sum(array_column($menus, 'n')) === 4, 'panel_datos cuenta sin sustituidas');

// --- catering: misma suma que el panel, alergias con nombre, no-store
ob_start();
$er = error_reporting(E_ALL & ~E_WARNING);   // header() en CLI avisa de que ya hubo salida; aquí se mide el HTML
panel_catering($slug, $c);
error_reporting($er);
$html = (string) ob_get_clean();
ok(strpos($html, 'catering-total"><td>Total</td><td>4<') !== false, 'catering suma 4, igual que el panel');
ok(substr_count($html, 'class="alergia">nueces') === 2, 'catering lista una alergia por grupo vigente (2), no la sustituida');

// --- página del grupo: sin «que confirme por su cuenta», que por este enlace sustituiría al grupo entero
$ci = config_inicial();
$ci['convite']['lugar'] = 'Finca';   // la nota solo sale si se pregunta a qué se asiste y hay convite
foreach ($ci['secciones'] as &$sx) if ($sx['tipo'] === 'rsvp') $sx['datos']['asistencia'] = true;
unset($sx);
$cr = normaliza_config($ci);   // como una boda guardada: con la ruta de cada sección
$srsvp = seccion_tipo($cr, 'rsvp');
$ctxg = ctx_live($slug) + ['grupo' => ['nombre' => $g['nombre'], 'personas' => $g['personas'], 'token' => $tokFam],
    'rsvp_ruta' => $srsvp['ruta'], 'rsvp_href' => '/i/' . $tokFam];
$fg = (string) render_pagina($cr, $srsvp['ruta'], $ctxg);   // igual que pagina_grupo()
$fn = (string) render_pagina($cr, $srsvp['ruta'], ctx_live($slug));
ok(strpos($fg, 'decídselo a los novios') !== false && strpos($fg, 'que confirme por su cuenta') === false, 'página del grupo: no invita a confirmar por separado');
ok(strpos($fn, 'que confirme por su cuenta') !== false, 'confirmación general: mantiene la nota de siempre');

// limpieza
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($it as $f) $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
@rmdir($tmp);
echo $fallos ? "ROJO: $fallos\n" : "VERDE\n";
exit($fallos ? 1 : 0);
