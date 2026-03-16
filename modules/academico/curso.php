<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();

$db = Database::get();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: '.APP_URL.'/modules/academico/'); exit; }

$curso = $db->query("SELECT cur.*, ac.nombre AS colegio_nombre FROM acad_cursos cur JOIN acad_colegios ac ON ac.id=cur.colegio_id WHERE cur.id=$id")->fetch();
if (!$curso) { header('Location: '.APP_URL.'/modules/academico/'); exit; }

$pageTitle  = $curso['nombre'];
$activeMenu = 'academico';
$error = $success = '';

// Guardar estudiante
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='save_est') {
    if (!Auth::csrfVerify($_POST['csrf']??'')) die('CSRF');
    try {
        $eid = (int)($_POST['est_id'] ?? 0);
        $data = [
            'curso_id'  => $id,
            'nombres'   => trim($_POST['nombres']),
            'apellidos' => trim($_POST['apellidos']),
            'documento' => trim($_POST['documento'] ?? '') ?: null,
            'activo'    => 1,
        ];
        if ($eid) {
            $sets = implode(',', array_map(function($k){ return "$k=:$k"; }, array_keys($data)));
            $data['id'] = $eid;
            $db->prepare("UPDATE acad_estudiantes SET $sets WHERE id=:id")->execute($data);
        } else {
            $cols2 = implode(',', array_keys($data));
            $vals2 = ':'.implode(',:', array_keys($data));
            $db->prepare("INSERT INTO acad_estudiantes ($cols2) VALUES ($vals2)")->execute($data);
            // Inicializar XP
            $newId = $db->lastInsertId();
            $db->prepare("INSERT IGNORE INTO acad_xp (estudiante_id,curso_id,xp_total,nivel_xp) VALUES (?,?,0,1)")->execute([$newId,$id]);
        }
        $success = 'Estudiante guardado.';
    } catch (Exception $e) { $error = $e->getMessage(); }
}

// Guardar unidad
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='save_unidad') {
    if (!Auth::csrfVerify($_POST['csrf']??'')) die('CSRF');
    try {
        $uid = (int)($_POST['unidad_id'] ?? 0);
        $data = [
            'curso_id'    => $id,
            'titulo'      => trim($_POST['titulo']),
            'descripcion' => trim($_POST['descripcion'] ?? ''),
            'orden'       => (int)($_POST['orden'] ?? 1),
            'color'       => $_POST['color'] ?? $curso['color'],
            'icono'       => $_POST['icono'] ?? 'bi-book',
            'activo'      => 1,
        ];
        if ($uid) {
            $sets = implode(',', array_map(function($k){ return "$k=:$k"; }, array_keys($data)));
            $data['id'] = $uid;
            $db->prepare("UPDATE acad_unidades SET $sets WHERE id=:id")->execute($data);
        } else {
            $cols2 = implode(',', array_keys($data));
            $vals2 = ':'.implode(',:', array_keys($data));
            $db->prepare("INSERT INTO acad_unidades ($cols2) VALUES ($vals2)")->execute($data);
        }
        $success = 'Unidad guardada.';
    } catch (Exception $e) { $error = $e->getMessage(); }
}

