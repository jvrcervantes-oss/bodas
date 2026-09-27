<?php
// Panel de la pareja por HTTP (rediseño del 27-sep-2026): la app en `php -S` (router_prueba.php), sin red. Recorre todas
// las rutas del panel sin sesión (van al login) y con sesión (200, `private, no-store`, la marca de BodaEnlace y NO el
// tema de la boda), comprueba que cada sección enseña los datos reales de la boda, que los filtros y la búsqueda van por
// URL, que las acciones siguen en sus POST de siempre con CSRF y vuelven a su sección, y que lo que escriben los
// invitados sale escapado. Uso: php tests/panel_http_test.php (sale con 1 si falla)

declare(strict_types=1);

$tmp = sys_get_temp_dir() . '/bodas_panel_test_' . bin2hex(random_bytes(4));
$web = sys_get_temp_dir() . '/bodas_panel_web_' . bin2hex(random_bytes(4));
foreach ([$tmp, $web] as $d) mkdir($d, 0700, true);
define('SIN_CONFIG_LOCAL', true);
define('DATA_DIR', $tmp);
define('BASE_DOMAIN', 'bodaenlace.com');
define('CREATOR_HOST', 'bodaenlace.com');
define('CREATOR_BASE', '');
define('CORREO_A_FICHERO', true);
$raiz = dirname(__DIR__);
foreach (['core', 'schema', 'render', 'foto', 'alta', 'mapa', 'cortesia', 'estudio', 'borrador', 'invitados', 'creador', 'landing', 'vista_constructor', 'boda', 'galeria', 'proxy'] as $m) {
    require_once $raiz . '/app/' . $m . '.php';
}
$fallos = 0;
$n = 0;
function ok(bool $c, string $q): void { global $fallos, $n; $n++; if (!$c) { $fallos++; echo "FALLA: $q\n"; } }

// ---------------------------------------------------------------- la boda: lista con grupos, respuestas, mesas, canciones, galería y libro
$slug = 'panel-prueba';
$host = $slug . '.bodaenlace.com';
$c = config_inicial();
$c['pareja']['nombre1'] = 'Lucía'; $c['pareja']['nombre2'] = 'Marcos'; $c['pareja']['email'] = 'pareja@example.com';
$c['fecha'] = date('Y-m-d', strtotime('+120 days'));
$c['ceremonia']['lugar'] = 'Ermita'; $c['ceremonia']['hora'] = '12:00';
$c['convite']['lugar'] = 'Finca El Olivar';
$c['codigo'] = 'olivo27';
foreach ($c['secciones'] as &$s) if (in_array($s['tipo'], ['galeria', 'libro', 'musica'], true)) $s['on'] = true;
unset($s);
$c = normaliza_config($c);
$fotoId = 'aaaaaaaaaaaaaaa1';
foreach ($c['secciones'] as &$s) if ($s['tipo'] === 'galeria') { $s['datos']['fotos'] = [['id' => $fotoId, 'pie' => 'Pedida']]; $s['datos']['consentido'] = true; }
unset($s);
escribe_json(dir_boda($slug) . '/config.json', $c);
asegura_dir(dir_boda($slug) . '/galeria');
file_put_contents(dir_boda($slug) . '/galeria/' . $fotoId . '.webp', 'RIFF0000WEBPVP8 ');   // basta con que exista: aquí no se decodifica
escribe_json(dir_boda($slug) . '/pedido.json', ['session_id' => 'ls_901', 'email' => 'pareja@example.com', 'atelier' => true]);
escribe_json(dir_datos('pedidos', 'ls_901.json'), ['session_id' => 'ls_901', 'pasarela' => 'lemon', 'slug' => $slug, 'estado' => 'creada', 'ls' => ['test' => true, 'recibo' => 'https://app.lemonsqueezy.com/my-orders/r901']]);
escribe_json(panel_fichero($slug), ['hash' => password_hash('clave-de-prueba-1', PASSWORD_DEFAULT), 'gen' => 0]);

