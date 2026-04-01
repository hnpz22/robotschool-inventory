<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();
Auth::requirePermiso('produccion','ver');

$db         = Database::get();
$pageTitle  = 'Tablero de Produccion';
$activeMenu = 'produccion';
$error = $success = '';
if (($_GET['msg'] ?? '') === 'al_ok') $success = 'Pedido enviado a alistamiento correctamente.';
$userId = Auth::user()['id'];

// Fuentes y sus metadatos
$FUENTES = [
    'tienda'     => ['label'=>'Tienda Online',    'icon'=>'bi-shop',           'color'=>'#185FA5', 'bg'=>'#eff6ff'],
    'comercial'  => ['label'=>'Comercial',         'icon'=>'bi-briefcase',      'color'=>'#7c3aed', 'bg'=>'#faf5ff'],
    'colegio'    => ['label'=>'Colegio',           'icon'=>'bi-building',       'color'=>'#0891b2', 'bg'=>'#e0f2fe'],
    'cliente'    => ['label'=>'Cliente Directo',   'icon'=>'bi-person',         'color'=>'#16a34a', 'bg'=>'#f0fdf4'],
    'salon'      => ['label'=>'Salon / Escuela',   'icon'=>'bi-mortarboard',    'color'=>'#d97706', 'bg'=>'#fffbeb'],
    'interno'    => ['label'=>'Interno',           'icon'=>'bi-gear',           'color'=>'#64748b', 'bg'=>'#f8fafc'],
];

$ESTADOS = [
    'pendiente'   => ['label'=>'Pendiente',    'color'=>'#dc2626', 'bg'=>'#fee2e2', 'icon'=>'bi-clock'],
    'en_proceso'  => ['label'=>'En proceso',   'color'=>'#d97706', 'bg'=>'#fef9c3', 'icon'=>'bi-tools'],
    'listo'       => ['label'=>'Listo',        'color'=>'#16a34a', 'bg'=>'#dcfce7', 'icon'=>'bi-check-circle'],
    'rechazado'   => ['label'=>'Rechazado',    'color'=>'#64748b', 'bg'=>'#f1f5f9', 'icon'=>'bi-x-circle'],
];

$PRIORIDADES = [
    1 => ['label'=>'URGENTE',  'color'=>'#dc2626'],
    2 => ['label'=>'Alta',     'color'=>'#d97706'],
    3 => ['label'=>'Normal',   'color'=>'#185FA5'],
    4 => ['label'=>'Baja',     'color'=>'#64748b'],
];

$TIPOS = [
    'armar_kit'       => ['label'=>'Armar Kit',          'icon'=>'bi-boxes'],
    'verificar_stock' => ['label'=>'Verificar Stock',    'icon'=>'bi-search'],
    'personalizar'    => ['label'=>'Personalizar',       'icon'=>'bi-pencil-square'],
    'urgente'         => ['label'=>'Urgente',             'icon'=>'bi-lightning-charge'],
];

// ── CAMBIAR ESTADO ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='cambiar_estado') {
    if (!Auth::csrfVerify($_POST['csrf']??'')) die('CSRF');
    try {
        $sid    = (int)$_POST['solicitud_id'];
        $estado = $_POST['estado'];
        $notas  = trim($_POST['notas_respuesta'] ?? '');
        
        $extra = $estado === 'listo' ? ", completado_at=NOW()" : "";
        $db->prepare("UPDATE solicitudes_produccion SET estado=?, notas_respuesta=? $extra WHERE id=?")
           ->execute([$estado, $notas ?: null, $sid]);
        
        // Historial
        $db->prepare("INSERT INTO solicitud_historial (solicitud_id,estado,usuario_id,comentario) VALUES (?,?,?,?)")
           ->execute([$sid, $estado, $userId, $notas ?: null]);

        // Sincronizar estado en tienda_pedidos para todos los cambios relevantes
        $pedidoId = $db->query("SELECT pedido_id FROM solicitudes_produccion WHERE id=$sid")->fetchColumn();
        if ($pedidoId) {
            $estadoTienda = match($estado) {
                'en_proceso' => 'en_produccion',
                'listo'      => 'listo_produccion',
                'pendiente'  => 'aprobado',
                'rechazado'  => 'pendiente',
                default      => null,
            };
            if ($estadoTienda) {
                $estActualTienda = $db->query("SELECT estado FROM tienda_pedidos WHERE id=$pedidoId")->fetchColumn();
                $db->prepare("UPDATE tienda_pedidos SET estado=?, updated_at=NOW() WHERE id=?")
                   ->execute([$estadoTienda, $pedidoId]);
                $db->prepare("INSERT INTO tienda_pedidos_historial (pedido_id,estado_ant,estado_nuevo,nota,usuario_id) VALUES (?,?,?,?,?)")
                   ->execute([$pedidoId, $estActualTienda, $estadoTienda, 'Sincronizado desde producción (' . $estado . ')', $userId]);
            }
        }
        $success = 'Estado actualizado correctamente.';
    } catch (Exception $e) { $error = $e->getMessage(); }
}

