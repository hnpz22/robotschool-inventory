# AGENTS.md — Guía para agentes IA

Este archivo describe el proyecto completo para que cualquier agente IA pueda trabajar sin preguntar. Leerlo antes de cualquier cambio.

---

## Qué es este proyecto y su propósito de negocio

**ROBOTSchool Inventory & Platform v3.3** es el sistema de gestión interna de ROBOTSchool Colombia, empresa que vende kits de robótica a colegios y opera una escuela de robótica para niños.

El sistema cubre toda la operación:
1. **Inventario** — componentes electrónicos comprados en China vía DHL, con liquidación de aranceles proporcional al peso
2. **Kits** — armado de kits robóticos desde el inventario para vender a colegios y la escuela
3. **Tienda online** — pedidos de WooCommerce que deben armarse y despacharse
4. **Producción** — tablero kanban para el equipo que arma los kits físicamente
5. **Colegios** — clientes B2B que contratan programas de robótica con kits incluidos
6. **Escuela** — cursos sabatinos para niños, con matrículas, pagos y cupos
7. **Académico LMS** — plataforma de contenidos para los docentes de los colegios
8. **Comercial** — pipeline de convenios y requerimientos con nuevos colegios

**No es un sistema público.** Solo accede personal interno de ROBOTSchool. No hay registro de usuarios desde la web.

---

## Stack técnico detallado

| Componente | Detalle |
|---|---|
| PHP | 8.1 — vanilla, sin frameworks |
| Base de datos | MySQL 8.0 (Docker) / MariaDB 10.4 (XAMPP local) |
| ORM / BD | PDO puro — sin ORM, queries manuales |
| Frontend CSS | Bootstrap 5.3.2 via CDN |
| Frontend JS | Vanilla JS + Bootstrap Icons 1.11.3 + JsBarcode 3.11.6 — sin React, Vue ni bundler |
| Servidor | Apache con `mod_rewrite` habilitado |
| Contenedores | Docker Compose 3.9 — servicios: `app` (PHP+Apache), `db` (MySQL 8), `phpmyadmin` |
| Zona horaria | `America/Bogota` — hardcodeada en `config.php` y `docker/php.ini` |
| Charset | `utf8mb4_unicode_ci` en toda la BD |
| Sesiones | PHP nativas — nombre `rs_inv_session`, timeout configurable via `SESSION_TIMEOUT` |
| Uploads | `assets/uploads/` — subdirectorios por tipo: `Elementos/`, `Colegios/`, `kits/`, `prototipos/`, `avatars/` |
| APIs externas | Anthropic Claude API (banners), WooCommerce REST v3, Microsoft OAuth 2.0 |

**Extensiones PHP requeridas:** `pdo_mysql`, `mysqli`, `zip`, `gd` (imagen con freetype + jpeg + webp)

---

## Patrón que sigue cada módulo PHP

Todo módulo sigue el mismo patrón sin excepción. Copiar esto como plantilla:

```php
<?php
// ── 1. Includes obligatorios (siempre estos 4, en este orden) ──
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';

// ── 2. Verificar autenticación ──
Auth::check();
// Opcional: restringir por rol
// Auth::requireRol('gerencia', 'administracion');
// Auth::requirePermiso('nombre_modulo', 'ver');

// ── 3. Variables de layout ──
$db         = Database::get();
$pageTitle  = 'Título visible en el topbar';
$activeMenu = 'clave_del_menu';  // coincide con la clave en header.php

// ── 4. Lógica PHP (queries, POST handling) ──
// ...

// ── 5. Renderizar header (abre <html>, <body>, sidebar, topbar) ──
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- 6. HTML del módulo aquí -->

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
```

**Reglas del patrón:**
- `dirname(__DIR__, 2)` — los módulos están en `modules/nombre/`, por eso suben 2 niveles
- `$pageTitle` — se muestra en `<title>` y en el topbar
- `$activeMenu` — la clave que `header.php` usa para aplicar la clase `active` al link del sidebar
- El header abre `<html>`, `<head>`, `<body>`, sidebar y `<main>`. El footer cierra `</main>`, `</body>`, `</html>`
- **Nunca** abrir `<html>` o `<body>` en el módulo directamente

