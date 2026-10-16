<?php
// El candado «DATA_DIR no puede estar dentro de la carpeta publicada» (app/core.php) no puede tumbar el cron (BOD-33/58,
// 16-oct-2026). En la línea de comandos no hay DOCUMENT_ROOT y realpath('') devuelve el directorio de trabajo: en Hostinger
// el cron corre desde el home y DATA_DIR (~/datos/bodas) cuelga de él, así que el diario moría en cada ejecución.
// Y el candado tiene que seguir parando en la web cuando DATA_DIR sí cae dentro de la carpeta servida.
// Uso: php tests/data_dir_cron_test.php
declare(strict_types=1);

$raiz = dirname(__DIR__);
$tmp = str_replace('\\', '/', sys_get_temp_dir()) . '/bodas_ddcron_' . bin2hex(random_bytes(4));
$home = $tmp . '/home';
$docroot = $tmp . '/home/public_html';
mkdir($home . '/datos/bodas', 0700, true);
mkdir($docroot . '/dentro', 0700, true);
file_put_contents($home . '/datos/bodas/secrets.php', '<?php return [];');
file_put_contents($docroot . '/dentro/secrets.php', '<?php return [];');

/** Carga app/core.php en un PHP aparte, desde $cwd, con ese DATA_DIR y ese DOCUMENT_ROOT; devuelve [código, salida]. */
function carga(string $raiz, string $cwd, string $dataDir, ?string $docroot): array {
    $codigo = '$_SERVER["DOCUMENT_ROOT"] = ' . var_export($docroot, true) . ';'
        . ($docroot === null ? 'unset($_SERVER["DOCUMENT_ROOT"]);' : '')
        . 'define("SIN_CONFIG_LOCAL", true); define("DATA_DIR", ' . var_export($dataDir, true) . ');'
        . 'require ' . var_export($raiz . '/app/core.php', true) . '; echo "ARRANCA";';
    $f = sys_get_temp_dir() . '/bodas_ddcron_carga_' . bin2hex(random_bytes(4)) . '.php';
    file_put_contents($f, '<?php ' . $codigo);
    $p = proc_open([PHP_BINARY, $f], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $tub, $cwd);
    $out = stream_get_contents($tub[1]) . stream_get_contents($tub[2]);
    $rc = proc_close($p);
    @unlink($f);
    return [$rc, $out];
}

$fallos = 0;
function ok(bool $c, string $que, string $out = ''): void { global $fallos; if (!$c) { $fallos++; echo "FALLA: $que\n" . ($out !== '' ? "  salida: " . trim($out) . "\n" : ''); } }

// 1. El cron: sin DOCUMENT_ROOT, desde el home, con DATA_DIR bajo el home pero fuera de public_html → arranca
[, $out] = carga($raiz, $home, $home . '/datos/bodas', null);
ok(strpos($out, 'ARRANCA') !== false && strpos($out, 'DATA_DIR no puede') === false, 'cron sin DOCUMENT_ROOT desde el home arranca', $out);
// 1b. Igual con DOCUMENT_ROOT vacío (algunos CLI lo definen así)
[, $out] = carga($raiz, $home, $home . '/datos/bodas', '');
ok(strpos($out, 'ARRANCA') !== false, 'cron con DOCUMENT_ROOT vacío arranca', $out);
// 2. La web: DATA_DIR dentro del DOCUMENT_ROOT → sigue parando
[, $out] = carga($raiz, $docroot, $docroot . '/dentro', $docroot);
ok(strpos($out, 'DATA_DIR no puede') !== false && strpos($out, 'ARRANCA') === false, 'web con DATA_DIR dentro del DOCUMENT_ROOT para', $out);
// 3. La web con DATA_DIR fuera del DOCUMENT_ROOT → arranca
[, $out] = carga($raiz, $docroot, $home . '/datos/bodas', $docroot);
ok(strpos($out, 'ARRANCA') !== false, 'web con DATA_DIR fuera del DOCUMENT_ROOT arranca', $out);
// 4. DATA_DIR dentro de la carpeta de la app (WEB_DIR), aunque sea el cron → sigue parando
@mkdir($raiz . '/_ddcron_tmp_' . basename($tmp), 0700);
[, $out] = carga($raiz, $home, $raiz . '/_ddcron_tmp_' . basename($tmp), null);
ok(strpos($out, 'DATA_DIR no puede') !== false, 'DATA_DIR dentro de WEB_DIR para también en el cron', $out);
@rmdir($raiz . '/_ddcron_tmp_' . basename($tmp));

foreach ([$home . '/datos/bodas/secrets.php', $docroot . '/dentro/secrets.php'] as $f) @unlink($f);
foreach ([$docroot . '/dentro', $docroot, $home . '/datos/bodas', $home . '/datos', $home, $tmp] as $d) @rmdir($d);
echo $fallos ? "data_dir_cron: $fallos fallo(s)\n" : "data_dir_cron: OK (5 casos)\n";
exit($fallos ? 1 : 0);
