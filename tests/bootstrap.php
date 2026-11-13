<?php

declare(strict_types=1);

/**
 * Arranque de PHPUnit.
 *
 * El proyecto no usa namespaces ni src/: las clases viven en
 * app/models/, app/helpers/, app/controllers/ y app/config/, y se cargan
 * con require, igual que index.php. Aquí no se abre la base de datos ni la sesión:
 * Database::getInstance() crea la BD si no existe, y las pruebas
 * unitarias no deben tocarla. Las de integración conectan cuando
 * lo necesiten.
 */

define('ROOT_PATH', dirname(__DIR__));
define('APP_PATH', ROOT_PATH . '/app');
define('BASE_URL', '/');
define('UPLOAD_PATH', APP_PATH . '/public/assets/uploads/');

require_once ROOT_PATH . '/librerias/autoload.php';
require_once APP_PATH . '/config/env.php';
require_once APP_PATH . '/config/Database.php';

foreach (['helpers', 'models', 'controllers'] as $carpeta) {
    foreach (glob(APP_PATH . '/' . $carpeta . '/*.php') ?: [] as $archivo) {
        require_once $archivo;
    }
}

if (file_exists(__DIR__ . '/Integration/IntegrationTestCase.php')) {
    require_once __DIR__ . '/Integration/IntegrationTestCase.php';
}
