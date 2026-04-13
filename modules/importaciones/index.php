<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();

$db         = Database::get();
$pageTitle  = 'Pedidos de Importaci&#243;n';
$activeMenu = 'pedidos';
$csrf       = Auth::csrfToken();
$msg = $msgType = '';

// ELIMINAR (solo borradores)
if (isset($_GET['del']) && Auth::csrfVerify($_GET['csrf'] ?? '')) {
    $delId = (int)$_GET['del'];
    $st = $db->prepare("SELECT estado,codigo_pedido FROM pedidos_importacion WHERE id=?");
    $st->execute([$delId]); $row = $st->fetch();
    if ($row && $row['estado'] === 'borrador') {
        $db->prepare("DELETE FROM pedido_items WHERE pedido_id=?")->execute([$delId]);
        $db->prepare("DELETE FROM pedidos_importacion WHERE id=?")->execute([$delId]);
    }
    header('Location: ' . APP_URL . '/modules/importaciones/'); exit;
}

// CAMBIAR ESTADO
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='cambiar_estado'
    && Auth::csrfVerify($_POST['csrf']??'')) {
    $pedId    = (int)$_POST['pedido_id'];
    $estados  = ['borrador','en_transito','en_aduana','recibido','liquidado'];
    $newEst   = in_array($_POST['estado']??'', $estados) ? $_POST['estado'] : null;
    if ($newEst) {
        $db->prepare("UPDATE pedidos_importacion SET estado=? WHERE id=?")->execute([$newEst, $pedId]);
    }
    header('Location: ' . APP_URL . '/modules/importaciones/'); exit;
}

