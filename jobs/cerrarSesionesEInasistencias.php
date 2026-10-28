<?php
declare(strict_types=1);

/**
 * cerrarSesionesEInasistencias.php — Job CLI de cierre automático de sesiones
 *
 * Cierra todas las sesiones con estado 'Activo' cuya fecha sea <= hoy (sesiones
 * olvidadas por el instructor) e inserta inasistencias para los aprendices
 * que no hayan marcado asistencia en cada sesión.
 *
 * Uso recomendado (Programador de Tareas / cron):
 *   php jobs/cerrarSesionesEInasistencias.php
 *
 * Cron Linux (cada día a las 23:55):
 *   55 23 * * * /usr/bin/php /ruta/al/proyecto/jobs/cerrarSesionesEInasistencias.php >> /var/log/scia_jobs.log 2>&1
 */

// ─── 1. PROTECCIÓN: Solo CLI ────────────────────────────────────────────────
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Acceso prohibido. Este script solo puede ejecutarse desde la línea de comandos (CLI).');
}

// ─── 2. ZONA HORARIA Y RUTAS ────────────────────────────────────────────────
date_default_timezone_set('America/Bogota');

$rootPath = dirname(__DIR__);

// Cargar autoload de Composer (necesario para phpdotenv)
if (file_exists($rootPath . '/librerias/autoload.php')) {
    require_once $rootPath . '/librerias/autoload.php';
} elseif (file_exists($rootPath . '/vendor/autoload.php')) {
    require_once $rootPath . '/vendor/autoload.php';
}

// Cargar variables de entorno desde .env
require_once $rootPath . '/config/env.php';

require_once $rootPath . '/config/Database.php';
require_once $rootPath . '/models/Sesion.php';
require_once $rootPath . '/models/IngresoAsistencia.php';

// ─── 3. HELPERS DE SALIDA ───────────────────────────────────────────────────

/**
 * Imprime una línea con marca de tiempo en STDOUT.
 */
function logOut(string $mensaje): void
{
    $ts = date('Y-m-d H:i:s');
    echo "[{$ts}] {$mensaje}" . PHP_EOL;
}

/**
 * Imprime una línea con marca de tiempo en STDERR y registra en error_log.
 */
function logErr(string $mensaje): void
{
    $ts  = date('Y-m-d H:i:s');
    $txt = "[{$ts}] ERROR: {$mensaje}";
    fwrite(STDERR, $txt . PHP_EOL);
    error_log('[SCIA][Job] ' . $mensaje);
}

// ─── 4. CONEXIÓN ────────────────────────────────────────────────────────────
try {
    $db = Database::getInstance()->getConnection();
    if ($db === null) {
        logErr('No se pudo obtener la conexión PDO.');
        exit(1);
    }
} catch (Throwable $e) {
    logErr('Error al conectar con la base de datos: ' . $e->getMessage());
    exit(1);
}

// ─── 5. BUSCAR SESIONES ABIERTAS ────────────────────────────────────────────
logOut('=== Inicio del job: cerrarSesionesEInasistencias ===');

try {
    $stmtBuscar = $db->prepare(
        "SELECT s.id        AS sesion_id,
                s.Ficha_id  AS ficha_id,
                s.Instructor_id AS instructor_id,
                s.fecha,
                s.hora_inicio,
                f.codigo    AS codigo_ficha
         FROM sesion s
         INNER JOIN ficha f ON s.Ficha_id = f.id
         WHERE s.estado = 'Activo'
           AND s.fecha <= CURDATE()
         ORDER BY s.fecha ASC, s.id ASC"
    );
    $stmtBuscar->execute();
    $sesionesAbiertas = $stmtBuscar->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    logErr('Error al consultar sesiones abiertas: ' . $e->getMessage());
    exit(1);
}

$totalDetectadas  = count($sesionesAbiertas);
$totalCerradas    = 0;
$totalInasistencias = 0;
$totalErrores     = 0;

logOut("Sesiones abiertas detectadas: {$totalDetectadas}");

if ($totalDetectadas === 0) {
    logOut('No hay sesiones pendientes de cierre. Fin del job.');
    exit(0);
}

