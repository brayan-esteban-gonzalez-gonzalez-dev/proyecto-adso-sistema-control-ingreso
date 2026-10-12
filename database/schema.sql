SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "-05:00";

-- Crear la base de datos
CREATE DATABASE IF NOT EXISTS `sistema_asistencia_rfid`
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `sistema_asistencia_rfid`;


-- Tabla: roles
CREATE TABLE IF NOT EXISTS `roles` (
    `id_rol`  INT AUTO_INCREMENT PRIMARY KEY,
    `nombre`  VARCHAR(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: usuarios
CREATE TABLE IF NOT EXISTS `usuarios` (
    `id_usuario`     INT AUTO_INCREMENT PRIMARY KEY,
    `id_rol`         INT NOT NULL,
    `num_documento`  VARCHAR(20)  NOT NULL,
    `nombre`         VARCHAR(100) NOT NULL,
    `apellido`       VARCHAR(100) NOT NULL,
    `correo`         VARCHAR(150) NOT NULL,
    `password`       VARCHAR(255) NOT NULL,
    `estado`         ENUM('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
    `creado_en`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY `uq_documento` (`num_documento`),
    UNIQUE KEY `uq_correo`    (`correo`),
    INDEX `idx_rol`           (`id_rol`),
    INDEX `idx_estado`        (`estado`),

    CONSTRAINT `fk_usuarios_rol`
        FOREIGN KEY (`id_rol`) REFERENCES `roles`(`id_rol`)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Tabla: fichas
CREATE TABLE IF NOT EXISTS `fichas` (
    `id_ficha`              INT AUTO_INCREMENT PRIMARY KEY,
    `codigo_ficha`          VARCHAR(20)  NOT NULL,
    `nombre_programa`       VARCHAR(150) NOT NULL,
    `id_instructor_lider`   INT DEFAULT NULL,

    UNIQUE KEY `uq_codigo_ficha`  (`codigo_ficha`),
    INDEX `idx_instructor_lider` (`id_instructor_lider`),

    CONSTRAINT `fk_fichas_instructor`
        FOREIGN KEY (`id_instructor_lider`) REFERENCES `usuarios`(`id_usuario`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: horarios
CREATE TABLE IF NOT EXISTS `horarios` (
    `id_horario`           INT AUTO_INCREMENT PRIMARY KEY,
    `id_ficha`             INT NOT NULL,
    `dia_semana`           ENUM('Lunes','Martes','Miercoles','Jueves','Viernes','Sabado') NOT NULL,
    `hora_entrada`         TIME NOT NULL,
    `hora_salida`          TIME NOT NULL,
    `tolerancia_minutos`   INT NOT NULL DEFAULT 15,

    INDEX `idx_ficha_dia` (`id_ficha`, `dia_semana`),

    CONSTRAINT `fk_horarios_ficha`
        FOREIGN KEY (`id_ficha`) REFERENCES `fichas`(`id_ficha`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Tabla: aprendices
CREATE TABLE IF NOT EXISTS `aprendices` (
    `id_aprendiz`  INT AUTO_INCREMENT PRIMARY KEY,
    `id_usuario`   INT NOT NULL,
    `id_ficha`     INT NOT NULL,
    `codigo_rfid`  VARCHAR(50) DEFAULT NULL,

    UNIQUE KEY `uq_usuario`    (`id_usuario`),
    UNIQUE KEY `uq_codigo_rfid`(`codigo_rfid`),
    INDEX `idx_ficha`          (`id_ficha`),

    CONSTRAINT `fk_aprendices_usuario`
        FOREIGN KEY (`id_usuario`) REFERENCES `usuarios`(`id_usuario`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_aprendices_ficha`
        FOREIGN KEY (`id_ficha`) REFERENCES `fichas`(`id_ficha`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Tabla: ingresos_asistencia
CREATE TABLE IF NOT EXISTS `ingresos_asistencia` (
    `id_ingreso`                INT AUTO_INCREMENT PRIMARY KEY,
    `id_aprendiz`               INT NOT NULL,
    `fecha`                     DATE NOT NULL,
    `hora_entrada`              TIME DEFAULT NULL,
    `hora_salida`               TIME DEFAULT NULL,
    `estado`                    ENUM('A_Tiempo','Retardo','Salida_Temprana','Inasistencia','Justificado') NOT NULL DEFAULT 'A_Tiempo',
    `minutos_retardo`           INT NOT NULL DEFAULT 0,
    `minutos_salida_anticipada` INT NOT NULL DEFAULT 0,

    INDEX `idx_aprendiz_fecha` (`id_aprendiz`, `fecha`),
    INDEX `idx_fecha`          (`fecha`),
    INDEX `idx_estado`         (`estado`),

    CONSTRAINT `fk_ingresos_aprendiz`
        FOREIGN KEY (`id_aprendiz`) REFERENCES `aprendices`(`id_aprendiz`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tabla: excusas_medicas
CREATE TABLE IF NOT EXISTS `excusas_medicas` (
    `id_excusa`              INT AUTO_INCREMENT PRIMARY KEY,
    `id_aprendiz`            INT NOT NULL,
    `id_ingreso`             INT DEFAULT NULL,
    `fecha_inicio`           DATE NOT NULL,
    `fecha_fin`              DATE NOT NULL,
    `motivo`                 TEXT NOT NULL,
    `archivo_adjunto`        VARCHAR(255) NOT NULL,
    `estado`                 ENUM('Pendiente','Aprobada','Rechazada') NOT NULL DEFAULT 'Pendiente',
    `id_instructor_revisor`  INT DEFAULT NULL,
    `comentario_revision`    TEXT DEFAULT NULL,
    `creado_en`              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    INDEX `idx_aprendiz`          (`id_aprendiz`),
    INDEX `idx_estado_excusa`     (`estado`),
    INDEX `idx_instructor_rev`    (`id_instructor_revisor`),

    CONSTRAINT `fk_excusas_aprendiz`
        FOREIGN KEY (`id_aprendiz`) REFERENCES `aprendices`(`id_aprendiz`)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_excusas_ingreso`
        FOREIGN KEY (`id_ingreso`) REFERENCES `ingresos_asistencia`(`id_ingreso`)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_excusas_instructor`
        FOREIGN KEY (`id_instructor_revisor`) REFERENCES `usuarios`(`id_usuario`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- Roles del sistema
INSERT INTO `roles` (`id_rol`, `nombre`) VALUES
(1, 'Administrador'),
(2, 'Instructor'),
(3, 'Aprendiz');


-- Usuario administrador por defecto
-- Credenciales: documento=admin | contraseña=Admin123*
INSERT INTO `usuarios` (`id_rol`, `num_documento`, `nombre`, `apellido`, `correo`, `password`, `estado`) VALUES
(1, 'admin', 'Administrador', 'Sistema', 'admin@sistema.local',
 '$2y$10$h0izUo8DRzAh1frZlc3xkOZmlcvMPmRkBpVaDOubrqO7/bvAogykK', 'Activo');

COMMIT;
