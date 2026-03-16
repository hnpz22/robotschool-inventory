# ROBOTSchool Inventory & Platform v3.3

Sistema de gestión integral para ROBOTSchool Colombia — inventario, pedidos, kits, cursos, matrículas y módulo académico LMS.

---

## Requisitos del sistema

| Componente | Versión mínima |
|---|---|
| PHP | 7.4+ (compatible PHP 8.x) |
| MySQL / MariaDB | 5.7+ / 10.3+ |
| Apache | 2.4+ |
| XAMPP (recomendado) | 8.x |

**Plataformas:** macOS, Windows, Linux (cualquier sistema con XAMPP)

---

## Instalación rápida

### 1. Copiar archivos

**macOS / Linux:**
```bash
cp -r robotschool_inventory /Applications/XAMPP/xamppfiles/htdocs/
# o en Linux:
cp -r robotschool_inventory /opt/lampp/htdocs/
```

**Windows:**
```
Copiar carpeta robotschool_inventory a:
C:\xampp\htdocs\
```

### 2. Permisos (macOS / Linux)

```bash
# macOS
sudo chmod -R 755 /Applications/XAMPP/xamppfiles/htdocs/robotschool_inventory
sudo chmod -R 777 /Applications/XAMPP/xamppfiles/htdocs/robotschool_inventory/uploads

# Linux
sudo chmod -R 755 /opt/lampp/htdocs/robotschool_inventory
sudo chmod -R 777 /opt/lampp/htdocs/robotschool_inventory/uploads
```

**Windows:** No requiere cambios de permisos.

### 3. Base de datos

1. Abrir **phpMyAdmin** → `http://localhost/phpmyadmin`
2. Crear base de datos: `robotschool_inventory` (cotejamiento: `utf8mb4_unicode_ci`)
3. Seleccionar la BD → pestaña **SQL**
4. Ejecutar los archivos SQL en este orden:

```
sql/01_schema_base.sql          ← Estructura base del sistema
sql/02_migration_v2.0.sql       ← Roles y permisos
sql/03_migration_v3.0.sql       ← Matrículas y módulo académico
sql/04_migration_v3.1.sql       ← Cupos por inventario
sql/05_migration_v3.2.sql       ← Módulo de cursos
sql/06_migration_v3.3.sql       ← Sedes
```

### 4. Configuración

Editar `config/config.php`:

```php
// Base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'robotschool_inventory');
define('DB_USER', 'root');
define('DB_PASS', '');          // Tu password de MySQL

// URL del sistema
define('APP_URL', 'http://localhost/robotschool_inventory');

// API Key de Anthropic (para banner IA - opcional)
define('ANTHROPIC_API_KEY', 'sk-ant-api03-...');
```

### 5. Acceder al sistema

```
http://localhost/robotschool_inventory
```

**Credenciales iniciales:**
| Usuario | Contraseña |
|---|---|
| admin@robotschool.com.co | robotschool2025 |

> ⚠️ Cambiar la contraseña después del primer acceso.

---

## Estructura del proyecto

```
robotschool_inventory/
├── config/
│   └── config.php              ← Configuración principal
├── includes/
│   ├── Auth.php                ← Autenticación y permisos
│   ├── Database.php            ← Conexión PDO
│   ├── helpers.php             ← Funciones utilitarias
│   ├── header.php              ← Layout header + menú
│   └── footer.php              ← Layout footer
├── modules/
│   ├── elementos/              ← Inventario de elementos
│   ├── kits/                   ← Constructor y gestión de kits
│   ├── colegios/               ← Colegios y cursos asignados
│   ├── pedidos_tienda/         ← Pedidos WooCommerce + stickers
│   ├── produccion/             ← Solicitudes de producción
│   ├── importaciones/          ← Importaciones y proveedores
│   ├── despachos/              ← Guías y despachos
│   ├── reportes/               ← Reportes e inventario
│   ├── cursos/                 ← Cursos escuela + horarios + sedes
│   ├── matriculas/             ← Matrículas y pagos de la escuela
│   ├── academico/              ← LMS académico para colegios
│   ├── usuarios/               ← Gestión de usuarios
│   └── barcodes/               ← Códigos de barras
├── uploads/                    ← Archivos subidos (imágenes)
├── assets/
│   ├── css/                    ← Estilos
│   ├── js/                     ← Scripts
│   └── img/                    ← Imágenes del sistema
└── sql/                        ← Migraciones SQL
```

