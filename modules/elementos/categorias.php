<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::requireAdmin();

$db = Database::get();
$pageTitle  = 'Categorías de Elementos';
$activeMenu = 'categorias';
$error = $success = '';

// ── POST: crear / editar ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::csrfVerify($_POST['csrf'] ?? '')) die('Token de seguridad inválido.');

    $id          = (int)($_POST['id'] ?? 0);
    $nombre      = trim($_POST['nombre']      ?? '');
    $prefijo     = strtoupper(trim($_POST['prefijo'] ?? ''));
    $descripcion = trim($_POST['descripcion'] ?? '');
    $icono       = trim($_POST['icono']       ?? 'bi-box');
    $color       = trim($_POST['color']       ?? '#3a72e8');
    $activa      = isset($_POST['activa']) ? 1 : 0;

    try {
        if (!$nombre || !$prefijo) throw new Exception('Nombre y prefijo son obligatorios.');
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) $color = '#3a72e8';

        if ($id) {
            $db->prepare("UPDATE categorias SET nombre=?,prefijo=?,descripcion=?,icono=?,color=?,activa=? WHERE id=?")
               ->execute([$nombre, $prefijo, $descripcion, $icono, $color, $activa, $id]);
            $success = 'Categoría actualizada correctamente.';
        } else {
            $db->prepare("INSERT INTO categorias (nombre,prefijo,descripcion,icono,color,activa) VALUES (?,?,?,?,?,?)")
               ->execute([$nombre, $prefijo, $descripcion, $icono, $color, $activa]);
            $success = 'Categoría creada correctamente.';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// ── GET: baja lógica ──────────────────────────────────────────────────────────
if (isset($_GET['del']) && Auth::csrfVerify($_GET['csrf'] ?? '')) {
    $delId = (int)$_GET['del'];
    // Verificar que no tenga elementos activos asociados
    $stEnUso = $db->prepare("SELECT COUNT(*) FROM elementos WHERE categoria_id = ? AND activo = 1");
    $stEnUso->execute([$delId]);
    $enUso = (int)$stEnUso->fetchColumn();
    if ($enUso > 0) {
        $error = "No se puede desactivar: la categoría tiene $enUso elemento(s) activo(s) asociado(s).";
    } else {
        $db->prepare("UPDATE categorias SET activa=0 WHERE id=?")->execute([$delId]);
        $success = 'Categoría desactivada.';
    }
}

// ── GET: reactivar ────────────────────────────────────────────────────────────
if (isset($_GET['act']) && Auth::csrfVerify($_GET['csrf'] ?? '')) {
    $db->prepare("UPDATE categorias SET activa=1 WHERE id=?")->execute([(int)$_GET['act']]);
    $success = 'Categoría reactivada.';
}

// ── Datos ─────────────────────────────────────────────────────────────────────
$categorias = $db->query("
    SELECT c.*,
           COUNT(e.id)   AS total_elem,
           SUM(CASE WHEN e.activo=1 AND e.stock_actual<=0 THEN 1 ELSE 0 END) AS sin_stock
    FROM categorias c
    LEFT JOIN elementos e ON e.categoria_id=c.id AND e.activo=1
    GROUP BY c.id
    ORDER BY c.activa DESC, c.nombre
")->fetchAll();

// Cargar para edición
$editCat = null;
if (isset($_GET['edit'])) {
    $editCat = $db->query("SELECT * FROM categorias WHERE id=".(int)$_GET['edit'])->fetch();
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- Header -->
<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-grid me-2"></i>Categorías de Elementos</h4>
    <p class="text-muted small mb-0"><?= count($categorias) ?> categorías registradas</p>
  </div>
</div>

<?php if ($error):   ?><div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success py-2 small"><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="row g-4">

  <!-- ── Lista de categorías ── -->
  <div class="col-lg-8">
    <div class="section-card p-0" style="overflow:hidden">
      <div style="background:#1e293b;color:#fff;padding:.6rem 1rem;font-size:.82rem;font-weight:700">
        <i class="bi bi-list-ul me-1"></i>Categorías
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0 table-inv">
          <thead><tr>
            <th style="width:40px"></th>
            <th>Nombre</th>
            <th>Prefijo</th>
            <th>Icono</th>
            <th style="text-align:center">Elementos</th>
            <th style="text-align:center">Estado</th>
            <th style="width:100px">Acciones</th>
          </tr></thead>
          <tbody>
          <?php foreach ($categorias as $cat): ?>
          <tr class="<?= !$cat['activa'] ? 'table-secondary text-muted' : '' ?>">
            <td>
              <span style="display:inline-block;width:18px;height:18px;border-radius:4px;background:<?= htmlspecialchars($cat['color']) ?>">&nbsp;</span>
            </td>
            <td>
              <div class="fw-semibold"><?= htmlspecialchars($cat['nombre']) ?></div>
              <?php if ($cat['descripcion']): ?>
                <div class="text-muted" style="font-size:.73rem"><?= htmlspecialchars($cat['descripcion']) ?></div>
              <?php endif; ?>
            </td>
            <td><code style="font-size:.8rem;color:#185FA5"><?= htmlspecialchars($cat['prefijo']) ?></code></td>
            <td><i class="bi <?= htmlspecialchars($cat['icono']) ?>" style="color:<?= htmlspecialchars($cat['color']) ?>"></i> <span style="font-size:.73rem;color:#94a3b8"><?= htmlspecialchars($cat['icono']) ?></span></td>
            <td style="text-align:center">
              <span class="fw-bold"><?= (int)$cat['total_elem'] ?></span>
              <?php if ($cat['sin_stock'] > 0): ?>
                <span style="font-size:.7rem;color:#ef4444;font-weight:700" title="Sin stock"> &#x26A0;<?= $cat['sin_stock'] ?></span>
              <?php endif; ?>
            </td>
            <td style="text-align:center">
              <?php if ($cat['activa']): ?>
                <span class="badge bg-success" style="font-size:.7rem">Activa</span>
              <?php else: ?>
                <span class="badge bg-secondary" style="font-size:.7rem">Inactiva</span>
              <?php endif; ?>
            </td>
            <td>
              <div class="d-flex gap-1">
                <a href="?edit=<?= $cat['id'] ?>" class="btn btn-sm btn-outline-primary" style="padding:.2rem .4rem" title="Editar"><i class="bi bi-pencil"></i></a>
                <?php if ($cat['activa']): ?>
                  <a href="?del=<?= $cat['id'] ?>&csrf=<?= Auth::csrfToken() ?>"
                     class="btn btn-sm btn-outline-danger" style="padding:.2rem .4rem" title="Desactivar"
                     onclick="return confirm('¿Desactivar la categoría «<?= addslashes(htmlspecialchars($cat['nombre'])) ?>»?')">
                    <i class="bi bi-toggle-on"></i>
                  </a>
                <?php else: ?>
                  <a href="?act=<?= $cat['id'] ?>&csrf=<?= Auth::csrfToken() ?>"
                     class="btn btn-sm btn-outline-success" style="padding:.2rem .4rem" title="Reactivar">
                    <i class="bi bi-toggle-off"></i>
                  </a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($categorias)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No hay categorías registradas</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ── Formulario crear / editar ── -->
  <div class="col-lg-4">
    <div class="section-card">
      <h6 class="fw-bold mb-3">
        <i class="bi bi-<?= $editCat ? 'pencil-square' : 'plus-circle' ?> me-1"></i>
        <?= $editCat ? 'Editar Categoría' : 'Nueva Categoría' ?>
      </h6>
      <form method="POST">
        <input type="hidden" name="csrf" value="<?= Auth::csrfToken() ?>">
        <?php if ($editCat): ?>
          <input type="hidden" name="id" value="<?= $editCat['id'] ?>">
        <?php endif; ?>

        <div class="mb-3">
          <label class="form-label small fw-semibold">Nombre <span class="text-danger">*</span></label>
          <input type="text" name="nombre" class="form-control form-control-sm" required maxlength="100"
                 value="<?= htmlspecialchars($editCat['nombre'] ?? '') ?>"
                 placeholder="Ej: Arduino & Clones">
        </div>

        <div class="mb-3">
          <label class="form-label small fw-semibold">Prefijo <span class="text-danger">*</span></label>
          <input type="text" name="prefijo" class="form-control form-control-sm" required maxlength="6"
                 style="text-transform:uppercase"
                 value="<?= htmlspecialchars($editCat['prefijo'] ?? '') ?>"
                 placeholder="Ej: ARD">
          <div class="form-text">Máx. 6 caracteres. Se usa para generar códigos.</div>
        </div>

        <div class="mb-3">
          <label class="form-label small fw-semibold">Descripción</label>
          <textarea name="descripcion" class="form-control form-control-sm" rows="2" maxlength="255"
                    placeholder="Descripción opcional"><?= htmlspecialchars($editCat['descripcion'] ?? '') ?></textarea>
        </div>

        <div class="row g-2 mb-3">
          <div class="col-7">
            <label class="form-label small fw-semibold">Icono Bootstrap</label>
            <input type="text" name="icono" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($editCat['icono'] ?? 'bi-box') ?>"
                   placeholder="bi-cpu">
            <div class="form-text">
              <a href="https://icons.getbootstrap.com" target="_blank" class="text-decoration-none" style="font-size:.72rem">Ver íconos</a>
            </div>
          </div>
          <div class="col-5">
            <label class="form-label small fw-semibold">Color</label>
            <div class="input-group input-group-sm">
              <input type="color" name="color" class="form-control form-control-color form-control-sm"
                     value="<?= htmlspecialchars($editCat['color'] ?? '#3a72e8') ?>"
                     style="min-width:42px;padding:.2rem">
              <input type="text" id="colorHex" class="form-control form-control-sm"
                     value="<?= htmlspecialchars($editCat['color'] ?? '#3a72e8') ?>"
                     maxlength="7" readonly style="font-family:monospace;font-size:.8rem">
            </div>
          </div>
        </div>

        <?php if ($editCat): ?>
        <div class="form-check mb-3">
          <input class="form-check-input" type="checkbox" name="activa" id="activa" <?= ($editCat['activa'] ?? 1) ? 'checked' : '' ?>>
          <label class="form-check-label small" for="activa">Categoría activa</label>
        </div>
        <?php endif; ?>

        <!-- Vista previa -->
        <div class="mb-3 p-2 rounded" style="background:#f8fafc;border:1px solid #e2e8f0">
          <div class="small text-muted mb-1">Vista previa:</div>
          <div class="d-flex align-items-center gap-2">
            <span id="prevColor" style="display:inline-block;width:14px;height:14px;border-radius:3px;background:<?= htmlspecialchars($editCat['color'] ?? '#3a72e8') ?>"></span>
            <span id="prevNombre" class="fw-semibold" style="font-size:.85rem;color:<?= htmlspecialchars($editCat['color'] ?? '#3a72e8') ?>"><?= htmlspecialchars($editCat['nombre'] ?? 'Nueva Categoría') ?></span>
            <code id="prevPrefijo" style="font-size:.75rem;color:#185FA5"><?= htmlspecialchars($editCat['prefijo'] ?? 'PRE') ?></code>
            <i id="prevIcono" class="bi <?= htmlspecialchars($editCat['icono'] ?? 'bi-box') ?>" style="color:<?= htmlspecialchars($editCat['color'] ?? '#3a72e8') ?>"></i>
          </div>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary btn-sm">
            <i class="bi bi-<?= $editCat ? 'check-lg' : 'plus-lg' ?> me-1"></i><?= $editCat ? 'Actualizar' : 'Crear' ?>
          </button>
          <?php if ($editCat): ?>
            <a href="?" class="btn btn-outline-secondary btn-sm">Cancelar</a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

</div>

<script>
// Vista previa en tiempo real
(function(){
  const colorInput  = document.querySelector('input[type="color"]');
  const colorHex    = document.getElementById('colorHex');
  const nombreInput = document.querySelector('input[name="nombre"]');
  const prefijoInput= document.querySelector('input[name="prefijo"]');
  const iconoInput  = document.querySelector('input[name="icono"]');
  const prevColor   = document.getElementById('prevColor');
  const prevNombre  = document.getElementById('prevNombre');
  const prevPrefijo = document.getElementById('prevPrefijo');
  const prevIcono   = document.getElementById('prevIcono');

  function update(){
    const c = colorInput.value;
    colorHex.value = c;
    prevColor.style.background = c;
    prevNombre.style.color = c;
    prevIcono.style.color  = c;
    prevNombre.textContent = nombreInput.value  || 'Nueva Categoría';
    prevPrefijo.textContent= prefijoInput.value.toUpperCase() || 'PRE';
    prevIcono.className    = 'bi ' + (iconoInput.value || 'bi-box');
    prefijoInput.value     = prefijoInput.value.toUpperCase();
  }
  colorInput.addEventListener('input', update);
  nombreInput.addEventListener('input', update);
  prefijoInput.addEventListener('input', update);
  iconoInput.addEventListener('input', update);
})();
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
