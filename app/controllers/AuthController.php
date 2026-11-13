<?php
declare(strict_types=1);

/**
 * AuthController.php — Controlador de Autenticación
 * Gestionar login, logout y validación de credenciales.
 * Compatible con PHP 8.2, 8.3, 8.4 y 8.5.
 */
class AuthController {
    private PDO $db;
    private Usuario $usuarioModel;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->usuarioModel = new Usuario($db);
    }

    /**
     * Muestra el formulario de login
     */
    public function login(): void {
        if (Auth::isLoggedIn()) {
            header('Location: ' . BASE_URL . '?action=dashboard');
            exit;
        }

        $error = '';
        $csrf_token = Auth::generateCSRF();
        require_once APP_PATH . '/views/auth/login.php';
    }

    /**
     * Procesa el formulario de login con rigor de tipos
     */
    public function doLogin(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '?action=auth/login');
            exit;
        }

        // Blindaje contra payloads no-string en POST (previene TypeError fatal en PHP 8.x)
        $rawToken     = $_POST['csrf_token'] ?? '';
        $rawDocumento = $_POST['documento']  ?? '';
        $rawPassword  = $_POST['password']   ?? '';

        $token     = is_string($rawToken)     ? $rawToken           : '';
        $documento = is_string($rawDocumento) ? trim($rawDocumento) : '';
        $password  = is_string($rawPassword)  ? $rawPassword        : '';

        // Normalizar $_POST['documento'] para evitar advertencias de tipado en la vista
        $_POST['documento'] = $documento;

        // Validar CSRF
        if (!Auth::validateCSRF($token)) {
            $error = 'Token de seguridad inválido. Intente nuevamente.';
            $csrf_token = Auth::generateCSRF();
            require_once APP_PATH . '/views/auth/login.php';
            return;
        }

        // Validaciones básicas
        if ($documento === '' || $password === '') {
            $error = 'Por favor ingrese su documento y contraseña.';
            $csrf_token = Auth::generateCSRF();
            require_once APP_PATH . '/views/auth/login.php';
            return;
        }

        // Autenticación segura
        $usuario = $this->usuarioModel->authenticate($documento, $password);

        if ($usuario !== null) {
            Auth::login($usuario);
            header('Location: ' . BASE_URL . '?action=dashboard');
            exit;
        }

        $error = 'Credenciales incorrectas o usuario inactivo.';
        $csrf_token = Auth::generateCSRF();
        require_once APP_PATH . '/views/auth/login.php';
    }

    /**
     * Cierra la sesión del usuario
     */
    public function logout(): void {
        Auth::logout();
        header('Location: ' . BASE_URL . '?action=auth/login');
        exit;
    }
}
