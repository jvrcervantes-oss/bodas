<?php
// Piel «Terraza» de Cítricos y azahar (owner, 14-oct-2026; sustituye a «Limonar»): la piel no toca otro diseño, el
// programa del día con 0-8 paradas y su icono, ceremonia y convite, los nombres con el «&» aparte, el monograma de la
// cabecera, una sola cuenta atrás (en «Guardad la fecha», como los demás) y lo que se fue con Limonar. Sin red.
// La maquetación la miden en el navegador tests/fabrica_atelier.js (leyes) y tests/cuenta_atras.js (el reloj).
// Uso: php tests/citricos_test.php  (sale con 1 si algo falla). Datos inventados.

declare(strict_types=1);

$tmp = sys_get_temp_dir() . '/bodas_citricos_test_' . bin2hex(random_bytes(4));
mkdir($tmp, 0700, true);
define('SIN_CONFIG_LOCAL', true);
define('DATA_DIR', $tmp);
define('BASE_DOMAIN', 'bodaenlace.com');
define('CREATOR_HOST', 'bodaenlace.com');
define('CREATOR_BASE', '');
file_put_contents($tmp . '/secrets.php', '<?php return ' . var_export(['pasarela' => 'lemon', 'empresa' => ['titular' => 'Titular De Prueba', 'nif' => '00000000T', 'domicilio' => 'Calle Prueba 1']], true) . ';');
$raiz = dirname(__DIR__);
foreach (['core', 'schema', 'render', 'mapa'] as $m) require_once $raiz . '/app/' . $m . '.php';

$fallos = 0;
$n = 0;
function ok(bool $c, string $que): void { global $fallos, $n; $n++; if (!$c) { $fallos++; echo "FALLA: $que\n"; } }
function hay(string $h, string $t): bool { return strpos($h, $t) !== false; }

function crudo(array $cambia = []): array {
    $c = config_inicial();
    $c['pareja'] = ['nombre1' => 'Ana', 'nombre2' => 'Luis', 'union' => '&', 'email' => 'pareja@example.com'];
    $c['fecha'] = '2027-06-12';
    $c['ceremonia']['lugar'] = 'Ermita';
    $c['ceremonia']['hora'] = '12:00';
    return array_replace_recursive($c, $cambia);
}
function momentos(int $k): array {
    $o = [];
    for ($i = 0; $i < $k; $i++) $o[] = ['hora' => sprintf('%02d:%02d', 15 + intdiv($i, 2), ($i % 2) * 30), 'titulo' => 'Momento ' . ($i + 1), 'lugar' => '', 'nota' => ''];
    return $o;
}
$ctx = ['modo' => 'live', 'assets' => '/assets/', 'slug' => 'prueba', 'foto' => '', 'mapa' => null];
$inicio = fn(array $cambia, array $x = []): string => (string) render_pagina(normaliza_config(crudo($cambia)), '', $x + $ctx);
/** El trozo de una sección de la portada, de su <section class="…"> a su </section>. */
function seccion(string $h, string $clase): string {
    $i = strpos($h, '<section class="' . $clase . '"');
    return $i === false ? '' : substr($h, $i, (int) strpos($h, '</section>', $i) - $i);
}

// 1. CSS: ninguna regla con .atelier-citricos comparte selector con otro diseño, el bloque «Terraza» va todo bajo
//    body.atelier-citricos, sin sombras ni pesos de más, y no queda nada de Limonar
$css = (string) file_get_contents($raiz . '/assets/boda.css');
$sinComentarios = (string) preg_replace('~/\*.*?\*/~s', '', $css);
preg_match_all('~([^{}]+)\{~', $sinComentarios, $sel);
// Selector a selector (la regla común de la palanca «arte» nombra una pieza de cada diseño, cada una en el suyo). El de
// limonar_test.php llevaba un carácter de retroceso (0x08) en vez de \b en la expresión y no cazaba nunca nada
$mezcla = [];
foreach ($sel[1] as $s) foreach (explode(',', $s) as $parte) {
    if (strpos($parte, 'atelier-citricos') === false) continue;
    foreach (array_keys(ATELIER) as $k) if ($k !== 'citricos' && preg_match('~\.atelier-' . $k . '(?![a-z0-9_-])~', $parte)) $mezcla[] = trim($parte);
}
ok(!$mezcla, 'ningún selector junta .atelier-citricos con otro diseño: ' . implode(' | ', array_slice($mezcla, 0, 3)));
$prueba = array_filter(['body.atelier-citricos .x', 'body.atelier-citricos.atelier-lacre .x', 'body.atelier-citricos .atelier-masia-y'],
    fn($p) => (bool) preg_match('~\.atelier-(lacre|masia)(?![a-z0-9_-])~', $p));
