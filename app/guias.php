<?php
// Guías SEO que publica El Padrino, como DATOS (nunca código ni git): /guia y /guia/<slug>.
// Revisión previa #100 (Legal + Seguridad), 25-sep-2026. Por qué así:
//
//  · Token propio `contenido` (no el de decisión): publicar prosa en nuestro dominio es phishing si
//    alguien lo roba, así que se rota y revoca aparte y tiene su propio límite.
//  · Lista cerrada de lo que se RECHAZA con 422 (Legal #100): cifras de dinero escritas a mano,
//    promociones y urgencia, competidores y comparaciones, prueba social, garantías, terceros con
//    nombre, contacto (emails, teléfonos, IBAN, pagos) y funciones que el producto no tiene. El precio
//    solo entra por {PRECIO_ESENCIAL}/{PRECIO_ATELIER} (precio final con IVA) y lo que incluye cada
//    pack por {INCLUYE}, que sale de la misma fuente que las condiciones.
//  · Markdown mínimo, y SIEMPRE se escapa primero y se reconoce la sintaxis después. Enlaces: https,
//    host idéntico a uno propio, sin usuario ni puerto; el href se reconstruye.
//  · Cuarentena: 24 h en noindex tras publicar o editar, para que el owner pueda vetar (el servicio del
//    Padrino le avisa por Telegram). Retirar se puede deshacer: las versiones se guardan.
//  · Pie honesto: se publica sin revisión humana previa y lo dice (arts. 5 LCD y 20 TRLGDCU).

declare(strict_types=1);

const GUIAS_MAX_DIA = 3;
const GUIAS_CUARENTENA_H = 24;
const GUIAS_RESERVADOS = ['guia', 'nueva', 'index', 'admin', 'api', 'estudio', 'crear', 'panel', 'login', 'pago', 'pagar'];
const GUIAS_HOSTS_ENLACE = ['axisworks.studio', 'www.axisworks.studio'];
// Legal #100 (a)-(h) + Seguridad #100 (pagos, contacto). Minúsculas; se busca como palabra o frase.
const GUIAS_PROHIBIDO = [
    // (b) promociones y urgencia
    'gratis', 'gratuito', 'gratuita', 'free', 'oferta', 'descuento', 'rebaja', 'promoción', 'promocion', 'solo hoy', 'por tiempo limitado', 'últimas', 'ultimas', 'plazas',
    // (c) comparaciones y superlativos
    'el mejor', 'la mejor', 'los mejores', 'las mejores', 'el más barato', 'la más barata', 'número 1', 'numero 1', 'nº 1', 'líder', 'lider', 'que otras', 'que otros', 'a diferencia de', 'más barato que', 'mas barato que',
    // (c) competidores
    'zankyou', 'bodas.net', 'weddingwire', 'wix', 'canva', 'withjoy', 'joy', 'the knot', 'minted', 'wedsites', 'bodaclick', 'zola', 'vowly', 'wedshoots',
    // (d) prueba social
    'opiniones', 'reseñas', 'resenas', 'valoración', 'valoracion', 'estrellas', 'testimonio', 'caso real',
    // (e) garantías y cumplimiento
    'garantizado', 'garantizada', 'garantía', '100 %', '100%', 'sin riesgo', 'cumple el rgpd', 'legal', 'deducible',
    // (h) funciones que no existen
    'dominio propio', 'app', 'whatsapp', 'lista de bodas', 'pago de regalos', 'invitaciones impresas', 'ilimitado', 'ilimitada', 'sin límite', 'sin limite',
    // Seguridad: pagos fuera de Stripe y credenciales
    'transferencia', 'bizum', 'iban', 'contraseña', 'paypal', 'tarjeta',
];

function guias_dir(string ...$p): string { return dir_datos('contenido', ...$p); }
function guia_slug_valido(string $s): bool {
    return strlen($s) <= 80 && (bool) preg_match('/^[a-z0-9]+(?:-[a-z0-9]+){0,9}$/', $s) && !in_array($s, GUIAS_RESERVADOS, true);
}
function guia_lee(string $slug): ?array { return guia_slug_valido($slug) ? lee_json(guias_dir($slug, 'actual.json')) : null; }

