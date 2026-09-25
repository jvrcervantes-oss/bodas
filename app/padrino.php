<?php
// El Padrino: la puerta por la que el CEO autónomo del producto (servicio aparte en Railway)
// lee cómo va el negocio y cambia precio y marca. Encargo: encargos/20260925_bodas_padrino.md
// en el repo de la agencia, revisión previa #99.
//
// POR QUÉ ASÍ:
//  · El Padrino no puede escribir PHP ni hacer push (Seguridad #99): quien toca el código de este
//    repo manda junto a secrets.php. Por eso precio y marca son DATOS (DATA_DIR/padrino/*.json) y
//    los límites viven AQUÍ, en código que él no toca: suelo, techo de 400 € (factura simplificada,
//    RD 1619/2012 art. 4), paso máximo, una subida o bajada por semana e historial fechado (el
//    precio de referencia de 30 días de Ómnibus sale de ahí).
//  · Dos tokens, lectura y decisión. En este repo público solo va su sha256
//    (app/padrino_tokens.php); el token en claro vive solo en Railway. Sin hash configurado → 401:
//    falla en cerrado, nunca abierto.
//  · El resumen NUNCA lleva emails ni nada de invitados: lista cerrada de campos. El Padrino lee
//    correo de desconocidos, y lo que él sepa puede acabar en una respuesta inyectada.
//  · El precio se congela en cada pedido (meta.json), así que un cambio con una sesión de pago
//    abierta no dispara una alerta falsa en el alta.

declare(strict_types=1);

const PADRINO_SUELO_CENT = 4900;     // por debajo, el margen no cubre comisiones, IA y alojamiento
const PADRINO_TECHO_CENT = 40000;    // factura simplificada: 400 € IVA incluido como máximo
const PADRINO_PASO_MAX = 0.30;       // ±30 % por cambio respecto al precio vigente
const PADRINO_DIAS_ENTRE_CAMBIOS = 7;
const PADRINO_MARCA_DIAS_ENTRE_CAMBIOS = 30;
// Nombres que no puede usar: competidores directos (BOD-7) y nombres internos del estudio.
const PADRINO_MARCA_VETADA = ['vowly', 'lawang', 'sumba', 'b2k', 'balibest', 'bali', 'benipizza', 'educora',
    'carbon', 'carbón', 'tenebris', 'warchanters', 'gamefactory', 'ids', 'zola', 'the knot', 'bodas.net', 'wedshoots'];

function padrino_dir(string ...$p): string { return dir_datos('padrino', ...$p); }

/** Precio vigente: el que decidió el Padrino si existe y es válido; si no, el del código. */
function padrino_precios(): array {
    static $p = null;
    if ($p !== null) return $p;
    $d = lee_json(padrino_dir('precios.json')) ?? [];
    $e = (int) ($d['esencial_cent'] ?? 0);
    $a = (int) ($d['atelier_cent'] ?? 0);
    $ok = padrino_precio_error($e, $a, null) === '';
    return $p = [
        'esencial_cent' => $ok ? $e : PRECIO_PACK_CENT,
        'atelier_cent' => $ok ? $a : PRECIO_PACK_ATELIER_CENT,
        'vigor_desde' => $ok ? (string) ($d['vigor_desde'] ?? '') : '',
    ];
}
function precio_esencial_cent(): int { return padrino_precios()['esencial_cent']; }
function precio_atelier_cent(): int { return padrino_precios()['atelier_cent']; }

/** Marca vigente: la del Padrino si pasó el filtro; si no, la del código. */
function marca(): string {
    static $m = null;
    if ($m !== null) return $m;
    $n = (string) ((lee_json(padrino_dir('marca.json')) ?? [])['nombre'] ?? '');
    return $m = ($n !== '' && padrino_marca_error($n, null) === '') ? $n : MARCA;
}

/**
 * '' si el precio es aceptable; si no, el motivo. $vigente = precios actuales (para el paso
 * máximo y la frecuencia) o null cuando solo se valida el fichero al leerlo.
 */
function padrino_precio_error(int $e, int $a, ?array $vigente): string {
    if ($e < PADRINO_SUELO_CENT || $a < PADRINO_SUELO_CENT) return 'Por debajo del suelo de ' . euros(PADRINO_SUELO_CENT) . '.';
    if ($e > PADRINO_TECHO_CENT || $a > PADRINO_TECHO_CENT) return 'Por encima de ' . euros(PADRINO_TECHO_CENT) . ': haría falta factura completa.';
    if ($a <= $e) return 'El Atelier tiene que costar más que el Esencial (Stripe cobra la diferencia como segunda línea).';
    if ($e % 100 !== 0 && $e % 100 !== 50 && $e % 100 !== 90 && $e % 100 !== 95) return 'Céntimos no admitidos: .00, .50, .90 o .95.';
    if ($a % 100 !== 0 && $a % 100 !== 50 && $a % 100 !== 90 && $a % 100 !== 95) return 'Céntimos no admitidos: .00, .50, .90 o .95.';
    if ($vigente === null) return '';
    foreach (['esencial_cent' => $e, 'atelier_cent' => $a] as $k => $nuevo) {
        $viejo = (int) $vigente[$k];
        if ($viejo > 0 && abs($nuevo - $viejo) / $viejo > PADRINO_PASO_MAX + 1e-9) return 'Cambio de más del ' . (int) (PADRINO_PASO_MAX * 100) . ' % de una vez.';
    }
    $ult = (string) ($vigente['vigor_desde'] ?? '');
    if ($ult !== '' && strtotime($ult) > time() - PADRINO_DIAS_ENTRE_CAMBIOS * 86400) return 'Solo un cambio de precio cada ' . PADRINO_DIAS_ENTRE_CAMBIOS . ' días.';
    return '';
}

