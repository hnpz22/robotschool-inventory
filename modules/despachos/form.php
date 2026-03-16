<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();

$db = Database::get();
$id  = (int)($_GET['id'] ?? 0);
$des = $id ? $db->query("SELECT d.*, col.nombre AS colegio_nombre, col.ciudad AS municipio, col.ciudad AS direccion, col.telefono, col.email AS colegio_email FROM despachos d LEFT JOIN colegios col ON col.id=d.colegio_id WHERE d.id=$id")->fetch() : null;
$pageTitle  = $des ? 'Editar ' . $des['codigo'] : 'Nuevo Despacho';
$activeMenu = 'despachos';
$error = $success = '';

// Verificar solo preparando puede editarse
if ($des && $des['estado'] !== 'preparando') {
    header('Location: ' . APP_URL . '/modules/despachos/ver.php?id=' . $id); exit;
}

// GUARDAR CABECERA
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='save' && Auth::csrfVerify($_POST['csrf']??'')) {
    try {
        $data = [
            'colegio_id'      => $_POST['colegio_id'] ?: null,
            'fecha'           => $_POST['fecha'],
            'transportadora'  => trim($_POST['transportadora'] ?? ''),
            'guia_transporte' => trim($_POST['guia_transporte'] ?? ''),
            'nombre_recibe'   => trim($_POST['nombre_recibe'] ?? ''),
            'cargo_recibe'    => trim($_POST['cargo_recibe'] ?? ''),
            'valor_flete_cop' => (float)str_replace(',','.',($_POST['valor_flete_cop']??0)),
            'notas'           => trim($_POST['notas'] ?? ''),
        ];
        if (!$des) {
            // Generar codigo DES-2026-001
            $anio = date('Y');
            $db->beginTransaction();
            $db->prepare("INSERT INTO despachos_secuencia (anio,ultimo_numero) VALUES (?,1) ON DUPLICATE KEY UPDATE ultimo_numero=ultimo_numero+1")->execute([$anio]);
            $num = $db->query("SELECT ultimo_numero FROM despachos_secuencia WHERE anio=$anio")->fetchColumn();
            $codigo = 'DES-' . $anio . '-' . str_pad($num, 3, '0', STR_PAD_LEFT);
            $data['codigo']     = $codigo;
            $data['creado_por'] = Auth::user()['id'];
            $cols = implode(',', array_keys($data));
            $vals = ':' . implode(',:', array_keys($data));
            $db->prepare("INSERT INTO despachos ($cols) VALUES ($vals)")->execute($data);
            $newId = $db->lastInsertId();
            $db->commit();
            header('Location: ' . APP_URL . '/modules/despachos/form.php?id=' . $newId . '&ok=1'); exit;
        } else {
            $sets = implode(',', array_map(fn($k)=>"$k=:$k", array_keys($data)));
            $data['id'] = $id;
            $db->prepare("UPDATE despachos SET $sets WHERE id=:id")->execute($data);
            $success = 'Despacho actualizado.';
            $des = $db->query("SELECT d.*, col.nombre AS colegio_nombre FROM despachos d LEFT JOIN colegios col ON col.id=d.colegio_id WHERE d.id=$id")->fetch();
        }
    } catch (Exception $e) { if($db->inTransaction())$db->rollBack(); $error = $e->getMessage(); }
}

// AGREGAR KIT
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='add_kit' && $des && Auth::csrfVerify($_POST['csrf']??'')) {
    $kitId = (int)$_POST['kit_id']; $cant = max(1,(int)$_POST['cantidad']);
    if ($kitId) {
        $db->prepare("INSERT INTO despacho_kits (despacho_id,kit_id,cantidad) VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE cantidad=cantidad+VALUES(cantidad)")
          ->execute([$id, $kitId, $cant]);
    }
    header('Location: ' . APP_URL . '/modules/despachos/form.php?id=' . $id . '#kits'); exit;
}

// QUITAR KIT
if (isset($_GET['del_kit']) && $des && Auth::csrfVerify($_GET['csrf']??'')) {
    $db->prepare("DELETE FROM despacho_kits WHERE id=? AND despacho_id=?")->execute([(int)$_GET['del_kit'], $id]);
    header('Location: ' . APP_URL . '/modules/despachos/form.php?id=' . $id . '#kits'); exit;
}

