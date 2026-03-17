<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();

$db = Database::get();
$id = (int)($_GET['id'] ?? 0);
$curso = $id ? $db->query("SELECT * FROM escuela_cursos WHERE id=$id")->fetch() : null;
$pageTitle  = $curso ? 'Editar Curso' : 'Nuevo Curso';
$activeMenu = 'cursos';
$error = '';

// Guardar
if ($_SERVER['REQUEST_METHOD']==='POST' && Auth::csrfVerify($_POST['csrf']??'')) {
    try {
        $objetivos = array_values(array_filter(explode("\n", trim($_POST['objetivos']??''))));
        $tematicas = array_values(array_filter(explode("\n", trim($_POST['tematicas']??''))));
        $data = [
            'nombre'           => trim($_POST['nombre']),
            'categoria'        => $_POST['categoria'] ?? 'robotica',
            'descripcion'      => trim($_POST['descripcion'] ?? ''),
            'objetivos'        => json_encode($objetivos),
            'tematicas'        => json_encode($tematicas),
            'color_primario'   => $_POST['color_primario'] ?? '#185FA5',
            'color_secundario' => $_POST['color_secundario'] ?? '#0f4c81',
            'edad_min'         => (int)($_POST['edad_min'] ?? 6),
            'edad_max'         => (int)($_POST['edad_max'] ?? 17),
            'nivel'            => $_POST['nivel'] ?? 'basico',
            'duracion_min'     => (int)($_POST['duracion_min'] ?? 120),
            'num_sesiones'     => (int)($_POST['num_sesiones'] ?? 16),
            'cupo_max'         => (int)($_POST['cupo_max'] ?? 10),
            'precio'           => (float)str_replace(['.','$','COP',' '],'',$_POST['precio']??'0'),
            'precio_semestral' => (float)str_replace(['.','$','COP',' '],'',$_POST['precio_semestral']??'0'),
            'destacado'        => isset($_POST['destacado']) ? 1 : 0,
            'activo'           => 1,
        ];

        // Manejo de imagen — usa subirFoto() que valida extensión, tamaño y MIME real
        if (!empty($_FILES['imagen']['name']) && ($_FILES['imagen']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $data['imagen'] = subirFoto($_FILES['imagen'], 'cursos');
        }

        if ($id) {
            $sets = implode(',', array_map(function($k){ return "$k=:$k"; }, array_keys($data)));
            $data['id'] = $id;
            $db->prepare("UPDATE escuela_cursos SET $sets WHERE id=:id")->execute($data);
        } else {
            $cols2 = implode(',', array_keys($data));
            $vals2 = ':'.implode(',:', array_keys($data));
            $db->prepare("INSERT INTO escuela_cursos ($cols2) VALUES ($vals2)")->execute($data);
            $id = $db->lastInsertId();
        }
        header('Location: '.APP_URL.'/modules/cursos/ver.php?id='.$id.'&ok=1'); exit;
    } catch (Exception $e) { $error = $e->getMessage(); }
}

$CATS   = ['robotica'=>'Robotica','programacion'=>'Programacion','videojuegos'=>'Videojuegos','impresion3d'=>'Impresion 3D','electronica'=>'Electronica','maker'=>'Maker','otro'=>'Otro'];
$NIVELES= ['inicial'=>'Inicial','basico'=>'Basico','intermedio'=>'Intermedio','avanzado'=>'Avanzado'];

$objArr = $curso ? json_decode($curso['objetivos'] ?? '[]', true) : [];
$temArr = $curso ? json_decode($curso['tematicas'] ?? '[]', true) : [];

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
.sc{background:#fff;border-radius:14px;border:1px solid #e2e8f0;padding:1.2rem;margin-bottom:1rem}
.preview-img{width:100%;height:180px;object-fit:cover;border-radius:10px;border:1px solid #e2e8f0}
.preview-ph{width:100%;height:180px;border-radius:10px;border:2px dashed #e2e8f0;display:flex;align-items:center;justify-content:center;font-size:2rem;color:#94a3b8;cursor:pointer;transition:.15s}
.preview-ph:hover{border-color:#185FA5;background:#f0f7ff}
</style>

<div class="d-flex align-items-center gap-2 mb-3">
  <a href="index.php" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i></a>
  <h4 class="fw-bold mb-0"><?= $curso ? 'Editar' : 'Nuevo' ?> Curso</h4>
</div>

<?php if ($error): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<form method="POST" enctype="multipart/form-data">
<input type="hidden" name="csrf" value="<?= Auth::csrfToken() ?>">

<div class="row g-3">

  <!-- Columna izquierda: imagen y colores -->
  <div class="col-lg-4">
    <div class="sc">
      <h6 class="fw-bold mb-3">Imagen del curso</h6>
      <?php if ($curso && $curso['imagen']): ?>
        <img src="<?= UPLOAD_URL.htmlspecialchars($curso['imagen']) ?>" class="preview-img mb-2" id="imgPreview" alt="">
      <?php else: ?>
        <div class="preview-ph mb-2" id="imgPreview" onclick="document.getElementById('fileImg').click()">
          &#x1F4F7; Clic para subir imagen
        </div>
      <?php endif; ?>
      <input type="file" name="imagen" id="fileImg" accept="image/*" class="d-none"
             onchange="previewImg(this)">
      <button type="button" class="btn btn-outline-secondary btn-sm w-100" onclick="document.getElementById('fileImg').click()">
        <i class="bi bi-upload me-1"></i>Cambiar imagen
      </button>
    </div>

    <div class="sc">
      <h6 class="fw-bold mb-3">Colores</h6>
      <div class="row g-2">
        <div class="col-6">
          <label class="form-label small">Color principal</label>
          <input type="color" name="color_primario" class="form-control form-control-color w-100" style="height:40px"
                 value="<?= $curso['color_primario'] ?? '#185FA5' ?>">
        </div>
        <div class="col-6">
          <label class="form-label small">Color secundario</label>
          <input type="color" name="color_secundario" class="form-control form-control-color w-100" style="height:40px"
                 value="<?= $curso['color_secundario'] ?? '#0f4c81' ?>">
        </div>
      </div>
    </div>

    <div class="sc">
      <h6 class="fw-bold mb-3">Configuracion</h6>
      <div class="row g-2">
        <div class="col-6">
          <label class="form-label small">Edad minima</label>
          <input type="number" name="edad_min" class="form-control form-control-sm" min="4" max="18" value="<?= $curso['edad_min'] ?? 6 ?>">
        </div>
        <div class="col-6">
          <label class="form-label small">Edad maxima</label>
          <input type="number" name="edad_max" class="form-control form-control-sm" min="4" max="20" value="<?= $curso['edad_max'] ?? 17 ?>">
        </div>
        <div class="col-6">
          <label class="form-label small">Duracion (min/sesion)</label>
          <input type="number" name="duracion_min" class="form-control form-control-sm" value="<?= $curso['duracion_min'] ?? 120 ?>">
        </div>
        <div class="col-6">
          <label class="form-label small">Num. sesiones</label>
          <input type="number" name="num_sesiones" class="form-control form-control-sm" value="<?= $curso['num_sesiones'] ?? 16 ?>">
        </div>
        <div class="col-12">
          <label class="form-label small fw-semibold">
            <i class="bi bi-people me-1 text-primary"></i>Cupo m&aacute;ximo de estudiantes
          </label>
          <div class="input-group">
            <span class="input-group-text" style="background:#e0e7ff;color:#3730a3">
              <i class="bi bi-people-fill"></i>
            </span>
            <input type="number" name="cupo_max" class="form-control form-control-sm"
                   min="1" max="100" step="1"
                   value="<?php
                     if ($curso && array_key_exists('cupo_max',$curso)) echo (int)$curso['cupo_max'];
                     else echo 10;
                   ?>"
                   placeholder="Ej: 10" required>
            <span class="input-group-text text-muted" style="font-size:.8rem">
              estudiantes por horario
            </span>
          </div>
          <div class="form-text">
            <i class="bi bi-info-circle me-1"></i>
            Cupos = cantidad de kits o computadores disponibles. Se puede ajustar por horario.
          </div>
        </div>
        <div class="col-6">
          <label class="form-label small">Precio mensual</label>
          <input type="text" name="precio" class="form-control form-control-sm" value="<?= $curso['precio'] ?? 0 ?>">
        </div>
        <div class="col-6">
          <label class="form-label small">Precio semestral</label>
          <input type="text" name="precio_semestral" class="form-control form-control-sm" value="<?= $curso['precio_semestral'] ?? 0 ?>">
        </div>
        <div class="col-12">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" name="destacado" id="chkDest" value="1" <?= ($curso['destacado']??0)?'checked':'' ?>>
            <label class="form-check-label small" for="chkDest">&#11088; Curso destacado</label>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Columna derecha: datos principales -->
  <div class="col-lg-8">
    <div class="sc">
      <h6 class="fw-bold mb-3">Datos del curso</h6>
      <div class="row g-3">
        <div class="col-12">
          <label class="form-label small fw-semibold">Nombre del curso *</label>
          <input type="text" name="nombre" class="form-control" required
                 placeholder="Ej: Robotica con Lego Spike"
                 value="<?= htmlspecialchars($curso['nombre'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-semibold">Categoria</label>
          <select name="categoria" class="form-select">
            <?php foreach ($CATS as $ck => $cv): ?>
              <option value="<?= $ck ?>" <?= ($curso['categoria']??'robotica')===$ck?'selected':'' ?>><?= $cv ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-semibold">Nivel</label>
          <select name="nivel" class="form-select">
            <?php foreach ($NIVELES as $nk => $nv): ?>
              <option value="<?= $nk ?>" <?= ($curso['nivel']??'basico')===$nk?'selected':'' ?>><?= $nv ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label small fw-semibold">Descripcion del curso</label>
          <textarea name="descripcion" class="form-control" rows="3"
                    placeholder="Descripcion atractiva para los padres y estudiantes..."><?= htmlspecialchars($curso['descripcion'] ?? '') ?></textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-semibold">Objetivos del curso</label>
          <div class="form-text mb-1">Un objetivo por linea</div>
          <textarea name="objetivos" class="form-control form-control-sm" rows="5"
                    placeholder="Pensamiento computacional&#10;Programacion por bloques&#10;Trabajo en equipo"><?= htmlspecialchars(implode("\n", $objArr)) ?></textarea>
        </div>
        <div class="col-md-6">
          <label class="form-label small fw-semibold">Tematicas del curso</label>
          <div class="form-text mb-1">Un tema por linea</div>
          <textarea name="tematicas" class="form-control form-control-sm" rows="5"
                    placeholder="Introduccion a la robotica&#10;Sensores y motores&#10;Programacion Spike"><?= htmlspecialchars(implode("\n", $temArr)) ?></textarea>
        </div>
      </div>
    </div>

    <div class="d-flex gap-2">
      <button type="submit" class="btn btn-primary fw-bold flex-grow-1">
        <i class="bi bi-save me-1"></i><?= $curso ? 'Guardar Cambios' : 'Crear Curso' ?>
      </button>
      <a href="<?= $curso?'ver.php?id='.$id:'index.php' ?>" class="btn btn-outline-secondary">Cancelar</a>
    </div>
  </div>

</div>
</form>

<script>
function previewImg(input) {
    if (!input.files || !input.files[0]) return;
    var reader = new FileReader();
    reader.onload = function(e) {
        var ph = document.getElementById('imgPreview');
        ph.outerHTML = '<img src="'+e.target.result+'" class="preview-img mb-2" id="imgPreview" alt="">';
    };
    reader.readAsDataURL(input.files[0]);
}
</script>
<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
