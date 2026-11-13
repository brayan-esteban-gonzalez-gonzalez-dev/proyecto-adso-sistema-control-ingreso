<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * HorarioReporte::generarReporte() promete un arreglo con el tipo de
 * importación, el conteo de bloques, la validación, la sincronización,
 * un resumen legible y la fecha. No persiste nada.
 */
class HorarioReporteTest extends TestCase
{
    private HorarioReporte $reporte;

    protected function setUp(): void
    {
        $this->reporte = new HorarioReporte();
    }

    public function testGenerarReporteConsolidaLoQueRecibe(): void
    {
        $resultado = $this->reporte->generarReporte(
            [
                'tipo'    => 'instructor',
                'meta'    => ['ficha' => '3064749'],
                'bloques' => [[], [], []],
            ],
            [
                'valido'  => true,
                'errores' => ['Choque de horario'],
            ],
            [
                'creadas'      => 2,
                'actualizadas' => 3,
                'omitidas'     => 4,
                'canceladas'   => 9,
                'protegidas'   => 5,
                'errores'      => ['Sesión cerrada'],
            ]
        );

        $this->assertSame(
            ['tipo', 'meta', 'total_bloques', 'validacion', 'sincronizacion', 'resumen', 'fecha_importacion'],
            array_keys($resultado)
        );
        $this->assertSame('instructor', $resultado['tipo']);
        $this->assertSame(['ficha' => '3064749'], $resultado['meta']);
        $this->assertSame(3, $resultado['total_bloques']);
        $this->assertTrue($resultado['validacion']['valido']);
        $this->assertSame(['Choque de horario'], $resultado['validacion']['errores']);
        $this->assertSame(2, $resultado['sincronizacion']['creadas']);
        $this->assertSame(3, $resultado['sincronizacion']['actualizadas']);
        $this->assertSame(4, $resultado['sincronizacion']['omitidas']);
        $this->assertSame(9, $resultado['sincronizacion']['canceladas']);
        $this->assertSame(5, $resultado['sincronizacion']['protegidas']);
        $this->assertSame(['Sesión cerrada'], $resultado['sincronizacion']['errores']);
        $this->assertSame(
            'Se procesaron 2 sesiones nuevas, 3 actualizadas, 4 omitidas y 5 protegidas.',
            $resultado['resumen']
        );
        $this->assertDoesNotMatchRegularExpression('/\b9\b/', $resultado['resumen']);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
            $resultado['fecha_importacion']
        );
    }

    public function testGenerarReporteConArreglosVaciosUsaLosValoresPorDefecto(): void
    {
        $resultado = $this->reporte->generarReporte([], [], []);

        $this->assertSame('ficha', $resultado['tipo']);
        $this->assertSame([], $resultado['meta']);
        $this->assertSame(0, $resultado['total_bloques']);
        $this->assertFalse($resultado['validacion']['valido']);
        $this->assertSame([], $resultado['validacion']['errores']);
        $this->assertSame(0, $resultado['sincronizacion']['creadas']);
        $this->assertSame(0, $resultado['sincronizacion']['actualizadas']);
        $this->assertSame(0, $resultado['sincronizacion']['omitidas']);
        $this->assertSame(0, $resultado['sincronizacion']['canceladas']);
        $this->assertSame(0, $resultado['sincronizacion']['protegidas']);
        $this->assertSame([], $resultado['sincronizacion']['errores']);
        $this->assertSame(
            'Se procesaron 0 sesiones nuevas, 0 actualizadas, 0 omitidas y 0 protegidas.',
            $resultado['resumen']
        );
    }

    public function testGenerarReporteTrataLosNulosComoAusentes(): void
    {
        $resultado = $this->reporte->generarReporte(
            ['tipo' => null, 'meta' => null, 'bloques' => null],
            ['valido' => null, 'errores' => null],
            [
                'creadas'      => null,
                'actualizadas' => null,
                'omitidas'     => null,
                'canceladas'   => null,
                'protegidas'   => null,
                'errores'      => null,
            ]
        );

        $this->assertSame('ficha', $resultado['tipo']);
        $this->assertSame([], $resultado['meta']);
        $this->assertSame(0, $resultado['total_bloques']);
        $this->assertFalse($resultado['validacion']['valido']);
        $this->assertSame([], $resultado['validacion']['errores']);
        $this->assertSame(0, $resultado['sincronizacion']['creadas']);
        $this->assertSame([], $resultado['sincronizacion']['errores']);
        $this->assertSame(
            'Se procesaron 0 sesiones nuevas, 0 actualizadas, 0 omitidas y 0 protegidas.',
            $resultado['resumen']
        );
    }

    public function testGenerarReporteNoConvierteUnConteoQueNoEsNumero(): void
    {
        $resultado = $this->reporte->generarReporte(
            ['bloques' => []],
            [],
            ['creadas' => 'abc', 'actualizadas' => -1]
        );

        $this->assertSame('abc', $resultado['sincronizacion']['creadas']);
        $this->assertSame(-1, $resultado['sincronizacion']['actualizadas']);
        $this->assertSame(
            'Se procesaron abc sesiones nuevas, -1 actualizadas, 0 omitidas y 0 protegidas.',
            $resultado['resumen']
        );
    }

    public function testGenerarReporteRechazaBloquesQueNoSonArreglo(): void
    {
        $this->expectException(TypeError::class);

        $this->reporte->generarReporte(['bloques' => 'no-es-lista'], [], []);
    }
}
