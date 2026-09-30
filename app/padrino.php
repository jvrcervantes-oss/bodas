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
//  · `GET pedidos` (30-sep-2026, subtarea 2 de encargos/20260927_trabajador_autonomo.md, Seguridad #174):
//    el Tesorero del Padrino lee aquí los pedidos de Lemon Squeezy, con un token PROPIO (`tesoreria`): el de
//    lectura lo tiene también quien lee correo de desconocidos, y los ingresos no le hacen falta. Las claves de LS no tienen alcance
//    (la misma clave hace POST /v1/orders/{id}/refund), así que la clave NO sale de este servidor
//    (#109): este guarda relee LS con ella y devuelve una lista CERRADA de cifras y enums, sin email,
//    nombre, cliente ni recibo. Se lee LS y no pedidos/*.json porque así cuenta también un pedido
//    cobrado cuyo webhook falló.

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
    $d = padrino_precios_fichero();   // una sola lectura del fichero para packs y extras
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

// ------------------------------------------------------------ extras de pago (encargo 20260927_bodas_servicios_extra)
// Lista CERRADA: una clave que no esté aquí no existe (el panel responde 400). Cada extra lleva su suelo y su
// techo propios (Administración #133): el suelo de 49 € de los packs rechazaría un extra de 19 € y el Padrino
// fallaría en silencio al moverlo. Suelo = lo que cubre la comisión de Lemon Squeezy (5 % + 0,50 €) y el trabajo;
// en el dominio, además, lo que cuesta el dominio un año. 'venta' = se puede comprar ya; los demás esperan a su
// fase (F2 álbum, F3 idiomas, F4 dominio) y existen aquí para que el precio tenga desde hoy una sola fuente.
// 'panel' = la página del panel que abre el extra (a la que vuelve el pago). Precios con IVA incluido.
// 'incluido' = va GRATIS en todos los packs (pagados y regalados) y no se vende: el plano de mesas desde el 27-sep-2026
// (decisión del owner). Sigue en la lista para que las compras de prueba ya hechas se lean y se etiqueten, y para que
// /panel/extra responda 409 («no está a la venta») y no 400. Su cent/suelo/techo ya no se usan para cobrar nada.
const EXTRAS = [
    'mesas' => ['nombre' => 'Plano de mesas', 'cent' => 1900, 'suelo' => 900, 'techo' => 4900, 'venta' => false, 'incluido' => true, 'panel' => 'mesas',
        'desc' => 'Plano de mesas en vuestro panel, con la hoja para el restaurante: cada mesa con sus personas, su menú y sus alergias.'],
    'idiomas' => ['nombre' => 'Dos idiomas', 'cent' => 2500, 'suelo' => 1200, 'techo' => 6900, 'venta' => false, 'panel' => '',
        'desc' => 'Vuestra web también en inglés.'],
    'album' => ['nombre' => 'Álbum de invitados', 'cent' => 1900, 'suelo' => 900, 'techo' => 4900, 'venta' => false, 'panel' => '',
        'desc' => 'Vuestros invitados suben sus fotos de la boda y vosotros decidís cuáles se ven.'],
    'dominio' => ['nombre' => 'Dominio propio', 'cent' => 2900, 'suelo' => 1900, 'techo' => 7900, 'venta' => false, 'panel' => '',
        'desc' => 'Vuestra web con un dominio propio.'],
];

/** ¿Es una clave de la lista cerrada? */
function extra_existe(string $clave): bool { return isset(EXTRAS[$clave]); }

/** ¿Va incluido en todos los packs (sin compra)? Entonces lo abre extra_activo() para toda web en pie, sin mirar pedido.json. */
function extra_incluido(string $clave): bool { return extra_existe($clave) && !empty(EXTRAS[$clave]['incluido']); }

/**
 * ¿Tiene esta boda el extra activo? $bp = su pedido.json. `extras.<clave>` lo escribe SOLO el webhook de
 * Lemon (lemon_extra) y lo da de baja solo el reembolso (Seguridad #133): {desde, pedido[, baja]}.
 */
function extra_activo_en(array $bp, string $clave): bool {
    $e = ((array) ($bp['extras'] ?? []))[$clave] ?? null;
    return extra_existe($clave) && is_array($e) && (string) ($e['pedido'] ?? '') !== '' && empty($e['baja']);
}

/**
 * Precio vigente de un extra: el que decidió el Padrino si existe y está dentro de los límites del
 * código; si no, el del código. Nunca se escribe a mano en un texto. 0 = clave desconocida.
 */
