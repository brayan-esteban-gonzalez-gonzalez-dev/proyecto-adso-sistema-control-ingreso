<?php
declare(strict_types=1);

/**
 * Usuario.php — Modelo de Usuarios
 *
 * CRUD completo para la tabla `usuarios` + autenticación.
 * Implementa `registrarUsuario()` con Transacción PDO atómica
 * como "Single Source of Truth" para garantizar integridad referencial
 * entre `usuarios` y `aprendices`.
 */
class Usuario {
    private PDO $conn;
    private string $table = 'usuarios';

    /** Constantes de Roles en el Sistema */
    public const ROL_ADMINISTRADOR = 1;
    public const ROL_INSTRUCTOR    = 2;
    public const ROL_APRENDIZ      = 3;

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    /**
     * Obtiene todos los usuarios (o filtrados por rol) junto con el nombre de su rol
     * y los datos vinculados de aprendiz/ficha si corresponden.
     */
    public function getAll(?int $rolId = null): array {
        $sql = "SELECT u.*, r.nombre AS nombre_rol,
                       a.id_aprendiz, a.codigo_rfid, a.id_ficha,
                       f.codigo_ficha, f.nombre_programa
                FROM {$this->table} u
                INNER JOIN roles r ON u.id_rol = r.id_rol
                LEFT JOIN aprendices a ON u.id_usuario = a.id_usuario
                LEFT JOIN fichas f ON a.id_ficha = f.id_ficha";

        $params = [];
        if ($rolId !== null && $rolId > 0) {
            $sql .= " WHERE u.id_rol = :id_rol";
            $params[':id_rol'] = $rolId;
        }

        $sql .= " ORDER BY u.creado_en DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Retorna el conteo total de usuarios agrupados por cada rol para los filtros rápidos.
     * @return array{total: int, administradores: int, instructores: int, aprendices: int}
     */
    public function getCountsByRole(): array {
        $sql = "SELECT 
                    COUNT(*) AS total,
                    SUM(CASE WHEN id_rol = :admin THEN 1 ELSE 0 END) AS administradores,
                    SUM(CASE WHEN id_rol = :inst THEN 1 ELSE 0 END) AS instructores,
                    SUM(CASE WHEN id_rol = :apren THEN 1 ELSE 0 END) AS aprendices
                FROM {$this->table}";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':admin' => self::ROL_ADMINISTRADOR,
            ':inst'  => self::ROL_INSTRUCTOR,
            ':apren' => self::ROL_APRENDIZ,
        ]);
        $row = $stmt->fetch();
        return [
            'total'           => (int) ($row['total'] ?? 0),
            'administradores' => (int) ($row['administradores'] ?? 0),
            'instructores'    => (int) ($row['instructores'] ?? 0),
            'aprendices'      => (int) ($row['aprendices'] ?? 0),
        ];
    }

    /**
     * Obtiene los instructores (rol 2) registrados en el sistema.
     * Utilizado para selectores de asignación como el Instructor Líder en fichas.
     *
     * @param bool $soloActivos Si es true, retorna únicamente instructores con estado 'Activo'.
     * @return array
     */
    public function getInstructores(bool $soloActivos = false): array {
        $sql = "SELECT id_usuario, nombre, apellido, num_documento, correo, estado
                FROM {$this->table}
                WHERE id_rol = :rol";
        if ($soloActivos) {
            $sql .= " AND estado = 'Activo'";
        }
        $sql .= " ORDER BY nombre ASC, apellido ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':rol' => self::ROL_INSTRUCTOR]);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene un usuario por su ID primario.
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
     * Obtiene un usuario por su número de documento.
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
     * Obtiene un usuario por su correo electrónico.
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
     * Obtiene los datos del usuario mediante el código RFID escaneado.
     * Realiza un INNER JOIN con la tabla `aprendices` donde reside `codigo_rfid`.
     */
    public function getByRfid(string $rfid): ?array {
        $sql = "SELECT u.*, a.id_aprendiz, a.id_ficha, a.codigo_rfid, r.nombre AS nombre_rol
                FROM {$this->table} u
                INNER JOIN aprendices a ON u.id_usuario = a.id_usuario
                INNER JOIN roles r ON u.id_rol = r.id_rol
                WHERE a.codigo_rfid = :rfid";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':rfid' => $rfid]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Autentica credenciales de acceso contra hash BCRYPT/Argon2.
     */
    public function authenticate(string $identificador, string $password): ?array {
        $sql = "SELECT u.*, r.nombre AS nombre_rol
                FROM {$this->table} u
                INNER JOIN roles r ON u.id_rol = r.id_rol
                WHERE (u.correo = :id_correo OR u.num_documento = :id_doc)
                  AND u.estado = 'Activo'";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':id_correo' => $identificador,
            ':id_doc'    => $identificador,
        ]);
        $usuario = $stmt->fetch();

        if ($usuario && password_verify($password, $usuario['password'])) {
            return $usuario;
        }

        return null;
    }

    /**
     * ─── SINGLE SOURCE OF TRUTH (TRANSACCIÓN PDO ATÓMICA) ───────────────────
     * Registra un usuario en la tabla `usuarios` y, en caso de ser Aprendiz,
     * inserta simultáneamente en la tabla `aprendices`.
     *
     * Flujo transaccional:
     *   A. Inicia transacción (`beginTransaction`).
     *   B. INSERT en `usuarios` (nombre, documento, correo, password, id_rol, etc.).
     *   C. Captura el ID insertado (`lastInsertId`).
     *   D. Si rol es Aprendiz, segundo INSERT en `aprendices` (id_usuario, id_ficha, codigo_rfid).
     *   E. Si todo sale bien, `commit()` y retorna true.
     *   F. Si ocurre un `PDOException`, ejecuta `rollBack()`, registra en log y retorna false.
     *
     * @param array{
     *     rol_id?: int,
     *     id_rol?: int,
     *     documento?: string,
     *     num_documento?: string,
     *     nombre: string,
     *     apellido: string,
     *     correo: string,
     *     password: string,
     *     estado?: string,
     *     ficha_id?: int|null,
     *     rfid_tag?: string|null
     * } $datos
     *
     * @return bool True si la transacción fue exitosa, false en error.
     */
    public function registrarUsuario(array $datos): bool {
        try {
            $this->conn->beginTransaction();

            // ── PASO A: INSERT en tabla `usuarios` ─────────────────────────────
            $sqlUsuario = "INSERT INTO {$this->table}
                            (id_rol, num_documento, nombre, apellido, correo, password, estado)
                           VALUES
                            (:id_rol, :num_documento, :nombre, :apellido, :correo, :password, :estado)";
            $stmtUsuario = $this->conn->prepare($sqlUsuario);

            // Asegurar que la contraseña esté hasheada
            $pass = (string) ($datos['password'] ?? '');
            $passwordHash = (str_starts_with($pass, '$2y$') || str_starts_with($pass, '$2a$') || str_starts_with($pass, '$argon2'))
                ? $pass
                : password_hash($pass, PASSWORD_DEFAULT);

            $idRol = (int) ($datos['rol_id'] ?? $datos['id_rol'] ?? self::ROL_APRENDIZ);
            $numDoc = (string) ($datos['documento'] ?? $datos['num_documento'] ?? '');

            $stmtUsuario->execute([
                ':id_rol'        => $idRol,
                ':num_documento' => $numDoc,
                ':nombre'        => (string) ($datos['nombre'] ?? ''),
                ':apellido'      => (string) ($datos['apellido'] ?? ''),
                ':correo'        => (string) ($datos['correo'] ?? ''),
                ':password'      => $passwordHash,
                ':estado'        => (string) ($datos['estado'] ?? 'Activo'),
            ]);

            // ── PASO B: Capturar ID generado ──────────────────────────────────
            $usuarioId = (int) $this->conn->lastInsertId();

            // ── PASO C: Si el rol es Aprendiz, segundo INSERT en `aprendices` ─
            if ($idRol === self::ROL_APRENDIZ) {
                $sqlAprendiz = "INSERT INTO aprendices (id_usuario, id_ficha, codigo_rfid)
                                VALUES (:id_usuario, :id_ficha, :codigo_rfid)";
                $stmtAprendiz = $this->conn->prepare($sqlAprendiz);
                $stmtAprendiz->execute([
                    ':id_usuario'  => $usuarioId,
                    ':id_ficha'    => (int) ($datos['ficha_id'] ?? 0),
                    ':codigo_rfid' => !empty($datos['rfid_tag']) ? (string) $datos['rfid_tag'] : null,
                ]);
            }

            // ── PASO D: Confirmar transacción ─────────────────────────────────
            $this->conn->commit();
            return true;

        } catch (\PDOException $e) {
            // Revertir en caso de cualquier fallo
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            error_log('[Usuario::registrarUsuario] PDOException: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Crea un usuario sin transacción (método de compatibilidad legado).
     */
    public function create(array $data): bool {
        $sql = "INSERT INTO {$this->table}
                    (id_rol, num_documento, nombre, apellido, correo, password, estado)
                VALUES
                    (:id_rol, :num_documento, :nombre, :apellido, :correo, :password, :estado)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':id_rol'        => $data['id_rol'],
            ':num_documento' => $data['num_documento'],
            ':nombre'        => $data['nombre'],
            ':apellido'      => $data['apellido'],
            ':correo'        => $data['correo'],
            ':password'      => password_hash($data['password'], PASSWORD_DEFAULT),
            ':estado'        => $data['estado'] ?? 'Activo',
        ]);
    }

    /**
     * Retorna el último ID insertado.
     */
    public function lastInsertId(): string {
        return $this->conn->lastInsertId();
    }

    /**
     * Actualiza un usuario existente.
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

        if (!empty($data['password'])) {
            $fields[] = "password = :password";
            $params[':password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id_usuario = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Elimina un usuario por su ID.
     */
    public function delete(int $id): bool {
        $sql = "DELETE FROM {$this->table} WHERE id_usuario = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Conteo total de usuarios.
     */
    public function count(): int {
        $sql = "SELECT COUNT(*) AS total FROM {$this->table}";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return (int) $stmt->fetch()['total'];
    }

    /**
     * Conteo de usuarios activos por rol.
     */
    public function countByRol(int $idRol): int {
        $sql = "SELECT COUNT(*) AS total
                FROM {$this->table}
                WHERE id_rol = :id_rol AND estado = 'Activo'";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id_rol' => $idRol]);
        return (int) $stmt->fetch()['total'];
    }
}
