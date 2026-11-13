<?php

declare(strict_types=1);

/**
 * Lanza ejecutar_accion.php y devuelve lo que el controlador hizo:
 * a dónde redirigió, qué dejó en sesión y qué SQL preparó.
 */
final class AccionControlador
{
    /**
     * @param array<string, mixed> $sesion
     * @param array<string, mixed> $get
     * @param array<string, mixed> $post
     * @return array{
     *     headers: list<string>,
     *     session: array<string, mixed>,
     *     body: string,
     *     sql: list<string>,
     *     params: list<array{sql: string, params: array<string, mixed>}>,
     *     excepcion: string,
     *     fatal: string,
     *     stderr: string,
     *     codigo: int
     * }
     */
    public static function ejecutar(
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
        $directorio = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'scia-pruebas';
        if (!is_dir($directorio) && !mkdir($directorio, 0777, true) && !is_dir($directorio)) {
            throw new RuntimeException('No se pudo crear el directorio temporal de pruebas.');
        }

        $id = bin2hex(random_bytes(8));
        $entrada = $directorio . DIRECTORY_SEPARATOR . $id . '.json';
        $salida = $directorio . DIRECTORY_SEPARATOR . $id . '.out.json';
        file_put_contents($entrada, json_encode([
            'clase'         => $clase,
            'metodo'        => $metodo,
            'sesion'        => $sesion,
            'get'           => $get,
            'post'          => $post,
            'method'        => $method,
            'semilla'       => $semilla,
            'falloSql'      => $falloSql,
            'mensajeFallo'  => $mensajeFallo,
        ], JSON_THROW_ON_ERROR));

        $proceso = proc_open(
            self::comando($entrada, $salida),
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $tuberias,
            dirname(__DIR__, 2)
        );

        if (!is_resource($proceso)) {
            throw new RuntimeException('No se pudo iniciar el proceso de la acción.');
        }

        $stdout = stream_get_contents($tuberias[1]);
        $stderr = stream_get_contents($tuberias[2]);
        fclose($tuberias[1]);
        fclose($tuberias[2]);
        $codigo = proc_close($proceso);
        @unlink($entrada);

        if (!is_file($salida)) {
            throw new RuntimeException(
                "La acción no dejó resultado (código {$codigo}).\n" . $stdout . "\n" . $stderr
            );
        }

        $datos = json_decode((string) file_get_contents($salida), true, 512, JSON_THROW_ON_ERROR);
        @unlink($salida);

        $datos['stderr'] = (string) $stderr;
        $datos['codigo'] = $codigo;
        if (($datos['headers'] ?? []) === []) {
            $datos['headers'] = self::cabecerasDesdeSalida((string) $stdout);
        }

        return $datos;
    }

    /**
     * header() no deja rastro en el CLI. php-cgi sí registra Location,
     * que es lo que prometen los controladores al redirigir.
     *
     * @return list<string>
     */
    private static function comando(string $entrada, string $salida): array
    {
        $cgi = dirname(PHP_BINARY) . DIRECTORY_SEPARATOR . (PHP_OS_FAMILY === 'Windows' ? 'php-cgi.exe' : 'php-cgi');
        $script = __DIR__ . '/ejecutar_accion.php';
        if (is_file($cgi)) {
            return [
                $cgi,
                '-d', 'register_argc_argv=1',
                '-d', 'display_errors=0',
                '-d', 'html_errors=0',
                $script,
                $entrada,
                $salida,
            ];
        }

        return [PHP_BINARY, $script, $entrada, $salida];
    }

    /** @return list<string> */
    private static function cabecerasDesdeSalida(string $salida): array
    {
        $cabeceras = [];
        foreach (preg_split("/\r\n|\n|\r/", $salida) ?: [] as $linea) {
            if ($linea === '') {
                break;
            }
            if (str_contains($linea, ':')) {
                $cabeceras[] = $linea;
            }
        }

        return $cabeceras;
    }
}
