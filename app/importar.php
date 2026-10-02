<?php
// Subir la lista de invitados desde un archivo (Excel .xlsx, Word .docx, PDF con texto, o .csv/.txt) en vez de pegarla.
// Sin librerías ni extensiones (no hay composer; ZipArchive no está en todos los PHP): el lector de zip, de xlsx, de docx y
// el extractor de texto de PDF se escriben aquí. NADA se guarda al subir: el archivo se lee, se convierte en las mismas
// líneas «nombre; grupo» que se pegan, y se enseñan en el cuadro de texto para que la pareja las revise y pulse
// «Guardar lista» (un PDF ajeno puede leerse mal; la revisión es la red). El archivo no se conserva en disco.
//  - El navegador no manda nada de fiar: el tipo se decide por los primeros bytes, nunca por la extensión ni por el MIME.
//  - Un zip o un PDF subido es un archivo hostil hasta que se demuestra lo contrario: tope de tamaño, de entradas y de bytes
//    descomprimidos (bomba de descompresión), y XML sin DOCTYPE ni entidades.
declare(strict_types=1);

const IMPORTAR_MAX_BYTES = 2 * 1024 * 1024;
const IMPORTAR_MAX_DESCOMPRIMIDO = 12 * 1024 * 1024;
const IMPORTAR_MAX_FILAS = 3000;

class ImportarError extends RuntimeException {}

// Un archivo hostil de 2 MB no puede ocupar un worker más que unos segundos: plazo duro que miran los bucles del PDF
const IMPORTAR_SEGUNDOS = 8;
const IMPORTAR_MAX_CMAP = 20000;
const IMPORTAR_MAX_PILA = 3000;
const IMPORTAR_MAX_TROZOS = 60000;
const IMPORTAR_MAX_CELDAS = 50;
function imp_plazo(): void {
    if (microtime(true) > (float) ($GLOBALS['IMP_FIN'] ?? PHP_INT_MAX)) throw new ImportarError('Ese archivo es demasiado complejo de leer. Subid el Excel o pegad la lista.');
}

// ---------------------------------------------------------------- zip (xlsx y docx)
/** Entradas de un zip por nombre. Soporta «sin comprimir» y «deflate»; solo lee las que `$quiero` acepta. */
function imp_zip(string $z, callable $quiero): array {
    $fin = strrpos($z, "PK\x05\x06");
    if ($fin === false || strlen($z) < $fin + 22) throw new ImportarError('El archivo está dañado o no es un documento de Office válido.');
    $f = unpack('vdisco/vdc/vn/vnt/Vtam/Voff', substr($z, $fin + 4, 18));
    if ($f['nt'] > 2000) throw new ImportarError('El archivo tiene demasiadas partes.');
    $p = $f['off'];
    $o = [];
    $total = 0;
    for ($i = 0; $i < $f['nt']; $i++) {
        $e = @unpack('Vsig/vv1/vv2/vflag/vmet/vhora/vdia/Vcrc/Vcomp/Vtam/vln/vle/vlc/vdi/vai/Vea/Voff', substr($z, $p, 46));
        if (!$e || $e['sig'] !== 0x02014b50) throw new ImportarError('El archivo está dañado o no es un documento de Office válido.');
        $nombre = substr($z, $p + 46, $e['ln']);
        $p += 46 + $e['ln'] + $e['le'] + $e['lc'];
        if (!$quiero($nombre)) continue;
        if (($e['flag'] & 1) || $e['tam'] > IMPORTAR_MAX_DESCOMPRIMIDO || ($total += $e['tam']) > IMPORTAR_MAX_DESCOMPRIMIDO) throw new ImportarError('El archivo es demasiado grande o está protegido con contraseña.');
        $l = @unpack('Vsig/vv/vflag/vmet/vhora/vdia/Vcrc/Vcomp/Vtam/vln/vle', substr($z, $e['off'], 30));
        if (!$l || $l['sig'] !== 0x04034b50) throw new ImportarError('El archivo está dañado o no es un documento de Office válido.');
        $dato = substr($z, $e['off'] + 30 + $l['ln'] + $l['le'], $e['comp']);
        if ($e['met'] === 8) $dato = function_exists('gzinflate') ? @gzinflate($dato, $e['tam'] + 1) : false;
        elseif ($e['met'] !== 0) $dato = false;
        if (!is_string($dato) || strlen($dato) !== $e['tam']) throw new ImportarError('No se ha podido leer el archivo.');
        $o[$nombre] = $dato;
    }
    return $o;
}

