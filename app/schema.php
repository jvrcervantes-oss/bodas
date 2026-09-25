<?php
// Forma del config.json de una boda: la fuente única de todo lo que se ve.
// El creador, la vista previa, la web publicada y el ZIP salen de aquí. Nada del
// cliente se guarda sin pasar por normaliza_config(): lista cerrada de claves, cada
// texto con su tope, y lo que no encaja se descarta (no se "arregla" en silencio
// hacia algo plausible, se queda vacío y lo marca faltan()).

declare(strict_types=1);

const TEMAS = [
    // clave => [nombre, primary, accent, accent-hover, secondary, sage, sage-2, sage-2-hover, gold, on-dark, on-dark-soft]
    'rosa'      => ['Rosa empolvado', '#7E4744', '#A86B68', '#925856', '#7E6461', '#FCF4F2', '#F3DEDB', '#EDD2CE', '#8A7350', '#F9E5E3', '#E9C6C2'],
    'champagne' => ['Champagne', '#5E4B2A', '#8A7350', '#74603F', '#6E6353', '#FAF6F0', '#EDE2CF', '#E5D7C0', '#A86B68', '#F2E6D2', '#DCCCB0'],
    'eucalipto' => ['Eucalipto', '#2C5448', '#446C5F', '#37574D', '#506357', '#F2F5F0', '#D3E8D9', '#C8DDCE', '#815D40', '#C0EBDB', '#A5D0C0'],
    'terracota' => ['Terracota', '#6E3B2A', '#9A5238', '#7F422D', '#7A5A4C', '#F7F1EC', '#EFD9CB', '#E6CBB9', '#8A6A2F', '#F5D6C6', '#E4B9A4'],
    'oceano'    => ['Océano',    '#23445E', '#3C6482', '#30526C', '#4F6475', '#F0F4F7', '#D4E2EC', '#C5D6E2', '#8A6B3D', '#CFE3F2', '#AECBE0'],
    'lavanda'   => ['Lavanda',   '#4E3F6B', '#7A69A0', '#665688', '#6A6378', '#F5F3F9', '#E3DDF0', '#D8D0EA', '#8A7350', '#E6DEF7', '#CDC2EA'],
    'arena'     => ['Arena',     '#5A4A3A', '#9A8266', '#826C53', '#6F6356', '#FAF7F2', '#EEE5D8', '#E6DACA', '#A86B68', '#F1E6D6', '#DDCDB6'],
    'burdeos'   => ['Burdeos',   '#5C1F2B', '#8A3344', '#742A39', '#6E4E54', '#FAF3F4', '#F0DDE0', '#E8CFD3', '#8A7350', '#F5D9DE', '#E6BCC4'],
    'malva'     => ['Malva',     '#5A3651', '#7D5271', '#68435E', '#6E5A68', '#F6F1F4', '#E8D8E2', '#DDC8D5', '#8A6440', '#EED3E5', '#D8B6CC'],
];

// Menús: desde el 25-sep-2026 cada boda define los suyos ({id, nombre, descripcion, infantil}).
// Esta tabla es solo la de antes (claves fijas): convierte los config y las respuestas viejos,
// cuyo id de menú ES esa clave, para que sigan casando.
// Tipografías (owner, 25-sep-2026): clave => [nombre, títulos, textos, nombres de la pareja, estilo de los nombres]
const FUENTES = [
    'clasica'     => ['Clásica',     "'Playfair Display', Georgia, serif",   "'Manrope', system-ui, sans-serif",           "'Playfair Display', Georgia, serif",   'italic'],
    'romantica'   => ['Romántica',   "'Cormorant Garamond', Georgia, serif", "'Manrope', system-ui, sans-serif",           "'Cormorant Garamond', Georgia, serif", 'italic'],
    'caligrafica' => ['Caligráfica', "'Playfair Display', Georgia, serif",   "'Manrope', system-ui, sans-serif",           "'Great Vibes', cursive",               'normal'],
    'moderna'     => ['Moderna',     "'Plus Jakarta Sans', system-ui, sans-serif", "'Plus Jakarta Sans', system-ui, sans-serif", "'Plus Jakarta Sans', system-ui, sans-serif", 'normal'],
];

