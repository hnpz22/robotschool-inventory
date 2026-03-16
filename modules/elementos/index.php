<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();

$db = Database::get();
$pageTitle  = 'Inventario de Elementos';
$activeMenu = 'elementos';

// ── Filtros ──
$buscar    = trim($_GET['q']    ?? '');
$catId     = (int)($_GET['cat'] ?? 0);
$semFiltro = trim($_GET['sem']  ?? '');
$vista     = $_GET['vista']     ?? 'tabla'; // tabla | tarjetas | categorias
$pagina    = max(1, (int)($_GET['p'] ?? 1));
$porPagina = 50;

$where  = ["e.activo=1"];
$params = [];
if ($buscar) {
    $where[]  = "(e.nombre LIKE ? OR e.codigo LIKE ? OR e.descripcion LIKE ? OR c.nombre LIKE ?)";
    $params   = array_merge($params, ["%$buscar%","%$buscar%","%$buscar%","%$buscar%"]);
}
if ($catId)              { $where[] = "e.categoria_id=?";                                               $params[] = $catId; }
if ($semFiltro==='rojo')     { $where[] = "e.stock_actual<=0"; }
elseif ($semFiltro==='amarillo') { $where[] = "e.stock_actual>0 AND e.stock_actual<=e.stock_minimo"; }
elseif ($semFiltro==='verde')    { $where[] = "e.stock_actual>e.stock_minimo AND e.stock_actual<e.stock_maximo"; }
elseif ($semFiltro==='azul')     { $where[] = "e.stock_actual>=e.stock_maximo"; }

$whereStr = implode(' AND ', $where);

// Total para paginación
$stTotal = $db->prepare("SELECT COUNT(*) FROM elementos e JOIN categorias c ON c.id=e.categoria_id WHERE $whereStr");
$stTotal->execute($params);
$total = (int)$stTotal->fetchColumn();
$totalPags = max(1, ceil($total / $porPagina));
$offset = ($pagina - 1) * $porPagina;

