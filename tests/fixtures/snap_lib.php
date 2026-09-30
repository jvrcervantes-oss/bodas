<?php
// Utilidades del snapshot de webs "antiguas" (sin programa, historia, hashtag, vestimenta ni estilo instantánea).
// Los .html de esta carpeta se generaron con el código ANTERIOR al paquete y se comparan byte a byte:
// una web ya publicada no debe cambiar ni un píxel. Datos inventados.

declare(strict_types=1);

/** Nombres de los configs de prueba (tests/fixtures/config_<nombre>.json = config.json guardado antes del paquete). */
const SNAP_CONFIGS = ['esencial', 'foto_papel', 'atelier_lacre'];

/** Todas las páginas de una web (live y zip) en un solo texto, con el sello de assets neutralizado. */
function snap_render(array $c): string {
    $out = '';
    $ctxL = ['modo' => 'live', 'assets' => '/assets/', 'slug' => 'prueba-snap', 'foto' => !empty($c['foto']) ? '/foto?v=1' : '', 'mapa' => null];
    $ctxZ = ['modo' => 'zip', 'assets' => 'assets/', 'slug' => 'prueba-snap', 'foto' => !empty($c['foto']) ? 'foto.webp' : '', 'mapa' => null];
    $rutas = ['', 'privacidad'];
    foreach ($c['secciones'] as $s) if ($s['on']) $rutas[] = $s['ruta'];
    foreach (['live' => $ctxL, 'zip' => $ctxZ] as $modo => $ctx) {
        foreach ($rutas as $r) {
            $h = render_pagina($c, $r, $ctx);
            $out .= "\n<!-- ==== $modo /$r ==== -->\n" . preg_replace('/\?v=[0-9a-f]{8}/', '?v=X', (string) $h);
        }
    }
    $out .= "\n<!-- ==== ics ==== -->\n" . preg_replace('/DTSTAMP:\S+/', 'DTSTAMP:X', ics($c));
    $out .= "\n<!-- ==== menu-tab ==== -->\n" . tab_bar($c, '', $ctxL);
    return str_replace(chr(13), '', $out);   // la copia de trabajo en Windows puede traer CRLF; git y producción, LF
}
