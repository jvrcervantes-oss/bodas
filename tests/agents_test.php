<?php
// /agents de punta a punta por HTTP (app/agents.php, 16-oct-2026): sin sesión 401 y ningún dato; entrada con CSRF y freno; la
// subida del PC solo con su llave y solo con una plantilla que lleve UN hueco dentro de un <script>; la foto no puede cerrar el
// <script>; cabeceras privadas siempre. La app en `php -S` (router_prueba.php), sin red. La contraseña y la llave se generan aquí
// en cada pasada (nada con forma de secreto en el repo). Uso: php tests/agents_test.php  (sale con 1 si falla)

declare(strict_types=1);

$tmp = sys_get_temp_dir() . '/bodas_agents_test_' . bin2hex(random_bytes(4));
$web = sys_get_temp_dir() . '/bodas_agents_web_' . bin2hex(random_bytes(4));   // docroot vacío fuera de DATA_DIR
mkdir($tmp, 0700, true);
mkdir($web, 0700, true);
$clave = bin2hex(random_bytes(12));
$llave = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
// Las huellas van en su fichero (el app/agents_huellas.php del repo, aquí una copia de la prueba); secrets.php ya no cuenta.
$huellasF = $tmp . '/agents_huellas.php';
$secretos = function (string $hashClave, string $shaLlave) use ($huellasF) {
    file_put_contents($huellasF, '<?php return ' . var_export(['AGENTS_CLAVE_SHA256' => $hashClave, 'AGENTS_SUBIDA_SHA256' => $shaLlave], true) . ';');
};
$secretos(hash('sha256', $clave), hash('sha256', $llave));
$fallos = 0;
function ok(bool $c, string $q): void { global $fallos; if (!$c) { $fallos++; echo "FALLA: $q\n"; } else echo "ok: $q\n"; }

$puerto = 18000 + random_int(0, 999);
$env = array_merge(getenv(), ['BODAS_TEST_DATA' => $tmp, 'BODAS_TEST_HUELLAS' => $huellasF]);
$srv = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $puerto, '-t', $web, __DIR__ . '/router_prueba.php'], [1 => ['file', $tmp . '/srv.log', 'a'], 2 => ['file', $tmp . '/srv.log', 'a']], $pipes, null, $env);
for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $puerto); $i++) usleep(100000);

/** Petición con cabeceras de vuelta. La cookie va a mano: la de /agents es Secure y curl no la manda por http. */
function pide(string $metodo, string $ruta, array $o = []): array {
    global $puerto;
    $ch = curl_init('http://127.0.0.1:' . $puerto . $ruta);
    $cab = ['Host: bodaenlace.com'];
    if (isset($o['cookie'])) $cab[] = 'Cookie: ' . $o['cookie'];
    foreach ($o['cab'] ?? [] as $c) $cab[] = $c;
    $opt = [CURLOPT_CUSTOMREQUEST => $metodo, CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => $cab];
    if (isset($o['form'])) $opt[CURLOPT_POSTFIELDS] = http_build_query($o['form']);
    if (isset($o['cuerpo'])) $opt[CURLOPT_POSTFIELDS] = $o['cuerpo'];
    curl_setopt_array($ch, $opt);
    $r = (string) curl_exec($ch);
    $st = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hs = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $h = [];
    foreach (explode("\r\n", substr($r, 0, $hs)) as $l) if (strpos($l, ':') !== false) { [$k, $v] = explode(':', $l, 2); $h[strtolower(trim($k))][] = trim($v); }
    return [$st, $h, substr($r, $hs)];
}
function cab(array $h, string $k): string { return implode(' | ', $h[strtolower($k)] ?? []); }
/** La cookie de sesión que fija la respuesta (nombre=valor) y la línea entera de Set-Cookie. */
function cookie(array $h): array {
    foreach ($h['set-cookie'] ?? [] as $l) if (stripos($l, 'axw_agents=') !== false) return [explode(';', $l)[0], $l];
    return ['', ''];
}
function csrf(string $html): string { return preg_match('/name="csrf" value="([a-f0-9]+)"/', $html, $m) ? $m[1] : ''; }
function sube(array $cuerpo, ?string $llaveUsada, string $tipo = 'application/json'): array {
    $cab = ['Content-Type: ' . $tipo];
    if ($llaveUsada !== null) $cab[] = 'Authorization: Bearer ' . $llaveUsada;
    return pide('POST', '/agents/sube.php', ['cab' => $cab, 'cuerpo' => $cuerpo['_crudo'] ?? json_encode($cuerpo)]);
}
$privadas = function (array $h, string $q) {
    ok(cab($h, 'X-Robots-Tag') === 'noindex, nofollow', "$q: X-Robots-Tag noindex, nofollow");
    ok(cab($h, 'Cache-Control') === 'private, no-store', "$q: Cache-Control private, no-store (" . cab($h, 'Cache-Control') . ')');
    ok(cab($h, 'Referrer-Policy') === 'no-referrer', "$q: Referrer-Policy no-referrer");
    ok(cab($h, 'X-Frame-Options') === 'DENY', "$q: X-Frame-Options DENY");
    $csp = cab($h, 'Content-Security-Policy');
    ok(strpos($csp, "frame-ancestors 'none'") !== false && strpos($csp, "form-action 'self'") !== false, "$q: CSP con frame-ancestors y form-action");
};

