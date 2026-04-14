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
$tipo       = $_GET['tipo']      ?? 'inventario';
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
