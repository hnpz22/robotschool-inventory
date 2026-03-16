<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
Auth::check();

$db = Database::get();

// ── Aplicar filtros (mismos que index.php) ──
$fEstado  = $_GET['estado']  ?? '';
$fSem     = $_GET['sem']     ?? '';
$fColegio = $_GET['colegio'] ?? '';
$fBusq    = trim($_GET['q']  ?? '');
$ids      = $_GET['ids']     ?? ''; // IDs específicos separados por coma

$where = ["1=1"];
if ($ids) {
    $idList = implode(',', array_map('intval', explode(',', $ids)));
    $where[] = "p.id IN ($idList)";
} else {
    if ($fEstado)  $where[] = "p.estado = " . $db->quote($fEstado);
    if ($fColegio) $where[] = "(p.colegio_nombre LIKE " . $db->quote('%'.$fColegio.'%') . ")";
    if ($fBusq)    $where[] = "(p.cliente_nombre LIKE " . $db->quote('%'.$fBusq.'%') . " OR p.woo_order_id LIKE " . $db->quote('%'.$fBusq.'%') . ")";
    if ($fSem) {
        switch ($fSem) {
            case 'verde':     $where[] = "p.estado NOT IN ('entregado','cancelado') AND DATEDIFF(CURDATE(),p.fecha_compra)<=5"; break;
            case 'amarillo':  $where[] = "p.estado NOT IN ('entregado','cancelado') AND DATEDIFF(CURDATE(),p.fecha_compra) BETWEEN 6 AND 7"; break;
            case 'rojo':      $where[] = "p.estado NOT IN ('entregado','cancelado') AND DATEDIFF(CURDATE(),p.fecha_compra)>7"; break;
        }
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
      CASE WHEN p.estado IN ('entregado','cancelado') THEN 1 ELSE 0 END ASC,
      CASE WHEN DATEDIFF(CURDATE(),p.fecha_compra)>7 THEN 0
           WHEN DATEDIFF(CURDATE(),p.fecha_compra)>5 THEN 1
           ELSE 2 END ASC,
      p.fecha_compra ASC
    LIMIT 300
")->fetchAll();

// Logo
$logoPath = APP_ROOT . '/assets/img/logo_oficial.png';
$logoB64  = file_exists($logoPath) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath)) : '';

$semColor = ['rojo'=>'#dc2626','amarillo'=>'#d97706','verde'=>'#16a34a','completado'=>'#64748b'];
$semLabel = ['rojo'=>'URGENTE','amarillo'=>'EN RIESGO','verde'=>'AL DIA','completado'=>'ENTREGADO'];
$estLabel = [
    'pendiente'=>'Pendiente','en_produccion'=>'En produccion',
    'listo_envio'=>'Listo envio','despachado'=>'Despachado',
    'entregado'=>'Entregado','cancelado'=>'Cancelado',
];

function fmt_fecha($f) {
    if (preg_match('#(\d{4})-(\d{2})-(\d{2})#',$f,$m)) return "{$m[3]}/{$m[2]}/{$m[1]}";
    return $f;
}
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Etiquetas de Envio &mdash; ROBOTSchool</title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: Arial, Helvetica, sans-serif; background: #e2e8f0; }

