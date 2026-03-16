<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();

$db  = Database::get();
$id  = (int)($_GET['id'] ?? 0);
$tab = $_GET['tab'] ?? 'info';
if (!$id) { header('Location: '.APP_URL.'/modules/cursos/'); exit; }

// Verificar que la tabla existe
$tablaExiste = $db->query("SHOW TABLES LIKE 'escuela_cursos'")->fetchColumn();
if (!$tablaExiste) { header('Location: '.APP_URL.'/modules/cursos/'); exit; }

$curso = $db->query("SELECT * FROM escuela_cursos WHERE id=$id")->fetch();
if (!$curso) { header('Location: '.APP_URL.'/modules/cursos/'); exit; }

$pageTitle  = $curso['nombre'];
$activeMenu = 'cursos';
$error = $success = '';

// Guardar módulo
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='save_modulo') {
    if (!Auth::csrfVerify($_POST['csrf']??'')) die('CSRF');
    try {
        $mid = (int)($_POST['mod_id'] ?? 0);
        $data = [
            'curso_id'    => $id,
            'titulo'      => trim($_POST['titulo']),
            'descripcion' => trim($_POST['descripcion'] ?? ''),
            'orden'       => (int)($_POST['orden'] ?? 1),
            'sesiones'    => (int)($_POST['sesiones'] ?? 2),
            'icono'       => $_POST['icono'] ?? 'bi-bookmark',
            'color'       => $_POST['color'] ?? $curso['color_primario'],
            'activo'      => 1,
        ];
        if ($mid) {
            $sets = implode(',', array_map(function($k){ return "$k=:$k"; }, array_keys($data)));
            $data['id'] = $mid;
            $db->prepare("UPDATE escuela_modulos SET $sets WHERE id=:id")->execute($data);
        } else {
            $c2 = implode(',', array_keys($data));
            $v2 = ':'.implode(',:', array_keys($data));
            $db->prepare("INSERT INTO escuela_modulos ($c2) VALUES ($v2)")->execute($data);
        }
        $success = 'Modulo guardado.'; $tab = 'modulos';
    } catch (Exception $e) { $error = $e->getMessage(); }
}

// Guardar horario
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='save_horario') {
    if (!Auth::csrfVerify($_POST['csrf']??'')) die('CSRF');
    try {
        $hid = (int)($_POST['hor_id'] ?? 0);
        $data = [
            'curso_id'    => $id,
            'dia_semana'  => (int)($_POST['dia_semana'] ?? 7),
            'hora_inicio' => $_POST['hora_inicio'],
            'hora_fin'    => $_POST['hora_fin'],
            'instructor'  => trim($_POST['instructor'] ?? ''),
            'sede'        => trim($_POST['sede'] ?? ''),
            'periodo'     => trim($_POST['periodo'] ?? ''),
            'elemento_id' => ($_POST['elemento_id'] ?: null),
            'kit_id'      => ($_POST['kit_id'] ?: null),
            'cupo_max'    => (int)($_POST['cupo_max'] ?? 15),
            'activo'      => 1,
        ];
        if ($hid) {
            $sets = implode(',', array_map(function($k){ return "$k=:$k"; }, array_keys($data)));
            $data['id'] = $hid;
            $db->prepare("UPDATE escuela_horarios SET $sets WHERE id=:id")->execute($data);
        } else {
            $c2 = implode(',', array_keys($data));
            $v2 = ':'.implode(',:', array_keys($data));
            $db->prepare("INSERT INTO escuela_horarios ($c2) VALUES ($v2)")->execute($data);
        }
        $success = 'Horario guardado.'; $tab = 'horarios';
    } catch (Exception $e) { $error = $e->getMessage(); }
}

// Guardar banner IA
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='save_banner') {
    if (!Auth::csrfVerify($_POST['csrf']??'')) die('CSRF');
    $db->prepare("UPDATE escuela_cursos SET banner_ia=? WHERE id=?")->execute([trim($_POST['banner_html']), $id]);
    $curso['banner_ia'] = trim($_POST['banner_html']);
    $success = 'Banner guardado.'; $tab = 'banner';
}

