<?php
// Palancas de los diseños Atelier (encargo 20261009_bodas_editor_disenos_atelier, S3): el catálogo, la normalización y
// la emisión de app/disenos.php. Criterios del encargo:
//  (b) fixture hostil → salida idéntica byte a byte a la de fábrica;   (c) un valor inválido revierte solo su palanca;
//  (d) cada var(--at-*) de boda.css tiene palanca (o está declarada fija) y viceversa, y su defecto coincide con el CSS;
//  (e) render.php no lee DATA_DIR;   (f) ninguna palanca mueve una variable con dos papeles.
// (a) —sin ajustes, estilos computados idénticos a la línea base— lo mide tests/estilos_atelier.js.
// Uso: php tests/disenos_test.php  (sale con 1 si algo falla). Sin red.

declare(strict_types=1);

$tmp = sys_get_temp_dir() . '/bodas_disenos_test_' . bin2hex(random_bytes(4));
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

function web(string $k): array {
    $c = json_decode((string) file_get_contents(dirname(__DIR__) . "/tests/fixtures/config_ejemplo_$k.json"), true);
    return normaliza_config($c);
}
function pagina(array $c, array $ctxExtra = []): string {
    return str_replace(chr(13), '', (string) render_pagina($c, '', ['modo' => 'live', 'assets' => '/assets/', 'slug' => 'prueba', 'foto' => '', 'mapa' => null] + $ctxExtra));
}

$claves = array_keys(ATELIER);

$css = (string) file_get_contents($raiz . '/assets/boda.css');

