<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();

$db = Database::get();
$pageTitle  = 'Proveedores';
$activeMenu = 'proveedores';

// Eliminar
if (isset($_GET['del']) && Auth::csrfVerify($_GET['csrf'] ?? '')) {
    Auth::requireAdmin();
    $db->prepare("UPDATE proveedores SET activo=0 WHERE id=?")->execute([(int)$_GET['del']]);
    header('Location: ?ok=1'); exit;
}

$q      = trim($_GET['q']    ?? '');
$tipo   = trim($_GET['tipo'] ?? '');
$pais   = trim($_GET['pais'] ?? '');

$where  = ['p.activo=1'];
$params = [];
if ($q)    { $where[] = '(p.nombre LIKE ? OR p.nombre_comercial LIKE ? OR p.email LIKE ?)'; $params = array_merge($params,["%$q%","%$q%","%$q%"]); }
if ($tipo) { $where[] = 'p.tipo=?';  $params[] = $tipo; }
if ($pais) { $where[] = 'p.pais=?';  $params[] = $pais; }
$ws = implode(' AND ', $where);

$proveedores = $db->prepare("
  SELECT p.*,
    COUNT(DISTINCT e.id)         AS total_elementos,
    COUNT(DISTINCT ped.id)       AS total_pedidos,
    COALESCE(SUM(ped.costo_total_cop),0) AS total_gastado_cop
  FROM proveedores p
  LEFT JOIN elementos e   ON e.proveedor_id = p.id AND e.activo = 1
  LEFT JOIN pedidos_importacion ped ON ped.proveedor_id = p.id AND ped.estado='liquidado'
  WHERE $ws
  GROUP BY p.id
  ORDER BY p.es_preferido DESC, p.tipo, p.nombre
");
$proveedores->execute($params);
$proveedores = $proveedores->fetchAll();

// Stats resumen
$stats = $db->query("
  SELECT
    COUNT(*)                                                AS total,
    SUM(CASE WHEN tipo LIKE '%china%'      THEN 1 ELSE 0 END) AS china,
    SUM(CASE WHEN tipo LIKE '%colombia%'   THEN 1 ELSE 0 END) AS colombia,
    SUM(CASE WHEN tipo='cajas_empaque'     THEN 1 ELSE 0 END) AS cajas,
    SUM(CASE WHEN tipo='stickers_impresion' THEN 1 ELSE 0 END) AS stickers,
    SUM(CASE WHEN tipo='libros_material'   THEN 1 ELSE 0 END) AS libros,
    SUM(CASE WHEN tipo='fabricacion_materiales' THEN 1 ELSE 0 END) AS fabricacion
  FROM proveedores WHERE activo=1
")->fetch();

$paises = $db->query("SELECT DISTINCT pais FROM proveedores WHERE activo=1 ORDER BY pais")->fetchAll(PDO::FETCH_COLUMN);

require_once dirname(__DIR__, 2) . '/includes/header.php';

// Configuración visual de tipos
$tipoConfig = [
  'electronica_china'      => ['🇨🇳', 'Electrónica China',       'danger'],
  'electronica_colombia'   => ['🇨🇴', 'Electrónica Colombia',    'success'],
  'cajas_empaque'          => ['&#x1F4E6;', 'Cajas y Empaque',          'warning'],
  'stickers_impresion'     => ['🖨️', 'Stickers e Impresión',    'info'],
  'libros_material'        => ['📚', 'Libros y Material',        'primary'],
  'fabricacion_materiales' => ['🔨', 'Materiales Fabricación',   'secondary'],
  'transporte'             => ['🚚', 'Transporte/Logística',     'dark'],
  'otro'                   => ['📋', 'Otro',                     'light'],
];
?>

<!-- Header -->
<div class="page-header">
  <div>
    <h4 class="page-header-title">Proveedores</h4>
    <p class="page-header-sub">Gestión de todos los proveedores de ROBOTSchool</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="<?= APP_URL ?>/modules/importaciones/proveedor_form.php" class="btn btn-primary btn-sm">
      <i class="bi bi-plus-lg me-1"></i>Nuevo Proveedor
    </a>
  </div>
</div>

<?php if (!empty($_GET['ok'])): ?>
<div class="alert alert-success py-2 alert-dismissible">
  <i class="bi bi-check-circle me-2"></i>Acción realizada correctamente.
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- ── Cards resumen por tipo ── -->
<div class="row g-2 mb-4">
  <?php
  $tipoCards = [
    ['🇨🇳', 'China',          $stats['china'],       'danger',    'tipo=electronica_china'],
    ['🇨🇴', 'Colombia',       $stats['colombia'],    'success',   'tipo=electronica_colombia'],
    ['&#x1F4E6;',  'Cajas',          $stats['cajas'],       'warning',   'tipo=cajas_empaque'],
    ['🖨️', 'Stickers',       $stats['stickers'],    'info',      'tipo=stickers_impresion'],
    ['📚',  'Libros',         $stats['libros'],      'primary',   'tipo=libros_material'],
    ['🔨',  'Fabricación',    $stats['fabricacion'], 'secondary', 'tipo=fabricacion_materiales'],
  ];
  foreach ($tipoCards as [$ico, $lbl, $num, $color, $qs]): ?>
  <div class="col-md-2 col-4">
    <a href="?<?= $qs ?>" class="text-decoration-none">
      <div class="card stat-card h-100 <?= $tipo && strpos($qs, $tipo)!==false ? "border-$color border-2":'' ?>">
        <div class="card-body p-2 text-center">
          <div style="font-size:1.5rem;"><?= $ico ?></div>
          <div class="fw-bold text-<?= $color ?> fs-5"><?= $num ?></div>
          <div class="text-muted" style="font-size:.72rem;"><?= $lbl ?></div>
        </div>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<!-- ── Filtros ── -->
<div class="filter-bar">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-4">
      <label class="form-label mb-1">Buscar</label>
      <div class="input-group input-group-sm">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" name="q" class="form-control" placeholder="Nombre, email..." value="<?= htmlspecialchars($q) ?>">
      </div>
    </div>
    <div class="col-md-3">
      <label class="form-label mb-1">Tipo</label>
      <select name="tipo" class="form-select form-select-sm">
        <option value="">Todos los tipos</option>
        <?php foreach ($tipoConfig as $val => [$ico, $lbl, $color]): ?>
          <option value="<?= $val ?>" <?= $tipo===$val?'selected':'' ?>><?= $ico ?> <?= $lbl ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label mb-1">País</label>
      <select name="pais" class="form-select form-select-sm">
        <option value="">Todos</option>
        <?php foreach ($paises as $p): ?>
          <option value="<?= htmlspecialchars($p) ?>" <?= $pais===$p?'selected':'' ?>><?= htmlspecialchars($p) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3 d-flex gap-2">
      <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
      <a href="?" class="btn btn-outline-secondary btn-sm">Limpiar</a>
    </div>
  </form>
</div>

<!-- ── Listado agrupado por tipo ── -->
<?php
// Agrupar por tipo
$porTipo = [];
foreach ($proveedores as $p) {
    $porTipo[$p['tipo']][] = $p;
}

// Orden de presentación
$ordenTipo = ['electronica_china','electronica_colombia','cajas_empaque','stickers_impresion','libros_material','fabricacion_materiales','transporte','otro'];
?>

<?php foreach ($ordenTipo as $tkey):
  if (empty($porTipo[$tkey])) continue;
  [$ico, $tipoNombre, $color] = $tipoConfig[$tkey];
?>
<div class="mb-4">
  <!-- Encabezado de grupo -->
  <div class="d-flex align-items-center gap-2 mb-3">
    <span style="font-size:1.4rem;"><?= $ico ?></span>
    <h6 class="fw-bold mb-0 text-<?= $color ?>"><?= $tipoNombre ?></h6>
    <span class="badge bg-<?= $color ?>"><?= count($porTipo[$tkey]) ?></span>
  </div>

  <div class="row g-3">
  <?php foreach ($porTipo[$tkey] as $prov): ?>
  <div class="col-md-6 col-xl-4">
    <div class="card stat-card h-100 <?= $prov['es_preferido'] ? "border-$color border-2" : '' ?>">
      <div class="card-body p-3">

        <!-- Header tarjeta -->
        <div class="d-flex align-items-start gap-2 mb-2">
          <?php if ($prov['foto']): ?>
            <img src="<?= UPLOAD_URL . htmlspecialchars($prov['foto']) ?>"
                 class="rounded" style="width:44px;height:44px;object-fit:contain;border:1px solid #eee;padding:3px;background:#fff;" alt="">
          <?php else: ?>
            <div class="rounded d-flex align-items-center justify-content-center flex-shrink-0"
                 style="width:44px;height:44px;background:<?= ['danger'=>'#fef2f2','success'=>'#f0fdf4','warning'=>'#fffbeb','info'=>'#eff6ff','primary'=>'#eff6ff','secondary'=>'#f9fafb'][$color]??'#f3f4f6' ?>;font-size:1.5rem;">
              <?= $ico ?>
            </div>
          <?php endif; ?>
          <div class="flex-grow-1 min-width-0">
            <div class="fw-bold text-truncate"><?= htmlspecialchars($prov['nombre']) ?></div>
            <?php if ($prov['nombre_comercial'] && $prov['nombre_comercial'] !== $prov['nombre']): ?>
              <div class="text-muted small text-truncate"><?= htmlspecialchars($prov['nombre_comercial']) ?></div>
            <?php endif; ?>
            <div class="d-flex gap-1 mt-1 flex-wrap">
              <span class="badge" style="background:<?= ['China'=>'#dc2626','Colombia'=>'#16a34a','Estados Unidos'=>'#2563eb'][$prov['pais']]??'#6b7280' ?>;font-size:.68rem;">
                <?= htmlspecialchars($prov['pais']) ?>
              </span>
              <?php if ($prov['es_preferido']): ?>
                <span class="badge bg-<?= $color ?>" style="font-size:.68rem;">⭐ Preferido</span>
              <?php endif; ?>
              <?php if ($prov['requiere_dhl']): ?>
                <span class="badge bg-dark" style="font-size:.68rem;">&#x2708;&#xFE0F; DHL</span>
              <?php endif; ?>
            </div>
          </div>
          <!-- Calificación -->
          <div class="text-warning text-nowrap" style="font-size:.85rem;">
            <?= str_repeat('★', $prov['calificacion'] ?? 0) ?><?= str_repeat('☆', 5-($prov['calificacion'] ?? 0)) ?>
          </div>
        </div>

        <!-- Info de contacto -->
        <div class="mb-2" style="font-size:.8rem;">
          <?php if ($prov['email']): ?>
            <div class="text-truncate"><i class="bi bi-envelope me-1 text-muted"></i><?= htmlspecialchars($prov['email']) ?></div>
          <?php endif; ?>
          <?php if ($prov['telefono']): ?>
            <div><i class="bi bi-telephone me-1 text-muted"></i><?= htmlspecialchars($prov['telefono']) ?></div>
          <?php endif; ?>
          <?php if ($prov['whatsapp']): ?>
            <div>
              <a href="https://wa.me/<?= preg_replace('/[^0-9]/','',$prov['whatsapp']) ?>" target="_blank" class="text-success text-decoration-none">
                <i class="bi bi-whatsapp me-1"></i><?= htmlspecialchars($prov['whatsapp']) ?>
              </a>
            </div>
          <?php endif; ?>
          <?php if ($prov['url_tienda']): ?>
            <div class="text-truncate">
              <a href="<?= htmlspecialchars($prov['url_tienda']) ?>" target="_blank" class="text-primary text-decoration-none">
                <i class="bi bi-globe me-1"></i><?= parse_url($prov['url_tienda'],PHP_URL_HOST) ?>
              </a>
            </div>
          <?php endif; ?>
        </div>

        <!-- Stats -->
        <div class="d-flex gap-2 mb-2" style="font-size:.78rem;">
          <?php if ($prov['tiempo_entrega_dias']): ?>
            <span class="text-muted"><i class="bi bi-clock me-1"></i><?= $prov['tiempo_entrega_dias'] ?> días</span>
          <?php endif; ?>
          <?php if ($prov['moneda']): ?>
            <span class="text-muted"><i class="bi bi-currency-exchange me-1"></i><?= $prov['moneda'] ?></span>
          <?php endif; ?>
          <?php if ($prov['incoterm']): ?>
            <span class="text-muted">· <?= $prov['incoterm'] ?></span>
          <?php endif; ?>
        </div>

        <!-- Categorías producto -->
        <?php if ($prov['categorias_producto']): ?>
          <div class="text-muted p-2 rounded mb-2" style="background:#f8fafc;font-size:.76rem;line-height:1.4;">
            <?= htmlspecialchars(mb_substr($prov['categorias_producto'], 0, 100)) ?><?= strlen($prov['categorias_producto'])>100?'...':'' ?>
          </div>
        <?php endif; ?>

        <!-- KPIs -->
        <div class="d-flex gap-3 mb-3" style="font-size:.78rem;">
          <div class="text-center">
            <div class="fw-bold text-primary"><?= $prov['total_elementos'] ?></div>
            <div class="text-muted">Elementos</div>
          </div>
          <div class="text-center">
            <div class="fw-bold text-success"><?= $prov['total_pedidos'] ?></div>
            <div class="text-muted">Pedidos</div>
          </div>
          <?php if ($prov['total_gastado_cop'] > 0): ?>
          <div class="text-center">
            <div class="fw-bold text-warning"><?= cop($prov['total_gastado_cop']) ?></div>
            <div class="text-muted">Comprado</div>
          </div>
          <?php endif; ?>
          <?php if ($prov['minimo_pedido']): ?>
          <div class="text-center ms-auto">
            <div class="text-muted" style="font-size:.7rem;">Mínimo</div>
            <div class="fw-semibold" style="font-size:.75rem;"><?= htmlspecialchars($prov['minimo_pedido']) ?></div>
          </div>
          <?php endif; ?>
        </div>

        <!-- Acciones -->
        <div class="d-flex gap-1">
          <a href="<?= APP_URL ?>/modules/importaciones/proveedor_form.php?id=<?= $prov['id'] ?>"
             class="btn btn-sm btn-outline-primary flex-grow-1">
            <i class="bi bi-pencil me-1"></i>Editar
          </a>
          <?php if ($prov['url_tienda']): ?>
          <a href="<?= htmlspecialchars($prov['url_tienda']) ?>" target="_blank"
             class="btn btn-sm btn-outline-secondary" title="Abrir tienda">
            <i class="bi bi-shop"></i>
          </a>
          <?php endif; ?>
          <?php if ($prov['whatsapp']): ?>
          <a href="https://wa.me/<?= preg_replace('/[^0-9]/','',$prov['whatsapp']) ?>" target="_blank"
             class="btn btn-sm btn-outline-success" title="WhatsApp">
            <i class="bi bi-whatsapp"></i>
          </a>
          <?php endif; ?>
          <?php if (Auth::isAdmin()): ?>
          <a href="?del=<?= $prov['id'] ?>&csrf=<?= Auth::csrfToken() ?>"
             class="btn btn-sm btn-outline-danger"
             data-confirm="¿Desactivar proveedor '<?= addslashes($prov['nombre']) ?>'?">
            <i class="bi bi-trash"></i>
          </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  </div>
</div>
<?php endforeach; ?>

<?php if (empty($proveedores)): ?>
<div class="text-center text-muted py-5">
  <i class="bi bi-shop fs-2 d-block mb-2"></i>No se encontraron proveedores
</div>
<?php endif; ?>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
