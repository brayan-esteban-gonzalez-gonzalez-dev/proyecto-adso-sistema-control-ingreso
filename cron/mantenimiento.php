<?php
/**
 * cron/mantenimiento.php — Script de mantenimiento automático
 * 
 * Ejecutar al cierre de jornada (ej: 11:59 PM) vía Cron Job o Task Scheduler.
 * 
 * Funciones:
 * 1. Genera registros de inasistencia para aprendices sin marcación
 * 2. Elimina registros "A_Tiempo" del día (solo conserva novedades)
 * 
 * Cron Job (Linux): 
 *   59 23 * * 1-6 /usr/bin/php /ruta/al/proyecto/cron/mantenimiento.php
 * 
 * Task Scheduler (Windows):
 *   Programa: C:\xampp\php\php.exe
 *   Argumento: C:\xampp\htdocs\...\cron\mantenimiento.php
 *   Hora: 23:59, Lun-Sab
 */

// ─── Configuración ───
define('ROOT_PATH', dirname(__DIR__));

require_once ROOT_PATH . '/config/Database.php';
require_once ROOT_PATH . '/models/IngresoAsistencia.php';

// ─── Conexión ───
$db = Database::getInstance()->getConnection();
$ingresoModel = new IngresoAsistencia($db);

$fecha = date('Y-m-d');
$log = "[" . date('Y-m-d H:i:s') . "] Mantenimiento diario iniciado\n";

try {
    // 1. Generar inasistencias
    $inasistencias = $ingresoModel->generarInasistencias($fecha);
    $log .= "  → Inasistencias generadas: {$inasistencias}\n";

    // 2. Limpiar registros "A_Tiempo"
    $eliminados = $ingresoModel->limpiarATiempo($fecha);
    $log .= "  → Registros 'A_Tiempo' eliminados: {$eliminados}\n";

    $log .= "  ✅ Mantenimiento completado exitosamente.\n";
} catch (Exception $e) {
    $log .= "  ❌ Error: " . $e->getMessage() . "\n";
}

// Escribir log
$logFile = ROOT_PATH . '/cron/mantenimiento.log';
file_put_contents($logFile, $log, FILE_APPEND);

// Si se ejecuta desde CLI, mostrar en consola
if (php_sapi_name() === 'cli') {
    echo $log;
}
