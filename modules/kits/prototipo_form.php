<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();

$db  = Database::get();
$id  = (int)($_GET['id'] ?? 0);
$pro = $id ? $db->query("SELECT * FROM prototipos WHERE id=$id")->fetch() : null;
$pageTitle  = $pro ? 'Editar Prototipo' : 'Nuevo Prototipo';
$activeMenu = 'elementos';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::csrfVerify($_POST['csrf'] ?? '')) die('CSRF inválido');
    try {
        $foto = $pro['foto'] ?? null;
        if (!empty($_FILES['foto']['tmp_name'])) $foto = subirFoto($_FILES['foto'], 'prototipos');

        $tipoFab = isset($_POST['tipo_fabricacion']) && is_array($_POST['tipo_fabricacion'])
            ? implode(',', array_filter($_POST['tipo_fabricacion']))
            : 'manual';

        $data = [
            'nombre'             => trim($_POST['nombre']),
            'descripcion'        => trim($_POST['descripcion']        ?? ''),
            'tipo_fabricacion'   => $tipoFab,
            'foto'               => $foto,
            'material_principal' => trim($_POST['material_principal'] ?? ''),
            'color_material'     => trim($_POST['color_material']     ?? ''),
            'grosor_mm'          => ($_POST['grosor_mm']          ?: null),
            'peso_gramos'        => ($_POST['peso_gramos']         ?: null),
            'tiempo_laser_min'   => (int)($_POST['tiempo_laser_min']  ?? 0),
            'tiempo_3d_min'      => (int)($_POST['tiempo_3d_min']     ?? 0),
            'tiempo_ensamble_min'=> (int)($_POST['tiempo_ensamble_min']?? 0),
            'costo_material_cop' => (float)str_replace(',','.',($_POST['costo_material_cop'] ?? 0)),
            'costo_maquina_cop'  => (float)str_replace(',','.',($_POST['costo_maquina_cop']  ?? 0)),
            'costo_mano_obra_cop'=> (float)str_replace(',','.',($_POST['costo_mano_obra_cop'] ?? 0)),
            'version'            => trim($_POST['version'] ?? 'v1.0'),
            'activo'             => 1,
        ];

        if ($pro) {
            $sets = implode(',', array_map(fn($k)=>"$k=:$k", array_keys($data)));
            $data['id'] = $id;
            $db->prepare("UPDATE prototipos SET $sets WHERE id=:id")->execute($data);
            header('Location: ' . APP_URL . '/modules/kits/prototipo_form.php?id=' . $id . '&ok=1'); exit;
        } else {
            // Generar código RS-PRO-001
            $db->beginTransaction();
            $seq = $db->query("SELECT ultimo_numero FROM prototipos_secuencia FOR UPDATE")->fetchColumn();
            $nuevo = $seq + 1;
            $db->query("UPDATE prototipos_secuencia SET ultimo_numero=$nuevo");
            $codigo = 'RS-PRO-' . str_pad($nuevo, 3, '0', STR_PAD_LEFT);
            $data['codigo']     = $codigo;
            $data['created_by'] = Auth::user()['id'];
            $cols = implode(',', array_keys($data));
            $vals = ':' . implode(',:', array_keys($data));
            $db->prepare("INSERT INTO prototipos ($cols) VALUES ($vals)")->execute($data);
            $newId = $db->lastInsertId();
            $db->commit();
            header('Location: ' . APP_URL . '/modules/kits/prototipo_form.php?id=' . $newId . '&ok=1'); exit;
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error = $e->getMessage();
    }
}
if (!empty($_GET['ok'])) $success = 'Prototipo guardado correctamente.';

require_once dirname(__DIR__, 2) . '/includes/header.php';

$tiposOpt = [
    'laser'        => ['✂️', 'Cortadora Láser',   'danger'],
    'impresion_3d' => ['🖨️', 'Impresora 3D',      'primary'],
    'manual'       => ['&#x1F527;', 'Ensamble Manual',   'secondary'],
    'electronica'  => ['⚡', 'Electrónica',        'warning'],
    'mixto'        => ['🔀', 'Mixto / Combinado',  'info'],
];
$tiposActivos = $pro ? explode(',', $pro['tipo_fabricacion']) : [];
?>

<div class="d-flex align-items-center gap-2 mb-4">
  <a href="<?= APP_URL ?>/modules/kits/prototipos.php" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i></a>
  <div>
    <h4 class="fw-bold mb-0"><?= $pageTitle ?></h4>
    <?php if ($pro): ?><code class="text-primary"><?= $pro['codigo'] ?></code><?php endif; ?>
  </div>
</div>

