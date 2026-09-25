<?php
// Rutas del creador (CREATOR_HOST): la página del constructor, la vista previa, la
// comprobación del nombre, el pago, el webhook de Stripe y la página de éxito.

declare(strict_types=1);

const CSP_CREADOR = "default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline'; font-src 'self'; "
    . "script-src 'self'; connect-src 'self'; frame-src 'self' https://maps.google.com https://www.google.com; "
    . "form-action 'self' https://checkout.stripe.com; frame-ancestors 'none'; base-uri 'self'; object-src 'none'";

function rutas_creador(string $ruta, string $metodo): void {
    switch ($ruta) {
        case '':
            header('Content-Security-Policy: ' . CSP_CREADOR);
            echo vista_constructor('crear', config_inicial(), '');
            return;
        case 'condiciones': case 'privacidad': case 'aviso-legal':
            header('Content-Security-Policy: ' . CSP_CREADOR);
            $t = ['condiciones' => 'Condiciones de contratación', 'privacidad' => 'Privacidad', 'aviso-legal' => 'Aviso legal'][$ruta];
            $E = empresa();
            ob_start();
            include APP_DIR . '/legal/' . $ruta . '.php';
            echo pagina_simple($t, '<article class="legal">' . ob_get_clean() . '</article>');
            return;
        case 'api/vista-previa':
            api_vista_previa($metodo, url_creador('assets/'));
            return;
        case 'api/nombre':
            api_nombre();
            return;
        case 'api/pagar':
            api_pagar($metodo);
            return;
        case 'api/stripe':
            api_webhook($metodo);
            return;
        case 'listo':
            header('Content-Security-Policy: ' . CSP_CREADOR);
            pagina_listo();
            return;
    }
    no_existe();
}

/** Vista previa: se renderiza con el MISMO generador, no se guarda nada, y solo por POST
 *  (un POST no se puede enlazar: nadie puede montar con esto una página falsa en nuestro dominio). */
function api_vista_previa(string $metodo, string $assets): void {
    if ($metodo !== 'POST') json_response(['ok' => false], 405);
    if (!limite('vp|' . ip_cliente(), 1500, 3600)) json_response(['ok' => false, 'error' => 'Demasiadas peticiones.'], 429);
    $raw = file_get_contents('php://input', false, null, 0, 200000);
    $in = json_decode((string) $raw, true);
    if (!is_array($in)) json_response(['ok' => false], 400);
    $c = normaliza_config($in['config'] ?? []);
    $pagina = clean_str($in['pagina'] ?? '', 40);
    $html = render_pagina($c, $pagina === 'inicio' ? '' : $pagina, ['modo' => 'preview', 'assets' => $assets, 'foto' => '']);
    if ($html === null) $html = render_pagina($c, '', ['modo' => 'preview', 'assets' => $assets, 'foto' => '']);
    $paginas = [['inicio', 'Inicio']];
    foreach ($c['secciones'] as $s) if ($s['on']) $paginas[] = [$s['ruta'], $s['titulo']];
    $paginas[] = ['privacidad', 'Privacidad'];
    json_response(['ok' => true, 'html' => $html, 'paginas' => $paginas, 'faltan' => faltan($c)]);
}

function api_nombre(): void {
    if (!limite('nombre|' . ip_cliente(), 300, 3600)) json_response(['ok' => false, 'error' => 'Demasiadas peticiones.'], 429);
    $s = strtolower(clean_str($_GET['s'] ?? '', 60));
    if (!preg_match(SLUG_RE, $s) || strpos($s, '--') !== false) {
        json_response(['ok' => true, 'libre' => false, 'motivo' => 'Entre 3 y 40 caracteres: letras sin tilde, números y guiones.']);
    }
    if (in_array($s, SLUGS_RESERVADOS, true)) json_response(['ok' => true, 'libre' => false, 'motivo' => 'Ese nombre está reservado.']);
    json_response(['ok' => true, 'libre' => slug_libre($s), 'motivo' => slug_libre($s) ? '' : 'Ese nombre ya está cogido.']);
}

