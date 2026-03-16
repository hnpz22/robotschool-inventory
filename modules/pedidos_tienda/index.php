<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();

$db         = Database::get();
$pageTitle  = 'Pedidos Tienda';
$activeMenu = 'pedidos_tienda';
$error = $success = '';

// ── Labels y colores de estado ──
$ESTADOS = [
    'pendiente'     => ['label'=>'Pendiente',       'color'=>'secondary', 'bg'=>'#f1f5f9', 'txt'=>'#475569'],
    'en_produccion' => ['label'=>'En producci&oacute;n', 'color'=>'info',  'bg'=>'#dbeafe', 'txt'=>'#1e40af'],
    'listo_envio'   => ['label'=>'Listo para env&iacute;o','color'=>'warning','bg'=>'#fef9c3','txt'=>'#854d0e'],
    'despachado'    => ['label'=>'Despachado',       'color'=>'primary',  'bg'=>'#ede9fe', 'txt'=>'#5b21b6'],
    'entregado'     => ['label'=>'Entregado',        'color'=>'success',  'bg'=>'#dcfce7', 'txt'=>'#166534'],
    'cancelado'     => ['label'=>'Cancelado',        'color'=>'danger',   'bg'=>'#fee2e2', 'txt'=>'#991b1b'],
];

// ── Helpers ──
function rbs_dias(string $fecha): int {
    try {
        if (preg_match('#(\d{2})/(\d{2})/(\d{4})#', $fecha, $m)) {
            $ts = mktime(0,0,0,(int)$m[1],(int)$m[2],(int)$m[3]);
            return max(0,(int)floor((time()-$ts)/86400));
        }
        return max(0,(int)floor((time()-strtotime($fecha))/86400));
    } catch (Exception $e) { return 0; }
}
function rbs_semaforo(string $fecha, string $estado): string {
    if (in_array($estado,['entregado','cancelado'])) return 'completado';
    $d = rbs_dias($fecha);
    if ($d <= 5) return 'verde';
    if ($d <= 7) return 'amarillo';
    return 'rojo';
}
function rbs_colegio(string $cat): string {
    return strpos($cat,'>') !== false ? trim(explode('>',$cat)[1]) : '';
}
function rbs_fecha_iso(string $fecha): string {
    if (preg_match('#(\d{2})/(\d{2})/(\d{4})#',$fecha,$m)) return "{$m[3]}-{$m[1]}-{$m[2]}";
    return $fecha;
}
function rbs_fecha_fmt(string $fecha): string {
    if (preg_match('#(\d{2})/(\d{2})/(\d{4})#',$fecha,$m)) return "{$m[2]}/{$m[1]}/{$m[3]}";
    if (preg_match('#(\d{4})-(\d{2})-(\d{2})#',$fecha,$m)) return "{$m[3]}/{$m[2]}/{$m[1]}";
    return $fecha;
}

