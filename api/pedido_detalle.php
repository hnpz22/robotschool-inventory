<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
Auth::check();
header('Content-Type: application/json');

$db  = Database::get();
$id  = (int)($_GET['id'] ?? 0);
if (!$id) { echo json_encode(['ok'=>false,'error'=>'ID requerido']); exit; }

$ped = $db->prepare("
    SELECT p.*, pv.nombre AS proveedor
    FROM pedidos_importacion p
    LEFT JOIN proveedores pv ON pv.id = p.proveedor_id
    WHERE p.id = ?
");
$ped->execute([$id]);
$pedido = $ped->fetch(PDO::FETCH_ASSOC);
if (!$pedido) { echo json_encode(['ok'=>false,'error'=>'No encontrado']); exit; }

$items = $db->prepare("
    SELECT pi.*, e.nombre AS elem_nombre, e.codigo AS elem_codigo,
           (pi.cantidad * pi.precio_unit_usd) AS subtotal_usd
    FROM pedido_items pi
    LEFT JOIN elementos e ON e.id = pi.elemento_id
    WHERE pi.pedido_id = ?
    ORDER BY pi.id
");
$items->execute([$id]);

echo json_encode([
    'ok'     => true,
    'pedido' => $pedido,
    'items'  => $items->fetchAll(PDO::FETCH_ASSOC),
]);
