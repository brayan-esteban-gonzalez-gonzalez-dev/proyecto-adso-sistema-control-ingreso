<?php
/**
 * Database.php — Clase de conexión PDO (Singleton)
 * 
 * Proporciona una única instancia de conexión a la base de datos MySQL
 * usando PDO con charset utf8mb4 y manejo de errores por excepciones.
 * Si la base de datos o las tablas no existen en phpMyAdmin, las crea automáticamente.
 */
class Database {
    private $host     = "localhost";
    private $db_name  = "db_ingreso_aprendices";
    private $username = "root";
    private $password = "";
    private $charset  = "utf8mb4";

    /** @var PDO|null */
    private $conn = null;

    /** @var Database|null */
    private static $instance = null;

    /**
     * Obtiene la instancia singleton de Database
     */
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Establece y retorna la conexión PDO
     */
    public function getConnection(): ?PDO {
        if ($this->conn === null) {
            try {
                $dsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";
                $options = [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ];
                $this->conn = new PDO($dsn, $this->username, $this->password, $options);

                // Verificar si existen las tablas principales
                $this->ensureTablesExist();

            } catch (PDOException $e) {
                // Si la base de datos no existe (Código 1049 / Unknown database)
                if ($e->getCode() == 1049 || str_contains(strtolower($e->getMessage()), "unknown database")) {
                    $this->autoInitDatabase();
                } else {
                    die("Error de conexión a la base de datos: " . $e->getMessage());
                }
            }
        }
        return $this->conn;
    }

    /**
     * Verifica que las tablas principales existan en la BD
     */
    private function ensureTablesExist(): void {
        try {
            $stmt = $this->conn->query("SHOW TABLES LIKE 'Usuario'");
            if ($stmt === false || $stmt->rowCount() === 0) {
                $this->runMigrationScript($this->conn);
            }
        } catch (PDOException $e) {
            $this->runMigrationScript($this->conn);
        }
    }

    /**
     * Crea la base de datos en MySQL y ejecuta la migración inicial
     */
    private function autoInitDatabase(): void {
        try {
            // Conectar al servidor MySQL sin especificar base de datos
            $dsn = "mysql:host={$this->host};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ];
            $serverConn = new PDO($dsn, $this->username, $this->password, $options);

            // Crear la base de datos si no existe
            $serverConn->exec("CREATE DATABASE IF NOT EXISTS `{$this->db_name}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
            $serverConn->exec("USE `{$this->db_name}`;");

            // Ejecutar el script SQL de migración
            $this->runMigrationScript($serverConn);

            // Establecer la conexión PDO principal a la BD recién creada
            $mainDsn = "mysql:host={$this->host};dbname={$this->db_name};charset={$this->charset}";
            $mainOptions = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $this->conn = new PDO($mainDsn, $this->username, $this->password, $mainOptions);

        } catch (PDOException $e) {
            die("Error al inicializar la base de datos automáticamente: " . $e->getMessage());
        }
    }

    /**
     * Carga y ejecuta el archivo SQL de migración
     */
    private function runMigrationScript(PDO $pdo): void {
        $sqlPath = defined('ROOT_PATH') 
            ? ROOT_PATH . '/migrations/db_ingreso_aprendices.sql' 
            : __DIR__ . '/../migrations/db_ingreso_aprendices.sql';
        
        if (!file_exists($sqlPath)) {
            die("Error: No se encontró el archivo de migración SQL en: {$sqlPath}");
        }

        $sql = file_get_contents($sqlPath);
        if (empty($sql)) {
            die("Error: El archivo de migración SQL está vacío.");
        }

        try {
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
            $pdo->exec($sql);
            $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch (PDOException $e) {
            die("Error ejecutando migración SQL: " . $e->getMessage());
        }
    }
}

