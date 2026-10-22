<?php
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
}
