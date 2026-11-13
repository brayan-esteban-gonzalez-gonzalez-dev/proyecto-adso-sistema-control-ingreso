<?php
declare(strict_types=1);

namespace Tests\Integration;

require_once __DIR__ . '/IntegrationTestCase.php';

use Aprendiz;
use Usuario;

/**
 * Pruebas de Integración para el modelo Aprendiz (models/Aprendiz.php)
 *
 * Valida el comportamiento del wrapper Aprendiz sobre la tabla `usuario`:
 * - Exclusividad de Rol_id = 3 (Aprendices)
 * - Alias de compatibilidad (id_aprendiz, num_documento, codigo_ficha)
 * - Búsqueda por RFID / Llavero (activos vs inactivos)
 * - Agrupación por Ficha
 * - CRUD con restricciones de rol
 */
class AprendizIntegrationTest extends IntegrationTestCase
{
    private Aprendiz $aprendizModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->aprendizModel = new Aprendiz($this->db);
    }

    public function testGetAllRetornaUnicamenteAprendicesConAlias(): void
    {
        $fichaId = $this->crearFicha();
        $idAprendiz = $this->crearUsuario(Usuario::ROL_APRENDIZ, ['Ficha_id' => $fichaId]);
        $idInstructor = $this->crearUsuario(Usuario::ROL_INSTRUCTOR);
        $idAdmin = $this->crearUsuario(Usuario::ROL_ADMINISTRADOR);

        $aprendices = $this->aprendizModel->getAll();

        $this->assertIsArray($aprendices);
        $ids = array_map(fn($a) => (int)$a['id'], $aprendices);

        $this->assertContains($idAprendiz, $ids);
        $this->assertNotContains($idInstructor, $ids);
        $this->assertNotContains($idAdmin, $ids);

        // Validar alias esperados
        $encontrado = null;
        foreach ($aprendices as $a) {
            if ((int)$a['id'] === $idAprendiz) {
                $encontrado = $a;
                break;
            }
        }
        $this->assertNotNull($encontrado);
        $this->assertArrayHasKey('num_documento', $encontrado);
        $this->assertArrayHasKey('correo', $encontrado);
        $this->assertArrayHasKey('codigo_ficha', $encontrado);
        $this->assertArrayHasKey('nombre_programa', $encontrado);
    }

    public function testGetByIdRetornaAprendizYRechazaUsuariosDeOtroRol(): void
    {
        $idAprendiz = $this->crearUsuario(Usuario::ROL_APRENDIZ, ['nombre' => 'Ana', 'apellido' => 'Rios']);
        $idInstructor = $this->crearUsuario(Usuario::ROL_INSTRUCTOR);

        // Caso 1: Aprendiz válido
        $aprendiz = $this->aprendizModel->getById($idAprendiz);
        $this->assertNotNull($aprendiz);
        $this->assertSame($idAprendiz, (int)$aprendiz['id']);
        $this->assertSame($idAprendiz, (int)$aprendiz['id_aprendiz']);
        $this->assertSame('Ana', $aprendiz['nombre']);

        // Caso 2: getByUsuario retorna el mismo resultado
        $porUsuario = $this->aprendizModel->getByUsuario($idAprendiz);
        $this->assertEquals($aprendiz, $porUsuario);

        // Caso 3: Usuario que no es Aprendiz (Instructor) debe retornar null
        $noEsAprendiz = $this->aprendizModel->getById($idInstructor);
        $this->assertNull($noEsAprendiz);

        // Caso 4: ID inexistente
        $this->assertNull($this->aprendizModel->getById(9999999));
    }

    public function testGetByRfidRetornaSoloAprendicesActivos(): void
    {
        $rfidActivo = 'RFID_ACT_' . uniqid();
        $idActivo = $this->crearUsuario(Usuario::ROL_APRENDIZ, [
            'codigo_llavero' => $rfidActivo,
            'estado'         => 'Activo',
        ]);

        $rfidInactivo = 'RFID_INACT_' . uniqid();
        $this->crearUsuario(Usuario::ROL_APRENDIZ, [
            'codigo_llavero' => $rfidInactivo,
            'estado'         => 'Inactivo',
        ]);

        // Caso 1: Aprendiz activo con RFID válido
        $resActivo = $this->aprendizModel->getByRfid($rfidActivo);
        $this->assertNotNull($resActivo);
        $this->assertSame($idActivo, (int)$resActivo['id']);
        $this->assertSame($rfidActivo, $resActivo['codigo_llavero']);

        // Caso 2: Aprendiz inactivo con RFID debe retornar null
        $resInactivo = $this->aprendizModel->getByRfid($rfidInactivo);
        $this->assertNull($resInactivo);

        // Caso 3: RFID inexistente
        $this->assertNull($this->aprendizModel->getByRfid('RFID_FANTASMA_999'));
    }

    public function testGetByFichaFiltraAprendicesPorFicha(): void
    {
        $fichaA = $this->crearFicha();
        $fichaB = $this->crearFicha();

        $aprenA1 = $this->crearUsuario(Usuario::ROL_APRENDIZ, ['Ficha_id' => $fichaA]);
        $aprenA2 = $this->crearUsuario(Usuario::ROL_APRENDIZ, ['Ficha_id' => $fichaA]);
        $aprenB1 = $this->crearUsuario(Usuario::ROL_APRENDIZ, ['Ficha_id' => $fichaB]);

        $aprendicesFichaA = $this->aprendizModel->getByFicha($fichaA);
        $idsA = array_map(fn($a) => (int)$a['id'], $aprendicesFichaA);

        $this->assertContains($aprenA1, $idsA);
        $this->assertContains($aprenA2, $idsA);
        $this->assertNotContains($aprenB1, $idsA);

        // Caso Ficha sin aprendices
        $fichaVacia = $this->crearFicha();
        $this->assertEmpty($this->aprendizModel->getByFicha($fichaVacia));
    }

    public function testCreateGuardaAprendizConRol3YHashPassword(): void
    {
        $fichaId = $this->crearFicha();
        $doc = $this->generarIdentificacion();
        $llavero = 'LLAVERO_' . uniqid();

        $data = [
            'identificacion' => $doc,
            'nombre'         => 'Mateo',
            'apellido'       => 'Sanchez',
            'email'          => 'mateo_' . uniqid() . '@sena.edu.co',
            'password'       => 'ClaveAprendiz123',
            'Ficha_id'       => $fichaId,
            'codigo_llavero' => $llavero,
            'estado'         => 'Activo',
        ];

        $res = $this->aprendizModel->create($data);
        $this->assertTrue($res);

        $creado = $this->aprendizModel->getByRfid($llavero);
        $this->assertNotNull($creado);
        $this->assertSame('Mateo', $creado['nombre']);
        $this->assertSame(Usuario::ROL_APRENDIZ, (int)$creado['Rol_id']);
        $this->assertSame($fichaId, (int)$creado['Ficha_id']);
        $this->assertTrue(password_verify('ClaveAprendiz123', $creado['password']));
    }

    public function testUpdateModificaFichaYLlaveroSinAfectarOtrosRoles(): void
    {
        $ficha1 = $this->crearFicha();
        $ficha2 = $this->crearFicha();

        $idAprendiz = $this->crearUsuario(Usuario::ROL_APRENDIZ, [
            'Ficha_id'       => $ficha1,
            'codigo_llavero' => 'RFID_VIEJO_' . uniqid(),
        ]);

        $nuevoLlavero = 'RFID_NUEVO_' . uniqid();
        $res = $this->aprendizModel->update($idAprendiz, [
            'Ficha_id'       => $ficha2,
            'codigo_llavero' => $nuevoLlavero,
        ]);
        $this->assertTrue($res);

        $actualizado = $this->aprendizModel->getById($idAprendiz);
        $this->assertSame($ficha2, (int)$actualizado['Ficha_id']);
        $this->assertSame($nuevoLlavero, $actualizado['codigo_llavero']);

        // Caso: No debe permitir actualizar un Instructor a través del modelo Aprendiz
        $idInstructor = $this->crearUsuario(Usuario::ROL_INSTRUCTOR, ['Ficha_id' => null]);
        $this->aprendizModel->update($idInstructor, ['Ficha_id' => $ficha2]);

        $stmt = $this->db->prepare("SELECT Ficha_id FROM usuario WHERE id = :id");
        $stmt->execute([':id' => $idInstructor]);
        $this->assertNull($stmt->fetchColumn());
    }

    public function testDeleteEliminaSoloAprendices(): void
    {
        $idAprendiz = $this->crearUsuario(Usuario::ROL_APRENDIZ);
        $idAdmin = $this->crearUsuario(Usuario::ROL_ADMINISTRADOR);

        // Eliminar aprendiz debe funcionar
        $res = $this->aprendizModel->delete($idAprendiz);
        $this->assertTrue($res);
        $this->assertNull($this->aprendizModel->getById($idAprendiz));

        // Intentar eliminar un Admin con Aprendiz::delete() no debe eliminarlo
        $this->aprendizModel->delete($idAdmin);
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM usuario WHERE id = :id");
        $stmt->execute([':id' => $idAdmin]);
        $this->assertSame(1, (int)$stmt->fetchColumn());
    }

    public function testCountCuentaUnicamenteAprendicesActivos(): void
    {
        $inicial = $this->aprendizModel->count();

        // 2 activos, 1 inactivo, 1 instructor
        $this->crearUsuario(Usuario::ROL_APRENDIZ, ['estado' => 'Activo']);
        $this->crearUsuario(Usuario::ROL_APRENDIZ, ['estado' => 'Activo']);
        $this->crearUsuario(Usuario::ROL_APRENDIZ, ['estado' => 'Inactivo']);
        $this->crearUsuario(Usuario::ROL_INSTRUCTOR, ['estado' => 'Activo']);

        $final = $this->aprendizModel->count();

        $this->assertSame($inicial + 2, $final);
    }
}

