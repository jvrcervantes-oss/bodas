<?php
// Correos con diseño (27-sep-2026): cada correo a la pareja sale en multipart/alternative (texto + HTML) y el HTML es la
// MISMA información que el texto. Uso: php tests/correo_html_test.php  (sale con 1 si algo falla). Sin red: mensaje_mime
// es pura y nada se envía.

declare(strict_types=1);

$tmp = sys_get_temp_dir() . '/bodas_correo_html_' . bin2hex(random_bytes(4));
define('SIN_CONFIG_LOCAL', true);
define('DATA_DIR', $tmp);
define('BASE_DOMAIN', 'bodaenlace.com');
define('CREATOR_HOST', 'bodaenlace.com');
define('CREATOR_BASE', '');
define('CORREO_A_FICHERO', true);
define('LEMON_API', 'http://127.0.0.1:9');
$raiz = dirname(__DIR__);
foreach (['core', 'schema', 'render', 'foto', 'alta', 'mapa', 'cortesia', 'estudio', 'borrador', 'invitados', 'creador', 'landing', 'vista_constructor', 'boda', 'galeria', 'proxy'] as $m) {
    require_once $raiz . '/app/' . $m . '.php';
}
$fallos = 0; $n = 0;
function ok(bool $c, string $q): void { global $fallos, $n; $n++; if (!$c) { $fallos++; echo "FALLA: $q\n"; } }