// Lista pegada como la guarda el panel (con sus grupos, gid y token), un grupo con un nombre hostil
$lista = inv_parsea("Ana Pérez; Familia Pérez\nCarlos Pérez; Familia Pérez\nMarta Gil\nJuan Ruiz; Tíos <b>Ruiz</b>\nEva Ruiz; Tíos <b>Ruiz</b>\nPedro Sol; Vecinos");
muta_json(inv_fichero($slug), function (array &$d) use ($lista) { $d['lista'] = $lista; inv_sincroniza_grupos($d); });
$inv = inv_lee($slug);
$gid = fn(string $k) => $inv['grupos'][$k]['gid'];
$per = fn(string $nombre, string $menu, string $alergias = '', string $tipo = 'adulto') => ['id' => bin2hex(random_bytes(8)), 'nombre' => $nombre, 'tipo' => $tipo, 'menu' => $menu, 'menu_nombre' => ucfirst($menu), 'alergias' => $alergias];
$resp = fn(string $id, array $ps, bool $viene, string $grupo = '', array $mas = []) => ['id' => $id, 'fecha_envio' => date('c', time() - 7200), 'invitados' => $ps, 'asiste_ceremonia' => $viene,
    'asiste_banquete' => $viene, 'contacto' => '600000000'] + ($grupo !== '' ? ['grupo' => $grupo] : []) + $mas;
$A = [$per('Ana Pérez', 'general', 'frutos secos'), $per('Carlos Pérez', 'general')];
escribe_json(dir_boda($slug) . '/guardado/rsvp.json', [
    $resp('ra', $A, true, $gid('g:familia perez'), ['cancion' => '"><img src=x onerror=alert(1)>']),
    $resp('rb', [$per('Marta Gil', 'general')], true, $gid('p:' . inv_id('Marta Gil', ''))),
    $resp('rc', [$per('Juan Ruiz', 'general'), $per('Eva Ruiz', 'general')], false, $gid('g:' . clave_nombre('Tíos <b>Ruiz</b>'))),
]);
// «Vecinos» abrió su enlace y no ha contestado
muta_json(inv_fichero($slug), function (array &$d) { $d['grupos']['g:vecinos']['abierto'] = date('c', time() - 86400 * 2); });
escribe_json(dir_boda($slug) . '/guardado/canciones.json', [['id' => 'c1', 'fecha' => date('c', time() - 86400 * 3), 'artista' => 'Earth, Wind & Fire', 'cancion' => 'September', 'votos' => 12],
    ['id' => 'c2', 'fecha' => date('c', time() - 86400 * 4), 'artista' => 'ABBA', 'cancion' => 'Dancing <Queen>', 'votos' => 5]]);
asegura_dir(dir_libro($slug));
escribe_json(dir_libro($slug) . '/libro.json', [['id' => 'e1', 'fecha' => date('c'), 'nombre' => 'Carmen', 'mensaje' => 'Que la vida os siga sorprendiendo', 'foto' => null, 'oculto' => false, 'ip' => '127.0.0.1']]);
$ban = mesas_banquete($slug);
muta_json(mesas_fichero($slug), function (array &$d) use ($ban, $A) {
    mesas_aplica($d, 'crear', ['nombre' => 'Presidencia', 'plazas' => 8], $ban);
    mesas_aplica($d, 'sentar', ['mesa' => $d['mesas'][0]['id'], 'personas' => array_column($A, 'id')], $ban);
});

// ---------------------------------------------------------------- servidor
$pApp = 19000 + random_int(0, 499);
$env = array_merge(getenv(), ['BODAS_TEST_DATA' => $tmp]);
$log = [1 => ['file', $tmp . '/srv.log', 'a'], 2 => ['file', $tmp . '/srv.log', 'a']];
$srv = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $pApp, '-t', $web, __DIR__ . '/router_prueba.php'], $log, $pp, null, $env);
for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $pApp); $i++) usleep(100000);

