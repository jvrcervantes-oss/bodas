<?php
// Mapa de ubicaciones: UNA imagen estática con la ceremonia y el convite, hecha en nuestro
// servidor. El invitado no conecta con Google ni con OpenStreetMap (sin cookies ni IP a
// terceros) y la imagen viaja en el ZIP. Revisión previa #92 (Seguridad + Legal, 25-sep-2026):
//  - Solo se genera con sesión (panel) o tras el pago (alta), nunca desde la vista previa
//    anónima: si no, el creador sería un proxy gratis de Nominatim y de las teselas de OSM
//    y nos banearían la IP compartida del hosting.
//  - Políticas de OSMF: User-Agent con contacto, Nominatim a 1 petición/s en todo el servidor
//    y solo al guardar, teselas en caché al menos 7 días, sin precarga, una conexión a la vez.
//    Si Bodas pasa de unos cientos de mapas al mes: proveedor de pago (MapTiler, Stadia).
//  - SSRF: los hosts son fijos; lo que escribe la pareja solo entra como parámetro `q`, y un
//    enlace de Google Maps se lee con regex, nunca se pide.
//  - Un fallo nunca bloquea el guardado ni se guarda en caché: el mapa queda «pendiente» y
//    el cron lo reintenta. Atribución obligatoria: «© Colaboradores de OpenStreetMap».
declare(strict_types=1);

const MAPA_UA = 'BodasByAxisWorks/1.0 (+https://bodas.axisworks.studio; hello@axisworks.studio)';
const MAPA_W = 960;
const MAPA_H = 540;
const MAPA_V = 2;               // subir si cambia el dibujo: se regeneran todos
const MAPA_MAX_TESELAS = 20;

/** España, Portugal, Francia, Italia y Andorra (con Canarias, Azores y Madeira), en una caja. */
function mapa_coords_validas($lat, $lon): bool {
    return is_float($lat) && is_float($lon) && is_finite($lat) && is_finite($lon)
        && $lat >= 27.0 && $lat <= 51.6 && $lon >= -31.5 && $lon <= 19.5;
}

/** «lat, lon» o un enlace largo de Google Maps. Los cortos (maps.app.goo.gl) no llevan coordenadas. */
function mapa_coords_de_texto(string $s): ?array {
    $pats = ['~!3d(-?\d{1,2}\.\d+)!4d(-?\d{1,3}\.\d+)~', '~@(-?\d{1,2}\.\d+),(-?\d{1,3}\.\d+)~',
        '~[?&](?:q|ll|query|destination)=(-?\d{1,2}\.\d+)(?:,|%2C)\s*(-?\d{1,3}\.\d+)~i', '~^\s*(-?\d{1,2}\.\d+)\s*[,;]\s*(-?\d{1,3}\.\d+)\s*$~'];
    foreach ($pats as $p) {
        if (preg_match($p, $s, $m)) {
            $lat = (float) $m[1];
            $lon = (float) $m[2];
            return mapa_coords_validas($lat, $lon) ? [$lat, $lon] : null;
        }
    }
    return null;
}

/** GET por HTTPS a un host fijo, sin redirecciones y con tope de bytes. [código, cuerpo] o null. */
function mapa_http(string $url, int $maxBytes): ?array {
    if (!function_exists('curl_init')) return null;
    $buf = '';
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 3, CURLOPT_TIMEOUT => 5, CURLOPT_USERAGENT => MAPA_UA,
        CURLOPT_HTTPHEADER => ['Accept-Language: es'],
        CURLOPT_WRITEFUNCTION => function ($ch, $trozo) use (&$buf, $maxBytes) {
            $buf .= $trozo;
            return strlen($buf) > $maxBytes ? 0 : strlen($trozo); // pasar del tope aborta
        },
    ]);
    $ok = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    return ($ok === false || strlen($buf) > $maxBytes) ? null : [$code, $buf];
}

