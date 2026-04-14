-- migration_v3.8.sql — Vistas para Reportes Comerciales
-- Ejecutar sobre robotschool_inventory
-- Seguro de re-ejecutar (CREATE OR REPLACE VIEW)
-- Autor: Sistema | Fecha: 2026-04-14
--
-- APLICAR:
--   docker exec -i robotschool_db mysql -u rsuser -p<DB_PASS> robotschool_inventory < database/migration_v3.8.sql

-- ─────────────────────────────────────────────────────────────────────────
-- Vista 1: v_ventas_colegios
-- Une convenio_cursos + convenios + colegios
-- Fuente de verdad comercial: qué kit se vendió a qué colegio, a qué precio
-- ─────────────────────────────────────────────────────────────────────────
CREATE OR REPLACE VIEW v_ventas_colegios AS
SELECT
    co.id                                       AS convenio_id,
    co.codigo                                   AS codigo_convenio,
    co.fecha_convenio,
    co.vigencia_inicio,
    co.vigencia_fin,
    co.estado                                   AS estado_convenio,
    COALESCE(c.id, 0)                           AS colegio_id,
    COALESCE(c.nombre, co.nombre_colegio)       AS colegio,
    COALESCE(c.ciudad, '')                      AS ciudad,
    COALESCE(c.tipo, '')                        AS tipo_colegio,
    cc.nombre_kit                               AS kit,
    cc.kit_id,
    COALESCE(cc.num_estudiantes, 0)             AS num_estudiantes,
    COALESCE(cc.valor_kit, 0)                   AS valor_kit,
    COALESCE(cc.valor_libro, 0)                 AS valor_libro,
    COALESCE(cc.valor_total, 0)                 AS valor_linea,
    cc.nombre_curso
FROM convenio_cursos cc
JOIN  convenios co ON co.id = cc.convenio_id
LEFT JOIN colegios c  ON c.id  = co.colegio_id
WHERE co.activo = 1;

-- ─────────────────────────────────────────────────────────────────────────
-- Vista 2: v_ingresos_mensuales
-- Consolida los 3 canales de ingreso de RobotSchool por mes
--   tienda    → pedidos WooCommerce (woo_total)
--   convenios → contratos B2B aprobados (valor_total)
--   escuela   → pagos de matrículas (valor_pagado)
-- ─────────────────────────────────────────────────────────────────────────
CREATE OR REPLACE VIEW v_ingresos_mensuales AS

SELECT
    'tienda'                                AS fuente,
    DATE_FORMAT(fecha_compra, '%Y-%m')      AS mes,
    COUNT(*)                                AS cantidad,
    SUM(COALESCE(woo_total, 0))             AS total_cop
FROM tienda_pedidos
WHERE estado NOT IN ('cancelado')
GROUP BY DATE_FORMAT(fecha_compra, '%Y-%m')

UNION ALL

SELECT
    'convenios'                             AS fuente,
    DATE_FORMAT(fecha_convenio, '%Y-%m')    AS mes,
    COUNT(*)                                AS cantidad,
    SUM(COALESCE(valor_total, 0))           AS total_cop
FROM convenios
WHERE estado = 'aprobado'
  AND activo = 1
GROUP BY DATE_FORMAT(fecha_convenio, '%Y-%m')

UNION ALL

SELECT
    'escuela'                               AS fuente,
    DATE_FORMAT(fecha_pago, '%Y-%m')        AS mes,
    COUNT(*)                                AS cantidad,
    SUM(COALESCE(valor_pagado, 0))          AS total_cop
FROM pagos
WHERE estado = 'pagado'
GROUP BY DATE_FORMAT(fecha_pago, '%Y-%m');
