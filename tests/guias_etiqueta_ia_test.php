<?php
// BOD-52 (Legal #189): toda guía del Padrino lleva una etiqueta de IA puesta por el SERVIDOR al renderizar:
// visible junto al titular y en meta + JSON-LD; sin etiqueta no se publica ni se sirve. Sin red. Datos inventados.
// Uso: php tests/guias_etiqueta_ia_test.php  (sale con 1 si algo falla). El .htaccess devuelve 404 a tests/.

declare(strict_types=1);

$hijo = ($argv[1] ?? '') === '--hijo';
$tmp = $hijo ? (string) getenv('BODAS_TEST_TMP') : sys_get_temp_dir() . '/bodas_guias_test_' . bin2hex(random_bytes(4));
if (!$hijo) mkdir($tmp, 0700, true);
define('SIN_CONFIG_LOCAL', true);
define('DATA_DIR', $tmp);
define('BASE_DOMAIN', 'bodaenlace.com');
define('CREATOR_HOST', 'bodaenlace.com');
define('CREATOR_BASE', '');
define('CORREO_A_FICHERO', true);

$raiz = dirname(__DIR__);
foreach (['core', 'schema', 'render', 'foto', 'alta', 'mapa', 'cortesia', 'estudio', 'borrador', 'invitados', 'creador', 'landing', 'vista_constructor', 'boda', 'galeria', 'proxy'] as $m) {
    require_once $raiz . '/app/' . $m . '.php';
}

if ($hijo) {   // subproceso: json_response hace exit, así que el endpoint se ejecuta aparte
    padrino_contenido('', ['slug' => 'prueba-guia', 'titulo' => 'Cómo organizar la confirmación de invitados',
        'descripcion' => 'Pasos sencillos para recoger las confirmaciones de vuestros invitados sin perseguir a nadie.',
        'cuerpo' => str_repeat("Una guía de prueba sobre cómo organizar la confirmación de invitados con calma y sin papeles.

", 6),
        'origen' => 'humano', 'autor' => 'Otro']);
    exit;
}
$fallos = 0;
$n = 0;
function ok(bool $c, string $que): void { global $fallos, $n; $n++; if (!$c) { $fallos++; echo "FALLA: $que\n"; } }
function sirve(string $slug): array { ob_start(); $r = sirve_guia($slug); return [$r, (string) ob_get_clean()]; }

$TXT = 'Esta guía ha sido redactada por El Padrino, el asistente de inteligencia artificial de BodaEnlace.';
$cuerpo = str_repeat("Una guía de prueba sobre cómo organizar la confirmación de invitados con calma y sin papeles.\n\n", 6);
$titulo = 'Cómo organizar la confirmación de invitados';
$desc = 'Pasos sencillos para recoger las confirmaciones de vuestros invitados sin perseguir a nadie.';

// 1. El registro lo etiqueta el servidor, no el contenido que llega
$g = guia_registro('prueba-guia', $titulo, $desc, $cuerpo, null, '2026-10-02T10:00:00+02:00');
ok($g !== null && $g['origen'] === 'padrino-ia' && $g['autor'] === 'El Padrino', 'el servidor fija origen y autor de IA');
ok(guia_registro('x', $titulo, $desc, $cuerpo, null, 'basura') === null, 'fecha de publicación inválida: no se puede etiquetar -> null');
ok(guia_etiqueta_ia(['origen' => 'humano', 'publicada' => '2026-10-02T10:00:00+02:00']) === null, 'origen que no es el Padrino: sin etiqueta válida');

// 2. Publicar por el endpoint (en subproceso: json_response hace exit) y servir la guía
putenv('BODAS_TEST_TMP=' . $tmp);
$resp = json_decode((string) shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --hijo'), true);
ok(is_array($resp) && ($resp['ok'] ?? false) === true, 'el endpoint publica la guía');
$act = guia_lee('prueba-guia');
ok($act && $act['origen'] === 'padrino-ia' && $act['autor'] === 'El Padrino', 'origen/autor del cliente ignorados: manda el servidor');

[$r, $html] = sirve('prueba-guia');
ok($r === true, 'la guía se sirve');
$h1 = strpos($html, '<h1>');
$pEt = strpos($html, 'data-ia="padrino"');
ok($h1 !== false && $pEt !== false && $pEt > $h1 && $pEt - $h1 < 200, 'etiqueta visible justo tras el titular');
ok(strpos($html, htmlspecialchars($TXT, ENT_QUOTES)) !== false || strpos($html, $TXT) !== false, 'texto exacto de la etiqueta en el HTML');
ok(strpos($html, 'revisad') === false || strpos($html, 'revisada por el equipo') === false, 'no afirma revisión del equipo');
ok(strpos($html, 'Publicada el ' . fecha_larga(date('Y-m-d'), false)) !== false, 'fecha de publicación visible');
ok(strpos($html, '<meta name="ai-disclosure" content="' . $TXT . '">') !== false, 'etiqueta en <meta>');
ok(preg_match('#<script type="application/ld\+json">(.+?)</script>#s', $html, $m) === 1, 'hay JSON-LD');
$ld = json_decode($m[1] ?? '', true);
ok(is_array($ld) && ($ld['@type'] ?? '') === 'Article' && ($ld['creditText'] ?? '') === $TXT && !empty($ld['datePublished']) && strpos($ld['author']['name'] ?? '', 'inteligencia artificial') !== false, 'JSON-LD con etiqueta, autor IA y fecha');

// 3. Sin etiqueta no se sirve: guía con fecha corrupta en disco; y guia_pagina no pinta un slug sin guía/etiqueta
$g['publicada'] = 'basura';
escribe_json(guias_dir('rota', 'actual.json'), ['slug' => 'rota'] + $g);
[$r2, $h2] = sirve('rota');
ok($r2 === false && strpos($h2, '<h1>') === false, 'guía no etiquetable: 404, no se pinta');
$lanza = false; try { guia_pagina('T', 'D', '<p>x</p>', true, 'otra', null); } catch (LogicException $e) { $lanza = true; }
ok($lanza, 'guia_pagina con slug y sin guía lanza (no hay ruta que se salte la etiqueta)');
$lanza = false; try { guia_pagina('T', 'D', '<p>x</p>', true, 'otra', ['publicada' => 'basura']); } catch (LogicException $e) { $lanza = true; }
ok($lanza, 'guia_pagina con guía no etiquetable lanza');

// 4. Un único render de guías y el endpoint bloquea sin etiqueta (sobre el fuente)
$fuente = (string) file_get_contents($raiz . '/app/guias.php');
ok(substr_count($fuente, '<main class="simple-main">') === 1, 'solo guia_pagina() pinta guías');
ok(strpos($fuente, "if (\$r === null) { guias_log(['accion' => 'rechazo'") !== false && strpos($fuente, "no se publica.'], 500)") !== false, 'padrino_contenido responde error y no publica sin etiqueta');
$otros = 0; foreach (glob($raiz . '/app/*.php') as $f) if (basename($f) !== 'guias.php' && strpos((string) file_get_contents($f), 'guia_pagina(') !== false) $otros++;
ok($otros === 0, 'nadie más llama a guia_pagina');

foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $f) { $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname()); }
@rmdir($tmp);
echo ($fallos ? "ROJO: $fallos de $n fallan\n" : "VERDE: $n/$n\n");
exit($fallos ? 1 : 0);
