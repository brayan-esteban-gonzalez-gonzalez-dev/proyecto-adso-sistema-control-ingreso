<?php
/**
 * Rol.php — Modelo de Roles
 * 
 * Acceso a la tabla `roles` del sistema.
 */
class Rol {
    private PDO $conn;
    private string $table = 'roles';

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    /**
     * Obtiene todos los roles
     */
    public function getAll(): array {
        $sql = "SELECT * FROM {$this->table} ORDER BY id_rol";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Obtiene un rol por su ID
     */
    public function getById(int $id): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE id_rol = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }
}
