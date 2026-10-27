# Reporte de seguridad — Track A (importación de horarios)

Fecha: 26 de octubre de 2026
Alcance: código del motor de importación implementado por Desarrollador 1 y el punto de entrada que lo usa.
Archivos revisados: `controllers/HorarioController.php`, `helpers/HorarioParser.php`, `helpers/HorarioValidator.php`, `helpers/HorarioSincronizador.php`, `helpers/HorarioReporte.php`, `models/AsignacionInstructor.php`, `models/ImportacionHorario.php`, `models/PermutaSesion.php` (método agregado), `models/Sesion.php` (solo la parte que el sincronizador llama), `views/horarios/importar.php`, `views/horarios/errores_validacion.php`, `views/horarios/confirmar_permutas.php`, `views/horarios/resultado.php`.

No es una auditoría del sistema completo. No se ejecutó la base de datos ni se subió un Excel real.

## Resumen

El flujo nuevo exige administrador y token CSRF, usa sentencias preparadas y escapa la salida en las vistas nuevas. Los problemas graves están en la carga del Excel (solo se mira la extensión), en la filtración de excepciones y en que la confirmación de permutas se puede saltar o reaplicar desde la sesión sin volver a validar.

| Severidad | Hallazgo | Dónde |
| --- | --- | --- |
| Alto | El archivo se acepta solo por extensión. No hay tope de tamaño ni comprobación de subida real. | `HorarioController::procesar()` |
| Alto | El mensaje crudo de la excepción llega al navegador. | `HorarioController::procesar()` |
| Medio | Se puede saltar la confirmación de permutas con un campo POST. | `procesar()`, campo `confirmar_permutas` |
| Medio | La importación pendiente se reaplica desde la sesión sin revalidar. | `confirmarPermutas()` |
| Medio | La sincronización no es una transacción. Queda horario a medias. | `HorarioSincronizador` |
| Medio | Si falla la auditoría, el error se traga. | `registrarImportacion()` |
| Medio | Los cruces no se comparan contra horarios ya guardados de otras fichas. | `HorarioValidator::validarCrucesInstructor()` |
| Medio | La búsqueda de instructor acepta coincidencias parciales. | `Usuario::getInstructorPorNombre()` |
| Bajo | Textos del Excel se guardan sin límite de longitud. | Parser y modelos |
| Bajo | La sesión puede crecer con todos los bloques parseados. | `$_SESSION['importacion_pendiente']` |

## Controles que sí están

- `importar()`, `procesar()` y `confirmarPermutas()` llaman a `Auth::requireAdmin()`.
- Los POST validan CSRF con `hash_equals` y el token se invalida al usarse.
- Las consultas nuevas van por PDO preparado. `Sesion::update()` solo escribe columnas de una lista blanca.
- El Excel se lee del temporal de PHP. No hay ruta de archivo controlada por el usuario.
- Las vistas nuevas usan `htmlspecialchars` en códigos, nombres, fechas y mensajes de error.
- Si hay incongruencias, `procesar()` no llama al sincronizador. El rechazo es completo para el caso de validación fallida.

## Hallazgos

### 1. Carga de Excel sin controles suficientes (alto)

`procesar()` revisa que la extensión sea `xlsx` o `xls`. No llama a `is_uploaded_file()`, no mira `$_FILES['archivo']['size']` y no comprueba el tipo real. La vista dice "máximo 10 MB" y el servidor no lo exige.

PhpSpreadsheet carga el libro completo en memoria. Un libro muy grande o mal formado, subido por un administrador o por quien tenga su sesión, puede agotar la memoria del proceso PHP. El formato `.xls` antiguo amplía la superficie de fallos del lector.

El nombre original se guarda en `importacion_horario.archivo` sin recortar longitud ni quitar caracteres de control. Hoy no se pinta en una vista; el riesgo aparece cuando esa columna se muestre.

Qué falta: tope de tamaño alineado con la vista, `is_uploaded_file()`, rechazo de nombres vacíos o demasiado largos, y no ofrecer `.xls` si no hace falta.

