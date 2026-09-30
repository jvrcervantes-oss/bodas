<?php
// Paquete de mejoras (30-sep-2026): programa del día, «Nuestra historia», foto Instantánea, hashtag y vestimenta.
// Sin red. Uso: php tests/paquete_test.php  (sale con 1 si algo falla). Datos inventados.
// Los snapshots de tests/fixtures/snap_*.html se generaron con el código ANTERIOR al paquete: una web ya publicada no cambia.

declare(strict_types=1);

// La etiqueta de script se arma por partes: un literal completo en este fichero lo lee el detector de JS embebido como una pagina.
const SCR_A = '<scr' . 'ipt>';
const SCR_C = '</scr' . 'ipt>';

$tmp = sys_get_temp_dir() . '/bodas_paquete_test_' . bin2hex(random_bytes(4));
mkdir($tmp, 0700, true);
define('SIN_CONFIG_LOCAL', true);
define('DATA_DIR', $tmp);
define('BASE_DOMAIN', 'bodaenlace.com');
define('CREATOR_HOST', 'bodaenlace.com');
define('CREATOR_BASE', '');
file_put_contents($tmp . '/secrets.php', '<?php return ' . var_export(['pasarela' => 'lemon', 'empresa' => ['titular' => 'Titular De Prueba', 'nif' => '00000000T', 'domicilio' => 'Calle Prueba 1']], true) . ';');
$raiz = dirname(__DIR__);
foreach (['core', 'schema', 'render', 'foto', 'alta', 'mapa', 'cortesia', 'estudio', 'borrador', 'invitados', 'creador', 'landing', 'vista_constructor', 'boda', 'galeria', 'proxy'] as $m) {
    require_once $raiz . '/app/' . $m . '.php';
}
require_once __DIR__ . '/fixtures/snap_lib.php';

$fallos = 0;
$n = 0;
function ok(bool $c, string $que): void { global $fallos, $n; $n++; if (!$c) { $fallos++; echo "FALLA: $que\n"; } }
function dia(int $d): string { return date('Y-m-d', strtotime(($d >= 0 ? '+' : '') . $d . ' days')); }

/** Config completa (sin faltas) como la manda el navegador: el array CRUDO. */
function crudo(array $cambia = []): array {
    $c = config_inicial();
    $c['pareja'] = ['nombre1' => 'Ana', 'nombre2' => 'Luis', 'union' => '&', 'email' => 'pareja@example.com'];
    $c['fecha'] = dia(100);
    $c['ceremonia']['lugar'] = 'Ermita';
    $c['ceremonia']['hora'] = '12:00';
    return array_replace_recursive($c, $cambia);
}
function conSeccion(array $c, string $tipo, array $datos, bool $on = true): array {
    foreach ($c['secciones'] as &$s) if ($s['tipo'] === $tipo) { $s['on'] = $on; $s['datos'] = array_replace($s['datos'], $datos); }
    unset($s);
    return $c;
}
function f(array $crudo): array { return faltan(normaliza_config($crudo), $crudo); }
function ctxL(): array { return ['modo' => 'live', 'assets' => '/assets/', 'slug' => 'prueba', 'foto' => '', 'mapa' => null]; }
function ctxZ(): array { return ['modo' => 'zip', 'assets' => 'assets/', 'slug' => 'prueba', 'foto' => '', 'mapa' => null]; }
function pag(array $crudo, string $ruta = '', ?array $ctx = null): ?string { return render_pagina(normaliza_config($crudo), $ruta, $ctx ?? ctxL()); }
function sec(array $c, string $tipo): array { foreach ($c['secciones'] as $s) if ($s['tipo'] === $tipo) return $s; return []; }
function hay(?string $h, string $t): bool { return $h !== null && strpos($h, $t) !== false; }

