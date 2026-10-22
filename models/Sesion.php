<?php
declare(strict_types=1);

/**
 * Sesion.php — Modelo de Sesiones de Clase (Horarios)
 *
 * Maneja las sesiones de clase programadas para las fichas,
 * incluyendo instructores, horarios y metadatos de la sesión.
 * Incluye métodos para el flujo de toggle de asistencia del instructor.
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
     * Sesiones de un instructor en un rango, con ficha, programa y jornada
     */
    public function getByInstructor(int $instructorId, string $desde, string $hasta, ?int $fichaId = null, ?string $estado = null): array {
        $sql = "SELECT s.*,
                       f.codigo AS codigo_ficha,
                       p.nombre AS nombre_programa,
                       j.nombre AS nombre_jornada,
                       c.nombre AS nombre_competencia
                FROM {$this->table} s
                INNER JOIN Ficha f ON s.Ficha_id = f.id
                LEFT JOIN Programa p ON f.Programa_id = p.id
                LEFT JOIN jornada j ON f.jornada_id = j.id
                LEFT JOIN competencia c ON s.Competencia_id = c.id
                WHERE s.Instructor_id = :instructor_id
                  AND s.fecha BETWEEN :desde AND :hasta";
        $params = [
            ':instructor_id' => $instructorId,
            ':desde'         => $desde,
            ':hasta'         => $hasta,
        ];

        if ($fichaId) {
            $sql .= " AND s.Ficha_id = :ficha_id";
            $params[':ficha_id'] = $fichaId;
        }
        if ($estado) {
            $sql .= " AND s.estado = :estado";
            $params[':estado'] = $estado;
        }

        $sql .= " ORDER BY s.fecha ASC, s.hora_inicio ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Fichas en las que el instructor tiene sesiones asignadas
     */
    public function getFichasDeInstructor(int $instructorId): array {
        $sql = "SELECT DISTINCT f.id, f.codigo, p.nombre AS nombre_programa
                FROM {$this->table} s
                INNER JOIN Ficha f ON s.Ficha_id = f.id
                LEFT JOIN Programa p ON f.Programa_id = p.id
                WHERE s.Instructor_id = :instructor_id
                ORDER BY f.codigo";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':instructor_id' => $instructorId]);
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
     * Obtiene una sesión existente por ficha, fecha y hora de inicio
     */
    public function getSesionExistente(int $fichaId, string $fecha, string $horaInicio): ?array {
        $sql = "SELECT * FROM {$this->table}
                WHERE Ficha_id = :ficha_id AND fecha = :fecha AND hora_inicio = :hora_inicio
                LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':ficha_id'    => $fichaId,
            ':fecha'       => $fecha,
            ':hora_inicio' => $horaInicio,
        ]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Verifica si ya existe una sesión en la misma fecha y hora para la misma ficha
     */
    public function existeSesion(int $fichaId, string $fecha, string $horaInicio): bool {
        return $this->getSesionExistente($fichaId, $fecha, $horaInicio) !== null;
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

    // =====================================================================
    //  TOGGLE DE ASISTENCIA — Sesiones activas
    // =====================================================================

    /**
     * Busca si existe una sesión con estado 'Activo' para una ficha en la fecha actual.
     *
     * @return array|null Datos de la sesión activa, o null si no hay ninguna.
     */
    public function obtenerSesionActivaPorFicha(int $fichaId): ?array
    {
        $sql = "SELECT s.*,
                       f.codigo AS codigo_ficha,
                       CONCAT(u.nombre, ' ', u.apellido) AS nombre_instructor
                FROM {$this->table} s
                INNER JOIN Ficha f ON s.Ficha_id = f.id
                INNER JOIN Usuario u ON s.Instructor_id = u.id
                WHERE s.Ficha_id = :ficha_id
                  AND s.fecha = CURDATE()
                  AND s.estado = 'Activo'
                LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':ficha_id' => $fichaId]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Obtiene todas las sesiones activas de un instructor en el día actual.
     *
     * @return array Lista de sesiones activas con datos de ficha y programa.
     */
    public function obtenerSesionesActivasInstructor(int $instructorId): array
    {
        $sql = "SELECT s.*,
                       f.id       AS ficha_id_ref,
                       f.codigo   AS codigo_ficha,
                       p.nombre   AS nombre_programa
                FROM {$this->table} s
                INNER JOIN Ficha f   ON s.Ficha_id    = f.id
                LEFT  JOIN Programa p ON f.Programa_id = p.id
                WHERE s.Instructor_id = :instructor_id
                  AND s.fecha  = CURDATE()
                  AND s.estado = 'Activo'
                ORDER BY s.hora_inicio ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':instructor_id' => $instructorId]);
        return $stmt->fetchAll();
    }

    /**
     * Abre una nueva sesión de asistencia para una ficha (toggle ON).
     *
     * Inserta un registro con estado 'Activo', hora_fin = NULL, fecha y hora actuales.
     *
     * @return int|false  ID de la sesión creada, o false si falla.
     */
    public function abrirSesion(int $fichaId, int $instructorId): int|false
    {
        // Verificar que no haya ya una sesión activa para esta ficha hoy
        $existente = $this->obtenerSesionActivaPorFicha($fichaId);
        if ($existente !== null) {
            return false;
        }

        $sql = "INSERT INTO {$this->table}
                    (Ficha_id, Instructor_id, fecha, hora_inicio, hora_fin, estado)
                VALUES
                    (:ficha_id, :instructor_id, CURDATE(), CURTIME(), NULL, 'Activo')";
        $stmt = $this->conn->prepare($sql);
        $ok = $stmt->execute([
            ':ficha_id'      => $fichaId,
            ':instructor_id' => $instructorId,
        ]);

        return $ok ? (int) $this->conn->lastInsertId() : false;
    }

    /**
     * Cierra una sesión de asistencia (toggle OFF).
     *
     * Usa una transacción para:
     *  1. Actualizar estado a 'Finalizada' y registrar hora_fin.
     *  2. Insertar inasistencias para los aprendices activos de la ficha
     *     que NO tengan registro en la tabla `asistencia` para esa sesión.
     *
     * @return array{ok: bool, inasistencias: int, mensaje: string}
     */
    public function cerrarSesion(int $sesionId, int $instructorId): array
    {
        // Verificar que la sesión existe, está activa y pertenece al instructor
        $sql = "SELECT s.*, f.id AS fk_ficha_id
                FROM {$this->table} s
                INNER JOIN Ficha f ON s.Ficha_id = f.id
                WHERE s.id = :id
                  AND s.Instructor_id = :instructor_id
                  AND s.estado = 'Activo'
                LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':id'            => $sesionId,
            ':instructor_id' => $instructorId,
        ]);
        $sesion = $stmt->fetch();

        if (!$sesion) {
            return [
                'ok'            => false,
                'inasistencias' => 0,
                'mensaje'       => 'Sesión no encontrada, ya cerrada o no pertenece a este instructor.',
            ];
        }

        $fichaId = (int) $sesion['Ficha_id'];

        try {
            $this->conn->beginTransaction();

            // 1. Cerrar la sesión
            $sqlUpdate = "UPDATE {$this->table}
                          SET estado = 'Finalizada', hora_fin = CURTIME()
                          WHERE id = :id";
            $stmtUpdate = $this->conn->prepare($sqlUpdate);
            $stmtUpdate->execute([':id' => $sesionId]);

            // 2. Registrar inasistencias para aprendices sin marcación en esta sesión
            $sqlInasistencia = "INSERT INTO inasistencia (Usuario_id, Ficha_id, Sesion_id, fecha, generado_por)
                                SELECT u.id, :ficha_id, :sesion_id, CURDATE(), 'sistema_toggle'
                                FROM Usuario u
                                WHERE u.Ficha_id = :ficha_id2
                                  AND u.Rol_id   = 3
                                  AND u.estado    = 'Activo'
                                  AND u.id NOT IN (
                                      SELECT a.Usuario_id
                                      FROM asistencia a
                                      WHERE a.Sesion_id = :sesion_id2
                                  )";
            $stmtInasistencia = $this->conn->prepare($sqlInasistencia);
            $stmtInasistencia->execute([
                ':ficha_id'   => $fichaId,
                ':sesion_id'  => $sesionId,
                ':ficha_id2'  => $fichaId,
                ':sesion_id2' => $sesionId,
            ]);
            $totalInasistencias = $stmtInasistencia->rowCount();

            $this->conn->commit();

            return [
                'ok'            => true,
                'inasistencias' => $totalInasistencias,
                'mensaje'       => "Sesión cerrada. Se registraron {$totalInasistencias} inasistencia(s).",
            ];

        } catch (\Throwable $e) {
            $this->conn->rollBack();
            return [
                'ok'            => false,
                'inasistencias' => 0,
                'mensaje'       => 'Error al cerrar la sesión: ' . $e->getMessage(),
            ];
        }
    }
}
