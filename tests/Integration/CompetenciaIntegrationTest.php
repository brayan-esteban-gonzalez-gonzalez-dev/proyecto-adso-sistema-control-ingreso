<?php
declare(strict_types=1);

namespace Tests\Integration;

require_once __DIR__ . '/IntegrationTestCase.php';

use Competencia;

/**
 * Pruebas de Integración para el modelo Competencia (app/models/Competencia.php)
 *
 * Valida operaciones reales sobre las tablas `competencia` e `instructor_competencia`:
 * - Creación y consulta de competencias asociadas a programas
 * - Regla de validación de unicidad de nombre en el mismo programa (`existeNombre`)
 * - Sincronización y vinculación de múltiples instructores (`syncInstructores`)
 * - Filtros por programa formativo en `getAll`
 * - Métricas de sesiones asociadas (`countSesiones`)
 * - Eliminación controlada y limpieza en cascada
 */
class CompetenciaIntegrationTest extends IntegrationTestCase
{
    private Competencia $competenciaModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->competenciaModel = new Competencia($this->db);
    }

    /**
     * Prueba 1: Camino feliz - Creación de competencia formativa ligada a un programa.
     */
    public function testCrearCompetenciaAsociadaAPrograma(): void
    {
        $progId = $this->crearPrograma('Desarrollo de Software');
        $instId = $this->crearUsuario(2);
        $nombreComp = 'Modelado de Bases de Datos ' . uniqid();

        $compId = $this->competenciaModel->create([
            'Programa_id'   => $progId,
            'Instructor_id' => $instId,
            'nombre'        => $nombreComp,
            'descripcion'   => 'Diseño de modelos relacionales y no relacionales',
        ]);

        $this->assertGreaterThan(0, $compId);

        $competencia = $this->competenciaModel->getById($compId);
        $this->assertNotNull($competencia);
        $this->assertEquals($nombreComp, $competencia['nombre']);
        $this->assertEquals($progId, (int) $competencia['Programa_id']);
        $this->assertEquals($instId, (int) $competencia['Instructor_id']);
        $this->assertEquals('Diseño de modelos relacionales y no relacionales', $competencia['descripcion']);
    }

    /**
     * Prueba 2: Regla de negocio - Detecta nombres duplicados dentro del mismo programa formativo.
     */
    public function testExisteNombreDetectaDuplicadosEnMismoPrograma(): void
    {
        $progA = $this->crearPrograma('Programa A');
        $progB = $this->crearPrograma('Programa B');
        $nombreRepetido = 'Programación Orientada a Objetos ' . uniqid();

        $compId = $this->competenciaModel->create([
            'Programa_id'   => $progA,
            'Instructor_id' => null,
            'nombre'        => $nombreRepetido,
            'descripcion'   => null,
        ]);

        // Mismo programa y mismo nombre -> true
        $this->assertTrue($this->competenciaModel->existeNombre($progA, $nombreRepetido));

        // Mismo programa pero excluyendo el propio ID de la competencia -> false
        $this->assertFalse($this->competenciaModel->existeNombre($progA, $nombreRepetido, $compId));

        // Otro programa con el mismo nombre -> false (se permiten homónimas en programas distintos)
        $this->assertFalse($this->competenciaModel->existeNombre($progB, $nombreRepetido));
    }

    /**
     * Prueba 3: Sincronización de instructores (syncInstructores) y consulta de instructores vinculados.
     */
    public function testSyncInstructoresVinculaMultiplesInstructores(): void
    {
        $progId = $this->crearPrograma();
        $inst1 = $this->crearUsuario(2);
        $inst2 = $this->crearUsuario(2);

        $compId = $this->competenciaModel->create([
            'Programa_id'   => $progId,
            'Instructor_id' => null,
            'nombre'        => 'Competencia Compartida ' . uniqid(),
            'descripcion'   => 'Docencia colaborativa',
        ]);

        // Vincular dos instructores
        $this->competenciaModel->syncInstructores($compId, [$inst1, $inst2]);

        $instructores = $this->competenciaModel->getInstructores($compId);
        $ids = array_map(fn($row) => (int) $row['id'], $instructores);

        $this->assertCount(2, $instructores);
        $this->assertContains($inst1, $ids);
        $this->assertContains($inst2, $ids);
    }

    /**
     * Prueba 4: Listado getAll filtra correctamente por programa formativo.
     */
    public function testGetAllFiltraPorPrograma(): void
    {
        $progA = $this->crearPrograma('Programa Alfa');
        $progB = $this->crearPrograma('Programa Beta');

        $this->competenciaModel->create([
            'Programa_id'   => $progA,
            'Instructor_id' => null,
            'nombre'        => 'Comp A1 ' . uniqid(),
            'descripcion'   => null,
        ]);
        $this->competenciaModel->create([
            'Programa_id'   => $progA,
            'Instructor_id' => null,
            'nombre'        => 'Comp A2 ' . uniqid(),
            'descripcion'   => null,
        ]);
        $this->competenciaModel->create([
            'Programa_id'   => $progB,
            'Instructor_id' => null,
            'nombre'        => 'Comp B1 ' . uniqid(),
            'descripcion'   => null,
        ]);

        $competenciasProgA = $this->competenciaModel->getAll($progA);
        $this->assertGreaterThanOrEqual(2, count($competenciasProgA));

        foreach ($competenciasProgA as $c) {
            $this->assertEquals($progA, (int) $c['Programa_id']);
        }
    }

    /**
     * Prueba 5: Métrica de sesiones asociadas (countSesiones).
     */
    public function testCountSesionesAsociadas(): void
    {
        $progId = $this->crearPrograma();
        $jornadaId = $this->crearJornada();
        $instId = $this->crearUsuario(2);
        $fichaId = $this->crearFicha(null, $progId, $instId, $jornadaId);

        $compId = $this->competenciaModel->create([
            'Programa_id'   => $progId,
            'Instructor_id' => $instId,
            'nombre'        => 'Competencia con Sesiones ' . uniqid(),
            'descripcion'   => null,
        ]);

        $this->assertEquals(0, $this->competenciaModel->countSesiones($compId));

        // Crear 2 sesiones vinculadas a esta competencia
        $this->crearSesion($fichaId, $instId, ['Competencia_id' => $compId]);
        $this->crearSesion($fichaId, $instId, ['Competencia_id' => $compId]);

        $this->assertEquals(2, $this->competenciaModel->countSesiones($compId));
    }

    /**
     * Prueba 6: Eliminación segura de competencia y limpieza de vinculaciones en instructor_competencia.
     */
    public function testEliminarCompetenciaSinSesiones(): void
    {
        $progId = $this->crearPrograma();
        $instId = $this->crearUsuario(2);

        $compId = $this->competenciaModel->create([
            'Programa_id'   => $progId,
            'Instructor_id' => $instId,
            'nombre'        => 'Competencia para Borrar ' . uniqid(),
            'descripcion'   => null,
        ]);

        $this->competenciaModel->syncInstructores($compId, [$instId]);
        $this->assertNotEmpty($this->competenciaModel->getInstructores($compId));

        $borrado = $this->competenciaModel->delete($compId);
        $this->assertTrue($borrado);

        $this->assertNull($this->competenciaModel->getById($compId));
        $this->assertEmpty($this->competenciaModel->getInstructores($compId));
    }
}