### 2. Filtración de errores internos (alto)

El `catch` de `procesar()` hace `Auth::setFlash('error', 'Error al procesar el archivo Excel: ' . $e->getMessage())`. Un fallo de PhpSpreadsheet o de PDO puede mostrar rutas del servidor o fragmentos de la consulta.

Qué falta: mensaje genérico al usuario y registro del detalle solo en el log del servidor.

### 3. La confirmación de permutas se puede saltar (medio)

`RF-HOR-15` dice que, si el Excel revertiría una permuta, no se guarda nada hasta que el administrador confirme. La implementación lo decide con `!empty($_POST['confirmar_permutas'])`. Ese campo no está en el formulario, pero un POST autenticado de administrador puede enviarlo y aplicar el archivo sin ver `confirmar_permutas.php`.

No eleva privilegios (sigue siendo administrador), pero anula el control de negocio.

Qué falta: no aceptar ese atajo en `procesar()`. La única vía de confirmación debe ser `confirmarPermutas()`, con el identificador de la carga pendiente.

### 4. La carga pendiente vive en sesión y no se revalida (medio)

Si hay permutas afectadas, `procesar()` guarda todos los bloques en `$_SESSION['importacion_pendiente']`. `confirmarPermutas()` los sincroniza tal cual. No repite `HorarioValidator`, no comprueba que el usuario de la sesión sea quien inició la carga y no pone caducidad.

Con la sesión del administrador comprometida, esa carga se puede aplicar después. Un Excel grande también infla el archivo de sesión.

Qué falta: guardar un identificador, el `usuario_id` y una hora de vencimiento; volver a validar antes de escribir; borrar la pendiente al cerrar sesión.

### 5. Escritura parcial y auditoría silenciosa (medio)

`sincronizar()` y `sincronizarAsignaciones()` hacen un `INSERT` o `UPDATE` por bloque, sin `beginTransaction()`. Si falla a mitad, la ficha o el instructor quedan con un horario incompleto. No hay forma automática de deshacerlo.

`registrarImportacion()` atrapa `Throwable` y no hace nada. Una importación aplicada puede no quedar en `importacion_horario`, que es la evidencia de `RF-HOR-08`.

Qué falta: una transacción por importación y, si la auditoría falla, dejar constancia en el log. No ocultar el fallo como si no hubiera pasado.

### 6. Huecos de integridad del validador (medio)

- `validarCrucesInstructor()` solo compara bloques del mismo archivo. `RF-HOR-17` también pide el cruce contra horarios ya guardados. Esa regla sí está en `Sesion::validarSolapamiento()`, pero el importador no la llama.
- `RF-HOR-19` (bloque fuera de la jornada de la ficha) no está implementada.
- `Usuario::getInstructorPorNombre()` después de la coincidencia exacta acepta que todas las palabras de 3 o más letras aparezcan en otro nombre. Dos instructores con apellidos parecidos pueden resolverse al primero de la lista. La sesión quedaría asignada a la persona equivocada.
- Dos importaciones simultáneas de la misma ficha no se bloquean.

### 7. Datos del Excel sin límite (bajo)

Instructor, tipo, especialidad, nivel y grupo convergente se insertan como vienen. Las vistas los escapan, así que no hay XSS almacenado en las pantallas revisadas. Si más adelante se exportan a otro Excel sin tratarlos como texto, una celda que empiece por `=` podría ejecutarse en la hoja de quien la abra.

Qué falta: recortar a la longitud de la columna y, al exportar, forzar texto.

### 8. Migraciones no aplicadas (operativo, no es un fallo de código)

`007-horarios-asignaciones-permutas.sql` y `008-auditoria-importaciones.sql` están en el repo y no se ejecutaron (el agente no toca la base). Sin la 007, `RF-HOR-38` falla porque no existe `asignacion_instructor`. Sin la 008, el registro de auditoría lanza una excepción que hoy se traga.