**Módulos de nivel raíz** (`dashboard.php`, `login.php`) usan `__DIR__` en lugar de `dirname(__DIR__, 2)`:
```php
require_once __DIR__ . '/config/config.php';
```

---

## Cómo funciona Auth

### Flujo de autenticación

```
index.php → login.php → Auth::login() → $_SESSION → dashboard.php
```

`Auth::check()` se llama al inicio de cada página protegida. Si no hay sesión, redirige a `login.php`. Si la sesión expiró (configurable con `SESSION_TIMEOUT`, default 3600s), cierra sesión automáticamente.

### Métodos principales de Auth

```php
Auth::check()                     // Verifica sesión activa. Redirige si no hay sesión o expiró.
Auth::requireAdmin()              // Exige rol_id <= 2 (gerencia o administracion)
Auth::requireRol('gerencia', 'academia')  // Exige uno de los roles listados. Gerencia siempre pasa.
Auth::requirePermiso('modulo', 'ver')     // Consulta tabla rol_permisos. Gerencia siempre pasa.
Auth::isAdmin()                   // bool — true si rol_id <= 2
Auth::isGerencia()                // bool — true si rol_id === 1
Auth::user()                      // array: id, name, nombre, email, rol, rol_nombre, avatar
Auth::getRolId()                  // int — ID del rol actual
Auth::getRol()                    // string — nombre del rol: 'gerencia', 'administracion', etc.
Auth::getRolMeta()                // array: label, color, icon — para UI
Auth::menuItems()                 // array — lista de claves de módulos accesibles para este rol
Auth::tieneAcceso('modulo')       // bool — si el módulo está en menuItems()
Auth::puede('modulo', 'accion')   // bool — permisos granulares (ver/crear/editar/eliminar)
Auth::csrfToken()                 // string — genera/retorna token CSRF de la sesión
Auth::csrfVerify($token)          // bool — valida token CSRF
Auth::notificacionesPendientes()  // int — solicitudes de producción pendientes (roles 1,2,4)
Auth::conveniosPendientes()       // int — convenios pendientes de aprobación (solo gerencia)
```

### Los 6 roles

| rol_id | Nombre clave | Label UI | Acceso |
|---|---|---|---|
| 1 | `gerencia` | Gerencia | **Todo sin excepción** — bypasea cualquier check de permisos |
| 2 | `administracion` | Administración | Dashboard, Inventario, Kits, Colegios, Pedidos Tienda, Producción, Reportes |
| 3 | `academia` | Academia | Dashboard, Cursos, Matrículas, Pagos, Académico LMS, Colegios |
| 4 | `produccion` | Producción | Dashboard, Inventario, Kits, Producción |
| 5 | `comercial` | Comercial | Dashboard, Comercial, Convenios, Colegios |
| 6 | `consulta` | Consulta | Dashboard, Inventario (solo lectura), Reportes |

**Importante:** Los roles 1 y 2 también ven Importaciones y Despachos aunque no aparezcan en `$ROL_MENUS` — el sidebar los renderiza con un check `$_rolId <= 2` directo.

### Datos disponibles en sesión

```php
$_SESSION['user_id']
$_SESSION['user_name']     // alias de user_nombre
$_SESSION['user_nombre']
$_SESSION['user_email']
$_SESSION['user_rol']      // int — rol_id
$_SESSION['rol_nombre']    // string — nombre clave del rol
$_SESSION['user_avatar']   // URL o null
$_SESSION['logged_at']     // timestamp Unix del último request
$_SESSION['csrf_token']
```

---

## Cómo funciona la base de datos

### Conexión

```php
$db = Database::get();  // PDO singleton — misma instancia en toda la request
```

`Database::get()` está en `includes/Database.php`. Usa las constantes definidas en `config.php` (`DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`, `DB_CHARSET`). En caso de fallo, mata la ejecución con `die(json_encode(['error'=>...]))`.

