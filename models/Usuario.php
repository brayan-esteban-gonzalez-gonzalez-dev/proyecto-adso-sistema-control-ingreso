<?php
/**
 * Usuario.php — Modelo de Usuarios
 *
 * CRUD completo para la tabla `Usuario` + autenticación.
 * Nueva DB: tabla `Usuario`, PK `id`, campos: identificacion, email, Rol_id, Ficha_id, codigo_llavero.
 */
class Usuario {
    public const ROL_ADMINISTRADOR = 1;
    public const ROL_INSTRUCTOR    = 2;
    public const ROL_APRENDIZ      = 3;

    private PDO $conn;
    private string $table = 'Usuario';

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    /**
     * Obtiene todos los usuarios con su rol y datos de ficha si aplica,
     * con opción de filtrado por rol.
     */
    public function getAll(?int $rolId = null): array {
        $sql = "SELECT u.*, r.nombre AS nombre_rol,
                       f.codigo AS codigo_ficha,
                       p.nombre AS nombre_programa
                FROM {$this->table} u
                INNER JOIN Rol r ON u.Rol_id = r.id
                LEFT JOIN Ficha f ON u.Ficha_id = f.id
                LEFT JOIN Programa p ON f.Programa_id = p.id";

        if ($rolId !== null) {
            $sql .= " WHERE u.Rol_id = :rol_id";
        }

        $sql .= " ORDER BY u.apellido, u.nombre";

        $stmt = $this->conn->prepare($sql);
        if ($rolId !== null) {
            $stmt->bindValue(':rol_id', $rolId, PDO::PARAM_INT);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Retorna el conteo total de usuarios agrupados por cada rol para los filtros rápidos.
     * @return array{total: int, administradores: int, instructores: int, aprendices: int}
     */
    public function getCountsByRole(): array {
        $sql = "SELECT 
                    COUNT(*) AS total,
                    SUM(CASE WHEN Rol_id = 1 THEN 1 ELSE 0 END) AS administradores,
                    SUM(CASE WHEN Rol_id = 2 THEN 1 ELSE 0 END) AS instructores,
                    SUM(CASE WHEN Rol_id = 3 THEN 1 ELSE 0 END) AS aprendices
                FROM {$this->table}";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $row = $stmt->fetch();
        return [
            'total'           => (int) ($row['total'] ?? 0),
            'administradores' => (int) ($row['administradores'] ?? 0),
            'instructores'    => (int) ($row['instructores'] ?? 0),
            'aprendices'      => (int) ($row['aprendices'] ?? 0),
        ];
    }

    /**
     * Obtiene un usuario por su ID primario.
     */
    public function getById(int $id): ?array {
        $sql = "SELECT u.*, r.nombre AS nombre_rol
                FROM {$this->table} u
                INNER JOIN Rol r ON u.Rol_id = r.id
                WHERE u.id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Obtiene un usuario por número de identificación
     */
    public function getByDocumento(string $identificacion): ?array {
        $sql = "SELECT u.*, r.nombre AS nombre_rol
                FROM {$this->table} u
                INNER JOIN Rol r ON u.Rol_id = r.id
                WHERE u.identificacion = :identificacion";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':identificacion' => $identificacion]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Obtiene un usuario por correo electrónico (email)
     */
    public function getByCorreo(string $email): ?array {
        $sql = "SELECT u.*, r.nombre AS nombre_rol
                FROM {$this->table} u
                INNER JOIN Rol r ON u.Rol_id = r.id
                WHERE u.email = :email";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':email' => $email]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Obtiene todos los instructores (roles 1 y 2) activos
     */
    public function getInstructores(): array {
        $sql = "SELECT * FROM {$this->table} WHERE Rol_id IN (1,2) AND estado = 'Activo' ORDER BY nombre";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Busca un instructor por coincidencia de nombre y apellido
     */
    public function getInstructorPorNombre(string $nombreBusqueda): ?array {
        $instructores = $this->getInstructores();
        $normalizar = function($str) {
            $str = mb_strtolower(trim($str), 'UTF-8');
            $str = str_replace(
                ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ', 'Á', 'É', 'Í', 'Ó', 'Ú', 'Ü', 'Ñ'],
                ['a', 'e', 'i', 'o', 'u', 'u', 'n', 'a', 'e', 'i', 'o', 'u', 'u', 'n'],
                $str
            );
            return preg_replace('/\s+/', ' ', $str);
        };

        $busqueda = $normalizar($nombreBusqueda);
        if (empty($busqueda)) {
            return null;
        }

        // 1. Coincidencia exacta completa
        foreach ($instructores as $inst) {
            $nomCompleto1 = $normalizar($inst['nombre'] . ' ' . $inst['apellido']);
            $nomCompleto2 = $normalizar($inst['apellido'] . ' ' . $inst['nombre']);

            if ($nomCompleto1 === $busqueda || $nomCompleto2 === $busqueda) {
                return $inst;
            }
        }

        // 2. Coincidencia por partes de palabras
        $partes = array_filter(explode(' ', $busqueda), fn($p) => mb_strlen($p) >= 3);
        if (count($partes) >= 2) {
            foreach ($instructores as $inst) {
                $nomCompleto = $normalizar($inst['nombre'] . ' ' . $inst['apellido']);
                $todasEncontradas = true;
                foreach ($partes as $parte) {
                    if (!str_contains($nomCompleto, $parte)) {
                        $todasEncontradas = false;
                        break;
                    }
                }
                if ($todasEncontradas) {
                    return $inst;
                }
            }
        }

        return null;
    }

    /**
     * Obtiene todos los aprendices (rol 3) con datos de ficha
     */
    public function getAprendices(): array {
        $sql = "SELECT u.*,
                       f.codigo AS codigo_ficha,
                       p.nombre AS nombre_programa,
                       r.nombre AS nombre_rol
                FROM {$this->table} u
                INNER JOIN Rol r ON u.Rol_id = r.id
                LEFT JOIN Ficha f ON u.Ficha_id = f.id
                LEFT JOIN Programa p ON f.Programa_id = p.id
                WHERE u.Rol_id = 3
                ORDER BY u.apellido, u.nombre";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Obtiene aprendices por ficha
     */
    public function getAprendicesByFicha(int $fichaId): array {
        $sql = "SELECT u.*
                FROM {$this->table} u
                WHERE u.Rol_id = 3 AND u.Ficha_id = :ficha_id AND u.estado = 'Activo'
                ORDER BY u.apellido, u.nombre";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':ficha_id' => $fichaId]);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene un usuario por código de llavero (RFID)
     */
    public function getByCodigoLlavero(string $codigo): ?array {
        $sql = "SELECT u.*, r.nombre AS nombre_rol,
                       f.codigo AS codigo_ficha
                FROM {$this->table} u
                INNER JOIN Rol r ON u.Rol_id = r.id
                LEFT JOIN Ficha f ON u.Ficha_id = f.id
                WHERE u.codigo_llavero = :codigo AND u.estado = 'Activo'";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':codigo' => $codigo]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Autentica un usuario por identificación y contraseña
     */
    public function authenticate(string $identificacion, string $password): ?array {
        $usuario = $this->getByDocumento($identificacion);
        if ($usuario && $usuario['estado'] === 'Activo' && password_verify($password, $usuario['password'])) {
            return $usuario;
        }
        return null;
    }

    /**
     * Crea un nuevo usuario
     */
    public function create(array $data): bool {
        $sql = "INSERT INTO {$this->table} (Rol_id, identificacion, nombre, apellido, email, password, Ficha_id, codigo_llavero, estado)
                VALUES (:Rol_id, :identificacion, :nombre, :apellido, :email, :password, :Ficha_id, :codigo_llavero, :estado)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':Rol_id'         => $data['Rol_id'],
            ':identificacion' => $data['identificacion'],
            ':nombre'         => $data['nombre'],
            ':apellido'       => $data['apellido'],
            ':email'          => $data['email'],
            ':password'       => password_hash($data['password'], PASSWORD_BCRYPT),
            ':Ficha_id'       => $data['Ficha_id'] ?? null,
            ':codigo_llavero' => $data['codigo_llavero'] ?? null,
            ':estado'         => $data['estado'] ?? 'Activo',
        ]);
    }

    /**
     * Registra un instructor nuevo detectado en la importación de horarios
     */
    public function createInstructorDesdeExcel(string $nombre, string $apellido): ?int {
        // Generar una identificación temporal única
        $identificacionTemp = 'INST_' . strtoupper(substr(md5($nombre . ' ' . $apellido . microtime()), 0, 8));
        
        // Limpiar para email
        $cleanEmailName = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($nombre));
        $cleanEmailApe  = preg_replace('/[^a-zA-Z0-9]/', '', strtolower($apellido));
        if (empty($cleanEmailName)) { $cleanEmailName = 'instructor'; }
        if (empty($cleanEmailApe)) { $cleanEmailApe = substr(md5(microtime()), 0, 5); }
        $email = $cleanEmailName . '.' . $cleanEmailApe . '_' . substr(uniqid(), -4) . '@sena.edu.co';

        $sql = "INSERT INTO {$this->table} (Rol_id, identificacion, nombre, apellido, email, password, estado)
                VALUES (:Rol_id, :identificacion, :nombre, :apellido, :email, :password, :estado)";
        $stmt = $this->conn->prepare($sql);
        $ok = $stmt->execute([
            ':Rol_id'         => 2, // Rol 2: Instructor
            ':identificacion' => $identificacionTemp,
            ':nombre'         => ucwords(mb_strtolower(trim($nombre))),
            ':apellido'       => ucwords(mb_strtolower(trim($apellido))),
            ':email'          => $email,
            ':password'       => password_hash(bin2hex(random_bytes(8)), PASSWORD_BCRYPT),
            ':estado'         => 'Activo',
        ]);

        return $ok ? (int)$this->conn->lastInsertId() : null;
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

        foreach (['Rol_id', 'identificacion', 'nombre', 'apellido', 'email', 'Ficha_id', 'codigo_llavero', 'estado'] as $field) {
            if (array_key_exists($field, $data)) {
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

        $sql = "UPDATE {$this->table} SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Elimina un usuario por su ID
     */
    public function delete(int $id): bool {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
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
    public function countByRol(int $rolId): int {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE Rol_id = :rol_id AND estado = 'Activo'";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':rol_id' => $rolId]);
        return (int) $stmt->fetch()['total'];
    }
}
