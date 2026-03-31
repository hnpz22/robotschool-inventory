-- ============================================================
-- migration_tipo_despacho.sql
-- Agrega tipo_despacho y sede_recogida a tienda_pedidos.
-- Permite distinguir entre envío con transportador y
-- recogida local en sede.
-- ============================================================

ALTER TABLE tienda_pedidos
  ADD COLUMN IF NOT EXISTS tipo_despacho ENUM('envio','recogida_local') NULL DEFAULT 'envio'
    COMMENT 'Tipo de egreso: envío con transportador o recogida local en sede'
    AFTER transportadora,
  ADD COLUMN IF NOT EXISTS sede_recogida VARCHAR(100) NULL DEFAULT NULL
    COMMENT 'Sede donde se recoge el pedido (cuando tipo_despacho = recogida_local)'
    AFTER tipo_despacho;
