<?php
/**
 * fix_encoding.php — Corrector de doble-encoding UTF-8/Latin-1
 *
 * Detecta valores almacenados con doble-encoding (bytes UTF-8 de un carácter
 * como ó/ñ/á fueron interpretados como Latin-1 al importar el SQL, y luego
 * re-codificados en UTF-8 por MySQL, produciendo "Ã³" en lugar de "ó").
 *
 * MODO DRY-RUN (por defecto): muestra qué corregiría, sin tocar la BD.
 * MODO APLICAR:               ?aplicar=1  — escribe los cambios en la BD.
 *
 * ⚠  ELIMINA ESTE ARCHIVO DEL SERVIDOR DESPUÉS DE USARLO.
 */

// ── Seguridad: solo localhost ─────────────────────────────────────────────────
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
if (!in_array($ip, ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    die('403 Forbidden — solo accesible desde localhost.');
}

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/Database.php';

$db = Database::get();
$db->exec("SET NAMES utf8mb4");

$aplicar  = isset($_GET['aplicar']) && $_GET['aplicar'] === '1';
$dryRun   = !$aplicar;

header('Content-Type: text/plain; charset=UTF-8');

echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║   fix_encoding.php — ROBOTSchool Inventory                  ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "Fecha    : " . date('Y-m-d H:i:s') . "\n";
echo "Modo     : " . ($dryRun ? "DRY-RUN (sin cambios) — agrega ?aplicar=1 para escribir" : "APLICAR — escribiendo en la BD") . "\n";
echo "Base de datos: " . DB_NAME . "\n";
echo "\n";
echo "⚠  ELIMINA ESTE ARCHIVO DEL SERVIDOR DESPUÉS DE USARLO:\n";
echo "   docker exec robotschool_app rm /var/www/html/fix_encoding.php\n";
echo str_repeat("─", 66) . "\n\n";

// ── Detección de doble-encoding ───────────────────────────────────────────────
//
// Mecanismo: si 'ó' (UTF-8: 0xC3 0xB3) fue importado con cliente latin1,
// MySQL recibió 'Ã³' y lo almacenó en UTF-8 como 0xC3 0x83 0xC2 0xB3.
// La corrección es: tomar el string UTF-8 'Ã³', convertir a ISO-8859-1
// para recuperar los bytes originales 0xC3 0xB3, y guardarlos de nuevo.
// MySQL los leerá como UTF-8 correctamente: 'ó'.
//
function necesitaCorreccion(string $valor): bool
{
    if ($valor === '' || !mb_check_encoding($valor, 'UTF-8')) {
        return false;
    }
    $decodificado = mb_convert_encoding($valor, 'ISO-8859-1', 'UTF-8');
    // Si el resultado es UTF-8 válido y diferente → estaba doble-codificado
    return $decodificado !== $valor
        && $decodificado !== ''
        && mb_check_encoding($decodificado, 'UTF-8');
}

function corregir(string $valor): string
{
    return mb_convert_encoding($valor, 'ISO-8859-1', 'UTF-8');
}

// ── Descubrir tablas y columnas de texto via INFORMATION_SCHEMA ───────────────
$tablas = $db->query("
    SELECT TABLE_NAME
    FROM   INFORMATION_SCHEMA.TABLES
    WHERE  TABLE_SCHEMA = DATABASE()
      AND  TABLE_TYPE   = 'BASE TABLE'
    ORDER  BY TABLE_NAME
")->fetchAll(PDO::FETCH_COLUMN);

$totalFilas   = 0;
$totalCampos  = 0;

foreach ($tablas as $tabla) {

    // Columnas de texto en esta tabla
    $columnasTxt = $db->query("
        SELECT COLUMN_NAME, DATA_TYPE
        FROM   INFORMATION_SCHEMA.COLUMNS
        WHERE  TABLE_SCHEMA = DATABASE()
          AND  TABLE_NAME   = " . $db->quote($tabla) . "
          AND  DATA_TYPE    IN ('varchar','text','mediumtext','longtext','tinytext')
        ORDER  BY ORDINAL_POSITION
    ")->fetchAll(PDO::FETCH_ASSOC);

    if (empty($columnasTxt)) {
        continue; // tabla sin columnas de texto — saltar
    }

    // Columnas que forman la PK
    $pkColumnas = $db->query("
        SELECT COLUMN_NAME
        FROM   INFORMATION_SCHEMA.KEY_COLUMN_USAGE
        WHERE  TABLE_SCHEMA     = DATABASE()
          AND  TABLE_NAME       = " . $db->quote($tabla) . "
          AND  CONSTRAINT_NAME  = 'PRIMARY'
        ORDER  BY ORDINAL_POSITION
    ")->fetchAll(PDO::FETCH_COLUMN);

    if (empty($pkColumnas)) {
        echo "  [SKIP] `$tabla` — sin PK definida\n";
        continue;
    }

    // Columnas a seleccionar: PK + columnas de texto
    $nombresTxt   = array_column($columnasTxt, 'COLUMN_NAME');
    $seleccionar  = array_unique(array_merge($pkColumnas, $nombresTxt));
    $selSql       = implode(', ', array_map(fn($c) => "`$c`", $seleccionar));

    // WHERE para UPDATE usando todos los campos de la PK
    $whereSql = implode(' AND ', array_map(fn($c) => "`$c` = ?", $pkColumnas));

    try {
        $filas = $db->query("SELECT {$selSql} FROM `{$tabla}`")->fetchAll();
    } catch (Exception $e) {
        echo "  [ERROR] `$tabla` — {$e->getMessage()}\n";
        continue;
    }

    $corrTable = 0;

    foreach ($filas as $fila) {
        $actualizaciones = [];
        $logLineas       = [];

        foreach ($nombresTxt as $col) {
            $valor = $fila[$col] ?? null;
            if ($valor === null || $valor === '') {
                continue;
            }
            if (necesitaCorreccion($valor)) {
                $corregido         = corregir($valor);
                $actualizaciones[$col] = $corregido;
                $preview           = mb_substr($valor,    0, 50);
                $previewFix        = mb_substr($corregido, 0, 50);
                $logLineas[]       = "    {$col}: «{$preview}» → «{$previewFix}»";
            }
        }

        if (empty($actualizaciones)) {
            continue;
        }

        // Valores de la PK para el WHERE
        $pkValores = array_map(fn($c) => $fila[$c], $pkColumnas);
        $pkLabel   = implode('+', array_map(
            fn($c, $v) => "{$c}={$v}",
            $pkColumnas,
            $pkValores
        ));

        echo "[{$tabla}] {$pkLabel}\n";
        echo implode("\n", $logLineas) . "\n\n";

        if (!$dryRun) {
            $setSql    = implode(', ', array_map(fn($c) => "`$c` = ?", array_keys($actualizaciones)));
            $valores   = array_values($actualizaciones);
            // Añadir valores de PK al final para el WHERE
            foreach ($pkValores as $v) {
                $valores[] = $v;
            }
            try {
                $stmt = $db->prepare("UPDATE `{$tabla}` SET {$setSql} WHERE {$whereSql}");
                $stmt->execute($valores);
                $totalCampos += count($actualizaciones);
            } catch (Exception $e) {
                echo "  [ERROR UPDATE] {$e->getMessage()}\n";
            }
        } else {
            $totalCampos += count($actualizaciones);
        }

        $corrTable++;
        $totalFilas++;
    }

    if ($corrTable === 0) {
        // Sin ruido — no imprimir nada para tablas limpias
    }
}

echo str_repeat("═", 66) . "\n";
echo "RESUMEN\n";
echo str_repeat("─", 66) . "\n";
echo "Filas con correcciones : {$totalFilas}\n";
echo "Campos corregidos      : {$totalCampos}\n";
echo "Modo                   : " . ($dryRun ? "DRY-RUN — nada fue modificado" : "APLICADO — BD actualizada") . "\n";

if ($dryRun && $totalCampos > 0) {
    echo "\nPara aplicar los cambios ejecuta:\n";
    echo "  http://localhost:8081/fix_encoding.php?aplicar=1\n";
    echo "O desde Docker:\n";
    echo "  docker exec robotschool_app php /var/www/html/fix_encoding.php\n";
    echo "  (edita el script para forzar \$aplicar = true si lo llamas por CLI)\n";
}

echo "\n⚠  RECUERDA ELIMINAR ESTE ARCHIVO:\n";
echo "   docker exec robotschool_app rm /var/www/html/fix_encoding.php\n";
