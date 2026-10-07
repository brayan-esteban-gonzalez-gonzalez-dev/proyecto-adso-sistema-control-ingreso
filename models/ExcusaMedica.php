<?php
/**
 * ExcusaMedica.php — Modelo de Excusas
 *
 * CRUD para las tablas `excusa` y `estado_excusa` de la nueva DB.
 * Nueva DB:
 *   excusa: id, fecha, motivo, evidencia, Usuario_id, Asistencia_id, Inasistencia_id
 *   estado_excusa: id, fecha, respuesta, estado (Aprobada|Rechazada), Excusa_id, Instructor_id
 */
class ExcusaMedica {
    private PDO $conn;
    private string $table      = 'excusa';
    private string $tableEstado = 'estado_excusa';

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    /**
     * Obtiene todas las excusas con datos del usuario y estado de revisión
     */
    public function getAll(): array {
        $sql = "SELECT e.*,
                       u.nombre, u.apellido, u.identificacion,
                       f.codigo         AS codigo_ficha,
                       p.nombre         AS nombre_programa,
                       ee.estado        AS estado_revision,
                       ee.respuesta     AS comentario_revision,
                       CONCAT(rev.nombre, ' ', rev.apellido) AS nombre_revisor
                FROM {$this->table} e
                INNER JOIN Usuario u   ON e.Usuario_id     = u.id
                LEFT JOIN Ficha   f    ON u.Ficha_id        = f.id
                LEFT JOIN Programa p   ON f.Programa_id     = p.id
                LEFT JOIN {$this->tableEstado} ee ON ee.Excusa_id = e.id
                LEFT JOIN Usuario rev  ON ee.Instructor_id  = rev.id
                ORDER BY e.fecha DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Obtiene una excusa por su ID con datos completos
     */
    public function getById(int $id): ?array {
        $sql = "SELECT e.*,
                    u.nombre, u.apellido, u.identificacion,
                    f.codigo         AS codigo_ficha,
                    p.nombre         AS nombre_programa,
                    ee.estado        AS estado_revision,
                    ee.respuesta     AS comentario_revision,
                    CONCAT(rev.nombre, ' ', rev.apellido) AS nombre_revisor
                FROM {$this->table} e
                INNER JOIN Usuario u   ON e.Usuario_id     = u.id
                LEFT JOIN Ficha   f    ON u.Ficha_id        = f.id
                LEFT JOIN Programa p   ON f.Programa_id     = p.id
                LEFT JOIN {$this->tableEstado} ee ON ee.Excusa_id = e.id
                LEFT JOIN Usuario rev  ON ee.Instructor_id  = rev.id
                WHERE e.id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Obtiene excusas de un usuario (aprendiz) específico
     */
    public function getByAprendiz(int $idUsuario): array {
        $sql = "SELECT e.*,
                    ee.estado    AS estado_revision,
                    ee.respuesta AS comentario_revision,
                    CONCAT(rev.nombre, ' ', rev.apellido) AS nombre_revisor
                FROM {$this->table} e
                LEFT JOIN {$this->tableEstado} ee ON ee.Excusa_id = e.id
                LEFT JOIN Usuario rev ON ee.Instructor_id = rev.id
                WHERE e.Usuario_id = :id
                ORDER BY e.fecha DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $idUsuario]);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene excusas pendientes de revisión (sin estado_excusa asociado)
     */
    public function getPendientes(): array {
        $sql = "SELECT e.*,
                       u.nombre, u.apellido, u.identificacion,
                       f.codigo AS codigo_ficha,
                       p.nombre AS nombre_programa
                FROM {$this->table} e
                INNER JOIN Usuario u ON e.Usuario_id  = u.id
                LEFT JOIN Ficha   f  ON u.Ficha_id     = f.id
                LEFT JOIN Programa p ON f.Programa_id  = p.id
                WHERE NOT EXISTS (
                    SELECT 1 FROM {$this->tableEstado} ee WHERE ee.Excusa_id = e.id
                )
                ORDER BY e.fecha ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Crea una nueva excusa
     * $data debe tener: usuario_id, fecha, motivo, evidencia (ruta archivo),
     *                   asistencia_id o inasistencia_id (uno de los dos, no ambos)
     */
    public function create(array $data): bool {
        $sql = "INSERT INTO {$this->table}
                    (fecha, motivo, evidencia, Usuario_id, Asistencia_id, Inasistencia_id)
                VALUES
                    (:fecha, :motivo, :evidencia, :Usuario_id, :Asistencia_id, :Inasistencia_id)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':fecha'           => $data['fecha']           ?? date('Y-m-d'),
            ':motivo'          => $data['motivo'],
            ':evidencia'       => $data['evidencia']       ?? $data['archivo_adjunto'] ?? null,
            ':Usuario_id'      => $data['usuario_id']      ?? $data['id_aprendiz']     ?? null,
            ':Asistencia_id'   => $data['Asistencia_id']   ?? $data['id_ingreso']      ?? null,
            ':Inasistencia_id' => $data['Inasistencia_id'] ?? null,
        ]);
    }

    /**
     * Aprueba una excusa: inserta registro en estado_excusa con estado='Aprobada'
     */
    public function aprobar(int $idExcusa, int $idInstructor, string $comentario = ''): bool {
        return $this->registrarEstado($idExcusa, $idInstructor, 'Aprobada', $comentario);
    }

    /**
     * Rechaza una excusa: inserta registro en estado_excusa con estado='Rechazada'
     */
    public function rechazar(int $idExcusa, int $idInstructor, string $comentario = ''): bool {
        return $this->registrarEstado($idExcusa, $idInstructor, 'Rechazada', $comentario);
    }

    /**
     * Inserta o actualiza el registro de estado_excusa
     */
    private function registrarEstado(int $idExcusa, int $idInstructor, string $estado, string $respuesta): bool {
        // Si ya existe, actualizar; si no, insertar
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
            ':estado'      => $estado,
            ':respuesta'   => $respuesta,
            ':excusa_id'   => $idExcusa,
            ':instructor'  => $idInstructor,
        ]);
    }

    /**
     * Cuenta excusas pendientes (sin estado_excusa)
     */
    public function countPendientes(): int {
        $sql = "SELECT COUNT(*) as total
                FROM {$this->table} e
                WHERE NOT EXISTS (
                    SELECT 1 FROM {$this->tableEstado} ee WHERE ee.Excusa_id = e.id
                )";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return (int) $stmt->fetch()['total'];
    }
}
