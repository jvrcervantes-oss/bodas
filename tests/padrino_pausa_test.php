<?php
// Marca de pausa propia de Bodas (8-oct-2026, contrato_a2 §7/§7b, decisión del owner):
//  · con la marca puesta, TODAS las rutas que escriben (precios, marca, campanas, contenido) responden 423; las de lectura no;
//  · `POST pausa` con su token propio pone la marca (idempotente) y NINGÚN token puede quitarla;
//  · reanudar solo en el panel del estudio (sesión + CSRF);
//  · `contenido/retirar`, `contenido/restaurar` y `precios` con `extra` responden 404 (sin llamador).
// Por HTTP real contra php -S, sin red. Uso: php tests/padrino_pausa_test.php  (sale con 1 si falla)

declare(strict_types=1);

$tmp = sys_get_temp_dir() . '/bodas_pausa_test_' . bin2hex(random_bytes(4));
$web = sys_get_temp_dir() . '/bodas_pausa_web_' . bin2hex(random_bytes(4));
mkdir($tmp, 0700, true);
mkdir($web, 0700, true);
file_put_contents($tmp . '/secrets.php', '<?php return ' . var_export(['pasarela' => 'lemon', 'lemon_test' => true], true) . ';');
$fallos = 0;
$n = 0;
function ok(bool $c, string $q): void { global $fallos, $n; $n++; if (!$c) { $fallos++; echo "FALLA: $q\n"; } }

$tipos = ['lectura', 'decision', 'contenido', 'tesoreria', 'pausa'];
$tok = [];
$hashes = [];
foreach ($tipos as $t) { $tok[$t] = rtrim(strtr(base64_encode(random_bytes(36)), '+/', '-_'), '='); $hashes[$t] = hash('sha256', $tok[$t]); }
$claveEstudio = 'clave-de-prueba-del-estudio';
mkdir($tmp . '/estudio', 0700, true);
file_put_contents($tmp . '/estudio/clave.json', json_encode(['hash' => password_hash($claveEstudio, PASSWORD_BCRYPT), 'gen' => 0]));

$puerto = 19000 + random_int(0, 999);
$env = array_merge(getenv(), ['BODAS_TEST_DATA' => $tmp, 'BODAS_TEST_HASHES' => json_encode($hashes)]);
$srv = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $puerto, '-t', $web, __DIR__ . '/router_padrino_pausa.php'],
    [1 => ['file', $tmp . '/srv.log', 'a'], 2 => ['file', $tmp . '/srv.log', 'a']], $pipes, null, $env);
for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $puerto); $i++) usleep(100000);

/** [status, cuerpo decodificado o texto]. $token = el token en claro o null. */
function api(int $puerto, string $metodo, string $ruta, ?string $token, $cuerpo = null): array {
    $ch = curl_init('http://127.0.0.1:' . $puerto . '/api/padrino/' . $ruta);
    $cab = ['Host: bodaenlace.com', 'Content-Type: application/json'];
    if ($token !== null) $cab[] = 'Authorization: Bearer ' . $token;
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $metodo, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => $cab]);
    if ($cuerpo !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($cuerpo));
    $r = (string) curl_exec($ch);
    $st = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$st, json_decode($r, true) ?? $r];
}
$marca = $tmp . '/padrino/pausa.json';
/** ¿Existe la marca AHORA? (el servidor la crea y la borra en otro proceso: sin esto, PHP responde con la caché de stat) */
function marcada(): bool { global $marca; clearstatcache(true, $marca); return is_file($marca); }
$precios = ['esencial_cent' => 12500, 'atelier_cent' => 24900, 'motivo' => 'prueba'];
$guia = ['slug' => 'guia-de-prueba', 'titulo' => 'Cómo organizar la confirmación de invitados', 'descripcion' => 'x', 'cuerpo' => 'x'];
// Las rutas que escriben, cada una con SU token y un cuerpo que sin pausa no sería 423
$escrituras = [
    'precios' => ['decision', 'precios', $precios],
    'marca' => ['decision', 'marca', ['nombre' => 'Vuestro Día', 'motivo' => 'prueba']],
    'campanas' => ['decision', 'campanas', ['id' => 'prueba-1']],
    'contenido' => ['contenido', 'contenido', $guia],
    'precios con extra' => ['decision', 'precios', ['extra' => 'album', 'cent' => 2250, 'motivo' => 'prueba']],
];

