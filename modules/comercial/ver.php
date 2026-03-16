<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();
Auth::requireRol('comercial','gerencia','administracion','produccion');

$db = Database::get();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: index.php'); exit; }

$conv = $db->query("
    SELECT c.*, u.nombre AS nombre_user, u.email AS email_user
    FROM convenios c
    LEFT JOIN usuarios u ON u.id=c.aprobado_por
    WHERE c.id=$id AND c.activo=1
")->fetch();
if (!$conv) { header('Location: index.php'); exit; }

$cursos   = $db->query("SELECT * FROM convenio_cursos WHERE convenio_id=$id ORDER BY id")->fetchAll();
$historial= $db->query("
    SELECT ch.*, u.nombre AS user_nombre
    FROM convenio_historial ch
    LEFT JOIN usuarios u ON u.id=ch.usuario_id
    WHERE ch.convenio_id=$id ORDER BY ch.created_at DESC
")->fetchAll();

$pageTitle  = 'Convenio '.$conv['codigo'];
$activeMenu = 'comercial';

$ESTADOS = [
    'borrador'             =>['label'=>'Borrador',            'color'=>'#64748b','bg'=>'#f1f5f9'],
    'pendiente_aprobacion' =>['label'=>'Pendiente aprobacion','color'=>'#d97706','bg'=>'#fef9c3'],
    'aprobado'             =>['label'=>'Aprobado',            'color'=>'#16a34a','bg'=>'#dcfce7'],
    'rechazado'            =>['label'=>'Rechazado',           'color'=>'#dc2626','bg'=>'#fee2e2'],
    'en_produccion'        =>['label'=>'En produccion',       'color'=>'#7c3aed','bg'=>'#f5f3ff'],
    'entregado'            =>['label'=>'Entregado',           'color'=>'#0891b2','bg'=>'#e0f2fe'],
];
$es = $ESTADOS[$conv['estado']] ?? $ESTADOS['borrador'];

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
.sc{background:#fff;border-radius:14px;border:1px solid #e2e8f0;padding:1rem 1.2rem;margin-bottom:1rem}
.hist-item{display:flex;align-items:flex-start;gap:.75rem;padding:.5rem 0;border-bottom:.5px solid #f1f5f9}
.hist-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;margin-top:4px}
</style>

<div class="d-flex align-items-center gap-2 mb-3">
  <a href="index.php" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i></a>
  <h4 class="fw-bold mb-0"><?= htmlspecialchars($conv['nombre_colegio']) ?></h4>
  <span class="badge" style="background:<?= $es['color'] ?>;font-size:.75rem"><?= $es['label'] ?></span>
  <?php if ($conv['codigo']): ?>
    <span class="badge bg-light text-dark border"><?= htmlspecialchars($conv['codigo']) ?></span>
  <?php endif; ?>
  <?php if (in_array($conv['estado'],['borrador','rechazado']) && Auth::puede('convenios','editar')): ?>
    <a href="form.php?id=<?= $id ?>" class="btn btn-sm btn-outline-primary ms-auto">
      <i class="bi bi-pencil me-1"></i>Editar
    </a>
  <?php endif; ?>
  <a href="imprimir.php?id=<?= $id ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-printer me-1"></i>Imprimir
  </a>
</div>

<?php if (isset($_GET['ok'])): ?>
<div class="alert alert-success py-2 small">Convenio guardado correctamente.</div>
<?php endif; ?>

<div class="row g-3">
  <div class="col-lg-8">

    <!-- Info convenio -->
    <div class="sc">
      <div class="row g-3">
        <div class="col-md-6">
          <div class="text-muted small">Colegio</div>
          <div class="fw-bold"><?= htmlspecialchars($conv['nombre_colegio']) ?></div>
        </div>
        <div class="col-md-6">
          <div class="text-muted small">Comercial</div>
          <div class="fw-bold"><?= htmlspecialchars($conv['nombre_comercial']) ?></div>
        </div>
        <div class="col-md-4">
          <div class="text-muted small">Fecha convenio</div>
          <div><?= $conv['fecha_convenio'] ? date('d/m/Y',strtotime($conv['fecha_convenio'])) : '—' ?></div>
        </div>
        <div class="col-md-4">
          <div class="text-muted small">Vigencia</div>
          <div><?= $conv['vigencia_inicio']?date('d/m/Y',strtotime($conv['vigencia_inicio'])):'—' ?> al <?= $conv['vigencia_fin']?date('d/m/Y',strtotime($conv['vigencia_fin'])):'—' ?></div>
        </div>
        <div class="col-md-4">
          <div class="text-muted small">Valor total</div>
          <div class="fw-bold text-success fs-5">$<?= number_format($conv['valor_total'],0,',','.') ?></div>
        </div>
      </div>
    </div>

    <!-- Cursos -->
    <div class="sc">
      <h6 class="fw-bold mb-3"><i class="bi bi-journal-text me-2 text-primary"></i>Cursos del Convenio</h6>
      <div class="table-responsive">
        <table class="table table-sm" style="font-size:.82rem">
          <thead style="background:#f8fafc">
            <tr>
              <th>Curso</th><th>Estudiantes</th><th>Kit</th><th>Valor kit</th><th>Libro</th><th>Valor libro</th><th>Total</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($cursos as $cc): ?>
          <tr>
            <td class="fw-semibold"><?= htmlspecialchars($cc['nombre_curso']) ?></td>
            <td><?= $cc['num_estudiantes'] ?></td>
            <td><?= htmlspecialchars($cc['nombre_kit']??'—') ?></td>
            <td>$<?= number_format($cc['valor_kit'],0,',','.') ?></td>
            <td><?= $cc['incluye_libro']?htmlspecialchars($cc['nombre_libro']??'Si'):'No' ?></td>
            <td><?= $cc['incluye_libro']?'$'.number_format($cc['valor_libro'],0,',','.'):'—' ?></td>
            <td class="fw-bold text-success">$<?= number_format($cc['valor_total'],0,',','.') ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
          <tfoot>
            <tr style="border-top:2px solid #e2e8f0">
              <td colspan="2"><strong>Totales</strong></td>
              <td colspan="4"></td>
              <td class="fw-bold text-success">$<?= number_format($conv['valor_total'],0,',','.') ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>

  </div>

  <div class="col-lg-4">

    <!-- Documento -->
    <div class="sc">
      <h6 class="fw-bold mb-2"><i class="bi bi-file-earmark-pdf me-2 text-danger"></i>Documento Convenio</h6>
      <?php if ($conv['doc_convenio']): ?>
        <a href="<?= UPLOAD_URL.'convenios/'.htmlspecialchars($conv['doc_convenio']) ?>"
           target="_blank" class="btn btn-outline-danger w-100 fw-semibold">
          <i class="bi bi-file-earmark-pdf me-2"></i>Ver PDF firmado
        </a>
        <?php if ($conv['estado']==='aprobado' && $conv['nombre_user']): ?>
          <div class="text-success small mt-2 text-center">
            <i class="bi bi-shield-check me-1"></i>
            Aprobado por <?= htmlspecialchars($conv['nombre_user']) ?>
            <?php if ($conv['fecha_aprobacion']): ?>
              el <?= date('d/m/Y', strtotime($conv['fecha_aprobacion'])) ?>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <div class="text-center text-muted py-2 small">
          <i class="bi bi-cloud-arrow-up fs-3 d-block mb-1 text-muted"></i>
          Sin documento. <a href="form.php?id=<?= $id ?>">Subir PDF firmado</a>
        </div>
      <?php endif; ?>
    </div>

    <!-- Historial -->
    <div class="sc">
      <h6 class="fw-bold mb-2"><i class="bi bi-clock-history me-2"></i>Historial</h6>
      <?php foreach ($historial as $h):
        $hc = ['borrador'=>'#94a3b8','pendiente_aprobacion'=>'#d97706','aprobado'=>'#16a34a','rechazado'=>'#dc2626','en_produccion'=>'#7c3aed','entregado'=>'#0891b2'][$h['estado']] ?? '#94a3b8';
      ?>
      <div class="hist-item">
        <div class="hist-dot" style="background:<?= $hc ?>"></div>
        <div>
          <div class="fw-semibold" style="font-size:.8rem"><?= htmlspecialchars($ESTADOS[$h['estado']]['label']??$h['estado']) ?></div>
          <?php if ($h['comentario']): ?>
            <div class="text-muted" style="font-size:.75rem"><?= htmlspecialchars($h['comentario']) ?></div>
          <?php endif; ?>
          <div class="text-muted" style="font-size:.7rem">
            <?= $h['user_nombre']?htmlspecialchars($h['user_nombre']):'Sistema' ?>
            &middot; <?= date('d/m/Y H:i',strtotime($h['created_at'])) ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

  </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
