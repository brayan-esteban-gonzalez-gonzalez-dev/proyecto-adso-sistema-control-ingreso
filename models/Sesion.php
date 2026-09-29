<?php
/**
 * Sesion.php — Modelo de Sesiones de Clase (Horarios)
 *
 * Maneja las sesiones de clase programadas para las fichas,
 * incluyendo instructores, horarios y metadatos de la sesión.
 */
class Sesion {
    private PDO $conn;
    private string $table = 'sesion';

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    /**
     * Obtiene todas las sesiones con información de ficha e instructor
     */
    public function getAll(): array {
        $sql = "SELECT s.*, 
                       f.codigo AS codigo_ficha,
                       CONCAT(u.nombre, ' ', u.apellido) AS nombre_instructor,
                       c.nombre AS nombre_competencia
                FROM {$this->table} s
                INNER JOIN Ficha f ON s.Ficha_id = f.id
                INNER JOIN Usuario u ON s.Instructor_id = u.id
                LEFT JOIN competencia c ON s.Competencia_id = c.id
                ORDER BY s.fecha DESC, s.hora_inicio ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Obtiene las sesiones de una ficha específica
     */
    public function getByFicha(int $fichaId): array {
        $sql = "SELECT s.*, 
                       f.codigo AS codigo_ficha,
                       CONCAT(u.nombre, ' ', u.apellido) AS nombre_instructor,
                       c.nombre AS nombre_competencia
                FROM {$this->table} s
                INNER JOIN Ficha f ON s.Ficha_id = f.id
                INNER JOIN Usuario u ON s.Instructor_id = u.id
                LEFT JOIN competencia c ON s.Competencia_id = c.id
                WHERE s.Ficha_id = :ficha_id
                ORDER BY s.fecha ASC, s.hora_inicio ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':ficha_id' => $fichaId]);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene una sesión por su ID
     */
    public function getById(int $id): ?array {
        $sql = "SELECT s.*, 
                       f.codigo AS codigo_ficha,
                       CONCAT(u.nombre, ' ', u.apellido) AS nombre_instructor,
                       c.nombre AS nombre_competencia
                FROM {$this->table} s
                INNER JOIN Ficha f ON s.Ficha_id = f.id
                INNER JOIN Usuario u ON s.Instructor_id = u.id
                LEFT JOIN competencia c ON s.Competencia_id = c.id
                WHERE s.id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Verifica si ya existe una sesión en la misma fecha y hora para la misma ficha
     */
    public function existeSesion(int $fichaId, string $fecha, string $horaInicio): bool {
        $sql = "SELECT COUNT(*) as total FROM {$this->table}
                WHERE Ficha_id = :ficha_id AND fecha = :fecha AND hora_inicio = :hora_inicio";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':ficha_id'    => $fichaId,
            ':fecha'       => $fecha,
            ':hora_inicio' => $horaInicio,
        ]);
        return ((int) $stmt->fetch()['total']) > 0;
    }

    /**
     * Crea una nueva sesión
     */
    public function create(array $data): bool {
        $sql = "INSERT INTO {$this->table} 
                (Ficha_id, Competencia_id, Instructor_id, fecha, hora_inicio, hora_fin, estado, tipo_instructor, especialidad, nivel, grupo_convergente)
                VALUES 
                (:Ficha_id, :Competencia_id, :Instructor_id, :fecha, :hora_inicio, :hora_fin, :estado, :tipo_instructor, :especialidad, :nivel, :grupo_convergente)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':Ficha_id'          => $data['Ficha_id'],
            ':Competencia_id'    => $data['Competencia_id'] ?? null,
            ':Instructor_id'     => $data['Instructor_id'],
            ':fecha'             => $data['fecha'],
            ':hora_inicio'       => $data['hora_inicio'],
            ':hora_fin'          => $data['hora_fin'],
            ':estado'            => $data['estado'] ?? 'Activo',
            ':tipo_instructor'   => $data['tipo_instructor'] ?? null,
            ':especialidad'      => $data['especialidad'] ?? null,
            ':nivel'             => $data['nivel'] ?? null,
            ':grupo_convergente' => $data['grupo_convergente'] ?? null,
        ]);
    }

    /**
     * Actualiza una sesión
     */
    public function update(int $id, array $data): bool {
        $fields = [];
        $params = [':id' => $id];

        $allowed = ['Ficha_id', 'Competencia_id', 'Instructor_id', 'fecha', 'hora_inicio', 'hora_fin', 'estado', 'tipo_instructor', 'especialidad', 'nivel', 'grupo_convergente'];
        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Elimina una sesión
     */
    public function delete(int $id): bool {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Cuenta total de sesiones
     */
    public function count(): int {
        $sql = "SELECT COUNT(*) as total FROM {$this->table}";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return (int) $stmt->fetch()['total'];
    }
}
