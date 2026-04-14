# CLAUDE.md — ROBOTSchool Inventory

> Este archivo es cargado automáticamente por Claude Code en cada sesión.
> Para el contexto técnico completo del proyecto, leer **AGENTS.md** antes de cualquier cambio.

---

## TL;DR para arrancar rápido

- **Stack:** PHP 8.1 vanilla · MySQL 8.0 · Bootstrap 5.3.2 · PDO puro · Docker Compose
- **Sin frameworks.** Sin ORM. Sin bundler. Sin React/Vue.
- **Patrón obligatorio:** `require_once` config + Database + Auth + helpers → `Auth::check()` → lógica → `header.php` → HTML → `footer.php`
- **CSRF en todo POST:** `Auth::csrfToken()` en el form, `Auth::csrfVerify()` al procesar
- **Auditoría en operaciones importantes:** `auditoria('accion', 'tabla', $id, $antes, $despues)`
- **Migraciones:** crear `database/migration_vX.X.sql` — nunca editar `database/seed.sql`
- **Deploy:** `git fetch origin && git reset --hard origin/main` en servidor — no requiere restart

## Contexto de negocio

RobotSchool Colombia vende kits de robótica a colegios (convenios B2B) y opera una escuela de robótica para niños. Este sistema cubre: inventario de componentes, armado de kits, pedidos WooCommerce, producción, colegios, escuela, LMS académico y comercial.

## Para el detalle completo

→ Leer `AGENTS.md` — cubre stack, Auth, PDO, convenciones de nombres, tablas BD, vistas SQL, helpers, módulos, integraciones externas (WooCommerce, Microsoft OAuth, Anthropic), deployment y archivos críticos que no tocar sin análisis.

---

## Vault de trabajo

El segundo cerebro del desarrollador está en `/Users/hnpz22/Documents/co-op/`.
Toda la documentación de este proyecto (reuniones, features, procesos, planes) vive ahí.
