<?php
// /agents: el escaparate del panel V2 del AaaS de BodaEnlace (16-oct-2026, sesión aaas-agents; demo del owner del 17-oct).
//
// Qué es: una FOTO de solo lectura de lo que ve el dueño en el panel del AaaS, que sube el PC cada 30 min. Aquí no hay
// backend del AaaS al que pedir nada: la página pinta la foto y cualquier botón contesta «se para desde el ERP».
//
// Superficie (reducir la exposición, contexto/seguridad_2026.md §1.ter), cada pieza con su llamador:
//   GET  /agents/          el owner, con sesión → la plantilla con la foto dentro; sin sesión → 401 con el formulario y NINGÚN dato
//   POST /agents/          el formulario de entrada (contraseña + CSRF, freno de 5 fallos por IP cada 15 min)
//   GET  /agents/salir     el enlace «Salir» que se mete en la página (token de la sesión en la URL; ver agents_salir)
//   POST /agents/sube.php  erp/aaas_local/sube_web.py del PC, con su llave (Bearer). ÚNICO punto de escritura
//   Todo lo demás bajo /agents/ es 404. No hay carpeta pública agents/: el .htaccess manda todo a index.php y el código vive
//   en app/, que ya da 404 desde fuera (por eso no hay listado de directorio posible).
//
// Por qué la plantilla y la foto NO van al repo: la plantilla es el código del ERP y el repo de Bodas es PÚBLICO; la foto es lo
// que ve el dueño. Las dos viven en DATA_DIR/agents/, fuera del webroot, y solo se sirven con sesión.
// Las dos HUELLAS viven en el repo, en app/agents_huellas.php, y suben por el despliegue normal (17-oct-2026). El paso por ssh a
// secrets.php lo denegó el clasificador y no se rodea. Seguridad las dio por aceptables en un repo público porque son huellas de
// secretos ALEATORIOS de máquina (2^144 y 2^256 intentos). Precedente: app/estudio_alta.php, revisión previa #96.
//   AGENTS_CLAVE_SHA256   sha256 de la contraseña del owner (token de máquina de 144 bits)
//   AGENTS_SUBIDA_SHA256  sha256 de la llave de subida del PC
// Las dos son sha256 y no bcrypt: el detector de secretos del push frena el bcrypt (con él, una contraseña elegida por una
// persona se prueba sin conexión) y contra un secreto de 144 bits un hash lento no añade nada. Lo que lo mantiene cierto es que
// erp/aaas_local/sube_web.py se niega a escribir la huella de una contraseña que no sea de máquina (24+ caracteres urlsafe).
// UNA sola fuente: secrets.php ya no cuenta para estas dos claves (una copia vieja allí ganaría en silencio a una rotación por push).
// Si el fichero falta, si una huella va vacía o no tiene su forma, queda cerrado: nadie entra y nada se sube.

declare(strict_types=1);

const AGENTS_HUECO = '/*AXW_FOTO*/';
const AGENTS_MAX_BYTES = 3 * 1024 * 1024;       // cuerpo de la subida; el /agente/ con todo metido dentro mide ~1-2 MB
const AGENTS_SESION_MAX = 12 * 3600;            // la sesión caduca a las 12 h de entrar, se use o no
const AGENTS_FALLOS_LOGIN = 5;                  // contraseñas malas por IP...
const AGENTS_VENTANA_LOGIN = 900;               // ...cada 15 min → 429
const AGENTS_FALLOS_SUBIDA = 10;                // llaves malas por IP cada hora → 429
const AGENTS_SUBIDAS_HORA = 20;                 // subidas buenas por IP y hora (el PC manda 2)
// Forma de llave: el cuerpo de una llave real, no su prefijo. El /agente/ de verdad lleva «sk-ant-…» como placeholder y en la
// ayuda («empieza por sk-ant-»), medido el 16-oct en el dist del ERP: con el prefijo a secas se rechazaría la página buena.
const AGENTS_LLAVES = [
    'Anthropic' => '/sk-ant-[A-Za-z0-9_\-]{20,}/',
    'Supabase secreta' => '/sb_secret_[A-Za-z0-9_\-]{20,}/',
    'GitHub' => '/github_pat_[A-Za-z0-9_]{30,}|\bghp_[A-Za-z0-9]{30,}/',
    'clave privada' => '/-----BEGIN [A-Z ]*PRIVATE KEY-----/',
    'Stripe' => '/\b[sr]k_live_[A-Za-z0-9]{20,}/',
];

