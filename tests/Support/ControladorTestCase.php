<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Base de las pruebas de controlador.
 * Cada acción corre en otro proceso porque el código de producción
 * termina con exit() al redirigir.
 */
abstract class ControladorTestCase extends TestCase
{
    /** @return array<string, mixed> */
    protected static function sesion(int $rol, int $id = 0): array
    {
        return [
            'user_id'       => $id > 0 ? $id : max($rol, 1),
            'user_rol'      => $rol,
            'user_nombre'   => 'Persona',
            'user_apellido' => 'Prueba',
            'csrf_token'    => 'token-valido',
        ];
    }

    protected static function semillaUsuarios(): string
    {
        return <<<'SQL'
INSERT INTO usuario (id, nombre, apellido, identificacion, email, password, Rol_id, estado) VALUES
(1, 'Elena', 'Vargas', '1001001001', 'elena.vargas@sena.edu.co', 'x', 1, 'Activo'),
(2, 'Marco', 'Ruiz', '1002002002', 'marco.ruiz@sena.edu.co', 'x', 2, 'Activo'),
(3, 'Laura', 'Pena', '1003003003', 'laura.pena@sena.edu.co', 'x', 3, 'Activo');
SQL;
    }

    protected static function semillaFichaDeMarco(): string
    {
        return self::semillaUsuarios() . <<<'SQL'

INSERT INTO programa (id, nombre, descripcion) VALUES (1, 'ADSO', 'Analisis y desarrollo de software');
INSERT INTO jornada (id, nombre) VALUES (1, 'Manana');
INSERT INTO ficha (id, codigo, Programa_id, jornada_id, instructor_id, estado)
VALUES (10, 3064749, 1, 1, 2, 'Activo');
SQL;
    }

    /**
     * @param array<string, mixed> $sesion
     * @param array<string, mixed> $get
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    protected function accion(
        string $clase,
        string $metodo,
        array $sesion = [],
        array $get = [],
        array $post = [],
        string $method = 'GET',
        string $semilla = '',
        ?string $falloSql = null,
        string $mensajeFallo = 'Duplicate entry'
    ): array {
        return AccionControlador::ejecutar(
            $clase,
            $metodo,
            $sesion,
            $get,
            $post,
            $method,
            $semilla,
            $falloSql,
            $mensajeFallo
        );
    }

    /** @param array<string, mixed> $resultado */
    protected function assertSinFallo(array $resultado): void
    {
        $this->assertSame('', (string) ($resultado['fatal'] ?? ''), (string) ($resultado['fatal'] ?? ''));
        $this->assertSame('', (string) ($resultado['excepcion'] ?? ''), (string) ($resultado['excepcion'] ?? '') . "\n" . (string) ($resultado['stderr'] ?? ''));
    }

    /**
     * @param array<string, mixed> $resultado
     * @param array<string, scalar|null> $consulta
     */
    protected function assertRedirige(array $resultado, string $action, array $consulta = []): void
    {
        $this->assertSinFallo($resultado);
        $ubicacion = $this->ubicacion($resultado);
        $this->assertNotNull($ubicacion, 'No hubo redirección. Cabeceras: ' . json_encode($resultado['headers'] ?? []));
        $partes = parse_url((string) $ubicacion);
        parse_str((string) ($partes['query'] ?? ''), $parametros);
        $this->assertSame($action, $parametros['action'] ?? null, (string) $ubicacion);
        foreach ($consulta as $clave => $valor) {
            $this->assertSame((string) $valor, (string) ($parametros[$clave] ?? ''), (string) $ubicacion);
        }
    }

    /** @param array<string, mixed> $resultado */
    protected function assertFlash(array $resultado, string $tipo, string $fragmento): void
    {
        $flash = $resultado['session']['flash'] ?? null;
        $this->assertIsArray($flash, 'No hubo mensaje flash.');
        $this->assertSame($tipo, $flash['type'] ?? null);
        $this->assertStringContainsString($fragmento, (string) ($flash['message'] ?? ''));
    }

    /** @param array<string, mixed> $resultado */
    protected function assertSinFlash(array $resultado): void
    {
        $this->assertArrayNotHasKey('flash', $resultado['session'] ?? []);
    }

    /** @param array<string, mixed> $resultado */
    protected function assertPinta(array $resultado, string $fragmento): void
    {
        $this->assertSinFallo($resultado);
        $this->assertNull($this->ubicacion($resultado), 'La acción redirigió y no pintó la vista.');
        $this->assertStringContainsString($fragmento, (string) ($resultado['body'] ?? ''));
    }

    /** @param array<string, mixed> $resultado */
    protected function assertSinEscritura(array $resultado): void
    {
        foreach ($resultado['sql'] ?? [] as $sql) {
            $this->assertDoesNotMatchRegularExpression(
                '/^\s*(INSERT|UPDATE|DELETE)\b/i',
                (string) $sql
            );
        }
    }

    /**
     * @param array<string, mixed> $resultado
     * @return array<string, mixed>
     */
    protected function parametrosQueContienen(array $resultado, string $fragmentoSql): array
    {
        $ultimo = null;
        foreach ($resultado['params'] ?? [] as $operacion) {
            if (stripos((string) ($operacion['sql'] ?? ''), $fragmentoSql) !== false) {
                $ultimo = $operacion['params'] ?? [];
            }
        }
        $this->assertIsArray($ultimo, 'No se ejecutó SQL que contenga: ' . $fragmentoSql);
        return $ultimo;
    }

    /** @param array<string, mixed> $resultado */
    private function ubicacion(array $resultado): ?string
    {
        foreach ($resultado['headers'] ?? [] as $cabecera) {
            if (stripos((string) $cabecera, 'Location:') === 0) {
                return trim(substr((string) $cabecera, strlen('Location:')));
            }
        }

        return null;
    }
}
