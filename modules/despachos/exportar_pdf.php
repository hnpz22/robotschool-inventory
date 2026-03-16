<?php
/**
 * exportar_pdf.php &#8212; Genera el PDF del acta de entrega de un despacho
 * Estrategia: HTML puro con CSS @page para impresi&#243;n como PDF
 * El usuario puede usar Ctrl+P &#8594; "Guardar como PDF" o el bot&#243;n de descarga
 * que usa window.print() con beforeprint/afterprint para auto-cerrar
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();

$db  = Database::get();
$id  = (int)($_GET['id']  ?? 0);
$doc = $_GET['doc'] ?? 'acta'; // acta | remision | transportista
if (!$id) { header('Location: ' . APP_URL . '/modules/despachos/'); exit; }

$des = $db->query("
    SELECT d.*,
           col.nombre AS colegio_nombre, col.ciudad AS municipio,
           col.ciudad AS direccion, col.telefono AS col_telefono,
           col.email AS col_email,
           u.nombre AS creado_por_nombre
    FROM despachos d
    LEFT JOIN colegios col ON col.id = d.colegio_id
    LEFT JOIN usuarios u   ON u.id   = d.creado_por
    WHERE d.id = $id
")->fetch();
if (!$des) die('Despacho no encontrado');

// Kits del despacho
$kitsDespacho = $db->query("
    SELECT dk.cantidad AS kits_cantidad,
           k.id AS kit_id, k.codigo AS kit_codigo, k.nombre AS kit_nombre,
           k.descripcion AS kit_descripcion, k.costo_cop,
           (dk.cantidad * k.costo_cop) AS subtotal
    FROM despacho_kits dk
    JOIN kits k ON k.id = dk.kit_id
    WHERE dk.despacho_id = $id
    ORDER BY k.nombre
")->fetchAll();

// Contenido de cada kit
$contenidoKits = [];
$resumenElementos = [];
foreach ($kitsDespacho as $dk) {
    $kitId = $dk['kit_id'];
    $multip = (int)$dk['kits_cantidad'];

    $elems = $db->query("
        SELECT ke.cantidad, e.codigo, e.nombre, e.unidad AS unidad_medida,
               c.nombre AS categoria,
               (ke.cantidad * $multip) AS total_unidades
        FROM kit_elementos ke
        JOIN elementos e ON e.id = ke.elemento_id
        JOIN categorias c ON c.id = e.categoria_id
        WHERE ke.kit_id = $kitId
        ORDER BY c.nombre, e.nombre
    ")->fetchAll();

    $protos = $db->query("
        SELECT kp.cantidad, kp.notas, p.codigo, p.nombre,
               p.tipo_fabricacion, p.material_principal,
               (kp.cantidad * $multip) AS total_unidades
        FROM kit_prototipos kp
        JOIN prototipos p ON p.id = kp.prototipo_id
        WHERE kp.kit_id = $kitId
        ORDER BY p.nombre
    ")->fetchAll();

    $contenidoKits[$kitId] = ['kit'=>$dk,'elementos'=>$elems,'protos'=>$protos];

    foreach ($elems as $e) {
        $key = $e['codigo'];
        if (!isset($resumenElementos[$key])) { $resumenElementos[$key] = $e; $resumenElementos[$key]['total_unidades']=0; }
        $resumenElementos[$key]['total_unidades'] += (int)$e['total_unidades'];
    }
    foreach ($protos as $p) {
        $key = 'PRO-'.$p['codigo'];
        if (!isset($resumenElementos[$key])) {
            $resumenElementos[$key] = $p;
            $resumenElementos[$key]['categoria'] = 'Prototipo fabricado';
            $resumenElementos[$key]['unidad'] = 'unidad';
            $resumenElementos[$key]['total_unidades'] = 0;
        }
        $resumenElementos[$key]['total_unidades'] += (int)$p['total_unidades'];
    }
}

$totalKits  = array_sum(array_column($kitsDespacho,'kits_cantidad'));
$totalCosto = array_sum(array_column($kitsDespacho,'subtotal'));
$flete      = (float)($des['valor_flete_cop']??0);
$totalItems = count($resumenElementos);
$totalUnids = array_sum(array_column($resumenElementos,'total_unidades'));

$docTitles = [
    'acta'          => 'Acta de Entrega de Materiales',
    'remision'      => 'Remision de Despacho',
    'transportista' => 'Guia de Transporte',
];
$docTitle = $docTitles[$doc] ?? 'Documento';
$filename = $des['codigo'] . '-' . $doc . '.pdf';
$logoUrl  = APP_URL . '/assets/img/logo_email.png';
$logoPath = APP_ROOT . '/assets/img/logo_email.png';
$logoB64  = file_exists($logoPath) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath)) : '';

// Colores ROBOTSchool
$azul   = '#1e3a5f';
$verde  = '#16a34a';
$celeste= '#3a72e8';
$gris   = '#64748b';
$grisClaro = '#f1f5f9';
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title><?= htmlspecialchars($docTitle) ?> &#8212; <?= htmlspecialchars($des['codigo']) ?></title>
<style>
* { margin:0; padding:0; box-sizing:border-box; }
body {
  font-family: Arial, Helvetica, sans-serif;
  font-size: 10pt;
  color: #1a1a1a;
  background: white;
}

/* &#9472;&#9472; Layout A4 &#9472;&#9472; */
@page {
  size: A4 portrait;
  margin: 15mm 15mm 20mm 15mm;
  @bottom-center {
    content: "ROBOTSchool Colombia  &#183;  " string(doccode) "  &#183;  P&#225;gina " counter(page) " de " counter(pages);
    font-size: 8pt;
    color: #888;
  }
}

