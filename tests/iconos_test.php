<?php
// Iconos de la marca (ICONOS_L de app/landing.php): pinta la landing, el creador y el editor del panel con los avisos de
// PHP convertidos en error, y exige que ningún icono salga con el trazado vacío. Existe porque el 28-sep-2026 se retiró
// 'lista' de ICONOS_L y el menú del editor (vista_constructor.php) lo pedía por variable: un grep de il('lista') no lo
// veía y el editor habría salido con un Warning y un icono vacío. Uso: php tests/iconos_test.php (sale con 1 si falla)

declare(strict_types=1);

$tmp = sys_get_temp_dir() . '/bodas_iconos_test_' . bin2hex(random_bytes(4));
mkdir($tmp, 0700, true);
define('SIN_CONFIG_LOCAL', true);
define('DATA_DIR', $tmp);
define('BASE_DOMAIN', 'bodaenlace.com');
define('CREATOR_HOST', 'bodaenlace.com');
define('CREATOR_BASE', '');
$raiz = dirname(__DIR__);
foreach (['core', 'schema', 'render', 'foto', 'alta', 'mapa', 'cortesia', 'estudio', 'borrador', 'invitados', 'creador', 'landing', 'vista_constructor', 'boda', 'galeria', 'proxy'] as $m) {
    require_once $raiz . '/app/' . $m . '.php';
}
$fallos = 0;
function ok(bool $c, string $q): void { global $fallos; if (!$c) { $fallos++; echo "FALLA: $q\n"; } }

// Cualquier aviso (clave de icono que no existe incluida) es un fallo
set_error_handler(function (int $n, string $msg, string $f, int $l): bool { throw new ErrorException($msg, 0, $n, $f, $l); });

$paginas = [
    'landing' => fn() => pagina_landing(),
    'creador' => fn() => vista_constructor('crear', config_inicial(), ''),
    'editor del panel' => fn() => vista_constructor('editar', config_inicial(), 'iconos-prueba', 'csrf-prueba'),
];
foreach ($paginas as $nombre => $pinta) {
    $nivel = ob_get_level();
    try {
        $html = $pinta();
        ok(strpos($html, 'd=""') === false, "$nombre: un icono sale con el trazado vacío");
    } catch (Throwable $e) {
        while (ob_get_level() > $nivel) ob_end_clean();   // la página a medias no se imprime
        ok(false, "$nombre: " . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')');
    }
}
restore_error_handler();

// limpieza
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($it as $f) $f->isDir() ? rmdir((string) $f) : unlink((string) $f);
rmdir($tmp);

echo $fallos ? "iconos: $fallos fallo(s)\n" : "iconos: ok\n";
exit($fallos ? 1 : 0);
