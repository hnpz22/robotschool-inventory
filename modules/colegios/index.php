<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();

$db = Database::get();
$pageTitle  = 'Colegios';
$activeMenu = 'colegios';

// Eliminar (desactivar)
if (isset($_GET['del']) && Auth::csrfVerify($_GET['csrf'] ?? '')) {
    Auth::requireAdmin();
    $db->prepare("UPDATE colegios SET activo=0 WHERE id=?")->execute([(int)$_GET['del']]);
    header('Location: ' . APP_URL . '/modules/colegios/?ok=eliminado'); exit;
}

$q      = trim($_GET['q'] ?? '');
$ciudad = trim($_GET['ciudad'] ?? '');
$tipo   = trim($_GET['tipo']   ?? '');

$where  = ['col.activo=1'];
$params = [];
if ($q) {
    $where[] = '(col.nombre LIKE ? OR col.contacto LIKE ? OR col.email LIKE ?)';
    $params  = array_merge($params, ["%$q%","%$q%","%$q%"]);
}
if ($ciudad) { $where[] = 'col.ciudad = ?';         $params[] = $ciudad; }
if ($tipo)   { $where[] = 'col.tipo   = ?';         $params[] = $tipo; }

$ws = implode(' AND ', $where);

$colegios = $db->prepare("
  SELECT col.*,
    COUNT(DISTINCT cur.id)                                           AS total_cursos,
    COALESCE(SUM(cur.num_estudiantes),0)                             AS total_estudiantes,
    COUNT(DISTINCT CASE WHEN cur.kit_id IS NOT NULL THEN cur.id END) AS cursos_con_kit
  FROM colegios col
  LEFT JOIN cursos cur ON cur.colegio_id = col.id AND cur.activo = 1
  WHERE $ws
  GROUP BY col.id
  ORDER BY col.nombre
");
$colegios->execute($params);
$colegios = $colegios->fetchAll();

// Totales para tarjetas resumen
$totales = $db->query("
  SELECT
    COUNT(DISTINCT col.id)               AS total_colegios,
    COALESCE(SUM(cur.num_estudiantes),0) AS total_estudiantes,
    COUNT(DISTINCT cur.kit_id)           AS kits_distintos,
    COUNT(DISTINCT col.ciudad)           AS ciudades
  FROM colegios col
  LEFT JOIN cursos cur ON cur.colegio_id = col.id AND cur.activo = 1
  WHERE col.activo = 1
")->fetch();

// Ciudades para filtro
$ciudades = $db->query("SELECT DISTINCT ciudad FROM colegios WHERE activo=1 AND ciudad IS NOT NULL ORDER BY ciudad")->fetchAll(PDO::FETCH_COLUMN);

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- ── Header ── -->
<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-0">Colegios</h4>
    <p class="text-muted small mb-0">Gestión de instituciones educativas y sus cursos</p>
  </div>
  <a href="<?= APP_URL ?>/modules/colegios/form.php" class="btn btn-primary btn-sm">
    <i class="bi bi-plus-lg me-1"></i>Nuevo Colegio
  </a>
</div>

<?php if (!empty($_GET['ok'])): ?>
<div class="alert alert-success alert-dismissible py-2"><i class="bi bi-check-circle me-2"></i>
  <?= ['creado'=>'Colegio creado.','guardado'=>'Cambios guardados.','eliminado'=>'Colegio desactivado.'][$_GET['ok']] ?? 'OK' ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- ── Stats cards ── -->
<div class="row g-3 mb-4">
  <?php
  $stats = [
    ['&#x1F3EB;', 'Colegios activos',  $totales['total_colegios'],   'primary'],
    ['🎓', 'Estudiantes',       number_format($totales['total_estudiantes']), 'success'],
    ['&#x1F916;', 'Kits distintos',    $totales['kits_distintos'],   'warning'],
    ['📍', 'Ciudades',          $totales['ciudades'],          'info'],
  ];
  foreach ($stats as [$ico, $lbl, $val, $color]): ?>
  <div class="col-md-3 col-6">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="icon-box bg-<?= $color ?> bg-opacity-10" style="font-size:1.5rem;"><?= $ico ?></div>
        <div>
          <div class="dashboard-stat-num text-<?= $color ?>"><?= $val ?></div>
          <div class="dashboard-stat-lbl"><?= $lbl ?></div>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- ── Filtros ── -->
<div class="section-card mb-3">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-4">
      <label class="form-label mb-1">Buscar</label>
      <div class="input-group input-group-sm">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" name="q" class="form-control" placeholder="Nombre, contacto..." value="<?= htmlspecialchars($q) ?>">
      </div>
    </div>
    <div class="col-md-3">
      <label class="form-label mb-1">Ciudad</label>
      <select name="ciudad" class="form-select form-select-sm">
        <option value="">Todas</option>
        <?php foreach ($ciudades as $c): ?>
          <option value="<?= htmlspecialchars($c) ?>" <?= $ciudad===$c?'selected':'' ?>><?= htmlspecialchars($c) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label mb-1">Tipo</label>
      <select name="tipo" class="form-select form-select-sm">
        <option value="">Todos</option>
        <option value="privado"  <?= $tipo==='privado'?'selected':'' ?>>Privado</option>
        <option value="publico"  <?= $tipo==='publico'?'selected':'' ?>>Público</option>
        <option value="mixto"    <?= $tipo==='mixto'?'selected':'' ?>>Mixto</option>
      </select>
    </div>
    <div class="col-md-3 d-flex gap-2">
      <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
      <a href="?" class="btn btn-outline-secondary btn-sm">Limpiar</a>
    </div>
  </form>
</div>

<!-- ── Tabla ── -->
<div class="section-card">
  <div class="table-responsive">
    <table class="table table-hover table-inv mb-0">
      <thead><tr>
        <th>Logo</th>
        <th>Colegio</th>
        <th>Ciudad</th>
        <th>Tipo</th>
        <th>Contacto</th>
        <th class="text-center">Cursos</th>
        <th class="text-center">Estudiantes</th>
        <th class="text-center">Con Kit</th>
        <th>Acciones</th>
      </tr></thead>
      <tbody>
      <?php foreach ($colegios as $col): ?>
      <tr>
        <td>
          <?php if ($col['logo']): ?>
            <img src="<?= htmlspecialchars(fotoUrl($col['logo'])) ?>" class="elem-foto" alt="">
          <?php else: ?>
            <div class="elem-foto-placeholder" style="background:#eaf1fd;">&#x1F3EB;</div>
          <?php endif; ?>
        </td>
        <td>
          <div class="fw-semibold"><?= htmlspecialchars($col['nombre']) ?></div>
          <?php if ($col['rector']): ?>
            <div class="text-muted" style="font-size:.75rem;"><i class="bi bi-person me-1"></i><?= htmlspecialchars($col['rector']) ?></div>
          <?php endif; ?>
        </td>
        <td class="text-muted small"><?= htmlspecialchars($col['ciudad'] ?? '&mdash;') ?></td>
        <td>
          <?php $tc=['privado'=>'primary','publico'=>'success','mixto'=>'warning']; ?>
          <span class="badge bg-<?= $tc[$col['tipo']] ?? 'secondary' ?> bg-opacity-10 text-<?= $tc[$col['tipo']] ?? 'secondary' ?> border border-<?= $tc[$col['tipo']] ?? 'secondary' ?>" style="border-opacity:.3;">
            <?= ucfirst($col['tipo'] ?? '&mdash;') ?>
          </span>
        </td>
        <td>
          <?php if ($col['email']): ?><div class="small"><i class="bi bi-envelope me-1 text-muted"></i><?= htmlspecialchars($col['email']) ?></div><?php endif; ?>
          <?php if ($col['telefono']): ?><div class="small"><i class="bi bi-telephone me-1 text-muted"></i><?= htmlspecialchars($col['telefono']) ?></div><?php endif; ?>
        </td>
        <td class="text-center fw-bold"><?= $col['total_cursos'] ?></td>
        <td class="text-center">
          <?= $col['total_estudiantes'] > 0 ? number_format($col['total_estudiantes']) : '<span class="text-muted">&mdash;</span>' ?>
        </td>
        <td class="text-center">
          <?php if ($col['cursos_con_kit'] > 0): ?>
            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i><?= $col['cursos_con_kit'] ?></span>
          <?php else: ?>
            <span class="badge bg-light text-muted border">Sin kit</span>
          <?php endif; ?>
        </td>
        <td>
          <div class="d-flex gap-1">
            <a href="<?= APP_URL ?>/modules/colegios/ver.php?id=<?= $col['id'] ?>"
               class="btn btn-sm btn-outline-info" title="Ver detalle">
              <i class="bi bi-eye"></i>
            </a>
            <a href="<?= APP_URL ?>/modules/colegios/form.php?id=<?= $col['id'] ?>"
               class="btn btn-sm btn-outline-primary" title="Editar">
              <i class="bi bi-pencil"></i>
            </a>
            <?php if (Auth::isAdmin()): ?>
            <a href="?del=<?= $col['id'] ?>&csrf=<?= Auth::csrfToken() ?>"
               class="btn btn-sm btn-outline-danger"
               data-confirm="¿Desactivar el colegio '<?= addslashes($col['nombre']) ?>'?">
              <i class="bi bi-trash"></i>
            </a>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($colegios)): ?>
        <tr><td colspan="9" class="text-center text-muted py-5">
          <i class="bi bi-building fs-2 d-block mb-2"></i>No se encontraron colegios
        </td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
