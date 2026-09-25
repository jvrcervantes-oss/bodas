<?php
// Núcleo del producto "webs de boda": configuración, rutas de datos y utilidades.
//
// Una sola app sirve el creador (CREATOR_HOST) y todas las bodas (<slug>.BASE_DOMAIN).
// Los datos viven en DATA_DIR, FUERA de la carpeta publicada: en EduCora `guardado/`
// estaba dentro del webroot y solo lo protegía un .htaccess — el mismo riesgo que en
// SumbaHills sirvió leads en abierto (27-jul-2026). Aquí la app ni siquiera arranca
// si DATA_DIR cae dentro de la carpeta servida.

declare(strict_types=1);

// Fechas de factura, logs y "hoy" del cron: hora de España (el hosting va en UTC)
date_default_timezone_set('Europe/Madrid');

const APP_DIR = __DIR__;
define('WEB_DIR', dirname(__DIR__));

if (is_file(APP_DIR . '/config.local.php')) require APP_DIR . '/config.local.php';

defined('DATA_DIR')     || define('DATA_DIR', dirname(WEB_DIR) . '/bodas_datos');
defined('BASE_DOMAIN')  || define('BASE_DOMAIN', 'axisworks.studio');
defined('CREATOR_HOST') || define('CREATOR_HOST', 'bodas.' . BASE_DOMAIN);
defined('SCHEME')       || define('SCHEME', 'https');
// Stripe: la base de la API se puede apuntar a un simulador local para pruebas.
defined('STRIPE_API')   || define('STRIPE_API', 'https://api.stripe.com');

// Precio: fijado por el owner el 25-sep-2026 — 100 € + IVA. Nunca llega del cliente.
const PRECIO_BASE_CENT = 10000;
// Diseños de la Colección Atelier: +50 € sobre la base, IVA aparte como el resto (owner, 25-sep-2026)
const PRECIO_ATELIER_CENT = 5000;
const IVA_PCT = 21;
// Tras la fecha de la boda: la web pasa a agradecimiento y se borran los datos de invitados.
// 2 meses por decisión del owner (25-sep-2026; antes 4): menos tiempo guardando alergias (dato de salud).
// Todos los textos (legales, landing, email, Stripe) leen esta constante: no escribir el número a mano.
const MESES_ALOJAMIENTO = 2;
const PRODUCTO = 'bodas';              // marca de propiedad en la metadata de Stripe
// Nombre comercial del producto. "Vowly" (el de la maqueta de Stitch) está cogido por
// competidores directos (25-sep-2026); el owner elige entre las propuestas. Una sola constante.
const MARCA = 'Bodas by AxisWorks';

if (strpos(str_replace('\\', '/', realpath(DATA_DIR) ?: DATA_DIR) . '/', str_replace('\\', '/', WEB_DIR) . '/') === 0) {
    http_response_code(500);
    exit('DATA_DIR no puede estar dentro de la carpeta publicada.');
}

/** Secretos (Stripe, datos de la entidad): DATA_DIR/secrets.php, nunca en git. */
function secreto(string $k, $def = '') {
    static $s = null;
    if ($s === null) {
        $f = DATA_DIR . '/secrets.php';
        $s = is_file($f) ? (array) require $f : [];
    }
    return $s[$k] ?? $def;
}

/** Datos del titular para textos legales y facturas. Vacíos = pendiente del owner. */
function empresa(): array {
    $e = (array) secreto('empresa', []);
    return [
        'titular' => (string) ($e['titular'] ?? ''),
        'nif' => (string) ($e['nif'] ?? ''),
        'domicilio' => (string) ($e['domicilio'] ?? ''),
        'email' => (string) ($e['email'] ?? 'hello@axisworks.studio'),
    ];
}
function empresa_completa(): bool {
    $e = empresa();
    return $e['titular'] !== '' && $e['nif'] !== '' && $e['domicilio'] !== '';
}
function stripe_modo_live(): bool {
    return strpos((string) secreto('stripe_secret'), '_live_') !== false;
}

