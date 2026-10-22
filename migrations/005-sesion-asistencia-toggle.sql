-- =============================================================================
-- Migración 005: Sesión de Asistencia — Toggle Instructor
-- Permite crear sesiones sin hora_fin (sesiones "abiertas") para el flujo
-- de encender/apagar la toma de asistencia.
-- =============================================================================

-- 1. Eliminar el CHECK que impide hora_fin NULL
ALTER TABLE `sesion` DROP CHECK `chk_sesion_rango`;

-- 2. Hacer hora_fin NULLABLE (para sesiones abiertas por el toggle)
ALTER TABLE `sesion` MODIFY `hora_fin` TIME NULL DEFAULT NULL;

-- 3. Re-crear el CHECK solo cuando hora_fin tiene valor
--    (permite hora_fin NULL, pero si ambas están definidas, inicio < fin)
ALTER TABLE `sesion` ADD CONSTRAINT `chk_sesion_rango`
  CHECK (`hora_fin` IS NULL OR `hora_inicio` < `hora_fin`);
