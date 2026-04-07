<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();

$db        = Database::get();
$pageTitle = 'Constructor de Kits';
$activeMenu= 'kits';
$error     = '';

$kitId   = (int)($_GET['kit_id']   ?? 0);
$cursoId = (int)($_GET['curso_id'] ?? 0);

// Detección de columna grado en kits
$colGradoExists = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='kits' AND COLUMN_NAME='grado'")->fetchColumn();
$colGradosColExists = $db->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='colegios' AND COLUMN_NAME='grados'")->fetchColumn();

$ALL_GRADOS_LABELS = [
    'transicion' => 'Transición',
    '1'  => '1°',  '2'  => '2°',  '3'  => '3°',  '4'  => '4°',  '5'  => '5°',
    '6'  => '6°',  '7'  => '7°',  '8'  => '8°',  '9'  => '9°',
    '10' => '10°', '11' => '11°',
];

// ── Guardar kit ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='guardar_kit') {
    if (!Auth::csrfVerify($_POST['csrf'] ?? '')) die('CSRF');
    try {
        $db->beginTransaction();

        $colegioId = null;
        $grado     = null;

        if (!$kitId) {
            $nombre    = trim($_POST['kit_nombre'] ?? 'Kit sin nombre');
            $colegioId = (int)($_POST['colegio_id'] ?? 0) ?: null;
            $grado     = trim($_POST['grado'] ?? '') ?: null;
            $desc      = trim($_POST['descripcion'] ?? '');
            $cajId     = (int)($_POST['tipo_caja_id'] ?? 0) ?: null;

            $seq    = $db->query("SELECT COUNT(*)+1 FROM kits FOR UPDATE")->fetchColumn();
            $codigo = 'KIT-' . str_pad($seq, 3, '0', STR_PAD_LEFT);

            $insertCols   = ['codigo','nombre','tipo','descripcion','colegio_id','tipo_caja_id','costo_cop','activo','created_by'];
            $insertVals   = ['?','?',"'colegio'",'?','?','?','0','1','?'];
            $insertParams = [$codigo,$nombre,$desc,$colegioId,$cajId,Auth::user()['id']];
            if ($colGradoExists) {
                $insertCols[] = 'grado';
                $insertVals[] = '?';
                $insertParams[] = $grado;
            }
            $db->prepare('INSERT INTO kits('.implode(',',$insertCols).') VALUES('.implode(',',$insertVals).')')
               ->execute($insertParams);
            $kitId = $db->lastInsertId();
        } else {
            $db->prepare("DELETE FROM kit_elementos  WHERE kit_id=?")->execute([$kitId]);
            $db->prepare("DELETE FROM kit_prototipos WHERE kit_id=?")->execute([$kitId]);
        }

        $costoTotal = 0;

        $elems = $_POST['elem_id']   ?? [];
        $eCant = $_POST['elem_cant'] ?? [];
        foreach ($elems as $k => $eId) {
            $eId  = (int)$eId;
            $cant = max(1,(int)($eCant[$k] ?? 1));
            $costo = (float)$db->query("SELECT costo_real_cop FROM elementos WHERE id=$eId")->fetchColumn();
            $db->prepare("INSERT INTO kit_elementos(kit_id,elemento_id,cantidad) VALUES(?,?,?)")->execute([$kitId,$eId,$cant]);
            $costoTotal += $cant * $costo;
        }

        $protos = $_POST['proto_id']   ?? [];
        $pCant  = $_POST['proto_cant'] ?? [];
        foreach ($protos as $k => $pId) {
            $pId  = (int)$pId;
            $cant = max(1,(int)($pCant[$k] ?? 1));
            $costo = (float)$db->query("SELECT costo_total_cop FROM prototipos WHERE id=$pId")->fetchColumn();
            $db->prepare("INSERT INTO kit_prototipos(kit_id,prototipo_id,cantidad) VALUES(?,?,?)")->execute([$kitId,$pId,$cant]);
            $costoTotal += $cant * $costo;
        }

        $db->prepare("UPDATE kits SET costo_cop=?,incluye_prototipo=? WHERE id=?")
           ->execute([$costoTotal, count($protos)>0?1:0, $kitId]);

        if ($cursoId) {
            $db->prepare("UPDATE cursos SET kit_id=? WHERE id=?")->execute([$kitId,$cursoId]);
        } elseif ($colegioId && $grado) {
            $stmt = $db->prepare("UPDATE cursos SET kit_id=? WHERE colegio_id=? AND grado=? AND activo=1");
            $stmt->execute([$kitId, $colegioId, $grado]);
            // Si no había curso activo con ese grado, crearlo y vincularlo
            if ($stmt->rowCount() === 0) {
                $nivelMap = [
                    'transicion'=>'preescolar','1'=>'primaria','2'=>'primaria','3'=>'primaria','4'=>'primaria','5'=>'primaria',
                    '6'=>'secundaria','7'=>'secundaria','8'=>'secundaria','9'=>'secundaria','10'=>'media','11'=>'media',
                ];
                $labelMap = [
                    'transicion'=>'Transición','1'=>'1°','2'=>'2°','3'=>'3°','4'=>'4°','5'=>'5°',
                    '6'=>'6°','7'=>'7°','8'=>'8°','9'=>'9°','10'=>'10°','11'=>'11°',
                ];
                $nombre = $labelMap[$grado] ?? "Grado $grado";
                $nivel  = $nivelMap[$grado] ?? 'otro';
                $db->prepare("INSERT INTO cursos (colegio_id, nombre, grado, nivel, activo, anio, kit_id) VALUES (?,?,?,?,1,?,?)")
                   ->execute([$colegioId, $nombre, $grado, $nivel, (int)date('Y'), $kitId]);
            }
        }

        $db->commit();

        if ($cursoId) {
            $colId = $db->query("SELECT colegio_id FROM cursos WHERE id=$cursoId")->fetchColumn();
            header('Location: '.APP_URL."/modules/colegios/ver.php?id=$colId&ok=kit_asignado");
        } else {
            header('Location: '.APP_URL."/modules/kits/constructor.php?kit_id=$kitId&ok=1");
        }
        exit;

    } catch (Exception $e) {
        $db->rollBack();
        $error = $e->getMessage();
    }
}

