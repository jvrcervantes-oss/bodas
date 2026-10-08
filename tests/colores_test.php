<?php
// Colores del dress code (8-oct-2026, encargo Limonar S4): normalización cerrada, avisos de lo descartado y muestras
// en web, vista previa y ZIP en todos los diseños. Sin red. Uso: php tests/colores_test.php  (sale con 1 si algo falla).
// Datos inventados.

declare(strict_types=1);

$tmp = sys_get_temp_dir() . '/bodas_colores_test_' . bin2hex(random_bytes(4));
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
function dia(int $d): string { return date('Y-m-d', strtotime(($d >= 0 ? '+' : '') . $d . ' days')); }

/** Config completa (sin faltas) como la manda el navegador: el array CRUDO, antes de normalizar. */
function crudo(array $cambia = []): array {
    $c = config_inicial();
    $c['pareja'] = ['nombre1' => 'Ana', 'nombre2' => 'Luis', 'union' => '&', 'email' => 'pareja@example.com'];
    $c['fecha'] = dia(100);
    $c['ceremonia']['lugar'] = 'Ermita';
    $c['ceremonia']['hora'] = '12:00';
    return array_replace_recursive($c, $cambia);
}
/** La sección Dress code con estos datos (se SUSTITUYEN, no se mezclan: así se prueban tipos raros) y encendida o no. */
function conDress(array $c, array $datos, bool $on = true): array {
    foreach ($c['secciones'] as &$s) if ($s['tipo'] === 'dresscode') { $s['on'] = $on; $s['datos'] = $datos; }
    unset($s);
    return $c;
}
function colores($lista): array {
    foreach (normaliza_config(conDress(crudo(), ['texto' => 'Etiqueta', 'colores' => $lista]))['secciones'] as $s) if ($s['tipo'] === 'dresscode') return $s['datos']['colores'];
    return ['NO HAY SECCION'];
}
function f(array $crudo): array { return faltan(normaliza_config($crudo), $crudo); }
function idDress(): string { foreach (config_inicial()['secciones'] as $s) if ($s['tipo'] === 'dresscode') return $s['id']; return ''; }
function ctx(string $modo): array {
    return ['live' => ['modo' => 'live', 'assets' => '/assets/', 'slug' => 'prueba', 'foto' => '', 'mapa' => null],
        'zip' => ['modo' => 'zip', 'assets' => 'assets/', 'slug' => 'prueba', 'foto' => '', 'mapa' => null],
        'preview' => ['modo' => 'preview', 'assets' => '/assets/', 'foto' => '', 'mapa' => null]][$modo];
}
function pag(array $crudo, string $ruta, string $modo = 'live'): string { return (string) render_pagina(normaliza_config($crudo), $ruta, ctx($modo)); }
function hay(string $h, string $t): bool { return strpos($h, $t) !== false; }
function cuenta(string $h, string $t): int { return substr_count($h, $t); }

$K = 'sec.' . idDress() . '.colores';

// ============================================================ 1. Normalización (norm_datos)
ok(colores(['#AABBCC', '#00Ff7a']) === ['#aabbcc', '#00ff7a'], 'mayúsculas: pasan a minúsculas');
ok(colores(['#aabbcc', '#AABBCC', '#aabbcc', '#112233']) === ['#aabbcc', '#112233'], 'repetidos (también por mayúsculas): fuera, conservando el orden');
ok(colores(['red', '#abc', '#gggggg', 'aabbcc', '#aabbccdd', '', ' #aabbcc', '#aabbcc ']) === [], 'no hex (nombres, 3 dígitos, letras fuera de rango, sin #, 8 dígitos, espacios): fuera');
ok(colores(['#aabbcc;background:url(x)', 'url(https://e.example/x.png)', '#aabbcc;', "#aabbcc\n", "#aabbcc\"onmouseover=x"]) === [], '«;», «url(», salto de línea final y comillas: fuera');
ok(colores(['#000001', '#000002', '#000003', '#000004', '#000005', '#000006', '#000007']) === ['#000001', '#000002', '#000003', '#000004', '#000005', '#000006'], '7 valores: se quedan los 6 primeros');
ok(colores([123, null, true, 1.5, ['#aabbcc'], (object) []]) === [], 'tipos raros (número, null, bool, lista, objeto): fuera');
ok(colores(['a' => '#aabbcc', 'b' => '#112233']) === [], 'un objeto en vez de lista: no son colores');
ok(colores('#aabbcc') === [] && colores(42) === [] && colores(null) === [], 'un escalar o null en vez de lista: []');
$sinClave = normaliza_config(conDress(crudo(), ['texto' => 'X']));
foreach ($sinClave['secciones'] as $s) if ($s['tipo'] === 'dresscode') ok($s['datos'] === ['texto' => 'X', 'colores' => []], 'sin «colores» (bodas de antes): la sección se normaliza con colores = []');
ok(datos_iniciales('dresscode') === ['texto' => '', 'colores' => []], 'datos iniciales del dress code: lista vacía');

