<?php
/**
 * modules/usuarios/roles.php
 * Gestión de permisos por rol — solo Gerencia.
 * Permite habilitar/deshabilitar módulos y acciones (ver/crear/editar/eliminar)
 * para cada rol. Los cambios se guardan en la tabla rol_permisos y aplican
 * en el próximo request de los usuarios afectados.
 */
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::requireRol('gerencia');

$db        = Database::get();
$pageTitle = 'Roles y Permisos';
$activeMenu= 'usuarios';
$error = $success = '';

// ── Módulos del sistema agrupados ────────────────────────────────
$GRUPOS = [
    'General' => [
        'dashboard'     => ['label' => 'Dashboard',       'icon' => 'bi-speedometer2'],
    ],
    'Operaciones' => [
        'inventario'    => ['label' => 'Inventario',      'icon' => 'bi-cpu'],
        'kits'          => ['label' => 'Kits',            'icon' => 'bi-bag-check'],
        'colegios'      => ['label' => 'Colegios',        'icon' => 'bi-building'],
        'categorias'    => ['label' => 'Categorías',      'icon' => 'bi-tags'],
    ],
    'Tienda & Producción' => [
        'pedidos_tienda'=> ['label' => 'Pedidos Tienda',  'icon' => 'bi-cart-check'],
        'produccion'    => ['label' => 'Producción',      'icon' => 'bi-tools'],
        'alistamiento'  => ['label' => 'Alistamiento',    'icon' => 'bi-box-seam'],
    ],
    'Compras & Logística' => [
        'pedidos'       => ['label' => 'Pedidos/Compras', 'icon' => 'bi-airplane'],
        'proveedores'   => ['label' => 'Proveedores',     'icon' => 'bi-shop'],
        'despachos'     => ['label' => 'Despachos',       'icon' => 'bi-truck'],
    ],
    'Academia' => [
        'cursos'        => ['label' => 'Cursos',          'icon' => 'bi-collection-play'],
        'matriculas'    => ['label' => 'Matrículas',      'icon' => 'bi-mortarboard'],
        'pagos'         => ['label' => 'Pagos Cursos',    'icon' => 'bi-cash-coin'],
        'academico'     => ['label' => 'Académico LMS',   'icon' => 'bi-journal-bookmark'],
    ],
    'Comercial' => [
        'comercial'     => ['label' => 'Requerimientos',  'icon' => 'bi-briefcase'],
        'convenios'     => ['label' => 'Convenios',       'icon' => 'bi-file-earmark-text'],
    ],
    'Administración' => [
        'reportes'      => ['label' => 'Reportes',        'icon' => 'bi-bar-chart-line'],
        'usuarios'      => ['label' => 'Usuarios',        'icon' => 'bi-people'],
        'config'        => ['label' => 'Configuración',   'icon' => 'bi-gear'],
    ],
];

// Metadatos de roles editables (no incluye Gerencia = 1)
$ROL_META = [
    2 => ['label' => 'Administración', 'color' => '#185FA5', 'icon' => 'bi-gear-fill'],
    3 => ['label' => 'Academia',       'color' => '#0f766e', 'icon' => 'bi-mortarboard-fill'],
    4 => ['label' => 'Producción',     'color' => '#b45309', 'icon' => 'bi-tools'],
    5 => ['label' => 'Comercial',      'color' => '#7c3aed', 'icon' => 'bi-briefcase-fill'],
    6 => ['label' => 'Consulta',       'color' => '#64748b', 'icon' => 'bi-eye-fill'],
];

// Aplanar todos los módulos para iterar en el POST
$allModKeys = [];
foreach ($GRUPOS as $mods) {
    foreach (array_keys($mods) as $k) $allModKeys[] = $k;
}

// ── POST: guardar permisos ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'guardar_permisos') {
    if (!Auth::csrfVerify($_POST['csrf'] ?? '')) die('CSRF');
    $rolId = (int)($_POST['rol_id'] ?? 0);
    if ($rolId === 1) {
        $error = 'Los permisos de Gerencia no son editables.';
    } elseif (!isset($ROL_META[$rolId])) {
        $error = 'Rol inválido.';
    } else {
        try {
            $db->beginTransaction();
            $db->prepare("DELETE FROM rol_permisos WHERE rol_id=?")->execute([$rolId]);
            $postedPerms = $_POST['perms'] ?? [];
            foreach ($allModKeys as $modulo) {
                $ver      = isset($postedPerms[$modulo]['ver'])      ? 1 : 0;
                $crear    = isset($postedPerms[$modulo]['crear'])    ? 1 : 0;
                $editar   = isset($postedPerms[$modulo]['editar'])   ? 1 : 0;
                $eliminar = isset($postedPerms[$modulo]['eliminar']) ? 1 : 0;
                // Sin ver → sin nada
                if (!$ver) $crear = $editar = $eliminar = 0;
                if ($ver) {
                    $db->prepare(
                        "INSERT INTO rol_permisos (rol_id,modulo,ver,crear,editar,eliminar) VALUES (?,?,?,?,?,?)"
                    )->execute([$rolId, $modulo, $ver, $crear, $editar, $eliminar]);
                }
            }
            $db->commit();
            auditoria('editar_permisos_rol', 'roles', $rolId, [],
                ['rol' => $ROL_META[$rolId]['label'], 'editado_por' => Auth::user()['id']]);
            header('Location: ?rol=' . $rolId . '&msg=ok');
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $error = $e->getMessage();
        }
    }
}

