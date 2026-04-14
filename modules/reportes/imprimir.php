<?php
/**
 * ROBOTSchool Inventory &mdash; Exportar Reporte
 * Genera una página imprimible en tamaño carta con logo
 * Para PDF: usar Ctrl+P → "Guardar como PDF" en el navegador
 * Para Excel: botón "Exportar Excel" usa SheetJS
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
Auth::check();

$db = Database::get();

$tipo       = $_GET['tipo']      ?? 'inventario';
$fechaDesde = $_GET['desde']     ?? date('Y-m-01');
$fechaHasta = $_GET['hasta']     ?? date('Y-m-d');
$pedidoId   = (int)($_GET['pedido_id'] ?? 0);
$catId      = (int)($_GET['cat_id']    ?? 0);
$provId     = (int)($_GET['prov_id']   ?? 0);
$salida     = $_GET['salida']    ?? 'pdf'; // pdf | excel | csv
$colegioId  = (int)($_GET['colegio_id']  ?? 0);
$kitNombre  = trim($_GET['kit_nombre']   ?? '');
$fuente     = $_GET['fuente']            ?? 'all';
$estadoConv = $_GET['estado_conv']       ?? 'aprobado';

if (in_array($tipo, ['ventas_colegios','financiero']) && !isset($_GET['desde'])) {
    $fechaDesde = date('Y-01-01');
    $fechaHasta = date('Y-m-d');
}

// ── Obtener datos y columnas ──
$datos = [];
$columnas = [];
$titulo_reporte = '';
$subtitulo = '';

switch ($tipo) {
    case 'inventario':
        $titulo_reporte = 'Inventario General de Stock';
        $subtitulo = 'Estado actual del inventario con semáforo de stock';
        $columnas = ['Código','Elemento','Categoría','Stock','Mín','Máx','Estado','Ubicación','Costo COP','Precio COP'];
        $where = "WHERE e.activo=1";
        if ($catId) $where .= " AND e.categoria_id=$catId";
        $datos = $db->query("
            SELECT e.codigo, e.nombre, c.nombre AS categoria, c.color,
                   e.stock_actual, e.stock_minimo, e.stock_maximo,
                   e.ubicacion, e.costo_real_cop, e.precio_venta_cop,
                   CASE WHEN e.stock_actual<=0 THEN 'Sin stock' WHEN e.stock_actual<=e.stock_minimo THEN 'Stock bajo' WHEN e.stock_actual>=e.stock_maximo THEN 'Lleno' ELSE 'OK' END AS estado,
                   CASE WHEN e.stock_actual<=0 THEN '#dc2626' WHEN e.stock_actual<=e.stock_minimo THEN '#d97706' WHEN e.stock_actual>=e.stock_maximo THEN '#2563eb' ELSE '#16a34a' END AS estado_color
            FROM elementos e JOIN categorias c ON c.id=e.categoria_id
            $where ORDER BY c.nombre, e.nombre
        ")->fetchAll();
        break;

    case 'importacion':
        $titulo_reporte = 'Reporte de Importación';
        $where = "WHERE 1=1";
        if ($pedidoId) {
            $ped = $db->query("SELECT * FROM pedidos_importacion WHERE id=$pedidoId")->fetch();
            $titulo_reporte = 'Importación: ' . ($ped['codigo_pedido'] ?? '');
            $subtitulo = 'Fecha: ' . ($ped['fecha_pedido'] ?? '') . ' · TRM: $' . number_format($ped['tasa_cambio_usd_cop'] ?? 0,0,',','.') . ' · Estado: ' . ucfirst($ped['estado'] ?? '');
            $where .= " AND pi.pedido_id=$pedidoId";
        }
        $columnas = ['Código','Elemento','Categoría','Cant.','P.Unit USD','Subtotal USD','Peso g','Flete COP','Arancel COP','IVA COP','Costo Unit COP'];
        $datos = $db->query("
            SELECT e.codigo, e.nombre, c.nombre AS categoria, c.color,
                   pi.cantidad, pi.precio_unit_usd, pi.subtotal_usd,
                   pi.peso_unit_gramos, pi.flete_asignado_cop,
                   pi.arancel_asignado_cop, pi.iva_asignado_cop, pi.costo_unit_final_cop,
                   p.codigo_pedido, p.fecha_pedido
            FROM pedido_items pi
            JOIN elementos e ON e.id=pi.elemento_id
            JOIN categorias c ON c.id=e.categoria_id
            JOIN pedidos_importacion p ON p.id=pi.pedido_id
            $where ORDER BY c.nombre, e.nombre
        ")->fetchAll();
        break;

    case 'fechas':
        $titulo_reporte = 'Movimientos de Inventario';
        $subtitulo = 'Del ' . date('d/m/Y', strtotime($fechaDesde)) . ' al ' . date('d/m/Y', strtotime($fechaHasta));
        $columnas = ['Fecha','Código','Elemento','Categoría','Tipo','Cantidad','Antes','Después','Referencia','Usuario'];
        $where = "WHERE DATE(m.created_at) BETWEEN '$fechaDesde' AND '$fechaHasta'";
        if ($catId) $where .= " AND c.id=$catId";
        $datos = $db->query("
            SELECT DATE_FORMAT(m.created_at,'%d/%m/%Y %H:%i') AS fecha,
                   e.codigo, e.nombre, c.nombre AS categoria, c.color,
                   m.tipo, m.cantidad, m.stock_antes, m.stock_despues,
                   m.referencia, u.nombre AS usuario
            FROM movimientos m
            JOIN elementos e ON e.id=m.elemento_id
            JOIN categorias c ON c.id=e.categoria_id
            LEFT JOIN usuarios u ON u.id=m.usuario_id
            $where ORDER BY m.created_at DESC
        ")->fetchAll();
        break;

    case 'lotes':
        $titulo_reporte = 'Inventario por Categoría';
        $subtitulo = 'Agrupado por categoría con valorización';
        $columnas = ['Código','Elemento','Stock','Mín','Estado','Costo Unit COP','Valor Total COP','Proveedor'];
        $where = "WHERE e.activo=1";
        if ($catId) $where .= " AND e.categoria_id=$catId";
        if ($provId) $where .= " AND e.proveedor_id=$provId";
        $datos = $db->query("
            SELECT e.codigo, e.nombre, c.nombre AS categoria, c.color, c.prefijo,
                   e.stock_actual, e.stock_minimo, e.costo_real_cop,
                   (e.stock_actual * e.costo_real_cop) AS valor_total,
                   pv.nombre AS proveedor,
                   CASE WHEN e.stock_actual<=0 THEN 'Sin stock' WHEN e.stock_actual<=e.stock_minimo THEN 'Stock bajo' ELSE 'OK' END AS estado,
                   CASE WHEN e.stock_actual<=0 THEN '#dc2626' WHEN e.stock_actual<=e.stock_minimo THEN '#d97706' ELSE '#16a34a' END AS estado_color
            FROM elementos e
            JOIN categorias c ON c.id=e.categoria_id
            LEFT JOIN proveedores pv ON pv.id=e.proveedor_id
            $where ORDER BY c.nombre, e.nombre
        ")->fetchAll();
        break;

    case 'valorizacion':
        $titulo_reporte = 'Valorización del Inventario';
        $subtitulo = 'Resumen financiero por categoría';
        $columnas = ['Categoría','Elementos','Stock Total','Valor Costo COP','Valor Venta COP','Margen COP','%'];
        $datos = $db->query("
            SELECT c.nombre AS categoria, c.color,
                   COUNT(e.id) AS num_elementos,
                   SUM(e.stock_actual) AS stock_total,
                   SUM(e.stock_actual * e.costo_real_cop)   AS valor_costo,
                   SUM(e.stock_actual * e.precio_venta_cop) AS valor_venta,
                   SUM(e.stock_actual * (e.precio_venta_cop - e.costo_real_cop)) AS margen
            FROM elementos e JOIN categorias c ON c.id=e.categoria_id
            WHERE e.activo=1 GROUP BY c.id ORDER BY valor_costo DESC
        ")->fetchAll();
        $total_venta = array_sum(array_column($datos,'valor_venta')) ?: 1;
        break;

    case 'ventas_colegios':
        $titulo_reporte = 'Ventas de Kits por Colegio';
        $subtitulo = 'Del ' . date('d/m/Y', strtotime($fechaDesde)) . ' al ' . date('d/m/Y', strtotime($fechaHasta));
        $columnas = ['Colegio','Ciudad','Tipo','Kit','Curso','Estudiantes','Val. Kit','Val. Línea','Convenio','Fecha'];
        $conds = [];
        if ($estadoConv !== 'all') $conds[] = "estado_convenio = " . $db->quote($estadoConv);
        if ($colegioId)            $conds[] = "colegio_id = $colegioId";
        if ($kitNombre)            $conds[] = "kit = " . $db->quote($kitNombre);
        if ($fechaDesde)           $conds[] = "fecha_convenio >= '$fechaDesde'";
        if ($fechaHasta)           $conds[] = "fecha_convenio <= '$fechaHasta'";
        $where = $conds ? ('WHERE ' . implode(' AND ', $conds)) : '';
        $datos = $db->query("SELECT * FROM v_ventas_colegios $where ORDER BY colegio, fecha_convenio DESC, kit")->fetchAll();
        break;

    case 'financiero':
        $titulo_reporte = 'Reporte Financiero Consolidado';
        $subtitulo = 'Del ' . date('d/m/Y', strtotime($fechaDesde)) . ' al ' . date('d/m/Y', strtotime($fechaHasta));
        $columnas = ['Mes','Canal','Operaciones','Total COP','%'];
        $mesDesde = substr($fechaDesde, 0, 7);
        $mesHasta = substr($fechaHasta, 0, 7);
        $conds = ["mes BETWEEN '$mesDesde' AND '$mesHasta'"];
        if ($fuente !== 'all') $conds[] = "fuente = " . $db->quote($fuente);
        $where = 'WHERE ' . implode(' AND ', $conds);
        $datos = $db->query("SELECT * FROM v_ingresos_mensuales $where ORDER BY mes DESC, fuente")->fetchAll();
        $grand_total = array_sum(array_column($datos,'total_cop')) ?: 1;
        foreach ($datos as &$r) { $r['pct'] = round(($r['total_cop'] / $grand_total) * 100, 1); }
        unset($r);
        break;
}

// ── Si es CSV: descargar directo ──
if ($salida === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="robotschool_' . $tipo . '_' . date('Y-m-d') . '.csv"');
    echo "\xEF\xBB\xBF"; // BOM UTF-8
    $out = fopen('php://output','w');
    fputcsv($out, ['ROBOTSchool Inventory','Reporte: '.strtoupper($tipo),'Generado:',date('d/m/Y H:i')], ';');
    fputcsv($out, [], ';');
    fputcsv($out, $columnas, ';');
    foreach ($datos as $r) { fputcsv($out, array_values($r), ';'); }
    // Fila de totales para reportes comerciales
    if ($tipo === 'ventas_colegios') {
        fputcsv($out, [], ';');
        fputcsv($out, ['TOTAL','','','','',array_sum(array_column($datos,'num_estudiantes')),'',array_sum(array_column($datos,'valor_linea')),'',''], ';');
    }
    if ($tipo === 'financiero') {
        fputcsv($out, [], ';');
        fputcsv($out, ['TOTAL','',array_sum(array_column($datos,'cantidad')),array_sum(array_column($datos,'total_cop')),'100%'], ';');
    }
    fclose($out);
    exit;
}

// ── Logo en base64 para embeber en HTML (sin depender de ruta relativa en PDF) ──
$logo_path = APP_ROOT . '/assets/img/logo_oficial.png';
$logo_b64 = file_exists($logo_path) ? 'data:image/png;base64,' . base64_encode(file_get_contents($logo_path)) : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($titulo_reporte) ?> &mdash; ROBOTSchool</title>
<!-- SheetJS para Excel -->
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', Arial, sans-serif; font-size: 10pt; color: #1e293b; background: #f1f5f9; }

  /* ── Controles (no se imprimen) ── */
  .toolbar {
    position: fixed; top: 0; left: 0; right: 0; z-index: 100;
    background: #1e293b; color: #fff; padding: .6rem 1.5rem;
    display: flex; align-items: center; gap: .75rem;
    box-shadow: 0 2px 8px rgba(0,0,0,.3);
  }
  .toolbar h1 { font-size: .9rem; font-weight: 600; flex: 1; }
  .btn-tool {
    padding: .35rem .9rem; border-radius: 6px; font-size: .8rem;
    font-weight: 600; cursor: pointer; border: none; display: flex; align-items: center; gap: .35rem;
  }
  .btn-pdf   { background: #e53e3e; color: #fff; }
  .btn-excel { background: #276749; color: #fff; }
  .btn-back  { background: #4a5568; color: #fff; }
  .btn-tool:hover { opacity: .85; }

  /* ── Hoja carta ── */
  .page-wrap { padding: 4.5rem 1.5rem 1.5rem; }
  .sheet {
    background: #fff;
    width: 21.59cm;
    min-height: 27.94cm;
    margin: 0 auto;
    padding: 1.2cm 1.4cm 1.4cm;
    box-shadow: 0 4px 24px rgba(0,0,0,.15);
    page-break-after: always;
  }

  /* ── Encabezado ── */
  .report-header {
    display: flex; align-items: center; justify-content: space-between;
    border-bottom: 3px solid #1e293b; padding-bottom: .6cm; margin-bottom: .5cm;
  }
  .report-header .logo img { height: 48px; }
  .report-header .empresa { font-size: 7.5pt; color: #64748b; text-align: right; line-height: 1.4; }
  .report-header .empresa strong { font-size: 9pt; color: #1e293b; display: block; }
  .report-title { text-align: center; margin-bottom: .4cm; }
  .report-title h2 { font-size: 13pt; font-weight: 700; color: #1e293b; }
  .report-title p { font-size: 8pt; color: #64748b; margin-top: 2px; }
  .report-meta {
    display: flex; gap: 1.5rem; font-size: 7.5pt; color: #64748b;
    background: #f8fafc; border-radius: 6px; padding: .25cm .4cm;
    margin-bottom: .4cm;
  }
  .report-meta span strong { color: #1e293b; }

  /* ── Tabla ── */
  table { width: 100%; border-collapse: collapse; font-size: 8pt; }
  thead tr { background: #1e293b; color: #fff; }
  thead th { padding: .22cm .3cm; text-align: left; font-weight: 600; font-size: 7.5pt; white-space: nowrap; }
  thead th.num { text-align: right; }
  tbody tr:nth-child(even) { background: #f8fafc; }
  tbody tr:hover { background: #eff6ff; }
  tbody td { padding: .18cm .3cm; border-bottom: 1px solid #e2e8f0; vertical-align: middle; }
  tbody td.num { text-align: right; font-variant-numeric: tabular-nums; }
  tbody td.center { text-align: center; }
  .group-row td { background: #1e293b !important; color: #fff; font-weight: 700; font-size: 8pt; padding: .2cm .3cm; }
  .subtotal-row td { background: #f0fdf4 !important; font-weight: 700; font-size: 8pt; color: #166534; }
  .total-row td { background: #1e293b !important; color: #fff; font-weight: 700; font-size: 9pt; }

  /* ── Badges ── */
  .cat-pill { border-radius: 20px; padding: 1px 6px; font-size: 7pt; color: #fff; font-weight: 600; white-space: nowrap; }
  .estado-pill { border-radius: 20px; padding: 1px 6px; font-size: 7pt; font-weight: 600; white-space: nowrap; }
  .tipo-entrada { background: #dcfce7; color: #166534; }
  .tipo-salida  { background: #fee2e2; color: #991b1b; }
  .tipo-ajuste  { background: #fef9c3; color: #92400e; }

  /* ── Footer ── */
  .report-footer {
    margin-top: .5cm; padding-top: .3cm; border-top: 1px solid #e2e8f0;
    display: flex; justify-content: space-between; font-size: 7pt; color: #94a3b8;
  }

  /* ── Resumen stats ── */
  .stats-bar {
    display: flex; gap: .6rem; flex-wrap: wrap; margin-bottom: .4cm;
  }
  .stat-box {
    border: 1px solid #e2e8f0; border-radius: 6px; padding: .2cm .4cm;
    text-align: center; min-width: 2.5cm;
  }
  .stat-box .val { font-size: 11pt; font-weight: 700; color: #1e293b; }
  .stat-box .lbl { font-size: 6.5pt; color: #64748b; }

  /* ── Print ── */
  @media print {
    body { background: white; }
    .toolbar { display: none !important; }
    .page-wrap { padding: 0; }
    .sheet { box-shadow: none; margin: 0; width: 100%; min-height: auto; padding: 1cm 1.2cm; }
    @page { size: letter portrait; margin: 0; }
    tbody tr:hover { background: inherit; }
  }
</style>
</head>
<body>

<!-- ── TOOLBAR (no imprime) ── -->
<div class="toolbar">
  <button class="btn-tool btn-back" onclick="history.back()">&#8592; Volver</button>
  <h1>📄 <?= htmlspecialchars($titulo_reporte) ?></h1>
  <button class="btn-tool btn-excel" onclick="exportarExcel()">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8l-6-6zm-1 7V3.5L18.5 9H13z"/></svg>
    Descargar Excel
  </button>
  <button class="btn-tool btn-pdf" onclick="window.print()">
    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor"><path d="M6 2v6H2v14h20V8h-4V2H6zm10 6H8V4h8v4zM4 10h16v10H4V10zm4 2v6h2v-2h2a2 2 0 0 0 0-4H8zm2 2h2v-2h-2v2zm6-2h-2v6h2v-2h1a2 2 0 0 0 0-4h-1zm0 2h1v-2h-1v2z"/></svg>
    Imprimir / PDF
  </button>
  <span style="font-size:.75rem;color:#94a3b8;">Ctrl+P → Guardar como PDF (tamaño: Carta)</span>
</div>

<!-- ── HOJA IMPRIMIBLE ── -->
<div class="page-wrap">
<div class="sheet" id="sheet-main">

  <!-- Header con logo -->
  <div class="report-header">
    <div class="logo">
      <?php if ($logo_b64): ?>
        <img src="<?= $logo_b64 ?>" alt="ROBOTSchool">
      <?php else: ?>
        <strong style="font-size:14pt;color:#1e293b;">ROBOT<span style="color:#e53e3e;">School</span></strong>
      <?php endif; ?>
    </div>
    <div style="text-align:center;flex:1;">
      <!-- espacio central -->
    </div>
    <div class="empresa">
      <strong>ROBOTSchool Colombia</strong>
      Calle 75 #20b-62, Bogotá<br>
      318 654 1859 · robotschool.com.co<br>
      NIT: 900.000.000-0
    </div>
  </div>

  <!-- Título del reporte -->
  <div class="report-title">
    <h2><?= htmlspecialchars($titulo_reporte) ?></h2>
    <?php if ($subtitulo): ?><p><?= htmlspecialchars($subtitulo) ?></p><?php endif; ?>
  </div>

  <!-- Metadata -->
  <div class="report-meta">
    <span><strong>Fecha de generación:</strong> <?= date('d/m/Y H:i') ?></span>
    <span><strong>Registros:</strong> <?= count($datos) ?></span>
    <?php if ($tipo === 'fechas'): ?>
    <span><strong>Período:</strong> <?= date('d/m/Y', strtotime($fechaDesde)) ?> al <?= date('d/m/Y', strtotime($fechaHasta)) ?></span>
    <?php endif; ?>
    <span><strong>Usuario:</strong> <?= htmlspecialchars(Auth::user()['name']) ?></span>
  </div>

  <!-- Stats según tipo -->
  <?php if (in_array($tipo, ['inventario','lotes'])): ?>
  <?php
    $rojos    = count(array_filter($datos, fn($r)=>($r['estado'] ?? '')==='Sin stock'));
    $amarillos= count(array_filter($datos, fn($r)=>($r['estado'] ?? '')==='Stock bajo'));
    $verdes   = count($datos) - $rojos - $amarillos;
    $val_total= array_sum(array_column($datos,'valor_total') ?: []);
  ?>
  <div class="stats-bar">
    <div class="stat-box"><div class="val" style="color:#dc2626;"><?= $rojos ?></div><div class="lbl">Sin Stock</div></div>
    <div class="stat-box"><div class="val" style="color:#d97706;"><?= $amarillos ?></div><div class="lbl">Stock Bajo</div></div>
    <div class="stat-box"><div class="val" style="color:#16a34a;"><?= $verdes ?></div><div class="lbl">Stock OK</div></div>
    <div class="stat-box"><div class="val"><?= count($datos) ?></div><div class="lbl">Total Refs.</div></div>
    <?php if ($val_total): ?>
    <div class="stat-box"><div class="val" style="color:#2563eb;">$<?= number_format($val_total,0,',','.') ?></div><div class="lbl">Valor COP</div></div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <?php if ($tipo === 'valorizacion'): ?>
  <?php
    $tc = array_sum(array_column($datos,'valor_costo'));
    $tv = array_sum(array_column($datos,'valor_venta'));
    $tm = array_sum(array_column($datos,'margen'));
  ?>
  <div class="stats-bar">
    <div class="stat-box"><div class="val" style="color:#2563eb;">$<?= number_format($tc,0,',','.') ?></div><div class="lbl">Costo total COP</div></div>
    <div class="stat-box"><div class="val" style="color:#16a34a;">$<?= number_format($tv,0,',','.') ?></div><div class="lbl">Venta total COP</div></div>
    <div class="stat-box"><div class="val" style="color:#7c3aed;">$<?= number_format($tm,0,',','.') ?></div><div class="lbl">Margen COP</div></div>
  </div>
  <?php endif; ?>

  <?php if ($tipo === 'ventas_colegios'): ?>
  <?php
    $vc_colegios    = count(array_unique(array_column($datos,'colegio')));
    $vc_estudiantes = array_sum(array_column($datos,'num_estudiantes'));
    $vc_total       = array_sum(array_column($datos,'valor_linea'));
  ?>
  <div class="stats-bar">
    <div class="stat-box"><div class="val"><?= $vc_colegios ?></div><div class="lbl">Colegios</div></div>
    <div class="stat-box"><div class="val"><?= count($datos) ?></div><div class="lbl">Líneas</div></div>
    <div class="stat-box"><div class="val" style="color:#2563eb;"><?= number_format($vc_estudiantes,0,',','.') ?></div><div class="lbl">Estudiantes</div></div>
    <div class="stat-box"><div class="val" style="color:#16a34a;">$<?= number_format($vc_total,0,',','.') ?></div><div class="lbl">Total COP</div></div>
  </div>
  <?php endif; ?>

  <?php if ($tipo === 'financiero'): ?>
  <?php
    $por_fuente = [];
    foreach ($datos as $r) $por_fuente[$r['fuente']] = ($por_fuente[$r['fuente']] ?? 0) + $r['total_cop'];
    $fi_total = array_sum($por_fuente);
  ?>
  <div class="stats-bar">
    <div class="stat-box"><div class="val" style="color:#2563eb;">$<?= number_format($por_fuente['tienda'] ?? 0,0,',','.') ?></div><div class="lbl">Tienda</div></div>
    <div class="stat-box"><div class="val" style="color:#16a34a;">$<?= number_format($por_fuente['convenios'] ?? 0,0,',','.') ?></div><div class="lbl">Convenios</div></div>
    <div class="stat-box"><div class="val" style="color:#7c3aed;">$<?= number_format($por_fuente['escuela'] ?? 0,0,',','.') ?></div><div class="lbl">Escuela</div></div>
    <div class="stat-box"><div class="val">$<?= number_format($fi_total,0,',','.') ?></div><div class="lbl">TOTAL COP</div></div>
  </div>
  <?php endif; ?>

  <!-- ── TABLA DE DATOS ── -->
  <table id="tabla-reporte">
    <thead>
      <tr>
        <?php foreach ($columnas as $i => $col): ?>
          <th class="<?= in_array($i, [3,4,5,8,9,10,11]) ? 'num' : '' ?>"><?= htmlspecialchars($col) ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>

    <?php if ($tipo === 'inventario'): ?>
      <?php $cat_actual = ''; foreach ($datos as $r): ?>
        <?php if ($r['categoria'] !== $cat_actual): $cat_actual = $r['categoria']; ?>
        <tr class="group-row">
          <td colspan="<?= count($columnas) ?>">
            <span class="cat-pill" style="background:<?= $r['color'] ?>;"><?= htmlspecialchars($r['categoria']) ?></span>
          </td>
        </tr>
        <?php endif; ?>
        <tr>
          <td><code style="font-size:7pt;"><?= htmlspecialchars($r['codigo']) ?></code></td>
          <td><?= htmlspecialchars($r['nombre']) ?></td>
          <td><?= htmlspecialchars($r['categoria']) ?></td>
          <td class="num"><strong><?= $r['stock_actual'] ?></strong></td>
          <td class="num"><?= $r['stock_minimo'] ?></td>
          <td class="num"><?= $r['stock_maximo'] ?></td>
          <td class="center"><span class="estado-pill" style="background:<?= $r['estado_color'] ?>22;color:<?= $r['estado_color'] ?>;border:1px solid <?= $r['estado_color'] ?>44;"><?= $r['estado'] ?></span></td>
          <td style="font-size:7.5pt;color:#64748b;"><?= htmlspecialchars($r['ubicacion'] ?? '&mdash;') ?></td>
          <td class="num"><?= $r['costo_real_cop'] ? '$'.number_format($r['costo_real_cop'],0,',','.') : '&mdash;' ?></td>
          <td class="num"><?= $r['precio_venta_cop'] ? '$'.number_format($r['precio_venta_cop'],0,',','.') : '&mdash;' ?></td>
        </tr>
      <?php endforeach; ?>

    <?php elseif ($tipo === 'importacion'): ?>
      <?php foreach ($datos as $r): ?>
        <tr>
          <td><code style="font-size:7pt;"><?= htmlspecialchars($r['codigo']) ?></code></td>
          <td><?= htmlspecialchars($r['nombre']) ?></td>
          <td><span class="cat-pill" style="background:<?= $r['color'] ?>;"><?= htmlspecialchars($r['categoria']) ?></span></td>
          <td class="num"><?= $r['cantidad'] ?></td>
          <td class="num">$<?= number_format($r['precio_unit_usd'],4,',','.') ?></td>
          <td class="num">$<?= number_format($r['subtotal_usd'],2,',','.') ?></td>
          <td class="num"><?= number_format($r['peso_unit_gramos'],1,',','.') ?>g</td>
          <td class="num"><?= $r['flete_asignado_cop'] ? '$'.number_format($r['flete_asignado_cop'],0,',','.') : '&mdash;' ?></td>
          <td class="num"><?= $r['arancel_asignado_cop'] ? '$'.number_format($r['arancel_asignado_cop'],0,',','.') : '&mdash;' ?></td>
          <td class="num"><?= $r['iva_asignado_cop'] ? '$'.number_format($r['iva_asignado_cop'],0,',','.') : '&mdash;' ?></td>
          <td class="num"><strong><?= $r['costo_unit_final_cop'] ? '$'.number_format($r['costo_unit_final_cop'],0,',','.') : '&mdash;' ?></strong></td>
        </tr>
      <?php endforeach; ?>

    <?php elseif ($tipo === 'fechas'): ?>
      <?php $fecha_actual = ''; foreach ($datos as $r): ?>
        <?php
          $fecha_dia = substr($r['fecha'],0,10);
          if ($fecha_dia !== $fecha_actual): $fecha_actual = $fecha_dia;
        ?>
        <tr class="group-row">
          <td colspan="<?= count($columnas) ?>"><?= $r['fecha'] ?></td>
        </tr>
        <?php endif; ?>
        <tr>
          <td style="font-size:7.5pt;"><?= $r['fecha'] ?></td>
          <td><code style="font-size:7pt;"><?= htmlspecialchars($r['codigo']) ?></code></td>
          <td><?= htmlspecialchars($r['nombre']) ?></td>
          <td><span class="cat-pill" style="background:<?= $r['color'] ?>;"><?= htmlspecialchars($r['categoria']) ?></span></td>
          <td><span class="estado-pill tipo-<?= $r['tipo'] ?>"><?= ucfirst($r['tipo']) ?></span></td>
          <td class="num" style="color:<?= $r['cantidad']>=0?'#16a34a':'#dc2626' ?>;font-weight:700;"><?= $r['cantidad']>0?'+':'' ?><?= $r['cantidad'] ?></td>
          <td class="num"><?= $r['stock_antes'] ?></td>
          <td class="num"><?= $r['stock_despues'] ?></td>
          <td style="font-size:7.5pt;"><?= htmlspecialchars($r['referencia'] ?? '&mdash;') ?></td>
          <td style="font-size:7.5pt;"><?= htmlspecialchars($r['usuario'] ?? '&mdash;') ?></td>
        </tr>
      <?php endforeach; ?>

    <?php elseif ($tipo === 'lotes'): ?>
      <?php
        $cat_actual=''; $sub_stock=0; $sub_valor=0; $grand_stock=0; $grand_valor=0;
        foreach ($datos as $r):
          if ($r['categoria'] !== $cat_actual):
            if ($cat_actual !== ''): ?>
        <tr class="subtotal-row">
          <td colspan="6" style="text-align:right;">Subtotal <?= htmlspecialchars($cat_actual) ?>:</td>
          <td class="num">$<?= number_format($sub_valor,0,',','.') ?></td>
          <td></td>
        </tr>
            <?php endif;
            $cat_actual = $r['categoria']; $sub_stock=0; $sub_valor=0; ?>
        <tr class="group-row">
          <td colspan="<?= count($columnas) ?>">
            <span class="cat-pill" style="background:<?= $r['color'] ?>;">[<?= $r['prefijo'] ?>] <?= htmlspecialchars($r['categoria']) ?></span>
          </td>
        </tr>
          <?php endif;
          $sub_stock += $r['stock_actual'];
          $sub_valor += $r['valor_total'];
          $grand_stock += $r['stock_actual'];
          $grand_valor += $r['valor_total'];
        ?>
        <tr>
          <td><code style="font-size:7pt;"><?= htmlspecialchars($r['codigo']) ?></code></td>
          <td><?= htmlspecialchars($r['nombre']) ?></td>
          <td class="num"><strong><?= $r['stock_actual'] ?></strong></td>
          <td class="num"><?= $r['stock_minimo'] ?></td>
          <td class="center"><span class="estado-pill" style="background:<?= $r['estado_color'] ?>22;color:<?= $r['estado_color'] ?>;border:1px solid <?= $r['estado_color'] ?>44;"><?= $r['estado'] ?></span></td>
          <td class="num"><?= $r['costo_real_cop'] ? '$'.number_format($r['costo_real_cop'],2,',','.') : '&mdash;' ?></td>
          <td class="num"><strong><?= $r['valor_total'] ? '$'.number_format($r['valor_total'],0,',','.') : '&mdash;' ?></strong></td>
          <td style="font-size:7.5pt;"><?= htmlspecialchars($r['proveedor'] ?? '&mdash;') ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($cat_actual): ?>
        <tr class="subtotal-row">
          <td colspan="6" style="text-align:right;">Subtotal <?= htmlspecialchars($cat_actual) ?>:</td>
          <td class="num">$<?= number_format($sub_valor,0,',','.') ?></td>
          <td></td>
        </tr>
        <tr class="total-row">
          <td colspan="6" style="text-align:right;">TOTAL INVENTARIO:</td>
          <td class="num">$<?= number_format($grand_valor,0,',','.') ?></td>
          <td></td>
        </tr>
      <?php endif; ?>

    <?php elseif ($tipo === 'valorizacion'): ?>
      <?php foreach ($datos as $r): $pct = $total_venta > 0 ? ($r['valor_venta']/$total_venta*100) : 0; ?>
        <tr>
          <td><span class="cat-pill" style="background:<?= $r['color'] ?>;"><?= htmlspecialchars($r['categoria']) ?></span></td>
          <td class="num"><?= $r['num_elementos'] ?></td>
          <td class="num"><?= number_format($r['stock_total'],0,',','.') ?></td>
          <td class="num">$<?= number_format($r['valor_costo'],0,',','.') ?></td>
          <td class="num" style="color:#16a34a;">$<?= number_format($r['valor_venta'],0,',','.') ?></td>
          <td class="num" style="color:#7c3aed;">$<?= number_format($r['margen'],0,',','.') ?></td>
          <td class="num"><?= number_format($pct,1,',','.') ?>%</td>
        </tr>
      <?php endforeach; ?>
        <tr class="total-row">
          <td><strong>TOTAL</strong></td>
          <td class="num"><?= array_sum(array_column($datos,'num_elementos')) ?></td>
          <td class="num"><?= number_format(array_sum(array_column($datos,'stock_total')),0,',','.') ?></td>
          <td class="num">$<?= number_format($tc,0,',','.') ?></td>
          <td class="num">$<?= number_format($tv,0,',','.') ?></td>
          <td class="num">$<?= number_format($tm,0,',','.') ?></td>
          <td class="num">100%</td>
        </tr>

    <?php elseif ($tipo === 'ventas_colegios'): ?>
      <?php
        $col_actual = ''; $sub_est = 0; $sub_val = 0;
        foreach ($datos as $r):
          if ($r['colegio'] !== $col_actual):
            if ($col_actual !== ''): ?>
        <tr class="subtotal-row">
          <td colspan="9" style="text-align:right;">Subtotal <?= htmlspecialchars($col_actual) ?>: <?= number_format($sub_est,0,',','.') ?> est. &mdash;</td>
          <td class="num">$<?= number_format($sub_val,0,',','.') ?></td>
        </tr>
        <?php   endif;
            $col_actual = $r['colegio']; $sub_est = 0; $sub_val = 0; ?>
        <tr class="group-row">
          <td colspan="<?= count($columnas) ?>"><?= htmlspecialchars($r['colegio']) ?><?= $r['ciudad'] ? ' · '.$r['ciudad'] : '' ?></td>
        </tr>
        <?php endif;
          $sub_est += $r['num_estudiantes'];
          $sub_val += $r['valor_linea'];
        ?>
        <tr>
          <td><?= htmlspecialchars($r['colegio']) ?></td>
          <td style="font-size:7.5pt;color:#64748b;"><?= htmlspecialchars($r['ciudad'] ?? '') ?></td>
          <td style="font-size:7.5pt;"><?= htmlspecialchars($r['tipo_colegio'] ?? '') ?></td>
          <td><?= htmlspecialchars($r['kit'] ?? '&mdash;') ?></td>
          <td style="font-size:7.5pt;color:#64748b;"><?= htmlspecialchars($r['nombre_curso'] ?? '&mdash;') ?></td>
          <td class="num"><?= number_format($r['num_estudiantes'],0,',','.') ?></td>
          <td class="num"><?= $r['valor_kit'] ? '$'.number_format($r['valor_kit'],0,',','.') : '&mdash;' ?></td>
          <td class="num"><strong><?= $r['valor_linea'] ? '$'.number_format($r['valor_linea'],0,',','.') : '&mdash;' ?></strong></td>
          <td style="font-size:7.5pt;"><?= htmlspecialchars($r['codigo_convenio'] ?? '') ?></td>
          <td style="font-size:7.5pt;"><?= $r['fecha_convenio'] ? date('d/m/Y', strtotime($r['fecha_convenio'])) : '' ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if ($col_actual): ?>
        <tr class="subtotal-row">
          <td colspan="9" style="text-align:right;">Subtotal <?= htmlspecialchars($col_actual) ?>: <?= number_format($sub_est,0,',','.') ?> est. &mdash;</td>
          <td class="num">$<?= number_format($sub_val,0,',','.') ?></td>
        </tr>
        <tr class="total-row">
          <td colspan="5"><strong>TOTAL</strong></td>
          <td class="num"><?= number_format($vc_estudiantes,0,',','.') ?></td>
          <td></td>
          <td class="num">$<?= number_format($vc_total,0,',','.') ?></td>
          <td colspan="2"></td>
        </tr>
      <?php endif; ?>

    <?php elseif ($tipo === 'financiero'): ?>
      <?php
        $fuentes_label = ['tienda'=>'Tienda Online','convenios'=>'Convenios Colegios','escuela'=>'Escuela / Matrículas'];
        $fuente_colors = ['tienda'=>'#2563eb','convenios'=>'#16a34a','escuela'=>'#7c3aed'];
        $mes_actual = '';
        foreach ($datos as $r):
          if ($r['mes'] !== $mes_actual):
            $mes_actual = $r['mes']; ?>
        <tr class="group-row">
          <td colspan="<?= count($columnas) ?>"><?= date('F Y', strtotime($r['mes'].'-01')) ?></td>
        </tr>
        <?php endif;
          $fc = $fuente_colors[$r['fuente']] ?? '#64748b'; ?>
        <tr>
          <td style="font-size:7.5pt;color:#64748b;"><?= date('M Y', strtotime($r['mes'].'-01')) ?></td>
          <td><span class="estado-pill" style="background:<?= $fc ?>22;color:<?= $fc ?>;border:1px solid <?= $fc ?>44;"><?= $fuentes_label[$r['fuente']] ?? ucfirst($r['fuente']) ?></span></td>
          <td class="num"><?= number_format($r['cantidad'],0,',','.') ?></td>
          <td class="num"><strong>$<?= number_format($r['total_cop'],0,',','.') ?></strong></td>
          <td class="num"><?= $r['pct'] ?>%</td>
        </tr>
      <?php endforeach; ?>
        <tr class="total-row">
          <td colspan="2"><strong>TOTAL</strong></td>
          <td class="num"><?= number_format(array_sum(array_column($datos,'cantidad')),0,',','.') ?></td>
          <td class="num">$<?= number_format($fi_total,0,',','.') ?></td>
          <td class="num">100%</td>
        </tr>

    <?php endif; ?>

    </tbody>
  </table>

  <!-- Footer -->
  <div class="report-footer">
    <span>ROBOTSchool Colombia · Sistema de Inventario v1.6</span>
    <span>Generado: <?= date('d/m/Y H:i:s') ?> · <?= htmlspecialchars(Auth::user()['name']) ?></span>
    <span>Página 1</span>
  </div>

</div><!-- /sheet -->
</div><!-- /page-wrap -->

<script>
function exportarExcel() {
  const tabla = document.getElementById('tabla-reporte');
  const wb = XLSX.utils.book_new();

  // Hoja de datos
  const ws = XLSX.utils.table_to_sheet(tabla);

  // Estilos básicos del encabezado (SheetJS CE no soporta estilos completos, pero sí columnas anchas)
  const cols = [];
  const headers = tabla.querySelectorAll('thead th');
  headers.forEach(() => cols.push({wch: 18}));
  ws['!cols'] = cols;

  XLSX.utils.book_append_sheet(wb, ws, '<?= addslashes($titulo_reporte) ?>');

  // Hoja de metadata
  const meta = [
    ['ROBOTSchool Colombia &mdash; Sistema de Inventario'],
    ['Reporte:', '<?= addslashes($titulo_reporte) ?>'],
    ['Generado:', '<?= date('d/m/Y H:i') ?>'],
    ['Usuario:', '<?= addslashes(Auth::user()['name']) ?>'],
    ['Total registros:', <?= count($datos) ?>],
  ];
  const wsMeta = XLSX.utils.aoa_to_sheet(meta);
  XLSX.utils.book_append_sheet(wb, wsMeta, 'Info');

  XLSX.writeFile(wb, 'robotschool_<?= $tipo ?>_<?= date('Y-m-d') ?>.xlsx');
}
</script>

</body>
</html>