$cookie = '';
/** Petición a la app. Devuelve [status, cuerpo, cabeceras]. La cookie de sesión se lleva a mano (la app la marca Secure). */
function pide(string $metodo, string $ruta, ?array $campos = null): array {
    global $pApp, $cookie, $host;
    $ch = curl_init('http://127.0.0.1:' . $pApp . $ruta);
    $cab = [];
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => array_merge(['Host: ' . $host], $cookie !== '' ? ['Cookie: ' . $cookie] : []),
        CURLOPT_CUSTOMREQUEST => $metodo, CURLOPT_NOBODY => $metodo === 'HEAD',
        CURLOPT_HEADERFUNCTION => function ($ch, $l) use (&$cab) { $cab[] = rtrim($l); return strlen($l); }]);
    if ($campos !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($campos));
    $r = (string) curl_exec($ch);
    $st = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    foreach ($cab as $l) if (preg_match('/^Set-Cookie: (bw_[a-f0-9]+=[^;]+)/i', $l, $m) && strpos($m[1], '=deleted') === false) $cookie = $m[1];
    return [$st, $r, implode("\n", $cab)];
}
function csrf_de(string $html): string { return preg_match('/name="csrf" value="([a-f0-9]{32})"/', $html, $m) ? $m[1] : ''; }
/** Enlaces de fuera permitidos (WhatsApp, recibo, la web): nunca un recurso cargado de fuera. */
function marca_ok_con_enlaces(string $h): bool {
    return strpos($h, '/assets/marca.css') !== false && strpos($h, '/assets/panel.css') !== false && strpos($h, 'assets/boda.css') === false
        && strpos($h, ':root{--primary:') === false && !preg_match('~<(script|link|img)\b[^>]+(src|href)="https?://~i', $h);
}

$RUTAS = ['/panel' => 'inicio', '/panel/invitados' => 'invitados', '/panel/respuestas' => 'respuestas', '/panel/mesas' => 'mesas', '/panel/catering' => 'catering',
    '/panel/musica' => 'musica', '/panel/galeria' => 'galeria', '/panel/descargas' => 'descargas', '/panel/mas' => 'mas'];

// ---------------------------------------------------------------- 1. sin sesión: todo al login; las páginas de acceso, con la marca
foreach (array_keys($RUTAS) + ['x' => '/panel/mesas/imprimir'] as $r) {
    [$st, , $cab] = pide('GET', $r);
    ok($st === 302 && strpos($cab, 'Location: /panel/entrar') !== false, "sin sesión: $r manda al login ($st)");
}
foreach (['/panel/entrar' => 'Entrar al panel', '/panel/recuperar' => 'Recuperar acceso', '/panel/clave?t=x' => 'Elegid vuestra contraseña'] as $r => $tit) {
    [$st, $h, $cab] = pide('GET', $r);
    ok($st === 200 && stripos($cab, 'Cache-Control: private, no-store') !== false && marca_ok_con_enlaces($h) && strpos($h, $tit) !== false && strpos($h, 'class="rail') === false,
        "$r: 200, no-store, la marca, sin tema ni menú");
}
[$st, $h] = pide('POST', '/panel/entrar', ['clave' => 'mala-mala-mala']);
ok($st === 200 && strpos($h, 'class="error" role="alert"') !== false, 'contraseña mala: error visible');
[$st] = pide('POST', '/panel/entrar', ['clave' => 'clave-de-prueba-1']);
ok($st === 302 && $cookie !== '', 'login del panel');

// ---------------------------------------------------------------- 2. con sesión: cada sección
$H = [];
foreach ($RUTAS as $r => $sec) {
    [$st, $h, $cab] = pide('GET', $r);
    $H[$sec] = $h;
    ok($st === 200, "$r: 200 ($st)");
    ok(stripos($cab, 'Cache-Control: private, no-store') !== false, "$r: private, no-store");
    ok(marca_ok_con_enlaces($h), "$r: marca de BodaEnlace, sin boda.css ni el tema de la boda, nada cargado de fuera");
    ok(preg_match('~<nav class="nav"[^>]*>.*?<a href="' . preg_quote($sec === 'inicio' ? '/panel' : '/panel/' . $sec, '~') . '" aria-current="page"~s', $h) === 1 || $sec === 'mas',
        "$r: el menú lateral marca la sección");
    ok(preg_match('~<nav class="tabbar[^"]*"[^>]*>.*aria-current="page"~s', $h) === 1, "$r: la barra del móvil marca dónde estáis");
    ok(strpos($h, 'href="/panel/editar"') !== false && strpos($h, 'href="https://' . $host . '/"') !== false, "$r: «Editar la web» y «Ver mi web»");
}
if (getenv('PANEL_DUMP')) foreach ($H as $k => $v) file_put_contents(getenv('PANEL_DUMP') . '/' . $k . '.html', $v);   // depuración
[$st, , $cab] = pide('HEAD', '/panel/respuestas');
ok($st === 200, 'HEAD /panel/respuestas: 200');

