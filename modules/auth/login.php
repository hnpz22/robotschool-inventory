<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';

session_start();
if (!empty($_SESSION['user_id'])) {
    header('Location: '.APP_URL.'/dashboard.php'); exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (Auth::login(trim($_POST['email']??''), $_POST['password']??'')) {
        header('Location: '.APP_URL.'/dashboard.php'); exit;
    }
    $error = 'Email o contrasena incorrectos.';
}

$msClientId = defined('MS_CLIENT_ID') ? MS_CLIENT_ID : '';
$msRedirect  = APP_URL.'/modules/auth/ms_callback.php';
$msLoginUrl  = '';
if ($msClientId) {
    $state = bin2hex(random_bytes(16));
    $_SESSION['ms_state'] = $state;
    $msLoginUrl = 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize?'.http_build_query([
        'client_id'     => $msClientId,
        'response_type' => 'code',
        'redirect_uri'  => $msRedirect,
        'scope'         => 'openid profile email User.Read',
        'state'         => $state,
    ]);
}
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>ROBOTSchool — Ingresar</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
<style>
body{background:linear-gradient(135deg,#1e293b 0%,#0f172a 100%);min-height:100vh;display:flex;align-items:center;justify-content:center}
.login-card{background:#fff;border-radius:20px;padding:2rem;width:100%;max-width:380px;box-shadow:0 20px 60px rgba(0,0,0,.3)}
.logo-text{font-size:1.5rem;font-weight:900;color:#1e293b;letter-spacing:-.02em}
.logo-text span{color:#3b82f6}
.btn-ms{background:#0078D4;color:#fff;border:none;display:flex;align-items:center;gap:.6rem;justify-content:center;width:100%;padding:.6rem;border-radius:8px;font-size:.9rem;font-weight:600;cursor:pointer;transition:.15s;text-decoration:none}
.btn-ms:hover{background:#006CBD;color:#fff}
.divider{display:flex;align-items:center;gap:.75rem;color:#94a3b8;font-size:.78rem;margin:.75rem 0}
.divider::before,.divider::after{content:'';flex:1;height:1px;background:#e2e8f0}
</style>
</head>
<body>
<div class="login-card">
  <div class="text-center mb-4">
    <div class="logo-text">ROBOT<span>School</span></div>
    <div class="text-muted small mt-1">Sistema de Gestion Integral</div>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if ($msLoginUrl): ?>
  <!-- Login Microsoft 365 -->
  <a href="<?= htmlspecialchars($msLoginUrl) ?>" class="btn-ms mb-2">
    <svg width="18" height="18" viewBox="0 0 21 21" fill="none">
      <rect x="1" y="1" width="9" height="9" fill="#F25022"/>
      <rect x="11" y="1" width="9" height="9" fill="#7FBA00"/>
      <rect x="1" y="11" width="9" height="9" fill="#00A4EF"/>
      <rect x="11" y="11" width="9" height="9" fill="#FFB900"/>
    </svg>
    Ingresar con Microsoft 365
  </a>
  <div class="divider">o con email y contrasena</div>
  <?php endif; ?>

  <!-- Login email/password -->
  <form method="POST">
    <div class="mb-3">
      <label class="form-label small fw-semibold">Email</label>
      <input type="email" name="email" class="form-control" required
             value="<?= htmlspecialchars($_POST['email']??'') ?>"
             placeholder="tu@robotschool.com.co" autofocus>
    </div>
    <div class="mb-3">
      <label class="form-label small fw-semibold">Contrasena</label>
      <input type="password" name="password" class="form-control" required placeholder="••••••••">
    </div>
    <button type="submit" class="btn btn-primary w-100 fw-bold">
      <i class="bi bi-box-arrow-in-right me-2"></i>Ingresar
    </button>
  </form>

  <div class="text-center mt-3 text-muted" style="font-size:.72rem">
    ROBOTSchool Colombia &middot; v3.4
  </div>
</div>
</body>
</html>