.page-container {
  width: 100%;
  max-width: 180mm;
  margin: 0 auto;
}

/* &#9472;&#9472; Encabezado &#9472;&#9472; */
.header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  padding-bottom: 10pt;
  border-bottom: 2pt solid <?= $azul ?>;
  margin-bottom: 12pt;
}
.header-logo img { max-height: 45pt; max-width: 130pt; }
.header-logo .logo-fallback { font-size:14pt; font-weight:700; color:<?= $celeste ?>; }
.header-info { text-align: right; }
.header-info .doc-code { font-size:14pt; font-weight:700; color:<?= $celeste ?>; font-family:monospace; }
.header-info .company-small { font-size:8pt; color:<?= $gris ?>; line-height:1.4; }

/* &#9472;&#9472; T&#237;tulo documento &#9472;&#9472; */
.doc-title {
  text-align: center;
  font-size: 13pt;
  font-weight: 700;
  letter-spacing: 1pt;
  color: <?= $azul ?>;
  text-transform: uppercase;
  border-bottom: 2pt solid <?= $verde ?>;
  padding-bottom: 4pt;
  margin-bottom: 12pt;
  display: inline-block;
  width: 100%;
}

/* &#9472;&#9472; Bloques info &#9472;&#9472; */
.info-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8pt;
  margin-bottom: 12pt;
}
.info-box {
  border: 1pt solid #d1d5db;
  border-radius: 4pt;
  padding: 7pt 9pt;
}
.info-box .label {
  font-size: 7pt;
  font-weight: 700;
  color: <?= $gris ?>;
  letter-spacing: .5pt;
  text-transform: uppercase;
  margin-bottom: 4pt;
}
.info-box .value { font-size: 10pt; font-weight: 700; }
.info-box .sub   { font-size: 8pt; color: <?= $gris ?>; line-height:1.5; }

/* &#9472;&#9472; Tarjetas resumen &#9472;&#9472; */
.stats-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 6pt;
  margin-bottom: 12pt;
}
.stat-box {
  text-align: center;
  border: 1pt solid #d1d5db;
  border-radius: 4pt;
  padding: 6pt;
}
.stat-box .num { font-size: 16pt; font-weight: 700; color: <?= $celeste ?>; }
.stat-box .lbl { font-size: 7pt; color: <?= $gris ?>; }

