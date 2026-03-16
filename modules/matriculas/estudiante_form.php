<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();

$db = Database::get();
$id  = (int)($_GET['id'] ?? 0);
$est = $id ? $db->query("SELECT * FROM estudiantes WHERE id=$id")->fetch() : null;
$pageTitle  = $est ? 'Editar Estudiante' : 'Nuevo Estudiante';
$activeMenu = 'matriculas';
$error = '';

// Colores para avatares
$AVATAR_COLORS = ['#185FA5','#16a34a','#dc2626','#7c3aed','#d97706','#0891b2','#be185d','#065f46'];

if ($_SERVER['REQUEST_METHOD']==='POST' && Auth::csrfVerify($_POST['csrf']??'')) {
    try {
        $data = [
            'nombres'           => trim($_POST['nombres']),
            'apellidos'         => trim($_POST['apellidos']),
            'tipo_doc'          => $_POST['tipo_doc']  ?? 'TI',
            'documento'         => trim($_POST['documento']  ?? '') ?: null,
            'fecha_nac'         => $_POST['fecha_nac']        ?: null,
            'genero'            => $_POST['genero']    ?? '',
            'rh'                => trim($_POST['rh']          ?? '') ?: null,
            'eps'               => trim($_POST['eps']         ?? '') ?: null,
            'num_seguro'        => trim($_POST['num_seguro']  ?? '') ?: null,
            'alergias'          => trim($_POST['alergias']    ?? '') ?: null,
            'condicion_medica'  => trim($_POST['condicion_medica'] ?? '') ?: null,
            'acudiente'         => trim($_POST['acudiente']),
            'parentesco'        => trim($_POST['parentesco']  ?? '') ?: null,
            'doc_acudiente'     => trim($_POST['doc_acudiente']??'') ?: null,
            'telefono'          => trim($_POST['telefono']    ?? '') ?: null,
            'telefono2'         => trim($_POST['telefono2']   ?? '') ?: null,
            'email'             => trim($_POST['email']       ?? '') ?: null,
            'direccion'         => trim($_POST['direccion']   ?? '') ?: null,
            'barrio'            => trim($_POST['barrio']      ?? '') ?: null,
            'ciudad'            => trim($_POST['ciudad']      ?? 'Bogota'),
            'colegio_ext'       => trim($_POST['colegio_ext'] ?? '') ?: null,
            'grado_ext'         => trim($_POST['grado_ext']   ?? '') ?: null,
            'jornada_ext'       => trim($_POST['jornada_ext'] ?? '') ?: null,
            'como_conocio'      => trim($_POST['como_conocio']?? '') ?: null,
            'autorizacion_foto' => isset($_POST['autorizacion_foto'])  ? 1 : 0,
            'autorizacion_datos'=> isset($_POST['autorizacion_datos']) ? 1 : 0,
            'avatar_color'      => $_POST['avatar_color'] ?? $AVATAR_COLORS[0],
            'notas'             => trim($_POST['notas'] ?? '') ?: null,
            'activo'            => 1,
        ];
        if ($id) {
            $sets = implode(',', array_map(function($k){ return "$k=:$k"; }, array_keys($data)));
            $data['id'] = $id;
            $db->prepare("UPDATE estudiantes SET $sets WHERE id=:id")->execute($data);
        } else {
            $c2 = implode(',', array_keys($data));
            $v2 = ':'.implode(',:', array_keys($data));
            $db->prepare("INSERT INTO estudiantes ($c2) VALUES ($v2)")->execute($data);
            $id = $db->lastInsertId();
        }
        header('Location: '.APP_URL.'/modules/matriculas/estudiantes.php?ok=1'); exit;
    } catch (Exception $e) { $error = $e->getMessage(); }
}

// Calcular edad si hay fecha
$edad = '';
if (!empty($est['fecha_nac'])) {
    $edad = date_diff(date_create($est['fecha_nac']), date_create('today'))->y . ' años';
}

// Iniciales para avatar
$initials = '';
if ($est) {
    $initials = strtoupper(substr($est['nombres'],0,1).substr($est['apellidos'],0,1));
} 