/* ── Toolbar ── */
.toolbar {
  position: fixed; top: 0; left: 0; right: 0; z-index: 100;
  background: #1e293b; color: #fff;
  padding: .55rem 1.5rem; display: flex; align-items: center; gap: .75rem;
}
.toolbar h1 { font-size: .88rem; font-weight: 700; flex: 1; }
.btn-t { padding: .38rem 1rem; border-radius: 6px; border: none; cursor: pointer; font-size: .82rem; font-weight: 700; }
.btn-rojo { background: #dc2626; color: #fff; }
.btn-gris { background: #475569; color: #fff; }
.hint { font-size: .71rem; color: #94a3b8; }

/* ── Hoja carta: 10 etiquetas (2 col x 5 fil) ── */
.pagina {
  width: 21.59cm;
  background: #fff;
  margin: 4.5rem auto 1.5rem;
  padding: .5cm .6cm;
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  grid-template-rows: repeat(5, auto);
  gap: .3cm;
  box-shadow: 0 4px 24px rgba(0,0,0,.15);
  page-break-after: always;
}

/* ── Etiqueta individual ── */
.etq {
  border: 1.5px solid #1e2d4f;
  border-radius: 6px;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  background: #fff;
  page-break-inside: avoid;
  height: 5.0cm; /* 5 filas * 5.0cm + 4 gaps * 0.3cm + padding = ~27cm carta */
}
.etq-bar  { height: 4px; flex-shrink: 0; }
.etq-head {
  background: #1e2d4f; color: #fff;
  display: flex; align-items: center; gap: 5px;
  padding: 3px 7px; flex-shrink: 0;
}
.etq-logo {
  width: 22px; height: 22px; object-fit: contain;
  background: #fff; border-radius: 3px; padding: 1px; flex-shrink: 0;
}
.etq-logo-txt {
  width: 22px; height: 22px; background: #fff; border-radius: 3px;
  display: flex; align-items: center; justify-content: center;
  font-size: 7px; font-weight: 900; color: #1e2d4f; flex-shrink: 0;
}
.etq-brand  { font-size: 7.5px; font-weight: 800; letter-spacing: .04em; line-height: 1.2; }
.etq-num    { font-size: 7px; opacity: .75; }
.etq-body   { padding: 4px 7px; flex: 1; display: flex; flex-direction: column; gap: 2px; overflow: hidden; }
.lbl        { font-size: 5.5px; text-transform: uppercase; letter-spacing: .07em; color: #9ca3af; font-weight: 700; }
.val        { font-size: 9.5px; color: #1e2d4f; font-weight: 700; line-height: 1.2; }
.val-sm     { font-size: 8px; color: #374151; font-weight: 600; line-height: 1.2; }
.g2         { display: grid; grid-template-columns: 1fr 1fr; gap: 4px; }
.div        { height: .5px; background: #e5e7eb; }
.kit-box    {
  background: #eff6ff; border-radius: 3px;
  padding: 3px 6px; border-left: 2px solid #2563eb;
}
.kit-box .lbl { color: #2563eb; }
.kit-box .val { font-size: 8px; color: #1e40af; }
.etq-foot {
  background: #f8fafc; border-top: 1px solid #e5e7eb;
  padding: 3px 7px; display: flex; justify-content: space-between; align-items: center;
  flex-shrink: 0;
}
.etq-foot .web { font-size: 6px; color: #9ca3af; }
.etq-foot .urg { font-size: 7px; font-weight: 800; letter-spacing: .05em; }

/* ── Impresión ── */
@media print {
  body { background: #fff; }
  .toolbar { display: none !important; }
  .pagina {
    margin: 0; box-shadow: none; width: 100%;
    padding: .5cm .5cm;
  }
  @page { size: letter portrait; margin: 0; }
}
</style>
</head>
<body>

<div class="toolbar">
  <button class="btn-t btn-gris" onclick="history.back()">&larr; Volver</button>
  <h1>
    Etiquetas de Env&iacute;o &mdash; <?= count($pedidos) ?> pedido(s)
    &mdash; 10 por p&aacute;gina carta
  </h1>
  <button class="btn-t btn-rojo" onclick="window.print()">&#128438; Imprimir / PDF</button>
  <span class="hint">Ctrl+P &rarr; Carta &rarr; Sin m&aacute;rgenes &rarr; Escala: 100%</span>
</div>

<?php if (empty($pedidos)): ?>
<div style="margin:6rem auto;text-align:center;color:#64748b;font-family:Arial">
  <div style="font-size:3rem;margin-bottom:1rem">&#128230;</div>
  <div style="font-size:1.2rem;font-weight:700">No hay pedidos para mostrar</div>
  <div style="margin-top:.5rem">Aplica filtros en la lista de pedidos y vuelve a imprimir</div>
  <button onclick="history.back()" style="margin-top:1rem;padding:.5rem 1.5rem;background:#1e293b;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:.9rem">Volver</button>
</div>
<?php else: ?>

<?php foreach (array_chunk($pedidos, 10) as $pagina): ?>
<div class="pagina">
<?php foreach ($pagina as $p):
  $sc  = $semColor[$p['semaforo']] ?? '#64748b';
  $sl  = $semLabel[$p['semaforo']] ?? '';
  $el  = $estLabel[$p['estado']]   ?? $p['estado'];
  $col = $p['colegio_bd'] ?: $p['colegio_nombre'];
  $dir = trim($p['direccion'] . ($p['ciudad'] ? ', '.$p['ciudad'] : ''));
?>
<div class="etq">
  <div class="etq-bar" style="background:<?= $sc ?>"></div>
  <div class="etq-head">
    <?php if ($logoB64): ?>
      <img src="<?= $logoB64 ?>" class="etq-logo" alt="RS">
    <?php else: ?>
      <div class="etq-logo-txt">RS</div>
    <?php endif; ?>
    <div>
      <div class="etq-brand">ROBOTSchool Colombia</div>
      <div class="etq-num">
        Pedido #<?= htmlspecialchars($p['woo_order_id']) ?>
        &nbsp;&middot;&nbsp;
        <?= fmt_fecha($p['fecha_compra']) ?>
      </div>
    </div>
  </div>

  <div class="etq-body">

    <div>
      <div class="lbl">Destinatario</div>
      <div class="val" style="font-size:12px"><?= htmlspecialchars($p['cliente_nombre']) ?></div>
    </div>

    <div class="div"></div>

    <div class="g2">
      <div>
        <div class="lbl">Tel&eacute;fono</div>
        <div class="val-sm"><?= htmlspecialchars($p['cliente_telefono'] ?: '&mdash;') ?></div>
      </div>
      <div>
        <div class="lbl">Estado</div>
        <div class="val-sm" style="color:<?= $sc ?>;font-weight:800"><?= $el ?></div>
      </div>
    </div>

    <div>
      <div class="lbl">Direcci&oacute;n de entrega</div>
      <div class="val-sm"><?= htmlspecialchars($dir ?: '&mdash;') ?></div>
    </div>

    <?php if ($col): ?>
    <div>
      <div class="lbl">Colegio</div>
      <div class="val-sm" style="color:#1d4ed8;font-weight:700"><?= htmlspecialchars($col) ?></div>
    </div>
    <?php endif; ?>

    <div class="kit-box">
      <div class="lbl">Producto / Kit</div>
      <div class="val"><?= htmlspecialchars(mb_strimwidth($p['kit_nombre'] ?? '', 0, 55, '...')) ?></div>
    </div>

    <?php if ($p['guia_envio']): ?>
    <div class="g2">
      <div>
        <div class="lbl">Gu&iacute;a</div>
        <div class="val-sm" style="font-weight:800"><?= htmlspecialchars($p['guia_envio']) ?></div>
      </div>
      <div>
        <div class="lbl">Transportadora</div>
        <div class="val-sm"><?= htmlspecialchars($p['transportadora'] ?? '&mdash;') ?></div>
      </div>
    </div>
    <?php endif; ?>

  </div>

  <div class="etq-foot">
    <span class="web">robotschool.com.co &nbsp;&middot;&nbsp; 318 654 1859</span>
    <span class="urg" style="color:<?= $sc ?>"><?= $sl ?></span>
  </div>
</div>
<?php endforeach; ?>
</div>
<?php endforeach; ?>

<?php endif; ?>
</body>
</html>
