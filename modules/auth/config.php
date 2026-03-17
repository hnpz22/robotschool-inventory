<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';

// Solo Gerencia puede acceder a configuración del sistema
Auth::check();
if (!Auth::isGerencia()) {
    header('Location: ' . APP_URL . '/dashboard.php?err=sin_permiso'); exit;
}

$pageTitle  = 'Configuración del Sistema';
$activeMenu = 'config';

// ── Info base de datos ────────────────────────────────────────────────────────
$db = Database::get();
$dbVersion  = 'N/A';
$dbCharset  = 'N/A';
$tableCount = 0;
try {
    $dbVersion  = $db->query("SELECT VERSION()")->fetchColumn();
    $dbCharset  = $db->query("SELECT @@character_set_database")->fetchColumn();
    $tableCount = (int)$db->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
} catch (Exception $e) {}

// ── Info PHP ──────────────────────────────────────────────────────────────────
$phpVersion    = PHP_VERSION;
$phpExtensions = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'curl', 'fileinfo', 'openssl'];
$extStatus     = [];
foreach ($phpExtensions as $ext) {
    $extStatus[$ext] = extension_loaded($ext);
}

// ── Sesión activa ─────────────────────────────────────────────────────────────
$sessionAge     = time() - ($_SESSION['logged_at'] ?? time());
$sessionTimeout = defined('SESSION_TIMEOUT') ? SESSION_TIMEOUT : 3600;
$sessionLeft    = max(0, $sessionTimeout - $sessionAge);

// ── APIs configuradas ─────────────────────────────────────────────────────────
$apisConfig = [
    'Anthropic Claude API' => defined('ANTHROPIC_API_KEY'),
    'Microsoft OAuth'      => defined('MS_CLIENT_ID'),
    'WooCommerce'          => !empty(getenv('WOO_URL')),
];

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-gear me-2"></i>Configuración del Sistema</h4>
    <p class="text-muted small mb-0">Solo lectura — Información del entorno de ejecución</p>
  </div>
  <span class="badge" style="background:<?= APP_ENV === 'production' ? '#16a34a' : '#f59e0b' ?>;color:#fff;font-size:.8rem">
    <i class="bi bi-circle-fill me-1" style="font-size:.5rem"></i><?= strtoupper(APP_ENV) ?>
  </span>
</div>

