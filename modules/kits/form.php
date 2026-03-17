<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
require_once dirname(__DIR__, 2) . '/includes/Storage.php';
Auth::check();

$db  = Database::get();
$id  = (int)($_GET['id'] ?? 0);
$kit = $id ? $db->query("SELECT * FROM kits WHERE id=$id")->fetch() : null;
$pageTitle  = $kit ? 'Editar Kit: ' . $kit['nombre'] : 'Nuevo Kit';
$activeMenu = 'kits';
$error = $success = '';

// ── Guardar cabecera del kit ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_kit') {
    if (!Auth::csrfVerify($_POST['csrf'] ?? '')) die('CSRF');
    try {
        $foto = $kit['foto'] ?? null;
        if (!empty($_FILES['foto']['tmp_name'])) {
            $file = $_FILES['foto'];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $dest = $kit
                ? 'kit_' . $id . '_' . time() . '.' . $ext
                : 'kit_'       . time() . '.' . $ext;
            try {
                if ($foto && (str_starts_with($foto, 'http://') || str_starts_with($foto, 'https://'))) {
                    Storage::getInstance()->delete(MINIO_BUCKET_KITS, basename($foto));
                }
                $foto = Storage::getInstance()->upload($file['tmp_name'], MINIO_BUCKET_KITS, $dest);
            } catch (Exception $e) {
                error_log('Storage MinIO falló, usando fallback local: ' . $e->getMessage());
                $foto = subirFoto($file, 'kits');
            }
        }

        $data = [
            'nombre'             => trim($_POST['nombre']),
            'tipo'               => $_POST['tipo']    ?? 'generico',
            'nivel'              => $_POST['nivel']   ?? 'basico',
            'descripcion'        => trim($_POST['descripcion'] ?? ''),
            'colegio_id'         => ($_POST['colegio_id']  ?: null),
            'tipo_caja_id'       => ($_POST['tipo_caja_id'] ?: null),
            'incluye_prototipo'  => isset($_POST['incluye_prototipo']) ? 1 : 0,
            'precio_cop'         => (float)str_replace(',','.',($_POST['precio_cop'] ?? 0)),
            'foto'               => $foto,
            'activo'             => 1,
        ];
        if ($kit) {
            $sets = implode(',', array_map(fn($k)=>"$k=:$k", array_keys($data)));
            $data['id'] = $id;
            $db->prepare("UPDATE kits SET $sets WHERE id=:id")->execute($data);
            $success = 'Kit actualizado.';
            $kit = $db->query("SELECT * FROM kits WHERE id=$id")->fetch();
        } else {
            // Código kit: KIT-001
            $seq = $db->query("SELECT COUNT(*) FROM kits")->fetchColumn() + 1;
            $codigo = 'KIT-' . str_pad($seq, 3, '0', STR_PAD_LEFT);
            $data['codigo']     = $codigo;
            $data['created_by'] = Auth::user()['id'];
            $data['costo_cop']  = 0;
            $cols = implode(',', array_keys($data));
            $vals = ':' . implode(',:', array_keys($data));
            $db->prepare("INSERT INTO kits ($cols) VALUES ($vals)")->execute($data);
            $newId = $db->lastInsertId();
            header('Location: ' . APP_URL . '/modules/kits/form.php?id=' . $newId . '&ok=1'); exit;
        }
    } catch (Exception $e) { $error = $e->getMessage(); }
}

// ── Agregar elemento importado ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_elemento' && $kit) {
    if (!Auth::csrfVerify($_POST['csrf'] ?? '')) die('CSRF');
    try {
        $elemId = (int)$_POST['elemento_id'];
        $cant   = max(1,(int)$_POST['cantidad']);
        $db->prepare("INSERT INTO kit_elementos (kit_id,elemento_id,cantidad) VALUES (?,?,?) ON DUPLICATE KEY UPDATE cantidad=VALUES(cantidad)")
           ->execute([$id, $elemId, $cant]);
        recalcularCostoKit($db, $id);
        header('Location: ' . APP_URL . "/modules/kits/form.php?id=$id#componentes"); exit;
    } catch (Exception $e) { $error = $e->getMessage(); }
}

