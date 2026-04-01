<?php
/**
 * modules/pedidos_tienda/crear.php
 * Crear un pedido de tienda manualmente (venta presencial).
 *
 * Número de orden con prefijo MAN- para diferenciarlo de WooCommerce.
 * Una vez creado, el pedido entra al flujo normal.
 *
 * Acceso: Gerencia, Administración
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();
Auth::requirePermiso('pedidos_tienda', 'crear');

$db        = Database::get();
$pageTitle = 'Nuevo Pedido Manual';
$activeMenu= 'pedidos_tienda';
$userId    = Auth::user()['id'];
$error     = '';

// Sedes de recogida disponibles
$SEDES_RECOGIDA = ['Sede Calle 75', 'Sede Calle 134'];

// Métodos de pago presenciales únicamente
$METODOS_PAGO = [
    'Efectivo' => ['label' => 'Efectivo',              'icon' => 'bi-cash'],
    'Datafono' => ['label' => 'Datáfono / Terminal',   'icon' => 'bi-credit-card-2-front'],
];

// Detección de columnas opcionales
$colTipoDespacho    = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tienda_pedidos'
    AND COLUMN_NAME='tipo_despacho'")->fetchColumn();
$colSedeRecogida    = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tienda_pedidos'
    AND COLUMN_NAME='sede_recogida'")->fetchColumn();
$colWooPayment      = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tienda_pedidos'
    AND COLUMN_NAME='woo_payment_method'")->fetchColumn();
$colWooTotal        = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tienda_pedidos'
    AND COLUMN_NAME='woo_total'")->fetchColumn();

// ── POST: crear pedido ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::csrfVerify($_POST['csrf'] ?? '')) die('CSRF');

    $rawItems      = $_POST['items'] ?? [];
    $clienteNombre = trim($_POST['cliente_nombre'] ?? '');
    $clienteTel    = trim($_POST['cliente_telefono'] ?? '');
    $clienteEmail  = trim($_POST['cliente_email'] ?? '');
    $ciudad        = trim($_POST['ciudad'] ?? '');
    $direccion     = trim($_POST['direccion'] ?? '');
    $metodoPago    = trim($_POST['metodo_pago'] ?? '');
    $notas         = trim($_POST['notas_internas'] ?? '');
    $tipoEntrega   = in_array($_POST['tipo_entrega'] ?? '', ['envio','recogida_local'])
                        ? $_POST['tipo_entrega'] : 'envio';
    $sedeRecogida  = $tipoEntrega === 'recogida_local'
                        ? trim($_POST['sede_recogida'] ?? '') : '';
    $estadoPago    = in_array($_POST['estado_pago'] ?? '', ['pendiente','aprobado'])
                        ? $_POST['estado_pago'] : 'pendiente';

    try {
        if (!$clienteNombre) throw new Exception('El nombre del cliente es requerido.');
        if (empty($rawItems)) throw new Exception('Agrega al menos un producto a la orden.');
        if (!$metodoPago)    throw new Exception('Selecciona el método de pago.');
        if ($tipoEntrega === 'recogida_local' && !$sedeRecogida)
            throw new Exception('Selecciona la sede de recogida.');

        // ── Validar y normalizar ítems ──────────────────────────────
        $parsedItems = [];
        foreach ($rawItems as $item) {
            $kitId = (int)($item['kit_id'] ?? 0);
            if (!$kitId) continue; // ignorar filas vacías
            $cantidad   = max(1, (int)($item['cantidad'] ?? 1));
            $precioUnit = (float)str_replace(['.', ','], ['', '.'], $item['precio_unit'] ?? '0');
            $colId      = (int)($item['colegio_id'] ?? 0);
            $cursoId    = (int)($item['curso_id']   ?? 0);

            $stKit = $db->prepare("SELECT nombre FROM kits WHERE id=? AND activo=1");
            $stKit->execute([$kitId]);
            $kitRow = $stKit->fetch(PDO::FETCH_ASSOC);
            if (!$kitRow) throw new Exception("Kit ID $kitId no encontrado o inactivo.");

            $colNombre = null;
            if ($colId) {
                $stCol = $db->prepare("SELECT nombre FROM colegios WHERE id=?");
                $stCol->execute([$colId]);
                $colNombre = $stCol->fetchColumn() ?: null;
            }

            $parsedItems[] = [
                'kit_id'         => $kitId,
                'kit_nombre'     => $kitRow['nombre'],
                'colegio_id'     => $colId   ?: null,
                'colegio_nombre' => $colNombre,
                'curso_id'       => $cursoId ?: null,
                'cantidad'       => $cantidad,
                'precio_unit'    => $precioUnit,
                'subtotal'       => round($precioUnit * $cantidad, 2),
            ];
        }
        if (empty($parsedItems)) throw new Exception('Agrega al menos un producto válido.');

        $grandTotal    = array_sum(array_column($parsedItems, 'subtotal'));
        $totalCantidad = array_sum(array_column($parsedItems, 'cantidad'));

        // Campos legacy de tienda_pedidos (compatibilidad con resto del sistema)
        $primerItem   = $parsedItems[0];
        $kitNombreRes = count($parsedItems) === 1
            ? $primerItem['kit_nombre']
            : count($parsedItems) . ' productos';
        $colIdRes     = count($parsedItems) === 1 ? ($primerItem['colegio_id']     ?? null) : null;
        $colNomRes    = count($parsedItems) === 1 ? ($primerItem['colegio_nombre'] ?? null) : null;

        $db->beginTransaction();

        $dirGuardar    = $tipoEntrega === 'envio' ? ($direccion ?: null) : null;
        $ciudadGuardar = $tipoEntrega === 'envio' ? ($ciudad    ?: null) : null;
        $tmpId = 'MAN-TMP-' . bin2hex(random_bytes(5));

        $insertCols   = ['woo_order_id','estado','cliente_nombre','cliente_telefono','cliente_email',
                         'direccion','ciudad','colegio_nombre','colegio_id',
                         'kit_nombre','cantidad','fecha_compra','notas_internas',
                         'creado_desde_csv','created_at','updated_at'];
        $insertVals   = ['?',"'pendiente'",'?','?','?','?','?','?','?','?','?','CURDATE()','?','0','NOW()','NOW()'];
        $insertParams = [$tmpId, $clienteNombre, $clienteTel ?: null, $clienteEmail ?: null,
                         $dirGuardar, $ciudadGuardar, $colNomRes, $colIdRes,
                         $kitNombreRes, $totalCantidad, $notas ?: null];

        if ($colWooPayment) {
            $insertCols[]   = 'woo_payment_method';
            $insertVals[]   = '?';
            $insertParams[] = $metodoPago ?: null;
        }
        if ($colWooTotal) {
            $insertCols[]   = 'woo_total';
            $insertVals[]   = '?';
            $insertParams[] = $grandTotal > 0 ? $grandTotal : null;
        }

        $sql = 'INSERT INTO tienda_pedidos (' . implode(',', $insertCols) . ') VALUES (' . implode(',', $insertVals) . ')';
        $db->prepare($sql)->execute($insertParams);

        $newId   = (int)$db->lastInsertId();
        $ordenId = 'MAN-' . $newId;

        $db->prepare("UPDATE tienda_pedidos SET woo_order_id=?, numero_pedido=? WHERE id=?")
           ->execute([$ordenId, $ordenId, $newId]);

        if ($colTipoDespacho) {
            $sqlTipo = "UPDATE tienda_pedidos SET tipo_despacho=?"
                . ($colSedeRecogida ? ", sede_recogida=?" : "")
                . " WHERE id=?";
            $params = $colSedeRecogida
                ? [$tipoEntrega, ($sedeRecogida ?: null), $newId]
                : [$tipoEntrega, $newId];
            $db->prepare($sqlTipo)->execute($params);
        }

        // ── Insertar ítems (migration_v3.6.sql) ────────────────────
        $tblItemsOk = $db->query("SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tienda_pedido_items'")->fetchColumn();
        if ($tblItemsOk) {
            $stItem = $db->prepare("INSERT INTO tienda_pedido_items
                (pedido_id,kit_id,kit_nombre,colegio_id,colegio_nombre,curso_id,cantidad,precio_unit,subtotal)
                VALUES (?,?,?,?,?,?,?,?,?)");
            foreach ($parsedItems as $it) {
                $stItem->execute([
                    $newId, $it['kit_id'], $it['kit_nombre'],
                    $it['colegio_id'], $it['colegio_nombre'], $it['curso_id'],
                    $it['cantidad'], $it['precio_unit'], $it['subtotal'],
                ]);
            }
        }

        // ── Historial ───────────────────────────────────────────────
        $notaEntrega  = $tipoEntrega === 'recogida_local'
            ? 'Recogida local — ' . $sedeRecogida
            : ($dirGuardar ? 'Envío a ' . $dirGuardar . ($ciudadGuardar ? ', ' . $ciudadGuardar : '') : 'Envío');
        $metodoLabel  = $METODOS_PAGO[$metodoPago]['label'] ?? $metodoPago;
        $notaCreacion = 'Pedido manual — ' . $metodoLabel . ' — ' . $notaEntrega;
        $db->prepare("INSERT INTO tienda_pedidos_historial
            (pedido_id, estado_ant, estado_nuevo, nota, usuario_id) VALUES (?,NULL,'pendiente',?,?)")
           ->execute([$newId, $notaCreacion, $userId]);

        // ── Aprobación inmediata ────────────────────────────────────
        if ($estadoPago === 'aprobado') {
            $db->prepare("UPDATE tienda_pedidos
                SET estado='aprobado', aprobado_por=?, aprobado_at=NOW(), updated_at=NOW()
                WHERE id=?")
               ->execute([$userId, $newId]);
            $db->prepare("INSERT INTO tienda_pedidos_historial
                (pedido_id, estado_ant, estado_nuevo, nota, usuario_id)
                VALUES (?,'pendiente','aprobado','Aprobado al crear — pago confirmado en tienda',?)")
               ->execute([$newId, $userId]);

            foreach ($parsedItems as $it) {
                crearSolicitudProduccion($db, [
                    'pedido_id'      => $newId,
                    'kit_nombre'     => $it['kit_nombre'],
                    'cantidad'       => $it['cantidad'],
                    'usuario_id'     => $userId,
                    'colegio_id'     => $it['colegio_id'],
                    'fuente'         => 'tienda',
                    'titulo'         => $it['kit_nombre'] . ' × ' . $it['cantidad'] . ' — ' . $ordenId,
                    'historial_nota' => 'Creado desde pedido manual en tienda',
                ]);
            }
        }

        $db->commit();
        auditoria('crear_pedido_manual', 'tienda_pedidos', $newId, [], [
            'orden'         => $ordenId,
            'items'         => count($parsedItems),
            'cliente'       => $clienteNombre,
            'metodo_pago'   => $metodoPago,
            'tipo_entrega'  => $tipoEntrega,
            'sede_recogida' => $sedeRecogida ?: null,
            'total'         => $grandTotal,
            'estado'        => $estadoPago,
        ]);

        header('Location: ' . APP_URL . '/modules/pedidos_tienda/?msg=manual_ok&orden=' . urlencode($ordenId));
        exit;

    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error = $e->getMessage();
    }
}

// ── Datos para el formulario ─────────────────────────────────────

// Colegios
$colegiosList = $db->query("SELECT id, nombre FROM colegios WHERE activo=1 ORDER BY nombre")
                   ->fetchAll(PDO::FETCH_ASSOC);

// Cursos con kit asignado, agrupados por colegio para JS
$cursosRaw = $db->query(
    "SELECT id, colegio_id, nombre, grado, grupo, kit_id
     FROM cursos WHERE activo=1 ORDER BY grado+0, grado, grupo, nombre"
)->fetchAll(PDO::FETCH_ASSOC);

$cursosXColegio = [];  // [colegio_id => [{id, label, kit_id}]]
foreach ($cursosRaw as $c) {
    if ($c['grado']) {
        $label = 'Grado ' . $c['grado'] . ($c['grupo'] ? '°' . $c['grupo'] : '');
    } else {
        $label = $c['nombre'];
    }
    $cursosXColegio[(int)$c['colegio_id']][] = [
        'id'     => (int)$c['id'],
        'label'  => $label,
        'kit_id' => $c['kit_id'] ? (int)$c['kit_id'] : null,
    ];
}

// Kits: agrupados por colegio_id (0 = genéricos)
$kitsRaw = $db->query(
    "SELECT id, nombre, precio_cop, costo_cop, colegio_id FROM kits WHERE activo=1 ORDER BY nombre"
)->fetchAll(PDO::FETCH_ASSOC);

$kitsXColegio = [];  // [colegio_id => [{id, nombre, precio}]], 0 = genérico
$kitData      = [];  // [id => {nombre, precio, costo}]  — para JS lookup
foreach ($kitsRaw as $k) {
    $key = $k['colegio_id'] ? (int)$k['colegio_id'] : 0;
    $kitsXColegio[$key][] = [
        'id'     => (int)$k['id'],
        'nombre' => $k['nombre'],
        'precio' => (float)($k['precio_cop'] ?? 0),
    ];
    $kitData[(int)$k['id']] = [
        'nombre' => $k['nombre'],
        'precio' => (float)($k['precio_cop'] ?? 0),
        'costo'  => (float)($k['costo_cop']  ?? 0),
    ];
}

// Valores del POST para repoblar en caso de error
$postItems   = !empty($_POST['items']) ? $_POST['items'] : [[]];
$postMetodo  = $_POST['metodo_pago']  ?? '';
$postEntrega = $_POST['tipo_entrega'] ?? 'envio';
$postEstado  = $_POST['estado_pago']  ?? 'pendiente';

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
.form-section{background:#fff;border:1px solid #e2e8f0;border-radius:12px;padding:1.2rem 1.4rem;margin-bottom:1rem}
.form-section-title{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;
                    color:#94a3b8;margin-bottom:.9rem;padding-bottom:.5rem;border-bottom:1px solid #f1f5f9}
.metodo-card{border:2px solid #e2e8f0;border-radius:10px;padding:.6rem .9rem;cursor:pointer;
             transition:.12s;display:flex;align-items:center;gap:.6rem;font-size:.83rem}
.metodo-card:hover{border-color:#94a3b8;background:#f8fafc}
.metodo-card.selected{border-color:#185FA5;background:#eff6ff;color:#185FA5;font-weight:600}
.precio-display{font-size:1.5rem;font-weight:800;color:#185FA5;font-variant-numeric:tabular-nums}
.badge-manual{background:#f59e0b;color:#fff;font-size:.68rem;padding:.2rem .55rem;border-radius:20px;font-weight:700}
.entrega-opt{border:2px solid #e2e8f0;border-radius:10px;padding:.6rem .9rem;cursor:pointer;
             transition:.12s;display:flex;align-items:center;gap:.6rem;font-size:.83rem}
.entrega-opt:hover{border-color:#94a3b8}
.entrega-opt.selected{border-color:#185FA5;background:#eff6ff;color:#185FA5;font-weight:600}
.paso-badge{display:inline-flex;align-items:center;justify-content:center;
            width:1.4rem;height:1.4rem;border-radius:50%;background:#185FA5;
            color:#fff;font-size:.65rem;font-weight:700;flex-shrink:0}
</style>

<!-- Cabecera -->
<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h4 class="fw-bold mb-0">
      <i class="bi bi-bag-plus me-2" style="color:#185FA5"></i>Nuevo Pedido Manual
      <span class="badge-manual ms-2">MAN</span>
    </h4>
    <div class="text-muted small">Venta presencial — el número de orden se asigna automáticamente</div>
  </div>
  <a href="<?= APP_URL ?>/modules/pedidos_tienda/" class="btn btn-sm btn-light">
    <i class="bi bi-arrow-left me-1"></i>Volver
  </a>
</div>

<?php if ($error): ?>
<div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST" id="form-manual">
<input type="hidden" name="csrf" value="<?= Auth::csrfToken() ?>">

<div class="row g-3">

  <!-- ── Columna izquierda ── -->
  <div class="col-lg-7">

    <!-- 1. Cliente -->
    <div class="form-section">
      <div class="form-section-title">
        <span class="paso-badge me-1">1</span> Datos del cliente
      </div>
      <div class="mb-3">
        <label class="form-label small fw-semibold">Nombre completo <span class="text-danger">*</span></label>
        <input type="text" name="cliente_nombre" class="form-control" required
               placeholder="Nombre del comprador"
               value="<?= htmlspecialchars($_POST['cliente_nombre'] ?? '') ?>">
      </div>
      <div class="row g-2">
        <div class="col-sm-6">
          <label class="form-label small fw-semibold">Teléfono</label>
          <input type="tel" name="cliente_telefono" class="form-control"
                 placeholder="Cel o fijo"
                 value="<?= htmlspecialchars($_POST['cliente_telefono'] ?? '') ?>">
        </div>
        <div class="col-sm-6">
          <label class="form-label small fw-semibold">Email</label>
          <input type="email" name="cliente_email" class="form-control"
                 placeholder="correo@ejemplo.com"
                 value="<?= htmlspecialchars($_POST['cliente_email'] ?? '') ?>">
        </div>
      </div>
    </div>

    <!-- 2. Productos (multi-ítem) -->
    <div class="form-section">
      <div class="form-section-title d-flex justify-content-between align-items-center">
        <span><span class="paso-badge me-1">2</span> Productos</span>
        <span id="items-count-badge" class="badge bg-primary bg-opacity-10 text-primary" style="font-size:.7rem">0 ítems · $0</span>
      </div>

      <div id="items-container"></div>

      <button type="button" class="btn btn-outline-primary btn-sm w-100 mt-2"
              onclick="addItem()">
        <i class="bi bi-plus-circle me-1"></i>Agregar producto
      </button>
    </div>

    <!-- 3. Entrega -->
    <div class="form-section">
      <div class="form-section-title">
        <span class="paso-badge me-1">3</span> Tipo de entrega
      </div>
      <input type="hidden" name="tipo_entrega" id="inp-tipo-entrega" value="<?= htmlspecialchars($postEntrega) ?>">
      <div class="d-flex gap-2 mb-3">
        <div class="entrega-opt flex-fill <?= $postEntrega !== 'recogida_local' ? 'selected' : '' ?>"
             onclick="selEntrega('envio', this)">
          <i class="bi bi-truck" style="font-size:1rem"></i>
          <div>
            <div class="fw-semibold small">Envío a domicilio</div>
            <div style="font-size:.7rem;color:#64748b">Se envía a una dirección</div>
          </div>
        </div>
        <div class="entrega-opt flex-fill <?= $postEntrega === 'recogida_local' ? 'selected' : '' ?>"
             onclick="selEntrega('recogida_local', this)">
          <i class="bi bi-shop" style="font-size:1rem"></i>
          <div>
            <div class="fw-semibold small">Recogida local</div>
            <div style="font-size:.7rem;color:#64748b">El cliente recoge en sede</div>
          </div>
        </div>
      </div>

      <!-- Envío a domicilio -->
      <div id="bloque-envio" style="<?= $postEntrega === 'recogida_local' ? 'display:none' : '' ?>">
        <div class="row g-2">
          <div class="col-sm-6">
            <label class="form-label small fw-semibold">Ciudad</label>
            <input type="text" name="ciudad" class="form-control" placeholder="ej: Bogotá"
                   value="<?= htmlspecialchars($_POST['ciudad'] ?? '') ?>">
          </div>
          <div class="col-sm-6">
            <label class="form-label small fw-semibold">Dirección de entrega</label>
            <input type="text" name="direccion" class="form-control"
                   placeholder="Calle / Carrera / Avenida..."
                   value="<?= htmlspecialchars($_POST['direccion'] ?? '') ?>">
          </div>
        </div>
      </div>

      <!-- Recogida local -->
      <div id="bloque-recogida" style="<?= $postEntrega === 'recogida_local' ? '' : 'display:none' ?>">
        <label class="form-label small fw-semibold">Sede de recogida <span class="text-danger">*</span></label>
        <select name="sede_recogida" id="sel-sede" class="form-select">
          <option value="">— Seleccionar sede —</option>
          <?php foreach ($SEDES_RECOGIDA as $sede): ?>
          <option value="<?= htmlspecialchars($sede) ?>"
                  <?= ($_POST['sede_recogida'] ?? '') === $sede ? 'selected' : '' ?>>
            <?= htmlspecialchars($sede) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <!-- Notas -->
    <div class="form-section">
      <div class="form-section-title"><i class="bi bi-sticky me-1"></i>Notas internas</div>
      <textarea name="notas_internas" class="form-control" rows="2"
                placeholder="Cualquier detalle relevante del pedido..."><?= htmlspecialchars($_POST['notas_internas'] ?? '') ?></textarea>
    </div>
  </div>

  <!-- ── Columna derecha ── -->
  <div class="col-lg-5">

    <!-- Método de pago -->
    <div class="form-section">
      <div class="form-section-title"><i class="bi bi-cash-coin me-1"></i>Método de pago <span class="text-danger">*</span></div>
      <input type="hidden" name="metodo_pago" id="inp-metodo" value="<?= htmlspecialchars($postMetodo) ?>">
      <?php foreach ($METODOS_PAGO as $val => $info): ?>
      <div class="metodo-card mb-2 <?= $postMetodo === $val ? 'selected' : '' ?>"
           onclick="selMetodo('<?= $val ?>', this)">
        <i class="bi <?= $info['icon'] ?>" style="font-size:1rem"></i>
        <span><?= htmlspecialchars($info['label']) ?></span>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Estado de pago -->
    <div class="form-section">
      <div class="form-section-title"><i class="bi bi-check2-circle me-1"></i>Estado del pago</div>
      <div class="d-flex flex-column gap-2">

        <label class="metodo-card <?= $postEstado === 'pendiente' ? 'selected' : '' ?>"
               style="cursor:pointer" onclick="selEstado('pendiente', this)">
          <input type="radio" name="estado_pago" value="pendiente" class="d-none"
                 <?= $postEstado === 'pendiente' ? 'checked' : '' ?>>
          <i class="bi bi-hourglass" style="color:#f59e0b;font-size:1rem"></i>
          <div>
            <div class="fw-semibold small">Pendiente de pago</div>
            <div style="font-size:.71rem;color:#64748b">El cliente aún no ha pagado — pedido en espera</div>
          </div>
        </label>

        <label class="metodo-card <?= $postEstado === 'aprobado' ? 'selected' : '' ?>"
               style="cursor:pointer" onclick="selEstado('aprobado', this)">
          <input type="radio" name="estado_pago" value="aprobado" class="d-none"
                 <?= $postEstado === 'aprobado' ? 'checked' : '' ?>>
          <i class="bi bi-check-circle-fill" style="color:#16a34a;font-size:1rem"></i>
          <div>
            <div class="fw-semibold small">Pago confirmado</div>
            <div style="font-size:.71rem;color:#64748b">Pago recibido — va directo a producción</div>
          </div>
        </label>

      </div>
    </div>

    <!-- Resumen total -->
    <div class="form-section">
      <div class="form-section-title"><i class="bi bi-receipt me-1"></i>Total del pedido</div>
      <div class="precio-display" id="resumen-total">$0</div>
      <div id="resumen-items-detalle" class="text-muted mt-1" style="font-size:.72rem"></div>
    </div>

    <!-- Botón crear -->
    <button type="submit" class="btn btn-primary w-100 fw-bold py-2 mt-2">
      <i class="bi bi-bag-plus me-2"></i>Crear pedido manual
    </button>
    <div class="text-center text-muted mt-2" style="font-size:.72rem">
      <i class="bi bi-info-circle me-1"></i>
      El número de orden <strong>MAN-XXX</strong> se asigna al guardar.
    </div>

  </div>
</div>
</form>

<script>
// ── Datos PHP → JS ───────────────────────────────────────────────
var CURSOS_X_COLEGIO = <?= json_encode($cursosXColegio) ?>;
var KITS_X_COLEGIO   = <?= json_encode($kitsXColegio)   ?>;
var KIT_DATA         = <?= json_encode($kitData)         ?>;
var COLEGIOS_LIST    = <?= json_encode($colegiosList)    ?>;

var _itemSeq = 0; // contador global de ítems (no se resetea al eliminar)

// ── Helpers ──────────────────────────────────────────────────────
function _colegioOpts(selId) {
    var html = '<option value="">— Sin colegio / Genérico —</option>';
    COLEGIOS_LIST.forEach(function(c) {
        var sel = (c.id == selId) ? ' selected' : '';
        html += '<option value="' + c.id + '"' + sel + '>'
             + c.nombre.replace(/&/g,'&amp;').replace(/</g,'&lt;') + '</option>';
    });
    return html;
}

function _lastColegioId() {
    var sels = document.querySelectorAll('select[name$="[colegio_id]"]');
    return sels.length ? sels[sels.length - 1].value : '';
}

// ── Crear ítem ───────────────────────────────────────────────────
function addItem(colegioPreset) {
    var n = _itemSeq++;
    var colId = (colegioPreset !== undefined) ? colegioPreset : _lastColegioId();

    var tpl = '<div class="item-card border rounded p-2 mb-2 bg-white" id="item-row-' + n + '">'
        + '<div class="d-flex justify-content-between align-items-center mb-2">'
        + '<span class="fw-semibold small text-primary item-num"></span>'
        + '<button type="button" class="btn btn-sm btn-outline-danger py-0 px-1" onclick="removeItem(' + n + ')"><i class="bi bi-x"></i></button>'
        + '</div>'
        + '<div class="row g-2">'
        // Colegio
        + '<div class="col-12">'
        + '<label class="form-label small text-muted mb-1">Colegio</label>'
        + '<select name="items[' + n + '][colegio_id]" class="form-select form-select-sm" onchange="onItemColChange(' + n + ',this.value)">'
        + _colegioOpts(colId) + '</select></div>'
        // Curso
        + '<div class="col-12 col-sm-6" id="blq-cur-' + n + '" style="display:none">'
        + '<label class="form-label small text-muted mb-1">Curso / Grado</label>'
        + '<select name="items[' + n + '][curso_id]" id="sel-cur-' + n + '" class="form-select form-select-sm" onchange="onItemCurChange(' + n + ',this.value)">'
        + '<option value="">— Seleccionar curso —</option></select></div>'
        // Kit
        + '<div class="col-12 col-sm-6" id="blq-kit-' + n + '" style="display:none">'
        + '<label class="form-label small text-muted mb-1 d-flex gap-1 align-items-center">Kit <span class="text-danger">*</span>'
        + '<span class="fw-normal text-muted" id="kit-hint-' + n + '" style="font-size:.7rem"></span></label>'
        + '<select id="sel-kit-' + n + '" class="form-select form-select-sm" onchange="document.getElementById(\'inp-kit-' + n + '\').value=this.value;onItemKitChange(' + n + ',this.value)">'
        + '<option value="">— Seleccionar kit —</option></select>'
        + '<input type="hidden" name="items[' + n + '][kit_id]" id="inp-kit-' + n + '"></div>'
        // Cantidad + precio + subtotal
        + '<div class="col-4 col-sm-2">'
        + '<label class="form-label small text-muted mb-1">Cant.</label>'
        + '<input type="number" name="items[' + n + '][cantidad]" id="inp-cant-' + n + '" class="form-control form-control-sm" min="1" value="1" oninput="calcSub(' + n + ')" onchange="calcSub(' + n + ')"></div>'
        + '<div class="col-8 col-sm-5">'
        + '<label class="form-label small text-muted mb-1">Precio unitario</label>'
        + '<div class="input-group input-group-sm"><span class="input-group-text">$</span>'
        + '<input type="text" name="items[' + n + '][precio_unit]" id="inp-prc-' + n + '" class="form-control" placeholder="0" oninput="calcSub(' + n + ')"></div>'
        + '<div id="warn-prc-' + n + '" class="form-text text-warning" style="display:none"><i class="bi bi-exclamation-triangle-fill"></i> Usando costo — define precio en el kit</div></div>'
        + '<div class="col-12 col-sm-5 d-flex flex-column justify-content-end">'
        + '<label class="form-label small text-muted mb-1">Subtotal</label>'
        + '<div class="fw-bold text-success fs-6" id="sub-disp-' + n + '">$0</div>'
        + '<input type="hidden" name="items[' + n + '][subtotal]" id="inp-sub-' + n + '" value="0"></div>'
        + '</div></div>';

    document.getElementById('items-container').insertAdjacentHTML('beforeend', tpl);
    _renumberItems();
    if (colId) onItemColChange(n, colId);
}

function removeItem(n) {
    var row = document.getElementById('item-row-' + n);
    if (row) {
        if (document.querySelectorAll('.item-card').length <= 1) { return; } // mínimo 1
        row.remove();
    }
    _renumberItems();
    calcGrandTotal();
}

function _renumberItems() {
    document.querySelectorAll('.item-card .item-num').forEach(function(el, i) {
        el.textContent = 'Ítem ' + (i + 1);
    });
}

// ── Embudo por ítem ──────────────────────────────────────────────
function onItemColChange(n, colId) {
    var selCur = document.getElementById('sel-cur-' + n);
    var blqCur = document.getElementById('blq-cur-' + n);
    var blqKit = document.getElementById('blq-kit-' + n);
    var selKit = document.getElementById('sel-kit-' + n);

    selCur.innerHTML = '<option value="">— Seleccionar curso —</option>';
    selKit.innerHTML = '<option value="">— Seleccionar kit —</option>';
    selKit.disabled  = false;
    document.getElementById('inp-kit-' + n).value  = '';
    document.getElementById('inp-prc-' + n).value  = '';
    document.getElementById('inp-sub-' + n).value  = 0;
    document.getElementById('sub-disp-' + n).textContent = '$0';
    document.getElementById('warn-prc-' + n).style.display = 'none';
    blqKit.style.display = 'none';
    calcGrandTotal();

    if (!colId) { blqCur.style.display = 'none'; return; }

    var cursos = CURSOS_X_COLEGIO[colId] || [];
    if (cursos.length) {
        cursos.forEach(function(c) {
            var o = document.createElement('option');
            o.value = c.id; o.textContent = c.label; o.dataset.kitId = c.kit_id || '';
            selCur.appendChild(o);
        });
        blqCur.style.display = '';
    } else {
        blqCur.style.display = 'none';
    }
    blqKit.style.display = '';
    _poblarKitsItem(n, colId, null, false);
}

function onItemCurChange(n, cursoId) {
    var selCur   = document.getElementById('sel-cur-' + n);
    var opt      = selCur.options[selCur.selectedIndex];
    var kitId    = opt ? opt.dataset.kitId : '';
    var selCol   = document.querySelector('select[name="items[' + n + '][colegio_id]"]');
    var colId    = selCol ? selCol.value : '';

    if (kitId) {
        _poblarKitsItem(n, colId, parseInt(kitId), true);
        var sk = document.getElementById('sel-kit-' + n);
        sk.value = kitId; sk.disabled = true;
        document.getElementById('inp-kit-' + n).value = kitId;
        document.getElementById('kit-hint-' + n).textContent = '(kit del curso)';
        onItemKitChange(n, kitId);
    } else {
        _poblarKitsItem(n, colId, null, false);
        document.getElementById('sel-kit-' + n).disabled = false;
        document.getElementById('inp-kit-' + n).value = '';
        document.getElementById('kit-hint-' + n).textContent = '(selecciona manualmente)';
    }
}

function _poblarKitsItem(n, colId, preselId, solo) {
    var sel = document.getElementById('sel-kit-' + n);
    sel.innerHTML = '<option value="">— Seleccionar kit —</option>';
    var kits;
    if (solo && preselId) {
        var all = (KITS_X_COLEGIO[colId] || []).concat(KITS_X_COLEGIO[0] || []);
        kits = all.filter(function(k){ return k.id === preselId; });
        if (!kits.length && KIT_DATA[preselId])
            kits = [{ id: preselId, nombre: KIT_DATA[preselId].nombre, precio: KIT_DATA[preselId].precio }];
    } else {
        kits = (KITS_X_COLEGIO[colId] || []).concat(KITS_X_COLEGIO[0] || []);
    }
    kits.forEach(function(k) {
        var o = document.createElement('option');
        o.value = k.id;
        o.textContent = k.nombre + (k.precio > 0 ? ' — $' + k.precio.toLocaleString('es-CO') : '');
        if (preselId && k.id === preselId) o.selected = true;
        sel.appendChild(o);
    });
}

function onItemKitChange(n, kitId) {
    document.getElementById('blq-kit-' + n).style.display = '';
    if (!kitId || !KIT_DATA[kitId]) {
        document.getElementById('inp-prc-' + n).value = '';
        document.getElementById('warn-prc-' + n).style.display = 'none';
        calcSub(n); return;
    }
    var kit = KIT_DATA[kitId];
    var inp = document.getElementById('inp-prc-' + n);
    var wrn = document.getElementById('warn-prc-' + n);
    if (kit.precio > 0) {
        inp.value = kit.precio; inp.classList.remove('is-invalid'); wrn.style.display = 'none';
    } else if (kit.costo > 0) {
        inp.value = kit.costo;  inp.classList.add('is-invalid');    wrn.style.display = '';
    } else {
        inp.value = 0;          inp.classList.add('is-invalid');    wrn.style.display = '';
    }
    calcSub(n);
}

function calcSub(n) {
    var prc = parseFloat(String(document.getElementById('inp-prc-' + n).value || '0').replace(/\./g,'').replace(',','.')) || 0;
    var qty = parseInt(document.getElementById('inp-cant-' + n).value) || 1;
    var sub = prc * qty;
    document.getElementById('inp-sub-' + n).value = sub.toFixed(0);
    document.getElementById('sub-disp-' + n).textContent = '$' + sub.toLocaleString('es-CO');
    calcGrandTotal();
}

function calcGrandTotal() {
    var total = 0, nItems = 0, names = [];
    document.querySelectorAll('input[id^="inp-sub-"]').forEach(function(inp) {
        var v = parseFloat(inp.value) || 0;
        total += v; nItems++;
    });
    document.querySelectorAll('input[id^="inp-kit-"]').forEach(function(inp) {
        if (inp.value && KIT_DATA[inp.value]) names.push(KIT_DATA[inp.value].nombre);
    });
    var fmt = '$' + total.toLocaleString('es-CO');
    document.getElementById('resumen-total').textContent = fmt;
    document.getElementById('items-count-badge').textContent =
        nItems + (nItems === 1 ? ' ítem' : ' ítems') + ' · ' + fmt;
    document.getElementById('resumen-items-detalle').textContent =
        names.length ? names.join(' · ') : '';
}

// ── Entrega ──────────────────────────────────────────────────────
function selEntrega(tipo, el) {
    document.querySelectorAll('.entrega-opt').forEach(function(e){ e.classList.remove('selected'); });
    el.classList.add('selected');
    document.getElementById('inp-tipo-entrega').value = tipo;
    document.getElementById('bloque-envio').style.display    = tipo === 'envio'          ? '' : 'none';
    document.getElementById('bloque-recogida').style.display = tipo === 'recogida_local' ? '' : 'none';
    document.getElementById('sel-sede').required = tipo === 'recogida_local';
}

function selMetodo(val, el) {
    document.querySelectorAll('.metodo-card[onclick^="selMetodo"]').forEach(function(c){ c.classList.remove('selected'); });
    el.classList.add('selected');
    document.getElementById('inp-metodo').value = val;
}

function selEstado(val, el) {
    document.querySelectorAll('.metodo-card[onclick^="selEstado"]').forEach(function(c){ c.classList.remove('selected'); });
    el.classList.add('selected');
    el.querySelector('input[type=radio]').checked = true;
}

// ── Init ─────────────────────────────────────────────────────────
(function init() {
    addItem(); // primer ítem vacío
    document.getElementById('sel-sede').required =
        document.getElementById('inp-tipo-entrega').value === 'recogida_local';
})();
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
