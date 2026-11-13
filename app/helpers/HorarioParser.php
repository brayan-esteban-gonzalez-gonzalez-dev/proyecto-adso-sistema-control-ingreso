<?php

/**
 * HorarioParser.php
 *
 * Clase responsable de leer archivos Excel de horarios (Ficha o Instructor)
 * y convertir la grilla visual en bloques de 3 horas normalizados.
 *
 * Esta clase SOLO parsea. No valida ni persiste datos.
 */

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class HorarioParser
{
    private array $mesesMap = [
        'ENERO'       => 1,
        'FEBRERO'     => 2,
        'MARZO'       => 3,
        'ABRIL'       => 4,
        'MAYO'        => 5,
        'JUNIO'       => 6,
        'JULIO'       => 7,
        'AGOSTO'      => 8,
        'SEPTIEMBRE'  => 9,
        'OCTUBRE'     => 10,
        'NOVIEMBRE'   => 11,
        'DICIEMBRE'   => 12,
    ];

    private array $diasKeywords = [
        'LUNES', 'MARTES', 'MIERCOLES', 'MIÉRCOLES',
        'JUEVES', 'VIERNES', 'SABADO', 'SÁBADO'
    ];

    /**
     * Parsea un archivo Excel y devuelve bloques normalizados.
     *
     * @param string $rutaArchivo Ruta temporal del archivo Excel
     * @param array  $opciones    Opciones: 'tipo' => 'ficha'|'instructor', 'año' => int
     * @return array Estructura con 'tipo', 'meta' y 'bloques'
     */
    public function parsear(string $rutaArchivo, array $opciones = []): array
    {
        $tipo  = $opciones['tipo']  ?? 'ficha';
        $anio  = (int)($opciones['año'] ?? date('Y'));

        $reader = IOFactory::createReaderForFile($rutaArchivo);
        $reader->setReadDataOnly(false);
        $spreadsheet = $reader->load($rutaArchivo);
        $sheet = $spreadsheet->getActiveSheet();

        if ($tipo === 'instructor') {
            return $this->parsearHorarioInstructor($sheet, $anio);
        }

        return $this->parsearHorarioFicha($sheet, $anio);
    }

    /**
     * Parsea horario de una ficha (una sola ficha por archivo).
     */
    private function parsearHorarioFicha($sheet, int $anioBase): array
    {
        $resultado = $this->estructuraBase('ficha');
        $highestRow = $sheet->getHighestRow();
        $highestCol = $sheet->getHighestColumn();
        $highestColIndex = Coordinate::columnIndexFromString($highestCol);

        // 1. Detectar código de ficha (7 dígitos)
        $fichaCodigo = $this->detectarCodigoFicha($sheet, $highestRow, $highestColIndex);
        if (!$fichaCodigo) {
            $resultado['errores'][] = 'No se detectó código de ficha de 7 dígitos en el encabezado.';
            return $resultado;
        }

        $resultado['meta']['codigo_ficha'] = $fichaCodigo;

        // 2. Procesar grilla
        $bloques = $this->extraerBloques($sheet, $highestRow, $highestColIndex, $anioBase);

        $resultado['bloques'] = $bloques;
        $resultado['meta']['total_bloques'] = count($bloques);

        return $resultado;
    }

    /**
     * Parsea horario de instructor (6 meses, múltiples fichas).
     *
     * La grilla es la misma del horario de ficha, pero cada celda trae el
     * código de la ficha que se le asigna al instructor y el encabezado
     * identifica al instructor.
     */
    private function parsearHorarioInstructor($sheet, int $anioBase): array
    {
        $resultado = $this->estructuraBase('instructor');
        $highestRow = $sheet->getHighestRow();
        $highestCol = $sheet->getHighestColumn();
        $highestColIndex = Coordinate::columnIndexFromString($highestCol);

        $resultado['meta']['instructor_nombre'] = $this->detectarNombreInstructor($sheet, $highestRow, $highestColIndex);
        $resultado['meta']['año'] = $anioBase;

        $bloques = $this->extraerBloques($sheet, $highestRow, $highestColIndex, $anioBase);

        // En el horario del instructor, el código de ficha sale del texto de la celda.
        $instructorNombre = (string) ($resultado['meta']['instructor_nombre'] ?? '');
        foreach ($bloques as $i => $bloque) {
            $texto = (string) ($bloque['texto_crudo'] ?? '');
            $codigo = null;
            if (preg_match('/\b(\d{7})\b/', $texto, $m)) {
                $codigo = (int) $m[1];
            }

            $bloques[$i]['codigo_ficha'] = $codigo;
            $bloques[$i]['instructor'] = $instructorNombre;
            // La ficha ocupa la línea del código; los descriptivos quedan del texto restante.
            $bloques[$i]['descripcion'] = trim(preg_replace('/\b\d{7}\b/', '', $texto));
        }

        $resultado['bloques'] = $bloques;
        $resultado['meta']['total_bloques'] = count($bloques);

        return $resultado;
    }

    /**
     * Detecta el nombre del instructor en las primeras filas del encabezado.
     * Se toma el primer texto alfabético suficientemente largo que no sea
     * el nombre de un mes ni una palabra de la grilla.
     */
    private function detectarNombreInstructor($sheet, int $maxRow, int $maxCol): ?string
    {
        for ($r = 1; $r <= min(8, $maxRow); $r++) {
            for ($c = 1; $c <= $maxCol; $c++) {
                $val = trim((string) $sheet->getCell([$c, $r])->getValue());
                if ($val === '' || !preg_match('/[A-Za-zÁÉÍÓÚÑáéíóúñ]/', $val)) {
                    continue;
                }

                $mayus = strtoupper($val);
                $esMes = false;
                foreach (array_keys($this->mesesMap) as $mes) {
                    if (str_contains($mayus, $mes)) {
                        $esMes = true;
                        break;
                    }
                }
                if ($esMes || in_array($mayus, $this->diasKeywords, true)) {
                    continue;
                }

                if (strlen($val) >= 6 && strlen($val) <= 80) {
                    return $val;
                }
            }
        }

        return null;
    }

    private function estructuraBase(string $tipo): array
    {
        return [
            'tipo'    => $tipo,
            'meta'    => [
                'año'           => (int)date('Y'),
                'codigo_ficha'  => null,
                'instructor_id' => null,
            ],
            'bloques' => [],
            'errores' => [],
        ];
    }

    private function detectarCodigoFicha($sheet, int $maxRow, int $maxCol): ?int
    {
        for ($r = 1; $r <= min(12, $maxRow); $r++) {
            for ($c = 1; $c <= $maxCol; $c++) {
                $val = (string)$sheet->getCell([$c, $r])->getValue();
                if (preg_match('/\b(\d{7})\b/', $val, $matches)) {
                    return (int)$matches[1];
                }
            }
        }
        return null;
    }

    /**
     * Extrae bloques de 3 horas a partir de la grilla visual.
     * Agrupa filas consecutivas en bloques de máximo 3 horas.
     */
    private function extraerBloques($sheet, int $maxRow, int $maxCol, int $anioBase): array
    {
        $bloques = [];
        $mergedMap = $this->mapearCeldasCombinadas($sheet);

        for ($r = 1; $r <= $maxRow; $r++) {
            $isHeaderRow = false;
            for ($c = 1; $c <= $maxCol; $c++) {
                $val = strtoupper(trim((string)$sheet->getCell([$c, $r])->getValue()));
                if (in_array($val, $this->diasKeywords)) {
                    $isHeaderRow = true;
                    break;
                }
            }

            if (!$isHeaderRow) {
                continue;
            }

            $dayNumberRow = $r + 1;
            $monthRow = max(1, $r - 1);

            // === Calcular fechas por columna ===
            $colDates = $this->calcularFechasPorColumna($sheet, $maxCol, $dayNumberRow, $monthRow, $anioBase);

            // === Recorrer filas de contenido ===
            $startClassRow = $dayNumberRow + 1;
            $endClassRow = min($maxRow, $startClassRow + 28);

            $currentBlock = null;

            for ($cr = $startClassRow; $cr <= $endClassRow; $cr++) {
                foreach ($colDates as $colIdx => $fecha) {
                    $cellKey = "{$colIdx}_{$cr}";

                    $rawText = trim((string)$sheet->getCell([$colIdx, $cr])->getValue());
                    if ($rawText === '') {
                        continue;
                    }

                    // Obtener hora de la columna 2 (hora_inicio)
                    $horaInicioVal = $sheet->getCell([2, $cr])->getValue();
                    if (!is_numeric($horaInicioVal)) {
                        continue;
                    }
                    $horaInicio = sprintf('%02d:00:00', (int)$horaInicioVal);

                    // Detectar fin del bloque (3 horas o fin de celda combinada)
                    $horaFin = $this->calcularHoraFin($sheet, $cr, $horaInicioVal, $mergedMap, $cellKey);

                    // Extraer datos del instructor desde la celda combinada
                    $datos = $this->extraerDatosInstructor($rawText);

                    $bloques[] = [
                        'fecha'             => $fecha,
                        'hora_inicio'       => $horaInicio,
                        'hora_fin'          => $horaFin,
                        'instructor'        => $datos['instructor'] ?? '',
                        'tipo'              => $datos['tipo'] ?? '',
                        'especialidad'      => $datos['especialidad'] ?? '',
                        'nivel'             => $datos['nivel'] ?? '',
                        'grupo_convergente' => $datos['grupo_convergente'] ?? '',
                        'texto_crudo'       => $rawText,
                    ];
                }
            }
        }

        return $bloques;
    }

    private function calcularFechasPorColumna($sheet, int $maxCol, int $dayNumberRow, int $monthRow, int $anioBase): array
    {
        $colDates = [];
        $currentMonth = (int)date('m');

        for ($c = 1; $c <= $maxCol; $c++) {
            // Buscar nombre del mes en filas superiores
            for ($mr = max(1, $monthRow - 2); $mr <= $monthRow; $mr++) {
                $mVal = strtoupper(trim((string)$sheet->getCell([$c, $mr])->getValue()));
                foreach ($this->mesesMap as $nombre => $num) {
                    if (str_contains($mVal, $nombre)) {
                        $currentMonth = $num;
                        break 2;
                    }
                }
            }

            $numDiaVal = trim((string)$sheet->getCell([$c, $dayNumberRow])->getValue());
            if (is_numeric($numDiaVal) && (int)$numDiaVal >= 1 && (int)$numDiaVal <= 31) {
                $dia = sprintf('%02d', (int)$numDiaVal);
                $mes  = sprintf('%02d', $currentMonth);
                $colDates[$c] = sprintf('%04d-%s-%s', $anioBase, $mes, $dia);
            }
        }

        return $colDates;
    }

    private function calcularHoraFin($sheet, int $row, $horaInicioVal, array $mergedMap, string $cellKey): string
    {
        if (isset($mergedMap[$cellKey])) {
            $info = $mergedMap[$cellKey];
            $rowSpan = $info['endRow'] - $info['startRow'] + 1;
            $duracion = max(1, $rowSpan);
            return sprintf('%02d:00:00', (int)$horaInicioVal + $duracion);
        }

        // Por defecto: bloques de 3 horas
        return sprintf('%02d:00:00', (int)$horaInicioVal + 3);
    }

    private function extraerDatosInstructor(string $rawText): array
    {
        $lineas = array_values(array_filter(
            array_map('trim', preg_split('/\r\n|\r|\n/', $rawText)),
            fn($l) => $l !== ''
        ));

        if (empty($lineas)) {
            return [];
        }

        $datos = [
            'instructor'        => $lineas[0] ?? '',
            'tipo'              => $lineas[1] ?? null,
            'especialidad'      => $lineas[2] ?? null,
            'nivel'             => $lineas[3] ?? null,
            'grupo_convergente' => $lineas[4] ?? null,
        ];

        // Caso especial: dos líneas con guiones
        if (count($lineas) === 2 && str_contains($lineas[1], '-')) {
            $partes = array_map('trim', explode('-', $lineas[1]));
            $datos['tipo']        = $partes[0] ?? null;
            $datos['especialidad'] = $partes[1] ?? null;
        }

        return $datos;
    }

    private function mapearCeldasCombinadas($sheet): array
    {
        $mergedMap = [];
        $mergedCells = $sheet->getMergeCells();

        foreach ($mergedCells as $range) {
            [$topLet, $bottomRight] = explode(':', $range);
            [$sColStr, $sRow] = Coordinate::coordinateFromString($topLet);
            [$eColStr, $eRow] = Coordinate::coordinateFromString($bottomRight);

            $sCol = Coordinate::columnIndexFromString($sColStr);
            $eCol = Coordinate::columnIndexFromString($eColStr);

            for ($row = (int)$sRow; $row <= (int)$eRow; $row++) {
                for ($col = $sCol; $col <= $eCol; $col++) {
                    $mergedMap["{$col}_{$row}"] = [
                        'startCol' => $sCol,
                        'startRow' => (int)$sRow,
                        'endCol'   => $eCol,
                        'endRow'   => (int)$eRow,
                    ];
                }
            }
        }

        return $mergedMap;
    }
}
