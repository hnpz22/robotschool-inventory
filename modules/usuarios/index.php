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

$rolMetaMap = [
    1 => ['color' => '#1e293b', 'icon' => 'bi-star-fill'],
    2 => ['color' => '#185FA5', 'icon' => 'bi-gear-fill'],
    3 => ['color' => '#0f766e', 'icon' => 'bi-mortarboard-fill'],
    4 => ['color' => '#b45309', 'icon' => 'bi-tools'],
    5 => ['color' => '#7c3aed', 'icon' => 'bi-briefcase-fill'],
    6 => ['color' => '#64748b', 'icon' => 'bi-eye-fill'],
];

// ── POST handlers ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::csrfVerify($_POST['csrf'] ?? '')) die('CSRF');
    $action = $_POST['action'] ?? '';

    // Resetear contraseña
    if ($action === 'reset_pwd') {
        $uid = (int)($_POST['id'] ?? 0);
        $pwd = trim($_POST['password'] ?? '');
        if (!$uid) {
            $error = 'Usuario inválido.';
        } elseif (strlen($pwd) < 6) {
            $error = 'La contraseña debe tener al menos 6 caracteres.';
        } else {
            $hash = password_hash($pwd, PASSWORD_BCRYPT, ['cost' => 12]);
            $db->prepare("UPDATE usuarios SET password_hash=? WHERE id=?")->execute([$hash, $uid]);
            auditoria('resetear_password', 'usuarios', $uid, [], ['reset_by' => Auth::user()['id']]);
            $success = 'Contraseña actualizada correctamente.';
        }

    // Crear / editar usuario
    } else {
        $uid    = (int)($_POST['id'] ?? 0);
        $nombre = trim($_POST['nombre'] ?? '');
        $email  = trim($_POST['email'] ?? '');
        $rolId  = (int)($_POST['rol_id'] ?? 2);
        $activo = isset($_POST['activo']) ? 1 : 0;
        try {
            if ($rolId === 1 && !Auth::isGerencia()) {
                throw new Exception('Solo Gerencia puede asignar el rol Gerencia.');
            }
            if ($uid) {
                $antes = $db->query("SELECT nombre,email,rol_id,activo FROM usuarios WHERE id=$uid")->fetch() ?: [];
                $data  = ['nombre'=>$nombre,'email'=>$email,'rol_id'=>$rolId,'activo'=>$activo,'id'=>$uid];
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
                auditoria('crear_usuario', 'usuarios', (int)$db->lastInsertId(), [], ['nombre'=>$nombre,'email'=>$email,'rol_id'=>$rolId]);
                $success = 'Usuario creado.';
            }
        } catch (Exception $e) { $error = $e->getMessage(); }
    }
}

// Desactivar usuario (GET)
if (isset($_GET['del']) && Auth::csrfVerify($_GET['csrf'] ?? '')) {
    $delId = (int)$_GET['del'];
    if ($delId !== Auth::user()['id']) {
        $antes = $db->query("SELECT nombre,email,rol_id,activo FROM usuarios WHERE id=$delId")->fetch() ?: [];
        $db->prepare("UPDATE usuarios SET activo=0 WHERE id=?")->execute([$delId]);
        auditoria('desactivar_usuario', 'usuarios', $delId, $antes, ['activo'=>0]);
        $success = 'Usuario desactivado.';
    } else { $error = 'No puedes desactivarte a ti mismo.'; }
}

