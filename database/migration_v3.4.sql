-- migration_v3.4.sql — Integración WooCommerce bidireccional
-- Ejecutar sobre robotschool_inventory

-- 1. Nuevas columnas en tienda_pedidos
ALTER TABLE `tienda_pedidos`
  ADD COLUMN IF NOT EXISTS `woo_status`          varchar(30)   DEFAULT NULL
      COMMENT 'Estado original en WooCommerce (processing, completed, etc.)'
      AFTER `estado`,
  ADD COLUMN IF NOT EXISTS `woo_payment_method`  varchar(100)  DEFAULT NULL
      COMMENT 'Método de pago reportado por WooCommerce'
      AFTER `woo_status`,
  ADD COLUMN IF NOT EXISTS `woo_total`           decimal(12,2) DEFAULT NULL
      COMMENT 'Total pagado según WooCommerce'
      AFTER `woo_payment_method`,
  ADD COLUMN IF NOT EXISTS `woo_payload`         json          DEFAULT NULL
      COMMENT 'Payload JSON completo del webhook/API para auditoría'
      AFTER `woo_total`,
  ADD COLUMN IF NOT EXISTS `woo_items_payload`   json          DEFAULT NULL
      COMMENT 'Array de line_items del pedido WooCommerce'
      AFTER `woo_payload`,
  ADD COLUMN IF NOT EXISTS `numero_pedido`       varchar(50)   DEFAULT NULL
      COMMENT 'Número de pedido legible (#1234)'
      AFTER `woo_items_payload`;

-- 2. Índice único en woo_order_id para garantizar deduplicación
SET @idx_exists = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'tienda_pedidos'
    AND INDEX_NAME   = 'uq_woo_order_id'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE tienda_pedidos ADD UNIQUE INDEX uq_woo_order_id (woo_order_id)',
  'SELECT "índice uq_woo_order_id ya existe"'
);
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
