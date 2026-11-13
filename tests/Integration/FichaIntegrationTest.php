<?php
declare(strict_types=1);

namespace Tests\Integration;

require_once __DIR__ . '/IntegrationTestCase.php';

use Ficha;
use PDOException;

/**
 * Pruebas de Integración para el modelo Ficha (app/models/Ficha.php)
 *
 * Valida operaciones reales sobre la tabla `ficha`:
 * - Creación y unicidad de código de ficha (camino feliz vs duplicado)
 * - Búsqueda por ID, código y relaciones con programa y jornada
 * - Pertenencia de instructor a ficha (líder, por sesión y asignación docente)
 * - Consulta de nómina de aprendices por ficha
 * - Actualización de estado y contadores globales
 */
class FichaIntegrationTest extends IntegrationTestCase
{
    private Ficha $fichaModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fichaModel = new Ficha($this->db);
    }

    /**
     * Prueba 1: Camino feliz - Creación de ficha con código numérico, programa y jornada.
     */
    public function testCrearFichaCaminoFeliz(): void
    {
        $progId = $this->crearPrograma('Desarrollo de Software');
        $jornadaId = $this->crearJornada('Diurna');
        $instId = $this->crearUsuario(2);
        $codigoFicha = mt_rand(1000000, 9999999);

        $creado = $this->fichaModel->create([
            'codigo'        => $codigoFicha,
            'Programa_id'   => $progId,
            'jornada_id'    => $jornadaId,
            'instructor_id' => $instId,
            'estado'        => 'Activo',
        ]);

        $this->assertTrue($creado, 'La ficha debe crearse exitosamente');

        $ficha = $this->fichaModel->getByCodigo($codigoFicha);
        $this->assertNotNull($ficha);
        $this->assertEquals($codigoFicha, (int) $ficha['codigo']);
        $this->assertEquals($progId, (int) $ficha['Programa_id']);
        $this->assertEquals($jornadaId, (int) $ficha['jornada_id']);
        $this->assertEquals($instId, (int) $ficha['instructor_id']);
        $this->assertEquals('Activo', $ficha['estado']);
    }

    /**
     * Prueba 2: Regla de integridad referencial - Programa inexistente genera PDOException y rollback.
     */
    public function testCrearFichaConProgramaInexistenteLanzaExcepcion(): void
    {
        $jornadaId = $this->crearJornada();
        $instId = $this->crearUsuario(2);
        $programaInexistente = 9999999;

        $this->expectException(PDOException::class);
        $this->fichaModel->create([
            'codigo'        => mt_rand(1000000, 9999999),
            'Programa_id'   => $programaInexistente,
            'jornada_id'    => $jornadaId,
            'instructor_id' => $instId,
            'estado'        => 'Activo',
        ]);
    }

    /**
     * Prueba 3: Consulta getByCodigo y getById retornan datos equivalentes con joins de programa y jornada.
     */
    public function testGetByCodigoYGetById(): void
    {
        $progId = $this->crearPrograma('Multimedia');
        $jornadaId = $this->crearJornada('Nocturna');
        $instId = $this->crearUsuario(2);
        $codigo = mt_rand(1000000, 9999999);

        $fichaId = $this->crearFicha($codigo, $progId, $instId, $jornadaId);

        $porId = $this->fichaModel->getById($fichaId);
        $porCodigo = $this->fichaModel->getByCodigo($codigo);

        $this->assertNotNull($porId);
        $this->assertNotNull($porCodigo);
        $this->assertEquals($porId['id'], $porCodigo['id']);
        $this->assertEquals($codigo, (int) $porId['codigo']);
        $this->assertNotEmpty($porId['nombre_programa']);
        $this->assertNotEmpty($porId['nombre_jornada']);
    }

    /**
     * Prueba 4: getByInstructor y getByInstructorAsignado discriminan asignación docente.
     */
    public function testGetByInstructorAsignado(): void
    {
        $progId = $this->crearPrograma();
        $jornadaId = $this->crearJornada();
        $instAsignado = $this->crearUsuario(2);
        $instSinFicha = $this->crearUsuario(2);

        $this->crearFicha(null, $progId, $instAsignado, $jornadaId);

        $fichasAsignado = $this->fichaModel->getByInstructorAsignado($instAsignado);
        $fichasSinAsignar = $this->fichaModel->getByInstructorAsignado($instSinFicha);

        $this->assertNotEmpty($fichasAsignado);
        $this->assertEmpty($fichasSinAsignar);
    }

    /**
     * Prueba 5: Validación de seguridad IDOR (instructorPerteneceAFicha).
     * Retorna true si es líder o tiene sesión en la ficha; false si es ajeno.
     */
    public function testInstructorPerteneceAFicha(): void
    {
        $progId = $this->crearPrograma();
        $jornadaId = $this->crearJornada();
        $instLider = $this->crearUsuario(2);
        $instDocente = $this->crearUsuario(2);
        $instAjeno = $this->crearUsuario(2);

        $fichaId = $this->crearFicha(null, $progId, $instLider, $jornadaId);

        // El docente imparte una sesión formativa en esa ficha
        $this->crearSesion($fichaId, $instDocente);

        $this->assertTrue($this->fichaModel->instructorPerteneceAFicha($instLider, $fichaId));
        $this->assertTrue($this->fichaModel->instructorPerteneceAFicha($instDocente, $fichaId));
        $this->assertFalse($this->fichaModel->instructorPerteneceAFicha($instAjeno, $fichaId));
    }

    /**
     * Prueba 6: Consulta de aprendices matriculados en una ficha (getAprendicesPorFicha).
     */
    public function testGetAprendicesPorFicha(): void
    {
        $progId = $this->crearPrograma();
        $jornadaId = $this->crearJornada();
        $instId = $this->crearUsuario(2);
        $ficha1 = $this->crearFicha(null, $progId, $instId, $jornadaId);
        $ficha2 = $this->crearFicha(null, $progId, $instId, $jornadaId);

        $ap1 = $this->crearUsuario(3, ['Ficha_id' => $ficha1]);
        $ap2 = $this->crearUsuario(3, ['Ficha_id' => $ficha1]);
        $apAjeno = $this->crearUsuario(3, ['Ficha_id' => $ficha2]);

        $aprendicesFicha1 = $this->fichaModel->getAprendicesPorFicha($ficha1);
        $ids = array_map(fn($row) => (int) $row['id'], $aprendicesFicha1);

        $this->assertContains($ap1, $ids);
        $this->assertContains($ap2, $ids);
        $this->assertNotContains($apAjeno, $ids);
    }

    /**
     * Prueba 7: Actualización de datos de una ficha (código, estado).
     */
    public function testActualizarFichaModificaDatos(): void
    {
        $progId = $this->crearPrograma();
        $jornadaId = $this->crearJornada();
        $instId = $this->crearUsuario(2);
        $codigoInicial = mt_rand(1000000, 9999999);
        $codigoNuevo = mt_rand(1000000, 9999999);

        $fichaId = $this->crearFicha($codigoInicial, $progId, $instId, $jornadaId);

        $actualizado = $this->fichaModel->update($fichaId, [
            'codigo'        => $codigoNuevo,
            'Programa_id'   => $progId,
            'jornada_id'    => $jornadaId,
            'instructor_id' => $instId,
            'estado'        => 'Inactivo',
        ]);

        $this->assertTrue($actualizado);

        $fichaModificada = $this->fichaModel->getById($fichaId);
        $this->assertEquals($codigoNuevo, (int) $fichaModificada['codigo']);
        $this->assertEquals('Inactivo', $fichaModificada['estado']);
    }

    /**
     * Prueba 8: Conteo global de fichas en la base de datos (count()).
     */
    public function testContadorDeFichasActivas(): void
    {
        $conteoInicial = $this->fichaModel->count();
        $this->assertIsInt($conteoInicial);

        $progId = $this->crearPrograma();
        $jornadaId = $this->crearJornada();
        $instId = $this->crearUsuario(2);
        $this->crearFicha(null, $progId, $instId, $jornadaId);

        $conteoFinal = $this->fichaModel->count();
        $this->assertGreaterThan($conteoInicial, $conteoFinal);
    }
}