// ============================================================ 0. Webs antiguas: HTML idéntico, byte a byte
foreach (SNAP_CONFIGS as $nom) {
    $c = normaliza_config(json_decode((string) file_get_contents(__DIR__ . "/fixtures/config_$nom.json"), true));
    $esperado = (string) file_get_contents(__DIR__ . "/fixtures/snap_$nom.html");
    $actual = snap_render($c);
    $dif = $actual === $esperado ? -1 : strspn($actual ^ $esperado, "\0");
    ok($actual === $esperado, "web antigua «{$nom}»: todas las páginas (live y zip), .ics y barra inferior idénticas byte a byte" . ($dif >= 0 ? " (difieren en el byte $dif: …" . substr($actual, max(0, $dif - 60), 140) . '…)' : ''));
}
$antigua = normaliza_config(json_decode((string) file_get_contents(__DIR__ . '/fixtures/config_esencial.json'), true));
$h = sec($antigua, 'historia');
ok($h && $h['on'] === false && $h['datos']['texto'] === '' && $h['ruta'] === 'nuestra-historia', 'config antiguo sin «historia»: se normaliza con la sección apagada y vacía');
ok($antigua['programa'] === [] && $antigua['portada']['hashtag'] === '' && $antigua['portada']['vestimenta'] === '', 'config antiguo: programa, hashtag y vestimenta vacíos');
$rutasLibres = array_column(array_filter($antigua['secciones'], fn($s) => $s['tipo'] === 'libre'), 'ruta');
ok($rutasLibres === ['historia', 'nuestra-historia'], 'web publicada con libres «Historia» y «Nuestra historia»: sus direcciones NO cambian (' . implode(',', $rutasLibres) . ')');

// ============================================================ 1. Programa del día
$p = normaliza_config(crudo(['programa' => [
    ['hora' => '18:00', 'titulo' => 'Baile', 'lugar' => 'Carpa', 'nota' => 'Hasta el amanecer'],
    ['hora' => '13:30', 'titulo' => 'Aperitivo'],
    ['hora' => '13:30', 'titulo' => 'Cóctel'],
    ['hora' => '09:05', 'titulo' => 'Desayuno'],
]]))['programa'];
ok(array_column($p, 'titulo') === ['Desayuno', 'Aperitivo', 'Cóctel', 'Baile'], 'programa: ordenado por hora en el servidor, y a igual hora manda el orden escrito');
ok($p[3]['lugar'] === 'Carpa' && $p[3]['nota'] === 'Hasta el amanecer' && $p[0]['lugar'] === '' && array_keys($p[0]) === ['hora', 'titulo', 'lugar', 'nota'], 'programa: lugar y nota opcionales; solo esas cuatro claves');
$gran = array_fill(0, 10000, ['hora' => '10:00', 'titulo' => 'x']);
ok(count(normaliza_config(crudo(['programa' => $gran]))['programa']) === MAX_PROGRAMA, 'programa: 10.000 elementos → tope de ' . MAX_PROGRAMA);
ok(count(normaliza_config(crudo(['programa' => ['a' => ['hora' => '10:00', 'titulo' => 'Uno'], 'b' => ['hora' => '11:00', 'titulo' => 'Dos']]]))['programa']) === 2, 'programa: un objeto en vez de lista se lee como lista');
foreach ([null, 'texto', 5, true, [1, 2, 'x'], [['hora' => ['x'], 'titulo' => ['y']]], [[['a']]]] as $i => $raro) {
    $in = crudo(); $in['programa'] = $raro;
    ok(normaliza_config($in)['programa'] === [], "programa: valor raro #$i no rompe y queda vacío");
}
$in = crudo(); $in['programa'] = [5, null, 'x', ['hora' => 10, 'titulo' => 9], ['hora' => '10:00', 'titulo' => ['a']], ['hora' => '10:00', 'titulo' => 'Bien']];
ok(array_column(normaliza_config($in)['programa'], 'titulo') === ['Bien'], 'programa: filas de tipos raros se descartan sin error');
$r = normaliza_config(crudo(['programa' => [
    ['hora' => '25:99', 'titulo' => 'Mala'], ['hora' => '', 'titulo' => 'Sin hora'], ['hora' => '10:00', 'titulo' => ''], ['hora' => '24:00', 'titulo' => 'Medianoche'],
    ['hora' => '23:59', 'titulo' => 'Ultima'], ['hora' => '00:00', 'titulo' => 'Primera'],
]]))['programa'];
ok(array_column($r, 'titulo') === ['Primera', 'Ultima'], 'programa: hora 25:99, 24:00, vacía o sin título se descartan; 00:00 y 23:59 valen');
$r = normaliza_config(crudo(['programa' => [['hora' => '10:00', 'titulo' => str_repeat('T', 90), 'lugar' => str_repeat('L', 120), 'nota' => "línea1\n\n  línea2\r\n" . str_repeat('N', 300)]]]))['programa'][0];
ok(mb_strlen($r['titulo']) === 60 && mb_strlen($r['lugar']) === 80 && mb_strlen($r['nota']) === 160 && strpos($r['nota'], "\n") === false && strpos($r['nota'], 'línea1 línea2 ') === 0, 'programa: topes 60/80/160 y una sola línea');
// Vacío = la portada de hoy (sin lista); con extras = lista nueva con clases .prog-
$home0 = (string) pag(crudo());
ok(!hay($home0, 'prog-lista') && !hay($home0, 'El programa del día') && !hay($home0, 'class="prog'), 'programa vacío: sin lista ni cabecera');
$c1 = crudo(['convite' => ['lugar' => 'Finca', 'hora' => '14:30'], 'programa' => [['hora' => '20:00', 'titulo' => 'Cena <b>&"\'', 'lugar' => 'Jardín', 'nota' => 'Traed ' . SCR_A . 'alert(1)' . SCR_C], ['hora' => '17:00', 'titulo' => 'Fotos']]]);
$home1 = (string) pag($c1);
ok(hay($home1, 'class="prog rv"') && hay($home1, 'prog-lista') && substr_count($home1, 'class="event-card rv"') === 2, 'programa con extras: lista nueva y las dos tarjetas de siempre siguen');
preg_match_all('/<span class="prog-hora">([^<]+)</', $home1, $m);
ok($m[1] === ['12:00', '14:30', '17:00', '20:00'], 'programa: la línea de tiempo junta ceremonia, convite y extras por hora (' . implode(',', $m[1]) . ')');
ok(substr_count($home1, 'prog-ceremonia') === 1, 'programa: solo la ceremonia lleva la clase de acento');
ok(hay($home1, 'Cena &lt;b&gt;&amp;&quot;&#039;') && !hay($home1, SCR_A . 'alert(1)') && hay($home1, 'Traed &lt;script&gt;'), 'programa: título, lugar y nota escapados');
ok(strpos(ics(normaliza_config($c1)), 'Cena') === false && strpos(url_google_calendar(normaliza_config($c1)), 'Fotos') === false, 'programa: no entra en el .ics ni en el enlace de Google Calendar');
ok(f($c1) === [], 'programa válido: no bloquea nada');

