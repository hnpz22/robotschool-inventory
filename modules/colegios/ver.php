<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();

$db  = Database::get();
$id  = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: ' . APP_URL . '/modules/colegios/'); exit; }

$col = $db->query("SELECT * FROM colegios WHERE id=$id")->fetch();
if (!$col) { header('Location: ' . APP_URL . '/modules/colegios/'); exit; }

$pageTitle  = $col['nombre'];
$activeMenu = 'colegios';
$error = $success = '';

// Guardar curso
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='save_curso') {
    if (!Auth::csrfVerify($_POST['csrf']??'')) die('CSRF');
    try {
        $cid  = (int)($_POST['curso_id'] ?? 0);
        $data = [
            'colegio_id'      => $id,
            'nombre'          => trim($_POST['nombre']),
            'grado'           => trim($_POST['grado']   ?? ''),
            'grupo'           => trim($_POST['grupo']   ?? ''),
            'nivel'           => $_POST['nivel']        ?? 'primaria',
            'num_estudiantes' => (int)($_POST['num_estudiantes'] ?? 0),
            'docente'         => trim($_POST['docente'] ?? ''),
            'kit_id'          => ($_POST['kit_id'] ?: null),
            'anio'            => ($_POST['anio']   ?: date('Y')),
            'activo'          => 1,
        ];
        if ($cid) {
            $sets = implode(',', array_map(function($k){ return "$k=:$k"; }, array_keys($data)));
            $data['id'] = $cid;
            $db->prepare("UPDATE cursos SET $sets WHERE id=:id AND colegio_id=$id")->execute($data);
            $success = 'Curso actualizado.';
        } else {
            $cols2 = implode(',', array_keys($data));
            $vals2 = ':'.implode(',:', array_keys($data));
            $db->prepare("INSERT INTO cursos ($cols2) VALUES ($vals2)")->execute($data);
            $success = 'Curso creado.';
        }
    } catch (Exception $e) { $error = $e->getMessage(); }
}

// Eliminar curso
if (isset($_GET['del_curso']) && Auth::csrfVerify($_GET['csrf']??'')) {
    $db->prepare("UPDATE cursos SET activo=0 WHERE id=? AND colegio_id=?")->execute([(int)$_GET['del_curso'], $id]);
    header('Location: '.APP_URL."/modules/colegios/ver.php?id=$id"); exit;
}

