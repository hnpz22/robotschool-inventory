-- migration_v3.6.sql
-- Tabla de ítems de pedidos de tienda — permite múltiples kits por orden
-- Los pedidos existentes (single-kit) quedan sin filas en esta tabla;
-- ver.php los sigue mostrando desde tienda_pedidos.kit_nombre + cantidad.

SET @tbl = (SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tienda_pedido_items');
SET @sql = IF(@tbl = 0,
  'CREATE TABLE tienda_pedido_items (
    id          INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    pedido_id   INT UNSIGNED    NOT NULL,
    kit_id      INT UNSIGNED    DEFAULT NULL,
    kit_nombre  VARCHAR(200)    NOT NULL,
    colegio_id  INT UNSIGNED    DEFAULT NULL,
    colegio_nombre VARCHAR(200) DEFAULT NULL,
    curso_id    INT UNSIGNED    DEFAULT NULL,
    cantidad    SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    precio_unit DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    subtotal    DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
    PRIMARY KEY (id),
    KEY idx_pedido (pedido_id),
    CONSTRAINT fk_tpi_pedido
      FOREIGN KEY (pedido_id) REFERENCES tienda_pedidos(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    COMMENT ''Ítems individuales de cada pedido manual multi-producto''',
  'SELECT "tienda_pedido_items ya existe"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
