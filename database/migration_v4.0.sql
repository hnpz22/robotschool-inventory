-- migration_v4.0.sql
-- Backfill: garantiza que toda categoría tenga fila en codigos_secuencia.
-- Las categorías creadas desde la UI (modules/elementos/categorias.php) antes
-- del fix no insertaban este contador, lo que provocaba el error
-- "Categoría no encontrada" al crear el primer elemento.

INSERT INTO codigos_secuencia (categoria_id, ultimo_numero)
SELECT c.id, COALESCE(MAX(CAST(SUBSTRING_INDEX(e.codigo, '-', -1) AS UNSIGNED)), 0)
FROM categorias c
LEFT JOIN elementos e ON e.categoria_id = c.id
LEFT JOIN codigos_secuencia cs ON cs.categoria_id = c.id
WHERE cs.categoria_id IS NULL
GROUP BY c.id;