<?php if ($error):            ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if (isset($success)): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

<form method="POST" enctype="multipart/form-data">
  <input type="hidden" name="csrf" value="<?= Auth::csrfToken() ?>">
  <div class="row g-4">

    <!-- ── Izquierda ── -->
    <div class="col-lg-8">

      <div class="section-card">
        <h6 class="fw-bold mb-3 text-primary"><i class="bi bi-info-circle me-2"></i>Información del Prototipo</h6>
        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label">Nombre del Prototipo *</label>
            <input type="text" name="nombre" class="form-control" required maxlength="200"
                   value="<?= htmlspecialchars($pro['nombre'] ?? '') ?>"
                   placeholder="Ej: Carro Seguidor de Línea v2">
          </div>
          <div class="col-md-4">
            <label class="form-label">Versión</label>
            <input type="text" name="version" class="form-control" maxlength="10"
                   value="<?= htmlspecialchars($pro['version'] ?? 'v1.0') ?>" placeholder="v1.0">
          </div>
          <div class="col-12">
            <label class="form-label">Descripción</label>
            <textarea name="descripcion" class="form-control" rows="3"
                      placeholder="Qué hace el prototipo, para qué curso va dirigido..."><?= htmlspecialchars($pro['descripcion'] ?? '') ?></textarea>
          </div>
          <div class="col-12">
            <label class="form-label">Tipo de Fabricación * <span class="text-muted fw-normal">(puede ser más de uno)</span></label>
            <div class="d-flex flex-wrap gap-2">
              <?php foreach ($tiposOpt as $val => [$ico, $lbl, $color]): ?>
              <div class="form-check-card">
                <input class="form-check-input visually-hidden" type="checkbox"
                       name="tipo_fabricacion[]" value="<?= $val ?>"
                       id="tipo_<?= $val ?>"
                       <?= in_array($val, $tiposActivos) ? 'checked' : '' ?>>
                <label class="btn btn-outline-<?= $color ?> btn-sm tipo-toggle" for="tipo_<?= $val ?>">
                  <?= $ico ?> <?= $lbl ?>
                </label>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="section-card">
        <h6 class="fw-bold mb-3 text-warning"><i class="bi bi-layers me-2"></i>Materiales y Dimensiones</h6>
        <div class="row g-3">
          <div class="col-md-5">
            <label class="form-label">Material Principal</label>
            <input type="text" name="material_principal" class="form-control"
                   value="<?= htmlspecialchars($pro['material_principal'] ?? '') ?>"
                   placeholder="Ej: MDF 3mm, PLA 1.75mm, Acrílico">
          </div>
          <div class="col-md-3">
            <label class="form-label">Color del Material</label>
            <input type="text" name="color_material" class="form-control"
                   value="<?= htmlspecialchars($pro['color_material'] ?? '') ?>"
                   placeholder="Natural, Negro, Rojo...">
          </div>
          <div class="col-md-2">
            <label class="form-label">Grosor (mm)</label>
            <input type="number" name="grosor_mm" class="form-control" step="0.1" min="0"
                   value="<?= $pro['grosor_mm'] ?? '' ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label">Peso (g)</label>
            <input type="number" name="peso_gramos" class="form-control" step="0.1" min="0"
                   value="<?= $pro['peso_gramos'] ?? '' ?>">
          </div>
        </div>
      </div>

      <div class="section-card">
        <h6 class="fw-bold mb-3 text-info"><i class="bi bi-stopwatch me-2"></i>Tiempos de Producción</h6>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">✂️ Tiempo Láser (min)</label>
            <div class="input-group">
              <input type="number" name="tiempo_laser_min" class="form-control" min="0" step="1"
                     value="<?= $pro['tiempo_laser_min'] ?? 0 ?>">
              <span class="input-group-text">min</span>
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label">🖨️ Tiempo 3D (min)</label>
            <div class="input-group">
              <input type="number" name="tiempo_3d_min" class="form-control" min="0" step="1"
                     value="<?= $pro['tiempo_3d_min'] ?? 0 ?>">
              <span class="input-group-text">min</span>
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label">&#x1F527; Ensamble (min)</label>
            <div class="input-group">
              <input type="number" name="tiempo_ensamble_min" class="form-control" min="0" step="1"
                     value="<?= $pro['tiempo_ensamble_min'] ?? 0 ?>">
              <span class="input-group-text">min</span>
            </div>
          </div>
          <?php
          $totalMin = ($pro['tiempo_laser_min'] ?? 0) + ($pro['tiempo_3d_min'] ?? 0) + ($pro['tiempo_ensamble_min'] ?? 0);
          if ($totalMin > 0):
          ?>
          <div class="col-12">
            <div class="alert alert-light py-2 small mb-0">
              <i class="bi bi-clock me-1"></i>
              Tiempo total de producción: <strong><?= $totalMin ?> minutos</strong>
              (<?= floor($totalMin/60) ?>h <?= $totalMin%60 ?>min)
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <div class="section-card">
        <h6 class="fw-bold mb-3 text-success"><i class="bi bi-calculator me-2"></i>Estructura de Costos</h6>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Costo de Materiales</label>
            <div class="input-group">
              <span class="input-group-text">$</span>
              <input type="number" name="costo_material_cop" class="form-control" step="100" min="0"
                     id="costoMat" oninput="calcTotal()"
                     value="<?= $pro['costo_material_cop'] ?? 0 ?>">
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Costo Hora Máquina</label>
            <div class="input-group">
              <span class="input-group-text">$</span>
              <input type="number" name="costo_maquina_cop" class="form-control" step="100" min="0"
                     id="costoMaq" oninput="calcTotal()"
                     value="<?= $pro['costo_maquina_cop'] ?? 0 ?>">
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Costo Mano de Obra</label>
            <div class="input-group">
              <span class="input-group-text">$</span>
              <input type="number" name="costo_mano_obra_cop" class="form-control" step="100" min="0"
                     id="costoMO" oninput="calcTotal()"
                     value="<?= $pro['costo_mano_obra_cop'] ?? 0 ?>">
            </div>
          </div>
          <div class="col-12">
            <div class="p-3 rounded text-center" style="background:#f0fdf4;border:2px solid #86efac;">
              <div class="text-muted small">COSTO TOTAL DE FABRICACIÓN</div>
              <div class="fw-bold text-success fs-4" id="costoTotal">
                <?= cop(($pro['costo_total_cop'] ?? 0)) ?>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Derecha ── -->
    <div class="col-lg-4">
      <?php if ($pro): ?>
      <div class="section-card text-center mb-3">
        <h6 class="fw-bold mb-2"><i class="bi bi-upc me-2 text-primary"></i>Código</h6>
        <code class="fs-5 text-primary d-block mb-2"><?= $pro['codigo'] ?></code>
        <div class="barcode-container">
          <svg data-barcode="<?= htmlspecialchars($pro['codigo']) ?>"></svg>
        </div>
        <button type="button" onclick="imprimirBarcode('<?= $pro['codigo'] ?>','<?= addslashes($pro['nombre']) ?>')"
                class="btn btn-outline-primary btn-sm mt-2 w-100">
          <i class="bi bi-printer me-1"></i>Imprimir Etiqueta
        </button>
      </div>
      <?php endif; ?>

      <div class="section-card">
        <h6 class="fw-bold mb-3"><i class="bi bi-image me-2 text-primary"></i>Foto del Prototipo</h6>
        <?php if ($pro && $pro['foto']): ?>
          <img src="<?= UPLOAD_URL . htmlspecialchars($pro['foto']) ?>" id="fotoPreview"
               class="img-fluid rounded mb-2 w-100" style="max-height:160px;object-fit:cover;">
        <?php else: ?>
          <div class="bg-light rounded text-center p-4 mb-2">
            <div style="font-size:2.5rem;">✂️</div>
          </div>
          <img id="fotoPreview" src="" class="img-fluid rounded mb-2 w-100" style="max-height:160px;object-fit:cover;display:none;">
        <?php endif; ?>
        <input type="file" name="foto" class="form-control form-control-sm img-preview-input"
               accept="image/*" data-preview="fotoPreview">
      </div>

      <div class="d-grid gap-2 mt-3">
        <button type="submit" class="btn btn-primary btn-lg fw-bold">
          <i class="bi bi-save me-2"></i><?= $pro ? 'Guardar Cambios' : 'Crear Prototipo' ?>
        </button>
        <a href="prototipos.php" class="btn btn-outline-secondary">Cancelar</a>
      </div>
    </div>

  </div>
</form>

<style>
/* Estilo para checkboxes tipo toggle */
.tipo-toggle { cursor:pointer; transition:.15s; }
input[type=checkbox]:checked + .tipo-toggle { font-weight:700; }
</style>
<script>
function calcTotal() {
  const mat = parseFloat(document.getElementById('costoMat').value)||0;
  const maq = parseFloat(document.getElementById('costoMaq').value)||0;
  const mo  = parseFloat(document.getElementById('costoMO').value)||0;
  const total = mat + maq + mo;
  document.getElementById('costoTotal').textContent =
    '$ ' + total.toLocaleString('es-CO', {minimumFractionDigits:0});
}
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
