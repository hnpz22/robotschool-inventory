<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();

$db = Database::get();
$pageTitle  = 'Movimientos de Inventario';
$activeMenu = 'movimientos';
$error = $success = '';

// ── ELIMINAR MOVIMIENTO (revierte el stock) ──────────────────────────────────
if (isset($_GET['del']) && Auth::csrfVerify($_GET['csrf'] ?? '')) {
    Auth::requireAdmin();
    $delId = (int)$_GET['del'];
    try {
        $db->beginTransaction();
        $mov = $db->query("SELECT * FROM movimientos WHERE id=$delId")->fetch();
        if (!$mov) throw new Exception('Movimiento no encontrado.');

        // Revertir el stock: deshacer el delta aplicado
        $delta = (int)$mov['cantidad'];
        $db->prepare("UPDATE elementos SET stock_actual = stock_actual - ? WHERE id=?")
           ->execute([$delta, $mov['elemento_id']]);

        // Verificar que no quede negativo
        $nuevoStock = $db->query("SELECT stock_actual FROM elementos WHERE id={$mov['elemento_id']}")->fetchColumn();
        if ($nuevoStock < 0) throw new Exception('No se puede eliminar: el stock quedaría negativo ('.$nuevoStock.').');

        $db->prepare("DELETE FROM movimientos WHERE id=?")->execute([$delId]);
        $db->commit();
        $success = 'Movimiento eliminado y stock revertido correctamente.';
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error = $e->getMessage();
    }
}

// ── EDITAR MOVIMIENTO ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='editar_movimiento') {
    if (!Auth::csrfVerify($_POST['csrf']??'')) die('CSRF');
    Auth::requireAdmin();
    $editId = (int)$_POST['movimiento_id'];
    try {
        $db->beginTransaction();
        $movOld = $db->query("SELECT * FROM movimientos WHERE id=$editId")->fetch();
        if (!$movOld) throw new Exception('Movimiento no encontrado.');

        $nuevoMotivo = trim($_POST['motivo'] ?? '');
        $nuevoRef    = trim($_POST['referencia'] ?? '');
        $nuevaCant   = (int)$_POST['cantidad'];
        $nuevoTipo   = $_POST['tipo'];

        if (!$nuevaCant) throw new Exception('La cantidad no puede ser cero.');

        // Calcular delta original y nuevo
        $deltaOld = (int)$movOld['cantidad'];
        $deltaNew = in_array($nuevoTipo, ['salida']) ? -abs($nuevaCant) : abs($nuevaCant);
        if ($nuevoTipo === 'ajuste') $deltaNew = $nuevaCant;

        // Revertir delta viejo y aplicar nuevo
        $stockActual = (int)$db->query("SELECT stock_actual FROM elementos WHERE id={$movOld['elemento_id']}")->fetchColumn();
        $stockSinViejo = $stockActual - $deltaOld;
        $stockNuevo    = $stockSinViejo + $deltaNew;

        if ($stockNuevo < 0) throw new Exception("Stock insuficiente. Con este ajuste el stock quedaría en $stockNuevo.");

        $db->prepare("UPDATE elementos SET stock_actual=? WHERE id=?")
           ->execute([$stockNuevo, $movOld['elemento_id']]);

        $db->prepare("UPDATE movimientos SET tipo=?, cantidad=?, stock_despues=?, motivo=?, referencia=? WHERE id=?")
           ->execute([$nuevoTipo, $deltaNew, $stockNuevo, $nuevoMotivo ?: null, $nuevoRef ?: null, $editId]);

        $db->commit();
        $success = 'Movimiento actualizado correctamente.';
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error = $e->getMessage();
    }
}

