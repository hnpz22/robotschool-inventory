-- migration_v3.7.sql — Estado de pago en pedidos manuales
-- Agrega estado_pago, pagado_at y pagado_por a tienda_pedidos
-- Compatible con MySQL 8.0

SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tienda_pedidos' AND COLUMN_NAME='estado_pago');
SET @sql = IF(@col=0,
  "ALTER TABLE tienda_pedidos ADD COLUMN estado_pago ENUM('pendiente','aprobado') NOT NULL DEFAULT 'pendiente' COMMENT 'Estado de pago del pedido manual' AFTER notas_internas",
  'SELECT "estado_pago ya existe"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tienda_pedidos' AND COLUMN_NAME='pagado_at');
SET @sql = IF(@col=0,
  "ALTER TABLE tienda_pedidos ADD COLUMN pagado_at datetime DEFAULT NULL COMMENT 'Timestamp de cuando se registró el pago' AFTER estado_pago",
  'SELECT "pagado_at ya existe"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tienda_pedidos' AND COLUMN_NAME='pagado_por');
SET @sql = IF(@col=0,
  "ALTER TABLE tienda_pedidos ADD COLUMN pagado_por int UNSIGNED DEFAULT NULL COMMENT 'FK a usuarios.id — quien registró el pago' AFTER pagado_at",
  'SELECT "pagado_por ya existe"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;
