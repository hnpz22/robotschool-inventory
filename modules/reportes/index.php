<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();

$db = Database::get();
$pageTitle  = 'Reportes de Inventario';
$activeMenu = 'reportes';

// ── Parámetros del reporte ──
$tipo       = $_GET['tipo']      ?? 'dashboard';
$fechaDesde = $_GET['desde']     ?? date('Y-m-01');
$fechaHasta = $_GET['hasta']     ?? date('Y-m-d');
$pedidoId   = (int)($_GET['pedido_id'] ?? 0);
$catId      = (int)($_GET['cat_id']    ?? 0);
$provId     = (int)($_GET['prov_id']   ?? 0);
$formato    = $_GET['formato']   ?? 'html';

// ── Parámetros adicionales para reportes comerciales ──
$colegioId  = (int)($_GET['colegio_id']  ?? 0);
$kitNombre  = trim($_GET['kit_nombre']   ?? '');
$fuente     = $_GET['fuente']            ?? 'all';
$estadoConv = $_GET['estado_conv']       ?? 'aprobado';

// Para reportes comerciales, default al año completo si no se especificó periodo
if (in_array($tipo, ['ventas_colegios','financiero']) && !isset($_GET['desde'])) {
    $fechaDesde = date('Y-01-01');
    $fechaHasta = date('Y-m-d');
}

// ── Datos para filtros ──
$pedidos    = $db->query("SELECT id, codigo_pedido, fecha_pedido, estado FROM pedidos_importacion ORDER BY fecha_pedido DESC")->fetchAll();
$categorias = $db->query("SELECT id, nombre, prefijo FROM categorias WHERE activa=1 ORDER BY nombre")->fetchAll();
$proveedores= $db->query("SELECT id, nombre FROM proveedores WHERE activo=1 ORDER BY nombre")->fetchAll();
$colegios_list = $db->query("SELECT id, nombre FROM colegios WHERE activo=1 ORDER BY nombre")->fetchAll();
$kits_list     = $db->query("SELECT DISTINCT kit_nombre AS nombre_kit FROM (SELECT nombre_kit AS kit_nombre FROM convenio_cursos WHERE nombre_kit IS NOT NULL AND nombre_kit != '' UNION SELECT kit_nombre FROM tienda_pedidos WHERE kit_nombre IS NOT NULL AND kit_nombre != '' AND colegio_id IS NOT NULL) t ORDER BY kit_nombre")->fetchAll();

// ── Ejecutar reporte según tipo ──
$datos = [];
$columnas = [];
$titulo_reporte = '';