// ============================================================ 2. Nuestra historia
$sinTexto = conSeccion(crudo(), 'historia', ['texto' => ''], true);
ok(sec(normaliza_config($sinTexto), 'historia')['on'] === false, 'historia activa pero sin texto: se apaga al normalizar');
ok(pag($sinTexto, 'nuestra-historia') === null, 'historia sin texto: la página no existe (404)');
ok(pag(crudo(), 'nuestra-historia') === null, 'historia apagada por defecto: 404');
$hi = conSeccion(crudo(), 'historia', ['texto' => "Nos conocimos en 2019.\n\nY <b>seguimos</b> \"juntos\" & felices."], true);
$hn = normaliza_config($hi);
ok(sec($hn, 'historia')['on'] === true, 'historia con texto y activa: encendida');
$pg = (string) render_pagina($hn, 'nuestra-historia', ctxL());
ok(hay($pg, '<h1>Nuestra historia</h1>') && hay($pg, 'historia-kicker') && hay($pg, '<p class="lede">Nos conocimos en 2019.</p>') && hay($pg, '<p class="lede">Y &lt;b&gt;seguimos&lt;/b&gt; &quot;juntos&quot; &amp; felices.</p>'), 'historia: kicker, título y un párrafo por bloque, cada uno escapado');
$homeH = (string) render_pagina($hn, '', ctxL());
ok(hay($homeH, 'href="/nuestra-historia"'), 'historia activa: entra en el menú y en la portada');
ok(hay(tab_bar($hn, '', ctxL()), 'nuestra-historia'), 'historia activa: entra en la barra inferior');
ok(!hay((string) pag(crudo()), 'nuestra-historia'), 'historia apagada: ausente de menú y portada');
ok(!hay(tab_bar(normaliza_config(crudo()), '', ctxL()), 'nuestra-historia'), 'historia apagada: ausente de la barra inferior');
$zh = (string) render_pagina($hn, '', ctxZ());
ok(hay($zh, 'href="nuestra-historia.html"') && !hay($zh, 'href="/nuestra-historia"'), 'ZIP: enlaces relativos a nuestra-historia.html');
$zp = (string) render_pagina($hn, 'nuestra-historia', ctxZ());
ok(hay($zp, '<h1>Nuestra historia</h1>') && hay($zp, 'href="index.html"'), 'ZIP: la página de la historia se genera con su nav relativo');
$rutasZip = array_map(fn($s) => $s['ruta'] . '.html', array_filter($hn['secciones'], fn($s) => $s['on']));
ok(in_array('nuestra-historia.html', $rutasZip, true), 'ZIP: panel_zip escribe una página por sección activa, historia incluida');
$dup = crudo(); $dup['secciones'][] = ['id' => 'h2', 'tipo' => 'historia', 'on' => true, 'titulo' => 'Otra', 'datos' => ['texto' => 'Segunda']];
$dup = conSeccion($dup, 'historia', ['texto' => 'Primera'], true);
$nd = normaliza_config($dup);
ok(count(array_filter($nd['secciones'], fn($s) => $s['tipo'] === 'historia')) === 1 && sec($nd, 'historia')['datos']['texto'] === 'Primera', 'historia duplicada: solo queda una (la primera)');
ok(mb_strlen(sec(normaliza_config(conSeccion(crudo(), 'historia', ['texto' => str_repeat('ñ', 2500)])), 'historia')['datos']['texto']) === MAX_HISTORIA, 'historia de 2.500 caracteres → tope de 2.000');
// Ruta reservada frente a una sección libre con el mismo título
$col = conSeccion(crudo(), 'historia', ['texto' => 'Hola'], true);
$col['secciones'][] = ['id' => 'lb', 'tipo' => 'libre', 'on' => true, 'titulo' => 'Nuestra historia', 'datos' => ['texto' => 'Libre']];
$nc = normaliza_config($col);
$rutaLibre = array_values(array_filter($nc['secciones'], fn($s) => $s['tipo'] === 'libre'))[0]['ruta'];
ok($rutaLibre === 'nuestra-historia-2' && sec($nc, 'historia')['ruta'] === 'nuestra-historia', 'historia activa + libre «Nuestra historia»: la libre se desplaza y no hay dos páginas en la misma dirección');
ok(seccion_por_ruta($nc, 'nuestra-historia')['tipo'] === 'historia', 'la ruta fija es de la historia');
$col2 = crudo(); $col2['secciones'][] = ['id' => 'lb', 'tipo' => 'libre', 'on' => true, 'titulo' => 'Nuestra historia', 'datos' => ['texto' => 'Libre']];
ok(array_values(array_filter(normaliza_config($col2)['secciones'], fn($s) => $s['tipo'] === 'libre'))[0]['ruta'] === 'nuestra-historia', 'historia apagada + libre «Nuestra historia» ya publicada: la libre conserva su dirección');
ok(sec(config_inicial(), 'historia')['on'] === false, 'config nuevo del creador: historia apagada');
ok(!hay(ics($hn), 'conocimos'), 'historia: fuera del .ics');
preg_match('/<title>(.*?)<\/title>/s', $pg, $titulo);
ok(($titulo[1] ?? '') === 'Nuestra historia — Ana &amp; Luis', 'historia: <title> con el título de la sección y los nombres, sin el texto');

