<?php
declare(strict_types=1);

/**
 * AprendizController.php — Controlador de Aprendices
 *
 * CRUD de aprendices (Usuarios con Rol_id = 3).
 * Protegido para Administrador; los Instructores son redirigidos a su directorio por ficha.
 */
class AprendizController {
    private PDO $db;
    private Aprendiz $model;

    public function __construct(PDO $db) {
        $this->db    = $db;
        $this->model = new Aprendiz($db);
    }

    /**
     * Lista todos los aprendices.
     * Si es Instructor, lo redirige al módulo de Directorio de Fichas (?action=usuarios).
     */
    public function index(): void {
        Auth::requireLogin();
        if (Auth::isInstructor()) {
            header('Location: ' . BASE_URL . '?action=usuarios');
            exit;
        }

        Auth::requireAdminOnly('dashboard');
        header('Location: ' . BASE_URL . '?action=usuarios&rol=3');
        exit;
    }

    /**
     * Formulario de creación (Solo Administrador)
     */
    public function crear(): void {
        Auth::requireAdminOnly('usuarios');
        header('Location: ' . BASE_URL . '?action=usuarios/crear');
        exit;
    }

    /**
     * Formulario de edición (Solo Administrador)
     */
    public function editar(): void {
        Auth::requireAdminOnly('usuarios');
        $id       = (int) ($_GET['id'] ?? 0);
        $aprendiz = $this->model->getById($id);

        if (!$aprendiz) {
            Auth::setFlash('error', 'Aprendiz no encontrado.');
            header('Location: ' . BASE_URL . '?action=usuarios&rol=3');
            exit;
        }

        require_once ROOT_PATH . '/models/Ficha.php';
        $fichaModel = new Ficha($this->db);
        $fichas     = $fichaModel->getAll();
        $csrf_token = Auth::generateCSRF();
        $pageTitle  = 'Editar Aprendiz';
        require_once ROOT_PATH . '/views/aprendices/form.php';
    }

    /**
     * Guarda un aprendiz (crear o actualizar) (Solo Administrador)
     */
    public function guardar(): void {
        Auth::requireAdminOnly('usuarios');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '?action=usuarios&rol=3');
            exit;
        }

        if (!Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
            Auth::setFlash('error', 'Token de seguridad inválido.');
            header('Location: ' . BASE_URL . '?action=usuarios&rol=3');
            exit;
        }

        $idAprendiz = (int) ($_POST['id_aprendiz'] ?? 0);
        Validator::reset();

        if ($idAprendiz === 0) {
            $data = [
                'identificacion'  => trim((string) ($_POST['num_documento'] ?? '')),
                'nombre'          => trim((string) ($_POST['nombre']        ?? '')),
                'apellido'        => trim((string) ($_POST['apellido']      ?? '')),
                'email'           => trim((string) ($_POST['correo']        ?? '')),
                'password'        => (string) ($_POST['password']           ?? ''),
                'Ficha_id'        => (int) ($_POST['id_ficha']   ?? 0) ?: null,
                'codigo_llavero'  => trim((string) ($_POST['codigo_rfid']   ?? '')) ?: null,
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
                header('Location: ' . BASE_URL . '?action=usuarios/crear');
                exit;
            }

            try {
                $this->model->create($data);
                Auth::setFlash('success', 'Aprendiz creado correctamente.');
            } catch (\PDOException $e) {
                if (str_contains($e->getMessage(), 'Duplicate')) {
                    Auth::setFlash('error', 'El documento, correo o código de llavero ya existe.');
                } else {
                    error_log('[SCIA][AprendizController] Error al crear aprendiz: ' . $e->getFile() . ':' . $e->getLine() . ' - ' . $e->getMessage());
                    Auth::setFlash('error', 'Ocurrió un error al crear el aprendiz. Por favor, inténtalo de nuevo.');
                }
            }
        } else {
            $dataAprendiz = [
                'Ficha_id'       => (int) ($_POST['id_ficha'] ?? 0) ?: null,
                'codigo_llavero' => trim((string) ($_POST['codigo_rfid'] ?? '')) ?: null,
            ];

            if (!Validator::isValid()) {
                Auth::setFlash('error', implode('<br>', Validator::getErrors()));
                header('Location: ' . BASE_URL . "?action=aprendices/editar&id={$idAprendiz}");
                exit;
            }

            try {
                $this->model->update($idAprendiz, $dataAprendiz);

                // Actualizar datos básicos de usuario si se proporcionaron
                $usuarioModel = new Usuario($this->db);
                $dataUsuario  = [];
                if (!empty($_POST['nombre']))   $dataUsuario['nombre']   = trim((string) $_POST['nombre']);
                if (!empty($_POST['apellido']))  $dataUsuario['apellido'] = trim((string) $_POST['apellido']);
                if (!empty($_POST['correo']))    $dataUsuario['email']    = trim((string) $_POST['correo']);
                if (!empty($_POST['password']))  $dataUsuario['password'] = (string) $_POST['password'];
                if (!empty($_POST['estado']))    $dataUsuario['estado']   = $_POST['estado'];

                if (!empty($dataUsuario)) {
                    $usuarioModel->update($idAprendiz, $dataUsuario);
                }

                Auth::setFlash('success', 'Aprendiz actualizado correctamente.');
            } catch (\PDOException $e) {
                if (str_contains($e->getMessage(), 'Duplicate')) {
                    Auth::setFlash('error', 'El documento, correo o código de llavero ya existe.');
                } else {
                    error_log('[SCIA][AprendizController] Error al actualizar aprendiz: ' . $e->getFile() . ':' . $e->getLine() . ' - ' . $e->getMessage());
                    Auth::setFlash('error', 'Ocurrió un error al actualizar el aprendiz. Por favor, inténtalo de nuevo.');
                }
            }
        }

        header('Location: ' . BASE_URL . '?action=usuarios&rol=3');
        exit;
    }

    /**
     * Elimina un aprendiz (Solo Administrador)
     */
    public function eliminar(): void {
        Auth::requireAdminOnly('usuarios');
        $id = (int) ($_GET['id'] ?? 0);

        try {
            $this->model->delete($id);
            Auth::setFlash('success', 'Aprendiz eliminado correctamente.');
        } catch (\PDOException $e) {
            Auth::setFlash('error', 'No se puede eliminar el aprendiz porque tiene registros asociados.');
        }

        header('Location: ' . BASE_URL . '?action=usuarios&rol=3');
        exit;
    }
}
