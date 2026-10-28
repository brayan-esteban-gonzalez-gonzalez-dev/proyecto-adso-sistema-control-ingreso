<?php
declare(strict_types=1);

/**
 * ReporteController.php — Controlador de Reportes Institucionales
 *
 * Genera reportes consolidados por ficha, individuales por aprendiz y de excusas,
 * con exportación en CSV, Excel (PhpSpreadsheet) y PDF (FPDF).
 */
class ReporteController {
    private PDO $db;
    private Reporte $model;

    public function __construct(PDO $db) {
        $this->db = $db;
        require_once ROOT_PATH . '/models/Reporte.php';
        $this->model = new Reporte($db);
    }     
    /**
     * Muestra la interfaz de reportes institucionales
     */
    public function index(): void {
        Auth::requireLogin();
        if (!Auth::isAdminOrInstructor()) {
            Auth::setFlash('error', 'Acceso restringido a administradores e instructores.');
            header('Location: ' . BASE_URL . '?action=dashboard');
            exit;
        }

        require_once ROOT_PATH . '/models/Ficha.php';
        require_once ROOT_PATH . '/models/Usuario.php';

        $fichaModel   = new Ficha($this->db);
        $usuarioModel = new Usuario($this->db);

        $fichas     = $fichaModel->getAll();
        $aprendices = $usuarioModel->getAprendices();
        $csrf_token = Auth::generateCSRF();
        $pageTitle  = 'Reportes Institucionales';

        require_once ROOT_PATH . '/views/reportes/index.php';
    }

    /**
     * Procesa la solicitud de generación de reporte
     */
    public function generar(): void {
        Auth::requireLogin();
        if (!Auth::isAdminOrInstructor()) {
            Auth::setFlash('error', 'Acceso restringido.');
            header('Location: ' . BASE_URL . '?action=dashboard');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . '?action=reportes');
            exit;
        }

        if (!Auth::validateCSRF($_POST['csrf_token'] ?? '')) {
            Auth::setFlash('error', 'Token de seguridad inválido. Recarga la página.');
            header('Location: ' . BASE_URL . '?action=reportes');
            exit;
        }

        $tipo        = $_POST['tipo'] ?? 'asistencia_ficha';
        $formato     = strtolower($_POST['formato'] ?? 'csv');
        $fechaInicio = $_POST['fecha_inicio'] ?? date('Y-m-01');
        $fechaFin    = $_POST['fecha_fin'] ?? date('Y-m-d');
        $fichaId     = (int) ($_POST['ficha_id'] ?? 0);
        $aprendizId  = (int) ($_POST['aprendiz_id'] ?? 0);

        $titulo = 'Reporte';
        $headers = [];
        $filas = [];

        if ($tipo === 'asistencia_ficha') {
            if ($fichaId <= 0) {
                Auth::setFlash('error', 'Debe seleccionar una ficha para este reporte.');
                header('Location: ' . BASE_URL . '?action=reportes');
                exit;
            }
            $titulo = 'Asistencia Consolidada Ficha ' . $fichaId;
            $headers = ['Aprendiz', 'Documento', 'Sesiones', 'Asistencias', 'Inasistencias', 'Retardos'];
            $datos = $this->model->asistenciaPorFicha($fichaId, $fechaInicio, $fechaFin);
            foreach ($datos as $d) {
                $filas[] = [
                    $d['aprendiz'] ?? '',
                    $d['identificacion'] ?? '',
                    (string) ($d['sesiones'] ?? 0),
                    (string) ($d['asistencias'] ?? 0),
                    (string) ($d['inasistencias'] ?? 0),
                    (string) ($d['retardos'] ?? 0),
                ];
            }
        } elseif ($tipo === 'asistencia_aprendiz') {
            if ($aprendizId <= 0) {
                Auth::setFlash('error', 'Debe seleccionar un aprendiz.');
                header('Location: ' . BASE_URL . '?action=reportes');
                exit;
            }
            $titulo = 'Asistencia Individual Aprendiz ' . $aprendizId;
            $headers = ['Fecha', 'Hora Inicio', 'Hora Fin', 'Competencia', 'Hora Entrada', 'Hora Salida', 'Retardo (min)'];
            $datos = $this->model->detalleAprendiz($aprendizId, 0, $fechaInicio, $fechaFin);
            foreach ($datos as $d) {
                $filas[] = [
                    $d['fecha'] ?? '',
                    $d['hora_inicio'] ?? '',
                    $d['hora_fin'] ?? '—',
                    $d['competencia'] ?? '—',
                    $d['hora_entrada'] ?? '—',
                    $d['hora_salida'] ?? '—',
                    (string) ($d['minutos_retardo'] ?? 0),
                ];
            }
        } elseif ($tipo === 'excusas') {
            $titulo = 'Relación de Excusas (' . $fechaInicio . ' a ' . $fechaFin . ')';
            $headers = ['Fecha', 'Aprendiz', 'Documento', 'Ficha', 'Motivo', 'Estado'];
            $datos = $this->model->excusas($fechaInicio, $fechaFin, $fichaId);
            foreach ($datos as $d) {
                $filas[] = [
                    $d['fecha'] ?? '',
                    $d['aprendiz'] ?? '',
                    $d['identificacion'] ?? '',
                    $d['codigo_ficha'] ?? '—',
                    $d['motivo'] ?? '',
                    $d['estado'] ?? '',
                ];
            }
        } else {
            Auth::setFlash('error', 'Tipo de reporte no válido.');
            header('Location: ' . BASE_URL . '?action=reportes');
            exit;
        }