// 0. El catálogo es coherente consigo mismo
foreach (DISENO_PALANCAS as $k => $p) {
    ok(in_array($p['tipo'], ['color', 'enum'], true), "$k: tipo color o enum");
    if ($p['tipo'] === 'enum') {
        ok(isset($p['opciones'][$p['defecto']]), "$k: el defecto es una de sus opciones");
        foreach ($p['opciones'] as $ok_ => $o) ok((bool) preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', (string) $ok_), "$k/$ok_: clave de opción solo letras y cifras");
        foreach ($p['opciones'] as $ok_ => $o) {
            if (is_array($o[1])) { // letra: [pila, estilo]; la familia tiene que estar alojada y con sus ñ y tildes
                ok((bool) preg_match("/^'[A-Za-z ]+', [A-Za-z, ]+$/", $o[1][0]) && in_array($o[1][1], ['normal', 'italic'], true), "$k/$ok_: pila de letra inocua");
                $fam = preg_match("/^'([^']+)'/", $o[1][0], $fm) ? $fm[1] : '';
                ok(strpos($css, "font-family: '$fam'") !== false, "$k/$ok_: «{$fam}» está declarada con @font-face en boda.css");
            } elseif (isset($p['por_diseno'])) {
                ok($o[1] === null || (bool) preg_match("/^'[A-Za-z ]+', [A-Za-z, ]+$/", (string) $o[1]), "$k/$ok_: pila de letra inocua");
                $fam = preg_match("/^'([^']+)'/", (string) $o[1], $fm) ? $fm[1] : '';
                ok($o[1] === null || strpos($css, "font-family: '$fam'") !== false, "$k/$ok_: «{$fam}» está declarada con @font-face en boda.css");
            } else ok($o[1] === null || (bool) preg_match('/^-?[a-z0-9.]+$/', (string) $o[1]), "$k/$ok_: el valor emitido es literal inocuo");
        }
        foreach ($p['por_diseno'] ?? [] as $dk => $lista) {
            ok(isset(ATELIER[$dk]) && in_array($p['defecto'], $lista, true) && !array_diff($lista, array_keys($p['opciones'])), "$k/$dk: opciones por diseño del catálogo y con la de fábrica");
            ok(count($lista) === 3, "$k/$dk: tres opciones (la de fábrica y dos más)");
        }
    }
}
ok(array_keys(DISENO_PRIMARY_SIGUE) === array_keys(ATELIER) || (array_diff(array_keys(ATELIER), array_keys(DISENO_PRIMARY_SIGUE)) === []), 'DISENO_PRIMARY_SIGUE cubre los seis');

// (f) ningún variable la mueven dos palancas; --primary sigue a UN rol y coincide con la fábrica
$dueno = [];
foreach (DISENO_PALANCAS as $k => $p) foreach ($p['vars'] as $v) { ok(!isset($dueno[$v]), "$v lo mueve una sola palanca ($k)"); $dueno[$v] = $k; }
foreach ($claves as $k) {
    $d = ATELIER[$k];
    $f = diseno_fabrica($k);
    $sigue = DISENO_PRIMARY_SIGUE[$k];
    ok(strtolower($d['colores'][0]) === $f[$sigue], "$k: --primary de fábrica vale lo mismo que su rol «{$sigue}» (si no, mover el rol lo arrastraría sin ser suyo)");
    if (isset($d['nombres_color'])) ok(in_array(strtolower($d['nombres_color']), [strtolower($d['colores'][0]), $f['tinta']], true), "$k: los nombres de fábrica son primary o tinta (siguen a su rol)");
}
// --gold no es palanca: en Herbario (verde) y Masía (terracota) no es oro, y ninguna palanca lo mueve
ok(!isset($dueno['--gold']), '--gold no es de ninguna palanca (no es oro en Herbario ni Masía; no se expone)');

// (d) --at-* de boda.css <-> palancas
preg_match_all('/--at-[a-z0-9-]+/', $css, $m);
$enCss = array_values(array_unique($m[0]));
$enCat = array_filter(array_keys($dueno), fn($v) => strpos($v, '--at-') === 0);
foreach ($enCss as $v) ok(isset($dueno[$v]) || isset(DISENO_FIJOS[$v]), "$v de boda.css tiene palanca o está declarada fija");
foreach ($enCat as $v) ok(in_array($v, $enCss, true), "$v del catálogo existe en boda.css");
foreach (array_keys(DISENO_FIJOS) as $v) ok(!isset($dueno[$v]), "$v fija no está en ninguna palanca");
// el defecto del catálogo (enum con css) coincide con el bloque `body.atelier{ --at-x: … }`
foreach (DISENO_PALANCAS as $k => $p) {
    if (!isset($p['css'])) continue;
    $okd = preg_match('/body\.atelier\{[^}]*' . preg_quote($p['css'], '/') . ':\s*([^;}\s]+)/', $css, $mm) === 1;
    ok($okd && $mm[1] === $p['opciones'][$p['defecto']][1], "$k: el defecto del catálogo es el de boda.css (" . ($mm[1] ?? '?') . ')');
}
// y todo var(--at-*) con valor de fábrica por diseño o común está definido en algún sitio
preg_match_all('/var\((--at-[a-z0-9-]+)/', $css, $usos);
foreach (array_unique($usos[1]) as $v) ok(preg_match('/' . preg_quote($v, '/') . '\s*:/', $css) === 1, "$v se usa y se define");

// (e) render.php no lee DATA_DIR
$render = (string) file_get_contents($raiz . '/app/render.php');
ok(strpos($render, 'DATA_DIR') === false && strpos($render, 'dir_datos') === false, 'render.php no toca DATA_DIR ni dir_datos()');
$dis = (string) file_get_contents($raiz . '/app/disenos.php');
ok(!preg_match('/\b(file_get_contents|fopen|DATA_DIR|dir_datos|include|require)\b/', preg_replace('~//.*~', '', $dis)), 'disenos.php no lee ni escribe disco');

// (a') sin ajustes la salida es la de fábrica, bit a bit, con los tres modos de «sin ajustes»
foreach ($claves as $k) {
    $c = web($k);
    $base = pagina($c);
    ok(pagina($c, ['ajustes_diseno' => []]) === $base, "$k: ajustes vacíos = fábrica");
    ok(pagina($c, ['ajustes_diseno' => ['papel' => ATELIER[$k]['colores'][4], 'nombres_tam' => 'M', 'accesos' => 'filetes', 'nombres_color' => 'diseno', 'ritmo' => 'M', 'ancho_texto' => 'normal', 'arte_escala' => 'e100', 'arte_x' => 'c0', 'arte_y' => 'c0', 'rotulos' => 'versalitas', 'hero_botones' => 'todos', 'letra_titulos' => 'diseno', 'letra_nombres' => 'diseno']]) === $base, "$k: ajustes iguales a la fábrica = fábrica");
    ok(diseno_css($k, []) === '', "$k: sin ajustes no se emite ni un bloque vacío");
    ok(strpos($base, 'atelier-' . $k . '{') === false && strpos($base, 'body.atelier.atelier-') === false, "$k: la fábrica no lleva el bloque de ajustes");
}

// (b) fixture hostil: salida idéntica a la de fábrica, para los seis, vía render y vía las funciones
$hostil = [
    'papel' => "#aabbcc\n", 'tinta' => '</style><script>alert(1)</script>', 'acento' => ';}body{display:none}',
    'nombres_color' => 'url(//x)', 'nombres_tam' => NAN, 'accesos' => INF,
    'peligro' => '</style>', '../estudio/clave' => '#000000', 'sombra' => '#ffffff',
];
foreach ($claves as $k) {
    $c = web($k);
    $base = pagina($c);
    $r = pagina($c, ['ajustes_diseno' => $hostil]);
    ok($r === $base, "$k: fixture hostil = fábrica byte a byte");
    ok(normaliza_diseno($k, $hostil) === [], "$k: normaliza_diseno del hostil queda vacío");
}
foreach (['../estudio/clave', '', 'citricos/../x', "citricos\0", 'CITRICOS', 'masia ', null, 5, [], ['citricos']] as $mala) {
    ok(normaliza_diseno($mala, ['papel' => '#112233']) === [], 'clave de diseño no válida «' . json_encode($mala) . '» → nada');
}
foreach ([null, 'x', 5, 1.5, true, [[]]] as $malo) ok(normaliza_diseno('citricos', $malo) === [], 'ajustes que no son un array → nada');
foreach (['#12345', '#1234567', '123456', '#ggg123', '#ABCDEF ', ' #ABCDEF', "#ABCDEF\n", '#ABCDEF;', 'red', '#ÁBCDEF', ['#112233'], 5] as $x)
    ok(normaliza_diseno('citricos', ['papel' => $x]) === [], 'color no válido «' . json_encode($x) . '» → nada');
ok(normaliza_diseno('citricos', ['papel' => '#ABCDEF']) === ['papel' => '#abcdef'], 'un color válido sale en minúsculas');
ok(normaliza_diseno('citricos', ['nombres_tam' => 'XL']) === ['nombres_tam' => 'XL'], 'una opción válida se conserva');
foreach (['xl', 'XL ', 'XXL', 0, 1.2, '1.2', true, ['XL'], 'constructor'] as $x)
    ok(normaliza_diseno('citricos', ['nombres_tam' => $x]) === [], 'opción no válida «' . json_encode($x) . '» → nada');

// (c) un valor inválido revierte solo su palanca: lo bueno de al lado sigue
$mix = normaliza_diseno('lacre', ['papel' => '#ffeedd', 'acento' => 'no-es-color', 'nombres_tam' => 'L', 'accesos' => 'raro']);
ok($mix === ['papel' => '#ffeedd', 'nombres_tam' => 'L'], 'lo inválido cae, lo válido se queda');
$css1 = diseno_css('lacre', ['papel' => '#ffeedd', 'acento' => 'no-es-color']);
ok($css1 === diseno_css('lacre', ['papel' => '#ffeedd']), 'el acento inválido no cambia la salida de lo válido');

// Emisión con ajustes válidos: lo que sale es del catálogo y tipado
foreach ($claves as $k) {
    $aj = ['papel' => '#F0E8D8', 'tinta' => '#222222', 'acento' => '#aa3355', 'nombres_color' => 'acento', 'nombres_tam' => 'XL', 'accesos' => 'tarjetas'];
    $c = web($k);
    $h = pagina($c, ['ajustes_diseno' => $aj]);
    ok($h !== pagina($c), "$k: con ajustes la página cambia");
    ok(strpos($h, 'data-accesos="tarjetas"') !== false && strpos($h, 'data-accesos="filetes"') === false, "$k: accesos → tarjetas");
    ok(strpos($h, '--at-nombres-escala:1.4') !== false, "$k: nombres XL");
    ok(strpos($h, '--accent:#AA3355') !== false, "$k: el acento llega a --accent");
    ok(strpos($h, '--nombres-color:#AA3355') !== false, "$k: nombres_color=acento");
    ok(strpos($h, '--sage:#F0E8D8') !== false, "$k: el papel llega a --sage");
    $blq = diseno_css($k, $aj);
    ok($blq !== '' && strpos($blq, 'body.atelier.atelier-' . $k . '{') === 0, "$k: bloque con especificidad para ganar a body.atelier-$k");
    ok(!preg_match('/[<>\\\\"\']/', $blq), "$k: el bloque no lleva < > \\ ni comillas");
    // el estilo propio del diseño sigue siendo el único <style> tras la hoja (el bloque va dentro del mismo <style> inline)
    ok(substr_count($h, '<style>') === 1, "$k: un solo <style> inline");
    foreach (diseno_atributos($k, $aj) as $an => $av) ok(preg_match('/^[a-z-]+$/', $an) === 1 && preg_match('/^[a-z]+$/', $av) === 1, "$k: atributo $an inocuo");
}

// Letra: solo las del diseño; lo de otro diseño o inventado no pasa; la emisión lleva la pila del catálogo
ok(normaliza_diseno('ceramica', ['letra_nombres' => 'sacramento']) === [], 'Sacramento no es opción de Cerámica');
ok(normaliza_diseno('lacre', ['letra_nombres' => 'sacramento']) === ['letra_nombres' => 'sacramento'], 'Sacramento sí lo es de Lacre');
ok(normaliza_diseno('lacre', ['letra_titulos' => 'diseno']) === [], 'la opción «como el diseño» no se guarda');
ok(normaliza_diseno('lacre', ['letra_titulos' => "bodoni'}</style>"]) === [], 'una clave de letra con inyección cae');
$h = pagina(web('lacre'), ['ajustes_diseno' => ['letra_titulos' => 'bodoni', 'letra_nombres' => 'sacramento']]);
ok(strpos($h, "--serif:'Bodoni Moda', Georgia, serif") !== false && strpos($h, "--nombres:'Sacramento', cursive;--nombres-estilo:normal") !== false, 'lacre: las letras elegidas llegan a --serif / --nombres');
$h = pagina(web('masia'), ['ajustes_diseno' => ['letra_nombres' => 'playfairit']]);
ok(strpos($h, "--nombres:'Playfair Display', Georgia, serif;--nombres-estilo:italic") !== false, 'masia: letra de nombres en cursiva');

// Botones del hero = uno: solo el de confirmar; donde el hero ya lleva su CTA, ninguno más. Herbario y Masía no tienen la palanca
$uno = ['hero_botones' => 'uno'];
$h = pagina(web('ceramica'), ['ajustes_diseno' => $uno]);
ok(substr_count($h, 'class="tal-btn') === 1 && strpos($h, 'tal-btn tal-rsvp') !== false, 'ceramica: un solo acceso en el hero y es el de confirmar');
ok(substr_count(pagina(web('ceramica')), 'class="tal-btn') === 3, 'ceramica: de fábrica sigue con tres');
$h = pagina(web('citricos'), ['ajustes_diseno' => $uno]);
ok(preg_match('~<nav class="cit-nav"[^>]*>(.*?)</nav>~s', $h, $nm) === 1 && substr_count($nm[1], '<a ') === 1 && strpos($nm[1], 'Confirmar') !== false, 'citricos: un solo acceso, Confirmar');
ok(strpos(pagina(web('lacre'), ['ajustes_diseno' => $uno]), 'class="lac-nav"') === false && strpos(pagina(web('lacre'), ['ajustes_diseno' => $uno]), 'lac-btn') !== false, 'lacre: sin la fila de accesos, con su CTA');
ok(strpos(pagina(web('atardecer'), ['ajustes_diseno' => $uno]), 'class="atd-nav"') === false && strpos(pagina(web('atardecer'), ['ajustes_diseno' => $uno]), 'atd-btn') !== false, 'atardecer: sin la fila de accesos, con su CTA');
ok(normaliza_diseno('masia', $uno) === [] && normaliza_diseno('herbario', $uno) === [], 'masia y herbario no tienen la palanca (ya llevan solo uno)');
// Atributos: rotulos y arte solo salen si se mueven; los valores son los del catálogo
ok(diseno_atributos('lacre', []) === ['data-accesos' => 'filetes'], 'sin ajustes el único atributo es el de fábrica');
ok(diseno_atributos('lacre', ['rotulos' => 'normal', 'arte_escala' => 'e80']) === ['data-accesos' => 'filetes', 'data-rotulos' => 'normal', 'data-arte' => 'si'], 'rotulos y arte activan sus atributos');
$bl = diseno_css('lacre', ['arte_escala' => 'e80', 'arte_x' => 'm16', 'ritmo' => 'L', 'ancho_texto' => 'ancho']);
ok($bl === 'body.atelier.atelier-lacre{--at-ritmo:1.4;--at-ancho-texto:900px;--at-arte-escala:0.8;--at-arte-x:-16px;}', 'el bloque lleva solo lo movido, en el orden del catálogo (' . $bl . ')');

// Color: contrastes y mezclas conocidas
ok(abs(dis_contraste('#000000', '#ffffff') - 21.0) < 0.01, 'contraste negro/blanco = 21');
ok(dis_mezcla('#000000', '#ffffff', 0.5) === '#808080', 'mezcla 50 % = #808080');
ok(dis_hex("#ABCDEF\n") === null && dis_hex('#ABCDEF') === '#abcdef', 'dis_hex estricto');
// Las recetas de derivados reproducen el criterio: acento hover más oscuro, papel-2 a medio camino
$pal = diseno_paleta('citricos', ['acento' => '#336699']);
ok(dis_luminancia($pal['colores'][2]) < dis_luminancia($pal['colores'][1]), 'el hover del acento es más oscuro');
ok(diseno_paleta('citricos', [])['body'] === [] && diseno_paleta('citricos', []) === array_merge(ATELIER['citricos'], ['body' => []]), 'sin ajustes la paleta es ATELIER tal cual');

// El config de la pareja no puede traer ajustes: normaliza_config los descarta (la clave no existe en el esquema)
$c0 = web('citricos');
$in = json_decode((string) file_get_contents($raiz . '/tests/fixtures/config_ejemplo_citricos.json'), true);
$in['ajustes_diseno'] = ['papel' => '#112233']; $in['diseno'] = ['papel' => '#112233']; $in['ajustes'] = ['papel' => '#112233'];
$c1 = normaliza_config($in);
ok(!array_key_exists('ajustes_diseno', $c1) && !array_key_exists('diseno', $c1) && !array_key_exists('ajustes', $c1), 'normaliza_config descarta ajustes del config de la pareja');
ok(pagina($c1) === pagina($c0), 'una web con «ajustes» en su config sale igual que sin ellos');

// Borrar el temporal
$borra = function (string $d) use (&$borra): void {
    foreach (scandir($d) ?: [] as $f) if ($f !== '.' && $f !== '..') is_dir("$d/$f") ? $borra("$d/$f") : unlink("$d/$f");
    rmdir($d);
};
$borra($tmp);

echo $fallos ? "ROJO: $fallos de $n\n" : "VERDE: $n/$n\n";
exit($fallos ? 1 : 0);
