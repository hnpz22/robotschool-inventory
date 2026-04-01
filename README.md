# ROBOTSchool Inventory & Platform — v3.3

Sistema de gestión interna de **ROBOTSchool Colombia**. Centraliza inventario de componentes electrónicos, producción de kits, pedidos de la tienda online (WooCommerce), matrículas de la escuela de robótica, módulo académico LMS para colegios y operaciones comerciales. Uso exclusivamente interno.

---

## Stack tecnológico

| Componente | Versión |
|---|---|
| PHP | 8.1 (Apache) |
| Base de datos | MySQL 8.0 (Docker) / MariaDB 10.4+ (XAMPP) |
| CSS | Bootstrap 5.3.2 + Bootstrap Icons 1.11.3 |
| JS | JsBarcode 3.11.6 — sin frameworks frontend |
| Contenedores | Docker + Docker Compose 3.9 |
| Zona horaria | `America/Bogota` |

No se usa ningún framework PHP (sin Laravel, sin Symfony). La arquitectura es PHP vanilla con PDO.

---

## Requisitos

- Docker Desktop 4.x o superior
- Docker Compose v2 (`docker compose` sin guion)
- Git
- Puerto `8081` libre (app), `8080` libre (phpMyAdmin), `3308` libre (MySQL)

Para desarrollo local sin Docker: XAMPP 8.x con PHP 8.1+ y MySQL/MariaDB.

---

## Levantar el proyecto localmente

### 1. Clonar el repositorio

```bash
git clone <url-del-repo> robotschool_inventory
cd robotschool_inventory
```

### 2. Crear el archivo de entorno

```bash
cp .env.example .env
```

