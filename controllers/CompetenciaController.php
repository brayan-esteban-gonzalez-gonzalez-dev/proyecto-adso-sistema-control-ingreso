<?php
/**
 * CompetenciaController.php — Controlador de Competencias
 *
 * CRUD de competencias vinculadas a un programa y a uno o más instructores.
 */
class CompetenciaController {
    private PDO $db;
    private Competencia $model;

    public function __construct(PDO $db) {
        $this->db    = $db;
        $this->model = new Competencia($db);
    }

    public function index(): void {
        Auth::requireAdmin();
        $programaId = isset($_GET['programa_id']) && $_GET['programa_id'] !== '' ? (int) $_GET['programa_id'] : null;
        if ($programaId !== null && $programaId <= 0) {
            $programaId = null;
        }
        $competencias = $this->model->getAll($programaId);
        $programas    = (new Programa($this->db))->getAll();
        $pageTitle    = 'Gestión de Competencias';
        require_once ROOT_PATH . '/views/competencias/index.php';
    }

    public function crear(): void {
        Auth::requireAdmin();
        $competencia = null;
        $programas   = (new Programa($this->db))->getAll();
        $instructores = (new Usuario($this->db))->getInstructores();
        $seleccionados = [];
        $csrf_token  = Auth::generateCSRF();
        $pageTitle   = 'Nueva Competencia';
        require_once ROOT_PATH . '/views/competencias/form.php';
    }

    public function editar(): void {
        Auth::requireAdmin();
        $id          = (int) ($_GET['id'] ?? 0);
        $competencia = $this->model->getById($id);

        if (!$competencia) {
            Auth::setFlash('error', 'Competencia no encontrada.');
            header('Location: ' . BASE_URL . '?action=competencias');
            exit;
        }

        $programas     = (new Programa($this->db))->getAll();
        $instructores  = (new Usuario($this->db))->getInstructores();
        $seleccionados = array_map(fn(array $u): int => (int) $u['id'], $competencia['instructores'] ?? []);
        $csrf_token    = Auth::generateCSRF();
        $pageTitle     = 'Editar Competencia';
        require_once ROOT_PATH . '/views/competencias/form.php';
    }

    public function guardar(): void {
        Auth::requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '?action=competencias');
            exit;
        }

        if (!Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
            Auth::setFlash('error', 'Token de seguridad inválido.');
            header('Location: ' . BASE_URL . '?action=competencias');
            exit;
        }

        $id = (int) ($_POST['id'] ?? 0);
        $instructorIds = $this->instructoresValidos($_POST['instructores'] ?? []);
        $data = [
            'Programa_id'   => (int) ($_POST['Programa_id'] ?? 0),
            'Instructor_id' => $instructorIds[0] ?? null,
            'nombre'        => trim($_POST['nombre'] ?? ''),
            'descripcion'   => trim($_POST['descripcion'] ?? ''),
        ];

        Validator::reset();
        Validator::required($data['nombre'], 'nombre');
        Validator::maxLength($data['nombre'], 100, 'nombre');
        Validator::required((string) $data['Programa_id'], 'programa');
        if ($data['descripcion'] !== '') {
            Validator::maxLength($data['descripcion'], 2000, 'descripción');
        }

        if ($data['Programa_id'] <= 0 || !(new Programa($this->db))->getById($data['Programa_id'])) {
            Auth::setFlash('error', 'Selecciona un programa válido.');
            $this->redirectForm($id);
        }

        if (!Validator::isValid()) {
            Auth::setFlash('error', implode('<br>', Validator::getErrors()));
            $this->redirectForm($id);
        }

        if ($this->model->existeNombre($data['Programa_id'], $data['nombre'], $id)) {
            Auth::setFlash('error', 'Ese programa ya tiene una competencia con el mismo nombre.');
            $this->redirectForm($id);
        }

        try {
            $this->db->beginTransaction();
            if ($id > 0) {
                if (!$this->model->getById($id)) {
                    $this->db->rollBack();
                    Auth::setFlash('error', 'Competencia no encontrada.');
                    header('Location: ' . BASE_URL . '?action=competencias');
                    exit;
                }
                $this->model->update($id, $data);
                $this->model->syncInstructores($id, $instructorIds);
                Auth::setFlash('success', 'Competencia actualizada correctamente.');
            } else {
                $nuevoId = $this->model->create($data);
                $this->model->syncInstructores($nuevoId, $instructorIds);
                Auth::setFlash('success', 'Competencia creada correctamente.');
            }
            $this->db->commit();
        } catch (\PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if (str_contains($e->getMessage(), 'Duplicate')) {
                Auth::setFlash('error', 'Ese programa ya tiene una competencia con el mismo nombre.');
            } else {
                Auth::setFlash('error', 'Error al guardar la competencia.');
            }
        }

        header('Location: ' . BASE_URL . '?action=competencias');
        exit;
    }

    public function eliminar(): void {
        Auth::requireAdmin();
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0 || !$this->model->getById($id)) {
            Auth::setFlash('error', 'Competencia no encontrada.');
            header('Location: ' . BASE_URL . '?action=competencias');
            exit;
        }

        if ($this->model->countSesiones($id) > 0) {
            Auth::setFlash('error', 'No se puede eliminar la competencia porque tiene sesiones de horario asociadas.');
            header('Location: ' . BASE_URL . '?action=competencias');
            exit;
        }

        try {
            $this->model->delete($id);
            Auth::setFlash('success', 'Competencia eliminada correctamente.');
        } catch (\PDOException $e) {
            Auth::setFlash('error', 'No se puede eliminar la competencia porque tiene registros asociados.');
        }

        header('Location: ' . BASE_URL . '?action=competencias');
        exit;
    }

    private function instructoresValidos($raw): array {
        $ids = is_array($raw) ? $raw : [$raw];
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn(int $id) => $id > 0)));
        if (!$ids) {
            return [];
        }
        $validos = array_map(fn(array $u): int => (int) $u['id'], (new Usuario($this->db))->getInstructores());
        return array_values(array_filter($ids, fn(int $id): bool => in_array($id, $validos, true)));
    }

    private function redirectForm(int $id): void {
        $redirect = $id > 0 ? "competencias/editar&id={$id}" : 'competencias/crear';
        header('Location: ' . BASE_URL . "?action={$redirect}");
        exit;
    }
}
