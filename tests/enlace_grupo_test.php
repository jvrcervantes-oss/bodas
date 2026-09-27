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
ok($p1[0]['id'] !== '' && $p1[0]['id'][0] === 'd' && $p1[0]['id'] !== $p1[1]['id'], 'respuesta antigua: id derivado, con «d» y distinto por persona');
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

// limpieza
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($it as $f) $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
@rmdir($tmp);
echo $fallos ? "ROJO: $fallos\n" : "VERDE\n";
exit($fallos ? 1 : 0);