function precio_extra_cent(string $clave): int {
    if (!extra_existe($clave)) return 0;
    $v = (int) ((padrino_precios_fichero()['extras'] ?? [])[$clave] ?? 0);
    return padrino_precio_extra_error($clave, $v, null) === '' ? $v : (int) EXTRAS[$clave]['cent'];
}

/** precios.json tal cual, leído una vez por petición. */
function padrino_precios_fichero(): array {
    static $d = null;
    return $d ??= (lee_json(padrino_dir('precios.json')) ?? []);
}

/** Céntimos de escaparate: .00, .50, .90 o .95 (la misma regla para packs y extras). */
function padrino_centimos_ok(int $c): bool { return in_array($c % 100, [0, 50, 90, 95], true); }

/**
 * '' si el precio del extra es aceptable; si no, el motivo. $desde = cuándo cambió por última vez ESTE
 * extra, o null al leer el fichero (entonces solo se miran los límites). Paso y frecuencia son los de los
 * packs pero por extra: mover el de mesas no bloquea el de idiomas.
 */
function padrino_precio_extra_error(string $clave, int $cent, ?string $desde, int $vigente = 0): string {
    if (!extra_existe($clave)) return 'Extra desconocido.';
    if (extra_incluido($clave)) return 'Va incluido en todos los packs: no tiene precio.';   // el Padrino no le pone precio
    $x = EXTRAS[$clave];
    if ($cent < $x['suelo']) return 'Por debajo del suelo de ' . euros($x['suelo']) . ' para ' . $x['nombre'] . '.';
    if ($cent > $x['techo']) return 'Por encima del techo de ' . euros($x['techo']) . ' para ' . $x['nombre'] . '.';
    if (!padrino_centimos_ok($cent)) return 'Céntimos no admitidos: .00, .50, .90 o .95.';
    if ($desde === null) return '';
    if ($vigente > 0 && abs($cent - $vigente) / $vigente > PADRINO_PASO_MAX + 1e-9) return 'Cambio de más del ' . (int) (PADRINO_PASO_MAX * 100) . ' % de una vez.';
    if ($desde !== '' && strtotime($desde) > time() - PADRINO_DIAS_ENTRE_CAMBIOS * 86400) return 'Solo un cambio de precio de cada extra cada ' . PADRINO_DIAS_ENTRE_CAMBIOS . ' días.';
    return '';
}

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
    if (!padrino_centimos_ok($e) || !padrino_centimos_ok($a)) return 'Céntimos no admitidos: .00, .50, .90 o .95.';
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
    $contenido = $sub === 'contenido' || strpos($sub, 'contenido/') === 0;
    $escritura = in_array($sub, ['precios', 'marca', 'campanas'], true);
    $tesoreria = $sub === 'pedidos';   // solo el Tesorero: token propio, nunca el de lectura
    if ($contenido && !limite('padrino-contenido|' . ip_cliente(), 30, 3600, true)) json_response(['ok' => false, 'error' => 'limite'], 429);
    if (!padrino_autorizado($contenido ? 'contenido' : ($escritura ? 'decision' : ($tesoreria ? 'tesoreria' : 'lectura')))) {
        padrino_log('rechazo', ['ruta' => $sub, 'ip' => hash('sha256', ip_cliente())]);
        json_response(['ok' => false], 401);
    }
    $cuerpo = $metodo === 'POST' ? json_decode((string) file_get_contents('php://input', false, null, 0, 10000), true) : null;
    if ($metodo === 'POST' && !is_array($cuerpo)) json_response(['ok' => false, 'error' => 'JSON no válido'], 400);
    switch ($sub) {
        case 'resumen':
            if ($metodo !== 'GET') json_response(['ok' => false], 405);
            json_response(padrino_resumen());
        case 'pedidos':
            if ($metodo !== 'GET') json_response(['ok' => false], 405);
            // Cada llamada son varias peticiones a LS: su propio límite, mucho más corto (el Tesorero lee 1 vez al día)
            if (!limite('padrino-pedidos|' . ip_cliente(), 12, 3600, true)) json_response(['ok' => false, 'error' => 'limite'], 429);
            @set_time_limit(180);   // el tope de verdad es PADRINO_PEDIDOS_SEGUNDOS: set_time_limit no cuenta la red
            try {
                json_response(padrino_pedidos());
            } catch (RuntimeException $e) {
                // Nunca una lista a medias: el Tesorero falla cerrado y avisa, en vez de contar menos ventas
                registra('padrino: pedidos de LS no leídos', ['motivo' => $e->getMessage()]);
                json_response(['ok' => false, 'error' => 'lemon'], 502);
            }
        case 'remitente':
            if ($metodo !== 'POST') json_response(['ok' => false], 405);
            json_response(padrino_remitente((string) ($cuerpo['email'] ?? '')));
        case 'precios':
            if ($metodo !== 'POST') json_response(['ok' => false], 405);
            // {extra, cent, motivo} mueve el precio de un extra; {esencial_cent, atelier_cent, motivo}, el de los packs
            if (isset($cuerpo['extra'])) padrino_cambia_precio_extra($cuerpo);
            padrino_cambia_precios($cuerpo);
        case 'marca':
            if ($metodo !== 'POST') json_response(['ok' => false], 405);
            padrino_cambia_marca($cuerpo);
        case 'campanas':
            if ($metodo !== 'POST') json_response(['ok' => false], 405);
            padrino_alta_campana($cuerpo);
        case 'contenido': case 'contenido/retirar': case 'contenido/restaurar':
            if ($metodo !== 'POST') json_response(['ok' => false], 405);
            padrino_contenido((string) substr($sub, 10), $cuerpo);
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
        if (!$arch) foreach (rsvp_vigentes($slug) as $r) $conf += count(personas($r));   // sin las sustituidas por un reenvío
        $fecha = (string) ($cfg['fecha'] ?? '');
        $bodas[] = [
            'slug' => $slug,
            'creada' => (string) ($ped['creado'] ?? ''),
            'fecha_boda' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) ? $fecha : '',
            'pack' => ((string) ($cfg['atelier'] ?? '')) !== '' ? 'atelier' : 'esencial',
            'origen' => str_starts_with((string) ($ped['session_id'] ?? ''), 'cortesia_') ? 'regalo' : 'pago',
            'archivada' => $arch,
            'confirmados' => $conf,
            // Extras COMPRADOS y en vigor; uno incluido en los packs no cuenta aunque se comprara en pruebas antes de serlo
            'extras' => array_values(array_filter(array_keys(EXTRAS), fn($k) => !extra_incluido($k) && extra_activo_en($ped, $k))),
            'borrado_en' => $fecha !== '' ? fecha_borrado($fecha) : '',
        ];
    }
    $pedidos = [];
    foreach (glob(dir_datos('pedidos', '*.json')) ?: [] as $f) {
        $p = lee_json($f);
        if (!$p) continue;
        $extra = ($p['tipo'] ?? '') === 'extra';
        $pedidos[] = [
            'ref' => substr(hash('sha256', (string) ($p['session_id'] ?? basename($f))), 0, 16),
            'fecha' => (string) ($p['creado'] ?? ''),
            // Un extra no es un pack (Administración #133): sin esto, un plano de mesas de 19 € contaría como un Esencial vendido
            'pack' => $extra ? '' : (((string) ($p['atelier'] ?? '')) !== '' || ($p['tipo'] ?? '') === 'mejora' ? 'atelier' : 'esencial'),
            'extra' => $extra && extra_existe((string) ($p['clave'] ?? '')) ? (string) $p['clave'] : '',
            'total_cent' => (int) ($p['importe']['total'] ?? 0),
            'estado' => (string) ($p['estado'] ?? ''),
            'regalo' => str_starts_with((string) ($p['session_id'] ?? ''), 'cortesia_'),
            // Con LS el IVA lo liquida LS y los pedidos de prueba no son ingresos (Tesorero: encargo aparte)
            'pasarela' => (string) ($p['pasarela'] ?? (str_starts_with((string) ($p['session_id'] ?? ''), 'cortesia_') ? '' : 'stripe')),
            // 'alta' = web nueva; 'mejora' = paso de Esencial a Atelier de una boda que ya existía (no es una venta nueva)
            // 'extra' = un servicio suelto de una boda que ya existía (plano de mesas…): tampoco es una venta nueva
            'tipo' => ($p['tipo'] ?? '') === 'mejora' ? 'mejora' : ($extra ? 'extra' : 'alta'),
            'test' => !empty($p['ls']['test']),
            'reembolsado_cent' => (int) ($p['reembolso']['importe_cent'] ?? 0),
        ];
    }
    $guias = array_map(fn($g) => ['slug' => $g['slug'], 'version' => (int) $g['version'], 'publicada' => (string) $g['publicada'], 'retirada' => !empty($g['retirada'])], guias_todas(true));
    $precios = padrino_precios() + ['extras' => array_map(fn($k) => ['clave' => $k, 'cent' => precio_extra_cent($k), 'suelo' => EXTRAS[$k]['suelo'],
        'techo' => EXTRAS[$k]['techo'], 'venta' => EXTRAS[$k]['venta']], array_values(array_filter(array_keys(EXTRAS), fn($k) => !extra_incluido($k))))];
    return ['ok' => true, 'generado' => date('c'), 'marca' => marca(), 'precios' => $precios, 'bodas' => $bodas, 'pedidos' => $pedidos,
        'analitica' => analitica_resumen(), 'campanas' => (array) ((lee_json(padrino_dir('campanas.json')) ?? [])['ids'] ?? []), 'guias' => $guias];
}

