<?php
declare(strict_types=1);

namespace Tests\Integration;

require_once __DIR__ . '/IntegrationTestCase.php';

use Sesion;
use Usuario;

/**
 * Pruebas de Integración para el modelo Sesion (models/Sesion.php)
 *
 * Valida la programación y control de sesiones formativas:
 * - Filtros por Rol y entidad (Ficha, Instructor, combinados)
 * - Detección de solapamientos horarios (RF-HOR-17, RF-HOR-18)
 * - Ciclo de vida del Toggle de Asistencia (Apertura y Cierre de sesión)
 * - Generación automática de inasistencias al cerrar sesión
 */
class SesionIntegrationTest extends IntegrationTestCase
{
    private Sesion $sesionModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sesionModel = new Sesion($this->db);
    }

    public function testGetAllYGetFiltradasPorFichaEInstructor(): void
    {
        $ficha1 = $this->crearFicha();
        $ficha2 = $this->crearFicha();
        $inst1  = $this->crearUsuario(Usuario::ROL_INSTRUCTOR);
        $inst2  = $this->crearUsuario(Usuario::ROL_INSTRUCTOR);

        $s1 = $this->crearSesion($ficha1, $inst1);
        $s2 = $this->crearSesion($ficha1, $inst2);
        $s3 = $this->crearSesion($ficha2, $inst1);

        // Caso 1: getAll retorna todas
        $todas = $this->sesionModel->getAll();
        $idsTodas = array_map(fn($s) => (int)$s['id'], $todas);
        $this->assertContains($s1, $idsTodas);
        $this->assertContains($s2, $idsTodas);
        $this->assertContains($s3, $idsTodas);

        // Caso 2: Filtradas por Ficha 1
        $porFicha1 = $this->sesionModel->getFiltradas($ficha1, null);
        $idsF1 = array_map(fn($s) => (int)$s['id'], $porFicha1);
        $this->assertContains($s1, $idsF1);
        $this->assertContains($s2, $idsF1);
        $this->assertNotContains($s3, $idsF1);

        // Caso 3: Filtradas por Instructor 1
        $porInst1 = $this->sesionModel->getFiltradas(null, $inst1);
        $idsI1 = array_map(fn($s) => (int)$s['id'], $porInst1);
        $this->assertContains($s1, $idsI1);
        $this->assertContains($s3, $idsI1);
        $this->assertNotContains($s2, $idsI1);

        // Caso 4: Filtradas cruzadas Ficha 1 + Instructor 1
        $cruzadas = $this->sesionModel->getFiltradas($ficha1, $inst1);
        $idsCruz = array_map(fn($s) => (int)$s['id'], $cruzadas);
        $this->assertContains($s1, $idsCruz);
        $this->assertNotContains($s2, $idsCruz);
        $this->assertNotContains($s3, $idsCruz);
    }

    public function testGetByFichaYGetByInstructor(): void
    {
        $ficha = $this->crearFicha();
        $inst = $this->crearUsuario(Usuario::ROL_INSTRUCTOR);

        $s1 = $this->crearSesion($ficha, $inst, ['fecha' => '2026-11-01', 'hora_inicio' => '07:00:00', 'hora_fin' => '10:00:00']);
        $s2 = $this->crearSesion($ficha, $inst, ['fecha' => '2026-11-02', 'hora_inicio' => '10:00:00', 'hora_fin' => '13:00:00']);

        $porFicha = $this->sesionModel->getByFicha($ficha);
        $this->assertCount(2, $porFicha);
        $this->assertSame($s1, (int)$porFicha[0]['id']);
        $this->assertSame($s2, (int)$porFicha[1]['id']);

        $porInst = $this->sesionModel->getByInstructor($inst, '2026-11-01', '2026-11-01');
        $this->assertCount(1, $porInst);
        $this->assertSame($s1, (int)$porInst[0]['id']);
    }

    public function testValidarSolapamientoDetectaConflictosCorrectamente(): void
    {
        $fichaA = $this->crearFicha();
        $fichaB = $this->crearFicha();
        $inst1 = $this->crearUsuario(Usuario::ROL_INSTRUCTOR);
        $inst2 = $this->crearUsuario(Usuario::ROL_INSTRUCTOR);

        $fecha = '2026-11-20';
        $this->crearSesion($fichaA, $inst1, [
            'fecha'       => $fecha,
            'hora_inicio' => '08:00:00',
            'hora_fin'    => '12:00:00',
            'estado'      => 'Programado',
        ]);

        // Caso 1: Mismo instructor en otra ficha al mismo horario -> Conflicto
        $resSolapInst = $this->sesionModel->validarSolapamiento($fichaB, $inst1, $fecha, '09:00:00', '11:00:00');
        $this->assertFalse($resSolapInst['valido']);
        $this->assertStringContainsString('instructor', strtolower($resSolapInst['mensaje']));

        // Caso 2: Misma ficha con otro instructor al mismo horario -> Conflicto
        $resSolapFicha = $this->sesionModel->validarSolapamiento($fichaA, $inst2, $fecha, '09:00:00', '11:00:00');
        $this->assertFalse($resSolapFicha['valido']);
        $this->assertStringContainsString('ficha', strtolower($resSolapFicha['mensaje']));

        // Caso 3: Horario completamente libre sin cruce -> Válido
        $resLibre = $this->sesionModel->validarSolapamiento($fichaB, $inst2, $fecha, '14:00:00', '18:00:00');
        $this->assertTrue($resLibre['valido']);
    }

    public function testCicloDeVidaToggleAbrirYCerrarSesionConInasistencias(): void
    {
        $ficha = $this->crearFicha();
        $instructor = $this->crearUsuario(Usuario::ROL_INSTRUCTOR);
        $this->crearCompetencia(null, $instructor);

        // Crear 2 aprendices para la ficha
        $aprenPresente = $this->crearUsuario(Usuario::ROL_APRENDIZ, ['Ficha_id' => $ficha]);
        $aprenAusente  = $this->crearUsuario(Usuario::ROL_APRENDIZ, ['Ficha_id' => $ficha]);

        // Paso 1: Abrir sesión (toggle ON)
        $sesionId = $this->sesionModel->abrirSesion($ficha, $instructor);
        $this->assertIsInt($sesionId);
        $this->assertGreaterThan(0, $sesionId);

        // Validar que la sesión está activa hoy
        $activa = $this->sesionModel->obtenerSesionActivaPorFicha($ficha);
        $this->assertNotNull($activa);
        $this->assertSame($sesionId, (int)$activa['id']);
        $this->assertSame('Activo', $activa['estado']);

        // No debe permitir abrir una segunda sesión concurrente en la misma ficha
        $segundoIntento = $this->sesionModel->abrirSesion($ficha, $instructor);
        $this->assertFalse($segundoIntento);

        // Paso 2: Registrar asistencia de 1 aprendiz
        $this->crearAsistencia($aprenPresente, $sesionId);
        $this->assertTrue($this->sesionModel->tieneAsistencias($sesionId));

        // Como cerrarSesion() maneja su propia transacción interna PDO,
        // confirmamos la transacción de setUp() y aplicamos limpieza con DELETE al finalizar.
        if ($this->db->inTransaction()) {
            $this->db->commit();
        }

        try {
            // Paso 3: Cerrar sesión (toggle OFF) con el instructor asignado
            $resCierre = $this->sesionModel->cerrarSesion($sesionId, $instructor);
            $this->assertTrue($resCierre['ok']);
            $this->assertSame(1, $resCierre['inasistencias']);

            // Comprobar que la sesión finalizó
            $stmtS = $this->db->prepare("SELECT estado, hora_fin FROM sesion WHERE id = :id");
            $stmtS->execute([':id' => $sesionId]);
            $sesionCerrada = $stmtS->fetch();
            $this->assertSame('Finalizada', $sesionCerrada['estado']);
            $this->assertNotNull($sesionCerrada['hora_fin']);

            // Comprobar la regla de inasistencias:
            // El aprendiz ausente debe tener registro en `inasistencia`
            $stmtI = $this->db->prepare("SELECT * FROM inasistencia WHERE Sesion_id = :sid AND Usuario_id = :uid");
            $stmtI->execute([':sid' => $sesionId, ':uid' => $aprenAusente]);
            $inasistencia = $stmtI->fetch();
            $this->assertNotEmpty($inasistencia, 'El aprendiz ausente debe tener inasistencia generada automáticamente.');

            // El aprendiz presente NO debe tener inasistencia
            $stmtI->execute([':sid' => $sesionId, ':uid' => $aprenPresente]);
            $this->assertFalse($stmtI->fetch(), 'El aprendiz presente no debe tener registro de inasistencia.');
        } finally {
            // Limpieza con DELETE en orden estricto de FK
            $this->db->prepare("DELETE FROM inasistencia WHERE Sesion_id = :id")->execute([':id' => $sesionId]);
            $this->db->prepare("DELETE FROM asistencia WHERE Sesion_id = :id")->execute([':id' => $sesionId]);
            $this->db->prepare("DELETE FROM sesion WHERE id = :id")->execute([':id' => $sesionId]);
            $this->db->prepare("DELETE FROM competencia WHERE Instructor_id = :id")->execute([':id' => $instructor]);
            $this->db->prepare("UPDATE usuario SET Ficha_id = NULL WHERE id IN (:u2, :u3)")->execute([
                ':u2' => $aprenPresente,
                ':u3' => $aprenAusente,
            ]);
            $this->db->prepare("DELETE FROM ficha WHERE id = :id")->execute([':id' => $ficha]);
            $this->db->prepare("DELETE FROM usuario WHERE id IN (:u1, :u2, :u3)")->execute([
                ':u1' => $instructor,
                ':u2' => $aprenPresente,
                ':u3' => $aprenAusente,
            ]);
        }
    }
}
