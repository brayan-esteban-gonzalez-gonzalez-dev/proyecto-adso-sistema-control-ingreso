<?php
/**
 * Auth.php — Helper de autenticación y sesiones
 * 
 * Gestionar el inicio/cierre de sesion, verificación de roles
 * y protección de rutas según permisos.
 */
class Auth {

    /**
     * Inicia la sesión si no está activa
     */
    public static function init(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Establece los datos de sesión al iniciar sesión
     */
    public static function login(array $usuario): void {
        self::init();
        $_SESSION['user_id']       = $usuario['id_usuario'];
        $_SESSION['user_rol']      = $usuario['id_rol'];
        $_SESSION['user_nombre']   = $usuario['nombre'];
        $_SESSION['user_apellido'] = $usuario['apellido'];
        $_SESSION['user_correo']   = $usuario['correo'];
        $_SESSION['user_documento']= $usuario['num_documento'];
        session_regenerate_id(true);
    }

    /**
     * Cierra la sesión y destruye los datos
     */
    public static function logout(): void {
        self::init();
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
    }

    /**
     * Verifica si hay un usuario autenticado
     */
    public static function isLoggedIn(): bool {
        self::init();
        return isset($_SESSION['user_id']);
    }

    /**
     * Verifica si el usuario actual es Administrador (rol 1)
     */
    public static function isAdmin(): bool {
        self::init();
        return isset($_SESSION['user_rol']) && (int)$_SESSION['user_rol'] === 1;
    }

    /**
     * Verifica si el usuario actual es Instructor (rol 2)
     */
    public static function isInstructor(): bool {
        self::init();
        return isset($_SESSION['user_rol']) && (int)$_SESSION['user_rol'] === 2;
    }

    /**
     * Verifica si el usuario actual es Administrador o Instructor
     */
    public static function isAdminOrInstructor(): bool {
        return self::isAdmin() || self::isInstructor();
    }

    /**
     * Verifica si el usuario actual es Aprendiz (rol 3)
     */
    public static function isAprendiz(): bool {
        self::init();
        return isset($_SESSION['user_rol']) && (int)$_SESSION['user_rol'] === 3;
    }

    /**
     * Retorna el ID del usuario autenticado
     */
    public static function getUserId(): ?int {
        self::init();
        return $_SESSION['user_id'] ?? null;
    }

    /**
     * Retorna el rol del usuario autenticado
     */
    public static function getUserRol(): ?int {
        self::init();
        return isset($_SESSION['user_rol']) ? (int)$_SESSION['user_rol'] : null;
    }

    /**
     * Retorna el nombre completo del usuario autenticado
     */
    public static function getUserFullName(): string {
        self::init();
        $nombre   = $_SESSION['user_nombre']   ?? '';
        $apellido = $_SESSION['user_apellido'] ?? '';
        return trim("$nombre $apellido");
    }

    /**
     * Redirige al login si el usuario no está autenticado
     */
    public static function requireLogin(): void {
        if (!self::isLoggedIn()) {
            header('Location: ' . BASE_URL . '?action=auth/login');
            exit;
        }
    }

    /**
     * Redirige al dashboard si el usuario no es admin/instructor
     */
    public static function requireAdmin(): void {
        self::requireLogin();
        if (!self::isAdminOrInstructor()) {
            header('Location: ' . BASE_URL . '?action=dashboard');
            exit;
        }
    }

    /**
     * Genera un token CSRF y lo almacena en sesión
     */
    public static function generateCSRF(): string {
        self::init();
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        return $token;
    }

    /**
     * Valida el token CSRF recibido
     */
    public static function validateCSRF(string $token): bool {
        self::init();
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        $valid = hash_equals($_SESSION['csrf_token'], $token);
        // Regenerar después de validar
        unset($_SESSION['csrf_token']);
        return $valid;
    }

    /**
     * Establece un mensaje flash en sesión
     */
    public static function setFlash(string $type, string $message): void {
        self::init();
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
    }

    /**
     * Obtiene y elimina el mensaje flash de sesión
     */
    public static function getFlash(): ?array {
        self::init();
        if (isset($_SESSION['flash'])) {
            $flash = $_SESSION['flash'];
            unset($_SESSION['flash']);
            return $flash;
        }
        return null;
    }
}
