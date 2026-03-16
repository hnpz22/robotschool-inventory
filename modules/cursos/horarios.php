<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();

$db         = Database::get();
$pageTitle  = 'Horarios';
$activeMenu = 'cursos';
$error = $success = '';

// Verificar tablas
$tablaExiste = $db->query("SHOW TABLES LIKE 'escuela_horarios'")->fetchColumn();

// Guardar horario
if ($tablaExiste && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='save_horario') {
    if (!Auth::csrfVerify($_POST['csrf']??'')) die('CSRF');
    try {
        $hid = (int)($_POST['hor_id'] ?? 0);
        $data = [
            'curso_id'    => (int)$_POST['curso_id'],
            'dia_semana'  => (int)($_POST['dia_semana'] ?? 7),
            'hora_inicio' => $_POST['hora_inicio'],
            'hora_fin'    => $_POST['hora_fin'],
            'instructor'  => trim($_POST['instructor'] ?? ''),
            'sede'        => trim($_POST['sede'] ?? ''),
            'periodo'     => trim($_POST['periodo'] ?? ''),
            'elemento_id' => ($_POST['elemento_id'] ?: null),
            'kit_id'      => ($_POST['kit_id']      ?: null),
            'cupo_max'    => (int)($_POST['cupo_max'] ?? 10),
            'activo'      => 1,
        ];
        if ($hid) {
            $sets = implode(',', array_map(function($k){ return "$k=:$k"; }, array_keys($data)));
            $data['id'] = $hid;
            $db->prepare("UPDATE escuela_horarios SET $sets WHERE id=:id")->execute($data);
            $success = 'Horario actualizado.';
        } else {
            $c2 = implode(',', array_keys($data));
            $v2 = ':'.implode(',:', array_keys($data));
            $db->prepare("INSERT INTO escuela_horarios ($c2) VALUES ($v2)")->execute($data);
            $success = 'Horario creado.';
        }
    } catch (Exception $e) { $error = $e->getMessage(); }
}

// Eliminar horario
if ($tablaExiste && isset($_GET['del']) && Auth::csrfVerify($_GET['csrf']??'')) {
    $db->prepare("UPDATE escuela_horarios SET activo=0 WHERE id=?")->execute([(int)$_GET['del']]);
    $success = 'Horario eliminado.';
}

// Cargar horario a editar
$editHor = null;
if ($tablaExiste && isset($_GET['edit'])) {
    $editHor = $db->query("SELECT * FROM escuela_horarios WHERE id=".(int)$_GET['edit'])->fetch();
}

