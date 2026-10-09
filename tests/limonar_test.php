<?php
// Piel «Limonar» de Cítricos y azahar (9-oct-2026, encargo 20261008_bodas_citricos_limonar): la piel no toca otro
// diseño, curva del itinerario con 0-8 paradas, calendario del mes y paradas del día. Sin red.
// Uso: php tests/limonar_test.php  (sale con 1 si algo falla). Datos inventados.

declare(strict_types=1);

$tmp = sys_get_temp_dir() . '/bodas_limonar_test_' . bin2hex(random_bytes(4));
mkdir($tmp, 0700, true);
define('SIN_CONFIG_LOCAL', true);
define('DATA_DIR', $tmp);
define('BASE_DOMAIN', 'bodaenlace.com');
define('CREATOR_HOST', 'bodaenlace.com');
define('CREATOR_BASE', '');
file_put_contents($tmp . '/secrets.php', '<?php return ' . var_export(['pasarela' => 'lemon', 'empresa' => ['titular' => 'Titular De Prueba', 'nif' => '00000000T', 'domicilio' => 'Calle Prueba 1']], true) . ';');
$raiz = dirname(__DIR__);
foreach (['core', 'schema', 'render', 'mapa'] as $m) require_once $raiz . '/app/' . $m . '.php';

$fallos = 0;
$n = 0;
function ok(bool $c, string $que): void { global $fallos, $n; $n++; if (!$c) { $fallos++; echo "FALLA: $que\n"; } }
function hay(string $h, string $t): bool { return strpos($h, $t) !== false; }

function crudo(array $cambia = []): array {
    $c = config_inicial();
    $c['pareja'] = ['nombre1' => 'Ana', 'nombre2' => 'Luis', 'union' => '&', 'email' => 'pareja@example.com'];
    $c['fecha'] = '2027-06-12';
    $c['ceremonia']['lugar'] = 'Ermita';
    $c['ceremonia']['hora'] = '12:00';
    return array_replace_recursive($c, $cambia);
}
function momentos(int $k): array {
    $o = [];
    for ($i = 0; $i < $k; $i++) $o[] = ['hora' => sprintf('%02d:%02d', 15 + intdiv($i, 2), ($i % 2) * 30), 'titulo' => 'Momento ' . ($i + 1), 'lugar' => '', 'nota' => ''];
    return $o;
}
$ctx = ['modo' => 'live', 'assets' => '/assets/', 'slug' => 'prueba', 'foto' => '', 'mapa' => null];

// 1. CSS: ninguna regla con .atelier-citricos comparte selector con otro diseño, y el bloque Limonar va todo bajo body.atelier-citricos
$css = (string) file_get_contents($raiz . '/assets/boda.css');
$sinComentarios = (string) preg_replace('~/\*.*?\*/~s', '', $css);
preg_match_all('~([^{}]+)\{~', $sinComentarios, $sel);
$mezcla = 0;
foreach ($sel[1] as $s) {
    if (strpos($s, 'atelier-citricos') === false) continue;
    foreach (array_keys(ATELIER) as $k) if ($k !== 'citricos' && preg_match('~\.atelier-' . $k . '~', $s)) $mezcla++;
}
ok($mezcla === 0, "ningún selector junta .atelier-citricos con otro diseño ($mezcla)");
$i = strrpos(substr($css, 0, (int) strpos($css, 'piel «Limonar»')), '/*');
ok($i !== false, 'el bloque Limonar existe');
$bloque = (string) preg_replace('~/\*.*?\*/~s', '', substr($css, (int) $i));
preg_match_all('~([^{}]+)\{~', $bloque, $sb);
$fuera = [];
foreach ($sb[1] as $s) foreach (explode(',', $s) as $parte) {
    $parte = trim($parte);
    if ($parte === '' || $parte[0] === '@' || preg_match('~^(from|to|\d+%)$~', $parte)) continue;
    if (strpos($parte, 'body.atelier-citricos') !== 0) $fuera[] = $parte;
}
ok(!$fuera, 'todo el bloque Limonar empieza por body.atelier-citricos: ' . implode(' | ', array_slice($fuera, 0, 3)));
ok(!preg_match('~body\.atelier-citricos[^{]*\{[^}]*box-shadow:\s*+(?!none)~', $bloque), 'Limonar no pone sombras');
ok(!preg_match('~body\.atelier-citricos[^{]*\{[^}]*font-weight:\s*([6-9]00|bold)~', $bloque), 'Limonar no pasa de peso 500');

// 2. Paradas del día: el programa del Esencial no cambia; la curva mete la ceremonia sin hora la primera
$c = normaliza_config(crudo(['programa' => momentos(2)]));
ok(array_column(paradas_dia($c), 'titulo') === ['Ceremonia', 'Momento 1', 'Momento 2'], 'paradas por hora (sin convite)');
$sinHora = normaliza_config(crudo(['ceremonia' => ['hora' => ''], 'convite' => ['lugar' => 'Finca', 'hora' => ''], 'programa' => momentos(1)]));
ok(array_column(paradas_dia($sinHora), 'titulo') === ['Momento 1'], 'sin $completa, lo que no tiene hora no entra (como antes)');
ok(array_column(paradas_dia($sinHora, true), 'titulo') === ['Ceremonia', 'Convite', 'Momento 1'], 'con $completa: ceremonia y convite sin hora delante');
$vacia = normaliza_config(crudo(['ceremonia' => ['lugar' => '', 'hora' => '']]));
ok(paradas_dia($vacia, true) === [], 'sin ceremonia ni convite ni programa: 0 paradas');

