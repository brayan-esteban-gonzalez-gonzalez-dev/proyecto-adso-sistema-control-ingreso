<?php
/**
 * IngresoAsistencia.php — Modelo de Ingresos/Asistencia
 * 
 * Gestión de marcaciones de entrada/salida, diagnóstico de estado,
 * consultas de historial y reportes.
 */
class IngresoAsistencia {
    private PDO $conn;
    private string $table = 'ingresos_asistencia';

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    /**
     * Registra la entrada de un aprendiz
     */
    public function registrarEntrada(int $idAprendiz, string $fecha, string $horaEntrada, string $estado, int $minutosRetardo = 0): bool {
        $sql = "INSERT INTO {$this->table} (id_aprendiz, fecha, hora_entrada, estado, minutos_retardo)
                VALUES (:id_aprendiz, :fecha, :hora_entrada, :estado, :minutos_retardo)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':id_aprendiz'    => $idAprendiz,
            ':fecha'          => $fecha,
            ':hora_entrada'   => $horaEntrada,
            ':estado'         => $estado,
            ':minutos_retardo'=> $minutosRetardo,
        ]);
    }

    /**
     * Registra la salida de un aprendiz
     */
    public function registrarSalida(int $idIngreso, string $horaSalida, int $minutosSalidaAnticipada = 0): bool {
        $estado_update = '';
        $params = [
            ':id'           => $idIngreso,
            ':hora_salida'  => $horaSalida,
            ':minutos_sal'  => $minutosSalidaAnticipada,
        ];

        // Si hay salida anticipada y la entrada fue a tiempo, cambiar estado
        if ($minutosSalidaAnticipada > 0) {
            $sql = "UPDATE {$this->table}
                    SET hora_salida = :hora_salida,
                        minutos_salida_anticipada = :minutos_sal,
                        estado = CASE 
                            WHEN estado = 'A_Tiempo' THEN 'Salida_Temprana'
                            ELSE estado 
                        END
                    WHERE id_ingreso = :id";
        } else {
            $sql = "UPDATE {$this->table}
                    SET hora_salida = :hora_salida,
                        minutos_salida_anticipada = :minutos_sal
                    WHERE id_ingreso = :id";
        }

        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Obtiene el ingreso de hoy para un aprendiz
     */
    public function getIngresoHoy(int $idAprendiz): ?array {
        $sql = "SELECT * FROM {$this->table}
                WHERE id_aprendiz = :id_aprendiz AND fecha = CURDATE()
                ORDER BY id_ingreso DESC LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id_aprendiz' => $idAprendiz]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Obtiene el historial de un aprendiz con filtros opcionales
     */
    public function getByAprendiz(int $idAprendiz, ?string $fechaInicio = null, ?string $fechaFin = null): array {
        $sql = "SELECT * FROM {$this->table} WHERE id_aprendiz = :id_aprendiz";
        $params = [':id_aprendiz' => $idAprendiz];

        if ($fechaInicio) {
            $sql .= " AND fecha >= :fecha_inicio";
            $params[':fecha_inicio'] = $fechaInicio;
        }
        if ($fechaFin) {
            $sql .= " AND fecha <= :fecha_fin";
            $params[':fecha_fin'] = $fechaFin;
        }

        $sql .= " ORDER BY fecha DESC, hora_entrada DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene todos los ingresos de una fecha
     */
    public function getByFecha(string $fecha): array {
        $sql = "SELECT i.*, 
                       u.nombre, u.apellido, u.num_documento,
                       a.codigo_rfid, f.codigo_ficha
                FROM {$this->table} i
                INNER JOIN aprendices a ON i.id_aprendiz = a.id_aprendiz
                INNER JOIN usuarios u ON a.id_usuario = u.id_usuario
                INNER JOIN fichas f ON a.id_ficha = f.id_ficha
                WHERE i.fecha = :fecha
                ORDER BY i.hora_entrada";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':fecha' => $fecha]);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene un ingreso por su ID
     */
    public function getById(int $id): ?array {
        $sql = "SELECT i.*, 
                       u.nombre, u.apellido, u.num_documento,
                       a.codigo_rfid, f.codigo_ficha, f.nombre_programa
                FROM {$this->table} i
                INNER JOIN aprendices a ON i.id_aprendiz = a.id_aprendiz
                INNER JOIN usuarios u ON a.id_usuario = u.id_usuario
                INNER JOIN fichas f ON a.id_ficha = f.id_ficha
                WHERE i.id_ingreso = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Genera reporte de asistencia con filtros
     */
    public function getReporte(?int $idFicha = null, ?string $fechaInicio = null, ?string $fechaFin = null, ?string $estado = null): array {
        $sql = "SELECT i.*, 
                       u.nombre, u.apellido, u.num_documento,
                       a.codigo_rfid, a.id_ficha,
                       f.codigo_ficha, f.nombre_programa
                FROM {$this->table} i
                INNER JOIN aprendices a ON i.id_aprendiz = a.id_aprendiz
                INNER JOIN usuarios u ON a.id_usuario = u.id_usuario
                INNER JOIN fichas f ON a.id_ficha = f.id_ficha
                WHERE 1=1";
        $params = [];

        if ($idFicha) {
            $sql .= " AND a.id_ficha = :id_ficha";
            $params[':id_ficha'] = $idFicha;
        }
        if ($fechaInicio) {
            $sql .= " AND i.fecha >= :fecha_inicio";
            $params[':fecha_inicio'] = $fechaInicio;
        }
        if ($fechaFin) {
            $sql .= " AND i.fecha <= :fecha_fin";
            $params[':fecha_fin'] = $fechaFin;
        }
        if ($estado) {
            $sql .= " AND i.estado = :estado";
            $params[':estado'] = $estado;
        }

        $sql .= " ORDER BY i.fecha DESC, u.apellido, u.nombre";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene estadísticas de retardos por aprendiz
     */
    public function getEstadisticasRetardo(int $idAprendiz, ?string $fechaInicio = null, ?string $fechaFin = null): array {
        $sql = "SELECT 
                    COUNT(CASE WHEN estado = 'Retardo' THEN 1 END) AS total_retardos,
                    COALESCE(SUM(minutos_retardo), 0) AS total_minutos_retardo,
                    COUNT(CASE WHEN estado = 'Inasistencia' THEN 1 END) AS total_inasistencias,
                    COUNT(CASE WHEN estado = 'Salida_Temprana' THEN 1 END) AS total_salidas_tempranas,
                    COUNT(CASE WHEN estado = 'A_Tiempo' THEN 1 END) AS total_a_tiempo,
                    COUNT(CASE WHEN estado = 'Justificado' THEN 1 END) AS total_justificados,
                    COUNT(*) AS total_registros
                FROM {$this->table}
                WHERE id_aprendiz = :id_aprendiz";
        $params = [':id_aprendiz' => $idAprendiz];

        if ($fechaInicio) {
            $sql .= " AND fecha >= :fecha_inicio";
            $params[':fecha_inicio'] = $fechaInicio;
        }
        if ($fechaFin) {
            $sql .= " AND fecha <= :fecha_fin";
            $params[':fecha_fin'] = $fechaFin;
        }

        $stmt = $this->conn->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    /**
     * Cuenta ingresos de hoy
     */
    public function countHoy(): int {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE fecha = CURDATE()";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return (int) $stmt->fetch()['total'];
    }

    /**
     * Cuenta retardos de hoy
     */
    public function countRetardosHoy(): int {
        $sql = "SELECT COUNT(*) as total FROM {$this->table} WHERE fecha = CURDATE() AND estado = 'Retardo'";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return (int) $stmt->fetch()['total'];
    }

    /**
     * Limpia registros "A_Tiempo" de un día específico (mantenimiento)
     */
    public function limpiarATiempo(string $fecha): int {
        $sql = "DELETE FROM {$this->table} WHERE fecha = :fecha AND estado = 'A_Tiempo'";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':fecha' => $fecha]);
        return $stmt->rowCount();
    }

    /**
     * Genera registros de inasistencia para aprendices sin marcación
     */
    public function generarInasistencias(string $fecha): int {
        // Obtener día de la semana
        $dias = ['Monday' => 'Lunes', 'Tuesday' => 'Martes', 'Wednesday' => 'Miercoles',
                 'Thursday' => 'Jueves', 'Friday' => 'Viernes', 'Saturday' => 'Sabado'];
        $dayName = date('l', strtotime($fecha));
        if (!isset($dias[$dayName])) return 0;
        $diaSemana = $dias[$dayName];

        // Buscar aprendices que tengan horario ese día y no tengan registro
        $sql = "INSERT INTO {$this->table} (id_aprendiz, fecha, estado)
                SELECT a.id_aprendiz, :fecha, 'Inasistencia'
                FROM aprendices a
                INNER JOIN usuarios u ON a.id_usuario = u.id_usuario
                INNER JOIN horarios h ON a.id_ficha = h.id_ficha AND h.dia_semana = :dia
                WHERE u.estado = 'Activo'
                AND a.id_aprendiz NOT IN (
                    SELECT id_aprendiz FROM {$this->table} WHERE fecha = :fecha2
                )";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':fecha' => $fecha, ':dia' => $diaSemana, ':fecha2' => $fecha]);
        return $stmt->rowCount();
    }

    /**
     * Actualiza el estado de un ingreso a 'Justificado'
     */
    public function justificar(int $idIngreso): bool {
        $sql = "UPDATE {$this->table} SET estado = 'Justificado' WHERE id_ingreso = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $idIngreso]);
    }

    /**
     * Obtiene los ingresos de hoy con detalle para la vista de marcación
     */
    public function getIngresosHoyDetalle(): array {
        $sql = "SELECT i.*, 
                       u.nombre, u.apellido, u.num_documento,
                       f.codigo_ficha
                FROM {$this->table} i
                INNER JOIN aprendices a ON i.id_aprendiz = a.id_aprendiz
                INNER JOIN usuarios u ON a.id_usuario = u.id_usuario
                INNER JOIN fichas f ON a.id_ficha = f.id_ficha
                WHERE i.fecha = CURDATE()
                ORDER BY i.hora_entrada DESC
                LIMIT 20";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
