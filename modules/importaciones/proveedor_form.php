<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();

$db  = Database::get();
$id  = (int)($_GET['id'] ?? 0);
$pro = $id ? $db->query("SELECT * FROM proveedores WHERE id=$id")->fetch() : null;
$pageTitle  = $pro ? 'Editar Proveedor' : 'Nuevo Proveedor';
$activeMenu = 'proveedores';
$error = $success = '';

// Contactos adicionales del proveedor
$contactos = $id ? $db->query("SELECT * FROM proveedor_contactos WHERE proveedor_id=$id ORDER BY es_principal DESC")->fetchAll() : [];

// ── Guardar contacto adicional ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_contacto' && $pro) {
    if (!Auth::csrfVerify($_POST['csrf'] ?? '')) die('CSRF');
    $db->prepare("INSERT INTO proveedor_contactos (proveedor_id,nombre,cargo,email,telefono,whatsapp,es_principal,notas) VALUES (?,?,?,?,?,?,?,?)")
       ->execute([$id, trim($_POST['c_nombre']), trim($_POST['c_cargo'] ?? ''), trim($_POST['c_email'] ?? ''),
                  trim($_POST['c_telefono'] ?? ''), trim($_POST['c_whatsapp'] ?? ''),
                  isset($_POST['c_principal']) ? 1 : 0, trim($_POST['c_notas'] ?? '')]);
    header('Location: ' . APP_URL . "/modules/importaciones/proveedor_form.php?id=$id#contactos"); exit;
}

// Eliminar contacto
if (isset($_GET['del_contacto']) && $pro && Auth::csrfVerify($_GET['csrf'] ?? '')) {
    $db->prepare("DELETE FROM proveedor_contactos WHERE id=? AND proveedor_id=?")->execute([(int)$_GET['del_contacto'], $id]);
    header('Location: ' . APP_URL . "/modules/importaciones/proveedor_form.php?id=$id#contactos"); exit;
}

// ── Guardar proveedor ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_proveedor') {
    if (!Auth::csrfVerify($_POST['csrf'] ?? '')) die('CSRF');
    try {
        $foto = $pro['foto'] ?? null;
        if (!empty($_FILES['foto']['tmp_name'])) $foto = subirFoto($_FILES['foto'], 'proveedores');

        // Método de pago (checkbox múltiple)
        $metodoPago = isset($_POST['metodo_pago']) && is_array($_POST['metodo_pago'])
            ? implode(',', array_filter($_POST['metodo_pago']))
            : null;

        $data = [
            'nombre'                  => trim($_POST['nombre']),
            'nombre_comercial'        => trim($_POST['nombre_comercial']     ?? ''),
            'tipo'                    => $_POST['tipo']                      ?? 'electronica_china',
            'pais'                    => trim($_POST['pais']),
            'ciudad'                  => trim($_POST['ciudad']              ?? ''),
            'contacto_nombre'         => trim($_POST['contacto_nombre']     ?? ''),
            'contacto_cargo'          => trim($_POST['contacto_cargo']      ?? ''),
            'email'                   => trim($_POST['email']               ?? ''),
            'telefono'                => trim($_POST['telefono']            ?? ''),
            'whatsapp'                => trim($_POST['whatsapp']            ?? ''),
            'url_tienda'              => trim($_POST['url_tienda']          ?? ''),
            'url_catalogo'            => trim($_POST['url_catalogo']        ?? ''),
            'nit_rut'                 => trim($_POST['nit_rut']             ?? ''),
            'tiempo_entrega_dias'     => ($_POST['tiempo_entrega_dias'] ?: null),
            'moneda'                  => $_POST['moneda']                   ?? 'USD',
            'minimo_pedido'           => trim($_POST['minimo_pedido']       ?? ''),
            'descuento_habitual_pct'  => (float)($_POST['descuento_habitual_pct'] ?? 0),
            'calificacion'            => max(1, min(5, (int)$_POST['calificacion'])),
            'metodo_pago'             => $metodoPago,
            'requiere_dhl'            => isset($_POST['requiere_dhl']) ? 1 : 0,
            'codigo_proveedor_dhl'    => trim($_POST['codigo_proveedor_dhl'] ?? ''),
            'puerto_origen'           => trim($_POST['puerto_origen']        ?? ''),
            'incoterm'                => $_POST['incoterm']                  ?? 'EXW',
            'categorias_producto'     => trim($_POST['categorias_producto']  ?? ''),
            'es_preferido'            => isset($_POST['es_preferido']) ? 1 : 0,
            'notas'                   => trim($_POST['notas']               ?? ''),
            'foto'                    => $foto,
            'activo'                  => 1,
        ];

        if ($pro) {
            $sets = implode(',', array_map(fn($k)=>"$k=:$k", array_keys($data)));
            $data['id'] = $id;
            $db->prepare("UPDATE proveedores SET $sets WHERE id=:id")->execute($data);
            $success = 'Proveedor actualizado correctamente.';
            $pro = $db->query("SELECT * FROM proveedores WHERE id=$id")->fetch();
        } else {
            // Generar código
            $db->beginTransaction();
            $seq = $db->query("SELECT ultimo_numero FROM proveedores_secuencia FOR UPDATE")->fetchColumn();
            $nuevo = (int)$seq + 1;
            $db->query("UPDATE proveedores_secuencia SET ultimo_numero=$nuevo");
            $data['codigo'] = 'RS-PROV-' . str_pad($nuevo, 3, '0', STR_PAD_LEFT);
            $data['created_by'] = Auth::user()['id'];
            $cols = implode(',', array_keys($data));
            $vals = ':' . implode(',:', array_keys($data));
            $db->prepare("INSERT INTO proveedores ($cols) VALUES ($vals)")->execute($data);
            $newId = $db->lastInsertId();
            $db->commit();
            header('Location: ' . APP_URL . '/modules/importaciones/proveedor_form.php?id=' . $newId . '&ok=1'); exit;
        }
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $error = $e->getMessage();
    }
}