// Datos
$horarios = $tablaExiste ? $db->query("
    SELECT h.*,
           c.nombre AS curso_nombre, c.color_primario AS curso_color, c.categoria,
           e.nombre AS elem_nombre, e.stock_actual,
           k.nombre AS kit_nombre,
           (SELECT COUNT(*) FROM matriculas m
            JOIN escuela_grupos eg ON eg.id=m.grupo_id
            WHERE eg.id=h.id AND m.estado IN ('activa','pendiente_pago')
           ) AS matriculados,
           s.nombre AS sede_nombre, s.color AS sede_color, s.ciudad AS sede_ciudad
    FROM escuela_horarios h
    JOIN escuela_cursos c ON c.id=h.curso_id
    LEFT JOIN elementos e ON e.id=h.elemento_id
    LEFT JOIN kits k      ON k.id=h.kit_id
    LEFT JOIN sedes s     ON s.id=h.sede_id
    WHERE h.activo=1 AND c.activo=1
    ORDER BY h.dia_semana, h.hora_inicio, c.nombre
")->fetchAll() : [];

$cursos    = $tablaExiste ? $db->query("SELECT id,nombre,color_primario FROM escuela_cursos WHERE activo=1 ORDER BY nombre")->fetchAll() : [];
$elementos = $db->query("SELECT id,codigo,nombre,stock_actual FROM elementos WHERE activo=1 AND stock_actual>0 ORDER BY nombre")->fetchAll();
$kits      = $db->query("SELECT id,codigo,nombre FROM kits WHERE activo=1 ORDER BY nombre")->fetchAll();
$sedeExiste= $db->query("SHOW TABLES LIKE 'sedes'")->fetchColumn();
$sedes     = $sedeExiste ? $db->query("SELECT * FROM sedes WHERE activo=1 ORDER BY ciudad,nombre")->fetchAll() : [];

// Franjas horarias ROBOTSchool sabados
$FRANJAS = [
    '08:00' => '08:00 - 10:00',
    '10:30' => '10:30 - 12:30',
    '13:00' => '13:00 - 15:00',
];
$DIAS = ['2'=>'Lunes','3'=>'Martes','4'=>'Miercoles','5'=>'Jueves','6'=>'Viernes','7'=>'Sabado','1'=>'Domingo'];
$CATS = ['robotica'=>'&#x1F916;','programacion'=>'&#x1F4BB;','videojuegos'=>'&#x1F3AE;','impresion3d'=>'&#x1F5A8;','electronica'=>'&#x26A1;','maker'=>'&#x1F527;','otro'=>'&#x1F4DA;'];

// Agrupar horarios por día y franja
$grid = [];
foreach ($horarios as $h) {
    $dv  = $h['dia_semana'];
    $hi  = substr($h['hora_inicio'], 0, 5);
    $grid[$dv][$hi][] = $h;
}

// Stats
$totalCupos     = array_sum(array_column($horarios, 'cupo_max'));
$totalMatric    = array_sum(array_column($horarios, 'matriculados'));
$totalDisponibles = $totalCupos - $totalMatric;

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
.sc{background:#fff;border-radius:14px;border:1px solid #e2e8f0;padding:1rem 1.2rem;margin-bottom:1rem}
.hor-card{border-radius:10px;overflow:hidden;border:1.5px solid #e2e8f0;transition:.15s;background:#fff;margin-bottom:.5rem}
.hor-card:hover{box-shadow:0 3px 12px rgba(0,0,0,.08)}
.hor-head{padding:.5rem .75rem;color:#fff;display:flex;align-items:center;justify-content:space-between}
.hor-body{padding:.6rem .75rem}
.cupo-bar{height:6px;border-radius:3px;background:#e2e8f0;overflow:hidden;margin-top:3px}
.cupo-fill{height:100%;border-radius:3px;transition:.3s}
.disp-disponible{color:#166534;background:#dcfce7}
.disp-casi_lleno{color:#854d0e;background:#fef9c3}
.disp-lleno{color:#991b1b;background:#fee2e2}
.franja-header{background:#1e293b;color:#fff;border-radius:10px;padding:.5rem .9rem;font-size:.82rem;font-weight:700;margin-bottom:.75rem;display:flex;align-items:center;gap:.5rem}
.stat-box{background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:.75rem;text-align:center}
.form-section{background:#f8fafc;border-radius:10px;padding:1rem;border:1px solid #e2e8f0}
</style>

<!-- Filtro sedes -->
<?php if (!empty($sedes)): ?>
<div class="d-flex gap-2 flex-wrap mb-3" id="filtroSedes">
  <button class="btn btn-sm btn-dark active" onclick="filtrarSede('todas',this)">
    <i class="bi bi-geo-alt me-1"></i>Todas las sedes
  </button>
  <?php foreach ($sedes as $s): ?>
  <button class="btn btn-sm" style="background:<?= $s['color'] ?>;color:#fff;border:none"
          onclick="filtrarSede(<?= $s['id'] ?>,this)">
    <?= $s['ciudad']==='bogota'?'&#x1F1E8;&#x1F1F4;':'&#x1F1E8;&#x1F1F4;' ?>
    <?= htmlspecialchars($s['nombre']) ?>
  </button>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Header -->
<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-clock me-2"></i>Horarios de Cursos</h4>
    <p class="text-muted small mb-0">S&aacute;bados · Tres franjas: 8-10, 10:30-12:30, 1-3pm</p>
  </div>
  <div class="d-flex gap-2">
    <a href="index.php" class="btn btn-sm btn-light"><i class="bi bi-arrow-left me-1"></i>Cursos</a>
    <?php if (Auth::isAdmin()): ?>
    <a href="sedes.php" class="btn btn-sm btn-outline-secondary">
      <i class="bi bi-geo-alt me-1"></i>Sedes
    </a>
    <?php endif; ?>
  </div>
</div>

<?php if ($error):   ?><div class="alert alert-danger  py-2 small"><?= htmlspecialchars($error)   ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<!-- Stats -->
<div class="row g-2 mb-3">
  <div class="col-6 col-md-3">
    <div class="stat-box">
      <div class="fs-4 fw-bold text-primary"><?= count($horarios) ?></div>
      <div class="text-muted small">Horarios activos</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-box">
      <div class="fs-4 fw-bold text-success"><?= $totalDisponibles ?></div>
      <div class="text-muted small">Cupos disponibles</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-box">
      <div class="fs-4 fw-bold text-warning"><?= $totalMatric ?></div>
      <div class="text-muted small">Matriculados</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-box">
      <div class="fs-4 fw-bold"><?= $totalCupos ?></div>
      <div class="text-muted small">Total cupos</div>
    </div>
  </div>
</div>

<div class="row g-3">

  <!-- Grilla de horarios -->
  <div class="col-lg-8">

    <?php if (empty($horarios)): ?>
    <div class="sc text-center py-5">
      <div style="font-size:3rem">&#x1F554;</div>
      <h5 class="fw-bold mt-2">Sin horarios configurados</h5>
      <p class="text-muted small">Agrega el primer horario con el formulario de la derecha</p>
    </div>
    <?php else: ?>

    <?php foreach ($DIAS as $dv => $dl):
      if (!isset($grid[$dv])) continue;
    ?>
    <div class="mb-3">
      <div class="d-flex align-items-center gap-2 mb-2">
        <span class="badge bg-dark rounded-pill fs-6 px-3"><?= $dl ?></span>
      </div>

      <?php foreach ($FRANJAS as $hi => $hl):
        if (!isset($grid[$dv][$hi])) continue;
      ?>
      <div class="mb-3">
        <div class="franja-header">
          <i class="bi bi-clock"></i> <?= $hl ?>
          <span class="ms-auto badge bg-secondary"><?= count($grid[$dv][$hi]) ?> curso(s)</span>
        </div>
        <div class="row g-2">
        <?php foreach ($grid[$dv][$hi] as $h):
          $cupoMax  = $h['cupo_max'] ?? ($h['stock_actual'] ?? 10);
          $matric   = $h['matriculados'];
          $libres   = max(0, $cupoMax - $matric);
          $pct      = $cupoMax > 0 ? min(100, round($matric/$cupoMax*100)) : 100;
          $disp     = $libres===0 ? 'lleno' : ($libres<=3 ? 'casi_lleno' : 'disponible');
          $fc       = ['disponible'=>'#22c55e','casi_lleno'=>'#f59e0b','lleno'=>'#ef4444'][$disp];
          $catIcon  = $CATS[$h['categoria']] ?? '&#x1F4DA;';
        ?>
        <div class="col-md-6 hor-col" data-sede="<?= $h['sede_id'] ?? 'sin_sede' ?>">
          <div class="hor-card">
            <div class="hor-head" style="background:<?= $h['curso_color'] ?>">
              <div>
                <div style="font-size:.72rem;opacity:.8">
                <?= $catIcon ?> <?= ucfirst($h['categoria']) ?>
                <?php if (!empty($h['sede_nombre'])): ?>
                  &nbsp;&middot;&nbsp;
                  <span style="font-size:.68rem;background:rgba(255,255,255,.25);padding:.1rem .35rem;border-radius:10px">
                    <?= htmlspecialchars($h['sede_nombre']) ?>
                  </span>
                <?php endif; ?>
              </div>
                <div class="fw-bold" style="font-size:.88rem"><?= htmlspecialchars($h['curso_nombre']) ?></div>
              </div>
              <span class="badge disp-<?= $disp ?>" style="font-size:.7rem"><?= $libres ?> libres</span>
            </div>
            <div class="hor-body">
              <?php if ($h['instructor']): ?>
                <div class="text-muted small mb-1"><i class="bi bi-person me-1"></i><?= htmlspecialchars($h['instructor']) ?></div>
              <?php endif; ?>
              <?php if ($h['elem_nombre']): ?>
                <div class="small mb-1" style="font-size:.75rem">
                  <i class="bi bi-cpu me-1 text-primary"></i><?= htmlspecialchars($h['elem_nombre']) ?>
                  <span class="text-muted">(<?= $h['stock_actual'] ?> uds)</span>
                </div>
              <?php endif; ?>
              <!-- Barra de cupos -->
              <div class="d-flex justify-content-between" style="font-size:.72rem;margin-top:.3rem">
                <span class="text-muted"><?= $matric ?> / <?= $cupoMax ?> cupos</span>
                <span style="color:<?= $fc ?>;font-weight:700"><?= $pct ?>%</span>
              </div>
              <div class="cupo-bar">
                <div class="cupo-fill" style="width:<?= $pct ?>%;background:<?= $fc ?>"></div>
              </div>
              <!-- Acciones -->
              <div class="d-flex gap-1 mt-2">
                <a href="ver.php?id=<?= $h['curso_id'] ?>&tab=horarios" class="btn btn-sm btn-outline-primary py-0 px-2 flex-grow-1" style="font-size:.72rem">
                  <i class="bi bi-eye me-1"></i>Ver curso
                </a>
                <a href="?edit=<?= $h['id'] ?>" class="btn btn-sm btn-outline-secondary py-0 px-1"><i class="bi bi-pencil"></i></a>
                <a href="?del=<?= $h['id'] ?>&csrf=<?= Auth::csrfToken() ?>"
                   class="btn btn-sm btn-outline-danger py-0 px-1"
                   onclick="return confirm('Eliminar este horario?')"><i class="bi bi-trash"></i></a>
              </div>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endforeach; ?>

    <!-- Horarios fuera de franjas predefinidas -->
    <?php
    $otrosHorarios = [];
    foreach ($horarios as $h) {
        $hi = substr($h['hora_inicio'],0,5);
        if (!isset($FRANJAS[$hi])) $otrosHorarios[] = $h;
    }
    if (!empty($otrosHorarios)):
    ?>
    <div class="mb-3">
      <div class="d-flex align-items-center gap-2 mb-2">
        <span class="badge bg-secondary rounded-pill">Otros horarios</span>
      </div>
      <div class="row g-2">
      <?php foreach ($otrosHorarios as $h):
        $cupoMax = $h['cupo_max'] ?? 10;
        $matric  = $h['matriculados'];
        $libres  = max(0, $cupoMax - $matric);
        $pct     = $cupoMax>0 ? min(100,round($matric/$cupoMax*100)) : 100;
        $fc      = $libres===0?'#ef4444':($libres<=3?'#f59e0b':'#22c55e');
        $disp    = $libres===0?'lleno':($libres<=3?'casi_lleno':'disponible');
      ?>
      <div class="col-md-6">
        <div class="hor-card">
          <div class="hor-head" style="background:<?= $h['curso_color'] ?>">
            <div>
              <div style="font-size:.72rem;opacity:.8"><?= substr($h['hora_inicio'],0,5) ?> - <?= substr($h['hora_fin'],0,5) ?> &middot; <?= $DIAS[$h['dia_semana']]??'?' ?></div>
              <div class="fw-bold" style="font-size:.88rem"><?= htmlspecialchars($h['curso_nombre']) ?></div>
            </div>
            <span class="badge disp-<?= $disp ?>" style="font-size:.7rem"><?= $libres ?> libres</span>
          </div>
          <div class="hor-body">
            <div class="d-flex justify-content-between" style="font-size:.72rem">
              <span class="text-muted"><?= $matric ?> / <?= $cupoMax ?></span>
              <span style="color:<?= $fc ?>;font-weight:700"><?= $pct ?>%</span>
            </div>
            <div class="cupo-bar"><div class="cupo-fill" style="width:<?= $pct ?>%;background:<?= $fc ?>"></div></div>
            <div class="d-flex gap-1 mt-2">
              <a href="?edit=<?= $h['id'] ?>" class="btn btn-sm btn-outline-secondary py-0 px-1"><i class="bi bi-pencil"></i></a>
              <a href="?del=<?= $h['id'] ?>&csrf=<?= Auth::csrfToken() ?>"
                 class="btn btn-sm btn-outline-danger py-0 px-1"
                 onclick="return confirm('Eliminar?')"><i class="bi bi-trash"></i></a>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

  </div>

  <!-- Formulario -->
  <div class="col-lg-4">
    <div class="sc" style="position:sticky;top:80px">
      <h6 class="fw-bold mb-3">
        <i class="bi bi-<?= $editHor?'pencil':'plus-circle' ?> me-2 text-primary"></i>
        <?= $editHor ? 'Editar Horario' : 'Nuevo Horario' ?>
      </h6>

      <?php if (empty($cursos)): ?>
        <div class="alert alert-warning small">Primero crea cursos para poder asignar horarios.</div>
      <?php else: ?>
      <form method="POST">
        <input type="hidden" name="action" value="save_horario">
        <input type="hidden" name="csrf"   value="<?= Auth::csrfToken() ?>">
        <?php if ($editHor): ?><input type="hidden" name="hor_id" value="<?= $editHor['id'] ?>"><?php endif; ?>

        <div class="form-section mb-2">
          <div class="row g-2">
            <div class="col-12">
              <label class="form-label small fw-semibold">Curso *</label>
              <select name="curso_id" class="form-select form-select-sm" required>
                <option value="">-- Seleccionar curso --</option>
                <?php foreach ($cursos as $c): ?>
                  <option value="<?= $c['id'] ?>" <?= ($editHor['curso_id']??0)==$c['id']?'selected':'' ?>>
                    <?= htmlspecialchars($c['nombre']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-5">
              <label class="form-label small fw-semibold">D&iacute;a</label>
              <select name="dia_semana" class="form-select form-select-sm">
                <?php foreach ($DIAS as $dv=>$dl): ?>
                  <option value="<?= $dv ?>" <?= ($editHor['dia_semana']??7)==$dv?'selected':'' ?>><?= $dl ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-7">
              <label class="form-label small fw-semibold">Franja horaria</label>
              <select name="franja" class="form-select form-select-sm" onchange="setFranja(this.value)">
                <option value="">Personalizado</option>
                <?php foreach ($FRANJAS as $hi=>$hl): ?>
                  <option value="<?= $hi ?>"><?= $hl ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label small">Inicio</label>
              <input type="time" name="hora_inicio" id="horaInicio" class="form-control form-control-sm"
                     value="<?= $editHor['hora_inicio']??'08:00' ?>">
            </div>
            <div class="col-6">
              <label class="form-label small">Fin</label>
              <input type="time" name="hora_fin" id="horaFin" class="form-control form-control-sm"
                     value="<?= $editHor['hora_fin']??'10:00' ?>">
            </div>
          </div>
        </div>

        <div class="form-section mb-2">
          <div class="row g-2">
            <div class="col-12">
              <label class="form-label small fw-semibold">Instructor</label>
              <input type="text" name="instructor" class="form-control form-control-sm"
                     placeholder="Nombre del instructor"
                     value="<?= htmlspecialchars($editHor['instructor'] ?? '') ?>">
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold">Sede</label>
              <?php if (!empty($sedes)): ?>
              <select name="sede_id" class="form-select form-select-sm">
                <option value="">-- Seleccionar sede --</option>
                <?php foreach ($sedes as $s): ?>
                  <option value="<?= $s['id'] ?>"
                    <?= ($editHor['sede_id']??0)==$s['id']?'selected':'' ?>
                    style="font-weight:600">
                    <?= $s['ciudad']==='bogota'?'Bogota':'Cali' ?> &mdash; <?= htmlspecialchars($s['nombre']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <?php else: ?>
              <input type="text" name="sede" class="form-control form-control-sm"
                     placeholder="Sede / Salon"
                     value="<?= htmlspecialchars($editHor['sede'] ?? '') ?>">
              <?php endif; ?>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Periodo</label>
              <input type="text" name="periodo" class="form-control form-control-sm"
                     placeholder="2025-1"
                     value="<?= htmlspecialchars($editHor['periodo'] ?? '') ?>">
            </div>
          </div>
        </div>

        <div class="form-section mb-2">
          <label class="form-label small fw-semibold">
            <i class="bi bi-cpu me-1 text-primary"></i>
            Recurso del inventario <span class="text-muted fw-normal">(define cupos)</span>
          </label>
          <select name="elemento_id" class="form-select form-select-sm mb-1"
                  onchange="if(this.value){document.querySelector('[name=kit_id]').value='';actualizarCupo(this)}">
            <option value="">-- Elemento (computadores, kits...) --</option>
            <?php foreach ($elementos as $e): ?>
              <option value="<?= $e['id'] ?>" data-stock="<?= $e['stock_actual'] ?>"
                <?= ($editHor['elemento_id']??0)==$e['id']?'selected':'' ?>>
                <?= htmlspecialchars($e['nombre']) ?> (<?= $e['stock_actual'] ?> uds)
              </option>
            <?php endforeach; ?>
          </select>
          <select name="kit_id" class="form-select form-select-sm mb-2"
                  onchange="if(this.value)document.querySelector('[name=elemento_id]').value=''">
            <option value="">-- O un kit especifico --</option>
            <?php foreach ($kits as $k): ?>
              <option value="<?= $k['id'] ?>" <?= ($editHor['kit_id']??0)==$k['id']?'selected':'' ?>>
                <?= htmlspecialchars($k['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>

          <label class="form-label small fw-semibold">
            <i class="bi bi-people-fill me-1" style="color:#3730a3"></i>
            Cupo m&aacute;ximo *
          </label>
          <div class="input-group input-group-sm">
            <span class="input-group-text" style="background:#e0e7ff;color:#3730a3"><i class="bi bi-people-fill"></i></span>
            <input type="number" name="cupo_max" id="inputCupoMax"
                   class="form-control fw-bold" min="1" max="100"
                   value="<?= $editHor['cupo_max'] ?? 10 ?>" required>
            <span class="input-group-text" id="cupoHint" style="font-size:.75rem;color:#64748b">estudiantes</span>
          </div>
          <div class="form-text">Al seleccionar un elemento, se completa automaticamente con el stock</div>
        </div>

        <div class="d-flex gap-2">
          <button type="submit" class="btn btn-primary btn-sm fw-bold flex-grow-1">
            <i class="bi bi-save me-1"></i><?= $editHor ? 'Guardar cambios' : 'Crear Horario' ?>
          </button>
          <?php if ($editHor): ?>
            <a href="horarios.php" class="btn btn-outline-secondary btn-sm">Cancelar</a>
          <?php endif; ?>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>

</div>

<script>
var FRANJAS = {
    '08:00': {inicio:'08:00', fin:'10:00'},
    '10:30': {inicio:'10:30', fin:'12:30'},
    '13:00': {inicio:'13:00', fin:'15:00'},
};

function setFranja(val) {
    if (!val || !FRANJAS[val]) return;
    document.getElementById('horaInicio').value = FRANJAS[val].inicio;
    document.getElementById('horaFin').value    = FRANJAS[val].fin;
}

function filtrarSede(sedeId, btn) {
    // Desactivar todos los botones
    document.querySelectorAll('#filtroSedes button').forEach(function(b) {
        b.classList.remove('active');
        b.style.opacity = '0.65';
    });
    btn.classList.add('active');
    btn.style.opacity = '1';

    // Mostrar/ocultar columnas
    document.querySelectorAll('.hor-col').forEach(function(col) {
        if (sedeId === 'todas') {
            col.style.display = '';
        } else {
            col.style.display = col.dataset.sede == sedeId ? '' : 'none';
        }
    });

    // Ocultar secciones vacías
    document.querySelectorAll('.row.g-2').forEach(function(row) {
        var visibles = Array.from(row.querySelectorAll('.hor-col')).some(function(c){
            return c.style.display !== 'none';
        });
        row.closest('.mb-3') && (row.closest('.mb-3').style.display = visibles || sedeId==='todas' ? '' : 'none');
    });
}

function actualizarCupo(select) {
    var opt   = select.options[select.selectedIndex];
    var stock = opt ? parseInt(opt.getAttribute('data-stock')) : 0;
    if (stock > 0) {
        document.getElementById('inputCupoMax').value    = stock;
        document.getElementById('cupoHint').textContent  = 'del inventario';
        document.getElementById('cupoHint').style.color  = '#16a34a';
        document.getElementById('cupoHint').style.fontWeight = '700';
    }
}
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