// ============================================================ 3. Foto «Instantánea»
ok(isset(FOTO_ESTILOS['instantanea']) && FOTO_ESTILOS['instantanea'][0] === 'Instantánea', 'FOTO_ESTILOS: «Instantánea»');
ok(normaliza_config(crudo(['foto_estilo' => 'instantanea']))['foto_estilo'] === 'instantanea', 'foto_estilo instantanea se acepta');
$ci = crudo(['foto' => true, 'foto_estilo' => 'instantanea', 'decoracion' => 'flores']);
$hi2 = (string) render_pagina(normaliza_config($ci), '', ['foto' => '/foto?v=1'] + ctxL());
ok(hay($hi2, 'mf mf-instantanea') && hay($hi2, 'class="inst-cinta"') && hay($hi2, 'deco-guirnalda') && !hay($hi2, 'mf-g-top'), 'Instantánea con flores: cinta propia y la guirnalda sigue sobre los nombres (no se pega a la foto)');
$at = normaliza_config(crudo(['foto' => true, 'foto_estilo' => 'instantanea', 'atelier' => 'lacre']));
ok(!hay((string) render_pagina($at, '', ['foto' => '/foto?v=1'] + ctxL()), 'inst-cinta'), 'Atelier: no aplica la Instantánea');
$css = (string) file_get_contents($raiz . '/assets/boda.css');
$ini = strpos($css, 'INSTANTANEA-INICIO'); $fin = strpos($css, 'INSTANTANEA-FIN');
$bloque = ($ini !== false && $fin > $ini) ? substr($css, $ini, $fin - $ini) : '';
$sinComentarios = (string) preg_replace('~/\*.*?\*/~s', '', $bloque);
ok($bloque !== '' && stripos($sinComentarios, 'gradient') === false, 'CSS de la Instantánea: sin ningún gradient');
preg_match_all('/box-shadow\s*:\s*([^;}]*)/i', $sinComentarios, $bs);
ok($bloque !== '' && array_unique(array_map('trim', $bs[1])) === ['none'], 'CSS de la Instantánea: el único box-shadow es «none»');
ok($bloque !== '' && stripos($sinComentarios, 'drop-shadow') === false && stripos($sinComentarios, 'text-shadow') === false, 'CSS de la Instantánea: sin drop-shadow ni text-shadow');
// La palabra que no puede aparecer en ningún sitio (marca registrada)
$hallado = [];
foreach (['app', 'assets', 'tests', 'cron', 'worker', 'index.php'] as $ruta) {
    $it = is_dir("$raiz/$ruta") ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$raiz/$ruta", FilesystemIterator::SKIP_DOTS)) : [new SplFileInfo("$raiz/$ruta")];
    foreach ($it as $fi) {
        if (!$fi->isFile() || preg_match('/\.(woff2?|webp|png|jpg|ico|mp4|zip)$/i', $fi->getFilename()) || $fi->getFilename() === 'paquete_test.php') continue;
        if (stripos((string) file_get_contents($fi->getPathname()), 'polaroid') !== false) $hallado[] = $fi->getFilename();
    }
}
ok($hallado === [], 'la marca registrada no aparece en ningún fichero (' . implode(',', $hallado) . ')');

