<?php
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

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->ingresoModel  = new IngresoAsistencia($db);
        $this->aprendizModel = new Aprendiz($db);
        $this->horarioModel  = new Horario($db);
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

        // Buscar aprendiz por RFID
        $aprendiz = $this->aprendizModel->getByRfid($codigoRfid);

        if (!$aprendiz) {
            echo json_encode([
                'success' => false,
                'message' => 'No se encontró un aprendiz con este código RFID.',
                'tipo'    => 'error'
            ]);
            return;
        }

        // Obtener horario del día
        $horario = $this->horarioModel->getHorarioHoy($aprendiz['id_ficha']);

        if (!$horario) {
            echo json_encode([
                'success' => false,
                'message' => 'No hay horario configurado para hoy.',
                'tipo'    => 'warning',
                'aprendiz' => $aprendiz['nombre'] . ' ' . $aprendiz['apellido']
            ]);
            return;
        }

        $fecha     = date('Y-m-d');
        $horaActual = date('H:i:s');

        // Verificar si ya tiene registro hoy
        $ingresoHoy = $this->ingresoModel->getIngresoHoy($aprendiz['id_aprendiz']);

        if (!$ingresoHoy) {
            // ─── REGISTRAR ENTRADA ───
            $resultado = $this->calcularEstadoEntrada($horaActual, $horario);

            $this->ingresoModel->registrarEntrada(
                $aprendiz['id_aprendiz'],
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
                'aprendiz'   => $aprendiz['nombre'] . ' ' . $aprendiz['apellido'],
                'documento'  => $aprendiz['num_documento'],
                'ficha'      => $aprendiz['codigo_ficha'],
                'hora'       => $horaActual,
            ]);

        } elseif ($ingresoHoy['hora_salida'] === null) {
            // ─── REGISTRAR SALIDA ───
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
                'aprendiz'   => $aprendiz['nombre'] . ' ' . $aprendiz['apellido'],
                'documento'  => $aprendiz['num_documento'],
                'ficha'      => $aprendiz['codigo_ficha'],
                'hora'       => $horaActual,
            ]);

        } else {
            // Ya registró entrada y salida
            echo json_encode([
                'success' => false,
                'message' => 'ℹ️ Ya tiene registro completo de hoy (entrada y salida).',
                'tipo'    => 'info',
                'aprendiz' => $aprendiz['nombre'] . ' ' . $aprendiz['apellido']
            ]);
        }
    }

    /**
     * Calcula el estado de la entrada según el horario
     */
    private function calcularEstadoEntrada(string $horaActual, array $horario): array {
        $entrada   = strtotime($horario['hora_entrada']);
        $actual    = strtotime($horaActual);
        $tolerancia = (int) $horario['tolerancia_minutos'];

        $limiteTolerancia = $entrada + ($tolerancia * 60);

        if ($actual <= $limiteTolerancia) {
            return ['estado' => 'A_Tiempo', 'minutos_retardo' => 0];
        } else {
            $minutosRetardo = (int) ceil(($actual - $entrada) / 60);
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
}
