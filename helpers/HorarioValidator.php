<?php

/**
 * HorarioValidator.php
 *
 * Motor de validación coherente multierror para horarios (RF-HOR-16 a RF-HOR-21
 * y RF-HOR-38). Recolecta TODOS los errores antes de rechazar el archivo.
 *
 * Uso:
 *   $validator = new HorarioValidator($usuarioModel, $asignacionModel);
 *   $resultado = $validator->validar($bloques, ['ficha_id' => $fichaId]);
 */

class HorarioValidator
{
    private array $errores = [];
    private ?Usuario $usuarioModel;
    private ?AsignacionInstructor $asignacionModel;

    public function __construct(?Usuario $usuarioModel = null, ?AsignacionInstructor $asignacionModel = null)
    {
        $this->usuarioModel = $usuarioModel;
        $this->asignacionModel = $asignacionModel;
    }

    /**
     * Valida un conjunto de bloques de horario.
     *
     * @param array $bloques  Bloques normalizados del parser
     * @param array $contexto Contexto adicional:
     *                        'ficha_id'      => int  (obligatorio para RF-HOR-38)
     *                        'codigo_ficha'  => string
     *                        'validar_asignaciones' => bool (por defecto true si hay modelo)
     * @return array ['valido' => bool, 'errores' => array]
     */
    public function validar(array $bloques, array $contexto = []): array
    {
        $this->errores = [];

        if (empty($bloques)) {
            $this->agregarError('No se encontraron bloques de horario en el archivo.');
            return $this->resultado();
        }

        $this->validarEstructura($bloques);
        $this->validarInstructoresExisten($bloques);
        $this->validarCrucesInstructor($bloques);
        $this->validarCrucesFicha($bloques);
        $this->validarUnSoloInstructorPorBloque($bloques);
        $this->validarHorasValidas($bloques);

        $validarAsignaciones = $contexto['validar_asignaciones'] ?? ($this->asignacionModel !== null);
        $fichaId = (int) ($contexto['ficha_id'] ?? 0);
        if ($validarAsignaciones && $fichaId > 0) {
            $this->validarAsignacionesInstructor($bloques, $fichaId);
        }

        return $this->resultado();
    }

    private function resultado(): array
    {
        return [
            'valido'  => empty($this->errores),
            'errores' => $this->errores,
        ];
    }

    private function agregarError(string $mensaje): void
    {
        $this->errores[] = $mensaje;
    }

