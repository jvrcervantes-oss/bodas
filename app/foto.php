<?php
// Foto de portada. El navegador ya la manda en WebP reducida, pero eso no protege
// nada: el servidor recibe lo que un atacante quiera mandar. Aquí se comprueba el
// tipo real (finfo), el tamaño, y se RECODIFICA con GD: lo que se guarda es una
// imagen nueva, sin EXIF ni nada escondido. Nombre fijo (foto.webp) elegido por
// nosotros, nunca por el cliente; la carpeta de datos no se sirve.

declare(strict_types=1);

const FOTO_MAX_BYTES = 4 * 1024 * 1024;
const FOTO_LADO_MAX = 1400;

/** Procesa $_FILES[$campo] y escribe $destino (webp). Devuelve '' si ok o el motivo. */
function guarda_foto(string $campo, string $destino, int $maxBytes = FOTO_MAX_BYTES, int $maxPx = 40000000, int $lado = FOTO_LADO_MAX): string {
    $f = $_FILES[$campo] ?? null;
    if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return 'sin-foto';
    if (($f['error'] ?? 1) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $f['tmp_name'])) return 'La foto no ha llegado bien.';
    if ((int) $f['size'] > $maxBytes) return 'La foto pesa demasiado (máximo ' . (int) round($maxBytes / 1048576) . ' MB).';
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file((string) $f['tmp_name']);
    if (!in_array($mime, ['image/webp', 'image/jpeg', 'image/png'], true)) return 'La foto tiene que ser JPG, PNG o WebP.';
    $info = @getimagesize((string) $f['tmp_name']);
    // El tope de píxeles se mira ANTES de decodificar: 40 MP en GD son ~160 MB de RAM (Seguridad, #87)
    if (!$info || $info[0] < 200 || $info[1] < 200 || $info[0] * $info[1] > $maxPx) return 'La foto no tiene un tamaño válido.';
    $img = @imagecreatefromstring((string) file_get_contents((string) $f['tmp_name']));
    if (!$img) return 'No hemos podido leer la foto.';
    [$w, $h] = [imagesx($img), imagesy($img)];
    $k = min(1, $lado / max($w, $h));
    if ($k < 1) {
        $nw = (int) round($w * $k);
        $nh = (int) round($h * $k);
        $dst = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);
        $img = $dst;
    }
    asegura_dir(dirname($destino));
    $tmp = $destino . '.tmp';
    $ok = imagewebp($img, $tmp, 82);
    imagedestroy($img);
    if (!$ok || !rename($tmp, $destino)) { @unlink($tmp); return 'No hemos podido guardar la foto.'; }
    return '';
}

/** Sirve la foto de una boda desde la carpeta de datos. */
function sirve_foto(string $ruta): void {
    if (!is_file($ruta)) { http_response_code(404); exit; }
    header('Content-Type: image/webp');
    header('Content-Length: ' . filesize($ruta));
    header('Cache-Control: public, max-age=300');
    header('X-Content-Type-Options: nosniff');
    readfile($ruta);
    exit;
}
