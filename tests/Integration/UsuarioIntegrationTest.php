<?php
declare(strict_types=1);

namespace Tests\Integration;

require_once __DIR__ . '/IntegrationTestCase.php';

use PDOException;
use Usuario;

/**
 * Pruebas de Integración para el modelo Usuario (models/Usuario.php)
 *
 * Valida operaciones reales contra la base de datos local:
 * - Filtros por Rol (Admin, Instructor, Aprendiz)
 * - Casos de éxito (camino feliz)
 * - Reglas de negocio y autenticación
 * - Manejo de errores (claves duplicadas, inexistentes, estados inactivos)
 */
class UsuarioIntegrationTest extends IntegrationTestCase
{
    private Usuario $usuarioModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->usuarioModel = new Usuario($this->db);
    }

    public function testGetAllSinFiltroRetornaUsuariosDeTodosLosRoles(): void
    {
        $idAdmin = $this->crearUsuario(Usuario::ROL_ADMINISTRADOR);
        $idInst  = $this->crearUsuario(Usuario::ROL_INSTRUCTOR);
        $idApren = $this->crearUsuario(Usuario::ROL_APRENDIZ);

        $usuarios = $this->usuarioModel->getAll();

        $this->assertIsArray($usuarios);
        $ids = array_map(fn($u) => (int)$u['id'], $usuarios);
        $this->assertContains($idAdmin, $ids);
        $this->assertContains($idInst, $ids);
        $this->assertContains($idApren, $ids);
    }

    public function testGetAllFiltradoPorRolAdministrador(): void
    {
        $idAdmin = $this->crearUsuario(Usuario::ROL_ADMINISTRADOR);
        $idInst  = $this->crearUsuario(Usuario::ROL_INSTRUCTOR);

        $admins = $this->usuarioModel->getAll(Usuario::ROL_ADMINISTRADOR);

        $this->assertNotEmpty($admins);
        foreach ($admins as $admin) {
            $this->assertSame(Usuario::ROL_ADMINISTRADOR, (int)$admin['Rol_id']);
        }
        $ids = array_map(fn($u) => (int)$u['id'], $admins);
        $this->assertContains($idAdmin, $ids);
        $this->assertNotContains($idInst, $ids);
    }

    public function testGetAllFiltradoPorRolInstructor(): void
    {
        $idInst = $this->crearUsuario(Usuario::ROL_INSTRUCTOR);
        $idApren = $this->crearUsuario(Usuario::ROL_APRENDIZ);

        $instructores = $this->usuarioModel->getAll(Usuario::ROL_INSTRUCTOR);

        $this->assertNotEmpty($instructores);
        foreach ($instructores as $inst) {
            $this->assertSame(Usuario::ROL_INSTRUCTOR, (int)$inst['Rol_id']);
        }
        $ids = array_map(fn($u) => (int)$u['id'], $instructores);
        $this->assertContains($idInst, $ids);
        $this->assertNotContains($idApren, $ids);
    }

    public function testGetAllFiltradoPorRolAprendiz(): void
    {
        $idApren = $this->crearUsuario(Usuario::ROL_APRENDIZ);
        $idAdmin = $this->crearUsuario(Usuario::ROL_ADMINISTRADOR);

        $aprendices = $this->usuarioModel->getAll(Usuario::ROL_APRENDIZ);

        $this->assertNotEmpty($aprendices);
        foreach ($aprendices as $apren) {
            $this->assertSame(Usuario::ROL_APRENDIZ, (int)$apren['Rol_id']);
        }
        $ids = array_map(fn($u) => (int)$u['id'], $aprendices);
        $this->assertContains($idApren, $ids);
        $this->assertNotContains($idAdmin, $ids);
    }

    public function testGetCountsByRoleCalculaTotalesCorrectos(): void
    {
        $conteoInicial = $this->usuarioModel->getCountsByRole();

        $this->crearUsuario(Usuario::ROL_ADMINISTRADOR);
        $this->crearUsuario(Usuario::ROL_INSTRUCTOR);
        $this->crearUsuario(Usuario::ROL_INSTRUCTOR);
        $this->crearUsuario(Usuario::ROL_APRENDIZ);

        $conteoFinal = $this->usuarioModel->getCountsByRole();

        $this->assertSame($conteoInicial['total'] + 4, $conteoFinal['total']);
        $this->assertSame($conteoInicial['administradores'] + 1, $conteoFinal['administradores']);
        $this->assertSame($conteoInicial['instructores'] + 2, $conteoFinal['instructores']);
        $this->assertSame($conteoInicial['aprendices'] + 1, $conteoFinal['aprendices']);
    }

    public function testGetByIdRetornaUsuarioConRolSiExiste(): void
    {
        $id = $this->crearUsuario(Usuario::ROL_INSTRUCTOR, ['nombre' => 'Carlos', 'apellido' => 'Perez']);

        $usuario = $this->usuarioModel->getById($id);

        $this->assertNotNull($usuario);
        $this->assertSame($id, (int)$usuario['id']);
        $this->assertSame('Carlos', $usuario['nombre']);
        $this->assertSame('Perez', $usuario['apellido']);
        $this->assertArrayHasKey('nombre_rol', $usuario);
        $this->assertNotEmpty($usuario['nombre_rol']);
    }

    public function testGetByIdRetornaNullParaUsuarioInexistente(): void
    {
        $usuario = $this->usuarioModel->getById(9999999);
        $this->assertNull($usuario);
    }

    public function testGetByDocumentoYEmailRetornaUsuarioCorrecto(): void
    {
        $doc = $this->generarIdentificacion();
        $email = 'busqueda_' . uniqid() . '@sena.edu.co';
        $id = $this->crearUsuario(Usuario::ROL_ADMINISTRADOR, [
            'identificacion' => $doc,
            'email'          => $email,
        ]);

        $porDoc = $this->usuarioModel->getByDocumento($doc);
        $porEmail = $this->usuarioModel->getByCorreo($email);

        $this->assertNotNull($porDoc);
        $this->assertSame($id, (int)$porDoc['id']);

        $this->assertNotNull($porEmail);
        $this->assertSame($id, (int)$porEmail['id']);

        $this->assertNull($this->usuarioModel->getByDocumento('9999999999'));
        $this->assertNull($this->usuarioModel->getByCorreo('noexiste@sena.edu.co'));
    }

    public function testCreateCaminoFelizGuardaUsuarioConPasswordHash(): void
    {
        $doc = $this->generarIdentificacion();
        $email = 'nuevo_' . uniqid() . '@sena.edu.co';
        $data = [
            'Rol_id'         => Usuario::ROL_INSTRUCTOR,
            'identificacion' => $doc,
            'nombre'         => 'Laura',
            'apellido'       => 'Gomez',
            'email'          => $email,
            'password'       => 'ClaveSecreta123',
            'estado'         => 'Activo',
        ];

        $resultado = $this->usuarioModel->create($data);
        $this->assertTrue($resultado);

        $creado = $this->usuarioModel->getByDocumento($doc);
        $this->assertNotNull($creado);
        $this->assertSame('Laura', $creado['nombre']);
        $this->assertSame('Gomez', $creado['apellido']);
        $this->assertSame($email, $creado['email']);
        // Verificar que la clave se haya guardado con hash BCRYPT
        $this->assertTrue(password_verify('ClaveSecreta123', $creado['password']));
    }

    public function testCreateFallaConExcepcionCuandoDocumentoEstaDuplicado(): void
    {
        $docDuplicado = $this->generarIdentificacion();
        $this->crearUsuario(Usuario::ROL_APRENDIZ, ['identificacion' => $docDuplicado]);

        $data = [
            'Rol_id'         => Usuario::ROL_APRENDIZ,
            'identificacion' => $docDuplicado,
            'nombre'         => 'Duplicado',
            'apellido'       => 'Prueba',
            'email'          => 'dup_' . uniqid() . '@sena.edu.co',
            'password'       => '12345678',
        ];

        $this->expectException(PDOException::class);
        $this->usuarioModel->create($data);
    }

    public function testUpdateModificaCamposCorrectamente(): void
    {
        $id = $this->crearUsuario(Usuario::ROL_INSTRUCTOR, ['nombre' => 'Original', 'apellido' => 'Antiguo']);

        $nuevoDoc = $this->generarIdentificacion();
        $nuevoEmail = 'modificado_' . uniqid() . '@sena.edu.co';

        $datosActualizar = [
            'Rol_id'         => Usuario::ROL_INSTRUCTOR,
            'identificacion' => $nuevoDoc,
            'nombre'         => 'NombreActualizado',
            'apellido'       => 'ApellidoActualizado',
            'email'          => $nuevoEmail,
            'estado'         => 'Inactivo',
        ];

        $res = $this->usuarioModel->update($id, $datosActualizar);
        $this->assertTrue($res);

        $actualizado = $this->usuarioModel->getById($id);
        $this->assertSame('NombreActualizado', $actualizado['nombre']);
        $this->assertSame('ApellidoActualizado', $actualizado['apellido']);
        $this->assertSame($nuevoEmail, $actualizado['email']);
        $this->assertSame('Inactivo', $actualizado['estado']);
    }

    public function testUpdateActualizaPasswordHash(): void
    {
        $id = $this->crearUsuario(Usuario::ROL_ADMINISTRADOR, ['password' => 'Antigua123']);

        $res = $this->usuarioModel->update($id, ['password' => 'NuevaClaveSegura456']);
        $this->assertTrue($res);

        $usuario = $this->usuarioModel->getById($id);
        $this->assertTrue(password_verify('NuevaClaveSegura456', $usuario['password']));
        $this->assertFalse(password_verify('Antigua123', $usuario['password']));
    }

    public function testAuthenticateCaminoFelizYCasosNegativos(): void
    {
        $doc = $this->generarIdentificacion();
        $this->crearUsuario(Usuario::ROL_INSTRUCTOR, [
            'identificacion' => $doc,
            'password'       => 'Correcta123',
            'estado'         => 'Activo',
        ]);

        // Caso 1: Éxito con credenciales correctas
        $autenticado = $this->usuarioModel->authenticate($doc, 'Correcta123');
        $this->assertNotNull($autenticado);
        $this->assertSame($doc, (string)$autenticado['identificacion']);

        // Caso 2: Clave errónea
        $falloClave = $this->usuarioModel->authenticate($doc, 'ClaveIncorrecta');
        $this->assertNull($falloClave);

        // Caso 3: Usuario inexistente
        $falloUsuario = $this->usuarioModel->authenticate('9999999999', 'Correcta123');
        $this->assertNull($falloUsuario);

        // Caso 4: Usuario inactivo (debe denegar acceso)
        $docInactivo = $this->generarIdentificacion();
        $this->crearUsuario(Usuario::ROL_INSTRUCTOR, [
            'identificacion' => $docInactivo,
            'password'       => 'Correcta123',
            'estado'         => 'Inactivo',
        ]);
        $falloInactivo = $this->usuarioModel->authenticate($docInactivo, 'Correcta123');
        $this->assertNull($falloInactivo);
    }

    public function testDeleteEliminaUsuario(): void
    {
        $id = $this->crearUsuario(Usuario::ROL_APRENDIZ);

        $res = $this->usuarioModel->delete($id);
        $this->assertTrue($res);

        $this->assertNull($this->usuarioModel->getById($id));
    }
}
