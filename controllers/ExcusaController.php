<?php
/**
 * ExcusaController.php — Controlador de Excusas Médicas
 * 
 * Gestiona el envío, visualización y revisión de excusas médicas.
 */
class ExcusaController {
    private PDO $db;
    private ExcusaMedica $model;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->model = new ExcusaMedica($db);
    }

    /**
     * Lista excusas (admin: todas/pendientes, aprendiz: propias)
     */
    public function index(): void {
        Auth::requireLogin();

        if (Auth::isAdminOrInstructor()) {
            $filtro = $_GET['filtro'] ?? 'todas';
            if ($filtro === 'pendientes') {
                $excusas = $this->model->getPendientes();
            } else {
                $excusas = $this->model->getAll();
            }
            $pageTitle = 'Gestión de Excusas Médicas';
        } else {
            $aprendizModel = new Aprendiz($this->db);
            $aprendiz = $aprendizModel->getByUsuario(Auth::getUserId());
            $excusas = $aprendiz ? $this->model->getByAprendiz($aprendiz['id_aprendiz']) : [];
            $pageTitle = 'Mis Excusas Médicas';
        }

        require_once ROOT_PATH . '/views/excusas/index.php';
    }

    /**
     * Formulario para crear una excusa (solo aprendiz)
     */
    public function crear(): void {
        Auth::requireLogin();

        if (!Auth::isAprendiz()) {
            Auth::setFlash('error', 'Solo los aprendices pueden enviar excusas.');
            header('Location: ' . BASE_URL . '?action=excusas');
            exit;
        }

        $csrf_token = Auth::generateCSRF();
        $pageTitle = 'Nueva Excusa Médica';
        require_once ROOT_PATH . '/views/excusas/form.php';
    }

    /**
     * Guarda una excusa médica
     */
    public function guardar(): void {
        Auth::requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '?action=excusas');
            exit;
        }

        if (!Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
            Auth::setFlash('error', 'Token de seguridad inválido.');
            header('Location: ' . BASE_URL . '?action=excusas');
            exit;
        }

        $aprendizModel = new Aprendiz($this->db);
        $aprendiz = $aprendizModel->getByUsuario(Auth::getUserId());

        if (!$aprendiz) {
            Auth::setFlash('error', 'No se encontró perfil de aprendiz.');
            header('Location: ' . BASE_URL . '?action=excusas');
            exit;
        }

        // Validaciones
        Validator::reset();
        $fechaInicio = $_POST['fecha_inicio'] ?? '';
        $fechaFin    = $_POST['fecha_fin'] ?? '';
        $motivo      = trim($_POST['motivo'] ?? '');

        Validator::required($fechaInicio, 'fecha de inicio');
        Validator::required($fechaFin, 'fecha de fin');
        Validator::required($motivo, 'motivo');

        // Validar archivo adjunto
        if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            Validator::required('', 'archivo adjunto');
        } else {
            $allowedTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
            Validator::fileType($_FILES['archivo'], $allowedTypes, 'archivo adjunto');
            Validator::fileSize($_FILES['archivo'], 5 * 1024 * 1024, 'archivo adjunto'); // 5MB
        }

        if (!Validator::isValid()) {
            Auth::setFlash('error', implode('<br>', Validator::getErrors()));
            header('Location: ' . BASE_URL . '?action=excusas/crear');
            exit;
        }

        // Subir archivo
        $uploadDir = UPLOAD_PATH . 'excusas/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $extension = pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION);
        $nombreArchivo = 'excusa_' . $aprendiz['id_aprendiz'] . '_' . time() . '.' . $extension;
        $rutaArchivo = $uploadDir . $nombreArchivo;

        if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $rutaArchivo)) {
            Auth::setFlash('error', 'Error al subir el archivo.');
            header('Location: ' . BASE_URL . '?action=excusas/crear');
            exit;
        }

        // Guardar excusa
        $data = [
            'id_aprendiz'     => $aprendiz['id_aprendiz'],
            'id_ingreso'      => !empty($_POST['id_ingreso']) ? (int) $_POST['id_ingreso'] : null,
            'fecha_inicio'    => $fechaInicio,
            'fecha_fin'       => $fechaFin,
            'motivo'          => $motivo,
            'archivo_adjunto' => 'assets/uploads/excusas/' . $nombreArchivo,
        ];

        try {
            $this->model->create($data);
            Auth::setFlash('success', 'Excusa médica enviada correctamente. Pendiente de revisión.');
        } catch (\PDOException $e) {
            Auth::setFlash('error', 'Error al guardar la excusa: ' . $e->getMessage());
        }

        header('Location: ' . BASE_URL . '?action=excusas');
        exit;
    }

    /**
     * Muestra una excusa para revisión (admin/instructor)
     */
    public function revisar(): void {
        Auth::requireAdmin();
        $id = (int) ($_GET['id'] ?? 0);
        $excusa = $this->model->getById($id);

        if (!$excusa) {
            Auth::setFlash('error', 'Excusa no encontrada.');
            header('Location: ' . BASE_URL . '?action=excusas');
            exit;
        }

        $csrf_token = Auth::generateCSRF();
        $pageTitle = 'Revisar Excusa Médica';
        require_once ROOT_PATH . '/views/excusas/revisar.php';
    }

    /**
     * Procesa la aprobación o rechazo de una excusa
     */
    public function procesar(): void {
        Auth::requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '?action=excusas');
            exit;
        }

        if (!Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
            Auth::setFlash('error', 'Token de seguridad inválido.');
            header('Location: ' . BASE_URL . '?action=excusas');
            exit;
        }

        $idExcusa   = (int) ($_POST['id_excusa'] ?? 0);
        $accion     = $_POST['accion'] ?? '';
        $comentario = trim($_POST['comentario_revision'] ?? '');

        $excusa = $this->model->getById($idExcusa);
        if (!$excusa) {
            Auth::setFlash('error', 'Excusa no encontrada.');
            header('Location: ' . BASE_URL . '?action=excusas');
            exit;
        }

        try {
            if ($accion === 'aprobar') {
                $this->model->aprobar($idExcusa, Auth::getUserId(), $comentario);

                // Si la excusa tiene un ingreso asociado, marcarlo como justificado
                if ($excusa['id_ingreso']) {
                    $ingresoModel = new IngresoAsistencia($this->db);
                    $ingresoModel->justificar($excusa['id_ingreso']);
                }

                Auth::setFlash('success', 'Excusa aprobada correctamente.');
            } elseif ($accion === 'rechazar') {
                $this->model->rechazar($idExcusa, Auth::getUserId(), $comentario);
                Auth::setFlash('success', 'Excusa rechazada.');
            } else {
                Auth::setFlash('error', 'Acción no válida.');
            }
        } catch (\PDOException $e) {
            Auth::setFlash('error', 'Error al procesar la excusa: ' . $e->getMessage());
        }

        header('Location: ' . BASE_URL . '?action=excusas');
        exit;
    }
}