// Colección Atelier (owner, 25-sep-2026; diseño de Stitch «Creatividades de autor»). Cada diseño
// trae su paleta, sus letras y su ilustración, y sustituye a paleta/tipografía/decoración.
// colores: mismo orden que TEMAS (primary, accent, accent-hover, secondary, sage, sage-2, sage-2-hover, gold, on-dark, on-dark-soft)
// fuentes: títulos, textos, nombres, estilo de los nombres
const ATELIER = [
    'citricos' => ['nombre' => 'Cítricos y azahar', 'categoria' => 'Mediterráneo', 'desc' => 'Limones y olivo en acuarela sobre papel verjurado.',
        'colores' => ['#3F4A3B', '#5C6B57', '#4D5A49', '#6E6A55', '#FAF7F2', '#F1E8C9', '#E9DEB8', '#B98E1F', '#F4ECCB', '#DCD3AE'],
        'fuentes' => ["'Playfair Display', Georgia, serif", "'Manrope', system-ui, sans-serif", "'Alex Brush', cursive", 'normal']],
    'ceramica' => ['nombre' => 'Cerámica y azulejo', 'categoria' => 'Talavera', 'desc' => 'Marco de azulejo azul cobalto y oro para vuestra foto.',
        'colores' => ['#183B75', '#1F4C94', '#183B75', '#3E5A86', '#F5F3ED', '#DCE6F0', '#CCDAE8', '#A8864A', '#DCE7F4', '#A9C2D8'],
        'fuentes' => ["'Cinzel', Georgia, serif", "'Manrope', system-ui, sans-serif", "'Pinyon Script', cursive", 'normal']],
    'herbario' => ['nombre' => 'Herbario y lavanda', 'categoria' => 'Botánica', 'desc' => 'Pliego de flores prensadas y lavanda silvestre.',
        'colores' => ['#4A3E4C', '#645166', '#54445A', '#5A674E', '#F3EFE6', '#E6DECE', '#DCD2BE', '#5A674E', '#EDE3EE', '#D2C4D4'],
        'fuentes' => ["'Italiana', Georgia, serif", "'Newsreader', Georgia, serif", "'Italiana', Georgia, serif", 'normal']],
    'lacre' => ['nombre' => 'Sello de lacre', 'categoria' => 'Editorial', 'desc' => 'Lino, tinta y un sello de cera con vuestras iniciales.',
        'colores' => ['#3A2A20', '#9C694E', '#85573F', '#6B5A4B', '#EFE8DE', '#E3D7C5', '#D8CAB4', '#B39355', '#F2E3D4', '#DCC6AE'],
        'fuentes' => ["'Libre Baskerville', Georgia, serif", "'Manrope', system-ui, sans-serif", "'Playfair Display', Georgia, serif", 'italic']],
    'masia' => ['nombre' => 'Boceto de masía', 'categoria' => 'Tinta y arquitectura', 'desc' => 'Grabado a plumilla de una masía, como un cuaderno de viaje.',
        'colores' => ['#38312B', '#A6634B', '#8E533E', '#4A5844', '#F8F4EC', '#EADFCD', '#E1D4BE', '#A6634B', '#F3E2D6', '#DDC3B3'],
        'fuentes' => ["'Cinzel', Georgia, serif", "'EB Garamond', Georgia, serif", "'EB Garamond', Georgia, serif", 'italic']],
    'atardecer' => ['nombre' => 'Atardecer en cala', 'categoria' => 'Acuarela costera', 'desc' => 'Acuarela de puesta de sol y foto en ventana redonda.',
        'colores' => ['#8C4A3C', '#C9706C', '#B25E5A', '#A0705E', '#F9F3EB', '#F6DCCB', '#F0CFBA', '#B8912A', '#FCE6DA', '#F0C4B0'],
        'fuentes' => ["'Cormorant Infant', Georgia, serif", "'Montserrat', system-ui, sans-serif", "'Cormorant Infant', Georgia, serif", 'italic']],
];

