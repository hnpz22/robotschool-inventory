<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();
Auth::requireRol('comercial','gerencia','administracion');

$db         = Database::get();
$pageTitle  = 'Requerimientos Comerciales';
$activeMenu = 'comercial';
$error = $success = '';
$userRol = Auth::getRol();
$userId  = Auth::user()['id'];

// ── Verificar tablas ──────────────────────────────────────────
$tablaConvenios = $db->query("SHOW TABLES LIKE 'convenios'")->fetchColumn();
if (!$tablaConvenios) {
    require_once dirname(__DIR__, 2) . '/includes/header.php';
    echo '<div class="alert alert-warning m-4">
        <i class="bi bi-exclamation-triangle me-2"></i>
        Las tablas del módulo comercial no existen aún.<br>
        Ejecuta <strong>crear_tablas_comercial.sql</strong> en phpMyAdmin.
    </div>';
    require_once dirname(__DIR__, 2) . '/includes/footer.php';
    exit;
}

$tablaHistorial = $db->query("SHOW TABLES LIKE 'convenio_historial'")->fetchColumn();
$tablaCursos    = $db->query("SHOW TABLES LIKE 'convenio_cursos'")->fetchColumn();

// ── Eliminar ──────────────────────────────────────────────────
if (isset($_GET['del']) && Auth::csrfVerify($_GET['csrf'] ?? '')) {
    Auth::requireRol('gerencia');
    $db->prepare("UPDATE convenios SET activo=0 WHERE id=?")->execute([(int)$_GET['del']]);
    $success = 'Requerimiento eliminado.';
}

// ── Aprobar / Rechazar ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && in_array($_POST['action']??'', ['aprobar','rechazar'])) {
    if (!Auth::csrfVerify($_POST['csrf'] ?? '')) die('CSRF');
    Auth::requireRol('gerencia');
    $cid    = (int)$_POST['convenio_id'];
    $accion = $_POST['action'];
    $motivo = trim($_POST['motivo'] ?? '');
    $estado = $accion === 'aprobar' ? 'aprobado' : 'rechazado';
    $db->prepare("UPDATE convenios SET estado=?,aprobado_por=?,fecha_aprobacion=NOW(),motivo_rechazo=? WHERE id=?")
       ->execute([$estado, $userId, $motivo ?: null, $cid]);
    if ($tablaHistorial) {
        $db->prepare("INSERT INTO convenio_historial (convenio_id,estado,usuario_id,comentario) VALUES (?,?,?,?)")
           ->execute([$cid, $estado, $userId, $motivo ?: null]);
    }

    // ── AUTO-ENVÍO A PRODUCCIÓN al aprobar ──────────────────────
    if ($estado === 'aprobado') {
        $conv = $db->query("SELECT * FROM convenios WHERE id=$cid")->fetch();
        if ($conv) {
            // Calcular prioridad según vigencia
            $prioridad = 3; // Normal
            if ($conv['vigencia_inicio']) {
                $dias = (new DateTime($conv['vigencia_inicio']))->diff(new DateTime('today'))->days;
                if ($dias <= 7)  $prioridad = 1; // Urgente
                elseif ($dias <= 15) $prioridad = 2; // Alta
            }

            // Crear una solicitud por cada grupo de cursos
            $cursos = $tablaCursos
                ? $db->query("SELECT * FROM convenio_cursos WHERE convenio_id=$cid")->fetchAll()
                : [];

            if (!empty($cursos)) {
                foreach ($cursos as $curso) {
                    crearSolicitudProduccion($db, [
                        'fuente'       => 'comercial',
                        'titulo'       => 'Convenio: '.$conv['nombre_colegio'].' — '.$curso['nombre_curso'],
                        'tipo'         => 'armar_kit',
                        'kit_nombre'   => $curso['nombre_kit'] ?: $curso['nombre_curso'],
                        'cantidad'     => $curso['num_estudiantes'] ?: 1,
                        'prioridad'    => $prioridad,
                        'fecha_limite' => $conv['vigencia_inicio'],
                        'usuario_id'   => $userId,
                        'convenio_id'  => $cid,
                        'colegio_id'   => $conv['colegio_id'],
                        'notas'        => 'Convenio '.$conv['codigo'].' aprobado por gerencia',
                        'historial_nota'=> 'Generado automáticamente al aprobar convenio '.$conv['codigo'],
                    ]);
                }
                $numSols = count($cursos);
            } else {
                // Sin cursos detallados → una sola solicitud global
                $sid = crearSolicitudProduccion($db, [
                    'fuente'       => 'comercial',
                    'titulo'       => 'Convenio: '.$conv['nombre_colegio'],
                    'tipo'         => 'armar_kit',
                    'kit_nombre'   => $conv['nombre_colegio'],
                    'cantidad'     => 1,
                    'prioridad'    => $prioridad,
                    'fecha_limite' => $conv['vigencia_inicio'],
                    'usuario_id'   => $userId,
                    'convenio_id'  => $cid,
                    'colegio_id'   => $conv['colegio_id'],
                    'notas'        => 'Convenio '.$conv['codigo'].' aprobado',
                    'historial_nota'=> 'Generado automáticamente al aprobar convenio '.$conv['codigo'],
                ]);
                $numSols = $sid ? 1 : 0;
            }

            // Cambiar estado convenio a en_produccion
            $db->prepare("UPDATE convenios SET estado='en_produccion' WHERE id=?")->execute([$cid]);
            if ($numSols > 0) {
                $success = "Convenio aprobado. Se crearon <strong>$numSols solicitud(es)</strong> en el tablero de producción. "
                         . "<a href='".APP_URL."/modules/produccion/' class='alert-link'>Ver tablero →</a>";
            } else {
                $success = 'Convenio aprobado correctamente.';
            }
        }
    } else {
        $success = 'Requerimiento rechazado.';
    }
}

