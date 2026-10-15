<?php
/**
 * Competencia.php — Modelo de Competencias
 *
 * Tabla `competencia`: Programa_id, Instructor_id, nombre, descripcion.
 * Instructores adicionales en `instructor_competencia`.
 */
class Competencia {
    private PDO $conn;
    private string $table = 'competencia';

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    public function getAll(?int $programaId = null): array {
        $sql = "SELECT c.*,
                       p.nombre AS nombre_programa,
                       p.codigo AS codigo_programa,
                       (SELECT COUNT(*) FROM sesion s WHERE s.Competencia_id = c.id) AS total_sesiones
                FROM {$this->table} c
                INNER JOIN Programa p ON c.Programa_id = p.id";
        $params = [];
        if ($programaId) {
            $sql .= " WHERE c.Programa_id = :programa_id";
            $params[':programa_id'] = $programaId;
        }
        $sql .= " ORDER BY p.nombre, c.nombre";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        foreach ($rows as &$row) {
            $row['instructores'] = $this->getInstructores((int) $row['id']);
        }
        unset($row);
        return $rows;
    }

    public function getById(int $id): ?array {
        $sql = "SELECT c.*, p.nombre AS nombre_programa, p.codigo AS codigo_programa
                FROM {$this->table} c
                INNER JOIN Programa p ON c.Programa_id = p.id
                WHERE c.id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        if (!$result) {
            return null;
        }
        $result['instructores'] = $this->getInstructores($id);
        return $result;
    }

    public function getInstructores(int $competenciaId): array {
        $sql = "SELECT u.id, u.nombre, u.apellido
                FROM instructor_competencia ic
                INNER JOIN Usuario u ON u.id = ic.Instructor_id
                WHERE ic.Competencia_id = :id
                ORDER BY u.nombre, u.apellido";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $competenciaId]);
        return $stmt->fetchAll();
    }

    public function existeNombre(int $programaId, string $nombre, int $excludeId = 0): bool {
        $sql = "SELECT id FROM {$this->table}
                WHERE Programa_id = :programa_id AND nombre = :nombre AND id <> :id
                LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':programa_id' => $programaId,
            ':nombre'      => $nombre,
            ':id'          => $excludeId,
        ]);
        return (bool) $stmt->fetch();
    }

    public function create(array $data): int {
        $sql = "INSERT INTO {$this->table} (Programa_id, Instructor_id, nombre, descripcion)
                VALUES (:Programa_id, :Instructor_id, :nombre, :descripcion)";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':Programa_id'   => $data['Programa_id'],
            ':Instructor_id' => $data['Instructor_id'] ?: null,
            ':nombre'        => $data['nombre'],
            ':descripcion'   => $data['descripcion'] ?: null,
        ]);
        return (int) $this->conn->lastInsertId();
    }

    public function update(int $id, array $data): bool {
        $sql = "UPDATE {$this->table}
                SET Programa_id = :Programa_id,
                    Instructor_id = :Instructor_id,
                    nombre = :nombre,
                    descripcion = :descripcion
                WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':id'            => $id,
            ':Programa_id'   => $data['Programa_id'],
            ':Instructor_id' => $data['Instructor_id'] ?: null,
            ':nombre'        => $data['nombre'],
            ':descripcion'   => $data['descripcion'] ?: null,
        ]);
    }

    public function syncInstructores(int $competenciaId, array $instructorIds): void {
        $ids = array_values(array_unique(array_filter(array_map('intval', $instructorIds), fn(int $id) => $id > 0)));
        $del = $this->conn->prepare("DELETE FROM instructor_competencia WHERE Competencia_id = :id");
        $del->execute([':id' => $competenciaId]);

        if ($ids) {
            $ins = $this->conn->prepare(
                "INSERT INTO instructor_competencia (Instructor_id, Competencia_id) VALUES (:instructor, :competencia)"
            );
            foreach ($ids as $instructorId) {
                $ins->execute([
                    ':instructor'  => $instructorId,
                    ':competencia' => $competenciaId,
                ]);
            }
        }

        $upd = $this->conn->prepare("UPDATE {$this->table} SET Instructor_id = :instructor WHERE id = :id");
        $upd->execute([
            ':instructor' => $ids[0] ?? null,
            ':id'         => $competenciaId,
        ]);
    }

    public function delete(int $id): bool {
        $del = $this->conn->prepare("DELETE FROM instructor_competencia WHERE Competencia_id = :id");
        $del->execute([':id' => $id]);
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    public function countSesiones(int $id): int {
        $sql = "SELECT COUNT(*) AS total FROM sesion WHERE Competencia_id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        return (int) $stmt->fetch()['total'];
    }
}
