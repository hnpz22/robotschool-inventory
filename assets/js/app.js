// assets/js/app.js — ROBOTSchool Inventory

// ── Sidebar toggle ──
document.getElementById('sidebarToggle')?.addEventListener('click', () => {
  const sb = document.getElementById('sidebar');
  if (window.innerWidth <= 768) {
    sb.classList.toggle('show');
  } else {
    sb.classList.toggle('collapsed');
  }
});

// ── Auto-generate barcodes ──
document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('[data-barcode]').forEach(el => {
    const code = el.dataset.barcode;
    if (!code) return;
    JsBarcode(el, code, {
      format: 'CODE128',
      width: 2, height: 50,
      displayValue: true,
      fontSize: 13,
      margin: 8,
      background: '#ffffff',
      lineColor: '#000000',
    });
  });
});

// ── Scanner de código de barras (teclado) ──
let barcodeBuffer = '';
let barcodeTimer  = null;
document.addEventListener('keydown', (e) => {
  if (!document.getElementById('barcode-scan-active')) return;
  clearTimeout(barcodeTimer);
  if (e.key === 'Enter') {
    if (barcodeBuffer.length > 3) {
      buscarPorCodigo(barcodeBuffer);
    }
    barcodeBuffer = '';
    return;
  }
  if (e.key.length === 1) barcodeBuffer += e.key;
  barcodeTimer = setTimeout(() => { barcodeBuffer = ''; }, 200);
});

function buscarPorCodigo(codigo) {
  fetch(`${appUrl}/api/elemento_by_code.php?codigo=${encodeURIComponent(codigo)}`)
    .then(r => r.json())
    .then(data => {
      if (data.error) {
        mostrarAlerta('No se encontró el código: ' + codigo, 'warning');
      } else {
        const ev = new CustomEvent('barcodeScan', { detail: data });
        document.dispatchEvent(ev);
      }
    });
}

// ── Alertas toast ──
function mostrarAlerta(msg, tipo = 'success') {
  const container = document.getElementById('toast-container') || crearToastContainer();
  const id = 'toast_' + Date.now();
  const icons = { success:'check-circle-fill', danger:'x-circle-fill', warning:'exclamation-triangle-fill', info:'info-circle-fill' };
  const icon = icons[tipo] || 'info-circle-fill';
  container.insertAdjacentHTML('beforeend', `
    <div id="${id}" class="toast align-items-center text-bg-${tipo} border-0 show" role="alert">
      <div class="d-flex">
        <div class="toast-body"><i class="bi bi-${icon} me-2"></i>${msg}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>
    </div>`);
  setTimeout(() => document.getElementById(id)?.remove(), 4000);
}

function crearToastContainer() {
  const div = document.createElement('div');
  div.id = 'toast-container';
  div.className = 'toast-container position-fixed bottom-0 end-0 p-3';
  div.style.zIndex = '9999';
  document.body.appendChild(div);
  return div;
}

// ── Preview de imagen ──
document.querySelectorAll('.img-preview-input').forEach(input => {
  input.addEventListener('change', function() {
    const preview = document.getElementById(this.dataset.preview);
    if (!preview || !this.files[0]) return;
    preview.src = URL.createObjectURL(this.files[0]);
    preview.style.display = 'block';
  });
});

// ── Confirmar acciones peligrosas ──
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', function(e) {
    if (!confirm(this.dataset.confirm || '¿Estás seguro?')) e.preventDefault();
  });
});

// ── Imprimir etiqueta de código de barras ──
function imprimirBarcode(codigo, nombre) {
  const win = window.open('', '_blank', 'width=400,height=250');
  win.document.write(`
    <html><head><title>Etiqueta</title>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"><\/script>
    </head><body style="margin:0;padding:10px;font-family:sans-serif;text-align:center;">
      <div style="font-size:11px;font-weight:bold;margin-bottom:4px;">${nombre}</div>
      <svg id="bc"></svg>
      <script>JsBarcode("#bc","${codigo}",{format:"CODE128",width:2,height:45,displayValue:true,fontSize:12});<\/script>
    </body></html>`);
  win.document.close();
  win.onload = () => { win.print(); win.close(); };
}