// ── 1. Sin marca: las escrituras NO dan 423 (llegan a su lógica); retirar/restaurar/extra dan 404
ok(!marcada(), 'arranca sin marca');
foreach ($escrituras as $nombre => [$t, $ruta, $cuerpo]) {
    if ($nombre === 'precios con extra') continue;   // 404, abajo
    [$st] = api($puerto, 'POST', $ruta, $tok[$t], $cuerpo);
    ok($st !== 423 && $st !== 401, "sin marca: $nombre no da 423 ni 401 (dio $st)");
}
[$st] = api($puerto, 'POST', 'precios', $tok['decision'], ['extra' => 'album', 'cent' => 2250, 'motivo' => 'x']);
ok($st === 404, "precios con extra: 404 (dio $st)");
foreach (['contenido/retirar', 'contenido/restaurar'] as $r) {
    [$st] = api($puerto, 'POST', $r, $tok['contenido'], ['slug' => 'guia-de-prueba']);
    ok($st === 404, "$r con su token: 404 (dio $st)");
    [$st] = api($puerto, 'POST', $r, null, ['slug' => 'x']);
    ok($st === 404, "$r sin token: 404, no 401 (dio $st)");
}
array_map('unlink', glob($tmp . '/padrino/{precios,marca,campanas}.json', GLOB_BRACE) ?: []);   // lo que sin marca sí se escribió

// ── 2. La ruta pausa: solo su token, solo POST, idempotente
[$st] = api($puerto, 'POST', 'pausa', null, []);
ok($st === 401, "pausa sin token: 401 ($st)");
foreach (['lectura', 'decision', 'contenido', 'tesoreria'] as $t) {
    [$st] = api($puerto, 'POST', 'pausa', $tok[$t], []);
    ok($st === 401, "pausa con el token de $t: 401 ($st)");
}
ok(!marcada(), 'los 401 no ponen marca');
[$st] = api($puerto, 'GET', 'pausa', $tok['pausa']);
ok($st === 405 && !marcada(), "GET pausa: 405 y sin marca ($st)");
[$st, $j] = api($puerto, 'POST', 'pausa', $tok['pausa'], ['motivo' => 'prueba de pausa']);
ok($st === 200 && ($j['pausado'] ?? null) === true && ($j['nueva'] ?? null) === true && marcada(), 'pausa pone la marca');
$desde = (string) (json_decode((string) file_get_contents($marca), true)['desde'] ?? '');
usleep(1100000);
[$st, $j] = api($puerto, 'POST', 'pausa', $tok['pausa'], []);
ok($st === 200 && ($j['nueva'] ?? null) === false, 'segunda pausa: 200, idempotente (nueva=false)');
ok((string) (json_decode((string) file_get_contents($marca), true)['desde'] ?? '') === $desde, 'la segunda pausa no reescribe quién ni cuándo');
ok((json_decode((string) file_get_contents($marca), true)['quien'] ?? '') === 'padrino', 'la marca dice que la puso el Padrino');
// El token de pausa no sirve para nada más
foreach (['precios', 'marca', 'campanas', 'contenido', 'resumen', 'pedidos'] as $r) {
    $lee = $r === 'resumen' || $r === 'pedidos';
    [$st] = api($puerto, $lee ? 'GET' : 'POST', $r, $tok['pausa'], $lee ? null : []);
    ok($st === 401, "el token de pausa en $r: 401 ($st)");
}

// ── 3. Con marca: TODAS las escrituras dan 423 con su token válido; las lecturas siguen
foreach ($escrituras as $nombre => [$t, $ruta, $cuerpo]) {
    [$st, $j] = api($puerto, 'POST', $ruta, $tok[$t], $cuerpo);
    ok($st === 423 && ($j['error'] ?? '') === 'pausado', "pausado: $nombre da 423 ($st)");
}
[$st] = api($puerto, 'POST', 'precios', null, $precios);
ok($st === 401, "pausado y sin token: 401, no 423 (no se cuenta el estado a un extraño) ($st)");
[$st, $j] = api($puerto, 'GET', 'resumen', $tok['lectura']);
ok($st === 200 && ($j['ok'] ?? false) === true, "pausado: resumen sigue ($st)");
[$st, $j] = api($puerto, 'POST', 'remitente', $tok['lectura'], ['email' => 'nadie@example.com']);
ok($st === 200 && ($j['tiene_boda'] ?? null) === false, "pausado: remitente sigue ($st)");
[$st] = api($puerto, 'GET', 'pedidos', $tok['tesoreria']);
ok($st !== 423 && $st !== 401, "pausado: pedidos sigue (llega a Lemon: dio $st)");
foreach (['contenido/retirar', 'contenido/restaurar'] as $r) {
    [$st] = api($puerto, 'POST', $r, $tok['contenido'], ['slug' => 'x']);
    ok($st === 404, "pausado: $r sigue en 404 ($st)");
}
ok(!is_file($tmp . '/padrino/precios.json') && !is_file($tmp . '/padrino/marca.json') && !is_file($tmp . '/padrino/campanas.json'), 'pausado: nada se escribió');

