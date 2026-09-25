<?php
// Galería (fotos de la pareja) y libro de invitados (mensajes y fotos de invitados).
//
// Decisiones del owner (25-sep-2026): el libro publica al momento (sin moderación previa)
// y galería + libro van detrás de un código de acceso por boda. Revisión previa #87:
//  - "Privado con enlace" no es privado (el slug se adivina y sale en los registros de
//    certificados): por eso el código, que se exige para VER y para ESCRIBIR.
//  - Las fotos se sirven comprobando cada vez que existen, no están ocultas y la boda no
//    está archivada; sin caché pública y con noimageindex.
//  - Subidas anónimas: límites por IP y por boda que fallan en cerrado, cuotas antes de
//    decodificar, tope de píxeles y espacio libre en disco.
//  - Legal: enlace de retirada visible, la pareja oculta o borra desde el panel, y el
//    estudio retira sin esperar ante un aviso fundado.

declare(strict_types=1);

const MAX_LIBRO_ENTRADAS = 300;
const MAX_LIBRO_FOTOS = 150;
const MAX_LIBRO_BYTES = 150 * 1024 * 1024;
const DISCO_MINIMO = 500 * 1024 * 1024;

// ---------------------------------------------------------------- acceso por código

function cookie_acceso(string $slug): string { return 'bwa_' . substr(hash('sha256', $slug), 0, 12); }
function token_acceso(string $slug, string $codigo): string {
    return hash_hmac('sha256', 'acceso|' . $slug . '|' . mb_strtolower($codigo, 'UTF-8'), clave_app());
}

/** ¿Puede ver la galería y el libro? El código entra en la firma: si la pareja lo cambia, se pide otra vez. */
function acceso_ok(string $slug, array $c): bool {
    if ($c['codigo'] === '') return true;
    $t = (string) ($_COOKIE[cookie_acceso($slug)] ?? '');
    return $t !== '' && hash_equals(token_acceso($slug, $c['codigo']), $t);
}

/** POST /acceso: valida el código y deja una cookie técnica 60 días. Límite estricto por IP y boda. */
function api_acceso(string $slug, array $c, string $metodo): void {
    if ($metodo !== 'POST') { http_response_code(405); exit; }
    $volver = (string) ($_POST['volver'] ?? '');
    $volver = preg_match('~^/[a-z0-9-]*$~', $volver) ? $volver : '/';
    if (!limite('acceso|' . $slug . '|' . ip_cliente(), 10, 3600, true)) {
        header('Location: ' . $volver . '?codigo=espera', true, 303); exit;
    }
    $cod = clean_str($_POST['codigo'] ?? '', 40);
    if ($c['codigo'] !== '' && hash_equals(mb_strtolower($c['codigo'], 'UTF-8'), mb_strtolower($cod, 'UTF-8'))) {
        setcookie(cookie_acceso($slug), token_acceso($slug, $c['codigo']), [
            'expires' => time() + 60 * 86400, 'path' => '/', 'secure' => SCHEME === 'https', 'httponly' => true, 'samesite' => 'Lax',
        ]);
        header('Location: ' . $volver, true, 303); exit;
    }
    header('Location: ' . $volver . '?codigo=mal', true, 303); exit;
}

/** Firma corta para las imágenes de la vista previa del panel (el iframe aislado no manda cookies). */
function firma_img(string $slug, string $id, int $dia = 0): string {
    return substr(hash_hmac('sha256', 'img|' . $slug . '|' . $id . '|' . gmdate('Ymd', time() - $dia * 86400), clave_app()), 0, 20);
}
function firma_img_ok(string $slug, string $id, string $t): bool {
    return $t !== '' && (hash_equals(firma_img($slug, $id), $t) || hash_equals(firma_img($slug, $id, 1), $t));
}

/** Sirve una imagen de galería o libro: sin caché compartida y fuera de buscadores de imágenes. */
function sirve_img_privada(string $ruta): void {
    if (!is_file($ruta)) { http_response_code(404); exit; }
    header('Content-Type: image/webp');
    header('Content-Length: ' . filesize($ruta));
    header('Cache-Control: private, no-store');
    header('X-Robots-Tag: noindex, noimageindex');
    header('X-Content-Type-Options: nosniff');
    readfile($ruta);
    exit;
}

function puede_ver_img(string $slug, array $c, string $id): bool {
    if (acceso_ok($slug, $c)) return true;
    if (firma_img_ok($slug, $id, (string) ($_GET['t'] ?? ''))) return true;
    return function_exists('panel_autenticado') && panel_autenticado($slug);
}

// ---------------------------------------------------------------- galería (la pareja)

function dir_galeria(string $slug): string { return dir_boda($slug) . '/galeria'; }

function sirve_galeria(string $slug, array $c, string $id): void {
    $g = seccion_tipo($c, 'galeria');
    $ids = $g ? array_column($g['datos']['fotos'], 'id') : [];
    if (!preg_match('/^[a-f0-9]{16}$/', $id) || !in_array($id, $ids, true) || !puede_ver_img($slug, $c, $id)) { http_response_code(404); exit; }
    sirve_img_privada(dir_galeria($slug) . '/' . $id . '.webp');
}

