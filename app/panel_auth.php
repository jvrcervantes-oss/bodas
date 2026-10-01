<?php
// Acceso al panel de cada boda. Heredado de EduCora (_auth.php) y corregido para
// muchas bodas (Seguridad, #81): la sesión GUARDA el slug y se comprueba en cada
// petición, el nombre de la cookie es propio de cada boda, el contador de intentos
// es por boda, y la contraseña no se envía nunca: la pareja la elige con un enlace
// de un solo uso (se guarda solo el hash del token).

declare(strict_types=1);

const PANEL_MAX_INTENTOS = 5;
const PANEL_BLOQUEO = 900;           // 15 min
const PANEL_ENLACE_VIDA = 14 * 86400;
const PANEL_MIN_CLAVE = 10;
const PANEL_SESION_MAX = 8 * 3600;   // la sesión dura 8 h aunque el navegador siga abierto

function panel_fichero(string $slug): string { return dir_datos('bodas', $slug, 'panel.json'); }
function panel_dir_sesiones(): string { return dir_datos('sesiones_panel'); }

/** Nombre de la cookie de sesión del panel de esta boda (fuente única: la usa también el enlace por grupo). */
function panel_cookie(string $slug): string { return 'bw_' . substr(hash('sha256', $slug), 0, 12); }

/**
 * ¿Es la pareja con la sesión del panel abierta? Sin su cookie ni se arranca la sesión: session_start()
 * mandaría una cookie nueva a un invitado, y la web de invitados no pone cookies nuevas (Legal #133).
 */
function panel_sesion_presente(string $slug): bool { return isset($_COOKIE[panel_cookie($slug)]) && panel_autenticado($slug); }

/**
 * Las sesiones van a DATA_DIR/sesiones_panel con su propia caducidad, como las del estudio. La carpeta común
 * del hosting tiene gc_maxlifetime=1440 (24 min) y la limpia el servidor, no esta app: la promesa de 8 h no
 * dependía de nosotros. El 1-oct-2026 las parejas perdían la sesión al guardar en el editor («Sin conexión»
 * y fuera del panel); con la sesión aquí, su vida la decide PANEL_SESION_MAX.
 */
function panel_sesion(string $slug): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    asegura_dir(panel_dir_sesiones());
    session_save_path(panel_dir_sesiones());
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.gc_maxlifetime', (string) PANEL_SESION_MAX);
    // El hosting trae la limpieza automática apagada (la hace su cron sobre la carpeta común): aquí la hace PHP
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '100');
    session_name(panel_cookie($slug));
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => SCHEME === 'https', 'httponly' => true, 'samesite' => 'Strict']);
    session_start();
}

/**
 * '' si la pareja tiene la sesión abierta; si no, el motivo (sin-cookie, sin-sesion, otra-boda, caducada,
 * clave-cambiada). El motivo solo va al log: a la pareja se le dice lo mismo en todos los casos.
 */
function panel_estado_sesion(string $slug): string {
    // Sin la cookie ni se arranca la sesión: session_start() le mandaría una cookie nueva a un invitado,
    // y la web de invitados no pone cookies nuevas (Legal #133)
    if (!isset($_COOKIE[panel_cookie($slug)])) return 'sin-cookie';
    panel_sesion($slug);
    if (empty($_SESSION['ok'])) return 'sin-sesion';
    if (($_SESSION['slug'] ?? '') !== $slug) return 'otra-boda';
    if (($_SESSION['desde'] ?? 0) + PANEL_SESION_MAX < time()) { $_SESSION = []; return 'caducada'; }
    // Cambio de contraseña = se cierran las sesiones abiertas antes
    $p = lee_json(panel_fichero($slug)) ?? [];
    if (($_SESSION['gen'] ?? -1) !== ($p['gen'] ?? 0)) { $_SESSION = []; return 'clave-cambiada'; }
    return '';
}

function panel_autenticado(string $slug): bool { return panel_estado_sesion($slug) === ''; }