// Decoración (estructura visual), independiente de la paleta. Owner, 25-sep-2026.
// Las acuarelas son las de su maqueta de Stitch (assets/img/deco/).
const DECORACIONES = [
    'flores'    => ['Flores de acuarela', 'Guirnalda y enredaderas en pastel'],
    'eucalipto' => ['Eucalipto', 'Una rama sobre los nombres'],
    'sobre'     => ['Sobre', 'La invitación en un sobre con lacre'],
    'ninguna'   => ['Sin adornos', 'Solo tipografía y color'],
];

const MENUS_ANTIGUOS = ['carne' => 'Carne', 'pescado' => 'Pescado', 'vegetariano' => 'Vegetariano', 'vegano' => 'Vegano', 'infantil' => 'Infantil'];
const MAX_MENUS = 8;
const MAX_TRAYECTOS = 6;
const MAX_GALERIA = 24;

// tipo => [título por defecto, ruta fija (null = libre, sale del título), única]
const SECCIONES = [
    'rsvp'        => ['Confirmar asistencia', 'confirmar-asistencia', true],
    'informacion' => ['Información', 'informacion', true],
    'hoteles'     => ['Hoteles', 'hoteles', true],
    'transporte'  => ['Transporte', 'transporte', true],
    'regalos'     => ['Lista de bodas', 'lista-de-bodas', true],
    'musica'      => ['Música', 'musica', true],
    'dresscode'   => ['Dress code', 'dress-code', true],
    'galeria'     => ['Galería', 'galeria', true],
    'libro'       => ['Libro de invitados', 'libro-de-invitados', true],
    'libre'       => ['Nueva sección', null, false],
];
const MAX_LIBRES = 3;
// Rutas que una sección libre nunca puede ocupar
const RUTAS_RESERVADAS = ['api', 'panel', 'privacidad', 'foto', 'boda', 'assets', 'inicio', 'index', 'crear', 'listo', 'legal', 'acceso', 'g', 'l'];

// Nombres de web que no se venden: técnicos del estudio o que se prestan a suplantación
const SLUGS_RESERVADOS = ['www', 'api', 'admin', 'panel', 'mail', 'correo', 'smtp', 'ftp', 'bodas', 'crear', 'static',
    'assets', 'cdn', 'app', 'demo', 'test', 'dev', 'staging', 'soporte', 'support', 'ayuda', 'help', 'login', 'pago',
    'pagos', 'stripe', 'factura', 'facturas', 'axisworks', 'lawang', 'eduycora', 'b2k', 'sumba', 'blog', 'shop', 'tienda'];
const SLUG_RE = '/^[a-z0-9](?:[a-z0-9-]{1,38}[a-z0-9])$/';

function slug_valido(string $s): bool {
    return (bool) preg_match(SLUG_RE, $s) && strpos($s, '--') === false && !in_array($s, SLUGS_RESERVADOS, true);
}

function slugify(string $s, int $max = 40): string {
    $s = mb_strtolower($s, 'UTF-8');
    $s = strtr($s, ['á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e',
        'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i', 'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o',
        'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u', 'ñ' => 'n', 'ç' => 'c', '&' => 'y']);
    $s = (string) preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim(substr(trim($s, '-'), 0, $max), '-');
}