/** POST /panel/galeria: sube UNA foto y la añade al config. */
function panel_galeria_subir(string $slug): void {
    if (!panel_csrf_ok()) json_response(['ok' => false, 'error' => 'La sesión ha caducado. Recarga la página.'], 403);
    if (!limite('galeria|' . $slug, 60, 3600, true)) json_response(['ok' => false, 'error' => 'Demasiadas fotos seguidas. Espera un poco.'], 429);
    if (@disk_free_space(DATA_DIR) !== false && disk_free_space(DATA_DIR) < DISCO_MINIMO) {
        registra('ALERTA disco casi lleno: subida de galería rechazada', ['slug' => $slug]);
        json_response(['ok' => false, 'error' => 'Ahora no podemos guardar fotos. Inténtalo más tarde.'], 507);
    }
    $f = dir_boda($slug) . '/config.json';
    $res = con_cerrojo(function () use ($slug, $f) {
        $raw = lee_json($f) ?? [];
        $c = normaliza_config($raw) + ['_estado' => $raw['_estado'] ?? 'activa'];
        $gi = null;
        foreach ($c['secciones'] as $i => $s) if ($s['tipo'] === 'galeria') $gi = $i;
        if ($gi === null) return ['error' => 'La galería no está disponible.'];
        if (!$c['secciones'][$gi]['datos']['consentido'] && ($_POST['consentido'] ?? '') !== 'si') return ['error' => 'Marcad la casilla de permisos antes de subir fotos.'];
        if (count($c['secciones'][$gi]['datos']['fotos']) >= MAX_GALERIA) return ['error' => 'La galería admite ' . MAX_GALERIA . ' fotos como máximo.'];
        $id = bin2hex(random_bytes(8));
        $r = guarda_foto('foto', dir_galeria($slug) . '/' . $id . '.webp', 8 * 1024 * 1024, 40000000, 1600);
        if ($r !== '') return ['error' => $r === 'sin-foto' ? 'No ha llegado ninguna foto.' : $r];
        $c['secciones'][$gi]['datos']['fotos'][] = ['id' => $id, 'pie' => ''];
        $c['secciones'][$gi]['datos']['consentido'] = true;
        escribe_json($f, $c);
        return ['id' => $id];
    });
    if (isset($res['error'])) json_response(['ok' => false, 'error' => $res['error']], 422);
    json_response(['ok' => true, 'id' => $res['id'], 'src' => '/g/' . $res['id'] . '.webp?t=' . firma_img($slug, $res['id'])]);
}

/** Tras guardar el config: borra del disco las fotos que ya no están en la galería. */
function galeria_limpia_huerfanas(string $slug, array $c): void {
    // Aunque la galería esté apagada, sus fotos se conservan (la pareja puede volver a encenderla)
    $ids = [];
    foreach ($c['secciones'] as $s) if ($s['tipo'] === 'galeria') $ids = array_column($s['datos']['fotos'], 'id');
    foreach (glob(dir_galeria($slug) . '/*.webp') ?: [] as $file) {
        // Solo las de más de 10 min: una subida en curso aún no está en el config que llega del navegador
        if (!in_array(basename($file, '.webp'), $ids, true) && filemtime($file) < time() - 600) @unlink($file);
    }
}

/** Solo conserva las fotos cuyo fichero existe (el navegador no puede inventarse ids). */
function galeria_filtra_existentes(string $slug, array $c): array {
    foreach ($c['secciones'] as &$s) {
        if ($s['tipo'] !== 'galeria') continue;
        $s['datos']['fotos'] = array_values(array_filter($s['datos']['fotos'], fn($f) => is_file(dir_galeria($slug) . '/' . $f['id'] . '.webp')));
    }
    unset($s);
    return $c;
}

// ---------------------------------------------------------------- libro de invitados

function dir_libro(string $slug): string { return dir_boda($slug) . '/libro'; }
function libro_entradas(string $slug): array { return lee_json(dir_libro($slug) . '/libro.json') ?? []; }

function sirve_libro_foto(string $slug, array $c, string $id): void {
    if (!preg_match('/^[a-f0-9]{16}$/', $id) || !seccion_tipo($c, 'libro')) { http_response_code(404); exit; }
    $e = array_values(array_filter(libro_entradas($slug), fn($x) => ($x['foto'] ?? '') === $id))[0] ?? null;
    $panel = function_exists('panel_autenticado') && panel_autenticado($slug);
    if (!$e || (!empty($e['oculto']) && !$panel) || !puede_ver_img($slug, $c, $id)) { http_response_code(404); exit; }
    sirve_img_privada(dir_libro($slug) . '/' . $id . '.webp');
}

function bytes_dir(string $d): int {
    $t = 0;
    foreach (glob($d . '/*') ?: [] as $f) $t += (int) @filesize($f);
    return $t;
}