// ── REGISTRAR MOVIMIENTO ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='nuevo_movimiento') {
    if (!Auth::csrfVerify($_POST['csrf']??'')) die('CSRF');
    try {
        $elemId   = (int)$_POST['elemento_id'];
        $tipo     = $_POST['tipo'];
        $cantidad = (int)$_POST['cantidad'];
        $motivo   = trim($_POST['motivo'] ?? '');
        $ref      = trim($_POST['referencia'] ?? '');

        if (!$elemId)   throw new Exception('Selecciona un elemento.');
        if (!$cantidad) throw new Exception('La cantidad no puede ser cero.');

        $tiposValidos = ['entrada','salida','ajuste','devolucion','transferencia'];
        if (!in_array($tipo, $tiposValidos)) throw new Exception('Tipo invalido.');

        $db->beginTransaction();
        $st = $db->prepare("SELECT stock_actual, nombre FROM elementos WHERE id=? AND activo=1 FOR UPDATE");
        $st->execute([$elemId]);
        $elem = $st->fetch();
        if (!$elem) throw new Exception('Elemento no encontrado.');

        $antes   = (int)$elem['stock_actual'];
        $delta   = in_array($tipo, ['salida']) ? -abs($cantidad) : abs($cantidad);
        if ($tipo === 'ajuste') $delta = $cantidad;
        $despues = $antes + $delta;

        if ($despues < 0) throw new Exception("Stock insuficiente. Stock actual: $antes, intentas restar $cantidad.");

        $db->prepare("UPDATE elementos SET stock_actual=? WHERE id=?")->execute([$despues, $elemId]);
        $db->prepare("INSERT INTO movimientos
            (elemento_id, tipo, cantidad, stock_antes, stock_despues, referencia, motivo, usuario_id)
            VALUES (?,?,?,?,?,?,?,?)")
          ->execute([$elemId, $tipo, $delta, $antes, $despues, $ref ?: null, $motivo ?: null, Auth::user()['id']]);

        $db->commit();
        $success = "Movimiento registrado: <strong>" . htmlspecialchars($elem['nombre']) . "</strong> &mdash; "
            . ($delta > 0 ? '+' : '') . $delta . " uds &rarr; Stock: <strong>$despues</strong>";
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error = $e->getMessage();
    }
}

// ── FILTROS ──────────────────────────────────────────────────────────────────
$elemFiltro = (int)($_GET['elem'] ?? 0);
$tipo       = trim($_GET['tipo'] ?? '');
$desde      = trim($_GET['desde'] ?? '');
$hasta      = trim($_GET['hasta'] ?? '');
$pagina     = max(1,(int)($_GET['p'] ?? 1));

$where  = ['1=1'];
$params = [];
if ($elemFiltro) { $where[] = 'm.elemento_id=?'; $params[] = $elemFiltro; }
if ($tipo)       { $where[] = 'm.tipo=?'; $params[] = $tipo; }
if ($desde)      { $where[] = 'DATE(m.created_at)>=?'; $params[] = $desde; }
if ($hasta)      { $where[] = 'DATE(m.created_at)<=?'; $params[] = $hasta; }
$whereStr = implode(' AND ', $where);

$total = $db->prepare("SELECT COUNT(*) FROM movimientos m WHERE $whereStr");
$total->execute($params);
$pag = paginar((int)$total->fetchColumn(), $pagina);

