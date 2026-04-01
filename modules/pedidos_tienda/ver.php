<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
Auth::check();

$db = Database::get();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . APP_URL . '/modules/pedidos_tienda/'); exit; }

$ESTADOS = [
    'pendiente'        => ['label'=>'Pendiente',          'bg'=>'#fef9c3', 'txt'=>'#854d0e'],
    'aprobado'         => ['label'=>'Aprobado',           'bg'=>'#dbeafe', 'txt'=>'#1d4ed8'],
    'en_produccion'    => ['label'=>'En producción',      'bg'=>'#ffedd5', 'txt'=>'#9a3412'],
    'listo_produccion' => ['label'=>'Listo producción',   'bg'=>'#d1fae5', 'txt'=>'#065f46'],
    'en_alistamiento'  => ['label'=>'En alistamiento',    'bg'=>'#ede9fe', 'txt'=>'#5b21b6'],
    'despachado'       => ['label'=>'Despachado',         'bg'=>'#bbf7d0', 'txt'=>'#14532d'],
    'entregado'        => ['label'=>'Entregado',          'bg'=>'#f1f5f9', 'txt'=>'#475569'],
    'cancelado'        => ['label'=>'Cancelado',          'bg'=>'#fee2e2', 'txt'=>'#991b1b'],
];

