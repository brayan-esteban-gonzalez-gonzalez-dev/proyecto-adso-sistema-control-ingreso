<?php
/**
 * HorarioController.php — Controlador de Horarios y Sesiones
 *
 * Gestión de horarios e importación masiva desde archivos Excel (calendario visual).
 */
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class HorarioController {
    private PDO $db;
    private Sesion $sesionModel;
    private Ficha $fichaModel;
    private Usuario $usuarioModel;

    public function __construct(PDO $db) {
        $this->db = $db;
        $this->sesionModel = new Sesion($db);
        $this->fichaModel = new Ficha($db);
        $this->usuarioModel = new Usuario($db);
    }

    /**
     * Lista las sesiones de horario programadas
     */
    public function index(): void {
        Auth::requireLogin();

        $pageTitle = 'Gestión de Horarios';
        $fichaId = isset($_GET['ficha_id']) && !empty($_GET['ficha_id']) ? (int)$_GET['ficha_id'] : null;

        $fichas = $this->fichaModel->getAll();

        if ($fichaId) {
            $sesiones = $this->sesionModel->getByFicha($fichaId);
            $fichaSeleccionada = $this->fichaModel->getById($fichaId);
        } else {
            $sesiones = $this->sesionModel->getAll();
            $fichaSeleccionada = null;
        }

        require_once ROOT_PATH . '/views/horarios/index.php';
    }

    /**
     * Muestra el formulario para importar horarios desde Excel
     */
    public function importar(): void {
        Auth::requireAdmin();

        $pageTitle = 'Importar Horarios desde Excel';
        $fichas = $this->fichaModel->getAll();
        $csrfToken = Auth::generateCSRF();

        require_once ROOT_PATH . '/views/horarios/importar.php';
    }

    /**
     * Procesa la subida y extracción del archivo Excel
     */
    public function procesar(): void {
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
            $reader = IOFactory::createReaderForFile($fileTmp);
            $reader->setReadDataOnly(false);
            $spreadsheet = $reader->load($fileTmp);
            $sheet = $spreadsheet->getActiveSheet();

            $resultado = $this->parsearExcelHorarios($sheet);

            $pageTitle = 'Resultado de la Importación';
            require_once ROOT_PATH . '/views/horarios/resultado.php';

        } catch (Exception $e) {
            Auth::setFlash('error', 'Error al procesar el archivo Excel: ' . $e->getMessage());
            header('Location: ' . BASE_URL . '?action=horarios/importar');
            exit;
        }
    }

    /**
     * Elimina una sesión
     */
    public function eliminar(): void {
        Auth::requireAdmin();

        $id = (int)($_GET['id'] ?? 0);
        if ($id > 0) {
            $this->sesionModel->delete($id);
            Auth::setFlash('success', 'Sesión de horario eliminada correctamente.');
        }

        $redirect = isset($_GET['ficha_id']) ? '&ficha_id=' . (int)$_GET['ficha_id'] : '';
        header('Location: ' . BASE_URL . '?action=horarios' . $redirect);
        exit;
    }

    /**
     * Algoritmo inteligente para parsear la grilla visual de horarios en el Excel
     */
    private function parsearExcelHorarios($sheet): array {
        $highestRow = $sheet->getHighestRow();
        $highestCol = $sheet->getHighestColumn();
        $highestColIndex = Coordinate::columnIndexFromString($highestCol);
        $resultado = [
            'ficha_detectada' => null,
            'total_procesadas' => 0,
            'creadas' => 0,
            'omitidas' => 0,
            'errores' => 0,
            'detalles' => []
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
            'ENERO' => '01', 'FEBRERO' => '02', 'MARZO' => '03', 'ABRIL' => '04',
            'MAYO' => '05', 'JUNIO' => '06', 'JULIO' => '07', 'AGOSTO' => '08',
            'SEPTIEMBRE' => '09', 'OCTUBRE' => '10', 'NOVIEMBRE' => '11', 'DICIEMBRE' => '12'
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

            // Fila de encabezado de días encontrada en fila $r
            // Buscar fila de números de día (generalmente $r + 1 o en la misma vecindad)
            $dayNumberRow = $r + 1;
            
            // Buscar fila de meses (generalmente $r - 1 o $r - 2)
            $monthRow = max(1, $r - 1);

            // Mapear cada columna a su fecha YYYY-MM-DD
            $colDates = [];
            $currentMonth = date('m');

            for ($c = 1; $c <= $highestColIndex; $c++) {
                // Verificar si hay nombre de mes en la fila de meses o superior
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

            // Filas de clases para esta semana: desde $dayNumberRow + 1 hasta la siguiente fila de días o final
            $startClassRow = $dayNumberRow + 1;
            $endClassRow = min($highestRow, $startClassRow + 25);

            // Buscar si hay otra fila de días antes de 25 filas
            for ($checkR = $startClassRow; $checkR <= min($highestRow, $startClassRow + 25); $checkR++) {
                for ($checkC = 1; $checkC <= $highestColIndex; $checkC++) {
                    $cVal = strtoupper(trim((string)$sheet->getCell([$checkC, $checkR])->getValue()));
                    if (in_array($cVal, $diasKeywords)) {
                        $endClassRow = $checkR - 1;
                        break 2;
                    }
                }
            }

            // Iterar por las celdas de este bloque
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

                    // Determinar límites del bloque (si es celda combinada o individual)
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

                        // Marcar todas las celdas de este merge como procesadas
                        for ($mr = $blockStartRow; $mr <= $blockEndRow; $mr++) {
                            for ($mc = $blockStartCol; $mc <= $blockEndCol; $mc++) {
                                $processedBlocks["{$mc}_{$mr}"] = true;
                            }
                        }
                    } else {
                        $processedBlocks[$cellKey] = true;
                    }

                    // Determinar hora de inicio y fin
                    // Revisamos columna B (hora inicio) y columna C (hora fin) o calculamos por fila
                    $horaInicioVal = $sheet->getCell([2, $blockStartRow])->getValue();
                    $horaFinVal = $sheet->getCell([3, $blockEndRow])->getValue();

                    if (is_numeric($horaInicioVal)) {
                        $horaInicio = sprintf('%02d:00:00', (int)$horaInicioVal);
                    } else {
                        // Fallback por bloque
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

                    // Parsear texto del instructor y metadata
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

                    if (!$instructor) {
                        $resultado['errores']++;
                        $resultado['detalles'][] = [
                            'tipo' => 'error',
                            'mensaje' => "Instructor \"$nombreInstructor\" no encontrado en la base de datos (Fecha: $fecha, Horario: $horaInicio - $horaFin)."
                        ];
                        continue;
                    }

                    // Verificar si ya existe la sesión
                    $existe = $this->sesionModel->existeSesion($fichaId, $fecha, $horaInicio);
                    if ($existe) {
                        $resultado['omitidas']++;
                        $resultado['detalles'][] = [
                            'tipo' => 'warning',
                            'mensaje' => "Sesión omitida (ya existía): Ficha {$fichaEncontrada['codigo']} el $fecha ($horaInicio - $horaFin) con {$instructor['nombre']} {$instructor['apellido']}."
                        ];
                        continue;
                    }

                    // Insertar sesión
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
}
