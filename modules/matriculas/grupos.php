<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();

$db         = Database::get();
$pageTitle  = 'Grupos';
$activeMenu = 'matriculas';
$error = $success = '';

// Guardar grupo
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='save_grupo') {
    if (!Auth::csrfVerify($_POST['csrf']??'')) die('CSRF');
    try {
        $gid = (int)($_POST['grupo_id'] ?? 0);
        $data = [
            'programa_id'  => (int)$_POST['programa_id'],
            'nombre'       => trim($_POST['nombre']),
            'sede'         => trim($_POST['sede']     ?? ''),
            'dia_semana'   => (int)($_POST['dia_semana'] ?? 7),
            'hora_inicio'  => $_POST['hora_inicio']   ?? '09:00',
            'hora_fin'     => $_POST['hora_fin']       ?? '11:00',
            'docente'      => trim($_POST['docente']   ?? ''),
            'periodo'      => trim($_POST['periodo']   ?? ''),
            'fecha_inicio' => $_POST['fecha_inicio']  ?: null,
            'fecha_fin'    => $_POST['fecha_fin']      ?: null,
            'kit_id'       => ($_POST['kit_id']        ?: null),
            'elemento_id'  => ($_POST['elemento_id']   ?: null),
            'cupo_max'     => (int)($_POST['cupo_max'] ?? 15),
            'activo'       => 1,
        ];
        if ($gid) {
            $sets = implode(',', array_map(function($k){ return "$k=:$k"; }, array_keys($data)));
            $data['id'] = $gid;
            $db->prepare("UPDATE escuela_grupos SET $sets WHERE id=:id")->execute($data);
            $success = 'Grupo actualizado.';
        } else {
            $cols2 = implode(',', array_keys($data));
            $vals2 = ':'.implode(',:', array_keys($data));
            $db->prepare("INSERT INTO escuela_grupos ($cols2) VALUES ($vals2)")->execute($data);
            $success = 'Grupo creado.';
        }
    } catch (Exception $e) { $error = $e->getMessage(); }
}

// Eliminar grupo
if (isset($_GET['del']) && Auth::csrfVerify($_GET['csrf']??'')) {
    $delId = (int)$_GET['del'];
    $enUso = $db->query("SELECT COUNT(*) FROM matriculas WHERE grupo_id=$delId AND estado='activa'")->fetchColumn();
    if ($enUso > 0) {
        $error = 'No se puede eliminar: hay '.$enUso.' matriculas activas en este grupo.';
    } else {
        $db->prepare("UPDATE escuela_grupos SET activo=0 WHERE id=?")->execute([$delId]);
        $success = 'Grupo eliminado.';
    }
}

$editGrupo = null;
if (isset($_GET['edit'])) {
    $editGrupo = $db->query("SELECT * FROM escuela_grupos WHERE id=".(int)$_GET['edit'])->fetch();
}

$grupos    = $db->query("SELECT * FROM v_grupos_cupos ORDER BY dia_semana, hora_inicio")->fetchAll();
$programas = $db->query("SELECT id,nombre,tipo FROM escuela_programas WHERE activo=1 ORDER BY nombre")->fetchAll();
$kits      = $db->query("SELECT id,codigo,nombre FROM kits WHERE activo=1 ORDER BY nombre")->fetchAll();

