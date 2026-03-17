<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();

$db         = Database::get();
$pageTitle  = 'Pedidos Tienda';
$activeMenu = 'pedidos_tienda';
$error = $success = '';

// Detectar columnas opcionales de la migración 002
$colAprobado = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tienda_pedidos'
    AND COLUMN_NAME='aprobado_por'")->fetchColumn();

// ── Labels y colores de estado ──
$ESTADOS = [
    'pendiente'       => ['label'=>'Pendiente',          'bg'=>'#fef9c3', 'txt'=>'#854d0e'],
    'aprobado'        => ['label'=>'Aprobado',            'bg'=>'#dbeafe', 'txt'=>'#1d4ed8'],
    'en_produccion'   => ['label'=>'En producci&oacute;n','bg'=>'#ffedd5', 'txt'=>'#9a3412'],
    'listo_produccion'=> ['label'=>'Listo producci&oacute;n','bg'=>'#d1fae5','txt'=>'#065f46'],
    'en_alistamiento' => ['label'=>'En alistamiento',     'bg'=>'#ede9fe', 'txt'=>'#5b21b6'],
    'despachado'      => ['label'=>'Despachado',          'bg'=>'#bbf7d0', 'txt'=>'#14532d'],
    'entregado'       => ['label'=>'Entregado',           'bg'=>'#f1f5f9', 'txt'=>'#475569'],
    'cancelado'       => ['label'=>'Cancelado',           'bg'=>'#fee2e2', 'txt'=>'#991b1b'],
];

// ── Helpers ──
function rbs_dias(string $fecha): int {
    try {
        if (preg_match('#(\d{2})/(\d{2})/(\d{4})#', $fecha, $m))
            return max(0,(int)floor((time()-mktime(0,0,0,(int)$m[1],(int)$m[2],(int)$m[3]))/86400));
        return max(0,(int)floor((time()-strtotime($fecha))/86400));
    } catch (Exception $e) { return 0; }
}
function rbs_semaforo(string $fecha, string $estado): string {
    if (in_array($estado, ['entregado','cancelado','despachado'])) return 'completado';
    $d = rbs_dias($fecha);
    if ($d <= 5) return 'verde';
    if ($d <= 7) return 'amarillo';
    return 'rojo';
}
function rbs_colegio(string $cat): string {
    return strpos($cat, '>') !== false ? trim(explode('>', $cat)[1]) : '';
}
function rbs_fecha_iso(string $fecha): string {
    if (preg_match('#(\d{2})/(\d{2})/(\d{4})#', $fecha, $m)) return "{$m[3]}-{$m[1]}-{$m[2]}";
    return $fecha;
}
function rbs_fecha_fmt(string $fecha): string {
    if (preg_match('#(\d{2})/(\d{2})/(\d{4})#', $fecha, $m)) return "{$m[2]}/{$m[1]}/{$m[3]}";
    if (preg_match('#(\d{4})-(\d{2})-(\d{2})#', $fecha, $m)) return "{$m[3]}/{$m[2]}/{$m[1]}";
    return $fecha;
}

$puedeAprobar = in_array(Auth::user()['rol_id'], [1, 2]);

