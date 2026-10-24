# RF — Gestión de Horarios (SCIA)


## Contexto y alcance

El sistema maneja dos horarios que se cargan desde Excel: el de la ficha, que ven los aprendices, y el del instructor. Los dos alimentan la asistencia: las inasistencias se generan con el id de la ficha y del instructor de la sesión (tablas `sesion`, `asistencia` e `inasistencia`), así que un horario incoherente produce faltas mal asignadas.

Hoy `HorarioController::procesar()` importa un solo tipo de Excel (el de la ficha), guarda lo que puede aunque haya errores y crea un instructor temporal cuando no encuentra el nombre. Estos RF lo cambian: dos tipos de archivo, rechazo completo si hay incongruencias, bloques de 3 horas y manejo de permutas.

## Los dos horarios

| Aspecto | Horario de la ficha | Horario del instructor |
| --- | --- | --- |
| Para quién | Una ficha tiene un horario (el de los aprendices) | Un instructor tiene un horario |
| Quién lo sube | El instructor líder de la ficha (`Ficha.instructor_id`) | El administrador |
| Cada cuánto | Normalmente cada mes | Cada 6 meses |
| Qué trae | Por fecha y bloque: instructor y datos descriptivos (tipo, especialidad, nivel, grupo convergente) | Por fecha y bloque, la ficha que le asignan, en la misma grilla que el de la ficha |
| Quién lo gestiona después | Los instructores de la ficha | El propio instructor |

## Decisiones ya tomadas

Estas respuestas definen los demás requisitos.

| Tema | Decisión |
| --- | --- |
| Dos horarios | El de la ficha lo sube el instructor líder, normalmente cada mes. El del instructor lo sube el administrador y dura 6 meses. |
| Unidad del horario | Bloque de 3 horas. Una jornada de 6 horas tiene 2 bloques (ej. 6-9 y 9-12) y las horas salen de la tabla `jornada`. Un instructor puede estar en dos fichas en la misma jornada. |
| Fuente de la verdad | El Excel nuevo siempre dicta. Los bloques iguales no se actualizan. |
| Excel con incongruencias | Se rechaza completo y se muestra un mensaje por cada problema. Casos: instructor no registrado, instructor a la misma hora en dos fichas, cruces en la misma ficha, horas fuera de la jornada y bloque con dos instructores. |
| Sesiones que ya no vienen | Se cancelan, no se borran. Las que tienen asistencia o inasistencia no se tocan. |
| Permutas | Un instructor puede cambiar fechas con otro, y el otro debe aceptar. Si un Excel nuevo revertiría una permuta, se listan y el administrador confirma antes de aplicarlo. |
| Quién gestiona y resuelve | Los instructores gestionan su propio horario y el de su ficha. Los cambios los resuelven los instructores involucrados y el administrador. Los cruces se corrigen al ingresar el Excel, porque un Excel con cruces se rechaza. |
| Año del Excel | Quien sube el archivo lo indica, con el año actual por defecto. |
| Excusas | La franja es la jornada completa: si una ficha tiene 2 o más instructores en la misma jornada, la excusa requiere doble validación. |
| Relación entre horarios | La ficha crea las sesiones y el horario del instructor las valida. La asistencia se toma abriendo la sesión programada del bloque. |

## Relación entre los dos horarios

El horario del instructor (6 meses) es el marco y el de la ficha (mensual) crea las sesiones: el segundo se valida contra el primero y la asistencia se toma sobre esas sesiones.

&#91;embedded content: flujo entre horarios · 3 roles, 7 pasos\]

| ID | Requisito |
| --- | --- |
| RF-HOR-36 | El horario del instructor guarda, por instructor, sus asignaciones (ficha, fecha y bloque). No crea sesiones. Viene en el mismo formato de grilla que el de la ficha (semanas, días y filas de hora). |
| RF-HOR-37 | Las sesiones, con fecha y hora, salen solo del horario de la ficha. |
| RF-HOR-38 | Al subir el horario de la ficha, el instructor de cada bloque debe tener esa ficha, ese día y ese bloque en su horario de 6 meses. Si no la tiene, o no hay horario cargado para esa fecha, el archivo se rechaza con este mensaje: "El instructor {nombre} no tiene asignada la ficha {código} el {fecha} de {inicio} a {fin}. Pida al administrador que actualice su horario o corrija el Excel." Las diferencias causadas por una permuta siguen RF-HOR-15. |
| RF-HOR-39 | El Excel del instructor pasa las validaciones de RF-HOR-16 a 21 que le apliquen, y además cada ficha de sus bloques debe estar registrada. |
| RF-HOR-40 | Las reglas de sincronización (RF-HOR-09 a 14) valen igual para las asignaciones del instructor: lo igual no se toca, lo distinto se actualiza y lo que ya no viene se cancela sin borrarse. |
| RF-HOR-41 | El instructor abre la asistencia sobre la sesión programada del bloque (ficha, fecha y bloque) que le corresponde y no se crea una sesión nueva. Si no tiene una sesión Activo de esa ficha dentro de las horas del bloque, no puede abrirla. |
| RF-HOR-42 | La hora programada del bloque no cambia al abrir ni al cerrar. La apertura y el cierre reales, que decide el instructor, se guardan aparte. El retardo de 10 minutos se cuenta desde la mayor entre la hora de inicio del bloque y la apertura real. |
| RF-HOR-43 | Una permuta o cesión actualiza también las asignaciones de los instructores, para que lo guardado siga coincidiendo entre sí. |

