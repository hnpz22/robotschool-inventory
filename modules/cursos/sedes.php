<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
Auth::check();
Auth::requireAdmin();

$db = Database::get();
$pageTitle  = 'Sedes';
$activeMenu = 'cursos';
$error = $success = '';

if ($_SERVER['REQUEST_METHOD']==='POST' && Auth::csrfVerify($_POST['csrf']??'')) {
    $sid = (int)($_POST['sede_id'] ?? 0);
    $data = [
        'nombre'      => trim($_POST['nombre']),
        'ciudad'      => $_POST['ciudad'] ?? 'bogota',
        'direccion'   => trim($_POST['direccion'] ?? ''),
        'telefono'    => trim($_POST['telefono']  ?? ''),
        'email'       => trim($_POST['email']     ?? ''),
        'responsable' => trim($_POST['responsable']?? ''),
        'color'       => $_POST['color'] ?? '#185FA5',
        'activo'      => 1,
    ];
    try {
        if ($sid) {
            $sets = implode(',', array_map(function($k){ return "$k=:$k"; }, array_keys($data)));
            $data['id'] = $sid;
            $db->prepare("UPDATE sedes SET $sets WHERE id=:id")->execute($data);
            $success = 'Sede actualizada.';
        } else {
            $c2 = implode(',', array_keys($data));
            $v2 = ':'.implode(',:', array_keys($data));
            $db->prepare("INSERT INTO sedes ($c2) VALUES ($v2)")->execute($data);
            $success = 'Sede creada.';
        }
    } catch (Exception $e) { $error = $e->getMessage(); }
}

if (isset($_GET['del']) && Auth::csrfVerify($_GET['csrf']??'')) {
    $db->prepare("UPDATE sedes SET activo=0 WHERE id=?")->execute([(int)$_GET['del']]);
    $success = 'Sede desactivada.';
}

