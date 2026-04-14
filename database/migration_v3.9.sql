-- migration_v3.9.sql
-- Expande v_ventas_colegios para incluir pedidos de tienda WooCommerce
-- vinculados a colegios (colegio_id IS NOT NULL).
-- Agrega columna `fuente` ('convenio' | 'tienda') para distinguir el canal.

CREATE OR REPLACE VIEW v_ventas_colegios AS

-- Canal: Convenios B2B
SELECT
    co.id                                 AS convenio_id,
    co.codigo                             AS codigo_convenio,
    co.fecha_convenio,
    co.vigencia_inicio,
    co.vigencia_fin,
    co.estado                             AS estado_convenio,
    COALESCE(c.id, 0)                     AS colegio_id,
    COALESCE(c.nombre, co.nombre_colegio) AS colegio,
    COALESCE(c.ciudad, '')                AS ciudad,
    COALESCE(c.tipo, '')                  AS tipo_colegio,
    cc.nombre_kit                         AS kit,
    cc.kit_id,
    COALESCE(cc.num_estudiantes, 0)       AS num_estudiantes,
    COALESCE(cc.valor_kit, 0)             AS valor_kit,
    COALESCE(cc.valor_libro, 0)           AS valor_libro,
    COALESCE(cc.valor_total, 0)           AS valor_linea,
    cc.nombre_curso,
    'convenio'                            AS fuente
FROM convenio_cursos cc
JOIN convenios co ON co.id = cc.convenio_id
LEFT JOIN colegios c ON c.id = co.colegio_id
WHERE co.activo = 1

UNION ALL

-- Canal: Tienda WooCommerce (pedidos asociados a un colegio)
SELECT
    NULL                                                                               AS convenio_id,
    COALESCE(tp.numero_pedido, tp.woo_order_id)                                        AS codigo_convenio,
    tp.fecha_compra                                                                    AS fecha_convenio,
    NULL                                                                               AS vigencia_inicio,
    NULL                                                                               AS vigencia_fin,
    CASE WHEN tp.estado = 'cancelado' THEN 'rechazado' ELSE 'aprobado' END             AS estado_convenio,
    tp.colegio_id,
    COALESCE(c.nombre, tp.colegio_nombre)                                              AS colegio,
    COALESCE(c.ciudad, tp.ciudad, '')                                                  AS ciudad,
    COALESCE(c.tipo, '')                                                               AS tipo_colegio,
    tp.kit_nombre                                                                      AS kit,
    NULL                                                                               AS kit_id,
    tp.cantidad                                                                        AS num_estudiantes,
    CASE WHEN tp.cantidad > 0 THEN ROUND(COALESCE(tp.woo_total,0) / tp.cantidad, 0)
         ELSE 0 END                                                                    AS valor_kit,
    0                                                                                  AS valor_libro,
    COALESCE(tp.woo_total, 0)                                                          AS valor_linea,
    NULL                                                                               AS nombre_curso,
    'tienda'                                                                           AS fuente
FROM tienda_pedidos tp
LEFT JOIN colegios c ON c.id = tp.colegio_id
WHERE tp.colegio_id IS NOT NULL;