/** Lista de motivos de rechazo (vacía = se puede publicar). Se valida el texto CRUDO. */
function guia_errores(string $titulo, string $desc, string $cuerpo): array {
    $e = [];
    if (mb_strlen($titulo) < 10 || mb_strlen($titulo) > 90) $e[] = 'Título de 10 a 90 caracteres.';
    if (mb_strlen($desc) < 50 || mb_strlen($desc) > 160) $e[] = 'Descripción de 50 a 160 caracteres.';
    if (mb_strlen($cuerpo) < 400 || mb_strlen($cuerpo) > 12000) $e[] = 'Cuerpo de 400 a 12.000 caracteres.';
    $todo = $titulo . "\n" . $desc . "\n" . $cuerpo;
    $bajo = mb_strtolower($todo, 'UTF-8');
    if (preg_match('/[<>]/', $todo)) $e[] = 'Sin HTML.';
    // (a) cifras de dinero a mano
    if (preg_match('/€|\beur\b|\beuros?\b|%|\$/iu', $todo)) $e[] = 'Sin cifras de dinero ni porcentajes: el precio va por {PRECIO_ESENCIAL}/{PRECIO_ATELIER}.';
    // (d) número + parejas/clientes/bodas
    if (preg_match('/\d[\d.,]*\s*(parejas|clientes|bodas|usuarios)/iu', $todo)) $e[] = 'Sin cifras de parejas, clientes o bodas.';
    // (g) contacto: emails, teléfonos, IBAN, dominios en texto plano ajenos
    if (strpos($todo, '@') !== false) $e[] = 'Sin emails.';
    if (preg_match('/(\+?\d[\d\s().-]{7,}\d)/', $todo)) $e[] = 'Sin teléfonos ni números largos.';
    if (preg_match('/\b[A-Z]{2}\d{2}(?:\s?[A-Z0-9]{4}){3,}/i', $todo)) $e[] = 'Sin cuentas bancarias.';
    $sinEnlaces = (string) preg_replace('/\]\([^)]*\)/', '](x)', $todo);
    if (preg_match_all('/\b(?:[a-z0-9-]+\.)+(?:com|es|net|org|io|me|co|info|app|shop|site|online|store|eu|cat|studio)\b/iu', $sinEnlaces, $m)) {
        foreach ($m[0] as $d) if (!in_array(strtolower($d), GUIAS_HOSTS_ENLACE, true)) { $e[] = 'Sin dominios ajenos en el texto (' . $d . ').'; break; }
    }
    foreach (GUIAS_PROHIBIDO as $w) {
        if (preg_match('/(?<![\p{L}\p{N}])' . preg_quote($w, '/') . '(?![\p{L}\p{N}])/u', $bajo)) { $e[] = 'Expresión no permitida: «' . $w . '».'; }
    }
    // Variables: solo las conocidas
    if (preg_match_all('/\{([A-Z_]+)\}/', $todo, $m)) foreach ($m[1] as $v) if (!in_array($v, ['PRECIO_ESENCIAL', 'PRECIO_ATELIER', 'MARCA', 'INCLUYE'], true)) $e[] = 'Variable desconocida {' . $v . '}.';
    // Enlaces
    if (preg_match_all('/\[([^\]]*)\]\(([^)]*)\)/', $cuerpo, $m)) foreach ($m[2] as $u) if (guia_url_ok($u) === null) $e[] = 'Enlace no permitido: solo https a ' . implode(', ', GUIAS_HOSTS_ENLACE) . '.';
    if (preg_match('/!\[|^\s*\[[^\]]+\]:/m', $cuerpo)) $e[] = 'Sin imágenes ni enlaces de referencia.';
    return array_values(array_unique($e));
}

/** URL reconstruida si es un enlace propio válido; null si no. */
function guia_url_ok(string $u): ?string {
    if (preg_match('/[\s"\'<>\\\\]/', $u)) return null;
    $p = parse_url($u);
    if (!$p || ($p['scheme'] ?? '') !== 'https' || isset($p['user']) || isset($p['pass']) || isset($p['port'])) return null;
    $host = (string) ($p['host'] ?? '');
    if (!in_array($host, GUIAS_HOSTS_ENLACE, true)) return null;
    $path = (string) ($p['path'] ?? '/');
    if (!preg_match('#^/[A-Za-z0-9/_.-]*$#', $path)) return null;
    return 'https://' . $host . $path;
}

