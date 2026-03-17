<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();

$db         = Database::get();
$pageTitle  = 'Cursos Escuela';
$activeMenu = 'cursos';
$error = $success = '';

// Verificar si la tabla existe
$tablaExiste = $db->query("SHOW TABLES LIKE 'escuela_cursos'")->fetchColumn();

// Eliminar curso
if ($tablaExiste && isset($_GET['del']) && Auth::csrfVerify($_GET['csrf'] ?? '')) {
    $delId = (int)$_GET['del'];
    try {
        $db->prepare("UPDATE escuela_cursos SET activo=0 WHERE id=?")->execute([$delId]);
        $success = 'Curso eliminado correctamente.';
    } catch (Exception $e) { $error = $e->getMessage(); }
}

$fCat   = $_GET['cat']   ?? '';
$fNivel = $_GET['nivel'] ?? '';
$buscar = trim($_GET['q'] ?? '');

$where  = ["c.activo=1"];
if ($fCat)   $where[] = "c.categoria=".$db->quote($fCat);
if ($fNivel) $where[] = "c.nivel=".$db->quote($fNivel);
if ($buscar) $where[] = "(c.nombre LIKE ".$db->quote('%'.$buscar.'%')." OR c.descripcion LIKE ".$db->quote('%'.$buscar.'%').")";
$whereStr = implode(' AND ', $where);

