<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();

$db = Database::get();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: '.APP_URL.'/modules/academico/'); exit; }

$unidad = $db->query("
    SELECT uni.*, cur.nombre AS curso_nombre, cur.color AS curso_color,
           cur.colegio_id, ac.nombre AS colegio_nombre, cur.id AS curso_id
    FROM acad_unidades uni
    JOIN acad_cursos cur ON cur.id=uni.curso_id
    JOIN acad_colegios ac ON ac.id=cur.colegio_id
    WHERE uni.id=$id
")->fetch();
if (!$unidad) { header('Location: '.APP_URL.'/modules/academico/'); exit; }

$pageTitle  = $unidad['titulo'];
$activeMenu = 'academico';
$error = $success = '';

$TIPOS = [
    'guia'       => ['label'=>'Guia',       'icon'=>'bi-file-earmark-text',  'color'=>'#185FA5'],
    'lectura'    => ['label'=>'Lectura',     'icon'=>'bi-book',               'color'=>'#7c3aed'],
    'video'      => ['label'=>'Video',       'icon'=>'bi-play-circle',        'color'=>'#dc2626'],
    'ejercicio'  => ['label'=>'Ejercicio',   'icon'=>'bi-pencil-square',      'color'=>'#16a34a'],
    'proyecto'   => ['label'=>'Proyecto',    'icon'=>'bi-tools',              'color'=>'#d97706'],
    'taller'     => ['label'=>'Taller',      'icon'=>'bi-wrench',             'color'=>'#0891b2'],
    'evaluacion' => ['label'=>'Evaluacion',  'icon'=>'bi-clipboard-check',    'color'=>'#7f1d1d'],
];

// Guardar actividad
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='save_act') {
    if (!Auth::csrfVerify($_POST['csrf']??'')) die('CSRF');
    try {
        $aid = (int)($_POST['act_id'] ?? 0);
        $data = [
            'unidad_id'   => $id,
            'titulo'      => trim($_POST['titulo']),
            'tipo'        => $_POST['tipo'] ?? 'guia',
            'descripcion' => trim($_POST['descripcion'] ?? ''),
            'contenido'   => trim($_POST['contenido']   ?? ''),
            'url_video'   => trim($_POST['url_video']   ?? '') ?: null,
            'puntos'      => (int)($_POST['puntos'] ?? 10),
            'orden'       => (int)($_POST['orden']  ?? 1),
            'activo'      => 1,
        ];
        if ($aid) {
            $sets = implode(',', array_map(function($k){ return "$k=:$k"; }, array_keys($data)));
            $data['id'] = $aid;
            $db->prepare("UPDATE acad_actividades SET $sets WHERE id=:id")->execute($data);
        } else {
            $cols2 = implode(',', array_keys($data));
            $vals2 = ':'.implode(',:', array_keys($data));
            $db->prepare("INSERT INTO acad_actividades ($cols2) VALUES ($vals2)")->execute($data);
        }
        $success = 'Actividad guardada.';
    } catch (Exception $e) { $error = $e->getMessage(); }
}