// ── NUEVA SOLICITUD ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='nueva_solicitud') {
    if (!Auth::csrfVerify($_POST['csrf']??'')) die('CSRF');
    try {
        $data = [
            'fuente'       => $_POST['fuente'] ?? 'interno',
            'titulo'       => trim($_POST['titulo']),
            'tipo'         => $_POST['tipo'] ?? 'armar_kit',
            'kit_nombre'   => trim($_POST['kit_nombre'] ?? '') ?: null,
            'cantidad'     => (int)($_POST['cantidad'] ?? 1),
            'prioridad'    => (int)($_POST['prioridad'] ?? 3),
            'fecha_limite' => $_POST['fecha_limite'] ?: null,
            'notas'        => trim($_POST['notas'] ?? '') ?: null,
            'descripcion'  => trim($_POST['descripcion'] ?? '') ?: null,
            'estado'       => 'pendiente',
            'solicitado_por' => $userId,
            'pedido_id'    => ($_POST['pedido_id'] ?: null),
            'convenio_id'  => ($_POST['convenio_id'] ?: null),
            'colegio_id'   => ($_POST['colegio_id'] ?: null),
        ];
        $c2 = implode(',', array_keys($data));
        $v2 = ':'.implode(',:', array_keys($data));
        $db->prepare("INSERT INTO solicitudes_produccion ($c2) VALUES ($v2)")->execute($data);
        $newId = $db->lastInsertId();

        // Historial
        $db->prepare("INSERT INTO solicitud_historial (solicitud_id,estado,usuario_id,comentario) VALUES (?,?,?,?)")
           ->execute([$newId, 'pendiente', $userId, 'Solicitud creada']);

        $success = 'Solicitud de produccion creada.';
    } catch (Exception $e) { $error = $e->getMessage(); }
}

// ── ENVIAR A ALISTAMIENTO ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='enviar_alistamiento') {
    if (!Auth::csrfVerify($_POST['csrf']??'')) die('CSRF');
    try {
        $sid      = (int)$_POST['solicitud_id'];
        $pidTienda = $db->query("SELECT pedido_id FROM solicitudes_produccion WHERE id=$sid")->fetchColumn();
        if (!$pidTienda) throw new RuntimeException('Esta solicitud no tiene pedido de tienda vinculado.');
        $estActual = $db->query("SELECT estado FROM tienda_pedidos WHERE id=$pidTienda")->fetchColumn();
        if ($estActual !== 'listo_produccion') throw new RuntimeException('El pedido debe estar en estado Listo producción (actual: '.$estActual.').');
        $db->prepare("UPDATE tienda_pedidos SET estado='en_alistamiento', updated_at=NOW() WHERE id=?")->execute([$pidTienda]);
        $db->prepare("INSERT INTO tienda_pedidos_historial (pedido_id,estado_ant,estado_nuevo,nota,usuario_id) VALUES (?,?,?,?,?)")
           ->execute([$pidTienda, $estActual, 'en_alistamiento', 'Enviado a alistamiento desde producción', $userId]);
        header('Location: ' . APP_URL . '/modules/produccion/?msg=al_ok');
        exit;
    } catch (Exception $e) { $error = $e->getMessage(); }
}

// ── ELIMINAR ────────────────────────────────────────────────────
if (isset($_GET['del']) && Auth::csrfVerify($_GET['csrf']??'')) {
    Auth::requireAdmin();
    $db->prepare("DELETE FROM solicitudes_produccion WHERE id=?")->execute([(int)$_GET['del']]);
    $success = 'Solicitud eliminada.';
}

// ── DATOS ───────────────────────────────────────────────────────
$fEstado = $_GET['estado'] ?? '';
$fFuente = $_GET['fuente'] ?? '';
$fPrior  = $_GET['prior']  ?? '';

$where = ["1=1"];
if ($fEstado) $where[] = "s.estado=".$db->quote($fEstado);
if ($fFuente) $where[] = "s.fuente=".$db->quote($fFuente);
if ($fPrior)  $where[] = "s.prioridad=".(int)$fPrior;
// Excluir solicitudes cuyo pedido ya está en alistamiento o despachado
$where[] = "(s.pedido_id IS NULL OR tp.estado IS NULL OR tp.estado NOT IN ('en_alistamiento','listo_envio','despachado','entregado','cancelado'))";
$whereStr = implode(' AND ', $where);