ok(array_values($prueba) === ['body.atelier-citricos.atelier-lacre .x'], 'la comprobación de mezclas caza una mezcla de verdad (y solo esa)');
$i = strrpos(substr($css, 0, (int) strpos($css, 'Cítricos y azahar · piel «Terraza»')), '/*');
ok($i !== false && strpos($css, 'Cítricos y azahar · piel «Terraza»') !== false, 'el bloque «Terraza» existe');
$bloque = (string) preg_replace('~/\*.*?\*/~s', '', substr($css, (int) $i));
preg_match_all('~([^{}]+)\{~', $bloque, $sb);
$fuera = [];
foreach ($sb[1] as $s) foreach (explode(',', $s) as $parte) {
    $parte = trim($parte);
    if ($parte === '' || $parte[0] === '@' || preg_match('~^(from|to|\d+%)$~', $parte)) continue;
    if (strpos($parte, 'body.atelier-citricos') !== 0) $fuera[] = $parte;
}
ok(count($sb[1]) > 100 && !$fuera, 'todo el bloque «Terraza» empieza por body.atelier-citricos: ' . implode(' | ', array_slice($fuera, 0, 3)));
ok(!preg_match('~body\.atelier-citricos[^{]*\{[^}]*box-shadow:\s*+(?!none)~', $bloque), '«Terraza» no pone sombras');
ok(!preg_match('~body\.atelier-citricos[^{]*\{[^}]*font-weight:\s*([6-9]00|bold)~', $bloque), '«Terraza» no pasa de peso 500');
ok(!preg_match('~drop-shadow|text-shadow:\s*(?!none)~', $bloque), '«Terraza» sin drop-shadow ni sombra de texto');
$viejo = array_filter(['.cit-curva', '.cit-paso', '.cit-cal', '.cit-cuenta', '.cit-hoy', '.cit-racimo', '.sello-circular', '.sello-txt', '.sello-fondo', 'gira-lento', 'piel «Limonar»'],
    fn($p) => hay($sinComentarios, $p) || ($p === 'piel «Limonar»' && hay($css, $p . ' ')));
ok(!$viejo, 'no queda CSS de Limonar: ' . implode(', ', $viejo));
foreach (['terraza', 'colinas', 'bodegon', 'azahar', 'ramita', 'corazon', 'laurel'] as $f) {
    $svg = (string) @file_get_contents($raiz . '/assets/img/atelier/' . $f . '-citricos.svg');
    ok($svg !== '' && hay($svg, '<svg') && !preg_match('~<script|\son[a-z]+=|(?:href|src)="(?!#)~i', $svg), "$f-citricos.svg existe y es solo dibujo (sin scripts ni enlaces fuera)");
    ok(hay($css . (string) file_get_contents($raiz . '/app/render.php'), $f . '-citricos.svg'), "$f-citricos.svg se usa");
}

// 2. Paradas del día: el programa del Esencial no cambia; el de Cítricos mete la ceremonia sin hora la primera
$c = normaliza_config(crudo(['programa' => momentos(2)]));
ok(array_column(paradas_dia($c), 'titulo') === ['Ceremonia', 'Momento 1', 'Momento 2'], 'paradas por hora (sin convite)');
$sinHora = normaliza_config(crudo(['ceremonia' => ['hora' => ''], 'convite' => ['lugar' => 'Finca', 'hora' => ''], 'programa' => momentos(1)]));
ok(array_column(paradas_dia($sinHora), 'titulo') === ['Momento 1'], 'sin $completa, lo que no tiene hora no entra (como antes)');
ok(array_column(paradas_dia($sinHora, true), 'titulo') === ['Ceremonia', 'Convite', 'Momento 1'], 'con $completa: ceremonia y convite sin hora delante');
$vacia = normaliza_config(crudo(['ceremonia' => ['lugar' => '', 'hora' => '']]));
ok(paradas_dia($vacia, true) === [], 'sin ceremonia ni convite ni programa: 0 paradas');

