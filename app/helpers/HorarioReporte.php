<?php

/**
 * HorarioReporte.php
 *
 * Genera reportes consolidados de importación de horarios (RF-HOR-07 y RF-HOR-08).
 */

class HorarioReporte
{
    /**
     * Consolida el reporte final de una importación.
     */
    public function generarReporte(array $parseResult, array $validacion, array $sincronizacion): array
    {
        return [
            'tipo'              => $parseResult['tipo'] ?? 'ficha',
            'meta'              => $parseResult['meta'] ?? [],
            'total_bloques'     => count($parseResult['bloques'] ?? []),
            'validacion'        => [
                'valido'  => $validacion['valido'] ?? false,
                'errores' => $validacion['errores'] ?? [],
            ],
            'sincronizacion'    => [
                'creadas'      => $sincronizacion['creadas'] ?? 0,
                'actualizadas' => $sincronizacion['actualizadas'] ?? 0,
                'omitidas'     => $sincronizacion['omitidas'] ?? 0,
                'canceladas'   => $sincronizacion['canceladas'] ?? 0,
                'protegidas'   => $sincronizacion['protegidas'] ?? 0,
                'errores'      => $sincronizacion['errores'] ?? [],
            ],
            'resumen'           => $this->generarResumen($sincronizacion),
            'fecha_importacion' => date('Y-m-d H:i:s'),
        ];
    }

    private function generarResumen(array $sincronizacion): string
    {
        $creadas      = $sincronizacion['creadas'] ?? 0;
        $actualizadas = $sincronizacion['actualizadas'] ?? 0;
        $omitidas     = $sincronizacion['omitidas'] ?? 0;
        $protegidas   = $sincronizacion['protegidas'] ?? 0;

        return "Se procesaron $creadas sesiones nuevas, $actualizadas actualizadas, $omitidas omitidas y $protegidas protegidas.";
    }
}