// ── Agregar prototipo ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_prototipo' && $kit) {
    if (!Auth::csrfVerify($_POST['csrf'] ?? '')) die('CSRF');
    try {
        $proId = (int)$_POST['prototipo_id'];
        $cant  = max(1,(int)$_POST['cantidad']);
        $db->prepare("INSERT INTO kit_prototipos (kit_id,prototipo_id,cantidad,notas) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE cantidad=VALUES(cantidad)")
           ->execute([$id, $proId, $cant, trim($_POST['notas'] ?? '')]);
        recalcularCostoKit($db, $id);
        header('Location: ' . APP_URL . "/modules/kits/form.php?id=$id#componentes"); exit;
    } catch (Exception $e) { $error = $e->getMessage(); }
}

// ── Eliminar elemento ──
if (isset($_GET['del_elem']) && $kit && Auth::csrfVerify($_GET['csrf'] ?? '')) {
    $db->prepare("DELETE FROM kit_elementos WHERE id=? AND kit_id=?")->execute([(int)$_GET['del_elem'], $id]);
    recalcularCostoKit($db, $id);
    header('Location: ' . APP_URL . "/modules/kits/form.php?id=$id#componentes"); exit;
}

// ── Eliminar prototipo ──
if (isset($_GET['del_proto']) && $kit && Auth::csrfVerify($_GET['csrf'] ?? '')) {
    $db->prepare("DELETE FROM kit_prototipos WHERE id=? AND kit_id=?")->execute([(int)$_GET['del_proto'], $id]);
    recalcularCostoKit($db, $id);
    header('Location: ' . APP_URL . "/modules/kits/form.php?id=$id#componentes"); exit;
}

