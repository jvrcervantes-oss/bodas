<?php
// La «Mesa del atelier» de la landing (owner, 10-oct-2026) enseña capturas de cada diseño (tarjeta-<k>.webp y
// movil-<k>.webp, de tests/tarjetas_atelier.js) y, al lado, su paleta y su letra de nombres leídas en vivo de ATELIER.
// Las capturas son una copia: si un diseño cambia y no se regraban, la landing dice una cosa en la imagen y otra al lado,
// sin un solo error (revisor, 10-oct-2026). Este test compara lo de ahora con lo que el manifiesto
// (tests/fixtures/tarjetas_atelier.json) dice que había al grabar:
//   · cada diseño de ATELIER tiene sus dos imágenes, del tamaño anotado;
//   · su ATELIER (colores y fuentes) no ha cambiado desde la captura;
//   · su línea base de estilos (tests/fixtures/estilos/<k>.txt.gz, que se regraba cuando su aspecto cambia a propósito)
//     es la misma;
//   · la portada pintaba los nombres con la letra que ATELIER dice;
//   · landing.css declara un @font-face para esa letra y ese estilo (sin él, «Lucía & Marcos» cae a cursive o Georgia).
// Si falla porque un diseño cambió: NODE_PATH=<carpeta con playwright-core> node tests/tarjetas_atelier.js (regraba las
// imágenes y el manifiesto). Uso: php tests/landing_atelier_test.php  (sale con 1 si algo falla). Sin red.

declare(strict_types=1);

$raiz = dirname(__DIR__);
require_once $raiz . '/app/schema.php';

$fallos = 0;
$n = 0;
function ok(bool $c, string $que): void { global $fallos, $n; $n++; if (!$c) { $fallos++; echo "FALLA: $que\n"; } }
const REGRABA = ' → regraba con: NODE_PATH=<carpeta con playwright-core> node tests/tarjetas_atelier.js';

$primera = fn(string $familia): string => trim(explode(',', $familia)[0], " '\"");

// Familia y estilo de cada @font-face de una hoja
function fuentes_declaradas(string $css): array {
    preg_match_all('~@font-face\s*\{([^}]*)\}~', $css, $m);
    $o = [];
    foreach ($m[1] as $cuerpo) {
        if (!preg_match('~font-family:\s*[\'"]?([^;\'"]+)~', $cuerpo, $f)) continue;
        $estilo = preg_match('~font-style:\s*(\w+)~', $cuerpo, $e) ? $e[1] : 'normal';
        $o[trim($f[1]) . '|' . $estilo] = true;
    }
    return $o;
}

$manifiesto = json_decode((string) @file_get_contents(__DIR__ . '/fixtures/tarjetas_atelier.json'), true);
ok(is_array($manifiesto) && isset($manifiesto['disenos']), 'existe tests/fixtures/tarjetas_atelier.json' . REGRABA);
$md = $manifiesto['disenos'] ?? [];
$km = array_keys($md); $ka = array_keys(ATELIER); sort($km); sort($ka);
ok($km === $ka, 'el manifiesto cubre exactamente los diseños de ATELIER (' . implode(', ', $ka) . ')' . REGRABA);

$css = fuentes_declaradas((string) file_get_contents($raiz . '/assets/landing.css'));
foreach (ATELIER as $k => $a) {
    $m = $md[$k] ?? [];
    foreach (['tarjeta', 'movil'] as $img) {
        $f = $raiz . "/assets/img/atelier/$img-$k.webp";
        $t = is_file($f) ? @getimagesize($f) : false;
        ok($t !== false, "«{$k}»: existe assets/img/atelier/$img-$k.webp" . REGRABA);
        ok($t !== false && [$t[0], $t[1]] === ($m[$img] ?? null), "«{$k}»: $img-$k.webp mide lo que anota el manifiesto" . REGRABA);
    }
    ok(($m['atelier'] ?? null) === ['colores' => $a['colores'], 'fuentes' => $a['fuentes']], "«{$k}»: su ATELIER no ha cambiado desde la captura" . REGRABA);
    $base = @file_get_contents(__DIR__ . "/fixtures/estilos/$k.txt.gz");
    ok($base !== false && hash('sha256', (string) gzdecode($base)) === ($m['linea_base'] ?? ''), "«{$k}»: su línea base de estilos es la de la captura" . REGRABA);
    $fam = $primera($a['fuentes'][2]);
    ok(($m['nombres'] ?? null) === ['familia' => $fam, 'estilo' => $a['fuentes'][3]], "«{$k}»: la portada pintaba los nombres en {$fam} {$a['fuentes'][3]}" . REGRABA);
    ok(isset($css[$fam . '|' . $a['fuentes'][3]]), "«{$k}»: assets/landing.css declara @font-face de '{$fam}' {$a['fuentes'][3]} (la muestra «Lucía & Marcos»)");
}

// Control del propio test: el lector de @font-face ve familia y estilo, y el estilo por defecto es normal.
$sonda = fuentes_declaradas("@font-face { font-family: 'Uno Dos'; font-style: italic; src: url(x) } @font-face{font-family:Tres;src:url(y)}");
ok(isset($sonda['Uno Dos|italic'], $sonda['Tres|normal']) && count($sonda) === 2, 'el test lee familia y estilo de los @font-face');

echo ($fallos ? "ROJO: $fallos de $n\n" : "VERDE: $n/$n\n");
exit($fallos ? 1 : 0);
