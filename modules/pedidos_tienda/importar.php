<?php
/**
 * modules/pedidos_tienda/importar.php
 * Importación CSV de pedidos WooCommerce.
 *
 * Reglas por estado WooCommerce:
 *  - "procesando" / "processing" → se importa como 'pendiente' (requiere aprobación manual)
 *  - "recibido"                  → se importa y se auto-aprueba, pasa directo a 'en_produccion'
 *  - "entregado" / "completed"   → se importa como 'entregado' (solo historial, nada que hacer)
 *  - Cualquier otro estado       → se salta sin importar
 *  - Si woo_order_id ya existe   → SKIP sin tocar nada
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

// ── Detectar columnas opcionales (dependen de que las migraciones hayan corrido) ──
$colCantidad  = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tienda_pedidos'
    AND COLUMN_NAME='cantidad'")->fetchColumn();
$colInstruc   = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tienda_pedidos'
    AND COLUMN_NAME='instrucciones_especiales'")->fetchColumn();
$colWooStatus = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tienda_pedidos'
    AND COLUMN_NAME='woo_status'")->fetchColumn();
$colAprobado  = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tienda_pedidos'
    AND COLUMN_NAME='aprobado_por'")->fetchColumn();

// ── Helpers locales ──
function tp_fecha_iso(string $f): string {
    if (preg_match('#(\d{2})/(\d{2})/(\d{4})#', $f, $m)) return "{$m[3]}-{$m[1]}-{$m[2]}";
    if (preg_match('#(\d{4})-(\d{2})-(\d{2})#', $f)) return $f;
    return date('Y-m-d');
}
function tp_colegio(string $cat): string {
    return strpos($cat, '>') !== false ? trim(explode('>', $cat)[1]) : '';
}

/**
 * Clasifica el estado WooCommerce del CSV y devuelve la acción a tomar:
 *  'pendiente'       → procesando/processing — importar como pendiente
 *  'auto_produccion' → recibido/on-hold      — importar y auto-aprobar a en_produccion
 *  'historico'       → entregado/completed   — importar como entregado (solo historial)
 *  null              → cualquier otro estado — saltar sin importar
 */
function tp_clasificar_estado(string $estWoo): ?string {
    $e = strtolower(trim($estWoo));
    if (str_contains($e, 'procesando') || $e === 'processing') return 'pendiente';
    if (str_contains($e, 'recibido')   || $e === 'on-hold')    return 'auto_produccion';
    if (str_contains($e, 'entregado')  || $e === 'completed')  return 'historico';
    return null;
}

// ── Resultado de la importación (se llena en POST) ──
$resumen = null; // null = no se importó aún

