<?php
declare(strict_types=1);

/**
 * env.php — Cargador de variables de entorno (.env)
 *
 * Utiliza vlucas/phpdotenv para cargar credenciales de base de datos
 * desde un archivo .env sin exponerlas en el código fuente.
 *
 * Debe incluirse ANTES de usar Database.php.
 */

use Dotenv\Dotenv;
use Dotenv\Exception\InvalidPathException;

$rootPath = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__);

// Intentar cargar el archivo .env
try {
    $dotenv = Dotenv::createImmutable($rootPath);
    $dotenv->safeLoad(); // safeLoad no falla si no existe el archivo (útil en algunos entornos)
} catch (InvalidPathException $e) {
    // El archivo .env no existe; continuamos con variables del sistema o valores por defecto
    error_log('[SCIA][Env] No se encontró el archivo .env en la raíz del proyecto.');
}

// Validar que existan las variables mínimas requeridas
$required = ['DB_HOST', 'DB_NAME', 'DB_USER'];
$missing = [];

foreach ($required as $key) {
    if (empty($_ENV[$key] ?? $_SERVER[$key] ?? getenv($key))) {
        $missing[] = $key;
    }
}

if (!empty($missing)) {
    $msg = 'Faltan variables de entorno requeridas: ' . implode(', ', $missing) .
           '. Copia .env.example como .env y configura las credenciales.';
    error_log('[SCIA][Env] ' . $msg);
    // En producción esto debería detener la app, pero para XAMPP mostramos un mensaje amigable
    if (PHP_SAPI !== 'cli') {
        die('Error de configuración del entorno. Contacte al administrador.');
    }
}
