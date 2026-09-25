<?php
// El ÚNICO generador de HTML de una boda. Lo usan la web publicada, la vista previa
// del creador y el ZIP descargable: si hubiera dos, la pareja vería una cosa y
// descargaría otra. Todo texto de la pareja o de un invitado pasa por h(); no existe
// ningún campo de HTML libre.

declare(strict_types=1);

require_once __DIR__ . '/schema.php';

/**
 * $ctx:
 *  modo    'live' | 'preview' | 'zip'
 *  assets  prefijo de /assets (live: '/assets/', zip: 'assets/', preview: url absoluta del creador)
 *  foto    url de la foto o '' si no hay (en preview la pone el creador por postMessage)
 *  slug    nombre de la web (para el ZIP: enlaza a la versión alojada)
 */
function render_pagina(array $c, string $ruta, array $ctx): ?string {
    $ctx += ['modo' => 'live', 'assets' => '/assets/', 'foto' => '', 'slug' => ''];
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
    'galeria' => 'M3 5h18v14H3zM3 15l5-5 4 4 3-3 6 6M15 9.5a1.5 1.5 0 1 0 0-.01',
    'libro' => 'M4 4h11a3 3 0 0 1 3 3v13H7a3 3 0 0 1-3-3zM18 20a2 2 0 0 0 2-2V6M8 9h6M8 13h4',
    'corazon' => 'M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z',
    'flecha' => 'M5 12h14M13 6l6 6-6 6',
    'mapa' => 'M9 4 3 6v14l6-2 6 2 6-2V4l-6 2-6-2zM9 4v14M15 6v14',
    'casa' => 'M3 11l9-7 9 7v9a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1z',
    'calendario' => 'M3 5h18v16H3zM3 10h18M8 3v4M16 3v4M9 15l2 2 4-4',
];

function tema_css(array $c): string {
    $t = TEMAS[$c['tema']] ?? TEMAS['eucalipto'];
    if (($c['atelier'] ?? '') !== '') $t = array_merge([ATELIER[$c['atelier']]['nombre']], ATELIER[$c['atelier']]['colores']);
    $rgb = function (string $hex): string {
        return implode(' ', array_map('hexdec', str_split(ltrim($hex, '#'), 2)));
    };
    return ':root{--primary:' . $t[1] . ';--accent:' . $t[2] . ';--accent-hover:' . $t[3] . ';--secondary:' . $t[4]
        . ';--sage:' . $t[5] . ';--sage-2:' . $t[6] . ';--sage-2-hover:' . $t[7] . ';--gold:' . $t[8]
        . ';--on-dark:' . $t[9] . ';--on-dark-soft:' . $t[10]
        . ';--primary-rgb:' . $rgb($t[1]) . ';--accent-rgb:' . $rgb($t[2]) . ';--on-dark-rgb:' . $rgb($t[9])
        . ';--sage-2-rgb:' . $rgb($t[6]) . ';--sage-rgb:' . $rgb($t[5])
        . ';' . fuente_css($c) . '}';
}

function fuente_css(array $c): string {
    $f = FUENTES[$c['fuente'] ?? 'clasica'] ?? FUENTES['clasica'];
    if (($c['atelier'] ?? '') !== '') $f = array_merge([ATELIER[$c['atelier']]['nombre']], ATELIER[$c['atelier']]['fuentes']);
    return '--serif:' . $f[1] . ';--sans:' . $f[2] . ';--nombres:' . $f[3] . ';--nombres-estilo:' . $f[4];
}

