-- =============================================================================
-- Migración 006: Estado de Inasistencias
-- Sistema de Control de Ingreso de Aprendices (SCIA / SENA)
-- 
-- Agrega columna 'estado' a la tabla 'inasistencia' para reflejar
-- de forma persistente su situación institucional:
--   - 'Injustificada' (por defecto)
--   - 'En revisión' (cuando el aprendiz radica una excusa médica)
--   - 'Justificada' (cuando el instructor o admin aprueba la excusa)
-- =============================================================================

USE `db_ingreso_aprendices`;

-- 1. Agregar columna estado con valor por defecto 'Injustificada'
ALTER TABLE `inasistencia` 
ADD COLUMN IF NOT EXISTS `estado` ENUM('Injustificada', 'En revisión', 'Justificada') NOT NULL DEFAULT 'Injustificada'
AFTER `generado_por`;

-- 2. Índice para consultas y filtros por estado
ALTER TABLE `inasistencia` 
ADD INDEX IF NOT EXISTS `idx_inasistencia_estado` (`estado` ASC);

-- 3. Sincronización inicial de estados con excusas ya existentes
UPDATE `inasistencia` i
INNER JOIN `excusa` e ON e.`Inasistencia_id` = i.`id`
LEFT JOIN `estado_excusa` ee ON ee.`Excusa_id` = e.`id`
SET i.`estado` = CASE
    WHEN ee.`estado` = 'Aprobada' THEN 'Justificada'
    WHEN ee.`estado` = 'Rechazada' THEN 'Injustificada'
    ELSE 'En revisión'
END;
