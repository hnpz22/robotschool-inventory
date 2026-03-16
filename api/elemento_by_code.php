<?php
// api/elemento_by_code.php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/Database.php';
require_once dirname(__DIR__) . '/includes/Auth.php';

header('Content-Type: application/json');
Auth::check();

$codigo = strtoupper(trim($_GET['codigo'] ?? ''));
if (!$codigo) { echo json_encode(['error'=>'Código vacío']); exit; }

$db = Database::get();
$st = $db->prepare("SELECT e.*, c.nombre AS cat FROM elementos e JOIN categorias c ON c.id=e.categoria_id WHERE e.codigo=? AND e.activo=1");
$st->execute([$codigo]);
$elem = $st->fetch();
echo $elem ? json_encode($elem) : json_encode(['error'=>'No encontrado']);
