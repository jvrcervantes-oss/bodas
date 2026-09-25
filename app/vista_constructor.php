<?php
// Pantalla del constructor. Composición de la maqueta de Stitch "constructor floral
// pastel" del owner (25-sep-2026): carril lateral, cabecera con Publicar, tarjeta del
// proyecto con pestañas, vista previa dentro de un navegador con la URL real.
// Mismo HTML para crear (y pagar) y para editar desde el panel. El config inicial va
// en <script type="application/json"> con HEX_* para que ningún texto cierre la etiqueta.

declare(strict_types=1);

function vista_constructor(string $modo, array $c, string $slug, string $csrf = ''): string {
    $L = textos_legales();
    $datos = ['modo' => $modo, 'config' => $c, 'slug' => $slug, 'csrf' => $csrf,
        'temas' => array_map(fn($t) => ['nombre' => $t[0], 'color' => $t[2], 'fondo' => $t[5], 'titulo' => $t[1]], TEMAS),
        'maxMenus' => MAX_MENUS, 'maxTrayectos' => MAX_TRAYECTOS, 'secciones' => array_map(fn($s) => ['titulo' => $s[0], 'unica' => $s[2]], SECCIONES),
        'maxLibres' => MAX_LIBRES, 'dominio' => BASE_DOMAIN,
        'precio' => ['base' => euros(PRECIO_BASE_CENT), 'iva' => IVA_PCT, 'total' => euros(precio_total_cent())],
        'fotoUrl' => $modo === 'editar' && is_file(dir_boda($slug) . '/foto.webp') ? '/foto?v=' . filemtime(dir_boda($slug) . '/foto.webp') : '',
    ];
    $json = json_encode($datos, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
    $editar = $modo === 'editar';
    $titulo = $editar ? 'Editar la web' : 'Crea la web de vuestra boda';
    // Orden (owner, 25-sep): primero el estilo, luego los datos
    $tabs = ['estilo' => 'Estilo', 'pareja' => 'Vosotros', 'lugares' => 'Ceremonia y convite', 'portada' => 'Portada', 'secciones' => 'Secciones'];
    if (!$editar) $tabs['publicar'] = 'Publicar';
    $rail = $editar
        ? [['/panel/editar', 'lapiz', 'Editar la web', true], ['/panel', 'sobre', 'Invitados y respuestas', false],
           ['/panel/excel', 'excel', 'Descargar Excel', false], ['/panel/zip', 'regalo', 'Descargar ZIP', false], ['/panel/factura', 'check', 'Factura', false]]
        : [];
    // Al crear, el carril son los pasos del propio constructor: antes enlazaba a la landing
    // y sacaba a la pareja a media edición (queja del owner, 25-sep-2026)
    $pasos = $tabs;
    ob_start(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($titulo) ?> — <?= h(MARCA) ?></title>
<meta name="robots" content="noindex">
<link rel="stylesheet" href="/assets/marca.css?v=<?= h(ASSETS_V) ?>">
<link rel="stylesheet" href="/assets/crear.css?v=<?= h(ASSETS_V) ?>">
</head>
<body class="c-app" data-modo="<?= h($modo) ?>" data-ver="editor">
<script type="application/json" id="datos"><?= $json ?></script>

<aside class="c-rail" aria-label="Menú">
  <a class="c-marca" href="<?= $editar ? '/panel' : '/' ?>"><?= il('flor') ?><span><?= h(MARCA) ?></span></a>
  <nav>
<?php if ($editar): foreach ($rail as [$href, $ico, $txt, $on]): ?>
    <a href="<?= h($href) ?>"<?= $on ? ' aria-current="page"' : '' ?><?= $href === '/panel/factura' ? ' target="_blank" rel="noopener"' : '' ?>><?= il($ico) ?><?= h($txt) ?></a>
<?php endforeach; else: $n = 0; foreach ($pasos as $k => $txt): ?>
    <button type="button" class="c-paso" data-tab="<?= $k ?>" aria-selected="<?= $n++ === 0 ? 'true' : 'false' ?>"><span class="c-paso-num"><?= $n ?></span><?= h($txt) ?></button>
<?php endforeach; endif; ?>
  </nav>
  <div class="c-pareja">
    <span class="c-pareja-ini" id="railIni" aria-hidden="true">·</span>
    <span class="c-pareja-txt"><b id="railNom">Vuestra boda</b><small id="railFecha"><?= $editar ? 'Web publicada' : 'Borrador' ?></small></span>
<?php if ($editar): ?>    <a class="c-salir" href="/panel/salir" aria-label="Salir del panel"><?= il('flecha') ?></a><?php endif; ?>
  </div>
</aside>

<div class="c-main">
  <header class="c-top">
    <a class="c-marca c-marca-movil" href="<?= $editar ? '/panel' : '/' ?>"><?= il('flor') ?><span><?= h(MARCA) ?></span></a>
    <div class="c-top-tit"><h1><?= h($editar ? 'Edición de vuestra web' : 'Estudio de edición') ?></h1><span class="c-badge"><?= $editar ? 'Publicada' : 'Borrador' ?></span></div>
    <div class="c-top-acc">
      <button type="button" class="b-btn b-paper c-btn-sm c-solo-movil c-ver-previa" data-ver="previa"><?= il('ojo', 'i i-sm') ?><span>Vista previa</span></button>
      <button type="button" class="b-btn b-paper c-btn-sm c-solo-movil c-ver-editor" data-ver="editor"><?= il('lapiz', 'i i-sm') ?><span>Editar</span></button>
      <button type="button" class="b-btn b-gold c-btn-sm" id="<?= $editar ? 'guardarTop' : 'irPublicar' ?>"><?= il($editar ? 'check' : 'enlace', 'i i-sm') ?><span><?= $editar ? 'Guardar' : 'Publicar' ?></span></button>
    </div>
  </header>

  <section class="c-proyecto">
    <div class="c-proyecto-fila">
      <span class="c-proyecto-ico"><?= il('corazon') ?></span>
      <div class="c-proyecto-txt">
        <b id="proyNom">Vuestra boda</b>
        <small><i class="c-punto"></i><?= $editar ? 'Los cambios se publican al guardar' : 'Guardado en este navegador' ?></small>
      </div>
      <div class="c-disp" role="group" aria-label="Dispositivo de la vista previa">
        <button type="button" data-disp="escritorio" aria-pressed="false">Escritorio</button>
        <button type="button" data-disp="movil" aria-pressed="true">Móvil</button>
      </div>
    </div>
    <div class="c-tabs" role="tablist" aria-label="Partes del constructor">
<?php $n = 0; foreach ($tabs as $k => $t): ?>
      <button type="button" role="tab" id="tab-<?= $k ?>" data-tab="<?= $k ?>" aria-controls="panel-<?= $k ?>" aria-selected="<?= $n++ === 0 ? 'true' : 'false' ?>"><?= h($t) ?></button>
<?php endforeach; ?>
    </div>
  </section>

  <div class="c-grid">
    <section class="c-editor" aria-label="Editor">
      <div class="c-panel" role="tabpanel" id="panel-estilo" data-panel="estilo" aria-labelledby="tab-estilo">
        <span class="overline overline-bronze">Arte y papelería</span>
        <h2>Elegid el estilo</h2>
        <p class="c-ayuda"><?= $editar ? 'Cambiad lo que queráis: se publica al pulsar «Guardar».' : 'La paleta cambia los colores de toda la web y podéis cambiarla cuando queráis. Lo que hagáis se guarda en este navegador hasta que publiquéis.' ?></p>
        <div class="c-temas" id="temas" role="radiogroup" aria-label="Paleta"></div>
      </div>

      <div class="c-panel" role="tabpanel" id="panel-pareja" data-panel="pareja" aria-labelledby="tab-pareja" hidden>
        <span class="overline overline-bronze">Los protagonistas</span>
        <h2>Vosotros y la fecha</h2>
        <div class="c-fila">
          <label class="c-campo"><span>Nombre</span><input data-k="pareja.nombre1" maxlength="40" autocomplete="off"></label>
          <label class="c-campo"><span>Nombre</span><input data-k="pareja.nombre2" maxlength="40" autocomplete="off"></label>
        </div>
        <label class="c-campo"><span>Email de contacto</span><input type="email" data-k="pareja.email" maxlength="160" autocomplete="email">
          <small>Sale en la página de privacidad de vuestra web: ahí os escriben los invitados por sus datos.</small></label>
        <div class="c-fila">
          <label class="c-campo"><span>Fecha de la boda</span><input type="date" data-k="fecha"></label>
          <label class="c-campo"><span>Ciudad</span><input data-k="ciudad" maxlength="60"></label>
        </div>
      </div>

      <div class="c-panel" role="tabpanel" id="panel-lugares" data-panel="lugares" aria-labelledby="tab-lugares" hidden>
        <span class="overline overline-bronze">El gran día</span>
        <h2>Ceremonia y convite</h2>
        <div class="c-tarjeta">
          <p class="c-sub">Ceremonia</p>
          <div class="c-fila">
            <label class="c-campo c-crece"><span>Lugar</span><input data-k="ceremonia.lugar" maxlength="120"></label>
            <label class="c-campo c-hora"><span>Hora</span><input type="time" data-k="ceremonia.hora"></label>
          </div>
          <label class="c-campo"><span>Dirección</span><input data-k="ceremonia.direccion" maxlength="160"></label>
        </div>
        <div class="c-tarjeta">
          <p class="c-sub">Convite <small>(opcional)</small></p>
          <div class="c-fila">
            <label class="c-campo c-crece"><span>Lugar</span><input data-k="convite.lugar" maxlength="120"></label>
            <label class="c-campo c-hora"><span>Hora</span><input type="time" data-k="convite.hora"></label>
          </div>
          <label class="c-campo"><span>Dirección</span><input data-k="convite.direccion" maxlength="160"></label>
        </div>
      </div>

      <div class="c-panel" role="tabpanel" id="panel-portada" data-panel="portada" aria-labelledby="tab-portada" hidden>
        <span class="overline overline-bronze">La portada</span>
        <h2>Foto y bienvenida</h2>
        <span class="overline overline-bronze">Fotografía de portada</span>
        <div class="c-foto">
          <div class="c-foto-marco" id="fotoMarco"><img id="fotoImg" alt="" hidden><span id="fotoVacia"><?= il('flor') ?></span></div>
          <div class="c-foto-acc">
            <b id="fotoNom">Sin foto</b>
            <small>Vertical queda mejor. Tiene que ser vuestra o tener permiso de quien sale en ella.</small>
            <span class="c-foto-bot">
              <label class="c-link">Elegir foto<input type="file" id="fotoInput" accept="image/jpeg,image/png,image/webp" hidden></label>
              <button type="button" class="c-link c-link-mal" id="fotoQuitar" hidden>Quitar</button>
            </span>
          </div>
        </div>
        <hr class="c-hr">
        <span class="overline overline-bronze">Textos de la portada</span>
        <label class="c-campo"><span>Frase sobre los nombres</span><input data-k="portada.invitacion" maxlength="120"></label>
        <label class="c-campo"><span>Título de bienvenida</span><input data-k="portada.titulo" maxlength="80"></label>
        <label class="c-campo"><span>Frase destacada <small>(opcional)</small></span><input data-k="portada.frase" maxlength="240"></label>
        <label class="c-campo"><span>Texto de bienvenida</span><textarea data-k="portada.texto" maxlength="1200" rows="4"></textarea></label>
        <label class="c-campo"><span>Despedida al pie</span><input data-k="portada.pie" maxlength="200"></label>
      </div>

      <div class="c-panel" role="tabpanel" id="panel-secciones" data-panel="secciones" aria-labelledby="tab-secciones" hidden>
        <span class="overline overline-bronze">Módulos activos</span>
        <h2>Secciones de la web</h2>
        <p class="c-ayuda">Activad, quitad y ordenad las páginas. Tocad una para editar su contenido.</p>
        <ol class="c-secciones" id="secciones"></ol>
        <button type="button" class="b-btn b-paper c-btn-sm c-anadir" id="anadirLibre"><?= il('mas', 'i i-sm') ?>Añadir sección propia</button>
      </div>

<?php if (!$editar): ?>
      <div class="c-panel" role="tabpanel" id="panel-publicar" data-panel="publicar" aria-labelledby="tab-publicar" hidden>
        <span class="overline overline-bronze">Identidad del enlace</span>
        <h2>Publicar vuestra web</h2>
        <label class="c-campo"><span>Dirección de vuestra web</span>
          <span class="c-slug"><input id="slug" maxlength="40" autocomplete="off" spellcheck="false" placeholder="nombre1-y-nombre2"><span>.<?= h(BASE_DOMAIN) ?></span></span>
          <small id="slugEstado" aria-live="polite"></small></label>
        <ul class="c-incluye">
          <li><?= il('check', 'i i-sm') ?>Web publicada al momento, con todas vuestras secciones</li>
          <li><?= il('check', 'i i-sm') ?>Confirmaciones con menú y alergias por invitado</li>
          <li><?= il('check', 'i i-sm') ?>Panel privado con Excel para el catering</li>
          <li><?= il('check', 'i i-sm') ?>Editar la web cuando queráis y descargarla en ZIP</li>
          <li><?= il('check', 'i i-sm') ?>Online hasta <?= (int) MESES_ALOJAMIENTO ?> meses después de la boda</li>
        </ul>
        <div class="c-precio"><b><?= h(euros(precio_total_cent())) ?></b><span><?= h(euros(PRECIO_BASE_CENT)) ?> + IVA <?= (int) IVA_PCT ?> % · pago único</span></div>
        <label class="c-check"><input type="checkbox" id="aceptoCond"> <span><?= h($L['check_condiciones'] ?? '') ?> <a href="/condiciones" target="_blank" rel="noopener">Leer condiciones</a></span></label>
        <label class="c-check"><input type="checkbox" id="aceptoDes"> <span><?= h($L['check_desistimiento'] ?? '') ?></span></label>
        <ul class="c-faltan" id="faltan" aria-live="polite"></ul>
        <button type="button" class="b-btn b-dark c-btn-pagar" id="pagar">Pagar <?= h(euros(precio_total_cent())) ?></button>
        <p class="c-nota">Pago seguro con Stripe. Recibiréis la factura por email.</p>
      </div>
<?php else: ?>
      <div class="c-guardar">
        <ul class="c-faltan" id="faltan" aria-live="polite"></ul>
        <button type="button" class="b-btn b-dark c-btn-pagar" id="guardar">Guardar cambios</button>
        <p class="c-nota" id="guardarEstado" aria-live="polite"></p>
      </div>
<?php endif; ?>
    </section>

    <section class="c-previa" aria-label="Vista previa">
      <div class="c-navegador">
        <div class="c-nav-barra">
          <span class="c-dots" aria-hidden="true"><i></i><i></i><i></i></span>
          <span class="c-url" id="urlPrevia"><?= h(($slug !== '' ? $slug : 'vuestra-web') . '.' . BASE_DOMAIN) ?></span>
          <select id="paginaSel" aria-label="Página de la vista previa"></select>
          <span class="c-envivo"><i></i>En vivo</span>
        </div>
        <div class="c-marco" id="marco" data-disp="movil">
          <iframe id="previa" title="Vista previa de la web" sandbox="allow-scripts"></iframe>
        </div>
      </div>
    </section>
  </div>
<?php if (!$editar): ?><?= pie_creador() ?><?php endif; ?>
</div>
<script src="/assets/js/crear.js?v=<?= h(ASSETS_V) ?>" defer></script>
</body>
</html>
<?php
    return (string) ob_get_clean();
}
