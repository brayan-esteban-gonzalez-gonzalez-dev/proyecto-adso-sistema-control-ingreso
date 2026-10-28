<?php
declare(strict_types=1);

/**
 * Reporte.php — Consultas de reportes institucionales e indicadores.
 */
class Reporte {
    private PDO $conn;

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    public function asistenciaPorFicha(int $fichaId, string $inicio, string $fin): array {
        $sql = "SELECT u.id,
                       CONCAT(u.apellido, ' ', u.nombre) AS aprendiz,
                       u.identificacion,
                       COUNT(DISTINCT s.id) AS sesiones,
                       COUNT(DISTINCT a.id) AS asistencias,
                       COUNT(DISTINCT CASE 
                           WHEN ee.estado = 'Aprobada' THEN NULL 
                           ELSE i.id 
                       END) AS inasistencias,
                       COUNT(DISTINCT CASE 
                           WHEN ee.estado = 'Aprobada' THEN i.id 
                           ELSE NULL 
                       END) AS inasistencias_justificadas,
                       COUNT(DISTINCT CASE WHEN a.minutos_retardo > 0 THEN a.id END) AS retardos
                FROM Usuario u
                LEFT JOIN sesion s
                       ON s.Ficha_id = u.Ficha_id
                      AND s.fecha BETWEEN :inicio AND :fin
                      AND s.estado <> 'Cancelado'
                LEFT JOIN asistencia a
                       ON a.Sesion_id = s.id
                      AND a.Usuario_id = u.id
                LEFT JOIN inasistencia i
                       ON i.Sesion_id = s.id
                      AND i.Usuario_id = u.id
                LEFT JOIN excusa e
                       ON e.Inasistencia_id = i.id
                LEFT JOIN estado_excusa ee
                       ON ee.Excusa_id = e.id
                WHERE u.Rol_id = 3
                  AND u.Ficha_id = :ficha
                GROUP BY u.id, u.apellido, u.nombre, u.identificacion
                ORDER BY u.apellido, u.nombre";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':inicio' => $inicio,
            ':fin'    => $fin,
            ':ficha'  => $fichaId,
        ]);
        return $stmt->fetchAll();
    }

    public function detalleAprendiz(int $usuarioId, int $fichaId, string $inicio, string $fin): array {
        if ($fichaId > 0) {
            $sql = "SELECT s.fecha,
                           s.hora_inicio,
                           s.hora_fin,
                           COALESCE(c.nombre, 'Sin competencia') AS competencia,
                           a.hora_entrada,
                           a.hora_salida,
                           a.minutos_retardo,
                           a.id AS asistencia_id,
                           i.id AS inasistencia_id
                    FROM sesion s
                    LEFT JOIN competencia c ON c.id = s.Competencia_id
                    LEFT JOIN asistencia a ON a.Sesion_id = s.id AND a.Usuario_id = :uid
                    LEFT JOIN inasistencia i ON i.Sesion_id = s.id AND i.Usuario_id = :uid_i
                    WHERE s.Ficha_id = :ficha
                      AND s.fecha BETWEEN :inicio AND :fin
                      AND s.estado <> 'Cancelado'
                    ORDER BY s.fecha, s.hora_inicio";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':uid'    => $usuarioId,
                ':uid_i'  => $usuarioId,
                ':ficha'  => $fichaId,
                ':inicio' => $inicio,
                ':fin'    => $fin,
            ]);
            return $stmt->fetchAll();
        }

        $sql = "SELECT s.fecha,
                       s.hora_inicio,
                       s.hora_fin,
                       COALESCE(c.nombre, 'Sin competencia') AS competencia,
                       a.hora_entrada,
                       a.hora_salida,
                       a.minutos_retardo,
                       a.id AS asistencia_id,
                       NULL AS inasistencia_id
                FROM asistencia a
                INNER JOIN sesion s ON s.id = a.Sesion_id
                LEFT JOIN competencia c ON c.id = s.Competencia_id
                WHERE a.Usuario_id = :uid
                  AND a.fecha BETWEEN :inicio AND :fin
                UNION ALL
                SELECT i.fecha,
                       s.hora_inicio,
                       s.hora_fin,
                       COALESCE(c.nombre, 'Sin competencia') AS competencia,
                       NULL, NULL, NULL, NULL, i.id
                FROM inasistencia i
                INNER JOIN sesion s ON s.id = i.Sesion_id
                LEFT JOIN competencia c ON c.id = s.Competencia_id
                WHERE i.Usuario_id = :uid_i
                  AND i.fecha BETWEEN :inicio_i AND :fin_i
                ORDER BY fecha, hora_inicio";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':uid'      => $usuarioId,
            ':inicio'   => $inicio,
            ':fin'      => $fin,
            ':uid_i'    => $usuarioId,
            ':inicio_i' => $inicio,
            ':fin_i'    => $fin,
        ]);
        return $stmt->fetchAll();
    }

    public function excusas(string $inicio, string $fin, int $fichaId = 0): array {
        $sql = "SELECT e.fecha,
                       CONCAT(u.apellido, ' ', u.nombre) AS aprendiz,
                       u.identificacion,
                       COALESCE(CAST(f.codigo AS CHAR), '') AS codigo_ficha,
                       e.motivo,
                       CASE
                           WHEN EXISTS (
                               SELECT 1 FROM estado_excusa ee
                               WHERE ee.Excusa_id = e.id AND ee.estado = 'Rechazada'
                           ) THEN 'Rechazada'
                           WHEN EXISTS (
                               SELECT 1 FROM estado_excusa ee
                               WHERE ee.Excusa_id = e.id AND ee.estado = 'Aprobada'
                           ) THEN 'Aprobada'
                           ELSE 'Pendiente'
                       END AS estado
                FROM excusa e
                INNER JOIN Usuario u ON u.id = e.Usuario_id
                LEFT JOIN Ficha f ON f.id = u.Ficha_id
                WHERE e.fecha BETWEEN :inicio AND :fin
                  AND (:filtrar = 0 OR u.Ficha_id = :ficha)
                ORDER BY e.fecha DESC, u.apellido, u.nombre";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':inicio'  => $inicio,
            ':fin'     => $fin,
            ':filtrar' => $fichaId > 0 ? 1 : 0,
            ':ficha'   => $fichaId > 0 ? $fichaId : 0,
        ]);
        return $stmt->fetchAll();
    }

    public function indicadores(?int $usuarioId = null): array {
        return [
            'asistencia_por_mes' => $this->asistenciaPorMes($usuarioId),
            'estado_excusas'     => $this->estadoExcusas($usuarioId),
            'puntualidad'        => $this->puntualidad30($usuarioId),
        ];
    }

    private function asistenciaPorMes(?int $usuarioId): array {
        $inicio = new DateTime('first day of this month');
        $inicio->modify('-5 months');
        $fin = new DateTime('first day of next month');

        $labels = [];
        $cursor = clone $inicio;
        for ($i = 0; $i < 6; $i++) {
            $labels[] = $cursor->format('Y-m');
            $cursor->modify('+1 month');
        }

        $asistencias = $this->contarPorMes('asistencia', $inicio->format('Y-m-d'), $fin->format('Y-m-d'), $usuarioId);
        $inasistencias = $this->contarPorMes('inasistencia', $inicio->format('Y-m-d'), $fin->format('Y-m-d'), $usuarioId);

        return [
            'labels'        => array_map([$this, 'etiquetaMes'], $labels),
            'asistencias'   => array_map(fn($mes) => $asistencias[$mes] ?? 0, $labels),
            'inasistencias' => array_map(fn($mes) => $inasistencias[$mes] ?? 0, $labels),
        ];
    }

    private function contarPorMes(string $tabla, string $desde, string $hasta, ?int $usuarioId): array {
        if ($tabla === 'inasistencia') {
            $filtro = $usuarioId ? ' AND t.Usuario_id = :uid' : '';
            $sql = "SELECT DATE_FORMAT(t.fecha, '%Y-%m') AS mes,
                           COUNT(DISTINCT CASE 
                               WHEN ee.estado = 'Aprobada' THEN NULL 
                               ELSE t.id 
                           END) AS total
                    FROM inasistencia t
                    LEFT JOIN excusa e ON e.Inasistencia_id = t.id
                    LEFT JOIN estado_excusa ee ON ee.Excusa_id = e.id
                    WHERE t.fecha >= :desde AND t.fecha < :hasta{$filtro}
                    GROUP BY mes";
        } else {
            $filtro = $usuarioId ? ' AND Usuario_id = :uid' : '';
            $sql = "SELECT DATE_FORMAT(fecha, '%Y-%m') AS mes, COUNT(*) AS total
                    FROM {$tabla}
                    WHERE fecha >= :desde AND fecha < :hasta{$filtro}
                    GROUP BY mes";
        }
        $stmt = $this->conn->prepare($sql);
        $params = [':desde' => $desde, ':hasta' => $hasta];
        if ($usuarioId) {
            $params[':uid'] = $usuarioId;
        }
        $stmt->execute($params);
        $mapa = [];
        foreach ($stmt->fetchAll() as $fila) {
            $mapa[$fila['mes']] = (int) $fila['total'];
        }
        return $mapa;
    }

    private function estadoExcusas(?int $usuarioId): array {
        $filtro = $usuarioId ? ' WHERE e.Usuario_id = :uid' : '';
        $sql = "SELECT
                    SUM(CASE
                        WHEN NOT EXISTS (
                            SELECT 1 FROM estado_excusa ee WHERE ee.Excusa_id = e.id
                        ) THEN 1 ELSE 0
                    END) AS pendientes,
                    SUM(CASE
                        WHEN EXISTS (
                            SELECT 1 FROM estado_excusa ee
                            WHERE ee.Excusa_id = e.id AND ee.estado = 'Rechazada'
                        ) THEN 1 ELSE 0
                    END) AS rechazadas,
                    SUM(CASE
                        WHEN EXISTS (
                            SELECT 1 FROM estado_excusa ee
                            WHERE ee.Excusa_id = e.id AND ee.estado = 'Aprobada'
                        )
                        AND NOT EXISTS (
                            SELECT 1 FROM estado_excusa ee
                            WHERE ee.Excusa_id = e.id AND ee.estado = 'Rechazada'
                        ) THEN 1 ELSE 0
                    END) AS aprobadas
                FROM excusa e{$filtro}";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute($usuarioId ? [':uid' => $usuarioId] : []);
        $fila = $stmt->fetch() ?: [];
        return [
            'aprobadas'  => (int) ($fila['aprobadas'] ?? 0),
            'rechazadas' => (int) ($fila['rechazadas'] ?? 0),
            'pendientes' => (int) ($fila['pendientes'] ?? 0),
        ];
    }

    private function puntualidad30(?int $usuarioId): array {
        $hasta = new DateTime('today');
        $desde = (clone $hasta)->modify('-29 days');
        $filtro = $usuarioId ? ' AND Usuario_id = :uid' : '';
        $sql = "SELECT fecha,
                       COUNT(*) AS total,
                       SUM(CASE WHEN minutos_retardo = 0 THEN 1 ELSE 0 END) AS a_tiempo
                FROM asistencia
                WHERE fecha BETWEEN :desde AND :hasta{$filtro}
                GROUP BY fecha";
        $stmt = $this->conn->prepare($sql);
        $params = [
            ':desde' => $desde->format('Y-m-d'),
            ':hasta' => $hasta->format('Y-m-d'),
        ];
        if ($usuarioId) {
            $params[':uid'] = $usuarioId;
        }
        $stmt->execute($params);
        $mapa = [];
        foreach ($stmt->fetchAll() as $fila) {
            $total = (int) $fila['total'];
            $mapa[$fila['fecha']] = $total > 0
                ? round(((int) $fila['a_tiempo'] / $total) * 100, 1)
                : null;
        }

        $labels = [];
        $valores = [];
        $cursor = clone $desde;
        while ($cursor <= $hasta) {
            $clave = $cursor->format('Y-m-d');
            $labels[] = $cursor->format('d/m');
            $valores[] = $mapa[$clave] ?? null;
            $cursor->modify('+1 day');
        }

        return [
            'labels'     => $labels,
            'porcentaje' => $valores,
        ];
    }

    private function etiquetaMes(string $ym): string {
        $nombres = [1 => 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
        [$anio, $mes] = explode('-', $ym);
        return ($nombres[(int) $mes] ?? $ym) . ' ' . $anio;
    }

    /**
     * Calcula métricas completas, historial de marcas y excusas médicas para el portal del aprendiz.
     *
     * @param int $usuarioId ID del usuario (aprendiz)
     * @return array Métricas, historial de marcas y excusas
     */
    public function getMetricasAprendiz(int $usuarioId): array
    {
        // 1. Obtener datos del aprendiz y ficha
        $sqlAprendiz = "SELECT u.id, u.nombre, u.apellido, u.identificacion, u.Ficha_id,
                               f.codigo AS codigo_ficha,
                               COALESCE(p.nombre, 'Sin programa asignado') AS programa_nombre
                        FROM usuario u
                        LEFT JOIN ficha f ON f.id = u.Ficha_id
                        LEFT JOIN programa p ON p.id = f.Programa_id
                        WHERE u.id = :uid AND u.Rol_id = 3
                        LIMIT 1";
        $stmtA = $this->conn->prepare($sqlAprendiz);
        $stmtA->execute([':uid' => $usuarioId]);
        $aprendiz = $stmtA->fetch() ?: [];

        $fichaId = (int) ($aprendiz['Ficha_id'] ?? 0);

        // 2. Sesiones programadas de la ficha
        $totalSesiones = 0;
        if ($fichaId > 0) {
            $sqlSesiones = "SELECT COUNT(*) AS total
                            FROM sesion s
                            WHERE s.Ficha_id = :ficha_id
                              AND s.estado <> 'Cancelado'";
            $stmtS = $this->conn->prepare($sqlSesiones);
            $stmtS->execute([':ficha_id' => $fichaId]);
            $totalSesiones = (int) ($stmtS->fetchColumn() ?: 0);
        }

        // 3. Métricas de asistencias (a tiempo, retardos, minutos)
        $sqlAsistencias = "SELECT 
                               COUNT(*) AS total_asistencias,
                               SUM(CASE WHEN minutos_retardo = 0 THEN 1 ELSE 0 END) AS asistencias_a_tiempo,
                               SUM(CASE WHEN minutos_retardo > 0 THEN 1 ELSE 0 END) AS retardos,
                               COALESCE(SUM(minutos_retardo), 0) AS total_minutos_retardo
                           FROM asistencia
                           WHERE Usuario_id = :uid";
        $stmtAst = $this->conn->prepare($sqlAsistencias);
        $stmtAst->execute([':uid' => $usuarioId]);
        $rowAst = $stmtAst->fetch() ?: [];

        $totalAsistencias   = (int) ($rowAst['total_asistencias'] ?? 0);
        $asistenciasATiempo = (int) ($rowAst['asistencias_a_tiempo'] ?? 0);
        $retardos           = (int) ($rowAst['retardos'] ?? 0);
        $minutosRetardo     = (int) ($rowAst['total_minutos_retardo'] ?? 0);

        // 4. Métricas de inasistencias (totales, justificadas con excusa aprobada)
        $sqlInasistencias = "SELECT 
                                COUNT(DISTINCT i.id) AS total_inasistencias,
                                COUNT(DISTINCT CASE WHEN ee.estado = 'Aprobada' THEN i.id ELSE NULL END) AS inasistencias_justificadas,
                                COUNT(DISTINCT CASE WHEN ee.estado = 'Aprobada' THEN NULL ELSE i.id END) AS inasistencias_injustificadas
                             FROM inasistencia i
                             LEFT JOIN excusa e ON e.Inasistencia_id = i.id
                             LEFT JOIN estado_excusa ee ON ee.Excusa_id = e.id
                             WHERE i.Usuario_id = :uid";
        $stmtInast = $this->conn->prepare($sqlInasistencias);
        $stmtInast->execute([':uid' => $usuarioId]);
        $rowInast = $stmtInast->fetch() ?: [];

        $totalInasistencias        = (int) ($rowInast['total_inasistencias'] ?? 0);
        $inasistenciasJustificadas = (int) ($rowInast['inasistencias_justificadas'] ?? 0);
        $inasistenciasInjustificadas = (int) ($rowInast['inasistencias_injustificadas'] ?? 0);

        // 5. Cálculo del porcentaje de asistencia general
        $baseSesiones = max($totalSesiones, $totalAsistencias + $totalInasistencias);
        $porcentajeAsistencia = $baseSesiones > 0
            ? round(($totalAsistencias / $baseSesiones) * 100, 1)
            : 100.0;
        if ($porcentajeAsistencia > 100.0) {
            $porcentajeAsistencia = 100.0;
        }

        // 6. Historial detallado de marcas (asistencias + inasistencias unificadas)
        $sqlHistorial = "SELECT 
                            'asistencia' AS tipo,
                            a.id AS registro_id,
                            a.fecha,
                            a.hora_entrada,
                            a.hora_salida,
                            a.minutos_retardo,
                            CASE 
                                WHEN a.minutos_retardo > 0 THEN 'Retardo'
                                ELSE 'A tiempo'
                            END AS estado_marca,
                            COALESCE(c.nombre, 'Formación Técnica') AS competencia,
                            s.hora_inicio AS sesion_hora_inicio,
                            s.hora_fin AS sesion_hora_fin,
                            NULL AS excusa_estado
                         FROM asistencia a
                         LEFT JOIN sesion s ON s.id = a.Sesion_id
                         LEFT JOIN competencia c ON c.id = s.Competencia_id
                         WHERE a.Usuario_id = :uid_a
                         
                         UNION ALL
                         
                         SELECT 
                            'inasistencia' AS tipo,
                            i.id AS registro_id,
                            i.fecha,
                            NULL AS hora_entrada,
                            NULL AS hora_salida,
                            0 AS minutos_retardo,
                            CASE 
                                WHEN ee.estado = 'Aprobada' THEN 'Justificada'
                                WHEN ee.estado = 'Rechazada' THEN 'Injustificada'
                                WHEN e.id IS NOT NULL THEN 'En revisión'
                                ELSE 'Inasistencia'
                            END AS estado_marca,
                            COALESCE(c.nombre, 'Formación Técnica') AS competencia,
                            s.hora_inicio AS sesion_hora_inicio,
                            s.hora_fin AS sesion_hora_fin,
                            ee.estado AS excusa_estado
                         FROM inasistencia i
                         LEFT JOIN sesion s ON s.id = i.Sesion_id
                         LEFT JOIN competencia c ON c.id = s.Competencia_id
                         LEFT JOIN excusa e ON e.Inasistencia_id = i.id
                         LEFT JOIN estado_excusa ee ON ee.Excusa_id = e.id
                         WHERE i.Usuario_id = :uid_i
                         
                         ORDER BY fecha DESC, hora_entrada DESC";
        $stmtH = $this->conn->prepare($sqlHistorial);
        $stmtH->execute([
            ':uid_a' => $usuarioId,
            ':uid_i' => $usuarioId,
        ]);
        $historialMarcas = $stmtH->fetchAll();

        // 7. Listado de excusas médicas radicadas por el aprendiz
        $sqlExcusas = "SELECT 
                          e.id,
                          e.fecha,
                          e.motivo,
                          e.evidencia,
                          e.Inasistencia_id,
                          e.Asistencia_id,
                          COALESCE(i.fecha, e.fecha) AS fecha_inasistencia,
                          CASE 
                              WHEN EXISTS (
                                  SELECT 1 FROM estado_excusa ee 
                                  WHERE ee.Excusa_id = e.id AND ee.estado = 'Rechazada'
                              ) THEN 'Rechazada'
                              WHEN EXISTS (
                                  SELECT 1 FROM estado_excusa ee 
                                  WHERE ee.Excusa_id = e.id AND ee.estado = 'Aprobada'
                              ) THEN 'Aprobada'
                              ELSE 'Pendiente'
                          END AS estado_excusa,
                          (SELECT ee.respuesta FROM estado_excusa ee WHERE ee.Excusa_id = e.id ORDER BY ee.id DESC LIMIT 1) AS respuesta_instructor,
                          (SELECT ee.fecha FROM estado_excusa ee WHERE ee.Excusa_id = e.id ORDER BY ee.id DESC LIMIT 1) AS fecha_respuesta
                       FROM excusa e
                       LEFT JOIN inasistencia i ON i.id = e.Inasistencia_id
                       WHERE e.Usuario_id = :uid
                       ORDER BY e.fecha DESC, e.id DESC";
        $stmtE = $this->conn->prepare($sqlExcusas);
        $stmtE->execute([':uid' => $usuarioId]);
        $excusas = $stmtE->fetchAll();

        return [
            'aprendiz'                     => $aprendiz,
            'total_sesiones'               => $totalSesiones,
            'base_sesiones'                => $baseSesiones,
            'total_asistencias'            => $totalAsistencias,
            'asistencias_a_tiempo'         => $asistenciasATiempo,
            'retardos'                     => $retardos,
            'total_minutos_retardo'        => $minutosRetardo,
            'total_inasistencias'          => $totalInasistencias,
            'inasistencias_justificadas'   => $inasistenciasJustificadas,
            'inasistencias_injustificadas' => $inasistenciasInjustificadas,
            'porcentaje_asistencia'        => $porcentajeAsistencia,
            'historial_marcas'             => $historialMarcas,
            'excusas'                      => $excusas,
        ];
    }
}