// 3. Programa del día: un hito por parada (1, 3, 6, 7 y 8), cada uno con su icono; sin paradas no hay programa
foreach ([0, 2, 5, 6] as $k) {
    $esperado = 1 + min($k, MAX_PROGRAMA);
    $dia = seccion($inicio(['atelier' => 'citricos', 'programa' => momentos($k)]), 'cit-dia');
    ok(substr_count($dia, '<li class="cit-hito rv">') === $esperado && substr_count($dia, '<svg class="cit-ico"') === $esperado, "programa con $esperado paradas, una con su icono");
}
$ocho = seccion($inicio(['atelier' => 'citricos', 'convite' => ['lugar' => 'Finca', 'hora' => '14:00'], 'programa' => momentos(6)]), 'cit-dia');
ok(substr_count($ocho, '<li class="cit-hito rv">') === 8, 'programa con 8 paradas (ceremonia, convite y 6 momentos)');
ok(strpos($ocho, '<h3>Ceremonia</h3>') < strpos($ocho, '<h3>Convite</h3>') && strpos($ocho, '<h3>Convite</h3>') < strpos($ocho, '<h3>Momento 1</h3>'), 'el programa va por hora');
ok(!hay($inicio(['atelier' => 'citricos', 'ceremonia' => ['lugar' => '', 'hora' => '']]), 'class="cit-dia"'), 'sin paradas, sin programa');
$x = seccion($inicio(['atelier' => 'citricos', 'programa' => [['hora' => '13:00', 'titulo' => '<b>Brindis</b>', 'lugar' => 'Patio "A"', 'nota' => 'Con <i>cava</i>']]]), 'cit-dia');
ok(hay($x, '&lt;b&gt;Brindis&lt;/b&gt;') && hay($x, 'Patio &quot;A&quot;') && hay($x, 'Con &lt;i&gt;cava&lt;/i&gt;') && !hay($x, '<b>Brindis') && !hay($x, '<i>cava'), 'el programa escapa título, lugar y nota');
ok(hay($x, '<span class="cit-h">13:00</span>') && hay($x, '<p class="cit-l">Patio') && hay($x, '<p class="cit-n">Con'), 'hora, lugar y nota en su sitio');
$tipo = fn(string $t): string => hito_icono(['tipo' => 'momento', 'titulo' => $t]);
ok(hito_icono(['tipo' => 'ceremonia', 'titulo' => 'Ceremonia']) === 'anillos' && hito_icono(['tipo' => 'convite', 'titulo' => 'Convite']) === 'campana', 'ceremonia con anillos, convite con campana');
$casos = ['Sesión de fotos' => 'camara', 'RETRATOS en el jardín' => 'camara', 'Corte de la tarta' => 'tarta', 'Postre' => 'tarta',
    'Primer baile' => 'musica', 'Fiesta' => 'musica', 'Música en directo' => 'musica', 'Llega el DJ' => 'musica', 'Barra libre' => 'musica',
    'Aperitivo' => 'copas', 'Cóctel de bienvenida' => 'copas', 'Brindis' => 'copas', 'Cena' => 'campana', 'Banquete' => 'campana',
    'Paseo junto al mar' => 'corazon', 'Adjudicación' => 'corazon', ''  => 'corazon'];
