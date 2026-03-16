<?php
// Capturar CUALQUIER output/error antes de que rompa el JSON
ob_start();

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';

header('Content-Type: application/json');

// Limpiar cualquier output previo (warnings, notices)
ob_clean();

try {
    Auth::check();

    $input  = json_decode(file_get_contents('php://input'), true);
    $prompt = trim($input['prompt'] ?? '');

    if (!$prompt) {
        echo json_encode(['error' => 'Prompt vacio']);
        exit;
    }

    $apiKey = defined('ANTHROPIC_API_KEY') ? ANTHROPIC_API_KEY : '';

    if (!$apiKey) {
        echo json_encode(['error' => 'API key no configurada. Agrega en config.php: define("ANTHROPIC_API_KEY", "sk-ant-...");']);
        exit;
    }

    if (!function_exists('curl_init')) {
        echo json_encode(['error' => 'cURL no esta disponible en este servidor PHP.']);
        exit;
    }

    $payload = json_encode([
        'model'      => 'claude-opus-4-6',
        'max_tokens' => 2000,
        'messages'   => [['role' => 'user', 'content' => $prompt]],
    ]);

    $ch = curl_init('https://api.anthropic.com/v1/messages');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
        ],
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => false, // Para XAMPP local
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        echo json_encode(['error' => 'Error de conexion: ' . $curlError]);
        exit;
    }

    $data = json_decode($response, true);

    if ($httpCode !== 200) {
        $msg = $data['error']['message'] ?? 'Error HTTP ' . $httpCode;
        echo json_encode(['error' => $msg]);
        exit;
    }

    $html = $data['content'][0]['text'] ?? '';
    // Limpiar markdown si viene con ```html
    $html = preg_replace('/^```html\s*/i', '', trim($html));
    $html = preg_replace('/\s*```\s*$/', '', $html);

    echo json_encode(['html' => trim($html)]);

} catch (Exception $e) {
    ob_clean();
    echo json_encode(['error' => $e->getMessage()]);
}
