<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname(__DIR__) . '/Support/AccionControlador.php';
require_once dirname(__DIR__) . '/Support/ControladorTestCase.php';

/**
 * UsuarioController según el rol: el instructor ve su directorio,
 * el administrador el listado, y el aprendiz no entra.
 * guardar() e index() se prueban una vez por rol, más datos nulos o inválidos.
 *
 * La vista de una ficha con integrantes usa GROUP_CONCAT de MySQL
 * (getInstructoresPorFicha). Esa consulta no corre en SQLite, así que
 * aquí se cubre el directorio vacío, el rechazo anti-IDOR y el listado global.
 */
class UsuarioControllerTest extends ControladorTestCase
{
    /** @return array<string, array{0: array<string, mixed>, 1: string, 2: ?string}> */
    public static function quienNoEsAdministrador(): array
    {
        return [
            'sin sesion'     => [[], 'auth/login', null],
            'instructor'     => [self::sesion(2, 2), 'usuarios', 'Acceso denegado'],
            'aprendiz'       => [self::sesion(3, 3), 'dashboard', 'Acceso denegado'],
            'rol incorrecto' => [self::sesion(9, 9), 'dashboard', 'Acceso denegado'],
        ];
    }

    /** @return array<string, array{0: int, 1: string}> */
    public static function roles(): array
    {
        return [
            'administrador' => [Usuario::ROL_ADMINISTRADOR, 'elena.nueva@sena.edu.co'],
            'instructor'    => [Usuario::ROL_INSTRUCTOR, 'marco.nuevo@sena.edu.co'],
            'aprendiz'      => [Usuario::ROL_APRENDIZ, 'laura.nueva@sena.edu.co'],
        ];
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function tokensInvalidos(): array
    {
        return [
            'ausente'    => [[]],
            'nulo'       => [['csrf_token' => null]],
            'vacio'      => [['csrf_token' => '']],
            'incorrecto' => [['csrf_token' => 'otro-token']],
        ];
    }

    /** @return array<string, array{0: array<string, mixed>, 1: list<string>, 2: list<string>}> */
    public static function filtrosDeRol(): array
    {
        return [
            'todos' => [
                [],
                ['elena.vargas@sena.edu.co', 'marco.ruiz@sena.edu.co', 'laura.pena@sena.edu.co'],
                [],
            ],
            'rol vacio' => [
                ['rol' => ''],
                ['elena.vargas@sena.edu.co', 'marco.ruiz@sena.edu.co', 'laura.pena@sena.edu.co'],
                [],
            ],
            'rol nulo' => [
                ['rol' => null],
                ['elena.vargas@sena.edu.co', 'marco.ruiz@sena.edu.co', 'laura.pena@sena.edu.co'],
                [],
            ],
            'rol cero' => [
                ['rol' => '0'],
                ['elena.vargas@sena.edu.co', 'marco.ruiz@sena.edu.co', 'laura.pena@sena.edu.co'],
                [],
            ],
            'rol fuera de rango' => [
                ['rol' => '4'],
                ['elena.vargas@sena.edu.co', 'marco.ruiz@sena.edu.co', 'laura.pena@sena.edu.co'],
                [],
            ],
            'rol no numerico' => [
                ['rol' => 'abc'],
                ['elena.vargas@sena.edu.co', 'marco.ruiz@sena.edu.co', 'laura.pena@sena.edu.co'],
                [],
            ],
            'solo administradores' => [
                ['rol' => '1'],
                ['elena.vargas@sena.edu.co'],
                ['marco.ruiz@sena.edu.co', 'laura.pena@sena.edu.co'],
            ],
            'solo instructores' => [
                ['rol' => '2'],
                ['marco.ruiz@sena.edu.co'],
                ['elena.vargas@sena.edu.co', 'laura.pena@sena.edu.co'],
            ],
            'solo aprendices' => [
                ['rol' => '3'],
                ['laura.pena@sena.edu.co'],
                ['elena.vargas@sena.edu.co', 'marco.ruiz@sena.edu.co'],
            ],
        ];
    }

    public function testIndexSinSesionRedirigeAlLogin(): void
    {
        $resultado = $this->accion('UsuarioController', 'index');

        $this->assertRedirige($resultado, 'auth/login');
        $this->assertSinEscritura($resultado);
    }

    public function testIndexInstructorSinFichasPintaSuDirectorio(): void
    {
        $resultado = $this->accion(
            'UsuarioController',
            'index',
            self::sesion(Usuario::ROL_INSTRUCTOR, 2),
            [],
            [],
            'GET',
            self::semillaUsuarios()
        );

        $this->assertPinta($resultado, 'Directorio de Fichas Asignadas');
        $this->assertStringContainsString('Mis Fichas Asignadas', (string) $resultado['body']);
        $this->assertSinEscritura($resultado);
    }

    public function testIndexInstructorRechazaFichaQueNoLePertenece(): void
    {
        $resultado = $this->accion(
            'UsuarioController',
            'index',
            self::sesion(Usuario::ROL_INSTRUCTOR, 2),
            ['ficha_id' => '99'],
            [],
            'GET',
            self::semillaFichaDeMarco()
        );

        $this->assertRedirige($resultado, 'usuarios');
        $this->assertFlash($resultado, 'error', 'no estás asignado a esa ficha');
        $this->assertSinEscritura($resultado);
    }

    public function testIndexInstructorIgnoraFichaNulaCeroONoNumericaSiNoTieneFichas(): void
    {
        foreach ([null, '', '0', 'abc'] as $fichaId) {
            $resultado = $this->accion(
                'UsuarioController',
                'index',
                self::sesion(Usuario::ROL_INSTRUCTOR, 2),
                ['ficha_id' => $fichaId],
                [],
                'GET',
                self::semillaUsuarios()
            );

            $this->assertPinta($resultado, 'Directorio de Fichas Asignadas');
            $this->assertStringContainsString('Mis Fichas Asignadas', (string) $resultado['body']);
        }
    }

    public function testIndexAprendizNoEntraAlListado(): void
    {
        $resultado = $this->accion('UsuarioController', 'index', self::sesion(Usuario::ROL_APRENDIZ, 3));

        $this->assertRedirige($resultado, 'dashboard');
        $this->assertSinFlash($resultado);
        $this->assertSinEscritura($resultado);
    }

    public function testIndexRolIncorrectoNoEntraAlListado(): void
    {
        $resultado = $this->accion('UsuarioController', 'index', self::sesion(9, 9));

        $this->assertRedirige($resultado, 'dashboard');
        $this->assertSinFlash($resultado);
    }

    #[DataProvider('filtrosDeRol')]
    public function testIndexAdministradorFiltraPorRol(array $get, array $visibles, array $ocultos): void
    {
        $resultado = $this->accion(
            'UsuarioController',
            'index',
            self::sesion(Usuario::ROL_ADMINISTRADOR, 1),
            $get,
            [],
            'GET',
            self::semillaUsuarios()
        );

        $this->assertPinta($resultado, 'Gestión de Usuarios');
        foreach ($visibles as $email) {
            $this->assertStringContainsString($email, (string) $resultado['body']);
        }
        foreach ($ocultos as $email) {
            $this->assertStringNotContainsString($email, (string) $resultado['body']);
        }
    }

    public function testIndexAdministradorConFichaInexistenteSigueEnElListadoGlobal(): void
    {
        $resultado = $this->accion(
            'UsuarioController',
            'index',
            self::sesion(Usuario::ROL_ADMINISTRADOR, 1),
            ['ficha_id' => '999'],
            [],
            'GET',
            self::semillaUsuarios()
        );

        $this->assertPinta($resultado, 'Gestión de Usuarios');
        $this->assertStringNotContainsString('Integrantes Ficha', (string) $resultado['body']);
    }

    #[DataProvider('quienNoEsAdministrador')]
    public function testCrearSoloLoHaceElAdministrador(array $sesion, string $destino, ?string $flash): void
    {
        $resultado = $this->accion('UsuarioController', 'crear', $sesion);

        $this->assertRedirige($resultado, $destino);
        if ($flash === null) {
            $this->assertSinFlash($resultado);
        } else {
            $this->assertFlash($resultado, 'error', $flash);
        }
        $this->assertSinEscritura($resultado);
    }

    public function testCrearAdministradorPintaElFormulario(): void
    {
        $resultado = $this->accion(
            'UsuarioController',
            'crear',
            self::sesion(1),
            [],
            [],
            'GET',
            self::semillaFichaDeMarco()
        );

        $this->assertPinta($resultado, 'Registrar Nuevo Usuario');
        $this->assertStringContainsString('csrf_token', (string) $resultado['body']);
        $this->assertStringContainsString('3064749', (string) $resultado['body']);
    }

    #[DataProvider('quienNoEsAdministrador')]
    public function testEditarSoloLoHaceElAdministrador(array $sesion, string $destino, ?string $flash): void
    {
        $resultado = $this->accion('UsuarioController', 'editar', $sesion, ['id' => '2']);

        $this->assertRedirige($resultado, $destino);
        if ($flash === null) {
            $this->assertSinFlash($resultado);
        } else {
            $this->assertFlash($resultado, 'error', $flash);
        }
        $this->assertSinEscritura($resultado);
    }

    public function testEditarSinIdOConIdInexistenteAvisa(): void
    {
        foreach ([[], ['id' => null], ['id' => ''], ['id' => '0'], ['id' => 'abc'], ['id' => '999']] as $get) {
            $resultado = $this->accion(
                'UsuarioController',
                'editar',
                self::sesion(1),
                $get,
                [],
                'GET',
                self::semillaUsuarios()
            );

            $this->assertRedirige($resultado, 'usuarios');
            $this->assertFlash($resultado, 'error', 'Usuario no encontrado.');
            $this->assertSinEscritura($resultado);
        }
    }

    public function testEditarUsuarioExistentePintaSusDatos(): void
    {
        $resultado = $this->accion(
            'UsuarioController',
            'editar',
            self::sesion(1),
            ['id' => '2'],
            [],
            'GET',
            self::semillaUsuarios()
        );

        $this->assertPinta($resultado, 'Editar Usuario');
        $this->assertStringContainsString('marco.ruiz@sena.edu.co', (string) $resultado['body']);
        $this->assertStringContainsString('1002002002', (string) $resultado['body']);
    }

    public function testGuardarSiNoEsPostRegresaAlListado(): void
    {
        $resultado = $this->accion('UsuarioController', 'guardar', self::sesion(1));

        $this->assertRedirige($resultado, 'usuarios');
        $this->assertSinFlash($resultado);
        $this->assertSinEscritura($resultado);
    }

    #[DataProvider('tokensInvalidos')]
    public function testGuardarRechazaTokenNuloOIncorrecto(array $post): void
    {
        $resultado = $this->accion('UsuarioController', 'guardar', self::sesion(1), [], $post, 'POST');

        $this->assertRedirige($resultado, 'usuarios');
        $this->assertFlash($resultado, 'error', 'Token de seguridad inválido.');
        $this->assertSinEscritura($resultado);
    }

    public function testGuardarCreacionConCamposVaciosNoInserta(): void
    {
        $resultado = $this->accion(
            'UsuarioController',
            'guardar',
            self::sesion(1),
            [],
            [
                'csrf_token'    => 'token-valido',
                'id_usuario'    => null,
                'id_rol'        => '1',
                'num_documento' => '   ',
                'nombre'        => '',
                'apellido'      => null,
                'correo'        => '',
                'password'      => '',
            ],
            'POST'
        );

        $this->assertRedirige($resultado, 'usuarios/crear');
        $this->assertFlash($resultado, 'error', 'número de documento');
        $this->assertFlash($resultado, 'error', 'nombre');
        $this->assertFlash($resultado, 'error', 'apellido');
        $this->assertFlash($resultado, 'error', 'correo');
        $this->assertFlash($resultado, 'error', 'contraseña');
        $this->assertSinEscritura($resultado);
    }

    public function testGuardarCreacionConCorreoInvalidoYContrasenaCorta(): void
    {
        $resultado = $this->accion(
            'UsuarioController',
            'guardar',
            self::sesion(1),
            [],
            $this->postUsuario(Usuario::ROL_INSTRUCTOR, 0, [
                'correo'   => 'correo-malo',
                'password' => '12345',
            ]),
            'POST'
        );

        $this->assertRedirige($resultado, 'usuarios/crear');
        $this->assertFlash($resultado, 'error', 'no tiene un formato válido');
        $this->assertFlash($resultado, 'error', 'al menos 6 caracteres');
        $this->assertSinEscritura($resultado);
    }

    public function testGuardarAprendizSinFichaNoSeCrea(): void
    {
        $resultado = $this->accion(
            'UsuarioController',
            'guardar',
            self::sesion(1),
            [],
            $this->postUsuario(Usuario::ROL_APRENDIZ, 0, ['Ficha_id' => '']),
            'POST'
        );

        $this->assertRedirige($resultado, 'usuarios/crear');
        $this->assertFlash($resultado, 'error', 'Ficha de Formación');
        $this->assertSinEscritura($resultado);
    }

    public function testGuardarSinRolExigeFichaPorqueAsumeAprendiz(): void
    {
        $post = $this->postUsuario(Usuario::ROL_INSTRUCTOR);
        unset($post['id_rol'], $post['Ficha_id']);

        $resultado = $this->accion('UsuarioController', 'guardar', self::sesion(1), [], $post, 'POST');

        $this->assertRedirige($resultado, 'usuarios/crear');
        $this->assertFlash($resultado, 'error', 'Ficha de Formación');
        $this->assertSinEscritura($resultado);
    }

    #[DataProvider('roles')]
    public function testGuardarCreaUnUsuarioPorRol(int $rol, string $email): void
    {
        $resultado = $this->accion(
            'UsuarioController',
            'guardar',
            self::sesion(1),
            [],
            $this->postUsuario($rol, 0, ['correo' => $email]),
            'POST',
            self::semillaFichaDeMarco()
        );

        $this->assertRedirige($resultado, 'usuarios');
        $this->assertFlash($resultado, 'success', 'Usuario creado correctamente.');
        $params = $this->parametrosQueContienen($resultado, 'INSERT INTO usuario');
        $this->assertSame($rol, (int) ($params[':Rol_id'] ?? 0));
        $this->assertSame($email, (string) ($params[':email'] ?? ''));
        $this->assertSame('Activo', (string) ($params[':estado'] ?? ''));
        $this->assertNotSame('Secreta1', (string) ($params[':password'] ?? ''));
        $this->assertStringStartsWith('$2y$', (string) ($params[':password'] ?? ''));

        if ($rol === Usuario::ROL_APRENDIZ) {
            $this->assertSame(10, (int) ($params[':Ficha_id'] ?? 0));
        } else {
            $this->assertArrayHasKey(':Ficha_id', $params);
            $this->assertNull($params[':Ficha_id']);
        }
        $this->assertNull($params[':codigo_llavero']);
    }

    public function testGuardarIdNoNumericoSeTrataComoCreacion(): void
    {
        $resultado = $this->accion(
            'UsuarioController',
            'guardar',
            self::sesion(1),
            [],
            $this->postUsuario(Usuario::ROL_INSTRUCTOR, 0, ['id_usuario' => 'abc']),
            'POST'
        );

        $this->assertFlash($resultado, 'success', 'Usuario creado correctamente.');
        $this->parametrosQueContienen($resultado, 'INSERT INTO usuario');
    }

    #[DataProvider('roles')]
    public function testGuardarActualizaCadaRolSinExigirContrasena(int $rol, string $email): void
    {
        $id = $rol;
        $resultado = $this->accion(
            'UsuarioController',
            'guardar',
            self::sesion(1),
            [],
            $this->postUsuario($rol, $id, [
                'correo'        => $email,
                'num_documento' => '100' . $rol . '00100' . $rol,
                'password'      => '',
                'nombre'        => 'Editado',
            ]),
            'POST',
            self::semillaFichaDeMarco()
        );

        $this->assertRedirige($resultado, 'usuarios');
        $this->assertFlash($resultado, 'success', 'Usuario actualizado correctamente.');
        $params = $this->parametrosQueContienen($resultado, 'UPDATE usuario');
        $this->assertSame($id, (int) ($params[':id'] ?? 0));
        $this->assertSame('Editado', (string) ($params[':nombre'] ?? ''));
        $this->assertArrayNotHasKey(':password', $params);
    }

    public function testGuardarActualizacionRechazaContrasenaCorta(): void
    {
        $resultado = $this->accion(
            'UsuarioController',
            'guardar',
            self::sesion(1),
            [],
            $this->postUsuario(Usuario::ROL_INSTRUCTOR, 2, ['password' => '123']),
            'POST',
            self::semillaUsuarios()
        );

        $this->assertRedirige($resultado, 'usuarios/editar', ['id' => '2']);
        $this->assertFlash($resultado, 'error', 'al menos 6 caracteres');
        $this->assertSinEscritura($resultado);
    }

    public function testGuardarCreacionDuplicadaNoRevelaElSql(): void
    {
        $resultado = $this->accion(
            'UsuarioController',
            'guardar',
            self::sesion(1),
            [],
            $this->postUsuario(Usuario::ROL_INSTRUCTOR),
            'POST',
            '',
            'INSERT INTO usuario',
            'SQLSTATE[23000]: Duplicate entry'
        );

        $this->assertRedirige($resultado, 'usuarios');
        $this->assertFlash($resultado, 'error', 'ya existe');
        $this->assertStringNotContainsString('SQLSTATE', (string) ($resultado['session']['flash']['message'] ?? ''));
    }

    public function testGuardarErrorDeBaseNoRevelaElMensaje(): void
    {
        $resultado = $this->accion(
            'UsuarioController',
            'guardar',
            self::sesion(1),
            [],
            $this->postUsuario(Usuario::ROL_ADMINISTRADOR),
            'POST',
            '',
            'INSERT INTO usuario',
            'server has gone away'
        );

        $this->assertRedirige($resultado, 'usuarios');
        $this->assertFlash($resultado, 'error', 'Ocurrió un error al guardar el usuario');
        $this->assertStringNotContainsString('gone away', (string) ($resultado['session']['flash']['message'] ?? ''));
    }

    #[DataProvider('quienNoEsAdministrador')]
    public function testEliminarSoloLoHaceElAdministrador(array $sesion, string $destino, ?string $flash): void
    {
        $resultado = $this->accion('UsuarioController', 'eliminar', $sesion, ['id' => '2']);

        $this->assertRedirige($resultado, $destino);
        if ($flash === null) {
            $this->assertSinFlash($resultado);
        } else {
            $this->assertFlash($resultado, 'error', $flash);
        }
        $this->assertSinEscritura($resultado);
    }

    public function testEliminarElPropioUsuarioSeNiega(): void
    {
        $resultado = $this->accion(
            'UsuarioController',
            'eliminar',
            self::sesion(1, 1),
            ['id' => '1abc'],
            [],
            'GET',
            self::semillaUsuarios()
        );

        $this->assertRedirige($resultado, 'usuarios');
        $this->assertFlash($resultado, 'error', 'No puedes eliminar tu propio usuario.');
        $this->assertSinEscritura($resultado);
    }

    public function testEliminarOtroUsuarioLoBorra(): void
    {
        $resultado = $this->accion(
            'UsuarioController',
            'eliminar',
            self::sesion(1, 1),
            ['id' => '2'],
            [],
            'GET',
            self::semillaUsuarios()
        );

        $this->assertRedirige($resultado, 'usuarios');
        $this->assertFlash($resultado, 'success', 'Usuario eliminado correctamente.');
        $params = $this->parametrosQueContienen($resultado, 'DELETE FROM usuario');
        $this->assertSame(2, (int) ($params[':id'] ?? 0));
    }

    public function testEliminarIdNuloConfirmaAunqueNoHayaFila(): void
    {
        $resultado = $this->accion(
            'UsuarioController',
            'eliminar',
            self::sesion(1, 1),
            ['id' => null],
            [],
            'GET',
            self::semillaUsuarios()
        );

        $this->assertFlash($resultado, 'success', 'Usuario eliminado correctamente.');
        $params = $this->parametrosQueContienen($resultado, 'DELETE FROM usuario');
        $this->assertSame(0, (int) ($params[':id'] ?? -1));
    }

    public function testEliminarConRegistrosAsociadosInformaElBloqueo(): void
    {
        $resultado = $this->accion(
            'UsuarioController',
            'eliminar',
            self::sesion(1, 1),
            ['id' => '3'],
            [],
            'GET',
            self::semillaUsuarios(),
            'DELETE FROM usuario',
            'FOREIGN KEY constraint failed'
        );

        $this->assertRedirige($resultado, 'usuarios');
        $this->assertFlash($resultado, 'error', 'registros asociados');
        $this->assertStringNotContainsString('FOREIGN KEY', (string) ($resultado['session']['flash']['message'] ?? ''));
    }

    /** @param array<string, mixed> $extra */
    private function postUsuario(int $rol, int $id = 0, array $extra = []): array
    {
        $datos = [
            'csrf_token'     => 'token-valido',
            'id_usuario'     => (string) $id,
            'id_rol'         => (string) $rol,
            'num_documento'  => '800' . $rol . $id,
            'nombre'         => 'Nuevo',
            'apellido'       => 'Usuario',
            'correo'         => "nuevo{$rol}{$id}@sena.edu.co",
            'password'       => 'Secreta1',
            'estado'         => 'Activo',
            'codigo_llavero' => '',
        ];
        if ($rol === Usuario::ROL_APRENDIZ) {
            $datos['Ficha_id'] = '10';
        }

        return array_merge($datos, $extra);
    }
}