if (!empty($_GET['ok'])) $success = 'Despacho creado. Ahora agrega los kits.';

$colegios = $db->query("SELECT id,nombre,ciudad AS municipio FROM colegios WHERE activo=1 ORDER BY nombre")->fetchAll();
$kits     = $db->query("SELECT k.id, k.codigo, k.nombre, col.nombre AS colegio FROM kits k LEFT JOIN colegios col ON col.id=k.colegio_id WHERE k.activo=1 ORDER BY k.nombre")->fetchAll();
$desKits  = $des ? $db->query("SELECT dk.*, k.codigo AS kit_codigo, k.nombre AS kit_nombre, k.costo_cop, col.nombre AS colegio FROM despacho_kits dk JOIN kits k ON k.id=dk.kit_id LEFT JOIN colegios col ON col.id=k.colegio_id WHERE dk.despacho_id=$id")->fetchAll() : [];

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="d-flex align-items-center gap-2 mb-4">
  <a href="<?= APP_URL ?>/modules/despachos/" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i></a>
  <div>
    <h4 class="fw-bold mb-0"><?= $pageTitle ?></h4>
    <?php if ($des): ?><span class="text-muted small">Estado: <strong><?= $des['estado'] ?></strong></span><?php endif; ?>
  </div>
  <?php if ($des): ?>
  <a href="<?= APP_URL ?>/modules/despachos/ver.php?id=<?= $id ?>" class="btn btn-outline-info btn-sm ms-auto">
    <i class="bi bi-eye me-1"></i>Ver detalle / Imprimir
  </a>
  <?php endif; ?>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

<!-- CABECERA -->
<form method="POST">
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="csrf"   value="<?= Auth::csrfToken() ?>">
  <div class="section-card mb-4">
    <h6 class="fw-bold mb-3"><i class="bi bi-truck me-2 text-primary"></i>Datos del Despacho</h6>
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label fw-semibold">Colegio destino *</label>
        <select name="colegio_id" class="form-select" required onchange="actualizarKitsColegio(this.value)">
          <option value="">&#8212; Seleccionar colegio &#8212;</option>
          <?php foreach ($colegios as $c): ?>
            <option value="<?= $c['id'] ?>" <?= ($des['colegio_id']??0)==$c['id']?'selected':'' ?>>
              <?= htmlspecialchars($c['nombre']) ?><?= $c['municipio']?' &#8212; '.htmlspecialchars($c['municipio']):'' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label fw-semibold">Fecha despacho *</label>
        <input type="date" name="fecha" class="form-control" value="<?= $des['fecha'] ?? date('Y-m-d') ?>" required>
      </div>
      <div class="col-md-3">
        <label class="form-label fw-semibold">Valor flete COP</label>
        <div class="input-group">
          <span class="input-group-text">$</span>
          <input type="number" name="valor_flete_cop" class="form-control" step="1" min="0"
                 value="<?= $des['valor_flete_cop'] ?? 0 ?>">
        </div>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold">Transportadora</label>
        <input type="text" name="transportadora" class="form-control"
               placeholder="Ej: Servientrega, Coordinadora, Moto mensajero"
               value="<?= htmlspecialchars($des['transportadora'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold">Gu&#237;a / N&#250;mero de rastreo</label>
        <input type="text" name="guia_transporte" class="form-control font-monospace"
               placeholder="N&#250;mero de gu&#237;a"
               value="<?= htmlspecialchars($des['guia_transporte'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold">Nombre de quien recibe</label>
        <input type="text" name="nombre_recibe" class="form-control"
               placeholder="Nombre completo"
               value="<?= htmlspecialchars($des['nombre_recibe'] ?? '') ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold">Cargo de quien recibe</label>
        <input type="text" name="cargo_recibe" class="form-control"
               placeholder="Ej: Rector, Coordinador, Docente"
               value="<?= htmlspecialchars($des['cargo_recibe'] ?? '') ?>">
      </div>
      <div class="col-12">
        <label class="form-label fw-semibold">Notas</label>
        <textarea name="notas" class="form-control" rows="2"
                  placeholder="Instrucciones de entrega, observaciones..."><?= htmlspecialchars($des['notas'] ?? '') ?></textarea>
      </div>
    </div>
    <div class="mt-3">
      <button type="submit" class="btn btn-primary fw-bold">
        <i class="bi bi-floppy me-2"></i><?= $des ? 'Guardar cambios' : 'Crear Despacho' ?>
      </button>
    </div>
  </div>
