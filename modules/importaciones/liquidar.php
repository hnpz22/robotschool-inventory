<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();

$db  = Database::get();
$id  = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . APP_URL . '/modules/importaciones/'); exit; }

$ped   = $db->query("SELECT * FROM pedidos_importacion WHERE id=$id")->fetch();
$items = $db->query("SELECT pi.*, e.nombre, e.codigo, e.peso_gramos FROM pedido_items pi JOIN elementos e ON e.id=pi.elemento_id WHERE pi.pedido_id=$id")->fetchAll();

$pageTitle  = 'Liquidar Pedido ' . $ped['codigo_pedido'];
$activeMenu = 'pedidos';
$error = $success = '';

// ── Calcular simulación ──
$trm            = (float)$ped['tasa_cambio_usd_cop'];
$dhlCOP         = (float)$ped['costo_dhl_usd'] * $trm;
$arancelPct     = (float)$ped['arancel_pct'] / 100;
$ivaPct         = (float)$ped['iva_pct'] / 100;
$otrosCOP       = (float)$ped['otros_impuestos_cop'];
$fobUSD         = (float)$ped['valor_fob_usd'];
$segUSD         = (float)$ped['valor_seguro_usd'];
$cifUSD         = $fobUSD + (float)$ped['costo_dhl_usd'] + $segUSD;
$cifCOP         = $cifUSD * $trm;
$arancelCOP     = $cifCOP * $arancelPct;
$ivaCOP         = ($cifCOP + $arancelCOP) * $ivaPct;
$costoTotalCOP  = $cifCOP + $arancelCOP + $ivaCOP + $otrosCOP;
$pesoTotalGr    = array_sum(array_column($items, 'peso_total_gramos'));

// Calcular por ítem
foreach ($items as &$it) {
    $pct       = $pesoTotalGr > 0 ? $it['peso_total_gramos'] / $pesoTotalGr : 0;
    $fleteIt   = $dhlCOP * $pct;
    $arancIt   = $arancelCOP * $pct;
    $ivaIt     = $ivaCOP * $pct;
    $otrosIt   = $otrosCOP * $pct;
    $mercCOP   = $it['precio_unit_usd'] * $trm;
    $it['_pct']    = $pct;
    $it['_flete']  = $fleteIt;
    $it['_aranc']  = $arancIt;
    $it['_iva']    = $ivaIt;
    $it['_otros']  = $otrosIt;
    $it['_costo_unit'] = $mercCOP + (($fleteIt + $arancIt + $ivaIt + $otrosIt) / $it['cantidad']);
}
unset($it);

