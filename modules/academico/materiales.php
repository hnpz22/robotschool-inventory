<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();

$db         = Database::get();
$pageTitle  = 'Materiales';
$activeMenu = 'academico';
$cursoId    = (int)($_GET['curso_id'] ?? 0);
$estId      = (int)($_GET['est_id']   ?? 0);
$error = $success = '';

// Guardar material
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='save_mat') {
    if (!Auth::csrfVerify($_POST['csrf']??'')) die('CSRF');
    try {
        $data = [
            'curso_id'    => (int)$_POST['curso_id'],
            'nombre'      => trim($_POST['nombre']),
            'tipo'        => $_POST['tipo'] ?? 'guia',
            'descripcion' => trim($_POST['descripcion'] ?? ''),
            'url'         => trim($_POST['url'] ?? '') ?: null,
            'para_grado'  => trim($_POST['para_grado'] ?? '') ?: null,
            'activo'      => 1,
        ];
        $cols2 = implode(',', array_keys($data));
        $vals2 = ':'.implode(',:', array_keys($data));
        $db->prepare("INSERT INTO acad_materiales ($cols2) VALUES ($vals2)")->execute($data);
        $success = 'Material guardado.';
    } catch (Exception $e) { $error = $e->getMessage(); }
}

// Asignar material a estudiante
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='asignar') {
    if (!Auth::csrfVerify($_POST['csrf']??'')) die('CSRF');
    try {
        $matIds = $_POST['mat_ids'] ?? [];
        $estIds = $_POST['est_ids'] ?? [];
        $count  = 0;
        foreach ($matIds as $mid) {
            foreach ($estIds as $eid) {
                $db->prepare("INSERT IGNORE INTO acad_asignaciones (material_id,estudiante_id) VALUES (?,?)")
                   ->execute([(int)$mid,(int)$eid]);
                $count++;
            }
        }
        $success = "$count asignacion(es) creada(s).";
    } catch (Exception $e) { $error = $e->getMessage(); }
}

// Marcar entregado
if (isset($_GET['entregar']) && Auth::csrfVerify($_GET['csrf']??'')) {
    $db->prepare("UPDATE acad_asignaciones SET entregado=1,fecha_entrega=CURDATE() WHERE id=?")->execute([(int)$_GET['entregar']]);
    $success = 'Marcado como entregado.';
}

// Datos
$cursos    = $db->query("SELECT cur.id, CONCAT(ac.nombre,' - ',cur.nombre) AS label FROM acad_cursos cur JOIN acad_colegios ac ON ac.id=cur.colegio_id WHERE cur.activo=1 ORDER BY ac.nombre,cur.nombre")->fetchAll();
$materiales= $cursoId ? $db->query("SELECT * FROM acad_materiales WHERE curso_id=$cursoId AND activo=1 ORDER BY tipo,nombre")->fetchAll() : [];
$estudiantes= $cursoId ? $db->query("SELECT * FROM acad_estudiantes WHERE curso_id=$cursoId AND activo=1 ORDER BY apellidos,nombres")->fetchAll() : [];