// ============================================================ 2. Lo descartado se avisa (y bloquea publicar); los repetidos NO
ok(f(crudo()) === [], 'base: una config completa no falta nada');
ok(f(conDress(crudo(), ['texto' => '', 'colores' => []])) === [], 'sin colores: nada que avisar');
ok(f(conDress(crudo(), ['texto' => '', 'colores' => ['#AABBCC', '#aabbcc', '#AaBbCc', '#112233']])) === [], 'repetidos y mayúsculas: ni avisan ni bloquean');
ok(f(conDress(crudo(), ['texto' => '', 'colores' => ['#000001', '#000002', '#000003', '#000004', '#000005', '#000006', '#000006']])) === [], '7 valores con un repetido (6 distintos): no avisa');
$d = datos_descartados(conDress(crudo(), ['texto' => '', 'colores' => ['#aabbcc', 'red', 'url(x)']]));
ok(isset($d[$K]) && hay($d[$K], '2 colores que no son válidos') && hay($d[$K], 'Dress code'), 'no válidos: salen en datos_descartados con su número y el nombre de la sección');
ok(isset(f(conDress(crudo(), ['texto' => '', 'colores' => ['#aabbcc;']]))[$K]), 'un «;»: bloquea por faltan()');
$d = datos_descartados(conDress(crudo(), ['texto' => '', 'colores' => ['#000001', '#000002', '#000003', '#000004', '#000005', '#000006', '#000007']]));
ok(isset($d[$K]) && hay($d[$K], 'caben 6') && hay($d[$K], 'sobran 1'), 'más de 6: sale en datos_descartados');
ok(isset(datos_descartados(conDress(crudo(), ['texto' => '', 'colores' => [42, null]]))[$K]), 'tipos raros: salen en datos_descartados');
ok(isset(datos_descartados(conDress(crudo(), ['texto' => '', 'colores' => ['a' => '#aabbcc']]))[$K]), 'objeto en vez de lista: sale en datos_descartados');
ok(isset(datos_descartados(conDress(crudo(), ['texto' => '', 'colores' => '#aabbcc']))[$K]), 'escalar en vez de lista: sale en datos_descartados');
ok(!isset(datos_descartados(conDress(crudo(), ['texto' => '', 'colores' => null]))[$K]), 'colores = null (ausente): no avisa');
ok(!isset(datos_descartados(conDress(crudo(), ['texto' => '', 'colores' => ['red', 'url(x)']], false))[$K]), 'sección apagada: no avisa (solo se mira lo que se publica)');

