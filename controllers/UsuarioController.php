<?php
declare(strict_types=1);

/**
 * UsuarioController.php — Controlador de Usuarios
 *
 * Gestión de usuarios con control de acceso por roles (RBAC):
 * - Administrador: CRUD global de usuarios y consulta agrupada por ficha.
 * - Instructor: Directorio enfocado estrictamente en sus fichas asignadas (Aprendices e Instructores).
 */
class UsuarioController {
    private PDO $db;
    private Usuario $model;
    private Ficha $fichaModel;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->model = new Usuario($db);
        require_once ROOT_PATH . '/models/Ficha.php';
        $this->fichaModel = new Ficha($db);
    }

    /**
     * Lista usuarios según el rol autenticado:
     * - Instructor: Directorio por ficha (solo sus fichas asignadas con protección Anti-IDOR).
     * - Administrador: Listado global filtrable o vista detallada por ficha seleccionada.
     */
    public function index(): void {
        Auth::requireLogin();

        // ── 1. ROL INSTRUCTOR (RF-RBAC) ──────────────────────────────────────
        if (Auth::isInstructor()) {
            $instructorId = (int) Auth::getUserId();
            $fichas = $this->fichaModel->getFichasPorInstructor($instructorId);

            $fichaSeleccionadaId = null;
            $fichaActual = null;
            $aprendices = [];
            $instructores = [];

            if (!empty($fichas)) {
                if (isset($_GET['ficha_id']) && (int) $_GET['ficha_id'] > 0) {
                    $solicitadaId = (int) $_GET['ficha_id'];
                    // Validación estricta Anti-IDOR
                    if ($this->fichaModel->instructorPerteneceAFicha($instructorId, $solicitadaId)) {
                        $fichaSeleccionadaId = $solicitadaId;
                    } else {
                        Auth::setFlash('error', 'Acceso denegado: no estás asignado a esa ficha.');
                        header('Location: ' . BASE_URL . '?action=usuarios');
                        exit;
                    }
                } else {
                    $fichaSeleccionadaId = (int) $fichas[0]['id'];
                }

                $fichaActual = $this->fichaModel->getById($fichaSeleccionadaId);
                $aprendices = $this->fichaModel->getAprendicesPorFicha($fichaSeleccionadaId);
                $instructores = $this->fichaModel->getInstructoresPorFicha($fichaSeleccionadaId);
            }

            $esVistaFicha = true;
            $pageTitle = 'Directorio de Fichas Asignadas';
            require_once ROOT_PATH . '/views/usuarios/index.php';
            return;
        }

        // ── 2. ROL ADMINISTRADOR ─────────────────────────────────────────────
        Auth::requireAdmin();

        // Si el administrador selecciona inspeccionar una ficha específica (?ficha_id=X)
        if (isset($_GET['ficha_id']) && (int) $_GET['ficha_id'] > 0) {
            $fichaSeleccionadaId = (int) $_GET['ficha_id'];
            $fichaActual = $this->fichaModel->getById($fichaSeleccionadaId);

            if ($fichaActual) {
                $fichas = $this->fichaModel->getAll();
                $aprendices = $this->fichaModel->getAprendicesPorFicha($fichaSeleccionadaId);
                $instructores = $this->fichaModel->getInstructoresPorFicha($fichaSeleccionadaId);
                $esVistaFicha = true;
                $pageTitle = 'Integrantes Ficha ' . ($fichaActual['codigo'] ?? '');
                require_once ROOT_PATH . '/views/usuarios/index.php';
                return;
            }
        }

        // Vista global estándar del administrador
        $rolFiltro = null;
        if (isset($_GET['rol']) && $_GET['rol'] !== '') {
            $rolId = (int) $_GET['rol'];
            if ($rolId >= 1 && $rolId <= 3) {
                $rolFiltro = $rolId;
            }
        }

        $fichas = $this->fichaModel->getAll();
        $usuarios = $this->model->getAll($rolFiltro);
        $conteos = $this->model->getCountsByRole();
        $esVistaFicha = false;
        $pageTitle = 'Gestión de Usuarios';
        require_once ROOT_PATH . '/views/usuarios/index.php';
    }

    /**
     * Muestra formulario de creación (Solo Administrador)
     */
    public function crear(): void {
        Auth::requireAdminOnly('usuarios');
        $rolModel   = new Rol($this->db);
        $roles      = $rolModel->getAll();
        $fichas     = $this->fichaModel->getAll();
        $usuario    = null;
        $csrf_token = Auth::generateCSRF();
        $pageTitle  = 'Nuevo Usuario';
        require_once ROOT_PATH . '/views/usuarios/form.php';
    }

    /**
     * Muestra formulario de edición (Solo Administrador)
     */
    public function editar(): void {
        Auth::requireAdminOnly('usuarios');
        $id      = (int) ($_GET['id'] ?? 0);
        $usuario = $this->model->getById($id);

        if (!$usuario) {
            Auth::setFlash('error', 'Usuario no encontrado.');
            header('Location: ' . BASE_URL . '?action=usuarios');
            exit;
        }

        $rolModel   = new Rol($this->db);
        $roles      = $rolModel->getAll();
        $fichas     = $this->fichaModel->getAll();
        $csrf_token = Auth::generateCSRF();
        $pageTitle  = 'Editar Usuario';
        require_once ROOT_PATH . '/views/usuarios/form.php';
    }

    /**
     * Guarda un usuario (crear o actualizar) (Solo Administrador)
     */
    public function guardar(): void {
        Auth::requireAdminOnly('usuarios');
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
        $rolId = (int) ($_POST['id_rol'] ?? $_POST['Rol_id'] ?? 3);
        $fichaId = ($rolId === Usuario::ROL_APRENDIZ && !empty($_POST['Ficha_id']))
            ? (int) $_POST['Ficha_id']
            : null;

        $data = [
            'Rol_id'         => $rolId,
            'identificacion' => trim((string) ($_POST['num_documento'] ?? $_POST['identificacion'] ?? '')),
            'nombre'         => trim((string) ($_POST['nombre']        ?? '')),
            'apellido'       => trim((string) ($_POST['apellido']      ?? '')),
            'email'          => trim((string) ($_POST['correo']        ?? $_POST['email'] ?? '')),
            'Ficha_id'       => $fichaId,
            'codigo_llavero' => trim((string) ($_POST['codigo_llavero'] ?? '')) ?: null,
            'estado'         => $_POST['estado'] ?? 'Activo',
        ];

        Validator::reset();
        Validator::required($data['identificacion'], 'número de documento');
        Validator::required($data['nombre'],         'nombre');
        Validator::required($data['apellido'],       'apellido');
        Validator::required($data['email'],          'correo');
        Validator::email($data['email']);

        if ($rolId === Usuario::ROL_APRENDIZ && empty($fichaId)) {
            Validator::required('', 'Ficha de Formación (obligatoria para el Aprendiz)');
        }

        if ($id === 0) {
            $data['password'] = (string) ($_POST['password'] ?? '');
            Validator::required($data['password'], 'contraseña');
            Validator::minLength($data['password'], 6, 'contraseña');
        } else {
            $password = (string) ($_POST['password'] ?? '');
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
                Auth::setFlash('error', 'El documento, correo o código de llavero ya existe en el sistema.');
            } else {
                error_log('[SCIA][UsuarioController] Error al guardar usuario: ' . $e->getFile() . ':' . $e->getLine() . ' - ' . $e->getMessage());
                Auth::setFlash('error', 'Ocurrió un error al guardar el usuario. Por favor, inténtalo de nuevo.');
            }
        }

        header('Location: ' . BASE_URL . '?action=usuarios');
        exit;
    }

    /**
     * Elimina un usuario (Solo Administrador)
     */
    public function eliminar(): void {
        Auth::requireAdminOnly('usuarios');
        $id = (int) ($_GET['id'] ?? 0);

        if ($id === Auth::getUserId()) {
            Auth::setFlash('error', 'No puedes eliminar tu propio usuario.');
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