// ── Filtros ───────────────────────────────────────────────────
$fEstado = trim($_GET['estado'] ?? '');
$fQ      = trim($_GET['q']     ?? '');

$where  = ["c.activo=1"];
$params = [];
if ($userRol === 'comercial') { $where[] = "c.comercial_id=?"; $params[] = $userId; }
if ($fEstado)  { $where[] = "c.estado=?";  $params[] = $fEstado; }
if ($fQ)       { $where[] = "(c.nombre_colegio LIKE ? OR c.codigo LIKE ?)"; $params[] = "%$fQ%"; $params[] = "%$fQ%"; }
$whereStr = implode(' AND ', $where);

// ── Query principal ───────────────────────────────────────────
$selCursos = $tablaCursos
    ? "(SELECT COUNT(*) FROM convenio_cursos cc WHERE cc.convenio_id=c.id) AS num_cursos,
       (SELECT SUM(cc.num_estudiantes) FROM convenio_cursos cc WHERE cc.convenio_id=c.id) AS total_est,"
    : "0 AS num_cursos, 0 AS total_est,";

$st = $db->prepare("
    SELECT c.*,
      $selCursos
      u.nombre AS nombre_user
    FROM convenios c
    LEFT JOIN usuarios u ON u.id=c.comercial_id
    WHERE $whereStr
    ORDER BY c.created_at DESC
");
$st->execute($params);
$convenios = $st->fetchAll();

// ── Contadores por estado ─────────────────────────────────────
$contBase = $userRol === 'comercial' ? "WHERE activo=1 AND comercial_id=$userId" : "WHERE activo=1";
$contadores = [];
foreach (['borrador','pendiente_aprobacion','aprobado','rechazado','en_produccion','entregado'] as $est) {
    $contadores[$est] = (int)$db->query(
        "SELECT COUNT(*) FROM convenios $contBase AND estado='$est'"
    )->fetchColumn();
}

$ESTADOS = [
    'borrador'              => ['label'=>'Borrador',             'color'=>'#64748b', 'bg'=>'#f1f5f9', 'icon'=>'bi-pencil'],
    'pendiente_aprobacion'  => ['label'=>'Pendiente aprobación', 'color'=>'#d97706', 'bg'=>'#fef9c3', 'icon'=>'bi-hourglass-split'],
    'aprobado'              => ['label'=>'Aprobado',             'color'=>'#16a34a', 'bg'=>'#dcfce7', 'icon'=>'bi-check-circle'],
    'rechazado'             => ['label'=>'Rechazado',            'color'=>'#dc2626', 'bg'=>'#fee2e2', 'icon'=>'bi-x-circle'],
    'en_produccion'         => ['label'=>'En producción',        'color'=>'#7c3aed', 'bg'=>'#f5f3ff', 'icon'=>'bi-tools'],
    'entregado'             => ['label'=>'Entregado',            'color'=>'#0891b2', 'bg'=>'#e0f2fe', 'icon'=>'bi-check2-all'],
];

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-briefcase me-2"></i>Requerimientos Comerciales</h4>
    <p class="text-muted small mb-0"><?= count($convenios) ?> requerimiento(s)</p>
  </div>
  <a href="<?= APP_URL ?>/modules/comercial/form.php" class="btn btn-primary fw-bold">
    <i class="bi bi-plus-lg me-2"></i>Nuevo Requerimiento
  </a>
</div>

<?php if ($error):   ?><div class="alert alert-danger  alert-dismissible fade show py-2"><i class="bi bi-x-circle me-2"></i><?= htmlspecialchars($error)   ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success alert-dismissible fade show py-2"><i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

<!-- Filtros por estado (tabs) -->
<div class="section-card mb-3 py-2 px-3">
  <div class="d-flex gap-2 flex-wrap align-items-center">
    <a href="?" class="btn btn-sm <?= !$fEstado ? 'btn-dark' : 'btn-outline-secondary' ?>">
      Todos <span class="badge bg-secondary ms-1"><?= array_sum($contadores) ?></span>
    </a>
    <?php foreach ($ESTADOS as $ek => $ev): if (!$contadores[$ek] && $fEstado !== $ek) continue; ?>
    <a href="?estado=<?= $ek ?><?= $fQ?"&q=".urlencode($fQ):'' ?>"
       class="btn btn-sm <?= $fEstado===$ek ? 'btn-primary' : 'btn-outline-secondary' ?>"
       style="<?= $fEstado===$ek ? "background:{$ev['color']};border-color:{$ev['color']};color:#fff" : '' ?>">
      <i class="bi <?= $ev['icon'] ?> me-1"></i><?= $ev['label'] ?>
      <?php if ($contadores[$ek]): ?>
        <span class="badge ms-1" style="background:rgba(0,0,0,.15)"><?= $contadores[$ek] ?></span>
      <?php endif; ?>
    </a>
    <?php endforeach; ?>

    <!-- Buscador rápido -->
    <form method="GET" class="ms-auto d-flex gap-1">
      <?php if ($fEstado): ?><input type="hidden" name="estado" value="<?= htmlspecialchars($fEstado) ?>"><?php endif; ?>
      <input type="text" name="q" class="form-control form-control-sm" placeholder="Buscar colegio..."
             value="<?= htmlspecialchars($fQ) ?>" style="width:180px">
      <button type="submit" class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
      <?php if ($fQ): ?><a href="?<?= $fEstado?"estado=$fEstado":'' ?>" class="btn btn-sm btn-outline-secondary">&#x2715;</a><?php endif; ?>
    </form>
  </div>
</div>

<!-- Lista vacía -->
<?php if (empty($convenios)): ?>
<div class="section-card text-center py-5">
  <i class="bi bi-briefcase fs-1 text-muted opacity-25 d-block mb-3"></i>
  <h5 class="fw-bold">Sin requerimientos<?= $fEstado ? ' en este estado' : '' ?></h5>
  <p class="text-muted">Crea el primer requerimiento comercial.</p>
  <a href="<?= APP_URL ?>/modules/comercial/form.php" class="btn btn-primary">
    <i class="bi bi-plus-lg me-2"></i>Crear Requerimiento
  </a>
</div>
<?php else: ?>

<!-- Grid de tarjetas -->
<div class="row g-3">
<?php foreach ($convenios as $c):
  $es = $ESTADOS[$c['estado']] ?? $ESTADOS['borrador'];
  $numCursos = (int)($c['num_cursos'] ?? 0);
  $totalEst  = (int)($c['total_est']  ?? 0);
?>
<div class="col-md-6 col-xl-4">
  <div class="card h-100 shadow-sm border-0" style="border-radius:14px;overflow:hidden">

    <!-- Barra de color según estado -->
    <div style="height:4px;background:<?= $es['color'] ?>"></div>

    <div class="card-body pb-2">
      <!-- Header tarjeta -->
      <div class="d-flex align-items-start justify-content-between mb-2">
        <div>
          <code class="text-primary" style="font-size:.75rem"><?= htmlspecialchars($c['codigo'] ?? 'Sin código') ?></code>
          <div class="fw-bold mt-1" style="font-size:.95rem;line-height:1.3">
            <?= htmlspecialchars($c['nombre_colegio']) ?>
          </div>
        </div>
        <span class="badge" style="background:<?= $es['bg'] ?>;color:<?= $es['color'] ?>;font-size:.7rem;white-space:nowrap">
          <i class="bi <?= $es['icon'] ?> me-1"></i><?= $es['label'] ?>
        </span>
      </div>

      <!-- Datos -->
      <div class="row g-1 text-muted" style="font-size:.78rem">
        <?php if ($c['fecha_convenio']): ?>
        <div class="col-6">
          <i class="bi bi-calendar me-1"></i><?= date('d/m/Y', strtotime($c['fecha_convenio'])) ?>
        </div>
        <?php endif; ?>
        <?php if ($c['vigencia_fin']): ?>
        <div class="col-6">
          <i class="bi bi-calendar-check me-1"></i>Hasta <?= date('d/m/Y', strtotime($c['vigencia_fin'])) ?>
        </div>
        <?php endif; ?>
        <?php if ($numCursos): ?>
        <div class="col-6">
          <i class="bi bi-collection-play me-1"></i><?= $numCursos ?> curso(s)
        </div>
        <?php endif; ?>
        <?php if ($totalEst): ?>
        <div class="col-6">
          <i class="bi bi-people me-1"></i><?= number_format($totalEst) ?> estudiantes
        </div>
        <?php endif; ?>
        <?php if ($c['valor_total']): ?>
        <div class="col-12 mt-1">
          <i class="bi bi-cash-coin me-1 text-success"></i>
          <strong class="text-success"><?= '$'.number_format($c['valor_total'],0,',','.') ?></strong>
        </div>
        <?php endif; ?>
      </div>

      <!-- Comercial responsable -->
      <div class="text-muted mt-2" style="font-size:.72rem">
        <i class="bi bi-person me-1"></i><?= htmlspecialchars($c['nombre_comercial'] ?? $c['nombre_user'] ?? 'Sin asignar') ?>
        &middot; <?= date('d/m/Y', strtotime($c['created_at'])) ?>
      </div>

      <!-- Motivo rechazo -->
      <?php if ($c['estado']==='rechazado' && $c['motivo_rechazo']): ?>
      <div class="alert alert-danger py-1 mt-2 mb-0 small">
        <i class="bi bi-x-circle me-1"></i><?= htmlspecialchars($c['motivo_rechazo']) ?>
      </div>
      <?php endif; ?>

      <!-- PDF -->
      <?php if ($c['doc_convenio']): ?>
      <div class="mt-2">
        <a href="<?= UPLOAD_URL ?>convenios/<?= htmlspecialchars($c['doc_convenio']) ?>"
           target="_blank" class="btn btn-sm btn-outline-secondary py-0 w-100" style="font-size:.75rem">
          <i class="bi bi-file-earmark-pdf me-1 text-danger"></i>Ver documento PDF
        </a>
      </div>
      <?php endif; ?>
    </div>

    <!-- Acciones -->
    <div class="card-footer bg-transparent border-top pt-2 pb-2 d-flex gap-1 flex-wrap">

      <!-- Ver / Editar -->
      <a href="<?= APP_URL ?>/modules/comercial/ver.php?id=<?= $c['id'] ?>"
         class="btn btn-sm btn-outline-primary flex-fill py-0" style="font-size:.75rem">
        <i class="bi bi-eye me-1"></i>Ver
      </a>

      <?php if (in_array($c['estado'], ['borrador','rechazado'])): ?>
      <a href="<?= APP_URL ?>/modules/comercial/form.php?id=<?= $c['id'] ?>"
         class="btn btn-sm btn-outline-secondary flex-fill py-0" style="font-size:.75rem">
        <i class="bi bi-pencil me-1"></i>Editar
      </a>
      <?php endif; ?>

      <!-- Aprobar / Rechazar (gerencia) -->
      <?php if (Auth::isGerencia() && $c['estado']==='pendiente_aprobacion'): ?>
      <button type="button"
              class="btn btn-sm btn-success flex-fill py-0" style="font-size:.75rem"
              onclick="accionConvenio(<?= $c['id'] ?>,'aprobar')">
        <i class="bi bi-check me-1"></i>Aprobar
      </button>
      <button type="button"
              class="btn btn-sm btn-danger flex-fill py-0" style="font-size:.75rem"
              onclick="accionConvenio(<?= $c['id'] ?>,'rechazar')">
        <i class="bi bi-x me-1"></i>Rechazar
      </button>
      <?php endif; ?>

      <!-- Eliminar (gerencia) -->
      <?php if (Auth::isGerencia()): ?>
      <a href="?del=<?= $c['id'] ?>&csrf=<?= Auth::csrfToken() ?>"
         class="btn btn-sm btn-outline-danger py-0" style="font-size:.75rem"
         onclick="return confirm('¿Eliminar este requerimiento?')">
        <i class="bi bi-trash"></i>
      </a>
      <?php endif; ?>

    </div>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Modal aprobar/rechazar -->
<div class="modal fade" id="modalAccion" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:14px">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-bold" id="modalAccionTitulo">Confirmar acción</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <div class="modal-body pt-0">
          <input type="hidden" name="action"      id="accionInput">
          <input type="hidden" name="convenio_id" id="convenioIdInput">
          <input type="hidden" name="csrf"         value="<?= Auth::csrfToken() ?>">
          <div id="motivoWrap">
            <label class="form-label small fw-semibold">Motivo / Comentario</label>
            <textarea name="motivo" class="form-control" rows="3"
                      placeholder="Escribe el motivo..."></textarea>
          </div>
        </div>
        <div class="modal-footer border-0 pt-0">
          <button type="submit" class="btn btn-primary fw-bold flex-grow-1" id="btnConfirmarAccion">
            Confirmar
          </button>
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function accionConvenio(id, accion) {
    document.getElementById('accionInput').value      = accion;
    document.getElementById('convenioIdInput').value  = id;
    var titulo = accion === 'aprobar' ? '✅ Aprobar requerimiento' : '❌ Rechazar requerimiento';
    document.getElementById('modalAccionTitulo').textContent = titulo;
    document.getElementById('motivoWrap').style.display = accion === 'rechazar' ? '' : 'none';
    document.getElementById('btnConfirmarAccion').className =
        'btn fw-bold flex-grow-1 ' + (accion === 'aprobar' ? 'btn-success' : 'btn-danger');
    new bootstrap.Modal(document.getElementById('modalAccion')).show();
}
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
