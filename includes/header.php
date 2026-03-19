<?php
defined('APP_URL') or die('Acceso directo no permitido');

$_user  = Auth::user();
$_rol   = Auth::getRol();
$_rolId = Auth::getRolId();
$_menu  = Auth::menuItems();
$_np    = Auth::notificacionesPendientes();
$_conv  = Auth::conveniosPendientes();
$_rm    = Auth::getRolMeta();
$pageTitle = $pageTitle ?? (defined('APP_NAME') ? APP_NAME : 'ROBOTSchool');

$_nombreUser = !empty($_user['nombre']) ? $_user['nombre'] : ($_user['name'] ?? '');
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($pageTitle) ?> &mdash; <?= defined('APP_NAME') ? APP_NAME : 'ROBOTSchool' ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
  <link href="<?= APP_URL ?>/assets/css/app.css" rel="stylesheet">
  <style>
    /* Badge rol en sidebar */
    .rol-badge{display:inline-flex;align-items:center;gap:.3rem;font-size:.65rem;font-weight:700;padding:.15rem .5rem;border-radius:20px;background:<?= $_rm['color'] ?? '#185FA5' ?>;color:#fff;margin-top:.25rem}
    /* Notif badge */
    .notif-dot{position:relative}
    .notif-dot .dot{position:absolute;top:-2px;right:-2px;width:8px;height:8px;background:#ef4444;border-radius:50%;border:1.5px solid #1e2a3a}
    /* ── Sidebar colapsado: ocultar elementos extra ── */
    #sidebar.collapsed .sidebar-header img,
    #sidebar.collapsed .sidebar-header .text-white-50,
    #sidebar.collapsed .sidebar-user .rol-badge,
    #sidebar.collapsed .sidebar-user div { display:none; }
    #sidebar.collapsed .sidebar-user .avatar-circle,
    #sidebar.collapsed .sidebar-user img[style*="border-radius"] { display:flex !important; margin:0 auto; }
    /* Transición suave del contenido principal */
    #page-content { transition: margin-left .25s ease; }
  </style>
</head>
<body>

<div class="d-flex" id="wrapper">

<!-- ══ SIDEBAR ══ -->
<nav id="sidebar" class="bg-sidebar text-white">

  <!-- Logo -->
  <div class="sidebar-header p-3 border-bottom border-secondary text-center">
    <img src="<?= APP_URL ?>/assets/img/logo_blanco.png"
         alt="ROBOTSchool" class="img-fluid" style="max-height:48px;"
         onerror="this.outerHTML='<div class=&quot;fw-bold text-white fs-5&quot;>ROBOT<span style=&quot;color:#ff6b00&quot;>School</span></div>'">
    <div class="text-white-50 mt-1" style="font-size:.62rem;letter-spacing:.06em">SISTEMA DE GESTI&Oacute;N</div>
  </div>

  <!-- Usuario y rol -->
  <div class="sidebar-user p-3 border-bottom border-secondary">
    <div class="d-flex align-items-center gap-2">
      <?php if (!empty($_SESSION['user_avatar'])): ?>
        <img src="<?= htmlspecialchars($_SESSION['user_avatar']) ?>"
             style="width:34px;height:34px;border-radius:50%;object-fit:cover;flex-shrink:0" alt="">
      <?php else: ?>
        <div class="avatar-circle" style="background:<?= $_rm['color'] ?? '#3a72e8' ?>">
          <i class="bi bi-person-fill"></i>
        </div>
      <?php endif; ?>
      <div>
        <div class="fw-bold small text-white"><?= htmlspecialchars($_nombreUser) ?></div>
        <div class="rol-badge"><i class="bi <?= $_rm['icon'] ?? 'bi-circle' ?>"></i><?= $_rm['label'] ?? ucfirst($_rol) ?></div>
      </div>
    </div>
  </div>

  <!-- ── MENU DINAMICO POR ROL ── -->
  <ul class="nav flex-column p-2 mt-1">

    <!-- Dashboard -->
    <?php if (in_array('dashboard', $_menu)): ?>
    <li class="nav-item">
      <a class="nav-link sidebar-link <?= ($activeMenu??'')==='dashboard'?'active':'' ?>"
         href="<?= APP_URL ?>/dashboard.php">
        <i class="bi bi-speedometer2"></i> <span>Dashboard</span>
      </a>
    </li>
    <?php endif; ?>

    <!-- ── OPERACIONES (Admin, Gerencia, Produccion) ── -->
    <?php if (array_intersect(['inventario','kits','colegios'], $_menu)): ?>
    <li class="nav-item"><div class="sidebar-divider">OPERACIONES</div></li>
    <?php endif; ?>

    <?php if (in_array('inventario', $_menu)): ?>
    <li class="nav-item">
      <a class="nav-link sidebar-link <?= ($activeMenu??'')==='elementos'?'active':'' ?>"
         href="<?= APP_URL ?>/modules/elementos/index.php">
        <i class="bi bi-cpu"></i> <span>Elementos</span>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link sidebar-link <?= ($activeMenu??'')==='movimientos'?'active':'' ?>"
         href="<?= APP_URL ?>/modules/inventario/movimientos.php">
        <i class="bi bi-arrow-left-right"></i> <span>Movimientos</span>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link sidebar-link <?= ($activeMenu??'')==='barcodes'?'active':'' ?>"
         href="<?= APP_URL ?>/modules/barcodes/index.php">
        <i class="bi bi-upc-scan"></i> <span>C&oacute;digos de Barras</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('kits', $_menu)): ?>
    <li class="nav-item">
      <a class="nav-link sidebar-link <?= ($activeMenu??'')==='kits'?'active':'' ?>"
         href="<?= APP_URL ?>/modules/kits/index.php">
        <i class="bi bi-bag-check"></i> <span>Kits</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('colegios', $_menu)): ?>
    <li class="nav-item">
      <a class="nav-link sidebar-link <?= ($activeMenu??'')==='colegios'?'active':'' ?>"
         href="<?= APP_URL ?>/modules/colegios/">
        <i class="bi bi-building"></i> <span>Colegios</span>
      </a>
    </li>
    <?php endif; ?>

    <!-- ── IMPORTACIONES (solo Admin/Gerencia) ── -->
    <?php if (in_array('pedidos', $_menu)): ?>
    <li class="nav-item"><div class="sidebar-divider">IMPORTACIONES</div></li>
    <li class="nav-item">
      <a class="nav-link sidebar-link <?= ($activeMenu??'')==='pedidos'?'active':'' ?>"
         href="<?= APP_URL ?>/modules/importaciones/index.php">
        <i class="bi bi-airplane"></i> <span>Pedidos</span>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link sidebar-link <?= ($activeMenu??'')==='proveedores'?'active':'' ?>"
         href="<?= APP_URL ?>/modules/importaciones/proveedores.php">
        <i class="bi bi-shop"></i> <span>Proveedores</span>
      </a>
    </li>
    <?php endif; ?>

    <!-- ── TIENDA & PRODUCCION ── -->
    <?php if (array_intersect(['pedidos_tienda','produccion'], $_menu)): ?>
    <li class="nav-item"><div class="sidebar-divider">TIENDA & PRODUCCION</div></li>
    <?php endif; ?>

    <?php if (in_array('pedidos_tienda', $_menu)): ?>
    <li class="nav-item">
      <a class="nav-link sidebar-link <?= ($activeMenu??'')==='pedidos_tienda'?'active':'' ?>"
         href="<?= APP_URL ?>/modules/pedidos_tienda/">
        <i class="bi bi-cart-check"></i> <span>Pedidos Tienda</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('produccion', $_menu)): ?>
    <li class="nav-item">
      <a class="nav-link sidebar-link notif-dot <?= ($activeMenu??'')==='produccion'?'active':'' ?>"
         href="<?= APP_URL ?>/modules/produccion/">
        <i class="bi bi-tools"></i> <span>Produccion</span>
        <?php if ($_np > 0): ?>
          <span class="badge bg-danger ms-auto" style="font-size:.6rem"><?= $_np ?></span>
        <?php endif; ?>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('alistamiento', $_menu)): ?>
    <li class="nav-item">
      <a class="nav-link sidebar-link <?= ($activeMenu??'')==='alistamiento'?'active':'' ?>"
         href="<?= APP_URL ?>/modules/alistamiento/">
        <i class="bi bi-box-seam"></i> <span>Alistamiento</span>
      </a>
    </li>
    <?php endif; ?>

    <!-- ── DESPACHOS (Admin/Gerencia) ── -->
    <?php if (in_array('despachos', $_menu)): ?>
    <li class="nav-item">
      <a class="nav-link sidebar-link <?= ($activeMenu??'')==='despachos'?'active':'' ?>"
         href="<?= APP_URL ?>/modules/despachos/">
        <i class="bi bi-truck"></i> <span>Despachos</span>
      </a>
    </li>
    <?php endif; ?>

    <!-- ── ESCUELA ── -->
    <?php if (array_intersect(['cursos','matriculas','pagos','academico'], $_menu)): ?>
    <li class="nav-item"><div class="sidebar-divider">ESCUELA</div></li>
    <?php endif; ?>

    <?php if (in_array('cursos', $_menu)): ?>
    <li class="nav-item">
      <a class="nav-link sidebar-link <?= ($activeMenu??'')==='cursos'?'active':'' ?>"
         href="<?= APP_URL ?>/modules/cursos/index.php">
        <i class="bi bi-collection-play"></i> <span>Cursos</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('matriculas', $_menu)): ?>
    <li class="nav-item">
      <a class="nav-link sidebar-link <?= ($activeMenu??'')==='matriculas'?'active':'' ?>"
         href="<?= APP_URL ?>/modules/matriculas/index.php">
        <i class="bi bi-mortarboard"></i> <span>Matr&iacute;culas</span>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link sidebar-link <?= ($activeMenu??'')==='estudiantes'?'active':'' ?>"
         href="<?= APP_URL ?>/modules/matriculas/estudiantes.php">
        <i class="bi bi-people"></i> <span>Estudiantes</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('pagos', $_menu)): ?>
    <li class="nav-item">
      <a class="nav-link sidebar-link <?= ($activeMenu??'')==='pagos'?'active':'' ?>"
         href="<?= APP_URL ?>/modules/matriculas/pagos.php">
        <i class="bi bi-cash-coin"></i> <span>Pagos Cursos</span>
      </a>
    </li>
    <?php endif; ?>

    <?php if (in_array('academico', $_menu)): ?>
    <li class="nav-item">
      <a class="nav-link sidebar-link <?= ($activeMenu??'')==='academico'?'active':'' ?>"
         href="<?= APP_URL ?>/modules/academico/index.php">
        <i class="bi bi-journal-bookmark"></i> <span>Acad&eacute;mico LMS</span>
      </a>
    </li>
    <?php endif; ?>

    <!-- ── COMERCIAL ── -->
    <?php if (array_intersect(['comercial','convenios'], $_menu)): ?>
    <li class="nav-item"><div class="sidebar-divider">COMERCIAL</div></li>
    <li class="nav-item">
      <a class="nav-link sidebar-link <?= ($activeMenu??'')==='comercial'?'active':'' ?>"
         href="<?= APP_URL ?>/modules/comercial/index.php">
        <i class="bi bi-briefcase"></i> <span>Requerimientos</span>
        <?php if ($_conv > 0): ?>
          <span class="badge bg-warning text-dark ms-auto" style="font-size:.6rem"><?= $_conv ?></span>
        <?php endif; ?>
      </a>
    </li>
    <?php endif; ?>

    <!-- ── REPORTES ── -->
    <?php if (in_array('reportes', $_menu)): ?>
    <li class="nav-item"><div class="sidebar-divider">REPORTES</div></li>
    <li class="nav-item">
      <a class="nav-link sidebar-link <?= ($activeMenu??'')==='reportes'?'active':'' ?>"
         href="<?= APP_URL ?>/modules/reportes/">
        <i class="bi bi-bar-chart-line"></i> <span>Reportes</span>
      </a>
    </li>
    <?php endif; ?>

    <!-- ── ADMINISTRACION ── -->
    <?php if (in_array('usuarios', $_menu)): ?>
    <li class="nav-item"><div class="sidebar-divider">ADMINISTRACI&Oacute;N</div></li>
    <li class="nav-item">
      <a class="nav-link sidebar-link <?= ($activeMenu??'')==='usuarios'?'active':'' ?>"
         href="<?= APP_URL ?>/modules/usuarios/index.php">
        <i class="bi bi-people"></i> <span>Usuarios</span>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link sidebar-link <?= ($activeMenu??'')==='categorias'?'active':'' ?>"
         href="<?= APP_URL ?>/modules/elementos/categorias.php">
        <i class="bi bi-tags"></i> <span>Categor&iacute;as</span>
      </a>
    </li>
    <li class="nav-item">
      <a class="nav-link sidebar-link <?= ($activeMenu??'')==='config'?'active':'' ?>"
         href="<?= APP_URL ?>/modules/auth/config.php">
        <i class="bi bi-gear"></i> <span>Configuraci&oacute;n</span>
      </a>
    </li>
    <?php endif; ?>

    <!-- Logout siempre -->
    <li class="nav-item mt-2 border-top border-secondary pt-2">
      <a class="nav-link sidebar-link text-danger"
         href="<?= APP_URL ?>/modules/auth/logout.php">
        <i class="bi bi-box-arrow-right"></i> <span>Cerrar sesi&oacute;n</span>
      </a>
    </li>

  </ul>
