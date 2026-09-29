<?php
// Landing del producto (CREATOR_HOST /). Composición y piel de la maqueta de Stitch
// del owner (25-sep-2026). Los textos son nuestros y solo dicen lo que el producto
// hace de verdad: la maqueta traía "+14.000 parejas", "99,4 %", testimonios con
// nombre, galería, Spotify y "0 % comisiones", y nada de eso existe. Las capturas del
// hero y del bloque del constructor son de NUESTRA plantilla con datos de ejemplo,
// rotuladas como ejemplo (tools: _dev/capturas.js las regenera).

declare(strict_types=1);

const ICONOS_L = [
    'flor' => 'M12 7.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5zM12 7.5V21M12 13c-3 0-5-2-5-5 3 0 5 2 5 5zm0 3c3 0 5-2 5-5-3 0-5 2-5 5z',
    'flecha' => 'M5 12h14M13 6l6 6-6 6',
    'corazon' => 'M12 20s-7-4.4-7-10a4 4 0 0 1 7-2.6A4 4 0 0 1 19 10c0 5.6-7 10-7 10z',
    'sobre' => 'M3 6h18v12H3zM3 7l9 6 9-6',
    'reloj' => 'M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18zM12 7v5l3 2',
    'mapa' => 'M12 21s-6-5.3-6-10a6 6 0 0 1 12 0c0 4.7-6 10-6 10zM12 13a2 2 0 1 0 0-4 2 2 0 0 0 0 4z',
    'regalo' => 'M3 8h18v4H3zM5 12v8h14v-8M12 8v12M12 8C10 4 6.5 4.5 7 7c.3 1.2 2.5 1 5 1zm0 0c2-4 5.5-3.5 5-1-.3 1.2-2.5 1-5 1z',
    'excel' => 'M12 4v11M7 10l5 5 5-5M5 20h14',
    'musica' => 'M9 18V5l11-2v13M9 18a3 3 0 1 1-6 0 3 3 0 0 1 6 0zM20 16a3 3 0 1 1-6 0 3 3 0 0 1 6 0z',
    'calendario' => 'M3 5h18v16H3zM3 10h18M8 3v4M16 3v4',
    'check' => 'M20 6 9 17l-5-5',
    'lapiz' => 'M4 20h4L19 9l-4-4L4 16zM13 7l4 4',
    'enlace' => 'M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1',
    'escudo' => 'M12 3 4 6v6c0 5 3.4 8 8 9 4.6-1 8-4 8-9V6z',
    'ojo' => 'M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12zM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6z',
    'mas' => 'M12 5v14M5 12h14',
    'libro' => 'M4 5a2 2 0 0 1 2-2h13v16H6a2 2 0 0 0-2 2zM4 21V5M9 8h6',   // libro de invitados (aviso del cierre)
    // Iconos de «Qué incluye» elegidos por el owner el 28-sep-2026 (artifact «Iconos BodaEnlace»): las rayas de la mesa
    // se leían como un sol, el portapapeles era de oficina y el ojo decía «vista», no «fotos»
    'mesa' => 'M8 12a4 4 0 1 0 8 0 4 4 0 1 0-8 0zM10.4 4.2a1.6 1.6 0 1 0 3.2 0 1.6 1.6 0 1 0-3.2 0zM10.4 19.8a1.6 1.6 0 1 0 3.2 0 1.6 1.6 0 1 0-3.2 0zM2.6 12a1.6 1.6 0 1 0 3.2 0 1.6 1.6 0 1 0-3.2 0zM18.2 12a1.6 1.6 0 1 0 3.2 0 1.6 1.6 0 1 0-3.2 0z',   // mesa redonda con cuatro sillas redondas
    'campana' => 'M3 17h18M5 17a7 7 0 0 1 14 0M12 10V8.5M10.5 8.5h3M4.5 20h15',   // campana de servir (catering)
    'marco' => 'M3.5 5h17v14h-17zM7 9.5a1.5 1.5 0 1 0 3 0 1.5 1.5 0 1 0-3 0zM3.5 17l5-5 4 4 3-3 5 5',   // marco de foto (galería)
    // «Respuestas» del menú del panel (vista_constructor.php, por variable: un grep de il('lista') no lo ve)
    'lista' => 'M9 3h6v3H9zM7 4.5H5V21h14V4.5h-2M8 11h8M8 15h8M8 19h5',             // hoja con renglones
];
/**
 * Logo de la marca: anillos + dominio (MARCA_WEB), con el .com en cursiva. ÚNICO sitio donde se
 * compone: portada, creador, legales, guías y estudio lo llaman, así que cambiarlo aquí lo cambia en
 * todas partes (owner, 26-sep-2026). $antes va delante del nombre (p. ej. «Estudio · »).
 */
