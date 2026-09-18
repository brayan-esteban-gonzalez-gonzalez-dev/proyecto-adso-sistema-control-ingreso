<?php
/**
 * AsistenciaController.php — Controlador de Asistencia
 * 
 * Gestiona el historial de asistencias, filtros y estadísticas.
 */
class AsistenciaController {
    private PDO $db;
    private IngresoAsistencia $model;
    private Aprendiz $aprendizModel;

    public function __construct(PDO $db) {
        $this->db = $db;
        
        // Cargar el modelo si no se cargó en index.php
        require_once ROOT_PATH . '/models/IngresoAsistencia.php';
        
        $this->model = new IngresoAsistencia($db);
        $this->aprendizModel = new Aprendiz($db);
    }

    /**
     * Muestra el historial de asistencias
     */
    public function historial(): void {
        $pageTitle = 'Historial de Asistencia';
        $currentAction = 'asistencia/historial';
        
        $ingresos = [];
        $estadisticas = [];

        if (Auth::isAdminOrInstructor()) {
            // Lógica para Administradores e Instructores
            $fecha = $_GET['fecha'] ?? date('Y-m-d');
            
            // Validar fecha
            Validator::reset();
            Validator::date($fecha, 'fecha');
            if (!Validator::isValid()) {
                $fecha = date('Y-m-d');
            }

            $ingresos = $this->model->getHistorial($fecha);
            
            require_once ROOT_PATH . '/views/asistencia/historial.php';
            
        } elseif (Auth::isAprendiz()) {
            // Lógica para el Aprendiz autenticado
            $idUsuario = $_SESSION['user_id'];
            $aprendizInfo = $this->aprendizModel->getByUsuario($idUsuario);
            
            if (!$aprendizInfo) {
                Auth::setFlash('error', 'No se encontró información del aprendiz.');
                header('Location: ' . BASE_URL);
                exit;
            }
            
            $idAprendiz = $aprendizInfo['id_aprendiz'];
            
            // Filtro de fechas por defecto: Mes actual
            $fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
            $fechaFin    = $_GET['fecha_fin'] ?? date('Y-m-t');
            
            // Validar fechas
            Validator::reset();
            Validator::date($fechaInicio, 'fecha de inicio');
            Validator::date($fechaFin, 'fecha de fin');
            
            if (!Validator::isValid()) {
                $fechaInicio = date('Y-m-01');
                $fechaFin    = date('Y-m-t');
            }

            $ingresos     = $this->model->getHistorialAprendiz($idAprendiz, $fechaInicio, $fechaFin);
            $estadisticas = $this->model->getEstadisticasAprendiz($idAprendiz, $fechaInicio, $fechaFin);
            
            require_once ROOT_PATH . '/views/asistencia/historial.php';
            
        } else {
            // Rol no reconocido
            header('Location: ' . BASE_URL);
            exit;
        }
    }
}