**Opciones PDO activas:**
- `ERRMODE_EXCEPTION` — los errores SQL lanzan `PDOException`
- `FETCH_ASSOC` — todos los fetch devuelven arrays asociativos
- `EMULATE_PREPARES = false` — prepares reales en el servidor MySQL

### Convenciones de queries

**Siempre usar `prepare()` + `execute()` para queries con input de usuario:**
```php
$st = $db->prepare("SELECT * FROM elementos WHERE id = ? AND activo = 1");
$st->execute([$id]);
$elem = $st->fetch();

$st = $db->prepare("SELECT * FROM elementos WHERE codigo = :codigo");
$st->execute(['codigo' => $codigo]);
```

**Para queries sin input de usuario es aceptable `query()` directo:**
```php
$total = $db->query("SELECT COUNT(*) FROM elementos WHERE activo=1")->fetchColumn();
$rows  = $db->query("SELECT id, nombre FROM categorias WHERE activa=1 ORDER BY nombre")->fetchAll();
```

**Transacciones:**
```php
$db->beginTransaction();
try {
    // múltiples operaciones
    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    throw $e;
}
```

**Auditoría:** Usar `auditoria()` de `helpers.php` cuando se modifiquen datos importantes:
```php
auditoria('crear_elemento', 'elementos', $newId, [], $data);
auditoria('editar_elemento', 'elementos', $id, $datosAntes, $datosNuevos);
```

### Tablas principales a conocer

```
elementos            — inventario de componentes (stock_actual, stock_minimo, stock_maximo, costo_real_cop)
categorias           — categorías de elementos (prefijo para código RS-XXX-001, color, icono)
codigos_secuencia    — contador autoincremental por categoría para generar códigos únicos
movimientos          — historial de cambios de stock (entrada/salida/ajuste/devolucion/transferencia)
proveedores          — proveedores de China/internacional
pedidos_importacion  — pedidos DHL (valor FOB USD, flete, aranceles, IVA)
pedido_items         — líneas de cada pedido (vincula elemento_id + cantidades + peso)
kits                 — kits armados (costo_cop calculado)
kit_elementos        — relación kit ↔ elemento con cantidad
kit_prototipos       — relación kit ↔ prototipo con cantidad
prototipos           — cortes MDF/piezas fabricadas (tipo_fabricacion, costo_total_cop)
tienda_pedidos       — pedidos WooCommerce importados (woo_order_id, estado_interno)
tienda_pedidos_historial — cambios de estado de pedidos tienda
solicitudes_produccion — tablero kanban de producción (estado, fuente, prioridad, kit_nombre)
solicitud_historial  — historial de cambios de estado en producción
solicitud_items      — ítems individuales dentro de una solicitud
colegios             — colegios clientes B2B
cursos               — cursos asignados a colegios (con kit_id)
sedes                — sedes físicas de la escuela
escuela_cursos       — catálogo de cursos de la escuela (Robótica, Python, etc.)
escuela_grupos       — grupos con horario, docente, cupo vinculado a inventario
escuela_programas    — programas académicos
matriculas           — inscripciones de estudiantes a grupos
estudiantes          — base de datos de estudiantes con acudiente
pagos                — registro de pagos de matrículas
roles                — 6 roles del sistema
rol_permisos         — permisos granulares por rol y módulo
usuarios             — usuarios del sistema (password_hash bcrypt)
configuracion        — pares clave/valor por grupo (ej: grupo='woocommerce')
auditoria            — log de acciones importantes con datos antes/después
convenios            — convenios comerciales con colegios
acad_*               — 10 tablas del módulo académico LMS
```

**Vistas SQL relevantes:**
```
v_movimientos        — movimientos con nombre de elemento, categoría y usuario
v_grupos_cupos       — grupos con stock_disponible, matriculas_activas, cupos_libres, disponibilidad
v_kits               — kits con conteo de elementos y costo
v_pedidos            — pedidos de importación con totales calculados
v_stock_semaforo     — elementos con su color de semáforo
v_tienda_pedidos     — pedidos tienda con datos del cliente
v_colegios           — colegios con conteo de cursos
```

---

