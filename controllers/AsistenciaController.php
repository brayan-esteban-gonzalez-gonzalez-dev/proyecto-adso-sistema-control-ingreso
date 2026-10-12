<?php
declare(strict_types=1);

/**
 * AsistenciaController.php — Controlador de Asistencia/RFID
 * 
 * Procesa marcaciones RFID, muestra historial de asistencia.
 */
class AsistenciaController {
    private PDO $db;
    private IngresoAsistencia $ingresoModel;
    private Aprendiz $aprendizModel;
    private Horario $horarioModel;
    private Ficha $fichaModel;
    private Usuario $usuarioModel;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->ingresoModel  = new IngresoAsistencia($db);
        $this->aprendizModel = new Aprendiz($db);
        $this->horarioModel  = new Horario($db);
        $this->fichaModel    = new Ficha($db);
        $this->usuarioModel  = new Usuario($db);
    }

    /**
     * Pantalla de marcación RFID
     */
    public function rfid(): void {
        Auth::requireAdmin();
        $ultimosIngresos = $this->ingresoModel->getIngresosHoyDetalle();
        $pageTitle = 'Marcación RFID';
        require_once ROOT_PATH . '/views/asistencia/rfid.php';
    }

    /**
     * Procesa una marcación RFID vía AJAX
     */
    public function marcar(): void {
        header('Content-Type: application/json');

        $codigoRfid = trim($_POST['codigo_rfid'] ?? '');

        if (empty($codigoRfid)) {
            echo json_encode(['success' => false, 'message' => 'Código RFID vacío.', 'tipo' => 'error']);
            return;
        }

        // Buscar usuario por RFID
        $usuario = $this->usuarioModel->getByRfid($codigoRfid);

        if (!$usuario) {
            echo json_encode([
                'success' => false,
                'message' => 'No se encontró un usuario con este código RFID.',
                'tipo'    => 'error'
            ]);
            return;
        }

        $fecha     = date('Y-m-d');
        $horaActual = date('H:i:s');
        $ingresoHoy = $this->ingresoModel->getIngresoHoy($usuario['id_usuario']);
        $nombreCompleto = $usuario['nombre'] . ' ' . $usuario['apellido'];

        // Si es Instructor (rol 2) u otro personal que no sea aprendiz
        if ($usuario['id_rol'] != 3) {
            if (!$ingresoHoy) {
                // Entrada
                $this->ingresoModel->registrarEntrada($usuario['id_usuario'], $fecha, $horaActual, 'A_Tiempo', 0);
                echo json_encode([
                    'success'    => true,
                    'message'    => '✅ Entrada de personal registrada.',
                    'tipo'       => 'success',
                    'accion'     => 'entrada',
                    'estado'     => 'A_Tiempo',
                    'aprendiz'   => $nombreCompleto,
                    'documento'  => $usuario['num_documento'],
                    'ficha'      => 'N/A',
                    'hora'       => $horaActual,
                ]);
            } else if ($ingresoHoy['hora_salida'] === null) {
                // Salida
                $this->ingresoModel->registrarSalida($ingresoHoy['id_ingreso'], $horaActual, 0);
                echo json_encode([
                    'success'    => true,
                    'message'    => '✅ Salida de personal registrada.',
                    'tipo'       => 'success',
                    'accion'     => 'salida',
                    'aprendiz'   => $nombreCompleto,
                    'documento'  => $usuario['num_documento'],
                    'ficha'      => 'N/A',
                    'hora'       => $horaActual,
                ]);
            } else {
                echo json_encode([
                    'success' => false, 'message' => 'ℹ️ Ya tiene registro completo de hoy.',
                    'tipo' => 'info', 'aprendiz' => $nombreCompleto
                ]);
            }
            return;
        }

        // Si es aprendiz (rol 3)
        $aprendiz = $this->aprendizModel->getByUsuario($usuario['id_usuario']);
        if (!$aprendiz) {
            echo json_encode(['success' => false, 'message' => 'Error: Aprendiz no configurado.', 'tipo' => 'error']);
            return;
        }

        // Obtener horario del día
        $horario = $this->horarioModel->getHorarioHoy($aprendiz['id_ficha']);
        if (!$horario) {
            echo json_encode([
                'success' => false, 'message' => 'No hay horario configurado para hoy.',
                'tipo'    => 'warning', 'aprendiz' => $nombreCompleto
            ]);
            return;
        }

        if (!$ingresoHoy) {
            // ─── REGISTRAR ENTRADA APRENDIZ ───
            $horaLlegadaInstructor = null;
            $ficha = $this->fichaModel->getById($aprendiz['id_ficha']);
            if ($ficha && $ficha['id_instructor_lider']) {
                $ingresoInst = $this->ingresoModel->getIngresoHoy($ficha['id_instructor_lider']);
                if ($ingresoInst) {
                    $horaLlegadaInstructor = $ingresoInst['hora_entrada'];
                }
            }

            $resultado = $this->calcularEstadoEntrada($horaActual, $horario, $horaLlegadaInstructor);

            $this->ingresoModel->registrarEntrada(
                $usuario['id_usuario'],
                $fecha,
                $horaActual,
                $resultado['estado'],
                $resultado['minutos_retardo']
            );

            $mensajes = [
                'A_Tiempo' => '✅ Entrada registrada — A TIEMPO',
                'Retardo'  => '⚠️ Entrada registrada — RETARDO (' . $resultado['minutos_retardo'] . ' min)',
            ];

            echo json_encode([
                'success'    => true,
                'message'    => $mensajes[$resultado['estado']] ?? 'Entrada registrada.',
                'tipo'       => $resultado['estado'] === 'A_Tiempo' ? 'success' : 'warning',
                'accion'     => 'entrada',
                'estado'     => $resultado['estado'],
                'aprendiz'   => $nombreCompleto,
                'documento'  => $usuario['num_documento'],
                'ficha'      => $aprendiz['codigo_ficha'],
                'hora'       => $horaActual,
            ]);

        } elseif ($ingresoHoy['hora_salida'] === null) {
            // ─── REGISTRAR SALIDA APRENDIZ ───
            $resultado = $this->calcularEstadoSalida($horaActual, $horario);

            $this->ingresoModel->registrarSalida(
                $ingresoHoy['id_ingreso'],
                $horaActual,
                $resultado['minutos_anticipada']
            );

            if ($resultado['minutos_anticipada'] > 0) {
                $mensaje = '⚠️ Salida registrada — ANTICIPADA (' . $resultado['minutos_anticipada'] . ' min antes)';
                $tipo = 'warning';
            } else {
                $mensaje = '✅ Salida registrada — NORMAL';
                $tipo = 'success';
            }

            echo json_encode([
                'success'    => true,
                'message'    => $mensaje,
                'tipo'       => $tipo,
                'accion'     => 'salida',
                'aprendiz'   => $nombreCompleto,
                'documento'  => $usuario['num_documento'],
                'ficha'      => $aprendiz['codigo_ficha'],
                'hora'       => $horaActual,
            ]);

        } else {
            // Ya registró entrada y salida
            echo json_encode([
                'success' => false,
                'message' => 'ℹ️ Ya tiene registro completo de hoy (entrada y salida).',
                'tipo'    => 'info',
                'aprendiz' => $nombreCompleto
            ]);
        }
    }

    /**
     * Calcula el estado de la entrada según el horario
     */
    private function calcularEstadoEntrada(string $horaActual, array $horario, ?string $horaLlegadaInstructor = null): array {
        $entradaProgramada = strtotime($horario['hora_entrada']);
        $actual = strtotime($horaActual);
        
        $baseTolerancia = $entradaProgramada;
        
        // Si el instructor llegó, verificamos si llegó tarde
        if ($horaLlegadaInstructor) {
            $llegadaInst = strtotime($horaLlegadaInstructor);
            if ($llegadaInst > $entradaProgramada) {
                // Si el instructor llegó tarde, la base de tolerancia se mueve a la hora en que él llegó
                $baseTolerancia = $llegadaInst;
            }
        }

        // El plazo es de 10 minutos desde la base
        $limiteTolerancia = $baseTolerancia + (10 * 60);

        if ($actual <= $limiteTolerancia) {
            return ['estado' => 'A_Tiempo', 'minutos_retardo' => 0];
        } else {
            $minutosRetardo = (int) ceil(($actual - $baseTolerancia) / 60);
            return ['estado' => 'Retardo', 'minutos_retardo' => $minutosRetardo];
        }
    }

    /**
     * Calcula si la salida es anticipada
     */
    private function calcularEstadoSalida(string $horaActual, array $horario): array {
        $salida = strtotime($horario['hora_salida']);
        $actual = strtotime($horaActual);

        if ($actual < $salida) {
            $minutosAnticipada = (int) ceil(($salida - $actual) / 60);
            return ['minutos_anticipada' => $minutosAnticipada];
        }

        return ['minutos_anticipada' => 0];
    }

    /**
     * Historial de asistencia (admin ve todos, aprendiz ve los suyos)
     */
    public function historial(): void {
        Auth::requireLogin();

        $fechaInicio = $_GET['fecha_inicio'] ?? null;
        $fechaFin    = $_GET['fecha_fin'] ?? null;

        if (Auth::isAdminOrInstructor()) {
            $fecha = $_GET['fecha'] ?? date('Y-m-d');
            $ingresos = $this->ingresoModel->getByFecha($fecha);
            $pageTitle = 'Historial de Asistencia';
            require_once ROOT_PATH . '/views/asistencia/historial.php';
        } else {
            $aprendiz = $this->aprendizModel->getByUsuario(Auth::getUserId());
            if ($aprendiz) {
                $ingresos = $this->ingresoModel->getByAprendiz($aprendiz['id_aprendiz'], $fechaInicio, $fechaFin);
                $estadisticas = $this->ingresoModel->getEstadisticasRetardo($aprendiz['id_aprendiz'], $fechaInicio, $fechaFin);
            } else {
                $ingresos = [];
                $estadisticas = null;
            }
            $pageTitle = 'Mi Historial de Asistencia';
            require_once ROOT_PATH . '/views/asistencia/historial.php';
        }
    }

    /**
     * Exporta el reporte de asistencia a Excel delegando a ReporteController
     */
    public function exportarExcel(): void {
        $reporteController = new ReporteController($this->db);
        $reporteController->exportarExcel();
    }

    /**
     * Exporta el reporte de asistencia a PDF delegando a ReporteController
     */
    public function exportarPdf(): void {
        $reporteController = new ReporteController($this->db);
        $reporteController->exportarPdf();
    }
}