// ============================================================ 3. Render: solo lo normalizado, en todos los diseños y modos
$conCol = conDress(crudo(), ['texto' => 'Traje oscuro.', 'colores' => ['#AABBCC', '#ffffff', '#aabbcc', 'red;x', 'url(evil)', "#123456\"x"]]);
foreach (['live', 'zip', 'preview'] as $modo) {
    $p = pag($conCol, 'dress-code', $modo);
    ok(cuenta($p, 'class="muestra"') === 2 && hay($p, 'style="background-color:#aabbcc"') && hay($p, 'style="background-color:#ffffff"'), "$modo, página Dress code: dos muestras, las normalizadas");
    ok(!hay($p, 'red;x') && !hay($p, 'url(') && !hay($p, '#AABBCC') && !hay($p, '#123456'), "$modo, página Dress code: nada de lo descartado llega al HTML");
    ok(hay($p, 'Traje oscuro.') && !hay($p, 'Pronto os contamos más'), "$modo, página Dress code: el texto sigue y las muestras van detrás");
    $home = pag($conCol, '', $modo);
    ok(cuenta($home, 'class="muestra"') === 2 && hay($home, 'Código de vestimenta'), "$modo, home sin vestimenta: la fila de Dress code lleva las muestras");
}
$soloCol = conDress(crudo(), ['texto' => '', 'colores' => ['#aabbcc']]);
ok(!hay(pag($soloCol, 'dress-code'), 'Pronto os contamos más') && cuenta(pag($soloCol, 'dress-code'), 'class="muestra"') === 1, 'solo colores, sin texto: las muestras sustituyen al «Pronto os contamos más»');
ok(hay(pag(conDress(crudo(), ['texto' => '', 'colores' => []]), 'dress-code'), 'Pronto os contamos más'), 'sin texto ni colores: sigue el «Pronto os contamos más»');

// Píldora de vestimenta de la portada: solo con la sección Dress code ACTIVA
$vest = conDress(crudo(['portada' => ['vestimenta' => 'Etiqueta']]), ['texto' => 'X', 'colores' => ['#aabbcc', '#112233']]);
$hv = pag($vest, '');
ok(hay($hv, '<span class="pildora-vest-txt">Etiqueta</span><span class="muestras muestras-vest"') && cuenta($hv, 'class="muestra"') === 2, 'vestimenta + Dress code activo: muestras dentro de la píldora (y la fila de la home no se duplica)');
$vestOff = conDress(crudo(['portada' => ['vestimenta' => 'Etiqueta']]), ['texto' => 'X', 'colores' => ['#aabbcc']], false);
ok(hay(pag($vestOff, ''), '<span class="pildora-vest-txt">Etiqueta</span></p>') && !hay(pag($vestOff, ''), 'class="muestra"'), 'vestimenta + Dress code apagado: píldora sin muestras');
ok(hay(pag(conDress(crudo(['portada' => ['vestimenta' => 'Etiqueta']]), ['texto' => 'X', 'colores' => []]), ''), '<span class="pildora-vest-txt">Etiqueta</span></p>'), 'vestimenta sin colores: la píldora de siempre');

// Todos los diseños (Esencial y cada Atelier) pintan las muestras con el mismo generador
foreach (array_merge([''], array_keys(ATELIER)) as $at) {
    $cfg = $conCol;
    $cfg['atelier'] = $at;
    $nom = $at === '' ? 'Esencial' : "Atelier $at";
    ok(cuenta(pag($cfg, 'dress-code', 'live'), 'class="muestra"') === 2 && cuenta(pag($cfg, 'dress-code', 'zip'), 'class="muestra"') === 2, "$nom: muestras en la página Dress code (live y zip)");
    ok(cuenta(pag($cfg, '', 'live'), 'class="muestra"') === 2, "$nom: muestras en la fila de la home");
}

// ============================================================ 4. Sin colores, el HTML no cambia ni un byte
$base = conDress(crudo(), ['texto' => 'Traje oscuro.']);
$vacio = conDress(crudo(), ['texto' => 'Traje oscuro.', 'colores' => []]);
$malos = conDress(crudo(), ['texto' => 'Traje oscuro.', 'colores' => ['red', 'url(x)']]);
foreach (['live', 'zip', 'preview'] as $modo) {
    foreach (['', 'dress-code'] as $r) {
        $h0 = pag($base, $r, $modo);
        ok($h0 !== '' && !hay($h0, 'muestra') && $h0 === pag($vacio, $r, $modo) && $h0 === pag($malos, $r, $modo), "$modo /$r: sin colores válidos, mismo HTML que sin el campo y sin rastro de muestras");
    }
}

@array_map('unlink', glob($tmp . '/*') ?: []);
@rmdir($tmp);
echo ($fallos ? "ROJO: $fallos de $n\n" : "VERDE: $n/$n\n");
exit($fallos ? 1 : 0);
