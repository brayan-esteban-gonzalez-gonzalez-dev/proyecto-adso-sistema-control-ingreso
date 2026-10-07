<?php
/**
 * AsistenciaController.php — Controlador de Asistencia
 *
 * Gestiona el historial de asistencias, filtros y estadísticas.
 * Nueva DB: tabla `asistencia`, campo Usuario_id (no id_aprendiz).
 * Los aprendices son Usuarios con Rol_id=3.
 */
class AsistenciaController {
    private PDO $db;
    private IngresoAsistencia $model;

    public function __construct(PDO $db) {
        $this->db = $db;

        // Cargar el modelo si no se cargó en index.php
        require_once ROOT_PATH . '/models/IngresoAsistencia.php';

        $this->model = new IngresoAsistencia($db);
    }

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

    /**
     * Muestra la pantalla de marcación RFID (kiosco)
     * Solo para Administrador/Instructor.
     */
    public function rfid(): void {
        if (!Auth::isAdminOrInstructor()) {
            header('Location: ' . BASE_URL . '?action=auth/login');
            exit;
        }

        $pageTitle     = 'Marcación RFID';
        $currentAction = 'asistencia/rfid';
        $csrf_token    = Auth::generateCSRF();

        // Últimos ingresos de hoy para la tabla
        $ultimosIngresos = $this->model->getHistorial(date('Y-m-d'));

        require_once ROOT_PATH . '/views/asistencia/rfid.php';
    }

    /**
     * Endpoint JSON para procesar marcación RFID.
     * ÚNICO endpoint JSON de todo el proyecto (excepción deliberada al patrón MVC con vistas).
     * Solo POST, solo Admin/Instructor.
     */
    public function marcar(): void {
        // Siempre responde JSON
        header('Content-Type: application/json; charset=utf-8');

        // Solo POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido.']);
            return;
        }