Requiere una tabla nueva para las asignaciones y columnas nuevas para la apertura y el cierre reales (migración 007).

## Importación del Excel

| ID | Requisito |
| --- | --- |
| RF-HOR-01 | Hay dos tipos de archivo, .xlsx o .xls: horario de la ficha y horario del instructor. Quien sube elige el tipo. |
| RF-HOR-02 | El horario de la ficha lo sube el instructor líder de esa ficha, normalmente una vez al mes, y es de una sola ficha. |
| RF-HOR-03 | El horario del instructor lo sube el administrador y cubre 6 meses. |
| RF-HOR-04 | El sistema detecta la ficha por el código de 7 dígitos del encabezado (ej. 3064749). Si la ficha no está registrada, rechaza el archivo. |
| RF-HOR-05 | El sistema lee la grilla semanal (días, fechas y filas de hora) y agrupa las horas consecutivas en bloques de 3 horas según la jornada de la ficha. |
| RF-HOR-06 | Quien sube el archivo indica el año al subirlo, con el año actual por defecto. El sistema suma un año al pasar de diciembre a enero. |
| RF-HOR-07 | Si el archivo se guarda, muestra un reporte con bloques creados, actualizados, sin cambios, cancelados y protegidos. |
| RF-HOR-08 | Cada importación queda registrada: quién, cuándo, tipo de horario, archivo y totales del reporte. |

## Sincronización con el horario guardado

Un bloque se identifica por ficha, fecha y hora de inicio. Dos bloques son iguales si además coinciden hora de fin, instructor y datos descriptivos (tipo, especialidad, nivel, grupo convergente). Estas reglas se aplican cuando el archivo ya pasó todas las validaciones. Al horario del instructor le valen igual (RF-HOR-40).

| ID | Requisito |
| --- | --- |
| RF-HOR-09 | Bloque que no existe en la base de datos: se crea la sesión en estado Activo. |
| RF-HOR-10 | Bloque igual al guardado: se omite y no se escribe nada en la base de datos. |
| RF-HOR-11 | Misma ficha, fecha y hora de inicio, pero con otro instructor u otros datos: se actualizan solo los campos distintos. |
| RF-HOR-12 | Sesión guardada de esa ficha, dentro del rango de fechas que cubre el Excel, que ya no aparece en él: pasa a Cancelado y se conserva. |
| RF-HOR-13 | Una sesión con asistencia o inasistencia registrada, o abierta en ese momento por el instructor, nunca se modifica ni se cancela por importación. Si el Excel difiere, se lista en el reporte como protegida. |
| RF-HOR-14 | Si un bloque cancelado reaparece en un Excel posterior, la sesión vuelve a Activo. |
| RF-HOR-15 | Si aplicar el Excel revertiría una permuta, antes de guardar se lista cada permuta afectada y el administrador debe confirmar. Sin esa confirmación no se guarda nada. |

## Validaciones de coherencia

Todos los bloques se validan antes de guardar. Si hay una sola incongruencia, el archivo se rechaza completo y no se guarda nada. El mensaje lista todas las incongruencias, no solo la primera, para corregirlas de una vez.