// Consulta principal
$st = $db->prepare("
    SELECT e.*, c.nombre AS cat_nombre, c.color AS cat_color, c.prefijo, c.icono AS cat_icono,
           pv.nombre AS prov_nombre,
           (e.stock_actual * e.costo_real_cop) AS valor_total
    FROM elementos e
    JOIN categorias c ON c.id=e.categoria_id
    LEFT JOIN proveedores pv ON pv.id=e.proveedor_id
    WHERE $whereStr
    ORDER BY c.nombre, e.nombre
    LIMIT $porPagina OFFSET $offset
");
$st->execute($params);
$elementos = $st->fetchAll();

// Stats globales (siempre sin filtro de paginación)
$stats = $db->query("
    SELECT
      COUNT(*) AS total,
      SUM(CASE WHEN e.stock_actual<=0 THEN 1 ELSE 0 END) AS rojos,
      SUM(CASE WHEN e.stock_actual>0 AND e.stock_actual<=e.stock_minimo THEN 1 ELSE 0 END) AS amarillos,
      SUM(CASE WHEN e.stock_actual>e.stock_minimo AND e.stock_actual<e.stock_maximo THEN 1 ELSE 0 END) AS verdes,
      SUM(e.stock_actual * e.costo_real_cop) AS valor_total
    FROM elementos e WHERE e.activo=1
")->fetch();

// Categorías con conteo para filtro lateral
$categorias = $db->query("
    SELECT c.id, c.nombre, c.color, c.prefijo, c.icono,
           COUNT(e.id) AS total_elem,
           SUM(CASE WHEN e.stock_actual<=0 THEN 1 ELSE 0 END) AS sin_stock
    FROM categorias c
    LEFT JOIN elementos e ON e.categoria_id=c.id AND e.activo=1
    WHERE c.activa=1
    GROUP BY c.id
    ORDER BY c.nombre
")->fetchAll();

// Vista por categorías: agrupar elementos
$porCat = [];
if ($vista === 'categorias') {
    foreach ($elementos as $e) $porCat[$e['cat_nombre']][] = $e;
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
.sem-dot{width:10px;height:10px;border-radius:50%;display:inline-block;flex-shrink:0}
.sem-rojo    {background:#ef4444}.sem-amarillo{background:#f59e0b}
.sem-verde   {background:#22c55e}.sem-azul    {background:#3b82f6}
.cat-sidebar{background:#fff;border-radius:14px;border:1px solid #e2e8f0;overflow:hidden}
.cat-item{display:flex;align-items:center;gap:.5rem;padding:.5rem .75rem;cursor:pointer;text-decoration:none;color:#374151;font-size:.82rem;border-bottom:.5px solid #f1f5f9}
.cat-item:hover,.cat-item.active{background:#f0f9ff;color:#185FA5}
.cat-item .cat-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
.cat-item .cnt{margin-left:auto;font-size:.72rem;color:#94a3b8}
.cat-item .warn{color:#ef4444;font-size:.7rem;font-weight:700}
.section-card{background:#fff;border-radius:14px;border:1px solid #e2e8f0;padding:1rem 1.2rem;margin-bottom:1rem}
.elem-card{background:#fff;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;transition:.15s;cursor:pointer}
.elem-card:hover{border-color:#3b82f6;box-shadow:0 4px 12px rgba(59,130,246,.15)}
.elem-foto{width:100%;height:80px;object-fit:cover}
.elem-foto-ph{width:100%;height:80px;background:#f8fafc;display:flex;align-items:center;justify-content:center;font-size:1.6rem;color:#94a3b8}
.stock-bar-wrap{height:4px;background:#f1f5f9;border-radius:2px;overflow:hidden}
.stock-bar-fill{height:100%;border-radius:2px;transition:.3s}
.vista-btn{padding:.3rem .7rem;border-radius:6px;border:.5px solid #e2e8f0;background:#fff;font-size:.8rem;cursor:pointer;color:#374151}
.vista-btn.active{background:#1e293b;color:#fff;border-color:#1e293b}
.table-inv th{background:#1e293b;color:#fff;font-size:.74rem;padding:.5rem .75rem;font-weight:600;white-space:nowrap}
.table-inv td{font-size:.8rem;padding:.45rem .75rem;vertical-align:middle;border-bottom:1px solid #f1f5f9}
.cat-group-header{background:#f8fafc;border-left:4px solid var(--cat-color, #3b82f6);padding:.5rem .75rem;font-weight:700;font-size:.82rem;margin:0}
.badge-cat{font-size:.7rem;padding:.18rem .5rem;border-radius:20px;font-weight:600}
</style>

<!-- Header -->
<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h4 class="fw-bold mb-0">&#x1F4E6; Inventario de Elementos</h4>
    <p class="text-muted small mb-0"><?= number_format($total) ?> elementos &mdash; Valor total: <strong>$<?= number_format($stats['valor_total']??0,0,',','.') ?> COP</strong></p>
  </div>
  <div class="d-flex gap-2">
    <a href="<?= APP_URL ?>/modules/kits/constructor.php" class="btn btn-success btn-sm">
      <i class="bi bi-tools me-1"></i>Armar Kit
    </a>
    <a href="<?= APP_URL ?>/modules/elementos/form.php" class="btn btn-primary btn-sm">
      <i class="bi bi-plus-lg me-1"></i>Nuevo Elemento
    </a>
  </div>
</div>

<!-- Stats semáforo -->
<div class="d-flex flex-wrap gap-2 mb-3">
  <a href="?sem=rojo<?= $catId?"&cat=$catId":'' ?>"    class="text-decoration-none" style="background:#fee2e2;color:#991b1b;border-radius:10px;padding:.4rem .9rem;font-size:.82rem;font-weight:600;display:inline-flex;align-items:center;gap:.4rem"><span class="sem-dot sem-rojo"></span><?= $stats['rojos'] ?> Sin stock</a>
  <a href="?sem=amarillo<?= $catId?"&cat=$catId":'' ?>" class="text-decoration-none" style="background:#fef9c3;color:#854d0e;border-radius:10px;padding:.4rem .9rem;font-size:.82rem;font-weight:600;display:inline-flex;align-items:center;gap:.4rem"><span class="sem-dot sem-amarillo"></span><?= $stats['amarillos'] ?> Stock bajo</a>
  <a href="?sem=verde<?= $catId?"&cat=$catId":'' ?>"   class="text-decoration-none" style="background:#dcfce7;color:#166534;border-radius:10px;padding:.4rem .9rem;font-size:.82rem;font-weight:600;display:inline-flex;align-items:center;gap:.4rem"><span class="sem-dot sem-verde"></span><?= $stats['verdes'] ?> OK</a>
  <a href="?"                                           class="text-decoration-none" style="background:#f8fafc;color:#475569;border:1px solid #e2e8f0;border-radius:10px;padding:.4rem .9rem;font-size:.82rem;font-weight:600;display:inline-flex;align-items:center;gap:.4rem"><i class="bi bi-list"></i><?= number_format($stats['total']) ?> Total</a>
</div>

<div class="row g-3">

  <!-- ── Sidebar categorías ── -->
  <div class="col-lg-2 col-md-3">
    <div class="cat-sidebar">
      <div style="padding:.6rem .75rem;background:#1e293b;color:#fff;font-size:.78rem;font-weight:700">
        <i class="bi bi-grid me-1"></i>Categor&iacute;as
      </div>
      <a href="?vista=<?= $vista ?>" class="cat-item <?= !$catId?'active':'' ?>">
        <span class="cat-dot" style="background:#475569"></span>
        <span>Todas</span>
        <span class="cnt"><?= number_format($stats['total']) ?></span>
      </a>
      <?php foreach ($categorias as $cat): ?>
      <a href="?cat=<?= $cat['id'] ?>&vista=<?= $vista ?>" class="cat-item <?= $catId==$cat['id']?'active':'' ?>">
        <span class="cat-dot" style="background:<?= $cat['color'] ?>"></span>
        <span style="flex:1"><?= htmlspecialchars($cat['nombre']) ?></span>
        <?php if ($cat['sin_stock'] > 0): ?>
          <span class="warn">&#x26A0;<?= $cat['sin_stock'] ?></span>
        <?php endif; ?>
        <span class="cnt"><?= $cat['total_elem'] ?></span>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- ── Contenido principal ── -->
  <div class="col-lg-10 col-md-9">

    <!-- Barra de búsqueda y vistas -->
    <div class="section-card mb-2">
      <form method="GET" class="d-flex gap-2 align-items-center flex-wrap">
        <input type="hidden" name="cat"   value="<?= $catId ?>">
        <input type="hidden" name="sem"   value="<?= $semFiltro ?>">
        <input type="hidden" name="vista" value="<?= $vista ?>">
        <div class="input-group input-group-sm flex-grow-1" style="max-width:400px">
          <span class="input-group-text"><i class="bi bi-search"></i></span>
          <input type="text" name="q" class="form-control" placeholder="Buscar por nombre, c&oacute;digo, categor&iacute;a..." value="<?= htmlspecialchars($buscar) ?>">
          <?php if ($buscar): ?>
          <a href="?cat=<?= $catId ?>&vista=<?= $vista ?>" class="btn btn-outline-secondary btn-sm">&#10005;</a>
          <?php endif; ?>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Buscar</button>

        <!-- Selector de vista -->
        <div class="d-flex gap-1 ms-auto">
          <a href="?<?= http_build_query(array_merge($_GET,['vista'=>'tabla'])) ?>"
             class="vista-btn <?= $vista==='tabla'?'active':'' ?>" title="Vista tabla">
            <i class="bi bi-table"></i>
          </a>
          <a href="?<?= http_build_query(array_merge($_GET,['vista'=>'tarjetas'])) ?>"
             class="vista-btn <?= $vista==='tarjetas'?'active':'' ?>" title="Vista tarjetas">
            <i class="bi bi-grid-3x3-gap"></i>
          </a>
          <a href="?<?= http_build_query(array_merge($_GET,['vista'=>'categorias'])) ?>"
             class="vista-btn <?= $vista==='categorias'?'active':'' ?>" title="Vista por categor&iacute;as">
            <i class="bi bi-collection"></i>
          </a>
          <a href="<?= APP_URL ?>/modules/reportes/?tipo=inventario&cat=<?= $catId ?>" target="_blank"
             class="vista-btn" title="Exportar reporte">
            <i class="bi bi-file-earmark-spreadsheet"></i>
          </a>
        </div>
      </form>
    </div>

    <!-- ══ VISTA TABLA ══ -->
    <?php if ($vista === 'tabla'): ?>
    <div class="section-card p-0" style="overflow:hidden">
      <div class="table-responsive">
        <table class="table table-hover mb-0 table-inv">
          <thead><tr>
            <th style="width:50px">Foto</th>
            <th>C&oacute;digo</th>
            <th>Nombre</th>
            <th>Categor&iacute;a</th>
            <th style="text-align:center">Stock</th>
            <th>Sem.</th>
            <th class="text-end">Costo COP</th>
            <th style="text-align:center">Peso</th>
            <th>Ubicaci&oacute;n</th>
            <th style="width:90px">Acciones</th>
          </tr></thead>
          <tbody>
          <?php foreach ($elementos as $e):
            $sem = semaforo($e['stock_actual'],$e['stock_minimo'],$e['stock_maximo']);
            $pct = $e['stock_maximo']>0 ? min(100,round($e['stock_actual']/$e['stock_maximo']*100)) : 0;
          ?>
          <tr>
            <td>
              <?php if ($e['foto']): ?>
                <img src="<?= UPLOAD_URL.htmlspecialchars($e['foto']) ?>" style="width:36px;height:36px;object-fit:cover;border-radius:6px" alt="">
              <?php else: ?>
                <div style="width:36px;height:36px;background:#f0f4ff;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#94a3b8"><i class="bi bi-cpu"></i></div>
              <?php endif; ?>
            </td>
            <td><code style="font-size:.75rem;color:#185FA5"><?= htmlspecialchars($e['codigo']) ?></code></td>
            <td>
              <div class="fw-semibold" style="font-size:.82rem"><?= htmlspecialchars($e['nombre']) ?></div>
              <?php if ($e['prov_nombre']): ?><div class="text-muted" style="font-size:.71rem"><?= htmlspecialchars($e['prov_nombre']) ?></div><?php endif; ?>
              <?php if (!empty($e['es_consumible'])): ?>
                <span style="font-size:.65rem;background:#fef9c3;color:#854d0e;padding:.1rem .35rem;border-radius:20px">consumible</span>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge-cat" style="background:<?= $e['cat_color'] ?>20;color:<?= $e['cat_color'] ?>;border:1px solid <?= $e['cat_color'] ?>40">
                <?= htmlspecialchars($e['prefijo']) ?>
              </span>
            </td>
            <td style="text-align:center">
              <div class="fw-bold"><?= number_format($e['stock_actual']) ?> <span class="text-muted fw-normal" style="font-size:.72rem">/ <?= $e['stock_maximo'] ?></span></div>
              <div class="stock-bar-wrap mt-1">
                <div class="stock-bar-fill" style="width:<?= $pct ?>%;background:<?= $sem['color']==='danger'?'#ef4444':($sem['color']==='warning'?'#f59e0b':($sem['color']==='info'?'#3b82f6':'#22c55e')) ?>"></div>
              </div>
            </td>
            <td><span class="sem-dot sem-<?= $sem['color']==="danger"?"rojo":($sem['color']==="warning"?"amarillo":($sem['color']==="info"?"azul":"verde")) ?>"></span></td>
            <td class="text-end fw-semibold" style="font-size:.8rem"><?= $e['costo_real_cop']>0 ? cop($e['costo_real_cop']) : '&mdash;' ?></td>
            <td style="text-align:center;font-size:.78rem;color:#64748b"><?= $e['peso_gramos']>0 ? number_format($e['peso_gramos'],1).'g' : '—' ?></td>
            <td style="font-size:.75rem;color:#64748b"><?= htmlspecialchars($e['ubicacion']??'—') ?></td>
            <td>
              <div class="d-flex gap-1">
                <a href="<?= APP_URL ?>/modules/elementos/form.php?id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-primary" style="padding:.2rem .4rem" title="Editar"><i class="bi bi-pencil"></i></a>
                <?php if (Auth::isAdmin()): ?>
                <a href="<?= APP_URL ?>/modules/elementos/delete.php?id=<?= $e['id'] ?>&csrf=<?= Auth::csrfToken() ?>"
                   class="btn btn-sm btn-outline-danger" style="padding:.2rem .4rem" title="Eliminar"
                   onclick="return confirm('Eliminar <?= addslashes($e['nombre']) ?>?')">
                   <i class="bi bi-trash"></i>
                </a>
                <?php endif; ?>
                <a href="<?= APP_URL ?>/modules/kits/constructor.php" class="btn btn-sm btn-outline-success" style="padding:.2rem .4rem" title="Usar en kit"><i class="bi bi-tools"></i></a>
                <a href="<?= APP_URL ?>/modules/inventario/movimientos.php?elem=<?= $e['id'] ?>" class="btn btn-sm btn-outline-secondary" style="padding:.2rem .4rem" title="Movimientos"><i class="bi bi-clock-history"></i></a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if (empty($elementos)): ?>
            <tr><td colspan="10" class="text-center text-muted py-5"><i class="bi bi-search fs-2 d-block mb-2"></i>No se encontraron elementos</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- ══ VISTA TARJETAS ══ -->
    <?php elseif ($vista === 'tarjetas'): ?>
    <div class="row g-2">
      <?php foreach ($elementos as $e):
        $sem = semaforo($e['stock_actual'],$e['stock_minimo'],$e['stock_maximo']);
        $pct = $e['stock_maximo']>0 ? min(100,round($e['stock_actual']/$e['stock_maximo']*100)) : 0;
      ?>
      <div class="col-6 col-md-3 col-lg-2">
        <div class="elem-card h-100">
          <?php if ($e['foto']): ?>
            <img src="<?= UPLOAD_URL.htmlspecialchars($e['foto']) ?>" class="elem-foto" alt="">
          <?php else: ?>
            <div class="elem-foto-ph"><i class="bi <?= htmlspecialchars($e['cat_icono']??'bi-cpu') ?>"></i></div>
          <?php endif; ?>
          <div style="padding:.5rem .6rem">
            <div style="font-size:.7rem;font-weight:700;color:<?= $e['cat_color'] ?>"><?= htmlspecialchars($e['prefijo']) ?></div>
            <div class="fw-semibold" style="font-size:.75rem;line-height:1.25;margin:.1rem 0"><?= htmlspecialchars(mb_strimwidth($e['nombre'],0,30,'...')) ?></div>
            <code style="font-size:.65rem;color:#94a3b8"><?= htmlspecialchars($e['codigo']) ?></code>
            <div class="d-flex align-items-center justify-content-between mt-1">
              <span class="fw-bold" style="font-size:.82rem"><?= number_format($e['stock_actual']) ?></span>
              <span class="sem-dot sem-<?= ($sem['color']==='danger'?'rojo':($sem['color']==='warning'?'amarillo':($sem['color']==='info'?'azul':'verde'))) ?>"></span>
            </div>
            <div class="stock-bar-wrap mt-1">
              <div class="stock-bar-fill" style="width:<?= $pct ?>%;background:<?= $sem['color']==='danger'?'#ef4444':($sem['color']==='warning'?'#f59e0b':($sem['color']==='info'?'#3b82f6':'#22c55e')) ?>"></div>
            </div>
            <?php if ($e['costo_real_cop']>0): ?>
              <div style="font-size:.72rem;color:#16a34a;font-weight:600;margin-top:.2rem"><?= cop($e['costo_real_cop']) ?></div>
            <?php endif; ?>
          </div>
          <div style="padding:.3rem .6rem;background:#f8fafc;border-top:.5px solid #e2e8f0;display:flex;gap:.3rem">
            <a href="<?= APP_URL ?>/modules/elementos/form.php?id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-primary w-50 py-0" style="font-size:.68rem"><i class="bi bi-pencil me-1"></i>Editar</a>
            <?php if (Auth::isAdmin()): ?>
            <a href="<?= APP_URL ?>/modules/elementos/delete.php?id=<?= $e['id'] ?>&csrf=<?= Auth::csrfToken() ?>"
               class="btn btn-sm btn-outline-danger py-0" style="font-size:.68rem;width:48%"
               onclick="return confirm('Eliminar <?= addslashes($e['nombre']) ?>?')">
               <i class="bi bi-trash me-1"></i>Eliminar
            </a>
            <?php endif; ?>
            <a href="<?= APP_URL ?>/modules/kits/constructor.php" class="btn btn-sm btn-success w-50 py-0" style="font-size:.68rem">Kit</a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($elementos)): ?>
        <div class="col-12 text-center text-muted py-5"><i class="bi bi-search fs-2 d-block mb-2"></i>No se encontraron elementos</div>
      <?php endif; ?>
    </div>

    <!-- ══ VISTA POR CATEGORÍAS ══ -->
    <?php elseif ($vista === 'categorias'): ?>
    <?php foreach ($porCat as $catNombre => $elems):
      $catColor = $elems[0]['cat_color'] ?? '#475569';
      $catPrefijo = $elems[0]['prefijo'] ?? '';
      $sinStock = count(array_filter($elems, function($e){ return $e['stock_actual']<=0; }));
    ?>
    <div class="section-card p-0 mb-3" style="overflow:hidden;border-left:4px solid <?= $catColor ?>">
      <!-- Header categoría -->
      <div class="d-flex align-items-center justify-content-between px-3 py-2" style="background:<?= $catColor ?>15;border-bottom:1px solid <?= $catColor ?>30">
        <div class="d-flex align-items-center gap-2">
          <span style="width:10px;height:10px;border-radius:50%;background:<?= $catColor ?>;display:inline-block"></span>
          <span class="fw-bold"><?= htmlspecialchars($catNombre) ?></span>
          <span style="font-size:.75rem;background:<?= $catColor ?>;color:#fff;padding:.1rem .45rem;border-radius:20px"><?= count($elems) ?> elementos</span>
          <?php if ($sinStock>0): ?>
            <span style="font-size:.72rem;background:#fee2e2;color:#991b1b;padding:.1rem .4rem;border-radius:20px">&#x26A0; <?= $sinStock ?> sin stock</span>
          <?php endif; ?>
        </div>
        <div style="font-size:.78rem;color:#64748b;font-weight:600">
          Valor: $<?= number_format(array_sum(array_column($elems,'valor_total') ?: [0]),0,',','.') ?> COP
        </div>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0" style="font-size:.79rem">
          <thead>
            <tr style="background:#f8fafc">
              <th style="padding:.4rem .75rem;font-size:.72rem;color:#64748b;font-weight:600">C&oacute;digo</th>
              <th style="padding:.4rem .75rem;font-size:.72rem;color:#64748b;font-weight:600">Nombre</th>
              <th style="padding:.4rem .75rem;font-size:.72rem;color:#64748b;font-weight:600;text-align:center">Stock</th>
              <th style="padding:.4rem .75rem;font-size:.72rem;color:#64748b;font-weight:600">Sem.</th>
              <th style="padding:.4rem .75rem;font-size:.72rem;color:#64748b;font-weight:600;text-align:right">Costo</th>
              <th style="padding:.4rem .75rem;font-size:.72rem;color:#64748b;font-weight:600;text-align:right">Valor Total</th>
              <th style="padding:.4rem .75rem;font-size:.72rem;color:#64748b;font-weight:600">Ubicaci&oacute;n</th>
              <th style="padding:.4rem .75rem;font-size:.72rem;color:#64748b;font-weight:600;width:70px"></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($elems as $e):
            $sem = semaforo($e['stock_actual'],$e['stock_minimo'],$e['stock_maximo']);
            $pct = $e['stock_maximo']>0 ? min(100,round($e['stock_actual']/$e['stock_maximo']*100)) : 0;
          ?>
          <tr>
            <td style="padding:.4rem .75rem"><code style="font-size:.72rem;color:#185FA5"><?= htmlspecialchars($e['codigo']) ?></code></td>
            <td style="padding:.4rem .75rem">
              <div class="fw-semibold"><?= htmlspecialchars($e['nombre']) ?></div>
              <?php if ($e['prov_nombre']): ?><div class="text-muted" style="font-size:.7rem"><?= htmlspecialchars($e['prov_nombre']) ?></div><?php endif; ?>
            </td>
            <td style="padding:.4rem .75rem;text-align:center">
              <span class="fw-bold"><?= number_format($e['stock_actual']) ?></span>
              <span class="text-muted" style="font-size:.7rem">/ <?= $e['stock_maximo'] ?></span>
            </td>
            <td style="padding:.4rem .75rem"><span class="sem-dot sem-<?= $sem['color']==="danger"?"rojo":($sem['color']==="warning"?"amarillo":($sem['color']==="info"?"azul":"verde")) ?>"></span></td>
            <td style="padding:.4rem .75rem;text-align:right;font-weight:600"><?= $e['costo_real_cop']>0 ? cop($e['costo_real_cop']) : '&mdash;' ?></td>
            <td style="padding:.4rem .75rem;text-align:right;color:#16a34a;font-weight:600">$<?= number_format(($e['valor_total']??0),0,',','.') ?></td>
            <td style="padding:.4rem .75rem;color:#94a3b8;font-size:.72rem"><?= htmlspecialchars($e['ubicacion']??'—') ?></td>
            <td style="padding:.4rem .75rem">
              <div class="d-flex gap-1">
                <a href="<?= APP_URL ?>/modules/elementos/form.php?id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-primary py-0 px-1" style="font-size:.72rem" title="Editar"><i class="bi bi-pencil"></i></a>
                <?php if (Auth::isAdmin()): ?>
                <a href="<?= APP_URL ?>/modules/elementos/delete.php?id=<?= $e['id'] ?>&csrf=<?= Auth::csrfToken() ?>"
                   class="btn btn-sm btn-outline-danger py-0 px-1" style="font-size:.72rem" title="Eliminar"
                   onclick="return confirm('Eliminar <?= addslashes($e['nombre']) ?>?')">
                   <i class="bi bi-trash"></i>
                </a>
                <?php endif; ?>
                <a href="<?= APP_URL ?>/modules/kits/constructor.php" class="btn btn-sm btn-outline-success py-0 px-1" style="font-size:.72rem" title="Usar en kit"><i class="bi bi-tools"></i></a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($porCat)): ?>
      <div class="text-center text-muted py-5"><i class="bi bi-search fs-2 d-block mb-2"></i>No se encontraron elementos</div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Paginación -->
    <?php if ($totalPags > 1 && $vista !== 'categorias'): ?>
    <nav class="mt-3">
      <ul class="pagination pagination-sm justify-content-center mb-0">
        <?php for ($i=1; $i<=$totalPags; $i++):
          $qp = array_merge($_GET, ['p'=>$i]);
        ?>
          <li class="page-item <?= $i==$pagina?'active':'' ?>">
            <a class="page-link" href="?<?= http_build_query($qp) ?>"><?= $i ?></a>
          </li>
        <?php endfor; ?>
      </ul>
    </nav>
    <?php endif; ?>

  </div><!-- /col principal -->
</div><!-- /row -->

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
