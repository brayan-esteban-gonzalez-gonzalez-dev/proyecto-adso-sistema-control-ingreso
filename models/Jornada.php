<?php
/**
 * Jornada.php — Modelo de Jornadas
 *
 * CRUD de franjas horarias maestras (Manana, Tarde, Noche).
 * Tabla `jornada`: id, nombre, hora_inicio, hora_fin.
 */
class Jornada {
    private PDO $conn;
    private string $table = 'jornada';

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    public function getAll(): array {
        $sql = "SELECT j.*,
                       (SELECT COUNT(*) FROM Ficha f WHERE f.jornada_id = j.id) AS total_fichas
                FROM {$this->table} j
                ORDER BY j.hora_inicio, j.nombre";
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

    public function getByNombre(string $nombre): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE nombre = :nombre LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':nombre' => $nombre]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function create(array $data): bool {
        $sql = "INSERT INTO {$this->table} (nombre, hora_inicio, hora_fin)
                VALUES (:nombre, :hora_inicio, :hora_fin)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':nombre'      => $data['nombre'],
            ':hora_inicio' => $data['hora_inicio'],
            ':hora_fin'    => $data['hora_fin'],
        ]);
    }

    public function update(int $id, array $data): bool {
        $sql = "UPDATE {$this->table}
                SET nombre = :nombre,
                    hora_inicio = :hora_inicio,
                    hora_fin = :hora_fin
                WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':id'          => $id,
            ':nombre'      => $data['nombre'],
            ':hora_inicio' => $data['hora_inicio'],
            ':hora_fin'    => $data['hora_fin'],
        ]);
    }

    public function delete(int $id): bool {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    public function countFichas(int $id): int {
        $sql = "SELECT COUNT(*) AS total FROM Ficha WHERE jornada_id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        return (int) $stmt->fetch()['total'];
    }
}
