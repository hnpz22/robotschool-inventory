<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();

$db  = Database::get();
$id  = (int)($_GET['id'] ?? 0);
$ped = $id ? $db->query("SELECT * FROM pedidos_importacion WHERE id=$id")->fetch() : null;
$items = $id ? $db->query("SELECT pi.*, e.nombre AS elem_nombre, e.codigo AS elem_codigo, e.peso_gramos FROM pedido_items pi JOIN elementos e ON e.id=pi.elemento_id WHERE pi.pedido_id=$id")->fetchAll() : [];

$pageTitle  = $ped ? 'Editar Pedido ' . $ped['codigo_pedido'] : 'Nuevo Pedido de Importaci&#243;n';
$activeMenu = 'pedidos';
$error = $success = '';

// Secuencia para c&#243;digo
if (!$ped) {
    $lastNum = $db->query("SELECT COUNT(*) FROM pedidos_importacion")->fetchColumn() + 1;
    $codigoSugerido = 'PED-' . date('Y') . '-' . str_pad($lastNum, 3, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_header') {
    if (!Auth::csrfVerify($_POST['csrf'] ?? '')) die('CSRF inv&#225;lido');
    try {
        $data = [
            'codigo_pedido'       => strtoupper(trim($_POST['codigo_pedido'])),
            'proveedor_id'        => $_POST['proveedor_id'] ?: null,
            'fecha_pedido'        => $_POST['fecha_pedido'],
            'fecha_envio'         => $_POST['fecha_envio'] ?: null,
            'fecha_llegada'       => $_POST['fecha_llegada'] ?: null,
            'numero_tracking_dhl' => trim($_POST['numero_tracking_dhl']),
            'peso_total_kg'       => (float)str_replace(',','.',($_POST['peso_total_kg']??0)),
            'costo_dhl_usd'       => (float)str_replace(',','.',($_POST['costo_dhl_usd']??0)),
            'tasa_cambio_usd_cop' => (float)str_replace(',','.',($_POST['tasa_cambio_usd_cop']??4200)),
            'valor_fob_usd'       => (float)str_replace(',','.',($_POST['valor_fob_usd']??0)),
            'valor_seguro_usd'    => (float)str_replace(',','.',($_POST['valor_seguro_usd']??0)),
            'arancel_pct'         => (float)str_replace(',','.',($_POST['arancel_pct']??5)),
            'iva_pct'             => (float)str_replace(',','.',($_POST['iva_pct']??19)),
            'otros_impuestos_cop' => (float)str_replace(',','.',($_POST['otros_impuestos_cop']??0)),
            'estado'              => $_POST['estado'] ?? 'borrador',
            'notas'               => trim($_POST['notas']),
        ];
        if ($ped) {
            $sets = implode(',', array_map(fn($k)=>"$k=:$k", array_keys($data)));
            $data['id'] = $id;
            $db->prepare("UPDATE pedidos_importacion SET $sets WHERE id=:id")->execute($data);
            $success = 'Pedido actualizado.';
            $ped = $db->query("SELECT * FROM pedidos_importacion WHERE id=$id")->fetch();
        } else {
            $data['created_by'] = Auth::user()['id'];
            $cols = implode(',', array_keys($data));
            $vals = ':' . implode(',:', array_keys($data));
            $db->prepare("INSERT INTO pedidos_importacion ($cols) VALUES ($vals)")->execute($data);
            $newId = $db->lastInsertId();
            header('Location: ' . APP_URL . '/modules/importaciones/form.php?id=' . $newId . '&ok=1');
            exit;
        }
    } catch (Exception $e) { $error = $e->getMessage(); }
}

// Cargar &#237;tems desde Excel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cargar_excel' && $ped) {
    if (!Auth::csrfVerify($_POST['csrf'] ?? '')) die('CSRF');
    $tmp  = $_FILES['excel']['tmp_name'] ?? '';
    $name = $_FILES['excel']['name']     ?? '';
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $resultado = ['ok'=>[], 'nuevo'=>[], 'error'=>[]];

    try {
        $rows = in_array($ext, ['xlsx','xls']) ? parsearExcelSimple($tmp) : parsearCSVSimple($tmp);
        if (empty($rows)) throw new Exception('No se encontraron filas de productos en el archivo.');

        $db->beginTransaction();
        foreach ($rows as $row) {
            $desc = trim($row['descripcion']);
            $cant = (int)$row['cantidad'];
            $prc  = (float)$row['precio'];
            $cod  = trim($row['codigo'] ?? '');
            if (!$desc || $cant <= 0) continue;

            // Buscar elemento por c&#243;digo o nombre
            $elem = null;
            if ($cod) {
                $s = $db->prepare("SELECT id,peso_gramos FROM elementos WHERE (codigo=? OR nombre LIKE ?) AND activo=1 LIMIT 1");
                $s->execute([$cod, '%'.$cod.'%']); $elem = $s->fetch();
            }
            if (!$elem) {
                $s = $db->prepare("SELECT id,peso_gramos FROM elementos WHERE nombre LIKE ? AND activo=1 LIMIT 1");
                $s->execute(['%'.substr($desc,0,20).'%']); $elem = $s->fetch();
            }

            if ($elem) {
                $db->prepare("INSERT INTO pedido_items (pedido_id,elemento_id,descripcion_item,cantidad,precio_unit_usd,peso_unit_gramos)
                    VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE cantidad=VALUES(cantidad),precio_unit_usd=VALUES(precio_unit_usd)")
                  ->execute([$id, $elem['id'], $desc, $cant, $prc, $elem['peso_gramos']??20]);
                $resultado['ok'][] = $desc;
            } else {
                // Guardar como &#237;tem sin elemento vinculado (descripcion_item)
                $chk = $db->prepare("SELECT id FROM pedido_items WHERE pedido_id=? AND descripcion_item=?");
                $chk->execute([$id, $desc]);
                if (!$chk->fetch()) {
                    $db->prepare("INSERT INTO pedido_items (pedido_id,elemento_id,descripcion_item,cantidad,precio_unit_usd,peso_unit_gramos)
                        VALUES (?,NULL,?,?,?,20)")
                      ->execute([$id, $desc, $cant, $prc]);
                }
                $resultado['nuevo'][] = $desc;
            }
        }
        $db->commit();
        $tot = count($resultado['ok']) + count($resultado['nuevo']);
        $success = "$tot &#237;tems cargados desde Excel. " .
            count($resultado['ok']).' vinculados al inventario, '.
            count($resultado['nuevo']).' sin vincular (marcados en rojo).';
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error = 'Error al leer el Excel: ' . $e->getMessage();
    }
}

// Agregar &#237;tem
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_item' && $ped) {
    if (!Auth::csrfVerify($_POST['csrf'] ?? '')) die('CSRF');
    try {
        $elemId = (int)$_POST['elemento_id'];
        $elem = $db->query("SELECT peso_gramos, precio_usd FROM elementos WHERE id=$elemId")->fetch();
        $db->prepare("INSERT INTO pedido_items (pedido_id,elemento_id,cantidad,precio_unit_usd,peso_unit_gramos) VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE cantidad=VALUES(cantidad),precio_unit_usd=VALUES(precio_unit_usd)")
           ->execute([$id, $elemId, (int)$_POST['cantidad'], (float)$_POST['precio_unit_usd'], $elem['peso_gramos']]);
        header('Location: ' . APP_URL . "/modules/importaciones/form.php?id=$id#items"); exit;
    } catch (Exception $e) { $error = $e->getMessage(); }
}

// Eliminar &#237;tem
if (isset($_GET['del_item']) && $ped) {
    $db->prepare("DELETE FROM pedido_items WHERE id=? AND pedido_id=?")->execute([(int)$_GET['del_item'], $id]);
    header('Location: ' . APP_URL . "/modules/importaciones/form.php?id=$id#items"); exit;
}

if (!empty($_GET['ok'])) $success = 'Pedido creado. Ahora agrega los elementos del pedido.';

$proveedores = $db->query("SELECT id,nombre FROM proveedores WHERE activo=1 ORDER BY nombre")->fetchAll();
$elementos   = $db->query("SELECT id,codigo,nombre FROM elementos WHERE activo=1 ORDER BY nombre")->fetchAll();
$cfg         = $db->query("SELECT clave,valor FROM configuracion WHERE clave IN ('trm_default','arancel_default_pct','iva_pct')")->fetchAll(PDO::FETCH_KEY_PAIR);

// &#9472;&#9472; Parsers de Excel/CSV para carga de &#237;tems &#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;
function parsearExcelSimple(string $path): array {
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) throw new Exception('No se pudo abrir el XLSX.');
    $ss = []; $ssXml = $zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml) {
        $xml = simplexml_load_string($ssXml);
        foreach ($xml->si as $si) {
            $t = isset($si->t) ? (string)$si->t : '';
            if (!$t) foreach ($si->r as $r) $t .= (string)$r->t;
            $ss[] = $t;
        }
    }
    $shXml = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if (!$shXml) throw new Exception('Hoja no encontrada.');

    $sheet = simplexml_load_string($shXml);
    $sheet->registerXPathNamespace('s','http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $matrix = [];
    foreach ($sheet->xpath('//s:row') as $row) {
        $rn = (int)$row['r']; $cells = [];
        foreach ($row->c as $cell) {
            $col = preg_replace('/[0-9]/','',(string)$cell['r']);
            $idx = 0; foreach(str_split(strtoupper($col)) as $c) $idx=$idx*26+(ord($c)-64); $idx--;
            $type = (string)$cell['t']; $val = isset($cell->v) ? (string)$cell->v : '';
            if ($type==='s') $val = $ss[(int)$val] ?? '';
            $cells[$idx] = trim($val);
        }
        $matrix[$rn] = $cells;
    }

    // Detectar cabecera
    $hRow = null; $hNum = 0;
    foreach ($matrix as $rn => $cells) {
        $lc = array_map('strtolower', $cells);
        if (in_array('description',$lc)||in_array('descripcion',$lc)||in_array('nombre',$lc)) {
            $hRow = $cells; $hNum = $rn; break;
        }
    }
    if (!$hRow) {
        // Sin cabecera &#8212; asumir col0=desc, col1=cant, col2=precio
        $rows = [];
        foreach ($matrix as $rn => $cells) {
            $desc = trim($cells[0]??''); $cant = trim($cells[1]??''); $prc = trim($cells[2]??'');
            if (!$desc||!is_numeric($cant)||(float)$cant<=0) continue;
            $skip = ['total','product cost','ship','payment','subtotal'];
            foreach ($skip as $s) if (str_contains(strtolower($desc),$s)) continue 2;
            $rows[] = ['descripcion'=>$desc,'cantidad'=>(int)$cant,'precio'=>(float)$prc,'codigo'=>''];
        }
        return $rows;
    }

    $map = [];
    foreach ($hRow as $ci => $h) {
        $lc = strtolower(trim($h));
        if (str_contains($lc,'description')||str_contains($lc,'descripcion')||str_contains($lc,'nombre')||$lc==='name') $map['desc']=$ci;
        elseif (preg_match('/^qty|^quantity|^cant/',$lc)) $map['cant']=$ci;
        elseif ($lc==='usd/p'||preg_match('/unit.?price|precio|usd/',$lc)) $map['precio']=$ci;
        elseif (preg_match('/code|sku|ref|codigo/',$lc)) $map['cod']=$ci;
    }

    $rows = [];
    $skip = ['total','product cost','ship','payment','subtotal','paid','left'];
    foreach ($matrix as $rn => $cells) {
        if ($rn <= $hNum) continue;
        $desc = trim($cells[$map['desc']??0]??'');
        $cant = trim($cells[$map['cant']??1]??0);
        $prc  = (float)($cells[$map['precio']??2]??0);
        $cod  = trim($cells[$map['cod']??-1]??'');
        if (!$desc||!is_numeric($cant)||(float)$cant<=0) continue;
        foreach ($skip as $s) if (str_contains(strtolower($desc),$s)) continue 2;
        $rows[] = ['descripcion'=>$desc,'cantidad'=>(int)$cant,'precio'=>$prc,'codigo'=>$cod];
    }
    return $rows;
}

