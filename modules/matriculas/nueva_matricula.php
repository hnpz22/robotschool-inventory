<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();

$db         = Database::get();
$pageTitle  = 'Nueva Matricula';
$activeMenu = 'matriculas';
$error = $success = '';

$grupoId  = (int)($_GET['grupo_id']  ?? 0);
$estId    = (int)($_GET['est_id']    ?? 0);
$cursoId  = (int)($_GET['curso_id'] ?? 0);

// Guardar matricula
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='matricular') {
    if (!Auth::csrfVerify($_POST['csrf']??'')) die('CSRF');
    try {
        $db->beginTransaction();

        $gid = (int)$_POST['grupo_id'];
        $eId = (int)($_POST['estudiante_id'] ?? 0);

        // Crear nuevo estudiante si no existe
        if (!$eId) {
            $nombres   = trim($_POST['nombres']   ?? '');
            $apellidos = trim($_POST['apellidos'] ?? '');
            if (!$nombres || !$apellidos) throw new Exception('Nombre y apellido son requeridos.');
            $db->prepare("INSERT INTO estudiantes (nombres,apellidos,documento,fecha_nac,acudiente,telefono,email,activo) VALUES (?,?,?,?,?,?,?,1)")
               ->execute([$nombres,$apellidos,
                 trim($_POST['documento']??'')?:null,
                 $_POST['fecha_nac']?:null,
                 trim($_POST['acudiente']??'')?:null,
                 trim($_POST['telefono']??'')?:null,
                 trim($_POST['email']??'')?:null,
               ]);
            $eId = $db->lastInsertId();
        }

        // Verificar cupos — SIN usar la vista
        $grupo = $db->query("SELECT g.*, p.nombre AS prog_nombre FROM escuela_grupos g JOIN escuela_programas p ON p.id=g.programa_id WHERE g.id=$gid")->fetch();
        if (!$grupo) throw new Exception('Grupo no encontrado.');

        $elem = $grupo['elemento_id'] ? $db->query("SELECT stock_actual FROM elementos WHERE id=".(int)$grupo['elemento_id'])->fetchColumn() : null;
        $cupoMax = $elem !== null && $elem !== false ? (int)$elem : (int)($grupo['cupo_max'] ?? 15);

        $matriculados = (int)$db->query("SELECT COUNT(*) FROM matriculas WHERE grupo_id=$gid AND estado IN ('activa','pendiente_pago')")->fetchColumn();
        if ($matriculados >= $cupoMax) throw new Exception('No hay cupos disponibles en este grupo.');

        // Verificar duplicado
        $yaExiste = $db->query("SELECT id FROM matriculas WHERE estudiante_id=$eId AND grupo_id=$gid AND estado NOT IN ('retirada','completada')")->fetchColumn();
        if ($yaExiste) throw new Exception('Este estudiante ya esta matriculado en este grupo.');

        // Crear matricula
        $estado = $_POST['estado'] ?? 'pendiente_pago';
        $db->prepare("INSERT INTO matriculas (estudiante_id,grupo_id,estado,fecha_matricula,descuento_pct,notas,created_by) VALUES (?,?,?,CURDATE(),?,?,?)")
           ->execute([$eId,$gid,$estado,(int)($_POST['descuento']??0),trim($_POST['notas']??'')?:null,Auth::user()['id']]);
        $matId = $db->lastInsertId();

        // Registrar pago inicial
        if (!empty($_POST['pago_matricula']) && (float)($_POST['valor_pago']??0) > 0) {
            $sabado = date('w')==6 ? date('Y-m-d') : date('Y-m-d', strtotime('last saturday'));
            $db->prepare("INSERT INTO pagos (matricula_id,tipo,concepto,valor,valor_pagado,medio_pago,referencia,fecha_pago,sabado_ref,estado,registrado_por) VALUES (?,?,?,?,?,?,?,CURDATE(),?,?,?)")
               ->execute([$matId,'matricula','Pago de matricula',(float)$_POST['valor_pago'],(float)$_POST['valor_pago'],$_POST['medio_pago']??'efectivo',trim($_POST['referencia']??'')?:null,$sabado,'pagado',Auth::user()['id']]);
        }

        $db->commit();
        header('Location: '.APP_URL.'/modules/matriculas/estudiantes.php?ok=matriculado'); exit;

    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error = $e->getMessage();
    }
}

