<?php
declare(strict_types=1);

namespace Tests\Integration;

require_once __DIR__ . '/IntegrationTestCase.php';

use ExcusaMedica;

/**
 * Pruebas de Integración para el modelo ExcusaMedica (app/models/ExcusaMedica.php)
 *
 * Valida operaciones reales sobre las tablas `excusa`, `estado_excusa` e `inasistencia`:
 * - Radicación de excusas médicas por aprendices (camino feliz)
 * - Flujos de revisión: Aprobación y Rechazo por instructores
 * - Regla de negocio de doble validación por co-docencia (AGENTS.md / RF-HOR-34)
 * - Privilegio de aprobación directa de Administrador
 * - Aislamiento de consultas por aprendiz, listado consolidado y bandeja de pendientes
 */
class ExcusaMedicaIntegrationTest extends IntegrationTestCase
{
    private ExcusaMedica $excusaModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->excusaModel = new ExcusaMedica($this->db);
    }

    /**
     * Prueba 1: Camino feliz - Aprendiz radica excusa médica para una inasistencia.
     */
    public function testCrearExcusaParaInasistenciaCaminoFeliz(): void
    {
        $progId = $this->crearPrograma();
        $jornadaId = $this->crearJornada();
        $instId = $this->crearUsuario(2);
        $fichaId = $this->crearFicha(null, $progId, $instId, $jornadaId);
        $aprendizId = $this->crearUsuario(3, ['Ficha_id' => $fichaId]);

        $sesionId = $this->crearSesion($fichaId, $instId);
        $inasistenciaId = $this->crearInasistencia($aprendizId, $fichaId, $sesionId, [
            'fecha' => date('Y-m-d'),
            'estado' => 'Injustificada'
        ]);

        $creado = $this->excusaModel->crear([
            'usuario_id'      => $aprendizId,
            'inasistencia_id' => $inasistenciaId,
            'motivo'          => 'Cita con especialista de ortopedia',
            'evidencia'       => 'incapacidad_eps_sura.pdf',
            'fecha'           => date('Y-m-d'),
        ]);

        $this->assertTrue($creado, 'La excusa médica debe registrarse con éxito en la base de datos');

        // Verificar persistencia de la excusa con getById
        $stmtExcusa = $this->db->prepare("SELECT id FROM excusa WHERE Inasistencia_id = :id");
        $stmtExcusa->execute([':id' => $inasistenciaId]);
        $excusaId = (int) $stmtExcusa->fetchColumn();

        $excusa = $this->excusaModel->getById($excusaId);
        $this->assertNotNull($excusa);
        $this->assertEquals($aprendizId, (int) $excusa['Usuario_id']);
        $this->assertEquals('Cita con especialista de ortopedia', $excusa['motivo']);
        $this->assertEquals('incapacidad_eps_sura.pdf', $excusa['evidencia']);
    }

    /**
     * Prueba 2: Rechazo de excusa por instructor con respuesta/motivo.
     */
    public function testRechazarExcusaPorInstructor(): void
    {
        $progId = $this->crearPrograma();
        $jornadaId = $this->crearJornada();
        $instId = $this->crearUsuario(2);
        $fichaId = $this->crearFicha(null, $progId, $instId, $jornadaId);
        $aprendizId = $this->crearUsuario(3, ['Ficha_id' => $fichaId]);

        $sesionId = $this->crearSesion($fichaId, $instId);
        $inasistenciaId = $this->crearInasistencia($aprendizId, $fichaId, $sesionId, ['fecha' => date('Y-m-d')]);
        $excusaId = $this->crearExcusa($aprendizId, $inasistenciaId);

        $rechazado = $this->excusaModel->rechazar($excusaId, $instId, 'Documento ilegible sin sello médico');
        $this->assertTrue($rechazado);

        // Verificar en estado_excusa
        $stmtEstado = $this->db->prepare("SELECT estado, respuesta, Instructor_id FROM estado_excusa WHERE Excusa_id = :id");
        $stmtEstado->execute([':id' => $excusaId]);
        $estadoRow = $stmtEstado->fetch();

        $this->assertIsArray($estadoRow);
        $this->assertEquals('Rechazada', $estadoRow['estado']);
        $this->assertEquals('Documento ilegible sin sello médico', $estadoRow['respuesta']);
        $this->assertEquals($instId, (int) $estadoRow['Instructor_id']);

        // La inasistencia debe permanecer como Injustificada
        $stmtInasistencia = $this->db->prepare("SELECT estado FROM inasistencia WHERE id = :id");
        $stmtInasistencia->execute([':id' => $inasistenciaId]);
        $this->assertEquals('Injustificada', $stmtInasistencia->fetchColumn());
    }

    /**
     * Prueba 3: Aprobación simple (1 solo instructor) actualiza la inasistencia a 'Justificada'.
     */
    public function testAprobarExcusaSimpleActualizaInasistenciaAJustificada(): void
    {
        $progId = $this->crearPrograma();
        $jornadaId = $this->crearJornada();
        $instId = $this->crearUsuario(2);
        $fichaId = $this->crearFicha(null, $progId, $instId, $jornadaId);
        $aprendizId = $this->crearUsuario(3, ['Ficha_id' => $fichaId]);

        $fecha = date('Y-m-d');
        $sesionId = $this->crearSesion($fichaId, $instId, ['fecha' => $fecha]);
        $inasistenciaId = $this->crearInasistencia($aprendizId, $fichaId, $sesionId, ['fecha' => $fecha]);
        $excusaId = $this->crearExcusa($aprendizId, $inasistenciaId, ['fecha' => $fecha]);

        $resultado = $this->excusaModel->aprobar($excusaId, $instId, 'Incapacidad EPS validada correctamente');

        $this->assertTrue($resultado['ok']);
        $this->assertTrue($resultado['completa']);

        // Inasistencia debe estar en estado Justificada
        $stmtInasistencia = $this->db->prepare("SELECT estado FROM inasistencia WHERE id = :id");
        $stmtInasistencia->execute([':id' => $inasistenciaId]);
        $this->assertEquals('Justificada', $stmtInasistencia->fetchColumn());
    }

    /**
     * Prueba 4: Administrador aprueba directamente con privilegio (esAdmin = true).
     */
    public function testAprobarExcusaPorAdminFuerzaAprobacionDirecta(): void
    {
        $progId = $this->crearPrograma();
        $jornadaId = $this->crearJornada();
        $adminId = $this->crearUsuario(1);
        $instId = $this->crearUsuario(2);
        $fichaId = $this->crearFicha(null, $progId, $instId, $jornadaId);
        $aprendizId = $this->crearUsuario(3, ['Ficha_id' => $fichaId]);

        $sesionId = $this->crearSesion($fichaId, $instId);
        $inasistenciaId = $this->crearInasistencia($aprendizId, $fichaId, $sesionId, ['fecha' => date('Y-m-d')]);
        $excusaId = $this->crearExcusa($aprendizId, $inasistenciaId);

        $resultado = $this->excusaModel->aprobar($excusaId, $adminId, 'Aprobación directiva por coordinación', true);

        $this->assertTrue($resultado['ok']);
        $this->assertTrue($resultado['completa']);

        // Inasistencia justificada
        $stmtInasistencia = $this->db->prepare("SELECT estado FROM inasistencia WHERE id = :id");
        $stmtInasistencia->execute([':id' => $inasistenciaId]);
        $this->assertEquals('Justificada', $stmtInasistencia->fetchColumn());
    }

    /**
     * Prueba 5: Regla de negocio de co-docencia (AGENTS.md / RF-HOR-34).
     * Si la ficha tiene 2 o más instructores en la misma fecha, requiere doble aprobación.
     */
    public function testReglaNegocioDobleAprobacionCuandoHayCoDocencia(): void
    {
        $progId = $this->crearPrograma();
        $jornadaId = $this->crearJornada();
        $inst1 = $this->crearUsuario(2);
        $inst2 = $this->crearUsuario(2);
        $fichaId = $this->crearFicha($progId, $jornadaId, $inst1);
        $aprendizId = $this->crearUsuario(3, ['Ficha_id' => $fichaId]);

        $fecha = date('Y-m-d');
        // Crear 2 sesiones para la misma ficha y fecha pero con instructores distintos
        $sesion1 = $this->crearSesion($fichaId, $inst1, ['fecha' => $fecha, 'hora_inicio' => '07:00:00', 'hora_fin' => '10:00:00']);
        $this->crearSesion($fichaId, $inst2, ['fecha' => $fecha, 'hora_inicio' => '10:00:00', 'hora_fin' => '13:00:00']);

        $inasistenciaId = $this->crearInasistencia($aprendizId, $fichaId, $sesion1, ['fecha' => $fecha]);
        $excusaId = $this->crearExcusa($aprendizId, $inasistenciaId, ['fecha' => $fecha]);

        $requiereDoble = $this->excusaModel->requiereDobleAprobacion($excusaId);
        $this->assertTrue($requiereDoble, 'Debe exigir doble aprobación cuando hay 2 o más instructores en la misma jornada');
    }

    /**
     * Prueba 6: Primera aprobación en co-docencia mantiene estado parcial en espera del segundo instructor.
     */
    public function testPrimeraAprobacionEnCoDocenciaMantieneEstadoParcial(): void
    {
        $progId = $this->crearPrograma();
        $jornadaId = $this->crearJornada();
        $inst1 = $this->crearUsuario(2);
        $inst2 = $this->crearUsuario(2);
        $fichaId = $this->crearFicha($progId, $jornadaId, $inst1);
        $aprendizId = $this->crearUsuario(3, ['Ficha_id' => $fichaId]);

        $fecha = date('Y-m-d');
        $sesion1 = $this->crearSesion($fichaId, $inst1, ['fecha' => $fecha, 'hora_inicio' => '07:00:00', 'hora_fin' => '10:00:00']);
        $this->crearSesion($fichaId, $inst2, ['fecha' => $fecha, 'hora_inicio' => '10:00:00', 'hora_fin' => '13:00:00']);

        $inasistenciaId = $this->crearInasistencia($aprendizId, $fichaId, $sesion1, ['fecha' => $fecha]);
        $excusaId = $this->crearExcusa($aprendizId, $inasistenciaId, ['fecha' => $fecha]);

        $res1 = $this->excusaModel->aprobar($excusaId, $inst1, 'Visto bueno instructor 1');

        $this->assertTrue($res1['ok']);
        $this->assertFalse($res1['completa']);
        $this->assertStringContainsString('co-docencia', $res1['mensaje']);
    }

    /**
     * Prueba 7: Consulta consolidada getAll retorna detalles completos con joins.
     */
    public function testGetAllRetornaDatosCompletosConRelaciones(): void
    {
        $progId = $this->crearPrograma('ADSO Test');
        $jornadaId = $this->crearJornada();
        $instId = $this->crearUsuario(2);
        $fichaId = $this->crearFicha(null, $progId, $instId, $jornadaId);
        $aprendizId = $this->crearUsuario(3, ['Ficha_id' => $fichaId]);

        $sesionId = $this->crearSesion($fichaId, $instId);
        $inapId = $this->crearInasistencia($aprendizId, $fichaId, $sesionId, ['fecha' => date('Y-m-d')]);
        $excusaId = $this->crearExcusa($aprendizId, $inapId, ['motivo' => 'Motivo Detallado']);

        $todas = $this->excusaModel->getAll();
        $this->assertIsArray($todas);

        $encontrada = null;
        foreach ($todas as $item) {
            if ((int) $item['id'] === $excusaId) {
                $encontrada = $item;
                break;
            }
        }

        $this->assertNotNull($encontrada, 'La excusa creada debe aparecer en el listado getAll');
        $this->assertEquals('Motivo Detallado', $encontrada['motivo']);
        $this->assertStringContainsString('ADSO Test', $encontrada['nombre_programa']);
    }

    /**
     * Prueba 8: Aislamiento por aprendiz (getByAprendiz solo devuelve las del aprendiz consultado).
     */
    public function testGetByAprendizFiltraUnicamenteExcusasDelUsuario(): void
    {
        $progId = $this->crearPrograma();
        $jornadaId = $this->crearJornada();
        $instId = $this->crearUsuario(2);
        $fichaId = $this->crearFicha(null, $progId, $instId, $jornadaId);

        $aprendizA = $this->crearUsuario(3, ['Ficha_id' => $fichaId]);
        $aprendizB = $this->crearUsuario(3, ['Ficha_id' => $fichaId]);

        $sesionId = $this->crearSesion($fichaId, $instId);
        $inapA = $this->crearInasistencia($aprendizA, $fichaId, $sesionId, ['fecha' => date('Y-m-d')]);
        $inapB = $this->crearInasistencia($aprendizB, $fichaId, $sesionId, ['fecha' => date('Y-m-d')]);

        $this->crearExcusa($aprendizA, $inapA, ['motivo' => 'Motivo A']);
        $this->crearExcusa($aprendizB, $inapB, ['motivo' => 'Motivo B']);

        $excusasA = $this->excusaModel->getByAprendiz($aprendizA);
        $this->assertCount(1, $excusasA);
        $this->assertEquals($aprendizA, (int) $excusasA[0]['Usuario_id']);
        $this->assertEquals('Motivo A', $excusasA[0]['motivo']);
    }

    /**
     * Prueba 9: getPendientes y countPendientes segregan excusas sin revisión.
     */
    public function testGetPendientesSegregaExcusasSinRevision(): void
    {
        $progId = $this->crearPrograma();
        $jornadaId = $this->crearJornada();
        $instId = $this->crearUsuario(2);
        $fichaId = $this->crearFicha(null, $progId, $instId, $jornadaId);
        $aprendizId = $this->crearUsuario(3, ['Ficha_id' => $fichaId]);

        $sesion1 = $this->crearSesion($fichaId, $instId);
        $sesion2 = $this->crearSesion($fichaId, $instId);

        $inap1 = $this->crearInasistencia($aprendizId, $fichaId, $sesion1, ['fecha' => date('Y-m-d')]);
        $inap2 = $this->crearInasistencia($aprendizId, $fichaId, $sesion2, ['fecha' => date('Y-m-d')]);

        // Excusa 1: Pendiente (sin revisión en estado_excusa)
        $excusa1 = $this->crearExcusa($aprendizId, $inap1, ['motivo' => 'Pendiente de revisar']);
        // Excusa 2: Ya rechazada
        $excusa2 = $this->crearExcusa($aprendizId, $inap2, ['motivo' => 'Ya rechazada']);
        $this->excusaModel->rechazar($excusa2, $instId, 'No aprobada');

        $pendientes = $this->excusaModel->getPendientes();
        $idsPendientes = array_map(fn($row) => (int) $row['id'], $pendientes);

        $this->assertContains($excusa1, $idsPendientes);
        $this->assertNotContains($excusa2, $idsPendientes);
        $this->assertGreaterThanOrEqual(1, $this->excusaModel->countPendientes());
    }
}
