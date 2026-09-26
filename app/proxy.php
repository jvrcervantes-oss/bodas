<?php
// Webs de boda detrás del Worker de Cloudflare (26-sep-2026, rev. previa #116).
//
// Hostinger compartido no sirve subdominios comodín: <slug>.bodaenlace.com llega a Cloudflare, un Worker
// lo reenvía a bodaenlace.com y firma de qué boda se trata. Aquí se comprueba esa firma y, solo si es
// buena, el host efectivo pasa a ser el de la boda. Reglas (Seguridad y Deploy #116):
//  · Sin secreto configurado (≥ 32 bytes) la rama del proxy no existe: nunca vale hash_equals('', '').
//  · Firma = HMAC-SHA256(secreto, "slug|ts|ip") con ventana de 60 s: un valor filtrado caduca solo.
//  · El slug de la firma pasa además por slug_valido() y boda_existe() en index.php, como cualquier host.
//  · Cabeceras de proxy con firma mala → 404. Nunca se cae a las rutas del creador.
//  · La IP del invitado viaja firmada (X-Boda-IP): los límites por IP siguen siendo por persona.

const PROXY_VENTANA_S = 60;

function proxy_secreto(): string {
    $s = defined('BODA_PROXY_SECRETO') ? (string) BODA_PROXY_SECRETO : (string) secreto('boda_proxy');
    return strlen($s) >= 32 ? $s : '';
}

/** Firma que calcula el Worker. Pública para los tests. */
function proxy_firma(string $secreto, string $slug, string $ts, string $ip): string {
    return hash_hmac('sha256', $slug . '|' . $ts . '|' . $ip, $secreto);
}

/**
 * Devuelve el slug si la petición viene firmada por nuestro Worker, null si no trae cabeceras de proxy.
 * Si las trae y la firma no vale, corta con 404.
 * @param array $srv  $_SERVER (inyectable en tests)
 */
function proxy_boda(array $srv, ?int $ahora = null): ?string {
    $firma = (string) ($srv['HTTP_X_BODA_FIRMA'] ?? '');
    $slug = strtolower((string) ($srv['HTTP_X_BODA_SLUG'] ?? ''));
    if ($firma === '' && $slug === '') return null;          // petición normal al creador
    $sec = proxy_secreto();
    $ts = (string) ($srv['HTTP_X_BODA_TS'] ?? '');
    $ip = (string) ($srv['HTTP_X_BODA_IP'] ?? '');
    $ahora = $ahora ?? time();
    $ok = $sec !== '' && $firma !== '' && ctype_digit($ts) && abs($ahora - (int) $ts) <= PROXY_VENTANA_S
        && slug_valido($slug) && ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) !== false)
        && hash_equals(proxy_firma($sec, $slug, $ts, $ip), $firma);
    if (!$ok) {
        registra('proxy: firma rechazada', ['slug' => substr($slug, 0, 40), 'con_secreto' => $sec !== '']);
        return '';                                            // index.php lo trata como 404
    }
    if ($ip !== '' && !defined('IP_PROXY')) define('IP_PROXY', $ip);
    return $slug;
}
