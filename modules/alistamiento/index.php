<?php
/**
 * modules/alistamiento/index.php
 * Módulo de alistamiento: búsqueda, confirmación de etiqueta y despacho de pedidos.
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

// ── Detectar columna instrucciones_especiales ──────────────────
$colInstruc = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tienda_pedidos'
    AND COLUMN_NAME='instrucciones_especiales'")->fetchColumn();

// ── POST: confirmar etiqueta → en_alistamiento ─────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'confirmar_etiqueta'
    && Auth::csrfVerify($_POST['csrf'] ?? '')
) {
    $pid = (int)($_POST['pedido_id'] ?? 0);
    try {
        $pedido = $db->prepare("SELECT estado FROM tienda_pedidos WHERE id=?");
        $pedido->execute([$pid]);
        $row = $pedido->fetch(PDO::FETCH_ASSOC);

        if (!$row) throw new RuntimeException('Pedido no encontrado.');
        if (!in_array($row['estado'], ['listo_produccion', 'aprobado', 'en_produccion'])) {
            throw new RuntimeException('El pedido no está listo para alistamiento (estado: ' . $row['estado'] . ').');
        }

        $db->prepare("UPDATE tienda_pedidos SET estado='en_alistamiento' WHERE id=?")
           ->execute([$pid]);

        $db->prepare("INSERT INTO tienda_pedidos_historial
            (pedido_id, estado_ant, estado_nuevo, nota, usuario_id)
            VALUES (?, ?, 'en_alistamiento', 'Etiqueta confirmada en alistamiento', ?)")
           ->execute([$pid, $row['estado'], $userId]);

        header('Location: ' . APP_URL . '/modules/alistamiento/?pid=' . $pid . '&ok=etiqueta');
        exit;
    } catch (Exception $e) {
        $errorAccion = $e->getMessage();
    }
}

// ── POST: marcar despachado ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'despachar'
    && Auth::csrfVerify($_POST['csrf'] ?? '')
) {
    $pid          = (int)($_POST['pedido_id'] ?? 0);
    $guia         = trim($_POST['guia_envio'] ?? '');
    $transportadora = trim($_POST['transportadora'] ?? '');

    try {
        $pedido = $db->prepare("SELECT estado FROM tienda_pedidos WHERE id=?");
        $pedido->execute([$pid]);
        $row = $pedido->fetch(PDO::FETCH_ASSOC);

        if (!$row) throw new RuntimeException('Pedido no encontrado.');

        $db->prepare("UPDATE tienda_pedidos
            SET estado='despachado', guia_envio=?, transportadora=?, actualizado_at=NOW()
            WHERE id=?")
           ->execute([$guia ?: null, $transportadora ?: null, $pid]);

        $nota = 'Despachado' . ($transportadora ? " vía $transportadora" : '') . ($guia ? " — guía $guia" : '');
        $db->prepare("INSERT INTO tienda_pedidos_historial
            (pedido_id, estado_ant, estado_nuevo, nota, usuario_id)
            VALUES (?, ?, 'despachado', ?, ?)")
           ->execute([$pid, $row['estado'], $nota, $userId]);

        header('Location: ' . APP_URL . '/modules/alistamiento/?ok=despachado');
        exit;
    } catch (Exception $e) {
        $errorAccion = $e->getMessage();
    }
}

// ── Buscar pedido ───────────────────────────────────────────────
$pedido    = null;
$historial = [];
$busqueda  = trim($_GET['q'] ?? '');
$pidGet    = (int)($_GET['pid'] ?? 0);
$okMsg     = $_GET['ok'] ?? '';

if ($busqueda !== '' || $pidGet > 0) {
    $instrucSelect = $colInstruc ? ", p.instrucciones_especiales" : ", NULL AS instrucciones_especiales";
    $sql = "SELECT p.id, p.woo_order_id, p.estado,
                   p.cliente_nombre, p.cliente_telefono, p.cliente_email,
                   p.direccion, p.ciudad,
                   p.kit_nombre, p.cantidad, p.colegio_nombre,
                   p.fecha_compra, p.guia_envio, p.transportadora
                   $instrucSelect
            FROM tienda_pedidos p
            WHERE ";

    if ($pidGet > 0) {
        $sql .= "p.id = ?";
        $param = $pidGet;
    } else {
        // Permite buscar con o sin #
        $q = ltrim($busqueda, '#');
        $sql .= "p.woo_order_id = ? OR p.id = ?";
        $param = null;
    }

    $st = $db->prepare($sql);
    if ($pidGet > 0) {
        $st->execute([$pidGet]);
    } else {
        $q = ltrim($busqueda, '# ');
        $st->execute([$q, is_numeric($q) ? (int)$q : -1]);
    }
    $pedido = $st->fetch(PDO::FETCH_ASSOC);

    if ($pedido) {
        $historial = $db->prepare("SELECT h.*, u.nombre AS usuario_nombre
            FROM tienda_pedidos_historial h
            LEFT JOIN usuarios u ON u.id = h.usuario_id
            WHERE h.pedido_id = ?
            ORDER BY h.created_at DESC
            LIMIT 10");
        $historial->execute([$pedido['id']]);
        $historial = $historial->fetchAll(PDO::FETCH_ASSOC);
    }
}

// ── Pedidos en alistamiento (cola de trabajo) ───────────────────
$instrucSelect2 = $colInstruc ? ", instrucciones_especiales" : ", NULL AS instrucciones_especiales";
$enAlistamiento = $db->query("SELECT id, woo_order_id, cliente_nombre, kit_nombre, cantidad,
    ciudad, colegio_nombre, fecha_compra $instrucSelect2
    FROM tienda_pedidos
    WHERE estado IN ('listo_produccion','en_alistamiento')
    ORDER BY FIELD(estado,'en_alistamiento','listo_produccion'), fecha_compra ASC
    LIMIT 50")->fetchAll(PDO::FETCH_ASSOC);

$ESTADO_INFO = [
    'pendiente'        => ['label' => 'Pendiente',        'color' => '#d97706'],
    'aprobado'         => ['label' => 'Aprobado',         'color' => '#2563eb'],
    'en_produccion'    => ['label' => 'En producción',    'color' => '#ea580c'],
    'listo_produccion' => ['label' => 'Listo producción', 'color' => '#16a34a'],
    'en_alistamiento'  => ['label' => 'En alistamiento',  'color' => '#7c3aed'],
    'despachado'       => ['label' => 'Despachado',       'color' => '#0891b2'],
    'entregado'        => ['label' => 'Entregado',        'color' => '#64748b'],
    'cancelado'        => ['label' => 'Cancelado',        'color' => '#dc2626'],
];

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<style>
.tr-cola{cursor:pointer}
.tr-cola:hover td{background:#f8fafc}
</style>

<!-- ── Header ────────────────────────────────────────────────── -->
<div class="d-flex align-items-center gap-2 mb-4">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-box-seam me-2" style="color:#7c3aed"></i>Alistamiento</h4>
    <div class="text-muted small">Confirma etiquetas y registra despachos.</div>
  </div>
  <a href="<?= APP_URL ?>/modules/pedidos_tienda/" class="btn btn-sm btn-light ms-auto">
    <i class="bi bi-arrow-left me-1"></i>Pedidos tienda
  </a>
</div>

<?php if (!empty($errorAccion)): ?>
  <div class="alert alert-danger py-2"><i class="bi bi-x-circle me-2"></i><?= htmlspecialchars($errorAccion) ?></div>
<?php endif; ?>
<?php if ($okMsg === 'etiqueta'): ?>
  <div class="alert alert-success py-2"><i class="bi bi-check-circle me-2"></i>Etiqueta confirmada. Pedido en alistamiento.</div>
<?php elseif ($okMsg === 'despachado'): ?>
  <div class="alert alert-success py-2"><i class="bi bi-truck me-2"></i>Pedido marcado como despachado.</div>
<?php endif; ?>

<!-- ── Cola de alistamiento — PRIMERO ───────────────────────── -->
<?php if (!empty($enAlistamiento)): ?>
<div class="section-card mb-4">
  <h6 class="fw-bold mb-3">
    <i class="bi bi-list-task me-2" style="color:#7c3aed"></i>
    Cola de alistamiento
    <span class="text-muted fw-normal small">(<?= count($enAlistamiento) ?> pedidos)</span>
  </h6>
  <div class="table-responsive">
    <table class="table table-sm mb-0" style="font-size:.82rem">
      <thead class="table-light">
        <tr>
          <th>#Orden</th>
          <th>Kit</th>
          <th style="text-align:center">Cant.</th>
          <th>Ciudad / Colegio</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($enAlistamiento as $p):
          $cant        = (int)($p['cantidad'] ?? 1);
          $borderColor = $p['estado'] === 'en_alistamiento' ? '#7c3aed' : '#16a34a';
        ?>
        <tr class="tr-cola" onclick="window.location.href='?pid=<?= $p['id'] ?>'">
          <td style="border-left:3px solid <?= $borderColor ?>;white-space:nowrap">
            <span class="fw-semibold">#<?= htmlspecialchars($p['woo_order_id']) ?></span>
            <?php if (!empty($p['instrucciones_especiales'])): ?>
            <i class="bi bi-info-circle text-warning ms-1"
               title="<?= htmlspecialchars($p['instrucciones_especiales']) ?>"
               data-bs-toggle="tooltip"></i>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($p['kit_nombre'] ?: '—') ?></td>
          <td class="text-center"><?= $cant ?></td>
          <td class="text-muted">
            <?= htmlspecialchars(implode(' / ', array_filter([$p['ciudad'], $p['colegio_nombre']]))) ?>
          </td>
          <td onclick="event.stopPropagation()">
            <a href="?pid=<?= $p['id'] ?>" class="btn btn-sm btn-outline-secondary">Ver</a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php elseif ($busqueda === '' && !$pidGet): ?>
<div class="section-card text-center text-muted py-5 mb-4">
  <i class="bi bi-box-seam fs-2 mb-2 d-block"></i>
  No hay pedidos listos para alistamiento en este momento.
</div>
<?php endif; ?>

<!-- ── Buscador — SEGUNDO ────────────────────────────────────── -->
<div class="section-card mb-4">
  <h6 class="fw-bold mb-3"><i class="bi bi-search me-2 text-primary"></i>Buscar pedido por número de orden</h6>
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-8">
      <input type="text" name="q" class="form-control"
             placeholder="# Orden WooCommerce (ej: 8110)"
             value="<?= htmlspecialchars($busqueda) ?>">
    </div>
    <div class="col-md-4">
      <button type="submit" class="btn btn-primary w-100">
        <i class="bi bi-search me-1"></i>Buscar
      </button>
    </div>
  </form>
</div>

<!-- ── Resultado de búsqueda — TERCERO ──────────────────────── -->
<?php if ($busqueda !== '' || $pidGet > 0): ?>
  <?php if (!$pedido): ?>
    <div class="alert alert-warning"><i class="bi bi-exclamation-triangle me-2"></i>No se encontró ningún pedido con ese criterio.</div>
  <?php else:
    $estadoInfo     = $ESTADO_INFO[$pedido['estado']] ?? ['label' => $pedido['estado'], 'color' => '#64748b'];
    $cantidad       = (int)($pedido['cantidad'] ?? 1);
    $puedeEtiquetar = in_array($pedido['estado'], ['listo_produccion', 'aprobado', 'en_produccion']);
    $puedeDespac    = $pedido['estado'] === 'en_alistamiento';
  ?>
  <div class="section-card mb-4">

    <!-- Cabecera del pedido -->
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-2 mb-3">
      <div>
        <h5 class="fw-bold mb-0">
          Pedido <code>#<?= htmlspecialchars($pedido['woo_order_id']) ?></code>
          <span class="badge ms-2" style="background:<?= $estadoInfo['color'] ?>;color:#fff;font-size:.7rem">
            <?= $estadoInfo['label'] ?>
          </span>
        </h5>
        <div class="text-muted small">Compra: <?= htmlspecialchars($pedido['fecha_compra']) ?></div>
      </div>

      <!-- Acciones -->
      <div class="d-flex gap-2 flex-wrap">
        <!-- Imprimir etiqueta directamente -->
        <a href="<?= APP_URL ?>/modules/pedidos_tienda/stickers.php?ids=<?= $pedido['id'] ?>"
           target="_blank" class="btn btn-sm btn-outline-danger">
          <i class="bi bi-printer me-1"></i>Etiqueta
        </a>

        <?php if ($puedeEtiquetar): ?>
        <form method="POST" style="display:inline">
          <input type="hidden" name="action"    value="confirmar_etiqueta">
          <input type="hidden" name="csrf"      value="<?= Auth::csrfToken() ?>">
          <input type="hidden" name="pedido_id" value="<?= $pedido['id'] ?>">
          <button type="submit" class="btn btn-sm"
                  style="background:#7c3aed;color:#fff;border-color:#7c3aed">
            <i class="bi bi-tag-fill me-1"></i>Confirmar etiqueta
          </button>
        </form>
        <?php endif; ?>

        <?php if ($puedeDespac): ?>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalDespacho">
          <i class="bi bi-truck me-1"></i>Marcar despachado
        </button>
        <?php endif; ?>
      </div>
    </div>

    <!-- Datos operativos -->
    <div class="row g-3">
      <div class="col-md-6">
        <div class="p-3 rounded" style="background:#f8fafc;border:1px solid #e2e8f0">
          <div class="fw-semibold small text-muted mb-2 text-uppercase" style="letter-spacing:.05em">Cliente</div>
          <div class="fw-bold"><?= htmlspecialchars($pedido['cliente_nombre']) ?></div>
          <?php if ($pedido['cliente_telefono']): ?>
          <div class="small mt-1"><i class="bi bi-telephone me-1 text-muted"></i><?= htmlspecialchars($pedido['cliente_telefono']) ?></div>
          <?php endif; ?>
          <?php if ($pedido['cliente_email']): ?>
          <div class="small"><i class="bi bi-envelope me-1 text-muted"></i><?= htmlspecialchars($pedido['cliente_email']) ?></div>
          <?php endif; ?>
          <?php if ($pedido['direccion'] || $pedido['ciudad']): ?>
          <div class="small mt-1"><i class="bi bi-geo-alt me-1 text-muted"></i>
            <?= htmlspecialchars(implode(', ', array_filter([$pedido['direccion'], $pedido['ciudad']]))) ?>
          </div>
          <?php endif; ?>
          <?php if (!empty($pedido['instrucciones_especiales'])): ?>
          <div class="mt-2 p-2 rounded" style="background:#fefce8;border:1px solid #fde68a;font-size:.82rem">
            <i class="bi bi-info-circle me-1 text-warning"></i>
            <strong>Instrucciones:</strong> <?= htmlspecialchars($pedido['instrucciones_especiales']) ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="col-md-6">
        <div class="p-3 rounded" style="background:#f8fafc;border:1px solid #e2e8f0">
          <div class="fw-semibold small text-muted mb-2 text-uppercase" style="letter-spacing:.05em">Kit y destino</div>
          <div class="fw-bold"><?= htmlspecialchars($pedido['kit_nombre'] ?: '—') ?></div>
          <div class="small mt-1">
            <span class="badge bg-secondary"><?= $cantidad ?> <?= $cantidad === 1 ? 'unidad' : 'unidades' ?></span>
            <?php if ($pedido['colegio_nombre']): ?>
            <span class="ms-1 text-muted"><i class="bi bi-building me-1"></i><?= htmlspecialchars($pedido['colegio_nombre']) ?></span>
            <?php endif; ?>
          </div>
          <?php if ($pedido['guia_envio'] || $pedido['transportadora']): ?>
          <div class="mt-2 p-2 rounded" style="background:#f0fdf4;border:1px solid #bbf7d0;font-size:.82rem">
            <i class="bi bi-truck me-1 text-success"></i>
            <?php if ($pedido['transportadora']): ?><strong><?= htmlspecialchars($pedido['transportadora']) ?></strong><?php endif; ?>
            <?php if ($pedido['guia_envio']): ?> — Guía: <code><?= htmlspecialchars($pedido['guia_envio']) ?></code><?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Historial reciente -->
    <?php if (!empty($historial)): ?>
    <div class="mt-3">
      <div class="fw-semibold small text-muted mb-2">Historial reciente</div>
      <div style="max-height:180px;overflow-y:auto">
        <?php foreach ($historial as $h): ?>
        <div class="d-flex gap-2 align-items-start mb-1" style="font-size:.79rem">
          <span class="text-muted" style="white-space:nowrap"><?= substr($h['created_at'], 0, 16) ?></span>
          <span class="text-muted">→</span>
          <span class="fw-semibold"><?= htmlspecialchars($ESTADO_INFO[$h['estado_nuevo'] ?? '']['label'] ?? ($h['estado_nuevo'] ?? '')) ?></span>
          <?php if ($h['nota']): ?>
          <span class="text-muted">— <?= htmlspecialchars($h['nota']) ?></span>
          <?php endif; ?>
          <?php if ($h['usuario_nombre']): ?>
          <span class="text-muted ms-auto"><?= htmlspecialchars($h['usuario_nombre']) ?></span>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Modal despacho -->
  <?php if ($puedeDespac): ?>
  <div class="modal fade" id="modalDespacho" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h6 class="modal-title fw-bold"><i class="bi bi-truck me-2"></i>Registrar despacho</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST">
          <div class="modal-body">
            <input type="hidden" name="action"    value="despachar">
            <input type="hidden" name="csrf"      value="<?= Auth::csrfToken() ?>">
            <input type="hidden" name="pedido_id" value="<?= $pedido['id'] ?>">
            <div class="mb-3">
              <label class="form-label fw-semibold">Transportadora</label>
              <input type="text" name="transportadora" class="form-control"
                     placeholder="ej: Coordinadora, Servientrega, TCC..."
                     value="<?= htmlspecialchars($pedido['transportadora'] ?? '') ?>">
            </div>
            <div class="mb-3">
              <label class="form-label fw-semibold">Número de guía</label>
              <input type="text" name="guia_envio" class="form-control"
                     placeholder="Número de guía o referencia de envío"
                     value="<?= htmlspecialchars($pedido['guia_envio'] ?? '') ?>">
            </div>
            <div class="form-text">Ambos campos son opcionales pero recomendados para trazabilidad.</div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-sm btn-light" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-truck me-1"></i>Confirmar despacho
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php endif; // pedido encontrado ?>
<?php endif; // búsqueda activa ?>

<script>
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function(el) {
  new bootstrap.Tooltip(el);
});
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