$MARCA = 'MARCA-FOTO-' . bin2hex(random_bytes(4));
$plantilla = "<!DOCTYPE html><html><head><title>Panel</title><script src=\"https://cdn.example/x.js\"></script>"
    . "<script>/*AXW_FOTO*/</script></head><body><p>Pega tu llave (sk-ant-…): empieza por sk-ant- y no lleva espacios.</p>"
    . "<script type=\"module\">document.body.dataset.v = window.AXW_FOTO.v;</script></body></html>";
$foto = ['v' => $MARCA, 'malo' => '</script><img src=x onerror=alert(1)>', 'dolar' => 'a$1b\\0c'];

// 1. Sin sesión: 401 con el formulario y ningún dato, y las cabeceras privadas
[$st, $h, $b] = pide('GET', '/agents/');
ok($st === 401 && strpos($b, 'name="clave"') !== false && strpos($b, '<script') === false, "GET /agents/ sin sesión → 401 con formulario y sin scripts ($st)");
$privadas($h, 'GET /agents/ sin sesión');
ok(strpos(cab($h, 'Content-Security-Policy'), 'script-src') === false, 'el formulario no admite ningún script');
[$st] = pide('GET', '/agents');
ok($st === 401, "GET /agents (sin barra) → 401 ($st)");
[$st, $h] = pide('GET', '/agents/plantilla.html');
ok($st === 404, "GET /agents/plantilla.html → 404 ($st)");
$privadas($h, '404 bajo /agents');
[$st] = pide('GET', '/agents/foto.json');
ok($st === 404, "GET /agents/foto.json → 404 ($st)");
[$st] = pide('GET', '/app/agents.php');
ok($st !== 200, "GET /app/agents.php no se sirve ($st)");
[$st, $h] = pide('PUT', '/agents/');
ok($st === 405, "PUT /agents/ → 405 ($st)");