if (!empty($_GET['ok'])) $success = 'Proveedor creado correctamente.';

$tipoConfig = [
    'electronica_china'      => ['🇨🇳','Electrónica China'],
    'electronica_colombia'   => ['🇨🇴','Electrónica Colombia'],
    'cajas_empaque'          => ['&#x1F4E6;','Cajas y Empaque'],
    'stickers_impresion'     => ['🖨️','Stickers e Impresión'],
    'libros_material'        => ['📚','Libros y Material Educativo'],
    'fabricacion_materiales' => ['🔨','Materiales de Fabricación'],
    'transporte'             => ['🚚','Transporte / Logística'],
    'otro'                   => ['📋','Otro'],
];

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="d-flex align-items-center gap-2 mb-4">
  <a href="<?= APP_URL ?>/modules/importaciones/proveedores.php" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i></a>
  <div>
    <h4 class="fw-bold mb-0"><?= $pageTitle ?></h4>
    <?php if ($pro): ?><code class="text-primary"><?= $pro['codigo'] ?></code><?php endif; ?>
  </div>
</div>

<?php if ($error):   ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<form method="POST" enctype="multipart/form-data">
  <input type="hidden" name="action" value="save_proveedor">
  <input type="hidden" name="csrf"   value="<?= Auth::csrfToken() ?>">

  <div class="row g-4">
    <!-- ── Columna izquierda ── -->
    <div class="col-lg-8">

      <!-- Identificación -->
      <div class="section-card">
        <h6 class="fw-bold mb-3 text-primary"><i class="bi bi-shop me-2"></i>Identificación del Proveedor</h6>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Nombre / Razón Social *</label>
            <input type="text" name="nombre" class="form-control" required
                   value="<?= htmlspecialchars($pro['nombre'] ?? '') ?>"
                   placeholder="Ej: Shenzhen Electronics Co. Ltd">
          </div>
          <div class="col-md-6">
            <label class="form-label">Nombre Comercial / Tienda</label>
            <input type="text" name="nombre_comercial" class="form-control"
                   value="<?= htmlspecialchars($pro['nombre_comercial'] ?? '') ?>"
                   placeholder="Ej: ElectronicaRobot Store">
          </div>
          <div class="col-md-6">
            <label class="form-label">Tipo de Proveedor *</label>
            <select name="tipo" class="form-select" id="tipoSelect" onchange="toggleCamposDHL()">
              <?php foreach ($tipoConfig as $val => [$ico, $lbl]): ?>
                <option value="<?= $val ?>" <?= ($pro['tipo'] ?? 'electronica_china') === $val ? 'selected' : '' ?>>
                  <?= $ico ?> <?= $lbl ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <label class="form-label">País *</label>
            <input type="text" name="pais" class="form-control" required list="paises-list"
                   value="<?= htmlspecialchars($pro['pais'] ?? 'China') ?>">
            <datalist id="paises-list">
              <option>China</option><option>Colombia</option>
              <option>Estados Unidos</option><option>Alemania</option>
            </datalist>
          </div>
          <div class="col-md-3">
            <label class="form-label">Ciudad</label>
            <input type="text" name="ciudad" class="form-control"
                   value="<?= htmlspecialchars($pro['ciudad'] ?? '') ?>" placeholder="Shenzhen / Bogotá">
          </div>
          <div class="col-md-4">
            <label class="form-label">NIT / RUT</label>
            <input type="text" name="nit_rut" class="form-control"
                   value="<?= htmlspecialchars($pro['nit_rut'] ?? '') ?>" placeholder="900.000.000-1">
          </div>
          <div class="col-md-4">
            <label class="form-label">Moneda de Negociación</label>
            <select name="moneda" class="form-select">
              <?php foreach (['USD'=>'Dólar (USD)','COP'=>'Peso CO (COP)','CNY'=>'Yuan (CNY)','EUR'=>'Euro (EUR)'] as $v=>$l): ?>
                <option value="<?= $v ?>" <?= ($pro['moneda']??'USD')===$v?'selected':'' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4 d-flex align-items-end pb-1 gap-3">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="es_preferido" id="chkPref"
                     <?= ($pro['es_preferido'] ?? 0) ? 'checked' : '' ?>>
              <label class="form-check-label fw-semibold" for="chkPref">⭐ Proveedor Preferido</label>
            </div>
          </div>
        </div>
      </div>

      <!-- Contacto -->
      <div class="section-card">
        <h6 class="fw-bold mb-3 text-primary"><i class="bi bi-person-lines-fill me-2"></i>Contacto Principal</h6>
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Nombre del Contacto</label>
            <input type="text" name="contacto_nombre" class="form-control"
                   value="<?= htmlspecialchars($pro['contacto_nombre'] ?? '') ?>" placeholder="Nombre del vendedor / asesor">
          </div>
          <div class="col-md-6">
            <label class="form-label">Cargo</label>
            <input type="text" name="contacto_cargo" class="form-control"
                   value="<?= htmlspecialchars($pro['contacto_cargo'] ?? '') ?>" placeholder="Sales Manager, Asesor Comercial">
          </div>
          <div class="col-md-6">
            <label class="form-label">Email</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-envelope"></i></span>
              <input type="email" name="email" class="form-control"
                     value="<?= htmlspecialchars($pro['email'] ?? '') ?>" placeholder="ventas@proveedor.com">
            </div>
          </div>
          <div class="col-md-3">
            <label class="form-label">Teléfono</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-telephone"></i></span>
              <input type="text" name="telefono" class="form-control"
                     value="<?= htmlspecialchars($pro['telefono'] ?? '') ?>" placeholder="+57 601...">
            </div>
          </div>
          <div class="col-md-3">
            <label class="form-label">WhatsApp</label>
            <div class="input-group">
              <span class="input-group-text text-success"><i class="bi bi-whatsapp"></i></span>
              <input type="text" name="whatsapp" class="form-control"
                     value="<?= htmlspecialchars($pro['whatsapp'] ?? '') ?>" placeholder="+57 300...">
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label">URL Tienda / Perfil</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-globe"></i></span>
              <input type="url" name="url_tienda" class="form-control"
                     value="<?= htmlspecialchars($pro['url_tienda'] ?? '') ?>" placeholder="https://...">
            </div>
          </div>
          <div class="col-md-6">
            <label class="form-label">URL Catálogo / PDF</label>
            <div class="input-group">
              <span class="input-group-text"><i class="bi bi-file-pdf"></i></span>
              <input type="url" name="url_catalogo" class="form-control"
                     value="<?= htmlspecialchars($pro['url_catalogo'] ?? '') ?>" placeholder="https://...">
            </div>
          </div>
        </div>
      </div>

      <!-- Condiciones comerciales -->
      <div class="section-card">
        <h6 class="fw-bold mb-3 text-warning"><i class="bi bi-cash-coin me-2"></i>Condiciones Comerciales</h6>
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Tiempo de Entrega (días)</label>
            <div class="input-group">
              <input type="number" name="tiempo_entrega_dias" class="form-control" min="0" max="120"
                     value="<?= $pro['tiempo_entrega_dias'] ?? '' ?>" placeholder="20">
              <span class="input-group-text">días</span>
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Pedido Mínimo</label>
            <input type="text" name="minimo_pedido" class="form-control"
                   value="<?= htmlspecialchars($pro['minimo_pedido'] ?? '') ?>"
                   placeholder="$50 USD / 10 unidades">
          </div>
          <div class="col-md-4">
            <label class="form-label">Descuento Habitual</label>
            <div class="input-group">
              <input type="number" name="descuento_habitual_pct" class="form-control"
                     min="0" max="100" step="0.5"
                     value="<?= $pro['descuento_habitual_pct'] ?? 0 ?>">
              <span class="input-group-text">%</span>
            </div>
          </div>
          <div class="col-12">
            <label class="form-label">Métodos de Pago Aceptados</label>
            <div class="d-flex flex-wrap gap-2">
              <?php
              $metodosPago = ['transferencia'=>'Transferencia Bancaria','paypal'=>'PayPal','tarjeta'=>'Tarjeta Crédito','efectivo'=>'Efectivo','credito'=>'Crédito 30-60 días','ali_escrow'=>'AliExpress Escrow'];
              $metodosActivos = $pro ? explode(',', $pro['metodo_pago'] ?? '') : [];
              foreach ($metodosPago as $val => $lbl):
              ?>
              <div>
                <input type="checkbox" class="form-check-input visually-hidden"
                       name="metodo_pago[]" value="<?= $val ?>" id="mp_<?= $val ?>"
                       <?= in_array($val, $metodosActivos) ? 'checked' : '' ?>>
                <label class="btn btn-sm btn-outline-secondary tipo-toggle" for="mp_<?= $val ?>"><?= $lbl ?></label>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="col-md-3">
            <label class="form-label">Calificación</label>
            <div class="d-flex gap-1 align-items-center">
              <?php for ($s=1; $s<=5; $s++): ?>
              <div>
                <input type="radio" name="calificacion" value="<?= $s ?>" id="star<?= $s ?>"
                       class="visually-hidden star-radio"
                       <?= ($pro['calificacion'] ?? 3) == $s ? 'checked' : '' ?>>
                <label for="star<?= $s ?>" class="star-label fs-4" style="cursor:pointer;color:#fbbf24;">☆</label>
              </div>
              <?php endfor; ?>
            </div>
          </div>
          <div class="col-12">
            <label class="form-label">Productos / Categorías que suministra</label>
            <textarea name="categorias_producto" class="form-control" rows="2"
                      placeholder="Ej: Cajas plásticas transparentes 15×10×5cm, organizadores para componentes electrónicos, caja con espuma"><?= htmlspecialchars($pro['categorias_producto'] ?? '') ?></textarea>
          </div>
        </div>
      </div>

      <!-- Logística de importación (solo para proveedores de China) -->
      <div class="section-card" id="seccionDHL">
        <h6 class="fw-bold mb-3 text-info">
          <i class="bi bi-airplane me-2"></i>Logística de Importación
          <span class="badge bg-light text-dark ms-1" style="font-size:.7rem;">Solo China/Internacional</span>
        </h6>
        <div class="row g-3">
          <div class="col-md-4 d-flex align-items-center">
            <div class="form-check">
              <input class="form-check-input" type="checkbox" name="requiere_dhl" id="chkDHL"
                     onchange="toggleDHLFields()"
                     <?= ($pro['requiere_dhl'] ?? 0) ? 'checked' : '' ?>>
              <label class="form-check-label fw-semibold" for="chkDHL">&#x2708;&#xFE0F; Envío via DHL</label>
            </div>
          </div>
          <div class="col-md-4">
            <label class="form-label">Puerto de Origen</label>
            <input type="text" name="puerto_origen" class="form-control" list="puertos-list"
                   value="<?= htmlspecialchars($pro['puerto_origen'] ?? '') ?>" placeholder="Shenzhen">
            <datalist id="puertos-list">
              <option>Shenzhen</option><option>Guangzhou</option><option>Shanghai</option><option>Yiwu</option>
            </datalist>
          </div>
          <div class="col-md-4">
            <label class="form-label">Incoterm</label>
            <select name="incoterm" class="form-select">
              <?php foreach (['EXW'=>'EXW (Ex Works)','FOB'=>'FOB','CIF'=>'CIF','DDP'=>'DDP','DAP'=>'DAP'] as $v=>$l): ?>
                <option value="<?= $v ?>" <?= ($pro['incoterm']??'EXW')===$v?'selected':'' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-6" id="dhlAccountField">
            <label class="form-label">Account Number DHL (si aplica)</label>
            <input type="text" name="codigo_proveedor_dhl" class="form-control"
                   value="<?= htmlspecialchars($pro['codigo_proveedor_dhl'] ?? '') ?>" placeholder="12345678">
          </div>
        </div>
      </div>

      <!-- Notas -->
      <div class="section-card">
        <h6 class="fw-bold mb-3"><i class="bi bi-sticky me-2 text-secondary"></i>Notas Internas</h6>
        <textarea name="notas" class="form-control" rows="3"
                  placeholder="Observaciones, acuerdos, experiencias anteriores, condiciones especiales..."><?= htmlspecialchars($pro['notas'] ?? '') ?></textarea>
      </div>
    </div>

    <!-- ── Columna derecha ── -->
    <div class="col-lg-4">

      <!-- Logo -->
      <div class="section-card text-center">
        <h6 class="fw-bold mb-2"><i class="bi bi-image me-2 text-primary"></i>Logo / Imagen</h6>
        <?php if ($pro && $pro['foto']): ?>
          <img src="<?= UPLOAD_URL . htmlspecialchars($pro['foto']) ?>" id="logoPreview"
               class="img-fluid rounded mb-2" style="max-height:120px;object-fit:contain;" alt="">
        <?php else: ?>
          <div class="bg-light rounded d-flex align-items-center justify-content-center mb-2" style="height:90px;font-size:2.5rem;" id="logoEmpty">🏭</div>
          <img id="logoPreview" src="" style="display:none;max-height:120px;" class="img-fluid rounded mb-2">
        <?php endif; ?>
        <input type="file" name="foto" class="form-control form-control-sm img-preview-input"
               accept="image/*" data-preview="logoPreview">
      </div>

      <!-- Estadísticas si existe -->
      <?php if ($pro): ?>
      <div class="section-card mt-3">
        <h6 class="fw-bold mb-2"><i class="bi bi-bar-chart me-2 text-success"></i>Actividad</h6>
        <?php
        $elems = $db->query("SELECT COUNT(*) FROM elementos WHERE proveedor_id=$id AND activo=1")->fetchColumn();
        $peds  = $db->query("SELECT COUNT(*) FROM pedidos_importacion WHERE proveedor_id=$id")->fetchColumn();
        $gasto = $db->query("SELECT COALESCE(SUM(costo_total_cop),0) FROM pedidos_importacion WHERE proveedor_id=$id AND estado='liquidado'")->fetchColumn();
        ?>
        <div class="d-flex justify-content-between py-1 border-bottom">
          <span class="text-muted small">Elementos asociados</span><strong><?= $elems ?></strong>
        </div>
        <div class="d-flex justify-content-between py-1 border-bottom">
          <span class="text-muted small">Pedidos realizados</span><strong><?= $peds ?></strong>
        </div>
        <div class="d-flex justify-content-between py-1">
          <span class="text-muted small">Total comprado</span><strong class="text-success"><?= cop($gasto) ?></strong>
        </div>
        <?php if ($peds > 0): ?>
        <a href="<?= APP_URL ?>/modules/importaciones/?prov=<?= $id ?>" class="btn btn-sm btn-outline-primary w-100 mt-2">
          Ver pedidos <i class="bi bi-arrow-right ms-1"></i>
        </a>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <!-- Guardar -->
      <div class="d-grid gap-2 mt-3">
        <button type="submit" class="btn btn-primary btn-lg fw-bold">
          <i class="bi bi-save me-2"></i><?= $pro ? 'Guardar Cambios' : 'Crear Proveedor' ?>
        </button>
        <a href="proveedores.php" class="btn btn-outline-secondary">Cancelar</a>
      </div>
    </div>
  </div>
