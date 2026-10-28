<?php
declare(strict_types=1);

/**
 * FichaController.php — Controlador de Fichas
 *
 * CRUD de fichas de formación.
 * Nueva DB: tabla `ficha`, campos: codigo (INT), Programa_id, jornada_id, instructor_id, estado.
 */
class FichaController {
    private PDO $db;
    private Ficha $model;
    public function __construct(PDO $db) {
        $this->db    = $db;
        $this->model = new Ficha($db);
    }
    /**
     * Lista las fichas (todas para Administrador, asignadas para Instructor)
     */
    public function index(): void {
        Auth::requireAdmin();

        if (Auth::isInstructor()) {
            $fichas    = $this->model->getByInstructorAsignado((int) Auth::getUserId());
            $pageTitle = 'Mis Fichas Asignadas';
        } else {
            $fichas    = $this->model->getAll();
            $pageTitle = 'Gestión de Fichas';
        }

        require_once ROOT_PATH . '/views/fichas/index.php';
    }
    /**
     * Formulario de creación
     */
    public function crear(): void {
        Auth::requireAdminOnly();
        $usuarioModel = new Usuario($this->db);
        $instructores = $usuarioModel->getInstructores();
        $programas    = $this->model->getProgramas();
        $jornadas     = $this->model->getJornadas();
        $ficha        = null;
        $csrf_token   = Auth::generateCSRF();
        $pageTitle    = 'Nueva Ficha';
        require_once ROOT_PATH . '/views/fichas/form.php';
    }
    /**
     * Formulario de edición
     */
    public function editar(): void {
        Auth::requireAdminOnly();
        $id    = (int) ($_GET['id'] ?? 0);
        $ficha = $this->model->getById($id);

        if (!$ficha) {
            Auth::setFlash('error', 'Ficha no encontrada.');
            header('Location: ' . BASE_URL . '?action=fichas');
            exit;
        }

        $usuarioModel = new Usuario($this->db);
        $instructores = $usuarioModel->getInstructores();
        $programas    = $this->model->getProgramas();
        $jornadas     = $this->model->getJornadas();
        $csrf_token   = Auth::generateCSRF();
        $pageTitle    = 'Editar Ficha';
        require_once ROOT_PATH . '/views/fichas/form.php';
    }

    /**
     * Guarda una ficha
     */
    public function guardar(): void {
        Auth::requireAdminOnly();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '?action=fichas');
            exit;
        }

        if (!Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
            Auth::setFlash('error', 'Token de seguridad inválido.');
            header('Location: ' . BASE_URL . '?action=fichas');
            exit;
        }

        $id   = (int) ($_POST['id_ficha']   ?? 0);
        $data = [
            'codigo'        => (int) ($_POST['codigo_ficha']        ?? 0),
            'Programa_id'   => (int) ($_POST['Programa_id']         ?? 0),
            'jornada_id'    => (int) ($_POST['jornada_id']          ?? 0) ?: null,
            'instructor_id' => (int) ($_POST['id_instructor_lider'] ?? 0) ?: null,
            'estado'        => $_POST['estado'] ?? 'Activo',
        ];

        Validator::reset();
        Validator::required((string)$data['codigo'],        'código de ficha');
        Validator::required((string)$data['Programa_id'],   'programa');
        Validator::required((string)($data['instructor_id'] ?? ''), 'instructor líder');

        if (!Validator::isValid()) {
            Auth::setFlash('error', implode('<br>', Validator::getErrors()));
            $redirect = $id > 0 ? "fichas/editar&id={$id}" : 'fichas/crear';
            header('Location: ' . BASE_URL . "?action={$redirect}");
            exit;
        }

        try {
            if ($id > 0) {
                $this->model->update($id, $data);
                Auth::setFlash('success', 'Ficha actualizada correctamente.');
            } else {
                $this->model->create($data);
                Auth::setFlash('success', 'Ficha creada correctamente.');
            }
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate')) {
                Auth::setFlash('error', 'Ya existe una ficha con ese código.');
            } else {
                error_log('[SCIA][FichaController] Error al guardar ficha: ' . $e->getFile() . ':' . $e->getLine() . ' - ' . $e->getMessage());
                Auth::setFlash('error', 'Ocurrió un error al guardar la ficha. Por favor, inténtalo de nuevo.');
            }
        }

        header('Location: ' . BASE_URL . '?action=fichas');
        exit;
    }

    /**
     * Elimina una ficha
     */
    public function eliminar(): void {
        Auth::requireAdminOnly();
        $id = (int) ($_GET['id'] ?? 0);

        try {
            $this->model->delete($id);
            Auth::setFlash('success', 'Ficha eliminada correctamente.');
        } catch (\PDOException $e) {
            Auth::setFlash('error', 'No se puede eliminar la ficha porque tiene usuarios u horarios asociados.');
        }

        header('Location: ' . BASE_URL . '?action=fichas');
        exit;
    }

    /**
     * Muestra el listado de aprendices e instructores vinculados a una ficha
     */
    public function integrantes(): void {
        Auth::requireAdmin();
        $id    = (int) ($_GET['id'] ?? 0);
        $ficha = $this->model->getById($id);

        if (!$ficha) {
            Auth::setFlash('error', 'Ficha no encontrada.');
            header('Location: ' . BASE_URL . '?action=fichas');
            exit;
        }

        // Si es instructor, validar que la ficha le esté asignada (Anti-IDOR)
        if (Auth::isInstructor()) {
            $instructorId = (int) Auth::getUserId();
            if (!$this->model->instructorPerteneceAFicha($instructorId, $id)) {
                Auth::setFlash('error', 'No tienes permisos para consultar los integrantes de esta ficha.');
                header('Location: ' . BASE_URL . '?action=fichas');
                exit;
            }
        }

        $aprendices   = $this->model->getAprendicesPorFicha($id);
        $instructores = $this->model->getInstructoresPorFicha($id);
        $pageTitle    = 'Integrantes de la Ficha ' . ($ficha['codigo'] ?? '');
        require_once ROOT_PATH . '/views/fichas/integrantes.php';
    }
}
