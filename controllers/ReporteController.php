<?php
/**
 * ReporteController.php — Controlador de Reportes
 * 
 * Generación de reportes de asistencia con filtros,
 * exportación a PDF y Excel.
 */
class ReporteController {
    private PDO $db;
    private IngresoAsistencia $ingresoModel;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->ingresoModel = new IngresoAsistencia($db);
    }

    /**
     * Panel de reportes con filtros
     */
    public function index(): void {
        Auth::requireAdmin();

        $fichaModel = new Ficha($this->db);
        $fichas = $fichaModel->getAll();

        // Filtros
        $idFicha     = !empty($_GET['id_ficha'])     ? (int) $_GET['id_ficha'] : null;
        $fechaInicio = $_GET['fecha_inicio'] ?? null;
        $fechaFin    = $_GET['fecha_fin']    ?? null;
        $estado      = $_GET['estado']       ?? null;

        $registros = [];
        $generado = false;

        if ($idFicha || $fechaInicio || $fechaFin || $estado) {
            $registros = $this->ingresoModel->getReporte($idFicha, $fechaInicio, $fechaFin, $estado);
            $generado = true;
        }

        $pageTitle = 'Reportes de Asistencia';
        require_once ROOT_PATH . '/views/reportes/index.php';
    }

    /**
     * Exporta reporte a PDF
     */
    public function exportarPdf(): void {
        Auth::requireAdmin();

        // Verificar si Dompdf está disponible
        if (!class_exists('Dompdf\Dompdf')) {
            Auth::setFlash('error', 'La librería Dompdf no está instalada. Ejecute: composer require dompdf/dompdf');
            header('Location: ' . BASE_URL . '?action=reportes');
            exit;
        }

        $idFicha     = !empty($_GET['id_ficha'])     ? (int) $_GET['id_ficha'] : null;
        $fechaInicio = $_GET['fecha_inicio'] ?? null;
        $fechaFin    = $_GET['fecha_fin']    ?? null;
        $estado      = $_GET['estado']       ?? null;

        $registros = $this->ingresoModel->getReporte($idFicha, $fechaInicio, $fechaFin, $estado);

        // Generar HTML para el PDF
        $html = $this->generarHtmlReporte($registros, $fechaInicio, $fechaFin);

        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $nombre = 'reporte_asistencia_' . date('Y-m-d_His') . '.pdf';
        $dompdf->stream($nombre, ['Attachment' => true]);
        exit;
    }

    /**
     * Exporta reporte a Excel
     */
    public function exportarExcel(): void {
        Auth::requireAdmin();

        // Verificar si PhpSpreadsheet está disponible
        if (!class_exists('PhpOffice\PhpSpreadsheet\Spreadsheet')) {
            Auth::setFlash('error', 'La librería PhpSpreadsheet no está instalada. Ejecute: composer require phpoffice/phpspreadsheet');
            header('Location: ' . BASE_URL . '?action=reportes');
            exit;
        }

        $idFicha     = !empty($_GET['id_ficha'])     ? (int) $_GET['id_ficha'] : null;
        $fechaInicio = $_GET['fecha_inicio'] ?? null;
        $fechaFin    = $_GET['fecha_fin']    ?? null;
        $estado      = $_GET['estado']       ?? null;

        $registros = $this->ingresoModel->getReporte($idFicha, $fechaInicio, $fechaFin, $estado);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Reporte Asistencia');

        // Encabezados
        $headers = ['#', 'Documento', 'Nombre', 'Apellido', 'Ficha', 'Fecha', 'Entrada', 'Salida', 'Estado', 'Min. Retardo', 'Min. Salida Ant.'];
        foreach ($headers as $col => $header) {
            $sheet->setCellValueByColumnAndRow($col + 1, 1, $header);
            $sheet->getStyleByColumnAndRow($col + 1, 1)->getFont()->setBold(true);
            $sheet->getStyleByColumnAndRow($col + 1, 1)->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('10B981');
            $sheet->getStyleByColumnAndRow($col + 1, 1)->getFont()->getColor()->setRGB('FFFFFF');
        }

        // Datos
        foreach ($registros as $i => $reg) {
            $row = $i + 2;
            $sheet->setCellValueByColumnAndRow(1,  $row, $i + 1);
            $sheet->setCellValueByColumnAndRow(2,  $row, $reg['num_documento']);
            $sheet->setCellValueByColumnAndRow(3,  $row, $reg['nombre']);
            $sheet->setCellValueByColumnAndRow(4,  $row, $reg['apellido']);
            $sheet->setCellValueByColumnAndRow(5,  $row, $reg['codigo_ficha']);
            $sheet->setCellValueByColumnAndRow(6,  $row, $reg['fecha']);
            $sheet->setCellValueByColumnAndRow(7,  $row, $reg['hora_entrada'] ?? '—');
            $sheet->setCellValueByColumnAndRow(8,  $row, $reg['hora_salida'] ?? '—');
            $sheet->setCellValueByColumnAndRow(9,  $row, str_replace('_', ' ', $reg['estado']));
            $sheet->setCellValueByColumnAndRow(10, $row, $reg['minutos_retardo']);
            $sheet->setCellValueByColumnAndRow(11, $row, $reg['minutos_salida_anticipada']);
        }

        // Autoajustar columnas
        foreach (range('A', 'K') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Descargar
        $nombre = 'reporte_asistencia_' . date('Y-m-d_His') . '.xlsx';
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $nombre . '"');
        header('Cache-Control: max-age=0');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    /**
     * Genera el HTML del reporte para PDF
     */
    private function generarHtmlReporte(array $registros, ?string $fechaInicio, ?string $fechaFin): string {
        $periodo = '';
        if ($fechaInicio && $fechaFin) {
            $periodo = "Del {$fechaInicio} al {$fechaFin}";
        } elseif ($fechaInicio) {
            $periodo = "Desde {$fechaInicio}";
        } elseif ($fechaFin) {
            $periodo = "Hasta {$fechaFin}";
        } else {
            $periodo = "Todos los registros";
        }

        $html = '<!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; font-size: 11px; color: #1e293b; }
                h1 { color: #10b981; font-size: 18px; margin-bottom: 5px; }
                h2 { color: #64748b; font-size: 12px; font-weight: normal; margin-top: 0; }
                table { width: 100%; border-collapse: collapse; margin-top: 15px; }
                th { background-color: #10b981; color: white; padding: 8px 6px; text-align: left; font-size: 10px; }
                td { padding: 6px; border-bottom: 1px solid #e2e8f0; font-size: 10px; }
                tr:nth-child(even) { background-color: #f8fafc; }
                .estado-retardo { color: #f59e0b; font-weight: bold; }
                .estado-inasistencia { color: #ef4444; font-weight: bold; }
                .estado-atiempo { color: #10b981; }
                .estado-justificado { color: #3b82f6; }
                .footer { margin-top: 20px; text-align: center; color: #94a3b8; font-size: 9px; }
            </style>
        </head>
        <body>
            <h1>📋 Reporte de Asistencia</h1>
            <h2>' . htmlspecialchars($periodo) . ' — Generado: ' . date('d/m/Y H:i') . '</h2>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Documento</th>
                        <th>Nombre Completo</th>
                        <th>Ficha</th>
                        <th>Fecha</th>
                        <th>Entrada</th>
                        <th>Salida</th>
                        <th>Estado</th>
                        <th>Min. Retardo</th>
                        <th>Min. Sal. Ant.</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($registros as $i => $reg) {
            $estadoClass = match ($reg['estado']) {
                'Retardo'         => 'estado-retardo',
                'Inasistencia'    => 'estado-inasistencia',
                'A_Tiempo'        => 'estado-atiempo',
                'Justificado'     => 'estado-justificado',
                default           => '',
            };
            $estadoTexto = str_replace('_', ' ', $reg['estado']);

            $html .= '<tr>
                <td>' . ($i + 1) . '</td>
                <td>' . htmlspecialchars($reg['num_documento']) . '</td>
                <td>' . htmlspecialchars($reg['nombre'] . ' ' . $reg['apellido']) . '</td>
                <td>' . htmlspecialchars($reg['codigo_ficha']) . '</td>
                <td>' . $reg['fecha'] . '</td>
                <td>' . ($reg['hora_entrada'] ?? '—') . '</td>
                <td>' . ($reg['hora_salida'] ?? '—') . '</td>
                <td class="' . $estadoClass . '">' . $estadoTexto . '</td>
                <td>' . $reg['minutos_retardo'] . '</td>
                <td>' . $reg['minutos_salida_anticipada'] . '</td>
            </tr>';
        }

        $html .= '</tbody>
            </table>
            <p class="footer">Sistema de Control de Ingreso de Aprendices — Generado automáticamente</p>
        </body>
        </html>';

        return $html;
    }
}
