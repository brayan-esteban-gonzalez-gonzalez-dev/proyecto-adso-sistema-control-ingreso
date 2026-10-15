# AGENTS.md — Sistema de Control de Ingreso (SCIA, ADSO / SENA)

Repo: https://github.com/brayan-esteban-gonzalez-gonzalez-dev/proyecto-adso-sistema-control-ingreso.git

> **[CONFIRMAR]** = dato no verificado. No lo trates como hecho: pregunta antes.


## 1. Idioma
Responde, comenta y documenta siempre en **español**. Nombres de código: sigue el idioma que ya usa el archivo que editas.

## 2. Fuente de la verdad (importante)
Los documentos del repo NO coinciden entre sí ni con el código. Orden de confianza:
1. **El código** (`index.php`, `helpers/`, `controllers/`, `models/`, `views/`).
2. `migrations/` (esquema) — ver sección 7.
3. `README.md`, `ESTADO_PROYECTO.txt`, `PENDIENTE_IMPLEMENTAR.txt`: son fotos de un momento y ya hay datos viejos (ej.: dicen que `AsistenciaController::rfid()` no existe, pero ya existe; dan 78 % y 70 % de avance).
4. Los `GUIA.md` de `controllers/`, `models/`, `migrations/`, `assets/`, `jobs/`: **describen otra arquitectura** (API que responde solo JSON, `ControllerBase`, rutas `?action=aprendices.listar`, base `sistema-ingresos`, clases `UsuarioModel`, tablas en plural). **No los uses como guía de implementación.** Úsalos solo como ideas, y pregunta antes de seguirlos.

Si encuentras otra contradicción entre documentos y código, avísala; no la resuelvas en silencio.

## 3. Stack real
- **PHP ≥ 8.2** (lo exige `librerias/composer/platform_check.php`), servido con **XAMPP** (Apache + MySQL/MariaDB) o servidor con PHP.
- Base de datos: `db_ingreso_aprendices`, acceso solo con **PDO** (`config/Database.php`, singleton).
- Patrón: **MVC con Front Controller**. Los controladores **renderizan vistas** (`require_once ROOT_PATH . '/views/...'`). Solo `HorarioController` responde JSON hoy.
- **URL base:** se detecta sola en `index.php` (`BASE_URL`, a partir de `SCRIPT_NAME`). **Nunca escribas URLs absolutas**; usa `BASE_URL` y `ROOT_PATH`.
- Credenciales de BD: hoy están escritas dentro de `config/Database.php` (usuario `root`, sin clave, valores de XAMPP). No las cambies ni agregues credenciales reales al repo.

## 4. Enrutamiento y roles
- Formato: `index.php?action=controlador/metodo&id=X`. El mapa clave → clase está en `helpers/Router.php` (`$controllers`). Para un controlador nuevo hay que registrarlo ahí.
- Rutas públicas (sin login): `auth/login`, `auth/doLogin`, `auth/logout`. Todo lo demás exige `Auth::requireLogin()`.
- Roles: **1 = Administrador, 2 = Instructor, 3 = Aprendiz** (el aprendiz es un `Usuario` con `Rol_id = 3`; no hay tabla `aprendices`). Helpers: `Auth::isAdminOrInstructor()`, `Auth::isInstructor()`, `Auth::isAprendiz()`.
- Validación de entrada: clase `Validator` (`helpers/Validator.php`). Hay token **CSRF** por sesión: `Auth::generateCSRF()` al mostrar el formulario y `Auth::validateCSRF($_POST['csrf_token'] ?? '')` al recibirlo. Úsalo en todo POST nuevo.
- Hoy `reportes` apunta a `ReporteController`, que **no existe** (da 404).

## 5. Librerías y dependencias
- **Composer** con `"vendor-dir": "librerias"`. Las librerías están en **`librerias/`, no en `vendor/`**. `composer.json` pide solo **PhpSpreadsheet ^5.10** (Excel: importación de horarios). `ESTADO_PROYECTO.txt` dice 1.29: está desactualizado.
- **PDF:** ya existe **FPDF** en `librerias/fpdf/` (copiada a mano, no está en `composer.json`). No propongas otra librería de PDF sin preguntar.
- Reportes: **por definir** (`models/Reporte.php` existe, sin controlador).
- `composer.phar` está dentro del repo (≈3,6 MB). No lo modifiques.
- **Antes de instalar, actualizar o quitar cualquier dependencia: avisa de forma clara** (paquete, versión, para qué, qué archivos cambian: `composer.json`, `composer.lock`, `librerias/`) y espera confirmación.
- No edites nada dentro de `librerias/`.