// 2. Subida: sin llave, con llave mala, método distinto
[$st, $h, $b] = pide('GET', '/agents/sube.php');
ok($st === 405 && cab($h, 'Allow') === 'POST', "GET /agents/sube.php → 405 con Allow: POST ($st)");
$privadas($h, 'sube.php');
[$st] = sube(['foto' => $foto, 'plantilla' => $plantilla], null);
ok($st === 401, "subida sin Authorization → 401 ($st)");
[$st] = sube(['foto' => $foto, 'plantilla' => $plantilla], rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='));
ok($st === 401, "subida con llave mala → 401 ($st)");
ok(!is_file($tmp . '/agents/plantilla.html'), 'tras las subidas rechazadas no hay plantilla en disco');

// 3. Subida con la llave buena: las validaciones del cuerpo
[$st] = sube(['_crudo' => '{esto no es json'], $llave);
ok($st === 400, "cuerpo que no es JSON → 400 ($st)");
[$st] = sube(['foto' => $foto, 'plantilla' => '<html><script>var a=1;</script></html>'], $llave);
ok($st === 422, "plantilla sin hueco → 422 ($st)");
[$st] = sube(['foto' => $foto, 'plantilla' => '<html><script>/*AXW_FOTO*//*AXW_FOTO*/</script></html>'], $llave);
ok($st === 422, "plantilla con dos huecos → 422 ($st)");
[$st] = sube(['foto' => $foto, 'plantilla' => '<html><body>/*AXW_FOTO*/</body></html>'], $llave);
ok($st === 422, "hueco fuera de un <script> → 422 ($st)");
[$st] = sube(['foto' => $foto, 'plantilla' => '<html><script src="/x.js">/*AXW_FOTO*/</script></html>'], $llave);
ok($st === 422, "hueco dentro de un <script src> → 422 ($st)");
[$st] = sube(['foto' => [1, 2], 'plantilla' => $plantilla], $llave);
ok($st === 422, "foto que es una lista y no un objeto → 422 ($st)");
[$st, , $b] = sube(['foto' => $foto + ['llave' => 'sk-ant-api03-' . str_repeat('Ab9_', 10)], 'plantilla' => $plantilla], $llave);
ok($st === 422 && strpos($b, 'Ab9_') === false, "foto con forma de llave de Anthropic → 422 sin repetir el valor ($st)");
[$st] = sube(['foto' => $foto, 'plantilla' => str_replace('</body>', 'sb_secret_' . str_repeat('x', 30) . '</body>', $plantilla)], $llave);
ok($st === 422, "plantilla con forma de llave secreta de Supabase → 422 ($st)");
[$st] = sube(['_crudo' => json_encode(['foto' => $foto, 'plantilla' => $plantilla . str_repeat(' ', 3 * 1024 * 1024)])], $llave);
ok($st === 413, "más de 3 MB → 413 ($st)");
ok(!is_file($tmp . '/agents/plantilla.html'), 'tras los rechazos sigue sin plantilla en disco');
[$st, , $b] = sube(['foto' => $foto, 'plantilla' => $plantilla], $llave);
ok($st === 200 && (json_decode($b, true)['ok'] ?? false) === true, "subida buena (con «sk-ant-…» de placeholder en la página) → 200 ($st $b)");
ok(is_file($tmp . '/agents/plantilla.html') && is_file($tmp . '/agents/foto.json'), 'plantilla y foto en DATA_DIR/agents');
ok(count(glob($tmp . '/agents/*.tmp') ?: []) === 0, 'sin temporales sueltos tras la subida');
ok(count(glob($web . '/*') ?: []) === 0, 'nada se escribe en la carpeta servida');
[$st, , $b] = pide('GET', '/agents/');
ok($st === 401 && strpos($b, $MARCA) === false, 'con foto ya subida, sin sesión sigue 401 y sin ningún dato');

// 4. Entrada: sin CSRF, contraseña mala, contraseña buena
[$st, $h, $b] = pide('GET', '/agents/');
[$ck0] = cookie($h);
$tok = csrf($b);
ok($ck0 !== '' && $tok !== '', 'el formulario abre sesión (cookie) y lleva su CSRF');
[, $linea] = cookie($h);
ok(stripos($linea, 'path=/agents') !== false && stripos($linea, 'httponly') !== false && stripos($linea, 'samesite=strict') !== false && stripos($linea, 'secure') !== false,
    "cookie propia Secure; HttpOnly; SameSite=Strict; Path=/agents ($linea)");
[$st] = pide('POST', '/agents/', ['cookie' => $ck0, 'form' => ['clave' => $clave]]);
ok($st === 403, "entrar sin CSRF → 403 ($st)");
[$st] = pide('POST', '/agents/', ['form' => ['clave' => $clave, 'csrf' => $tok]]);
ok($st === 403, "entrar con el CSRF de otra sesión (sin cookie) → 403 ($st)");
[$st, , $b] = pide('POST', '/agents/', ['cookie' => $ck0, 'form' => ['clave' => 'mala', 'csrf' => $tok]]);
ok($st === 401 && strpos($b, $MARCA) === false, "contraseña mala → 401 sin datos ($st)");
[$st, $h] = pide('POST', '/agents/', ['cookie' => $ck0, 'form' => ['clave' => $clave, 'csrf' => $tok]]);
[$ck1] = cookie($h);
ok($st === 303 && cab($h, 'Location') === '/agents/', "contraseña buena → 303 a /agents/ ($st)");
ok($ck1 !== '' && $ck1 !== $ck0, 'al entrar se regenera el id de sesión');
[$st] = pide('GET', '/agents/', ['cookie' => $ck0]);
ok($st === 401, "la cookie de antes de entrar ya no vale ($st)");

// 5. Con sesión: la plantilla con la foto dentro, escapada, y la CSP con nonce
[$st, $h, $b] = pide('GET', '/agents/', ['cookie' => $ck1]);
ok($st === 200 && strpos($b, 'window.AXW_FOTO = {') !== false && strpos($b, $MARCA) !== false && strpos($b, '/*AXW_FOTO*/') === false, "con sesión → 200 con la foto en el hueco ($st)");
$privadas($h, 'GET /agents/ con sesión');
$U = chr(92) . 'u00';   // la barra se construye: el editor convierte la secuencia escrita a mano en el carácter
ok(strpos($b, '</script><img') === false && strpos($b, $U . '3C/script' . $U . '3E' . $U . '3Cimg') !== false, 'la foto no puede cerrar el <script>: < y > van escapados como u003C y u003E');
ok(strpos($b, 'a$1b\\\\0c') !== false, 'un «$1» o una barra de la foto llegan tal cual (sin reemplazo por expresión regular)');
$csp = cab($h, 'Content-Security-Policy');
preg_match("/script-src 'nonce-([A-Za-z0-9_\\-]+)'/", $csp, $m);
$n = $m[1] ?? '';
ok($n !== '' && substr_count($b, 'nonce="' . $n . '"') === 2, 'CSP con nonce, puesto en los 2 <script> en línea');
ok(strpos($b, '<script src="https://cdn.example/x.js">') !== false && strpos($csp, 'cdn.example') === false, 'un <script src> no lleva nonce y la CSP no lo admite');
ok(strpos($csp, "connect-src 'none'") !== false && strpos($csp, "default-src 'none'") !== false && strpos($csp, 'unsafe-eval') === false, "CSP: connect-src 'none', default-src 'none', sin unsafe-eval");
// Salir es un enlace, no un formulario: la plantilla real trae una meta CSP con form-action 'none' (Edge, 16-oct); y lleva
// data-axw-fuera porque la plantilla anula el clic de cualquier enlace sin esa marca
ok(preg_match('#<a data-axw-fuera href="/agents/salir\?csrf=([a-f0-9]+)"#', $b, $ms) === 1 && strpos($b, 'action="/agents/salir"') === false, 'la página lleva el enlace Salir con el token y la marca data-axw-fuera');
$tok1 = $ms[1] ?? '';
[$st, $h2, $b2] = pide('GET', '/agents/', ['cookie' => $ck1]);
preg_match("/script-src 'nonce-([A-Za-z0-9_\\-]+)'/", cab($h2, 'Content-Security-Policy'), $m2);
ok(($m2[1] ?? '') !== '' && ($m2[1] ?? '') !== $n, 'el nonce cambia en cada respuesta');

// 6. Salir
[$st] = pide('GET', '/agents/salir', ['cookie' => $ck1]);
ok($st === 403, "salir sin token → 403 ($st)");
[$st] = pide('GET', '/agents/salir?csrf=' . str_repeat('0', 32), ['cookie' => $ck1]);
ok($st === 403, "salir con un token que no es el de la sesión → 403 ($st)");
[$st] = pide('GET', '/agents/', ['cookie' => $ck1]);
ok($st === 200, "tras los dos intentos sin token, la sesión sigue abierta ($st)");
[$st] = pide('DELETE', '/agents/salir', ['cookie' => $ck1]);
ok($st === 405, "salir con otro método → 405 ($st)");
[$st, $h] = pide('GET', '/agents/salir?csrf=' . $tok1, ['cookie' => $ck1]);
ok($st === 303 && cab($h, 'Location') === '/agents/', "salir con el token → 303 a /agents/ ($st)");
[$st, , $b] = pide('GET', '/agents/', ['cookie' => $ck1]);
ok($st === 401 && strpos($b, $MARCA) === false, "tras salir, la cookie ya no entra ($st)");

// 7. Cambiar la contraseña en el fichero de huellas tira las sesiones abiertas
[, $h, $b] = pide('GET', '/agents/');
[$ckA] = cookie($h);
[, $h] = pide('POST', '/agents/', ['cookie' => $ckA, 'form' => ['clave' => $clave, 'csrf' => csrf($b)]]);
[$ckB] = cookie($h);
[$st] = pide('GET', '/agents/', ['cookie' => $ckB]);
ok($st === 200, "sesión nueva dentro ($st)");
$clave2 = bin2hex(random_bytes(12));
$secretos(hash('sha256', $clave2), hash('sha256', $llave));
[$st] = pide('GET', '/agents/', ['cookie' => $ckB]);
ok($st === 401, "con otra contraseña en el fichero de huellas, la sesión abierta cae ($st)");
$clave = $clave2;

// 8. Freno: 5 fallos por IP en 15 min → 429, y entonces ni la buena entra (fallos hasta aquí: 1)
[, $h, $b] = pide('GET', '/agents/');
[$ckF] = cookie($h);
$tokF = csrf($b);
for ($i = 0; $i < 4; $i++) pide('POST', '/agents/', ['cookie' => $ckF, 'form' => ['clave' => 'mala' . $i, 'csrf' => $tokF]]);
[$st, $h] = pide('POST', '/agents/', ['cookie' => $ckF, 'form' => ['clave' => $clave, 'csrf' => $tokF]]);
ok($st === 429 && cab($h, 'Retry-After') !== '', "a los 5 fallos → 429 con Retry-After, también con la contraseña buena ($st)");

// 9. Sin huellas: cerrado (ni entra nadie ni se sube nada)
$secretos('', '');
[$st] = sube(['foto' => $foto, 'plantilla' => $plantilla], $llave);
ok($st === 401, "sin AGENTS_SUBIDA_SHA256 la llave buena no sube → 401 ($st)");
array_map('unlink', glob($tmp . '/agents/fallos/*') ?: []);
[, $h, $b] = pide('GET', '/agents/');
[$ckV] = cookie($h);
[$st] = pide('POST', '/agents/', ['cookie' => $ckV, 'form' => ['clave' => $clave, 'csrf' => csrf($b)]]);
ok($st === 401, "sin AGENTS_CLAVE_SHA256 nadie entra → 401 ($st)");

// 9b. UNA sola fuente (Seguridad, 17-oct): las huellas buenas en secrets.php no abren nada si el fichero de huellas va vacío
file_put_contents($tmp . '/secrets.php', '<?php return ' . var_export(['AGENTS_CLAVE_SHA256' => hash('sha256', $clave), 'AGENTS_SUBIDA_SHA256' => hash('sha256', $llave)], true) . ';');
$entra = function () use ($clave): int {
    global $tmp;
    array_map('unlink', glob($tmp . '/agents/fallos/*') ?: []);
    [, $h, $b] = pide('GET', '/agents/');
    [$ck] = cookie($h);
    [$st] = pide('POST', '/agents/', ['cookie' => $ck, 'form' => ['clave' => $clave, 'csrf' => csrf($b)]]);
    return $st;
};
[$stS] = sube(['foto' => $foto, 'plantilla' => $plantilla], $llave);
$stE = $entra();
ok($stS === 401 && $stE === 401, "con las huellas solo en secrets.php: cerrado, ni sube ($stS) ni entra ($stE)");
@unlink($tmp . '/secrets.php');
// 9c. Huellas sin su forma (un bcrypt donde va el sha256 de la contraseña, sha con mayúsculas o recortado), con el nombre
// viejo de la clave o el fichero ausente: cerrado
$rapido = password_hash($clave, PASSWORD_BCRYPT, ['cost' => 4]);
$secretos($rapido, strtoupper(hash('sha256', $llave)));
[$stS] = sube(['foto' => $foto, 'plantilla' => $plantilla], $llave);
$stE = $entra();
ok($stS === 401 && $stE === 401, "huellas sin su forma: cerrado, ni sube ($stS) ni entra ($stE)");
// un bcrypt BUENO de la contraseña buena, con la llave buena: la subida pasa pero nadie entra (el login solo casa sha256)
$secretos(password_hash($clave, PASSWORD_DEFAULT), hash('sha256', $llave));
$stE = $entra();
ok($stE === 401, "un bcrypt en AGENTS_CLAVE_SHA256 no abre: 401 ($stE)");
// la clave con su nombre de antes (AGENTS_CLAVE_HASH), aunque lleve el sha256 bueno: no cuenta
file_put_contents($huellasF, '<?php return ' . var_export(['AGENTS_CLAVE_HASH' => hash('sha256', $clave), 'AGENTS_SUBIDA_SHA256' => hash('sha256', $llave)], true) . ';');
$stE = $entra();
ok($stE === 401, "con el nombre viejo AGENTS_CLAVE_HASH nadie entra: 401 ($stE)");
$secretos(hash('sha256', $clave), substr(hash('sha256', $llave), 0, 63));
[$stS] = sube(['foto' => $foto, 'plantilla' => $plantilla], $llave);
ok($stS === 401, "sha256 recortado: no sube ($stS)");
@unlink($huellasF);
[$stS] = sube(['foto' => $foto, 'plantilla' => $plantilla], $llave);
$stE = $entra();
ok($stS === 401 && $stE === 401, "sin fichero de huellas: cerrado, ni sube ($stS) ni entra ($stE)");
// 9d. Y con las huellas buenas en su fichero, la buena entra (el mismo camino que la de verdad)
$secretos(hash('sha256', $clave), hash('sha256', $llave));
$stE = $entra();
ok($stE === 303 || $stE === 302 || $stE === 200, "con las huellas en su fichero la contraseña buena entra ($stE)");
array_map('unlink', glob($tmp . '/agents/fallos/*') ?: []);

// 10. Freno de la subida: 10 llaves malas por IP en una hora → 429
$secretos(hash('sha256', $clave), hash('sha256', $llave));
for ($i = 0; $i < 10; $i++) sube(['foto' => $foto, 'plantilla' => $plantilla], rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='));
[$st] = sube(['foto' => $foto, 'plantilla' => $plantilla], $llave);
ok($st === 429, "a las 10 llaves malas → 429, también con la buena ($st)");

// 11. El registro no guarda la contraseña ni la llave
$log = implode('', array_map('file_get_contents', glob($tmp . '/agents/log-*.jsonl') ?: []));
ok($log !== '' && strpos($log, $clave) === false && strpos($log, $llave) === false && strpos($log, '127.0.0.1') === false, 'el registro existe y no lleva contraseña, llave ni IP en claro');

proc_terminate($srv);
proc_close($srv);
$borra = function (string $d) use (&$borra) { foreach (glob($d . '/{,.}[!.,!..]*', GLOB_BRACE) ?: [] as $f) is_dir($f) ? $borra($f) : @unlink($f); @rmdir($d); };
$borra($tmp);
$borra($web);
echo $fallos ? "\n$fallos FALLOS\n" : "\nTODO OK\n";
exit($fallos ? 1 : 0);