// Inicio
$h = $H['inicio'];
ok(strpos($h, '<h1>Lucía &amp; Marcos</h1>') !== false && strpos($h, '120<small>días</small>') !== false, 'inicio: la pareja y la cuenta atrás real (120 días)');
ok(strpos($h, 'id="qr"') !== false && strpos($h, 'data-url="https://' . $host . '/"') !== false && strpos($h, '/assets/js/vendor/qrcode.js') !== false, 'inicio: el QR (vendor/qrcode.js) con la URL de la web');
ok(strpos($h, 'id="btnWa"') !== false && strpos($h, 'id="msgWa"') !== false && strpos($h, 'olivo27') !== false, 'inicio: WhatsApp con el mensaje (y el código de galería)');
// Lista: 6 personas; contestaron Ana, Carlos, Marta (vienen) y Juan, Eva (no) → 5/6 = 83 %
ok(strpos($h, '<b class="tab">83%</b>') !== false && strpos($h, 'stroke-dashoffset:17') !== false, 'inicio: el anillo = 5 de 6 de la lista (83 %)');
ok(preg_match('~kpi ok"><b>3</b><span>vienen~', $h) && preg_match('~kpi mal"><b>2</b><span>no vienen~', $h) && preg_match('~kpi warn"><b>1</b><span>sin responder~', $h)
    && preg_match('~<b>1</b><span>con alergias~', $h), 'inicio: vienen 3, no vienen 2, sin responder 1, alergias 1');
ok(strpos($h, 'class="hecho"><span class="c" aria-hidden="true">✓</span><span>Pegar la lista') !== false, 'inicio: «Pegar la lista» tachado (hay lista)');
ok(strpos($h, 'Sentar a los invitados en sus mesas (1 sin mesa)') !== false, 'inicio: mesas pendientes calculadas (Marta sin mesa)');
ok(strpos($h, 'Vecinos abrió su enlace') !== false && strpos($h, 'Familia Pérez confirmaron') !== false && strpos($h, 'Carmen dejó un mensaje') !== false
    && strpos($h, 'September') === false, 'inicio: «Lo último» = los 5 hechos más recientes, con su fecha real');
ok(strpos($h, 'Tíos &lt;b&gt;Ruiz&lt;/b&gt; no vienen') !== false && strpos($h, 'Tíos <b>Ruiz') === false, 'inicio: el nombre del grupo, escapado');

// Invitados
$h = $H['invitados'];
ok(substr_count($h, '/i/') >= 4 && strpos($h, 'Confirmado') !== false && strpos($h, 'Sin abrir') !== false && strpos($h, 'Abierto el ') !== false, 'invitados: enlaces por grupo con su estado');
ok(strpos($h, 'Tíos &lt;b&gt;Ruiz&lt;/b&gt;') !== false && strpos($h, 'Tíos <b>Ruiz') === false, 'invitados: nombre de grupo escapado');
ok(strpos($h, 'General ×2') !== false || strpos($h, '×2') !== false, 'invitados: menús del grupo confirmado');
[, $hf] = pide('GET', '/panel/invitados?f=sin');
ok(strpos($hf, 'aria-current="true">Sin abrir') !== false && strpos($hf, '<b>Vecinos</b>') === false && strpos($hf, '<b>Familia Pérez</b>') === false, 'filtro «Sin abrir» por URL: solo los sin abrir');
[, $hq] = pide('GET', '/panel/invitados?q=' . rawurlencode('marta'));
ok(strpos($hq, '<b>Marta Gil</b>') !== false && strpos($hq, '<b>Familia Pérez</b>') === false && strpos($hq, 'value="marta"') !== false, 'búsqueda por URL: «marta»');
[, $hq] = pide('GET', '/panel/invitados?q=' . rawurlencode('"><script>'));
ok(strpos($hq, '"><script>') === false && strpos($hq, '&quot;&gt;&lt;script&gt;') !== false, 'búsqueda: lo escrito vuelve escapado');
ok(strpos($h, 'id="lista"') !== false && strpos($h, 'name="accion" value="lista"') !== false && strpos($h, 'data-copiar-pendientes') !== false, 'invitados: editar la lista y copiar los que faltan');
$csrf = csrf_de($h);
ok($csrf !== '', 'invitados: lleva el CSRF');

