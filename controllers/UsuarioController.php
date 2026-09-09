<?php
/**
 * UsuarioController.php — Controlador de Usuarios
 * 
 * CRUD de usuarios (solo accesible por administrador/instructor).
 */
class UsuarioController {
    private PDO $db;
    private Usuario $model;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->model = new Usuario($db);
    }

    /**
     * Lista todos los usuarios
     */
    public function index(): void {
        Auth::requireAdmin();
        $usuarios = $this->model->getAll();
        $pageTitle = 'Gestión de Usuarios';
        require_once ROOT_PATH . '/views/usuarios/index.php';
    }

    /**
     * Muestra formulario de creación
     */
    public function crear(): void {
        Auth::requireAdmin();
        $rolModel = new Rol($this->db);
        $roles = $rolModel->getAll();
        $usuario = null;
        $csrf_token = Auth::generateCSRF();
        $pageTitle = 'Nuevo Usuario';
        require_once ROOT_PATH . '/views/usuarios/form.php';
    }

    /**
     * Muestra formulario de edición
     */
    public function editar(): void {
        Auth::requireAdmin();
        $id = (int) ($_GET['id'] ?? 0);
        $usuario = $this->model->getById($id);

        if (!$usuario) {
            Auth::setFlash('error', 'Usuario no encontrado.');
            header('Location: ' . BASE_URL . '?action=usuarios');
            exit;
        }

        $rolModel = new Rol($this->db);
        $roles = $rolModel->getAll();
        $csrf_token = Auth::generateCSRF();
        $pageTitle = 'Editar Usuario';
        require_once ROOT_PATH . '/views/usuarios/form.php';
    }

    /**
     * Guarda un usuario (crear o actualizar)
     */
    public function guardar(): void {
        Auth::requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '?action=usuarios');
            exit;
        }

        // Validar CSRF
        if (!Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
            Auth::setFlash('error', 'Token de seguridad inválido.');
            header('Location: ' . BASE_URL . '?action=usuarios');
            exit;
        }

        $id = (int) ($_POST['id_usuario'] ?? 0);

        // Recoger datos
        $data = [
            'id_rol'        => (int) ($_POST['id_rol'] ?? 3),
            'num_documento' => trim($_POST['num_documento'] ?? ''),
            'nombre'        => trim($_POST['nombre'] ?? ''),
            'apellido'      => trim($_POST['apellido'] ?? ''),
            'correo'        => trim($_POST['correo'] ?? ''),
            'estado'        => $_POST['estado'] ?? 'Activo',
        ];

        // Validaciones
        Validator::reset();
        Validator::required($data['num_documento'], 'número de documento');
        Validator::required($data['nombre'], 'nombre');
        Validator::required($data['apellido'], 'apellido');
        Validator::required($data['correo'], 'correo');
        Validator::email($data['correo']);

        if ($id === 0) {
            // Nuevo usuario — contraseña obligatoria
            $data['password'] = $_POST['password'] ?? '';
            Validator::required($data['password'], 'contraseña');
            Validator::minLength($data['password'], 6, 'contraseña');
        } else {
            // Edición — contraseña opcional
            $password = $_POST['password'] ?? '';
            if (!empty($password)) {
                Validator::minLength($password, 6, 'contraseña');
                $data['password'] = $password;
            }
        }

        if (!Validator::isValid()) {
            Auth::setFlash('error', implode('<br>', Validator::getErrors()));
            $redirect = $id > 0 ? "usuarios/editar&id={$id}" : 'usuarios/crear';
            header('Location: ' . BASE_URL . "?action={$redirect}");
            exit;
        }

        try {
            if ($id > 0) {
                $this->model->update($id, $data);
                Auth::setFlash('success', 'Usuario actualizado correctamente.');
            } else {
                $this->model->create($data);
                Auth::setFlash('success', 'Usuario creado correctamente.');
            }
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'Duplicate')) {
                Auth::setFlash('error', 'El documento o correo ya existe en el sistema.');
            } else {
                Auth::setFlash('error', 'Error al guardar: ' . $e->getMessage());
            }
        }

        header('Location: ' . BASE_URL . '?action=usuarios');
        exit;
    }

    /**
     * Elimina un usuario
     */
    public function eliminar(): void {
        Auth::requireAdmin();
        $id = (int) ($_GET['id'] ?? 0);

        if ($id === Auth::getUserId()) {
            Auth::setFlash('error', 'No puede eliminar su propio usuario.');
            header('Location: ' . BASE_URL . '?action=usuarios');
            exit;
        }

        try {
            $this->model->delete($id);
            Auth::setFlash('success', 'Usuario eliminado correctamente.');
        } catch (\PDOException $e) {
            Auth::setFlash('error', 'No se puede eliminar el usuario porque tiene registros asociados.');
        }

        header('Location: ' . BASE_URL . '?action=usuarios');
        exit;
    }
}
