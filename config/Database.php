<?php
declare(strict_types=1);
/**
 * Database.php — Clase de conexión PDO (Singleton)
 * 
 * Proporciona una única instancia de conexión a la base de datos MySQL
 * usando PDO con charset utf8mb4 y manejo de errores por excepciones.
 * Si la base de datos o las tablas no existen en phpMyAdmin, las crea automáticamente.
 */
class Database {
    /** @var string */
    private $host;
    /** @var string */
    private $db_name;
    /** @var string */
    private $username;
    /** @var string */
    private $password;
    /** @var string */
    private $charset;

    public function __construct()
    {
        // Leer credenciales desde variables de entorno (.env) con fallback a XAMPP por defecto
        $this->host     = $_ENV['DB_HOST']     ?? getenv('DB_HOST')     ?: 'localhost';
        $this->db_name  = $_ENV['DB_NAME']     ?? getenv('DB_NAME')     ?: 'db_ingreso_aprendices';
        $this->username = $_ENV['DB_USER']     ?? getenv('DB_USER')     ?: 'root';
        $this->password = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD') ?: '';
        $this->charset  = $_ENV['DB_CHARSET']  ?? getenv('DB_CHARSET')  ?: 'utf8mb4';

        // Validación básica de seguridad (evitar caracteres raros en el .env)
        $this->validateEnvValues();
    }

