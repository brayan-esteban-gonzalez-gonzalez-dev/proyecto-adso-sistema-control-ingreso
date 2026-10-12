<?php

/**
 * HorarioController.php — Controlador de Horarios y Sesiones
 *
 * Gestión de horarios e importación masiva desde archivos Excel (calendario visual).
 */

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class HorarioController
{
    private PDO $db;
    private Sesion $sesionModel;
    private Ficha $fichaModel;
    private Usuario $usuarioModel;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->sesionModel = new Sesion($db);
        $this->fichaModel = new Ficha($db);
        $this->usuarioModel = new Usuario($db);
    }

    /**
     * Lista las sesiones de horario programadas
     */
    public function index(): void
    {
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
    public function eliminar(): void
    {
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
}
