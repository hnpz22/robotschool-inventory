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
<style>
.kit-card{background:#fff;border:1px solid #e2e8f0;border-radius:14px;overflow:hidden;transition:.15s;height:100%}
.kit-card:hover{box-shadow:0 4px 16px rgba(0,0,0,.08);border-color:#93c5fd}
.nivel-pill{font-size:.68rem;padding:.18rem .55rem;border-radius:20px;font-weight:700}
.nivel-basico    {background:#dcfce7;color:#166534}
.nivel-intermedio{background:#fef9c3;color:#854d0e}
.nivel-avanzado  {background:#fee2e2;color:#991b1b}
.section-card{background:#fff;border-radius:14px;border:1px solid #e2e8f0;padding:1rem 1.2rem;margin-bottom:1rem}
.btn-accion{padding:.25rem .5rem;font-size:.75rem;border-radius:6px}
</style>

<!-- Header -->
<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h4 class="fw-bold mb-0">&#x1F6CD; Kits Armados</h4>
    <p class="text-muted small mb-0"><?= count($kits) ?> kits registrados</p>
  </div>
  <div class="d-flex gap-2">
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
<div class="section-card">
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
<?php foreach ($kits as $k): ?>
<div class="col-md-6 col-xl-4">
  <div class="kit-card">

    <!-- Foto o placeholder -->
    <div style="height:160px;overflow:hidden;background:linear-gradient(135deg,#f0f4ff,#e0e7ff);position:relative;display:flex;align-items:center;justify-content:center">
      <span style="font-size:2.5rem;position:absolute;color:#6366f1">&#x1F4E6;</span>
      <?php if ($k['foto']): ?>
        <img src="<?= htmlspecialchars(fotoUrl($k['foto'])) ?>" alt=""
             style="width:100%;height:100%;object-fit:cover;position:relative;z-index:1"
             onerror="this.style.display='none'">
      <?php endif; ?>
    </div>

    <div class="p-3">
      <!-- Código + nivel -->
      <div class="d-flex align-items-center justify-content-between mb-1">
        <code style="font-size:.75rem;color:#185FA5"><?= htmlspecialchars($k['codigo']) ?></code>
        <span class="nivel-pill nivel-<?= $k['nivel'] ?? 'basico' ?>"><?= ucfirst($k['nivel'] ?? 'basico') ?></span>
      </div>

      <!-- Nombre -->
      <h6 class="fw-bold mb-1" style="font-size:.92rem"><?= htmlspecialchars($k['nombre']) ?></h6>

      <!-- Info -->
      <div class="text-muted small mb-2" style="font-size:.78rem">
        <?php if ($k['colegio']): ?>
          <div><i class="bi bi-building me-1"></i><?= htmlspecialchars($k['colegio']) ?></div>
        <?php endif; ?>
        <?php if ($k['caja']): ?>
          <div><i class="bi bi-box-seam me-1"></i><?= htmlspecialchars($k['caja']) ?></div>
        <?php endif; ?>
        <div>
          <i class="bi bi-list-check me-1"></i>
          <?= $k['num_elementos'] ?> tipo<?= $k['num_elementos']!=1?'s':'' ?> de elemento
          <?php if ($k['num_prototipos'] > 0): ?>
            &middot; <?= $k['num_prototipos'] ?> prototipo<?= $k['num_prototipos']!=1?'s':'' ?>
          <?php endif; ?>
        </div>
      </div>

      <!-- Costo -->
      <?php if ($k['costo_cop'] > 0): ?>
      <div class="mb-2 p-2 rounded" style="background:#f0fdf4;font-size:.8rem">
        <span class="text-muted">Costo:</span>
        <strong class="text-success ms-1"><?= cop($k['costo_cop']) ?></strong>
        <?php if ($k['precio_cop'] > 0): ?>
          &nbsp;&middot;&nbsp;
          <span class="text-muted">Precio:</span>
          <strong class="text-primary ms-1"><?= cop($k['precio_cop']) ?></strong>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Acciones -->
      <div class="d-flex gap-1 flex-wrap">
        <!-- Editar componentes con constructor -->
        <a href="<?= APP_URL ?>/modules/kits/constructor.php?kit_id=<?= $k['id'] ?>"
           class="btn btn-success btn-accion flex-grow-1" title="Editar componentes del kit">
          <i class="bi bi-tools me-1"></i>Editar Kit
        </a>
        <!-- Editar datos básicos -->
        <a href="<?= APP_URL ?>/modules/kits/form.php?id=<?= $k['id'] ?>"
           class="btn btn-outline-primary btn-accion" title="Editar datos del kit">
          <i class="bi bi-pencil"></i>
        </a>
        <!-- Sticker de caja -->
        <a href="<?= APP_URL ?>/modules/kits/sticker_caja.php?kit_id=<?= $k['id'] ?>"
           target="_blank" class="btn btn-outline-warning btn-accion" title="Sticker para la caja">
          <i class="bi bi-printer"></i>
        </a>
        <!-- Código de barras -->
        <button onclick="imprimirBarcode('<?= $k['codigo'] ?>','<?= addslashes($k['nombre']) ?>')"
                class="btn btn-outline-secondary btn-accion" title="Código de barras">
          <i class="bi bi-upc-scan"></i>
        </button>
        <!-- Eliminar -->
        <?php if (Auth::isAdmin() || Auth::puede('kits','eliminar')): ?>
        <a href="?del=<?= $k['id'] ?>&csrf=<?= Auth::csrfToken() ?>"
           class="btn btn-outline-danger btn-accion"
           title="Eliminar kit"
           onclick="return confirm('¿Eliminar el kit <?= addslashes($k['nombre']) ?>?\n\nEsta acción no se puede deshacer.')">
          <i class="bi bi-trash"></i>
        </a>
        <?php endif; ?>
      </div>

    </div>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