$mal = [];
foreach ($casos as $t => $k) if ($tipo((string) $t) !== $k) $mal[] = "«$t» → " . $tipo((string) $t) . " (esperaba $k)";
ok(!$mal, 'cada momento con el icono de su título: ' . implode('; ', $mal));
$svgs = [];
foreach (array_keys(HITOS_CITRICOS) as $k) $svgs[] = svg_hito($k);
ok(count(array_unique($svgs)) === count(HITOS_CITRICOS) && !array_filter($svgs, fn($s) => !preg_match('~^<svg class="cit-ico" viewBox="0 0 40 40" aria-hidden="true">.+</svg>$~', $s)), 'los iconos son distintos, de 40 y escondidos al lector');

// 4. Ceremonia y convite: una ficha por lugar con cómo llegar; con convite, el sello de limones entre las dos
$solo = seccion($inicio(['atelier' => 'citricos', 'ceremonia' => ['direccion' => 'Plaza Mayor 1']]), 'cit-lugares');
ok(substr_count($solo, '<article class="cit-sitio rv">') === 1 && hay($solo, 'id="cit-lugares-t">Ceremonia</h2>') && !hay($solo, 'cit-sello'), 'solo ceremonia: una ficha, sin sello');
ok(hay($solo, '<p class="cit-sitio-d">Plaza Mayor 1</p>') && hay($solo, 'class="cit-mapa" href="https://maps.google.com/?q=') && hay($solo, 'rel="noopener noreferrer">Cómo llegar</a>'), 'la dirección y «Cómo llegar» a Google Maps');
$dos = seccion($inicio(['atelier' => 'citricos', 'convite' => ['lugar' => 'Finca "La Noria"', 'direccion' => 'Camino Viejo s/n', 'hora' => '14:00']]), 'cit-lugares');
ok(substr_count($dos, '<article class="cit-sitio rv">') === 2 && hay($dos, 'Ceremonia y convite</h2>') && substr_count($dos, 'class="cit-sello"') === 1, 'con convite: dos fichas y el sello entre las dos');
ok(hay($dos, 'Finca &quot;La Noria&quot;') && hay($dos, 'Camino Viejo s/n') && !hay($dos, 'Finca "La'), 'el lugar del convite, escapado');
$sinLugar = seccion($inicio(['atelier' => 'citricos', 'ceremonia' => ['lugar' => '']]), 'cit-lugares');
ok(hay($sinLugar, 'Lugar de la ceremonia') && !hay($sinLugar, 'cit-mapa'), 'sin lugar: el hueco con su rótulo y sin «Cómo llegar»');

// 5. Portada: nombres en dos líneas con el conector aparte, fecha en cifras, la terraza y los accesos
$cit = $inicio(['atelier' => 'citricos', 'programa' => momentos(2), 'portada' => ['hashtag' => 'AnaYLuis']]);
ok(hay($cit, '<h1 class="hero-names" style="--nl:10">Ana <span class="hero-n2"><i>&amp;</i> Luis</span></h1>'), 'nombres: el primero solo y «& Luis» debajo, con el «&» en <i>');
ok(hay($inicio(['atelier' => 'citricos', 'pareja' => ['nombre1' => 'María del Carmen', 'nombre2' => 'José Antonio']]), 'María del Carmen <span class="hero-n2"><i>&amp;</i> José Antonio</span>'), 'nombres largos enteros, con el «&» aparte');
ok(hay($inicio(['atelier' => 'citricos', 'pareja' => ['union' => 'y']]), 'Ana <span class="hero-n2"><i>y</i> Luis</span>'), 'el conector «y» va igual');
ok(hay($inicio(['atelier' => 'citricos', 'pareja' => ['nombre2' => '']]), '<h1 class="hero-names" style="--nl:3">Ana</h1>'), 'con un solo nombre, el nombre solo');
ok(hay($inicio(['atelier' => 'citricos', 'pareja' => ['nombre1' => '<b>Ana</b>']]), '&lt;b&gt;Ana&lt;/b&gt; <span class="hero-n2">'), 'los nombres se escapan');
ok(hay($cit, '<p class="cit-fecha"><time datetime="2027-06-12">12.06.2027</time><span>sábado</span></p>'), 'la fecha en cifras con el día de la semana');
ok(hay($cit, '<p class="hero-hashtag">#AnaYLuis</p>') && hay($cit, '<nav class="cit-nav" aria-label="Accesos">'), 'el hashtag y los accesos bajo la fecha');
ok(hay($cit, '<div class="cit-escena" aria-hidden="true"><img class="cit-terraza" src="/assets/img/atelier/terraza-citricos.svg" alt=""></div>'), 'la terraza, de adorno');
ok(hay($cit, 'class="atelier-arte esq esq-i"') && hay($cit, 'class="atelier-arte cit-rama"'), 'las dos ramas de limonero de arriba');
ok(hay($cit, '<span class="cit-mono" aria-hidden="true"><b>A</b><b>L</b></span>') && hay($cit, 'class="nav-brand" aria-label="Ana &amp; Luis, inicio"'), 'el monograma de la cabecera, con el nombre de la pareja para el lector');
ok(mono_citricos(normaliza_config(crudo(['pareja' => ['nombre1' => 'álvaro', 'nombre2' => 'Íñigo']]))) === '<b>Á</b><b>Í</b>', 'iniciales con tilde, en mayúscula');
ok(mono_citricos(normaliza_config(crudo(['pareja' => ['nombre1' => '', 'nombre2' => '']]))) === '<b>♥</b>', 'sin nombres, un corazón');
ok(!hay($cit, '<p class="cit-dicho">') || preg_match('~<p class="cit-dicho"><span>[^<]+</span></p>~', $cit) === 1, 'la frase de la invitación, entre filetes');

