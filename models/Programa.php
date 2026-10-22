<?php
/**
 * Programa.php — Modelo de Programas de Formación
 *
 * Tabla `Programa`: id, codigo, nombre, descripcion.
 */
class Programa {
    private PDO $conn;
    private string $table = 'Programa';

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    public function getAll(): array {
        $sql = "SELECT p.*,
                       (SELECT COUNT(*) FROM Ficha f WHERE f.Programa_id = p.id) AS total_fichas,
                       (SELECT COUNT(*) FROM competencia c WHERE c.Programa_id = p.id) AS total_competencias
                FROM {$this->table} p
                ORDER BY p.nombre";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getById(int $id): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function getByCodigo(string $codigo): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE codigo = :codigo LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':codigo' => $codigo]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function create(array $data): bool {
        $sql = "INSERT INTO {$this->table} (codigo, nombre, descripcion)
                VALUES (:codigo, :nombre, :descripcion)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':codigo'      => $data['codigo'],
            ':nombre'      => $data['nombre'],
            ':descripcion' => $data['descripcion'] ?: null,
        ]);
    }

    public function update(int $id, array $data): bool {
        $sql = "UPDATE {$this->table}
                SET codigo = :codigo,
                    nombre = :nombre,
                    descripcion = :descripcion
                WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':id'          => $id,
            ':codigo'      => $data['codigo'],
            ':nombre'      => $data['nombre'],
            ':descripcion' => $data['descripcion'] ?: null,
        ]);
    }

    public function delete(int $id): bool {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    public function countFichas(int $id): int {
        $sql = "SELECT COUNT(*) AS total FROM Ficha WHERE Programa_id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        return (int) $stmt->fetch()['total'];
    }

    public function countCompetencias(int $id): int {
        $sql = "SELECT COUNT(*) AS total FROM competencia WHERE Programa_id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        return (int) $stmt->fetch()['total'];
    }
    /**
     * Obtiene los programas activos.
     */
    public function obtenerActivos(): array {
        $sql = "SELECT * FROM {$this->table} WHERE estado = 'Activo' ORDER BY nombre";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    }
