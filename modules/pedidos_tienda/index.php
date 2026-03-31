<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
require_once dirname(__DIR__, 2) . '/includes/WooSync.php';
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

$puedeAprobar  = Auth::isAdmin();
$puedeEliminar = Auth::isAdmin();

// ── Leer estado del modo limpieza ──
$modoLimpieza = (bool)($db->query(
    "SELECT valor FROM configuracion WHERE clave='modo_limpieza_pedidos'"
)->fetchColumn());

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

            // Crear solicitud de producción si no hay una activa (ignora rechazadas)
            $yaExiste = $db->query("SHOW TABLES LIKE 'solicitudes_produccion'")->fetchColumn()
                ? $db->query("SELECT COUNT(*) FROM solicitudes_produccion WHERE pedido_id=$pid AND estado NOT IN ('rechazado')")->fetchColumn()
                : 0;
            if (!$yaExiste) {
                $dias = (new DateTime($pedRow['fecha_compra']))->diff(new DateTime('today'))->days;
                crearSolicitudProduccion($db, [
                    'fuente'        => 'tienda',
                    'titulo'        => ($pedRow['kit_nombre'] ?? 'Pedido sin kit'),
                    'tipo'          => 'armar_kit',
                    'kit_nombre'    => $pedRow['kit_nombre'],
                    'cantidad'      => (int)($pedRow['cantidad'] ?? 1),
                    'prioridad'     => $dias > 7 ? 1 : ($dias > 5 ? 2 : 3),
                    'fecha_limite'  => $pedRow['fecha_limite'],
                    'usuario_id'    => Auth::user()['id'],
                    'pedido_id'     => $pid,
                    'colegio_id'    => $pedRow['colegio_id'],
                    'notas'         => null,
                    'historial_nota'=> 'Aprobado y enviado a producción',
                ]);
            }

            $db->prepare("INSERT INTO tienda_pedidos_historial (pedido_id,estado_ant,estado_nuevo,nota,usuario_id) VALUES (?,?,?,?,?)")
               ->execute([$pid, 'pendiente', 'aprobado', 'Aprobado por '.Auth::user()['nombre'], Auth::user()['id']]);

            // Sincronizar con WooCommerce: marcar como on-hold (recibido/en preparación)
            if (!empty($pedRow['woo_order_id']) && empty($pedRow['creado_desde_csv'])) {
                $woo = new WooSync($db);
                if ($woo->isConfigured()) $woo->actualizarEstado($pedRow['woo_order_id'], 'on-hold');
            }

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

        // Al retroceder a pendiente → cancelar la solicitud de producción activa
        if ($nuevoEst === 'pendiente'
            && $db->query("SHOW TABLES LIKE 'solicitudes_produccion'")->fetchColumn()
        ) {
            $solActiva = $db->query("SELECT id FROM solicitudes_produccion WHERE pedido_id=$pid AND estado NOT IN ('rechazado') LIMIT 1")->fetch();
            if ($solActiva) {
                $db->prepare("UPDATE solicitudes_produccion SET estado='rechazado', notas_respuesta=? WHERE id=?")
                   ->execute(['Pedido retrocedido a pendiente desde tienda', $solActiva['id']]);
                $db->prepare("INSERT INTO solicitud_historial (solicitud_id,estado,usuario_id,comentario) VALUES (?,?,?,?)")
                   ->execute([$solActiva['id'], 'rechazado', Auth::user()['id'], 'Pedido retrocedido a pendiente']);
            }
        }

        // Crear solicitud de producción si se marca en_produccion y no viene del flujo aprobado
        if ($nuevoEst === 'en_produccion' && $actualEst !== 'en_produccion') {
            $pedRow  = $db->query("SELECT * FROM tienda_pedidos WHERE id=$pid")->fetch();
            $yaExiste = $db->query("SHOW TABLES LIKE 'solicitudes_produccion'")->fetchColumn()
                ? $db->query("SELECT COUNT(*) FROM solicitudes_produccion WHERE pedido_id=$pid AND estado NOT IN ('rechazado')")->fetchColumn()
                : 0;
            if (!$yaExiste && $pedRow) {
                $dias = (new DateTime($pedRow['fecha_compra']))->diff(new DateTime('today'))->days;
                crearSolicitudProduccion($db, [
                    'fuente'        => 'tienda',
                    'titulo'        => ($pedRow['kit_nombre'] ?? 'Pedido sin kit'),
                    'tipo'          => 'armar_kit',
                    'kit_nombre'    => $pedRow['kit_nombre'],
                    'cantidad'      => (int)($pedRow['cantidad'] ?? 1),
                    'prioridad'     => $dias > 7 ? 1 : ($dias > 5 ? 2 : 3),
                    'fecha_limite'  => $pedRow['fecha_limite'],
                    'usuario_id'    => Auth::user()['id'],
                    'pedido_id'     => $pid,
                    'colegio_id'    => $pedRow['colegio_id'],
                    'notas'         => null,
                    'historial_nota'=> 'Enviado a producción desde módulo Tienda',
                ]);
            }
        }

        $db->prepare("INSERT INTO tienda_pedidos_historial (pedido_id,estado_ant,estado_nuevo,nota,usuario_id) VALUES (?,?,?,?,?)")
           ->execute([$pid, $actualEst, $nuevoEst, $nota ?: null, Auth::user()['id']]);

        // Sincronizar con WooCommerce: marcar como completed al despachar
        if ($nuevoEst === 'despachado') {
            $wooRow = $db->query("SELECT woo_order_id, creado_desde_csv FROM tienda_pedidos WHERE id=$pid")->fetch();
            if (!empty($wooRow['woo_order_id']) && empty($wooRow['creado_desde_csv'])) {
                $woo = new WooSync($db);
                if ($woo->isConfigured()) $woo->actualizarEstado($wooRow['woo_order_id'], 'completed');
            }
        }

        $success = 'Estado actualizado correctamente.';
    }
}

