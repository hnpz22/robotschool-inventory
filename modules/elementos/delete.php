<?php
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once dirname(__DIR__, 2) . '/includes/Database.php';
require_once dirname(__DIR__, 2) . '/includes/Auth.php';
Auth::requireAdmin();
$db = Database::get();
$id = (int)($_GET['id'] ?? 0);
if ($id && Auth::csrfVerify($_GET['csrf'] ?? '')) {
    $db->prepare("UPDATE elementos SET activo=0 WHERE id=?")->execute([$id]);
}
header('Location: ' . APP_URL . '/modules/elementos/'); exit;
