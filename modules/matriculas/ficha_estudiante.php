<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();

$db = Database::get();
$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: estudiantes.php'); exit; }

$est = $db->query("SELECT * FROM estudiantes WHERE id=$id")->fetch();
if (!$est) { header('Location: estudiantes.php'); exit; }

// Matriculas activas
$matriculas = $db->query("
    SELECT m.*, eg.nombre AS grupo_nombre, eg.dia_semana, eg.hora_inicio, eg.hora_fin,
           ep.nombre AS programa_nombre
    FROM matriculas m
    JOIN escuela_grupos eg ON eg.id=m.grupo_id
    JOIN escuela_programas ep ON ep.id=eg.programa_id
    WHERE m.estudiante_id=$id AND m.estado IN ('activa','pendiente_pago')
    ORDER BY m.created_at DESC
")->fetchAll();

$logoPath = APP_ROOT . '/assets/img/logo_oficial.png';
$logoB64  = file_exists($logoPath) ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath)) : '';

$edad = '';
if (!empty($est['fecha_nac'])) {
    $edad = date_diff(date_create($est['fecha_nac']), date_create('today'))->y;
}

$DIAS = ['1'=>'Dom','2'=>'Lun','3'=>'Mar','4'=>'Mie','5'=>'Jue','6'=>'Vie','7'=>'Sab'];
$avatarColor = $est['avatar_color'] ?? '#185FA5';
$initials    = strtoupper(substr($est['nombres'],0,1).substr($est['apellidos'],0,1));
$fechaHoy    = date('d \d\e F \d\e Y');
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Ficha Estudiante — <?= htmlspecialchars($est['nombres'].' '.$est['apellidos']) ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:Arial,Helvetica,sans-serif;font-size:9.5pt;color:#1e293b;background:#f1f5f9}