// 6. El orden de la portada y los enganches de boda.js: una sola cuenta atrás, en «Guardad la fecha»
$orden = [];
foreach (['hero', 'cit-dia', 'cit-lugares', 'cit-carta rv', 'save-band', 'quick cit-fichas', 'cit-rsvp'] as $s) $orden[$s] = strpos($cit, '<section class="' . $s . '"');
ok(!in_array(false, $orden, true) && array_values($orden) === array_values(array_filter($orden)) && $orden === array_combine(array_keys($orden), (function ($v) { sort($v); return $v; })(array_values($orden))), 'portada, programa, lugares, carta, fecha, fichas y confirmar, en ese orden: ' . json_encode($orden));
$fecha = seccion($cit, 'save-band');
ok(substr_count($cit, 'id="countdown"') === 1 && hay($fecha, 'id="countdown"') && hay($fecha, 'data-objetivo="2027-06-12T12:00:00"'), 'un solo #countdown, en «Guardad la fecha», a la hora de la ceremonia');
ok(substr_count($fecha, 'data-c="') === 4 && !hay($cit, 'data-c="weeks"') && !hay($cit, 'data-c-hoy'), 'cuatro cifras (días, horas, min, seg), sin las semanas ni el aviso de Limonar');
ok(hay($fecha, 'id="calBtn"') && hay($fecha, 'id="calOptions"') && hay($fecha, 'href="/boda.ics" download'), 'el botón del calendario y sus dos opciones');
ok(!hay($cit, 'class="event-list"') && !hay($cit, 'class="prog '), 'Cítricos no pinta las tarjetas ni el programa de siempre');
$sinFecha = $inicio(['atelier' => 'citricos', 'fecha' => '']);
ok(!hay($sinFecha, 'save-band') && !hay($sinFecha, 'id="countdown"') && !hay($sinFecha, 'cit-fecha'), 'sin fecha: sin cuenta atrás ni fecha en la portada');
$zip = $inicio(['atelier' => 'citricos'], ['modo' => 'zip', 'assets' => 'assets/']);
ok(hay($zip, 'href="boda.ics" download') && hay($zip, 'src="assets/img/atelier/terraza-citricos.svg"'), 'en el ZIP, el .ics y las imágenes van en relativo');
$foto = $inicio(['atelier' => 'citricos', 'foto' => true], ['foto' => '/foto?v=1']);
ok(hay($foto, '<figure class="cit-instantanea"><div class="cit-foto"><img data-foto src="/foto?v=1" alt="Ana &amp; Luis"') && hay($foto, 'loading="eager"'), 'la foto, en instantánea, con data-foto (vista previa) y sin carga perezosa');
ok(!hay($cit, '<figure class="cit-instantanea">'), 'sin foto, sin instantánea');
$rsvp = seccion($cit, 'cit-rsvp');
ok(hay($rsvp, '<a href="/confirmar-asistencia" class="btn">Confirmar</a>') && substr_count($rsvp, 'class="btn') === 1, 'la banda de confirmar con su botón');
$fichas = seccion($inicio(['atelier' => 'citricos', 'portada' => ['vestimenta' => 'Formal de verano']]), 'quick cit-fichas');
ok(hay($fichas, '<div class="cit-vestir rv">') && hay($fichas, '<p class="cit-vestir-dicho">Formal de verano</p>') && substr_count($fichas, 'cit-bodegon') === 1, 'el dress code en su ficha de papel limón, con el bodegón');

