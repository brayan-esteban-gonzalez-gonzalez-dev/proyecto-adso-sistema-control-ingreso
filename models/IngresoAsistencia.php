<?php

/**
 * IngresoAsistencia.php — Modelo de Asistencias
 *
 * Gestiona las tablas `asistencia` e `inasistencia` de la nueva DB.
 * Nueva DB: tabla `asistencia`, PK `id`, campos:
 *   fecha, hora_entrada, hora_salida, Usuario_id, Sesion_id,
 *   registrado_por, estado, codigo_llavero, minutos_retardo, minutos_anticipacion.
 */
class IngresoAsistencia
{
    private PDO $conn;
    private string $table = 'asistencia';

    public function __construct(PDO $db)
    {
        $this->conn = $db;
    }

    /**
     * Obtiene el historial de asistencia de una fecha (para Admin/Instructor)
     */
    public function getHistorial(string $fecha): array
    {
        $sql = "SELECT a.*,
                       u.nombre, u.apellido, u.identificacion,
                       f.codigo         AS codigo_ficha,
                       p.nombre         AS nombre_programa,
                       s.fecha          AS sesion_fecha,
                       s.hora_inicio    AS sesion_hora_inicio,
                       s.hora_fin       AS sesion_hora_fin
                FROM {$this->table} a
                INNER JOIN Usuario u  ON a.Usuario_id = u.id
                INNER JOIN sesion  s  ON a.Sesion_id  = s.id
                LEFT  JOIN Ficha   f  ON u.Ficha_id   = f.id
                LEFT  JOIN Programa p ON f.Programa_id = p.id
                WHERE a.fecha = :fecha
                ORDER BY a.fecha DESC, a.hora_entrada DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':fecha' => $fecha]);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene el historial de asistencia de un usuario (aprendiz) en un rango de fechas
     */
    public function getHistorialAprendiz(int $idUsuario, string $fechaInicio, string $fechaFin): array
    {
        $sql = "SELECT a.*,
                       s.hora_inicio AS sesion_hora_inicio,
                       s.hora_fin    AS sesion_hora_fin
                FROM {$this->table} a
                INNER JOIN sesion s ON a.Sesion_id = s.id
                WHERE a.Usuario_id = :id
                  AND a.fecha BETWEEN :inicio AND :fin
                ORDER BY a.fecha DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':id'     => $idUsuario,
            ':inicio' => $fechaInicio,
            ':fin'    => $fechaFin,
        ]);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene estadísticas de asistencia de un usuario en un rango de fechas
     */
    public function getEstadisticasAprendiz(int $idUsuario, string $fechaInicio, string $fechaFin): array
    {
        $sql = "SELECT
                    SUM(CASE WHEN a.minutos_retardo = 0 AND a.estado = 'Activo' THEN 1 ELSE 0 END) AS total_a_tiempo,
                    SUM(CASE WHEN a.minutos_retardo > 0 THEN 1 ELSE 0 END)                          AS total_retardos,
                    SUM(a.minutos_retardo)                                                           AS total_minutos_retardo,
                    COUNT(i.id)                                                                      AS total_inasistencias
                FROM {$this->table} a
                LEFT JOIN inasistencia i
                    ON i.Usuario_id = a.Usuario_id
                   AND i.fecha BETWEEN :inicio2 AND :fin2
                WHERE a.Usuario_id = :id
                  AND a.fecha BETWEEN :inicio AND :fin";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':id'     => $idUsuario,
            ':inicio' => $fechaInicio,
            ':fin'    => $fechaFin,
            ':inicio2' => $fechaInicio,
            ':fin2'   => $fechaFin,
        ]);

        $result = $stmt->fetch();
        return [
            'total_a_tiempo'        => (int)($result['total_a_tiempo']        ?? 0),
            'total_retardos'        => (int)($result['total_retardos']         ?? 0),
            'total_inasistencias'   => (int)($result['total_inasistencias']    ?? 0),
            'total_minutos_retardo' => (int)($result['total_minutos_retardo']  ?? 0),
        ];
    }

    /**
     * Registra una asistencia (entrada)
     */
    public function registrarEntrada(int $usuarioId, int $sesionId, int $registradoPor, string $codigoLlavero = null): bool
    {
        $sql = "INSERT INTO {$this->table}
                    (fecha, hora_entrada, Usuario_id, Sesion_id, registrado_por, codigo_llavero, estado)
                VALUES
                    (CURDATE(), CURTIME(), :usuario_id, :sesion_id, :registrado_por, :codigo_llavero, 'Activo')";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':usuario_id'      => $usuarioId,
            ':sesion_id'       => $sesionId,
            ':registrado_por'  => $registradoPor,
            ':codigo_llavero'  => $codigoLlavero,
        ]);
    }

    /**
     * Marca la salida de una asistencia
     */
    public function registrarSalida(int $asistenciaId): bool
    {
        $sql = "UPDATE {$this->table}
                SET hora_salida = CURTIME(), estado = 'Completado'
                WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $asistenciaId]);
    }

    /**
     * Marca una asistencia como justificada (compatible con ExcusaController)
     */
    public function justificar(int $asistenciaId): bool
    {
        // En la nueva DB no hay estado 'Justificado' en asistencia;
        // la justificación se gestiona a través de la tabla excusa/estado_excusa.
        // Este método queda como stub para compatibilidad.
        return true;
    }
}
