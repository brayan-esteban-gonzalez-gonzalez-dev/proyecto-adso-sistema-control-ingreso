<?php
declare(strict_types=1);

/**
 * AsistenciaController.php — Controlador de Asistencia
 *
 * Gestiona el historial de asistencias, la terminal RFID y
 * el flujo de toggle (encender/apagar) de sesiones de asistencia.
 *
 * Nueva DB: tabla `asistencia`, campo Usuario_id (no id_aprendiz).
 * Los aprendices son Usuarios con Rol_id=3.
 */
class AsistenciaController {
    private PDO $db;
    private IngresoAsistencia $model;
    private Sesion $sesionModel;
    private Ficha $fichaModel;
    private Aprendiz $aprendizModel;

    public function __construct(PDO $db) {
        $this->db = $db;

        // Cargar modelos si no se cargaron en index.php
        require_once ROOT_PATH . '/models/IngresoAsistencia.php';
        require_once ROOT_PATH . '/models/Sesion.php';
        require_once ROOT_PATH . '/models/Ficha.php';
        require_once ROOT_PATH . '/models/Aprendiz.php';

        $this->model          = new IngresoAsistencia($db);
        $this->sesionModel    = new Sesion($db);
        $this->fichaModel     = new Ficha($db);
        $this->aprendizModel  = new Aprendiz($db);
    }

    // =====================================================================
    //  VISTA — Terminal RFID
    // =====================================================================

    /**
     * Pantalla de marcación RFID / Toggle de asistencia. Solo instructor.
     */
    public function rfid(): void {
        Auth::requireLogin();
        if (!Auth::isInstructor()) {
            Auth::setFlash('info', 'La marcación RFID está reservada para el rol de Instructor.');
            header('Location: ' . BASE_URL . '?action=dashboard');
            exit;
        }

        $instructorId = (int) Auth::getUserId();

        // Fichas vinculadas al instructor (vía tabla Ficha.instructor_id)
        $fichasVinculadas = $this->fichaModel->getByInstructor($instructorId);

        // También fichas donde tiene sesiones asignadas en la tabla sesion
        $fichasSesiones = $this->sesionModel->getFichasDeInstructor($instructorId);

        // Unificar ambas fuentes de fichas (evitar duplicados)
        $fichasMap = [];
        foreach ($fichasVinculadas as $f) {
            $fichasMap[(int)$f['id']] = $f;
        }
        foreach ($fichasSesiones as $f) {
            if (!isset($fichasMap[(int)$f['id']])) {
                $fichasMap[(int)$f['id']] = $f;
            }
        }
        $fichas = array_values($fichasMap);

        // Sesiones activas hoy del instructor
        $sesionesActivas = $this->sesionModel->obtenerSesionesActivasInstructor($instructorId);

        // Mapear sesiones activas por ficha_id para acceso rápido en la vista
        $sesionActivaPorFicha = [];
        foreach ($sesionesActivas as $sa) {
            $sesionActivaPorFicha[(int)$sa['Ficha_id']] = $sa;
        }

        // Token CSRF para las peticiones AJAX
        $csrfToken = Auth::generateCSRF();

        // Marcaciones de hoy (todas las sesiones)
        $ultimosIngresos = array_map(static function (array $ing): array {
            $ing['num_documento'] = $ing['identificacion'] ?? ($ing['num_documento'] ?? '');
            return $ing;
        }, $this->model->getHistorial(date('Y-m-d')));

        $pageTitle = 'Marcación RFID';
        require_once ROOT_PATH . '/views/asistencia/rfid.php';
    }

    // =====================================================================
    //  API AJAX — Toggle de sesión
    // =====================================================================

    /**
     * Inicia una sesión de asistencia para una ficha (toggle ON).
     * Endpoint: POST ?action=asistencia/iniciarSesionAsistencia
     * Responde JSON.
     */
    public function iniciarSesionAsistencia(): void {
        header('Content-Type: application/json; charset=utf-8');
        Auth::requireLogin();

        if (!Auth::isInstructor()) {
            echo json_encode(['ok' => false, 'mensaje' => 'Acceso denegado. Solo instructores.']);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido.']);
            return;
        }

        // Validar CSRF
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Auth::validateCSRF($csrfToken)) {
            echo json_encode(['ok' => false, 'mensaje' => 'Token CSRF inválido. Recarga la página.']);
            return;
        }

        $fichaId      = (int) ($_POST['ficha_id'] ?? 0);
        $instructorId = (int) Auth::getUserId();

        if ($fichaId <= 0) {
            echo json_encode(['ok' => false, 'mensaje' => 'Ficha no especificada.']);
            return;
        }

