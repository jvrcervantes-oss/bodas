<?php
// faltan(): fecha pasada al editar desde el panel (arreglo #5) y datos vaciados en silencio (arreglo #6), 30-sep-2026.
// Sin red. Uso: php tests/faltan_test.php  (sale con 1 si algo falla)

declare(strict_types=1);

$tmp = sys_get_temp_dir() . '/bodas_faltan_test_' . bin2hex(random_bytes(4));
mkdir($tmp, 0700, true);
define('SIN_CONFIG_LOCAL', true);
define('DATA_DIR', $tmp);
define('BASE_DOMAIN', 'bodaenlace.com');
define('CREATOR_HOST', 'bodaenlace.com');
define('CREATOR_BASE', '');
file_put_contents($tmp . '/secrets.php', '<?php return ' . var_export(['pasarela' => 'lemon'], true) . ';');
$raiz = dirname(__DIR__);
foreach (['core', 'schema'] as $m) require_once $raiz . '/app/' . $m . '.php';

$fallos = 0;
$n = 0;
function ok(bool $c, string $que): void { global $fallos, $n; $n++; if (!$c) { $fallos++; echo "FALLA: $que\n"; } }
function dia(int $d): string { return date('Y-m-d', strtotime(($d >= 0 ? '+' : '') . $d . ' days')); }

/** Config completa (sin faltas) como la manda el navegador: el array CRUDO, antes de normalizar. */
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
function f(array $crudo, ?array $panel = null): array { return faltan(normaliza_config($crudo), $crudo, $panel); }

ok(f(crudo()) === [], 'base: una config completa no falta nada');

// ---- #5 Fecha al editar desde el panel
$pasada = dia(-10);
$c = crudo(['fecha' => $pasada]);
ok(isset(f($c)['fecha']), 'crear: una fecha pasada sigue siendo error');
ok(f($c, ['guardada' => $pasada, 'pago' => dia(-100)]) === [], 'panel: guardar con la fecha pasada SIN tocarla → sin error');
ok(isset(f(crudo(['fecha' => dia(-20)]), ['guardada' => $pasada, 'pago' => dia(-100)])['fecha']), 'panel: cambiar la fecha a otra pasada → error');
ok(strpos(f(crudo(['fecha' => dia(-20)]), ['guardada' => $pasada, 'pago' => dia(-100)])['fecha'], 'ya ha pasado') !== false, 'panel: el mensaje dice que ya ha pasado');
// Mover hacia delante: tope de 60 días respecto de la fecha al contratar
$pago = dia(30);
ok(f(crudo(['fecha' => dia(30 + 60)]), ['guardada' => $pago, 'pago' => $pago]) === [], 'panel: mover exactamente 60 días → OK');
ok(isset(f(crudo(['fecha' => dia(30 + 61)]), ['guardada' => $pago, 'pago' => $pago])['fecha']), 'panel: mover 61 días → error');
ok(isset(f(crudo(['fecha' => dia(30 + 200)]), ['guardada' => $pago, 'pago' => $pago])['fecha']) && strpos(f(crudo(['fecha' => dia(230)]), ['guardada' => $pago, 'pago' => $pago])['fecha'], '60') !== false, 'panel: mover >60 días → error que lo explica');
ok(f(crudo(['fecha' => dia(10)]), ['guardada' => $pago, 'pago' => $pago]) === [], 'panel: adelantar la fecha no se limita');
// El tope cuenta desde la fecha al contratar, no desde la última guardada (no vale ir de 60 en 60)
ok(isset(f(crudo(['fecha' => dia(30 + 100)]), ['guardada' => dia(30 + 60), 'pago' => $pago])['fecha']), 'panel: encadenar saltos de 60 días no burla el tope (ancla = fecha al contratar)');
ok(f(crudo(['fecha' => dia(100)]), ['guardada' => dia(50), 'pago' => '']) === [], 'panel: bodas antiguas sin fecha_pago usan la guardada como ancla (50 → 100 = 50 días)');
ok(isset(f(crudo(['fecha' => dia(200)]), ['guardada' => dia(50), 'pago' => ''])['fecha']), 'panel: sin fecha_pago, 150 días desde la guardada → error');
// Crear no cambia
ok(f(crudo(['fecha' => dia(100)])) === [], 'crear: fecha futura → OK');
ok(isset(f(crudo(['fecha' => dia(365 * 4)]))['fecha']), 'crear: a más de 3 años → error');
ok(isset(f(crudo(['fecha' => '']))['fecha']), 'sin fecha → error');

// ---- #6 Datos vaciados en silencio
$mal = f(conSeccion(crudo(), 'regalos', ['iban' => 'ES00 1234 5678 9012 3456 7890']));
$k = array_keys(array_filter($mal, fn($m, $kk) => strpos($kk, '.iban') !== false, ARRAY_FILTER_USE_BOTH));
ok(count($k) === 1 && strpos($mal[$k[0]], 'IBAN') !== false && strpos($mal[$k[0]], 'Lista de bodas') !== false, 'IBAN inválido → error con el nombre de la sección');
ok(f(conSeccion(crudo(), 'regalos', ['iban' => 'ES91 2100 0418 4502 0005 1332']))  === [], 'IBAN válido con espacios → OK');
ok(f(conSeccion(crudo(), 'regalos', ['iban' => 'es9121000418450200051332'])) === [], 'IBAN válido en minúsculas y sin espacios → OK');
ok(f(conSeccion(crudo(), 'regalos', ['iban' => ''])) === [] && f(conSeccion(crudo(), 'regalos', ['iban' => '   '])) === [], 'IBAN vacío (o en blanco) → sin error');
ok(f(conSeccion(crudo(), 'regalos', ['iban' => 'ES00 1234'], false)) === [], 'IBAN malo en una sección apagada no bloquea');
$hot = fn(string $web, string $nombre = 'Hotel Sol') => conSeccion(crudo(), 'hoteles', ['hoteles' => [['nombre' => $nombre, 'zona' => '', 'web' => $web, 'telefono' => '', 'nota' => '']]]);
$mal = f($hot('esto no es una web'));
ok(count($mal) === 1 && strpos(array_values($mal)[0], 'Hotel Sol') !== false && strpos(array_values($mal)[0], 'Hoteles') !== false, 'URL de hotel inválida → error con hotel y sección');
ok(f($hot('')) === [], 'URL de hotel vacía → sin error');
ok(f($hot('hotelsol.com')) === [] && f($hot('https://www.hotelsol.com/reservas')) === [], 'URL válida (con o sin https) → OK');
ok(f($hot('javascript:alert(1)')) !== [], 'esquema raro (javascript:) → error, no se descarta en silencio');
// Sin el crudo (llamada antigua) faltan() sigue funcionando y no inventa errores
ok(faltan(normaliza_config(conSeccion(crudo(), 'regalos', ['iban' => 'ES00']))) === [], 'sin config crudo no hay comparación (compatibilidad)');
// Se acumula con las otras faltas y no pisa las suyas
$mix = f(conSeccion(crudo(['ceremonia' => ['lugar' => '']]), 'regalos', ['iban' => 'ES00 1234']));
ok(isset($mix['ceremonia.lugar']) && count($mix) === 2, 'convive con otras faltas');

echo $fallos ? "ROJO: " . ($n - $fallos) . "/$n\n" : "VERDE: $n/$n\n";
exit($fallos ? 1 : 0);
