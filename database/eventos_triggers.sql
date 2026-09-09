USE `sistema_asistencia_rfid`;

-- Habilitar el scheduler de eventos globalmente para que el evento se ejecute automáticamente
SET GLOBAL event_scheduler = ON;

-- ============================================================
-- 1. TRIGGER: Aprobar excusa médica actualiza la asistencia
-- ============================================================
DELIMITER //

DROP TRIGGER IF EXISTS trg_aprobar_excusa //

CREATE TRIGGER trg_aprobar_excusa
AFTER UPDATE ON excusas_medicas
FOR EACH ROW
BEGIN
    -- Si el estado cambia a 'Aprobada' y antes no lo estaba
    IF NEW.estado = 'Aprobada' AND OLD.estado != 'Aprobada' THEN
        
        -- Si la excusa está vinculada directamente a un registro de ingreso
        IF NEW.id_ingreso IS NOT NULL THEN
            UPDATE ingresos_asistencia 
            SET estado = 'Justificado' 
            WHERE id_ingreso = NEW.id_ingreso;
        ELSE
            -- Si no está vinculada a un registro específico, actualizar todo el rango de fechas
            UPDATE ingresos_asistencia 
            SET estado = 'Justificado'
            WHERE id_aprendiz = NEW.id_aprendiz 
              AND fecha BETWEEN NEW.fecha_inicio AND NEW.fecha_fin
              AND estado IN ('Inasistencia', 'Retardo', 'Salida_Temprana');
        END IF;
        
    END IF;
END //

DELIMITER ;

-- ============================================================
-- 2. EVENTO DIARIO: Marcar Inasistencias (Se ejecuta a las 23:59)
-- ============================================================
DELIMITER //

DROP EVENT IF EXISTS evt_marcar_inasistencias //

CREATE EVENT evt_marcar_inasistencias
ON SCHEDULE EVERY 1 DAY
STARTS (TIMESTAMP(CURRENT_DATE) + INTERVAL 23 HOUR + INTERVAL 59 MINUTE)
DO
BEGIN
    DECLARE v_dia_semana VARCHAR(15);
    
    -- Determinar el día de la semana actual (MySQL DAYOFWEEK: 1=Dom, 2=Lun, etc.)
    SET v_dia_semana = ELT(DAYOFWEEK(CURRENT_DATE), 'Domingo', 'Lunes', 'Martes', 'Miercoles', 'Jueves', 'Viernes', 'Sabado');
    
    -- Insertar inasistencias para los aprendices que tenían horario hoy y NO registraron asistencia
    INSERT INTO ingresos_asistencia (id_aprendiz, fecha, estado)
    SELECT a.id_aprendiz, CURRENT_DATE, 'Inasistencia'
    FROM aprendices a
    INNER JOIN horarios h ON a.id_ficha = h.id_ficha
    WHERE h.dia_semana = v_dia_semana
      AND NOT EXISTS (
          SELECT 1 
          FROM ingresos_asistencia i 
          WHERE i.id_aprendiz = a.id_aprendiz 
            AND i.fecha = CURRENT_DATE
      );
END //

DELIMITER ;
