<?php

class Aprendiz {
    private PDO $conn;
    private string $table = 'aprendices';

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    /**
     * Obtiene todos los aprendices con datos de usuario y ficha
     */
    public function getAll(): array {
        $sql = "SELECT a.*, 
                       u.num_documento, u.nombre, u.apellido, u.correo, u.estado,
                       f.codigo_ficha, f.nombre_programa
                FROM {$this->table} a
                INNER JOIN usuarios u ON a.id_usuario = u.id_usuario
                INNER JOIN fichas f ON a.id_ficha = f.id_ficha
                ORDER BY u.apellido, u.nombre";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Obtiene un aprendiz por su ID
     */
    public function getById(int $id): ?array {
        $sql = "SELECT a.*, 
                       u.num_documento, u.nombre, u.apellido, u.correo, u.estado, u.id_rol,
                       f.codigo_ficha, f.nombre_programa
                FROM {$this->table} a
                INNER JOIN usuarios u ON a.id_usuario = u.id_usuario
                INNER JOIN fichas f ON a.id_ficha = f.id_ficha
                WHERE a.id_aprendiz = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Obtiene un aprendiz por ID de usuario
     */
    public function getByUsuario(int $idUsuario): ?array {
        $sql = "SELECT a.*, 
                       u.num_documento, u.nombre, u.apellido, u.correo, u.estado,
                       f.codigo_ficha, f.nombre_programa
                FROM {$this->table} a
                INNER JOIN usuarios u ON a.id_usuario = u.id_usuario
                INNER JOIN fichas f ON a.id_ficha = f.id_ficha
                WHERE a.id_usuario = :id_usuario";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id_usuario' => $idUsuario]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Obtiene un aprendiz por código RFID
     */
    public function getByRfid(string $codigoRfid): ?array {
        $sql = "SELECT a.*, 
                       u.num_documento, u.nombre, u.apellido, u.correo, u.estado,
                       f.codigo_ficha, f.nombre_programa, f.id_ficha
                FROM {$this->table} a
                INNER JOIN usuarios u ON a.id_usuario = u.id_usuario
                INNER JOIN fichas f ON a.id_ficha = f.id_ficha
                WHERE a.codigo_rfid = :rfid AND u.estado = 'Activo'";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':rfid' => $codigoRfid]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Obtiene aprendices por ficha
     */
    public function getByFicha(int $idFicha): array {
        $sql = "SELECT a.*, 
                       u.num_documento, u.nombre, u.apellido, u.correo, u.estado
                FROM {$this->table} a
                INNER JOIN usuarios u ON a.id_usuario = u.id_usuario
                WHERE a.id_ficha = :id_ficha
                ORDER BY u.apellido, u.nombre";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id_ficha' => $idFicha]);
        return $stmt->fetchAll();
    }

    /**
     * Crea un nuevo aprendiz
     */
    public function create(array $data): bool {
        $sql = "INSERT INTO {$this->table} (id_usuario, id_ficha, codigo_rfid)
                VALUES (:id_usuario, :id_ficha, :codigo_rfid)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':id_usuario'  => $data['id_usuario'],
            ':id_ficha'    => $data['id_ficha'],
            ':codigo_rfid' => $data['codigo_rfid'] ?: null,
        ]);
    }

    /**
     * Actualiza un aprendiz existente
     */
    public function update(int $id, array $data): bool {
        $sql = "UPDATE {$this->table}
                SET id_ficha = :id_ficha,
                    codigo_rfid = :codigo_rfid
                WHERE id_aprendiz = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':id'          => $id,
            ':id_ficha'    => $data['id_ficha'],
            ':codigo_rfid' => $data['codigo_rfid'] ?: null,
        ]);
    }

    /**
     * Elimina un aprendiz por su ID
     */
    public function delete(int $id): bool {
        $sql = "DELETE FROM {$this->table} WHERE id_aprendiz = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Cuenta el total de aprendices activos
     */
    public function count(): int {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} a
                INNER JOIN usuarios u ON a.id_usuario = u.id_usuario
                WHERE u.estado = 'Activo'";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return (int) $stmt->fetch()['total'];
    }
}
