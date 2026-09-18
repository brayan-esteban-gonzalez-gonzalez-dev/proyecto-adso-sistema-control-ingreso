<?php
/**
 * ExcusaMedica.php — Modelo de Excusas Médicas
 * 
 * CRUD para la tabla `excusas_medicas`.
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
        $sql = "SELECT em.*, 
                       u.nombre, u.apellido, u.num_documento,
                       f.codigo_ficha, f.nombre_programa,
                       CONCAT(rev.nombre, ' ', rev.apellido) AS nombre_revisor
                FROM {$this->table} em
                INNER JOIN aprendices a ON em.id_aprendiz = a.id_aprendiz
                INNER JOIN usuarios u ON a.id_usuario = u.id_usuario
                INNER JOIN fichas f ON a.id_ficha = f.id_ficha
                LEFT JOIN usuarios rev ON em.id_instructor_revisor = rev.id_usuario
                ORDER BY em.creado_en DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Obtiene una excusa por su ID con datos completos
     */
    public function getById(int $id): ?array {
        $sql = "SELECT em.*, 
                       u.nombre, u.apellido, u.num_documento,
                       f.codigo_ficha, f.nombre_programa,
                       CONCAT(rev.nombre, ' ', rev.apellido) AS nombre_revisor
                FROM {$this->table} em
                INNER JOIN aprendices a ON em.id_aprendiz = a.id_aprendiz
                INNER JOIN usuarios u ON a.id_usuario = u.id_usuario
                INNER JOIN fichas f ON a.id_ficha = f.id_ficha
                LEFT JOIN usuarios rev ON em.id_instructor_revisor = rev.id_usuario
                WHERE em.id_excusa = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Obtiene excusas de un aprendiz específico
     */
    public function getByAprendiz(int $idAprendiz): array {
        $sql = "SELECT em.*,
                       CONCAT(rev.nombre, ' ', rev.apellido) AS nombre_revisor
                FROM {$this->table} em
                LEFT JOIN usuarios rev ON em.id_instructor_revisor = rev.id_usuario
                WHERE em.id_aprendiz = :id
                ORDER BY em.creado_en DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $idAprendiz]);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene excusas pendientes de revisión
     */
    public function getPendientes(): array {
        $sql = "SELECT em.*, 
                       u.nombre, u.apellido, u.num_documento,
                       f.codigo_ficha, f.nombre_programa
                FROM {$this->table} em
                INNER JOIN aprendices a ON em.id_aprendiz = a.id_aprendiz
                INNER JOIN usuarios u ON a.id_usuario = u.id_usuario
                INNER JOIN fichas f ON a.id_ficha = f.id_ficha
                WHERE em.estado = 'Pendiente'
                ORDER BY em.creado_en ASC";
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
                VALUES 
                    (:id_aprendiz, :id_ingreso, :fecha_inicio, :fecha_fin, :motivo, :archivo_adjunto)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':id_aprendiz'     => $data['id_aprendiz'],
            ':id_ingreso'      => $data['id_ingreso'] ?? null,
            ':fecha_inicio'    => $data['fecha_inicio'],
            ':fecha_fin'       => $data['fecha_fin'],
            ':motivo'          => $data['motivo'],
            ':archivo_adjunto' => $data['archivo_adjunto'],
        ]);
    }

    /**
     * Aprueba una excusa médica
     */
    public function aprobar(int $id, int $idRevisor, string $comentario = ''): bool {
        $sql = "UPDATE {$this->table} 
                SET estado = 'Aprobada', 
                    id_instructor_revisor = :revisor, 
                    comentario_revision = :comentario
                WHERE id_excusa = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':id'         => $id,
            ':revisor'    => $idRevisor,
            ':comentario' => $comentario,
        ]);
    }

    /**
     * Rechaza una excusa médica
     */
    public function rechazar(int $id, int $idRevisor, string $comentario = ''): bool {
        $sql = "UPDATE {$this->table} 
                SET estado = 'Rechazada', 
                    id_instructor_revisor = :revisor, 
                    comentario_revision = :comentario
                WHERE id_excusa = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':id'         => $id,
            ':revisor'    => $idRevisor,
            ':comentario' => $comentario,
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
}