/** XML sin DOCTYPE ni entidades (nada de XXE ni de «billion laughs») y sin red. */
function imp_dom(string $xml): DOMDocument {
    if (preg_match('/<!(DOCTYPE|ENTITY)/i', substr($xml, 0, 4096)) || stripos($xml, '<!ENTITY') !== false) throw new ImportarError('El archivo no es un documento válido.');   // 1.ª barrera por bytes; la 2.ª, tras cargar (UTF-16)
    $d = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    $ok = $d->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    if (!$ok) throw new ImportarError('No se ha podido leer el contenido del archivo.');
    if ($d->doctype !== null) throw new ImportarError('El archivo no es un documento válido.');
    return $d;
}

// ---------------------------------------------------------------- Excel
/** Letras de columna → índice desde 0 («B» → 1, «AA» → 26). */
function imp_col(string $ref): int {
    $n = 0;
    foreach (str_split(strtoupper((string) preg_replace('/[^A-Za-z]/', '', $ref))) as $ch) $n = $n * 26 + ord($ch) - 64;
    return max(0, $n - 1);
}

function imp_xlsx(string $z): array {
    $p = imp_zip($z, fn($n) => $n === 'xl/workbook.xml' || $n === 'xl/_rels/workbook.xml.rels' || $n === 'xl/sharedStrings.xml' || preg_match('~^xl/worksheets/[^/]+\.xml$~', $n) === 1);
    // Primera hoja según el libro; si algo no cuadra, la primera por nombre
    $hoja = null;
    if (isset($p['xl/workbook.xml'], $p['xl/_rels/workbook.xml.rels']) && preg_match('/<sheet\b[^>]*\br:id="([^"]+)"/', $p['xl/workbook.xml'], $m)
        && preg_match('/<Relationship\b(?=[^>]*\bId="' . preg_quote($m[1], '/') . '")[^>]*\bTarget="([^"]+)"/', $p['xl/_rels/workbook.xml.rels'], $t)) {
        $hoja = 'xl/' . ltrim((string) preg_replace('~^/?(xl/)?~', '', $t[1]), '/');
    }
    if ($hoja === null || !isset($p[$hoja])) {
        $hojas = array_values(array_filter(array_keys($p), fn($n) => strpos($n, 'xl/worksheets/') === 0));
        sort($hojas, SORT_NATURAL);
        $hoja = $hojas[0] ?? null;
    }
    if ($hoja === null) throw new ImportarError('No he encontrado ninguna hoja en ese Excel.');
    $comp = [];
    if (isset($p['xl/sharedStrings.xml'])) {
        $x = new DOMXPath($d = imp_dom($p['xl/sharedStrings.xml']));
        foreach ($x->query('//*[local-name()="si"]') as $si) {
            $t = '';
            foreach ($x->query('.//*[local-name()="t"][not(ancestor::*[local-name()="rPh"])]', $si) as $tn) $t .= $tn->textContent;
            $comp[] = $t;
        }
    }
    $x = new DOMXPath($d = imp_dom($p[$hoja]));
    $filas = [];
    foreach ($x->query('//*[local-name()="sheetData"]/*[local-name()="row"]') as $row) {
        $fila = [];
        foreach ($x->query('./*[local-name()="c"]', $row) as $i => $c) {
            /** @var DOMElement $c */
            $col = $c->hasAttribute('r') ? imp_col($c->getAttribute('r')) : (int) $i;
            $tipo = $c->getAttribute('t');
            $v = $x->query('./*[local-name()="v"]', $c)->item(0);
            if ($tipo === 'inlineStr') { $v = ''; foreach ($x->query('.//*[local-name()="t"]', $c) as $tn) $v .= $tn->textContent; }
            elseif ($v === null) $v = '';
            else { $v = $v->textContent; if ($tipo === 's') $v = $comp[(int) $v] ?? ''; }
            if ($col < 50) $fila[$col] = trim((string) $v);
        }
        if ($fila) { $n = max(array_keys($fila)); $fila = array_replace(array_fill(0, $n + 1, ''), $fila); $filas[] = $fila; }
        if (count($filas) >= IMPORTAR_MAX_FILAS) break;
    }
    return $filas;
}

