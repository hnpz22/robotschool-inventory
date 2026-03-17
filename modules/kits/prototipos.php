<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();

$db = Database::get();
$pageTitle  = 'Prototipos y Proyectos';
$activeMenu = 'elementos';

if (isset($_GET['del']) && Auth::csrfVerify($_GET['csrf'] ?? '')) {
    Auth::requireAdmin();
    $db->prepare("UPDATE prototipos SET activo=0 WHERE id=?")->execute([(int)$_GET['del']]);
    header('Location: ?ok=1'); exit;
}

$q    = trim($_GET['q']    ?? '');
$tipo = trim($_GET['tipo'] ?? '');

$where  = ['activo=1'];
$params = [];
if ($q)    { $where[] = '(nombre LIKE ? OR codigo LIKE ?)'; $params = array_merge($params,["%$q%","%$q%"]); }
if ($tipo) { $where[] = 'FIND_IN_SET(?,tipo_fabricacion)';  $params[] = $tipo; }

$ws = implode(' AND ', $where);
$prototipos = $db->prepare("SELECT * FROM prototipos WHERE $ws ORDER BY updated_at DESC");
$prototipos->execute($params);
$prototipos = $prototipos->fetchAll();

require_once dirname(__DIR__, 2) . '/includes/header.php';

$tipoIcon  = ['laser'=>'✂️','impresion_3d'=>'🖨️','manual'=>'&#x1F527;','electronica'=>'⚡','mixto'=>'🔀'];
$tipoColor = ['laser'=>'danger','impresion_3d'=>'primary','manual'=>'secondary','electronica'=>'warning','mixto'=>'info'];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-0">Prototipos y Proyectos</h4>
    <p class="text-muted small mb-0">Fabricados por ROBOTSchool &mdash; Cortadora Láser · Impresora 3D · Ensamble</p>
  </div>
  <a href="<?= APP_URL ?>/modules/kits/prototipo_form.php" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-lg me-1"></i>Nuevo Prototipo
  </a>
</div>

<?php if (!empty($_GET['ok'])): ?><div class="alert alert-success py-2">Acción realizada.</div><?php endif; ?>

<!-- Filtros -->
<div class="section-card mb-3">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-5">
      <input type="text" name="q" class="form-control form-control-sm" placeholder="Buscar por nombre o código..." value="<?= htmlspecialchars($q) ?>">
    </div>
    <div class="col-md-3">
      <select name="tipo" class="form-select form-select-sm">
        <option value="">Todos los tipos</option>
        <option value="laser"        <?= $tipo==='laser'?'selected':'' ?>>✂️ Cortadora Láser</option>
        <option value="impresion_3d" <?= $tipo==='impresion_3d'?'selected':'' ?>>🖨️ Impresora 3D</option>
        <option value="manual"       <?= $tipo==='manual'?'selected':'' ?>>&#x1F527; Manual</option>
        <option value="electronica"  <?= $tipo==='electronica'?'selected':'' ?>>⚡ Electrónica</option>
        <option value="mixto"        <?= $tipo==='mixto'?'selected':'' ?>>🔀 Mixto</option>
      </select>
    </div>
    <div class="col-md-2">
      <button type="submit" class="btn btn-primary btn-sm w-100">Filtrar</button>
    </div>
    <div class="col-md-2">
      <a href="?" class="btn btn-outline-secondary btn-sm w-100">Limpiar</a>
    </div>
  </form>
</div>

<!-- Grid de prototipos -->
<div class="row g-3">
<?php foreach ($prototipos as $p):
  $tipos = explode(',', $p['tipo_fabricacion']);