// Datos
$cursos = $db->query("
    SELECT cur.*, k.nombre AS kit_nombre, k.codigo AS kit_codigo, k.nivel AS kit_nivel
    FROM cursos cur
    LEFT JOIN kits k ON k.id=cur.kit_id
    WHERE cur.colegio_id=$id AND cur.activo=1
    ORDER BY cur.grado, cur.grupo, cur.nombre
")->fetchAll();

$kits = $db->query("SELECT id,codigo,nombre,nivel FROM kits WHERE activo=1 ORDER BY nombre")->fetchAll();

$editCurso = null;
if (isset($_GET['edit_curso'])) {
    $editCurso = $db->query("SELECT * FROM cursos WHERE id=".(int)$_GET['edit_curso']." AND colegio_id=$id")->fetch();
}

if (!empty($_GET['ok'])) {
    $msgs = ['creado'=>'Colegio creado.','guardado'=>'Cambios guardados.','kit_asignado'=>'Kit asignado.'];
    $success = $msgs[$_GET['ok']] ?? '';
}

$totalEst = array_sum(array_column($cursos,'num_estudiantes'));
$conKit   = count(array_filter($cursos, function($c){ return !empty($c['kit_id']); }));

// Agrupar por grado
$porGrado = [];
foreach ($cursos as $c) {
    $g = $c['grado'] ?: 'Sin grado';
    $porGrado[$g][] = $c;
}
ksort($porGrado);

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
.sc{background:#fff;border-radius:14px;border:1px solid #e2e8f0;padding:1rem 1.2rem;margin-bottom:1rem}
.irow{display:flex;gap:.5rem;padding:.4rem 0;border-bottom:.5px solid #f1f5f9;align-items:flex-start}
.irow:last-child{border-bottom:none}
.ilbl{font-size:.72rem;color:#94a3b8;min-width:78px;flex-shrink:0;padding-top:2px}
.ival{font-size:.84rem;font-weight:600;color:#1e293b}
.np{font-size:.68rem;padding:.15rem .5rem;border-radius:20px;font-weight:700}
.np-preescolar{background:#fae8ff;color:#7e22ce}
.np-primaria{background:#dbeafe;color:#1e40af}
.np-secundaria{background:#fef9c3;color:#854d0e}
.np-media{background:#fee2e2;color:#991b1b}
.np-otro{background:#f1f5f9;color:#475569}
.cc{border:1px solid #e2e8f0;border-radius:10px;background:#fff;overflow:hidden;margin-bottom:.5rem}
.cc:hover{box-shadow:0 2px 8px rgba(0,0,0,.06)}
.kb{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:7px;padding:.45rem .7rem}
.nk{background:#fef9c3;border:1px solid #fde047;border-radius:7px;padding:.4rem .7rem;font-size:.8rem}
</style>

<div class="d-flex align-items-center gap-2 mb-3">
  <a href="<?= APP_URL ?>/modules/colegios/" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i></a>
  <div class="flex-grow-1">
    <h4 class="fw-bold mb-0"><?= htmlspecialchars($col['nombre']) ?></h4>
    <span class="text-muted small">
      <?= htmlspecialchars($col['ciudad'] ?? '') ?>
      <?php if ($col['tipo']): ?>&nbsp;&middot;&nbsp;<span class="badge bg-secondary" style="font-size:.7rem"><?= ucfirst($col['tipo']) ?></span><?php endif; ?>
    </span>
  </div>
  <a href="<?= APP_URL ?>/modules/colegios/form.php?id=<?= $id ?>" class="btn btn-sm btn-outline-primary">
    <i class="bi bi-pencil me-1"></i>Editar
  </a>
</div>

<?php if ($error):   ?><div class="alert alert-danger  py-2 small"><?= $error   ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="row g-3">

  <!-- Izquierda: info -->
  <div class="col-lg-4">
    <div class="sc text-center">
      <?php if ($col['logo']): ?>
        <img src="<?= UPLOAD_URL.htmlspecialchars($col['logo']) ?>" class="img-fluid rounded mb-2" style="max-height:80px;object-fit:contain">
      <?php else: ?>
        <div style="font-size:2.5rem;margin-bottom:.5rem">&#x1F3EB;</div>
      <?php endif; ?>
      <h5 class="fw-bold mb-0"><?= htmlspecialchars($col['nombre']) ?></h5>
      <?php if ($col['direccion']): ?><p class="text-muted small mb-0"><?= htmlspecialchars($col['direccion']) ?></p><?php endif; ?>
    </div>

    <div class="sc">
      <h6 class="fw-bold mb-2"><i class="bi bi-info-circle me-1 text-primary"></i>Contacto</h6>
      <?php
      $filas = [
        ['bi-person',    'Rector',    $col['rector']   ?? ''],
        ['bi-person-badge','Contacto',$col['contacto'] ?? ''],
        ['bi-envelope',  'Email',     $col['email']    ?? ''],
        ['bi-telephone', 'Tel',       $col['telefono'] ?? ''],
        ['bi-card-text', 'NIT',       $col['nit']      ?? ''],
      ];
      foreach ($filas as $f) {
          if (!$f[2]) continue;
          echo '<div class="irow">';
          echo '<i class="bi '.$f[0].' text-muted" style="margin-top:2px"></i>';
          echo '<div><div class="ilbl">'.$f[1].'</div><div class="ival">'.htmlspecialchars($f[2]).'</div></div>';
          echo '</div>';
      }
      ?>
      <?php if ($col['notas']): ?>
        <div class="mt-2 p-2 bg-light rounded small text-muted"><?= nl2br(htmlspecialchars($col['notas'])) ?></div>
      <?php endif; ?>
    </div>

    <div class="sc">
      <h6 class="fw-bold mb-2"><i class="bi bi-bar-chart me-1 text-success"></i>Resumen</h6>
      <div class="row g-2 text-center">
        <div class="col-4">
          <div class="fs-4 fw-bold text-primary"><?= count($cursos) ?></div>
          <div class="text-muted" style="font-size:.72rem">Cursos</div>
        </div>
        <div class="col-4">
          <div class="fs-4 fw-bold text-success"><?= number_format($totalEst) ?></div>
          <div class="text-muted" style="font-size:.72rem">Estudiantes</div>
        </div>
        <div class="col-4">
          <div class="fs-4 fw-bold text-warning"><?= $conKit ?></div>
          <div class="text-muted" style="font-size:.72rem">Con Kit</div>
        </div>
      </div>
    </div>
  </div>

  <!-- Derecha: cursos -->
  <div class="col-lg-8">

    <!-- Formulario curso -->
    <div class="sc mb-3">
      <h6 class="fw-bold mb-3">
        <i class="bi bi-mortarboard me-2 text-primary"></i>
        <?= $editCurso ? 'Editar Curso' : 'Agregar Curso' ?>
      </h6>
      <form method="POST">
        <input type="hidden" name="action" value="save_curso">
        <input type="hidden" name="csrf"   value="<?= Auth::csrfToken() ?>">
        <?php if ($editCurso): ?><input type="hidden" name="curso_id" value="<?= $editCurso['id'] ?>"><?php endif; ?>
        <div class="row g-2">
          <div class="col-md-5">
            <input type="text" name="nombre" class="form-control form-control-sm" required
                   placeholder="Nombre del curso *"
                   value="<?= htmlspecialchars($editCurso['nombre'] ?? '') ?>">
          </div>
          <div class="col-md-2">
            <input type="text" name="grado" class="form-control form-control-sm"
                   placeholder="Grado" value="<?= htmlspecialchars($editCurso['grado'] ?? '') ?>">
          </div>
          <div class="col-md-2">
            <input type="text" name="grupo" class="form-control form-control-sm"
                   placeholder="Grupo" value="<?= htmlspecialchars($editCurso['grupo'] ?? '') ?>">
          </div>
          <div class="col-md-3">
            <select name="nivel" class="form-select form-select-sm">
              <?php foreach (['preescolar','primaria','secundaria','media','otro'] as $nv): ?>
                <option value="<?= $nv ?>" <?= ($editCurso['nivel']??'primaria')===$nv?'selected':'' ?>><?= ucfirst($nv) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <input type="number" name="num_estudiantes" class="form-control form-control-sm"
                   min="0" placeholder="# estudiantes" value="<?= $editCurso['num_estudiantes'] ?? 0 ?>">
          </div>
          <div class="col-md-2">
            <input type="number" name="anio" class="form-control form-control-sm"
                   min="2020" max="2035" placeholder="Anio" value="<?= $editCurso['anio'] ?? date('Y') ?>">
          </div>
          <div class="col-md-7">
            <input type="text" name="docente" class="form-control form-control-sm"
                   placeholder="Docente responsable"
                   value="<?= htmlspecialchars($editCurso['docente'] ?? '') ?>">
          </div>
          <div class="col-12">
            <select name="kit_id" class="form-select form-select-sm">
              <option value="">-- Sin kit asignado --</option>
              <?php foreach ($kits as $k): ?>
                <option value="<?= $k['id'] ?>" <?= ($editCurso['kit_id']??0)==$k['id']?'selected':'' ?>>
                  <?= htmlspecialchars($k['codigo']) ?> &mdash; <?= htmlspecialchars($k['nombre']) ?> (<?= ucfirst($k['nivel']??'basico') ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm">
              <i class="bi bi-save me-1"></i><?= $editCurso ? 'Guardar' : 'Agregar Curso' ?>
            </button>
            <?php if ($editCurso): ?>
              <a href="ver.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm">Cancelar</a>
            <?php endif; ?>
          </div>
        </div>
      </form>
    </div>

    <!-- Lista de cursos -->
    <div class="sc">
      <h6 class="fw-bold mb-3"><i class="bi bi-list-check me-2 text-primary"></i>Cursos (<?= count($cursos) ?>)</h6>
      <?php if (empty($cursos)): ?>
        <div class="text-center text-muted py-4 small">
          <i class="bi bi-mortarboard fs-2 d-block mb-2"></i>
          No hay cursos. Agrega el primero arriba.
        </div>
      <?php else: ?>
        <?php foreach ($porGrado as $grado => $lista): ?>
        <div class="mb-3">
          <div class="d-flex align-items-center gap-2 mb-2">
            <span class="badge bg-dark rounded-pill" style="font-size:.72rem">Grado <?= htmlspecialchars($grado) ?></span>
            <span class="text-muted small"><?= count($lista) ?> curso(s)</span>
          </div>
          <div class="row g-2">
          <?php foreach ($lista as $c): ?>
          <div class="col-md-6">
            <div class="cc">
              <div class="d-flex align-items-start justify-content-between p-3 pb-2">
                <div>
                  <div class="fw-bold" style="font-size:.88rem"><?= htmlspecialchars($c['nombre']) ?></div>
                  <div class="d-flex gap-1 mt-1 flex-wrap">
                    <span class="np np-<?= $c['nivel']??'otro' ?>"><?= ucfirst($c['nivel']??'otro') ?></span>
                    <?php if ($c['anio']): ?>
                      <span class="badge bg-light text-dark border" style="font-size:.65rem"><?= $c['anio'] ?></span>
                    <?php endif; ?>
                    <?php if ($c['num_estudiantes']): ?>
                      <span class="badge bg-light text-dark border" style="font-size:.65rem"><i class="bi bi-people"></i> <?= $c['num_estudiantes'] ?></span>
                    <?php endif; ?>
                  </div>
                  <?php if ($c['docente']): ?>
                    <div class="text-muted mt-1" style="font-size:.75rem"><i class="bi bi-person me-1"></i><?= htmlspecialchars($c['docente']) ?></div>
                  <?php endif; ?>
                </div>
                <div class="d-flex gap-1">
                  <a href="ver.php?id=<?= $id ?>&edit_curso=<?= $c['id'] ?>"
                     class="btn btn-sm btn-outline-primary py-0 px-1" style="font-size:.7rem"><i class="bi bi-pencil"></i></a>
                  <a href="ver.php?id=<?= $id ?>&del_curso=<?= $c['id'] ?>&csrf=<?= Auth::csrfToken() ?>"
                     class="btn btn-sm btn-outline-danger py-0 px-1" style="font-size:.7rem"
                     onclick="return confirm('Eliminar <?= addslashes($c['nombre']) ?>?')"><i class="bi bi-trash"></i></a>
                </div>
              </div>
              <div class="px-3 pb-3">
                <?php if ($c['kit_id']): ?>
                <div class="kb">
                  <div class="d-flex align-items-center justify-content-between">
                    <div>
                      <div class="fw-bold text-success" style="font-size:.8rem"><?= htmlspecialchars($c['kit_nombre']) ?></div>
                      <code class="text-success" style="font-size:.7rem"><?= htmlspecialchars($c['kit_codigo']) ?></code>
                    </div>
                    <div class="d-flex gap-1">
                      <a href="<?= APP_URL ?>/modules/kits/constructor.php?kit_id=<?= $c['kit_id'] ?>"
                         class="btn btn-sm btn-outline-success py-0 px-1" title="Editar kit"><i class="bi bi-tools"></i></a>
                      <a href="<?= APP_URL ?>/modules/kits/sticker_caja.php?kit_id=<?= $c['kit_id'] ?>"
                         target="_blank" class="btn btn-sm btn-outline-warning py-0 px-1" title="Sticker"><i class="bi bi-printer"></i></a>
                    </div>
                  </div>
                </div>
                <?php else: ?>
                <div class="nk d-flex justify-content-between align-items-center">
                  <span class="text-muted"><i class="bi bi-exclamation-triangle me-1 text-warning"></i>Sin kit</span>
                  <a href="<?= APP_URL ?>/modules/kits/constructor.php?curso_id=<?= $c['id'] ?>"
                     class="btn btn-warning btn-sm py-0 px-2 fw-bold" style="font-size:.72rem">
                    <i class="bi bi-tools me-1"></i>Armar Kit
                  </a>
                </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
