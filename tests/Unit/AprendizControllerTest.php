<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;

require_once dirname(__DIR__) . '/Support/AccionControlador.php';
require_once dirname(__DIR__) . '/Support/ControladorTestCase.php';

/**
 * AprendizController no devuelve un valor: según el rol redirige,
 * y al guardar acepta o rechaza lo que llegó en el formulario.
 * Cada método se prueba con sesión nula, rol incorrecto y datos vacíos.
 */
class AprendizControllerTest extends ControladorTestCase
{
    /** @return array<string, array{0: array<string, mixed>, 1: string, 2: ?string}> */
    public static function quienNoEsAdministrador(): array
    {
        return [
            'sin sesion'    => [[], 'auth/login', null],
            'instructor'    => [self::sesion(2), 'usuarios', 'Acceso denegado'],
            'aprendiz'      => [self::sesion(3), 'dashboard', 'Acceso denegado'],
            'rol incorrecto'=> [self::sesion(9), 'dashboard', 'Acceso denegado'],
        ];
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function idsQueNoSonAprendiz(): array
    {
        return [
            'sin id'        => [[]],
            'id nulo'       => [['id' => null]],
            'id vacio'      => [['id' => '']],
            'id cero'       => [['id' => '0']],
            'id negativo'   => [['id' => '-4']],
            'id no numerico'=> [['id' => 'abc']],
            'id inexistente'=> [['id' => '999']],
        ];
    }

    /** @return array<string, array{0: array<string, mixed>}> */
    public static function tokensInvalidos(): array
    {
        return [
            'ausente'     => [[]],
            'nulo'        => [['csrf_token' => null]],
            'vacio'       => [['csrf_token' => '']],
            'incorrecto'  => [['csrf_token' => 'otro-token']],
        ];
    }

    public function testIndexSinSesionRedirigeAlLogin(): void
    {
        $resultado = $this->accion('AprendizController', 'index');

        $this->assertRedirige($resultado, 'auth/login');
        $this->assertSinEscritura($resultado);
    }
    
    public function testIndexInstructorVaAlDirectorioDeUsuarios(): void
    {
        $resultado = $this->accion('AprendizController', 'index', self::sesion(2));

        $this->assertRedirige($resultado, 'usuarios');
        $this->assertSinFlash($resultado);
        $this->assertSinEscritura($resultado);
    }

    public function testIndexAdministradorVaAlListadoDeAprendices(): void
    {
        $resultado = $this->accion('AprendizController', 'index', self::sesion(1));

        $this->assertRedirige($resultado, 'usuarios', ['rol' => '3']);
        $this->assertSinFlash($resultado);
    }

    #[DataProvider('quienNoEsAdministrador')]
    public function testCrearSoloLoHaceElAdministrador(array $sesion, string $destino, ?string $flash): void
    {
        $resultado = $this->accion('AprendizController', 'crear', $sesion);

        $this->assertRedirige($resultado, $destino);
        if ($flash === null) {
            $this->assertSinFlash($resultado);
        } else {
            $this->assertFlash($resultado, 'error', $flash);
        }
        $this->assertSinEscritura($resultado);
    }

    public function testCrearAdministradorAbreElFormularioDeUsuarios(): void
    {
        $resultado = $this->accion('AprendizController', 'crear', self::sesion(1));

        $this->assertRedirige($resultado, 'usuarios/crear');
        $this->assertSinFlash($resultado);
    }

    #[DataProvider('quienNoEsAdministrador')]
    public function testEditarSoloLoHaceElAdministrador(array $sesion, string $destino, ?string $flash): void
    {
        $resultado = $this->accion('AprendizController', 'editar', $sesion, ['id' => '3']);

        $this->assertRedirige($resultado, $destino);
        if ($flash === null) {
            $this->assertSinFlash($resultado);
        } else {
            $this->assertFlash($resultado, 'error', $flash);
        }
        $this->assertSinEscritura($resultado);
    }

    #[DataProvider('idsQueNoSonAprendiz')]
    public function testEditarConIdNuloOInexistenteAvisaYNoPintaElFormulario(array $get): void
    {
        $resultado = $this->accion(
            'AprendizController',
            'editar',
            self::sesion(1),
            $get,
            [],
            'GET',
            self::semillaUsuarios()
        );

        $this->assertRedirige($resultado, 'usuarios', ['rol' => '3']);
        $this->assertFlash($resultado, 'error', 'Aprendiz no encontrado.');
        $this->assertSinEscritura($resultado);
    }

    public function testEditarAprendizExistentePintaElFormulario(): void
    {
        $resultado = $this->accion(
            'AprendizController',
            'editar',
            self::sesion(1),
            ['id' => '3'],
            [],
            'GET',
            self::semillaFichaDeMarco()
        );

        $this->assertPinta($resultado, 'Editar Aprendiz');
        $this->assertStringContainsString('1003003003', (string) $resultado['body']);
        $this->assertStringContainsString('csrf_token', (string) $resultado['body']);
        $this->assertSinEscritura($resultado);
    }

    public function testGuardarSiNoEsPostRegresaAlListado(): void
    {
        $resultado = $this->accion('AprendizController', 'guardar', self::sesion(1));

        $this->assertRedirige($resultado, 'usuarios', ['rol' => '3']);
        $this->assertSinFlash($resultado);
        $this->assertSinEscritura($resultado);
    }

    #[DataProvider('tokensInvalidos')]
    public function testGuardarRechazaTokenNuloOIncorrecto(array $post): void
    {
        $resultado = $this->accion(
            'AprendizController',
            'guardar',
            self::sesion(1),
            [],
            $post,
            'POST'
        );

        $this->assertRedirige($resultado, 'usuarios', ['rol' => '3']);
        $this->assertFlash($resultado, 'error', 'Token de seguridad inválido.');
        $this->assertSinEscritura($resultado);
    }

    public function testGuardarCreacionConCamposVaciosNoInserta(): void
    {
        $resultado = $this->accion(
            'AprendizController',
            'guardar',
            self::sesion(1),
            [],
            [
                'csrf_token'  => 'token-valido',
                'id_aprendiz' => '0',
                'num_documento'=> '   ',
                'nombre'      => '',
                'apellido'    => null,
                'correo'      => '',
                'password'    => '',
                'id_ficha'    => '0',
                'codigo_rfid' => '',
            ],
            'POST'
        );

        $this->assertRedirige($resultado, 'usuarios/crear');
        $this->assertFlash($resultado, 'error', 'número de documento');
        $this->assertFlash($resultado, 'error', 'nombre');
        $this->assertFlash($resultado, 'error', 'apellido');
        $this->assertFlash($resultado, 'error', 'correo');
        $this->assertFlash($resultado, 'error', 'contraseña');
        $this->assertFlash($resultado, 'error', 'ficha');
        $this->assertSinEscritura($resultado);
    }

    public function testGuardarCreacionConCorreoInvalidoYContrasenaCorta(): void
    {
        $resultado = $this->accion(
            'AprendizController',
            'guardar',
            self::sesion(1),
            [],
            $this->postAprendizNuevo([
                'correo'   => 'no-es-correo',
                'password' => '123',
            ]),
            'POST'
        );

        $this->assertRedirige($resultado, 'usuarios/crear');
        $this->assertFlash($resultado, 'error', 'no tiene un formato válido');
        $this->assertFlash($resultado, 'error', 'al menos 6 caracteres');
        $this->assertSinEscritura($resultado);
    }

    public function testGuardarCreacionValidaInsertaAprendizActivo(): void
    {
        $resultado = $this->accion(
            'AprendizController',
            'guardar',
            self::sesion(1),
            [],
            $this->postAprendizNuevo(),
            'POST',
            self::semillaFichaDeMarco()
        );

        $this->assertRedirige($resultado, 'usuarios', ['rol' => '3']);
        $this->assertFlash($resultado, 'success', 'Aprendiz creado correctamente.');
        $params = $this->parametrosQueContienen($resultado, 'INSERT INTO usuario');
        $this->assertSame(3, (int) ($params[':Rol_id'] ?? 0));
        $this->assertSame('555001', (string) ($params[':identificacion'] ?? ''));
        $this->assertSame('ana.diaz@sena.edu.co', (string) ($params[':email'] ?? ''));
        $this->assertSame(10, (int) ($params[':Ficha_id'] ?? 0));
        $this->assertSame('Activo', (string) ($params[':estado'] ?? ''));
        $this->assertNotSame('Secreta1', (string) ($params[':password'] ?? ''));
        $this->assertStringStartsWith('$2y$', (string) ($params[':password'] ?? ''));
    }

    public function testGuardarCreacionDuplicadaAvisaSinDetalleSql(): void
    {
        $resultado = $this->accion(
            'AprendizController',
            'guardar',
            self::sesion(1),
            [],
            $this->postAprendizNuevo(),
            'POST',
            '',
            'INSERT INTO usuario',
            'SQLSTATE[23000]: Duplicate entry'
        );

        $this->assertRedirige($resultado, 'usuarios', ['rol' => '3']);
        $this->assertFlash($resultado, 'error', 'ya existe');
        $this->assertStringNotContainsString('SQLSTATE', (string) ($resultado['session']['flash']['message'] ?? ''));
    }

    public function testGuardarCreacionConErrorDeBaseNoRevelaElMensaje(): void
    {
        $resultado = $this->accion(
            'AprendizController',
            'guardar',
            self::sesion(1),
            [],
            $this->postAprendizNuevo(),
            'POST',
            '',
            'INSERT INTO usuario',
            'server has gone away'
        );

        $this->assertRedirige($resultado, 'usuarios', ['rol' => '3']);
        $this->assertFlash($resultado, 'error', 'Ocurrió un error al crear el aprendiz');
        $this->assertStringNotContainsString('gone away', (string) ($resultado['session']['flash']['message'] ?? ''));
    }

    public function testGuardarConIdNuloONoNumericoCreaElAprendiz(): void
    {
        foreach ([null, '', 'abc'] as $id) {
            $resultado = $this->accion(
                'AprendizController',
                'guardar',
                self::sesion(1),
                [],
                $this->postAprendizNuevo(['id_aprendiz' => $id]),
                'POST',
                self::semillaFichaDeMarco()
            );

            $this->assertRedirige($resultado, 'usuarios', ['rol' => '3']);
            $this->assertFlash($resultado, 'success', 'Aprendiz creado correctamente.');
            $params = $this->parametrosQueContienen($resultado, 'INSERT INTO usuario');
            $this->assertSame(3, (int) ($params[':Rol_id'] ?? 0));
        }
    }

    public function testGuardarActualizacionValidaCambiaFichaYLlavero(): void
    {
        $resultado = $this->accion(
            'AprendizController',
            'guardar',
            self::sesion(1),
            [],
            [
                'csrf_token'  => 'token-valido',
                'id_aprendiz' => '3',
                'id_ficha'    => '10',
                'codigo_rfid' => 'LL-3',
                'nombre'      => 'Laura',
                'apellido'    => 'Pena',
                'correo'      => 'laura.pena@sena.edu.co',
                'password'    => '',
                'estado'      => 'Activo',
            ],
            'POST',
            self::semillaFichaDeMarco()
        );

        $this->assertRedirige($resultado, 'usuarios', ['rol' => '3']);
        $this->assertFlash($resultado, 'success', 'Aprendiz actualizado correctamente.');
        $ficha = $this->parametrosQueContienen($resultado, 'SET Ficha_id');
        $this->assertSame(3, (int) ($ficha[':id'] ?? 0));
        $this->assertSame(10, (int) ($ficha[':Ficha_id'] ?? 0));
        $this->assertSame('LL-3', (string) ($ficha[':codigo_llavero'] ?? ''));
    }

    public function testGuardarActualizacionConFichaYLlaveroVaciosLosDejaNulos(): void
    {
        $resultado = $this->accion(
            'AprendizController',
            'guardar',
            self::sesion(1),
            [],
            [
                'csrf_token'  => 'token-valido',
                'id_aprendiz' => '3',
                'id_ficha'    => '',
                'codigo_rfid' => '   ',
            ],
            'POST',
            self::semillaUsuarios()
        );

        $this->assertRedirige($resultado, 'usuarios', ['rol' => '3']);
        $this->assertFlash($resultado, 'success', 'Aprendiz actualizado correctamente.');
        $ficha = $this->parametrosQueContienen($resultado, 'SET Ficha_id');
        $this->assertArrayHasKey(':Ficha_id', $ficha);
        $this->assertNull($ficha[':Ficha_id']);
        $this->assertArrayHasKey(':codigo_llavero', $ficha);
        $this->assertNull($ficha[':codigo_llavero']);
    }

    public function testGuardarActualizacionDuplicadaNoRevelaElSql(): void
    {
        $resultado = $this->accion(
            'AprendizController',
            'guardar',
            self::sesion(1),
            [],
            [
                'csrf_token'  => 'token-valido',
                'id_aprendiz' => '3',
                'id_ficha'    => '10',
                'codigo_rfid' => 'LL-3',
            ],
            'POST',
            self::semillaUsuarios(),
            'UPDATE usuario',
            'Duplicate entry'
        );

        $this->assertRedirige($resultado, 'usuarios', ['rol' => '3']);
        $this->assertFlash($resultado, 'error', 'ya existe');
    }

    #[DataProvider('quienNoEsAdministrador')]
    public function testEliminarSoloLoHaceElAdministrador(array $sesion, string $destino, ?string $flash): void
    {
        $resultado = $this->accion('AprendizController', 'eliminar', $sesion, ['id' => '3']);

        $this->assertRedirige($resultado, $destino);
        if ($flash === null) {
            $this->assertSinFlash($resultado);
        } else {
            $this->assertFlash($resultado, 'error', $flash);
        }
        $this->assertSinEscritura($resultado);
    }

    public function testEliminarIdNuloOCeroNoBorraFilasYAunAsiConfirma(): void
    {
        $resultado = $this->accion(
            'AprendizController',
            'eliminar',
            self::sesion(1),
            ['id' => null],
            [],
            'GET',
            self::semillaUsuarios()
        );

        $this->assertRedirige($resultado, 'usuarios', ['rol' => '3']);
        $this->assertFlash($resultado, 'success', 'Aprendiz eliminado correctamente.');
        $params = $this->parametrosQueContienen($resultado, 'DELETE FROM usuario');
        $this->assertSame(0, (int) ($params[':id'] ?? -1));
        $this->assertSame(3, (int) ($params[':rol'] ?? 0));
    }

    public function testEliminarAprendizExistenteBorraSoloEseRol(): void
    {
        $resultado = $this->accion(
            'AprendizController',
            'eliminar',
            self::sesion(1),
            ['id' => '3'],
            [],
            'GET',
            self::semillaUsuarios()
        );

        $this->assertRedirige($resultado, 'usuarios', ['rol' => '3']);
        $this->assertFlash($resultado, 'success', 'Aprendiz eliminado correctamente.');
        $params = $this->parametrosQueContienen($resultado, 'DELETE FROM usuario');
        $this->assertSame(3, (int) ($params[':id'] ?? 0));
        $this->assertSame(3, (int) ($params[':rol'] ?? 0));
    }

    public function testEliminarConRegistrosAsociadosInformaElBloqueo(): void
    {
        $resultado = $this->accion(
            'AprendizController',
            'eliminar',
            self::sesion(1),
            ['id' => '3'],
            [],
            'GET',
            self::semillaUsuarios(),
            'DELETE FROM usuario',
            'FOREIGN KEY constraint failed'
        );

        $this->assertRedirige($resultado, 'usuarios', ['rol' => '3']);
        $this->assertFlash($resultado, 'error', 'registros asociados');
        $this->assertStringNotContainsString('FOREIGN KEY', (string) ($resultado['session']['flash']['message'] ?? ''));
    }

    /** @param array<string, mixed> $extra */
    private function postAprendizNuevo(array $extra = []): array
    {
        return array_merge([
            'csrf_token'   => 'token-valido',
            'id_aprendiz'  => '0',
            'num_documento'=> '555001',
            'nombre'       => 'Ana',
            'apellido'     => 'Diaz',
            'correo'       => 'ana.diaz@sena.edu.co',
            'password'     => 'Secreta1',
            'id_ficha'     => '10',
            'codigo_rfid'  => 'LL-9',
        ], $extra);
    }
}
