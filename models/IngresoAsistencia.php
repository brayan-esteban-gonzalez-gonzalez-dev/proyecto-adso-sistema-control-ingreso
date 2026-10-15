<?php

/**
 * IngresoAsistencia.php — Modelo de Asistencias
 *
 * Gestiona las tablas `asistencia` e `inasistencia` de la nueva DB.
 * Nueva DB: tabla `asistencia`, PK `id`, campos:
 *   fecha, hora_entrada, hora_salida, Usuario_id, Sesion_id,
 *   registrado_por, estado, codigo_llavero, minutos_retardo, minutos_anticipacion.
 */
class IngresoAsistencia
{
    private PDO $conn;
    private string $table = 'asistencia';

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    /**
     * Obtiene el historial de asistencia de una fecha (para Admin/Instructor)
     */
    public function getHistorial(string $fecha): array
    {
        $sql = "SELECT a.*,
                       u.nombre, u.apellido, u.identificacion,
                       f.codigo         AS codigo_ficha,
                       p.nombre         AS nombre_programa,
                       s.fecha          AS sesion_fecha,
                       s.hora_inicio    AS sesion_hora_inicio,
                       s.hora_fin       AS sesion_hora_fin
                FROM {$this->table} a
                INNER JOIN Usuario u  ON a.Usuario_id = u.id
                INNER JOIN sesion  s  ON a.Sesion_id  = s.id
                LEFT  JOIN Ficha   f  ON u.Ficha_id   = f.id
                LEFT  JOIN Programa p ON f.Programa_id = p.id
                WHERE a.fecha = :fecha
                ORDER BY a.fecha DESC, a.hora_entrada DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':fecha' => $fecha]);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene el historial de asistencia de un usuario (aprendiz) en un rango de fechas
     */
    public function getHistorialAprendiz(int $idUsuario, string $fechaInicio, string $fechaFin): array
    {
        $sql = "SELECT a.*,
                       s.hora_inicio AS sesion_hora_inicio,
                       s.hora_fin    AS sesion_hora_fin
                FROM {$this->table} a
                INNER JOIN sesion s ON a.Sesion_id = s.id
                WHERE a.Usuario_id = :id
                  AND a.fecha BETWEEN :inicio AND :fin
                ORDER BY a.fecha DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':id'     => $idUsuario,
            ':inicio' => $fechaInicio,
            ':fin'    => $fechaFin,
        ]);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene estadísticas de asistencia de un usuario en un rango de fechas
     */
    public function getEstadisticasAprendiz(int $idUsuario, string $fechaInicio, string $fechaFin): array
    {
        $sql = "SELECT
                    SUM(CASE WHEN a.minutos_retardo = 0 AND a.estado = 'Activo' THEN 1 ELSE 0 END) AS total_a_tiempo,
                    SUM(CASE WHEN a.minutos_retardo > 0 THEN 1 ELSE 0 END)                          AS total_retardos,
                    SUM(a.minutos_retardo)                                                           AS total_minutos_retardo,
                    COUNT(i.id)                                                                      AS total_inasistencias
                FROM {$this->table} a
                LEFT JOIN inasistencia i
                    ON i.Usuario_id = a.Usuario_id
                   AND i.fecha BETWEEN :inicio2 AND :fin2
                WHERE a.Usuario_id = :id
                  AND a.fecha BETWEEN :inicio AND :fin";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':id'     => $idUsuario,
            ':inicio' => $fechaInicio,
            ':fin'    => $fechaFin,
            ':inicio2' => $fechaInicio,
            ':fin2'   => $fechaFin,
        ]);

        $result = $stmt->fetch();
        return [
            'total_a_tiempo'        => (int)($result['total_a_tiempo']        ?? 0),
            'total_retardos'        => (int)($result['total_retardos']         ?? 0),
            'total_inasistencias'   => (int)($result['total_inasistencias']    ?? 0),
            'total_minutos_retardo' => (int)($result['total_minutos_retardo']  ?? 0),
        ];
    }

    /**
     * Registra una asistencia (entrada)
     */
    public function registrarEntrada(int $usuarioId, int $sesionId, int $registradoPor, string $codigoLlavero = null): bool
    {
        $sql = "INSERT INTO {$this->table}
                    (fecha, hora_entrada, Usuario_id, Sesion_id, registrado_por, codigo_llavero, estado)
                VALUES
                    (CURDATE(), CURTIME(), :usuario_id, :sesion_id, :registrado_por, :codigo_llavero, 'Activo')";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':usuario_id'      => $usuarioId,
            ':sesion_id'       => $sesionId,
            ':registrado_por'  => $registradoPor,
            ':codigo_llavero'  => $codigoLlavero,
        ]);
    }

    /**
     * Marca la salida de una asistencia
     */
    public function registrarSalida(int $asistenciaId): bool
    {
        $sql = "UPDATE {$this->table}
                SET hora_salida = CURTIME(), estado = 'Completado'
                WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $asistenciaId]);
    }

    /**
     * Marca una asistencia como justificada (compatible con ExcusaController)
     */
    public function justificar(int $asistenciaId): bool
    {
        // En la nueva DB no hay estado 'Justificado' en asistencia;
        // la justificación se gestiona a través de la tabla excusa/estado_excusa.
        // Este método queda como stub para compatibilidad.
        return true;
    }

    // ═══════════════════════════════════════════════════════════
    // MÉTODOS PARA MARCACIÓN RFID (kiosco)
    // ═══════════════════════════════════════════════════════════

    /**
     * Busca un usuario activo por su código de llavero RFID.
     * Devuelve datos del usuario + nombre de rol + código de ficha, o null.
     */
    public function buscarPorLlavero(string $codigoLlavero): ?array {
        $sql = "SELECT u.id, u.nombre, u.apellido, u.identificacion, u.email,
                       u.Rol_id, u.Ficha_id, u.codigo_llavero, u.estado,
                       r.nombre AS nombre_rol,
                       f.codigo AS codigo_ficha
                FROM Usuario u
                INNER JOIN Rol r ON u.Rol_id = r.id
                LEFT  JOIN Ficha f ON u.Ficha_id = f.id
                WHERE u.codigo_llavero = :codigo AND u.estado = 'Activo'";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':codigo' => $codigoLlavero]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Busca o crea una competencia por defecto para registrar la sesión
     */
    private function findOrCreateDefaultCompetencia(int $programaId): int {
        $stmt = $this->conn->prepare("SELECT id FROM competencia WHERE Programa_id = :pid LIMIT 1");
        $stmt->execute([':pid' => $programaId]);
        $c = $stmt->fetch();
        if ($c) return (int) $c['id'];

        $stmt2 = $this->conn->query("SELECT id FROM competencia LIMIT 1");
        $c2 = $stmt2->fetch();
        if ($c2) return (int) $c2['id'];

        $stmtIns = $this->conn->prepare("INSERT INTO competencia (Programa_id, nombre, descripcion) VALUES (:pid, 'Formación Técnica', 'Competencia general por defecto')");
        $stmtIns->execute([':pid' => $programaId ?: 1]);
        return (int) $this->conn->lastInsertId();
    }

    /**
     * Obtiene la sesión activa para la ficha de un aprendiz.
     * Si no existe ninguna sesión creada hoy, la genera automáticamente con la jornada de la ficha.
     */
    public function obtenerSesionActivaParaAprendiz(int $idFicha, string $fecha, string $horaActual): ?array {
        $sql = "SELECT s.*, c.nombre AS nombre_competencia
                FROM sesion s
                LEFT JOIN competencia c ON s.Competencia_id = c.id
                WHERE s.Ficha_id = :ficha
                  AND s.fecha = :fecha
                  AND s.estado = 'Activo'
                ORDER BY ABS(TIMESTAMPDIFF(SECOND, s.hora_inicio, :hora)) ASC
                LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':ficha' => $idFicha,
            ':fecha' => $fecha,
            ':hora'  => $horaActual,
        ]);
        $result = $stmt->fetch();
        if ($result) {
            return $result;
        }

        // Auto-crear sesión de hoy para esta ficha si no existe
        $stmtFicha = $this->conn->prepare("SELECT f.*, j.hora_inicio, j.hora_fin 
                                            FROM Ficha f 
                                            LEFT JOIN jornada j ON f.jornada_id = j.id 
                                            WHERE f.id = :id LIMIT 1");
        $stmtFicha->execute([':id' => $idFicha]);
        $ficha = $stmtFicha->fetch();
        if (!$ficha) {
            return null;
        }

        $instructorId = (int) ($ficha['instructor_id'] ?: 0);
        if ($instructorId === 0) {
            $stmtInst = $this->conn->query("SELECT id FROM Usuario WHERE Rol_id = 2 AND estado = 'Activo' LIMIT 1");
            $inst = $stmtInst->fetch();
            $instructorId = $inst ? (int)$inst['id'] : 1;
        }

        $horaInicio = $ficha['hora_inicio'] ?: '06:00:00';
        $horaFin    = $ficha['hora_fin']    ?: '12:00:00';
        $compId     = $this->findOrCreateDefaultCompetencia((int)$ficha['Programa_id']);

        $sqlIns = "INSERT INTO sesion (Ficha_id, Competencia_id, Instructor_id, fecha, hora_inicio, hora_fin, estado)
                   VALUES (:ficha, :comp, :inst, :fecha, :h_in, :h_fin, 'Activo')";
        $stmtIns = $this->conn->prepare($sqlIns);
        $stmtIns->execute([
            ':ficha'  => $idFicha,
            ':comp'   => $compId,
            ':inst'   => $instructorId,
            ':fecha'  => $fecha,
            ':h_in'   => $horaInicio,
            ':h_fin'  => $horaFin,
        ]);

        $newId = (int) $this->conn->lastInsertId();
        $stmtSes = $this->conn->prepare("SELECT s.*, c.nombre AS nombre_competencia FROM sesion s LEFT JOIN competencia c ON s.Competencia_id = c.id WHERE s.id = :id");
        $stmtSes->execute([':id' => $newId]);
        return $stmtSes->fetch() ?: null;
    }

    /**
     * Obtiene la sesión activa para un instructor.
     * Si no existe ninguna sesión creada hoy para este instructor, la genera automáticamente.
     */
    public function obtenerSesionActivaParaInstructor(int $idInstructor, string $fecha, string $horaActual): ?array {
        $sql = "SELECT s.*, c.nombre AS nombre_competencia
                FROM sesion s
                LEFT JOIN competencia c ON s.Competencia_id = c.id
                WHERE s.Instructor_id = :instructor
                  AND s.fecha = :fecha
                  AND s.estado = 'Activo'
                ORDER BY ABS(TIMESTAMPDIFF(SECOND, s.hora_inicio, :hora)) ASC
                LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':instructor' => $idInstructor,
            ':fecha'      => $fecha,
            ':hora'       => $horaActual,
        ]);
        $result = $stmt->fetch();
        if ($result) {
            return $result;
        }

        // Auto-crear sesión de hoy para la ficha asignada a este instructor
        $stmtFicha = $this->conn->prepare("SELECT f.*, j.hora_inicio, j.hora_fin 
                                            FROM Ficha f 
                                            LEFT JOIN jornada j ON f.jornada_id = j.id 
                                            WHERE f.instructor_id = :inst AND f.estado = 'Activo' 
                                            LIMIT 1");
        $stmtFicha->execute([':inst' => $idInstructor]);
        $ficha = $stmtFicha->fetch();

        if (!$ficha) {
            // Fallback a cualquier ficha activa
            $stmtFicha = $this->conn->query("SELECT f.*, j.hora_inicio, j.hora_fin FROM Ficha f LEFT JOIN jornada j ON f.jornada_id = j.id WHERE f.estado = 'Activo' LIMIT 1");
            $ficha = $stmtFicha->fetch();
        }

        if (!$ficha) {
            return null;
        }

        $horaInicio = $ficha['hora_inicio'] ?: '06:00:00';
        $horaFin    = $ficha['hora_fin']    ?: '12:00:00';
        $compId     = $this->findOrCreateDefaultCompetencia((int)$ficha['Programa_id']);

        $sqlIns = "INSERT INTO sesion (Ficha_id, Competencia_id, Instructor_id, fecha, hora_inicio, hora_fin, estado)
                   VALUES (:ficha, :comp, :inst, :fecha, :h_in, :h_fin, 'Activo')";
        $stmtIns = $this->conn->prepare($sqlIns);
        $stmtIns->execute([
            ':ficha'  => $ficha['id'],
            ':comp'   => $compId,
            ':inst'   => $idInstructor,
            ':fecha'  => $fecha,
            ':h_in'   => $horaInicio,
            ':h_fin'  => $horaFin,
        ]);

        $newId = (int) $this->conn->lastInsertId();
        $stmtSes = $this->conn->prepare("SELECT s.*, c.nombre AS nombre_competencia FROM sesion s LEFT JOIN competencia c ON s.Competencia_id = c.id WHERE s.id = :id");
        $stmtSes->execute([':id' => $newId]);
        return $stmtSes->fetch() ?: null;
    }

    /**
     * Busca un registro de asistencia existente para un usuario en una sesión.
     * Aprovecha el UNIQUE (Usuario_id, Sesion_id).
     */
    public function buscarAsistenciaDeHoy(int $idUsuario, int $idSesion): ?array {
        $sql = "SELECT * FROM {$this->table}
                WHERE Usuario_id = :usuario AND Sesion_id = :sesion";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':usuario' => $idUsuario,
            ':sesion'  => $idSesion,
        ]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Busca la asistencia del instructor asignado a una sesión.
     * Se usa para calcular la tolerancia del aprendiz.
     */
    public function buscarAsistenciaInstructorEnSesion(int $idSesion): ?array {
        $sql = "SELECT a.*
                FROM {$this->table} a
                INNER JOIN sesion s ON a.Sesion_id = s.id
                WHERE a.Sesion_id = :sesion
                  AND a.Usuario_id = s.Instructor_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':sesion' => $idSesion]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Registra una entrada de asistencia con minutos_retardo ya calculados.
     * Variante RFID que acepta todos los campos pre-calculados.
     */
    public function registrarEntradaRfid(
        int $usuarioId,
        int $sesionId,
        int $registradoPor,
        ?string $codigoLlavero,
        int $minutosRetardo = 0
    ): bool {
        $sql = "INSERT INTO {$this->table}
                    (fecha, hora_entrada, Usuario_id, Sesion_id, registrado_por,
                     codigo_llavero, estado, minutos_retardo)
                VALUES
                    (CURDATE(), CURTIME(), :usuario_id, :sesion_id, :registrado_por,
                     :codigo_llavero, 'Activo', :minutos_retardo)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':usuario_id'      => $usuarioId,
            ':sesion_id'       => $sesionId,
            ':registrado_por'  => $registradoPor,
            ':codigo_llavero'  => $codigoLlavero,
            ':minutos_retardo' => $minutosRetardo,
        ]);
    }

    /**
     * Registra la salida de una asistencia con minutos_anticipacion ya calculados.
     * Variante RFID que acepta el campo de salida anticipada pre-calculado.
     */
    public function registrarSalidaRfid(int $asistenciaId, int $minutosAnticipacion = 0): bool {
        $sql = "UPDATE {$this->table}
                SET hora_salida = CURTIME(),
                    estado = 'Completado',
                    minutos_anticipacion = :minutos_anticipacion
                WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':id'                    => $asistenciaId,
            ':minutos_anticipacion'  => $minutosAnticipacion,
        ]);
    }
}