const PADRINO_PEDIDOS_POR_PAGINA = 100;   // el máximo que admite LS (docs.lemonsqueezy.com/api/getting-started/requests)
const PADRINO_PEDIDOS_SEGUNDOS = 150;     // se corta aquí (el cliente del Padrino espera 180 s): no seguir llamando
                                          // a LS cuando ya nadie espera la respuesta
const PADRINO_PEDIDOS_MAX_PAGINAS = 50;   // 5.000 pedidos: pasado esto, error y no una lista cortada. Antes de llegar,
                                          // la integración Bodas -> ERP sustituye esta relectura entera (fuente interina)

/**
 * Pedidos de la tienda de LS para el Tesorero del Padrino. Lista CERRADA de campos: cifras, enums y fechas.
 * Fuera user_email, user_name, customer_id, identifier y urls (el recibo es una URL firmada). Todo o nada:
 * si una página falla, el total no cuadra, la tienda no es la nuestra o se pasa del tope de páginas, lanza
 * RuntimeException y la ruta responde 502. Solo en live: en modo test, o si aparece un pedido con test_mode:true con la
 * clave live, también 502 (si no, el Tesorero descartaría todos y contaría 0 ventas sin avisar). test_mode viaja igual
 * y el Tesorero lo vuelve a filtrar (lo prueba él).
 * `$api` es inyectable para las pruebas; por defecto, lemon_api() con la clave de secrets.php.
 */
