SET GLOBAL event_scheduler = ON;

DELIMITER //

CREATE EVENT IF NOT EXISTS `evt_procesar_faltas_diarias`
ON SCHEDULE EVERY 1 DAY
STARTS (TIMESTAMP(CURRENT_DATE) + INTERVAL 1 DAY - INTERVAL 1 MINUTE) -- Se ejecuta a las 11:59 todos los dias
DO
BEGIN
    -- Llama al procedimiento almacenado pasando la fecha de hoy
    CALL sp_administrar_faltas(CURDATE());
END //

DELIMITER ;
