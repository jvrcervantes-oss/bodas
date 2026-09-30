<?php
// Reserva del nombre al volver atrás desde el checkout (arreglo #4, 30-sep-2026): quien reintenta la
// misma reserva recupera su nombre; otra persona sigue recibiendo el 409; un pedido cobrado nunca se pisa.
// Sin red. Uso: php tests/reserva_test.php  (sale con 1 si algo falla)

declare(strict_types=1);

$tmp = sys_get_temp_dir() . '/bodas_reserva_test_' . bin2hex(random_bytes(4));
mkdir($tmp, 0700, true);
define('SIN_CONFIG_LOCAL', true);
define('DATA_DIR', $tmp);
define('BASE_DOMAIN', 'bodaenlace.com');
define('CREATOR_HOST', 'bodaenlace.com');
define('CREATOR_BASE', '');
define('CORREO_A_FICHERO', true);
file_put_contents($tmp . '/secrets.php', '<?php return ' . var_export(['pasarela' => 'lemon'], true) . ';');
$raiz = dirname(__DIR__);
foreach (['core', 'schema', 'render', 'foto', 'alta', 'mapa'] as $m) require_once $raiz . '/app/' . $m . '.php';

$fallos = 0;
$n = 0;
function ok(bool $c, string $que): void { global $fallos, $n; $n++; if (!$c) { $fallos++; echo "FALLA: $que\n"; } }

/** Como api_pagar: token nuevo, reserva y pendiente escrito. */
function intenta(string $slug, string $ip, string $email): array {
    $tok = bin2hex(random_bytes(16));
    $res = reserva_toma($slug, $tok, reservante_id($ip, $email));
    if ($res) escribe_json(dir_datos('pendientes', $tok, 'meta.json'), ['slug' => $slug, 'creado' => time(), 'precio_cent' => 12500, 'pasarela' => 'lemon']);
    return [$res, $tok];
}
function reservas_de(string $slug): array { return lee_json(dir_datos('reservas', $slug . '.json')) ?? []; }
function pendientes_n(): int { return count(glob(dir_datos('pendientes', '*'), GLOB_ONLYDIR) ?: []); }

// Identificador: no reversible (sin IP ni email en claro), estable, y distinto entre personas
$a = reservante_id('1.2.3.4', 'Ana@Example.com ');
ok($a === reservante_id('1.2.3.4', 'ana@example.com'), 'el email se normaliza (mayúsculas y espacios)');
ok($a !== reservante_id('1.2.3.5', 'ana@example.com') && $a !== reservante_id('1.2.3.4', 'otra@example.com'), 'otra IP u otro email = otro reservante');
ok(strpos($a, '1.2.3.4') === false && strpos($a, 'example') === false && preg_match('/^[a-f0-9]{64}$/', $a) === 1, 'el identificador no lleva IP ni email en claro');
ok(reservante_id('1.2.3.4', '') === '', 'sin email no hay identificador');

// Misma persona reintenta: OK, solo queda una reserva y un pendiente
[$r1, $t1] = intenta('ana-y-luis', '1.2.3.4', 'ana@example.com');
ok($r1 && true && reservas_de('ana-y-luis')['token'] === $t1, 'primera reserva');
$fr = file_get_contents(dir_datos('reservas', 'ana-y-luis.json'));
ok(strpos($fr, '1.2.3.4') === false && strpos($fr, 'ana@example.com') === false, 'la reserva no guarda IP ni email en claro');
[$r2, $t2] = intenta('ana-y-luis', '1.2.3.4', 'ANA@example.com');
ok($r2 && $t2 !== $t1, 'la misma persona vuelve a reservar su nombre');
ok(reservas_de('ana-y-luis')['token'] === $t2 && is_dir(dir_datos('pendientes', $t2)), 'solo queda una reserva (la del pedido nuevo)');
ok(is_dir(dir_datos('pendientes', $t1)), 'el pendiente anterior NO se borra: si su checkout se paga tarde, el aviso de LS aún encuentra sus datos');

// Otra persona (otro email, o misma conexión con otro email, o mismo email desde otra IP) → 409
[$r3, ] = intenta('ana-y-luis', '9.9.9.9', 'intrusa@example.com');
ok(!$r3 && reservas_de('ana-y-luis')['token'] === $t2 && is_dir(dir_datos('pendientes', $t2)), 'otra persona: sigue ocupado y no se toca lo de la primera');
[$r4, ] = intenta('ana-y-luis', '1.2.3.4', 'otro@example.com');
ok(!$r4, 'misma conexión con otro email: ocupado');
[$r5, ] = intenta('ana-y-luis', '9.9.9.9', 'ana@example.com');
ok(!$r5, 'mismo email desde otra conexión: ocupado');

// Un pedido ya cobrado (token atado a un pedido de LS) nunca se pisa
escribe_json(dir_datos('ls_tokens', $t2 . '.json'), ['order_id' => '77', 'creado' => date('c')]);
[$r6, ] = intenta('ana-y-luis', '1.2.3.4', 'ana@example.com');
ok(!$r6 && reservas_de('ana-y-luis')['token'] === $t2 && is_dir(dir_datos('pendientes', $t2)), 'pedido con cobro recibido: no se pisa');

// La web ya creada nunca se pisa, ni por su dueño
[$r7, $t7] = intenta('boda-hecha', '1.2.3.4', 'ana@example.com');
escribe_json(dir_boda('boda-hecha') . '/config.json', ['x' => 1]);
[$r8, ] = intenta('boda-hecha', '1.2.3.4', 'ana@example.com');
ok($r7 && !$r8, 'web ya publicada: no se puede volver a reservar');

// Reserva caducada: la coge cualquiera (comportamiento de siempre); reserva antigua sin reservante: 409 hasta caducar
escribe_json(dir_datos('reservas', 'vieja.json'), ['token' => str_repeat('b', 32), 'hasta' => time() - 5]);
[$r9, ] = intenta('vieja', '9.9.9.9', 'x@example.com');
escribe_json(dir_datos('reservas', 'sin-dueno.json'), ['token' => str_repeat('c', 32), 'hasta' => time() + 600]);
[$r10, ] = intenta('sin-dueno', '1.2.3.4', 'ana@example.com');
ok($r9 && !$r10, 'reserva caducada se coge; una reserva sin reservante conocido no se pisa');

echo $fallos ? "ROJO: " . ($n - $fallos) . "/$n\n" : "VERDE: $n/$n\n";
exit($fallos ? 1 : 0);
