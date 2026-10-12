<?php
declare(strict_types=1);

/**
 * ReporteController.php — Controlador de Reportes de Asistencia
 * 
 * Gestiona la visualización, filtrado dinámico y exportación a formatos
 * Excel (.xlsx) y PDF de los reportes consolidados de asistencia.
 */
class ReporteController {
    private PDO $db;
    private IngresoAsistencia $ingresoModel;
    private Ficha $fichaModel;

    public function __construct(PDO $db) {
        $this->db           = $db;
        $this->ingresoModel = new IngresoAsistencia($db);
        $this->fichaModel   = new Ficha($db);
    }

    /**
     * Procesa y normaliza los parámetros de filtrado desde la petición GET.
     * Soporta filtros dinámicos por día, semana, mes o rango personalizado.
     *
     * @return array{fecha_inicio: string, fecha_fin: string, ficha_id: ?int, tipo_reporte: string}
     */
    public function procesarFiltros(): array {
        $tipoReporte = trim((string) ($_GET['tipo_reporte'] ?? 'rango'));
        $fichaIdRaw  = $_GET['id_ficha'] ?? ($_GET['ficha_id'] ?? null);
        $fichaId     = !empty($fichaIdRaw) && is_numeric($fichaIdRaw) ? (int) $fichaIdRaw : null;

        $hoy = date('Y-m-d');
        $fechaInicioParam = trim((string) ($_GET['fecha_inicio'] ?? ''));
        $fechaFinParam    = trim((string) ($_GET['fecha_fin'] ?? ''));

        switch ($tipoReporte) {
            case 'dia':
            case 'diario':
            case 'hoy':
                $tipoReporte = 'dia';
                $fechaInicio = !empty($fechaInicioParam) ? $fechaInicioParam : $hoy;
                $fechaFin    = $fechaInicio;
                break;

            case 'semana':
            case 'semanal':
                $tipoReporte = 'semana';
                $refDate     = !empty($fechaInicioParam) ? new DateTimeImmutable($fechaInicioParam) : new DateTimeImmutable($hoy);
                $fechaInicio = $refDate->modify('monday this week')->format('Y-m-d');
                $fechaFin    = $refDate->modify('saturday this week')->format('Y-m-d');
                break;

            case 'mes':
            case 'mensual':
                $tipoReporte = 'mes';
                $refDate     = !empty($fechaInicioParam) ? new DateTimeImmutable($fechaInicioParam) : new DateTimeImmutable($hoy);
                $fechaInicio = $refDate->modify('first day of this month')->format('Y-m-d');
                $fechaFin    = $refDate->modify('last day of this month')->format('Y-m-d');
                break;

            case 'rango':
            case 'personalizado':
            default:
                $tipoReporte = 'rango';
                $fechaInicio = !empty($fechaInicioParam) ? $fechaInicioParam : date('Y-m-01');
                $fechaFin    = !empty($fechaFinParam)    ? $fechaFinParam    : $hoy;
                break;
        }

        // Validación de coherencia cronológica
        if ($fechaInicio > $fechaFin) {
            [$fechaInicio, $fechaFin] = [$fechaFin, $fechaInicio];
        }

        return [
            'fecha_inicio' => $fechaInicio,
            'fecha_fin'    => $fechaFin,
            'ficha_id'     => $fichaId,
            'tipo_reporte' => $tipoReporte,
        ];
    }