$editSede = isset($_GET['edit']) ? $db->query("SELECT * FROM sedes WHERE id=".(int)$_GET['edit'])->fetch() : null;
$sedes    = $db->query("
    SELECT s.*,
      (SELECT COUNT(*) FROM escuela_horarios h WHERE h.sede_id=s.id AND h.activo=1) AS num_horarios
    FROM sedes s WHERE s.activo=1 ORDER BY s.ciudad, s.nombre
")->fetchAll();

$CIUDADES = ['bogota'=>'Bogota','cali'=>'Cali','medellin'=>'Medellin','otro'=>'Otra ciudad'];

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
.sc{background:#fff;border-radius:14px;border:1px solid #e2e8f0;padding:1rem 1.2rem;margin-bottom:1rem}
.sede-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;margin-bottom:.75rem}
.sede-bar{height:6px}
</style>

<div class="d-flex align-items-center gap-2 mb-3">
  <a href="horarios.php" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i></a>
  <h4 class="fw-bold mb-0"><i class="bi bi-geo-alt me-2"></i>Sedes ROBOTSchool</h4>
</div>

<?php if ($error):   ?><div class="alert alert-danger  py-2 small"><?= htmlspecialchars($error)   ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="row g-3">

  <!-- Lista sedes -->
  <div class="col-lg-7">
    <?php foreach (['bogota','cali','medellin','otro'] as $ciudad):
      $filtradas = array_filter($sedes, function($s) use ($ciudad){ return $s['ciudad']===$ciudad; });
      if (empty($filtradas)) continue;
    ?>
    <h6 class="fw-bold text-muted mb-2 mt-1"><?= $CIUDADES[$ciudad] ?></h6>
    <?php foreach ($filtradas as $s): ?>
    <div class="sede-card">
      <div class="sede-bar" style="background:<?= $s['color'] ?>"></div>
      <div class="p-3">
        <div class="d-flex align-items-start justify-content-between">
          <div>
            <div class="fw-bold fs-6"><?= htmlspecialchars($s['nombre']) ?></div>
            <?php if ($s['direccion']): ?>
              <div class="text-muted small"><i class="bi bi-geo me-1"></i><?= htmlspecialchars($s['direccion']) ?></div>
            <?php endif; ?>
            <?php if ($s['responsable']): ?>
              <div class="text-muted small"><i class="bi bi-person me-1"></i><?= htmlspecialchars($s['responsable']) ?></div>
            <?php endif; ?>
            <?php if ($s['telefono']): ?>
              <div class="text-muted small"><i class="bi bi-telephone me-1"></i><?= htmlspecialchars($s['telefono']) ?></div>
            <?php endif; ?>
            <div class="mt-1">
              <span class="badge bg-light text-dark border" style="font-size:.7rem">
                <i class="bi bi-clock me-1"></i><?= $s['num_horarios'] ?> horarios
              </span>
            </div>
          </div>
          <div class="d-flex gap-1">
            <div style="width:20px;height:20px;border-radius:4px;background:<?= $s['color'] ?>"></div>
            <a href="?edit=<?= $s['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-1"><i class="bi bi-pencil"></i></a>
            <a href="?del=<?= $s['id'] ?>&csrf=<?= Auth::csrfToken() ?>"
               class="btn btn-sm btn-outline-danger py-0 px-1"
               onclick="return confirm('Desactivar esta sede?')"><i class="bi bi-trash"></i></a>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endforeach; ?>
  </div>

  <!-- Formulario -->
  <div class="col-lg-5">
    <div class="sc" style="position:sticky;top:80px">
      <h6 class="fw-bold mb-3">
        <i class="bi bi-<?= $editSede?'pencil':'plus-circle' ?> me-2 text-primary"></i>
        <?= $editSede ? 'Editar Sede' : 'Nueva Sede' ?>
      </h6>
      <form method="POST">
        <input type="hidden" name="csrf" value="<?= Auth::csrfToken() ?>">
        <?php if ($editSede): ?><input type="hidden" name="sede_id" value="<?= $editSede['id'] ?>"><?php endif; ?>
        <div class="row g-2">
          <div class="col-12">
            <label class="form-label small fw-semibold">Nombre de la sede *</label>
            <input type="text" name="nombre" class="form-control form-control-sm" required
                   placeholder="Ej: ROBOTSchool Bogota Norte"
                   value="<?= htmlspecialchars($editSede['nombre'] ?? '') ?>">
          </div>
          <div class="col-6">
            <label class="form-label small fw-semibold">Ciudad</label>
            <select name="ciudad" class="form-select form-select-sm">
              <?php foreach ($CIUDADES as $ck=>$cv): ?>
                <option value="<?= $ck ?>" <?= ($editSede['ciudad']??'bogota')===$ck?'selected':'' ?>><?= $cv ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6 d-flex align-items-end gap-2">
            <div class="flex-grow-1">
              <label class="form-label small fw-semibold">Color</label>
              <input type="color" name="color" class="form-control form-control-color w-100" style="height:33px"
                     value="<?= $editSede['color'] ?? '#185FA5' ?>">
            </div>
          </div>
          <div class="col-12">
            <label class="form-label small fw-semibold">Direcci&oacute;n</label>
            <input type="text" name="direccion" class="form-control form-control-sm"
                   placeholder="Direcci&oacute;n completa"
                   value="<?= htmlspecialchars($editSede['direccion'] ?? '') ?>">
          </div>
          <div class="col-6">
            <label class="form-label small fw-semibold">Tel&eacute;fono</label>
            <input type="text" name="telefono" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($editSede['telefono'] ?? '') ?>">
          </div>
          <div class="col-6">
            <label class="form-label small fw-semibold">Email</label>
            <input type="email" name="email" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($editSede['email'] ?? '') ?>">
          </div>
          <div class="col-12">
            <label class="form-label small fw-semibold">Responsable</label>
            <input type="text" name="responsable" class="form-control form-control-sm"
                   placeholder="Nombre del coordinador de sede"
                   value="<?= htmlspecialchars($editSede['responsable'] ?? '') ?>">
          </div>
          <div class="col-12 d-flex gap-2 mt-1">
            <button type="submit" class="btn btn-primary btn-sm fw-bold flex-grow-1">
              <i class="bi bi-save me-1"></i><?= $editSede ? 'Guardar cambios' : 'Crear Sede' ?>
            </button>
            <?php if ($editSede): ?>
              <a href="sedes.php" class="btn btn-outline-secondary btn-sm">Cancelar</a>
            <?php endif; ?>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
