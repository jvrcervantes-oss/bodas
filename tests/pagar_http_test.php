<?php
// /api/pagar de punta a punta por HTTP (BOD-20): una web REGALADA guarda su propia casilla
// (check_condiciones_regalo, sin vendedor ni desistimiento) y con `pasarela = stripe` el pago da 503
// (los textos dicen que vende Lemon Squeezy). Uso: php tests/pagar_http_test.php  (sale con 1 si falla)

declare(strict_types=1);

$tmp = sys_get_temp_dir() . '/bodas_http_test_' . bin2hex(random_bytes(4));
$web = sys_get_temp_dir() . '/bodas_http_web_' . bin2hex(random_bytes(4));   // docroot vacío fuera de DATA_DIR
mkdir($tmp, 0700, true);
mkdir($web, 0700, true);
$secretos = function (array $extra) use ($tmp) {
    file_put_contents($tmp . '/secrets.php', '<?php return ' . var_export($extra + [
        'empresa' => ['titular' => 'Titular de Prueba', 'nif' => '00000000T', 'domicilio' => 'Calle de Prueba 1, Madrid'],
    ], true) . ';');
};
$fallos = 0;
function ok(bool $c, string $q): void { global $fallos; if (!$c) { $fallos++; echo "FALLA: $q\n"; } else echo "ok: $q\n"; }

// Código de regalo creado como lo crea el panel del estudio
define('SIN_CONFIG_LOCAL', true);
define('DATA_DIR', $tmp);
define('BASE_DOMAIN', 'bodaenlace.com');
define('CREATOR_HOST', 'bodaenlace.com');
define('CREATOR_BASE', '');
$raiz = dirname(__DIR__);
foreach (['core', 'schema', 'render', 'foto', 'alta', 'mapa', 'cortesia'] as $m) require_once $raiz . '/app/' . $m . '.php';
$secretos(['pasarela' => 'lemon']);
[$idCod, $codigo] = cortesia_crea(1, false, date('Y-m-d', strtotime('+30 days')), 'prueba');
$L = textos_legales();

$puerto = 18000 + random_int(0, 999);
$env = array_merge(getenv(), ['BODAS_TEST_DATA' => $tmp]);
$srv = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $puerto, '-t', $web, __DIR__ . '/router_prueba.php'], [1 => ['file', $tmp . '/srv.log', 'a'], 2 => ['file', $tmp . '/srv.log', 'a']], $pipes, null, $env);
for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $puerto); $i++) usleep(100000);

function pagar(int $puerto, array $campos): array {
    $ch = curl_init('http://127.0.0.1:' . $puerto . '/api/pagar');
    curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $campos, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Host: bodaenlace.com']]);
    $r = (string) curl_exec($ch);
    $st = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return [$st, json_decode($r, true) ?? ['crudo' => substr($r, 0, 300)]];
}
$c = config_inicial();
$c['pareja']['nombre1'] = 'Ana'; $c['pareja']['nombre2'] = 'Luis'; $c['pareja']['email'] = 'pareja@example.com';
$c['fecha'] = date('Y-m-d', strtotime('+200 days'));
$c['ceremonia']['lugar'] = 'Ayuntamiento'; $c['ceremonia']['hora'] = '12:00';
$base = ['config' => json_encode($c), 'acepto_condiciones' => 'si'];

// 0. En pruebas (lemon_test por defecto) el titular no se publica, pero los regalos SÍ se canjean (owner, 29-sep)
//    y las páginas legales salen enteras sin NIF ni domicilio. El canje de verdad se prueba en el punto 1.
[$st, $j] = pagar($puerto, $base + ['slug' => 'regalo-en-pruebas', 'codigo' => 'AAAAA-BBBBB-CCCCC']);
ok($st === 422 && empty($j['ok']), 'en pruebas: un código falso se rechaza como no válido, no como cerrado (' . $st . ')');
function pagina(int $puerto, string $ruta): string {
    $ch = curl_init('http://127.0.0.1:' . $puerto . $ruta);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 30, CURLOPT_HTTPHEADER => ['Host: bodaenlace.com']]);
    $r = (string) curl_exec($ch);
    curl_close($ch);
    return $r;
}
foreach (['/aviso-legal', '/condiciones', '/privacidad'] as $r) {
    $h = pagina($puerto, $r);
    ok(strpos($h, '00000000T') === false && strpos($h, 'Calle de Prueba') === false && strpos($h, 'Titular de Prueba') === false, "en pruebas: $r sin titular, NIF ni domicilio");
    ok(strpos($h, 'todavía no está a la venta. Publicaremos') === false && substr_count($h, '<h2>') >= 3, "en pruebas: $r con el texto entero");
}

// Cobro real: los datos vuelven solos y los regalos se abren
$secretos(['pasarela' => 'lemon', 'lemon_test' => false]);
foreach (['/aviso-legal', '/condiciones', '/privacidad'] as $r) {
    $h = pagina($puerto, $r);
    ok(strpos($h, '00000000T') !== false && strpos($h, 'Calle de Prueba 1') !== false, "cobro real: $r con NIF y domicilio");
}

// 1. Regalo: se publica y guarda la casilla de regalo, sin vendedor ni desistimiento
[$st, $j] = pagar($puerto, $base + ['slug' => 'regalo-prueba', 'codigo' => $codigo]);
ok($st === 200 && !empty($j['ok']), 'regalo: publicada (' . $st . ' ' . json_encode($j) . ')');
$ped = null;
foreach (glob($tmp . '/pedidos/cortesia_*.json') ?: [] as $f) $ped = json_decode((string) file_get_contents($f), true);
$ac = (array) ($ped['aceptacion'] ?? []);
ok(($ac['condiciones'] ?? '') === ($L['check_condiciones_regalo'] ?? '-') && ($L['check_condiciones_regalo'] ?? '') !== '', 'regalo: guarda check_condiciones_regalo literal');
ok(($ac['condiciones'] ?? '') !== ($L['check_condiciones'] ?? ''), 'regalo: no guarda la casilla de compra');
ok(($ac['vendedor'] ?? 'x') === '' && ($ac['desistimiento'] ?? 'x') === '', 'regalo: sin vendedor ni desistimiento');

// 2. Stripe: con clave configurada y todo en regla, el pago NO se abre (503)
$secretos(['pasarela' => 'stripe', 'stripe_secret' => 'sk_test_x', 'stripe_tax_rate' => 'txr_x']);
[$st, $j] = pagar($puerto, $base + ['slug' => 'stripe-prueba', 'acepto_desistimiento' => 'si']);
ok($st === 503 && empty($j['ok']), 'stripe: el pago da 503 (' . $st . ')');
ok(!glob($tmp . '/pendientes/*'), 'stripe: no deja pedido pendiente');

proc_terminate($srv);
proc_close($srv);
foreach ([$tmp, $web] as $d) {
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($d, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    @rmdir($d);
}
echo ($fallos ? 'ROJO' : 'VERDE') . "\n";
exit($fallos ? 1 : 0);