/** Nominatim: 1 petición por segundo en todo el servidor (cerrojo + marca de tiempo). */
function mapa_nominatim(string $q): ?array {
    asegura_dir(dir_datos('locks'));
    $fp = fopen(dir_datos('locks', 'nominatim.lock'), 'c+');
    if (!$fp) return null;
    flock($fp, LOCK_EX);
    try {
        $ultima = (float) stream_get_contents($fp);
        $espera = $ultima + 1.1 - microtime(true);
        if ($espera > 0) usleep((int) min(1.2e6, $espera * 1e6));
        $r = mapa_http('https://nominatim.openstreetmap.org/search?' . http_build_query(
            ['q' => $q, 'format' => 'jsonv2', 'limit' => 1, 'countrycodes' => 'es,pt,fr,it,ad']), 200000);
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, (string) microtime(true));
        return $r;
    } finally {
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}

/** Dirección → [lat, lon]. Caché indefinida de aciertos; un «no existe» se recuerda un día. */
function mapa_geocodifica(string $q): ?array {
    $q = trim(preg_replace('/\s+/u', ' ', mb_strtolower(mb_substr($q, 0, 300, 'UTF-8'), 'UTF-8')));
    if (mb_strlen($q, 'UTF-8') < 4) return null;
    $f = dir_datos('geo', sha1($q) . '.json');
    $cache = lee_json($f);
    if ($cache && isset($cache['lat'], $cache['lon'])) return [(float) $cache['lat'], (float) $cache['lon']];
    if ($cache && !empty($cache['nada']) && ($cache['t'] ?? 0) > time() - 86400) return null;
    $r = mapa_nominatim($q);
    if (!$r || $r[0] !== 200) return null;            // fallo de red o del servicio: nada en caché
    $j = json_decode($r[1], true);
    if (!is_array($j)) return null;
    asegura_dir(dir_datos('geo'));
    if (!$j) { escribe_json($f, ['nada' => true, 't' => time()]); return null; }
    $lat = (float) ($j[0]['lat'] ?? 'x');
    $lon = (float) ($j[0]['lon'] ?? 'x');
    if (!mapa_coords_validas($lat, $lon)) return null;
    escribe_json($f, ['lat' => $lat, 'lon' => $lon, 't' => time()]);
    return [$lat, $lon];
}

/** Una tesela de 256 px de OSM, de la caché si tiene menos de 30 días. */
function mapa_tesela(int $z, int $x, int $y) {
    $n = 1 << $z;
    if ($z < 10 || $z > 16 || $x < 0 || $y < 0 || $x >= $n || $y >= $n) return null;
    $f = dir_datos('teselas', (string) $z, (string) $x, $y . '.png');
    if (is_file($f) && filemtime($f) > time() - 30 * 86400) return @imagecreatefrompng($f) ?: null;
    $r = mapa_http("https://tile.openstreetmap.org/$z/$x/$y.png", 150000);
    if (!$r || $r[0] !== 200) return null;
    $info = @getimagesizefromstring($r[1]);
    if (!$info || $info[0] !== 256 || $info[1] !== 256 || $info[2] !== IMAGETYPE_PNG) return null;
    asegura_dir(dirname($f));
    $tmp = $f . '.' . bin2hex(random_bytes(4)) . '.tmp';
    file_put_contents($tmp, $r[1]);
    rename($tmp, $f);
    return @imagecreatefromstring($r[1]) ?: null;
}

/** Coordenadas del mundo en píxeles (Web Mercator) a zoom $z. */
function mapa_px(float $lat, float $lon, int $z): array {
    $s = 256 * (1 << $z);
    $r = deg2rad($lat);
    return [($lon + 180) / 360 * $s, (1 - log(tan($r) + 1 / cos($r)) / M_PI) / 2 * $s];
}

/** ¿Caben todos los puntos en un mapa a zoom 10 como mínimo? */
function mapa_caben(array $pts): bool {
    $ps = array_map(fn($p) => mapa_px($p[0], $p[1], 10), $pts);
    return max(array_column($ps, 0)) - min(array_column($ps, 0)) <= MAPA_W - 220 && max(array_column($ps, 1)) - min(array_column($ps, 1)) <= MAPA_H - 170;
}

/** Hex «#RRGGBB» → [r, g, b]. */
function mapa_rgb(string $hex): array {
    $h = ltrim($hex, '#');
    return strlen($h) === 6 && ctype_xdigit($h) ? array_map('hexdec', str_split($h, 2)) : [128, 128, 128];
}

/**
 * Compone el mapa de 1-2 puntos, virado a dos tintas con los colores del diseño (oscuro =
 * primario, claro = papel). Devuelve [id, pines en %] o null si algo falla (nunca a medias).
 */
