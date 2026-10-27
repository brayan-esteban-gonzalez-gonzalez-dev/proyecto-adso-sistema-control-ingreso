<?php
declare(strict_types=1);

/**
 * ExcusaMedica.php — Modelo de Excusas
 *
 * CRUD para las tablas `excusa` y `estado_excusa` de la base de datos oficial.
 * Estructura:
 *   excusa: id, fecha, motivo, evidencia, Usuario_id, Asistencia_id, Inasistencia_id
 *   estado_excusa: id, fecha, respuesta, estado (Aprobada|Rechazada), Excusa_id, Instructor_id
 */
class ExcusaMedica
{
    private PDO $conn;
    private string $table       = 'excusa';
    private string $tableEstado = 'estado_excusa';

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    /**
     * Obtiene todas las excusas con datos del usuario, inasistencia y estado de revisión.
     */
    public function getAll(): array
    {
        $sql = "SELECT e.*,
                       u.nombre, u.apellido, u.identificacion,
                       f.codigo         AS codigo_ficha,
                       p.nombre         AS nombre_programa,
                       ee.estado        AS estado_revision,
                       ee.respuesta     AS comentario_revision,
                       ee.fecha         AS fecha_revision,
                       CONCAT(rev.nombre, ' ', rev.apellido) AS nombre_revisor,
                       i.fecha          AS fecha_inasistencia,
                       s.hora_inicio    AS sesion_hora_inicio,
                       s.hora_fin       AS sesion_hora_fin,
                       COALESCE(c.nombre, 'Sesión formativa') AS nombre_competencia
                FROM {$this->table} e
                INNER JOIN Usuario u   ON e.Usuario_id     = u.id
                LEFT JOIN Ficha   f    ON u.Ficha_id        = f.id
                LEFT JOIN Programa p   ON f.Programa_id     = p.id
                LEFT JOIN {$this->tableEstado} ee ON ee.Excusa_id = e.id
                LEFT JOIN Usuario rev  ON ee.Instructor_id  = rev.id
                LEFT JOIN inasistencia i ON e.Inasistencia_id = i.id
                LEFT JOIN sesion s     ON i.Sesion_id       = s.id
                LEFT JOIN competencia c ON s.Competencia_id = c.id
                ORDER BY e.fecha DESC, e.id DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Obtiene una excusa por su ID con datos completos del aprendiz, la inasistencia y la revisión.
     */
    public function getById(int $id): ?array
    {
        $sql = "SELECT e.*,
                       u.nombre, u.apellido, u.identificacion,
                       u.email,
                       f.codigo         AS codigo_ficha,
                       p.nombre         AS nombre_programa,
                       ee.estado        AS estado_revision,
                       ee.respuesta     AS comentario_revision,
                       ee.fecha         AS fecha_revision,
                       CONCAT(rev.nombre, ' ', rev.apellido) AS nombre_revisor,
                       i.fecha          AS fecha_inasistencia,
                       s.hora_inicio    AS sesion_hora_inicio,
                       s.hora_fin       AS sesion_hora_fin,
                       COALESCE(c.nombre, 'Sesión formativa') AS nombre_competencia
                FROM {$this->table} e
                INNER JOIN Usuario u   ON e.Usuario_id     = u.id
                LEFT JOIN Ficha   f    ON u.Ficha_id        = f.id
                LEFT JOIN Programa p   ON f.Programa_id     = p.id
                LEFT JOIN {$this->tableEstado} ee ON ee.Excusa_id = e.id
                LEFT JOIN Usuario rev  ON ee.Instructor_id  = rev.id
                LEFT JOIN inasistencia i ON e.Inasistencia_id = i.id
                LEFT JOIN sesion s     ON i.Sesion_id       = s.id
                LEFT JOIN competencia c ON s.Competencia_id = c.id
                WHERE e.id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Obtiene las excusas de un aprendiz específico con datos de la inasistencia.
     */
    public function getByAprendiz(int $idUsuario): array
    {
        $sql = "SELECT e.*,
                       ee.estado        AS estado_revision,
                       ee.respuesta     AS comentario_revision,
                       ee.fecha         AS fecha_revision,
                       CONCAT(rev.nombre, ' ', rev.apellido) AS nombre_revisor,
                       i.fecha          AS fecha_inasistencia,
                       s.hora_inicio    AS sesion_hora_inicio,
                       s.hora_fin       AS sesion_hora_fin,
                       COALESCE(c.nombre, 'Sesión formativa') AS nombre_competencia
                FROM {$this->table} e
                LEFT JOIN {$this->tableEstado} ee ON ee.Excusa_id = e.id
                LEFT JOIN Usuario rev  ON ee.Instructor_id  = rev.id
                LEFT JOIN inasistencia i ON e.Inasistencia_id = i.id
                LEFT JOIN sesion s     ON i.Sesion_id       = s.id
                LEFT JOIN competencia c ON s.Competencia_id = c.id
                WHERE e.Usuario_id = :id
                ORDER BY e.fecha DESC, e.id DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $idUsuario]);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene excusas pendientes de revisión (sin registro en estado_excusa).
     */
    public function getPendientes(): array
    {
        $sql = "SELECT e.*,
                       u.nombre, u.apellido, u.identificacion,
                       f.codigo         AS codigo_ficha,
                       p.nombre         AS nombre_programa,
                       i.fecha          AS fecha_inasistencia,
                       s.hora_inicio    AS sesion_hora_inicio,
                       s.hora_fin       AS sesion_hora_fin,
                       COALESCE(c.nombre, 'Sesión formativa') AS nombre_competencia
                FROM {$this->table} e
                INNER JOIN Usuario u   ON e.Usuario_id      = u.id
                LEFT JOIN Ficha   f    ON u.Ficha_id        = f.id
                LEFT JOIN Programa p   ON f.Programa_id     = p.id
                LEFT JOIN inasistencia i ON e.Inasistencia_id = i.id
                LEFT JOIN sesion s     ON i.Sesion_id       = s.id
                LEFT JOIN competencia c ON s.Competencia_id = c.id
                WHERE NOT EXISTS (
                    SELECT 1 FROM {$this->tableEstado} ee WHERE ee.Excusa_id = e.id
                )
                ORDER BY e.fecha ASC, e.id ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Crea una nueva excusa médica vinculada obligatoriamente a una inasistencia previa.
     *
     * @param array $datos Parámetros:
     *                     - ingreso_asistencia_id / Inasistencia_id (int)
     *                     - usuario_id / id_aprendiz (int)
     *                     - descripcion / motivo (string)
     *                     - ruta_archivo / evidencia (string)
     *                     - fecha (string opcional, Y-m-d)
     * @return bool True si la inserción fue exitosa
     */
    public function crear(array $datos): bool
    {
        $inasistenciaId = $datos['ingreso_asistencia_id']
            ?? $datos['Inasistencia_id']
            ?? $datos['inasistencia_id']
            ?? null;

        $asistenciaId = $datos['Asistencia_id']
            ?? $datos['asistencia_id']
            ?? null;

        $usuarioId = $datos['usuario_id']
            ?? $datos['Usuario_id']
            ?? $datos['id_aprendiz']
            ?? null;

        $motivo = trim($datos['descripcion'] ?? $datos['motivo'] ?? '');
        $evidencia = $datos['ruta_archivo'] ?? $datos['evidencia'] ?? $datos['archivo_adjunto'] ?? null;
        $fecha = $datos['fecha'] ?? date('Y-m-d');

        $sql = "INSERT INTO {$this->table}
                    (fecha, motivo, evidencia, Usuario_id, Asistencia_id, Inasistencia_id)
                VALUES
                    (:fecha, :motivo, :evidencia, :Usuario_id, :Asistencia_id, :Inasistencia_id)";

        $stmt = $this->conn->prepare($sql);
        $ok = $stmt->execute([
            ':fecha'           => $fecha,
            ':motivo'          => $motivo,
            ':evidencia'       => $evidencia,
            ':Usuario_id'      => $usuarioId,
            ':Asistencia_id'   => $asistenciaId ? (int) $asistenciaId : null,
            ':Inasistencia_id' => $inasistenciaId ? (int) $inasistenciaId : null,
        ]);

        if ($ok && $inasistenciaId) {
            // Actualizar resilientemente el estado de la inasistencia a 'En revisión' si la columna existe
            $this->actualizarEstadoInasistencia((int) $inasistenciaId, 'En revisión');
        }

        return $ok;
    }

    /**
     * Alias compatible de crear()
     */
    public function create(array $data): bool
    {
        return $this->crear($data);
    }

    /**
     * Verifica si una excusa requiere doble validación por co-docencia en la jornada (RF-HOR-34)
     */
    public function requiereDobleAprobacion(int $idExcusa): bool
    {
        $sql = "SELECT s.Ficha_id, s.fecha
                FROM {$this->table} e
                INNER JOIN inasistencia i ON e.Inasistencia_id = i.id
                INNER JOIN sesion s ON i.Sesion_id = s.id
                WHERE e.id = :id
                LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $idExcusa]);
        $row = $stmt->fetch();
        if (!$row) {
            return false;
        }

        $fichaId = (int) $row['Ficha_id'];
        $fecha   = $row['fecha'];

        $sqlCount = "SELECT COUNT(DISTINCT Instructor_id) AS total_instructores
                     FROM sesion
                     WHERE Ficha_id = :ficha_id
                       AND fecha = :fecha
                       AND estado != 'Cancelado'";
        $stmtCount = $this->conn->prepare($sqlCount);
        $stmtCount->execute([
            ':ficha_id' => $fichaId,
            ':fecha'    => $fecha,
        ]);
        $countRow = $stmtCount->fetch();
        return ((int)($countRow['total_instructores'] ?? 0)) >= 2;
    }

