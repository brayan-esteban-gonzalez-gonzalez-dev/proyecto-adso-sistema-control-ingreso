-- =============================================================================
-- BASE DE DATOS: db_ingreso_aprendices
-- Sistema de Control e Ingreso de Aprendices (SENA)
-- Script unificado y consolidado de migraciones (001 a 008)
-- =============================================================================

SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0;
SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0;
SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- -----------------------------------------------------
-- Creación y selección de la base de datos
-- -----------------------------------------------------
CREATE DATABASE IF NOT EXISTS `db_ingreso_aprendices`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `db_ingreso_aprendices`;

-- -----------------------------------------------------
-- Eliminación de tablas en orden inverso para evitar error #1451
-- -----------------------------------------------------
DROP TABLE IF EXISTS `estado_excusa`;
DROP TABLE IF EXISTS `excusa`;
DROP TABLE IF EXISTS `inasistencia`;
DROP TABLE IF EXISTS `asistencia`;
DROP TABLE IF EXISTS `sesion`;
DROP TABLE IF EXISTS `instructor_competencia`;
DROP TABLE IF EXISTS `competencia`;
DROP TABLE IF EXISTS `Usuario`;
DROP TABLE IF EXISTS `Ficha`;
DROP TABLE IF EXISTS `jornada`;
DROP TABLE IF EXISTS `Programa`;
DROP TABLE IF EXISTS `Rol`;

