<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();
Auth::requireRol('comercial','gerencia','administracion');

$db  = Database::get();
$id  = (int)($_GET['id'] ?? 0);
$conv= $id ? $db->query("SELECT * FROM convenios WHERE id=$id AND activo=1")->fetch() : null;
$pageTitle  = $conv ? 'Editar Convenio' : 'Nuevo Convenio';
$activeMenu = 'comercial';
$error = '';
$user  = Auth::user();

$cursos   = $db->query("SHOW TABLES LIKE 'escuela_cursos'")->fetchColumn() ?
    $db->query("SELECT id,nombre FROM escuela_cursos WHERE activo=1 ORDER BY nombre")->fetchAll() : [];
$kits     = $db->query("SELECT id,nombre,codigo FROM kits WHERE activo=1 ORDER BY nombre")->fetchAll();
$colegios = $db->query("SELECT id,nombre FROM colegios WHERE activo=1 ORDER BY nombre")->fetchAll();

// Guardar
if ($_SERVER['REQUEST_METHOD']==='POST' && Auth::csrfVerify($_POST['csrf']??'')) {
    try {
        // Subir PDF
        $docConvenio = $conv['doc_convenio'] ?? null;
        if (!empty($_FILES['doc_convenio']['name'])) {
            $ext = strtolower(pathinfo($_FILES['doc_convenio']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext,['pdf','PDF'])) throw new Exception('Solo se permiten archivos PDF.');
            $dest = UPLOAD_DIR.'convenios/';
            if (!is_dir($dest)) mkdir($dest,0755,true);
            $fname = 'conv_'.time().'_'.uniqid().'.'.$ext;
            if (!move_uploaded_file($_FILES['doc_convenio']['tmp_name'],$dest.$fname))
                throw new Exception('Error al subir el documento.');
            $docConvenio = $fname;
        }

        // Calcular total
        $cursoItems = $_POST['curso_nombre'] ?? [];
        $valorTotal = 0;
        foreach ($_POST['valor_total_c'] ?? [] as $v) $valorTotal += (float)str_replace(['.','$',' '],'',$v);

        $estado = $conv ? $conv['estado'] : 'borrador';
        // Si sube el doc y estaba en borrador → pendiente aprobacion
        if ($docConvenio && (!$conv || $conv['estado']==='borrador' || $conv['estado']==='rechazado')) {
            $estado = 'pendiente_aprobacion';
        }

        $data = [
            'nombre_colegio'  => trim($_POST['nombre_colegio']),
            'colegio_id'      => ($_POST['colegio_id']?:null),
            'comercial_id'    => $user['id'],
            'nombre_comercial'=> $user['nombre'],
            'fecha_convenio'  => $_POST['fecha_convenio'] ?: null,
            'vigencia_inicio' => $_POST['vigencia_inicio'] ?: null,
            'vigencia_fin'    => $_POST['vigencia_fin']    ?: null,
            'valor_total'     => $valorTotal,
            'estado'          => $estado,
            'doc_convenio'    => $docConvenio,
            'notas'           => trim($_POST['notas']??'') ?: null,
        ];

        if ($id) {
            $sets = implode(',', array_map(function($k){ return "$k=:$k"; }, array_keys($data)));
            $data['id'] = $id;
            $db->prepare("UPDATE convenios SET $sets WHERE id=:id")->execute($data);
            // Borrar cursos anteriores
            $db->prepare("DELETE FROM convenio_cursos WHERE convenio_id=?")->execute([$id]);
        } else {
            // Generar codigo
            $anio = date('Y');
            $num  = $db->query("SELECT COUNT(*)+1 FROM convenios WHERE YEAR(created_at)=$anio")->fetchColumn();
            $data['codigo'] = 'CON-'.$anio.'-'.str_pad($num,3,'0',STR_PAD_LEFT);
            $c2 = implode(',', array_keys($data));
            $v2 = ':'.implode(',:', array_keys($data));
            $db->prepare("INSERT INTO convenios ($c2) VALUES ($v2)")->execute($data);
            $id = $db->lastInsertId();
        }

        // Insertar cursos del convenio
        foreach ($cursoItems as $i => $cn) {
            if (!trim($cn)) continue;
            $db->prepare("INSERT INTO convenio_cursos
                (convenio_id,nombre_curso,curso_id,num_estudiantes,kit_id,nombre_kit,valor_kit,incluye_libro,nombre_libro,valor_libro,valor_total,notas)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")->execute([
                $id, trim($cn),
                ($_POST['curso_id'][$i]?:null),
                (int)($_POST['num_estudiantes'][$i]??0),
                ($_POST['kit_id'][$i]?:null),
                trim($_POST['nombre_kit'][$i]??'') ?: null,
                (float)str_replace(['.','$',' '],'', $_POST['valor_kit'][$i]??'0'),
                isset($_POST['incluye_libro'][$i]) ? 1 : 0,
                trim($_POST['nombre_libro'][$i]??'') ?: null,
                (float)str_replace(['.','$',' '],'', $_POST['valor_libro'][$i]??'0'),
                (float)str_replace(['.','$',' '],'', $_POST['valor_total_c'][$i]??'0'),
                trim($_POST['notas_c'][$i]??'') ?: null,
            ]);
        }

        // Historial
        $db->prepare("INSERT INTO convenio_historial (convenio_id,estado,usuario_id,comentario) VALUES (?,?,?,?)")
           ->execute([$id,$estado,$user['id'],$docConvenio?'Documento subido, pendiente aprobacion':'Convenio guardado']);

        header('Location: '.APP_URL.'/modules/comercial/ver.php?id='.$id.'&ok=1'); exit;
    } catch (Exception $e) { $error = $e->getMessage(); }
}