/** Config de arranque del creador. Textos genéricos que la pareja reescribe; ningún dato inventado. */
function config_inicial(): array {
    $sec = [];
    $n = 0;
    foreach (['rsvp', 'informacion', 'hoteles', 'transporte', 'regalos', 'musica', 'dresscode', 'galeria', 'libro'] as $t) {
        // Galería y libro, apagadas de inicio: piden código de acceso y la galería se llena desde el panel
        $sec[] = ['id' => 's' . (++$n), 'tipo' => $t, 'on' => !in_array($t, ['galeria', 'libro'], true), 'titulo' => SECCIONES[$t][0], 'datos' => datos_iniciales($t)];
    }
    return [
        'v' => 1,
        'pareja' => ['nombre1' => '', 'nombre2' => '', 'email' => ''],
        'fecha' => '',
        'ciudad' => '',
        'tema' => 'rosa',
        'decoracion' => 'flores',
        'fuente' => 'clasica',
        'atelier' => '',
        'ceremonia' => ['lugar' => '', 'direccion' => '', 'hora' => ''],
        'convite' => ['lugar' => '', 'direccion' => '', 'hora' => '', 'mismo' => false],
        'portada' => [
            'invitacion' => 'Tenemos el placer de invitaros a nuestra boda',
            'titulo' => 'Os damos la bienvenida',
            'frase' => '',
            'texto' => 'Queremos compartir este día con las personas que más queremos. Gracias por acompañarnos.',
            'pie' => 'Gracias por formar parte de nuestra historia.',
        ],
        'foto' => false,
        'codigo' => '',
        'secciones' => $sec,
    ];
}

function datos_iniciales(string $tipo): array {
    switch ($tipo) {
        case 'rsvp': return ['texto' => 'Si venís en familia o en grupo, basta con que uno lo rellene por todos.', 'asistencia' => true, 'fecha_limite' => '',
            'menus' => [menu_nuevo('carne', 'Carne'), menu_nuevo('pescado', 'Pescado'), menu_nuevo('vegetariano', 'Vegetariano'), menu_nuevo('infantil', 'Infantil', true)]];
        case 'informacion': return ['texto' => ''];
        case 'hoteles': return ['texto' => 'Opciones de alojamiento cerca de la celebración.', 'hoteles' => []];
        case 'transporte': return ['texto' => '', 'trayectos' => [], 'preguntar' => true];
        case 'regalos': return ['texto' => 'Vuestra compañía es el mejor regalo. Si además queréis tener un detalle con nosotros, podéis hacerlo aquí.', 'titular' => '', 'iban' => '', 'otro' => ''];
        case 'musica': return ['texto' => 'Proponed la canción que os hace saltar a la pista y votad las de los demás.'];
        case 'dresscode': return ['texto' => ''];
        case 'galeria': return ['texto' => '', 'fotos' => [], 'consentido' => false];
        case 'libro': return ['texto' => 'Dejadnos un mensaje, un recuerdo o una foto de la boda.', 'fotos' => true];
        default: return ['texto' => ''];
    }
}

function norm_hora($v): string {
    $v = clean_str($v, 5);
    return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $v) ? $v : '';
}
function norm_fecha($v): string {
    $v = clean_str($v, 10);
    $t = DateTimeImmutable::createFromFormat('!Y-m-d', $v);
    return ($t && $t->format('Y-m-d') === $v) ? $v : '';
}
function norm_url($v): string {
    $v = clean_str($v, 300);
    if ($v === '') return '';
    if (!preg_match('~^https?://~i', $v)) $v = 'https://' . $v;
    $p = parse_url($v);
    if (!$p || empty($p['host']) || !in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true)) return '';
    if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $p['host'])) return '';
    return filter_var($v, FILTER_VALIDATE_URL) ? $v : '';
}
/** IBAN con su dígito de control comprobado: un número de regalos mal copiado cuesta dinero de verdad. */
function norm_iban($v): string {
    $v = strtoupper((string) preg_replace('/\s+/', '', clean_str($v, 50)));
    return iban_ok($v) ? trim(chunk_split($v, 4, ' ')) : '';
}
function iban_ok(string $v): bool {
    if (!preg_match('/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/', $v)) return false;
    $r = substr($v, 4) . substr($v, 0, 4);
    $num = '';
    foreach (str_split($r) as $ch) $num .= ctype_alpha($ch) ? (string) (ord($ch) - 55) : $ch;
    $mod = 0;
    foreach (str_split($num, 7) as $trozo) $mod = (int) (($mod . $trozo) % 97);
    return $mod === 1;
}
function norm_bool($v): bool { return $v === true || $v === 1 || $v === '1' || $v === 'si'; }