// ── Aprobar pedido ──
if ($_SERVER['REQUEST_METHOD']==='POST'
    && ($_POST['action']??'')==='aprobar'
    && Auth::csrfVerify($_POST['csrf']??'')
    && $puedeAprobar
) {
    $pid = (int)$_POST['pedido_id'];
    if ($pid) {
        $pedRow = $db->query("SELECT * FROM tienda_pedidos WHERE id=$pid")->fetch();
        if ($pedRow && $pedRow['estado'] === 'pendiente') {

            $sets   = "estado='aprobado', updated_at=NOW()";
            $params = ['id' => $pid];
            if ($colAprobado) {
                $sets .= ", aprobado_por=:ap, aprobado_at=NOW()";
                $params['ap'] = Auth::user()['id'];
            }
            $db->prepare("UPDATE tienda_pedidos SET $sets WHERE id=:id")->execute($params);

            // Crear solicitud de producción si no existe
            $yaExiste = $db->query("SHOW TABLES LIKE 'solicitudes_produccion'")->fetchColumn()
                ? $db->query("SELECT COUNT(*) FROM solicitudes_produccion WHERE pedido_id=$pid")->fetchColumn()
                : 0;
            if (!$yaExiste) {
                $dias = (new DateTime($pedRow['fecha_compra']))->diff(new DateTime('today'))->days;
                crearSolicitudProduccion($db, [
                    'fuente'        => 'tienda',
                    'titulo'        => 'Pedido Tienda: '.($pedRow['kit_nombre'] ?? '').' — '.$pedRow['cliente_nombre'],
                    'tipo'          => 'armar_kit',
                    'kit_nombre'    => $pedRow['kit_nombre'],
                    'cantidad'      => (int)($pedRow['cantidad'] ?? 1),
                    'prioridad'     => $dias > 7 ? 1 : ($dias > 5 ? 2 : 3),
                    'fecha_limite'  => $pedRow['fecha_limite'],
                    'usuario_id'    => Auth::user()['id'],
                    'pedido_id'     => $pid,
                    'colegio_id'    => $pedRow['colegio_id'],
                    'notas'         => 'Cliente: '.$pedRow['cliente_nombre'].' | '.($pedRow['ciudad'] ?? ''),
                    'historial_nota'=> 'Aprobado y enviado a producción',
                ]);
            }

            $db->prepare("INSERT INTO tienda_pedidos_historial (pedido_id,estado_ant,estado_nuevo,nota,usuario_id) VALUES (?,?,?,?,?)")
               ->execute([$pid, 'pendiente', 'aprobado', 'Aprobado por '.Auth::user()['nombre'], Auth::user()['id']]);

            $success = 'Pedido aprobado y enviado a producción.';
        }
    }
}

// ── Cambiar estado de un pedido ──
if ($_SERVER['REQUEST_METHOD']==='POST'
    && ($_POST['action']??'')==='cambiar_estado'
    && Auth::csrfVerify($_POST['csrf']??'')
) {
    $pid      = (int)$_POST['pedido_id'];
    $nuevoEst = $_POST['estado_nuevo'] ?? '';
    $nota     = trim($_POST['nota'] ?? '');
    $guia     = trim($_POST['guia_envio'] ?? '');
    $transport= trim($_POST['transportadora'] ?? '');

    if ($pid && isset($ESTADOS[$nuevoEst])) {
        $actualEst = $db->query("SELECT estado FROM tienda_pedidos WHERE id=$pid")->fetchColumn();

        $sets   = "estado=:e, updated_at=NOW()";
        $params = ['e' => $nuevoEst, 'id' => $pid];

        if ($nuevoEst === 'despachado') {
            $sets .= ", fecha_despacho=CURDATE()";
            if ($guia)      { $sets .= ", guia_envio=:g";     $params['g'] = $guia; }
            if ($transport) { $sets .= ", transportadora=:t";  $params['t'] = $transport; }
        }
        if ($nuevoEst === 'entregado') {
            $sets .= ", fecha_entrega=CURDATE()";
        }

        $db->prepare("UPDATE tienda_pedidos SET $sets WHERE id=:id")->execute($params);

        // Crear solicitud de producción si se marca en_produccion y no viene del flujo aprobado
        if ($nuevoEst === 'en_produccion' && $actualEst !== 'en_produccion') {
            $pedRow  = $db->query("SELECT * FROM tienda_pedidos WHERE id=$pid")->fetch();
            $yaExiste = $db->query("SHOW TABLES LIKE 'solicitudes_produccion'")->fetchColumn()
                ? $db->query("SELECT COUNT(*) FROM solicitudes_produccion WHERE pedido_id=$pid")->fetchColumn()
                : 0;
            if (!$yaExiste && $pedRow) {
                $dias = (new DateTime($pedRow['fecha_compra']))->diff(new DateTime('today'))->days;
                crearSolicitudProduccion($db, [
                    'fuente'        => 'tienda',
                    'titulo'        => 'Pedido Tienda: '.($pedRow['kit_nombre'] ?? '').' — '.$pedRow['cliente_nombre'],
                    'tipo'          => 'armar_kit',
                    'kit_nombre'    => $pedRow['kit_nombre'],
                    'cantidad'      => (int)($pedRow['cantidad'] ?? 1),
                    'prioridad'     => $dias > 7 ? 1 : ($dias > 5 ? 2 : 3),
                    'fecha_limite'  => $pedRow['fecha_limite'],
                    'usuario_id'    => Auth::user()['id'],
                    'pedido_id'     => $pid,
                    'colegio_id'    => $pedRow['colegio_id'],
                    'notas'         => 'Cliente: '.$pedRow['cliente_nombre'].' | '.($pedRow['ciudad'] ?? ''),
                    'historial_nota'=> 'Enviado a producción desde módulo Tienda',
                ]);
            }
        }

        $db->prepare("INSERT INTO tienda_pedidos_historial (pedido_id,estado_ant,estado_nuevo,nota,usuario_id) VALUES (?,?,?,?,?)")
           ->execute([$pid, $actualEst, $nuevoEst, $nota ?: null, Auth::user()['id']]);

        $success = 'Estado actualizado correctamente.';
    }
}