// Cargar cursos existentes del convenio
$cursosConv = $id ? $db->query("SELECT * FROM convenio_cursos WHERE convenio_id=$id ORDER BY id")->fetchAll() : [];

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
.sc{background:#fff;border-radius:14px;border:1px solid #e2e8f0;padding:1.2rem;margin-bottom:1rem}
.curso-row{background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;padding:.75rem;margin-bottom:.5rem;position:relative}
.curso-row .btn-del{position:absolute;top:.5rem;right:.5rem}
.upload-zona{border:2px dashed #e2e8f0;border-radius:10px;padding:1.5rem;text-align:center;cursor:pointer;transition:.15s}
.upload-zona:hover{border-color:#7c3aed;background:#faf5ff}
.upload-zona.tiene-doc{border-color:#16a34a;background:#f0fdf4}
</style>

<div class="d-flex align-items-center gap-2 mb-3">
  <a href="index.php" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i></a>
  <h4 class="fw-bold mb-0"><?= $conv?'Editar':'Nuevo' ?> Convenio</h4>
  <?php if ($conv): ?>
    <span class="badge" style="background:#7c3aed;font-size:.75rem"><?= htmlspecialchars($conv['codigo']??'') ?></span>
  <?php endif; ?>
</div>

<?php if ($error): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<form method="POST" enctype="multipart/form-data" id="frmConv">
<input type="hidden" name="csrf" value="<?= Auth::csrfToken() ?>">

<div class="row g-3">

  <!-- Izquierda -->
  <div class="col-lg-4">

    <!-- Datos generales -->
    <div class="sc">
      <h6 class="fw-bold mb-3"><i class="bi bi-building me-2 text-primary"></i>Datos del Convenio</h6>
      <div class="row g-2">
        <div class="col-12">
          <label class="form-label small fw-semibold">Colegio *</label>
          <select name="colegio_id" class="form-select form-select-sm" onchange="setColegio(this)">
            <option value="">-- Seleccionar o escribir --</option>
            <?php foreach ($colegios as $col): ?>
              <option value="<?= $col['id'] ?>"
                      data-nombre="<?= htmlspecialchars($col['nombre']) ?>"
                      <?= ($conv['colegio_id']??0)==$col['id']?'selected':'' ?>>
                <?= htmlspecialchars($col['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12">
          <label class="form-label small fw-semibold">Nombre del colegio (manual) *</label>
          <input type="text" name="nombre_colegio" class="form-control form-control-sm" required
                 id="inputNombreColegio"
                 value="<?= htmlspecialchars($conv['nombre_colegio'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label small fw-semibold">Fecha del convenio</label>
          <input type="date" name="fecha_convenio" class="form-control form-control-sm"
                 value="<?= $conv['fecha_convenio'] ?? date('Y-m-d') ?>">
        </div>
        <div class="col-6">
          <label class="form-label small fw-semibold">Vigencia desde</label>
          <input type="date" name="vigencia_inicio" class="form-control form-control-sm"
                 value="<?= $conv['vigencia_inicio'] ?? '' ?>">
        </div>
        <div class="col-6">
          <label class="form-label small fw-semibold">Vigencia hasta</label>
          <input type="date" name="vigencia_fin" class="form-control form-control-sm"
                 value="<?= $conv['vigencia_fin'] ?? '' ?>">
        </div>
        <div class="col-12">
          <label class="form-label small fw-semibold">Notas</label>
          <textarea name="notas" class="form-control form-control-sm" rows="2"
                    placeholder="Condiciones especiales..."><?= htmlspecialchars($conv['notas']??'') ?></textarea>
        </div>
        <div class="col-12 p-2 rounded" style="background:#f0f7ff;font-size:.78rem">
          <i class="bi bi-person me-1 text-primary"></i>
          Comercial: <strong><?= htmlspecialchars($user['nombre']) ?></strong>
        </div>
      </div>
    </div>

    <!-- Documento convenio -->
    <div class="sc">
      <h6 class="fw-bold mb-3"><i class="bi bi-file-earmark-pdf me-2 text-danger"></i>Documento Firmado</h6>
      <div class="upload-zona <?= !empty($conv['doc_convenio'])?'tiene-doc':'' ?>"
           id="uploadZona" onclick="document.getElementById('fileDoc').click()">
        <?php if (!empty($conv['doc_convenio'])): ?>
          <i class="bi bi-file-earmark-check" style="font-size:1.5rem;color:#16a34a"></i>
          <div class="mt-1 small fw-semibold text-success">Documento cargado</div>
          <div class="text-muted" style="font-size:.72rem"><?= htmlspecialchars($conv['doc_convenio']) ?></div>
          <div class="text-muted" style="font-size:.72rem;margin-top:.25rem">Clic para reemplazar</div>
        <?php else: ?>
          <i class="bi bi-cloud-arrow-up" style="font-size:1.5rem;color:#7c3aed"></i>
          <div class="mt-1 small fw-semibold">Subir convenio firmado (PDF)</div>
          <div class="text-muted" style="font-size:.72rem">Este documento valida la orden de produccion</div>
        <?php endif; ?>
      </div>
      <input type="file" id="fileDoc" name="doc_convenio" accept=".pdf" class="d-none"
             onchange="previewDoc(this)">
      <div id="docNombre" class="text-muted mt-1" style="font-size:.72rem;display:none"></div>
      <div class="alert alert-info py-2 mt-2" style="font-size:.75rem">
        <i class="bi bi-info-circle me-1"></i>
        Al subir el documento, el convenio pasa a <strong>Pendiente de Aprobacion</strong> por Gerencia.
      </div>
    </div>

  </div>

  <!-- Derecha: cursos -->
  <div class="col-lg-8">
    <div class="sc">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h6 class="fw-bold mb-0"><i class="bi bi-journal-text me-2 text-primary"></i>Cursos del Convenio</h6>
        <button type="button" class="btn btn-outline-primary btn-sm" onclick="addCurso()">
          <i class="bi bi-plus-lg me-1"></i>Agregar curso
        </button>
      </div>

      <div id="cursosContainer">
        <?php
        $itemsBase = !empty($cursosConv) ? $cursosConv : [null];
        foreach ($itemsBase as $i => $cc):
        ?>
        <div class="curso-row" id="cursoRow<?= $i ?>">
          <button type="button" class="btn btn-sm btn-outline-danger btn-del py-0 px-1"
                  onclick="delCurso(<?= $i ?>)" title="Eliminar"><i class="bi bi-trash"></i></button>
          <div class="row g-2">
            <div class="col-md-5">
              <label class="form-label small fw-semibold">Nombre del curso *</label>
              <input type="text" name="curso_nombre[]" class="form-control form-control-sm" required
                     placeholder="Ej: Robotica Lego Spike Grado 4"
                     value="<?= htmlspecialchars($cc['nombre_curso'] ?? '') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Curso del sistema</label>
              <select name="curso_id[]" class="form-select form-select-sm">
                <option value="">-- Vincular --</option>
                <?php foreach ($cursos as $cur): ?>
                  <option value="<?= $cur['id'] ?>" <?= ($cc['curso_id']??0)==$cur['id']?'selected':'' ?>>
                    <?= htmlspecialchars($cur['nombre']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-3">
              <label class="form-label small fw-semibold">Num. estudiantes</label>
              <input type="number" name="num_estudiantes[]" class="form-control form-control-sm"
                     min="0" placeholder="0" value="<?= $cc['num_estudiantes']??'' ?>"
                     oninput="calcTotal(this)">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Kit</label>
              <select name="kit_id[]" class="form-select form-select-sm" onchange="setKit(this)">
                <option value="">-- Seleccionar kit --</option>
                <?php foreach ($kits as $k): ?>
                  <option value="<?= $k['id'] ?>"
                          data-nombre="<?= htmlspecialchars($k['nombre']) ?>"
                          <?= ($cc['kit_id']??0)==$k['id']?'selected':'' ?>>
                    <?= htmlspecialchars($k['nombre']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Nombre kit (manual)</label>
              <input type="text" name="nombre_kit[]" class="form-control form-control-sm"
                     placeholder="Nombre del kit"
                     value="<?= htmlspecialchars($cc['nombre_kit']??'') ?>">
            </div>
            <div class="col-md-4">
              <label class="form-label small fw-semibold">Valor kit negociado $</label>
              <input type="text" name="valor_kit[]" class="form-control form-control-sm"
                     placeholder="0" value="<?= $cc['valor_kit']??0 ?>"
                     oninput="calcTotal(this)">
            </div>
            <div class="col-md-1 d-flex align-items-end pb-1">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" name="incluye_libro[<?= $i ?>]"
                       value="1" <?= ($cc['incluye_libro']??0)?'checked':'' ?>
                       onchange="toggleLibro(this,<?= $i ?>)">
                <label class="form-check-label small">Libro</label>
              </div>
            </div>
            <div class="col-md-5" id="libroNombreDiv<?= $i ?>" style="<?= ($cc['incluye_libro']??0)?'':'display:none' ?>">
              <label class="form-label small fw-semibold">Nombre del libro</label>
              <input type="text" name="nombre_libro[]" class="form-control form-control-sm"
                     placeholder="Titulo del libro"
                     value="<?= htmlspecialchars($cc['nombre_libro']??'') ?>">
            </div>
            <div class="col-md-3" id="libroValorDiv<?= $i ?>" style="<?= ($cc['incluye_libro']??0)?'':'display:none' ?>">
              <label class="form-label small fw-semibold">Valor libro $</label>
              <input type="text" name="valor_libro[]" class="form-control form-control-sm"
                     placeholder="0" value="<?= $cc['valor_libro']??0 ?>"
                     oninput="calcTotal(this)">
            </div>
            <div class="col-md-3 ms-auto">
              <label class="form-label small fw-semibold text-success">Total curso $</label>
              <input type="text" name="valor_total_c[]" class="form-control form-control-sm fw-bold text-success"
                     readonly value="<?= number_format($cc['valor_total']??0,0,',','.') ?>">
            </div>
            <div class="col-12">
              <input type="text" name="notas_c[]" class="form-control form-control-sm"
                     placeholder="Notas adicionales del curso..."
                     value="<?= htmlspecialchars($cc['notas']??'') ?>">
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Total general -->
      <div class="d-flex justify-content-end align-items-center gap-2 mt-2 pt-2 border-top">
        <span class="fw-semibold">Valor total del convenio:</span>
        <span class="fw-bold text-success fs-5" id="totalGeneral">$0</span>
      </div>
    </div>

    <div class="d-flex gap-2">
      <button type="submit" class="btn btn-primary fw-bold flex-grow-1">
        <i class="bi bi-save me-1"></i><?= $conv?'Guardar cambios':'Crear Convenio' ?>
      </button>
      <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
    </div>
  </div>

</div>
</form>

<script>
var cursoCount = <?= count($itemsBase) ?>;

function setColegio(sel) {
    var opt = sel.options[sel.selectedIndex];
    if (opt && opt.dataset.nombre) {
        document.getElementById('inputNombreColegio').value = opt.dataset.nombre;
    }
}

function setKit(sel) {
    var opt  = sel.options[sel.selectedIndex];
    var row  = sel.closest('.curso-row');
    var inpK = row.querySelector('[name="nombre_kit[]"]');
    if (opt && opt.dataset.nombre && inpK) inpK.value = opt.dataset.nombre;
}

function toggleLibro(chk, idx) {
    ['libroNombreDiv','libroValorDiv'].forEach(function(id) {
        var el = document.getElementById(id+idx);
        if (el) el.style.display = chk.checked ? '' : 'none';
    });
}

function calcTotal(el) {
    var row  = el.closest('.curso-row');
    var est  = parseInt(row.querySelector('[name="num_estudiantes[]"]')?.value) || 0;
    var kit  = parseFloat((row.querySelector('[name="valor_kit[]"]')?.value||'0').replace(/\./g,'').replace(',','.')) || 0;
    var lib  = parseFloat((row.querySelector('[name="valor_libro[]"]')?.value||'0').replace(/\./g,'').replace(',','.')) || 0;
    var tot  = est * (kit + lib);
    var inp  = row.querySelector('[name="valor_total_c[]"]');
    if (inp) inp.value = tot.toLocaleString('es-CO');
    calcTotalGeneral();
}

function calcTotalGeneral() {
    var inputs = document.querySelectorAll('[name="valor_total_c[]"]');
    var total  = 0;
    inputs.forEach(function(i){ total += parseFloat((i.value||'0').replace(/\./g,'').replace(',','.')) || 0; });
    document.getElementById('totalGeneral').textContent = '$' + total.toLocaleString('es-CO');
}

function addCurso() {
    var i   = cursoCount++;
    var tpl = document.querySelector('.curso-row').cloneNode(true);
    tpl.id  = 'cursoRow'+i;
    tpl.querySelectorAll('input').forEach(function(inp){ inp.value = ''; });
    tpl.querySelectorAll('select').forEach(function(s){ s.selectedIndex = 0; });
    tpl.querySelector('.btn-del')?.setAttribute('onclick','delCurso('+i+')');
    var chk = tpl.querySelector('input[type=checkbox]');
    if (chk) {
        chk.name = 'incluye_libro['+i+']';
        chk.checked = false;
        chk.setAttribute('onchange','toggleLibro(this,'+i+')');
    }
    ['libroNombreDiv','libroValorDiv'].forEach(function(pfx){
        var el = tpl.querySelector('[id^="'+pfx+'"]');
        if (el) { el.id=pfx+i; el.style.display='none'; }
    });
    document.getElementById('cursosContainer').appendChild(tpl);
}

function delCurso(i) {
    var el = document.getElementById('cursoRow'+i);
    if (el && document.querySelectorAll('.curso-row').length > 1) {
        el.remove(); calcTotalGeneral();
    }
}

function previewDoc(input) {
    if (!input.files || !input.files[0]) return;
    var zona = document.getElementById('uploadZona');
    zona.classList.add('tiene-doc');
    zona.innerHTML = '<i class="bi bi-file-earmark-check" style="font-size:1.5rem;color:#16a34a"></i>'+
        '<div class="mt-1 small fw-semibold text-success">'+input.files[0].name+'</div>'+
        '<div class="text-muted" style="font-size:.72rem">Listo para subir</div>';
}

calcTotalGeneral();
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
