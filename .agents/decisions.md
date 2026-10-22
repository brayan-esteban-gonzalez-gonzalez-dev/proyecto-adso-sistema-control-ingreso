# decisions.md — Decisiones de implementación

Actualización: solo agregar entradas o tachar (`~~texto~~`). No borrar ni reescribir.

Formato sugerido por entrada:
- **AAAA-MM-DD — Función:** qué se decidió, por qué y qué alternativa se descartó.

## Registro

- **2026-10-22 — Control de Sesión de Asistencia (Toggle Instructor) y Terminal RFID:**
  - Se decidió no crear una tabla nueva `sesiones_asistencia`, reutilizando la entidad `sesion` existente con migración `005-sesion-asistencia-toggle.sql` para permitir `hora_fin` NULLABLE en sesiones abiertas (toggle ON).
  - Los estados de sesión se alinearon al ENUM existente en la base de datos (`'Activo'` para sesión abierta recibiendo llaveros, `'Finalizada'` al cerrar la toma de asistencia con toggle OFF).
  - Al cerrar la sesión, se ejecuta una transacción atómica que actualiza la sesión con `hora_fin = CURTIME()` y calcula/inserta automáticamente las inasistencias en la tabla `inasistencia` para todos los aprendices activos de la ficha sin registro de asistencia en dicha sesión.
  - La captura RFID se implementó mediante input oculto enfocado permanentemente para lectores USB en emulación de teclado, restringida a fichas que tengan sesión activa abierta por el instructor, aplicando la regla de tolerancia de 10 minutos para el cálculo de minutos de retardo.
- **2026-10-22 — Unificación de Usuarios y Aprendices con Filtros por Rol:**
  - Se restauraron las pestañas de filtro por rol (`Todos`, `Administradores`, `Instructores`, `Aprendices`) en la vista de Usuarios (`views/usuarios/index.php`) con conteos en tiempo real mediante `Usuario::getCountsByRole()`.
  - Se restauró en el formulario de usuarios (`views/usuarios/form.php`) la sección dinámica interactiva `#seccionAprendiz` que aparece únicamente cuando se selecciona el rol Aprendiz (`Rol_id = 3`), permitiendo asignar la Ficha de formación y el código de llavero/RFID.
  - Se eliminó el enlace redundante de "Aprendices" en la barra lateral (`views/layouts/header.php`) y se configuró `AprendizController` para redirigir limpiamente al listado unificado de Usuarios (`?action=usuarios&rol=3`), eliminando interfaces duplicadas.
- **2026-10-22 — Resiliencia en Consultas de Programa y Creación de ReporteController:**
  - Se eliminó la dependencia rígida de la columna `p.codigo` en `Ficha::getAll()` y se hizo resiliente en `Competencia::getAll()` / `Competencia::getById()` mediante detección condicional, permitiendo compatibilidad plena con esquemas de base de datos donde la migración `004-programa-codigo.sql` no se haya ejecutado aún. Esto subsana los errores fatales en `usuarios/crear`, `fichas`, `competencias` y `horarios`.
  - Se implementó `ReporteController` (`index()` y `generar()`) conectando `models/Reporte.php` y `views/reportes/index.php`, con exportación en CSV (compatible con Excel) y PDF (FPDF), resolviendo el error 404 en la ruta `reportes`.
- **2026-10-22 — Resiliencia en Ficha y Programa para Creación de Fichas:**
  - Se ajustó `Ficha::getProgramas()` y `Programa::obtenerActivos()` para verificar dinámicamente si la columna `estado` existe en la tabla `Programa`, evitando el error fatal `SQLSTATE[42S22]: Column not found: 1054 Unknown column 'estado' in 'where clause'` al abrir el formulario de nueva ficha (`?action=fichas/crear`).
  - Se ajustó `Programa::create()`, `Programa::update()` y `Programa::getByCodigo()` para adaptarse limpiamente a tablas `Programa` con o sin la columna `codigo`.
  - Se estableció el campo `id_instructor_lider` como requerido en `views/fichas/form.php` y en la validación de `FichaController::guardar()`, respetando la restricción `NOT NULL` de clave foránea en la tabla `Ficha` de la base de datos.
