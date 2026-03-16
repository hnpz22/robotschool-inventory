<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();

$db = Database::get();
$pageTitle  = 'Despachos';
$activeMenu = 'despachos';
$csrf       = Auth::csrfToken();

// ELIMINAR
if (isset($_GET['del']) && Auth::csrfVerify($_GET['csrf'] ?? '')) {
    $delId = (int)$_GET['del'];
    $row = $db->prepare("SELECT estado,codigo FROM despachos WHERE id=?");
    $row->execute([$delId]); $row = $row->fetch();
    if ($row && $row['estado'] === 'preparando') {
        $db->prepare("DELETE FROM despacho_kits WHERE despacho_id=?")->execute([$delId]);
        $db->prepare("DELETE FROM despachos WHERE id=?")->execute([$delId]);
    }
    header('Location: ' . APP_URL . '/modules/despachos/'); exit;
}

// CAMBIAR ESTADO
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='cambiar_estado'
    && Auth::csrfVerify($_POST['csrf']??'')) {
    $desId   = (int)$_POST['despacho_id'];
    $estados = ['preparando','despachado','entregado','anulado'];
    $newEst  = in_array($_POST['estado']??'', $estados) ? $_POST['estado'] : null;
    if ($newEst) {
        $extra = '';
        if ($newEst === 'entregado') $extra = ", fecha_entrega=CURDATE()";
        $db->prepare("UPDATE despachos SET estado=?$extra WHERE id=?")->execute([$newEst, $desId]);
    }
    header('Location: ' . APP_URL . '/modules/despachos/'); exit;
}

// FILTROS
$filtroCol  = (int)($_GET['colegio'] ?? 0);
$filtroEst  = trim($_GET['estado'] ?? '');
$desde      = trim($_GET['desde'] ?? '');
$hasta      = trim($_GET['hasta'] ?? '');
$pagina     = max(1,(int)($_GET['p'] ?? 1));

$where = ['1=1']; $params = [];
if ($filtroCol) { $where[] = 'd.colegio_id=?'; $params[] = $filtroCol; }
if ($filtroEst) { $where[] = 'd.estado=?'; $params[] = $filtroEst; }
if ($desde)     { $where[] = 'd.fecha>=?'; $params[] = $desde; }
if ($hasta)     { $where[] = 'd.fecha<=?'; $params[] = $hasta; }
$whereStr = implode(' AND ', $where);

$total = $db->prepare("SELECT COUNT(*) FROM despachos d WHERE $whereStr");
$total->execute($params); $pag = paginar((int)$total->fetchColumn(), $pagina, 20);

