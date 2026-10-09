<?php
// Paso 0 del editor de diseños (encargo 20261009_bodas_editor_disenos_atelier, S2): en las reglas de cada diseño
// Atelier de assets/boda.css ningún hex ni font-family repite un valor de su ATELIER[<clave>] (app/schema.php).
// Esos valores llegan como variables por tema_css()/fuente_css() de render.php; un literal que los repite no sigue a
// la paleta ni a las letras del diseño, y una palanca del editor no lo movería. Se escribe var(--…) en su lugar.
// Si un valor coincide pero no es ese rol, se usa la variable igual y se anota (las sobrecargas, en la bitácora).
// Uso: php tests/atelier_tokens_test.php  (sale con 1 si algo falla). Sin red.

declare(strict_types=1);

$raiz = dirname(__DIR__);
if (!defined('WEB_DIR')) define('WEB_DIR', $raiz);
require_once $raiz . '/app/schema.php';

$fallos = 0;
$n = 0;
function ok(bool $c, string $que): void { global $fallos, $n; $n++; if (!$c) { $fallos++; echo "FALLA: $que\n"; } }

// Qué reglas son de cada diseño: las que nombran su clase de body o de entrada, o una pieza que solo pinta ese diseño
// (render.php: arte_atelier, atelier_tras_texto, entrada_atelier, sello_circular). Prefijo con «-» = empieza por.
const DUENO_ATELIER = [
    'citricos' => ['atelier-citricos', 'entrada-citricos', 'en-sello-citricos', 'cit-', 'esq', 'sello-circular', 'sello-txt', 'en-petalos', 'en-esq', 'en-sello-disco'],
    'herbario' => ['atelier-herbario', 'entrada-herbario', 'en-sello-herbario', 'herb-', 'ramita', 'banda-herbario', 'arte-herbario', 'en-arte-banda'],
    'ceramica' => ['atelier-ceramica', 'entrada-ceramica', 'en-sello-ceramica', 'tal-'],
    'lacre' => ['atelier-lacre', 'entrada-lacre', 'en-sello-lacre', 'lac-', 'sello-bronce', 'en-poema'],
    'masia' => ['atelier-masia', 'entrada-masia', 'en-sello-masia', 'arte-masia', 'en-dibujo', 'en-lapiz', 'en-tinta'],
    'atardecer' => ['atelier-atardecer', 'entrada-atardecer', 'en-sello-atardecer', 'atd-', 'en-aro', 'en-sol'],
];
$kd = array_keys(DUENO_ATELIER); $ka = array_keys(ATELIER); sort($kd); sort($ka);
ok($kd === $ka, 'la tabla de dueños cubre exactamente los diseños de ATELIER');

function disenos_de(string $sel): array {
    preg_match_all('~\.([A-Za-z0-9_-]+)~', $sel, $m);
    $res = [];
    foreach (DUENO_ATELIER as $k => $pref) foreach ($m[1] as $c) foreach ($pref as $p) {
        if (substr($p, -1) === '-' ? strpos($c, $p) === 0 : $c === $p) $res[$k] = true;
    }
    return array_keys($res);
}

/** [selector, declaraciones] de cada regla, entrando en @media/@supports y saltando @font-face/@keyframes. */
function reglas_css(string $t): array {
    $o = [];
    $i = 0;
    $len = strlen($t);
    while (($j = strpos($t, '{', $i)) !== false) {
        $sel = trim(substr($t, $i, $j - $i));
        $prof = 1;
        $k = $j + 1;
        while ($prof > 0 && $k < $len) { if ($t[$k] === '{') $prof++; elseif ($t[$k] === '}') $prof--; $k++; }
        $cuerpo = substr($t, $j + 1, $k - $j - 2);
        if (strpos($sel, '@media') === 0 || strpos($sel, '@supports') === 0) array_push($o, ...reglas_css($cuerpo));
        elseif ($sel !== '' && $sel[0] !== '@') $o[] = [$sel, $cuerpo];
        $i = $k;
    }
    return $o;
}

$css = (string) preg_replace('~/\*.*?\*/~s', '', (string) file_get_contents($raiz . '/assets/boda.css'));
$reglas = reglas_css($css);
ok(count($reglas) > 500, 'boda.css se lee y se trocea en reglas (' . count($reglas) . ')');

$vistas = array_fill_keys(array_keys(ATELIER), 0);
foreach (ATELIER as $k => $a) {
    $hex = array_map('strtolower', array_merge($a['colores'], isset($a['nombres_color']) ? [$a['nombres_color'], $a['tinta']] : []));
    $fuentes = array_slice($a['fuentes'], 0, 3);
    $malos = [];
    foreach ($reglas as [$sel, $decl]) {
        if (!in_array($k, disenos_de($sel), true)) continue;
        $vistas[$k]++;
        preg_match_all('~#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?\b~', $decl, $hm);
        foreach ($hm[0] as $h) {
            $h6 = strlen($h) === 4 ? '#' . $h[1] . $h[1] . $h[2] . $h[2] . $h[3] . $h[3] : $h;
            if (in_array(strtolower($h6), $hex, true)) $malos[] = "$h en «" . substr(preg_replace('~\s+~', ' ', $sel), 0, 60) . '»';
        }
        preg_match_all('~font-family\s*:\s*([^;}]+)~', $decl, $fm);
        foreach ($fm[1] as $f) if (in_array(trim($f), $fuentes, true)) $malos[] = trim($f) . ' en «' . substr(preg_replace('~\s+~', ' ', $sel), 0, 60) . '»';
    }
    ok($vistas[$k] > 0, "hay reglas de «{$k}» en boda.css");
    ok(!$malos, "«{$k}»: ningún literal repite un token de ATELIER (" . count($malos) . ': ' . implode(' | ', array_slice($malos, 0, 4)) . ')');
}

// Control del propio test: un literal con el valor de un token, en una regla del diseño, se ve.
$sonda = reglas_css('body.atelier-lacre .x{ color: ' . ATELIER['lacre']['colores'][1] . '; } .tal-y{ font-family: ' . ATELIER['ceramica']['fuentes'][0] . '; }');
ok(disenos_de($sonda[0][0]) === ['lacre'] && disenos_de($sonda[1][0]) === ['ceramica'], 'el test atribuye las reglas de prueba a su diseño');

echo ($fallos ? "ROJO: $fallos de $n\n" : "VERDE: $n/$n\n");
exit($fallos ? 1 : 0);