// ---------------------------------------------------------------- Word
function imp_docx(string $z): array {
    $p = imp_zip($z, fn($n) => $n === 'word/document.xml');
    if (!isset($p['word/document.xml'])) throw new ImportarError('No he encontrado texto en ese documento de Word.');
    $x = new DOMXPath($d = imp_dom($p['word/document.xml']));
    $texto = function (DOMNode $n) use ($x): string {
        $t = '';
        foreach ($x->query('.//*[local-name()="t" or local-name()="tab" or local-name()="br"]', $n) as $e) $t .= $e->localName === 't' ? $e->textContent : ($e->localName === 'tab' ? "\t" : ' ');
        return trim($t);
    };
    $filas = [];
    // Tablas: una fila por fila de tabla, una columna por celda (los párrafos de una celda se unen)
    foreach ($x->query('//*[local-name()="tbl"]//*[local-name()="tr"]') as $tr) {
        $fila = [];
        foreach ($x->query('./*[local-name()="tc"]', $tr) as $tc) {
            $ps = [];
            foreach ($x->query('./*[local-name()="p"]', $tc) as $pa) if (($t = $texto($pa)) !== '') $ps[] = $t;
            $fila[] = implode(' ', $ps);
        }
        $filas[] = $fila;
        if (count($filas) >= IMPORTAR_MAX_FILAS) return $filas;
    }
    if ($filas) return $filas;
    // Sin tablas: un párrafo por línea («nombre; grupo» o con tabulador)
    foreach ($x->query('//*[local-name()="body"]/*[local-name()="p"]') as $pa) {
        $t = $texto($pa);
        if ($t !== '') $filas[] = array_map('trim', preg_split('/\t|;/', $t) ?: [$t]);
        if (count($filas) >= IMPORTAR_MAX_FILAS) break;
    }
    return $filas;
}

// ---------------------------------------------------------------- PDF (solo con texto; no un escaneo)
/** Mapa ToUnicode → [código (bytes) => utf8, longitud del código]. */
function imp_pdf_cmap(string $s): array {
    $m = [];
    $u = fn(string $hex) => (string) mb_convert_encoding(hex2bin(strlen($hex) % 2 ? '0' . $hex : $hex) ?: '', 'UTF-8', 'UTF-16BE');
    $largo = 1;
    if (preg_match_all('/beginbfchar(.*?)endbfchar/s', $s, $bl)) foreach ($bl[1] as $b) {
        if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>/', $b, $q, PREG_SET_ORDER)) foreach ($q as $r) { if (count($m) > IMPORTAR_MAX_CMAP) throw new ImportarError('Ese PDF es demasiado complejo de leer. Subid el Excel o pegad la lista.'); $m[hex2bin(strlen($r[1]) % 2 ? '0' . $r[1] : $r[1])] = $u($r[2]); $largo = max($largo, intdiv(strlen($r[1]) + 1, 2)); }
    }
    if (preg_match_all('/beginbfrange(.*?)endbfrange/s', $s, $bl)) foreach ($bl[1] as $b) {
        if (preg_match_all('/<([0-9A-Fa-f]+)>\s*<([0-9A-Fa-f]+)>\s*(<([0-9A-Fa-f]+)>|\[([^\]]*)\])/', $b, $q, PREG_SET_ORDER)) foreach ($q as $r) {
            $a = hexdec($r[1]); $z = min(hexdec($r[2]), $a + 65535); $l = intdiv(strlen($r[1]) + 1, 2); $largo = max($largo, $l);
            $lista = isset($r[5]) && $r[5] !== '' ? (preg_match_all('/<([0-9A-Fa-f]+)>/', $r[5], $ll) ? $ll[1] : []) : null;
            imp_plazo();
            for ($c = $a; $c <= $z; $c++) {
                if (count($m) > IMPORTAR_MAX_CMAP) throw new ImportarError('Ese PDF es demasiado complejo de leer. Subid el Excel o pegad la lista.');
                $k = substr(pack('N', $c), 4 - $l);
                if ($lista !== null) { if (isset($lista[$c - $a])) $m[$k] = $u($lista[$c - $a]); }
                else { $ini = hexdec($r[4]) + ($c - $a); $m[$k] = (string) mb_convert_encoding(pack('n', $ini), 'UTF-8', 'UTF-16BE'); }
            }
        }
    }
    return [$m, $largo];
}