// ── Confirmar liquidación ──
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::csrfVerify($_POST['csrf'] ?? '')) die('CSRF');
    try {
        $res = liquidarPedido($id);
        $success = "&#x2705; Pedido liquidado exitosamente. Se actualizó el stock de {$res['items']} elementos. Costo total: " . cop($res['costo_total_cop']);
        $ped = $db->query("SELECT * FROM pedidos_importacion WHERE id=$id")->fetch();
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="d-flex align-items-center gap-2 mb-4">
  <a href="<?= APP_URL ?>/modules/importaciones/form.php?id=<?= $id ?>" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i></a>
  <div>
    <h4 class="fw-bold mb-0">Liquidar Pedido</h4>
    <code class="text-primary"><?= $ped['codigo_pedido'] ?></code>
  </div>
</div>

<?php if ($error): ?><div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

<?php if ($ped['estado'] === 'liquidado'): ?>
  <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i>Este pedido ya fue liquidado el <?= date('d/m/Y H:i', strtotime($ped['liquidado_at'])) ?>.</div>
<?php else: ?>

<!-- Resumen financiero -->
<div class="row g-3 mb-4">
  <?php
  $cards = [
    ['label'=>'Valor CIF (USD)','val'=>usd($cifUSD),'icon'=>'bi-globe','color'=>'primary'],
    ['label'=>'Flete DHL (COP)','val'=>cop($dhlCOP),'icon'=>'bi-airplane','color'=>'info'],
    ['label'=>'Arancel '.number_format($ped['arancel_pct'],1).'%','val'=>cop($arancelCOP),'icon'=>'bi-bank','color'=>'warning'],
    ['label'=>'IVA '.$ped['iva_pct'].'%','val'=>cop($ivaCOP),'icon'=>'bi-receipt','color'=>'secondary'],
    ['label'=>'Otros Gastos','val'=>cop($otrosCOP),'icon'=>'bi-three-dots','color'=>'dark'],
    ['label'=>'COSTO TOTAL COP','val'=>cop($costoTotalCOP),'icon'=>'bi-calculator-fill','color'=>'success'],
  ];
  foreach ($cards as $c): ?>
  <div class="col-md-2 col-6">
    <div class="card stat-card h-100">
      <div class="card-body p-3">
        <div class="text-<?= $c['color'] ?> mb-1"><i class="bi <?= $c['icon'] ?> fs-5"></i></div>
        <div class="fw-bold <?= $c['label']==='COSTO TOTAL COP'?'fs-6 text-success':'' ?>"><?= $c['val'] ?></div>
        <div class="text-muted" style="font-size:.72rem;"><?= $c['label'] ?></div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Tabla de distribución proporcional -->
<div class="section-card">
  <h6 class="fw-bold mb-3"><i class="bi bi-pie-chart me-2 text-primary"></i>Distribución por Elemento (Proporcional al Peso)</h6>
  <div class="table-responsive">
    <table class="table table-sm table-bordered table-inv mb-0">
      <thead class="table-primary"><tr>
        <th>Código</th><th>Elemento</th><th>Cant.</th>
        <th>Peso Total (g)</th><th>% Peso</th>
        <th>Precio Mercancía</th>
        <th>Flete DHL</th><th>Arancel</th><th>IVA</th>
        <th class="bg-success text-white">Costo Unit Final COP</th>
      </tr></thead>
      <tbody>
      <?php foreach ($items as $it): ?>
      <tr>
        <td><code class="text-primary"><?= $it['codigo'] ?></code></td>
        <td><?= htmlspecialchars($it['nombre']) ?></td>
        <td class="text-center fw-bold"><?= $it['cantidad'] ?></td>
        <td class="text-end"><?= number_format($it['peso_total_gramos'],1) ?>g</td>
        <td class="text-end">
          <div class="d-flex align-items-center gap-1">
            <div class="progress flex-grow-1" style="height:6px;">
              <div class="progress-bar bg-primary" style="width:<?= min(100,round($it['_pct']*100)) ?>%"></div>
            </div>
            <span class="small"><?= number_format($it['_pct']*100,1) ?>%</span>
          </div>
        </td>
        <td class="text-end"><?= cop($it['precio_unit_usd'] * $trm * $it['cantidad']) ?></td>
        <td class="text-end text-info"><?= cop($it['_flete']) ?></td>
        <td class="text-end text-warning"><?= cop($it['_aranc']) ?></td>
        <td class="text-end text-secondary"><?= cop($it['_iva']) ?></td>
        <td class="text-end fw-bold text-success fs-6"><?= cop($it['_costo_unit']) ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if (!empty($items) && $pesoTotalGr > 0): ?>
  <div class="alert alert-warning mt-3 mb-0">
    <i class="bi bi-exclamation-triangle me-2"></i>
    <strong>Revisar antes de confirmar:</strong> Al liquidar se actualizará el <strong>stock y costo real</strong> de todos los elementos. Esta operación <strong>no se puede deshacer</strong>.
  </div>

  <form method="POST" class="mt-3">
    <input type="hidden" name="csrf" value="<?= Auth::csrfToken() ?>">
    <div class="d-flex gap-2">
      <button type="submit" class="btn btn-success btn-lg fw-bold"
              data-confirm="¿Confirmar liquidación del pedido <?= $ped['codigo_pedido'] ?>? Esto actualizará el stock e inventario.">
        <i class="bi bi-check-circle me-2"></i>Confirmar Liquidación
      </button>
      <a href="<?= APP_URL ?>/modules/importaciones/form.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-lg">Cancelar</a>
    </div>
  </form>
  <?php else: ?>
  <div class="alert alert-danger mt-3">No se puede liquidar: el pedido no tiene ítems o los pesos son 0.</div>
  <?php endif; ?>
</div>

<?php endif; ?>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
