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
$colWooTotal  = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tienda_pedidos'
    AND COLUMN_NAME='woo_total'")->fetchColumn();
$colEstadoPago = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tienda_pedidos'
    AND COLUMN_NAME='estado_pago'")->fetchColumn();
$tblItemsOk   = (bool) $db->query("SELECT COUNT(*) FROM information_schema.TABLES
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tienda_pedido_items'")->fetchColumn();

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
        'nuevos_multi'     => 0,
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
            'cantidad'        => ['Item #1 Quantity', 'Quantity', 'Cantidad', 'qty'],
            'instruc'         => ['Customer Note', 'Nota del cliente', 'Order Notes', 'Customer Provided Note'],
            'total_articulos' => ['Total de artículos', 'Total Items', 'Item Count'],
            'precio_linea'    => ['Total de la línea del pedido', 'Line Total', 'Item Total'],
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

        // IDs ya en BD (para detectar duplicados)
        $existentes_db = array_flip(
            $db->query("SELECT woo_order_id FROM tienda_pedidos")->fetchAll(PDO::FETCH_COLUMN)
        );

        $usuarioId = Auth::user()['id'];

        // ── Pre-paso: agrupar filas por woo_order_id ──
        $grupos = [];
        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            $cols  = str_getcsv($line, $sep);
            $wooId = trim($get($cols, 'id'), "# \t");
            if ($wooId === '') continue;
            if (!isset($grupos[$wooId])) {
                $grupos[$wooId] = ['rows' => [], 'total_articulos' => 0];
            }
            $grupos[$wooId]['rows'][] = $cols;
            $ta = (int)$get($cols, 'total_articulos');
            if ($ta > 0 && $grupos[$wooId]['total_articulos'] === 0) {
                $grupos[$wooId]['total_articulos'] = $ta;
            }
        }

        // ── Procesar cada grupo ──
        foreach ($grupos as $wooId => $grupo) {
            $allRows    = $grupo['rows'];
            $taEsperado = $grupo['total_articulos'];
            $baseRow    = $allRows[0];

            $estWoo  = $get($baseRow, 'estado_woo');
            $nombre  = trim($get($baseRow, 'nombre') . ' ' . $get($baseRow, 'apellido')) ?: 'Sin nombre';
            $fila    = ['woo_id' => '#' . $wooId, 'nombre' => $nombre, 'estado_woo' => $estWoo, 'resultado' => '', 'detalle' => ''];

            try {
                // ── REGLA 1: clasificar el estado ──
                $accion = tp_clasificar_estado($estWoo);
                if ($accion === null) {
                    $fila['resultado'] = 'saltado';
                    $fila['detalle']   = 'Estado: ' . ($estWoo ?: '(vacío)');
                    $resumen['saltados']++;
                    $resumen['filas'][] = $fila;
                    continue;
                }

                // ── REGLA 2: SKIP si ya existe en BD ──
                if (isset($existentes_db[$wooId])) {
                    $fila['resultado'] = 'ya_existe';
                    $fila['detalle']   = 'Ya estaba en la base de datos';
                    $resumen['omitidos']++;
                    $resumen['filas'][] = $fila;
                    continue;
                }

                // ── Deduplicar ítems por nombre de producto ──
                $seenKits   = [];
                $uniqueRows = [];
                foreach ($allRows as $row) {
                    $kitKey = strtolower(trim($get($row, 'kit')));
                    if (!isset($seenKits[$kitKey])) {
                        $seenKits[$kitKey] = true;
                        $uniqueRows[] = $row;
                    }
                }
                $isMulti = count($uniqueRows) > 1;

                // ── Calcular total del pedido (suma de líneas) ──
                $wooTotal = 0.0;
                foreach ($uniqueRows as $row) {
                    $wooTotal += (float)$get($row, 'precio_linea');
                }

                // ── Aviso si artículos != esperado ──
                $avisoItems = '';
                if ($taEsperado > 0 && count($uniqueRows) !== $taEsperado) {
                    $avisoItems = ' [⚠ esperados ' . $taEsperado . ', encontrados ' . count($uniqueRows) . ']';
                }

                // ── Datos del colegio (del primer ítem) ──
                $cat       = $get($baseRow, 'categoria');
                $colegio   = tp_colegio($cat);
                $colegioId = null;
                if ($colegio) {
                    $st = $db->prepare("SELECT id FROM colegios WHERE activo=1 AND nombre LIKE ? LIMIT 1");
                    $st->execute(['%' . $colegio . '%']);
                    $colegioId = $st->fetchColumn() ?: null;
                }

                // ── kit_nombre legacy para tienda_pedidos ──
                if ($isMulti) {
                    $kitNombresArr   = array_map(fn($r) => $get($r, 'kit'), $uniqueRows);
                    $kitNombreLegacy = count($uniqueRows) . ' productos: '
                                     . implode(' + ', array_slice($kitNombresArr, 0, 2))
                                     . (count($kitNombresArr) > 2 ? ' ...' : '');
                    $cantidadLegacy  = array_sum(array_map(fn($r) => max(1, (int)$get($r, 'cantidad')), $uniqueRows));
                } else {
                    $kitNombreLegacy = $get($baseRow, 'kit');
                    $cantidadLegacy  = max(1, (int)$get($baseRow, 'cantidad'));
                }

                $estadoInterno = ($accion === 'historico') ? 'entregado' : 'pendiente';

                // ── Construir $data para INSERT tienda_pedidos ──
                $data = [
                    'woo_order_id'    => $wooId,
                    'estado'          => $estadoInterno,
                    'cliente_nombre'  => $nombre,
                    'cliente_telefono'=> $get($baseRow, 'telefono'),
                    'cliente_email'   => $get($baseRow, 'email'),
                    'direccion'       => $get($baseRow, 'dir'),
                    'ciudad'          => $get($baseRow, 'ciudad'),
                    'colegio_nombre'  => $colegio,
                    'colegio_id'      => $colegioId,
                    'kit_nombre'      => $kitNombreLegacy,
                    'categoria'       => $cat,
                    'fecha_compra'    => tp_fecha_iso($get($baseRow, 'fecha')),
                    'creado_desde_csv'=> 1,
                ];
                if ($colWooStatus)  $data['woo_status']  = $estWoo ?: null;
                if ($colCantidad)   $data['cantidad']    = $cantidadLegacy;
                if ($colInstruc)    $data['instrucciones_especiales'] = $get($baseRow, 'instruc') ?: null;
                if ($colWooTotal && $wooTotal > 0) $data['woo_total'] = $wooTotal;
                if ($colEstadoPago) $data['estado_pago'] = 'aprobado';

                // ── INSERT tienda_pedidos ──
                $placeholders = ':' . implode(', :', array_keys($data));
                $colNames     = implode(', ', array_keys($data));
                $db->prepare("INSERT INTO tienda_pedidos ($colNames) VALUES ($placeholders)")->execute($data);
                $newId = (int)$db->lastInsertId();

                // ── Historial inicial ──
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

                // ── INSERT en tienda_pedido_items ──
                if ($tblItemsOk) {
                    foreach ($uniqueRows as $row) {
                        $linea      = (float)$get($row, 'precio_linea');
                        $qty        = max(1, (int)$get($row, 'cantidad'));
                        $precioUnit = $qty > 0 ? round($linea / $qty, 2) : $linea;
                        $rowCat     = $get($row, 'categoria');
                        $rowColegio = tp_colegio($rowCat);
                        $rowColId   = null;
                        if ($rowColegio) {
                            $st2 = $db->prepare("SELECT id FROM colegios WHERE activo=1 AND nombre LIKE ? LIMIT 1");
                            $st2->execute(['%' . $rowColegio . '%']);
                            $rowColId = $st2->fetchColumn() ?: null;
                        }
                        $db->prepare("INSERT INTO tienda_pedido_items
                            (pedido_id, kit_nombre, colegio_id, colegio_nombre, cantidad, precio_unit, subtotal)
                            VALUES (?, ?, ?, ?, ?, ?, ?)")
                           ->execute([$newId, $get($row, 'kit'), $rowColId, $rowColegio, $qty, $precioUnit, $linea]);
                    }
                }

                // ── Auto-aprobación para "recibido" ──
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

                    $fechaCompra = tp_fecha_iso($get($baseRow, 'fecha'));
                    $dias = 0;
                    try { $dias = (new DateTime($fechaCompra))->diff(new DateTime('today'))->days; } catch (Exception $e) {}

                    $solicitudTablaExiste = $db->query("SHOW TABLES LIKE 'solicitudes_produccion'")->fetchColumn();
                    if ($solicitudTablaExiste) {
                        foreach ($uniqueRows as $row) {
                            $qty = max(1, (int)$get($row, 'cantidad'));
                            crearSolicitudProduccion($db, [
                                'fuente'        => 'tienda',
                                'titulo'        => $get($row, 'kit') ?: 'Pedido sin kit',
                                'tipo'          => 'armar_kit',
                                'kit_nombre'    => $get($row, 'kit') ?: null,
                                'cantidad'      => $qty,
                                'prioridad'     => $dias > 7 ? 1 : ($dias > 5 ? 2 : 3),
                                'fecha_limite'  => null,
                                'usuario_id'    => $usuarioId,
                                'pedido_id'     => $newId,
                                'colegio_id'    => $colegioId,
                                'notas'         => null,
                                'historial_nota'=> 'Auto-aprobado al importar CSV (recibido en WooCommerce)',
                            ]);
                        }
                    }
                }

                // ── Contadores y resultado ──
                $precioStr = $wooTotal > 0 ? ' · $' . number_format($wooTotal, 0, ',', '.') : '';
                if ($isMulti) {
                    $resumen['nuevos_multi']++;
                    $kitListStr = implode(' + ', array_map(fn($r) => $get($r, 'kit'), $uniqueRows));
                    if ($accion === 'auto_produccion') {
                        $resumen['nuevos_recibido']++;
                        $fila['resultado'] = 'importado_multi_recibido';
                        $fila['detalle']   = count($uniqueRows) . ' productos: ' . $kitListStr . $precioStr . $avisoItems . ' → Auto-aprobado';
                    } elseif ($accion === 'historico') {
                        $resumen['nuevos_historico']++;
                        $fila['resultado'] = 'importado_multi_historico';
                        $fila['detalle']   = count($uniqueRows) . ' productos: ' . $kitListStr . $precioStr . $avisoItems;
                    } else {
                        $resumen['nuevos_procesando']++;
                        $fila['resultado'] = 'importado_multi';
                        $fila['detalle']   = count($uniqueRows) . ' productos: ' . $kitListStr . $precioStr . $avisoItems;
                    }
                } else {
                    if ($accion === 'auto_produccion') {
                        $resumen['nuevos_recibido']++;
                        $fila['resultado'] = 'importado_recibido';
                        $fila['detalle']   = 'Auto-aprobado → En producción' . $precioStr . $avisoItems;
                    } elseif ($accion === 'historico') {
                        $resumen['nuevos_historico']++;
                        $fila['resultado'] = 'importado_historico';
                        $fila['detalle']   = 'Registrado como historial' . $precioStr . $avisoItems;
                    } else {
                        $resumen['nuevos_procesando']++;
                        $fila['resultado'] = 'importado_procesando';
                        $fila['detalle']   = 'Pendiente de aprobación' . $precioStr . $avisoItems;
                    }
                }

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
      <div class="fs-3 fw-bold" style="color:#7c3aed"><?= $resumen['nuevos_multi'] ?></div>
      <div class="small text-muted">Multi-producto<br><span style="font-size:.7rem">(incluidos arriba)</span></div>
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
      <?php if ($resumen['nuevos_multi']): ?>
      <button type="button" class="btn btn-outline-secondary"         data-filtro="importado_multi" style="color:#7c3aed">Multi</button>
      <?php endif; ?>
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
            'importado_procesando'       => ['warning',   'Pendiente'],
            'importado_recibido'         => ['primary',   'En producción'],
            'importado_historico'        => ['success',   'Histórico'],
            'importado_multi'            => ['warning',   'Multi · Pendiente'],
            'importado_multi_recibido'   => ['primary',   'Multi · Producción'],
            'importado_multi_historico'  => ['success',   'Multi · Histórico'],
            'ya_existe'                  => ['secondary', 'Ya existía'],
            'saltado'                    => ['light',     'Saltado'],
            'error'                      => ['danger',    'Error'],
            default                      => ['light',     $f['resultado']],
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
  $totalImportados = $resumen['nuevos_procesando'] + $resumen['nuevos_recibido'] + $resumen['nuevos_historico']; // nuevos_multi ya está incluido en estos
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
      const res = tr.dataset.resultado;
      // El filtro "importado_multi" muestra las 3 variantes multi
      const match = !filtro
        || res === filtro
        || (filtro === 'importado_multi' && res.startsWith('importado_multi'));
      tr.style.display = match ? '' : 'none';
    });
  });
});
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
