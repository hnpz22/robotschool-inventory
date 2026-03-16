<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Auth.php';
require_once __DIR__ . '/includes/helpers.php';
Auth::check();

$db = Database::get();
$pageTitle  = 'Dashboard';
$activeMenu = 'dashboard';

// ── Stats ──
$totalElementos = $db->query("SELECT COUNT(*) FROM elementos WHERE activo=1")->fetchColumn();
$sinStock       = $db->query("SELECT COUNT(*) FROM elementos WHERE activo=1 AND stock_actual<=0")->fetchColumn();
$stockBajo      = $db->query("SELECT COUNT(*) FROM elementos WHERE activo=1 AND stock_actual>0 AND stock_actual<=stock_minimo")->fetchColumn();
$stockOk        = $totalElementos - $sinStock - $stockBajo;
$pedidosPend    = $db->query("SELECT COUNT(*) FROM pedidos_importacion WHERE estado IN ('en_transito','en_aduana')")->fetchColumn();
$kitsTotal      = $db->query("SELECT COUNT(*) FROM kits WHERE activo=1")->fetchColumn();
$movHoy         = $db->query("SELECT COUNT(*) FROM movimientos WHERE DATE(created_at)=CURDATE()")->fetchColumn();

// ── Semáforo por categoría ──
$semaforos = $db->query("
  SELECT c.nombre, c.color,
    SUM(CASE WHEN e.stock_actual<=0 THEN 1 ELSE 0 END) AS rojos,
    SUM(CASE WHEN e.stock_actual>0 AND e.stock_actual<=e.stock_minimo THEN 1 ELSE 0 END) AS amarillos,
    SUM(CASE WHEN e.stock_actual>e.stock_minimo THEN 1 ELSE 0 END) AS verdes,
    COUNT(*) AS total
  FROM elementos e JOIN categorias c ON c.id=e.categoria_id
  WHERE e.activo=1 GROUP BY c.id ORDER BY rojos DESC LIMIT 10
")->fetchAll();

// ── Últimos movimientos ──
$movimientos = $db->query("SELECT * FROM v_movimientos ORDER BY created_at DESC LIMIT 15")->fetchAll();

// ── Alertas críticas ──
$alertas = $db->query("
  SELECT e.codigo, e.nombre, e.stock_actual, e.stock_minimo, c.nombre AS cat, c.color
  FROM elementos e JOIN categorias c ON c.id=e.categoria_id
  WHERE e.activo=1 AND e.stock_actual<=e.stock_minimo
  ORDER BY e.stock_actual ASC LIMIT 10
")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<!-- Page title -->
<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h4 class="fw-bold mb-0">Dashboard</h4>
    <p class="text-muted small mb-0">Resumen general del inventario ROBOTSchool</p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= APP_URL ?>/modules/elementos/form.php" class="btn btn-primary btn-sm">
      <i class="bi bi-plus-lg me-1"></i>Nuevo Elemento
    </a>
    <a href="<?= APP_URL ?>/modules/importaciones/form.php" class="btn btn-warning btn-sm">
      <i class="bi bi-airplane me-1"></i>Nuevo Pedido
    </a>
  </div>
</div>

<!-- ── STAT CARDS ── -->
<div class="row g-3 mb-4">
  <div class="col-xl-2 col-md-4 col-6">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="icon-box bg-primary bg-opacity-10 text-primary">&#x1F4E6;</div>
        <div>
          <div class="dashboard-stat-num text-primary"><?= $totalElementos ?></div>
          <div class="dashboard-stat-lbl">Elementos</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-xl-2 col-md-4 col-6">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="icon-box bg-success bg-opacity-10 text-success">&#x1F7E2;</div>
        <div>
          <div class="dashboard-stat-num text-success"><?= $stockOk ?></div>
          <div class="dashboard-stat-lbl">Stock OK</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-xl-2 col-md-4 col-6">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="icon-box bg-warning bg-opacity-10 text-warning">&#x1F7E1;</div>
        <div>
          <div class="dashboard-stat-num text-warning"><?= $stockBajo ?></div>
          <div class="dashboard-stat-lbl">Stock Bajo</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-xl-2 col-md-4 col-6">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="icon-box bg-danger bg-opacity-10 text-danger">&#x1F534;</div>
        <div>
          <div class="dashboard-stat-num text-danger"><?= $sinStock ?></div>
          <div class="dashboard-stat-lbl">Sin Stock</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-xl-2 col-md-4 col-6">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="icon-box bg-info bg-opacity-10 text-info">&#x2708;&#xFE0F;</div>
        <div>
          <div class="dashboard-stat-num text-info"><?= $pedidosPend ?></div>
          <div class="dashboard-stat-lbl">En tránsito</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-xl-2 col-md-4 col-6">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="icon-box bg-secondary bg-opacity-10 text-secondary">&#x1F916;</div>
        <div>
          <div class="dashboard-stat-num"><?= $movHoy ?></div>
          <div class="dashboard-stat-lbl">Movimientos hoy</div>
        </div>
      </div>
    </div>
  </div>
</div>

<div class="row g-4">
  <!-- ── Semáforo por categoría ── -->
  <div class="col-xl-8">
    <div class="section-card">
      <h6 class="fw-bold mb-3"><i class="bi bi-traffic-light me-2 text-primary"></i>Semáforo de Stock por Categoría</h6>
      <?php foreach ($semaforos as $s): ?>
      <div class="mb-3">
        <div class="d-flex justify-content-between align-items-center mb-1">
          <span class="small fw-semibold"><?= htmlspecialchars($s['nombre']) ?></span>
          <span class="small text-muted"><?= $s['total'] ?> elementos</span>
        </div>
        <div class="d-flex gap-1" style="height:12px;">
          <?php
            $t = max(1,$s['total']);
            $pR = round($s['rojos']/$t*100);
            $pA = round($s['amarillos']/$t*100);
            $pV = 100 - $pR - $pA;
          ?>
          <?php if($pR>0): ?><div class="bg-danger rounded-start" style="width:<?=$pR?>%;height:12px;" title="Sin stock: <?=$s['rojos']?>"></div><?php endif; ?>
          <?php if($pA>0): ?><div class="bg-warning" style="width:<?=$pA?>%;height:12px;" title="Bajo: <?=$s['amarillos']?>"></div><?php endif; ?>
          <?php if($pV>0): ?><div class="bg-success rounded-end" style="width:<?=$pV?>%;height:12px;" title="OK: <?=$s['verdes']?>"></div><?php endif; ?>
        </div>
        <div class="d-flex gap-3 mt-1">
          <span class="text-danger" style="font-size:.75rem;">&#x1F534; <?=$s['rojos']?></span>
          <span class="text-warning" style="font-size:.75rem;">&#x1F7E1; <?=$s['amarillos']?></span>
          <span class="text-success" style="font-size:.75rem;">&#x1F7E2; <?=$s['verdes']?></span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ── Alertas críticas ── -->
  <div class="col-xl-4">
    <div class="section-card">
      <h6 class="fw-bold mb-3"><i class="bi bi-exclamation-triangle text-warning me-2"></i>Alertas de Stock</h6>
      <?php if (empty($alertas)): ?>
        <div class="text-success text-center py-3"><i class="bi bi-check-circle fs-2"></i><br>¡Todo en orden!</div>
      <?php else: ?>
        <div class="list-group list-group-flush">
        <?php foreach ($alertas as $a): $s = semaforo($a['stock_actual'], $a['stock_minimo'], 999); ?>
          <a href="<?= APP_URL ?>/modules/elementos/form.php?id=<?= $a['id'] ?? '' ?>" class="list-group-item list-group-item-action py-2 px-0 border-0">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <div class="fw-semibold small"><?= htmlspecialchars($a['nombre']) ?></div>
                <div class="text-muted" style="font-size:.75rem;"><?= $a['codigo'] ?> · <?= $a['cat'] ?></div>
              </div>
              <span class="badge bg-<?= $s['color'] ?> badge-semaforo"><?= $s['icon'] ?> <?= $a['stock_actual'] ?></span>
            </div>
          </a>
        <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── Últimos movimientos ── -->
  <div class="col-12">
    <div class="section-card">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold mb-0"><i class="bi bi-clock-history me-2 text-primary"></i>Últimos Movimientos</h6>
        <a href="<?= APP_URL ?>/modules/inventario/movimientos.php" class="btn btn-sm btn-outline-primary">Ver todos</a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover table-inv mb-0">
          <thead><tr>
            <th>Fecha</th><th>Elemento</th><th>Categoría</th>
            <th>Tipo</th><th>Cantidad</th><th>Stock</th><th>Referencia</th><th>Usuario</th>
          </tr></thead>
          <tbody>
          <?php foreach ($movimientos as $m): ?>
          <tr>
            <td class="text-muted small"><?= date('d/m/y H:i', strtotime($m['created_at'])) ?></td>
            <td><span class="fw-semibold"><?= htmlspecialchars($m['elem_nombre']) ?></span><br><span class="text-muted" style="font-size:.75rem;"><?= $m['elem_codigo'] ?></span></td>
            <td><span class="small"><?= htmlspecialchars($m['categoria']) ?></span></td>
            <td>
              <?php $tc=['entrada'=>'success','salida'=>'danger','ajuste'=>'warning','devolucion'=>'info','transferencia'=>'secondary']; ?>
              <span class="badge bg-<?= $tc[$m['tipo']] ?? 'secondary' ?>"><?= ucfirst($m['tipo']) ?></span>
            </td>
            <td class="fw-bold <?= $m['cantidad']>0?'text-success':'text-danger' ?>"><?= ($m['cantidad']>0?'+':'').$m['cantidad'] ?></td>
            <td><?= $m['stock_despues'] ?></td>
            <td class="small text-muted"><?= htmlspecialchars($m['referencia'] ?? '&mdash;') ?></td>
            <td class="small"><?= htmlspecialchars($m['usuario'] ?? 'Sistema') ?></td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($movimientos)): ?>
            <tr><td colspan="8" class="text-center text-muted py-4">Sin movimientos registrados</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