/** Partes hoja de un MIME, en orden: [[content-type(:fichero), cuerpo decodificado], ...], entrando en los multipart. */
function partes_mime(string $mime): array {
    [$cab, $cuerpo] = explode("\r\n\r\n", $mime, 2) + [1 => ''];
    if (!preg_match('~Content-Type: ([^;\r\n]+)~', $cab, $ct)) return [];
    if (!preg_match('~boundary="([^"]+)"~', $cab, $bm)) {
        return [[trim($ct[1]) . (preg_match('~filename="([^"]+)"~', $cab, $fn) ? ':' . $fn[1] : ''), base64_decode(str_replace("\r\n", '', $cuerpo))]];
    }
    $out = [];
    foreach (array_slice(explode('--' . $bm[1], $cuerpo), 1) as $trozo) {
        if (str_starts_with($trozo, '--')) break;
        $out = array_merge($out, partes_mime(ltrim($trozo, "\r\n")));
    }
    return $out;
}
$sinTextoHtml = fn(string $h): string => preg_replace('~\s+~u', ' ', html_entity_decode(strip_tags($h), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

$L = textos_legales();
$cfg = config_inicial();
$cfg['fecha'] = date('Y-m-d', strtotime('+200 days'));
escribe_json(dir_boda('ana-y-luis') . '/config.json', $cfg);
escribe_json(dir_boda('ana-y-luis') . '/pedido.json', ['email' => 'pareja@example.com']);
$enlace = 'https://ana-y-luis.bodaenlace.com/panel/clave?t=abc123&x=<1>';   // & y <> para ver el escape
$ac = ['fecha' => '2026-09-27T18:02:00+02:00', 'version' => $L['version'], 'condiciones' => $L['check_condiciones'], 'desistimiento' => $L['check_desistimiento']];
$ped = ['slug' => 'ana-y-luis', 'email' => 'pareja@example.com', 'pasarela' => 'lemon', 'factura' => '', 'aceptacion' => $ac, 'ls' => ['order_number' => 4834612], 'importe' => ['total' => 20900], 'atelier' => 'x'];
$reg = ['slug' => 'ana-y-luis', 'email' => 'pareja@example.com', 'factura' => '', 'aceptacion' => ['fecha' => date('c'), 'version' => $L['version'], 'condiciones' => $L['check_condiciones_regalo']]];
$pe = ['session_id' => 'ls_9', 'ls' => ['order_number' => 9], 'importe' => ['total' => 1900]];
$me = ['aceptacion' => ['fecha' => date('c'), 'version' => $L['version'], 'casilla' => 'Casilla <b>literal</b> & «extra»', 'vendedor' => 'Lemon Squeezy']];
$mm = ['aceptacion' => ['fecha' => date('c'), 'version' => $L['version'], 'desistimiento' => (string) $L['check_mejora']]];
$condDoc = documento_legal('condiciones', 'Condiciones del servicio');

$db = datos_bienvenida($ped, $cfg, $enlace);
$dr = datos_bienvenida($reg, $cfg, $enlace);
$dx = datos_extra_correo('ana-y-luis', 'mesas', $pe, $me);
$dm = datos_mejora_correo('ana-y-luis', $pe, $mm);
$correos = [
    'bienvenida' => [bienvenida_texto($db), bienvenida_html($db), $db],
    'bienvenida regalo' => [bienvenida_texto($dr), bienvenida_html($dr), $dr],
    'extra' => [compra_texto($dx), compra_html($dx), $dx],
    'mejora' => [compra_texto($dm), compra_html($dm), $dm],
    'acceso (recuperar)' => partes_acceso_panel('ana-y-luis', $enlace, false) + [2 => null],
    'acceso (reenvío)' => partes_acceso_panel('ana-y-luis', $enlace, true) + [2 => null],
];

// Las funciones públicas de texto siguen dando lo mismo que el renderizado común (la plana no cambió de fuente)
ok(texto_bienvenida($ped, $cfg, $enlace) === $correos['bienvenida'][0], 'texto_bienvenida = bienvenida_texto(datos)');
ok(texto_extra_correo('ana-y-luis', 'mesas', $pe, $me) === $correos['extra'][0], 'texto_extra_correo = compra_texto(datos)');
ok(texto_mejora_correo('ana-y-luis', $pe, $mm) === $correos['mejora'][0], 'texto_mejora_correo = compra_texto(datos)');

foreach ($correos as $q => [$texto, $html, $d]) {
    // 1. MIME: alternative con las dos partes, en ese orden, y cada una decodifica a lo que se construyó
    $mime = mensaje_mime('hola@bodaenlace.com', 'pareja@example.com', 'Asunto', $texto, [], $html);
    $p = partes_mime($mime);
    $pm = array_column($p, 1, 0);
    ok(strpos($mime, 'Content-Type: multipart/alternative; boundary="') !== false && strpos($mime, 'multipart/mixed') === false, "$q: multipart/alternative sin mixed");
    ok(array_column($p, 0) === ['text/plain', 'text/html'], "$q: text/plain primero, text/html después");
    ok(str_replace("\r\n", "\n", (string) ($pm['text/plain'] ?? '')) === $texto && str_replace("\r\n", "\n", (string) ($pm['text/html'] ?? '')) === $html, "$q: las dos partes decodifican íntegras (UTF-8, base64)");
    ok(substr_count($mime, 'charset=UTF-8') === 2 && substr_count($mime, 'Content-Transfer-Encoding: base64') === 2, "$q: ambas partes UTF-8 + base64");
    // 2. Sin recursos externos (img, link, @import, url(), src=) ni script
    ok(!preg_match('~<img\b|<link\b|@import|url\(|\bsrc=|<script\b|<iframe\b~i', $html), "$q: sin recursos externos ni scripts");
    ok(strpos($html, 'http://') === false, "$q: ningún http:// en claro");
    ok(strpos($html, '<meta name="color-scheme" content="light dark">') !== false && strpos($html, 'prefers-color-scheme:dark') !== false && strpos($html, 'max-width:620px') !== false, "$q: modo oscuro y móvil");
    ok(strlen($html) < 102000, "$q: HTML por debajo del recorte de Gmail (" . strlen($html) . ' bytes)');
    // 3. Enlaces: el del panel (o la URL de la compra) está, escapado, como botón
    $destino = $d['donde'] ?? $enlace;
    ok(strpos($html, 'href="' . h($destino) . '"') !== false, "$q: el botón lleva el enlace correcto escapado");
    ok(strpos($html, 'x=<1>') === false && strpos($html, '&x=') === false, "$q: nada del enlace sin escapar");
    if ($d === null) continue;
    // 4. Misma información: cada valor del resumen (la fuente que pinta también el texto plano) está en el HTML
    $visible = $sinTextoHtml($html);
    foreach ($d['resumen'] as [$et, $val]) ok(strpos($visible, preg_replace('~\s+~u', ' ', correo_mayuscula($val))) !== false && strpos($texto, "- $et: $val\n") !== false, "$q: fila «{$et}» en texto y HTML");
    ok(strpos($visible, $d['marcasteis']) !== false, "$q: la frase de la aceptación con fecha y versión");
    // 5. Casillas literales (escapadas) en cita
    foreach ((array) ($d['casillas'] ?? [$d['casilla']]) as $c) {
        if ($c === '') continue;
        ok(strpos($html, '«' . h($c) . '»') !== false && strpos($texto, '«' . $c . '»') !== false, "$q: casilla literal en texto y HTML");
    }
    // 6. Soporte duradero: las condiciones ÍNTEGRAS en el cuerpo HTML, sin una letra de menos que en el texto
    $leg = correo_legal_html($condDoc);
    ok(strpos($html, $leg) !== false, "$q: el HTML lleva el bloque legal completo");
    $planoLegal = legal_a_texto($condDoc);
    ok(legal_a_texto($leg) === $planoLegal && strpos($texto, $planoLegal) !== false, "$q: condiciones del HTML = condiciones del texto, letra a letra");
    ok(strpos($visible, 'ANEXO II') !== false || stripos($visible, 'Anexo II') !== false, "$q: llega hasta el anexo II");
}
// Longitud del texto legal: el HTML visible no se queda corto frente al plano (± 3 % por viñetas y saltos)
$lp = mb_strlen(preg_replace('~\s+~u', '', legal_a_texto($condDoc)));
$lh = mb_strlen(preg_replace('~\s+~u', '', $sinTextoHtml(correo_legal_html($condDoc))));
ok(abs($lp - $lh) <= $lp * 0.03, "condiciones: longitud del texto legal plano $lp y HTML $lh");
// La mayúscula de la caja no toca direcciones: el email de reclamaciones sale tal cual
$mailE = (string) empresa_publica()['email'];
ok(correo_mayuscula($mailE) === $mailE && strpos($correos['bienvenida'][1], ucfirst($mailE)) === false, 'el email de reclamaciones no se escribe con mayúscula');
ok(correo_mayuscula('sobre algo') === 'Sobre algo' && correo_mayuscula('https://x.es') === 'https://x.es', 'mayúscula solo en frases');
// Escape: una casilla con etiquetas no mete HTML
ok(strpos($correos['extra'][1], 'Casilla <b>literal</b>') === false && strpos($correos['extra'][1], 'Casilla &lt;b&gt;literal&lt;/b&gt; &amp; «extra»') !== false, 'la casilla va escapada con h()');
// Con adjunto (factura de Stripe): mixed por fuera, alternative dentro, y el adjunto
$mime = mensaje_mime('hola@bodaenlace.com', 'a@b.c', 'x', 'plano', ['BODA-1.html' => '<p>f</p>'], '<p>html</p>');
$p = partes_mime($mime);
ok(strpos($mime, 'multipart/mixed') < strpos($mime, 'multipart/alternative') && array_column($p, 0) === ['text/plain', 'text/html', 'text/html:BODA-1.html'], 'con adjunto: mixed > alternative (texto, html) + adjunto');
// Sin HTML, el mensaje es el de siempre (solo text/plain)
$mime = mensaje_mime('hola@bodaenlace.com', 'a@b.c', 'x', 'plano', []);
ok(strpos($mime, 'multipart') === false && strpos($mime, 'Content-Type: text/plain; charset=UTF-8') !== false, 'sin HTML ni adjuntos: solo text/plain, como antes');
// Un aviso encolado antiguo, sin clave html, sale igual (solo texto)
ok(aviso_envia(['tipo' => 'correo', 'para' => 'viejo@example.com', 'asunto' => 'x', 'texto' => 'hola']) === true && count(glob(dir_datos('correos_html', '*')) ?: []) === 0, 'aviso de la cola sin html: sale en texto');
// El envío de acceso deja la versión HTML junto al texto (modo a fichero)
correo_enlace_panel('ana-y-luis', 'pareja@example.com', $enlace);
ok(count(glob(dir_datos('correos_html', '*')) ?: []) === 1, 'correo_enlace_panel manda también el HTML');
// El reenvío del estudio pasa por la misma plantilla
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
estudio_reenviar('ana-y-luis');
$hs = array_map('file_get_contents', glob(dir_datos('correos_html', '*')) ?: []);
ok(count($hs) === 2 && count(array_filter($hs, fn($h) => strpos($h, 'enlace nuevo') !== false)) === 1, 'estudio_reenviar manda el HTML del reenvío');

foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
    $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
}
@rmdir($tmp);
echo ($fallos ? "ROJO: $fallos de $n" : "VERDE: $n/$n") . "\n";
exit($fallos ? 1 : 0);
