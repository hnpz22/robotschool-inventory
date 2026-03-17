-- ============================================================
--  002_pedidos_flujo_completo.sql
--  Migración: flujo completo de pedidos tienda
--
--  Ejecutar en el servidor:
--    docker exec -i robotschool_db mysql \
--      -u rsuser -p<DB_PASS> robotschool_inventory \
--      < database/migrations/002_pedidos_flujo_completo.sql
--
--  Idempotente: usa IF NOT EXISTS / MODIFY seguro. Puede
--  correrse más de una vez sin romper nada.
-- ============================================================

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ──────────────────────────────────────────────────────────────
-- 1. Ampliar el ENUM de estado con los nuevos valores
--    MySQL permite agregar valores al ENUM con un simple MODIFY.
--    Incluye todos los anteriores + los nuevos para no perder datos.
-- ──────────────────────────────────────────────────────────────
ALTER TABLE tienda_pedidos
  MODIFY COLUMN `estado` ENUM(
    'pendiente',
    'aprobado',
    'en_produccion',
    'listo_produccion',
    'listo_envio',        -- conservado para no romper filas históricas
    'en_alistamiento',
    'despachado',
    'entregado',
    'cancelado'
  ) NOT NULL DEFAULT 'pendiente';

-- ──────────────────────────────────────────────────────────────
-- 2. Migrar listo_envio → listo_produccion en filas existentes
-- ──────────────────────────────────────────────────────────────
UPDATE tienda_pedidos
  SET estado = 'listo_produccion'
  WHERE estado = 'listo_envio';

-- ──────────────────────────────────────────────────────────────
-- 3. Nuevas columnas (IF NOT EXISTS vía INFORMATION_SCHEMA)
-- ──────────────────────────────────────────────────────────────

-- 3a. cantidad — número de kits del pedido
SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'tienda_pedidos'
    AND COLUMN_NAME  = 'cantidad'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE tienda_pedidos ADD COLUMN `cantidad` SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER `kit_nombre`',
  'SELECT 1 -- cantidad ya existe'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3b. instrucciones_especiales — indicaciones de entrega del cliente
SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'tienda_pedidos'
    AND COLUMN_NAME  = 'instrucciones_especiales'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE tienda_pedidos ADD COLUMN `instrucciones_especiales` TEXT DEFAULT NULL AFTER `notas_internas`',
  'SELECT 1 -- instrucciones_especiales ya existe'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3c. aprobado_por — usuario que aprobó el pedido (FK a usuarios)
SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'tienda_pedidos'
    AND COLUMN_NAME  = 'aprobado_por'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE tienda_pedidos ADD COLUMN `aprobado_por` INT UNSIGNED DEFAULT NULL AFTER `asignado_a`',
  'SELECT 1 -- aprobado_por ya existe'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3d. aprobado_at — timestamp de aprobación
SET @col_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'tienda_pedidos'
    AND COLUMN_NAME  = 'aprobado_at'
);
SET @sql = IF(@col_exists = 0,
  'ALTER TABLE tienda_pedidos ADD COLUMN `aprobado_at` DATETIME DEFAULT NULL AFTER `aprobado_por`',
  'SELECT 1 -- aprobado_at ya existe'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ──────────────────────────────────────────────────────────────
-- 4. FK aprobado_por → usuarios (solo si no existe ya)
-- ──────────────────────────────────────────────────────────────
SET @fk_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA      = DATABASE()
    AND TABLE_NAME        = 'tienda_pedidos'
    AND CONSTRAINT_NAME   = 'fk_tp_aprobado'
    AND CONSTRAINT_TYPE   = 'FOREIGN KEY'
);
SET @sql = IF(@fk_exists = 0,
  'ALTER TABLE tienda_pedidos ADD CONSTRAINT fk_tp_aprobado FOREIGN KEY (aprobado_por) REFERENCES usuarios(id) ON DELETE SET NULL',
  'SELECT 1 -- fk_tp_aprobado ya existe'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ──────────────────────────────────────────────────────────────
-- 5. Índice en aprobado_por (para JOINs con usuarios)
-- ──────────────────────────────────────────────────────────────
SET @idx_exists = (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME   = 'tienda_pedidos'
    AND INDEX_NAME   = 'idx_aprobado_por'
);
SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE tienda_pedidos ADD INDEX idx_aprobado_por (aprobado_por)',
  'SELECT 1 -- idx_aprobado_por ya existe'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ──────────────────────────────────────────────────────────────
-- 6. Recrear la vista v_tienda_pedidos con los nuevos campos y
--    la lógica de semáforo actualizada
-- ──────────────────────────────────────────────────────────────
DROP VIEW IF EXISTS `v_tienda_pedidos`;

CREATE VIEW `v_tienda_pedidos` AS
SELECT
  p.*,
  DATEDIFF(CURDATE(), p.fecha_compra)                  AS dias_transcurridos,
  CASE
    WHEN p.estado IN ('entregado','cancelado','despachado') THEN 'completado'
    WHEN DATEDIFF(CURDATE(), p.fecha_compra) <= 5          THEN 'verde'
    WHEN DATEDIFF(CURDATE(), p.fecha_compra) <= 7          THEN 'amarillo'
    ELSE                                                        'rojo'
  END                                                  AS semaforo,
  col.nombre                                           AS colegio_bd,
  u.nombre                                             AS asignado_nombre,
  ua.nombre                                            AS aprobado_nombre
FROM tienda_pedidos p
LEFT JOIN colegios col ON col.id = p.colegio_id
LEFT JOIN usuarios u   ON u.id  = p.asignado_a
LEFT JOIN usuarios ua  ON ua.id = p.aprobado_por;

SET foreign_key_checks = 1;

-- ──────────────────────────────────────────────────────────────
-- FIN
-- ──────────────────────────────────────────────────────────────
