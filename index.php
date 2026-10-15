<?php

//  Constantes globales 
define('ROOT_PATH', __DIR__);

// Detectar la URL base del proyecto automaticamente
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
define('BASE_URL', rtrim($scriptDir, '/') . '/');

// Ruta de uploads
define('UPLOAD_PATH', ROOT_PATH . '/assets/uploads/');

// Autoload de librerías (Composer en /librerias)
if (file_exists(ROOT_PATH . '/librerias/autoload.php')) {
    require_once ROOT_PATH . '/librerias/autoload.php';
} elseif (file_exists(ROOT_PATH . '/vendor/autoload.php')) {
    require_once ROOT_PATH . '/vendor/autoload.php';
}

// Cargar Helpers 
require_once ROOT_PATH . '/config/Database.php';
require_once ROOT_PATH . '/helpers/Auth.php';
require_once ROOT_PATH . '/helpers/Validator.php';
require_once ROOT_PATH . '/helpers/Router.php';

// Iniciar sesion
Auth::init();

// Cargar modelos dinamicamente (incluye maestros y reportes)
$modelsDir = ROOT_PATH . '/models/';
if (is_dir($modelsDir)) {
    foreach (glob($modelsDir . '*.php') as $modelFile) {
        require_once $modelFile;
    }
}

//Conexion a la base de datos 
$database = Database::getInstance();
$db = $database->getConnection();

//Despachar la peticion 
$router = new Router($db);
$router->dispatch();