// ── Eliminar pedido (temporal — solo desarrollo) ──
if ($_SERVER['REQUEST_METHOD']==='POST'
    && ($_POST['action']??'')==='eliminar_pedido'
    && Auth::csrfVerify($_POST['csrf']??'')
    && $puedeEliminar
) {
    $pid = (int)$_POST['pedido_id'];
    if ($pid) {
        $db->prepare("DELETE FROM tienda_pedidos_historial WHERE pedido_id = ?")->execute([$pid]);
        $db->prepare("DELETE FROM tienda_pedidos WHERE id = ?")->execute([$pid]);
    }
    header('Location: index.php?msg=eliminado');
    exit;
}
if (($_GET['msg'] ?? '') === 'eliminado') $success = 'Pedido eliminado correctamente.';

// ── Toggle modo limpieza ──
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'toggle_modo_limpieza'
    && Auth::csrfVerify($_POST['csrf'] ?? '')
    && Auth::isAdmin()
) {
    $nuevoValor = $modoLimpieza ? '0' : '1';
    $db->prepare(
        "INSERT INTO configuracion (clave, valor, descripcion, grupo)
         VALUES ('modo_limpieza_pedidos', ?, 'Habilita el borrado masivo de pedidos tienda (solo pruebas)', 'sistema')
         ON DUPLICATE KEY UPDATE valor = VALUES(valor)"
    )->execute([$nuevoValor]);
    header('Location: index.php?msg=toggle_' . ($nuevoValor === '1' ? 'on' : 'off'));
    exit;
}
if (($_GET['msg'] ?? '') === 'toggle_on')  $success = 'Modo limpieza activado. Recuerda desactivarlo cuando termines.';
if (($_GET['msg'] ?? '') === 'toggle_off') $success = 'Modo limpieza desactivado.';
if (($_GET['msg'] ?? '') === 'manual_ok')  $success = 'Pedido manual creado correctamente.' . (!empty($_GET['orden']) ? ' Orden: ' . htmlspecialchars($_GET['orden']) : '');

// ── Limpiar todos los pedidos ──
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'limpiar_todos_pedidos'
    && Auth::csrfVerify($_POST['csrf'] ?? '')
    && Auth::isAdmin()
    && $modoLimpieza
) {
    if (($_POST['confirmar'] ?? '') !== '1') {
        $error = 'Debes marcar la casilla de confirmación para proceder.';
    } else {
        $db->beginTransaction();
        try {
            $totalBorrados = $db->query("SELECT COUNT(*) FROM tienda_pedidos")->fetchColumn();

            // Borrar solicitudes de producción vinculadas si se pidió
            $borroSolicitudes = 0;
            if (($_POST['borrar_solicitudes'] ?? '') === '1') {
                $db->query("DELETE FROM solicitudes_produccion WHERE fuente='tienda'");
                $borroSolicitudes = 1;
            }

            // Borrar todos los pedidos (historial se borra en cascade)
            $db->query("DELETE FROM tienda_pedidos");

            auditoria(
                'limpiar_pedidos_tienda',
                'tienda_pedidos',
                0,
                ['total_pedidos' => $totalBorrados, 'solicitudes_borradas' => $borroSolicitudes],
                []
            );

            $db->commit();
            header('Location: index.php?msg=limpieza_ok&n=' . $totalBorrados . ($borroSolicitudes ? '&sol=1' : ''));
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $error = 'Error al limpiar: ' . $e->getMessage();
        }
    }
}
if (($_GET['msg'] ?? '') === 'limpieza_ok') {
    $n   = (int)($_GET['n'] ?? 0);
    $sol = !empty($_GET['sol']);
    $success = "Se eliminaron $n pedidos" . ($sol ? ' y sus solicitudes de producción' : '') . '.';
}

