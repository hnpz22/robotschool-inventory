<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
Auth::check();

$db = Database::get();

$fEstado  = $_GET['estado']  ?? '';
$fSem     = $_GET['sem']     ?? '';
$fColegio = $_GET['colegio'] ?? '';
$fBusq    = trim($_GET['q']  ?? '');

$where = ["1=1"];
if ($fEstado)  $where[] = "p.estado = " . $db->quote($fEstado);
if ($fColegio) $where[] = "p.colegio_nombre LIKE " . $db->quote('%'.$fColegio.'%');
if ($fBusq)    $where[] = "(p.cliente_nombre LIKE " . $db->quote('%'.$fBusq.'%') . " OR p.woo_order_id LIKE " . $db->quote('%'.$fBusq.'%') . ")";
if ($fSem) {
    switch ($fSem) {
        case 'verde':     $where[] = "p.estado NOT IN ('entregado','cancelado') AND DATEDIFF(CURDATE(),p.fecha_compra)<=5"; break;
        case 'amarillo':  $where[] = "p.estado NOT IN ('entregado','cancelado') AND DATEDIFF(CURDATE(),p.fecha_compra) BETWEEN 6 AND 7"; break;
        case 'rojo':      $where[] = "p.estado NOT IN ('entregado','cancelado') AND DATEDIFF(CURDATE(),p.fecha_compra)>7"; break;
        case 'completado':$where[] = "p.estado IN ('entregado','cancelado')"; break;
    }
}
$whereStr = implode(' AND ', $where);

$pedidos = $db->query("
    SELECT p.*,
           DATEDIFF(CURDATE(),p.fecha_compra) AS dias,
           CASE
             WHEN p.estado IN ('entregado','cancelado') THEN 'completado'
             WHEN DATEDIFF(CURDATE(),p.fecha_compra)<=5 THEN 'verde'
             WHEN DATEDIFF(CURDATE(),p.fecha_compra)<=7 THEN 'amarillo'
             ELSE 'rojo'
           END AS semaforo,
           col.nombre AS colegio_bd
    FROM tienda_pedidos p
    LEFT JOIN colegios col ON col.id=p.colegio_id
    WHERE $whereStr
    ORDER BY
      CASE WHEN p.estado IN ('entregado','cancelado') THEN 1 ELSE 0 END,
      CASE WHEN DATEDIFF(CURDATE(),p.fecha_compra)>7 THEN 0
           WHEN DATEDIFF(CURDATE(),p.fecha_compra)>5 THEN 1
           ELSE 2 END,
      p.fecha_compra ASC
    LIMIT 500
")->fetchAll();

$logoPath = APP_ROOT . '/assets/img/logo_oficial.png';
$logoB64  = file_exists($logoPath) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath)) : '';

$semColor = ['rojo'=>'#dc2626','amarillo'=>'#d97706','verde'=>'#16a34a','completado'=>'#64748b'];
$estLabel = [
    'pendiente'=>'Pendiente','en_produccion'=>'En produccion',
    'listo_envio'=>'Listo envio','despachado'=>'Despachado',
    'entregado'=>'Entregado','cancelado'=>'Cancelado',
];

function fmt_f($f) {
    if (preg_match('#(\d{4})-(\d{2})-(\d{2})#',$f,$m)) return "{$m[3]}/{$m[2]}/{$m[1]}";
    return $f;
}

// Agrupar por colegio
$porColegio = [];
foreach ($pedidos as $p) {
    $key = $p['colegio_bd'] ?: ($p['colegio_nombre'] ?: '— Sin colegio —');
    $porColegio[$key][] = $p;
}
ksort($porColegio);
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Lista de Pedidos &mdash; ROBOTSchool</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, Helvetica, sans-serif; font-size: 9pt; color: #1e293b; background: #f1f5f9; }