// Respuestas
$h = $H['respuestas'];
ok(strpos($h, '/panel/excel') !== false && strpos($h, 'frutos secos') !== false && strpos($h, 'No vienen') !== false, 'respuestas: tabla con alergias, «No vienen» y Excel');
ok(strpos($h, '&quot;&gt;&lt;img src=x') !== false && strpos($h, '"><img src=x') === false, 'respuestas: la canción del invitado, escapada');
[, $hf] = pide('GET', '/panel/respuestas?f=alergias');
ok(strpos($hf, 'Ana Pérez') !== false && strpos($hf, 'Marta Gil') === false && strpos($hf, 'Juan Ruiz') === false, 'filtro «Con alergias»');
[, $hf] = pide('GET', '/panel/respuestas?f=no');
ok(strpos($hf, 'Juan Ruiz') !== false && strpos($hf, 'Ana Pérez') === false, 'filtro «No vienen»');
[, $hf] = pide('GET', '/panel/respuestas?f=<x>');
ok(strpos($hf, 'aria-current="true">Todas') !== false, 'filtro desconocido: «Todas»');

// Mesas, catering, música, galería, descargas, más
$h = $H['mesas'];
ok(strpos($h, 'class="m-circ mesa-destino" data-sentar=') !== false && substr_count($h, 'class="silla') >= 8 && strpos($h, '2 / 8') !== false, 'mesas: la mesa redonda con sus 8 sillas (2 ocupadas)');
ok(strpos($h, 'Incluido en vuestro pack') !== false && strpos($h, 'Crear mesa') !== false && strpos($h, '€') === false, 'mesas: incluido, sin precio');
ok(substr_count($h, 'data-persona=') === 3, 'mesas: 3 personas seleccionables (2 sentadas + Marta sin mesa), las sillas no');
$h = $H['catering'];
ok(strpos($h, 'catering-total"><td>Total</td><td>3<') !== false && strpos($h, 'data-imprimir') !== false && strpos($h, 'data-copiar-texto=') !== false, 'catering: total 3, imprimir y copiar');
ok(preg_match('~Ana Pérez</td><td class="catering-mesa">Presidencia</td>~', $h) === 1, 'catering: la alergia con su mesa');
$h = $H['musica'];
ok(strpos($h, 'September') !== false && strpos($h, 'Dancing &lt;Queen&gt;') !== false && strpos($h, 'Copiar lista para el DJ') !== false && strpos($h, 'width:100%') !== false, 'música: canciones con votos y lista para el DJ');
ok(strpos($h, 'Pedidas al confirmar') !== false, 'música: la canción pedida al confirmar');
$h = $H['galeria'];
ok(strpos($h, 'src="/g/' . $fotoId . '.webp"') !== false && strpos($h, 'olivo27') !== false && preg_match('~<a class="btn b-rosa" href="/panel/editar">Subir fotos</a>~', $h) === 1, 'galería: la foto, el código y «Subir fotos» al editor (que guarda el config entero)');
ok(strpos($h, 'Que la vida os siga sorprendiendo') !== false && strpos($h, 'action="/panel/libro"') !== false && strpos($h, 'value="ocultar"') !== false, 'libro: el mensaje con ocultar/borrar');
$h = $H['descargas'];
ok(strpos($h, 'href="/panel/excel"') !== false && strpos($h, 'href="/panel/zip"') !== false && strpos($h, 'r901') !== false && strpos($h, 'Pack Atelier') !== false, 'descargas: Excel, ZIP, recibo y pack');
ok(strpos($h, 'href="/panel/recuperar"') !== false && strpos($h, 'href="/panel/salir"') !== false && strpos($h, fecha_larga(fecha_borrado($c['fecha']), false)) !== false, 'cuenta: contraseña, salir y fecha de borrado');
$h = $H['mas'];
ok(strpos($h, 'href="/panel/catering"') !== false && strpos($h, 'href="/panel/descargas"') !== false && preg_match('~<nav class="tabbar[^"]*"[^>]*>.*<a href="/panel/mas" aria-current="page"~s', $h) === 1, 'más: enlaces y «Más» marcado en la barra');