function menu_nuevo(string $id, string $nombre, bool $infantil = false): array {
    return ['id' => $id, 'nombre' => $nombre, 'descripcion' => '', 'infantil' => $infantil];
}

/** Menús de la confirmación. Acepta el formato viejo (lista de claves fijas) y lo convierte. */
function norm_menus($lista): array {
    $out = [];
    $ids = [];
    foreach (array_slice(is_array($lista) ? array_values($lista) : [], 0, MAX_MENUS) as $m) {
        if (is_string($m)) {                                   // formato antiguo
            if (!isset(MENUS_ANTIGUOS[$m])) continue;
            $m = menu_nuevo($m, MENUS_ANTIGUOS[$m], $m === 'infantil');
        }
        if (!is_array($m)) continue;
        $id = preg_match('/^[a-z0-9]{1,12}$/', (string) ($m['id'] ?? '')) ? $m['id'] : 'm' . bin2hex(random_bytes(3));
        if (isset($ids[$id])) $id = 'm' . bin2hex(random_bytes(3));
        $ids[$id] = true;
        $out[] = ['id' => $id, 'nombre' => clean_str($m['nombre'] ?? '', 40), 'descripcion' => clean_str($m['descripcion'] ?? '', 300),
            'infantil' => norm_bool($m['infantil'] ?? false)];
    }
    return $out ?: [menu_nuevo('general', 'Menú')];
}

function norm_trayectos($lista): array {
    $out = [];
    foreach (array_slice(is_array($lista) ? array_values($lista) : [], 0, MAX_TRAYECTOS) as $t) {
        if (!is_array($t)) continue;
        $out[] = ['titulo' => clean_str($t['titulo'] ?? '', 60), 'salida' => clean_str($t['salida'] ?? '', 140),
            'hora' => norm_hora($t['hora'] ?? ''), 'llegada' => clean_str($t['llegada'] ?? '', 140), 'nota' => clean_str($t['nota'] ?? '', 300)];
    }
    return $out;
}

function norm_datos(string $tipo, $d): array {
    $d = is_array($d) ? $d : [];
    $texto = clean_str($d['texto'] ?? '', 2000);
    switch ($tipo) {
        case 'rsvp':
            $r = ['texto' => clean_str($d['texto'] ?? '', 600), 'menus' => norm_menus($d['menus'] ?? null),
                'asistencia' => norm_bool($d['asistencia'] ?? true), 'fecha_limite' => norm_fecha($d['fecha_limite'] ?? '')];
            // La pregunta del autobús vivía aquí hasta el 25-sep: se conserva solo para migrarla a Transporte
            if (array_key_exists('bus', $d)) $r['_bus_antiguo'] = norm_bool($d['bus']);
            return $r;
        case 'galeria':
            $fs = [];
            foreach (array_slice(is_array($d['fotos'] ?? null) ? array_values($d['fotos']) : [], 0, MAX_GALERIA) as $f) {
                if (is_array($f) && preg_match('/^[a-f0-9]{16}$/', (string) ($f['id'] ?? ''))) $fs[] = ['id' => $f['id'], 'pie' => clean_str($f['pie'] ?? '', 140)];
            }
            return ['texto' => clean_str($d['texto'] ?? '', 600), 'fotos' => $fs, 'consentido' => norm_bool($d['consentido'] ?? false)];
        case 'libro':
            return ['texto' => clean_str($d['texto'] ?? '', 600), 'fotos' => norm_bool($d['fotos'] ?? true)];
        case 'transporte':
            return ['texto' => $texto, 'trayectos' => norm_trayectos($d['trayectos'] ?? null),
                'preguntar' => array_key_exists('preguntar', $d) ? norm_bool($d['preguntar']) : null];
        case 'hoteles':
            $hs = [];
            foreach (array_slice(is_array($d['hoteles'] ?? null) ? array_values($d['hoteles']) : [], 0, 8) as $x) {
                if (!is_array($x)) continue;
                $hs[] = ['nombre' => clean_str($x['nombre'] ?? '', 100), 'zona' => clean_str($x['zona'] ?? '', 100),
                    'web' => norm_url($x['web'] ?? ''), 'telefono' => clean_str($x['telefono'] ?? '', 40),
                    'nota' => clean_str($x['nota'] ?? '', 400)];
            }
            return ['texto' => clean_str($d['texto'] ?? '', 800), 'hoteles' => $hs];
        case 'regalos':
            return ['texto' => clean_str($d['texto'] ?? '', 800), 'titular' => clean_str($d['titular'] ?? '', 120),
                'iban' => norm_iban($d['iban'] ?? ''), 'otro' => clean_str($d['otro'] ?? '', 600)];
        default:
            return ['texto' => $texto];
    }
}

