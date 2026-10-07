-- =============================================================================
-- Migración 003: Modificaciones a la tabla `sesion` para módulo de Horarios
-- =============================================================================

USE `db_ingreso_aprendices`;

-- 1. Modificar columna Competencia_id para permitir valores NULL
-- (El horario importado desde Excel puede no especificar competencia)
ALTER TABLE `sesion`
  MODIFY `Competencia_id` INT NULL;

-- 2. Agregar columnas para la metadata del instructor asignado en la sesión
ALTER TABLE `sesion`
  ADD COLUMN IF NOT EXISTS `tipo_instructor` VARCHAR(50) NULL AFTER `estado`,
  ADD COLUMN IF NOT EXISTS `especialidad` VARCHAR(50) NULL AFTER `tipo_instructor`,
  ADD COLUMN IF NOT EXISTS `nivel` VARCHAR(50) NULL AFTER `especialidad`,
  ADD COLUMN IF NOT EXISTS `grupo_convergente` VARCHAR(100) NULL AFTER `nivel`;