</form>

<!-- ── CONTACTOS ADICIONALES ── -->
<?php if ($pro): ?>
<div class="section-card mt-4" id="contactos">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h6 class="fw-bold mb-0"><i class="bi bi-people me-2 text-primary"></i>Contactos Adicionales</h6>
    <button class="btn btn-sm btn-outline-primary" data-bs-toggle="collapse" data-bs-target="#addContactoForm">
      <i class="bi bi-plus-lg me-1"></i>Agregar Contacto
    </button>
  </div>

  <div class="collapse mb-3" id="addContactoForm">
    <form method="POST" class="bg-light rounded p-3">
      <input type="hidden" name="action" value="save_contacto">
      <input type="hidden" name="csrf"   value="<?= Auth::csrfToken() ?>">
      <div class="row g-2">
        <div class="col-md-4">
          <input type="text" name="c_nombre" class="form-control form-control-sm" placeholder="Nombre *" required>
        </div>
        <div class="col-md-3">
          <input type="text" name="c_cargo" class="form-control form-control-sm" placeholder="Cargo">
        </div>
        <div class="col-md-5">
          <input type="email" name="c_email" class="form-control form-control-sm" placeholder="Email">
        </div>
        <div class="col-md-3">
          <input type="text" name="c_telefono" class="form-control form-control-sm" placeholder="Teléfono">
        </div>
        <div class="col-md-3">
          <input type="text" name="c_whatsapp" class="form-control form-control-sm" placeholder="WhatsApp">
        </div>
        <div class="col-md-4">
          <input type="text" name="c_notas" class="form-control form-control-sm" placeholder="Notas">
        </div>
        <div class="col-md-2 d-flex align-items-center gap-2">
          <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" name="c_principal" id="chkPrincipal">
            <label class="form-check-label small" for="chkPrincipal">Principal</label>
          </div>
        </div>
        <div class="col-12">
          <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>Guardar Contacto</button>
        </div>
      </div>
    </form>
  </div>

  <?php if (empty($contactos)): ?>
    <p class="text-muted small text-center py-2">No hay contactos adicionales registrados.</p>
  <?php else: ?>
  <div class="row g-2">
  <?php foreach ($contactos as $ct): ?>
    <div class="col-md-6">
      <div class="d-flex align-items-start gap-2 p-2 border rounded">
        <div class="avatar-circle" style="width:32px;height:32px;font-size:.85rem;flex-shrink:0;">
          <?= strtoupper(substr($ct['nombre'],0,1)) ?>
        </div>
        <div class="flex-grow-1 min-width-0">
          <div class="fw-semibold small"><?= htmlspecialchars($ct['nombre']) ?>
            <?php if ($ct['es_principal']): ?><span class="badge bg-primary ms-1" style="font-size:.65rem;">Principal</span><?php endif; ?>
          </div>
          <?php if ($ct['cargo']): ?><div class="text-muted" style="font-size:.75rem;"><?= htmlspecialchars($ct['cargo']) ?></div><?php endif; ?>
          <div style="font-size:.75rem;">
            <?php if ($ct['email']): ?><a href="mailto:<?= $ct['email'] ?>" class="text-muted d-block"><?= htmlspecialchars($ct['email']) ?></a><?php endif; ?>
            <?php if ($ct['whatsapp']): ?><a href="https://wa.me/<?= preg_replace('/[^0-9]/','',$ct['whatsapp']) ?>" target="_blank" class="text-success"><i class="bi bi-whatsapp me-1"></i><?= htmlspecialchars($ct['whatsapp']) ?></a><?php endif; ?>
          </div>
        </div>
        <a href="?id=<?= $id ?>&del_contacto=<?= $ct['id'] ?>&csrf=<?= Auth::csrfToken() ?>"
           class="btn btn-sm btn-outline-danger py-0 px-1" style="font-size:.75rem;"
           data-confirm="¿Eliminar este contacto?"><i class="bi bi-trash"></i></a>
      </div>
    </div>
  <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<style>
