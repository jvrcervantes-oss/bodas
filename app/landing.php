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
    'anillos' => 'M9 6.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11zM15 6.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11z',
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
];
function il(string $n, string $cls = 'i'): string {
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
    [$webNombre, $webTld] = array_pad(explode('.', MARCA_WEB, 2), 2, '');
    $E = empresa();
    $paletas = array_map(fn($t) => $t[2], TEMAS);
    $ejemplo = 'lucia-y-marcos.' . BASE_DOMAIN;
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
<title><?= h(marca()) ?> — La web de vuestra boda con confirmación de asistencia</title>
<meta name="description" content="Cread la web de vuestra boda en un rato: confirmación de asistencia con menú y alergias, mapa de la ceremonia y el convite, galería, libro de invitados, música y lista de bodas. Desde <?= h($total) ?> en un pago único.">
<link rel="canonical" href="<?= h(url_creador()) ?>">
<meta property="og:title" content="<?= h(marca()) ?> — La web de vuestra boda">
<meta property="og:description" content="Confirmación de asistencia con menú y alergias, cuenta atrás, música, hoteles y lista de bodas. Pago único de <?= h($total) ?>.">
<meta property="og:type" content="website">
<meta property="og:image" content="<?= h(url_creador('assets/img/landing/demo-escritorio.webp')) ?>">
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/marca.css?v=<?= h(ASSETS_V) ?>">
<link rel="stylesheet" href="<?= BASE_PATH ?>/assets/landing.css?v=<?= h(ASSETS_V) ?>">
</head>
<body class="l">

<header class="l-top">
  <div class="l-top-in">
    <a class="l-logo l-logo-web" href="<?= BASE_PATH ?>/" aria-label="<?= h(MARCA_WEB) ?>, inicio"><?= il('anillos', 'i i-anillos') ?><span><?= h($webNombre) ?><em>.<?= h($webTld) ?></em></span></a>
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
    <img class="l-deco l-deco-hero-a" src="<?= BASE_PATH ?>/assets/img/landing/enredadera.webp" alt="" width="896" height="1200">
    <img class="l-deco l-deco-hero-b" src="<?= BASE_PATH ?>/assets/img/landing/enredadera-2.webp" alt="" width="896" height="1200">
    <div class="l-wrap l-hero-grid">
      <div class="l-hero-txt">
        <span class="l-chip"><?= il('flor', 'i i-sm') ?>Web de boda con confirmación de asistencia</span>
        <h1>La web de vuestra boda, <em>tan bonita</em> como el gran día.</h1>
        <p class="l-lede">Montadla en un rato, desde el móvil o el ordenador: confirmación de asistencia con menú y alergias, mapa de la ceremonia y el convite, galería, libro de invitados, música y lista de bodas. Sin tocar código.</p>
        <div class="l-ctas">
          <a class="b-btn b-rose" href="<?= BASE_PATH ?>/crear">Probar el constructor <?= il('flecha', 'i i-sm i-arrow') ?></a>
          <a class="b-btn b-paper" href="#pasos">Cómo funciona</a>
        </div>
        <dl class="l-datos">
          <div><dt><?= h($total) ?></dt><dd>Desde, pago único al publicar</dd></div>
          <div><dt>0 €</dt><dd>Crear y probar la web</dd></div>
          <div><dt><?= (int) MESES_ALOJAMIENTO ?> meses</dt><dd>Online después de la boda</dd></div>
        </dl>
      </div>
      <div class="l-hero-vis">
        <div class="l-marco">
          <img class="l-marco-fondo" src="<?= BASE_PATH ?>/assets/img/landing/papel.webp" alt="" width="1376" height="768">
          <?php // Spot v8 (infraestructura/remotion, Spot-Bodas-ES) recodificado para web: 540×960, sin audio ?>
          <video class="l-spot" src="<?= BASE_PATH ?>/assets/img/landing/spot-v8.mp4" poster="<?= BASE_PATH ?>/assets/img/landing/spot-v8-poster.webp" width="540" height="960" autoplay muted loop playsinline preload="metadata" aria-label="Vídeo de ejemplo: cómo se crea una web de boda con <?= h(marca()) ?>, sus paletas, los diseños Atelier, la confirmación de asistencia y los dos packs"></video>
          <script src="<?= BASE_PATH ?>/assets/js/landing.js?v=<?= h(ASSETS_V) ?>"></script>
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
          <h2>Cada detalle resuelto, antes de decir el <em>«sí, quiero»</em>.</h2>
        </div>
        <p>Lo que vuestros invitados necesitan saber y lo que vosotros necesitáis recoger, en un solo enlace.</p>
      </div>
      <div class="l-cards">
        <article class="l-card">
          <span class="l-card-ico"><?= il('sobre') ?></span>
          <h3>Confirmación por grupo</h3>
          <p>Uno confirma por toda su familia, con el menú y las alergias de cada persona. Vosotros lo descargáis en Excel para el catering.</p>
          <span class="l-card-pie">Excel para el catering <?= il('excel', 'i i-sm') ?></span>
        </article>
        <article class="l-card">
          <span class="l-card-ico"><?= il('reloj') ?></span>
          <h3>Cuenta atrás y música</h3>
          <p>La cuenta atrás hasta el gran día y una lista de canciones que proponen y votan vuestros invitados.</p>
          <span class="l-card-pie">Votos de los invitados <?= il('musica', 'i i-sm') ?></span>
        </article>
        <article class="l-card">
          <span class="l-card-ico"><?= il('mapa') ?></span>
          <h3>Un mapa con los dos sitios</h3>
          <p>La ceremonia y el convite en un solo mapa, con los colores de vuestra web, y botón para abrir cada uno en Google Maps. La fecha, lista para el calendario del móvil.</p>
          <span class="l-card-pie">Añadir al calendario <?= il('calendario', 'i i-sm') ?></span>
        </article>
        <article class="l-card">
          <span class="l-card-ico"><?= il('regalo') ?></span>
          <h3>Lista de bodas</h3>
          <p>Vuestro número de cuenta con botón para copiarlo. Comprobamos que el IBAN esté bien escrito antes de publicarlo.</p>
          <span class="l-card-pie">IBAN comprobado <?= il('check', 'i i-sm') ?></span>
        </article>
        <article class="l-card">
          <span class="l-card-ico"><?= il('ojo') ?></span>
          <h3>Galería y libro de invitados</h3>
          <p>Vuestras fotos, y un libro donde los invitados os dejan mensajes y fotos. Todo protegido con un código que solo tienen ellos.</p>
          <span class="l-card-pie">Protegido con código <?= il('escudo', 'i i-sm') ?></span>
        </article>
        <article class="l-card">
          <span class="l-card-ico"><?= il('lapiz') ?></span>
          <h3>Menús y transporte a medida</h3>
          <p>Los menús que tengáis (carne, pescado, vegetariano, infantil…) y, si ponéis autobús, sus trayectos y horarios. Cada invitado elige al confirmar.</p>
          <span class="l-card-pie">Cada invitado elige <?= il('check', 'i i-sm') ?></span>
        </article>
      </div>
    </div>
  </section>

  <section class="l-sec l-pasos" id="pasos">
    <img class="l-guirnalda" src="<?= BASE_PATH ?>/assets/img/landing/guirnalda.webp" alt="" width="1376" height="768">
    <div class="l-wrap">
      <div class="l-sec-cab l-centro">
        <span class="overline">Fácil y al momento</span>
        <h2>Vuestra web en tres pasos</h2>
        <p>No hace falta saber de diseño ni de informática. Lo que escribís se ve al instante en la vista previa.</p>
      </div>
      <ol class="l-pasos-lista">
        <li>
          <span class="l-num">01</span>
          <h3>Elegid el estilo</h3>
          <p><?= count(TEMAS) ?> paletas, 4 tipografías y 4 decoraciones para montar el vuestro, o uno de los <?= count(ATELIER) ?> diseños ilustrados de la Colección Atelier.</p>
          <span class="l-swatches"><?php foreach ($paletas as $col): ?><i style="background:<?= h($col) ?>"></i><?php endforeach; ?></span>
        </li>
        <li>
          <span class="l-num">02</span>
          <h3>Rellenad datos y foto</h3>
          <p>Nombres, fecha, lugares, vuestra foto y las secciones que queráis: activad, quitad y ordenad las páginas.</p>
          <span class="l-paso-pie"><?= il('lapiz', 'i i-sm') ?>Vista previa en directo</span>
        </li>
        <li>
          <span class="l-num">03</span>
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
            <span class="l-swatches l-swatches-sm"><?php foreach ($paletas as $i => $col): ?><i class="<?= $i === 'rosa' ? 'on' : '' ?>" style="background:<?= h($col) ?>"></i><?php endforeach; ?></span>
          </div>
          <div class="l-demo-previa">
            <img src="<?= BASE_PATH ?>/assets/img/landing/demo-escritorio.webp" alt="Ejemplo de la vista previa en ordenador de una web de boda" width="1280" height="800" loading="lazy">
            <span class="l-ejemplo">Ejemplo</span>
          </div>
        </div>
        <div class="l-demo-pie">
          <span class="l-flota-ico"><?= il('flor') ?></span>
          <p><b>¿Lo probáis?</b> Crear la web y verla en la vista previa es gratis. Solo se paga al publicar.</p>
          <a class="b-btn b-gold" href="<?= BASE_PATH ?>/crear">Abrir el constructor</a>
        </div>
      </div>
    </div>
  </section>

  <section class="l-sec l-atelier" id="atelier">
    <div class="l-wrap">
      <div class="l-sec-cab">
        <div>
          <span class="overline">Colección Atelier</span>
          <h2>Diseños ilustrados, <em>como papelería fina</em>.</h2>
        </div>
        <p><?= count(ATELIER) ?> diseños, cada uno con su paleta, sus letras y su ilustración. La invitación llega en un sobre cerrado: vuestros invitados rompen el sello, se abre la solapa y aparece la web. Pack Atelier: <?= h($totalAtelier) ?>, IVA incluido.</p>
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

  <section class="l-sec l-confianza">
    <div class="l-wrap">
      <ul class="l-confianza-lista">
        <li><?= il('escudo') ?><span><b>Datos de invitados protegidos</b>Solo los veis vosotros y se borran <?= (int) MESES_ALOJAMIENTO ?> meses después de la boda.</span></li>
        <li><?= il('ojo') ?><span><b>Fuera de los buscadores</b>Vuestra web no aparece en Google: solo la ve quien tiene el enlace.</span></li>
        <li><?= il('check') ?><span><b>Sin publicidad ni suscripciones</b>Un único pago, con factura, a través de Stripe.</span></li>
      </ul>
    </div>
  </section>

  <section class="l-sec" id="precio">
    <div class="l-wrap">
      <div class="l-sec-cab l-centro">
        <span class="overline">Precio</span>
        <h2>Dos packs, <em>todo incluido</em>.</h2>
        <p>Sin suscripciones ni extras. Pagáis una vez, cuando la web está como queréis.</p>
      </div>
      <div class="l-packs">
        <div class="l-precio-card">
          <span class="overline overline-bronze">Esencial</span>
          <div class="l-precio-cifra"><b><?= h($total) ?></b><span>IVA incluido · pago único</span></div>
          <ul>
            <li><?= il('check', 'i i-sm') ?>Web publicada al momento, con todas vuestras secciones</li>
            <li><?= il('check', 'i i-sm') ?>Confirmaciones con menú y alergias por invitado</li>
            <li><?= il('check', 'i i-sm') ?>Panel privado con Excel para el catering</li>
            <li><?= il('check', 'i i-sm') ?><?= count(TEMAS) ?> paletas, 4 tipografías y 4 decoraciones</li>
            <li><?= il('check', 'i i-sm') ?>Mapa de la ceremonia y el convite</li>
            <li><?= il('check', 'i i-sm') ?>Galería y libro de invitados</li>
            <li><?= il('check', 'i i-sm') ?>Cambios ilimitados y descarga en ZIP</li>
          </ul>
          <a class="b-btn b-paper" href="<?= BASE_PATH ?>/crear">Empezar gratis</a>
        </div>
        <div class="l-precio-card l-precio-atelier">
          <span class="overline">Atelier · diseño ilustrado</span>
          <div class="l-precio-cifra"><b><?= h($totalAtelier) ?></b><span>IVA incluido · pago único</span></div>
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
<?php foreach ($faq as [$q, $a]): ?>
      <details class="l-faq-item">
        <summary><?= h($q) ?><svg class="i i-sm" viewBox="0 0 24 24" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg></summary>
        <p><?= h($a) ?></p>
      </details>
<?php endforeach; ?>
    </div>
  </section>

  <section class="l-sec">
    <div class="l-wrap">
      <div class="l-final">
        <div>
          <span class="l-chip l-chip-dark"><?= il('flor', 'i i-sm') ?>Vuestro momento es ahora</span>
          <h2>Cread hoy la web que vuestros invitados van a abrir una y otra vez.</h2>
          <p>Sin suscripciones. Un único pago al publicar, y la web vuestra hasta <?= (int) MESES_ALOJAMIENTO ?> meses después de la boda.</p>
        </div>
        <div class="l-final-ctas">
          <a class="b-btn b-rose" href="<?= BASE_PATH ?>/crear">Crear nuestra web</a>
          <a class="b-btn l-btn-ghost" href="#precio">Ver precio</a>
        </div>
      </div>
    </div>
  </section>
</main>

<footer class="l-pie">
  <div class="l-wrap l-pie-in">
    <a class="l-logo l-logo-pie" href="<?= BASE_PATH ?>/"><?= il('anillos', 'i i-anillos') ?><span><?= h($webNombre) ?> <small>by AxisWorks</small></span></a>
    <nav aria-label="Legal"><a href="<?= BASE_PATH ?>/condiciones">Condiciones</a><a href="<?= BASE_PATH ?>/privacidad">Privacidad</a><a href="<?= BASE_PATH ?>/aviso-legal">Aviso legal</a><a href="mailto:<?= h($E['email']) ?>"><?= h($E['email']) ?></a></nav>
  </div>
</footer>
</body>
</html>
<?php
    return (string) ob_get_clean();
}