La 007 además reescribe el `ENUM` de `sesion.estado`. Conserva `Activo`, `Cancelado`, `Finalizada` y agrega `Permutado`. Hay que revisarla contra los valores reales antes de aplicarla.

## Funciones duplicadas

Estas no son fallos de seguridad por sí solas. Duplican reglas y hacen que un arreglo en un sitio no llegue al otro.

### Parser de Excel, dos implementaciones

| Función viva | Función duplicada | Estado |
| --- | --- | --- |
| `HorarioParser::parsear()` y `extraerBloques()` | `HorarioController::parsearExcelHorarios()` (desde la línea 1027) | El controlador ya no la llama. Sigue en el archivo. |

La vieja además crea instructores temporales (`createInstructorDesdeExcel`) y asume `07:00-13:00` si no lee la hora. Eso contradice `RF-HOR-16` y `RF-HOR-21`. Si alguien vuelve a llamarla, reintroduce el comportamiento que el Track A debía quitar.

### Normalización de nombres, dos copias

| Función | Dónde |
| --- | --- |
| Closure `$normalizar` dentro de `Usuario::getInstructorPorNombre()` | `models/Usuario.php` |
| `HorarioController::normalizarNombre()` | usada solo al comparar permutas |

Hacen lo mismo (minúsculas, tildes, espacios). La del controlador no quita mayúsculas acentuadas por separado porque ya pasó por `mb_strtolower`, pero no es la misma función. Un cambio de regla de nombres puede hacer que la validación y la detección de permutas no coincidan.

### Cruce de horarios, dos motores

| Función | Qué compara |
| --- | --- |
| `HorarioValidator::validarCrucesInstructor()` y `validarCrucesFicha()` | Solo los bloques del archivo en memoria |
| `Sesion::validarSolapamiento()` | La base: misma ficha u otro instructor a la misma hora |

La del modelo la usa el CRUD manual (Dev 2). El importador no. Por eso el Excel puede guardar un cruce que el formulario manual rechazaría.

### Sesión protegida, dos consultas

| Función | Qué mira |
| --- | --- |
| `HorarioSincronizador::estaProtegida()` | Cuenta filas en `asistencia` e `inasistencia`, y si hay `apertura_real` sin `cierre_real` |
| `Sesion::tieneAsistencias()` | Solo cuenta `asistencia` |

`RF-HOR-13` queda partido. El CRUD puede dejar modificar una sesión que el importador considera protegida, o al revés.

### Meses y días, dos mapas

`HorarioParser` tiene `$mesesMap` y `$diasKeywords` en mayúsculas para leer el Excel. `HorarioController::nombreMes()` y `nombreDia()` tienen los mismos nombres en formato de presentación. No es la misma responsabilidad, pero la lista de meses está copiada. Si se agrega un alias (por ejemplo "SETIEMBRE" mal escrito en los Excel del SENA), hay que tocarlo en un solo sitio y hoy no existe ese sitio único.

### Nombres iguales que no son duplicados

`Ficha::getByCodigo()` y `Programa::getByCodigo()` se llaman igual y consultan tablas distintas. No conviene unificarlas.

`HorarioSincronizador::sincronizar()` y `sincronizarAsignaciones()` se parecen, pero escriben en `sesion` y en `asignacion_instructor`. Comparten la idea (crear, omitir, actualizar, cancelar) y no el SQL. Unificarlas ahora mezclaría dos tablas.

## Prioridad sugerida

1. Limitar la subida y dejar de mostrar `$e->getMessage()`.
2. Quitar el atajo `confirmar_permutas` y revalidar la carga pendiente.
3. Transacción en la sincronización y log si falla la auditoría.
4. Borrar o aislar `parsearExcelHorarios()` para que nadie la vuelva a usar.
5. Hacer que el importador llame a `Sesion::validarSolapamiento()` (o extraer una sola regla) y a `tieneAsistencias()` más la revisión de inasistencias.
6. Una sola función de normalización de nombres, usada por el modelo y por el controlador.

No se modificó código de aplicación en este reporte.
