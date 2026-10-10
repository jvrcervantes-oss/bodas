<?php
// Palancas de los diseños Atelier (encargo 20261009_bodas_editor_disenos_atelier, S3).
// El estudio ajusta un diseño con un juego CERRADO de palancas; este fichero es el catálogo, la normalización y la
// emisión. No lee ni escribe nada del disco (el almacén es S4): recibe los ajustes ya cargados y devuelve valores.
//
// Reglas que lo hacen seguro (revisión previa #241, Seguridad):
//  · La salida recorre el CATÁLOGO, nunca el JSON de ajustes: lo que no está en el catálogo no se mira.
//  · Cada valor se reemite tipado: un color es #rrggbb en minúsculas (preg con \z); una opción es una CLAVE del
//    catálogo y lo que sale es el valor del catálogo, nunca el texto recibido. No hay números libres.
//  · Un valor inválido revierte solo su palanca (la fábrica); un valor igual a la fábrica no se guarda ni se emite.
//  · Sin ajustes no se emite NADA (ni un bloque vacío): la salida es idéntica byte a byte a la de fábrica.
//  · El CSS compuesto que contenga `<`, `>` o `\` se descarta entero.
//
// Dueño de cada valor: la FÁBRICA de colores es ATELIER (schema.php); la de tamaños y composición, el bloque
// `body.atelier{ --at-*: … }` de assets/boda.css. Aquí no se copia ningún defecto: test tests/disenos_test.php (d).

declare(strict_types=1);

// Palancas. tipo: color | enum. grupo/etiqueta: texto del editor (S5). solo: diseños a los que aplica (null = los seis).
// enum: opciones clave => [etiqueta, valor]; defecto = la clave de fábrica. Si lleva 'css', el valor va a esa variable
// (`--at-*` de boda.css); si lleva 'attr', al atributo del <body>; los dos únicos sitios fuera de la paleta.
// vars: lo que la palanca MUEVE (para que el test vea que ninguna variable tiene dos dueños).
const DISENO_PALANCAS = [
    'papel' => ['tipo' => 'color', 'grupo' => 'Color', 'etiqueta' => 'Papel', 'solo' => null,
        'vars' => ['--sage', '--sage-rgb', '--sage-2', '--sage-2-rgb', '--sage-2-hover', '--on-dark', '--on-dark-rgb', '--on-dark-soft',
            '--at-papel-tarjeta', '--at-lino', '--at-filete-azul', '--papel-2']],
    'tinta' => ['tipo' => 'color', 'grupo' => 'Color', 'etiqueta' => 'Tinta', 'solo' => null,
        'vars' => ['--tinta', '--secondary', '--ink-soft']],
    'acento' => ['tipo' => 'color', 'grupo' => 'Color', 'etiqueta' => 'Acento', 'solo' => null,
        'vars' => ['--accent', '--accent-rgb', '--accent-hover']],
    'nombres_color' => ['tipo' => 'enum', 'grupo' => 'Color', 'etiqueta' => 'Color de los nombres', 'solo' => null, 'defecto' => 'diseno',
        'opciones' => ['diseno' => ['Como el diseño', null], 'tinta' => ['Tinta', 'tinta'], 'acento' => ['Acento', 'acento']],
        'vars' => ['--nombres-color']],
    'nombres_tam' => ['tipo' => 'enum', 'grupo' => 'Letra', 'etiqueta' => 'Tamaño de los nombres', 'solo' => null, 'defecto' => 'M',
        'opciones' => ['S' => ['Pequeño', '0.85'], 'M' => ['Medio', '1'], 'L' => ['Grande', '1.2'], 'XL' => ['Muy grande', '1.4']],
        'css' => '--at-nombres-escala', 'vars' => ['--at-nombres-escala']],
    'accesos' => ['tipo' => 'enum', 'grupo' => 'Composición', 'etiqueta' => 'Accesos', 'solo' => null, 'defecto' => 'filetes',
        'opciones' => ['filetes' => ['Filetes', 'filetes'], 'tarjetas' => ['Tarjetas', 'tarjetas']],
        'attr' => 'data-accesos', 'vars' => []],
];

// Variables `--at-*` que NO mueve ninguna palanca, con el porqué. (tests/disenos_test.php (d): toda `--at-*` de boda.css
// o la mueve una palanca o está aquí; ninguna está en las dos.)
const DISENO_FIJOS = [
    '--at-terracota' => 'la terracota del corazón del calendario de Limonar: un segundo color a propósito, no sigue al acento',
];

