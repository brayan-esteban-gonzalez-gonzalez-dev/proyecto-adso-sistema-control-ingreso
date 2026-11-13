<?php
/**
 * Rol.php — Modelo de Roles
 *
 * Acceso a la tabla `rol` del sistema.
 * Nueva DB: tabla `rol`, PK `id`.
 */
class Rol {
    private PDO $conn;
    private string $table = 'rol';

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    /**
     * Obtiene todos los roles
     */
    public function getAll(): array {
        $sql = "SELECT * FROM {$this->table} ORDER BY id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Obtiene un rol por su ID
     */
    public function getById(int $id): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
}