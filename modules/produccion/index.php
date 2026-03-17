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

        // Si es listo → actualizar pedido de tienda si existe
        if ($estado === 'listo') {
            $pedidoId = $db->query("SELECT pedido_id FROM solicitudes_produccion WHERE id=$sid")->fetchColumn();
            if ($pedidoId) {
                $db->prepare("UPDATE tienda_pedidos SET estado='listo_produccion' WHERE id=?")
                   ->execute([$pedidoId]);
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

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
.tablero-col{background:#fff;border-radius:14px;border:1.5px solid #e2e8f0;min-height:200px}
.sol-card{background:#fff;border-radius:10px;border:1px solid #e2e8f0;margin-bottom:.6rem;
          transition:.15s;overflow:hidden}
.sol-card:hover{box-shadow:0 3px 12px rgba(0,0,0,.08)}
.sol-card-header{padding:.5rem .75rem;display:flex;align-items:center;justify-content:space-between}
.sol-card-body{padding:.5rem .75rem .6rem}
.fuente-badge{font-size:.65rem;padding:.15rem .45rem;border-radius:20px;font-weight:700;display:inline-flex;align-items:center;gap:.25rem}
.prior-dot{width:10px;height:10px;border-radius:50%;display:inline-block;flex-shrink:0}
.stat-col{background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:.75rem 1rem;text-align:center;cursor:pointer;transition:.15s}
.stat-col:hover{transform:translateY(-2px);box-shadow:0 4px 12px rgba(0,0,0,.06)}
.stat-col.activo{border-width:2px}
.prog-bar{height:4px;border-radius:2px;background:#e2e8f0;overflow:hidden;margin-top:4px}
.prog-fill{height:100%;background:#22c55e;border-radius:2px;transition:.3s}
.kanban-header{padding:.5rem .75rem;border-radius:10px 10px 0 0;font-size:.78rem;font-weight:700}
/* Modal Nueva Solicitud - scroll forzado */
#modalNueva .modal-body{
  overflow-y:auto !important;
  max-height:calc(100vh - 200px) !important;
}
#modalNueva .modal-dialog{
  margin:.5rem auto;
}
</style>

<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-kanban me-2"></i>Tablero de Producci&oacute;n</h4>
    <p class="text-muted small mb-0">Solicitudes de todas las fuentes en tiempo real</p>
  </div>
  <div class="d-flex gap-2">
    <a href="cronograma.php" class="btn btn-outline-primary fw-bold">
      <i class="bi bi-calendar3 me-1"></i>Cronograma
    </a>
    <button class="btn btn-primary fw-bold" data-bs-toggle="modal" data-bs-target="#modalNueva">
      <i class="bi bi-plus-lg me-1"></i>Nueva Solicitud
    </button>
  </div>
</div>

<?php if ($error):   ?><div class="alert alert-danger  py-2 small"><?= htmlspecialchars($error)   ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<!-- Stats por estado -->
<div class="row g-2 mb-3">
  <?php foreach ($ESTADOS as $ek => $ev): ?>
  <div class="col-6 col-md-3">
    <div class="stat-col <?= $fEstado===$ek?'activo':''; ?>"
         style="border-color:<?= $fEstado===$ek?$ev['color']:'#e2e8f0' ?>"
         onclick="window.location='?estado=<?= $ek ?><?= $fFuente?"&fuente=$fFuente":'' ?>'">
      <i class="bi <?= $ev['icon'] ?>" style="font-size:1.3rem;color:<?= $ev['color'] ?>"></i>
      <div class="fs-3 fw-bold mt-1" style="color:<?= $ev['color'] ?>"><?= $stats[$ek] ?></div>
      <div class="text-muted" style="font-size:.72rem"><?= $ev['label'] ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Filtros por fuente -->
<div class="d-flex gap-2 flex-wrap mb-3 align-items-center">
  <span class="text-muted small fw-semibold">Fuente:</span>
  <a href="?<?= $fEstado?"estado=$fEstado&":'' ?>" 
     class="btn btn-sm <?= !$fFuente?'btn-dark':'btn-outline-secondary' ?>">
    Todas
  </a>
  <?php foreach ($FUENTES as $fk => $fv): ?>
  <a href="?fuente=<?= $fk ?><?= $fEstado?"&estado=$fEstado":'' ?>"
     class="btn btn-sm <?= $fFuente===$fk?'btn-primary':'btn-outline-secondary' ?>"
     style="<?= $fFuente===$fk?'background:'.$fv['color'].';border-color:'.$fv['color'].';color:#fff':'' ?>">
    <i class="bi <?= $fv['icon'] ?> me-1"></i><?= $fv['label'] ?>
    <?php if (isset($statsF[$fk])): ?>
      <span class="badge bg-danger ms-1" style="font-size:.6rem"><?= $statsF[$fk] ?></span>
    <?php endif; ?>
  </a>
  <?php endforeach; ?>
  <?php if ($fEstado || $fFuente): ?>
    <a href="?" class="btn btn-sm btn-outline-secondary ms-auto">&#x2715; Limpiar filtros</a>
  <?php endif; ?>
</div>

<!-- ── TABLERO KANBAN ── -->
<div class="row g-3">
  <?php foreach ($ESTADOS as $ek => $ev):
    $cols = array_filter($solicitudes, fn($s) => $s['estado'] === $ek);
    if ($fEstado && $fEstado !== $ek) continue;
  ?>
  <div class="col-md-6 col-xl-3">
    <div class="tablero-col">
      <div class="kanban-header" style="background:<?= $ev['bg'] ?>;color:<?= $ev['color'] ?>">
        <i class="bi <?= $ev['icon'] ?> me-1"></i><?= $ev['label'] ?>
        <span class="float-end badge" style="background:<?= $ev['color'] ?>;color:#fff;font-size:.68rem">
          <?= count($cols) ?>
        </span>
      </div>
      <div class="p-2" style="min-height:120px">
        <?php if (empty($cols)): ?>
          <div class="text-center text-muted py-4" style="font-size:.78rem">Sin solicitudes</div>
        <?php endif; ?>
        <?php foreach ($cols as $s):
          $fu   = $FUENTES[$s['fuente']] ?? $FUENTES['interno'];
          $pr   = $PRIORIDADES[$s['prioridad']] ?? $PRIORIDADES[3];
          $pct  = $s['num_items'] > 0 ? round($s['items_listos']/$s['num_items']*100) : 0;
          $diasRest = '';
          if ($s['fecha_limite']) {
              $diff = (new DateTime($s['fecha_limite']))->diff(new DateTime('today'));
              $diasRest = $diff->invert ? '<span class="text-danger">Vencido hace '.$diff->days.'d</span>' : ($diff->days==0 ? '<span class="text-danger">Hoy!</span>' : '<span class="text-muted">'.$diff->days.'d restantes</span>');
          }
        ?>
        <div class="sol-card" style="border-left:3px solid <?= $fu['color'] ?>">
          <div class="sol-card-header">
            <div class="d-flex align-items-center gap-1 flex-wrap">
              <span class="fuente-badge" style="background:<?= $fu['bg'] ?>;color:<?= $fu['color'] ?>">
                <i class="bi <?= $fu['icon'] ?>" style="font-size:.65rem"></i><?= $fu['label'] ?>
              </span>
              <span class="prior-dot" style="background:<?= $pr['color'] ?>" title="<?= $pr['label'] ?>"></span>
            </div>
            <div class="d-flex gap-1">
              <?php if ($ek !== 'rechazado'): ?>
              <button class="btn btn-link btn-sm p-0 text-primary" style="font-size:.7rem"
                      onclick="abrirCambioEstado(<?= $s['id'] ?>, '<?= $ek ?>')"
                      title="Cambiar estado">
                <i class="bi bi-arrow-right-circle"></i>
              </button>
              <?php endif; ?>
              <?php if (Auth::isAdmin()): ?>
              <a href="?del=<?= $s['id'] ?>&csrf=<?= Auth::csrfToken() ?>"
                 class="btn btn-link btn-sm p-0 text-danger" style="font-size:.7rem"
                 onclick="return confirm('Eliminar esta solicitud?')" title="Eliminar">
                <i class="bi bi-trash"></i>
              </a>
              <?php endif; ?>
            </div>
          </div>
          <div class="sol-card-body">
            <div class="fw-semibold" style="font-size:.82rem;line-height:1.3">
              <?= htmlspecialchars($s['titulo'] ?: ($s['kit_nombre'] ?: 'Sin titulo')) ?>
            </div>
            <?php if ($s['kit_nombre'] && $s['titulo']): ?>
              <div class="text-muted" style="font-size:.72rem"><?= htmlspecialchars($s['kit_nombre']) ?></div>
            <?php endif; ?>
            <?php
              // Determinar nombre del colegio según fuente
              $nombreColegio = $s['colegio_nombre']
                  ?: $s['conv_colegio']
                  ?: ($s['tienda_colegio'] ?? null);
            ?>
            <?php if ($nombreColegio): ?>
            <div class="fw-semibold mt-1" style="font-size:.75rem;color:#0891b2">
              <i class="bi bi-building me-1"></i><?= htmlspecialchars(mb_strimwidth($nombreColegio,0,30,'...')) ?>
            </div>
            <?php endif; ?>
            <div class="d-flex gap-2 mt-1 flex-wrap" style="font-size:.7rem;color:#64748b">
              <span><i class="bi bi-boxes me-1"></i><?= $s['cantidad'] ?> uds</span>
              <?php if ($s['tienda_pedido']): ?>
                <span><i class="bi bi-shop me-1"></i><?= htmlspecialchars($s['tienda_pedido']) ?></span>
              <?php endif; ?>
              <?php if ($s['fuente']==='comercial' && $s['conv_colegio'] && $s['conv_colegio']!==$nombreColegio): ?>
                <span><i class="bi bi-briefcase me-1"></i><?= htmlspecialchars(mb_strimwidth($s['conv_colegio'],0,20,'...')) ?></span>
              <?php endif; ?>
            </div>
            <?php if ($diasRest): ?>
              <div style="font-size:.7rem;margin-top:3px"><?= $diasRest ?></div>
            <?php endif; ?>
            <?php if ($s['num_items'] > 0): ?>
            <div class="d-flex justify-content-between mt-1" style="font-size:.68rem;color:#64748b">
              <span><?= $s['items_listos'] ?>/<?= $s['num_items'] ?> items</span>
              <span><?= $pct ?>%</span>
            </div>
            <div class="prog-bar"><div class="prog-fill" style="width:<?= $pct ?>%"></div></div>
            <?php endif; ?>
            <?php if ($s['solicitado_nombre']): ?>
              <div style="font-size:.68rem;color:#94a3b8;margin-top:4px">
                <i class="bi bi-person me-1"></i><?= htmlspecialchars($s['solicitado_nombre']) ?>
                &middot; <?= date('d/m',strtotime($s['created_at'])) ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ── MODAL CAMBIAR ESTADO ── -->
<div class="modal fade" id="modalEstado" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:14px">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-bold"><i class="bi bi-arrow-right-circle me-2"></i>Actualizar Estado</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <div class="modal-body pt-0">
          <input type="hidden" name="action"       value="cambiar_estado">
          <input type="hidden" name="csrf"         value="<?= Auth::csrfToken() ?>">
          <input type="hidden" name="solicitud_id" id="estadoSolId">
          <div class="mb-3">
            <label class="form-label small fw-semibold">Nuevo estado</label>
            <div class="d-flex gap-2 flex-wrap">
              <?php foreach ($ESTADOS as $ek => $ev): ?>
              <button type="button" class="btn btn-sm btn-estado flex-fill"
                      style="background:<?= $ev['bg'] ?>;color:<?= $ev['color'] ?>;border:1.5px solid <?= $ev['color'] ?>"
                      data-estado="<?= $ek ?>"
                      onclick="selEstado('<?= $ek ?>', this)">
                <i class="bi <?= $ev['icon'] ?> me-1"></i><?= $ev['label'] ?>
              </button>
              <?php endforeach; ?>
              <input type="hidden" name="estado" id="estadoHidden" required>
            </div>
          </div>
          <div>
            <label class="form-label small fw-semibold">Notas / Comentario</label>
            <textarea name="notas_respuesta" class="form-control form-control-sm" rows="3"
                      placeholder="Observaciones del equipo de produccion..."></textarea>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="submit" class="btn btn-primary fw-bold" id="btnActualizarEstado" disabled>
            <i class="bi bi-check me-1"></i>Actualizar
          </button>
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── MODAL NUEVA SOLICITUD ── -->
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

            <!-- Fuente -->
            <div class="col-12">
              <label class="form-label small fw-semibold">Origen / Fuente *</label>
              <div class="d-flex gap-2 flex-wrap">
                <?php foreach ($FUENTES as $fk => $fv): ?>
                <button type="button" class="btn btn-sm btn-fuente"
                        style="background:<?= $fv['bg'] ?>;color:<?= $fv['color'] ?>;border:1.5px solid <?= $fv['color'] ?>40"
                        data-fuente="<?= $fk ?>"
                        onclick="selFuente('<?= $fk ?>', this)">
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
              <input type="date" name="fecha_limite" class="form-control"
                     min="<?= date('Y-m-d') ?>">
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

            <!-- Vínculos según fuente -->
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
function abrirCambioEstado(id, estadoActual) {
    document.getElementById('estadoSolId').value = id;
    document.getElementById('estadoHidden').value = '';
    document.querySelectorAll('.btn-estado').forEach(b => b.style.fontWeight = '400');
    document.getElementById('btnActualizarEstado').disabled = true;
    var m = new bootstrap.Modal(document.getElementById('modalEstado'));
    m.show();
}
function selEstado(estado, btn) {
    document.querySelectorAll('.btn-estado').forEach(b => b.style.fontWeight = '400');
    btn.style.fontWeight = '700';
    document.getElementById('estadoHidden').value = estado;
    document.getElementById('btnActualizarEstado').disabled = false;
}
function selFuente(fuente, btn) {
    document.querySelectorAll('.btn-fuente').forEach(b => b.style.borderWidth = '1.5px');
    btn.style.borderWidth = '3px';
    document.getElementById('fuenteHidden').value = fuente;
}
// Pre-seleccionar interno
document.addEventListener('DOMContentLoaded', function() {
    var btnInterno = document.querySelector('[data-fuente="interno"]');
    if (btnInterno) selFuente('interno', btnInterno);
});
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
