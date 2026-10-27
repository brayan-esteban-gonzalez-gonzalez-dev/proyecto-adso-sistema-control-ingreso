<?php

/**
 * HorarioSincronizador.php
 *
 * Algoritmo de sincronización diferencial de horarios (RF-HOR-09 a 14).
 * Responsable de crear, actualizar, omitir y cancelar sesiones de forma inteligente.
 */

require_once ROOT_PATH . '/models/AsignacionInstructor.php';

class HorarioSincronizador
{
    private PDO $db;
    private Sesion $sesionModel;
    private ?Usuario $usuarioModel;
    private ?AsignacionInstructor $asignacionModel;

    public function __construct(PDO $db, ?Usuario $usuarioModel = null, ?AsignacionInstructor $asignacionModel = null)
    {
        $this->db = $db;
        $this->sesionModel = new Sesion($db);
        $this->usuarioModel = $usuarioModel;
        $this->asignacionModel = $asignacionModel;
    }

    /**
     * Sincroniza los bloques validados con la base de datos.
     *
     * @param array $bloques  Bloques ya validados
     * @param int   $fichaId
     * @return array Reporte de operación
     */
    public function sincronizar(array $bloques, int $fichaId): array
    {
        $reporte = [
            'creadas'      => 0,
            'actualizadas' => 0,
            'omitidas'     => 0,
            'canceladas'   => 0,
            'protegidas'   => 0,
            'errores'      => [],
        ];

        if (empty($bloques)) {
            return $reporte;
        }

        // 1. Obtener sesiones existentes de la ficha en el rango del Excel
        $fechas = array_column($bloques, 'fecha');
        $minFecha = min($fechas);
        $maxFecha = max($fechas);

        $sesionesExistentes = $this->obtenerSesionesExistentes($fichaId, $minFecha, $maxFecha);

        // 2. Indexar bloques del Excel por clave única (fecha + hora_inicio)
        $bloquesExcel = [];
        foreach ($bloques as $bloque) {
            $key = $bloque['fecha'] . '|' . $bloque['hora_inicio'];
            $bloquesExcel[$key] = $bloque;
        }

        // 3. Procesar cada bloque del Excel
        foreach ($bloquesExcel as $key => $bloqueExcel) {
            if (isset($sesionesExistentes[$key])) {
                $sesionBD = $sesionesExistentes[$key];

                // RF-HOR-14: si el bloque estaba cancelado y reaparece, vuelve a Activo.
                if (($sesionBD['estado'] ?? '') === 'Cancelado') {
                    $reactivado = $this->sesionModel->update((int) $sesionBD['id'], ['estado' => 'Activo']);
                    if ($reactivado) {
                        $reporte['actualizadas']++;
                    } else {
                        $reporte['errores'][] = "Error al reactivar la sesión {$key}";
                    }
                    continue;
                }

                // Verificar si está protegida
                if ($this->estaProtegida($sesionBD)) {
                    $reporte['protegidas']++;
                    continue;
                }

                // Comparar y actualizar si es necesario
                $camposDiferentes = $this->calcularDiferencias($sesionBD, $bloqueExcel);

                if (empty($camposDiferentes)) {
                    $reporte['omitidas']++;
                } else {
                    $actualizado = $this->sesionModel->update((int)$sesionBD['id'], $camposDiferentes);
                    if ($actualizado) {
                        $reporte['actualizadas']++;
                    } else {
                        $reporte['errores'][] = "Error al actualizar sesión {$key}";
                    }
                }
            } else {
                // Crear nueva sesión: resolver instructor por nombre normalizado
                $instructorId = $this->resolverInstructorId($bloqueExcel['instructor'] ?? '');
                if ($instructorId === null) {
                    $reporte['errores'][] = 'No se pudo resolver el instructor "' . ($bloqueExcel['instructor'] ?? '') . '" para la sesión ' . $key;
                    continue;
                }

                $insertado = $this->sesionModel->create([
                    'Ficha_id'          => $fichaId,
                    'Competencia_id'    => null,
                    'Instructor_id'     => $instructorId,
                    'fecha'             => $bloqueExcel['fecha'],
                    'hora_inicio'       => $bloqueExcel['hora_inicio'],
                    'hora_fin'          => $bloqueExcel['hora_fin'],
                    'estado'            => 'Activo',
                    'tipo_instructor'   => $bloqueExcel['tipo'] ?? null,
                    'especialidad'      => $bloqueExcel['especialidad'] ?? null,
                    'nivel'             => $bloqueExcel['nivel'] ?? null,
                    'grupo_convergente' => $bloqueExcel['grupo_convergente'] ?? null,
                ]);

                if ($insertado) {
                    $reporte['creadas']++;
                } else {
                    $reporte['errores'][] = "Error al crear sesión {$key}";
                }
            }
        }

        // 4. Cancelar sesiones de la ficha en el rango que ya no vienen (RF-HOR-12)
        $reporte['canceladas'] = $this->cancelarSesionesAusentes(
            $fichaId,
            $minFecha,
            $maxFecha,
            $bloquesExcel,
            $sesionesExistentes
        );

        return $reporte;
    }