$st = $db->prepare("
    SELECT m.*, e.codigo, e.nombre AS elem_nombre, c.nombre AS cat, u.nombre AS usuario
    FROM movimientos m
    JOIN elementos e ON e.id=m.elemento_id
    JOIN categorias c ON c.id=e.categoria_id
    LEFT JOIN usuarios u ON u.id=m.usuario_id
    WHERE $whereStr
    ORDER BY m.created_at DESC
    LIMIT {$pag['por_pagina']} OFFSET {$pag['offset']}
");
$st->execute($params);
$movimientos = $st->fetchAll();

$elementos  = $db->query("SELECT id,codigo,nombre,stock_actual FROM elementos WHERE activo=1 ORDER BY nombre")->fetchAll();
$elemActual = $elemFiltro ? $db->query("SELECT nombre FROM elementos WHERE id=$elemFiltro")->fetchColumn() : null;

// Movimiento a editar
$movEdit = isset($_GET['edit']) ? $db->query("SELECT m.*, e.nombre AS elem_nombre FROM movimientos m JOIN elementos e ON e.id=m.elemento_id WHERE m.id=".(int)$_GET['edit'])->fetch() : null;

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
  <div>
    <h4 class="fw-bold mb-0">Kardex de Inventario</h4>
    <?php if ($elemActual): ?>
      <span class="text-primary fw-semibold"><i class="bi bi-filter me-1"></i>Filtrado: <?= htmlspecialchars($elemActual) ?></span>
    <?php else: ?>
      <p class="text-muted small mb-0"><?= number_format($pag['total']) ?> movimientos registrados</p>
    <?php endif; ?>
  </div>
  <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalMovimiento">
    <i class="bi bi-plus-lg me-2"></i>Registrar Movimiento
  </button>
</div>

<?php if ($error): ?>
  <div class="alert alert-danger alert-dismissible fade show">
    <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>
<?php if ($success): ?>
  <div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-2"></i><?= $success ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
  </div>
<?php endif; ?>

<!-- Filtros -->
<div class="section-card mb-3">
  <form method="GET" class="row g-2 align-items-end">
    <div class="col-md-3">
      <label class="form-label mb-1 small fw-semibold">Elemento</label>
      <select name="elem" class="form-select form-select-sm">
        <option value="">Todos los elementos</option>
        <?php foreach ($elementos as $e): ?>
          <option value="<?= $e['id'] ?>" <?= $elemFiltro===$e['id']?'selected':'' ?>>
            <?= $e['codigo'] ?> &mdash; <?= htmlspecialchars(mb_substr($e['nombre'],0,35)) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label mb-1 small fw-semibold">Tipo</label>
      <select name="tipo" class="form-select form-select-sm">
        <option value="">Todos</option>
        <?php foreach (['entrada','salida','ajuste','devolucion','transferencia'] as $t): ?>
          <option value="<?= $t ?>" <?= $tipo===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-2">
      <label class="form-label mb-1 small fw-semibold">Desde</label>
      <input type="date" name="desde" class="form-control form-control-sm" value="<?= $desde ?>">
    </div>
    <div class="col-md-2">
      <label class="form-label mb-1 small fw-semibold">Hasta</label>
      <input type="date" name="hasta" class="form-control form-control-sm" value="<?= $hasta ?>">
    </div>
    <div class="col-md-3">
      <button type="submit" class="btn btn-primary btn-sm me-1"><i class="bi bi-funnel me-1"></i>Filtrar</button>
      <a href="?" class="btn btn-outline-secondary btn-sm">Limpiar</a>
    </div>
  </form>
</div>

<?php if ($movEdit): ?>
<!-- ── PANEL EDITAR MOVIMIENTO ── -->
<div class="section-card mb-3 border border-warning">
  <h6 class="fw-bold mb-3 text-warning">
    <i class="bi bi-pencil me-2"></i>Editando movimiento #<?= $movEdit['id'] ?>
    &mdash; <span class="text-dark"><?= htmlspecialchars($movEdit['elem_nombre']) ?></span>
    <a href="?" class="btn btn-sm btn-outline-secondary ms-2 float-end">&#x2715; Cancelar</a>
  </h6>
  <form method="POST">
    <input type="hidden" name="action"       value="editar_movimiento">
    <input type="hidden" name="csrf"         value="<?= Auth::csrfToken() ?>">
    <input type="hidden" name="movimiento_id" value="<?= $movEdit['id'] ?>">
    <div class="row g-3 align-items-end">
      <div class="col-md-2">
        <label class="form-label small fw-semibold">Tipo</label>
        <select name="tipo" class="form-select form-select-sm">
          <?php foreach (['entrada','salida','ajuste','devolucion','transferencia'] as $t): ?>
            <option value="<?= $t ?>" <?= $movEdit['tipo']===$t?'selected':'' ?>><?= ucfirst($t) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label small fw-semibold">Cantidad</label>
        <input type="number" name="cantidad" class="form-control form-control-sm fw-bold"
               value="<?= $movEdit['cantidad'] ?>" required>
        <div class="form-text" style="font-size:.7rem">Positivo=entrada, Negativo=salida</div>
      </div>
      <div class="col-md-4">
        <label class="form-label small fw-semibold">Motivo</label>
        <input type="text" name="motivo" class="form-control form-control-sm"
               value="<?= htmlspecialchars($movEdit['motivo'] ?? '') ?>"
               placeholder="Motivo del movimiento...">
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold">Referencia</label>
        <input type="text" name="referencia" class="form-control form-control-sm"
               value="<?= htmlspecialchars($movEdit['referencia'] ?? '') ?>"
               placeholder="N&#250;mero de pedido...">
      </div>
      <div class="col-md-1">
        <button type="submit" class="btn btn-warning btn-sm fw-bold w-100">
          <i class="bi bi-save"></i>
        </button>
      </div>
    </div>
    <div class="alert alert-warning py-1 mt-2 small">
      <i class="bi bi-exclamation-triangle me-1"></i>
      Al editar se recalcula el stock autom&aacute;ticamente. Stock antes: <strong><?= $movEdit['stock_antes'] ?></strong> &rarr; Despu&eacute;s: <strong><?= $movEdit['stock_despues'] ?></strong>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="section-card">
  <div class="table-responsive">
    <table class="table table-hover table-inv mb-0">
      <thead><tr>
        <th>Fecha</th>
        <th>Elemento</th>
        <th>Categor&#237;a</th>
        <th>Tipo</th>
        <th class="text-center">&#916; Cant.</th>
        <th class="text-center">Antes</th>
        <th class="text-center">Despu&#233;s</th>
        <th>Referencia</th>
        <th>Motivo</th>
        <th>Usuario</th>
        <?php if (Auth::isAdmin()): ?>
        <th class="text-center">Acciones</th>
        <?php endif; ?>
      </tr></thead>
      <tbody>
      <?php foreach ($movimientos as $m):
        $tc=['entrada'=>'success','salida'=>'danger','ajuste'=>'warning','devolucion'=>'info','transferencia'=>'secondary'];
        $esEditando = ($movEdit && $movEdit['id'] == $m['id']);
      ?>
      <tr <?= $esEditando ? 'class="table-warning"' : '' ?>>
        <td class="text-muted small text-nowrap"><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></td>
        <td>
          <a href="<?= APP_URL ?>/modules/elementos/form.php?id=<?= $m['elemento_id'] ?>"
             class="text-decoration-none fw-semibold">
            <?= htmlspecialchars($m['elem_nombre']) ?>
          </a>
          <br><code class="text-primary" style="font-size:.7rem;"><?= $m['codigo'] ?></code>
        </td>
        <td class="small"><?= htmlspecialchars($m['cat']) ?></td>
        <td><span class="badge bg-<?= $tc[$m['tipo']] ?? 'secondary' ?>"><?= ucfirst($m['tipo']) ?></span></td>
        <td class="fw-bold text-center <?= $m['cantidad']>0?'text-success':'text-danger' ?>">
          <?= ($m['cantidad']>0?'+':'').$m['cantidad'] ?>
        </td>
        <td class="text-center text-muted"><?= $m['stock_antes'] ?></td>
        <td class="text-center fw-bold"><?= $m['stock_despues'] ?></td>
        <td class="small text-muted"><?= htmlspecialchars($m['referencia'] ?? '&#8212;') ?></td>
        <td class="small"><?= htmlspecialchars($m['motivo'] ?? '&#8212;') ?></td>
        <td class="small"><?= htmlspecialchars($m['usuario'] ?? 'Sistema') ?></td>
        <?php if (Auth::isAdmin()): ?>
        <td class="text-center text-nowrap">
          <a href="?edit=<?= $m['id'] ?>&elem=<?= $elemFiltro ?>&tipo=<?= urlencode($tipo) ?>&desde=<?= $desde ?>&hasta=<?= $hasta ?>"
             class="btn btn-sm btn-outline-warning py-0 px-1" title="Editar">
            <i class="bi bi-pencil"></i>
          </a>
          <a href="?del=<?= $m['id'] ?>&csrf=<?= Auth::csrfToken() ?>&elem=<?= $elemFiltro ?>&tipo=<?= urlencode($tipo) ?>&desde=<?= $desde ?>&hasta=<?= $hasta ?>"
             class="btn btn-sm btn-outline-danger py-0 px-1 ms-1" title="Eliminar y revertir stock"
             onclick="return confirm('Eliminar este movimiento?\nSe revertir\u00e1 el stock del elemento.')">
            <i class="bi bi-trash"></i>
          </a>
        </td>
        <?php endif; ?>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($movimientos)): ?>
        <tr><td colspan="11" class="text-center text-muted py-5">
          <i class="bi bi-inbox fs-2 d-block mb-2 opacity-25"></i>No hay movimientos con estos filtros
        </td></tr>
      <?php endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pag['total_paginas'] > 1): ?>
  <nav class="mt-3">
    <ul class="pagination pagination-sm justify-content-center mb-0">
      <?php for ($i=1; $i<=$pag['total_paginas']; $i++): ?>
        <li class="page-item <?= $i==$pag['pagina']?'active':'' ?>">
          <a class="page-link"
             href="?tipo=<?= urlencode($tipo) ?>&desde=<?= $desde ?>&hasta=<?= $hasta ?>&elem=<?= $elemFiltro ?>&p=<?= $i ?>">
            <?= $i ?>
          </a>
        </li>
      <?php endfor; ?>
    </ul>
  </nav>
  <?php endif; ?>
