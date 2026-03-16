<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();

$db = Database::get();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: '.APP_URL.'/modules/academico/'); exit; }

$col = $db->query("SELECT * FROM acad_colegios WHERE id=$id")->fetch();
if (!$col) { header('Location: '.APP_URL.'/modules/academico/'); exit; }

$pageTitle  = $col['nombre'];
$activeMenu = 'academico';
$error = $success = '';

// Guardar curso
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='save_curso') {
    if (!Auth::csrfVerify($_POST['csrf']??'')) die('CSRF');
    try {
        $cid = (int)($_POST['curso_id'] ?? 0);
        $data = [
            'colegio_id'  => $id,
            'nombre'      => trim($_POST['nombre']),
            'grado'       => trim($_POST['grado'] ?? ''),
            'grupo'       => trim($_POST['grupo'] ?? ''),
            'nivel'       => $_POST['nivel'] ?? 'primaria',
            'anio'        => (int)($_POST['anio'] ?? date('Y')),
            'periodo'     => trim($_POST['periodo'] ?? ''),
            'docente'     => trim($_POST['docente'] ?? ''),
            'descripcion' => trim($_POST['descripcion'] ?? ''),
            'color'       => $_POST['color'] ?? '#185FA5',
            'activo'      => 1,
        ];
        if ($cid) {
            $sets = implode(',', array_map(function($k){ return "$k=:$k"; }, array_keys($data)));
            $data['id'] = $cid;
            $db->prepare("UPDATE acad_cursos SET $sets WHERE id=:id")->execute($data);
        } else {
            $cols2 = implode(',', array_keys($data));
            $vals2 = ':'.implode(',:', array_keys($data));
            $db->prepare("INSERT INTO acad_cursos ($cols2) VALUES ($vals2)")->execute($data);
        }
        $success = 'Curso guardado.';
    } catch (Exception $e) { $error = $e->getMessage(); }
}

// Guardar coordinador
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='save_coord') {
    if (!Auth::csrfVerify($_POST['csrf']??'')) die('CSRF');
    try {
        $data = [
            'colegio_id' => $id,
            'nombre'     => trim($_POST['nombre']),
            'email'      => trim($_POST['email'] ?? ''),
            'telefono'   => trim($_POST['telefono'] ?? ''),
            'cargo'      => trim($_POST['cargo'] ?? ''),
            'activo'     => 1,
        ];
        $cols2 = implode(',', array_keys($data));
        $vals2 = ':'.implode(',:', array_keys($data));
        $db->prepare("INSERT INTO acad_coordinadores ($cols2) VALUES ($vals2)")->execute($data);
        $success = 'Coordinador agregado.';
    } catch (Exception $e) { $error = $e->getMessage(); }
}