// ---------------------------------------------------------------- 3. acciones: sus POST de siempre, con CSRF, y vuelta a su sección
[$st] = pide('POST', '/panel/invitados', ['accion' => 'marcar', 'id' => $lista[0]['id'], 'estado' => 'no']);
ok($st === 403, 'invitados sin CSRF: 403');
[$st, , $cab] = pide('POST', '/panel/invitados', ['csrf' => $csrf, 'accion' => 'marcar', 'id' => inv_id('Pedro Sol', 'Vecinos'), 'estado' => 'viene']);
ok($st === 303 && strpos($cab, 'Location: /panel/invitados#personas') !== false && (inv_lee($slug)['manual'][inv_id('Pedro Sol', 'Vecinos')] ?? '') === 'viene', 'marcar a mano: guarda y vuelve a Invitados');
$tokAntes = inv_lee($slug)['grupos']['g:vecinos']['token'];
[$st, , $cab] = pide('POST', '/panel/invitados', ['csrf' => $csrf, 'accion' => 'rotar', 'gid' => $gid('g:vecinos')]);
ok($st === 303 && strpos($cab, 'Location: /panel/invitados#enlaces') !== false && inv_lee($slug)['grupos']['g:vecinos']['token'] !== $tokAntes, 'cambiar enlace: token nuevo y vuelta a Invitados');
[$st, , $cab] = pide('PUT', '/panel/galeria', ['consentido' => 'si']);
ok($st === 405 && stripos($cab, 'Allow: GET, HEAD, POST') !== false, 'PUT /panel/galeria: 405 con Allow');
[$st, $j] = pide('POST', '/panel/galeria', ['consentido' => 'si']);
ok($st === 403 && strpos($j, '"ok":false') !== false, 'subir foto sin CSRF: 403');
[$st, , $cab] = pide('POST', '/panel/libro', ['csrf' => $csrf, 'id' => 'e1', 'accion' => 'ocultar']);
ok($st === 303 && strpos($cab, 'Location: /panel/galeria#libro') !== false && !empty((lee_json(dir_libro($slug) . '/libro.json') ?? [])[0]['oculto']), 'ocultar un mensaje: vuelve a Galería y libro');
[$st] = pide('POST', '/panel/libro', ['id' => 'e1', 'accion' => 'borrar']);
ok($st === 403 && count(lee_json(dir_libro($slug) . '/libro.json') ?? []) === 1, 'libro sin CSRF: 403, no borra');
[$st] = pide('GET', '/panel/libro');
ok($st === 404, 'GET /panel/libro: 404');
foreach (['/panel', '/panel/respuestas', '/panel/musica', '/panel/descargas', '/panel/mas', '/panel/catering'] as $r) {
    [$st, , $cab] = pide('POST', $r, ['csrf' => $csrf]);
    ok($st === 405 && stripos($cab, 'Allow: GET, HEAD') !== false, "POST $r: 405 con Allow ($st)");
}
[$st, $h, $cab] = pide('GET', '/panel/mesas/imprimir');
ok($st === 200 && stripos($cab, 'private, no-store') !== false && marca_ok_con_enlaces($h) && strpos($h, 'class="rail') === false && strpos($h, 'data-imprimir') !== false, 'hoja para el restaurante: la marca, sin menú');

