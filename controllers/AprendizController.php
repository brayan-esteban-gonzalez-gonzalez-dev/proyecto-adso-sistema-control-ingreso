<?php
class AprendizController {
    private PDO $db;
    private Aprendiz $model;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->model = new Aprendiz($db);
    }

    /**
     * Lista todos los aprendices
     */
    public function index(): void {
        Auth::requireAdmin();
        $aprendices = $this->model->getAll();
        $pageTitle = 'Gestión de Aprendices';
        require_once ROOT_PATH . '/views/aprendices/index.php';
    }

    /**
     * Formulario de creación
     */
    public function crear(): void {
        Auth::requireAdmin();
        $fichaModel = new Ficha($this->db);
        $fichas = $fichaModel->getAll();
        $aprendiz = null;
        $csrf_token = Auth::generateCSRF();
        $pageTitle = 'Nuevo Aprendiz';
        require_once ROOT_PATH . '/views/aprendices/form.php';
    }

    /**
     * Formulario de edición
     */
    public function editar(): void {
        Auth::requireAdmin();
        $id = (int) ($_GET['id'] ?? 0);
        $aprendiz = $this->model->getById($id);

        if (!$aprendiz) {
            Auth::setFlash('error', 'Aprendiz no encontrado.');
            header('Location: ' . BASE_URL . '?action=aprendices');
            exit;
        }

        $fichaModel = new Ficha($this->db);
        $fichas = $fichaModel->getAll();
        $csrf_token = Auth::generateCSRF();
        $pageTitle = 'Editar Aprendiz';
        require_once ROOT_PATH . '/views/aprendices/form.php';
    }

    /**
     * Guarda un aprendiz (crear usuario + aprendiz, o actualizar)
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

        $idAprendiz = (int) ($_POST['id_aprendiz'] ?? 0);

        $dataAprendiz = [
            'id_ficha'    => (int) ($_POST['id_ficha'] ?? 0),
            'codigo_rfid' => trim($_POST['codigo_rfid'] ?? ''),
        ];

        Validator::reset();

        if ($idAprendiz === 0) {
            // Nuevo aprendiz — crear usuario primero
            $dataUsuario = [
                'id_rol'        => 3, // Rol de aprendiz
                'num_documento' => trim($_POST['num_documento'] ?? ''),
                'nombre'        => trim($_POST['nombre'] ?? ''),
                'apellido'      => trim($_POST['apellido'] ?? ''),
                'correo'        => trim($_POST['correo'] ?? ''),
                'password'      => $_POST['password'] ?? '',
                'estado'        => 'Activo',
            ];

            Validator::required($dataUsuario['num_documento'], 'número de documento');
            Validator::required($dataUsuario['nombre'], 'nombre');
            Validator::required($dataUsuario['apellido'], 'apellido');
            Validator::required($dataUsuario['correo'], 'correo');
            Validator::email($dataUsuario['correo']);
            Validator::required($dataUsuario['password'], 'contraseña');
            Validator::minLength($dataUsuario['password'], 6, 'contraseña');
        }

        if ($dataAprendiz['id_ficha'] === 0) {
            Validator::required('', 'ficha');
        }

        if (!Validator::isValid()) {
            Auth::setFlash('error', implode('<br>', Validator::getErrors()));
            $redirect = $idAprendiz > 0 ? "aprendices/editar&id={$idAprendiz}" : 'aprendices/crear';
            header('Location: ' . BASE_URL . "?action={$redirect}");
            exit;
        }

        try {
            if ($idAprendiz > 0) {
                // Actualizar aprendiz y usuario
                $aprendiz = $this->model->getById($idAprendiz);
                if ($aprendiz) {
                    $this->model->update($idAprendiz, $dataAprendiz);

                    // Actualizar datos del usuario si se proporcionaron
                    $dataUsuarioUpdate = [];
                    if (!empty($_POST['nombre']))   $dataUsuarioUpdate['nombre']   = trim($_POST['nombre']);
                    if (!empty($_POST['apellido']))  $dataUsuarioUpdate['apellido'] = trim($_POST['apellido']);
                    if (!empty($_POST['correo']))    $dataUsuarioUpdate['correo']   = trim($_POST['correo']);
                    if (!empty($_POST['password']))  $dataUsuarioUpdate['password'] = $_POST['password'];
                    if (!empty($_POST['estado']))    $dataUsuarioUpdate['estado']   = $_POST['estado'];

                    if (!empty($dataUsuarioUpdate)) {
                        $usuarioModel = new Usuario($this->db);
                        $usuarioModel->update($aprendiz['id_usuario'], $dataUsuarioUpdate);
                    }

                    Auth::setFlash('success', 'Aprendiz actualizado correctamente.');
                }
            } else {
                // Crear usuario primero
                $usuarioModel = new Usuario($this->db);
                $usuarioModel->create($dataUsuario);
                $idUsuario = $usuarioModel->lastInsertId();

                // Crear aprendiz
                $dataAprendiz['id_usuario'] = $idUsuario;
                $this->model->create($dataAprendiz);

                Auth::setFlash('success', 'Aprendiz creado correctamente.');
            }
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate')) {
                Auth::setFlash('error', 'El documento, correo o código RFID ya existe en el sistema.');
            } else {
                Auth::setFlash('error', 'Error al guardar: ' . $e->getMessage());
            }
        }

        header('Location: ' . BASE_URL . '?action=aprendices');
        exit;
    }

    /**
     * Elimina un aprendiz y su usuario asociado
     */
    public function eliminar(): void {
        Auth::requireAdmin();
        $id = (int) ($_GET['id'] ?? 0);

        try {
            $aprendiz = $this->model->getById($id);
            if ($aprendiz) {
                $this->model->delete($id);
                // También eliminar el usuario (CASCADE debería hacerlo, pero por seguridad)
                $usuarioModel = new Usuario($this->db);
                $usuarioModel->delete($aprendiz['id_usuario']);
            }
            Auth::setFlash('success', 'Aprendiz eliminado correctamente.');
        } catch (\PDOException $e) {
            Auth::setFlash('error', 'No se puede eliminar el aprendiz porque tiene registros asociados.');
        }

        header('Location: ' . BASE_URL . '?action=aprendices');
        exit;
    }
}