function mapa_compone(array $pts, string $oscuro, string $claro): ?array {
    if (!function_exists('imagecreatetruecolor') || !function_exists('imagewebp')) return null;
    $zoom = null;
    for ($z = 16; $z >= 10; $z--) {
        $ps = array_map(fn($p) => mapa_px($p[0], $p[1], $z), $pts);
        $xs = array_column($ps, 0);
        $ys = array_column($ps, 1);
        if (max($xs) - min($xs) <= MAPA_W - 220 && max($ys) - min($ys) <= MAPA_H - 170) { $zoom = $z; break; }
    }
    if ($zoom === null) return null;                   // demasiado lejos para un mapa legible
    if (count($pts) === 1) $zoom = min($zoom, 15);
    $ps = array_map(fn($p) => mapa_px($p[0], $p[1], $zoom), $pts);
    $cx = (min(array_column($ps, 0)) + max(array_column($ps, 0))) / 2;
    $cy = (min(array_column($ps, 1)) + max(array_column($ps, 1))) / 2 + 18; // un poco abajo: las chinchetas miran arriba
    $x0 = (int) floor($cx - MAPA_W / 2);
    $y0 = (int) floor($cy - MAPA_H / 2);
    $tx0 = intdiv($x0, 256); $ty0 = intdiv($y0, 256);
    $tx1 = intdiv($x0 + MAPA_W - 1, 256); $ty1 = intdiv($y0 + MAPA_H - 1, 256);
    if (($tx1 - $tx0 + 1) * ($ty1 - $ty0 + 1) > MAPA_MAX_TESELAS) return null;

    $im = imagecreatetruecolor(MAPA_W, MAPA_H);
    for ($ty = $ty0; $ty <= $ty1; $ty++) {
        for ($tx = $tx0; $tx <= $tx1; $tx++) {
            $t = mapa_tesela($zoom, $tx, $ty);
            if (!$t) { imagedestroy($im); return null; }
            imagecopy($im, $t, $tx * 256 - $x0, $ty * 256 - $y0, 0, 0, 256, 256);
            imagedestroy($t);
        }
    }
    // Dos tintas: gris → paleta de 256 → cada gris pasa a una mezcla de claro y oscuro (suave)
    imagefilter($im, IMG_FILTER_GRAYSCALE);
    imagetruecolortopalette($im, false, 256);
    [$or, $og, $ob] = mapa_rgb($oscuro);
    [$cr, $cg, $cb] = mapa_rgb($claro);
    for ($i = 0, $n = imagecolorstotal($im); $i < $n; $i++) {
        $c = imagecolorsforindex($im, $i);
        $t = 0.25 + 0.75 * (($c['red'] / 255) ** 2);     // fondo casi papel; calles y nombres con cuerpo
        imagecolorset($im, $i, (int) round($cr * $t + $or * (1 - $t)), (int) round($cg * $t + $og * (1 - $t)), (int) round($cb * $t + $ob * (1 - $t)));
    }
    imagepalettetotruecolor($im);
    $id = sha1(json_encode([MAPA_V, $pts, $zoom, $oscuro, $claro]));
    asegura_dir(dir_datos('mapas'));
    $f = dir_datos('mapas', $id . '.webp');
    $tmp = $f . '.' . bin2hex(random_bytes(4)) . '.tmp';
    imagewebp($im, $tmp, 80);
    imagedestroy($im);
    rename($tmp, $f);
    $pines = [];
    foreach ($ps as $p) $pines[] = [round(($p[0] - $x0) / MAPA_W * 100, 2), round(($p[1] - $y0) / MAPA_H * 100, 2)];
    return [$id, $pines];
}

/** Los sitios que salen en el mapa: [etiqueta, texto para geocodificar, coordenadas a mano]. */
function mapa_sitios(array $c): array {
    $s = [];
    $ciudad = $c['ciudad'] ?? '';
    $mismo = !empty($c['convite']['mismo']);
    foreach (['ceremonia' => 'Ceremonia', 'convite' => 'Convite'] as $k => $rot) {
        $e = $c[$k];
        if ($k === 'convite' && $mismo) continue;
        if (trim($e['lugar'] . $e['direccion'] . ($e['coords'] ?? '')) === '') continue;
        // Con dirección se geocodifica la dirección (más fiable que el nombre del salón)
        $q = $e['direccion'] !== '' ? $e['direccion'] . ($ciudad !== '' && mb_stripos($e['direccion'], $ciudad) === false ? ', ' . $ciudad : '') : trim($e['lugar'] . ', ' . $ciudad, ', ');
        $s[] = [$k === 'ceremonia' && $mismo ? 'Ceremonia y convite' : $rot, $q, (string) ($e['coords'] ?? '')];
    }
    return $s;
}

