<?php
// Tarea diaria (cron de Hostinger: php cron/diario.php). Solo por línea de comandos.
//  1. Bodas cuya fecha + MESES_ALOJAMIENTO ya pasó: se BORRAN las respuestas de
//     invitados, las canciones y la foto, y la web queda como página de agradecimiento.
//     Es lo que prometen las condiciones, el encargo de tratamiento y la privacidad
//     de cada boda (Legal, 25-sep): si esto no corre, los tres textos mienten.
//  2. Pedidos pendientes de más de 2 h, reservas de nombre caducadas y contadores
//     de límite viejos.
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__ . '/../app/core.php';
require __DIR__ . '/../app/schema.php';
require __DIR__ . '/../app/render.php';
require __DIR__ . '/../app/alta.php';
require __DIR__ . '/../app/mapa.php';

$hoy = date('Y-m-d');
$n = ['archivadas' => 0, 'pendientes' => 0, 'reservas' => 0, 'rl' => 0];

foreach (glob(dir_datos('bodas', '*'), GLOB_ONLYDIR) ?: [] as $d) {
    $c = lee_json($d . '/config.json');
    if (!$c || ($c['_estado'] ?? '') === 'archivada') continue;
    $borrado = fecha_borrado((string) ($c['fecha'] ?? ''));
    if ($borrado === '' || $borrado > $hoy) continue;
    borra_arbol($d . '/guardado');
    borra_arbol($d . '/historial');
    borra_arbol($d . '/galeria');     // fotos de la pareja
    borra_arbol($d . '/libro');       // mensajes, fotos e IP de los invitados
    @unlink($d . '/foto.webp');
    $c['_estado'] = 'archivada';
    $c['foto'] = false;
    escribe_json($d . '/config.json', $c);
    registra('boda archivada: datos de invitados borrados', ['slug' => basename($d), 'fecha' => $c['fecha']]);
    $n['archivadas']++;
}

// 3. Mapas que quedaron pendientes (Nominatim o las teselas fallaron al guardar). Uno tras otro:
//    las peticiones a OSM ya van de una en una y a 1/s (políticas de OSMF).
$n['mapas'] = 0;
foreach (glob(dir_datos('bodas', '*'), GLOB_ONLYDIR) ?: [] as $d) {
    $m = lee_json($d . '/mapa.json');
    if (!$m || empty($m['pendiente'])) continue;
    $c = lee_json($d . '/config.json');
    if (!$c || ($c['_estado'] ?? '') === 'archivada') continue;
    if (mapa_actualiza(basename($d), normaliza_config($c)) === 'ok') $n['mapas']++;
}

// 4. Panel del estudio: el log de acciones se guarda 12 meses (Legal, #96) y las sesiones, 12 h
foreach (glob(dir_datos('estudio', 'log-*.jsonl')) ?: [] as $f) {
    if (preg_match('/log-(\d{4}-\d{2})\.jsonl$/', $f, $m) && $m[1] < date('Y-m', strtotime('-12 months'))) @unlink($f);
}
foreach (glob(dir_datos('estudio', 'sesiones', 'sess_*')) ?: [] as $f) {
    if (filemtime($f) < time() - 12 * 3600) @unlink($f);
}

foreach (glob(dir_datos('pendientes', '*'), GLOB_ONLYDIR) ?: [] as $d) {
    $m = lee_json($d . '/meta.json');
    if (($m['creado'] ?? 0) < time() - 7200) { borra_arbol($d); $n['pendientes']++; }
}
foreach (glob(dir_datos('reservas', '*.json')) ?: [] as $f) {
    if ((lee_json($f)['hasta'] ?? 0) < time()) { @unlink($f); $n['reservas']++; }
}
foreach (glob(dir_datos('rl', '*.json')) ?: [] as $f) {
    $d = lee_json($f) ?? [];
    // Cada contador sabe su ventana (los votos duran un año: "un voto por canción")
    if (($d['desde'] ?? 0) + ($d['v'] ?? 86400) < time()) { @unlink($f); $n['rl']++; }
}
echo json_encode($n), "\n";
