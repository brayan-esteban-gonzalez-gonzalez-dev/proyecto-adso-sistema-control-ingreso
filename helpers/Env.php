<?php
declare(strict_types=1);

/**
 * Env.php — Carga un archivo .env a variables de entorno del proceso.
 * No pisa variables que ya vengan del sistema (Apache, CLI, hosting).
 */
class Env
{
    public static function load(?string $path = null): void
    {
        if ($path === null) {
            $root = defined('ROOT_PATH') ? ROOT_PATH : dirname(__DIR__);
            $path = $root . DIRECTORY_SEPARATOR . '.env';
        }

        if (!is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            return;
        }

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_starts_with($line, 'export ')) {
                $line = trim(substr($line, 7));
            }

            $separator = strpos($line, '=');
            if ($separator === false) {
                continue;
            }

            $key = trim(substr($line, 0, $separator));
            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
                continue;
            }

            if (getenv($key) !== false) {
                continue;
            }

            $value = self::parseValue(substr($line, $separator + 1));
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }
        return $value;
    }

    private static function parseValue(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $quote = $value[0];
        if (($quote === '"' || $quote === "'") && str_ends_with($value, $quote) && strlen($value) >= 2) {
            $value = substr($value, 1, -1);
            if ($quote === '"') {
                $value = strtr($value, [
                    '\\n' => "\n",
                    '\\r' => "\r",
                    '\\t' => "\t",
                    '\\"' => '"',
                    '\\\\' => '\\',
                ]);
            }
        }

        return $value;
    }
}
