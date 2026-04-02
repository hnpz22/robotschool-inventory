-- ============================================================
-- migration_tipo_despacho.sql
-- Agrega tipo_despacho y sede_recogida a tienda_pedidos.
-- Permite distinguir entre envío con transportador y
-- recogida local en sede.
-- Compatible con MySQL 8.0 (usa INFORMATION_SCHEMA, no ADD COLUMN IF NOT EXISTS de MariaDB)
-- ============================================================

SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tienda_pedidos' AND COLUMN_NAME='tipo_despacho');
SET @sql = IF(@col=0,
  "ALTER TABLE tienda_pedidos ADD COLUMN tipo_despacho ENUM('envio','recogida_local') NULL DEFAULT 'envio' COMMENT 'Tipo de egreso: envío con transportador o recogida local en sede' AFTER transportadora",
  'SELECT "tipo_despacho ya existe"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tienda_pedidos' AND COLUMN_NAME='sede_recogida');
SET @sql = IF(@col=0,
  "ALTER TABLE tienda_pedidos ADD COLUMN sede_recogida VARCHAR(100) NULL DEFAULT NULL COMMENT 'Sede donde se recoge el pedido (cuando tipo_despacho = recogida_local)' AFTER tipo_despacho",
  'SELECT "sede_recogida ya existe"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