// ============================================================ 4. Hashtag
$hs = fn($v) => normaliza_config(crudo(['portada' => ['hashtag' => $v]]))['portada']['hashtag'];
ok($hs('#AnaYLuis2031') === 'AnaYLuis2031', 'hashtag: se guarda sin «#»');
ok($hs('Ana y Luis') === 'AnayLuis', 'hashtag: sin espacios');
ok($hs('Ñandú_Ángel-2031!') === 'Ñandú_Ángel2031', 'hashtag: tildes y ñ valen; guion normal y signos fuera; guion bajo vale');
ok($hs('Boda 💍 Ana') === 'BodaAna', 'hashtag: sin emojis');
ok(mb_strlen($hs(str_repeat('a', 500))) === MAX_HASHTAG, 'hashtag: tope de 30');
ok(mb_strlen($hs(str_repeat('🙂', 40) . str_repeat('b', 40))) === MAX_HASHTAG, 'hashtag: el tope cuenta sobre el valor ya limpio');
$in = crudo(); $in['portada']['hashtag'] = ['a'];
ok(normaliza_config($in)['portada']['hashtag'] === '' && $hs(12) === '12', 'hashtag: tipos raros');
$hh = crudo(['portada' => ['hashtag' => '#Ana<Luis>"\'']]);
$homeT = (string) pag($hh);
ok(hay($homeT, '<p class="hero-hashtag">#AnaLuis</p>') && hay($homeT, '<p class="footer-hashtag">#AnaLuis</p>'), 'hashtag: «#» + valor bajo la fecha y en el pie, solo texto');
ok(!hay($homeT, 'href="#AnaLuis') && !hay(ics(normaliza_config($hh)), 'AnaLuis'), 'hashtag: sin enlace y fuera del .ics');
preg_match('/<title>(.*?)<\/title>/s', $homeT, $tt);
ok(!hay($tt[1] ?? '', 'AnaLuis') && !hay($homeT, 'og:description') && !hay($homeT, 'application/ld+json'), 'hashtag: fuera del <title>; la web no lleva og:description ni JSON-LD');
ok(!hay(url_google_calendar(normaliza_config($hh)), 'AnaLuis'), 'hashtag: fuera del enlace de Google Calendar');
ok(!hay($home0, 'hero-hashtag') && !hay($home0, 'footer-hashtag') && !hay($home0, '>#<'), 'hashtag vacío: no pinta nada (ni «#» suelto)');

