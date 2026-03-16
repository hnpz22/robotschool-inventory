<?php
// ============================================================
//  setup_admin.php &mdash; Crea o reinicia el usuario administrador
//  ROBOTSchool Inventory System
//
//  USO: http://localhost/robotschool_inventory/setup_admin.php
//  &#x26A0;&#xFE0F;  ELIMINA ESTE ARCHIVO después de usarlo.
// ============================================================

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';

$message = '';
$type    = '';
$done    = false;

// ── Seguridad: solo desde localhost ──────────────────────────
$ip = $_SERVER['REMOTE_ADDR'] ?? '';
if (!in_array($ip, ['127.0.0.1', '::1', 'localhost'])) {
    http_response_code(403);
    die('<h1>403 - Acceso denegado</h1><p>Este script solo puede ejecutarse desde localhost.</p>');
}

// ── Procesar formulario ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre   = trim($_POST['nombre']   ?? 'Administrador');
    $email    = trim($_POST['email']    ?? 'admin@robotschool.com');
    $password = trim($_POST['password'] ?? '');
    $password2= trim($_POST['password2']?? '');

    if (empty($password) || strlen($password) < 8) {
        $message = '&#x26A0;&#xFE0F; La contraseña debe tener al menos 8 caracteres.';
        $type    = 'warning';
    } elseif ($password !== $password2) {
        $message = '&#x26A0;&#xFE0F; Las contraseñas no coinciden.';
        $type    = 'warning';
    } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = '&#x26A0;&#xFE0F; Ingresa un email válido.';
        $type    = 'warning';
    } else {
        try {
            $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $db   = Database::get();

            // ¿Ya existe un usuario con ese email?
            $exist = $db->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
            $exist->execute([$email]);
            $row = $exist->fetch();

            if ($row) {
                // Actualizar
                $db->prepare("UPDATE usuarios SET nombre=?, password_hash=?, rol_id=1, activo=1 WHERE email=?")
                   ->execute([$nombre, $hash, $email]);
                $action = 'actualizado';
            } else {
                // Insertar nuevo admin
                $db->prepare("INSERT INTO usuarios (nombre, email, password_hash, rol_id, activo) VALUES (?,?,?,1,1)")
                   ->execute([$nombre, $email, $hash]);
                $action = 'creado';
            }

            // Verificar que el hash funciona
            $verify = password_verify($password, $hash);

            if ($verify) {
                $message = "&#x2705; Usuario admin <strong>$action</strong> correctamente.<br>
                            Hash verificado OK con PHP " . PHP_VERSION . ".<br>
                            <strong>Email:</strong> $email<br>
                            <strong>Ya puedes iniciar sesión.</strong><br><br>
                            <span class='text-danger fw-bold'>&#x26A0;&#xFE0F; ELIMINA este archivo (setup_admin.php) por seguridad.</span>";
                $type    = 'success';
                $done    = true;
            } else {
                $message = '&#x274C; Error interno: el hash no pudo verificarse. Intenta de nuevo.';
                $type    = 'danger';
            }

        } catch (Exception $e) {
            $message = '&#x274C; Error de base de datos: ' . htmlspecialchars($e->getMessage());
            $type    = 'danger';
        }
    }
}
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Setup Admin &mdash; ROBOTSchool Inventory</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body { background: linear-gradient(135deg, #1e2a3a 0%, #2d4a80 100%); min-height: 100vh; display: flex; align-items: center; }
    .card { border-radius: 20px; box-shadow: 0 20px 60px rgba(0,0,0,.35); }
    .php-info { font-size: .8rem; background: #f8f9fa; border-radius: 8px; padding: .75rem 1rem; }
  </style>
</head>
<body>
<div class="container" style="max-width: 480px;">
  <div class="card bg-white p-4 p-md-5">

    <div class="text-center mb-4">
      <span style="font-size:3rem;">🛠️</span>
      <h4 class="fw-bold mt-2 mb-0">Setup Administrador</h4>
      <p class="text-muted small">ROBOTSchool Inventory System</p>
    </div>

    <!-- Info del entorno -->
    <div class="php-info mb-4">
      <div class="d-flex justify-content-between">
        <span><i class="bi bi-code-slash me-1"></i>PHP</span>
        <strong><?= PHP_VERSION ?></strong>
      </div>
      <div class="d-flex justify-content-between">
        <span><i class="bi bi-database me-1"></i>Bcrypt disponible</span>
        <strong class="text-success"><?= function_exists('password_hash') ? '&#x2705; Sí' : '&#x274C; No' ?></strong>
      </div>
      <div class="d-flex justify-content-between">
        <span><i class="bi bi-gear me-1"></i>Cost bcrypt</span>
        <strong>12</strong>
      </div>
      <div class="d-flex justify-content-between">
        <span><i class="bi bi-hdd me-1"></i>BD configurada</span>
        <strong><?= DB_NAME ?> @ <?= DB_HOST ?></strong>
      </div>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?= $type ?> mb-4"><?= $message ?></div>
    <?php endif; ?>

    <?php if (!$done): ?>
    <form method="POST" autocomplete="off">
      <div class="mb-3">
        <label class="form-label fw-bold">Nombre del Administrador</label>
        <input type="text" name="nombre" class="form-control" value="Administrador ROBOTSchool" required>
      </div>
      <div class="mb-3">
        <label class="form-label fw-bold">Email de acceso</label>
        <input type="email" name="email" class="form-control" value="admin@robotschool.com" required>
        <div class="form-text">Si ya existe este email, se actualizará la contraseña.</div>
      </div>
      <div class="mb-3">
        <label class="form-label fw-bold">Nueva Contraseña</label>
        <div class="input-group">
          <input type="password" name="password" id="pwd1" class="form-control" placeholder="Mínimo 8 caracteres" required minlength="8">
          <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('pwd1','eye1')">
            <i class="bi bi-eye" id="eye1"></i>
          </button>
        </div>
      </div>
      <div class="mb-4">
        <label class="form-label fw-bold">Confirmar Contraseña</label>
        <div class="input-group">
          <input type="password" name="password2" id="pwd2" class="form-control" placeholder="Repite la contraseña" required minlength="8">
          <button type="button" class="btn btn-outline-secondary" onclick="togglePwd('pwd2','eye2')">
            <i class="bi bi-eye" id="eye2"></i>
          </button>
        </div>
      </div>
      <button type="submit" class="btn btn-primary w-100 fw-bold py-2">
        <i class="bi bi-shield-check me-2"></i>Crear / Actualizar Administrador
      </button>
    </form>

    <?php else: ?>
    <div class="text-center">
      <a href="<?= APP_URL ?>/login.php" class="btn btn-success btn-lg fw-bold w-100">
        <i class="bi bi-box-arrow-in-right me-2"></i>Ir al Login
      </a>
      <p class="text-muted small mt-3">
        <i class="bi bi-exclamation-triangle-fill text-warning me-1"></i>
        Recuerda eliminar <code>setup_admin.php</code> del servidor.
      </p>
    </div>
    <?php endif; ?>

  </div>
</div>
<script>
function togglePwd(id, eyeId) {
  const p = document.getElementById(id);
  const i = document.getElementById(eyeId);
  if (p.type === 'password') { p.type = 'text'; i.className = 'bi bi-eye-slash'; }
  else { p.type = 'password'; i.className = 'bi bi-eye'; }
}
// Verificar que las contraseñas coincidan en tiempo real
document.querySelector('[name=password2]')?.addEventListener('input', function() {
  const p1 = document.getElementById('pwd1').value;
  this.setCustomValidity(this.value !== p1 ? 'Las contraseñas no coinciden' : '');
});
</script>
</body>
</html>