// ── Datos ────────────────────────────────────────────────────
$kit   = $kitId   ? $db->query("SELECT * FROM kits WHERE id=$kitId")->fetch() : null;
$curso = $cursoId ? $db->query("SELECT cu.*,c.nombre AS colegio,c.id AS colegio_id FROM cursos cu JOIN colegios c ON c.id=cu.colegio_id WHERE cu.id=$cursoId")->fetch() : null;

$kitElems  = $kitId ? $db->query("SELECT elemento_id,cantidad  FROM kit_elementos  WHERE kit_id=$kitId")->fetchAll(PDO::FETCH_KEY_PAIR) : [];
$kitProtos = $kitId ? $db->query("SELECT prototipo_id,cantidad FROM kit_prototipos WHERE kit_id=$kitId")->fetchAll(PDO::FETCH_KEY_PAIR) : [];

// Elementos agrupados por categoría
$elementos = $db->query("
    SELECT e.id,e.codigo,e.nombre,e.foto,e.stock_actual,e.costo_real_cop,
           c.nombre AS cat,c.color AS cat_color,c.prefijo
    FROM elementos e
    JOIN categorias c ON c.id=e.categoria_id
    WHERE e.activo=1
    ORDER BY c.nombre,e.nombre
")->fetchAll();

$prototipos = $db->query("
    SELECT id,codigo,nombre,foto,tipo_fabricacion,costo_total_cop
    FROM prototipos WHERE activo=1 ORDER BY nombre
")->fetchAll();

$colegios  = $db->query("SELECT id,nombre FROM colegios WHERE activo=1 ORDER BY nombre")->fetchAll();
$tiposCaja = $db->query("SELECT id,nombre FROM tipos_caja WHERE activo=1 ORDER BY nombre")->fetchAll();

// Grados por colegio para JS (de cursos + colegios.grados si existe)
$gradosByColegio = [];
foreach ($db->query("SELECT DISTINCT colegio_id, grado FROM cursos WHERE activo=1 AND grado IS NOT NULL ORDER BY colegio_id, grado+0, grado")->fetchAll() as $r) {
    $gradosByColegio[(int)$r['colegio_id']][] = $r['grado'];
}
if ($colGradosColExists) {
    foreach ($db->query("SELECT id, grados FROM colegios WHERE activo=1 AND grados IS NOT NULL AND grados != ''")->fetchAll() as $row) {
        $extra = array_filter(array_map('trim', explode(',', $row['grados'])));
        if ($extra) {
            $existing = $gradosByColegio[(int)$row['id']] ?? [];
            $gradosByColegio[(int)$row['id']] = array_values(array_unique(array_merge($existing, $extra)));
        }
    }
}

$porCat = [];
foreach ($elementos as $e) $porCat[$e['cat']][] = $e;

// Calcular costo actual del kit
$costoActual = 0;
foreach ($kitElems as $eId => $cant) {
    $c = $db->query("SELECT costo_real_cop FROM elementos WHERE id=$eId")->fetchColumn();
    $costoActual += $cant * (float)$c;
}
foreach ($kitProtos as $pId => $cant) {
    $c = $db->query("SELECT costo_total_cop FROM prototipos WHERE id=$pId")->fetchColumn();
    $costoActual += $cant * (float)$c;
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<style>
.item-card .card{border-radius:10px;cursor:pointer;transition:all .15s;border:2px solid transparent}
.item-card .card:hover{box-shadow:0 4px 12px rgba(0,0,0,.12);transform:translateY(-2px);border-color:#93c5fd}
.item-card .card::after{content:'+ Agregar';position:absolute;bottom:4px;right:4px;background:#185FA5;color:#fff;font-size:.6rem;padding:.1rem .35rem;border-radius:4px;opacity:0;transition:.15s}
.item-card .card:hover::after{opacity:1}
.item-card{position:relative}
.item-card .card.seleccionado{border:2px solid #185FA5!important;background:#f0f7ff;box-shadow:0 0 0 3px #bfdbfe}
.item-card .card.seleccionado::after{content:'&#10003; Agregado';background:#16a34a;opacity:1}
.item-card .card.seleccionado.proto{border-color:#dc2626!important;background:#fff5f5;box-shadow:0 0 0 3px #fecaca}
.item-card .card.seleccionado.proto::after{background:#dc2626}
.qty-control{display:none}
.item-card .card.seleccionado .qty-control{display:block}
.resumen-item{font-size:.8rem;padding:.4rem .5rem;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center;gap:.5rem}
.section-card{background:#fff;border-radius:14px;border:1px solid #e2e8f0;padding:1rem 1.2rem}
.cat-header{background:#f8fafc;border-left:4px solid var(--cc);padding:.4rem .75rem;font-weight:700;font-size:.82rem;margin-bottom:.5rem;border-radius:0 6px 6px 0}
</style>

<div class="d-flex align-items-center gap-2 mb-3">
  <a href="<?= APP_URL ?>/modules/kits/" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i></a>
  <div class="flex-grow-1">
    <h4 class="fw-bold mb-0">&#x1F6E0;&#xFE0F; Constructor de Kits</h4>
    <?php if ($curso): ?>
      <p class="text-muted small mb-0">Kit para <strong><?= htmlspecialchars($curso['nombre']) ?></strong> &middot; <?= htmlspecialchars($curso['colegio']) ?></p>
    <?php elseif ($kit): ?>
      <p class="text-muted small mb-0">Editando <strong><?= htmlspecialchars($kit['nombre']) ?></strong> &middot; <code><?= $kit['codigo'] ?></code></p>
    <?php else: ?>
      <p class="text-muted small mb-0">Selecciona componentes del inventario</p>
    <?php endif; ?>
  </div>
</div>

<?php if (!empty($_GET['ok'])): ?><div class="alert alert-success py-2">Kit guardado correctamente.</div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger py-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<form method="POST" id="fKit">
<input type="hidden" name="action" value="guardar_kit">
<input type="hidden" name="csrf"   value="<?= Auth::csrfToken() ?>">
<?php if ($kitId):   ?><input type="hidden" name="kit_id_existente" value="<?= $kitId ?>"><?php endif; ?>
<?php if ($cursoId): ?><input type="hidden" name="curso_id"         value="<?= $cursoId ?>"><?php endif; ?>

<div class="row g-3">

  <!-- ══ COLUMNA IZQUIERDA: Catálogo ══ -->
  <div class="col-lg-8">

    <!-- Instrucción + Buscador -->
    <div class="alert alert-info py-2 mb-2 d-flex align-items-center gap-2" style="font-size:.82rem">
      <i class="bi bi-hand-index-thumb fs-5"></i>
      <span><strong>Haz clic</strong> en cualquier producto para agregarlo al kit. Clic de nuevo para quitarlo.</span>
    </div>
    <div class="mb-2">
      <input type="text" id="buscador" class="form-control" placeholder="&#128269; Buscar por nombre, c&oacute;digo o categor&iacute;a...">
    </div>

    <!-- Prototipos -->
    <?php if (!empty($prototipos)): ?>
    <div class="mb-3">
      <div class="cat-header" style="--cc:#dc2626">
        Prototipos y Cortes &mdash; <span class="fw-normal"><?= count($prototipos) ?> disponibles</span>
      </div>
      <div class="row g-2">
      <?php foreach ($prototipos as $p):
        $enKit = $kitProtos[$p['id']] ?? 0;
        $tipos = implode(', ', array_map('ucfirst', array_map(function($t){ return str_replace('_',' ',$t); }, explode(',',$p['tipo_fabricacion']??''))));
      ?>
      <div class="col-md-4 col-6 item-card" data-nombre="<?= strtolower($p['nombre']) ?>" data-tipo="proto">
        <div class="card h-100 <?= $enKit?'seleccionado proto':'' ?>"
             onclick="toggleItem(this,'proto',<?= $p['id'] ?>)">
          <div class="card-body p-2">
            <?php if ($p['foto']): ?>
              <img src="<?= htmlspecialchars(fotoUrl($p['foto'])) ?>" class="w-100 rounded mb-1" style="height:60px;object-fit:cover" alt=""
                   onerror="this.outerHTML='<div class=\'rounded mb-1 d-flex align-items-center justify-content-center\' style=\'height:55px;background:#fff0f0;color:#dc2626;font-size:1.5rem\'>&#x2702;</div>'">
            <?php else: ?>
              <div class="rounded mb-1 d-flex align-items-center justify-content-center" style="height:55px;background:#fff0f0;color:#dc2626;font-size:1.5rem">&#x2702;</div>
            <?php endif; ?>
            <div class="fw-semibold" style="font-size:.73rem"><?= htmlspecialchars(mb_substr($p['nombre'],0,38)) ?></div>
            <div class="text-muted" style="font-size:.65rem"><?= htmlspecialchars($tipos) ?></div>
            <?php if ($p['costo_total_cop']>0): ?>
              <div class="text-success fw-bold" style="font-size:.72rem"><?= cop($p['costo_total_cop']) ?></div>
            <?php endif; ?>
            <!-- Control cantidad -->
            <div class="qty-control mt-1" onclick="event.stopPropagation()">
              <div class="input-group input-group-sm">
                <button type="button" class="btn btn-outline-danger btn-sm py-0 px-1" onclick="cambiarCant(this,-1)">&minus;</button>
                <input type="number" class="form-control text-center py-0 fw-bold qty-input" style="font-size:.8rem" min="1" value="<?= $enKit ?: 1 ?>">
                <button type="button" class="btn btn-outline-danger btn-sm py-0 px-1" onclick="cambiarCant(this,1)">+</button>
              </div>
              <input type="hidden" <?= $enKit ? 'name="proto_id[]"'   : '' ?> class="hid-id"   value="<?= $p['id'] ?>">
              <input type="hidden" <?= $enKit ? 'name="proto_cant[]"' : '' ?> class="hid-cant" value="<?= $enKit ?: 1 ?>">
            </div>
            <div class="mt-1 <?= $enKit?'':'d-none' ?> badge-enkit">
              <span class="badge bg-danger w-100" style="font-size:.65rem">&#10003; En el kit</span>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Elementos por categoría -->
    <?php foreach ($porCat as $catNombre => $elems):
      $catColor = $elems[0]['cat_color'] ?? '#185FA5';
    ?>
    <div class="mb-3 cat-bloque" data-cat="<?= strtolower(preg_replace('/\s+/','-',$catNombre)) ?>">
      <div class="cat-header" style="--cc:<?= $catColor ?>">
        <?= htmlspecialchars($catNombre) ?> &mdash; <span class="fw-normal"><?= count($elems) ?> elementos</span>
      </div>
      <div class="row g-2">
      <?php foreach ($elems as $e):
        $enKit = $kitElems[$e['id']] ?? 0;
        $semaf = $e['stock_actual']<=0 ? 'danger' : ($e['stock_actual']<=10 ? 'warning' : 'success');
      ?>
      <div class="col-md-3 col-4 item-card" data-nombre="<?= strtolower($e['nombre'].' '.$e['codigo'].' '.$catNombre) ?>" data-tipo="elem">
        <div class="card h-100 <?= $enKit?'seleccionado':'' ?>"
             onclick="toggleItem(this,'elem',<?= $e['id'] ?>)">
          <div class="card-body p-2">
            <?php if ($e['foto']): ?>
              <img src="<?= htmlspecialchars(fotoUrl($e['foto'])) ?>" class="w-100 rounded mb-1" style="height:55px;object-fit:cover" alt=""
                   onerror="this.outerHTML='<div class=\'rounded mb-1 d-flex align-items-center justify-content-center\' style=\'height:48px;background:#f0f4ff;color:<?= $catColor ?>;font-size:1.2rem\'><i class=\'bi bi-cpu\'></i></div>'">
            <?php else: ?>
              <div class="rounded mb-1 d-flex align-items-center justify-content-center" style="height:48px;background:#f0f4ff;color:<?= $catColor ?>;font-size:1.2rem"><i class="bi bi-cpu"></i></div>
            <?php endif; ?>
            <div class="fw-semibold" style="font-size:.7rem;line-height:1.2"><?= htmlspecialchars(mb_substr($e['nombre'],0,32)) ?></div>
            <div class="d-flex justify-content-between mt-1">
              <code style="font-size:.6rem;color:#94a3b8"><?= htmlspecialchars($e['codigo']) ?></code>
              <span class="badge bg-<?= $semaf ?> bg-opacity-20 text-<?= $semaf ?>" style="font-size:.6rem"><?= $e['stock_actual'] ?></span>
            </div>
            <?php if ($e['costo_real_cop']>0): ?>
              <div class="text-success fw-bold" style="font-size:.7rem"><?= cop($e['costo_real_cop']) ?></div>
            <?php endif; ?>
            <!-- Control cantidad -->
            <div class="qty-control mt-1" onclick="event.stopPropagation()">
              <div class="input-group input-group-sm">
                <button type="button" class="btn btn-outline-primary btn-sm py-0 px-1" onclick="cambiarCant(this,-1)">&minus;</button>
                <input type="number" class="form-control text-center py-0 fw-bold qty-input" style="font-size:.78rem" min="1" value="<?= $enKit ?: 1 ?>">
                <button type="button" class="btn btn-outline-primary btn-sm py-0 px-1" onclick="cambiarCant(this,1)">+</button>
              </div>
              <input type="hidden" <?= $enKit ? 'name="elem_id[]"'   : '' ?> class="hid-id"   value="<?= $e['id'] ?>">
              <input type="hidden" <?= $enKit ? 'name="elem_cant[]"' : '' ?> class="hid-cant" value="<?= $enKit ?: 1 ?>">
            </div>
            <div class="mt-1 <?= $enKit?'':'d-none' ?> badge-enkit">
              <span class="badge bg-primary w-100" style="font-size:.63rem">&#10003; En el kit</span>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>

  </div>

  <!-- ══ COLUMNA DERECHA: Resumen ══ -->
  <div class="col-lg-4">
    <div style="position:sticky;top:80px">

      <!-- Datos del kit (solo si es nuevo) -->
      <?php if (!$kit): ?>
      <div class="section-card mb-3">
        <h6 class="fw-bold mb-2"><i class="bi bi-bag-plus me-1 text-primary"></i>Datos del Kit</h6>
        <div class="row g-2">
          <div class="col-12">
            <input type="text" name="kit_nombre" class="form-control form-control-sm fw-bold" required
                   placeholder="Nombre del kit *"
                   value="<?= $curso ? 'Kit '.$curso['nombre'].' - '.$curso['colegio'] : '' ?>">
          </div>
          <div class="col-12">
            <select name="colegio_id" id="cons-sel-colegio" class="form-select form-select-sm"
                    onchange="consOnColegioChange(this.value)">
              <option value="">Sin colegio</option>
              <?php foreach ($colegios as $c): ?>
                <option value="<?= $c['id'] ?>" <?= ($curso && $curso['colegio_id']==$c['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($c['nombre']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6" id="cons-blq-grado" style="<?= ($curso && $curso['colegio_id']) ? '' : 'display:none' ?>">
            <select name="grado" id="cons-sel-grado" class="form-select form-select-sm">
              <option value="">— Grado —</option>
              <?php foreach ($ALL_GRADOS_LABELS as $val => $lbl): ?>
              <option value="<?= $val ?>"><?= $lbl ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6">
            <select name="tipo_caja_id" class="form-select form-select-sm">
              <option value="">Sin caja</option>
              <?php foreach ($tiposCaja as $tc): ?>
                <option value="<?= $tc['id'] ?>"><?= htmlspecialchars($tc['nombre']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <textarea name="descripcion" class="form-control form-control-sm" rows="2" placeholder="Descripci&oacute;n..."></textarea>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Resumen del kit -->
      <div class="section-card">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <h6 class="fw-bold mb-0"><i class="bi bi-bag-check me-1 text-success"></i>Kit armado</h6>
          <span class="badge bg-success" id="badge-total">0 items</span>
        </div>

        <div id="lista-resumen" style="max-height:320px;overflow-y:auto;min-height:80px">
          <div id="msg-vacio" class="text-center text-muted py-3 small">
            <div style="font-size:1.8rem">&#x1F4E6;</div>
            Clic en los productos para agregarlos
          </div>
        </div>

        <hr class="my-2">

        <div class="row g-1 text-center mb-2" style="font-size:.77rem">
          <div class="col-4"><div class="text-muted">Elementos</div><div class="fw-bold text-primary" id="cnt-elem">0</div></div>
          <div class="col-4"><div class="text-muted">Prototipos</div><div class="fw-bold text-danger" id="cnt-proto">0</div></div>
          <div class="col-4"><div class="text-muted">Unidades</div><div class="fw-bold" id="cnt-uds">0</div></div>
        </div>

        <div class="rounded text-center p-2 mb-3" style="background:#f0fdf4;border:1.5px solid #86efac">
          <div class="text-muted" style="font-size:.72rem">COSTO TOTAL</div>
          <div class="fw-bold text-success" style="font-size:1.2rem" id="costo-total">
            $<?= number_format($costoActual, 0, ',', '.') ?> COP
          </div>
        </div>

        <button type="submit" class="btn btn-primary w-100 fw-bold" id="btn-guardar" <?= (empty($kitElems)&&empty($kitProtos))?'disabled':'' ?>>
          <i class="bi bi-save me-1"></i>
          <?= $kit ? 'Guardar Cambios' : ($curso ? 'Asignar al Curso' : 'Crear Kit') ?>
        </button>
        <button type="button" class="btn btn-warning w-100 fw-bold mt-2" id="btn-sticker"
                onclick="verSticker()" <?= (empty($kitElems)&&empty($kitProtos))?'disabled':'' ?>>
          <i class="bi bi-printer me-1"></i>Sticker para la caja
        </button>
        <a href="<?= APP_URL ?>/modules/kits/" class="btn btn-outline-secondary w-100 btn-sm mt-2">Cancelar</a>
      </div>

    </div>
  </div>
</div>
</form>

<script>
// Catálogo JS construido desde PHP
var catalogo = {
<?php
foreach ($elementos as $e) {
    $nom = addslashes($e['nombre']);
    echo "  'e{$e['id']}': {tipo:'elem', nombre:'$nom', costo:{$e['costo_real_cop']}},\n";
}
foreach ($prototipos as $p) {
    $nom = addslashes($p['nombre']);
    echo "  'p{$p['id']}': {tipo:'proto', nombre:'$nom', costo:{$p['costo_total_cop']}},\n";
}
?>
};

function toggleItem(card, tipo, id) {
    var hidId   = card.querySelector('.hid-id');
    var hidCant = card.querySelector('.hid-cant');
    if (card.classList.contains('seleccionado')) {
        // Quitar del kit
        card.classList.remove('seleccionado','proto');
        card.querySelector('.qty-control').style.display = 'none';
        card.querySelector('.badge-enkit').classList.add('d-none');
        if (hidId)   hidId.removeAttribute('name');
        if (hidCant) hidCant.removeAttribute('name');
    } else {
        // Agregar al kit
        card.classList.add('seleccionado');
        if (tipo === 'proto') card.classList.add('proto');
        card.querySelector('.qty-control').style.display = 'block';
        card.querySelector('.badge-enkit').classList.remove('d-none');
        card.querySelector('.qty-input').value = 1;
        card.querySelector('.hid-cant').value  = 1;
        if (hidId)   hidId.name   = (tipo === 'proto') ? 'proto_id[]'   : 'elem_id[]';
        if (hidCant) hidCant.name = (tipo === 'proto') ? 'proto_cant[]' : 'elem_cant[]';
    }
    actualizarResumen();
}

function cambiarCant(btn, delta) {
    // btn = el botón +/-, subir al .card contenedor
    var card = btn.closest('.card');
    var inp  = card.querySelector('.qty-input');
    var hid  = card.querySelector('.hid-cant');
    var val  = Math.max(1, parseInt(inp.value || 1) + delta);
    inp.value = val;
    hid.value = val;
    actualizarResumen();
}

function actualizarResumen() {
    var selElem  = document.querySelectorAll('.item-card[data-tipo="elem"]  .card.seleccionado');
    var selProto = document.querySelectorAll('.item-card[data-tipo="proto"] .card.seleccionado');
    var costo = 0, uds = 0, rows = [];

    selElem.forEach(function(card) {
        var wrap  = card.closest('.item-card');
        var id    = wrap.querySelector('.hid-id').value;
        var cant  = parseInt(wrap.querySelector('.qty-input').value || 1);
        var info  = catalogo['e'+id];
        if (!info) return;
        costo += info.costo * cant;
        uds   += cant;
        // Obtener imagen del catálogo
        var img = card.querySelector('img');
        var imgSrc = img ? img.src : '';
        rows.push(
          '<div class="resumen-item">' +
            '<div class="d-flex align-items-center gap-2">' +
              (imgSrc ? '<img src="'+imgSrc+'" style="width:32px;height:32px;object-fit:cover;border-radius:4px;flex-shrink:0">' :
                        '<div style="width:32px;height:32px;background:#e0e7ff;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0">&#x1F4E6;</div>') +
              '<div>' +
                '<div style="font-size:.78rem;font-weight:600;line-height:1.2">' + info.nombre.substring(0,30) + (info.nombre.length>30?'&hellip;':'') + '</div>' +
                '<div style="font-size:.7rem;color:#94a3b8">' + info.codigo + '</div>' +
              '</div>' +
            '</div>' +
            '<span class="badge bg-primary" style="font-size:.72rem;flex-shrink:0">&times;' + cant + '</span>' +
          '</div>'
        );
    });

    selProto.forEach(function(card) {
        var wrap  = card.closest('.item-card');
        var id    = wrap.querySelector('.hid-id').value;
        var cant  = parseInt(wrap.querySelector('.qty-input').value || 1);
        var info  = catalogo['p'+id];
        if (!info) return;
        costo += info.costo * cant;
        uds   += cant;
        var img = card.querySelector('img');
        var imgSrc = img ? img.src : '';
        rows.push(
          '<div class="resumen-item">' +
            '<div class="d-flex align-items-center gap-2">' +
              (imgSrc ? '<img src="'+imgSrc+'" style="width:32px;height:32px;object-fit:cover;border-radius:4px;flex-shrink:0">' :
                        '<div style="width:32px;height:32px;background:#fff0f0;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0">&#x2702;</div>') +
              '<div>' +
                '<div style="font-size:.78rem;font-weight:600;line-height:1.2">' + info.nombre.substring(0,30) + (info.nombre.length>30?'&hellip;':'') + '</div>' +
                '<div style="font-size:.7rem;color:#94a3b8">Prototipo / Corte</div>' +
              '</div>' +
            '</div>' +
            '<span class="badge bg-danger" style="font-size:.72rem;flex-shrink:0">&times;' + cant + '</span>' +
          '</div>'
        );
    });

    var total = selElem.length + selProto.length;
    document.getElementById('badge-total').textContent = total + ' item' + (total!==1?'s':'');
    document.getElementById('cnt-elem').textContent    = selElem.length;
    document.getElementById('cnt-proto').textContent   = selProto.length;
    document.getElementById('cnt-uds').textContent     = uds;
    document.getElementById('costo-total').textContent = '$ ' + costo.toLocaleString('es-CO', {maximumFractionDigits:0}) + ' COP';

    var total0 = total === 0;
    document.getElementById('btn-guardar').disabled  = total0;
    if (document.getElementById('btn-sticker')) {
        document.getElementById('btn-sticker').disabled = total0;
    }

    var lista = document.getElementById('lista-resumen');
    var msg   = document.getElementById('msg-vacio');
    if (total0) {
        lista.innerHTML = '';
        msg.style.display = '';
    } else {
        msg.style.display = 'none';
        lista.innerHTML   = rows.join('');
    }
}

// Buscador
document.getElementById('buscador').addEventListener('input', function() {
    var q = this.value.toLowerCase().trim();
    document.querySelectorAll('.item-card').forEach(function(c) {
        var match = !q || c.dataset.nombre.includes(q);
        c.style.display = match ? '' : 'none';
    });
    // Ocultar bloques de categoría vacíos
    document.querySelectorAll('.cat-bloque').forEach(function(b) {
        var vis = Array.from(b.querySelectorAll('.item-card')).some(function(c){ return c.style.display !== 'none'; });
        b.style.display = vis || !q ? '' : 'none';
    });
});

// Inicializar resumen con kit existente
actualizarResumen();

// ── Fix: sincronizar name ANTES de enviar el formulario ──────────────────
// Solo los inputs con name llegan al POST; los sin name son ignorados.
document.getElementById('fKit').addEventListener('submit', function () {
    document.querySelectorAll('.item-card').forEach(function (wrap) {
        var card    = wrap.querySelector('.card');
        var hidId   = wrap.querySelector('.hid-id');
        var hidCant = wrap.querySelector('.hid-cant');
        if (!hidId) return;
        var tipo     = wrap.dataset.tipo;
        var selected = card && card.classList.contains('seleccionado');
        if (selected) {
            hidId.name   = (tipo === 'proto') ? 'proto_id[]'   : 'elem_id[]';
            hidCant.name = (tipo === 'proto') ? 'proto_cant[]' : 'elem_cant[]';
        } else {
            hidId.removeAttribute('name');
            hidCant.removeAttribute('name');
        }
    });
});

function verSticker() {
    // Recolectar datos de los elementos seleccionados
    var items = [];
    document.querySelectorAll('.item-card .card.seleccionado').forEach(function(card) {
        var wrap  = card.closest('.item-card');
        var id    = wrap.querySelector('.hid-id').value;
        var tipo  = wrap.dataset.tipo;
        var cant  = parseInt(wrap.querySelector('.qty-input').value || 1);
        var info  = catalogo[(tipo==='elem'?'e':'p')+id];
        if (!info) return;
        var img   = card.querySelector('img');
        items.push({
            id: id, tipo: tipo, cant: cant,
            nombre: info.nombre,
            codigo: info.codigo || '',
            img: img ? img.src : '',
            costo: info.costo
        });
    });
    if (items.length === 0) { alert('Agrega elementos al kit primero.'); return; }

    // Obtener nombre del kit
    var kitNombre = document.querySelector('[name="kit_nombre"]')?.value ||
                    document.querySelector('h4')?.textContent?.trim() || 'Kit ROBOTSchool';

    // Guardar en sessionStorage y abrir sticker
    sessionStorage.setItem('sticker_kit_items', JSON.stringify(items));
    sessionStorage.setItem('sticker_kit_nombre', kitNombre);
    window.open('sticker_caja.php<?= $kitId?"?kit_id=$kitId":'' ?>', '_blank');
}
</script>

<script>
// Grados por colegio para el constructor
var CONS_GRADOS_X_COLEGIO = <?= json_encode($gradosByColegio) ?>;
var CONS_GRADOS_LABELS    = <?= json_encode($ALL_GRADOS_LABELS) ?>;

function consOnColegioChange(colegioId) {
    var blq      = document.getElementById('cons-blq-grado');
    var selGrado = document.getElementById('cons-sel-grado');
    if (!blq || !selGrado) return;
    if (!colegioId) { blq.style.display = 'none'; return; }
    blq.style.display = '';
    var grados = CONS_GRADOS_X_COLEGIO[colegioId] || Object.keys(CONS_GRADOS_LABELS);
    var currentVal = selGrado.value;
    selGrado.innerHTML = '<option value="">— Grado —</option>';
    grados.forEach(function(g) {
        var opt = document.createElement('option');
        opt.value = g;
        opt.textContent = CONS_GRADOS_LABELS[g] || g;
        if (g === currentVal) opt.selected = true;
        selGrado.appendChild(opt);
    });
}

// Init si ya hay colegio seleccionado (modo edición)
(function() {
    var sel = document.getElementById('cons-sel-colegio');
    if (sel && sel.value) consOnColegioChange(sel.value);
})();
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