// ── 4. Ningún token (ni verbo) reanuda
foreach ($tipos as $t) {
    foreach ([['POST', 'pausa', ['pausado' => false]], ['DELETE', 'pausa', null], ['POST', 'reanudar', []], ['POST', 'pausa/reanudar', []], ['PUT', 'pausa', ['pausado' => false]]] as [$m, $r, $c]) {
        api($puerto, $m, $r, $tok[$t], $c);
        if (!marcada()) { ok(false, "$t con $m $r quitó la marca"); file_put_contents($marca, '{}'); }
    }
}
ok(marcada(), 'ningún token ni verbo quita la marca');

// ── 5. Panel del estudio: sin sesión no se reanuda; con sesión pero sin CSRF tampoco; con las dos, sí
$jar = $tmp . '/cookies.txt';
function panel(int $puerto, string $metodo, string $ruta, array $campos, string $jar): array {
    $ch = curl_init('http://127.0.0.1:' . $puerto . $ruta);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => ['Host: bodaenlace.com'],
        CURLOPT_COOKIEJAR => $jar, CURLOPT_COOKIEFILE => $jar]);
    if ($metodo === 'POST') { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($campos)); }
    $r = (string) curl_exec($ch);
    $st = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$st, $r];
}
function csrf_de(string $html): string { return preg_match('/name="csrf" value="([a-f0-9]+)"/', $html, $m) ? $m[1] : ''; }
[$st, $h] = panel($puerto, 'POST', '/estudio/padrino', ['accion' => 'reanudar', 'csrf' => 'x'], $jar);
ok(marcada() && strpos($h, 'Reanudar al Padrino') === false, 'sin sesión: reanudar no hace nada y no enseña el botón');
[, $h] = panel($puerto, 'GET', '/estudio', [], $jar);
$c = csrf_de($h);
panel($puerto, 'POST', '/estudio', ['csrf' => $c, 'clave' => $claveEstudio], $jar);
[$st, $h] = panel($puerto, 'GET', '/estudio/padrino', [], $jar);
ok($st === 200 && strpos($h, 'data-accion="padrino-reanudar"') !== false && strpos($h, 'data-estado="pausado"') !== false, "con sesión: el panel muestra Pausado y el botón por data-accion ($st)");
$c = csrf_de($h);
[$st] = panel($puerto, 'POST', '/estudio/padrino', ['accion' => 'reanudar'], $jar);
ok($st === 403 && marcada(), "con sesión y SIN csrf: 403 y sigue pausado ($st)");
[$st] = panel($puerto, 'POST', '/estudio/padrino', ['accion' => 'reanudar', 'csrf' => 'ffff'], $jar);
ok($st === 403 && marcada(), "con csrf falso: 403 y sigue pausado ($st)");
[$st, $h] = panel($puerto, 'POST', '/estudio/padrino', ['accion' => 'reanudar', 'csrf' => $c], $jar);
ok($st === 200 && !marcada() && strpos($h, 'data-accion="padrino-pausar"') !== false, "el owner reanuda con sesión y csrf ($st)");
[$st] = api($puerto, 'POST', 'campanas', $tok['decision'], ['id' => 'tras-reanudar']);
ok($st === 200, "reanudado: las escrituras vuelven ($st)");
$c = csrf_de($h);
[$st, $h] = panel($puerto, 'POST', '/estudio/padrino', ['accion' => 'pausar', 'csrf' => $c], $jar);
ok($st === 200 && marcada() && (json_decode((string) file_get_contents($marca), true)['quien'] ?? '') === 'estudio', 'el owner pausa desde el panel');
[$st] = api($puerto, 'POST', 'campanas', $tok['decision'], ['id' => 'otra']);
ok($st === 423, "pausado desde el panel: 423 ($st)");

// ── 6. Falla en cerrado: marca ilegible = pausado
file_put_contents($marca, '{basura');
[$st] = api($puerto, 'POST', 'marca', $tok['decision'], ['nombre' => 'Vuestro Día', 'motivo' => 'x']);
ok($st === 423, "marca ilegible: sigue pausado ($st)");

// ── 7. Sobre el fuente
$fuente = (string) file_get_contents(dirname(__DIR__) . '/app/padrino.php');
ok(strpos($fuente, "case 'contenido/retirar'") === false && strpos($fuente, "case 'contenido/restaurar'") === false, 'el switch ya no tiene retirar/restaurar');
ok(strpos($fuente, 'padrino_cambia_precio_extra') === false, 'el envoltorio HTTP de precio de extra está retirado');
ok(strpos((string) file_get_contents(dirname(__DIR__) . '/app/padrino_tokens.php'), "'pausa' =>") !== false, 'padrino_tokens.php tiene su entrada pausa (vacía hasta generarla)');

proc_terminate($srv);
foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
@rmdir($tmp); @rmdir($web);
echo ($fallos ? "ROJO: $fallos de $n fallan\n" : "VERDE: $n/$n\n");
exit($fallos ? 1 : 0);