$avatarColor = $est['avatar_color'] ?? $AVATAR_COLORS[0];

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
.sc{background:#fff;border-radius:14px;border:1px solid #e2e8f0;padding:1.2rem;margin-bottom:1rem}
.sec-title{font-size:.8rem;font-weight:700;text-transform:uppercase;letter-spacing:.07em;padding:.35rem .7rem;border-radius:6px;margin-bottom:1rem;display:flex;align-items:center;gap:.5rem}
.req{color:#ef4444;margin-left:2px}

/* Avatar */
.avatar-wrap{position:relative;width:100px;height:100px;margin:0 auto 1rem}
.avatar-svg{width:100px;height:100px;border-radius:50%;cursor:pointer;transition:.2s}
.avatar-svg:hover{opacity:.85;transform:scale(1.05)}
.avatar-upload{position:absolute;bottom:0;right:0;width:28px;height:28px;background:#1e293b;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#fff;font-size:.75rem}
.color-dot{width:22px;height:22px;border-radius:50%;cursor:pointer;border:3px solid transparent;transition:.15s;display:inline-block}
.color-dot.sel{border-color:#1e293b;transform:scale(1.2)}

/* Política modal */
.politica-text{font-size:.82rem;line-height:1.7;color:#374151}
.politica-text h6{color:#185FA5;font-weight:700;margin-top:1rem}
.auth-box{border:2px solid #e2e8f0;border-radius:10px;padding:1rem;background:#f8fafc}
.auth-box.firmado{border-color:#22c55e;background:#f0fdf4}
</style>

<div class="d-flex align-items-center gap-2 mb-3">
  <a href="estudiantes.php" class="btn btn-sm btn-light"><i class="bi bi-arrow-left"></i></a>
  <h4 class="fw-bold mb-0"><?= $est ? 'Editar Estudiante' : 'Nuevo Estudiante' ?></h4>
  <?php if ($edad): ?>
    <span class="badge bg-primary ms-1"><?= $edad ?></span>
  <?php endif; ?>
</div>

<?php if ($error): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<form method="POST" id="frmEstudiante">
<input type="hidden" name="csrf"         value="<?= Auth::csrfToken() ?>">
<input type="hidden" name="avatar_color" id="avatarColorInput" value="<?= htmlspecialchars($avatarColor) ?>">

<div class="row g-3">

<!-- ══ COLUMNA IZQUIERDA ══ -->
<div class="col-lg-4">

  <!-- Avatar -->
  <div class="sc text-center">
    <div class="avatar-wrap" id="avatarWrap">
      <svg class="avatar-svg" id="avatarSvg" viewBox="0 0 100 100"
           xmlns="http://www.w3.org/2000/svg" onclick="document.getElementById('fileAvatar').click()">
        <circle cx="50" cy="50" r="50" fill="<?= $avatarColor ?>"/>
        <text x="50" y="50" text-anchor="middle" dominant-baseline="central"
              fill="white" font-size="36" font-weight="700" font-family="Arial">
          <?= $initials ?: '?' ?>
        </text>
      </svg>
      <div class="avatar-upload" onclick="document.getElementById('fileAvatar').click()" title="Subir foto">
        <i class="bi bi-camera"></i>
      </div>
    </div>
    <input type="file" id="fileAvatar" accept="image/*" class="d-none"
           onchange="previewAvatar(this)">
    <div class="text-muted small mb-2">Color del avatar</div>
    <div class="d-flex gap-1 justify-content-center flex-wrap mb-1">
      <?php foreach ($AVATAR_COLORS as $color): ?>
      <div class="color-dot <?= $color===$avatarColor?'sel':'' ?>"
           style="background:<?= $color ?>"
           onclick="setAvatarColor('<?= $color ?>')"></div>
      <?php endforeach; ?>
    </div>
    <?php if ($est): ?>
    <div class="mt-2">
      <div class="fw-bold"><?= htmlspecialchars($est['nombres'].' '.$est['apellidos']) ?></div>
      <?php if ($est['documento']): ?>
        <div class="text-muted small"><?= $est['tipo_doc']??'TI' ?> <?= htmlspecialchars($est['documento']) ?></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>

  <!-- Acudiente -->
  <div class="sc">
    <div class="sec-title" style="background:#f0fdf4;color:#166534">
      <i class="bi bi-people-fill"></i>Datos del Acudiente
    </div>
    <div class="row g-2">
      <div class="col-12">
        <label class="form-label small fw-semibold">Nombre completo<span class="req">*</span></label>
        <input type="text" name="acudiente" class="form-control form-control-sm" required
               value="<?= htmlspecialchars($est['acudiente'] ?? '') ?>">
      </div>
      <div class="col-6">
        <label class="form-label small fw-semibold">Parentesco</label>
        <select name="parentesco" class="form-select form-select-sm">
          <option value="">--</option>
          <?php foreach (['Madre','Padre','Abuelo/a','Tio/a','Hermano/a','Tutor/a legal','Otro'] as $p): ?>
            <option value="<?= $p ?>" <?= ($est['parentesco']??'')===$p?'selected':'' ?>><?= $p ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6">
        <label class="form-label small fw-semibold">C.C. Acudiente</label>
        <input type="text" name="doc_acudiente" class="form-control form-control-sm"
               value="<?= htmlspecialchars($est['doc_acudiente'] ?? '') ?>">
      </div>
      <div class="col-12">
        <label class="form-label small fw-semibold">Tel&eacute;fono principal<span class="req">*</span></label>
        <input type="tel" name="telefono" class="form-control form-control-sm" required
               value="<?= htmlspecialchars($est['telefono'] ?? '') ?>">
      </div>
      <div class="col-12">
        <label class="form-label small fw-semibold">Tel&eacute;fono alternativo</label>
        <input type="tel" name="telefono2" class="form-control form-control-sm"
               value="<?= htmlspecialchars($est['telefono2'] ?? '') ?>">
      </div>
      <div class="col-12">
        <label class="form-label small fw-semibold">Email</label>
        <input type="email" name="email" class="form-control form-control-sm"
               value="<?= htmlspecialchars($est['email'] ?? '') ?>">
      </div>
    </div>
  </div>

  <!-- Como nos conocio -->
  <div class="sc">
    <label class="form-label small fw-semibold">Como nos conocio</label>
    <select name="como_conocio" class="form-select form-select-sm">
      <option value="">--</option>
      <?php foreach (['Instagram','Facebook','WhatsApp','Recomendacion de un amigo','Colegio','Google','Valla o pendon','Evento','Otro'] as $c): ?>
        <option value="<?= $c ?>" <?= ($est['como_conocio']??'')===$c?'selected':'' ?>><?= $c ?></option>
      <?php endforeach; ?>
    </select>
  </div>

</div>

<!-- ══ COLUMNA DERECHA ══ -->
<div class="col-lg-8">

  <!-- Datos personales -->
  <div class="sc">
    <div class="sec-title" style="background:#eff6ff;color:#1e40af">
      <i class="bi bi-person-badge-fill"></i>Datos Personales del Estudiante
    </div>
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label small fw-semibold">Nombres<span class="req">*</span></label>
        <input type="text" name="nombres" class="form-control" required
               oninput="actualizarAvatar()"
               value="<?= htmlspecialchars($est['nombres'] ?? '') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label small fw-semibold">Apellidos<span class="req">*</span></label>
        <input type="text" name="apellidos" class="form-control" required
               oninput="actualizarAvatar()"
               value="<?= htmlspecialchars($est['apellidos'] ?? '') ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold">Tipo doc.</label>
        <select name="tipo_doc" class="form-select">
          <option value="TI" <?= ($est['tipo_doc']??'TI')==='TI'?'selected':'' ?>>TI</option>
          <option value="RC" <?= ($est['tipo_doc']??'')==='RC'?'selected':'' ?>>RC</option>
          <option value="CC" <?= ($est['tipo_doc']??'')==='CC'?'selected':'' ?>>CC</option>
          <option value="NUIP" <?= ($est['tipo_doc']??'')==='NUIP'?'selected':'' ?>>NUIP</option>
          <option value="PA" <?= ($est['tipo_doc']??'')==='PA'?'selected':'' ?>>Pasaporte</option>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label small fw-semibold">Numero de documento</label>
        <input type="text" name="documento" class="form-control"
               value="<?= htmlspecialchars($est['documento'] ?? '') ?>">
      </div>
      <div class="col-md-5">
        <label class="form-label small fw-semibold">Fecha de nacimiento</label>
        <input type="date" name="fecha_nac" class="form-control"
               value="<?= $est['fecha_nac'] ?? '' ?>" onchange="calcularEdad(this.value)">
        <div class="form-text" id="edadDisplay"><?= $edad ?></div>
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold">G&eacute;nero</label>
        <select name="genero" class="form-select">
          <option value="">--</option>
          <option value="M" <?= ($est['genero']??'')==='M'?'selected':'' ?>>Masculino</option>
          <option value="F" <?= ($est['genero']??'')==='F'?'selected':'' ?>>Femenino</option>
          <option value="O" <?= ($est['genero']??'')==='O'?'selected':'' ?>>Otro</option>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold">RH</label>
        <select name="rh" class="form-select">
          <option value="">--</option>
          <?php foreach (['A+','A-','B+','B-','AB+','AB-','O+','O-'] as $rh): ?>
            <option value="<?= $rh ?>" <?= ($est['rh']??'')===$rh?'selected':'' ?>><?= $rh ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6">
        <label class="form-label small fw-semibold">Direcci&oacute;n</label>
        <input type="text" name="direccion" class="form-control" placeholder="Calle / Carrera"
               value="<?= htmlspecialchars($est['direccion'] ?? '') ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold">Barrio</label>
        <input type="text" name="barrio" class="form-control"
               value="<?= htmlspecialchars($est['barrio'] ?? '') ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold">Ciudad</label>
        <input type="text" name="ciudad" class="form-control"
               value="<?= htmlspecialchars($est['ciudad'] ?? 'Bogota') ?>">
      </div>
    </div>
  </div>

  <!-- Datos medicos -->
  <div class="sc">
    <div class="sec-title" style="background:#fff1f2;color:#be123c">
      <i class="bi bi-heart-pulse-fill"></i>Datos M&eacute;dicos y de Seguridad
    </div>
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label small fw-semibold">EPS / Aseguradora<span class="req">*</span></label>
        <input type="text" name="eps" class="form-control" required
               placeholder="Ej: Sanitas, Compensar, Sura..."
               value="<?= htmlspecialchars($est['eps'] ?? '') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label small fw-semibold">N&uacute;m. afiliaci&oacute;n / carnet</label>
        <input type="text" name="num_seguro" class="form-control"
               placeholder="Numero de carnet o poliza"
               value="<?= htmlspecialchars($est['num_seguro'] ?? '') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label small fw-semibold">Alergias</label>
        <input type="text" name="alergias" class="form-control"
               placeholder="Medicamentos, alimentos, etc."
               value="<?= htmlspecialchars($est['alergias'] ?? '') ?>">
      </div>
      <div class="col-md-6">
        <label class="form-label small fw-semibold">Condici&oacute;n m&eacute;dica especial</label>
        <input type="text" name="condicion_medica" class="form-control"
               placeholder="Asma, epilepsia, etc. (Ninguna si no aplica)"
               value="<?= htmlspecialchars($est['condicion_medica'] ?? '') ?>">
      </div>
    </div>
  </div>

  <!-- Colegio -->
  <div class="sc">
    <div class="sec-title" style="background:#faf5ff;color:#6b21a8">
      <i class="bi bi-building"></i>Colegio donde Estudia
    </div>
    <div class="row g-3">
      <div class="col-md-6">
        <label class="form-label small fw-semibold">Nombre del colegio</label>
        <input type="text" name="colegio_ext" class="form-control"
               value="<?= htmlspecialchars($est['colegio_ext'] ?? '') ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold">Grado</label>
        <input type="text" name="grado_ext" class="form-control" placeholder="Ej: 5B"
               value="<?= htmlspecialchars($est['grado_ext'] ?? '') ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label small fw-semibold">Jornada</label>
        <select name="jornada_ext" class="form-select">
          <option value="">--</option>
          <option value="manana"  <?= ($est['jornada_ext']??'')==='manana'?'selected':'' ?>>Manana</option>
          <option value="tarde"   <?= ($est['jornada_ext']??'')==='tarde'?'selected':'' ?>>Tarde</option>
          <option value="unica"   <?= ($est['jornada_ext']??'')==='unica'?'selected':'' ?>>Unica</option>
        </select>
      </div>
    </div>
  </div>

  <!-- Notas -->
  <div class="sc">
    <label class="form-label small fw-semibold">Observaciones adicionales</label>
    <textarea name="notas" class="form-control" rows="2"
              placeholder="Informacion adicional relevante..."><?= htmlspecialchars($est['notas'] ?? '') ?></textarea>
  </div>

  <!-- AUTORIZACIONES -->
  <div class="sc" id="secAutorizaciones">
    <div class="sec-title" style="background:#f0fdf4;color:#166534">
      <i class="bi bi-shield-check-fill"></i>Autorizaciones y Pol&iacute;tica de Datos
    </div>

    <!-- Autorizacion fotos/videos -->
    <div class="auth-box mb-3 <?= ($est['autorizacion_foto']??0)?'firmado':'' ?>" id="boxFoto">
      <div class="d-flex align-items-start gap-3">
        <div class="form-check mt-1">
          <input class="form-check-input" type="checkbox" name="autorizacion_foto"
                 id="chkFoto" value="1" <?= ($est['autorizacion_foto']??0)?'checked':'' ?>
                 onchange="this.closest('.auth-box').classList.toggle('firmado',this.checked)">
        </div>
        <div>
          <div class="fw-bold small">AUTORIZACI&Oacute;N USO DE IM&Aacute;GENES Y VIDEOS</div>
          <div class="text-muted" style="font-size:.78rem;margin-top:.25rem">
            Autorizo expresamente a <strong>ROBOTSchool Colombia</strong> para tomar y usar
            fotograf&iacute;as y videos de mi hijo/a o representado/a durante las actividades acad&eacute;micas,
            competencias y eventos, &uacute;nicamente para fines de comunicaci&oacute;n institucional,
            redes sociales propias, material did&aacute;ctico y promoci&oacute;n de ROBOTSchool.
            Las im&aacute;genes NO ser&aacute;n cedidas a terceros ni usadas con fines comerciales externos.
          </div>
          <button type="button" class="btn btn-link btn-sm p-0 mt-1" style="font-size:.75rem"
                  data-bs-toggle="modal" data-bs-target="#modalFotos">
            Ver pol&iacute;tica completa &rarr;
          </button>
        </div>
      </div>
    </div>

    <!-- Autorizacion datos personales -->
    <div class="auth-box <?= ($est['autorizacion_datos']??1)?'firmado':'' ?>" id="boxDatos">
      <div class="d-flex align-items-start gap-3">
        <div class="form-check mt-1">
          <input class="form-check-input" type="checkbox" name="autorizacion_datos"
                 id="chkDatos" value="1" <?= ($est['autorizacion_datos']??1)?'checked':'' ?>
                 onchange="this.closest('.auth-box').classList.toggle('firmado',this.checked)">
        </div>
        <div>
          <div class="fw-bold small">AUTORIZACI&Oacute;N TRATAMIENTO DE DATOS PERSONALES</div>
          <div class="text-muted" style="font-size:.78rem;margin-top:.25rem">
            Autorizo a <strong>ROBOTSchool Colombia</strong> para recolectar, almacenar y usar los datos
            personales del estudiante y su acudiente para la gesti&oacute;n acad&eacute;mica, administrativa y
            de comunicaci&oacute;n, conforme a la <strong>Ley 1581 de 2012</strong> y el Decreto 1377 de 2013
            de protecci&oacute;n de datos personales de Colombia.
          </div>
          <button type="button" class="btn btn-link btn-sm p-0 mt-1" style="font-size:.75rem"
                  data-bs-toggle="modal" data-bs-target="#modalDatos">
            Ver pol&iacute;tica completa &rarr;
          </button>
        </div>
      </div>
    </div>

  </div>

  <div class="d-flex gap-2">
    <button type="submit" class="btn btn-primary fw-bold flex-grow-1">
      <i class="bi bi-save me-1"></i><?= $est ? 'Guardar Cambios' : 'Registrar Estudiante' ?>
    </button>
    <a href="estudiantes.php" class="btn btn-outline-secondary">Cancelar</a>
  </div>

</div>
</div>
</form>

<!-- ══ MODAL: POLITICA FOTOGRAFIAS ══ -->
<div class="modal fade" id="modalFotos" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content" style="border-radius:14px">
      <div class="modal-header" style="background:#1e293b;color:#fff;border-radius:14px 14px 0 0">
        <h5 class="modal-title fw-bold">
          <i class="bi bi-camera-video me-2"></i>
          Pol&iacute;tica de Uso de Fotograf&iacute;as y Videos
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body politica-text p-4">
        <div style="background:#eff6ff;border-left:4px solid #185FA5;padding:.75rem 1rem;border-radius:0 8px 8px 0;margin-bottom:1.5rem">
          <strong>ROBOTSchool Colombia</strong> &mdash; NIT: [NIT ROBOTSchool]<br>
          Bogot&aacute;, Colombia &middot; robotschool.com.co &middot; 318 654 1859
        </div>

        <h6>1. Objeto de la Autorizaci&oacute;n</h6>
        <p>El acudiente autoriza a ROBOTSchool Colombia para capturar, almacenar y publicar im&aacute;genes
        fotogr&aacute;ficas y material audiovisual del estudiante durante su participaci&oacute;n en actividades
        acad&eacute;micas, talleres, competencias, eventos y dem&aacute;s actividades organizadas por
        ROBOTSchool Colombia.</p>

        <h6>2. Finalidades Autorizadas</h6>
        <p>Las im&aacute;genes y videos podr&aacute;n ser utilizados &uacute;nicamente para:</p>
        <ul style="font-size:.82rem;line-height:2">
          <li>Publicaciones en redes sociales oficiales de ROBOTSchool (Instagram, Facebook, YouTube, TikTok)</li>
          <li>Material did&aacute;ctico y educativo interno</li>
          <li>Portafolio institucional y presentaciones corporativas</li>
          <li>Comunicaciones a padres de familia y acudientes</li>
          <li>Campa&ntilde;as de difusi&oacute;n de la educaci&oacute;n en rob&oacute;tica y tecnolog&iacute;a</li>
          <li>Registro de logros y progreso acad&eacute;mico del estudiante</li>
        </ul>

        <h6>3. Restricciones</h6>
        <p>ROBOTSchool Colombia <strong>NO</strong> podr&aacute;:</p>
        <ul style="font-size:.82rem;line-height:2">
          <li>Ceder, vender o transferir las im&aacute;genes a terceros con fines comerciales</li>
          <li>Usar las im&aacute;genes en publicidad de terceras marcas o empresas</li>
          <li>Publicar im&aacute;genes que puedan comprometer la dignidad o integridad del menor</li>
          <li>Compartir im&aacute;genes con datos personales sensibles visibles (documentos, direcciones)</li>
        </ul>

        <h6>4. Duraci&oacute;n</h6>
        <p>Esta autorizaci&oacute;n tiene vigencia durante todo el tiempo que el estudiante permanezca
        matriculado en ROBOTSchool, y podr&aacute; ser revocada en cualquier momento mediante comunicaci&oacute;n
        escrita dirigida a info@robotschool.com.co.</p>

        <h6>5. Revocaci&oacute;n</h6>
        <p>El acudiente podr&aacute; revocar esta autorizaci&oacute;n en cualquier momento sin necesidad
        de justificaci&oacute;n, contact&aacute;ndonos en info@robotschool.com.co o al 318 654 1859.
        La revocaci&oacute;n no afectar&aacute; los usos ya realizados con anterioridad.</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal"
                onclick="document.getElementById('chkFoto').checked=true;document.getElementById('boxFoto').classList.add('firmado')">
          <i class="bi bi-check-lg me-1"></i>Aceptar y cerrar
        </button>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<!-- ══ MODAL: POLITICA DATOS PERSONALES ══ -->
<div class="modal fade" id="modalDatos" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content" style="border-radius:14px">
      <div class="modal-header" style="background:#1e293b;color:#fff;border-radius:14px 14px 0 0">
        <h5 class="modal-title fw-bold">
          <i class="bi bi-shield-lock me-2"></i>
          Pol&iacute;tica de Protecci&oacute;n de Datos Personales
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body politica-text p-4">
        <div style="background:#eff6ff;border-left:4px solid #185FA5;padding:.75rem 1rem;border-radius:0 8px 8px 0;margin-bottom:1.5rem">
          <strong>Ley 1581 de 2012 &middot; Decreto 1377 de 2013</strong><br>
          Protecci&oacute;n de Datos Personales &mdash; Colombia<br>
          <strong>Responsable:</strong> ROBOTSchool Colombia &middot; info@robotschool.com.co
        </div>

        <h6>1. Responsable del Tratamiento</h6>
        <p>ROBOTSchool Colombia, con domicilio en Bogot&aacute;, Colombia, es responsable del tratamiento
        de los datos personales recopilados mediante este formulario de matr&iacute;cula.</p>

        <h6>2. Datos Recopilados</h6>
        <ul style="font-size:.82rem;line-height:2">
          <li>Datos de identificaci&oacute;n del estudiante (nombre, documento, fecha de nacimiento)</li>
          <li>Datos de contacto del acudiente (nombre, tel&eacute;fono, email, direcci&oacute;n)</li>
          <li>Datos de salud (EPS, grupo sangu&iacute;neo, alergias, condiciones m&eacute;dicas)</li>
          <li>Datos acad&eacute;micos (colegio, grado, jornada)</li>
          <li>Im&aacute;genes y material audiovisual (sujeto a autorizaci&oacute;n separada)</li>
        </ul>

        <h6>3. Finalidades del Tratamiento</h6>
        <ul style="font-size:.82rem;line-height:2">
          <li>Gesti&oacute;n del proceso de matr&iacute;cula y prestaci&oacute;n del servicio educativo</li>
          <li>Comunicaciones administrativas y acad&eacute;micas con el acudiente</li>
          <li>Atenci&oacute;n de emergencias m&eacute;dicas durante las actividades</li>
          <li>Facturaci&oacute;n y cobro del servicio educativo</li>
          <li>Mejora continua de los programas acad&eacute;micos</li>
          <li>Env&iacute;o de informaci&oacute;n sobre actividades, eventos y nuevos programas</li>
        </ul>

        <h6>4. Derechos del Titular</h6>
        <p>Como titular de los datos, el acudiente tiene derecho a:</p>
        <ul style="font-size:.82rem;line-height:2">
          <li><strong>Acceso:</strong> conocer qu&eacute; datos tenemos sobre el estudiante</li>
          <li><strong>Correcci&oacute;n:</strong> solicitar actualizaci&oacute;n de datos incorrectos</li>
          <li><strong>Supresi&oacute;n:</strong> pedir eliminaci&oacute;n de datos cuando no sean necesarios</li>
          <li><strong>Revocaci&oacute;n:</strong> retirar el consentimiento en cualquier momento</li>
          <li><strong>Queja:</strong> presentar reclamaciones ante la Superintendencia de Industria y Comercio (SIC)</li>
        </ul>

        <h6>5. Datos Sensibles</h6>
        <p>Los datos de salud (EPS, grupo sangu&iacute;neo, alergias) son considerados datos sensibles
        y ser&aacute;n tratados con especial protecci&oacute;n, siendo usados &uacute;nicamente para
        garantizar la seguridad y bienestar del estudiante durante las actividades.</p>

        <h6>6. Seguridad</h6>
        <p>ROBOTSchool Colombia adopta medidas t&eacute;cnicas y administrativas para proteger los datos
        personales contra acceso no autorizado, p&eacute;rdida o alteraci&oacute;n.</p>

        <h6>7. Contacto</h6>
        <p>Para ejercer sus derechos o resolver dudas: <strong>info@robotschool.com.co</strong>
        &middot; 318 654 1859 &middot; Bogot&aacute;, Colombia.</p>

        <div style="background:#fef9c3;border:1px solid #fde047;border-radius:8px;padding:.75rem;margin-top:1rem;font-size:.78rem">
          <strong>&#x26A0; Condiciones especiales:</strong> Los datos de menores de edad son tratados
          con especial protecci&oacute;n. El acudiente garantiza ser el representante legal del menor
          y tener capacidad legal para otorgar esta autorizaci&oacute;n. ROBOTSchool no compartir&aacute;
          datos del menor con terceros sin autorizaci&oacute;n expresa del acudiente.
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal"
                onclick="document.getElementById('chkDatos').checked=true;document.getElementById('boxDatos').classList.add('firmado')">
          <i class="bi bi-check-lg me-1"></i>Acepto la pol&iacute;tica
        </button>
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script>
var AVATAR_COLORS = <?= json_encode($AVATAR_COLORS) ?>;
var currentColor  = <?= json_encode($avatarColor) ?>;

function getInitials() {
    var n = (document.querySelector('[name=nombres]')?.value || '').trim();
    var a = (document.querySelector('[name=apellidos]')?.value || '').trim();
    return ((n[0]||'')+(a[0]||'')).toUpperCase() || '?';
}

function renderAvatar(initials, color) {
    var svg = document.getElementById('avatarSvg');
    if (!svg) return;
    svg.innerHTML =
        '<circle cx="50" cy="50" r="50" fill="'+color+'"/>' +
        '<text x="50" y="50" text-anchor="middle" dominant-baseline="central" '+
        'fill="white" font-size="36" font-weight="700" font-family="Arial">'+
        initials+'</text>';
}

function actualizarAvatar() {
    renderAvatar(getInitials(), currentColor);
}

function setAvatarColor(color) {
    currentColor = color;
    document.getElementById('avatarColorInput').value = color;
    renderAvatar(getInitials(), color);
    document.querySelectorAll('.color-dot').forEach(function(d) {
        d.classList.toggle('sel', d.style.background === color ||
            d.style.backgroundColor === color);
    });
}

function previewAvatar(input) {
    if (!input.files || !input.files[0]) return;
    var reader = new FileReader();
    reader.onload = function(e) {
        var svg = document.getElementById('avatarSvg');
        // Reemplazar SVG con img
        svg.outerHTML = '<img src="'+e.target.result+'" id="avatarSvg" '+
            'style="width:100px;height:100px;border-radius:50%;object-fit:cover;cursor:pointer" '+
            'onclick="document.getElementById(\'fileAvatar\').click()" alt="Avatar">';
    };
    reader.readAsDataURL(input.files[0]);
}

function calcularEdad(fechaNac) {
    if (!fechaNac) return;
    var hoy  = new Date();
    var nac  = new Date(fechaNac);
    var edad = hoy.getFullYear() - nac.getFullYear();
    var m    = hoy.getMonth() - nac.getMonth();
    if (m < 0 || (m === 0 && hoy.getDate() < nac.getDate())) edad--;
    document.getElementById('edadDisplay').textContent = edad + ' años';
}

// Inicializar
<?php if (!$est): ?>
document.querySelector('[name=nombres]')?.addEventListener('input', actualizarAvatar);
document.querySelector('[name=apellidos]')?.addEventListener('input', actualizarAvatar);
<?php endif; ?>
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
