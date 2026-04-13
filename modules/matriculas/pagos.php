<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();

$db         = Database::get();
$pageTitle  = 'Pagos';
$activeMenu = 'matriculas';
$error = $success = '';

// Registrar pago
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='registrar_pago') {
    if (!Auth::csrfVerify($_POST['csrf']??'')) die('CSRF');
    try {
        $matId  = (int)$_POST['matricula_id'];
        $valor  = (float)$_POST['valor'];
        $sabado = $_POST['sabado_ref'] ?: null;
        if (!$sabado) {
            // Calcular el sabado de referencia
            $dow = date('w');
            $sabado = $dow == 6 ? date('Y-m-d') : date('Y-m-d', strtotime('last saturday'));
        }
        $db->prepare("INSERT INTO pagos (matricula_id,tipo,concepto,valor,descuento,valor_pagado,medio_pago,referencia,fecha_pago,sabado_ref,estado,notas,registrado_por)
                      VALUES (?,?,?,?,?,?,?,?,CURDATE(),?,?,?,?)")
           ->execute([$matId,$_POST['tipo']??'mensualidad',trim($_POST['concepto']??''),$valor,0,$valor,$_POST['medio_pago']??'efectivo',trim($_POST['referencia']??'')?:null,$sabado,'pagado',trim($_POST['notas']??'')?:null,Auth::user()['id']]);

        // Activar matricula si estaba pendiente
        $db->prepare("UPDATE matriculas SET estado='activa' WHERE id=? AND estado='pendiente_pago'")->execute([$matId]);
        $success = 'Pago registrado correctamente.';
    } catch (Exception $e) { $error = $e->getMessage(); }
}

// Filtros
$fSabado = $_GET['sabado'] ?? '';
$fGrupo  = (int)($_GET['grupo_id'] ?? 0);
$fMedio  = $_GET['medio'] ?? '';

$where = ["p.estado='pagado'"];
if ($fSabado) $where[] = "p.sabado_ref = ".$db->quote($fSabado);
if ($fGrupo)  $where[] = "m.grupo_id=$fGrupo";
if ($fMedio)  $where[] = "p.medio_pago=".$db->quote($fMedio);
$whereStr = implode(' AND ', $where);

$pagos = $db->query("
    SELECT p.*, m.grupo_id,
           CONCAT(e.nombres,' ',e.apellidos) AS estudiante,
           g.nombre AS grupo_nombre
    FROM pagos p
    JOIN matriculas m ON m.id=p.matricula_id
    JOIN estudiantes e ON e.id=m.estudiante_id
    JOIN escuela_grupos g ON g.id=m.grupo_id
    WHERE $whereStr
    ORDER BY p.fecha_pago DESC, p.created_at DESC
    LIMIT 200
")->fetchAll();

$totalFiltrado = array_sum(array_column($pagos,'valor_pagado'));

// Para el form de nuevo pago
$matriculas = $db->query("
    SELECT m.id, CONCAT(e.nombres,' ',e.apellidos) AS nombre, g.nombre AS grupo
    FROM matriculas m
    JOIN estudiantes e ON e.id=m.estudiante_id
    JOIN escuela_grupos g ON g.id=m.grupo_id
    WHERE m.estado IN ('activa','pendiente_pago')
    ORDER BY e.apellidos, e.nombres
")->fetchAll();

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
/* Solo medios de pago — tabla usa .table-inv del sistema */
.mp-efectivo  {background:#dcfce7;color:#166534}
.mp-nequi     {background:#fae8ff;color:#7e22ce}
.mp-daviplata {background:#fee2e2;color:#991b1b}
.mp-transferencia{background:#dbeafe;color:#1e40af}
.mp-tarjeta   {background:#fef9c3;color:#854d0e}
</style>

<div class="page-header">
  <div>
    <h4 class="page-header-title"><i class="bi bi-cash me-2"></i>Pagos</h4>
    <?php if ($fSabado): ?>
      <p class="page-header-sub">S&aacute;bado: <strong><?= date('d \d\e F \d\e Y', strtotime($fSabado)) ?></strong></p>
    <?php endif; ?>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <button class="btn btn-success btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#modalPago">
      <i class="bi bi-plus-lg me-1"></i>Registrar Pago
    </button>
  </div>
</div>

<?php if ($error):   ?><div class="alert alert-danger  py-2 small"><?= htmlspecialchars($error)   ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<!-- Filtros -->
<div class="filter-bar">
  <form method="GET" class="d-flex gap-2 flex-wrap align-items-center">
    <input type="date" name="sabado" class="form-control form-control-sm" style="max-width:150px" value="<?= $fSabado ?>">
    <select name="medio" class="form-select form-select-sm" style="max-width:150px">
      <option value="">Todos los medios</option>
      <?php foreach (['efectivo','nequi','daviplata','transferencia','tarjeta'] as $m): ?>
        <option value="<?= $m ?>" <?= $fMedio===$m?'selected':'' ?>><?= ucfirst($m) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-primary btn-sm">Filtrar</button>
    <?php if ($fSabado||$fMedio||$fGrupo): ?><a href="pagos.php" class="btn btn-outline-secondary btn-sm">Limpiar</a><?php endif; ?>
    <span class="ms-auto fw-bold text-success">Total: $<?= number_format($totalFiltrado,0,',','.') ?></span>
  </form>
</div>

<!-- Tabla de pagos -->
<div class="section-card p-0" style="overflow:hidden">
  <div class="table-responsive">
    <table class="table table-hover table-inv mb-0">
      <thead><tr>
        <th>Fecha</th><th>S&aacute;bado</th><th>Estudiante</th><th>Grupo</th>
        <th>Concepto</th><th>Medio</th><th class="text-end">Valor</th>
      </tr></thead>
      <tbody>
      <?php foreach ($pagos as $p): ?>
      <tr>
        <td style="white-space:nowrap"><?= date('d/m/Y', strtotime($p['fecha_pago'])) ?></td>
        <td style="white-space:nowrap;color:#475569">
          <?= $p['sabado_ref'] ? date('d/m', strtotime($p['sabado_ref'])) : '&mdash;' ?>
        </td>
        <td class="fw-semibold"><?= htmlspecialchars($p['estudiante']) ?></td>
        <td class="text-muted"><?= htmlspecialchars($p['grupo_nombre']) ?></td>
        <td><?= htmlspecialchars($p['concepto'] ?: ucfirst($p['tipo'])) ?></td>
        <td>
          <span class="badge mp-<?= $p['medio_pago'] ?>" style="font-size:.7rem"><?= ucfirst($p['medio_pago']) ?></span>
          <?php if ($p['referencia']): ?>
            <span class="text-muted" style="font-size:.7rem"><?= htmlspecialchars($p['referencia']) ?></span>
          <?php endif; ?>
        </td>
        <td class="text-end fw-bold text-success">$<?= number_format($p['valor_pagado'],0,',','.') ?></td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($pagos)): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">No hay pagos registrados con los filtros seleccionados</td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Modal nuevo pago -->
<div class="modal fade" id="modalPago" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:14px">
      <div class="modal-header border-0">
        <h5 class="modal-title fw-bold"><i class="bi bi-cash me-2"></i>Registrar Pago</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <form method="POST">
          <input type="hidden" name="action" value="registrar_pago">
          <input type="hidden" name="csrf"   value="<?= Auth::csrfToken() ?>">
          <div class="row g-2">
            <div class="col-12">
              <label class="form-label small fw-semibold">Estudiante / Matr&iacute;cula *</label>
              <select name="matricula_id" class="form-select form-select-sm" required>
                <option value="">-- Seleccionar --</option>
                <?php foreach ($matriculas as $m): ?>
                  <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nombre']) ?> &mdash; <?= htmlspecialchars($m['grupo']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Tipo</label>
              <select name="tipo" class="form-select form-select-sm">
                <option value="mensualidad">Mensualidad</option>
                <option value="matricula">Matr&iacute;cula</option>
                <option value="semestral">Semestral</option>
                <option value="material">Material</option>
                <option value="otro">Otro</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Medio de pago</label>
              <select name="medio_pago" class="form-select form-select-sm">
                <option value="efectivo">Efectivo</option>
                <option value="nequi">Nequi</option>
                <option value="daviplata">Daviplata</option>
                <option value="transferencia">Transferencia</option>
                <option value="tarjeta">Tarjeta</option>
              </select>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Valor *</label>
              <input type="number" name="valor" class="form-control form-control-sm" required min="1000" step="1000">
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">S&aacute;bado de referencia</label>
              <input type="date" name="sabado_ref" class="form-control form-control-sm"
                     value="<?= date('w')==6 ? date('Y-m-d') : date('Y-m-d', strtotime('last saturday')) ?>">
            </div>
            <div class="col-12">
              <label class="form-label small fw-semibold">Concepto</label>
              <input type="text" name="concepto" class="form-control form-control-sm" placeholder="Ej: Mensualidad Enero">
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Referencia</label>
              <input type="text" name="referencia" class="form-control form-control-sm" placeholder="# transaccion">
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold">Notas</label>
              <input type="text" name="notas" class="form-control form-control-sm">
            </div>
          </div>
          <div class="d-flex gap-2 mt-3">
            <button type="submit" class="btn btn-success fw-bold">Guardar Pago</button>
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