</div>

<!-- ── MODAL REGISTRAR MOVIMIENTO ── -->
<div class="modal fade" id="modalMovimiento" tabindex="-1">
  <div class="modal-dialog modal-md">
    <div class="modal-content">
      <div class="modal-header" style="background:linear-gradient(135deg,#1e2a3a,#2d3f55);">
        <h5 class="modal-title text-white fw-bold">
          <i class="bi bi-arrow-left-right me-2 text-info"></i>Registrar Movimiento
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST">
        <input type="hidden" name="action" value="nuevo_movimiento">
        <input type="hidden" name="csrf"   value="<?= Auth::csrfToken() ?>">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-semibold mb-2">Tipo de movimiento *</label>
            <div class="d-flex gap-2 flex-wrap" id="tiposBtns">
              <?php
              $tiposInfo = [
                'entrada'      => ['success', 'bi-box-arrow-in-down',   'Entrada',       'Compra, recepci&#243;n'],
                'salida'       => ['danger',  'bi-box-arrow-up',        'Salida',         'Uso en kit, despacho'],
                'ajuste'       => ['warning', 'bi-sliders',             'Ajuste',         'Correcci&#243;n inventario'],
                'devolucion'   => ['info',    'bi-arrow-counterclockwise','Devoluci&#243;n','Devoluci&#243;n'],
                'transferencia'=> ['secondary','bi-arrows-left-right',  'Transferencia','Entre sedes'],
              ];
              foreach ($tiposInfo as $key => [$col, $icon, $label, $hint]): ?>
              <button type="button"
                      class="btn btn-outline-<?= $col ?> btn-tipo flex-fill text-start p-2"
                      style="min-width:120px;max-width:48%;"
                      data-tipo="<?= $key ?>"
                      data-signo="<?= in_array($key,['salida'])?'-':'+' ?>"
                      onclick="selTipo(this)">
                <i class="bi <?= $icon ?> d-block mb-1" style="font-size:1.3rem;"></i>
                <span class="fw-bold d-block" style="font-size:.82rem;"><?= $label ?></span>
                <span class="d-block text-muted" style="font-size:.7rem;"><?= $hint ?></span>
              </button>
              <?php endforeach; ?>
            </div>
            <input type="hidden" name="tipo" id="tipoHidden" required>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold mb-1">Elemento *</label>
            <select name="elemento_id" id="elemSelect" class="form-select" required onchange="actualizarStock()">
              <option value="">&#8212; Seleccionar elemento &#8212;</option>
              <?php foreach ($elementos as $e): ?>
                <option value="<?= $e['id'] ?>"
                        data-stock="<?= $e['stock_actual'] ?>"
                        <?= $elemFiltro===$e['id']?'selected':'' ?>>
                  <?= $e['codigo'] ?> &mdash; <?= htmlspecialchars($e['nombre']) ?>
                  (stock: <?= $e['stock_actual'] ?>)
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div id="stockActualPanel" class="d-none mb-3">
            <div class="p-2 rounded d-flex align-items-center gap-3"
                 style="background:#f0f9ff;border:1px solid #bae6fd;">
              <div>
                <div class="small text-muted">Stock actual</div>
                <div class="fw-bold fs-5" id="stockActualVal">&#8212;</div>
              </div>
              <i class="bi bi-arrow-right text-muted fs-5"></i>
              <div>
                <div class="small text-muted">Quedar&#225;</div>
                <div class="fw-bold fs-5" id="stockResultado">&#8212;</div>
              </div>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold mb-1">
              Cantidad *
              <span id="signoHint" class="text-muted small ms-1"></span>
            </label>
            <div class="input-group">
              <span class="input-group-text fw-bold" id="signoSpan" style="min-width:40px;">&#177;</span>
              <input type="number" name="cantidad" id="cantInput"
                     class="form-control form-control-lg fw-bold text-center"
                     min="1" step="1" value="1" required
                     oninput="actualizarStock()">
              <span class="input-group-text">unidades</span>
            </div>
            <div id="ajusteHint" class="form-text d-none text-warning">
              <i class="bi bi-info-circle me-1"></i>Para ajuste: positivo para agregar, negativo para quitar.
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold mb-1">Motivo / Descripci&#243;n</label>
            <input type="text" name="motivo" class="form-control"
                   placeholder="Ej: Compra orden PED-2026-001, uso en kit Arduino b&#225;sico...">
          </div>

          <div class="mb-2">
            <label class="form-label fw-semibold mb-1">Referencia <span class="text-muted small">(opcional)</span></label>
            <input type="text" name="referencia" class="form-control form-control-sm"
                   placeholder="N&#250;mero de pedido, kit, colegio...">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary fw-bold" id="btnGuardar" disabled>
            <i class="bi bi-check-circle me-2"></i>Registrar
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
let _tipoSel = null;
function selTipo(btn) {
  document.querySelectorAll('.btn-tipo').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  _tipoSel = btn.dataset.tipo;
  document.getElementById('tipoHidden').value = _tipoSel;
  const signo = btn.dataset.signo;
  const signoSpan = document.getElementById('signoSpan');
  signoSpan.textContent = signo === '-' ? '\u2212' : '+';
  signoSpan.style.color  = signo === '-' ? '#dc3545' : '#198754';
  const ajusteHint = document.getElementById('ajusteHint');
  if (_tipoSel === 'ajuste') {
    ajusteHint.classList.remove('d-none');
    document.getElementById('cantInput').min = null;
    document.getElementById('signoHint').textContent = '(+ para agregar, - para quitar)';
  } else {
    ajusteHint.classList.add('d-none');
    document.getElementById('cantInput').min = 1;
    document.getElementById('signoHint').textContent = '';
  }
  actualizarStock();
  validarForm();
}
function actualizarStock() {
  const sel   = document.getElementById('elemSelect');
  const opt   = sel.options[sel.selectedIndex];
  const stock = parseInt(opt?.dataset?.stock ?? '');
  const cant  = parseInt(document.getElementById('cantInput').value) || 0;
  if (!isNaN(stock) && opt?.value) {
    document.getElementById('stockActualPanel').classList.remove('d-none');
    document.getElementById('stockActualVal').textContent = stock;
    let resultado;
    if (_tipoSel === 'salida')      resultado = stock - Math.abs(cant);
    else if (_tipoSel === 'ajuste') resultado = stock + cant;
    else                            resultado = stock + Math.abs(cant);
    const resEl = document.getElementById('stockResultado');
    resEl.textContent = isNaN(resultado) ? '?' : resultado;
    resEl.style.color = (!isNaN(resultado) && resultado < 0) ? '#dc3545' : '#198754';
  } else {
    document.getElementById('stockActualPanel').classList.add('d-none');
  }
  validarForm();
}
function validarForm() {
  const elemOk = document.getElementById('elemSelect').value !== '';
  const tipoOk = !!_tipoSel;
  const cantOk = parseInt(document.getElementById('cantInput').value) !== 0;
  document.getElementById('btnGuardar').disabled = !(elemOk && tipoOk && cantOk);
}
document.getElementById('elemSelect').addEventListener('change', validarForm);
document.getElementById('cantInput').addEventListener('input', validarForm);
<?php if ($elemFiltro): ?>actualizarStock();<?php endif; ?>
<?php if ($error): ?>
document.addEventListener('DOMContentLoaded', () => {
  new bootstrap.Modal(document.getElementById('modalMovimiento')).show();
});
<?php endif; ?>
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