/** Markdown mínimo → HTML. Escapa primero, reconoce la sintaxis después. */
function guia_html(string $cuerpo): string {
    $out = [];
    $lista = false;
    $parrafo = [];
    $cierraP = function () use (&$parrafo, &$out) { if ($parrafo) { $out[] = '<p>' . implode(' ', $parrafo) . '</p>'; $parrafo = []; } };
    foreach (preg_split('/\n/', str_replace("\r", '', $cuerpo)) as $l) {
        $l = trim($l);
        $x = h($l);
        $x = (string) preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $x);
        $x = (string) preg_replace_callback('/\[([^\]]*)\]\(([^)]*)\)/', function ($m) {
            $url = guia_url_ok(html_entity_decode($m[2], ENT_QUOTES, 'UTF-8'));
            return $url === null ? $m[1] : '<a href="' . h($url) . '">' . $m[1] . '</a>';
        }, $x);
        if ($l === '') { $cierraP(); if ($lista) { $out[] = '</ul>'; $lista = false; } continue; }
        if (strpos($l, '## ') === 0) { $cierraP(); if ($lista) { $out[] = '</ul>'; $lista = false; } $out[] = '<h2>' . substr($x, 3) . '</h2>'; continue; }
        if (strpos($l, '- ') === 0) { $cierraP(); if (!$lista) { $out[] = '<ul>'; $lista = true; } $out[] = '<li>' . substr($x, 2) . '</li>'; continue; }
        if ($lista) { $out[] = '</ul>'; $lista = false; }
        $parrafo[] = $x;
    }
    $cierraP();
    if ($lista) $out[] = '</ul>';
    return guia_variables(implode("\n", $out));
}

/** Sustituye variables DESPUÉS de escapar. El precio siempre como precio final con IVA. */
function guia_variables(string $html, bool $texto = false): string {
    $incluye = '<div class="g-incluye"><p><strong>Qué incluye</strong></p><ul>'
        . '<li>Pack Esencial, ' . h(euros(precio_esencial_cent())) . ' (IVA incluido): web de la boda con confirmación de asistencia por grupo, menú y alergias de cada invitado, peticiones de canciones, panel privado con Excel, edición y descarga en ZIP, y alojamiento hasta ' . (int) MESES_ALOJAMIENTO . ' meses después de la boda.</li>'
        . '<li>Pack Atelier, ' . h(euros(precio_atelier_cent())) . ' (IVA incluido): lo mismo con un diseño ilustrado de la colección y sus animaciones.</li>'
        . '</ul><p>Detalle completo en las <a href="' . BASE_PATH . '/condiciones">condiciones</a>.</p></div>';
    $r = [
        '{PRECIO_ESENCIAL}' => h(euros(precio_esencial_cent())) . ' (IVA incluido)',
        '{PRECIO_ATELIER}' => h(euros(precio_atelier_cent())) . ' (IVA incluido)',
        '{MARCA}' => h(marca()),
        '{INCLUYE}' => $texto ? '' : $incluye,
    ];
    // {INCLUYE} suele quedar dentro de un <p> propio: se saca para no anidar bloques
    $html = str_replace('<p>{INCLUYE}</p>', '{INCLUYE}', $html);
    return strtr($html, $r);
}

function guia_texto(string $s): string { return trim(html_entity_decode(strip_tags(guia_variables(h($s), true)), ENT_QUOTES, 'UTF-8')); }

