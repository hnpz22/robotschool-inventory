<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();

$db = Database::get();
$pageTitle  = 'Academico LMS';
$activeMenu = 'academico';

// Verificar si las tablas existen
$tablaExiste = $db->query("SHOW TABLES LIKE 'acad_colegios'")->fetchColumn();

$colegios = $tablaExiste ? $db->query("
    SELECT ac.*,
      (SELECT COUNT(*) FROM acad_cursos cur WHERE cur.colegio_id=ac.id AND cur.activo=1) AS num_cursos,
      (SELECT COUNT(*) FROM acad_estudiantes est JOIN acad_cursos cur ON cur.id=est.curso_id WHERE cur.colegio_id=ac.id AND est.activo=1) AS num_estudiantes
    FROM acad_colegios ac WHERE ac.activo=1 ORDER BY ac.nombre
")->fetchAll() : [];

$stats = $tablaExiste ? $db->query("
    SELECT
      (SELECT COUNT(*) FROM acad_colegios   WHERE activo=1) AS colegios,
      (SELECT COUNT(*) FROM acad_cursos     WHERE activo=1) AS cursos,
      (SELECT COUNT(*) FROM acad_estudiantes WHERE activo=1) AS estudiantes,
      (SELECT COUNT(*) FROM acad_actividades WHERE activo=1) AS actividades
")->fetch() : ['colegios'=>0,'cursos'=>0,'estudiantes'=>0,'actividades'=>0];

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
.sc{background:#fff;border-radius:14px;border:1px solid #e2e8f0;padding:1rem 1.2rem;margin-bottom:1rem}
.col-card{background:#fff;border:1.5px solid #e2e8f0;border-radius:14px;overflow:hidden;transition:.15s;cursor:pointer}
.col-card:hover{border-color:#185FA5;box-shadow:0 4px 16px rgba(24,95,165,.12);transform:translateY(-2px)}
.col-logo-ph{width:56px;height:56px;border-radius:10px;background:linear-gradient(135deg,#e0e7ff,#c7d2fe);display:flex;align-items:center;justify-content:center;font-size:1.5rem;flex-shrink:0}
.stat-pill{display:inline-flex;align-items:center;gap:.3rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:20px;padding:.2rem .6rem;font-size:.72rem;font-weight:600}
.stat-box{background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:1rem;text-align:center}
.nivel-pill{font-size:.68rem;padding:.15rem .5rem;border-radius:20px;font-weight:700}
.nivel-primaria{background:#dbeafe;color:#1e40af}
.nivel-secundaria{background:#fef9c3;color:#854d0e}
.nivel-media{background:#fee2e2;color:#991b1b}
</style>

<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h4 class="fw-bold mb-0">&#x1F4DA; Acad&eacute;mico LMS</h4>
    <p class="text-muted small mb-0">Gesti&oacute;n acad&eacute;mica para colegios, cursos y estudiantes</p>
  </div>
  <div class="d-flex gap-2">
    <a href="materiales.php" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-file-earmark-text me-1"></i>Materiales
    </a>
    <a href="colegio_form.php" class="btn btn-primary btn-sm fw-bold">
      <i class="bi bi-plus-lg me-1"></i>Nuevo Colegio
    </a>
  </div>
</div>

<?php if (!$tablaExiste): ?>
<div class="alert alert-warning">
  <i class="bi bi-exclamation-triangle me-2"></i>
  Las tablas del m&oacute;dulo acad&eacute;mico no existen aun.
  Ejecuta <strong>migration_v3.0_matriculas_academico.sql</strong> en phpMyAdmin para activar este m&oacute;dulo.
</div>
<?php endif; ?>

<!-- Stats -->
<div class="row g-3 mb-3">
  <?php
  $statItems = [
    ['colegios',    'Colegios',    'bi-building',       '#185FA5'],
    ['cursos',      'Cursos',      'bi-journal-text',   '#16a34a'],
    ['estudiantes', 'Estudiantes', 'bi-people',         '#7c3aed'],
    ['actividades', 'Actividades', 'bi-lightning-charge','#d97706'],
  ];
  foreach ($statItems as [$key,$lbl,$icon,$color]):
  ?>
  <div class="col-6 col-md-3">
    <div class="stat-box">
      <i class="bi <?= $icon ?>" style="font-size:1.4rem;color:<?= $color ?>"></i>
      <div class="fs-3 fw-bold mt-1"><?= $stats[$key] ?></div>
      <div class="text-muted small"><?= $lbl ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Colegios -->
<?php if (empty($colegios)): ?>
<div class="sc text-center py-5">
  <div style="font-size:3rem">&#x1F3EB;</div>
  <h5 class="fw-bold mt-2">Sin colegios registrados</h5>
  <p class="text-muted">Agrega el primer colegio para empezar a gestionar cursos y estudiantes</p>
  <a href="colegio_form.php" class="btn btn-primary">
    <i class="bi bi-plus-lg me-1"></i>Agregar Colegio
  </a>
</div>
<?php else: ?>
<div class="row g-3">
<?php foreach ($colegios as $c): ?>
<div class="col-md-6 col-xl-4">
  <div class="col-card" onclick="window.location='colegio.php?id=<?= $c['id'] ?>'">
    <div class="p-3">
      <div class="d-flex align-items-center gap-3 mb-3">
        <?php if ($c['logo']): ?>
          <img src="<?= UPLOAD_URL.htmlspecialchars($c['logo']) ?>"
               style="width:56px;height:56px;border-radius:10px;object-fit:contain;border:1px solid #e2e8f0">
        <?php else: ?>
          <div class="col-logo-ph">&#x1F3EB;</div>
        <?php endif; ?>
        <div class="flex-grow-1 min-w-0">
          <div class="fw-bold" style="font-size:.9rem"><?= htmlspecialchars($c['nombre']) ?></div>
          <div class="text-muted small"><?= htmlspecialchars($c['ciudad'] ?? '') ?></div>
        </div>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <span class="stat-pill"><i class="bi bi-journal-text text-primary"></i><?= $c['num_cursos'] ?> cursos</span>
        <span class="stat-pill"><i class="bi bi-people text-success"></i><?= $c['num_estudiantes'] ?> estudiantes</span>
      </div>
    </div>
    <div class="d-flex border-top">
      <a href="colegio.php?id=<?= $c['id'] ?>"
         class="flex-fill text-center py-2 text-decoration-none text-muted small"
         style="font-size:.78rem;border-right:1px solid #e2e8f0" onclick="event.stopPropagation()">
        <i class="bi bi-eye me-1"></i>Ver
      </a>
      <a href="colegio_form.php?id=<?= $c['id'] ?>"
         class="flex-fill text-center py-2 text-decoration-none text-muted small"
         style="font-size:.78rem;border-right:1px solid #e2e8f0" onclick="event.stopPropagation()">
        <i class="bi bi-pencil me-1"></i>Editar
      </a>
      <a href="colegio.php?id=<?= $c['id'] ?>#cursos"
         class="flex-fill text-center py-2 text-decoration-none text-muted small"
         style="font-size:.78rem" onclick="event.stopPropagation()">
        <i class="bi bi-plus me-1"></i>Curso
      </a>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
