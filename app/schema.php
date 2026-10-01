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
// Tipografías (owner, 26-sep-2026: 10, elegidas sobre la muestra; fuera Romántica y Caligráfica, que
// Botánica y Jardín cubren mejor). Una clave que ya no existe cae a 'clasica' al normalizar.
// La clave no cambia aunque cambie el nombre visible (configs guardados y clases .fuente-<clave>):
// 'ciudad' se ve como «Manuscrita», 'jardin' como «Artística» y 'sobria' como «Caligráfica» (owner, 26-sep-2026).
// clave => [nombre, títulos, textos, nombres de la pareja, estilo de los nombres]
const FUENTES = [
    'clasica'   => ['Clásica',   "'Playfair Display', Georgia, serif",   "'Manrope', system-ui, sans-serif",          "'Playfair Display', Georgia, serif",   'italic'],
    'moderna'   => ['Moderna',   "'Plus Jakarta Sans', system-ui, sans-serif", "'Plus Jakarta Sans', system-ui, sans-serif", "'Plus Jakarta Sans', system-ui, sans-serif", 'normal'],
    'formal'    => ['Formal',    "'Bodoni Moda', Georgia, serif",        "'Crimson Pro', Georgia, serif",             "'Pinyon Script', cursive",             'normal'],
    'editorial' => ['Editorial', "'Arapey', Georgia, serif",             "'Hanken Grotesk', system-ui, sans-serif",   "'Arapey', Georgia, serif",             'italic'],
    'revista'   => ['Revista',   "'Instrument Serif', Georgia, serif",   "'Instrument Sans', system-ui, sans-serif",  "'Instrument Serif', Georgia, serif",   'italic'],
    'ciudad'    => ['Manuscrita',    "'Instrument Serif', Georgia, serif",   "'Public Sans', system-ui, sans-serif",      "'Ms Madi', cursive",                   'normal'],
    'grabado'   => ['Grabado',   "'Aboreto', Georgia, serif",            "'Rethink Sans', system-ui, sans-serif",     "'EB Garamond', Georgia, serif",        'italic'],
    'jardin'    => ['Artística',    "'Playfair Display', Georgia, serif",   "'Inclusive Sans', system-ui, sans-serif",   "'Alex Brush', cursive",                'normal'],
    'botanica'  => ['Botánica',  "'Cormorant Infant', Georgia, serif",   "'Montserrat', system-ui, sans-serif",       "'Alex Brush', cursive",                'normal'],    'sobria'    => ['Caligráfica',    "'Cinzel', Georgia, serif",             "'Host Grotesk', system-ui, sans-serif",     "'Petit Formal Script', cursive",       'normal'],
];

// Tipografías de autor: SOLO en el Pack Atelier. Vacío desde el 25-sep-2026: el owner mandó quitar
// las que no tenían licencia (Awesome Serif, Adelia, Artifact; las dos últimas, además, sin á é í ó ú ñ).
// Para añadir una: licencia de producto/SaaS por escrito, cobertura del español comprobada, el woff2
// en assets/fonts/premium/ fuera de git y del ZIP, y su entrada aquí:
// 'clave' => ['nombre', 'nota', 'titulos', 'textos', 'nombres', 'estilo', 'archivos' => ['premium/x.woff2' => [familia, estilo]]]
const FUENTES_AUTOR = [];

/** Las tipografías de autor cuyos ficheros están de verdad en este servidor. */
function fuentes_autor(): array {
    static $d = null;
    if ($d === null) {
        $d = array_filter(FUENTES_AUTOR, function ($f) {
            foreach (array_keys($f['archivos']) as $a) if (!is_file(WEB_DIR . '/assets/fonts/' . $a)) return false;
            return true;
        });
    }
    return $d;
}

