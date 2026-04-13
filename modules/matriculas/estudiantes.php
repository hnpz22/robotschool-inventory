<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();

$db         = Database::get();
$pageTitle  = 'Estudiantes';
$activeMenu = 'matriculas';
$error = $success = '';

// Eliminar
if (isset($_GET['del']) && Auth::csrfVerify($_GET['csrf'] ?? '')) {
    $db->prepare("UPDATE estudiantes SET activo=0 WHERE id=?")->execute([(int)$_GET['del']]);
    $success = 'Estudiante desactivado.';
}

// Filtros
$buscar = trim($_GET['q']    ?? '');
$fCiudad= trim($_GET['ciudad']?? '');

$where  = ["e.activo=1"];
$params = [];
if ($buscar) {
    $where[]  = "(e.nombres LIKE ? OR e.apellidos LIKE ? OR e.documento LIKE ? OR e.acudiente LIKE ? OR e.telefono LIKE ?)";
    $params   = array_merge($params, ["%$buscar%","%$buscar%","%$buscar%","%$buscar%","%$buscar%"]);
}
if ($fCiudad) { $where[] = "e.ciudad=?"; $params[] = $fCiudad; }
$whereStr = implode(' AND ', $where);

$total = $db->prepare("SELECT COUNT(*) FROM estudiantes e WHERE $whereStr");
$total->execute($params);
$totalEst = (int)$total->fetchColumn();

