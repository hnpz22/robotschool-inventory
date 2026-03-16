<?php
// ============================================================
//  config/config.php — Configuración central ROBOTSchool
//  Lee desde variables de entorno; usa valores por defecto
//  para compatibilidad con entornos locales sin .env.
// ============================================================

// ── Entorno ──
$appEnv = getenv('APP_ENV') ?: 'development';

// ── Aplicación ──
define('APP_NAME',    'ROBOTSchool Inventory');
define('APP_VERSION', '3.3');
define('APP_URL',     rtrim(getenv('APP_URL') ?: 'http://localhost/robotschool_inventory', '/'));
define('APP_ROOT',    dirname(__DIR__));
define('APP_ENV',     $appEnv);

// ── Base de datos ──
define('DB_HOST',    getenv('DB_HOST')    ?: 'db');
define('DB_NAME',    getenv('DB_NAME')    ?: 'robotschool_inventory');
define('DB_USER',    getenv('DB_USER')    ?: 'root');
define('DB_PASS',    getenv('DB_PASS')    ?: '');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

// ── Rutas ──
define('UPLOAD_DIR', APP_ROOT . '/assets/uploads/');
define('UPLOAD_URL', APP_URL  . '/assets/uploads/');

// ── Sesión ──
define('SESSION_NAME',    'rs_inv_session');
define('SESSION_TIMEOUT', (int)(getenv('SESSION_TIMEOUT') ?: 3600));

// ── Paginación ──
define('ITEMS_PER_PAGE', 25);

// ── Zona horaria ──
date_default_timezone_set('America/Bogota');

// ── Errores: estricto en producción, verboso en desarrollo ──
if ($appEnv === 'production') {
    ini_set('display_errors', '0');
    ini_set('log_errors',     '1');
    ini_set('error_log',      '/var/log/php/error.log');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
} else {
    ini_set('display_errors', '1');
    ini_set('log_errors',     '1');
    error_reporting(E_ALL);
}

// ── APIs externas (opcionales) ──
$_anthropic = getenv('ANTHROPIC_API_KEY');
if (!empty($_anthropic)) {
    define('ANTHROPIC_API_KEY', $_anthropic);
}

$_msClientId = getenv('MS_CLIENT_ID');
if (!empty($_msClientId)) {
    define('MS_CLIENT_ID',     $_msClientId);
    define('MS_CLIENT_SECRET', getenv('MS_CLIENT_SECRET') ?: '');
    define('MS_TENANT_ID',     getenv('MS_TENANT_ID')     ?: 'common');
}