// ── Procesar CSV ──
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'importar_csv'
    && Auth::csrfVerify($_POST['csrf'] ?? '')
) {
    $file = $_FILES['csv_file'] ?? null;
    $resumen = [
        'filas'            => [],
        'nuevos_procesando'=> 0,
        'nuevos_recibido'  => 0,
        'nuevos_historico' => 0,
        'omitidos'         => 0,
        'saltados'         => 0,
        'errores'          => 0,
    ];

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

        // IDs ya en BD (para detectar duplicados reales entre importaciones)
        $existentes_db = array_flip(
            $db->query("SELECT woo_order_id FROM tienda_pedidos")->fetchAll(PDO::FETCH_COLUMN)
        );
        // IDs vistos en ESTE CSV (para detectar duplicados de pedidos con múltiples líneas de producto)
        $existentes_csv = [];

        $usuarioId = Auth::user()['id'];

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

                // ── REGLA 1: clasificar el estado ──
                $accion = tp_clasificar_estado($estWoo);
                if ($accion === null) {
                    $fila['resultado'] = 'saltado';
                    $fila['detalle']   = 'Estado: ' . ($estWoo ?: '(vacío)');
                    $resumen['saltados']++;
                    $resumen['filas'][] = $fila;
                    $existentes_csv[$wooId] = true; // evitar procesar otra fila del mismo pedido
                    continue;
                }

                // ── REGLA 2: SKIP si ya existe (en BD o en este mismo CSV) ──
                if (isset($existentes_db[$wooId]) || isset($existentes_csv[$wooId])) {
                    $fila['resultado'] = 'ya_existe';
                    $fila['detalle']   = isset($existentes_db[$wooId])
                        ? 'Ya estaba en la base de datos'
                        : 'Duplicado en el CSV (pedido con múltiples productos)';
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

                // Estado interno según la clasificación
                $estadoInterno = ($accion === 'historico') ? 'entregado' : 'pendiente';

                $data = [
                    'woo_order_id'    => $wooId,
                    'estado'          => $estadoInterno,
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

                if ($colWooStatus) {
                    $data['woo_status'] = $estWoo ?: null;
                }
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

                // Historial inicial
                $notaHistorial = match($accion) {
                    'pendiente'       => 'Importado desde CSV (procesando)',
                    'auto_produccion' => 'Importado desde CSV (recibido)',
                    'historico'       => 'Importado desde CSV como histórico (ya entregado en WooCommerce)',
                    default           => 'Importado desde CSV',
                };
                $db->prepare("INSERT INTO tienda_pedidos_historial
                    (pedido_id, estado_ant, estado_nuevo, nota, usuario_id)
                    VALUES (?, NULL, ?, ?, ?)")
                   ->execute([$newId, $estadoInterno, $notaHistorial, $usuarioId]);

                // ── Auto-aprobación para estado "recibido" ──
                if ($accion === 'auto_produccion') {
                    $sets   = "estado='aprobado', updated_at=NOW()";
                    $params = ['id' => $newId];
                    if ($colAprobado) {
                        $sets .= ", aprobado_por=:ap, aprobado_at=NOW()";
                        $params['ap'] = $usuarioId;
                    }
                    $db->prepare("UPDATE tienda_pedidos SET $sets WHERE id=:id")->execute($params);

                    $db->prepare("INSERT INTO tienda_pedidos_historial
                        (pedido_id, estado_ant, estado_nuevo, nota, usuario_id)
                        VALUES (?, 'pendiente', 'aprobado', 'Auto-aprobado al importar (recibido en WooCommerce)', ?)")
                       ->execute([$newId, $usuarioId]);

                    // Crear solicitud de producción
                    $fechaCompra = tp_fecha_iso($get($cols, 'fecha'));
                    $dias = 0;
                    try {
                        $dias = (new DateTime($fechaCompra))->diff(new DateTime('today'))->days;
                    } catch (Exception $e) {}

                    $solicitudTablaExiste = $db->query("SHOW TABLES LIKE 'solicitudes_produccion'")->fetchColumn();
                    if ($solicitudTablaExiste) {
                        crearSolicitudProduccion($db, [
                            'fuente'        => 'tienda',
                            'titulo'        => ($data['kit_nombre'] ?? 'Pedido sin kit'),
                            'tipo'          => 'armar_kit',
                            'kit_nombre'    => $data['kit_nombre'] ?? null,
                            'cantidad'      => (int)($data['cantidad'] ?? 1),
                            'prioridad'     => $dias > 7 ? 1 : ($dias > 5 ? 2 : 3),
                            'fecha_limite'  => null,
                            'usuario_id'    => $usuarioId,
                            'pedido_id'     => $newId,
                            'colegio_id'    => $colegioId,
                            'notas'         => null,
                            'historial_nota'=> 'Auto-aprobado al importar CSV (recibido en WooCommerce)',
                        ]);
                    }

                    $resumen['nuevos_recibido']++;
                    $fila['resultado'] = 'importado_recibido';
                    $fila['detalle']   = 'Auto-aprobado → En producción';

                } elseif ($accion === 'historico') {
                    $resumen['nuevos_historico']++;
                    $fila['resultado'] = 'importado_historico';
                    $fila['detalle']   = 'Registrado como historial';

                } else {
                    $resumen['nuevos_procesando']++;
                    $fila['resultado'] = 'importado_procesando';
                    $fila['detalle']   = 'Pendiente de aprobación';
                }

                // Registrar para evitar procesar otra fila del mismo pedido en este CSV
                $existentes_csv[$wooId] = true;

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
    <div class="text-muted small">
      <strong>Procesando</strong> → pendiente de aprobación &nbsp;·&nbsp;
      <strong>Recibido</strong> → auto-aprobado a producción &nbsp;·&nbsp;
      <strong>Entregado</strong> → histórico &nbsp;·&nbsp;
      Duplicados se omiten sin modificar
    </div>
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
  <div class="col-6 col-md-2">
    <div class="section-card text-center py-3">
      <div class="fs-3 fw-bold text-warning"><?= $resumen['nuevos_procesando'] ?></div>
      <div class="small text-muted">Procesando<br><span style="font-size:.7rem">→ Pendientes</span></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="section-card text-center py-3">
      <div class="fs-3 fw-bold text-primary"><?= $resumen['nuevos_recibido'] ?></div>
      <div class="small text-muted">Recibido<br><span style="font-size:.7rem">→ En producción</span></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="section-card text-center py-3">
      <div class="fs-3 fw-bold text-success"><?= $resumen['nuevos_historico'] ?></div>
      <div class="small text-muted">Entregado<br><span style="font-size:.7rem">→ Histórico</span></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="section-card text-center py-3">
      <div class="fs-3 fw-bold text-secondary"><?= $resumen['omitidos'] ?></div>
      <div class="small text-muted">Ya existían<br><span style="font-size:.7rem">(skip)</span></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
    <div class="section-card text-center py-3">
      <div class="fs-3 fw-bold" style="color:#94a3b8"><?= $resumen['saltados'] ?></div>
      <div class="small text-muted">Otro estado<br><span style="font-size:.7rem">(saltados)</span></div>
    </div>
  </div>
  <div class="col-6 col-md-2">
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
      <button type="button" class="btn btn-outline-secondary active"  data-filtro="">Todos</button>
      <button type="button" class="btn btn-outline-warning"           data-filtro="importado_procesando">Procesando</button>
      <button type="button" class="btn btn-outline-primary"           data-filtro="importado_recibido">Recibido</button>
      <button type="button" class="btn btn-outline-success"           data-filtro="importado_historico">Entregado</button>
      <button type="button" class="btn btn-outline-secondary"         data-filtro="ya_existe">Ya existían</button>
      <button type="button" class="btn btn-outline-secondary"         data-filtro="saltado" style="color:#94a3b8">Saltados</button>
      <?php if ($resumen['errores']): ?>
      <button type="button" class="btn btn-outline-danger"            data-filtro="error">Errores</button>
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
            'importado_procesando' => ['warning',   'Pendiente'],
            'importado_recibido'   => ['primary',   'En producción'],
            'importado_historico'  => ['success',   'Histórico'],
            'ya_existe'            => ['secondary', 'Ya existía'],
            'saltado'              => ['light',     'Saltado'],
            'error'                => ['danger',    'Error'],
            default                => ['light',     $f['resultado']],
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

<?php
  $totalImportados = $resumen['nuevos_procesando'] + $resumen['nuevos_recibido'] + $resumen['nuevos_historico'];
?>
<?php if ($totalImportados > 0): ?>
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
