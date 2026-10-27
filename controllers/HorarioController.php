<?php

/**
 * HorarioController.php — Controlador de Horarios y Sesiones
 *
 * Gestión de horarios e importación masiva desde archivos Excel (calendario visual).
 */

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

require_once ROOT_PATH . '/helpers/HorarioParser.php';
require_once ROOT_PATH . '/helpers/HorarioValidator.php';
require_once ROOT_PATH . '/helpers/HorarioSincronizador.php';
require_once ROOT_PATH . '/helpers/HorarioReporte.php';
require_once ROOT_PATH . '/models/AsignacionInstructor.php';
require_once ROOT_PATH . '/models/ImportacionHorario.php';

class HorarioController
{
    private PDO $db;
    private Sesion $sesionModel;
    private Ficha $fichaModel;
    private Usuario $usuarioModel;
    private HorarioParser $horarioParser;
    private HorarioValidator $horarioValidator;
    private HorarioSincronizador $horarioSincronizador;
    private HorarioReporte $horarioReporte;
    private PermutaSesion $permutaModel;
    private AsignacionInstructor $asignacionModel;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->sesionModel = new Sesion($db);
        $this->fichaModel = new Ficha($db);
        $this->usuarioModel = new Usuario($db);
        $this->asignacionModel = new AsignacionInstructor($db);
        $this->horarioParser = new HorarioParser();
        $this->horarioValidator = new HorarioValidator($this->usuarioModel, $this->asignacionModel);
        $this->horarioSincronizador = new HorarioSincronizador($db, $this->usuarioModel, $this->asignacionModel);
        $this->horarioReporte = new HorarioReporte();

