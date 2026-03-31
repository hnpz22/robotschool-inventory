-- ============================================================
-- migration_roles_permisos.sql
-- Poblar rol_permisos con los accesos base del sistema.
-- Ejecutar una vez en base de datos nueva o para resetear al
-- estado base configurado por Gerencia.
--
-- Permisos configurados según indicaciones:
--   Gerencia (1)      → todo
--   Administración (2) → pedidos tienda, producción, alistamiento
--   Academia (3)       → cursos, matrículas, pagos, académico, colegios
--   Producción (4)     → producción, alistamiento
--   Comercial (5)      → comercial, convenios, colegios
--   Consulta (6)       → inventario, reportes (solo ver)
-- ============================================================

TRUNCATE TABLE rol_permisos;

INSERT INTO rol_permisos (rol_id, modulo, ver, crear, editar, eliminar) VALUES

-- ── Gerencia (1): acceso total ─────────────────────────────────
(1,'dashboard',    1,1,1,1),
(1,'inventario',   1,1,1,1),
(1,'kits',         1,1,1,1),
(1,'colegios',     1,1,1,1),
(1,'categorias',   1,1,1,1),
(1,'pedidos_tienda',1,1,1,1),
(1,'produccion',   1,1,1,1),
(1,'alistamiento', 1,1,1,1),
(1,'pedidos',      1,1,1,1),
(1,'proveedores',  1,1,1,1),
(1,'despachos',    1,1,1,1),
(1,'cursos',       1,1,1,1),
(1,'matriculas',   1,1,1,1),
(1,'pagos',        1,1,1,1),
(1,'academico',    1,1,1,1),
(1,'comercial',    1,1,1,1),
(1,'convenios',    1,1,1,1),
(1,'reportes',     1,1,1,1),
(1,'usuarios',     1,1,1,1),
(1,'config',       1,1,1,1),

-- ── Administración (2): pedidos tienda, producción, alistamiento ─
(2,'dashboard',     1,0,0,0),
(2,'pedidos_tienda',1,1,1,0),
(2,'produccion',    1,1,1,0),
(2,'alistamiento',  1,1,1,0),

-- ── Academia (3) ───────────────────────────────────────────────
(3,'dashboard',  1,0,0,0),
(3,'colegios',   1,0,0,0),
(3,'cursos',     1,1,1,0),
(3,'matriculas', 1,1,1,0),
(3,'pagos',      1,1,1,0),
(3,'academico',  1,1,1,0),

-- ── Producción (4): produccion y alistamiento ──────────────────
(4,'dashboard',   1,0,0,0),
(4,'produccion',  1,1,1,0),
(4,'alistamiento',1,1,1,0),

-- ── Comercial (5) ──────────────────────────────────────────────
(5,'dashboard', 1,0,0,0),
(5,'colegios',  1,0,0,0),
(5,'comercial', 1,1,1,0),
(5,'convenios', 1,1,1,0),

-- ── Consulta (6): solo ver ─────────────────────────────────────
(6,'dashboard',  1,0,0,0),
(6,'inventario', 1,0,0,0),
(6,'reportes',   1,0,0,0);