$estudiantes = $db->prepare("
    SELECT e.*,
      TIMESTAMPDIFF(YEAR, e.fecha_nac, CURDATE()) AS edad_calc,
      (SELECT COUNT(*) FROM matriculas m WHERE m.estudiante_id=e.id AND m.estado='activa') AS matriculas_activas
    FROM estudiantes e
    WHERE $whereStr
    ORDER BY e.apellidos, e.nombres
    LIMIT 100
");
$estudiantes->execute($params);
$estudiantes = $estudiantes->fetchAll();

$AVATAR_COLORS = ['#185FA5','#16a34a','#dc2626','#7c3aed','#d97706','#0891b2','#be185d','#065f46'];

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
/* Específicos de estudiantes — el sistema ya define .section-card */
.est-card{background:#fff;border:1px solid var(--rs-gray-200);border-radius:var(--rs-radius);overflow:hidden;transition:transform .2s,box-shadow .2s}
.est-card:hover{transform:translateY(-2px);box-shadow:var(--rs-shadow-md);border-color:var(--rs-blue)}
.avatar-sm{width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.9rem;font-weight:700;color:#fff;flex-shrink:0}
.badge-mat{font-size:.65rem;padding:.15rem .45rem;border-radius:20px;font-weight:700}
</style>

<div class="page-header">
  <div>
    <h4 class="page-header-title"><i class="bi bi-people me-2"></i>Estudiantes</h4>
    <p class="page-header-sub"><?= number_format($totalEst) ?> estudiantes registrados</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="index.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Volver</a>
    <a href="estudiante_form.php" class="btn btn-primary btn-sm fw-bold">
      <i class="bi bi-person-plus me-1"></i>Nuevo Estudiante
    </a>
  </div>
</div>

<?php if ($error):   ?><div class="alert alert-danger  py-2 small"><?= htmlspecialchars($error)   ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<!-- Buscador -->
<div class="filter-bar">
  <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
    <div class="input-group input-group-sm flex-grow-1" style="max-width:400px">
      <span class="input-group-text"><i class="bi bi-search"></i></span>
      <input type="text" name="q" class="form-control"
             placeholder="Buscar nombre, documento, acudiente, telefono..."
             value="<?= htmlspecialchars($buscar) ?>">
    </div>
    <button type="submit" class="btn btn-primary btn-sm">Buscar</button>
    <?php if ($buscar||$fCiudad): ?>
      <a href="?" class="btn btn-outline-secondary btn-sm">&#10005; Limpiar</a>
    <?php endif; ?>
    <span class="text-muted small ms-auto"><?= count($estudiantes) ?> resultados</span>
  </form>
</div>

<!-- Lista -->
<?php if (empty($estudiantes)): ?>
<div class="section-card text-center py-5">
  <div style="font-size:3rem">&#x1F9D1;</div>
  <h5 class="fw-bold mt-2">No hay estudiantes<?= $buscar ? ' con esa busqueda' : '' ?></h5>
  <a href="estudiante_form.php" class="btn btn-primary mt-2">
    <i class="bi bi-person-plus me-1"></i>Registrar primer estudiante
  </a>
</div>
<?php else: ?>
<div class="row g-2">
<?php foreach ($estudiantes as $e):
  $initials   = strtoupper(substr($e['nombres'],0,1).substr($e['apellidos'],0,1));
  $color      = $e['avatar_color'] ?? $AVATAR_COLORS[abs(crc32($e['nombres']))%count($AVATAR_COLORS)];
  $edad       = $e['edad_calc'] ?? ($e['fecha_nac'] ? date_diff(date_create($e['fecha_nac']),date_create('today'))->y : null);
  $autFoto    = !empty($e['autorizacion_foto']);
  $autDatos   = !empty($e['autorizacion_datos']);
?>
<div class="col-md-6 col-xl-4">
  <div class="est-card">
    <div class="p-3 d-flex align-items-start gap-3">
      <!-- Avatar -->
      <div class="avatar-sm" style="background:<?= $color ?>">
        <?php if ($e['foto']): ?>
          <img src="<?= UPLOAD_URL.htmlspecialchars($e['foto']) ?>"
               style="width:44px;height:44px;border-radius:50%;object-fit:cover" alt="">
        <?php else: ?>
          <?= $initials ?>
        <?php endif; ?>
      </div>
      <!-- Info -->
      <div class="flex-grow-1 min-w-0">
        <div class="fw-bold" style="font-size:.9rem">
          <?= htmlspecialchars($e['apellidos'].', '.$e['nombres']) ?>
        </div>
        <div class="text-muted" style="font-size:.75rem">
          <?= htmlspecialchars(($e['tipo_doc']??'TI').' '.($e['documento']??'')) ?>
          <?php if ($edad): ?>&nbsp;&middot;&nbsp;<?= $edad ?> años<?php endif; ?>
          <?php if ($e['rh']): ?>&nbsp;&middot;&nbsp;<?= htmlspecialchars($e['rh']) ?><?php endif; ?>
        </div>
        <!-- Acudiente -->
        <div class="text-muted" style="font-size:.73rem;margin-top:2px">
          <i class="bi bi-people me-1"></i>
          <?= htmlspecialchars($e['acudiente'] ?? '') ?>
          <?php if ($e['parentesco']): ?>(<?= htmlspecialchars($e['parentesco']) ?>)<?php endif; ?>
        </div>
        <?php if ($e['telefono']): ?>
        <div class="text-muted" style="font-size:.73rem">
          <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($e['telefono']) ?>
          <?php if ($e['telefono2']): ?>&nbsp;&middot;&nbsp;<?= htmlspecialchars($e['telefono2']) ?><?php endif; ?>
        </div>
        <?php endif; ?>
        <!-- EPS -->
        <?php if ($e['eps']): ?>
        <div style="font-size:.7rem;color:#dc2626;margin-top:2px">
          <i class="bi bi-heart-pulse me-1"></i><?= htmlspecialchars($e['eps']) ?>
          <?php if ($e['num_seguro']): ?>&nbsp;#<?= htmlspecialchars($e['num_seguro']) ?><?php endif; ?>
        </div>
        <?php endif; ?>
        <!-- Matriculas activas + autorizaciones -->
        <div class="d-flex gap-1 mt-2 flex-wrap">
          <?php if ($e['matriculas_activas'] > 0): ?>
            <span class="badge-mat" style="background:#dbeafe;color:#1e40af">
              <i class="bi bi-check-circle me-1"></i><?= $e['matriculas_activas'] ?> matricula(s)
            </span>
          <?php endif; ?>
          <?php if ($autFoto): ?>
            <span class="badge-mat" style="background:#dcfce7;color:#166534" title="Autoriza fotos">&#x1F4F8;</span>
          <?php else: ?>
            <span class="badge-mat" style="background:#fee2e2;color:#991b1b" title="No autoriza fotos">&#x1F6AB;&#x1F4F8;</span>
          <?php endif; ?>
          <?php if ($autDatos): ?>
            <span class="badge-mat" style="background:#dcfce7;color:#166534" title="Autoriza datos">&#x1F512;</span>
          <?php else: ?>
            <span class="badge-mat" style="background:#fee2e2;color:#991b1b" title="No autoriza datos">&#x1F513;</span>
          <?php endif; ?>
        </div>
      </div>
      <!-- Acciones -->
      <div class="d-flex flex-column gap-1">
        <a href="estudiante_form.php?id=<?= $e['id'] ?>"
           class="btn btn-sm btn-outline-primary py-0 px-1" title="Editar">
          <i class="bi bi-pencil"></i>
        </a>
        <a href="ficha_estudiante.php?id=<?= $e['id'] ?>" target="_blank"
           class="btn btn-sm btn-outline-secondary py-0 px-1" title="Imprimir ficha">
          <i class="bi bi-printer"></i>
        </a>
        <a href="nueva_matricula.php?est_id=<?= $e['id'] ?>"
           class="btn btn-sm btn-outline-success py-0 px-1" title="Matricular">
          <i class="bi bi-plus-lg"></i>
        </a>
        <?php if (Auth::isAdmin()): ?>
        <a href="?del=<?= $e['id'] ?>&csrf=<?= Auth::csrfToken() ?>"
           class="btn btn-sm btn-outline-danger py-0 px-1"
           title="Desactivar"
           onclick="return confirm('Desactivar a <?= addslashes($e['nombres'].' '.$e['apellidos']) ?>?')">
          <i class="bi bi-person-x"></i>
        </a>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($e['alergias'] || $e['condicion_medica']): ?>
    <div class="px-3 pb-2">
      <div style="background:#fff1f2;border-radius:6px;padding:.3rem .6rem;font-size:.72rem;color:#be123c">
        <i class="bi bi-exclamation-triangle me-1"></i>
        <?php if ($e['alergias']): ?>Alergias: <?= htmlspecialchars($e['alergias']) ?>. <?php endif; ?>
        <?php if ($e['condicion_medica']): ?><?= htmlspecialchars($e['condicion_medica']) ?><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