// Verificar columnas opcionales que dependen de migraciones
$colsFuente  = $db->query("SHOW COLUMNS FROM solicitudes_produccion LIKE 'fuente'")->fetchColumn();
$colsTitulo  = $db->query("SHOW COLUMNS FROM solicitudes_produccion LIKE 'titulo'")->fetchColumn();
$colsConvId  = $db->query("SHOW COLUMNS FROM solicitudes_produccion LIKE 'convenio_id'")->fetchColumn();
$colsColId   = $db->query("SHOW COLUMNS FROM solicitudes_produccion LIKE 'colegio_id'")->fetchColumn();
$tablaConv   = $db->query("SHOW TABLES LIKE 'convenios'")->fetchColumn();
$tablaSolIt  = $db->query("SHOW TABLES LIKE 'solicitud_items'")->fetchColumn();

$selFuente   = $colsFuente  ? "s.fuente,"                          : "'tienda' AS fuente,";
$selTitulo   = $colsTitulo  ? "s.titulo, s.descripcion,"           : "NULL AS titulo, NULL AS descripcion,";
$selConvId   = $colsConvId  ? "s.convenio_id,"                     : "NULL AS convenio_id,";
$selColId    = $colsColId   ? "s.colegio_id,"                      : "NULL AS colegio_id,";
$joinConv    = ($tablaConv && $colsConvId) ? "LEFT JOIN convenios co ON co.id=s.convenio_id" : "";
$joinCol     = $colsColId   ? "LEFT JOIN colegios col ON col.id=s.colegio_id" : "";
$selConvNom  = ($tablaConv && $colsConvId)  ? "co.nombre_colegio AS conv_colegio," : "NULL AS conv_colegio,";
$selColNom   = $colsColId   ? "col.nombre AS colegio_nombre,"      : "NULL AS colegio_nombre,";
$selItems    = $tablaSolIt  ? "(SELECT COUNT(*) FROM solicitud_items si WHERE si.solicitud_id=s.id) AS num_items,
      (SELECT COUNT(*) FROM solicitud_items si WHERE si.solicitud_id=s.id AND si.listo=1) AS items_listos," : "0 AS num_items, 0 AS items_listos,";