// ── Cambiar estado de un pedido ──
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='cambiar_estado' && Auth::csrfVerify($_POST['csrf']??'')) {
    $pid       = (int)$_POST['pedido_id'];
    $nuevoEst  = $_POST['estado_nuevo'] ?? '';
    $nota      = trim($_POST['nota'] ?? '');
    $guia      = trim($_POST['guia_envio'] ?? '');
    $transport = trim($_POST['transportadora'] ?? '');

    if ($pid && isset($ESTADOS[$nuevoEst])) {
        $actualEst = $db->query("SELECT estado FROM tienda_pedidos WHERE id=$pid")->fetchColumn();

        $sets = "estado=:e, updated_at=NOW()";
        $params = ['e'=>$nuevoEst, 'id'=>$pid];

        if ($nuevoEst === 'despachado') {
            $sets .= ", fecha_despacho=CURDATE()";
            if ($guia)      { $sets .= ", guia_envio=:g";      $params['g'] = $guia; }
            if ($transport) { $sets .= ", transportadora=:t";   $params['t'] = $transport; }
        }
        if ($nuevoEst === 'entregado') {
            $sets .= ", fecha_entrega=CURDATE()";
        }

        $db->prepare("UPDATE tienda_pedidos SET $sets WHERE id=:id")->execute($params);

        // Auto-envío a producción al marcar "en_produccion"
        if ($nuevoEst === 'en_produccion' && $actualEst !== 'en_produccion') {
            $pedRow = $db->query("SELECT * FROM tienda_pedidos WHERE id=$pid")->fetch();
            if ($pedRow) {
                // Verificar si ya tiene solicitud
                $yaExiste = $db->query("SHOW TABLES LIKE 'solicitudes_produccion'")->fetchColumn()
                    ? $db->query("SELECT COUNT(*) FROM solicitudes_produccion WHERE pedido_id=$pid")->fetchColumn()
                    : 0;
                if (!$yaExiste) {
                    $diasComp = (new DateTime($pedRow['fecha_compra']))->diff(new DateTime('today'))->days;
                    crearSolicitudProduccion($db, [
                        'fuente'        => 'tienda',
                        'titulo'        => 'Pedido Tienda: '.($pedRow['kit_nombre'] ?? '').' — '.$pedRow['cliente_nombre'],
                        'tipo'          => 'armar_kit',
                        'kit_nombre'    => $pedRow['kit_nombre'],
                        'cantidad'      => 1,
                        'prioridad'     => $diasComp > 7 ? 1 : ($diasComp > 5 ? 2 : 3),
                        'fecha_limite'  => $pedRow['fecha_limite'],
                        'usuario_id'    => Auth::user()['id'],
                        'pedido_id'     => $pid,
                        'colegio_id'    => $pedRow['colegio_id'],
                        'notas'         => 'Cliente: '.$pedRow['cliente_nombre'].' | '.$pedRow['ciudad'],
                        'historial_nota'=> 'Enviado a producción desde módulo Tienda',
                    ]);
                }
            }
        }

        // Historial
        $db->prepare("INSERT INTO tienda_pedidos_historial (pedido_id,estado_ant,estado_nuevo,nota,usuario_id) VALUES (?,?,?,?,?)")
           ->execute([$pid, $actualEst, $nuevoEst, $nota ?: null, Auth::user()['id']]);

        $success = 'Estado actualizado correctamente.';
    }
}

