-- =============================================================================
-- Migración 004: Código de programa de formación
-- =============================================================================

USE `db_ingreso_aprendices`;

ALTER TABLE `Programa`
  ADD COLUMN IF NOT EXISTS `codigo` VARCHAR(20) NULL AFTER `id`;

SET @existe_idx := (
  SELECT COUNT(*)
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'Programa'
    AND index_name = 'uq_programa_codigo'
);

SET @sql_idx := IF(
  @existe_idx = 0,
  'ALTER TABLE `Programa` ADD UNIQUE INDEX `uq_programa_codigo` (`codigo`)',
  'SELECT 1'
);

PREPARE stmt_idx FROM @sql_idx;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;