- **2026-10-22 — Refactorización de Excusas Médicas Vinculadas a Inasistencias:**
  - Se eliminaron los campos de selección libre de fecha (`<input type="date">`) en `views/excusas/form.php`, reemplazándolos obligatoriamente por un `<select name="ingreso_asistencia_id">` cargado dinámicamente con inasistencias reales del aprendiz obtenidas mediante `IngresoAsistencia::obtenerInasistenciasSinExcusaPorAprendiz()`.
  - Se implementó en `ExcusaController::guardar()` la verificación estricta de pertenencia de la inasistencia al aprendiz autenticado (`verificarInasistenciaAprendiz`), validación de tamaño máximo de archivo a 2 MB y análisis profundo de tipo MIME real con `finfo_file` (permitiendo exclusivamente `application/pdf`, `image/jpeg` y `image/png`).
  - Se actualizó `ExcusaMedica::crear()`, `aprobar()` y `rechazar()` para vincular la inasistencia y actualizar resilientemente su estado (`En revisión`, `Justificada`, `Injustificada`), acompañándolo de la propuesta de migración `006-inasistencia-estado.sql`.
  - Se adaptaron `views/excusas/revisar.php` (panel del instructor con visualizador/descarga de soportes y botones de aprobación/rechazo) y los modelos de cálculo (`Reporte::asistenciaPorFicha`, `Reporte::contarPorMes`, `IngresoAsistencia::getEstadisticasAprendiz`) para que las inasistencias con excusa médica aprobada no computen negativamente en el récord del aprendiz.
- **2026-10-22 — Restricción del CRUD de Fichas al Administrador:**
  - Se introdujo `Auth::requireAdminOnly()` en `helpers/Auth.php` para restringir de forma estricta las operaciones a usuarios con `Rol_id = 1` (Administrador), diferenciándolo de `Auth::requireAdmin()` que históricamente englobaba Administradores e Instructores.
  - En `controllers/FichaController.php`, se actualizaron los métodos de creación (`crear()`), edición (`editar()`), guardado (`guardar()`) y eliminación (`eliminar()`) para exigir `Auth::requireAdminOnly()`, impidiendo que los instructores alteren las fichas.
  - El listado general (`index()`) se mantuvo visible para Administradores e Instructores, pero en la vista `views/fichas/index.php` se condicionaron con `Auth::isAdmin()` el botón de "Nueva Ficha", la columna y botones de "Acciones" (editar y eliminar), y el botón del estado vacío, asegurando una experiencia de solo lectura para los instructores.
  - Se condicionó el botón de acceso rápido de "Nueva Ficha" en `views/dashboard/index.php` para que solo se muestre a Administradores.
- **2026-10-22 — Consulta de Fichas Asignadas y Vista de Integrantes para Instructores:**
  - Se modificó `FichaController::index()` para que los usuarios con rol Instructor (`Rol_id = 2`) consulten únicamente las fichas que tienen asignadas, ya sea por ser Instructor Líder (`Ficha.instructor_id`) o por tener sesiones programadas (`sesion.Instructor_id`), mientras los Administradores continúan visualizando todas las fichas.
  - Se crearon en `models/Ficha.php` los métodos `getByInstructorAsignado()`, `perteneceAInstructor()`, `getAprendices()` y `getInstructoresVinculados()`.
  - Se implementó la acción `FichaController::integrantes()` y la vista `views/fichas/integrantes.php` para visualizar el detalle de los aprendices matriculados y los instructores vinculados (Líder y de Sesión), con control de acceso que restringe la consulta al instructor asignado o al administrador.
  - Se integró el botón de acceso a integrantes (`fichas/integrantes`) en la tabla de `views/fichas/index.php`.
