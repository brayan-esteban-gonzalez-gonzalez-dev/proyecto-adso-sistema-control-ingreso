<?php
/**
 * Usuario.php — Modelo de Usuarios
 * 
 * CRUD completo para la tabla `usuarios` + autenticación.
 */
class Usuario {
    private PDO $conn;
    private string $table = 'usuarios';

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    /**
     * Obtiene todos los usuarios con su rol
     */
    public function getAll(): array {
        $sql = "SELECT u.*, r.nombre AS nombre_rol
                FROM {$this->table} u
                INNER JOIN roles r ON u.id_rol = r.id_rol
                ORDER BY u.creado_en DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Obtiene un usuario por su ID
     */
    public function getById(int $id): ?array {
        $sql = "SELECT u.*, r.nombre AS nombre_rol
                FROM {$this->table} u
                INNER JOIN roles r ON u.id_rol = r.id_rol
                WHERE u.id_usuario = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Obtiene un usuario por número de documento
     */
    public function getByDocumento(string $documento): ?array {
        $sql = "SELECT u.*, r.nombre AS nombre_rol
                FROM {$this->table} u
                INNER JOIN roles r ON u.id_rol = r.id_rol
                WHERE u.num_documento = :doc";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':doc' => $documento]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Obtiene un usuario por correo electrónico
     */
    public function getByCorreo(string $correo): ?array {
        $sql = "SELECT u.*, r.nombre AS nombre_rol
                FROM {$this->table} u
                INNER JOIN roles r ON u.id_rol = r.id_rol
                WHERE u.correo = :correo";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':correo' => $correo]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Obtiene todos los instructores (rol 2) activos
     */
    public function getInstructores(): array {
        $sql = "SELECT * FROM {$this->table} WHERE id_rol IN (1,2) AND estado = 'Activo' ORDER BY nombre";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Autentica un usuario por documento y contraseña
     */
    public function authenticate(string $documento, string $password): ?array {
        $usuario = $this->getByDocumento($documento);
        if ($usuario && $usuario['estado'] === 'Activo' && password_verify($password, $usuario['password'])) {
            return $usuario;
        }
        return null;
    }

    /**
     * Crea un nuevo usuario
     */
    public function create(array $data): bool {
        $sql = "INSERT INTO {$this->table} (id_rol, num_documento, nombre, apellido, correo, password, estado)
                VALUES (:id_rol, :num_documento, :nombre, :apellido, :correo, :password, :estado)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':id_rol'        => $data['id_rol'],
            ':num_documento' => $data['num_documento'],
            ':nombre'        => $data['nombre'],
            ':apellido'      => $data['apellido'],
            ':correo'        => $data['correo'],
            ':password'      => password_hash($data['password'], PASSWORD_BCRYPT),
            ':estado'        => $data['estado'] ?? 'Activo',
        ]);
    }

    /**
     * Retorna el ID del último registro insertado
     */
    public function lastInsertId(): string {
        return $this->conn->lastInsertId();
    }

    /**
     * Actualiza un usuario existente
     */
    public function update(int $id, array $data): bool {
        $fields = [];
        $params = [':id' => $id];

        foreach (['id_rol', 'num_documento', 'nombre', 'apellido', 'correo', 'estado'] as $field) {
            if (isset($data[$field])) {
                $fields[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        // Si se proporcionó una nueva contraseña, actualizarla
        if (!empty($data['password'])) {
            $fields[] = "password = :password";
            $params[':password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id_usuario = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Elimina un usuario por su ID
     */
    public function delete(int $id): bool {
        $sql = "DELETE FROM {$this->table} WHERE id_usuario = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Cuenta el total de usuarios
     */
    public function count(): int {
        $sql = "SELECT COUNT(*) as total FROM {$this->table}";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return (int) $stmt->fetch()['total'];
    }

    /**
     * Cuenta usuarios por rol
     */
    public function countByRol(int $idRol): int {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE id_rol = :id_rol AND estado = 'Activo'";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id_rol' => $idRol]);
        return (int) $stmt->fetch()['total'];
    }
}
