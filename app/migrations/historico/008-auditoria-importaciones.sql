-- =============================================================================
-- Migración 008: Auditoría de Importaciones de Horario
-- Sistema de Control de Ingreso de Aprendices (SCIA / SENA)
--
-- Registra cada importación de horario (RF-HOR-08): quién la hizo, cuándo,
-- tipo de horario, nombre del archivo y totales del reporte.
-- =============================================================================

USE `db_ingreso_aprendices`;

CREATE TABLE IF NOT EXISTS `importacion_horario` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `usuario_id` INT NOT NULL,
    `tipo` ENUM('ficha', 'instructor') NOT NULL,
    `archivo` VARCHAR(255) NOT NULL,
    `anio` SMALLINT NOT NULL,
    `codigo_ficha` VARCHAR(20) NULL,
    `instructor_id` INT NULL,
    `creadas` INT NOT NULL DEFAULT 0,
    `actualizadas` INT NOT NULL DEFAULT 0,
    `omitidas` INT NOT NULL DEFAULT 0,
    `canceladas` INT NOT NULL DEFAULT 0,
    `protegidas` INT NOT NULL DEFAULT 0,
    `errores` TEXT NULL,
    `permutas_confirmadas` TINYINT(1) NOT NULL DEFAULT 0,
    `fecha_importacion` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT `fk_importacion_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `Usuario`(`id`),
    CONSTRAINT `fk_importacion_instructor` FOREIGN KEY (`instructor_id`) REFERENCES `Usuario`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX IF NOT EXISTS `idx_importacion_fecha` ON `importacion_horario` (`fecha_importacion`);
CREATE INDEX IF NOT EXISTS `idx_importacion_tipo` ON `importacion_horario` (`tipo`);
