<?php
// Utilidades del snapshot HTML de las webs, comparado byte a byte: una web ya publicada no debe cambiar ni un píxel.
// Dos familias de fixtures (datos inventados):
//  · esencial, foto_papel, atelier_lacre: webs "antiguas" (sin programa, historia, hashtag, vestimenta ni estilo
//    instantánea); sus .html se generaron con el código ANTERIOR al paquete del 30-sep-2026.
//  · ejemplo_<diseño>: la boda de ejemplo de cada diseño Atelier, con todo relleno (programa de 4 paradas, historia,
//    hashtag, vestimenta, colores del dress code). Misma base para los seis; solo cambia `atelier`. Sus .html son la
//    foto del código del 9-oct-2026, red del encargo 20261009_bodas_editor_disenos_atelier (S0): al parametrizar el
//    CSS Atelier el HTML no cambia. Regrabados a propósito (y snap_atelier_lacre con ellos) con la base corregida de
//    S2b (d64bfc0): la decisión del owner fue aplicarla también a las bodas Atelier ya publicadas. Su pareja en estilos computados es tests/estilos_atelier.js.

declare(strict_types=1);

/** Nombres de los configs de prueba (tests/fixtures/config_<nombre>.json). */
const SNAP_CONFIGS = ['esencial', 'foto_papel', 'atelier_lacre',
    'ejemplo_citricos', 'ejemplo_ceramica', 'ejemplo_herbario', 'ejemplo_lacre', 'ejemplo_masia', 'ejemplo_atardecer'];

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
