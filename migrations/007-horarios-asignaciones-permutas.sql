-- =============================================================================
-- Migración 007: Horarios, Asignaciones de Instructor y Permutas
-- Sistema de Control de Ingreso de Aprendices (SCIA / SENA)
--
-- Agrega soporte para:
--   - Tabla `asignacion_instructor` (horario semestral del instructor)
--   - Columnas `apertura_real` y `cierre_real` en `sesion`
--   - Ampliación de estados de sesión ('Cancelado', 'Permutado')
--   - Tabla `permuta_sesion` (registro de intercambios y cesiones)
-- =============================================================================

USE `db_ingreso_aprendices`;

-- ============================================================================
-- 1. Tabla asignacion_instructor (horario de 6 meses del instructor)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `asignacion_instructor` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `instructor_id` INT NOT NULL,
    `ficha_id` INT NOT NULL,
    `fecha` DATE NOT NULL,
    `hora_inicio` TIME NOT NULL,
    `hora_fin` TIME NOT NULL,
    `estado` ENUM('Activo', 'Cancelado') NOT NULL DEFAULT 'Activo',
    `creado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `actualizado_en` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT `fk_asignacion_instructor_usuario` FOREIGN KEY (`instructor_id`) REFERENCES `Usuario`(`id`),
    CONSTRAINT `fk_asignacion_instructor_ficha` FOREIGN KEY (`ficha_id`) REFERENCES `Ficha`(`id`),
    UNIQUE KEY `uk_asignacion_unica` (`instructor_id`, `ficha_id`, `fecha`, `hora_inicio`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- 2. Modificar tabla sesion: agregar apertura_real, cierre_real y nuevos estados
-- ============================================================================

-- Agregar columnas de apertura y cierre real
ALTER TABLE `sesion` 
    ADD COLUMN IF NOT EXISTS `apertura_real` TIME NULL AFTER `hora_fin`,
    ADD COLUMN IF NOT EXISTS `cierre_real` TIME NULL AFTER `apertura_real`;

-- Modificar ENUM de estado para incluir 'Cancelado' y 'Permutado'
ALTER TABLE `sesion` 
    MODIFY COLUMN `estado` ENUM('Activo', 'Cancelado', 'Permutado') NOT NULL DEFAULT 'Activo';

-- ============================================================================
-- 3. Tabla permuta_sesion (registro de solicitudes y resoluciones)
-- ============================================================================
CREATE TABLE IF NOT EXISTS `permuta_sesion` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `sesion_origen_id` INT NOT NULL,
    `sesion_destino_id` INT NOT NULL,
    `instructor_solicitante_id` INT NOT NULL,
    `instructor_receptor_id` INT NOT NULL,
    `estado` ENUM('Pendiente', 'Aprobada', 'Rechazada') NOT NULL DEFAULT 'Pendiente',
    `motivo` TEXT NULL,
    `fecha_solicitud` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `fecha_resolucion` TIMESTAMP NULL,
    CONSTRAINT `fk_permuta_sesion_origen` FOREIGN KEY (`sesion_origen_id`) REFERENCES `sesion`(`id`),
    CONSTRAINT `fk_permuta_sesion_destino` FOREIGN KEY (`sesion_destino_id`) REFERENCES `sesion`(`id`),
    CONSTRAINT `fk_permuta_solicitante` FOREIGN KEY (`instructor_solicitante_id`) REFERENCES `Usuario`(`id`),
    CONSTRAINT `fk_permuta_receptor` FOREIGN KEY (`instructor_receptor_id`) REFERENCES `Usuario`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
