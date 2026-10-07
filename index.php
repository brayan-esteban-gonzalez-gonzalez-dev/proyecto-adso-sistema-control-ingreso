<?php

//  Constantes globales 
define('ROOT_PATH', __DIR__);

// Detectar la URL base del proyecto automaticamente
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
define('BASE_URL', rtrim($scriptDir, '/') . '/');

// Ruta de uploads
define('UPLOAD_PATH', ROOT_PATH . '/assets/uploads/');

// Cargar Helpers 
require_once ROOT_PATH . '/config/Database.php';
require_once ROOT_PATH . '/helpers/Auth.php';
require_once ROOT_PATH . '/helpers/Validator.php';
require_once ROOT_PATH . '/helpers/Router.php';

// Iniciar sesion
Auth::init();

//Cargar Modelos base 
require_once ROOT_PATH . '/models/Rol.php';
require_once ROOT_PATH . '/models/Usuario.php';
require_once ROOT_PATH . '/models/Ficha.php';
require_once ROOT_PATH . '/models/Aprendiz.php';
require_once ROOT_PATH . '/models/ExcusaMedica.php';

//Conexion a la base de datos 
$database = Database::getInstance();
$db = $database->getConnection();

//Despachar la peticion 
$router = new Router($db);
$router->dispatch();