// DUPLICAR
if (isset($_GET['dup']) && Auth::csrfVerify($_GET['csrf'] ?? '')) {
    $dupId = (int)$_GET['dup'];
    $orig  = $db->prepare("SELECT * FROM pedidos_importacion WHERE id=?");
    $orig->execute([$dupId]); $orig = $orig->fetch();
    if ($orig) {
        $lastNum = $db->query("SELECT COUNT(*) FROM pedidos_importacion")->fetchColumn() + 1;
        $newCod  = 'PED-' . date('Y') . '-' . str_pad($lastNum, 3, '0', STR_PAD_LEFT);
        $db->prepare("INSERT INTO pedidos_importacion
            (codigo_pedido,proveedor_id,fecha_pedido,valor_fob_usd,arancel_pct,iva_pct,
             tasa_cambio_usd_cop,notas,estado,created_by)
            VALUES (?,?,?,?,?,?,?,?,?,?)")
          ->execute([$newCod, $orig['proveedor_id'], date('Y-m-d'),
            $orig['valor_fob_usd'], $orig['arancel_pct'], $orig['iva_pct'],
            $orig['tasa_cambio_usd_cop'],
            'Duplicado de ' . $orig['codigo_pedido'],
            'borrador', Auth::user()['id']]);
        $newId = $db->lastInsertId();
        $items = $db->prepare("SELECT * FROM pedido_items WHERE pedido_id=?");
        $items->execute([$dupId]);
        foreach ($items->fetchAll() as $it) {
            $db->prepare("INSERT INTO pedido_items
                (pedido_id,elemento_id,descripcion_item,cantidad,precio_unit_usd,peso_unit_gramos)
                VALUES (?,?,?,?,?,?)")
              ->execute([$newId,$it['elemento_id'],$it['descripcion_item'],
                $it['cantidad'],$it['precio_unit_usd'],$it['peso_unit_gramos']]);
        }
        header('Location: ' . APP_URL . '/modules/importaciones/form.php?id=' . $newId . '&ok=duplicado'); exit;
    }
}

$pedidos = $db->query("SELECT * FROM v_pedidos ORDER BY fecha_pedido DESC")->fetchAll();
require_once dirname(__DIR__, 2) . '/includes/header.php';

$estadoColor = ['borrador'=>'secondary','en_transito'=>'info','en_aduana'=>'warning','recibido'=>'primary','liquidado'=>'success'];
$estadoIcon  = ['borrador'=>'&#128221;','en_transito'=>'&#9992;','en_aduana'=>'&#128707;','recibido'=>'&#128230;','liquidado'=>'&#9989;'];
$estadoLabel = ['borrador'=>'Borrador','en_transito'=>'En tr&#225;nsito','en_aduana'=>'En aduana','recibido'=>'Recibido','liquidado'=>'Liquidado'];
?>

<div class="page-header">
  <div>
    <h4 class="page-header-title">Pedidos de Importaci&#243;n</h4>
    <p class="page-header-sub">Gesti&#243;n de importaciones desde China &#183; Liquidaci&#243;n DHL + Aranceles</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="<?= APP_URL ?>/modules/importaciones/importar_csv.php" class="btn btn-success btn-sm">
      <i class="bi bi-file-earmark-spreadsheet me-1"></i>Importar CSV/Excel
    </a>
    <a href="<?= APP_URL ?>/modules/importaciones/form.php" class="btn btn-primary btn-sm">
      <i class="bi bi-plus-lg me-1"></i>Nuevo Pedido
    </a>
  </div>
</div>

<!-- Tarjetas resumen por estado -->
<div class="row g-3 mb-4">
<?php
$resumen = ['borrador'=>0,'en_transito'=>0,'en_aduana'=>0,'recibido'=>0,'liquidado'=>0];
foreach ($pedidos as $p) $resumen[$p['estado']] = ($resumen[$p['estado']]??0)+1;
$cards = ['borrador'=>['secondary','Borradores'],'en_transito'=>['info','En tr&#225;nsito'],
          'en_aduana'=>['warning','En aduana'],'recibido'=>['primary','Recibidos'],'liquidado'=>['success','Liquidados']];
foreach ($cards as $est => [$col,$lbl]):
?>
<div class="col">
  <div class="card stat-card h-100" style="border-left-color: var(--bs-<?= $col ?>)">
    <div class="card-body d-flex align-items-center gap-3">
      <div class="icon-box bg-<?= $col ?> bg-opacity-10 text-<?= $col ?>"><?= $estadoIcon[$est] ?></div>
      <div>
        <div class="dashboard-stat-num text-<?= $col ?>"><?= $resumen[$est] ?></div>
        <div class="dashboard-stat-lbl"><?= $lbl ?></div>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>

<div class="section-card">
  <div class="table-responsive">
    <table class="table table-hover table-inv mb-0">
      <thead><tr>
        <th>C&#243;digo</th><th>Proveedor</th><th>Fecha</th><th>Tracking DHL</th>
        <th>Peso kg</th><th>Items / Uds</th><th>FOB USD</th><th>Total COP</th>
        <th>Estado</th><th>Acciones</th>
      </tr></thead>
      <tbody>
      <?php foreach ($pedidos as $p): ?>
      <tr>
        <td>
          <a href="<?= APP_URL ?>/modules/importaciones/form.php?id=<?= $p['id'] ?>"
             class="fw-bold text-primary text-decoration-none font-monospace">
            <?= htmlspecialchars($p['codigo_pedido']) ?>
          </a>
        </td>
        <td><?= htmlspecialchars($p['proveedor'] ?? '&#8212;') ?></td>
        <td class="text-nowrap"><?= $p['fecha_pedido'] ? date('d/m/Y', strtotime($p['fecha_pedido'])) : '&#8212;' ?></td>
        <td>
          <?php if ($p['numero_tracking_dhl']): ?>
            <a href="https://www.dhl.com/co-es/home/tracking.html?tracking-id=<?= urlencode($p['numero_tracking_dhl']) ?>"
               target="_blank" class="font-monospace small text-decoration-none">
              <?= htmlspecialchars($p['numero_tracking_dhl']) ?> <i class="bi bi-box-arrow-up-right"></i>
            </a>
          <?php else: ?><span class="text-muted">&#8212;</span><?php endif; ?>
        </td>
        <td><?= number_format((float)($p['peso_total_kg']??0),3) ?></td>
        <td class="text-center">
          <span class="badge bg-light text-dark border">
            <?= (int)($p['total_items']??0) ?> ref / <?= number_format((float)($p['total_unidades']??0)) ?> uds
          </span>
        </td>
        <td><?= ($p['valor_fob_usd']??0) > 0 ? usd($p['valor_fob_usd']) : '&#8212;' ?></td>
        <td class="fw-semibold"><?= ($p['costo_total_cop']??0) > 0 ? cop($p['costo_total_cop']) : '&#8212;' ?></td>
        <td>
          <!-- Badge estado clicable para cambiar -->
          <div class="dropdown">
            <button class="badge bg-<?= $estadoColor[$p['estado']] ?> border-0 dropdown-toggle"
                    style="cursor:pointer;font-size:.75rem;padding:.35em .65em;"
                    data-bs-toggle="dropdown">
              <?= $estadoIcon[$p['estado']] ?> <?= $estadoLabel[$p['estado']] ?>
            </button>
            <ul class="dropdown-menu shadow-sm" style="min-width:170px;">
              <li><span class="dropdown-header small">Cambiar estado a:</span></li>
              <?php foreach ($estadoLabel as $est => $lbl): if ($est===$p['estado']) continue; ?>
              <li>
                <form method="POST" class="m-0">
                  <input type="hidden" name="action"    value="cambiar_estado">
                  <input type="hidden" name="csrf"      value="<?= $csrf ?>">
                  <input type="hidden" name="pedido_id" value="<?= $p['id'] ?>">
                  <input type="hidden" name="estado"    value="<?= $est ?>">
                  <button type="submit" class="dropdown-item small py-1">
                    <?= $estadoIcon[$est] ?> <?= $lbl ?>
                  </button>
                </form>
              </li>
              <?php endforeach; ?>
            </ul>
          </div>
        </td>
        <td>
          <div class="d-flex gap-1">
            <a href="<?= APP_URL ?>/modules/importaciones/form.php?id=<?= $p['id'] ?>"
               class="btn btn-sm btn-outline-primary" title="Editar">
              <i class="bi bi-pencil"></i>
            </a>
            <?php if (in_array($p['estado'], ['recibido','borrador','en_aduana'])): ?>
            <a href="<?= APP_URL ?>/modules/importaciones/liquidar.php?id=<?= $p['id'] ?>"
               class="btn btn-sm btn-success" title="Liquidar">
              <i class="bi bi-calculator"></i>
            </a>
            <?php endif; ?>
            <div class="dropdown">
              <button class="btn btn-sm btn-outline-secondary dropdown-toggle dropdown-toggle-split px-2"
                      data-bs-toggle="dropdown"></button>
              <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li>
                  <a class="dropdown-item small" href="#"
                     onclick="verDetalle(<?= $p['id'] ?>,'<?= htmlspecialchars($p['codigo_pedido']) ?>');return false;">
                    <i class="bi bi-eye me-2 text-info"></i>Ver detalle
                  </a>
                </li>
                <li>
                  <a class="dropdown-item small"
                     href="?dup=<?= $p['id'] ?>&csrf=<?= $csrf ?>"
                     onclick="return confirm('Duplicar pedido <?= htmlspecialchars($p['codigo_pedido']) ?>?')">
                    <i class="bi bi-copy me-2 text-primary"></i>Duplicar
                  </a>
                </li>
                <?php if ($p['estado']==='borrador'): ?>
                <li><hr class="dropdown-divider my-1"></li>
                <li>
                  <a class="dropdown-item small text-danger"
                     href="?del=<?= $p['id'] ?>&csrf=<?= $csrf ?>"
                     onclick="return confirm('Eliminar pedido <?= htmlspecialchars($p['codigo_pedido']) ?>?\nEsta accion no se puede deshacer.')">
                    <i class="bi bi-trash me-2"></i>Eliminar
                  </a>
                </li>
                <?php endif; ?>
              </ul>
            </div>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($pedidos)): ?>
        <tr><td colspan="10" class="text-center text-muted py-5">
          <i class="bi bi-airplane fs-2 d-block mb-2 opacity-25"></i>No hay pedidos registrados
        </td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal detalle -->
<div class="modal fade" id="modalDetalle" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header" style="background:linear-gradient(135deg,#1e2a3a,#243447);">
        <h5 class="modal-title text-white fw-bold" id="mdTitulo">Detalle del Pedido</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" id="mdBody">
        <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
      </div>
      <div class="modal-footer">
        <a id="mdEditBtn" href="#" class="btn btn-primary btn-sm">
          <i class="bi bi-pencil me-1"></i>Editar
        </a>
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script>
function verDetalle(id, codigo) {
  document.getElementById('mdTitulo').textContent = 'Pedido ' + codigo;
  document.getElementById('mdEditBtn').href = '<?= APP_URL ?>/modules/importaciones/form.php?id=' + id;
  document.getElementById('mdBody').innerHTML =
    '<div class="text-center py-4"><div class="spinner-border text-primary"></div></div>';
  new bootstrap.Modal(document.getElementById('modalDetalle')).show();

  fetch('<?= APP_URL ?>/api/pedido_detalle.php?id=' + id)
    .then(r => r.json()).then(d => {
      if (!d.ok) { document.getElementById('mdBody').innerHTML='<p class="text-danger">Error.</p>'; return; }
      const p = d.pedido;
      const ec = {borrador:'secondary',en_transito:'info',en_aduana:'warning',recibido:'primary',liquidado:'success'};
      let html = `<div class="row g-3 mb-3">
        <div class="col-4"><div class="small text-muted">Proveedor</div><div class="fw-bold">${p.proveedor||'&#8212;'}</div></div>
        <div class="col-4"><div class="small text-muted">Fecha pedido</div><div>${p.fecha_pedido||'&#8212;'}</div></div>
        <div class="col-4"><div class="small text-muted">Estado</div>
          <span class="badge bg-${ec[p.estado]||'secondary'}">${p.estado.replace(/_/g,' ')}</span></div>
        <div class="col-4"><div class="small text-muted">Tracking DHL</div><div class="font-monospace small">${p.numero_tracking_dhl||'&#8212;'}</div></div>
        <div class="col-4"><div class="small text-muted">Peso</div><div>${p.peso_total_kg||0} kg</div></div>
        <div class="col-4"><div class="small text-muted">TRM</div><div>$ ${parseFloat(p.tasa_cambio_usd_cop||4200).toLocaleString('es-CO')}</div></div>
        <div class="col-3"><div class="small text-muted">FOB USD</div><div class="fw-bold text-primary">$ ${parseFloat(p.valor_fob_usd||0).toLocaleString('es-CO',{minimumFractionDigits:2})}</div></div>
        <div class="col-3"><div class="small text-muted">Flete DHL</div><div>$ ${parseFloat(p.costo_dhl_usd||0).toLocaleString('es-CO',{minimumFractionDigits:2})}</div></div>
        <div class="col-3"><div class="small text-muted">Arancel</div><div>${p.arancel_pct||0}%</div></div>
        <div class="col-3"><div class="small text-muted">Total COP</div><div class="fw-bold text-success">$ ${parseFloat(p.costo_total_cop||0).toLocaleString('es-CO',{minimumFractionDigits:0})}</div></div>
      </div>`;
      if (d.items && d.items.length) {
        html += `<h6 class="fw-bold border-top pt-3 mb-2">Items (${d.items.length} referencias)</h6>
          <div class="table-responsive"><table class="table table-sm table-hover mb-0">
          <thead class="table-light"><tr><th>Descripci&#243;n</th><th class="text-center">Cant.</th><th class="text-end">USD/u</th><th class="text-end">Subtotal</th></tr></thead><tbody>`;
        let tot = 0;
        d.items.forEach(it => {
          tot += parseFloat(it.subtotal_usd||0);
          html += `<tr>
            <td><small>${it.descripcion_item||it.elem_nombre||''}</small>
              ${it.elem_codigo?`<br><code class="text-muted" style="font-size:.65rem;">${it.elem_codigo}</code>`:''}</td>
            <td class="text-center">${it.cantidad}</td>
            <td class="text-end">$ ${parseFloat(it.precio_unit_usd||0).toFixed(3)}</td>
            <td class="text-end fw-bold">$ ${parseFloat(it.subtotal_usd||0).toFixed(2)}</td></tr>`;
        });
        html += `</tbody><tfoot><tr class="table-light fw-bold">
          <td colspan="3" class="text-end">FOB Total:</td>
          <td class="text-end text-primary">$ ${tot.toFixed(2)}</td></tr></tfoot></table></div>`;
      } else {
        html += '<p class="text-muted small mt-2">Sin items registrados.</p>';
      }
      if (p.notas) html += `<div class="mt-3 p-2 bg-light rounded small"><i class="bi bi-chat-left-text me-2"></i>${p.notas}</div>`;
      document.getElementById('mdBody').innerHTML = html;
    }).catch(() => {
      document.getElementById('mdBody').innerHTML = '<p class="text-danger">Error de conexi&#243;n.</p>';
    });
}
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