// ── Filtros ──
$fEstado  = $_GET['estado']  ?? '';
$fSem     = $_GET['sem']     ?? '';
$fColegio = $_GET['colegio'] ?? '';
$fBusqRaw = trim($_GET['q']  ?? '');
$fBusq    = ltrim($fBusqRaw, '#');
$pagina   = max(1, (int)($_GET['pag'] ?? 1));
$porPag   = 30;

$where = ["1=1"];
if ($fEstado && isset($ESTADOS[$fEstado])) {
    $where[] = "p.estado = " . $db->quote($fEstado);
}
if ($fColegio) {
    $where[] = "(p.colegio_nombre LIKE " . $db->quote('%'.$fColegio.'%') .
               " OR EXISTS (SELECT 1 FROM colegios c2 WHERE c2.id=p.colegio_id AND c2.nombre LIKE " . $db->quote('%'.$fColegio.'%') . "))";
}
if ($fBusq) {
    $busqNum = preg_replace('/[^0-9]/', '', $fBusq);
    $qLike   = $db->quote('%'.$fBusq.'%');
    $cond    = "(p.cliente_nombre LIKE $qLike OR p.woo_order_id LIKE $qLike OR p.kit_nombre LIKE $qLike";
    if ($busqNum) $cond .= " OR p.woo_order_id = " . $db->quote($busqNum);
    $cond .= ")";
    $where[] = $cond;
}
if ($fSem) {
    switch ($fSem) {
        case 'verde':     $where[] = "p.estado NOT IN ('entregado','cancelado','despachado') AND DATEDIFF(CURDATE(),p.fecha_compra)<=5"; break;
        case 'amarillo':  $where[] = "p.estado NOT IN ('entregado','cancelado','despachado') AND DATEDIFF(CURDATE(),p.fecha_compra) BETWEEN 6 AND 7"; break;
        case 'rojo':      $where[] = "p.estado NOT IN ('entregado','cancelado','despachado') AND DATEDIFF(CURDATE(),p.fecha_compra)>7"; break;
        case 'completado':$where[] = "p.estado IN ('entregado','cancelado','despachado')"; break;
    }
}
$whereStr = implode(' AND ', $where);

$total    = $db->query("SELECT COUNT(*) FROM tienda_pedidos p WHERE $whereStr")->fetchColumn();
$totalPags= max(1, ceil($total / $porPag));
$offset   = ($pagina - 1) * $porPag;

$aprobadoJoin = $colAprobado ? "LEFT JOIN usuarios ua ON ua.id=p.aprobado_por" : "";
$aprobadoSel  = $colAprobado ? ", ua.nombre AS aprobado_nombre" : "";