/**
 * Normaliza lo que llega del navegador contra la forma cerrada de arriba. Nunca lanza:
 * lo inválido se queda vacío. Las rutas de las secciones libres se calculan aquí.
 */
function normaliza_config($in): array {
    $in = is_array($in) ? $in : [];
    $c = config_inicial();
    $p = is_array($in['pareja'] ?? null) ? $in['pareja'] : [];
    $email = clean_str($p['email'] ?? '', 160);
    $c['pareja'] = ['nombre1' => clean_str($p['nombre1'] ?? '', 40), 'nombre2' => clean_str($p['nombre2'] ?? '', 40),
        'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : ''];
    $c['fecha'] = norm_fecha($in['fecha'] ?? '');
    $c['ciudad'] = clean_str($in['ciudad'] ?? '', 60);
    $c['tema'] = isset(TEMAS[$in['tema'] ?? '']) ? $in['tema'] : 'eucalipto';
    // Las bodas de antes de existir la decoración conservan su rama de eucalipto
    $c['decoracion'] = isset(DECORACIONES[$in['decoracion'] ?? '']) ? $in['decoracion'] : 'eucalipto';
    $c['fuente'] = isset(FUENTES[$in['fuente'] ?? '']) ? $in['fuente'] : 'clasica';
    $c['atelier'] = isset(ATELIER[$in['atelier'] ?? '']) ? $in['atelier'] : '';
    foreach (['ceremonia', 'convite'] as $k) {
        $e = is_array($in[$k] ?? null) ? $in[$k] : [];
        $c[$k] = ['lugar' => clean_str($e['lugar'] ?? '', 120), 'direccion' => clean_str($e['direccion'] ?? '', 160), 'hora' => norm_hora($e['hora'] ?? '')];
    }
    // Convite en el mismo sitio que la ceremonia: el lugar y la dirección SIEMPRE se toman de
    // la ceremonia (se recalculan en cada normalización, no pueden desviarse)
    $c['convite']['mismo'] = norm_bool($in['convite']['mismo'] ?? false);
    if ($c['convite']['mismo']) {
        $c['convite']['lugar'] = $c['ceremonia']['lugar'];
        $c['convite']['direccion'] = $c['ceremonia']['direccion'];
    }
    $po = is_array($in['portada'] ?? null) ? $in['portada'] : [];
    $c['portada'] = ['invitacion' => clean_str($po['invitacion'] ?? '', 120), 'titulo' => clean_str($po['titulo'] ?? '', 80),
        'frase' => clean_str($po['frase'] ?? '', 240), 'texto' => clean_str($po['texto'] ?? '', 1200), 'pie' => clean_str($po['pie'] ?? '', 200)];
    $c['foto'] = norm_bool($in['foto'] ?? false);
    // Código de acceso a la galería y al libro (owner, 25-sep): lo reparte la pareja en la invitación
    $c['codigo'] = (string) preg_replace('/[^A-Za-z0-9-]/', '', clean_str($in['codigo'] ?? '', 20));

    $sec = [];
    $vistos = [];
    $libres = 0;
    $rutas = [];
    foreach (array_slice(is_array($in['secciones'] ?? null) ? array_values($in['secciones']) : [], 0, 20) as $s) {
        if (!is_array($s)) continue;
        $tipo = (string) ($s['tipo'] ?? '');
        if (!isset(SECCIONES[$tipo])) continue;
        if (SECCIONES[$tipo][2] && isset($vistos[$tipo])) continue;
        if ($tipo === 'libre' && ++$libres > MAX_LIBRES) continue;
        $vistos[$tipo] = true;
        $id = preg_match('/^[a-z0-9]{1,12}$/', (string) ($s['id'] ?? '')) ? $s['id'] : 's' . bin2hex(random_bytes(3));
        $titulo = clean_str($s['titulo'] ?? '', 40) ?: SECCIONES[$tipo][0];
        $ruta = SECCIONES[$tipo][1];
        if ($ruta === null) {
            $base = slugify($titulo, 30) ?: 'seccion';
            $ruta = $base;
            $i = 2;
            while (in_array($ruta, RUTAS_RESERVADAS, true) || in_array($ruta, array_column(SECCIONES, 1), true) || isset($rutas[$ruta])) {
                $ruta = $base . '-' . $i++;
            }
        }
        $rutas[$ruta] = true;
        $sec[] = ['id' => $id, 'tipo' => $tipo, 'on' => norm_bool($s['on'] ?? true), 'titulo' => $titulo, 'ruta' => $ruta,
            'datos' => norm_datos($tipo, $s['datos'] ?? [])];
    }
    // Secciones que la boda aún no tiene (creadas antes de existir): se añaden apagadas para
    // que aparezcan en «Más secciones»
    foreach (SECCIONES as $tipo => [$tit, $ruta, $unica]) {
        if (!$unica || isset($vistos[$tipo])) continue;
        $sec[] = ['id' => 's' . substr(md5($tipo), 0, 6), 'tipo' => $tipo, 'on' => false, 'titulo' => $tit, 'ruta' => $ruta, 'datos' => norm_datos($tipo, datos_iniciales($tipo))];
    }
    // Migración (25-sep-2026): «¿Necesitáis autobús?» pasa de la confirmación a Transporte.
    // Si Transporte no dice nada, hereda lo que tenía la confirmación; si no hay nada, se pregunta.
    $busAntiguo = null;
    foreach ($sec as &$x) {
        if ($x['tipo'] === 'rsvp' && array_key_exists('_bus_antiguo', $x['datos'])) { $busAntiguo = $x['datos']['_bus_antiguo']; unset($x['datos']['_bus_antiguo']); }
    }
    foreach ($sec as &$x) {
        if ($x['tipo'] === 'transporte' && $x['datos']['preguntar'] === null) $x['datos']['preguntar'] = $busAntiguo ?? true;
    }
    unset($x);
    // Confirmación de asistencia + menú: base del producto (owner, 25-sep-2026). Siempre
    // existe, siempre activa y va la primera; no se puede quitar desde el creador.
    $iRsvp = null;
    foreach ($sec as $i => $x) if ($x['tipo'] === 'rsvp') { $iRsvp = $i; break; }
    if ($iRsvp === null) {
        $rs = ['id' => 'rsvp', 'tipo' => 'rsvp', 'on' => true, 'titulo' => SECCIONES['rsvp'][0], 'ruta' => SECCIONES['rsvp'][1], 'datos' => norm_datos('rsvp', datos_iniciales('rsvp'))];
    } else {
        $rs = $sec[$iRsvp];
        $rs['on'] = true;
        array_splice($sec, $iRsvp, 1);
    }
    array_unshift($sec, $rs);
    $c['secciones'] = $sec;
    return $c;
}

/** Lo que falta para poder pagar o guardar. [clave de campo => mensaje]. */
function faltan(array $c): array {
    $f = [];
    if ($c['pareja']['nombre1'] === '') $f['pareja.nombre1'] = 'Falta el primer nombre.';
    if ($c['pareja']['nombre2'] === '') $f['pareja.nombre2'] = 'Falta el segundo nombre.';
    if ($c['pareja']['email'] === '') $f['pareja.email'] = 'Falta un email de contacto válido (sale en la página de privacidad de vuestra web).';
    if ($c['fecha'] === '') {
        $f['fecha'] = 'Falta la fecha de la boda.';
    } else {
        $hoy = date('Y-m-d');
        if ($c['fecha'] < $hoy) $f['fecha'] = 'La fecha de la boda ya ha pasado.';
        elseif ($c['fecha'] > date('Y-m-d', strtotime('+3 years'))) $f['fecha'] = 'La fecha está a más de 3 años vista.';
    }
    if ($c['ceremonia']['lugar'] === '') $f['ceremonia.lugar'] = 'Falta el lugar de la ceremonia.';
    if ($c['ceremonia']['hora'] === '') $f['ceremonia.hora'] = 'Falta la hora de la ceremonia.';
    foreach ($c['secciones'] as $s) {
        if (!$s['on']) continue;
        if ($s['tipo'] === 'hoteles') {
            foreach ($s['datos']['hoteles'] as $i => $ho) {
                if ($ho['nombre'] === '') $f['sec.' . $s['id'] . '.hotel' . $i] = 'Hay un hotel sin nombre en «' . $s['titulo'] . '».';
            }
        }
        if ($s['tipo'] === 'rsvp') {
            foreach ($s['datos']['menus'] as $i => $m) {
                if ($m['nombre'] === '') $f['sec.' . $s['id'] . '.menu' . $i] = 'Hay un menú sin nombre en «' . $s['titulo'] . '».';
            }
        }
        if ($s['tipo'] === 'transporte') {
            foreach ($s['datos']['trayectos'] as $i => $t) {
                if ($t['salida'] === '') $f['sec.' . $s['id'] . '.trayecto' . $i] = 'Hay un trayecto sin punto de salida en «' . $s['titulo'] . '».';
            }
        }
        if (in_array($s['tipo'], ['galeria', 'libro'], true) && mb_strlen($c['codigo']) < 4) {
            $f['codigo'] = 'La galería y el libro de invitados necesitan un código de acceso para los invitados (mínimo 4 caracteres).';
        }
        if ($s['tipo'] === 'libre' && $s['datos']['texto'] === '') {
            $f['sec.' . $s['id']] = 'La sección «' . $s['titulo'] . '» está vacía: escribid su texto o quitadla.';
        }
    }
    return $f;
}

function nombres(array $c, string $sep = ' & '): string {
    $a = $c['pareja']['nombre1'];
    $b = $c['pareja']['nombre2'];
    return ($a !== '' && $b !== '') ? $a . $sep . $b : ($a . $b);
}
function iniciales(array $c): string {
    $a = mb_substr($c['pareja']['nombre1'], 0, 1, 'UTF-8');
    $b = mb_substr($c['pareja']['nombre2'], 0, 1, 'UTF-8');
    return mb_strtoupper(trim($a . ' & ' . $b, ' &'), 'UTF-8');
}
function seccion_por_ruta(array $c, string $ruta): ?array {
    foreach ($c['secciones'] as $s) if ($s['on'] && $s['ruta'] === $ruta) return $s;
    return null;
}
function seccion_tipo(array $c, string $tipo): ?array {
    foreach ($c['secciones'] as $s) if ($s['on'] && $s['tipo'] === $tipo) return $s;
    return null;
}

/** Menús que ofrece la boda (vacío si no recoge confirmaciones). */
function menus_de(array $c): array {
    $s = seccion_tipo($c, 'rsvp');
    return $s ? $s['datos']['menus'] : [];
}
/** Nombre de un menú por su id; si la pareja lo borró, la copia que guardó la respuesta. */
function nombre_menu(array $c, string $id, string $copia = ''): string {
    foreach (menus_de($c) as $m) if ($m['id'] === $id) return $m['nombre'];
    if ($copia !== '') return $copia;
    return MENUS_ANTIGUOS[$id] ?? ($id !== '' ? $id : 'sin indicar');
}
/** ¿Se pregunta a los invitados si necesitan autobús? Solo con la sección Transporte activa. */
function pregunta_bus(array $c): bool {
    $t = seccion_tipo($c, 'transporte');
    return $t !== null && !empty($t['datos']['preguntar']);
}