        // Requiere sesión Admin/Instructor (no redirect, responde 401 JSON)
        if (!Auth::isLoggedIn() || !Auth::isAdminOrInstructor()) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'mensaje' => 'No autorizado.']);
            return;
        }

        // Validar CSRF
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Auth::validateCSRF($csrfToken)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'mensaje' => 'Token de seguridad inválido. Recarga la página.']);
            return;
        }

        // Leer código de llavero
        $codigoLlavero = trim($_POST['codigo_llavero'] ?? '');
        if ($codigoLlavero === '') {
            echo json_encode(['ok' => false, 'mensaje' => 'Código de llavero vacío.']);
            return;
        }

        try {
            // 1. Buscar usuario por llavero
            $usuario = $this->model->buscarPorLlavero($codigoLlavero);
            if (!$usuario) {
                echo json_encode(['ok' => false, 'mensaje' => 'Llavero no reconocido.']);
                return;
            }

            $rolId          = (int) $usuario['Rol_id'];
            $nombreCompleto = trim($usuario['nombre'] . ' ' . $usuario['apellido']);
            $fechaHoy       = date('Y-m-d');
            $horaActual     = date('H:i:s');

            // 2. Un administrador (Rol 1) no marca asistencia desde el kiosco
            if ($rolId === 1) {
                echo json_encode([
                    'ok'      => false,
                    'mensaje' => 'Un administrador no marca asistencia desde este kiosco.',
                ]);
                return;
            }

            // 3. Resolver sesión activa según rol
            $sesion = null;
            if ($rolId === 3) {
                // Aprendiz: buscar por Ficha_id
                if (!$usuario['Ficha_id']) {
                    echo json_encode(['ok' => false, 'mensaje' => 'Este aprendiz no tiene ficha asignada.']);
                    return;
                }
                $sesion = $this->model->obtenerSesionActivaParaAprendiz(
                    (int) $usuario['Ficha_id'], $fechaHoy, $horaActual
                );
            } elseif ($rolId === 2) {
                // Instructor: buscar por Instructor_id
                $sesion = $this->model->obtenerSesionActivaParaInstructor(
                    (int) $usuario['id'], $fechaHoy, $horaActual
                );
            }

            if (!$sesion) {
                echo json_encode([
                    'ok'      => false,
                    'mensaje' => 'No hay una sesión en curso para esta ficha/instructor en este momento.',
                ]);
                return;
            }

            $idSesion       = (int) $sesion['id'];
            $operadorId     = Auth::getUserId();

            // 4. Buscar si ya existe asistencia para (Usuario_id, Sesion_id)
            $asistenciaExistente = $this->model->buscarAsistenciaDeHoy(
                (int) $usuario['id'], $idSesion
            );

            if (!$asistenciaExistente) {
                // ── ENTRADA ──
                $minutosRetardo = $this->calcularRetardo(
                    $rolId, $idSesion, $sesion, $horaActual
                );

                $ok = $this->model->registrarEntradaRfid(
                    (int) $usuario['id'],
                    $idSesion,
                    $operadorId,
                    $codigoLlavero,
                    $minutosRetardo
                );

                if (!$ok) {
                    http_response_code(500);
                    echo json_encode(['ok' => false, 'mensaje' => 'Error interno.']);
                    return;
                }

                $msgRetardo = $minutosRetardo > 0
                    ? " (retardo: {$minutosRetardo} min)"
                    : ' (a tiempo)';

                // Generar nuevo token CSRF para la siguiente marcación
                $nuevoToken = Auth::generateCSRF();

                echo json_encode([
                    'ok'         => true,
                    'tipo'       => 'entrada',
                    'usuario'    => $nombreCompleto,
                    'mensaje'    => "Entrada registrada para {$nombreCompleto}{$msgRetardo}.",
                    'csrf_token' => $nuevoToken,
                ]);
                return;
            }

            if ($asistenciaExistente['hora_salida'] === null) {
                // ── SALIDA ──
                $minutosAnticipacion = $this->calcularAnticipacion(
                    $sesion, $horaActual
                );

                $ok = $this->model->registrarSalidaRfid(
                    (int) $asistenciaExistente['id'],
                    $minutosAnticipacion
                );

                if (!$ok) {
                    http_response_code(500);
                    echo json_encode(['ok' => false, 'mensaje' => 'Error interno.']);
                    return;
                }

                $msgAnticipacion = $minutosAnticipacion > 0
                    ? " (salida anticipada: {$minutosAnticipacion} min)"
                    : '';

                // Generar nuevo token CSRF para la siguiente marcación
                $nuevoToken = Auth::generateCSRF();

                echo json_encode([
                    'ok'         => true,
                    'tipo'       => 'salida',
                    'usuario'    => $nombreCompleto,
                    'mensaje'    => "Salida registrada para {$nombreCompleto}{$msgAnticipacion}.",
                    'csrf_token' => $nuevoToken,
                ]);
                return;
            }

            // Ya tiene entrada Y salida
            echo json_encode([
                'ok'      => false,
                'mensaje' => 'Ya registraste entrada y salida en esta sesión.',
            ]);

        } catch (\PDOException $e) {
            error_log('RFID marcar() PDOException: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['ok' => false, 'mensaje' => 'Error interno.']);
        } catch (\Throwable $e) {
            error_log('RFID marcar() Error: ' . $e->getMessage());
            http_response_code(500);
            echo json_encode(['ok' => false, 'mensaje' => 'Error interno.']);
        }
    }

    // ─── Helpers privados para cálculos de puntualidad ───

    /**
     * Calcula minutos de retardo para la entrada.
     *
     * INSTRUCTOR (Rol 2): 0 minutos de tolerancia. Su retardo se mide directamente
     * contra sesion.hora_inicio. (Decisión: el instructor no tiene gracia extra.)
     *
     * APRENDIZ (Rol 3): 10 minutos de tolerancia desde la hora en que el instructor
     * de esa sesión marcó su entrada. Si el instructor aún no marcó, se usa
     * sesion.hora_inicio como fallback (+ los 10 minutos de tolerancia igualmente).
     */
    private function calcularRetardo(int $rolId, int $idSesion, array $sesion, string $horaActual): int {
        $ahora = new \DateTime($horaActual);

        if ($rolId === 2) {
            // Instructor: retardo = max(0, ahora - sesion.hora_inicio)
            $inicio = new \DateTime($sesion['hora_inicio']);
            if ($ahora <= $inicio) {
                return 0;
            }
            $diff = $inicio->diff($ahora);
            return ($diff->h * 60) + $diff->i;
        }

        // Aprendiz (Rol 3)
        $asistenciaInstructor = $this->model->buscarAsistenciaInstructorEnSesion($idSesion);

        if ($asistenciaInstructor && $asistenciaInstructor['hora_entrada']) {
            // Referencia = hora de llegada del instructor
            $referencia = new \DateTime($asistenciaInstructor['hora_entrada']);
        } else {
            // Fallback: sesion.hora_inicio
            $referencia = new \DateTime($sesion['hora_inicio']);
        }

        // Sumar 10 minutos de tolerancia
        $limiteTolerancia = clone $referencia;
        $limiteTolerancia->modify('+10 minutes');

        if ($ahora <= $limiteTolerancia) {
            return 0; // A tiempo
        }

        $diff = $limiteTolerancia->diff($ahora);
        return ($diff->h * 60) + $diff->i;
    }

    /**
     * Calcula minutos de anticipación para la salida.
     * Si el usuario sale antes de sesion.hora_fin, se registra la diferencia.
     */
    private function calcularAnticipacion(array $sesion, string $horaActual): int {
        $ahora = new \DateTime($horaActual);
        $fin   = new \DateTime($sesion['hora_fin']);

        if ($ahora >= $fin) {
            return 0; // Salió a la hora o después
        }

        $diff = $ahora->diff($fin);
        return ($diff->h * 60) + $diff->i;
    }
}