.star-radio:checked ~ .star-label, .star-label:hover { color: #f59e0b !important; }
.tipo-toggle { cursor:pointer; }
input[type=checkbox]:checked + .tipo-toggle { font-weight:700; background-color: rgba(0,0,0,.08); }
</style>

<script>
function toggleCamposDHL() {
  const tipo = document.getElementById('tipoSelect').value;
  const esInternacional = tipo.includes('china') || tipo === 'transporte';
  document.getElementById('seccionDHL').style.display = esInternacional ? '' : 'none';
}

function toggleDHLFields() {
  // Mostrar/ocultar campo account DHL
  const activo = document.getElementById('chkDHL').checked;
  document.getElementById('dhlAccountField').style.display = activo ? '' : 'none';
}

// Inicializar estrellitas
document.querySelectorAll('.star-radio').forEach((radio, i) => {
  if (radio.checked) {
    document.querySelectorAll('.star-label').forEach((lbl, j) => {
      lbl.textContent = j <= i ? '★' : '☆';
    });
  }
  radio.addEventListener('change', function() {
    const idx = parseInt(this.value) - 1;
    document.querySelectorAll('.star-label').forEach((lbl, j) => {
      lbl.textContent = j <= idx ? '★' : '☆';
    });
  });
});

// Inicializar visibilidad sección DHL
toggleCamposDHL();
toggleDHLFields();
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