function panel_entra(string $slug): void {
    panel_sesion($slug);
    session_regenerate_id(true);
    $p = lee_json(panel_fichero($slug)) ?? [];
    $_SESSION = ['slug' => $slug, 'ok' => true, 'desde' => time(), 'gen' => $p['gen'] ?? 0, 'csrf' => bin2hex(random_bytes(16))];
}

function panel_sal(string $slug): void {
    panel_sesion($slug);
    $_SESSION = [];
    session_destroy();
}

function panel_csrf(): string { return (string) ($_SESSION['csrf'] ?? ''); }
function panel_csrf_ok(): bool {
    $t = (string) ($_POST['csrf'] ?? $_SERVER['HTTP_X_CSRF'] ?? '');
    return $t !== '' && hash_equals(panel_csrf(), $t);
}

function panel_tiene_clave(string $slug): bool {
    return (string) ((lee_json(panel_fichero($slug)) ?? [])['hash'] ?? '') !== '';
}

/** Devuelve '' si entra, o el motivo. El contador es de esta boda y de esta IP a la vez. */
function panel_login(string $slug, string $clave): string {
    if (!limite('login-ip|' . ip_cliente(), 30, 3600)) return 'Demasiados intentos desde tu conexión. Prueba dentro de una hora.';
    $res = muta_json(panel_fichero($slug), function (array &$p) use ($clave) {
        $ahora = time();
        if (($p['bloqueo'] ?? 0) > $ahora) return 'bloqueado';
        $hash = (string) ($p['hash'] ?? '');
        if ($hash !== '' && password_verify($clave, $hash)) { $p['intentos'] = 0; return 'ok'; }
        $p['intentos'] = ($p['intentos'] ?? 0) + 1;
        if ($p['intentos'] >= PANEL_MAX_INTENTOS) { $p['bloqueo'] = $ahora + PANEL_BLOQUEO; $p['intentos'] = 0; }
        return 'mal';
    });
    if ($res === 'ok') { panel_entra($slug); return ''; }
    if ($res === 'bloqueado') return 'Demasiados intentos fallidos. Vuelve a probar en 15 minutos.';
    return 'Contraseña incorrecta.';
}

/** Crea un enlace de un solo uso para elegir contraseña. Solo se guarda el hash. */
function panel_nuevo_enlace(string $slug): string {
    $tok = bin2hex(random_bytes(24));
    muta_json(panel_fichero($slug), function (array &$p) use ($tok) {
        $vivos = array_values(array_filter($p['enlaces'] ?? [], fn($e) => ($e['hasta'] ?? 0) > time()));
        $vivos[] = ['h' => hash('sha256', $tok), 'hasta' => time() + PANEL_ENLACE_VIDA];
        $p['enlaces'] = array_slice($vivos, -5);
    });
    return url_boda($slug, 'panel/clave?t=' . $tok);
}

function panel_enlace_valido(string $slug, string $tok): bool {
    if (!preg_match('/^[a-f0-9]{48}$/', $tok)) return false;
    $h = hash('sha256', $tok);
    foreach ((lee_json(panel_fichero($slug)) ?? [])['enlaces'] ?? [] as $e) {
        if (hash_equals($e['h'], $h) && $e['hasta'] > time()) return true;
    }
    return false;
}

/** Fija la contraseña consumiendo el enlace. Todos los enlaces vivos se anulan. */
function panel_fija_clave(string $slug, string $tok, string $clave): string {
    if (mb_strlen($clave, 'UTF-8') < PANEL_MIN_CLAVE) return 'La contraseña tiene que tener al menos ' . PANEL_MIN_CLAVE . ' caracteres.';
    if (!panel_enlace_valido($slug, $tok)) return 'Este enlace ya se ha usado o ha caducado. Pide otro desde la página de acceso.';
    muta_json(panel_fichero($slug), function (array &$p) use ($clave) {
        $p['hash'] = password_hash($clave, PASSWORD_DEFAULT);
        $p['enlaces'] = [];
        $p['intentos'] = 0;
        $p['bloqueo'] = 0;
        $p['gen'] = ($p['gen'] ?? 0) + 1;
    });
    panel_entra($slug);
    return '';
}
