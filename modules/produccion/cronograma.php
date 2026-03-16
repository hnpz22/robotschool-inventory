<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();
Auth::requirePermiso('produccion','ver');

$db         = Database::get();
$pageTitle  = 'Cronograma de Produccion';
$activeMenu = 'produccion';
$error = $success = '';
$userId = Auth::user()['id'];
$isComercial = in_array(Auth::getRol(), ['comercial','gerencia']);

// ── ASIGNAR A MAQUINA ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='asignar') {
    if (!Auth::csrfVerify($_POST['csrf']??'')) die('CSRF');
    Auth::requirePermiso('produccion','editar');
    try {
        $sid     = (int)$_POST['solicitud_id'];
        $maqId   = (int)$_POST['maquina_id'];
        $fInicio = $_POST['fecha_inicio'];
        $fFin    = $_POST['fecha_fin'];
        $horas   = (float)($_POST['horas_estimadas'] ?? 0) ?: null;
        $notas   = trim($_POST['notas'] ?? '') ?: null;

        if (!$sid || !$maqId || !$fInicio || !$fFin)
            throw new Exception('Todos los campos son requeridos.');
        if ($fFin <= $fInicio)
            throw new Exception('La fecha de fin debe ser posterior a la de inicio.');

        // Verificar traslape en la misma máquina
        $traslape = $db->query("
            SELECT COUNT(*) FROM produccion_cronograma
            WHERE maquina_id=$maqId
            AND id != 0
            AND fecha_inicio < ".$db->quote($fFin)."
            AND fecha_fin > ".$db->quote($fInicio)
        )->fetchColumn();

        if ($traslape > 0)
            throw new Exception('¡Conflicto de horario! Esa maquina ya tiene una tarea asignada en ese rango de fechas. Revisa el cronograma.');

        // Verificar si ya existe asignación para esta solicitud en esta máquina
        $existe = $db->query("SELECT id FROM produccion_cronograma WHERE solicitud_id=$sid AND maquina_id=$maqId")->fetchColumn();
        if ($existe) {
            $db->prepare("UPDATE produccion_cronograma SET fecha_inicio=?,fecha_fin=?,horas_estimadas=?,notas=? WHERE id=?")
               ->execute([$fInicio,$fFin,$horas,$notas,$existe]);
        } else {
            $db->prepare("INSERT INTO produccion_cronograma (solicitud_id,maquina_id,fecha_inicio,fecha_fin,horas_estimadas,notas,created_by) VALUES (?,?,?,?,?,?,?)")
               ->execute([$sid,$maqId,$fInicio,$fFin,$horas,$notas,$userId]);
        }

        // Actualizar fecha_limite en la solicitud
        $db->prepare("UPDATE solicitudes_produccion SET fecha_limite=DATE(?) WHERE id=?")
           ->execute([$fFin,$sid]);

        $success = 'Tarea asignada al cronograma correctamente.';
    } catch (Exception $e) { $error = $e->getMessage(); }
}

// ── QUITAR ASIGNACION ─────────────────────────────────────────
if (isset($_GET['rm']) && Auth::csrfVerify($_GET['csrf']??'')) {
    Auth::requirePermiso('produccion','editar');
    $db->prepare("DELETE FROM produccion_cronograma WHERE id=?")->execute([(int)$_GET['rm']]);
    $success = 'Asignacion removida.';
}

// ── DATOS ──────────────────────────────────────────────────────
$semanas = (int)($_GET['semanas'] ?? 4);
$hoy     = new DateTime();
$hoy->setTime(0,0,0);
$finVista= (clone $hoy)->modify("+$semanas weeks");

$maquinas = $db->query("SELECT * FROM maquinas_produccion WHERE activa=1 ORDER BY tipo,nombre")->fetchAll();