// Colección Atelier (owner, 25-sep-2026; diseño de Stitch «Creatividades de autor»). Cada diseño
// trae su paleta, sus letras y su ilustración, y sustituye a paleta/tipografía/decoración.
// colores: mismo orden que TEMAS (primary, accent, accent-hover, secondary, sage, sage-2, sage-2-hover, gold, on-dark, on-dark-soft)
// fuentes: títulos, textos, nombres, estilo de los nombres
const ATELIER = [
    // Tokens de «Jardín de cítricos y azahar» (especificación del owner, 25-sep-2026)
    'citricos' => ['nombre' => 'Cítricos y azahar', 'categoria' => 'Mediterráneo', 'desc' => 'Papel de algodón rasgado, limones y azahar en acuarela.',
        'colores' => ['#5C6B57', '#5C6B57', '#4D5A49', '#5C6B57', '#F0ECE1', '#E6DCC4', '#DDD1B5', '#A6634B', '#F4ECCB', '#DCD3AE'],
        'fuentes' => ["'Playfair Display', Georgia, serif", "'Cormorant Garamond', Georgia, serif", "'Alex Brush', cursive", 'normal'],
        'nombres_color' => '#A6634B', 'tinta' => '#3D352E'],
    // Tokens de «Cerámica & Azulejo Talavera» (especificación del owner, 25-sep-2026)
    'ceramica' => ['nombre' => 'Cerámica y azulejo', 'categoria' => 'Talavera', 'desc' => 'Cenefa de azulejo cobalto, marco barroco para vuestra foto y patio andaluz.',
        'colores' => ['#183B75', '#183B75', '#2C5EA8', '#6B7A8D', '#F5F3ED', '#E6ECF3', '#D8E2EC', '#C6A664', '#FDFCFA', '#A9C2D8'],
        'fuentes' => ["'Cinzel', Georgia, serif", "'Cormorant Garamond', Georgia, serif", "'Pinyon Script', cursive", 'normal'],
        'nombres_color' => '#183B75', 'tinta' => '#3D352E'],
    // Tokens de «Herbario y lavanda silvestre» (especificación del owner, 25-sep-2026)
    'herbario' => ['nombre' => 'Herbario y lavanda', 'categoria' => 'Botánica', 'desc' => 'Pliego rasgado con flores prensadas sobre mesa de roble.',
        'colores' => ['#645166', '#645166', '#54445A', '#5A674E', '#EFE9DD', '#E4DCCB', '#DCD2BE', '#5A674E', '#EDE3EE', '#D2C4D4'],
        'fuentes' => ["'Bodoni Moda', Georgia, serif", "'Cormorant Garamond', Georgia, serif", "'Newsreader', Georgia, serif", 'italic'],
        'nombres_color' => '#3D352E', 'tinta' => '#3D352E'],
    // Tokens de «Sello de cera & caligrafía poética» (especificación del owner, 25-sep-2026)
    'lacre' => ['nombre' => 'Sello de lacre', 'categoria' => 'Editorial', 'desc' => 'Papel de algodón rasgado sobre lino y un sello de cera bronce con vuestras iniciales.',
        'colores' => ['#2D2926', '#9C694E', '#85573F', '#6B635B', '#F3EFE8', '#E8E2D8', '#DDD5C8', '#C5A059', '#FAF8F5', '#E5DED4'],
        'fuentes' => ["'Playfair Display', Georgia, serif", "'Cormorant Garamond', Georgia, serif", "'Pinyon Script', cursive", 'normal'],
        'nombres_color' => '#2D2926', 'tinta' => '#2D2926'],
    'masia' => ['nombre' => 'Boceto de masía', 'categoria' => 'Tinta y arquitectura', 'desc' => 'Grabado a plumilla de una masía, como un cuaderno de viaje.',
        'colores' => ['#38312B', '#A6634B', '#8E533E', '#4A5844', '#F8F4EC', '#EADFCD', '#E1D4BE', '#A6634B', '#F3E2D6', '#DDC3B3'],
        'fuentes' => ["'Cinzel', Georgia, serif", "'EB Garamond', Georgia, serif", "'EB Garamond', Georgia, serif", 'italic']],
    // Tokens de «Acuarela atardecer & calas» (especificación del owner, 25-sep-2026)
    'atardecer' => ['nombre' => 'Atardecer en cala', 'categoria' => 'Acuarela costera', 'desc' => 'Acuarela melocotón con polvo de oro, foto en aro dorado y mapa de la cala.',
        'colores' => ['#BD6A4B', '#BD6A4B', '#AA583A', '#7A6E65', '#F9F5EE', '#F3D2C1', '#EDC3AE', '#C5A059', '#FDFBF7', '#F3D2C1'],
        'fuentes' => ["'Playfair Display', Georgia, serif", "'Montserrat', system-ui, sans-serif", "'Pinyon Script', cursive", 'normal'],
        'nombres_color' => '#BD6A4B', 'tinta' => '#2D2824'],
];

// Decoración (estructura visual), independiente de la paleta. Owner, 25-sep-2026.
// «Flores de acuarela»: guirnalda de rosas pintada con código (assets/img/deco/flores-*.svg, 29-sep-2026); antes eran las acuarelas de la maqueta de Stitch.
const DECORACIONES = [
    'flores'    => ['Flores de acuarela', 'Guirnalda de rosas en acuarela'],
    'eucalipto' => ['Eucalipto', 'Una rama sobre los nombres'],
    'sobre'     => ['Sobre', 'La invitación en un sobre con lacre'],
    'ninguna'   => ['Sin adornos', 'Solo tipografía y color'],
];

