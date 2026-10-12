<?php
declare(strict_types=1);

/**
 * UsuarioController.php — Controlador de Usuarios
 *
 * CRUD de usuarios accesible solo para Administrador/Instructor.
 * La creación centraliza cualquier rol en un único flujo `store()`,
 * aplicando el principio de "Single Source of Truth" y delegando
 * la atomicidad a `Usuario::registrarUsuario()` mediante transacciones PDO.
 */
class UsuarioController {
    private PDO $db;
    private Usuario $model;

    public function __construct(PDO $db) {
        $this->db    = $db;
        $this->model = new Usuario($db);
    }

    /**
     * Lista todos los usuarios con opción de filtrado por rol (Administradores, Instructores, Aprendices).
     */
    public function index(): void {
        Auth::requireAdmin();

        $rolFiltro = null;
        if (isset($_GET['rol']) && $_GET['rol'] !== '') {
            $rolId = (int) $_GET['rol'];
            if ($rolId >= 1 && $rolId <= 3) {
                $rolFiltro = $rolId;
            }
        }

        $usuarios  = $this->model->getAll($rolFiltro);
        $conteos   = $this->model->getCountsByRole();
        $rolModel  = new Rol($this->db);
        $roles     = $rolModel->getAll();
        $pageTitle = 'Gestión de Usuarios';

        require_once ROOT_PATH . '/views/usuarios/index.php';
    }

    /**
     * Muestra el formulario unificado de creación de usuario.
     * Carga roles y fichas para poblar los selects en la vista.
     */
    public function crear(): void {
        Auth::requireAdmin();

        $rolModel   = new Rol($this->db);
        $fichaModel = new Ficha($this->db);

        $roles      = $rolModel->getAll();
        $fichas     = $fichaModel->getAll();
        $usuario    = null;   // null indica modo creación
        $csrf_token = Auth::generateCSRF();
        $pageTitle  = 'Nuevo Usuario';

        require_once ROOT_PATH . '/views/usuarios/form.php';
    }

    /**
     * Muestra el formulario de edición de un usuario existente.
     */
    public function editar(): void {
        Auth::requireAdmin();

        $id      = (int) ($_GET['id'] ?? 0);
        $usuario = $this->model->getById($id);

        if (!$usuario) {
            Auth::setFlash('error', 'Usuario no encontrado.');
            header('Location: ' . BASE_URL . '?action=usuarios');
            exit;
        }

        $rolModel   = new Rol($this->db);
        $fichaModel = new Ficha($this->db);

        $roles      = $rolModel->getAll();
        $fichas     = $fichaModel->getAll();
        $csrf_token = Auth::generateCSRF();
        $pageTitle  = 'Editar Usuario';

        require_once ROOT_PATH . '/views/usuarios/form.php';
    }