// Versión de los assets para romper la caché del CDN de Hostinger (sirve CSS viejo
// si la URL no cambia — memoria reference_hostinger_cdn_css_sin_version).
define('ASSETS_V', substr(md5((string) @filemtime(WEB_DIR . '/assets/boda.css') . (string) @filemtime(WEB_DIR . '/assets/js/boda.js') . (string) @filemtime(WEB_DIR . '/assets/js/crear.js') . (string) @filemtime(WEB_DIR . '/assets/crear.css') . (string) @filemtime(WEB_DIR . '/assets/landing.css') . (string) @filemtime(WEB_DIR . '/assets/marca.css')), 0, 8));

function h($v): string { return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function dir_datos(string ...$partes): string {
    $p = DATA_DIR . ($partes ? '/' . implode('/', $partes) : '');
    return $p;
}
function asegura_dir(string $d): void {
    if (!is_dir($d) && !@mkdir($d, 0750, true) && !is_dir($d)) {
        throw new RuntimeException('No se pudo crear ' . $d);
    }
}

function json_response(array $data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function lee_json(string $f): ?array {
    if (!is_file($f)) return null;
    $fp = fopen($f, 'r');
    if ($fp === false) return null;
    flock($fp, LOCK_SH);
    $c = stream_get_contents($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    $d = json_decode((string) $c, true);
    return is_array($d) ? $d : null;
}

/** Escritura atómica: fichero temporal + rename, para que nunca quede un JSON a medias. */
function escribe_json(string $f, array $d): void {
    asegura_dir(dirname($f));
    $tmp = $f . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (file_put_contents($tmp, json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false) {
        throw new RuntimeException('No se pudo escribir ' . $f);
    }
    if (!@rename($tmp, $f)) { @unlink($f); rename($tmp, $f); }
}

/**
 * Lee-modifica-escribe un array JSON con lock exclusivo. $fn recibe el array por
 * referencia y devuelve lo que se quiera devolver al llamador. Si el fichero pasa
 * de $maxBytes tras el cambio, no se escribe y se devuelve null: una boda no puede
 * llenar el disco que comparte con todas las demás.
 */
function muta_json(string $f, callable $fn, int $maxBytes = 0) {
    asegura_dir(dirname($f));
    $fp = fopen($f, 'c+');
    if ($fp === false) return null;
    if (!flock($fp, LOCK_EX)) { fclose($fp); return null; }
    $c = stream_get_contents($fp);
    $d = $c !== '' ? json_decode((string) $c, true) : [];
    if (!is_array($d)) $d = [];
    $ret = $fn($d);
    $out = json_encode($d, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if ($maxBytes > 0 && strlen($out) > $maxBytes) {
        flock($fp, LOCK_UN); fclose($fp);
        return null;
    }
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, $out);
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);
    return $ret;
}

function clean_str($v, int $max = 500): string {
    $v = is_string($v) ? $v : (is_int($v) || is_float($v) ? (string) $v : '');
    // Bytes que no son UTF-8 válido se descartan: si no, preg_replace(/u) devuelve null
    // y un nombre entero se quedaría vacío sin avisar
    if (!mb_check_encoding($v, 'UTF-8')) $v = mb_convert_encoding($v, 'UTF-8', 'UTF-8');
    $v = str_replace("\r", '', trim($v));
    // Fuera caracteres de control (salvo salto de línea y tabulador)
    $v = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v);
    return mb_substr($v, 0, $max, 'UTF-8');
}

/** IP del cliente. Hostinger entrega REMOTE_ADDR real; no se confía en X-Forwarded-For. */
function ip_cliente(): string { return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'); }

/**
 * Límite de peticiones por ventana: $clave ya incluye lo que acota (IP, boda…).
 * Devuelve false si se ha superado. Ficheros en DATA_DIR/rl, los barre el cron diario.
 */
/**
 * Límite de peticiones por ventana. $estricto = true falla en cerrado (si el disco no
 * responde, se rechaza): obligatorio en subidas anónimas (Seguridad, #87).
 */
function limite(string $clave, int $max, int $ventana, bool $estricto = false): bool {
    $f = dir_datos('rl', hash('sha256', $clave) . '.json');
    $ok = muta_json($f, function (array &$d) use ($max, $ventana) {
        $ahora = time();
        if (($d['desde'] ?? 0) + $ventana < $ahora) $d = ['desde' => $ahora, 'n' => 0, 'v' => $ventana];
        $d['n'] = ($d['n'] ?? 0) + 1;
        return $d['n'] <= $max;
    });
    if ($ok === null) return !$estricto; // disco caído: el RSVP no se bloquea; una subida sí
    return $ok !== false;
}

function registra(string $msg, array $ctx = []): void {
    try {
        asegura_dir(dir_datos('log'));
        $linea = date('c') . ' ' . $msg . ($ctx ? ' ' . json_encode($ctx, JSON_UNESCAPED_UNICODE) : '') . "\n";
        file_put_contents(dir_datos('log', 'app-' . date('Y-m') . '.log'), $linea, FILE_APPEND | LOCK_EX);
    } catch (Throwable $e) { /* el log nunca tumba la petición */ }
}

/** Clave propia de la app para firmar cookies y enlaces (se crea sola la primera vez, fuera de git). */
function clave_app(): string {
    $f = dir_datos('clave_app');
    if (!is_file($f)) {
        asegura_dir(DATA_DIR);
        @file_put_contents($f, bin2hex(random_bytes(32)), LOCK_EX);
    }
    return trim((string) file_get_contents($f));
}

function url_boda(string $slug, string $ruta = ''): string {
    return SCHEME . '://' . $slug . '.' . BASE_DOMAIN . (defined('PUERTO_DEV') ? ':' . PUERTO_DEV : '') . '/' . ltrim($ruta, '/');
}
function url_creador(string $ruta = ''): string {
    return SCHEME . '://' . CREATOR_HOST . (defined('PUERTO_DEV') ? ':' . PUERTO_DEV : '') . '/' . ltrim($ruta, '/');
}

function cabeceras_privadas(): void {
    header('Cache-Control: no-store');
    header('Referrer-Policy: no-referrer');
    header('X-Robots-Tag: noindex, nofollow');
}

/** Fecha en castellano: 2027-05-01 → "sábado, 1 de mayo de 2027". */
const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
const DIAS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado'];
function fecha_larga(string $ymd, bool $conDia = true): string {
    $t = DateTimeImmutable::createFromFormat('!Y-m-d', $ymd);
    if (!$t) return '';
    $s = (int) $t->format('j') . ' de ' . MESES[(int) $t->format('n') - 1] . ' de ' . $t->format('Y');
    return $conDia ? DIAS[(int) $t->format('w')] . ', ' . $s : $s;
}
function fecha_puntos(string $ymd): string {
    $t = DateTimeImmutable::createFromFormat('!Y-m-d', $ymd);
    return $t ? $t->format('d · m · Y') : '';
}
/** Día en que se borran los datos de invitados: fecha de la boda + MESES_ALOJAMIENTO. */
function fecha_borrado(string $ymd): string {
    $t = DateTimeImmutable::createFromFormat('!Y-m-d', $ymd);
    return $t ? $t->modify('+' . MESES_ALOJAMIENTO . ' months')->format('Y-m-d') : '';
}
function euros(int $cent): string { return number_format($cent / 100, 2, ',', '.') . ' €'; }
/** Base imponible de una boda: la única fuente del precio (el navegador nunca lo manda). */
function precio_base_cent(?array $c = null): int {
    return PRECIO_BASE_CENT + (($c['atelier'] ?? '') !== '' ? PRECIO_ATELIER_CENT : 0);
}
function con_iva(int $base): int { return $base + intdiv($base * IVA_PCT, 100); }
function precio_total_cent(?array $c = null): int { return con_iva(precio_base_cent($c)); }