// Cargar cursos disponibles
$cursosDisp = [];
$tabCursos = $db->query("SHOW TABLES LIKE 'escuela_cursos'")->fetchColumn();
if ($tabCursos) {
    $cursosDisp = $db->query("SELECT id,nombre,categoria,color_primario FROM escuela_cursos WHERE activo=1 ORDER BY nombre")->fetchAll();
}

// Cargar grupos SIN usar vista — query directo
$grupos = $db->query("
    SELECT g.id, g.nombre, g.dia_semana, g.hora_inicio, g.hora_fin,
           g.docente, g.cupo_max, g.elemento_id, g.kit_id,
           p.nombre AS prog_nombre, p.nivel AS prog_nivel,
           e.nombre AS elem_nombre, e.stock_actual,
           (SELECT COUNT(*) FROM matriculas m
            WHERE m.grupo_id=g.id AND m.estado IN ('activa','pendiente_pago')) AS matriculados
    FROM escuela_grupos g
    JOIN escuela_programas p ON p.id=g.programa_id
    LEFT JOIN elementos e ON e.id=g.elemento_id
    WHERE g.activo=1
    ORDER BY g.dia_semana, g.hora_inicio, g.nombre
")->fetchAll();

// Si hay curso seleccionado, filtrar grupos por ese curso
// Buscar grupos que tengan el programa del curso seleccionado
if ($cursoId) {
    // Los grupos están ligados a programas, no directamente a cursos de escuela_cursos
    // Mostrar todos los grupos y filtrar por nombre del curso si hay match
    // O simplemente mostrar todos los grupos disponibles del horario
}
$cursoSel = $cursoId ? current(array_filter($cursosDisp, function($c) use ($cursoId){ return $c['id']==$cursoId; })) : null;

// Calcular cupos libres para cada grupo
foreach ($grupos as &$g) {
    $cupoMax = ($g['elemento_id'] && $g['stock_actual'] !== null) ? (int)$g['stock_actual'] : (int)($g['cupo_max'] ?? 15);
    $g['cupo_max_real'] = $cupoMax;
    $g['cupos_libres']  = max(0, $cupoMax - (int)$g['matriculados']);
    $g['disponibilidad']= $g['cupos_libres']===0 ? 'lleno' : ($g['cupos_libres']<=2 ? 'casi_lleno' : 'disponible');
}
unset($g);

$gruposDisp = array_filter($grupos, function($g){ return $g['disponibilidad'] !== 'lleno'; });

// Grupo seleccionado
$grupoSel = $grupoId ? current(array_filter($grupos, function($g) use ($grupoId){ return $g['id']==$grupoId; })) : null;

// Estudiante preseleccionado
$estSel = $estId ? $db->query("SELECT * FROM estudiantes WHERE id=$estId AND activo=1")->fetch() : null;

// Lista de estudiantes para selector
$estudiantes = $db->query("SELECT id, CONCAT(apellidos,' ',nombres) AS nombre_completo, documento FROM estudiantes WHERE activo=1 ORDER BY apellidos,nombres")->fetchAll();

$DIAS = ['1'=>'Dom','2'=>'Lun','3'=>'Mar','4'=>'Mie','5'=>'Jue','6'=>'Vie','7'=>'Sab'];

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
.sc{background:#fff;border-radius:14px;border:1px solid #e2e8f0;padding:1rem 1.2rem;margin-bottom:1rem}
.grupo-sel-card{border:2px solid #185FA5;border-radius:10px;padding:.75rem;background:#eff6ff}
.cupo-bar{height:7px;border-radius:4px;background:#e2e8f0;overflow:hidden;margin-top:4px}
.cupo-fill{height:100%;border-radius:4px}
.disp-disponible{background:#dcfce7;color:#166534}
.disp-casi_lleno{background:#fef9c3;color:#854d0e}
.disp-lleno{background:#fee2e2;color:#991b1b;text-decoration:line-through}
.grupo-btn{border:1.5px solid #e2e8f0;border-radius:8px;padding:.5rem .75rem;cursor:pointer;transition:.15s;background:#fff;margin-bottom:.4rem;display:flex;align-items:center;justify-content:space-between}
.grupo-btn:hover{border-color:#185FA5;background:#eff6ff}
.grupo-btn.sel{border-color:#185FA5;background:#eff6ff;border-width:2px}
.grupo-btn.lleno{opacity:.5;cursor:not-allowed}
</style>

<div class="d-flex align-items-center gap-2 mb-3">
  <a href="index.php" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i></a>
  <h4 class="fw-bold mb-0">Nueva Matr&iacute;cula</h4>
</div>

<?php if ($error): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<form method="POST" id="frmMat">
<input type="hidden" name="action"   value="matricular">
<input type="hidden" name="csrf"     value="<?= Auth::csrfToken() ?>">
<input type="hidden" name="grupo_id" id="hidGrupoId" value="<?= $grupoId ?>">

<div class="row g-3">

  <!-- PASO 0: Seleccionar curso -->
  <?php if (!empty($cursosDisp) && !$grupoId): ?>
  <div class="col-12">
    <div class="sc">
      <h6 class="fw-bold mb-2"><span class="badge bg-dark me-2">1</span>Selecciona el curso</h6>
      <div class="d-flex gap-2 flex-wrap">
        <a href="?<?= $estId?"est_id=$estId&":'' ?>" class="btn btn-sm <?= !$cursoId?'btn-dark':'btn-outline-secondary' ?>">
          Todos los horarios
        </a>
        <?php foreach ($cursosDisp as $cur): ?>
        <a href="?curso_id=<?= $cur['id'] ?><?= $estId?"&est_id=$estId":'' ?>"
           class="btn btn-sm <?= $cursoId==$cur['id']?'btn-primary':'btn-outline-secondary' ?>"
           style="<?= $cursoId==$cur['id']?'background:'.$cur['color_primario'].';border-color:'.$cur['color_primario'].';color:#fff':'' ?>">
          <?= htmlspecialchars($cur['nombre']) ?>
        </a>
        <?php endforeach; ?>
      </div>
      <?php if ($cursoSel): ?>
        <div class="mt-2 p-2 rounded" style="background:<?= $cursoSel['color_primario'] ?>15;border:1px solid <?= $cursoSel['color_primario'] ?>40;font-size:.82rem">
          Mostrando horarios de: <strong><?= htmlspecialchars($cursoSel['nombre']) ?></strong>
          &nbsp;&middot;&nbsp;
          <a href="?<?= $estId?"est_id=$estId":'' ?>" class="text-muted small">ver todos</a>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- PASO 1: Seleccionar grupo -->
  <div class="col-lg-5">
    <div class="sc">
      <h6 class="fw-bold mb-3"><span class="badge bg-primary me-2">2</span>Seleccionar horario</h6>

      <?php if ($grupoSel): ?>
      <!-- Grupo ya seleccionado -->
      <div class="grupo-sel-card mb-2">
        <div class="fw-bold"><?= htmlspecialchars($grupoSel['nombre']) ?></div>
        <div class="text-muted small"><?= htmlspecialchars($grupoSel['prog_nombre']) ?></div>
        <div class="d-flex gap-2 mt-1" style="font-size:.78rem">
          <span><i class="bi bi-calendar me-1"></i><?= $DIAS[$grupoSel['dia_semana']]??'Sab' ?></span>
          <span><i class="bi bi-clock me-1"></i><?= substr($grupoSel['hora_inicio'],0,5) ?>-<?= substr($grupoSel['hora_fin'],0,5) ?></span>
          <?php if ($grupoSel['docente']): ?><span><i class="bi bi-person me-1"></i><?= htmlspecialchars($grupoSel['docente']) ?></span><?php endif; ?>
        </div>
        <?php if ($grupoSel['elem_nombre']): ?>
        <div class="mt-1" style="font-size:.75rem">
          <i class="bi bi-cpu me-1 text-primary"></i><?= htmlspecialchars($grupoSel['elem_nombre']) ?>
        </div>
        <?php endif; ?>
        <div class="mt-2">
          <div class="d-flex justify-content-between" style="font-size:.75rem">
            <span class="text-muted"><?= $grupoSel['matriculados'] ?> / <?= $grupoSel['cupo_max_real'] ?> cupos</span>
            <span style="color:<?= ['disponible'=>'#16a34a','casi_lleno'=>'#d97706','lleno'=>'#dc2626'][$grupoSel['disponibilidad']] ?>;font-weight:700">
              <?= $grupoSel['cupos_libres'] ?> libres
            </span>
          </div>
          <?php $pct = $grupoSel['cupo_max_real']>0 ? min(100,round($grupoSel['matriculados']/$grupoSel['cupo_max_real']*100)) : 100; ?>
          <div class="cupo-bar">
            <div class="cupo-fill" style="width:<?= $pct ?>%;background:<?= ['disponible'=>'#22c55e','casi_lleno'=>'#f59e0b','lleno'=>'#ef4444'][$grupoSel['disponibilidad']] ?>"></div>
          </div>
        </div>
        <button type="button" class="btn btn-outline-secondary btn-sm mt-2 w-100"
                onclick="document.getElementById('hidGrupoId').value='';location.href='nueva_matricula.php<?= $estId?"?est_id=$estId":'' ?>'">
          Cambiar grupo
        </button>
      </div>
      <?php else: ?>
      <!-- Selector de grupo -->
      <div style="max-height:320px;overflow-y:auto">
        <?php
        // Filtrar por nombre del curso si está seleccionado
        $gruposFiltrados = $grupos;
        if ($cursoId && $cursoSel) {
            $gruposFiltrados = array_filter($grupos, function($g) use ($cursoSel) {
                return stripos($g['nombre'], $cursoSel['nombre']) !== false
                    || stripos($g['prog_nombre'], $cursoSel['nombre']) !== false;
            });
            if (empty($gruposFiltrados)) $gruposFiltrados = $grupos; // fallback a todos
        }
        ?>
        <?php if (empty($gruposFiltrados)): ?>
          <div class="text-center text-muted py-3 small">No hay grupos activos. <a href="grupos.php">Crear grupo</a></div>
        <?php else: ?>
        <?php foreach ($gruposFiltrados as $g):
          $fc = ['disponible'=>'#22c55e','casi_lleno'=>'#f59e0b','lleno'=>'#ef4444'][$g['disponibilidad']];
          $pct2 = $g['cupo_max_real']>0 ? min(100,round($g['matriculados']/$g['cupo_max_real']*100)) : 100;
        ?>
        <div class="grupo-btn <?= $g['disponibilidad']==='lleno'?'lleno':'' ?>"
             onclick="<?= $g['disponibilidad']!=='lleno'?"selGrupo({$g['id']})":"alert('Sin cupos disponibles')" ?>">
          <div>
            <div class="fw-semibold" style="font-size:.83rem"><?= htmlspecialchars($g['nombre']) ?></div>
            <div class="text-muted" style="font-size:.72rem">
              <?= htmlspecialchars($g['prog_nombre']) ?> &middot;
              <?= $DIAS[$g['dia_semana']]??'?' ?> <?= substr($g['hora_inicio'],0,5) ?>
            </div>
            <div class="cupo-bar mt-1" style="max-width:150px">
              <div class="cupo-fill" style="width:<?= $pct2 ?>%;background:<?= $fc ?>"></div>
            </div>
          </div>
          <span class="badge disp-<?= $g['disponibilidad'] ?>" style="font-size:.68rem;white-space:nowrap">
            <?= $g['cupos_libres'] ?> cupos
          </span>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>

    <!-- Estado y condiciones -->
    <div class="sc">
      <h6 class="fw-bold mb-2">Condiciones de matr&iacute;cula</h6>
      <div class="row g-2">
        <div class="col-8">
          <label class="form-label small">Estado inicial</label>
          <select name="estado" class="form-select form-select-sm">
            <option value="pendiente_pago">Pendiente de pago</option>
            <option value="activa">Activa (pago confirmado)</option>
          </select>
        </div>
        <div class="col-4">
          <label class="form-label small">Descuento %</label>
          <input type="number" name="descuento" class="form-control form-control-sm" min="0" max="100" value="0">
        </div>
        <div class="col-12">
          <label class="form-label small">Notas</label>
          <textarea name="notas" class="form-control form-control-sm" rows="2" placeholder="Observaciones..."></textarea>
        </div>
      </div>
    </div>

    <!-- Pago inicial -->
    <div class="sc">
      <div class="form-check mb-2">
        <input class="form-check-input" type="checkbox" name="pago_matricula" id="chkPago" value="1"
               onchange="document.getElementById('blkPago').style.display=this.checked?'':'none'">
        <label class="form-check-label small fw-semibold" for="chkPago">
          <i class="bi bi-cash me-1 text-success"></i>Registrar pago ahora
        </label>
      </div>
      <div id="blkPago" style="display:none">
        <div class="row g-2">
          <div class="col-6">
            <input type="number" name="valor_pago" class="form-control form-control-sm" placeholder="Valor $" step="1000">
          </div>
          <div class="col-6">
            <select name="medio_pago" class="form-select form-select-sm">
              <option value="efectivo">Efectivo</option>
              <option value="nequi">Nequi</option>
              <option value="daviplata">Daviplata</option>
              <option value="transferencia">Transferencia</option>
              <option value="tarjeta">Tarjeta</option>
            </select>
          </div>
          <div class="col-12">
            <input type="text" name="referencia" class="form-control form-control-sm" placeholder="# referencia (opcional)">
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- PASO 2: Estudiante -->
  <div class="col-lg-7">
    <div class="sc">
      <h6 class="fw-bold mb-3"><span class="badge bg-primary me-2">3</span>Estudiante</h6>

      <?php if ($estSel): ?>
      <!-- Estudiante preseleccionado -->
      <input type="hidden" name="estudiante_id" value="<?= $estSel['id'] ?>">
      <div class="d-flex align-items-center gap-3 p-3 rounded mb-3" style="background:#f0fdf4;border:2px solid #bbf7d0">
        <div style="width:44px;height:44px;border-radius:50%;background:<?= $estSel['avatar_color']??'#185FA5' ?>;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.9rem;flex-shrink:0">
          <?= strtoupper(substr($estSel['nombres'],0,1).substr($estSel['apellidos'],0,1)) ?>
        </div>
        <div>
          <div class="fw-bold"><?= htmlspecialchars($estSel['apellidos'].', '.$estSel['nombres']) ?></div>
          <div class="text-muted small"><?= htmlspecialchars(($estSel['tipo_doc']??'TI').' '.($estSel['documento']??'')) ?></div>
          <?php if ($estSel['acudiente']): ?>
            <div class="text-muted small"><i class="bi bi-person me-1"></i><?= htmlspecialchars($estSel['acudiente']) ?> &middot; <?= htmlspecialchars($estSel['telefono']??'') ?></div>
          <?php endif; ?>
        </div>
        <a href="nueva_matricula.php<?= $grupoId?"?grupo_id=$grupoId":'' ?>" class="btn btn-outline-secondary btn-sm ms-auto">Cambiar</a>
      </div>
      <?php else: ?>
      <!-- Buscar estudiante existente -->
      <div class="mb-3">
        <label class="form-label small fw-semibold">Buscar estudiante existente</label>
        <select name="estudiante_id" class="form-select" id="selEst" onchange="toggleNuevo(this.value)">
          <option value="">-- Nuevo estudiante --</option>
          <?php foreach ($estudiantes as $e): ?>
            <option value="<?= $e['id'] ?>"><?= htmlspecialchars($e['nombre_completo']) ?> <?= $e['documento']?'('.$e['documento'].')':'' ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Formulario nuevo estudiante -->
      <div id="blkNuevo">
        <div style="background:#fef9c3;border-radius:6px;padding:.4rem .75rem;font-size:.78rem;margin-bottom:.75rem">
          <i class="bi bi-info-circle me-1 text-warning"></i>
          Si el estudiante ya existe, sel&eacute;ccialo arriba. Si es nuevo, llena los datos:
        </div>
        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Nombres *</label>
            <input type="text" name="nombres" class="form-control form-control-sm" placeholder="Nombres del estudiante">
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Apellidos *</label>
            <input type="text" name="apellidos" class="form-control form-control-sm" placeholder="Apellidos">
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-semibold">Documento</label>
            <input type="text" name="documento" class="form-control form-control-sm" placeholder="TI / CC">
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-semibold">Fecha de nac.</label>
            <input type="date" name="fecha_nac" class="form-control form-control-sm">
          </div>
          <div class="col-md-4">
            <label class="form-label small fw-semibold">EPS</label>
            <input type="text" name="eps_quick" class="form-control form-control-sm" placeholder="EPS">
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Acudiente *</label>
            <input type="text" name="acudiente" class="form-control form-control-sm" placeholder="Nombre del acudiente">
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Tel&eacute;fono *</label>
            <input type="tel" name="telefono" class="form-control form-control-sm" placeholder="Celular">
          </div>
          <div class="col-12">
            <label class="form-label small fw-semibold">Email</label>
            <input type="email" name="email" class="form-control form-control-sm" placeholder="Email del acudiente">
          </div>
          <div class="col-12">
            <div class="alert alert-info py-2 small">
              <i class="bi bi-info-circle me-1"></i>
              Para completar todos los datos del estudiante (EPS, RH, autorizaciones, etc.) usa
              <a href="estudiante_form.php" target="_blank" class="fw-bold">Nuevo Estudiante completo</a>
              y luego regresa a matricular.
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Boton guardar -->
    <button type="submit" class="btn btn-success w-100 fw-bold py-3" style="font-size:1rem"
            <?= !$grupoId?'id="btnGuardar" disabled':'' ?>>
      <i class="bi bi-check-lg me-2"></i>Confirmar Matr&iacute;cula
    </button>
    <?php if (!$grupoId): ?>
    <div class="text-center text-muted small mt-2">Selecciona un grupo para continuar</div>
    <?php endif; ?>
  </div>

</div>
</form>

<script>
function selGrupo(id) {
    document.getElementById('hidGrupoId').value = id;
    // Recargar con el grupo seleccionado
    var url = 'nueva_matricula.php?grupo_id=' + id;
    <?php if ($estId): ?>url += '&est_id=<?= $estId ?>';<?php endif; ?>
    <?php if ($cursoId): ?>url += '&curso_id=<?= $cursoId ?>';<?php endif; ?>
    window.location.href = url;
}

function toggleNuevo(val) {
    document.getElementById('blkNuevo').style.display = val ? 'none' : '';
}
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
