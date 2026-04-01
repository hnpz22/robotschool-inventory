-- migration_v3.4.sql — Integración WooCommerce bidireccional
-- Ejecutar sobre robotschool_inventory
-- Compatible con MySQL 8.0 (no usa ADD COLUMN IF NOT EXISTS de MariaDB)

-- 1. Nuevas columnas en tienda_pedidos (idempotente via INFORMATION_SCHEMA)
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tienda_pedidos' AND COLUMN_NAME='woo_status');
SET @sql = IF(@col=0,
  "ALTER TABLE tienda_pedidos ADD COLUMN woo_status varchar(30) DEFAULT NULL COMMENT 'Estado original en WooCommerce (processing, completed, etc.)' AFTER estado",
  'SELECT "woo_status ya existe"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tienda_pedidos' AND COLUMN_NAME='woo_payment_method');
SET @sql = IF(@col=0,
  "ALTER TABLE tienda_pedidos ADD COLUMN woo_payment_method varchar(100) DEFAULT NULL COMMENT 'Método de pago reportado por WooCommerce' AFTER woo_status",
  'SELECT "woo_payment_method ya existe"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tienda_pedidos' AND COLUMN_NAME='woo_total');
SET @sql = IF(@col=0,
  "ALTER TABLE tienda_pedidos ADD COLUMN woo_total decimal(12,2) DEFAULT NULL COMMENT 'Total pagado según WooCommerce' AFTER woo_payment_method",
  'SELECT "woo_total ya existe"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tienda_pedidos' AND COLUMN_NAME='woo_payload');
SET @sql = IF(@col=0,
  "ALTER TABLE tienda_pedidos ADD COLUMN woo_payload json DEFAULT NULL COMMENT 'Payload JSON completo del webhook/API para auditoría' AFTER woo_total",
  'SELECT "woo_payload ya existe"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tienda_pedidos' AND COLUMN_NAME='woo_items_payload');
SET @sql = IF(@col=0,
  "ALTER TABLE tienda_pedidos ADD COLUMN woo_items_payload json DEFAULT NULL COMMENT 'Array de line_items del pedido WooCommerce' AFTER woo_payload",
  'SELECT "woo_items_payload ya existe"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tienda_pedidos' AND COLUMN_NAME='numero_pedido');
SET @sql = IF(@col=0,
  "ALTER TABLE tienda_pedidos ADD COLUMN numero_pedido varchar(50) DEFAULT NULL COMMENT 'Número de pedido legible (#1234)' AFTER woo_items_payload",
  'SELECT "numero_pedido ya existe"');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 2. Índice único en woo_order_id para garantizar deduplicación
SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='tienda_pedidos' AND INDEX_NAME='uq_woo_order_id'
);
SET @sql = IF(@idx_exists=0,
  'ALTER TABLE tienda_pedidos ADD UNIQUE INDEX uq_woo_order_id (woo_order_id)',
  'SELECT "índice uq_woo_order_id ya existe"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. Tabla de log de webhooks recibidos (debugging y auditoría)
CREATE TABLE IF NOT EXISTS `woo_webhook_log` (
  `id`           int UNSIGNED NOT NULL AUTO_INCREMENT,
  `woo_order_id` varchar(30)  DEFAULT NULL,
  `evento`       varchar(100) DEFAULT NULL,
  `status_code`  varchar(20)  DEFAULT NULL,
  `resultado`    enum('ok','duplicado','ignorado','error') NOT NULL DEFAULT 'ok',
  `detalle`      text         DEFAULT NULL,
  `payload_hash` varchar(64)  DEFAULT NULL COMMENT 'SHA256 del body para detectar duplicados',
  `received_at`  datetime     NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_woo_order_id` (`woo_order_id`),
  KEY `idx_received_at`  (`received_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
