-- migration_v3.5.sql — Columnas faltantes en tienda_pedidos + ENUM estado actualizado
-- Ejecutar sobre robotschool_inventory
-- Prerequisito: migration_v3.4.sql ya aplicado
-- Nota: ADD COLUMN IF NOT EXISTS no es soportado por MySQL 8.0 — se usa
--       INFORMATION_SCHEMA + PREPARE/EXECUTE para idempotencia.

-- 1. Ampliar ENUM estado con valores del flujo actual
--    'listo_envio' se mantiene al final como valor legacy
ALTER TABLE `tienda_pedidos`
  MODIFY COLUMN `estado` enum(
    'pendiente',
    'aprobado',
    'en_produccion',
    'listo_produccion',
    'en_alistamiento',
    'listo_envio',
    'despachado',
    'entregado',
    'cancelado'
  ) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'pendiente';

-- 2. Columna cantidad: cuántos kits/unidades incluye el pedido
--    Origen: campo 'Item #1 Quantity' / 'Quantity' / 'Cantidad' del CSV de WooCommerce
--    WooSync (API/webhook) no lo popula — DEFAULT 1 garantiza valor siempre
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tienda_pedidos' AND COLUMN_NAME = 'cantidad');
SET @sql = IF(@col = 0,
  'ALTER TABLE tienda_pedidos ADD COLUMN cantidad smallint(5) UNSIGNED NOT NULL DEFAULT 1 COMMENT "Numero de kits/unidades del pedido. Parseado del CSV; DEFAULT 1 para pedidos de API." AFTER kit_nombre',
  'SELECT "cantidad ya existe"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. Columna instrucciones_especiales: Customer Note del checkout WooCommerce
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tienda_pedidos' AND COLUMN_NAME = 'instrucciones_especiales');
SET @sql = IF(@col = 0,
  'ALTER TABLE tienda_pedidos ADD COLUMN instrucciones_especiales text COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT "Nota especial del cliente al hacer el pedido (Customer Note del checkout)" AFTER notas_internas',
  'SELECT "instrucciones_especiales ya existe"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4a. Columna aprobado_por: FK a usuarios.id
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tienda_pedidos' AND COLUMN_NAME = 'aprobado_por');
SET @sql = IF(@col = 0,
  'ALTER TABLE tienda_pedidos ADD COLUMN aprobado_por int(10) UNSIGNED DEFAULT NULL COMMENT "FK a usuarios.id — quien aprobo el pedido" AFTER instrucciones_especiales',
  'SELECT "aprobado_por ya existe"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4b. Columna aprobado_at: timestamp de aprobación
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tienda_pedidos' AND COLUMN_NAME = 'aprobado_at');
SET @sql = IF(@col = 0,
  'ALTER TABLE tienda_pedidos ADD COLUMN aprobado_at datetime DEFAULT NULL COMMENT "Timestamp de cuando fue aprobado" AFTER aprobado_por',
  'SELECT "aprobado_at ya existe"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5. Índice en aprobado_por para el LEFT JOIN de pedidos_tienda/index.php
SET @idx = (SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tienda_pedidos' AND INDEX_NAME = 'fk_tp_aprobado');
SET @sql = IF(@idx = 0,
  'ALTER TABLE tienda_pedidos ADD KEY fk_tp_aprobado (aprobado_por)',
  'SELECT "indice fk_tp_aprobado ya existe"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
