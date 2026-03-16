<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
Auth::check();

$db = Database::get();
$id = (int)($_GET['id'] ?? 0);
$col = $id ? $db->query("SELECT * FROM acad_colegios WHERE id=$id")->fetch() : null;
$pageTitle  = $col ? 'Editar Colegio' : 'Nuevo Colegio';
$activeMenu = 'academico';
$error = '';

if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (!Auth::csrfVerify($_POST['csrf']??'')) die('CSRF');
    try {
        $data = [
            'nombre'   => trim($_POST['nombre']),
            'ciudad'   => trim($_POST['ciudad']   ?? ''),
            'nit'      => trim($_POST['nit']       ?? '') ?: null,
            'contacto' => trim($_POST['contacto']  ?? ''),
            'email'    => trim($_POST['email']     ?? ''),
            'telefono' => trim($_POST['telefono']  ?? ''),
            'activo'   => 1,
        ];
        if ($id) {
            $sets = implode(',', array_map(function($k){ return "$k=:$k"; }, array_keys($data)));
            $data['id'] = $id;
            $db->prepare("UPDATE acad_colegios SET $sets WHERE id=:id")->execute($data);
        } else {
            $cols2 = implode(',', array_keys($data));
            $vals2 = ':'.implode(',:', array_keys($data));
            $db->prepare("INSERT INTO acad_colegios ($cols2) VALUES ($vals2)")->execute($data);
            $id = $db->lastInsertId();
        }
        header('Location: '.APP_URL.'/modules/academico/colegio.php?id='.$id.'&ok=1'); exit;
    } catch (Exception $e) { $error = $e->getMessage(); }
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="d-flex align-items-center gap-2 mb-3">
  <a href="index.php" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i></a>
  <h4 class="fw-bold mb-0"><?= $col ? 'Editar' : 'Nuevo' ?> Colegio</h4>
</div>
<?php if ($error): ?><div class="alert alert-danger py-2 small"><?= $error ?></div><?php endif; ?>
<div class="sc" style="background:#fff;border-radius:14px;border:1px solid #e2e8f0;padding:1.5rem;max-width:600px">
  <form method="POST">
    <input type="hidden" name="csrf" value="<?= Auth::csrfToken() ?>">
    <div class="row g-3">
      <div class="col-12"><label class="form-label small fw-semibold">Nombre *</label>
        <input type="text" name="nombre" class="form-control" required value="<?= htmlspecialchars($col['nombre'] ?? '') ?>"></div>
      <div class="col-md-6"><label class="form-label small fw-semibold">Ciudad</label>
        <input type="text" name="ciudad" class="form-control" value="<?= htmlspecialchars($col['ciudad'] ?? '') ?>"></div>
      <div class="col-md-6"><label class="form-label small fw-semibold">NIT</label>
        <input type="text" name="nit" class="form-control" value="<?= htmlspecialchars($col['nit'] ?? '') ?>"></div>
      <div class="col-md-6"><label class="form-label small fw-semibold">Contacto</label>
        <input type="text" name="contacto" class="form-control" value="<?= htmlspecialchars($col['contacto'] ?? '') ?>"></div>
      <div class="col-md-6"><label class="form-label small fw-semibold">Tel&eacute;fono</label>
        <input type="text" name="telefono" class="form-control" value="<?= htmlspecialchars($col['telefono'] ?? '') ?>"></div>
      <div class="col-12"><label class="form-label small fw-semibold">Email</label>
        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($col['email'] ?? '') ?>"></div>
      <div class="col-12 d-flex gap-2">
        <button type="submit" class="btn btn-primary fw-bold">
          <i class="bi bi-save me-1"></i><?= $col ? 'Guardar cambios' : 'Crear Colegio' ?>
        </button>
        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
      </div>
    </div>
  </form>
</div>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
