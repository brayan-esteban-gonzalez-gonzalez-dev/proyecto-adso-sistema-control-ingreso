<?php

declare(strict_types=1);

/**
 * Arranque de PHPUnit.
 *
 * El proyecto no usa namespaces ni src/: las clases viven en
 * models/, helpers/, controllers/ y config/, y se cargan con require,
 * igual que index.php. Aquí no se abre la base de datos ni la sesión:
 * Database::getInstance() crea la BD si no existe, y las pruebas
 * unitarias no deben tocarla. Las de integración conectan cuando
 * lo necesiten.
 */

define('ROOT_PATH', dirname(__DIR__));
define('BASE_URL', '/');
define('UPLOAD_PATH', ROOT_PATH . '/assets/uploads/');

require_once ROOT_PATH . '/librerias/autoload.php';
require_once ROOT_PATH . '/config/env.php';
require_once ROOT_PATH . '/config/Database.php';

foreach (['helpers', 'models', 'controllers'] as $carpeta) {
    foreach (glob(ROOT_PATH . '/' . $carpeta . '/*.php') ?: [] as $archivo) {
        require_once $archivo;
    }
}