function parsearCSVSimple(string $path): array {
    $rows = []; $header = null;
    $h = fopen($path,'r');
    while (($line = fgetcsv($h,2000,',')) !== false) {
        $lc = array_map('strtolower',array_map('trim',$line));
        if (!$header && (in_array('description',$lc)||in_array('descripcion',$lc)||in_array('nombre',$lc)||in_array('qty',$lc))) {
            $header = $lc; continue;
        }
        if (!$header) {
            // Sin cabecera
            $desc=$line[0]??''; $cant=$line[1]??0; $prc=$line[2]??0;
            if (trim($desc)&&is_numeric($cant)&&(float)$cant>0) $rows[]=[ 'descripcion'=>trim($desc),'cantidad'=>(int)$cant,'precio'=>(float)$prc,'codigo'=>''];
            continue;
        }
        $r = array_combine(array_slice($header,0,count($line)),$line);
        $desc = $r['description']??$r['descripcion']??$r['nombre']??'';
        $cant = $r['qty']??$r['quantity']??$r['cantidad']??0;
        $prc  = $r['usd/p']??$r['price']??$r['precio']??$r['precio_usd']??0;
        $cod  = $r['code']??$r['codigo']??$r['sku']??'';
        if (!trim($desc)||!is_numeric($cant)||(float)$cant<=0) continue;
        $rows[] = ['descripcion'=>trim($desc),'cantidad'=>(int)$cant,'precio'=>(float)$prc,'codigo'=>trim($cod)];
    }
    fclose($h); return $rows;
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="d-flex align-items-center gap-2 mb-4">
  <a href="<?= APP_URL ?>/modules/importaciones/" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i></a>
  <div>
    <h4 class="fw-bold mb-0"><?= $pageTitle ?></h4>
    <?php if ($ped): ?><span class="text-muted small">Estado: <strong><?= $ped['estado'] ?></strong></span><?php endif; ?>
  </div>
  <?php if ($ped && in_array($ped['estado'],['recibido','borrador','en_aduana'])): ?>
  <a href="<?= APP_URL ?>/modules/importaciones/liquidar.php?id=<?= $id ?>" class="btn btn-success ms-auto">
    <i class="bi bi-calculator me-1"></i>Liquidar Pedido
  </a>
  <?php endif; ?>
  <?php if ($ped && !empty($items)): ?>
  <a href="<?= APP_URL ?>/modules/importaciones/exportar_orden.php?id=<?= $id ?>"
     class="btn btn-outline-success <?= !$ped || in_array($ped['estado'],['recibido','borrador','en_aduana']) ? '' : 'ms-auto' ?>"
     title="Descargar Excel para enviar al proveedor">
    <i class="bi bi-file-earmark-excel me-1"></i>Descargar para Proveedor
  </a>
  <?php endif; ?>
</div>

<?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= $success ?></div><?php endif; ?>

<!-- &#9472;&#9472; FORMULARIO CABECERA &#9472;&#9472; -->
<form method="POST">
  <input type="hidden" name="action" value="save_header">
  <input type="hidden" name="csrf" value="<?= Auth::csrfToken() ?>">

  <div class="row g-4">
    <div class="col-lg-8">
      <div class="section-card">
        <h6 class="fw-bold mb-3 text-primary"><i class="bi bi-file-earmark-text me-2"></i>Informaci&#243;n del Pedido</h6>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">C&#243;digo Pedido *</label>
            <input type="text" name="codigo_pedido" class="form-control text-uppercase fw-bold" required
                   value="<?= htmlspecialchars($ped['codigo_pedido'] ?? $codigoSugerido ?? '') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Proveedor</label>
            <select name="proveedor_id" class="form-select">
              <option value="">Sin especificar</option>
              <?php foreach ($proveedores as $p): ?>
                <option value="<?= $p['id'] ?>" <?= ($ped['proveedor_id']??0)==$p['id']?'selected':'' ?>><?= htmlspecialchars($p['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Estado</label>
            <select name="estado" class="form-select">
              <?php foreach (['borrador','en_transito','en_aduana','recibido','liquidado'] as $s): ?>
                <option value="<?= $s ?>" <?= ($ped['estado']??'borrador')===$s?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Fecha Pedido *</label>
            <input type="date" name="fecha_pedido" class="form-control" required value="<?= $ped['fecha_pedido'] ?? date('Y-m-d') ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Fecha Env&#237;o</label>
            <input type="date" name="fecha_envio" class="form-control" value="<?= $ped['fecha_envio'] ?? '' ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Fecha Llegada</label>
            <input type="date" name="fecha_llegada" class="form-control" value="<?= $ped['fecha_llegada'] ?? '' ?>">
          </div>
          <div class="col-12">
            <label class="form-label">N&#250;mero Tracking DHL</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-airplane"></i></span>
              <input type="text" name="numero_tracking_dhl" class="form-control" placeholder="1234567890"
                     value="<?= htmlspecialchars($ped['numero_tracking_dhl'] ?? '') ?>">
              <?php if ($ped['numero_tracking_dhl'] ?? ''): ?>
              <a href="https://www.dhl.com/co-es/home/tracking.html?tracking-id=<?= urlencode($ped['numero_tracking_dhl']) ?>" target="_blank" class="btn btn-outline-info">
                <i class="bi bi-search"></i> Rastrear
              </a>
              <?php endif; ?>
            </div>
          </div>
          <div class="col-12">
            <label class="form-label">Notas</label>
            <textarea name="notas" class="form-control" rows="2"><?= htmlspecialchars($ped['notas'] ?? '') ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-4">
      <div class="section-card">
        <h6 class="fw-bold mb-3 text-warning"><i class="bi bi-calculator me-2"></i>Costos y Liquidaci&#243;n</h6>
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label">Peso Total Paquete (kg)</label>
            <div class="input-group">
              <input type="number" name="peso_total_kg" class="form-control" step="0.001" min="0"
                     value="<?= $ped['peso_total_kg'] ?? '' ?>" placeholder="0.000">
              <span class="input-group-text">kg</span>
            </div>
          </div>
          <div class="col-12">
            <label class="form-label">Costo Flete DHL (USD)</label>
            <div class="input-group">
              <span class="input-group-text">$</span>
              <input type="number" name="costo_dhl_usd" class="form-control" step="0.01"
                     value="<?= $ped['costo_dhl_usd'] ?? '' ?>">
              <span class="input-group-text">USD</span>
            </div>
          </div>
          <div class="col-12">
            <label class="form-label">TRM (USD &#8594; COP)</label>
            <div class="input-group">
              <span class="input-group-text">$</span>
              <input type="number" name="tasa_cambio_usd_cop" class="form-control" step="1"
                     value="<?= $ped['tasa_cambio_usd_cop'] ?? ($cfg['trm_default']??4200) ?>">
            </div>
          </div>
          <div class="col-12">
            <label class="form-label">Valor FOB (USD)</label>
            <div class="input-group">
              <span class="input-group-text">$</span>
              <input type="number" name="valor_fob_usd" class="form-control" step="0.01"
                     value="<?= $ped['valor_fob_usd'] ?? '' ?>">
              <span class="input-group-text">USD</span>
            </div>
          </div>
          <div class="col-6">
            <label class="form-label">Arancel %</label>
            <div class="input-group">
              <input type="number" name="arancel_pct" class="form-control" step="0.1"
                     value="<?= $ped['arancel_pct'] ?? ($cfg['arancel_default_pct']??5) ?>">
              <span class="input-group-text">%</span>
            </div>
          </div>
          <div class="col-6">
            <label class="form-label">IVA %</label>
            <div class="input-group">
              <input type="number" name="iva_pct" class="form-control" step="0.1"
                     value="<?= $ped['iva_pct'] ?? ($cfg['iva_pct']??19) ?>">
              <span class="input-group-text">%</span>
            </div>
          </div>
          <div class="col-12">
            <label class="form-label">Otros Gastos (COP)</label>
            <div class="input-group">
              <span class="input-group-text">$</span>
              <input type="number" name="otros_impuestos_cop" class="form-control" step="100"
                     value="<?= $ped['otros_impuestos_cop'] ?? 0 ?>">
            </div>
            <div class="form-text">Agencia aduanera, gastos bancarios</div>
          </div>
        </div>
      </div>
      <div class="d-grid">
        <button type="submit" class="btn btn-primary fw-bold">
          <i class="bi bi-save me-2"></i><?= $ped ? 'Guardar Cambios' : 'Crear Pedido' ?>
        </button>
      </div>
    </div>
  </div>
</form>

<!-- &#9472;&#9472; &#205;TEMS DEL PEDIDO &#9472;&#9472; -->
<?php if ($ped): ?>
<div class="section-card mt-4" id="items">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-bold mb-0"><i class="bi bi-list-check me-2 text-primary"></i>Elementos del Pedido</h6>
    <div class="d-flex gap-2">
      <?php if ($ped): ?>
      <button class="btn btn-sm btn-outline-success fw-bold" data-bs-toggle="modal" data-bs-target="#modalExcel">
        <i class="bi bi-file-earmark-excel me-1"></i>Cargar desde Excel
      </button>
      <?php endif; ?>
      <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#addItemForm">
        <i class="bi bi-plus-lg me-1"></i>Agregar Elemento
      </button>
    </div>
  </div>

  <!-- Form agregar &#237;tem -->
  <div class="collapse mb-3" id="addItemForm">
    <form method="POST" class="bg-light rounded p-3">
      <input type="hidden" name="action" value="add_item">
      <input type="hidden" name="csrf" value="<?= Auth::csrfToken() ?>">
      <div class="row g-2 align-items-end">
        <div class="col-md-5">
          <label class="form-label mb-1">Elemento</label>
          <select name="elemento_id" class="form-select form-select-sm" required>
            <option value="">Seleccionar...</option>
            <?php foreach ($elementos as $e): ?>
              <option value="<?= $e['id'] ?>" data-precio="<?= $e['precio_usd'] ?>"><?= $e['codigo'] ?> &#8212; <?= htmlspecialchars($e['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <label class="form-label mb-1">Cantidad</label>
          <input type="number" name="cantidad" class="form-control form-control-sm" min="1" value="1" required>
        </div>
        <div class="col-md-3">
          <label class="form-label mb-1">Precio Unit. USD</label>
          <div class="input-group input-group-sm">
            <span class="input-group-text">$</span>
            <input type="number" name="precio_unit_usd" id="precioItem" class="form-control" step="0.0001" min="0" required>
          </div>
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn btn-primary btn-sm w-100">Agregar</button>
        </div>
      </div>
    </form>
  </div>

  <?php
  $pesoTotal = array_sum(array_column($items,'peso_total_gramos'));
  $subtotalUSD = array_sum(array_column($items,'subtotal_usd'));
  ?>

  <div class="table-responsive">
    <table class="table table-sm table-hover table-inv mb-0">
      <thead><tr>
        <th>C&#243;digo</th><th>Elemento</th><th>Cant.</th>
        <th>Precio USD</th><th>Subtotal USD</th>
        <th>Peso Unit (g)</th><th>Peso Total (g)</th>
        <?php if ($ped['estado']==='liquidado'): ?>
        <th>Flete COP</th><th>Arancel COP</th><th>IVA COP</th><th>Costo Unit Final</th>
        <?php endif; ?>
        <th></th>
      </tr></thead>
      <tbody>
      <?php foreach ($items as $it): ?>
      <tr>
        <td><code class="text-primary"><?= htmlspecialchars($it['elem_codigo']) ?></code></td>
        <td><?= htmlspecialchars($it['elem_nombre']) ?></td>
        <td class="fw-bold"><?= $it['cantidad'] ?></td>
        <td><?= usd($it['precio_unit_usd']) ?></td>
        <td class="fw-semibold"><?= usd($it['subtotal_usd']) ?></td>
        <td><?= number_format($it['peso_unit_gramos'],1) ?>g</td>
        <td><?= number_format($it['peso_total_gramos'],1) ?>g
          <?php if ($pesoTotal>0): ?>
          <span class="text-muted small">(<?= round($it['peso_total_gramos']/$pesoTotal*100,1) ?>%)</span>
          <?php endif; ?>
        </td>
        <?php if ($ped['estado']==='liquidado'): ?>
        <td><?= cop($it['flete_asignado_cop']) ?></td>
        <td><?= cop($it['arancel_asignado_cop']) ?></td>
        <td><?= cop($it['iva_asignado_cop']) ?></td>
        <td class="fw-bold text-success"><?= cop($it['costo_unit_final_cop']) ?></td>
        <?php endif; ?>
        <td>
          <?php if ($ped['estado'] !== 'liquidado'): ?>
          <a href="?id=<?= $id ?>&del_item=<?= $it['id'] ?>&csrf=<?= Auth::csrfToken() ?>" class="btn btn-xs btn-outline-danger btn-sm" data-confirm="&#191;Eliminar este &#237;tem?"><i class="bi bi-trash"></i></a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (empty($items)): ?>
        <tr><td colspan="12" class="text-muted text-center py-3">No hay elementos. Agrega el contenido del pedido.</td></tr>
      <?php endif; ?>
      </tbody>
      <?php if (!empty($items)): ?>
      <tfoot class="fw-bold bg-light">
        <tr>
          <td colspan="4">TOTAL</td>
          <td><?= usd($subtotalUSD) ?></td>
          <td>&#8212;</td>
          <td><?= number_format($pesoTotal,1) ?>g</td>
          <?php if ($ped['estado']==='liquidado'): ?>
          <td><?= cop(array_sum(array_column($items,'flete_asignado_cop'))) ?></td>
          <td><?= cop(array_sum(array_column($items,'arancel_asignado_cop'))) ?></td>
          <td><?= cop(array_sum(array_column($items,'iva_asignado_cop'))) ?></td>
          <td><?= cop($ped['costo_total_cop']) ?></td>
          <?php endif; ?>
          <td></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>
<?php endif; ?>

<script>
// Auto-llenar precio al seleccionar elemento
document.querySelector('[name=elemento_id]')?.addEventListener('change', function() {
  const opt = this.options[this.selectedIndex];
  const precio = opt.dataset.precio || '';
  document.getElementById('precioItem').value = precio;
});
</script>

<?php if ($ped): ?>
<!-- Modal Cargar desde Excel -->
<div class="modal fade" id="modalExcel" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-success text-white">
        <h5 class="modal-title fw-bold">
          <i class="bi bi-file-earmark-excel me-2"></i>Cargar &#237;tems desde Excel / CSV
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="cargar_excel">
        <input type="hidden" name="csrf"   value="<?= Auth::csrfToken() ?>">
        <div class="modal-body">
          <div class="alert alert-info py-2 small">
            <i class="bi bi-info-circle me-1"></i>
            El sistema reconoce columnas <strong>Description/Nombre, QTY/Cantidad, USD/P/Precio</strong> y <strong>Code/SKU</strong>.
            Los &#237;tems que coincidan con el inventario quedan vinculados autom&#225;ticamente.
          </div>

          <!-- Drop zone -->
          <div id="exDZ" onclick="document.getElementById('exFile').click()"
               style="border:2px dashed #16a34a;border-radius:12px;padding:2.5rem;text-align:center;cursor:pointer;background:#f0fdf4;transition:.2s;">
            <div style="font-size:2.5rem;">&#128202;</div>
            <div class="fw-bold mt-1">Arrastra tu archivo aqui o haz clic</div>
            <div class="text-muted small">Excel del proveedor China &middot; Tu propio formato</div>
            <div class="mt-2">
              <span class="badge bg-success">.XLSX</span>
              <span class="badge bg-primary">.XLS</span>
              <span class="badge bg-secondary">.CSV</span>
            </div>
            <div id="exFname" class="mt-2 fw-bold text-success d-none"></div>
          </div>
          <input type="file" id="exFile" name="excel" class="d-none" accept=".xlsx,.xls,.csv">

          <!-- Formatos soportados -->
          <div class="mt-3">
            <div class="small text-muted fw-semibold mb-1">Formatos que reconoce:</div>
            <div class="row g-2" style="font-size:.75rem;">
              <div class="col-6">
                <div class="p-2 rounded border">
                  <div class="fw-bold text-success mb-1">&#127464;&#127475; Proveedor China</div>
                  <code>No | Description | QTY | Photo | USD/P | Total</code>
                </div>
              </div>
              <div class="col-6">
                <div class="p-2 rounded border">
                  <div class="fw-bold text-primary mb-1">&#128196; Formato libre</div>
                  <code>Nombre | Cantidad | Precio_USD | SKU</code>
                  <div class="text-muted">o sin cabecera: col A, B, C</div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" id="btnCargar" class="btn btn-success fw-bold" disabled>
            <i class="bi bi-cloud-upload me-2"></i>Cargar Items al Pedido
          </button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
const exFile = document.getElementById('exFile');
const exDZ   = document.getElementById('exDZ');
exFile.addEventListener('change', function() {
  if (this.files[0]) {
    document.getElementById('exFname').textContent = '&#128196; ' + this.files[0].name;
    document.getElementById('exFname').classList.remove('d-none');
    document.getElementById('btnCargar').disabled = false;
    exDZ.style.background = '#dcfce7'; exDZ.style.borderColor = '#16a34a';
  }
});
exDZ.addEventListener('dragover', e => { e.preventDefault(); exDZ.style.background='#bbf7d0'; });
exDZ.addEventListener('dragleave', () => { exDZ.style.background='#f0fdf4'; });
exDZ.addEventListener('drop', e => {
  e.preventDefault();
  if (e.dataTransfer.files[0]) { exFile.files=e.dataTransfer.files; exFile.dispatchEvent(new Event('change')); }
});
</script>
<?php endif; ?>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