## 6. Convenciones de código
- PHP estándar (PSR-1/PSR-12), priorizando **camelCase**: métodos y variables `camelCase`, clases `PascalCase` (una clase por archivo, nombre del archivo = nombre de la clase), constantes `MAYUSCULAS`. El código actual ya es mayormente camelCase.
- Columnas y tablas de la BD: **respeta los nombres existentes** (hay mezcla, ej. `Usuario_id`, `Rol_id`, `codigo_llavero`). No renombres.
- Los modelos son la única capa que habla con la BD y usan **sentencias preparadas**. Los modelos no hacen `echo` ni HTML. Mantén esto.
- Escapa toda salida en vistas (`htmlspecialchars`).
- Controladores delgados: la lógica de negocio va en el modelo (o en un servicio si se aprueba).

## 7. Base de datos y migraciones
- Carpeta `migrations/` contiene: `db_ingreso_aprendices.sql` (el que `Database.php` carga solo), `001-sistema-ingresos.sql`, `003-horarios-columnas.sql`, `004-programa-codigo.sql`. **Falta la `002`.**
- **[CONFIRMAR]** cuál es el esquema oficial (README nombra la 001; ESTADO nombra `db_ingreso_aprendices.sql` como consolidado). Pregunta antes de tocar el esquema.
- Cambios de esquema nuevos: archivo `NNN-descripcion.sql` con el siguiente número (hoy sería `005`). Se muestran en el plan y **no se ejecutan** sin permiso.
- Ojo: `Database.php` **crea la base y las tablas automáticamente** si no existen. Es el propio sistema, no tú. Tú no ejecutes nada contra la BD.

## 8. Frontend
- **Hoy el repo NO usa Bootstrap.** Usa un `assets/css/style.css` propio (tema oscuro con variables CSS: `--bg-card`, `--accent-green`, etc.), **Font Awesome 6.5.1** y la fuente Inter por CDN, y JS propio en `assets/js/`.
- **[CONFIRMAR]** si el equipo quiere adoptar Bootstrap. Mientras tanto, **no lo agregues** (es una dependencia nueva) y usa las variables de `style.css`; no inventes colores.
- Hay unos 100 `style="..."` en las vistas. En código nuevo evita el estilo en línea; úsalo solo si es puntual.

## 9. Jobs
- `jobs/` solo tiene un `GUIA.md` (sin scripts). Está pensado para tareas por consola (cron / Programador de tareas), p. ej. generar inasistencias. No crees jobs sin que se pida y se apruebe el plan.

## 10. Reglas de negocio
- Cada rol tiene sus reglas y responsabilidades (sección 4). No supongas permisos que no estén en el código; pregunta.
- **El tiempo lo decide el instructor.** No pongas duraciones fijas "por tu cuenta".
- **Retardo:** el aprendiz tiene **10 minutos** de tolerancia, contados desde el registro del instructor (la hora base es el mayor entre la hora de inicio de la sesión y la hora en que el instructor registró/abrió). Pasado ese tiempo, se registran los minutos de retardo.
- **Excusas:** si una ficha tiene 2 o más instructores en la misma franja, la excusa requiere **doble validación**.
- **Marcación RFID:** el lector USB funciona como teclado (escribe el código del llavero en un input con foco automático). Valida y sanea ese dato igual que cualquier otra entrada.
- Todavía **no existe** `AsistenciaController::registrarMarcacion()` ni `assets/js/rfid.js` (la terminal RFID abre, pero no guarda marcaciones). Tampoco existe `assets/uploads/` (lo usa `UPLOAD_PATH` para excusas).
- Pendientes conocidos están en `PENDIENTE_IMPLEMENTAR.txt` (úsalo como lista de trabajo, con la advertencia de la sección 2).

## 11. Flujo de trabajo (obligatorio)
1. **Al iniciar:** ponte al día con `main` (`git fetch` y actualizar). Si hay cambios sin guardar o conflictos, **detente y avisa**. No descartes ni resuelvas nada por tu cuenta.
2. **Antes de cambiar archivos:** muestra un **plan** (qué archivos, qué cambios, por qué) y espera aprobación.
3. **Push:** solo con autorización explícita. Nada de  push por iniciativa propia.
4. **Dependencias:** ver sección 5. **Base de datos:** ver `global_rules.md`.

## 12. Archivos de contexto
Léelos al iniciar y úsalos como contexto:
- `.agents/global_rules.md` — alcance y límites del agente.
- `.agents/decisions.md` — decisiones tomadas al implementar funciones.
Para actualizarlos: solo **agrega** entradas nuevas o **tacha** (`~~texto~~`) las que ya no 
apliquen. No borres ni reescribas lo existente.
- `.agents/ESTADO_PROYETO.txt`
- `.agents/PENDIENTE_IMPLEMENTAR.txt`
## 13. Calidad (recomendado, aún no adoptado)
No hay pruebas ni linter. Recomendación: **PHP_CodeSniffer (PSR-12)** primero (barato). PHPUnit solo para lógica pura (parser de horarios, regla de los 10 minutos). Es una dependencia nueva: pide aprobación (sección 5).