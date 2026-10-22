<?php
declare(strict_types=1);

/**
 * ExcusaController.php — Controlador de Excusas
 *
 * Gestiona el envío, visualización y revisión de excusas médicas.
 * Vincula de manera obligatoria cada excusa a una inasistencia previa real del aprendiz.
 */
class ExcusaController
{
    private PDO $db;
    private ExcusaMedica $model;
    private IngresoAsistencia $asistenciaModel;

    public function __construct(PDO $db)
    {
        $this->db              = $db;
        $this->model           = new ExcusaMedica($db);
        $this->asistenciaModel = new IngresoAsistencia($db);
    }

    /**
     * Lista excusas (admin/instructor: todas o pendientes; aprendiz: propias)
     */
    public function index(): void
    {
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
            $excusas   = $this->model->getByAprendiz(Auth::getUserId());
            $pageTitle = 'Mis Excusas Médicas';
        }

        require_once ROOT_PATH . '/views/excusas/index.php';
    }

    /**
     * Muestra el formulario para crear una excusa (solo aprendiz).
     * Consulta únicamente inasistencias reales del aprendiz sin excusa previa.
     */
    public function crear(): void
    {
        Auth::requireLogin();

        if (!Auth::isAprendiz()) {
            Auth::setFlash('error', 'Solo los aprendices pueden registrar excusas médicas.');
            header('Location: ' . BASE_URL . '?action=excusas');
            exit;
        }

        $idUsuario     = Auth::getUserId();
        $inasistencias = $this->asistenciaModel->obtenerInasistenciasSinExcusaPorAprendiz($idUsuario);
        $csrf_token    = Auth::generateCSRF();
        $pageTitle     = 'Nueva Excusa Médica';

        require_once ROOT_PATH . '/views/excusas/form.php';
    }

    /**
     * Alias compatible de crear()
     */
    public function create(): void
    {
        $this->crear();
    }

    /**
     * Alias compatible de crear()
     */
    public function form(): void
    {
        $this->crear();
    }

    /**
     * Guarda una nueva excusa médica vinculada a una inasistencia previa.
     */
    public function guardar(): void
    {
        Auth::requireLogin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '?action=excusas');
            exit;
        }

        if (!Auth::isAprendiz()) {
            Auth::setFlash('error', 'Solo los aprendices pueden enviar excusas médicas.');
            header('Location: ' . BASE_URL . '?action=excusas');
            exit;
        }

        if (!Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
            Auth::setFlash('error', 'Token de seguridad inválido. Por favor intente nuevamente.');
            header('Location: ' . BASE_URL . '?action=excusas/crear');
            exit;
        }

        $idUsuario = Auth::getUserId();

        // 1. Validar el ID de inasistencia
        $inasistenciaId = (int) ($_POST['ingreso_asistencia_id'] ?? $_POST['Inasistencia_id'] ?? 0);
        if ($inasistenciaId <= 0) {
            Auth::setFlash('error', 'Debe seleccionar una inasistencia válida a justificar.');
            header('Location: ' . BASE_URL . '?action=excusas/crear');
            exit;
        }

        // Verificar que la inasistencia pertenezca realmente al aprendiz en sesión
        $inasistencia = $this->asistenciaModel->verificarInasistenciaAprendiz($inasistenciaId, $idUsuario);
        if (!$inasistencia) {
            Auth::setFlash('error', 'La inasistencia seleccionada no existe o no corresponde a su usuario.');
            header('Location: ' . BASE_URL . '?action=excusas/crear');
            exit;
        }

        // 2. Validar motivo / descripción
        $motivo = trim($_POST['motivo'] ?? $_POST['descripcion'] ?? '');
        Validator::reset();
        Validator::required($motivo, 'motivo o descripción');
        Validator::minLength($motivo, 10, 'motivo o descripción');

        // 3. Validar archivo adjunto (Máx 2 MB, extensiones y MIME real con finfo_file)
        if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            Validator::required('', 'archivo adjunto');
        } else {
            $archivo      = $_FILES['archivo'];
            $maxBytes     = 2 * 1024 * 1024; // 2 MB
            $extPermitidas = ['pdf', 'jpg', 'jpeg', 'png'];
            $mimesPermitidos = [
                'application/pdf',
                'image/jpeg',
                'image/png',
            ];

            // Validar tamaño
            if ($archivo['size'] > $maxBytes) {
                Validator::required('', 'El archivo excede el tamaño máximo permitido de 2 MB');
            }

            // Validar extensión
            $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, $extPermitidas, true)) {
                Validator::required('', 'Formato de archivo no permitido. Solo se aceptan PDF, JPG y PNG');
            }

            // Validar tipo MIME real mediante finfo_file
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeReal = $finfo ? finfo_file($finfo, $archivo['tmp_name']) : '';
            if ($finfo) {
                finfo_close($finfo);
            }

            if (!in_array($mimeReal, $mimesPermitidos, true)) {
                Validator::required('', 'El contenido del archivo no coincide con un formato válido (PDF, JPG, PNG)');
            }
        }

        if (!Validator::isValid()) {
            Auth::setFlash('error', implode('<br>', Validator::getErrors()));
            header('Location: ' . BASE_URL . '?action=excusas/crear');
            exit;
        }

        // 4. Mover archivo de forma segura a carpeta protegida
        $uploadDir = UPLOAD_PATH . 'excusas/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $extension = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
        if ($extension === 'jpeg') {
            $extension = 'jpg';
        }
        $nombreArchivo = sprintf(
            'excusa_%d_%d_%d_%s.%s',
            $idUsuario,
            $inasistenciaId,
            time(),
            bin2hex(random_bytes(4)),
            $extension
        );
        $rutaFisica  = $uploadDir . $nombreArchivo;
        $rutaRelativa = 'assets/uploads/excusas/' . $nombreArchivo;

        if (!move_uploaded_file($_FILES['archivo']['tmp_name'], $rutaFisica)) {
            Auth::setFlash('error', 'Ocurrió un error al guardar el archivo adjunto en el servidor.');
            header('Location: ' . BASE_URL . '?action=excusas/crear');
            exit;
        }

        // 5. Guardar la excusa vinculada a la inasistencia
        try {
            $ok = $this->model->crear([
                'ingreso_asistencia_id' => $inasistenciaId,
                'usuario_id'            => $idUsuario,
                'descripcion'           => $motivo,
                'ruta_archivo'          => $rutaRelativa,
                'fecha'                 => $inasistencia['fecha'] ?? date('Y-m-d'),
            ]);

            if ($ok) {
                Auth::setFlash('success', 'Excusa médica registrada exitosamente. Queda en estado "En revisión".');
            } else {
                Auth::setFlash('error', 'No se pudo guardar la excusa médica.');
            }
        } catch (\PDOException $e) {
            Auth::setFlash('error', 'Error al registrar la excusa: ' . $e->getMessage());
        }

        header('Location: ' . BASE_URL . '?action=excusas');
        exit;
    }

    /**
     * Alias compatible de guardar()
     */
    public function store(): void
    {
        $this->guardar();
    }

    /**
     * Muestra el detalle de una excusa para revisión (Instructor o Administrador).
     */
    public function revisar(): void
    {
        Auth::requireLogin();

        if (!Auth::isAdminOrInstructor()) {
            Auth::setFlash('error', 'No tiene permisos para revisar excusas médicas.');
            header('Location: ' . BASE_URL . '?action=excusas');
            exit;
        }

        $id     = (int) ($_GET['id'] ?? 0);
        $excusa = $this->model->getById($id);

        if (!$excusa) {
            Auth::setFlash('error', 'Excusa médica no encontrada.');
            header('Location: ' . BASE_URL . '?action=excusas');
            exit;
        }

        $csrf_token = Auth::generateCSRF();
        $pageTitle  = 'Revisar Excusa Médica';
        require_once ROOT_PATH . '/views/excusas/revisar.php';
    }

    /**
     * Procesa la aprobación o rechazo de una excusa médica (Instructor o Administrador).
     */
    public function procesar(): void
    {
        Auth::requireLogin();

        if (!Auth::isAdminOrInstructor()) {
            Auth::setFlash('error', 'No tiene permisos para procesar excusas médicas.');
            header('Location: ' . BASE_URL . '?action=excusas');
            exit;
        }

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
            Auth::setFlash('error', 'Excusa médica no encontrada.');
            header('Location: ' . BASE_URL . '?action=excusas');
            exit;
        }

        $instructorId = Auth::getUserId();

        try {
            if ($accion === 'aprobar') {
                $this->model->aprobar($idExcusa, $instructorId, $comentario);
                Auth::setFlash('success', 'Excusa médica aprobada correctamente. La inasistencia ha sido justificada.');
            } elseif ($accion === 'rechazar') {
                $this->model->rechazar($idExcusa, $instructorId, $comentario);
                Auth::setFlash('success', 'Excusa médica rechazada.');
            } else {
                Auth::setFlash('error', 'Acción de revisión no válida.');
            }
        } catch (\PDOException $e) {
            Auth::setFlash('error', 'Error al procesar la excusa: ' . $e->getMessage());
        }

        header('Location: ' . BASE_URL . '?action=excusas');
        exit;
    }
}
