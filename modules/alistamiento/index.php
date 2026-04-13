<?php
/**
 * modules/alistamiento/index.php
 * Kanban de alistamiento — 3 columnas:
 *   en_alistamiento  → Pendiente alistamiento
 *   listo_envio      → Empacado
 *   despachado       → Enviado (últimos 30 días)
 *
 * Acceso: Gerencia (1), Administración (2), Producción (4)
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();
Auth::requirePermiso('alistamiento', 'ver');

$db         = Database::get();
$pageTitle  = 'Alistamiento';
$activeMenu = 'alistamiento';
$userId     = Auth::user()['id'];
$error = $success = '';

$colInstruc = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tienda_pedidos'
    AND COLUMN_NAME='instrucciones_especiales'")->fetchColumn();
$instrucSel = $colInstruc ? ", p.instrucciones_especiales" : ", NULL AS instrucciones_especiales";

$colTipoDespacho = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tienda_pedidos'
    AND COLUMN_NAME='tipo_despacho'")->fetchColumn();
$colSedeRecogida = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tienda_pedidos'
    AND COLUMN_NAME='sede_recogida'")->fetchColumn();
$colEstadoPago   = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tienda_pedidos'
    AND COLUMN_NAME='estado_pago'")->fetchColumn();
$tipoDespachoSel = $colTipoDespacho ? ", p.tipo_despacho"        : ", 'envio' AS tipo_despacho";
$sedeRecogidaSel = $colSedeRecogida ? ", p.sede_recogida"        : ", NULL AS sede_recogida";
$estadoPagoSel   = $colEstadoPago   ? ", p.estado_pago"          : ", 'pagado' AS estado_pago";

// ── Sedes de recogida local (agregar/quitar sedes aquí) ──────────
$SEDES_RECOGIDA = ['Sede Calle 75', 'Sede Calle 134'];

// ── POST: marcar empacado (individual o bulk) ─────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'empacar'
    && Auth::csrfVerify($_POST['csrf'] ?? '')
) {
    $pids = array_filter(array_map('intval', (array)($_POST['pedido_ids'] ?? [])));
    if (empty($pids)) $pids = [(int)($_POST['pedido_id'] ?? 0)];
    $pids = array_filter($pids);
    try {
        $n = 0;
        foreach ($pids as $pid) {
            $est = $db->prepare("SELECT estado FROM tienda_pedidos WHERE id=?");
            $est->execute([$pid]);
            $estActual = $est->fetchColumn();
            if ($estActual !== 'en_alistamiento') continue;
            $db->prepare("UPDATE tienda_pedidos SET estado='listo_envio', updated_at=NOW() WHERE id=?")->execute([$pid]);
            $db->prepare("INSERT INTO tienda_pedidos_historial (pedido_id,estado_ant,estado_nuevo,nota,usuario_id) VALUES (?,?,?,?,?)")
               ->execute([$pid, 'en_alistamiento', 'listo_envio', 'Marcado como empacado', $userId]);
            $n++;
        }
        $success = $n === 1 ? 'Pedido marcado como empacado.' : "$n pedidos marcados como empacados.";
    } catch (Exception $e) { $error = $e->getMessage(); }
}

// ── POST: marcar despachado (individual o bulk) ───────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'despachar'
    && Auth::csrfVerify($_POST['csrf'] ?? '')
) {
    $tipo           = in_array($_POST['tipo_despacho'] ?? '', ['envio','recogida_local'])
                        ? $_POST['tipo_despacho'] : 'envio';
    $guia           = $tipo === 'envio' ? trim($_POST['guia_envio']     ?? '') : '';
    $transportadora = $tipo === 'envio' ? trim($_POST['transportadora'] ?? '') : '';
    $sede           = $tipo === 'recogida_local' ? trim($_POST['sede_recogida'] ?? '') : '';

    // Soporta tanto pedido_id (individual) como pedido_ids[] (bulk)
    $pids = array_filter(array_map('intval', (array)($_POST['pedido_ids'] ?? [])));
    if (empty($pids) && !empty($_POST['pedido_id'])) $pids = [(int)$_POST['pedido_id']];
    $pids = array_filter($pids);

    try {
        if (empty($pids)) throw new RuntimeException('No se especificaron pedidos.');

        // Nota para el historial
        if ($tipo === 'recogida_local') {
            $nota = 'Recogida local' . ($sede ? " — $sede" : '');
        } else {
            $nota = 'Despachado' . ($transportadora ? " vía $transportadora" : '')
                                 . ($guia          ? " — guía $guia"        : '');
        }

        // Construir SET dinámico
        $sets      = "estado='despachado', fecha_despacho=CURDATE(), updated_at=NOW()";
        $setParams = [];
        if ($colTipoDespacho) { $sets .= ', tipo_despacho=?'; $setParams[] = $tipo; }
        if ($tipo === 'recogida_local') {
            $sets .= ', guia_envio=NULL, transportadora=NULL';
            if ($colSedeRecogida) {
                $sets .= ', sede_recogida=?';
                $setParams[] = $sede ?: null;
            }
        } else {
            if ($colSedeRecogida) $sets .= ', sede_recogida=NULL';
            if ($guia)            { $sets .= ', guia_envio=?';    $setParams[] = $guia; }
            if ($transportadora)  { $sets .= ', transportadora=?'; $setParams[] = $transportadora; }
        }

        $n = 0;
        foreach ($pids as $pid) {
            $estSt = $db->prepare("SELECT estado FROM tienda_pedidos WHERE id=?");
            $estSt->execute([$pid]);
            $estActual = $estSt->fetchColumn();
            if (!$estActual || $estActual !== 'listo_envio') continue;
            $db->prepare("UPDATE tienda_pedidos SET $sets WHERE id=?")->execute([...$setParams, $pid]);
            $db->prepare("INSERT INTO tienda_pedidos_historial (pedido_id,estado_ant,estado_nuevo,nota,usuario_id) VALUES (?,?,?,?,?)")
               ->execute([$pid, $estActual, 'despachado', $nota, $userId]);
            $n++;
        }
        $success = $n === 1 ? 'Pedido marcado como enviado.' : "$n pedidos marcados como enviados.";
    } catch (Exception $e) { $error = $e->getMessage(); }
}

// ── Cargar pedidos por columna ────────────────────────────────────
// Subquery: última nota del historial al despachar (fuente fiable del tipo de egreso)
$historialSel = ",(SELECT nota FROM tienda_pedidos_historial
                   WHERE pedido_id=p.id AND estado_nuevo='despachado'
                   ORDER BY id DESC LIMIT 1) AS nota_despacho";

$BASE_SELECT = "SELECT p.id, p.woo_order_id, p.estado,
       p.cliente_nombre, p.cliente_telefono, p.cliente_email,
       p.kit_nombre, p.cantidad, p.ciudad, p.colegio_nombre,
       p.fecha_compra, p.guia_envio, p.transportadora,
       p.direccion $instrucSel $tipoDespachoSel $sedeRecogidaSel $estadoPagoSel $historialSel
FROM tienda_pedidos p";

$cols = [];
foreach (['en_alistamiento', 'listo_envio', 'despachado'] as $est) {
    $extraWhere = ($est === 'despachado') ? "AND p.fecha_despacho >= CURDATE() - INTERVAL 30 DAY" : '';
    $st = $db->query("$BASE_SELECT WHERE p.estado = '$est' $extraWhere ORDER BY p.fecha_compra ASC LIMIT 100");
    $cols[$est] = $st->fetchAll(PDO::FETCH_ASSOC);
}

$COLS_META = [
    'en_alistamiento' => ['label' => 'Pendiente alistamiento', 'color' => '#7c3aed', 'bg' => '#f5f3ff', 'icon' => 'bi-box-seam'],
    'listo_envio'     => ['label' => 'Empacado',               'color' => '#0891b2', 'bg' => '#e0f2fe', 'icon' => 'bi-box-fill'],
    'despachado'      => ['label' => 'Enviado',                'color' => '#16a34a', 'bg' => '#dcfce7', 'icon' => 'bi-truck'],
];

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
.al-col{background:#fff;border-radius:14px;border:1.5px solid #e2e8f0;overflow:hidden;height:fit-content}
.al-col-header{padding:.6rem .9rem;display:flex;align-items:center;justify-content:space-between;
               cursor:pointer;user-select:none;gap:.5rem}
.al-col-body{padding:.5rem;min-height:60px}
.al-card{background:#fff;border-radius:10px;border:1px solid #e2e8f0;
         margin-bottom:.5rem;padding:.6rem .75rem;transition:.12s}
.al-card:hover{box-shadow:0 2px 8px rgba(0,0,0,.07)}
.al-card.sel{background:#f5f3ff;border-color:#7c3aed44}
.chk-al{width:15px;height:15px;cursor:pointer;accent-color:#7c3aed;flex-shrink:0;margin-top:2px}
.chk-listo{width:15px;height:15px;cursor:pointer;accent-color:#0891b2;flex-shrink:0;margin-top:2px}
.al-card.sel-listo{background:#e0f2fe;border-color:#0891b244}
.barra-al,.barra-listo{position:fixed;left:50%;transform:translateX(-50%);
          background:#1e293b;color:#fff;border-radius:14px;
          padding:.6rem 1.4rem;display:none;align-items:center;gap:.75rem;
          box-shadow:0 8px 24px rgba(0,0,0,.3);z-index:1000;white-space:nowrap}
.barra-al{bottom:1.5rem}
.barra-listo{bottom:5.5rem}
.barra-al.visible{display:flex}
.barra-listo.visible{display:flex}
</style>

<!-- ── Cabecera ── -->
<div class="page-header">
  <div>
    <h4 class="page-header-title">
      <i class="bi bi-box-seam me-2" style="color:#7c3aed"></i>Alistamiento
    </h4>
    <p class="page-header-sub">
      <span style="color:#7c3aed;font-weight:600"><?= count($cols['en_alistamiento']) ?></span> pendientes &middot;
      <span style="color:#0891b2;font-weight:600"><?= count($cols['listo_envio']) ?></span> empacados &middot;
      <span style="color:#16a34a;font-weight:600"><?= count($cols['despachado']) ?></span> enviados (últimos 30 días)
    </p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="<?= APP_URL ?>/modules/produccion/" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-arrow-left me-1"></i>Producción
    </a>
  </div>
</div>

<?php if ($error):   ?><div class="alert alert-danger  py-2 small"><?= htmlspecialchars($error)   ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<!-- ── Kanban 3 columnas ── -->
<div class="row g-3 align-items-start">
<?php foreach ($COLS_META as $estKey => $meta):
  $tarjetas = $cols[$estKey];
?>
<div class="col-md-4">
<div class="al-col" id="alcol-<?= $estKey ?>">

  <!-- Header colapsable -->
  <div class="al-col-header" style="background:<?= $meta['bg'] ?>;color:<?= $meta['color'] ?>"
       onclick="toggleAlCol('<?= $estKey ?>')">
    <span class="fw-bold d-flex align-items-center gap-1" style="font-size:.82rem">
      <i class="bi <?= $meta['icon'] ?>"></i>
      <?= $meta['label'] ?>
      <span class="badge ms-1" style="background:<?= $meta['color'] ?>;color:#fff;font-size:.65rem">
        <?= count($tarjetas) ?>
      </span>
    </span>
    <?php if ($estKey === 'en_alistamiento' && !empty($tarjetas)): ?>
    <button class="btn btn-sm" style="padding:.15rem .55rem;font-size:.71rem;background:<?= $meta['color'] ?>;color:#fff;border-radius:8px;flex-shrink:0"
            onclick="event.stopPropagation();imprimirSeleccionados()">
      <i class="bi bi-printer me-1"></i>Imprimir seleccionados
    </button>
    <?php endif; ?>
    <?php if ($estKey === 'listo_envio' && !empty($tarjetas)): ?>
    <button class="btn btn-sm" style="padding:.15rem .55rem;font-size:.71rem;background:<?= $meta['color'] ?>;color:#fff;border-radius:8px;flex-shrink:0"
            onclick="event.stopPropagation();abrirDespachoBulk()">
      <i class="bi bi-truck me-1"></i>Despachar seleccionados
    </button>
    <?php endif; ?>
  </div>

  <!-- Body -->
  <div class="al-col-body" id="albody-<?= $estKey ?>">

    <?php if (empty($tarjetas)): ?>
    <div class="text-center text-muted py-5" style="font-size:.78rem">
      <i class="bi bi-inbox d-block mb-1" style="font-size:1.4rem"></i>Sin pedidos
    </div>
    <?php endif; ?>

    <!-- Seleccionar todos (columna 1 y 2) -->
    <?php if ($estKey === 'en_alistamiento' && !empty($tarjetas)): ?>
    <div class="d-flex align-items-center gap-2 px-1 pb-2 mb-1" style="border-bottom:1px solid #f1f5f9">
      <input type="checkbox" class="chk-al" id="chk-al-all"
             onchange="selTodosAl(this.checked)" title="Seleccionar todos">
      <label for="chk-al-all" style="font-size:.72rem;color:#64748b;cursor:pointer;margin:0">
        Seleccionar todos
      </label>
    </div>
    <?php endif; ?>
    <?php if ($estKey === 'listo_envio' && !empty($tarjetas)): ?>
    <div class="d-flex align-items-center gap-2 px-1 pb-2 mb-1" style="border-bottom:1px solid #f1f5f9">
      <input type="checkbox" class="chk-listo" id="chk-listo-all"
             onchange="selTodosListo(this.checked)" title="Seleccionar todos">
      <label for="chk-listo-all" style="font-size:.72rem;color:#64748b;cursor:pointer;margin:0">
        Seleccionar todos
      </label>
    </div>
    <?php endif; ?>

    <?php foreach ($tarjetas as $p):
      $cant = (int)($p['cantidad'] ?? 1);
    ?>
    <div class="al-card" id="card-<?= $p['id'] ?>">
      <div class="d-flex align-items-start gap-2">

        <!-- Checkbox (col 1 y col 2) -->
        <?php if ($estKey === 'en_alistamiento'): ?>
        <input type="checkbox" class="chk-al chk-ped" value="<?= $p['id'] ?>"
               data-order="<?= htmlspecialchars($p['woo_order_id']) ?>"
               onchange="actualizarBarraAl()" title="Seleccionar">
        <?php elseif ($estKey === 'listo_envio'): ?>
        <input type="checkbox" class="chk-listo chk-listo-ped" value="<?= $p['id'] ?>"
               onchange="actualizarBarraListo()" title="Seleccionar">
        <?php endif; ?>

        <div style="flex:1;min-width:0">
          <!-- Orden + link -->
          <div class="d-flex align-items-center justify-content-between gap-1 mb-1">
            <span class="fw-bold" style="font-size:.8rem">
              #<?= htmlspecialchars($p['woo_order_id']) ?>
            </span>
            <a href="<?= APP_URL ?>/modules/pedidos_tienda/ver.php?id=<?= $p['id'] ?>"
               target="_blank" class="text-muted" style="font-size:.72rem" title="Ver pedido completo">
              <i class="bi bi-box-arrow-up-right"></i>
            </a>
          </div>

          <!-- Kit -->
          <div class="fw-semibold" style="font-size:.79rem;color:#1e293b;line-height:1.3;margin-bottom:.2rem">
            <?= htmlspecialchars($p['kit_nombre'] ?: '—') ?>
            <?php if ($cant > 1): ?>
            <span class="badge bg-secondary ms-1" style="font-size:.62rem"><?= $cant ?> uds</span>
            <?php endif; ?>
          </div>

          <!-- Cliente -->
          <div style="font-size:.72rem;color:#64748b;margin-bottom:.2rem">
            <i class="bi bi-person me-1"></i><?= htmlspecialchars($p['cliente_nombre']) ?>
            <?php if ($p['ciudad']): ?>&middot; <?= htmlspecialchars($p['ciudad']) ?><?php endif; ?>
          </div>

          <!-- Colegio -->
          <?php if ($p['colegio_nombre']): ?>
          <div style="font-size:.72rem;color:#0891b2;margin-bottom:.2rem">
            <i class="bi bi-building me-1"></i><?= htmlspecialchars($p['colegio_nombre']) ?>
          </div>
          <?php endif; ?>

          <!-- Instrucciones especiales -->
          <?php if (!empty($p['instrucciones_especiales'])): ?>
          <div class="mb-2 px-2 py-1 rounded" style="background:#fefce8;border:1px solid #fde68a;font-size:.71rem">
            <i class="bi bi-info-circle me-1 text-warning"></i>
            <?= htmlspecialchars($p['instrucciones_especiales']) ?>
          </div>
          <?php endif; ?>

          <!-- Pago pendiente -->
          <?php if (($p['estado_pago'] ?? 'pagado') === 'pendiente'): ?>
          <div class="mb-2 px-2 py-1 rounded d-flex align-items-center gap-1"
               style="background:#fef2f2;border:1px solid #fca5a5;font-size:.71rem;color:#991b1b">
            <i class="bi bi-exclamation-triangle-fill"></i>
            <strong>Pago pendiente</strong>
          </div>
          <?php endif; ?>

          <!-- Tipo de despacho (todas las columnas) -->
          <?php
            $esRecogida = ($p['tipo_despacho'] ?? '') === 'recogida_local';
            $sedeVal    = $p['sede_recogida'] ?? '';
            // Fallback: parsear nota del historial (siempre disponible)
            if (!$esRecogida && !empty($p['nota_despacho'])) {
                $notaD = $p['nota_despacho'];
                if (str_starts_with($notaD, 'Recogida local')) {
                    $esRecogida = true;
                    if (empty($sedeVal) && preg_match('/Recogida local\s*[—-]\s*(.+)$/u', $notaD, $m)) {
                        $sedeVal = trim($m[1]);
                    }
                }
            }
            if ($esRecogida):
          ?>
          <div style="font-size:.72rem;color:#0891b2;margin-bottom:.3rem">
            <i class="bi bi-shop me-1"></i>
            <strong>Recogida local</strong>
            <?php if (!empty($sedeVal)): ?>
            &mdash; <?= htmlspecialchars($sedeVal) ?>
            <?php endif; ?>
          </div>
          <?php elseif ($estKey !== 'en_alistamiento' && ($p['guia_envio'] || $p['transportadora'])): ?>
          <div style="font-size:.72rem;color:#16a34a;margin-bottom:.3rem">
            <i class="bi bi-truck me-1"></i>
            <?= htmlspecialchars($p['transportadora'] ?? '') ?>
            <?php if ($p['guia_envio']): ?>
            &mdash; <code style="font-size:.68rem"><?= htmlspecialchars($p['guia_envio']) ?></code>
            <?php endif; ?>
          </div>
          <?php endif; ?>

          <!-- Acciones -->
          <div class="d-flex gap-1 flex-wrap mt-2">
            <!-- Imprimir etiqueta (siempre) -->
            <a href="<?= APP_URL ?>/modules/pedidos_tienda/stickers.php?ids=<?= $p['id'] ?>"
               target="_blank"
               class="btn btn-sm btn-outline-danger"
               style="font-size:.71rem;padding:.2rem .5rem">
              <i class="bi bi-printer me-1"></i>Etiqueta
            </a>

            <?php if ($estKey === 'en_alistamiento'): ?>
            <!-- Marcar empacado (individual) -->
            <form method="POST" style="display:inline">
              <input type="hidden" name="action"    value="empacar">
              <input type="hidden" name="csrf"      value="<?= Auth::csrfToken() ?>">
              <input type="hidden" name="pedido_id" value="<?= $p['id'] ?>">
              <button type="submit" class="btn btn-sm btn-outline-primary"
                      style="font-size:.71rem;padding:.2rem .5rem">
                <i class="bi bi-box-fill me-1"></i>Marcar empacado
              </button>
            </form>
            <?php endif; ?>

            <?php if ($estKey === 'listo_envio'): ?>
            <!-- Marcar enviado → abre modal -->
            <button class="btn btn-sm btn-success"
                    style="font-size:.71rem;padding:.2rem .5rem"
                    onclick="abrirDespacho(
                      <?= $p['id'] ?>,
                      '<?= htmlspecialchars($p['tipo_despacho'] ?? 'envio', ENT_QUOTES) ?>',
                      '<?= htmlspecialchars($p['transportadora'] ?? '', ENT_QUOTES) ?>',
                      '<?= htmlspecialchars($p['guia_envio'] ?? '', ENT_QUOTES) ?>',
                      '<?= htmlspecialchars($p['sede_recogida'] ?? '', ENT_QUOTES) ?>')">
              <i class="bi bi-truck me-1"></i>Marcar enviado
            </button>
            <?php endif; ?>
          </div><!-- /acciones -->

        </div><!-- /flex:1 -->
      </div><!-- /d-flex -->
    </div><!-- /.al-card -->
    <?php endforeach; ?>

  </div><!-- /.al-col-body -->
</div><!-- /.al-col -->
</div><!-- /.col-md-4 -->
<?php endforeach; ?>
</div><!-- /.row -->

<!-- ── Barra flotante selección múltiple ── -->
<div class="barra-al" id="barra-al">
  <span style="font-size:.85rem;font-weight:700">
    <span id="cnt-al">0</span> seleccionado(s)
  </span>
  <div class="d-flex gap-2">
    <button class="btn btn-danger btn-sm fw-bold" onclick="imprimirSeleccionados()">
      <i class="bi bi-printer me-1"></i>Imprimir guías
    </button>
    <form method="POST" id="form-empacar-bulk" style="display:inline">
      <input type="hidden" name="action" value="empacar">
      <input type="hidden" name="csrf"   value="<?= Auth::csrfToken() ?>">
      <div id="hidden-pids"></div>
      <button type="submit" class="btn btn-primary btn-sm fw-bold"
              onclick="prepararBulk(event)">
        <i class="bi bi-box-fill me-1"></i>Marcar empacados
      </button>
    </form>
    <button class="btn btn-outline-light btn-sm" onclick="deselTodosAl()">Cancelar</button>
  </div>
</div>

<!-- ── Barra flotante selección listo_envio ── -->
<div class="barra-listo" id="barra-listo">
  <span style="font-size:.85rem;font-weight:700">
    <span id="cnt-listo">0</span> empacado(s) seleccionado(s)
  </span>
  <div class="d-flex gap-2">
    <button class="btn btn-success btn-sm fw-bold" onclick="abrirDespachoBulk()">
      <i class="bi bi-truck me-1"></i>Despachar seleccionados
    </button>
    <button class="btn btn-outline-light btn-sm" onclick="deselTodosListo()">Cancelar</button>
  </div>
</div>

<!-- ── Modal despacho individual ── -->
<div class="modal fade" id="modalDespacho" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title fw-bold">
          <i class="bi bi-send me-2"></i>Confirmar egreso del pedido
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <div class="modal-body">
          <input type="hidden" name="action"    value="despachar">
          <input type="hidden" name="csrf"      value="<?= Auth::csrfToken() ?>">
          <input type="hidden" name="pedido_id" id="modal-pid" value="">

          <!-- Tipo de egreso -->
          <div class="mb-3">
            <label class="form-label fw-semibold small">Tipo de egreso</label>
            <div class="d-flex gap-3">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="tipo_despacho"
                       id="modal-tipo-envio" value="envio" checked
                       onchange="toggleTipo('modal','envio')">
                <label class="form-check-label small fw-semibold" for="modal-tipo-envio">
                  <i class="bi bi-truck me-1 text-success"></i>Envío
                </label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="tipo_despacho"
                       id="modal-tipo-recogida" value="recogida_local"
                       onchange="toggleTipo('modal','recogida_local')">
                <label class="form-check-label small fw-semibold" for="modal-tipo-recogida">
                  <i class="bi bi-shop me-1 text-primary"></i>Recogida local
                </label>
              </div>
            </div>
          </div>

          <!-- Sección envío -->
          <div id="modal-seccion-envio">
            <div class="mb-3">
              <label class="form-label small fw-semibold">Transportadora</label>
              <input type="text" name="transportadora" id="modal-transportadora"
                     class="form-control" placeholder="ej: Coordinadora, Servientrega, TCC...">
            </div>
            <div class="mb-2">
              <label class="form-label small fw-semibold">Número de guía</label>
              <input type="text" name="guia_envio" id="modal-guia"
                     class="form-control" placeholder="Número de guía o referencia de envío">
            </div>
            <div class="form-text">Ambos campos son opcionales pero recomendados.</div>
          </div>

          <!-- Sección recogida local -->
          <div id="modal-seccion-recogida" style="display:none">
            <div class="mb-2">
              <label class="form-label small fw-semibold">Sede de recogida</label>
              <select name="sede_recogida" id="modal-sede" class="form-select">
                <option value="">— Seleccionar sede —</option>
                <?php foreach ($SEDES_RECOGIDA as $s): ?>
                <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-text">El pedido quedará registrado como entregado en esa sede.</div>
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-sm btn-success fw-bold">
            <i class="bi bi-check2 me-1"></i>Confirmar
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- ── Modal despacho BULK ── -->
<div class="modal fade" id="modalDespachoBulk" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header py-2">
        <h6 class="modal-title fw-bold">
          <i class="bi bi-send me-2"></i>Egreso masivo de pedidos
        </h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" id="form-despachar-bulk">
        <div class="modal-body">
          <input type="hidden" name="action" value="despachar">
          <input type="hidden" name="csrf"   value="<?= Auth::csrfToken() ?>">
          <div id="hidden-pids-listo"></div>

          <div class="alert alert-info py-2 small mb-3">
            <i class="bi bi-info-circle me-1"></i>
            El mismo tipo de egreso se aplicará a todos los pedidos seleccionados
            (<strong><span id="bulk-count-label">0</span> pedido(s)</strong>).
          </div>

          <!-- Tipo de egreso -->
          <div class="mb-3">
            <label class="form-label fw-semibold small">Tipo de egreso</label>
            <div class="d-flex gap-3">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="tipo_despacho"
                       id="bulk-tipo-envio" value="envio" checked
                       onchange="toggleTipo('bulk','envio')">
                <label class="form-check-label small fw-semibold" for="bulk-tipo-envio">
                  <i class="bi bi-truck me-1 text-success"></i>Envío
                </label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="tipo_despacho"
                       id="bulk-tipo-recogida" value="recogida_local"
                       onchange="toggleTipo('bulk','recogida_local')">
                <label class="form-check-label small fw-semibold" for="bulk-tipo-recogida">
                  <i class="bi bi-shop me-1 text-primary"></i>Recogida local
                </label>
              </div>
            </div>
          </div>

          <!-- Sección envío bulk -->
          <div id="bulk-seccion-envio">
            <div class="mb-3">
              <label class="form-label small fw-semibold">Transportadora</label>
              <input type="text" name="transportadora" id="bulk-transportadora"
                     class="form-control" placeholder="ej: Coordinadora, Servientrega, TCC...">
            </div>
            <div class="mb-2">
              <label class="form-label small fw-semibold">Número de guía</label>
              <input type="text" name="guia_envio" id="bulk-guia"
                     class="form-control" placeholder="Número de guía (se asigna a todos)">
            </div>
            <div class="form-text">Ambos campos son opcionales.</div>
          </div>

          <!-- Sección recogida local bulk -->
          <div id="bulk-seccion-recogida" style="display:none">
            <div class="mb-2">
              <label class="form-label small fw-semibold">Sede de recogida</label>
              <select name="sede_recogida" id="bulk-sede" class="form-select">
                <option value="">— Seleccionar sede —</option>
                <?php foreach ($SEDES_RECOGIDA as $s): ?>
                <option value="<?= htmlspecialchars($s) ?>"><?= htmlspecialchars($s) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-text">Todos los pedidos se registrarán como recogidos en esa sede.</div>
          </div>
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-sm btn-success fw-bold" onclick="prepararDespachoBulk(event)">
            <i class="bi bi-check2-all me-1"></i>Confirmar egreso masivo
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// ── Toggle columna ──────────────────────────────────────────────
function toggleAlCol(key) {
    var body = document.getElementById('albody-' + key);
    if (body) body.style.display = (body.style.display === 'none') ? '' : 'none';
}

// ── Selección ───────────────────────────────────────────────────
function selTodosAl(on) {
    document.querySelectorAll('.chk-ped').forEach(function(c) { c.checked = on; });
    actualizarBarraAl();
}
function deselTodosAl() {
    document.querySelectorAll('.chk-ped').forEach(function(c) { c.checked = false; });
    var all = document.getElementById('chk-al-all');
    if (all) all.checked = false;
    actualizarBarraAl();
}
function actualizarBarraAl() {
    var chks  = document.querySelectorAll('.chk-ped:checked');
    var total = document.querySelectorAll('.chk-ped').length;
    var n     = chks.length;
    document.getElementById('cnt-al').textContent = n;
    document.getElementById('barra-al').classList.toggle('visible', n > 0);
    // Resaltar tarjetas seleccionadas
    document.querySelectorAll('.chk-ped').forEach(function(c) {
        var card = document.getElementById('card-' + c.value);
        if (card) card.classList.toggle('sel', c.checked);
    });
    var all = document.getElementById('chk-al-all');
    if (all) {
        all.indeterminate = n > 0 && n < total;
        all.checked       = n > 0 && n === total;
    }
}

// ── Imprimir seleccionados ──────────────────────────────────────
function imprimirSeleccionados() {
    var ids = Array.from(document.querySelectorAll('.chk-ped:checked')).map(function(c){ return c.value; });
    if (!ids.length) { alert('Selecciona al menos un pedido para imprimir.'); return; }
    window.open('<?= APP_URL ?>/modules/pedidos_tienda/stickers.php?ids=' + ids.join(','), '_blank');
}

// ── Bulk empacar ────────────────────────────────────────────────
function prepararBulk(e) {
    var ids = Array.from(document.querySelectorAll('.chk-ped:checked')).map(function(c){ return c.value; });
    if (!ids.length) { e.preventDefault(); alert('Selecciona al menos un pedido.'); return; }
    var container = document.getElementById('hidden-pids');
    container.innerHTML = '';
    ids.forEach(function(id) {
        var inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'pedido_ids[]'; inp.value = id;
        container.appendChild(inp);
    });
}

// ── Selección listo_envio ───────────────────────────────────────
function selTodosListo(on) {
    document.querySelectorAll('.chk-listo-ped').forEach(function(c) { c.checked = on; });
    actualizarBarraListo();
}
function deselTodosListo() {
    document.querySelectorAll('.chk-listo-ped').forEach(function(c) { c.checked = false; });
    var all = document.getElementById('chk-listo-all');
    if (all) all.checked = false;
    actualizarBarraListo();
}
function actualizarBarraListo() {
    var chks  = document.querySelectorAll('.chk-listo-ped:checked');
    var total = document.querySelectorAll('.chk-listo-ped').length;
    var n     = chks.length;
    document.getElementById('cnt-listo').textContent = n;
    document.getElementById('barra-listo').classList.toggle('visible', n > 0);
    document.querySelectorAll('.chk-listo-ped').forEach(function(c) {
        var card = document.getElementById('card-' + c.value);
        if (card) card.classList.toggle('sel-listo', c.checked);
    });
    var all = document.getElementById('chk-listo-all');
    if (all) {
        all.indeterminate = n > 0 && n < total;
        all.checked       = n > 0 && n === total;
    }
}

// ── Toggle envío / recogida local ──────────────────────────────
function toggleTipo(prefix, tipo) {
    var envio   = document.getElementById(prefix + '-seccion-envio');
    var recogida= document.getElementById(prefix + '-seccion-recogida');
    if (!envio || !recogida) return;
    var esRecogida = tipo === 'recogida_local';
    envio.style.display    = esRecogida ? 'none' : '';
    recogida.style.display = esRecogida ? ''     : 'none';
}

// ── Abrir modal despacho individual ────────────────────────────
function abrirDespacho(pid, tipo, transportadora, guia, sede) {
    tipo = tipo || 'envio';
    document.getElementById('modal-pid').value            = pid;
    document.getElementById('modal-transportadora').value = transportadora || '';
    document.getElementById('modal-guia').value           = guia || '';
    document.getElementById('modal-sede').value           = sede || '';
    // Preseleccionar y bloquear el tipo — no se puede cambiar lo que ya definió el pedido
    var radioEnvio    = document.getElementById('modal-tipo-envio');
    var radioRecogida = document.getElementById('modal-tipo-recogida');
    radioEnvio.checked     = (tipo === 'envio');
    radioRecogida.checked  = (tipo === 'recogida_local');
    radioEnvio.disabled    = true;
    radioRecogida.disabled = true;
    toggleTipo('modal', tipo);
    new bootstrap.Modal(document.getElementById('modalDespacho')).show();
}
document.getElementById('modalDespacho').addEventListener('hidden.bs.modal', function() {
    document.getElementById('modal-tipo-envio').disabled    = false;
    document.getElementById('modal-tipo-recogida').disabled = false;
});

// ── Abrir modal despacho bulk ───────────────────────────────────
function abrirDespachoBulk() {
    var ids = Array.from(document.querySelectorAll('.chk-listo-ped:checked')).map(function(c){ return c.value; });
    if (!ids.length) { alert('Selecciona al menos un pedido para despachar.'); return; }
    // Reset formulario
    document.getElementById('bulk-transportadora').value = '';
    document.getElementById('bulk-guia').value           = '';
    document.getElementById('bulk-sede').value           = '';
    document.getElementById('bulk-tipo-envio').checked   = true;
    toggleTipo('bulk', 'envio');
    document.getElementById('bulk-count-label').textContent = ids.length;
    new bootstrap.Modal(document.getElementById('modalDespachoBulk')).show();
}

// ── Preparar form despacho bulk ─────────────────────────────────
function prepararDespachoBulk(e) {
    var ids = Array.from(document.querySelectorAll('.chk-listo-ped:checked')).map(function(c){ return c.value; });
    if (!ids.length) { e.preventDefault(); alert('Selecciona al menos un pedido.'); return; }
    var container = document.getElementById('hidden-pids-listo');
    container.innerHTML = '';
    ids.forEach(function(id) {
        var inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'pedido_ids[]'; inp.value = id;
        container.appendChild(inp);
    });
}
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