    /**
     * RF-HOR-21: cada bloque debe traer fecha, hora de inicio y de fin legibles.
     */
    private function validarEstructura(array $bloques): void
    {
        foreach ($bloques as $i => $bloque) {
            $n = $i + 1;
            $fecha = trim((string) ($bloque['fecha'] ?? ''));
            $inicio = trim((string) ($bloque['hora_inicio'] ?? ''));
            $fin = trim((string) ($bloque['hora_fin'] ?? ''));

            if ($fecha === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
                $this->agregarError("No se pudo leer la fecha del bloque en la fila {$n}.");
                continue;
            }
            if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $inicio) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $fin)) {
                $this->agregarError("No se pudo leer la hora del bloque en la fila {$n}.");
            }
        }
    }

    /**
     * RF-HOR-16: el instructor debe existir en Usuario con rol Instructor.
     * Si no se inyectó el modelo, se hace una validación mínima de forma.
     */
    private function validarInstructoresExisten(array $bloques): void
    {
        foreach ($bloques as $bloque) {
            $nombre = trim((string) ($bloque['instructor'] ?? ''));
            $fecha = (string) ($bloque['fecha'] ?? '');
            $hora = (string) ($bloque['hora_inicio'] ?? '');

            if ($nombre === '') {
                $this->agregarError("El instructor del bloque del {$fecha} ({$hora}) no está registrado ({$fecha}, {$hora}). Regístrelo o corrija el nombre y suba el archivo de nuevo.");
                continue;
            }

            if ($this->usuarioModel === null) {
                continue;
            }

            if ($this->usuarioModel->getInstructorPorNombre($nombre) === null) {
                $this->agregarError("El instructor {$nombre} no está registrado ({$fecha}, {$hora}). Regístrelo o corrija el nombre y suba el archivo de nuevo.");
            }
        }
    }

    /**
     * RF-HOR-17: un instructor no puede tener dos bloques a la misma hora en fichas distintas.
     */
    private function validarCrucesInstructor(array $bloques): void
    {
        $ocupacion = [];

        foreach ($bloques as $bloque) {
            $instructor = trim((string) ($bloque['instructor'] ?? ''));
            $fecha = (string) ($bloque['fecha'] ?? '');
            $inicio = (string) ($bloque['hora_inicio'] ?? '');
            $fin = (string) ($bloque['hora_fin'] ?? '');
            $codigoFicha = (string) ($bloque['codigo_ficha'] ?? '');

            if ($instructor === '' || $fecha === '' || $inicio === '') {
                continue;
            }

            $key = $instructor . '|' . $fecha;
            if (!isset($ocupacion[$key])) {
                $ocupacion[$key] = [];
            }

            foreach ($ocupacion[$key] as $previo) {
                if ($this->seSolapan($inicio, $fin, $previo['inicio'], $previo['fin'])) {
                    $codigo = $previo['codigo_ficha'] ?: $codigoFicha;
                    $this->agregarError(
                        "El instructor {$instructor} ya tiene clase el {$fecha} de {$previo['inicio']} a {$previo['fin']} en la ficha {$codigo}. No puede estar en dos fichas a la misma hora."
                    );
                }
            }

            $ocupacion[$key][] = [
                'inicio' => $inicio,
                'fin' => $fin,
                'codigo_ficha' => $codigoFicha,
            ];
        }
    }

    /**
     * RF-HOR-18: una ficha no puede tener dos bloques distintos a la misma hora.
     */
    private function validarCrucesFicha(array $bloques): void
    {
        $ocupacion = [];

        foreach ($bloques as $bloque) {
            $fecha = (string) ($bloque['fecha'] ?? '');
            $inicio = (string) ($bloque['hora_inicio'] ?? '');
            $fin = (string) ($bloque['hora_fin'] ?? '');
            $codigoFicha = (string) ($bloque['codigo_ficha'] ?? '');

            if ($fecha === '' || $inicio === '') {
                continue;
            }

            if (!isset($ocupacion[$fecha])) {
                $ocupacion[$fecha] = [];
            }

            foreach ($ocupacion[$fecha] as $previo) {
                if ($this->seSolapan($inicio, $fin, $previo['inicio'], $previo['fin'])) {
                    $this->agregarError(
                        "La ficha {$codigoFicha} tiene dos bloques distintos el {$fecha} de {$inicio} a {$fin}."
                    );
                }
            }

            $ocupacion[$fecha][] = ['inicio' => $inicio, 'fin' => $fin];
        }
    }

    /**
     * RF-HOR-20: cada bloque de 3 horas debe tener un solo instructor.
     * El parser entrega un nombre por bloque; si llegan varios separados por
     * guion o barra se reporta.
     */
    private function validarUnSoloInstructorPorBloque(array $bloques): void
    {
        foreach ($bloques as $bloque) {
            $instructor = trim((string) ($bloque['instructor'] ?? ''));
            $fecha = (string) ($bloque['fecha'] ?? '');
            $inicio = (string) ($bloque['hora_inicio'] ?? '');
            $fin = (string) ($bloque['hora_fin'] ?? '');

            if ($instructor === '') {
                $this->agregarError("El bloque del {$fecha} de {$inicio} a {$fin} no tiene instructor asignado.");
                continue;
            }

            $partes = preg_split('/\s*[\/|-]\s*/', $instructor);
            $partes = array_values(array_filter(array_map('trim', $partes), fn($p) => $p !== ''));
            if (count($partes) > 1) {
                $this->agregarError(
                    "El bloque del {$fecha} de {$inicio} a {$fin} tiene más de un instructor (" . implode(', ', $partes) . "). Debe tener uno solo."
                );
            }
        }
    }

    /**
     * RF-HOR-21: la hora de inicio debe ser menor que la de fin.
     */
    private function validarHorasValidas(array $bloques): void
    {
        foreach ($bloques as $i => $bloque) {
            $inicio = (string) ($bloque['hora_inicio'] ?? '');
            $fin = (string) ($bloque['hora_fin'] ?? '');

            if ($inicio !== '' && $fin !== '' && $inicio >= $fin) {
                $this->agregarError(
                    'El bloque #' . ($i + 1) . " tiene hora de inicio ({$inicio}) mayor o igual a la hora de fin ({$fin})."
                );
            }
        }
    }

    /**
     * RF-HOR-38: el instructor de cada bloque debe tener la ficha, el día y el
     * bloque en su horario de 6 meses (tabla asignacion_instructor).
     */
    private function validarAsignacionesInstructor(array $bloques, int $fichaId): void
    {
        if ($this->asignacionModel === null) {
            return;
        }

        foreach ($bloques as $bloque) {
            $nombre = trim((string) ($bloque['instructor'] ?? ''));
            $fecha = (string) ($bloque['fecha'] ?? '');
            $inicio = (string) ($bloque['hora_inicio'] ?? '');
            $fin = (string) ($bloque['hora_fin'] ?? '');

            if ($nombre === '' || $fecha === '' || $inicio === '' || $fin === '') {
                continue;
            }

            $instructor = $this->usuarioModel?->getInstructorPorNombre($nombre);
            if ($instructor === null) {
                // Ya se reportó en RF-HOR-16.
                continue;
            }

            $asignacion = $this->asignacionModel->existeAsignacion(
                (int) $instructor['id'],
                $fichaId,
                $fecha,
                $inicio,
                $fin
            );

            if ($asignacion === null) {
                $codigo = (string) ($bloque['codigo_ficha'] ?? $fichaId);
                $this->agregarError(
                    "El instructor {$nombre} no tiene asignada la ficha {$codigo} el {$fecha} de {$inicio} a {$fin}. Pida al administrador que actualice su horario o corrija el Excel."
                );
            }
        }
    }

    private function seSolapan(string $inicioA, string $finA, string $inicioB, string $finB): bool
    {
        if ($finA === '') {
            $finA = $inicioA;
        }
        if ($finB === '') {
            $finB = $inicioB;
        }
        return $inicioA < $finB && $inicioB < $finA;
    }
}
