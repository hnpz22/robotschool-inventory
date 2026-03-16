<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();

$db         = Database::get();
$pageTitle  = 'Calendario Sabados';
$activeMenu = 'matriculas';

// Generar sabados de los ultimos 3 meses + proximos 2 meses
function generarSabados($mesesAtras = 3, $mesesAdelante = 2) {
    $sabados = [];
    $inicio = strtotime("-$mesesAtras months", strtotime('last saturday'));
    $fin    = strtotime("+$mesesAdelante months");
    $actual = $inicio;
    // Ajustar al sabado
    $dow = date('w', $actual);
    if ($dow !== '6') {
        $actual = strtotime('last saturday', $actual);
    }
    while ($actual <= $fin) {
        $sabados[] = date('Y-m-d', $actual);
        $actual = strtotime('+7 days', $actual);
    }
    return $sabados;
}

$sabados = generarSabados(3, 2);

// Pagos por sabado
$pagosSQL = $db->query("
    SELECT
        DATE(sabado_ref) AS sabado,
        COUNT(*) AS num_pagos,
        SUM(valor_pagado) AS total,
        SUM(CASE WHEN medio_pago='efectivo' THEN valor_pagado ELSE 0 END) AS efectivo,
        SUM(CASE WHEN medio_pago IN ('nequi','daviplata','transferencia','tarjeta') THEN valor_pagado ELSE 0 END) AS digital
    FROM pagos
    WHERE estado='pagado' AND sabado_ref IS NOT NULL
    GROUP BY DATE(sabado_ref)
")->fetchAll(PDO::FETCH_UNIQUE);

// Matriculas activas por sabado (aproximacion: las que estaban activas ese dia)
$matriculasActivas = $db->query("
    SELECT COUNT(*) AS total, MIN(fecha_matricula) AS primera
    FROM matriculas WHERE estado IN ('activa','pendiente_pago')
")->fetch();

// Stats totales
$stats = $db->query("
    SELECT
        SUM(valor_pagado) AS total_recaudado,
        SUM(CASE WHEN medio_pago='efectivo' THEN valor_pagado ELSE 0 END) AS total_efectivo,
        SUM(CASE WHEN medio_pago IN ('nequi','daviplata','transferencia','tarjeta') THEN valor_pagado ELSE 0 END) AS total_digital,
        COUNT(DISTINCT DATE(sabado_ref)) AS num_sabados,
        COUNT(*) AS num_pagos
    FROM pagos WHERE estado='pagado'
")->fetch();

// Pendientes
$pendientes = $db->query("
    SELECT COUNT(*) AS cnt, SUM(valor-descuento) AS monto
    FROM pagos WHERE estado='pendiente'
")->fetch();

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
.sc{background:#fff;border-radius:14px;border:1px solid #e2e8f0;padding:1rem 1.2rem;margin-bottom:1rem}
.sab-card{background:#fff;border:1px solid #e2e8f0;border-radius:10px;padding:.75rem;margin-bottom:.5rem;transition:.15s}
.sab-card:hover{border-color:#93c5fd;box-shadow:0 2px 8px rgba(0,0,0,.06)}
.sab-card.hoy{border-color:#22c55e;background:#f0fdf4}
.sab-card.futuro{opacity:.65;background:#f8fafc}
.sab-card.con-pagos{border-left:4px solid #22c55e}
.medio-bar{height:6px;border-radius:3px;overflow:hidden;background:#e2e8f0;margin-top:4px}
.medio-efectivo{height:100%;background:#22c55e;float:left;border-radius:3px 0 0 3px}
.medio-digital{height:100%;background:#3b82f6;float:right;border-radius:0 3px 3px 0}
.stat-box{background:#fff;border-radius:12px;border:1px solid #e2e8f0;padding:1rem;text-align:center}
</style>

<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h4 class="fw-bold mb-0">&#x1F4C5; Calendario de S&aacute;bados</h4>
    <p class="text-muted small mb-0">Resumen de pagos y actividad por semana</p>
  </div>
  <a href="pagos.php" class="btn btn-success btn-sm">
    <i class="bi bi-plus-lg me-1"></i>Registrar pago
  </a>
</div>

<!-- Stats globales -->
<div class="row g-3 mb-3">
  <div class="col-6 col-md-3">
    <div class="stat-box">
      <div class="fs-4 fw-bold text-success">$<?= number_format($stats['total_recaudado']??0,0,',','.') ?></div>
      <div class="text-muted small">Total recaudado</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-box">
      <div class="fs-4 fw-bold text-primary"><?= $stats['num_pagos'] ?? 0 ?></div>
      <div class="text-muted small">Pagos registrados</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-box">
      <div class="fs-4 fw-bold text-warning">$<?= number_format($pendientes['monto']??0,0,',','.') ?></div>
      <div class="text-muted small"><?= $pendientes['cnt']??0 ?> pagos pendientes</div>
    </div>
  </div>
  <div class="col-6 col-md-3">
    <div class="stat-box">
      <div class="d-flex justify-content-center gap-2">
        <div>
          <div class="fw-bold text-success" style="font-size:.95rem">$<?= number_format($stats['total_efectivo']??0,0,',','.') ?></div>
          <div class="text-muted" style="font-size:.7rem">Efectivo</div>
        </div>
        <div class="border-start ps-2">
          <div class="fw-bold text-primary" style="font-size:.95rem">$<?= number_format($stats['total_digital']??0,0,',','.') ?></div>
          <div class="text-muted" style="font-size:.7rem">Digital</div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Calendario de sabados -->
<div class="row g-3">
  <div class="col-lg-8">
    <div class="sc">
      <h6 class="fw-bold mb-3">S&aacute;bados (ultimos 3 meses + pr&oacute;ximos 2)</h6>
      <?php
      $hoy = date('Y-m-d');
      $sabadoHoy = date('Y-m-d', strtotime('last saturday'));
      if (date('w') == 6) $sabadoHoy = $hoy;

      foreach (array_reverse($sabados) as $sab):
        $pago  = $pagosSQL[$sab] ?? null;
        $esFut = $sab > $sabadoHoy;
        $esHoy = $sab === $sabadoHoy;
        $total = $pago['total'] ?? 0;
        $ef    = $pago['efectivo'] ?? 0;
        $dig   = $pago['digital']  ?? 0;
        $pctEf = $total > 0 ? round($ef/$total*100) : 0;
        $pctDig= $total > 0 ? round($dig/$total*100) : 0;
        $clase = $esHoy ? 'hoy' : ($esFut ? 'futuro' : ($pago ? 'con-pagos' : ''));
      ?>
      <div class="sab-card <?= $clase ?>">
        <div class="d-flex align-items-center justify-content-between">
          <div>
            <div class="d-flex align-items-center gap-2">
              <span class="fw-bold" style="font-size:.9rem">
                <?= date('d \d\e F Y', strtotime($sab)) ?>
              </span>
              <?php if ($esHoy): ?>
                <span class="badge bg-success" style="font-size:.68rem">HOY</span>
              <?php elseif ($esFut): ?>
                <span class="badge bg-light text-muted border" style="font-size:.68rem">Pr&oacute;ximo</span>
              <?php endif; ?>
            </div>
            <?php if ($pago): ?>
              <div class="text-muted small"><?= $pago['num_pagos'] ?> pago(s) registrado(s)</div>
            <?php elseif (!$esFut): ?>
              <div class="text-muted small">Sin pagos registrados</div>
            <?php endif; ?>
          </div>
          <div class="text-end">
            <?php if ($total > 0): ?>
              <div class="fw-bold text-success" style="font-size:1rem">$<?= number_format($total,0,',','.') ?></div>
              <div class="d-flex gap-2 justify-content-end" style="font-size:.72rem">
                <span class="text-success"><i class="bi bi-cash me-1"></i>$<?= number_format($ef,0,',','.') ?></span>
                <span class="text-primary"><i class="bi bi-phone me-1"></i>$<?= number_format($dig,0,',','.') ?></span>
              </div>
              <div class="medio-bar" style="width:120px;margin-left:auto">
                <div class="medio-efectivo" style="width:<?= $pctEf ?>%"></div>
                <div class="medio-digital" style="width:<?= $pctDig ?>%"></div>
              </div>
            <?php elseif (!$esFut): ?>
              <span class="text-muted small">$0</span>
            <?php endif; ?>
          </div>
          <?php if (!$esFut): ?>
            <a href="pagos.php?sabado=<?= $sab ?>" class="btn btn-sm btn-outline-primary ms-2 py-0 px-2" style="font-size:.72rem">
              Ver pagos
            </a>
          <?php else: ?>
            <a href="nueva_matricula.php" class="btn btn-sm btn-outline-success ms-2 py-0 px-2" style="font-size:.72rem">
              Matricular
            </a>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Panel lateral -->
  <div class="col-lg-4">
    <!-- Medios de pago -->
    <div class="sc">
      <h6 class="fw-bold mb-3"><i class="bi bi-pie-chart me-2 text-primary"></i>Por medio de pago</h6>
      <?php
      $medios = $db->query("
          SELECT medio_pago, COUNT(*) AS cnt, SUM(valor_pagado) AS total
          FROM pagos WHERE estado='pagado'
          GROUP BY medio_pago ORDER BY total DESC
      ")->fetchAll();
      $totalMedios = array_sum(array_column($medios,'total'));
      $medioColors = ['efectivo'=>'#22c55e','nequi'=>'#a855f7','daviplata'=>'#ef4444','transferencia'=>'#3b82f6','tarjeta'=>'#f59e0b','otro'=>'#94a3b8'];
      foreach ($medios as $m):
        $pct = $totalMedios > 0 ? round($m['total']/$totalMedios*100) : 0;
        $color = $medioColors[$m['medio_pago']] ?? '#94a3b8';
      ?>
      <div class="mb-2">
        <div class="d-flex justify-content-between" style="font-size:.8rem">
          <span class="fw-semibold"><?= ucfirst($m['medio_pago']) ?></span>
          <span class="text-muted"><?= $pct ?>% &mdash; $<?= number_format($m['total'],0,',','.') ?></span>
        </div>
        <div class="cupo-bar" style="height:5px;border-radius:3px;background:#e2e8f0">
          <div style="height:100%;width:<?= $pct ?>%;background:<?= $color ?>;border-radius:3px"></div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($medios)): ?>
        <div class="text-center text-muted small py-3">Sin pagos registrados aun</div>
      <?php endif; ?>
    </div>

    <!-- Accesos rapidos -->
    <div class="sc">
      <h6 class="fw-bold mb-3">Accesos r&aacute;pidos</h6>
      <div class="d-grid gap-2">
        <a href="pagos.php" class="btn btn-success btn-sm"><i class="bi bi-cash me-1"></i>Registrar pago</a>
        <a href="nueva_matricula.php" class="btn btn-primary btn-sm"><i class="bi bi-person-plus me-1"></i>Nueva matr&iacute;cula</a>
        <a href="estudiantes.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-people me-1"></i>Ver estudiantes</a>
      </div>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
