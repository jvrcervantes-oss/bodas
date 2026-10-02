<?php
// Exportar la lista de invitados desde el panel: Excel (.xlsx), PDF y Word (.docx). Sin librerías ni extensiones:
// no hay composer y ZipArchive no está en todos los PHP, así que el .zip de xlsx/docx y el PDF se escriben aquí
// (formatos mínimos pero válidos). Una fila por persona: grupo, nombre y estado, la misma cuenta que «Persona a
// persona» (inv_cruza). Ni contacto ni alergias ni el enlace personal de cada grupo: es la lista de la pareja,
// que ya la tiene en el panel, y un fichero suelto no se puede revocar como un enlace.
declare(strict_types=1);

const EXPORT_FORMATOS = [
    'xlsx' => ['Excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
    'pdf' => ['PDF', 'application/pdf'],
    'docx' => ['Word', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
];
const EXPORT_ESTADO = ['viene' => 'Viene', 'no' => 'No viene', 'pend' => 'Sin contestar'];

/** Texto seguro para XML: sin caracteres de control que lo invalidan y con las cinco entidades escapadas. */
function exp_xml(string $s): string {
    $s = (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $s);
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/** Zip sin compresión (método 0): basta para estos ficheros pequeños y no depende de ZipArchive. */
function exp_zip(array $ficheros): string {
    $datos = $central = '';
    $n = 0;
    [$hora, $dia] = [0, (1 << 5) | 1];   // 1-ene-1980 00:00: fecha fija, el resultado no depende del reloj
    foreach ($ficheros as $nombre => $c) {
        $c = (string) $c;
        $crc = crc32($c);
        $cab = pack('vvvvvVVVvv', 20, 0x0800, 0, $hora, $dia, $crc, strlen($c), strlen($c), strlen($nombre), 0) . $nombre;
        $central .= "PK\x01\x02" . pack('vvvvvvVVVvvvvvVV', 20, 20, 0x0800, 0, $hora, $dia, $crc, strlen($c), strlen($c), strlen($nombre), 0, 0, 0, 0, 0, strlen($datos)) . $nombre;
        $datos .= "PK\x03\x04" . $cab . $c;
        $n++;
    }
    return $datos . $central . "PK\x05\x06" . pack('vvvvVVv', 0, 0, $n, $n, strlen($central), strlen($datos), 0);
}

/** Filas de la exportación: [grupo, nombre, estado], en el orden de la lista de la pareja (grupo y nombre). */
function exp_filas(string $slug, array $c): array {
    [$filas, $res] = inv_cruza($slug, $c);
    usort($filas, fn($a, $b) => [clave_nombre($a['grupo']) === '' ? 1 : 0, clave_nombre($a['grupo']), clave_nombre($a['nombre'])] <=> [clave_nombre($b['grupo']) === '' ? 1 : 0, clave_nombre($b['grupo']), clave_nombre($b['nombre'])]);
    return [array_map(fn($f) => [$f['grupo'], $f['nombre'], EXPORT_ESTADO[$f['estado']] . ($f['manual'] ? ' (a mano)' : '')], $filas), $res];
}

function exp_titulo(array $c): string { return 'Lista de invitados' . (nombres($c) !== '' ? ' · ' . nombres($c) : ''); }

// ---------------------------------------------------------------- Excel
function exp_xlsx(array $filas, array $res, array $c): string {
    $cab = ['Grupo', 'Nombre', 'Estado'];
    $fila = function (int $r, array $v, int $estilo) {
        $o = '<row r="' . $r . '">';
        foreach ($v as $i => $t) $o .= '<c r="' . chr(65 + $i) . $r . '" s="' . $estilo . '" t="inlineStr"><is><t xml:space="preserve">' . exp_xml((string) $t) . '</t></is></c>';
        return $o . '</row>';
    };
    $sh = '';
    $sh .= $fila(1, $cab, 1);
    foreach ($filas as $i => $f) $sh .= $fila($i + 2, $f, 0);
    $ult = count($filas) + 1;
    $hoja = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
        . '<cols><col min="1" max="1" width="32" customWidth="1"/><col min="2" max="2" width="36" customWidth="1"/><col min="3" max="3" width="22" customWidth="1"/></cols>'
        . '<sheetData>' . $sh . '</sheetData><autoFilter ref="A1:C' . $ult . '"/></worksheet>';
    $estilos = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
        . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
        . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
        . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    return exp_zip([
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/></Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
        'xl/workbook.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Invitados" sheetId="1" r:id="rId1"/></sheets><definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">Invitados!$A$1:$C$' . $ult . '</definedName></definedNames></workbook>',
        'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>',
        'xl/styles.xml' => $estilos,
        'xl/worksheets/sheet1.xml' => $hoja,
    ]);
}

// ---------------------------------------------------------------- Word
function exp_docx(array $filas, array $res, array $c): string {
    $p = fn(string $t, string $rpr = '', string $ppr = '') => '<w:p>' . ($ppr !== '' ? '<w:pPr>' . $ppr . '</w:pPr>' : '') . '<w:r>' . ($rpr !== '' ? '<w:rPr>' . $rpr . '</w:rPr>' : '') . '<w:t xml:space="preserve">' . exp_xml($t) . '</w:t></w:r></w:p>';
    $celda = fn(string $t, int $w, bool $neg) => '<w:tc><w:tcPr><w:tcW w:w="' . $w . '" w:type="dxa"/>' . ($neg ? '<w:shd w:val="clear" w:color="auto" w:fill="EFE7E2"/>' : '') . '</w:tcPr>'
        . $p($t, $neg ? '<w:b/>' : '', '<w:spacing w:before="40" w:after="40"/>') . '</w:tc>';
    $W = [3400, 3800, 2200];
    $tabla = '<w:tbl><w:tblPr><w:tblW w:w="9400" w:type="dxa"/><w:tblBorders>';
    foreach (['top', 'left', 'bottom', 'right', 'insideH', 'insideV'] as $b) $tabla .= '<w:' . $b . ' w:val="single" w:sz="4" w:space="0" w:color="C9BDB6"/>';
    $tabla .= '</w:tblBorders><w:tblLayout w:type="fixed"/></w:tblPr><w:tblGrid>' . implode('', array_map(fn($w) => '<w:gridCol w:w="' . $w . '"/>', $W)) . '</w:tblGrid>';
    $tabla .= '<w:tr><w:trPr><w:tblHeader/></w:trPr>' . $celda('Grupo', $W[0], true) . $celda('Nombre', $W[1], true) . $celda('Estado', $W[2], true) . '</w:tr>';
    foreach ($filas as $f) $tabla .= '<w:tr><w:trPr><w:cantSplit/></w:trPr>' . $celda($f[0], $W[0], false) . $celda($f[1], $W[1], false) . $celda($f[2], $W[2], false) . '</w:tr>';
    $tabla .= '</w:tbl>';
    $resumen = count($filas) . ' invitados · ' . $res['viene'] . ' vienen · ' . $res['no'] . ' no vienen · ' . $res['pend'] . ' sin contestar';
    $cuerpo = $p(exp_titulo($c), '<w:b/><w:sz w:val="36"/>', '<w:spacing w:after="80"/>')
        . $p($resumen . '. Lista del ' . date('d/m/Y') . '.', '<w:color w:val="6B5F5A"/><w:sz w:val="20"/>', '<w:spacing w:after="240"/>')
        . $tabla . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134" w:header="567" w:footer="567" w:gutter="0"/></w:sectPr>';
    return exp_zip([
        '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>',
        '_rels/.rels' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>',
        'word/document.xml' => '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>' . $cuerpo . '</w:body></w:document>',
    ]);
}

// ---------------------------------------------------------------- PDF
/** Anchos de Helvetica (milésimas de em) de ASCII 32-126; todo lo que no es ASCII (tildes, ñ) se mide con 556, algo más ancho que su letra base: sobra margen, nunca falta. */
const EXP_ANCHOS = [278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556,
    1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556,
    333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556, 556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584];

/** UTF-8 → Windows-1252 (lo que habla la fuente estándar del PDF); lo que no cabe sale «?». */
function exp_pdf_texto(string $s): string {
    $s = (string) preg_replace('/[\x00-\x1F\x7F]/u', ' ', $s);
    return (string) mb_convert_encoding($s, 'Windows-1252', 'UTF-8');
}
function exp_pdf_ancho(string $t, float $pt): float {
    $w = 0;
    foreach (str_split($t) as $ch) {
        $o = ord($ch);
        $w += $o >= 32 && $o <= 126 ? EXP_ANCHOS[$o - 32] : 556;
    }
    return $w * $pt / 1000;
}
/** Parte un texto (ya en cp1252) en líneas que caben en `$ancho` puntos; una palabra más larga que la línea se corta. */
function exp_pdf_lineas(string $t, float $ancho, float $pt): array {
    $o = [];
    $l = '';
    foreach (preg_split('/ +/', trim($t)) ?: [] as $pal) {
        while (exp_pdf_ancho($pal, $pt) > $ancho) {
            $i = strlen($pal);
            while ($i > 1 && exp_pdf_ancho(substr($pal, 0, $i), $pt) > $ancho) $i--;
            if ($l !== '') { $o[] = $l; $l = ''; }
            $o[] = substr($pal, 0, $i);
            $pal = substr($pal, $i);
        }
        $prueba = $l === '' ? $pal : $l . ' ' . $pal;
        if ($l !== '' && exp_pdf_ancho($prueba, $pt) > $ancho) { $o[] = $l; $l = $pal; } else $l = $prueba;
    }
    if ($l !== '' || !$o) $o[] = $l;
    return $o;
}
function exp_pdf_esc(string $s): string { return strtr($s, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)']); }

function exp_pdf(array $filas, array $res, array $c): string {
    $pw = 595; $ph = 842; $mx = 48; $top = 56; $pie = 52;
    $col = [$mx, $mx + 170, $mx + 370];            // izquierda de Grupo, Nombre y Estado
    $anch = [160, 190, $pw - $mx - ($mx + 370)];
    $sz = 10; $alto = 13;
    $paginas = [];
    $cur = '';
    $y = $ph - $top;
    $texto = function (float $x, float $y, string $t, bool $neg, float $pt, string $gris = '0 g') use (&$cur) {
        $cur .= 'BT ' . $gris . ' /' . ($neg ? 'F2' : 'F1') . ' ' . $pt . ' Tf ' . round($x, 2) . ' ' . round($y, 2) . ' Td (' . exp_pdf_esc($t) . ") Tj ET\n";
    };
    $cabTabla = function () use (&$cur, &$y, $col, $mx, $pw, $texto, $sz) {
        $cur .= '0.937 0.906 0.886 rg ' . $mx . ' ' . ($y - 5) . ' ' . ($pw - 2 * $mx) . " 18 re f\n";
        foreach (['Grupo', 'Nombre', 'Estado'] as $i => $t) $texto($col[$i] + 4, $y, $t, true, $sz);
        $y -= 20;
    };
    $nuevaPagina = function () use (&$paginas, &$cur, &$y, $ph, $top) { if ($cur !== '') $paginas[] = $cur; $cur = ''; $y = $ph - $top; };
    // Portada de la primera hoja: título, resumen y fecha
    $texto($mx, $y, exp_pdf_texto(exp_titulo($c)), true, 18);
    $y -= 20;
    $texto($mx, $y, exp_pdf_texto(count($filas) . ' invitados · ' . $res['viene'] . ' vienen · ' . $res['no'] . ' no vienen · ' . $res['pend'] . ' sin contestar'), false, 10, '0.42 0.37 0.35 rg');
    $y -= 13;
    $texto($mx, $y, exp_pdf_texto('Lista del ' . date('d/m/Y')), false, 10, '0.42 0.37 0.35 rg');
    $y -= 26;
    $cabTabla();
    foreach ($filas as $f) {
        $l = [exp_pdf_lineas(exp_pdf_texto($f[0]), $anch[0] - 8, $sz), exp_pdf_lineas(exp_pdf_texto($f[1]), $anch[1] - 8, $sz), exp_pdf_lineas(exp_pdf_texto($f[2]), $anch[2] - 8, $sz)];
        $n = max(count($l[0]), count($l[1]), count($l[2]));
        if ($y - $n * $alto < $pie) { $nuevaPagina(); $cabTabla(); }
        foreach ($l as $i => $ls) foreach ($ls as $k => $t) $texto($col[$i] + 4, $y - $k * $alto, $t, false, $sz);
        $cur .= '0.85 0.82 0.8 RG 0.5 w ' . $mx . ' ' . round($y - ($n - 1) * $alto - 5, 2) . ' m ' . ($pw - $mx) . ' ' . round($y - ($n - 1) * $alto - 5, 2) . " l S\n";
        $y -= $n * $alto + 3;
    }
    if (!$filas) $texto($col[0] + 4, $y, 'La lista está vacía.', false, $sz);
    $paginas[] = $cur;
    // Objetos: 1 catálogo, 2 páginas, 3 y 4 fuentes, luego (página, contenido) por hoja
    $obj = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>'];
    $kids = [];
    $total = count($paginas);
    foreach ($paginas as $i => $cont) {
        $cont .= 'BT 0.42 0.37 0.35 rg /F1 8 Tf ' . $mx . ' 30 Td (' . exp_pdf_esc(exp_pdf_texto(exp_titulo($c))) . ') Tj ET' . "\n"
            . 'BT 0.42 0.37 0.35 rg /F1 8 Tf ' . ($pw - $mx - 30) . ' 30 Td (' . ($i + 1) . ' / ' . $total . ") Tj ET\n";
        $np = 5 + $i * 2;
        $kids[] = $np . ' 0 R';
        $obj[$np] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . $pw . ' ' . $ph . '] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents ' . ($np + 1) . ' 0 R >>';
        $obj[$np + 1] = '<< /Length ' . strlen($cont) . " >>\nstream\n" . $cont . 'endstream';
    }
    $obj[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . $total . ' >>';
    ksort($obj);
    $pdf = "%PDF-1.4\n";
    $pos = [];
    foreach ($obj as $n => $cuerpo) { $pos[$n] = strlen($pdf); $pdf .= $n . " 0 obj\n" . $cuerpo . "\nendobj\n"; }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($obj) + 1) . "\n0000000000 65535 f \n";
    foreach ($pos as $p) $pdf .= sprintf("%010d 00000 n \n", $p);
    return $pdf . "trailer\n<< /Size " . (count($obj) + 1) . " /Root 1 0 R /Info << /Title (" . exp_pdf_esc(exp_pdf_texto(exp_titulo($c))) . ") >> >>\nstartxref\n" . $xref . "\n%%EOF\n";
}

// ---------------------------------------------------------------- ruta /panel/invitados/exportar?f=xlsx|pdf|docx
function panel_invitados_exportar(string $slug, array $c, string $metodo): void {
    if ($metodo !== 'GET' && $metodo !== 'HEAD') { header('Allow: GET, HEAD'); http_response_code(405); exit; }
    $f = (string) ($_GET['f'] ?? '');
    if (!isset(EXPORT_FORMATOS[$f])) { http_response_code(404); exit; }
    if (!inv_lee($slug)['lista']) { header('Location: /panel/invitados', true, 303); exit; }
    [$filas, $res] = exp_filas($slug, $c);
    $bin = $f === 'xlsx' ? exp_xlsx($filas, $res, $c) : ($f === 'pdf' ? exp_pdf($filas, $res, $c) : exp_docx($filas, $res, $c));
    header('Content-Type: ' . EXPORT_FORMATOS[$f][1]);
    header('Content-Disposition: attachment; filename="invitados-' . $slug . '-' . date('Y-m-d') . '.' . $f . '"');
    header('Content-Length: ' . strlen($bin));
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    if ($metodo !== 'HEAD') echo $bin;
    exit;
}
