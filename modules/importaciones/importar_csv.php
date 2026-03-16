<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();

$db        = Database::get();
$pageTitle = 'Orden de Compra China';
$activeMenu= 'importaciones';
$step      = $_GET['step'] ?? 'upload';
$error     = '';

// &#9472;&#9472; Parsear Excel/CSV &#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='parse') {
    if (!Auth::csrfVerify($_POST['csrf']??'')) die('CSRF');
    $tmp  = $_FILES['archivo']['tmp_name'] ?? '';
    $name = $_FILES['archivo']['name']     ?? '';
    if (!$tmp) { $error='Selecciona un archivo.'; goto showForm; }
    $ext = strtolower(pathinfo($name,PATHINFO_EXTENSION));
    try {
        $parsed = in_array($ext,['xlsx','xls']) ? parsearExcel($tmp) : parsearCSV($tmp);
        if (empty($parsed['rows'])) { $error='No se encontraron productos.'; goto showForm; }
        $_SESSION['imp_rows']    = $parsed['rows'];
        $_SESSION['imp_resumen'] = $parsed['resumen'];
        $_SESSION['imp_source']  = $name;
        $step = 'preview';
    } catch (Exception $e) { $error='Error: '.$e->getMessage(); }
}

// &#9472;&#9472; Confirmar y crear pedido &#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='confirm') {
    if (!Auth::csrfVerify($_POST['csrf']??'')) die('CSRF');
    $rows   = $_SESSION['imp_rows'] ?? [];
    $selIdx = $_POST['sel']         ?? [];
    if (empty($selIdx)) { $error='Selecciona al menos un producto.'; $step='preview'; goto showForm; }

    $provId       = (int)$_POST['proveedor_id'];
    $tracking     = trim($_POST['tracking']       ?? '');
    $pesoKg       = (float)($_POST['peso_kg']     ?? 0);
    $dhlUSD       = (float)($_POST['dhl_usd']     ?? 0);
    $comPct       = (float)($_POST['com_pct']      ?? 0);
    $comFija      = (float)($_POST['com_fija']     ?? 0);
    $metodo       = trim($_POST['metodo_pago']     ?? 'paypal');
    $tarjeta1     = trim($_POST['tarjeta1']        ?? '');
    $monto1       = (float)($_POST['monto1']       ?? 0);
    $tarjeta2     = trim($_POST['tarjeta2']        ?? '');
    $monto2       = (float)($_POST['monto2']       ?? 0);
    $trm          = (float)($_POST['trm']          ?? 4200);
    $arancelPct   = (float)($_POST['arancel_pct']  ?? 5);
    $ivaPct       = (float)($_POST['iva_pct']      ?? 19);
    $notas        = trim($_POST['notas']           ?? '');

    try {
        $db->beginTransaction();
        $fob = 0;
        foreach ($selIdx as $i) { if ($rows[$i]??null) $fob += $rows[$i]['total_usd']; }
        $comisionUSD = ($fob+$dhlUSD)*$comPct/100 + $comFija;
        $totalPagar  = $fob + $dhlUSD + $comisionUSD;

        $db->prepare("INSERT INTO pedidos_importacion
            (proveedor_id,tracking_dhl,peso_total_kg,costo_dhl_usd,TRM,valor_fob_usd,
             arancel_pct,iva_pct,otros_impuestos_cop,estado,created_by)
            VALUES(?,?,?,?,?,?,?,?,?,'borrador',?)")
          ->execute([$provId,$tracking,$pesoKg,$dhlUSD+$comisionUSD,$trm,$fob,$arancelPct,$ivaPct,0,Auth::user()['id']]);
        $pedidoId = $db->lastInsertId();

        // Auditor&#237;a de datos de pago
        auditoria($db,'pedidos_importacion',$pedidoId,'pago_registrado',null,[
            'metodo'=>$metodo,'total_usd'=>$totalPagar,'comision'=>$comisionUSD,
            'tarjeta1'=>$tarjeta1,'monto1'=>$monto1,
            'tarjeta2'=>$tarjeta2,'monto2'=>$monto2,
            'notas'=>$notas,
        ]);

        foreach ($selIdx as $i) {
            $r = $rows[$i]??null; if (!$r) continue;
            $desc = trim($r['descripcion']);
            $elem = null;
            if ($r['codigo']) {
                $s=$db->prepare("SELECT id,peso_gramos FROM elementos WHERE codigo=? AND activo=1 LIMIT 1");
                $s->execute([$r['codigo']]); $elem=$s->fetch();
            }
            if (!$elem) {
                $s=$db->prepare("SELECT id,peso_gramos FROM elementos WHERE nombre LIKE ? AND activo=1 LIMIT 1");
                $s->execute(['%'.substr($desc,0,22).'%']); $elem=$s->fetch();
            }
            $db->prepare("INSERT INTO pedido_items(pedido_id,elemento_id,descripcion_item,cantidad,precio_unit_usd,peso_unit_gramos)
                VALUES(?,?,?,?,?,?)")
              ->execute([$pedidoId,$elem?$elem['id']:null,$desc,(int)$r['cantidad'],(float)$r['precio_unit_usd'],$elem?($elem['peso_gramos']??20):20]);
        }

        $db->commit();
        unset($_SESSION['imp_rows'],$_SESSION['imp_resumen'],$_SESSION['imp_source']);
        header('Location: '.APP_URL.'/modules/importaciones/form.php?id='.$pedidoId.'&ok=importado'); exit;
    } catch (Exception $e) {
        $db->rollBack();
        $error='Error: '.$e->getMessage(); $step='preview';
    }
}

// &#9472;&#9472; Parser Excel (PHP puro &#8212; no requiere librer&#237;a) &#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;&#9472;
function parsearExcel(string $path): array {
    $zip = new ZipArchive();
    if ($zip->open($path)!==true) throw new Exception('No se pudo abrir el XLSX.');
    $ss=[]; $ssXml=$zip->getFromName('xl/sharedStrings.xml');
    if ($ssXml) {
        $xml=simplexml_load_string($ssXml);
        foreach ($xml->si as $si) {
            $t=isset($si->t)?(string)$si->t:'';
            if (!$t) foreach($si->r as $r) $t.=(string)$r->t;
            $ss[]=$t;
        }
    }
    $shXml=$zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if (!$shXml) throw new Exception('Hoja no encontrada.');

    $sheet=simplexml_load_string($shXml);
    $sheet->registerXPathNamespace('s','http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $matrix=[];
    foreach ($sheet->xpath('//s:row') as $row) {
        $rn=(int)$row['r']; $cells=[];
        foreach ($row->c as $cell) {
            $col=preg_replace('/[0-9]/','',($cell['r']));
            $idx=0; foreach(str_split(strtoupper($col))as $c) $idx=$idx*26+(ord($c)-64); $idx--;
            $type=(string)$cell['t']; $val=isset($cell->v)?(string)$cell->v:'';
            if ($type==='s') $val=$ss[(int)$val]??'';
            $cells[$idx]=trim($val);
        }
        $matrix[$rn]=$cells;
    }

    // Detectar cabecera flexible
    $hRow=null; $hNum=0;
    foreach ($matrix as $rn=>$cells) {
        $lc=array_map('strtolower',array_map('trim',$cells));
        if (in_array('description',$lc)||in_array('descripcion',$lc)) {
            $hRow=$cells; $hNum=$rn; break;
        }
    }
    if (!$hRow) throw new Exception('No se encontr&#243; columna Description/Descripcion.');

    $map=[];
    foreach ($hRow as $ci=>$h) {
        $lc=strtolower(trim($h));
        if (str_contains($lc,'description')||str_contains($lc,'descripcion')||$lc==='name') $map['descripcion']=$ci;
        elseif (preg_match('/^qty|^quantity|^cant/',$lc))  $map['cantidad']=$ci;
        elseif ($lc==='usd/p'||preg_match('/unit.?price|usd/',$lc)) $map['precio']=$ci;
        elseif (str_contains($lc,'total'))   $map['total']=$ci;
        elseif (preg_match('/code|sku|ref|codigo/',$lc))   $map['codigo']=$ci;
        elseif (preg_match('/photo|image|foto/',$lc))      $map['foto']=$ci;
    }

    $rows=[]; $resumen=['product_cost'=>0,'ship_cost'=>0,'payment_fee'=>0,'total'=>0,'paid'=>0,'left'=>0];
    $resKeys=['product cost'=>'product_cost','ship cost'=>'ship_cost','payment fee'=>'payment_fee','total'=>'total','paid'=>'paid','left'=>'left'];

    foreach ($matrix as $rn=>$cells) {
        if ($rn<=$hNum) continue;
        $desc=trim($cells[$map['descripcion']??-1]??'');
        $cant=trim($cells[$map['cantidad']??-1]??'');

        // Detectar filas de resumen
        $dl=strtolower($desc);
        foreach ($resKeys as $kw=>$field) {
            if (str_contains($dl,$kw)) {
                // buscar valor num&#233;rico en la fila
                foreach ($cells as $v) {
                    if (is_numeric($v)&&(float)$v>0) { $resumen[$field]=(float)$v; break; }
                }
                continue 2;
            }
        }

        if (!$desc||!is_numeric($cant)||(float)$cant<=0) continue;

        $prc=(float)($cells[$map['precio']??-1]??0);
        $cod=trim($cells[$map['codigo']??-1]??'');

        $rows[]=[
            'descripcion'     =>$desc,
            'cantidad'        =>(int)$cant,
            'precio_unit_usd' =>$prc,
            'total_usd'       =>round((float)$cant*$prc,2),
            'codigo'          =>$cod,
            'tiene_foto'      =>isset($map['foto']),
        ];
    }
    return ['rows'=>$rows,'resumen'=>$resumen];
}

function parsearCSV(string $path): array {
    $rows=[]; $header=null;
    $h=fopen($path,'r');
    while (($line=fgetcsv($h,2000,','))!==false) {
        $lc=array_map('strtolower',array_map('trim',$line));
        if (!$header&&(in_array('description',$lc)||in_array('qty',$lc))) { $header=$lc; continue; }
        if (!$header) continue;
        $r=array_combine(array_slice($header,0,count($line)),$line);
        $desc=$r['description']??$r['descripcion']??'';
        $cant=$r['qty']??$r['qty ']??$r['quantity']??0;
        $prc =$r['usd/p']??$r['price']??$r['unit price']??0;
        $cod =$r['code']??$r['codigo']??$r['sku']??'';
        if (!trim($desc)||!is_numeric($cant)||(float)$cant<=0) continue;
        if (str_contains(strtolower($desc),'product cost')||str_contains(strtolower($desc),'total')) continue;
        $rows[]=['descripcion'=>trim($desc),'cantidad'=>(int)$cant,'precio_unit_usd'=>(float)$prc,'total_usd'=>round((float)$cant*(float)$prc,2),'codigo'=>$cod,'tiene_foto'=>false];
    }
    fclose($h);
    return ['rows'=>$rows,'resumen'=>[]];
}

// Columnas opcionales que pueden no existir si la migraci&#243;n v1.4 no corri&#243; completa
try {
    $provChina=$db->query("SELECT id,nombre,nombre_comercial FROM proveedores WHERE activo=1 ORDER BY nombre")->fetchAll();
} catch(Exception $e) {
    $provChina=$db->query("SELECT id,nombre,nombre AS nombre_comercial FROM proveedores WHERE activo=1 ORDER BY nombre")->fetchAll();
}
$categorias=$db->query("SELECT id,nombre,prefijo FROM categorias WHERE activa=1 ORDER BY nombre")->fetchAll();
$cfg=$db->query("SELECT clave,valor FROM configuracion")->fetchAll(PDO::FETCH_KEY_PAIR);
$previewRows=$_SESSION['imp_rows']    ?? [];
$resumenFile=$_SESSION['imp_resumen'] ?? [];
$sourceName =$_SESSION['imp_source']  ?? '';
$trm_def    =$cfg['trm_default']??4200;
$aran_def   =$cfg['arancel_pct']??5;
$iva_def    =$cfg['iva_pct']??19;

showForm:
require_once dirname(__DIR__,2).'/includes/header.php';
?>

<div class="d-flex align-items-center gap-2 mb-4">
  <a href="<?=APP_URL?>/modules/importaciones/" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i></a>
  <div>
    <h4 class="fw-bold mb-0">&#127464;&#127475; Orden de Compra &#8212; Proveedor China</h4>
    <p class="text-muted small mb-0">Importa tu Excel del proveedor y registra el pago por PayPal/tarjeta</p>
  </div>
</div>

<!-- Stepper -->
<div class="d-flex align-items-center mb-4">
  <?php foreach([['upload','1','Subir archivo'],['preview','2','Revisar & Pagar'],['done','3','Pedido creado']]as[$k,$n,$lbl]):
    $o=['upload'=>0,'preview'=>1,'done'=>2]; $curo=['upload'=>0,'preview'=>1,'confirm'=>2,'done'=>2];
    $est=$o[$k]<($curo[$step]??0)?'done':($k===$step?'active':'pending');
  ?>
  <div class="d-flex align-items-center <?=$k!=='done'?'flex-grow-1':''?>">
    <div class="rounded-circle fw-bold d-flex align-items-center justify-content-center"
         style="width:30px;height:30px;flex-shrink:0;font-size:.82rem;
           background:<?=$est==='done'?'#16a34a':($est==='active'?'#3a72e8':'#e5e7eb')?>;
           color:<?=$est==='pending'?'#6b7280':'#fff'?>">
      <?=$est==='done'?'OK':$n?>
    </div>
    <span class="ms-2 small fw-semibold <?=$est==='active'?'text-primary':($est==='done'?'text-success':'text-muted')?>"><?=$lbl?></span>
    <?php if($k!=='done'):?><div class="flex-grow-1 mx-3" style="height:2px;background:<?=$o[$k]<($curo[$step]??0)?'#16a34a':'#e5e7eb'?>;"></div><?php endif;?>
  </div>
  <?php endforeach;?>
</div>

<?php if($error):?><div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle me-2"></i><?=htmlspecialchars($error)?></div><?php endif;?>

<?php if($step==='upload'): ?>
<!-- &#9552;&#9552; PASO 1: SUBIR &#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552; -->
<div class="row g-4">
  <div class="col-lg-6">
    <div class="section-card">
      <h6 class="fw-bold mb-3 text-primary"><i class="bi bi-cloud-upload me-2"></i>Seleccionar Archivo</h6>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="parse">
        <input type="hidden" name="csrf"   value="<?=Auth::csrfToken()?>">
        <div id="dz" onclick="document.getElementById('fi').click()"
             style="border:2px dashed #3a72e8;border-radius:14px;padding:3rem;text-align:center;cursor:pointer;background:#f8faff;transition:.2s;">
          <div style="font-size:3rem;">&#128194;</div>
          <div class="fw-bold mt-2">Arrastra tu archivo aqu&#237;</div>
          <div class="text-muted small">o haz clic para buscar</div>
          <div class="mt-2"><span class="badge bg-success">.XLSX</span> <span class="badge bg-primary">.XLS</span> <span class="badge bg-secondary">.CSV</span></div>
          <div id="fn" class="mt-2 fw-bold text-success d-none"></div>
        </div>
        <input type="file" id="fi" name="archivo" class="d-none" accept=".csv,.xlsx,.xls">
        <button type="submit" id="btnP" class="btn btn-primary w-100 btn-lg fw-bold mt-3" disabled>
          <i class="bi bi-table me-2"></i>Leer y Previsualizar
        </button>
      </form>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="section-card">
      <h6 class="fw-bold mb-2">&#128202; Formato del proveedor China</h6>
      <div class="table-responsive mb-3">
        <table class="table table-sm table-bordered" style="font-size:.75rem;">
          <thead class="table-dark"><tr><th>No</th><th>Description</th><th>QTY</th><th>Photo</th><th>USD/P</th><th>Total</th></tr></thead>
          <tbody>
            <tr><td>1</td><td>UNO R3 CH340 Chip</td><td>400</td><td>[img]</td><td>2.83</td><td>1,132</td></tr>
            <tr><td>2</td><td>ESP32 WiFi+Bluetooth</td><td>150</td><td>[img]</td><td>2.80</td><td>420</td></tr>
            <tr><td>3</td><td>Servo SG90 9g</td><td>200</td><td>[img]</td><td>0.70</td><td>140</td></tr>
            <tr class="table-warning"><td colspan="3"></td><td colspan="2" class="text-end fw-bold">Product cost</td><td>8,420.50</td></tr>
            <tr class="table-info"><td colspan="3"></td><td colspan="2" class="text-end fw-bold">Ship Cost</td><td>350.00</td></tr>
            <tr class="table-danger"><td colspan="3"></td><td colspan="2" class="text-end fw-bold">Payment fee</td><td>257.13</td></tr>
            <tr class="table-success fw-bold"><td colspan="3"></td><td colspan="2" class="text-end">Total</td><td>9,027.63</td></tr>
          </tbody>
        </table>
      </div>
      <div class="small text-muted">
        <div class="mb-1"><i class="bi bi-check-circle text-success me-1"></i>Los totales (Product cost, Ship Cost, Payment fee, Total) <strong>se leen autom&#225;ticamente</strong> del archivo.</div>
        <div class="mb-1"><i class="bi bi-check-circle text-success me-1"></i>La columna <strong>Code/SKU</strong> vincula con tu inventario existente.</div>
        <div><i class="bi bi-check-circle text-success me-1"></i>La columna <strong>Photo</strong> se reconoce pero es solo referencia visual.</div>
      </div>
    </div>
  </div>
</div>
<script>
const fi=document.getElementById('fi'),dz=document.getElementById('dz');
fi.addEventListener('change',function(){if(this.files[0]){document.getElementById('fn').textContent='&#128196; '+this.files[0].name;document.getElementById('fn').classList.remove('d-none');document.getElementById('btnP').disabled=false;dz.style.background='#f0fdf4';dz.style.borderColor='#16a34a';}});
dz.addEventListener('dragover',e=>{e.preventDefault();dz.style.background='#eff6ff';});
dz.addEventListener('dragleave',()=>{dz.style.background='#f8faff';});
dz.addEventListener('drop',e=>{e.preventDefault();if(e.dataTransfer.files[0]){fi.files=e.dataTransfer.files;fi.dispatchEvent(new Event('change'));}});
</script>

<?php elseif($step==='preview'&&!empty($previewRows)): ?>
<!-- &#9552;&#9552; PASO 2: REVISI&#211;N + PAGO &#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552; -->
<?php
$totalFOB  = array_sum(array_column($previewRows,'total_usd'));
$shipFile  = $resumenFile['ship_cost']   ?? 0;
$feeFile   = $resumenFile['payment_fee'] ?? 0;
$totalFile = $resumenFile['total']       ?? 0;
$comPctDef = ($feeFile>0&&$totalFOB>0) ? round($feeFile/($totalFOB+$shipFile)*100,2) : 3.49;
?>
<form method="POST">
  <input type="hidden" name="action" value="confirm">
  <input type="hidden" name="csrf"   value="<?=Auth::csrfToken()?>">
  <div class="row g-4">

    <!-- Tabla de productos -->
    <div class="col-xl-7">
      <div class="section-card">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h6 class="fw-bold mb-0">
            <i class="bi bi-table me-2 text-primary"></i><?=count($previewRows)?> productos
            <span class="text-muted fw-normal small">&#183; <?=htmlspecialchars($sourceName)?></span>
          </h6>
          <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-secondary" onclick="sa(true)">Todos</button>
            <button type="button" class="btn btn-outline-secondary" onclick="sa(false)">Ninguno</button>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-hover table-sm mb-0" style="font-size:.8rem;">
            <thead class="table-light">
              <tr>
                <th style="width:28px;"><input type="checkbox" id="ca" class="form-check-input" checked onchange="sa(this.checked)"></th>
                <th>Descripci&#243;n</th>
                <th class="text-center">Cant.</th>
                <th class="text-end">USD/u</th>
                <th class="text-end">Total</th>
                <th class="text-center">Inv.</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach($previewRows as $i=>$row):
              $inv=null;
              if($row['codigo']){$s=$db->prepare("SELECT id,codigo,stock_actual FROM elementos WHERE codigo=? AND activo=1 LIMIT 1");$s->execute([$row['codigo']]);$inv=$s->fetch();}
              if(!$inv){$s=$db->prepare("SELECT id,codigo,stock_actual FROM elementos WHERE nombre LIKE ? AND activo=1 LIMIT 1");$s->execute(['%'.substr($row['descripcion'],0,18).'%']);$inv=$s->fetch();}
            ?>
            <tr>
              <td><input type="checkbox" name="sel[]" value="<?=$i?>" class="form-check-input rs" checked></td>
              <td>
                <div class="fw-semibold"><?=htmlspecialchars(mb_substr($row['descripcion'],0,65))?><?=mb_strlen($row['descripcion'])>65?'&#8230;':''?></div>
                <?php if($row['codigo']):?><code class="text-muted" style="font-size:.68rem;"><?=htmlspecialchars($row['codigo'])?></code><?php endif;?>
                <?php if($inv):?>
                  <span class="badge bg-success bg-opacity-10 text-success border" style="font-size:.62rem;border-color:#86efac;" id="inv_badge_<?=$i?>">OK <?=$inv['codigo']?></span>
                <?php else:?>
                  <button type="button" class="badge bg-warning bg-opacity-10 text-warning border btn-nuevo-elem"
                          style="font-size:.62rem;border-color:#fde68a;cursor:pointer;border-radius:4px;padding:2px 5px;"
                          id="inv_badge_<?=$i?>"
                          data-idx="<?=$i?>"
                          data-nombre="<?=htmlspecialchars($row['descripcion'])?>"
                          data-codigo="<?=htmlspecialchars($row['codigo']??'')?>"
                          data-precio="<?=$row['precio_unit_usd']?>"
                          onclick="abrirModalNuevo(this)">
                    &#xff0b; Crear elemento
                  </button>
                <?php endif;?>
              </td>
              <td class="text-center fw-bold"><?=number_format($row['cantidad'])?></td>
              <td class="text-end text-muted">$<?=number_format($row['precio_unit_usd'],3)?></td>
              <td class="text-end fw-bold text-primary rt" data-v="<?=$row['total_usd']?>">$<?=number_format($row['total_usd'],2)?></td>
              <td class="text-center"><?=$inv?"<span class='badge bg-secondary'>{$inv['stock_actual']}</span>":'<span class="text-muted">&#8212;</span>'?></td>
            </tr>
            <?php endforeach;?>
            </tbody>
            <tfoot>
              <tr class="table-light fw-bold">
                <td colspan="4" class="text-end">FOB Seleccionado</td>
                <td class="text-end text-primary" id="tdFob">$<?=number_format($totalFOB,2)?></td>
                <td></td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <!-- Resumen detectado en el archivo -->
      <?php if($totalFile>0||$shipFile>0||$feeFile>0):?>
      <div class="section-card mt-3">
        <h6 class="fw-bold mb-2 text-success">&#128196; Resumen le&#237;do del archivo Excel</h6>
        <div class="row g-2 text-center" style="font-size:.83rem;">
          <div class="col-3"><div class="p-2 rounded" style="background:#f0fdf4;"><div class="text-muted small">Product Cost</div><div class="fw-bold text-success">$<?=number_format($resumenFile['product_cost']??$totalFOB,2)?></div></div></div>
          <div class="col-3"><div class="p-2 rounded" style="background:#fef3c7;"><div class="text-muted small">Ship Cost</div><div class="fw-bold text-warning">$<?=number_format($shipFile,2)?></div></div></div>
          <div class="col-3"><div class="p-2 rounded" style="background:#fef2f2;"><div class="text-muted small">Payment Fee</div><div class="fw-bold text-danger">$<?=number_format($feeFile,2)?></div></div></div>
          <div class="col-3"><div class="p-2 rounded" style="background:#eff6ff;border:2px solid #93c5fd;"><div class="text-muted small">Total</div><div class="fw-bold text-primary">$<?=number_format($totalFile,2)?></div></div></div>
        </div>
      </div>
      <?php endif;?>
    </div>

    <!-- Panel de pago -->
    <div class="col-xl-5">
      <!-- Proveedor y env&#237;o -->
      <div class="section-card">
        <h6 class="fw-bold mb-3 text-primary">&#128722; Proveedor y Env&#237;o</h6>
        <div class="row g-2">
          <div class="col-12">
            <label class="form-label mb-1 small fw-semibold">Proveedor *</label>
            <select name="proveedor_id" class="form-select form-select-sm" required>
              <option value="">&#8212; Seleccionar &#8212;</option>
              <?php foreach($provChina as $p):?>
                <option value="<?=$p['id']?>"><?=htmlspecialchars($p['nombre_comercial']?:$p['nombre'])?></option>
              <?php endforeach;?>
            </select>
          </div>
          <div class="col-7">
            <label class="form-label mb-1 small">Tracking DHL</label>
            <div class="input-group input-group-sm"><span class="input-group-text">&#9992;&#65039;</span><input type="text" name="tracking" class="form-control" placeholder="1234567890"></div>
          </div>
          <div class="col-5">
            <label class="form-label mb-1 small">Peso (kg)</label>
            <div class="input-group input-group-sm"><input type="number" name="peso_kg" class="form-control" step="0.1" min="0" value="0"><span class="input-group-text">kg</span></div>
          </div>
          <div class="col-12">
            <label class="form-label mb-1 small fw-semibold">&#9992;&#65039; Flete / Env&#237;o del proveedor (USD)</label>
            <div class="input-group input-group-sm">
              <span class="input-group-text">$</span>
              <input type="number" name="dhl_usd" id="iDHL" class="form-control" step="0.01" min="0" value="<?=$shipFile?>" oninput="rc()">
              <span class="input-group-text text-muted">USD</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Pago PayPal / Tarjetas -->
      <div class="section-card mt-3">
        <h6 class="fw-bold mb-3 text-warning">&#128179; Pago al Proveedor</h6>
        <div class="row g-2">
          <div class="col-12">
            <label class="form-label mb-1 small fw-semibold">Plataforma de pago</label>
            <select name="metodo_pago" id="selM" class="form-select form-select-sm" onchange="toggleM()">
              <option value="paypal"> PayPal</option>
              <option value="ali_escrow"> AliExpress Escrow</option>
              <option value="tarjeta_directa"> Tarjeta directa</option>
              <option value="transferencia"> Transferencia bancaria</option>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label mb-1 small">Comisi&#243;n %</label>
            <div class="input-group input-group-sm">
              <input type="number" name="com_pct" id="iCP" class="form-control" step="0.01" min="0" value="<?=$comPctDef?>" oninput="rc()">
              <span class="input-group-text">%</span>
            </div>
          </div>
          <div class="col-6">
            <label class="form-label mb-1 small">Comisi&#243;n fija USD</label>
            <div class="input-group input-group-sm">
              <span class="input-group-text">$</span>
              <input type="number" name="com_fija" id="iCF" class="form-control" step="0.01" min="0" value="0" oninput="rc()">
            </div>
          </div>

          <!-- Tarjetas empresa -->
          <div class="col-12 mt-2" id="secT">
            <div class="fw-semibold small mb-2"> Tarjetas empresa ROBOTSchool</div>
            <div class="row g-2">
              <div class="col-7"><input type="text" name="tarjeta1" class="form-control form-control-sm" placeholder="Visa *1234" value="Tarjeta 1"></div>
              <div class="col-5"><div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="number" name="monto1" id="iM1" class="form-control" step="0.01" min="0" placeholder="0.00" oninput="rc()"></div></div>
              <div class="col-7"><input type="text" name="tarjeta2" class="form-control form-control-sm" placeholder="Visa *5678 (si aplica)"></div>
              <div class="col-5"><div class="input-group input-group-sm"><span class="input-group-text">$</span><input type="number" name="monto2" id="iM2" class="form-control" step="0.01" min="0" placeholder="0.00" oninput="rc()"></div></div>
            </div>
          </div>

          <div class="col-12">
            <textarea name="notas" class="form-control form-control-sm" rows="2" placeholder="Instrucciones, notas del pago..."></textarea>
          </div>
        </div>
      </div>

      <!-- Calculadora total a pagar -->
      <div class="mt-3 rounded-3 p-3" style="background:linear-gradient(135deg,#0f172a,#1e2a3a);">
        <div class="fw-bold mb-3" style="color:#93c5fd;font-size:.9rem;">&#128290; TOTAL A ENVIAR POR PAYPAL</div>
        <div style="font-size:.83rem;">
          <div class="d-flex justify-content-between py-1 border-bottom" style="border-color:#1e3a5f!important;">
            <span style="color:#94a3b8;">Productos (FOB seleccionado)</span><span class="fw-semibold text-white" id="cF">$0.00</span>
          </div>
          <div class="d-flex justify-content-between py-1 border-bottom" style="border-color:#1e3a5f!important;">
            <span style="color:#94a3b8;">+ &#9992;&#65039; Flete / Env&#237;o</span><span style="color:#fcd34d;" id="cD">$0.00</span>
          </div>
          <div class="d-flex justify-content-between py-1 border-bottom" style="border-color:#1e3a5f!important;">
            <span style="color:#94a3b8;">+ Comisi&#243;n PayPal / Banco</span><span style="color:#f87171;" id="cC">$0.00</span>
          </div>
          <div class="d-flex justify-content-between mt-2 mb-1">
            <span class="fw-bold text-white" style="font-size:1rem;">TOTAL A PAGAR</span>
            <span class="fw-bold" style="color:#4ade80;font-size:1.3rem;" id="cT">$0.00 USD</span>
          </div>
          <!-- Distribuci&#243;n tarjetas -->
          <div id="dT" style="display:none;background:#0d1f35;border-radius:8px;padding:.5rem .75rem;margin-bottom:.5rem;">
            <div class="small mb-1" style="color:#64748b;">Distribuci&#243;n en tarjetas:</div>
            <div class="d-flex justify-content-between small"><span style="color:#94a3b8;">Tarjeta 1</span><span style="color:#67e8f9;" id="ct1">$0.00</span></div>
            <div class="d-flex justify-content-between small"><span style="color:#94a3b8;">Tarjeta 2</span><span style="color:#67e8f9;" id="ct2">$0.00</span></div>
            <div id="aDif" style="display:none;background:#450a0a;border-radius:4px;padding:.25rem .5rem;margin-top:.25rem;font-size:.72rem;color:#fca5a5;">
              ! La suma de tarjetas no coincide con el total a pagar
            </div>
          </div>
          <div class="rounded p-2" style="background:#0d1f35;">
            <div class="d-flex justify-content-between align-items-center">
              <span style="color:#64748b;font-size:.76rem;">~ Equivalente COP (TRM <input type="number" name="trm" id="iTRM" style="background:transparent;border:none;border-bottom:1px solid #334155;width:65px;color:#94a3b8;font-size:.76rem;text-align:center;" value="<?=$trm_def?>" oninput="rc()">)</span>
              <span class="fw-bold" style="color:#34d399;font-size:.9rem;" id="cCOP">$ 0 COP</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Liquidaci&#243;n aduana -->
      <div class="section-card mt-3">
        <h6 class="fw-semibold mb-2 small text-muted"> LIQUIDACI&#211;N EN ADUANA (para despu&#233;s)</h6>
        <div class="row g-2">
          <div class="col-6"><label class="form-label mb-1 small">Arancel %</label>
            <div class="input-group input-group-sm"><input type="number" name="arancel_pct" class="form-control" step="0.5" value="<?=$aran_def?>"><span class="input-group-text">%</span></div></div>
          <div class="col-6"><label class="form-label mb-1 small">IVA %</label>
            <div class="input-group input-group-sm"><input type="number" name="iva_pct" class="form-control" step="0.5" value="<?=$iva_def?>"><span class="input-group-text">%</span></div></div>
        </div>
      </div>

      <div class="d-grid gap-2 mt-3">
        <button type="submit" class="btn btn-success btn-lg fw-bold">
          <i class="bi bi-check-circle me-2"></i>Crear Pedido en el Sistema
        </button>
        <a href="?step=upload" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Cambiar archivo</a>
      </div>
    </div>
  </div><!-- /row -->
</form>

<script>
const rv=<?=json_encode(array_column($previewRows,'total_usd'))?>;
function sa(v){document.querySelectorAll('.rs').forEach(c=>c.checked=v);document.getElementById('ca').checked=v;rc();}
function g(id){return parseFloat(document.getElementById(id)?.value)||0;}
function fmt(n){return '$'+n.toFixed(2);}

function rc(){
  let fob=0;
  document.querySelectorAll('.rs').forEach((c,i)=>{if(c.checked)fob+=rv[i]||0;});
  document.getElementById('tdFob').textContent=fmt(fob);

  const dhl=g('iDHL'), cp=g('iCP'), cf=g('iCF'), trm=g('iTRM');
  const com=(fob+dhl)*cp/100+cf;
  const tot=fob+dhl+com;

  document.getElementById('cF').textContent=fmt(fob);
  document.getElementById('cD').textContent=fmt(dhl);
  document.getElementById('cC').textContent=fmt(com);
  document.getElementById('cT').textContent=fmt(tot)+' USD';
  document.getElementById('cCOP').textContent='$ '+(tot*trm).toLocaleString('es-CO',{maximumFractionDigits:0})+' COP';

  const m1=g('iM1'),m2=g('iM2');
  if(m1>0||m2>0){
    document.getElementById('dT').style.display='';
    document.getElementById('ct1').textContent=fmt(m1);
    document.getElementById('ct2').textContent=fmt(m2);
    document.getElementById('aDif').style.display=Math.abs(m1+m2-tot)>0.05?'':'none';
  } else document.getElementById('dT').style.display='none';
}

function toggleM(){
  const v=document.getElementById('selM').value;
  document.getElementById('secT').style.display=(v==='transferencia')?'none':'';
  const pm={paypal:3.49,ali_escrow:0,tarjeta_directa:0,transferencia:0};
  document.getElementById('iCP').value=pm[v]??3.49;
  rc();
}

document.querySelectorAll('.rs').forEach(c=>c.addEventListener('change',rc));
rc();
</script>

<!-- &#9552;&#9552; MODAL CREAR ELEMENTO R&#193;PIDO &#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552;&#9552; -->
<div class="modal fade" id="modalNuevoElem" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header" style="background:linear-gradient(135deg,#1e2a3a,#243447);">
        <h5 class="modal-title text-white fw-bold">&#128187; Crear Elemento Nuevo</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-info py-2 mb-3" style="font-size:.83rem;">
          <i class="bi bi-info-circle me-2"></i>Este elemento se crear&#225; en el inventario y quedar&#225; vinculado al pedido autom&#225;ticamente.
        </div>
        <div class="row g-3">
          <!-- Nombre -->
          <div class="col-12">
            <label class="form-label fw-semibold mb-1">Nombre / Descripci&#243;n *</label>
            <input type="text" id="ne_nombre" class="form-control" placeholder="Ej: M&#243;dulo ESP32 WiFi+BT">
          </div>
          <!-- Categor&#237;a y C&#243;digo -->
          <div class="col-md-5">
            <label class="form-label fw-semibold mb-1">Categor&#237;a *</label>
            <select id="ne_cat" class="form-select" onchange="generarCodigoPreview()">
              <option value="">&#8212; Seleccionar &#8212;</option>
              <?php foreach($categorias as $cat):?>
                <option value="<?=$cat['id']?>" data-prefijo="<?=$cat['prefijo']?>"><?=htmlspecialchars($cat['nombre'])?></option>
              <?php endforeach;?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold mb-1">C&#243;digo SKU proveedor</label>
            <input type="text" id="ne_codigo_prov" class="form-control" placeholder="SKU del proveedor">
          </div>
          <div class="col-md-3">
            <label class="form-label fw-semibold mb-1">Unidad</label>
            <select id="ne_unidad" class="form-select">
              <option value="unidad">Unidad</option>
              <option value="par">Par</option>
              <option value="metro">Metro</option>
              <option value="rollo">Rollo</option>
              <option value="set">Set</option>
              <option value="bolsa">Bolsa</option>
            </select>
          </div>
          <!-- Costo y proveedor -->
          <div class="col-md-4">
            <label class="form-label fw-semibold mb-1">Precio unit. USD</label>
            <div class="input-group">
              <span class="input-group-text">$</span>
              <input type="number" id="ne_precio_usd" class="form-control" step="0.001" min="0">
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold mb-1">Costo COP <span class="text-muted small">(TRM: <span id="ne_trm_val"><?=$trm_def?></span>)</span></label>
            <div class="input-group">
              <span class="input-group-text">$</span>
              <input type="number" id="ne_costo_cop" class="form-control" step="1" min="0">
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold mb-1">Stock inicial</label>
            <input type="number" id="ne_stock" class="form-control" min="0" value="0">
          </div>
          <!-- Proveedor -->
          <div class="col-md-6">
            <label class="form-label fw-semibold mb-1">Proveedor</label>
            <select id="ne_prov" class="form-select">
              <option value="">&#8212; Sin proveedor &#8212;</option>
              <?php foreach($provChina as $p):?>
                <option value="<?=$p['id']?>"><?=htmlspecialchars($p['nombre_comercial']?:$p['nombre'])?></option>
              <?php endforeach;?>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold mb-1">Ubicaci&#243;n en bodega</label>
            <input type="text" id="ne_ubicacion" class="form-control" placeholder="Ej: Estante A &#183; Caj&#243;n 3">
          </div>
          <!-- Descripci&#243;n t&#233;cnica -->
          <div class="col-12">
            <label class="form-label fw-semibold mb-1">Descripci&#243;n t&#233;cnica <span class="text-muted small">(opcional)</span></label>
            <textarea id="ne_descripcion" class="form-control" rows="2" placeholder="Specs t&#233;cnicas, voltaje, dimensiones..."></textarea>
          </div>
        </div>

        <!-- C&#243;digo que se generar&#225; -->
        <div class="mt-3 p-2 rounded d-flex align-items-center gap-2" style="background:#f0fdf4;border:1px solid #86efac;">
          <i class="bi bi-tag text-success"></i>
          <span class="text-muted small">C&#243;digo que se asignar&#225;:</span>
          <code class="fw-bold text-success" id="ne_codigo_preview">&#8212; selecciona categor&#237;a &#8212;</code>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-success fw-bold" onclick="guardarElemento()">
          <i class="bi bi-check-circle me-2"></i>Crear Elemento y Vincular
        </button>
      </div>
      <!-- Spinner de guardado -->
      <div id="ne_saving" class="modal-footer justify-content-center d-none">
        <div class="spinner-border text-success me-2" role="status"></div>
        <span>Guardando elemento...</span>
      </div>
    </div>
  </div>
</div>

<script>
let _neIdx = null; // &#237;ndice de la fila en previewRows
const _trm  = <?= $trm_def ?>;

function abrirModalNuevo(btn) {
  _neIdx = btn.dataset.idx;
  const nombre = btn.dataset.nombre;
  const codigo = btn.dataset.codigo;
  const precio = parseFloat(btn.dataset.precio) || 0;

  document.getElementById('ne_nombre').value      = nombre;
  document.getElementById('ne_codigo_prov').value  = codigo;
  document.getElementById('ne_precio_usd').value   = precio;
  document.getElementById('ne_costo_cop').value    = Math.round(precio * _trm);
  document.getElementById('ne_stock').value        = 0;
  document.getElementById('ne_descripcion').value  = '';
  document.getElementById('ne_ubicacion').value    = '';
  document.getElementById('ne_cat').value          = '';
  document.getElementById('ne_codigo_preview').textContent = '&#8212; selecciona categor&#237;a &#8212;';

  // Pre-seleccionar proveedor del pedido si hay uno seleccionado
  const provSel = document.querySelector('[name="proveedor_id"]')?.value;
  if (provSel) document.getElementById('ne_prov').value = provSel;

  new bootstrap.Modal(document.getElementById('modalNuevoElem')).show();
}

// Sincronizar precio USD &#8594; COP
document.getElementById('ne_precio_usd')?.addEventListener('input', function() {
  document.getElementById('ne_costo_cop').value = Math.round(parseFloat(this.value||0) * _trm);
});

function generarCodigoPreview() {
  const sel = document.getElementById('ne_cat');
  const opt = sel.options[sel.selectedIndex];
  const pfx = opt?.dataset?.prefijo || '???';
  document.getElementById('ne_codigo_preview').textContent = pfx + '-XXX (se asigna autom&#225;ticamente)';
}

async function guardarElemento() {
  const nombre = document.getElementById('ne_nombre').value.trim();
  const catId  = document.getElementById('ne_cat').value;
  if (!nombre) { alert('Ingresa el nombre del elemento.'); return; }
  if (!catId)  { alert('Selecciona una categor&#237;a.'); return; }

  // Mostrar spinner
  document.getElementById('ne_saving').classList.remove('d-none');
  document.querySelector('#modalNuevoElem .modal-footer').classList.add('d-none');

  try {
    const resp = await fetch('<?=APP_URL?>/api/crear_elemento.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        csrf:        '<?=Auth::csrfToken()?>',
        nombre:      nombre,
        categoria_id: catId,
        codigo_proveedor: document.getElementById('ne_codigo_prov').value.trim(),
        unidad_medida:    document.getElementById('ne_unidad').value,
        precio_usd:       parseFloat(document.getElementById('ne_precio_usd').value)||0,
        costo_real_cop:   parseFloat(document.getElementById('ne_costo_cop').value)||0,
        stock_inicial:    parseInt(document.getElementById('ne_stock').value)||0,
        proveedor_id:     document.getElementById('ne_prov').value||null,
        ubicacion:        document.getElementById('ne_ubicacion').value.trim(),
        descripcion:      document.getElementById('ne_descripcion').value.trim(),
      })
    });
    const data = await resp.json();

    if (data.ok) {
      // Actualizar badge en la fila de la tabla
      const badge = document.getElementById('inv_badge_' + _neIdx);
      if (badge) {
        badge.outerHTML = `<span class="badge bg-success bg-opacity-10 text-success border"
          style="font-size:.62rem;border-color:#86efac;" id="inv_badge_${_neIdx}">
          OK ${data.codigo}</span>`;
      }
      // Cerrar modal y mostrar toast
      bootstrap.Modal.getInstance(document.getElementById('modalNuevoElem')).hide();
      mostrarToast('&#9989; Elemento <strong>' + data.codigo + '</strong> creado correctamente.', 'success');
    } else {
      alert('Error: ' + (data.error || 'No se pudo crear el elemento.'));
      document.getElementById('ne_saving').classList.add('d-none');
      document.querySelector('#modalNuevoElem .modal-footer').classList.remove('d-none');
    }
  } catch(e) {
    alert('Error de conexi&#243;n: ' + e.message);
    document.getElementById('ne_saving').classList.add('d-none');
    document.querySelector('#modalNuevoElem .modal-footer').classList.remove('d-none');
  }
}

function mostrarToast(msg, tipo) {
  const t = document.createElement('div');
  t.className = `toast align-items-center text-bg-${tipo} border-0 show`;
  t.style.cssText = 'position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;min-width:280px;';
  t.innerHTML = `<div class="d-flex"><div class="toast-body">${msg}</div>
    <button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="this.closest('.toast').remove()"></button></div>`;
  document.body.appendChild(t);
  setTimeout(() => t.remove(), 4000);
}
</script>

<?php endif;?>
<?php require_once dirname(__DIR__,2).'/includes/footer.php';?>