function padrino_marca_error(string $n, ?array $vigente): string {
    if (!preg_match("/^[\p{L}][\p{L}\p{N} &'·.-]{1,38}[\p{L}\p{N}]$/u", $n)) return 'Entre 3 y 40 caracteres: letras, números, espacio, & \' · . -';
    $b = mb_strtolower($n, 'UTF-8');
    foreach (PADRINO_MARCA_VETADA as $v) if (strpos($b, $v) !== false) return 'Nombre vetado (competidor o nombre interno).';
    if ($vigente === null) return '';
    $ult = (string) ($vigente['desde'] ?? '');
    if ($ult !== '' && strtotime($ult) > time() - PADRINO_MARCA_DIAS_ENTRE_CAMBIOS * 86400) return 'Solo un cambio de marca cada ' . PADRINO_MARCA_DIAS_ENTRE_CAMBIOS . ' días.';
    return '';
}

/** Token de la cabecera contra el sha256 guardado. Hash vacío = nadie entra. */
function padrino_autorizado(string $tipo): bool {
    $f = APP_DIR . '/padrino_tokens.php';
    $hashes = is_file($f) ? (array) require $f : [];
    $h = (string) ($hashes[$tipo] ?? '');
    if (!preg_match('/^[a-f0-9]{64}$/', $h)) return false;
    $auth = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '');
    if (!preg_match('/^Bearer ([A-Za-z0-9_-]{40,200})$/', $auth, $m)) return false;
    return hash_equals($h, hash('sha256', $m[1]));
}

function padrino_log(string $accion, array $datos = []): void {
    try {
        asegura_dir(padrino_dir());
        file_put_contents(padrino_dir('registro.jsonl'), json_encode(['t' => date('c'), 'accion' => $accion] + $datos, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND | LOCK_EX);
    } catch (Throwable $e) { /* el log nunca tumba la petición */ }
}

function rutas_padrino(string $sub, string $metodo): void {
    cabeceras_privadas();
    if (!limite('padrino|' . ip_cliente(), 120, 3600, true)) json_response(['ok' => false, 'error' => 'limite'], 429);
    $escritura = in_array($sub, ['precios', 'marca'], true);
    if (!padrino_autorizado($escritura ? 'decision' : 'lectura')) {
        padrino_log('rechazo', ['ruta' => $sub, 'ip' => hash('sha256', ip_cliente())]);
        json_response(['ok' => false], 401);
    }
    $cuerpo = $metodo === 'POST' ? json_decode((string) file_get_contents('php://input', false, null, 0, 10000), true) : null;
    if ($metodo === 'POST' && !is_array($cuerpo)) json_response(['ok' => false, 'error' => 'JSON no válido'], 400);
    switch ($sub) {
        case 'resumen':
            if ($metodo !== 'GET') json_response(['ok' => false], 405);
            json_response(padrino_resumen());
        case 'remitente':
            if ($metodo !== 'POST') json_response(['ok' => false], 405);
            json_response(padrino_remitente((string) ($cuerpo['email'] ?? '')));
        case 'precios':
            if ($metodo !== 'POST') json_response(['ok' => false], 405);
            padrino_cambia_precios($cuerpo);
        case 'marca':
            if ($metodo !== 'POST') json_response(['ok' => false], 405);
            padrino_cambia_marca($cuerpo);
    }
    json_response(['ok' => false], 404);
}

/** Lista CERRADA de campos. Nada de emails, nombres ni datos de invitados: solo cifras. */
function padrino_resumen(): array {
    $bodas = [];
    foreach (glob(dir_datos('bodas', '*'), GLOB_ONLYDIR) ?: [] as $d) {
        $slug = basename($d);
        if (!slug_valido($slug)) continue;
        $cfg = lee_json($d . '/config.json');
        if (!$cfg) continue;
        $ped = lee_json($d . '/pedido.json') ?? [];
        $arch = ($cfg['_estado'] ?? '') === 'archivada';
        $conf = 0;
        if (!$arch) foreach (lee_json($d . '/guardado/rsvp.json') ?? [] as $r) $conf += count(personas($r));
        $fecha = (string) ($cfg['fecha'] ?? '');
        $bodas[] = [
            'slug' => $slug,
            'creada' => (string) ($ped['creado'] ?? ''),
            'fecha_boda' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) ? $fecha : '',
            'pack' => ((string) ($cfg['atelier'] ?? '')) !== '' ? 'atelier' : 'esencial',
            'origen' => str_starts_with((string) ($ped['session_id'] ?? ''), 'cortesia_') ? 'regalo' : 'pago',
            'archivada' => $arch,
            'confirmados' => $conf,
            'borrado_en' => $fecha !== '' ? fecha_borrado($fecha) : '',
        ];
    }
    $pedidos = [];
    foreach (glob(dir_datos('pedidos', '*.json')) ?: [] as $f) {
        $p = lee_json($f);
        if (!$p) continue;
        $pedidos[] = [
            'ref' => substr(hash('sha256', (string) ($p['session_id'] ?? basename($f))), 0, 16),
            'fecha' => (string) ($p['creado'] ?? ''),
            'pack' => ((string) ($p['atelier'] ?? '')) !== '' ? 'atelier' : 'esencial',
            'total_cent' => (int) ($p['importe']['total'] ?? 0),
            'estado' => (string) ($p['estado'] ?? ''),
            'regalo' => str_starts_with((string) ($p['session_id'] ?? ''), 'cortesia_'),
        ];
    }
    return ['ok' => true, 'generado' => date('c'), 'marca' => marca(), 'precios' => padrino_precios(), 'bodas' => $bodas, 'pedidos' => $pedidos];
}

