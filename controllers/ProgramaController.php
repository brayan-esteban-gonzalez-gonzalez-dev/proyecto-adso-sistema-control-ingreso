<?php
/**
 * ProgramaController.php — Controlador de Programas de Formación
 *
 * CRUD de la tabla Programa (codigo, nombre, descripcion).
 */
class ProgramaController {
    private PDO $db;
    private Programa $model;

    public function __construct(PDO $db) {
        $this->db    = $db;
        $this->model = new Programa($db);
    }

    public function index(): void {
        Auth::requireAdmin();
        $programas = $this->model->getAll();
        $pageTitle = 'Gestión de Programas';
        require_once ROOT_PATH . '/views/programas/index.php';
    }

    public function crear(): void {
        Auth::requireAdmin();
        $programa   = null;
        $csrf_token = Auth::generateCSRF();
        $pageTitle  = 'Nuevo Programa';
        require_once ROOT_PATH . '/views/programas/form.php';
    }

    public function editar(): void {
        Auth::requireAdmin();
        $id       = (int) ($_GET['id'] ?? 0);
        $programa = $this->model->getById($id);

        if (!$programa) {
            Auth::setFlash('error', 'Programa no encontrado.');
            header('Location: ' . BASE_URL . '?action=programas');
            exit;
        }

        $csrf_token = Auth::generateCSRF();
        $pageTitle  = 'Editar Programa';
        require_once ROOT_PATH . '/views/programas/form.php';
    }

    public function guardar(): void {
        Auth::requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '?action=programas');
            exit;
        }

        if (!Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
            Auth::setFlash('error', 'Token de seguridad inválido.');
            header('Location: ' . BASE_URL . '?action=programas');
            exit;
        }

        $id   = (int) ($_POST['id'] ?? 0);
        $data = [
            'codigo'      => strtoupper(trim($_POST['codigo'] ?? '')),
            'nombre'      => trim($_POST['nombre'] ?? ''),
            'descripcion' => trim($_POST['descripcion'] ?? ''),
        ];

        Validator::reset();
        Validator::required($data['codigo'], 'código');
        Validator::maxLength($data['codigo'], 20, 'código');
        Validator::required($data['nombre'], 'nombre');
        Validator::maxLength($data['nombre'], 100, 'nombre');
        if ($data['descripcion'] !== '') {
            Validator::maxLength($data['descripcion'], 2000, 'descripción');
        }
        if ($data['codigo'] !== '' && !preg_match('/^[A-Z0-9_-]+$/', $data['codigo'])) {
            Auth::setFlash('error', 'El código solo puede contener letras, números, guion y guion bajo.');
            $this->redirectForm($id);
        }

        if (!Validator::isValid()) {
            Auth::setFlash('error', implode('<br>', Validator::getErrors()));
            $this->redirectForm($id);
        }

        $existente = $this->model->getByCodigo($data['codigo']);
        if ($existente && (int) $existente['id'] !== $id) {
            Auth::setFlash('error', 'Ya existe un programa con ese código.');
            $this->redirectForm($id);
        }

        try {
            if ($id > 0) {
                if (!$this->model->getById($id)) {
                    Auth::setFlash('error', 'Programa no encontrado.');
                    header('Location: ' . BASE_URL . '?action=programas');
                    exit;
                }
                $this->model->update($id, $data);
                Auth::setFlash('success', 'Programa actualizado correctamente.');
            } else {
                $this->model->create($data);
                Auth::setFlash('success', 'Programa creado correctamente.');
            }
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate')) {
                Auth::setFlash('error', 'Ya existe un programa con ese código.');
            } else {
                Auth::setFlash('error', 'Error al guardar el programa.');
            }
        }

        header('Location: ' . BASE_URL . '?action=programas');
        exit;
    }

    public function eliminar(): void {
        Auth::requireAdmin();
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0 || !$this->model->getById($id)) {
            Auth::setFlash('error', 'Programa no encontrado.');
            header('Location: ' . BASE_URL . '?action=programas');
            exit;
        }

        if ($this->model->countFichas($id) > 0 || $this->model->countCompetencias($id) > 0) {
            Auth::setFlash('error', 'No se puede eliminar el programa porque tiene fichas o competencias asociadas.');
            header('Location: ' . BASE_URL . '?action=programas');
            exit;
        }

        try {
            $this->model->delete($id);
            Auth::setFlash('success', 'Programa eliminado correctamente.');
        } catch (\PDOException $e) {
            Auth::setFlash('error', 'No se puede eliminar el programa porque tiene registros asociados.');
        }

        header('Location: ' . BASE_URL . '?action=programas');
        exit;
    }

    private function redirectForm(int $id): void {
        $redirect = $id > 0 ? "programas/editar&id={$id}" : 'programas/crear';
        header('Location: ' . BASE_URL . "?action={$redirect}");
        exit;
    }
}
