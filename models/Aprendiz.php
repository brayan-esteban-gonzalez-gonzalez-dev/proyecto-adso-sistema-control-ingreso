<?php
/**
 * Aprendiz.php — Modelo de Aprendices
 *
 * En la nueva DB no existe tabla `aprendices` separada.
 * Los aprendices son Usuarios con Rol_id = 3.
 * Este modelo actúa como wrapper sobre la tabla `Usuario` filtrando por Rol_id = 3,
 * manteniendo la interfaz que esperan los controladores existentes.
 *
 * Campos relevantes de Usuario para aprendices:
 *   id, nombre, apellido, identificacion, email, password,
 *   Rol_id (=3), Ficha_id, codigo_llavero, estado.
 */
class Aprendiz {
    private PDO $conn;
    private string $table = 'Usuario';
    private int $rolAprendiz = 3;

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    /**
     * Obtiene todos los aprendices con datos de ficha y programa
     */
    public function getAll(): array {
        $sql = "SELECT u.*,
                       u.identificacion AS num_documento,
                       u.email          AS correo,
                       f.codigo         AS codigo_ficha,
                       p.nombre         AS nombre_programa
                FROM {$this->table} u
                LEFT JOIN Ficha f   ON u.Ficha_id    = f.id
                LEFT JOIN Programa p ON f.Programa_id = p.id
                WHERE u.Rol_id = :rol
                ORDER BY u.apellido, u.nombre";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':rol' => $this->rolAprendiz]);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene un aprendiz por su ID de usuario
     * Mantiene alias id_aprendiz = id para compatibilidad con controladores.
     */
    public function getById(int $id): ?array {
        $sql = "SELECT u.*,
                       u.id             AS id_aprendiz,
                       u.identificacion AS num_documento,
                       u.email          AS correo,
                       f.codigo         AS codigo_ficha,
                       p.nombre         AS nombre_programa
                FROM {$this->table} u
                LEFT JOIN Ficha f   ON u.Ficha_id    = f.id
                LEFT JOIN Programa p ON f.Programa_id = p.id
                WHERE u.id = :id AND u.Rol_id = :rol";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id, ':rol' => $this->rolAprendiz]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Obtiene un aprendiz por ID de usuario (mismo resultado que getById)
     */
    public function getByUsuario(int $idUsuario): ?array {
        return $this->getById($idUsuario);
    }

    /**
     * Obtiene un aprendiz por código de llavero (RFID)
     */
    public function getByRfid(string $codigoLlavero): ?array {
        $sql = "SELECT u.*,
                       u.id             AS id_aprendiz,
                       u.identificacion AS num_documento,
                       u.email          AS correo,
                       f.codigo         AS codigo_ficha,
                       p.nombre         AS nombre_programa,
                       f.id             AS id_ficha
                FROM {$this->table} u
                LEFT JOIN Ficha f   ON u.Ficha_id    = f.id
                LEFT JOIN Programa p ON f.Programa_id = p.id
                WHERE u.codigo_llavero = :llavero AND u.estado = 'Activo' AND u.Rol_id = :rol";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':llavero' => $codigoLlavero, ':rol' => $this->rolAprendiz]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Obtiene aprendices por ficha
     */
    public function getByFicha(int $fichaId): array {
        $sql = "SELECT u.*,
                       u.identificacion AS num_documento,
                       u.email          AS correo
                FROM {$this->table} u
                WHERE u.Ficha_id = :ficha_id AND u.Rol_id = :rol
                ORDER BY u.apellido, u.nombre";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':ficha_id' => $fichaId, ':rol' => $this->rolAprendiz]);
        return $stmt->fetchAll();
    }

    /**
     * Crea un nuevo aprendiz (inserta en tabla Usuario con Rol_id=3)
     */
    public function create(array $data): bool {
        $sql = "INSERT INTO {$this->table} (Rol_id, identificacion, nombre, apellido, email, password, Ficha_id, codigo_llavero, estado)
                VALUES (:Rol_id, :identificacion, :nombre, :apellido, :email, :password, :Ficha_id, :codigo_llavero, :estado)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':Rol_id'         => $this->rolAprendiz,
            ':identificacion' => $data['identificacion'] ?? $data['num_documento'] ?? null,
            ':nombre'         => $data['nombre'],
            ':apellido'       => $data['apellido'],
            ':email'          => $data['email'] ?? $data['correo'] ?? null,
            ':password'       => password_hash($data['password'], PASSWORD_BCRYPT),
            ':Ficha_id'       => $data['Ficha_id'] ?? $data['id_ficha'] ?? null,
            ':codigo_llavero' => $data['codigo_llavero'] ?? $data['codigo_rfid'] ?? null,
            ':estado'         => $data['estado'] ?? 'Activo',
        ]);
    }

    /**
     * Actualiza un aprendiz (ficha y código llavero)
     */
    public function update(int $id, array $data): bool {
        $sql = "UPDATE {$this->table}
                SET Ficha_id       = :Ficha_id,
                    codigo_llavero = :codigo_llavero
                WHERE id = :id AND Rol_id = :rol";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':id'            => $id,
            ':Ficha_id'      => $data['Ficha_id'] ?? $data['id_ficha'] ?? null,
            ':codigo_llavero'=> $data['codigo_llavero'] ?? $data['codigo_rfid'] ?? null,
            ':rol'           => $this->rolAprendiz,
        ]);
    }

    /**
     * Elimina un aprendiz (elimina el Usuario)
     */
    public function delete(int $id): bool {
        $sql = "DELETE FROM {$this->table} WHERE id = :id AND Rol_id = :rol";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id, ':rol' => $this->rolAprendiz]);
    }

    /**
     * Cuenta el total de aprendices activos
     */
    public function count(): int {
        $sql = "SELECT COUNT(*) as total FROM {$this->table}
                WHERE Rol_id = :rol AND estado = 'Activo'";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':rol' => $this->rolAprendiz]);
        return (int) $stmt->fetch()['total'];
    }

    /**
     * Retorna el ID del último insert (para compatibilidad con controladores)
     */
    public function lastInsertId(): string {
        return $this->conn->lastInsertId();
    }
}