// Estilo de la foto de portada (owner, 30-sep-2026, artifact «Foto con flores de acuarela»,
// https://claude.ai/artifact/6MoGPJnzKpBP19HQvRvaQC): vale con cualquier decoración; con «Flores de acuarela»
// las rosas rodean la foto. Los diseños Atelier traen su propio marco y no lo usan.
const FOTO_ESTILOS = [
    'arco'     => ['Arco', 'Recortada como un ventanal'],
    // Sello sustituye a Papel (owner, 1-oct-2026, artifact «Sexto estilo de foto»): las bodas con 'papel' pasan a 'sello' al normalizar.
    'sello'    => ['Sello', 'Troquelada como un sello, con matasellos'],
    'medallon' => ['Medallón', 'En óvalo, como un retrato'],
    'fundida'  => ['Fundida', 'A todo lo ancho, deshecha en el papel'],
    // Álbum sustituye a Instantánea (owner, 1-oct-2026, artifact «Sexto estilo de foto»): las bodas con 'instantanea' pasan a 'album'.
    'album'    => ['Álbum', 'Sujeta con esquineras, como en un álbum de fotos'],
    // Sexto estilo (owner, 1-oct-2026, artifact «Sexto estilo de foto»): máscara de acuarela y aguada del color de la paleta.
    'acuarela' => ['Acuarela', 'Recortada como una mancha de acuarela'],
];

/** Ramos de esquina junto a la foto de portada en ordenador (owner, 30-sep-2026, artifact «Ramos junto a la foto»; recolocados y
 *  en parte rediseñados por el owner el 1-oct-2026 en el «Editor de ramos», https://claude.ai/artifact/SqnGcjz5vtcEWEVWancrvu).
 *  Cada ramo son dos imagenes: «atras» (tallos, detras del marco) y «delante» (flores y hojas). x,y = donde nace (% del marco);
 *  dx,dy,w = posicion y ancho de la imagen respecto a ese punto, en cqw (1 % del ancho del marco); g = giro en grados alrededor
 *  de donde nace. 'nombres' => true: el ramo no va en la foto sino sobre los nombres (Fundida), y x,y,dx,dy,w se miden sobre el
 *  bloque de los nombres. Generados con tests/ramos_gen.js (los «-s<semilla>», con los ajustes del editor).
 *  Sello hereda los ramos que el owner colocó en Papel y Álbum los de Arco (1-oct-2026); se afinan en el Editor de ramos.
 *  Flores + Acuarela: colocación del owner en el Editor de ramos (1-oct-2026), con un ramo rediseñado (-s445). */
const RAMOS_FOTO = [
    'flores' => [
        'arco' => [['f' => 'ramo-flores-arco-0', 'x' => 6, 'y' => 20, 'dx' => -33.58, 'dy' => -42.92, 'w' => 42.49, 'g' => 6], ['f' => 'ramo-flores-arco-1', 'x' => 99.7, 'y' => 100, 'dx' => -5.09, 'dy' => -5.43, 'w' => 31.03, 'g' => 1], ['f' => 'ramo-flores-arco-s7862', 'x' => 8, 'y' => 12, 'dx' => -10, 'dy' => -30.26, 'w' => 31.05]],
        'sello' => [['f' => 'ramo-flores-sello-0', 'x' => 2, 'y' => 2, 'dx' => -31.65, 'dy' => -33.63, 'w' => 37.62, 'g' => -134], ['f' => 'ramo-flores-sello-1', 'x' => 98.2, 'y' => 97.9, 'dx' => -4.27, 'dy' => -6.55, 'w' => 34.15, 'g' => -120], ['f' => 'ramo-flores-sello-s4437', 'x' => 9.3, 'y' => 4.2, 'dx' => -7.57, 'dy' => -25.41, 'w' => 36.21]],
        'medallon' => [['f' => 'ramo-flores-medallon-0', 'x' => 96.5, 'y' => 30.9, 'dx' => -5, 'dy' => -29.46, 'w' => 34.46, 'g' => 124], ['f' => 'ramo-flores-medallon-1', 'x' => 25, 'y' => 94.9, 'dx' => -29.21, 'dy' => -4.98, 'w' => 36.43, 'g' => 101]],
        'fundida' => [['f' => 'ramo-flores-nombres-s2787', 'nombres' => true, 'x' => 49.34, 'y' => 0, 'dx' => -8.39, 'dy' => -28.36, 'w' => 17.0]],
        'acuarela' => [['f' => 'ramo-flores-arco-0', 'x' => 12.8, 'y' => 31.2, 'dx' => -27.25, 'dy' => -34.84, 'w' => 34.5, 'g' => 6], ['f' => 'ramo-flores-acuarela-s445', 'x' => 97.6, 'y' => 73.1, 'dx' => -37.57, 'dy' => -9.19, 'w' => 50.81], ['f' => 'ramo-flores-arco-s7862', 'x' => 9.7, 'y' => 17.3, 'dx' => -10, 'dy' => -30.26, 'w' => 31.05]],
        'album' => [['f' => 'ramo-flores-arco-0', 'x' => 6, 'y' => 20, 'dx' => -33.58, 'dy' => -42.92, 'w' => 42.49, 'g' => 6], ['f' => 'ramo-flores-arco-1', 'x' => 99.7, 'y' => 100, 'dx' => -5.09, 'dy' => -5.43, 'w' => 31.03, 'g' => 1], ['f' => 'ramo-flores-arco-s7862', 'x' => 8, 'y' => 12, 'dx' => -10, 'dy' => -30.26, 'w' => 31.05]],
    ],
    'eucalipto' => [
        'arco' => [['f' => 'ramo-eucalipto-arco-0', 'x' => 6, 'y' => 20, 'dx' => -31.3, 'dy' => -34.75, 'w' => 37.25, 'g' => 1], ['f' => 'ramo-eucalipto-arco-s8996', 'x' => 100.8, 'y' => 100.4, 'dx' => -8.68, 'dy' => -8.16, 'w' => 38.16]],
        'sello' => [['f' => 'ramo-eucalipto-sello-0', 'x' => 2, 'y' => 2, 'dx' => -28.68, 'dy' => -27.56, 'w' => 32.7, 'g' => -117], ['f' => 'ramo-eucalipto-sello-1', 'x' => 98, 'y' => 97, 'dx' => -5.64, 'dy' => -6.01, 'w' => 37.12, 'g' => -119]],
        'medallon' => [['f' => 'ramo-eucalipto-medallon-s7994', 'x' => 90.2, 'y' => 17.4, 'dx' => -15.56, 'dy' => -6.67, 'w' => 41.11], ['f' => 'ramo-eucalipto-medallon-1', 'x' => 14, 'y' => 85, 'dx' => -23.75, 'dy' => -4.87, 'w' => 29.25, 'g' => -89]],
        'fundida' => [['f' => 'ramo-eucalipto-nombres-s6307', 'nombres' => true, 'x' => 49.34, 'y' => 0, 'dx' => -10.85, 'dy' => -35.17, 'w' => 22.52]],
        'acuarela' => [['f' => 'ramo-eucalipto-arco-0', 'x' => 6, 'y' => 20, 'dx' => -31.3, 'dy' => -34.75, 'w' => 37.25, 'g' => 1], ['f' => 'ramo-eucalipto-arco-s8996', 'x' => 100.8, 'y' => 100.4, 'dx' => -8.68, 'dy' => -8.16, 'w' => 38.16]],
        'album' => [['f' => 'ramo-eucalipto-arco-0', 'x' => 6, 'y' => 20, 'dx' => -31.3, 'dy' => -34.75, 'w' => 37.25, 'g' => 1], ['f' => 'ramo-eucalipto-arco-s8996', 'x' => 100.8, 'y' => 100.4, 'dx' => -8.68, 'dy' => -8.16, 'w' => 38.16]],
    ],
];

