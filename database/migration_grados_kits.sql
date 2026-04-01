-- migration_grados_kits.sql
-- Agrega columna grados a colegios (qué grados ofrece)
-- Agrega columna grado a kits (grado al que está dirigido)
-- Hace nullable nivel en colegios y kits para evitar errores de insert

-- 1. colegios.grados — lista separada por coma (transicion,1,2,...,11)
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'colegios' AND COLUMN_NAME = 'grados');
SET @sql = IF(@col = 0,
  'ALTER TABLE colegios ADD COLUMN grados VARCHAR(150) NULL DEFAULT NULL
   COMMENT "Grados que ofrece separados por coma: transicion,1,2,...,11" AFTER nivel',
  'SELECT "colegios.grados ya existe"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 2. kits.grado — grado escolar al que está dirigido el kit
SET @col = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kits' AND COLUMN_NAME = 'grado');
SET @sql = IF(@col = 0,
  'ALTER TABLE kits ADD COLUMN grado VARCHAR(20) NULL DEFAULT NULL
   COMMENT "Grado escolar al que está dirigido el kit (ej: 6, transicion)" AFTER colegio_id',
  'SELECT "kits.grado ya existe"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. colegios.nivel → nullable para que inserts sin ese campo no fallen
SET @is_not_null = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'colegios'
    AND COLUMN_NAME = 'nivel' AND IS_NULLABLE = 'NO');
SET @sql = IF(@is_not_null > 0,
  'ALTER TABLE colegios MODIFY COLUMN nivel VARCHAR(100) NULL DEFAULT NULL',
  'SELECT "colegios.nivel ya es nullable"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 4. kits.nivel → nullable para que inserts sin ese campo no fallen
SET @is_not_null = (SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'kits'
    AND COLUMN_NAME = 'nivel' AND IS_NULLABLE = 'NO');
SET @sql = IF(@is_not_null > 0,
  'ALTER TABLE kits MODIFY COLUMN nivel VARCHAR(100) NULL DEFAULT NULL',
  'SELECT "kits.nivel ya es nullable"');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 5. tipos_caja: S (Sanduchera), L, XL si no existen aún
SET @ex_s  = (SELECT COUNT(*) FROM tipos_caja WHERE nombre = 'S');
SET @sql   = IF(@ex_s  = 0, 'INSERT INTO tipos_caja (nombre, activo) VALUES (''S'',  1)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ex_l  = (SELECT COUNT(*) FROM tipos_caja WHERE nombre = 'L');
SET @sql   = IF(@ex_l  = 0, 'INSERT INTO tipos_caja (nombre, activo) VALUES (''L'',  1)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @ex_xl = (SELECT COUNT(*) FROM tipos_caja WHERE nombre = 'XL');
SET @sql   = IF(@ex_xl = 0, 'INSERT INTO tipos_caja (nombre, activo) VALUES (''XL'', 1)', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