/** Datos del mapa guardado de una boda (o null). */
function mapa_de(string $slug): ?array {
    $m = lee_json(dir_boda($slug) . '/mapa.json');
    return ($m && !empty($m['id']) && preg_match('/^[a-f0-9]{40}$/', $m['id']) && is_file(dir_datos('mapas', $m['id'] . '.webp'))) ? $m : null;
}

/**
 * Rehace el mapa de una boda si han cambiado sus sitios o sus colores. Nunca lanza ni
 * bloquea: si algo falla, queda pendiente para el cron. Devuelve el estado.
 */
function mapa_actualiza(string $slug, array $c): string {
    try {
        $sitios = mapa_sitios($c);
        $t = (($c['atelier'] ?? '') !== '') ? array_merge([''], ATELIER[$c['atelier']]['colores']) : (TEMAS[$c['tema']] ?? TEMAS['eucalipto']);
        $firma = sha1(json_encode([MAPA_V, $sitios, $t[1], $t[5]]));
        $f = dir_boda($slug) . '/mapa.json';
        $prev = lee_json($f) ?? [];
        if (!$sitios) { @unlink($f); return 'sin-sitios'; }
        if (($prev['firma'] ?? '') === $firma && (mapa_de($slug) || ($prev['error'] ?? '') === 'sin-sitio')) return 'igual';
        $pts = [];
        $etiquetas = [];
        foreach ($sitios as [$rot, $q, $coords]) {
            $p = $coords !== '' ? mapa_coords_de_texto($coords) : null;
            $p = $p ?? mapa_geocodifica($q);
            if ($p) { $pts[] = $p; $etiquetas[] = $rot; }
        }
        if (!$pts) { escribe_json($f, ['firma' => $firma, 'error' => 'sin-sitio', 't' => time()]); return 'sin-sitio'; }
        // Un mapa a la vez en todo el servidor (CPU/RAM de GD en hosting compartido)
        asegura_dir(dir_datos('locks'));
        $fl = fopen(dir_datos('locks', 'mapa.lock'), 'c');
        flock($fl, LOCK_EX);
        try { $r = mapa_compone($pts, $t[1], $t[5]); } finally { flock($fl, LOCK_UN); fclose($fl); }
        // Ceremonia y convite demasiado lejos para un solo mapa legible: se queda la ceremonia
        if (!$r && count($pts) > 1 && !mapa_caben($pts)) {
            $pts = [$pts[0]];
            $etiquetas = [$etiquetas[0]];
            flock($fl = fopen(dir_datos('locks', 'mapa.lock'), 'c'), LOCK_EX);
            try { $r = mapa_compone($pts, $t[1], $t[5]); } finally { flock($fl, LOCK_UN); fclose($fl); }
        }
        if (!$r) { escribe_json($f, ['firma' => $firma, 'pendiente' => true, 't' => time()]); return 'pendiente'; }
        $pines = [];
        foreach ($r[1] as $i => $xy) $pines[] = ['x' => $xy[0], 'y' => $xy[1], 't' => $etiquetas[$i]];
        escribe_json($f, ['firma' => $firma, 'id' => $r[0], 'pines' => $pines, 't' => time(), 'faltan' => count($sitios) - count($pts)]);
        return 'ok';
    } catch (Throwable $e) {
        registra('mapa: error al generar', ['slug' => $slug, 'e' => $e->getMessage()]);
        return 'error';
    }
}

function sirve_mapa(string $slug): void {
    $m = mapa_de($slug);
    if (!$m) { http_response_code(404); exit; }
    $f = dir_datos('mapas', $m['id'] . '.webp');
    header('Content-Type: image/webp');
    header('Content-Length: ' . filesize($f));
    header('Cache-Control: public, max-age=86400');
    header('X-Content-Type-Options: nosniff');
    readfile($f);
    exit;
}