// ── Filtros ──
$fEstado     = $_GET['estado']      ?? '';
$fColegio    = trim($_GET['colegio'] ?? '');
$fKit        = trim($_GET['kit']    ?? '');
$fPrioridad  = $_GET['prioridad']   ?? '';
$fFechaDesde = $_GET['fecha_desde'] ?? '';
$fFechaHasta = $_GET['fecha_hasta'] ?? '';
$fBusqRaw    = trim($_GET['q']      ?? '');
$fBusq       = ltrim($fBusqRaw, '#');
$fSort       = $_GET['sort']        ?? '';
$fDir        = in_array(strtolower($_GET['dir'] ?? ''), ['asc','desc'])
               ? strtoupper($_GET['dir'])
               : 'ASC';
$pagina      = max(1, (int)($_GET['pag'] ?? 1));
$porPag      = 50;

$where  = ["1=1"];
$params = [];

if ($fEstado && isset($ESTADOS[$fEstado])) {
    $where[]  = "p.estado = ?";
    $params[] = $fEstado;
}
if ($fColegio) {
    $like = '%' . $fColegio . '%';
    $where[]  = "(p.colegio_nombre LIKE ? OR p.colegio_id IN (SELECT id FROM colegios WHERE nombre LIKE ?))";
    $params[] = $like;
    $params[] = $like;
}
if ($fKit) {
    $where[]  = "p.kit_nombre LIKE ?";
    $params[] = '%' . $fKit . '%';
}
if ($fFechaDesde) {
    $where[]  = "DATE(p.fecha_compra) >= ?";
    $params[] = $fFechaDesde;
}
if ($fFechaHasta) {
    $where[]  = "DATE(p.fecha_compra) <= ?";
    $params[] = $fFechaHasta;
}
if ($fBusq) {
    $busqNum  = preg_replace('/[^0-9]/', '', $fBusq);
    $subConds = ["p.cliente_nombre LIKE ?", "p.woo_order_id LIKE ?", "p.kit_nombre LIKE ?", "p.colegio_nombre LIKE ?", "p.ciudad LIKE ?"];
    $params[] = '%' . $fBusq . '%';
    $params[] = '%' . $fBusq . '%';
    $params[] = '%' . $fBusq . '%';
    $params[] = '%' . $fBusq . '%';
    $params[] = '%' . $fBusq . '%';
    if ($busqNum) {
        $subConds[] = "p.woo_order_id = ?";
        $params[]   = $busqNum;
    }
    $where[] = "(" . implode(" OR ", $subConds) . ")";
}
if ($fPrioridad) {
    switch ($fPrioridad) {
        case 'rojo':
            $where[] = "p.estado NOT IN ('entregado','cancelado','despachado') AND DATEDIFF(CURDATE(),p.fecha_compra) > 7";
            break;
        case 'amarillo':
            $where[] = "p.estado NOT IN ('entregado','cancelado','despachado') AND DATEDIFF(CURDATE(),p.fecha_compra) BETWEEN 6 AND 7";
            break;
        case 'verde':
            $where[] = "p.estado NOT IN ('entregado','cancelado','despachado') AND DATEDIFF(CURDATE(),p.fecha_compra) <= 5";
            break;
        case 'completado':
            $where[] = "p.estado IN ('entregado','cancelado','despachado')";
            break;
    }
}
$whereStr = implode(' AND ', $where);

$stCount = $db->prepare("SELECT COUNT(*) FROM tienda_pedidos p WHERE $whereStr");
$stCount->execute($params);
$total     = $stCount->fetchColumn();
$totalPags = max(1, ceil($total / $porPag));
$offset    = ($pagina - 1) * $porPag;

$aprobadoJoin = $colAprobado ? "LEFT JOIN usuarios ua ON ua.id=p.aprobado_por" : "";
$aprobadoSel  = $colAprobado ? ", ua.nombre AS aprobado_nombre" : "";

if ($fSort === 'fecha') {
    $orderBy = "p.fecha_compra $fDir";
} else {
    $orderBy = "CASE WHEN p.estado IN ('entregado','cancelado','despachado') THEN 1 ELSE 0 END ASC,
                CASE WHEN DATEDIFF(CURDATE(),p.fecha_compra)>7 THEN 0
                     WHEN DATEDIFF(CURDATE(),p.fecha_compra)>5 THEN 1
                     ELSE 2 END ASC,
                p.fecha_compra ASC";
}