Editar `.env` con los valores reales (ver sección [Variables de entorno](#variables-de-entorno)):

```bash
# Mínimo obligatorio para levantar:
DB_PASS=una_contraseña_segura
DB_ROOT_PASS=otra_contraseña_root
```

### 3. Levantar los contenedores

```bash
docker compose up -d
```

Esto levanta tres servicios:
- `robotschool_app` — PHP 8.1 + Apache en `http://localhost:8081`
- `robotschool_db` — MySQL 8.0 en `localhost:3308`
- `robotschool_pma` — phpMyAdmin en `http://localhost:8080`

El contenedor `app` espera a que la BD esté sana (`healthcheck`) antes de arrancar.

### 4. Importar la base de datos

Desde phpMyAdmin (`http://localhost:8080`) o desde CLI (reemplaza `<DB_PASS>` por tu contraseña):

```bash
docker exec -i robotschool_db mysql \
  -u rsuser -p<DB_PASS> robotschool_inventory \
  < database/schema.sql && \
  docker exec -i robotschool_db mysql \
  -u rsuser -p<DB_PASS> robotschool_inventory \
  < database/seeds.sql
```

`schema.sql` crea todas las tablas y vistas. `seeds.sql` inserta los datos de referencia iniciales.

### 5. Inicializar los buckets de MinIO (una sola vez)

Con los contenedores corriendo, ejecutar desde el servidor:

```bash
docker compose exec minio sh /scripts/init-minio.sh
```

Esto crea los 6 buckets (`elementos`, `colegios`, `cursos`, `kits`, `despachos`, `documentos`) y les aplica política de descarga pública. El script es idempotente — si los buckets ya existen no falla.

La consola web de MinIO queda disponible en `http://TU-IP-O-DOMINIO:9001`.

### 6. Crear el primer usuario administrador

Con los contenedores corriendo, abrir en el navegador:

```
http://TU-IP-O-DOMINIO:8081/setup_admin.php
```

- Solo accesible desde `localhost` (bloquea otras IPs por seguridad).
- Ingresar nombre, email y contraseña (mínimo 8 caracteres).
- Si el email ya existe, actualiza la contraseña y fuerza `rol_id = 1` (Gerencia).
- **Eliminar `setup_admin.php` del servidor después de usarlo.**

### 6. Iniciar sesión

```
http://TU-IP-O-DOMINIO:8081
```

Redirige automáticamente al login. Ingresar con las credenciales creadas en el paso anterior.

---

## Configuración del servidor

`APP_URL` debe apuntar a la IP pública o dominio real del servidor donde corre la app. Es la variable más crítica del deploy — un valor incorrecto rompe todos los redirects.

```bash
# En el servidor, editar .env:
APP_URL=http://147.93.114.39:8081   # ← IP real o dominio, con el puerto correcto
APP_PORT=8081
```

Reglas:
- **Sin barra final** (`/`) — `config.php` la elimina con `rtrim()` pero evita confusiones
- **Con puerto** si no es 80/443 estándar
- **Con `https://`** si el servidor tiene TLS configurado

Después de cambiar `APP_URL` en `.env`, reiniciar los contenedores para que Docker propague la variable:

```bash
docker compose down && docker compose up -d
```

---

## Detener el proyecto

```bash
docker compose down          # detiene y elimina contenedores (datos persisten en volumen)
docker compose down -v       # detiene y elimina contenedores + volúmenes (borra la BD)
```

---

## Referencia de comandos por tipo de cambio

| Tipo de cambio | Comando en servidor |
|----------------|---------------------|
| PHP, JS, CSS, vistas, SQL | `git fetch origin && git reset --hard origin/main` — sin restart |
| `.env` | `git reset --hard origin/main && docker compose up -d --force-recreate` |
| `Dockerfile` o `docker-compose.yml` | `docker compose up -d --force-recreate` |
| `docker/nginx/conf.d/*.conf` | `docker compose up -d --force-recreate nginx` |
| `docker/php.ini` | `docker compose up -d --force-recreate app` |

> **Nota:** `docker compose restart` nunca recarga volúmenes montados. Siempre usar `--force-recreate` para cambios de configuración.

---

## Variables de entorno

Todas se definen en `.env` (no commitear, está en `.gitignore`). Ver `.env.example` como referencia.

| Variable | Requerida | Descripción | Ejemplo |
|---|---|---|---|
| `APP_ENV` | Sí | Entorno de ejecución | `development` / `production` |
| `APP_PORT` | No | Puerto local de la app | `8081` |
| `APP_URL` | Sí | URL base sin barra final — IP o dominio real del servidor | `http://147.93.114.39:8081` |
| `DB_HOST` | — | Fijado a `db` por docker-compose | `db` |
| `DB_NAME` | No | Nombre de la BD | `robotschool_inventory` |
| `DB_USER` | No | Usuario MySQL de la app | `rsuser` |
| `DB_PASS` | **Sí** | Contraseña del usuario MySQL | — |
| `DB_ROOT_PASS` | **Sí** | Contraseña root MySQL | — |
| `SESSION_TIMEOUT` | No | Segundos de inactividad antes de cerrar sesión | `3600` |
| `ANTHROPIC_API_KEY` | No | API key de Claude AI (banner de cursos) | `sk-ant-...` |
| `MS_CLIENT_ID` | No | Azure App Client ID (login Microsoft) | — |
| `MS_CLIENT_SECRET` | No | Azure App Secret (login Microsoft) | — |
| `MS_TENANT_ID` | No | Tenant ID Azure (`common` para multi-tenant) | `common` |

---

## Estructura de carpetas

```
robotschool_inventory/
│
├── config/
│   ├── config.php              # Configuración central — lee variables de entorno
│   └── config.example.php      # Plantilla de configuración
│
├── includes/
│   ├── Auth.php                # Autenticación, roles, CSRF, permisos granulares
│   ├── Database.php            # Conexión PDO (singleton)
│   ├── helpers.php             # Funciones globales: semáforo, liquidación, auditoría, paginación
│   ├── WooSync.php             # Sincronización con WooCommerce REST API v3
│   ├── header.php              # Layout HTML: sidebar dinámico por rol + topbar
│   └── footer.php              # Cierre del layout + scripts Bootstrap
│
├── modules/                    # Un directorio por módulo funcional
│   ├── academico/              # LMS para colegios
│   ├── auth/                   # Login, logout, OAuth Microsoft
│   ├── barcodes/               # Impresión de códigos de barras
│   ├── colegios/               # Gestión de colegios clientes
│   ├── comercial/              # Requerimientos y convenios comerciales
│   ├── cursos/                 # Cursos de la escuela de robótica
│   ├── despachos/              # Guías de despacho y entregas
│   ├── elementos/              # Inventario de componentes electrónicos
│   ├── importaciones/          # Pedidos de importación y liquidación aduanera
│   ├── inventario/             # Historial de movimientos de stock
│   ├── kits/                   # Constructor y gestión de kits
│   ├── matriculas/             # Matrículas, pagos y estudiantes de la escuela
│   ├── pedidos_tienda/         # Pedidos WooCommerce y logística
│   ├── produccion/             # Tablero kanban de producción
│   ├── reportes/               # Reportes e inventario valorizado
│   └── usuarios/               # Gestión de usuarios y roles
│
├── api/                        # Endpoints JSON para llamadas AJAX internas
│   ├── crear_elemento.php
│   ├── elemento_by_code.php
│   ├── movimiento.php
│   └── pedido_detalle.php
│
├── assets/
│   ├── css/app.css             # Estilos personalizados del sistema
│   ├── js/app.js               # Scripts globales
│   ├── img/                    # Logos del sistema
│   └── uploads/                # Imágenes subidas (elementos, kits, colegios, etc.)
│
├── database/
│   ├── schema.sql              # Esquema completo: tablas y vistas
│   └── seeds.sql               # Datos de referencia iniciales
│
├── docker/
│   ├── php.ini                 # Configuración PHP para el contenedor
│   └── mysql-init/             # Scripts SQL que MySQL ejecuta al inicializar (si existen)
│
├── .env.example                # Plantilla de variables de entorno
├── .htaccess                   # Configuración Apache (mod_rewrite)
├── docker-compose.yml          # Orquestación de servicios
├── Dockerfile                  # Imagen PHP 8.1 + Apache + extensiones
├── index.php                   # Entrada: redirige a login.php
├── login.php                   # Pantalla de inicio de sesión
├── dashboard.php               # Dashboard principal post-login
└── setup_admin.php             # Utilidad de primer arranque — eliminar después de usar
```

---

## Módulos del sistema

| Módulo | Ruta | Descripción |
|---|---|---|
| **Dashboard** | `/dashboard.php` | Resumen ejecutivo: semáforo de stock por categoría, alertas críticas, últimos movimientos |
| **Elementos** | `/modules/elementos/` | Inventario de componentes con semáforo rojo/amarillo/verde/azul, 3 vistas (tabla, tarjetas, categorías), código de barras automático (`RS-ARD-001`) |
| **Movimientos** | `/modules/inventario/` | Historial completo de entradas, salidas, ajustes, devoluciones y transferencias de stock |
| **Códigos de barras** | `/modules/barcodes/` | Impresión masiva de etiquetas con código de barras JsBarcode |
| **Kits** | `/modules/kits/` | Constructor visual de kits desde inventario, gestión de prototipos, sticker de caja imprimible (4 por hoja) |
| **Colegios** | `/modules/colegios/` | CRUD de colegios clientes, asignación de cursos y kits, coordinadores |
| **Importaciones** | `/modules/importaciones/` | Pedidos de importación DHL, liquidación proporcional de flete + aranceles + IVA por peso, gestión de proveedores |
| **Pedidos Tienda** | `/modules/pedidos_tienda/` | Importación CSV de WooCommerce, gestión de estados, stickers de envío (10 por hoja carta), lista imprimible |
| **Producción** | `/modules/produccion/` | Tablero Kanban con 4 estados (pendiente/en proceso/listo/rechazado), 6 fuentes de origen, cronograma de producción |
| **Despachos** | `/modules/despachos/` | Registro de guías de despacho, exportar PDF, historial de entregas |
| **Cursos** | `/modules/cursos/` | Catálogo de cursos de la escuela (Robótica, Python, Minecraft, Roblox, Impresión 3D, Arduino), horarios sabatinos, sedes, generación de banner con Claude AI |
| **Matrículas** | `/modules/matriculas/` | Inscripción de estudiantes, grupos por horario, pagos (Nequi/Daviplata/transferencia/efectivo), cupos en tiempo real vinculados al inventario, calendario semanal de recaudo |
| **Académico LMS** | `/modules/academico/` | LMS para colegios: Cursos → Unidades → Actividades, materiales asignables, sistema de XP y gamificación |
| **Comercial** | `/modules/comercial/` | Pipeline de requerimientos y convenios comerciales con colegios, historial de estados, badge de pendientes en tiempo real |
| **Reportes** | `/modules/reportes/` | Inventario valorizado en COP, exportar Excel/PDF, reporte por categoría |
| **Usuarios** | `/modules/usuarios/` | CRUD de usuarios, asignación de roles, historial de último acceso |

---

## Roles y permisos

El sistema tiene 6 roles fijos. El acceso a cada módulo del sidebar se controla por rol. Gerencia tiene acceso total y no puede ser bloqueada por permisos granulares.

| ID | Rol | Módulos accesibles |
|---|---|---|
| 1 | **Gerencia** | Todo — sin restricciones |
| 2 | **Administración** | Dashboard, Inventario, Kits, Colegios, Pedidos Tienda, Producción, Reportes |
| 3 | **Academia** | Dashboard, Cursos, Matrículas, Pagos, Académico LMS, Colegios |
| 4 | **Producción** | Dashboard, Inventario, Kits, Producción |
| 5 | **Comercial** | Dashboard, Comercial, Convenios, Colegios |
| 6 | **Consulta** | Dashboard, Inventario (solo lectura), Reportes |

Los roles 1 y 2 tienen además acceso a Importaciones y Despachos que no aparecen en la tabla de menú estándar.

Los permisos granulares (ver/crear/editar/eliminar por módulo) se almacenan en la tabla `rol_permisos` y son consultados por `Auth::puede()`.

---

## Integración WooCommerce (opcional)

La sincronización con WooCommerce se configura desde la tabla `configuracion` (grupo `woocommerce`):

| Clave | Descripción |
|---|---|
| `woo_url` | URL base del sitio WordPress (ej: `https://tienda.robotschool.com.co`) |
| `woo_consumer_key` | Consumer Key de WooCommerce REST API v3 |
| `woo_consumer_secret` | Consumer Secret de WooCommerce REST API v3 |
| `woo_campo_colegio` | Campo personalizado del checkout donde el cliente ingresa su colegio |

---

## Login con Microsoft 365 (opcional)

Configurar en `.env`:

```
MS_CLIENT_ID=<Azure App Registration Client ID>
MS_CLIENT_SECRET=<Azure App Registration Secret>
MS_TENANT_ID=common
```

El usuario debe existir previamente en la tabla `usuarios` con el mismo email corporativo. El callback OAuth está en `/modules/auth/ms_callback.php`.

---

## Generador de banners con Claude AI (opcional)

El módulo de Cursos puede generar banners promocionales usando la API de Anthropic:

```
ANTHROPIC_API_KEY=sk-ant-api03-...
```

Sin esta variable, el botón "Generar Banner IA" no aparece en el formulario de cursos.

---

## Notas de seguridad

- `setup_admin.php` solo acepta conexiones desde `127.0.0.1` / `::1`. Eliminarlo tras el primer uso.
- Todos los formularios incluyen token CSRF validado por `Auth::csrfVerify()`.
- Las contraseñas se almacenan con `password_hash()` bcrypt cost 12.
- Las cookies de sesión tienen `HttpOnly`, `SameSite=Lax` y modo estricto activado.
- En producción (`APP_ENV=production`), los errores PHP no se muestran en pantalla.

---

## Changelog

| Versión | Cambios principales |
|---|---|
| **v3.3** | Sedes (Bogotá Norte, Bogotá Sur, Cali), filtro por sede en horarios y matrículas |
| **v3.2** | Módulo de cursos, horarios sabatinos, cupos vinculados al inventario, banner con Claude AI |
| **v3.1** | Cupos de grupos calculados desde stock del inventario en tiempo real |
| **v3.0** | Módulo de matrículas, módulo académico LMS, sistema XP/gamificación |
| **v2.0** | Sistema de roles y permisos granulares por módulo |
| **v1.9** | Nuevas categorías de inventario |
| **v1.8** | Integración WooCommerce: importación CSV y sincronización API |
| **v1.0** | Sistema base: inventario, kits, colegios |

---

## Soporte

**ROBOTSchool Colombia**
- Web: robotschool.com.co
- Email: info@robotschool.com.co
- Tel: 318 654 1859
- Bogotá, Colombia
