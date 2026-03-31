<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Auth.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

header('Content-Type: application/json');
Auth::check();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok'=>false,'error'=>'Método no permitido']); exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) { echo json_encode(['ok'=>false,'error'=>'JSON inválido']); exit; }
if (!Auth::csrfVerify($body['csrf']??'')) { echo json_encode(['ok'=>false,'error'=>'CSRF inválido']); exit; }

$nombre     = trim($body['nombre']           ?? '');
$catId      = (int)($body['categoria_id']    ?? 0);
$codProv    = trim($body['codigo_proveedor'] ?? '');
$unidad     = trim($body['unidad_medida']    ?? 'unidad');
$precioUSD  = (float)($body['precio_usd']    ?? 0);
$costoCOP   = (float)($body['costo_real_cop']?? 0);
$stockIni   = (int)($body['stock_inicial']   ?? 0);
$provId     = ($body['proveedor_id'] && $body['proveedor_id']!='') ? (int)$body['proveedor_id'] : null;
$ubicacion  = trim($body['ubicacion']        ?? '');
$descripcion= trim($body['descripcion']      ?? '');

if (!$nombre) { echo json_encode(['ok'=>false,'error'=>'Nombre requerido']); exit; }
if (!$catId)  { echo json_encode(['ok'=>false,'error'=>'Categoría requerida']); exit; }

try {
    $db = Database::get();

    // Obtener prefijo de la categoría
    $cat = $db->prepare("SELECT prefijo FROM categorias WHERE id=? AND activo=1");
    $cat->execute([$catId]);
    $catRow = $cat->fetch();
    if (!$catRow) { echo json_encode(['ok'=>false,'error'=>'Categoría inválida']); exit; }

    // Generar código único RS-XXX-000
    $codigo = generarCodigo($catId);

    $db->prepare("
        INSERT INTO elementos
          (codigo, nombre, descripcion, categoria_id, unidad_medida,
           costo_real_cop, precio_unit_usd, stock_actual, stock_minimo,
           proveedor_id, ubicacion, activo, created_by)
        VALUES
          (?,?,?,?,?,?,?,?,5,?,?,1,?)
    ")->execute([
        $codigo, $nombre, $descripcion ?: null, $catId, $unidad,
        $costoCOP, $precioUSD, $stockIni,
        $provId, $ubicacion ?: null,
        Auth::user()['id']
    ]);
    $elemId = $db->lastInsertId();

    // Registrar auditoría
    auditoria('creado_desde_importacion', 'elementos', $elemId, [], [
        'codigo'           => $codigo,
        'codigo_proveedor' => $codProv,
    ]);

    // Registrar movimiento inicial si hay stock
    if ($stockIni > 0) {
        $db->prepare("
            INSERT INTO movimientos (elemento_id, tipo, cantidad, stock_resultante, notas, created_by)
            VALUES (?, 'entrada', ?, ?, 'Stock inicial al crear desde pedido de importación', ?)
        ")->execute([$elemId, $stockIni, $stockIni, Auth::user()['id']]);
    }

    echo json_encode(['ok'=>true, 'id'=>$elemId, 'codigo'=>$codigo, 'nombre'=>$nombre]);

} catch (Exception $e) {
    echo json_encode(['ok'=>false, 'error'=>$e->getMessage()]);
}
