<?php
/**
 * AsignacionInstructor.php — Modelo de Asignaciones de Instructor
 *
 * Horario semestral del instructor (6 meses). Tabla `asignacion_instructor`:
 * id, instructor_id, ficha_id, fecha, hora_inicio, hora_fin, estado.
 *
 * No crea sesiones: solo sirve como marco de validación del horario
 * de la ficha (RF-HOR-36 a RF-HOR-38).
 */
class AsignacionInstructor {
    private PDO $conn;
    private string $table = 'asignacion_instructor';

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    /**
     * Verifica si un instructor tiene asignada una ficha en una fecha y bloque.
     * Un bloque se considera cubierto si la asignación contiene el rango
     * [hora_inicio, hora_fin] del bloque consultado.
     */
    public function existeAsignacion(int $instructorId, int $fichaId, string $fecha, string $horaInicio, string $horaFin): ?array {
        $sql = "SELECT *
                FROM {$this->table}
                WHERE instructor_id = :instructor_id
                  AND ficha_id = :ficha_id
                  AND fecha = :fecha
                  AND estado = 'Activo'
                  AND hora_inicio <= :hora_inicio
                  AND hora_fin >= :hora_fin
                LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':instructor_id' => $instructorId,
            ':ficha_id'      => $fichaId,
            ':fecha'         => $fecha,
            ':hora_inicio'   => $horaInicio,
            ':hora_fin'      => $horaFin,
        ]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Lista las asignaciones de un instructor en un rango de fechas.
     */
    public function getByInstructor(int $instructorId, string $desde, string $hasta): array {
        $sql = "SELECT a.*, f.codigo AS codigo_ficha
                FROM {$this->table} a
                INNER JOIN Ficha f ON a.ficha_id = f.id
                WHERE a.instructor_id = :instructor_id
                  AND a.fecha BETWEEN :desde AND :hasta
                ORDER BY a.fecha, a.hora_inicio";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':instructor_id' => $instructorId,
            ':desde'         => $desde,
            ':hasta'         => $hasta,
        ]);
        return $stmt->fetchAll();
    }

    /**
     * Lista las asignaciones de una ficha en un rango de fechas.
     */
    public function getByFicha(int $fichaId, string $desde, string $hasta): array {
        $sql = "SELECT a.*, CONCAT(u.nombre, ' ', u.apellido) AS nombre_instructor
                FROM {$this->table} a
                INNER JOIN Usuario u ON a.instructor_id = u.id
                WHERE a.ficha_id = :ficha_id
                  AND a.fecha BETWEEN :desde AND :hasta
                ORDER BY a.fecha, a.hora_inicio";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':ficha_id' => $fichaId,
            ':desde'    => $desde,
            ':hasta'    => $hasta,
        ]);
        return $stmt->fetchAll();
    }

    public function create(array $data): bool {
        $sql = "INSERT INTO {$this->table}
                    (instructor_id, ficha_id, fecha, hora_inicio, hora_fin, estado)
                VALUES
                    (:instructor_id, :ficha_id, :fecha, :hora_inicio, :hora_fin, :estado)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':instructor_id' => $data['instructor_id'],
            ':ficha_id'      => $data['ficha_id'],
            ':fecha'         => $data['fecha'],
            ':hora_inicio'   => $data['hora_inicio'],
            ':hora_fin'      => $data['hora_fin'],
            ':estado'        => $data['estado'] ?? 'Activo',
        ]);
    }

    public function update(int $id, array $data): bool {
        $sql = "UPDATE {$this->table}
                SET instructor_id = :instructor_id,
                    ficha_id      = :ficha_id,
                    fecha         = :fecha,
                    hora_inicio   = :hora_inicio,
                    hora_fin      = :hora_fin,
                    estado        = :estado
                WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':id'            => $id,
            ':instructor_id' => $data['instructor_id'],
            ':ficha_id'      => $data['ficha_id'],
            ':fecha'         => $data['fecha'],
            ':hora_inicio'   => $data['hora_inicio'],
            ':hora_fin'      => $data['hora_fin'],
            ':estado'        => $data['estado'] ?? 'Activo',
        ]);
    }

    public function cancelar(int $id): bool {
        $sql = "UPDATE {$this->table} SET estado = 'Cancelado' WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
}