ok((strpos($H['inicio'], 'en autobús') !== false) === pregunta_bus($c) && (strpos($H['respuestas'], '<th>Bus</th>') !== false) === pregunta_bus($c),
    'autobús: Inicio y Respuestas lo enseñan o no a la vez, según se pregunte (' . (pregunta_bus($c) ? 'sí' : 'no') . ')');
// Web archivada: la galería lo dice y no ofrece subir ni carga imágenes que ya no se sirven
muta_json(dir_boda($slug) . '/config.json', function (array &$d) { $d['_estado'] = 'archivada'; });
[$st, $h] = pide('GET', '/panel/galeria');
ok($st === 200 && strpos($h, 'está archivada') !== false && strpos($h, 'Subir fotos') === false && strpos($h, 'src="/g/') === false, 'archivada: la galería sin subir ni fotos rotas');
muta_json(dir_boda($slug) . '/config.json', function (array &$d) { $d['_estado'] = 'activa'; });

// ---------------------------------------------------------------- 3c. reenvío del grupo y «es la misma respuesta» (BOD-24, BOD-22)
$rsvpF = dir_boda($slug) . '/guardado/rsvp.json';
$tokPerez = inv_lee($slug)['grupos']['g:familia perez']['token'];
$idMarta = personas(array_values(array_filter(lee_json($rsvpF), fn($r) => ($r['id'] ?? '') === 'rb'))[0])[0]['id'];
[$st, $h] = pide('POST', '/api/rsvp', ['i' => $tokPerez, 'contacto' => '600000000', 'asiste_banquete' => 'si', 'asiste_ceremonia' => 'si', 'consent_alergias' => 'si', 'consent_acompanantes' => 'si',
    'invitados' => [['nombre' => 'Ana Pérez', 'id' => $idMarta, 'alergias' => 'frutos secos'], ['nombre' => 'Carlos Pérez', 'id' => $idMarta]]]);
