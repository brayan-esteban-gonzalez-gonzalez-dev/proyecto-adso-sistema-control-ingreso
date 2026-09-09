<?php
/**
 * DashboardController.php — Controlador de Dashboard
 * 
 * Renderiza el panel principal según el rol del usuario.
 */
class DashboardController {
    private PDO $db;

    public function __construct(PDO $db) {
        $this->db = $db;
    }

    /**
     * Muestra el dashboard según el rol
     */
    public function index(): void {
        Auth::requireLogin();

        if (Auth::isAdminOrInstructor()) {
            $this->adminDashboard();
        } else {
            $this->aprendizDashboard();
        }
    }

    /**
     * Dashboard para administrador/instructor
     */
    private function adminDashboard(): void {
        $aprendizModel  = new Aprendiz($this->db);
        $fichaModel     = new Ficha($this->db);
        $ingresoModel   = new IngresoAsistencia($this->db);
        $excusaModel    = new ExcusaMedica($this->db);

        // Estadísticas
        $stats = [
            'total_aprendices'   => $aprendizModel->count(),
            'total_fichas'       => $fichaModel->count(),
            'ingresos_hoy'       => $ingresoModel->countHoy(),
            'retardos_hoy'       => $ingresoModel->countRetardosHoy(),
            'excusas_pendientes' => $excusaModel->countPendientes(),
        ];

        // Últimos ingresos de hoy
        $ultimosIngresos = $ingresoModel->getIngresosHoyDetalle();

        // Excusas pendientes
        $excusasPendientes = $excusaModel->getPendientes();

        $pageTitle = 'Panel de Administración';
        require_once ROOT_PATH . '/views/dashboard/admin.php';
    }

    /**
     * Dashboard para aprendiz
     */
    private function aprendizDashboard(): void {
        $aprendizModel = new Aprendiz($this->db);
        $ingresoModel  = new IngresoAsistencia($this->db);
        $excusaModel   = new ExcusaMedica($this->db);
        $horarioModel  = new Horario($this->db);

        // Obtener datos del aprendiz
        $aprendiz = $aprendizModel->getByUsuario(Auth::getUserId());

        if (!$aprendiz) {
            Auth::setFlash('error', 'No se encontró perfil de aprendiz asociado.');
            $pageTitle = 'Mi Panel';
            require_once ROOT_PATH . '/views/dashboard/aprendiz.php';
            return;
        }

        // Ingreso de hoy
        $ingresoHoy = $ingresoModel->getIngresoHoy($aprendiz['id_aprendiz']);

        // Horario de hoy
        $horarioHoy = $horarioModel->getHorarioHoy($aprendiz['id_ficha']);

        // Estadísticas del aprendiz
        $estadisticas = $ingresoModel->getEstadisticasRetardo($aprendiz['id_aprendiz']);

        // Últimos registros
        $ultimosRegistros = $ingresoModel->getByAprendiz($aprendiz['id_aprendiz']);
        $ultimosRegistros = array_slice($ultimosRegistros, 0, 10);

        // Excusas del aprendiz
        $excusas = $excusaModel->getByAprendiz($aprendiz['id_aprendiz']);

        $pageTitle = 'Mi Panel';
        require_once ROOT_PATH . '/views/dashboard/aprendiz.php';
    }
}