$solicitudes = $db->query("
    SELECT s.id, s.tipo, s.estado, s.kit_nombre, s.cantidad, s.prioridad,
           s.notas, s.notas_respuesta, s.solicitado_por, s.asignado_a,
           s.fecha_limite, s.completado_at, s.created_at, s.updated_at,
           s.pedido_id,
           $selFuente
           $selTitulo
           $selConvId
           $selColId
           u1.nombre AS solicitado_nombre,
           u2.nombre AS asignado_nombre,
           tp.woo_order_id AS tienda_pedido,
      tp.colegio_nombre AS tienda_colegio,
           $selConvNom
           $selColNom
           $selItems
           1 AS _ok
    FROM solicitudes_produccion s
    LEFT JOIN usuarios u1       ON u1.id=s.solicitado_por
    LEFT JOIN usuarios u2       ON u2.id=s.asignado_a
    LEFT JOIN tienda_pedidos tp ON tp.id=s.pedido_id
    $joinConv
    $joinCol
    WHERE $whereStr
    ORDER BY s.prioridad ASC, s.created_at ASC
")->fetchAll();

// Stats por estado
$stats = [];
foreach ($ESTADOS as $ek => $ev) {
    $stats[$ek] = (int)$db->query("SELECT COUNT(*) FROM solicitudes_produccion WHERE estado=".$db->quote($ek))->fetchColumn();
}
$statsF = [];
foreach ($FUENTES as $fk => $fv) {
    $cnt = (int)$db->query("SELECT COUNT(*) FROM solicitudes_produccion WHERE fuente=".$db->quote($fk)." AND estado NOT IN ('listo','rechazado')")->fetchColumn();
    if ($cnt > 0) $statsF[$fk] = $cnt;
}

$kits      = $db->query("SELECT id,nombre FROM kits WHERE activo=1 ORDER BY nombre")->fetchAll();
$colegios  = $db->query("SELECT id,nombre FROM colegios WHERE activo=1 ORDER BY nombre")->fetchAll();
$convs     = $db->query("SHOW TABLES LIKE 'convenios'")->fetchColumn() ?
             $db->query("SELECT id,codigo,nombre_colegio FROM convenios WHERE activo=1 AND estado='aprobado' ORDER BY nombre_colegio")->fetchAll() : [];
$pedidos   = $db->query("SELECT id,woo_order_id AS numero_pedido FROM tienda_pedidos WHERE estado NOT IN ('entregado','cancelado') ORDER BY created_at DESC LIMIT 50")->fetchAll();
$usuarios  = $db->query("SELECT id,nombre FROM usuarios WHERE activo=1 ORDER BY nombre")->fetchAll();

// ── Historial batch: último movimiento por solicitud ─────────────
$histMap = [];
$solIds  = array_column($solicitudes, 'id');
if (!empty($solIds)) {
    $inList   = implode(',', array_map('intval', $solIds));
    $histRows = $db->query("
        SELECT h.solicitud_id, h.estado, h.comentario, h.created_at, u.nombre AS usuario_nombre
        FROM solicitud_historial h
        LEFT JOIN usuarios u ON u.id = h.usuario_id
        WHERE h.solicitud_id IN ($inList)
        ORDER BY h.solicitud_id, h.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($histRows as $hr) {
        $sid = $hr['solicitud_id'];
        if (!isset($histMap[$sid])) $histMap[$sid] = [$hr]; // solo el último
    }
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
/* ── Kanban layout ── */
.tablero-col{background:#fff;border-radius:14px;border:1.5px solid #e2e8f0;overflow:hidden}
.tablero-col.collapsed .col-body{display:none}
.col-header{padding:.6rem .9rem;display:flex;align-items:center;justify-content:space-between;
            cursor:pointer;user-select:none;transition:.12s}
.col-header:hover{filter:brightness(.97)}
.col-toggle-arrow{transition:transform .2s;font-size:.8rem}
.tablero-col.collapsed .col-toggle-arrow{transform:rotate(-90deg)}
.col-body{padding:.55rem;min-height:60px}

/* ── Tarjeta solicitud ── */
.sol-card{background:#fff;border-radius:10px;border:1px solid #e2e8f0;margin-bottom:.5rem;overflow:hidden;transition:.12s}
.sol-card:hover{box-shadow:0 2px 10px rgba(0,0,0,.07)}
.sol-compact{padding:.5rem .65rem}
.sol-detail{padding:.5rem .65rem .6rem;border-top:1px solid #f1f5f9;display:none}
.sol-detail.open{display:block}

/* ── Prioridad badge ── */
.prior-badge{font-size:.62rem;font-weight:700;padding:.1rem .4rem;border-radius:10px;
             display:inline-flex;align-items:center;gap:.2rem;white-space:nowrap}
/* ── Fuente badge ── */
.fuente-badge{font-size:.62rem;padding:.1rem .4rem;border-radius:10px;font-weight:600;
              display:inline-flex;align-items:center;gap:.2rem}
/* ── Progress bar ── */
.prog-bar{height:3px;border-radius:2px;background:#e2e8f0;overflow:hidden;margin-top:3px}
.prog-fill{height:100%;background:#22c55e;border-radius:2px}

/* ── Historial ── */
.hist-row{font-size:.71rem;color:#64748b;padding:.15rem 0;border-bottom:1px solid #f8fafc}
.hist-row:last-child{border-bottom:none}


/* ── Stat chips arriba ── */
.stat-chip-sm{display:inline-flex;align-items:center;gap:.3rem;padding:.2rem .6rem;border-radius:10px;
              font-size:.75rem;font-weight:600;text-decoration:none;border:1px solid transparent;
              cursor:pointer;transition:.1s}
.stat-chip-sm:hover{filter:brightness(.95)}

/* ── Modal nueva solicitud ── */
#modalNueva .modal-body{overflow-y:auto!important;max-height:calc(100vh - 200px)!important}
#modalNueva .modal-dialog{margin:.5rem auto}
</style>

<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-kanban me-2"></i>Tablero de Producci&oacute;n</h4>
    <p class="text-muted small mb-0">Solicitudes de todas las fuentes &mdash; <?= array_sum($stats) ?> total</p>
  </div>
  <div class="d-flex gap-2">
    <a href="cronograma.php" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-calendar3 me-1"></i>Cronograma
    </a>
    <button class="btn btn-primary btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalNueva">
      <i class="bi bi-plus-lg me-1"></i>Nueva Solicitud
    </button>
  </div>
</div>

<?php if ($error):   ?><div class="alert alert-danger  py-2 small"><?= htmlspecialchars($error)   ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<!-- ── Chips de estado + filtro fuente ── -->
<div class="d-flex gap-2 flex-wrap align-items-center mb-3">
  <?php foreach ($ESTADOS as $ek => $ev): ?>
  <a href="?estado=<?= $ek ?><?= $fFuente ? "&fuente=$fFuente" : '' ?>"
     class="stat-chip-sm <?= $fEstado===$ek?'activo':'' ?>"
     style="background:<?= $ev['bg'] ?>;color:<?= $ev['color'] ?>;border-color:<?= $ev['color'] ?>40">
    <i class="bi <?= $ev['icon'] ?>" style="font-size:.65rem"></i>
    <?= $ev['label'] ?>
    <strong><?= $stats[$ek] ?></strong>
  </a>
  <?php endforeach; ?>
  <span style="border-left:1px solid #e2e8f0;height:18px;margin:0 .1rem"></span>
  <?php foreach ($FUENTES as $fk => $fv): if (!isset($statsF[$fk])) continue; ?>
  <a href="?fuente=<?= $fk ?><?= $fEstado?"&estado=$fEstado":'' ?>"
     class="stat-chip-sm <?= $fFuente===$fk?'activo':'' ?>"
     style="background:<?= $fv['bg'] ?>;color:<?= $fv['color'] ?>;border-color:<?= $fv['color'] ?>40">
    <i class="bi <?= $fv['icon'] ?>" style="font-size:.65rem"></i><?= $fv['label'] ?>
    <strong><?= $statsF[$fk] ?></strong>
  </a>
  <?php endforeach; ?>
  <?php if ($fEstado || $fFuente): ?>
  <a href="?" class="stat-chip-sm ms-1" style="background:#f1f5f9;color:#475569;border-color:#e2e8f0">
    &#10005; Limpiar
  </a>
  <?php endif; ?>
</div>

<!-- ══ TABLERO KANBAN (3 columnas) ══ -->
<?php
// Solo las 3 columnas del flujo activo; Rechazado queda fuera del kanban
$KANBAN_COLS = ['pendiente', 'en_proceso', 'listo'];
$KANBAN_ACCIONES = [
    'pendiente'  => ['avanzar' => ['estado' => 'en_proceso', 'label' => '&#9654; Iniciar',  'cls' => 'btn-primary'],
                     'retroceder' => null],
    'en_proceso' => ['avanzar' => ['estado' => 'listo',      'label' => '&#10003; Listo',   'cls' => 'btn-success'],
                     'retroceder' => ['estado' => 'pendiente', 'label' => '&#8629; Pendiente', 'cls' => 'btn-outline-secondary']],
    'listo'      => ['avanzar' => null,
                     'retroceder' => ['estado' => 'en_proceso','label' => '&#8629; En proceso','cls' => 'btn-outline-secondary']],
];
?>
<div class="row g-3">
<?php foreach ($KANBAN_COLS as $ek):
  $ev   = $ESTADOS[$ek];
  $cols = array_values(array_filter($solicitudes, fn($s) => $s['estado'] === $ek));
  if ($fEstado && $fEstado !== $ek) continue;
?>
<div class="col-md-4">
  <div class="tablero-col" id="kcol-<?= $ek ?>">

    <!-- Header colapsable -->
    <div class="col-header" style="background:<?= $ev['bg'] ?>;color:<?= $ev['color'] ?>"
         onclick="toggleCol('<?= $ek ?>')">
      <span class="fw-bold" style="font-size:.82rem">
        <i class="bi <?= $ev['icon'] ?> me-1"></i><?= $ev['label'] ?>
        <span class="badge ms-1" style="background:<?= $ev['color'] ?>;color:#fff;font-size:.65rem">
          <?= count($cols) ?>
        </span>
      </span>
      <i class="bi bi-chevron-down col-toggle-arrow"></i>
    </div>

    <!-- Cuerpo de columna -->
    <div class="col-body" id="kbody-<?= $ek ?>">
      <?php if (empty($cols)): ?>
        <div class="text-center text-muted py-4" style="font-size:.77rem">
          <i class="bi bi-inbox d-block mb-1" style="font-size:1.2rem"></i>Sin solicitudes
        </div>
      <?php endif; ?>

      <?php foreach ($cols as $s):
        $fu  = $FUENTES[$s['fuente']] ?? $FUENTES['interno'];
        $pr  = $PRIORIDADES[$s['prioridad']] ?? $PRIORIDADES[3];
        $pct = $s['num_items'] > 0 ? round($s['items_listos']/$s['num_items']*100) : 0;
        $nombreColegio = $s['colegio_nombre'] ?: $s['conv_colegio'] ?: ($s['tienda_colegio'] ?? null);
        $acc = $KANBAN_ACCIONES[$ek];

        $diasRest = '';
        if ($s['fecha_limite']) {
            $diff = (new DateTime($s['fecha_limite']))->diff(new DateTime('today'));
            $diasRest = $diff->invert
                ? '<span class="text-danger fw-semibold">Vencido '.$diff->days.'d</span>'
                : ($diff->days == 0 ? '<span class="text-danger fw-semibold">Hoy!</span>'
                                    : '<span class="text-muted">'.$diff->days.'d restantes</span>');
        }
        $hist = $histMap[$s['id']] ?? [];
      ?>
      <div class="sol-card" style="border-left:3px solid <?= $pr['color'] ?>">

        <!-- Vista compacta (siempre visible) -->
        <div class="sol-compact">

          <!-- Fila 1: prioridad + fuente + expand arrow -->
          <div class="d-flex align-items-center justify-content-between mb-1">
            <div class="d-flex align-items-center gap-1 flex-wrap">
              <span class="prior-badge" style="background:<?= $pr['color'] ?>22;color:<?= $pr['color'] ?>">
                <?= htmlspecialchars($pr['label']) ?>
              </span>
              <span class="fuente-badge" style="background:<?= $fu['bg'] ?>;color:<?= $fu['color'] ?>">
                <i class="bi <?= $fu['icon'] ?>" style="font-size:.6rem"></i><?= $fu['label'] ?>
              </span>
              <?php if ($s['tienda_pedido']): ?>
              <span class="fw-bold" style="font-size:.75rem;color:#1e293b">
                #<?= htmlspecialchars($s['tienda_pedido']) ?>
              </span>
              <?php endif; ?>
            </div>
            <div class="d-flex align-items-center gap-1">
              <?php if (Auth::isAdmin()): ?>
              <a href="?del=<?= $s['id'] ?>&csrf=<?= Auth::csrfToken() ?>"
                 class="text-danger" style="font-size:.75rem;line-height:1"
                 onclick="return confirm('¿Eliminar esta solicitud?')" title="Eliminar">
                <i class="bi bi-trash3"></i>
              </a>
              <?php endif; ?>
              <button type="button" class="btn p-0 border-0" style="color:#94a3b8;font-size:.8rem;line-height:1"
                      onclick="toggleCard(<?= $s['id'] ?>)" title="Ver detalle" id="arrow-<?= $s['id'] ?>">
                <i class="bi bi-chevron-down"></i>
              </button>
            </div>
          </div>

          <!-- Fila 2: nombre del kit -->
          <?php
          // Mostrar kit_nombre como título principal.
          // Si no hay kit_nombre, limpiar el título eliminando prefijo "Pedido Tienda: " y el sufijo " — Cliente".
          if ($s['kit_nombre']) {
              $displayKit = $s['kit_nombre'];
          } else {
              $t = $s['titulo'] ?? 'Sin título';
              $t = preg_replace('/^Pedido\s+Tienda:\s*/i', '', $t);
              $t = preg_replace('/\s*—\s*.+$/', '', $t);
              $displayKit = trim($t) ?: 'Sin título';
          }
          ?>
          <div class="fw-semibold" style="font-size:.81rem;line-height:1.3;margin-bottom:.2rem">
            <?= htmlspecialchars($displayKit) ?>
          </div>

          <!-- Fila 3: cantidad + colegio -->
          <div class="d-flex gap-2 align-items-center flex-wrap" style="font-size:.71rem;color:#64748b;margin-bottom:.35rem">
            <span><i class="bi bi-boxes me-1"></i><strong><?= $s['cantidad'] ?></strong> uds</span>
            <?php if ($nombreColegio): ?>
            <span style="color:#0891b2"><i class="bi bi-building me-1"></i><?= htmlspecialchars(mb_strimwidth($nombreColegio,0,28,'…')) ?></span>
            <?php endif; ?>
            <?php if ($diasRest): ?><span><?= $diasRest ?></span><?php endif; ?>
          </div>

          <?php if ($s['num_items'] > 0): ?>
          <div class="prog-bar mb-2"><div class="prog-fill" style="width:<?= $pct ?>%"></div></div>
          <?php endif; ?>

          <!-- Botones de acción inline -->
          <div class="d-flex gap-1 flex-wrap">
            <?php if ($acc['avanzar']): ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action"       value="cambiar_estado">
              <input type="hidden" name="csrf"         value="<?= Auth::csrfToken() ?>">
              <input type="hidden" name="solicitud_id" value="<?= $s['id'] ?>">
              <input type="hidden" name="estado"       value="<?= $acc['avanzar']['estado'] ?>">
              <button type="submit" class="btn btn-sm <?= $acc['avanzar']['cls'] ?>">
                <?= $acc['avanzar']['label'] ?>
              </button>
            </form>
            <?php endif; ?>

            <?php if ($acc['retroceder']): ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action"       value="cambiar_estado">
              <input type="hidden" name="csrf"         value="<?= Auth::csrfToken() ?>">
              <input type="hidden" name="solicitud_id" value="<?= $s['id'] ?>">
              <input type="hidden" name="estado"       value="<?= $acc['retroceder']['estado'] ?>">
              <button type="submit" class="btn btn-sm <?= $acc['retroceder']['cls'] ?>">
                <?= $acc['retroceder']['label'] ?>
              </button>
            </form>
            <?php endif; ?>

            <?php if ($ek === 'listo' && $s['pedido_id']): ?>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action"       value="enviar_alistamiento">
              <input type="hidden" name="csrf"         value="<?= Auth::csrfToken() ?>">
              <input type="hidden" name="solicitud_id" value="<?= $s['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-box-seam me-1"></i>→ Alistamiento
              </button>
            </form>
            <?php endif; ?>
          </div>
        </div>

        <!-- Vista expandida (toggle) -->
        <div class="sol-detail" id="detail-<?= $s['id'] ?>">
          <div class="row g-2" style="font-size:.75rem">

            <?php if ($s['fecha_limite']): ?>
            <?php
              $limDiff  = (new DateTime($s['fecha_limite']))->diff(new DateTime('today'));
              $limColor = $limDiff->invert
                  ? '#dc2626'
                  : ($limDiff->days == 0 ? '#dc2626' : ($limDiff->days <= 3 ? '#d97706' : '#16a34a'));
            ?>
            <div class="col-12">
              <div class="d-flex align-items-center gap-2 p-2 rounded"
                   style="background:<?= $limColor ?>18;border:1px solid <?= $limColor ?>40">
                <i class="bi bi-calendar-event" style="color:<?= $limColor ?>"></i>
                <span style="color:<?= $limColor ?>;font-weight:700;font-size:.82rem">
                  <?= date('d/m/Y', strtotime($s['fecha_limite'])) ?>
                </span>
                <?php if ($diasRest): ?><span><?= $diasRest ?></span><?php endif; ?>
              </div>
            </div>
            <?php endif; ?>

            <?php
            // Notas: omitir si contienen datos de cliente ("Cliente: …")
            $notasLimpias = (isset($s['notas']) && stripos($s['notas'], 'Cliente:') === 0)
                ? null : ($s['notas'] ?? null);
            ?>
            <?php if ($notasLimpias): ?>
            <div class="col-12">
              <span class="text-muted">Notas:</span> <?= htmlspecialchars($notasLimpias) ?>
            </div>
            <?php endif; ?>

            <?php if ($s['tienda_pedido'] && $s['pedido_id']): ?>
            <div class="col-12">
              <span class="text-muted">Pedido:</span>
              <a href="<?= APP_URL ?>/modules/pedidos_tienda/ver.php?id=<?= $s['pedido_id'] ?>"
                 style="font-weight:600;color:#1e293b" target="_blank">
                #<?= htmlspecialchars($s['tienda_pedido']) ?>
              </a>
            </div>
            <?php endif; ?>

            <?php if ($s['descripcion'] ?? null): ?>
            <div class="col-12">
              <span class="text-muted">Descripci&oacute;n:</span>
              <?= htmlspecialchars($s['descripcion']) ?>
            </div>
            <?php endif; ?>

            <div class="col-12" style="color:#94a3b8">
              Creado por <?= htmlspecialchars($s['solicitado_nombre'] ?? '—') ?>
              &middot; <?= date('d/m/Y', strtotime($s['created_at'])) ?>
            </div>

            <?php if (!empty($hist)): ?>
            <?php $hr = $hist[0]; $hEv = $ESTADOS[$hr['estado']] ?? ['label'=>$hr['estado'],'color'=>'#64748b']; ?>
            <div class="col-12 mt-1 pt-1" style="border-top:1px solid #f1f5f9">
              <span class="text-muted" style="font-size:.68rem;text-transform:uppercase;letter-spacing:.04em">Último estado</span>
              <div class="d-flex align-items-center gap-2 mt-1">
                <span style="color:<?= $hEv['color'] ?>;font-weight:600"><?= htmlspecialchars($hEv['label']) ?></span>
                <?php if ($hr['usuario_nombre']): ?>
                <span class="text-muted">· <?= htmlspecialchars($hr['usuario_nombre']) ?></span>
                <?php endif; ?>
                <span class="ms-auto text-muted" style="white-space:nowrap">
                  <?= date('d/m H:i', strtotime($hr['created_at'])) ?>
                </span>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </div>

      </div><!-- /.sol-card -->
      <?php endforeach; ?>
    </div><!-- /.col-body -->
  </div><!-- /.tablero-col -->
</div>
<?php endforeach; ?>
</div><!-- /.row -->

<!-- ── MODAL NUEVA SOLICITUD (intacto) ── -->
<div class="modal fade" id="modalNueva" tabindex="-1">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content" style="border-radius:14px">
      <div class="modal-header" style="background:#1e293b;color:#fff;border-radius:14px 14px 0 0">
        <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>Nueva Solicitud de Producci&oacute;n</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="nueva_solicitud">
        <input type="hidden" name="csrf"   value="<?= Auth::csrfToken() ?>">
        <div class="modal-body" style="overflow-y:auto;max-height:65vh">
          <div class="row g-3">

            <div class="col-12">
              <label class="form-label small fw-semibold">Origen / Fuente *</label>
              <div class="d-flex gap-2 flex-wrap">
                <?php foreach ($FUENTES as $fk => $fv): ?>
                <button type="button" class="btn btn-sm btn-fuente"
                        style="background:<?= $fv['bg'] ?>;color:<?= $fv['color'] ?>;border:1.5px solid <?= $fv['color'] ?>40"
                        data-fuente="<?= $fk ?>" onclick="selFuente('<?= $fk ?>', this)">
                  <i class="bi <?= $fv['icon'] ?> me-1"></i><?= $fv['label'] ?>
                </button>
                <?php endforeach; ?>
                <input type="hidden" name="fuente" id="fuenteHidden" value="interno">
              </div>
            </div>

            <div class="col-12">
              <label class="form-label small fw-semibold">T&iacute;tulo de la solicitud *</label>
              <input type="text" name="titulo" class="form-control" required
                     placeholder="Ej: 15 kits Arduino para Colegio San Andres - Convenio 2025">
            </div>

            <div class="col-md-4">
              <label class="form-label small fw-semibold">Tipo</label>
              <select name="tipo" class="form-select">
                <?php foreach ($TIPOS as $tk => $tv): ?>
                  <option value="<?= $tk ?>"><?= $tv['label'] ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Prioridad</label>
              <select name="prioridad" class="form-select">
                <?php foreach ($PRIORIDADES as $pk => $pv): ?>
                  <option value="<?= $pk ?>" <?= $pk===3?'selected':'' ?>><?= $pv['label'] ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Fecha l&iacute;mite</label>
              <input type="date" name="fecha_limite" class="form-control" min="<?= date('Y-m-d') ?>">
            </div>

            <div class="col-md-8">
              <label class="form-label small fw-semibold">Kit / Producto principal</label>
              <input type="text" name="kit_nombre" class="form-control"
                     list="kitsList" placeholder="Nombre del kit o producto...">
              <datalist id="kitsList">
                <?php foreach ($kits as $k): ?>
                  <option value="<?= htmlspecialchars($k['nombre']) ?>">
                <?php endforeach; ?>
              </datalist>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Cantidad</label>
              <input type="number" name="cantidad" class="form-control" min="1" value="1" required>
            </div>

            <div class="col-md-6" id="divPedido">
              <label class="form-label small fw-semibold">Pedido Tienda (opcional)</label>
              <select name="pedido_id" class="form-select form-select-sm">
                <option value="">-- Sin pedido --</option>
                <?php foreach ($pedidos as $p): ?>
                  <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['numero_pedido']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6" id="divConvenio">
              <label class="form-label small fw-semibold">Convenio Comercial (opcional)</label>
              <select name="convenio_id" class="form-select form-select-sm">
                <option value="">-- Sin convenio --</option>
                <?php foreach ($convs as $cv): ?>
                  <option value="<?= $cv['id'] ?>"><?= htmlspecialchars($cv['codigo'].' - '.$cv['nombre_colegio']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6" id="divColegio">
              <label class="form-label small fw-semibold">Colegio (opcional)</label>
              <select name="colegio_id" class="form-select form-select-sm">
                <option value="">-- Sin colegio --</option>
                <?php foreach ($colegios as $col): ?>
                  <option value="<?= $col['id'] ?>"><?= htmlspecialchars($col['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label small fw-semibold">Asignar a</label>
              <select name="asignado_a" class="form-select form-select-sm">
                <option value="">-- Sin asignar --</option>
                <?php foreach ($usuarios as $u): ?>
                  <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['nombre']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-12">
              <label class="form-label small fw-semibold">Descripci&oacute;n / Detalles</label>
              <textarea name="descripcion" class="form-control" rows="3"
                        placeholder="Describe los requerimientos especificos, condiciones del convenio, instrucciones adicionales..."></textarea>
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold">Notas internas</label>
              <input type="text" name="notas" class="form-control form-control-sm"
                     placeholder="Notas para el equipo de produccion...">
            </div>

          </div>
        </div>
        <div class="modal-footer border-0">
          <button type="submit" class="btn btn-primary fw-bold flex-grow-1">
            <i class="bi bi-check-circle me-1"></i>Crear Solicitud
          </button>
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// ── Colapsar / expandir columna ───────────────────────────────
function toggleCol(col) {
    var el = document.getElementById('kcol-' + col);
    if (!el) return;
    var collapsed = el.classList.toggle('collapsed');
    try { localStorage.setItem('kanban_col_' + col, collapsed ? '1' : '0'); } catch(e) {}
}

// ── Expandir / colapsar tarjeta ───────────────────────────────
function toggleCard(id) {
    var detail = document.getElementById('detail-' + id);
    var arrow  = document.getElementById('arrow-' + id);
    if (!detail) return;
    var open = detail.classList.toggle('open');
    if (arrow) {
        var icon = arrow.querySelector('i');
        if (icon) {
            icon.className = open ? 'bi bi-chevron-up' : 'bi bi-chevron-down';
        }
    }
}

// ── Restaurar estado de columnas desde localStorage ──────────
document.addEventListener('DOMContentLoaded', function() {
    ['pendiente','en_proceso','listo'].forEach(function(col) {
        try {
            if (localStorage.getItem('kanban_col_' + col) === '1') {
                var el = document.getElementById('kcol-' + col);
                if (el) el.classList.add('collapsed');
            }
        } catch(e) {}
    });

    // Modal Nueva Solicitud: pre-seleccionar "interno"
    var btnInterno = document.querySelector('[data-fuente="interno"]');
    if (btnInterno) selFuente('interno', btnInterno);
});

// ── Selector de fuente en modal ───────────────────────────────
function selFuente(fuente, btn) {
    document.querySelectorAll('.btn-fuente').forEach(function(b) { b.style.borderWidth = '1.5px'; });
    btn.style.borderWidth = '3px';
    document.getElementById('fuenteHidden').value = fuente;
}
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
