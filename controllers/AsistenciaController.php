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
     * Pantalla de marcación RFID y Manual / Toggle de asistencia.
     * Accesible para Instructores y Administradores.
     */
    public function rfid(): void {
        Auth::requireLogin();
        if (!Auth::isAdminOrInstructor()) {
            Auth::setFlash('error', 'La marcación de asistencia está reservada para Instructores y Administradores.');
            header('Location: ' . BASE_URL . '?action=dashboard');
            exit;
        }

        $instructorId = (int) Auth::getUserId();
        $isAdmin      = Auth::isAdmin();

        if ($isAdmin) {
            $fichas = $this->fichaModel->getAll();
            $sesionesActivas = $this->sesionModel->obtenerSesionesActivasHoy();
        } else {
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
        }

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

        $pageTitle = 'Marcación de Asistencia (RFID y Manual)';
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

        if (!Auth::isAdminOrInstructor()) {
            echo json_encode([
                'ok'         => false,
                'mensaje'    => 'Acceso denegado. Solo instructores y administradores.',
                'csrf_token' => Auth::generateCSRF(),
            ]);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode([
                'ok'         => false,
                'mensaje'    => 'Método no permitido.',
                'csrf_token' => Auth::generateCSRF(),
            ]);
            return;
        }

        // Validar CSRF
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Auth::validateCSRF($csrfToken)) {
            echo json_encode([
                'ok'         => false,
                'mensaje'    => 'Token CSRF inválido. Recarga la página.',
                'csrf_token' => Auth::generateCSRF(),
            ]);
            return;
        }

        $fichaId      = (int) ($_POST['ficha_id'] ?? 0);
        $instructorId = (int) Auth::getUserId();

        if ($fichaId <= 0) {
            echo json_encode([
                'ok'         => false,
                'mensaje'    => 'Ficha no especificada.',
                'csrf_token' => Auth::generateCSRF(),
            ]);
            return;
        }

        // Verificar que el instructor está vinculado a esta ficha (o es admin)
        if (!$this->instructorVinculadoAFicha($instructorId, $fichaId)) {
            echo json_encode([
                'ok'         => false,
                'mensaje'    => 'No estás vinculado a esta ficha.',
                'csrf_token' => Auth::generateCSRF(),
            ]);
            return;
        }

        // Verificar que no haya sesión activa ya
        $existente = $this->sesionModel->obtenerSesionActivaPorFicha($fichaId);
        if ($existente !== null) {
            $nomina = $this->model->getNominaSesionActiva($fichaId, (int)$existente['id']);
            echo json_encode([
                'ok'         => false,
                'mensaje'    => 'Ya existe una sesión activa para esta ficha.',
                'sesion_id'  => (int)$existente['id'],
                'nomina'     => $nomina,
                'csrf_token' => Auth::generateCSRF(),
            ]);
            return;
        }

        $sesionId = $this->sesionModel->abrirSesion($fichaId, $instructorId);

        if ($sesionId === false) {
            echo json_encode([
                'ok'         => false,
                'mensaje'    => 'No se pudo iniciar la sesión.',
                'csrf_token' => Auth::generateCSRF(),
            ]);
            return;
        }

        $nomina = $this->model->getNominaSesionActiva($fichaId, $sesionId);

        echo json_encode([
            'ok'         => true,
            'sesion_id'  => $sesionId,
            'ficha_id'   => $fichaId,
            'mensaje'    => 'Sesión de asistencia iniciada.',
            'nomina'     => $nomina,
            'csrf_token' => Auth::generateCSRF(),
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

        if (!Auth::isAdminOrInstructor()) {
            echo json_encode([
                'ok'         => false,
                'mensaje'    => 'Acceso denegado.',
                'csrf_token' => Auth::generateCSRF(),
            ]);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode([
                'ok'         => false,
                'mensaje'    => 'Método no permitido.',
                'csrf_token' => Auth::generateCSRF(),
            ]);
            return;
        }

        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Auth::validateCSRF($csrfToken)) {
            echo json_encode([
                'ok'         => false,
                'mensaje'    => 'Token CSRF inválido. Recarga la página.',
                'csrf_token' => Auth::generateCSRF(),
            ]);
            return;
        }

        $sesionId     = (int) ($_POST['sesion_id'] ?? 0);
        $instructorId = (int) Auth::getUserId();

        if ($sesionId <= 0) {
            echo json_encode([
                'ok'         => false,
                'mensaje'    => 'Sesión no especificada.',
                'csrf_token' => Auth::generateCSRF(),
            ]);
            return;
        }

        $resultado = $this->sesionModel->cerrarSesion($sesionId, $instructorId);

        // Generar nuevo CSRF en todos los casos
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
     */
    public function registrarMarcacion(): void {
        header('Content-Type: application/json; charset=utf-8');
        Auth::requireLogin();

        if (!Auth::isAdminOrInstructor()) {
            echo json_encode([
                'ok'         => false,
                'success'    => false,
                'mensaje'    => 'Acceso denegado.',
                'csrf_token' => Auth::generateCSRF(),
            ]);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode([
                'ok'         => false,
                'success'    => false,
                'mensaje'    => 'Método no permitido.',
                'csrf_token' => Auth::generateCSRF(),
            ]);
            return;
        }

        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Auth::validateCSRF($csrfToken)) {
            echo json_encode([
                'ok'         => false,
                'success'    => false,
                'mensaje'    => 'Token CSRF inválido. Recarga la página.',
                'csrf_token' => Auth::generateCSRF(),
            ]);
            return;
        }

        $rfidTag = trim($_POST['rfid_tag'] ?? '');
        $instructorId = (int) Auth::getUserId();

        if ($rfidTag === '') {
            echo json_encode([
                'ok'         => false,
                'success'    => false,
                'mensaje'    => 'Código RFID vacío.',
                'csrf_token' => Auth::generateCSRF(),
            ]);
            return;
        }

        // Sanear: solo alfanuméricos y guiones
        $rfidTag = preg_replace('/[^a-zA-Z0-9\-]/', '', $rfidTag);

        // 1. Buscar aprendiz por código de llavero
        $aprendiz = $this->aprendizModel->getByRfid($rfidTag);
        if ($aprendiz === null) {
            echo json_encode([
                'ok'         => false,
                'success'    => false,
                'mensaje'    => 'Llavero RFID no reconocido en el sistema.',
                'csrf_token' => Auth::generateCSRF(),
            ]);
            return;
        }

        $aprendizId = (int) $aprendiz['id'];
        $fichaId    = (int) ($aprendiz['Ficha_id'] ?? $aprendiz['id_ficha'] ?? 0);

        if ($fichaId <= 0) {
            echo json_encode([
                'ok'         => false,
                'success'    => false,
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
                'success'    => false,
                'mensaje'    => 'La toma de asistencia para esta ficha no está habilitada en este momento.',
                'aprendiz'   => $aprendiz['nombre'] . ' ' . $aprendiz['apellido'],
                'csrf_token' => Auth::generateCSRF(),
            ]);
            return;
        }

        $sesionId   = (int) $sesionActiva['id'];
        $horaInicio = $sesionActiva['hora_inicio'];

        // 3. Verificar que no haya registro duplicado en esta sesión
        if ($this->model->existeRegistroEnSesion($aprendizId, $sesionId)) {
            echo json_encode([
                'ok'         => false,
                'success'    => false,
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

        // 5. Cargar nómina actualizada para sincronizar en tiempo real la UI
        $nominaActualizada = $this->model->getNominaSesionActiva($fichaId, $sesionId);

        $resultado['success']            = $resultado['ok'];
        $resultado['message']            = $resultado['mensaje'];
        $resultado['csrf_token']         = Auth::generateCSRF();
        $resultado['aprendiz']           = $aprendiz['nombre'] . ' ' . $aprendiz['apellido'];
        $resultado['documento']          = $aprendiz['identificacion'] ?? '';
        $resultado['ficha']              = $aprendiz['codigo_ficha'] ?? '';
        $resultado['hora']               = date('H:i:s');
        $resultado['nomina_actualizada'] = $nominaActualizada;

        echo json_encode($resultado);
    }

    // =====================================================================
    //  API AJAX — Marcación Manual de Asistencia (Respaldo)
    // =====================================================================

    /**
     * Registra una marcación de asistencia de respaldo manual por PIN o Documento.
     * Endpoint: POST ?action=asistencia/registrarMarcacionManualPin
     * Responde JSON con: success, message, aprendiz_nombre, hora, etc.
     */
    public function registrarMarcacionManualPin(): void {
        $this->procesarMarcacionManual();
    }

    /**
     * Registra una marcación de asistencia de manera manual (por documento o ID de aprendiz).
     * Endpoint: POST ?action=asistencia/registrarManual
     * Responde JSON.
     */
    public function registrarManual(): void {
        $this->procesarMarcacionManual();
    }

    /**
     * Lógica central de procesamiento para marcaciones manuales (Documento, PIN o ID nómina).
     */
    private function procesarMarcacionManual(): void {
        header('Content-Type: application/json; charset=utf-8');
        Auth::requireLogin();

        if (!Auth::isAdminOrInstructor()) {
            echo json_encode([
                'ok'              => false,
                'success'         => false,
                'mensaje'         => 'Acceso denegado.',
                'message'         => 'Acceso denegado.',
                'aprendiz_nombre' => null,
                'hora'            => date('H:i:s'),
                'csrf_token'      => Auth::generateCSRF(),
            ]);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode([
                'ok'              => false,
                'success'         => false,
                'mensaje'         => 'Método no permitido.',
                'message'         => 'Método no permitido.',
                'aprendiz_nombre' => null,
                'hora'            => date('H:i:s'),
                'csrf_token'      => Auth::generateCSRF(),
            ]);
            return;
        }

        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Auth::validateCSRF($csrfToken)) {
            echo json_encode([
                'ok'              => false,
                'success'         => false,
                'mensaje'         => 'Token CSRF inválido. Recarga la página.',
                'message'         => 'Token CSRF inválido. Recarga la página.',
                'aprendiz_nombre' => null,
                'hora'            => date('H:i:s'),
                'csrf_token'      => Auth::generateCSRF(),
            ]);
            return;
        }

        $instructorId = (int) Auth::getUserId();
        $fichaId      = (int) ($_POST['ficha_id'] ?? 0);
        $sesionId     = (int) ($_POST['sesion_id'] ?? 0);
        $documento    = trim((string)($_POST['documento'] ?? ($_POST['pin'] ?? '')));
        $aprendizId   = (int) ($_POST['aprendiz_id'] ?? 0);

        // 1. Resolver sesión activa si no viene dada explícitamente
        $sesionActiva = null;
        if ($sesionId <= 0 && $fichaId > 0) {
            $sesionActiva = $this->sesionModel->obtenerSesionActivaPorFicha($fichaId);
            if ($sesionActiva !== null) {
                $sesionId = (int) $sesionActiva['id'];
            }
        } elseif ($sesionId > 0) {
            $sesionActiva = $this->sesionModel->getById($sesionId);
            if ($sesionActiva) {
                $fichaId = (int) $sesionActiva['Ficha_id'];
            }
        }

        if ($sesionId <= 0 || empty($sesionActiva) || ($sesionActiva['estado'] ?? '') !== 'Activo') {
            echo json_encode([
                'ok'              => false,
                'success'         => false,
                'mensaje'         => 'No hay una sesión activa para esta ficha. Debes encender la asistencia primero.',
                'message'         => 'No hay una sesión activa para esta ficha. Debes encender la asistencia primero.',
                'aprendiz_nombre' => null,
                'hora'            => date('H:i:s'),
                'csrf_token'      => Auth::generateCSRF(),
            ]);
            return;
        }

        // 2. Anti-IDOR: validar vinculación del instructor si no es Administrador
        if (!Auth::isAdmin()) {
            if (!$this->instructorVinculadoAFicha($instructorId, $fichaId) && (int)($sesionActiva['Instructor_id'] ?? 0) !== $instructorId) {
                echo json_encode([
                    'ok'              => false,
                    'success'         => false,
                    'mensaje'         => 'No tienes permisos para registrar asistencia en esta ficha.',
                    'message'         => 'No tienes permisos para registrar asistencia en esta ficha.',
                    'aprendiz_nombre' => null,
                    'hora'            => date('H:i:s'),
                    'csrf_token'      => Auth::generateCSRF(),
                ]);
                return;
            }
        }

        // 3. Buscar aprendiz distinguiendo explícitamente búsqueda por ID o por Documento
        if ($aprendizId > 0) {
            $busqueda = $this->model->buscarAprendizPorId($aprendizId, $fichaId);
        } elseif ($documento !== '') {
            $busqueda = $this->model->buscarAprendizPorDocumento($documento, $fichaId);
        } else {
            echo json_encode([
                'ok'              => false,
                'success'         => false,
                'mensaje'         => 'Debes ingresar el número de documento o seleccionar un aprendiz de la nómina.',
                'message'         => 'Debes ingresar el número de documento o seleccionar un aprendiz de la nómina.',
                'aprendiz_nombre' => null,
                'hora'            => date('H:i:s'),
                'csrf_token'      => Auth::generateCSRF(),
            ]);
            return;
        }

        if (!$busqueda['ok']) {
            echo json_encode([
                'ok'              => false,
                'success'         => false,
                'mensaje'         => $busqueda['mensaje'],
                'message'         => $busqueda['mensaje'],
                'aprendiz_nombre' => $busqueda['aprendiz']['nombre_completo'] ?? null,
                'aprendiz'        => $busqueda['aprendiz']['nombre_completo'] ?? null,
                'hora'            => date('H:i:s'),
                'csrf_token'      => Auth::generateCSRF(),
            ]);
            return;
        }

        $aprendiz          = $busqueda['aprendiz'];
        $idUsuarioAprendiz = (int) $aprendiz['id'];

        // 4. Prevenir doble marcación
        if ($this->model->existeRegistroEnSesion($idUsuarioAprendiz, $sesionId)) {
            echo json_encode([
                'ok'              => false,
                'success'         => false,
                'mensaje'         => 'Ya se registró la asistencia de este aprendiz en la sesión actual.',
                'message'         => 'Ya se registró la asistencia de este aprendiz en la sesión actual.',
                'aprendiz_nombre' => $aprendiz['nombre_completo'],
                'aprendiz'        => $aprendiz['nombre_completo'],
                'documento'       => $aprendiz['documento'],
                'hora'            => date('H:i:s'),
                'csrf_token'      => Auth::generateCSRF(),
            ]);
            return;
        }

        // 5. Registrar asistencia con cálculo de retardo (tolerancia 10 min)
        $horaInicio   = $sesionActiva['hora_inicio'];
        $aperturaReal = $sesionActiva['apertura_real'] ?? null;

        $resultado = $this->model->registrarEntradaConRetardo(
            $idUsuarioAprendiz,
            $sesionId,
            $instructorId,
            $horaInicio,
            $aprendiz['rfid_tag'] ?? null,
            $aperturaReal
        );

        // 6. Obtener nómina actualizada de la sesión
        $nomina = $this->model->getNominaSesionActiva($fichaId, $sesionId);

        echo json_encode([
            'ok'                 => $resultado['ok'],
            'success'            => $resultado['ok'],
            'mensaje'            => $resultado['mensaje'],
            'message'            => $resultado['mensaje'],
            'aprendiz_nombre'    => $aprendiz['nombre_completo'],
            'aprendiz'           => $aprendiz['nombre_completo'],
            'documento'          => $aprendiz['documento'],
            'ficha'              => $sesionActiva['codigo_ficha'] ?? ($aprendiz['codigo_ficha'] ?? ''),
            'estado_asistencia'  => $resultado['estado'],
            'minutos_retardo'    => $resultado['minutos_retardo'],
            'hora'               => date('H:i:s'),
            'nomina_actualizada' => $nomina,
            'csrf_token'         => Auth::generateCSRF(),
        ]);
    }

    // =====================================================================
    //  API AJAX — Carga de Nómina en Tiempo Real
    // =====================================================================

    /**
     * Retorna la nómina de aprendices de una ficha con su estado en la sesión activa.
     * Endpoint: GET / POST ?action=asistencia/getNominaSesion
     * Responde JSON.
     */
    public function getNominaSesion(): void {
        header('Content-Type: application/json; charset=utf-8');
        Auth::requireLogin();

        if (!Auth::isAdminOrInstructor()) {
            echo json_encode([
                'ok'         => false,
                'mensaje'    => 'Acceso denegado.',
                'csrf_token' => Auth::generateCSRF(),
            ]);
            return;
        }

        $instructorId = (int) Auth::getUserId();
        $fichaId      = (int) ($_REQUEST['ficha_id'] ?? 0);
        $sesionId     = (int) ($_REQUEST['sesion_id'] ?? 0);

        if ($fichaId <= 0 && $sesionId <= 0) {
            echo json_encode([
                'ok'         => false,
                'mensaje'    => 'Ficha o sesión no especificada.',
                'csrf_token' => Auth::generateCSRF(),
            ]);
            return;
        }

        // Si no se proporcionó sesión pero sí ficha, buscar sesión activa
        if ($sesionId <= 0) {
            $sesion = $this->sesionModel->obtenerSesionActivaPorFicha($fichaId);
            if ($sesion) {
                $sesionId = (int) $sesion['id'];
            }
        } elseif ($fichaId <= 0) {
            $sesion = $this->sesionModel->getById($sesionId);
            if ($sesion) {
                $fichaId = (int) $sesion['Ficha_id'];
            }
        }

        // Anti-IDOR para instructor
        if (!Auth::isAdmin()) {
            if (!$this->instructorVinculadoAFicha($instructorId, $fichaId)) {
                echo json_encode([
                    'ok'         => false,
                    'mensaje'    => 'No tienes permisos para consultar la nómina de esta ficha.',
                    'csrf_token' => Auth::generateCSRF(),
                ]);
                return;
            }
        }

        $nomina = ($fichaId > 0 && $sesionId > 0)
            ? $this->model->getNominaSesionActiva($fichaId, $sesionId)
            : [];

        echo json_encode([
            'ok'         => true,
            'ficha_id'   => $fichaId,
            'sesion_id'  => $sesionId,
            'nomina'     => $nomina,
            'csrf_token' => Auth::generateCSRF(),
        ]);
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
     * Los administradores tienen acceso global garantizado.
     */
    private function instructorVinculadoAFicha(int $instructorId, int $fichaId): bool
    {
        if (Auth::isAdmin()) {
            return true;
        }

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

