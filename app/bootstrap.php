<?php

/**
 * Arranque web. index.php (raíz) define ROOT_PATH, APP_PATH, BASE_URL y UPLOAD_PATH.
 */

if (file_exists(ROOT_PATH . '/librerias/autoload.php')) {
    require_once ROOT_PATH . '/librerias/autoload.php';
} elseif (file_exists(ROOT_PATH . '/vendor/autoload.php')) {
    require_once ROOT_PATH . '/vendor/autoload.php';
}

require_once APP_PATH . '/config/env.php';
require_once APP_PATH . '/config/Database.php';
require_once APP_PATH . '/helpers/Auth.php';
require_once APP_PATH . '/helpers/Validator.php';
require_once APP_PATH . '/helpers/Router.php';

Auth::init();

$modelsDir = APP_PATH . '/models/';
if (is_dir($modelsDir)) {
    foreach (glob($modelsDir . '*.php') as $modelFile) {
        require_once $modelFile;
    }
}

$database = Database::getInstance();
$db = $database->getConnection();

$router = new Router($db);
$router->dispatch();