$nueva = array_values(array_filter(lee_json($rsvpF), fn($r) => ($r['grupo'] ?? '') === $gid('g:familia perez') && empty($r['sustituido'])))[0] ?? [];
ok($st === 200 && array_column($nueva['invitados'] ?? [], 'id') === array_column($A, 'id'), '(d) reenvío por el enlace: hereda los ids de la familia y descarta el id que llega en el formulario');
[, $h] = pide('GET', '/panel/mesas');
ok(strpos($h, 'Ha vuelto a responder') === false && strpos($h, 'Ya no viene') === false, 'reenvío del grupo: siguen sentados en Presidencia, sin avisos');
// Carlos también respondió por la confirmación general, con una alergia que la del enlace no trae
muta_json($rsvpF, function (array &$d) use ($per) { $d[] = ['id' => 'rgen', 'fecha_envio' => date('c'), 'invitados' => [$per('Carlos Pérez', 'general', 'marisco')], 'asiste_ceremonia' => true, 'asiste_banquete' => true, 'contacto' => '611111111']; });
[, $h] = pide('GET', '/panel/respuestas');
ok(strpos($h, 'misma=rgen&amp;con=' . $nueva['id']) !== false && strpos($h, '¿Es la misma que la de «Familia Pérez»?') !== false, 'respuestas: la general repetida ofrece juntarla con la del grupo');
[, $h] = pide('GET', '/panel/respuestas?misma=rgen&con=' . $nueva['id']);
$rsvpAntes = lee_json($rsvpF);
muta_json($rsvpF, function (array &$d) use ($nueva) { foreach ($d as $k => $r) if (($r['id'] ?? '') === $nueva['id']) $d[$k]['invitados'][1]['alergias'] = 'gluten'; });
[, $h2] = pide('GET', '/panel/respuestas?misma=rgen&con=' . $nueva['id']);
muta_json($rsvpF, function (array &$d) use ($nueva) { foreach ($d as $k => $r) if (($r['id'] ?? '') === $nueva['id']) $d[$k]['invitados'][1]['alergias'] = 'Marisco y gluten'; });
[, $h3] = pide('GET', '/panel/respuestas?misma=rgen&con=' . $nueva['id']);
escribe_json($rsvpF, $rsvpAntes);
ok(strpos($h2, 'Carlos Pérez: marisco') !== false && strpos($h3, 'Ojo: la respuesta general') === false, 'aviso: alergias distintas en el grupo («gluten») también avisan; si la del grupo ya la trae, no');
ok(strpos($h, 'id="misma"') !== false && strpos($h, 'Carlos Pérez: marisco') !== false && strpos($h, 'action="/panel/respuestas/misma"') !== false, 'confirmación: las dos respuestas y el aviso de la alergia que se perdería');
[, $h] = pide('GET', '/panel/respuestas?misma=' . $nueva['id'] . '&con=rgen');
ok(strpos($h, 'id="misma"') === false, 'confirmación: al revés (grupo como general) no se ofrece');
$antes = lee_json($rsvpF);
[$st] = pide('POST', '/panel/respuestas/misma', ['general' => 'rgen', 'grupo' => $nueva['id']]);
ok($st === 403 && lee_json($rsvpF) === $antes, '(g) sin CSRF: 403 y nada cambia');
[$st] = pide('GET', '/panel/respuestas/misma?general=rgen&grupo=' . $nueva['id']);
ok($st === 405 && lee_json($rsvpF) === $antes, 'por GET: 405 y nada cambia');
// 'ra' es la respuesta del grupo que el reenvío acaba de sustituir
foreach ([['rgen', 'ra', 'la del grupo ya sustituida'], ['ra', $nueva['id'], 'una «general» que tiene grupo'], ['rgen', 'noexiste', 'un id que no está'], ['rgen', '../x', 'un id con forma rara']] as [$g1, $g2, $q]) {
    [$st] = pide('POST', '/panel/respuestas/misma', ['csrf' => $csrf, 'general' => $g1, 'grupo' => $g2]);
    ok($st === 409 && lee_json($rsvpF) === $antes, "(f) $q: 409 y nada cambia");
}
[$st, , $cab] = pide('POST', '/panel/respuestas/misma', ['csrf' => $csrf, 'general' => 'rgen', 'grupo' => $nueva['id']]);
$rg = array_values(array_filter(lee_json($rsvpF), fn($r) => ($r['id'] ?? '') === 'rgen'))[0] ?? [];
ok($st === 303 && strpos($cab, 'Location: /panel/respuestas') !== false && ($rg['sustituido'] ?? '') === $nueva['id'] && ($rg['sustituido_por'] ?? '') === 'pareja'
    && ($rg['invitados'][0]['alergias'] ?? 'x') === '', 'misma: la general deja de contar, anotada por la pareja y sin alergias');
[, $h] = pide('GET', '/panel/respuestas');
ok(strpos($h, 'misma=rgen') === false && strpos($h, '611111111') === false, 'respuestas: la general juntada ya no sale');

// ---------------------------------------------------------------- 4. otra boda: su cookie no abre este panel
$otra = 'otra-prueba';
escribe_json(dir_boda($otra) . '/config.json', normaliza_config(config_inicial()));
$hostBak = $host; $host = $otra . '.bodaenlace.com';
[$st, , $cab] = pide('GET', '/panel/invitados');
ok($st === 302 && strpos($cab, 'Location: /panel/entrar') !== false, 'la sesión de una boda no abre el panel de otra');
$host = $hostBak;

// ---------------------------------------------------------------- 5. salir
[$st, , $cab] = pide('GET', '/panel/salir');
[$st] = pide('GET', '/panel/respuestas');
ok($st === 302, 'tras salir: al login');

proc_terminate($srv); proc_close($srv);
foreach ([$tmp, $web] as $d) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $x) {
        $x->isDir() ? @rmdir($x->getPathname()) : @unlink($x->getPathname());
    }
    @rmdir($d);
}
echo ($fallos ? 'ROJO' : 'VERDE') . ': ' . ($n - $fallos) . "/$n\n";
exit($fallos ? 1 : 0);