<div class="row g-4">

  <!-- ── Aplicación ── -->
  <div class="col-lg-6">
    <div class="section-card h-100">
      <h6 class="fw-bold mb-3 pb-2" style="border-bottom:1px solid #e2e8f0">
        <i class="bi bi-app me-1 text-primary"></i>Aplicación
      </h6>
      <table class="table table-sm mb-0" style="font-size:.83rem">
        <tbody>
          <tr><td class="text-muted" style="width:45%">Nombre</td><td class="fw-semibold"><?= htmlspecialchars(APP_NAME) ?></td></tr>
          <tr><td class="text-muted">Versión</td><td><code><?= htmlspecialchars(APP_VERSION) ?></code></td></tr>
          <tr><td class="text-muted">Entorno</td>
              <td><span class="badge bg-<?= APP_ENV === 'production' ? 'success' : 'warning text-dark' ?>"><?= htmlspecialchars(APP_ENV) ?></span></td>
          </tr>
          <tr><td class="text-muted">URL Base</td><td><code style="font-size:.78rem"><?= htmlspecialchars(APP_URL) ?></code></td></tr>
          <tr><td class="text-muted">Zona Horaria</td><td><?= htmlspecialchars(date_default_timezone_get()) ?></td></tr>
          <tr><td class="text-muted">Fecha/Hora Servidor</td><td><?= date('d/m/Y H:i:s') ?></td></tr>
          <tr><td class="text-muted">Elementos por página</td><td><?= defined('ITEMS_PER_PAGE') ? ITEMS_PER_PAGE : 25 ?></td></tr>
          <tr><td class="text-muted">Timeout Sesión</td><td><?= $sessionTimeout / 60 ?> min</td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ── Base de datos ── -->
  <div class="col-lg-6">
    <div class="section-card h-100">
      <h6 class="fw-bold mb-3 pb-2" style="border-bottom:1px solid #e2e8f0">
        <i class="bi bi-database me-1 text-success"></i>Base de Datos
      </h6>
      <table class="table table-sm mb-0" style="font-size:.83rem">
        <tbody>
          <tr><td class="text-muted" style="width:45%">Host</td><td><code><?= htmlspecialchars(DB_HOST) ?></code></td></tr>
          <tr><td class="text-muted">Base de datos</td><td><code><?= htmlspecialchars(DB_NAME) ?></code></td></tr>
          <tr><td class="text-muted">Usuario</td><td><code><?= htmlspecialchars(DB_USER) ?></code></td></tr>
          <tr><td class="text-muted">Charset</td><td><code><?= htmlspecialchars(DB_CHARSET) ?></code></td></tr>
          <tr><td class="text-muted">Versión MySQL</td><td><code><?= htmlspecialchars($dbVersion) ?></code></td></tr>
          <tr><td class="text-muted">Charset BD</td><td><code><?= htmlspecialchars($dbCharset) ?></code></td></tr>
          <tr><td class="text-muted">Tablas</td><td><span class="badge bg-primary"><?= $tableCount ?></span></td></tr>
          <tr><td class="text-muted">Conexión</td><td><span class="badge bg-success">OK</span></td></tr>
        </tbody>
      </table>
    </div>
  </div>

  <!-- ── PHP ── -->
  <div class="col-lg-6">
    <div class="section-card h-100">
      <h6 class="fw-bold mb-3 pb-2" style="border-bottom:1px solid #e2e8f0">
        <i class="bi bi-filetype-php me-1 text-info"></i>PHP
      </h6>
      <table class="table table-sm mb-0" style="font-size:.83rem">
        <tbody>
          <tr><td class="text-muted" style="width:45%">Versión</td><td><code><?= htmlspecialchars($phpVersion) ?></code></td></tr>
          <tr><td class="text-muted">Límite memoria</td><td><?= ini_get('memory_limit') ?></td></tr>
          <tr><td class="text-muted">Tamaño máx. upload</td><td><?= ini_get('upload_max_filesize') ?></td></tr>
          <tr><td class="text-muted">POST máx.</td><td><?= ini_get('post_max_size') ?></td></tr>
          <tr><td class="text-muted">Tiempo máx. ejecución</td><td><?= ini_get('max_execution_time') ?>s</td></tr>
        </tbody>
      </table>
      <div class="mt-3">
        <div class="small text-muted fw-semibold mb-2">Extensiones requeridas:</div>
        <div class="d-flex flex-wrap gap-1">
          <?php foreach ($extStatus as $ext => $loaded): ?>
            <span class="badge bg-<?= $loaded ? 'success' : 'danger' ?>" style="font-size:.72rem">
              <i class="bi bi-<?= $loaded ? 'check' : 'x' ?> me-1"></i><?= $ext ?>
            </span>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- ── Sesión e Integraciones ── -->
  <div class="col-lg-6">
    <div class="row g-4 h-100">

      <!-- Sesión actual -->
      <div class="col-12">
        <div class="section-card">
          <h6 class="fw-bold mb-3 pb-2" style="border-bottom:1px solid #e2e8f0">
            <i class="bi bi-person-lock me-1 text-warning"></i>Sesión Actual
          </h6>
          <table class="table table-sm mb-0" style="font-size:.83rem">
            <tbody>
              <tr><td class="text-muted" style="width:45%">Usuario</td><td class="fw-semibold"><?= htmlspecialchars(Auth::user()['name']) ?></td></tr>
              <tr><td class="text-muted">Email</td><td><?= htmlspecialchars(Auth::user()['email']) ?></td></tr>
              <tr><td class="text-muted">Rol</td><td><span class="badge bg-dark"><?= htmlspecialchars(Auth::user()['rol_nombre']) ?></span></td></tr>
              <tr><td class="text-muted">Nombre sesión</td><td><code><?= htmlspecialchars(defined('SESSION_NAME') ? SESSION_NAME : 'PHPSESSID') ?></code></td></tr>
              <tr><td class="text-muted">Tiempo restante</td>
                  <td>
                    <span class="fw-semibold <?= $sessionLeft < 300 ? 'text-danger' : 'text-success' ?>">
                      <?= floor($sessionLeft / 60) ?>m <?= $sessionLeft % 60 ?>s
                    </span>
                  </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Integraciones -->
      <div class="col-12">
        <div class="section-card">
          <h6 class="fw-bold mb-3 pb-2" style="border-bottom:1px solid #e2e8f0">
            <i class="bi bi-plug me-1 text-purple"></i>Integraciones Externas
          </h6>
          <table class="table table-sm mb-0" style="font-size:.83rem">
            <tbody>
              <?php foreach ($apisConfig as $nombre => $activa): ?>
              <tr>
                <td class="text-muted" style="width:60%"><?= htmlspecialchars($nombre) ?></td>
                <td>
                  <span class="badge bg-<?= $activa ? 'success' : 'secondary' ?>" style="font-size:.72rem">
                    <i class="bi bi-<?= $activa ? 'check-circle' : 'dash-circle' ?> me-1"></i>
                    <?= $activa ? 'Configurada' : 'No configurada' ?>
                  </span>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

    </div>
  </div>

</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