        // Verificar que el instructor está vinculado a esta ficha
        if (!$this->instructorVinculadoAFicha($instructorId, $fichaId)) {
            echo json_encode(['ok' => false, 'mensaje' => 'No estás vinculado a esta ficha.']);
            return;
        }

        // Verificar que no haya sesión activa ya
        $existente = $this->sesionModel->obtenerSesionActivaPorFicha($fichaId);
        if ($existente !== null) {
            echo json_encode([
                'ok'        => false,
                'mensaje'   => 'Ya existe una sesión activa para esta ficha.',
                'sesion_id' => (int)$existente['id'],
            ]);
            return;
        }

        $sesionId = $this->sesionModel->abrirSesion($fichaId, $instructorId);

        if ($sesionId === false) {
            echo json_encode(['ok' => false, 'mensaje' => 'No se pudo iniciar la sesión.']);
            return;
        }

        // Generar nuevo CSRF para la próxima petición
        $nuevoCsrf = Auth::generateCSRF();

        echo json_encode([
            'ok'         => true,
            'sesion_id'  => $sesionId,
            'mensaje'    => 'Sesión de asistencia iniciada.',
            'csrf_token' => $nuevoCsrf,
        ]);
    }

    /**
     * Cierra una sesión de asistencia (toggle OFF).
     * Endpoint: POST ?action=asistencia/cerrarSesionAsistencia
     * Responde JSON.
     */
    public function cerrarSesionAsistencia(): void {
        header('Content-Type: application/json; charset=utf-8');
        Auth::requireLogin();

        if (!Auth::isInstructor()) {
            echo json_encode(['ok' => false, 'mensaje' => 'Acceso denegado. Solo instructores.']);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido.']);
            return;
        }

        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Auth::validateCSRF($csrfToken)) {
            echo json_encode(['ok' => false, 'mensaje' => 'Token CSRF inválido. Recarga la página.']);
            return;
        }

        $sesionId     = (int) ($_POST['sesion_id'] ?? 0);
        $instructorId = (int) Auth::getUserId();

        if ($sesionId <= 0) {
            echo json_encode(['ok' => false, 'mensaje' => 'Sesión no especificada.']);
            return;
        }

        $resultado = $this->sesionModel->cerrarSesion($sesionId, $instructorId);

        // Generar nuevo CSRF
        $resultado['csrf_token'] = Auth::generateCSRF();

        echo json_encode($resultado);
    }

    // =====================================================================
    //  API AJAX — Marcación RFID
    // =====================================================================

    /**
     * Registra una marcación RFID de un aprendiz.
     * Endpoint: POST ?action=asistencia/registrarMarcacion
     * Responde JSON.
     *
     * Flujo:
     *  1. Busca al aprendiz por codigo_llavero.
     *  2. Identifica la ficha del aprendiz.
     *  3. Verifica si hay sesión activa para esa ficha.
     *  4. Si no hay → rechaza con mensaje claro.
     *  5. Si hay → verifica duplicado, registra con cálculo de retardo.
     */
    public function registrarMarcacion(): void {
        header('Content-Type: application/json; charset=utf-8');
        Auth::requireLogin();

        if (!Auth::isInstructor()) {
            echo json_encode(['ok' => false, 'mensaje' => 'Acceso denegado.']);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido.']);
            return;
        }

        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Auth::validateCSRF($csrfToken)) {
            echo json_encode(['ok' => false, 'mensaje' => 'Token CSRF inválido. Recarga la página.', 'csrf_token' => Auth::generateCSRF()]);
            return;
        }

        $rfidTag = trim($_POST['rfid_tag'] ?? '');
        $instructorId = (int) Auth::getUserId();

        if ($rfidTag === '') {
            echo json_encode(['ok' => false, 'mensaje' => 'Código RFID vacío.', 'csrf_token' => Auth::generateCSRF()]);
            return;
        }

        // Sanear: solo alfanuméricos y guiones
        $rfidTag = preg_replace('/[^a-zA-Z0-9\-]/', '', $rfidTag);

        // 1. Buscar aprendiz por código de llavero
        $aprendiz = $this->aprendizModel->getByRfid($rfidTag);
        if ($aprendiz === null) {
            echo json_encode([
                'ok'         => false,
                'mensaje'    => 'Llavero RFID no reconocido.',
                'csrf_token' => Auth::generateCSRF(),
            ]);
            return;
        }

        $aprendizId = (int) $aprendiz['id'];
        $fichaId    = (int) ($aprendiz['Ficha_id'] ?? $aprendiz['id_ficha'] ?? 0);

        if ($fichaId <= 0) {
            echo json_encode([
                'ok'         => false,
                'mensaje'    => 'El aprendiz no tiene una ficha asignada.',
                'csrf_token' => Auth::generateCSRF(),
            ]);
            return;
        }

        // 2. Verificar sesión activa para esa ficha
        $sesionActiva = $this->sesionModel->obtenerSesionActivaPorFicha($fichaId);

        if ($sesionActiva === null) {
            echo json_encode([
                'ok'         => false,
                'mensaje'    => 'La toma de asistencia para tu ficha no está habilitada por el instructor en este momento.',
                'aprendiz'   => $aprendiz['nombre'] . ' ' . $aprendiz['apellido'],
                'csrf_token' => Auth::generateCSRF(),
            ]);
            return;
        }

        $sesionId      = (int) $sesionActiva['id'];
        $horaInicio    = $sesionActiva['hora_inicio'];

        // 3. Verificar que no haya registro duplicado en esta sesión
        if ($this->model->existeRegistroEnSesion($aprendizId, $sesionId)) {
            echo json_encode([
                'ok'         => false,
                'mensaje'    => 'Ya se registró la asistencia de este aprendiz en esta sesión.',
                'aprendiz'   => $aprendiz['nombre'] . ' ' . $aprendiz['apellido'],
                'csrf_token' => Auth::generateCSRF(),
            ]);
            return;
        }

        // 4. Registrar asistencia con cálculo de retardo (RF-HOR-42: hora base con apertura_real)
        $aperturaReal = $sesionActiva['apertura_real'] ?? null;
        $resultado = $this->model->registrarEntradaConRetardo(
            $aprendizId,
            $sesionId,
            $instructorId,
            $horaInicio,
            $rfidTag,
            $aperturaReal
        );

        $resultado['csrf_token'] = Auth::generateCSRF();
        $resultado['aprendiz']   = $aprendiz['nombre'] . ' ' . $aprendiz['apellido'];
        $resultado['documento']  = $aprendiz['identificacion'] ?? '';
        $resultado['ficha']      = $aprendiz['codigo_ficha'] ?? '';
        $resultado['hora']       = date('H:i:s');

        echo json_encode($resultado);
    }

    // =====================================================================
    //  VISTA — Historial
    // =====================================================================

    /**
     * Muestra el historial de asistencias
     */
    public function historial(): void {
        $pageTitle     = 'Historial de Asistencia';
        $currentAction = 'asistencia/historial';

        $ingresos     = [];
        $estadisticas = [];

        if (Auth::isAdminOrInstructor()) {
            // Lógica para Administradores e Instructores
            $fecha = $_GET['fecha'] ?? date('Y-m-d');

            // Validar fecha
            Validator::reset();
            Validator::date($fecha, 'fecha');
            if (!Validator::isValid()) {
                $fecha = date('Y-m-d');
            }

            $ingresos = $this->model->getHistorial($fecha);

            require_once ROOT_PATH . '/views/asistencia/historial.php';

        } elseif (Auth::isAprendiz()) {
            // El aprendiz usa su propio id de usuario directamente (ya no hay tabla aprendices)
            $idUsuario = (int) ($_SESSION['user_id'] ?? 0);

            // Filtro de fechas por defecto: Mes actual
            $fechaInicio = $_GET['fecha_inicio'] ?? date('Y-m-01');
            $fechaFin    = $_GET['fecha_fin']    ?? date('Y-m-t');

            // Validar fechas
            Validator::reset();
            Validator::date($fechaInicio, 'fecha de inicio');
            Validator::date($fechaFin,    'fecha de fin');

            if (!Validator::isValid()) {
                $fechaInicio = date('Y-m-01');
                $fechaFin    = date('Y-m-t');
            }

            $ingresos     = $this->model->getHistorialAprendiz($idUsuario, $fechaInicio, $fechaFin);
            $estadisticas = $this->model->getEstadisticasAprendiz($idUsuario, $fechaInicio, $fechaFin);

            require_once ROOT_PATH . '/views/asistencia/historial.php';

        } else {
            // Rol no reconocido
            header('Location: ' . BASE_URL);
            exit;
        }
    }

    // =====================================================================
    //  HELPERS PRIVADOS
    // =====================================================================

    /**
     * Verifica si un instructor está vinculado a una ficha.
     * Un instructor está vinculado si:
     *  - Es el instructor_id de la tabla Ficha, o
     *  - Tiene sesiones asignadas para esa ficha en la tabla sesion.
     */
    private function instructorVinculadoAFicha(int $instructorId, int $fichaId): bool
    {
        // Verificar por tabla Ficha
        $ficha = $this->fichaModel->getById($fichaId);
        if ($ficha && (int)($ficha['instructor_id'] ?? 0) === $instructorId) {
            return true;
        }

        // Verificar por sesiones asignadas
        $fichasSesion = $this->sesionModel->getFichasDeInstructor($instructorId);
        foreach ($fichasSesion as $f) {
            if ((int)$f['id'] === $fichaId) {
                return true;
            }
        }

        return false;
    }
}

