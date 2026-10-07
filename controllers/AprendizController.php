<?php
/**
 * AprendizController.php — Controlador de Aprendices
 *
 * CRUD de aprendices. En la nueva DB los aprendices son Usuarios con Rol_id=3.
 * El modelo Aprendiz actúa como wrapper sobre la tabla Usuario.
 */
class AprendizController {
    private PDO $db;
    private Aprendiz $model;

    public function __construct(PDO $db) {
        $this->db    = $db;
        $this->model = new Aprendiz($db);
    }

    /**
     * Lista todos los aprendices
     */
    public function index(): void {
        Auth::requireAdmin();
        $aprendices = $this->model->getAll();
        $pageTitle   = 'Gestión de Aprendices';
        require_once ROOT_PATH . '/views/aprendices/index.php';
    }

    /**
     * Formulario de creación
     */
    public function crear(): void {
        Auth::requireAdmin();
        $fichaModel = new Ficha($this->db);
        $fichas     = $fichaModel->getAll();
        $aprendiz   = null;
        $csrf_token = Auth::generateCSRF();
        $pageTitle  = 'Nuevo Aprendiz';
        require_once ROOT_PATH . '/views/aprendices/form.php';
    }

    /**
     * Formulario de edición
     */
    public function editar(): void {
        Auth::requireAdmin();
        $id       = (int) ($_GET['id'] ?? 0);
        $aprendiz = $this->model->getById($id);

        if (!$aprendiz) {
            Auth::setFlash('error', 'Aprendiz no encontrado.');
            header('Location: ' . BASE_URL . '?action=aprendices');
            exit;
        }

        $fichaModel = new Ficha($this->db);
        $fichas     = $fichaModel->getAll();
        $csrf_token = Auth::generateCSRF();
        $pageTitle  = 'Editar Aprendiz';
        require_once ROOT_PATH . '/views/aprendices/form.php';
    }

    /**
     * Guarda un aprendiz (crear o actualizar en tabla Usuario con Rol_id=3)
     */
    public function guardar(): void {
        Auth::requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '?action=aprendices');
            exit;
        }

        if (!Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
            Auth::setFlash('error', 'Token de seguridad inválido.');
            header('Location: ' . BASE_URL . '?action=aprendices');
            exit;
        }

        // En la nueva DB, el ID del aprendiz es el mismo que el del usuario
        $idAprendiz = (int) ($_POST['id_aprendiz'] ?? 0);

        Validator::reset();

        if ($idAprendiz === 0) {
            // Nuevo aprendiz — validar todos los campos de usuario
            $data = [
                'identificacion'  => trim($_POST['num_documento'] ?? ''),
                'nombre'          => trim($_POST['nombre']        ?? ''),
                'apellido'        => trim($_POST['apellido']      ?? ''),
                'email'           => trim($_POST['correo']        ?? ''),
                'password'        => $_POST['password']           ?? '',
                'Ficha_id'        => (int) ($_POST['id_ficha']   ?? 0) ?: null,
                'codigo_llavero'  => trim($_POST['codigo_rfid']   ?? '') ?: null,
                'estado'          => 'Activo',
            ];

            Validator::required($data['identificacion'], 'número de documento');
            Validator::required($data['nombre'],         'nombre');
            Validator::required($data['apellido'],       'apellido');
            Validator::required($data['email'],          'correo');
            Validator::email($data['email']);
            Validator::required($data['password'],       'contraseña');
            Validator::minLength($data['password'], 6,   'contraseña');

            if (!$data['Ficha_id']) {
                Validator::required('', 'ficha');
            }

            if (!Validator::isValid()) {
                Auth::setFlash('error', implode('<br>', Validator::getErrors()));
                header('Location: ' . BASE_URL . '?action=aprendices/crear');
                exit;
            }

            try {
                $this->model->create($data);
                Auth::setFlash('success', 'Aprendiz creado correctamente.');
            } catch (\PDOException $e) {
                if (str_contains($e->getMessage(), 'Duplicate')) {
                    Auth::setFlash('error', 'El documento, correo o código de llavero ya existe.');
                } else {
                    Auth::setFlash('error', 'Error al guardar: ' . $e->getMessage());
                }
            }
        } else {
            // Actualizar aprendiz existente
            $dataAprendiz = [
                'Ficha_id'       => (int) ($_POST['id_ficha'] ?? 0) ?: null,
                'codigo_llavero' => trim($_POST['codigo_rfid'] ?? '') ?: null,
            ];

            if (!Validator::isValid()) {
                Auth::setFlash('error', implode('<br>', Validator::getErrors()));
                header('Location: ' . BASE_URL . "?action=aprendices/editar&id={$idAprendiz}");
                exit;
            }

            try {
                $this->model->update($idAprendiz, $dataAprendiz);

                // Actualizar datos básicos del usuario si se proporcionaron
                $usuarioModel = new Usuario($this->db);
                $dataUsuario  = [];
                if (!empty($_POST['nombre']))   $dataUsuario['nombre']   = trim($_POST['nombre']);
                if (!empty($_POST['apellido']))  $dataUsuario['apellido'] = trim($_POST['apellido']);
                if (!empty($_POST['correo']))    $dataUsuario['email']    = trim($_POST['correo']);
                if (!empty($_POST['password']))  $dataUsuario['password'] = $_POST['password'];
                if (!empty($_POST['estado']))    $dataUsuario['estado']   = $_POST['estado'];

                if (!empty($dataUsuario)) {
                    $usuarioModel->update($idAprendiz, $dataUsuario);
                }

                Auth::setFlash('success', 'Aprendiz actualizado correctamente.');
            } catch (\PDOException $e) {
                if (str_contains($e->getMessage(), 'Duplicate')) {
                    Auth::setFlash('error', 'El documento, correo o código de llavero ya existe.');
                } else {
                    Auth::setFlash('error', 'Error al guardar: ' . $e->getMessage());
                }
            }
        }

        header('Location: ' . BASE_URL . '?action=aprendices');
        exit;
    }

    /**
     * Elimina un aprendiz (elimina el registro Usuario con Rol_id=3)
     */
    public function eliminar(): void {
        Auth::requireAdmin();
        $id = (int) ($_GET['id'] ?? 0);

        try {
            $this->model->delete($id);
            Auth::setFlash('success', 'Aprendiz eliminado correctamente.');
        } catch (\PDOException $e) {
            Auth::setFlash('error', 'No se puede eliminar el aprendiz porque tiene registros asociados.');
        }

        header('Location: ' . BASE_URL . '?action=aprendices');
        exit;
    }
}