        require_once ROOT_PATH . '/models/PermutaSesion.php';
        $this->permutaModel = new PermutaSesion($db);
    }

    /**
     * Lista las sesiones de horario programadas
     */
    public function index(): void
    {
        Auth::requireLogin();

        // RF-HOR-29: Si es aprendiz, redirigir a miHorario (vista de su ficha)
        if (Auth::isAprendiz()) {
            header('Location: ' . BASE_URL . '?action=horarios/miHorario');
            exit;
        }

        $pageTitle = 'Gestion de Horarios';
        $fichaId = isset($_GET['ficha_id']) && !empty($_GET['ficha_id']) ? (int)$_GET['ficha_id'] : null;
        $instructorId = isset($_GET['instructor_id']) && !empty($_GET['instructor_id']) ? (int)$_GET['instructor_id'] : null;
        
        $fichas = $this->fichaModel->getAll();
        $instructores = $this->usuarioModel->getInstructores();

        $sesiones = $this->sesionModel->getFiltradas($fichaId, $instructorId);
        $fichaSeleccionada = $fichaId ? $this->fichaModel->getById($fichaId) : null;
        $instructorSeleccionado = $instructorId ? $this->usuarioModel->getById($instructorId) : null;

        require_once ROOT_PATH . '/views/horarios/index.php';
    }

    /**
     * Portal del instructor / aprendiz: carga horaria semanal y mensual en calendario (RF-HOR-29, RF-HOR-30)
     */
    public function miHorario(): void
    {
        Auth::requireLogin();
        $esAprendiz = Auth::isAprendiz();
        $esInstructor = Auth::isInstructor();
        $esAdmin = Auth::isAdmin();

        $userId = (int) Auth::getUserId();
        $vistaGet = $_GET['vista'] ?? '';
        $vista = (is_string($vistaGet) && $vistaGet === 'mes') ? 'mes' : 'semana';
        $fechaGet = $_GET['fecha'] ?? null;
        $fechaRef = $this->fechaValida(is_string($fechaGet) ? $fechaGet : null);
        
        $fichaId = null;
        if ($esAprendiz) {
            // El aprendiz ve exclusivamente su ficha asignada
            $aprendizData = $this->usuarioModel->getById($userId);
            $fichaId = (int) ($aprendizData['Ficha_id'] ?? 0);
            if ($fichaId <= 0) {
                Auth::setFlash('info', 'Aún no tienes una ficha de formación asignada.');
                header('Location: ' . BASE_URL . '?action=dashboard');
                exit;
            }
        } else {
            $fichaGet = $_GET['ficha_id'] ?? '';
            $fichaId = is_scalar($fichaGet) && $fichaGet !== '' ? (int) $fichaGet : null;
            if ($fichaId !== null && $fichaId <= 0) {
                $fichaId = null;
            }
        }

        $estadosValidos = ['Activo', 'Finalizada', 'Cancelado', 'Permutado'];
        $estadoGet = $_GET['estado'] ?? '';
        $estado = is_string($estadoGet) && in_array($estadoGet, $estadosValidos, true) ? $estadoGet : '';

        $ref = DateTime::createFromFormat('!Y-m-d', $fechaRef);
        $inicioSemana = (clone $ref)->modify('-' . ((int) $ref->format('N') - 1) . ' days');
        $finSemana = (clone $inicioSemana)->modify('+6 days');
        $inicioMes = (clone $ref)->modify('first day of this month');
        $finMes = (clone $ref)->modify('last day of this month');
        $gridStart = (clone $inicioMes)->modify('-' . ((int) $inicioMes->format('N') - 1) . ' days');
        $gridEnd = (clone $finMes)->modify('+' . (7 - (int) $finMes->format('N')) . ' days');

        $iniSemStr = $inicioSemana->format('Y-m-d');
        $finSemStr = $finSemana->format('Y-m-d');
        $iniMesStr = $inicioMes->format('Y-m-d');
        $finMesStr = $finMes->format('Y-m-d');
        $desde = min($gridStart->format('Y-m-d'), $iniSemStr);
        $hasta = max($gridEnd->format('Y-m-d'), $finSemStr);

        if ($esAprendiz) {
            $sesiones = $this->sesionModel->getByFichaRango($fichaId, $desde, $hasta);
        } else {
            $sesiones = $this->sesionModel->getByInstructor($userId, $desde, $hasta, $fichaId, $estado !== '' ? $estado : null);
        }
        foreach ($sesiones as $i => $s) {
            $sesiones[$i] = $this->decorarSesion($s);
        }

        $eventosJson = array_map(function (array $s): array {
            return [
                'id'                 => (int) $s['id'],
                'fecha'              => $s['fecha'],
                'fecha_fmt'          => $s['fecha_fmt'],
                'dia_nombre'         => $s['dia_nombre'],
                'etiqueta'           => $s['etiqueta'],
                'hora_ini_fmt'       => $s['hora_ini_fmt'],
                'hora_fin_fmt'       => $s['hora_fin_fmt'],
                'horas_fmt'          => $s['horas_fmt'],
                'codigo_ficha'       => (string) $s['codigo_ficha'],
                'nombre_programa'    => (string) ($s['nombre_programa'] ?? ''),
                'nombre_jornada'     => (string) ($s['nombre_jornada'] ?? ''),
                'tipo_instructor'    => (string) ($s['tipo_instructor'] ?? ''),
                'especialidad'       => (string) ($s['especialidad'] ?? ''),
                'nivel'              => (string) ($s['nivel'] ?? ''),
                'grupo_convergente'  => (string) ($s['grupo_convergente'] ?? ''),
                'nombre_competencia' => (string) ($s['nombre_competencia'] ?? ''),
                'estado'             => (string) $s['estado'],
                'color'              => $s['color'],
            ];
        }, $sesiones);
        $jsonEventos = json_encode($eventosJson, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
        if ($jsonEventos === false) {
            $jsonEventos = '[]';
        }

        $porFecha = [];
        foreach ($sesiones as $s) {
            $porFecha[$s['fecha']][] = $s;
        }

        $sesionesSemanaLista = array_values(array_filter(
            $sesiones,
            fn(array $s): bool => $this->enRango($s['fecha'], $iniSemStr, $finSemStr)
        ));
        $horaDesde = 7;
        $horaHasta = 18;
        foreach ($sesionesSemanaLista as $s) {
            $ini = $this->aMinutos($s['hora_inicio']);
            $fin = $this->aMinutos($s['hora_fin']);
            if ($fin <= $ini) {
                $fin = $ini + 60;
            }
            $horaDesde = min($horaDesde, intdiv($ini, 60));
            $horaHasta = max($horaHasta, (int) ceil($fin / 60));
        }
        $horaDesde = max(0, $horaDesde);
        $horaHasta = min(24, max($horaHasta, $horaDesde + 1));
        $hourPx = 52;

        $diasSemana = [];
        $cursor = clone $inicioSemana;
        for ($i = 0; $i < 7; $i++) {
            $fecha = $cursor->format('Y-m-d');
            $diasSemana[] = [
                'fecha'       => $fecha,
                'nombre'      => $this->nombreDia($cursor, true),
                'nombreLargo' => $this->nombreDia($cursor),
                'numero'      => $cursor->format('j'),
                'etiqueta'    => $this->nombreDia($cursor) . ' ' . $cursor->format('j') . ' de ' . $this->nombreMes((int) $cursor->format('n')),
                'esHoy'       => $fecha === date('Y-m-d'),
                'sesiones'    => $this->layoutDia($porFecha[$fecha] ?? [], $horaDesde, $hourPx),
            ];
            $cursor->modify('+1 day');
        }

        $celdasMes = [];
        $cursor = clone $gridStart;
        while ($cursor <= $gridEnd) {
            $fecha = $cursor->format('Y-m-d');
            $lista = $porFecha[$fecha] ?? [];
            $celdasMes[] = [
                'fecha'    => $fecha,
                'numero'   => $cursor->format('j'),
                'esHoy'    => $fecha === date('Y-m-d'),
                'enMes'    => $cursor->format('Y-m') === $inicioMes->format('Y-m'),
                'etiqueta' => $this->nombreDia($cursor) . ' ' . $cursor->format('j') . ' de ' . $this->nombreMes((int) $cursor->format('n')),
                'urlDia'   => $this->urlMiHorario('semana', $fecha, $fichaId, $estado),
                'sesiones' => $lista,
                'extra'    => max(0, count($lista) - 3),
            ];
            $cursor->modify('+1 day');
        }

        $agenda = [];
        foreach ($porFecha as $fecha => $lista) {
            $dt = DateTime::createFromFormat('!Y-m-d', $fecha) ?: new DateTime($fecha);
            $agenda[] = [
                'fecha'    => $fecha,
                'etiqueta' => $this->nombreDia($dt) . ' ' . $dt->format('j') . ' de ' . $this->nombreMes((int) $dt->format('n')),
                'enSemana' => $this->enRango($fecha, $iniSemStr, $finSemStr),
                'enMes'    => $this->enRango($fecha, $iniMesStr, $finMesStr),
                'sesiones' => $lista,
            ];
        }
        usort($agenda, fn(array $a, array $b): int => strcmp($a['fecha'], $b['fecha']));

        $horasSemana = 0.0;
        $horasMes = 0.0;
        $sesionesMes = 0;
        $fichasMes = [];
        foreach ($sesiones as $s) {
            if ($this->enRango($s['fecha'], $iniSemStr, $finSemStr)) {
                $horasSemana += (float) $s['horas'];
            }
            if ($this->enRango($s['fecha'], $iniMesStr, $finMesStr)) {
                $horasMes += (float) $s['horas'];
                $sesionesMes++;
                $fichasMes[(int) $s['Ficha_id']] = true;
            }
        }

        $leyenda = [];
        foreach ($sesiones as $s) {
            $idFicha = (int) $s['Ficha_id'];
            if (!isset($leyenda[$idFicha])) {
                $leyenda[$idFicha] = [
                    'codigo'   => $s['codigo_ficha'],
                    'programa' => $s['nombre_programa'] ?? '',
                    'color'    => $this->colorFicha($idFicha),
                ];
            }
        }

        $fichasFiltro = $esAprendiz ? [] : $this->sesionModel->getFichasDeInstructor($userId);
        $pageTitle = $esAprendiz ? 'Horario de mi Ficha' : 'Mi Horario';
        $nombreInstructor = Auth::getUserFullName();
        $tituloSemana = $this->tituloSemana($inicioSemana, $finSemana);
        $tituloMes = $this->nombreMes((int) $inicioMes->format('n')) . ' ' . $inicioMes->format('Y');
        $urlSemana = $this->urlMiHorario('semana', $fechaRef, $fichaId, $estado);
        $urlMes = $this->urlMiHorario('mes', $fechaRef, $fichaId, $estado);
        $urlPrevSemana = $this->urlMiHorario('semana', (clone $inicioSemana)->modify('-7 days')->format('Y-m-d'), $fichaId, $estado);
        $urlNextSemana = $this->urlMiHorario('semana', (clone $inicioSemana)->modify('+7 days')->format('Y-m-d'), $fichaId, $estado);
        $urlPrevMes = $this->urlMiHorario('mes', (clone $ref)->modify('first day of last month')->format('Y-m-d'), $fichaId, $estado);
        $urlNextMes = $this->urlMiHorario('mes', (clone $ref)->modify('first day of next month')->format('Y-m-d'), $fichaId, $estado);
        $urlHoySemana = $this->urlMiHorario('semana', date('Y-m-d'), $fichaId, $estado);
        $urlHoyMes = $this->urlMiHorario('mes', date('Y-m-d'), $fichaId, $estado);
        $urlLimpiar = $this->urlMiHorario($vista, $fechaRef, null, '');
        $hayFiltro = $fichaId || $estado !== '';
        $resumen = [
            'sesiones_semana' => count($sesionesSemanaLista),
            'horas_semana'    => $this->formatoHoras($horasSemana),
            'sesiones_mes'    => $sesionesMes,
            'horas_mes'       => $this->formatoHoras($horasMes),
            'fichas_mes'      => count($fichasMes),
        ];

        require_once ROOT_PATH . '/views/horarios/mi_horario.php';
    }

    /**
     * Muestra el formulario para importar horarios desde Excel
     */
    public function importar(): void
    {
        Auth::requireAdmin();

        $pageTitle = 'Importar Horarios desde Excel';
        $fichas = $this->fichaModel->getAll();
        $csrfToken = Auth::generateCSRF();

        require_once ROOT_PATH . '/views/horarios/importar.php';
    }

    /**
     * Procesa la subida y extracción del archivo Excel
     */
    public function procesar(): void
    {
        Auth::requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '?action=horarios');
            exit;
        }

        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Auth::validateCSRF($csrfToken)) {
            Auth::setFlash('error', 'Token de seguridad inválido o expirado.');
            header('Location: ' . BASE_URL . '?action=horarios/importar');
            exit;
        }

        if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            Auth::setFlash('error', 'Por favor selecciona un archivo Excel válido (.xlsx o .xls).');
            header('Location: ' . BASE_URL . '?action=horarios/importar');
            exit;
        }

        $fileTmp = $_FILES['archivo']['tmp_name'];
        $fileName = $_FILES['archivo']['name'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($ext, ['xlsx', 'xls'])) {
            Auth::setFlash('error', 'El formato del archivo debe ser .xlsx o .xls.');
            header('Location: ' . BASE_URL . '?action=horarios/importar');
            exit;
        }

        try {
            $tipoHorario = ($_POST['tipo_horario'] ?? 'ficha') === 'instructor' ? 'instructor' : 'ficha';
            $anio = (int) ($_POST['anio'] ?? date('Y'));
            if ($anio < 2000 || $anio > 2100) {
                $anio = (int) date('Y');
            }

            $parseResult = $this->horarioParser->parsear($fileTmp, [
                'tipo' => $tipoHorario,
                'año'  => $anio,
            ]);

            // Validación (Tarea A.2)
            if ($tipoHorario === 'instructor') {
                $resultado = $this->procesarHorarioInstructor($parseResult, $fileName);
                $pageTitle = 'Resultado de la Importación';
                require_once ROOT_PATH . '/views/horarios/resultado.php';
                return;
            }

            $fichaDetectada = $this->fichaModel->getByCodigo($parseResult['meta']['codigo_ficha'] ?? 0);

            if (!$fichaDetectada) {
                $pageTitle = 'Errores de Validación';
                $errores = ['La ficha con código ' . ($parseResult['meta']['codigo_ficha'] ?? 'desconocido') . ' no está registrada en el sistema.'];
                require_once ROOT_PATH . '/views/horarios/errores_validacion.php';
                exit;
            }

            $fichaId = (int) $fichaDetectada['id'];
            foreach ($parseResult['bloques'] as $i => $bloque) {
                $parseResult['bloques'][$i]['codigo_ficha'] = (string) ($fichaDetectada['codigo'] ?? '');
            }

            $validacion = $this->horarioValidator->validar(
                $parseResult['bloques'] ?? [],
                ['ficha_id' => $fichaId]
            );

            if (!$validacion['valido']) {
                $pageTitle = 'Errores de Validación';
                $errores = $validacion['errores'];
                require_once ROOT_PATH . '/views/horarios/errores_validacion.php';
                exit;
            }

            // RF-HOR-15: detectar si el Excel revertiría permutas aprobadas.
            $confirmarPermutas = !empty($_POST['confirmar_permutas']);
            if (!$confirmarPermutas) {
                $permutasAfectadas = $this->detectarPermutasAfectadas($parseResult['bloques'] ?? [], $fichaId);
                if (!empty($permutasAfectadas)) {
                    $_SESSION['importacion_pendiente'] = [
                        'bloques' => $parseResult['bloques'],
                        'meta'    => $parseResult['meta'],
                        'anio'    => $anio,
                        'archivo' => $fileName,
                        'ficha_id' => $fichaId,
                    ];
                    $pageTitle = 'Confirmar importación con permutas';
                    require_once ROOT_PATH . '/views/horarios/confirmar_permutas.php';
                    return;
                }
            }

            // Sincronización (Tarea A.3)
            $reporteSincronizacion = $this->horarioSincronizador->sincronizar($parseResult['bloques'] ?? [], $fichaId);

            $resultado = $this->procesarResultadoFicha($parseResult, $validacion, $reporteSincronizacion, $fileName, $anio, $fichaId, $confirmarPermutas);
            unset($_SESSION['importacion_pendiente']);

            $pageTitle = 'Resultado de la Importación';
            require_once ROOT_PATH . '/views/horarios/resultado.php';

        } catch (Exception $e) {
            Auth::setFlash('error', 'Error al procesar el archivo Excel: ' . $e->getMessage());
            header('Location: ' . BASE_URL . '?action=horarios/importar');
            exit;
        }
    }

    /**
     * RF-HOR-15: confirma y aplica una importación que revertiría permutas.
     */
    public function confirmarPermutas(): void
    {
        Auth::requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST'
            || !Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
            Auth::setFlash('error', 'Token de seguridad inválido o expirado.');
            header('Location: ' . BASE_URL . '?action=horarios/importar');
            exit;
        }

        $pendiente = $_SESSION['importacion_pendiente'] ?? null;
        if (!is_array($pendiente) || empty($pendiente['bloques'])) {
            Auth::setFlash('error', 'No hay una importación pendiente de confirmar.');
            header('Location: ' . BASE_URL . '?action=horarios/importar');
            exit;
        }

        $fichaId = (int) ($pendiente['ficha_id'] ?? 0);
        $reporteSincronizacion = $this->horarioSincronizador->sincronizar($pendiente['bloques'], $fichaId);

        $parseResult = [
            'tipo'    => 'ficha',
            'meta'    => $pendiente['meta'] ?? [],
            'bloques' => $pendiente['bloques'],
        ];

        $resultado = $this->procesarResultadoFicha(
            $parseResult,
            ['valido' => true, 'errores' => []],
            $reporteSincronizacion,
            (string) ($pendiente['archivo'] ?? ''),
            (int) ($pendiente['anio'] ?? date('Y')),
            $fichaId,
            true
        );

        unset($_SESSION['importacion_pendiente']);

        $pageTitle = 'Resultado de la Importación';
        require_once ROOT_PATH . '/views/horarios/resultado.php';
    }

    /**
     * Genera el reporte, registra la auditoría y devuelve el resultado.
     */
    private function procesarResultadoFicha(
        array $parseResult,
        array $validacion,
        array $reporteSincronizacion,
        string $archivo,
        int $anio,
        int $fichaId,
        bool $permutasConfirmadas
    ): array {
        $resultado = $this->horarioReporte->generarReporte($parseResult, $validacion, $reporteSincronizacion);

        $this->registrarImportacion([
            'usuario_id'   => (int) Auth::getUserId(),
            'tipo'         => 'ficha',
            'archivo'      => $archivo,
            'anio'         => $anio,
            'codigo_ficha' => $parseResult['meta']['codigo_ficha'] ?? null,
            'creadas'      => $reporteSincronizacion['creadas'] ?? 0,
            'actualizadas' => $reporteSincronizacion['actualizadas'] ?? 0,
            'omitidas'     => $reporteSincronizacion['omitidas'] ?? 0,
            'canceladas'   => $reporteSincronizacion['canceladas'] ?? 0,
            'protegidas'   => $reporteSincronizacion['protegidas'] ?? 0,
            'errores'      => $reporteSincronizacion['errores'] ?? [],
            'permutas_confirmadas' => $permutasConfirmadas,
        ]);

        return $resultado;
    }

    /**
     * RF-HOR-15: permutas aprobadas que el Excel dejaría sin efecto.
     */
    private function detectarPermutasAfectadas(array $bloques, int $fichaId): array
    {
        if (empty($bloques)) {
            return [];
        }

        $fechas = array_column($bloques, 'fecha');
        $desde = min($fechas);
        $hasta = max($fechas);

        $permutas = $this->permutaModel->getAprobadasEnFicha($fichaId, $desde, $hasta);
        if (empty($permutas)) {
            return [];
        }

        $indiceExcel = [];
        foreach ($bloques as $bloque) {
            $indiceExcel[$bloque['fecha'] . '|' . $bloque['hora_inicio']] = $bloque;
        }

        $afectadas = [];
        foreach ($permutas as $permuta) {
            $sesiones = [
                [
                    'fecha' => $permuta['fecha_origen'],
                    'hora_inicio' => $permuta['hora_ini_origen'],
                    'hora_fin' => $permuta['hora_fin_origen'],
                    'ficha_id' => (int) $permuta['ficha_origen_id'],
                    'instructor_id' => (int) $permuta['instructor_origen_id'],
                    'codigo' => $permuta['ficha_origen_codigo'],
                ],
                [
                    'fecha' => $permuta['fecha_destino'],
                    'hora_inicio' => $permuta['hora_ini_destino'],
                    'hora_fin' => $permuta['hora_fin_destino'],
                    'ficha_id' => (int) $permuta['ficha_destino_id'],
                    'instructor_id' => (int) $permuta['instructor_destino_id'],
                    'codigo' => $permuta['ficha_destino_codigo'],
                ],
            ];

            foreach ($sesiones as $sesion) {
                if ($sesion['ficha_id'] !== $fichaId) {
                    continue;
                }

                $key = $sesion['fecha'] . '|' . $sesion['hora_inicio'];
                $actual = $this->usuarioModel->getById($sesion['instructor_id']);
                $nombreActual = $actual ? trim($actual['nombre'] . ' ' . $actual['apellido']) : '';

                $motivo = null;
                if (!isset($indiceExcel[$key])) {
                    $motivo = 'el bloque ya no aparece en el Excel';
                } else {
                    $nombreExcel = trim((string) ($indiceExcel[$key]['instructor'] ?? ''));
                    if ($nombreActual !== '' && $this->normalizarNombre($nombreExcel) !== $this->normalizarNombre($nombreActual)) {
                        $motivo = "el Excel asigna {$nombreExcel} en lugar de {$nombreActual}";
                    }
                }

                if ($motivo !== null) {
                    $afectadas[] = [
                        'permuta_id'       => (int) $permuta['id'],
                        'fecha'            => $sesion['fecha'],
                        'hora_inicio'      => $sesion['hora_inicio'],
                        'hora_fin'         => $sesion['hora_fin'],
                        'codigo_ficha'     => $sesion['codigo'],
                        'instructor_actual' => $nombreActual,
                        'motivo'           => $motivo,
                    ];
                }
            }
        }

        return $afectadas;
    }

    private function normalizarNombre(string $texto): string
    {
        $texto = mb_strtolower(trim($texto), 'UTF-8');
        $texto = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'],
            ['a', 'e', 'i', 'o', 'u', 'u', 'n'],
            $texto
        );
        return preg_replace('/\s+/', ' ', $texto);
    }

    /**
     * RF-HOR-08: registra la importación en la tabla de auditoría.
     */
    private function registrarImportacion(array $datos): void
    {
        try {
            $modelo = new ImportacionHorario($this->db);
            $modelo->registrar($datos);
        } catch (Throwable $e) {
            // La auditoría no debe impedir el resultado de la importación.
        }
    }

    /**
     * Procesa el horario semestral de un instructor (RF-HOR-03, 36, 39, 40).
     */
    private function procesarHorarioInstructor(array $parseResult, string $archivo = ''): array
    {
        $nombreInstructor = trim((string) ($parseResult['meta']['instructor_nombre'] ?? ''));
        $instructor = $nombreInstructor !== ''
            ? $this->usuarioModel->getInstructorPorNombre($nombreInstructor)
            : null;

        if (!$instructor) {
            $errores = ['No se pudo identificar al instructor en el encabezado del archivo'
                . ($nombreInstructor !== '' ? " (\"{$nombreInstructor}\")" : '')
                . '. Verifique que esté registrado.'];
            $pageTitle = 'Errores de Validación';
            require_once ROOT_PATH . '/views/horarios/errores_validacion.php';
            exit;
        }

        // Mapa de fichas registradas
        $fichas = $this->fichaModel->getAll();
        $mapaFichas = [];
        foreach ($fichas as $f) {
            $mapaFichas[(string) $f['codigo']] = (int) $f['id'];
        }

        $errores = [];
        foreach ($parseResult['bloques'] as $i => $bloque) {
            $codigo = (string) ($bloque['codigo_ficha'] ?? '');
            if ($codigo === '') {
                $errores[] = 'El bloque del ' . ($bloque['fecha'] ?? '?') . ' (' . ($bloque['hora_inicio'] ?? '?') . ') no indica la ficha asignada.';
                continue;
            }
            if (!isset($mapaFichas[$codigo])) {
                $errores[] = "La ficha {$codigo} no está registrada en el sistema.";
                continue;
            }
            $parseResult['bloques'][$i]['codigo_ficha'] = $codigo;
        }

        $validacion = $this->horarioValidator->validar(
            $parseResult['bloques'] ?? [],
            ['validar_asignaciones' => false]
        );
        $errores = array_merge($errores, $validacion['errores']);

        if (!empty($errores)) {
            $pageTitle = 'Errores de Validación';
            require_once ROOT_PATH . '/views/horarios/errores_validacion.php';
            exit;
        }

        $reporteSincronizacion = $this->horarioSincronizador->sincronizarAsignaciones(
            $parseResult['bloques'] ?? [],
            (int) $instructor['id'],
            $mapaFichas
        );

        $resultado = $this->horarioReporte->generarReporte(
            $parseResult,
            ['valido' => true, 'errores' => []],
            $reporteSincronizacion
        );

        $this->registrarImportacion([
            'usuario_id'    => (int) Auth::getUserId(),
            'tipo'          => 'instructor',
            'archivo'       => $archivo,
            'anio'          => (int) ($parseResult['meta']['año'] ?? date('Y')),
            'instructor_id' => (int) $instructor['id'],
            'creadas'       => $reporteSincronizacion['creadas'] ?? 0,
            'actualizadas'  => $reporteSincronizacion['actualizadas'] ?? 0,
            'omitidas'      => $reporteSincronizacion['omitidas'] ?? 0,
            'canceladas'    => $reporteSincronizacion['canceladas'] ?? 0,
            'protegidas'    => $reporteSincronizacion['protegidas'] ?? 0,
            'errores'       => $reporteSincronizacion['errores'] ?? [],
        ]);

        return $resultado;
    }

    // =====================================================================
    //  CRUD MANUAL DE SESIONES (ALTA-04, RF-HOR-22, RF-HOR-27)
    // =====================================================================

    /**
     * Muestra formulario para crear una nueva sesión manual
     */
    public function crear(): void
    {
        Auth::requireAdminOrInstructor();

        $pageTitle = 'Nueva Sesión de Horario';
        $sesion = null;
        $fichas = $this->fichaModel->getAll();
        
        require_once ROOT_PATH . '/models/Competencia.php';
        $competenciaModel = new Competencia($this->db);
        $competencias = $competenciaModel->getAll();

        $instructores = $this->usuarioModel->getInstructores();
        $csrfToken = Auth::generateCSRF();

        require_once ROOT_PATH . '/views/horarios/form.php';
    }

    /**
     * Muestra formulario para editar una sesión existente
     */
    public function editar(): void
    {
        Auth::requireAdminOrInstructor();

        $id = (int)($_GET['id'] ?? 0);
        $sesion = $this->sesionModel->getById($id);

        if (!$sesion) {
            Auth::setFlash('error', 'La sesión solicitada no existe.');
            header('Location: ' . BASE_URL . '?action=horarios');
            exit;
        }

        // RF-HOR-27: Una sesión con asistencia registrada no se puede mover ni reasignar
        if ($this->sesionModel->tieneAsistencias($id)) {
            Auth::setFlash('error', 'Esta sesión ya cuenta con asistencias registradas y no puede modificarse.');
            header('Location: ' . BASE_URL . '?action=horarios');
            exit;
        }

        // Si es instructor, solo puede editar si es su sesión o es líder de la ficha
        if (Auth::isInstructor() && !Auth::isAdmin()) {
            $userId = (int) Auth::getUserId();
            $ficha = $this->fichaModel->getById((int)$sesion['Ficha_id']);
            $esLider = ($ficha && (int)($ficha['instructor_id'] ?? 0) === $userId);
            if ((int)$sesion['Instructor_id'] !== $userId && !$esLider) {
                Auth::setFlash('error', 'No tienes permisos para modificar esta sesión.');
                header('Location: ' . BASE_URL . '?action=horarios');
                exit;
            }
        }

        $pageTitle = 'Editar Sesión de Horario';
        $fichas = $this->fichaModel->getAll();

        require_once ROOT_PATH . '/models/Competencia.php';
        $competenciaModel = new Competencia($this->db);
        $competencias = $competenciaModel->getAll();

        $instructores = $this->usuarioModel->getInstructores();
        $csrfToken = Auth::generateCSRF();

        require_once ROOT_PATH . '/views/horarios/form.php';
    }

    /**
     * Guarda (crea o actualiza) una sesión manual
     */
    public function guardar(): void
    {
        Auth::requireAdminOrInstructor();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '?action=horarios');
            exit;
        }

        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Auth::validateCSRF($csrfToken)) {
            Auth::setFlash('error', 'Token CSRF inválido o expirado.');
            header('Location: ' . BASE_URL . '?action=horarios');
            exit;
        }

        $id = (int)($_POST['id'] ?? 0);
        $fichaId = (int)($_POST['Ficha_id'] ?? 0);
        $competenciaId = !empty($_POST['Competencia_id']) ? (int)$_POST['Competencia_id'] : null;
        $instructorId = (int)($_POST['Instructor_id'] ?? 0);
        $fecha = trim($_POST['fecha'] ?? '');
        $horaInicio = trim($_POST['hora_inicio'] ?? '');
        $horaFin = trim($_POST['hora_fin'] ?? '');
        $tipoInstructor = trim($_POST['tipo_instructor'] ?? '');
        $especialidad = trim($_POST['especialidad'] ?? '');
        $nivel = trim($_POST['nivel'] ?? '');
        $grupoConvergente = trim($_POST['grupo_convergente'] ?? '');
        $estado = trim($_POST['estado'] ?? 'Activo');

        if (!in_array($estado, ['Activo', 'Cancelado', 'Permutado'], true)) {
            $estado = 'Activo';
        }

        // Validaciones básicas
        if ($fichaId <= 0 || $instructorId <= 0 || empty($fecha) || empty($horaInicio) || empty($horaFin)) {
            Auth::setFlash('error', 'Por favor completa todos los campos requeridos.');
            $redirect = $id > 0 ? '?action=horarios/editar&id=' . $id : '?action=horarios/crear';
            header('Location: ' . BASE_URL . $redirect);
            exit;
        }

        // RF-HOR-21: Validar que hora_fin > hora_inicio
        if ($horaInicio >= $horaFin) {
            Auth::setFlash('error', 'La hora de inicio debe ser menor que la hora de fin.');
            $redirect = $id > 0 ? '?action=horarios/editar&id=' . $id : '?action=horarios/crear';
            header('Location: ' . BASE_URL . $redirect);
            exit;
        }

        // RF-HOR-19: Validar horas dentro de la jornada de la ficha
        $ficha = $this->fichaModel->getById($fichaId);
        if ($ficha && !empty($ficha['jornada_id'])) {
            require_once ROOT_PATH . '/models/Jornada.php';
            $jornadaModel = new Jornada($this->db);
            $jornada = $jornadaModel->getById((int)$ficha['jornada_id']);
            if ($jornada) {
                if ($horaInicio < $jornada['hora_inicio'] || $horaFin > $jornada['hora_fin']) {
                    Auth::setFlash('error', "El horario ({$horaInicio} - {$horaFin}) queda fuera de la jornada {$jornada['nombre']} de la ficha ({$jornada['hora_inicio']} - {$jornada['hora_fin']}).");
                    $redirect = $id > 0 ? '?action=horarios/editar&id=' . $id : '?action=horarios/crear';
                    header('Location: ' . BASE_URL . $redirect);
                    exit;
                }
            }
        }

        // RF-HOR-22: Validar solapamientos
        $solapamiento = $this->sesionModel->validarSolapamiento($fichaId, $instructorId, $fecha, $horaInicio, $horaFin, $id > 0 ? $id : null);
        if (!$solapamiento['valido']) {
            Auth::setFlash('error', $solapamiento['mensaje']);
            $redirect = $id > 0 ? '?action=horarios/editar&id=' . $id : '?action=horarios/crear';
            header('Location: ' . BASE_URL . $redirect);
            exit;
        }

        $datos = [
            'Ficha_id'          => $fichaId,
            'Competencia_id'    => $competenciaId,
            'Instructor_id'     => $instructorId,
            'fecha'             => $fecha,
            'hora_inicio'       => $horaInicio,
            'hora_fin'          => $horaFin,
            'estado'            => $estado,
            'tipo_instructor'   => $tipoInstructor ?: null,
            'especialidad'      => $especialidad ?: null,
            'nivel'             => $nivel ?: null,
            'grupo_convergente' => $grupoConvergente ?: null,
        ];

        if ($id > 0) {
            // Verificar si tiene asistencias
            if ($this->sesionModel->tieneAsistencias($id)) {
                Auth::setFlash('error', 'No se puede modificar una sesión con asistencias ya registradas.');
                header('Location: ' . BASE_URL . '?action=horarios');
                exit;
            }
            $ok = $this->sesionModel->update($id, $datos);
            $msg = $ok ? 'Sesión de horario actualizada correctamente.' : 'Error al actualizar la sesión.';
        } else {
            $ok = $this->sesionModel->create($datos);
            $msg = $ok ? 'Sesión de horario creada exitosamente.' : 'Error al crear la sesión.';
        }

        Auth::setFlash($ok ? 'success' : 'error', $msg);
        header('Location: ' . BASE_URL . '?action=horarios&ficha_id=' . $fichaId);
        exit;
    }

    /**
     * Elimina una sesión validando que no tenga asistencias (RF-HOR-27, ALTA-04)
     */
    public function eliminar(): void
    {
        Auth::requireAdminOrInstructor();

        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) {
            header('Location: ' . BASE_URL . '?action=horarios');
            exit;
        }

        $sesion = $this->sesionModel->getById($id);
        if (!$sesion) {
            Auth::setFlash('error', 'La sesión no existe.');
            header('Location: ' . BASE_URL . '?action=horarios');
            exit;
        }

        // RF-HOR-27: Una sesión con asistencia registrada no se puede mover ni eliminar
        if ($this->sesionModel->tieneAsistencias($id)) {
            Auth::setFlash('error', 'No se puede eliminar la sesión porque ya tiene registros de asistencia vinculados.');
            $redirect = isset($_GET['ficha_id']) ? '&ficha_id=' . (int)$_GET['ficha_id'] : '';
            header('Location: ' . BASE_URL . '?action=horarios' . $redirect);
            exit;
        }

        $this->sesionModel->delete($id);
        Auth::setFlash('success', 'Sesión de horario eliminada correctamente.');

        $redirect = isset($_GET['ficha_id']) ? '&ficha_id=' . (int)$_GET['ficha_id'] : '';
        header('Location: ' . BASE_URL . '?action=horarios' . $redirect);
        exit;
    }

    // =====================================================================
    //  MÓDULO DE PERMUTAS Y CESIONES (RF-HOR-23 a 28, RF-HOR-43)
    // =====================================================================

    /**
     * Panel de control de permutas e intercambios
     */
    public function permutas(): void
    {
        Auth::requireAdminOrInstructor();

        $pageTitle = 'Permutas de Horario';
        $userId = (int) Auth::getUserId();
        $esAdmin = Auth::isAdmin();

        if ($esAdmin) {
            $permutas = $this->permutaModel->getAll();
            $recibidas = [];
            $enviadas = [];
        } else {
            $recibidas = $this->permutaModel->getPendientesPorReceptor($userId);
            $enviadas = $this->permutaModel->getEnviadasPorSolicitante($userId);
            $permutas = [];
        }

        // Sesiones del instructor para proponer nueva permuta
        $misSesiones = $this->sesionModel->getByInstructor($userId, date('Y-m-d'), date('Y-m-d', strtotime('+3 months')), null, 'Activo');
        $csrfToken = Auth::generateCSRF();

        require_once ROOT_PATH . '/views/horarios/permutas.php';
    }

    /**
     * Procesa la creación de una solicitud de permuta
     */
    public function proponerPermuta(): void
    {
        Auth::requireAdminOrInstructor();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '?action=horarios/permutas');
            exit;
        }

        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Auth::validateCSRF($csrfToken)) {
            Auth::setFlash('error', 'Token de seguridad inválido.');
            header('Location: ' . BASE_URL . '?action=horarios/permutas');
            exit;
        }

        $sesionOrigenId = (int)($_POST['sesion_origen_id'] ?? 0);
        $sesionDestinoId = (int)($_POST['sesion_destino_id'] ?? 0);
        $motivo = trim($_POST['motivo'] ?? '');
        $userId = (int) Auth::getUserId();

        $origen = $this->sesionModel->getById($sesionOrigenId);
        $destino = $this->sesionModel->getById($sesionDestinoId);

        if (!$origen || !$destino) {
            Auth::setFlash('error', 'Una de las sesiones seleccionadas no existe.');
            header('Location: ' . BASE_URL . '?action=horarios/permutas');
            exit;
        }

        // Validar que el origen pertenezca al usuario (si no es admin)
        if (!Auth::isAdmin() && (int)$origen['Instructor_id'] !== $userId) {
            Auth::setFlash('error', 'Solo puedes proponer permutas sobre tus propias sesiones.');
            header('Location: ' . BASE_URL . '?action=horarios/permutas');
            exit;
        }

        // No permitir permutar sesiones que ya tengan asistencias
        if ($this->sesionModel->tieneAsistencias($sesionOrigenId) || $this->sesionModel->tieneAsistencias($sesionDestinoId)) {
            Auth::setFlash('error', 'No se pueden permutar sesiones con asistencias registradas.');
            header('Location: ' . BASE_URL . '?action=horarios/permutas');
            exit;
        }

        $solicitanteId = (int) $origen['Instructor_id'];
        $receptorId = (int) $destino['Instructor_id'];

        $res = $this->permutaModel->crearSolicitud($sesionOrigenId, $sesionDestinoId, $solicitanteId, $receptorId, $motivo);
        Auth::setFlash($res['ok'] ? 'success' : 'error', $res['mensaje']);
        header('Location: ' . BASE_URL . '?action=horarios/permutas');
        exit;
    }

    /**
     * Responde (Aprueba o Rechaza) una solicitud de permuta
     */
    public function responderPermuta(): void
    {
        Auth::requireAdminOrInstructor();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '?action=horarios/permutas');
            exit;
        }

        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Auth::validateCSRF($csrfToken)) {
            Auth::setFlash('error', 'Token de seguridad inválido.');
            header('Location: ' . BASE_URL . '?action=horarios/permutas');
            exit;
        }

        $permutaId = (int)($_POST['permuta_id'] ?? 0);
        $decision = trim($_POST['decision'] ?? '');
        $userId = (int) Auth::getUserId();
        $esAdmin = Auth::isAdmin();

        if ($decision === 'aprobar') {
            $res = $this->permutaModel->aprobarPermuta($permutaId, $userId, $esAdmin);
        } else {
            $res = $this->permutaModel->rechazarPermuta($permutaId, $userId, $esAdmin);
        }

        Auth::setFlash($res['ok'] ? 'success' : 'error', $res['mensaje']);
        header('Location: ' . BASE_URL . '?action=horarios/permutas');
        exit;
    }

    /**
     * Algoritmo inteligente para parsear la grilla visual de horarios en el Excel
     */
    private function parsearExcelHorarios($sheet): array
    {
        $highestRow = $sheet->getHighestRow();
        $highestCol = $sheet->getHighestColumn();
        $highestColIndex = Coordinate::columnIndexFromString($highestCol);
        $resultado = [
            'ficha_detectada'     => null,
            'total_procesadas'    => 0,
            'creadas'             => 0,
            'actualizadas'        => 0,
            'omitidas'            => 0,
            'errores'             => 0,
            'instructores_nuevos' => 0,
            'detalles'            => []
        ];
        // 1. Detectar el Código de Ficha en las primeras filas
        $fichaEncontrada = null;
        for ($r = 1; $r <= min(10, $highestRow); $r++) {
            for ($c = 1; $c <= $highestColIndex; $c++) {
                $val = (string)$sheet->getCell([$c, $r])->getValue();
                if (preg_match('/\b(\d{7})\b/', $val, $matches)) {
                    $codigoFicha = (int)$matches[1];
                    $fichaEncontrada = $this->fichaModel->getByCodigo($codigoFicha);
                    if ($fichaEncontrada) {
                        break 2;
                    }
                }
            }
        }

        if (!$fichaEncontrada) {
            $resultado['detalles'][] = [
                'tipo' => 'error',
                'mensaje' => 'No se pudo detectar un código de Ficha válido registrado en el sistema en la cabecera del archivo.'
            ];
            return $resultado;
        }

        $resultado['ficha_detectada'] = $fichaEncontrada;
        $fichaId = (int)$fichaEncontrada['id'];

        // Meses en español a número
        $mesesMap = [
            'ENERO' => '01',
            'FEBRERO' => '02',
            'MARZO' => '03',
            'ABRIL' => '04',
            'MAYO' => '05',
            'JUNIO' => '06',
            'JULIO' => '07',
            'AGOSTO' => '08',
            'SEPTIEMBRE' => '09',
            'OCTUBRE' => '10',
            'NOVIEMBRE' => '11',
            'DICIEMBRE' => '12'
        ];

        // 2. Mapear celdas combinadas
        $mergedCells = $sheet->getMergeCells();
        $mergedMap = []; // 'col_row' => ['startCol', 'startRow', 'endCol', 'endRow']

        foreach ($mergedCells as $range) {
            [$topLet, $bottomRight] = explode(':', $range);
            [$sColStr, $sRow] = Coordinate::coordinateFromString($topLet);
            [$eColStr, $eRow] = Coordinate::coordinateFromString($bottomRight);

            $sCol = Coordinate::columnIndexFromString($sColStr);
            $eCol = Coordinate::columnIndexFromString($eColStr);

            $info = [
                'startCol' => $sCol,
                'startRow' => (int)$sRow,
                'endCol'   => $eCol,
                'endRow'   => (int)$eRow,
            ];

            for ($row = (int)$sRow; $row <= (int)$eRow; $row++) {
                for ($col = $sCol; $col <= $eCol; $col++) {
                    $mergedMap["{$col}_{$row}"] = $info;
                }
            }
        }

        // 3. Escanear bloques de semanas
        // Buscamos filas de días (LUNES, MARTES, MIERCOLES...)
        $diasKeywords = ['LUNES', 'MARTES', 'MIERCOLES', 'MIÉRCOLES', 'JUEVES', 'VIERNES', 'SABADO', 'SÁBADO'];
        $processedBlocks = []; // para no duplicar bloques procesados

        $year = (int)date('Y');

        for ($r = 1; $r <= $highestRow; $r++) {
            $isHeaderRow = false;
            for ($c = 1; $c <= $highestColIndex; $c++) {
                $val = strtoupper(trim((string)$sheet->getCell([$c, $r])->getValue()));
                if (in_array($val, $diasKeywords)) {
                    $isHeaderRow = true;
                    break;
                }
            }

            if (!$isHeaderRow) {
                continue;
            }

            $dayNumberRow = $r + 1;

            $monthRow = max(1, $r - 1);

           
            $colDates = [];
            $currentMonth = date('m');

            for ($c = 1; $c <= $highestColIndex; $c++) {
                for ($mr = max(1, $r - 3); $mr <= $r; $mr++) {
                    $mVal = strtoupper(trim((string)$sheet->getCell([$c, $mr])->getValue()));
                    foreach ($mesesMap as $nombreMes => $numMes) {
                        if (str_contains($mVal, $nombreMes)) {
                            $currentMonth = $numMes;
                            break 2;
                        }
                    }
                }

                $numDiaVal = trim((string)$sheet->getCell([$c, $dayNumberRow])->getValue());
                if (is_numeric($numDiaVal) && (int)$numDiaVal >= 1 && (int)$numDiaVal <= 31) {
                    $diaNum = sprintf('%02d', (int)$numDiaVal);
                    $colDates[$c] = sprintf('%04d-%s-%s', $year, $currentMonth, $diaNum);
                }
            }

            $startClassRow = $dayNumberRow + 1;
            $endClassRow = min($highestRow, $startClassRow + 25);

            for ($checkR = $startClassRow; $checkR <= min($highestRow, $startClassRow + 25); $checkR++) {
                for ($checkC = 1; $checkC <= $highestColIndex; $checkC++) {
                    $cVal = strtoupper(trim((string)$sheet->getCell([$checkC, $checkR])->getValue()));
                    if (in_array($cVal, $diasKeywords)) {
                        $endClassRow = $checkR - 1;
                        break 2;
                    }
                }
            }

            for ($cr = $startClassRow; $cr <= $endClassRow; $cr++) {
                foreach ($colDates as $colIdx => $fecha) {
                    $cellKey = "{$colIdx}_{$cr}";
                    if (isset($processedBlocks[$cellKey])) {
                        continue;
                    }

                    $cell = $sheet->getCell([$colIdx, $cr]);
                    $rawText = trim((string)$cell->getValue());

                    if (empty($rawText)) {
                        continue;
                    }
                    $blockStartRow = $cr;
                    $blockEndRow = $cr;
                    $blockStartCol = $colIdx;
                    $blockEndCol = $colIdx;

                    if (isset($mergedMap[$cellKey])) {
                        $mInfo = $mergedMap[$cellKey];
                        $blockStartRow = $mInfo['startRow'];
                        $blockEndRow = $mInfo['endRow'];
                        $blockStartCol = $mInfo['startCol'];
                        $blockEndCol = $mInfo['endCol'];
                        for ($mr = $blockStartRow; $mr <= $blockEndRow; $mr++) {
                            for ($mc = $blockStartCol; $mc <= $blockEndCol; $mc++) {
                                $processedBlocks["{$mc}_{$mr}"] = true;
                            }
                        }
                    } else {
                        $processedBlocks[$cellKey] = true;
                    }
                    $horaInicioVal = $sheet->getCell([2, $blockStartRow])->getValue();
                    $horaFinVal = $sheet->getCell([3, $blockEndRow])->getValue();

                    if (is_numeric($horaInicioVal)) {
                        $horaInicio = sprintf('%02d:00:00', (int)$horaInicioVal);
                    } else {
                        $horaInicio = '07:00:00';
                    }

                    if (is_numeric($horaFinVal)) {
                        $horaFin = sprintf('%02d:00:00', (int)$horaFinVal);
                    } elseif (is_numeric($horaInicioVal)) {
                        $duracionHoras = max(1, ($blockEndRow - $blockStartRow + 1));
                        $horaFin = sprintf('%02d:00:00', (int)$horaInicioVal + $duracionHoras);
                    } else {
                        $horaFin = '13:00:00';
                    }

                    $lineas = array_values(array_filter(
                        array_map('trim', preg_split('/\r\n|\r|\n/', $rawText)),
                        fn($l) => !empty($l)
                    ));

                    if (empty($lineas)) {
                        continue;
                    }

                    $resultado['total_procesadas']++;

                    $nombreInstructor = $lineas[0];
                    $tipoInstructor   = isset($lineas[1]) ? rtrim($lineas[1], ' -') : null;
                    $especialidad     = isset($lineas[2]) ? rtrim($lineas[2], ' -') : null;
                    $nivel            = isset($lineas[3]) ? rtrim($lineas[3], ' -') : null;
                    $grupoConvergente = isset($lineas[4]) ? rtrim($lineas[4], ' -') : null;

                    // Si hay 2 líneas y la segunda tiene varias etiquetas juntas
                    if (count($lineas) === 2 && str_contains($lineas[1], '-')) {
                        $subPartes = array_map('trim', explode('-', $lineas[1]));
                        $tipoInstructor = $subPartes[0] ?? null;
                        $especialidad   = $subPartes[1] ?? null;
                    }

                    // Buscar instructor en la BD
                    $instructor = $this->usuarioModel->getInstructorPorNombre($nombreInstructor);

                    // Si no se encuentra, auto-registrar al instructor
                    if (!$instructor) {
                        $partes = array_values(array_filter(explode(' ', trim($nombreInstructor))));
                        $nombreParte = $partes[0] ?? $nombreInstructor;
                        $apellidoParte = count($partes) > 1 ? implode(' ', array_slice($partes, 1)) : 'Docente';

                        $nuevoId = $this->usuarioModel->createInstructorDesdeExcel($nombreParte, $apellidoParte);
                        if (!$nuevoId) {
                            $resultado['errores']++;
                            $resultado['detalles'][] = [
                                'tipo' => 'error',
                                'mensaje' => "No se pudo registrar automáticamente al instructor \"$nombreInstructor\" (Fecha: $fecha, Horario: $horaInicio - $horaFin)."
                            ];
                            continue;
                        }

                        $instructor = $this->usuarioModel->getById($nuevoId);
                        $resultado['instructores_nuevos']++;
                        $resultado['detalles'][] = [
                            'tipo' => 'info',
                            'mensaje' => "Instructor \"$nombreInstructor\" registrado automáticamente (Identificación temporal asignada)."
                        ];
                    }

                    // Verificar si ya existe la sesión
                    $sesionExistente = $this->sesionModel->getSesionExistente($fichaId, $fecha, $horaInicio);

                    if ($sesionExistente) {
                        // Comparar campos del Excel vs BD
                        $datosExcel = [
                            'Instructor_id'     => (int)$instructor['id'],
                            'hora_fin'          => $horaFin,
                            'tipo_instructor'   => $tipoInstructor,
                            'especialidad'      => $especialidad,
                            'nivel'             => $nivel,
                            'grupo_convergente' => $grupoConvergente,
                        ];

                        $camposDiferentes = [];
                        foreach ($datosExcel as $campo => $valorExcel) {
                            $valorBD = $sesionExistente[$campo] ?? null;
                            if ($campo === 'Instructor_id') {
                                if ((int)$valorBD !== (int)$valorExcel) {
                                    $camposDiferentes[$campo] = $valorExcel;
                                }
                            } else {
                                $strBD = $valorBD !== null ? trim((string)$valorBD) : '';
                                $strExcel = $valorExcel !== null ? trim((string)$valorExcel) : '';
                                if ($strBD !== $strExcel) {
                                    $camposDiferentes[$campo] = $valorExcel;
                                }
                            }
                        }

                        if (empty($camposDiferentes)) {
                            // Los datos coinciden exactamente -> no actualizar
                            $resultado['omitidas']++;
                            $resultado['detalles'][] = [
                                'tipo' => 'warning',
                                'mensaje' => "Sesión sin cambios (omitida): Ficha {$fichaEncontrada['codigo']} el $fecha ($horaInicio - $horaFin) con {$instructor['nombre']} {$instructor['apellido']}."
                            ];
                        } else {
                            // Hay diferencias -> actualizar solo lo necesario
                            $actualizado = $this->sesionModel->update((int)$sesionExistente['id'], $camposDiferentes);
                            if ($actualizado) {
                                $resultado['actualizadas']++;
                                $camposCambiados = implode(', ', array_keys($camposDiferentes));
                                $resultado['detalles'][] = [
                                    'tipo' => 'info',
                                    'mensaje' => "Sesión actualizada ($camposCambiados): Ficha {$fichaEncontrada['codigo']} el $fecha ($horaInicio - $horaFin) — {$instructor['nombre']} {$instructor['apellido']}."
                                ];
                            } else {
                                $resultado['errores']++;
                                $resultado['detalles'][] = [
                                    'tipo' => 'error',
                                    'mensaje' => "Error al actualizar sesión para el $fecha ($horaInicio - $horaFin)."
                                ];
                            }
                        }
                        continue;
                    }

                    // Insertar sesión nueva si no existía
                    $insertado = $this->sesionModel->create([
                        'Ficha_id'          => $fichaId,
                        'Competencia_id'    => null,
                        'Instructor_id'     => $instructor['id'],
                        'fecha'             => $fecha,
                        'hora_inicio'       => $horaInicio,
                        'hora_fin'          => $horaFin,
                        'estado'            => 'Activo',
                        'tipo_instructor'   => $tipoInstructor,
                        'especialidad'      => $especialidad,
                        'nivel'             => $nivel,
                        'grupo_convergente' => $grupoConvergente,
                    ]);

                    if ($insertado) {
                        $resultado['creadas']++;
                        $resultado['detalles'][] = [
                            'tipo' => 'success',
                            'mensaje' => "Sesión programada: $fecha ($horaInicio - $horaFin) — {$instructor['nombre']} {$instructor['apellido']} ($tipoInstructor)."
                        ];
                    } else {
                        $resultado['errores']++;
                        $resultado['detalles'][] = [
                            'tipo' => 'error',
                            'mensaje' => "Error al guardar sesión para el $fecha ($horaInicio - $horaFin)."
                        ];
                    }
                }
            }
        }

        return $resultado;
    }

    private function fechaValida(?string $fecha): string
    {
        $fecha = $fecha ?? '';
        $dt = DateTime::createFromFormat('!Y-m-d', $fecha);
        $errores = DateTime::getLastErrors();
        $invalida = !$dt
            || $dt->format('Y-m-d') !== $fecha
            || ($errores && (($errores['warning_count'] ?? 0) > 0 || ($errores['error_count'] ?? 0) > 0));
        return $invalida ? date('Y-m-d') : $fecha;
    }

    private function urlMiHorario(string $vista, string $fecha, ?int $fichaId, string $estado): string
    {
        $params = [
            'action' => 'horarios/miHorario',
            'vista'  => $vista,
            'fecha'  => $fecha,
        ];
        if ($fichaId) {
            $params['ficha_id'] = $fichaId;
        }
        if ($estado !== '') {
            $params['estado'] = $estado;
        }
        return BASE_URL . '?' . http_build_query($params);
    }

    private function nombreDia(DateTime $dt, bool $corto = false): string
    {
        $largos = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
        $cortos = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];
        $n = (int) $dt->format('N');
        return $corto ? $cortos[$n] : $largos[$n];
    }

    private function nombreMes(int $mes): string
    {
        $meses = [1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'];
        return $meses[$mes] ?? '';
    }

    private function tituloSemana(DateTime $inicio, DateTime $fin): string
    {
        $mesI = $this->nombreMes((int) $inicio->format('n'));
        $mesF = $this->nombreMes((int) $fin->format('n'));
        if ($inicio->format('Y-m') === $fin->format('Y-m')) {
            return $inicio->format('j') . ' al ' . $fin->format('j') . ' de ' . $mesI . ' de ' . $inicio->format('Y');
        }
        if ($inicio->format('Y') === $fin->format('Y')) {
            return $inicio->format('j') . ' de ' . $mesI . ' al ' . $fin->format('j') . ' de ' . $mesF . ' de ' . $fin->format('Y');
        }
        return $inicio->format('j') . ' de ' . $mesI . ' de ' . $inicio->format('Y') . ' al ' . $fin->format('j') . ' de ' . $mesF . ' de ' . $fin->format('Y');
    }

    private function aMinutos(string $hora): int
    {
        $partes = explode(':', $hora);
        return ((int) ($partes[0] ?? 0) * 60) + (int) ($partes[1] ?? 0);
    }

    private function horasSesion(array $s): float
    {
        $ini = $this->aMinutos($s['hora_inicio']);
        $fin = $this->aMinutos($s['hora_fin']);
        if ($fin <= $ini) {
            return 0;
        }
        return ($fin - $ini) / 60;
    }

    private function formatoHoras(float $horas): string
    {
        $totalMin = (int) round($horas * 60);
        $h = intdiv($totalMin, 60);
        $m = $totalMin % 60;
        if ($h === 0 && $m === 0) {
            return '0 h';
        }
        if ($h === 0) {
            return $m . ' min';
        }
        if ($m === 0) {
            return $h . ' h';
        }
        return $h . ' h ' . $m . ' min';
    }

    private function enRango(string $fecha, string $desde, string $hasta): bool
    {
        return $fecha >= $desde && $fecha <= $hasta;
    }

    private function colorFicha(int $fichaId): string
    {
        $colores = ['#10b981', '#3b82f6', '#8b5cf6', '#f59e0b', '#06b6d4', '#ec4899', '#f97316', '#14b8a6'];
        return $colores[$fichaId % count($colores)];
    }

    private function decorarSesion(array $s): array
    {
        $dt = DateTime::createFromFormat('!Y-m-d', $s['fecha']) ?: new DateTime($s['fecha']);
        $horas = $this->horasSesion($s);
        $s['hora_ini_fmt'] = substr($s['hora_inicio'], 0, 5);
        $s['hora_fin_fmt'] = substr($s['hora_fin'], 0, 5);
        $s['fecha_fmt'] = $dt->format('d/m/Y');
        $s['dia_nombre'] = $this->nombreDia($dt);
        $s['etiqueta'] = $s['dia_nombre'] . ' ' . $dt->format('j') . ' de ' . $this->nombreMes((int) $dt->format('n'));
        $s['horas'] = $horas;
        $s['horas_fmt'] = $this->formatoHoras($horas);
        $s['color'] = $s['estado'] === 'Cancelado' ? '#64748b' : $this->colorFicha((int) $s['Ficha_id']);
        $partes = [
            $s['codigo_ficha'] ?? '',
            $s['nombre_programa'] ?? '',
            $s['nombre_jornada'] ?? '',
            $s['especialidad'] ?? '',
            $s['tipo_instructor'] ?? '',
            $s['nombre_competencia'] ?? '',
            $s['grupo_convergente'] ?? '',
            $s['nivel'] ?? '',
            $s['estado'] ?? '',
        ];
        $s['busqueda'] = trim(implode(' ', array_filter($partes, fn($v) => $v !== null && $v !== '')));
        return $s;
    }

    private function layoutDia(array $sesiones, int $horaDesde, int $hourPx): array
    {
        if (!$sesiones) {
            return [];
        }

        usort($sesiones, fn(array $a, array $b): int => strcmp($a['hora_inicio'], $b['hora_inicio']));

        $clusters = [];
        $cluster = [];
        $clusterEnd = null;
        foreach ($sesiones as $s) {
            $ini = $this->aMinutos($s['hora_inicio']);
            $fin = $this->aMinutos($s['hora_fin']);
            if ($fin <= $ini) {
                $fin = $ini + 60;
            }
            if ($clusterEnd !== null && $ini >= $clusterEnd) {
                $clusters[] = $cluster;
                $cluster = [];
                $clusterEnd = null;
            }
            $s['_ini'] = $ini;
            $s['_fin'] = $fin;
            $cluster[] = $s;
            $clusterEnd = max($clusterEnd ?? $fin, $fin);
        }
        if ($cluster) {
            $clusters[] = $cluster;
        }

        $base = $horaDesde * 60;
        $result = [];
        foreach ($clusters as $grupo) {
            $columnasFin = [];
            foreach ($grupo as $i => $s) {
                $col = 0;
                while (isset($columnasFin[$col]) && $columnasFin[$col] > $s['_ini']) {
                    $col++;
                }
                $columnasFin[$col] = $s['_fin'];
                $grupo[$i]['_col'] = $col;
            }
            $total = max(1, count($columnasFin));
            foreach ($grupo as $s) {
                $s['_top'] = round((($s['_ini'] - $base) / 60) * $hourPx, 2);
                $s['_height'] = round(max(34, (($s['_fin'] - $s['_ini']) / 60) * $hourPx - 4), 2);
                $s['_left'] = round(($s['_col'] / $total) * 100 + 1, 2);
                $s['_width'] = round((100 / $total) - 2, 2);
                $result[] = $s;
            }
        }

        return $result;
    }
}