    /**
     * Registra la revisión de un instructor sobre una excusa.
     * Si requiere doble aprobación, solo marca como 'Aprobada' cuando todos los
     * instructores de la jornada hayan aprobado.
     */
    public function registrarRevision(int $idExcusa, int $instructorId, string $estado, ?string $respuesta = null): array
    {
        $estado = in_array($estado, ['Aprobada', 'Rechazada'], true) ? $estado : 'Rechazada';

        // Insertar o actualizar la revisión del instructor
        $sql = "INSERT INTO {$this->tableEstado} (Excusa_id, Instructor_id, estado, respuesta, fecha)
                VALUES (:excusa_id, :instructor_id, :estado, :respuesta, NOW())
                ON DUPLICATE KEY UPDATE estado = :estado2, respuesta = :respuesta2, fecha = NOW()";
        $stmt = $this->conn->prepare($sql);
        $ok = $stmt->execute([
            ':excusa_id'   => $idExcusa,
            ':instructor_id'=> $instructorId,
            ':estado'      => $estado,
            ':respuesta'   => $respuesta,
            ':estado2'     => $estado,
            ':respuesta2'  => $respuesta,
        ]);

        if (!$ok) {
            return ['ok' => false, 'mensaje' => 'No se pudo registrar la revisión.'];
        }

        // Si es rechazo → rechazar inmediatamente
        if ($estado === 'Rechazada') {
            $this->actualizarEstadoExcusa($idExcusa, 'Rechazada', $respuesta);
            return ['ok' => true, 'mensaje' => 'Excusa rechazada.'];
        }

        // Si requiere doble aprobación, verificar si ya todos aprobaron
        if ($this->requiereDobleAprobacion($idExcusa)) {
            if ($this->todosInstructoresAprobaron($idExcusa)) {
                $this->actualizarEstadoExcusa($idExcusa, 'Aprobada', $respuesta);
                return ['ok' => true, 'mensaje' => 'Excusa aprobada por unanimidad.'];
            } else {
                $this->actualizarEstadoExcusa($idExcusa, 'En revisión', $respuesta);
                return ['ok' => true, 'mensaje' => 'Revisión registrada. Esperando aprobación de los demás instructores.'];
            }
        }

        // Caso normal (un solo instructor)
        $this->actualizarEstadoExcusa($idExcusa, 'Aprobada', $respuesta);
        return ['ok' => true, 'mensaje' => 'Excusa aprobada.'];
    }

