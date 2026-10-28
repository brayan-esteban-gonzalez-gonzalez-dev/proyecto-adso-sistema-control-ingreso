<?php
declare(strict_types=1);

/**
 * Muestra la vista principal con estadísticas del sistema o portal del aprendiz.
 */
class DashboardController {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Muestra el dashboard con estadísticas adaptadas al rol
     */
    public function index(): void {
        Auth::requireLogin();

        if (Auth::isAprendiz()) {
            $pageTitle = 'Portal del Aprendiz — Mi Asistencia';
            require_once ROOT_PATH . '/models/Reporte.php';

            $reporteModel     = new Reporte($this->db);
            $usuarioId        = (int) Auth::getUserId();
            $metricasAprendiz = $reporteModel->getMetricasAprendiz($usuarioId);
            $stats            = [];
        } else {
            $pageTitle = 'Dashboard';

            $usuarioModel  = new Usuario($this->db);
            $fichaModel    = new Ficha($this->db);
            $aprendizModel = new Aprendiz($this->db);
            $excusaModel   = new ExcusaMedica($this->db);

            $stats = [
                'total_usuarios'     => $usuarioModel->count(),
                'total_instructores' => $usuarioModel->countByRol(2),
                'total_fichas'       => $fichaModel->count(),
                'total_aprendices'   => $aprendizModel->count(),
                'excusas_pendientes' => $excusaModel->countPendientes(),
            ];
            $metricasAprendiz = null;
        }

        require_once ROOT_PATH . '/views/dashboard/index.php';
    }

    /**
     * Endpoint JSON con los indicadores para las gráficas del dashboard.
     *
     * Ruta: ?action=dashboard/datosGraficos
     * Seguridad: Auth::requireLogin() se ejecuta en Router antes de llegar aquí.
     * Respuesta: JSON con claves asistencia_por_mes, estado_excusas y puntualidad.
     */
    public function datosGraficos(): void
    {
        // Si es aprendiz, filtra solo sus propios datos
        $usuarioId = Auth::isAprendiz() ? Auth::getUserId() : null;

        $reporte = new Reporte($this->db);
        $datos   = $reporte->indicadores($usuarioId);

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($datos, JSON_UNESCAPED_UNICODE);
        exit;
    }
}