// ─── 6. CERRAR CADA SESIÓN DE FORMA TRANSACCIONAL ───────────────────────────
foreach ($sesionesAbiertas as $sesion) {
    $sesionId   = (int) $sesion['sesion_id'];
    $fichaId    = (int) $sesion['ficha_id'];
    $codigoFicha = $sesion['codigo_ficha'];
    $fecha      = $sesion['fecha'];

    logOut("Procesando sesión #{$sesionId} | Ficha {$codigoFicha} | Fecha {$fecha}");

    try {
        $db->beginTransaction();

        // 1. Cerrar la sesión: estado = 'Finalizada', hora_fin = COALESCE(hora_fin, CURTIME())
        //    Registrar cierre_real si la columna existe (migración 007).
        $tieneCierreReal = false;
        $chkCol = $db->query("SHOW COLUMNS FROM `sesion` LIKE 'cierre_real'");
        if ($chkCol && $chkCol->rowCount() > 0) {
            $tieneCierreReal = true;
        }

        if ($tieneCierreReal) {
            $sqlCerrar = "UPDATE sesion
                          SET estado      = 'Finalizada',
                              hora_fin    = COALESCE(hora_fin, CURTIME()),
                              cierre_real = CURTIME()
                          WHERE id = :id AND estado = 'Activo'";
        } else {
            $sqlCerrar = "UPDATE sesion
                          SET estado   = 'Finalizada',
                              hora_fin = COALESCE(hora_fin, CURTIME())
                          WHERE id = :id AND estado = 'Activo'";
        }
        $stmtCerrar = $db->prepare($sqlCerrar);
        $stmtCerrar->execute([':id' => $sesionId]);

        if ($stmtCerrar->rowCount() === 0) {
            // Ya fue cerrada por otra ejecución concurrente → idempotente
            $db->rollBack();
            logOut("  → Sesión #{$sesionId} ya estaba cerrada (idempotente). Omitida.");
            continue;
        }

        // 2. Insertar inasistencias para aprendices activos de la ficha sin marcación.
        //    NOT EXISTS garantiza idempotencia (no duplica si el job se ejecuta 2 veces).
        $sqlInasistencia = "INSERT INTO inasistencia
                                (Usuario_id, Ficha_id, Sesion_id, fecha, generado_por)
                            SELECT u.id,
                                   :ficha_id,
                                   :sesion_id,
                                   :fecha,
                                   'job_cli'
                            FROM usuario u
                            WHERE u.Ficha_id = :ficha_id2
                              AND u.Rol_id   = 3
                              AND u.estado   = 'Activo'
                              AND NOT EXISTS (
                                  SELECT 1
                                  FROM asistencia a
                                  WHERE a.Sesion_id  = :sesion_id2
                                    AND a.Usuario_id = u.id
                              )
                              AND NOT EXISTS (
                                  SELECT 1
                                  FROM inasistencia i
                                  WHERE i.Sesion_id  = :sesion_id3
                                    AND i.Usuario_id = u.id
                              )";
        $stmtInas = $db->prepare($sqlInasistencia);
        $stmtInas->execute([
            ':ficha_id'   => $fichaId,
            ':sesion_id'  => $sesionId,
            ':fecha'      => $fecha,
            ':ficha_id2'  => $fichaId,
            ':sesion_id2' => $sesionId,
            ':sesion_id3' => $sesionId,
        ]);
        $inasistenciasGeneradas = $stmtInas->rowCount();

        $db->commit();

        $totalCerradas++;
        $totalInasistencias += $inasistenciasGeneradas;

        logOut("  → Cerrada ✓ | {$inasistenciasGeneradas} inasistencia(s) generada(s) para ficha {$codigoFicha}");

    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $totalErrores++;
        logErr("Sesión #{$sesionId} (ficha {$codigoFicha}): " . $e->getMessage());
    }
}

// ─── 7. RESUMEN FINAL ───────────────────────────────────────────────────────
logOut('');
logOut('=== Resumen del job ===');
logOut("  Sesiones detectadas  : {$totalDetectadas}");
logOut("  Sesiones cerradas    : {$totalCerradas}");
logOut("  Inasistencias nuevas : {$totalInasistencias}");
logOut("  Errores              : {$totalErrores}");
logOut('=== Fin del job ===');

exit($totalErrores > 0 ? 1 : 0);