function logo_marca(string $cls, string $href, string $antes = '', bool $tld = true, string $despuesHtml = ''): string {
    [$nombre, $dom] = array_pad(explode('.', MARCA_WEB, 2), 2, '');
    return '<a class="' . $cls . '" href="' . h($href) . '" aria-label="' . h($antes . MARCA_WEB) . '">' . il('anillos', 'i i-anillos')
        . '<span>' . h($antes . $nombre) . ($tld && $dom !== '' ? '<em>.' . h($dom) . '</em>' : '') . $despuesHtml . '</span></a>';
}

function il(string $n, string $cls = 'i'): string {
    if ($n === 'anillos') {   // logo de la marca: dos anillos enlazados y un corazón (SVG de la owner, 26-sep-2026), relleno.
                              // pathLength="1" en los anillos: la cabecera de la landing los dibuja al cargar (landing.css → «logo»)
        return '<svg class="' . $cls . '" viewBox="26 80 1027 918" aria-hidden="true">'
            . '<path pathLength="1" d="M599.02,966.83l46.65-44.85c15.66.11,31.02,6.31,46.81,7.48,137.91,10.19,262.45-78.65,287.53-215.98,35.91-196.63-149.85-361.49-341.23-301.85-168.78,52.6-239.62,258.98-138.33,404.47-.76,4.51-41.28,32.79-46.08,32.82-3.64.02-14.61-18.26-17-22.36-108.59-186.7-2.02-428.17,206.04-477.17,254.34-59.89,471.91,188.86,378.03,433.06-64.16,166.9-255.38,249.88-422.43,184.38Z"/>'
            . '<path pathLength="1" d="M480.98,363.03l-46.65,44.85c-15.66-.11-31.02-6.31-46.81-7.48-137.91-10.19-262.45,78.65-287.53,215.98-35.91,196.63,149.85,361.49,341.23,301.85,168.78-52.6,239.62-258.98,138.33-404.47.76-4.51,41.28-32.79,46.08-32.82,3.64-.02,14.61,18.26,17,22.36,108.59,186.7,2.02,428.17-206.04,477.17-254.34,59.89-471.91-188.86-378.03-433.06,64.18-166.94,255.35-249.84,422.43-184.38Z"/>'
            . '<path d="M472.65,92.09c30.5-4.45,47.13,7.65,66.64,28.85,3.44.6,15.49-14.59,19.4-17.56,55.58-42.06,131.44,26.89,75.78,91.51-27.83,32.31-64.73,61.5-93.76,93.23l-4.7-2.74c-23.67-32.08-93.33-79.31-106.2-114.12-12.29-33.22,6.32-73.84,42.84-79.18Z"/></svg>';
    }
    if ($n === 'flor') {   // cinco pétalos y botón central
        $o = '<svg class="' . $cls . '" viewBox="0 0 24 24" aria-hidden="true">';
        foreach ([0, 72, 144, 216, 288] as $g) $o .= '<ellipse cx="12" cy="7" rx="2.6" ry="4" transform="rotate(' . $g . ' 12 12)"/>';
        return $o . '<circle cx="12" cy="12" r="1.8"/></svg>';
    }
    return '<svg class="' . $cls . '" viewBox="0 0 24 24" aria-hidden="true"><path d="' . ICONOS_L[$n] . '"/></svg>';
}