</form>

<?php if ($des): ?>
<!-- KITS -->
<div class="section-card" id="kits">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-bold mb-0"><i class="bi bi-bag-check me-2 text-success"></i>Kits en este despacho</h6>
    <button class="btn btn-sm btn-outline-success" data-bs-toggle="collapse" data-bs-target="#addKitForm">
      <i class="bi bi-plus-lg me-1"></i>Agregar Kit
    </button>
  </div>

  <!-- Form agregar kit -->
  <div class="collapse mb-3" id="addKitForm">
    <form method="POST" class="bg-light rounded p-3">
      <input type="hidden" name="action" value="add_kit">
      <input type="hidden" name="csrf"   value="<?= Auth::csrfToken() ?>">
      <div class="row g-2 align-items-end">
        <div class="col-md-7">
          <label class="form-label mb-1 fw-semibold">Kit</label>
          <select name="kit_id" class="form-select form-select-sm" required>
            <option value="">&#8212; Seleccionar kit &#8212;</option>
            <?php foreach ($kits as $k): ?>
              <option value="<?= $k['id'] ?>">
                <?= $k['codigo'] ?> &#8212; <?= htmlspecialchars($k['nombre']) ?>
                <?= $k['colegio'] ? ' ['.htmlspecialchars($k['colegio']).']' : ' [Gen&#233;rico]' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label mb-1 fw-semibold">Cantidad</label>
          <input type="number" name="cantidad" class="form-control form-control-sm" min="1" value="1">
        </div>
        <div class="col-md-3">
          <button type="submit" class="btn btn-success btn-sm w-100">
            <i class="bi bi-plus-lg me-1"></i>Agregar
          </button>
        </div>
      </div>
    </form>
  </div>

  <!-- Tabla kits -->
  <?php if (!empty($desKits)): ?>
  <div class="table-responsive">
    <table class="table table-sm table-hover mb-0">
      <thead><tr>
        <th>C&#243;digo</th><th>Nombre del Kit</th><th>Colegio</th>
        <th class="text-center">Cantidad</th><th class="text-end">Costo unit.</th>
        <th class="text-end">Subtotal</th><th></th>
      </tr></thead>
      <tbody>
      <?php
      $totalCosto = 0;
      foreach ($desKits as $dk):
        $sub = $dk['cantidad'] * $dk['costo_cop'];
        $totalCosto += $sub;
      ?>
      <tr>
        <td><code class="text-primary"><?= htmlspecialchars($dk['kit_codigo']) ?></code></td>
        <td class="fw-semibold"><?= htmlspecialchars($dk['kit_nombre']) ?></td>
        <td class="small text-muted"><?= htmlspecialchars($dk['colegio'] ?? 'Gen&#233;rico') ?></td>
        <td class="text-center"><span class="badge bg-primary"><?= $dk['cantidad'] ?></span></td>
        <td class="text-end"><?= cop($dk['costo_cop']) ?></td>
        <td class="text-end fw-bold text-success"><?= cop($sub) ?></td>
        <td>
          <a href="?id=<?= $id ?>&del_kit=<?= $dk['id'] ?>&csrf=<?= Auth::csrfToken() ?>"
             class="btn btn-xs btn-outline-danger btn-sm"
             onclick="return confirm('Quitar este kit del despacho?')">
            <i class="bi bi-x-lg"></i>
          </a>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr class="table-light fw-bold">
          <td colspan="4" class="text-end">Total kits despacho:</td>
          <td></td>
          <td class="text-end text-success"><?= cop($totalCosto) ?></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>
  <?php else: ?>
  <div class="text-center text-muted py-4">
    <i class="bi bi-bag fs-2 d-block mb-2 opacity-25"></i>
    A&#250;n no hay kits en este despacho. Agrega kits con el bot&#243;n de arriba.
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
