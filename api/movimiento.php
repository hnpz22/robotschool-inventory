<?php
// api/movimiento.php &mdash; Registro rápido de movimientos desde el escáner
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Auth.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

header('Content-Type: application/json');
Auth::check();

$body = json_decode(file_get_contents('php://input'), true);
if (!Auth::csrfVerify($body['csrf'] ?? '')) {
    echo json_encode(['error'=>'CSRF inválido']); exit;
}

$elemId   = (int)($body['elemento_id'] ?? 0);
$tipo     = $body['tipo'] ?? '';
$cantidad = abs((int)($body['cantidad'] ?? 0));
$motivo   = trim($body['motivo'] ?? '');

if (!$elemId || !in_array($tipo, ['entrada','salida','ajuste']) || $cantidad <= 0) {
    echo json_encode(['error'=>'Parámetros inválidos']); exit;
}

$db = Database::get();
$elem = $db->query("SELECT id, stock_actual FROM elementos WHERE id=$elemId AND activo=1")->fetch();
if (!$elem) { echo json_encode(['error'=>'Elemento no encontrado']); exit; }

$antes = (int)$elem['stock_actual'];
$delta = ($tipo === 'salida') ? -$cantidad : $cantidad;
$despues = $antes + $delta;

if ($despues < 0) { echo json_encode(['error'=>"Stock insuficiente. Stock actual: $antes"]); exit; }

try {
    $db->prepare("UPDATE elementos SET stock_actual=? WHERE id=?")->execute([$despues, $elemId]);
    $db->prepare("INSERT INTO movimientos (elemento_id,tipo,cantidad,stock_antes,stock_despues,motivo,usuario_id) VALUES (?,?,?,?,?,?,?)")
       ->execute([$elemId, $tipo, $delta, $antes, $despues, $motivo ?: 'Movimiento rápido', Auth::user()['id']]);
    echo json_encode(['ok'=>true, 'stock'=>$despues]);
} catch (Exception $e) {
    echo json_encode(['error'=>$e->getMessage()]);
}
