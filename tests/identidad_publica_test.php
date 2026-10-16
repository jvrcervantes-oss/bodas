<?php
// Identidad pública (owner, 1-oct-2026): lo que sale en las páginas legales y los correos es SOLO «PT Mahkota» + el email.
// Aunque secrets.php siga trayendo nombre de persona, NIF y domicilio, ninguno sale. Uso: php tests/identidad_publica_test.php
declare(strict_types=1);

// Sin argumento: corre los dos casos (secrets viejo con persona/NIF/domicilio, y secrets nuevo solo razón social + email).
if (!isset($argv[1])) {
    $rc = 0;
    foreach (['viejo', 'nuevo', 'conrep'] as $c) { passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' ' . $c, $r); $rc |= $r; }
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
    'conrep' => ['razon_social' => 'PT Mahkota', 'email' => 'hola@bodaenlace.com'],
];
const REP_F = 'Representante Ficticio Uno';
const REP_MAIL_F = 'rep-ficticio@example.test';
$caso = $argv[1] ?? 'viejo';
file_put_contents($tmp . '/secrets.php', '<?php return ' . var_export([
    'pasarela' => 'lemon', 'lemon_api_key' => 'x', 'lemon_webhook_secret' => 'y', 'lemon_tienda' => '1', 'lemon_variante' => '2',
    'lemon_producto' => '3', 'lemon_test' => false, 'empresa' => $empresas[$caso],
] + ($caso === 'conrep' ? ['representante_ue' => ['nombre' => REP_F, 'email' => REP_MAIL_F]] : []), true) . ';');
$raiz = dirname(__DIR__);
foreach (['core', 'schema', 'render', 'foto', 'alta', 'mapa', 'cortesia', 'estudio', 'borrador', 'invitados', 'creador', 'landing', 'vista_constructor', 'boda', 'galeria', 'proxy'] as $m) require_once $raiz . '/app/' . $m . '.php';

$fallos = 0;
function ok(bool $c, string $que): void { global $fallos; if (!$c) { $fallos++; echo "FALLA: $que\n"; } }

ok(!titular_oculto(), 'live: titular_oculto es falso');
$ep = empresa_publica();
ok($ep['titular'] === 'PT Mahkota Property Global' && $ep['nif'] === '' && $ep['domicilio'] === '' && $ep['email'] === 'hola@bodaenlace.com', "[$caso] empresa_publica = solo razón social y email");
ok(empresa_completa(), "[$caso] el candado de venta sigue abierto sin NIF ni domicilio");
require_once $raiz . '/app/analitica.php';
ok(analitica_activa() === ($caso === 'viejo' && ANALITICA), "[$caso] analítica: solo con NIF y domicilio en secrets.php (Legal, 1-oct)");
require_once $raiz . '/app/correo.php';
require_once $raiz . '/app/correo_html.php';
$malo = static fn(string $s): bool => stripos($s, PERSONA) !== false || strpos($s, NIF_F) !== false || strpos($s, DOM_F) !== false;
foreach (['condiciones', 'privacidad', 'aviso-legal'] as $doc) {
    $h = documento_legal($doc, $doc);
    ok(!$malo($h), "[$caso] $doc sin persona, NIF ni domicilio");
    ok(strpos($h, 'PT Mahkota') !== false && strpos($h, 'hola@bodaenlace.com') !== false, "[$caso] $doc nombra a PT Mahkota y da el email");
    ok(!preg_match('~(NIF|Domicilio)\s*:\s*(</li>|<)~i', $h) && strpos($h, 'está en pruebas') === false, "[$caso] $doc sin campos vacíos ni «en pruebas»");
}
// Representante UE (art. 27): con él, nombre y contacto de secrets.php, nunca NIF ni domicilio; sin él, el párrafo no sale
// (16-oct: nada de avisos «PENDIENTE» en una página pública; la falta vive en contexto/pendientes.md, BOD-32).
$priv = documento_legal('privacidad', 'privacidad');
ok(strpos($priv, 'PENDIENTE') === false, "[$caso] la política no enseña ningún «PENDIENTE»");
// D1/D3 (16-oct) y la casilla de oposición, tal como la ve la pareja (texto de textos.php, no parafraseado)
ok(strpos($priv, 'como mucho 24 meses desde el último mensaje') !== false && strpos($priv, 'lo que gasta la IA, 6 años') !== false, "[$caso] la política lleva D1 (buzón 24 meses) y D3 (consumo de la IA 6 años)");
ok(strpos($priv, h(textos_legales()['check_oposicion_comercial'])) !== false, "[$caso] la política cita la casilla de oposición literal");
ok(stripos($priv, 'en frío') === false && stripos($priv, 'fuentes de acceso público') === false, "[$caso] sin sección de correo en frío a empresas");
if ($caso === 'conrep') {
    ok(strpos($priv, REP_F) !== false && strpos($priv, REP_MAIL_F) !== false && strpos($priv, 'designado por escrito') !== false, '[conrep] la política nombra al representante y su contacto');
} else {
    ok(strpos($priv, REP_F) === false && strpos($priv, 'Representante en la Unión Europea') === false, "[$caso] sin representante no sale el párrafo ni ningún nombre");
}
$ls = correo_linea_servicio($ep);
ok(!$malo($ls) && strpos($ls, 'PT Mahkota') !== false && strpos($ls, 'NIF') === false && strpos($ls, 'pruebas') === false, "[$caso] línea de servicio del correo: $ls");
echo $fallos ? "$fallos fallos\n" : "OK identidad_publica ($caso)\n";
exit($fallos ? 1 : 0);