## Convenciones de nombres

### Archivos
- Módulos: `snake_case.php` — ej: `nueva_matricula.php`, `exportar_pdf.php`
- Clases: `PascalCase.php` — ej: `Auth.php`, `Database.php`, `WooSync.php`
- Un módulo por carpeta en `modules/` — carpeta en `snake_case`

### Variables PHP
- Variables de módulo: `$snake_case` — ej: `$pageTitle`, `$activeMenu`, `$db`
- Variables de layout internas de `header.php`: prefijo `$_` — ej: `$_user`, `$_rol`, `$_menu`, `$_rm`
- Arrays de resultados BD: plural del concepto — ej: `$elementos`, `$categorias`, `$solicitudes`
- Variable singular para un registro: `$elem`, `$kit`, `$pedido`, `$grupo`

### Funciones en helpers.php
- `camelCase` — ej: `generarCodigo()`, `liquidarPedido()`, `crearSolicitudProduccion()`
- Excepciones por ser helpers simples: `cop()`, `usd()` — abreviadas por frecuencia de uso

### Tablas BD
- `snake_case` en singular o plural según convención ya existente
- Tablas del módulo académico LMS: prefijo `acad_` — ej: `acad_actividades`, `acad_progreso`
- Tablas de tienda: prefijo `tienda_` — ej: `tienda_pedidos`, `tienda_pedidos_historial`
- Tablas de solicitudes de producción: `solicitudes_produccion`, `solicitud_historial`, `solicitud_items`
- Vistas: prefijo `v_` — ej: `v_movimientos`, `v_grupos_cupos`

### Columnas BD
- Claves primarias: `id` (siempre)
- Claves foráneas: `tabla_id` — ej: `categoria_id`, `elemento_id`, `kit_id`
- Flags booleanos: `activo`, `activa` (tinyint 0/1)
- Timestamps: `created_at` (datetime default NOW()), `updated_at`, `liquidado_at`, `completado_at`
- Creado por: `created_by` (FK a `usuarios.id`)
- Costos en pesos colombianos: sufijo `_cop` — ej: `costo_real_cop`, `costo_total_cop`
- Costos en dólares: sufijo `_usd` — ej: `precio_usd`, `valor_fob_usd`
- Porcentajes: sufijo `_pct` — ej: `arancel_pct`, `iva_pct`

### Claves `$activeMenu` (para el sidebar)
Los valores exactos que reconoce `header.php`:
`dashboard`, `elementos`, `movimientos`, `barcodes`, `kits`, `colegios`, `pedidos`, `proveedores`, `pedidos_tienda`, `produccion`, `despachos`, `cursos`, `matriculas`, `estudiantes`, `pagos`, `academico`, `comercial`, `reportes`, `usuarios`, `categorias`, `config`

---

## Archivos que NUNCA se deben modificar sin análisis previo

### `includes/helpers.php`
Contiene `liquidarPedido()` — función crítica que distribuye flete DHL + aranceles + IVA entre los ítems de un pedido, actualiza `costo_real_cop` en `elementos`, registra movimientos de entrada y marca el pedido como liquidado, todo en una transacción. Un bug aquí corrompe costos históricos irreversiblemente.

También contiene `crearSolicitudProduccion()` que detecta en tiempo de ejecución si las columnas opcionales existen (`SHOW COLUMNS`). Cambiar la firma de esta función requiere actualizar todos los módulos que la llaman.

### `includes/Auth.php`
Define el sistema completo de roles y permisos. `$ROL_MENUS` controla qué ve cada rol en el sidebar. Modificar IDs de roles aquí sin actualizar la BD y `header.php` rompe el acceso de todos los usuarios. `csrfVerify()` protege todos los formularios POST — no remover sin reemplazar.

### `includes/header.php`
Renderiza el sidebar dinámico. Contiene lógica de permisos en la vista (`$_rolId <= 2` para Importaciones/Despachos). Modificaciones aquí afectan todas las páginas del sistema simultáneamente. Además hay un bug documentado en esta misma sección.