switch ($tipo) {

    // ─────────────────────────────────────────────
    // 0. DASHBOARD EJECUTIVO
    // ─────────────────────────────────────────────
    case 'dashboard':
        $titulo_reporte = 'Dashboard Ejecutivo';
        $columnas = [];
        $datos    = [];

        $d_revenue = $db->query("
            SELECT
                SUM(CASE WHEN mes = DATE_FORMAT(CURDATE(),'%Y-%m') THEN total_cop ELSE 0 END)                       AS total_mes,
                SUM(CASE WHEN mes LIKE CONCAT(YEAR(CURDATE()),'-%') THEN total_cop ELSE 0 END)                      AS total_anio,
                SUM(CASE WHEN fuente='tienda'    AND mes = DATE_FORMAT(CURDATE(),'%Y-%m') THEN total_cop ELSE 0 END) AS tienda_mes,
                SUM(CASE WHEN fuente='tienda'    AND mes LIKE CONCAT(YEAR(CURDATE()),'-%') THEN total_cop ELSE 0 END) AS tienda_anio,
                SUM(CASE WHEN fuente='convenios' AND mes = DATE_FORMAT(CURDATE(),'%Y-%m') THEN total_cop ELSE 0 END) AS conv_mes,
                SUM(CASE WHEN fuente='convenios' AND mes LIKE CONCAT(YEAR(CURDATE()),'-%') THEN total_cop ELSE 0 END) AS conv_anio,
                SUM(CASE WHEN fuente='escuela'   AND mes = DATE_FORMAT(CURDATE(),'%Y-%m') THEN total_cop ELSE 0 END) AS esc_mes,
                SUM(CASE WHEN fuente='escuela'   AND mes LIKE CONCAT(YEAR(CURDATE()),'-%') THEN total_cop ELSE 0 END) AS esc_anio
            FROM v_ingresos_mensuales
        ")->fetch();

        $d_pipeline = $db->query("
            SELECT
                SUM(CASE WHEN estado='pendiente' THEN 1 ELSE 0 END)                                                                        AS pendiente,
                SUM(CASE WHEN estado IN ('aprobado','en_produccion','listo_produccion','en_alistamiento','listo_envio') THEN 1 ELSE 0 END)   AS en_proceso,
                SUM(CASE WHEN estado='despachado' THEN 1 ELSE 0 END)                                                                       AS despachado,
                SUM(CASE WHEN estado='entregado'  THEN 1 ELSE 0 END)                                                                       AS entregado,
                SUM(CASE WHEN estado NOT IN ('entregado','cancelado','despachado') THEN 1 ELSE 0 END)                                       AS activos,
                SUM(CASE WHEN YEAR(fecha_compra)=YEAR(CURDATE()) AND estado != 'cancelado' THEN 1 ELSE 0 END)                               AS total_anio,
                ROUND(AVG(CASE WHEN estado='entregado' AND fecha_entrega IS NOT NULL THEN DATEDIFF(fecha_entrega,fecha_compra) END),1)       AS dias_promedio
            FROM tienda_pedidos
        ")->fetch();

        $d_convenios = $db->query("
            SELECT
                SUM(CASE WHEN estado IN ('aprobado','vigente') THEN 1 ELSE 0 END) AS activos,
                SUM(CASE WHEN estado = 'pendiente' THEN 1 ELSE 0 END)             AS pendientes,
                COUNT(*)                                                           AS total_anio
            FROM convenios
            WHERE YEAR(fecha_convenio) = YEAR(CURDATE()) AND activo = 1
        ")->fetch();

        $d_top_colegios = $db->query("
            SELECT colegio AS nombre, COUNT(*) AS operaciones, SUM(valor_linea) AS total
            FROM v_ventas_colegios
            WHERE YEAR(fecha_convenio) = YEAR(CURDATE())
              AND estado_convenio != 'rechazado'
              AND colegio IS NOT NULL AND colegio != ''
            GROUP BY colegio
            ORDER BY total DESC
            LIMIT 6
        ")->fetchAll();

        $d_tendencia_raw = $db->query("
            SELECT mes, fuente, SUM(total_cop) AS total
            FROM v_ingresos_mensuales
            WHERE mes >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 5 MONTH),'%Y-%m')
            GROUP BY mes, fuente
            ORDER BY mes ASC, fuente
        ")->fetchAll();
        // Organizar tendencia por mes
        $d_meses = []; $d_tend = [];
        foreach ($d_tendencia_raw as $r) {
            $d_meses[$r['mes']] = true;
            $d_tend[$r['mes']][$r['fuente']] = (float)$r['total'];
        }
        ksort($d_meses); ksort($d_tend);
        $d_max_mes = 1;
        foreach ($d_tend as $m => $fuentes) {
            $tot = array_sum($fuentes);
            if ($tot > $d_max_mes) $d_max_mes = $tot;
        }
        break;

    // ─────────────────────────────────────────────
    // 1. INVENTARIO GENERAL (con semáforo)
    // ─────────────────────────────────────────────
    case 'inventario':
        $titulo_reporte = 'Inventario General de Stock';
        $columnas = ['Código','Elemento','Categoría','Stock Actual','Stock Mín','Stock Máx','Semáforo','Ubicación','Costo COP','Precio COP'];
        $where = "WHERE e.activo=1";
        if ($catId) $where .= " AND e.categoria_id=$catId";
        $datos = $db->query("
            SELECT e.codigo, e.nombre, c.nombre AS categoria, c.color AS cat_color,
                   e.stock_actual, e.stock_minimo, e.stock_maximo,
                   e.ubicacion, e.costo_real_cop, e.precio_venta_cop,
                   CASE
                     WHEN e.stock_actual <= 0              THEN 'rojo'
                     WHEN e.stock_actual <= e.stock_minimo THEN 'amarillo'
                     WHEN e.stock_actual >= e.stock_maximo THEN 'azul'
                     ELSE 'verde'
                   END AS semaforo
            FROM elementos e
            JOIN categorias c ON c.id = e.categoria_id
            $where
            ORDER BY c.nombre, e.nombre
        ")->fetchAll();
        break;

    // ─────────────────────────────────────────────
    // 2. POR IMPORTACIÓN (pedido específico)
    // ─────────────────────────────────────────────
    case 'importacion':
        $titulo_reporte = 'Reporte por Importación';
        $columnas = ['Código','Elemento','Categoría','Cantidad','Precio USD','Subtotal USD','Peso g','% Peso','Flete COP','Arancel COP','IVA COP','Costo Unit. Final COP'];
        $where = "WHERE 1=1";
        if ($pedidoId) $where .= " AND pi.pedido_id=$pedidoId";
        $datos = $db->query("
            SELECT e.codigo, e.nombre, c.nombre AS categoria,
                   pi.cantidad, pi.precio_unit_usd,
                   pi.subtotal_usd, pi.peso_unit_gramos,
                   pi.pct_peso, pi.flete_asignado_cop,
                   pi.arancel_asignado_cop, pi.iva_asignado_cop,
                   pi.costo_unit_final_cop,
                   p.codigo_pedido, p.fecha_pedido, p.estado,
                   p.tasa_cambio_usd_cop, p.costo_dhl_usd
            FROM pedido_items pi
            JOIN elementos e ON e.id = pi.elemento_id
            JOIN categorias c ON c.id = e.categoria_id
            JOIN pedidos_importacion p ON p.id = pi.pedido_id
            $where
            ORDER BY p.fecha_pedido DESC, c.nombre, e.nombre
        ")->fetchAll();
        break;

    // ─────────────────────────────────────────────
    // 3. POR FECHAS (movimientos / kardex)
    // ─────────────────────────────────────────────
    case 'fechas':
        $titulo_reporte = "Movimientos del $fechaDesde al $fechaHasta";
        $columnas = ['Fecha','Código','Elemento','Categoría','Tipo','Cantidad','Stock Antes','Stock Después','Referencia','Motivo','Usuario'];
        $where = "WHERE DATE(m.created_at) BETWEEN '$fechaDesde' AND '$fechaHasta'";
        if ($catId) $where .= " AND c.id=$catId";
        $datos = $db->query("
            SELECT DATE(m.created_at) AS fecha, e.codigo, e.nombre,
                   c.nombre AS categoria, m.tipo, m.cantidad,
                   m.stock_antes, m.stock_despues,
                   m.referencia, m.motivo, u.nombre AS usuario
            FROM movimientos m
            JOIN elementos e ON e.id = m.elemento_id
            JOIN categorias c ON c.id = e.categoria_id
            LEFT JOIN usuarios u ON u.id = m.usuario_id
            $where
            ORDER BY m.created_at DESC
        ")->fetchAll();
        break;

    // ─────────────────────────────────────────────
    // 4. POR LOTES / CATEGORÍA
    // ─────────────────────────────────────────────
    case 'lotes':
        $titulo_reporte = 'Inventario por Categoría (Lotes)';
        $columnas = ['Código','Elemento','Stock','Mín','Semáforo','Costo Unit. COP','Valor Total COP','Proveedor'];
        $where = "WHERE e.activo=1";
        if ($catId) $where .= " AND e.categoria_id=$catId";
        if ($provId) $where .= " AND e.proveedor_id=$provId";
        $datos = $db->query("
            SELECT e.codigo, e.nombre, c.nombre AS categoria, c.color AS cat_color,
                   c.prefijo, e.stock_actual, e.stock_minimo,
                   e.costo_real_cop,
                   (e.stock_actual * e.costo_real_cop) AS valor_total,
                   pv.nombre AS proveedor,
                   CASE
                     WHEN e.stock_actual <= 0              THEN 'rojo'
                     WHEN e.stock_actual <= e.stock_minimo THEN 'amarillo'
                     WHEN e.stock_actual >= e.stock_maximo THEN 'azul'
                     ELSE 'verde'
                   END AS semaforo
            FROM elementos e
            JOIN categorias c ON c.id = e.categoria_id
            LEFT JOIN proveedores pv ON pv.id = e.proveedor_id
            $where
            ORDER BY c.nombre, e.nombre
        ")->fetchAll();
        break;

    // ─────────────────────────────────────────────
    // 5. VALORIZACIÓN DE INVENTARIO
    // ─────────────────────────────────────────────
    case 'valorizacion':
        $titulo_reporte = 'Valorización del Inventario';
        $columnas = ['Categoría','# Elementos','Stock Total','Valor Costo COP','Valor Venta COP','Margen COP'];
        $datos = $db->query("
            SELECT c.nombre AS categoria, c.color AS cat_color,
                   COUNT(e.id) AS num_elementos,
                   SUM(e.stock_actual) AS stock_total,
                   SUM(e.stock_actual * e.costo_real_cop)   AS valor_costo,
                   SUM(e.stock_actual * e.precio_venta_cop) AS valor_venta,
                   SUM(e.stock_actual * (e.precio_venta_cop - e.costo_real_cop)) AS margen
            FROM elementos e
            JOIN categorias c ON c.id = e.categoria_id
            WHERE e.activo=1
            GROUP BY c.id
            ORDER BY valor_costo DESC
        ")->fetchAll();
        break;

    // ─────────────────────────────────────────────
    // 6. VENTAS DE KITS POR COLEGIO
    // ─────────────────────────────────────────────
    case 'ventas_colegios':
        $titulo_reporte = 'Ventas de Kits por Colegio';
        $columnas = ['Colegio','Ciudad','Tipo','Kit','Curso','Cant./Est.','Val. Kit','Val. Línea','Canal','Convenio/Ref','Fecha'];

        $conds = [];
        if ($estadoConv !== 'all') $conds[] = "estado_convenio = " . $db->quote($estadoConv);
        if ($colegioId)            $conds[] = "colegio_id = $colegioId";
        if ($kitNombre)            $conds[] = "kit = " . $db->quote($kitNombre);
        if ($fechaDesde)           $conds[] = "fecha_convenio >= '$fechaDesde'";
        if ($fechaHasta)           $conds[] = "fecha_convenio <= '$fechaHasta'";

        $where = $conds ? ('WHERE ' . implode(' AND ', $conds)) : '';

        $datos = $db->query("
            SELECT * FROM v_ventas_colegios
            $where
            ORDER BY colegio, fecha_convenio DESC, kit
        ")->fetchAll();
        break;

    // ─────────────────────────────────────────────
    // 7. FINANCIERO CONSOLIDADO
    // ─────────────────────────────────────────────
    case 'financiero':
        $titulo_reporte = 'Reporte Financiero Consolidado';
        $columnas = ['Mes','Canal','Operaciones','Total COP','% del Período'];

        $mesDesde = substr($fechaDesde, 0, 7);
        $mesHasta = substr($fechaHasta, 0, 7);

        $conds = ["mes BETWEEN '$mesDesde' AND '$mesHasta'"];
        if ($fuente !== 'all') $conds[] = "fuente = " . $db->quote($fuente);
        $where = 'WHERE ' . implode(' AND ', $conds);

        $datos = $db->query("
            SELECT * FROM v_ingresos_mensuales
            $where
            ORDER BY mes DESC, fuente
        ")->fetchAll();

        $grand_total = array_sum(array_column($datos, 'total_cop')) ?: 1;
        foreach ($datos as &$r) {
            $r['pct'] = round(($r['total_cop'] / $grand_total) * 100, 1);
        }
        unset($r);
        break;
}

// Resumen stats según tipo
$stats = [];
if ($tipo === 'inventario' || $tipo === 'lotes') {
    $stats['total']    = count($datos);
    $stats['rojos']    = count(array_filter($datos, fn($r) => $r['semaforo']==='rojo'));
    $stats['amarillos']= count(array_filter($datos, fn($r) => $r['semaforo']==='amarillo'));
    $stats['verdes']   = count(array_filter($datos, fn($r) => $r['semaforo']==='verde'));
}
if ($tipo === 'fechas') {
    $stats['total']    = count($datos);
    $stats['entradas'] = count(array_filter($datos, fn($r) => $r['tipo']==='entrada'));
    $stats['salidas']  = count(array_filter($datos, fn($r) => $r['tipo']==='salida'));
    $stats['ajustes']  = count(array_filter($datos, fn($r) => !in_array($r['tipo'],['entrada','salida'])));
}
if ($tipo === 'valorizacion') {
    $stats['total_costo'] = array_sum(array_column($datos,'valor_costo'));
    $stats['total_venta'] = array_sum(array_column($datos,'valor_venta'));
    $stats['total_margen']= array_sum(array_column($datos,'margen'));
}
if ($tipo === 'ventas_colegios') {
    $stats['colegios']      = count(array_unique(array_column($datos, 'colegio')));
    $stats['lineas']        = count($datos);
    $stats['estudiantes']   = array_sum(array_column($datos, 'num_estudiantes'));
    $stats['total_valor']   = array_sum(array_column($datos, 'valor_linea'));
}
if ($tipo === 'financiero') {
    $stats['total_general']  = array_sum(array_column($datos, 'total_cop'));
    $por_fuente = [];
    foreach ($datos as $r) $por_fuente[$r['fuente']] = ($por_fuente[$r['fuente']] ?? 0) + $r['total_cop'];
    $stats['total_tienda']    = $por_fuente['tienda']    ?? 0;
    $stats['total_convenios'] = $por_fuente['convenios'] ?? 0;
    $stats['total_escuela']   = $por_fuente['escuela']   ?? 0;
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<style>
/* Reportes — NO redefinir .section-card (viene de app.css) */
.filter-group label { font-size: .78rem; font-weight: 600; color: var(--rs-text-muted); margin-bottom: .3rem; }
.stat-chip { border-radius: var(--rs-radius-sm); padding: .45rem .9rem; font-size: .82rem; font-weight: 600; }
.report-table thead th { background: var(--rs-gray-100); color: var(--rs-text-muted); font-size: var(--rs-font-xs); font-weight: 600; letter-spacing: .05em; text-transform: uppercase; white-space: nowrap; padding: .55rem .75rem; border: none; border-bottom: 2px solid var(--rs-gray-200); }
.report-table tbody tr:hover { background: var(--rs-gray-100); }
.report-table tbody td { font-size: var(--rs-font-sm); padding: .48rem .75rem; border-bottom: 1px solid var(--rs-gray-200); vertical-align: middle; }
.sem-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
.sem-rojo    { background: #dc3545; }
.sem-amarillo{ background: #ffc107; }
.sem-verde   { background: #28a745; }
.sem-azul    { background: #0dcaf0; }
.tipo-badge { font-size: .7rem; padding: .2rem .55rem; border-radius: 20px; font-weight: 600; }
.tipo-entrada   { background: #dcfce7; color: #16a34a; }
.tipo-salida    { background: #fee2e2; color: #dc2626; }
.tipo-ajuste    { background: #fef9c3; color: #b45309; }
.tipo-devolucion{ background: #ede9fe; color: #7c3aed; }
.tipo-transferencia{ background: #dbeafe; color: #2563eb; }
.cat-pill { font-size: .7rem; padding: .15rem .5rem; border-radius: 20px; color: #fff; font-weight: 600; }
.print-btn { display: none; }
/* ── Dashboard ejecutivo ── */
.dash-grid   { display:grid; gap:.85rem; }
.dash-kpis   { grid-template-columns: repeat(4,1fr); }
.dash-canales{ grid-template-columns: repeat(3,1fr); }
.dash-bottom { grid-template-columns: 1.6fr 1fr; }
@media(max-width:960px){ .dash-kpis{grid-template-columns:repeat(2,1fr)} .dash-canales{grid-template-columns:1fr 1fr} .dash-bottom{grid-template-columns:1fr} }
@media(max-width:560px){ .dash-kpis{grid-template-columns:1fr} .dash-canales{grid-template-columns:1fr} }
.dk-card  { background:#fff; border:1px solid var(--rs-gray-200); border-radius:var(--rs-radius); padding:1rem 1.15rem; display:flex; flex-direction:column; gap:.2rem; transition:.15s; }
.dk-card:hover { box-shadow:0 4px 18px rgba(0,0,0,.07); }
.dk-icon  { width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.05rem; margin-bottom:.3rem; flex-shrink:0; }
.dk-val   { font-size:1.6rem; font-weight:800; line-height:1; color:#0f172a; }
.dk-val.md{ font-size:1.25rem; }
.dk-lbl   { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em; color:var(--rs-text-muted); margin-top:.1rem; }
.dk-sub   { font-size:.71rem; color:var(--rs-text-muted); margin-top:.15rem; }
.dk-sep   { width:100%; height:1px; background:var(--rs-gray-100); margin:.45rem 0; }
.dk-row   { display:flex; align-items:center; justify-content:space-between; font-size:.78rem; }
.dk-row-lbl{ color:var(--rs-text-muted); }
.dk-row-val{ font-weight:700; }
/* Canal cards */
.canal-card { background:#fff; border:1px solid var(--rs-gray-200); border-radius:var(--rs-radius); padding:.9rem 1rem; border-top:3px solid; }
.canal-card .dk-val { font-size:1.15rem; }
/* Pipeline strip */
.dk-pipeline { background:#fff; border:1px solid var(--rs-gray-200); border-radius:var(--rs-radius); padding:.85rem 1.1rem; }
.dk-pipe-flow{ display:flex; gap:0; overflow-x:auto; padding:.2rem 0; }
.dk-pipe-step{ flex:1; min-width:64px; text-align:center; padding:.5rem .25rem; border-radius:8px; text-decoration:none; transition:.12s; border:1.5px solid transparent; }
.dk-pipe-step:hover { background:var(--rs-gray-100); }
.dk-pipe-count{ font-size:1.3rem; font-weight:800; line-height:1; }
.dk-pipe-lbl  { font-size:.6rem; font-weight:600; color:var(--rs-text-muted); text-transform:uppercase; letter-spacing:.03em; margin-top:.2rem; line-height:1.2; }
.dk-pipe-arr  { color:var(--rs-gray-300); display:flex; align-items:center; padding:0 .05rem; align-self:center; margin-top:-.5rem; font-size:.9rem; flex-shrink:0; }
/* Tendencia */
.dk-panel { background:#fff; border:1px solid var(--rs-gray-200); border-radius:var(--rs-radius); padding:.85rem 1.1rem; }
.dk-panel-title { font-size:.7rem; font-weight:700; text-transform:uppercase; letter-spacing:.06em; color:var(--rs-text-muted); margin-bottom:.8rem; }
.trend-chart  { display:flex; align-items:flex-end; gap:.45rem; height:110px; }
.trend-col    { flex:1; display:flex; flex-direction:column; align-items:center; gap:1px; min-width:28px; }
.trend-bars   { flex:1; width:100%; display:flex; flex-direction:column; justify-content:flex-end; gap:1px; }
.trend-bar    { width:100%; border-radius:2px 2px 0 0; min-height:2px; transition:height .4s; }
.trend-mes    { font-size:.6rem; color:var(--rs-text-muted); margin-top:4px; text-align:center; white-space:nowrap; }
/* Top colegios */
.top-row  { display:flex; align-items:center; gap:.5rem; padding:.35rem 0; border-bottom:1px solid var(--rs-gray-100); }
.top-row:last-child { border-bottom:none; }
.top-rank { width:20px; height:20px; border-radius:50%; font-size:.65rem; font-weight:700; display:flex; align-items:center; justify-content:center; flex-shrink:0; background:var(--rs-gray-100); color:var(--rs-text-muted); }
.top-rank.gold{ background:#fef9c3; color:#854d0e; }
.top-rank.silver{ background:#f1f5f9; color:#475569; }
.top-name { flex:1; font-size:.78rem; font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.top-val  { font-size:.75rem; font-weight:700; color:#16a34a; white-space:nowrap; }
.top-ops  { font-size:.63rem; color:var(--rs-text-muted); }
@media print {
  .filter-bar, .sidebar, .topbar, .no-print { display: none !important; }
  .print-btn { display: inline; }
  body { background: white; }
  .report-table { font-size: .72rem; }
}
</style>

<!-- Header -->
<div class="page-header">
  <div>
    <h4 class="page-header-title">&#x1F4CA; Reportes de Inventario</h4>
    <p class="page-header-sub">Genera reportes por referencia, importación, fechas o lotes</p>
  </div>
  <div class="d-flex gap-2 flex-wrap no-print">
    <a href="imprimir.php?<?= htmlspecialchars(http_build_query($_GET)) ?>" target="_blank"
       class="btn btn-danger btn-sm">
      <i class="bi bi-file-pdf me-1"></i>PDF / Imprimir
    </a>
    <a href="imprimir.php?<?= htmlspecialchars(http_build_query($_GET)) ?>#excel" target="_blank"
       class="btn btn-success btn-sm" onclick="this.href='imprimir.php?<?= htmlspecialchars(http_build_query($_GET)) ?>'; setTimeout(()=>{},100);">
      <i class="bi bi-file-earmark-excel me-1"></i>Excel
    </a>
    <a href="exportar.php?<?= htmlspecialchars(http_build_query($_GET)) ?>&salida=csv" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-filetype-csv me-1"></i>CSV
    </a>
  </div>
</div>

<!-- ── FILTROS ── -->
<div class="filter-bar no-print">
  <form method="GET" class="row g-3 align-items-end">

    <!-- Tipo de reporte -->
    <div class="col-12 col-md-3 filter-group">
      <label>Tipo de Reporte</label>
      <select name="tipo" class="form-select form-select-sm" onchange="this.form.submit()">
        <option value="dashboard"    <?= $tipo==='dashboard'    ?'selected':'' ?>>&#x1F4CA; Dashboard Ejecutivo</option>
        <option value="inventario"   <?= $tipo==='inventario'   ?'selected':'' ?>>&#x1F4E6; Inventario General</option>
        <option value="importacion"  <?= $tipo==='importacion'  ?'selected':'' ?>>&#x2708;&#xFE0F; Por Importación</option>
        <option value="fechas"       <?= $tipo==='fechas'       ?'selected':'' ?>>&#x1F4C5; Por Fechas (Movimientos)</option>
        <option value="lotes"        <?= $tipo==='lotes'        ?'selected':'' ?>>&#x1F5C2;&#xFE0F; Por Lotes / Categoría</option>
        <option value="valorizacion"    <?= $tipo==='valorizacion'    ?'selected':'' ?>>&#x1F4B0; Valorización</option>
        <option value="ventas_colegios" <?= $tipo==='ventas_colegios' ?'selected':'' ?>>&#x1F3EB; Ventas por Colegio</option>
        <option value="financiero"      <?= $tipo==='financiero'      ?'selected':'' ?>>&#x1F4C8; Financiero Consolidado</option>
      </select>
    </div>

    <!-- Filtros condicionales -->
    <?php if ($tipo === 'importacion'): ?>
    <div class="col-12 col-md-4 filter-group">
      <label>Pedido de Importación</label>
      <select name="pedido_id" class="form-select form-select-sm">
        <option value="">&mdash; Todos los pedidos &mdash;</option>
        <?php foreach ($pedidos as $ped): ?>
          <option value="<?= $ped['id'] ?>" <?= $pedidoId==$ped['id']?'selected':'' ?>>
            <?= htmlspecialchars($ped['codigo_pedido']) ?> &mdash; <?= $ped['fecha_pedido'] ?>
            (<?= ucfirst($ped['estado']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>

    <?php if (in_array($tipo, ['fechas'])): ?>
    <div class="col-6 col-md-2 filter-group">
      <label>Desde</label>
      <input type="date" name="desde" class="form-control form-control-sm" value="<?= $fechaDesde ?>">
    </div>
    <div class="col-6 col-md-2 filter-group">
      <label>Hasta</label>
      <input type="date" name="hasta" class="form-control form-control-sm" value="<?= $fechaHasta ?>">
    </div>
    <?php endif; ?>

    <?php if (in_array($tipo, ['inventario','lotes','fechas'])): ?>
    <div class="col-12 col-md-3 filter-group">
      <label>Categoría</label>
      <select name="cat_id" class="form-select form-select-sm">
        <option value="">&mdash; Todas las categorías &mdash;</option>
        <?php foreach ($categorias as $cat): ?>
          <option value="<?= $cat['id'] ?>" <?= $catId==$cat['id']?'selected':'' ?>>
            [<?= $cat['prefijo'] ?>] <?= htmlspecialchars($cat['nombre']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>

    <?php if ($tipo === 'lotes'): ?>
    <div class="col-12 col-md-3 filter-group">
      <label>Proveedor</label>
      <select name="prov_id" class="form-select form-select-sm">
        <option value="">&mdash; Todos los proveedores &mdash;</option>
        <?php foreach ($proveedores as $pv): ?>
          <option value="<?= $pv['id'] ?>" <?= $provId==$pv['id']?'selected':'' ?>>
            <?= htmlspecialchars($pv['nombre']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>

    <?php if ($tipo === 'ventas_colegios'): ?>
    <div class="col-6 col-md-2 filter-group">
      <label>Año</label>
      <select id="filtro-anio" class="form-select form-select-sm" onchange="aplicarAnio(this.value)">
        <option value="">— Año —</option>
        <?php for ($a = (int)date('Y'); $a >= (int)date('Y') - 4; $a--): ?>
          <option value="<?= $a ?>" <?= substr($fechaDesde,0,4)==$a?'selected':'' ?>><?= $a ?></option>
        <?php endfor; ?>
      </select>
    </div>
    <div class="col-6 col-md-2 filter-group">
      <label>Desde</label>
      <input type="date" id="filtro-desde" name="desde" class="form-control form-control-sm" value="<?= $fechaDesde ?>">
    </div>
    <div class="col-6 col-md-2 filter-group">
      <label>Hasta</label>
      <input type="date" id="filtro-hasta" name="hasta" class="form-control form-control-sm" value="<?= $fechaHasta ?>">
    </div>
    <div class="col-12 col-md-3 filter-group">
      <label>Colegio</label>
      <select name="colegio_id" class="form-select form-select-sm">
        <option value="">— Todos los colegios —</option>
        <?php foreach ($colegios_list as $col): ?>
          <option value="<?= $col['id'] ?>" <?= $colegioId==$col['id']?'selected':'' ?>><?= htmlspecialchars($col['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-md-3 filter-group">
      <label>Kit</label>
      <select name="kit_nombre" class="form-select form-select-sm">
        <option value="">— Todos los kits —</option>
        <?php foreach ($kits_list as $k): ?>
          <option value="<?= htmlspecialchars($k['nombre_kit']) ?>" <?= $kitNombre===$k['nombre_kit']?'selected':'' ?>><?= htmlspecialchars($k['nombre_kit']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2 filter-group">
      <label>Estado convenio</label>
      <select name="estado_conv" class="form-select form-select-sm">
        <option value="aprobado" <?= $estadoConv==='aprobado'?'selected':'' ?>>Aprobado</option>
        <option value="all"      <?= $estadoConv==='all'?'selected':''      ?>>Todos</option>
      </select>
    </div>
    <?php endif; ?>

    <?php if ($tipo === 'financiero'): ?>
    <div class="col-6 col-md-2 filter-group">
      <label>Año</label>
      <select id="filtro-anio" class="form-select form-select-sm" onchange="aplicarAnio(this.value)">
        <option value="">— Año —</option>
        <?php for ($a = (int)date('Y'); $a >= (int)date('Y') - 4; $a--): ?>
          <option value="<?= $a ?>" <?= substr($fechaDesde,0,4)==$a?'selected':'' ?>><?= $a ?></option>
        <?php endfor; ?>
      </select>
    </div>
    <div class="col-6 col-md-2 filter-group">
      <label>Desde</label>
      <input type="date" id="filtro-desde" name="desde" class="form-control form-control-sm" value="<?= $fechaDesde ?>">
    </div>
    <div class="col-6 col-md-2 filter-group">
      <label>Hasta</label>
      <input type="date" id="filtro-hasta" name="hasta" class="form-control form-control-sm" value="<?= $fechaHasta ?>">
    </div>
    <div class="col-12 col-md-3 filter-group">
      <label>Canal de Ingreso</label>
      <select name="fuente" class="form-select form-select-sm">
        <option value="all"       <?= $fuente==='all'?'selected':''       ?>>— Todos los canales —</option>
        <option value="tienda"    <?= $fuente==='tienda'?'selected':''    ?>>Tienda Online</option>
        <option value="convenios" <?= $fuente==='convenios'?'selected':'' ?>>Convenios Colegios</option>
        <option value="escuela"   <?= $fuente==='escuela'?'selected':''   ?>>Escuela / Matrículas</option>
      </select>
    </div>
    <?php endif; ?>

    <div class="col-auto filter-group">
      <label>&nbsp;</label>
      <button type="submit" class="btn btn-primary btn-sm d-block">
        <i class="bi bi-search me-1"></i>Generar
      </button>
    </div>
  </form>
</div>

<script>
function aplicarAnio(anio) {
  if (!anio) return;
  const d = document.getElementById('filtro-desde');
  const h = document.getElementById('filtro-hasta');
  if (d) d.value = anio + '-01-01';
  if (h) h.value = anio + '-12-31';
}
</script>

<!-- ── STATS CHIPS ── -->
<?php if (!empty($stats)): ?>
<div class="d-flex flex-wrap gap-2 mb-3 no-print">
  <?php if (isset($stats['total'])): ?>
    <span class="stat-chip bg-light text-dark border"><i class="bi bi-list me-1"></i><?= $stats['total'] ?> registros</span>
  <?php endif; ?>
  <?php if (isset($stats['rojos'])): ?>
    <span class="stat-chip" style="background:#fee2e2;color:#dc2626;"><span class="sem-dot sem-rojo me-1"></span><?= $stats['rojos'] ?> sin stock</span>
    <span class="stat-chip" style="background:#fef9c3;color:#b45309;"><span class="sem-dot sem-amarillo me-1"></span><?= $stats['amarillos'] ?> stock bajo</span>
    <span class="stat-chip" style="background:#dcfce7;color:#16a34a;"><span class="sem-dot sem-verde me-1"></span><?= $stats['verdes'] ?> OK</span>
  <?php endif; ?>
  <?php if (isset($stats['entradas'])): ?>
    <span class="stat-chip tipo-entrada"><i class="bi bi-arrow-down me-1"></i><?= $stats['entradas'] ?> entradas</span>
    <span class="stat-chip tipo-salida"><i class="bi bi-arrow-up me-1"></i><?= $stats['salidas'] ?> salidas</span>
    <span class="stat-chip tipo-ajuste"><i class="bi bi-sliders me-1"></i><?= $stats['ajustes'] ?> ajustes</span>
  <?php endif; ?>
  <?php if (isset($stats['total_costo'])): ?>
    <span class="stat-chip" style="background:#dbeafe;color:#1d4ed8;">Costo total: $<?= number_format($stats['total_costo'],0,',','.') ?> COP</span>
    <span class="stat-chip" style="background:#dcfce7;color:#15803d;">Venta total: $<?= number_format($stats['total_venta'],0,',','.') ?> COP</span>
    <span class="stat-chip" style="background:#faf5ff;color:#6b21a8;">Margen: $<?= number_format($stats['total_margen'],0,',','.') ?> COP</span>
  <?php endif; ?>
  <?php if (isset($stats['colegios'])): ?>
    <span class="stat-chip bg-light text-dark border"><i class="bi bi-building me-1"></i><?= $stats['colegios'] ?> colegios</span>
    <span class="stat-chip bg-light text-dark border"><i class="bi bi-list me-1"></i><?= $stats['lineas'] ?> líneas</span>
    <span class="stat-chip" style="background:#dbeafe;color:#1d4ed8;"><i class="bi bi-people me-1"></i><?= number_format($stats['estudiantes'],0,',','.') ?> estudiantes</span>
    <span class="stat-chip" style="background:#dcfce7;color:#15803d;">$<?= number_format($stats['total_valor'],0,',','.') ?> COP total</span>
  <?php endif; ?>
  <?php if (isset($stats['total_general'])): ?>
    <span class="stat-chip" style="background:#dbeafe;color:#1d4ed8;">Tienda: $<?= number_format($stats['total_tienda'],0,',','.') ?></span>
    <span class="stat-chip" style="background:#dcfce7;color:#15803d;">Convenios: $<?= number_format($stats['total_convenios'],0,',','.') ?></span>
    <span class="stat-chip" style="background:#faf5ff;color:#6b21a8;">Escuela: $<?= number_format($stats['total_escuela'],0,',','.') ?></span>
    <span class="stat-chip" style="background:#1e293b;color:#fff;"><strong>TOTAL: $<?= number_format($stats['total_general'],0,',','.') ?></strong></span>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- ── DASHBOARD EJECUTIVO ── -->
<?php if ($tipo === 'dashboard'): ?>
<?php
$anio_actual = date('Y');
$mes_label   = strftime('%B', mktime(0,0,0,date('m'),1,date('Y')));
$canal_cfg   = [
    'tienda'    => ['label'=>'Tienda Online',        'color'=>'#2563eb', 'icon'=>'bi-bag'],
    'convenios' => ['label'=>'Convenios Colegios',   'color'=>'#16a34a', 'icon'=>'bi-building'],
    'escuela'   => ['label'=>'Escuela / Matrículas', 'color'=>'#7c3aed', 'icon'=>'bi-mortarboard'],
];
$pipe_cfg = [
    'pendiente'  => ['lbl'=>'Pendiente',   'color'=>'#854d0e', 'key'=>'pendiente'],
    'en_proceso' => ['lbl'=>'En Proceso',  'color'=>'#1d4ed8', 'key'=>'en_proceso'],
    'despachado' => ['lbl'=>'Despachado',  'color'=>'#14532d', 'key'=>'despachado'],
    'entregado'  => ['lbl'=>'Entregado',   'color'=>'#475569', 'key'=>'entregado'],
];
?>

<!-- Fila 1: KPIs -->
<div class="dash-grid dash-kpis mb-3">

  <div class="dk-card">
    <div class="dk-icon" style="background:#dbeafe;color:#1d4ed8"><i class="bi bi-graph-up-arrow"></i></div>
    <div class="dk-val md">$<?= number_format((float)$d_revenue['total_mes']/1000000,2,',','.') ?>M</div>
    <div class="dk-lbl">Ingresos <?= ucfirst($mes_label) ?></div>
    <div class="dk-sep"></div>
    <div class="dk-row">
      <span class="dk-row-lbl">Año <?= $anio_actual ?></span>
      <span class="dk-row-val" style="color:#1d4ed8">$<?= number_format((float)$d_revenue['total_anio']/1000000,2,',','.') ?>M</span>
    </div>
  </div>

  <div class="dk-card">
    <div class="dk-icon" style="background:#dcfce7;color:#16a34a"><i class="bi bi-building"></i></div>
    <div class="dk-val"><?= (int)$d_convenios['activos'] ?></div>
    <div class="dk-lbl">Convenios activos <?= $anio_actual ?></div>
    <div class="dk-sep"></div>
    <div class="dk-row">
      <span class="dk-row-lbl">Pendientes</span>
      <span class="dk-row-val" style="color:#b45309"><?= (int)$d_convenios['pendientes'] ?></span>
    </div>
    <div class="dk-row">
      <span class="dk-row-lbl">Total firmados</span>
      <span class="dk-row-val"><?= (int)$d_convenios['total_anio'] ?></span>
    </div>
  </div>

  <div class="dk-card">
    <div class="dk-icon" style="background:#ffedd5;color:#9a3412"><i class="bi bi-box-seam"></i></div>
    <div class="dk-val"><?= (int)$d_pipeline['activos'] ?></div>
    <div class="dk-lbl">Pipeline tienda activo</div>
    <div class="dk-sep"></div>
    <div class="dk-row">
      <span class="dk-row-lbl">Pendientes</span>
      <span class="dk-row-val" style="color:#b45309"><?= (int)$d_pipeline['pendiente'] ?></span>
    </div>
    <div class="dk-row">
      <span class="dk-row-lbl">Pedidos año</span>
      <span class="dk-row-val"><?= (int)$d_pipeline['total_anio'] ?></span>
    </div>
  </div>

  <div class="dk-card">
    <div class="dk-icon" style="background:#faf5ff;color:#7c3aed"><i class="bi bi-patch-check-fill"></i></div>
    <div class="dk-val"><?= (int)$d_pipeline['entregado'] ?></div>
    <div class="dk-lbl">Entregados (total)</div>
    <div class="dk-sep"></div>
    <div class="dk-row">
      <span class="dk-row-lbl">Prom. entrega</span>
      <span class="dk-row-val" style="color:#7c3aed"><?= $d_pipeline['dias_promedio'] ? $d_pipeline['dias_promedio'].' días' : '—' ?></span>
    </div>
    <div class="dk-row">
      <span class="dk-row-lbl">Despachados</span>
      <span class="dk-row-val"><?= (int)$d_pipeline['despachado'] ?></span>
    </div>
  </div>

</div>

<!-- Fila 2: canales -->
<div class="dash-grid dash-canales mb-3">
  <?php foreach ($canal_cfg as $key => $canal):
    $v_mes  = (float)($d_revenue[$key.'_mes']  ?? 0);
    $v_anio = (float)($d_revenue[$key.'_anio'] ?? 0);
    $pct    = $d_revenue['total_anio'] > 0 ? round(($v_anio / $d_revenue['total_anio']) * 100) : 0;
  ?>
  <div class="canal-card" style="border-top-color:<?= $canal['color'] ?>">
    <div style="display:flex;align-items:center;gap:.5rem;margin-bottom:.6rem">
      <div class="dk-icon" style="background:<?= $canal['color'] ?>18;color:<?= $canal['color'] ?>;width:28px;height:28px;font-size:.85rem">
        <i class="bi <?= $canal['icon'] ?>"></i>
      </div>
      <span style="font-size:.73rem;font-weight:700;color:var(--rs-text-muted);text-transform:uppercase;letter-spacing:.04em"><?= $canal['label'] ?></span>
    </div>
    <div class="dk-val md">$<?= number_format($v_mes/1000000,2,',','.') ?>M</div>
    <div class="dk-sub">este mes</div>
    <div class="dk-sep"></div>
    <div class="dk-row" style="margin-bottom:.3rem">
      <span class="dk-row-lbl">Año <?= $anio_actual ?></span>
      <span class="dk-row-val" style="color:<?= $canal['color'] ?>">$<?= number_format($v_anio/1000000,2,',','.') ?>M</span>
    </div>
    <div style="height:5px;background:var(--rs-gray-100);border-radius:3px;overflow:hidden">
      <div style="height:100%;width:<?= $pct ?>%;background:<?= $canal['color'] ?>;border-radius:3px"></div>
    </div>
    <div style="font-size:.63rem;color:var(--rs-text-muted);margin-top:.2rem"><?= $pct ?>% del ingreso total</div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Pipeline tienda -->
<div class="dk-pipeline mb-3">
  <div class="dk-panel-title"><i class="bi bi-diagram-3 me-1"></i>Pipeline tienda — estado actual</div>
  <div class="dk-pipe-flow">
    <?php $psteps = [
      ['lbl'=>'Pendiente',   'color'=>'#854d0e', 'val'=>(int)$d_pipeline['pendiente'],  'url'=>APP_URL.'/modules/pedidos_tienda/?estado=pendiente'],
      ['lbl'=>'En proceso',  'color'=>'#1d4ed8', 'val'=>(int)$d_pipeline['en_proceso'], 'url'=>APP_URL.'/modules/pedidos_tienda/?estado=aprobado'],
      ['lbl'=>'Despachado',  'color'=>'#14532d', 'val'=>(int)$d_pipeline['despachado'], 'url'=>APP_URL.'/modules/pedidos_tienda/?estado=despachado'],
      ['lbl'=>'Entregado',   'color'=>'#475569', 'val'=>(int)$d_pipeline['entregado'],  'url'=>APP_URL.'/modules/pedidos_tienda/?estado=entregado'],
    ];
    foreach ($psteps as $i => $ps): ?>
    <a href="<?= htmlspecialchars($ps['url']) ?>" class="dk-pipe-step" target="_blank" style="color:<?= $ps['color'] ?>">
      <div class="dk-pipe-count" style="color:<?= $ps['val'] > 0 ? $ps['color'] : '#cbd5e1' ?>"><?= $ps['val'] ?></div>
      <div class="dk-pipe-lbl"><?= $ps['lbl'] ?></div>
    </a>
    <?php if ($i < count($psteps)-1): ?><div class="dk-pipe-arr">›</div><?php endif; ?>
    <?php endforeach; ?>
    <div style="flex:1;min-width:1px"></div>
    <div class="dk-pipe-step" style="color:#7c3aed;border-color:transparent">
      <div class="dk-pipe-count" style="color:#7c3aed;font-size:1rem"><?= (int)$d_pipeline['total_anio'] ?></div>
      <div class="dk-pipe-lbl">Total año</div>
    </div>
  </div>
</div>

<!-- Tendencia + Top colegios -->
<div class="dash-grid dash-bottom mb-3">

  <!-- Tendencia últimos 6 meses -->
  <div class="dk-panel">
    <div class="dk-panel-title"><i class="bi bi-bar-chart-line me-1"></i>Ingresos — últimos 6 meses</div>
    <?php if (empty($d_tend)): ?>
      <p class="text-muted small mb-0">Sin datos de ingresos registrados.</p>
    <?php else: ?>
    <div style="display:flex;gap:.3rem;margin-bottom:.5rem">
      <?php foreach ($canal_cfg as $k => $c): ?>
      <span style="font-size:.64rem;font-weight:600;color:<?= $c['color'] ?>">
        <span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:<?= $c['color'] ?>;margin-right:2px"></span>
        <?= $c['label'] ?>
      </span>
      <?php endforeach; ?>
    </div>
    <div class="trend-chart">
      <?php foreach (array_keys($d_meses) as $mes):
        $vals  = $d_tend[$mes] ?? [];
        $total = array_sum($vals);
        $pct_h = $d_max_mes > 0 ? max(4, round(($total/$d_max_mes)*100)) : 4;
        $dt    = DateTime::createFromFormat('Y-m', $mes);
        $label = $dt ? strtoupper(substr($dt->format('M'), 0, 3)) : $mes;
      ?>
      <div class="trend-col">
        <div style="flex:1;display:flex;flex-direction:column;justify-content:flex-end;width:100%;gap:1px">
          <?php foreach (['escuela','convenios','tienda'] as $f):
            $v = $vals[$f] ?? 0;
            $ph = $total > 0 ? max(2, round(($v/$total)*$pct_h)) : 0;
            $col = $canal_cfg[$f]['color'] ?? '#94a3b8';
          ?>
          <?php if ($ph > 0): ?>
          <div title="<?= $canal_cfg[$f]['label'] ?>: $<?= number_format($v/1000000,2,',','.') ?>M"
               style="height:<?= $ph ?>px;background:<?= $col ?>;border-radius:2px 2px 0 0;opacity:.85"></div>
          <?php endif; ?>
          <?php endforeach; ?>
        </div>
        <div class="trend-mes"><?= $label ?></div>
        <div style="font-size:.58rem;color:var(--rs-text-muted);text-align:center">$<?= number_format($total/1000000,1,',','.') ?>M</div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Top colegios -->
  <div class="dk-panel">
    <div class="dk-panel-title"><i class="bi bi-building me-1"></i>Top colegios — <?= $anio_actual ?></div>
    <?php if (empty($d_top_colegios)): ?>
      <p class="text-muted small mb-0">Sin ventas registradas este año.</p>
    <?php else:
      $max_c = max(array_column($d_top_colegios,'total')) ?: 1;
      foreach ($d_top_colegios as $i => $c):
    ?>
    <div class="top-row">
      <div class="top-rank <?= $i===0?'gold':($i===1?'silver':'') ?>"><?= $i+1 ?></div>
      <div style="flex:1;min-width:0">
        <div class="top-name" title="<?= htmlspecialchars($c['nombre']) ?>"><?= htmlspecialchars($c['nombre']) ?></div>
        <div style="height:3px;background:var(--rs-gray-100);border-radius:2px;margin-top:3px;overflow:hidden">
          <div style="height:100%;width:<?= round(($c['total']/$max_c)*100) ?>%;background:#16a34a;border-radius:2px"></div>
        </div>
      </div>
      <div style="text-align:right;min-width:80px">
        <div class="top-val">$<?= number_format($c['total'],0,',','.') ?></div>
        <div class="top-ops"><?= $c['operaciones'] ?> operaciones</div>
      </div>
    </div>
    <?php endforeach; endif; ?>
  </div>

</div>
<?php endif; // fin dashboard ?>

<!-- ── TABLA PRINCIPAL ── -->
<div class="section-card p-0" style="overflow:hidden;">
  <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
    <div>
      <span class="fw-bold"><?= $titulo_reporte ?></span>
      <span class="text-muted small ms-2">&mdash; <?= date('d/m/Y H:i') ?></span>
    </div>
    <span class="badge bg-secondary"><?= count($datos) ?> filas</span>
  </div>

  <?php if (empty($datos)): ?>
    <div class="text-center text-muted py-5">
      <i class="bi bi-inbox fs-2 d-block mb-2"></i>
      No hay datos para los filtros seleccionados.
    </div>
  <?php else: ?>
  <div class="table-responsive">
  <table class="table table-hover report-table mb-0">
    <thead>
      <tr>
        <?php foreach ($columnas as $col): ?>
          <th><?= $col ?></th>
        <?php endforeach; ?>
      </tr>
    </thead>
    <tbody>

      <?php if ($tipo === 'inventario'): ?>
        <?php foreach ($datos as $r): ?>
        <tr>
          <td><code style="font-size:.75rem;"><?= htmlspecialchars($r['codigo']) ?></code></td>
          <td class="fw-semibold"><?= htmlspecialchars($r['nombre']) ?></td>
          <td><span class="cat-pill" style="background:<?= $r['cat_color'] ?>;"><?= htmlspecialchars($r['categoria']) ?></span></td>
          <td class="fw-bold text-center"><?= $r['stock_actual'] ?></td>
          <td class="text-center text-muted"><?= $r['stock_minimo'] ?></td>
          <td class="text-center text-muted"><?= $r['stock_maximo'] ?></td>
          <td class="text-center"><span class="sem-dot sem-<?= $r['semaforo'] ?>"></span> <?= ucfirst($r['semaforo']) ?></td>
          <td class="text-muted small"><?= htmlspecialchars($r['ubicacion'] ?? '&mdash;') ?></td>
          <td class="text-end"><?= $r['costo_real_cop'] ? '$'.number_format($r['costo_real_cop'],0,',','.') : '&mdash;' ?></td>
          <td class="text-end"><?= $r['precio_venta_cop'] ? '$'.number_format($r['precio_venta_cop'],0,',','.') : '&mdash;' ?></td>
        </tr>
        <?php endforeach; ?>

      <?php elseif ($tipo === 'importacion'): ?>
        <?php
        $pedido_actual = '';
        foreach ($datos as $r):
          if ($r['codigo_pedido'] !== $pedido_actual):
            $pedido_actual = $r['codigo_pedido'];
        ?>
        <tr style="background:#f8fafc;">
          <td colspan="12" class="fw-bold small" style="color:#1e293b;padding:.4rem .75rem;">
            <i class="bi bi-airplane me-1 text-primary"></i>
            Pedido: <?= htmlspecialchars($r['codigo_pedido']) ?>
            &nbsp;·&nbsp; Fecha: <?= $r['fecha_pedido'] ?>
            &nbsp;·&nbsp; TRM: $<?= number_format($r['tasa_cambio_usd_cop'],0,',','.') ?>
            &nbsp;·&nbsp; Estado: <span class="badge bg-<?= $r['estado']==='liquidado'?'success':'warning' ?>"><?= ucfirst($r['estado']) ?></span>
          </td>
        </tr>
        <?php endif; ?>
        <tr>
          <td><code style="font-size:.75rem;"><?= htmlspecialchars($r['codigo']) ?></code></td>
          <td class="fw-semibold"><?= htmlspecialchars($r['nombre']) ?></td>
          <td><?= htmlspecialchars($r['categoria']) ?></td>
          <td class="text-center"><?= $r['cantidad'] ?></td>
          <td class="text-end">$<?= number_format($r['precio_unit_usd'],4,',','.') ?></td>
          <td class="text-end">$<?= number_format($r['subtotal_usd'],2,',','.') ?></td>
          <td class="text-end"><?= number_format($r['peso_unit_gramos'],1,',','.') ?>g</td>
          <td class="text-end"><?= $r['pct_peso'] ? number_format($r['pct_peso']*100,2,',','.').'%' : '&mdash;' ?></td>
          <td class="text-end"><?= $r['flete_asignado_cop'] ? '$'.number_format($r['flete_asignado_cop'],0,',','.') : '&mdash;' ?></td>
          <td class="text-end"><?= $r['arancel_asignado_cop'] ? '$'.number_format($r['arancel_asignado_cop'],0,',','.') : '&mdash;' ?></td>
          <td class="text-end"><?= $r['iva_asignado_cop'] ? '$'.number_format($r['iva_asignado_cop'],0,',','.') : '&mdash;' ?></td>
          <td class="text-end fw-bold"><?= $r['costo_unit_final_cop'] ? '$'.number_format($r['costo_unit_final_cop'],0,',','.') : '&mdash;' ?></td>
        </tr>
        <?php endforeach; ?>

      <?php elseif ($tipo === 'fechas'): ?>
        <?php
        $fecha_actual = '';
        foreach ($datos as $r):
          if ($r['fecha'] !== $fecha_actual):
            $fecha_actual = $r['fecha'];
        ?>
        <tr style="background:#f8fafc;">
          <td colspan="11" class="fw-semibold small" style="color:#475569;padding:.35rem .75rem;">
            <i class="bi bi-calendar3 me-1"></i><?= date('l d/m/Y', strtotime($r['fecha'])) ?>
          </td>
        </tr>
        <?php endif; ?>
        <tr>
          <td class="text-muted small"><?= date('H:i', strtotime($r['fecha'])) ?></td>
          <td><code style="font-size:.75rem;"><?= htmlspecialchars($r['codigo']) ?></code></td>
          <td class="fw-semibold"><?= htmlspecialchars($r['nombre']) ?></td>
          <td><?= htmlspecialchars($r['categoria']) ?></td>
          <td><span class="tipo-badge tipo-<?= $r['tipo'] ?>"><?= ucfirst($r['tipo']) ?></span></td>
          <td class="text-center fw-bold <?= $r['cantidad']>0?'text-success':'text-danger' ?>">
            <?= $r['cantidad']>0?'+':'' ?><?= $r['cantidad'] ?>
          </td>
          <td class="text-center text-muted"><?= $r['stock_antes'] ?></td>
          <td class="text-center fw-semibold"><?= $r['stock_despues'] ?></td>
          <td class="text-muted small"><?= htmlspecialchars($r['referencia'] ?? '&mdash;') ?></td>
          <td class="text-muted small"><?= htmlspecialchars($r['motivo'] ?? '&mdash;') ?></td>
          <td class="text-muted small"><?= htmlspecialchars($r['usuario'] ?? '&mdash;') ?></td>
        </tr>
        <?php endforeach; ?>

      <?php elseif ($tipo === 'lotes'): ?>
        <?php
        $cat_actual = '';
        $subtotal_stock = 0; $subtotal_valor = 0;
        foreach ($datos as $r):
          if ($r['categoria'] !== $cat_actual):
            if ($cat_actual !== '') {
        ?>
        <tr style="background:#f0fdf4;">
          <td colspan="7" class="text-end fw-bold small" style="color:#166534;">
            Subtotal: <?= number_format($subtotal_stock,0,',','.') ?> uds &mdash; $<?= number_format($subtotal_valor,0,',','.') ?> COP
          </td>
          <td></td>
        </tr>
        <?php } $cat_actual = $r['categoria']; $subtotal_stock = 0; $subtotal_valor = 0; ?>
        <tr style="background:#f8fafc;">
          <td colspan="8" class="fw-bold small" style="color:#1e293b;padding:.4rem .75rem;">
            <span class="cat-pill me-1" style="background:<?= $r['cat_color'] ?>;"><?= htmlspecialchars($r['prefijo']) ?></span>
            <?= htmlspecialchars($r['categoria']) ?>
          </td>
        </tr>
        <?php endif;
          $subtotal_stock += $r['stock_actual'];
          $subtotal_valor += $r['valor_total'];
        ?>
        <tr>
          <td><code style="font-size:.75rem;"><?= htmlspecialchars($r['codigo']) ?></code></td>
          <td class="fw-semibold"><?= htmlspecialchars($r['nombre']) ?></td>
          <td class="text-center fw-bold"><?= $r['stock_actual'] ?></td>
          <td class="text-center text-muted"><?= $r['stock_minimo'] ?></td>
          <td class="text-center"><span class="sem-dot sem-<?= $r['semaforo'] ?>"></span> <?= ucfirst($r['semaforo']) ?></td>
          <td class="text-end"><?= $r['costo_real_cop'] ? '$'.number_format($r['costo_real_cop'],2,',','.') : '&mdash;' ?></td>
          <td class="text-end fw-semibold"><?= $r['valor_total'] ? '$'.number_format($r['valor_total'],0,',','.') : '&mdash;' ?></td>
          <td class="text-muted small"><?= htmlspecialchars($r['proveedor'] ?? '&mdash;') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if ($cat_actual !== ''): ?>
        <tr style="background:#f0fdf4;">
          <td colspan="7" class="text-end fw-bold small" style="color:#166534;">
            Subtotal: <?= number_format($subtotal_stock,0,',','.') ?> uds &mdash; $<?= number_format($subtotal_valor,0,',','.') ?> COP
          </td>
          <td></td>
        </tr>
        <?php endif; ?>

      <?php elseif ($tipo === 'valorizacion'): ?>
        <?php foreach ($datos as $r): ?>
        <tr>
          <td><span class="cat-pill" style="background:<?= $r['cat_color'] ?>;"><?= htmlspecialchars($r['categoria']) ?></span></td>
          <td class="text-center"><?= $r['num_elementos'] ?></td>
          <td class="text-center fw-bold"><?= number_format($r['stock_total'],0,',','.') ?></td>
          <td class="text-end">$<?= number_format($r['valor_costo'],0,',','.') ?></td>
          <td class="text-end text-success fw-semibold">$<?= number_format($r['valor_venta'],0,',','.') ?></td>
          <td class="text-end fw-bold" style="color:#6b21a8;">$<?= number_format($r['margen'],0,',','.') ?></td>
        </tr>
        <?php endforeach; ?>
        <!-- Totales -->
        <tr style="background:#1e293b;color:#fff;">
          <td class="fw-bold">TOTAL</td>
          <td class="text-center fw-bold"><?= array_sum(array_column($datos,'num_elementos')) ?></td>
          <td class="text-center fw-bold"><?= number_format(array_sum(array_column($datos,'stock_total')),0,',','.') ?></td>
          <td class="text-end fw-bold">$<?= number_format($stats['total_costo'],0,',','.') ?></td>
          <td class="text-end fw-bold">$<?= number_format($stats['total_venta'],0,',','.') ?></td>
          <td class="text-end fw-bold">$<?= number_format($stats['total_margen'],0,',','.') ?></td>
        </tr>

      <?php elseif ($tipo === 'ventas_colegios'): ?>
        <?php
        $col_actual = ''; $sub_est = 0; $sub_val = 0;
        foreach ($datos as $r):
          if ($r['colegio'] !== $col_actual):
            if ($col_actual !== ''): ?>
        <tr style="background:#f0fdf4;">
          <td colspan="11" class="text-end fw-bold small" style="color:#166534;padding:.35rem .75rem;">
            Subtotal <?= htmlspecialchars($col_actual) ?>:
            <?= number_format($sub_est,0,',','.') ?> uds. &mdash;
            $<?= number_format($sub_val,0,',','.') ?> COP
          </td>
        </tr>
        <?php endif;
            $col_actual = $r['colegio']; $sub_est = 0; $sub_val = 0; ?>
        <tr style="background:#f8fafc;">
          <td colspan="11" class="fw-bold small" style="color:#1e293b;padding:.4rem .75rem;">
            <i class="bi bi-building me-1 text-primary"></i>
            <?= htmlspecialchars($r['colegio']) ?>
            <?php if ($r['ciudad']): ?><span class="text-muted fw-normal"> · <?= htmlspecialchars($r['ciudad']) ?></span><?php endif; ?>
          </td>
        </tr>
        <?php endif;
          $sub_est += $r['num_estudiantes'];
          $sub_val += $r['valor_linea'];
        ?>
        <?php $es_tienda = ($r['fuente'] ?? 'convenio') === 'tienda'; ?>
        <tr>
          <td class="fw-semibold"><?= htmlspecialchars($r['colegio']) ?></td>
          <td class="text-muted small"><?= htmlspecialchars($r['ciudad'] ?? '&mdash;') ?></td>
          <td><span class="badge bg-light text-secondary border"><?= htmlspecialchars($r['tipo_colegio'] ?? '&mdash;') ?></span></td>
          <td class="fw-semibold"><?= htmlspecialchars($r['kit'] ?? '&mdash;') ?></td>
          <td class="text-muted small"><?= htmlspecialchars($r['nombre_curso'] ?? '&mdash;') ?></td>
          <td class="text-center fw-bold"><?= number_format($r['num_estudiantes'],0,',','.') ?></td>
          <td class="text-end"><?= $r['valor_kit'] ? '$'.number_format($r['valor_kit'],0,',','.') : '&mdash;' ?></td>
          <td class="text-end fw-semibold"><?= $r['valor_linea'] ? '$'.number_format($r['valor_linea'],0,',','.') : '&mdash;' ?></td>
          <td>
            <span class="badge" style="font-size:.62rem;padding:.2rem .5rem;background:<?= $es_tienda?'#dbeafe':'#dcfce7' ?>;color:<?= $es_tienda?'#1d4ed8':'#16a34a' ?>;">
              <?= $es_tienda ? 'Tienda' : 'Conv.' ?>
            </span>
          </td>
          <td class="text-muted small"><?= htmlspecialchars($r['codigo_convenio'] ?? '&mdash;') ?></td>
          <td class="text-muted small"><?= $r['fecha_convenio'] ? date('d/m/Y', strtotime($r['fecha_convenio'])) : '&mdash;' ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if ($col_actual): ?>
        <tr style="background:#f0fdf4;">
          <td colspan="11" class="text-end fw-bold small" style="color:#166534;padding:.35rem .75rem;">
            Subtotal <?= htmlspecialchars($col_actual) ?>:
            <?= number_format($sub_est,0,',','.') ?> uds. &mdash;
            $<?= number_format($sub_val,0,',','.') ?> COP
          </td>
        </tr>
        <tr style="background:#1e293b;color:#fff;">
          <td colspan="5" class="fw-bold">TOTAL</td>
          <td class="text-center fw-bold"><?= number_format($stats['estudiantes'],0,',','.') ?></td>
          <td></td>
          <td class="text-end fw-bold">$<?= number_format($stats['total_valor'],0,',','.') ?></td>
          <td colspan="3"></td>
        </tr>
        <?php endif; ?>

      <?php elseif ($tipo === 'financiero'): ?>
        <?php
        $fuentes_label = ['tienda'=>'Tienda Online','convenios'=>'Convenios Colegios','escuela'=>'Escuela / Matrículas'];
        $fuente_colors = ['tienda'=>'#2563eb','convenios'=>'#16a34a','escuela'=>'#7c3aed'];
        $mes_actual = '';
        foreach ($datos as $r):
          if ($r['mes'] !== $mes_actual):
            $mes_actual = $r['mes'];
        ?>
        <tr style="background:#f8fafc;">
          <td colspan="5" class="fw-bold small" style="color:#1e293b;padding:.4rem .75rem;">
            <i class="bi bi-calendar3 me-1 text-primary"></i>
            <?= date('F Y', strtotime($r['mes'].'-01')) ?>
          </td>
        </tr>
        <?php endif;
          $fc = $fuente_colors[$r['fuente']] ?? '#64748b';
        ?>
        <tr>
          <td class="text-muted small"><?= date('M Y', strtotime($r['mes'].'-01')) ?></td>
          <td>
            <span class="badge" style="background:<?= $fc ?>22;color:<?= $fc ?>;border:1px solid <?= $fc ?>44;">
              <?= $fuentes_label[$r['fuente']] ?? ucfirst($r['fuente']) ?>
            </span>
          </td>
          <td class="text-center"><?= number_format($r['cantidad'],0,',','.') ?></td>
          <td class="text-end fw-semibold">$<?= number_format($r['total_cop'],0,',','.') ?></td>
          <td class="text-end text-muted"><?= $r['pct'] ?>%</td>
        </tr>
        <?php endforeach; ?>
        <tr style="background:#1e293b;color:#fff;">
          <td colspan="2" class="fw-bold">TOTAL</td>
          <td class="text-center fw-bold"><?= number_format(array_sum(array_column($datos,'cantidad')),0,',','.') ?></td>
          <td class="text-end fw-bold">$<?= number_format($stats['total_general'],0,',','.') ?></td>
          <td class="text-end">100%</td>
        </tr>

      <?php endif; ?>

    </tbody>
  </table>
  </div>
  <?php endif; ?>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