function pagina_landing(): string {
    $total = euros_escaparate(precio_total_cent());
    $totalAtelier = euros_escaparate(precio_atelier_cent());
    $E = empresa();
    $paletas = array_map(fn($t) => $t[2], TEMAS);
    $ejemplo = 'lucia-y-marcos.' . BASE_DOMAIN;
    // Una sola descripción para Google (meta description) y para compartir (og:description), owner 28-sep-2026
    $descripcion = 'Cread vuestra web de boda en minutos: confirmación de asistencia con menú y alergias, plano de mesas, galería, libro de invitados y música. Pago único, sin suscripción.';
    $faq = [
        ['¿Cuánto cuesta?', "Dos packs, IVA incluido: Esencial, $total; y Atelier, $totalAtelier, con un diseño ilustrado y animado. Se paga una sola vez, cuando publicáis la web. Crearla y verla en la vista previa no cuesta nada: podéis probar todo lo que queráis antes de pagar. No hay suscripción."],
        ['¿Podemos cambiar la web después de publicarla?', 'Sí. Desde vuestro panel privado editáis textos, secciones, fotos y colores cuando queráis, también desde el móvil, y los cambios se ven al momento.'],
        ['¿Quién ve la galería y el libro de invitados?', 'Solo quien tenga el enlace y el código de vuestra boda, que elegís vosotros. En el libro, vuestros invitados os dejan mensajes y fotos, y desde el panel podéis ocultar lo que no queráis que se vea.'],
        ['Nos han regalado la web, ¿dónde ponemos el código?', 'Montad la web como cualquier otra pareja y, en el último paso, «Publicar», escribid el código de regalo. La web se publica sin pagar nada.'],
        ['¿Cómo llega la web a los invitados?', "Con un enlace del tipo $ejemplo que compartís por WhatsApp, email o donde queráis. No tienen que instalar nada ni registrarse."],
        ['¿Podemos usar nuestro propio dominio?', 'Por ahora la web vive en un subdominio nuestro. Desde el panel podéis descargarla en ZIP y subirla a vuestro dominio, aunque esa copia no recoge confirmaciones.'],
        ['¿Se ve bien en el móvil?', 'Está pensada primero para el móvil, que es donde la van a abrir casi todos vuestros invitados. En la vista previa podéis verla en móvil y en ordenador.'],
        ['¿Qué pasa con los datos de nuestros invitados?', 'Solo los veis vosotros, en vuestro panel. La web no aparece en Google y no lleva publicidad. ' . MESES_ALOJAMIENTO . ' meses después de la boda borramos las respuestas: exportad el Excel antes si queréis guardarlas.'],
    ];
    ob_start(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<?php if (OCULTO): ?><meta name="robots" content="noindex, nofollow">
<?php endif; ?><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Web de boda con confirmación de asistencia | <?= h(marca()) ?></title>
<?= favicon_links() ?>
<meta name="description" content="<?= h($descripcion) ?>">
<link rel="canonical" href="<?= h(url_creador()) ?>">
<meta property="og:title" content="Web de boda con confirmación de asistencia | <?= h(marca()) ?>">
<meta property="og:description" content="<?= h($descripcion) ?>">
<meta property="og:type" content="website">
<meta property="og:image" content="<?= h(url_creador('assets/img/landing/demo-escritorio.webp')) ?>">
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/marca.css?v=<?= h(ASSETS_V) ?>">
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/landing.css?v=<?= h(ASSETS_V) ?>">
</head>
<body class="l">

<header class="l-top">
  <div class="l-top-in">
    <?= logo_marca('l-logo l-logo-web', BASE_PATH . '/') ?>
    <nav class="l-nav" aria-label="Secciones">
      <a href="#incluye">Qué incluye</a>
      <a href="#pasos">Cómo funciona</a>
      <a href="#constructor">Constructor</a>
      <a href="#atelier">Atelier</a>
      <a href="#precio">Precio</a>
      <a href="#preguntas">Preguntas</a>
    </nav>
    <a class="b-btn b-rose l-top-cta" href="<?= BASE_PATH ?>/crear">Crear nuestra web</a>
  </div>
</header>

<main>
  <section class="l-hero">
    <?php // Hero sin enredaderas (owner, 29-sep-2026): la de la izquierda era una imagen girada y se le veía el corte («Sin flor»,
          // artifact «Flor del hero»), y la de detrás de la caja del vídeo se quitó también el mismo día ?>
    <div class="l-wrap l-hero-grid">
      <div class="l-hero-txt">
        <?php // Punto salvia que respira en vez de la flor (owner, 28-sep-2026, artifact «Chips BodaEnlace») ?>
        <span class="l-chip"><span class="l-punto" aria-hidden="true"></span>Web de boda con confirmación de asistencia</span>
        <?php // Motion (28-sep-2026): cada palabra en su span para la entrada escalonada (landing.css → «entrada del hero»); el trazo dorado subraya la parte en cursiva ?>
        <?php // Titular y <title> del owner (28-sep-2026): llevan «web de boda» tal cual, la búsqueda principal; el anterior («tan bonita como el gran día») era casi el de bodas.com ?>
        <h1><span class="w">La</span> <span class="w">web</span> <span class="w">de</span> <span class="w">boda</span> <em class="w">que lo organiza todo<svg class="l-trazo" viewBox="0 0 100 10" preserveAspectRatio="none" aria-hidden="true"><path pathLength="1" d="M2 7C25 2 55 2 98 6"/></svg></em></h1>
        <p class="l-lede">Todo lo que necesitáis para planificar vuestra boda, en un solo lugar. Cread vuestra web desde el móvil o el ordenador en pocos minutos.</p>
        <div class="l-ctas">
          <a class="b-btn b-rose" href="<?= BASE_PATH ?>/crear">Probar la web <?= il('flecha', 'i i-sm i-arrow') ?></a>
          <a class="b-btn b-paper" href="#pasos">Cómo funciona</a>
        </div>
        <?php // Tres fichas en ordenador y lista vertical en móvil (owner, 28-sep-2026). Antes eran tres cifras iguales
              // («125 €», «0 €», «2 meses») y «0 €» junto al precio se leía como otro precio. .l-movil solo se ve en móvil,
              // donde cada dato va en una frase («Desde 125 €, pago único…», «Online hasta 2 meses…») ?>
        <ul class="l-datos">
          <li><span class="l-datos-ck"><?= il('check', 'i i-sm') ?></span><span><b>Gratis</b> <span class="l-datos-s">crearla y probarla</span></span></li>
          <li><span class="l-datos-ck"><?= il('check', 'i i-sm') ?></span><span><b>Desde <?= h($total) ?></b><span class="l-movil">,</span> <span class="l-datos-s">pago único al publicar</span></span></li>
          <li><span class="l-datos-ck"><?= il('check', 'i i-sm') ?></span><span><b>Online <span class="l-movil">hasta </span><?= (int) MESES_ALOJAMIENTO ?> meses</b> <span class="l-datos-s">después de la boda</span></span></li>
        </ul>
      </div>
      <div class="l-hero-vis">
        <div class="l-marco">
          <img class="l-marco-fondo" src="<?= BASE_PATH ?>/assets/img/landing/papel.webp" alt="" width="1376" height="768">
          <?php // Spot v9 (infraestructura/remotion, Spot-Bodas-ES, 28-sep-2026: titular y textos de la landing actual, app regrabada
                // con el sello de lacre nuevo) recodificado para web: 540×960, sin audio, 300 kb/s a dos pasadas ?>
          <video class="l-spot" src="<?= BASE_PATH ?>/assets/img/landing/spot-v9.mp4" poster="<?= BASE_PATH ?>/assets/img/landing/spot-v9-poster.webp" width="540" height="960" autoplay muted loop playsinline preload="metadata" aria-label="Vídeo de ejemplo: cómo se crea una web de boda con <?= h(marca()) ?>, sus paletas, los diseños Atelier, la confirmación de asistencia y los dos packs"></video>
          <span class="l-ejemplo">Ejemplo</span>
        </div>
        <div class="l-flota l-flota-a" aria-hidden="true">
          <span class="l-flota-ico"><?= il('corazon') ?></span>
          <span><b class="overline">Confirmación recibida</b><span class="l-flota-t">Ana y 2 acompañantes</span><span class="l-flota-s">1 menú infantil · sin gluten</span></span>
        </div>
        <div class="l-flota l-flota-b" aria-hidden="true">
          <span class="l-flota-ico l-flota-ico-sage"><?= il('musica') ?></span>
          <span><b class="overline overline-bronze">La más votada</b><span class="l-flota-t">Propuesta por vuestros invitados</span></span>
        </div>
      </div>
    </div>
  </section>

  <section class="l-sec" id="incluye">
    <div class="l-wrap">
      <div class="l-sec-cab">
        <div>
          <span class="overline">Todo en una web</span>
          <h2>Cada detalle resuelto, antes de decir el <em>«sí, quiero»</em></h2>
        </div>
      </div>
      <div class="l-cards">
        <article class="l-card">
          <span class="l-card-ico"><?= il('sobre') ?></span>
          <h3>Confirmación por grupo</h3>
          <p>Uno confirma por toda su familia, con el menú y las alergias de cada persona, y si necesitan autobús. Vosotros lo descargáis en Excel.</p>
          <span class="l-card-pie">Excel para el catering <?= il('excel', 'i i-sm') ?></span>
        </article>
        <article class="l-card">
          <span class="l-card-ico"><?= il('reloj') ?></span>
          <h3>Cuenta atrás y música</h3>
          <p>La cuenta atrás hasta el gran día y una lista de canciones que proponen y votan vuestros invitados.</p>
          <span class="l-card-pie">Votos de los invitados <?= il('musica', 'i i-sm') ?></span>
        </article>
        <?php // Mapa, lista de bodas y «menús y transporte» fuera (owner, 27-sep: «lo menos interesante»); el autobús pasa a la tarjeta de confirmación ?>
        <article class="l-card">
          <span class="l-card-ico"><?= il('marco') ?></span>
          <h3>Galería y libro de invitados</h3>
          <p>Vuestras fotos, y un libro donde los invitados os dejan mensajes y fotos. Todo protegido con un código que solo tienen ellos.</p>
          <span class="l-card-pie">Protegido con código <?= il('escudo', 'i i-sm') ?></span>
        </article>
        <article class="l-card l-card-salvia">
          <span class="l-card-ico"><?= il('campana') ?></span>
          <h3>Resumen para el catering</h3>
          <p>Cuántos hay de cada menú y cada alergia con su nombre y su mesa, listo para imprimir o guardar en PDF y dárselo al restaurante.</p>
          <span class="l-card-pie">Incluido <?= il('check', 'i i-sm') ?></span>
        </article>
        <?php // Plano de mesas y catering: incluidos en todos los packs (owner, 27-sep-2026). La captura es del panel real con una boda de ejemplo (nombres inventados) ?>
        <article class="l-card l-card-foto">
          <div class="l-card-txt">
            <span class="l-card-ico"><?= il('mesa') ?></span>
            <h3>Plano de mesas</h3>
            <p>Sentad a vuestros invitados tocando su nombre y luego la mesa. Para el restaurante, una hoja con cada mesa, su menú y sus alergias.</p>
            <span class="l-card-pie">Incluido <?= il('check', 'i i-sm') ?></span>
          </div>
          <img class="l-card-img" src="<?= BASE_PATH ?>/assets/img/landing/plano-mesas.webp" width="1234" height="1156" loading="lazy" decoding="async"
            alt="Plano de mesas en el panel de BodaEnlace: mesas redondas con sus sillas (Presidencia y Familia de Lucía), quién se sienta en cada una y quién tiene alergia">
        </article>
      </div>
    </div>
  </section>

  <section class="l-sec l-pasos" id="pasos">
    <?php // Guirnalda colgando de una rama sobre el título (owner, 29-sep-2026): las rosas cuelgan enteras de una rama fina que
          // cruza de lado a lado, en vez de salir cortadas por el borde de arriba. Dibujada por código en el estudio, sin licencias
          // (artifact «Guirnalda de Cómo funciona», https://claude.ai/artifact/VxnTovGpVazaDk5b1enVqd, opción «rama», semilla 24201),
          // exportada a 2x: 1178 px de ancho para ordenador y 388 px para móvil, cada una compuesta para su ancho. ?>
    <div class="l-guirnalda"><picture>
      <source media="(max-width: 600px)" srcset="<?= BASE_PATH ?>/assets/img/landing/guirnalda-rama-movil.webp" width="776" height="340">
      <img src="<?= BASE_PATH ?>/assets/img/landing/guirnalda-rama.webp" alt="" width="2356" height="500" loading="lazy" decoding="async">
    </picture></div>
    <div class="l-wrap">
      <div class="l-sec-cab l-centro">
        <span class="overline">Fácil y al momento</span>
        <h2>Vuestra web en tres pasos</h2>
        <p>No hace falta saber de diseño ni de informática. Lo que escribís se ve al instante en la vista previa.</p>
      </div>
      <?php // Papelería (owner, 29-sep-2026, artifact «Tres pasos de BodaEnlace»): cada paso es un objeto de boda dibujado en
            // HTML/CSS: el muestrario de papeles con las paletas reales y la tarjeta con los nombres. El Paso 3 es un trozo de
            // chat de WhatsApp (owner, 29-sep-2026, artifact «Paso 3 de BodaEnlace», https://claude.ai/artifact/ST4KLaDZPUoAhSaNpnd2Td,
            // opción «Conversación», colores WhatsApp): la pareja manda el mensaje que el panel deja escrito (bloque_compartir) y
            // una amiga contesta. Solo dibujo de chat: ni logo ni nombre de WhatsApp dentro de la escena ?>
      <ol class="l-pasos-lista">
        <li>
          <div class="l-paso-esc" aria-hidden="true"><span class="l-abanico"><?php foreach (array_slice(array_values($paletas), 0, 7) as $k => $col): ?><i style="background:<?= h($col) ?>;--g:<?= ($k - 3) * 13 ?>deg"></i><?php endforeach; ?></span></div>
          <span class="l-paso-n">Paso 1</span>
          <h3>Elegid el estilo</h3>
          <p><?= count(TEMAS) ?> paletas, <?= count(FUENTES) ?> tipografías y <?= count(DECORACIONES) ?> decoraciones para montar el vuestro, o uno de los <?= count(ATELIER) ?> diseños ilustrados de la Colección Atelier.</p>
        </li>
        <li>
          <div class="l-paso-esc" aria-hidden="true"><span class="l-paso-tarjeta"><small>Nuestra boda</small><b>Lucía &amp; Marcos</b><span>12 · 06 · 2027 · Sevilla</span></span></div>
          <span class="l-paso-n">Paso 2</span>
          <h3>Rellenad datos y foto</h3>
          <p>Nombres, fecha, lugares, vuestra foto y las secciones que queráis: activad, quitad y ordenad las páginas.</p>
          <span class="l-paso-pie"><?= il('lapiz', 'i i-sm') ?>Vista previa en directo</span>
        </li>
        <li>
          <div class="l-paso-esc" aria-hidden="true"><div class="l-wa">
            <div class="l-wa-cab"><span class="l-wa-av">C</span><span class="l-wa-nom">Carmen<small>en línea</small></span></div>
            <div class="l-wa-cuerpo">
              <div class="l-wa-b l-wa-sale">¡Nos casamos! Toda la info y la confirmación aquí: <span class="l-wa-url"><?= h($ejemplo) ?></span><span class="l-wa-h">20:14 <svg viewBox="0 0 16 10"><path d="M1 5.5 4 8.5 10 1.5M6.5 7.5l1 1 6-7"/></svg></span></div>
              <div class="l-wa-b l-wa-entra">¡¡Qué ilusión!! Ya hemos confirmado los dos<span class="l-wa-h">20:19</span></div>
            </div>
          </div></div>
          <span class="l-paso-n">Paso 3</span>
          <h3>Compartid el enlace</h3>
          <p>Al publicar, la web queda online al momento. Mandad el enlace por WhatsApp y las confirmaciones llegan a vuestro panel.</p>
          <span class="l-paso-pie"><?= il('enlace', 'i i-sm') ?><?= h($ejemplo) ?></span>
        </li>
      </ol>
    </div>
  </section>

  <section class="l-sec" id="constructor">
    <div class="l-wrap">
      <div class="l-demo">
        <div class="l-demo-barra">
          <span class="l-dots"><i></i><i></i><i></i></span>
          <span class="l-demo-tit">Constructor · vista previa en directo</span>
          <span class="l-demo-disp"><b>Escritorio</b><span>Móvil</span></span>
        </div>
        <div class="l-demo-cuerpo">
          <div class="l-demo-lado">
            <span class="overline overline-bronze">Secciones</span>
            <ul>
              <li><?= il('corazon', 'i i-sm') ?>Confirmar asistencia<span class="l-sw"></span></li>
              <li><?= il('mapa', 'i i-sm') ?>Información<span class="l-sw"></span></li>
              <li><?= il('regalo', 'i i-sm') ?>Lista de bodas<span class="l-sw"></span></li>
              <li><?= il('musica', 'i i-sm') ?>Música<span class="l-sw"></span></li>
              <li class="off"><?= il('mas', 'i i-sm') ?>Sección propia<span class="l-sw"></span></li>
            </ul>
            <span class="overline overline-bronze">Colores</span>
            <?php // Paletas de verdad: cada botón cambia la captura por la de la web de ejemplo en esa paleta (assets/img/landing/paleta-<tema>.webp,
                  // hechas con render_pagina en modo zip, 28-sep-2026). Si se añade un tema a TEMAS, falta su captura: landing.js no cambia a una que no existe ?>
            <span class="l-swatches l-swatches-sm l-paletas" data-base="<?= BASE_PATH ?>/assets/img/landing/paleta-"><?php foreach (TEMAS as $i => $t): ?><button type="button" class="<?= $i === 'rosa' ? 'on' : '' ?>" data-tema="<?= h($i) ?>" aria-label="Paleta <?= h($t[0]) ?>" aria-pressed="<?= $i === 'rosa' ? 'true' : 'false' ?>" style="background:<?= h($t[2]) ?>"></button><?php endforeach; ?></span>
          </div>
          <div class="l-demo-previa">
            <img src="<?= BASE_PATH ?>/assets/img/landing/paleta-rosa.webp" alt="Ejemplo de la vista previa en ordenador de una web de boda" width="1280" height="800" loading="lazy">
            <span class="l-ejemplo">Ejemplo</span>
          </div>
        </div>
        <?php // Tique de tres pasos (owner, 29-sep-2026, artifact «Probadlo gratis BodaEnlace», https://claude.ai/artifact/QvXnH3RB4SiJs8ePEZ8GXL):
              // crear y la vista previa a 0 €, publicar desde el pack Esencial. El precio sale de precio_total_cent(), nunca escrito a mano ?>
        <div class="l-demo-pie">
          <p class="l-tique-tit">¿Lo <em>probáis</em>?</p>
          <ul class="l-tique" aria-label="Qué se paga y cuándo">
            <li><small>Crear</small><b>0 €</b><span class="l-tique-gratis">Gratis</span></li>
            <li><small>Vista previa</small><b>0 €</b><span class="l-tique-gratis">Gratis</span></li>
            <li class="l-tique-pago"><small>Publicar</small><b>desde <?= h($total) ?> <span>IVA incl.</span></b></li>
          </ul>
          <a class="b-btn b-rose" href="<?= BASE_PATH ?>/crear">Abrir el constructor</a>
        </div>
      </div>
    </div>
  </section>

  <section class="l-sec l-atelier" id="atelier">
    <div class="l-wrap">
      <div class="l-sec-cab">
        <div>
          <span class="overline">Colección Atelier</span>
          <h2>Diseños ilustrados, <em>como papelería fina</em></h2>
        </div>
        <p><?= count(ATELIER) ?> diseños, cada uno con su paleta, sus letras y su ilustración. La invitación llega en un sobre animado.</p>
      </div>
      <div class="l-atelier-grid">
<?php foreach (ATELIER as $k => $a): ?>
        <article class="l-atelier-card">
          <div class="l-atelier-img"><img src="<?= BASE_PATH ?>/assets/img/atelier/muestra-<?= h($k) ?>.webp" alt="Ejemplo del diseño <?= h($a['nombre']) ?>" width="330" height="440" loading="lazy"><span class="l-ejemplo">Ejemplo</span></div>
          <div class="l-atelier-meta"><span class="overline overline-bronze"><?= h($a['categoria']) ?></span><span class="l-atelier-precio"><?= h($totalAtelier) ?> <small>IVA incl.</small></span></div>
          <h3><?= h($a['nombre']) ?></h3>
          <p><?= h($a['desc']) ?></p>
          <a class="b-btn b-dark l-atelier-btn" href="<?= BASE_PATH ?>/crear?atelier=<?= h($k) ?>">Empezar con este diseño</a>
        </article>
<?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="l-sec" id="precio">
    <div class="l-wrap">
      <div class="l-sec-cab l-centro">
        <span class="overline">Precio</span>
        <h2>Dos packs, <em>todo incluido</em></h2>
        <p>Sin suscripciones ni extras. Pagáis una vez, cuando la web está como queréis.</p>
      </div>
      <?php // Tarjetas de invitación (owner, 29-sep-2026, artifact «Precio de BodaEnlace»): papelería con filete; la Atelier con sello de lacre y doble filete dorado ?>
      <div class="l-packs">
        <div class="l-precio-card">
          <span class="overline overline-bronze">Pack</span>
          <h3 class="l-precio-nombre">Esencial</h3>
          <div class="l-precio-cifra"><b><?= h($total) ?></b><span>IVA incluido · pago único</span></div>
          <span class="l-precio-orn" aria-hidden="true"><?= il('flor', 'i') ?></span>
          <ul>
            <li><?= il('check', 'i i-sm') ?>Web publicada al momento, con todas vuestras secciones</li>
            <li><?= il('check', 'i i-sm') ?>Confirmaciones con menú y alergias por invitado</li>
            <li><?= il('check', 'i i-sm') ?>Panel privado con Excel para el catering</li>
            <li><?= il('check', 'i i-sm') ?><?= count(TEMAS) ?> paletas, <?= count(FUENTES) ?> tipografías y <?= count(DECORACIONES) ?> decoraciones</li>
            <li><?= il('check', 'i i-sm') ?>Mapa de la ceremonia y el convite</li>
            <li><?= il('check', 'i i-sm') ?>Galería y libro de invitados</li>
            <li><?= il('check', 'i i-sm') ?>Cambios ilimitados y descarga en ZIP</li>
          </ul>
          <a class="b-btn b-paper" href="<?= BASE_PATH ?>/crear">Empezar gratis</a>
        </div>
        <div class="l-precio-card l-precio-atelier">
          <span class="l-precio-sello" aria-hidden="true"><?= il('anillos', 'i i-anillos') ?></span>
          <span class="overline">Pack · diseño ilustrado</span>
          <h3 class="l-precio-nombre">Atelier</h3>
          <div class="l-precio-cifra"><b><?= h($totalAtelier) ?></b><span>IVA incluido · pago único</span></div>
          <span class="l-precio-orn" aria-hidden="true"><?= il('flor', 'i') ?></span>
          <ul>
            <li><?= il('check', 'i i-sm') ?>Todo lo del pack Esencial</li>
            <li><?= il('check', 'i i-sm') ?>Uno de los <?= count(ATELIER) ?> diseños de la Colección Atelier</li>
            <li><?= il('check', 'i i-sm') ?>Entrada animada: la invitación llega en un sobre con sello de cera</li>
            <li><?= il('check', 'i i-sm') ?>Ilustraciones y fotos que aparecen con movimiento</li>
            <li><?= il('check', 'i i-sm') ?>Detalles animados propios de cada diseño</li>
<?php if (fuentes_autor()): ?>            <li><?= il('check', 'i i-sm') ?>Tipografías de autor, solo en este pack</li>
<?php endif; ?>          </ul>
          <a class="b-btn b-rose" href="<?= BASE_PATH ?>/#atelier">Ver la Colección Atelier <?= il('flecha', 'i i-sm i-arrow') ?></a>
        </div>
      </div>
    </div>
  </section>

  <section class="l-sec" id="preguntas">
    <div class="l-wrap l-faq">
      <div class="l-sec-cab l-centro">
        <span class="overline">Preguntas frecuentes</span>
        <h2>Todo lo que necesitáis saber</h2>
      </div>
      <?php // Dos columnas de cuatro, todas plegadas (owner, 29-sep-2026, artifact «Preguntas de BodaEnlace», opción «Dos columnas»).
            // El orden de lectura sigue siendo el de $faq: primero la columna izquierda entera y luego la derecha ?>
      <div class="l-faq-cols">
<?php foreach (array_chunk($faq, (int) ceil(count($faq) / 2)) as $col): ?>
        <div class="l-faq-col">
<?php foreach ($col as [$q, $a]): ?>
          <details class="l-faq-item">
            <summary><?= h($q) ?><svg class="i i-sm" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
            <p><?= h($a) ?></p>
          </details>
<?php endforeach; ?>
        </div>
<?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="l-sec">
    <div class="l-wrap">
      <?php // Cierre con la web en el móvil de un invitado y los avisos que le van llegando a la pareja (owner, 29-sep-2026,
            // artifact «Cierre de BodaEnlace», https://claude.ai/artifact/6UnQZmVnoWvk4Nz8bTqFAT, opción «Con la web en el móvil»).
            // Los avisos son de ejemplo y cuentan lo que hace el panel: confirmaciones, canciones con votos y el libro ?>
      <div class="l-final">
        <div class="l-final-txt">
          <span class="l-chip l-chip-dark"><?= il('corazon', 'i i-sm') ?>Vuestro momento es ahora</span>
          <h2>Cread hoy la web que vuestros invitados van a abrir <em>una y otra vez</em></h2>
          <p>Sin suscripciones. Un único pago al publicar, y la web vuestra hasta <?= (int) MESES_ALOJAMIENTO ?> meses después de la boda.</p>
          <div class="l-final-ctas">
            <a class="b-btn b-rose" href="<?= BASE_PATH ?>/crear">Crear nuestra web</a>
            <a class="b-btn l-btn-ghost" href="#precio">Ver precio</a>
          </div>
        </div>
        <div class="l-final-tel" aria-hidden="true">
          <div class="l-final-pant"><img src="<?= BASE_PATH ?>/assets/img/landing/demo-movil.webp" alt="" width="780" height="1560" loading="lazy" decoding="async"></div>
          <div class="l-final-avisos">
            <div class="l-aviso"><span class="l-aviso-ic l-aviso-rosa"><?= il('check', 'i i-sm') ?></span><span><b>Carmen ha confirmado</b><small>2 personas · menú de carne</small></span></div>
            <div class="l-aviso"><span class="l-aviso-ic l-aviso-salvia"><?= il('musica', 'i i-sm') ?></span><span><b>Nueva canción propuesta</b><small>por Javi · 4 votos</small></span></div>
            <div class="l-aviso"><span class="l-aviso-ic l-aviso-oro"><?= il('libro', 'i i-sm') ?></span><span><b>Mensaje en el libro</b><small>de la tía Rosa</small></span></div>
          </div>
        </div>
      </div>
    </div>
  </section>
</main>

<footer class="l-pie">
  <div class="l-wrap l-pie-in">
    <span class="l-pie-marca"><?= logo_marca('l-logo l-logo-pie', BASE_PATH . '/', '', false) ?><a class="l-pie-by" href="https://axisworks.studio/" target="_blank" rel="noopener">by AxisWorks</a></span>
    <nav aria-label="Legal"><a href="<?= BASE_PATH ?>/condiciones">Condiciones</a><a href="<?= BASE_PATH ?>/privacidad">Privacidad</a><a href="<?= BASE_PATH ?>/aviso-legal">Aviso legal</a><a href="mailto:<?= h($E['email']) ?>"><?= h($E['email']) ?></a></nav>
  </div>
</footer>
<?php // Al final: el motion toca la cabecera, el hero y las secciones, que ya tienen que existir (la CSP no deja scripts en línea) ?>
<script src="<?= BASE_PATH ?>/assets/js/landing.js?v=<?= h(ASSETS_V) ?>"></script>
</body>
</html>
<?php
    return (string) ob_get_clean();
}
