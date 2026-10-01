<?php
/**
 * Ficha.php — Modelo de Fichas
 *
 * CRUD completo para la tabla `Ficha`.
 * Nueva DB: tabla `Ficha`, PK `id`, campos: codigo (INT), Programa_id, jornada_id, instructor_id, estado.
 * Relacionado con tabla `Programa` (id, nombre, descripcion) y `jornada`.
 */
class Ficha {
    private PDO $conn;
    private string $table = 'Ficha';

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    /**
     * Obtiene todas las fichas con programa, jornada e instructor
     */
    public function getAll(): array {
        $sql = "SELECT f.*,
                       p.nombre AS nombre_programa,
                       p.descripcion AS descripcion_programa,
                       j.nombre AS nombre_jornada,
                       CONCAT(u.nombre, ' ', u.apellido) AS nombre_instructor,
                       (SELECT COUNT(*) FROM Usuario a WHERE a.Ficha_id = f.id AND a.Rol_id = 3) AS total_aprendices
                FROM {$this->table} f
                INNER JOIN Programa p ON f.Programa_id = p.id
                LEFT JOIN jornada j ON f.jornada_id = j.id
                LEFT JOIN Usuario u ON f.instructor_id = u.id
                ORDER BY f.codigo";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Obtiene una ficha por su ID
     */
    public function getById(int $id): ?array {
        $sql = "SELECT f.*,
                       p.nombre AS nombre_programa,
                       p.descripcion AS descripcion_programa,
                       j.nombre AS nombre_jornada,
                       CONCAT(u.nombre, ' ', u.apellido) AS nombre_instructor
                FROM {$this->table} f
                INNER JOIN Programa p ON f.Programa_id = p.id
                LEFT JOIN jornada j ON f.jornada_id = j.id
                LEFT JOIN Usuario u ON f.instructor_id = u.id
                WHERE f.id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Obtiene fichas por instructor
     */
    public function getByInstructor(int $instructorId): array {
        $sql = "SELECT f.*, p.nombre AS nombre_programa
                FROM {$this->table} f
                INNER JOIN Programa p ON f.Programa_id = p.id
                WHERE f.instructor_id = :instructor_id
                ORDER BY f.codigo";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':instructor_id' => $instructorId]);
        return $stmt->fetchAll();
    }

    /**
     * Crea una nueva ficha
     */
    public function create(array $data): bool {
        $sql = "INSERT INTO {$this->table} (codigo, Programa_id, jornada_id, instructor_id, estado)
                VALUES (:codigo, :Programa_id, :jornada_id, :instructor_id, :estado)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':codigo'        => $data['codigo'],
            ':Programa_id'   => $data['Programa_id'],
            ':jornada_id'    => $data['jornada_id'] ?? null,
            ':instructor_id' => $data['instructor_id'] ?: null,
            ':estado'        => $data['estado'] ?? 'Activo',
        ]);
    }

    /**
     * Actualiza una ficha existente
     */
    public function update(int $id, array $data): bool {
        $sql = "UPDATE {$this->table}
                SET codigo        = :codigo,
                    Programa_id   = :Programa_id,
                    jornada_id    = :jornada_id,
                    instructor_id = :instructor_id,
                    estado        = :estado
                WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':id'            => $id,
            ':codigo'        => $data['codigo'],
            ':Programa_id'   => $data['Programa_id'],
            ':jornada_id'    => $data['jornada_id'] ?? null,
            ':instructor_id' => $data['instructor_id'] ?: null,
            ':estado'        => $data['estado'] ?? 'Activo',
        ]);
    }

    /**
     * Elimina una ficha por su ID
     */
    public function delete(int $id): bool {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Cuenta el total de fichas activas
     */
    public function count(): int {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE estado = 'Activo'";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return (int) $stmt->fetch()['total'];
    }

    /**
     * Obtiene todos los programas disponibles
     */
    public function getProgramas(): array {
        $sql = "SELECT * FROM Programa ORDER BY nombre";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Obtiene todas las jornadas disponibles
     */
    public function getJornadas(): array {
        $sql = "SELECT * FROM jornada ORDER BY hora_inicio";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