/** POST /api/libro: mensaje (y foto opcional) de un invitado con el código de la boda. */
function api_libro(string $slug, array $c, string $metodo): void {
    if ($metodo !== 'POST') json_response(['ok' => false], 405);
    $s = seccion_tipo($c, 'libro');
    if (!$s) json_response(['ok' => false], 404);
    if (!acceso_ok($slug, $c)) json_response(['ok' => false, 'error' => 'Hace falta el código de la boda.'], 403);
    // Sin CSRF (es anónimo): al menos, que el envío venga de la propia web
    $origen = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    if ($origen !== '' && rtrim($origen, '/') !== rtrim(url_boda($slug), '/')) json_response(['ok' => false], 403);
    if (clean_str($_POST['web'] ?? '') !== '') json_response(['ok' => true]);
    if (!limite('libro|' . $slug . '|' . ip_cliente(), 5, 3600, true) || !limite('libro-boda|' . $slug, 40, 3600, true)) {
        json_response(['ok' => false, 'error' => 'Demasiados mensajes seguidos. Prueba dentro de un rato.'], 429);
    }
    $nombre = clean_str($_POST['nombre'] ?? '', 80);
    $mensaje = clean_str($_POST['mensaje'] ?? '', 600);
    if ($nombre === '' || $mensaje === '') json_response(['ok' => false, 'error' => 'Escribe tu nombre y tu mensaje.']);
    if (($_POST['acepto_publicar'] ?? '') !== 'si') json_response(['ok' => false, 'error' => 'Marca la casilla: tu mensaje se publica en la web.']);

    $entradas = libro_entradas($slug);
    if (count($entradas) >= MAX_LIBRO_ENTRADAS) json_response(['ok' => false, 'error' => 'El libro está completo.'], 507);
    $hayFoto = isset($_FILES['foto']) && ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    $fotoId = null;
    if ($hayFoto) {
        if (empty($s['datos']['fotos'])) json_response(['ok' => false, 'error' => 'En este libro no se pueden subir fotos.']);
        if (($_POST['acepto_foto'] ?? '') !== 'si') json_response(['ok' => false, 'error' => 'Marca la casilla de permisos de la foto.']);
        $nFotos = count(array_filter($entradas, fn($e) => !empty($e['foto'])));
        $libre = @disk_free_space(DATA_DIR);
        if ($nFotos >= MAX_LIBRO_FOTOS || bytes_dir(dir_libro($slug)) > MAX_LIBRO_BYTES || ($libre !== false && $libre < DISCO_MINIMO)) {
            registra('libro: foto rechazada por cuota o disco', ['slug' => $slug]);
            json_response(['ok' => false, 'error' => 'Ya no caben más fotos en el libro. Puedes dejar tu mensaje sin foto.'], 507);
        }
        $fotoId = bin2hex(random_bytes(8));
        $r = guarda_foto('foto', dir_libro($slug) . '/' . $fotoId . '.webp', 6 * 1024 * 1024, 24000000, 1400);
        if ($r !== '') json_response(['ok' => false, 'error' => $r], 422);
    }
    $e = ['id' => bin2hex(random_bytes(8)), 'fecha' => date('c'), 'nombre' => $nombre, 'mensaje' => $mensaje, 'foto' => $fotoId,
        'oculto' => false, 'ip' => ip_cliente()];   // la IP, para atender una denuncia; se borra con la web
    $ok = muta_json(dir_libro($slug) . '/libro.json', function (array &$d) use ($e) { $d[] = $e; return true; }, 2 * 1024 * 1024);
    if ($ok !== true) {
        if ($fotoId) @unlink(dir_libro($slug) . '/' . $fotoId . '.webp');
        json_response(['ok' => false, 'error' => 'No se ha podido guardar. Inténtalo de nuevo.'], 507);
    }
    json_response(['ok' => true]);
}

/** POST /panel/libro: ocultar, mostrar o borrar una entrada. */
function panel_libro_accion(string $slug): void {
    if (!panel_csrf_ok()) { http_response_code(403); exit('La sesión ha caducado.'); }
    $id = clean_str($_POST['id'] ?? '', 20);
    $acc = (string) ($_POST['accion'] ?? '');
    $foto = null;
    muta_json(dir_libro($slug) . '/libro.json', function (array &$d) use ($id, $acc, &$foto) {
        foreach ($d as $i => &$e) {
            if (($e['id'] ?? '') !== $id) continue;
            if ($acc === 'ocultar') $e['oculto'] = true;
            if ($acc === 'mostrar') $e['oculto'] = false;
            if ($acc === 'borrar') { $foto = $e['foto'] ?? null; array_splice($d, $i, 1); }
            return true;
        }
        return false;
    });
    if ($foto && preg_match('/^[a-f0-9]{16}$/', $foto)) @unlink(dir_libro($slug) . '/' . $foto . '.webp');
    header('Location: /panel#libro', true, 303);
    exit;
}
