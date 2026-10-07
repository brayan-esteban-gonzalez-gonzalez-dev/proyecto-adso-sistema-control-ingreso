<?php
/**
 * Router.php — Enrutador Front Controller
 * 
 * Parsea la URL para despachar a controladores/métodos.
 * Patrón: ?action=controlador/metodo&id=X
 */
class Router {

    /** @var array Mapa de rutas controlador => clase */
    private $controllers = [
        'auth'       => 'AuthController',
        'dashboard'  => 'DashboardController',
        'usuarios'   => 'UsuarioController',
        'fichas'     => 'FichaController',
        'horarios'   => 'HorarioController',
        'aprendices' => 'AprendizController',
        'asistencia' => 'AsistenciaController',
        'excusas'    => 'ExcusaController',
        'reportes'   => 'ReporteController',
    ];

    /** @var PDO Conexión a la base de datos */
    private $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Despacha la petición al controlador y método apropiados
     */
    public function dispatch(): void {
        $action = $_GET['action'] ?? 'dashboard';
        $parts  = explode('/', $action, 2);

        $controllerKey = $parts[0] ?? 'dashboard';
        $method        = $parts[1] ?? 'index';

        // Sanitizar nombre del método
        $method = preg_replace('/[^a-zA-Z0-9_]/', '', $method);

        // Verificar si el controlador existe en el mapa
        if (!isset($this->controllers[$controllerKey])) {
            $this->show404();
            return;
        }

        $controllerClass = $this->controllers[$controllerKey];
        $controllerFile  = ROOT_PATH . '/controllers/' . $controllerClass . '.php';

        if (!file_exists($controllerFile)) {
            $this->show404();
            return;
        }

        require_once $controllerFile;

        if (!class_exists($controllerClass)) {
            $this->show404();
            return;
        }

        $controller = new $controllerClass($this->db);

        // Verificar que el método existe y es público
        if (!method_exists($controller, $method) || !is_callable([$controller, $method])) {
            $this->show404();
            return;
        }

        // Rutas públicas (no requieren autenticación)
        $publicRoutes = ['auth/login', 'auth/doLogin', 'auth/logout'];

        if (!in_array($action, $publicRoutes)) {
            Auth::requireLogin();
        }

        // Ejecutar el método del controlador
        $controller->$method();
    }

    /**
     * Muestra página 404
     */
    private function show404(): void {
        http_response_code(404);
        echo '<div style="text-align:center;padding:50px;font-family:Inter,sans-serif;">';
        echo '<h1 style="font-size:72px;color:#ef4444;">404</h1>';
        echo '<p style="font-size:18px;color:#94a3b8;">Página no encontrada</p>';
        echo '<a href="' . BASE_URL . '" style="color:#10b981;">Volver al inicio</a>';
        echo '</div>';
    }
}