// 3. Curva: un <li> por parada (1, 3, 6, 8), alternando lado; sin paradas no hay curva
foreach ([0, 2, 5, 6] as $k) {
    $cc = normaliza_config(crudo(['atelier' => 'citricos', 'programa' => momentos($k)]));
    $esperado = 1 + min($k, MAX_PROGRAMA);
    $h = curva_itinerario($cc);
    ok(substr_count($h, '<li class="cit-paso') === $esperado, "curva con $esperado paradas");
    ok(substr_count($h, 'cit-izq') === intdiv($esperado + 1, 2) && substr_count($h, 'cit-der') === intdiv($esperado, 2), "lados alternos con $esperado");
}
$ocho = curva_itinerario(normaliza_config(crudo(['atelier' => 'citricos', 'convite' => ['lugar' => 'Finca', 'hora' => '14:00'], 'programa' => momentos(6)])));
ok(substr_count($ocho, '<li class="cit-paso') === 8, 'curva con 8 paradas (ceremonia, convite y 6 momentos)');
ok(curva_itinerario(normaliza_config(crudo(['atelier' => 'citricos', 'ceremonia' => ['lugar' => '', 'hora' => '']]))) === '', 'sin paradas, sin curva');
$dir = curva_itinerario(normaliza_config(crudo(['atelier' => 'citricos', 'ceremonia' => ['direccion' => 'Plaza Mayor 1'], 'convite' => ['lugar' => 'Finca', 'direccion' => 'Camino Viejo s/n', 'hora' => '14:00']])));
ok(substr_count($dir, 'class="cit-d"') === 2 && hay($dir, 'Plaza Mayor 1') && hay($dir, 'Camino Viejo s/n'), 'la dirección de ceremonia y convite se ve en la curva');
$x = curva_itinerario(normaliza_config(crudo(['atelier' => 'citricos', 'programa' => [['hora' => '13:00', 'titulo' => '<b>Brindis</b>', 'lugar' => 'Patio "A"', 'nota' => '']]])));
ok(hay($x, '&lt;b&gt;Brindis&lt;/b&gt;') && !hay($x, '<b>Brindis'), 'la curva escapa el texto');

// 4. Calendario: lunes, domingo, febrero normal y bisiesto; corazón en el día; sin fecha no sale
$feb27 = calendario_mes('2027-02-14');   // 1-feb-2027 es lunes, 28 días
ok(hay($feb27, '<tbody><tr><td>1</td>') && substr_count($feb27, '<td') === 28 && hay($feb27, '<td class="cit-boda">14'), 'febrero 2027: empieza en lunes, 28 días, 4 semanas justas');
$feb28 = calendario_mes('2028-02-29');   // 1-feb-2028 es martes, 29 días
ok(hay($feb28, '<tbody><tr><td></td><td>1</td>') && hay($feb28, '<td class="cit-boda">29'), 'febrero 2028 bisiesto: empieza en martes y llega al 29');
$ago27 = calendario_mes('2027-08-01');   // 1-ago-2027 es domingo
ok(hay($ago27, '<tbody><tr>' . str_repeat('<td></td>', 6) . '<td class="cit-boda">1'), 'agosto 2027: empieza en domingo (6 huecos)');
ok(substr_count($ago27, '<tr>') - 1 === 6, 'agosto 2027 ocupa 6 semanas');
ok(substr_count(calendario_mes('2027-06-12'), 'cit-corazon') === 1 && hay(calendario_mes('2027-06-12'), 'Junio <span>2027'), 'junio 2027: un corazón y el mes en el título');
ok(calendario_mes('') === '' && calendario_mes('no-fecha') === '', 'sin fecha válida no hay calendario');

// 5. Home de Cítricos: lleva la piel nueva; otro diseño no
$cit = (string) render_pagina(normaliza_config(crudo(['atelier' => 'citricos', 'programa' => momentos(2)])), '', $ctx);
ok(hay($cit, 'class="cit-curva"') && hay($cit, 'class="cit-cal"') && hay($cit, 'class="cit-fecha"') && hay($cit, '<i>&amp;</i>'), 'Cítricos: curva, calendario, fecha y «&» aparte');
ok(hay($cit, 'id="countdown"') && hay($cit, 'id="calBtn"') && hay($cit, 'id="calOptions"'), 'Cítricos conserva los enganches de boda.js');
ok(!hay($cit, 'class="event-list"'), 'Cítricos con paradas no pinta las tarjetas de siempre');
$largo = (string) render_pagina(normaliza_config(crudo(['atelier' => 'citricos', 'pareja' => ['nombre1' => 'María del Carmen', 'nombre2' => 'José Antonio']])), '', $ctx);
ok(hay($largo, 'María del Carmen <i>&amp;</i> José Antonio'), 'nombres largos: el «&» va aparte y los nombres enteros');
foreach (['lacre', 'herbario', ''] as $otro) {
    $h = (string) render_pagina(normaliza_config(crudo(['atelier' => $otro, 'programa' => momentos(2)])), '', $ctx);
    ok(!hay($h, 'cit-curva') && !hay($h, 'cit-cal') && !hay($h, '<i>&amp;</i>'), 'el diseño «' . ($otro ?: 'Esencial') . '» no lleva piezas de Limonar');
}

@array_map('unlink', glob($tmp . '/*') ?: []);
@rmdir($tmp);
echo ($fallos ? "ROJO: $fallos de $n\n" : "VERDE: $n/$n\n");
exit($fallos ? 1 : 0);