    /**
     * Valida que las variables de entorno solo contengan caracteres permitidos.
     */
    private function validateEnvValues(): void
    {
        $allowed = '/^[a-zA-Z0-9_.\-]+$/';

        foreach ([
            'host'     => $this->host,
            'db_name'  => $this->db_name,
            'username' => $this->username,
            'charset'  => $this->charset,
        ] as $name => $value) {
            if (!preg_match($allowed, (string)$value)) {
                error_log("[SCIA][Database] Valor inválido para {$name} en variables de entorno.");
                die('Error de configuración de base de datos. Contacte al administrador.');
            }
        }
    }

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
                    error_log('[SCIA][Database] Error de conexión: ' . $e->getMessage());
                    die("Error de conexión a la base de datos. Contacte al administrador.");
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
            $stmt = $this->conn->query("SHOW TABLES LIKE 'usuario'");
            if ($stmt === false || $stmt->rowCount() === 0) {
                $this->runMigrationScript($this->conn);
            } else {
                // La BD ya existe: aplicar migraciones incrementales si faltan
                $this->ensureMigrationsApplied();
            }
        } catch (PDOException $e) {
            $this->runMigrationScript($this->conn);
        }
    }

    /**
     * Verifica idempotentemente que las migraciones 005-008 estén aplicadas.
     * Se ejecuta solo cuando la BD ya existía (instalación previa).
     * Usa SHOW TABLES / SHOW COLUMNS para no romper esquemas correctos.
     */
    private function ensureMigrationsApplied(): void
    {
        try {
            // --- Migración 005: hora_fin NULL en sesion ---
            $colHoraFin = $this->conn->query(
                "SELECT IS_NULLABLE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'sesion'
                   AND COLUMN_NAME  = 'hora_fin'"
            );
            $rowHF = $colHoraFin ? $colHoraFin->fetch() : null;
            if ($rowHF && $rowHF['IS_NULLABLE'] === 'NO') {
                // Eliminar CHECK antiguo si existe, luego hacer nullable
                @$this->conn->exec("ALTER TABLE `sesion` DROP CHECK `chk_sesion_rango`");
                $this->conn->exec("ALTER TABLE `sesion` MODIFY `hora_fin` TIME NULL DEFAULT NULL");
                $this->conn->exec(
                    "ALTER TABLE `sesion` ADD CONSTRAINT `chk_sesion_rango`
                     CHECK (`hora_fin` IS NULL OR `hora_inicio` < `hora_fin`)"
                );
            }

            // --- Migración 007A: columnas apertura_real y cierre_real en sesion ---
            foreach (['apertura_real', 'cierre_real'] as $col) {
                $check = $this->conn->query(
                    "SHOW COLUMNS FROM `sesion` LIKE '{$col}'"
                );
                if ($check && $check->rowCount() === 0) {
                    $this->conn->exec(
                        "ALTER TABLE `sesion` ADD COLUMN `{$col}` TIME NULL DEFAULT NULL"
                    );
                }
            }

            // --- Migración 007B: ampliar ENUM estado en sesion con 'Permutado' ---
            $enumStmt = $this->conn->query(
                "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME   = 'sesion'
                   AND COLUMN_NAME  = 'estado'"
            );
            $enumRow = $enumStmt ? $enumStmt->fetch() : null;
            if ($enumRow && strpos($enumRow['COLUMN_TYPE'], 'Permutado') === false) {
                $this->conn->exec(
                    "ALTER TABLE `sesion`
                     MODIFY COLUMN `estado`
                     ENUM('Activo','Cancelado','Finalizada','Permutado') NOT NULL DEFAULT 'Activo'"
                );
            }

            // --- Migración 006: columna estado en inasistencia ---
            $chkEstado = $this->conn->query(
                "SHOW COLUMNS FROM `inasistencia` LIKE 'estado'"
            );
            if ($chkEstado && $chkEstado->rowCount() === 0) {
                $this->conn->exec(
                    "ALTER TABLE `inasistencia`
                     ADD COLUMN `estado`
                     ENUM('Injustificada','En revisión','Justificada') NOT NULL DEFAULT 'Injustificada'
                     AFTER `generado_por`"
                );
            }

            // --- Migración 007C: tabla asignacion_instructor ---
            $chkAI = $this->conn->query("SHOW TABLES LIKE 'asignacion_instructor'");
            if ($chkAI && $chkAI->rowCount() === 0) {
                $this->conn->exec(
                    "CREATE TABLE `asignacion_instructor` (
                       `id` INT NOT NULL AUTO_INCREMENT,
                       `instructor_id` INT NOT NULL,
                       `ficha_id` INT NOT NULL,
                       `fecha` DATE NOT NULL,
                       `hora_inicio` TIME NOT NULL,
                       `hora_fin` TIME NOT NULL,
                       `estado` ENUM('Activo','Cancelado') NOT NULL DEFAULT 'Activo',
                       `creado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                       `actualizado_en` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                       PRIMARY KEY (`id`),
                       UNIQUE KEY `uk_asignacion_unica` (`instructor_id`,`ficha_id`,`fecha`,`hora_inicio`),
                       KEY `fk_asignacion_ficha_idx` (`ficha_id`),
                       CONSTRAINT `fk_asig_inst_usuario`
                           FOREIGN KEY (`instructor_id`) REFERENCES `usuario` (`id`),
                       CONSTRAINT `fk_asig_inst_ficha`
                           FOREIGN KEY (`ficha_id`) REFERENCES `ficha` (`id`)
                     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
                );
            }

            // --- Migración 007D: tabla permuta_sesion ---
            $chkPS = $this->conn->query("SHOW TABLES LIKE 'permuta_sesion'");
            if ($chkPS && $chkPS->rowCount() === 0) {
                $this->conn->exec(
                    "CREATE TABLE `permuta_sesion` (
                       `id` INT NOT NULL AUTO_INCREMENT,
                       `sesion_origen_id` INT NOT NULL,
                       `sesion_destino_id` INT NOT NULL,
                       `instructor_solicitante_id` INT NOT NULL,
                       `instructor_receptor_id` INT NOT NULL,
                       `estado` ENUM('Pendiente','Aprobada','Rechazada') NOT NULL DEFAULT 'Pendiente',
                       `motivo` TEXT DEFAULT NULL,
                       `fecha_solicitud` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                       `fecha_resolucion` TIMESTAMP NULL DEFAULT NULL,
                       PRIMARY KEY (`id`),
                       KEY `fk_permuta_origen_idx` (`sesion_origen_id`),
                       KEY `fk_permuta_destino_idx` (`sesion_destino_id`),
                       KEY `fk_permuta_sol_idx` (`instructor_solicitante_id`),
                       KEY `fk_permuta_rec_idx` (`instructor_receptor_id`),
                       CONSTRAINT `fk_ps_origen`
                           FOREIGN KEY (`sesion_origen_id`) REFERENCES `sesion` (`id`),
                       CONSTRAINT `fk_ps_destino`
                           FOREIGN KEY (`sesion_destino_id`) REFERENCES `sesion` (`id`),
                       CONSTRAINT `fk_ps_solicitante`
                           FOREIGN KEY (`instructor_solicitante_id`) REFERENCES `usuario` (`id`),
                       CONSTRAINT `fk_ps_receptor`
                           FOREIGN KEY (`instructor_receptor_id`) REFERENCES `usuario` (`id`)
                     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
                );
            }

            // --- Migración 008: tabla importacion_horario ---
            $chkIH = $this->conn->query("SHOW TABLES LIKE 'importacion_horario'");
            if ($chkIH && $chkIH->rowCount() === 0) {
                $this->conn->exec(
                    "CREATE TABLE `importacion_horario` (
                       `id` INT NOT NULL AUTO_INCREMENT,
                       `usuario_id` INT NOT NULL,
                       `tipo` ENUM('ficha','instructor') NOT NULL,
                       `archivo` VARCHAR(255) NOT NULL,
                       `anio` SMALLINT NOT NULL,
                       `codigo_ficha` VARCHAR(20) DEFAULT NULL,
                       `instructor_id` INT DEFAULT NULL,
                       `creadas` INT NOT NULL DEFAULT 0,
                       `actualizadas` INT NOT NULL DEFAULT 0,
                       `omitidas` INT NOT NULL DEFAULT 0,
                       `canceladas` INT NOT NULL DEFAULT 0,
                       `protegidas` INT NOT NULL DEFAULT 0,
                       `errores` TEXT DEFAULT NULL,
                       `permutas_confirmadas` TINYINT(1) NOT NULL DEFAULT 0,
                       `fecha_importacion` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                       PRIMARY KEY (`id`),
                       KEY `idx_importacion_fecha` (`fecha_importacion`),
                       KEY `idx_importacion_tipo` (`tipo`),
                       KEY `fk_imp_instructor_idx` (`instructor_id`),
                       CONSTRAINT `fk_imp_usuario`
                           FOREIGN KEY (`usuario_id`) REFERENCES `usuario` (`id`),
                       CONSTRAINT `fk_imp_instructor`
                           FOREIGN KEY (`instructor_id`) REFERENCES `usuario` (`id`)
                     ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
                );
            }

        } catch (PDOException $e) {
            error_log('[SCIA][Database] Error en ensureMigrationsApplied: ' . $e->getMessage());
            // No detiene el sistema: las migraciones fallidas se loguean y continúan
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
            error_log('[SCIA][Database] Error en autoInitDatabase: ' . $e->getMessage());
            die("Error al inicializar la base de datos. Contacte al administrador.");
        }
    }

    /**
     * Carga y ejecuta el archivo SQL de migración
     */
    private function runMigrationScript(PDO $pdo): void {
        $sqlPath = defined('ROOT_PATH') 
            ? ROOT_PATH . '/migrations/db_ingreso_aprendices_27_08.sql' 
            : __DIR__ . '/../migrations/db_ingreso_aprendices_27_08.sql';
        
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
            error_log('[SCIA][Database] Error ejecutando migración SQL: ' . $e->getMessage());
            die("Error al aplicar la migración inicial. Contacte al administrador.");
        }
    }
}

