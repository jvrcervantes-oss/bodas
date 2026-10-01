<?php
// Identidad pública (owner, 1-oct-2026): lo que sale en las páginas legales y los correos es SOLO «PT Mahkota» + el email.
// Aunque secrets.php siga trayendo nombre de persona, NIF y domicilio, ninguno sale. Uso: php tests/identidad_publica_test.php
declare(strict_types=1);

// Sin argumento: corre los dos casos (secrets viejo con persona/NIF/domicilio, y secrets nuevo solo razón social + email).
if (!isset($argv[1])) {
    $rc = 0;
    foreach (['viejo', 'nuevo'] as $c) { passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' ' . $c, $r); $rc |= $r; }
    exit($rc);
}

$tmp = sys_get_temp_dir() . '/bodas_ident_test_' . bin2hex(random_bytes(4));
mkdir($tmp, 0700, true);
define('SIN_CONFIG_LOCAL', true);
define('DATA_DIR', $tmp);
define('BASE_DOMAIN', 'bodaenlace.com');
define('CREATOR_HOST', 'bodaenlace.com');
define('CREATOR_BASE', '');
define('CORREO_A_FICHERO', true);
const PERSONA = 'Persona Ficticia Apellido';
const NIF_F = 'X1234567Z';
const DOM_F = 'Calle Inventada 99, 28001 Madrid';
$empresas = [
    'viejo' => ['titular' => PERSONA, 'nif' => NIF_F, 'domicilio' => DOM_F, 'email' => 'hola@bodaenlace.com'],
    'nuevo' => ['razon_social' => 'PT Mahkota', 'email' => 'hola@bodaenlace.com'],
];
$caso = $argv[1] ?? 'viejo';
file_put_contents($tmp . '/secrets.php', '<?php return ' . var_export([
    'pasarela' => 'lemon', 'lemon_api_key' => 'x', 'lemon_webhook_secret' => 'y', 'lemon_tienda' => '1', 'lemon_variante' => '2',
    'lemon_producto' => '3', 'lemon_test' => false, 'empresa' => $empresas[$caso],
], true) . ';');
$raiz = dirname(__DIR__);
foreach (['core', 'schema', 'render', 'foto', 'alta', 'mapa', 'cortesia', 'estudio', 'borrador', 'invitados', 'creador', 'landing', 'vista_constructor', 'boda', 'galeria', 'proxy'] as $m) require_once $raiz . '/app/' . $m . '.php';

$fallos = 0;
function ok(bool $c, string $que): void { global $fallos; if (!$c) { $fallos++; echo "FALLA: $que\n"; } }

ok(!titular_oculto(), 'live: titular_oculto es falso');
$ep = empresa_publica();
ok($ep['titular'] === 'PT Mahkota Property Global' && $ep['nif'] === '' && $ep['domicilio'] === '' && $ep['email'] === 'hola@bodaenlace.com', "[$caso] empresa_publica = solo razón social y email");
ok(empresa_completa(), "[$caso] el candado de venta sigue abierto sin NIF ni domicilio");
require_once $raiz . '/app/correo.php';
require_once $raiz . '/app/correo_html.php';
$malo = static fn(string $s): bool => stripos($s, PERSONA) !== false || strpos($s, NIF_F) !== false || strpos($s, DOM_F) !== false;
foreach (['condiciones', 'privacidad', 'aviso-legal'] as $doc) {
    $h = documento_legal($doc, $doc);
    ok(!$malo($h), "[$caso] $doc sin persona, NIF ni domicilio");
    ok(strpos($h, 'PT Mahkota') !== false && strpos($h, 'hola@bodaenlace.com') !== false, "[$caso] $doc nombra a PT Mahkota y da el email");
    ok(!preg_match('~(NIF|Domicilio)\s*:\s*(</li>|<)~i', $h) && strpos($h, 'está en pruebas') === false, "[$caso] $doc sin campos vacíos ni «en pruebas»");
}
$ls = correo_linea_servicio($ep);
ok(!$malo($ls) && strpos($ls, 'PT Mahkota') !== false && strpos($ls, 'NIF') === false && strpos($ls, 'pruebas') === false, "[$caso] línea de servicio del correo: $ls");
echo $fallos ? "$fallos fallos\n" : "OK identidad_publica ($caso)\n";
exit($fallos ? 1 : 0);