$st = $db->prepare("
    SELECT p.*,
           DATEDIFF(CURDATE(),p.fecha_compra) AS dias,
           CASE
             WHEN p.estado IN ('entregado','cancelado','despachado') THEN 'completado'
             WHEN DATEDIFF(CURDATE(),p.fecha_compra)<=5 THEN 'verde'
             WHEN DATEDIFF(CURDATE(),p.fecha_compra)<=7 THEN 'amarillo'
             ELSE 'rojo'
           END AS semaforo,
           COALESCE(col.nombre, p.colegio_nombre) AS nombre_colegio,
           u.nombre AS asignado_nombre
           $aprobadoSel
    FROM tienda_pedidos p
    LEFT JOIN colegios col ON col.id=p.colegio_id
    LEFT JOIN usuarios u   ON u.id=p.asignado_a
    $aprobadoJoin
    WHERE $whereStr
    ORDER BY $orderBy
    LIMIT ? OFFSET ?
");
$st->execute([...$params, $porPag, $offset]);
$pedidos = $st->fetchAll();

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
      COALESCE(col.nombre, p.colegio_nombre) AS nombre_display
    FROM tienda_pedidos p
    LEFT JOIN colegios col ON col.id = p.colegio_id
    WHERE COALESCE(col.nombre, p.colegio_nombre) IS NOT NULL
      AND COALESCE(col.nombre, p.colegio_nombre) != ''
    ORDER BY nombre_display
")->fetchAll();

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
/* ── Semáforo chips ── */
.sem-dot{width:8px;height:8px;border-radius:50%;display:inline-block;flex-shrink:0}
.sem-verde    {background:#22c55e}
.sem-amarillo {background:#f59e0b}
.sem-rojo     {background:#ef4444}
/* ── Tabs de estado ── */
.estado-tabs{display:flex;flex-wrap:wrap;gap:.35rem;margin-bottom:.85rem}
.estado-tab{display:inline-flex;align-items:center;gap:.4rem;padding:.3rem .75rem;border-radius:20px;
            font-size:.78rem;font-weight:600;text-decoration:none;border:1.5px solid transparent;
            transition:.12s;white-space:nowrap;color:#475569;background:#f1f5f9;border-color:#e2e8f0}
.estado-tab:hover{filter:brightness(.96);color:#1e293b}
.estado-tab.activo{border-color:currentColor;box-shadow:0 0 0 2px currentColor20}
.tab-cnt{font-size:.7rem;font-weight:700;padding:.05rem .35rem;border-radius:10px;background:rgba(0,0,0,.12)}
/* ── Sem chips ── */
.sem-chip{display:inline-flex;align-items:center;gap:.3rem;padding:.2rem .55rem;border-radius:20px;
          font-size:.73rem;font-weight:600;text-decoration:none;border:1px solid transparent;transition:.1s}
.sem-chip:hover{filter:brightness(.95)}
.sem-chip.activo{outline:2px solid currentColor;outline-offset:1px}
/* ── Tabla ── */
.section-card{background:#fff;border-radius:14px;border:1px solid #e2e8f0;padding:1rem 1.2rem;margin-bottom:1rem}
.rt thead th{background:#1e293b;color:#fff;padding:.45rem .7rem;font-size:.73rem;font-weight:600;white-space:nowrap;border:none}
.rt tbody td{padding:.4rem .7rem;font-size:.8rem;border-bottom:1px solid #f1f5f9;vertical-align:middle}
.rt tbody tr:hover td{background:#f8fafc}
/* ── Cambiar estado dropdown ── */
.dd-estado .dropdown-item{padding:.3rem .75rem;font-size:.78rem;cursor:pointer}
.dd-estado .dropdown-item:hover{background:#f1f5f9}
/* ── Upload area ── */
.upload-area{border:2px dashed #cbd5e1;border-radius:14px;padding:3rem;text-align:center;cursor:pointer}
.upload-area:hover{background:#f8fafc}
/* ── Selección múltiple ── */
.chk-row{width:15px;height:15px;cursor:pointer;accent-color:#185FA5}
.barra-sel{position:fixed;bottom:1.5rem;left:50%;transform:translateX(-50%);background:#1e293b;color:#fff;
           border-radius:14px;padding:.6rem 1.2rem;display:none;align-items:center;gap:.75rem;
           box-shadow:0 8px 24px rgba(0,0,0,.3);z-index:1000;white-space:nowrap}
.barra-sel.visible{display:flex}
tr.fila-sel td{background:#eff6ff!important}
</style>

<!-- ── Cabecera ── -->
<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h4 class="fw-bold mb-0">&#x1F6D2; Pedidos Tienda Online</h4>
    <p class="text-muted small mb-0"><?= $stats['total'] ?> pedidos en total</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if (!empty($pedidos)): ?>
    <a href="lista_imprimible.php?<?= htmlspecialchars(http_build_query($_GET)) ?>" target="_blank"
       class="btn btn-outline-secondary btn-sm"><i class="bi bi-list-ul me-1"></i>Lista</a>
    <?php endif; ?>
    <a href="<?= APP_URL ?>/modules/alistamiento/" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-box-seam me-1"></i>Alistamiento
    </a>
    <a href="crear.php" class="btn btn-success btn-sm fw-semibold">
      <i class="bi bi-bag-plus me-1"></i>Pedido manual
    </a>
    <a href="importar.php" class="btn btn-primary btn-sm">
      <i class="bi bi-upload me-1"></i>Importar CSV
    </a>
  </div>
</div>

<?php if ($error):   ?><div class="alert alert-danger  py-2 small"><?= htmlspecialchars($error)   ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($puedeEliminar): ?>
<div class="alert alert-warning py-1 px-3 small mb-2 d-flex align-items-center gap-2" style="border-left:4px solid #f59e0b">
  <i class="bi bi-exclamation-triangle-fill text-warning"></i>
  <span>&#x26A0; <strong>Modo desarrollo</strong> &mdash; eliminaci&oacute;n de pedidos habilitada</span>
</div>
<?php endif; ?>


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

<!-- ── Barra de filtros compacta ── -->
<div class="section-card py-2 mb-3">
  <form method="GET" class="row g-2 align-items-end flex-wrap">

    <div class="col-auto">
      <select name="estado" class="form-select form-select-sm" style="min-width:145px">
        <option value="">Todos los estados</option>
        <?php foreach ($ESTADOS as $k => $e): ?>
        <option value="<?= $k ?>" <?= $fEstado === $k ? 'selected' : '' ?>>
          <?= strip_tags($e['label']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="col-auto">
      <input type="text" name="colegio" class="form-control form-control-sm"
             list="lista-colegios" placeholder="Colegio..."
             value="<?= htmlspecialchars($fColegio) ?>" style="min-width:160px">
      <datalist id="lista-colegios">
        <?php foreach ($colegios_pedidos as $c): ?>
        <option value="<?= htmlspecialchars($c['nombre_display']) ?>">
        <?php endforeach; ?>
      </datalist>
    </div>

    <div class="col-auto">
      <input type="text" name="kit" class="form-control form-control-sm"
             placeholder="Kit..." value="<?= htmlspecialchars($fKit) ?>"
             style="width:130px">
    </div>

    <div class="col-auto">
      <select name="prioridad" class="form-select form-select-sm">
        <option value="">Prioridad</option>
        <option value="rojo"       <?= $fPrioridad === 'rojo'       ? 'selected' : '' ?>>🔴 Urgente +7d</option>
        <option value="amarillo"   <?= $fPrioridad === 'amarillo'   ? 'selected' : '' ?>>🟡 Normal 6-7d</option>
        <option value="verde"      <?= $fPrioridad === 'verde'      ? 'selected' : '' ?>>🟢 Ok ≤5d</option>
        <option value="completado" <?= $fPrioridad === 'completado' ? 'selected' : '' ?>>✅ Completado</option>
      </select>
    </div>

    <div class="col-auto">
      <input type="date" name="fecha_desde" class="form-control form-control-sm"
             value="<?= htmlspecialchars($fFechaDesde) ?>" title="Compra desde">
    </div>

    <div class="col-auto">
      <input type="date" name="fecha_hasta" class="form-control form-control-sm"
             value="<?= htmlspecialchars($fFechaHasta) ?>" title="Compra hasta">
    </div>

    <div class="col">
      <input type="text" name="q" class="form-control form-control-sm"
             placeholder="&#x1F50D; Nombre, kit o #pedido..."
             value="<?= htmlspecialchars($fBusqRaw) ?>">
    </div>

    <div class="col-auto d-flex gap-2">
      <button type="submit" class="btn btn-primary btn-sm">Buscar</button>
      <a href="index.php" class="btn btn-outline-secondary btn-sm">Limpiar</a>
    </div>

  </form>
</div>

<!-- ── Tabla de pedidos ── -->
<?php if (empty($pedidos)): ?>
  <div class="section-card text-center text-muted py-5">
    <i class="bi bi-inbox fs-2 d-block mb-2"></i>Sin resultados para los filtros aplicados.
  </div>
<?php else: ?>
<div class="section-card p-0" style="overflow:hidden">
  <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
    <span class="small text-muted fw-semibold">
      <?= $total ?> pedido<?= $total != 1 ? 's' : '' ?>
      <?php if ($totalPags > 1): ?>&mdash; p&aacute;g. <?= $pagina ?>/<?= $totalPags ?><?php endif; ?>
    </span>
    <a href="stickers.php?<?= htmlspecialchars(http_build_query($_GET)) ?>" target="_blank"
       class="btn btn-danger btn-sm"><i class="bi bi-printer me-1"></i>Etiquetas (6/hoja)</a>
  </div>
  <div class="table-responsive" style="width:100%">
    <table class="table table-hover mb-0 rt" style="width:100%;table-layout:fixed">
      <thead><tr>
        <th style="width:40px;text-align:center">
          <input type="checkbox" class="chk-row" id="chk-all" title="Seleccionar todos"
                 onchange="selTodos(this.checked)">
        </th>
        <th style="width:90px;white-space:nowrap">#Orden</th>
        <th style="width:100px;text-align:center;white-space:nowrap">
          <?php
            $nextDir  = ($fSort === 'fecha' && $fDir === 'ASC') ? 'desc' : 'asc';
            $sortIcon = match(true) {
                $fSort === 'fecha' && $fDir === 'ASC'  => '&#x2191;',
                $fSort === 'fecha' && $fDir === 'DESC' => '&#x2193;',
                default                                 => '&#x2195;',
            };
            $sortPrms = array_filter(
                array_merge($_GET, ['sort'=>'fecha','dir'=>$nextDir,'pag'=>'']),
                fn($v) => $v !== ''
            );
          ?>
          <a href="?<?= http_build_query($sortPrms) ?>" class="text-white text-decoration-none">
            Fecha <?= $sortIcon ?>
          </a>
        </th>
        <th style="width:15%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">Colegio</th>
        <th>Kit</th>
        <th style="width:130px;text-align:center">Estado</th>
        <th style="width:120px;text-align:center;white-space:nowrap">Acciones</th>
      </tr></thead>
      <tbody>
      <?php
      $semCol = ['rojo'=>'#ef4444','amarillo'=>'#f59e0b','verde'=>'#22c55e','completado'=>'#94a3b8'];
      foreach ($pedidos as $p):
        $sc   = $semCol[$p['semaforo']] ?? '#ccc';
        $est  = $ESTADOS[$p['estado']] ?? ['label'=>$p['estado'],'bg'=>'#f1f5f9','txt'=>'#475569'];
        $cant = (int)($p['cantidad'] ?? 1);
        $diasTitle = $p['dias'] . ' días desde la compra';
      ?>
      <tr id="fila-<?= $p['id'] ?>" onclick="window.location='ver.php?id=<?= $p['id'] ?>'" style="cursor:pointer">
        <!-- Checkbox — borde de color = urgencia; tooltip = días exactos -->
        <td style="text-align:center;border-left:4px solid <?= $sc ?>;padding:.4rem .45rem"
            title="<?= htmlspecialchars($diasTitle) ?>">
          <input type="checkbox" class="chk-row chk-pedido"
                 value="<?= $p['id'] ?>" data-order="<?= htmlspecialchars($p['woo_order_id']) ?>"
                 onclick="event.stopPropagation()" onchange="actualizarSel()">
        </td>
        <!-- #Orden -->
        <td class="fw-bold" style="white-space:nowrap;font-size:.82rem">
          #<?= htmlspecialchars($p['woo_order_id']) ?>
        </td>
        <!-- Fecha + días con color según urgencia -->
        <?php
          $diasColor = match(true) {
              in_array($p['estado'], ['entregado','cancelado','despachado']) => '#94a3b8',
              (int)$p['dias'] <= 5 => '#22c55e',
              (int)$p['dias'] <= 7 => '#f59e0b',
              default               => '#ef4444',
          };
        ?>
        <td style="text-align:center;white-space:nowrap;font-size:.78rem;color:#64748b">
          <?= htmlspecialchars(rbs_fecha_fmt($p['fecha_compra'] ?? '')) ?>
          <div style="font-size:.75rem;font-weight:600;color:<?= $diasColor ?>"><?= (int)$p['dias'] ?>d</div>
        </td>
        <!-- Colegio -->
        <td style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
            title="<?= htmlspecialchars($p['nombre_colegio'] ?? '') ?>">
          <span style="font-size:.78rem;color:#1d4ed8">
            <?= htmlspecialchars($p['nombre_colegio'] ?: '—') ?>
          </span>
        </td>
        <!-- Kit + cantidad -->
        <td>
          <div style="font-size:.77rem;white-space:normal;word-break:break-word">
            <?= htmlspecialchars($p['kit_nombre'] ?? '—') ?>
          </div>
          <?php if ($cant > 1): ?>
          <span class="badge bg-secondary" style="font-size:.65rem"><?= $cant ?> uds</span>
          <?php endif; ?>
        </td>
        <!-- Estado (badge visual, sin dropdown) -->
        <td style="text-align:center">
          <span class="badge d-block" style="background:<?= $est['bg'] ?>;color:<?= $est['txt'] ?>;font-size:.7rem">
            <?= strip_tags($est['label']) ?>
          </span>
        </td>
        <!-- Acciones -->
        <td style="text-align:center;white-space:nowrap">
          <div class="d-flex gap-1 align-items-center justify-content-center flex-nowrap">
            <?php if ($p['estado'] === 'pendiente' && $puedeAprobar): ?>
            <form method="POST" style="margin:0">
              <input type="hidden" name="action"    value="aprobar">
              <input type="hidden" name="pedido_id" value="<?= $p['id'] ?>">
              <input type="hidden" name="csrf"      value="<?= Auth::csrfToken() ?>">
              <button type="submit" class="btn btn-success btn-sm"
                      style="font-size:.75rem;white-space:nowrap"
                      onclick="event.stopPropagation();return confirm('¿Aprobar pedido #<?= htmlspecialchars($p['woo_order_id']) ?> y enviar a producción?')">
                <i class="bi bi-check-lg"></i> Aprobar
              </button>
            </form>
            <?php else: ?>
            <a href="ver.php?id=<?= $p['id'] ?>" class="btn btn-outline-secondary btn-sm"
               style="font-size:.75rem;white-space:nowrap"
               onclick="event.stopPropagation()">
              <i class="bi bi-eye"></i> Ver
            </a>
            <?php endif; ?>
            <?php if ($puedeEliminar): ?>
            <form method="POST" style="margin:0">
              <input type="hidden" name="action"    value="eliminar_pedido">
              <input type="hidden" name="pedido_id" value="<?= $p['id'] ?>">
              <input type="hidden" name="csrf"      value="<?= Auth::csrfToken() ?>">
              <button type="submit" class="btn btn-outline-danger btn-sm"
                      style="font-size:.75rem;padding:.25rem .45rem" title="Eliminar pedido"
                      onclick="event.stopPropagation();return confirm('¿Eliminar este pedido? Esta acción no se puede deshacer.')">
                <i class="bi bi-trash"></i>
              </button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPags > 1): ?>
  <div class="px-3 py-2 border-top d-flex align-items-center justify-content-between flex-wrap gap-2">
    <span class="text-muted small">
      Mostrando <?= number_format($offset + 1) ?>–<?= number_format(min($offset + $porPag, $total)) ?>
      de <?= number_format($total) ?> pedidos
    </span>
    <nav><ul class="pagination pagination-sm mb-0">
      <?php
        // ← Anterior
        if ($pagina > 1):
          $pp = array_filter(array_merge($_GET, ['pag' => $pagina - 1]), fn($v) => $v !== '' && $v !== null);
      ?>
      <li class="page-item">
        <a class="page-link" href="?<?= http_build_query($pp) ?>">&#8249;</a>
      </li>
      <?php endif; ?>

      <?php
        // Rango inteligente: primeras 2, últimas 2, y ±2 alrededor de la página actual
        if ($totalPags <= 9) {
            $pRange = range(1, $totalPags);
        } else {
            $pRange = array_unique(array_merge(
                [1, 2],
                range(max(3, $pagina - 2), min($totalPags - 2, $pagina + 2)),
                [$totalPags - 1, $totalPags]
            ));
            sort($pRange);
        }
        $prev = null;
        foreach ($pRange as $i):
          if ($prev !== null && $i - $prev > 1): ?>
      <li class="page-item disabled">
        <span class="page-link" style="padding:.3rem .5rem;pointer-events:none">…</span>
      </li>
      <?php endif;
          $pp = array_filter(array_merge($_GET, ['pag' => $i]), fn($v) => $v !== '' && $v !== null);
      ?>
      <li class="page-item <?= $i === $pagina ? 'active' : '' ?>">
        <a class="page-link" href="?<?= http_build_query($pp) ?>"><?= $i ?></a>
      </li>
      <?php $prev = $i; endforeach; ?>

      <?php
        // → Siguiente
        if ($pagina < $totalPags):
          $pp = array_filter(array_merge($_GET, ['pag' => $pagina + 1]), fn($v) => $v !== '' && $v !== null);
      ?>
      <li class="page-item">
        <a class="page-link" href="?<?= http_build_query($pp) ?>">&#8250;</a>
      </li>
      <?php endif; ?>
    </ul></nav>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- ── Barra flotante selección múltiple ── -->
<div class="barra-sel" id="barra-sel">
  <span style="font-size:.85rem;font-weight:700"><span id="cnt-sel">0</span> pedido(s)</span>
  <div class="d-flex gap-2">
    <button class="btn btn-danger btn-sm fw-bold" onclick="imprimirSeleccionados()">
      <i class="bi bi-printer me-1"></i>Stickers
    </button>
    <button class="btn btn-outline-light btn-sm" onclick="deselTodos()">Cancelar</button>
  </div>
</div>

<?php if (Auth::isAdmin()): ?>
<!-- ── Zona de riesgo ── -->
<div style="border:2px solid #fca5a5;border-radius:14px;padding:1rem 1.2rem;margin-bottom:1.5rem;background:#fff5f5">
  <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
    <div class="d-flex align-items-center gap-2">
      <i class="bi bi-exclamation-triangle-fill text-danger fs-5"></i>
      <div>
        <div class="fw-bold text-danger" style="font-size:.9rem">Zona de riesgo — Solo para pruebas</div>
        <div class="text-muted" style="font-size:.75rem">
          Herramienta para limpiar la base y re-importar CSVs durante desarrollo.
          <?php if ($modoLimpieza): ?>
            <span class="badge bg-danger ms-1">MODO LIMPIEZA ACTIVO</span>
          <?php else: ?>
            <span class="badge bg-secondary ms-1">desactivado</span>
          <?php endif; ?>
        </div>
      </div>
    </div>
    <form method="POST" style="margin:0">
      <input type="hidden" name="action" value="toggle_modo_limpieza">
      <input type="hidden" name="csrf"   value="<?= Auth::csrfToken() ?>">
      <?php if ($modoLimpieza): ?>
        <button type="submit" class="btn btn-sm btn-outline-danger fw-bold"
                onclick="return confirm('¿Desactivar el modo limpieza?')">
          <i class="bi bi-lock me-1"></i>Desactivar modo limpieza
        </button>
      <?php else: ?>
        <button type="submit" class="btn btn-sm btn-outline-secondary"
                onclick="return confirm('¿Activar el modo limpieza? Esto habilitará el borrado masivo de pedidos.')">
          <i class="bi bi-unlock me-1"></i>Activar modo limpieza
        </button>
      <?php endif; ?>
    </form>
  </div>

  <?php if ($modoLimpieza): ?>
  <?php $totalPedidos = (int)$db->query("SELECT COUNT(*) FROM tienda_pedidos")->fetchColumn(); ?>
  <?php $totalSolicitudes = $db->query("SHOW TABLES LIKE 'solicitudes_produccion'")->fetchColumn()
      ? (int)$db->query("SELECT COUNT(*) FROM solicitudes_produccion WHERE fuente='tienda'")->fetchColumn()
      : 0; ?>
  <hr style="border-color:#fca5a5;margin:.85rem 0">
  <form method="POST" onsubmit="return validarLimpieza(this)">
    <input type="hidden" name="action" value="limpiar_todos_pedidos">
    <input type="hidden" name="csrf"   value="<?= Auth::csrfToken() ?>">
    <div class="mb-3">
      <div style="font-size:.82rem;color:#991b1b;font-weight:600;margin-bottom:.5rem">
        <i class="bi bi-trash3 me-1"></i>
        Se eliminarán <strong><?= number_format($totalPedidos) ?> pedidos</strong>
        y su historial de estados. Esta acción <u>no se puede deshacer</u>.
      </div>
      <?php if ($totalSolicitudes > 0): ?>
      <div class="form-check mb-2">
        <input class="form-check-input" type="checkbox" name="borrar_solicitudes" value="1" id="chk-sol">
        <label class="form-check-label" for="chk-sol" style="font-size:.82rem">
          También eliminar <strong><?= number_format($totalSolicitudes) ?> solicitudes de producción</strong>
          vinculadas a pedidos tienda
          <span class="text-muted">(fuente=tienda)</span>
        </label>
      </div>
      <?php endif; ?>
      <div class="form-check">
        <input class="form-check-input border-danger" type="checkbox" name="confirmar" value="1" id="chk-confirm">
        <label class="form-check-label fw-bold text-danger" for="chk-confirm" style="font-size:.82rem">
          Confirmo que quiero eliminar todos los pedidos de la tienda
        </label>
      </div>
    </div>
    <button type="submit" class="btn btn-danger btn-sm fw-bold">
      <i class="bi bi-trash3-fill me-1"></i>Eliminar todo
    </button>
  </form>
  <?php endif; ?>
</div>
<?php endif; ?>

<script>
function validarLimpieza(form) {
    if (!form.querySelector('[name="confirmar"]').checked) {
        alert('Debes marcar la casilla de confirmación para proceder.');
        return false;
    }
    return confirm('¿Estás seguro? Se borrarán TODOS los pedidos. Esta acción es irreversible.');
}
function actualizarSel() {
    var chks  = document.querySelectorAll('.chk-pedido:checked');
    var todos = document.querySelectorAll('.chk-pedido');
    var n     = chks.length;
    document.getElementById('cnt-sel').textContent = n;
    document.querySelectorAll('.chk-pedido').forEach(function(c) {
        c.closest('tr').classList.toggle('fila-sel', c.checked);
    });
    document.getElementById('barra-sel').classList.toggle('visible', n > 0);
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