/** Trozos de texto del PDF: [pagina/flujo, x, y, tamaño, texto]. */
function imp_pdf_trozos(string $pdf): array {
    if (!function_exists('gzuncompress')) throw new ImportarError('Este servidor no puede leer PDF. Subid el Excel o pegad la lista.');
    if (preg_match('~/Encrypt\b~', $pdf)) throw new ImportarError('Ese PDF está protegido: no puedo leerlo. Subid el Excel o pegad la lista.');
    $objs = [];
    $total = 0;
    if (preg_match_all('/(\d+)\s+\d+\s+obj\b(.*?)\bendobj/s', $pdf, $mm, PREG_SET_ORDER)) foreach ($mm as $o) {
        if (count($objs) > 6000) break;
        $cuerpo = $o[2];
        $dic = $cuerpo;
        $flujo = null;
        if (preg_match('/^(.*?)\bstream\r?\n(.*)$/s', $cuerpo, $sm)) {
            $dic = $sm[1];
            $raw = preg_replace('/\r?\n?endstream\s*$/', '', $sm[2]);
            if (preg_match('~/FlateDecode~', $dic)) {
                $flujo = @gzuncompress((string) $raw, 6 * 1024 * 1024);
                if ($flujo === false) $flujo = null;
            } elseif (!preg_match('~/Filter~', $dic)) $flujo = $raw;
            if ($flujo !== null && ($total += strlen($flujo)) > IMPORTAR_MAX_DESCOMPRIMIDO * 2) throw new ImportarError('Ese PDF es demasiado grande.');
        }
        $objs[(int) $o[1]] = [$dic, $flujo];
    }
    // Fuentes: nombre de recurso → mapa ToUnicode (global; los PDF habituales usan los mismos nombres en todo el documento)
    $mapas = [];
    $porNombre = [];
    foreach ($objs as $n => [$dic, $flujo]) {
        if (preg_match('~/Font\s*<<(.*?)>>~s', $dic, $fm) && preg_match_all('~/(\w+)\s+(\d+)\s+0\s+R~', $fm[1], $fr, PREG_SET_ORDER)) foreach ($fr as $r) $porNombre[$r[1]] = (int) $r[2];
    }
    foreach ($porNombre as $nombre => $num) {
        $d = $objs[$num][0] ?? '';
        if (preg_match('~/ToUnicode\s+(\d+)\s+0\s+R~', $d, $tu) && isset($objs[(int) $tu[1]][1])) $mapas[$nombre] = imp_pdf_cmap($objs[(int) $tu[1]][1]);
    }
    // Página de cada flujo de contenido (una página puede traer varios): se agrupa por página, no por flujo
    $pagina = [];
    ksort($objs);
    $np = 0;
    foreach ($objs as $n => [$dic]) {
        if (!preg_match('~/Type\s*/Page\b(?!s)~', $dic) || !preg_match('~/Contents\s*(\[[^\]]*\]|\d+\s+0\s+R)~', $dic, $cm)) continue;
        $np++;
        if (preg_match_all('~(\d+)\s+0\s+R~', $cm[1], $cr)) foreach ($cr[1] as $cn) $pagina[(int) $cn] = $np;
    }
    $trozos = [];
    foreach ($objs as $n => [$dic, $flujo]) {
        if ($flujo === null || strpos($flujo, 'BT') === false || preg_match('~/(Subtype\s*/(Image|Form|Type1C|CIDFontType0C)|Type\s*/(XObject|Font|FontFile))~', $dic) || strpos($flujo, 'beginbfchar') !== false) continue;
        foreach (imp_pdf_contenido($flujo, $mapas) as $t) { $t[0] = $pagina[$n] ?? 1000 + $n; $trozos[] = $t; if (count($trozos) > IMPORTAR_MAX_TROZOS) throw new ImportarError('Ese PDF es demasiado complejo de leer. Subid el Excel o pegad la lista.'); }
    }
    return $trozos;
}

