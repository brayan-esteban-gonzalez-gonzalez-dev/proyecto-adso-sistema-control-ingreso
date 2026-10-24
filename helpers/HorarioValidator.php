<?php

/**
 * HorarioValidator.php
 *
 * Motor de validación coherente multierror para horarios.
 * Recolecta TODOS los errores antes de rechazar el archivo.
 */

class HorarioValidator
{
    private array $errores = [];

    /**
     * Valida un conjunto de bloques de horario.
     *
     * @param array $bloques  Bloques normalizados del parser
     * @param array $contexto Contexto adicional (ficha_id, instructor_id, etc.)
     * @return array ['valido' => bool, 'errores' => array]
     */
    public function validar(array $bloques, array $contexto = []): array
    {
        $this->errores = [];

        if (empty($bloques)) {
            $this->agregarError('No se encontraron bloques de horario en el archivo.');
            return $this->resultado();
        }

        $this->validarInstructoresExisten($bloques);
        $this->validarCrucesInstructor($bloques);
        $this->validarCrucesFicha($bloques);
        $this->validarUnSoloInstructorPorBloque($bloques);
        $this->validarHorasValidas($bloques);

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
     * RF-HOR-16: Verifica que los instructores existan (versión simplificada)
     */
    private function validarInstructoresExisten(array $bloques): void
    {
        foreach ($bloques as $i => $bloque) {
            $nombre = trim($bloque['instructor'] ?? '');

            if ($nombre === '') {
                $this->agregarError("El bloque #{$i} no tiene nombre de instructor.");
                continue;
            }

            // TODO: Integrar con UsuarioModel para búsqueda normalizada real
            // Por ahora solo validamos que tenga nombre
            if (strlen($nombre) < 3) {
                $this->agregarError("Nombre de instructor demasiado corto: \"{$nombre}\".");
            }
        }
    }

    /**
     * RF-HOR-17: Un instructor no puede estar en dos fichas a la misma hora
     */
    private function validarCrucesInstructor(array $bloques): void
    {
        $horariosInstructor = [];

        foreach ($bloques as $bloque) {
            $instructor = $bloque['instructor'] ?? '';
            $fecha      = $bloque['fecha'] ?? '';
            $horaInicio = $bloque['hora_inicio'] ?? '';

            if (!$instructor || !$fecha || !$horaInicio) {
                continue;
            }

            $key = "{$instructor}|{$fecha}|{$horaInicio}";

            if (isset($horariosInstructor[$key])) {
                $this->agregarError(
                    "El instructor {$instructor} ya tiene clase el {$fecha} a las {$horaInicio} en otra ficha."
                );
            }

            $horariosInstructor[$key] = true;
        }
    }

    /**
     * RF-HOR-18: Una ficha no puede tener dos bloques a la misma hora
     */
    private function validarCrucesFicha(array $bloques): void
    {
        $horariosFicha = [];

        foreach ($bloques as $bloque) {
            $fecha      = $bloque['fecha'] ?? '';
            $horaInicio = $bloque['hora_inicio'] ?? '';

            if (!$fecha || !$horaInicio) {
                continue;
            }

            $key = "{$fecha}|{$horaInicio}";

            if (isset($horariosFicha[$key])) {
                $this->agregarError(
                    "La ficha tiene dos bloques distintos el {$fecha} a las {$horaInicio}."
                );
            }

            $horariosFicha[$key] = true;
        }
    }

    /**
     * RF-HOR-20: Cada bloque debe tener un solo instructor
     */
    private function validarUnSoloInstructorPorBloque(array $bloques): void
    {
        // Por ahora esta validación es básica.
        // En el futuro se puede mejorar cuando se parseen múltiples instructores por celda.
        foreach ($bloques as $i => $bloque) {
            if (empty($bloque['instructor'])) {
                $this->agregarError("El bloque #{$i} no tiene instructor asignado.");
            }
        }
    }

    /**
     * RF-HOR-21: Validar que las horas sean coherentes
     */
    private function validarHorasValidas(array $bloques): void
    {
        foreach ($bloques as $i => $bloque) {
            $inicio = $bloque['hora_inicio'] ?? '';
            $fin    = $bloque['hora_fin'] ?? '';

            if ($inicio && $fin && $inicio >= $fin) {
                $this->agregarError(
                    "El bloque #{$i} tiene hora de inicio ({$inicio}) mayor o igual a la hora de fin ({$fin})."
                );
            }
        }
    }
}
