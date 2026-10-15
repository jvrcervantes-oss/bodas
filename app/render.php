<?php
// El ÚNICO generador de HTML de una boda. Lo usan la web publicada, la vista previa
// del creador y el ZIP descargable: si hubiera dos, la pareja vería una cosa y
// descargaría otra. Todo texto de la pareja o de un invitado pasa por h(); no existe
// ningún campo de HTML libre.

declare(strict_types=1);

require_once __DIR__ . '/schema.php';
require_once __DIR__ . '/disenos.php';

/**
 * $ctx:
 *  modo    'live' | 'preview' | 'zip'
 *  assets  prefijo de /assets (live: '/assets/', zip: 'assets/', preview: url absoluta del creador)
 *  foto    url de la foto o '' si no hay (en preview la pone el creador por postMessage)
 *  slug    nombre de la web (para el ZIP: enlaza a la versión alojada)
 */
function render_pagina(array $c, string $ruta, array $ctx): ?string {
    $ctx += ['modo' => 'live', 'assets' => '/assets/', 'foto' => '', 'slug' => ''];
    // Ajustes de diseño (S3): los pone SOLO quien llama desde código del estudio; nunca salen del config de la pareja ni de la petición
    $ctx['ajustes_diseno'] = ($c['atelier'] ?? '') !== '' && is_array($ctx['ajustes_diseno'] ?? null) ? normaliza_diseno($c['atelier'], $ctx['ajustes_diseno']) : [];
    $ctx['hero_uno'] = ($c['atelier'] ?? '') !== '' && diseno_hero_uno($c['atelier'], $ctx['ajustes_diseno']);
    if ($ruta === '' || $ruta === 'inicio') {
        $titulo = nombres($c) ?: 'Nuestra boda';
        $cuerpo = pagina_inicio($c, $ctx);
        $ruta = '';
    } elseif ($ruta === 'privacidad') {
        $titulo = 'Privacidad';
        $cuerpo = pagina_privacidad($c, $ctx);
    } else {
        $s = seccion_por_ruta($c, $ruta);
        if (!$s) return null;
        $titulo = $s['titulo'];
        $cuerpo = pagina_seccion($c, $s, $ctx);
    }
    return layout($c, $ruta, $titulo, $cuerpo, $ctx);
}

/** Enlace interno según el modo. */
function enlace(string $ruta, array $ctx): string {
    if ($ctx['modo'] === 'zip') return $ruta === '' ? 'index.html' : $ruta . '.html';
    if ($ctx['modo'] === 'preview') return '#' . ($ruta === '' ? 'inicio' : $ruta);
    // Abierta por un enlace de grupo (/i/<token>): «Confirmar» vuelve a ese enlace, no a la confirmación sin grupo
    if (($ctx['rsvp_href'] ?? '') !== '' && $ruta === ($ctx['rsvp_ruta'] ?? null)) return $ctx['rsvp_href'];
    return '/' . $ruta;
}
function a_interno(string $ruta, array $ctx, string $attrs = ''): string {
    $extra = $ctx['modo'] === 'preview' ? ' data-ir="' . h($ruta === '' ? 'inicio' : $ruta) . '"' : '';
    return '<a href="' . h(enlace($ruta, $ctx)) . '"' . $extra . ($attrs !== '' ? ' ' . $attrs : '') . '>';
}

function parrafos(string $t, string $clase = ''): string {
    $out = '';
    foreach (preg_split('/\n\s*\n|\n/', trim($t)) ?: [] as $p) {
        $p = trim($p);
        if ($p !== '') $out .= '<p' . ($clase !== '' ? ' class="' . $clase . '"' : '') . '>' . h($p) . '</p>';
    }
    return $out;
}

function ico(string $d, string $cls = 'ico'): string {
    return '<svg class="' . $cls . '" viewBox="0 0 24 24" aria-hidden="true"><path d="' . $d . '"/></svg>';
}
const ICONOS = [
    'rsvp' => 'M3 7h18v12H3zM3 7l9 6 9-6',
    'informacion' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 11v6M12 7.5v.5',
    'hoteles' => 'M3 18V7M3 14h18v4M21 14v-2a3 3 0 0 0-3-3h-7v5',
    'transporte' => 'M4 3h16v14H4zM4 11h16M7 17v3M17 17v3',
    'regalos' => 'M3 8h18v4H3zM5 12v8h14v-8M12 8v12M12 8C10 4 6.5 4.5 7 7c.3 1.2 2.5 1 5 1zm0 0c2-4 5.5-3.5 5-1-.3 1.2-2.5 1-5 1z',
    'musica' => 'M9 18V5l11-2v13M9 18a3 3 0 1 1-6 0 3 3 0 0 1 6 0zM20 16a3 3 0 1 1-6 0 3 3 0 0 1 6 0z',
    'dresscode' => 'M12 7a2 2 0 1 1 2-2c0 1-2 1.5-2 3M12 8 3 16h18z',
    'libre' => 'M5 4h14v16H5zM8 8h8M8 12h8M8 16h5',
    'historia' => 'M4 5c3-1.5 5-1.5 8 0v14c-3-1.5-5-1.5-8 0zM12 5c3-1.5 5-1.5 8 0v14c-3-1.5-5-1.5-8 0z',
    'galeria' => 'M3 5h18v14H3zM3 15l5-5 4 4 3-3 6 6M15 9.5a1.5 1.5 0 1 0 0-.01',
    'libro' => 'M4 4h11a3 3 0 0 1 3 3v13H7a3 3 0 0 1-3-3zM18 20a2 2 0 0 0 2-2V6M8 9h6M8 13h4',
    'corazon' => 'M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z',
    'flecha' => 'M5 12h14M13 6l6 6-6 6',
    'mapa' => 'M9 4 3 6v14l6-2 6 2 6-2V4l-6 2-6-2zM9 4v14M15 6v14',
    'casa' => 'M3 11l9-7 9 7v9a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z',
    'calendario' => 'M3 5h18v16H3zM3 10h18M8 3v4M16 3v4M9 15l2 2 4-4',
];

function tema_css(array $c, array $aj = []): string {
    $t = TEMAS[$c['tema']] ?? TEMAS['eucalipto'];
    $at = ($c['atelier'] ?? '') !== '' ? diseno_paleta($c['atelier'], $aj) : null; // S3: la paleta con los ajustes del estudio (sin ajustes, ATELIER tal cual)
    if ($at) $t = array_merge([$at['nombre']], $at['colores']);
    $rgb = function (string $hex): string {
        return implode(' ', array_map('hexdec', str_split(ltrim($hex, '#'), 2)));
    };
    return ':root{--primary:' . $t[1] . ';--accent:' . $t[2] . ';--accent-hover:' . $t[3] . ';--secondary:' . $t[4]
        . ';--sage:' . $t[5] . ';--sage-2:' . $t[6] . ';--sage-2-hover:' . $t[7] . ';--gold:' . $t[8]
        . ';--on-dark:' . $t[9] . ';--on-dark-soft:' . $t[10]
        . ';--primary-rgb:' . $rgb($t[1]) . ';--accent-rgb:' . $rgb($t[2]) . ';--on-dark-rgb:' . $rgb($t[9])
        . ';--sage-2-rgb:' . $rgb($t[6]) . ';--sage-rgb:' . $rgb($t[5])
        . ';' . fuente_css($c, $at)
        . ($at && isset($at['nombres_color']) ? ';--nombres-color:' . $at['nombres_color'] . ';--tinta:' . $at['tinta'] : '')
        . '}';
}

function fuente_css(array $c, ?array $at = null): string {
    $f = FUENTES[$c['fuente'] ?? 'clasica'] ?? FUENTES['clasica'];
    if (($c['atelier'] ?? '') !== '') { $a = $at ?? ATELIER[$c['atelier']]; $f = array_merge([$a['nombre']], $a['fuentes']); }
    if (($a = FUENTES_AUTOR[$c['fuente_autor'] ?? ''] ?? null) && ($c['atelier'] ?? '') !== '') $f = [$a['nombre'], $a['titulos'], $a['textos'], $a['nombres'], $a['estilo']];
    return '--serif:' . $f[1] . ';--sans:' . $f[2] . ';--nombres:' . $f[3] . ';--nombres-estilo:' . $f[4];
}

/**
 * @font-face de la tipografía de autor elegida, solo si se usa. En el ZIP no: el fichero no se
 * entrega (redistribución) y la web descargada cae a la alternativa libre de la pila.
 */
function fuente_autor_css(array $c, array $ctx): string {
    $f = fuentes_autor()[$c['fuente_autor'] ?? ''] ?? null;
    if (!$f || ($c['atelier'] ?? '') === '' || $ctx['modo'] === 'zip') return '';
    $o = '';
    foreach ($f['archivos'] as $arch => [$fam, $estilo]) {
        $o .= "@font-face{font-family:'" . $fam . "';font-style:" . $estilo . ";font-weight:1 700;font-display:swap;src:url(" . $ctx['assets'] . 'fonts/' . $arch . ") format('woff2');}";
    }
    return $o;
}

/** Iniciales para sellos y monogramas: «E&A». */
function monograma(array $c): string { return str_replace(' ', '', iniciales($c)); }

/** Hasta $n accesos a secciones de la web, en el orden en que la pareja las tiene. $claseRsvp: la clase del de
 *  confirmar, si la lleva distinta (Cerámica: solo ese va relleno, S2b). */
function accesos(array $c, array $ctx, int $n, string $clase = '', string $claseRsvp = '', bool $soloCta = false): array {
    $links = [];
    foreach (['informacion', 'rsvp', 'regalos', 'musica', 'historia', 'galeria'] as $t) {
        $s = seccion_tipo($c, $t);
        $cl = ($t === 'rsvp' && $claseRsvp !== '') ? $claseRsvp : $clase;
        if ($s && count($links) < $n) $links[$t] = a_interno($s['ruta'], $ctx, $cl !== '' ? 'class="' . $cl . '"' : '') . h($t === 'rsvp' ? 'Confirmar' : $s['titulo']) . '</a>';
    }
    // Palanca «botones del hero = uno» (S3): solo el de confirmar (o el primero si no hay confirmar). Donde el hero ya lleva su CTA, $soloCta: ninguno
    if ($links && ($ctx['hero_uno'] ?? false)) $links = $soloCta ? [] : [$links['rsvp'] ?? reset($links)];
    return array_values($links);
}

/** Nombres del hero Atelier (S2b): el conector («&» o «y») y el segundo nombre van juntos en una caja en línea
 *  (.hero-n2): si no caben tras el primero bajan enteros a la línea siguiente, así el conector nunca queda colgado
 *  del primer nombre. Cítricos los pone siempre en dos líneas, con el conector en <i> (nombres_citricos). */
function nombres_hero(array $c): string {
    $a = $c['pareja']['nombre1'];
    $b = $c['pareja']['nombre2'];
    $nom = nombres($c);
    if ($a === '' || $b === '') return h($nom ?: 'Vuestros nombres');
    $sep = trim(substr($nom, strlen($a), strlen($nom) - strlen($a) - strlen($b)));
    return h($a) . ' <span class="hero-n2">' . h($sep) . ' ' . h($b) . '</span>';
}

function lugar_linea(array $c): string {
    return trim($c['ceremonia']['lugar'] . ($c['ciudad'] !== '' ? ' · ' . $c['ciudad'] : ''), ' ·');
}

/** Ilustración de cabecera de cada diseño Atelier (piezas de la maqueta del owner, sin textos ni iniciales ajenas). */
function arte_atelier(array $c, string $A): string {
    $img = fn($f, $cls) => '<img class="atelier-arte ' . $cls . '" src="' . h($A) . 'img/atelier/' . $f . '" alt="" aria-hidden="true">';
    switch ($c['atelier']) {
        // Las dos ramas de limonero de las esquinas de arriba: la izquierda junto a los nombres, la derecha sobre la terraza
        case 'citricos': return $img('esquina-1.webp', 'esq esq-i') . $img('esquina-2.webp', 'cit-rama');
        case 'herbario': return $img('herbario-banda.webp', 'banda-herbario');
        case 'masia': return $img('masia.webp', 'arte-masia');
        // Monograma entre las dos ramas de olivo de azulejo
        case 'ceramica': return '<div class="tal-mono" aria-hidden="true">' . $img('olivo-i.webp', 'tal-olivo') . '<span>' . h(iniciales($c) ?: '♥') . '</span>' . $img('olivo-d.webp', 'tal-olivo') . '</div>';
        default: return '';
    }
}

/** Sello de cera bronce con las iniciales de ESTA pareja (el original traía un monograma ajeno, borrado). */
function sello_bronce(array $c, string $A, string $cls = 'sello-bronce'): string {
    // aria-hidden (S2b): es un adorno que repite las iniciales de los nombres del h1, como el monograma de Cerámica
    return '<span class="' . $cls . '" aria-hidden="true"><img src="' . h($A) . 'img/atelier/sello-bronce.webp" alt=""><b>' . h(monograma($c) ?: '♥') . '</b></span>';
}

