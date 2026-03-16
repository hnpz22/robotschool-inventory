<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();

$db    = Database::get();
$id    = (int)($_GET['id'] ?? 0);
if (!$id) die('Pedido no especificado.');

$ped   = $db->query("SELECT * FROM pedidos_importacion WHERE id=$id")->fetch();
if (!$ped) die('Pedido no encontrado.');

$items = $db->query("
    SELECT pi.descripcion_item, pi.cantidad, pi.precio_unit_usd,
           (pi.cantidad * pi.precio_unit_usd) AS total,
           e.codigo AS elem_codigo
    FROM pedido_items pi
    LEFT JOIN elementos e ON e.id = pi.elemento_id
    WHERE pi.pedido_id = $id
    ORDER BY pi.id
")->fetchAll();

if (empty($items)) die('El pedido no tiene items aun.');

// &#9472;&#9472; Generar XLSX con PHP puro (sin librer&#237;a) &#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;
// Usamos SpreadsheetWriter minimalista

$rows   = [];
$fob    = 0;

foreach ($items as $i => $it) {
    $rows[] = [
        'no'    => $i + 1,
        'desc'  => $it['descripcion_item'],
        'qty'   => (int)$it['cantidad'],
        'photo' => '',
        'usdp'  => (float)$it['precio_unit_usd'],
        'total' => (float)$it['total'],
    ];
    $fob += (float)$it['total'];
}

$filename = 'Orden_' . ($ped['codigo_pedido'] ?? 'ROBOTSchool') . '_' . date('Ymd') . '.xlsx';

// Generar XLSX puro
header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

echo generarXLSX($rows, $fob, $ped);
exit;

// &#9472;&#9472; GENERADOR XLSX MINIMALISTA &#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;
function generarXLSX(array $rows, float $fob, array $ped): string {
    // Construir worksheet XML
    $sharedStrings = [];
    $ssIdx = function(string $s) use (&$sharedStrings): int {
        $i = array_search($s, $sharedStrings);
        if ($i === false) { $sharedStrings[] = $s; $i = count($sharedStrings)-1; }
        return $i;
    };

    // Celdas
    function colLetter(int $n): string {
        $s = '';
        $n++;
        while ($n > 0) { $s = chr(65+(($n-1)%26)) . $s; $n = intdiv($n-1,26); }
        return $s;
    }

    $sheetRows = [];

    // Fila 1: T&#237;tulo
    $sheetRows[] = buildRow(1, [
        [0, 's', $ssIdx('ROBOTSchool Colombia &#8212; Orden de Compra')],
    ]);

    // Fila 2: Cabecera
    $headers = ['No','Description','QTY','Photo','USD/P','Total'];
    $hCells = [];
    foreach ($headers as $ci => $h) $hCells[] = [$ci, 's', $ssIdx($h)];
    $sheetRows[] = buildRow(2, $hCells);

    // Filas de productos
    $rn = 3;
    foreach ($rows as $r) {
        $sheetRows[] = buildRow($rn, [
            [0, 'n', $r['no']],
            [1, 's', $ssIdx($r['desc'])],
            [2, 'n', $r['qty']],
            [3, 's', $ssIdx('')],
            [4, 'n', $r['usdp']],
            [5, 'n', $r['total']],
        ]);
        $rn++;
    }

    // Filas de resumen
    $summary = [
        ['Product cost', $fob],
        ['Payment fee',  0],
        ['Ship Cost',    0],
        ['Total',        $fob],
        ['Paid',         0],
        ['left',         $fob],
    ];
    foreach ($summary as [$lbl, $val]) {
        $sheetRows[] = buildRow($rn, [
            [4, 's', $ssIdx($lbl)],
            [5, 'n', $val],
        ]);
        $rn++;
    }

    // Armar sheet XML
    $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<sheetData>' . implode('', $sheetRows) . '</sheetData>'
        . '</worksheet>';

    // Armar sharedStrings XML
    $ssItems = '';
    foreach ($sharedStrings as $s) {
        $ssItems .= '<si><t>' . xmlesc($s) . '</t></si>';
    }
    $ssXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="' . count($sharedStrings) . '" uniqueCount="' . count($sharedStrings) . '">'
        . $ssItems . '</sst>';

    // Armar XLSX (ZIP)
    $tmpFile = tempnam(sys_get_temp_dir(), 'xlsx_');
    $zip = new ZipArchive();
    $zip->open($tmpFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    $zip->addFromString('[Content_Types].xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        . '<Default Extension="xml"  ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
        . '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>'
        . '</Types>');

    $zip->addFromString('_rels/.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        . '</Relationships>');

    $zip->addFromString('xl/workbook.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        . '<sheets><sheet name="Order" sheetId="1" r:id="rId1"/></sheets>'
        . '</workbook>');

    $zip->addFromString('xl/_rels/workbook.xml.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
        . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>'
        . '</Relationships>');

    $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
    $zip->addFromString('xl/sharedStrings.xml', $ssXml);
    $zip->close();

    $content = file_get_contents($tmpFile);
    unlink($tmpFile);
    return $content;
}

function buildRow(int $rn, array $cells): string {
    $xml = "<row r=\"$rn\">";
    foreach ($cells as [$ci, $type, $val]) {
        $col = colLetter($ci) . $rn;
        if ($type === 's') {
            $xml .= "<c r=\"$col\" t=\"s\"><v>$val</v></c>";
        } else {
            $xml .= "<c r=\"$col\"><v>$val</v></c>";
        }
    }
    return $xml . '</row>';
}

function xmlesc(string $s): string {
    return htmlspecialchars($s, ENT_XML1, 'UTF-8');
}

function colLetter(int $n): string {
    $s = ''; $n++;
    while ($n > 0) { $s = chr(65+(($n-1)%26)).$s; $n = intdiv($n-1,26); }
    return $s;
}
