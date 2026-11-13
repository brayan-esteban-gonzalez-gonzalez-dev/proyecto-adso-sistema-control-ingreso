<?php

// Raíz del repositorio (librerias, .env, index.php). Apache sirve esta carpeta.
define('ROOT_PATH', __DIR__);

// Código de la aplicación.
define('APP_PATH', ROOT_PATH . '/app');

// Detectar la URL base del proyecto automaticamente
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
define('BASE_URL', rtrim($scriptDir, '/') . '/');

// Evidencias públicas. La URL sigue siendo assets/uploads/ (ver .htaccess).
define('UPLOAD_PATH', APP_PATH . '/public/assets/uploads/');

require APP_PATH . '/bootstrap.php';
