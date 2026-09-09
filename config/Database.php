<?php
/**
 * Database.php — Clase de conexión PDO (Singleton)
 * 
 * Proporciona una única instancia de conexión a la base de datos MySQL
 * usando PDO con charset utf8mb4 y manejo de errores por excepciones.
 */
class Database {
    private $host     = "localhost";
    private $db_name  = "sistema_asistencia_rfid";
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
            } catch (PDOException $e) {
                die("Error de conexión a la base de datos: " . $e->getMessage());
            }
        }
        return $this->conn;
    }
}