$cursos = $db->query("
    SELECT cur.*,
      (SELECT COUNT(*) FROM acad_estudiantes est WHERE est.curso_id=cur.id AND est.activo=1) AS num_est,
      (SELECT COUNT(*) FROM acad_unidades uni WHERE uni.curso_id=cur.id AND uni.activo=1) AS num_unidades
    FROM acad_cursos cur
    WHERE cur.colegio_id=$id AND cur.activo=1
    ORDER BY cur.nivel, cur.grado, cur.nombre
")->fetchAll();

$coordinadores = $db->query("SELECT * FROM acad_coordinadores WHERE colegio_id=$id AND activo=1")->fetchAll();

$editCurso = isset($_GET['edit_curso']) ? $db->query("SELECT * FROM acad_cursos WHERE id=".(int)$_GET['edit_curso']." AND colegio_id=$id")->fetch() : null;

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
.sc{background:#fff;border-radius:14px;border:1px solid #e2e8f0;padding:1rem 1.2rem;margin-bottom:1rem}
.curso-card{border:2px solid #e2e8f0;border-radius:12px;overflow:hidden;transition:.15s;background:#fff}
.curso-card:hover{box-shadow:0 3px 12px rgba(0,0,0,.08)}
.np{font-size:.68rem;padding:.15rem .5rem;border-radius:20px;font-weight:700}
.np-primaria{background:#dbeafe;color:#1e40af}
.np-secundaria{background:#fef9c3;color:#854d0e}
.np-media{background:#fee2e2;color:#991b1b}
.np-preescolar{background:#fae8ff;color:#7e22ce}
</style>

<div class="d-flex align-items-center gap-2 mb-3">
  <a href="index.php" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i></a>
  <div class="flex-grow-1">
    <h4 class="fw-bold mb-0"><?= htmlspecialchars($col['nombre']) ?></h4>
    <span class="text-muted small"><?= htmlspecialchars($col['ciudad'] ?? '') ?></span>
  </div>
  <a href="colegio_form.php?id=<?= $id ?>" class="btn btn-sm btn-outline-primary">
    <i class="bi bi-pencil me-1"></i>Editar
  </a>
</div>

<?php if ($error):   ?><div class="alert alert-danger  py-2 small"><?= $error   ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="row g-3">

  <!-- Izquierda -->
  <div class="col-lg-4">

    <!-- Info colegio -->
    <div class="sc">
      <?php if ($col['logo']): ?>
        <img src="<?= UPLOAD_URL.htmlspecialchars($col['logo']) ?>" class="img-fluid rounded mb-2" style="max-height:70px;object-fit:contain">
      <?php else: ?>
        <div style="font-size:2.5rem;text-align:center">&#x1F3EB;</div>
      <?php endif; ?>
      <h6 class="fw-bold text-center mb-2"><?= htmlspecialchars($col['nombre']) ?></h6>
      <?php if ($col['contacto']): ?><div class="text-muted small"><i class="bi bi-person me-1"></i><?= htmlspecialchars($col['contacto']) ?></div><?php endif; ?>
      <?php if ($col['email']): ?>   <div class="text-muted small"><i class="bi bi-envelope me-1"></i><?= htmlspecialchars($col['email']) ?></div><?php endif; ?>
      <?php if ($col['telefono']): ?><div class="text-muted small"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($col['telefono']) ?></div><?php endif; ?>
    </div>

    <!-- Stats -->
    <div class="sc">
      <div class="row g-2 text-center">
        <div class="col-4">
          <div class="fs-4 fw-bold text-primary"><?= count($cursos) ?></div>
          <div class="text-muted" style="font-size:.72rem">Cursos</div>
        </div>
        <div class="col-4">
          <div class="fs-4 fw-bold text-success"><?= array_sum(array_column($cursos,'num_est')) ?></div>
          <div class="text-muted" style="font-size:.72rem">Estudiantes</div>
        </div>
        <div class="col-4">
          <div class="fs-4 fw-bold text-warning"><?= count($coordinadores) ?></div>
          <div class="text-muted" style="font-size:.72rem">Coordinadores</div>
        </div>
      </div>
    </div>

    <!-- Coordinadores -->
    <div class="sc">
      <h6 class="fw-bold mb-2"><i class="bi bi-person-badge me-1 text-primary"></i>Coordinadores</h6>
      <?php foreach ($coordinadores as $coord): ?>
      <div class="d-flex align-items-center gap-2 py-2 border-bottom">
        <div style="width:32px;height:32px;background:#e0e7ff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0">&#x1F464;</div>
        <div class="flex-grow-1">
          <div class="fw-semibold" style="font-size:.82rem"><?= htmlspecialchars($coord['nombre']) ?></div>
          <div class="text-muted" style="font-size:.72rem"><?= htmlspecialchars($coord['cargo'] ?? '') ?></div>
        </div>
      </div>
      <?php endforeach; ?>
      <!-- Form nuevo coordinador -->
      <form method="POST" class="mt-2">
        <input type="hidden" name="action" value="save_coord">
        <input type="hidden" name="csrf"   value="<?= Auth::csrfToken() ?>">
        <div class="row g-1">
          <div class="col-12"><input type="text" name="nombre" class="form-control form-control-sm" placeholder="Nombre *" required></div>
          <div class="col-6"><input type="text" name="cargo" class="form-control form-control-sm" placeholder="Cargo"></div>
          <div class="col-6"><input type="text" name="telefono" class="form-control form-control-sm" placeholder="Tel&eacute;fono"></div>
          <div class="col-12"><input type="email" name="email" class="form-control form-control-sm" placeholder="Email"></div>
          <div class="col-12"><button type="submit" class="btn btn-outline-primary btn-sm w-100">+ Agregar coordinador</button></div>
        </div>
      </form>
    </div>

  </div>

  <!-- Derecha: cursos -->
  <div class="col-lg-8">

    <!-- Form curso -->
    <div class="sc mb-3" id="cursos">
      <h6 class="fw-bold mb-3"><i class="bi bi-journal-text me-2 text-primary"></i><?= $editCurso ? 'Editar Curso' : 'Nuevo Curso' ?></h6>
      <form method="POST">
        <input type="hidden" name="action" value="save_curso">
        <input type="hidden" name="csrf"   value="<?= Auth::csrfToken() ?>">
        <?php if ($editCurso): ?><input type="hidden" name="curso_id" value="<?= $editCurso['id'] ?>"><?php endif; ?>
        <div class="row g-2">
          <div class="col-md-5">
            <input type="text" name="nombre" class="form-control form-control-sm" required
                   placeholder="Nombre del curso *" value="<?= htmlspecialchars($editCurso['nombre'] ?? '') ?>">
          </div>
          <div class="col-md-2">
            <input type="text" name="grado" class="form-control form-control-sm" placeholder="Grado"
                   value="<?= htmlspecialchars($editCurso['grado'] ?? '') ?>">
          </div>
          <div class="col-md-2">
            <input type="text" name="grupo" class="form-control form-control-sm" placeholder="Grupo"
                   value="<?= htmlspecialchars($editCurso['grupo'] ?? '') ?>">
          </div>
          <div class="col-md-3">
            <select name="nivel" class="form-select form-select-sm">
              <?php foreach (['preescolar','primaria','secundaria','media'] as $nv): ?>
                <option value="<?= $nv ?>" <?= ($editCurso['nivel']??'primaria')===$nv?'selected':'' ?>><?= ucfirst($nv) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-2">
            <input type="number" name="anio" class="form-control form-control-sm" placeholder="Ano"
                   value="<?= $editCurso['anio'] ?? date('Y') ?>">
          </div>
          <div class="col-md-3">
            <input type="text" name="periodo" class="form-control form-control-sm" placeholder="Periodo (2025-1)"
                   value="<?= htmlspecialchars($editCurso['periodo'] ?? '') ?>">
          </div>
          <div class="col-md-4">
            <input type="text" name="docente" class="form-control form-control-sm" placeholder="Docente"
                   value="<?= htmlspecialchars($editCurso['docente'] ?? '') ?>">
          </div>
          <div class="col-md-3 d-flex align-items-center gap-2">
            <label class="form-label small mb-0">Color:</label>
            <input type="color" name="color" class="form-control form-control-sm form-control-color"
                   style="width:40px;height:32px" value="<?= $editCurso['color'] ?? '#185FA5' ?>">
          </div>
          <div class="col-12 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm">
              <i class="bi bi-save me-1"></i><?= $editCurso ? 'Guardar' : 'Crear Curso' ?>
            </button>
            <?php if ($editCurso): ?><a href="colegio.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm">Cancelar</a><?php endif; ?>
          </div>
        </div>
      </form>
    </div>

    <!-- Lista cursos -->
    <div class="sc">
      <h6 class="fw-bold mb-3"><i class="bi bi-collection me-2 text-primary"></i>Cursos (<?= count($cursos) ?>)</h6>
      <?php if (empty($cursos)): ?>
        <div class="text-center text-muted py-4"><i class="bi bi-journal-text fs-2 d-block mb-2"></i>No hay cursos. Crea el primero arriba.</div>
      <?php else: ?>
      <div class="row g-2">
      <?php foreach ($cursos as $cur): ?>
      <div class="col-md-6">
        <div class="curso-card" style="border-color:<?= $cur['color'] ?>40">
          <div class="p-1" style="background:<?= $cur['color'] ?>;height:5px"></div>
          <div class="p-3">
            <div class="d-flex align-items-start justify-content-between">
              <div>
                <div class="fw-bold"><?= htmlspecialchars($cur['nombre']) ?></div>
                <div class="d-flex gap-1 mt-1 flex-wrap">
                  <span class="np np-<?= $cur['nivel'] ?>"><?= ucfirst($cur['nivel']) ?></span>
                  <?php if ($cur['grado']): ?><span class="badge bg-light text-dark border" style="font-size:.65rem">Grado <?= htmlspecialchars($cur['grado']) ?></span><?php endif; ?>
                  <?php if ($cur['anio']): ?><span class="badge bg-light text-dark border" style="font-size:.65rem"><?= $cur['anio'] ?></span><?php endif; ?>
                </div>
                <?php if ($cur['docente']): ?><div class="text-muted mt-1" style="font-size:.75rem"><i class="bi bi-person me-1"></i><?= htmlspecialchars($cur['docente']) ?></div><?php endif; ?>
                <div class="d-flex gap-2 mt-2" style="font-size:.75rem">
                  <span class="text-primary"><i class="bi bi-people me-1"></i><?= $cur['num_est'] ?> est.</span>
                  <span class="text-success"><i class="bi bi-book me-1"></i><?= $cur['num_unidades'] ?> unidades</span>
                </div>
              </div>
              <div class="d-flex gap-1">
                <a href="curso.php?id=<?= $cur['id'] ?>" class="btn btn-sm btn-primary py-0 px-2" style="font-size:.72rem">
                  <i class="bi bi-arrow-right"></i>
                </a>
                <a href="colegio.php?id=<?= $id ?>&edit_curso=<?= $cur['id'] ?>#cursos"
                   class="btn btn-sm btn-outline-secondary py-0 px-1"><i class="bi bi-pencil"></i></a>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