---

## Módulos del sistema

### 📦 Inventario
- Elementos por categoría con semáforo de stock
- Categorías: Arduino, ESP32, Sensores, Tornillos, LEDs, MDF, Filamentos, etc.
- Códigos de barras y etiquetas
- Importaciones con liquidación de aranceles
- Reportes y valorización

### 🛒 Tienda Online
- Importación CSV de WooCommerce
- Gestión de estados de pedidos
- Etiquetas de envío (10 por hoja carta)
- Selección de pedidos con checkboxes para impresión masiva
- Solicitudes a producción con notificaciones

### 🎒 Kits
- Constructor visual de kits desde inventario
- Sticker de caja con componentes e imágenes (4 por hoja)
- Asignación a cursos de colegios

### 🏫 Colegios
- Gestión de colegios con coordinadores
- Cursos por colegio agrupados por grado
- Asignación de kits a cursos

### 🎓 Cursos Escuela
- Catálogo de cursos: Robótica, Python, Minecraft, Roblox, Impresión 3D, Arduino
- **Horarios**: 3 franjas sabatinas (8-10, 10:30-12:30, 1-3pm)
- **Cupos**: calculados automáticamente desde el stock del inventario
- **Sedes**: Bogotá Norte, Bogotá Sur, Cali
- **Banner IA**: generación de banner promocional con Claude AI
- Módulos de avance del curso

### 📋 Matrículas
- Registro de estudiantes y acudientes
- Matrículas vinculadas a grupos/horarios
- Seguimiento de pagos: efectivo, Nequi, Daviplata, transferencia
- **Calendario de sábados**: resumen de recaudo por semana
- Cupos en tiempo real según inventario

### 📚 Académico LMS
- Colegios con coordinadores
- Cursos → Unidades → Actividades
- Materiales asignables por estudiante
- **Sistema XP y gamificación**:
  - Aprendiz (0-99 XP)
  - Constructor (100-299 XP)
  - Ingeniero (300-599 XP)
  - Maestro (600+ XP)

### 👥 Usuarios y Roles
| Rol | Permisos |
|---|---|
| Administrador | Acceso total |
| Operador | Inventario, kits, colegios |
| Tienda | Pedidos tienda + producción |
| Producción | Armar kits, actualizar estados |
| Despachos | Guías y entregas |
| Consulta | Solo lectura |

---

## Configurar Banner IA

Para usar el generador de banners con Claude AI:

1. Obtener API key en [console.anthropic.com](https://console.anthropic.com)
2. Agregar en `config/config.php`:
```php
define('ANTHROPIC_API_KEY', 'sk-ant-api03-TU-API-KEY-AQUI');
```
3. Verificar que cURL esté habilitado en PHP (XAMPP lo incluye por defecto)

---

## Franjas horarias sabatinas

| Franja | Horario |
|---|---|
| Mañana 1 | 8:00 am - 10:00 am |
| Mañana 2 | 10:30 am - 12:30 pm |
| Tarde | 1:00 pm - 3:00 pm |

---

## Soporte

**ROBOTSchool Colombia**
- Web: robotschool.com.co
- Tel: 318 654 1859
- Email: info@robotschool.com.co
- Bogotá, Colombia

---

## Changelog

| Versión | Cambios |
|---|---|
| v3.3 | Sedes (Bogotá x2, Cali), filtro por sede en horarios |
| v3.2 | Módulo de cursos, horarios, cupos por inventario, banner IA |
| v3.1 | Cupos de grupos vinculados al inventario |
| v3.0 | Módulo matrículas, módulo académico LMS |
| v2.0 | Sistema de roles y permisos por módulo |
| v1.9 | Nuevas categorías inventario |
| v1.8 | Pedidos tienda WooCommerce |
| v1.0 | Sistema base: inventario, kits, colegios |
