<?php
/**
 * AuthController.php — Controlador de Autenticación
 * 
 * Gestionar login, logout y validacion de credenciales.
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
        // Si ya está logueado, redirigir al dashboard
        if (Auth::isLoggedIn()) {
            header('Location: ' . BASE_URL . '?action=dashboard');
            exit;
        }

        $error = '';
        $csrf_token = Auth::generateCSRF();
        require_once ROOT_PATH . '/views/auth/login.php';
    }

    /**
     * Procesa el formulario de login
     */
    public function doLogin(): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '?action=auth/login');
            exit;
        }

        // Validar CSRF
        $token = $_POST['csrf_token'] ?? '';
        if (!Auth::validateCSRF($token)) {
            $error = 'Token de seguridad inválido. Intente nuevamente.';
            $csrf_token = Auth::generateCSRF();
            require_once ROOT_PATH . '/views/auth/login.php';
            return;
        }

        $documento = trim($_POST['documento'] ?? '');
        $password  = $_POST['password'] ?? '';

        // Validaciones básicas
        if (empty($documento) || empty($password)) {
            $error = 'Por favor ingrese su documento y contraseña.';
            $csrf_token = Auth::generateCSRF();
            require_once ROOT_PATH . '/views/auth/login.php';
            return;
        }

        // Autenticación
        $usuario = $this->usuarioModel->authenticate($documento, $password);

        if ($usuario) {
            Auth::login($usuario);
            header('Location: ' . BASE_URL . '?action=dashboard');
            exit;
        } else {
            $error = 'Credenciales incorrectas o usuario inactivo.';
            $csrf_token = Auth::generateCSRF();
            require_once ROOT_PATH . '/views/auth/login.php';
        }
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