$modulos  = $db->query("SELECT * FROM escuela_modulos WHERE curso_id=$id AND activo=1 ORDER BY orden")->fetchAll();
$horarios = $db->query("
    SELECT h.*, e.nombre AS elem_nombre, e.stock_actual, k.nombre AS kit_nombre
    FROM escuela_horarios h
    LEFT JOIN elementos e ON e.id=h.elemento_id
    LEFT JOIN kits k ON k.id=h.kit_id
    WHERE h.curso_id=$id AND h.activo=1
    ORDER BY h.hora_inicio
")->fetchAll();

$elementos = $db->query("SELECT id,codigo,nombre,stock_actual FROM elementos WHERE activo=1 AND stock_actual>0 ORDER BY nombre")->fetchAll();
$kits      = $db->query("SELECT id,codigo,nombre FROM kits WHERE activo=1 ORDER BY nombre")->fetchAll();

$objArr = json_decode($curso['objetivos'] ?? '[]', true) ?: [];
$temArr = json_decode($curso['tematicas'] ?? '[]', true) ?: [];

$CATS   = ['robotica'=>['label'=>'Robotica','icon'=>'&#x1F916;','color'=>'#E3A600'],'programacion'=>['label'=>'Programacion','icon'=>'&#x1F4BB;','color'=>'#3776AB'],'videojuegos'=>['label'=>'Videojuegos','icon'=>'&#x1F3AE;','color'=>'#62B53E'],'impresion3d'=>['label'=>'Impresion 3D','icon'=>'&#x1F5A8;','color'=>'#FF6F00'],'electronica'=>['label'=>'Electronica','icon'=>'&#x26A1;','color'=>'#00979D'],'maker'=>['label'=>'Maker','icon'=>'&#x1F527;','color'=>'#7c3aed'],'otro'=>['label'=>'Otro','icon'=>'&#x1F4DA;','color'=>'#64748b']];
$dias   = ['2'=>'Lunes','3'=>'Martes','4'=>'Miercoles','5'=>'Jueves','6'=>'Viernes','7'=>'Sabado','1'=>'Domingo'];

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
.sc{background:#fff;border-radius:14px;border:1px solid #e2e8f0;padding:1rem 1.2rem;margin-bottom:1rem}
.tab-btn{padding:.45rem 1rem;border-radius:8px;border:1.5px solid #e2e8f0;background:#fff;font-size:.8rem;font-weight:600;cursor:pointer;color:#475569;text-decoration:none;transition:.15s}
.tab-btn.active{background:#1e293b;color:#fff;border-color:#1e293b}
.mod-card{border-left:4px solid var(--mc);background:#fff;border-radius:0 10px 10px 0;border:1px solid #e2e8f0;border-left:4px solid var(--mc);padding:.75rem 1rem;margin-bottom:.5rem}
.disp-disponible{background:#dcfce7;color:#166534}
.disp-casi_lleno{background:#fef9c3;color:#854d0e}
.disp-lleno{background:#fee2e2;color:#991b1b}
.cupo-bar{height:6px;border-radius:3px;background:#e2e8f0;overflow:hidden;margin-top:4px}
.cupo-fill{height:100%;border-radius:3px}
.objetivo-item{display:flex;align-items:flex-start;gap:.5rem;padding:.4rem 0;border-bottom:.5px solid #f1f5f9}
.objetivo-item:last-child{border-bottom:none}
.obj-icon{width:22px;height:22px;background:var(--cc);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:.7rem;flex-shrink:0;margin-top:1px}
</style>

<!-- Header con banner de color -->
<div style="background:linear-gradient(135deg,<?= $curso['color_primario'] ?>,<?= $curso['color_secundario'] ?? $curso['color_primario'] ?>);border-radius:16px;padding:1.5rem;margin-bottom:1rem;color:#fff">
  <div class="d-flex align-items-start justify-content-between">
    <div class="d-flex align-items-center gap-3">
      <a href="index.php" class="btn btn-sm" style="background:rgba(255,255,255,.2);color:#fff;border:none"><i class="bi bi-arrow-left"></i></a>
      <?php $cat = $CATS[$curso['categoria']] ?? $CATS['otro']; ?>
      <div>
        <div style="font-size:.75rem;opacity:.8"><?= $cat['icon'] ?> <?= $cat['label'] ?></div>
        <h3 class="fw-bold mb-0" style="font-size:1.4rem"><?= htmlspecialchars($curso['nombre']) ?></h3>
        <div style="font-size:.8rem;opacity:.8;margin-top:.2rem">
          <?= $curso['edad_min'] ?>-<?= $curso['edad_max'] ?> años
          &middot; <?= ucfirst($curso['nivel']) ?>
          &middot; <?= $curso['duracion_min'] ?> min/sesion
          &middot; <?= $curso['num_sesiones'] ?> sesiones
        </div>
      </div>
    </div>
    <div class="d-flex gap-2">
      <a href="form.php?id=<?= $id ?>" class="btn btn-sm" style="background:rgba(255,255,255,.2);color:#fff;border:none">
        <i class="bi bi-pencil me-1"></i>Editar
      </a>
    </div>
  </div>
</div>

<?php if ($error):   ?><div class="alert alert-danger  py-2 small"><?= $error   ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<!-- Tabs -->
<div class="d-flex gap-2 mb-3 flex-wrap">
  <a href="?id=<?= $id ?>&tab=info"     class="tab-btn <?= $tab==='info'?'active':'' ?>"><i class="bi bi-info-circle me-1"></i>Info</a>
  <a href="?id=<?= $id ?>&tab=modulos"  class="tab-btn <?= $tab==='modulos'?'active':'' ?>"><i class="bi bi-bookmark me-1"></i>Modulos (<?= count($modulos) ?>)</a>
  <a href="?id=<?= $id ?>&tab=horarios" class="tab-btn <?= $tab==='horarios'?'active':'' ?>"><i class="bi bi-clock me-1"></i>Horarios (<?= count($horarios) ?>)</a>
  <a href="?id=<?= $id ?>&tab=banner"   class="tab-btn <?= $tab==='banner'?'active':'' ?>">&#x1F916; Banner IA</a>
</div>

<!-- TAB: INFO -->
<?php if ($tab === 'info'): ?>
<div class="row g-3">
  <div class="col-md-7">
    <div class="sc">
      <h6 class="fw-bold mb-3">Descripcion</h6>
      <p style="font-size:.88rem;line-height:1.6"><?= nl2br(htmlspecialchars($curso['descripcion'] ?? '')) ?></p>
    </div>
    <?php if (!empty($objArr)): ?>
    <div class="sc">
      <h6 class="fw-bold mb-3"><i class="bi bi-check-circle me-2" style="color:<?= $curso['color_primario'] ?>"></i>Objetivos</h6>
      <?php foreach ($objArr as $obj): ?>
      <div class="objetivo-item" style="--cc:<?= $curso['color_primario'] ?>">
        <div class="obj-icon"><i class="bi bi-check" style="font-size:.75rem"></i></div>
        <span style="font-size:.85rem"><?= htmlspecialchars($obj) ?></span>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
  <div class="col-md-5">
    <?php if ($curso['imagen']): ?>
    <img src="<?= UPLOAD_URL.htmlspecialchars($curso['imagen']) ?>" class="w-100 rounded mb-3" style="max-height:200px;object-fit:cover">
    <?php endif; ?>
    <?php if (!empty($temArr)): ?>
    <div class="sc">
      <h6 class="fw-bold mb-3"><i class="bi bi-list-ul me-2" style="color:<?= $curso['color_primario'] ?>"></i>Tematicas</h6>
      <?php foreach ($temArr as $i => $t): ?>
      <div class="d-flex align-items-center gap-2 py-1 border-bottom" style="font-size:.82rem">
        <span class="badge rounded-pill" style="background:<?= $curso['color_primario'] ?>;font-size:.68rem;min-width:20px"><?= $i+1 ?></span>
        <?= htmlspecialchars($t) ?>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="sc">
      <h6 class="fw-bold mb-2">Precios</h6>
      <?php if ($curso['precio'] > 0): ?>
        <div class="d-flex justify-content-between py-1"><span class="text-muted small">Mensual</span><span class="fw-bold text-success">$<?= number_format($curso['precio'],0,',','.') ?></span></div>
      <?php endif; ?>
      <?php if ($curso['precio_semestral'] > 0): ?>
        <div class="d-flex justify-content-between py-1"><span class="text-muted small">Semestral</span><span class="fw-bold text-primary">$<?= number_format($curso['precio_semestral'],0,',','.') ?></span></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- TAB: MODULOS -->
<?php elseif ($tab === 'modulos'): ?>
<div class="row g-3">
  <div class="col-lg-7">
    <div class="sc">
      <h6 class="fw-bold mb-3">Modulos / Avances del curso</h6>
      <?php if (empty($modulos)): ?>
        <div class="text-center text-muted py-4"><i class="bi bi-bookmark fs-2 d-block mb-2"></i>Sin modulos. Agrega el primero.</div>
      <?php else: ?>
        <?php $totalSes = 0; foreach ($modulos as $m): $totalSes += $m['sesiones']; ?>
        <div class="mod-card" style="--mc:<?= $m['color'] ?>">
          <div class="d-flex align-items-center justify-content-between">
            <div class="d-flex align-items-center gap-2">
              <span class="badge" style="background:<?= $m['color'] ?>;min-width:24px"><?= $m['orden'] ?></span>
              <div>
                <div class="fw-bold" style="font-size:.85rem"><?= htmlspecialchars($m['titulo']) ?></div>
                <?php if ($m['descripcion']): ?>
                  <div class="text-muted" style="font-size:.75rem"><?= htmlspecialchars(mb_strimwidth($m['descripcion'],0,60,'...')) ?></div>
                <?php endif; ?>
              </div>
            </div>
            <div class="d-flex align-items-center gap-2">
              <span class="badge bg-light text-dark border" style="font-size:.68rem"><?= $m['sesiones'] ?> sesiones</span>
              <a href="?id=<?= $id ?>&tab=modulos&edit_mod=<?= $m['id'] ?>" class="btn btn-sm btn-outline-secondary py-0 px-1"><i class="bi bi-pencil"></i></a>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
        <div class="text-muted small text-end mt-1">Total: <?= $totalSes ?> sesiones de <?= $curso['num_sesiones'] ?> planificadas</div>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-lg-5">
    <?php $editMod = isset($_GET['edit_mod']) ? $db->query("SELECT * FROM escuela_modulos WHERE id=".(int)$_GET['edit_mod']." AND curso_id=$id")->fetch() : null; ?>
    <div class="sc">
      <h6 class="fw-bold mb-3"><?= $editMod ? 'Editar Modulo' : 'Nuevo Modulo' ?></h6>
      <form method="POST">
        <input type="hidden" name="action" value="save_modulo">
        <input type="hidden" name="csrf"   value="<?= Auth::csrfToken() ?>">
        <?php if ($editMod): ?><input type="hidden" name="mod_id" value="<?= $editMod['id'] ?>"><?php endif; ?>
        <div class="row g-2">
          <div class="col-12"><input type="text" name="titulo" class="form-control form-control-sm" required placeholder="Titulo del modulo *" value="<?= htmlspecialchars($editMod['titulo'] ?? '') ?>"></div>
          <div class="col-12"><textarea name="descripcion" class="form-control form-control-sm" rows="2" placeholder="Descripcion..."><?= htmlspecialchars($editMod['descripcion'] ?? '') ?></textarea></div>
          <div class="col-3"><input type="number" name="orden" class="form-control form-control-sm" placeholder="#" min="1" value="<?= $editMod['orden'] ?? count($modulos)+1 ?>"></div>
          <div class="col-4"><input type="number" name="sesiones" class="form-control form-control-sm" placeholder="Sesiones" min="1" value="<?= $editMod['sesiones'] ?? 2 ?>"></div>
          <div class="col-5 d-flex align-items-center gap-1">
            <label class="small mb-0">Color:</label>
            <input type="color" name="color" class="form-control form-control-color" style="width:40px;height:30px" value="<?= $editMod['color'] ?? $curso['color_primario'] ?>">
          </div>
          <div class="col-12 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm"><?= $editMod?'Guardar':'Agregar Modulo' ?></button>
            <?php if ($editMod): ?><a href="?id=<?= $id ?>&tab=modulos" class="btn btn-outline-secondary btn-sm">Cancelar</a><?php endif; ?>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- TAB: HORARIOS -->
<?php elseif ($tab === 'horarios'): ?>
<div class="row g-3">
  <div class="col-lg-7">
    <div class="sc">
      <h6 class="fw-bold mb-3">Horarios disponibles</h6>
      <?php if (empty($horarios)): ?>
        <div class="text-center text-muted py-4"><i class="bi bi-clock fs-2 d-block mb-2"></i>Sin horarios configurados</div>
      <?php else: ?>
        <?php foreach ($horarios as $h):
          $stock = $h['stock_actual'] ?? 999;
          $matriculados = $db->query("SELECT COUNT(*) FROM matriculas m JOIN escuela_grupos eg ON eg.id=m.grupo_id WHERE eg.id={$h['id']} AND m.estado IN ('activa','pendiente_pago')")->fetchColumn();
          $libres = max(0, $stock - $matriculados);
          $disp = $libres===0 ? 'lleno' : ($libres<=3 ? 'casi_lleno' : 'disponible');
          $pct  = $stock>0 ? min(100,round($matriculados/$stock*100)) : 100;
          $fc   = ['disponible'=>'#22c55e','casi_lleno'=>'#f59e0b','lleno'=>'#ef4444'][$disp]??'#94a3b8';
        ?>
        <div class="sc mb-2" style="padding:.75rem">
          <div class="d-flex align-items-start justify-content-between">
            <div>
              <div class="fw-bold"><?= $dias[$h['dia_semana']]??'Sab' ?> <?= substr($h['hora_inicio'],0,5) ?> - <?= substr($h['hora_fin'],0,5) ?></div>
              <?php if ($h['instructor']): ?><div class="text-muted small"><i class="bi bi-person me-1"></i><?= htmlspecialchars($h['instructor']) ?></div><?php endif; ?>
              <?php if ($h['elem_nombre']): ?>
              <div class="mt-1" style="font-size:.77rem">
                <i class="bi bi-cpu me-1 text-primary"></i>
                <strong><?= htmlspecialchars($h['elem_nombre']) ?></strong>
                <span class="text-muted ms-1">Stock: <?= $h['stock_actual'] ?> &rarr; <?= $matriculados ?> en uso &rarr; <span style="color:<?= $fc ?>;font-weight:700"><?= $libres ?> libres</span></span>
              </div>
              <?php endif; ?>
              <div class="cupo-bar mt-1" style="max-width:180px">
                <div class="cupo-fill" style="width:<?= $pct ?>%;background:<?= $fc ?>"></div>
              </div>
            </div>
            <div class="d-flex gap-1">
              <div class="text-end">
                <span class="badge disp-<?= $disp ?>" style="font-size:.7rem"><?= $libres ?> cupos libres</span>
                <div class="text-muted" style="font-size:.68rem">de <?= $h['cupo_max'] ?? $stock ?> max</div>
              </div>
              <a href="?id=<?= $id ?>&tab=horarios&edit_hor=<?= $h['id'] ?>" class="btn btn-sm btn-outline-secondary py-0 px-1"><i class="bi bi-pencil"></i></a>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-lg-5">
    <?php $editHor = isset($_GET['edit_hor']) ? $db->query("SELECT * FROM escuela_horarios WHERE id=".(int)$_GET['edit_hor']." AND curso_id=$id")->fetch() : null; ?>
    <div class="sc">
      <h6 class="fw-bold mb-3"><?= $editHor ? 'Editar Horario' : 'Nuevo Horario' ?></h6>
      <form method="POST">
        <input type="hidden" name="action" value="save_horario">
        <input type="hidden" name="csrf"   value="<?= Auth::csrfToken() ?>">
        <?php if ($editHor): ?><input type="hidden" name="hor_id" value="<?= $editHor['id'] ?>"><?php endif; ?>
        <div class="row g-2">
          <div class="col-4">
            <label class="form-label small">Dia</label>
            <select name="dia_semana" class="form-select form-select-sm">
              <?php foreach ($dias as $dv=>$dl): ?><option value="<?= $dv ?>" <?= ($editHor['dia_semana']??7)==$dv?'selected':'' ?>><?= $dl ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="col-4">
            <label class="form-label small">Inicio</label>
            <input type="time" name="hora_inicio" class="form-control form-control-sm" value="<?= $editHor['hora_inicio']??'08:00' ?>">
          </div>
          <div class="col-4">
            <label class="form-label small">Fin</label>
            <input type="time" name="hora_fin" class="form-control form-control-sm" value="<?= $editHor['hora_fin']??'10:00' ?>">
          </div>
          <div class="col-12">
            <label class="form-label small">Instructor</label>
            <input type="text" name="instructor" class="form-control form-control-sm" value="<?= htmlspecialchars($editHor['instructor'] ?? '') ?>">
          </div>
          <div class="col-12">
            <label class="form-label small">Periodo</label>
            <input type="text" name="periodo" class="form-control form-control-sm" placeholder="2025-1" value="<?= htmlspecialchars($editHor['periodo'] ?? '') ?>">
          </div>
          <div class="col-12">
            <label class="form-label small fw-semibold">
              Recurso del inventario <span class="text-muted fw-normal">(define cupos automaticamente)</span>
            </label>
            <select name="elemento_id" class="form-select form-select-sm mb-1" onchange="if(this.value){document.querySelector('[name=kit_id]').value='';actualizarCupo(this)}">
              <option value="">-- Elemento (computadores, kits...) --</option>
              <?php foreach ($elementos as $e): ?>
                <option value="<?= $e['id'] ?>" data-stock="<?= $e['stock_actual'] ?>"
                  <?= ($editHor['elemento_id']??0)==$e['id']?'selected':'' ?>>
                  <?= htmlspecialchars($e['codigo'].' - '.$e['nombre']) ?> (<?= $e['stock_actual'] ?> uds)
                </option>
              <?php endforeach; ?>
            </select>
            <select name="kit_id" class="form-select form-select-sm mb-1" onchange="if(this.value)document.querySelector('[name=elemento_id]').value=''">
              <option value="">-- O un kit especifico --</option>
              <?php foreach ($kits as $k): ?>
                <option value="<?= $k['id'] ?>" <?= ($editHor['kit_id']??0)==$k['id']?'selected':'' ?>><?= htmlspecialchars($k['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label small fw-semibold">
              Cupo m&aacute;ximo
              <span class="text-muted fw-normal">(se calcula del inventario, o ingresa manualmente)</span>
            </label>
            <div class="input-group input-group-sm">
              <span class="input-group-text"><i class="bi bi-people"></i></span>
              <input type="number" name="cupo_max" id="inputCupoMax" class="form-control" min="1" max="50"
                     value="<?= $editHor['cupo_max'] ?? 15 ?>"
                     placeholder="Ej: 10">
              <span class="input-group-text text-muted" style="font-size:.72rem" id="cupoHint">estudiantes</span>
            </div>
            <div class="form-text">Si tienes 10 computadores seleccionados arriba, se llena automaticamente.</div>
          </div>
          <div class="col-12 d-flex gap-2">
            <button type="submit" class="btn btn-primary btn-sm"><?= $editHor?'Guardar':'Agregar Horario' ?></button>
            <?php if ($editHor): ?><a href="?id=<?= $id ?>&tab=horarios" class="btn btn-outline-secondary btn-sm">Cancelar</a><?php endif; ?>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- TAB: BANNER IA -->
<?php elseif ($tab === 'banner'): ?>
<div class="row g-3">
  <div class="col-lg-6">
    <div class="sc">
      <div class="d-flex align-items-center gap-2 mb-3">
        <h6 class="fw-bold mb-0">&#x1F916; Generar Banner con IA</h6>
        <span class="badge bg-primary" style="font-size:.65rem">Claude AI</span>
      </div>
      <p class="text-muted small">El agente de IA generara un banner HTML promocional para este curso basado en su descripcion, objetivos y tematicas.</p>
      <div class="mb-3">
        <label class="form-label small fw-semibold">Estilo del banner</label>
        <select id="estiloBanner" class="form-select form-select-sm">
          <option value="moderno">Moderno y colorido</option>
          <option value="minimalista">Minimalista y elegante</option>
          <option value="divertido">Divertido para ninos</option>
          <option value="profesional">Profesional para padres</option>
        </select>
      </div>
      <button class="btn btn-primary fw-bold w-100" id="btnGenerar" onclick="generarBanner()">
        <i class="bi bi-magic me-1"></i>Generar Banner con IA
      </button>
      <div id="loadingBanner" class="text-center py-3 d-none">
        <div class="spinner-border text-primary" role="status"></div>
        <div class="text-muted small mt-2">Generando banner...</div>
      </div>
    </div>
    <div class="sc">
      <h6 class="fw-bold mb-2">O pegar HTML personalizado</h6>
      <form method="POST">
        <input type="hidden" name="action" value="save_banner">
        <input type="hidden" name="csrf"   value="<?= Auth::csrfToken() ?>">
        <textarea name="banner_html" id="bannerHtml" class="form-control form-control-sm" rows="8"
                  placeholder="Pega aqui el HTML del banner..."><?= htmlspecialchars($curso['banner_ia'] ?? '') ?></textarea>
        <button type="submit" class="btn btn-success btn-sm mt-2 w-100">
          <i class="bi bi-save me-1"></i>Guardar Banner
        </button>
      </form>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="sc">
      <h6 class="fw-bold mb-3">Vista previa del banner</h6>
      <div id="bannerPreview" style="min-height:200px;border:2px dashed #e2e8f0;border-radius:10px;overflow:hidden">
        <?php if ($curso['banner_ia']): ?>
          <?= $curso['banner_ia'] ?>
        <?php else: ?>
          <div class="d-flex align-items-center justify-content-center h-100 text-muted" style="min-height:200px">
            <div class="text-center"><div style="font-size:2rem">&#x1F916;</div><div class="small">Genera un banner para verlo aqui</div></div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<script>
function actualizarCupo(select) {
    var opt = select.options[select.selectedIndex];
    var stock = opt ? opt.getAttribute('data-stock') : null;
    if (stock && parseInt(stock) > 0) {
        document.getElementById('inputCupoMax').value = stock;
        document.getElementById('cupoHint').textContent = 'del inventario';
        document.getElementById('cupoHint').style.color = '#16a34a';
    }
}

async function generarBanner() {
    const btn     = document.getElementById('btnGenerar');
    const loading = document.getElementById('loadingBanner');
    const preview = document.getElementById('bannerPreview');
    const htmlArea= document.getElementById('bannerHtml');
    const estilo  = document.getElementById('estiloBanner').value;

    btn.disabled = true;
    loading.classList.remove('d-none');

    const cursoData = {
        nombre:      <?= json_encode($curso['nombre']) ?>,
        descripcion: <?= json_encode($curso['descripcion'] ?? '') ?>,
        objetivos:   <?= json_encode($objArr) ?>,
        tematicas:   <?= json_encode($temArr) ?>,
        colorPrimario: <?= json_encode($curso['color_primario']) ?>,
        colorSecundario: <?= json_encode($curso['color_secundario'] ?? $curso['color_primario']) ?>,
        edadMin: <?= (int)$curso['edad_min'] ?>,
        edadMax: <?= (int)$curso['edad_max'] ?>,
        nivel: <?= json_encode($curso['nivel']) ?>,
        precio: <?= (float)$curso['precio'] ?>,
        categoria: <?= json_encode($curso['categoria']) ?>,
        duracion: <?= (int)$curso['duracion_min'] ?>,
        sesiones: <?= (int)$curso['num_sesiones'] ?>,
    };

    const prompt = `Genera un banner HTML promocional para el siguiente curso de ROBOTSchool Colombia.

CURSO: ${cursoData.nombre}
DESCRIPCION: ${cursoData.descripcion}
CATEGORIA: ${cursoData.categoria}
NIVEL: ${cursoData.nivel}
EDADES: ${cursoData.edadMin} a ${cursoData.edadMax} años
DURACION: ${cursoData.duracion} minutos por sesion, ${cursoData.sesiones} sesiones
PRECIO: $${cursoData.precio.toLocaleString('es-CO')} COP/mes
OBJETIVOS: ${cursoData.objetivos.join(', ')}
TEMATICAS: ${cursoData.tematicas.join(', ')}
COLOR PRIMARIO: ${cursoData.colorPrimario}
COLOR SECUNDARIO: ${cursoData.colorSecundario}
ESTILO: ${estilo}

Genera un banner HTML completo y atractivo. Requisitos:
- Ancho 100%, altura aprox 300-400px
- Usa SOLO estilos inline (no <style> externo)
- Incluye: nombre del curso, descripcion corta, edades, precio, 3-4 objetivos o tematicas destacadas
- Usa los colores del curso
- Emojis relacionados con la tecnologia/robotica
- Estilo ${estilo === 'divertido' ? 'colorido y alegre para ninos' : estilo === 'profesional' ? 'serio y confiable para padres' : estilo === 'minimalista' ? 'limpio y elegante' : 'moderno con gradientes'}
- SOLO retorna el HTML, sin explicaciones, sin \`\`\`html, sin markdown`;

    try {
        const response = await fetch("generar_banner.php", {
            method: "POST",
            headers: {"Content-Type": "application/json"},
            body: JSON.stringify({ prompt: prompt, curso_id: <?= $id ?> })
        });
        const data = await response.json();
        if (data.error) throw new Error(data.error);
        const cleanHtml = (data.html || '').replace(/```html|```/g,'').trim();
        preview.innerHTML  = cleanHtml;
        htmlArea.value     = cleanHtml;
    } catch(e) {
        preview.innerHTML = '<div class="alert alert-danger m-2">Error: '+e.message+'</div>';
    }

    btn.disabled = false;
    loading.classList.add('d-none');
}
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
