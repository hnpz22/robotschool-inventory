<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::requirePermiso('barcodes', 'ver');

$db = Database::get();
$pageTitle  = 'Códigos de Barras';
$activeMenu = 'barcodes';

// Búsqueda por código o nombre
$q = trim($_GET['q'] ?? '');
$elementos = [];
if ($q) {
    $st = $db->prepare("SELECT e.id, e.codigo, e.nombre, e.stock_actual, c.nombre AS cat FROM elementos e JOIN categorias c ON c.id=e.categoria_id WHERE e.activo=1 AND (e.codigo LIKE ? OR e.nombre LIKE ?) LIMIT 50");
    $st->execute(["%$q%","%$q%"]);
    $elementos = $st->fetchAll();
} else {
    $elementos = $db->query("SELECT e.id, e.codigo, e.nombre, e.stock_actual, c.nombre AS cat FROM elementos e JOIN categorias c ON c.id=e.categoria_id WHERE e.activo=1 ORDER BY e.updated_at DESC LIMIT 50")->fetchAll();
}

// API: buscar por código exacto (para escáner)
if (isset($_GET['api_code'])) {
    header('Content-Type: application/json');
    $st = $db->prepare("SELECT e.*, c.nombre AS cat FROM elementos e JOIN categorias c ON c.id=e.categoria_id WHERE e.codigo=? AND e.activo=1");
    $st->execute([strtoupper(trim($_GET['api_code']))]);
    $elem = $st->fetch();
    echo $elem ? json_encode($elem) : json_encode(['error'=>'No encontrado']);
    exit;
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="d-flex align-items-center justify-content-between mb-4">
  <div>
    <h4 class="fw-bold mb-0">Códigos de Barras</h4>
    <p class="text-muted small mb-0">Genera, imprime y escanea etiquetas de inventario</p>
  </div>
  <button class="btn btn-success" onclick="imprimirSeleccionados()">
    <i class="bi bi-printer me-1"></i>Imprimir Seleccionados
  </button>
</div>

<div class="row g-4">
  <!-- Panel escáner -->
  <div class="col-lg-4">
    <div class="section-card">
      <h6 class="fw-bold mb-3"><i class="bi bi-upc-scan me-2 text-primary"></i>Escáner en Tiempo Real</h6>
      <div id="barcode-scan-active">
        <div class="alert alert-info py-2 small">
          <i class="bi bi-keyboard me-1"></i>Conecta tu lector de código de barras y escanea cualquier elemento.
        </div>
        <div class="input-group mb-3">
          <span class="input-group-text"><i class="bi bi-upc-scan"></i></span>
          <input type="text" id="scanInput" class="form-control" placeholder="Escanea o escribe un código..." autofocus>
          <button class="btn btn-primary" onclick="buscarManual()">Buscar</button>
        </div>
        <div id="scanResult" class="d-none">
          <div class="card border-success">
            <div class="card-body">
              <h6 class="fw-bold text-success" id="scanNombre"></h6>
              <div class="row g-2 text-sm">
                <div class="col-6"><span class="text-muted">Código:</span><br><code id="scanCodigo" class="text-primary"></code></div>
                <div class="col-6"><span class="text-muted">Categoría:</span><br><span id="scanCat"></span></div>
                <div class="col-6"><span class="text-muted">Stock:</span><br><strong id="scanStock" class="fs-5"></strong></div>
                <div class="col-6"><span class="text-muted">Costo Real:</span><br><span id="scanCosto"></span></div>
              </div>
              <div class="d-flex gap-2 mt-3">
                <button class="btn btn-sm btn-outline-success" onclick="registrarMovimiento('entrada')">
                  <i class="bi bi-plus-circle me-1"></i>Entrada
                </button>
                <button class="btn btn-sm btn-outline-danger" onclick="registrarMovimiento('salida')">
                  <i class="bi bi-dash-circle me-1"></i>Salida
                </button>
                <a id="scanEditLink" href="#" class="btn btn-sm btn-outline-primary">
                  <i class="bi bi-pencil me-1"></i>Editar
                </a>
              </div>
            </div>
          </div>
        </div>
        <div id="scanError" class="alert alert-danger d-none py-2 small"></div>
      </div>
    </div>

    <!-- Movimiento rápido modal info -->
    <div class="section-card mt-3">
      <h6 class="fw-bold mb-2"><i class="bi bi-lightning me-2 text-warning"></i>Movimiento Rápido</h6>
      <div id="movForm" class="d-none">
        <div class="mb-2">
          <label class="form-label mb-1">Cantidad</label>
          <input type="number" id="movCantidad" class="form-control form-control-sm" min="1" value="1">
        </div>
        <div class="mb-2">
          <label class="form-label mb-1">Motivo / Referencia</label>
          <input type="text" id="movMotivo" class="form-control form-control-sm" placeholder="Ej: Colegio XYZ, Ajuste">
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-success btn-sm flex-grow-1" onclick="confirmarMovimiento()"><i class="bi bi-check-circle me-1"></i>Confirmar</button>
          <button class="btn btn-outline-secondary btn-sm" onclick="cancelarMovimiento()">Cancelar</button>
        </div>
      </div>
      <p class="text-muted small mb-0" id="movInfo">Escanea un elemento para registrar un movimiento rápido.</p>
    </div>
  </div>

  <!-- Lista de elementos para imprimir -->
  <div class="col-lg-8">
    <div class="section-card">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="fw-bold mb-0">Catálogo de Etiquetas</h6>
        <div class="d-flex gap-2">
          <form method="GET" class="d-flex gap-2">
            <input type="text" name="q" class="form-control form-control-sm" placeholder="Buscar elemento..." value="<?= htmlspecialchars($q) ?>">
            <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-search"></i></button>
          </form>
          <button class="btn btn-sm btn-outline-secondary" onclick="toggleSeleccionTodos()">
            <i class="bi bi-check-all me-1"></i>Todos
          </button>
        </div>
      </div>

      <div class="row g-3" id="barcodeGrid">
        <?php foreach ($elementos as $e): ?>
        <div class="col-md-6 col-xl-4">
          <div class="barcode-container border rounded p-2 position-relative" style="font-size:.8rem;">
            <input type="checkbox" class="form-check-input position-absolute top-0 start-0 m-1 barcode-check"
                   value="<?= $e['id'] ?>" data-codigo="<?= htmlspecialchars($e['codigo']) ?>"
                   data-nombre="<?= htmlspecialchars(addslashes($e['nombre'])) ?>">
            <div class="fw-bold text-truncate mb-1 text-center" style="font-size:.75rem;" title="<?= htmlspecialchars($e['nombre']) ?>">
              <?= htmlspecialchars($e['nombre']) ?>
            </div>
            <svg data-barcode="<?= htmlspecialchars($e['codigo']) ?>" class="w-100"></svg>
            <div class="d-flex justify-content-between align-items-center mt-1">
              <span class="badge bg-light text-dark border"><?= htmlspecialchars($e['cat']) ?></span>
              <span class="text-muted">Stock: <strong><?= $e['stock_actual'] ?></strong></span>
              <button class="btn btn-link btn-sm p-0" onclick="imprimirBarcode('<?= $e['codigo'] ?>','<?= addslashes($e['nombre']) ?>')">
                <i class="bi bi-printer text-primary"></i>
              </button>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<script>
const appUrl = '<?= APP_URL ?>';
let scanElementoId = null;
let movTipo = null;

// Escáner por teclado ya manejado en app.js
document.getElementById('scanInput')?.addEventListener('keydown', function(e) {
  if (e.key === 'Enter') { buscarManual(); }
});

document.addEventListener('barcodeScan', (ev) => { mostrarResultado(ev.detail); });

function buscarManual() {
  const codigo = document.getElementById('scanInput').value.trim();
  if (!codigo) return;
  fetch(`${appUrl}/modules/barcodes/index.php?api_code=${encodeURIComponent(codigo)}`)
    .then(r => r.json())
    .then(data => {
      if (data.error) { mostrarError('Código no encontrado: ' + codigo); }
      else { mostrarResultado(data); }
    });
}

function mostrarResultado(data) {
  scanElementoId = data.id;
  document.getElementById('scanNombre').textContent = data.nombre;
  document.getElementById('scanCodigo').textContent = data.codigo;
  document.getElementById('scanCat').textContent = data.cat || data.categoria;
  document.getElementById('scanStock').textContent = data.stock_actual;
  document.getElementById('scanCosto').textContent = data.costo_real_cop > 0 ? '$ ' + parseInt(data.costo_real_cop).toLocaleString('es-CO') : '&mdash;';
  document.getElementById('scanEditLink').href = `${appUrl}/modules/elementos/form.php?id=${data.id}`;
  document.getElementById('scanResult').classList.remove('d-none');
  document.getElementById('scanError').classList.add('d-none');
  document.getElementById('scanInput').value = '';
}

function mostrarError(msg) {
  document.getElementById('scanResult').classList.add('d-none');
  const err = document.getElementById('scanError');
  err.textContent = msg;
  err.classList.remove('d-none');
}

function registrarMovimiento(tipo) {
  if (!scanElementoId) return;
  movTipo = tipo;
  document.getElementById('movInfo').classList.add('d-none');
  document.getElementById('movForm').classList.remove('d-none');
}

function cancelarMovimiento() {
  movTipo = null;
  document.getElementById('movForm').classList.add('d-none');
  document.getElementById('movInfo').classList.remove('d-none');
}

function confirmarMovimiento() {
  if (!scanElementoId || !movTipo) return;
  const cantidad = parseInt(document.getElementById('movCantidad').value);
  const motivo   = document.getElementById('movMotivo').value;
  fetch(`${appUrl}/api/movimiento.php`, {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ elemento_id: scanElementoId, tipo: movTipo, cantidad, motivo,
                           csrf: '<?= Auth::csrfToken() ?>' })
  })
  .then(r => r.json())
  .then(data => {
    if (data.ok) {
      mostrarAlerta(`Movimiento registrado. Nuevo stock: ${data.stock}`, 'success');
      document.getElementById('scanStock').textContent = data.stock;
      cancelarMovimiento();
    } else {
      mostrarAlerta(data.error || 'Error al registrar', 'danger');
    }
  });
}

