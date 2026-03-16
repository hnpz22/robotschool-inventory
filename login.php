<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';
require_once __DIR__ . '/includes/Auth.php';

Auth::start();
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . APP_URL . '/dashboard.php'); exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $res = Auth::login(trim($_POST['email'] ?? ''), $_POST['password'] ?? '');
    if ($res['ok']) {
        header('Location: ' . APP_URL . '/dashboard.php'); exit;
    }
    $error = $res['msg'];
}
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Iniciar Sesión — <?= APP_NAME ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="<?= APP_URL ?>/assets/css/app.css" rel="stylesheet">
  <style>
    body { background: linear-gradient(135deg, #1e2a3a 0%, #2d4a80 100%); min-height:100vh; display:flex; align-items:center; }
    .login-card { border-radius:20px; box-shadow:0 20px 60px rgba(0,0,0,.35); }
    .login-logo { max-height:80px; }
    .btn-login  { background:#ff6b00; border:none; font-weight:800; padding:.75rem; font-size:1rem; }
    .btn-login:hover { background:#e05e00; }
  </style>
</head>
<body>
<div class="container" style="max-width:420px;">
  <div class="login-card bg-white p-5">
    <div class="text-center mb-4">
      <img src="<?= APP_URL ?>/assets/img/logo_oficial.png" alt="ROBOTSchool" class="login-logo mb-3" style="max-height:120px;"
           onerror="this.outerHTML='<div class=\'fw-bold fs-4 text-primary\'>ROBOTSchool</div>'">
      <h5 class="fw-bold text-dark mb-0">Sistema de Inventario</h5>
      <p class="text-muted small mt-1">Inicia sesión para continuar</p>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <input type="hidden" name="csrf" value="<?= Auth::csrfToken() ?>">
      <div class="mb-3">
        <label class="form-label fw-bold">Correo electrónico</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-envelope"></i></span>
          <input type="email" name="email" class="form-control" placeholder="admin@robotschool.com"
                 value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
        </div>
      </div>
      <div class="mb-4">
        <label class="form-label fw-bold">Contraseña</label>
        <div class="input-group">
          <span class="input-group-text"><i class="bi bi-lock"></i></span>
          <input type="password" name="password" id="pwd" class="form-control" placeholder="••••••••" required>
          <button type="button" class="btn btn-outline-secondary" onclick="togglePwd()"><i class="bi bi-eye" id="eyeIcon"></i></button>
        </div>
      </div>
      <button type="submit" class="btn btn-login btn-primary text-white w-100 rounded-pill">
        <i class="bi bi-box-arrow-in-right me-2"></i>Ingresar
      </button>
    </form>
    <p class="text-center text-muted small mt-4 mb-0">
      <i class="bi bi-shield-lock me-1"></i>Acceso restringido — ROBOTSchool Colombia
    </p>
  </div>
</div>
<script>
function togglePwd() {
  const p = document.getElementById('pwd');
  const i = document.getElementById('eyeIcon');
  if (p.type==='password') { p.type='text'; i.className='bi bi-eye-slash'; }
  else { p.type='password'; i.className='bi bi-eye'; }
}
</script>
</body>
</html>
