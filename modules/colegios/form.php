<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
require_once dirname(__DIR__, 2) . '/includes/Storage.php';
Auth::check();

$db  = Database::get();
$id  = (int)($_GET['id'] ?? 0);
$col = $id ? $db->query("SELECT * FROM colegios WHERE id=$id")->fetch() : null;
$pageTitle  = $col ? 'Editar Colegio' : 'Nuevo Colegio';
$activeMenu = 'colegios';
$error = '';

$GRADOS_OPTS = [
    'transicion' => 'Transición',
    '1'  => '1°',  '2'  => '2°',  '3'  => '3°',  '4'  => '4°',  '5'  => '5°',
    '6'  => '6°',  '7'  => '7°',  '8'  => '8°',  '9'  => '9°',
    '10' => '10°', '11' => '11°',
];

$colGradosExists = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='colegios'
    AND COLUMN_NAME='grados'")->fetchColumn();

$gradosActivos = $colGradosExists
    ? array_filter(array_map('trim', explode(',', $col['grados'] ?? '')))
    : [];

// Crea un curso por grado si aún no existe para este colegio
function crearCursosPorGrado(PDO $db, int $colegioId, array $grados): void {
    $nivelMap = [
        'transicion' => 'preescolar',
        '1' => 'primaria',  '2' => 'primaria',  '3' => 'primaria',
        '4' => 'primaria',  '5' => 'primaria',
        '6' => 'secundaria','7' => 'secundaria','8' => 'secundaria','9' => 'secundaria',
        '10' => 'media',    '11' => 'media',
    ];
    $labelMap = [
        'transicion' => 'Transición',
        '1' => '1°', '2' => '2°', '3' => '3°', '4' => '4°',  '5' => '5°',
        '6' => '6°', '7' => '7°', '8' => '8°', '9' => '9°', '10' => '10°', '11' => '11°',
    ];
    $check = $db->prepare("SELECT COUNT(*) FROM cursos WHERE colegio_id=? AND grado=?");
    $ins   = $db->prepare(
        "INSERT INTO cursos (colegio_id, nombre, grado, nivel, activo, anio)
         VALUES (?, ?, ?, ?, 1, ?)"
    );
    $anio = (int)date('Y');
    foreach ($grados as $g) {
        $g = trim($g);
        if (!$g || !isset($labelMap[$g])) continue;
        $check->execute([$colegioId, $g]);
        if ($check->fetchColumn() == 0) {
            $ins->execute([$colegioId, $labelMap[$g], $g, $nivelMap[$g] ?? 'otro', $anio]);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::csrfVerify($_POST['csrf'] ?? '')) die('CSRF inválido');
    try {
        $logo = $col['logo'] ?? null;
        if (!empty($_FILES['logo']['tmp_name'])) {
            $file = $_FILES['logo'];
            $ext  = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $dest = $col
                ? 'colegio_' . $id . '_' . time() . '.' . $ext
                : 'colegio_'          . time() . '.' . $ext;
            try {
                if ($logo && (str_starts_with($logo, 'http://') || str_starts_with($logo, 'https://'))) {
                    Storage::getInstance()->delete(MINIO_BUCKET_COLEGIOS, basename($logo));
                }
                $logo = Storage::getInstance()->upload($file['tmp_name'], MINIO_BUCKET_COLEGIOS, $dest);
            } catch (Exception $e) {
                error_log('Storage MinIO falló, usando fallback local: ' . $e->getMessage());
                $logo = subirFoto($file, 'colegios');
            }
        }
        $gradosArr = $_POST['grados'] ?? [];
        $gradosStr = is_array($gradosArr) ? (implode(',', array_filter($gradosArr)) ?: null) : null;

        $data = [
            'nombre'    => trim($_POST['nombre']),
            'nit'       => trim($_POST['nit']       ?? ''),
            'ciudad'    => trim($_POST['ciudad']     ?? 'Bogotá'),
            'direccion' => trim($_POST['direccion']  ?? ''),
            'tipo'      => $_POST['tipo']  ?? 'privado',
            'contacto'  => trim($_POST['contacto']   ?? ''),
            'email'     => trim($_POST['email']      ?? ''),
            'telefono'  => trim($_POST['telefono']   ?? ''),
            'rector'    => trim($_POST['rector']     ?? ''),
            'notas'     => trim($_POST['notas']      ?? ''),
            'logo'      => $logo,
            'activo'    => 1,
        ];
        if ($colGradosExists) {
            $data['grados'] = $gradosStr;
        }
        // Si nivel sigue siendo NOT NULL (antes de migración), proveer valor por defecto
        $nivelColInfo = $db->query("SELECT IS_NULLABLE, COLUMN_TYPE FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='colegios' AND COLUMN_NAME='nivel'")->fetch(PDO::FETCH_ASSOC);
        if ($nivelColInfo && $nivelColInfo['IS_NULLABLE'] === 'NO') {
            preg_match_all("/'([^']+)'/", $nivelColInfo['COLUMN_TYPE'] ?? '', $m);
            $data['nivel'] = $m[1][0] ?? '';
        }
        if ($col) {
            $sets = implode(',', array_map(fn($k)=>"$k=:$k", array_keys($data)));
            $data['id'] = $id;
            $db->prepare("UPDATE colegios SET $sets WHERE id=:id")->execute($data);
            if ($gradosArr) crearCursosPorGrado($db, $id, $gradosArr);
            header('Location: ' . APP_URL . '/modules/colegios/ver.php?id=' . $id . '&ok=guardado'); exit;
        } else {
            $cols = implode(',', array_keys($data));
            $vals = ':' . implode(',:', array_keys($data));
            $db->prepare("INSERT INTO colegios ($cols) VALUES ($vals)")->execute($data);
            $newId = $db->lastInsertId();
            if ($gradosArr) crearCursosPorGrado($db, $newId, $gradosArr);
            header('Location: ' . APP_URL . '/modules/colegios/ver.php?id=' . $newId . '&ok=creado'); exit;
        }
    } catch (Exception $e) { $error = $e->getMessage(); }
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="d-flex align-items-center gap-2 mb-4">
  <a href="<?= APP_URL ?>/modules/colegios/" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i></a>
  <h4 class="fw-bold mb-0"><?= $pageTitle ?></h4>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<form method="POST" enctype="multipart/form-data">
  <input type="hidden" name="csrf" value="<?= Auth::csrfToken() ?>">

  <div class="row g-4">
    <!-- ── Izquierda: datos principales ── -->
    <div class="col-lg-8">

      <div class="section-card">
        <h6 class="fw-bold mb-3 text-primary"><i class="bi bi-building me-2"></i>Información Institucional</h6>
        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label">Nombre del Colegio *</label>
            <input type="text" name="nombre" class="form-control" required maxlength="200"
                   value="<?= htmlspecialchars($col['nombre'] ?? '') ?>"
                   placeholder="Ej: Colegio San José de la Salle">
          </div>
          <div class="col-md-4">
            <label class="form-label">NIT</label>
            <input type="text" name="nit" class="form-control" maxlength="30"
                   value="<?= htmlspecialchars($col['nit'] ?? '') ?>" placeholder="800.000.000-1">
          </div>
          <div class="col-md-4">
            <label class="form-label">Tipo</label>
            <select name="tipo" class="form-select">
              <option value="privado"  <?= ($col['tipo']??'privado')==='privado'?'selected':'' ?>>Privado</option>
              <option value="publico"  <?= ($col['tipo']??'')==='publico'?'selected':'' ?>>Público</option>
              <option value="mixto"    <?= ($col['tipo']??'')==='mixto'?'selected':'' ?>>Mixto</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Ciudad</label>
            <input type="text" name="ciudad" class="form-control"
                   value="<?= htmlspecialchars($col['ciudad'] ?? 'Bogotá') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Rector / Directivo</label>
            <input type="text" name="rector" class="form-control"
                   value="<?= htmlspecialchars($col['rector'] ?? '') ?>"
                   placeholder="Nombre del rector">
          </div>
          <div class="col-12">
            <label class="form-label">Dirección</label>
            <input type="text" name="direccion" class="form-control"
                   value="<?= htmlspecialchars($col['direccion'] ?? '') ?>"
                   placeholder="Calle 45 #10-20, Bogotá">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Grados que maneja</label>
            <?php if (!$colGradosExists): ?>
            <div class="alert alert-warning py-1 small mb-2">
              <i class="bi bi-exclamation-triangle me-1"></i>
              Ejecuta <code>migration_grados_kits.sql</code> para activar este campo.
            </div>
            <?php endif; ?>
            <div class="d-flex flex-wrap gap-3 mt-1 p-2 rounded" style="background:#f8fafc;border:1px solid #e2e8f0">
              <?php foreach ($GRADOS_OPTS as $val => $lbl): ?>
              <div class="form-check m-0">
                <input class="form-check-input" type="checkbox" name="grados[]"
                       id="grado_<?= $val ?>" value="<?= $val ?>"
                       <?= in_array($val, $gradosActivos) ? 'checked' : '' ?>
                       <?= !$colGradosExists ? 'disabled' : '' ?>>
                <label class="form-check-label small fw-semibold" for="grado_<?= $val ?>">
                  <?= $lbl ?>
                </label>
              </div>
              <?php endforeach; ?>
            </div>
            <div class="form-text">Selecciona todos los grados que ofrece el colegio.</div>
          </div>
        </div>
      </div>

      <div class="section-card">
        <h6 class="fw-bold mb-3 text-primary"><i class="bi bi-person-lines-fill me-2"></i>Datos de Contacto</h6>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Persona de contacto</label>
            <input type="text" name="contacto" class="form-control"
                   value="<?= htmlspecialchars($col['contacto'] ?? '') ?>"
                   placeholder="Nombre del coordinador TIC">
          </div>
          <div class="col-md-6">
            <label class="form-label">Teléfono</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-telephone"></i></span>
              <input type="text" name="telefono" class="form-control"
                     value="<?= htmlspecialchars($col['telefono'] ?? '') ?>"
                     placeholder="601 234 5678">
            </div>
          </div>
          <div class="col-12">
            <label class="form-label">Email institucional</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-envelope"></i></span>
              <input type="email" name="email" class="form-control"
                     value="<?= htmlspecialchars($col['email'] ?? '') ?>"
                     placeholder="colegio@example.edu.co">
            </div>
          </div>
          <div class="col-12">
            <label class="form-label">Notas internas</label>
            <textarea name="notas" class="form-control" rows="3"
                      placeholder="Observaciones, convenios, condiciones especiales..."><?= htmlspecialchars($col['notas'] ?? '') ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <!-- ── Derecha: logo y acciones ── -->
    <div class="col-lg-4">
      <div class="section-card text-center">
        <h6 class="fw-bold mb-3"><i class="bi bi-image me-2 text-primary"></i>Logo del Colegio</h6>
        <?php if ($col && $col['logo']): ?>
          <img src="<?= htmlspecialchars(fotoUrl($col['logo'])) ?>" id="logoPreview"
               class="img-fluid rounded mb-2" style="max-height:150px;object-fit:contain;" alt="">
        <?php else: ?>
          <div class="bg-light rounded d-flex align-items-center justify-content-center mb-2" style="height:120px;">
            <i class="bi bi-building fs-1 text-muted"></i>
          </div>
          <img id="logoPreview" src="" style="display:none;max-height:150px;" class="img-fluid rounded mb-2">
        <?php endif; ?>
        <input type="file" name="logo" class="form-control form-control-sm img-preview-input"
               accept="image/*" data-preview="logoPreview">
        <div class="form-text">PNG, JPG · Max 5MB</div>
      </div>

      <div class="d-grid gap-2 mt-3">
        <button type="submit" class="btn btn-primary btn-lg fw-bold">
          <i class="bi bi-save me-2"></i><?= $col ? 'Guardar Cambios' : 'Crear Colegio' ?>
        </button>
        <a href="<?= APP_URL ?>/modules/colegios/" class="btn btn-outline-secondary">Cancelar</a>
      </div>

      <?php if ($col): ?>
      <div class="section-card mt-3">
        <h6 class="fw-bold mb-2 text-success"><i class="bi bi-bar-chart me-2"></i>Resumen</h6>
        <?php
        $res = $db->query("SELECT COUNT(*) AS c, SUM(num_estudiantes) AS e FROM cursos WHERE colegio_id=$id AND activo=1")->fetch();
        ?>
        <div class="d-flex justify-content-between py-1 border-bottom">
          <span class="text-muted small">Cursos activos</span>
          <strong><?= $res['c'] ?></strong>
        </div>
        <div class="d-flex justify-content-between py-1">
          <span class="text-muted small">Estudiantes</span>
          <strong><?= number_format($res['e'] ?? 0) ?></strong>
        </div>
        <a href="<?= APP_URL ?>/modules/colegios/ver.php?id=<?= $id ?>" class="btn btn-sm btn-outline-primary w-100 mt-2">
          <i class="bi bi-eye me-1"></i>Ver cursos y kits
        </a>
      </div>
      <?php endif; ?>
    </div>
  </div>
</form>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