$despachos = $db->prepare("
    SELECT d.*,
           col.nombre AS colegio_nombre, col.ciudad AS municipio,
           COUNT(dk.id) AS num_kits,
           SUM(dk.cantidad) AS total_kits
    FROM despachos d
    LEFT JOIN colegios col ON col.id = d.colegio_id
    LEFT JOIN despacho_kits dk ON dk.despacho_id = d.id
    WHERE $whereStr
    GROUP BY d.id
    ORDER BY d.fecha DESC, d.id DESC
    LIMIT {$pag['por_pagina']} OFFSET {$pag['offset']}
");
$despachos->execute($params); $despachos = $despachos->fetchAll();

$colegios = $db->query("SELECT id,nombre FROM colegios WHERE activo=1 ORDER BY nombre")->fetchAll();

require_once dirname(__DIR__, 2) . '/includes/header.php';

$estColor = ['preparando'=>'warning','despachado'=>'info','entregado'=>'success','anulado'=>'danger'];
$estIcon  = ['preparando'=>'&#128230;','despachado'=>'&#9992;','entregado'=>'&#9989;','anulado'=>'&#10060;'];
$estLabel = ['preparando'=>'Preparando','despachado'=>'Despachado','entregado'=>'Entregado','anulado'=>'Anulado'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-0">&#128230; Despachos</h4>
    <p class="text-muted small mb-0">Trazabilidad de kits enviados a colegios</p>
  </div>
  <a href="<?= APP_URL ?>/modules/despachos/form.php" class="btn btn-primary">
    <i class="bi bi-plus-lg me-2"></i>Nuevo Despacho
  </a>
</div>

<!-- Tarjetas resumen -->
<div class="row g-3 mb-4">
<?php
$res = ['preparando'=>0,'despachado'=>0,'entregado'=>0,'anulado'=>0];
$tots = $db->query("SELECT estado, COUNT(*) c FROM despachos GROUP BY estado")->fetchAll();
foreach ($tots as $t) $res[$t['estado']] = (int)$t['c'];
foreach ($estLabel as $est => $lbl): ?>
<div class="col">
  <div class="card border-0 shadow-sm" style="border-left:4px solid var(--bs-<?= $estColor[$est] ?>) !important;">
    <div class="card-body py-2 px-3 d-flex align-items-center gap-3">
      <span style="font-size:1.5rem;"><?= $estIcon[$est] ?></span>
      <div><div class="fw-bold fs-4 lh-1"><?= $res[$est] ?></div>
      <div class="text-muted small"><?= $lbl ?></div></div>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>

<!-- Filtros -->
<div class="section-card mb-3">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label mb-1 small fw-semibold">Colegio</label>
      <select name="colegio" class="form-select form-select-sm">
        <option value="">Todos los colegios</option>
        <?php foreach ($colegios as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $filtroCol===$c['id']?'selected':'' ?>><?= htmlspecialchars($c['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label mb-1 small fw-semibold">Estado</label>
      <select name="estado" class="form-select form-select-sm">
        <option value="">Todos</option>
        <?php foreach ($estLabel as $k=>$v): ?>
          <option value="<?= $k ?>" <?= $filtroEst===$k?'selected':'' ?>><?= $v ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label mb-1 small fw-semibold">Desde</label>
      <input type="date" name="desde" class="form-control form-control-sm" value="<?= $desde ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label mb-1 small fw-semibold">Hasta</label>
      <input type="date" name="hasta" class="form-control form-control-sm" value="<?= $hasta ?>">
    </div>
    <div class="col-md-3">
      <button type="submit" class="btn btn-primary btn-sm me-1"><i class="bi bi-funnel me-1"></i>Filtrar</button>
      <a href="?" class="btn btn-outline-secondary btn-sm">Limpiar</a>
    </div>
  </form>
</div>

<div class="section-card">
  <div class="table-responsive">
    <table class="table table-hover table-inv mb-0">
      <thead><tr>
        <th>C&#243;digo</th><th>Colegio</th><th>Fecha</th>
        <th>Kits</th><th>Transportadora</th><th>Gu&#237;a</th>
        <th>Recibe</th><th>Entregado</th><th>Estado</th><th>Acciones</th>
      </tr></thead>
      <tbody>
      <?php foreach ($despachos as $d): ?>
      <tr>
        <td>
          <a href="<?= APP_URL ?>/modules/despachos/ver.php?id=<?= $d['id'] ?>"
             class="fw-bold text-primary text-decoration-none font-monospace">
            <?= htmlspecialchars($d['codigo']) ?>
          </a>
        </td>
        <td>
          <?php if ($d['colegio_nombre']): ?>
            <div class="fw-semibold"><?= htmlspecialchars($d['colegio_nombre']) ?></div>
            <div class="text-muted small"><?= htmlspecialchars($d['municipio'] ?? '') ?></div>
          <?php else: ?><span class="text-muted">&#8212;</span><?php endif; ?>
        </td>
        <td class="text-nowrap"><?= date('d/m/Y', strtotime($d['fecha'])) ?></td>
        <td class="text-center">
          <span class="badge bg-primary bg-opacity-10 text-primary border" style="border-color:#93c5fd !important;">
            <?= (int)($d['num_kits']??0) ?> ref / <?= (int)($d['total_kits']??0) ?> uds
          </span>
        </td>
        <td class="small"><?= htmlspecialchars($d['transportadora'] ?? '&#8212;') ?></td>
        <td class="font-monospace small"><?= htmlspecialchars($d['guia_transporte'] ?? '&#8212;') ?></td>
        <td class="small">
          <?php if ($d['nombre_recibe']): ?>
            <div><?= htmlspecialchars($d['nombre_recibe']) ?></div>
            <div class="text-muted"><?= htmlspecialchars($d['cargo_recibe'] ?? '') ?></div>
          <?php else: ?><span class="text-muted">&#8212;</span><?php endif; ?>
        </td>
        <td class="text-nowrap small"><?= $d['fecha_entrega'] ? date('d/m/Y', strtotime($d['fecha_entrega'])) : '&#8212;' ?></td>
        <td>
          <div class="dropdown">
            <button class="badge bg-<?= $estColor[$d['estado']] ?> border-0 dropdown-toggle"
                    style="cursor:pointer;font-size:.75rem;padding:.35em .65em;"
                    data-bs-toggle="dropdown">
              <?= $estIcon[$d['estado']] ?> <?= $estLabel[$d['estado']] ?>
            </button>
            <ul class="dropdown-menu shadow-sm" style="min-width:160px;">
              <li><span class="dropdown-header small">Cambiar a:</span></li>
              <?php foreach ($estLabel as $est => $lbl): if ($est===$d['estado']) continue; ?>
              <li>
                <form method="POST" class="m-0">
                  <input type="hidden" name="action"      value="cambiar_estado">
                  <input type="hidden" name="csrf"        value="<?= $csrf ?>">
                  <input type="hidden" name="despacho_id" value="<?= $d['id'] ?>">
                  <input type="hidden" name="estado"      value="<?= $est ?>">
                  <button type="submit" class="dropdown-item small py-1">
                    <?= $estIcon[$est] ?> <?= $lbl ?>
                  </button>
                </form>
              </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </td>
        <td>
          <div class="d-flex gap-1">
            <a href="<?= APP_URL ?>/modules/despachos/ver.php?id=<?= $d['id'] ?>"
               class="btn btn-sm btn-outline-info" title="Ver detalle">
              <i class="bi bi-eye"></i>
            </a>
            <?php if ($d['estado'] === 'preparando'): ?>
            <a href="<?= APP_URL ?>/modules/despachos/form.php?id=<?= $d['id'] ?>"
               class="btn btn-sm btn-outline-primary" title="Editar">
              <i class="bi bi-pencil"></i>
            </a>
            <a href="?del=<?= $d['id'] ?>&csrf=<?= $csrf ?>"
               class="btn btn-sm btn-outline-danger" title="Eliminar"
               onclick="return confirm('Eliminar despacho <?= htmlspecialchars($d['codigo']) ?>?')">
              <i class="bi bi-trash"></i>
            </a>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($despachos)): ?>
      <tr><td colspan="10" class="text-center text-muted py-5">
        <i class="bi bi-truck fs-2 d-block mb-2 opacity-25"></i>No hay despachos registrados
      </td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
  <?php if ($pag['total_paginas'] > 1): ?>
  <nav class="mt-3"><ul class="pagination pagination-sm justify-content-center mb-0">
    <?php for ($i=1; $i<=$pag['total_paginas']; $i++): ?>
      <li class="page-item <?= $i==$pag['pagina']?'active':'' ?>">
        <a class="page-link" href="?colegio=<?= $filtroCol ?>&estado=<?= $filtroEst ?>&desde=<?= $desde ?>&hasta=<?= $hasta ?>&p=<?= $i ?>"><?= $i ?></a>
      </li>
    <?php endfor; ?>
  </ul></nav>
  <?php endif; ?>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
