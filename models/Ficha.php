<?php
declare(strict_types=1);

/**
 * Ficha.php — Modelo de Fichas
 *
 * CRUD completo para la tabla `ficha`.
 * Nueva DB: tabla `ficha`, PK `id`, campos: codigo (INT), Programa_id, jornada_id, instructor_id, estado.
 * Relacionado con tabla `programa` (id, nombre, descripcion) y `jornada`.
 */
class Ficha {
    private PDO $conn;
    private string $table = 'ficha';

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    /**
     * Obtiene todas las fichas con programa, jornada e instructor
     */
    public function getAll(): array {
        $sql = "SELECT f.*,
                       f.codigo AS numero_ficha,
                       p.nombre AS nombre_programa,
                       p.nombre AS programa_nombre,
                       p.descripcion AS descripcion_programa,
                       j.nombre AS nombre_jornada,
                       j.nombre AS jornada_nombre,
                       CONCAT(u.nombre, ' ', u.apellido) AS nombre_instructor,
                       (SELECT COUNT(*) FROM usuario a WHERE a.Ficha_id = f.id AND a.Rol_id = 3 AND a.estado = 'Activo') AS total_aprendices,
                       (
                           SELECT COUNT(DISTINCT inst.id)
                           FROM usuario inst
                           WHERE inst.Rol_id IN (1, 2)
                             AND (
                                 inst.id = f.instructor_id
                                 OR EXISTS (SELECT 1 FROM sesion s WHERE s.Ficha_id = f.id AND s.Instructor_id = inst.id)
                                 OR EXISTS (SELECT 1 FROM asignacion_instructor ai WHERE ai.ficha_id = f.id AND ai.instructor_id = inst.id)
                             )
                       ) AS total_instructores
                FROM {$this->table} f
                INNER JOIN programa p ON f.Programa_id = p.id
                LEFT JOIN jornada j ON f.jornada_id = j.id
                LEFT JOIN usuario u ON f.instructor_id = u.id
                ORDER BY f.codigo ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Obtiene una ficha por su ID
     */
    public function getById(int $id): ?array {
        $sql = "SELECT f.*,
                       f.codigo AS numero_ficha,
                       p.nombre AS nombre_programa,
                       p.nombre AS programa_nombre,
                       p.descripcion AS descripcion_programa,
                       j.nombre AS nombre_jornada,
                       j.nombre AS jornada_nombre,
                       CONCAT(u.nombre, ' ', u.apellido) AS nombre_instructor
                FROM {$this->table} f
                INNER JOIN programa p ON f.Programa_id = p.id
                LEFT JOIN jornada j ON f.jornada_id = j.id
                LEFT JOIN usuario u ON f.instructor_id = u.id
                WHERE f.id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Obtiene una ficha por su código numérico (ej. 3064749)
     */
    public function getByCodigo(int $codigo): ?array {
        $sql = "SELECT f.*,
                       f.codigo AS numero_ficha,
                       p.nombre AS nombre_programa,
                       p.nombre AS programa_nombre,
                       p.descripcion AS descripcion_programa,
                       j.nombre AS nombre_jornada,
                       j.nombre AS jornada_nombre,
                       CONCAT(u.nombre, ' ', u.apellido) AS nombre_instructor
                FROM {$this->table} f
                INNER JOIN programa p ON f.Programa_id = p.id
                LEFT JOIN jornada j ON f.jornada_id = j.id
                LEFT JOIN usuario u ON f.instructor_id = u.id
                WHERE f.codigo = :codigo";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':codigo' => $codigo]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Obtiene fichas por instructor líder
     */
    public function getByInstructor(int $instructorId): array {
        $sql = "SELECT f.*, p.nombre AS nombre_programa, p.nombre AS programa_nombre
                FROM {$this->table} f
                INNER JOIN programa p ON f.Programa_id = p.id
                WHERE f.instructor_id = :instructor_id
                ORDER BY f.codigo ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':instructor_id' => $instructorId]);
        return $stmt->fetchAll();
    }

    /**
     * Crea una nueva ficha
     */
    public function create(array $data): bool {
        $sql = "INSERT INTO {$this->table} (codigo, Programa_id, jornada_id, instructor_id, estado)
                VALUES (:codigo, :Programa_id, :jornada_id, :instructor_id, :estado)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':codigo'        => $data['codigo'],
            ':Programa_id'   => $data['Programa_id'],
            ':jornada_id'    => $data['jornada_id'] ?? null,
            ':instructor_id' => $data['instructor_id'] ?: null,
            ':estado'        => $data['estado'] ?? 'Activo',
        ]);
    }

    /**
     * Actualiza una ficha existente
     */
    public function update(int $id, array $data): bool {
        $sql = "UPDATE {$this->table}
                SET codigo        = :codigo,
                    Programa_id   = :Programa_id,
                    jornada_id    = :jornada_id,
                    instructor_id = :instructor_id,
                    estado        = :estado
                WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':id'            => $id,
            ':codigo'        => $data['codigo'],
            ':Programa_id'   => $data['Programa_id'],
            ':jornada_id'    => $data['jornada_id'] ?? null,
            ':instructor_id' => $data['instructor_id'] ?: null,
            ':estado'        => $data['estado'] ?? 'Activo',
        ]);
    }

    /**
     * Elimina una ficha por su ID
     */
    public function delete(int $id): bool {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Cuenta el total de fichas activas
     */
    public function count(): int {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE estado = 'Activo'";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return (int) $stmt->fetch()['total'];
    }

    /**
     * Obtiene todos los programas activos disponibles
     */
    public function getProgramas(): array {
        static $hasEstado = null;
        if ($hasEstado === null) {
            try {
                $stmt = $this->conn->query("SHOW COLUMNS FROM programa LIKE 'estado'");
                $hasEstado = ($stmt && $stmt->rowCount() > 0);
            } catch (\Throwable $e) {
                $hasEstado = false;
            }
        }
        $where = $hasEstado ? "WHERE estado = 'Activo'" : "";
        $sql = "SELECT * FROM programa {$where} ORDER BY nombre";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Obtiene todas las jornadas disponibles
     */
    public function getJornadas(): array {
        $sql = "SELECT * FROM jornada ORDER BY hora_inicio";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * RF-RBAC: Obtiene únicamente las fichas activas donde el instructor tiene vinculación real
     * (como instructor líder, en sesiones de clase o en asignaciones semestrales).
     *
     * Incluye datos enriquecidos: f.id, f.numero_ficha, programa_nombre, jornada_nombre,
     * total de aprendices y total de instructores vinculados.
     */
    public function getFichasPorInstructor(int $instructorId): array {
        $sql = "SELECT f.id,
                       f.codigo,
                       f.codigo AS numero_ficha,
                       f.estado,
                       p.nombre AS programa_nombre,
                       p.nombre AS nombre_programa,
                       p.descripcion AS descripcion_programa,
                       j.nombre AS jornada_nombre,
                       j.nombre AS nombre_jornada,
                       CONCAT(u.nombre, ' ', u.apellido) AS nombre_instructor,
                       (SELECT COUNT(*) FROM usuario a WHERE a.Ficha_id = f.id AND a.Rol_id = 3 AND a.estado = 'Activo') AS total_aprendices,
                       (
                           SELECT COUNT(DISTINCT inst.id)
                           FROM usuario inst
                           WHERE inst.Rol_id IN (1, 2)
                             AND (
                                 inst.id = f.instructor_id
                                 OR EXISTS (SELECT 1 FROM sesion s WHERE s.Ficha_id = f.id AND s.Instructor_id = inst.id)
                                 OR EXISTS (SELECT 1 FROM asignacion_instructor ai WHERE ai.ficha_id = f.id AND ai.instructor_id = inst.id)
                             )
                       ) AS total_instructores
                FROM {$this->table} f
                INNER JOIN programa p ON f.Programa_id = p.id
                LEFT JOIN jornada j ON f.jornada_id = j.id
                LEFT JOIN usuario u ON f.instructor_id = u.id
                WHERE f.estado = 'Activo'
                  AND (
                      f.instructor_id = :instructor_id1
                      OR EXISTS (SELECT 1 FROM sesion s WHERE s.Ficha_id = f.id AND s.Instructor_id = :instructor_id2)
                      OR EXISTS (SELECT 1 FROM asignacion_instructor ai WHERE ai.ficha_id = f.id AND ai.instructor_id = :instructor_id3)
                  )
                ORDER BY f.codigo ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':instructor_id1' => $instructorId,
            ':instructor_id2' => $instructorId,
            ':instructor_id3' => $instructorId,
        ]);
        return $stmt->fetchAll();
    }

    /**
     * Alias compatible de getFichasPorInstructor
     */
    public function getByInstructorAsignado(int $instructorId): array {
        return $this->getFichasPorInstructor($instructorId);
    }

    /**
     * Valida en servidor si un instructor está vinculado legítimamente a una ficha
     * (evita vulnerabilidades IDOR por alteración de parámetros GET).
     */
    public function instructorPerteneceAFicha(int $instructorId, int $fichaId): bool {
        $sql = "SELECT 1 FROM {$this->table} f
                WHERE f.id = :ficha_id
                  AND (
                      f.instructor_id = :instructor_id1
                      OR EXISTS (SELECT 1 FROM sesion s WHERE s.Ficha_id = f.id AND s.Instructor_id = :instructor_id2)
                      OR EXISTS (SELECT 1 FROM asignacion_instructor ai WHERE ai.ficha_id = f.id AND ai.instructor_id = :instructor_id3)
                  )
                LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':ficha_id'        => $fichaId,
            ':instructor_id1'  => $instructorId,
            ':instructor_id2'  => $instructorId,
            ':instructor_id3'  => $instructorId,
        ]);
        return (bool) $stmt->fetchColumn();
    }

    /**
     * Alias compatible de instructorPerteneceAFicha
     */
    public function perteneceAInstructor(int $fichaId, int $instructorId): bool {
        return $this->instructorPerteneceAFicha($instructorId, $fichaId);
    }

    /**
     * Obtiene todos los aprendices matriculados en una ficha ordenados alfabéticamente por nombre
     */
    public function getAprendicesPorFicha(int $fichaId): array {
        $sql = "SELECT u.id,
                       u.identificacion,
                       u.identificacion AS documento,
                       u.nombre,
                       u.apellido,
                       CONCAT(u.nombre, ' ', u.apellido) AS nombre_completo,
                       u.email,
                       u.email AS correo,
                       u.codigo_llavero,
                       u.codigo_llavero AS rfid_tag,
                       u.estado
                FROM usuario u
                WHERE u.Rol_id = 3 AND u.Ficha_id = :ficha_id
                ORDER BY u.nombre ASC, u.apellido ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':ficha_id' => $fichaId]);
        return $stmt->fetchAll();
    }

    /**
     * Alias compatible de getAprendicesPorFicha
     */
    public function getAprendices(int $fichaId): array {
        return $this->getAprendicesPorFicha($fichaId);
    }

    /**
     * Obtiene todos los instructores vinculados a la ficha (líder, sesiones de clase y asignaciones)
     * con sus competencias o especialidades asignadas, sin duplicados.
     */
    public function getInstructoresPorFicha(int $fichaId): array {
        $sql = "SELECT u.id,
                       u.identificacion,
                       u.identificacion AS documento,
                       u.nombre,
                       u.apellido,
                       CONCAT(u.nombre, ' ', u.apellido) AS nombre_completo,
                       u.email,
                       u.email AS correo,
                       u.estado,
                       CASE WHEN f.instructor_id = u.id THEN 'Instructor Líder' ELSE 'Instructor de Formación' END AS rol_en_ficha,
                       COALESCE(
                           (
                               SELECT GROUP_CONCAT(DISTINCT COALESCE(c.nombre, s.especialidad) ORDER BY COALESCE(c.nombre, s.especialidad) SEPARATOR ', ')
                               FROM sesion s
                               LEFT JOIN competencia c ON s.Competencia_id = c.id
                               WHERE s.Ficha_id = :ficha_id1 AND s.Instructor_id = u.id
                           ),
                           'Formación Técnica General'
                       ) AS competencias_asignadas
                FROM usuario u
                LEFT JOIN ficha f ON f.id = :ficha_id2 AND f.instructor_id = u.id
                WHERE u.Rol_id IN (1, 2)
                  AND (
                      f.instructor_id = u.id
                      OR EXISTS (SELECT 1 FROM sesion s WHERE s.Ficha_id = :ficha_id3 AND s.Instructor_id = u.id)
                      OR EXISTS (SELECT 1 FROM asignacion_instructor ai WHERE ai.ficha_id = :ficha_id4 AND ai.instructor_id = u.id)
                  )
                GROUP BY u.id, u.identificacion, u.nombre, u.apellido, u.email, u.estado, f.instructor_id
                ORDER BY (CASE WHEN f.instructor_id = u.id THEN 0 ELSE 1 END), u.nombre ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':ficha_id1' => $fichaId,
            ':ficha_id2' => $fichaId,
            ':ficha_id3' => $fichaId,
            ':ficha_id4' => $fichaId,
        ]);
        return $stmt->fetchAll();
    }

    /**
     * Alias compatible de getInstructoresPorFicha
     */
    public function getInstructoresVinculados(int $fichaId): array {
        return $this->getInstructoresPorFicha($fichaId);
    }
}
