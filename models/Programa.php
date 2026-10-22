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

    private function hasCodigo(): bool {
        static $hasCodigo = null;
        if ($hasCodigo === null) {
            try {
                $stmt = $this->conn->query("SHOW COLUMNS FROM {$this->table} LIKE 'codigo'");
                $hasCodigo = ($stmt && $stmt->rowCount() > 0);
            } catch (\Throwable $e) {
                $hasCodigo = false;
            }
        }
        return $hasCodigo;
    }

    public function getByCodigo(string $codigo): ?array {
        if (!$this->hasCodigo()) {
            return null;
        }
        $sql = "SELECT * FROM {$this->table} WHERE codigo = :codigo LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':codigo' => $codigo]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function create(array $data): bool {
        if ($this->hasCodigo()) {
            $sql = "INSERT INTO {$this->table} (codigo, nombre, descripcion)
                    VALUES (:codigo, :nombre, :descripcion)";
            $params = [
                ':codigo'      => $data['codigo'],
                ':nombre'      => $data['nombre'],
                ':descripcion' => $data['descripcion'] ?: null,
            ];
        } else {
            $sql = "INSERT INTO {$this->table} (nombre, descripcion)
                    VALUES (:nombre, :descripcion)";
            $params = [
                ':nombre'      => $data['nombre'],
                ':descripcion' => $data['descripcion'] ?: null,
            ];
        }
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($params);
    }

    public function update(int $id, array $data): bool {
        if ($this->hasCodigo()) {
            $sql = "UPDATE {$this->table}
                    SET codigo = :codigo,
                        nombre = :nombre,
                        descripcion = :descripcion
                    WHERE id = :id";
            $params = [
                ':id'          => $id,
                ':codigo'      => $data['codigo'],
                ':nombre'      => $data['nombre'],
                ':descripcion' => $data['descripcion'] ?: null,
            ];
        } else {
            $sql = "UPDATE {$this->table}
                    SET nombre = :nombre,
                        descripcion = :descripcion
                    WHERE id = :id";
            $params = [
                ':id'          => $id,
                ':nombre'      => $data['nombre'],
                ':descripcion' => $data['descripcion'] ?: null,
            ];
        }
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($params);
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
        static $hasEstado = null;
        if ($hasEstado === null) {
            try {
                $stmt = $this->conn->query("SHOW COLUMNS FROM {$this->table} LIKE 'estado'");
                $hasEstado = ($stmt && $stmt->rowCount() > 0);
            } catch (\Throwable $e) {
                $hasEstado = false;
            }
        }
        $where = $hasEstado ? "WHERE estado = 'Activo'" : "";
        $sql = "SELECT * FROM {$this->table} {$where} ORDER BY nombre";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    }