/** Lo que va bajo los nombres en cada diseño (composición de sus especificaciones). */
function atelier_tras_texto(array $c, array $ctx): string {
    $A = $ctx['assets'];
    $rsvp = seccion_tipo($c, 'rsvp');
    $lugar = lugar_linea($c);
    switch ($c['atelier']) {
        case 'citricos':
            // La frase de la invitación entre filetes («¡Nos casamos!»), la fecha en cifras con el día de la semana, el
            // lugar, el hashtag y los accesos (el de confirmar es la CTA del hero)
            $links = accesos($c, $ctx, 3);
            $d = $c['fecha'] !== '' ? DateTimeImmutable::createFromFormat('!Y-m-d', $c['fecha']) : false;
            return ($c['portada']['invitacion'] !== '' ? '<p class="cit-dicho"><span>' . h($c['portada']['invitacion']) . '</span></p>' : '')
                . ($d ? '<p class="cit-fecha"><time datetime="' . $d->format('Y-m-d') . '">' . $d->format('d.m.Y') . '</time><span>' . h(DIAS[(int) $d->format('w')]) . '</span></p>' : '')
                . ($lugar !== '' ? '<p class="cit-lugar">' . h($lugar) . '</p>' : '')
                . ($c['portada']['hashtag'] !== '' ? '<p class="hero-hashtag">#' . h($c['portada']['hashtag']) . '</p>' : '')
                . ($links ? '<nav class="cit-nav" aria-label="Accesos">' . implode('', $links) . '</nav>' : '');
        case 'herbario':
            return '<img class="ramita" src="' . h($A) . 'img/atelier/ramita.svg" alt="" aria-hidden="true">'
                . ($lugar !== '' ? '<p class="herb-lugar">' . h($lugar) . '</p>' : '')
                . ($rsvp ? '<span class="herb-label">Confirmación</span>' . a_interno($rsvp['ruta'], $ctx, 'class="herb-btn"') . h($rsvp['titulo']) . '</a>' : '');
        case 'ceramica':
            $links = accesos($c, $ctx, 3, 'tal-btn', 'tal-btn tal-rsvp');
            return '<img class="tal-flor" src="' . h($A) . 'img/atelier/flor-talavera.svg" alt="" aria-hidden="true">'
                . ($lugar !== '' ? '<p class="tal-lugar">' . h($lugar) . '</p>' : '')
                . ($links ? '<nav class="tal-nav" aria-label="Accesos">' . implode('', $links) . '</nav>' : '');
        case 'lacre':
            $links = accesos($c, $ctx, 4, '', '', true);
            return sello_bronce($c, $A, 'sello-bronce lac-sello')
                . ($lugar !== '' ? '<p class="lac-lugar">' . h($lugar) . '</p>' : '')
                . ($rsvp ? a_interno($rsvp['ruta'], $ctx, 'class="lac-btn"') . h($rsvp['titulo']) . '</a>' : '')
                . ($links ? '<nav class="lac-nav" aria-label="Accesos">' . implode('<i aria-hidden="true">•</i>', $links) . '</nav>' : '');
        case 'masia':
            // S2b: era el único hero sin llamada a la acción; la de confirmar va rellena (la única de la pantalla)
            return ($lugar !== '' ? '<p class="mas-lugar">' . h($lugar) . '</p>' : '')
                . ($rsvp ? a_interno($rsvp['ruta'], $ctx, 'class="btn mas-btn"') . h($rsvp['titulo']) . '</a>' : '');
        case 'atardecer':
            $links = accesos($c, $ctx, 3, '', '', true);
            return ($lugar !== '' ? '<p class="atd-lugar">' . h($lugar) . '</p>' : '')
                . ($rsvp ? a_interno($rsvp['ruta'], $ctx, 'class="atd-btn"') . h($rsvp['titulo']) . '</a>' : '')
                . ($links ? '<nav class="atd-nav" aria-label="Accesos">' . implode('', $links) . '</nav>' : '');
    }
    return '';
}

/**
 * Entrada del pack Atelier: un sobre cerrado a pantalla completa. Se pulsa el sello, la solapa
 * gira en 3D, la tarjeta sale del sobre, viene hacia delante y la web aparece detrás, de
 * desenfocada a nítida. Mismo mecanismo para los seis diseños, con la piel de cada uno
 * (fondo, forro, sello y arte de la tarjeta). boda.js la enseña una vez por visita y nunca
 * con «reducir movimiento».
 */
function entrada_atelier(array $c, array $ctx): string {
    $A = $ctx['assets'];
    $k = $c['atelier'];
    $nom = nombres($c) ?: 'Vuestros nombres';
    $rsvp = seccion_tipo($c, 'rsvp');
    $img = fn($f, $cls = '') => '<img class="' . $cls . '" src="' . h($A) . 'img/atelier/' . $f . '" alt="">';
    $mono = h(monograma($c) ?: '♥');
    // Sello: lo que se pulsa. Lacre y Cítricos llevan su pieza; el resto, lacre de color con las iniciales.
    $sello = match ($k) {
        'lacre' => '<img src="' . h($A) . 'img/atelier/sello-bronce.webp" alt=""><b>' . $mono . '</b>',
        'citricos' => '<img class="en-sello-disco" src="' . h($A) . 'img/atelier/sello-limon.webp" alt="">',
        default => '<b>' . $mono . '</b>',
    };
    $arte = match ($k) {
        'masia' => '<div class="en-dibujo">' . $img('masia.webp', 'en-lapiz') . $img('masia.webp', 'en-tinta') . '</div>',
        'citricos' => $img('limones.webp', 'en-arte'),
        'herbario' => $img('herbario-banda.webp', 'en-arte en-arte-banda'),
        'ceramica' => '<div class="tal-mono en-arte">' . $img('olivo-i.webp', 'tal-olivo') . '<span>' . h(iniciales($c) ?: '♥') . '</span>' . $img('olivo-d.webp', 'tal-olivo') . '</div>',
        'lacre' => '<span class="en-arte en-poema">' . h($c['portada']['invitacion'] ?: 'Nos casamos') . '</span>',
        default => '<span class="en-arte en-aro"></span>',
    };
    $escena = $k === 'citricos'
        ? $img('esquina-1.webp', 'en-esq en-esq-i') . $img('esquina-2.webp', 'en-esq en-esq-d') . '<div class="en-petalos" aria-hidden="true">' . str_repeat('<i></i>', 14) . '</div>'
        : ($k === 'atardecer' ? '<span class="en-sol" aria-hidden="true"></span>' : '');
    return '<div class="entrada entrada-' . h($k) . '" id="entrada" role="dialog" aria-modal="true" aria-label="Invitación de ' . h($nom) . '" data-entrada' . (!empty($ctx['intro']) ? ' data-entrada-previa' : '') . '>'
        . '<div class="en-papel"></div>' . $escena
        . '<div class="en-escena">'
        . '<div class="en-sobre" data-entrada-abrir>'
        . '<div class="en-fondo"></div>'
        . '<div class="en-tarjeta">' . $arte
        . '<p class="en-kicker">' . h($k === 'lacre' ? linea_fecha($c) : ($c['portada']['invitacion'] ?: 'Nos casamos')) . '</p>'
        . '<h2 class="en-nombres">' . h($nom) . '</h2>'
        . ($k !== 'lacre' ? '<p class="en-fecha">' . h(linea_fecha($c)) . '</p>' : '')
        . '</div>'
        . '<div class="en-bolsillo"><span class="en-destino">' . h($nom) . '</span></div>'
        . '<div class="en-solapa"><i class="en-solapa-f"></i><i class="en-solapa-b"></i></div>'
        . '<button type="button" class="en-sello en-sello-' . h($k) . '" data-entrada-abrir aria-label="Abrir la invitación">' . $sello . '</button>'
        . '</div>'
        . '<p class="en-pista">Pulsad el sello para abrir la invitación</p>'
        . ($rsvp ? '<p class="en-atajo">' . a_interno($rsvp['ruta'], $ctx, 'class="en-rsvp" data-entrada-rsvp') . 'Ir directos a ' . h(mb_strtolower($rsvp['titulo'], 'UTF-8')) . '</a></p>' : '')
        . '</div></div>';
}

/** Frase que la pareja cambió en el creador, o '' si deja la de siempre. */
function frase_propia(array $c, string $k): string { return (string) ($c['portada'][$k] ?? ''); }
function frase_web(array $c, string $k, string $porDefecto): string { return frase_propia($c, $k) ?: $porDefecto; }

function linea_fecha(array $c): string {
    $partes = array_filter([fecha_puntos($c['fecha']), $c['ciudad']]);
    return implode(' · ', $partes);
}