// Qué rol arrastra al `--primary` (títulos y enlaces) en cada diseño: en cinco coincide con el acento (un solo
// acento, ley 2) y en Lacre y Masía con la tinta. Lo comprueba el test contra los valores de fábrica.
const DISENO_PRIMARY_SIGUE = ['citricos' => 'acento', 'ceramica' => 'acento', 'herbario' => 'acento', 'lacre' => 'tinta', 'masia' => 'tinta', 'atardecer' => 'acento'];

// ---------- Color: hex, mezcla y contraste (los usará también el gate de «Publicar», S5) ----------

/** '#RRGGBB' (cualquier caja) → '#rrggbb', o null si no es exactamente eso (con \z: «#aabbcc\n» no pasa). */
function dis_hex($v): ?string {
    return is_string($v) && preg_match('/^#[0-9a-fA-F]{6}\z/', $v) ? strtolower($v) : null;
}

/** @return array{0:int,1:int,2:int} */
function dis_rgb(string $hex): array {
    return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
}

/** a·(1−t) + b·t en sRGB, redondeado. */
function dis_mezcla(string $a, string $b, float $t): string {
    $x = dis_rgb($a);
    $y = dis_rgb($b);
    $o = '#';
    for ($i = 0; $i < 3; $i++) $o .= sprintf('%02x', (int) max(0, min(255, round($x[$i] * (1 - $t) + $y[$i] * $t))));
    return $o;
}

function dis_luminancia(string $hex): float {
    $l = 0.0;
    foreach (dis_rgb($hex) as $i => $c) {
        $s = $c / 255;
        $l += [0.2126, 0.7152, 0.0722][$i] * ($s <= 0.03928 ? $s / 12.92 : (($s + 0.055) / 1.055) ** 2.4);
    }
    return $l;
}

/** Contraste WCAG entre dos #rrggbb (1 a 21). */
function dis_contraste(string $a, string $b): float {
    $x = dis_luminancia($a);
    $y = dis_luminancia($b);
    return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
}

// ---------- Normalización ----------

/** ¿La palanca $k aplica al diseño $clave? */
function dis_aplica(array $p, string $clave): bool {
    return $p['solo'] === null || in_array($clave, $p['solo'], true);
}

/**
 * Ajustes de un diseño, normalizados: SOLO palancas del catálogo, con su valor canónico, y sin las que valen lo
 * mismo que la fábrica. Cualquier cosa rara (clave de diseño que no es de ATELIER —incluida «../estudio/clave»—,
 * ajustes que no son un array, valor de otro tipo, número, NaN) se queda en nada.
 * @param mixed $clave @param mixed $in @return array<string,string>
 */
function normaliza_diseno($clave, $in): array {
    if (!is_string($clave) || !isset(ATELIER[$clave]) || !is_array($in)) return [];
    $fab = diseno_fabrica($clave);
    $o = [];
    foreach (DISENO_PALANCAS as $k => $p) {
        if (!dis_aplica($p, $clave) || !array_key_exists($k, $in)) continue;
        $v = $in[$k];
        if ($p['tipo'] === 'color') $v = dis_hex($v);
        else $v = (is_string($v) && isset($p['opciones'][$v])) ? $v : null;
        if ($v === null || $v === ($fab[$k] ?? null)) continue;
        $o[$k] = $v;
    }
    return $o;
}

/** Valores de fábrica de las palancas de un diseño (lo que hay sin ajustes). */
function diseno_fabrica(string $clave): array {
    $d = ATELIER[$clave];
    $o = ['papel' => strtolower($d['colores'][4]), 'tinta' => strtolower($d['tinta'] ?? $d['colores'][0]), 'acento' => strtolower($d['colores'][1])];
    foreach (DISENO_PALANCAS as $k => $p) if ($p['tipo'] === 'enum') $o[$k] = $p['defecto'];
    return $o;
}

// ---------- Emisión ----------

/**
 * La paleta de un diseño con sus ajustes: misma forma que ATELIER[$clave] (colores, nombres_color, tinta) más
 * 'body', las variables que boda.css fija a nivel de <body> y que hay que mover a la vez (--papel-2, --at-*…).
 * Sin ajustes devuelve ATELIER[$clave] tal cual y 'body' vacío. Los colores derivados salen de recetas fijas.
 * @return array<string,mixed>
 */