$pedidos = $db->query("
    SELECT p.*,
           DATEDIFF(CURDATE(),p.fecha_compra) AS dias,
           CASE
             WHEN p.estado IN ('entregado','cancelado','despachado') THEN 'completado'
             WHEN DATEDIFF(CURDATE(),p.fecha_compra)<=5 THEN 'verde'
             WHEN DATEDIFF(CURDATE(),p.fecha_compra)<=7 THEN 'amarillo'
             ELSE 'rojo'
           END AS semaforo,
           col.nombre AS colegio_bd,
           u.nombre   AS asignado_nombre
           $aprobadoSel
    FROM tienda_pedidos p
    LEFT JOIN colegios col ON col.id=p.colegio_id
    LEFT JOIN usuarios u   ON u.id=p.asignado_a
    $aprobadoJoin
    WHERE $whereStr
    ORDER BY
      CASE WHEN p.estado IN ('entregado','cancelado','despachado') THEN 1 ELSE 0 END ASC,
      CASE WHEN DATEDIFF(CURDATE(),p.fecha_compra)>7 THEN 0
           WHEN DATEDIFF(CURDATE(),p.fecha_compra)>5 THEN 1
           ELSE 2 END ASC,
      p.fecha_compra ASC
    LIMIT $porPag OFFSET $offset
")->fetchAll();

$stats = $db->query("
    SELECT
      COUNT(*) AS total,
      SUM(CASE WHEN estado NOT IN ('entregado','cancelado','despachado') AND DATEDIFF(CURDATE(),fecha_compra)>7  THEN 1 ELSE 0 END) AS rojos,
      SUM(CASE WHEN estado NOT IN ('entregado','cancelado','despachado') AND DATEDIFF(CURDATE(),fecha_compra) BETWEEN 6 AND 7 THEN 1 ELSE 0 END) AS amarillos,
      SUM(CASE WHEN estado NOT IN ('entregado','cancelado','despachado') AND DATEDIFF(CURDATE(),fecha_compra)<=5 THEN 1 ELSE 0 END) AS verdes,
      SUM(CASE WHEN estado='pendiente'        THEN 1 ELSE 0 END) AS cnt_pendiente,
      SUM(CASE WHEN estado='aprobado'         THEN 1 ELSE 0 END) AS cnt_aprobado,
      SUM(CASE WHEN estado='en_produccion'    THEN 1 ELSE 0 END) AS cnt_en_produccion,
      SUM(CASE WHEN estado='listo_produccion' THEN 1 ELSE 0 END) AS cnt_listo_produccion,
      SUM(CASE WHEN estado='en_alistamiento'  THEN 1 ELSE 0 END) AS cnt_en_alistamiento,
      SUM(CASE WHEN estado='despachado'       THEN 1 ELSE 0 END) AS cnt_despachado,
      SUM(CASE WHEN estado='entregado'        THEN 1 ELSE 0 END) AS cnt_entregado,
      SUM(CASE WHEN estado='cancelado'        THEN 1 ELSE 0 END) AS cnt_cancelado
    FROM tienda_pedidos
")->fetch();

$statMap = [
    'pendiente'       => 'cnt_pendiente',
    'aprobado'        => 'cnt_aprobado',
    'en_produccion'   => 'cnt_en_produccion',
    'listo_produccion'=> 'cnt_listo_produccion',
    'en_alistamiento' => 'cnt_en_alistamiento',
    'despachado'      => 'cnt_despachado',
    'entregado'       => 'cnt_entregado',
    'cancelado'       => 'cnt_cancelado',
];

$colegios_pedidos = $db->query("
    SELECT DISTINCT
      COALESCE(col.nombre, p.colegio_nombre) AS nombre_display,
      p.colegio_nombre AS nombre_csv
    FROM tienda_pedidos p
    LEFT JOIN colegios col ON col.id = p.colegio_id
    WHERE (p.colegio_nombre IS NOT NULL AND p.colegio_nombre != '')
       OR (p.colegio_id IS NOT NULL)
    ORDER BY nombre_display
")->fetchAll();

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
.sem-dot{width:11px;height:11px;border-radius:50%;display:inline-block;flex-shrink:0}
.sem-verde    {background:#22c55e;box-shadow:0 0 0 3px #dcfce7}
.sem-amarillo {background:#f59e0b;box-shadow:0 0 0 3px #fef9c3}
.sem-rojo     {background:#ef4444;box-shadow:0 0 0 3px #fee2e2}
.sem-completado{background:#94a3b8;box-shadow:0 0 0 3px #f1f5f9}
.stat-chip{border-radius:10px;padding:.35rem .8rem;font-size:.8rem;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:.35rem;cursor:pointer;border:1px solid transparent;transition:.1s}
.stat-chip:hover{filter:brightness(.95)}
.stat-chip.activo{outline:2px solid currentColor}
.section-card{background:#fff;border-radius:14px;border:1px solid #e2e8f0;padding:1rem 1.2rem;margin-bottom:1rem}
.rt thead th{background:#1e293b;color:#fff;padding:.5rem .75rem;font-size:.74rem;font-weight:600;white-space:nowrap;border:none}
.rt tbody td{padding:.45rem .75rem;font-size:.81rem;border-bottom:1px solid #f1f5f9;vertical-align:middle}
.rt tbody tr:hover td{background:#f8fafc}
.estado-sel{font-size:.73rem;padding:.2rem .35rem;border-radius:6px;border:1px solid #e2e8f0;background:#fff;cursor:pointer;max-width:130px}
.borde-rojo      td:first-child{border-left:4px solid #ef4444!important}
.borde-amarillo  td:first-child{border-left:4px solid #f59e0b!important}
.borde-verde     td:first-child{border-left:4px solid #22c55e!important}
.borde-completado td:first-child{border-left:4px solid #94a3b8!important;opacity:.8}
.upload-area{border:2px dashed #cbd5e1;border-radius:14px;padding:3rem;text-align:center;cursor:pointer}
.upload-area:hover{background:#f8fafc}
.chk-row{width:16px;height:16px;cursor:pointer;accent-color:#185FA5}
.barra-sel{position:fixed;bottom:1.5rem;left:50%;transform:translateX(-50%);background:#1e293b;color:#fff;border-radius:14px;padding:.6rem 1.2rem;display:none;align-items:center;gap:.75rem;box-shadow:0 8px 24px rgba(0,0,0,.3);z-index:1000;white-space:nowrap}
.barra-sel.visible{display:flex}
tr.fila-sel td{background:#eff6ff!important}
.btn-aprobar{font-size:.7rem;padding:.15rem .45rem;white-space:nowrap}
</style>

<!-- Cabecera -->
<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h4 class="fw-bold mb-0">&#x1F6D2; Pedidos Tienda Online</h4>
    <p class="text-muted small mb-0"><?= $stats['total'] ?> pedidos en base de datos</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if (!empty($pedidos)): ?>
    <a href="stickers.php?<?= htmlspecialchars(http_build_query($_GET)) ?>" target="_blank" class="btn btn-danger btn-sm">
      <i class="bi bi-printer me-1"></i>Etiquetas
    </a>
    <a href="lista_imprimible.php?<?= htmlspecialchars(http_build_query($_GET)) ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-list-ul me-1"></i>Lista
    </a>
    <?php endif; ?>
    <a href="<?= APP_URL ?>/modules/alistamiento/" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-box-seam me-1"></i>Alistamiento
    </a>
    <a href="importar.php" class="btn btn-primary btn-sm">
      <i class="bi bi-upload me-1"></i>Importar CSV
    </a>
  </div>
</div>

<?php if ($error):   ?><div class="alert alert-danger   py-2 small"><?= htmlspecialchars($error)   ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success  py-2 small"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<!-- Chips de semáforo y estado -->
<?php
function urlFiltro(array $override): string {
    $actual = ['estado'=>$GLOBALS['fEstado'],'sem'=>$GLOBALS['fSem'],
               'colegio'=>$GLOBALS['fColegio'],'q'=>$GLOBALS['fBusqRaw'] ?? ''];
    $params = array_filter(array_merge($actual, $override), fn($v) => $v !== '');
    return '?' . http_build_query($params);
}
?>
<div class="d-flex flex-wrap gap-2 mb-3">
  <a href="<?= urlFiltro(['sem'=>'rojo','estado'=>'']) ?>"
     class="stat-chip <?= $fSem==='rojo'?'activo':'' ?>"
     style="background:#fee2e2;color:#991b1b">
    <span class="sem-dot sem-rojo"></span><?= $stats['rojos'] ?> Urgentes &gt;7d
  </a>
  <a href="<?= urlFiltro(['sem'=>'amarillo','estado'=>'']) ?>"
     class="stat-chip <?= $fSem==='amarillo'?'activo':'' ?>"
     style="background:#fef9c3;color:#854d0e">
    <span class="sem-dot sem-amarillo"></span><?= $stats['amarillos'] ?> En riesgo
  </a>
  <a href="<?= urlFiltro(['sem'=>'verde','estado'=>'']) ?>"
     class="stat-chip <?= $fSem==='verde'?'activo':'' ?>"
     style="background:#dcfce7;color:#166534">
    <span class="sem-dot sem-verde"></span><?= $stats['verdes'] ?> Al d&iacute;a
  </a>
  <span style="border-left:1px solid #e2e8f0;margin:0 .25rem"></span>
  <?php foreach ($ESTADOS as $k => $e): ?>
  <a href="<?= urlFiltro(['estado'=>$k,'sem'=>'']) ?>"
     class="stat-chip <?= $fEstado===$k?'activo':'' ?>"
     style="background:<?= $e['bg'] ?>;color:<?= $e['txt'] ?>">
    <?= strip_tags($e['label']) ?>: <?= $stats[$statMap[$k]] ?? 0 ?>
  </a>
  <?php endforeach; ?>
  <?php if ($fEstado || $fSem || $fColegio || ($fBusqRaw??'')): ?>
  <a href="?" class="stat-chip" style="background:#f1f5f9;color:#475569;border-color:#e2e8f0">
    &#10005; Limpiar
  </a>
  <?php endif; ?>
</div>

<?php
$totalGlobal = $db->query("SELECT COUNT(*) FROM tienda_pedidos")->fetchColumn();
if ($totalGlobal == 0): ?>
<div class="upload-area" onclick="window.location='importar.php'">
  <i class="bi bi-cloud-upload fs-1 text-primary d-block mb-3"></i>
  <h5 class="fw-bold">Importa tu primer CSV de WooCommerce</h5>
  <p class="text-muted">Solo se importan pedidos con estado <strong>Procesando</strong>. Los duplicados se omiten automáticamente.</p>
  <span class="btn btn-primary mt-2">Ir a importar CSV</span>
</div>
<?php else: ?>

<!-- Filtros -->
<div class="section-card">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-12 col-md-3">
      <input type="text" name="q" class="form-control form-control-sm"
             placeholder="Nombre, kit o #pedido..."
             value="<?= htmlspecialchars($fBusqRaw ?? $fBusq) ?>">
    </div>
    <div class="col-6 col-md-2">
      <select name="estado" class="form-select form-select-sm">
        <option value="">Todos los estados</option>
        <?php foreach ($ESTADOS as $k => $e): ?>
          <option value="<?= $k ?>" <?= $fEstado===$k?'selected':'' ?>><?= strip_tags($e['label']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <select name="sem" class="form-select form-select-sm">
        <option value="">Sem&aacute;foro</option>
        <option value="rojo"       <?= $fSem==='rojo'      ?'selected':'' ?>>Urgente &gt;7d</option>
        <option value="amarillo"   <?= $fSem==='amarillo'  ?'selected':'' ?>>En riesgo 5-7d</option>
        <option value="verde"      <?= $fSem==='verde'     ?'selected':'' ?>>Al d&iacute;a</option>
        <option value="completado" <?= $fSem==='completado'?'selected':'' ?>>Completados</option>
      </select>
    </div>
    <div class="col-12 col-md-3">
      <select name="colegio" class="form-select form-select-sm">
        <option value="">Todos los colegios</option>
        <?php foreach ($colegios_pedidos as $c):
          $val = $c['nombre_csv'] ?: $c['nombre_display'];
        ?>
          <option value="<?= htmlspecialchars($val) ?>" <?= $fColegio===$val?'selected':'' ?>><?= htmlspecialchars($c['nombre_display'] ?: $val) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto"><button type="submit" class="btn btn-primary btn-sm">Filtrar</button></div>
    <?php if ($fBusq||$fEstado||$fSem||$fColegio): ?>
    <div class="col-auto"><a href="?" class="btn btn-outline-secondary btn-sm">Limpiar</a></div>
    <?php endif; ?>
  </form>
</div>

<!-- Tabla -->
<?php if (empty($pedidos)): ?>
  <div class="section-card text-center text-muted py-5">
    <i class="bi bi-inbox fs-2 d-block mb-2"></i>Sin resultados para los filtros aplicados.
  </div>
<?php else: ?>
<div class="section-card p-0" style="overflow:hidden">
  <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
    <span class="small text-muted fw-semibold"><?= $total ?> pedidos &mdash; p&aacute;gina <?= $pagina ?> de <?= $totalPags ?></span>
    <a href="stickers.php?<?= htmlspecialchars(http_build_query($_GET)) ?>" target="_blank" class="btn btn-danger btn-sm">
      <i class="bi bi-printer me-1"></i>Etiquetas (6/hoja)
    </a>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0 rt">
      <thead><tr>
        <th style="width:36px;text-align:center">
          <input type="checkbox" class="chk-row" id="chk-all" title="Seleccionar todos" onchange="selTodos(this.checked)">
        </th>
        <th></th>
        <th>#WOO</th>
        <th>Fecha</th>
        <th>Cliente</th>
        <th>Colegio</th>
        <th>Kit</th>
        <th>D&iacute;as</th>
        <th>Estado</th>
        <th>Acciones</th>
      </tr></thead>
      <tbody>
      <?php
      $semCol = ['rojo'=>'#ef4444','amarillo'=>'#f59e0b','verde'=>'#22c55e','completado'=>'#94a3b8'];
      foreach ($pedidos as $p):
        $sc  = $semCol[$p['semaforo']] ?? '#ccc';
        $est = $ESTADOS[$p['estado']] ?? ['label'=>$p['estado'],'bg'=>'#f1f5f9','txt'=>'#475569'];
      ?>
      <tr class="borde-<?= $p['semaforo'] ?>" id="fila-<?= $p['id'] ?>">
        <td style="text-align:center;border-left:4px solid <?= $sc ?>;padding:.45rem .5rem">
          <input type="checkbox" class="chk-row chk-pedido"
                 value="<?= $p['id'] ?>"
                 data-order="<?= htmlspecialchars($p['woo_order_id']) ?>"
                 onchange="actualizarSel()">
        </td>
        <td><span class="sem-dot sem-<?= $p['semaforo'] ?>"></span></td>
        <td class="fw-semibold" style="white-space:nowrap">
          <a href="ver.php?id=<?= $p['id'] ?>" class="text-decoration-none">#<?= htmlspecialchars($p['woo_order_id']) ?></a>
        </td>
        <td style="white-space:nowrap"><?= rbs_fecha_fmt($p['fecha_compra']) ?></td>
        <td>
          <div class="fw-semibold" style="font-size:.82rem"><?= htmlspecialchars($p['cliente_nombre']) ?></div>
          <?php if ($p['cliente_telefono']): ?><div class="text-muted" style="font-size:.72rem"><?= htmlspecialchars($p['cliente_telefono']) ?></div><?php endif; ?>
        </td>
        <td style="color:#1d4ed8;font-size:.78rem;max-width:130px">
          <?= $p['colegio_bd']
                ? htmlspecialchars($p['colegio_bd'])
                : ($p['colegio_nombre'] ? htmlspecialchars($p['colegio_nombre']) : '<span class="text-muted">&mdash;</span>') ?>
        </td>
        <td style="max-width:150px;font-size:.77rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
            title="<?= htmlspecialchars($p['kit_nombre'] ?? '') ?>">
          <?= htmlspecialchars($p['kit_nombre'] ?? '—') ?>
        </td>
        <td style="text-align:center;font-weight:700;color:<?= $sc ?>"><?= $p['dias'] ?>d</td>
        <td>
          <span class="badge" style="background:<?= $est['bg'] ?>;color:<?= $est['txt'] ?>;font-size:.71rem">
            <?= strip_tags($est['label']) ?>
          </span>
        </td>
        <td>
          <div class="d-flex gap-1 align-items-center flex-wrap">
            <!-- Botón Aprobar: solo pendiente + Gerencia/Administración -->
            <?php if ($p['estado'] === 'pendiente' && $puedeAprobar): ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action"    value="aprobar">
              <input type="hidden" name="pedido_id" value="<?= $p['id'] ?>">
              <input type="hidden" name="csrf"      value="<?= Auth::csrfToken() ?>">
              <button type="submit" class="btn btn-primary btn-aprobar"
                      onclick="return confirm('Aprobar pedido #<?= htmlspecialchars($p['woo_order_id']) ?> y enviar a producción?')">
                <i class="bi bi-check-lg me-1"></i>Aprobar
              </button>
            </form>
            <?php endif; ?>
            <!-- Cambio rápido de estado -->
            <form method="POST" style="display:inline">
              <input type="hidden" name="action"    value="cambiar_estado">
              <input type="hidden" name="pedido_id" value="<?= $p['id'] ?>">
              <input type="hidden" name="csrf"      value="<?= Auth::csrfToken() ?>">
              <select name="estado_nuevo" class="estado-sel" onchange="this.form.submit()">
                <?php foreach ($ESTADOS as $k => $e): ?>
                  <option value="<?= $k ?>" <?= $p['estado']===$k?'selected':'' ?>><?= strip_tags($e['label']) ?></option>
                <?php endforeach; ?>
              </select>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($totalPags > 1): ?>
  <div class="px-3 py-2 border-top">
    <nav><ul class="pagination pagination-sm mb-0 justify-content-center">
      <?php for ($i = 1; $i <= $totalPags; $i++):
        $p2 = array_merge($_GET, ['pag' => $i]); ?>
      <li class="page-item <?= $i===$pagina?'active':'' ?>">
        <a class="page-link" href="?<?= http_build_query($p2) ?>"><?= $i ?></a>
      </li>
      <?php endfor; ?>
    </ul></nav>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- Barra flotante de selección múltiple -->
<div class="barra-sel" id="barra-sel">
  <span style="font-size:.85rem;font-weight:700"><span id="cnt-sel">0</span> pedido(s)</span>
  <div class="d-flex gap-2">
    <button class="btn btn-danger btn-sm fw-bold" onclick="imprimirSeleccionados()">
      <i class="bi bi-printer me-1"></i>Stickers
    </button>
    <button class="btn btn-outline-light btn-sm" onclick="deselTodos()">Cancelar</button>
  </div>
</div>

<script>
function actualizarSel() {
    var chks  = document.querySelectorAll('.chk-pedido:checked');
    var todos = document.querySelectorAll('.chk-pedido');
    var n     = chks.length;
    var barra = document.getElementById('barra-sel');
    document.getElementById('cnt-sel').textContent = n;
    document.querySelectorAll('.chk-pedido').forEach(function(c) {
        c.closest('tr').classList.toggle('fila-sel', c.checked);
    });
    barra.classList.toggle('visible', n > 0);
    var allChk = document.getElementById('chk-all');
    allChk.indeterminate = n > 0 && n < todos.length;
    allChk.checked = n > 0 && n === todos.length;
}
function selTodos(on) {
    document.querySelectorAll('.chk-pedido').forEach(function(c){ c.checked = on; });
    actualizarSel();
}
function deselTodos() {
    document.querySelectorAll('.chk-pedido').forEach(function(c){ c.checked = false; });
    actualizarSel();
}
function imprimirSeleccionados() {
    var ids = Array.from(document.querySelectorAll('.chk-pedido:checked')).map(function(c){ return c.value; });
    if (!ids.length) { alert('Selecciona al menos un pedido.'); return; }
    window.open('stickers.php?ids=' + ids.join(','), '_blank');
}
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