/** Ilustración de cabecera de cada diseño Atelier. El sello lleva encima las iniciales de ESTA pareja. */
function arte_atelier(array $c, string $A): string {
    $img = fn($f, $cls) => '<img class="atelier-arte ' . $cls . '" src="' . h($A) . 'img/atelier/' . $f . '.webp" alt="" aria-hidden="true">';
    switch ($c['atelier']) {
        case 'citricos': return $img('limones', 'arte-limones');
        case 'herbario': return '<div class="arte-herbario-marco">' . $img('herbario', 'arte-herbario') . '</div>';
        case 'masia': return $img('masia', 'arte-masia');
        case 'lacre': return '<div class="arte-sello" aria-hidden="true"><img src="' . h($A) . 'img/atelier/sello.webp" alt=""><span>' . h(iniciales($c) ?: '♥') . '</span></div>';
        case 'ceramica': return '<p class="arte-monograma" aria-hidden="true">' . h(iniciales($c)) . '</p>';
        default: return '';
    }
}

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
<?php if ($ctx['modo'] === 'preview'): ?><base href="<?= h($A) ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= h($ctx['modo'] === 'preview' ? '' : $A) ?>boda.css?v=<?= h(ASSETS_V) ?>">
<style><?= tema_css($c) ?></style>
</head>
<body class="<?= $ruta === '' ? 'page-home' : 'page-inner' ?><?= $c['atelier'] !== '' ? ' atelier atelier-' . h($c['atelier']) : ' deco-' . h($c['decoracion']) . ' fuente-' . h($c['fuente']) ?><?= $ctx['modo'] === 'preview' ? ' is-preview' : '' ?>">
<?php if ($ctx['modo'] === 'preview'): // marca de agua: viaja con el HTML si alguien copia la vista previa (owner, 25-sep) ?>
<div class="marca-previa" aria-hidden="true"><span>Vista previa · <?= h(MARCA) ?></span><span>Publicad vuestra web para quitar esta marca</span></div>
<?php endif; ?>

<nav class="site-nav" aria-label="Navegación principal">
  <div class="nav-bar">
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="siteMenu" aria-label="Abrir menú"><?= ico('M4 7h16M4 12h16M4 17h16') ?></button>
    <?= a_interno('', $ctx, 'class="nav-brand"') ?><span class="nav-names"><?= h($nom ?: 'Vuestros nombres') ?></span><span class="nav-sub">Nuestra boda</span></a>
    <ul class="nav-links">
<?php foreach ($secs as $s): if ($s['tipo'] === 'rsvp') continue; ?>
      <li><?= a_interno($s['ruta'], $ctx, $ruta === $s['ruta'] ? 'aria-current="page"' : '') ?><?= h($s['titulo']) ?></a></li>
<?php endforeach; ?>
    </ul>
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

<?= $cuerpo ?>

<footer class="site-footer">
  <div class="footer-mono" aria-hidden="true"><span class="rule"></span><span><?= h(iniciales($c)) ?></span><span class="rule"></span></div>
<?php if ($c['portada']['pie'] !== ''): ?>
  <p class="footer-thanks"><?= h($c['portada']['pie']) ?></p>
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
    if ($rsvp) $items[] = [$rsvp['ruta'], 'RSVP', ICONOS['rsvp']];
    foreach ($c['secciones'] as $s) {
        if (!$s['on'] || $s['tipo'] === 'rsvp' || count($items) >= 5) continue;
        $corto = ['regalos' => 'Regalos', 'informacion' => 'Info', 'dresscode' => 'Dress code'][$s['tipo']] ?? $s['titulo'];
        $items[] = [$s['ruta'], mb_strimwidth($corto, 0, 11, '…', 'UTF-8'), ICONOS[$s['tipo']]];
    }
    $o = '<nav class="tab-bar" aria-label="Accesos rápidos"><ul>';
    foreach ($items as [$r, $t, $i]) {
        $o .= '<li>' . a_interno($r, $ctx, $ruta === $r ? 'aria-current="page"' : '') . ico($i) . h($t) . '</a></li>';
    }
    return $o . '</ul></nav>';
}

function mapa_q(array $lugar): string {
    return trim($lugar['lugar'] . ' ' . $lugar['direccion']);
}

