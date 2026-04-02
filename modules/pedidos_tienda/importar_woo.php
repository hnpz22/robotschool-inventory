<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/WooSync.php';

Auth::requireRol('gerencia', 'administracion');

$db         = Database::get();
$pageTitle  = 'Importar desde WooCommerce';
$activeMenu = 'pedidos_tienda';

$woo    = new WooSync($db);
$result = null;
$error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && Auth::csrfVerify($_POST['csrf'] ?? '')) {
    if (!$woo->isConfigured()) {
        $error = 'WooCommerce no está configurado. Define las variables WOO_* en el archivo .env y reinicia el contenedor.';
    } else {
        $result = $woo->importarHistorico(20);
    }
}

// Últimos 50 registros del log de webhooks
try {
    $logs = $db->query(
        "SELECT * FROM woo_webhook_log ORDER BY received_at DESC LIMIT 50"
    )->fetchAll();
} catch (Exception $e) {
    $logs = [];
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h4 class="fw-bold mb-0">&#x1F4E6; Importar desde WooCommerce</h4>
    <p class="text-muted small mb-0">Integración bidireccional — pedidos en estado <em>processing</em></p>
  </div>
  <a href="index.php" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-arrow-left me-1"></i>Volver a Pedidos
  </a>
</div>

<?php if (!$woo->isConfigured()): ?>
<div class="alert alert-warning d-flex align-items-start gap-2 mb-3">
  <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0 mt-1"></i>
  <div>
    <strong>WooCommerce no configurado.</strong><br>
    Define las variables <code>WOO_URL</code>, <code>WOO_CONSUMER_KEY</code> y
    <code>WOO_CONSUMER_SECRET</code> en el archivo <code>.env</code> y reinicia el contenedor.
    Consulta <code>.env.example</code> para ver la estructura requerida.
  </div>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if ($result !== null): ?>
<div class="alert alert-<?= $result['errores'] > 0 ? 'warning' : 'success' ?> py-2 mb-3">
  <strong>Importación completada:</strong>
  <strong class="text-success"><?= $result['importados'] ?></strong> importados &bull;
  <strong class="text-secondary"><?= $result['duplicados'] ?></strong> duplicados (ya existían) &bull;
  <strong class="text-danger"><?= $result['errores'] ?></strong> errores
  <?php if (!empty($result['statuses_vistos'])): ?>
  <div class="mt-2 small"><strong>Statuses en WooCommerce:</strong>
    <?php foreach ($result['statuses_vistos'] as $st => $cnt): ?>
      <code><?= htmlspecialchars($st) ?></code> (<?= $cnt ?>)&nbsp;
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <?php if (!empty($result['detalle'])): ?>
  <ul class="mb-0 mt-2 small">
    <?php foreach ($result['detalle'] as $d): ?>
    <li><?= htmlspecialchars($d) ?></li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>
</div>
<?php endif; ?>

<!-- ── Importar histórico ── -->
<div class="card border-0 shadow-sm mb-4">
  <div class="card-header bg-white border-bottom d-flex align-items-center gap-2 py-3">
    <i class="bi bi-cloud-download text-primary fs-5"></i>
    <h6 class="mb-0 fw-bold">Importar pedidos históricos de WooCommerce</h6>
  </div>
  <div class="card-body">
    <p class="text-muted small mb-3">
      Trae pedidos en estado <strong>Procesando, Completado, Recibido, Entregado y Enviado</strong>
      desde WooCommerce mediante la API REST. Se importan hasta
      <strong>2 000 pedidos</strong> (20&nbsp;páginas &times;&nbsp;100).
      Los pedidos cuyo <code>woo_order_id</code> ya existe se omiten automáticamente.<br>
      <span class="text-muted">Mapeo: <code>processing</code> → Pendiente &bull;
      <code>completed / recibido / entregado</code> → Entregado &bull;
      <code>enviado</code> → Despachado</span>
    </p>
    <form method="POST">
      <input type="hidden" name="csrf" value="<?= Auth::csrfToken() ?>">
      <button type="submit" class="btn btn-primary" <?= !$woo->isConfigured() ? 'disabled' : '' ?>>
        <i class="bi bi-cloud-download me-1"></i>Importar pedidos históricos
      </button>
    </form>
  </div>
</div>

<!-- ── Log de webhooks ── -->
<div class="card border-0 shadow-sm">
  <div class="card-header bg-white border-bottom d-flex align-items-center justify-content-between py-3">
    <div class="d-flex align-items-center gap-2">
      <i class="bi bi-journal-text text-secondary fs-5"></i>
      <h6 class="mb-0 fw-bold">Log de webhooks recibidos</h6>
    </div>
    <span class="text-muted small">Últimos 50 registros</span>
  </div>
  <?php if (empty($logs)): ?>
  <div class="card-body text-center text-muted py-5">
    <i class="bi bi-inbox fs-2 d-block mb-2 opacity-50"></i>
    <p class="mb-0 small">No hay registros de webhooks todavía.</p>
  </div>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-sm table-hover mb-0" style="font-size:.8rem">
      <thead class="table-dark">
        <tr>
          <th>Fecha recibido</th>
          <th>Order ID</th>
          <th>Evento</th>
          <th>Estado WC</th>
          <th>Resultado</th>
          <th>Detalle</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $badgeMap = [
            'ok'        => 'success',
            'duplicado' => 'secondary',
            'ignorado'  => 'warning',
            'error'     => 'danger',
        ];
        foreach ($logs as $log):
            $res = $log['resultado'] ?? 'error';
        ?>
        <tr>
          <td class="text-nowrap text-muted"><?= htmlspecialchars($log['received_at']) ?></td>
          <td><?= htmlspecialchars($log['woo_order_id'] ?? '—') ?></td>
          <td class="text-muted"><?= htmlspecialchars($log['evento'] ?? '—') ?></td>
          <td><?= htmlspecialchars($log['status_code'] ?? '—') ?></td>
          <td>
            <span class="badge bg-<?= $badgeMap[$res] ?? 'secondary' ?>">
              <?= htmlspecialchars($res) ?>
            </span>
          </td>
          <td class="text-muted" style="max-width:300px;word-break:break-word">
            <?= htmlspecialchars($log['detalle'] ?? '') ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