    /**
     * ─── SINGLE SOURCE OF TRUTH ─────────────────────────────────────────────
     * Procesa el POST para registrar un usuario de cualquier rol.
     *
     * 1. Valida CSRF y método HTTP.
     * 2. Sanitiza entradas comunes.
     * 3. Aplica password_hash($pass, PASSWORD_DEFAULT).
     * 4. Si el rol es Aprendiz, valida estrictamente rfid_tag y ficha_id.
     * 5. Delega la creación atómica al modelo Usuario::registrarUsuario().
     * 6. Redirige con mensajes flash en variables de sesión.
     */
    public function store(): void {
        Auth::requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '?action=usuarios');
            exit;
        }

        // 1. Validar CSRF
        if (!Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
            Auth::setFlash('error', 'Token de seguridad inválido. Por favor recarga el formulario.');
            header('Location: ' . BASE_URL . '?action=usuarios/crear');
            exit;
        }

        // 2. Capturar y sanitizar datos
        $rolId     = (int) ($_POST['rol_id'] ?? $_POST['id_rol'] ?? 0);
        $documento = trim((string) ($_POST['documento'] ?? $_POST['num_documento'] ?? ''));
        $nombre    = trim((string) ($_POST['nombre'] ?? ''));
        $apellido  = trim((string) ($_POST['apellido'] ?? ''));
        $correo    = strtolower(trim((string) ($_POST['correo'] ?? '')));
        $password  = (string) ($_POST['password'] ?? '');
        $estado    = in_array($_POST['estado'] ?? '', ['Activo', 'Inactivo'], true) ? $_POST['estado'] : 'Activo';

        // 3. Validaciones de campos obligatorios
        Validator::reset();
        Validator::required($documento, 'número de documento');
        Validator::maxLength($documento, 20, 'número de documento');
        Validator::required($nombre, 'nombre');
        Validator::maxLength($nombre, 100, 'nombre');
        Validator::required($apellido, 'apellido');
        Validator::maxLength($apellido, 100, 'apellido');
        Validator::required($correo, 'correo electrónico');
        Validator::email($correo);
        Validator::required($password, 'contraseña');
        Validator::minLength($password, 8, 'contraseña');

        if ($rolId < 1 || $rolId > 3) {
            Validator::required('', 'rol');
        }

        // 4. Si el rol es Aprendiz, validar estrictamente ficha_id y rfid_tag
        $fichaId = null;
        $rfidTag = null;

        if ($rolId === Usuario::ROL_APRENDIZ) {
            $fichaId = filter_var($_POST['ficha_id'] ?? '', FILTER_VALIDATE_INT);
            $rfidTag = trim((string) ($_POST['rfid_tag'] ?? ''));

            if ($fichaId === false || $fichaId <= 0) {
                Auth::setFlash('error', 'Debe seleccionar una Ficha de Formación válida para el Aprendiz.');
                header('Location: ' . BASE_URL . '?action=usuarios/crear');
                exit;
            }

            if (empty($rfidTag)) {
                Auth::setFlash('error', 'El código RFID (rfid_tag) es obligatorio para el Aprendiz.');
                header('Location: ' . BASE_URL . '?action=usuarios/crear');
                exit;
            }

            Validator::maxLength($rfidTag, 50, 'código RFID');
        }

        if (!Validator::isValid()) {
            Auth::setFlash('error', implode('<br>', Validator::getErrors()));
            header('Location: ' . BASE_URL . '?action=usuarios/crear');
            exit;
        }

        // 5. Aplicar password_hash con algoritmo por defecto
        $passHash = password_hash($password, PASSWORD_DEFAULT);

        // 6. Preparar array de datos estructurado para el modelo
        $datos = [
            'rol_id'        => $rolId,
            'id_rol'        => $rolId,
            'documento'     => $documento,
            'num_documento' => $documento,
            'nombre'        => $nombre,
            'apellido'      => $apellido,
            'correo'        => $correo,
            'password'      => $passHash,
            'estado'        => $estado,
            'ficha_id'      => $fichaId,
            'rfid_tag'      => $rfidTag,
        ];

        // 7. Delegar al modelo con Transacción PDO
        $exito = $this->model->registrarUsuario($datos);

        // 8. Manejo de redirección y mensajes en sesión
        if ($exito) {
            $rolNombre = match ($rolId) {
                Usuario::ROL_ADMINISTRADOR => 'Administrador',
                Usuario::ROL_INSTRUCTOR    => 'Instructor',
                Usuario::ROL_APRENDIZ      => 'Aprendiz',
                default                    => 'Usuario',
            };
            Auth::setFlash('success', "{$rolNombre} <strong>{$nombre} {$apellido}</strong> registrado exitosamente.");
            header('Location: ' . BASE_URL . '?action=usuarios');
        } else {
            Auth::setFlash('error', 'Error al registrar el usuario. Verifique que el documento, correo o código RFID no estén ya duplicados en el sistema.');
            header('Location: ' . BASE_URL . '?action=usuarios/crear');
        }
        exit;
    }

    /**
     * Procesa la actualización de un usuario existente.
     */
    public function guardar(): void {
        Auth::requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '?action=usuarios');
            exit;
        }

        if (!Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
            Auth::setFlash('error', 'Token de seguridad inválido.');
            header('Location: ' . BASE_URL . '?action=usuarios');
            exit;
        }

        $id = (int) ($_POST['id_usuario'] ?? 0);

        $data = [
            'id_rol'        => (int)   ($_POST['rol_id'] ?? $_POST['id_rol'] ?? 3),
            'num_documento' => trim(   (string) ($_POST['documento'] ?? $_POST['num_documento'] ?? '')),
            'nombre'        => trim(   (string) ($_POST['nombre']        ?? '')),
            'apellido'      => trim(   (string) ($_POST['apellido']      ?? '')),
            'correo'        => strtolower(trim((string) ($_POST['correo'] ?? ''))),
            'estado'        => in_array($_POST['estado'] ?? '', ['Activo', 'Inactivo'], true)
                                ? $_POST['estado'] : 'Activo',
        ];

        Validator::reset();
        Validator::required($data['num_documento'], 'número de documento');
        Validator::required($data['nombre'], 'nombre');
        Validator::required($data['apellido'], 'apellido');
        Validator::required($data['correo'], 'correo');
        Validator::email($data['correo']);

        $password = trim((string) ($_POST['password'] ?? ''));
        if ($id === 0) {
            Validator::required($password, 'contraseña');
            Validator::minLength($password, 8, 'contraseña');
            $data['password'] = $password;
        } elseif (!empty($password)) {
            Validator::minLength($password, 8, 'contraseña');
            $data['password'] = $password;
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
            $mensaje = str_contains($e->getMessage(), 'Duplicate')
                ? 'El documento o correo ya existe en el sistema.'
                : 'Error al guardar el usuario. Inténtalo de nuevo.';
            Auth::setFlash('error', $mensaje);
            error_log('[UsuarioController::guardar] ' . $e->getMessage());
        }

        header('Location: ' . BASE_URL . '?action=usuarios');
        exit;
    }

    /**
     * Elimina un usuario por su ID.
     * Previene la auto-eliminación.
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
            Auth::setFlash('error', 'No se puede eliminar: el usuario tiene registros asociados en el sistema.');
            error_log('[UsuarioController::eliminar] ' . $e->getMessage());
        }

        header('Location: ' . BASE_URL . '?action=usuarios');
        exit;
    }
}
