<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();

$db    = Database::get();
$kitId = (int)($_GET['kit_id'] ?? 0);

$kit = $kitId ? $db->query("
    SELECT k.*, c.nombre AS colegio_nombre
    FROM kits k LEFT JOIN colegios c ON c.id=k.colegio_id
    WHERE k.id=$kitId
")->fetch() : null;

$itemsBD = [];
if ($kit) {
    $elems = $db->query("
        SELECT e.id,e.codigo,e.nombre,e.foto,ke.cantidad,'elem' AS tipo
        FROM kit_elementos ke JOIN elementos e ON e.id=ke.elemento_id
        WHERE ke.kit_id=$kitId ORDER BY e.nombre
    ")->fetchAll();
    $protos = $db->query("
        SELECT p.id,p.codigo,p.nombre,p.foto,kp.cantidad,'proto' AS tipo
        FROM kit_prototipos kp JOIN prototipos p ON p.id=kp.prototipo_id
        WHERE kp.kit_id=$kitId ORDER BY p.nombre
    ")->fetchAll();
    $itemsBD = array_merge($elems, $protos);
}

$logoPath = APP_ROOT . '/assets/img/logo_oficial.png';
$logoB64  = file_exists($logoPath) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath)) : '';
$kitNombreDefault = $kit ? $kit['nombre'] : 'Kit ROBOTSchool';
$colegioDefault   = $kit ? ($kit['colegio_nombre'] ?? '') : '';
$codigoDefault    = $kit ? $kit['codigo'] : '';
$nivelDefault     = $kit ? ($kit['nivel'] ?? 'basico') : 'basico';
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Sticker Caja &mdash; <?= htmlspecialchars($kitNombreDefault) ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,Helvetica,sans-serif;background:#e2e8f0}

/* Toolbar */
.toolbar{
  position:fixed;top:0;left:0;right:0;z-index:200;
  background:#1e293b;color:#fff;
  padding:.5rem 1.5rem;display:flex;align-items:center;gap:.75rem;flex-wrap:wrap;
}
.toolbar h1{font-size:.88rem;font-weight:700;flex:1}
.btn-t{padding:.38rem 1rem;border-radius:6px;border:none;cursor:pointer;font-size:.82rem;font-weight:700}
.btn-green{background:#16a34a;color:#fff}.btn-gris{background:#475569;color:#fff}
.qty-ctrl{display:flex;align-items:center;gap:.4rem;background:#334155;border-radius:6px;padding:.2rem .5rem}
.qty-ctrl label{font-size:.78rem;color:#94a3b8}
.qty-ctrl input{width:48px;text-align:center;border:none;background:#1e293b;color:#fff;border-radius:4px;padding:.2rem;font-size:.85rem;font-weight:700}
.qty-ctrl button{width:24px;height:24px;border:none;border-radius:4px;background:#475569;color:#fff;cursor:pointer;font-size:.9rem;font-weight:700;display:flex;align-items:center;justify-content:center}
.qty-ctrl button:hover{background:#64748b}

/* Hoja carta: 4 stickers (2x2) */
.pagina{
  width:21.59cm;
  background:#fff;
  margin:4.5rem auto 1.5rem;
  padding:.5cm .5cm;
  display:grid;
  grid-template-columns:1fr 1fr;
  gap:.4cm;
  box-shadow:0 4px 24px rgba(0,0,0,.15);
  page-break-after:always;
}

/* Sticker = 1/4 de carta */
.stk{
  border:2px solid #1e2d4f;
  border-radius:8px;
  overflow:hidden;
  display:flex;
  flex-direction:column;
  height:12.7cm; /* (27.94 - 2*0.5 - 0.4) / 2 */
  page-break-inside:avoid;
  background:#fff;
}

/* Header del sticker */
.stk-head{
  background:#1e2d4f;color:#fff;
  display:flex;align-items:center;gap:6px;
  padding:5px 8px;flex-shrink:0;
}
.stk-logo{width:32px;height:32px;object-fit:contain;background:#fff;border-radius:3px;padding:1px;flex-shrink:0}
.stk-logo-txt{width:32px;height:32px;background:#fff;border-radius:3px;display:flex;align-items:center;justify-content:center;font-size:8px;font-weight:900;color:#1e2d4f;flex-shrink:0}
.stk-brand{font-size:9px;font-weight:800;letter-spacing:.04em;line-height:1.2}
.stk-kit-nm{font-size:8px;opacity:.8;margin-top:1px}

/* Cuerpo */
.stk-body{padding:6px 8px;flex:1;overflow:hidden;display:flex;flex-direction:column;gap:4px}
.stk-titulo{font-size:7.5px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#475569;border-bottom:1px solid #e2e8f0;padding-bottom:3px}

/* Grid componentes: 3 columnas */
.comp-grid{
  display:grid;
  grid-template-columns:repeat(3,1fr);
  gap:3px;
  flex:1;
  overflow:hidden;
}
.comp-item{
  border:1px solid #e2e8f0;border-radius:4px;
  overflow:hidden;text-align:center;
  display:flex;flex-direction:column;
}
.comp-foto{width:100%;flex:1;object-fit:cover;display:block;min-height:0}
.comp-foto-ph{width:100%;flex:1;background:#f0f4ff;display:flex;align-items:center;justify-content:center;font-size:1rem;color:#94a3b8;min-height:0}
.comp-info{padding:2px 2px 0}
.comp-nm{font-size:6px;font-weight:700;line-height:1.2;color:#1e293b}
.comp-cod{font-size:5.5px;color:#94a3b8}
.comp-cant{background:#1e2d4f;color:#fff;font-size:6.5px;font-weight:800;padding:1px 0;text-align:center;flex-shrink:0}

/* Checklist compacta */
.check-list{flex-shrink:0}
.check-head{background:#334155;color:#fff;font-size:6.5px;font-weight:700;padding:2px 6px;letter-spacing:.05em}
.check-cols{display:grid;grid-template-columns:1fr 1fr;font-size:6.5px}
.check-item{display:flex;align-items:center;gap:3px;padding:2px 5px;border-bottom:.5px solid #f1f5f9}
.chk-box{width:9px;height:9px;border:1.5px solid #1e293b;border-radius:1px;flex-shrink:0}

/* Footer */
.stk-foot{
  background:#f8fafc;border-top:1px solid #e2e8f0;
  padding:3px 8px;display:flex;justify-content:space-between;align-items:center;
  flex-shrink:0;
}
.stk-foot .web{font-size:6px;color:#9ca3af}
.nivel-pill{font-size:6.5px;padding:1px 6px;border-radius:20px;font-weight:700}
.nivel-basico{background:#dcfce7;color:#166534}
.nivel-intermedio{background:#fef9c3;color:#854d0e}
.nivel-avanzado{background:#fee2e2;color:#991b1b}

@media print{
  body{background:#fff}
  .toolbar{display:none!important}
  .pagina{margin:0;box-shadow:none;width:100%;padding:.4cm .4cm}
  @page{size:letter portrait;margin:0}
}
</style>
</head>
<body>

<div class="toolbar">
  <button class="btn-t btn-gris" onclick="history.back()">&larr; Volver</button>
  <h1>Sticker de Caja &mdash; 1/4 hoja &mdash; <?= htmlspecialchars($kitNombreDefault) ?></h1>
  <div class="qty-ctrl">
    <label>Copias:</label>
    <button onclick="cambiarCopias(-1)">-</button>
    <input type="number" id="num-copias" value="4" min="1" max="40" onchange="renderStickers()">
    <button onclick="cambiarCopias(1)">+</button>
  </div>
  <button class="btn-t btn-green" onclick="window.print()">&#128438; Imprimir / PDF</button>
  <span style="font-size:.72rem;color:#94a3b8">Ctrl+P &rarr; Carta &rarr; Sin m&aacute;rgenes</span>
</div>

<div id="hojas-container"></div>

<script>
// Datos del kit
var KIT_NOMBRE  = <?= json_encode($kitNombreDefault) ?>;
var KIT_CODIGO  = <?= json_encode($codigoDefault) ?>;
var KIT_COLEGIO = <?= json_encode($colegioDefault) ?>;
var KIT_NIVEL   = <?= json_encode($nivelDefault) ?>;
var LOGO_B64    = <?= json_encode($logoB64) ?>;
var UPLOAD_URL  = <?= json_encode(UPLOAD_URL) ?>;

// Items desde BD o sessionStorage
var ITEMS = <?= $itemsBD ? json_encode(array_map(function($i){
    return [
        'nombre' => $i['nombre'],
        'codigo' => $i['codigo'] ?? '',
        'cant'   => $i['cantidad'],
        'foto'   => fotoUrl($i['foto']),
        'tipo'   => $i['tipo'],
    ];
}, $itemsBD)) : '[]' ?>;

// Si no hay items de BD, intentar sessionStorage
if (ITEMS.length === 0) {
    var ss = sessionStorage.getItem('sticker_kit_items');
    if (ss) {
        var raw = JSON.parse(ss);
        KIT_NOMBRE = sessionStorage.getItem('sticker_kit_nombre') || KIT_NOMBRE;
        ITEMS = raw.map(function(it) {
            return {
                nombre: it.nombre,
                codigo: it.codigo || '',
                cant:   it.cant,
                foto:   it.img || '',
                tipo:   it.tipo
            };
        });
    }
}

function cambiarCopias(d) {
    var inp = document.getElementById('num-copias');
    inp.value = Math.max(1, Math.min(40, parseInt(inp.value||4) + d));
    renderStickers();
}

function renderStickers() {
    var n = Math.max(1, parseInt(document.getElementById('num-copias').value || 4));
    var container = document.getElementById('hojas-container');
    container.innerHTML = '';

    // Agrupar en páginas de 4 stickers
    var paginas = Math.ceil(n / 4);
    var stikerIdx = 0;

    for (var pg = 0; pg < paginas && stikerIdx < n; pg++) {
        var pagDiv = document.createElement('div');
        pagDiv.className = 'pagina';

        for (var s = 0; s < 4 && stikerIdx < n; s++, stikerIdx++) {
            pagDiv.innerHTML += buildSticker();
        }
        container.appendChild(pagDiv);
    }
}

function buildSticker() {
    // Header
    var logoHtml = LOGO_B64
        ? '<img src="'+LOGO_B64+'" class="stk-logo" alt="RS">'
        : '<div class="stk-logo-txt">RS</div>';

    var colegioStr = KIT_COLEGIO ? ' &middot; ' + escHtml(KIT_COLEGIO) : '';

    // Grid de componentes (máx 12 para que quepan en 1/4 carta)
    var compHtml = '';
    var maxComp = Math.min(ITEMS.length, 12);
    for (var i = 0; i < maxComp; i++) {
        var it = ITEMS[i];
        var fotoHtml = it.foto
            ? '<img src="'+it.foto+'" class="comp-foto" alt="">'
            : '<div class="comp-foto-ph">'+(it.tipo==='proto'?'&#x2702;':'&#x1F527;')+'</div>';
        compHtml +=
            '<div class="comp-item">' +
                fotoHtml +
                '<div class="comp-info">' +
                    '<div class="comp-nm">'+escHtml(it.nombre.substring(0,22))+(it.nombre.length>22?'&hellip;':'')+'</div>' +
                    (it.codigo ? '<div class="comp-cod">'+escHtml(it.codigo)+'</div>' : '') +
                '</div>' +
                '<div class="comp-cant">x'+it.cant+'</div>' +
            '</div>';
    }
    if (ITEMS.length > 12) {
        compHtml += '<div class="comp-item" style="background:#f8fafc;display:flex;align-items:center;justify-content:center"><div style="font-size:6px;color:#94a3b8;text-align:center">+'+(ITEMS.length-12)+'<br>m&aacute;s</div></div>';
    }

    // Checklist
    var checkHtml = '';
    ITEMS.forEach(function(it) {
        checkHtml +=
            '<div class="check-item">' +
                '<div class="chk-box"></div>' +
                '<span>'+escHtml(it.nombre.substring(0,20))+(it.nombre.length>20?'&hellip;':'')+' <b>(x'+it.cant+')</b></span>' +
            '</div>';
    });

    var nivelClass = 'nivel-' + KIT_NIVEL;

    return (
        '<div class="stk">' +
            '<div class="stk-head">' +
                logoHtml +
                '<div>' +
                    '<div class="stk-brand">ROBOTSchool Colombia</div>' +
                    '<div class="stk-kit-nm">'+escHtml(KIT_NOMBRE)+(KIT_CODIGO?' &middot; '+escHtml(KIT_CODIGO):'')+colegioStr+'</div>' +
                '</div>' +
            '</div>' +
            '<div class="stk-body">' +
                '<div class="stk-titulo">Contenido del Kit &mdash; '+ITEMS.length+' componentes</div>' +
                '<div class="comp-grid">'+compHtml+'</div>' +
            '</div>' +
            '<div class="check-list">' +
                '<div class="check-head">&#9745; Verificar antes de entregar</div>' +
                '<div class="check-cols">'+checkHtml+'</div>' +
            '</div>' +
            '<div class="stk-foot">' +
                '<span class="web">robotschool.com.co</span>' +
                '<span class="nivel-pill '+nivelClass+'">'+KIT_NIVEL.charAt(0).toUpperCase()+KIT_NIVEL.slice(1)+'</span>' +
            '</div>' +
        '</div>'
    );
}

function escHtml(str) {
    return String(str)
        .replace(/&/g,'&amp;')
        .replace(/</g,'&lt;')
        .replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;');
}

// Inicializar
renderStickers();
</script>
</body>
</html>
