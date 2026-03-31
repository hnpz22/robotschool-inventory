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
$colTipoDespacho = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tienda_pedidos'
    AND COLUMN_NAME='tipo_despacho'")->fetchColumn();
$colSedeRecogida = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tienda_pedidos'
    AND COLUMN_NAME='sede_recogida'")->fetchColumn();

// ── POST: crear pedido ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::csrfVerify($_POST['csrf'] ?? '')) die('CSRF');

    $kitId         = (int)($_POST['kit_id'] ?? 0);
    $colegioId     = (int)($_POST['colegio_id'] ?? 0);
    $cursoId       = (int)($_POST['curso_id'] ?? 0);
    $cantidad      = max(1, (int)($_POST['cantidad'] ?? 1));
    $precioUnit    = (float)str_replace(['.', ','], ['', '.'], $_POST['precio_unitario'] ?? '0');
    $total         = (float)str_replace(['.', ','], ['', '.'], $_POST['total'] ?? '0');
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
    // Estado de pago: 'pendiente' = pendiente de pago, 'aprobado' = pago confirmado → va a producción
    $estadoPago    = in_array($_POST['estado_pago'] ?? '', ['pendiente','aprobado'])
                        ? $_POST['estado_pago'] : 'pendiente';

    try {
        if (!$clienteNombre) throw new Exception('El nombre del cliente es requerido.');
        if (!$kitId)         throw new Exception('Selecciona un kit.');
        if (!$metodoPago)    throw new Exception('Selecciona el método de pago.');
        if ($tipoEntrega === 'recogida_local' && !$sedeRecogida)
            throw new Exception('Selecciona la sede de recogida.');

        // Datos del kit
        $kitRow = $db->prepare("SELECT nombre, precio_cop FROM kits WHERE id=? AND activo=1");
        $kitRow->execute([$kitId]);
        $kit = $kitRow->fetch(PDO::FETCH_ASSOC);
        if (!$kit) throw new Exception('Kit no encontrado o inactivo.');
        $kitNombre = $kit['nombre'];

        // Nombre del colegio
        $colegioNombre = null;
        if ($colegioId) {
            $colegioNombre = $db->prepare("SELECT nombre FROM colegios WHERE id=?");
            $colegioNombre->execute([$colegioId]);
            $colegioNombre = $colegioNombre->fetchColumn() ?: null;
        }

        $db->beginTransaction();

        // Dirección: vacía si es recogida local
        $dirGuardar    = $tipoEntrega === 'envio' ? ($direccion ?: null) : null;
        $ciudadGuardar = $tipoEntrega === 'envio' ? ($ciudad    ?: null) : null;

        // Insertar con woo_order_id temporal único
        $tmpId = 'MAN-TMP-' . bin2hex(random_bytes(5));
        $db->prepare("INSERT INTO tienda_pedidos
            (woo_order_id, estado, woo_payment_method, woo_total,
             cliente_nombre, cliente_telefono, cliente_email,
             direccion, ciudad, colegio_nombre, colegio_id,
             kit_nombre, cantidad, fecha_compra, notas_internas,
             creado_desde_csv, created_at, updated_at)
            VALUES (?, 'pendiente', ?, ?,
                    ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, CURDATE(), ?,
                    0, NOW(), NOW())")
           ->execute([
               $tmpId,
               $metodoPago ?: null,
               $total > 0 ? $total : null,
               $clienteNombre,
               $clienteTel   ?: null,
               $clienteEmail ?: null,
               $dirGuardar,
               $ciudadGuardar,
               $colegioNombre,
               $colegioId    ?: null,
               $kitNombre,
               $cantidad,
               $notas        ?: null,
           ]);

        $newId   = (int)$db->lastInsertId();
        $ordenId = 'MAN-' . $newId;

        // Actualizar con el ID definitivo
        $db->prepare("UPDATE tienda_pedidos SET woo_order_id=?, numero_pedido=? WHERE id=?")
           ->execute([$ordenId, $ordenId, $newId]);

        // Guardar tipo de entrega en columnas si existen
        if ($colTipoDespacho) {
            $sqlTipo = "UPDATE tienda_pedidos SET tipo_despacho=?"
                . ($colSedeRecogida ? ", sede_recogida=?" : "")
                . " WHERE id=?";
            $params = $colSedeRecogida
                ? [$tipoEntrega, ($sedeRecogida ?: null), $newId]
                : [$tipoEntrega, $newId];
            $db->prepare($sqlTipo)->execute($params);
        }

        // Nota de entrega para historial
        $notaEntrega = $tipoEntrega === 'recogida_local'
            ? 'Recogida local — ' . $sedeRecogida
            : ($dirGuardar ? 'Envío a ' . $dirGuardar . ($ciudadGuardar ? ', ' . $ciudadGuardar : '') : 'Envío');

        // Historial: creación
        $metodoLabel = $METODOS_PAGO[$metodoPago]['label'] ?? $metodoPago;
        $notaCreacion = 'Pedido manual — ' . $metodoLabel . ' — ' . $notaEntrega;
        $db->prepare("INSERT INTO tienda_pedidos_historial
            (pedido_id, estado_ant, estado_nuevo, nota, usuario_id) VALUES (?,NULL,'pendiente',?,?)")
           ->execute([$newId, $notaCreacion, $userId]);

        // Aprobación inmediata (pago confirmado)
        if ($estadoPago === 'aprobado') {
            $db->prepare("UPDATE tienda_pedidos
                SET estado='aprobado', aprobado_por=?, aprobado_at=NOW(), updated_at=NOW()
                WHERE id=?")
               ->execute([$userId, $newId]);

            $db->prepare("INSERT INTO tienda_pedidos_historial
                (pedido_id, estado_ant, estado_nuevo, nota, usuario_id)
                VALUES (?,'pendiente','aprobado','Aprobado al crear — pago confirmado en tienda',?)")
               ->execute([$newId, $userId]);

            crearSolicitudProduccion($db, [
                'pedido_id'      => $newId,
                'kit_nombre'     => $kitNombre,
                'cantidad'       => $cantidad,
                'usuario_id'     => $userId,
                'colegio_id'     => $colegioId ?: null,
                'fuente'         => 'tienda_manual',
                'titulo'         => "$kitNombre × $cantidad — $ordenId",
                'historial_nota' => 'Creado desde pedido manual en tienda',
            ]);
        }

        $db->commit();
        auditoria('crear_pedido_manual', 'tienda_pedidos', $newId, [], [
            'orden'         => $ordenId,
            'kit'           => $kitNombre,
            'cliente'       => $clienteNombre,
            'metodo_pago'   => $metodoPago,
            'tipo_entrega'  => $tipoEntrega,
            'sede_recogida' => $sedeRecogida ?: null,
            'total'         => $total,
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
    "SELECT id, nombre, precio_cop, colegio_id FROM kits WHERE activo=1 ORDER BY nombre"
)->fetchAll(PDO::FETCH_ASSOC);

$kitsXColegio = [];  // [colegio_id => [{id, nombre, precio}]], 0 = genérico
$kitData      = [];  // [id => {nombre, precio}]  — para JS lookup
foreach ($kitsRaw as $k) {
    $key = $k['colegio_id'] ? (int)$k['colegio_id'] : 0;
    $kitsXColegio[$key][] = [
        'id'     => (int)$k['id'],
        'nombre' => $k['nombre'],
        'precio' => (float)($k['precio_cop'] ?? 0),
    ];
    $kitData[(int)$k['id']] = ['nombre' => $k['nombre'], 'precio' => (float)($k['precio_cop'] ?? 0)];
}

// Valores del POST para repoblar en caso de error
$postColegioId = (int)($_POST['colegio_id'] ?? 0);
$postCursoId   = (int)($_POST['curso_id']   ?? 0);
$postKitId     = (int)($_POST['kit_id']     ?? 0);
$postMetodo    = $_POST['metodo_pago']  ?? '';
$postEntrega   = $_POST['tipo_entrega'] ?? 'envio';
$postEstado    = $_POST['estado_pago']  ?? 'pendiente';

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

    <!-- 2. Producto: embudo colegio → curso → kit -->
    <div class="form-section">
      <div class="form-section-title">
        <span class="paso-badge me-1">2</span> Producto
      </div>

      <!-- Paso A: Colegio -->
      <div class="mb-3">
        <label class="form-label small fw-semibold">
          Colegio <span class="text-muted fw-normal">(filtra los cursos disponibles)</span>
        </label>
        <select name="colegio_id" id="sel-colegio" class="form-select"
                onchange="onColegioChange(this.value)">
          <option value="">— Seleccionar colegio —</option>
          <?php foreach ($colegiosList as $col): ?>
          <option value="<?= $col['id'] ?>"
                  <?= $postColegioId == $col['id'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($col['nombre']) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Paso B: Curso/Grado (oculto hasta elegir colegio) -->
      <div class="mb-3" id="bloque-curso" style="<?= $postColegioId ? '' : 'display:none' ?>">
        <label class="form-label small fw-semibold">
          Curso / Grado <span class="text-muted fw-normal">(asigna el kit del curso automáticamente)</span>
        </label>
        <select name="curso_id" id="sel-curso" class="form-select"
                onchange="onCursoChange(this.value)">
          <option value="">— Seleccionar curso —</option>
        </select>
      </div>

      <!-- Paso C: Kit (se auto-selecciona del curso, o se elige manualmente) -->
      <div class="mb-3" id="bloque-kit" style="<?= $postColegioId ? '' : 'display:none' ?>">
        <label class="form-label small fw-semibold">
          Kit <span class="text-danger">*</span>
          <span class="text-muted fw-normal" id="kit-hint">(según el curso seleccionado)</span>
        </label>
        <select name="kit_id" id="sel-kit" class="form-select" required
                onchange="onKitChange(this.value)">
          <option value="">— Seleccionar kit —</option>
        </select>
        <input type="hidden" name="_kit_colegio_id" id="inp-kit-colegio" value="<?= $postColegioId ?>">
      </div>

      <!-- Cantidad + precio -->
      <div class="row g-2 align-items-end" id="bloque-precio" style="<?= $postKitId ? '' : 'display:none' ?>">
        <div class="col-sm-3">
          <label class="form-label small fw-semibold">Cantidad</label>
          <input type="number" name="cantidad" id="inp-cantidad" class="form-control"
                 min="1" value="<?= (int)($_POST['cantidad'] ?? 1) ?>"
                 onchange="calcTotal()" oninput="calcTotal()">
        </div>
        <div class="col-sm-4">
          <label class="form-label small fw-semibold">Precio unitario</label>
          <div class="input-group">
            <span class="input-group-text small">$</span>
            <input type="text" name="precio_unitario" id="inp-precio" class="form-control"
                   placeholder="0"
                   value="<?= htmlspecialchars($_POST['precio_unitario'] ?? '') ?>"
                   oninput="calcTotal()">
          </div>
        </div>
        <div class="col-sm-5">
          <label class="form-label small fw-semibold">Total del pedido</label>
          <div class="input-group">
            <span class="input-group-text small fw-bold">$</span>
            <input type="text" name="total" id="inp-total" class="form-control fw-bold"
                   placeholder="0"
                   value="<?= htmlspecialchars($_POST['total'] ?? '') ?>">
          </div>
          <div class="form-text">Editable si hay descuento.</div>
        </div>
      </div>
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

    <!-- Resumen precio -->
    <div class="form-section" id="box-resumen" style="display:none">
      <div class="form-section-title"><i class="bi bi-receipt me-1"></i>Resumen</div>
      <div id="resumen-kit" class="small text-muted mb-1"></div>
      <div class="precio-display" id="resumen-total">$0</div>
      <div class="text-muted" style="font-size:.72rem">Total del pedido</div>
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
// Datos cargados desde PHP
var CURSOS_X_COLEGIO = <?= json_encode($cursosXColegio) ?>;
var KITS_X_COLEGIO   = <?= json_encode($kitsXColegio)   ?>;
var KIT_DATA         = <?= json_encode($kitData)         ?>;

// ── Embudo colegio → curso → kit ─────────────────────────────────
function onColegioChange(colegioId) {
    var selCurso  = document.getElementById('sel-curso');
    var selKit    = document.getElementById('sel-kit');
    var blqCurso  = document.getElementById('bloque-curso');
    var blqKit    = document.getElementById('bloque-kit');
    var blqPrecio = document.getElementById('bloque-precio');
    var boxRes    = document.getElementById('box-resumen');

    // Limpiar selects dependientes
    selCurso.innerHTML = '<option value="">— Seleccionar curso —</option>';
    selKit.innerHTML   = '<option value="">— Seleccionar kit —</option>';
    document.getElementById('inp-precio').value = '';
    document.getElementById('inp-total').value  = '';
    blqPrecio.style.display = 'none';
    boxRes.style.display    = 'none';

    if (!colegioId) {
        blqCurso.style.display = 'none';
        blqKit.style.display   = 'none';
        return;
    }

    // Poblar cursos
    var cursos = CURSOS_X_COLEGIO[colegioId] || [];
    blqCurso.style.display = '';
    blqKit.style.display   = '';
    document.getElementById('inp-kit-colegio').value = colegioId;

    if (cursos.length > 0) {
        cursos.forEach(function(c) {
            var opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.label;
            opt.dataset.kitId = c.kit_id || '';
            selCurso.appendChild(opt);
        });
    } else {
        // No hay cursos registrados; mostrar kits del colegio directamente
        poblarKits(colegioId, null);
        document.getElementById('kit-hint').textContent = '';
        blqCurso.style.display = 'none';
    }

    poblarKits(colegioId, null);
}

function onCursoChange(cursoId) {
    var selCurso = document.getElementById('sel-curso');
    var opt      = selCurso.options[selCurso.selectedIndex];
    var kitId    = opt ? opt.dataset.kitId : '';
    var colegioId = document.getElementById('inp-kit-colegio').value;

    // Poblar kits filtrados por colegio
    poblarKits(colegioId, kitId ? parseInt(kitId) : null);

    if (kitId) {
        // Auto-seleccionar kit del curso
        document.getElementById('sel-kit').value = kitId;
        onKitChange(kitId);
        document.getElementById('kit-hint').textContent = '(kit del curso seleccionado)';
    } else {
        document.getElementById('kit-hint').textContent = '(sin kit asignado — selecciona manualmente)';
    }
}

function poblarKits(colegioId, preselId) {
    var selKit = document.getElementById('sel-kit');
    selKit.innerHTML = '<option value="">— Seleccionar kit —</option>';

    // Kits del colegio + genéricos
    var kits = (KITS_X_COLEGIO[colegioId] || []).concat(KITS_X_COLEGIO[0] || []);
    kits.forEach(function(k) {
        var opt = document.createElement('option');
        opt.value = k.id;
        opt.textContent = k.nombre + (k.precio > 0 ? ' — $' + k.precio.toLocaleString('es-CO') : '');
        if (preselId && k.id === preselId) opt.selected = true;
        selKit.appendChild(opt);
    });
}

function onKitChange(kitId) {
    var blqPrecio = document.getElementById('bloque-precio');
    var boxRes    = document.getElementById('box-resumen');
    if (!kitId || !KIT_DATA[kitId]) {
        blqPrecio.style.display = 'none';
        boxRes.style.display    = 'none';
        return;
    }
    var precio = KIT_DATA[kitId].precio;
    document.getElementById('inp-precio').value = precio > 0 ? precio : '';
    calcTotal();
    blqPrecio.style.display = '';
    boxRes.style.display    = '';
    document.getElementById('resumen-kit').textContent = KIT_DATA[kitId].nombre;
}

function calcTotal() {
    var precio = parseFloat(document.getElementById('inp-precio').value.replace(/\./g,'').replace(',','.')) || 0;
    var cant   = parseInt(document.getElementById('inp-cantidad').value) || 1;
    var total  = precio * cant;
    document.getElementById('inp-total').value = total > 0 ? total.toFixed(0) : '';
    document.getElementById('resumen-total').textContent = total > 0
        ? '$' + total.toLocaleString('es-CO') : '$0';
}

// ── Entrega ──────────────────────────────────────────────────────
function selEntrega(tipo, el) {
    document.querySelectorAll('.entrega-opt').forEach(function(e){ e.classList.remove('selected'); });
    el.classList.add('selected');
    document.getElementById('inp-tipo-entrega').value = tipo;
    document.getElementById('bloque-envio').style.display    = tipo === 'envio'          ? '' : 'none';
    document.getElementById('bloque-recogida').style.display = tipo === 'recogida_local' ? '' : 'none';
    // Sede required solo en recogida
    document.getElementById('sel-sede').required = tipo === 'recogida_local';
}

// ── Método de pago ───────────────────────────────────────────────
function selMetodo(val, el) {
    document.querySelectorAll('.metodo-card[onclick^="selMetodo"]').forEach(function(c){ c.classList.remove('selected'); });
    el.classList.add('selected');
    document.getElementById('inp-metodo').value = val;
}

// ── Estado de pago ───────────────────────────────────────────────
function selEstado(val, el) {
    document.querySelectorAll('.metodo-card[onclick^="selEstado"]').forEach(function(c){ c.classList.remove('selected'); });
    el.classList.add('selected');
    el.querySelector('input[type=radio]').checked = true;
}

// ── Inicialización (repoblar si hay error POST) ──────────────────
(function init() {
    var colegioId = <?= $postColegioId ?>;
    var cursoId   = <?= $postCursoId   ?>;
    var kitId     = <?= $postKitId     ?>;

    if (colegioId) {
        // Disparar manualmente (no llama onchange para no limpiar valores ya puestos)
        var selCurso  = document.getElementById('sel-curso');
        var selKit    = document.getElementById('sel-kit');
        document.getElementById('bloque-curso').style.display = '';
        document.getElementById('bloque-kit').style.display   = '';

        var cursos = CURSOS_X_COLEGIO[colegioId] || [];
        cursos.forEach(function(c) {
            var opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = c.label;
            opt.dataset.kitId = c.kit_id || '';
            if (c.id === cursoId) opt.selected = true;
            selCurso.appendChild(opt);
        });

        poblarKits(colegioId, kitId || null);
        if (kitId) {
            selKit.value = kitId;
            document.getElementById('bloque-precio').style.display = '';
            document.getElementById('box-resumen').style.display   = '';
            if (KIT_DATA[kitId]) {
                document.getElementById('resumen-kit').textContent = KIT_DATA[kitId].nombre;
                calcTotal();
            }
        }
    }

    // Init entrega
    var tipoEntrega = document.getElementById('inp-tipo-entrega').value;
    document.getElementById('sel-sede').required = tipoEntrega === 'recogida_local';
})();
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