function guias_log(array $d): void {
    asegura_dir(guias_dir());
    @file_put_contents(guias_dir('registro.jsonl'), json_encode(['t' => date('c')] + $d, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
}

function guias_publicadas_hoy(): int {
    $n = 0;
    foreach (@file(guias_dir('registro.jsonl'), FILE_IGNORE_NEW_LINES) ?: [] as $l) {
        $d = json_decode($l, true);
        if (($d['accion'] ?? '') === 'publicar' && substr((string) ($d['t'] ?? ''), 0, 10) === date('Y-m-d')) $n++;
    }
    return $n;
}

/** API: POST contenido {slug, titulo, descripcion, cuerpo} · POST contenido/retirar {slug} · POST contenido/restaurar {slug}. */
function padrino_contenido(string $sub, array $c, string $quien = 'padrino'): void {
    $slug = strtolower(clean_str($c['slug'] ?? '', 80));
    if (!guia_slug_valido($slug)) json_response(['ok' => false, 'error' => 'Slug no válido.'], 422);
    if ($sub === 'retirar' || $sub === 'restaurar') {
        $r = con_cerrojo(function () use ($slug, $sub) {
            $g = guia_lee($slug);
            if (!$g) return 'No existe.';
            $g['retirada'] = $sub === 'retirar';
            escribe_json(guias_dir($slug, 'actual.json'), $g);
            return '';
        });
        guias_log(['accion' => $sub, 'slug' => $slug, 'quien' => $quien, 'ok' => $r === '']);
        if ($r !== '') json_response(['ok' => false, 'error' => $r], 404);
        json_response(['ok' => true]);
    }
    $titulo = clean_str($c['titulo'] ?? '', 200);
    $desc = clean_str($c['descripcion'] ?? '', 400);
    $cuerpo = clean_str($c['cuerpo'] ?? '', 13000);
    $err = guia_errores($titulo, $desc, $cuerpo);
    if ($err) { guias_log(['accion' => 'rechazo', 'slug' => $slug, 'errores' => $err]); json_response(['ok' => false, 'errores' => $err], 422); }
    if (guias_publicadas_hoy() >= GUIAS_MAX_DIA) json_response(['ok' => false, 'error' => 'Máximo ' . GUIAS_MAX_DIA . ' publicaciones al día.'], 429);
    $r = con_cerrojo(function () use ($slug, $titulo, $desc, $cuerpo) {
        $prev = guia_lee($slug);
        $v = (int) ($prev['version'] ?? 0) + 1;
        $g = ['slug' => $slug, 'version' => $v, 'titulo' => $titulo, 'descripcion' => $desc, 'cuerpo' => $cuerpo,
            'creada' => (string) ($prev['creada'] ?? date('c')), 'publicada' => date('c'), 'retirada' => false];
        escribe_json(guias_dir($slug, 'v' . $v . '.json'), $g);
        escribe_json(guias_dir($slug, 'actual.json'), $g);
        return $g;
    });
    $hash = hash('sha256', $titulo . "\n" . $desc . "\n" . $cuerpo);
    guias_log(['accion' => 'publicar', 'slug' => $slug, 'version' => $r['version'], 'sha256' => $hash]);
    json_response(['ok' => true, 'slug' => $slug, 'version' => $r['version'], 'url' => url_creador('guia/' . $slug), 'sha256' => $hash, 'cuarentena_hasta' => date('c', time() + GUIAS_CUARENTENA_H * 3600)]);
}

function guias_todas(bool $conRetiradas = false): array {
    $gs = [];
    foreach (glob(guias_dir('*'), GLOB_ONLYDIR) ?: [] as $d) {
        $g = guia_lee(basename($d));
        if ($g && ($conRetiradas || empty($g['retirada']))) $gs[] = $g;
    }
    usort($gs, fn($a, $b) => strcmp((string) $b['publicada'], (string) $a['publicada']));
    return $gs;
}

/** /guia y /guia/<slug>. Devuelve false si no existe (404 del llamador). */
function sirve_guia(string $slug): bool {
    if ($slug === '') {
        $items = '';
        foreach (guias_todas() as $g) $items .= '<li><a href="' . BASE_PATH . '/guia/' . h($g['slug']) . '">' . h(guia_texto($g['titulo'])) . '</a><br><small>' . h(guia_texto($g['descripcion'])) . '</small></li>';
        echo guia_pagina('Guías para organizar vuestra boda', 'Ideas prácticas para la web de la boda, la confirmación de invitados y los menús.',
            $items !== '' ? '<ul class="g-lista">' . $items . '</ul>' : '<p>Pronto publicaremos aquí las primeras guías.</p>', true, '');
        return true;
    }
    $g = guia_lee($slug);
    if (!$g || !empty($g['retirada'])) return false;
    $cuarentena = strtotime((string) $g['publicada']) > time() - GUIAS_CUARENTENA_H * 3600;
    $pie = '<p class="g-pie">Texto redactado y publicado por un sistema de IA sin revisión humana previa. Fecha: ' . h(fecha_larga(substr((string) $g['publicada'], 0, 10), false))
        . '. Si ves un error, escríbenos a <a href="mailto:' . h(empresa()['email']) . '">' . h(empresa()['email']) . '</a>.</p>';
    echo guia_pagina(guia_texto($g['titulo']), guia_texto($g['descripcion']), '<article class="legal g-art">' . guia_html($g['cuerpo']) . $pie . '</article>', !$cuarentena, $slug);
    return true;
}

function guia_pagina(string $titulo, string $desc, string $cuerpo, bool $indexable, string $slug): string {
    $robots = (OCULTO || !$indexable) ? 'noindex, nofollow' : 'index, follow';
    if (OCULTO || !$indexable) header('X-Robots-Tag: noindex, nofollow');
    $url = url_creador('guia' . ($slug !== '' ? '/' . $slug : ''));
    return '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . h($titulo) . ' — ' . h(marca()) . '</title><meta name="description" content="' . h($desc) . '"><meta name="robots" content="' . $robots . '">'
        . '<link rel="canonical" href="' . h($url) . '">'
        . '<link rel="stylesheet" href="' . BASE_PATH . '/assets/marca.css?v=' . h(ASSETS_V) . '"><link rel="stylesheet" href="' . BASE_PATH . '/assets/crear.css?v=' . h(ASSETS_V) . '"></head><body class="simple">'
        . '<header class="s-top"><a class="c-marca" href="' . BASE_PATH . '/">' . il('flor') . '<span>' . h(marca()) . '</span></a></header>'
        . '<main class="simple-main"><h1>' . h($titulo) . '</h1>' . $cuerpo
        . '<p class="g-cta"><a class="c-btn" href="' . BASE_PATH . '/crear">Crear la web de vuestra boda</a></p></main>' . pie_creador() . '</body></html>';
}

/** Panel del estudio: el owner retira o restaura cualquier guía (Legal #100). */
function estudio_guias(string $metodo): string {
    $msg = '';
    if ($metodo === 'POST') {
        $slug = strtolower(clean_str($_POST['slug'] ?? '', 80));
        $acc = ($_POST['accion'] ?? '') === 'restaurar' ? 'restaurar' : 'retirar';
        $g = guia_lee($slug);
        if ($g) {
            $g['retirada'] = $acc === 'retirar';
            escribe_json(guias_dir($slug, 'actual.json'), $g);
            guias_log(['accion' => $acc, 'slug' => $slug, 'quien' => 'estudio']);
            estudio_log('guia-' . $acc, ['slug' => $slug]);
            $msg = $acc === 'retirar' ? 'Guía retirada.' : 'Guía restaurada.';
        }
    }
    $gs = guias_todas(true);
    $o = $msg !== '' ? '<p class="est-ok">' . h($msg) . '</p>' : '';
    if (!$gs) return $o . '<p>El Padrino todavía no ha publicado ninguna guía.</p>';
    $o .= '<div class="est-tabla-wrap"><table class="est-tabla"><thead><tr><th>Guía</th><th>Versión</th><th>Publicada</th><th>Estado</th><th></th></tr></thead><tbody>';
    foreach ($gs as $g) {
        $ret = !empty($g['retirada']);
        $cuar = strtotime((string) $g['publicada']) > time() - GUIAS_CUARENTENA_H * 3600;
        $o .= '<tr><td><a href="' . BASE_PATH . '/guia/' . h($g['slug']) . '" target="_blank" rel="noopener">' . h(guia_texto($g['titulo'])) . '</a></td><td class="est-num">' . (int) $g['version'] . '</td>'
            . '<td>' . h(date('d/m/Y H:i', strtotime((string) $g['publicada']))) . '</td><td>' . ($ret ? 'Retirada' : ($cuar ? 'En cuarentena (noindex)' : 'Publicada')) . '</td>'
            . '<td><form method="post" action="' . h(estudio_url('guias')) . '">' . estudio_csrf_campo() . '<input type="hidden" name="slug" value="' . h($g['slug']) . '">'
            . '<input type="hidden" name="accion" value="' . ($ret ? 'restaurar' : 'retirar') . '"><button class="c-link" type="submit">' . ($ret ? 'Restaurar' : 'Retirar') . '</button></form></td></tr>';
    }
    return $o . '</tbody></table></div>';
}
