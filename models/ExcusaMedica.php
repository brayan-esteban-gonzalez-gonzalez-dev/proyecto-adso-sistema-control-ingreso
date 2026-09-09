<?php
/**
 * ExcusaMedica.php — Modelo de Excusas Médicas
 * 
 * CRUD y flujo de aprobación para la tabla `excusas_medicas`.
 */
class ExcusaMedica {
    private PDO $conn;
    private string $table = 'excusas_medicas';

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    /**
     * Obtiene todas las excusas con datos del aprendiz
     */
    public function getAll(): array {
        $sql = "SELECT e.*, 
                       u.nombre, u.apellido, u.num_documento,
                       f.codigo_ficha,
                       CONCAT(r.nombre, ' ', r.apellido) AS nombre_revisor
                FROM {$this->table} e
                INNER JOIN aprendices a ON e.id_aprendiz = a.id_aprendiz
                INNER JOIN usuarios u ON a.id_usuario = u.id_usuario
                INNER JOIN fichas f ON a.id_ficha = f.id_ficha
                LEFT JOIN usuarios r ON e.id_instructor_revisor = r.id_usuario
                ORDER BY e.creado_en DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Obtiene una excusa por su ID
     */
    public function getById(int $id): ?array {
        $sql = "SELECT e.*, 
                       u.nombre, u.apellido, u.num_documento,
                       f.codigo_ficha, f.nombre_programa,
                       CONCAT(r.nombre, ' ', r.apellido) AS nombre_revisor
                FROM {$this->table} e
                INNER JOIN aprendices a ON e.id_aprendiz = a.id_aprendiz
                INNER JOIN usuarios u ON a.id_usuario = u.id_usuario
                INNER JOIN fichas f ON a.id_ficha = f.id_ficha
                LEFT JOIN usuarios r ON e.id_instructor_revisor = r.id_usuario
                WHERE e.id_excusa = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Obtiene excusas de un aprendiz
     */
    public function getByAprendiz(int $idAprendiz): array {
        $sql = "SELECT e.*, 
                       CONCAT(r.nombre, ' ', r.apellido) AS nombre_revisor
                FROM {$this->table} e
                LEFT JOIN usuarios r ON e.id_instructor_revisor = r.id_usuario
                WHERE e.id_aprendiz = :id_aprendiz
                ORDER BY e.creado_en DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id_aprendiz' => $idAprendiz]);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene excusas pendientes de revisión
     */
    public function getPendientes(): array {
        $sql = "SELECT e.*, 
                       u.nombre, u.apellido, u.num_documento,
                       f.codigo_ficha
                FROM {$this->table} e
                INNER JOIN aprendices a ON e.id_aprendiz = a.id_aprendiz
                INNER JOIN usuarios u ON a.id_usuario = u.id_usuario
                INNER JOIN fichas f ON a.id_ficha = f.id_ficha
                WHERE e.estado = 'Pendiente'
                ORDER BY e.creado_en ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Crea una nueva excusa médica
     */
    public function create(array $data): bool {
        $sql = "INSERT INTO {$this->table} 
                (id_aprendiz, id_ingreso, fecha_inicio, fecha_fin, motivo, archivo_adjunto)
                VALUES (:id_aprendiz, :id_ingreso, :fecha_inicio, :fecha_fin, :motivo, :archivo_adjunto)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':id_aprendiz'     => $data['id_aprendiz'],
            ':id_ingreso'      => $data['id_ingreso'] ?: null,
            ':fecha_inicio'    => $data['fecha_inicio'],
            ':fecha_fin'       => $data['fecha_fin'],
            ':motivo'          => $data['motivo'],
            ':archivo_adjunto' => $data['archivo_adjunto'],
        ]);
    }

    /**
     * Aprueba una excusa médica
     */
    public function aprobar(int $idExcusa, int $idInstructor, string $comentario = ''): bool {
        $sql = "UPDATE {$this->table}
                SET estado = 'Aprobada',
                    id_instructor_revisor = :id_instructor,
                    comentario_revision = :comentario
                WHERE id_excusa = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':id'             => $idExcusa,
            ':id_instructor'  => $idInstructor,
            ':comentario'     => $comentario,
        ]);
    }

    /**
     * Rechaza una excusa médica
     */
    public function rechazar(int $idExcusa, int $idInstructor, string $comentario = ''): bool {
        $sql = "UPDATE {$this->table}
                SET estado = 'Rechazada',
                    id_instructor_revisor = :id_instructor,
                    comentario_revision = :comentario
                WHERE id_excusa = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':id'             => $idExcusa,
            ':id_instructor'  => $idInstructor,
            ':comentario'     => $comentario,
        ]);
    }

    /**
     * Cuenta excusas pendientes
     */
    public function countPendientes(): int {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE estado = 'Pendiente'";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return (int) $stmt->fetch()['total'];
    }

    /**
     * Elimina una excusa por su ID
     */
    public function delete(int $id): bool {
        $sql = "DELETE FROM {$this->table} WHERE id_excusa = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
}
