<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::requireRol('gerencia');

$db = Database::get();
$pageTitle  = 'Usuarios';
$activeMenu = 'usuarios';
$error = $success = '';

// Guardar usuario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::csrfVerify($_POST['csrf'] ?? '')) die('CSRF');
    $uid = (int)($_POST['id'] ?? 0);
    $nombre = trim($_POST['nombre']);
    $email  = trim($_POST['email']);
    $rolId  = (int)$_POST['rol_id'];
    $activo = isset($_POST['activo']) ? 1 : 0;
    try {
        if ($rolId === 1 && !Auth::isGerencia()) {
            throw new Exception('Solo Gerencia puede asignar el rol Gerencia.');
        }
        if ($uid) {
            $antes = $db->query("SELECT nombre,email,rol_id,activo FROM usuarios WHERE id=$uid")->fetch() ?: [];
            $data = ['nombre'=>$nombre,'email'=>$email,'rol_id'=>$rolId,'activo'=>$activo,'id'=>$uid];
            if (!empty($_POST['password'])) {
                $data['password_hash'] = password_hash($_POST['password'], PASSWORD_BCRYPT, ['cost'=>12]);
                $db->prepare("UPDATE usuarios SET nombre=:nombre,email=:email,rol_id=:rol_id,activo=:activo,password_hash=:password_hash WHERE id=:id")->execute($data);
            } else {
                $db->prepare("UPDATE usuarios SET nombre=:nombre,email=:email,rol_id=:rol_id,activo=:activo WHERE id=:id")->execute($data);
            }
            auditoria('editar_usuario', 'usuarios', $uid, $antes, ['nombre'=>$nombre,'email'=>$email,'rol_id'=>$rolId,'activo'=>$activo]);
            $success = 'Usuario actualizado.';
        } else {
            if (empty($_POST['password'])) throw new Exception('La contraseña es requerida para nuevos usuarios.');
            $db->prepare("INSERT INTO usuarios (nombre,email,password_hash,rol_id,activo) VALUES (?,?,?,?,?)")
               ->execute([$nombre,$email,password_hash($_POST['password'],PASSWORD_BCRYPT,['cost'=>12]),$rolId,$activo]);
            auditoria('crear_usuario', 'usuarios', (int)$db->lastInsertId(), [], ['nombre'=>$nombre,'email'=>$email,'rol_id'=>$rolId,'activo'=>$activo]);
            $success = 'Usuario creado.';
        }
    } catch (Exception $e) { $error = $e->getMessage(); }
}

// Eliminar
if (isset($_GET['del']) && Auth::csrfVerify($_GET['csrf']??'')) {
    $delId = (int)$_GET['del'];
    if ($delId !== Auth::user()['id']) {
        $antes = $db->query("SELECT nombre,email,rol_id,activo FROM usuarios WHERE id=$delId")->fetch() ?: [];
        $db->prepare("UPDATE usuarios SET activo=0 WHERE id=?")->execute([$delId]);
        auditoria('desactivar_usuario', 'usuarios', $delId, $antes, ['activo'=>0]);
        $success = 'Usuario desactivado.';
    } else { $error = 'No puedes desactivarte a ti mismo.'; }
}

$usuarios = $db->query("SELECT u.*,r.nombre AS rol_nombre FROM usuarios u JOIN roles r ON r.id=u.rol_id ORDER BY u.id")->fetchAll();
$roles = Auth::isGerencia()
    ? $db->query("SELECT * FROM roles ORDER BY id")->fetchAll()
    : $db->query("SELECT * FROM roles WHERE id != 1 ORDER BY id")->fetchAll();

// Cargar para editar
$editUser = null;
if (isset($_GET['edit'])) {
    $editUser = $db->query("SELECT * FROM usuarios WHERE id=".(int)$_GET['edit'])->fetch();
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<h4 class="fw-bold mb-4">Administración de Usuarios</h4>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

<div class="row g-4">
  <div class="col-lg-8">
    <div class="section-card">
      <table class="table table-hover table-inv mb-0">
        <thead><tr>
          <th>Nombre</th><th>Email</th><th>Rol</th><th>Último Login</th><th>Estado</th><th>Acciones</th>
        </tr></thead>
        <tbody>
        <?php foreach ($usuarios as $u): ?>
        <tr>
          <td class="fw-semibold"><?= htmlspecialchars($u['nombre']) ?></td>
          <td class="small"><?= htmlspecialchars($u['email']) ?></td>
          <td><span class="badge bg-primary"><?= htmlspecialchars($u['rol_nombre']) ?></span></td>
          <td class="text-muted small"><?= $u['ultimo_login'] ? date('d/m/Y H:i',strtotime($u['ultimo_login'])) : 'Nunca' ?></td>
          <td><span class="badge bg-<?= $u['activo']?'success':'secondary' ?>"><?= $u['activo']?'Activo':'Inactivo' ?></span></td>
          <td>
            <a href="?edit=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
            <?php if ($u['id'] != Auth::user()['id']): ?>
            <a href="?del=<?= $u['id'] ?>&csrf=<?= Auth::csrfToken() ?>" class="btn btn-sm btn-outline-danger" data-confirm="¿Desactivar este usuario?"><i class="bi bi-person-x"></i></a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="col-lg-4">
    <div class="section-card">
      <h6 class="fw-bold mb-3"><?= $editUser ? 'Editar Usuario' : 'Nuevo Usuario' ?></h6>
      <form method="POST">
        <input type="hidden" name="csrf" value="<?= Auth::csrfToken() ?>">
        <?php if ($editUser): ?><input type="hidden" name="id" value="<?= $editUser['id'] ?>"><?php endif; ?>
        <div class="mb-3">
          <label class="form-label">Nombre</label>
          <input type="text" name="nombre" class="form-control" required value="<?= htmlspecialchars($editUser['nombre'] ?? '') ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($editUser['email'] ?? '') ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Contraseña <?= $editUser ? '(dejar vacío para no cambiar)' : '*' ?></label>
          <input type="password" name="password" class="form-control" <?= $editUser?'':'required' ?>>
        </div>
        <div class="mb-3">
          <label class="form-label">Rol</label>
          <select name="rol_id" class="form-select">
            <?php foreach ($roles as $r): ?>
              <option value="<?= $r['id'] ?>" <?= ($editUser['rol_id']??2)==$r['id']?'selected':'' ?>><?= htmlspecialchars($r['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="activo" id="activo" <?= ($editUser['activo']??1)?'checked':'' ?>>
          <label class="form-check-label" for="activo">Usuario activo</label>
        </div>
        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary"><?= $editUser ? 'Actualizar' : 'Crear' ?></button>
          <?php if ($editUser): ?><a href="?" class="btn btn-outline-secondary">Cancelar</a><?php endif; ?>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