    /**
     * RF-HOR-12 / RF-HOR-13: cancela lógicamente las sesiones guardadas de la
     * ficha que no aparecen en el Excel, salvo las protegidas.
     */
    private function cancelarSesionesAusentes(
        int $fichaId,
        string $desde,
        string $hasta,
        array $bloquesExcel,
        array $sesionesExistentes
    ): int {
        $canceladas = 0;

        foreach ($sesionesExistentes as $key => $sesionBD) {
            if (isset($bloquesExcel[$key])) {
                continue;
            }

            if (in_array(($sesionBD['estado'] ?? ''), ['Cancelado', 'Finalizada'], true)) {
                continue;
            }

            if ($this->estaProtegida($sesionBD)) {
                continue;
            }

            $sql = "UPDATE sesion SET estado = 'Cancelado' WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            if ($stmt->execute([':id' => (int) $sesionBD['id']])) {
                $canceladas++;
            }
        }

        return $canceladas;
    }

    /**
     * RF-HOR-40: sincroniza el horario del instructor con `asignacion_instructor`.
     * Mismas reglas: lo igual no se toca, lo distinto se actualiza y lo que ya
     * no viene se cancela sin borrarse.
     *
     * @param array $bloques   Bloques del horario del instructor (con codigo_ficha)
     * @param int   $instructorId
     * @param array $mapaFichas ['codigo' => id]
     */
    public function sincronizarAsignaciones(array $bloques, int $instructorId, array $mapaFichas): array
    {
        $reporte = [
            'creadas'      => 0,
            'actualizadas' => 0,
            'omitidas'     => 0,
            'canceladas'   => 0,
            'protegidas'   => 0,
            'errores'      => [],
        ];

        if (empty($bloques)) {
            return $reporte;
        }

        $fechas = array_column($bloques, 'fecha');
        $minFecha = min($fechas);
        $maxFecha = max($fechas);

        $sql = "SELECT * FROM asignacion_instructor
                WHERE instructor_id = :instructor_id
                  AND fecha BETWEEN :desde AND :hasta";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':instructor_id' => $instructorId,
            ':desde' => $minFecha,
            ':hasta' => $maxFecha,
        ]);

        $existentes = [];
        foreach ($stmt->fetchAll() as $a) {
            $key = $a['fecha'] . '|' . $a['hora_inicio'] . '|' . $a['ficha_id'];
            if (isset($existentes[$key]) && ($existentes[$key]['estado'] ?? '') !== 'Cancelado') {
                continue;
            }
            $existentes[$key] = $a;
        }

        $clavesExcel = [];
        foreach ($bloques as $bloque) {
            $codigo = (string) ($bloque['codigo_ficha'] ?? '');
            $fichaId = $mapaFichas[$codigo] ?? null;
            if ($fichaId === null) {
                $reporte['errores'][] = "La ficha {$codigo} no está registrada.";
                continue;
            }

            $key = $bloque['fecha'] . '|' . $bloque['hora_inicio'] . '|' . $fichaId;
            $clavesExcel[$key] = true;

            $datos = [
                'instructor_id' => $instructorId,
                'ficha_id'      => $fichaId,
                'fecha'         => $bloque['fecha'],
                'hora_inicio'   => $bloque['hora_inicio'],
                'hora_fin'      => $bloque['hora_fin'],
                'estado'        => 'Activo',
            ];

            if (!isset($existentes[$key])) {
                $ok = $this->asignacionModel->create($datos);
                $ok ? $reporte['creadas']++ : $reporte['errores'][] = "Error al crear asignación {$key}";
                continue;
            }

            $actual = $existentes[$key];
            if (($actual['estado'] ?? '') === 'Cancelado') {
                $ok = $this->asignacionModel->update((int) $actual['id'], $datos);
                $ok ? $reporte['actualizadas']++ : $reporte['errores'][] = "Error al reactivar asignación {$key}";
                continue;
            }

            if (($actual['hora_fin'] ?? '') === $datos['hora_fin']) {
                $reporte['omitidas']++;
                continue;
            }

            $ok = $this->asignacionModel->update((int) $actual['id'], $datos);
            $ok ? $reporte['actualizadas']++ : $reporte['errores'][] = "Error al actualizar asignación {$key}";
        }

        foreach ($existentes as $key => $actual) {
            if (isset($clavesExcel[$key]) || ($actual['estado'] ?? '') === 'Cancelado') {
                continue;
            }
            $this->asignacionModel->cancelar((int) $actual['id']);
            $reporte['canceladas']++;
        }

        return $reporte;
    }

    private function obtenerSesionesExistentes(int $fichaId, string $desde, string $hasta): array
    {
        // Implementación simplificada - idealmente debería estar en el modelo
        $sql = "SELECT * FROM sesion 
                WHERE Ficha_id = :ficha_id 
                  AND fecha BETWEEN :desde AND :hasta";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':ficha_id' => $fichaId,
            ':desde'    => $desde,
            ':hasta'    => $hasta,
        ]);

        $sesiones = [];
        foreach ($stmt->fetchAll() as $s) {
            $key = $s['fecha'] . '|' . $s['hora_inicio'];
            // Si ya hay una sesión activa para esa clave, no la sobreescribe
            // una cancelada (evita reactivar/omitir la sesión equivocada).
            if (isset($sesiones[$key]) && ($sesiones[$key]['estado'] ?? '') !== 'Cancelado') {
                continue;
            }
            $sesiones[$key] = $s;
        }

        return $sesiones;
    }

    private function estaProtegida(array $sesion): bool
    {
        $id = (int) ($sesion['id'] ?? 0);
        if ($id <= 0) {
            return false;
        }

        // RF-HOR-13: sesión con asistencia o inasistencia registrada.
        $sql = "SELECT
                   (SELECT COUNT(*) FROM asistencia WHERE Sesion_id = :id_asistencia) AS asistencias,
                   (SELECT COUNT(*) FROM inasistencia WHERE Sesion_id = :id_inasistencia) AS inasistencias";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':id_asistencia'   => $id,
            ':id_inasistencia' => $id,
        ]);
        $row = $stmt->fetch();

        if ((int) ($row['asistencias'] ?? 0) > 0 || (int) ($row['inasistencias'] ?? 0) > 0) {
            return true;
        }

        // RF-HOR-13: sesión abierta ahora por el instructor (sin cierre real).
        if (!empty($sesion['apertura_real']) && empty($sesion['cierre_real'])) {
            return true;
        }

        return false;
    }

    private function calcularDiferencias(array $sesionBD, array $bloqueExcel): array
    {
        $diferencias = [];

        $instructorId = $this->resolverInstructorId($bloqueExcel['instructor'] ?? '');
        if ($instructorId !== null && (int) ($sesionBD['Instructor_id'] ?? 0) !== $instructorId) {
            $diferencias['Instructor_id'] = $instructorId;
        }

        $campos = ['hora_fin', 'tipo_instructor', 'especialidad', 'nivel', 'grupo_convergente'];

        foreach ($campos as $campo) {
            $valorBD = $sesionBD[$campo] ?? null;
            $valorExcel = $bloqueExcel[$campo] ?? null;

            if ($valorBD != $valorExcel) {
                $diferencias[$campo] = $valorExcel;
            }
        }

        return $diferencias;
    }

    /**
     * Resuelve el id del instructor a partir del nombre del Excel.
     */
    private function resolverInstructorId(string $nombre): ?int
    {
        if ($this->usuarioModel === null) {
            return null;
        }

        $instructor = $this->usuarioModel->getInstructorPorNombre($nombre);
        if (!$instructor) {
            return null;
        }

        return (int) $instructor['id'];
    }
}