?>
<div class="col-md-6 col-xl-4">
  <div class="card stat-card h-100">
    <div class="card-body p-0">
      <?php if ($p['foto']): ?>
        <img src="<?= htmlspecialchars(fotoUrl($p['foto'])) ?>" class="w-100 rounded-top"
             style="height:140px;object-fit:cover;" alt="">
      <?php else: ?>
        <div class="w-100 rounded-top bg-light d-flex align-items-center justify-content-center"
             style="height:100px;font-size:2.5rem;">
          <?= $tipoIcon[$tipos[0]] ?? '&#x1F527;' ?>
        </div>
      <?php endif; ?>
      <div class="p-3">
        <div class="d-flex justify-content-between align-items-start mb-1">
          <code class="text-primary"><?= htmlspecialchars($p['codigo']) ?></code>
          <span class="badge bg-<?= $tipoColor[$tipos[0]] ?? 'secondary' ?>"><?= $tipoIcon[$tipos[0]] ?? '' ?> <?= ucfirst(str_replace('_',' ',$tipos[0])) ?></span>
        </div>
        <h6 class="fw-bold mb-2"><?= htmlspecialchars($p['nombre']) ?></h6>

        <!-- Badges de tipo de fabricación -->
        <div class="d-flex flex-wrap gap-1 mb-2">
          <?php foreach ($tipos as $t): if(!$t) continue; ?>
            <span class="badge bg-<?= $tipoColor[$t] ?? 'secondary' ?> bg-opacity-15 text-<?= $tipoColor[$t] ?? 'secondary' ?> border" style="font-size:.7rem;">
              <?= $tipoIcon[$t] ?? '' ?> <?= str_replace('_',' ',ucfirst($t)) ?>
            </span>
          <?php endforeach; ?>
        </div>

        <?php if ($p['material_principal']): ?>
          <div class="text-muted small mb-1"><i class="bi bi-layers me-1"></i><?= htmlspecialchars($p['material_principal']) ?>
            <?php if ($p['grosor_mm']): ?> · <?= $p['grosor_mm'] ?>mm<?php endif; ?>
          </div>
        <?php endif; ?>

        <!-- Tiempos -->
        <div class="d-flex gap-3 mb-2" style="font-size:.78rem;">
          <?php if ($p['tiempo_laser_min']): ?>
            <span class="text-muted"><i class="bi bi-stopwatch me-1"></i>Láser: <?= $p['tiempo_laser_min'] ?>min</span>
          <?php endif; ?>
          <?php if ($p['tiempo_3d_min']): ?>
            <span class="text-muted"><i class="bi bi-printer me-1"></i>3D: <?= $p['tiempo_3d_min'] ?>min</span>
          <?php endif; ?>
          <?php if ($p['tiempo_ensamble_min']): ?>
            <span class="text-muted"><i class="bi bi-wrench me-1"></i>Ensamble: <?= $p['tiempo_ensamble_min'] ?>min</span>
          <?php endif; ?>
        </div>

        <!-- Costos -->
        <?php if ($p['costo_total_cop'] > 0): ?>
          <div class="d-flex justify-content-between align-items-center p-2 rounded mt-1" style="background:#f0fdf4;font-size:.82rem;">
            <span class="text-muted">Costo total</span>
            <span class="fw-bold text-success"><?= cop($p['costo_total_cop']) ?></span>
          </div>
        <?php endif; ?>

        <div class="d-flex gap-1 mt-3">
          <a href="<?= APP_URL ?>/modules/kits/prototipo_form.php?id=<?= $p['id'] ?>"
             class="btn btn-sm btn-outline-primary flex-grow-1"><i class="bi bi-pencil me-1"></i>Editar</a>
          <?php if (Auth::isAdmin()): ?>
          <a href="?del=<?= $p['id'] ?>&csrf=<?= Auth::csrfToken() ?>"
             class="btn btn-sm btn-outline-danger"
             data-confirm="¿Eliminar '<?= addslashes($p['nombre']) ?>'?">
            <i class="bi bi-trash"></i>
          </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php if (empty($prototipos)): ?>
<div class="col-12 text-center text-muted py-5">
  <div style="font-size:3rem;">✂️</div>
  <p>No hay prototipos registrados aún</p>
</div>
<?php endif; ?>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
