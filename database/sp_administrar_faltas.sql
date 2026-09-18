DELIMITER //

CREATE PROCEDURE `sp_administrar_faltas`(IN p_fecha DATE)
BEGIN
    DECLARE v_dia_semana VARCHAR(15);

    SET v_dia_semana = CASE DAYOFWEEK(p_fecha)
        WHEN 2 THEN 'Lunes'
        WHEN 3 THEN 'Martes'
        WHEN 4 THEN 'Miercoles'
        WHEN 5 THEN 'Jueves'
        WHEN 6 THEN 'Viernes'
        WHEN 7 THEN 'Sabado'
        ELSE 'Domingo'
    END;

    -- 2. Procesar Inasistencias
    -- Busca aprendices que tienen horario este día (ej. 6am-12pm o 12pm-6pm)
    -- pero NO tienen registro en la tabla ingresos_asistencia para esa fecha.
    INSERT INTO ingresos_asistencia (id_aprendiz, fecha, estado, minutos_retardo, minutos_salida_anticipada)
    SELECT 
        a.id_aprendiz, 
        p_fecha, 
        'Inasistencia', 
        0, 
        0
    FROM aprendices a
    JOIN fichas f ON a.id_ficha = f.id_ficha
    JOIN horarios h ON f.id_ficha = h.id_ficha AND h.dia_semana = v_dia_semana
    LEFT JOIN ingresos_asistencia ia ON a.id_aprendiz = ia.id_aprendiz AND ia.fecha = p_fecha
    WHERE ia.id_ingreso IS NULL;

    -- 3. Procesar Retardos
    -- Actualiza a los que llegaron despues de la hora de entrada + la tolerancia (ej. 15 min)
    UPDATE ingresos_asistencia ia
    JOIN aprendices a ON ia.id_aprendiz = a.id_aprendiz
    JOIN horarios h ON a.id_ficha = h.id_ficha AND h.dia_semana = v_dia_semana
    SET 
        ia.estado = 'Retardo',
        ia.minutos_retardo = TIMESTAMPDIFF(MINUTE, h.hora_entrada, ia.hora_entrada)
    WHERE ia.fecha = p_fecha 
      AND ia.hora_entrada IS NOT NULL
      -- Si la hora de entrada es mayor a la hora permitida (hora_entrada + tolerancia)
      AND ia.hora_entrada > ADDTIME(h.hora_entrada, SEC_TO_TIME(h.tolerancia_minutos * 60))
      AND ia.estado = 'A_Tiempo'; -- Solo actualiza si estaba A_Tiempo

    -- 4. Procesar Salidas Tempranas
    -- Actualiza a los que salieron antes de la hora de salida de su horario (ej. antes de las 12pm o 6pm)
    UPDATE ingresos_asistencia ia
    JOIN aprendices a ON ia.id_aprendiz = a.id_aprendiz
    JOIN horarios h ON a.id_ficha = h.id_ficha AND h.dia_semana = v_dia_semana
    SET 
        -- Si ya tenía retardo, se queda con estado Retardo, pero igual guardamos los minutos que salio temprano
        ia.estado = CASE WHEN ia.estado = 'Retardo' THEN 'Retardo' ELSE 'Salida_Temprana' END,
        ia.minutos_salida_anticipada = TIMESTAMPDIFF(MINUTE, ia.hora_salida, h.hora_salida)
    WHERE ia.fecha = p_fecha 
      AND ia.hora_salida IS NOT NULL
      AND ia.hora_salida < h.hora_salida
      AND ia.estado IN ('A_Tiempo', 'Retardo');

END //

DELIMITER ;
