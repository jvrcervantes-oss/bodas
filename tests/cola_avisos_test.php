<?php
// Un aviso que no sale (correo o Telegram) no rompe el alta: queda en DATA_DIR/cola_avisos y el cron
// lo reintenta (owner, 27-sep-2026). Uso: php tests/cola_avisos_test.php  (sale con 1 si algo falla)
// Fase 1 (sin argumento): todo envío falla → la web se crea igual y los avisos quedan en cola.
// Fase 2 (proceso hijo con «envia»): el envío vuelve a funcionar → el cron vacía la cola.

declare(strict_types=1);

$fase = $argv[1] ?? 'falla';
$tmp = $argv[2] ?? sys_get_temp_dir() . '/bodas_cola_test_' . bin2hex(random_bytes(4));
if ($fase === 'falla') define('CORREO_FALLA', true);
define('SIN_CONFIG_LOCAL', true);
define('DATA_DIR', $tmp);
define('BASE_DOMAIN', 'bodaenlace.com');
define('CREATOR_HOST', 'bodaenlace.com');
define('CREATOR_BASE', '');
define('CORREO_A_FICHERO', true);
define('LEMON_API', 'http://127.0.0.1:9');
if ($fase === 'falla') {
    mkdir($tmp, 0700, true);
    file_put_contents($tmp . '/secrets.php', '<?php return ' . var_export([
        'pasarela' => 'lemon', 'lemon_api_key' => 'k', 'lemon_webhook_secret' => 's', 'lemon_tienda' => '483461', 'lemon_variante' => '2169949',
        'lemon_producto' => '1389266', 'lemon_test' => true, 'telegram_token' => 'x', 'telegram_chat' => '1'], true) . ';');
}
$raiz = dirname(__DIR__);
foreach (['core', 'schema', 'render', 'foto', 'alta', 'mapa', 'cortesia', 'estudio', 'borrador', 'invitados', 'creador', 'landing', 'vista_constructor', 'boda', 'galeria', 'proxy'] as $m) {
    require_once $raiz . '/app/' . $m . '.php';
}
$fallos = 0;
function ok(bool $c, string $q): void { global $fallos; if (!$c) { $fallos++; echo "FALLA: $q\n"; } else echo "ok: $q\n"; }
$cola = fn() => count(glob(dir_datos('cola_avisos', '*.json')) ?: []);

if ($fase === 'falla') {
    $tok = bin2hex(random_bytes(16));
    $c = config_inicial();
    $c['pareja'] = ['nombre1' => 'Ana', 'nombre2' => 'Luis', 'email' => 'pareja@example.com'] + $c['pareja'];
    $c['fecha'] = date('Y-m-d', strtotime('+200 days'));
    $p = precio_esencial_cent();
    escribe_json(dir_datos('pendientes', $tok, 'config.json'), $c);
    escribe_json(dir_datos('pendientes', $tok, 'meta.json'), ['slug' => 'cola', 'creado' => time(), 'precio_cent' => $p, 'pasarela' => 'lemon', 'aceptacion' => null]);
    $o = ['store_id' => 483461, 'currency' => 'EUR', 'total' => $p, 'tax' => 0, 'discount_total' => 0, 'status' => 'paid', 'test_mode' => true,
        'user_email' => 'pareja@example.com', 'order_number' => 1, 'first_order_item' => ['variant_id' => 2169949, 'product_id' => 1389266, 'quantity' => 1]];
    $ev = ['meta' => ['event_name' => 'order_created', 'custom_data' => ['token' => $tok, 'slug' => 'cola', 'producto' => 'bodas']], 'data' => ['type' => 'orders', 'id' => '1']];
    [$st, $r] = lemon_procesa_evento($ev, fn($id) => $o);
    ok($st === 200 && $r === 'creada' && boda_existe('cola'), 'con el correo y Telegram caídos, la web se crea igual');
    ok($cola() === 3, 'bienvenida, aviso al owner y Telegram quedan en la cola (' . $cola() . ')');
    $n = cola_avisos_reintenta();
    ok($n['pendientes'] === 3 && $cola() === 3, 'si sigue fallando, siguen en la cola con un intento más');
    // Fase 2 en otro proceso: sin CORREO_FALLA
    passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' envia ' . escapeshellarg($tmp), $rc);
    if ($rc !== 0) $fallos++;
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) {
        $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
    }
    @rmdir($tmp);
    echo ($fallos ? 'ROJO' : 'VERDE') . "\n";
    exit($fallos ? 1 : 0);
}
$n = cola_avisos_reintenta();
ok($n['enviados'] === 3 && $cola() === 0, 'con el envío de vuelta, el cron vacía la cola');
ok(count(glob(dir_datos('telegram', '*')) ?: []) === 1, 'el Telegram sale una vez');
exit($fallos ? 1 : 0);
