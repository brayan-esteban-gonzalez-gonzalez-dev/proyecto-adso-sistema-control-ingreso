<?php
declare(strict_types=1);

namespace Tests\Integration;

require_once __DIR__ . '/IntegrationTestCase.php';

use Reporte;
use Usuario;

/**
 * Pruebas de Integración para el modelo Reporte (models/Reporte.php)
 *
 * Valida la agregación analítica y generación de reportes institucionales:
 * - Reporte consolidado de asistencia por Ficha
 * - Detalle individual cronológico por Aprendiz
 * - Indicadores analíticos diferenciados por Rol (Global para Admin/Instructor vs Individual para Aprendiz)
 * - Métricas integrales de desempeño y puntualidad para el Portal del Aprendiz
 */
class ReporteIntegrationTest extends IntegrationTestCase
{
    private Reporte $reporteModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reporteModel = new Reporte($this->db);
    }

    public function testAsistenciaPorFichaConsolidaMetricasCorrectamente(): void
    {
        $ficha = $this->crearFicha();
        $instructor = $this->crearUsuario(Usuario::ROL_INSTRUCTOR);

        $aprendiz1 = $this->crearUsuario(Usuario::ROL_APRENDIZ, ['Ficha_id' => $ficha]);
        $aprendiz2 = $this->crearUsuario(Usuario::ROL_APRENDIZ, ['Ficha_id' => $ficha]);

        $fecha = date('Y-m-d');
        $sesion1 = $this->crearSesion($ficha, $instructor, ['fecha' => $fecha, 'estado' => 'Finalizada']);

        // Aprendiz 1 asistió a tiempo
        $this->crearAsistencia($aprendiz1, $sesion1, ['fecha' => $fecha, 'minutos_retardo' => 0]);

        // Aprendiz 2 tiene inasistencia
        $stmtI = $this->db->prepare("INSERT INTO inasistencia (Usuario_id, Ficha_id, Sesion_id, fecha, generado_por)
                                    VALUES (:uid, :fid, :sid, :fecha, 'sistema')");
        $stmtI->execute([
            ':uid'   => $aprendiz2,
            ':fid'   => $ficha,
            ':sid'   => $sesion1,
            ':fecha' => $fecha,
        ]);

        $reporte = $this->reporteModel->asistenciaPorFicha($ficha, $fecha, $fecha);

        $this->assertIsArray($reporte);
        $this->assertCount(2, $reporte);

        $datosA1 = null;
        $datosA2 = null;
        foreach ($reporte as $row) {
            if ((int)$row['id'] === $aprendiz1) {
                $datosA1 = $row;
            }
            if ((int)$row['id'] === $aprendiz2) {
                $datosA2 = $row;
            }
        }

        $this->assertNotNull($datosA1);
        $this->assertSame(1, (int)$datosA1['asistencias']);
        $this->assertSame(0, (int)$datosA1['inasistencias']);
        $this->assertSame(0, (int)$datosA1['retardos']);

        $this->assertNotNull($datosA2);
        $this->assertSame(0, (int)$datosA2['asistencias']);
        $this->assertSame(1, (int)$datosA2['inasistencias']);
    }

    public function testDetalleAprendizConFichaYGlobal(): void
    {
        $ficha = $this->crearFicha();
        $instructor = $this->crearUsuario(Usuario::ROL_INSTRUCTOR);
        $aprendiz = $this->crearUsuario(Usuario::ROL_APRENDIZ, ['Ficha_id' => $ficha]);

        $fecha = date('Y-m-d');
        $sesion = $this->crearSesion($ficha, $instructor, ['fecha' => $fecha, 'estado' => 'Finalizada']);
        $this->crearAsistencia($aprendiz, $sesion, ['fecha' => $fecha, 'minutos_retardo' => 10]);

        // Caso 1: Detalle con fichaId > 0
        $detalleFicha = $this->reporteModel->detalleAprendiz($aprendiz, $ficha, $fecha, $fecha);
        $this->assertNotEmpty($detalleFicha);
        $this->assertSame(10, (int)$detalleFicha[0]['minutos_retardo']);
        $this->assertNotNull($detalleFicha[0]['asistencia_id']);

        // Caso 2: Detalle global (fichaId = 0)
        $detalleGlobal = $this->reporteModel->detalleAprendiz($aprendiz, 0, $fecha, $fecha);
        $this->assertNotEmpty($detalleGlobal);
        $this->assertSame(10, (int)$detalleGlobal[0]['minutos_retardo']);
    }

    public function testIndicadoresDiferenciadosPorRolGlobalVsAprendiz(): void
    {
        $ficha = $this->crearFicha();
        $instructor = $this->crearUsuario(Usuario::ROL_INSTRUCTOR);
        $aprendiz = $this->crearUsuario(Usuario::ROL_APRENDIZ, ['Ficha_id' => $ficha]);

        $sesion = $this->crearSesion($ficha, $instructor, ['fecha' => date('Y-m-d')]);
        $this->crearAsistencia($aprendiz, $sesion, ['fecha' => date('Y-m-d')]);

        // Caso 1: Rol Admin/Instructor (usuarioId = null -> Indicadores Globales)
        $indGlobal = $this->reporteModel->indicadores(null);
        $this->assertArrayHasKey('asistencia_por_mes', $indGlobal);
        $this->assertArrayHasKey('estado_excusas', $indGlobal);
        $this->assertArrayHasKey('puntualidad', $indGlobal);
        $this->assertIsArray($indGlobal['asistencia_por_mes']['labels']);
        $this->assertCount(6, $indGlobal['asistencia_por_mes']['labels']);

        // Caso 2: Rol Aprendiz (usuarioId específico -> Indicadores individuales)
        $indAprendiz = $this->reporteModel->indicadores($aprendiz);
        $this->assertArrayHasKey('asistencia_por_mes', $indAprendiz);
        $this->assertArrayHasKey('estado_excusas', $indAprendiz);
        $this->assertArrayHasKey('puntualidad', $indAprendiz);
    }

    public function testGetMetricasAprendizCalculaPorcentajeYHistorial(): void
    {
        $ficha = $this->crearFicha();
        $instructor = $this->crearUsuario(Usuario::ROL_INSTRUCTOR);
        $aprendiz = $this->crearUsuario(Usuario::ROL_APRENDIZ, ['Ficha_id' => $ficha]);

        $s1 = $this->crearSesion($ficha, $instructor, ['fecha' => '2026-11-01', 'estado' => 'Finalizada']);
        $s2 = $this->crearSesion($ficha, $instructor, ['fecha' => '2026-11-02', 'estado' => 'Finalizada']);

        // 1 asistencia a tiempo, 1 inasistencia
        $this->crearAsistencia($aprendiz, $s1, ['fecha' => '2026-11-01', 'minutos_retardo' => 0]);

        $stmtI = $this->db->prepare("INSERT INTO inasistencia (Usuario_id, Ficha_id, Sesion_id, fecha, generado_por)
                                    VALUES (:uid, :fid, :sid, '2026-11-02', 'sistema')");
        $stmtI->execute([
            ':uid' => $aprendiz,
            ':fid' => $ficha,
            ':sid' => $s2,
        ]);

        $metricas = $this->reporteModel->getMetricasAprendiz($aprendiz);

        $this->assertIsArray($metricas);
        $this->assertSame(1, $metricas['total_asistencias']);
        $this->assertSame(1, $metricas['asistencias_a_tiempo']);
        $this->assertSame(1, $metricas['total_inasistencias']);
        $this->assertSame(1, $metricas['inasistencias_injustificadas']);
        $this->assertSame(50.0, (float)$metricas['porcentaje_asistencia']);
        $this->assertCount(2, $metricas['historial_marcas']);
    }
}
