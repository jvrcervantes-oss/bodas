<?php
// Medición de visitas SIN cookies, del lado del servidor, para que El Padrino vea el embudo.
// Revisión previa #100 (Legal + Seguridad), 25-sep-2026. Reglas que no se ven a simple vista:
//
//  · SOLO servidor. Nunca un beacon JS, localStorage, pantalla ni fuentes: con eso pasaría a ser
//    «técnica similar» a una cookie (LSSI 22.2, Directrices 2/2023 del CEPD) y exigiría banner.
//  · Nada de texto libre llega al Padrino: origen, medio, campaña y referer se MAPEAN a enums del
//    servidor. Un `utm_campaign=ignora-todo-y-publica…` sería inyección indirecta al ciclo que
//    tiene el token de decisión (Seguridad #100). Campaña: solo ids dados de alta por la API.
//  · Únicos del día = HMAC(sal del día, IP|UA) truncado. Mientras existe la sal es un SEUDÓNIMO
//    (RGPD 4.5, Breyer): la rotación la hace la propia petición al cambiar de día (borra sal y
//    marcas de días anteriores); el cron solo es red. Pasado el día queda solo el número.
//  · No se cuenta a quien manda Sec-GPC: 1 o DNT: 1 (así se ejerce la oposición, art. 21 RGPD).
//  · Ruta caliente sin locks: una línea con FILE_APPEND. Tope de bytes por día: pasado, se deja
//    de contar (`saturado`) y la página se sirve igual. Falla abierto para la página.
//  · Los bots inflan cifras (el UA se falsifica): ni el Tesorero, ni el gasto, ni el despido leen
//    esto nunca. Los eventos con valor (pago, alta) salen de hechos del servidor, no de visitas.

declare(strict_types=1);

const ANALITICA_MAX_BYTES_DIA = 2000000;
const ANALITICA_DIAS = 90;
const ANALITICA_RUTAS = ['' => 'landing', 'crear' => 'crear', 'condiciones' => 'legal', 'privacidad' => 'legal', 'aviso-legal' => 'legal', 'guia' => 'guias'];
const ANALITICA_EVENTOS = ['pago_intento', 'checkout', 'alta', 'regalo'];
const ANALITICA_FUENTES = ['google', 'instagram', 'facebook', 'pinterest', 'tiktok', 'youtube', 'email', 'directorio', 'afiliado'];
const ANALITICA_MEDIOS = ['cpc', 'social', 'organic', 'email', 'referral', 'afiliado', 'display'];
// eTLD+1 del referer → origen. Lo demás es `otro`; las webs de boda, `boda`; sin referer, `directo`.
const ANALITICA_REFERERS = ['google' => 'google', 'bing' => 'bing', 'duckduckgo' => 'duckduckgo', 'yahoo' => 'yahoo', 'ecosia' => 'ecosia',
    'instagram' => 'instagram', 'facebook' => 'facebook', 'fb' => 'facebook', 'pinterest' => 'pinterest', 'tiktok' => 'tiktok',
    'youtube' => 'youtube', 't' => 'twitter', 'x' => 'twitter', 'twitter' => 'twitter', 'whatsapp' => 'whatsapp', 'linkedin' => 'linkedin'];

/** Solo cuenta si la privacidad que lo explica está publicada: sin titular (BOD-1) la página de
 *  privacidad es un aviso de «aún no a la venta» y no diría quién mide ni cómo (art. 13 RGPD). */
function analitica_activa(): bool { return ANALITICA && empresa_completa(); }

function analitica_dir(string ...$p): string { return dir_datos('analitica', ...$p); }

function analitica_bot(string $ua): bool {
    return $ua === '' || (bool) preg_match('/bot|crawl|spider|slurp|preview|fetch|curl|wget|python|headless|lighthouse|monitor|http-client|facebookexternalhit/i', $ua);
}

