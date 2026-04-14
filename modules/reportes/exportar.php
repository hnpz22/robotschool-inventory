<?php
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
$colegioId  = (int)($_GET['colegio_id']  ?? 0);
$kitNombre  = trim($_GET['kit_nombre']   ?? '');
$fuente     = $_GET['fuente']            ?? 'all';
$estadoConv = $_GET['estado_conv']       ?? 'aprobado';

if (in_array($tipo, ['ventas_colegios','financiero']) && !isset($_GET['desde'])) {
    $fechaDesde = date('Y-01-01');
    $fechaHasta = date('Y-m-d');
}

$datos = [];
$columnas = [];
$nombre_archivo = 'reporte_' . $tipo . '_' . date('Y-m-d');

switch ($tipo) {
    case 'inventario':
        $columnas = ['Codigo','Elemento','Categoria','Stock Actual','Stock Min','Stock Max','Semaforo','Ubicacion','Costo COP','Precio COP'];
        $where = "WHERE e.activo=1";
        if ($catId) $where .= " AND e.categoria_id=$catId";
        $datos = $db->query("
            SELECT e.codigo, e.nombre, c.nombre AS categoria,
                   e.stock_actual, e.stock_minimo, e.stock_maximo,
                   e.ubicacion, e.costo_real_cop, e.precio_venta_cop,
                   CASE WHEN e.stock_actual<=0 THEN 'rojo' WHEN e.stock_actual<=e.stock_minimo THEN 'amarillo' WHEN e.stock_actual>=e.stock_maximo THEN 'azul' ELSE 'verde' END AS semaforo
            FROM elementos e JOIN categorias c ON c.id=e.categoria_id
            $where ORDER BY c.nombre, e.nombre
        ")->fetchAll();
        break;

    case 'importacion':
        $columnas = ['Pedido','Fecha','Codigo','Elemento','Categoria','Cantidad','Precio USD','Subtotal USD','Peso g','Pct Peso','Flete COP','Arancel COP','IVA COP','Costo Unit Final COP'];
        $where = "WHERE 1=1";
        if ($pedidoId) $where .= " AND pi.pedido_id=$pedidoId";
        $datos = $db->query("
            SELECT p.codigo_pedido, p.fecha_pedido, e.codigo, e.nombre, c.nombre AS categoria,
                   pi.cantidad, pi.precio_unit_usd, pi.subtotal_usd,
                   pi.peso_unit_gramos, pi.pct_peso,
                   pi.flete_asignado_cop, pi.arancel_asignado_cop,
                   pi.iva_asignado_cop, pi.costo_unit_final_cop
            FROM pedido_items pi
            JOIN elementos e ON e.id=pi.elemento_id
            JOIN categorias c ON c.id=e.categoria_id
            JOIN pedidos_importacion p ON p.id=pi.pedido_id
            $where ORDER BY p.fecha_pedido DESC, e.nombre
        ")->fetchAll();
        break;

    case 'fechas':
        $columnas = ['Fecha','Hora','Codigo','Elemento','Categoria','Tipo','Cantidad','Stock Antes','Stock Despues','Referencia','Motivo','Usuario'];
        $where = "WHERE DATE(m.created_at) BETWEEN '$fechaDesde' AND '$fechaHasta'";
        if ($catId) $where .= " AND c.id=$catId";
        $datos = $db->query("
            SELECT DATE(m.created_at) AS fecha, TIME(m.created_at) AS hora,
                   e.codigo, e.nombre, c.nombre AS categoria,
                   m.tipo, m.cantidad, m.stock_antes, m.stock_despues,
                   m.referencia, m.motivo, u.nombre AS usuario
            FROM movimientos m
            JOIN elementos e ON e.id=m.elemento_id
            JOIN categorias c ON c.id=e.categoria_id
            LEFT JOIN usuarios u ON u.id=m.usuario_id
            $where ORDER BY m.created_at DESC
        ")->fetchAll();
        break;

    case 'lotes':
        $columnas = ['Categoria','Prefijo','Codigo','Elemento','Stock','Stock Min','Semaforo','Costo Unit COP','Valor Total COP','Proveedor'];
        $where = "WHERE e.activo=1";
        if ($catId) $where .= " AND e.categoria_id=$catId";
        if ($provId) $where .= " AND e.proveedor_id=$provId";
        $datos = $db->query("
            SELECT c.nombre AS categoria, c.prefijo, e.codigo, e.nombre,
                   e.stock_actual, e.stock_minimo, e.costo_real_cop,
                   (e.stock_actual * e.costo_real_cop) AS valor_total,
                   pv.nombre AS proveedor,
                   CASE WHEN e.stock_actual<=0 THEN 'rojo' WHEN e.stock_actual<=e.stock_minimo THEN 'amarillo' WHEN e.stock_actual>=e.stock_maximo THEN 'azul' ELSE 'verde' END AS semaforo
            FROM elementos e
            JOIN categorias c ON c.id=e.categoria_id
            LEFT JOIN proveedores pv ON pv.id=e.proveedor_id
            $where ORDER BY c.nombre, e.nombre
        ")->fetchAll();
        break;

    case 'valorizacion':
        $columnas = ['Categoria','Num Elementos','Stock Total','Valor Costo COP','Valor Venta COP','Margen COP'];
        $datos = $db->query("
            SELECT c.nombre AS categoria, COUNT(e.id) AS num_elementos,
                   SUM(e.stock_actual) AS stock_total,
                   SUM(e.stock_actual * e.costo_real_cop)   AS valor_costo,
                   SUM(e.stock_actual * e.precio_venta_cop) AS valor_venta,
                   SUM(e.stock_actual * (e.precio_venta_cop - e.costo_real_cop)) AS margen
            FROM elementos e JOIN categorias c ON c.id=e.categoria_id
            WHERE e.activo=1 GROUP BY c.id ORDER BY valor_costo DESC
        ")->fetchAll();
        break;

    case 'ventas_colegios':
        $columnas = ['Colegio','Ciudad','Tipo','Kit','Curso','Cant./Est.','Val Kit COP','Val Linea COP','Canal','Convenio/Ref','Fecha','Estado'];
        $conds = [];
        if ($estadoConv !== 'all') $conds[] = "estado_convenio = " . $db->quote($estadoConv);
        if ($colegioId)            $conds[] = "colegio_id = $colegioId";
        if ($kitNombre)            $conds[] = "kit = " . $db->quote($kitNombre);
        if ($fechaDesde)           $conds[] = "fecha_convenio >= '$fechaDesde'";
        if ($fechaHasta)           $conds[] = "fecha_convenio <= '$fechaHasta'";
        $where = $conds ? ('WHERE ' . implode(' AND ', $conds)) : '';
        $datos = $db->query("SELECT colegio, ciudad, tipo_colegio, kit, nombre_curso, num_estudiantes, valor_kit, valor_linea, fuente, codigo_convenio, fecha_convenio, estado_convenio FROM v_ventas_colegios $where ORDER BY colegio, fecha_convenio DESC, kit")->fetchAll();
        break;

    case 'financiero':
        $columnas = ['Mes','Canal','Operaciones','Total COP'];
        $mesDesde = substr($fechaDesde, 0, 7);
        $mesHasta = substr($fechaHasta, 0, 7);
        $conds = ["mes BETWEEN '$mesDesde' AND '$mesHasta'"];
        if ($fuente !== 'all') $conds[] = "fuente = " . $db->quote($fuente);
        $where = 'WHERE ' . implode(' AND ', $conds);
        $datos = $db->query("SELECT mes, fuente, cantidad, total_cop FROM v_ingresos_mensuales $where ORDER BY mes DESC, fuente")->fetchAll();
        break;
}

// ── Enviar CSV ──
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $nombre_archivo . '.csv"');
header('Pragma: no-cache');

// BOM para que Excel abra correctamente con UTF-8
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// Cabecera: metadata del reporte
fputcsv($out, ['ROBOTSchool Inventory - Reporte: ' . strtoupper($tipo)], ';');
fputcsv($out, ['Generado:', date('d/m/Y H:i'), 'Filtros:', "desde=$fechaDesde hasta=$fechaHasta colegio=$colegioId kit=$kitNombre fuente=$fuente cat=$catId prov=$provId pedido=$pedidoId"], ';');
fputcsv($out, [], ';');

// Columnas
fputcsv($out, $columnas, ';');

// Datos
foreach ($datos as $row) {
    fputcsv($out, array_values($row), ';');
}

fclose($out);
exit;