function api_pagar(string $metodo): void {
    if ($metodo !== 'POST') json_response(['ok' => false], 405);
    if (!limite('pagar|' . ip_cliente(), 20, 3600)) json_response(['ok' => false, 'error' => 'Demasiados intentos. Prueba dentro de un rato.'], 429);
    // En LIVE no se vende sin los datos del titular en los textos legales y la factura
    if (stripe_modo_live() && !empresa_completa()) {
        registra('ALERTA pago bloqueado: faltan datos del titular');
        json_response(['ok' => false, 'error' => 'La venta está en pausa un momento. Vuelve a intentarlo más tarde.'], 503);
    }
    if (($_POST['acepto_condiciones'] ?? '') !== 'si' || ($_POST['acepto_desistimiento'] ?? '') !== 'si') {
        json_response(['ok' => false, 'error' => 'Marca las dos casillas para continuar.'], 422);
    }
    $c = normaliza_config(json_decode((string) ($_POST['config'] ?? ''), true));
    $f = faltan($c);
    if ($f) json_response(['ok' => false, 'error' => 'Faltan datos.', 'faltan' => $f], 422);
    $slug = strtolower(clean_str($_POST['slug'] ?? '', 60));
    if (!slug_valido($slug)) json_response(['ok' => false, 'error' => 'El nombre de la web no es válido.'], 422);

    asegura_dir(dir_datos('pendientes'));
    if (count(glob(dir_datos('pendientes', '*'), GLOB_ONLYDIR) ?: []) > 500) {
        registra('ALERTA demasiados pedidos pendientes');
        json_response(['ok' => false, 'error' => 'Ahora mismo no podemos atenderte. Prueba en unos minutos.'], 503);
    }
    $token = bin2hex(random_bytes(16));
    $pend = dir_datos('pendientes', $token);

    // Reserva del nombre dentro del cerrojo: dos parejas no pueden pagar el mismo a la vez
    $reservado = con_cerrojo(function () use ($slug, $token) {
        if (!slug_libre($slug)) return false;
        escribe_json(dir_datos('reservas', $slug . '.json'), ['token' => $token, 'hasta' => time() + 1860]);
        return true;
    });
    if (!$reservado) json_response(['ok' => false, 'error' => 'Ese nombre de web ya está cogido. Elige otro.', 'faltan' => ['slug' => 'Ese nombre ya está cogido.']], 409);

    asegura_dir($pend);
    $fr = guarda_foto('foto', $pend . '/foto.webp');
    if ($fr !== '' && $fr !== 'sin-foto') { borra_arbol($pend); @unlink(dir_datos('reservas', $slug . '.json')); json_response(['ok' => false, 'error' => $fr], 422); }
    $c['foto'] = $fr === '';
    escribe_json($pend . '/config.json', $c);
    $L = textos_legales();
    escribe_json($pend . '/meta.json', ['slug' => $slug, 'creado' => time(), 'aceptacion' => [
        'fecha' => date('c'), 'version' => $L['version'] ?? '', 'condiciones' => $L['check_condiciones'] ?? '', 'desistimiento' => $L['check_desistimiento'] ?? '',
    ]]);

    [$st, $s] = stripe_crea_checkout($token, $slug, $c['pareja']['email']);
    if ($st !== 200 || empty($s['url'])) {
        registra('stripe: no se pudo crear la sesión', ['status' => $st, 'error' => $s['error']['message'] ?? '']);
        borra_arbol($pend);
        @unlink(dir_datos('reservas', $slug . '.json'));
        json_response(['ok' => false, 'error' => 'No hemos podido abrir el pago. Inténtalo de nuevo.'], 502);
    }
    json_response(['ok' => true, 'url' => $s['url']]);
}