-- -----------------------------------------------------
-- 1. Tabla: Rol
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `Rol` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(45) NOT NULL,
  `descripcion` VARCHAR(255) NULL,
  PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 2. Tabla: Programa
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `Programa` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(100) NOT NULL,
  `descripcion` TEXT NULL,
  PRIMARY KEY (`id`)
) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 3. Tabla: Jornada
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `jornada` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(45) NOT NULL,
  `hora_inicio` TIME NOT NULL,
  `hora_fin` TIME NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_jornada_nombre` (`nombre`)
) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 4. Tabla: Ficha
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `Ficha` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `codigo` INT(7) NOT NULL,
  `Programa_id` INT NOT NULL,
  `jornada_id` INT NULL,
  `instructor_id` INT NOT NULL,
  `estado` ENUM('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (`id`),
  INDEX `fk_Ficha_Programa1_idx` (`Programa_id` ASC),
  INDEX `fk_Ficha_jornada_idx` (`jornada_id` ASC),
  INDEX `fk_Ficha_Instructor_idx` (`instructor_id` ASC),
  CONSTRAINT `fk_Ficha_Programa1`
    FOREIGN KEY (`Programa_id`)
    REFERENCES `Programa` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_ficha_jornada`
    FOREIGN KEY (`jornada_id`)
    REFERENCES `jornada` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_Ficha_Instructor`
    FOREIGN KEY (`instructor_id`)
    REFERENCES `Usuario` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION
) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 5. Tabla: Usuario
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `Usuario` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `nombre` VARCHAR(45) NOT NULL,
  `apellido` VARCHAR(45) NOT NULL,
  `identificacion` BIGINT(20) NOT NULL,
  `email` VARCHAR(100) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `Rol_id` INT NOT NULL,
  `Ficha_id` INT NULL,
  `codigo_llavero` VARCHAR(50) NULL,
  `estado` ENUM('Activo','Inactivo') NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (`id`),
  UNIQUE INDEX `email_UNIQUE` (`email` ASC),
  UNIQUE INDEX `identificacion_UNIQUE` (`identificacion` ASC),
  UNIQUE INDEX `uq_usuario_codigo_llavero` (`codigo_llavero` ASC),
  INDEX `fk_Usuario_Rol_idx` (`Rol_id` ASC),
  INDEX `fk_Usuario_Ficha1_idx` (`Ficha_id` ASC),
  CONSTRAINT `fk_Usuario_Rol`
    FOREIGN KEY (`Rol_id`)
    REFERENCES `Rol` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_Usuario_Ficha1`
    FOREIGN KEY (`Ficha_id`)
    REFERENCES `Ficha` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION
) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 6. Tabla: Competencia
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `competencia` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `Programa_id` INT NOT NULL,
  `Instructor_id` INT NULL,
  `nombre` VARCHAR(100) NOT NULL,
  `descripcion` TEXT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_competencia_programa_nombre` (`Programa_id`, `nombre`),
  CONSTRAINT `fk_competencia_programa`
    FOREIGN KEY (`Programa_id`)
    REFERENCES `Programa` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_competencia_instructor`
    FOREIGN KEY (`Instructor_id`)
    REFERENCES `Usuario` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION
) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 7. Tabla: instructor_competencia (Legacy / Sincronización)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `instructor_competencia` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `Instructor_id` INT NOT NULL,
  `Competencia_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_instructor_competencia` (`Instructor_id`, `Competencia_id`),
  CONSTRAINT `fk_ic_usuario`
    FOREIGN KEY (`Instructor_id`)
    REFERENCES `Usuario` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_ic_competencia`
    FOREIGN KEY (`Competencia_id`)
    REFERENCES `competencia` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION
) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 8. Tabla: Sesion
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `sesion` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `Ficha_id` INT NOT NULL,
  `Competencia_id` INT NOT NULL,
  `Instructor_id` INT NOT NULL,
  `fecha` DATE NOT NULL,
  `hora_inicio` TIME NOT NULL,
  `hora_fin` TIME NOT NULL,
  `estado` ENUM('Activo','Cancelado','Finalizada') NOT NULL DEFAULT 'Activo',
  PRIMARY KEY (`id`),
  INDEX `fk_sesion_ficha_idx` (`Ficha_id` ASC),
  INDEX `fk_sesion_competencia_idx` (`Competencia_id` ASC),
  INDEX `fk_sesion_instructor_idx` (`Instructor_id` ASC),
  CONSTRAINT `fk_sesion_ficha`
    FOREIGN KEY (`Ficha_id`)
    REFERENCES `Ficha` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_sesion_competencia`
    FOREIGN KEY (`Competencia_id`)
    REFERENCES `competencia` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_sesion_instructor`
    FOREIGN KEY (`Instructor_id`)
    REFERENCES `Usuario` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `chk_sesion_rango` CHECK (`hora_inicio` < `hora_fin`)
) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 9. Tabla: Asistencia
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `asistencia` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `fecha` DATE NOT NULL,
  `hora_entrada` TIME NULL,
  `hora_salida` TIME NULL,
  `Usuario_id` INT NOT NULL,
  `Sesion_id` INT NOT NULL,
  `registrado_por` INT NOT NULL,
  `estado` ENUM('Activo','Completado') NOT NULL DEFAULT 'Activo',
  `codigo_llavero` VARCHAR(50) NULL COMMENT 'Snapshot del llavero al momento del registro',
  `minutos_retardo` INT NOT NULL DEFAULT 0,
  `minutos_anticipacion` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  INDEX `fk_asistencia_usuario_idx` (`Usuario_id` ASC),
  INDEX `fk_asistencia_sesion_idx` (`Sesion_id` ASC),
  INDEX `fk_asistencia_registro_idx` (`registrado_por` ASC),
  UNIQUE KEY `uq_asistencia_usuario_sesion` (`Usuario_id`, `Sesion_id`),
  CONSTRAINT `fk_asistencia_usuario`
    FOREIGN KEY (`Usuario_id`)
    REFERENCES `Usuario` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_asistencia_sesion`
    FOREIGN KEY (`Sesion_id`)
    REFERENCES `sesion` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_asistencia_registro`
    FOREIGN KEY (`registrado_por`)
    REFERENCES `Usuario` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION
) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 10. Tabla: Inasistencia
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `inasistencia` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `Usuario_id` INT NOT NULL,
  `Ficha_id` INT NOT NULL,
  `Sesion_id` INT NOT NULL,
  `fecha` DATE NOT NULL,
  `generado_por` VARCHAR(50) NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_inasistencia_ficha_idx` (`Ficha_id` ASC),
  INDEX `fk_inasistencia_usuario_idx` (`Usuario_id` ASC),
  INDEX `fk_inasistencia_sesion_idx` (`Sesion_id` ASC),
  UNIQUE KEY `uq_inasistencia_usuario_sesion` (`Usuario_id`, `Sesion_id`),
  CONSTRAINT `fk_inasistencia_ficha`
    FOREIGN KEY (`Ficha_id`)
    REFERENCES `Ficha` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_inasistencia_usuario`
    FOREIGN KEY (`Usuario_id`)
    REFERENCES `Usuario` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_inasistencia_sesion`
    FOREIGN KEY (`Sesion_id`)
    REFERENCES `sesion` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION
) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 11. Tabla: Excusa
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `excusa` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `fecha` DATE NOT NULL,
  `motivo` TEXT NOT NULL,
  `evidencia` VARCHAR(255) NULL,
  `Usuario_id` INT NOT NULL,
  `Asistencia_id` INT NULL,
  `Inasistencia_id` INT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_Excusa_Usuario1_idx` (`Usuario_id` ASC),
  INDEX `fk_Excusa_Asistencia_idx` (`Asistencia_id` ASC),
  INDEX `fk_Excusa_Inasistencia_idx` (`Inasistencia_id` ASC),
  CONSTRAINT `fk_Excusa_Usuario1`
    FOREIGN KEY (`Usuario_id`)
    REFERENCES `Usuario` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_Excusa_Asistencia`
    FOREIGN KEY (`Asistencia_id`)
    REFERENCES `asistencia` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_Excusa_Inasistencia`
    FOREIGN KEY (`Inasistencia_id`)
    REFERENCES `inasistencia` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `chk_excusa_referencia` CHECK (
    (`Asistencia_id` IS NOT NULL AND `Inasistencia_id` IS NULL) OR
    (`Asistencia_id` IS NULL AND `Inasistencia_id` IS NOT NULL)
  )
) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- 12. Tabla: Estado_Excusa
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS `estado_excusa` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `fecha` DATE NULL,
  `respuesta` TEXT NULL,
  `estado` ENUM('Aprobada', 'Rechazada') NULL,
  `Excusa_id` INT NOT NULL,
  `Instructor_id` INT NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `fk_Estado_Excusa_Excusa1_idx` (`Excusa_id` ASC),
  INDEX `fk_Estado_Excusa_Usuario1_idx` (`Instructor_id` ASC),
  CONSTRAINT `fk_Estado_Excusa_Excusa1`
    FOREIGN KEY (`Excusa_id`)
    REFERENCES `excusa` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION,
  CONSTRAINT `fk_Estado_Excusa_Usuario1`
    FOREIGN KEY (`Instructor_id`)
    REFERENCES `Usuario` (`id`)
    ON DELETE NO ACTION
    ON UPDATE NO ACTION
) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------
-- SEED DATA (Datos iniciales)
-- -----------------------------------------------------

-- Roles iniciales
INSERT INTO `Rol` (`id`, `nombre`, `descripcion`) VALUES
(1, 'Administrador', 'Acceso total al sistema'),
(2, 'Instructor', 'Gestiona fichas y aprendices'),
(3, 'Aprendiz', 'Registro de ingreso/salida')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`), `descripcion` = VALUES(`descripcion`);