if (!defined('AGENTS_HUELLAS_F')) define('AGENTS_HUELLAS_F', __DIR__ . '/agents_huellas.php');   // las pruebas lo apuntan a su copia
const AGENTS_HUELLAS_FORMA = [
    'AGENTS_CLAVE_SHA256' => '/^[a-f0-9]{64}$/',
    'AGENTS_SUBIDA_SHA256' => '/^[a-f0-9]{64}$/',
];

/** Una de las dos huellas de app/agents_huellas.php, solo si tiene su forma; si no, '' (cerrado). */
function agents_huella(string $k): string {
    static $h = null;
    if ($h === null) {
        $d = is_file(AGENTS_HUELLAS_F) ? require AGENTS_HUELLAS_F : [];
        $h = is_array($d) ? $d : [];
    }
    $v = $h[$k] ?? '';
    return is_string($v) && isset(AGENTS_HUELLAS_FORMA[$k]) && preg_match(AGENTS_HUELLAS_FORMA[$k], $v) === 1 ? $v : '';
}

function agents_dir(string ...$p): string { return dir_datos('agents', ...$p); }
function agents_url(string $sub = ''): string { return BASE_PATH . '/agents/' . $sub; }

function rutas_agents(string $sub, string $metodo): void {
    agents_cabeceras();
    if ($sub === 'sube.php') { agents_sube($metodo); return; }
    if ($sub === 'salir') { agents_salir($metodo); return; }
    if ($sub !== '') { agents_texto(404, 'No existe.'); return; }
    if (!in_array($metodo, ['GET', 'HEAD', 'POST'], true)) { header('Allow: GET, POST'); agents_texto(405, 'Método no permitido.'); return; }
    agents_sesion();
    if ($metodo === 'POST') { agents_login(); return; }
    if (!agents_dentro()) { agents_formulario(401, ''); return; }
    agents_panel();
}

/** Las de toda respuesta de /agents. La CSP de aquí es la estrecha (sin scripts); la del panel la sustituye. */
function agents_cabeceras(): void {
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: private, no-store');      // ni Cloudflare ni LiteSpeed la guardan
    header('Referrer-Policy: no-referrer');
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Cross-Origin-Opener-Policy: same-origin');
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
}

function agents_texto(int $st, string $msg): void {
    http_response_code($st);
    header('Content-Type: text/plain; charset=utf-8');
    echo $msg;
}

function agents_json(int $st, array $d): void {
    http_response_code($st);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
}

