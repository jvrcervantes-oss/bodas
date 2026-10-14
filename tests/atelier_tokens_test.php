<?php
// Paso 0 del editor de diseños (encargo 20261009_bodas_editor_disenos_atelier, S2): en las reglas de cada diseño
// Atelier de assets/boda.css ningún hex ni font-family repite un valor de su ATELIER[<clave>] (app/schema.php).
// Esos valores llegan como variables por tema_css()/fuente_css() de render.php; un literal que los repite no sigue a
// la paleta ni a las letras del diseño, y una palanca del editor no lo movería. Se escribe var(--…) en su lugar.
// Si un valor coincide pero no es ese rol, se usa la variable igual y se anota: las sobrecargas de S2 (lino de Lacre
// en --on-dark-soft, papel de tarjeta en --on-dark…) están en la bitácora del encargo, para S2b/S3.
// S2b las separó en variables con su propio rol, --at-* (los defectos de las palancas de S3). Su valor vive en UN
// bloque de defectos por diseño, `body.atelier-<clave>{ --at-x: …; }`, que solo define --at-* (la opción B decidida en
// la revisión previa: el render emitirá solo lo que cambie). Ese bloque es el único sitio donde un hex puede coincidir
// con un token de ATELIER, porque es una definición, no un uso. A cambio: cada var(--at-x) de un diseño está definida
// en su bloque y cada --at-x definida se usa.
// Uso: php tests/atelier_tokens_test.php  (sale con 1 si algo falla). Sin red.

declare(strict_types=1);

$raiz = dirname(__DIR__);
if (!defined('WEB_DIR')) define('WEB_DIR', $raiz);
require_once $raiz . '/app/schema.php';

$fallos = 0;
$n = 0;
function ok(bool $c, string $que): void { global $fallos, $n; $n++; if (!$c) { $fallos++; echo "FALLA: $que\n"; } }