</nav>
<!-- ══ FIN SIDEBAR ══ -->

<!-- ══ CONTENIDO ══ -->
<div id="page-content" class="flex-grow-1">

  <!-- Topbar -->
  <div class="topbar bg-white border-bottom px-3 d-flex align-items-center gap-3 shadow-sm">
    <button class="btn btn-sm btn-light" id="sidebarToggle" onclick="toggleSidebar()">
      <i class="bi bi-list fs-5"></i>
    </button>
    <img src="<?= APP_URL ?>/assets/img/logo_email.png"
         alt="ROBOTSchool" style="max-height:34px;"
         onerror="this.outerHTML='<span class=&quot;fw-bold&quot; style=&quot;color:#e53935&quot;>ROBOT<span style=&quot;color:#1a9c9c&quot;>School</span></span>'">
    <span class="text-muted small d-none d-lg-block ms-2" style="font-size:.75rem;border-left:1px solid #e2e8f0;padding-left:.75rem">
      <?= htmlspecialchars($pageTitle) ?>
    </span>
    <div class="ms-auto d-flex align-items-center gap-2">
      <?php if ($_conv > 0): ?>
      <a href="<?= APP_URL ?>/modules/comercial/index.php"
         class="btn btn-sm btn-warning fw-semibold" style="font-size:.75rem">
        <i class="bi bi-briefcase me-1"></i><?= $_conv ?> pendiente(s)
      </a>
      <?php endif; ?>
      <?php if ($_np > 0): ?>
      <a href="<?= APP_URL ?>/modules/produccion/"
         class="btn btn-sm btn-danger fw-semibold" style="font-size:.75rem">
        <i class="bi bi-tools me-1"></i><?= $_np ?> producci&oacute;n
      </a>
      <?php endif; ?>
      <span class="text-muted small d-none d-md-block"><?= htmlspecialchars($_nombreUser) ?></span>
    </div>
  </div>

  <!-- Aviso sin permiso -->
  <?php if (isset($_GET['err']) && $_GET['err']==='sin_permiso'): ?>
  <div class="alert alert-warning m-3 py-2 small">
    <i class="bi bi-shield-exclamation me-2"></i>
    No tienes permiso para acceder a ese m&oacute;dulo.
  </div>
  <?php endif; ?>

  <!-- Contenido del modulo -->
  <main class="p-3 p-md-4">

<script>
(function () {
    // Restaurar estado al cargar (solo desktop)
    if (window.innerWidth > 768 && localStorage.getItem('sidebarCollapsed') === '1') {
        var s = document.getElementById('sidebar');
        if (s) s.classList.add('collapsed');
    }
})();

function toggleSidebar() {
    var s = document.getElementById('sidebar');
    if (!s) return;
    if (window.innerWidth <= 768) {
        // Móvil: deslizar dentro/fuera
        s.classList.toggle('show');
    } else {
        // Desktop: colapsar a solo íconos
        s.classList.toggle('collapsed');
        localStorage.setItem('sidebarCollapsed', s.classList.contains('collapsed') ? '1' : '0');
    }
}
</script>
