<?php
/**
 * IngresoAsistencia.php — Modelo de Ingresos y Asistencia
 * 
 * Gestiona la tabla `ingresos_asistencia`.
 */
class IngresoAsistencia {
    private PDO $conn;
    private string $table = 'ingresos_asistencia';

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    /**
     * Obtiene el historial de asistencia general con filtros (para Admin/Instructor)
     */
    public function getHistorial(string $fecha): array {
        $sql = "SELECT ia.*, 
                       u.nombre, u.apellido, u.num_documento,
                       f.codigo_ficha
                FROM {$this->table} ia
                INNER JOIN aprendices a ON ia.id_aprendiz = a.id_aprendiz
                INNER JOIN usuarios u ON a.id_usuario = u.id_usuario
                INNER JOIN fichas f ON a.id_ficha = f.id_ficha
                WHERE ia.fecha = :fecha
                ORDER BY ia.fecha DESC, ia.hora_entrada DESC";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':fecha' => $fecha]);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene el historial de un aprendiz específico en un rango de fechas
     */
    public function getHistorialAprendiz(int $idAprendiz, string $fechaInicio, string $fechaFin): array {
        $sql = "SELECT ia.*
                FROM {$this->table} ia
                WHERE ia.id_aprendiz = :id 
                  AND ia.fecha BETWEEN :inicio AND :fin
                ORDER BY ia.fecha DESC";
                
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':id'     => $idAprendiz,
            ':inicio' => $fechaInicio,
            ':fin'    => $fechaFin
        ]);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene estadísticas de asistencia de un aprendiz en un rango de fechas
     */
    public function getEstadisticasAprendiz(int $idAprendiz, string $fechaInicio, string $fechaFin): array {
        $sql = "SELECT 
                    SUM(CASE WHEN estado = 'A_Tiempo' THEN 1 ELSE 0 END) as total_a_tiempo,
                    SUM(CASE WHEN estado = 'Retardo' THEN 1 ELSE 0 END) as total_retardos,
                    SUM(CASE WHEN estado = 'Inasistencia' THEN 1 ELSE 0 END) as total_inasistencias,
                    SUM(minutos_retardo) as total_minutos_retardo
                FROM {$this->table}
                WHERE id_aprendiz = :id 
                  AND fecha BETWEEN :inicio AND :fin";
                  
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':id'     => $idAprendiz,
            ':inicio' => $fechaInicio,
            ':fin'    => $fechaFin
        ]);
        
        $result = $stmt->fetch();
        return [
            'total_a_tiempo'        => (int)($result['total_a_tiempo'] ?? 0),
            'total_retardos'        => (int)($result['total_retardos'] ?? 0),
            'total_inasistencias'   => (int)($result['total_inasistencias'] ?? 0),
            'total_minutos_retardo' => (int)($result['total_minutos_retardo'] ?? 0),
        ];
    }
}
