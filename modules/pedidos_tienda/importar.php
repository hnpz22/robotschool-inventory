<?php
/**
 * modules/pedidos_tienda/importar.php
 * Importación CSV de pedidos WooCommerce.
 *
 * Reglas:
 *  - Solo importa pedidos con estado "procesando" / "processing"
 *  - Si woo_order_id ya existe → SKIP sin tocar nada
 *  - Muestra tabla resumen con resultado de cada fila
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();
Auth::requirePermiso('pedidos_tienda', 'crear');

$db         = Database::get();
$pageTitle  = 'Importar Pedidos CSV';
$activeMenu = 'pedidos_tienda';

// ── Detectar columnas opcionales (dependen de que la migración 002 haya corrido) ──
$colCantidad  = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tienda_pedidos'
    AND COLUMN_NAME='cantidad'")->fetchColumn();
$colInstruc   = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tienda_pedidos'
    AND COLUMN_NAME='instrucciones_especiales'")->fetchColumn();

// ── Helpers locales ──
function tp_fecha_iso(string $f): string {
    if (preg_match('#(\d{2})/(\d{2})/(\d{4})#', $f, $m)) return "{$m[3]}-{$m[1]}-{$m[2]}";
    if (preg_match('#(\d{4})-(\d{2})-(\d{2})#', $f)) return $f;
    return date('Y-m-d');
}
function tp_colegio(string $cat): string {
    return strpos($cat, '>') !== false ? trim(explode('>', $cat)[1]) : '';
}
function tp_es_procesando(string $estWoo): bool {
    $e = strtolower(trim($estWoo));
    return str_contains($e, 'procesando')
        || str_contains($e, 'processing')
        || $e === 'wc-processing';
}

// ── Resultado de la importación (se llena en POST) ──
$resumen = null; // null = no se importó aún

// ── Procesar CSV ──
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'importar_csv'
    && Auth::csrfVerify($_POST['csrf'] ?? '')
) {
    $file = $_FILES['csv_file'] ?? null;
    $resumen = ['filas' => [], 'nuevos' => 0, 'omitidos' => 0, 'saltados' => 0, 'errores' => 0];

    if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $resumen['error_general'] = 'Error al subir el archivo (código ' . ($file['error'] ?? -1) . ')';
    } else {
        // Normalizar encoding
        $raw = file_get_contents($file['tmp_name']);
        $enc = mb_detect_encoding($raw, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($enc && $enc !== 'UTF-8') $raw = mb_convert_encoding($raw, 'UTF-8', $enc);
        $raw = ltrim($raw, "\xEF\xBB\xBF"); // quitar BOM

        // Detectar separador
        $firstLine = strtok($raw, "\n");
        $sep = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $lines   = str_getcsv($raw, "\n");
        $headers = array_map('trim', str_getcsv(array_shift($lines), $sep));

        // Mapa de columnas CSV → campo interno (múltiples nombres posibles)
        $map = [
            'id'          => ['ID del pedido', 'Order ID', 'id'],
            'fecha'       => ['Fecha del pedido', 'Order Date', 'Date'],
            'nombre'      => ['First Name (Billing)', 'Billing First Name', 'Nombre'],
            'apellido'    => ['Last Name (Billing)', 'Billing Last Name', 'Apellido'],
            'telefono'    => ['Phone (Billing)', 'Billing Phone', 'Teléfono'],
            'email'       => ['Email (Billing)', 'Billing Email', 'Email'],
            'ciudad'      => ['City (Billing)', 'Billing City', 'Ciudad'],
            'dir'         => ['Dirección lineas 1 y 2 (envío)', 'Billing Address 1', 'Dirección'],
            'kit'         => ['Nombre del producto (principal)', 'Line Items', 'Products'],
            'categoria'   => ['Nombres completos de las categorías', 'Category', 'Categorías'],
            'estado_woo'  => ['Estado del pedido', 'Status', 'Estado'],
            'cantidad'    => ['Item #1 Quantity', 'Quantity', 'Cantidad', 'qty'],
            'instruc'     => ['Customer Note', 'Nota del cliente', 'Order Notes', 'Customer Provided Note'],
        ];

        $idx = [];
        foreach ($map as $campo => $opts) {
            foreach ($opts as $op) {
                $pos = array_search($op, $headers);
                if ($pos !== false) { $idx[$campo] = $pos; break; }
            }
        }

        $get = function (array $cols, string $campo) use ($idx): string {
            return isset($idx[$campo]) ? trim($cols[$idx[$campo]] ?? '') : '';
        };

        // Precargar woo_order_ids ya existentes para evitar un SELECT por fila
        $existentes = $db->query("SELECT woo_order_id FROM tienda_pedidos")->fetchAll(PDO::FETCH_COLUMN);
        $existentes = array_flip($existentes); // flip para O(1) lookup

        $stInsert = null; // se prepara al primer INSERT real

        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            $cols  = str_getcsv($line, $sep);
            $wooId = trim($get($cols, 'id'), "# \t");
            if ($wooId === '') continue;

            $fila = ['woo_id' => '#' . $wooId, 'nombre' => '', 'estado_woo' => '', 'resultado' => '', 'detalle' => ''];

            try {
                $estWoo         = $get($cols, 'estado_woo');
                $fila['estado_woo'] = $estWoo;
                $fila['nombre']     = trim($get($cols, 'nombre') . ' ' . $get($cols, 'apellido')) ?: 'Sin nombre';

                // ── REGLA 1: solo "procesando" ──
                if (!tp_es_procesando($estWoo)) {
                    $fila['resultado'] = 'saltado';
                    $fila['detalle']   = 'Estado: ' . ($estWoo ?: '(vacío)');
                    $resumen['saltados']++;
                    $resumen['filas'][] = $fila;
                    continue;
                }

                // ── REGLA 2: SKIP si ya existe ──
                if (isset($existentes[$wooId])) {
                    $fila['resultado'] = 'ya_existe';
                    $fila['detalle']   = 'Ya estaba en la base de datos';
                    $resumen['omitidos']++;
                    $resumen['filas'][] = $fila;
                    continue;
                }

                // ── Preparar datos ──
                $cat     = $get($cols, 'categoria');
                $colegio = tp_colegio($cat);

                $colegioId = null;
                if ($colegio) {
                    $st = $db->prepare("SELECT id FROM colegios WHERE activo=1 AND nombre LIKE ? LIMIT 1");
                    $st->execute(['%' . $colegio . '%']);
                    $colegioId = $st->fetchColumn() ?: null;
                }

                $data = [
                    'woo_order_id'    => $wooId,
                    'estado'          => 'pendiente',
                    'cliente_nombre'  => $fila['nombre'],
                    'cliente_telefono'=> $get($cols, 'telefono'),
                    'cliente_email'   => $get($cols, 'email'),
                    'direccion'       => $get($cols, 'dir'),
                    'ciudad'          => $get($cols, 'ciudad'),
                    'colegio_nombre'  => $colegio,
                    'colegio_id'      => $colegioId,
                    'kit_nombre'      => $get($cols, 'kit'),
                    'categoria'       => $cat,
                    'fecha_compra'    => tp_fecha_iso($get($cols, 'fecha')),
                    'creado_desde_csv'=> 1,
                ];

                if ($colCantidad) {
                    $cant = (int)$get($cols, 'cantidad');
                    $data['cantidad'] = $cant > 0 ? $cant : 1;
                }
                if ($colInstruc) {
                    $data['instrucciones_especiales'] = $get($cols, 'instruc') ?: null;
                }

                // ── INSERT ──
                $placeholders = ':' . implode(', :', array_keys($data));
                $colNames     = implode(', ', array_keys($data));
                $db->prepare("INSERT INTO tienda_pedidos ($colNames) VALUES ($placeholders)")
                   ->execute($data);

                $newId = (int)$db->lastInsertId();

                $db->prepare("INSERT INTO tienda_pedidos_historial
                    (pedido_id, estado_ant, estado_nuevo, nota, usuario_id)
                    VALUES (?, NULL, 'pendiente', 'Importado desde CSV', ?)")
                   ->execute([$newId, Auth::user()['id']]);

                // Añadir al mapa de existentes para evitar duplicados dentro del mismo CSV
                $existentes[$wooId] = true;

                $fila['resultado'] = 'importado';
                $resumen['nuevos']++;

            } catch (Exception $e) {
                $fila['resultado'] = 'error';
                $fila['detalle']   = $e->getMessage();
                $resumen['errores']++;
            }

            $resumen['filas'][] = $fila;
        }
    }
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="d-flex align-items-center gap-2 mb-4">
  <a href="<?= APP_URL ?>/modules/pedidos_tienda/" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i></a>
  <div>
    <h4 class="fw-bold mb-0">Importar Pedidos CSV</h4>
    <div class="text-muted small">Solo se importan pedidos con estado <strong>Procesando</strong>. Los duplicados se omiten sin modificar.</div>
  </div>
</div>

<?php if (isset($resumen['error_general'])): ?>
  <div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i><?= htmlspecialchars($resumen['error_general']) ?></div>
<?php endif; ?>

<!-- ── Formulario de subida ── -->
<div class="section-card mb-4">
  <h6 class="fw-bold mb-3"><i class="bi bi-upload me-2 text-primary"></i>Subir archivo CSV</h6>
  <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="action" value="importar_csv">
    <input type="hidden" name="csrf"   value="<?= Auth::csrfToken() ?>">
    <div class="row g-3 align-items-end">
      <div class="col-md-8">
        <label class="form-label">Archivo CSV de WooCommerce</label>
        <input type="file" name="csv_file" class="form-control" accept=".csv,.txt" required>
        <div class="form-text">Separador auto-detectado (coma o punto y coma) · UTF-8, ISO-8859-1 o Windows-1252</div>
      </div>
      <div class="col-md-4">
        <button type="submit" class="btn btn-primary w-100">
          <i class="bi bi-cloud-upload me-2"></i>Importar
        </button>
      </div>
    </div>
  </form>
</div>

<!-- ── Resumen de importación ── -->
<?php if ($resumen !== null && !isset($resumen['error_general'])): ?>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-3">
    <div class="section-card text-center py-3">
      <div class="fs-3 fw-bold text-success"><?= $resumen['nuevos'] ?></div>
      <div class="small text-muted">Importados</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="section-card text-center py-3">
      <div class="fs-3 fw-bold text-secondary"><?= $resumen['omitidos'] ?></div>
      <div class="small text-muted">Ya existían (skip)</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="section-card text-center py-3">
      <div class="fs-3 fw-bold text-warning"><?= $resumen['saltados'] ?></div>
      <div class="small text-muted">Otro estado</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="section-card text-center py-3">
      <div class="fs-3 fw-bold text-danger"><?= $resumen['errores'] ?></div>
      <div class="small text-muted">Errores</div>
    </div>
  </div>
</div>

<?php if (!empty($resumen['filas'])): ?>
<div class="section-card">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-bold mb-0"><i class="bi bi-list-check me-2"></i>Detalle fila por fila
      <span class="text-muted fw-normal small">(<?= count($resumen['filas']) ?> filas procesadas)</span>
    </h6>
    <!-- Filtros rápidos por resultado -->
    <div class="btn-group btn-group-sm" role="group" id="filtroTabla">
      <button type="button" class="btn btn-outline-secondary active" data-filtro="">Todos</button>
      <button type="button" class="btn btn-outline-success"   data-filtro="importado">Importados</button>
      <button type="button" class="btn btn-outline-secondary" data-filtro="ya_existe">Ya existían</button>
      <button type="button" class="btn btn-outline-warning"   data-filtro="saltado">Otro estado</button>
      <?php if ($resumen['errores']): ?>
      <button type="button" class="btn btn-outline-danger"    data-filtro="error">Errores</button>
      <?php endif; ?>
    </div>
  </div>
  <div class="table-responsive">
    <table class="table table-sm table-hover" style="font-size:.82rem">
      <thead class="table-light">
        <tr>
          <th># Orden</th>
          <th>Cliente</th>
          <th>Estado WooCommerce</th>
          <th>Resultado</th>
          <th>Detalle</th>
        </tr>
      </thead>
      <tbody id="tablaResultado">
        <?php foreach ($resumen['filas'] as $f):
          $badge = match($f['resultado']) {
            'importado' => ['success',   'Importado'],
            'ya_existe' => ['secondary', 'Ya existía'],
            'saltado'   => ['warning',   'Otro estado'],
            'error'     => ['danger',    'Error'],
            default     => ['light',     $f['resultado']],
          };
        ?>
        <tr data-resultado="<?= $f['resultado'] ?>">
          <td><code style="font-size:.78rem"><?= htmlspecialchars($f['woo_id']) ?></code></td>
          <td><?= htmlspecialchars($f['nombre']) ?></td>
          <td><span class="text-muted"><?= htmlspecialchars($f['estado_woo']) ?></span></td>
          <td><span class="badge bg-<?= $badge[0] ?>"><?= $badge[1] ?></span></td>
          <td class="text-muted"><?= htmlspecialchars($f['detalle']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if ($resumen['nuevos'] > 0): ?>
  <div class="text-center mt-3">
    <a href="<?= APP_URL ?>/modules/pedidos_tienda/" class="btn btn-primary">
      <i class="bi bi-arrow-right me-2"></i>Ver pedidos importados
    </a>
  </div>
<?php endif; ?>

<?php endif; ?>

<script>
document.querySelectorAll('#filtroTabla button').forEach(btn => {
  btn.addEventListener('click', function () {
    document.querySelectorAll('#filtroTabla button').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
    const filtro = this.dataset.filtro;
    document.querySelectorAll('#tablaResultado tr').forEach(tr => {
      tr.style.display = (!filtro || tr.dataset.resultado === filtro) ? '' : 'none';
    });
  });
});
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