| ID | Requisito | Mensaje que ve quien sube |
| --- | --- | --- |
| RF-HOR-16 | El instructor debe existir en `Usuario` con rol Instructor. Se busca por nombre normalizado (sin tildes, mayúsculas ni espacios dobles). Si no hay coincidencia, o hay más de una, se rechaza. Ya no se crea un instructor temporal. | "El instructor {nombre} no está registrado ({fecha}, {hora}). Regístrelo o corrija el nombre y suba el archivo de nuevo." |
| RF-HOR-17 | Un instructor no puede tener dos bloques en la misma fecha y hora en fichas distintas, ni dentro del archivo ni contra horarios ya guardados. | "El instructor {nombre} ya tiene clase el {fecha} de {inicio} a {fin} en la ficha {código}. No puede estar en dos fichas a la misma hora." |
| RF-HOR-18 | Una ficha no puede tener dos bloques distintos en la misma fecha y hora. | "La ficha {código} tiene dos bloques distintos el {fecha} de {inicio} a {fin}." |
| RF-HOR-19 | Las horas de cada bloque deben estar dentro de la jornada de la ficha (tabla `jornada`). | "El bloque del {fecha} ({inicio}-{fin}) queda fuera de la jornada {nombre} de la ficha {código} ({inicio}-{fin})." |
| RF-HOR-20 | Cada bloque de 3 horas debe tener un solo instructor. | "El bloque del {fecha} de {inicio} a {fin} tiene más de un instructor ({nombre1}, {nombre2}). Debe tener uno solo." |
| RF-HOR-21 | La fecha debe existir y la hora de inicio debe ser menor que la de fin. Si no se puede leer, se rechaza; ya no se asume 07:00-13:00 como hoy. | "No se pudo leer la fecha o la hora del bloque en la fila {n}." |

## Permutas y cambios manuales

| ID | Requisito |
| --- | --- |
| RF-HOR-22 | No se guardan sesiones en conflicto: un instructor que quedaría en dos fichas a la misma hora se rechaza tanto al importar como al cambiar el horario a mano. |
| RF-HOR-23 | Los instructores gestionan su propio horario y el de su ficha. Pueden proponer una permuta (intercambiar fecha y hora de dos sesiones con otro instructor) o ceder un bloque a otro instructor. |
| RF-HOR-24 | El otro instructor debe aceptar la permuta o la cesión. Si no la acepta, no se aplica. |
| RF-HOR-25 | La permuta se aplica completa o no se aplica (las dos sesiones a la vez) y antes de guardar valida de nuevo cruces, jornada y ficha. |
| RF-HOR-26 | El administrador puede hacer estos mismos cambios en cualquier horario. |
| RF-HOR-27 | Una sesión con asistencia registrada no se puede mover ni reasignar. |
| RF-HOR-28 | Todo cambio manual queda registrado (quién, cuándo, valor anterior y nuevo, motivo) y deja la sesión marcada como permutada, para detectar cuándo un Excel la revertiría (RF-HOR-15). |

## Consulta de horarios y relación con la asistencia

| ID | Requisito |
| --- | --- |
| RF-HOR-29 | El aprendiz ve el horario de su ficha en solo lectura, por semana y por mes, con instructor, hora y estado de cada bloque. |
| RF-HOR-30 | El instructor ve su propio horario (todas sus fichas) y el de su ficha, por semana y por mes, con filtro por ficha y por estado. |
| RF-HOR-31 | El administrador ve todos los horarios, con filtro por ficha e instructor. |
| RF-HOR-32 | Las sesiones canceladas y las permutadas se muestran marcadas, no se ocultan, para que se sepa que cambió la clase. |
| RF-HOR-33 | La asistencia se toma sobre una sesión Activo del horario, y la inasistencia se genera con la ficha y el instructor de esa sesión: los aprendices activos de la ficha sin marcación de entrada. Cómo la abre el instructor: RF-HOR-41. |
| RF-HOR-34 | Para excusas, la franja es la jornada completa: si la ficha tiene 2 o más instructores distintos en la misma jornada y fecha (ej. 6-9 y 9-12), la excusa requiere doble validación. |
| RF-HOR-35 | Cambiar el horario nunca regenera ni borra inasistencias ya creadas. |

## Puntos abiertos

Cada uno trae mi propuesta para que solo la confirmes o la cambies.

1. **Qué trae el Excel del instructor.** Viene como la captura. Asumo que el encabezado identifica al instructor, que cada celda trae la ficha y que las fechas cubren los 6 meses. Confírmalo con un ejemplo.
2. **Cambios en el horario del instructor con sesiones ya creadas.** Propongo listar las sesiones futuras afectadas, que el administrador confirme y que esas sesiones pasen a Cancelado, para que el líder suba el horario corregido de la ficha.
3. **Bloque programado que el instructor nunca abre.** Propongo que quede marcado como no tomado y que se avise al administrador, sin generar inasistencias automáticas.
4. **Permisos de subida.** ¿El administrador también puede subir el horario de una ficha? ¿Un instructor que no es líder puede? Propongo que el administrador sí, que el instructor no líder no, y que el líder solo suba el de su ficha.
5. **Permutas revertidas cuando sube el líder.** Confirma el administrador (RF-HOR-15). Propongo que el archivo quede pendiente hasta que confirme.
6. **Jornadas que no miden 6 horas.** Propongo bloques de 3 horas desde el inicio de la jornada y que el último pueda ser más corto.