// Elementos que pueden ser kits de clase (sensores, robots, etc)
$elementos = $db->query("
    SELECT e.id, e.codigo, e.nombre, e.stock_actual, c.nombre AS cat
    FROM elementos e
    JOIN categorias c ON c.id=e.categoria_id
    WHERE e.activo=1 AND e.stock_actual > 0
    ORDER BY e.stock_actual DESC, e.nombre
")->fetchAll();

$dias = ['2'=>'Lunes','3'=>'Martes','4'=>'Miercoles','5'=>'Jueves','6'=>'Viernes','7'=>'Sabado','1'=>'Domingo'];

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
.sc{background:#fff;border-radius:14px;border:1px solid #e2e8f0;padding:1rem 1.2rem;margin-bottom:1rem}
.disp-disponible{background:#dcfce7;color:#166534}
.disp-casi_lleno{background:#fef9c3;color:#854d0e}
.disp-lleno{background:#fee2e2;color:#991b1b}
.cupo-bar{height:6px;border-radius:3px;background:#e2e8f0;overflow:hidden;margin-top:4px}
.cupo-fill{height:100%;border-radius:3px}
</style>

<div class="d-flex align-items-center gap-2 mb-3">
  <a href="index.php" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i></a>
  <h4 class="fw-bold mb-0">Grupos de Clase</h4>
</div>

<?php if ($error):   ?><div class="alert alert-danger  py-2 small"><?= $error   ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="row g-3">

  <!-- Formulario -->
  <div class="col-lg-5">
    <div class="sc">
      <h6 class="fw-bold mb-3"><i class="bi bi-plus-circle me-2 text-primary"></i><?= $editGrupo ? 'Editar Grupo' : 'Nuevo Grupo' ?></h6>
      <form method="POST">
        <input type="hidden" name="action" value="save_grupo">
        <input type="hidden" name="csrf"   value="<?= Auth::csrfToken() ?>">
        <?php if ($editGrupo): ?><input type="hidden" name="grupo_id" value="<?= $editGrupo['id'] ?>"><?php endif; ?>

        <div class="row g-2">
          <div class="col-12">
            <label class="form-label small fw-semibold">Programa *</label>
            <select name="programa_id" class="form-select form-select-sm" required>
              <option value="">Seleccionar programa...</option>
              <?php foreach ($programas as $p): ?>
                <option value="<?= $p['id'] ?>" <?= ($editGrupo['programa_id']??0)==$p['id']?'selected':'' ?>>
                  <?= htmlspecialchars($p['nombre']) ?> (<?= $p['tipo'] ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-8">
            <label class="form-label small fw-semibold">Nombre del grupo *</label>
            <input type="text" name="nombre" class="form-control form-control-sm" required
                   placeholder="Ej: Lego Sabados 9am"
                   value="<?= htmlspecialchars($editGrupo['nombre'] ?? '') ?>">
          </div>
          <div class="col-4">
            <label class="form-label small fw-semibold">Periodo</label>
            <input type="text" name="periodo" class="form-control form-control-sm"
                   placeholder="2025-1"
                   value="<?= htmlspecialchars($editGrupo['periodo'] ?? '') ?>">
          </div>
          <div class="col-4">
            <label class="form-label small fw-semibold">Dia</label>
            <select name="dia_semana" class="form-select form-select-sm">
              <?php foreach ($dias as $v => $l): ?>
                <option value="<?= $v ?>" <?= ($editGrupo['dia_semana']??7)==$v?'selected':'' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-4">
            <label class="form-label small fw-semibold">Hora inicio</label>
            <input type="time" name="hora_inicio" class="form-control form-control-sm"
                   value="<?= $editGrupo['hora_inicio'] ?? '09:00' ?>">
          </div>
          <div class="col-4">
            <label class="form-label small fw-semibold">Hora fin</label>
            <input type="time" name="hora_fin" class="form-control form-control-sm"
                   value="<?= $editGrupo['hora_fin'] ?? '11:00' ?>">
          </div>
          <div class="col-12">
            <label class="form-label small fw-semibold">Docente</label>
            <input type="text" name="docente" class="form-control form-control-sm"
                   value="<?= htmlspecialchars($editGrupo['docente'] ?? '') ?>">
          </div>

          <!-- Kit vinculado - la clave del sistema -->
          <div class="col-12">
            <label class="form-label small fw-semibold">
              <i class="bi bi-box-seam me-1 text-primary"></i>
              Kit / Robot del grupo
              <span class="text-muted fw-normal">(define los cupos)</span>
            </label>
            <select name="kit_id" class="form-select form-select-sm" onchange="if(this.value)document.querySelector('[name=elemento_id]').value=''">
              <option value="">-- Sin kit vinculado --</option>
              <?php foreach ($kits as $k): ?>
                <option value="<?= $k['id'] ?>" <?= ($editGrupo['kit_id']??0)==$k['id']?'selected':'' ?>>
                  <?= htmlspecialchars($k['codigo']) ?> &mdash; <?= htmlspecialchars($k['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-12">
            <label class="form-label small fw-semibold">
              <i class="bi bi-cpu me-1 text-success"></i>
              O elemento especifico del inventario
            </label>
            <select name="elemento_id" class="form-select form-select-sm" onchange="if(this.value)document.querySelector('[name=kit_id]').value=''">
              <option value="">-- Sin elemento vinculado --</option>
              <?php foreach ($elementos as $e): ?>
                <option value="<?= $e['id'] ?>" <?= ($editGrupo['elemento_id']??0)==$e['id']?'selected':'' ?>>
                  <?= htmlspecialchars($e['codigo']) ?> &mdash; <?= htmlspecialchars($e['nombre']) ?>
                  (Stock: <?= $e['stock_actual'] ?>)
                </option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">
              Los cupos del grupo = stock disponible del elemento en inventario.
              Si hay 10 Lego Spike hay 10 cupos.
            </div>
          </div>

          <div class="col-6">
            <label class="form-label small fw-semibold">Fecha inicio</label>
            <input type="date" name="fecha_inicio" class="form-control form-control-sm"
                   value="<?= $editGrupo['fecha_inicio'] ?? '' ?>">
          </div>
          <div class="col-6">
            <label class="form-label small fw-semibold">Fecha fin</label>
            <input type="date" name="fecha_fin" class="form-control form-control-sm"
                   value="<?= $editGrupo['fecha_fin'] ?? '' ?>">
          </div>

          <div class="col-12 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm">
              <i class="bi bi-save me-1"></i><?= $editGrupo ? 'Guardar' : 'Crear Grupo' ?>
            </button>
            <?php if ($editGrupo): ?>
              <a href="grupos.php" class="btn btn-outline-secondary btn-sm">Cancelar</a>
            <?php endif; ?>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Lista de grupos -->
  <div class="col-lg-7">
    <?php if (empty($grupos)): ?>
      <div class="sc text-center text-muted py-5">
        <i class="bi bi-people fs-2 d-block mb-2"></i>
        No hay grupos creados.
      </div>
    <?php else: ?>
    <?php foreach ($grupos as $g):
      $stock = $g['stock_disponible'] ?? $g['cupo_max'] ?? 0;
      $pct   = $stock > 0 ? min(100, round($g['matriculas_activas'] / $stock * 100)) : 100;
      $fc    = ['disponible'=>'#22c55e','casi_lleno'=>'#f59e0b','lleno'=>'#ef4444'][$g['disponibilidad']] ?? '#94a3b8';
    ?>
    <div class="sc mb-2">
      <div class="d-flex align-items-start justify-content-between">
        <div class="flex-grow-1">
          <div class="d-flex align-items-center gap-2 flex-wrap">
            <span class="fw-bold"><?= htmlspecialchars($g['nombre']) ?></span>
            <span class="badge disp-<?= $g['disponibilidad'] ?>" style="font-size:.68rem">
              <?= $g['cupos_libres'] ?> cupos libres
            </span>
            <span class="badge bg-light text-dark border" style="font-size:.66rem">
              <?= $dias[$g['dia_semana']] ?? 'Sab' ?>
              <?= substr($g['hora_inicio'],0,5) ?>
            </span>
          </div>
          <div class="text-muted small mt-1"><?= htmlspecialchars($g['programa_nombre']) ?></div>

          <!-- Kit vinculado -->
          <?php if ($g['elemento_nombre'] || $g['kit_nombre']): ?>
          <div class="mt-1" style="font-size:.77rem">
            <i class="bi bi-box-seam me-1 text-primary"></i>
            <strong><?= htmlspecialchars($g['elemento_nombre'] ?: $g['kit_nombre']) ?></strong>
            &nbsp;
            <span class="text-muted">Stock: <?= $stock ?> &rarr; <?= $g['matriculas_activas'] ?> en uso &rarr; <?= $g['cupos_libres'] ?> disponibles</span>
          </div>
          <?php else: ?>
          <div class="mt-1 text-warning" style="font-size:.75rem">
            <i class="bi bi-exclamation-triangle me-1"></i>Sin kit vinculado &mdash; cupos manuales: <?= $g['cupo_max'] ?>
          </div>
          <?php endif; ?>

          <!-- Barra cupos -->
          <div class="cupo-bar mt-1" style="max-width:200px">
            <div class="cupo-fill" style="width:<?= $pct ?>%;background:<?= $fc ?>"></div>
          </div>
        </div>

        <div class="d-flex gap-1 ms-2">
          <a href="grupo.php?id=<?= $g['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-1" title="Ver matriculas">
            <i class="bi bi-people"></i>
          </a>
          <a href="grupos.php?edit=<?= $g['id'] ?>" class="btn btn-sm btn-outline-secondary py-0 px-1">
            <i class="bi bi-pencil"></i>
          </a>
          <a href="grupos.php?del=<?= $g['id'] ?>&csrf=<?= Auth::csrfToken() ?>"
             class="btn btn-sm btn-outline-danger py-0 px-1"
             onclick="return confirm('Eliminar este grupo?')">
            <i class="bi bi-trash"></i>
          </a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