// Cronograma actual
$cronograma = $db->query("
    SELECT pc.*,
           mp.nombre AS maq_nombre, mp.color AS maq_color,
           s.titulo, s.kit_nombre, s.cantidad, s.fuente, s.estado AS sol_estado,
           s.prioridad, s.fecha_limite,
           u.nombre AS solicitado_nombre,
           tp.woo_order_id AS numero_pedido,
           co.nombre_colegio AS conv_colegio
    FROM produccion_cronograma pc
    JOIN maquinas_produccion mp ON mp.id=pc.maquina_id
    JOIN solicitudes_produccion s ON s.id=pc.solicitud_id
    LEFT JOIN usuarios u ON u.id=s.solicitado_por
    LEFT JOIN tienda_pedidos tp ON tp.id=s.pedido_id
    LEFT JOIN convenios co ON co.id=s.convenio_id
    WHERE pc.fecha_fin >= NOW()
    ORDER BY pc.maquina_id, pc.fecha_inicio
")->fetchAll();

// Solicitudes sin asignar (pendientes o en proceso)
// Verificar columnas para sin_asignar
$_colColId3  = $db->query("SHOW COLUMNS FROM solicitudes_produccion LIKE 'colegio_id'")->fetchColumn();
$_colConvId3 = $db->query("SHOW COLUMNS FROM solicitudes_produccion LIKE 'convenio_id'")->fetchColumn();
$_tabConvSA  = $db->query("SHOW TABLES LIKE 'convenios'")->fetchColumn();

$_joinColSA  = $_colColId3  ? "LEFT JOIN colegios col2 ON col2.id=s.colegio_id" : "";
$_selColSA   = $_colColId3  ? "col2.nombre AS colegio_nombre," : "NULL AS colegio_nombre,";
$_joinConvSA = ($_tabConvSA && $_colConvId3) ? "LEFT JOIN convenios conv2 ON conv2.id=s.convenio_id" : "";
$_selConvSA  = ($_tabConvSA && $_colConvId3) ? "conv2.nombre_colegio AS conv_colegio," : "NULL AS conv_colegio,";
$_selColTpSA = $db->query("SHOW TABLES LIKE 'tienda_pedidos'")->fetchColumn()
    ? "tp2.colegio_nombre AS tienda_colegio," : "NULL AS tienda_colegio,";

$sinAsignar = $db->query("
    SELECT s.id, s.titulo, s.kit_nombre, s.cantidad, s.fuente, s.prioridad,
           s.fecha_limite, s.estado, s.pedido_id,
           u.nombre AS sol_nombre,
           $_selColSA
           $_selConvSA
           $_selColTpSA
           1 AS _ok
    FROM solicitudes_produccion s
    LEFT JOIN produccion_cronograma pc ON pc.solicitud_id=s.id
    LEFT JOIN usuarios u ON u.id=s.solicitado_por
    $_joinColSA
    $_joinConvSA
    LEFT JOIN tienda_pedidos tp2 ON tp2.id=s.pedido_id
    WHERE s.estado IN ('pendiente','en_proceso')
    AND pc.id IS NULL
    ORDER BY s.prioridad ASC, s.created_at ASC
")->fetchAll();

$FUENTES = [
    'tienda'    =>['label'=>'Tienda',     'color'=>'#185FA5'],
    'comercial' =>['label'=>'Comercial',  'color'=>'#7c3aed'],
    'colegio'   =>['label'=>'Colegio',    'color'=>'#0891b2'],
    'cliente'   =>['label'=>'Cliente',    'color'=>'#16a34a'],
    'salon'     =>['label'=>'Salon',      'color'=>'#d97706'],
    'interno'   =>['label'=>'Interno',    'color'=>'#64748b'],
];
$PRIORIDADES = [
    1=>['label'=>'URGENTE','color'=>'#dc2626','bg'=>'#fee2e2'],
    2=>['label'=>'Alta',   'color'=>'#d97706','bg'=>'#fef9c3'],
    3=>['label'=>'Normal', 'color'=>'#185FA5','bg'=>'#eff6ff'],
    4=>['label'=>'Baja',   'color'=>'#64748b','bg'=>'#f8fafc'],
];

// Generar días de la vista
$dias = [];
$d = clone $hoy;
while ($d < $finVista) {
    $dias[] = clone $d;
    $d->modify('+1 day');
}

// Agrupar cronograma por máquina
$cronMaq = [];
foreach ($cronograma as $c) {
    $cronMaq[$c['maquina_id']][] = $c;
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
.sc{background:#fff;border-radius:14px;border:1px solid #e2e8f0;padding:1rem 1.2rem;margin-bottom:1rem}
/* Gantt */
.gantt-wrap{overflow-x:auto}
.gantt-table{border-collapse:collapse;min-width:100%}
.gantt-table th,.gantt-table td{border:1px solid #e2e8f0;padding:0;white-space:nowrap}
.gantt-col-maq{min-width:130px;width:130px;background:#f8fafc;padding:.4rem .6rem;font-size:.78rem;font-weight:700;vertical-align:middle}
.gantt-col-dia{min-width:38px;width:38px;text-align:center;padding:.2rem;font-size:.65rem;color:#64748b;vertical-align:middle}
.gantt-col-dia.hoy{background:#eff6ff;color:#185FA5;font-weight:700}
.gantt-col-dia.finde{background:#fafaf9;color:#94a3b8}
.gantt-cel{height:36px;position:relative;vertical-align:middle}
.gantt-bloque{
  position:absolute;top:3px;bottom:3px;
  border-radius:4px;
  display:flex;align-items:center;
  padding:0 6px;
  font-size:.65rem;font-weight:700;color:#fff;
  overflow:hidden;white-space:nowrap;
  cursor:pointer;
  transition:.15s;z-index:1
}
.gantt-bloque:hover{filter:brightness(0.9);z-index:10}
.dia-header{display:flex;flex-direction:column;align-items:center;gap:1px}
.dia-num{font-size:.75rem;font-weight:700;line-height:1}
.dia-mes{font-size:.58rem;line-height:1}
.dia-dow{font-size:.58rem;line-height:1}
/* Semaforo */
.semaforo-badge{display:inline-flex;align-items:center;gap:.3rem;padding:.2rem .55rem;border-radius:20px;font-size:.72rem;font-weight:700}
.sem-rojo{background:#fee2e2;color:#dc2626}
.sem-amarillo{background:#fef9c3;color:#854d0e}
.sem-verde{background:#dcfce7;color:#166534}
.sem-azul{background:#eff6ff;color:#185FA5}
/* Sin asignar */
.pending-card{border:1px solid #e2e8f0;border-radius:8px;padding:.5rem .75rem;margin-bottom:.4rem;
              cursor:pointer;transition:.15s;background:#fff}
.pending-card:hover{border-color:#185FA5;background:#f0f7ff}
.prior-strip{width:4px;border-radius:2px;align-self:stretch;flex-shrink:0}
</style>

<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h4 class="fw-bold mb-0"><i class="bi bi-calendar3 me-2"></i>Cronograma de Producci&oacute;n</h4>
    <p class="text-muted small mb-0">
      <?= count($maquinas) ?> m&aacute;quinas activas &middot;
      Vista <?= $semanas ?> semanas
      <?php if ($isComercial): ?>
        &middot; <span class="text-primary fw-semibold"><i class="bi bi-eye me-1"></i>Vista Comercial</span>
      <?php endif; ?>
    </p>
  </div>
  <div class="d-flex gap-2">
    <a href="?semanas=2" class="btn btn-sm <?= $semanas==2?'btn-primary':'btn-outline-secondary' ?>">2 sem</a>
    <a href="?semanas=4" class="btn btn-sm <?= $semanas==4?'btn-primary':'btn-outline-secondary' ?>">4 sem</a>
    <a href="?semanas=8" class="btn btn-sm <?= $semanas==8?'btn-primary':'btn-outline-secondary' ?>">8 sem</a>
    <a href="index.php" class="btn btn-sm btn-light"><i class="bi bi-kanban me-1"></i>Tablero</a>
  </div>
</div>

<?php if ($error):   ?><div class="alert alert-danger  py-2 small"><?= htmlspecialchars($error)   ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="row g-3">

  <!-- CRONOGRAMA GANTT -->
  <div class="col-lg-9">
    <div class="sc" style="padding:.75rem">
      <div class="d-flex align-items-center justify-content-between mb-2">
        <h6 class="fw-bold mb-0"><i class="bi bi-bar-chart-gantt me-2 text-primary"></i>Cronograma por M&aacute;quina</h6>
        <div class="d-flex gap-2 flex-wrap" style="font-size:.7rem">
          <?php foreach ($maquinas as $mq): ?>
          <span style="background:<?= $mq['color'] ?>20;color:<?= $mq['color'] ?>;padding:.15rem .45rem;border-radius:20px;font-weight:700;border:1px solid <?= $mq['color'] ?>40">
            <i class="bi bi-cpu me-1" style="font-size:.65rem"></i><?= htmlspecialchars($mq['nombre']) ?>
          </span>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="gantt-wrap">
        <table class="gantt-table">
          <thead>
            <tr>
              <th class="gantt-col-maq" style="background:#1e293b;color:#fff">M&aacute;quina</th>
              <?php foreach ($dias as $dia):
                $esFinde = in_array($dia->format('N'), [6,7]);
                $esHoy   = $dia->format('Y-m-d') === date('Y-m-d');
                $DOW     = ['','Lun','Mar','Mie','Jue','Vie','Sab','Dom'][$dia->format('N')];
              ?>
              <th class="gantt-col-dia <?= $esHoy?'hoy':($esFinde?'finde':'') ?>">
                <div class="dia-header">
                  <div class="dia-dow"><?= $DOW ?></div>
                  <div class="dia-num"><?= $dia->format('j') ?></div>
                  <div class="dia-mes"><?= $dia->format('M') ?></div>
                </div>
              </th>
              <?php endforeach; ?>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($maquinas as $mq):
            $tareasM = $cronMaq[$mq['id']] ?? [];
          ?>
          <tr>
            <td class="gantt-col-maq">
              <div style="color:<?= $mq['color'] ?>">
                <i class="bi bi-cpu me-1"></i><?= htmlspecialchars($mq['nombre']) ?>
              </div>
              <div class="text-muted" style="font-size:.65rem"><?= count($tareasM) ?> tarea(s)</div>
            </td>
            <?php
            // Construir mapa día → tarea
            $diaMap = [];
            foreach ($tareasM as $t) {
                $ini = new DateTime($t['fecha_inicio']);
                $fin = new DateTime($t['fecha_fin']);
                $ini->setTime(0,0,0);
                $fin->setTime(0,0,0);
                $d2 = clone $ini;
                while ($d2 <= $fin) {
                    $diaMap[$d2->format('Y-m-d')] = $t;
                    $d2->modify('+1 day');
                }
            }
            // Renderizar días
            $skipDias = [];
            foreach ($dias as $dia):
                $dk = $dia->format('Y-m-d');
                $esFinde = in_array($dia->format('N'), [6,7]);
                $esHoy   = $dk === date('Y-m-d');
                if (in_array($dk, $skipDias)) { echo "<td></td>"; continue; }
                $tarea = $diaMap[$dk] ?? null;
                if ($tarea) {
                    // Calcular cuántos días ocupa (colspan)
                    $ini2 = new DateTime($tarea['fecha_inicio']);
                    $fin2 = new DateTime($tarea['fecha_fin']);
                    $ini2->setTime(0,0,0); $fin2->setTime(0,0,0);
                    $span = 1;
                    $nextD = clone $dia;
                    for ($x=1; $x<30; $x++) {
                        $nextD->modify('+1 day');
                        $nk = $nextD->format('Y-m-d');
                        if (isset($diaMap[$nk]) && $diaMap[$nk]['id']==$tarea['id']) {
                            $span++;
                            $skipDias[] = $nk;
                        } else break;
                    }
                    $pr = $PRIORIDADES[$tarea['prioridad']] ?? $PRIORIDADES[3];
                    $titulo = mb_strimwidth($tarea['titulo'] ?: $tarea['kit_nombre'] ?: '?', 0, 25, '...');
                    $diasRest = (new DateTime($tarea['fecha_limite']??$tarea['fecha_fin']))->diff(new DateTime('today'));
                    $vencido  = $diasRest->invert;
                    echo '<td class="gantt-cel" colspan="'.$span.'" style="background:'.$mq['color'].'08">';
                    echo '<div class="gantt-bloque" style="background:'.$mq['color'].';left:2px;right:2px;"
                         title="'.htmlspecialchars($tarea['titulo'].' | '.$tarea['cantidad'].' uds | '.($tarea['sol_nombre']??'')).'"
                         onclick="mostrarDetalleGantt('.$tarea['id'].')">';
                    echo '<i class="bi bi-circle-fill me-1" style="font-size:.5rem;color:'.$pr['color'].'"></i>';
                    echo htmlspecialchars($titulo);
                    if ($vencido) echo ' ⚠';
                    echo '</div></td>';
                } else {
                    $bg = $esHoy?'#eff6ff':($esFinde?'#f9fafb':'#fff');
                    echo '<td class="gantt-cel" style="background:'.$bg.'"></td>';
                }
            endforeach; ?>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Leyenda semáforo -->
      <div class="d-flex gap-2 flex-wrap mt-2">
        <span class="text-muted small fw-semibold">Sem&aacute;foro prioridad:</span>
        <?php foreach ($PRIORIDADES as $pk => $pv): ?>
        <span class="semaforo-badge <?= $pk==1?'sem-rojo':($pk==2?'sem-amarillo':($pk==3?'sem-azul':'sem-verde')) ?>">
          <i class="bi bi-circle-fill" style="font-size:.5rem"></i><?= $pv['label'] ?>
        </span>
        <?php endforeach; ?>
        <span class="text-muted small ms-2">⚠ = Fecha vencida o muy pr&oacute;xima</span>
      </div>
    </div>

    <!-- LISTA DETALLADA PARA COMERCIAL -->
    <?php if ($isComercial || Auth::isAdmin()): ?>
    <div class="sc">
      <h6 class="fw-bold mb-3">
        <i class="bi bi-table me-2 text-primary"></i>Detalle de Entregas
        <span class="badge bg-primary ms-1" style="font-size:.7rem">Vista Comercial</span>
      </h6>
      <div class="table-responsive">
        <table class="table table-sm" style="font-size:.8rem">
          <thead style="background:#f8fafc">
            <tr>
              <th>Solicitud</th>
              <th>Fuente</th>
              <th>Kit / Producto</th>
              <th>Cant.</th>
              <th>M&aacute;quina</th>
              <th>Inicio</th>
              <th>Entrega</th>
              <th>Estado</th>
              <th>Prioridad</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($cronograma as $c):
            $fu   = $FUENTES[$c['fuente']] ?? $FUENTES['interno'];
            $pr   = $PRIORIDADES[$c['prioridad']] ?? $PRIORIDADES[3];
            $fLim = $c['fecha_limite'] ?? date('Y-m-d', strtotime($c['fecha_fin']));
            $diff = (new DateTime($fLim))->diff(new DateTime('today'));
            $diasRest = $diff->invert ? -$diff->days : $diff->days;
            $semClass = $diasRest < 0 ? 'sem-rojo' : ($diasRest <= 3 ? 'sem-amarillo' : ($diasRest <= 7 ? 'sem-azul' : 'sem-verde'));
            $semLabel = $diasRest < 0 ? 'Vencido' : ($diasRest == 0 ? '¡Hoy!' : "$diasRest días");
          ?>
          <tr>
            <td class="fw-semibold"><?= htmlspecialchars(mb_strimwidth($c['titulo']?:'Sin título',0,30,'...')) ?></td>
            <td><span style="background:<?= $fu['color'] ?>20;color:<?= $fu['color'] ?>;padding:.1rem .4rem;border-radius:20px;font-size:.68rem;font-weight:700"><?= $fu['label'] ?></span></td>
            <td><?= htmlspecialchars(mb_strimwidth($c['kit_nombre']??'',0,25,'...')) ?></td>
            <td class="text-center fw-bold"><?= $c['cantidad'] ?></td>
            <td><span style="color:<?= $c['maq_color'] ?>;font-weight:700"><?= htmlspecialchars($c['maq_nombre']) ?></span></td>
            <td class="text-muted"><?= date('d/m/Y', strtotime($c['fecha_inicio'])) ?></td>
            <td class="fw-bold"><?= date('d/m/Y', strtotime($c['fecha_fin'])) ?></td>
            <td>
              <?php
              $se = ['pendiente'=>['danger','Pendiente'],'en_proceso'=>['warning','En proceso'],'listo'=>['success','Listo'],'rechazado'=>['secondary','Rechazado']];
              [$sc,$sl] = $se[$c['sol_estado']] ?? ['secondary','?'];
              ?>
              <span class="badge bg-<?= $sc ?>"><?= $sl ?></span>
            </td>
            <td>
              <span class="semaforo-badge <?= $semClass ?>">
                <i class="bi bi-circle-fill" style="font-size:.45rem"></i><?= $semLabel ?>
              </span>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($cronograma)): ?>
          <tr><td colspan="9" class="text-center text-muted py-3">Sin tareas programadas</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- PANEL LATERAL -->
  <div class="col-lg-3">

    <!-- Sin asignar -->
    <div class="sc">
      <h6 class="fw-bold mb-2">
        <i class="bi bi-hourglass-split me-2 text-danger"></i>Sin programar
        <?php if (!empty($sinAsignar)): ?>
          <span class="badge bg-danger ms-1"><?= count($sinAsignar) ?></span>
        <?php endif; ?>
      </h6>
      <?php if (empty($sinAsignar)): ?>
        <div class="text-center text-muted py-3 small">
          <i class="bi bi-check-circle fs-2 d-block mb-1 text-success"></i>Todo programado
        </div>
      <?php else: ?>
      <?php foreach ($sinAsignar as $s):
        <?php
          $colSA = ($s['colegio_nombre'] ?? null) ?: ($s['conv_colegio'] ?? null) ?: ($s['tienda_colegio'] ?? null);
        ?>
        $pr = $PRIORIDADES[$s['prioridad']] ?? $PRIORIDADES[3];
        $fu = $FUENTES[$s['fuente']] ?? $FUENTES['interno'];
      ?>
      <div class="pending-card d-flex gap-2" onclick="abrirAsignar(<?= $s['id'] ?>, '<?= addslashes($s['titulo']?:$s['kit_nombre']) ?>')">
        <div class="prior-strip" style="background:<?= $pr['color'] ?>"></div>
        <div class="flex-grow-1">
          <div class="fw-semibold" style="font-size:.78rem"><?= htmlspecialchars(mb_strimwidth($s['titulo']?:$s['kit_nombre']?:'Sin titulo',0,30,'...')) ?></div>
          <?php if ($colSA): ?>
          <div style="font-size:.7rem;color:#0891b2;font-weight:600;margin-top:2px">
            <i class="bi bi-building me-1"></i><?= htmlspecialchars(mb_strimwidth($colSA,0,28,'...')) ?>
          </div>
          <?php endif; ?>
          <div class="d-flex gap-1 mt-1 flex-wrap">
            <span style="background:<?= $fu['color'] ?>15;color:<?= $fu['color'] ?>;font-size:.65rem;padding:.1rem .35rem;border-radius:10px;font-weight:700"><?= $fu['label'] ?></span>
            <span style="background:<?= $pr['bg'] ?>;color:<?= $pr['color'] ?>;font-size:.65rem;padding:.1rem .35rem;border-radius:10px;font-weight:700"><?= $pr['label'] ?></span>
            <span class="text-muted" style="font-size:.65rem"><?= $s['cantidad'] ?> uds</span>
          </div>
          <?php if ($s['fecha_limite']): ?>
            <div class="text-danger" style="font-size:.65rem;margin-top:2px">
              <i class="bi bi-calendar-x me-1"></i>Limite: <?= date('d/m/Y',strtotime($s['fecha_limite'])) ?>
            </div>
          <?php endif; ?>
        </div>
        <i class="bi bi-calendar-plus text-primary" style="font-size:.85rem;margin-top:2px"></i>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Ocupacion maquinas hoy -->
    <div class="sc">
      <h6 class="fw-bold mb-2"><i class="bi bi-cpu me-2 text-primary"></i>Ocupaci&oacute;n hoy</h6>
      <?php foreach ($maquinas as $mq):
        $tareaHoy = null;
        foreach ($cronograma as $c) {
            if ($c['maquina_id']==$mq['id'] &&
                $c['fecha_inicio'] <= date('Y-m-d 23:59:59') &&
                $c['fecha_fin']    >= date('Y-m-d 00:00:00')) {
                $tareaHoy = $c; break;
            }
        }
      ?>
      <div class="d-flex align-items-center gap-2 py-2 border-bottom">
        <div style="width:10px;height:10px;border-radius:50%;background:<?= $mq['color'] ?>;flex-shrink:0"></div>
        <div class="flex-grow-1">
          <div class="fw-semibold" style="font-size:.8rem"><?= htmlspecialchars($mq['nombre']) ?></div>
          <?php if ($tareaHoy): ?>
            <div style="font-size:.7rem;color:<?= $mq['color'] ?>">
              <?= htmlspecialchars(mb_strimwidth($tareaHoy['titulo']??$tareaHoy['kit_nombre']??'',0,25,'...')) ?>
            </div>
          <?php else: ?>
            <div class="text-success" style="font-size:.7rem"><i class="bi bi-check-circle me-1"></i>Disponible</div>
          <?php endif; ?>
        </div>
        <span class="badge" style="background:<?= $tareaHoy?$mq['color'].'20':'#dcfce7' ?>;color:<?= $tareaHoy?$mq['color']:'#166534' ?>;font-size:.65rem">
          <?= $tareaHoy ? 'Ocupada' : 'Libre' ?>
        </span>
      </div>
      <?php endforeach; ?>
    </div>

  </div>
</div>

<!-- MODAL ASIGNAR A MAQUINA -->
<div class="modal fade" id="modalAsignar" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:14px">
      <div class="modal-header" style="background:#1e293b;color:#fff;border-radius:14px 14px 0 0">
        <h5 class="modal-title fw-bold"><i class="bi bi-calendar-plus me-2"></i>Programar en Cronograma</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action"       value="asignar">
        <input type="hidden" name="csrf"         value="<?= Auth::csrfToken() ?>">
        <input type="hidden" name="solicitud_id" id="asigSolId">
        <div class="modal-body">
          <div class="alert alert-info py-2 small" id="asigSolTitulo"></div>
          <div class="row g-3">
            <div class="col-12">
              <label class="form-label small fw-semibold">M&aacute;quina *</label>
              <div class="d-flex gap-2 flex-wrap">
                <?php foreach ($maquinas as $mq): ?>
                <button type="button" class="btn btn-sm btn-maq"
                        style="background:<?= $mq['color'] ?>20;color:<?= $mq['color'] ?>;border:1.5px solid <?= $mq['color'] ?>40"
                        data-maq="<?= $mq['id'] ?>"
                        onclick="selMaq(<?= $mq['id'] ?>, this)">
                  <i class="bi bi-cpu me-1"></i><?= htmlspecialchars($mq['nombre']) ?>
                </button>
                <?php endforeach; ?>
                <input type="hidden" name="maquina_id" id="maqHidden" required>
              </div>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Fecha / Hora inicio *</label>
              <input type="datetime-local" name="fecha_inicio" id="fechaInicio"
                     class="form-control form-control-sm" required
                     min="<?= date('Y-m-d\TH:i') ?>">
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Fecha / Hora fin *</label>
              <input type="datetime-local" name="fecha_fin" id="fechaFin"
                     class="form-control form-control-sm" required>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Horas estimadas</label>
              <input type="number" name="horas_estimadas" class="form-control form-control-sm"
                     min="0.5" step="0.5" placeholder="Ej: 4.5">
            </div>
            <div class="col-6">
              <div class="alert alert-warning py-1 mt-1 small" id="traslapeAlert" style="display:none">
                <i class="bi bi-exclamation-triangle me-1"></i>Posible traslape
              </div>
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold">Notas</label>
              <input type="text" name="notas" class="form-control form-control-sm"
                     placeholder="Instrucciones especiales para esta maquina...">
            </div>
          </div>
        </div>
        <div class="modal-footer border-0">
          <button type="submit" class="btn btn-primary fw-bold flex-grow-1">
            <i class="bi bi-calendar-check me-1"></i>Guardar en Cronograma
          </button>
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Cronograma existente para detección de traslapes en el cliente
var cronogramaData = <?= json_encode(array_map(function($c){
    return ['maquina_id'=>$c['maquina_id'],'inicio'=>$c['fecha_inicio'],'fin'=>$c['fecha_fin']];
}, $cronograma)) ?>;

function abrirAsignar(id, titulo) {
    document.getElementById('asigSolId').value = id;
    document.getElementById('asigSolTitulo').textContent = titulo;
    document.getElementById('maqHidden').value = '';
    document.querySelectorAll('.btn-maq').forEach(b => b.style.borderWidth = '1.5px');
    new bootstrap.Modal(document.getElementById('modalAsignar')).show();
}

function selMaq(id, btn) {
    document.querySelectorAll('.btn-maq').forEach(b => b.style.borderWidth = '1.5px');
    btn.style.borderWidth = '3px';
    document.getElementById('maqHidden').value = id;
    verificarTraslape();
}

function verificarTraslape() {
    var maqId  = parseInt(document.getElementById('maqHidden').value);
    var inicio = document.getElementById('fechaInicio').value;
    var fin    = document.getElementById('fechaFin').value;
    if (!maqId || !inicio || !fin) return;

    var hay = cronogramaData.some(function(c) {
        return c.maquina_id == maqId && c.inicio < fin && c.fin > inicio;
    });
    document.getElementById('traslapeAlert').style.display = hay ? '' : 'none';
}

document.getElementById('fechaInicio')?.addEventListener('change', function() {
    // Auto calcular fin = inicio + 1 día si no hay fin
    if (!document.getElementById('fechaFin').value && this.value) {
        var d = new Date(this.value);
        d.setDate(d.getDate() + 1);
        document.getElementById('fechaFin').value = d.toISOString().slice(0,16);
    }
    verificarTraslape();
});
document.getElementById('fechaFin')?.addEventListener('change', verificarTraslape);

function mostrarDetalleGantt(id) {
    // Scroll a la tabla detalle si existe
    var tabla = document.querySelector('.table-responsive');
    if (tabla) tabla.scrollIntoView({behavior:'smooth'});
}
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
