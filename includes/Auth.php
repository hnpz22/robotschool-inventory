<?php
require_once __DIR__ . '/Database.php';

class Auth {

    // Mapa rol_id => nombre de modulos permitidos
    private static $ROL_MENUS = [
        1 => [ // gerencia
            'dashboard','inventario','movimientos','barcodes','kits','colegios','pedidos_tienda',
            'produccion','alistamiento','cursos','matriculas','pagos','academico',
            'comercial','convenios','reportes','usuarios','config','categorias',
            'pedidos','proveedores','despachos'
        ],
        2 => [ // administracion
            'dashboard','inventario','movimientos','barcodes','kits','colegios',
            'pedidos_tienda','produccion','alistamiento','reportes',
            'pedidos','proveedores','despachos'
        ],
        3 => [ // academia
            'dashboard','cursos','matriculas','pagos','academico','colegios'
        ],
        4 => [ // produccion
            'dashboard','inventario','movimientos','barcodes','kits','produccion','alistamiento'
        ],
        5 => [ // comercial
            'dashboard','comercial','convenios','colegios'
        ],
        6 => [ // consulta
            'dashboard','inventario','movimientos','reportes'
        ],
    ];

    private static $ROL_NOMBRES = [
        1=>'gerencia', 2=>'administracion', 3=>'academia',
        4=>'produccion', 5=>'comercial', 6=>'consulta'
    ];

    private static $ROL_META = [
        1=>['label'=>'Gerencia',       'color'=>'#1e293b','icon'=>'bi-star-fill'],
        2=>['label'=>'Administracion', 'color'=>'#185FA5','icon'=>'bi-gear-fill'],
        3=>['label'=>'Academia',       'color'=>'#0f766e','icon'=>'bi-mortarboard-fill'],
        4=>['label'=>'Produccion',     'color'=>'#b45309','icon'=>'bi-tools'],
        5=>['label'=>'Comercial',      'color'=>'#7c3aed','icon'=>'bi-briefcase-fill'],
        6=>['label'=>'Consulta',       'color'=>'#64748b','icon'=>'bi-eye-fill'],
    ];

    // ── Sesion ───────────────────────────────────────────────────
    public static function start(): void {
        if (session_status() === PHP_SESSION_NONE) {
            if (defined('SESSION_NAME')) session_name(SESSION_NAME);
            session_start();
        }
    }

    public static function check(): void {
        self::start();
        if (empty($_SESSION['user_id'])) {
            header('Location: ' . APP_URL . '/login.php'); exit;
        }
        if (defined('SESSION_TIMEOUT') && time() - ($_SESSION['logged_at'] ?? 0) > SESSION_TIMEOUT) {
            self::logout();
        }
        $_SESSION['logged_at'] = time();
    }

    // ── Login email + password ──────────────────────────────────
    public static function login(string $email, string $password): array {
        self::start();
        $db = Database::get();
        $st = $db->prepare("SELECT u.*, r.nombre AS rol_nombre
            FROM usuarios u JOIN roles r ON r.id=u.rol_id
            WHERE u.email=? AND u.activo=1 LIMIT 1");
        $st->execute([$email]);
        $user = $st->fetch();
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return ['ok'=>false, 'msg'=>'Credenciales inv&aacute;lidas'];
        }
        self::_setSession($user);
        $db->prepare("UPDATE usuarios SET ultimo_login=NOW() WHERE id=?")->execute([$user['id']]);
        return ['ok'=>true];
    }