$estudiantes = $db->query("
    SELECT est.*,
      COALESCE(xp.xp_total,0) AS xp_total,
      COALESCE(xp.nivel_xp,1) AS nivel_xp,
      (SELECT COUNT(*) FROM acad_progreso pr WHERE pr.estudiante_id=est.id AND pr.estado='completado') AS completadas
    FROM acad_estudiantes est
    LEFT JOIN acad_xp xp ON xp.estudiante_id=est.id AND xp.curso_id=$id
    WHERE est.curso_id=$id AND est.activo=1
    ORDER BY xp.xp_total DESC, est.apellidos, est.nombres
")->fetchAll();

$unidades = $db->query("
    SELECT uni.*,
      (SELECT COUNT(*) FROM acad_actividades act WHERE act.unidad_id=uni.id AND act.activo=1) AS num_act,
      (SELECT SUM(puntos) FROM acad_actividades act WHERE act.unidad_id=uni.id AND act.activo=1) AS xp_posible
    FROM acad_unidades uni
    WHERE uni.curso_id=$id AND uni.activo=1
    ORDER BY uni.orden
")->fetchAll();

$totalXP = array_sum(array_column($unidades, 'xp_posible'));

// Niveles XP
$niveles = [
    1 => ['nombre'=>'Aprendiz',   'min'=>0,    'max'=>99,   'color'=>'#64748b', 'icon'=>'&#x1F535;'],
    2 => ['nombre'=>'Constructor','min'=>100,  'max'=>299,  'color'=>'#16a34a', 'icon'=>'&#x1F7E2;'],
    3 => ['nombre'=>'Ingeniero',  'min'=>300,  'max'=>599,  'color'=>'#2563eb', 'icon'=>'&#x1F539;'],
    4 => ['nombre'=>'Maestro',    'min'=>600,  'max'=>99999,'color'=>'#7c3aed', 'icon'=>'&#x1F7E3;'],
];

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
.sc{background:#fff;border-radius:14px;border:1px solid #e2e8f0;padding:1rem 1.2rem;margin-bottom:1rem}
.xp-bar{height:8px;border-radius:4px;background:#e2e8f0;overflow:hidden}
.xp-fill{height:100%;border-radius:4px;transition:.5s}
.unidad-card{border-left:4px solid var(--uc);background:#fff;border-radius:0 10px 10px 0;border:1px solid #e2e8f0;border-left:4px solid var(--uc);padding:.75rem 1rem;margin-bottom:.5rem;cursor:pointer;transition:.15s}
.unidad-card:hover{box-shadow:0 2px 8px rgba(0,0,0,.06);transform:translateX(2px)}
.est-card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:.75rem;margin-bottom:.5rem;transition:.15s}
.est-card:hover{border-color:#185FA5}
.avatar{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.85rem;font-weight:700;color:#fff;flex-shrink:0}
.nivel-tag{font-size:.65rem;padding:.15rem .45rem;border-radius:20px;font-weight:700;color:#fff}
</style>

<div class="d-flex align-items-center gap-2 mb-3">
  <a href="colegio.php?id=<?= $curso['colegio_id'] ?>" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i></a>
  <div class="flex-grow-1">
    <h4 class="fw-bold mb-0" style="color:<?= $curso['color'] ?>"><?= htmlspecialchars($curso['nombre']) ?></h4>
    <span class="text-muted small"><?= htmlspecialchars($curso['colegio_nombre']) ?> &middot; <?= ucfirst($curso['nivel'] ?? '') ?> &middot; <?= $curso['anio'] ?></span>
  </div>
  <a href="materiales.php?curso_id=<?= $id ?>" class="btn btn-outline-secondary btn-sm">
    <i class="bi bi-file-earmark-text me-1"></i>Materiales
  </a>
</div>

<?php if ($error):   ?><div class="alert alert-danger  py-2 small"><?= $error   ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="row g-3">

  <!-- Unidades -->
  <div class="col-lg-4">
    <div class="sc">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h6 class="fw-bold mb-0"><i class="bi bi-book me-2" style="color:<?= $curso['color'] ?>"></i>Unidades</h6>
        <span class="badge" style="background:<?= $curso['color'] ?>;font-size:.7rem"><?= count($unidades) ?></span>
      </div>

      <?php foreach ($unidades as $u): ?>
      <div class="unidad-card" style="--uc:<?= $u['color'] ?? $curso['color'] ?>"
           onclick="window.location='unidad.php?id=<?= $u['id'] ?>'">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="fw-bold" style="font-size:.85rem"><?= htmlspecialchars($u['titulo']) ?></div>
            <div class="text-muted" style="font-size:.72rem">
              <?= $u['num_act'] ?> actividades
              &middot; <?= $u['xp_posible'] ?? 0 ?> XP
            </div>
          </div>
          <i class="bi bi-chevron-right text-muted"></i>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($unidades)): ?>
        <div class="text-center text-muted py-3 small"><i class="bi bi-book fs-2 d-block mb-1"></i>Sin unidades</div>
      <?php endif; ?>

      <!-- Form nueva unidad -->
      <div class="mt-3 pt-3 border-top">
        <form method="POST">
          <input type="hidden" name="action" value="save_unidad">
          <input type="hidden" name="csrf"   value="<?= Auth::csrfToken() ?>">
          <div class="row g-1">
            <div class="col-12">
              <input type="text" name="titulo" class="form-control form-control-sm" placeholder="Titulo de la unidad *" required>
            </div>
            <div class="col-6">
              <input type="number" name="orden" class="form-control form-control-sm" placeholder="Orden" value="<?= count($unidades)+1 ?>">
            </div>
            <div class="col-6 d-flex align-items-center gap-1">
              <label class="small mb-0">Color:</label>
              <input type="color" name="color" class="form-control form-control-sm form-control-color"
                     style="width:36px;height:30px" value="<?= $curso['color'] ?>">
            </div>
            <div class="col-12">
              <button type="submit" class="btn btn-sm w-100" style="background:<?= $curso['color'] ?>;color:#fff">
                + Nueva Unidad
              </button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Estudiantes y XP -->
  <div class="col-lg-8">

    <!-- Ranking XP -->
    <div class="sc mb-3">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h6 class="fw-bold mb-0"><i class="bi bi-trophy me-2 text-warning"></i>Ranking de la clase</h6>
        <span class="text-muted small"><?= count($estudiantes) ?> estudiantes &middot; <?= $totalXP ?? 0 ?> XP en total</span>
      </div>
      <?php foreach ($estudiantes as $i => $est):
        $nv     = $niveles[min(4, $est['nivel_xp'])] ?? $niveles[1];
        $xpNext = $niveles[min(4,$est['nivel_xp']+1)]['min'] ?? $nv['max'];
        $xpPrev = $nv['min'];
        $pct    = $xpNext > $xpPrev ? min(100, round(($est['xp_total']-$xpPrev)/($xpNext-$xpPrev)*100)) : 100;
        $colors = ['#ef4444','#f59e0b','#22c55e','#3b82f6','#7c3aed','#ec4899','#06b6d4','#84cc16','#f97316','#6366f1'];
        $avatarColor = $colors[$i % count($colors)];
        $initials = strtoupper(substr($est['nombres'],0,1).substr($est['apellidos'],0,1));
      ?>
      <div class="est-card">
        <div class="d-flex align-items-center gap-3">
          <!-- Posicion -->
          <div class="text-muted fw-bold" style="width:20px;font-size:.8rem;text-align:center">
            <?= $i===0?'&#x1F947;':($i===1?'&#x1F948;':($i===2?'&#x1F949;':'#'.($i+1))) ?>
          </div>
          <!-- Avatar -->
          <div class="avatar" style="background:<?= $avatarColor ?>"><?= $initials ?></div>
          <!-- Info -->
          <div class="flex-grow-1">
            <div class="d-flex align-items-center gap-2">
              <span class="fw-semibold" style="font-size:.85rem"><?= htmlspecialchars($est['apellidos'].', '.$est['nombres']) ?></span>
              <span class="nivel-tag" style="background:<?= $nv['color'] ?>"><?= $nv['icon'] ?> <?= $nv['nombre'] ?></span>
            </div>
            <div class="d-flex align-items-center gap-2 mt-1">
              <div class="xp-bar flex-grow-1">
                <div class="xp-fill" style="width:<?= $pct ?>%;background:<?= $nv['color'] ?>"></div>
              </div>
              <span class="text-muted" style="font-size:.72rem;white-space:nowrap"><?= $est['xp_total'] ?> XP</span>
            </div>
          </div>
          <!-- Acciones -->
          <div class="d-flex gap-1">
            <a href="estudiante.php?id=<?= $est['id'] ?>"
               class="btn btn-sm btn-outline-primary py-0 px-1" title="Ver perfil"><i class="bi bi-person"></i></a>
            <a href="materiales.php?curso_id=<?= $id ?>&est_id=<?= $est['id'] ?>"
               class="btn btn-sm btn-outline-secondary py-0 px-1" title="Materiales"><i class="bi bi-file-earmark-text"></i></a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($estudiantes)): ?>
        <div class="text-center text-muted py-3 small"><i class="bi bi-people fs-2 d-block mb-1"></i>Sin estudiantes</div>
      <?php endif; ?>
    </div>

    <!-- Form agregar estudiante -->
    <div class="sc">
      <h6 class="fw-bold mb-2"><i class="bi bi-person-plus me-2 text-primary"></i>Agregar Estudiante</h6>
      <form method="POST">
        <input type="hidden" name="action" value="save_est">
        <input type="hidden" name="csrf"   value="<?= Auth::csrfToken() ?>">
        <div class="row g-2 align-items-end">
          <div class="col-md-4">
            <input type="text" name="nombres" class="form-control form-control-sm" placeholder="Nombres *" required>
          </div>
          <div class="col-md-4">
            <input type="text" name="apellidos" class="form-control form-control-sm" placeholder="Apellidos *" required>
          </div>
          <div class="col-md-3">
            <input type="text" name="documento" class="form-control form-control-sm" placeholder="Documento">
          </div>
          <div class="col-md-1">
            <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-plus-lg"></i></button>
          </div>
        </div>
      </form>
    </div>

  </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