$actividades = $db->query("
    SELECT act.*,
      (SELECT COUNT(*) FROM acad_progreso pr WHERE pr.actividad_id=act.id AND pr.estado='completado') AS completadas_cnt,
      (SELECT COUNT(*) FROM acad_estudiantes est WHERE est.curso_id={$unidad['curso_id']} AND est.activo=1) AS total_est
    FROM acad_actividades act
    WHERE act.unidad_id=$id AND act.activo=1
    ORDER BY act.orden
")->fetchAll();

$totalXP = array_sum(array_column($actividades,'puntos'));
$editAct  = isset($_GET['edit_act']) ? $db->query("SELECT * FROM acad_actividades WHERE id=".(int)$_GET['edit_act']." AND unidad_id=$id")->fetch() : null;

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
.sc{background:#fff;border-radius:14px;border:1px solid #e2e8f0;padding:1rem 1.2rem;margin-bottom:1rem}
.act-card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden;margin-bottom:.5rem;transition:.15s}
.act-card:hover{box-shadow:0 2px 8px rgba(0,0,0,.06)}
.tipo-icon{width:38px;height:38px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0}
.prog-bar{height:5px;border-radius:3px;background:#e2e8f0;overflow:hidden}
.prog-fill{height:100%;background:#22c55e;border-radius:3px}
</style>

<div class="d-flex align-items-center gap-2 mb-3">
  <a href="curso.php?id=<?= $unidad['curso_id'] ?>" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i></a>
  <div class="flex-grow-1">
    <h4 class="fw-bold mb-0" style="color:<?= $unidad['color'] ?? $unidad['curso_color'] ?>"><?= htmlspecialchars($unidad['titulo']) ?></h4>
    <span class="text-muted small"><?= htmlspecialchars($unidad['curso_nombre']) ?> &middot; <?= htmlspecialchars($unidad['colegio_nombre']) ?></span>
  </div>
  <span class="badge" style="background:<?= $unidad['color'] ?? $unidad['curso_color'] ?>;font-size:.75rem"><?= $totalXP ?> XP total</span>
</div>

<?php if ($error):   ?><div class="alert alert-danger  py-2 small"><?= $error   ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="row g-3">

  <!-- Actividades -->
  <div class="col-lg-8">
    <div class="sc">
      <h6 class="fw-bold mb-3"><i class="bi bi-lightning-charge me-2 text-warning"></i>Actividades (<?= count($actividades) ?>)</h6>

      <?php foreach ($actividades as $act):
        $ti   = $TIPOS[$act['tipo']] ?? $TIPOS['guia'];
        $pct  = $act['total_est'] > 0 ? round($act['completadas_cnt']/$act['total_est']*100) : 0;
      ?>
      <div class="act-card">
        <div class="p-3 d-flex align-items-start gap-3">
          <div class="tipo-icon" style="background:<?= $ti['color'] ?>20;color:<?= $ti['color'] ?>">
            <i class="bi <?= $ti['icon'] ?>"></i>
          </div>
          <div class="flex-grow-1">
            <div class="d-flex align-items-start justify-content-between">
              <div>
                <div class="fw-bold" style="font-size:.88rem"><?= htmlspecialchars($act['titulo']) ?></div>
                <div class="d-flex gap-2 mt-1 flex-wrap">
                  <span class="badge" style="background:<?= $ti['color'] ?>20;color:<?= $ti['color'] ?>;font-size:.68rem"><?= $ti['label'] ?></span>
                  <span class="badge bg-warning text-dark" style="font-size:.68rem">&#x26A1; <?= $act['puntos'] ?> XP</span>
                </div>
                <?php if ($act['descripcion']): ?>
                  <div class="text-muted mt-1" style="font-size:.78rem"><?= htmlspecialchars(mb_strimwidth($act['descripcion'],0,80,'...')) ?></div>
                <?php endif; ?>
              </div>
              <div class="d-flex gap-1 ms-2">
                <a href="actividad.php?id=<?= $act['id'] ?>" class="btn btn-sm btn-primary py-0 px-2" style="font-size:.72rem">Ver</a>
                <a href="unidad.php?id=<?= $id ?>&edit_act=<?= $act['id'] ?>" class="btn btn-sm btn-outline-secondary py-0 px-1"><i class="bi bi-pencil"></i></a>
              </div>
            </div>
            <!-- Progreso del grupo -->
            <div class="d-flex align-items-center gap-2 mt-2">
              <div class="prog-bar flex-grow-1">
                <div class="prog-fill" style="width:<?= $pct ?>%"></div>
              </div>
              <span class="text-muted" style="font-size:.7rem;white-space:nowrap"><?= $act['completadas_cnt'] ?>/<?= $act['total_est'] ?> completaron</span>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($actividades)): ?>
        <div class="text-center text-muted py-4"><i class="bi bi-lightning-charge fs-2 d-block mb-2"></i>Sin actividades aun</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Form actividad -->
  <div class="col-lg-4">
    <div class="sc">
      <h6 class="fw-bold mb-3"><i class="bi bi-plus-circle me-2 text-primary"></i><?= $editAct ? 'Editar Actividad' : 'Nueva Actividad' ?></h6>
      <form method="POST">
        <input type="hidden" name="action" value="save_act">
        <input type="hidden" name="csrf"   value="<?= Auth::csrfToken() ?>">
        <?php if ($editAct): ?><input type="hidden" name="act_id" value="<?= $editAct['id'] ?>"><?php endif; ?>
        <div class="row g-2">
          <div class="col-12">
            <input type="text" name="titulo" class="form-control form-control-sm" required
                   placeholder="Titulo de la actividad *"
                   value="<?= htmlspecialchars($editAct['titulo'] ?? '') ?>">
          </div>
          <div class="col-7">
            <select name="tipo" class="form-select form-select-sm">
              <?php foreach ($TIPOS as $tk => $tv): ?>
                <option value="<?= $tk ?>" <?= ($editAct['tipo']??'guia')===$tk?'selected':'' ?>><?= $tv['label'] ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-3">
            <input type="number" name="puntos" class="form-control form-control-sm"
                   placeholder="XP" min="0" max="500" value="<?= $editAct['puntos'] ?? 10 ?>">
          </div>
          <div class="col-2">
            <input type="number" name="orden" class="form-control form-control-sm"
                   placeholder="#" min="1" value="<?= $editAct['orden'] ?? count($actividades)+1 ?>">
          </div>
          <div class="col-12">
            <textarea name="descripcion" class="form-control form-control-sm" rows="2"
                      placeholder="Descripcion breve..."><?= htmlspecialchars($editAct['descripcion'] ?? '') ?></textarea>
          </div>
          <div class="col-12">
            <input type="url" name="url_video" class="form-control form-control-sm"
                   placeholder="URL del video (YouTube, Drive...)"
                   value="<?= htmlspecialchars($editAct['url_video'] ?? '') ?>">
          </div>
          <div class="col-12">
            <textarea name="contenido" class="form-control form-control-sm" rows="4"
                      placeholder="Contenido / instrucciones (HTML o texto)..."><?= htmlspecialchars($editAct['contenido'] ?? '') ?></textarea>
          </div>
          <div class="col-12 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm"><?= $editAct ? 'Guardar' : 'Crear' ?></button>
            <?php if ($editAct): ?><a href="unidad.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm">Cancelar</a><?php endif; ?>
          </div>
        </div>
      </form>
    </div>

    <!-- Info unidad -->
    <div class="sc">
      <h6 class="fw-bold mb-2"><i class="bi bi-info-circle me-1 text-muted"></i>Resumen</h6>
      <div class="row g-2 text-center">
        <div class="col-4">
          <div class="fs-4 fw-bold text-primary"><?= count($actividades) ?></div>
          <div class="text-muted" style="font-size:.7rem">Actividades</div>
        </div>
        <div class="col-4">
          <div class="fs-4 fw-bold text-warning"><?= $totalXP ?></div>
          <div class="text-muted" style="font-size:.7rem">XP total</div>
        </div>
        <div class="col-4">
          <?php $completadas = array_sum(array_column($actividades,'completadas_cnt')); ?>
          <div class="fs-4 fw-bold text-success"><?= $completadas ?></div>
          <div class="text-muted" style="font-size:.7rem">Completadas</div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
