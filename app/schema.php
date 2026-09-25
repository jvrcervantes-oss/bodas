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
    'malva'     => ['Malva',     '#5A3651', '#7D5271', '#68435E', '#6E5A68', '#F6F1F4', '#E8D8E2', '#DDC8D5', '#8A6440', '#EED3E5', '#D8B6CC'],
];

const MENUS = ['carne' => 'Carne', 'pescado' => 'Pescado', 'vegetariano' => 'Vegetariano', 'vegano' => 'Vegano', 'infantil' => 'Infantil'];

// tipo => [título por defecto, ruta fija (null = libre, sale del título), única]
const SECCIONES = [
    'rsvp'        => ['Confirmar asistencia', 'confirmar-asistencia', true],
    'informacion' => ['Información', 'informacion', true],
    'hoteles'     => ['Hoteles', 'hoteles', true],
    'transporte'  => ['Transporte', 'transporte', true],
    'regalos'     => ['Lista de bodas', 'lista-de-bodas', true],
    'musica'      => ['Música', 'musica', true],
    'dresscode'   => ['Dress code', 'dress-code', true],
    'libre'       => ['Nueva sección', null, false],
];
const MAX_LIBRES = 3;
// Rutas que una sección libre nunca puede ocupar
const RUTAS_RESERVADAS = ['api', 'panel', 'privacidad', 'foto', 'boda', 'assets', 'inicio', 'index', 'crear', 'listo', 'legal'];

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
    foreach (['rsvp', 'informacion', 'hoteles', 'transporte', 'regalos', 'musica', 'dresscode'] as $t) {
        $sec[] = ['id' => 's' . (++$n), 'tipo' => $t, 'on' => true, 'titulo' => SECCIONES[$t][0], 'datos' => datos_iniciales($t)];
    }
    return [
        'v' => 1,
        'pareja' => ['nombre1' => '', 'nombre2' => '', 'email' => ''],
        'fecha' => '',
        'ciudad' => '',
        'tema' => 'rosa',
        'ceremonia' => ['lugar' => '', 'direccion' => '', 'hora' => ''],
        'convite' => ['lugar' => '', 'direccion' => '', 'hora' => ''],
        'portada' => [
            'invitacion' => 'Tenemos el placer de invitaros a nuestra boda',
            'titulo' => 'Os damos la bienvenida',
            'frase' => '',
            'texto' => 'Queremos compartir este día con las personas que más queremos. Gracias por acompañarnos.',
            'pie' => 'Gracias por formar parte de nuestra historia.',
        ],
        'foto' => false,
        'secciones' => $sec,
    ];
}

function datos_iniciales(string $tipo): array {
    switch ($tipo) {
        case 'rsvp': return ['texto' => 'Si venís en familia o en grupo, basta con que uno lo rellene por todos.', 'menus' => ['carne', 'pescado', 'vegetariano', 'infantil'], 'bus' => false, 'asistencia' => true, 'fecha_limite' => ''];
        case 'informacion': return ['texto' => ''];
        case 'hoteles': return ['texto' => 'Opciones de alojamiento cerca de la celebración.', 'hoteles' => []];
        case 'transporte': return ['texto' => ''];
        case 'regalos': return ['texto' => 'Vuestra compañía es el mejor regalo. Si además queréis tener un detalle con nosotros, podéis hacerlo aquí.', 'titular' => '', 'iban' => '', 'otro' => ''];
        case 'musica': return ['texto' => 'Proponed la canción que os hace saltar a la pista y votad las de los demás.'];
        case 'dresscode': return ['texto' => ''];
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

function norm_datos(string $tipo, $d): array {
    $d = is_array($d) ? $d : [];
    $texto = clean_str($d['texto'] ?? '', 2000);
    switch ($tipo) {
        case 'rsvp':
            $menus = array_values(array_intersect(array_keys(MENUS), is_array($d['menus'] ?? null) ? $d['menus'] : []));
            return ['texto' => clean_str($d['texto'] ?? '', 600), 'menus' => $menus ?: ['carne'],
                'bus' => norm_bool($d['bus'] ?? false), 'asistencia' => norm_bool($d['asistencia'] ?? true),
                'fecha_limite' => norm_fecha($d['fecha_limite'] ?? '')];
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
    foreach (['ceremonia', 'convite'] as $k) {
        $e = is_array($in[$k] ?? null) ? $in[$k] : [];
        $c[$k] = ['lugar' => clean_str($e['lugar'] ?? '', 120), 'direccion' => clean_str($e['direccion'] ?? '', 160), 'hora' => norm_hora($e['hora'] ?? '')];
    }
    $po = is_array($in['portada'] ?? null) ? $in['portada'] : [];
    $c['portada'] = ['invitacion' => clean_str($po['invitacion'] ?? '', 120), 'titulo' => clean_str($po['titulo'] ?? '', 80),
        'frase' => clean_str($po['frase'] ?? '', 240), 'texto' => clean_str($po['texto'] ?? '', 1200), 'pie' => clean_str($po['pie'] ?? '', 200)];
    $c['foto'] = norm_bool($in['foto'] ?? false);

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