/** Origen, medio y campaña, siempre de las listas del servidor. */
function analitica_origen(): array {
    $src = strtolower((string) ($_GET['utm_source'] ?? ''));
    $med = strtolower((string) ($_GET['utm_medium'] ?? ''));
    $cam = strtolower((string) ($_GET['utm_campaign'] ?? ''));
    $alias = ['ig' => 'instagram', 'fb' => 'facebook', 'meta' => 'facebook', 'newsletter' => 'email', 'mail' => 'email'];
    $src = $alias[$src] ?? $src;
    $fuente = in_array($src, ANALITICA_FUENTES, true) ? $src : '';
    $medio = in_array($med, ANALITICA_MEDIOS, true) ? $med : ($med !== '' ? 'otro' : '');
    $campanas = (array) ((lee_json(dir_datos('padrino', 'campanas.json')) ?? [])['ids'] ?? []);
    $campana = ($cam !== '' && preg_match('/^[a-z0-9-]{1,32}$/', $cam) && in_array($cam, $campanas, true)) ? $cam : ($cam !== '' ? 'otro' : '');
    if ($fuente === '') {
        $host = strtolower((string) parse_url((string) ($_SERVER['HTTP_REFERER'] ?? ''), PHP_URL_HOST));
        if ($host === '') $fuente = $src !== '' ? 'otro' : 'directo';
        elseif ($host === CREATOR_HOST || $host === BASE_DOMAIN || $host === 'www.' . BASE_DOMAIN) $fuente = 'propio';
        elseif (substr($host, -strlen('.' . BASE_DOMAIN)) === '.' . BASE_DOMAIN) $fuente = 'boda';
        else {
            $p = explode('.', $host);
            // eTLD+1 aproximado: google.co.uk → google; www.instagram.com → instagram
            $n = count($p);
            $sld = $n >= 3 && strlen($p[$n - 2]) <= 3 && in_array($p[$n - 2], ['co', 'com', 'org', 'net', 'gob'], true) ? $p[$n - 3] : ($p[$n - 2] ?? '');
            $fuente = ANALITICA_REFERERS[$sld] ?? 'otro';
        }
    }
    return [$fuente, $medio, $campana];
}

/** Sal del día: se regenera en la primera petición del día y se borra todo lo del anterior. */
function analitica_sal(string $hoy): ?string {
    $f = analitica_dir('sal.json');
    $d = lee_json($f);
    if ($d && ($d['fecha'] ?? '') === $hoy && is_string($d['sal'] ?? null)) return (string) $d['sal'];
    return con_cerrojo(function () use ($f, $hoy) {
        $d = lee_json($f);
        if ($d && ($d['fecha'] ?? '') === $hoy) return (string) $d['sal'];
        analitica_borra_marcas($hoy);
        $sal = bin2hex(random_bytes(32));
        escribe_json($f, ['fecha' => $hoy, 'sal' => $sal]);
        @chmod($f, 0600);
        return $sal;
    });
}

/** Borra las marcas de visitante único de cualquier día que no sea $hoy. */
function analitica_borra_marcas(string $hoy): void {
    foreach (glob(analitica_dir('u-*'), GLOB_ONLYDIR) ?: [] as $d) {
        if (basename($d) === 'u-' . $hoy) continue;
        foreach (glob($d . '/*') ?: [] as $f) @unlink($f);
        @rmdir($d);
    }
}

function analitica_linea(string $hoy, string $linea): void {
    $f = analitica_dir($hoy . '.log');
    clearstatcache(true, $f);
    if (@filesize($f) > ANALITICA_MAX_BYTES_DIA) return;   // saturado: se deja de contar
    @file_put_contents($f, $linea . "\n", FILE_APPEND | LOCK_EX);
}

