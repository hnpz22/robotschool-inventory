<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();

$db         = Database::get();
$pageTitle  = 'Kits';
$activeMenu = 'kits';
$error = $success = '';

// ── Eliminar kit ──────────────────────────────────────────────
if (isset($_GET['del']) && Auth::csrfVerify($_GET['csrf'] ?? '')) {
    $delId = (int)$_GET['del'];
    try {
        // Verificar que no esté asignado a cursos
        $enCursos = $db->query("SELECT COUNT(*) FROM cursos WHERE kit_id=$delId")->fetchColumn();
        if ($enCursos > 0) {
            $error = 'No se puede eliminar: el kit est&aacute; asignado a ' . $enCursos . ' curso(s). Desasign&aacute;lo primero.';
        } else {
            $db->beginTransaction();
            $db->prepare("DELETE FROM kit_elementos  WHERE kit_id=?")->execute([$delId]);
            $db->prepare("DELETE FROM kit_prototipos WHERE kit_id=?")->execute([$delId]);
            $db->prepare("UPDATE kits SET activo=0 WHERE id=?")->execute([$delId]);
            $db->commit();
            $success = 'Kit eliminado correctamente.';
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error = $e->getMessage();
    }
}

// ── Filtros ──────────────────────────────────────────────────
$buscar   = trim($_GET['q']       ?? '');
$fColegio = (int)($_GET['colegio'] ?? 0);
$fNivel   = $_GET['nivel']         ?? '';

$where  = ["k.activo=1"];
$params = [];
if ($buscar) {
    $where[]  = "(k.nombre LIKE ? OR k.codigo LIKE ?)";
    $params   = array_merge($params, ["%$buscar%", "%$buscar%"]);
}
if ($fColegio) { $where[] = "k.colegio_id=?";   $params[] = $fColegio; }
if ($fNivel)   { $where[] = "k.nivel=?";         $params[] = $fNivel; }
$whereStr = implode(' AND ', $where);

$st = $db->prepare("
    SELECT k.*,
           c.nombre  AS colegio,
           tc.nombre AS caja,
           COUNT(DISTINCT ke.id)  AS num_elementos,
           COUNT(DISTINCT kp.id)  AS num_prototipos,
           SUM(ke.cantidad)       AS total_elem_uds
    FROM kits k
    LEFT JOIN colegios       c  ON c.id  = k.colegio_id
    LEFT JOIN tipos_caja     tc ON tc.id = k.tipo_caja_id
    LEFT JOIN kit_elementos  ke ON ke.kit_id = k.id
    LEFT JOIN kit_prototipos kp ON kp.kit_id = k.id
    WHERE $whereStr
    GROUP BY k.id
    ORDER BY k.created_at DESC
");
$st->execute($params);
$kits = $st->fetchAll();

$colegios = $db->query("SELECT id,nombre FROM colegios WHERE activo=1 ORDER BY nombre")->fetchAll();

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<!-- Header -->
<div class="page-header">
  <div>
    <h4 class="page-header-title">&#x1F6CD; Kits Armados</h4>
    <p class="page-header-sub"><?= count($kits) ?> kits registrados</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="<?= APP_URL ?>/modules/kits/constructor.php" class="btn btn-success btn-sm fw-bold">
      <i class="bi bi-tools me-1"></i>Nuevo Kit (Constructor)
    </a>
    <a href="<?= APP_URL ?>/modules/kits/form.php" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-plus-lg me-1"></i>Nuevo Kit (Formulario)
    </a>
  </div>
</div>

<?php if ($error):   ?><div class="alert alert-danger  py-2 small"><?= $error   ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<!-- Filtros -->
<div class="filter-bar">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-12 col-md-4">
      <input type="text" name="q" class="form-control form-control-sm"
             placeholder="&#128269; Buscar por nombre o c&oacute;digo..."
             value="<?= htmlspecialchars($buscar) ?>">
    </div>
    <div class="col-6 col-md-3">
      <select name="colegio" class="form-select form-select-sm">
        <option value="">Todos los colegios</option>
        <?php foreach ($colegios as $c): ?>
          <option value="<?= $c['id'] ?>" <?= $fColegio==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['nombre']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <select name="nivel" class="form-select form-select-sm">
        <option value="">Todos los niveles</option>
        <option value="basico"      <?= $fNivel==='basico'?'selected':'' ?>>B&aacute;sico</option>
        <option value="intermedio"  <?= $fNivel==='intermedio'?'selected':'' ?>>Intermedio</option>
        <option value="avanzado"    <?= $fNivel==='avanzado'?'selected':'' ?>>Avanzado</option>
      </select>
    </div>
    <div class="col-auto">
      <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
    </div>
    <?php if ($buscar || $fColegio || $fNivel): ?>
    <div class="col-auto">
      <a href="?" class="btn btn-outline-secondary btn-sm">Limpiar</a>
    </div>
    <?php endif; ?>
  </form>
</div>

<!-- Grid de kits -->
<?php if (empty($kits)): ?>
<div class="section-card text-center text-muted py-5">
  <i class="bi bi-bag-check fs-2 d-block mb-2"></i>
  No hay kits<?= $buscar||$fColegio||$fNivel ? ' con los filtros seleccionados' : ' creados a&uacute;n' ?>.
  <br>
  <a href="<?= APP_URL ?>/modules/kits/constructor.php" class="btn btn-success btn-sm mt-3">
    <i class="bi bi-tools me-1"></i>Crear primer kit
  </a>
</div>
<?php else: ?>
<div class="row g-3">
<?php foreach ($kits as $k):
  $nivelClasses = [
    'basico'      => 'bg-success-subtle text-success',
    'intermedio'  => 'bg-warning-subtle text-warning',
    'avanzado'    => 'bg-danger-subtle text-danger',
  ];
  $nivelCls = $nivelClasses[$k['nivel'] ?? 'basico'] ?? 'bg-success-subtle text-success';
?>
<div class="col-md-6 col-xl-4">
  <div class="kit-card">

    <!-- Foto o placeholder -->
    <div class="kit-card-img">
      <span style="position:absolute;font-size:2.5rem;color:#6366f1">&#x1F4E6;</span>
      <?php if ($k['foto']): ?>
        <img src="<?= htmlspecialchars(fotoUrl($k['foto'])) ?>" alt=""
             style="width:100%;height:100%;object-fit:cover;position:relative;z-index:1"
             onerror="this.style.display='none'">
      <?php endif; ?>
    </div>

    <div class="kit-card-body">
      <!-- Código -->
      <div class="kit-card-code"><?= htmlspecialchars($k['codigo']) ?></div>

      <!-- Nombre + nivel -->
      <div class="d-flex justify-content-between align-items-start">
        <h6 class="kit-card-name"><?= htmlspecialchars($k['nombre']) ?></h6>
        <span class="badge <?= $nivelCls ?> rounded-pill" style="font-size:.7rem"><?= ucfirst($k['nivel'] ?? 'basico') ?></span>
      </div>

      <!-- Meta -->
      <div class="kit-card-meta">
        <?php if ($k['colegio']): ?>
          <span><i class="bi bi-building me-1"></i><?= htmlspecialchars($k['colegio']) ?></span>
        <?php endif; ?>
        <?php if ($k['caja']): ?>
          <span><i class="bi bi-box me-1"></i><?= htmlspecialchars($k['caja']) ?></span>
        <?php endif; ?>
        <span>
          <i class="bi bi-layers me-1"></i>
          <?= $k['num_elementos'] ?> tipo<?= $k['num_elementos']!=1?'s':'' ?> de elemento
          <?php if ($k['num_prototipos'] > 0): ?>
            &middot; <?= $k['num_prototipos'] ?> prototipo<?= $k['num_prototipos']!=1?'s':'' ?>
          <?php endif; ?>
        </span>
      </div>

      <!-- Costo -->
      <?php if ($k['costo_cop'] > 0): ?>
      <div class="small fw-semibold text-success">
        Costo: <?= cop($k['costo_cop']) ?>
        <?php if ($k['precio_cop'] > 0): ?>
          &middot; Precio: <span class="text-primary"><?= cop($k['precio_cop']) ?></span>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Acciones -->
    <div class="kit-card-footer">
      <a href="<?= APP_URL ?>/modules/kits/constructor.php?kit_id=<?= $k['id'] ?>"
         class="btn btn-success btn-sm flex-grow-1" title="Editar componentes del kit">
        <i class="bi bi-tools me-1"></i>Editar Kit
      </a>
      <a href="<?= APP_URL ?>/modules/kits/form.php?id=<?= $k['id'] ?>"
         class="btn btn-outline-primary btn-sm" title="Editar datos del kit">
        <i class="bi bi-pencil"></i>
      </a>
      <a href="<?= APP_URL ?>/modules/kits/sticker_caja.php?kit_id=<?= $k['id'] ?>"
         target="_blank" class="btn btn-outline-warning btn-sm" title="Sticker para la caja">
        <i class="bi bi-printer"></i>
      </a>
      <button onclick="imprimirBarcode('<?= $k['codigo'] ?>','<?= addslashes($k['nombre']) ?>')"
              class="btn btn-outline-secondary btn-sm" title="Código de barras">
        <i class="bi bi-upc-scan"></i>
      </button>
      <?php if (Auth::isAdmin() || Auth::puede('kits','eliminar')): ?>
      <a href="?del=<?= $k['id'] ?>&csrf=<?= Auth::csrfToken() ?>"
         class="btn btn-outline-danger btn-sm"
         title="Eliminar kit"
         onclick="return confirm('¿Eliminar el kit <?= addslashes($k['nombre']) ?>?\n\nEsta acción no se puede deshacer.')">
        <i class="bi bi-trash"></i>
      </a>
      <?php endif; ?>
    </div>

  </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
