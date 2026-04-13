<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();

$db         = Database::get();
$pageTitle  = 'Matriculas Escuela';
$activeMenu = 'matriculas';

// Stats generales
$stats = $db->query("
    SELECT
      (SELECT COUNT(*) FROM matriculas WHERE estado='activa') AS activas,
      (SELECT COUNT(*) FROM matriculas WHERE estado='pendiente_pago') AS pendientes,
      (SELECT COUNT(*) FROM estudiantes WHERE activo=1) AS estudiantes,
      (SELECT COALESCE(SUM(valor_pagado),0) FROM pagos
       WHERE estado='pagado' AND YEARWEEK(fecha_pago)=YEARWEEK(CURDATE())) AS recaudo_semana
")->fetch();

// Grupos con cupos (vista)
$grupos = [];
try {
    $grupos = $db->query("
        SELECT * FROM v_grupos_cupos
        ORDER BY dia_semana, hora_inicio, nombre
    ")->fetchAll();
} catch (Exception $e) {
    // Vista puede no existir aún
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
/* Estilos específicos matrículas — reutilizar sistema cuando es posible */
.grupo-card{background:#fff;border:1px solid var(--rs-gray-200);border-radius:var(--rs-radius);overflow:hidden;margin-bottom:.75rem}
.disp-disponible {background:#dcfce7;color:#166534}
.disp-casi_lleno  {background:#fef9c3;color:#854d0e}
.disp-lleno       {background:#fee2e2;color:#991b1b}
.cupo-bar{height:6px;border-radius:3px;background:var(--rs-gray-200);overflow:hidden;margin-top:4px}
.cupo-fill{height:100%;border-radius:3px;transition:width .3s}
</style>

<div class="page-header">
  <div>
    <h4 class="page-header-title">&#x1F393; Escuela ROBOTSchool</h4>
    <p class="page-header-sub">Cursos libres y semestral de rob&oacute;tica</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="grupos.php" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-people me-1"></i>Grupos
    </a>
    <a href="estudiantes.php" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-person-plus me-1"></i>Estudiantes
    </a>
    <a href="nueva_matricula.php" class="btn btn-success btn-sm fw-bold">
      <i class="bi bi-plus-lg me-1"></i>Nueva Matr&iacute;cula
    </a>
  </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="icon-box bg-success bg-opacity-10 text-success"><i class="bi bi-check-circle"></i></div>
        <div>
          <div class="dashboard-stat-num text-success"><?= $stats['activas'] ?></div>
          <div class="dashboard-stat-lbl">Matr&iacute;culas activas</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card stat-card<?= $stats['pendientes']>0 ? ' stat-warning' : '' ?> h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="icon-box bg-warning bg-opacity-10 text-warning"><i class="bi bi-hourglass-split"></i></div>
        <div>
          <div class="dashboard-stat-num text-warning"><?= $stats['pendientes'] ?></div>
          <div class="dashboard-stat-lbl">Pendientes de pago</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="icon-box bg-primary bg-opacity-10 text-primary"><i class="bi bi-people"></i></div>
        <div>
          <div class="dashboard-stat-num text-primary"><?= $stats['estudiantes'] ?></div>
          <div class="dashboard-stat-lbl">Estudiantes registrados</div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="card stat-card h-100">
      <div class="card-body d-flex align-items-center gap-3">
        <div class="icon-box bg-success bg-opacity-10 text-success"><i class="bi bi-cash-coin"></i></div>
        <div>
          <div class="dashboard-stat-num text-success">$<?= number_format($stats['recaudo_semana'],0,',','.') ?></div>
          <div class="dashboard-stat-lbl">Recaudo esta semana</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Grupos disponibles con cupos -->
<div class="section-card">
  <div class="section-card-header">
    <h6 class="section-card-title"><i class="bi bi-grid me-2 text-primary"></i>Grupos y Disponibilidad de Cupos</h6>
    <a href="grupos.php" class="btn btn-outline-primary btn-sm">Ver todos</a>
  </div>

  <?php if (empty($grupos)): ?>
    <div class="text-center text-muted py-4">
      <i class="bi bi-people fs-2 d-block mb-2"></i>
      No hay grupos configurados.
      <br>
      <a href="grupos.php?nuevo=1" class="btn btn-primary btn-sm mt-2">Crear primer grupo</a>
    </div>
  <?php else: ?>
  <div class="row g-3">
  <?php foreach ($grupos as $g):
    $pct = $g['stock_disponible'] > 0
        ? min(100, round($g['matriculas_activas'] / $g['stock_disponible'] * 100))
        : 100;
    $fillColor = ['disponible'=>'#22c55e','casi_lleno'=>'#f59e0b','lleno'=>'#ef4444'][$g['disponibilidad']] ?? '#94a3b8';
    $dias = ['1'=>'Dom','2'=>'Lun','3'=>'Mar','4'=>'Mie','5'=>'Jue','6'=>'Vie','7'=>'Sab'];
    $dia = $dias[$g['dia_semana']] ?? 'Sab';
  ?>
  <div class="col-md-6 col-lg-4">
    <div class="grupo-card">
      <div class="p-3 pb-2">
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <div class="fw-bold"><?= htmlspecialchars($g['nombre']) ?></div>
            <div class="text-muted small"><?= htmlspecialchars($g['programa_nombre']) ?></div>
          </div>
          <span class="badge disp-<?= $g['disponibilidad'] ?>" style="font-size:.7rem">
            <?= ['disponible'=>'Disponible','casi_lleno'=>'Casi lleno','lleno'=>'Lleno'][$g['disponibilidad']] ?>
          </span>
        </div>

        <!-- Horario -->
        <div class="d-flex gap-2 mt-2" style="font-size:.78rem;color:#475569">
          <span><i class="bi bi-calendar me-1"></i><?= $dia ?></span>
          <span><i class="bi bi-clock me-1"></i><?= substr($g['hora_inicio'],0,5) ?> - <?= substr($g['hora_fin'],0,5) ?></span>
          <?php if ($g['docente']): ?>
            <span><i class="bi bi-person me-1"></i><?= htmlspecialchars($g['docente']) ?></span>
          <?php endif; ?>
        </div>

        <!-- Kit/Elemento vinculado -->
        <?php if ($g['elemento_nombre'] || $g['kit_nombre']): ?>
        <div class="mt-2 p-2 rounded" style="background:#f0f4ff;font-size:.77rem">
          <i class="bi bi-box-seam me-1 text-primary"></i>
          <strong><?= htmlspecialchars($g['elemento_nombre'] ?: $g['kit_nombre']) ?></strong>
          <span class="text-muted ms-1"><?= htmlspecialchars($g['elemento_codigo'] ?: $g['kit_codigo']) ?></span>
        </div>
        <?php endif; ?>

        <!-- Barra de cupos -->
        <div class="mt-2">
          <div class="d-flex justify-content-between" style="font-size:.75rem">
            <span class="text-muted">Cupos</span>
            <span class="fw-bold">
              <?= $g['matriculas_activas'] ?> / <?= $g['stock_disponible'] ?? $g['cupo_max'] ?>
              <span class="text-muted fw-normal">(<?= $g['cupos_libres'] ?> libres)</span>
            </span>
          </div>
          <div class="cupo-bar">
            <div class="cupo-fill" style="width:<?= $pct ?>%;background:<?= $fillColor ?>"></div>
          </div>
        </div>
      </div>

      <!-- Acciones -->
      <div class="px-3 pb-3 d-flex gap-2">
        <a href="nueva_matricula.php?grupo_id=<?= $g['id'] ?>"
           class="btn btn-success btn-sm flex-grow-1 <?= $g['disponibilidad']==='lleno'?'disabled':'' ?>">
          <i class="bi bi-plus-lg me-1"></i>Matricular
        </a>
        <a href="grupo.php?id=<?= $g['id'] ?>"
           class="btn btn-outline-primary btn-sm">
          <i class="bi bi-people"></i>
        </a>
        <a href="pagos.php?grupo_id=<?= $g['id'] ?>"
           class="btn btn-outline-warning btn-sm">
          <i class="bi bi-cash"></i>
        </a>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Accesos rapidos -->
<div class="row g-3">
  <div class="col-md-4">
    <a href="pagos.php" class="section-card d-block text-decoration-none text-dark">
      <div class="d-flex align-items-center gap-3">
        <div style="font-size:2rem">&#x1F4B0;</div>
        <div>
          <div class="fw-bold">Registro de Pagos</div>
          <div class="text-muted small">Ver y registrar pagos de matr&iacute;culas</div>
        </div>
        <i class="bi bi-chevron-right ms-auto text-muted"></i>
      </div>
    </a>
  </div>
  <div class="col-md-4">
    <a href="calendario.php" class="section-card d-block text-decoration-none text-dark">
      <div class="d-flex align-items-center gap-3">
        <div style="font-size:2rem">&#x1F4C5;</div>
        <div>
          <div class="fw-bold">Calendario S&aacute;bados</div>
          <div class="text-muted small">Resumen de pagos y asistencia por semana</div>
        </div>
        <i class="bi bi-chevron-right ms-auto text-muted"></i>
      </div>
    </a>
  </div>
  <div class="col-md-4">
    <a href="estudiantes.php" class="section-card d-block text-decoration-none text-dark">
      <div class="d-flex align-items-center gap-3">
        <div style="font-size:2rem">&#x1F9D1;</div>
        <div>
          <div class="fw-bold">Estudiantes</div>
          <div class="text-muted small">Base de datos de ni&ntilde;os inscritos</div>
        </div>
        <i class="bi bi-chevron-right ms-auto text-muted"></i>
      </div>
    </a>
  </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
