<?php
declare(strict_types=1);

/**
 * PermutaSesion.php — Modelo de Permutas y Cesiones de Sesiones
 *
 * Gestiona las solicitudes y resoluciones de intercambios (permutas)
 * y cesiones de bloques de horario entre instructores (RF-HOR-23 a 28, RF-HOR-43).
 * Basado en la tabla `permuta_sesion` (migración 007).
 */
class PermutaSesion
{
    private PDO $conn;
    private string $table = 'permuta_sesion';

    public function __construct(PDO $db)
    {
        $this->conn = $db;
        $this->asegurarTabla();
    }

    /**
     * Asegura de manera resiliente la existencia de la tabla si no ha corrido la migración 007
     */
    private function asegurarTabla(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->table}` (
            `id` INT AUTO_INCREMENT PRIMARY KEY,
            `sesion_origen_id` INT NOT NULL,
            `sesion_destino_id` INT NOT NULL,
            `instructor_solicitante_id` INT NOT NULL,
            `instructor_receptor_id` INT NOT NULL,
            `estado` ENUM('Pendiente', 'Aprobada', 'Rechazada') NOT NULL DEFAULT 'Pendiente',
            `motivo` TEXT NULL,
            `fecha_solicitud` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            `fecha_resolucion` TIMESTAMP NULL,
            CONSTRAINT `fk_permuta_sesion_origen` FOREIGN KEY (`sesion_origen_id`) REFERENCES `sesion`(`id`),
            CONSTRAINT `fk_permuta_sesion_destino` FOREIGN KEY (`sesion_destino_id`) REFERENCES `sesion`(`id`),
            CONSTRAINT `fk_permuta_solicitante` FOREIGN KEY (`instructor_solicitante_id`) REFERENCES `Usuario`(`id`),
            CONSTRAINT `fk_permuta_receptor` FOREIGN KEY (`instructor_receptor_id`) REFERENCES `Usuario`(`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        try {
            $this->conn->exec($sql);
        } catch (\Throwable $e) {
            // Ignorar si no hay permisos DDL; continuará operando si ya existe
        }
    }

    /**
     * Obtiene una solicitud de permuta por ID con datos detallados de sesiones e instructores
     */
    public function getById(int $id): ?array
    {
        $sql = "SELECT p.*,
                       so.fecha AS fecha_origen, so.hora_inicio AS hora_ini_origen, so.hora_fin AS hora_fin_origen,
                       fo.codigo AS ficha_origen_codigo,
                       sd.fecha AS fecha_destino, sd.hora_inicio AS hora_ini_destino, sd.hora_fin AS hora_fin_destino,
                       fd.codigo AS ficha_destino_codigo,
                       CONCAT(us.nombre, ' ', us.apellido) AS nombre_solicitante,
                       CONCAT(ur.nombre, ' ', ur.apellido) AS nombre_receptor
                FROM {$this->table} p
                INNER JOIN sesion so ON p.sesion_origen_id = so.id
                INNER JOIN Ficha fo ON so.Ficha_id = fo.id
                INNER JOIN sesion sd ON p.sesion_destino_id = sd.id
                INNER JOIN Ficha fd ON sd.Ficha_id = fd.id
                INNER JOIN Usuario us ON p.instructor_solicitante_id = us.id
                INNER JOIN Usuario ur ON p.instructor_receptor_id = ur.id
                WHERE p.id = :id
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Obtiene las solicitudes de permuta pendientes recibidas por un instructor
     */
    public function getPendientesPorReceptor(int $instructorId): array
    {
        $sql = "SELECT p.*,
                       so.fecha AS fecha_origen, so.hora_inicio AS hora_ini_origen, so.hora_fin AS hora_fin_origen,
                       fo.codigo AS ficha_origen_codigo,
                       sd.fecha AS fecha_destino, sd.hora_inicio AS hora_ini_destino, sd.hora_fin AS hora_fin_destino,
                       fd.codigo AS ficha_destino_codigo,
                       CONCAT(us.nombre, ' ', us.apellido) AS nombre_solicitante
                FROM {$this->table} p
                INNER JOIN sesion so ON p.sesion_origen_id = so.id
                INNER JOIN Ficha fo ON so.Ficha_id = fo.id
                INNER JOIN sesion sd ON p.sesion_destino_id = sd.id
                INNER JOIN Ficha fd ON sd.Ficha_id = fd.id
                INNER JOIN Usuario us ON p.instructor_solicitante_id = us.id
                WHERE p.instructor_receptor_id = :instructor_id
                  AND p.estado = 'Pendiente'
                ORDER BY p.fecha_solicitud DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':instructor_id' => $instructorId]);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene las solicitudes de permuta enviadas por un instructor
     */
    public function getEnviadasPorSolicitante(int $instructorId): array
    {
        $sql = "SELECT p.*,
                       so.fecha AS fecha_origen, so.hora_inicio AS hora_ini_origen, so.hora_fin AS hora_fin_origen,
                       fo.codigo AS ficha_origen_codigo,
                       sd.fecha AS fecha_destino, sd.hora_inicio AS hora_ini_destino, sd.hora_fin AS hora_fin_destino,
                       fd.codigo AS ficha_destino_codigo,
                       CONCAT(ur.nombre, ' ', ur.apellido) AS nombre_receptor
                FROM {$this->table} p
                INNER JOIN sesion so ON p.sesion_origen_id = so.id
                INNER JOIN Ficha fo ON so.Ficha_id = fo.id
                INNER JOIN sesion sd ON p.sesion_destino_id = sd.id
                INNER JOIN Ficha fd ON sd.Ficha_id = fd.id
                INNER JOIN Usuario ur ON p.instructor_receptor_id = ur.id
                WHERE p.instructor_solicitante_id = :instructor_id
                ORDER BY p.fecha_solicitud DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':instructor_id' => $instructorId]);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene todas las permutas (para administradores)
     */
    public function getAll(): array
    {
        $sql = "SELECT p.*,
                       so.fecha AS fecha_origen, so.hora_inicio AS hora_ini_origen, so.hora_fin AS hora_fin_origen,
                       fo.codigo AS ficha_origen_codigo,
                       sd.fecha AS fecha_destino, sd.hora_inicio AS hora_ini_destino, sd.hora_fin AS hora_fin_destino,
                       fd.codigo AS ficha_destino_codigo,
                       CONCAT(us.nombre, ' ', us.apellido) AS nombre_solicitante,
                       CONCAT(ur.nombre, ' ', ur.apellido) AS nombre_receptor
                FROM {$this->table} p
                INNER JOIN sesion so ON p.sesion_origen_id = so.id
                INNER JOIN Ficha fo ON so.Ficha_id = fo.id
                INNER JOIN sesion sd ON p.sesion_destino_id = sd.id
                INNER JOIN Ficha fd ON sd.Ficha_id = fd.id
                INNER JOIN Usuario us ON p.instructor_solicitante_id = us.id
                INNER JOIN Usuario ur ON p.instructor_receptor_id = ur.id
                ORDER BY p.fecha_solicitud DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Crea una solicitud de permuta
     */
    public function crearSolicitud(int $sesionOrigenId, int $sesionDestinoId, int $solicitanteId, int $receptorId, string $motivo): array
    {
        if ($sesionOrigenId === $sesionDestinoId) {
            return ['ok' => false, 'mensaje' => 'La sesión de origen y destino deben ser distintas.'];
        }
        if ($solicitanteId === $receptorId) {
            return ['ok' => false, 'mensaje' => 'No puedes proponer una permuta contigo mismo.'];
        }

        // Verificar si ya existe solicitud pendiente para estas sesiones
        $sqlCheck = "SELECT id FROM {$this->table}
                     WHERE ((sesion_origen_id = :o AND sesion_destino_id = :d)
                        OR (sesion_origen_id = :d2 AND sesion_destino_id = :o2))
                       AND estado = 'Pendiente'
                     LIMIT 1";
        $stmtCheck = $this->conn->prepare($sqlCheck);
        $stmtCheck->execute([
            ':o'  => $sesionOrigenId,
            ':d'  => $sesionDestinoId,
            ':d2' => $sesionDestinoId,
            ':o2' => $sesionOrigenId,
        ]);
        if ($stmtCheck->fetch()) {
            return ['ok' => false, 'mensaje' => 'Ya existe una solicitud de permuta pendiente entre estas dos sesiones.'];
        }

        $sql = "INSERT INTO {$this->table} 
                (sesion_origen_id, sesion_destino_id, instructor_solicitante_id, instructor_receptor_id, estado, motivo, fecha_solicitud)
                VALUES
                (:origen, :destino, :solicitante, :receptor, 'Pendiente', :motivo, NOW())";
        $stmt = $this->conn->prepare($sql);
        $ok = $stmt->execute([
            ':origen'      => $sesionOrigenId,
            ':destino'     => $sesionDestinoId,
            ':solicitante' => $solicitanteId,
            ':receptor'    => $receptorId,
            ':motivo'      => $motivo,
        ]);

        if (!$ok) {
            return ['ok' => false, 'mensaje' => 'No se pudo registrar la solicitud de permuta.'];
        }

        return ['ok' => true, 'mensaje' => 'Solicitud de permuta enviada correctamente.'];
    }

    /**
     * Aplica la aprobación de la permuta de forma atómica (RF-HOR-24, RF-HOR-25)
     */
    public function aprobarPermuta(int $permutaId, int $evaluadorId, bool $esAdmin = false): array
    {
        $permuta = $this->getById($permutaId);
        if (!$permuta) {
            return ['ok' => false, 'mensaje' => 'Solicitud de permuta no encontrada.'];
        }

        if ($permuta['estado'] !== 'Pendiente') {
            return ['ok' => false, 'mensaje' => 'La solicitud ya fue resuelta previamente.'];
        }

        // Solo el instructor receptor o un administrador pueden aprobar
        if (!$esAdmin && (int)$permuta['instructor_receptor_id'] !== $evaluadorId) {
            return ['ok' => false, 'mensaje' => 'No tienes permiso para aprobar esta permuta.'];
        }

        $origenId  = (int) $permuta['sesion_origen_id'];
        $destinoId = (int) $permuta['sesion_destino_id'];
        $instSolicitante = (int) $permuta['instructor_solicitante_id'];
        $instReceptor    = (int) $permuta['instructor_receptor_id'];

        // Cargar sesiones para revalidar cruces y vigencia (RF-HOR-25)
        require_once ROOT_PATH . '/models/Sesion.php';
        $sesionModel = new Sesion($this->conn);
        $sesionOrigen = $sesionModel->getById($origenId);
        $sesionDestino = $sesionModel->getById($destinoId);

        if (!$sesionOrigen || !$sesionDestino) {
            return ['ok' => false, 'mensaje' => 'Una de las sesiones involucradas ya no existe.'];
        }

        // RF-HOR-27: Una sesión con asistencia registrada no se puede mover ni reasignar
        if ($sesionModel->tieneAsistencias($origenId) || $sesionModel->tieneAsistencias($destinoId)) {
            return ['ok' => false, 'mensaje' => 'No se puede permutar porque una de las sesiones ya cuenta con asistencias registradas.'];
        }

        // RF-HOR-25: Revalidar que el instructor receptor no tenga conflicto en la franja de origen
        $valReceptor = $sesionModel->validarSolapamiento(
            (int)$sesionOrigen['Ficha_id'],
            $instReceptor,
            $sesionOrigen['fecha'],
            $sesionOrigen['hora_inicio'],
            $sesionOrigen['hora_fin'],
            $destinoId
        );
        if (!$valReceptor['valido']) {
            return ['ok' => false, 'mensaje' => "Conflicto para el receptor: {$valReceptor['mensaje']}"];
        }

        // RF-HOR-25: Revalidar que el instructor solicitante no tenga conflicto en la franja de destino
        $valSolicitante = $sesionModel->validarSolapamiento(
            (int)$sesionDestino['Ficha_id'],
            $instSolicitante,
            $sesionDestino['fecha'],
            $sesionDestino['hora_inicio'],
            $sesionDestino['hora_fin'],
            $origenId
        );
        if (!$valSolicitante['valido']) {
            return ['ok' => false, 'mensaje' => "Conflicto para el solicitante: {$valSolicitante['mensaje']}"];
        }

        try {
            $this->conn->beginTransaction();

            // 1. Intercambiar instructores y marcar estado 'Permutado' (RF-HOR-28)
            // Sesión origen pasa al instructor receptor
            $sqlUpdate1 = "UPDATE sesion 
                           SET Instructor_id = :receptor, estado = 'Permutado'
                           WHERE id = :origen";
            $stmtUp1 = $this->conn->prepare($sqlUpdate1);
            $stmtUp1->execute([
                ':receptor' => $instReceptor,
                ':origen'   => $origenId,
            ]);

            // Sesión destino pasa al instructor solicitante
            $sqlUpdate2 = "UPDATE sesion 
                           SET Instructor_id = :solicitante, estado = 'Permutado'
                           WHERE id = :destino";
            $stmtUp2 = $this->conn->prepare($sqlUpdate2);
            $stmtUp2->execute([
                ':solicitante' => $instSolicitante,
                ':destino'     => $destinoId,
            ]);

            // 2. Marcar la permuta como Aprobada
            $sqlPermuta = "UPDATE {$this->table}
                           SET estado = 'Aprobada', fecha_resolucion = NOW()
                           WHERE id = :id";
            $stmtP = $this->conn->prepare($sqlPermuta);
            $stmtP->execute([':id' => $permutaId]);

            // 3. Sincronizar asignaciones semestrales si la tabla existe (RF-HOR-43)
            $this->sincronizarAsignacion($instReceptor, (int)$sesionOrigen['Ficha_id'], $sesionOrigen['fecha'], $sesionOrigen['hora_inicio'], $sesionOrigen['hora_fin']);
            $this->sincronizarAsignacion($instSolicitante, (int)$sesionDestino['Ficha_id'], $sesionDestino['fecha'], $sesionDestino['hora_inicio'], $sesionDestino['hora_fin']);

            $this->conn->commit();
            return ['ok' => true, 'mensaje' => 'Permuta aprobada con éxito. Las sesiones han sido intercambiadas.'];

        } catch (\Throwable $e) {
            $this->conn->rollBack();
            return ['ok' => false, 'mensaje' => 'Error al aplicar la permuta: ' . $e->getMessage()];
        }
    }

    /**
     * Sincroniza la asignación de instructor semestral tras permuta (RF-HOR-43)
     */
    private function sincronizarAsignacion(int $instructorId, int $fichaId, string $fecha, string $horaInicio, string $horaFin): void
    {
        try {
            $checkTable = $this->conn->query("SHOW TABLES LIKE 'asignacion_instructor'")->fetch();
            if (!$checkTable) {
                return;
            }

            $sql = "INSERT INTO asignacion_instructor (instructor_id, ficha_id, fecha, hora_inicio, hora_fin, estado)
                    VALUES (:instructor, :ficha, :fecha, :inicio, :fin, 'Activo')
                    ON DUPLICATE KEY UPDATE instructor_id = VALUES(instructor_id), estado = 'Activo'";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':instructor' => $instructorId,
                ':ficha'      => $fichaId,
                ':fecha'      => $fecha,
                ':inicio'     => $horaInicio,
                ':fin'        => $horaFin,
            ]);
        } catch (\Throwable $e) {
            // Continúa sin romper la transacción de la permuta
        }
    }

    /**
     * Rechaza una solicitud de permuta
     */
    public function rechazarPermuta(int $permutaId, int $evaluadorId, bool $esAdmin = false): array
    {
        $permuta = $this->getById($permutaId);
        if (!$permuta) {
            return ['ok' => false, 'mensaje' => 'Solicitud de permuta no encontrada.'];
        }

        if ($permuta['estado'] !== 'Pendiente') {
            return ['ok' => false, 'mensaje' => 'La solicitud ya fue resuelta previamente.'];
        }

        // Solo el receptor o admin pueden rechazar
        if (!$esAdmin && (int)$permuta['instructor_receptor_id'] !== $evaluadorId) {
            return ['ok' => false, 'mensaje' => 'No tienes permiso para responder a esta permuta.'];
        }

        $sql = "UPDATE {$this->table}
                SET estado = 'Rechazada', fecha_resolucion = NOW()
                WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $ok = $stmt->execute([':id' => $permutaId]);

        return $ok
            ? ['ok' => true, 'mensaje' => 'Solicitud de permuta rechazada.']
            : ['ok' => false, 'mensaje' => 'No se pudo rechazar la solicitud.'];
    }
}