function pagina_inicio(array $c, array $ctx): string {
    $A = $ctx['assets'];
    $nom = nombres($c);
    $po = $c['portada'];
    $rsvp = seccion_tipo($c, 'rsvp');
    $hayFoto = $c['foto'] && ($ctx['foto'] !== '' || $ctx['modo'] === 'preview');
    $objetivo = $c['fecha'] !== '' ? $c['fecha'] . 'T' . ($c['ceremonia']['hora'] ?: '12:00') . ':00' : '';
    ob_start(); ?>
<main class="home">
  <section class="hero<?= $hayFoto ? '' : ' hero--solo' ?>">
    <div class="hero-left">
<?php if ($c['atelier'] !== ''): ?>
      <?= arte_atelier($c, $A) ?>
<?php elseif ($c['decoracion'] === 'eucalipto'): ?>
      <img class="hero-sprig" src="<?= h($A) ?>img/eucalipto.webp" alt="" width="512" height="140">
<?php elseif ($c['decoracion'] === 'flores'): ?>
      <img class="deco-guirnalda" src="<?= h($A) ?>img/deco/guirnalda.webp" alt="" width="1376" height="768">
<?php endif; ?>
      <div class="hero-text">
<?php if ($c['atelier'] === '' && $c['decoracion'] === 'sobre'): ?>        <span class="deco-solapa" aria-hidden="true"></span><span class="deco-lacre" aria-hidden="true"><?= h(iniciales($c) ?: '♥') ?></span>
<?php endif; ?>
<?php if ($po['invitacion'] !== ''): ?>        <span class="kicker"><?= h($po['invitacion']) ?></span><?php endif; ?>
        <h1 class="hero-names"><?= h($nom ?: 'Vuestros nombres') ?></h1>
        <div class="hero-date">
          <span class="rule" aria-hidden="true"></span><span class="star" aria-hidden="true">✦</span>
          <span class="kicker"><?= h(linea_fecha($c) ?: 'La fecha') ?></span>
          <span class="star" aria-hidden="true">✦</span><span class="rule" aria-hidden="true"></span>
        </div>
      </div>
    </div>
<?php if ($hayFoto): ?>
    <div class="mat rv">
      <div class="mat-inner">
        <div class="mat-photo"><img data-foto src="<?= h($ctx['foto']) ?>" alt="<?= h($nom) ?>" width="960" height="1280" loading="eager"></div>
        <p class="mat-caption"><?= h(trim($nom . ' · ' . linea_fecha($c), ' ·')) ?></p>
      </div>
    </div>
<?php endif; ?>
  </section>

<?php if ($po['titulo'] !== '' || $po['frase'] !== '' || $po['texto'] !== ''): ?>
  <section class="letter rv">
<?php if ($po['titulo'] !== ''): ?>    <h2 class="section-title"><?= h($po['titulo']) ?></h2>
    <div class="mini-rule" aria-hidden="true"></div><?php endif; ?>
<?php if ($po['frase'] !== ''): ?>    <p class="big"><?= h($po['frase']) ?></p><?php endif; ?>
    <?= parrafos($po['texto']) ?>
<?php if ($rsvp): ?>    <?= a_interno($rsvp['ruta'], $ctx, 'class="btn"') ?><?= ico(ICONOS['corazon'], 'ico ico-sm') ?><?= h($rsvp['titulo']) ?></a><?php endif; ?>
  </section>
<?php endif; ?>

<?php if ($c['fecha'] !== ''): ?>
  <section class="save-band">
    <div class="save-card rv">
      <span class="kicker">Guardad el momento</span>
      <h2>Anotad la fecha</h2>
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
          <div class="icon-dot" aria-hidden="true"><?= ico($k === 'ceremonia' ? 'M12 2v4M10 4h4M6 21V11l6-4 6 4v10M3 21h18M10 21v-5h4v5' : 'M12 3v4M12 17v4M3 12h4M17 12h4M6 6l2.5 2.5M15.5 15.5 18 18M6 18l2.5-2.5M15.5 8.5 18 6') ?></div>
        </div>
<?php if ($e['lugar'] !== ''): ?>
        <a class="btn btn-soft" href="https://maps.google.com/?q=<?= h(rawurlencode(mapa_q($e))) ?>" target="_blank" rel="noopener noreferrer"><?= ico(ICONOS['mapa'], 'ico ico-sm') ?>Ver mapa · <?= h($rot) ?></a>
<?php endif; ?>
      </article>
<?php endforeach; ?>
    </div>
  </section>

  <section class="quick">
    <div class="quick-grid">
<?php foreach ($c['secciones'] as $s):
        if (!$s['on'] || $s['tipo'] === 'informacion') continue;
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
          <span class="kicker" style="margin-bottom:4px">¿Qué canción no puede faltar?</span>
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
        <?= a_interno($s['ruta'], $ctx, 'class="link-arrow"') ?>Ver <?= h(mb_strtolower($s['titulo'], 'UTF-8')) ?> <?= ico(ICONOS['flecha'], 'ico ico-sm') ?></a>
      </div>
<?php endif; endforeach; ?>
    </div>
  </section>

<?php if ($c['atelier'] !== ''): ?>
  <div class="atelier-pie" aria-hidden="true"></div>
<?php elseif ($c['decoracion'] === 'eucalipto'): ?>
  <div class="sprig-foot" aria-hidden="true"><img src="<?= h($A) ?>img/eucalipto.webp" alt="" width="512" height="140"></div>
<?php elseif ($c['decoracion'] === 'flores'): ?>
  <div class="deco-pie" aria-hidden="true"><img src="<?= h($A) ?>img/deco/enredadera.webp" alt="" width="896" height="1200"><img src="<?= h($A) ?>img/deco/enredadera-2.webp" alt="" width="896" height="1200"></div>
<?php endif; ?>
</main>
<?php
    return (string) ob_get_clean();
}