/** Recorre un flujo de contenido y devuelve sus trozos de texto con posición aproximada. */
function imp_pdf_contenido(string $s, array $mapas): array {
    $o = [];
    $len = strlen($s);
    $i = 0;
    $pila = [];
    $fuente = '';
    $tam = 10.0;
    [$x, $y, $lx, $ly, $lead] = [0.0, 0.0, 0.0, 0.0, 0.0];
    $decod = function (string $bytes) use (&$fuente, $mapas): string {
        [$mapa, $largo] = $mapas[$fuente] ?? [null, 1];
        if ($mapa) {
            $t = '';
            foreach (str_split($bytes, $largo) as $c) $t .= $mapa[$c] ?? ($largo === 1 ? '' : '');
            return $t;
        }
        return (string) mb_convert_encoding($bytes, 'UTF-8', 'Windows-1252');
    };
    $emite = function (string $t) use (&$o, &$x, &$y, &$tam) { if (trim($t) !== '') $o[] = [0, $x, $y, $tam, $t]; };
    $abre = [];
    $vuelta = 0;
    while ($i < $len) {
        if ((++$vuelta & 255) === 0) imp_plazo();
        if (count($pila) > IMPORTAR_MAX_PILA || count($o) > IMPORTAR_MAX_TROZOS) throw new ImportarError('Ese PDF es demasiado complejo de leer. Subid el Excel o pegad la lista.');
        $ch = $s[$i];
        if (ctype_space($ch)) { $i++; continue; }
        if ($ch === '%') { $i = (int) strpos($s . "\n", "\n", $i) + 1; continue; }
        if ($ch === '(') {   // cadena literal con escapes y paréntesis anidados
            $nivel = 1; $b = ''; $i++;
            while ($i < $len && $nivel > 0) {
                $c = $s[$i];
                if ($c === '\\') {
                    $n = $s[$i + 1] ?? '';
                    if (ctype_digit($n)) { preg_match('/^[0-7]{1,3}/', substr($s, $i + 1, 3), $oc); $b .= chr(octdec($oc[0]) & 255); $i += 1 + strlen($oc[0]); continue; }
                    $b .= ['n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\x0c"][$n] ?? ($n === "\n" ? '' : $n);
                    $i += 2; continue;
                }
                if ($c === '(') $nivel++;
                elseif ($c === ')') { $nivel--; if ($nivel === 0) { $i++; break; } }
                $b .= $c; $i++;
            }
            $pila[] = ['s', $b]; continue;
        }
        if ($ch === '<' && ($s[$i + 1] ?? '') !== '<') {
            $e = strpos($s, '>', $i); if ($e === false) break;
            $h = preg_replace('/\s+/', '', substr($s, $i + 1, $e - $i - 1));
            $pila[] = ['s', (string) hex2bin(strlen($h) % 2 ? $h . '0' : $h)]; $i = $e + 1; continue;
        }
        if ($ch === '[') { $abre[] = count($pila); $pila[] = ['[', null]; $i++; continue; }
        if ($ch === ']') {
            $k = array_pop($abre);
            if ($k === null) { $i++; continue; }
            $a = array_slice(array_splice($pila, $k), 1);   // lo de dentro, sin la marca «[»; O(n), no O(n²)
            $pila[] = ['a', $a]; $i++; continue;
        }
        if ($ch === '/') { preg_match('~^/[^\s/\[\]()<>%]*~', substr($s, $i, 80), $nm); $pila[] = ['n', substr($nm[0], 1)]; $i += strlen($nm[0]); continue; }
        if ($ch === '<' || $ch === '>' || $ch === '{' || $ch === '}') { $i += ($s[$i + 1] ?? '') === $ch ? 2 : 1; continue; }
        if (preg_match('/^[+-]?(\d+\.?\d*|\.\d+)/', substr($s, $i, 24), $nm)) { $pila[] = ['#', (float) $nm[0]]; $i += strlen($nm[0]); continue; }
        preg_match('/^[A-Za-z\'"*]+/', substr($s, $i, 12), $op);
        $op = $op[0] ?? '';
        $i += max(1, strlen($op));
        $ult = fn(int $k) => array_slice(array_values(array_filter($pila, fn($t) => $t[0] === '#')), -$k);
        switch ($op) {
            case 'BT': [$x, $y, $lx, $ly] = [0.0, 0.0, 0.0, 0.0]; break;
            case 'Tf': foreach ($pila as $t) if ($t[0] === 'n') $fuente = $t[1]; $nn = $ult(1); $tam = abs($nn[0][1] ?? 10.0) ?: 10.0; break;
            case 'Td': case 'TD':
                $nn = $ult(2); if (count($nn) === 2) { $lx += $nn[0][1]; $ly += $nn[1][1]; [$x, $y] = [$lx, $ly]; if ($op === 'TD') $lead = -$nn[1][1]; } break;
            case 'Tm': $nn = $ult(6); if (count($nn) === 6) { [$lx, $ly] = [$nn[4][1], $nn[5][1]]; [$x, $y] = [$lx, $ly]; if (abs($nn[3][1]) >= 2 && abs($nn[3][1]) < 100) $tam = abs($nn[3][1]); } break;
            case 'TL': $nn = $ult(1); $lead = $nn[0][1] ?? 0.0; break;
            case 'T*': $ly -= $lead; [$x, $y] = [$lx, $ly]; break;
            case "'": case '"': $ly -= $lead; [$x, $y] = [$lx, $ly]; $st = array_values(array_filter($pila, fn($t) => $t[0] === 's')); if ($st) $emite($decod(end($st)[1])); break;
            case 'Tj': $st = array_values(array_filter($pila, fn($t) => $t[0] === 's')); if ($st) $emite($decod(end($st)[1])); break;
            case 'TJ':
                $a = null; foreach ($pila as $t) if ($t[0] === 'a') $a = $t[1];
                if ($a !== null) {
                    $t = '';
                    foreach ($a as $e) { if ($e[0] === 's') $t .= $decod($e[1]); elseif ($e[0] === '#' && $e[1] <= -200) $t .= ' '; }
                    $emite($t);
                }
                break;
        }
        $pila = [];
        $abre = [];
    }
    return $o;
}

/** Filas de un PDF: los trozos se agrupan por línea (misma y) y, si hay cabecera «Nombre», se reparten en sus columnas por la x. */
function imp_pdf(string $pdf): array {
    $tr = imp_pdf_trozos($pdf);
    if (!$tr) throw new ImportarError('No he encontrado texto en ese PDF (¿es un escaneo?). Subid el Excel o pegad la lista.');
    $lineas = [];
    foreach ($tr as [$pag, $x, $y, $tam, $t]) $lineas[$pag . '|' . (int) round($y / 3)][] = [$x, $tam, $t, $pag, $y];
    uasort($lineas, fn($a, $b) => [$a[0][3], -$a[0][4]] <=> [$b[0][3], -$b[0][4]]);
    $filas = [];   // cada fila: [[x, texto], …] ya con los trozos pegados cuando están juntos
    $meta = [];    // de cada fila: página, y y tamaño de letra
    foreach ($lineas as $items) {
        usort($items, fn($a, $b) => $a[0] <=> $b[0]);
        $fila = [];
        foreach ($items as [$x, $tam, $t]) {
            $ancho = fn(string $s) => exp_pdf_ancho(exp_pdf_texto($s), $tam);
            $t = trim(preg_replace('/\s+/', ' ', $t));
            if ($t === '') continue;
            $k = count($fila) - 1;
            if ($k >= 0 && $x - $fila[$k][2] < $tam * 0.8) { $fila[$k][1] .= ' ' . $t; $fila[$k][2] = $x + $ancho($t); }
            else $fila[] = [$x, $t, $x + $ancho($t)];
        }
        imp_plazo();
        $fila = array_slice($fila, 0, IMPORTAR_MAX_CELDAS);
        if ($fila) { $filas[] = array_map(fn($c) => [$c[0], $c[1]], $fila); $meta[] = [$items[0][3], $items[0][4], $items[0][1]]; }
        if (count($filas) >= IMPORTAR_MAX_FILAS) break;
    }
    // Cabecera con «Nombre»: columnas por posición; las filas que no traen nombre (títulos, pies, segundas líneas de un grupo) quedan vacías
    foreach ($filas as $fi => $fila) {
        $nombres = array_map(fn($c) => imp_clave_col($c[1]), $fila);
        if (in_array('nombre', $nombres, true) && count($fila) >= 2) {
            $xs = array_column($fila, 0);
            $res = [];
            foreach ($filas as $k => $f) {
                $r = array_fill(0, count($xs), '');
                foreach ($f as [$x, $t]) { $c = 0; foreach ($xs as $j => $xj) if ($x + 6 >= $xj) $c = $j; $r[$c] = trim($r[$c] . ' ' . $t); }
                // En una tabla, una línea pegada a la anterior (interlineado, no separación de filas) es su continuación: nombre o grupo que no cupo en una línea
                if ($k > $fi + 1 && $res && $meta[$k][0] === $meta[$k - 1][0] && $meta[$k - 1][1] - $meta[$k][1] <= $meta[$k][2] * 1.31) {
                    foreach ($r as $j => $t) if ($t !== '') $res[count($res) - 1][$j] = trim($res[count($res) - 1][$j] . ' ' . $t);
                    continue;
                }
                $res[] = $r;
            }
            return array_slice($res, $fi);
        }
    }
    return array_map(fn($f) => array_column($f, 1), $filas);
}

// ---------------------------------------------------------------- de filas a «nombre; grupo»
function imp_clave_col(string $t): string {
    $k = preg_replace('/[^a-z]/', '', clave_nombre($t));
    if (in_array($k, ['nombre', 'nombres', 'invitado', 'invitados', 'name', 'nombreyapellidos', 'nombrecompleto'], true)) return 'nombre';
    if (in_array($k, ['apellido', 'apellidos', 'surname'], true)) return 'apellidos';
    if (in_array($k, ['grupo', 'familia', 'grupofamiliar', 'unidadfamiliar'], true)) return 'grupo';
    return '';
}

/** Filas (celdas) → líneas «nombre; grupo». Con cabecera usa las columnas Nombre, Apellidos y Grupo/Familia; sin ella, la 1.ª es el nombre y la 2.ª el grupo. */
function imp_filas_a_texto(array $filas): array {
    $filas = array_values(array_filter($filas, fn($f) => array_filter(array_map('trim', $f), 'strlen')));
    $cn = $ca = $cg = null;
    $ini = 0;
    foreach (array_slice($filas, 0, 15) as $i => $f) {
        $cl = array_map('imp_clave_col', $f);
        if (in_array('nombre', $cl, true)) { $cn = array_search('nombre', $cl, true); $ca = array_search('apellidos', $cl, true); $cg = array_search('grupo', $cl, true); $ini = $i + 1; break; }
    }
    if ($cn === null) {
        $cn = 0; $cg = 1; $ca = false;
        $cols = max(array_map('count', $filas) ?: [0]);
        // Columnas numéricas al principio (nº de orden): se saltan
        for ($c = 0; $c < $cols - 1; $c++) {
            $v = array_filter(array_map(fn($f) => trim((string) ($f[$c] ?? '')), $filas), 'strlen');
            if ($v && count(array_filter($v, fn($t) => preg_match('/^\d+[.)]?$/', $t))) === count($v)) { $cn = $c + 1; $cg = $c + 2; } else break;
        }
    }
    $o = [];
    foreach (array_slice($filas, $ini) as $f) {
        $n = trim((string) ($f[$cn] ?? ''));
        if ($ca !== false && $ca !== null) $n = trim($n . ' ' . trim((string) ($f[$ca] ?? '')));
        if ($n === '' || preg_match('/^\d+[.)]?$/', $n) || imp_clave_col($n) === 'nombre') continue;   // vacía, nº de orden o cabecera repetida (cada página de un PDF)
        $g = ($cg !== false && $cg !== null) ? trim((string) ($f[$cg] ?? '')) : '';
        $o[] = str_replace([';', "\t", "\n"], ' ', $n) . ($g !== '' ? '; ' . str_replace([';', "\t", "\n"], ' ', $g) : '');
    }
    return $o;
}

/** Contenido subido → líneas «nombre; grupo». El tipo se decide por los primeros bytes. */
function imp_lee(string $bin): array {
    $GLOBALS['IMP_FIN'] = microtime(true) + IMPORTAR_SEGUNDOS;
    if (strncmp($bin, "PK\x03\x04", 4) === 0) {
        $nombres = [];
        $fin = strrpos($bin, "PK\x05\x06");
        if ($fin !== false) { imp_zip($bin, function ($n) use (&$nombres) { $nombres[] = $n; return false; }); }
        if (in_array('xl/workbook.xml', $nombres, true)) return imp_filas_a_texto(imp_xlsx($bin));
        if (in_array('word/document.xml', $nombres, true)) return imp_filas_a_texto(imp_docx($bin));
        throw new ImportarError('Ese archivo no es un Excel (.xlsx) ni un Word (.docx).');
    }
    if (strncmp($bin, '%PDF', 4) === 0) return imp_filas_a_texto(imp_pdf($bin));
    if (strncmp($bin, "\xD0\xCF\x11\xE0", 4) === 0) throw new ImportarError('Ese es un Excel o un Word antiguo (.xls o .doc). Guardadlo como .xlsx o .docx desde el programa y subidlo otra vez.');
    $t = preg_replace('/^\xEF\xBB\xBF/', '', $bin);
    if (strpos($t, "\0") !== false) throw new ImportarError('No reconozco ese archivo. Podéis subir Excel (.xlsx), Word (.docx), PDF, .csv o .txt.');
    if (!mb_check_encoding($t, 'UTF-8')) $t = (string) mb_convert_encoding($t, 'UTF-8', 'Windows-1252');
    $filas = [];
    foreach (preg_split('/\r\n|\r|\n/', $t) ?: [] as $l) { if (trim($l) !== '') $filas[] = array_map('trim', str_getcsv($l, strpos($l, ';') !== false ? ';' : (strpos($l, "\t") !== false ? "\t" : ','), '"', '')); if (count($filas) >= IMPORTAR_MAX_FILAS) break; }
    return imp_filas_a_texto($filas);
}

// ---------------------------------------------------------------- ruta POST /panel/invitados/importar
/** El resultado se enseña en la página de Invitados (panel_invitados lo lee): [texto del cuadro, aviso, error]. */
$GLOBALS['INV_IMPORTADO'] = null;

function panel_invitados_importar(string $slug, array $c, string $metodo): void {
    // Un archivo mayor que post_max_size llega con $_POST y $_FILES vacíos (y sin CSRF): se contesta con el motivo, no con un 403 mudo
    $demasiado = $metodo === 'POST' && !$_POST && !$_FILES && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0;
    if ($metodo !== 'POST' || (!$demasiado && !panel_csrf_ok())) { http_response_code(403); exit; }
    $res = ['texto' => null, 'aviso' => '', 'error' => ''];
    $f = $_FILES['archivo'] ?? null;
    try {
        if ($demasiado) throw new ImportarError('El archivo pesa demasiado (máximo 2 MB). Para una lista de invitados sobra: quizá lleva imágenes.');
        if (!limite('importar|' . $slug, 20, 3600, true)) throw new ImportarError('Demasiados intentos seguidos. Esperad un rato y probad de nuevo.');
        if (!is_array($f) || ($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) throw new ImportarError('Elegid primero un archivo.');
        if (($f['error'] ?? 0) === UPLOAD_ERR_INI_SIZE || ($f['error'] ?? 0) === UPLOAD_ERR_FORM_SIZE || (int) ($f['size'] ?? 0) > IMPORTAR_MAX_BYTES) throw new ImportarError('El archivo pesa demasiado (máximo 2 MB). Para una lista de invitados sobra: quizá lleva imágenes.');
        if (($f['error'] ?? 0) !== UPLOAD_ERR_OK || !is_uploaded_file((string) $f['tmp_name'])) throw new ImportarError('No se ha podido recibir el archivo. Probad otra vez.');
        $bin = (string) file_get_contents((string) $f['tmp_name'], false, null, 0, IMPORTAR_MAX_BYTES + 1);
        if (strlen($bin) > IMPORTAR_MAX_BYTES) throw new ImportarError('El archivo pesa demasiado (máximo 2 MB).');
        $lineas = imp_lee($bin);
        if (!$lineas) throw new ImportarError('No he encontrado nombres en ese archivo. Poned un nombre por fila (y, si queréis, el grupo en la columna de al lado) o pegad la lista.');
        $nuevas = inv_parsea(implode("\n", $lineas));
        $actual = inv_lee($slug)['lista'];
        $ids = array_column($actual, 'id');
        $anadidas = count(array_filter($nuevas, fn($g) => !in_array($g['id'], $ids, true)));
        $todas = array_slice(array_merge($actual, array_filter($nuevas, fn($g) => !in_array($g['id'], $ids, true))), 0, INVITADOS_MAX);
        $res['texto'] = implode("\n", array_map(fn($g) => $g['nombre'] . ($g['grupo'] !== '' ? '; ' . $g['grupo'] : ''), $todas));
        $res['aviso'] = 'He leído ' . count($nuevas) . ($nuevas === [] || count($nuevas) !== 1 ? ' invitados' : ' invitado') . ' del archivo y ' . $anadidas . ($anadidas === 1 ? ' es nuevo' : ' son nuevos')
            . ' respecto a vuestra lista. Revisadlos aquí abajo y pulsad «Guardar lista»: hasta entonces no se ha guardado nada.'
            . (count($actual) + $anadidas > INVITADOS_MAX ? ' (Tope de ' . INVITADOS_MAX . ' invitados: el resto se ha dejado fuera.)' : '');
    } catch (ImportarError $e) {
        $res['error'] = $e->getMessage();
    } catch (Throwable $e) {
        registra('importar lista: fallo al leer', ['slug' => $slug, 'error' => get_class($e)]);
        $res['error'] = 'No he podido leer ese archivo. Probad con otro formato (Excel o Word) o pegad la lista.';
    }
    $GLOBALS['INV_IMPORTADO'] = $res;
    echo panel_pagina($slug, $c, 'invitados', PANEL_SECCIONES['invitados'][1], panel_invitados($slug, $c));
    exit;
}
