<?php
/**
 * Validator.php — Helper de validación de datos
 * 
 * Proporciona métodos estáticos para validar campos de formularios,
 * tipos de archivos y tamaños.
 */
class Validator {

    /** @var array Errores acumulados */
    private static $errors = [];

    /**
     * Reinicia el array de errores
     */
    public static function reset(): void {
        self::$errors = [];
    }

    /**
     * Retorna los errores acumulados
     */
    public static function getErrors(): array {
        return self::$errors;
    }

    /**
     * Verifica si la validación fue exitosa
     */
    public static function isValid(): bool {
        return empty(self::$errors);
    }

    /**
     * Valida que un campo no esté vacío
     */
    public static function required(string $value, string $fieldName): bool {
        if (trim($value) === '') {
            self::$errors[] = "El campo {$fieldName} es obligatorio.";
            return false;
        }
        return true;
    }

    /**
     * Valida formato de correo electrónico
     */
    public static function email(string $value, string $fieldName = 'correo'): bool {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            self::$errors[] = "El campo {$fieldName} no tiene un formato válido.";
            return false;
        }
        return true;
    }

    /**
     * Valida longitud mínima
     */
    public static function minLength(string $value, int $min, string $fieldName): bool {
        if (mb_strlen($value) < $min) {
            self::$errors[] = "El campo {$fieldName} debe tener al menos {$min} caracteres.";
            return false;
        }
        return true;
    }

    /**
     * Valida longitud máxima
     */
    public static function maxLength(string $value, int $max, string $fieldName): bool {
        if (mb_strlen($value) > $max) {
            self::$errors[] = "El campo {$fieldName} no puede exceder {$max} caracteres.";
            return false;
        }
        return true;
    }

    /**
     * Valida que sea un número entero positivo
     */
    public static function positiveInt($value, string $fieldName): bool {
        if (!is_numeric($value) || (int)$value < 0) {
            self::$errors[] = "El campo {$fieldName} debe ser un número positivo.";
            return false;
        }
        return true;
    }

    /**
     * Valida el tipo de archivo subido
     */
    public static function fileType(array $file, array $allowedTypes, string $fieldName): bool {
        if (!isset($file['type'])) {
            self::$errors[] = "No se pudo determinar el tipo de archivo para {$fieldName}.";
            return false;
        }
        if (!in_array($file['type'], $allowedTypes)) {
            $types = implode(', ', $allowedTypes);
            self::$errors[] = "El archivo {$fieldName} debe ser uno de los siguientes tipos: {$types}.";
            return false;
        }
        return true;
    }

    /**
     * Valida el tamaño del archivo subido (en bytes)
     */
    public static function fileSize(array $file, int $maxSize, string $fieldName): bool {
        if (!isset($file['size'])) {
            self::$errors[] = "No se pudo determinar el tamaño del archivo para {$fieldName}.";
            return false;
        }
        if ($file['size'] > $maxSize) {
            $maxMB = round($maxSize / (1024 * 1024), 1);
            self::$errors[] = "El archivo {$fieldName} no puede exceder {$maxMB} MB.";
            return false;
        }
        return true;
    }

    /**
     * Valida formato de fecha (YYYY-MM-DD)
     */
    public static function date(string $value, string $fieldName): bool {
        $date = \DateTime::createFromFormat('Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            self::$errors[] = "El campo {$fieldName} no tiene un formato de fecha válido (YYYY-MM-DD).";
            return false;
        }
        return true;
    }

    /**
     * Valida formato de hora (HH:MM o HH:MM:SS)
     */
    public static function time(string $value, string $fieldName): bool {
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $value)) {
            self::$errors[] = "El campo {$fieldName} no tiene un formato de hora válido (HH:MM).";
            return false;
        }
        return true;
    }

    /**
     * Sanitiza una cadena para prevenir XSS
     */
    public static function sanitize(string $value): string {
        return htmlspecialchars(trim($value), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitiza un arreglo completo
     */
    public static function sanitizeArray(array $data): array {
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = self::sanitize($value);
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }
}
