<?php

declare(strict_types=1);

/**
 * Ejecuta un método de controlador en un proceso aparte.
 *
 * Los controladores llaman a exit() después de redirigir. Si eso
 * ocurriera dentro de PHPUnit, se caería toda la suite. Aquí el
 * proceso termina y, antes, deja en un JSON las cabeceras, la sesión,
 * el HTML y el SQL que sí se ejecutó.
 */

if (!isset($argv[1], $argv[2])) {
    file_put_contents('php://stderr', "Uso interno de las pruebas.\n");
    exit(1);
}

ob_start();

require dirname(__DIR__) . '/bootstrap.php';

$pedido = json_decode((string) file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
$archivoSalida = $argv[2];

final class PdoDePrueba extends PDO
{
    /** @var list<array{sql: string, params: array<string, mixed>}> */
    public array $operaciones = [];

    public function __construct(
        private readonly ?string $falloSql,
        private readonly string $mensajeFallo
    ) {
        parent::__construct('sqlite::memory:', null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $this->sqliteCreateFunction('CONCAT', static function (mixed ...$partes): string {
            $texto = '';
            foreach ($partes as $parte) {
                $texto .= (string) $parte;
            }
            return $texto;
        }, -1);
    }

    public function prepare(string $query, array $options = []): SentenciaEnvuelta
    {
        if ($this->falloSql !== null && $this->falloSql !== '' && stripos($query, $this->falloSql) !== false) {
            throw new PDOException($this->mensajeFallo);
        }

        $real = parent::prepare($query, $options);
        if (!$real instanceof PDOStatement) {
            throw new PDOException('No se pudo preparar la consulta de prueba.');
        }

        return new SentenciaEnvuelta($real, $this, $query);
    }
}

/**
 * Anota el SQL y los parámetros en el momento de execute().
 * Los modelos no exigen el tipo PDOStatement: usan execute, fetch,
 * fetchAll, fetchColumn y bindValue.
 */
final class SentenciaEnvuelta
{
    public function __construct(
        private readonly PDOStatement $real,
        private readonly PdoDePrueba $pdo,
        private readonly string $sql
    ) {
    }

    public function execute(?array $params = null): bool
    {
        $this->pdo->operaciones[] = [
            'sql'    => $this->sql,
            'params' => $params ?? [],
        ];

        return $this->real->execute($params);
    }

    public function fetch(mixed ...$args): mixed
    {
        return $this->real->fetch(...$args);
    }

    public function fetchAll(mixed ...$args): array
    {
        return $this->real->fetchAll(...$args);
    }

    public function fetchColumn(int $column = 0): mixed
    {
        return $this->real->fetchColumn($column);
    }

    public function bindValue(string|int $param, mixed $value, int $type = PDO::PARAM_STR): bool
    {
        return $this->real->bindValue($param, $value, $type);
    }
}

$captura = ['excepcion' => ''];
set_exception_handler(static function (Throwable $e) use (&$captura): void {
    $captura['excepcion'] = $e::class . ': ' . $e->getMessage();
});

$pdo = new PdoDePrueba(
    isset($pedido['falloSql']) && $pedido['falloSql'] !== null && $pedido['falloSql'] !== ''
        ? (string) $pedido['falloSql']
        : null,
    (string) ($pedido['mensajeFallo'] ?? 'Duplicate entry')
);

$ejecutarSql = static function (PDO $conexion, string $sql): void {
    foreach (explode(';', $sql) as $sentencia) {
        $sentencia = trim($sentencia);
        if ($sentencia !== '') {
            $conexion->exec($sentencia);
        }
    }
};

$ejecutarSql($pdo, (string) file_get_contents(__DIR__ . '/esquema_minimo.sql'));
if (!empty($pedido['semilla'])) {
    $ejecutarSql($pdo, (string) $pedido['semilla']);
}

register_shutdown_function(static function () use ($archivoSalida, $pdo, &$captura): void {
    $ultimo = error_get_last();
    $esFatal = $ultimo !== null && in_array($ultimo['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true);
    $cuerpo = ob_get_contents();
    file_put_contents($archivoSalida, json_encode([
        'headers'   => headers_list(),
        'session'   => $_SESSION ?? [],
        'body'      => is_string($cuerpo) ? $cuerpo : '',
        'sql'       => array_map(static fn (array $op): string => $op['sql'], $pdo->operaciones),
        'params'    => $pdo->operaciones,
        'excepcion' => $captura['excepcion'],
        'fatal'     => $esFatal ? (string) ($ultimo['message'] ?? '') : '',
    ], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR));
});

session_save_path(sys_get_temp_dir());
session_start();
$_SESSION = is_array($pedido['sesion'] ?? null) ? $pedido['sesion'] : [];
$_GET = is_array($pedido['get'] ?? null) ? $pedido['get'] : [];
$_POST = is_array($pedido['post'] ?? null) ? $pedido['post'] : [];
$_SERVER['REQUEST_METHOD'] = (string) ($pedido['method'] ?? 'GET');
$_REQUEST = array_merge($_GET, $_POST);

$clase = (string) $pedido['clase'];
$metodo = (string) $pedido['metodo'];
$controlador = new $clase($pdo);
$controlador->{$metodo}();