/** Registro de quién entra y quién sube: la IP va como hash corto (como estudio_log), nunca la contraseña ni la llave. */
function agents_log(string $accion, array $datos = []): void {
    try {
        asegura_dir(agents_dir());
        file_put_contents(agents_dir('log-' . date('Y-m') . '.jsonl'),
            json_encode(['fecha' => date('c'), 'accion' => $accion, 'ip' => substr(hash_hmac('sha256', ip_cliente(), clave_app()), 0, 12)] + $datos,
                JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
    } catch (Throwable $e) { /* el registro nunca tumba la petición */ }
}

// ── sesión ────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
function agents_sesion(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    asegura_dir(agents_dir('sesiones'));
    session_save_path(agents_dir('sesiones'));
    ini_set('session.use_strict_mode', '1');      // un id inventado por el cliente no se acepta (fijación de sesión)
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.gc_maxlifetime', (string) AGENTS_SESION_MAX);
    // Cada visita anónima abre una sesión (la del CSRF del formulario): la carpeta es nuestra y se barre sola, sin esperar a un cron
    ini_set('session.gc_probability', '1');
    ini_set('session.gc_divisor', '100');
    session_cache_limiter('');                    // si no, session_start pisa nuestro Cache-Control con el suyo
    $https = SCHEME === 'https';
    // Cookie propia con Path=/agents: el prefijo __Host- exige Path=/, así que es __Secure- (exige Secure, que es lo que vale aquí)
    session_name($https ? '__Secure-axw_agents' : 'axw_agents');
    session_set_cookie_params(['lifetime' => AGENTS_SESION_MAX, 'path' => BASE_PATH . '/agents', 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict']);
    session_start();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

/** Huella del hash vigente: si se rota la contraseña (agents_huellas.php nuevo por push), todas las sesiones abiertas caen. */
function agents_gen(string $hash): string { return substr(hash('sha256', 'agents|' . $hash), 0, 16); }

function agents_dentro(): bool {
    $hash = agents_huella('AGENTS_CLAVE_SHA256');
    $s = $_SESSION;
    if ($hash === '' || empty($s['ok'])) return false;
    if ((int) ($s['desde'] ?? 0) + AGENTS_SESION_MAX < time() || !hash_equals(agents_gen($hash), (string) ($s['gen'] ?? ''))) {
        $_SESSION = ['csrf' => bin2hex(random_bytes(16))];
        return false;
    }
    return true;
}

function agents_csrf_ok(): bool {
    $t = (string) ($_POST['csrf'] ?? '');
    return $t !== '' && hash_equals((string) ($_SESSION['csrf'] ?? ''), $t);
}

/**
 * Freno en disco por clave (ya lleva la IP): devuelve cuántos fallos hay en la ventana, sumando uno si $suma. El nombre del
 * fichero es un HMAC con la clave de la app (un sha256 de la IP se revierte recorriendo IPv4). Si el disco no responde, falla
 * en CERRADO: devuelve PHP_INT_MAX y la petición se frena.
 */
function agents_fallos(string $clave, int $ventana, bool $suma = false): int {
    $f = agents_dir('fallos', hash_hmac('sha256', $clave, clave_app()) . '.json');
    $n = muta_json($f, function (array &$d) use ($ventana, $suma) {
        $ahora = time();
        $d['t'] = array_values(array_filter((array) ($d['t'] ?? []), fn($t) => is_int($t) && $t > $ahora - $ventana));
        if ($suma) $d['t'][] = $ahora;
        return count($d['t']);
    });
    return is_int($n) ? $n : PHP_INT_MAX;
}

function agents_login(): void {
    $hash = agents_huella('AGENTS_CLAVE_SHA256');
    if (!agents_csrf_ok()) { agents_formulario(403, 'La página ha caducado. Vuelve a intentarlo.'); return; }
    $clave = 'agents-entrar|' . ip_cliente();
    if (agents_fallos($clave, AGENTS_VENTANA_LOGIN) >= AGENTS_FALLOS_LOGIN) {
        agents_log('entrar-frenado');
        header('Retry-After: ' . AGENTS_VENTANA_LOGIN);
        agents_formulario(429, 'Demasiados intentos. Prueba dentro de 15 minutos.');
        return;
    }
    $pw = (string) ($_POST['clave'] ?? '');
    if ($hash !== '' && $pw !== '' && strlen($pw) <= 256 && hash_equals($hash, hash('sha256', $pw))) {
        session_regenerate_id(true);
        $_SESSION = ['ok' => true, 'desde' => time(), 'gen' => agents_gen($hash), 'csrf' => bin2hex(random_bytes(16))];
        agents_log('entrar');
        header('Location: ' . agents_url(), true, 303);
        return;
    }
    agents_fallos($clave, AGENTS_VENTANA_LOGIN, true);
    agents_log($hash === '' ? 'entrar-sin-clave-configurada' : 'entrar-fallo');
    agents_formulario(401, 'Contraseña incorrecta.');
}

/**
 * Salir es un ENLACE (GET con el token en la URL), no un formulario: la plantilla del V2 trae su propia CSP en una <meta> con
 * form-action 'none', y el navegador aplica las dos políticas a la vez, así que un formulario de salir quedaba bloqueado (medido en
 * Edge el 16-oct). Sigue protegido: hace falta el token de la sesión, y la cookie es SameSite=Strict (otra web no la manda).
 */
function agents_salir(string $metodo): void {
    if ($metodo !== 'GET' && $metodo !== 'POST') { header('Allow: GET, POST'); agents_texto(405, 'Método no permitido.'); return; }
    agents_sesion();
    $t = (string) ($_GET['csrf'] ?? $_POST['csrf'] ?? '');
    if ($t === '' || !hash_equals((string) ($_SESSION['csrf'] ?? ''), $t)) { agents_formulario(403, 'La página ha caducado. Vuelve a intentarlo.'); return; }
    agents_log('salir');
    $_SESSION = [];
    session_destroy();
    setcookie(session_name(), '', ['expires' => 1, 'path' => BASE_PATH . '/agents', 'secure' => SCHEME === 'https', 'httponly' => true, 'samesite' => 'Strict']);
    header('Location: ' . agents_url(), true, 303);
}

/** La página de entrada: solo el formulario. Ningún dato del panel, ni el nombre del negocio. Sin JavaScript. */
function agents_formulario(int $st, string $error): void {
    http_response_code($st);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<meta name="robots" content="noindex,nofollow"><title>Acceso</title><style>'
        . 'body{font:16px/1.5 system-ui,sans-serif;margin:0;min-height:100vh;display:grid;place-items:center;background:#F6F5F2;color:#1B1C1E}'
        . 'form{display:grid;gap:12px;width:min(320px,calc(100vw - 32px))}h1{font-weight:500;font-size:20px;margin:0 0 4px}'
        . 'input{font:inherit;padding:10px 12px;border:1px solid #C9C6BF;border-radius:8px;background:#fff}'
        . 'button{font:inherit;padding:10px 12px;border:0;border-radius:8px;background:#1B1C1E;color:#fff;cursor:pointer}'
        . '.e{color:#A2261A;margin:0}</style></head><body>'
        . '<form method="post" action="' . h(agents_url()) . '"><h1>Acceso</h1>'
        . '<input type="hidden" name="csrf" value="' . h($_SESSION['csrf'] ?? '') . '">'
        . '<label for="c">Contraseña</label><input id="c" type="password" name="clave" autocomplete="current-password" required autofocus>'
        . ($error !== '' ? '<p class="e" role="alert">' . h($error) . '</p>' : '')
        . '<button type="submit">Entrar</button></form></body></html>';
}

// ── panel ─────────────────────────────────────────────────────────────────────────────────────────────────────────────────────
/** Lee plantilla y foto a la vez, bajo el mismo cerrojo que usa la subida: nunca una plantilla nueva con una foto vieja. */
function agents_lee(): array {
    $pl = agents_dir('plantilla.html');
    $fo = agents_dir('foto.json');
    if (!is_file($pl) || !is_file($fo)) return [null, null];
    $lk = @fopen(agents_dir('.cerrojo'), 'c');
    if ($lk !== false) flock($lk, LOCK_SH);
    $plantilla = @file_get_contents($pl);
    $foto = @file_get_contents($fo);
    if ($lk !== false) { flock($lk, LOCK_UN); fclose($lk); }
    $d = is_string($foto) ? json_decode($foto, true) : null;
    return [is_string($plantilla) && $plantilla !== '' ? $plantilla : null, is_array($d) ? $d : null];
}

function agents_panel(): void {
    [$plantilla, $foto] = agents_lee();
    // data-axw-fuera: la plantilla del V2 deja quietos todos los enlaces (es una sola página y los del menú irían a pantallas que aquí
    // no existen) salvo los que llevan esta marca; sin ella el clic en Salir no hacía nada (medido en Edge el 16-oct).
    $salir = '<a data-axw-fuera href="' . h(agents_url('salir') . '?csrf=' . rawurlencode((string) ($_SESSION['csrf'] ?? ''))) . '" '
        . 'style="position:fixed;right:12px;bottom:12px;z-index:2147483647;font:14px system-ui,sans-serif;padding:8px 14px;border-radius:999px;'
        . 'background:#1B1C1E;color:#fff;text-decoration:none;box-shadow:0 2px 8px rgba(0,0,0,.25)">Salir</a>';
    $pos = $plantilla === null ? false : strpos($plantilla, AGENTS_HUECO);
    if ($plantilla === null || $foto === null || $pos === false) {
        http_response_code(503);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="robots" content="noindex,nofollow"><title>Sin foto</title></head>'
            . '<body style="font:16px/1.5 system-ui,sans-serif;margin:32px 16px"><p>Aún no ha llegado ninguna foto del panel. La sube el equipo cada 30 minutos.</p>' . $salir . '</body></html>';
        return;
    }
    // El nonce solo va a los <script> EN LÍNEA de la plantilla (nuestros): uno con src no lo lleva y la CSP lo bloquea, así que la
    // página tiene que ser autocontenida. Se pone ANTES de meter la foto, y la foto va con < > & escapados (JSON_HEX_TAG/AMP): un
    // texto de la foto (los hallazgos del Cazador vienen de webs ajenas) no puede cerrar el <script> ni abrir uno.
    $nonce = rtrim(strtr(base64_encode(random_bytes(18)), '+/', '-_'), '=');
    $antes = preg_replace('/<script\b(?![^>]*\bsrc\s*=)/i', '<script nonce="' . $nonce . '"', substr($plantilla, 0, $pos));
    $despues = preg_replace('/<script\b(?![^>]*\bsrc\s*=)/i', '<script nonce="' . $nonce . '"', substr($plantilla, $pos + strlen(AGENTS_HUECO)));
    $json = json_encode($foto, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    // preg_replace devuelve null si se queda sin pila: mejor un 500 que una página en blanco que parece «sin datos»
    if (!is_string($antes) || !is_string($despues) || $json === false) { agents_texto(500, 'La foto no se puede pintar.'); return; }
    // Concatenación, nunca preg_replace con la foto: un «$1» o una barra del JSON se reinterpretarían
    $html = $antes . 'window.AXW_FOTO = ' . $json . ';' . $despues;
    $fin = strripos($html, '</body>');
    $html = $fin === false ? $html . $salir : substr($html, 0, $fin) . $salir . substr($html, $fin);
    header("Content-Security-Policy: default-src 'none'; script-src 'nonce-" . $nonce . "'; style-src 'unsafe-inline' https://fonts.googleapis.com; "
        . "font-src https://fonts.gstatic.com data:; img-src 'self' data:; connect-src 'none'; form-action 'self'; frame-ancestors 'none'; "
        . "base-uri 'none'; object-src 'none'");
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    echo $html;
}

// ── subida (el PC) ────────────────────────────────────────────────────────────────────────────────────────────────────────────
/** ¿El hueco cae DENTRO de un <script> en línea? Si no, la foto acabaría como texto o atributo HTML, no como dato de JS. */
function agents_hueco_en_script(string $pl): bool {
    $pos = strpos($pl, AGENTS_HUECO);
    if ($pos === false) return false;
    $antes = substr($pl, 0, $pos);
    $abre = strripos($antes, '<script');
    if ($abre === false) return false;
    $cierra = strripos($antes, '</script');
    if ($cierra !== false && $cierra > $abre) return false;
    $etiqueta = substr($antes, $abre, (int) strpos($antes . '>', '>', $abre) - $abre);
    return !preg_match('/\bsrc\s*=/i', $etiqueta) && stripos(substr($pl, $pos), '</script') !== false;
}

/** El tipo de llave que aparece en el texto, o '' si no hay ninguna. Nunca devuelve el valor. */
function agents_forma_llave(string $t): string {
    foreach (AGENTS_LLAVES as $tipo => $re) if (preg_match($re, $t)) return $tipo;
    return '';
}

function agents_es_objeto($v): bool {
    if (!is_array($v) || $v === []) return false;
    return array_keys($v) !== range(0, count($v) - 1);   // array_is_list sin depender de PHP 8.1
}

function agents_sube(string $metodo): void {
    if ($metodo !== 'POST') { header('Allow: POST'); agents_json(405, ['ok' => false, 'error' => 'Solo POST.']); return; }
    $ipk = 'agents-sube-fallo|' . ip_cliente();
    if (agents_fallos($ipk, 3600) >= AGENTS_FALLOS_SUBIDA) {
        header('Retry-After: 3600');
        agents_json(429, ['ok' => false, 'error' => 'Demasiados intentos.']);
        return;
    }
    // La llave se mira ANTES de leer el cuerpo: sin ella no se parsea nada
    $esperado = agents_huella('AGENTS_SUBIDA_SHA256');
    $auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (!preg_match('/^[a-f0-9]{64}$/', $esperado) || !preg_match('/^Bearer ([A-Za-z0-9_\-]{32,200})$/', $auth, $m)
        || !hash_equals($esperado, hash('sha256', $m[1]))) {
        agents_fallos($ipk, 3600, true);
        agents_log('subida-sin-llave', ['cabecera' => $auth !== '']);
        header('WWW-Authenticate: Bearer');
        agents_json(401, ['ok' => false, 'error' => 'Sin autorización.']);
        return;
    }
    if (!limite('agents-sube|' . ip_cliente(), AGENTS_SUBIDAS_HORA, 3600, true)) {
        header('Retry-After: 3600');
        agents_json(429, ['ok' => false, 'error' => 'Demasiadas subidas en una hora.']);
        return;
    }
    if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > AGENTS_MAX_BYTES) { agents_json(413, ['ok' => false, 'error' => 'Más de 3 MB.']); return; }
    $raw = (string) file_get_contents('php://input', false, null, 0, AGENTS_MAX_BYTES + 1);
    if (strlen($raw) > AGENTS_MAX_BYTES) { agents_json(413, ['ok' => false, 'error' => 'Más de 3 MB.']); return; }
    $d = json_decode($raw, true);
    if (!is_array($d)) { agents_json(400, ['ok' => false, 'error' => 'El cuerpo no es JSON válido.']); return; }
    $foto = $d['foto'] ?? null;
    $pl = $d['plantilla'] ?? null;
    $mal = function (string $e) { agents_log('subida-rechazada', ['motivo' => $e]); agents_json(422, ['ok' => false, 'error' => $e]); };
    if (!agents_es_objeto($foto)) { $mal('«foto» tiene que ser un objeto JSON con datos.'); return; }
    if (!is_string($pl) || $pl === '') { $mal('«plantilla» tiene que ser el HTML de la página.'); return; }
    if (substr_count($pl, AGENTS_HUECO) !== 1) { $mal('La plantilla tiene que llevar exactamente un ' . AGENTS_HUECO . '.'); return; }
    if (!agents_hueco_en_script($pl)) { $mal('El hueco de la foto tiene que estar dentro de un <script> en línea.'); return; }
    $fotoTxt = json_encode($foto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($fotoTxt === false) { $mal('La foto no se puede volver a escribir como JSON.'); return; }
    $llave = agents_forma_llave($pl . "\n" . $fotoTxt);
    if ($llave !== '') { $mal('Lleva algo con forma de llave (' . $llave . '). No se guarda nada.'); return; }
    agents_guarda($pl, $fotoTxt);
    agents_log('subida', ['plantilla' => strlen($pl), 'foto' => strlen($fotoTxt), 'sha' => substr(hash('sha256', $pl . $fotoTxt), 0, 12)]);
    agents_json(200, ['ok' => true, 'plantilla' => strlen($pl), 'foto' => strlen($fotoTxt)]);
}

/** Escritura atómica de las dos piezas (temporal + rename) bajo el cerrojo exclusivo: quien lee nunca ve una pareja a medias. */
function agents_guarda(string $pl, string $fotoTxt): void {
    asegura_dir(agents_dir());
    $sufijo = '.' . bin2hex(random_bytes(4)) . '.tmp';
    $tmpP = agents_dir('plantilla.html' . $sufijo);
    $tmpF = agents_dir('foto.json' . $sufijo);
    $vieja = umask(0077);
    try {
        if (file_put_contents($tmpP, $pl) !== strlen($pl) || file_put_contents($tmpF, $fotoTxt) !== strlen($fotoTxt)) {
            @unlink($tmpP); @unlink($tmpF);
            throw new RuntimeException('agents: escritura incompleta');
        }
        $lk = fopen(agents_dir('.cerrojo'), 'c');
        if ($lk === false || !flock($lk, LOCK_EX)) { @unlink($tmpP); @unlink($tmpF); throw new RuntimeException('agents: sin cerrojo'); }
        try {
            if (!rename($tmpP, agents_dir('plantilla.html')) || !rename($tmpF, agents_dir('foto.json'))) {
                @unlink($tmpP); @unlink($tmpF);
                throw new RuntimeException('agents: no se pudo reemplazar');
            }
        } finally { flock($lk, LOCK_UN); fclose($lk); }
    } finally { umask($vieja); }
}
