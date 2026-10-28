<?php
declare(strict_types=1);

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
        // 1. Estadísticas de asistencia
        $sqlAsistencia = "SELECT
                    SUM(CASE WHEN a.minutos_retardo = 0 AND a.estado = 'Activo' THEN 1 ELSE 0 END) AS total_a_tiempo,
                    SUM(CASE WHEN a.minutos_retardo > 0 THEN 1 ELSE 0 END)                          AS total_retardos,
                    SUM(a.minutos_retardo)                                                           AS total_minutos_retardo
                FROM {$this->table} a
                WHERE a.Usuario_id = :id
                  AND a.fecha BETWEEN :inicio AND :fin";

        $stmtA = $this->conn->prepare($sqlAsistencia);
        $stmtA->execute([
            ':id'     => $idUsuario,
            ':inicio' => $fechaInicio,
            ':fin'    => $fechaFin,
        ]);
        $resA = $stmtA->fetch() ?: [];

        // 2. Estadísticas de inasistencias (excluyendo justificadas con excusa aprobada)
        $sqlInasistencia = "SELECT
                    COUNT(DISTINCT CASE 
                        WHEN ee.estado = 'Aprobada' THEN NULL 
                        ELSE i.id 
                    END) AS total_inasistencias,
                    COUNT(DISTINCT CASE 
                        WHEN ee.estado = 'Aprobada' THEN i.id 
                        ELSE NULL 
                    END) AS inasistencias_justificadas
                FROM inasistencia i
                LEFT JOIN excusa e ON e.Inasistencia_id = i.id
                LEFT JOIN estado_excusa ee ON ee.Excusa_id = e.id
                WHERE i.Usuario_id = :id
                  AND i.fecha BETWEEN :inicio AND :fin";

        $stmtI = $this->conn->prepare($sqlInasistencia);
        $stmtI->execute([
            ':id'     => $idUsuario,
            ':inicio' => $fechaInicio,
            ':fin'    => $fechaFin,
        ]);
        $resI = $stmtI->fetch() ?: [];

        return [
            'total_a_tiempo'             => (int)($resA['total_a_tiempo']            ?? 0),
            'total_retardos'             => (int)($resA['total_retardos']             ?? 0),
            'total_inasistencias'        => (int)($resI['total_inasistencias']        ?? 0),
            'inasistencias_justificadas' => (int)($resI['inasistencias_justificadas'] ?? 0),
            'total_minutos_retardo'      => (int)($resA['total_minutos_retardo']      ?? 0),
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
     * Registra una asistencia con cálculo de retardo.
     *
     * Calcula los minutos de retardo comparando la hora actual con la hora base
     * (hora_inicio de la sesión) + 10 minutos de tolerancia.
     *
     * @return array{ok: bool, estado: string, minutos_retardo: int, mensaje: string}
     */
    public function registrarEntradaConRetardo(
        int    $usuarioId,
        int    $sesionId,
        int    $registradoPor,
        string $horaInicioSesion,
        ?string $codigoLlavero = null,
        ?string $aperturaReal = null
    ): array {
        $ahora       = new \DateTime();
        $horaBase    = new \DateTime($horaInicioSesion);
        
        // RF-HOR-42: El retardo de 10 minutos se cuenta desde la mayor entre la hora de inicio del bloque y la apertura real
        if (!empty($aperturaReal)) {
            $horaApertura = new \DateTime($aperturaReal);
            if ($horaApertura > $horaBase) {
                $horaBase = $horaApertura;
            }
        }

        $tolerancia  = (clone $horaBase)->modify('+10 minutes');

        $minutosRetardo = 0;
        $estadoTexto    = 'Asistió';

        if ($ahora > $tolerancia) {
            $diff           = $tolerancia->diff($ahora);
            $minutosRetardo = ($diff->h * 60) + $diff->i;
            $estadoTexto    = 'Retardo';
        }

        $sql = "INSERT INTO {$this->table}
                    (fecha, hora_entrada, Usuario_id, Sesion_id, registrado_por,
                     codigo_llavero, estado, minutos_retardo)
                VALUES
                    (CURDATE(), CURTIME(), :usuario_id, :sesion_id, :registrado_por,
                     :codigo_llavero, 'Activo', :minutos_retardo)";
        $stmt = $this->conn->prepare($sql);
        $ok   = $stmt->execute([
            ':usuario_id'      => $usuarioId,
            ':sesion_id'       => $sesionId,
            ':registrado_por'  => $registradoPor,
            ':codigo_llavero'  => $codigoLlavero,
            ':minutos_retardo' => $minutosRetardo,
        ]);

        if (!$ok) {
            return [
                'ok'              => false,
                'estado'          => 'Error',
                'minutos_retardo' => 0,
                'mensaje'         => 'No se pudo registrar la asistencia.',
            ];
        }

        $msg = $minutosRetardo > 0
            ? "Retardo de {$minutosRetardo} minuto(s)."
            : 'Asistencia registrada a tiempo.';

        return [
            'ok'              => true,
            'estado'          => $estadoTexto,
            'minutos_retardo' => $minutosRetardo,
            'mensaje'         => $msg,
        ];
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
     * Verifica si un usuario ya tiene un registro de asistencia en una sesión específica.
     */
    public function existeRegistroEnSesion(int $usuarioId, int $sesionId): bool
    {
        $sql = "SELECT COUNT(*) AS total
                FROM {$this->table}
                WHERE Usuario_id = :usuario_id
                  AND Sesion_id  = :sesion_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':sesion_id'  => $sesionId,
        ]);
        return ((int) $stmt->fetch()['total']) > 0;
    }

    /**
     * Obtiene las marcaciones de hoy para una sesión específica.
     * Usado para la tabla en tiempo real de la vista RFID.
     */
    public function getMarcacionesPorSesion(int $sesionId): array
    {
        $sql = "SELECT a.*,
                       u.nombre, u.apellido, u.identificacion,
                       f.codigo AS codigo_ficha
                FROM {$this->table} a
                INNER JOIN Usuario u ON a.Usuario_id = u.id
                LEFT  JOIN Ficha f   ON u.Ficha_id   = f.id
                WHERE a.Sesion_id = :sesion_id
                ORDER BY a.hora_entrada DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':sesion_id' => $sesionId]);
        return $stmt->fetchAll();
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

    /**
     * Obtiene los registros de inasistencia del aprendiz que aún no posean
     * una excusa asociada (sin excusa previa o cuya excusa no esté aprobada/en revisión).
     *
     * @param int $usuarioId ID del usuario con rol Aprendiz
     * @return array Listado de inasistencias con datos de ficha, programa, sesión y competencia
     */
    public function obtenerInasistenciasSinExcusaPorAprendiz(int $usuarioId): array
    {
        $sql = "SELECT i.id,
                       i.fecha,
                       i.Sesion_id,
                       i.Ficha_id,
                       f.codigo         AS codigo_ficha,
                       p.nombre         AS nombre_programa,
                       s.hora_inicio    AS sesion_hora_inicio,
                       s.hora_fin       AS sesion_hora_fin,
                       COALESCE(c.nombre, 'Sesión formativa') AS nombre_competencia
                FROM inasistencia i
                INNER JOIN Ficha f ON i.Ficha_id = f.id
                LEFT JOIN Programa p ON f.Programa_id = p.id
                LEFT JOIN sesion s ON i.Sesion_id = s.id
                LEFT JOIN competencia c ON s.Competencia_id = c.id
                WHERE i.Usuario_id = :usuarioId
                  AND NOT EXISTS (
                      SELECT 1 FROM excusa e
                      LEFT JOIN estado_excusa ee ON ee.Excusa_id = e.id
                      WHERE e.Inasistencia_id = i.id
                        AND (ee.estado IS NULL OR ee.estado = 'Aprobada')
                  )
                ORDER BY i.fecha DESC, s.hora_inicio DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':usuarioId' => $usuarioId]);
        return $stmt->fetchAll();
    }

    /**
     * Verifica que una inasistencia pertenezca a un aprendiz específico.
     *
     * @param int $inasistenciaId ID de la inasistencia
     * @param int $usuarioId ID del aprendiz logueado
     * @return array|null Datos de la inasistencia o null si no pertenece al aprendiz
     */
    public function verificarInasistenciaAprendiz(int $inasistenciaId, int $usuarioId): ?array
    {
        $sql = "SELECT i.*,
                       f.codigo         AS codigo_ficha,
                       p.nombre         AS nombre_programa,
                       s.hora_inicio    AS sesion_hora_inicio,
                       s.hora_fin       AS sesion_hora_fin,
                       COALESCE(c.nombre, 'Sesión formativa') AS nombre_competencia
                FROM inasistencia i
                INNER JOIN Ficha f ON i.Ficha_id = f.id
                LEFT JOIN Programa p ON f.Programa_id = p.id
                LEFT JOIN sesion s ON i.Sesion_id = s.id
                LEFT JOIN competencia c ON s.Competencia_id = c.id
                WHERE i.id = :id AND i.Usuario_id = :usuarioId";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':id'        => $inasistenciaId,
            ':usuarioId' => $usuarioId,
        ]);
        $res = $stmt->fetch();
        return $res ?: null;
    }

    /**
     * Actualiza el estado de una inasistencia de forma resiliente
     * (solo si la columna 'estado' existe en la tabla).
     *
     * @param int $inasistenciaId ID de la inasistencia
     * @param string $estado 'Injustificada' | 'En revisión' | 'Justificada'
     * @return bool True si se actualizó, false en caso contrario
     */
    public function actualizarEstadoInasistencia(int $inasistenciaId, string $estado): bool
    {
        try {
            $check = $this->conn->query("SHOW COLUMNS FROM inasistencia LIKE 'estado'");
            if ($check && $check->rowCount() > 0) {
                $sql = "UPDATE inasistencia SET estado = :estado WHERE id = :id";
                $stmt = $this->conn->prepare($sql);
                return $stmt->execute([
                    ':estado' => $estado,
                    ':id'     => $inasistenciaId,
                ]);
            }
        } catch (\PDOException $e) {
            // Silencioso si la columna no existe aún
        }
        return false;
    }

    /**
     * Busca un aprendiz activo por su número de documento (identificacion)
     * y valida su pertenencia estricta a la ficha de la sesión activa.
     *
     * @param string $documento Número de identificación del aprendiz
     * @param int $fichaIdSesion ID de la ficha de la sesión activa
     * @return array{ok: bool, error?: string, mensaje: string, aprendiz?: array}
     */
    public function buscarAprendizPorDocumento(string $documento, int $fichaIdSesion): array
    {
        $documento = trim($documento);
        if ($documento === '') {
            return [
                'ok'      => false,
                'error'   => 'empty_document',
                'mensaje' => 'El número de documento no puede estar vacío.',
            ];
        }

        $sql = "SELECT u.id, u.identificacion AS documento, u.nombre, u.apellido,
                       CONCAT(u.nombre, ' ', u.apellido) AS nombre_completo,
                       u.Ficha_id, u.codigo_llavero AS rfid_tag, u.estado,
                       f.codigo AS codigo_ficha
                FROM usuario u
                LEFT JOIN ficha f ON u.Ficha_id = f.id
                WHERE u.identificacion = :documento
                  AND u.Rol_id = 3
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':documento' => $documento]);
        $aprendiz = $stmt->fetch();

        if (!$aprendiz) {
            return [
                'ok'      => false,
                'error'   => 'not_found',
                'mensaje' => "Aprendiz con documento \"{$documento}\" no encontrado o no tiene rol de Aprendiz.",
            ];
        }

        if ($aprendiz['estado'] !== 'Activo') {
            return [
                'ok'      => false,
                'error'   => 'inactive',
                'mensaje' => "El aprendiz {$aprendiz['nombre_completo']} se encuentra en estado \"{$aprendiz['estado']}\".",
            ];
        }

        if ((int)$aprendiz['Ficha_id'] !== $fichaIdSesion) {
            $fichaCod = $aprendiz['codigo_ficha'] ?? 'desconocida';
            return [
                'ok'       => false,
                'error'    => 'wrong_ficha',
                'mensaje'  => "El aprendiz {$aprendiz['nombre_completo']} (Doc: {$aprendiz['documento']}) no pertenece a la ficha en sesión activa (está matriculado en Ficha {$fichaCod}).",
                'aprendiz' => $aprendiz,
            ];
        }

        return [
            'ok'       => true,
            'mensaje'  => 'Aprendiz localizado con éxito.',
            'aprendiz' => $aprendiz,
        ];
    }

    /**
     * Busca un aprendiz activo por su ID primario de usuario
     * y valida su pertenencia estricta a la ficha de la sesión activa.
     *
     * @param int $aprendizId ID del usuario aprendiz
     * @param int $fichaIdSesion ID de la ficha de la sesión activa
     * @return array{ok: bool, error?: string, mensaje: string, aprendiz?: array}
     */
    public function buscarAprendizPorId(int $aprendizId, int $fichaIdSesion): array
    {
        if ($aprendizId <= 0) {
            return [
                'ok'      => false,
                'error'   => 'invalid_id',
                'mensaje' => 'ID de aprendiz inválido.',
            ];
        }

        $sql = "SELECT u.id, u.identificacion AS documento, u.nombre, u.apellido,
                       CONCAT(u.nombre, ' ', u.apellido) AS nombre_completo,
                       u.Ficha_id, u.codigo_llavero AS rfid_tag, u.estado,
                       f.codigo AS codigo_ficha
                FROM usuario u
                LEFT JOIN ficha f ON u.Ficha_id = f.id
                WHERE u.id = :id
                  AND u.Rol_id = 3
                LIMIT 1";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $aprendizId]);
        $aprendiz = $stmt->fetch();

        if (!$aprendiz) {
            return [
                'ok'      => false,
                'error'   => 'not_found',
                'mensaje' => 'Aprendiz no encontrado en el sistema.',
            ];
        }

        if ($aprendiz['estado'] !== 'Activo') {
            return [
                'ok'      => false,
                'error'   => 'inactive',
                'mensaje' => "El aprendiz {$aprendiz['nombre_completo']} se encuentra en estado \"{$aprendiz['estado']}\".",
            ];
        }

        if ((int)$aprendiz['Ficha_id'] !== $fichaIdSesion) {
            $fichaCod = $aprendiz['codigo_ficha'] ?? 'desconocida';
            return [
                'ok'       => false,
                'error'    => 'wrong_ficha',
                'mensaje'  => "El aprendiz {$aprendiz['nombre_completo']} (Doc: {$aprendiz['documento']}) no pertenece a la ficha en sesión activa (está matriculado en Ficha {$fichaCod}).",
                'aprendiz' => $aprendiz,
            ];
        }

        return [
            'ok'       => true,
            'mensaje'  => 'Aprendiz localizado con éxito.',
            'aprendiz' => $aprendiz,
        ];
    }

    /**
     * Obtiene la nómina completa de aprendices de una ficha con su estado
     * en la sesión activa actual (Pendiente, Presente, Retardo).
     *
     * @param int $fichaId ID de la ficha
     * @param int $sesionId ID de la sesión activa
     * @return array Lista de aprendices con estado de asistencia en la sesión
     */
    public function getNominaSesionActiva(int $fichaId, int $sesionId): array
    {
        $sql = "SELECT 
                    u.id,
                    u.identificacion AS documento,
                    u.nombre,
                    u.apellido,
                    CONCAT(u.nombre, ' ', u.apellido) AS nombre_completo,
                    u.codigo_llavero AS rfid_tag,
                    a.id AS asistencia_id,
                    a.hora_entrada,
                    a.hora_salida,
                    COALESCE(a.minutos_retardo, 0) AS minutos_retardo,
                    CASE 
                        WHEN a.id IS NULL THEN 'Pendiente'
                        WHEN a.minutos_retardo > 0 THEN 'Retardo'
                        ELSE 'Presente'
                    END AS estado_asistencia
                FROM usuario u
                LEFT JOIN asistencia a 
                    ON a.Usuario_id = u.id 
                   AND a.Sesion_id  = :sesion_id
                WHERE u.Ficha_id = :ficha_id
                  AND u.Rol_id   = 3
                  AND u.estado   = 'Activo'
                ORDER BY u.apellido ASC, u.nombre ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':ficha_id'  => $fichaId,
            ':sesion_id' => $sesionId,
        ]);
        return $stmt->fetchAll();
    }
}