.toolbar {
  position: fixed; top: 0; left: 0; right: 0; z-index: 100;
  background: #1e293b; color: #fff;
  padding: .5rem 1.5rem; display: flex; align-items: center; gap: .75rem;
}
.toolbar h1 { font-size: .85rem; font-weight: 700; flex: 1; }
.btn-t { padding: .35rem .9rem; border-radius: 6px; border: none; cursor: pointer; font-size: .8rem; font-weight: 700; }
.btn-rojo { background: #dc2626; color: #fff; }
.btn-gris { background: #475569; color: #fff; }

/* Hoja carta */
.hoja {
  width: 21.59cm;
  min-height: 27.94cm;
  background: #fff;
  margin: 4.5rem auto 1.5rem;
  padding: 1cm 1.2cm;
  box-shadow: 0 4px 24px rgba(0,0,0,.12);
}

/* Header del reporte */
.rep-header {
  display: flex; align-items: center; justify-content: space-between;
  border-bottom: 3px solid #1e293b; padding-bottom: .4cm; margin-bottom: .4cm;
}
.rep-logo { height: 40px; }
.rep-info { text-align: right; font-size: 7.5pt; color: #64748b; line-height: 1.5; }
.rep-info strong { font-size: 9pt; color: #1e293b; display: block; }
.rep-title { text-align: center; margin-bottom: .3cm; }
.rep-title h2 { font-size: 12pt; font-weight: 700; }
.rep-title p  { font-size: 7.5pt; color: #64748b; margin-top: 2px; }
.rep-meta {
  display: flex; gap: 1.5rem; font-size: 7pt; color: #64748b;
  background: #f8fafc; border-radius: 5px; padding: .2cm .4cm;
  margin-bottom: .4cm;
}

/* Stats */
.stats { display: flex; gap: .5rem; margin-bottom: .4cm; flex-wrap: wrap; }
.stat-box { border: 1px solid #e2e8f0; border-radius: 5px; padding: .15cm .35cm; text-align: center; min-width: 2cm; }
.stat-val { font-size: 11pt; font-weight: 700; }
.stat-lbl { font-size: 6.5pt; color: #64748b; }

/* Sección por colegio */
.sec-colegio { margin-bottom: .5cm; page-break-inside: avoid; }
.sec-header {
  background: #1e293b; color: #fff;
  padding: .2cm .4cm; border-radius: 4px 4px 0 0;
  display: flex; justify-content: space-between; align-items: center;
  font-size: 8.5pt; font-weight: 700;
}
.sec-count { font-size: 7.5pt; opacity: .8; }

/* Tabla */
table { width: 100%; border-collapse: collapse; font-size: 7.5pt; }
thead th {
  background: #f1f5f9; color: #475569;
  padding: .18cm .3cm; text-align: left;
  font-size: 7pt; font-weight: 700; letter-spacing: .03em;
  border-bottom: 1px solid #e2e8f0;
}
tbody td { padding: .16cm .3cm; border-bottom: .5px solid #f1f5f9; vertical-align: middle; }
tbody tr:last-child td { border-bottom: none; }
.sem-bar { width: 4px; border-radius: 2px; height: 18px; display: inline-block; vertical-align: middle; }
.fw { font-weight: 700; }
.muted { color: #64748b; }

/* Footer */
.rep-footer {
  margin-top: .5cm; padding-top: .3cm; border-top: 1px solid #e2e8f0;
  display: flex; justify-content: space-between; font-size: 7pt; color: #94a3b8;
}

@media print {
  body { background: #fff; }
  .toolbar { display: none !important; }
  .hoja { margin: 0; box-shadow: none; width: 100%; min-height: auto; padding: .8cm 1cm; }
  @page { size: letter portrait; margin: 0; }
  .sec-colegio { page-break-inside: avoid; }
}
</style>
</head>
<body>

<div class="toolbar">
  <button class="btn-t btn-gris" onclick="history.back()">&larr; Volver</button>
  <h1>Lista de Pedidos &mdash; <?= count($pedidos) ?> pedidos &mdash; <?= count($porColegio) ?> colegios</h1>
  <button class="btn-t btn-rojo" onclick="window.print()">&#128438; Imprimir / PDF</button>
</div>

<div class="hoja">

  <!-- Header con logo -->
  <div class="rep-header">
    <div>
      <?php if ($logoB64): ?>
        <img src="<?= $logoB64 ?>" class="rep-logo" alt="ROBOTSchool">
      <?php else: ?>
        <strong style="font-size:14pt;color:#1e293b;">ROBOT<span style="color:#dc2626">School</span></strong>
      <?php endif; ?>
    </div>
    <div class="rep-info">
      <strong>ROBOTSchool Colombia</strong>
      Calle 75 #20b-62, Bogot&aacute;<br>
      318 654 1859 &nbsp;&middot;&nbsp; robotschool.com.co
    </div>
  </div>

  <div class="rep-title">
    <h2>Lista de Pedidos &mdash; Tienda Online</h2>
    <p>Generado: <?= date('d/m/Y H:i') ?> &nbsp;&middot;&nbsp; <?= count($pedidos) ?> pedidos en <?= count($porColegio) ?> colegios</p>
  </div>

  <!-- Stats -->
  <?php
  $rojos    = count(array_filter($pedidos, function($p){ return $p['semaforo']==='rojo'; }));
  $amarillos= count(array_filter($pedidos, function($p){ return $p['semaforo']==='amarillo'; }));
  $verdes   = count(array_filter($pedidos, function($p){ return $p['semaforo']==='verde'; }));
  ?>
  <div class="stats">
    <div class="stat-box"><div class="stat-val" style="color:#dc2626"><?= $rojos ?></div><div class="stat-lbl">Urgentes</div></div>
    <div class="stat-box"><div class="stat-val" style="color:#d97706"><?= $amarillos ?></div><div class="stat-lbl">En riesgo</div></div>
    <div class="stat-box"><div class="stat-val" style="color:#16a34a"><?= $verdes ?></div><div class="stat-lbl">Al d&iacute;a</div></div>
    <div class="stat-box"><div class="stat-val"><?= count($pedidos) ?></div><div class="stat-lbl">Total</div></div>
    <div class="stat-box"><div class="stat-val"><?= count($porColegio) ?></div><div class="stat-lbl">Colegios</div></div>
  </div>

  <!-- Pedidos agrupados por colegio -->
  <?php foreach ($porColegio as $colegio => $lista): ?>
  <div class="sec-colegio">
    <div class="sec-header">
      <span>&#x1F3EB; <?= htmlspecialchars($colegio) ?></span>
      <span class="sec-count"><?= count($lista) ?> pedido(s)</span>
    </div>
    <table>
      <thead>
        <tr>
          <th style="width:10px"></th>
          <th>#Pedido</th>
          <th>Fecha</th>
          <th>Cliente</th>
          <th>Tel&eacute;fono</th>
          <th>Producto / Kit</th>
          <th>D&iacute;as</th>
          <th>Estado</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($lista as $p):
        $sc = $semColor[$p['semaforo']] ?? '#64748b';
        $el = $estLabel[$p['estado']]   ?? $p['estado'];
      ?>
      <tr>
        <td><span class="sem-bar" style="background:<?= $sc ?>"></span></td>
        <td class="fw">#<?= htmlspecialchars($p['woo_order_id']) ?></td>
        <td style="white-space:nowrap"><?= fmt_f($p['fecha_compra']) ?></td>
        <td class="fw"><?= htmlspecialchars($p['cliente_nombre']) ?></td>
        <td class="muted"><?= htmlspecialchars($p['cliente_telefono'] ?? '&mdash;') ?></td>
        <td style="max-width:5cm;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($p['kit_nombre']??'') ?>">
          <?= htmlspecialchars(mb_strimwidth($p['kit_nombre']??'',0,45,'...')) ?>
        </td>
        <td style="text-align:center;font-weight:700;color:<?= $sc ?>"><?= $p['dias'] ?>d</td>
        <td style="color:<?= $sc ?>;font-weight:700;white-space:nowrap"><?= $el ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endforeach; ?>

  <div class="rep-footer">
    <span>ROBOTSchool Colombia &middot; Sistema de Inventario</span>
    <span>Generado: <?= date('d/m/Y H:i:s') ?></span>
  </div>

</div>
</body>
</html>