// Qué reglas son de cada diseño: las que nombran su clase de body o de entrada, o una pieza que solo pinta ese diseño
// (render.php: arte_atelier, atelier_tras_texto, entrada_atelier, inicio_citricos). Prefijo con «-» = empieza por.
// S2b añadió mas- (el lugar y la CTA del hero de Masía) y tal-rsvp (cubierto por tal-). La piel «Terraza» de Cítricos
// (14-oct-2026) se fue con el sello circular de Limonar (sello-circular, sello-txt, sello-fondo): lo suyo es todo cit-.
const DUENO_ATELIER = [
    'citricos' => ['atelier-citricos', 'entrada-citricos', 'en-sello-citricos', 'cit-', 'esq', 'en-petalos', 'en-esq', 'en-sello-disco'],
    'herbario' => ['atelier-herbario', 'entrada-herbario', 'en-sello-herbario', 'herb-', 'ramita', 'banda-herbario', 'arte-herbario', 'en-arte-banda'],
    'ceramica' => ['atelier-ceramica', 'entrada-ceramica', 'en-sello-ceramica', 'tal-'],
    'lacre' => ['atelier-lacre', 'entrada-lacre', 'en-sello-lacre', 'lac-', 'sello-bronce', 'en-poema'],
    'masia' => ['atelier-masia', 'entrada-masia', 'en-sello-masia', 'arte-masia', 'mas-', 'en-dibujo', 'en-lapiz', 'en-tinta'],
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

/** Bloque de defectos del diseño: selector exacto body.atelier-<clave> y solo declaraciones --at-*. */
function es_bloque_defectos(string $sel, string $decl, string $k): bool {
    if (trim($sel) !== 'body.atelier-' . $k) return false;
    $partes = array_filter(array_map('trim', explode(';', $decl)), 'strlen');
    foreach ($partes as $p) if (!preg_match('~^--at-[a-z0-9-]+\s*:~', $p)) return false;
    return (bool) $partes;
}

// S3: los defectos de las palancas comunes (tamaño de nombres…) viven en UN bloque `body.atelier{ --at-x: … }` para los seis;
// cuentan como definidos en todos y no se exige que cada diseño los use (tests/disenos_test.php (d) comprueba su defecto).
$comunes = [];
foreach ($reglas as [$sel, $decl]) if (trim($sel) === 'body.atelier') { preg_match_all('~(--at-[a-z0-9-]+)\s*:~', $decl, $cm); foreach ($cm[1] as $v) $comunes[$v] = true; }

$vistas = array_fill_keys(array_keys(ATELIER), 0);
foreach (ATELIER as $k => $a) {
    $hex = array_map('strtolower', array_merge($a['colores'], isset($a['nombres_color']) ? [$a['nombres_color'], $a['tinta']] : []));
    $fuentes = array_slice($a['fuentes'], 0, 3);
    $malos = [];
    $definidas = [];
    $usadas = [];
    foreach ($reglas as [$sel, $decl]) {
        if (!in_array($k, disenos_de($sel), true)) continue;
        $vistas[$k]++;
        if (es_bloque_defectos($sel, $decl, $k)) {
            preg_match_all('~(--at-[a-z0-9-]+)\s*:~', $decl, $dm);
            foreach ($dm[1] as $v) $definidas[$v] = true;
            continue;
        }
        preg_match_all('~var\(\s*(--at-[a-z0-9-]+)~', $decl, $um);
        foreach ($um[1] as $v) $usadas[$v] = true;
        if (preg_match('~--at-[a-z0-9-]+\s*:~', $decl)) $malos[] = 'una --at-* definida fuera del bloque de defectos en «' . substr(preg_replace('~\s+~', ' ', $sel), 0, 60) . '»';
        preg_match_all('~#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?\b~', $decl, $hm);
        foreach ($hm[0] as $h) {
            $h6 = strlen($h) === 4 ? '#' . $h[1] . $h[1] . $h[2] . $h[2] . $h[3] . $h[3] : $h;
            if (in_array(strtolower($h6), $hex, true)) $malos[] = "$h en «" . substr(preg_replace('~\s+~', ' ', $sel), 0, 60) . '»';
        }
        preg_match_all('~font-family\s*:\s*([^;}]+)~', $decl, $fm);
        foreach ($fm[1] as $f) if (in_array(trim($f), $fuentes, true)) $malos[] = trim($f) . ' en «' . substr(preg_replace('~\s+~', ' ', $sel), 0, 60) . '»';
    }
    ok($vistas[$k] > 0, "hay reglas de «{$k}» en boda.css");
    $sinDefinir = array_keys(array_diff_key($usadas, $definidas, $comunes));
    $sinUso = array_keys(array_diff_key($definidas, $usadas));
    ok(!$sinDefinir, "«{$k}»: cada var(--at-*) está en su bloque de defectos (" . implode(', ', $sinDefinir) . ')');
    ok(!$sinUso, "«{$k}»: cada --at-* del bloque de defectos se usa (" . implode(', ', $sinUso) . ')');
    ok(!$malos, "«{$k}»: ningún literal repite un token de ATELIER (" . count($malos) . ': ' . implode(' | ', array_slice($malos, 0, 4)) . ')');
}

// S2b (ley 1): ningún :hover de los Atelier levanta la pieza ni le da sombra. Se lee del CSS porque el script de
// medida (tests/fabrica_atelier.js) mide con «reducir movimiento», y ahí .rv{transform:none !important} tapa un
// translateY de las tarjetas: el navegador no lo vería.
$hoverMalos = [];
$sondaHover = reglas_css('.atelier .q-card:hover, .atelier .event-card:hover{ transform: translateY(-3px); box-shadow: var(--shadow-lg); }');
foreach (array_merge($sondaHover, $reglas) as [$sel, $decl]) {
    if (strpos($sel, ':hover') === false) continue;
    if (!disenos_de($sel) && strpos($sel, '.atelier') === false && strpos($sel, '[data-accesos') === false) continue;
    if (preg_match('~translate(Y|3d)?\s*\(|(^|;)\s*translate\s*:~i', $decl) || preg_match('~box-shadow\s*:\s*+(?!none)~i', $decl)) {
        $hoverMalos[] = substr(preg_replace('~\s+~', ' ', $sel), 0, 70);
    }
}
ok(($hoverMalos[0] ?? '') === '.atelier .q-card:hover, .atelier .event-card:hover', 'el test ve el hover que levantaba las tarjetas (la regla de antes de S2b)');
array_shift($hoverMalos);
ok(!$hoverMalos, 'ningún :hover Atelier con translate ni sombra (' . implode(' | ', array_slice($hoverMalos, 0, 4)) . ')');

// S2b (ley 1): ninguna regla de los Atelier pone sombra, ni box-shadow ni filter: drop-shadow. Incluye la entrada (el
// sobre: .entrada-*, .en-*), que el script de medida no puede ver porque mide con «reducir movimiento».
$sombras = [];
$sondaSombra = reglas_css('.en-x{ box-shadow: 0 1px 2px #000; } .atelier .y{ background: none; box-shadow: none; padding: 0; }');
foreach (array_merge($sondaSombra, $reglas) as [$sel, $decl]) {
    $atelier = disenos_de($sel) || strpos($sel, '.atelier') !== false || strpos($sel, '[data-accesos') !== false || preg_match('~\.(en-|entrada)~', $sel);
    if (!$atelier) continue;
    if (preg_match('~box-shadow\s*:\s*+(?!none\s*(;|$))~i', $decl) || stripos($decl, 'drop-shadow(') !== false) $sombras[] = substr(preg_replace('~\s+~', ' ', $sel), 0, 70);
}
ok(($sombras[0] ?? '') === '.en-x' && ($sombras[1] ?? '') !== '.atelier .y', 'el test ve una sombra de la entrada y no confunde box-shadow: none con una sombra');
array_shift($sombras);
ok(!$sombras, 'ninguna regla Atelier ni de la entrada con sombra (' . count($sombras) . ': ' . implode(' | ', array_slice($sombras, 0, 4)) . ')');

// S2b: los tokens de cada diseño pasan el gate que tendrá «Publicar» (S5): texto/papel y blanco/acento >= 4,5,
// acento/papel >= 3. Antes solo lo veía el navegador (tests/fabrica_atelier.js, fuera del gate); varios están ajustados
// al mínimo (Atardecer, Cítricos) y una edición de ATELIER los podía bajar sin que nada saltara.
function lum(string $hex): float {
    $c = array_map(fn($x) => hexdec($x) / 255, str_split(ltrim($hex, '#'), 2));
    $c = array_map(fn($v) => $v <= 0.04045 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4, $c);
    return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}
function contraste(string $a, string $b): float { $x = lum($a); $y = lum($b); return (max($x, $y) + 0.05) / (min($x, $y) + 0.05); }
ok(round(contraste('#FFFFFF', '#777777'), 2) === 4.48, 'la fórmula de contraste es la de WCAG (#777 sobre blanco = 4,48)');
foreach (ATELIER as $k => $a) {
    [$primary, $accent, , $secondary, $papel] = $a['colores'];
    $tinta = $a['tinta'] ?? $primary;
    foreach (['tinta' => $tinta, 'títulos' => $primary, 'rótulos' => $secondary, 'nombres' => $a['nombres_color'] ?? $primary] as $rol => $c) {
        ok(contraste($c, $papel) >= 4.5, "«{$k}»: {$rol} sobre el papel >= 4,5 (" . round(contraste($c, $papel), 2) . ')');
    }
    ok(contraste('#FFFFFF', $accent) >= 4.5, "«{$k}»: blanco sobre el acento >= 4,5 (" . round(contraste('#FFFFFF', $accent), 2) . ')');
    ok(contraste($accent, $papel) >= 3, "«{$k}»: acento sobre el papel >= 3 (" . round(contraste($accent, $papel), 2) . ')');
}

// Control del propio test: el bloque de defectos se reconoce solo si es exacto y solo define --at-*.
ok(es_bloque_defectos('body.atelier-lacre', ' --at-lino: #E5DED4; ', 'lacre') && !es_bloque_defectos('body.atelier-lacre', ' --at-lino: #E5DED4; color: #2D2926; ', 'lacre')
    && !es_bloque_defectos('body.atelier-lacre .x', ' --at-lino: #E5DED4; ', 'lacre'), 'el bloque de defectos solo admite --at-* y su selector exacto');
// Control del propio test: un literal con el valor de un token, en una regla del diseño, se ve.
$sonda = reglas_css('body.atelier-lacre .x{ color: ' . ATELIER['lacre']['colores'][1] . '; } .tal-y{ font-family: ' . ATELIER['ceramica']['fuentes'][0] . '; }');
ok(disenos_de($sonda[0][0]) === ['lacre'] && disenos_de($sonda[1][0]) === ['ceramica'], 'el test atribuye las reglas de prueba a su diseño');

echo ($fallos ? "ROJO: $fallos de $n\n" : "VERDE: $n/$n\n");
exit($fallos ? 1 : 0);
