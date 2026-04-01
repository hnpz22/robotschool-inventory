<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
require_once dirname(__DIR__, 2) . '/includes/Storage.php';
Auth::check();

$db = Database::get();
$id = (int)($_GET['id'] ?? 0);
$elem = $id ? $db->query("SELECT * FROM elementos WHERE id=$id")->fetch() : null;
$pageTitle  = $elem ? 'Editar Elemento' : 'Nuevo Elemento';
$activeMenu = 'elementos';
$error = $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::csrfVerify($_POST['csrf'] ?? '')) die('CSRF inválido');
    try {
        $catId = (int)$_POST['categoria_id'];

        // Para nuevos elementos generamos el código antes de subir la foto,
        // así podemos incluirlo en el nombre del archivo en MinIO.
        $codigoNuevo = null;
        if (!$elem) {
            $codigoNuevo = generarCodigo($catId);
        }

        $foto        = $elem['foto'] ?? null;
        $codigoFoto  = $elem ? $elem['codigo'] : $codigoNuevo;

        if (!empty($_FILES['foto']['tmp_name'])) {
            $file = $_FILES['foto'];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $dest = $codigoFoto . '_' . time() . '.' . $ext;
            try {
                // Borrar foto anterior de MinIO si era una URL de MinIO
                if ($foto && (str_starts_with($foto, 'http://') || str_starts_with($foto, 'https://'))) {
                    Storage::getInstance()->delete(MINIO_BUCKET_ELEMENTOS, basename($foto));
                }
                $foto = Storage::getInstance()->upload($file['tmp_name'], MINIO_BUCKET_ELEMENTOS, $dest);
            } catch (Exception $e) {
                // Fallback: guardar en assets/uploads/ si MinIO no está disponible
                error_log('Storage MinIO falló, usando fallback local: ' . $e->getMessage());
                $foto = subirFoto($file, 'elementos');
            }
        }

        $data = [
            'categoria_id'    => $catId,
            'proveedor_id'    => $_POST['proveedor_id'] ?: null,
            'nombre'          => trim($_POST['nombre']),
            'descripcion'     => trim($_POST['descripcion']),
            'especificaciones'=> trim($_POST['especificaciones']),
            'foto'            => $foto,
            'precio_usd'      => (float)str_replace(',','.',($_POST['precio_usd']??0)),
            'peso_gramos'     => (float)str_replace(',','.',($_POST['peso_gramos']??0)),
            'largo_mm'        => ($_POST['largo_mm'] ?: null),
            'ancho_mm'        => ($_POST['ancho_mm'] ?: null),
            'alto_mm'         => ($_POST['alto_mm']  ?: null),
            'unidad'          => $_POST['unidad'] ?? 'unidad',
            'stock_minimo'    => (int)$_POST['stock_minimo'],
            'stock_maximo'    => (int)$_POST['stock_maximo'],
            'ubicacion'       => trim($_POST['ubicacion']),
            'precio_venta_cop'=> (float)str_replace(',','.',($_POST['precio_venta_cop']??0)),
        ];
        if ($elem) {
            // UPDATE
            $sets = implode(',', array_map(fn($k)=>"$k=:$k", array_keys($data)));
            $st = $db->prepare("UPDATE elementos SET $sets WHERE id=:id");
            $data['id'] = $id;
            $st->execute($data);
            auditoria('editar_elemento','elementos',$id, $elem, $data);
            $success = 'Elemento actualizado correctamente.';
            $elem = $db->query("SELECT * FROM elementos WHERE id=$id")->fetch();
        } else {
            // INSERT — código ya generado antes de la subida de foto
            $data['codigo']     = $codigoNuevo;
            $data['stock_actual']= 0;
            $data['created_by'] = Auth::user()['id'];
            $cols = implode(',', array_keys($data));
            $vals = ':' . implode(',:', array_keys($data));
            $db->prepare("INSERT INTO elementos ($cols) VALUES ($vals)")->execute($data);
            $newId = $db->lastInsertId();
            auditoria('crear_elemento','elementos',$newId, [], $data);
            header('Location: ' . APP_URL . '/modules/elementos/form.php?id=' . $newId . '&ok=1');
            exit;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

if (!empty($_GET['ok'])) $success = 'Elemento creado correctamente.';

$categorias = $db->query("SELECT id,nombre,prefijo FROM categorias WHERE activa=1 ORDER BY nombre")->fetchAll();
$proveedores = $db->query("SELECT id,nombre FROM proveedores WHERE activo=1 ORDER BY nombre")->fetchAll();

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="d-flex align-items-center gap-2 mb-4">
  <a href="<?= APP_URL ?>/modules/elementos/" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i></a>
  <div>
    <h4 class="fw-bold mb-0"><?= $pageTitle ?></h4>
    <?php if ($elem): ?>
      <code class="text-primary"><?= $elem['codigo'] ?></code>
    <?php endif; ?>
  </div>
</div>

<?php if ($error): ?><div class="alert alert-danger"><i class="bi bi-x-circle me-2"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?= $success ?></div><?php endif; ?>

<form method="POST" enctype="multipart/form-data">
  <input type="hidden" name="csrf" value="<?= Auth::csrfToken() ?>">

  <div class="row g-4">
    <!-- ── Columna izquierda ── -->
    <div class="col-lg-8">

      <!-- Información básica -->
      <div class="section-card">
        <h6 class="fw-bold mb-3 text-primary"><i class="bi bi-info-circle me-2"></i>Información Básica</h6>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Categoría *</label>
            <select name="categoria_id" class="form-select" required>
              <option value="">Seleccionar...</option>
              <?php foreach ($categorias as $c): ?>
                <option value="<?= $c['id'] ?>" <?= ($elem['categoria_id'] ?? 0)==$c['id']?'selected':'' ?>>
                  <?= htmlspecialchars($c['nombre']) ?> (<?= $c['prefijo'] ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Proveedor</label>
            <select name="proveedor_id" class="form-select">
              <option value="">Sin proveedor</option>
              <?php foreach ($proveedores as $p): ?>
                <option value="<?= $p['id'] ?>" <?= ($elem['proveedor_id'] ?? 0)==$p['id']?'selected':'' ?>>
                  <?= htmlspecialchars($p['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Nombre del Elemento *</label>
            <input type="text" name="nombre" class="form-control" required maxlength="200"
                   value="<?= htmlspecialchars($elem['nombre'] ?? '') ?>"
                   placeholder="Ej: Arduino UNO R3 CH340">
          </div>
          <div class="col-12">
            <label class="form-label">Descripción</label>
            <textarea name="descripcion" class="form-control" rows="3" placeholder="Descripción detallada del elemento..."><?= htmlspecialchars($elem['descripcion'] ?? '') ?></textarea>
          </div>
          <div class="col-12">
            <label class="form-label">Especificaciones Técnicas <span class="text-muted fw-normal">(JSON o texto libre)</span></label>
            <textarea name="especificaciones" class="form-control font-monospace" rows="4"
                      placeholder='{"voltaje":"5V","frecuencia":"16MHz","flash":"32KB"}'><?= htmlspecialchars($elem['especificaciones'] ?? '') ?></textarea>
          </div>
        </div>
      </div>

      <!-- Compra e importación -->
      <div class="section-card">
        <h6 class="fw-bold mb-3 text-warning"><i class="bi bi-airplane me-2"></i>Datos de Compra e Importación</h6>
        <div class="alert alert-info py-2 small mb-3">
          <i class="bi bi-info-circle me-1"></i>El <strong>costo real en COP</strong> se calcula automáticamente al liquidar un pedido de importación, distribuyendo el flete DHL y aranceles proporcionalmente por peso.
        </div>
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Precio Unitario USD *</label>
            <div class="input-group">
              <span class="input-group-text">$</span>
              <input type="number" name="precio_usd" class="form-control" step="0.0001" min="0"
                     value="<?= $elem['precio_usd'] ?? '' ?>" placeholder="0.0000">
              <span class="input-group-text">USD</span>
            </div>
          </div>
          <div class="col-md-3">
            <label class="form-label">Peso Unitario *</label>
            <div class="input-group">
              <input type="number" name="peso_gramos" class="form-control" step="0.001" min="0"
                     value="<?= $elem['peso_gramos'] ?? '' ?>" placeholder="0.000">
              <span class="input-group-text">g</span>
            </div>
            <div class="form-text">Crítico para liquidar flete DHL</div>
          </div>
          <div class="col-md-2">
            <label class="form-label">Largo (mm)</label>
            <input type="number" name="largo_mm" class="form-control" step="0.1" min="0" value="<?= $elem['largo_mm'] ?? '' ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label">Ancho (mm)</label>
            <input type="number" name="ancho_mm" class="form-control" step="0.1" min="0" value="<?= $elem['ancho_mm'] ?? '' ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label">Alto (mm)</label>
            <input type="number" name="alto_mm" class="form-control" step="0.1" min="0" value="<?= $elem['alto_mm'] ?? '' ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Costo Real (COP)</label>
            <div class="input-group">
              <span class="input-group-text">$</span>
              <input type="text" class="form-control bg-light" readonly
                     value="<?= $elem ? number_format($elem['costo_real_cop'],0,',','.') : '0' ?>">
            </div>
            <div class="form-text">Se actualiza al liquidar pedido</div>
          </div>
          <div class="col-md-3">
            <label class="form-label">Precio Venta (COP)</label>
            <div class="input-group">
              <span class="input-group-text">$</span>
              <input type="number" name="precio_venta_cop" class="form-control" step="1" min="0"
                     value="<?= $elem['precio_venta_cop'] ?? '' ?>">
            </div>
          </div>
          <div class="col-md-3">
            <label class="form-label">Unidad de medida</label>
            <select name="unidad" class="form-select">
              <?php foreach (['unidad','par','set','rollo','metro','kg','litro'] as $u): ?>
                <option value="<?= $u ?>" <?= ($elem['unidad']??'unidad')===$u?'selected':'' ?>><?= ucfirst($u) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <!-- Stock -->
      <div class="section-card">
        <h6 class="fw-bold mb-3 text-success"><i class="bi bi-boxes me-2"></i>Control de Stock y Ubicación</h6>
        <div class="row g-3">
          <div class="col-md-3">
            <label class="form-label">Stock Mínimo 🟡</label>
            <input type="number" name="stock_minimo" class="form-control" min="0"
                   value="<?= $elem['stock_minimo'] ?? 5 ?>">
            <div class="form-text">Dispara alerta amarilla</div>
          </div>
          <div class="col-md-3">
            <label class="form-label">Stock Máximo 🔵</label>
            <input type="number" name="stock_maximo" class="form-control" min="1"
                   value="<?= $elem['stock_maximo'] ?? 100 ?>">
            <div class="form-text">Nivel óptimo/lleno</div>
          </div>
          <div class="col-md-6">
            <label class="form-label">Ubicación Física</label>
            <input type="text" name="ubicacion" class="form-control" maxlength="100"
                   value="<?= htmlspecialchars($elem['ubicacion'] ?? '') ?>"
                   placeholder="Ej: Estante A, Cajón 3, Caja azul">
          </div>
        </div>
      </div>
    </div>

    <!-- ── Columna derecha ── -->
    <div class="col-lg-4">

      <!-- Código generado -->
      <?php if ($elem): ?>
      <div class="section-card text-center">
        <h6 class="fw-bold mb-3"><i class="bi bi-upc me-2 text-primary"></i>Código de Barras</h6>
        <code class="fs-5 text-primary d-block mb-2"><?= $elem['codigo'] ?></code>
        <div class="barcode-container">
          <svg data-barcode="<?= htmlspecialchars($elem['codigo']) ?>"></svg>
        </div>
        <button type="button" onclick="imprimirBarcode('<?= $elem['codigo'] ?>','<?= addslashes($elem['nombre']) ?>')"
                class="btn btn-outline-primary btn-sm mt-2 w-100">
          <i class="bi bi-printer me-1"></i>Imprimir Etiqueta
        </button>
      </div>
      <?php else: ?>
      <div class="section-card text-center text-muted">
        <i class="bi bi-upc fs-2 d-block mb-2"></i>
        <p class="small">El código de barras se genera automáticamente al guardar el elemento.</p>
      </div>
      <?php endif; ?>

      <!-- Fotos -->
      <div class="section-card">
        <h6 class="fw-bold mb-3"><i class="bi bi-image me-2 text-primary"></i>Foto del Elemento</h6>
        <?php if ($elem && $elem['foto']): ?>
          <img src="<?= htmlspecialchars(fotoUrl($elem['foto'])) ?>" class="img-fluid rounded mb-2 w-100" style="max-height:180px;object-fit:cover;" id="fotoPreview" alt="">
        <?php else: ?>
          <div class="bg-light rounded text-center p-4 mb-2" id="fotoPreviewBox">
            <i class="bi bi-image fs-2 text-muted"></i>
            <p class="small text-muted mt-1">Sin foto</p>
          </div>
          <img id="fotoPreview" src="" class="img-fluid rounded mb-2 w-100" style="max-height:180px;object-fit:cover;display:none;" alt="">
        <?php endif; ?>
        <input type="file" name="foto" class="form-control form-control-sm img-preview-input"
               accept="image/*" data-preview="fotoPreview">
        <div class="form-text">JPG, PNG, WebP · Max 5MB</div>
      </div>

      <!-- Botón guardar -->
      <div class="d-grid gap-2">
        <button type="submit" class="btn btn-primary btn-lg fw-bold">
          <i class="bi bi-save me-2"></i><?= $elem ? 'Guardar Cambios' : 'Crear Elemento' ?>
        </button>
        <a href="<?= APP_URL ?>/modules/elementos/" class="btn btn-outline-secondary">Cancelar</a>
      </div>
    </div>
  </div>
</form>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
