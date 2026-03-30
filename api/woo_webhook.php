<?php
/**
 * api/woo_webhook.php — Receptor de webhooks de WooCommerce
 *
 * Configurar en WooCommerce → Ajustes → Avanzado → Webhooks:
 *   Nombre:      ROBOTSchool Sync
 *   Estado:      Activo
 *   Tema:        Pedido actualizado
 *   URL:         https://sistema.miel-robotschool.com/api/woo_webhook.php
 *   Versión API: WC/v3
 *   Secret:      [valor de WOO_WEBHOOK_SECRET en .env]
 */

require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/WooSync.php';

// 1. Leer body crudo ANTES de cualquier parsing
$rawBody = file_get_contents('php://input');

// 2. Responder 200 de inmediato para que WooCommerce no reintente
http_response_code(200);
header('Content-Type: application/json');

$db          = Database::get();
$payloadHash = hash('sha256', $rawBody);
$evento      = $_SERVER['HTTP_X_WC_WEBHOOK_TOPIC'] ?? 'order.updated';

// Helper: registrar en woo_webhook_log
$logWebhook = function (
    ?string $wooOrderId,
    ?string $statusCode,
    string  $resultado,
    ?string $detalle
) use ($db, $evento, $payloadHash): void {
    try {
        $db->prepare(
            "INSERT INTO woo_webhook_log (woo_order_id, evento, status_code, resultado, detalle, payload_hash)
             VALUES (?,?,?,?,?,?)"
        )->execute([$wooOrderId, $evento, $statusCode, $resultado, $detalle, $payloadHash]);
    } catch (Exception $e) {
        // Si la tabla aún no existe, silenciar
    }
};

// 3. Validar firma HMAC-SHA256
$secret    = defined('WOO_WEBHOOK_SECRET') ? WOO_WEBHOOK_SECRET : '';
$signature = $_SERVER['HTTP_X_WC_WEBHOOK_SIGNATURE'] ?? '';
$expected  = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));

if (empty($secret) || !hash_equals($expected, $signature)) {
    $logWebhook(null, null, 'error', 'Firma HMAC inválida');
    exit(json_encode(['ok' => false]));
}

// 4. Parsear payload
$order = json_decode($rawBody, true);
if (!$order || empty($order['id'])) {
    $logWebhook(null, null, 'error', 'Payload JSON inválido o id ausente');
    exit(json_encode(['ok' => false, 'msg' => 'payload inválido']));
}

$wooOrderId = (string)$order['id'];
$wooStatus  = $order['status'] ?? '';

// 5. Solo procesar si el estado es 'processing' (pedido pagado)
if ($wooStatus !== 'processing') {
    $logWebhook($wooOrderId, $wooStatus, 'ignorado', "Estado '$wooStatus' no requiere acción");
    exit(json_encode(['ok' => true, 'msg' => 'estado ignorado: ' . $wooStatus]));
}

// 6. Insertar en tienda_pedidos via WooSync
$woo    = new WooSync($db);
$result = $woo->procesarDesdeWebhook($order);

$logResultado = match(true) {
    $result === 'ok'        => 'ok',
    $result === 'duplicado' => 'duplicado',
    default                 => 'error',
};
$logDetalle = ($logResultado === 'error') ? $result : null;

$logWebhook($wooOrderId, $wooStatus, $logResultado, $logDetalle);

echo json_encode(['ok' => $result === 'ok', 'result' => $result]);