-- Jornadas estándar
INSERT INTO `jornada` (`id`, `nombre`, `hora_inicio`, `hora_fin`) VALUES
(1, UNHEX('4D61C3B1616E61'), '06:00:00', '12:00:00'),
(2, 'Tarde', '12:00:00', '18:00:00'),
(3, 'Noche', '18:00:00', '21:00:00')
ON DUPLICATE KEY UPDATE
  `nombre` = VALUES(`nombre`),
  `hora_inicio` = VALUES(`hora_inicio`),
  `hora_fin` = VALUES(`hora_fin`);
-- Usuarios iniciales de prueba (claves cifradas con password_hash: admin123, instructor123, aprendiz123)
INSERT INTO `Usuario` (`id`, `nombre`, `apellido`, `identificacion`, `email`, `password`, `Rol_id`, `Ficha_id`, `codigo_llavero`, `estado`) VALUES
(1, 'Admin', 'SENA', 1000000001, 'admin@sena.edu.co', '$2y$10$3g/ZtekvGBqB/3yyY.LoJeUdTaKpwDxgoyNLiy15Faw5JSn4cTsn2', 1, NULL, NULL, 'Activo'),
(2, 'Ana', 'Garcia', 1000000002, 'instructor@sena.edu.co', '$2y$10$b2o2s5tejt3.T7xxa03HauzfhoPGIWbKjWmwwnkftyLlQxE0riz7K', 2, NULL, NULL, 'Activo'),
(3, 'Luis', 'Perez', 1000000003, 'aprendiz@sena.edu.co', '$2y$10$IbO3K/Fx1zqsDHPHIM5r8OGqaIrQlq6xBq1/h5rDFY6xZZH3.Lp6O', 3, NULL, 'LL-001', 'Activo')
ON DUPLICATE KEY UPDATE
  `nombre` = VALUES(`nombre`),
  `apellido` = VALUES(`apellido`),
  `password` = VALUES(`password`),
  `Rol_id` = VALUES(`Rol_id`),
  `codigo_llavero` = VALUES(`codigo_llavero`),
  `estado` = VALUES(`estado`);

SET SQL_MODE=@OLD_SQL_MODE;
SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS;
SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS;