    /**
     * Muestra la vista principal de reportes con filtros y tabla consolidada.
     */
    public function index(): void {
        Auth::requireAdmin();

        $fichas  = $this->fichaModel->getAll();
        $filtros = $this->procesarFiltros();

        // Determinar si el usuario activó la generación del reporte
        $generado = isset($_GET['generar']) || isset($_GET['tipo_reporte']) || isset($_GET['id_ficha']) || isset($_GET['fecha_inicio']);

        $registros = [];
        if ($generado) {
            try {
                $registros = $this->ingresoModel->obtenerReporteAsistencia(
                    $filtros['fecha_inicio'],
                    $filtros['fecha_fin'],
                    $filtros['ficha_id']
                );
            } catch (\Throwable $e) {
                error_log('[ReporteController::index] ' . $e->getMessage());
                Auth::setFlash('error', 'Error al consultar los datos del reporte: ' . $e->getMessage());
            }
        }

        // Variables para la vista
        $idFicha     = $filtros['ficha_id'];
        $fechaInicio = $filtros['fecha_inicio'];
        $fechaFin    = $filtros['fecha_fin'];
        $tipoReporte = $filtros['tipo_reporte'];
        $pageTitle   = 'Reportes de Asistencia';

        require_once ROOT_PATH . '/views/reportes/index.php';
    }