// ============================================================ 5. Vestimenta
$vs = fn($v) => normaliza_config(crudo(['portada' => ['vestimenta' => $v]]))['portada']['vestimenta'];
ok($vs("Traje\noscuro \n y  corbata") === 'Traje oscuro y corbata', 'vestimenta: una línea (sin \n)');
ok(mb_strlen($vs(str_repeat('v', 200))) === MAX_VESTIMENTA, 'vestimenta: tope de 60');
$in = crudo(); $in['portada']['vestimenta'] = ['x'];
ok(normaliza_config($in)['portada']['vestimenta'] === '', 'vestimenta: tipos raros');
$dress = conSeccion(crudo(['portada' => ['vestimenta' => 'Etiqueta <i>"rigurosa"</i>']]), 'dresscode', ['texto' => 'Detalles del dress code'], true);
$hv = (string) pag($dress);
ok(hay($hv, '<p class="pildora-vest"><span class="pildora-vest-rot">Vestimenta</span><span class="pildora-vest-txt">Etiqueta &lt;i&gt;&quot;rigurosa&quot;&lt;/i&gt;</span></p>'), 'vestimenta: píldora escapada en la portada');
ok(!hay($hv, 'Código de vestimenta'), 'vestimenta rellena: sustituye a la tarjeta rápida de dress code de la portada');
ok(hay((string) pag($dress, 'dress-code'), 'Detalles del dress code'), 'vestimenta rellena: la página interior de dress code sigue');
$dress0 = conSeccion(crudo(), 'dresscode', ['texto' => 'Detalles'], true);
ok(hay((string) pag($dress0), 'Código de vestimenta') && !hay((string) pag($dress0), 'pildora-vest'), 'vestimenta vacía: la tarjeta de dress code sigue y no hay píldora');
$soloV = crudo(['portada' => ['titulo' => '', 'frase' => '', 'texto' => '', 'vestimenta' => 'Sport']]);
ok(hay((string) pag($soloV), 'pildora-vest'), 'vestimenta con el resto de la bienvenida vacío: se pinta igualmente');
ok(!hay(ics(normaliza_config($dress)), 'rigurosa') && !hay(url_google_calendar(normaliza_config($dress)), 'rigurosa'), 'vestimenta: fuera del .ics');

// ============================================================ 6. Escape en cada campo nuevo (comillas y HTML)
$xss = crudo(['portada' => ['vestimenta' => '"><img src=x onerror=alert(1)>', 'hashtag' => 'a"onmouseover="x'],
    'programa' => [['hora' => '10:00', 'titulo' => '">' . SCR_A . '1' . SCR_C, 'lugar' => "' onload='x", 'nota' => '<img src=x>']]]);
$xss = conSeccion($xss, 'historia', ['texto' => '">' . SCR_A . 'alert(2)' . SCR_C], true);
foreach (['', 'nuestra-historia'] as $rt) {
    $hx = (string) pag($xss, $rt);
    ok(!hay($hx, SCR_A . 'alert') && !hay($hx, '<img src=x') && !hay($hx, '">' . SCR_A . '1') && !hay($hx, "' onload='x"), "escape de HTML y comillas en la página «/{$rt}»");
}