$pedido = $db->query("
    SELECT p.*, DATEDIFF(CURDATE(),p.fecha_compra) AS dias,
           CASE WHEN p.estado IN ('entregado','cancelado') THEN 'completado'
                WHEN DATEDIFF(CURDATE(),p.fecha_compra)<=5 THEN 'verde'
                WHEN DATEDIFF(CURDATE(),p.fecha_compra)<=7 THEN 'amarillo'
                ELSE 'rojo' END AS semaforo,
           col.nombre AS colegio_bd, u.nombre AS asignado_nombre
    FROM tienda_pedidos p
    LEFT JOIN colegios col ON col.id=p.colegio_id
    LEFT JOIN usuarios u   ON u.id=p.asignado_a
    WHERE p.id=$id
")->fetch();
if (!$pedido) { header('Location: ' . APP_URL . '/modules/pedidos_tienda/'); exit; }

$pageTitle  = 'Pedido #' . $pedido['woo_order_id'];
$activeMenu = 'pedidos_tienda';
$success = '';

if ($_SERVER['REQUEST_METHOD']==='POST' && Auth::csrfVerify($_POST['csrf']??'')) {
    $action = $_POST['action'] ?? '';
    if ($action === 'cambiar_estado') {
        $nuevo = $_POST['estado_nuevo'];
        $nota  = trim($_POST['nota'] ?? '');
        $guia  = trim($_POST['guia_envio'] ?? '');
        $trans = trim($_POST['transportadora'] ?? '');
        $actual = $pedido['estado'];

        $sets = "estado=:e, updated_at=NOW()";
        $params = ['e'=>$nuevo,'id'=>$id];
        if ($nuevo==='despachado') {
            $sets .= ", fecha_despacho=CURDATE()";
            if ($guia)  { $sets .= ", guia_envio=:g";     $params['g']=$guia; }
            if ($trans) { $sets .= ", transportadora=:t";  $params['t']=$trans; }
        }
        if ($nuevo==='entregado') { $sets .= ", fecha_entrega=CURDATE()"; }

        $db->prepare("UPDATE tienda_pedidos SET $sets WHERE id=:id")->execute($params);
        $db->prepare("INSERT INTO tienda_pedidos_historial (pedido_id,estado_ant,estado_nuevo,nota,usuario_id) VALUES (?,?,?,?,?)")
           ->execute([$id,$actual,$nuevo,$nota?:null,Auth::user()['id']]);

        header('Location: ver.php?id='.$id.'&ok=1'); exit;
    }
    if ($action === 'guardar_notas') {
        $db->prepare("UPDATE tienda_pedidos SET notas_internas=?, asignado_a=?, fecha_limite=?, updated_at=NOW() WHERE id=?")
           ->execute([trim($_POST['notas']??''), (int)$_POST['asignado_a']?:null, $_POST['fecha_limite']?:null, $id]);
        header('Location: ver.php?id='.$id.'&ok=1'); exit;
    }
}
if (!empty($_GET['ok'])) $success = 'Cambios guardados correctamente.';

$historial = $db->query("
    SELECT h.*, u.nombre AS usuario
    FROM tienda_pedidos_historial h
    LEFT JOIN usuarios u ON u.id=h.usuario_id
    WHERE h.pedido_id=$id ORDER BY h.created_at DESC
")->fetchAll();

$usuarios = $db->query("SELECT id,nombre FROM usuarios WHERE activo=1 ORDER BY nombre")->fetchAll();

// Multi-item: check if tienda_pedido_items table exists and load rows
$tblItemsOk = (bool) $db->query(
    "SELECT COUNT(*) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tienda_pedido_items'"
)->fetchColumn();
$pedidoItems = [];
if ($tblItemsOk) {
    $stmtItems = $db->prepare("SELECT * FROM tienda_pedido_items WHERE pedido_id=? ORDER BY id");
    $stmtItems->execute([$id]);
    $pedidoItems = $stmtItems->fetchAll();
}

$semCol=['rojo'=>'#ef4444','amarillo'=>'#f59e0b','verde'=>'#22c55e','completado'=>'#94a3b8'];
$sc  = $semCol[$pedido['semaforo']] ?? '#ccc';
$est = $ESTADOS[$pedido['estado']] ?? ['label'=>$pedido['estado'],'bg'=>'#f1f5f9','txt'=>'#475569'];

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
.section-card{background:#fff;border-radius:14px;border:1px solid #e2e8f0;padding:1rem 1.2rem;margin-bottom:1rem}
.sem-dot{width:14px;height:14px;border-radius:50%;display:inline-block}
.sem-verde{background:#22c55e}.sem-amarillo{background:#f59e0b}.sem-rojo{background:#ef4444}.sem-completado{background:#94a3b8}
.timeline{border-left:2px solid #e2e8f0;padding-left:1rem;margin-left:.5rem}
.t-dot{width:10px;height:10px;border-radius:50%;background:#3b82f6;flex-shrink:0;margin-left:-1.1rem;margin-top:3px}
</style>

<div class="d-flex align-items-center gap-2 mb-3">
  <a href="<?= APP_URL ?>/modules/pedidos_tienda/" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i></a>
  <div class="flex-grow-1">
    <h4 class="fw-bold mb-0">
      <span class="sem-dot sem-<?= $pedido['semaforo'] ?>" style="margin-right:.4rem"></span>
      Pedido #<?= htmlspecialchars($pedido['woo_order_id']) ?>
    </h4>
    <span class="text-muted small">
      <?= date('d/m/Y', strtotime($pedido['fecha_compra'])) ?>
      &nbsp;&middot;&nbsp;
      <span class="badge" style="background:<?= $est['bg'] ?>;color:<?= $est['txt'] ?>"><?= $est['label'] ?></span>
    </span>
  </div>
</div>

<?php if ($success): ?><div class="alert alert-success py-2 small"><?= $success ?></div><?php endif; ?>

<div class="row g-3">
  <div class="col-lg-4">

    <!-- 1. Cambiar Estado — acción principal, va primero -->
    <div class="section-card">
      <h6 class="fw-bold mb-3"><i class="bi bi-arrow-repeat me-2 text-warning"></i>Cambiar Estado</h6>
      <form method="POST">
        <input type="hidden" name="action" value="cambiar_estado">
        <input type="hidden" name="csrf"   value="<?= Auth::csrfToken() ?>">
        <select name="estado_nuevo" class="form-select form-select-sm mb-2">
          <?php foreach ($ESTADOS as $k => $e): ?>
            <option value="<?= $k ?>" <?= $pedido['estado']===$k?'selected':'' ?>><?= strip_tags($e['label']) ?></option>
          <?php endforeach; ?>
        </select>
        <div id="campos-despacho" style="display:<?= $pedido['estado']==='despachado'?'block':'none' ?>">
          <input type="text" name="guia_envio"     class="form-control form-control-sm mb-1" placeholder="N&uacute;mero de gu&iacute;a" value="<?= htmlspecialchars($pedido['guia_envio']??'') ?>">
          <input type="text" name="transportadora" class="form-control form-control-sm mb-2" placeholder="Transportadora" value="<?= htmlspecialchars($pedido['transportadora']??'') ?>">
        </div>
        <textarea name="nota" class="form-control form-control-sm mb-2" rows="2" placeholder="Nota opcional..."></textarea>
        <button type="submit" class="btn btn-primary btn-sm w-100">Guardar cambio de estado</button>
      </form>
    </div>

    <!-- 2. Cliente + Colegio — fusionados para reducir scroll -->
    <div class="section-card">
      <h6 class="fw-bold mb-2"><i class="bi bi-person-circle me-2 text-primary"></i>Cliente</h6>
      <div class="fw-bold"><?= htmlspecialchars($pedido['cliente_nombre']) ?></div>
      <?php if ($pedido['cliente_telefono']): ?><div class="small text-muted"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($pedido['cliente_telefono']) ?></div><?php endif; ?>
      <?php if ($pedido['cliente_email']): ?><div class="small text-muted"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($pedido['cliente_email']) ?></div><?php endif; ?>
      <?php if ($pedido['direccion']): ?><div class="small text-muted mt-1"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($pedido['direccion'].($pedido['ciudad']?', '.$pedido['ciudad']:'')) ?></div><?php endif; ?>
      <?php if ($pedido['colegio_bd'] || $pedido['colegio_nombre']): ?>
      <div class="mt-2 pt-2" style="border-top:1px solid #f1f5f9">
        <div class="small text-muted mb-1"><i class="bi bi-building me-1"></i>Colegio</div>
        <div class="fw-semibold" style="color:#1d4ed8;font-size:.88rem">
          <?= htmlspecialchars($pedido['colegio_bd'] ?? $pedido['colegio_nombre']) ?>
        </div>
        <?php if ($pedido['colegio_id']): ?>
          <a href="<?= APP_URL ?>/modules/colegios/ver.php?id=<?= $pedido['colegio_id'] ?>"
             class="btn btn-outline-primary btn-sm mt-1" style="font-size:.75rem">
            <i class="bi bi-eye me-1"></i>Ver colegio
          </a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- 3. Producto(s) -->
    <div class="section-card">
      <h6 class="fw-bold mb-2"><i class="bi bi-box-seam me-2 text-warning"></i>Producto<?= count($pedidoItems) > 1 ? 's' : '' ?></h6>
      <?php if (!empty($pedidoItems)): ?>
        <table class="table table-sm table-borderless mb-0" style="font-size:.82rem">
          <thead>
            <tr class="text-muted" style="border-bottom:1px solid #e2e8f0">
              <th class="ps-0">Kit</th>
              <th>Colegio</th>
              <th class="text-center">Cant.</th>
              <th class="text-end pe-0">Subtotal</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($pedidoItems as $item): ?>
            <tr>
              <td class="ps-0 fw-semibold"><?= htmlspecialchars($item['kit_nombre']) ?></td>
              <td class="text-muted"><?= htmlspecialchars($item['colegio_nombre'] ?? '—') ?></td>
              <td class="text-center"><?= (int)$item['cantidad'] ?></td>
              <td class="text-end pe-0">$<?= number_format((float)$item['subtotal'], 0, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
          <?php
            $grandTotal = array_sum(array_column($pedidoItems, 'subtotal'));
          ?>
          <tfoot>
            <tr style="border-top:1px solid #e2e8f0">
              <td colspan="3" class="ps-0 fw-bold text-end pe-2">Total</td>
              <td class="text-end pe-0 fw-bold text-primary">$<?= number_format($grandTotal, 0, ',', '.') ?></td>
            </tr>
          </tfoot>
        </table>
      <?php else: ?>
        <div class="fw-semibold"><?= htmlspecialchars($pedido['kit_nombre'] ?? '&mdash;') ?></div>
        <?php $cant = (int)($pedido['cantidad'] ?? 1); ?>
        <div class="text-muted small">
          <?= htmlspecialchars($pedido['categoria'] ?? '') ?>
          <?php if ($cant > 0): ?>
            <span class="badge bg-secondary ms-1"><?= $cant ?> <?= $cant === 1 ? 'unidad' : 'unidades' ?></span>
          <?php endif; ?>
          <?php if (($pedido['total'] ?? 0) > 0): ?>
            <span class="ms-2 fw-semibold text-primary">$<?= number_format((float)$pedido['total'], 0, ',', '.') ?></span>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- 4. Asignación y Notas -->
    <div class="section-card">
      <h6 class="fw-bold mb-3"><i class="bi bi-person-check me-2 text-success"></i>Asignaci&oacute;n y Notas</h6>
      <form method="POST">
        <input type="hidden" name="action" value="guardar_notas">
        <input type="hidden" name="csrf"   value="<?= Auth::csrfToken() ?>">
        <label class="form-label small mb-1">Responsable</label>
        <select name="asignado_a" class="form-select form-select-sm mb-2">
          <option value="">Sin asignar</option>
          <?php foreach ($usuarios as $u): ?>
            <option value="<?= $u['id'] ?>" <?= $pedido['asignado_a']==$u['id']?'selected':'' ?>><?= htmlspecialchars($u['nombre']) ?></option>
          <?php endforeach; ?>
        </select>
        <label class="form-label small mb-1">Fecha l&iacute;mite</label>
        <input type="date" name="fecha_limite" class="form-control form-control-sm mb-2" value="<?= $pedido['fecha_limite'] ?? '' ?>">
        <label class="form-label small mb-1">Notas internas</label>
        <textarea name="notas" class="form-control form-control-sm mb-2" rows="3"><?= htmlspecialchars($pedido['notas_internas']??'') ?></textarea>
        <button type="submit" class="btn btn-outline-success btn-sm w-100">Guardar notas</button>
      </form>
    </div>

  </div>

  <div class="col-lg-8">
    <div class="section-card">
      <h6 class="fw-bold mb-3"><i class="bi bi-clock-history me-2 text-secondary"></i>Historial de Estados</h6>
      <?php if (empty($historial)): ?>
        <div class="text-muted small">Sin historial registrado.</div>
      <?php else: ?>
      <div class="timeline">
        <?php foreach ($historial as $h):
          $ec = $ESTADOS[$h['estado_nuevo']] ?? ['label'=>$h['estado_nuevo'],'bg'=>'#e2e8f0','txt'=>'#475569'];
          $ea = isset($h['estado_ant']) ? ($ESTADOS[$h['estado_ant']] ?? ['label'=>$h['estado_ant'],'bg'=>'#e2e8f0','txt'=>'#475569']) : null;
        ?>
        <div class="d-flex gap-2 mb-3">
          <div class="t-dot"></div>
          <div>
            <div class="small fw-semibold">
              <?php if ($ea && $h['estado_ant']): ?>
                <span class="badge" style="background:<?= $ea['bg'] ?>;color:<?= $ea['txt'] ?>;font-size:.68rem"><?= strip_tags($ea['label']) ?></span>
                <i class="bi bi-arrow-right mx-1 text-muted"></i>
              <?php endif; ?>
              <span class="badge" style="background:<?= $ec['bg'] ?>;color:<?= $ec['txt'] ?>;font-size:.68rem"><?= strip_tags($ec['label']) ?></span>
            </div>
            <?php if ($h['nota']): ?><div class="text-muted small mt-1"><?= htmlspecialchars($h['nota']) ?></div><?php endif; ?>
            <div class="text-muted" style="font-size:.7rem">
              <?= date('d/m/Y H:i', strtotime($h['created_at'])) ?>
              <?php if ($h['usuario']): ?>&nbsp;&middot;&nbsp;<?= htmlspecialchars($h['usuario']) ?><?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
document.querySelector('select[name="estado_nuevo"]').addEventListener('change', function() {
  document.getElementById('campos-despacho').style.display = this.value === 'despachado' ? 'block' : 'none';
});
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