if (!$tablaExiste) { $cursos = []; } else
$cursos = $db->query("
    SELECT c.*,
      (SELECT COUNT(*) FROM escuela_horarios h WHERE h.curso_id=c.id AND h.activo=1) AS num_horarios,
      (SELECT COUNT(*) FROM escuela_modulos  m WHERE m.curso_id=c.id AND m.activo=1) AS num_modulos
    FROM escuela_cursos c
    WHERE $whereStr
    ORDER BY c.destacado DESC, c.nombre ASC
")->fetchAll();

$CATS = [
    'robotica'     => ['label'=>'Rob&oacute;tica',   'icon'=>'&#x1F916;', 'color'=>'#E3A600'],
    'programacion' => ['label'=>'Programaci&oacute;n','icon'=>'&#x1F4BB;','color'=>'#3776AB'],
    'videojuegos'  => ['label'=>'Videojuegos',        'icon'=>'&#x1F3AE;','color'=>'#62B53E'],
    'impresion3d'  => ['label'=>'Impresi&oacute;n 3D','icon'=>'&#x1F5A8;','color'=>'#FF6F00'],
    'electronica'  => ['label'=>'Electr&oacute;nica', 'icon'=>'&#x26A1;', 'color'=>'#00979D'],
    'maker'        => ['label'=>'Maker',               'icon'=>'&#x1F527;','color'=>'#7c3aed'],
    'otro'         => ['label'=>'Otro',                'icon'=>'&#x1F4DA;','color'=>'#64748b'],
];

$NIVELES = ['inicial'=>'Inicial','basico'=>'B&aacute;sico','intermedio'=>'Intermedio','avanzado'=>'Avanzado'];

// Contar por categoría
$conteos = [];
foreach ($db->query("SELECT categoria, COUNT(*) cnt FROM escuela_cursos WHERE activo=1 GROUP BY categoria")->fetchAll() as $r) {
    $conteos[$r['categoria']] = $r['cnt'];
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
.sc{background:#fff;border-radius:14px;border:1px solid #e2e8f0;padding:1rem 1.2rem;margin-bottom:1rem}
.curso-card{background:#fff;border-radius:16px;overflow:hidden;border:1.5px solid #e2e8f0;transition:all .2s;cursor:pointer}
.curso-card:hover{transform:translateY(-4px);box-shadow:0 8px 24px rgba(0,0,0,.1);border-color:transparent}
.curso-img{width:100%;height:160px;object-fit:cover}
.curso-img-ph{width:100%;height:160px;display:flex;align-items:center;justify-content:center;font-size:3.5rem}
.cat-pill{font-size:.68rem;padding:.2rem .6rem;border-radius:20px;font-weight:700}
.nivel-pill{font-size:.65rem;padding:.15rem .5rem;border-radius:20px;font-weight:700;background:#f1f5f9;color:#475569}
.cat-btn{padding:.4rem .85rem;border-radius:20px;border:1.5px solid #e2e8f0;background:#fff;font-size:.78rem;cursor:pointer;font-weight:600;transition:.15s;text-decoration:none;color:#374151;display:inline-flex;align-items:center;gap:.35rem}
.cat-btn:hover,.cat-btn.active{border-color:var(--cc);background:var(--cc);color:#fff}
.horario-pill{display:inline-flex;align-items:center;gap:.3rem;background:#f0f4ff;color:#185FA5;border-radius:20px;padding:.18rem .55rem;font-size:.7rem;font-weight:600}
.btn-accion{font-size:.75rem;padding:.3rem .6rem;border-radius:7px}
.destacado-badge{position:absolute;top:10px;right:10px;background:#f59e0b;color:#fff;font-size:.65rem;padding:.2rem .5rem;border-radius:20px;font-weight:700}
</style>

<!-- Header -->
<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h4 class="fw-bold mb-0">&#x1F393; Cursos ROBOTSchool</h4>
    <p class="text-muted small mb-0"><?= count($cursos) ?> curso(s) disponibles</p>
  </div>
  <div class="d-flex gap-2">
    <a href="horarios.php" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-clock me-1"></i>Horarios
    </a>
    <a href="form.php" class="btn btn-primary btn-sm fw-bold">
      <i class="bi bi-plus-lg me-1"></i>Nuevo Curso
    </a>
  </div>
</div>

<?php if ($error):   ?><div class="alert alert-danger  py-2 small"><?= htmlspecialchars($error)   ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if (!$tablaExiste): ?>
<div class="alert alert-warning small"><i class="bi bi-exclamation-triangle me-2"></i>Ejecuta <strong>migration_v3.2_cursos_escuela.sql</strong> en phpMyAdmin para activar este m&oacute;dulo.</div>
<?php endif; ?>

<!-- Filtros por categoría -->
<div class="d-flex gap-2 flex-wrap mb-3">
  <a href="?" class="cat-btn <?= !$fCat?'active':'' ?>" style="--cc:#1e293b">
    Todos <span class="badge bg-secondary ms-1" style="font-size:.65rem"><?= array_sum($conteos) ?></span>
  </a>
  <?php foreach ($CATS as $ck => $cv): ?>
  <a href="?cat=<?= $ck ?><?= $buscar?"&q=$buscar":'' ?>"
     class="cat-btn <?= $fCat===$ck?'active':'' ?>" style="--cc:<?= $cv['color'] ?>">
    <?= $cv['icon'] ?> <?= $cv['label'] ?>
    <?php if (isset($conteos[$ck])): ?>
      <span class="badge ms-1" style="font-size:.6rem;background:rgba(255,255,255,.3)"><?= $conteos[$ck] ?></span>
    <?php endif; ?>
  </a>
  <?php endforeach; ?>
</div>

<!-- Buscador -->
<div class="sc mb-3">
  <form method="GET" class="d-flex gap-2">
    <input type="hidden" name="cat" value="<?= htmlspecialchars($fCat) ?>">
    <input type="text" name="q" class="form-control form-control-sm flex-grow-1"
           placeholder="&#128269; Buscar curso..."
           value="<?= htmlspecialchars($buscar) ?>">
    <select name="nivel" class="form-select form-select-sm" style="max-width:150px">
      <option value="">Todos los niveles</option>
      <?php foreach ($NIVELES as $nk => $nv): ?>
        <option value="<?= $nk ?>" <?= $fNivel===$nk?'selected':'' ?>><?= $nv ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Buscar</button>
    <?php if ($buscar||$fNivel): ?><a href="?cat=<?= $fCat ?>" class="btn btn-outline-secondary btn-sm">Limpiar</a><?php endif; ?>
  </form>
</div>

<!-- Grid de cursos -->
<?php if (empty($cursos)): ?>
<div class="sc text-center py-5">
  <div style="font-size:3rem">&#x1F393;</div>
  <h5 class="fw-bold mt-2">No hay cursos aun</h5>
  <p class="text-muted">Crea el primer curso para ROBOTSchool</p>
  <a href="form.php" class="btn btn-primary"><i class="bi bi-plus-lg me-1"></i>Crear Curso</a>
</div>
<?php else: ?>
<div class="row g-3">
<?php foreach ($cursos as $c):
  $cat = $CATS[$c['categoria']] ?? $CATS['otro'];
  $diasLabel = ['1'=>'Dom','2'=>'Lun','3'=>'Mar','4'=>'Mie','5'=>'Jue','6'=>'Vie','7'=>'Sab'];
?>
<div class="col-md-6 col-xl-4">
  <div class="curso-card position-relative h-100" onclick="window.location='ver.php?id=<?= $c['id'] ?>'">

    <?php if ($c['destacado']): ?>
      <div class="destacado-badge">&#11088; Destacado</div>
    <?php endif; ?>

    <!-- Imagen -->
    <?php if ($c['imagen']): ?>
      <img src="<?= htmlspecialchars(fotoUrl($c['imagen'])) ?>" class="curso-img" alt="">
    <?php else: ?>
      <div class="curso-img-ph" style="background:<?= $cat['color'] ?>15"><?= $cat['icon'] ?></div>
    <?php endif; ?>

    <!-- Barra de color -->
    <div style="height:4px;background:<?= $c['color_primario'] ?>"></div>

    <div class="p-3">
      <!-- Cat + nivel -->
      <div class="d-flex gap-1 mb-2 flex-wrap">
        <span class="cat-pill" style="background:<?= $cat['color'] ?>20;color:<?= $cat['color'] ?>"><?= $cat['icon'] ?> <?= $cat['label'] ?></span>
        <span class="nivel-pill"><?= $NIVELES[$c['nivel']] ?? ucfirst($c['nivel']) ?></span>
        <span class="nivel-pill"><?= $c['edad_min'] ?>-<?= $c['edad_max'] ?> años</span>
      </div>

      <!-- Nombre -->
      <h5 class="fw-bold mb-1" style="font-size:1rem;color:<?= $c['color_primario'] ?>"><?= htmlspecialchars($c['nombre']) ?></h5>

      <!-- Descripcion -->
      <p class="text-muted small mb-2" style="font-size:.78rem;line-height:1.4">
        <?= htmlspecialchars(mb_strimwidth($c['descripcion'] ?? '', 0, 90, '...')) ?>
      </p>

      <!-- Horarios disponibles -->
      <?php if ($c['num_horarios'] > 0): ?>
      <div class="d-flex gap-1 flex-wrap mb-2">
        <?php
        $hors = $db->query("SELECT hora_inicio,hora_fin FROM escuela_horarios WHERE curso_id={$c['id']} AND activo=1 ORDER BY hora_inicio")->fetchAll();
        foreach ($hors as $h):
        ?>
        <span class="horario-pill">
          <i class="bi bi-clock" style="font-size:.6rem"></i>
          <?= substr($h['hora_inicio'],0,5) ?>-<?= substr($h['hora_fin'],0,5) ?>
        </span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Footer card -->
      <div class="d-flex align-items-center justify-content-between mt-2 pt-2 border-top">
        <div>
          <?php if ($c['precio'] > 0): ?>
            <div class="fw-bold text-success" style="font-size:.9rem">$<?= number_format($c['precio'],0,',','.') ?></div>
            <div class="text-muted" style="font-size:.68rem">por mes</div>
          <?php endif; ?>
        </div>
        <div class="d-flex gap-1">
          <span class="horario-pill">
            <i class="bi bi-book" style="font-size:.6rem"></i>
            <?= $c['num_modulos'] ?> m&oacute;dulos
          </span>
          <?php if (isset($c['cupo_max']) && $c['cupo_max'] > 0): ?>
          <span class="horario-pill" style="background:#f0fdf4;color:#166534">
            <i class="bi bi-people" style="font-size:.6rem"></i>
            <?= $c['cupo_max'] ?> cupos
          </span>
          <?php endif; ?>
          <button onclick="event.stopPropagation();window.location='form.php?id=<?= $c['id'] ?>'"
                  class="btn btn-outline-secondary btn-accion" title="Editar">
            <i class="bi bi-pencil"></i>
          </button>
          <button onclick="event.stopPropagation();window.location='ver.php?id=<?= $c['id'] ?>&tab=banner'"
                  class="btn btn-outline-warning btn-accion" title="Banner IA">
            &#x1F916;
          </button>
          <?php if (Auth::isAdmin() || Auth::puede('cursos','eliminar')): ?>
          <button onclick="event.stopPropagation();if(confirm('Eliminar el curso <?= addslashes($c['nombre']) ?>?'))window.location='?del=<?= $c['id'] ?>&csrf=<?= Auth::csrfToken() ?>'"
                  class="btn btn-outline-danger btn-accion" title="Eliminar curso">
            <i class="bi bi-trash"></i>
          </button>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