function imprimirSeleccionados() {
  const checks = document.querySelectorAll('.barcode-check:checked');
  if (!checks.length) { mostrarAlerta('Selecciona al menos un elemento', 'warning'); return; }
  const items = Array.from(checks).map(c => ({ codigo: c.dataset.codigo, nombre: c.dataset.nombre }));
  const win = window.open('', '_blank', 'width=800,height=600');
  let barcodes = items.map(i => `
    <div style="display:inline-block;margin:8px;padding:10px;border:1px solid #ccc;border-radius:6px;text-align:center;width:220px;">
      <div style="font-size:11px;font-weight:bold;margin-bottom:4px;max-width:200px;overflow:hidden;white-space:nowrap;text-overflow:ellipsis;">${i.nombre}</div>
      <svg class="bc" data-code="${i.codigo}"></svg>
    </div>`).join('');
  win.document.write(`<html><head><title>Etiquetas</title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"><\/script>
    </head><body style="font-family:sans-serif;padding:10px;">${barcodes}
    <script>document.querySelectorAll('.bc').forEach(el=>JsBarcode(el,el.dataset.code,{format:'CODE128',width:2,height:45,displayValue:true,fontSize:12}));<\/script>
    </body></html>`);
  win.document.close();
  win.onload = () => { win.print(); };
}

function toggleSeleccionTodos() {
  const checks = document.querySelectorAll('.barcode-check');
  const allChecked = Array.from(checks).every(c => c.checked);
  checks.forEach(c => c.checked = !allChecked);
}
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
