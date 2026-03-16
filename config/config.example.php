<?php
// ============================================================
// ROBOTSchool Inventory — Configuración
// Copia este archivo como config.php y ajusta los valores
// ============================================================

// ── App ──────────────────────────────────────────────────────
define('APP_NAME',    'ROBOTSchool Inventory');
define('APP_VERSION', '3.5.0');
define('APP_URL',     'http://localhost:8081');   // ← Cambia según tu entorno
define('APP_ROOT',    dirname(__DIR__));

// ── Base de datos ─────────────────────────────────────────────
define('DB_HOST',    'localhost');
define('DB_NAME',    'robotschool_inventory');
define('DB_USER',    'root');        // ← Cambia en producción
define('DB_PASS',    '');            // ← Cambia en producción
define('DB_CHARSET', 'utf8mb4');

// ── Archivos subidos ──────────────────────────────────────────
define('UPLOAD_DIR', APP_ROOT . '/assets/uploads/');
define('UPLOAD_URL', APP_URL  . '/assets/uploads/');

// ── Sesión ────────────────────────────────────────────────────
define('SESSION_NAME',    'rs_inv_session');
define('SESSION_TIMEOUT', 3600);  // 1 hora

// ── Paginación ────────────────────────────────────────────────
define('ITEMS_PER_PAGE', 25);

// ── API Anthropic (opcional — para banners IA en cursos) ──────
// define('ANTHROPIC_API_KEY', 'sk-ant-...');

// ── Microsoft 365 OAuth (opcional — para login corporativo) ──
// define('MS_CLIENT_ID',     'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx');
// define('MS_CLIENT_SECRET', 'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
// define('MS_TENANT_ID',     'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx');
// define('MS_REDIRECT_URI',  APP_URL . '/modules/auth/ms_callback.php');
