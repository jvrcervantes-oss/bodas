<?php
// La vista previa oculta el cuerpo hasta tener las fuentes (render.php + vista-previa.js), con respaldo en CSS a los 3 s.
// El riesgo grave es que ese «visibility:hidden» salga en la web publicada o en el ZIP: boda en blanco. Uso: php tests/previa_visible_test.php

declare(strict_types=1);

$tmp = sys_get_temp_dir() . '/bodas_previa_test_' . bin2hex(random_bytes(4));
mkdir($tmp, 0700, true);
define('SIN_CONFIG_LOCAL', true);
define('DATA_DIR', $tmp);
define('BASE_DOMAIN', 'bodaenlace.com');
define('CREATOR_HOST', 'bodaenlace.com');
define('CREATOR_BASE', '');
$raiz = dirname(__DIR__);
foreach (['core', 'schema', 'render'] as $m) require_once $raiz . '/app/' . $m . '.php';
$fallos = 0;
function ok(bool $c, string $q): void { global $fallos; if (!$c) { $fallos++; echo "FALLA: $q\n"; } }

$c = normaliza_config(config_inicial());
$prev = (string) render_pagina($c, '', ['modo' => 'preview', 'assets' => 'https://x.test/assets/']);
ok(strpos($prev, 'body{visibility:hidden') !== false, 'preview: falta ocultar el cuerpo hasta las fuentes');
ok(strpos($prev, 'animation:vp-ver 0s 3s') !== false, 'preview: falta el respaldo CSS que lo muestra a los 3 s');
foreach (['live' => '/assets/', 'zip' => 'assets/'] as $modo => $assets) {
    $h = (string) render_pagina($c, '', ['modo' => $modo, 'assets' => $assets]);
    ok(strpos($h, 'visibility:hidden') === false, "$modo: sale visibility:hidden (web en blanco)");
}
echo $fallos ? "$fallos fallo(s)\n" : "OK previa_visible\n";
exit($fallos ? 1 : 0);