function padrino_pedidos(?callable $api = null, int $segundos = PADRINO_PEDIDOS_SEGUNDOS, ?bool $modo_test = null): array {
    $inicio = microtime(true);
    $api = $api ?? 'lemon_api';
    // Con la clave de test LS solo enseña pedidos de prueba: el Tesorero los descartaría y contaría 0 ventas sin avisar.
    // Esta lectura solo tiene sentido en live (Datos, 30-sep-2026): en test, o si aparece un pedido de prueba con la
    // clave live, falla cerrada.
    if ($modo_test ?? lemon_test()) throw new RuntimeException('Lemon Squeezy en modo test');
    $tienda = (string) secreto('lemon_tienda');
    if (!preg_match('/^\d{1,12}$/', $tienda)) throw new RuntimeException('tienda sin configurar');
    $vistos = [];
    $total_ls = null;
    for ($pagina = 1; ; $pagina++) {
        if ($pagina > PADRINO_PEDIDOS_MAX_PAGINAS) throw new RuntimeException('más de ' . PADRINO_PEDIDOS_MAX_PAGINAS . ' páginas');
        if (microtime(true) - $inicio > $segundos) throw new RuntimeException('LS tarda más de ' . $segundos . ' s');
        // sort explícito (el de los `links` de LS): no depender del orden por defecto entre páginas
        $q = http_build_query(['filter' => ['store_id' => $tienda], 'sort' => '-createdAt',
            'page' => ['size' => PADRINO_PEDIDOS_POR_PAGINA, 'number' => $pagina]]);
        [$st, $d] = $api('GET', '/v1/orders?' . $q);
        if ($st !== 200 || !is_array($d['data'] ?? null)) throw new RuntimeException('LS respondió ' . $st . ' en la página ' . $pagina);
        $meta = (array) (($d['meta'] ?? [])['page'] ?? []);
        if ($total_ls === null) $total_ls = (int) ($meta['total'] ?? -1);
        foreach ($d['data'] as $o) {
            $id = (string) ($o['id'] ?? '');
            $a = (array) ($o['attributes'] ?? []);
            if (!preg_match('/^\d{1,15}$/', $id)) throw new RuntimeException('pedido sin id');
            if ((string) ($a['store_id'] ?? '') !== $tienda) throw new RuntimeException('pedido de otra tienda');
            if (($a['test_mode'] ?? null) === true) throw new RuntimeException('pedido de prueba con la clave live');
            $vistos[$id] = padrino_pedido_cerrado($id, $a);   // por id: si un pedido nuevo desplaza la paginación, no se cuenta dos veces
        }
        $ultima = (int) ($meta['lastPage'] ?? 0);
        if ($ultima < 1) {
            if ($total_ls === 0 && !$d['data']) break;   // tienda sin pedidos
            throw new RuntimeException('LS sin paginación');
        }
        if ($pagina >= $ultima) break;
    }
    if ($total_ls < 0 || count($vistos) < $total_ls) throw new RuntimeException('faltan pedidos: ' . count($vistos) . ' de ' . $total_ls);
    return ['ok' => true, 'generado' => date('c'), 'fuente' => 'lemonsqueezy', 'pedidos' => array_values($vistos)];
}

