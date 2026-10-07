<?php
/**
 * FichaController.php — Controlador de Fichas
 *
 * CRUD de fichas de formación.
 * Nueva DB: tabla `Ficha`, campos: codigo (INT), Programa_id, jornada_id, instructor_id, estado.
 */
class FichaController {
    private PDO $db;
    private Ficha $model;

    public function __construct(PDO $db) {
        $this->db    = $db;
        $this->model = new Ficha($db);
    }

    /**
     * Lista todas las fichas
     */
    public function index(): void {
        Auth::requireAdmin();
        $fichas    = $this->model->getAll();
        $pageTitle = 'Gestión de Fichas';
        require_once ROOT_PATH . '/views/fichas/index.php';
    }

    /**
     * Formulario de creación
     */
    public function crear(): void {
        Auth::requireAdmin();
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
        Auth::requireAdmin();
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
        Auth::requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '?action=fichas');
            exit;
        }

        if (!Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
            Auth::setFlash('error', 'Token de seguridad inválido.');
            header('Location: ' . BASE_URL . '?action=fichas');
            exit;
        }

        $id             = (int) ($_POST['id_ficha'] ?? 0);
        $nombrePrograma = trim($_POST['nombre_programa'] ?? $_POST['Programa_id'] ?? '');

        $programaId = 0;
        if (!empty($nombrePrograma)) {
            if (is_numeric($nombrePrograma) && (int)$nombrePrograma > 0 && empty($_POST['nombre_programa'])) {
                $programaId = (int)$nombrePrograma;
            } else {
                $programaId = $this->model->findOrCreatePrograma($nombrePrograma);
            }
        }

        $data = [
            'codigo'        => (int) ($_POST['codigo_ficha']        ?? 0),
            'Programa_id'   => $programaId,
            'jornada_id'    => (int) ($_POST['jornada_id']          ?? 0) ?: null,
            'instructor_id' => (int) ($_POST['id_instructor_lider'] ?? 0) ?: null,
            'estado'        => $_POST['estado'] ?? 'Activo',
        ];

        Validator::reset();
        Validator::required((string)$data['codigo'],      'código de ficha');
        Validator::required((string)$data['Programa_id'], 'programa de formación');

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
                Auth::setFlash('error', 'Error al guardar: ' . $e->getMessage());
            }
        }

        header('Location: ' . BASE_URL . '?action=fichas');
        exit;
    }

    /**
     * Elimina una ficha
     */
    public function eliminar(): void {
        Auth::requireAdmin();
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
}
