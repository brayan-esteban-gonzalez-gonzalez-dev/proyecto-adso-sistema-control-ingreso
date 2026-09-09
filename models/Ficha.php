<?php
/**
 * Ficha.php — Modelo de Fichas
 * 
 * CRUD completo para la tabla `fichas`.
 */
class Ficha {
    private PDO $conn;
    private string $table = 'fichas';

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    /**
     * Obtiene todas las fichas con su instructor líder
     */
    public function getAll(): array {
        $sql = "SELECT f.*, 
                       CONCAT(u.nombre, ' ', u.apellido) AS nombre_instructor,
                       (SELECT COUNT(*) FROM aprendices a WHERE a.id_ficha = f.id_ficha) AS total_aprendices
                FROM {$this->table} f
                LEFT JOIN usuarios u ON f.id_instructor_lider = u.id_usuario
                ORDER BY f.codigo_ficha";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Obtiene una ficha por su ID
     */
    public function getById(int $id): ?array {
        $sql = "SELECT f.*, 
                       CONCAT(u.nombre, ' ', u.apellido) AS nombre_instructor
                FROM {$this->table} f
                LEFT JOIN usuarios u ON f.id_instructor_lider = u.id_usuario
                WHERE f.id_ficha = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Obtiene fichas por instructor
     */
    public function getByInstructor(int $idInstructor): array {
        $sql = "SELECT * FROM {$this->table} WHERE id_instructor_lider = :id ORDER BY codigo_ficha";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $idInstructor]);
        return $stmt->fetchAll();
    }

    /**
     * Crea una nueva ficha
     */
    public function create(array $data): bool {
        $sql = "INSERT INTO {$this->table} (codigo_ficha, nombre_programa, id_instructor_lider)
                VALUES (:codigo_ficha, :nombre_programa, :id_instructor_lider)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':codigo_ficha'        => $data['codigo_ficha'],
            ':nombre_programa'     => $data['nombre_programa'],
            ':id_instructor_lider' => $data['id_instructor_lider'] ?: null,
        ]);
    }

    /**
     * Actualiza una ficha existente
     */
    public function update(int $id, array $data): bool {
        $sql = "UPDATE {$this->table}
                SET codigo_ficha = :codigo_ficha,
                    nombre_programa = :nombre_programa,
                    id_instructor_lider = :id_instructor_lider
                WHERE id_ficha = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':id'                  => $id,
            ':codigo_ficha'        => $data['codigo_ficha'],
            ':nombre_programa'     => $data['nombre_programa'],
            ':id_instructor_lider' => $data['id_instructor_lider'] ?: null,
        ]);
    }

    /**
     * Elimina una ficha por su ID
     */
    public function delete(int $id): bool {
        $sql = "DELETE FROM {$this->table} WHERE id_ficha = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Cuenta el total de fichas
     */
    public function count(): int {
        $sql = "SELECT COUNT(*) as total FROM {$this->table}";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return (int) $stmt->fetch()['total'];
    }
}