        // Exportar según formato
        if ($formato === 'pdf') {
            $this->exportarPdf($titulo, $headers, $filas, $fechaInicio, $fechaFin);
        } else {
            // CSV o Excel (CSV con delimitador ; y BOM para apertura nativa en Excel)
            $this->exportarCsv($titulo, $headers, $filas);
        }
    }

    /**
     * Sanitiza una cadena contra inyección de fórmulas en el CSV.
     * Antepone una comilla simple ('), según la recomendación de OWASP,
     * cuando el valor inicia (tras espacios/saltos, espacio inseparable o
     * byte nulo) con =, +, -, @, tabulador o retorno de carro.
     */
    private function sanitizarCsv(string $valor): string {
        if (preg_match('/^[\s\x00\xC2\xA0]*[=\+\-@\t]/', $valor)) {
            return '\'' . $valor;
        }
        return $valor;
    }

    /**
     * Exporta los datos en formato CSV con BOM UTF-8
     */
    private function exportarCsv(string $titulo, array $headers, array $filas): void {
        $filename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', strtolower($titulo)) . '_' . date('Ymd_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        // BOM UTF-8 para apertura correcta en Excel
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

        $headersSanitizados = array_map(
            fn ($valor) => $this->sanitizarCsv((string) $valor),
            $headers
        );
        fputcsv($out, $headersSanitizados, ';');
        foreach ($filas as $fila) {
            $filaSanitizada = array_map(
                fn ($valor) => $this->sanitizarCsv((string) $valor),
                $fila
            );
            fputcsv($out, $filaSanitizada, ';');
        }
        fclose($out);
        exit;
    }

    /**
     * Exporta los datos en formato PDF usando la librería FPDF
     */
    private function exportarPdf(string $titulo, array $headers, array $filas, string $inicio, string $fin): void {
        $fpdfPath = ROOT_PATH . '/librerias/fpdf/fpdf.php';
        if (!file_exists($fpdfPath)) {
            // Fallback a CSV si FPDF no está disponible
            $this->exportarCsv($titulo, $headers, $filas);
            return;
        }

        require_once $fpdfPath;

        $pdf = new FPDF('L', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 15);

        // Encabezado
        $pdf->Cell(0, 10, utf8_decode('SENA — Sistema de Control de Ingreso (SCIA)'), 0, 1, 'C');
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 8, utf8_decode($titulo), 0, 1, 'C');
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(0, 6, utf8_decode("Periodo: $inicio a $fin | Generado: " . date('Y-m-d H:i:s')), 0, 1, 'C');
        $pdf->Ln(4);

        // Tabla
        $colWidth = floor(275 / max(count($headers), 1));
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor(30, 41, 59);
        $pdf->SetTextColor(255, 255, 255);

        foreach ($headers as $h) {
            $pdf->Cell($colWidth, 8, utf8_decode($h), 1, 0, 'C', true);
        }
        $pdf->Ln();

        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(0, 0, 0);
        $fill = false;
        $pdf->SetFillColor(241, 245, 249);

        foreach ($filas as $fila) {
            foreach ($fila as $val) {
                // Truncar para que quepa en la celda
                $texto = mb_strimwidth((string)$val, 0, 32, '...');
                $pdf->Cell($colWidth, 7, utf8_decode($texto), 1, 0, 'L', $fill);
            }
            $pdf->Ln();
            $fill = !$fill;
        }

        $filename = preg_replace('/[^a-zA-Z0-9_\-]/', '_', strtolower($titulo)) . '.pdf';
        $pdf->Output('D', $filename);
        exit;
    }
}