### `database/seed.sql`
Es el único archivo de BD del proyecto. Contiene el esquema completo + datos de referencia. No editarlo directamente — agregar nuevas migraciones como archivos separados en `database/` con nombre `migration_vX.X.sql` y documentarlos en el changelog del README.

---

## Bugs conocidos y documentados

### Bug 1 — `modules/elementos/categorias.php` no existe
**Ubicación:** `includes/header.php`, línea ~258
**Código afectado:**
```php
<a class="nav-link sidebar-link" href="<?= APP_URL ?>/modules/elementos/categorias.php">
    <i class="bi bi-tags"></i> <span>Categorías</span>
</a>
```
**Efecto:** El link "Categorías" en el sidebar (visible para roles 1 y 2) lleva a una página 404. La gestión de categorías no existe como módulo PHP aunque la tabla `categorias` sí existe en BD.
**Solución pendiente:** Crear `modules/elementos/categorias.php` con CRUD de la tabla `categorias`.

### Bug 2 — `modules/auth/config.php` no existe
**Ubicación:** `includes/header.php`, línea ~264
**Código afectado:**
```php
<a class="nav-link sidebar-link" href="<?= APP_URL ?>/modules/auth/config.php">
    <i class="bi bi-gear"></i> <span>Configuración</span>
</a>
```
**Efecto:** El link "Configuración" en el sidebar (visible para roles 1 y 2) lleva a una página 404. La tabla `configuracion` existe en BD y tiene la configuración de WooCommerce, pero no hay UI para editarla.
**Solución pendiente:** Crear `modules/auth/config.php` (o mover a `modules/config/index.php`) con formulario para editar la tabla `configuracion`.

### Bug 3 — Variable `$rm` vs `$_rm` en `header.php`
**Ubicación:** `includes/header.php`, línea ~26
**Código afectado:**
```php
.rol-badge { background: <?= $rm['color'] ?? '#185FA5' ?>; }
```
**Efecto:** La variable correcta es `$_rm` (definida en la línea 10 como `$_rm = Auth::getRolMeta()`). `$rm` es `undefined`, por lo que siempre usa el color por defecto `#185FA5` en lugar del color del rol del usuario. El badge de rol en el sidebar siempre sale azul.
**Solución:** Cambiar `$rm['color']` por `$_rm['color']` en esa línea CSS inline.

### Bug 4 — Inconsistencia `woo_pedidos` vs `tienda_pedidos`
**Ubicación:** `includes/WooSync.php` usa la tabla `woo_pedidos`; `modules/produccion/index.php` hace JOIN con `tienda_pedidos`; `database/seed.sql` define `tienda_pedidos` y `tienda_pedidos_historial`.
**Efecto:** `WooSync.php` intenta escribir en `woo_pedidos` que posiblemente no existe (el seed define `tienda_pedidos`). El módulo de producción intenta hacer JOIN con `tienda_pedidos` que puede no tener los registros importados via `WooSync`.
**Hipótesis:** La tabla se renombró de `woo_pedidos` a `tienda_pedidos` durante el desarrollo y `WooSync.php` quedó desactualizado.
**Solución pendiente:** Actualizar `WooSync.php` para usar `tienda_pedidos` y `tienda_pedidos_historial` en lugar de `woo_pedidos` y `woo_pedido_historial`. También actualizar `api/pedido_detalle.php` si usa la tabla vieja.

---

## Cómo agregar un módulo nuevo

### Paso 1 — Crear la carpeta y el archivo principal

```
modules/mi_modulo/
    index.php       ← listado principal
    form.php        ← crear/editar (GET con ?id=X para editar)
    delete.php      ← eliminar (GET con ?id=X&csrf=TOKEN)
```

### Paso 2 — `index.php` con el patrón estándar

```php
<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
Auth::check();
Auth::requirePermiso('mi_modulo', 'ver');  // si aplica restricción de rol

$db         = Database::get();
$pageTitle  = 'Título del Módulo';
$activeMenu = 'mi_modulo';  // registrar esta clave en el sidebar

// Lógica de datos...

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- HTML del módulo -->

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
```

### Paso 3 — Registrar en el sidebar (`includes/header.php`)

