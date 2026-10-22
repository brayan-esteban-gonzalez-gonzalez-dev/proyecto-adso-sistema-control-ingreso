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
     * Aprueba una excusa: registra 'Aprobada' en estado_excusa y actualiza la inasistencia.
     */
    public function aprobar(int $idExcusa, int $idInstructor, string $comentario = ''): bool
    {
        $ok = $this->registrarEstado($idExcusa, $idInstructor, 'Aprobada', $comentario);
        if ($ok) {
            $inasistenciaId = $this->obtenerInasistenciaIdDeExcusa($idExcusa);
            if ($inasistenciaId !== null) {
                $this->actualizarEstadoInasistencia($inasistenciaId, 'Justificada');
            }
        }
        return $ok;
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