/** Un pedido de LS reducido a la lista cerrada. Lo que no tiene la forma esperada sale como null, nunca como texto libre. */
function padrino_pedido_cerrado(string $id, array $a): array {
    $ent = fn($v) => is_int($v) ? $v : (is_string($v) && preg_match('/^-?\d{1,12}$/', $v) ? (int) $v : null);
    $fecha = fn($v) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}T[0-9:.]+(Z|[+-]\d{2}:?\d{2})$/', $v) ? $v : null;
    $estado = (string) ($a['status'] ?? '');
    $moneda = (string) ($a['currency'] ?? '');
    $tasa = $a['tax_rate'] ?? null;
    return [
        'id' => $id,
        'created_at' => $fecha($a['created_at'] ?? null),
        'currency' => preg_match('/^[A-Z]{3}$/', $moneda) ? $moneda : null,
        'subtotal' => $ent($a['subtotal'] ?? null),
        'discount_total' => $ent($a['discount_total'] ?? null),
        'setup_fee' => $ent($a['setup_fee'] ?? null),
        'tax' => $ent($a['tax'] ?? null),
        'total' => $ent($a['total'] ?? null),
        'refunded_amount' => $ent($a['refunded_amount'] ?? null),
        'status' => preg_match('/^[a-z_]{1,20}$/', $estado) ? $estado : null,
        'refunded' => is_bool($a['refunded'] ?? null) ? $a['refunded'] : null,
        'refunded_at' => $fecha($a['refunded_at'] ?? null),
        'test_mode' => is_bool($a['test_mode'] ?? null) ? $a['test_mode'] : null,   // null = el Tesorero lo descarta
        'tax_rate' => (is_int($tasa) || is_float($tasa) || (is_string($tasa) && preg_match('/^\d{1,3}(\.\d{1,4})?$/', $tasa))) ? (string) $tasa : null,
        'tax_inclusive' => is_bool($a['tax_inclusive'] ?? null) ? $a['tax_inclusive'] : null,
        'affiliate' => ($a['affiliate_id'] ?? null) !== null,
        'referral_amount' => $ent($a['referral_amount'] ?? null),
    ];
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
    $r = padrino_guarda_precios($e, $a, $motivo);
    padrino_log('precios', ['esencial_cent' => $e, 'atelier_cent' => $a, 'ok' => $r === '', 'error' => $r]);
    if ($r !== '') json_response(['ok' => false, 'error' => $r], 422);
    json_response(['ok' => true, 'esencial_cent' => $e, 'atelier_cent' => $a]);
}

/** Aplica el cambio de precio de los packs bajo el cerrojo. '' si se guardó; si no, el motivo (se prueba sin HTTP). */
function padrino_guarda_precios(int $e, int $a, string $motivo): string {
    return con_cerrojo(function () use ($e, $a, $motivo) {
        $f = padrino_dir('precios.json');
        $d = lee_json($f) ?? [];
        $vig = padrino_precios();
        $err = padrino_precio_error($e, $a, $vig);
        if ($err !== '') return $err;
        $hist = (array) ($d['historial'] ?? []);
        // El historial arranca con el precio del código, para que el de referencia de 30 días exista (se mira si
        // ya hay alguna entrada de packs: las de los extras comparten el historial y no cuentan)
        if (!array_filter($hist, fn($h) => is_array($h) && isset($h['esencial_cent']))) $hist[] = ['desde' => '', 'esencial_cent' => PRECIO_PACK_CENT, 'atelier_cent' => PRECIO_PACK_ATELIER_CENT, 'motivo' => 'precio inicial del owner'];
        $ahora = date('c');
        $hist[] = ['desde' => $ahora, 'esencial_cent' => $e, 'atelier_cent' => $a, 'motivo' => $motivo];
        // Se conserva el resto del fichero (precios de los extras y sus fechas): reescribirlo solo con
        // estas claves borraría en silencio lo que el Padrino decidió para los extras
        escribe_json($f, ['esencial_cent' => $e, 'atelier_cent' => $a, 'vigor_desde' => $ahora, 'historial' => array_slice($hist, -200)] + $d);
        return '';
    });
}