const MENUS_ANTIGUOS = ['carne' => 'Carne', 'pescado' => 'Pescado', 'vegetariano' => 'Vegetariano', 'vegano' => 'Vegano', 'infantil' => 'Infantil'];
const MAX_MENUS = 8;
const MAX_BANQUETE = 1500;      // menú del banquete: caracteres (unas 20 líneas)
const MAX_TRAYECTOS = 6;
const MAX_PROGRAMA = 6;         // momentos EXTRA del programa del día (además de ceremonia y convite)
const MAX_HASHTAG = 30;
const MAX_VESTIMENTA = 60;
const MAX_HISTORIA = 2000;
const MAX_GALERIA = 24;

// tipo => [título por defecto, ruta fija (null = libre, sale del título), única]
const SECCIONES = [
    'rsvp'        => ['Confirmar asistencia', 'confirmar-asistencia', true],
    'informacion' => ['Información', 'informacion', true],
    // Paquete de mejoras (30-sep-2026): apagada por defecto y sin página mientras no tenga texto (normaliza_config)
    'historia'    => ['Nuestra historia', 'nuestra-historia', true],
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
// 'nuestra-historia' (la ruta de la sección Historia) se reserva en normaliza_config SOLO mientras esa sección está activa
const RUTAS_RESERVADAS = ['api', 'panel', 'privacidad', 'foto', 'boda', 'assets', 'inicio', 'index', 'crear', 'listo', 'legal', 'acceso', 'g', 'l'];

// Nombres de web que no se venden: técnicos del estudio o que se prestan a suplantación
const SLUGS_RESERVADOS = ['www', 'api', 'admin', 'panel', 'mail', 'correo', 'smtp', 'ftp', 'bodas', 'crear', 'static',
    'assets', 'cdn', 'app', 'demo', 'test', 'dev', 'staging', 'soporte', 'support', 'ayuda', 'help', 'login', 'pago',
    'pagos', 'stripe', 'factura', 'facturas', 'axisworks', 'lawang', 'eduycora', 'b2k', 'sumba', 'blog', 'shop', 'tienda',
    // Seguridad #105 (26-sep): la marca, el panel y los nombres de correo/infraestructura no se venden
    'estudio', 'bodaenlace', 'hola', 'guia', 'guias', 'cuenta', 'seguridad', 'legal', 'privacidad', 'condiciones',
    'webmail', 'autodiscover', 'autoconfig', 'imap', 'pop', 'mta-sts', 'ns1', 'ns2', 'cpanel', 'hpanel', 'lemon'];
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
    foreach (['rsvp', 'informacion', 'historia', 'hoteles', 'transporte', 'regalos', 'musica', 'dresscode', 'galeria', 'libro'] as $t) {
        // Galería y libro, apagadas de inicio: piden código de acceso y la galería se llena desde el panel. Historia, apagada hasta que se escriba
        $sec[] = ['id' => 's' . (++$n), 'tipo' => $t, 'on' => !in_array($t, ['galeria', 'libro', 'historia'], true), 'titulo' => SECCIONES[$t][0], 'datos' => datos_iniciales($t)];
    }
    return [
        'v' => 1,
        'pareja' => ['nombre1' => '', 'nombre2' => '', 'union' => '&', 'email' => ''],
        'fecha' => '',
        'ciudad' => '',
        'tema' => 'rosa',
        'decoracion' => 'flores',
        'foto_estilo' => 'arco',
        'fuente' => 'clasica',
        'atelier' => '',
        'fuente_autor' => '',
        'ceremonia' => ['lugar' => '', 'direccion' => '', 'hora' => '', 'coords' => ''],
        'convite' => ['lugar' => '', 'direccion' => '', 'hora' => '', 'coords' => '', 'mismo' => false],
        'programa' => [],
        'portada' => [
            'invitacion' => 'Tenemos el placer de invitaros a nuestra boda',
            'titulo' => 'Os damos la bienvenida',
            'frase' => '',
            'texto' => 'Queremos compartir este día con las personas que más queremos. Gracias por acompañarnos.',
            'pie' => 'Gracias por formar parte de nuestra historia.',
            'hashtag' => '',
            'vestimenta' => '',
        ],
        'foto' => false,
        'codigo' => '',
        'secciones' => $sec,
    ];
}