$usuarios  = $db->query("SELECT u.*,r.nombre AS rol_nombre FROM usuarios u JOIN roles r ON r.id=u.rol_id ORDER BY u.activo DESC, u.id")->fetchAll();
$roles     = $db->query("SELECT * FROM roles ORDER BY id")->fetchAll();
$editUser  = null;
if (isset($_GET['edit'])) {
    $editUser = $db->query("SELECT * FROM usuarios WHERE id=".(int)$_GET['edit'])->fetch();
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h4 class="fw-bold mb-0">
      <i class="bi bi-people me-2" style="color:#185FA5"></i>Usuarios
    </h4>
    <div class="text-muted small"><?= count($usuarios) ?> usuario(s) en el sistema</div>
  </div>
  <a href="<?= APP_URL ?>/modules/usuarios/roles.php"
     class="btn btn-outline-primary btn-sm fw-semibold">
    <i class="bi bi-shield-lock me-1"></i>Roles y Permisos
  </a>
</div>

<?php if ($error):   ?><div class="alert alert-danger  py-2 small"><?= htmlspecialchars($error)   ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="row g-4">

  <!-- ── Tabla de usuarios ── -->
  <div class="col-lg-8">
    <div class="section-card p-0 overflow-hidden">
      <table class="table table-hover table-inv mb-0">
        <thead>
          <tr>
            <th>Usuario</th>
            <th>Rol</th>
            <th>Último Login</th>
            <th>Estado</th>
            <th class="text-end pe-3">Acciones</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($usuarios as $u):
          $rm = $rolMetaMap[$u['rol_id']] ?? ['color'=>'#64748b','icon'=>'bi-person'];
        ?>
        <tr class="<?= $u['activo'] ? '' : 'opacity-50' ?>">
          <td>
            <div class="fw-semibold" style="font-size:.85rem"><?= htmlspecialchars($u['nombre']) ?></div>
            <div class="text-muted" style="font-size:.72rem"><?= htmlspecialchars($u['email']) ?></div>
          </td>
          <td>
            <span class="badge d-inline-flex align-items-center gap-1"
                  style="background:<?= $rm['color'] ?>;font-size:.7rem">
              <i class="bi <?= $rm['icon'] ?>"></i>
              <?= htmlspecialchars($u['rol_nombre']) ?>
            </span>
          </td>
          <td class="text-muted small">
            <?= $u['ultimo_login'] ? date('d/m/Y H:i', strtotime($u['ultimo_login'])) : '<span class="text-muted">Nunca</span>' ?>
          </td>
          <td>
            <span class="badge bg-<?= $u['activo'] ? 'success' : 'secondary' ?>"
                  style="font-size:.7rem">
              <?= $u['activo'] ? 'Activo' : 'Inactivo' ?>
            </span>
          </td>
          <td class="text-end pe-2">
            <div class="d-flex justify-content-end gap-1">
              <!-- Editar -->
              <a href="?edit=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary"
                 title="Editar usuario" style="padding:.2rem .5rem">
                <i class="bi bi-pencil"></i>
              </a>
              <!-- Resetear contraseña -->
              <button type="button" class="btn btn-sm btn-outline-warning"
                      title="Resetear contraseña" style="padding:.2rem .5rem"
                      onclick="abrirResetPwd(<?= $u['id'] ?>, '<?= htmlspecialchars($u['nombre'], ENT_QUOTES) ?>')">
                <i class="bi bi-key"></i>
              </button>
              <!-- Desactivar (solo si no es el mismo usuario) -->
              <?php if ($u['id'] != Auth::user()['id']): ?>
              <a href="?del=<?= $u['id'] ?>&csrf=<?= Auth::csrfToken() ?>"
                 class="btn btn-sm btn-outline-danger" title="Desactivar usuario"
                 style="padding:.2rem .5rem"
                 onclick="return confirm('¿Desactivar a <?= htmlspecialchars($u['nombre'], ENT_QUOTES) ?>?')">
                <i class="bi bi-person-x"></i>
              </a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ── Formulario crear / editar ── -->
  <div class="col-lg-4">
    <div class="section-card">
      <h6 class="fw-bold mb-3 d-flex align-items-center gap-2">
        <i class="bi <?= $editUser ? 'bi-pencil-square' : 'bi-person-plus' ?> text-primary"></i>
        <?= $editUser ? 'Editar Usuario' : 'Nuevo Usuario' ?>
      </h6>
      <form method="POST">
        <input type="hidden" name="csrf" value="<?= Auth::csrfToken() ?>">
        <?php if ($editUser): ?>
        <input type="hidden" name="id" value="<?= $editUser['id'] ?>">
        <?php endif; ?>

        <div class="mb-3">
          <label class="form-label small fw-semibold">Nombre</label>
          <input type="text" name="nombre" class="form-control" required
                 value="<?= htmlspecialchars($editUser['nombre'] ?? '') ?>">
        </div>
        <div class="mb-3">
          <label class="form-label small fw-semibold">Email</label>
          <input type="email" name="email" class="form-control" required
                 value="<?= htmlspecialchars($editUser['email'] ?? '') ?>">
        </div>
        <?php if (!$editUser): ?>
        <div class="mb-3">
          <label class="form-label small fw-semibold">Contraseña *</label>
          <input type="password" name="password" class="form-control" required minlength="6">
        </div>
        <?php endif; ?>
        <div class="mb-3">
          <label class="form-label small fw-semibold">Rol</label>
          <select name="rol_id" class="form-select">
            <?php foreach ($roles as $r): ?>
            <option value="<?= $r['id'] ?>"
                    <?= ($editUser['rol_id'] ?? 2) == $r['id'] ? 'selected' : '' ?>>
              <?= htmlspecialchars(ucfirst($r['nombre'])) ?>
            </option>
            <?php endforeach; ?>
          </select>
          <?php if ($editUser): ?>
          <div class="form-text">
            Cambia el rol para modificar los accesos del usuario.
            <a href="<?= APP_URL ?>/modules/usuarios/roles.php" class="text-primary">Ver permisos por rol →</a>
          </div>
          <?php endif; ?>
        </div>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="activo" id="activo"
                 <?= ($editUser['activo'] ?? 1) ? 'checked' : '' ?>>
          <label class="form-check-label small" for="activo">Usuario activo</label>
        </div>
        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary btn-sm fw-bold">
            <i class="bi bi-check2 me-1"></i><?= $editUser ? 'Actualizar' : 'Crear usuario' ?>
          </button>
          <?php if ($editUser): ?>
          <a href="?" class="btn btn-outline-secondary btn-sm">Cancelar</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <!-- Info de roles -->
    <div class="section-card mt-3">
      <h6 class="fw-bold mb-3 d-flex align-items-center gap-2">
        <i class="bi bi-shield-lock text-primary"></i>Roles del sistema
      </h6>
      <?php foreach ($roles as $r):
        $rm = $rolMetaMap[$r['id']] ?? ['color'=>'#64748b','icon'=>'bi-person'];
        $cnt = array_sum(array_column(array_filter($usuarios, fn($u) => $u['rol_id'] == $r['id']), 'activo'));
      ?>
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="badge d-inline-flex align-items-center gap-1"
              style="background:<?= $rm['color'] ?>;font-size:.72rem">
          <i class="bi <?= $rm['icon'] ?>"></i>
          <?= htmlspecialchars(ucfirst($r['nombre'])) ?>
        </span>
        <span class="text-muted small"><?= $cnt ?> usuario(s) activo(s)</span>
      </div>
      <?php endforeach; ?>
      <a href="<?= APP_URL ?>/modules/usuarios/roles.php"
         class="btn btn-sm btn-outline-primary w-100 mt-2">
        <i class="bi bi-shield-lock me-1"></i>Gestionar Permisos
      </a>
    </div>
  </div>
</div>

<!-- ── Modal resetear contraseña ── -->
<div class="modal fade" id="modalResetPwd" tabindex="-1">
  <div class="modal-dialog modal-sm modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title fw-bold">
          <i class="bi bi-key me-2 text-warning"></i>Resetear Contraseña
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <div class="modal-body">
          <input type="hidden" name="action" value="reset_pwd">
          <input type="hidden" name="csrf"   value="<?= Auth::csrfToken() ?>">
          <input type="hidden" name="id"     id="rpwd-uid" value="">
          <p class="small text-muted mb-3">
            <i class="bi bi-person me-1"></i>
            Definiendo nueva contraseña para <strong id="rpwd-nombre"></strong>
          </p>
          <div class="mb-2">
            <label class="form-label small fw-semibold">Nueva contraseña</label>
            <input type="password" name="password" id="rpwd-input"
                   class="form-control" required minlength="6"
                   placeholder="Mínimo 6 caracteres" autocomplete="new-password">
          </div>
          <div class="form-text">
            <i class="bi bi-info-circle me-1"></i>
            El usuario deberá usar esta contraseña en su próximo inicio de sesión.
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-sm btn-warning fw-bold">
            <i class="bi bi-key me-1"></i>Actualizar contraseña
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function abrirResetPwd(uid, nombre) {
    document.getElementById('rpwd-uid').value = uid;
    document.getElementById('rpwd-nombre').textContent = nombre;
    document.getElementById('rpwd-input').value = '';
    new bootstrap.Modal(document.getElementById('modalResetPwd')).show();
}
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
