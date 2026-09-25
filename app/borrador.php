<?php
// «Seguir en otro dispositivo»: el borrador del creador (antes de comprar) se guarda en el
// servidor 30 días bajo un enlace secreto, para que la pareja lo abra en otro móvil u ordenador.
// Es una INSTANTÁNEA: no se sincroniza. Revisión previa #101 (Seguridad + Legal), 25-sep-2026:
//  - El token (16 bytes, base64url) viaja en el fragmento: crear#b=…, que no llega a los logs
//    del servidor ni al Referer; la previsualización de WhatsApp solo ve el creador vacío. Se lee
//    por POST. En disco, el nombre es su sha256: ni el listado de la carpeta da los enlaces.
//  - Lo que entra y lo que sale pasa por normaliza_config() (la misma lista blanca que publicar);
//    la foto, por guarda_foto() (valida y recodifica). 404 idéntico si no existe o caducó.
//  - Límites: 20 enlaces/hora y 60 lecturas/hora por IP; si hay demasiados borradores o poco
//    disco, 503 (nunca se borran borradores ajenos para hacer sitio). El cron borra los caducados.
//  - Legal: AxisWorks es responsable (medidas precontractuales, art. 6.1.b), 30 días, y el email
//    del borrador no se usa para nada más.
declare(strict_types=1);

const BORRADOR_DIAS = 30;
const BORRADOR_MAX = 3000;

function borrador_fich(string $tok, string $ext): string { return dir_datos('borradores', hash('sha256', $tok) . '.' . $ext); }
function borrador_tok_valido(string $t): bool { return (bool) preg_match('/^[A-Za-z0-9_-]{22}$/', $t); }

function api_borrador(string $metodo): void {
    cabeceras_privadas();
    header('X-Robots-Tag: noindex, nofollow');
    if ($metodo !== 'POST') json_response(['ok' => false], 405);
    $acc = (string) ($_POST['accion'] ?? '');
    $tok = (string) ($_POST['b'] ?? '');

    if ($acc === 'guardar') {
        if (!limite('borrador|' . ip_cliente(), 20, 3600, true)) json_response(['ok' => false, 'error' => 'Demasiados enlaces seguidos. Prueba dentro de un rato.'], 429);
        $raw = (string) ($_POST['config'] ?? '');
        if (strlen($raw) > 200000) json_response(['ok' => false, 'error' => 'El borrador es demasiado grande.'], 413);
        $in = json_decode($raw, true);
        if (!is_array($in)) json_response(['ok' => false], 400);
        asegura_dir(dir_datos('borradores'));
        $libre = @disk_free_space(DATA_DIR);
        if (count(glob(dir_datos('borradores', '*.json')) ?: []) >= BORRADOR_MAX || ($libre !== false && $libre < 300 * 1024 * 1024)) {
            registra('ALERTA borradores: sin sitio');
            json_response(['ok' => false, 'error' => 'Ahora mismo no podemos guardar el borrador. Prueba más tarde.'], 503);
        }
        $tok = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
        $foto = false;
        if (!empty($_FILES['foto']['tmp_name'])) {
            $fr = guarda_foto('foto', borrador_fich($tok, 'webp'));
            if ($fr !== '' && $fr !== 'sin-foto') json_response(['ok' => false, 'error' => $fr], 422);
            $foto = $fr === '';
        }
        $caduca = time() + BORRADOR_DIAS * 86400;
        escribe_json(borrador_fich($tok, 'json'), [
            'config' => normaliza_config($in),
            'slug' => slug_valido($s = strtolower(clean_str($_POST['slug'] ?? '', 60))) ? $s : '',
            'foto' => $foto, 'creado' => time(), 'caduca' => $caduca,
        ]);
        json_response(['ok' => true, 'url' => url_creador('crear') . '#b=' . $tok, 'b' => $tok, 'caduca' => date('d/m/Y', $caduca)]);
    }

    if (!borrador_tok_valido($tok)) json_response(['ok' => false, 'error' => 'Este enlace no existe o ha caducado.'], 404);
    if (!limite('borrador-leer|' . ip_cliente(), 60, 3600, true)) json_response(['ok' => false, 'error' => 'Demasiados intentos. Prueba dentro de un rato.'], 429);
    $f = borrador_fich($tok, 'json');
    $d = lee_json($f);
    if (!$d || ($d['caduca'] ?? 0) < time()) json_response(['ok' => false, 'error' => 'Este enlace no existe o ha caducado.'], 404);

    if ($acc === 'leer') {
        $ff = borrador_fich($tok, 'webp');
        json_response(['ok' => true, 'config' => normaliza_config($d['config'] ?? []), 'slug' => (string) ($d['slug'] ?? ''),
            'foto' => (!empty($d['foto']) && is_file($ff)) ? 'data:image/webp;base64,' . base64_encode((string) file_get_contents($ff)) : '',
            'caduca' => date('d/m/Y', (int) $d['caduca'])]);
    }
    if ($acc === 'borrar') {
        @unlink($f);
        @unlink(borrador_fich($tok, 'webp'));
        json_response(['ok' => true]);
    }
    json_response(['ok' => false], 400);
}