// Asignaciones si hay filtro de estudiante
$asignaciones = [];
if ($estId && $cursoId) {
    $asignaciones = $db->query("
        SELECT asig.*, mat.nombre AS mat_nombre, mat.tipo AS mat_tipo
        FROM acad_asignaciones asig
        JOIN acad_materiales mat ON mat.id=asig.material_id
        WHERE asig.estudiante_id=$estId
        ORDER BY mat.tipo, mat.nombre
    ")->fetchAll();
}

$TIPO_MAT = ['libro'=>['label'=>'Libro','icon'=>'bi-book','color'=>'#185FA5'],'guia'=>['label'=>'Guia','icon'=>'bi-file-earmark-text','color'=>'#16a34a'],'kit'=>['label'=>'Kit','icon'=>'bi-box-seam','color'=>'#7c3aed'],'ficha'=>['label'=>'Ficha','icon'=>'bi-card-text','color'=>'#d97706'],'video'=>['label'=>'Video','icon'=>'bi-play-circle','color'=>'#dc2626'],'otro'=>['label'=>'Otro','icon'=>'bi-paperclip','color'=>'#64748b']];

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
.sc{background:#fff;border-radius:14px;border:1px solid #e2e8f0;padding:1rem 1.2rem;margin-bottom:1rem}
.mat-card{border:1px solid #e2e8f0;border-radius:8px;padding:.6rem .8rem;margin-bottom:.4rem;display:flex;align-items:center;gap:.75rem;cursor:pointer;transition:.15s}
.mat-card:hover,.mat-card.sel{border-color:#185FA5;background:#f0f7ff}
.mat-card.sel{border-width:2px}
.mat-icon{width:34px;height:34px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0}
.est-row{display:flex;align-items:center;gap:.5rem;padding:.4rem .5rem;border-radius:6px;cursor:pointer;transition:.15s}
.est-row:hover,.est-row.sel{background:#f0f7ff}
</style>

<div class="d-flex align-items-center gap-2 mb-3">
  <?php if ($cursoId): ?>
    <a href="curso.php?id=<?= $cursoId ?>" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i></a>
  <?php else: ?>
    <a href="index.php" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i></a>
  <?php endif; ?>
  <h4 class="fw-bold mb-0"><i class="bi bi-file-earmark-text me-2"></i>Materiales</h4>
</div>

<?php if ($error):   ?><div class="alert alert-danger  py-2 small"><?= $error   ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<!-- Selector de curso -->
<div class="sc mb-2">
  <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
    <label class="small fw-semibold mb-0">Curso:</label>
    <select name="curso_id" class="form-select form-select-sm" style="max-width:350px" onchange="this.form.submit()">
      <option value="">-- Seleccionar curso --</option>
      <?php foreach ($cursos as $cur): ?>
        <option value="<?= $cur['id'] ?>" <?= $cursoId==$cur['id']?'selected':'' ?>><?= htmlspecialchars($cur['label']) ?></option>
      <?php endforeach; ?>
    </select>
  </form>
</div>

<?php if ($cursoId): ?>
<div class="row g-3">

  <!-- Materiales del curso -->
  <div class="col-lg-5">
    <div class="sc">
      <h6 class="fw-bold mb-3"><i class="bi bi-collection me-2 text-primary"></i>Materiales del curso</h6>
      <?php foreach ($materiales as $mat):
        $ti = $TIPO_MAT[$mat['tipo']] ?? $TIPO_MAT['otro'];
      ?>
      <div class="mat-card" onclick="toggleMat(<?= $mat['id'] ?>)">
        <input type="checkbox" class="mat-chk" value="<?= $mat['id'] ?>" id="m<?= $mat['id'] ?>" onchange="event.stopPropagation()">
        <div class="mat-icon" style="background:<?= $ti['color'] ?>20;color:<?= $ti['color'] ?>">
          <i class="bi <?= $ti['icon'] ?>"></i>
        </div>
        <div class="flex-grow-1">
          <div class="fw-semibold" style="font-size:.82rem"><?= htmlspecialchars($mat['nombre']) ?></div>
          <div class="text-muted" style="font-size:.7rem"><?= $ti['label'] ?><?= $mat['para_grado']?' &middot; Grado '.$mat['para_grado']:'' ?></div>
        </div>
        <?php if ($mat['url']): ?>
          <a href="<?= htmlspecialchars($mat['url']) ?>" target="_blank"
             class="btn btn-sm btn-outline-primary py-0 px-1" onclick="event.stopPropagation()"><i class="bi bi-link-45deg"></i></a>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
      <?php if (empty($materiales)): ?>
        <div class="text-center text-muted py-3 small">Sin materiales. Agrega abajo.</div>
      <?php endif; ?>

      <!-- Nuevo material -->
      <div class="mt-3 pt-3 border-top">
        <h6 class="fw-semibold small mb-2">Agregar material</h6>
        <form method="POST">
          <input type="hidden" name="action"   value="save_mat">
          <input type="hidden" name="csrf"     value="<?= Auth::csrfToken() ?>">
          <input type="hidden" name="curso_id" value="<?= $cursoId ?>">
          <div class="row g-1">
            <div class="col-12"><input type="text" name="nombre" class="form-control form-control-sm" placeholder="Nombre *" required></div>
            <div class="col-6">
              <select name="tipo" class="form-select form-select-sm">
                <?php foreach ($TIPO_MAT as $tk => $tv): ?>
                  <option value="<?= $tk ?>"><?= $tv['label'] ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6"><input type="text" name="para_grado" class="form-control form-control-sm" placeholder="Para grado (opcional)"></div>
            <div class="col-12"><input type="url" name="url" class="form-control form-control-sm" placeholder="URL del recurso"></div>
            <div class="col-12"><button type="submit" class="btn btn-primary btn-sm w-100">Guardar material</button></div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Asignar a estudiantes -->
  <div class="col-lg-7">
    <div class="sc">
      <h6 class="fw-bold mb-3"><i class="bi bi-person-check me-2 text-success"></i>Asignar a estudiantes</h6>
      <form method="POST">
        <input type="hidden" name="action"   value="asignar">
        <input type="hidden" name="csrf"     value="<?= Auth::csrfToken() ?>">
        <div id="mats-sel" class="mb-3 p-2 rounded" style="background:#f0f7ff;min-height:40px;font-size:.78rem;color:#185FA5">
          Selecciona materiales de la izquierda...
        </div>
        <div class="mb-2 fw-semibold small">Estudiantes del curso:</div>
        <div style="max-height:300px;overflow-y:auto">
          <?php foreach ($estudiantes as $est):
            $initials = strtoupper(substr($est['nombres'],0,1).substr($est['apellidos'],0,1));
          ?>
          <div class="est-row" onclick="toggleEst(this,<?= $est['id'] ?>)">
            <div style="width:30px;height:30px;border-radius:50%;background:#185FA5;color:#fff;display:flex;align-items:center;justify-content:center;font-size:.72rem;font-weight:700;flex-shrink:0"><?= $initials ?></div>
            <span style="font-size:.82rem;flex-grow:1"><?= htmlspecialchars($est['apellidos'].', '.$est['nombres']) ?></span>
            <input type="checkbox" name="est_ids[]" value="<?= $est['id'] ?>" class="est-chk" onclick="event.stopPropagation()">
          </div>
          <?php endforeach; ?>
        </div>
        <div id="mats-hidden"></div>
        <button type="submit" class="btn btn-success w-100 mt-3 fw-bold" id="btn-asignar" disabled>
          <i class="bi bi-person-check me-1"></i>Asignar seleccionados
        </button>
      </form>
    </div>
  </div>

</div>
<?php else: ?>
<div class="sc text-center text-muted py-5">
  <i class="bi bi-file-earmark-text fs-2 d-block mb-2"></i>
  Selecciona un curso para gestionar materiales
</div>
<?php endif; ?>

<script>
var matsSelec  = {};
var estsSelec  = {};

function toggleMat(id) {
    var chk = document.getElementById('m'+id);
    chk.checked = !chk.checked;
    var card = chk.closest('.mat-card');
    if (chk.checked) { matsSelec[id]=true; card.classList.add('sel'); }
    else { delete matsSelec[id]; card.classList.remove('sel'); }
    actualizarUI();
}

function toggleEst(row, id) {
    var chk = row.querySelector('.est-chk');
    chk.checked = !chk.checked;
    if (chk.checked) { estsSelec[id]=true; row.classList.add('sel'); }
    else { delete estsSelec[id]; row.classList.remove('sel'); }
    actualizarUI();
}

function actualizarUI() {
    var nMat = Object.keys(matsSelec).length;
    var nEst = Object.keys(estsSelec).length;
    var div  = document.getElementById('mats-sel');
    div.textContent = nMat > 0 ? nMat+' material(es) seleccionado(s)' : 'Selecciona materiales de la izquierda...';

    // Inputs hidden para materiales seleccionados
    var hidDiv = document.getElementById('mats-hidden');
    hidDiv.innerHTML = '';
    Object.keys(matsSelec).forEach(function(mid) {
        var inp = document.createElement('input');
        inp.type='hidden'; inp.name='mat_ids[]'; inp.value=mid;
        hidDiv.appendChild(inp);
    });

    document.getElementById('btn-asignar').disabled = nMat===0 || nEst===0;
}
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