    // ── Login Microsoft OAuth ───────────────────────────────────
    public static function loginMicrosoft(array $msUser): bool {
        self::start();
        $db = Database::get();
        $st = $db->prepare("SELECT u.*, r.nombre AS rol_nombre
            FROM usuarios u JOIN roles r ON r.id=u.rol_id
            WHERE (u.microsoft_id=? OR u.email=?) AND u.activo=1 LIMIT 1");
        $st->execute([$msUser['id'], $msUser['mail']]);
        $user = $st->fetch();
        if (!$user) return false;
        $db->prepare("UPDATE usuarios SET microsoft_id=?,avatar_url=? WHERE id=?")
           ->execute([$msUser['id'], $msUser['photo']??null, $user['id']]);
        $user['avatar_url'] = $msUser['photo'] ?? $user['avatar_url'] ?? null;
        self::_setSession($user);
        return true;
    }

    private static function _setSession(array $user): void {
        $_SESSION['user_id']     = $user['id'];
        $_SESSION['user_name']   = $user['nombre'];
        $_SESSION['user_nombre'] = $user['nombre']; // alias
        $_SESSION['user_email']  = $user['email'];
        $_SESSION['user_rol']    = $user['rol_id'];
        $_SESSION['rol_nombre']  = $user['rol_nombre'] ?? (self::$ROL_NOMBRES[$user['rol_id']] ?? 'consulta');
        $_SESSION['user_avatar'] = $user['avatar_url'] ?? null;
        $_SESSION['logged_at']   = time();
    }

    public static function logout(): void {
        self::start();
        session_destroy();
        header('Location: ' . APP_URL . '/login.php'); exit;
    }

    // ── Getters ─────────────────────────────────────────────────
    public static function user(): array {
        return [
            'id'     => $_SESSION['user_id']    ?? 0,
            'name'   => $_SESSION['user_name']  ?? '',
            'nombre' => $_SESSION['user_nombre']?? $_SESSION['user_name'] ?? '',
            'email'  => $_SESSION['user_email'] ?? '',
            'rol'    => $_SESSION['user_rol']   ?? 6,
            'rol_nombre' => $_SESSION['rol_nombre'] ?? 'consulta',
            'avatar' => $_SESSION['user_avatar']?? null,
        ];
    }

    public static function getRolId(): int {
        return (int)($_SESSION['user_rol'] ?? 6);
    }

    public static function getRol(): string {
        return $_SESSION['rol_nombre'] ?? (self::$ROL_NOMBRES[self::getRolId()] ?? 'consulta');
    }

    public static function getRolMeta(): array {
        return self::$ROL_META[self::getRolId()] ?? self::$ROL_META[6];
    }

    public static function isAdmin(): bool {
        return self::getRolId() <= 2; // gerencia o administracion
    }

    public static function isGerencia(): bool {
        return self::getRolId() === 1;
    }

    public static function requireAdmin(): void {
        self::check();
        if (!self::isAdmin()) {
            header('Location: ' . APP_URL . '/dashboard.php?err=sin_permiso'); exit;
        }
    }

    public static function requireRol(string ...$roles): void {
        self::check();
        if (!in_array(self::getRol(), $roles) && !self::isGerencia()) {
            header('Location: ' . APP_URL . '/dashboard.php?err=sin_permiso'); exit;
        }
    }

    // Alias para compatibilidad con modulos que usan requirePermiso
    public static function requirePermiso(string $modulo, string $accion = 'ver'): void {
        self::check();
        if (!self::puede($modulo, $accion)) {
            header('Location: ' . APP_URL . '/dashboard.php?err=sin_permiso'); exit;
        }
    }

    public static function menuItems(): array {
        // Gerencia siempre tiene acceso total (definido estáticamente)
        if (self::isGerencia()) return self::$ROL_MENUS[1];
        $rolId = self::getRolId();
        static $menuCache = [];
        if (isset($menuCache[$rolId])) return $menuCache[$rolId];
        $staticMenu = self::$ROL_MENUS[$rolId] ?? self::$ROL_MENUS[6];
        try {
            $db = Database::get();
            $allowed = $db->query("SELECT modulo FROM rol_permisos WHERE rol_id=$rolId AND ver=1")
                          ->fetchAll(PDO::FETCH_COLUMN);
            $configured = $db->query("SELECT modulo FROM rol_permisos WHERE rol_id=$rolId")
                             ->fetchAll(PDO::FETCH_COLUMN);
            if (!empty($configured)) {
                // Rol ya configurado: respetar explícitos + fallback estático para módulos sin fila
                $fallback = array_diff($staticMenu, $configured);
                return $menuCache[$rolId] = array_values(array_unique(array_merge($allowed, $fallback)));
            }
        } catch (Exception $e) {}
        // Fallback a definición estática si la tabla no existe o está vacía
        return $menuCache[$rolId] = $staticMenu;
    }

    public static function tieneAcceso(string $modulo): bool {
        return in_array($modulo, self::menuItems());
    }

    // ── Permisos granulares ──────────────────────────────────────
    public static function puede(string $modulo, string $accion = 'ver'): bool {
        if (self::isGerencia()) return true;
        $db = Database::get();
        static $cache = [];
        $key = self::getRolId().'_'.$modulo.'_'.$accion;
        if (isset($cache[$key])) return $cache[$key];
        $accionesValidas = ['ver', 'crear', 'editar', 'eliminar'];
        if (!in_array($accion, $accionesValidas, true)) {
            return $cache[$key] = false;
        }
        try {
            $val = $db->query("SELECT `$accion` FROM rol_permisos
                WHERE rol_id=".self::getRolId()." AND modulo=".$db->quote($modulo))->fetchColumn();
            // Si no hay fila para este módulo (false), caer a menú estático en vez de bloquear:
            // evita bloquear módulos nuevos cuando el rol ya tenía otras filas configuradas.
            if ($val === false) {
                $cache[$key] = ($accion === 'ver') ? self::tieneAcceso($modulo) : false;
            } else {
                $cache[$key] = (bool)$val;
            }
        } catch (Exception $e) {
            // Si no existe la tabla permisos, usar acceso por menu
            $cache[$key] = self::tieneAcceso($modulo);
        }
        return $cache[$key];
    }

    // ── CSRF ─────────────────────────────────────────────────────
    public static function csrfToken(): string {
        self::start();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function csrfVerify(string $token): bool {
        return hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }

    // ── Notificaciones ───────────────────────────────────────────
    public static function notificacionesPendientes(): int {
        try {
            $db  = Database::get();
            $rid = self::getRolId();
            if ($rid <= 2 || $rid === 4) {
                return (int)$db->query(
                    "SELECT COUNT(*) FROM solicitudes_produccion WHERE estado='pendiente'"
                )->fetchColumn();
            }
        } catch (Exception $e) {}
        return 0;
    }

    public static function conveniosPendientes(): int {
        if (!self::isGerencia()) return 0;
        try {
            return (int)Database::get()->query(
                "SELECT COUNT(*) FROM convenios WHERE estado='pendiente_aprobacion' AND activo=1"
            )->fetchColumn();
        } catch (Exception $e) { return 0; }
    }

    public static function pedidosTiendaPendientes(): int {
        if (!in_array(self::getRolId(), [1, 2])) return 0;
        try {
            return (int)Database::get()->query(
                "SELECT COUNT(*) FROM tienda_pedidos WHERE estado='pendiente' AND creado_desde_csv=0"
            )->fetchColumn();
        } catch (Exception $e) { return 0; }
    }
}