// 6b. Arriba de la portada, la foto de la pareja con su acabado (ESCENAS_CITRICOS, aviso de usuario 15-oct-2026)
ok(normaliza_config(crudo())['escena'] === 'terraza', 'de fábrica, la terraza');
ok(normaliza_config(crudo(['escena' => 'lapiz']))['escena'] === 'terraza' && normaliza_config(crudo(['escena' => '<x>']))['escena'] === 'terraza',
    'un acabado que no está en la lista vuelve a la terraza');
ok(normaliza_config(crudo(['escena' => ['acuarela']]))['escena'] === 'terraza', 'un array en lugar del acabado no rompe: terraza');
$crudoMalo = normaliza_config(crudo(['atelier' => 'citricos', 'foto' => true]));
$crudoMalo['escena'] = '"><b>x';   // como si alguien pintara sin normalizar
$hm = (string) render_pagina($crudoMalo, '', ['foto' => '/foto?v=1'] + $ctx);
ok(!hay($hm, '<b>x') && !hay($hm, 'cit-escena-foto') && hay($hm, 'class="cit-terraza"'), 'al pintar, un acabado fuera de la lista no llega al HTML: terraza');
ok(hay($foto, 'class="cit-terraza"') && !hay($foto, 'cit-escena-foto') && !hay($foto, '<svg class="cit-defs"'), 'con foto y la terraza elegida, como siempre: el dibujo arriba y la foto en la carta');
$sinF = $inicio(['atelier' => 'citricos', 'escena' => 'salvia']);
ok(hay($sinF, 'class="cit-terraza"') && !hay($sinF, 'cit-escena-foto'), 'sin foto, la terraza aunque se haya elegido un acabado');
foreach (array_keys(ESCENAS_CITRICOS) as $k) {
    if ($k === 'terraza') continue;
    $h = $inicio(['atelier' => 'citricos', 'foto' => true, 'escena' => $k], ['foto' => '/foto?v=1']);
    $ie = strpos($h, '<div class="cit-escena cit-escena-foto">');
    $esc = $ie === false ? '' : substr($h, $ie, (int) strpos($h, '</div></div>', $ie) - $ie);
    ok($esc !== '' && !hay($h, 'cit-terraza') && !hay($esc, 'aria-hidden="true"><div'), "«{$k}»: la foto en lugar de la terraza, sin esconderla a los lectores de pantalla");
    ok(hay($esc, '<img data-foto src="/foto?v=1" alt="Foto de Ana &amp; Luis" width="960" height="1280" loading="eager" fetchpriority="high"'), "«{$k}»: data-foto (vista previa), texto alternativo y carga con prioridad");
    ok(hay($esc, 'style="clip-path:url(#cit-rasgado)"') && substr_count($h, '<clipPath id="cit-rasgado"') === 1, "«{$k}»: el borde rasgado, una vez");
    ok(substr_count($h, '<filter id="cit-ef-') === ($k === 'natural' ? 0 : 1) && ($k === 'natural' || hay($esc, 'style="filter:url(#cit-ef-' . $k . ')"')),
        "«{$k}»: solo el filtro del acabado elegido");
    ok(!hay($h, '<figure class="cit-instantanea">'), "«{$k}»: la carta no repite la foto");
}
// El filtro no lleva sombras ni nada que Safari recalcule en cada frame de scroll (medido el 15-oct: 65 de 239 frames lentos)
$defs = svg_escena(array_keys(ESCENAS_CITRICOS));
foreach (['feDropShadow', 'feTurbulence', 'feDisplacementMap', 'feConvolveMatrix', 'arithmetic', 'feMorphology', 'feImage', 'mode="'] as $prohibido) {
    ok(!hay($defs, $prohibido), "los acabados no usan $prohibido");
}
// Las tintas salen de la paleta del diseño: el papel y la tinta de ATELIER, no hex sueltos
$P = canales_hex(ATELIER['citricos']['colores'][4]);
ok(hay(filtro_escena('salvia'), 'tableValues="' . implode(' ', [round(canales_hex(ATELIER['citricos']['tinta'])[0], 3), round(canales_hex(ATELIER['citricos']['colores'][1])[0], 3)])) && hay(filtro_escena('salvia'), ' ' . round($P[0], 3) . '"'),
    'la tinta salvia va de la tinta al papel pasando por la salvia del diseño');