Agregar en la sección correspondiente del sidebar:

```php
<?php if (in_array('mi_modulo', $_menu)): ?>
<li class="nav-item">
  <a class="nav-link sidebar-link <?= ($activeMenu??'')==='mi_modulo'?'active':'' ?>"
     href="<?= APP_URL ?>/modules/mi_modulo/">
    <i class="bi bi-icon-aqui"></i> <span>Mi Módulo</span>
  </a>
</li>
<?php endif; ?>
```

### Paso 4 — Registrar en `$ROL_MENUS` (`includes/Auth.php`)

Agregar `'mi_modulo'` en el array de cada rol que debe tener acceso:

```php
private static $ROL_MENUS = [
    1 => ['dashboard', ..., 'mi_modulo'],  // gerencia
    2 => ['dashboard', ..., 'mi_modulo'],  // administracion si aplica
    // ...
];
```

### Paso 5 — Formularios POST: siempre con CSRF

```php
<!-- En el HTML del formulario -->
<form method="POST">
    <input type="hidden" name="csrf" value="<?= Auth::csrfToken() ?>">
    <!-- campos -->
</form>

<?php
// Al procesar el POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::csrfVerify($_POST['csrf'] ?? '')) die('CSRF inválido');
    // ... lógica
}
?>
```

### Paso 6 — Auditoría en operaciones importantes

```php
auditoria('crear_registro', 'mi_tabla', $newId, [], $data);
auditoria('editar_registro', 'mi_tabla', $id, $datosAntes, $datosNuevos);
auditoria('eliminar_registro', 'mi_tabla', $id, $datosAntes, []);
```

### Paso 7 — Tablas nuevas en BD

Crear un archivo `database/migration_vX.X.sql` con las sentencias `CREATE TABLE` o `ALTER TABLE`. No modificar `seed.sql`. Documentar en `README.md` changelog.

---

## Integraciones externas

### WooCommerce REST API v3

**Clase:** `includes/WooSync.php`

**Configuración:** Tabla `configuracion` en BD, grupo `woocommerce`:
- `woo_url` — URL base WordPress
- `woo_consumer_key` — CK de WooCommerce
- `woo_consumer_secret` — CS de WooCommerce
- `woo_campo_colegio` — nombre del meta field del checkout donde el cliente escribe su colegio

**Uso:**
```php
$woo = new WooSync(Database::get());
if ($woo->isConfigured()) {
    $resultado = $woo->sincronizar(3);  // 3 páginas x 100 pedidos
}
```

**Flujo:** `sincronizar()` → `procesarPedido()` → escribe en `tienda_pedidos` + `tienda_pedidos_historial` + intenta cruzar colegio por nombre aproximado.

**Bug conocido:** La clase actualmente escribe en `woo_pedidos` (tabla que no existe en el seed). Ver Bug 4 en sección anterior.

### Microsoft OAuth 2.0

**Archivo:** `modules/auth/ms_callback.php`

**Variables de entorno requeridas:** `MS_CLIENT_ID`, `MS_CLIENT_SECRET`, `MS_TENANT_ID`

**Flujo:** Redirige a Microsoft → Microsoft devuelve code → `ms_callback.php` intercambia por token → llama a Microsoft Graph para obtener perfil → `Auth::loginMicrosoft($msUser)` busca el usuario por `microsoft_id` o `email` en la tabla `usuarios` → si existe, inicia sesión; si no existe, falla silenciosamente (no crea usuarios automáticamente).

**Tabla afectada:** `usuarios.microsoft_id` y `usuarios.avatar_url` se actualizan al hacer login.

**Sin esta config:** El botón de login con Microsoft simplemente no aparece. No rompe nada.

### Anthropic Claude API

**Ubicación de uso:** `modules/cursos/generar_banner.php`

**Variable de entorno:** `ANTHROPIC_API_KEY`

**Cómo se activa:** En `config.php`, si `ANTHROPIC_API_KEY` está definida como variable de entorno, se define la constante `ANTHROPIC_API_KEY`. El módulo de cursos verifica `defined('ANTHROPIC_API_KEY')` antes de mostrar el botón de generación.