// ── Función recalcular costo del kit ──
function recalcularCostoKit(PDO $db, int $kitId): void {
    $costoElem = $db->query("
        SELECT COALESCE(SUM(ke.cantidad * e.costo_real_cop),0)
        FROM kit_elementos ke JOIN elementos e ON e.id=ke.elemento_id
        WHERE ke.kit_id=$kitId
    ")->fetchColumn();

    $costoProto = $db->query("
        SELECT COALESCE(SUM(kp.cantidad * p.costo_total_cop),0)
        FROM kit_prototipos kp JOIN prototipos p ON p.id=kp.prototipo_id
        WHERE kp.kit_id=$kitId
    ")->fetchColumn();

    $db->prepare("UPDATE kits SET costo_cop=? WHERE id=?")->execute([$costoElem + $costoProto, $kitId]);
}

if (!empty($_GET['ok'])) $success = 'Kit creado correctamente.';

// ── Cargar datos ──
$elemKits   = $id ? $db->query("SELECT ke.*, e.nombre, e.codigo, e.costo_real_cop, e.peso_gramos, e.foto, c.nombre AS cat FROM kit_elementos ke JOIN elementos e ON e.id=ke.elemento_id JOIN categorias c ON c.id=e.categoria_id WHERE ke.kit_id=$id ORDER BY c.nombre, e.nombre")->fetchAll() : [];
$protoKits  = $id ? $db->query("SELECT kp.*, p.nombre, p.codigo, p.tipo_fabricacion, p.costo_total_cop, p.foto FROM kit_prototipos kp JOIN prototipos p ON p.id=kp.prototipo_id WHERE kp.kit_id=$id")->fetchAll() : [];
$colegios   = $db->query("SELECT id,nombre FROM colegios WHERE activo=1 ORDER BY nombre")->fetchAll();
$tiposCaja  = $db->query("SELECT id,nombre,tipo FROM tipos_caja WHERE activo=1 ORDER BY nombre")->fetchAll();
$elementos  = $db->query("SELECT e.id,e.codigo,e.nombre,e.costo_real_cop,c.nombre AS cat FROM elementos e JOIN categorias c ON c.id=e.categoria_id WHERE e.activo=1 ORDER BY c.nombre,e.nombre")->fetchAll();
$prototipos = $db->query("SELECT id,codigo,nombre,tipo_fabricacion,costo_total_cop FROM prototipos WHERE activo=1 ORDER BY nombre")->fetchAll();

$costoTotal = ($kit['costo_cop']   ?? 0);
$precioSug  = ($kit['precio_cop']  ?? 0);

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="d-flex align-items-center gap-2 mb-4">
  <a href="<?= APP_URL ?>/modules/kits/" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i></a>
  <div>
    <h4 class="fw-bold mb-0"><?= $pageTitle ?></h4>
    <?php if ($kit): ?><code class="text-primary"><?= $kit['codigo'] ?></code><?php endif; ?>
  </div>
</div>

<?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<!-- ── FORMULARIO CABECERA KIT ── -->
<form method="POST" enctype="multipart/form-data">
  <input type="hidden" name="action" value="save_kit">
  <input type="hidden" name="csrf"   value="<?= Auth::csrfToken() ?>">

  <div class="row g-4 mb-4">
    <div class="col-lg-8">
      <div class="section-card">
        <h6 class="fw-bold mb-3 text-primary"><i class="bi bi-bag-check me-2"></i>Información del Kit</h6>
        <div class="row g-3">
          <div class="col-md-7">
            <label class="form-label">Nombre del Kit *</label>
            <input type="text" name="nombre" class="form-control" required
                   value="<?= htmlspecialchars($kit['nombre'] ?? '') ?>"
                   placeholder="Ej: Kit Robótica Junior &mdash; Colegio San José">
          </div>
          <div class="col-md-2">
            <label class="form-label">Tipo</label>
            <select name="tipo" class="form-select">
              <option value="generico"  <?= ($kit['tipo']??'')==='generico' ?'selected':'' ?>>Genérico</option>
              <option value="colegio"   <?= ($kit['tipo']??'')==='colegio'  ?'selected':'' ?>>Colegio</option>
              <option value="proyecto"  <?= ($kit['tipo']??'')==='proyecto' ?'selected':'' ?>>Proyecto</option>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">Nivel</label>
            <select name="nivel" class="form-select">
              <option value="basico"       <?= ($kit['nivel']??'')==='basico'      ?'selected':'' ?>>&#x1F7E2; Básico</option>
              <option value="intermedio"   <?= ($kit['nivel']??'')==='intermedio'  ?'selected':'' ?>>&#x1F7E1; Intermedio</option>
              <option value="avanzado"     <?= ($kit['nivel']??'')==='avanzado'    ?'selected':'' ?>>&#x1F534; Avanzado</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Colegio (opcional)</label>
            <select name="colegio_id" class="form-select">
              <option value="">Kit Genérico / Sin colegio</option>
              <?php foreach ($colegios as $c): ?>
                <option value="<?= $c['id'] ?>" <?= ($kit['colegio_id']??0)==$c['id']?'selected':'' ?>><?= htmlspecialchars($c['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Tipo de Caja de Empaque</label>
            <select name="tipo_caja_id" class="form-select">
              <option value="">Sin caja definida</option>
              <?php foreach ($tiposCaja as $tc): ?>
                <option value="<?= $tc['id'] ?>" <?= ($kit['tipo_caja_id']??0)==$tc['id']?'selected':'' ?>>
                  <?= htmlspecialchars($tc['nombre']) ?> (<?= $tc['tipo'] ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Descripción</label>
            <textarea name="descripcion" class="form-control" rows="2"><?= htmlspecialchars($kit['descripcion'] ?? '') ?></textarea>
          </div>
          <div class="col-md-4">
            <label class="form-label">Precio de Venta (COP)</label>
            <div class="input-group">
              <span class="input-group-text">$</span>
              <input type="number" name="precio_cop" class="form-control" step="1000" min="0"
                     value="<?= $kit['precio_cop'] ?? 0 ?>">
            </div>
          </div>
          <div class="col-md-4 d-flex align-items-end">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="incluye_prototipo" id="chkProto"
                     <?= ($kit['incluye_prototipo'] ?? 0) ? 'checked' : '' ?>>
              <label class="form-check-label" for="chkProto">Incluye Prototipo Fabricado</label>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <!-- Foto del kit -->
      <div class="section-card text-center">
        <h6 class="fw-bold mb-2"><i class="bi bi-image me-2 text-primary"></i>Imagen del Kit</h6>
        <?php if ($kit && $kit['foto']): ?>
          <img src="<?= htmlspecialchars(fotoUrl($kit['foto'])) ?>" id="kitFotoPreview"
               class="img-fluid rounded mb-2 w-100" style="max-height:130px;object-fit:cover;">
        <?php else: ?>
          <div class="bg-light rounded d-flex align-items-center justify-content-center mb-2" style="height:90px;font-size:2.5rem;">&#x1F916;</div>
          <img id="kitFotoPreview" src="" style="display:none;max-height:130px;" class="img-fluid rounded mb-2 w-100">
        <?php endif; ?>
        <input type="file" name="foto" class="form-control form-control-sm img-preview-input"
               accept="image/*" data-preview="kitFotoPreview">
      </div>

      <!-- Resumen costo -->
      <?php if ($kit): ?>
      <div class="section-card mt-3 text-center">
        <div class="text-muted small mb-1">Costo calculado del kit</div>
        <div class="fw-bold text-success fs-4"><?= cop($costoTotal) ?></div>
        <?php if ($precioSug > 0): ?>
          <?php $margen = $costoTotal > 0 ? round(($precioSug - $costoTotal) / $costoTotal * 100, 1) : 0; ?>
          <div class="text-muted small">Precio: <?= cop($precioSug) ?> · Margen: <strong><?= $margen ?>%</strong></div>
        <?php endif; ?>
        <div class="mt-2">
          <span class="badge bg-primary bg-opacity-10 text-primary"><?= count($elemKits) ?> elementos</span>
          <span class="badge bg-danger bg-opacity-10 text-danger"><?= count($protoKits) ?> prototipos</span>
        </div>
      </div>
      <?php endif; ?>

      <div class="d-grid mt-3">
        <button type="submit" class="btn btn-primary fw-bold">
          <i class="bi bi-save me-2"></i><?= $kit ? 'Guardar Cambios' : 'Crear Kit' ?>
        </button>
      </div>
    </div>
  </div>
</form>

<!-- ── COMPONENTES DEL KIT ── -->
<?php if ($kit): ?>
<div id="componentes">

  <div class="row g-4">
    <!-- ── Elementos importados ── -->
    <div class="col-lg-6">
      <div class="section-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="fw-bold mb-0">
            <span class="text-primary">&#x1F4E6;</span> Elementos Importados
            <span class="badge bg-primary ms-1"><?= count($elemKits) ?></span>
          </h6>
          <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#addElemForm">
            <i class="bi bi-plus-lg"></i>
          </button>
        </div>

        <div class="collapse mb-3" id="addElemForm">
          <form method="POST" class="bg-light rounded p-3">
            <input type="hidden" name="action" value="add_elemento">
            <input type="hidden" name="csrf"   value="<?= Auth::csrfToken() ?>">
            <div class="row g-2">
              <div class="col-8">
                <select name="elemento_id" class="form-select form-select-sm" required>
                  <option value="">Seleccionar elemento...</option>
                  <?php
                  $catActual = '';
                  foreach ($elementos as $e):
                    if ($e['cat'] !== $catActual):
                      if ($catActual) echo '</optgroup>';
                      echo '<optgroup label="' . htmlspecialchars($e['cat']) . '">';
                      $catActual = $e['cat'];
                    endif;
                  ?>
                    <option value="<?= $e['id'] ?>" data-costo="<?= $e['costo_real_cop'] ?>">
                      <?= htmlspecialchars($e['codigo']) ?> &mdash; <?= htmlspecialchars($e['nombre']) ?>
                    </option>
                  <?php endforeach; if ($catActual) echo '</optgroup>'; ?>
                </select>
              </div>
              <div class="col-2">
                <input type="number" name="cantidad" class="form-control form-control-sm" min="1" value="1">
              </div>
              <div class="col-2">
                <button type="submit" class="btn btn-primary btn-sm w-100">+</button>
              </div>
            </div>
          </form>
        </div>

        <?php if (empty($elemKits)): ?>
          <div class="text-muted text-center py-3 small">
            <i class="bi bi-cpu fs-4 d-block mb-1"></i>
            No hay elementos importados.<br>Agrega placas, sensores, módulos...
          </div>
        <?php else: ?>
        <div class="list-group list-group-flush">
        <?php
        $costoElemTotal = 0;
        foreach ($elemKits as $ke):
          $costoElemTotal += $ke['cantidad'] * $ke['costo_real_cop'];
        ?>
          <div class="list-group-item px-0 py-2 border-0 border-bottom">
            <div class="d-flex align-items-center gap-2">
              <?php if ($ke['foto']): ?>
                <img src="<?= htmlspecialchars(fotoUrl($ke['foto'])) ?>" class="elem-foto" style="width:38px;height:38px;" alt="">
              <?php else: ?>
                <div class="elem-foto-placeholder" style="width:38px;height:38px;font-size:1rem;flex-shrink:0;"><i class="bi bi-cpu"></i></div>
              <?php endif; ?>
              <div class="flex-grow-1 min-width-0">
                <div class="fw-semibold small text-truncate"><?= htmlspecialchars($ke['nombre']) ?></div>
                <div class="d-flex gap-2" style="font-size:.73rem;">
                  <span class="text-muted"><?= $ke['codigo'] ?></span>
                  <span class="text-muted">·</span>
                  <span class="text-muted"><?= htmlspecialchars($ke['cat']) ?></span>
                </div>
              </div>
              <div class="text-end flex-shrink-0">
                <div class="fw-bold text-primary">×<?= $ke['cantidad'] ?></div>
                <div class="text-success" style="font-size:.75rem;">
                  <?= $ke['costo_real_cop'] > 0 ? cop($ke['cantidad'] * $ke['costo_real_cop']) : '&mdash;' ?>
                </div>
              </div>
              <a href="?id=<?= $id ?>&del_elem=<?= $ke['id'] ?>&csrf=<?= Auth::csrfToken() ?>"
                 class="btn btn-sm btn-outline-danger py-0 px-1"
                 data-confirm="¿Quitar <?= htmlspecialchars(addslashes($ke['nombre'])) ?> del kit?">
                <i class="bi bi-x"></i>
              </a>
            </div>
          </div>
        <?php endforeach; ?>
        </div>
        <div class="d-flex justify-content-between align-items-center pt-2 mt-2 border-top">
          <span class="text-muted small fw-semibold">SUBTOTAL ELEMENTOS</span>
          <span class="fw-bold text-success"><?= cop($costoElemTotal) ?></span>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ── Prototipos fabricados ── -->
    <div class="col-lg-6">
      <div class="section-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="fw-bold mb-0">
            <span class="text-danger">✂️</span> Prototipos Fabricados
            <span class="badge bg-danger ms-1"><?= count($protoKits) ?></span>
          </h6>
          <button class="btn btn-sm btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#addProtoForm">
            <i class="bi bi-plus-lg"></i>
          </button>
        </div>

        <div class="collapse mb-3" id="addProtoForm">
          <form method="POST" class="bg-light rounded p-3">
            <input type="hidden" name="action" value="add_prototipo">
            <input type="hidden" name="csrf"   value="<?= Auth::csrfToken() ?>">
            <div class="row g-2">
              <div class="col-12">
                <select name="prototipo_id" class="form-select form-select-sm" required>
                  <option value="">Seleccionar prototipo...</option>
                  <?php foreach ($prototipos as $p): ?>
                    <option value="<?= $p['id'] ?>">
                      <?= htmlspecialchars($p['codigo']) ?> &mdash; <?= htmlspecialchars($p['nombre']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-4">
                <input type="number" name="cantidad" class="form-control form-control-sm" min="1" value="1" placeholder="Cant.">
              </div>
              <div class="col-8">
                <input type="text" name="notas" class="form-control form-control-sm" placeholder="Notas (opcional)">
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-danger btn-sm w-100">
                  <i class="bi bi-plus-circle me-1"></i>Agregar Prototipo
                </button>
              </div>
            </div>
            <div class="form-text mt-1">
              ¿No existe? <a href="prototipo_form.php" target="_blank">Crear nuevo prototipo <i class="bi bi-box-arrow-up-right"></i></a>
            </div>
          </form>
        </div>

        <?php if (empty($protoKits)): ?>
          <div class="text-muted text-center py-3 small">
            <div style="font-size:2rem;">✂️</div>
            No hay prototipos en este kit.<br>Agrega piezas de láser o impresión 3D.
          </div>
        <?php else: ?>
        <div class="list-group list-group-flush">
        <?php
        $costoProtoTotal = 0;
        foreach ($protoKits as $kp):
          $costoProtoTotal += $kp['cantidad'] * $kp['costo_total_cop'];
          $tipos = explode(',', $kp['tipo_fabricacion']);
          $tipoIcons = ['laser'=>'✂️','impresion_3d'=>'🖨️','manual'=>'&#x1F527;','electronica'=>'⚡','mixto'=>'🔀'];
        ?>
          <div class="list-group-item px-0 py-2 border-0 border-bottom">
            <div class="d-flex align-items-center gap-2">
              <?php if ($kp['foto']): ?>
                <img src="<?= htmlspecialchars(fotoUrl($kp['foto'])) ?>" class="elem-foto" style="width:38px;height:38px;" alt="">
              <?php else: ?>
                <div class="elem-foto-placeholder" style="width:38px;height:38px;flex-shrink:0;background:#fff0f0;color:#dc3545;font-size:1.2rem;">✂️</div>
              <?php endif; ?>
              <div class="flex-grow-1 min-width-0">
                <div class="fw-semibold small text-truncate"><?= htmlspecialchars($kp['nombre']) ?></div>
                <div class="d-flex gap-1 flex-wrap" style="font-size:.72rem;">
                  <span class="text-muted"><?= $kp['codigo'] ?></span>
                  <?php foreach ($tipos as $t): if(!$t) continue; ?>
                    <span><?= $tipoIcons[$t] ?? '' ?> <?= str_replace('_',' ',ucfirst($t)) ?></span>
                  <?php endforeach; ?>
                </div>
                <?php if ($kp['notas']): ?>
                  <div class="text-muted" style="font-size:.72rem;"><i class="bi bi-chat me-1"></i><?= htmlspecialchars($kp['notas']) ?></div>
                <?php endif; ?>
              </div>
              <div class="text-end flex-shrink-0">
                <div class="fw-bold text-danger">×<?= $kp['cantidad'] ?></div>
                <div class="text-success" style="font-size:.75rem;">
                  <?= $kp['costo_total_cop'] > 0 ? cop($kp['cantidad'] * $kp['costo_total_cop']) : '&mdash;' ?>
                </div>
              </div>
              <a href="?id=<?= $id ?>&del_proto=<?= $kp['id'] ?>&csrf=<?= Auth::csrfToken() ?>"
                 class="btn btn-sm btn-outline-danger py-0 px-1"
                 data-confirm="¿Quitar <?= htmlspecialchars(addslashes($kp['nombre'])) ?> del kit?">
                <i class="bi bi-x"></i>
              </a>
            </div>
          </div>
        <?php endforeach; ?>
        </div>
        <div class="d-flex justify-content-between align-items-center pt-2 mt-2 border-top">
          <span class="text-muted small fw-semibold">SUBTOTAL PROTOTIPOS</span>
          <span class="fw-bold text-danger"><?= cop($costoProtoTotal) ?></span>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Resumen final del kit -->
  <?php
  $costoElemTotal  = array_sum(array_map(fn($k)=>$k['cantidad']*$k['costo_real_cop'],  $elemKits));
  $costoProtoTotal = array_sum(array_map(fn($k)=>$k['cantidad']*$k['costo_total_cop'], $protoKits));
  $totalKit = $costoElemTotal + $costoProtoTotal;
  ?>
  <div class="section-card mt-3">
    <h6 class="fw-bold mb-3"><i class="bi bi-receipt me-2 text-success"></i>Resumen de Costos del Kit</h6>
    <div class="row g-3">
      <div class="col-md-3">
        <div class="p-3 rounded text-center" style="background:#eff6ff;">
          <div class="text-muted small">Elementos Importados</div>
          <div class="fw-bold text-primary fs-5"><?= cop($costoElemTotal) ?></div>
          <div class="text-muted" style="font-size:.75rem;"><?= count($elemKits) ?> tipo(s)</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="p-3 rounded text-center" style="background:#fef2f2;">
          <div class="text-muted small">Prototipos Fabricados</div>
          <div class="fw-bold text-danger fs-5"><?= cop($costoProtoTotal) ?></div>
          <div class="text-muted" style="font-size:.75rem;"><?= count($protoKits) ?> tipo(s)</div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="p-3 rounded text-center" style="background:#f0fdf4;border:2px solid #86efac;">
          <div class="text-muted small">COSTO TOTAL KIT</div>
          <div class="fw-bold text-success fs-4"><?= cop($totalKit) ?></div>
        </div>
      </div>
      <div class="col-md-3">
        <?php if ($precioSug > 0 && $totalKit > 0):
          $margen = round(($precioSug - $totalKit) / $totalKit * 100, 1);
        ?>
        <div class="p-3 rounded text-center" style="background:#fffbeb;">
          <div class="text-muted small">Precio / Margen</div>
          <div class="fw-bold text-warning fs-5"><?= cop($precioSug) ?></div>
          <div class="text-success fw-bold"><?= $margen ?>% margen</div>
        </div>
        <?php else: ?>
        <div class="p-3 rounded text-center bg-light text-muted small">
          Define el precio de venta en la cabecera del kit.
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Barcode del kit -->
    <div class="mt-3 p-3 rounded border text-center">
      <div class="fw-bold mb-1"><?= htmlspecialchars($kit['nombre']) ?> · <?= htmlspecialchars($kit['codigo']) ?></div>
      <svg data-barcode="<?= htmlspecialchars($kit['codigo']) ?>"></svg>
      <div class="mt-2">
        <button type="button" onclick="imprimirBarcode('<?= $kit['codigo'] ?>','<?= addslashes($kit['nombre']) ?>')"
                class="btn btn-sm btn-outline-primary">
          <i class="bi bi-printer me-1"></i>Imprimir Etiqueta del Kit
        </button>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