function api_webhook(string $metodo): void {
    if ($metodo !== 'POST') json_response(['ok' => false], 405);
    $payload = (string) file_get_contents('php://input', false, null, 0, 1000000);
    $ev = stripe_verifica_webhook($payload, (string) ($_SERVER['HTTP_STRIPE_SIGNATURE'] ?? ''));
    if (!$ev) json_response(['ok' => false], 400);
    $tipo = (string) ($ev['type'] ?? '');
    $obj = $ev['data']['object'] ?? [];
    // Eventos de otros proyectos de la cuenta compartida: 200 y fuera, sin tocar nada
    if (($obj['metadata']['producto'] ?? '') !== PRODUCTO) json_response(['ok' => true, 'ignorado' => true]);
    if (in_array($tipo, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
        // Se vuelve a pedir la sesión a Stripe: la verdad es la API, no el cuerpo recibido
        $s = stripe_lee_sesion((string) ($obj['id'] ?? ''));
        if ($s && sesion_pagada_nuestra($s)) alta_desde_sesion($s);
    } elseif ($tipo === 'checkout.session.expired') {
        $tok = (string) ($obj['client_reference_id'] ?? '');
        if (preg_match('/^[a-f0-9]{32}$/', $tok)) {
            borra_arbol(dir_datos('pendientes', $tok));
            $slug = (string) ($obj['metadata']['slug'] ?? '');
            $r = slug_valido($slug) ? lee_json(dir_datos('reservas', $slug . '.json')) : null;
            if ($r && ($r['token'] ?? '') === $tok) @unlink(dir_datos('reservas', $slug . '.json'));
        }
    }
    json_response(['ok' => true]);
}

function pagina_listo(): void {
    cabeceras_privadas();
    $sid = clean_str($_GET['sid'] ?? '', 250);
    $s = stripe_lee_sesion($sid);
    if (!$s || ($s['metadata']['producto'] ?? '') !== PRODUCTO) { echo pagina_simple('Pago no encontrado', '<p>No encontramos este pago. Si te han cobrado, escríbenos a ' . h(empresa()['email']) . '.</p>'); return; }
    if (!sesion_pagada_nuestra($s)) { echo pagina_simple('Pago pendiente', '<p>Stripe todavía no ha confirmado el pago. Recarga esta página en un minuto; te llegará también un email.</p>'); return; }
    $ped = alta_desde_sesion($s);
    if (!$ped || ($ped['estado'] ?? '') !== 'creada') {
        echo pagina_simple('Pago recibido', '<p>Hemos recibido el pago, pero no hemos podido crear la web automáticamente. Ya nos ha llegado el aviso y te escribimos en breve a ' . h($ped['email'] ?? '') . '.</p>');
        return;
    }
    $slug = $ped['slug'];
    $url = url_boda($slug);
    $enlace = '';
    // El enlace para elegir contraseña se enseña UNA vez aquí (y siempre va por email).
    // Una URL /listo reenviada o del historial ya no lo muestra.
    $mostrar = con_cerrojo(function () use ($ped) {
        $f = dir_datos('pedidos', $ped['session_id'] . '.json');
        $p = lee_json($f) ?? [];
        if (!empty($p['enlace_mostrado'])) return false;
        $p['enlace_mostrado'] = date('c');
        escribe_json($f, $p);
        return true;
    });
    if ($mostrar && !panel_tiene_clave($slug)) $enlace = panel_nuevo_enlace($slug);
    $o = '<p class="lede">Vuestra web ya está publicada en:</p><p><a class="btn" href="' . h($url) . '" target="_blank" rel="noopener">' . h(preg_replace('~^https?://~', '', rtrim($url, '/'))) . '</a></p>';
    if ($enlace !== '') {
        $o .= '<p>Ahora elegid la contraseña de vuestro panel, desde donde veréis las respuestas, editaréis la web y descargaréis el ZIP:</p><p><a class="btn btn-sec" href="' . h($enlace) . '">Elegir contraseña</a></p>';
    }
    $o .= '<p class="nota">Os hemos enviado un email a ' . h($ped['email']) . ' con el enlace del panel, la factura (' . h($ped['factura']) . ') y las condiciones.</p>';
    echo pagina_simple('¡Enhorabuena!', $o);
}

/** Página sencilla del creador (legales, éxito). */
function pagina_simple(string $titulo, string $cuerpo): string {
    return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . h($titulo) . ' — Webs de boda · AxisWorks</title><meta name="robots" content="noindex">'
        . '<link rel="stylesheet" href="/assets/crear.css?v=' . h(ASSETS_V) . '"></head><body class="simple">'
        . '<header class="c-top"><a class="c-logo" href="/">Webs de boda <span>AxisWorks</span></a></header>'
        . '<main class="simple-main"><h1>' . h($titulo) . '</h1>' . $cuerpo . '</main>' . pie_creador() . '</body></html>';
}

function pie_creador(): string {
    return '<footer class="c-pie"><a href="/condiciones">Condiciones</a><a href="/privacidad">Privacidad</a><a href="/aviso-legal">Aviso legal</a><span>' . h(empresa()['email']) . '</span></footer>';
}

/**
 * La página del constructor. Mismo HTML para crear (y pagar) y para editar desde el
 * panel: cambia el modo y a dónde se envía. El config inicial va en un <script
 * type="application/json"> (json_encode con HEX_* para que ningún texto cierre la etiqueta).
 */
function vista_constructor(string $modo, array $c, string $slug, string $csrf = ''): string {
    $L = textos_legales();
    $datos = ['modo' => $modo, 'config' => $c, 'slug' => $slug, 'csrf' => $csrf,
        'temas' => array_map(fn($t) => ['nombre' => $t[0], 'color' => $t[2], 'fondo' => $t[5]], TEMAS),
        'menus' => MENUS, 'secciones' => array_map(fn($s) => ['titulo' => $s[0], 'unica' => $s[2]], SECCIONES),
        'maxLibres' => MAX_LIBRES, 'dominio' => BASE_DOMAIN,
        'precio' => ['base' => euros(PRECIO_BASE_CENT), 'iva' => IVA_PCT, 'total' => euros(precio_total_cent())],
        'fotoUrl' => $modo === 'editar' && is_file(dir_boda($slug) . '/foto.webp') ? '/foto?v=' . filemtime(dir_boda($slug) . '/foto.webp') : '',
    ];
    $json = json_encode($datos, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
    $titulo = $modo === 'editar' ? 'Editar la web' : 'Crea la web de vuestra boda';
    ob_start(); ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= h($titulo) ?> — AxisWorks</title>
<meta name="description" content="Montad la web de vuestra boda en minutos: confirmación de asistencia, menús y alergias, música, hoteles y lista de bodas. Vista previa en directo.">
<?php if ($modo === 'editar'): ?><meta name="robots" content="noindex"><?php endif; ?>
<link rel="stylesheet" href="/assets/crear.css?v=<?= h(ASSETS_V) ?>">
</head>
<body class="c-app" data-modo="<?= h($modo) ?>">
<script type="application/json" id="datos"><?= $json ?></script>

<header class="c-top">
  <a class="c-logo" href="<?= $modo === 'editar' ? '/panel' : '/' ?>"><?= $modo === 'editar' ? '← Volver al panel' : 'Webs de boda <span>AxisWorks</span>' ?></a>
  <div class="c-vista-toggle" role="group" aria-label="Ver">
    <button type="button" data-ver="editor" aria-pressed="true">Editar</button>
    <button type="button" data-ver="previa" aria-pressed="false">Vista previa</button>
  </div>
</header>

<div class="c-grid">
  <section class="c-editor" aria-label="Editor">
    <div class="c-intro">
      <h1><?= h($titulo) ?></h1>
<?php if ($modo === 'crear'): ?>
      <p>Rellenad los datos, activad las secciones que queráis y ved el resultado en la vista previa. Lo que escribís se guarda en este navegador hasta que publiquéis.</p>
<?php else: ?>
      <p>Los cambios se publican al pulsar «Guardar cambios».</p>
<?php endif; ?>
    </div>

    <details class="c-bloque" open data-bloque="pareja">
      <summary><span class="c-num">1</span> Vosotros y la fecha</summary>
      <div class="c-campos">
        <div class="c-fila">
          <label class="c-campo"><span>Nombre</span><input data-k="pareja.nombre1" maxlength="40" autocomplete="off"></label>
          <label class="c-campo"><span>Nombre</span><input data-k="pareja.nombre2" maxlength="40" autocomplete="off"></label>
        </div>
        <label class="c-campo"><span>Email de contacto</span><input type="email" data-k="pareja.email" maxlength="160" autocomplete="email">
          <small>Sale en la página de privacidad de vuestra web: los invitados os escriben ahí por sus datos.</small></label>
        <div class="c-fila">
          <label class="c-campo"><span>Fecha de la boda</span><input type="date" data-k="fecha"></label>
          <label class="c-campo"><span>Ciudad</span><input data-k="ciudad" maxlength="60"></label>
        </div>
      </div>
    </details>

    <details class="c-bloque" data-bloque="lugares">
      <summary><span class="c-num">2</span> Ceremonia y convite</summary>
      <div class="c-campos">
        <p class="c-sub">Ceremonia</p>
        <div class="c-fila">
          <label class="c-campo c-crece"><span>Lugar</span><input data-k="ceremonia.lugar" maxlength="120"></label>
          <label class="c-campo c-hora"><span>Hora</span><input type="time" data-k="ceremonia.hora"></label>
        </div>
        <label class="c-campo"><span>Dirección</span><input data-k="ceremonia.direccion" maxlength="160"></label>
        <p class="c-sub">Convite <small>(opcional)</small></p>
        <div class="c-fila">
          <label class="c-campo c-crece"><span>Lugar</span><input data-k="convite.lugar" maxlength="120"></label>
          <label class="c-campo c-hora"><span>Hora</span><input type="time" data-k="convite.hora"></label>
        </div>
        <label class="c-campo"><span>Dirección</span><input data-k="convite.direccion" maxlength="160"></label>
      </div>
    </details>

    <details class="c-bloque" data-bloque="portada">
      <summary><span class="c-num">3</span> Portada, foto y colores</summary>
      <div class="c-campos">
        <div class="c-foto">
          <div class="c-foto-marco" id="fotoMarco"><img id="fotoImg" alt="" hidden><span id="fotoVacia">Sin foto</span></div>
          <div class="c-foto-acc">
            <label class="c-btn c-btn-sec">Elegir foto<input type="file" id="fotoInput" accept="image/jpeg,image/png,image/webp" hidden></label>
            <button type="button" class="c-link" id="fotoQuitar" hidden>Quitar foto</button>
            <small>Vertical queda mejor. Tiene que ser vuestra o tener permiso de quien sale en ella.</small>
          </div>
        </div>
        <fieldset class="c-temas"><legend>Colores</legend><div id="temas"></div></fieldset>
        <label class="c-campo"><span>Frase sobre los nombres</span><input data-k="portada.invitacion" maxlength="120"></label>
        <label class="c-campo"><span>Título de bienvenida</span><input data-k="portada.titulo" maxlength="80"></label>
        <label class="c-campo"><span>Frase destacada <small>(opcional)</small></span><input data-k="portada.frase" maxlength="240"></label>
        <label class="c-campo"><span>Texto de bienvenida</span><textarea data-k="portada.texto" maxlength="1200" rows="4"></textarea></label>
        <label class="c-campo"><span>Despedida al pie</span><input data-k="portada.pie" maxlength="200"></label>
      </div>
    </details>

    <details class="c-bloque" open data-bloque="secciones">
      <summary><span class="c-num">4</span> Secciones</summary>
      <div class="c-campos">
        <p class="c-ayuda">Activad, quitad y ordenad las páginas de vuestra web. Tocad una para editar su contenido.</p>
        <ol class="c-secciones" id="secciones"></ol>
        <button type="button" class="c-btn c-btn-sec c-anadir" id="anadirLibre">+ Añadir sección propia</button>
      </div>
    </details>

<?php if ($modo === 'crear'): ?>
    <details class="c-bloque" open data-bloque="publicar">
      <summary><span class="c-num">5</span> Publicar</summary>
      <div class="c-campos">
        <label class="c-campo"><span>Dirección de vuestra web</span>
          <div class="c-slug"><input id="slug" maxlength="40" autocomplete="off" spellcheck="false" placeholder="nombre1-y-nombre2"><span>.<?= h(BASE_DOMAIN) ?></span></div>
          <small id="slugEstado" aria-live="polite"></small></label>
        <ul class="c-incluye">
          <li>Web publicada al momento, con todas vuestras secciones</li>
          <li>Confirmaciones con menú y alergias por invitado</li>
          <li>Panel privado con Excel para el catering</li>
          <li>Editar la web cuando queráis y descargarla en ZIP</li>
          <li>Alojada hasta <?= (int) MESES_ALOJAMIENTO ?> meses después de la boda</li>
        </ul>
        <div class="c-precio"><b><?= h(euros(precio_total_cent())) ?></b><span><?= h(euros(PRECIO_BASE_CENT)) ?> + IVA <?= (int) IVA_PCT ?> % · pago único</span></div>
        <label class="c-check"><input type="checkbox" id="aceptoCond"> <span><?= h($L['check_condiciones'] ?? '') ?> <a href="/condiciones" target="_blank" rel="noopener">Leer condiciones</a></span></label>
        <label class="c-check"><input type="checkbox" id="aceptoDes"> <span><?= h($L['check_desistimiento'] ?? '') ?></span></label>
        <ul class="c-faltan" id="faltan" aria-live="polite"></ul>
        <button type="button" class="c-btn c-btn-pagar" id="pagar">Pagar <?= h(euros(precio_total_cent())) ?></button>
        <p class="c-nota">Pago seguro con Stripe. Recibiréis la factura por email.</p>
      </div>
    </details>
<?php else: ?>
    <div class="c-guardar">
      <ul class="c-faltan" id="faltan" aria-live="polite"></ul>
      <button type="button" class="c-btn c-btn-pagar" id="guardar">Guardar cambios</button>
      <p class="c-nota" id="guardarEstado" aria-live="polite"></p>
    </div>
<?php endif; ?>
  </section>

  <section class="c-previa" aria-label="Vista previa">
    <div class="c-previa-barra">
      <select id="paginaSel" aria-label="Página"></select>
      <div class="c-disp" role="group" aria-label="Dispositivo">
        <button type="button" data-disp="movil" aria-pressed="true">Móvil</button>
        <button type="button" data-disp="escritorio" aria-pressed="false">Escritorio</button>
      </div>
    </div>
    <div class="c-marco" id="marco" data-disp="movil">
      <iframe id="previa" title="Vista previa de la web" sandbox="allow-scripts"></iframe>
    </div>
  </section>
</div>
<?php if ($modo === 'crear'): ?><?= pie_creador() ?><?php endif; ?>
<script src="/assets/js/crear.js?v=<?= h(ASSETS_V) ?>" defer></script>
</body>
</html>
<?php
    return (string) ob_get_clean();
}