// ── Importar CSV ──
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='importar_csv' && Auth::csrfVerify($_POST['csrf']??'')) {
    $file = $_FILES['csv_file'] ?? null;
    if ($file && $file['error']===0) {
        $raw = file_get_contents($file['tmp_name']);
        $enc = mb_detect_encoding($raw,['UTF-8','ISO-8859-1','Windows-1252'],true);
        if ($enc && $enc!=='UTF-8') $raw = mb_convert_encoding($raw,'UTF-8',$enc);
        $raw = ltrim($raw,"\xEF\xBB\xBF");

        $firstLine = strtok($raw,"\n");
        $sep = substr_count($firstLine,';') > substr_count($firstLine,',') ? ';' : ',';
        $lines   = str_getcsv($raw,"\n");
        $headers = array_map('trim', str_getcsv(array_shift($lines),$sep));

        $map = [
            'id'       => ['ID del pedido','Order ID'],
            'fecha'    => ['Fecha del pedido','Order Date'],
            'nombre'   => ['First Name (Billing)','Billing First Name'],
            'apellido' => ['Last Name (Billing)','Billing Last Name'],
            'telefono' => ['Phone (Billing)','Billing Phone'],
            'email'    => ['Email (Billing)','Billing Email'],
            'ciudad'   => ['City (Billing)','Billing City'],
            'dir'      => ['Dirección lineas 1 y 2 (envío)','Billing Address 1'],
            'kit'      => ['Nombre del producto (principal)','Line Items'],
            'categoria'=> ['Nombres completos de las categorías','Category'],
            'estado_woo'=> ['Estado del pedido','Status'],
        ];
        $idx = [];
        foreach ($map as $campo => $opts) {
            foreach ($opts as $op) {
                $i = array_search($op,$headers);
                if ($i!==false) { $idx[$campo]=$i; break; }
            }
        }
        $get = function($cols,$campo) use ($idx) {
            return isset($idx[$campo]) ? trim($cols[$idx[$campo]]??'') : '';
        };

        $nuevos = $actualizados = 0;
        foreach ($lines as $line) {
            if (trim($line)==='') continue;
            $cols = str_getcsv($line,$sep);
            $wooId = $get($cols,'id');
            if (!$wooId) continue;

            $fecha   = $get($cols,'fecha');
            $cat     = $get($cols,'categoria');
            $nombre  = trim($get($cols,'nombre').' '.$get($cols,'apellido'));
            $colegio = rbs_colegio($cat);

            // Cruzar colegio con BD
            $colegioId = null;
            if ($colegio) {
                $st = $db->prepare("SELECT id FROM colegios WHERE activo=1 AND nombre LIKE ? LIMIT 1");
                $st->execute(['%'.$colegio.'%']);
                $colegioId = $st->fetchColumn() ?: null;
            }

            // Estado inicial basado en WooCommerce
            $estWoo = strtolower($get($cols,'estado_woo'));
            if (strpos($estWoo,'recibido')!==false || strpos($estWoo,'complet')!==false) {
                $estInt = 'entregado';
            } elseif (strpos($estWoo,'cancel')!==false) {
                $estInt = 'cancelado';
            } else {
                $estInt = 'pendiente';
            }

            $data = [
                'woo_order_id'    => $wooId,
                'cliente_nombre'  => $nombre ?: 'Sin nombre',
                'cliente_telefono'=> $get($cols,'telefono'),
                'cliente_email'   => $get($cols,'email'),
                'direccion'       => $get($cols,'dir'),
                'ciudad'          => $get($cols,'ciudad'),
                'colegio_nombre'  => $colegio,
                'colegio_id'      => $colegioId,
                'kit_nombre'      => $get($cols,'kit'),
                'categoria'       => $cat,
                'fecha_compra'    => rbs_fecha_iso($fecha) ?: date('Y-m-d'),
            ];

            {
                $data['estado'] = $estInt;
                $cols2 = implode(',', array_keys($data));
                $vals2 = ':'.implode(',:', array_keys($data));
                // ON DUPLICATE KEY: actualiza datos del cliente pero NO el estado
                $updateSets = implode(',', array_map(function($k) {
                    // No tocar estado, fecha_compra ni woo_order_id en duplicados
                    if (in_array($k, ['woo_order_id','estado'])) return "$k=$k";
                    return "$k=VALUES($k)";
                }, array_keys($data)));
                $db->prepare("INSERT INTO tienda_pedidos ($cols2) VALUES ($vals2)
                              ON DUPLICATE KEY UPDATE $updateSets")->execute($data);
                $newId = $db->lastInsertId();
                if ($newId > 0) {
                    // Es nuevo (lastInsertId > 0 en INSERT real)
                    $db->prepare("INSERT INTO tienda_pedidos_historial (pedido_id,estado_ant,estado_nuevo,nota,usuario_id) VALUES (?,NULL,?,?,?)")
                       ->execute([$newId, $estInt, 'Importado desde CSV', Auth::user()['id']]);
                    $nuevos++;
                } else {
                    $actualizados++;
                }
            }
        }
        $success = "$nuevos pedidos nuevos importados, $actualizados actualizados.";
    }
}

// ── Filtros ──
$fEstado  = $_GET['estado']  ?? '';
$fSem     = $_GET['sem']     ?? '';
$fColegio = $_GET['colegio'] ?? '';
$fBusqRaw = trim($_GET['q'] ?? '');
$fBusq    = ltrim($fBusqRaw, '#'); // Quitar # si escribe #8110
$pagina   = max(1,(int)($_GET['pag'] ?? 1));
$porPag   = 30;

$where = ["1=1"];
if ($fEstado)  $where[] = "p.estado = ".$db->quote($fEstado);
if ($fColegio) {
    $where[] = "(p.colegio_nombre LIKE " . $db->quote('%'.$fColegio.'%') .
               " OR EXISTS (SELECT 1 FROM colegios c2 WHERE c2.id=p.colegio_id AND c2.nombre LIKE " . $db->quote('%'.$fColegio.'%') . "))";
}
if ($fBusq) {
    $busqNum = preg_replace('/[^0-9]/', '', $fBusq);
    $qLike   = $db->quote('%'.$fBusq.'%');
    $cond    = "(p.cliente_nombre LIKE $qLike OR p.woo_order_id LIKE $qLike OR p.kit_nombre LIKE $qLike";
    if ($busqNum) $cond .= " OR p.woo_order_id = ".$db->quote($busqNum);
    $cond .= ")";
    $where[] = $cond;
}
if ($fSem) {
    switch ($fSem) {
        case 'verde':     $where[] = "p.estado NOT IN ('entregado','cancelado') AND DATEDIFF(CURDATE(),p.fecha_compra)<=5"; break;
        case 'amarillo':  $where[] = "p.estado NOT IN ('entregado','cancelado') AND DATEDIFF(CURDATE(),p.fecha_compra) BETWEEN 6 AND 7"; break;
        case 'rojo':      $where[] = "p.estado NOT IN ('entregado','cancelado') AND DATEDIFF(CURDATE(),p.fecha_compra)>7"; break;
        case 'completado':$where[] = "p.estado IN ('entregado','cancelado')"; break;
    }
}
$whereStr = implode(' AND ', $where);

$total = $db->query("SELECT COUNT(*) FROM tienda_pedidos p WHERE $whereStr")->fetchColumn();
$totalPags = max(1,ceil($total/$porPag));
$offset = ($pagina-1)*$porPag;

$pedidos = $db->query("
    SELECT p.*,
           DATEDIFF(CURDATE(),p.fecha_compra) AS dias,
           CASE
             WHEN p.estado IN ('entregado','cancelado') THEN 'completado'
             WHEN DATEDIFF(CURDATE(),p.fecha_compra)<=5 THEN 'verde'
             WHEN DATEDIFF(CURDATE(),p.fecha_compra)<=7 THEN 'amarillo'
             ELSE 'rojo'
           END AS semaforo,
           col.nombre AS colegio_bd,
           u.nombre   AS asignado_nombre
    FROM tienda_pedidos p
    LEFT JOIN colegios col ON col.id=p.colegio_id
    LEFT JOIN usuarios u   ON u.id=p.asignado_a
    WHERE $whereStr
    ORDER BY
      CASE WHEN p.estado IN ('entregado','cancelado') THEN 1 ELSE 0 END ASC,
      CASE WHEN DATEDIFF(CURDATE(),p.fecha_compra)>7  THEN 0
           WHEN DATEDIFF(CURDATE(),p.fecha_compra)>5  THEN 1
           ELSE 2 END ASC,
      p.fecha_compra ASC
    LIMIT $porPag OFFSET $offset
")->fetchAll();

// Stats
$stats = $db->query("
    SELECT
      COUNT(*) AS total,
      SUM(CASE WHEN estado NOT IN ('entregado','cancelado') AND DATEDIFF(CURDATE(),fecha_compra)>7  THEN 1 ELSE 0 END) AS rojos,
      SUM(CASE WHEN estado NOT IN ('entregado','cancelado') AND DATEDIFF(CURDATE(),fecha_compra) BETWEEN 6 AND 7 THEN 1 ELSE 0 END) AS amarillos,
      SUM(CASE WHEN estado NOT IN ('entregado','cancelado') AND DATEDIFF(CURDATE(),fecha_compra)<=5 THEN 1 ELSE 0 END) AS verdes,
      SUM(CASE WHEN estado='pendiente'     THEN 1 ELSE 0 END) AS pendientes,
      SUM(CASE WHEN estado='en_produccion' THEN 1 ELSE 0 END) AS en_prod,
      SUM(CASE WHEN estado='listo_envio'   THEN 1 ELSE 0 END) AS listos,
      SUM(CASE WHEN estado='despachado'    THEN 1 ELSE 0 END) AS despachados,
      SUM(CASE WHEN estado='entregado'     THEN 1 ELSE 0 END) AS entregados
    FROM tienda_pedidos
")->fetch();

// Colegios únicos que existen en los pedidos importados (no solo los de BD)
$colegios_pedidos = $db->query("
    SELECT DISTINCT
      COALESCE(col.nombre, p.colegio_nombre) AS nombre_display,
      p.colegio_nombre AS nombre_csv
    FROM tienda_pedidos p
    LEFT JOIN colegios col ON col.id = p.colegio_id
    WHERE (p.colegio_nombre IS NOT NULL AND p.colegio_nombre != '')
       OR (p.colegio_id IS NOT NULL)
    ORDER BY nombre_display
")->fetchAll();

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
.sem-dot{width:11px;height:11px;border-radius:50%;display:inline-block;flex-shrink:0}
.sem-verde    {background:#22c55e;box-shadow:0 0 0 3px #dcfce7}
.sem-amarillo {background:#f59e0b;box-shadow:0 0 0 3px #fef9c3}
.sem-rojo     {background:#ef4444;box-shadow:0 0 0 3px #fee2e2}
.sem-completado{background:#94a3b8;box-shadow:0 0 0 3px #f1f5f9}
.stat-chip{border-radius:10px;padding:.4rem .9rem;font-size:.82rem;font-weight:600;text-decoration:none;display:inline-flex;align-items:center;gap:.4rem;cursor:pointer}
.section-card{background:#fff;border-radius:14px;border:1px solid #e2e8f0;padding:1rem 1.2rem;margin-bottom:1rem}
.rt thead th{background:#1e293b;color:#fff;padding:.5rem .75rem;font-size:.74rem;font-weight:600;white-space:nowrap;border:none}
.rt tbody td{padding:.45rem .75rem;font-size:.81rem;border-bottom:1px solid #f1f5f9;vertical-align:middle}
.rt tbody tr:hover td{background:#f8fafc}
.estado-sel{font-size:.75rem;padding:.2rem .4rem;border-radius:6px;border:1px solid #e2e8f0;background:#fff;cursor:pointer}
.borde-rojo     td:first-child{border-left:4px solid #ef4444!important}
.borde-amarillo td:first-child{border-left:4px solid #f59e0b!important}
.borde-verde    td:first-child{border-left:4px solid #22c55e!important}
.borde-completado td:first-child{border-left:4px solid #94a3b8!important;opacity:.8}
.upload-area{border:2px dashed #cbd5e1;border-radius:14px;padding:3rem;text-align:center;cursor:pointer}
.upload-area:hover{background:#f8fafc}
.chk-row{width:16px;height:16px;cursor:pointer;accent-color:#185FA5}
.barra-sel{
  position:fixed;bottom:1.5rem;left:50%;transform:translateX(-50%);
  background:#1e293b;color:#fff;border-radius:14px;
  padding:.6rem 1.2rem;display:none;align-items:center;gap:.75rem;
  box-shadow:0 8px 24px rgba(0,0,0,.3);z-index:1000;white-space:nowrap;
}
.barra-sel.visible{display:flex}
.barra-sel .cnt{font-size:.85rem;font-weight:700}
.barra-sel .cnt span{color:#60a5fa}
tr.fila-sel td{background:#eff6ff!important}
</style>

<!-- Header -->
<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h4 class="fw-bold mb-0">&#x1F6D2; Pedidos Tienda Online</h4>
    <p class="text-muted small mb-0"><?= $stats['total'] ?> pedidos en base de datos</p>
  </div>
  <div class="d-flex gap-2">
    <?php if (!empty($pedidos)): ?>
    <a href="stickers.php?<?= htmlspecialchars(http_build_query($_GET)) ?>" target="_blank" class="btn btn-danger btn-sm">
      <i class="bi bi-printer me-1"></i>Etiquetas (<?= count($pedidos) ?>)
    </a>
    <a href="lista_imprimible.php?<?= htmlspecialchars(http_build_query($_GET)) ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-list-ul me-1"></i>Lista imprimible
    </a>
    <?php endif; ?>
    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCSV">
      <i class="bi bi-upload me-1"></i>Importar CSV
    </button>
  </div>
</div>

<!-- Modal CSV -->
<div class="modal fade" id="modalCSV" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:14px">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-bold">Importar CSV de WooCommerce</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form method="POST" enctype="multipart/form-data">
          <input type="hidden" name="action" value="importar_csv">
          <input type="hidden" name="csrf"   value="<?= Auth::csrfToken() ?>">
          <label class="upload-area d-block" for="csv_inp">
            <i class="bi bi-file-earmark-spreadsheet fs-1 text-success d-block mb-2"></i>
            <div class="fw-semibold">Arrastra el CSV o haz clic</div>
            <div class="text-muted small mt-1">Los pedidos nuevos se agregan, los existentes se actualizan sin cambiar su estado interno</div>
            <input type="file" name="csv_file" id="csv_inp" accept=".csv,.txt" class="d-none" onchange="this.form.submit()">
          </label>
        </form>
      </div>
    </div>
  </div>
</div>

<?php if ($error):   ?><div class="alert alert-danger  py-2 small"><?= htmlspecialchars($error)   ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<!-- Stats semáforo -->
<?php
// Helper: construir URL conservando filtros activos
function urlFiltro(array $override): string {
    $base = ['estado'=>'','sem'=>'','colegio'=>'','q'=>'','pag'=>''];
    $actual = ['estado'=>$GLOBALS['fEstado'],'sem'=>$GLOBALS['fSem'],
               'colegio'=>$GLOBALS['fColegio'],'q'=>$GLOBALS['fBusqRaw'] ?? ''];
    $params = array_filter(array_merge($actual, $override), function($v){ return $v !== ''; });
    return '?' . http_build_query($params);
}
?>
<div class="d-flex flex-wrap gap-2 mb-3">
  <a href="<?= urlFiltro(['sem'=>'rojo',     'estado'=>'']) ?>"
     class="stat-chip <?= $fSem==='rojo'?'fw-bold':'' ?>"
     style="background:#fee2e2;color:#991b1b;<?= $fSem==='rojo'?'outline:2px solid #991b1b;':'' ?>">
    <span class="sem-dot sem-rojo"></span><?= $stats['rojos'] ?> Urgentes &gt;7d
  </a>
  <a href="<?= urlFiltro(['sem'=>'amarillo',  'estado'=>'']) ?>"
     class="stat-chip <?= $fSem==='amarillo'?'fw-bold':'' ?>"
     style="background:#fef9c3;color:#854d0e;<?= $fSem==='amarillo'?'outline:2px solid #854d0e;':'' ?>">
    <span class="sem-dot sem-amarillo"></span><?= $stats['amarillos'] ?> En riesgo 5-7d
  </a>
  <a href="<?= urlFiltro(['sem'=>'verde',     'estado'=>'']) ?>"
     class="stat-chip <?= $fSem==='verde'?'fw-bold':'' ?>"
     style="background:#dcfce7;color:#166534;<?= $fSem==='verde'?'outline:2px solid #166534;':'' ?>">
    <span class="sem-dot sem-verde"></span><?= $stats['verdes'] ?> Al d&iacute;a
  </a>
  <?php foreach ($ESTADOS as $k => $e): ?>
  <a href="<?= urlFiltro(['estado'=>$k, 'sem'=>'']) ?>"
     class="stat-chip <?= $fEstado===$k?'fw-bold':'' ?>"
     style="background:<?= $e['bg'] ?>;color:<?= $e['txt'] ?>;<?= $fEstado===$k?'outline:2px solid '.$e['txt'].';':'' ?>">
    <?php
    $statKey = ['pendiente'=>'pendientes','en_produccion'=>'en_prod','listo_envio'=>'listos',
                'despachado'=>'despachados','entregado'=>'entregados','cancelado'=>0][$k] ?? 0;
    echo strip_tags($e['label']) . ': ' . ($statKey && isset($stats[$statKey]) ? $stats[$statKey] : 0);
    ?>
  </a>
  <?php endforeach; ?>
  <?php if ($fEstado || $fSem || $fColegio || ($fBusqRaw??'')): ?>
  <a href="?" class="stat-chip" style="background:#f1f5f9;color:#475569;border:1px solid #e2e8f0">
    &#10005; Limpiar filtros
  </a>
  <?php endif; ?>
</div>

<?php
// Contar total global sin filtros
$totalGlobal = $db->query("SELECT COUNT(*) FROM tienda_pedidos")->fetchColumn();
if ($totalGlobal == 0): ?>
<div class="upload-area" onclick="document.getElementById('csv_inp').click()">
  <i class="bi bi-cloud-upload fs-1 text-primary d-block mb-3"></i>
  <h5 class="fw-bold">Importa tu primer CSV de WooCommerce</h5>
  <p class="text-muted">Los pedidos se guardan en la base de datos y puedes cambiar su estado desde aqu&iacute;</p>
  <span class="btn btn-primary mt-2">Seleccionar CSV</span>
</div>
<?php else: ?>

<!-- Filtros -->
<div class="section-card">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-12 col-md-3">
      <input type="text" name="q" class="form-control form-control-sm" placeholder="Buscar nombre, kit o #pedido..." value="<?= htmlspecialchars($fBusqRaw ?? $fBusq) ?>">
    </div>
    <div class="col-6 col-md-2">
      <select name="estado" class="form-select form-select-sm">
        <option value="">Todos los estados</option>
        <?php foreach ($ESTADOS as $k => $e): ?>
          <option value="<?= $k ?>" <?= $fEstado===$k?'selected':'' ?>><?= $e['label'] ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <select name="sem" class="form-select form-select-sm">
        <option value="">Sem&aacute;foro</option>
        <option value="rojo"       <?= $fSem==='rojo'      ?'selected':'' ?>>Urgente &gt;7d</option>
        <option value="amarillo"   <?= $fSem==='amarillo'  ?'selected':'' ?>>En riesgo 5-7d</option>
        <option value="verde"      <?= $fSem==='verde'     ?'selected':'' ?>>Al d&iacute;a</option>
        <option value="completado" <?= $fSem==='completado'?'selected':'' ?>>Completados</option>
      </select>
    </div>
    <div class="col-12 col-md-3">
      <select name="colegio" class="form-select form-select-sm">
        <option value="">Todos los colegios</option>
        <?php foreach ($colegios_pedidos as $c):
          $val = $c['nombre_csv'] ?: $c['nombre_display'];
        ?>
          <option value="<?= htmlspecialchars($val) ?>" <?= $fColegio===$val?'selected':'' ?>><?= htmlspecialchars($c['nombre_display'] ?: $val) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto"><button type="submit" class="btn btn-primary btn-sm">Filtrar</button></div>
    <?php if ($fBusq||$fEstado||$fSem||$fColegio): ?>
    <div class="col-auto"><a href="?" class="btn btn-outline-secondary btn-sm">Limpiar</a></div>
    <?php endif; ?>
  </form>
</div>

<!-- Tabla -->
<?php if (empty($pedidos)): ?>
  <div class="section-card text-center text-muted py-5"><i class="bi bi-inbox fs-2 d-block mb-2"></i>Sin resultados.</div>
<?php else: ?>
<div class="section-card p-0" style="overflow:hidden">
  <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
    <span class="small text-muted fw-semibold"><?= $total ?> pedidos &mdash; p&aacute;gina <?= $pagina ?> de <?= $totalPags ?></span>
    <a href="stickers.php?<?= htmlspecialchars(http_build_query($_GET)) ?>" target="_blank" class="btn btn-danger btn-sm">
      <i class="bi bi-printer me-1"></i>Etiquetas (6/hoja)
    </a>
  </div>
  <div class="table-responsive">
    <table class="table table-hover mb-0 rt">
      <thead><tr>
        <th style="width:36px;text-align:center">
          <input type="checkbox" class="chk-row" id="chk-all" title="Seleccionar todos" onchange="selTodos(this.checked)">
        </th>
        <th></th><th>#WOO</th><th>Fecha</th><th>Cliente</th>
        <th>Colegio</th><th>Kit</th><th>D&iacute;as</th><th>Estado</th><th>Acciones</th>
      </tr></thead>
      <tbody>
      <?php
      $semCol=['rojo'=>'#ef4444','amarillo'=>'#f59e0b','verde'=>'#22c55e','completado'=>'#94a3b8'];
      foreach ($pedidos as $p):
        $sc = $semCol[$p['semaforo']] ?? '#ccc';
        $est = $ESTADOS[$p['estado']] ?? ['label'=>$p['estado'],'bg'=>'#f1f5f9','txt'=>'#475569'];
      ?>
      <tr class="borde-<?= $p['semaforo'] ?>" id="fila-<?= $p['id'] ?>">
        <td style="text-align:center;border-left:4px solid <?= $sc ?>;padding:.45rem .5rem">
          <input type="checkbox" class="chk-row chk-pedido"
                 value="<?= $p['id'] ?>"
                 data-order="<?= htmlspecialchars($p['woo_order_id']) ?>"
                 onchange="actualizarSel()">
        </td>
        <td><span class="sem-dot sem-<?= $p['semaforo'] ?>"></span></td>
        <td class="fw-semibold" style="white-space:nowrap">
          <a href="ver.php?id=<?= $p['id'] ?>" class="text-decoration-none">#<?= htmlspecialchars($p['woo_order_id']) ?></a>
        </td>
        <td style="white-space:nowrap"><?= rbs_fecha_fmt($p['fecha_compra']) ?></td>
        <td>
          <div class="fw-semibold"><?= htmlspecialchars($p['cliente_nombre']) ?></div>
          <?php if ($p['cliente_telefono']): ?><div class="text-muted" style="font-size:.72rem"><?= htmlspecialchars($p['cliente_telefono']) ?></div><?php endif; ?>
        </td>
        <td style="color:#1d4ed8;font-size:.78rem;max-width:140px">
          <?= $p['colegio_bd'] ? htmlspecialchars($p['colegio_bd']) : ($p['colegio_nombre'] ? htmlspecialchars($p['colegio_nombre']) : '<span class="text-muted">&mdash;</span>') ?>
        </td>
        <td style="max-width:160px;font-size:.77rem;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($p['kit_nombre']) ?>">
          <?= htmlspecialchars($p['kit_nombre']) ?>
        </td>
        <td style="text-align:center;font-weight:700;color:<?= $sc ?>"><?= $p['dias'] ?>d</td>
        <td>
          <span class="badge" style="background:<?= $est['bg'] ?>;color:<?= $est['txt'] ?>;font-size:.72rem"><?= $est['label'] ?></span>
        </td>
        <td>
          <!-- Cambio rápido de estado inline -->
          <form method="POST" class="d-flex gap-1 align-items-center">
            <input type="hidden" name="action"    value="cambiar_estado">
            <input type="hidden" name="pedido_id" value="<?= $p['id'] ?>">
            <input type="hidden" name="csrf"      value="<?= Auth::csrfToken() ?>">
            <select name="estado_nuevo" class="estado-sel" onchange="this.form.submit()">
              <?php foreach ($ESTADOS as $k => $e): ?>
                <option value="<?= $k ?>" <?= $p['estado']===$k?'selected':'' ?>><?= strip_tags($e['label']) ?></option>
              <?php endforeach; ?>
            </select>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <!-- Paginación -->
  <?php if ($totalPags > 1): ?>
  <div class="px-3 py-2 border-top">
    <nav><ul class="pagination pagination-sm mb-0 justify-content-center">
      <?php for ($i=1;$i<=$totalPags;$i++): $p2=array_merge($_GET,['pag'=>$i]); ?>
      <li class="page-item <?= $i===$pagina?'active':'' ?>">
        <a class="page-link" href="?<?= http_build_query($p2) ?>"><?= $i ?></a>
      </li>
      <?php endfor; ?>
    </ul></nav>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<!-- Barra flotante de selección -->
<div class="barra-sel" id="barra-sel">
  <span class="cnt"><span id="cnt-sel">0</span> pedido(s) seleccionado(s)</span>
  <div class="d-flex gap-2">
    <button class="btn btn-danger btn-sm fw-bold" onclick="imprimirSeleccionados()">
      <i class="bi bi-printer me-1"></i>Imprimir stickers
    </button>
    <button class="btn btn-outline-light btn-sm" onclick="deselTodos()">
      Cancelar
    </button>
  </div>
</div>

<script>
function actualizarSel() {
    var chks = document.querySelectorAll('.chk-pedido:checked');
    var n    = chks.length;
    var barra = document.getElementById('barra-sel');
    document.getElementById('cnt-sel').textContent = n;

    // Resaltar filas seleccionadas
    document.querySelectorAll('.chk-pedido').forEach(function(c) {
        var fila = c.closest('tr');
        if (c.checked) fila.classList.add('fila-sel');
        else           fila.classList.remove('fila-sel');
    });

    // Mostrar / ocultar barra
    if (n > 0) barra.classList.add('visible');
    else       barra.classList.remove('visible');

    // Sincronizar checkbox cabecera
    var todos = document.querySelectorAll('.chk-pedido');
    document.getElementById('chk-all').indeterminate = n > 0 && n < todos.length;
    document.getElementById('chk-all').checked = n === todos.length && todos.length > 0;
}

function selTodos(on) {
    document.querySelectorAll('.chk-pedido').forEach(function(c){ c.checked = on; });
    actualizarSel();
}

function deselTodos() {
    document.querySelectorAll('.chk-pedido').forEach(function(c){ c.checked = false; });
    actualizarSel();
}

function imprimirSeleccionados() {
    var ids = Array.from(document.querySelectorAll('.chk-pedido:checked'))
                   .map(function(c){ return c.value; });
    if (ids.length === 0) { alert('Selecciona al menos un pedido.'); return; }
    // Abrir stickers con IDs seleccionados
    window.open('stickers.php?ids=' + ids.join(','), '_blank');
}
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
