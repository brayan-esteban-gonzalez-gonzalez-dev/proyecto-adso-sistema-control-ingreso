<?php
/**
 * JornadaController.php — Controlador de Jornadas
 *
 * CRUD de franjas horarias maestras.
 */
class JornadaController {
    private PDO $db;
    private Jornada $model;

    public function __construct(PDO $db) {
        $this->db    = $db;
        $this->model = new Jornada($db);
    }

    public function index(): void {
        Auth::requireAdmin();
        $jornadas  = $this->model->getAll();
        $pageTitle = 'Gestión de Jornadas';
        require_once ROOT_PATH . '/views/jornadas/index.php';
    }

    public function crear(): void {
        Auth::requireAdmin();
        $jornada    = null;
        $csrf_token = Auth::generateCSRF();
        $pageTitle  = 'Nueva Jornada';
        require_once ROOT_PATH . '/views/jornadas/form.php';
    }

    public function editar(): void {
        Auth::requireAdmin();
        $id      = (int) ($_GET['id'] ?? 0);
        $jornada = $this->model->getById($id);

        if (!$jornada) {
            Auth::setFlash('error', 'Jornada no encontrada.');
            header('Location: ' . BASE_URL . '?action=jornadas');
            exit;
        }

        $csrf_token = Auth::generateCSRF();
        $pageTitle  = 'Editar Jornada';
        require_once ROOT_PATH . '/views/jornadas/form.php';
    }

    public function guardar(): void {
        Auth::requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '?action=jornadas');
            exit;
        }

        if (!Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
            Auth::setFlash('error', 'Token de seguridad inválido.');
            header('Location: ' . BASE_URL . '?action=jornadas');
            exit;
        }

        $id   = (int) ($_POST['id'] ?? 0);
        $data = [
            'nombre'      => trim($_POST['nombre'] ?? ''),
            'hora_inicio' => $this->normalizarHora($_POST['hora_inicio'] ?? ''),
            'hora_fin'    => $this->normalizarHora($_POST['hora_fin'] ?? ''),
        ];

        Validator::reset();
        Validator::required($data['nombre'], 'nombre');
        Validator::maxLength($data['nombre'], 45, 'nombre');
        Validator::required($data['hora_inicio'], 'hora de inicio');
        Validator::required($data['hora_fin'], 'hora de fin');

        if ($data['hora_inicio'] !== '' && $data['hora_fin'] !== '' && $data['hora_fin'] <= $data['hora_inicio']) {
            Auth::setFlash('error', 'La hora de fin debe ser mayor que la hora de inicio.');
            $redirect = $id > 0 ? "jornadas/editar&id={$id}" : 'jornadas/crear';
            header('Location: ' . BASE_URL . "?action={$redirect}");
            exit;
        }

        if (!Validator::isValid()) {
            Auth::setFlash('error', implode('<br>', Validator::getErrors()));
            $redirect = $id > 0 ? "jornadas/editar&id={$id}" : 'jornadas/crear';
            header('Location: ' . BASE_URL . "?action={$redirect}");
            exit;
        }

        $existente = $this->model->getByNombre($data['nombre']);
        if ($existente && (int) $existente['id'] !== $id) {
            Auth::setFlash('error', 'Ya existe una jornada con ese nombre.');
            $redirect = $id > 0 ? "jornadas/editar&id={$id}" : 'jornadas/crear';
            header('Location: ' . BASE_URL . "?action={$redirect}");
            exit;
        }

        try {
            if ($id > 0) {
                $this->model->update($id, $data);
                Auth::setFlash('success', 'Jornada actualizada correctamente.');
            } else {
                $this->model->create($data);
                Auth::setFlash('success', 'Jornada creada correctamente.');
            }
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate')) {
                Auth::setFlash('error', 'Ya existe una jornada con ese nombre.');
            } else {
                Auth::setFlash('error', 'Error al guardar la jornada.');
            }
        }

        header('Location: ' . BASE_URL . '?action=jornadas');
        exit;
    }

    public function eliminar(): void {
        Auth::requireAdmin();
        $id = (int) ($_GET['id'] ?? 0);

        if ($id <= 0 || !$this->model->getById($id)) {
            Auth::setFlash('error', 'Jornada no encontrada.');
            header('Location: ' . BASE_URL . '?action=jornadas');
            exit;
        }

        if ($this->model->countFichas($id) > 0) {
            Auth::setFlash('error', 'No se puede eliminar la jornada porque tiene fichas asociadas.');
            header('Location: ' . BASE_URL . '?action=jornadas');
            exit;
        }

        try {
            $this->model->delete($id);
            Auth::setFlash('success', 'Jornada eliminada correctamente.');
        } catch (\PDOException $e) {
            Auth::setFlash('error', 'No se puede eliminar la jornada porque tiene registros asociados.');
        }

        header('Location: ' . BASE_URL . '?action=jornadas');
        exit;
    }

    private function normalizarHora(string $hora): string {
        $hora = trim($hora);
        if ($hora === '') {
            return '';
        }
        if (!preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $hora)) {
            return '';
        }
        return strlen($hora) === 5 ? $hora . ':00' : $hora;
    }
}