/**
 * Precio de un extra: al mismo historial que los packs (el precio de referencia de 30 días de Ómnibus sale
 * de ahí), con la clave del extra en cada entrada. La primera vez se apunta también el precio del código.
 */
function padrino_cambia_precio_extra(array $c): void {
    $clave = (string) ($c['extra'] ?? '');
    $cent = (int) ($c['cent'] ?? 0);
    $motivo = clean_str($c['motivo'] ?? '', 300);
    if ($motivo === '') json_response(['ok' => false, 'error' => 'Falta el motivo.'], 422);
    $r = padrino_guarda_precio_extra($clave, $cent, $motivo);
    padrino_log('precio-extra', ['extra' => $clave, 'cent' => $cent, 'ok' => $r === '', 'error' => $r]);
    if ($r !== '') json_response(['ok' => false, 'error' => $r], 422);
    json_response(['ok' => true, 'extra' => $clave, 'cent' => $cent]);
}

/** Aplica el cambio de precio de un extra bajo el cerrojo. '' si se guardó; si no, el motivo (se prueba sin HTTP). */
function padrino_guarda_precio_extra(string $clave, int $cent, string $motivo): string {
    return con_cerrojo(function () use ($clave, $cent, $motivo) {
        $f = padrino_dir('precios.json');
        $d = lee_json($f) ?? [];
        $desde = (string) (((array) ($d['extras_desde'] ?? []))[$clave] ?? '');
        // El vigente se lee del fichero de ESTE momento (bajo el cerrojo), no de la caché de la petición
        $vig = (int) (((array) ($d['extras'] ?? []))[$clave] ?? 0);
        if (!extra_existe($clave)) return 'Extra desconocido.';
        if (padrino_precio_extra_error($clave, $vig, null) !== '') $vig = (int) EXTRAS[$clave]['cent'];
        $err = padrino_precio_extra_error($clave, $cent, $desde, $vig);
        if ($err !== '') return $err;
        $hist = (array) ($d['historial'] ?? []);
        if (!array_filter($hist, fn($h) => is_array($h) && ($h['extra'] ?? '') === $clave)) {
            $hist[] = ['desde' => '', 'extra' => $clave, 'cent' => (int) EXTRAS[$clave]['cent'], 'motivo' => 'precio inicial del owner'];
        }
        $ahora = date('c');
        $hist[] = ['desde' => $ahora, 'extra' => $clave, 'cent' => $cent, 'motivo' => $motivo];
        $d['extras'] = array_merge((array) ($d['extras'] ?? []), [$clave => $cent]);
        $d['extras_desde'] = array_merge((array) ($d['extras_desde'] ?? []), [$clave => $ahora]);
        $d['historial'] = array_slice($hist, -200);
        escribe_json($f, $d);
        return '';
    });
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

/** Alta de un id de campaña para los enlaces con utm_campaign (solo cuentan los dados de alta). */
function padrino_alta_campana(array $c): void {
    $id = strtolower(clean_str($c['id'] ?? '', 40));
    if (!preg_match('/^[a-z0-9-]{1,32}$/', $id)) json_response(['ok' => false, 'error' => 'Id: a-z, 0-9 y guion, hasta 32.'], 422);
    $n = muta_json(padrino_dir('campanas.json'), function (array &$d) use ($id) {
        $ids = (array) ($d['ids'] ?? []);
        if (!in_array($id, $ids, true)) { if (count($ids) >= 200) return -1; $ids[] = $id; }
        $d['ids'] = $ids;
        return count($ids);
    });
    if ($n === -1) json_response(['ok' => false, 'error' => 'Máximo 200 campañas.'], 422);
    padrino_log('campana', ['id' => $id]);
    json_response(['ok' => true, 'id' => $id]);
}