**Sin esta config:** El botón "Generar Banner IA" no aparece. El módulo de cursos funciona igual.

**Comunicación:** cURL directo a la API de Anthropic desde PHP. No hay SDK instalado — la llamada es HTTP manual.

---

## Helpers disponibles globalmente (includes/helpers.php)

Estas funciones están disponibles en todos los módulos después de incluir `helpers.php`:

```php
generarCodigo(int $categoriaId): string
// Genera el próximo código único RS-XXX-NNN para un elemento. Usa transacción + FOR UPDATE.

semaforo(int $actual, int $minimo, int $maximo): array
// Retorna ['color'=>'danger|warning|success|info', 'label'=>'...', 'icon'=>'🔴...']

liquidarPedido(int $pedidoId): array
// Distribuye costos de importación, actualiza stock y crea movimientos. CRÍTICA.

cop(float $valor): string           // Formatea en pesos COP: "$ 1.234.567"
usd(float $valor): string           // Formatea en dólares: "USD 12.34"

auditoria(string $accion, string $tabla, int $regId, array $antes, array $despues): void
// Registra en tabla auditoria. Silent fail si hay error.

subirFoto(array $file, string $subdir): ?string
// Valida y mueve una imagen subida. Retorna ruta relativa desde uploads/. Max 5MB.

paginar(int $total, int $pagina, int $porPagina): array
// Retorna [total, pagina, por_pagina, total_paginas, offset]

crearSolicitudProduccion(PDO $db, array $datos): int
// Crea solicitud en tablero de producción desde cualquier módulo. Retorna ID creado.
```

---

## Constantes disponibles en todo el sistema (config.php)

```php
APP_NAME        // 'ROBOTSchool Inventory'
APP_VERSION     // '3.3'
APP_URL         // URL base sin barra final — ej: 'http://localhost:8000'
APP_ROOT        // Ruta absoluta a la raíz del proyecto
APP_ENV         // 'development' | 'production'
DB_HOST, DB_NAME, DB_USER, DB_PASS, DB_CHARSET
UPLOAD_DIR      // APP_ROOT . '/assets/uploads/' — ruta absoluta para mover archivos
UPLOAD_URL      // APP_URL . '/assets/uploads/' — URL para mostrar imágenes en HTML
SESSION_NAME    // 'rs_inv_session'
SESSION_TIMEOUT // int — segundos, default 3600
ITEMS_PER_PAGE  // 25 — paginación por defecto
// Opcionales (solo si están en el entorno):
ANTHROPIC_API_KEY
MS_CLIENT_ID, MS_CLIENT_SECRET, MS_TENANT_ID
```

---

## Puntos de entrada del sistema

| URL | Archivo | Descripción |
|---|---|---|
| `/` | `index.php` | Redirige a `/login.php` |
| `/login.php` | `login.php` | Pantalla de login — también verifica sesión activa |
| `/dashboard.php` | `dashboard.php` | Dashboard principal — requiere sesión |
| `/setup_admin.php` | `setup_admin.php` | Crea/resetea admin — solo localhost — eliminar después |
| `/modules/auth/logout.php` | `modules/auth/logout.php` | Destruye sesión y redirige a login |
| `/modules/auth/ms_callback.php` | `modules/auth/ms_callback.php` | Callback OAuth Microsoft |
| `/api/*.php` | `api/` | Endpoints JSON para llamadas AJAX internas |

---

## Lo que NO existe (para no intentar importarlo o incluirlo)

Los siguientes archivos son referenciados en el código pero **no existen** en el repositorio:

- `modules/elementos/categorias.php` — mencionado en sidebar (Bug 1)
- `modules/auth/config.php` — mencionado en sidebar (Bug 2)
- `sql/01_schema_base.sql` a `sql/06_migration_v3.3.sql` — mencionados en la documentación anterior, reemplazados por `database/seed.sql`
- Tabla `woo_pedidos` — mencionada en `WooSync.php`, la tabla real es `tienda_pedidos` (Bug 4)
