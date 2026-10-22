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