// ============================================================ 7. Aviso de descartes: un test por campo
$av = fn(array $extra) => f(array_replace_recursive(crudo(), $extra));
$k = fn(array $r, string $frag) => array_values(array_filter(array_keys($r), fn($x) => strpos($x, $frag) !== false));
ok($k($av(['portada' => ['hashtag' => 'Ana y Luis']]), 'portada.hashtag') !== [], 'aviso: hashtag con espacios');
ok($k($av(['portada' => ['hashtag' => 'Boda💍']]), 'portada.hashtag') !== [], 'aviso: hashtag con emoji');
ok($k($av(['portada' => ['hashtag' => 'Bodá-2031']]), 'portada.hashtag') !== [], 'aviso: hashtag con guion');
ok($k($av(['portada' => ['hashtag' => str_repeat('a', 31)]]), 'portada.hashtag') !== [], 'aviso: hashtag de 31 (truncado)');
ok($av(['portada' => ['hashtag' => '#AnaYLuis']]) === [] && $av(['portada' => ['hashtag' => '  #Ana_Luis_2031 ']]) === [] && $av(['portada' => ['hashtag' => str_repeat('a', 30)]]) === [], 'sin aviso: «#» delante, espacios en los extremos, 30 justos');
ok($k($av(['portada' => ['vestimenta' => str_repeat('v', 61)]]), 'portada.vestimenta') !== [], 'aviso: vestimenta de 61 (truncada)');
ok($av(['portada' => ['vestimenta' => "Traje\ny corbata"]]) === [] && $av(['portada' => ['vestimenta' => str_repeat('v', 60)]]) === [], 'sin aviso: vestimenta con \n que cabe, o de 60 justos');
$pr = fn(array $fila) => $av(['programa' => [$fila]]);
$bien = ['hora' => '10:00', 'titulo' => 'Algo'];
ok($k($pr(['hora' => '25:99', 'titulo' => 'Algo']), 'programa0.hora') !== [], 'aviso: hora 25:99');
ok($k($pr(['hora' => '', 'titulo' => 'Algo']), 'programa0.hora') !== [], 'aviso: falta la hora');
ok($k($pr(['hora' => '10:00', 'titulo' => '']), 'programa0.titulo') !== [], 'aviso: falta el título');
ok($k($pr($bien + ['lugar' => str_repeat('l', 81)]), 'programa0.lugar') !== [], 'aviso: lugar de 81');
ok($k($pr($bien + ['nota' => str_repeat('n', 161)]), 'programa0.nota') !== [], 'aviso: nota de 161');
ok($k($pr(['hora' => '10:00', 'titulo' => str_repeat('t', 61)]), 'programa0.titulo') !== [], 'aviso: título de 61');
ok($pr($bien + ['lugar' => str_repeat('l', 80), 'nota' => str_repeat('n', 160)]) === [], 'sin aviso: lugar y nota en su tope exacto');
ok($k($av(['programa' => array_fill(0, 7, $bien)]), 'programa.max') !== [], 'aviso: 7 momentos (solo caben 6)');
ok($av(['programa' => array_fill(0, 6, $bien)]) === [], 'sin aviso: 6 momentos');
$orden = $av(['programa' => [$bien, ['hora' => '99:99', 'titulo' => 'Mal']]]);
ok($k($orden, 'programa1.hora') !== [] && $k($orden, 'programa0') === [], 'aviso: la clave lleva el índice CRUDO (antes de ordenar), que es el que marca el creador');
ok(strpos(array_values($av(['programa' => [['hora' => '', 'titulo' => 'A']]]))[0], 'Programa del día') !== false, 'aviso: nombra la sección (Programa del día)');
$rh = $av(['portada' => ['hashtag' => 'a b']]);
ok(isset($rh['portada.hashtag']) && strpos($rh['portada.hashtag'], 'Portada') === 0, 'aviso: nombra la sección (Portada)');
$hxs = f(conSeccion(crudo(), 'historia', ['texto' => str_repeat('h', 2001)], true));
ok($k($hxs, '.texto') !== [] && strpos(array_values($hxs)[0], 'Nuestra historia') !== false, 'aviso: historia de 2.001 (truncada), con el nombre de la sección');
ok(f(conSeccion(crudo(), 'historia', ['texto' => str_repeat('h', 2000)], true)) === [], 'sin aviso: historia de 2.000 justos');
ok(f(conSeccion(crudo(), 'historia', ['texto' => str_repeat('h', 3000)], false)) === [], 'historia apagada con texto largo: no bloquea');
ok(f(conSeccion(crudo(), 'historia', ['texto' => ''], true)) === [], 'historia activa sin texto: no bloquea (simplemente no existe)');
ok(faltan(normaliza_config(crudo(['portada' => ['hashtag' => 'a b']]))) === [], 'sin config crudo no hay comparación (compatibilidad)');
ok(count(f(crudo(['portada' => ['hashtag' => 'a b'], 'programa' => [['hora' => '', 'titulo' => 'x']]]))) === 2, 'los avisos nuevos viajan como faltantes (bloquean el pago igual que los demás)');

echo $fallos ? "ROJO: " . ($n - $fallos) . "/$n\n" : "VERDE: $n/$n\n";
exit($fallos ? 1 : 0);
