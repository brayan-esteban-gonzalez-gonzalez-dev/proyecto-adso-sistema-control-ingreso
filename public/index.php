<?php
/**
 * index.php — Punto de entrada principal (Front Controller)
 * 
 * Todas las peticiones HTTP se canalizan aquí.
 * Se definen constantes globales, se cargan dependencias
 * y se despacha la petición al Router.
 */

// ─── Constantes de ruta ───
define('ROOT_PATH', dirname(__DIR__));
define('BASE_URL', '/proyects/segundoCrud/SistemaControlIngresoAprendices-mvc/public/');
define('UPLOAD_PATH', ROOT_PATH . '/public/assets/uploads/');

// ─── Autoload de Composer (si existe) ───
$autoload = ROOT_PATH . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

// ─── Cargar helpers ───
require_once ROOT_PATH . '/helpers/Auth.php';
require_once ROOT_PATH . '/helpers/Validator.php';
require_once ROOT_PATH . '/helpers/Router.php';

// ─── Cargar configuración de BD ───
require_once ROOT_PATH . '/config/Database.php';

// ─── Cargar modelos ───
$modelsDir = ROOT_PATH . '/models/';
if (is_dir($modelsDir)) {
    foreach (glob($modelsDir . '*.php') as $modelFile) {
        require_once $modelFile;
    }
}

// ─── Iniciar sesión ───
Auth::init();

// ─── Conexión a la base de datos ───
$database = Database::getInstance();
$db = $database->getConnection();

// ─── Despachar la petición ───
$router = new Router($db);
$router->dispatch();