    /**
     * Verifica si todos los instructores que dan clase en esa jornada ya aprobaron.
     */
    private function todosInstructoresAprobaron(int $idExcusa): bool
    {
        $sql = "SELECT s.Ficha_id, s.fecha
                FROM {$this->table} e
                INNER JOIN inasistencia i ON e.Inasistencia_id = i.id
                INNER JOIN sesion s ON i.Sesion_id = s.id
                WHERE e.id = :id LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $idExcusa]);
        $row = $stmt->fetch();
        if (!$row) return false;

        $fichaId = (int)$row['Ficha_id'];
        $fecha   = $row['fecha'];

        // Total de instructores que dan clase ese día
        $sqlTotal = "SELECT COUNT(DISTINCT Instructor_id) AS total
                     FROM sesion
                     WHERE Ficha_id = :ficha_id AND fecha = :fecha AND estado != 'Cancelado'";
        $stmtTotal = $this->conn->prepare($sqlTotal);
        $stmtTotal->execute([':ficha_id' => $fichaId, ':fecha' => $fecha]);
        $total = (int)($stmtTotal->fetch()['total'] ?? 0);

        // Total de aprobaciones registradas
        $sqlAprob = "SELECT COUNT(DISTINCT ee.Instructor_id) AS aprobados
                     FROM {$this->tableEstado} ee
                     INNER JOIN {$this->table} e ON ee.Excusa_id = e.id
                     INNER JOIN inasistencia i ON e.Inasistencia_id = i.id
                     INNER JOIN sesion s ON i.Sesion_id = s.id
                     WHERE e.id = :id AND ee.estado = 'Aprobada'
                       AND s.Ficha_id = :ficha_id AND s.fecha = :fecha";
        $stmtAprob = $this->conn->prepare($sqlAprob);
        $stmtAprob->execute([':id' => $idExcusa, ':ficha_id' => $fichaId, ':fecha' => $fecha]);
        $aprobados = (int)($stmtAprob->fetch()['aprobados'] ?? 0);

        return $aprobados >= $total && $total > 0;
    }

    private function actualizarEstadoExcusa(int $idExcusa, string $estado, ?string $respuesta): void
    {
        $sql = "UPDATE {$this->tableEstado}
                SET estado = :estado, respuesta = :respuesta
                WHERE Excusa_id = :id
                ORDER BY id DESC LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':estado'    => $estado,
            ':respuesta' => $respuesta,
            ':id'        => $idExcusa
        ]);
    }

    /**
     * Aprueba una excusa: registra 'Aprobada' en estado_excusa y actualiza la inasistencia.
     * Soporta regla de doble validación por co-docencia en la jornada (RF-HOR-34).
     *
     * @return array{ok: bool, completa: bool, mensaje: string}
     */
    public function aprobar(int $idExcusa, int $idInstructor, string $comentario = '', bool $esAdmin = false): array
    {
        $requiereDoble = $this->requiereDobleAprobacion($idExcusa);

        if (!$requiereDoble || $esAdmin) {
            $ok = $this->registrarEstado($idExcusa, $idInstructor, 'Aprobada', $comentario);
            if ($ok) {
                $inasistenciaId = $this->obtenerInasistenciaIdDeExcusa($idExcusa);
                if ($inasistenciaId !== null) {
                    $this->actualizarEstadoInasistencia($inasistenciaId, 'Justificada');
                }
            }
            return [
                'ok' => $ok,
                'completa' => true,
                'mensaje' => 'Excusa médica aprobada correctamente. La inasistencia ha sido justificada.'
            ];
        }

        // Requiere doble validación (RF-HOR-34)
        $check = $this->conn->prepare("SELECT id, Instructor_id, estado, respuesta FROM {$this->tableEstado} WHERE Excusa_id = :id LIMIT 1");
        $check->execute([':id' => $idExcusa]);
        $prev = $check->fetch();

        if ($prev && (int)$prev['Instructor_id'] !== $idInstructor && $prev['estado'] === 'Pendiente') {
            // Segunda firma recibida de co-docente
            $comentarioFinal = trim($prev['respuesta'] . " | 2da aprobación: " . $comentario);
            $ok = $this->registrarEstado($idExcusa, $idInstructor, 'Aprobada', $comentarioFinal);
            if ($ok) {
                $inasistenciaId = $this->obtenerInasistenciaIdDeExcusa($idExcusa);
                if ($inasistenciaId !== null) {
                    $this->actualizarEstadoInasistencia($inasistenciaId, 'Justificada');
                }
            }
            return [
                'ok' => $ok,
                'completa' => true,
                'mensaje' => 'Doble validación completada con éxito. La inasistencia ha sido justificada.'
            ];
        }

        // Primera aprobación registrada
        $comentarioParcial = "[1 de 2 firmas requeridas por co-docencia] " . $comentario;
        $ok = $this->registrarEstado($idExcusa, $idInstructor, 'Pendiente', $comentarioParcial);
        return [
            'ok' => $ok,
            'completa' => false,
            'mensaje' => 'Primera aprobación registrada. Por regla de co-docencia (RF-HOR-34), se requiere la validación del segundo instructor de la jornada.'
        ];
    }

    /**
     * Rechaza una excusa: registra 'Rechazada' en estado_excusa y actualiza la inasistencia.
     */
    public function rechazar(int $idExcusa, int $idInstructor, string $comentario = ''): bool
    {
        $ok = $this->registrarEstado($idExcusa, $idInstructor, 'Rechazada', $comentario);
        if ($ok) {
            $inasistenciaId = $this->obtenerInasistenciaIdDeExcusa($idExcusa);
            if ($inasistenciaId !== null) {
                $this->actualizarEstadoInasistencia($inasistenciaId, 'Injustificada');
            }
        }
        return $ok;
    }

    /**
     * Inserta o actualiza el registro en estado_excusa.
     */
    private function registrarEstado(int $idExcusa, int $idInstructor, string $estado, string $respuesta): bool
    {
        $check = $this->conn->prepare("SELECT id FROM {$this->tableEstado} WHERE Excusa_id = :id LIMIT 1");
        $check->execute([':id' => $idExcusa]);
        $existing = $check->fetch();

        if ($existing) {
            $sql = "UPDATE {$this->tableEstado}
                    SET estado = :estado, respuesta = :respuesta, fecha = CURDATE(), Instructor_id = :instructor
                    WHERE Excusa_id = :excusa_id";
        } else {
            $sql = "INSERT INTO {$this->tableEstado} (fecha, respuesta, estado, Excusa_id, Instructor_id)
                    VALUES (CURDATE(), :respuesta, :estado, :excusa_id, :instructor)";
        }

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':estado'     => $estado,
            ':respuesta'  => $respuesta,
            ':excusa_id'  => $idExcusa,
            ':instructor' => $idInstructor,
        ]);
    }

    /**
     * Obtiene el Inasistencia_id vinculado a una excusa específica.
     */
    private function obtenerInasistenciaIdDeExcusa(int $idExcusa): ?int
    {
        $stmt = $this->conn->prepare("SELECT Inasistencia_id FROM {$this->table} WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $idExcusa]);
        $res = $stmt->fetch();
        return !empty($res['Inasistencia_id']) ? (int) $res['Inasistencia_id'] : null;
    }

    /**
     * Actualiza el estado en la tabla inasistencia de forma resiliente
     * (solo si la columna 'estado' existe en el esquema).
     */
    private function actualizarEstadoInasistencia(int $inasistenciaId, string $estado): bool
    {
        try {
            $check = $this->conn->query("SHOW COLUMNS FROM inasistencia LIKE 'estado'");
            if ($check && $check->rowCount() > 0) {
                $sql = "UPDATE inasistencia SET estado = :estado WHERE id = :id";
                $stmt = $this->conn->prepare($sql);
                return $stmt->execute([
                    ':estado' => $estado,
                    ':id'     => $inasistenciaId,
                ]);
            }
        } catch (\PDOException $e) {
            // Silencioso si la columna no existe en la base de datos
        }
        return false;
    }

    /**
     * Cuenta excusas pendientes (sin estado_excusa).
     */
    public function countPendientes(): int
    {
        $sql = "SELECT COUNT(*) AS total
                FROM {$this->table} e
                WHERE NOT EXISTS (
                    SELECT 1 FROM {$this->tableEstado} ee WHERE ee.Excusa_id = e.id
                )";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return (int) ($stmt->fetch()['total'] ?? 0);
    }
}