function layout(array $c, string $ruta, string $titulo, string $cuerpo, array $ctx): string {
    $A = $ctx['assets'];
    $nom = nombres($c);
    $secs = array_values(array_filter($c['secciones'], fn($s) => $s['on']));
    $rsvp = seccion_tipo($c, 'rsvp');
    $fechaTxt = $c['fecha'] !== '' ? ' — ' . fecha_larga($c['fecha'], false) : '';
    ob_start(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($titulo === $nom ? $nom . $fechaTxt : $titulo . ' — ' . ($nom ?: 'Nuestra boda')) ?></title>
<meta name="robots" content="noindex, nofollow">
<?php if ($ctx['modo'] === 'preview'): ?><base href="<?= h($A) ?>">
<style>body{visibility:hidden;animation:vp-ver 0s 3s forwards}@keyframes vp-ver{to{visibility:visible}}</style><?php /* vista-previa.js lo muestra al tener las fuentes: sin esto, cada cambio de letra enseña un instante la de reserva */ endif; ?>
<link rel="stylesheet" href="<?= h($ctx['modo'] === 'preview' ? '' : $A) ?>boda.css?v=<?= h(ASSETS_V) ?>">
<style><?= tema_css($c, $ctx['ajustes_diseno'] ?? []) ?><?= fuente_autor_css($c, $ctx) ?><?= $c['atelier'] !== '' ? diseno_css($c['atelier'], $ctx['ajustes_diseno'] ?? []) : '' ?></style>
</head>
<body class="<?= $ruta === '' ? 'page-home' : 'page-inner' ?><?= $c['atelier'] !== '' ? ' atelier atelier-' . h($c['atelier']) : ' deco-' . h($c['decoracion']) . ' fuente-' . h($c['fuente']) ?><?= $ctx['modo'] === 'preview' ? ' is-preview' : '' ?>"<?php if ($c['atelier'] !== '') foreach (diseno_atributos($c['atelier'], $ctx['ajustes_diseno'] ?? []) as $an => $av) echo ' ' . $an . '="' . h($av) . '"'; /* S3: lo de fábrica (accesos en filetes) o lo que el estudio ha ajustado */ ?>>
<?php if ($ctx['modo'] === 'preview' && empty($ctx['firma_slug'])): // marca de agua: viaja con el HTML si alguien copia la vista previa (owner, 25-sep). En el panel (firma_slug: la web ya está pagada y publicada) no sale (owner, 1-oct) ?>
<div class="marca-previa" aria-hidden="true"><span>Vista previa · <?= h(marca()) ?></span><span>Publicad vuestra web para quitar esta marca</span></div>
<?php endif; ?>

<nav class="site-nav" aria-label="Navegación principal">
  <div class="nav-bar">
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="siteMenu" aria-label="Abrir menú"><?= ico('M4 7h16M4 12h16M4 17h16') ?><span class="nav-toggle-txt">Menú</span></button>
    <?= a_interno('', $ctx, 'class="nav-brand"' . ($c['atelier'] === 'citricos' ? ' aria-label="' . h(($nom ?: 'Nuestra boda') . ', inicio') . '"' : '')) ?><?= $c['atelier'] === 'citricos' ? '<span class="cit-mono" aria-hidden="true">' . mono_citricos($c) . '</span>' : '' /* Cítricos: monograma en laurel (piel «Terraza») */ ?><span class="nav-names"><?= h($nom ?: 'Vuestros nombres') ?></span><span class="nav-sub">Nuestra boda</span></a>
<?php if ($rsvp): ?>
    <?= a_interno($rsvp['ruta'], $ctx, 'class="btn nav-cta"') ?><?= h($rsvp['titulo']) ?></a>
    <?= a_interno($rsvp['ruta'], $ctx, 'class="nav-heart" aria-label="' . h($rsvp['titulo']) . '"') ?><?= ico(ICONOS['corazon']) ?></a>
<?php endif; ?>
  </div>
  <div class="nav-drawer" id="siteMenu">
    <div class="drawer-head">
      <span class="nav-names"><?= h($nom ?: 'Nuestra boda') ?></span>
      <button class="nav-close" type="button" data-nav-close aria-label="Cerrar menú"><?= ico('M6 6l12 12M18 6 6 18') ?></button>
    </div>
    <ul>
      <li><?= a_interno('', $ctx, $ruta === '' ? 'aria-current="page"' : '') ?><span>Inicio</span><?= ico(ICONOS['flecha']) ?></a></li>
<?php foreach ($secs as $s): ?>
      <li<?= $s['tipo'] === 'rsvp' ? ' class="drawer-rsvp"' : '' ?>><?= a_interno($s['ruta'], $ctx, $ruta === $s['ruta'] ? 'aria-current="page"' : '') ?><span><?= h($s['titulo']) ?></span><?= ico($s['tipo'] === 'rsvp' ? ICONOS['corazon'] : ICONOS['flecha']) ?></a></li>
<?php endforeach; ?>
    </ul>
    <div class="drawer-foot">
      <p class="mono"><?= h(iniciales($c)) ?></p>
      <p class="kicker"><?= h(linea_fecha($c)) ?></p>
    </div>
  </div>
</nav>
<?= tab_bar($c, $ruta, $ctx) ?>
<?php if ($c['atelier'] !== '' && $ruta === '' && ($ctx['modo'] !== 'preview' || !empty($ctx['intro']))) echo entrada_atelier($c, $ctx); ?>

<?= $cuerpo ?>

<footer class="site-footer">
  <div class="footer-mono" aria-hidden="true"><span class="rule"></span><span><?= h(iniciales($c)) ?></span><span class="rule"></span></div>
<?php if ($c['portada']['pie'] !== ''): ?>
  <p class="footer-thanks"><?= h($c['portada']['pie']) ?></p>
<?php endif; ?>
<?php if (($c['portada']['hashtag'] ?? '') !== ''): ?>
  <p class="footer-hashtag">#<?= h($c['portada']['hashtag']) ?></p>
<?php endif; ?>
<?php if ($rsvp && $ruta !== $rsvp['ruta']): ?>
  <?= a_interno($rsvp['ruta'], $ctx, 'class="btn"') ?><?= h($rsvp['titulo']) ?></a>
<?php endif; ?>
  <p class="kicker"><?= h(trim($nom . ' · ' . linea_fecha($c), ' ·')) ?></p>
  <p class="footer-legal"><?= a_interno('privacidad', $ctx) ?>Privacidad</a></p>
</footer>

<?php if ($ctx['modo'] === 'preview'): ?>
<script src="js/vista-previa.js?v=<?= h(ASSETS_V) ?>" defer></script>
<?php endif; ?>
<script src="<?= h($ctx['modo'] === 'preview' ? '' : $A) ?>js/boda.js?v=<?= h(ASSETS_V) ?>" defer></script>
</body>
</html>
<?php
    return (string) ob_get_clean();
}

function tab_bar(array $c, string $ruta, array $ctx): string {
    $items = [['', 'Inicio', ICONOS['casa']]];
    $rsvp = seccion_tipo($c, 'rsvp');
    if ($rsvp) $items[] = [$rsvp['ruta'], 'Menú', ICONOS['rsvp']];   // antes «RSVP» (owner, 25-sep)
    foreach ($c['secciones'] as $s) {
        if (!$s['on'] || $s['tipo'] === 'rsvp' || count($items) >= 5) continue;
        $corto = ['regalos' => 'Regalos', 'informacion' => 'Info', 'dresscode' => 'Dress code', 'historia' => 'Historia'][$s['tipo']] ?? $s['titulo'];
        $items[] = [$s['ruta'], mb_strimwidth($corto, 0, 11, '…', 'UTF-8'), ICONOS[$s['tipo']]];
    }
    $o = '<nav class="tab-bar" aria-label="Accesos rápidos"><ul>';
    foreach ($items as [$r, $t, $i]) {
        $o .= '<li>' . a_interno($r, $ctx, $ruta === $r ? 'aria-current="page"' : '') . ico($i) . h($t) . '</a></li>';
    }
    return $o . '</ul></nav>';
}

/**
 * El mapita de ubicaciones: imagen hecha en el servidor (app/mapa.php) y las chinchetas en
 * HTML, del color del diseño. En la vista previa del creador no hay mapa real (no se generan
 * mapas sin sesión): se enseña un hueco que lo explica.
 */
function bloque_mapa(array $c, array $ctx): string {
    if (trim($c['ceremonia']['lugar'] . $c['ceremonia']['direccion'] . $c['convite']['lugar'] . $c['convite']['direccion']) === '') return '';
    $m = $ctx['mapa'] ?? null;
    if (!$m) {
        if ($ctx['modo'] !== 'preview') return '';
        return '<figure class="mapa mapa-hueco rv"><div class="mapa-lienzo"><span>El mapa con vuestras ubicaciones aparece aquí al publicar la web.</span></div></figure>';
    }
    $o = '<figure class="mapa rv"><div class="mapa-lienzo"><img src="' . h($m['src']) . '" alt="Mapa con ' . h(implode(' y ', array_map(fn($p) => mb_strtolower($p['t'], 'UTF-8'), $m['pines']))) . '" width="' . 960 . '" height="' . 540 . '" loading="lazy">';
    foreach ($m['pines'] as $i => $p) {
        $o .= '<span class="mapa-pin" style="left:' . (float) $p['x'] . '%;top:' . (float) $p['y'] . '%"><i aria-hidden="true"></i><b>' . h($p['t']) . '</b></span>';
    }
    return $o . '</div><figcaption>© <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener noreferrer">Colaboradores de OpenStreetMap</a></figcaption></figure>';
}

function mapa_q(array $lugar): string {
    return trim($lugar['lugar'] . ' ' . $lugar['direccion']);
}

/** Ramos de esquina de «Flores de acuarela» y «Eucalipto» junto a la foto (solo ordenador; en el móvil siguen las guirnaldas).
 *  $capa: 'atras' (tallos, van detrás del marco) o 'delante'. Las posiciones salen de RAMOS_FOTO (schema.php).
 *  $nombres: true pinta los que van sobre los nombres (Fundida) en vez de los de la foto. */
function ramos_foto(array $c, string $fe, string $A, string $capa, bool $nombres = false): string {
    $o = '';
    foreach (RAMOS_FOTO[$c['decoracion']][$fe] ?? [] as $r) {
        if (!empty($r['nombres']) !== $nombres) continue;
        $o .= '<img class="mf-r mf-r-' . $capa . '" src="' . h($A) . 'img/deco/ramos/' . h($r['f']) . '-' . $capa . '.webp" alt="" style="--x:' . $r['x'] . ';--y:' . $r['y']
            . ';--dx:' . $r['dx'] . ';--dy:' . $r['dy'] . ';--w:' . $r['w'] . (isset($r['g']) ? ';--g:' . $r['g'] : '') . '">';
    }
    return $o;
}

function pagina_inicio(array $c, array $ctx): string {
    if ($c['atelier'] === 'citricos') return inicio_citricos($c, $ctx);   // composición propia (piel «Terraza», 14-oct-2026)
    $A = $ctx['assets'];
    $nom = nombres($c);
    $po = $c['portada'];
    $rsvp = seccion_tipo($c, 'rsvp');
    // Colores del dress code en la píldora de vestimenta: solo con la sección Dress code ACTIVA ('' = nada cambia)
    $dc = seccion_tipo($c, 'dresscode');
    $muestrasVest = $dc ? muestras_color($dc['datos']['colores'] ?? [], 'muestras-vest') : '';
    $hayFoto = $c['foto'] && ($ctx['foto'] !== '' || $ctx['modo'] === 'preview');
    // Estilo de la foto (Pack Esencial; los Atelier traen su marco). Con flores, una sola guirnalda pegada a la foto
    // (Medallón: arriba y abajo) y la de encima de los nombres se quita (owner, 30-sep-2026).
    $fe = ($c['atelier'] === '' && $hayFoto) ? $c['foto_estilo'] : '';
    $hayRamos = $fe !== '' && isset(RAMOS_FOTO[$c['decoracion']][$fe]);   // en ordenador, ramos de esquina en vez de guirnalda
    $floresEnFoto = $fe !== '' && $c['decoracion'] === 'flores';
    $gTop = '<img class="mf-g mf-g-top" src="' . h($A) . 'img/deco/flores-portada-movil.webp" alt="" width="520" height="250">';
    $adornoFoto = !$floresEnFoto ? '' : ($fe === 'medallon' ? $gTop . str_replace('mf-g-top', 'mf-g-bot', $gTop) : $gTop);
    $objetivo = $c['fecha'] !== '' ? $c['fecha'] . 'T' . ($c['ceremonia']['hora'] ?: '12:00') . ':00' : '';
    ob_start(); ?>
<main class="home">
  <section class="hero<?= $hayFoto ? '' : ' hero--solo' ?><?= $fe !== '' ? ' hero--mf-' . h($fe) : '' ?>"<?= $fe === 'fundida' && ($ctx['foto_ar'] ?? '') !== '' ? ' style="--ar:' . h($ctx['foto_ar']) . '"' : '' ?>>
    <div class="hero-left">
<?php if ($c['atelier'] !== ''): ?>
      <?= arte_atelier($c, $A) ?>
<?php elseif ($c['decoracion'] === 'eucalipto'): ?>
      <picture class="deco-guirnalda<?= $hayRamos ? ' deco-g-fuera' : '' ?>"><source media="(max-width: 900px)" srcset="<?= h($A) ?>img/deco/eucalipto-portada-movil.webp" width="520" height="190"><img src="<?= h($A) ?>img/deco/eucalipto-portada.webp" alt="" width="1200" height="330"></picture>
<?php elseif ($c['decoracion'] === 'flores' && !$floresEnFoto): ?>
      <picture class="deco-guirnalda"><source media="(max-width: 900px)" srcset="<?= h($A) ?>img/deco/flores-portada-movil.webp" width="520" height="250"><img src="<?= h($A) ?>img/deco/flores-portada.webp" alt="" width="1200" height="330"></picture>
<?php endif; ?>
<?php $ramosNombres = $hayRamos ? ramos_foto($c, $fe, $A, 'atras', true) . ramos_foto($c, $fe, $A, 'delante', true) : ''; ?>
      <div class="hero-text<?= $ramosNombres !== '' ? ' hero-text--ramo' : '' ?>">
<?php if ($ramosNombres !== ''): ?>        <?= $ramosNombres ?>
<?php endif; ?><?php if ($c['atelier'] === '' && $c['decoracion'] === 'sobre'): ?>        <span class="deco-solapa" aria-hidden="true"></span><span class="deco-lacre" aria-hidden="true"><?= h(iniciales($c) ?: '♥') ?></span>
<?php endif; ?>
<?php if ($po['invitacion'] !== ''): ?>        <span class="kicker"><?= h($po['invitacion']) ?></span><?php endif; ?>
        <h1 class="hero-names" style="--nl:<?= mb_strlen($nom ?: 'Vuestros nombres', 'UTF-8') ?>"><?= $c['atelier'] !== '' ? nombres_hero($c) : h($nom ?: 'Vuestros nombres') ?></h1>
        <div class="hero-date">
          <span class="rule" aria-hidden="true"></span><span class="star" aria-hidden="true">✦</span>
          <span class="kicker"><?= h(linea_fecha($c) ?: 'La fecha') ?></span>
          <span class="star" aria-hidden="true">✦</span><span class="rule" aria-hidden="true"></span>
        </div>
<?php if ($po['hashtag'] !== ''): ?>        <p class="hero-hashtag">#<?= h($po['hashtag']) ?></p>
<?php endif; ?>
      </div>
<?php if ($c['atelier'] !== ''): ?>      <?= atelier_tras_texto($c, $ctx) ?>
<?php endif; ?>
    </div>
<?php if ($hayFoto): ?>
    <div class="mat<?= $fe !== '' ? ' mf mf-' . h($fe) : '' ?><?= $hayRamos ? ' mf-ramos' : '' ?> rv">
      <div class="mat-inner">
<?php if ($fe !== ''): ?>        <div class="mf-marco">
        <?= ramos_foto($c, $fe, $A, 'atras') ?>
<?php endif; ?>        <div class="mat-photo"><img data-foto src="<?= h($ctx['foto']) ?>" alt="<?= h($nom) ?>" width="960" height="1280" loading="eager"></div>
<?php if ($fe !== ''): ?>        <?= $adornoFoto ?>
        <?= ramos_foto($c, $fe, $A, 'delante') ?>
        </div>
<?php endif; ?><?php if ($fe !== 'fundida'): ?>        <p class="mat-caption"><?= h(trim($nom . ' · ' . linea_fecha($c), ' ·')) ?></p>
<?php endif; ?>
      </div>
    </div>
<?php endif; ?>
  </section>
<?php if ($fe === 'fundida' && in_array($c['decoracion'], ['flores', 'eucalipto'], true)): ?>
  <div class="deco-entre" aria-hidden="true"><img src="<?= h($A) ?>img/deco/<?= $c['decoracion'] === 'flores' ? 'flores-portada-movil.webp" width="520" height="250' : 'eucalipto-portada-movil.webp" width="520" height="190' ?>" alt=""></div>
<?php endif; ?>

<?php if ($po['titulo'] !== '' || $po['frase'] !== '' || $po['texto'] !== '' || $po['vestimenta'] !== ''): ?>
  <section class="letter rv">
<?php if ($po['titulo'] !== ''): ?>    <h2 class="section-title"><?= h($po['titulo']) ?></h2>
    <div class="mini-rule" aria-hidden="true"></div><?php endif; ?>
<?php if ($po['frase'] !== ''): ?>    <p class="big"><?= h($po['frase']) ?></p><?php endif; ?>
    <?= parrafos($po['texto']) ?>
<?php if ($po['vestimenta'] !== ''): ?>    <p class="pildora-vest"><span class="pildora-vest-rot">Vestimenta</span><span class="pildora-vest-txt"><?= h($po['vestimenta']) ?></span><?= $muestrasVest ?></p>
<?php endif; ?>
<?php if ($rsvp): ?>    <?= a_interno($rsvp['ruta'], $ctx, 'class="btn"') ?><?= ico(ICONOS['corazon'], 'ico ico-sm') ?><?= h($rsvp['titulo']) ?></a><?php endif; ?>
  </section>
<?php endif; ?>

<?php if ($c['fecha'] !== ''): ?>
  <section class="save-band">
    <div class="save-card rv">
      <span class="kicker"><?= h(frase_web($c, 'fecha_kicker', 'Guardad el momento')) ?></span>
      <h2><?= h(frase_web($c, 'fecha_titulo', 'Anotad la fecha')) ?></h2>
      <p class="save-date-text"><?= h(ucfirst(fecha_larga($c['fecha']))) ?></p>
      <div class="countdown" id="countdown" data-objetivo="<?= h($objetivo) ?>" aria-label="Cuenta atrás para la boda">
        <div class="countdown-item"><b data-c="days">0</b><span>Días</span></div>
        <div class="countdown-item"><b data-c="hours">0</b><span>Horas</span></div>
        <div class="countdown-item"><b data-c="mins">0</b><span>Min</span></div>
        <div class="countdown-item"><b data-c="secs">0</b><span>Seg</span></div>
      </div>
      <button class="btn cal-btn" type="button" id="calBtn" aria-expanded="false" aria-controls="calOptions"><?= ico(ICONOS['calendario'], 'ico ico-sm') ?>Añadir a mi calendario</button>
      <div class="cal-options" id="calOptions" hidden>
        <span class="kicker">Elige tu calendario</span>
        <a href="<?= h(url_google_calendar($c)) ?>" target="_blank" rel="noopener noreferrer"><span><?= ico(ICONOS['calendario']) ?>Google Calendar</span><?= ico(ICONOS['flecha'], 'ico ico-sm') ?></a>
        <a href="<?= h($ctx['modo'] === 'zip' ? 'boda.ics' : ($ctx['modo'] === 'preview' ? '#' : '/boda.ics')) ?>" download><span><?= ico('M6 2h12v20H6zM11 18h2') ?>Apple / Outlook (.ics)</span><?= ico('M12 4v11M7 10l5 5 5-5M5 20h14', 'ico ico-sm') ?></a>
      </div>
    </div>
  </section>
<?php endif; ?>

  <section class="itinerary">
    <div class="itinerary-head rv">
      <span class="kicker">Itinerario</span>
      <h2 class="section-title"><?= $c['convite']['lugar'] !== '' ? 'Ceremonia y convite' : 'Ceremonia' ?></h2>
    </div>
    <?= bloque_mapa($c, $ctx) ?>
    <div class="event-list">
<?php foreach (['ceremonia' => 'Ceremonia', 'convite' => 'Convite'] as $k => $rot):
        $e = $c[$k];
        if ($e['lugar'] === '' && $k === 'convite') continue; ?>
      <article class="event-card rv">
        <div class="event-top">
          <div>
            <span class="kicker"><?= h(trim(($e['hora'] !== '' ? $e['hora'] . ' · ' : '') . $rot)) ?></span>
            <h3><?= h($e['lugar'] ?: 'Lugar de la ' . strtolower($rot)) ?></h3>
<?php if ($e['direccion'] !== ''): ?>            <p class="where"><?= h($e['direccion']) ?></p><?php endif; ?>
          </div>
          <div class="icon-dot icon-<?= h($k) ?>" aria-hidden="true"><?= ico($k === 'ceremonia' ? 'M12 2v4M10 4h4M6 21V11l6-4 6 4v10M3 21h18M10 21v-5h4v5' : 'M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M6 18l2.5-2.5M15.5 8.5 18 6') ?></div>
        </div>
<?php if ($e['lugar'] !== ''): ?>
        <a class="btn btn-soft" href="https://maps.google.com/?q=<?= h(rawurlencode(mapa_q($e))) ?>" target="_blank" rel="noopener noreferrer"><?= ico(ICONOS['mapa'], 'ico ico-sm') ?>Ver mapa · <?= h($rot) ?></a>
<?php endif; ?>
      </article>
<?php endforeach; ?>
    </div>
<?php if ($c['programa']): ?><?= bloque_programa($c) ?><?php endif; ?>
  </section>

  <section class="quick">
    <div class="quick-grid">
<?php foreach ($c['secciones'] as $s):
        if (!$s['on'] || $s['tipo'] === 'informacion') continue;
        if ($s['tipo'] === 'dresscode' && $po['vestimenta'] !== '') continue;   // la píldora de la portada ya lo dice: no se duplica
        $resumen = resumen($s['datos']['texto'] ?? '');
        if ($resumen === '' && $s['tipo'] === 'transporte' && $s['datos']['trayectos']) {
            $t0 = $s['datos']['trayectos'][0];
            $resumen = resumen('Autobús' . ($t0['hora'] !== '' ? ' a las ' . $t0['hora'] : '') . ' desde ' . $t0['salida'] . '.');
        }
        if ($s['tipo'] === 'rsvp'): ?>
      <div class="q-card q-rsvp rv">
        <div class="q-label"><?= ico(ICONOS['rsvp']) ?><span class="kicker" style="color:inherit">Confirmación</span></div>
        <h3><?= h($s['titulo']) ?></h3>
        <p><?= h($s['datos']['fecha_limite'] !== '' ? 'Por favor, confirmad antes del ' . fecha_larga($s['datos']['fecha_limite'], false) . '.' : ($resumen ?: 'Contadnos si venís y qué menú preferís.')) ?></p>
        <?= a_interno($s['ruta'], $ctx, 'class="btn btn-light"') ?><?= h($s['titulo']) ?></a>
      </div>
<?php elseif ($s['tipo'] === 'musica'): ?>
      <div class="q-card q-music rv">
        <div>
          <span class="kicker" style="margin-bottom:4px"><?= h(frase_web($c, 'cancion_kicker', '¿Qué canción no puede faltar?')) ?></span>
          <h3><?= h($s['titulo']) ?></h3>
<?php if ($resumen !== ''): ?>          <p><?= h($resumen) ?></p><?php endif; ?>
        </div>
        <?= a_interno($s['ruta'], $ctx, 'class="round-btn" aria-label="Ir a ' . h($s['titulo']) . '"') ?><?= ico(ICONOS['musica']) ?></a>
      </div>
<?php else: ?>
      <div class="q-card rv">
        <div class="q-label"><?= ico(ICONOS[$s['tipo']]) ?><span class="kicker"><?= h(['hoteles' => 'Alojamiento', 'transporte' => 'Desplazamiento', 'regalos' => 'Detalle con nosotros', 'dresscode' => 'Código de vestimenta'][$s['tipo']] ?? 'Más información') ?></span></div>
        <h3><?= h($s['titulo']) ?></h3>
<?php if ($resumen !== ''): ?>        <p><?= h($resumen) ?></p><?php endif; ?>
<?php if ($s['tipo'] === 'dresscode' && ($mu = muestras_color($s['datos']['colores'] ?? [])) !== ''): ?>        <?= $mu ?>
<?php endif; ?>
        <?= a_interno($s['ruta'], $ctx, 'class="link-arrow"') ?>Ver <?= h(mb_strtolower($s['titulo'], 'UTF-8')) ?> <?= ico(ICONOS['flecha'], 'ico ico-sm') ?></a>
      </div>
<?php endif; endforeach; ?>
    </div>
  </section>

<?php if ($c['atelier'] !== ''): ?>
  <div class="atelier-pie" aria-hidden="true"></div>
<?php elseif ($c['decoracion'] === 'eucalipto'): ?>
  <div class="deco-pie" aria-hidden="true"><img src="<?= h($A) ?>img/deco/eucalipto-pie.webp" alt="" width="900" height="170"></div>
<?php elseif ($c['decoracion'] === 'flores'): ?>
  <div class="deco-pie" aria-hidden="true"><img src="<?= h($A) ?>img/deco/flores-pie.webp" alt="" width="900" height="170"></div>
<?php endif; ?>
</main>
<?php
    return (string) ob_get_clean();
}

/**
 * Muestras de los colores del dress code (8-oct-2026): '' si no hay, y entonces el HTML no cambia ni un byte.
 * Los valores ya vienen normalizados (norm_colores: #rrggbb en minúsculas); aun así se vuelven a comprobar y pasan
 * por h(), porque acaban en un atributo style. Mismo marcado en web, vista previa y ZIP, en todos los diseños.
 */
function muestras_color(array $colores, string $clase = ''): string {
    $o = '';
    foreach ($colores as $v) {
        if (es_color_hex($v)) $o .= '<span class="muestra" style="background-color:' . h(strtolower($v)) . '"></span>';
    }
    return $o === '' ? '' : '<span class="muestras' . ($clase !== '' ? ' ' . h($clase) : '') . '" role="img" aria-label="Colores del dress code">' . $o . '</span>';
}

/** Primera frase de un texto, para las tarjetas de la portada. */
function resumen(string $t, int $max = 140): string {
    $t = trim((string) preg_replace('/\s+/', ' ', $t));
    if ($t === '') return '';
    if (preg_match('/^(.{20,}?[.!?])(\s|$)/u', $t, $m)) $t = $m[1];
    return mb_strimwidth($t, 0, $max, '…', 'UTF-8');
}

/**
 * Paradas del día ordenadas por hora: ceremonia, convite y los momentos de `programa`.
 * Sin $completa (programa del Esencial) solo entran las que tienen hora. Con $completa (programa del día de Cítricos;
 * nació con la curva de «Limonar», revisión previa #231) la ceremonia sin hora va la primera y el convite sin hora
 * justo detrás.
 */
function paradas_dia(array $c, bool $completa = false): array {
    $ev = [];
    foreach (['ceremonia' => 'Ceremonia', 'convite' => 'Convite'] as $k => $rot) {
        $e = $c[$k];
        if ($k === 'convite' && $e['lugar'] === '') continue;
        if ($e['hora'] === '' && (!$completa || ($k === 'ceremonia' && $e['lugar'] === ''))) continue;
        // sin hora: la ceremonia, la primera (''); el convite, justo detrás de la ceremonia ('!' va antes que cualquier hora)
        $orden = $e['hora'] !== '' ? $e['hora'] : ($k === 'ceremonia' ? '' : ($c['ceremonia']['hora'] !== '' ? $c['ceremonia']['hora'] . '~' : '!'));
        $ev[] = ['hora' => $e['hora'], 'titulo' => $rot, 'lugar' => $e['lugar'], 'nota' => '', 'tipo' => $k, 'orden' => $orden,
            'clase' => $k === 'ceremonia' ? ' prog-ceremonia' : ''];
    }
    foreach ($c['programa'] as $m) $ev[] = $m + ['clase' => '', 'tipo' => 'momento', 'orden' => $m['hora']];
    usort($ev, fn($a, $b) => $a['orden'] <=> $b['orden']);   // PHP 8: estable, a igual hora manda el orden de inserción
    return $ev;
}

// ============================================================ Cítricos y azahar · piel «Terraza» (owner, 14-oct-2026)
// Sustituye a «Limonar» (curva del itinerario, calendario del mes, fichas de papel y sello circular). Calcada de la
// referencia que eligió el owner y pasada por las leyes de la fábrica Atelier (sin sombras, radio 0 o pastilla, peso
// hasta 500, el oro nunca como texto, contraste 4,5). Ningún otro diseño pasa por estas funciones.

/** Iconos de línea del programa del día y de los lugares, en una caja de 40: boda.css los pinta en el oro (son dibujo,
 *  no texto). Un momento del programa lleva el que dice su título (hito_icono). */
const HITOS_CITRICOS = [
    'anillos' => '<circle cx="15" cy="24" r="8.5"/><circle cx="25" cy="24" r="8.5"/><path d="M25 9.5l3 3-3 3-3-3z"/>',
    'campana' => '<path d="M8.5 27c0-7.5 5.1-12.5 11.5-12.5S31.5 19.5 31.5 27M5.5 27.5h29M9 31h22M13 22c1-2.5 3-4 5.5-4.6"/><circle cx="20" cy="12.3" r="1.8"/>',
    'camara' => '<rect x="6.5" y="13" width="27" height="18" rx="3"/><path d="M14 13l2.2-3.5h7.6L26 13M28.5 17H30"/><circle cx="20" cy="22" r="5.5"/><circle cx="20" cy="22" r="2.4"/>',
    'tarta' => '<path d="M9 25h22v8H9zM12 18h16v7H12zM15 12.5h10V18H15zM9 28.5c2.75 2 5.25 2 8 0s5.25-2 8 0c2 1.4 4 1.4 6 0M20 12.5V9M20 9c-1-1-1-2.4 0-3.4 1 1 1 2.4 0 3.4z"/>',
    'musica' => '<path d="M15 28.5v-17l14-3v17M15 15l14-3"/><ellipse class="cit-lleno" cx="12" cy="28.5" rx="3.4" ry="2.6" transform="rotate(-20 12 28.5)"/><ellipse class="cit-lleno" cx="26" cy="25.5" rx="3.4" ry="2.6" transform="rotate(-20 26 25.5)"/>',
    'copas' => '<path d="M11 8h7c0 7-1.5 10-3.5 10S11 15 11 8zM14.5 18v12M10.5 30h8M22 8h7c0 7-1.5 10-3.5 10S22 15 22 8zM25.5 18v12M21.5 30h8"/>',
    'corazon' => '<path d="M20 31s-10-6.5-10-14a5.5 5.5 0 0 1 10-3.2A5.5 5.5 0 0 1 30 17c0 7.5-10 14-10 14z"/>',
];

/** Qué icono lleva una parada del día: la ceremonia, los anillos; el convite, la campana; un momento, el de su título
 *  (fotos, tarta, baile, aperitivo, cena) y, si no dice nada de eso, un corazón. */
function hito_icono(array $m): string {
    if ($m['tipo'] === 'ceremonia') return 'anillos';
    if ($m['tipo'] === 'convite') return 'campana';
    $t = mb_strtolower($m['titulo'], 'UTF-8');
    foreach (['camara' => '~foto|sesi[oó]n|retrato~u', 'tarta' => '~tarta|pastel|postre~u', 'musica' => '~bail|fiesta|m[uú]sica|\bdj\b|concierto|barra libre~u',
        'copas' => '~aperitivo|c[oó]ctel|cocktail|brindis|vino|copa~u', 'campana' => '~cena|comida|banquete|almuerzo|men[uú]~u'] as $k => $re) {
        if (preg_match($re, $t)) return $k;
    }
    return 'corazon';
}
function svg_hito(string $k): string {
    return '<svg class="cit-ico" viewBox="0 0 40 40" aria-hidden="true">' . HITOS_CITRICOS[$k] . '</svg>';
}

/** Iniciales del monograma de la cabecera (dentro de la corona de laurel): una por nombre; «♥» si no hay ninguno. */
function mono_citricos(array $c): string {
    $o = '';
    foreach ([$c['pareja']['nombre1'], $c['pareja']['nombre2']] as $n) {
        if ($n !== '') $o .= '<b>' . h(mb_strtoupper(mb_substr($n, 0, 1, 'UTF-8'), 'UTF-8')) . '</b>';
    }
    return $o !== '' ? $o : '<b>♥</b>';
}

/** Nombres de la portada: el primero en su línea y el conector con el segundo en la siguiente («& Marcos»), con el
 *  conector en <i> (el bronce). Así el «&» nunca comparte línea con el primer nombre. Sin los dos nombres, como el resto. */
function nombres_citricos(array $c): string {
    $a = $c['pareja']['nombre1'];
    $b = $c['pareja']['nombre2'];
    $nom = nombres($c);
    if ($a === '' || $b === '') return h($nom ?: 'Vuestros nombres');
    $sep = trim(substr($nom, strlen($a), strlen($nom) - strlen($a) - strlen($b)));
    return h($a) . ' <span class="hero-n2"><i>' . h($sep) . '</i> ' . h($b) . '</span>';
}

/**
 * Portada de Cítricos: cabecera con monograma en laurel (layout), portada a dos columnas con la terraza sobre el mar y
 * el borde rasgado, programa del día con iconos, ceremonia y convite, la carta con la foto en instantánea, la cuenta
 * atrás, el dress code y los detalles en fichas, y la banda de confirmar con la rama de limonero. Mismos datos y mismos
 * enganches que la portada común (#countdown, #calBtn, #calOptions, img[data-foto], .rv, data-ir).
 */
function inicio_citricos(array $c, array $ctx): string {
    $A = h($ctx['assets']);
    $nom = nombres($c);
    $po = $c['portada'];
    $rsvp = seccion_tipo($c, 'rsvp');
    $dc = seccion_tipo($c, 'dresscode');
    $hayFoto = $c['foto'] && ($ctx['foto'] !== '' || $ctx['modo'] === 'preview');
    $fecha = $c['fecha'] !== '' ? DateTimeImmutable::createFromFormat('!Y-m-d', $c['fecha']) : false;
    $verMas = fn(array $s): string => a_interno($s['ruta'], $ctx, 'class="link-arrow"') . 'Ver ' . h(mb_strtolower($s['titulo'], 'UTF-8')) . ' ' . ico(ICONOS['flecha'], 'ico ico-sm') . '</a>';

    // 1. Portada: los nombres y la fecha a la izquierda, la terraza a la derecha (debajo, en el móvil)
    $o = "<main class=\"home\">\n  <section class=\"hero\">\n"
        . '    <div class="hero-left">' . arte_atelier($c, $ctx['assets'])
        . '<div class="hero-text"><h1 class="hero-names" style="--nl:' . mb_strlen($nom ?: 'Vuestros nombres', 'UTF-8') . '">' . nombres_citricos($c) . '</h1></div>'
        . atelier_tras_texto($c, $ctx) . "</div>\n"
        . '    <div class="cit-escena" aria-hidden="true"><img class="cit-terraza" src="' . $A . 'img/atelier/terraza-citricos.svg" alt=""></div>' . "\n"
        . "  </section>\n";

    // 2. Programa del día: ceremonia, convite y los momentos de la pareja, por hora, cada uno con su icono
    $ev = paradas_dia($c, true);
    if ($ev) {
        $o .= '  <section class="cit-dia" aria-labelledby="cit-dia-t"><h2 class="cit-titulo" id="cit-dia-t">Programa del día</h2><ol class="cit-hitos">';
        foreach ($ev as $m) {
            $o .= '<li class="cit-hito rv">' . svg_hito(hito_icono($m))
                . ($m['hora'] !== '' ? '<span class="cit-h">' . h($m['hora']) . '</span>' : '')
                . '<h3>' . h($m['titulo']) . '</h3>'
                . ($m['lugar'] !== '' ? '<p class="cit-l">' . h($m['lugar']) . '</p>' : '')
                . ($m['nota'] !== '' ? '<p class="cit-n">' . h($m['nota']) . '</p>' : '')
                . '</li>';
        }
        $o .= "</ol></section>\n";
    }

    // 3. Ceremonia y convite: dónde, la dirección y cómo llegar; entre los dos, el sello de limones. Debajo, el mapa
    $sitios = [];
    foreach (['ceremonia' => 'Ceremonia', 'convite' => 'Convite'] as $k => $rot) {
        $e = $c[$k];
        if ($k === 'convite' && $e['lugar'] === '') continue;
        $sitios[] = '<article class="cit-sitio rv"><span class="cit-sitio-ico">' . svg_hito($k === 'ceremonia' ? 'anillos' : 'campana') . '</span>'
            . '<h3>' . $rot . '</h3>'
            . ($e['hora'] !== '' ? '<p class="cit-h">' . h($e['hora']) . '</p>' : '')
            . '<p class="cit-sitio-l">' . h($e['lugar'] ?: 'Lugar de la ' . strtolower($rot)) . '</p>'
            . ($e['direccion'] !== '' ? '<p class="cit-sitio-d">' . h($e['direccion']) . '</p>' : '')
            . ($e['lugar'] !== '' ? '<a class="cit-mapa" href="https://maps.google.com/?q=' . h(rawurlencode(mapa_q($e))) . '" target="_blank" rel="noopener noreferrer">Cómo llegar</a>' : '')
            . '</article>';
    }
    $o .= '  <section class="cit-lugares" aria-labelledby="cit-lugares-t"><h2 class="cit-titulo" id="cit-lugares-t">' . (count($sitios) > 1 ? 'Ceremonia y convite' : 'Ceremonia') . '</h2>'
        . '<div class="cit-sitios">' . implode(count($sitios) > 1 ? '<img class="cit-sello" src="' . $A . 'img/atelier/sello-limon.webp" alt="" aria-hidden="true">' : '', $sitios) . '</div>'
        . bloque_mapa($c, $ctx) . "</section>\n";

    // 4. La carta: la foto de la pareja en instantánea con su cinta y su ramita de azahar, y la bienvenida al lado.
    //    Con la ficha del dress code, la vestimenta va allí (no se duplica)
    $vest = $po['vestimenta'] !== '' && !$dc;
    if ($hayFoto || $po['titulo'] !== '' || $po['frase'] !== '' || $po['texto'] !== '' || $vest) {
        $o .= '  <section class="cit-carta rv">'
            . ($hayFoto ? '<figure class="cit-instantanea"><div class="cit-foto"><img data-foto src="' . h($ctx['foto']) . '" alt="' . h($nom) . '" width="960" height="1280" loading="eager"></div>'
                . '<img class="cit-azahar" src="' . $A . 'img/atelier/azahar-citricos.svg" alt="" aria-hidden="true"></figure>' : '')
            . '<div class="cit-carta-txt">'
            . ($po['titulo'] !== '' ? '<h2 class="cit-guion">' . h($po['titulo']) . '</h2>' : '')
            . ($po['frase'] !== '' ? '<p class="big">' . h($po['frase']) . '</p>' : '')
            . parrafos($po['texto'])
            . ($vest ? '<p class="pildora-vest"><span class="pildora-vest-rot">Vestimenta</span><span class="pildora-vest-txt">' . h($po['vestimenta']) . '</span></p>' : '')
            . "</div></section>\n";
    }

    // 5. Guardad la fecha: cuenta atrás en fichas con corazones entre las cifras, y el calendario
    if ($fecha) {
        $o .= "  <section class=\"save-band\">\n    <div class=\"save-card rv\">"
            . '<span class="kicker">' . h(frase_web($c, 'fecha_kicker', 'Guardad el momento')) . '</span>'
            . '<h2 class="cit-titulo">' . h(frase_web($c, 'fecha_titulo', 'Anotad la fecha')) . '</h2>'
            . '<p class="save-date-text">' . h(ucfirst(fecha_larga($c['fecha']))) . '</p>'
            . '<div class="countdown" id="countdown" data-objetivo="' . h($c['fecha'] . 'T' . ($c['ceremonia']['hora'] ?: '12:00') . ':00') . '" aria-label="Cuenta atrás para la boda">'
            . '<div class="countdown-item"><b data-c="days">0</b><span>Días</span></div><div class="countdown-item"><b data-c="hours">0</b><span>Horas</span></div>'
            . '<div class="countdown-item"><b data-c="mins">0</b><span>Min</span></div><div class="countdown-item"><b data-c="secs">0</b><span>Seg</span></div></div>'
            . '<button class="btn cal-btn" type="button" id="calBtn" aria-expanded="false" aria-controls="calOptions">' . ico(ICONOS['calendario'], 'ico ico-sm') . 'Añadir a mi calendario</button>'
            . '<div class="cal-options" id="calOptions" hidden><span class="kicker">Elige tu calendario</span>'
            . '<a href="' . h(url_google_calendar($c)) . '" target="_blank" rel="noopener noreferrer"><span>' . ico(ICONOS['calendario']) . 'Google Calendar</span>' . ico(ICONOS['flecha'], 'ico ico-sm') . '</a>'
            . '<a href="' . h($ctx['modo'] === 'zip' ? 'boda.ics' : ($ctx['modo'] === 'preview' ? '#' : '/boda.ics')) . '" download><span>' . ico('M6 2h12v20H6zM11 18h2') . 'Apple / Outlook (.ics)</span>' . ico('M12 4v11M7 10l5 5 5-5M5 20h14', 'ico ico-sm') . '</a>'
            . "</div></div>\n  </section>\n";
    }

    // 6. Fichas: el dress code en papel limón, con el bodegón de limones al pie, y en «Detalles» el resto de secciones
    //    en filas con filete (los accesos de los seis Atelier). La confirmación tiene su banda propia, debajo
    $bodegon = '<img class="cit-bodegon" src="' . $A . 'img/atelier/bodegon-citricos.svg" alt="" aria-hidden="true">';
    $vestir = '';
    if ($dc) {
        $txt = mb_strimwidth(trim((string) preg_replace('/\s+/', ' ', $dc['datos']['texto'] ?? '')), 0, 240, '…', 'UTF-8');
        $vestir = '<div class="cit-vestir rv"><h2 class="cit-titulo">' . h($dc['titulo']) . '</h2>'
            . ($po['vestimenta'] !== '' ? '<p class="cit-vestir-dicho">' . h($po['vestimenta']) . '</p>' : '')
            . ($txt !== '' ? '<p>' . h($txt) . '</p>' : '')
            . muestras_color($dc['datos']['colores'] ?? [], 'cit-muestras') . $verMas($dc) . $bodegon . '</div>';
    }
    $filas = '';
    foreach ($c['secciones'] as $s) {
        if (!$s['on'] || in_array($s['tipo'], ['rsvp', 'informacion', 'dresscode'], true)) continue;
        $resumen = resumen($s['datos']['texto'] ?? '');
        if ($resumen === '' && $s['tipo'] === 'transporte' && $s['datos']['trayectos']) {
            $t0 = $s['datos']['trayectos'][0];
            $resumen = resumen('Autobús' . ($t0['hora'] !== '' ? ' a las ' . $t0['hora'] : '') . ' desde ' . $t0['salida'] . '.');
        }
        $kicker = $s['tipo'] === 'musica' ? frase_web($c, 'cancion_kicker', '¿Qué canción no puede faltar?')
            : (['hoteles' => 'Alojamiento', 'transporte' => 'Desplazamiento', 'regalos' => 'Detalle con nosotros'][$s['tipo']] ?? 'Más información');
        $filas .= '<div class="q-card rv"><div class="q-label">' . ico(ICONOS[$s['tipo']]) . '<span class="kicker">' . h($kicker) . '</span></div>'
            . '<h3>' . h($s['titulo']) . '</h3>' . ($resumen !== '' ? '<p>' . h($resumen) . '</p>' : '') . $verMas($s) . '</div>';
    }
    if ($vestir !== '' || $filas !== '') {
        $o .= '  <section class="quick cit-fichas">' . $vestir
            . ($filas !== '' ? '<div class="cit-detalles"><h2 class="cit-titulo">Detalles</h2><div class="quick-grid">' . $filas . '</div>'
                . ($vestir === '' ? $bodegon : '') . '</div>' : '')
            . "</section>\n";
    }

    // 7. Confirmar: banda de salvia con colinas en acuarela y la rama de limonero en la esquina
    if ($rsvp) {
        $dr = $rsvp['datos'];
        $txt = $dr['fecha_limite'] !== '' ? 'Por favor, confirmad antes del ' . fecha_larga($dr['fecha_limite'], false) . '.' : (resumen($dr['texto'] ?? '') ?: 'Contadnos si venís y qué menú preferís.');
        $o .= '  <section class="cit-rsvp" aria-labelledby="cit-rsvp-t"><img class="cit-rama-pie" src="' . $A . 'img/atelier/limones.webp" alt="" aria-hidden="true">'
            . '<div class="cit-rsvp-in rv"><h2 class="cit-titulo" id="cit-rsvp-t">' . h($rsvp['titulo']) . '</h2><p>' . h($txt) . '</p>'
            . a_interno($rsvp['ruta'], $ctx, 'class="btn"') . "Confirmar</a></div></section>\n";
    }
    return $o . "</main>\n";
}

/**
 * Programa del día: la línea de tiempo de la jornada. Junta la ceremonia y el convite (los que tienen hora) con los momentos
 * extra de la pareja, por hora; las tarjetas de arriba no cambian. Solo la ceremonia lleva el acento.
 */
function bloque_programa(array $c): string {
    $ev = paradas_dia($c);
    $o = '<div class="prog rv"><span class="kicker prog-kicker">El programa del día</span><ol class="prog-lista">';
    foreach ($ev as $m) {
        $o .= '<li class="prog-item' . $m['clase'] . '"><span class="prog-hora">' . h($m['hora']) . '</span><span class="prog-marca" aria-hidden="true"></span><div class="prog-cuerpo"><h3 class="prog-titulo">' . h($m['titulo']) . '</h3>'
            . ($m['lugar'] !== '' ? '<p class="prog-lugar">' . h($m['lugar']) . '</p>' : '')
            . ($m['nota'] !== '' ? '<p class="prog-nota">' . h($m['nota']) . '</p>' : '') . '</div></li>';
    }
    return $o . '</ol></div>';
}

function url_google_calendar(array $c): string {
    if ($c['fecha'] === '') return '#';
    $d = str_replace('-', '', $c['fecha']);
    $fin = date('Ymd', strtotime($c['fecha'] . ' +1 day'));
    $det = [];
    foreach (['ceremonia' => 'Ceremonia', 'convite' => 'Convite'] as $k => $r) {
        if ($c[$k]['lugar'] !== '') $det[] = $r . ($c[$k]['hora'] !== '' ? ' a las ' . $c[$k]['hora'] : '') . ' en ' . $c[$k]['lugar'] . '.';
    }
    return 'https://calendar.google.com/calendar/render?' . http_build_query([
        'action' => 'TEMPLATE', 'text' => 'Boda de ' . nombres($c, ' y '), 'dates' => $d . '/' . $fin,
        'details' => implode(' ', $det), 'location' => mapa_q($c['ceremonia']),
    ]);
}

/** .ics de día completo: no hay hora de fin real (mismo criterio que EduCora, 24-sep). */
function ics(array $c): string {
    $esc = fn($s) => str_replace(["\\", ';', ',', "\n"], ["\\\\", '\;', '\,', '\n'], $s);
    $d = str_replace('-', '', $c['fecha']);
    $fin = date('Ymd', strtotime($c['fecha'] . ' +1 day'));
    $det = [];
    foreach (['ceremonia' => 'Ceremonia', 'convite' => 'Convite'] as $k => $r) {
        if ($c[$k]['lugar'] !== '') $det[] = $r . ($c[$k]['hora'] !== '' ? ' a las ' . $c[$k]['hora'] : '') . ' en ' . $c[$k]['lugar'] . '.';
    }
    return "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//AxisWorks//Bodas//ES\r\nBEGIN:VEVENT\r\n"
        . 'UID:' . $d . '-' . md5(nombres($c)) . "@axisworks.studio\r\n"
        . 'DTSTAMP:' . gmdate('Ymd\THis\Z') . "\r\n"
        . 'DTSTART;VALUE=DATE:' . $d . "\r\nDTEND;VALUE=DATE:" . $fin . "\r\n"
        . 'SUMMARY:' . $esc('Boda de ' . nombres($c, ' y ')) . "\r\n"
        . 'LOCATION:' . $esc(mapa_q($c['ceremonia'])) . "\r\n"
        . 'DESCRIPTION:' . $esc(implode(' ', $det)) . "\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";
}

function envoltorio(string $titulo, string $dentro, string $estilo = '', string $antes = ''): string {
    return '<main><div class="wrap"><section class="section rv"' . ($estilo !== '' ? ' style="' . $estilo . '"' : '') . '>' . $antes . '<h1>' . h($titulo) . '</h1><hr class="divider">' . $dentro . '</section></div></main>';
}

function pagina_seccion(array $c, array $s, array $ctx): string {
    $d = $s['datos'];
    $intro = parrafos($d['texto'] ?? '', 'lede');
    switch ($s['tipo']) {
        case 'rsvp':
            // Enlace de grupo: saludo con el nombre que puso la pareja en su lista (texto plano, por h())
            $saludo = isset($ctx['grupo']) ? '<p class="lede saludo-grupo"><strong>¡Hola, ' . h($ctx['grupo']['nombre']) . '!</strong></p>' : '';
            return envoltorio($s['titulo'], $saludo . $intro . bloque_banquete((string) ($d['banquete'] ?? '')) . form_rsvp($c, $s, $ctx));
        case 'informacion': return envoltorio($s['titulo'], $intro . bloque_mapa($c, $ctx) . pagina_informacion($c) . tarjeta_menu($c));
        case 'transporte': return envoltorio($s['titulo'], $intro . bloque_transporte($c, $s, $ctx));
        case 'hoteles': return envoltorio($s['titulo'], $intro . lista_hoteles($d));
        case 'regalos': return envoltorio($s['titulo'], $intro . bloque_regalos($c, $d));
        case 'musica': return envoltorio($s['titulo'], $intro . bloque_musica($ctx));
        case 'galeria': return envoltorio($s['titulo'], $intro . bloque_galeria($s, $ctx), 'max-width:1000px;margin:0 auto;');
        case 'libro': return envoltorio($s['titulo'], $intro . bloque_libro($c, $s, $ctx));
        // Kicker (los nombres) sobre el título y párrafos: cada párrafo pasa por h() en parrafos()
        case 'historia': return envoltorio($s['titulo'], '<div class="historia-texto">' . $intro . '</div>', 'max-width:640px;margin:0 auto;', '<span class="kicker historia-kicker">' . h(nombres($c) ?: 'Nuestra boda') . '</span>');
        case 'dresscode':
            // Con colores, las muestras van tras el texto (y sustituyen al «Pronto os contamos más» si no hay texto)
            $mu = muestras_color($d['colores'] ?? [], 'muestras-pag');
            return envoltorio($s['titulo'], ($intro ?: ($mu === '' ? '<p class="lede">Pronto os contamos más.</p>' : '')) . $mu, 'max-width:560px;margin:0 auto;');
        default: return envoltorio($s['titulo'], $intro ?: '<p class="lede">Pronto os contamos más.</p>');
    }
}

function pagina_informacion(array $c): string {
    $o = '';
    $tabs = [];
    foreach (['ceremonia' => 'Ceremonia', 'convite' => 'Banquete'] as $k => $r) {
        if ($c[$k]['lugar'] !== '') $tabs[$k] = $r;
    }
    if (!$tabs) return '<div class="pending-note">Pronto os contamos dónde será.</div>';
    // Mismo sitio: una sola ficha con las dos horas
    if (!empty($c['convite']['mismo'])) {
        $e = $c['ceremonia'];
        $horas = implode(' · ', array_filter([$e['hora'] !== '' ? 'Ceremonia ' . $e['hora'] : '', $c['convite']['hora'] !== '' ? 'Banquete ' . $c['convite']['hora'] : '']));
        return '<div class="info-title">CEREMONIA Y BANQUETE</div>'
            . '<p class="info-text"><b>' . h($e['lugar']) . '</b>' . ($e['direccion'] !== '' ? '<br>' . h($e['direccion']) : '')
            . ($c['fecha'] !== '' ? '<br>' . h(fecha_larga($c['fecha'], false)) : '') . ($horas !== '' ? '<br>' . h($horas) : '') . '</p>'
            . '<p class="info-mapa"><a class="btn btn-soft" href="https://maps.google.com/?q=' . h(rawurlencode(mapa_q($e))) . '" target="_blank" rel="noopener noreferrer">'
            . ico(ICONOS['mapa'], 'ico ico-sm') . 'Ver en el mapa</a></p>';
    }
    $o .= '<div data-tabs>';
    if (count($tabs) > 1) {
        $o .= '<div class="tabs" role="tablist">';
        $first = true;
        foreach ($tabs as $k => $r) {
            $o .= '<button class="tab-btn" type="button" role="tab" aria-selected="' . ($first ? 'true' : 'false') . '" data-target="tab-' . $k . '">' . h($r) . '</button>';
            $first = false;
        }
        $o .= '</div>';
    }
    $first = true;
    foreach ($tabs as $k => $r) {
        $e = $c[$k];
        $cuando = trim(($c['fecha'] !== '' ? fecha_larga($c['fecha'], false) : '') . ($e['hora'] !== '' ? ' — ' . $e['hora'] : ''), ' —');
        $o .= '<div id="tab-' . $k . '" class="tab-panel' . ($first ? ' active' : '') . '" role="tabpanel">'
            . '<div class="info-title">' . h(mb_strtoupper($r, 'UTF-8')) . '</div>'
            . '<p class="info-text"><b>' . h($e['lugar']) . '</b>'
            . ($e['direccion'] !== '' ? '<br>' . h($e['direccion']) : '')
            . ($cuando !== '' ? '<br>' . h($cuando) : '') . '</p>'
            // Enlace a Google Maps en vez de mapa incrustado: el iframe carga cookies de
            // Google y los textos legales prometen una web sin terceros (Legal, 25-sep)
            . '<p class="info-mapa"><a class="btn btn-soft" href="https://maps.google.com/?q=' . h(rawurlencode(mapa_q($e))) . '" target="_blank" rel="noopener noreferrer">'
            . ico(ICONOS['mapa'], 'ico ico-sm') . 'Ver en el mapa</a></p></div>';
        $first = false;
    }
    return $o . '</div>';
}

/**
 * Menú del banquete (lo que se va a servir), en la página de la confirmación y en el ZIP. Una línea
 * «Momento: platos» sale con el momento de título; cualquier otra, como párrafo. Todo pasa por h().
 */
function bloque_banquete(string $t): string {
    $lineas = array_slice(array_values(array_filter(array_map('trim', explode("\n", $t)), fn($l) => $l !== '')), 0, 30);
    if (!$lineas) return '';
    $o = '<div class="menu-card banquete"><div class="info-title">EL BANQUETE</div>';
    foreach ($lineas as $l) {
        if (preg_match('/^([^:]{1,40}):\s*(.+)$/u', $l, $m)) $o .= '<div class="menu-item"><h3>' . h(trim($m[1])) . '</h3><p>' . h(trim($m[2])) . '</p></div>';
        else $o .= '<div class="menu-item"><p>' . h($l) . '</p></div>';
    }
    return $o . '</div>';
}

/** «El menú»: solo si algún menú lleva descripción (si no, repetiría los nombres del formulario). */
function tarjeta_menu(array $c): string {
    $ms = array_filter(menus_de($c), fn($m) => $m['nombre'] !== '');
    if (!array_filter($ms, fn($m) => $m['descripcion'] !== '')) return '';
    $o = '<div class="menu-card"><div class="info-title">EL MENÚ</div>';
    foreach ($ms as $m) {
        $o .= '<div class="menu-item"><h3>' . h($m['nombre']) . ($m['infantil'] ? ' <span class="menu-tag">niños</span>' : '') . '</h3>'
            . ($m['descripcion'] !== '' ? parrafos($m['descripcion']) : '') . '</div>';
    }
    return $o . '</div>';
}

function bloque_transporte(array $c, array $s, array $ctx): string {
    $d = $s['datos'];
    $o = '';
    if ($d['trayectos']) {
        $o .= '<div class="trayectos">';
        foreach ($d['trayectos'] as $t) {
            $o .= '<article class="trayecto">'
                . '<div class="trayecto-cab">' . ($t['hora'] !== '' ? '<span class="trayecto-hora">' . h($t['hora']) . '</span>' : '')
                . ($t['titulo'] !== '' ? '<h3>' . h($t['titulo']) . '</h3>' : '') . '</div>'
                . '<ol class="trayecto-ruta"><li><span class="kicker">Salida</span>' . h($t['salida']) . '</li>'
                . ($t['llegada'] !== '' ? '<li><span class="kicker">Llegada</span>' . h($t['llegada']) . '</li>' : '') . '</ol>'
                . ($t['nota'] !== '' ? parrafos($t['nota'], 'trayecto-nota') : '')
                . '<a class="btn btn-soft" href="https://maps.google.com/?q=' . h(rawurlencode($t['salida'])) . '" target="_blank" rel="noopener noreferrer">'
                . ico(ICONOS['mapa'], 'ico ico-sm') . 'Ver la salida en el mapa</a></article>';
        }
        $o .= '</div>';
    } elseif ($d['texto'] === '') {
        $o .= '<div class="pending-note">Horarios y paradas: os los contamos pronto.</div>';
    }
    $rsvp = seccion_tipo($c, 'rsvp');
    if (!empty($d['preguntar']) && $rsvp) {
        $o .= '<p class="lede trayecto-aviso">¿Necesitáis autobús? Decídnoslo al ' . a_interno($rsvp['ruta'], $ctx) . h(mb_strtolower($rsvp['titulo'], 'UTF-8')) . '</a>.</p>';
    }
    return $o;
}

function lista_hoteles(array $d): string {
    if (!$d['hoteles']) return '<div class="pending-note">Estamos cerrando los alojamientos. En cuanto los tengamos, los veréis aquí.</div>';
    $o = '';
    foreach ($d['hoteles'] as $ho) {
        $o .= '<div class="hotel-card"><h3>' . h($ho['nombre']) . '</h3>';
        if ($ho['zona'] !== '') $o .= '<p class="hotel-zona">' . h($ho['zona']) . '</p>';
        if ($ho['nota'] !== '') $o .= parrafos($ho['nota']);
        $links = [];
        if ($ho['web'] !== '') $links[] = '<a class="btn btn-soft" href="' . h($ho['web']) . '" target="_blank" rel="noopener noreferrer nofollow">Ver web</a>';
        if ($ho['telefono'] !== '') $links[] = '<a class="btn btn-soft" href="tel:' . h(preg_replace('/[^0-9+]/', '', $ho['telefono'])) . '">' . h($ho['telefono']) . '</a>';
        if ($links) $o .= '<p class="hotel-links">' . implode(' ', $links) . '</p>';
        $o .= '</div>';
    }
    return $o;
}

function bloque_regalos(array $c, array $d): string {
    $o = '';
    if ($d['iban'] !== '') {
        $o .= '<div class="iban-box">';
        if ($d['titular'] !== '') $o .= '<p><b>Titular:</b> ' . h($d['titular']) . '</p>';
        $o .= '<p class="iban"><b>IBAN:</b> <span class="iban-num">' . h($d['iban']) . '</span></p>'
            . '<button type="button" class="btn btn-soft copy-btn" data-copiar="' . h(str_replace(' ', '', $d['iban'])) . '">Copiar IBAN</button></div>';
    }
    if ($d['otro'] !== '') $o .= parrafos($d['otro'], 'lede');
    $o .= '<p class="thanks-script">Muchísimas gracias</p>';
    return $o;
}

function bloque_musica(array $ctx): string {
    if ($ctx['modo'] === 'zip') return aviso_alojada($ctx, 'Proponed y votad canciones en nuestra web');
    return '<form id="musicForm" class="stack" novalidate>'
        . '<input type="text" name="web" tabindex="-1" autocomplete="off" class="hp" aria-hidden="true">'
        . '<div class="field"><label for="musicArtista">Artista</label><input type="text" id="musicArtista" name="artista" maxlength="120" required></div>'
        . '<div class="field"><label for="musicCancion">Canción</label><input type="text" id="musicCancion" name="cancion" maxlength="120" required></div>'
        . '<div class="form-actions"><button type="submit" class="btn">Añadir canción</button></div>'
        . '<div class="form-msg" role="alert" id="musicAddMsg"></div></form>'
        . '<p class="lede" style="margin-top:var(--s5);margin-bottom:0;">Esta es la lista hasta el momento:</p>'
        . '<div class="song-list" id="songList"><p class="song-empty" id="songListEmpty">Aún no hay canciones. ¿Rompes el hielo?</p></div>';
}

/** URL de una foto de galería o libro según el modo (en el ZIP van en carpeta propia; en la vista previa del panel, firmadas). */
function src_privada(string $tipo, string $id, array $ctx): string {
    if ($ctx['modo'] === 'zip') return 'galeria/' . $id . '.webp';
    $u = '/' . $tipo . '/' . $id . '.webp';
    if (!empty($ctx['firma_slug'])) $u .= '?t=' . firma_img($ctx['firma_slug'], $id);
    return $u;
}

function bloque_galeria(array $s, array $ctx): string {
    $fotos = $s['datos']['fotos'];
    if (!$fotos) {
        return '<div class="pending-note">' . ($ctx['modo'] === 'preview' && empty($ctx['firma_slug'])
            ? 'Las fotos de la galería se suben desde vuestro panel en cuanto publiquéis la web.'
            : 'Pronto compartiremos aquí nuestras fotos.') . '</div>';
    }
    $o = '<div class="galeria">';
    foreach ($fotos as $f) {
        $src = src_privada('g', $f['id'], $ctx);
        $o .= '<figure class="galeria-foto"><a href="' . h($src) . '" target="_blank" rel="noopener"><img src="' . h($src) . '" alt="' . h($f['pie']) . '" loading="lazy"></a>'
            . ($f['pie'] !== '' ? '<figcaption>' . h($f['pie']) . '</figcaption>' : '') . '</figure>';
    }
    return $o . '</div>';
}

function bloque_libro(array $c, array $s, array $ctx): string {
    if ($ctx['modo'] === 'zip') return aviso_alojada($ctx, 'Dejad vuestro mensaje en el libro de invitados de nuestra web');
    $L = textos_legales();
    $capa1 = strtr((string) ($L['libro_capa1'] ?? ''), [
        '{pareja}' => nombres($c, ' y '), '{email}' => $c['pareja']['email'],
        '{borrado}' => $c['fecha'] !== '' ? fecha_larga(fecha_borrado($c['fecha']), false) : '',
    ]);
    $frase = 'Más información en el aviso de privacidad.';
    $capa1 = trim(str_replace($frase, '', $capa1));
    $fotos = !empty($s['datos']['fotos']);
    $retirar = 'mailto:' . rawurlencode($c['pareja']['email']) . '?cc=' . rawurlencode(empresa()['email'])
        . '&subject=' . rawurlencode('Retirar un mensaje o una foto del libro de invitados');
    ob_start(); ?>
<form id="libroForm" class="stack libro-form" novalidate enctype="multipart/form-data">
  <input type="text" name="web" tabindex="-1" autocomplete="off" class="hp" aria-hidden="true">
  <div class="field"><label for="libroNombre">Tu nombre</label><input type="text" id="libroNombre" name="nombre" maxlength="80" required></div>
  <div class="field"><label for="libroMensaje">Tu mensaje</label><textarea id="libroMensaje" name="mensaje" maxlength="600" rows="4" required></textarea></div>
<?php if ($fotos): ?>
  <div class="field"><label for="libroFoto">Una foto (opcional)</label><input type="file" id="libroFoto" name="foto" accept="image/jpeg,image/png,image/webp"></div>
<?php endif; ?>
  <div class="rsvp-legal">
<?php if ($capa1 !== ''): ?>    <p class="rsvp-capa1"><?= h($capa1) ?> <?= a_interno('privacidad', $ctx) ?>Más información en el aviso de privacidad</a>.</p><?php endif; ?>
    <div class="field"><label class="check-group"><input type="checkbox" name="acepto_publicar" value="si" required> <?= h($L['check_libro_publicar'] ?? 'Entiendo que mi nombre y mi mensaje se publican en la web de la boda.') ?></label></div>
<?php if ($fotos): ?>
    <div class="field" data-si-foto hidden><label class="check-group"><input type="checkbox" name="acepto_foto" value="si"> <?= h($L['check_foto_libro'] ?? '') ?></label></div>
<?php endif; ?>
  </div>
  <div class="form-actions"><button type="submit" class="btn">Dejar mi mensaje</button></div>
  <div class="form-msg" role="alert"></div>
</form>
<div class="libro-lista">
<?php $vis = array_reverse(array_values(array_filter($ctx['libro'] ?? [], fn($e) => empty($e['oculto']))));
    if (!$vis): ?>
  <p class="song-empty">Aún no hay mensajes. ¿Escribes el primero?</p>
<?php endif; foreach ($vis as $e): ?>
  <article class="libro-entrada">
<?php if (!empty($e['foto'])): ?>    <img src="<?= h(src_privada('l', (string) $e['foto'], $ctx)) ?>" alt="Foto de <?= h($e['nombre']) ?>" loading="lazy"><?php endif; ?>
    <?= parrafos((string) $e['mensaje']) ?>
    <p class="libro-firma">— <?= h($e['nombre']) ?></p>
  </article>
<?php endforeach; ?>
</div>
<p class="libro-retirar"><a href="<?= h($retirar) ?>">Pedir que se retire un mensaje o una foto</a></p>
<?php
    return (string) ob_get_clean();
}

/** Página para escribir el código de la boda (galería y libro). */
function render_codigo(array $c, array $s, array $ctx, string $aviso): string {
    $msg = ['mal' => 'Ese código no es. Lo tenéis en la invitación.', 'espera' => 'Demasiados intentos. Prueba dentro de un rato.'][$aviso] ?? '';
    $cuerpo = envoltorio($s['titulo'], '<form method="post" action="/acceso" class="stack codigo-form">'
        . '<p class="lede">Para ver ' . ($s['tipo'] === 'libro' ? 'y escribir en el libro' : 'la galería') . ', escribe el código que viene en la invitación.</p>'
        . '<input type="hidden" name="volver" value="/' . h($s['ruta']) . '">'
        . '<div class="field"><label for="codigo">Código de la boda</label><input type="text" id="codigo" name="codigo" maxlength="40" autocomplete="off" autocapitalize="off" required autofocus></div>'
        . ($msg !== '' ? '<div class="form-msg">' . h($msg) . '</div>' : '')
        . '<div class="form-actions"><button type="submit" class="btn">Entrar</button></div></form>', 'max-width:520px;margin:0 auto;');
    return layout($c, $s['ruta'], $s['titulo'], $cuerpo, $ctx);
}

function aviso_alojada(array $ctx, string $txt): string {
    $url = url_boda($ctx['slug'], '');
    return '<div class="pending-note"><p>' . h($txt) . ':</p><p><a class="btn" href="' . h($url) . '">' . h(preg_replace('~^https?://~', '', rtrim($url, '/'))) . '</a></p></div>';
}

function textos_legales(): array {
    static $t = null;
    if ($t === null) {
        $f = __DIR__ . '/legal/textos.php';
        $t = is_file($f) ? (array) require $f : [];
        // Único sitio donde se ponen titular, marca y vendedor: la casilla que se ve y la que se guarda
        // en el pedido salen de aquí, así que son el mismo texto (Legal, 27-sep-2026)
        $sust = ['{titular}' => empresa_publica()['titular'], '{marca}' => marca(), '{vendedor}' => (string) ($t['vendedor'] ?? '')];
        foreach ($t as $k => $v) if (is_string($v)) $t[$k] = strtr($v, $sust);
    }
    return $t;
}

function form_rsvp(array $c, array $s, array $ctx): string {
    if ($ctx['modo'] === 'zip') return aviso_alojada($ctx, 'Confirmad vuestra asistencia en nuestra web');
    $d = $s['datos'];
    $L = textos_legales();
    // Menú por defecto: el primero "para niños" para los peques, el primero que no lo es para adultos
    $ms = array_values(array_filter($d['menus'], fn($m) => $m['nombre'] !== ''));
    if (!$ms) $ms = [menu_nuevo('general', 'Menú')];
    $ninos = array_values(array_filter($ms, fn($m) => $m['infantil']));
    $adultos = array_values(array_filter($ms, fn($m) => !$m['infantil']));
    $defNino = ($ninos[0] ?? $ms[0])['id'];
    $defAdulto = ($adultos[0] ?? $ms[0])['id'];
    $menus = '';
    $menuTpl = '';
    foreach ($ms as $m) {
        $desc = $m['descripcion'] !== '' ? '<small class="menu-desc">' . h(resumen($m['descripcion'], 90)) . '</small>' : '';
        $menus .= '<label><input type="radio" data-f="menu" name="invitados[0][menu]" value="' . h($m['id']) . '"' . ($m['id'] === $defAdulto ? ' checked' : '') . '> <span>' . h($m['nombre']) . $desc . '</span></label>';
        $menuTpl .= '<label><input type="radio" data-f="menu" value="' . h($m['id']) . '"> <span>' . h($m['nombre']) . $desc . '</span></label>';
    }
    $menuField = fn($radios) => count($ms) > 1
        ? '<div class="field"><span class="field-label">Menú</span><div class="radio-group radio-menus">' . $radios . '</div></div>'
        : '<input type="hidden" data-f="menu" value="' . h($ms[0]['id']) . '">';
    $capa1 = strtr((string) ($L['rsvp_capa1'] ?? ''), [
        '{pareja}' => nombres($c, ' y '), '{email}' => $c['pareja']['email'],
        '{borrado}' => $c['fecha'] !== '' ? fecha_larga(fecha_borrado($c['fecha']), false) : '',
    ]);
    $conv = $c['convite']['lugar'] !== '';
    // Enlace de grupo (/i/<token>): los nombres de la lista de la pareja ya escritos y editables.
    // Solo esos nombres: nada de lo que el grupo haya contestado antes se enseña aquí (Legal #133).
    $grupo = $ctx['modo'] === 'live' && isset($ctx['grupo']) ? $ctx['grupo'] : null;
    $pre = $grupo ? array_values($grupo['personas']) : [];
    $extra = '';
    foreach (array_slice($pre, 1) as $n => $nombre) {
        $i = $n + 1;
        $radios = '';
        foreach ($ms as $m) {
            $desc = $m['descripcion'] !== '' ? '<small class="menu-desc">' . h(resumen($m['descripcion'], 90)) . '</small>' : '';
            $radios .= '<label><input type="radio" data-f="menu" name="invitados[' . $i . '][menu]" value="' . h($m['id']) . '"' . ($m['id'] === $defAdulto ? ' checked' : '') . '> <span>' . h($m['nombre']) . $desc . '</span></label>';
        }
        // Misma ficha que #guestTpl, ya rellena; boda.js le pone el «Quitar» y la renumera
        // La lista de la pareja no dice quién es niño: salen como adultos y «Es niño/a» lo cambia (tipo y menú)
        $extra .= '<fieldset class="guest" data-kind="adulto" data-pre><legend class="guest-head"><span class="guest-tag"></span><span class="guest-acc">'
            . '<button type="button" class="guest-remove guest-tipo" data-cambia-tipo>Es niño/a</button>'
            . '<button type="button" class="guest-remove" aria-label="Quitar a esta persona">Quitar</button></span></legend>'
            . '<input type="hidden" data-f="tipo" name="invitados[' . $i . '][tipo]" value="adulto">'
            . '<div class="field"><label for="p' . $i . '-nombre">Nombre y apellidos</label><input type="text" id="p' . $i . '-nombre" data-f="nombre" name="invitados[' . $i . '][nombre]" autocomplete="off" maxlength="120" required value="' . h($nombre) . '"></div>'
            . $menuField($radios)
            . '<div class="field"><label for="p' . $i . '-alergias">Alergias o intolerancias</label><input type="text" id="p' . $i . '-alergias" data-f="alergias" name="invitados[' . $i . '][alergias]" maxlength="300" placeholder="Déjalo en blanco si come de todo"></div></fieldset>';
    }
    ob_start(); ?>
<form id="rsvpForm" class="stack" novalidate data-menu-nino="<?= h($defNino) ?>" data-menu-adulto="<?= h($defAdulto) ?>">
  <input type="text" name="web" tabindex="-1" autocomplete="off" class="hp" aria-hidden="true">
<?php if ($grupo): // el servidor saca el grupo de este token, nunca de un campo «grupo» ?>
  <input type="hidden" name="i" value="<?= h($grupo['token']) ?>">
<?php endif; ?>
  <div class="guests-head">
    <span class="kicker">Quiénes venís</span>
    <p class="guests-hint">Uno confirma por todos: añadid a cada adulto y a cada peque.</p>
<?php if ($grupo): // un reenvío por este enlace SUSTITUYE al anterior entero: que nadie mande medio grupo para cambiar un teléfono ?>
    <p class="guests-hint">Si ya confirmasteis y queréis cambiar algo, enviad de nuevo al grupo completo, con menús y alergias: la respuesta nueva sustituye a la anterior. Este enlace es solo para vuestro grupo: no lo reenviéis a nadie más.</p>
<?php endif; ?>
  </div>
  <div class="guest-list" id="guestList">
    <fieldset class="guest" data-kind="adulto">
      <legend class="guest-head"><span class="guest-tag">Tú</span></legend>
      <input type="hidden" data-f="tipo" name="invitados[0][tipo]" value="adulto">
      <div class="field"><label for="g0-nombre">Nombre y apellidos</label><input type="text" id="g0-nombre" data-f="nombre" name="invitados[0][nombre]" autocomplete="name" maxlength="120" required<?= $pre ? ' value="' . h($pre[0]) . '"' : '' ?>></div>
      <?= $menuField($menus) ?>
      <div class="field"><label for="g0-alergias">Alergias o intolerancias</label><input type="text" id="g0-alergias" data-f="alergias" name="invitados[0][alergias]" maxlength="300" placeholder="Déjalo en blanco si comes de todo"></div>
    </fieldset>
<?= $extra ?>
  </div>
  <div class="guest-add">
    <button type="button" class="btn btn-add" data-add-guest="adulto"><span aria-hidden="true">+</span> Añadir adulto</button>
    <button type="button" class="btn btn-add" data-add-guest="nino"><span aria-hidden="true">+</span> Añadir niño/a</button>
  </div>
  <template id="guestTpl">
    <fieldset class="guest">
      <legend class="guest-head"><span class="guest-tag"></span><button type="button" class="guest-remove" aria-label="Quitar a esta persona">Quitar</button></legend>
      <input type="hidden" data-f="tipo" value="adulto">
      <div class="field"><label data-for="nombre">Nombre y apellidos</label><input type="text" data-f="nombre" data-id="nombre" autocomplete="off" maxlength="120" required></div>
      <?= $menuField($menuTpl) ?>
      <div class="field"><label data-for="alergias">Alergias o intolerancias</label><input type="text" data-f="alergias" data-id="alergias" maxlength="300" placeholder="Déjalo en blanco si come de todo"></div>
    </fieldset>
  </template>
<?php if ($d['asistencia'] && $conv): ?>
  <div class="field">
    <span class="field-label">¿A qué asistís?</span>
    <div class="check-group">
      <label><input type="checkbox" name="asiste_ceremonia" value="si" checked> Ceremonia</label>
      <label><input type="checkbox" name="asiste_banquete" value="si" checked> Banquete</label>
    </div>
<?php if ($grupo): // por este enlace, «confirmar por su cuenta» sustituiría la respuesta del grupo entero ?>
    <p class="guests-note">Vale para todo el grupo. Si alguien no va a todo, decídselo a los novios.</p>
<?php else: ?>
    <p class="guests-note">Vale para todo el grupo. Si alguien no va a todo, que confirme por su cuenta.</p>
<?php endif; ?>
  </div>
<?php else: ?>
  <input type="hidden" name="asiste_ceremonia" value="si"><input type="hidden" name="asiste_banquete" value="si">
<?php endif; ?>
<?php if (pregunta_bus($c)): $tr = seccion_tipo($c, 'transporte'); ?>
  <div class="field"><label class="check-group"><input type="checkbox" name="necesita_bus" value="si"> Necesitamos autobús <?= a_interno($tr['ruta'], $ctx, 'class="field-link"') ?>(ver horarios)</a></label></div>
<?php endif; ?>
  <div class="field"><label for="rsvpContacto">Teléfono o email de contacto</label><input type="text" id="rsvpContacto" name="contacto" maxlength="120" data-validate="email-or-phone" required></div>
  <div class="field"><label for="rsvpCancion">La canción que no puede faltar (opcional)</label><input type="text" id="rsvpCancion" name="cancion" maxlength="150"></div>

  <div class="rsvp-legal">
<?php if ($capa1 !== ''):
    // La última frase del texto de Legal ("Más información en el aviso de privacidad.") es el enlace
    $frase = 'Más información en el aviso de privacidad.';
    $antes = strpos($capa1, $frase) !== false ? trim(str_replace($frase, '', $capa1)) : $capa1; ?>
    <p class="rsvp-capa1"><?= h($antes) ?> <?= a_interno('privacidad', $ctx) ?>Más información en el aviso de privacidad</a>.</p><?php endif; ?>
    <div class="field" data-si-alergias hidden><label class="check-group"><input type="checkbox" name="consent_alergias" value="si"> <?= h($L['check_alergias'] ?? 'Doy mi consentimiento explícito para que la pareja use las alergias o intolerancias que indico solo para preparar el menú, y para que se las pase al catering con mi nombre y, si hay plano de mesas, mi mesa.') ?></label></div>
    <div class="field" data-si-grupo hidden><label class="check-group"><input type="checkbox" name="consent_acompanantes" value="si"> <?= h($L['check_acompanantes'] ?? 'Tengo permiso de las personas que apunto para facilitar sus datos.') ?></label></div>
  </div>

  <div class="form-actions"><button type="submit" class="btn" id="rsvpSubmit"<?= frase_propia($c, 'rsvp_boton') ? ' data-fijo="1"' : '' ?>><?= h(frase_web($c, 'rsvp_boton', '¡Allí estaré!')) ?></button></div>
  <div class="form-msg" role="alert"></div>
</form>
<div class="modal-overlay" id="rsvpSuccessModal">
  <div class="modal">
    <h2<?= frase_propia($c, 'rsvp_ok') ? ' data-fijo="1"' : '' ?>><?= h(frase_web($c, 'rsvp_ok', '¡Apuntado!')) ?></h2>
    <p>Gracias por confirmar. ¡Nos vemos<?= $c['fecha'] !== '' ? ' el ' . h(fecha_larga($c['fecha'], false)) : ' pronto' ?>!</p>
    <button type="button" class="btn" data-close-modal>Cerrar</button>
  </div>
</div>
<?php
    return (string) ob_get_clean();
}

function pagina_privacidad(array $c, array $ctx): string {
    $f = __DIR__ . '/legal/privacidad-boda.php';
    if (!is_file($f)) return envoltorio('Privacidad', '<p class="lede">Texto en preparación.</p>');
    $b = ['nombre1' => $c['pareja']['nombre1'], 'nombre2' => $c['pareja']['nombre2'], 'email' => $c['pareja']['email'],
        'borrado' => $c['fecha'] !== '' ? fecha_larga(fecha_borrado($c['fecha']), false) : ''];
    $E = empresa_publica();
    ob_start();
    include $f;
    return '<main><div class="wrap"><section class="section legal-text">' . ob_get_clean() . '</section></div></main>';
}

/** Página que queda cuando termina el alojamiento: sin formularios, sin datos de invitados. */
function render_archivada(array $c, array $ctx): string {
    $cuerpo = '<main><div class="wrap"><section class="section" style="text-align:center"><h1>' . h(nombres($c)) . '</h1><hr class="divider">'
        . '<p class="lede">Gracias a todos por acompañarnos' . ($c['fecha'] !== '' ? ' el ' . h(fecha_larga($c['fecha'], false)) : '') . '.</p></section></div></main>';
    $c2 = $c;
    $c2['secciones'] = [];
    return layout($c2, '', nombres($c), $cuerpo, $ctx);
}
