<?php

/**
 * HorarioSincronizador.php
 *
 * Algoritmo de sincronización diferencial de horarios (RF-HOR-09 a 14).
 * Responsable de crear, actualizar, omitir y cancelar sesiones de forma inteligente.
 */

class HorarioSincronizador
{
    private PDO $db;
    private Sesion $sesionModel;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->sesionModel = new Sesion($db);
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
                // Crear nueva sesión
                $insertado = $this->sesionModel->create([
                    'Ficha_id'          => $fichaId,
                    'Instructor_id'     => null, // TODO: resolver ID del instructor
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

        // 4. Cancelar sesiones que ya no vienen en el Excel (lógica futura)
        // $reporte['canceladas'] = $this->cancelarSesionesAusentes(...);

        return $reporte;
    }

    private function obtenerSesionesExistentes(int $fichaId, string $desde, string $hasta): array
    {
        // Implementación simplificada - idealmente debería estar en el modelo
        $sql = "SELECT * FROM sesion 
                WHERE Ficha_id = :ficha_id 
                  AND fecha BETWEEN :desde AND :hasta
                  AND estado != 'Cancelado'";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':ficha_id' => $fichaId,
            ':desde'    => $desde,
            ':hasta'    => $hasta,
        ]);

        $sesiones = [];
        foreach ($stmt->fetchAll() as $s) {
            $key = $s['fecha'] . '|' . $s['hora_inicio'];
            $sesiones[$key] = $s;
        }

        return $sesiones;
    }

    private function estaProtegida(array $sesion): bool
    {
        // TODO: Verificar si tiene asistencias o inasistencias
        return false;
    }

    private function calcularDiferencias(array $sesionBD, array $bloqueExcel): array
    {
        $diferencias = [];

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
}