function datos_iniciales(string $tipo): array {
    switch ($tipo) {
        case 'rsvp': return ['texto' => 'Si venís en familia o en grupo, basta con que uno lo rellene por todos.', 'asistencia' => true, 'fecha_limite' => '', 'banquete' => '',
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
/** Texto de UNA línea: los saltos y los espacios repetidos pasan a un espacio. Tope en caracteres. */
function linea($v, int $max): string {
    $t = (string) preg_replace('/\s+/u', ' ', clean_str($v, 100000));
    return mb_substr(trim($t), 0, $max, 'UTF-8');
}
/** Hashtag SIN «#»: solo letras, números y guion bajo; el tope cuenta sobre el valor ya limpio. */
function norm_hashtag($v): string {
    $t = (string) preg_replace('/[^\p{L}\p{N}_]/u', '', clean_str($v, 500));
    return mb_substr($t, 0, MAX_HASHTAG, 'UTF-8');
}
/** Momentos extra del programa: hora válida y título obligatorios (si no, se descartan; datos_descartados() lo avisa), ordenados por hora. */
function norm_programa($lista): array {
    $out = [];
    foreach (array_slice(is_array($lista) ? array_values($lista) : [], 0, MAX_PROGRAMA) as $i => $m) {
        if (!is_array($m)) continue;
        $hora = norm_hora($m['hora'] ?? '');
        $titulo = linea($m['titulo'] ?? '', 60);
        if ($hora === '' || $titulo === '') continue;
        $out[] = ['hora' => $hora, 'titulo' => $titulo, 'lugar' => linea($m['lugar'] ?? '', 80), 'nota' => linea($m['nota'] ?? '', 160), '_i' => $i];
    }
    usort($out, fn($a, $b) => [$a['hora'], $a['_i']] <=> [$b['hora'], $b['_i']]);   // estable: misma hora, el orden en que se escribieron
    return array_map(function ($m) { unset($m['_i']); return $m; }, $out);
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
                'asistencia' => norm_bool($d['asistencia'] ?? true), 'fecha_limite' => norm_fecha($d['fecha_limite'] ?? ''),
                // Lo que se va a servir (owner, 27-sep): texto libre, una línea por momento («Principal: …»). Opcional
                'banquete' => clean_str($d['banquete'] ?? '', MAX_BANQUETE)];
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
        case 'historia':
            return ['texto' => clean_str($d['texto'] ?? '', MAX_HISTORIA)];
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
    // Qué va entre los nombres: «&» (el de siempre) o «y». Lista cerrada; lo demás vuelve a «&».
    $c['pareja'] = ['nombre1' => clean_str($p['nombre1'] ?? '', 40), 'nombre2' => clean_str($p['nombre2'] ?? '', 40),
        'union' => ($p['union'] ?? '') === 'y' ? 'y' : '&',
        'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : ''];
    $c['fecha'] = norm_fecha($in['fecha'] ?? '');
    $c['ciudad'] = clean_str($in['ciudad'] ?? '', 60);
    $c['tema'] = isset(TEMAS[$in['tema'] ?? '']) ? $in['tema'] : 'eucalipto';
    // Las bodas de antes de existir la decoración conservan su rama de eucalipto
    $c['decoracion'] = isset(DECORACIONES[$in['decoracion'] ?? '']) ? $in['decoracion'] : 'eucalipto';
    $fe = ['papel' => 'sello', 'instantanea' => 'album'][$in['foto_estilo'] ?? ''] ?? ($in['foto_estilo'] ?? '');   // retirados el 1-oct-2026: Papel pasa a Sello e Instantánea a Álbum
    $c['foto_estilo'] = isset(FOTO_ESTILOS[$fe]) ? $fe : 'arco';
    $c['fuente'] = isset(FUENTES[$in['fuente'] ?? '']) ? $in['fuente'] : 'clasica';
    $c['atelier'] = isset(ATELIER[$in['atelier'] ?? '']) ? $in['atelier'] : '';
    // Solo con un diseño Atelier: sin él se descarta (el panel ya rechaza Atelier no comprado)
    $c['fuente_autor'] = ($c['atelier'] !== '' && isset(fuentes_autor()[$in['fuente_autor'] ?? ''])) ? $in['fuente_autor'] : '';
    foreach (['ceremonia', 'convite'] as $k) {
        $e = is_array($in[$k] ?? null) ? $in[$k] : [];
        // coords: enlace de Google Maps o «lat, lon» por si la dirección no cae en su sitio (se lee, nunca se pide)
        $c[$k] = ['lugar' => clean_str($e['lugar'] ?? '', 120), 'direccion' => clean_str($e['direccion'] ?? '', 160), 'hora' => norm_hora($e['hora'] ?? ''),
            'coords' => clean_str($e['coords'] ?? '', 400)];
    }
    // Convite en el mismo sitio que la ceremonia: el lugar y la dirección SIEMPRE se toman de
    // la ceremonia (se recalculan en cada normalización, no pueden desviarse)
    $c['convite']['mismo'] = norm_bool($in['convite']['mismo'] ?? false);
    if ($c['convite']['mismo']) {
        $c['convite']['lugar'] = $c['ceremonia']['lugar'];
        $c['convite']['direccion'] = $c['ceremonia']['direccion'];
        $c['convite']['coords'] = $c['ceremonia']['coords'];
    }
    $po = is_array($in['portada'] ?? null) ? $in['portada'] : [];
    $c['portada'] = ['invitacion' => clean_str($po['invitacion'] ?? '', 120), 'titulo' => clean_str($po['titulo'] ?? '', 80),
        'frase' => clean_str($po['frase'] ?? '', 240), 'texto' => clean_str($po['texto'] ?? '', 1200), 'pie' => clean_str($po['pie'] ?? '', 200),
        'hashtag' => norm_hashtag($po['hashtag'] ?? ''), 'vestimenta' => linea($po['vestimenta'] ?? '', MAX_VESTIMENTA)];
    $c['programa'] = norm_programa($in['programa'] ?? null);
    $c['foto'] = norm_bool($in['foto'] ?? false);
    // Código de acceso a la galería y al libro (owner, 25-sep): lo reparte la pareja en la invitación
    $c['codigo'] = (string) preg_replace('/[^A-Za-z0-9-]/', '', clean_str($in['codigo'] ?? '', 20));

    $sec = [];
    $vistos = [];
    $libres = 0;
    $rutas = [];
    // La ruta de «Nuestra historia» solo se reserva a las secciones libres mientras la historia está ACTIVA: si no, una
    // web ya publicada con una sección propia con ese título (/nuestra-historia) cambiaría de dirección sin querer.
    $historiaActiva = false;
    foreach (array_slice(is_array($in['secciones'] ?? null) ? array_values($in['secciones']) : [], 0, 20) as $s) {
        if (is_array($s) && ($s['tipo'] ?? '') === 'historia' && norm_bool($s['on'] ?? true)
            && clean_str(is_array($s['datos'] ?? null) ? ($s['datos']['texto'] ?? '') : '', MAX_HISTORIA) !== '') { $historiaActiva = true; break; }
    }
    $rutaHistoria = SECCIONES['historia'][1];
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
            while (in_array($ruta, RUTAS_RESERVADAS, true) || (in_array($ruta, array_column(SECCIONES, 1), true) && ($ruta !== $rutaHistoria || $historiaActiva)) || isset($rutas[$ruta])) {
                $ruta = $base . '-' . $i++;
            }
        }
        $datos = norm_datos($tipo, $s['datos'] ?? []);
        $on = norm_bool($s['on'] ?? true);
        if ($tipo === 'historia' && $datos['texto'] === '') $on = false;   // sin texto no hay página: ni menú, ni barra, ni ZIP
        if ($tipo !== 'historia' || $on) $rutas[$ruta] = true;   // una historia apagada no ocupa su dirección
        $sec[] = ['id' => $id, 'tipo' => $tipo, 'on' => $on, 'titulo' => $titulo, 'ruta' => $ruta, 'datos' => $datos];
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

/** Días que la fecha de una boda ya contratada puede desplazarse hacia delante desde el panel: el alojamiento
 *  se calcula sobre la fecha (fecha_borrado) y no se alarga gratis. */
const MAX_MOVER_FECHA_DIAS = 60;

/**
 * Datos que el navegador mandó y normaliza_config() vació en silencio: un IBAN con el dígito de control mal o
 * una web de hotel que no es una URL. Sin esto la pareja paga y publica SIN su caja de regalos o SIN el enlace
 * del hotel, sin enterarse. $crudo = el config tal como llegó (json_decode), antes de normalizar.
 * Solo se mira lo de secciones ACTIVAS. [clave => mensaje] con el nombre de la sección.
 */
function datos_descartados($crudo): array {
    $f = [];
    $secs = is_array($crudo) && is_array($crudo['secciones'] ?? null) ? array_slice(array_values($crudo['secciones']), 0, 20) : [];
    foreach ($secs as $s) {
        if (!is_array($s) || !norm_bool($s['on'] ?? true)) continue;
        $tipo = (string) ($s['tipo'] ?? '');
        if (!in_array($tipo, ['regalos', 'hoteles', 'historia'], true)) continue;
        $id = preg_match('/^[a-z0-9]{1,12}$/', (string) ($s['id'] ?? '')) ? (string) $s['id'] : $tipo;
        $titulo = clean_str($s['titulo'] ?? '', 40) ?: SECCIONES[$tipo][0];
        $d = is_array($s['datos'] ?? null) ? $s['datos'] : [];
        if ($tipo === 'regalos') {
            $iban = $d['iban'] ?? '';
            if (is_string($iban) && trim($iban) !== '' && norm_iban($iban) === '') {
                $f['sec.' . $id . '.iban'] = 'El IBAN de «' . $titulo . '» no es válido (revisad los dígitos): así no se publica la caja de regalos. Corregidlo o dejadlo vacío.';
            }
        } elseif ($tipo === 'historia') {
            if (se_pasa($d['texto'] ?? '', MAX_HISTORIA)) {
                $f['sec.' . $id . '.texto'] = 'El texto de «' . $titulo . '» pasa de ' . MAX_HISTORIA . ' caracteres: se cortaría al publicar. Acortadlo.';
            }
        } else {
            foreach (array_slice(is_array($d['hoteles'] ?? null) ? array_values($d['hoteles']) : [], 0, 8) as $i => $ho) {
                if (!is_array($ho)) continue;
                $web = $ho['web'] ?? '';
                if (is_string($web) && trim($web) !== '' && norm_url($web) === '') {
                    $n = clean_str($ho['nombre'] ?? '', 100);
                    $f['sec.' . $id . '.hotelweb' . $i] = 'La web del hotel ' . ($n !== '' ? '«' . $n . '»' : ($i + 1)) . ' en «' . $titulo . '» no es una dirección válida: así no saldría el enlace. Corregidla o dejadla vacía.';
                }
            }
        }
    }
    // Portada: hashtag y vestimenta
    $po = is_array($crudo) && is_array($crudo['portada'] ?? null) ? $crudo['portada'] : [];
    $h = $po['hashtag'] ?? '';
    if (is_string($h) && trim($h) !== '') {
        // Un «#» delante es lo normal y no se avisa; lo demás que se pierda (espacios, tildes sueltas, emojis, signos) o un exceso sí
        $pedido = ltrim(clean_str($h, 500), "# \t\n");
        $limpio = (string) preg_replace('/[^\p{L}\p{N}_]/u', '', $pedido);
        if ($limpio !== $pedido) {
            $f['portada.hashtag'] = 'Portada: el hashtag solo admite letras, números y guion bajo, sin espacios ni signos: quedaría «#' . mb_substr($limpio, 0, MAX_HASHTAG, 'UTF-8') . '». Corregidlo o dejadlo vacío.';
        } elseif (mb_strlen($limpio, 'UTF-8') > MAX_HASHTAG) {
            $f['portada.hashtag'] = 'Portada: el hashtag admite ' . MAX_HASHTAG . ' caracteres como máximo: se cortaría al publicar. Acortadlo.';
        }
    }
    if (se_pasa($po['vestimenta'] ?? '', MAX_VESTIMENTA, true)) {
        $f['portada.vestimenta'] = 'Portada: la vestimenta admite ' . MAX_VESTIMENTA . ' caracteres como máximo, en una línea: se cortaría al publicar. Acortadla.';
    }
    // Programa del día: el índice es el de la lista TAL COMO LA MANDÓ el navegador (antes de ordenar por hora)
    if (is_array($crudo) && is_array($crudo['programa'] ?? null)) {
        $filas = array_values($crudo['programa']);
        if (count($filas) > MAX_PROGRAMA) {
            $f['programa.max'] = 'Programa del día: caben ' . MAX_PROGRAMA . ' momentos como máximo; los demás no se publicarían. Quitad los que sobren.';
        }
        foreach (array_slice($filas, 0, MAX_PROGRAMA) as $i => $m) {
            if (!is_array($m)) continue;
            $n = $i + 1;
            $hora = $m['hora'] ?? '';
            if (norm_hora($hora) === '') {
                $f['programa' . $i . '.hora'] = 'Programa del día, momento ' . $n . ': ' . (trim(is_string($hora) ? $hora : '') === '' ? 'falta la hora' : 'la hora no es válida') . '. Sin hora no se publica.';
            }
            if (linea($m['titulo'] ?? '', 60) === '') {
                $f['programa' . $i . '.titulo'] = 'Programa del día, momento ' . $n . ': falta el título. Sin título no se publica.';
            }
            foreach (['titulo' => 60, 'lugar' => 80, 'nota' => 160] as $k => $max) {
                if (se_pasa($m[$k] ?? '', $max, true)) {
                    $f['programa' . $i . '.' . $k] = 'Programa del día, momento ' . $n . ': el ' . $k . ' admite ' . $max . ' caracteres como máximo: se cortaría al publicar. Acortadlo.';
                }
            }
        }
    }
    return $f;
}

/** ¿El texto crudo es más largo que su tope? (lo que normalizar recortaría en silencio). $unaLinea: cuenta tras juntar los saltos. */
function se_pasa($v, int $max, bool $unaLinea = false): bool {
    if (!is_string($v)) return false;
    $t = $unaLinea ? linea($v, 100000) : clean_str($v, 100000);
    return mb_strlen($t, 'UTF-8') > $max;
}

/**
 * Lo que falta para poder pagar o guardar. [clave de campo => mensaje].
 * $crudo: el config como llegó del navegador; con él, lo que normalizar vació en silencio (IBAN, webs) también bloquea.
 * $panel: solo al editar una boda YA contratada (panel): ['guardada' => fecha guardada, 'pago' => fecha al contratar].
 *   La regla «fecha pasada» solo aplica si la fecha CAMBIA (después de la boda se sigue pudiendo guardar todo lo demás),
 *   y no se puede mover hacia delante más de MAX_MOVER_FECHA_DIAS respecto de la de contratación. Al crear ($panel null)
 *   la fecha tiene que ser futura.
 */
function faltan(array $c, $crudo = null, ?array $panel = null): array {
    $f = [];
    if ($c['pareja']['nombre1'] === '') $f['pareja.nombre1'] = 'Falta el primer nombre.';
    if ($c['pareja']['nombre2'] === '') $f['pareja.nombre2'] = 'Falta el segundo nombre.';
    if ($c['pareja']['email'] === '') $f['pareja.email'] = 'Falta un email de contacto válido (sale en la página de privacidad de vuestra web).';
    if ($c['fecha'] === '') {
        $f['fecha'] = 'Falta la fecha de la boda.';
    } else {
        $hoy = date('Y-m-d');
        $guardada = $panel !== null ? (string) ($panel['guardada'] ?? '') : null;
        if ($guardada !== null && $c['fecha'] === $guardada) {
            // Sin tocar la fecha: se puede guardar lo demás aunque la boda ya haya pasado
        } elseif ($c['fecha'] < $hoy) {
            $f['fecha'] = 'La fecha de la boda ya ha pasado.';
        } elseif ($c['fecha'] > date('Y-m-d', strtotime('+3 years'))) {
            $f['fecha'] = 'La fecha está a más de 3 años vista.';
        } elseif ($panel !== null) {
            $ancla = (string) ($panel['pago'] ?? '') !== '' ? (string) $panel['pago'] : (string) ($panel['guardada'] ?? '');
            if ($ancla !== '' && (strtotime($c['fecha']) - strtotime($ancla)) > MAX_MOVER_FECHA_DIAS * 86400) {
                $f['fecha'] = 'La fecha no puede moverse más de ' . MAX_MOVER_FECHA_DIAS . ' días hacia delante respecto de la que teníais al contratar ('
                    . date('d/m/Y', strtotime($ancla)) . '), porque el alojamiento de la web se cuenta desde ella. Si necesitáis un cambio mayor, escribidnos a ' . empresa()['email'] . '.';
            }
        }
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
    if ($crudo !== null) $f += datos_descartados($crudo);
    return $f;
}

/** Los dos nombres con lo que la pareja eligió entre ellos (& o y). Un $sep explícito manda:
 *  las frases («Boda de Ana y Luis») piden siempre «y». */
function nombres(array $c, ?string $sep = null): string {
    $sep = $sep ?? ((($c['pareja']['union'] ?? '&') === 'y') ? ' y ' : ' & ');
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
