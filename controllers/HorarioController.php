<?php
/**
 * HorarioController.php — Controlador de Horarios
 * 
 * CRUD de horarios por ficha.
 */
class HorarioController {
    private PDO $db;
    private Horario $model;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->model = new Horario($db);
    }

    /**
     * Lista horarios de una ficha
     */
    public function index(): void {
        Auth::requireAdmin();
        $idFicha = (int) ($_GET['id_ficha'] ?? 0);
        $fichaModel = new Ficha($this->db);

        if ($idFicha > 0) {
            $ficha = $fichaModel->getById($idFicha);
            $horarios = $this->model->getByFicha($idFicha);
        } else {
            $ficha = null;
            $horarios = [];
        }

        $fichas = $fichaModel->getAll();
        $pageTitle = 'Gestión de Horarios';
        require_once ROOT_PATH . '/views/horarios/index.php';
    }

    /**
     * Formulario de creación
     */
    public function crear(): void {
        Auth::requireAdmin();
        $idFicha = (int) ($_GET['id_ficha'] ?? 0);
        $fichaModel = new Ficha($this->db);
        $fichas = $fichaModel->getAll();
        $horario = null;
        $csrf_token = Auth::generateCSRF();
        $pageTitle = 'Nuevo Horario';
        require_once ROOT_PATH . '/views/horarios/form.php';
    }

    /**
     * Formulario de edición
     */
    public function editar(): void {
        Auth::requireAdmin();
        $id = (int) ($_GET['id'] ?? 0);
        $horario = $this->model->getById($id);

        if (!$horario) {
            Auth::setFlash('error', 'Horario no encontrado.');
            header('Location: ' . BASE_URL . '?action=horarios');
            exit;
        }

        $fichaModel = new Ficha($this->db);
        $fichas = $fichaModel->getAll();
        $idFicha = $horario['id_ficha'];
        $csrf_token = Auth::generateCSRF();
        $pageTitle = 'Editar Horario';
        require_once ROOT_PATH . '/views/horarios/form.php';
    }

    /**
     * Guarda un horario
     */
    public function guardar(): void {
        Auth::requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '?action=horarios');
            exit;
        }

        if (!Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
            Auth::setFlash('error', 'Token de seguridad inválido.');
            header('Location: ' . BASE_URL . '?action=horarios');
            exit;
        }

        $id = (int) ($_POST['id_horario'] ?? 0);
        $data = [
            'id_ficha'           => (int) ($_POST['id_ficha'] ?? 0),
            'dia_semana'         => $_POST['dia_semana'] ?? '',
            'hora_entrada'       => $_POST['hora_entrada'] ?? '',
            'hora_salida'        => $_POST['hora_salida'] ?? '',
            'tolerancia_minutos' => (int) ($_POST['tolerancia_minutos'] ?? 15),
        ];

        Validator::reset();
        Validator::required($data['dia_semana'], 'día de la semana');
        Validator::required($data['hora_entrada'], 'hora de entrada');
        Validator::required($data['hora_salida'], 'hora de salida');

        if (!Validator::isValid()) {
            Auth::setFlash('error', implode('<br>', Validator::getErrors()));
            header('Location: ' . BASE_URL . '?action=horarios&id_ficha=' . $data['id_ficha']);
            exit;
        }

        try {
            if ($id > 0) {
                $this->model->update($id, $data);
                Auth::setFlash('success', 'Horario actualizado correctamente.');
            } else {
                $this->model->create($data);
                Auth::setFlash('success', 'Horario creado correctamente.');
            }
        } catch (\PDOException $e) {
            Auth::setFlash('error', 'Error al guardar el horario: ' . $e->getMessage());
        }

        header('Location: ' . BASE_URL . '?action=horarios&id_ficha=' . $data['id_ficha']);
        exit;
    }

    /**
     * Elimina un horario
     */
    public function eliminar(): void {
        Auth::requireAdmin();
        $id = (int) ($_GET['id'] ?? 0);
        $idFicha = (int) ($_GET['id_ficha'] ?? 0);

        try {
            $this->model->delete($id);
            Auth::setFlash('success', 'Horario eliminado correctamente.');
        } catch (\PDOException $e) {
            Auth::setFlash('error', 'Error al eliminar el horario.');
        }

        header('Location: ' . BASE_URL . '?action=horarios&id_ficha=' . $idFicha);
        exit;
    }
}