/** Primera frase de un texto, para las tarjetas de la portada. */
function resumen(string $t, int $max = 140): string {
    $t = trim((string) preg_replace('/\s+/', ' ', $t));
    if ($t === '') return '';
    if (preg_match('/^(.{20,}?[.!?])(\s|$)/u', $t, $m)) $t = $m[1];
    return mb_strimwidth($t, 0, $max, '…', 'UTF-8');
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

function envoltorio(string $titulo, string $dentro, string $estilo = ''): string {
    return '<main><div class="wrap"><section class="section rv"' . ($estilo !== '' ? ' style="' . $estilo . '"' : '') . '><h1>' . h($titulo) . '</h1><hr class="divider">' . $dentro . '</section></div></main>';
}

function pagina_seccion(array $c, array $s, array $ctx): string {
    $d = $s['datos'];
    $intro = parrafos($d['texto'] ?? '', 'lede');
    switch ($s['tipo']) {
        case 'rsvp': return envoltorio($s['titulo'], $intro . form_rsvp($c, $s, $ctx));
        case 'informacion': return envoltorio($s['titulo'], $intro . pagina_informacion($c) . tarjeta_menu($c));
        case 'transporte': return envoltorio($s['titulo'], $intro . bloque_transporte($c, $s, $ctx));
        case 'hoteles': return envoltorio($s['titulo'], $intro . lista_hoteles($d));
        case 'regalos': return envoltorio($s['titulo'], $intro . bloque_regalos($c, $d));
        case 'musica': return envoltorio($s['titulo'], $intro . bloque_musica($ctx));
        case 'galeria': return envoltorio($s['titulo'], $intro . bloque_galeria($s, $ctx), 'max-width:1000px;margin:0 auto;');
        case 'libro': return envoltorio($s['titulo'], $intro . bloque_libro($c, $s, $ctx));
        case 'dresscode': return envoltorio($s['titulo'], $intro ?: '<p class="lede">Pronto os contamos más.</p>', 'max-width:560px;margin:0 auto;');
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
    ob_start(); ?>
<form id="rsvpForm" class="stack" novalidate data-menu-nino="<?= h($defNino) ?>" data-menu-adulto="<?= h($defAdulto) ?>">
  <input type="text" name="web" tabindex="-1" autocomplete="off" class="hp" aria-hidden="true">
  <div class="guests-head">
    <span class="kicker">Quiénes venís</span>
    <p class="guests-hint">Uno confirma por todos: añadid a cada adulto y a cada peque.</p>
  </div>
  <div class="guest-list" id="guestList">
    <fieldset class="guest" data-kind="adulto">
      <legend class="guest-head"><span class="guest-tag">Tú</span></legend>
      <input type="hidden" data-f="tipo" name="invitados[0][tipo]" value="adulto">
      <div class="field"><label for="g0-nombre">Nombre y apellidos</label><input type="text" id="g0-nombre" data-f="nombre" name="invitados[0][nombre]" autocomplete="name" maxlength="120" required></div>
      <?= $menuField($menus) ?>
      <div class="field"><label for="g0-alergias">Alergias o intolerancias</label><input type="text" id="g0-alergias" data-f="alergias" name="invitados[0][alergias]" maxlength="300" placeholder="Déjalo en blanco si comes de todo"></div>
    </fieldset>
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
    <p class="guests-note">Vale para todo el grupo. Si alguien no va a todo, que confirme por su cuenta.</p>
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
    <div class="field" data-si-alergias hidden><label class="check-group"><input type="checkbox" name="consent_alergias" value="si"> <?= h($L['check_alergias'] ?? 'Consiento que se traten los datos de alergias e intolerancias para organizar el menú.') ?></label></div>
    <div class="field" data-si-grupo hidden><label class="check-group"><input type="checkbox" name="consent_acompanantes" value="si"> <?= h($L['check_acompanantes'] ?? 'Tengo permiso de las personas que apunto para facilitar sus datos.') ?></label></div>
  </div>

  <div class="form-actions"><button type="submit" class="btn" id="rsvpSubmit">¡Allí estaré!</button></div>
  <div class="form-msg" role="alert"></div>
</form>
<div class="modal-overlay" id="rsvpSuccessModal">
  <div class="modal">
    <h2>¡Apuntado!</h2>
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
    $E = empresa();
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
