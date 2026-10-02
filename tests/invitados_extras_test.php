<?php
// Quien se añade al confirmar por el enlace de un grupo (Marta Gil confirma por su enlace y añade a su hijo) tiene que salir en la lista
// de invitados, en el recuento del grupo y en la exportación: antes la lista solo enseñaba lo que la pareja había pegado.
// Función pura contra un DATA_DIR temporal, sin servidor. Uso: php tests/invitados_extras_test.php (sale con 1 si falla)

declare(strict_types=1);

$tmp = sys_get_temp_dir() . '/bodas_extras_test_' . bin2hex(random_bytes(4));
mkdir($tmp, 0700, true);
define('SIN_CONFIG_LOCAL', true);
define('DATA_DIR', $tmp);
define('BASE_DOMAIN', 'bodaenlace.com');
define('CREATOR_HOST', 'bodaenlace.com');
define('CREATOR_BASE', '');
define('CORREO_A_FICHERO', true);
$raiz = dirname(__DIR__);
foreach (['core', 'schema', 'render', 'foto', 'alta', 'mapa', 'cortesia', 'estudio', 'borrador', 'invitados', 'exportar', 'creador', 'landing', 'vista_constructor', 'boda', 'galeria', 'proxy'] as $m) {
    require_once $raiz . '/app/' . $m . '.php';
}
$fallos = 0;
$n = 0;
function ok(bool $c, string $q): void { global $fallos, $n; $n++; if (!$c) { $fallos++; echo "FALLA: $q\n"; } }

$slug = 'extras-prueba';
$c = normaliza_config(config_inicial());
escribe_json(dir_boda($slug) . '/config.json', $c);
$lista = inv_parsea("Marta Gil\nAna Pérez; Familia Pérez\nCarlos Pérez; Familia Pérez\nJuan Ruiz; Tíos\nEva Ruiz; Tíos");
muta_json(inv_fichero($slug), function (array &$d) use ($lista) { $d['lista'] = $lista; inv_sincroniza_grupos($d); });
$inv = inv_lee($slug);
$gid = fn(string $k) => $inv['grupos'][$k]['gid'];
$per = fn(string $nombre) => ['id' => bin2hex(random_bytes(8)), 'nombre' => $nombre, 'tipo' => 'adulto', 'menu' => 'general', 'menu_nombre' => 'General', 'alergias' => ''];
$resp = fn(string $id, array $ps, bool $viene, string $grupo = '') => ['id' => $id, 'fecha_envio' => date('c'), 'invitados' => $ps, 'asiste_ceremonia' => $viene, 'asiste_banquete' => $viene, 'contacto' => '600000000']
    + ($grupo !== '' ? ['grupo' => $grupo] : []);
$rsvp = dir_boda($slug) . '/guardado/rsvp.json';

// 1. Marta Gil confirma por su enlace y añade a su hijo: la lista pasa de 5 a 6 y el hijo sale con su grupo
escribe_json($rsvp, [$resp('r1', [$per('Marta Gil'), $per('Leo Gil')], true, $gid('p:' . inv_id('Marta Gil', '')))]);
[$filas, $res] = inv_cruza($slug, $c);
$por = array_column($filas, null, 'nombre');
ok(count($filas) === 6 && isset($por['Leo Gil']) && !empty($por['Leo Gil']['extra']) && $por['Leo Gil']['estado'] === 'viene', 'Marta añade a su hijo: la lista tiene 6 y Leo Gil sale como «viene»');
ok($por['Leo Gil']['grupo'] === 'Marta Gil' && $por['Marta Gil']['estado'] === 'viene' && empty($por['Marta Gil']['extra']), 'el hijo sale en el grupo de Marta y Marta sigue contando como suya');
ok($res === ['viene' => 2, 'no' => 0, 'pend' => 4], 'recuento: 2 vienen y 4 sin contestar');

// 2. Exportación y recuento del grupo
[$exp] = exp_filas($slug, $c);
ok(count($exp) === 6 && in_array('Leo Gil', array_column($exp, 1), true), 'la exportación lleva a Leo Gil');
$c['secciones'] = array_map(function ($s) { if ($s['tipo'] === 'rsvp') $s['on'] = true; return $s; }, $c['secciones']);
require_once $raiz . '/app/panel.php';
$G = array_column(panel_grupos($slug, $c), null, 'nombre');
ok(($G['Marta Gil']['extras'] ?? []) === ['Leo Gil'] && ($G['Marta Gil']['estado'] ?? '') === 'conf', 'el grupo de Marta lista a su hijo como añadido');

// 3. Escribe «Marta» (una palabra) en vez de «Marta Gil»: es ella, no una persona nueva; el hijo sí
escribe_json($rsvp, [$resp('r1', [$per('Marta'), $per('Leo')], true, $gid('p:' . inv_id('Marta Gil', '')))]);
[$filas, $res] = inv_cruza($slug, $c);
$por = array_column($filas, null, 'nombre');
ok(count($filas) === 6 && ($por['Marta Gil']['estado'] ?? '') === 'viene' && !isset($por['Marta']) && isset($por['Leo']), '«Marta» = Marta Gil por llegar por su enlace; «Leo» es el añadido');

// 4. Un grupo que dice que no viene: lo añadido también cuenta como que no viene
escribe_json($rsvp, [$resp('r1', [$per('Ana Pérez'), $per('Carlos Pérez'), $per('Tía Lola')], false, $gid('g:familia perez'))]);
[$filas, $res] = inv_cruza($slug, $c);
$por = array_column($filas, null, 'nombre');
ok(($por['Tía Lola']['estado'] ?? '') === 'no' && !empty($por['Tía Lola']['extra']) && $res['no'] === 3, 'grupo que no viene: la persona añadida sale como «no viene»');

// 5. Alguien que ya está en la lista en OTRO grupo no se duplica como añadido
escribe_json($rsvp, [$resp('r1', [$per('Marta Gil'), $per('Juan Ruiz')], true, $gid('p:' . inv_id('Marta Gil', '')))]);
[$filas] = inv_cruza($slug, $c);
ok(count($filas) === 5 && count(array_filter($filas, fn($f) => $f['nombre'] === 'Juan Ruiz')) === 1, 'Juan Ruiz ya estaba en la lista (otro grupo): no se duplica');

// 6. La confirmación general (sin enlace de grupo) no añade gente a la lista: es abierta
escribe_json($rsvp, [$resp('r1', [$per('Desconocido Cualquiera')], true)]);
[$filas] = inv_cruza($slug, $c);
ok(count($filas) === 5, 'la confirmación general no añade filas a la lista');

// 7. Un grupo con una respuesta sustituida por otra: solo cuenta la vigente
escribe_json($rsvp, [$resp('r1', [$per('Marta Gil'), $per('Leo Gil')], true, $gid('p:' . inv_id('Marta Gil', ''))) + ['sustituido' => 'r2'],
    $resp('r2', [$per('Marta Gil')], true, $gid('p:' . inv_id('Marta Gil', '')))]);
[$filas] = inv_cruza($slug, $c);
ok(count($filas) === 5, 'si el grupo corrigió su respuesta, el añadido anterior ya no sale');

foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $x) {
    $x->isDir() ? @rmdir($x->getPathname()) : @unlink($x->getPathname());
}
@rmdir($tmp);
echo ($fallos ? 'ROJO' : 'VERDE') . ': ' . ($n - $fallos) . "/$n\n";
exit($fallos ? 1 : 0);