.toolbar{
  position:fixed;top:0;left:0;right:0;z-index:100;
  background:#1e293b;color:#fff;
  padding:.5rem 1.5rem;display:flex;align-items:center;gap:.75rem
}
.toolbar h1{font-size:.85rem;font-weight:700;flex:1}
.btn-t{padding:.35rem .9rem;border-radius:6px;border:none;cursor:pointer;font-size:.8rem;font-weight:700}
.btn-green{background:#16a34a;color:#fff}
.btn-gris{background:#475569;color:#fff}

/* Hoja carta */
.hoja{
  width:21.59cm;min-height:27.94cm;background:#fff;
  margin:4.2rem auto 1.5rem;
  padding:.8cm 1cm;
  box-shadow:0 4px 24px rgba(0,0,0,.12);
}

/* Header */
.doc-header{
  display:flex;align-items:center;justify-content:space-between;
  border-bottom:3px solid #1e293b;padding-bottom:.4cm;margin-bottom:.4cm
}
.doc-logo{height:50px;object-fit:contain}
.doc-logo-ph{height:50px;background:#1e293b;color:#fff;padding:6px 12px;border-radius:6px;
  display:flex;align-items:center;font-size:12px;font-weight:900}
.doc-info{text-align:right;font-size:7.5pt;color:#64748b;line-height:1.6}
.doc-info strong{font-size:9pt;color:#1e293b;display:block}
.doc-title{text-align:center;margin-bottom:.35cm}
.doc-title h2{font-size:13pt;font-weight:700}
.doc-title p{font-size:7.5pt;color:#64748b;margin-top:2px}

/* Avatar en ficha */
.avatar-circle{
  width:70px;height:70px;border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  font-size:22pt;font-weight:700;color:#fff;flex-shrink:0
}

/* Secciones */
.seccion{margin-bottom:.4cm;page-break-inside:avoid}
.sec-header{
  background:#1e293b;color:#fff;font-size:8pt;font-weight:700;
  padding:.15cm .4cm;border-radius:4px 4px 0 0;letter-spacing:.04em
}
.sec-body{border:1px solid #e2e8f0;border-top:none;border-radius:0 0 4px 4px;padding:.3cm .4cm}
.grid2{display:grid;grid-template-columns:1fr 1fr;gap:.2cm .5cm}
.grid3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:.2cm .5cm}
.campo{padding:.12cm 0;border-bottom:.5px solid #f1f5f9}
.campo:last-child{border-bottom:none}
.campo-lbl{font-size:7pt;color:#94a3b8;font-weight:700;text-transform:uppercase;letter-spacing:.05em}
.campo-val{font-size:9pt;font-weight:600;color:#1e293b;margin-top:1px}
.campo-val.vacio{color:#cbd5e1;font-weight:400;font-style:italic}

/* Politicas */
.politica-box{border:1px solid #e2e8f0;border-radius:6px;padding:.35cm .5cm;margin-bottom:.3cm;font-size:8pt;line-height:1.6;color:#374151}
.politica-box h4{font-size:8.5pt;font-weight:700;color:#1e293b;margin-bottom:.2cm;display:flex;align-items:center;gap:.2cm}
.politica-item{display:flex;align-items:flex-start;gap:.2cm;margin-bottom:.1cm}
.politica-item::before{content:"•";color:#185FA5;font-weight:700;flex-shrink:0}

/* Firma */
.firma-area{display:grid;grid-template-columns:1fr 1fr;gap:1cm;margin-top:.4cm}
.firma-box{text-align:center}
.firma-linea{border-bottom:1.5px solid #1e293b;margin-bottom:.15cm;height:1.2cm}
.firma-lbl{font-size:8pt;color:#475569}
.firma-sub{font-size:7pt;color:#94a3b8;margin-top:2px}

/* Alertas medicas */
.alerta-med{background:#fff1f2;border:1px solid #fecdd3;border-radius:6px;padding:.25cm .4cm;margin-top:.2cm}
.alerta-med-title{font-size:8pt;font-weight:700;color:#be123c}

/* Autorizaciones */
.auth-check{display:flex;align-items:center;gap:.3cm;padding:.2cm .3cm;border-radius:4px;margin-bottom:.15cm}
.auth-check.si{background:#f0fdf4;border:1px solid #bbf7d0}
.auth-check.no{background:#fff1f2;border:1px solid #fecdd3}
.chk-box{width:14px;height:14px;border:2px solid #1e293b;border-radius:2px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:9pt;font-weight:700}
.chk-box.si{background:#16a34a;border-color:#16a34a;color:#fff}

/* Footer */
.doc-footer{margin-top:.4cm;padding-top:.3cm;border-top:1px solid #e2e8f0;
  display:flex;justify-content:space-between;font-size:7pt;color:#94a3b8}

@media print{
  body{background:#fff}
  .toolbar{display:none!important}
  .hoja{margin:0;box-shadow:none;width:100%;min-height:auto;padding:.7cm .9cm}
  @page{size:letter portrait;margin:0}
}
</style>
</head>
<body>

<div class="toolbar">
  <button class="btn-t btn-gris" onclick="history.back()">&larr; Volver</button>
  <h1>Ficha Estudiante &mdash; <?= htmlspecialchars($est['nombres'].' '.$est['apellidos']) ?></h1>
  <button class="btn-t btn-green" onclick="window.print()">&#128438; Imprimir / PDF</button>
  <span style="font-size:.72rem;color:#94a3b8">Ctrl+P &rarr; Carta &rarr; Sin m&aacute;rgenes</span>
</div>

<div class="hoja">

  <!-- Header -->
  <div class="doc-header">
    <div style="display:flex;align-items:center;gap:12px">
      <?php if ($logoB64): ?>
        <img src="<?= $logoB64 ?>" class="doc-logo" alt="ROBOTSchool">
      <?php else: ?>
        <div class="doc-logo-ph">ROBOTSchool</div>
      <?php endif; ?>
      <div>
        <div style="font-size:11pt;font-weight:800;color:#1e293b">ROBOTSchool Colombia</div>
        <div style="font-size:8pt;color:#64748b">Educaci&oacute;n en Rob&oacute;tica y Tecnolog&iacute;a</div>
      </div>
    </div>
    <div class="doc-info">
      <strong>FICHA DE MATR&Iacute;CULA</strong>
      Bogot&aacute;, Colombia &middot; robotschool.com.co<br>
      318 654 1859 &middot; info@robotschool.com.co<br>
      Fecha: <?= $fechaHoy ?>
    </div>
  </div>

  <!-- Avatar + datos basicos -->
  <div style="display:flex;align-items:center;gap:.6cm;margin-bottom:.4cm;padding:.3cm;background:#f8fafc;border-radius:8px;border:1px solid #e2e8f0">
    <?php if ($est['foto']): ?>
      <img src="<?= UPLOAD_URL.htmlspecialchars($est['foto']) ?>"
           style="width:70px;height:70px;border-radius:50%;object-fit:cover;flex-shrink:0" alt="">
    <?php else: ?>
      <div class="avatar-circle" style="background:<?= $avatarColor ?>"><?= $initials ?></div>
    <?php endif; ?>
    <div style="flex:1">
      <div style="font-size:14pt;font-weight:800;color:#1e293b">
        <?= htmlspecialchars(strtoupper($est['apellidos']).' '.$est['nombres']) ?>
      </div>
      <div style="font-size:9pt;color:#64748b;margin-top:3px">
        <?= htmlspecialchars(($est['tipo_doc']??'TI').' '.$est['documento']) ?>
        <?php if ($edad): ?>&nbsp;&middot;&nbsp;<?= $edad ?> a&ntilde;os<?php endif; ?>
        <?php if ($est['fecha_nac']): ?>&nbsp;&middot;&nbsp;Nac: <?= date('d/m/Y',strtotime($est['fecha_nac'])) ?><?php endif; ?>
        <?php if ($est['genero']): ?>&nbsp;&middot;&nbsp;<?= $est['genero']==='M'?'Masculino':($est['genero']==='F'?'Femenino':'Otro') ?><?php endif; ?>
        <?php if ($est['rh']): ?>&nbsp;&middot;&nbsp;RH: <strong><?= htmlspecialchars($est['rh']) ?></strong><?php endif; ?>
      </div>
      <?php if (!empty($matriculas)): ?>
      <div style="font-size:8pt;color:#185FA5;margin-top:4px">
        <?php foreach ($matriculas as $m): ?>
          <span style="background:#eff6ff;padding:1px 6px;border-radius:10px;margin-right:4px">
            &#9679; <?= htmlspecialchars($m['programa_nombre']) ?> &mdash; <?= htmlspecialchars($m['grupo_nombre']) ?>
          </span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="grid2" style="gap:.3cm">

  <!-- Datos personales -->
  <div class="seccion">
    <div class="sec-header">&#128100; DATOS PERSONALES</div>
    <div class="sec-body">
      <div class="grid2">
        <div class="campo"><div class="campo-lbl">Direcci&oacute;n</div>
          <div class="campo-val <?= empty($est['direccion'])?'vacio':'' ?>"><?= htmlspecialchars($est['direccion'] ?? 'Sin registrar') ?></div></div>
        <div class="campo"><div class="campo-lbl">Barrio</div>
          <div class="campo-val <?= empty($est['barrio'])?'vacio':'' ?>"><?= htmlspecialchars($est['barrio'] ?? 'Sin registrar') ?></div></div>
        <div class="campo"><div class="campo-lbl">Ciudad</div>
          <div class="campo-val"><?= htmlspecialchars($est['ciudad'] ?? 'Bogota') ?></div></div>
        <div class="campo"><div class="campo-lbl">Colegio</div>
          <div class="campo-val <?= empty($est['colegio_ext'])?'vacio':'' ?>"><?= htmlspecialchars($est['colegio_ext'] ?? 'Sin registrar') ?></div></div>
        <div class="campo"><div class="campo-lbl">Grado / Jornada</div>
          <div class="campo-val <?= empty($est['grado_ext'])?'vacio':'' ?>"><?= htmlspecialchars(($est['grado_ext']??'—').' / '.($est['jornada_ext']??'—')) ?></div></div>
        <div class="campo"><div class="campo-lbl">Como nos conoci&oacute;</div>
          <div class="campo-val <?= empty($est['como_conocio'])?'vacio':'' ?>"><?= htmlspecialchars($est['como_conocio'] ?? 'Sin registrar') ?></div></div>
      </div>
    </div>
  </div>

  <!-- Datos acudiente -->
  <div class="seccion">
    <div class="sec-header">&#128106; DATOS DEL ACUDIENTE</div>
    <div class="sec-body">
      <div class="grid2">
        <div class="campo" style="grid-column:1/-1"><div class="campo-lbl">Nombre completo</div>
          <div class="campo-val" style="font-size:10pt"><?= htmlspecialchars($est['acudiente'] ?? '') ?></div></div>
        <div class="campo"><div class="campo-lbl">Parentesco</div>
          <div class="campo-val"><?= htmlspecialchars($est['parentesco'] ?? '—') ?></div></div>
        <div class="campo"><div class="campo-lbl">C.C. Acudiente</div>
          <div class="campo-val <?= empty($est['doc_acudiente'])?'vacio':'' ?>"><?= htmlspecialchars($est['doc_acudiente'] ?? 'Sin registrar') ?></div></div>
        <div class="campo"><div class="campo-lbl">Tel&eacute;fono principal</div>
          <div class="campo-val" style="font-size:10pt;color:#185FA5"><?= htmlspecialchars($est['telefono'] ?? '—') ?></div></div>
        <div class="campo"><div class="campo-lbl">Tel&eacute;fono alternativo</div>
          <div class="campo-val <?= empty($est['telefono2'])?'vacio':'' ?>"><?= htmlspecialchars($est['telefono2'] ?? 'Sin registrar') ?></div></div>
        <div class="campo" style="grid-column:1/-1"><div class="campo-lbl">Email</div>
          <div class="campo-val <?= empty($est['email'])?'vacio':'' ?>"><?= htmlspecialchars($est['email'] ?? 'Sin registrar') ?></div></div>
      </div>
    </div>
  </div>

  </div><!-- /grid2 -->

  <!-- Datos medicos -->
  <div class="seccion">
    <div class="sec-header">&#10084;&#65039; DATOS M&Eacute;DICOS Y DE SEGURIDAD</div>
    <div class="sec-body">
      <div class="grid3">
        <div class="campo"><div class="campo-lbl">EPS / Aseguradora</div>
          <div class="campo-val" style="color:#dc2626"><?= htmlspecialchars($est['eps'] ?? 'Sin registrar') ?></div></div>
        <div class="campo"><div class="campo-lbl">N&uacute;m. Afiliaci&oacute;n</div>
          <div class="campo-val <?= empty($est['num_seguro'])?'vacio':'' ?>"><?= htmlspecialchars($est['num_seguro'] ?? 'Sin registrar') ?></div></div>
        <div class="campo"><div class="campo-lbl">Grupo Sangu&iacute;neo (RH)</div>
          <div class="campo-val" style="font-size:11pt;font-weight:800;color:#dc2626"><?= htmlspecialchars($est['rh'] ?? '—') ?></div></div>
      </div>
      <?php if ($est['alergias'] || $est['condicion_medica']): ?>
      <div class="alerta-med">
        <div class="alerta-med-title">&#9888; ALERTAS M&Eacute;DICAS IMPORTANTES</div>
        <?php if ($est['alergias']): ?>
          <div style="margin-top:3px"><strong>Alergias:</strong> <?= htmlspecialchars($est['alergias']) ?></div>
        <?php endif; ?>
        <?php if ($est['condicion_medica']): ?>
          <div style="margin-top:3px"><strong>Condici&oacute;n especial:</strong> <?= htmlspecialchars($est['condicion_medica']) ?></div>
        <?php endif; ?>
      </div>
      <?php else: ?>
      <div style="font-size:8pt;color:#94a3b8;margin-top:.2cm">Sin alergias ni condiciones m&eacute;dicas especiales registradas.</div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Cursos matriculados -->
  <?php if (!empty($matriculas)): ?>
  <div class="seccion">
    <div class="sec-header">&#127891; CURSOS MATRICULADOS</div>
    <div class="sec-body">
      <?php foreach ($matriculas as $m): ?>
      <div style="display:flex;align-items:center;gap:.5cm;padding:.18cm 0;border-bottom:.5px solid #f1f5f9">
        <div style="flex:1">
          <span style="font-size:9pt;font-weight:700"><?= htmlspecialchars($m['programa_nombre']) ?></span>
          &nbsp;&middot;&nbsp;
          <span style="font-size:8.5pt;color:#475569"><?= htmlspecialchars($m['grupo_nombre']) ?></span>
        </div>
        <div style="font-size:8pt;color:#185FA5">
          <?= $DIAS[$m['dia_semana']] ?? 'Sab' ?> <?= substr($m['hora_inicio'],0,5) ?>-<?= substr($m['hora_fin'],0,5) ?>
        </div>
        <div>
          <span style="font-size:7.5pt;padding:.1rem .4rem;border-radius:20px;background:<?= $m['estado']==='activa'?'#dcfce7':'#fef9c3' ?>;color:<?= $m['estado']==='activa'?'#166534':'#854d0e' ?>;font-weight:700">
            <?= ucfirst($m['estado']) ?>
          </span>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- AUTORIZACIONES -->
  <div class="seccion">
    <div class="sec-header">&#128737;&#65039; AUTORIZACIONES Y POL&Iacute;TICA DE DATOS</div>
    <div class="sec-body">

      <!-- Autorizacion fotos -->
      <div class="auth-check <?= ($est['autorizacion_foto']??0)?'si':'no' ?>">
        <div class="chk-box <?= ($est['autorizacion_foto']??0)?'si':'' ?>"><?= ($est['autorizacion_foto']??0)?'&#10003;':'' ?></div>
        <div style="flex:1">
          <div style="font-size:8.5pt;font-weight:700">AUTORIZACI&Oacute;N USO DE FOTOGRAF&Iacute;AS Y VIDEOS</div>
          <div style="font-size:7.5pt;color:#475569;margin-top:2px">
            Autorizo a ROBOTSchool Colombia para capturar y publicar im&aacute;genes y videos del estudiante
            durante actividades acad&eacute;micas, &uacute;nicamente para comunicaci&oacute;n institucional y
            promoci&oacute;n de ROBOTSchool. Las im&aacute;genes NO ser&aacute;n cedidas a terceros ni usadas
            con fines comerciales externos. Esta autorizaci&oacute;n puede revocarse en cualquier momento.
          </div>
        </div>
        <div style="font-size:8pt;font-weight:700;color:<?= ($est['autorizacion_foto']??0)?'#16a34a':'#dc2626' ?>;white-space:nowrap;margin-left:.3cm">
          <?= ($est['autorizacion_foto']??0) ? '&#10003; AUTORIZA' : '&#10007; NO AUTORIZA' ?>
        </div>
      </div>

      <!-- Autorizacion datos -->
      <div class="auth-check <?= ($est['autorizacion_datos']??0)?'si':'no' ?>" style="margin-top:.2cm">
        <div class="chk-box <?= ($est['autorizacion_datos']??0)?'si':'' ?>"><?= ($est['autorizacion_datos']??0)?'&#10003;':'' ?></div>
        <div style="flex:1">
          <div style="font-size:8.5pt;font-weight:700">AUTORIZACI&Oacute;N TRATAMIENTO DE DATOS PERSONALES (Ley 1581/2012)</div>
          <div style="font-size:7.5pt;color:#475569;margin-top:2px">
            Autorizo a ROBOTSchool Colombia para recolectar, almacenar y usar los datos personales del
            estudiante y acudiente para la gesti&oacute;n acad&eacute;mica, administrativa y de comunicaci&oacute;n,
            conforme a la Ley 1581 de 2012. Declaro conocer mis derechos de acceso, correcci&oacute;n,
            supresi&oacute;n y revocaci&oacute;n sobre mis datos. Los datos sensibles de salud ser&aacute;n
            usados &uacute;nicamente para garantizar la seguridad del menor.
          </div>
        </div>
        <div style="font-size:8pt;font-weight:700;color:<?= ($est['autorizacion_datos']??0)?'#16a34a':'#dc2626' ?>;white-space:nowrap;margin-left:.3cm">
          <?= ($est['autorizacion_datos']??0) ? '&#10003; AUTORIZA' : '&#10007; NO AUTORIZA' ?>
        </div>
      </div>

      <!-- Condiciones especiales -->
      <div style="background:#fef9c3;border:1px solid #fde047;border-radius:6px;padding:.25cm .4cm;margin-top:.2cm;font-size:7.5pt;color:#713f12">
        <strong>&#9888; Condiciones especiales:</strong>
        El acudiente firmante declara ser el representante legal del menor, tener plena capacidad legal,
        y que la informaci&oacute;n suministrada es verídica. El incumplimiento del pago puede generar
        suspensi&oacute;n temporal del servicio. ROBOTSchool no se hace responsable por objetos de valor
        traidos por el estudiante. En caso de emergencia m&eacute;dica, el acudiente autoriza la atenci&oacute;n
        m&eacute;dica de urgencias con la EPS registrada.
      </div>

    </div>
  </div>

  <!-- Firma -->
  <div class="firma-area">
    <div class="firma-box">
      <div class="firma-linea"></div>
      <div class="firma-lbl"><strong><?= htmlspecialchars($est['acudiente'] ?? 'Acudiente') ?></strong></div>
      <div class="firma-sub">C.C. <?= htmlspecialchars($est['doc_acudiente'] ?? '________________') ?></div>
      <div class="firma-sub"><?= htmlspecialchars($est['parentesco'] ?? 'Acudiente / Representante legal') ?></div>
    </div>
    <div class="firma-box">
      <div class="firma-linea"></div>
      <div class="firma-lbl"><strong>ROBOTSchool Colombia</strong></div>
      <div class="firma-sub">Coordinador de Sede</div>
      <div class="firma-sub">Fecha: <?= $fechaHoy ?></div>
    </div>
  </div>

  <!-- Footer -->
  <div class="doc-footer">
    <span>ROBOTSchool Colombia &middot; NIT: [NIT] &middot; Bogot&aacute;, Colombia</span>
    <span>Documento generado el <?= date('d/m/Y H:i') ?> &middot; Sistema ROBOTSchool v3.3</span>
  </div>

</div><!-- /hoja -->
</body>
</html>