// Rol activo (por GET)
$rolActivo = (int)($_GET['rol'] ?? 2);
if (!isset($ROL_META[$rolActivo])) $rolActivo = 2;
if (($_GET['msg'] ?? '') === 'ok') {
    $success = 'Permisos de ' . $ROL_META[$rolActivo]['label'] . ' actualizados correctamente.';
}

// Cargar permisos actuales del rol seleccionado
$permActual = [];
$st = $db->prepare("SELECT modulo,ver,crear,editar,eliminar FROM rol_permisos WHERE rol_id=?");
$st->execute([$rolActivo]);
foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $permActual[$r['modulo']] = $r;
}

// Resumen de acceso por rol (para las tarjetas de tabs)
$resumenRoles = [];
foreach (array_keys($ROL_META) as $rid) {
    $cnt = (int)$db->query("SELECT COUNT(*) FROM rol_permisos WHERE rol_id=$rid AND ver=1")->fetchColumn();
    $resumenRoles[$rid] = $cnt;
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<style>
.rol-tab {
    border: 2px solid transparent;
    border-radius: 12px;
    padding: .5rem 1rem;
    cursor: pointer;
    transition: .15s;
    display: flex;
    align-items: center;
    gap: .5rem;
    text-decoration: none;
    font-size: .82rem;
    font-weight: 600;
    white-space: nowrap;
    background: #f8fafc;
    color: #64748b;
}
.rol-tab:hover { background: #f1f5f9; color: inherit; }
.rol-tab.activo {
    background: #fff;
    box-shadow: 0 2px 8px rgba(0,0,0,.08);
    border-color: var(--tab-color);
    color: var(--tab-color);
}
.rol-tab .tab-count {
    background: currentColor;
    color: #fff;
    border-radius: 20px;
    font-size: .63rem;
    padding: .1rem .45rem;
    font-weight: 700;
    opacity: .85;
}
.perm-table th {
    font-size: .72rem;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: #64748b;
    white-space: nowrap;
    padding: .5rem .75rem;
    background: #f8fafc;
}
.perm-table td { vertical-align: middle; padding: .45rem .75rem; }
.perm-table .group-hdr td {
    background: #f1f5f9;
    font-size: .7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: #94a3b8;
    padding: .35rem .75rem;
}
.perm-table .form-check-input { width: 2.2em; cursor: pointer; }
.perm-table .form-check { display: flex; justify-content: center; margin: 0; }
.perm-table tr[data-mod]:hover td { background: #fafbfc; }
.perm-table .chk-sub:disabled { opacity: .3; cursor: not-allowed; }
</style>

<!-- Cabecera -->
<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h4 class="fw-bold mb-0">
      <i class="bi bi-shield-lock me-2" style="color:#185FA5"></i>Roles y Permisos
    </h4>
    <div class="text-muted small">Controla qué módulos y acciones puede usar cada rol</div>
  </div>
  <a href="<?= APP_URL ?>/modules/usuarios/index.php" class="btn btn-sm btn-light">
    <i class="bi bi-arrow-left me-1"></i>Usuarios
  </a>
</div>

<?php if ($error):   ?><div class="alert alert-danger  py-2 small"><?= htmlspecialchars($error)   ?></div><?php endif; ?>
<?php if ($success): ?><div class="alert alert-success py-2 small"><?= htmlspecialchars($success) ?></div><?php endif; ?>

<div class="alert alert-info py-2 small mb-3">
  <i class="bi bi-info-circle me-1"></i>
  <strong>Gerencia</strong> siempre tiene acceso total y no es configurable aquí.
  Los cambios aplican desde el próximo request de los usuarios con ese rol.
</div>

<!-- Tabs de roles -->
<div class="d-flex flex-wrap gap-2 mb-4">
  <?php foreach ($ROL_META as $rid => $rm): ?>
  <a href="?rol=<?= $rid ?>"
     class="rol-tab <?= $rolActivo === $rid ? 'activo' : '' ?>"
     style="--tab-color:<?= $rm['color'] ?>">
    <i class="bi <?= $rm['icon'] ?>"></i>
    <span><?= htmlspecialchars($rm['label']) ?></span>
    <span class="tab-count"><?= $resumenRoles[$rid] ?? 0 ?></span>
  </a>
  <?php endforeach; ?>
</div>

<!-- Tabla de permisos del rol activo -->
<?php $rolMeta = $ROL_META[$rolActivo]; ?>
<div class="card border-0 shadow-sm">

  <div class="card-header d-flex align-items-center justify-content-between py-2 px-3"
       style="background:<?= $rolMeta['color'] ?>12;border-bottom:2px solid <?= $rolMeta['color'] ?>30">
    <span class="fw-bold d-flex align-items-center gap-2" style="color:<?= $rolMeta['color'] ?>;font-size:.9rem">
      <i class="bi <?= $rolMeta['icon'] ?>"></i>
      Permisos para: <?= htmlspecialchars($rolMeta['label']) ?>
    </span>
    <div class="d-flex gap-2">
      <button type="button" class="btn btn-sm btn-outline-secondary" style="font-size:.72rem"
              onclick="setTodos(true)">
        <i class="bi bi-check2-all me-1"></i>Habilitar todo
      </button>
      <button type="button" class="btn btn-sm btn-outline-secondary" style="font-size:.72rem"
              onclick="setTodos(false)">
        <i class="bi bi-x-circle me-1"></i>Deshabilitar todo
      </button>
    </div>
  </div>

  <div class="card-body p-0">
    <form method="POST">
      <input type="hidden" name="action"  value="guardar_permisos">
      <input type="hidden" name="csrf"    value="<?= Auth::csrfToken() ?>">
      <input type="hidden" name="rol_id"  value="<?= $rolActivo ?>">

      <div class="table-responsive">
        <table class="table table-sm perm-table mb-0">
          <thead>
            <tr>
              <th style="min-width:210px">Módulo</th>
              <th class="text-center" style="width:90px">
                <i class="bi bi-eye me-1"></i>Ver
              </th>
              <th class="text-center" style="width:90px">
                <i class="bi bi-plus-circle me-1"></i>Crear
              </th>
              <th class="text-center" style="width:90px">
                <i class="bi bi-pencil me-1"></i>Editar
              </th>
              <th class="text-center" style="width:90px">
                <i class="bi bi-trash me-1"></i>Eliminar
              </th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($GRUPOS as $grupoNombre => $mods): ?>
            <tr class="group-hdr">
              <td colspan="5"><?= htmlspecialchars($grupoNombre) ?></td>
            </tr>
            <?php foreach ($mods as $modKey => $modInfo):
              $p = $permActual[$modKey] ?? ['ver'=>0,'crear'=>0,'editar'=>0,'eliminar'=>0];
              $tieneVer = (bool)($p['ver'] ?? 0);
            ?>
            <tr data-mod="<?= $modKey ?>">
              <td>
                <i class="bi <?= $modInfo['icon'] ?> me-2" style="color:#94a3b8;font-size:.8rem"></i>
                <span style="font-size:.83rem"><?= htmlspecialchars($modInfo['label']) ?></span>
              </td>
              <?php foreach (['ver','crear','editar','eliminar'] as $accion): ?>
              <td>
                <div class="form-check form-switch">
                  <input class="form-check-input <?= $accion === 'ver' ? 'chk-ver' : 'chk-sub' ?>"
                         type="checkbox"
                         name="perms[<?= $modKey ?>][<?= $accion ?>]"
                         value="1"
                         <?= $accion === 'ver' ? 'data-mod="'.$modKey.'" onchange="onVerChange(this)"' : '' ?>
                         <?= ($p[$accion] ?? 0) ? 'checked' : '' ?>
                         <?= ($accion !== 'ver' && !$tieneVer) ? 'disabled' : '' ?>>
                </div>
              </td>
              <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="p-3 border-top d-flex align-items-center justify-content-between">
        <span class="small text-muted">
          <i class="bi bi-info-circle me-1"></i>
          Desactivar "Ver" deshabilita automáticamente Crear, Editar y Eliminar para ese módulo.
        </span>
        <button type="submit" class="btn btn-primary fw-bold">
          <i class="bi bi-floppy me-1"></i>Guardar — <?= htmlspecialchars($rolMeta['label']) ?>
        </button>
      </div>
    </form>
  </div>
</div>

<script>
// Al cambiar el toggle "Ver" de un módulo
function onVerChange(el) {
    var mod     = el.dataset.mod;
    var enabled = el.checked;
    var row     = document.querySelector('tr[data-mod="' + mod + '"]');
    if (!row) return;
    row.querySelectorAll('.chk-sub').forEach(function(c) {
        if (!enabled) c.checked = false;
        c.disabled = !enabled;
    });
}

// Habilitar / deshabilitar todo
function setTodos(on) {
    document.querySelectorAll('.chk-ver').forEach(function(el) {
        el.checked = on;
        onVerChange(el);
        if (on) {
            var mod = el.dataset.mod;
            var row = document.querySelector('tr[data-mod="' + mod + '"]');
            if (!row) return;
            var subs = row.querySelectorAll('.chk-sub');
            // Habilitar Ver + Crear + Editar, dejar Eliminar sin marcar
            subs.forEach(function(c, i) {
                if (i < 2) c.checked = true; // crear, editar
                // eliminar (i=2) queda sin marcar por defecto
            });
        }
    });
}
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
