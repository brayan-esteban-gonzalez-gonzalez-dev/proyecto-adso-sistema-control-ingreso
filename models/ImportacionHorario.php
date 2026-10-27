<?php
/**
 * ImportacionHorario.php — Modelo de Auditoría de Importaciones
 *
 * Registra cada importación de horario (RF-HOR-08): quién, cuándo, tipo,
 * archivo y totales del reporte. Tabla `importacion_horario`.
 */
class ImportacionHorario {
    private PDO $conn;
    private string $table = 'importacion_horario';

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    /**
     * Registra una importación finalizada.
     *
     * @param array $datos usuario_id, tipo, archivo, anio, codigo_ficha,
     *                     instructor_id, totales, errores, permutas_confirmadas
     */
    public function registrar(array $datos): ?int {
        $sql = "INSERT INTO {$this->table}
                    (usuario_id, tipo, archivo, anio, codigo_ficha, instructor_id,
                     creadas, actualizadas, omitidas, canceladas, protegidas,
                     errores, permutas_confirmadas)
                VALUES
                    (:usuario_id, :tipo, :archivo, :anio, :codigo_ficha, :instructor_id,
                     :creadas, :actualizadas, :omitidas, :canceladas, :protegidas,
                     :errores, :permutas_confirmadas)";
        $stmt = $this->conn->prepare($sql);
        $ok = $stmt->execute([
            ':usuario_id'          => (int) ($datos['usuario_id'] ?? 0),
            ':tipo'                => $datos['tipo'] ?? 'ficha',
            ':archivo'             => $datos['archivo'] ?? '',
            ':anio'                => (int) ($datos['anio'] ?? date('Y')),
            ':codigo_ficha'        => $datos['codigo_ficha'] ?? null,
            ':instructor_id'       => isset($datos['instructor_id']) ? (int) $datos['instructor_id'] : null,
            ':creadas'             => (int) ($datos['creadas'] ?? 0),
            ':actualizadas'        => (int) ($datos['actualizadas'] ?? 0),
            ':omitidas'            => (int) ($datos['omitidas'] ?? 0),
            ':canceladas'          => (int) ($datos['canceladas'] ?? 0),
            ':protegidas'          => (int) ($datos['protegidas'] ?? 0),
            ':errores'             => isset($datos['errores']) && $datos['errores'] !== ''
                                        ? (is_array($datos['errores']) ? implode("\n", $datos['errores']) : (string) $datos['errores'])
                                        : null,
            ':permutas_confirmadas' => !empty($datos['permutas_confirmadas']) ? 1 : 0,
        ]);

        return $ok ? (int) $this->conn->lastInsertId() : null;
    }

    /**
     * Lista las últimas importaciones para consulta del administrador.
     */
    public function getRecientes(int $limite = 50): array {
        $limite = max(1, min(200, $limite));
        $sql = "SELECT i.*,
                       CONCAT(u.nombre, ' ', u.apellido) AS nombre_usuario
                FROM {$this->table} i
                INNER JOIN Usuario u ON i.usuario_id = u.id
                ORDER BY i.fecha_importacion DESC
                LIMIT {$limite}";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function getById(int $id): ?array {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