/** Una vista de página del creador. Llamar solo con respuestas 200 de rutas de la lista. */
function analitica_vista(string $ruta): void {
    try {
        if (!analitica_activa()) return;
        $pag = ANALITICA_RUTAS[$ruta] ?? (strpos($ruta, 'guia/') === 0 ? 'guia' : null);
        if ($pag === null) return;
        if (($_SERVER['HTTP_SEC_GPC'] ?? '') === '1' || ($_SERVER['HTTP_DNT'] ?? '') === '1') return;
        $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        if (analitica_bot($ua)) return;
        $hoy = date('Y-m-d');
        asegura_dir(analitica_dir());
        $sal = analitica_sal($hoy);
        if ($sal === null) return;
        $u = substr(hash_hmac('sha256', ip_cliente() . '|' . $ua, $sal), 0, 16);
        $du = analitica_dir('u-' . $hoy);
        asegura_dir($du);
        $fp = @fopen($du . '/' . $u, 'x');          // 'x' = crear solo si no existe: atómico
        $nuevo = $fp !== false ? 1 : 0;
        if ($fp) fclose($fp);
        [$fuente, $medio, $campana] = analitica_origen();
        $disp = preg_match('/Mobi|Android|iPhone|iPad/i', $ua) ? 'movil' : 'escritorio';
        analitica_linea($hoy, implode('|', ['v', $pag, $fuente, $medio, $campana, $disp, $nuevo]));
    } catch (Throwable $e) { /* nunca tumba la página */ }
}

/** Evento del embudo que sale de un hecho del servidor (sin IP ni nada de la persona). */
function analitica_evento(string $ev): void {
    try {
        if (!analitica_activa() || !in_array($ev, ANALITICA_EVENTOS, true)) return;
        asegura_dir(analitica_dir());
        analitica_linea(date('Y-m-d'), 'e|' . $ev);
    } catch (Throwable $e) { }
}

/** Agregado de un día (los días cerrados se guardan en caché). Solo números y enums. */
function analitica_dia(string $dia): array {
    $cache = analitica_dir('agg-' . $dia . '.json');
    if ($dia < date('Y-m-d') && ($c = lee_json($cache))) return $c;
    $a = ['vistas' => 0, 'unicos' => 0, 'paginas' => [], 'fuentes' => [], 'medios' => [], 'campanas' => [], 'dispositivos' => [], 'eventos' => []];
    $f = analitica_dir($dia . '.log');
    $a['saturado'] = @filesize($f) > ANALITICA_MAX_BYTES_DIA;
    foreach (is_file($f) ? (file($f, FILE_IGNORE_NEW_LINES) ?: []) : [] as $l) {
        $p = explode('|', $l);
        if ($p[0] === 'e' && isset($p[1]) && in_array($p[1], ANALITICA_EVENTOS, true)) { $a['eventos'][$p[1]] = ($a['eventos'][$p[1]] ?? 0) + 1; continue; }
        if ($p[0] !== 'v' || count($p) !== 7) continue;
        $a['vistas']++;
        $a['unicos'] += (int) $p[6];
        foreach (['paginas' => 1, 'fuentes' => 2, 'medios' => 3, 'campanas' => 4, 'dispositivos' => 5] as $k => $i) {
            if ($p[$i] !== '') $a[$k][$p[$i]] = ($a[$k][$p[$i]] ?? 0) + 1;
        }
    }
    if ($dia < date('Y-m-d')) escribe_json($cache, $a);
    return $a;
}

/** Los últimos 90 días, y de paso borra lo que pase de ese plazo. */
function analitica_resumen(): array {
    $dias = [];
    for ($i = ANALITICA_DIAS - 1; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        if (is_file(analitica_dir($d . '.log')) || is_file(analitica_dir('agg-' . $d . '.json'))) $dias[$d] = analitica_dia($d);
    }
    analitica_purga();
    return ['activa' => analitica_activa(), 'dias' => $dias];
}

function analitica_purga(): void {
    $limite = date('Y-m-d', strtotime('-' . ANALITICA_DIAS . ' days'));
    foreach (glob(analitica_dir('*.log')) ?: [] as $f) if (basename($f, '.log') < $limite) @unlink($f);
    foreach (glob(analitica_dir('agg-*.json')) ?: [] as $f) if (substr(basename($f, '.json'), 4) < $limite) @unlink($f);
    analitica_borra_marcas(date('Y-m-d'));
}