/* &#9472;&#9472; Tablas &#9472;&#9472; */
table {
  width: 100%;
  border-collapse: collapse;
  margin-bottom: 10pt;
  font-size: 9pt;
  page-break-inside: auto;
}
table thead tr {
  background-color: <?= $azul ?> !important;
  color: white !important;
  -webkit-print-color-adjust: exact;
  print-color-adjust: exact;
}
table thead th {
  padding: 5pt 4pt;
  text-align: left;
  font-weight: 700;
  font-size: 8pt;
}
table tbody tr { border-bottom: .5pt solid #e5e7eb; }
table tbody tr:nth-child(even) {
  background: <?= $grisClaro ?>;
  -webkit-print-color-adjust: exact;
  print-color-adjust: exact;
}
table tbody td { padding: 4pt; vertical-align: top; }
table tfoot tr {
  background: #e8eef7 !important;
  -webkit-print-color-adjust: exact;
  print-color-adjust: exact;
  font-weight: 700;
}
table tfoot td { padding: 5pt 4pt; }

/* &#9472;&#9472; Kit header dentro del acta &#9472;&#9472; */
.kit-header {
  background: <?= $azul ?> !important;
  color: white !important;
  -webkit-print-color-adjust: exact;
  print-color-adjust: exact;
  padding: 5pt 8pt;
  border-radius: 3pt 3pt 0 0;
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-top: 10pt;
  font-size: 9pt;
}
.kit-block { page-break-inside: avoid; }
.kit-badge {
  background: <?= $celeste ?>;
  border-radius: 10pt;
  padding: 1pt 7pt;
  font-size: 8pt;
}
.badge-comp {
  background: #dbeafe;
  color: #1e40af;
  border: .5pt solid #93c5fd;
  border-radius: 3pt;
  padding: 1pt 4pt;
  font-size: 7pt;
}
.badge-proto {
  background: #dcfce7;
  color: #166534;
  border: .5pt solid #86efac;
  border-radius: 3pt;
  padding: 1pt 4pt;
  font-size: 7pt;
}
.chk { font-size: 12pt; }

/* &#9472;&#9472; Declaraci&#243;n &#9472;&#9472; */
.declaracion {
  background: #f8fafc;
  border: 1pt solid #e2e8f0;
  border-radius: 4pt;
  padding: 8pt 10pt;
  font-size: 9pt;
  line-height: 1.7;
  margin: 10pt 0;
}

/* &#9472;&#9472; Notas &#9472;&#9472; */
.notas-box {
  background: #fefce8;
  border: 1pt solid #fde047;
  border-radius: 4pt;
  padding: 6pt 8pt;
  font-size: 9pt;
  margin-bottom: 10pt;
}

/* &#9472;&#9472; Firmas &#9472;&#9472; */
.firmas {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20pt;
  margin-top: 30pt;
  padding-top: 10pt;
}
.firma-box { text-align: center; }
.firma-line {
  border-top: 1pt solid #334155;
  padding-top: 5pt;
  margin-top: 35pt;
}
.firma-box .nombre { font-weight: 700; font-size: 9pt; }
.firma-box .cargo  { font-size: 8pt; color: <?= $gris ?>; }

/* &#9472;&#9472; Barra de datos &#9472;&#9472; */
.data-strip {
  background: <?= $azul ?> !important;
  color: white !important;
  -webkit-print-color-adjust: exact;
  print-color-adjust: exact;
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  text-align: center;
  padding: 8pt;
  border-radius: 4pt;
  margin-bottom: 12pt;
  gap: 4pt;
}
.data-strip .ds-lbl { font-size: 7pt; opacity:.75; }
.data-strip .ds-val { font-size: 10pt; font-weight:700; font-family:monospace; }

/* &#9472;&#9472; Botones (no imprimen) &#9472;&#9472; */
.no-print {
  position: fixed;
  top: 12px;
  right: 12px;
  display: flex;
  gap: 8px;
  z-index: 999;
  background: white;
  padding: 8px 12px;
  border-radius: 8px;
  box-shadow: 0 2px 12px rgba(0,0,0,.15);
}
.btn-pdf {
  background: #1e3a5f;
  color: white;
  border: none;
  padding: 8px 16px;
  border-radius: 6px;
  cursor: pointer;
  font-size: 13px;
  font-weight: 700;
  display: flex;
  align-items: center;
  gap: 6px;
}
.btn-back {
  background: #f1f5f9;
  color: #334155;
  border: 1px solid #e2e8f0;
  padding: 8px 14px;
  border-radius: 6px;
  cursor: pointer;
  font-size: 13px;
  text-decoration: none;
  display: flex;
  align-items: center;
  gap: 4px;
}
.btn-doc {
  background: white;
  color: #334155;
  border: 1px solid #e2e8f0;
  padding: 6px 12px;
  border-radius: 6px;
  cursor: pointer;
  font-size: 12px;
}
.btn-doc.active {
  background: <?= $celeste ?>;
  color: white;
  border-color: <?= $celeste ?>;
}

@media print {
  .no-print { display: none !important; }
  body { background: white; }
}
</style>
</head>
<body>

<!-- Controles (no imprimen) -->
<div class="no-print">
  <a class="btn-back" href="<?= APP_URL ?>/modules/despachos/ver.php?id=<?= $id ?>">
    &#8592; Volver
  </a>
  <button class="btn-doc <?= $doc==='remision'?'active':'' ?>"
          onclick="location.href='?id=<?= $id ?>&doc=remision'">&#128196; Remisi&#243;n</button>
  <button class="btn-doc <?= $doc==='acta'?'active':'' ?>"
          onclick="location.href='?id=<?= $id ?>&doc=acta'">&#9989; Acta</button>
  <button class="btn-doc <?= $doc==='transportista'?'active':'' ?>"
          onclick="location.href='?id=<?= $id ?>&doc=transportista'">&#128666; Transportista</button>
  <button class="btn-pdf" onclick="imprimirPDF()">
    &#128195; Descargar PDF
  </button>
</div>

<div class="page-container">

  <!-- ENCABEZADO -->
  <div class="header">
    <div class="header-logo">
      <?php if ($logoB64): ?>
        <img src="<?= $logoB64 ?>" alt="ROBOTSchool">
      <?php else: ?>
        <div class="logo-fallback">ROBOTSchool</div>
      <?php endif; ?>
      <div class="company-small" style="margin-top:4pt;">
        Calle 75 #20b-62, Bogot&#225; D.C.<br>
        Tel: 318 654 1859 | robotschoolcol@gmail.com<br>
        www.robotschool.com.co
      </div>
    </div>
    <div class="header-info">
      <div class="doc-code"><?= htmlspecialchars($des['codigo']) ?></div>
      <div class="company-small">
        Fecha: <strong><?= date('d/m/Y', strtotime($des['fecha'])) ?></strong><br>
        Generado: <?= date('d/m/Y H:i') ?><br>
        <?= ucfirst($des['estado']) ?>
      </div>
    </div>
  </div>

  <!-- T&#205;TULO -->
  <div class="doc-title"><?= $docTitle ?></div>

<?php if ($doc === 'acta'): ?>
  <!-- &#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;
       ACTA DE ENTREGA DE MATERIALES
       &#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552; -->

  <!-- Estad&#237;sticas -->
  <div class="stats-row">
    <div class="stat-box">
      <div class="num"><?= $totalKits ?></div>
      <div class="lbl">Kits entregados</div>
    </div>
    <div class="stat-box">
      <div class="num"><?= $totalItems ?></div>
      <div class="lbl">Referencias</div>
    </div>
    <div class="stat-box">
      <div class="num" style="color:#16a34a;"><?= number_format($totalUnids) ?></div>
      <div class="lbl">Unidades totales</div>
    </div>
    <div class="stat-box">
      <div class="num" style="font-size:10pt;padding-top:4pt;"><?= date('d/m/Y', strtotime($des['fecha'])) ?></div>
      <div class="lbl">Fecha despacho</div>
    </div>
  </div>

  <!-- Info destinatario -->
  <div class="info-grid">
    <div class="info-box">
      <div class="label">Destinatario</div>
      <div class="value"><?= htmlspecialchars($des['colegio_nombre'] ?? 'Sin colegio') ?></div>
      <div class="sub">
        <?= htmlspecialchars($des['municipio'] ?? '') ?><br>
        <?php if ($des['col_telefono']): ?>Tel: <?= htmlspecialchars($des['col_telefono']) ?><?php endif; ?>
      </div>
    </div>
    <div class="info-box">
      <div class="label">Recibe</div>
      <div class="value"><?= htmlspecialchars($des['nombre_recibe'] ?: '&#8212;') ?></div>
      <div class="sub">
        <?= htmlspecialchars($des['cargo_recibe'] ?? '') ?><br>
        <?php if ($des['transportadora']): ?>V&#237;a: <?= htmlspecialchars($des['transportadora']) ?><?php endif; ?>
        <?php if ($des['guia_transporte']): ?> | Gu&#237;a: <strong><?= htmlspecialchars($des['guia_transporte']) ?></strong><?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Desglose por kit -->
  <?php foreach ($contenidoKits as $kitId => $data):
    $dk    = $data['kit'];
    $elems = $data['elementos'];
    $protos= $data['protos'];
  ?>
  <div class="kit-block">
    <div class="kit-header">
      <div>
        <span style="font-family:monospace;font-weight:700;"><?= htmlspecialchars($dk['kit_codigo']) ?></span>
        &nbsp;&#8212;&nbsp;
        <?= htmlspecialchars($dk['kit_nombre']) ?>
      </div>
      <div class="kit-badge"><?= $dk['kits_cantidad'] ?> kit<?= $dk['kits_cantidad']>1?'s':'' ?></div>
    </div>
    <table>
      <thead>
        <tr>
          <th style="width:14%">C&#243;digo</th>
          <th>Descripci&#243;n</th>
          <th style="width:12%">Tipo</th>
          <th style="width:9%;text-align:center">x Kit</th>
          <th style="width:13%;text-align:center">x <?= $dk['kits_cantidad'] ?> kits</th>
          <th style="width:8%;text-align:center">&#10003;</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($elems as $e): ?>
      <tr>
        <td style="font-family:monospace;font-size:8pt;color:#1e40af;"><?= htmlspecialchars($e['codigo']) ?></td>
        <td>
          <strong><?= htmlspecialchars($e['nombre']) ?></strong><br>
          <span style="font-size:8pt;color:<?= $gris ?>"><?= htmlspecialchars($e['categoria']) ?></span>
        </td>
        <td><span class="badge-comp">Componente</span></td>
        <td style="text-align:center;font-weight:700;"><?= $e['cantidad'] ?></td>
        <td style="text-align:center;font-weight:700;color:#1e40af;"><?= $e['total_unidades'] ?></td>
        <td style="text-align:center;" class="chk">&#9633;</td>
      </tr>
      <?php endforeach; ?>
      <?php foreach ($protos as $p): ?>
      <tr style="background:#f0fdf4;">
        <td style="font-family:monospace;font-size:8pt;color:#166534;"><?= htmlspecialchars($p['codigo']) ?></td>
        <td>
          <strong><?= htmlspecialchars($p['nombre']) ?></strong><br>
          <span style="font-size:8pt;color:<?= $gris ?>">
            <?= htmlspecialchars($p['tipo_fabricacion']??'') ?>
            <?= $p['material_principal'] ? ' &#183; '.htmlspecialchars($p['material_principal']) : '' ?>
          </span>
        </td>
        <td><span class="badge-proto">Prototipo</span></td>
        <td style="text-align:center;font-weight:700;"><?= $p['cantidad'] ?></td>
        <td style="text-align:center;font-weight:700;color:#166534;"><?= $p['total_unidades'] ?></td>
        <td style="text-align:center;" class="chk">&#9633;</td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($elems) && empty($protos)): ?>
      <tr><td colspan="6" style="text-align:center;color:<?= $gris ?>;padding:8pt;">Kit sin componentes registrados</td></tr>
      <?php endif; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="4" style="text-align:right;">Total <?= htmlspecialchars($dk['kit_codigo']) ?>:</td>
          <td style="text-align:center;color:<?= $celeste ?>;">
            <?= array_sum(array_column($elems,'total_unidades')) + array_sum(array_column($protos,'total_unidades')) ?> uds
          </td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>
  <?php endforeach; ?>

  <!-- Resumen consolidado (si hay varios kits) -->
  <?php if (count($kitsDespacho) > 1): ?>
  <div style="page-break-before:auto;margin-top:14pt;">
    <div style="background:<?= $celeste ?>;color:white;padding:6pt 10pt;border-radius:3pt 3pt 0 0;font-weight:700;font-size:9pt;letter-spacing:.5pt;-webkit-print-color-adjust:exact;print-color-adjust:exact;">
      RESUMEN CONSOLIDADO DE MATERIALES
    </div>
    <table style="margin-top:0;">
      <thead>
        <tr>
          <th style="width:14%">C&#243;digo</th>
          <th>Material / Componente</th>
          <th style="width:18%">Categor&#237;a</th>
          <th style="width:13%;text-align:center">Total Uds</th>
          <th style="width:8%;text-align:center">&#10003;</th>
        </tr>
      </thead>
      <tbody>
      <?php
      uasort($resumenElementos, fn($a,$b) => strcmp($a['categoria']??'',$b['categoria']??'') ?: strcmp($a['nombre'],$b['nombre']));
      foreach ($resumenElementos as $e): ?>
      <tr>
        <td style="font-family:monospace;font-size:8pt;color:#1e40af;"><?= htmlspecialchars($e['codigo']) ?></td>
        <td><strong><?= htmlspecialchars($e['nombre']) ?></strong></td>
        <td style="font-size:8pt;color:<?= $gris ?>"><?= htmlspecialchars($e['categoria']) ?></td>
        <td style="text-align:center;font-weight:700;color:<?= $celeste ?>;"><?= $e['total_unidades'] ?></td>
        <td style="text-align:center;" class="chk">&#9633;</td>
      </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="3" style="text-align:right;">TOTAL UNIDADES:</td>
          <td style="text-align:center;color:<?= $celeste ?>;"><?= number_format($totalUnids) ?></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>
  <?php endif; ?>

  <!-- Declaraci&#243;n -->
  <?php if ($des['notas']): ?>
  <div class="notas-box"><strong>&#9888; Observaciones:</strong> <?= htmlspecialchars($des['notas']) ?></div>
  <?php endif; ?>

  <div class="declaracion">
    El colegio <strong><?= htmlspecialchars($des['colegio_nombre']??'') ?></strong> certifica haber recibido
    a satisfacci&#243;n los materiales descritos en el presente documento, correspondientes al despacho
    <strong><?= htmlspecialchars($des['codigo']) ?></strong> con fecha <strong><?= date('d/m/Y', strtotime($des['fecha'])) ?></strong>,
    por parte de <strong>ROBOTSchool Colombia</strong>. Los materiales fueron revisados y se encontraron
    en buen estado y en la cantidad indicada en el presente inventario.
  </div>

<?php elseif ($doc === 'remision'): ?>
  <!-- &#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;
       REMISI&#211;N
       &#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552; -->
  <div class="info-grid">
    <div class="info-box">
      <div class="label">Destinatario</div>
      <div class="value"><?= htmlspecialchars($des['colegio_nombre']??'Sin colegio') ?></div>
      <div class="sub"><?= htmlspecialchars($des['municipio']??'') ?><br>
        <?php if ($des['col_telefono']): ?>Tel: <?= htmlspecialchars($des['col_telefono']) ?><?php endif; ?>
      </div>
    </div>
    <div class="info-box">
      <div class="label">Transporte</div>
      <div class="sub">
        Empresa: <strong><?= htmlspecialchars($des['transportadora']??'&#8212;') ?></strong><br>
        Gu&#237;a: <strong style="font-family:monospace;"><?= htmlspecialchars($des['guia_transporte']??'&#8212;') ?></strong><br>
        Flete: <strong><?= $flete>0?cop($flete):'&#8212;' ?></strong><br>
        Recibe: <strong><?= htmlspecialchars($des['nombre_recibe']??'&#8212;') ?></strong>
      </div>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th style="width:15%">C&#243;digo Kit</th>
        <th>Nombre del Kit</th>
        <th style="width:10%;text-align:center">Cant.</th>
        <th style="width:18%;text-align:right">Costo Unit.</th>
        <th style="width:18%;text-align:right">Subtotal</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($kitsDespacho as $dk): ?>
    <tr>
      <td style="font-family:monospace;font-weight:700;"><?= htmlspecialchars($dk['kit_codigo']) ?></td>
      <td>
        <strong><?= htmlspecialchars($dk['kit_nombre']) ?></strong>
        <?php if ($dk['kit_descripcion']): ?>
        <br><span style="font-size:8pt;color:<?= $gris ?>"><?= htmlspecialchars(mb_substr($dk['kit_descripcion'],0,80)) ?></span>
        <?php endif; ?>
      </td>
      <td style="text-align:center;font-weight:700;"><?= $dk['kits_cantidad'] ?></td>
      <td style="text-align:right;"><?= cop($dk['costo_cop']) ?></td>
      <td style="text-align:right;font-weight:700;"><?= cop($dk['subtotal']) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="2" style="text-align:right;">TOTAL (<?= $totalKits ?> kits):</td>
        <td style="text-align:center;"><?= $totalKits ?></td>
        <td></td>
        <td style="text-align:right;color:<?= $verde ?>;"><?= cop($totalCosto) ?></td>
      </tr>
      <?php if ($flete>0): ?>
      <tr><td colspan="4" style="text-align:right;font-weight:400;">Flete:</td><td style="text-align:right;"><?= cop($flete) ?></td></tr>
      <tr><td colspan="4" style="text-align:right;">TOTAL GENERAL:</td><td style="text-align:right;color:<?= $celeste ?>;"><?= cop($totalCosto+$flete) ?></td></tr>
      <?php endif; ?>
    </tfoot>
  </table>

  <?php if ($des['notas']): ?>
  <div class="notas-box"><strong>Observaciones:</strong> <?= htmlspecialchars($des['notas']) ?></div>
  <?php endif; ?>

<?php else: /* transportista */ ?>
  <!-- &#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;
       GU&#205;A TRANSPORTISTA
       &#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552; -->
  <div class="info-grid">
    <div class="info-box">
      <div class="label">Remitente</div>
      <div class="value">ROBOTSchool Colombia</div>
      <div class="sub">Calle 75 #20b-62, Bogot&#225;<br>Tel: 318 654 1859</div>
    </div>
    <div class="info-box">
      <div class="label">Destinatario</div>
      <div class="value"><?= htmlspecialchars($des['colegio_nombre']??'') ?></div>
      <div class="sub">
        <?= htmlspecialchars($des['municipio']??'') ?><br>
        <?php if ($des['col_telefono']): ?>Tel: <?= htmlspecialchars($des['col_telefono']) ?><br><?php endif; ?>
        Recibe: <strong><?= htmlspecialchars($des['nombre_recibe']??'Rector/Coordinador') ?></strong>
      </div>
    </div>
  </div>

  <div class="data-strip">
    <div><div class="ds-lbl">N&#250;m. Despacho</div><div class="ds-val"><?= htmlspecialchars($des['codigo']) ?></div></div>
    <div><div class="ds-lbl">Fecha</div><div class="ds-val"><?= date('d/m/Y', strtotime($des['fecha'])) ?></div></div>
    <div><div class="ds-lbl">Gu&#237;a</div><div class="ds-val"><?= htmlspecialchars($des['guia_transporte']??'&#8212;') ?></div></div>
    <div><div class="ds-lbl">Transportadora</div><div class="ds-val"><?= htmlspecialchars($des['transportadora']??'&#8212;') ?></div></div>
  </div>

  <table>
    <thead>
      <tr>
        <th>#</th>
        <th>Descripci&#243;n del paquete</th>
        <th style="text-align:center">Kits</th>
        <th style="text-align:center">Componentes aprox.</th>
        <th style="text-align:center">Fr&#225;gil</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($kitsDespacho as $i => $dk):
      $numComp = 0;
      if (isset($contenidoKits[$dk['kit_id']])) {
          foreach ($contenidoKits[$dk['kit_id']]['elementos'] as $e) $numComp += $e['total_unidades'];
          foreach ($contenidoKits[$dk['kit_id']]['protos'] as $p) $numComp += $p['total_unidades'];
      }
    ?>
    <tr>
      <td style="text-align:center;"><?= $i+1 ?></td>
      <td>
        <strong><?= htmlspecialchars($dk['kit_nombre']) ?></strong><br>
        <span style="font-family:monospace;font-size:8pt;color:<?= $gris ?>"><?= htmlspecialchars($dk['kit_codigo']) ?></span>
      </td>
      <td style="text-align:center;font-weight:700;"><?= $dk['kits_cantidad'] ?></td>
      <td style="text-align:center;"><?= number_format($numComp) ?></td>
      <td style="text-align:center;font-size:11pt;">&#9633; S&#237; &nbsp; &#9633; No</td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <td colspan="2" style="text-align:right;">TOTAL:</td>
        <td style="text-align:center;color:<?= $celeste ?>;"><?= $totalKits ?> kits</td>
        <td style="text-align:center;color:<?= $celeste ?>;"><?= number_format($totalUnids) ?> uds</td>
        <td></td>
      </tr>
    </tfoot>
  </table>

  <?php if ($des['notas']): ?>
  <div class="notas-box"><strong>&#9888; Instrucciones de entrega:</strong> <?= htmlspecialchars($des['notas']) ?></div>
  <?php endif; ?>

  <div class="firmas">
    <div class="firma-box">
      <div class="firma-line">
        <div class="nombre">Entregado a transportadora</div>
        <div class="cargo">Firma y sello ROBOTSchool</div>
      </div>
    </div>
    <div class="firma-box">
      <div class="firma-line">
        <div class="nombre">Recibido por transportadora</div>
        <div class="cargo">Firma, sello y fecha</div>
      </div>
    </div>
    <div class="firma-box">
      <div class="firma-line">
        <div class="nombre">Entregado en destino</div>
        <div class="cargo">Firma y sello colegio</div>
      </div>
    </div>
  </div>
<?php endif; ?>

  <!-- FIRMAS (acta y remisi&#243;n) -->
  <?php if ($doc !== 'transportista'): ?>
  <div class="firmas">
    <div class="firma-box">
      <div class="firma-line">
        <div class="nombre">Elaborado por ROBOTSchool</div>
        <div class="cargo"><?= htmlspecialchars($des['creado_por_nombre']??'ROBOTSchool') ?></div>
      </div>
    </div>
    <div class="firma-box">
      <div class="firma-line">
        <div class="nombre">Despachado por</div>
        <div class="cargo">&nbsp;</div>
      </div>
    </div>
    <div class="firma-box">
      <div class="firma-line">
        <div class="nombre">Recibido a satisfacci&#243;n</div>
        <div class="cargo"><?= htmlspecialchars($des['nombre_recibe']??'') ?></div>
        <?php if ($des['cargo_recibe']): ?>
        <div class="cargo"><?= htmlspecialchars($des['cargo_recibe']) ?></div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <div style="text-align:center;font-size:7pt;color:<?= $gris ?>;margin-top:14pt;padding-top:8pt;border-top:.5pt solid #e2e8f0;" class="no-print">
    Documento generado el <?= date('d/m/Y H:i') ?> &mdash; ROBOTSchool Inventory System
  </div>

</div><!-- /page-container -->

<script>
function imprimirPDF() {
  // Configurar el t&#237;tulo del documento para que el nombre del PDF sea correcto
  var tituloOriginal = document.title;
  document.title = '<?= addslashes($des['codigo']) ?>-<?= addslashes($doc) ?>';

  // Peque&#241;o delay para que el t&#237;tulo se actualice
  setTimeout(function() {
    window.print();
    // Restaurar t&#237;tulo despu&#233;s de imprimir
    setTimeout(function() {
      document.title = tituloOriginal;
    }, 1000);
  }, 100);
}

// Auto-imprimir si viene con ?print=1
<?php if (!empty($_GET['print'])): ?>
window.addEventListener('load', function() { setTimeout(imprimirPDF, 500); });
<?php endif; ?>
</script>
</body>
</html>