    /**
     * Exporta el reporte consolidado de asistencia a formato Excel (.xlsx)
     * utilizando PhpOffice/PhpSpreadsheet con estilos visuales y encabezados HTTP seguros.
     */
    public function exportarExcel(): void {
        Auth::requireAdmin();

        try {
            // Verificar disponibilidad de PhpSpreadsheet
            if (!class_exists(\PhpOffice\PhpSpreadsheet\Spreadsheet::class)) {
                throw new \RuntimeException('La librería PhpSpreadsheet no está instalada en el servidor. Ejecute "composer require phpoffice/phpspreadsheet".');
            }

            $filtros = $this->procesarFiltros();
            $datos = $this->ingresoModel->obtenerReporteAsistencia(
                $filtros['fecha_inicio'],
                $filtros['fecha_fin'],
                $filtros['ficha_id']
            );

            // Obtener información descriptiva de la ficha si se filtró por una
            $nombreFicha = 'Todas las fichas';
            if ($filtros['ficha_id'] !== null) {
                $infoFicha = $this->fichaModel->getById($filtros['ficha_id']);
                if ($infoFicha) {
                    $nombreFicha = $infoFicha['codigo_ficha'] . ' — ' . $infoFicha['nombre_programa'];
                }
            }

            // Crear libro y hoja de cálculo
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $spreadsheet->getProperties()
                ->setCreator('Sistema Control Ingreso Aprendices (SCIA) - SENA')
                ->setTitle('Reporte Consolidado de Asistencia')
                ->setSubject('Reporte de Asistencia y Novedades');

            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Consolidado Asistencia');

            // ─── 1. ENCABEZADO INSTITUCIONAL / TÍTULO ───
            $sheet->mergeCells('A1:I1');
            $sheet->setCellValue('A1', 'SISTEMA DE CONTROL DE INGRESO DE APRENDICES (SCIA) — REPORTE DE ASISTENCIA');
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('FFFFFF');
            $sheet->getStyle('A1')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('047857'); // Verde SENA
            $sheet->getStyle('A1')->getAlignment()
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
                ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            $sheet->getRowDimension(1)->setRowHeight(32);

            // Subtítulo con metadata de filtros
            $sheet->mergeCells('A2:I2');
            $subtitulo = sprintf(
                'Período: %s al %s | Modalidad: %s | Ficha: %s | Generado: %s',
                $filtros['fecha_inicio'],
                $filtros['fecha_fin'],
                strtoupper($filtros['tipo_reporte']),
                $nombreFicha,
                date('d/m/Y H:i:s')
            );
            $sheet->setCellValue('A2', $subtitulo);
            $sheet->getStyle('A2')->getFont()->setSize(9)->setItalic(true)->getColor()->setRGB('475569');
            $sheet->getStyle('A2')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setRGB('F1F5F9');
            $sheet->getStyle('A2')->getAlignment()
                ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
                ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
            $sheet->getRowDimension(2)->setRowHeight(22);

            // ─── 2. ENCABEZADOS DE COLUMNA (FILA 4) ───
            $headers = [
                'A' => '#',
                'B' => 'Documento',
                'C' => 'Nombre del Aprendiz',
                'D' => 'Ficha',
                'E' => 'Programa de Formación',
                'F' => 'Instructor Líder',
                'G' => 'Min. Retardo',
                'H' => 'Días Inasistencia',
                'I' => 'Fechas de Inasistencia (Detalle)',
            ];

            $headerRow = 4;
            $sheet->getRowDimension($headerRow)->setRowHeight(26);

            foreach ($headers as $col => $texto) {
                $cell = $col . $headerRow;
                $sheet->setCellValue($cell, $texto);
                $sheet->getStyle($cell)->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle($cell)->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('059669'); // Emerald 600
                $sheet->getStyle($cell)->getAlignment()
                    ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER)
                    ->setHorizontal(in_array($col, ['A', 'B', 'D', 'G', 'H'])
                        ? \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER
                        : \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle($cell)->getBorders()->getAllBorders()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
                    ->getColor()->setRGB('047857');
            }

            // ─── 3. LLENADO DE DATOS (FILA 5 EN ADELANTE) ───
            $startRow = 5;
            $currentRow = $startRow;

            if (empty($datos)) {
                $sheet->mergeCells("A{$currentRow}:I{$currentRow}");
                $sheet->setCellValue("A{$currentRow}", 'No se encontraron registros de aprendices con los filtros aplicados.');
                $sheet->getStyle("A{$currentRow}")->getFont()->setItalic(true)->getColor()->setRGB('64748B');
                $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                $sheet->getRowDimension($currentRow)->setRowHeight(24);
                $currentRow++;
            } else {
                foreach ($datos as $idx => $row) {
                    $bgZebra = ($idx % 2 === 0) ? 'FFFFFF' : 'F8FAFC';

                    $sheet->setCellValue("A{$currentRow}", $idx + 1);
                    // Forzar documento como texto para conservar ceros a la izquierda
                    $sheet->setCellValueExplicit(
                        "B{$currentRow}",
                        (string) $row['num_documento'],
                        \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING
                    );
                    $sheet->setCellValue("C{$currentRow}", $row['nombre_completo']);
                    $sheet->setCellValue("D{$currentRow}", $row['codigo_ficha']);
                    $sheet->setCellValue("E{$currentRow}", $row['nombre_programa']);
                    $sheet->setCellValue("F{$currentRow}", $row['nombre_instructor']);
                    $sheet->setCellValue("G{$currentRow}", (int) $row['total_minutos_retardo']);
                    $sheet->setCellValue("H{$currentRow}", (int) $row['total_inasistencias']);
                    $sheet->setCellValue("I{$currentRow}", $row['dias_inasistencia']);

                    // Alineación de datos
                    $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("B{$currentRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("C{$currentRow}")->getFont()->setBold(true);
                    $sheet->getStyle("D{$currentRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("G{$currentRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("H{$currentRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("I{$currentRow}")->getAlignment()->setWrapText(true);

                    // Estilo de fondo zebra y bordes finos
                    $range = "A{$currentRow}:I{$currentRow}";
                    $sheet->getStyle($range)->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB($bgZebra);
                    $sheet->getStyle($range)->getBorders()->getAllBorders()
                        ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN)
                        ->getColor()->setRGB('E2E8F0');

                    $sheet->getRowDimension($currentRow)->setRowHeight(22);
                    $currentRow++;
                }

                // ─── 4. FILA DE TOTALES / RESUMEN ───
                $lastDataRow = $currentRow - 1;
                $sheet->mergeCells("A{$currentRow}:F{$currentRow}");
                $sheet->setCellValue("A{$currentRow}", 'TOTALES CONSOLIDADOS:');
                $sheet->setCellValue("G{$currentRow}", "=SUM(G{$startRow}:G{$lastDataRow})");
                $sheet->setCellValue("H{$currentRow}", "=SUM(H{$startRow}:H{$lastDataRow})");
                $sheet->setCellValue("I{$currentRow}", '');

                $sheet->getStyle("A{$currentRow}:I{$currentRow}")->getFont()->setBold(true);
                $sheet->getStyle("A{$currentRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("G{$currentRow}:H{$currentRow}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle("A{$currentRow}:I{$currentRow}")->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('E2E8F0');
                $sheet->getStyle("A{$currentRow}:I{$currentRow}")->getBorders()->getTop()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_DOUBLE);
                $sheet->getStyle("A{$currentRow}:I{$currentRow}")->getBorders()->getBottom()
                    ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THICK)
                    ->getColor()->setRGB('047857');
                $sheet->getRowDimension($currentRow)->setRowHeight(24);
            }

            // Autoajustar ancho de columnas de datos A..H
            foreach (['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'] as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
            // Columna de detalle con ancho fijo para ajuste de texto
            $sheet->getColumnDimension('I')->setWidth(40);

            // ─── 5. CABECERAS HTTP Y DESCARGA LIMPIA ───
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            $nombreArchivo = 'reporte_asistencia_' . date('Ymd_His') . '.xlsx';

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="' . $nombreArchivo . '"');
            header('Cache-Control: max-age=0');
            header('Cache-Control: max-age=1');
            header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
            header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
            header('Pragma: public');

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;

        } catch (\Throwable $e) {
            error_log('[ReporteController::exportarExcel] ' . $e->getMessage());
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            Auth::setFlash('error', 'Ocurrió un error al generar el archivo Excel: ' . $e->getMessage());
            header('Location: ' . BASE_URL . '?action=reportes');
            exit;
        }
    }

    /**
     * Exporta el reporte consolidado de asistencia a formato PDF
     * utilizando Dompdf con plantilla HTML estilizada y orientación apaisada.
     */
    public function exportarPdf(): void {
        Auth::requireAdmin();

        try {
            // Verificar disponibilidad de Dompdf
            if (!class_exists(\Dompdf\Dompdf::class)) {
                throw new \RuntimeException('La librería Dompdf no está instalada en el servidor. Ejecute "composer require dompdf/dompdf".');
            }

            $filtros = $this->procesarFiltros();
            $datos = $this->ingresoModel->obtenerReporteAsistencia(
                $filtros['fecha_inicio'],
                $filtros['fecha_fin'],
                $filtros['ficha_id']
            );

            // Nombre de ficha para título
            $nombreFicha = 'Todas las fichas';
            if ($filtros['ficha_id'] !== null) {
                $infoFicha = $this->fichaModel->getById($filtros['ficha_id']);
                if ($infoFicha) {
                    $nombreFicha = $infoFicha['codigo_ficha'] . ' — ' . $infoFicha['nombre_programa'];
                }
            }

            // Generar HTML optimizado para motor de renderizado PDF
            $html = $this->generarHtmlReportePdf($datos, $filtros, $nombreFicha);

            $options = new \Dompdf\Options();
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', true);
            $options->set('defaultFont', 'Helvetica');

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'landscape');
            $dompdf->render();

            // Limpieza de búfer de salida para no corromper el binario PDF
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            $nombreArchivo = 'reporte_asistencia_' . date('Ymd_His') . '.pdf';
            $dompdf->stream($nombreArchivo, ['Attachment' => true]);
            exit;

        } catch (\Throwable $e) {
            error_log('[ReporteController::exportarPdf] ' . $e->getMessage());
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
            Auth::setFlash('error', 'Ocurrió un error al generar el archivo PDF: ' . $e->getMessage());
            header('Location: ' . BASE_URL . '?action=reportes');
            exit;
        }
    }

    /**
     * Construye la plantilla HTML institucional con estilos CSS print para el PDF.
     *
     * @param array<int, array<string, mixed>> $datos
     * @param array{fecha_inicio: string, fecha_fin: string, ficha_id: ?int, tipo_reporte: string} $filtros
     * @param string $nombreFicha
     * @return string
     */
    private function generarHtmlReportePdf(array $datos, array $filtros, string $nombreFicha): string {
        $totalRetardoMin = 0;
        $totalInasistencias = 0;
        foreach ($datos as $row) {
            $totalRetardoMin   += (int) ($row['total_minutos_retardo'] ?? 0);
            $totalInasistencias += (int) ($row['total_inasistencias'] ?? 0);
        }

        $periodoTexto = sprintf('Del %s al %s', $filtros['fecha_inicio'], $filtros['fecha_fin']);
        $generadoEl   = date('d/m/Y H:i:s');

        ob_start();
        ?>
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <title>Reporte de Asistencia</title>
            <style>
                @page {
                    margin: 12mm 10mm 15mm 10mm;
                }
                body {
                    font-family: Helvetica, Arial, sans-serif;
                    font-size: 9.5px;
                    color: #1e293b;
                    margin: 0;
                    padding: 0;
                }
                .header-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 12px;
                    border-bottom: 2px solid #047857;
                    padding-bottom: 8px;
                }
                .header-title {
                    font-size: 15px;
                    font-weight: bold;
                    color: #047857;
                    text-transform: uppercase;
                    margin: 0 0 4px 0;
                }
                .header-subtitle {
                    font-size: 10px;
                    color: #475569;
                    margin: 0;
                }
                .header-meta {
                    text-align: right;
                    font-size: 9px;
                    color: #64748b;
                }
                .summary-cards {
                    width: 100%;
                    margin-bottom: 14px;
                    border-collapse: collapse;
                }
                .summary-card {
                    background-color: #f8fafc;
                    border: 1px solid #e2e8f0;
                    border-radius: 4px;
                    padding: 6px 10px;
                    text-align: center;
                }
                .summary-card-val {
                    font-size: 13px;
                    font-weight: bold;
                    color: #047857;
                }
                .summary-card-lbl {
                    font-size: 8px;
                    color: #64748b;
                    text-transform: uppercase;
                }
                .data-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 5px;
                }
                .data-table th {
                    background-color: #047857;
                    color: #ffffff;
                    padding: 6px 5px;
                    font-size: 8.5px;
                    text-transform: uppercase;
                    border: 1px solid #0369a1;
                    letter-spacing: 0.3px;
                }
                .data-table td {
                    padding: 5px;
                    border: 1px solid #cbd5e1;
                    font-size: 8.5px;
                    vertical-align: middle;
                }
                .data-table tr:nth-child(even) {
                    background-color: #f8fafc;
                }
                .text-center { text-align: center; }
                .text-right { text-align: right; }
                .text-left { text-align: left; }
                .badge-retardo {
                    background-color: #fef3c7;
                    color: #b45309;
                    font-weight: bold;
                    padding: 2px 5px;
                    border-radius: 3px;
                }
                .badge-inasistencia {
                    background-color: #fee2e2;
                    color: #b91c1c;
                    font-weight: bold;
                    padding: 2px 5px;
                    border-radius: 3px;
                }
                .badge-ok {
                    color: #047857;
                    font-weight: normal;
                }
                .table-totals {
                    background-color: #e2e8f0 !important;
                    font-weight: bold;
                    border-top: 2px solid #047857;
                }
                .footer {
                    position: fixed;
                    bottom: -10mm;
                    left: 0;
                    right: 0;
                    text-align: center;
                    font-size: 8px;
                    color: #94a3b8;
                    border-top: 1px solid #e2e8f0;
                    padding-top: 4px;
                }
            </style>
        </head>
        <body>
            <table class="header-table">
                <tr>
                    <td style="width: 70%;">
                        <div class="header-title">Sistema de Control de Ingreso de Aprendices (SCIA)</div>
                        <div class="header-subtitle">Reporte Consolidado de Asistencia, Retardos e Inasistencias</div>
                        <div style="font-size: 8.5px; color: #64748b; margin-top: 3px;">
                            <strong>Ficha:</strong> <?= htmlspecialchars($nombreFicha) ?> | 
                            <strong>Período:</strong> <?= htmlspecialchars($periodoTexto) ?>
                        </div>
                    </td>
                    <td class="header-meta" style="width: 30%;">
                        <strong>Modalidad:</strong> <?= strtoupper(htmlspecialchars($filtros['tipo_reporte'])) ?><br>
                        <strong>Fecha de Emisión:</strong> <?= $generadoEl ?><br>
                        <strong>Generado por:</strong> Administrador SENA
                    </td>
                </tr>
            </table>

            <!-- Resumen numérico -->
            <table class="summary-cards">
                <tr>
                    <td class="summary-card" style="width: 25%;">
                        <div class="summary-card-val"><?= count($datos) ?></div>
                        <div class="summary-card-lbl">Aprendices Evaluados</div>
                    </td>
                    <td class="summary-card" style="width: 25%;">
                        <div class="summary-card-val" style="color: #b91c1c;"><?= $totalInasistencias ?></div>
                        <div class="summary-card-lbl">Total Días Inasistencia</div>
                    </td>
                    <td class="summary-card" style="width: 25%;">
                        <div class="summary-card-val" style="color: #b45309;"><?= $totalRetardoMin ?> min</div>
                        <div class="summary-card-lbl">Minutos Totales Retardo</div>
                    </td>
                    <td class="summary-card" style="width: 25%;">
                        <div class="summary-card-val"><?= htmlspecialchars($periodoTexto) ?></div>
                        <div class="summary-card-lbl">Ventana Temporal</div>
                    </td>
                </tr>
            </table>

            <!-- Tabla de datos -->
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 3%;" class="text-center">#</th>
                        <th style="width: 9%;" class="text-center">Documento</th>
                        <th style="width: 17%;">Aprendiz</th>
                        <th style="width: 8%;" class="text-center">Ficha</th>
                        <th style="width: 15%;">Programa</th>
                        <th style="width: 15%;">Instructor Líder</th>
                        <th style="width: 9%;" class="text-right">Min. Retardo</th>
                        <th style="width: 9%;" class="text-right">Inasistencias</th>
                        <th style="width: 15%;">Días de Inasistencia (Fechas)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($datos)): ?>
                        <tr>
                            <td colspan="9" class="text-center" style="padding: 15px; color: #64748b; font-style: italic;">
                                No se encontraron registros de aprendices para los filtros seleccionados.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($datos as $idx => $r): ?>
                            <tr>
                                <td class="text-center"><?= $idx + 1 ?></td>
                                <td class="text-center"><strong><?= htmlspecialchars((string) $r['num_documento']) ?></strong></td>
                                <td><?= htmlspecialchars($r['nombre_completo']) ?></td>
                                <td class="text-center"><?= htmlspecialchars($r['codigo_ficha']) ?></td>
                                <td><?= htmlspecialchars($r['nombre_programa']) ?></td>
                                <td><?= htmlspecialchars($r['nombre_instructor']) ?></td>
                                <td class="text-right">
                                    <?php if ($r['total_minutos_retardo'] > 0): ?>
                                        <span class="badge-retardo"><?= $r['total_minutos_retardo'] ?> min</span>
                                    <?php else: ?>
                                        <span class="badge-ok">0 min</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-right">
                                    <?php if ($r['total_inasistencias'] > 0): ?>
                                        <span class="badge-inasistencia"><?= $r['total_inasistencias'] ?></span>
                                    <?php else: ?>
                                        <span class="badge-ok">0</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 8px;"><?= htmlspecialchars($r['dias_inasistencia']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="table-totals">
                            <td colspan="6" class="text-right">TOTALES GENERALES:</td>
                            <td class="text-right"><?= $totalRetardoMin ?> min</td>
                            <td class="text-right"><?= $totalInasistencias ?></td>
                            <td>—</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <div class="footer">
                Sistema de Control de Ingreso de Aprendices — Documento Oficial generado automáticamente por SCIA SENA.
            </div>
        </body>
        </html>
        <?php
        return (string) ob_get_clean();
    }
}