// El rasgado es el de la terraza, pasado a la caja (0-1) y cerrado
$r = rasgado_escena();
preg_match_all('~[ML]([\d.]+),([\d.]+)~', $r, $pr);
ok(count($pr[1]) > 20 && max(array_map('floatval', $pr[1])) <= 1 && max(array_map('floatval', $pr[2])) <= 1 && min(array_map('floatval', $pr[2])) === 0.0 && str_ends_with($r, 'Z'),
    'el borde rasgado sale del dibujo de la terraza, en la caja de la foto');
$zipF = $inicio(['atelier' => 'citricos', 'foto' => true, 'escena' => 'acuarela'], ['modo' => 'zip', 'assets' => 'assets/', 'foto' => 'foto.webp']);
ok(hay($zipF, '<img data-foto src="foto.webp"') && hay($zipF, '<filter id="cit-ef-acuarela"'), 'en el ZIP, la foto en relativo y el filtro dentro de la página (sin conexión)');
$prev = $inicio(['atelier' => 'citricos', 'foto' => true, 'escena' => 'salvia'], ['modo' => 'preview']);
ok(hay($prev, '<img data-foto src="" alt="Foto de Ana &amp; Luis"'), 'en la vista previa, el hueco que rellena vista-previa.js');

// 7. Los otros diseños no llevan nada de esto y siguen con la cuenta atrás en «Guardad la fecha»
foreach (['lacre', 'herbario', 'atardecer', ''] as $otro) {
    $h = $inicio(['atelier' => $otro, 'programa' => momentos(2)]);
    $nombre = '«' . ($otro ?: 'Esencial') . '»';
    $piezas = array_filter(['cit-dia', 'cit-hito', 'cit-sitio', 'cit-mono', 'cit-escena', 'cit-carta', 'cit-fichas', 'cit-rsvp', 'terraza-citricos', '<i>&amp;</i>'], fn($p) => hay($h, $p));
    ok(!$piezas, "el diseño $nombre no lleva piezas de Cítricos: " . implode(', ', $piezas));
    $ib = (int) strpos($h, 'class="save-band"');
    ok(substr_count($h, 'id="countdown"') === 1 && $ib > 0 && (int) strpos($h, 'id="countdown"') > $ib, "el diseño $nombre sigue con la cuenta atrás en «Guardad la fecha»");
}

// 8. Lo que se fue con Limonar: ni funciones ni lectura de semanas en boda.js
foreach (['curva_itinerario', 'calendario_mes', 'sello_circular'] as $f) ok(!function_exists($f), "$f() ya no existe");
$js = (string) file_get_contents($raiz . '/assets/js/boda.js');
ok(!hay($js, 'data-c="weeks"') && !hay($js, 'data-c-hoy') && hay($js, "querySelector('[data-c=\"days\"]')"), 'boda.js cuenta días, horas, min y seg, sin las semanas ni el aviso de Limonar');

@array_map('unlink', glob($tmp . '/*') ?: []);
@rmdir($tmp);
echo ($fallos ? "ROJO: $fallos de $n\n" : "VERDE: $n/$n\n");
exit($fallos ? 1 : 0);