function diseno_paleta(string $clave, array $aj): array {
    $d = ATELIER[$clave];
    $d['body'] = [];
    $aj = normaliza_diseno($clave, $aj);
    if (!$aj) return $d;
    $fab = diseno_fabrica($clave);
    $P = $aj['papel'] ?? $fab['papel'];
    $T = $aj['tinta'] ?? $fab['tinta'];
    $A = $aj['acento'] ?? $fab['acento'];
    $col = array_map('strtolower', $d['colores']);
    $sigue = DISENO_PRIMARY_SIGUE[$clave];
    $primary0 = $col[0];
    $nombres0 = isset($d['nombres_color']) ? strtolower($d['nombres_color']) : null;

    if (isset($aj['papel']) || isset($aj['acento'])) {
        $col[5] = dis_mezcla($P, $A, 0.10);
        $col[6] = dis_mezcla($P, $A, 0.16);
    }
    if (isset($aj['papel'])) {
        $col[4] = $P;
        $col[8] = dis_mezcla($P, '#ffffff', 0.55);
        $col[9] = $col[5];
        $d['body']['--at-papel-tarjeta'] = $col[8];
        $d['body']['--at-lino'] = dis_mezcla($P, $T, 0.10);
        if ($clave === 'citricos') $d['body']['--papel-2'] = dis_mezcla($P, $col[7], 0.12);
    }
    if (isset($aj['papel']) || isset($aj['acento'])) $d['body']['--at-filete-azul'] = dis_mezcla($P, $A, 0.34);
    if (isset($aj['acento'])) {
        $col[1] = $A;
        $col[2] = dis_mezcla($A, '#000000', 0.20);
        if ($sigue === 'acento') $col[0] = $A;
    }
    if (isset($aj['tinta'])) {
        $d['tinta'] = $T;
        $col[3] = dis_mezcla($T, $P, 0.22);
        if ($sigue === 'tinta') $col[0] = $T;
        if ($clave === 'citricos') $d['body']['--ink-soft'] = dis_mezcla($T, $P, 0.12);
    }
    // Color de los nombres: por defecto sigue a lo que ya seguía (el primary o la tinta del diseño); si no, el valor elegido
    $nc = $aj['nombres_color'] ?? 'diseno';
    if ($nc === 'tinta') $d['nombres_color'] = $T;
    elseif ($nc === 'acento') $d['nombres_color'] = $A;
    elseif ($nombres0 !== null) {
        if ($nombres0 === $primary0) $d['nombres_color'] = $col[0];
        elseif ($nombres0 === ($fab['tinta'])) $d['nombres_color'] = $T;
    }
    // Sin tinta propia (Masía) el texto va en --primary: --tinta solo se emite si la palanca la movió
    $d['colores'] = array_map('strtoupper', $col);
    if (isset($d['nombres_color'])) $d['nombres_color'] = strtoupper($d['nombres_color']);
    if (isset($d['tinta'])) $d['tinta'] = strtoupper($d['tinta']);
    $d['body'] = array_map('strtoupper', $d['body']);
    return $d;
}

/** CSS de lo que va a nivel de <body> (variables de boda.css que no llegan por la paleta). '' si no hay ajustes. */
function diseno_css(string $clave, array $aj): string {
    $aj = normaliza_diseno($clave, $aj);
    if (!$aj) return '';
    $pal = diseno_paleta($clave, $aj);
    $v = $pal['body'];
    foreach (DISENO_PALANCAS as $k => $p) {
        if (isset($p['css']) && isset($aj[$k])) $v[$p['css']] = $p['opciones'][$aj[$k]][1];
    }
    if (!$v) return '';
    $o = 'body.atelier.atelier-' . $clave . '{';
    foreach ($v as $n => $val) $o .= $n . ':' . $val . ';';
    $o .= '}';
    // Último cerrojo: nada de lo emitido puede cerrar el <style> ni escapar de la declaración
    return preg_match('/[<>\\\\]/', $o) ? '' : $o;
}

/** Atributos del <body> que mueven las palancas (hoy solo «accesos»). Siempre trae el de fábrica. @return array<string,string> */
function diseno_atributos(string $clave, array $aj): array {
    $aj = normaliza_diseno($clave, $aj);
    $o = [];
    foreach (DISENO_PALANCAS as $k => $p) {
        if (!isset($p['attr'])) continue;
        $o[$p['attr']] = $p['opciones'][$aj[$k] ?? $p['defecto']][1];
    }
    return $o;
}