/** ¿Este remitente tiene boda? Sí/no y el slug. Nunca devuelve el email de nadie. */
function padrino_remitente(string $email): array {
    $email = mb_strtolower(trim($email), 'UTF-8');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) return ['ok' => true, 'tiene_boda' => false, 'slug' => null];
    foreach (glob(dir_datos('bodas', '*'), GLOB_ONLYDIR) ?: [] as $d) {
        $slug = basename($d);
        if (!slug_valido($slug)) continue;
        $cfg = lee_json($d . '/config.json') ?? [];
        if (($cfg['_estado'] ?? '') === 'archivada') continue;
        $ped = lee_json($d . '/pedido.json') ?? [];
        foreach ([(string) ($cfg['pareja']['email'] ?? ''), (string) ($ped['email'] ?? '')] as $e) {
            if ($e !== '' && hash_equals(mb_strtolower($e, 'UTF-8'), $email)) return ['ok' => true, 'tiene_boda' => true, 'slug' => $slug];
        }
    }
    return ['ok' => true, 'tiene_boda' => false, 'slug' => null];
}

function padrino_cambia_precios(array $c): void {
    $e = (int) ($c['esencial_cent'] ?? 0);
    $a = (int) ($c['atelier_cent'] ?? 0);
    $motivo = clean_str($c['motivo'] ?? '', 300);
    if ($motivo === '') json_response(['ok' => false, 'error' => 'Falta el motivo.'], 422);
    $r = con_cerrojo(function () use ($e, $a, $motivo) {
        $f = padrino_dir('precios.json');
        $d = lee_json($f) ?? [];
        $vig = padrino_precios();
        $err = padrino_precio_error($e, $a, $vig);
        if ($err !== '') return $err;
        $hist = (array) ($d['historial'] ?? []);
        // El historial arranca con el precio del código, para que el de referencia de 30 días exista
        if (!$hist) $hist[] = ['desde' => '', 'esencial_cent' => PRECIO_PACK_CENT, 'atelier_cent' => PRECIO_PACK_ATELIER_CENT, 'motivo' => 'precio inicial del owner'];
        $ahora = date('c');
        $hist[] = ['desde' => $ahora, 'esencial_cent' => $e, 'atelier_cent' => $a, 'motivo' => $motivo];
        escribe_json($f, ['esencial_cent' => $e, 'atelier_cent' => $a, 'vigor_desde' => $ahora, 'historial' => array_slice($hist, -200)]);
        return '';
    });
    padrino_log('precios', ['esencial_cent' => $e, 'atelier_cent' => $a, 'ok' => $r === '', 'error' => $r]);
    if ($r !== '') json_response(['ok' => false, 'error' => $r], 422);
    json_response(['ok' => true, 'esencial_cent' => $e, 'atelier_cent' => $a]);
}

function padrino_cambia_marca(array $c): void {
    $n = clean_str($c['nombre'] ?? '', 40);
    $motivo = clean_str($c['motivo'] ?? '', 300);
    if ($motivo === '') json_response(['ok' => false, 'error' => 'Falta el motivo.'], 422);
    $r = con_cerrojo(function () use ($n, $motivo) {
        $f = padrino_dir('marca.json');
        $d = lee_json($f) ?? [];
        $err = padrino_marca_error($n, $d ?: null);
        if ($err !== '') return $err;
        $hist = (array) ($d['historial'] ?? []);
        $hist[] = ['desde' => date('c'), 'nombre' => $n, 'motivo' => $motivo];
        escribe_json($f, ['nombre' => $n, 'desde' => date('c'), 'historial' => array_slice($hist, -50)]);
        return '';
    });
    padrino_log('marca', ['nombre' => $n, 'ok' => $r === '', 'error' => $r]);
    if ($r !== '') json_response(['ok' => false, 'error' => $r], 422);
    json_response(['ok' => true, 'nombre' => $n]);
}
