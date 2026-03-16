<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();

$db  = Database::get();
$id  = (int)($_GET['id'] ?? 0);
$doc = $_GET['doc'] ?? 'resumen'; // resumen | acta | transportista
if (!$id) { header('Location: ' . APP_URL . '/modules/despachos/'); exit; }

$des = $db->query("
    SELECT d.*, col.nombre AS colegio_nombre, col.ciudad AS municipio,
           col.ciudad AS direccion, col.telefono AS col_telefono,
           col.email AS col_email, NULL AS nit, NULL AS col_logo,
           u.nombre AS creado_por_nombre
    FROM despachos d
    LEFT JOIN colegios col ON col.id = d.colegio_id
    LEFT JOIN usuarios u   ON u.id   = d.creado_por
    WHERE d.id = $id
")->fetch();
if (!$des) { header('Location: ' . APP_URL . '/modules/despachos/'); exit; }

// Kits del despacho con sus contenidos completos
$kitsDespacho = $db->query("
    SELECT dk.cantidad AS kits_cantidad,
           k.id AS kit_id, k.codigo AS kit_codigo, k.nombre AS kit_nombre,
           k.descripcion AS kit_descripcion, k.costo_cop,
           (dk.cantidad * k.costo_cop) AS subtotal
    FROM despacho_kits dk
    JOIN kits k ON k.id = dk.kit_id
    WHERE dk.despacho_id = $id
    ORDER BY k.nombre
")->fetchAll();

// Para cada kit, cargar elementos y prototipos
$contenidoKits = [];
foreach ($kitsDespacho as $dk) {
    $kitId = $dk['kit_id'];
    $multip = (int)$dk['kits_cantidad'];

    // Elementos importados
    $elems = $db->query("
        SELECT ke.cantidad, e.codigo, e.nombre, e.unidad AS unidad_medida,
               c.nombre AS categoria,
               (ke.cantidad * $multip) AS total_unidades
        FROM kit_elementos ke
        JOIN elementos e ON e.id = ke.elemento_id
        JOIN categorias c ON c.id = e.categoria_id
        WHERE ke.kit_id = $kitId
        ORDER BY c.nombre, e.nombre
    ")->fetchAll();

    // Prototipos fabricados
    $protos = $db->query("
        SELECT kp.cantidad, kp.notas, p.codigo, p.nombre,
               p.tipo_fabricacion, p.material_principal,
               (kp.cantidad * $multip) AS total_unidades
        FROM kit_prototipos kp
        JOIN prototipos p ON p.id = kp.prototipo_id
        WHERE kp.kit_id = $kitId
        ORDER BY p.nombre
    ")->fetchAll();

    $contenidoKits[$kitId] = [
        'kit'      => $dk,
        'elementos'=> $elems,
        'protos'   => $protos,
    ];
}

// Totales generales de elementos (sumados entre todos los kits)
$resumenElementos = [];
foreach ($contenidoKits as $data) {
    foreach ($data['elementos'] as $e) {
        $key = $e['codigo'];
        if (!isset($resumenElementos[$key])) {
            $resumenElementos[$key] = $e;
            $resumenElementos[$key]['total_unidades'] = 0;
        }
        $resumenElementos[$key]['total_unidades'] += (int)$e['total_unidades'];
    }
    foreach ($data['protos'] as $p) {
        $key = 'PRO-' . $p['codigo'];
        if (!isset($resumenElementos[$key])) {
            $resumenElementos[$key] = $p;
            $resumenElementos[$key]['categoria'] = 'Prototipo fabricado';
            $resumenElementos[$key]['unidad'] = 'unidad';
            $resumenElementos[$key]['total_unidades'] = 0;
        }
        $resumenElementos[$key]['total_unidades'] += (int)$p['total_unidades'];
    }
}

$totalKits   = array_sum(array_column($kitsDespacho,'kits_cantidad'));
$totalCosto  = array_sum(array_column($kitsDespacho,'subtotal'));
$flete       = (float)($des['valor_flete_cop'] ?? 0);
$totalItems  = count($resumenElementos);
$totalUnids  = array_sum(array_column($resumenElementos,'total_unidades'));

$estColor = ['preparando'=>'warning','despachado'=>'info','entregado'=>'success','anulado'=>'danger'];
$estLabel = ['preparando'=>'En preparaci&#243;n','despachado'=>'Despachado','entregado'=>'Entregado','anulado'=>'Anulado'];
$pageTitle  = 'Despacho ' . $des['codigo'];
$activeMenu = 'despachos';

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- Botones de control (no imprimen) -->
<div class="d-flex justify-content-between align-items-center mb-4 no-print">
  <div class="d-flex align-items-center gap-2">
    <a href="<?= APP_URL ?>/modules/despachos/" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i></a>
    <h4 class="fw-bold mb-0">Despacho <?= htmlspecialchars($des['codigo']) ?></h4>
    <span class="badge bg-<?= $estColor[$des['estado']] ?>"><?= $estLabel[$des['estado']] ?></span>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <?php if ($des['estado']==='preparando'): ?>
    <a href="<?= APP_URL ?>/modules/despachos/form.php?id=<?= $id ?>" class="btn btn-outline-primary btn-sm">
      <i class="bi bi-pencil me-1"></i>Editar
    </a>
    <?php endif; ?>
    <div class="btn-group">
      <button onclick="mostrarDoc('resumen')" id="btnResumen"
              class="btn btn-sm btn-outline-secondary <?= $doc==='resumen'?'active':'' ?>">
        <i class="bi bi-file-text me-1"></i>Remisi&#243;n
      </button>
      <button onclick="mostrarDoc('acta')" id="btnActa"
              class="btn btn-sm btn-outline-success <?= $doc==='acta'?'active':'' ?>">
        <i class="bi bi-list-check me-1"></i>Acta de Entrega
      </button>
      <button onclick="mostrarDoc('transportista')" id="btnTransp"
              class="btn btn-sm btn-outline-info <?= $doc==='transportista'?'active':'' ?>">
        <i class="bi bi-truck me-1"></i>Gu&#237;a Transportista
      </button>
    </div>
    <!-- Dropdown PDF -->
    <div class="dropdown">
      <button class="btn btn-primary btn-sm dropdown-toggle fw-bold" data-bs-toggle="dropdown">
        <i class="bi bi-file-earmark-pdf me-1"></i>Generar PDF
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow">
        <li><h6 class="dropdown-header">Seleccionar documento</h6></li>
        <li>
          <a class="dropdown-item" href="<?= APP_URL ?>/modules/despachos/exportar_pdf.php?id=<?= $id ?>&doc=acta" target="_blank">
            <i class="bi bi-list-check text-success me-2"></i><strong>Acta de Entrega</strong>
            <div class="small text-muted ms-4">Detalle completo de materiales</div>
          </a>
        </li>
        <li>
          <a class="dropdown-item" href="<?= APP_URL ?>/modules/despachos/exportar_pdf.php?id=<?= $id ?>&doc=remision" target="_blank">
            <i class="bi bi-file-text text-primary me-2"></i><strong>Remisi&#243;n</strong>
            <div class="small text-muted ms-4">Resumen de kits y valores</div>
          </a>
        </li>
        <li>
          <a class="dropdown-item" href="<?= APP_URL ?>/modules/despachos/exportar_pdf.php?id=<?= $id ?>&doc=transportista" target="_blank">
            <i class="bi bi-truck text-info me-2"></i><strong>Gu&#237;a Transportista</strong>
            <div class="small text-muted ms-4">Documento para el mensajero</div>
          </a>
        </li>
        <li><hr class="dropdown-divider"></li>
        <li>
          <a class="dropdown-item small text-muted" href="<?= APP_URL ?>/modules/despachos/exportar_pdf.php?id=<?= $id ?>&doc=acta&print=1" target="_blank">
            <i class="bi bi-printer me-2"></i>Abrir e imprimir autom&#225;ticamente
          </a>
        </li>
      </ul>
    </div>
  </div>
</div>

<!-- ===================================================================
     DOCUMENTO 1: REMISION / RESUMEN EJECUTIVO
     =================================================================== -->
<div id="docResumen" class="doc-panel section-card <?= $doc!=='resumen'?'d-none':'' ?>">
  <div class="d-flex justify-content-between align-items-start mb-3 pb-3 border-bottom">
  <div>
    <img src="<?= APP_URL ?>/assets/img/logo_email.png" alt="ROBOTSchool"
         style="max-height:50px;" onerror="this.style.display='none'">
    <div class="small text-muted mt-1">Calle 75 #20b-62, Bogot&#225; D.C. | Tel: 318 654 1859</div>
    <div class="small text-muted">robotschoolcol@gmail.com | www.robotschool.com.co</div>
  </div>
  <div class="text-end">
    <div class="fw-bold font-monospace text-primary fs-5"><?= htmlspecialchars($des['codigo']) ?></div>
    <div class="small text-muted">Fecha: <?= date('d/m/Y', strtotime($des['fecha'])) ?></div>
    <div class="small text-muted">Elaborado: <?= date('d/m/Y H:i') ?></div>
  </div>
</div>

  <div class="text-center mb-4">
    <div style="font-size:1.2rem;font-weight:700;letter-spacing:1px;color:#1e2a3a;border-bottom:3px solid #3a72e8;display:inline-block;padding-bottom:4px;">
      REMISI&#211;N DE DESPACHO
    </div>
  </div>

  <!-- Info colegio y transporte -->
  <div class="row g-3 mb-4">
    <div class="col-6">
      <div class="p-3 rounded border">
        <div class="small text-muted fw-bold mb-2">DESTINATARIO</div>
        <div class="fw-bold"><?= htmlspecialchars($des['colegio_nombre'] ?? 'Sin colegio') ?></div>
        <?php if ($des['municipio']): ?><div class="small text-muted"><?= htmlspecialchars($des['municipio']) ?></div><?php endif; ?>
        <?php if ($des['col_telefono']): ?><div class="small">Tel: <?= htmlspecialchars($des['col_telefono']) ?></div><?php endif; ?>
        <?php if ($des['col_email']): ?><div class="small"><?= htmlspecialchars($des['col_email']) ?></div><?php endif; ?>
      </div>
    </div>
    <div class="col-6">
      <div class="p-3 rounded border">
        <div class="small text-muted fw-bold mb-2">TRANSPORTE</div>
        <div><span class="small text-muted">Empresa:</span> <strong><?= htmlspecialchars($des['transportadora'] ?: '&#8212;') ?></strong></div>
        <div><span class="small text-muted">Gu&#237;a:</span> <strong class="font-monospace"><?= htmlspecialchars($des['guia_transporte'] ?: '&#8212;') ?></strong></div>
        <div><span class="small text-muted">Flete:</span> <strong><?= $flete > 0 ? cop($flete) : '&#8212;' ?></strong></div>
        <div><span class="small text-muted">Recibe:</span> <strong><?= htmlspecialchars($des['nombre_recibe'] ?: '&#8212;') ?></strong></div>
      </div>
    </div>
  </div>

  <!-- Tabla kits -->
  <table class="table table-sm table-bordered mb-3" style="font-size:.88rem;">
    <thead style="background:#1e2a3a;color:white;">
      <tr>
        <th style="width:15%">C&#243;digo</th>
        <th>Kit / Producto</th>
        <th class="text-center" style="width:10%">Cant.</th>
        <th class="text-end" style="width:15%">Costo Unit.</th>
        <th class="text-end" style="width:15%">Subtotal</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($kitsDespacho as $dk): ?>
    <tr>
      <td class="font-monospace fw-bold"><?= htmlspecialchars($dk['kit_codigo']) ?></td>
      <td>
        <div class="fw-semibold"><?= htmlspecialchars($dk['kit_nombre']) ?></div>
        <?php if ($dk['kit_descripcion']): ?><div class="small text-muted"><?= htmlspecialchars(mb_substr($dk['kit_descripcion'],0,80)) ?></div><?php endif; ?>
      </td>
      <td class="text-center fw-bold"><?= $dk['kits_cantidad'] ?></td>
      <td class="text-end"><?= cop($dk['costo_cop']) ?></td>
      <td class="text-end fw-bold"><?= cop($dk['subtotal']) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr style="background:#f1f5f9;font-weight:700;">
        <td colspan="2" class="text-end">TOTAL (<?= $totalKits ?> kits):</td>
        <td class="text-center"><?= $totalKits ?></td>
        <td></td>
        <td class="text-end" style="color:#16a34a;"><?= cop($totalCosto) ?></td>
      </tr>
      <?php if ($flete > 0): ?>
      <tr style="background:#f8fafc;">
        <td colspan="4" class="text-end text-muted">Flete:</td>
        <td class="text-end"><?= cop($flete) ?></td>
      </tr>
      <tr style="background:#f1f5f9;font-weight:700;">
        <td colspan="4" class="text-end">TOTAL GENERAL:</td>
        <td class="text-end" style="color:#3a72e8;"><?= cop($totalCosto + $flete) ?></td>
      </tr>
      <?php endif; ?>
    </tfoot>
  </table>

  <?php if ($des['notas']): ?>
  <div class="mb-4 p-2 rounded" style="background:#fefce8;border:1px solid #fde047;font-size:.85rem;">
    <strong>Observaciones:</strong> <?= htmlspecialchars($des['notas']) ?>
  </div>
  <?php endif; ?>

  <div class="row mt-5 pt-3">
  <div class="col-4 text-center">
    <div style="border-top:1px solid #334155;padding-top:.5rem;">
      <div class="small fw-semibold">Elaborado por ROBOTSchool</div>
      <div class="small text-muted"><?= htmlspecialchars($des['creado_por_nombre'] ?? 'ROBOTSchool') ?></div>
    </div>
  </div>
  <div class="col-4 text-center">
    <div style="border-top:1px solid #334155;padding-top:.5rem;">
      <div class="small fw-semibold">Despachado por</div>
      <div class="small text-muted">&nbsp;</div>
    </div>
  </div>
  <div class="col-4 text-center">
    <div style="border-top:1px solid #334155;padding-top:.5rem;">
      <div class="small fw-semibold">Recibido a satisfacci&#243;n</div>
      <div class="small text-muted"><?= htmlspecialchars($des['nombre_recibe'] ?? '') ?></div>
      <?php if ($des['cargo_recibe']): ?><div class="small text-muted"><?= htmlspecialchars($des['cargo_recibe']) ?></div><?php endif; ?>
    </div>
  </div>
</div>
</div>

<!-- ===================================================================
     DOCUMENTO 2: ACTA DE ENTREGA DETALLADA (para el colegio)
     =================================================================== -->
<div id="docActa" class="doc-panel section-card <?= $doc!=='acta'?'d-none':'' ?>">
  <div class="d-flex justify-content-between align-items-start mb-3 pb-3 border-bottom">
  <div>
    <img src="<?= APP_URL ?>/assets/img/logo_email.png" alt="ROBOTSchool"
         style="max-height:50px;" onerror="this.style.display='none'">
    <div class="small text-muted mt-1">Calle 75 #20b-62, Bogot&#225; D.C. | Tel: 318 654 1859</div>
    <div class="small text-muted">robotschoolcol@gmail.com | www.robotschool.com.co</div>
  </div>
  <div class="text-end">
    <div class="fw-bold font-monospace text-primary fs-5"><?= htmlspecialchars($des['codigo']) ?></div>
    <div class="small text-muted">Fecha: <?= date('d/m/Y', strtotime($des['fecha'])) ?></div>
    <div class="small text-muted">Elaborado: <?= date('d/m/Y H:i') ?></div>
  </div>
</div>

  <div class="text-center mb-4">
    <div style="font-size:1.2rem;font-weight:700;letter-spacing:1px;color:#1e2a3a;border-bottom:3px solid #16a34a;display:inline-block;padding-bottom:4px;">
      ACTA DE ENTREGA DE MATERIALES
    </div>
    <div class="text-muted small mt-1">
      Detalle completo de materiales entregados al colegio
    </div>
  </div>

  <!-- Resumen -->
  <div class="row g-2 mb-4">
    <div class="col-3">
      <div class="text-center p-2 rounded border">
        <div class="fw-bold fs-4 text-primary"><?= $totalKits ?></div>
        <div class="small text-muted">Kits entregados</div>
      </div>
    </div>
    <div class="col-3">
      <div class="text-center p-2 rounded border">
        <div class="fw-bold fs-4 text-success"><?= $totalItems ?></div>
        <div class="small text-muted">Referencias</div>
      </div>
    </div>
    <div class="col-3">
      <div class="text-center p-2 rounded border">
        <div class="fw-bold fs-4 text-warning"><?= number_format($totalUnids) ?></div>
        <div class="small text-muted">Unidades totales</div>
      </div>
    </div>
    <div class="col-3">
      <div class="text-center p-2 rounded border">
        <div class="fw-bold fs-4" style="color:#3a72e8;"><?= date('d/m/Y', strtotime($des['fecha'])) ?></div>
        <div class="small text-muted">Fecha despacho</div>
      </div>
    </div>
  </div>

  <!-- Desglose por kit -->
  <?php foreach ($contenidoKits as $kitId => $data):
    $dk    = $data['kit'];
    $elems = $data['elementos'];
    $protos= $data['protos'];
    if (empty($elems) && empty($protos)) continue;
  ?>
  <div class="mb-4" style="page-break-inside:avoid;">
    <div class="d-flex align-items-center gap-2 mb-2 p-2 rounded"
         style="background:#1e2a3a;color:white;">
      <span class="font-monospace fw-bold"><?= htmlspecialchars($dk['kit_codigo']) ?></span>
      <span class="fw-bold"><?= htmlspecialchars($dk['kit_nombre']) ?></span>
      <span class="ms-auto badge bg-primary"><?= $dk['kits_cantidad'] ?> kit<?= $dk['kits_cantidad']>1?'s':'' ?></span>
    </div>

    <table class="table table-sm table-bordered mb-0" style="font-size:.83rem;">
      <thead style="background:#f1f5f9;">
        <tr>
          <th style="width:15%">C&#243;digo</th>
          <th>Descripci&#243;n / Material</th>
          <th style="width:12%">Tipo</th>
          <th class="text-center" style="width:10%">x Kit</th>
          <th class="text-center" style="width:10%">x <?= $dk['kits_cantidad'] ?> kits</th>
          <th class="text-center" style="width:8%">&#10003; Verif.</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($elems as $e): ?>
      <tr>
        <td class="font-monospace small text-primary"><?= htmlspecialchars($e['codigo']) ?></td>
        <td>
          <div class="fw-semibold"><?= htmlspecialchars($e['nombre']) ?></div>
          <div class="small text-muted"><?= htmlspecialchars($e['categoria']) ?></div>
        </td>
        <td><span class="badge bg-info bg-opacity-10 text-info border" style="border-color:#93c5fd !important;font-size:.65rem;">Componente</span></td>
        <td class="text-center fw-bold"><?= $e['cantidad'] ?></td>
        <td class="text-center fw-bold text-primary"><?= $e['total_unidades'] ?></td>
        <td class="text-center"><span style="font-size:1rem;">&#9633;</span></td>
      </tr>
      <?php endforeach; ?>
      <?php foreach ($protos as $p): ?>
      <tr style="background:#fafff7;">
        <td class="font-monospace small text-success"><?= htmlspecialchars($p['codigo']) ?></td>
        <td>
          <div class="fw-semibold"><?= htmlspecialchars($p['nombre']) ?></div>
          <div class="small text-muted">
            <?= htmlspecialchars($p['tipo_fabricacion'] ?? '') ?>
            <?= $p['material_principal'] ? ' &middot; ' . htmlspecialchars($p['material_principal']) : '' ?>
          </div>
        </td>
        <td><span class="badge bg-success bg-opacity-10 text-success border" style="border-color:#86efac !important;font-size:.65rem;">Prototipo</span></td>
        <td class="text-center fw-bold"><?= $p['cantidad'] ?></td>
        <td class="text-center fw-bold text-success"><?= $p['total_unidades'] ?></td>
        <td class="text-center"><span style="font-size:1rem;">&#9633;</span></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="background:#f8fafc;font-weight:600;">
          <td colspan="4" class="text-end">Subtotal <?= htmlspecialchars($dk['kit_codigo']) ?>:</td>
          <td class="text-center text-primary fw-bold">
            <?= array_sum(array_column($elems,'total_unidades')) + array_sum(array_column($protos,'total_unidades')) ?> uds
          </td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>
  <?php endforeach; ?>

  <!-- Resumen consolidado si hay varios kits diferentes -->
  <?php if (count($kitsDespacho) > 1): ?>
  <div class="mt-4" style="page-break-before:auto;">
    <div class="fw-bold mb-2 p-2 rounded" style="background:#3a72e8;color:white;letter-spacing:.5px;">
      RESUMEN CONSOLIDADO DE MATERIALES
    </div>
    <table class="table table-sm table-bordered mb-3" style="font-size:.83rem;">
      <thead style="background:#f1f5f9;">
        <tr>
          <th style="width:15%">C&#243;digo</th>
          <th>Material / Componente</th>
          <th style="width:15%">Categor&#237;a</th>
          <th class="text-center" style="width:12%">Total Uds</th>
          <th class="text-center" style="width:8%">&#10003;</th>
        </tr>
      </thead>
      <tbody>
      <?php
      uasort($resumenElementos, fn($a,$b) => strcmp($a['categoria']??'',$b['categoria']??'') ?: strcmp($a['nombre'],$b['nombre']));
      foreach ($resumenElementos as $e):
      ?>
      <tr>
        <td class="font-monospace small text-primary"><?= htmlspecialchars($e['codigo']) ?></td>
        <td class="fw-semibold"><?= htmlspecialchars($e['nombre']) ?></td>
        <td class="small text-muted"><?= htmlspecialchars($e['categoria']) ?></td>
        <td class="text-center fw-bold text-primary"><?= $e['total_unidades'] ?></td>
        <td class="text-center">&#9633;</td>
      </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr style="background:#f1f5f9;font-weight:700;">
          <td colspan="3" class="text-end">TOTAL UNIDADES:</td>
          <td class="text-center text-primary"><?= number_format($totalUnids) ?></td>
          <td></td>
        </tr>
      </tfoot>
    </table>
  </div>
  <?php endif; ?>

  <?php if ($des['notas']): ?>
  <div class="mb-3 p-2 rounded" style="background:#fefce8;border:1px solid #fde047;font-size:.85rem;">
    <strong>Observaciones:</strong> <?= htmlspecialchars($des['notas']) ?>
  </div>
  <?php endif; ?>

  <!-- Declaracion -->
  <div class="mb-4 p-3 rounded" style="background:#f8fafc;border:1px solid #e2e8f0;font-size:.85rem;line-height:1.6;">
    El colegio <strong><?= htmlspecialchars($des['colegio_nombre'] ?? '') ?></strong> certifica haber recibido
    a satisfacci&#243;n los materiales descritos en el presente documento, correspondientes al despacho
    <strong><?= htmlspecialchars($des['codigo']) ?></strong> con fecha <?= date('d/m/Y', strtotime($des['fecha'])) ?>,
    por parte de <strong>ROBOTSchool Colombia</strong>. Los materiales fueron revisados y se encontraron
    en buen estado y cantidad correcta seg&#250;n el presente inventario.
  </div>

  <div class="row mt-5 pt-3">
  <div class="col-4 text-center">
    <div style="border-top:1px solid #334155;padding-top:.5rem;">
      <div class="small fw-semibold">Elaborado por ROBOTSchool</div>
      <div class="small text-muted"><?= htmlspecialchars($des['creado_por_nombre'] ?? 'ROBOTSchool') ?></div>
    </div>
  </div>
  <div class="col-4 text-center">
    <div style="border-top:1px solid #334155;padding-top:.5rem;">
      <div class="small fw-semibold">Despachado por</div>
      <div class="small text-muted">&nbsp;</div>
    </div>
  </div>
  <div class="col-4 text-center">
    <div style="border-top:1px solid #334155;padding-top:.5rem;">
      <div class="small fw-semibold">Recibido a satisfacci&#243;n</div>
      <div class="small text-muted"><?= htmlspecialchars($des['nombre_recibe'] ?? '') ?></div>
      <?php if ($des['cargo_recibe']): ?><div class="small text-muted"><?= htmlspecialchars($des['cargo_recibe']) ?></div><?php endif; ?>
    </div>
  </div>
</div>
</div>

<!-- ===================================================================
     DOCUMENTO 3: GUIA TRANSPORTISTA (simple, solo bultos y destino)
     =================================================================== -->
<div id="docTransportista" class="doc-panel section-card <?= $doc!=='transportista'?'d-none':'' ?>">
  <div class="d-flex justify-content-between align-items-start mb-3 pb-3 border-bottom">
  <div>
    <img src="<?= APP_URL ?>/assets/img/logo_email.png" alt="ROBOTSchool"
         style="max-height:50px;" onerror="this.style.display='none'">
    <div class="small text-muted mt-1">Calle 75 #20b-62, Bogot&#225; D.C. | Tel: 318 654 1859</div>
    <div class="small text-muted">robotschoolcol@gmail.com | www.robotschool.com.co</div>
  </div>
  <div class="text-end">
    <div class="fw-bold font-monospace text-primary fs-5"><?= htmlspecialchars($des['codigo']) ?></div>
    <div class="small text-muted">Fecha: <?= date('d/m/Y', strtotime($des['fecha'])) ?></div>
    <div class="small text-muted">Elaborado: <?= date('d/m/Y H:i') ?></div>
  </div>
</div>

  <div class="text-center mb-4">
    <div style="font-size:1.2rem;font-weight:700;letter-spacing:1px;color:#1e2a3a;border-bottom:3px solid #0ea5e9;display:inline-block;padding-bottom:4px;">
      GU&#205;A DE TRANSPORTE
    </div>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-6">
      <div class="p-3 rounded border">
        <div class="small text-muted fw-bold mb-1">REMITENTE</div>
        <div class="fw-bold">ROBOTSchool Colombia</div>
        <div class="small">Calle 75 #20b-62, Bogot&#225;</div>
        <div class="small">Tel: 318 654 1859</div>
      </div>
    </div>
    <div class="col-6">
      <div class="p-3 rounded border">
        <div class="small text-muted fw-bold mb-1">DESTINATARIO</div>
        <div class="fw-bold"><?= htmlspecialchars($des['colegio_nombre'] ?? '') ?></div>
        <?php if ($des['municipio']): ?><div class="small"><?= htmlspecialchars($des['municipio']) ?></div><?php endif; ?>
        <?php if ($des['col_telefono']): ?><div class="small">Tel: <?= htmlspecialchars($des['col_telefono']) ?></div><?php endif; ?>
        <div class="small fw-bold mt-1">Recibe: <?= htmlspecialchars($des['nombre_recibe'] ?: 'Rector / Coordinador') ?></div>
      </div>
    </div>
    <div class="col-12">
      <div class="p-3 rounded" style="background:#1e2a3a;color:white;">
        <div class="row text-center">
          <div class="col-3">
            <div class="small opacity-75">N&#250;m. despacho</div>
            <div class="fw-bold font-monospace fs-6"><?= htmlspecialchars($des['codigo']) ?></div>
          </div>
          <div class="col-3">
            <div class="small opacity-75">Fecha</div>
            <div class="fw-bold"><?= date('d/m/Y', strtotime($des['fecha'])) ?></div>
          </div>
          <div class="col-3">
            <div class="small opacity-75">Gu&#237;a transportadora</div>
            <div class="fw-bold font-monospace"><?= htmlspecialchars($des['guia_transporte'] ?: '&#8212;') ?></div>
          </div>
          <div class="col-3">
            <div class="small opacity-75">Transportadora</div>
            <div class="fw-bold"><?= htmlspecialchars($des['transportadora'] ?: '&#8212;') ?></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Contenido simplificado para transportista -->
  <table class="table table-bordered mb-4" style="font-size:.9rem;">
    <thead style="background:#f1f5f9;">
      <tr>
        <th>#</th><th>Descripci&#243;n del paquete</th>
        <th class="text-center">Kits</th>
        <th class="text-center">Componentes aprox.</th>
        <th class="text-center">Fragil</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($kitsDespacho as $i => $dk):
      $numComp = 0;
      if (isset($contenidoKits[$dk['kit_id']])) {
          foreach ($contenidoKits[$dk['kit_id']]['elementos'] as $e) $numComp += $e['total_unidades'];
          foreach ($contenidoKits[$dk['kit_id']]['protos'] as $p) $numComp += $p['total_unidades'];
      }
    ?>
    <tr>
      <td class="text-center"><?= $i+1 ?></td>
      <td>
        <div class="fw-bold"><?= htmlspecialchars($dk['kit_nombre']) ?></div>
        <div class="small text-muted font-monospace"><?= htmlspecialchars($dk['kit_codigo']) ?></div>
      </td>
      <td class="text-center fw-bold"><?= $dk['kits_cantidad'] ?></td>
      <td class="text-center"><?= number_format($numComp) ?></td>
      <td class="text-center">&#9633; S&#237; &nbsp; &#9633; No</td>
    </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr style="background:#f1f5f9;font-weight:700;">
        <td colspan="2" class="text-end">TOTAL:</td>
        <td class="text-center text-primary"><?= $totalKits ?> kits</td>
        <td class="text-center text-primary"><?= number_format($totalUnids) ?> uds</td>
        <td></td>
      </tr>
    </tfoot>
  </table>

  <?php if ($des['notas']): ?>
  <div class="mb-3 p-2 rounded" style="background:#fef3c7;border:1px solid #fcd34d;font-size:.85rem;">
    <strong>&#9888; Instrucciones:</strong> <?= htmlspecialchars($des['notas']) ?>
  </div>
  <?php endif; ?>

  <div class="row mt-5 pt-3">
    <div class="col-4 text-center">
      <div style="border-top:1px solid #334155;padding-top:.5rem;">
        <div class="small fw-semibold">Entregado a transportadora</div>
        <div class="small text-muted">Firma y sello ROBOTSchool</div>
      </div>
    </div>
    <div class="col-4 text-center">
      <div style="border-top:1px solid #334155;padding-top:.5rem;">
        <div class="small fw-semibold">Recibido por transportadora</div>
        <div class="small text-muted">Firma, sello y fecha</div>
      </div>
    </div>
    <div class="col-4 text-center">
      <div style="border-top:1px solid #334155;padding-top:.5rem;">
        <div class="small fw-semibold">Entregado en destino</div>
        <div class="small text-muted">Firma y sello colegio</div>
      </div>
    </div>
  </div>
</div>



<script>
function mostrarDoc(tipo) {
  document.querySelectorAll('.doc-panel').forEach(d => d.classList.add('d-none'));
  document.querySelectorAll('[id^=btn]').forEach(b => b.classList.remove('active'));
  document.getElementById('doc' + tipo.charAt(0).toUpperCase() + tipo.slice(1)).classList.remove('d-none');
  const map = {resumen:'btnResumen', acta:'btnActa', transportista:'btnTransp'};
  document.getElementById(map[tipo])?.classList.add('active');
}
</script>

<style>
@media print {
  .no-print, nav, #sidebar, .sidebar, footer { display: none !important; }
  .section-card { box-shadow: none !important; }
  body { background: white !important; font-size: 11pt; }
  table { font-size: 9pt; }
  .doc-panel { padding: 0 !important; }
}
</style>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
