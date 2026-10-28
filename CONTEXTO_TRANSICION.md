# DOCUMENTO MAESTRO DE ARQUITECTURA Y CONTEXTO DE TRANSICIÓN TÉCNICA
## Sistema de Control de Ingreso de Aprendices (SCIA) — SENA ADSO

---

### 1. DESCRIPCIÓN GENERAL Y OBJETIVOS DEL SISTEMA

#### 1.1 Propósito del Sistema
El **Sistema de Control de Ingreso de Aprendices (SCIA)** es una solución integral desarrollada para el Centro de Formación del SENA. Su objetivo primordial es automatizar y asegurar el registro de ingreso y salida de aprendices mediante hardware de lectura **RFID** (frecuencia estándar emulada por teclado USB), vinculado directamente a la planificación académica (fichas, programas, competencias y sesiones de clase). Además, administra el ciclo de vida de inasistencias generadas de forma automática y el flujo de justificación mediante excusas médicas digitales con soporte de co-docencia y reportes institucionales.

#### 1.2 Stack Tecnológico Exacto
* **Lenguaje del Backend:** PHP ≥ 8.2 (ejecutado con tipado estricto `declare(strict_types=1);` en controladores y servicios nucleares).
* **Gestor de Base de Datos:** MySQL / MariaDB (motor InnoDB, cotejamiento `utf8mb4_unicode_ci`).
* **Capa de Abstracción de Datos:** **PDO (PHP Data Objects)** con sentencias preparadas nativas (`PDO::ATTR_EMULATE_PREPARES => false`, `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION`).
* **Librerías de Terceros:**
  * **PhpOffice\PhpSpreadsheet (^5.10):** Motor de lectura y normalización de mallas curriculares y calendarios en hojas de cálculo Excel (`.xlsx`, `.xls`).
  * **FPDF (v1.86 en `librerias/fpdf/`):** Motor de renderizado vectorial de documentos PDF para la exportación de reportes institucionales consolidados e individuales.
* **Frontend y Capa de Presentación:**
  * Arquitectura basada en componentes y grillas responsivas estructuradas bajo estándares compatibles con **Bootstrap 5** y maquetación Vanilla CSS de alta fidelidad.
  * Hoja de estilos centralizada `assets/css/style.css` gobernada por un sistema de diseño con variables CSS (`--bg-card`, `--accent-green`, `--sidebar-width`, etc.) y paleta oscura/profesional.
  * **Iconografía y Tipografía:** Font Awesome 6.5.1 y Google Fonts (Familia tipográfica *Inter*).
  * **Visualización de Datos:** Chart.js 4 (distribuido vía CDN para métricas en Dashboard e historiales).

#### 1.3 Roles del Sistema y Matriz de Permisos
El sistema implementa un control de acceso basado en roles (RBAC) centralizado en la clase helper `Auth`:

| Rol ID | Denominación | Permisos y Alcance Operativo |
| :---: | :--- | :--- |
| **1** | **Administrador** | Acceso total al sistema. Gestión de usuarios (instructores, aprendices, administradores), creación y parametrización de fichas, programas de formación, competencias y jornadas. Importación y sincronización masiva de horarios vía Excel. Generación y exportación de reportes institucionales (CSV, PDF). Aprobación directa y anulación de excusas médicas. |
| **2** | **Instructor** | Control de asistencia en tiempo real mediante la terminal RFID. Encendido y apagado de sesiones de clase (*toggle ON/OFF*) asociadas a sus fichas asignadas. Visualización de horarios semanales y mensuales en grilla interactiva. Revisión, aprobación y rechazo de excusas médicas (incluyendo el flujo de validación con co-docente). Consulta de aprendices de sus fichas. |
| **3** | **Aprendiz** | Identificación en el sistema mediante tarjeta/llavero RFID. Consulta personalizada de su propio historial de ingresos, retardos e inasistencias. Visualización del horario programado de su ficha. Registro y radicación de excusas médicas digitales vinculadas obligatoriamente a inasistencias previas sin justificar. |

---

### 2. ARQUITECTURA Y ESTRUCTURA DE DIRECTORIOS

#### 2.1 Patrón Arquitectónico
El sistema implementa el patrón **MVC (Modelo - Vista - Controlador)** coordinado por un **Front Controller** único (`index.php`).

```
Petición HTTP (?action=controlador/metodo&id=X)
                    │
                    ▼
            ┌───────────────┐
            │   index.php   │  <── Inicializa constantes, Autoload Composer,
            └───────┬───────┘      Helpers, Modelos y conexión PDO Singleton
                    │
                    ▼
            ┌───────────────┐
            │   Router.php  │  <── Sanitiza método, valida permisos con Auth::requireLogin()
            └───────┬───────┘      e instancia el controlador correspondiente
                    │
        ┌───────────┴───────────┐
        ▼                       ▼
┌───────────────┐       ┌───────────────┐
│  Controlador  │ <───> │    Modelo     │ <───> Base de Datos MySQL
└───────┬───────┘       └───────────────┘       (db_ingreso_aprendices)
        │
        ▼
┌───────────────┐
│     Vista     │  <── Inyecta Layouts (header.php / footer.php) y CSS
└───────────────┘
```

#### 2.2 Flujo de Ejecución del Front Controller
1. **Entrada Global (`index.php`):**
   * Define las constantes de entorno: `ROOT_PATH` (raíz absoluta en disco), `BASE_URL` (detección automática de ruta web a partir de `SCRIPT_NAME`) y `UPLOAD_PATH` (`ROOT_PATH . '/assets/uploads/'`).
   * Carga el autoloader de Composer (`librerias/autoload.php`).
   * Carga los componentes transversales: `Database.php`, `Auth.php`, `Validator.php` y `Router.php`.
   * Inicializa la sesión mediante `Auth::init()`.
   * Carga dinámicamente todos los modelos mediante `glob(ROOT_PATH . '/models/*.php')`.
   * Obtiene la instancia de base de datos (`Database::getInstance()->getConnection()`).
   * Invoca `$router->dispatch()`.
2. **Despacho y Enrutamiento (`helpers/Router.php`):**
   * Lee el parámetro `$_GET['action']` (por defecto `dashboard`).
   * Divide la acción en `{controladorKey}/{metodo}`.
   * Resuelve el controlador a través de un mapa estricto `$controllers` (ej. `'asistencia' => 'AsistenciaController'`).
   * Sanitiza el nombre del método (`preg_replace('/[^a-zA-Z0-9_]/', '', $method)`).
   * Aplica la política de rutas públicas (`auth/login`, `auth/doLogin`, `auth/logout`). Toda otra ruta exige sesión activa mediante `Auth::requireLogin()`.
   * Si la clase, archivo o método no existen o no son invocables, genera una respuesta HTTP 404 institucional.
3. **Controladores (`controllers/`):**
   * Reciben la inyección de la instancia `PDO` en su constructor.
   * Procesan parámetros de entrada, aplican validaciones semánticas (`Validator.php`) y tokens anti-falsificación (`Auth::validateCSRF()`).
   * Delegan la persistencia e integridad relacional a los modelos.
   * Renderizan las vistas mediante `require_once ROOT_PATH . '/views/...php'`. En endpoints asíncronos (AJAX), retornan payloads JSON con cabeceras `application/json`.
4. **Modelos (`models/`):**
   * Único punto de contacto con la base de datos.
   * Emplean consultas preparadas con mapeo de parámetros posicionales o nombrados.
   * No generan salida HTML ni manipulan buffers de salida.
5. **Vistas (`views/`):**
   * Archivos PHP puros organizados por carpetas modulares (`views/asistencia/`, `views/excusas/`, etc.).
   * Incluyen las plantillas estructurales `views/layouts/header.php` y `views/layouts/footer.php`.
   * Todas las salidas de variables dinámicas se encuentran saneadas con `htmlspecialchars($val, ENT_QUOTES, 'UTF-8')`.

#### 2.3 Árbol de Directorios del Proyecto
```
proyecto-adso-sistema-control-ingreso-main/
├── .agents/                      # Contexto operativo del agente (decisiones, bitácoras)
├── assets/
│   ├── css/
│   │   └── style.css             # Sistema de diseño centralizado (variables, dark theme)
│   ├── js/
│   │   ├── main.js               # Interactividad de layout, sidebar y helpers globales
│   │   └── rfid.js               # Gestión de terminal RFID, autoenfoque y llamadas AJAX
│   └── uploads/
│       └── excusas/              # Almacenamiento seguro de evidencias médicas adjuntas
├── config/
│   └── Database.php              # Patrón Singleton de conexión PDO y auto-migración
├── controllers/
│   ├── AprendizController.php    # Listados y consultas de aprendices por ficha
│   ├── AsistenciaController.php  # Terminal RFID, toggle de sesión y consultas de historial
│   ├── AuthController.php        # Autenticación, control de sesión y cierre de sesión
│   ├── CompetenciaController.php # Maestro de competencias formativas
│   ├── DashboardController.php   # Agregaciones estadísticas por rol
│   ├── ExcusaController.php      # Ciclo de vida de excusas médicas (radicación, revisión)
│   ├── FichaController.php       # Maestro de fichas de caracterización
│   ├── HorarioController.php     # Procesamiento de grilla de horarios y calendario
│   ├── JornadaController.php     # Maestro de jornadas horarias
│   ├── ProgramaController.php    # Maestro de programas de formación académica
│   ├── ReporteController.php     # Exportación de reportes en formatos CSV y FPDF
│   └── UsuarioController.php     # CRUD de usuarios y vinculación de llaveros RFID
├── helpers/
│   ├── Auth.php                  # Seguridad de sesión, RBAC, hashes y CSRF tokens
│   ├── HorarioParser.php         # Extracción de celdas Excel vía PhpSpreadsheet
│   ├── HorarioReporte.php        # Estructuración de resúmenes de importación
│   ├── HorarioSincronizador.php  # Sincronización diferencial de sesiones y conflictos
│   ├── HorarioValidator.php      # Validación de reglas de negocio sobre mallas horarias
│   ├── Router.php                # Front Controller Router e interceptor de rutas
│   └── Validator.php             # Validador de tipos de datos, correos, fechas y tamaños
├── librerias/                    # Directorio Vendor personalizado de Composer
│   ├── autoload.php              # Autoloader PSR-4 generado por Composer
│   ├── composer/                 # Metadatos del gestor de paquetes
│   ├── fpdf/                     # Motor FPDF 1.86 independiente
│   └── phpoffice/                # PhpSpreadsheet y dependencias binarias
├── migrations/
│   ├── db_ingreso_aprendices_27_08.sql # Esquema oficial consolidado de la base de datos
│   └── historico/                # Versiones y respaldos históricos de migración
├── models/
│   ├── Aprendiz.php              # Abstracción de usuarios con rol de aprendiz
│   ├── AsignacionInstructor.php  # Vinculación de instructores a fichas y competencias
│   ├── Competencia.php           # Operaciones sobre competencias académicas
│   ├── ExcusaMedica.php          # Persistencia de excusas y tabla estado_excusa
│   ├── Ficha.php                 # Relaciones y persistencia de fichas de formación
│   ├── ImportacionHorario.php    # Metadatos de auditoría de cargas de Excel
│   ├── IngresoAsistencia.php     # Asistencias, cálculo de retardos y métricas
│   ├── Jornada.php               # Franjas horarias maestras
│   ├── PermutaSesion.php         # Novedades y cambios temporales entre instructores
│   ├── Programa.php              # Programas formativos SENA
│   ├── Reporte.php               # Consultas analíticas consolidadas
│   ├── Rol.php                   # Catálogo de roles del sistema
│   ├── Sesion.php                # Sesiones formativas, aperturas y cierres con toggle
│   └── Usuario.php               # Maestro de usuarios, autenticación BCRYPT y RFID
├── views/
│   ├── aprendices/               # Vistas de consulta de aprendices
│   ├── asistencia/               # Vistas de terminal RFID (`rfid.php`) e historial
│   ├── auth/                     # Vista de acceso (`login.php`)
│   ├── competencias/             # Formularios y tablas de competencias
│   ├── dashboard/                # Paneles de control por rol
│   ├── excusas/                  # Radicación (`form.php`), listado y revisión (`revisar.php`)
│   ├── fichas/                   # Formularios y listados de fichas
│   ├── horarios/                 # Visualizador de horarios, importación masiva y calendarios
│   ├── jornadas/                 # Vistas de jornadas académicas
│   ├── layouts/                  # Plantillas base (`header.php`, `footer.php`)
│   ├── programas/                # Vistas de programas de estudio
│   ├── reportes/                 # Filtros y panel de exportación institucional
│   └── usuarios/                 # Formularios y listado general de usuarios
├── composer.json                 # Configuración de Composer ("vendor-dir": "librerias")
├── composer.lock                 # Bloqueo de dependencias de Composer
├── index.php                     # Punto de entrada único del aplicativo
└── README.md                     # Documentación general de instalación
```

---

### 3. ESQUEMA Y MODELO DE BASE DE DATOS

La base de datos oficial se denomina **`db_ingreso_aprendices`**. El esquema consolida **12 tablas nombradas estrictamente en singular** y normalizadas bajo el motor transaccional InnoDB:

```
                  ┌───────────────┐
                  │   programa    │
                  └───────┬───────┘
                          │ 1:N
                          ▼
┌─────────────┐   ┌───────────────┐   ┌───────────────┐
│   jornada   │<──┤     ficha     ├──>│    usuario    │ (Instructor Lector)
└─────────────┘   └───┬───────┬───┘   └───────▲───────┘
                  1:N │       │ 1:N           │
                      │       ▼               │ Rol_id (1, 2, 3)
                      │  ┌─────────┐          │
                      │  │ usuario │──────────┘ (Aprendiz: Rol_id=3)
                      │  └────┬────┘
                      ▼       │
                 ┌─────────┐  │
                 │ sesion  │  │
                 └────┬────┘  │
          1:N ┌───────┴───────┐ 1:N
              ▼               ▼
      ┌───────────────┐ ┌───────────────┐
      │  asistencia   │ │ inasistencia  │
      └───────┬───────┘ └───────┬───────┘
              │ 1:1             │ 1:1
              └───────┬─────────┘
                      ▼
              ┌───────────────┐
              │    excusa     │
              └───────┬───────┘
                      │ 1:N
                      ▼
              ┌───────────────┐
              │ estado_excusa │
              └───────────────┘
```

#### 3.1 Catálogo Oficial de Tablas

1. **`rol`**
   * **Propósito:** Catálogo maestro de roles del sistema.
   * **Campos:** `id` (PK, AI), `nombre` (VARCHAR 45, ej. 'Administrador', 'Instructor', 'Aprendiz'), `descripcion` (VARCHAR 255).
2. **`programa`**
   * **Propósito:** Programas de formación técnica o tecnológica impartidos en el centro.
   * **Campos:** `id` (PK, AI), `nombre` (VARCHAR 100), `descripcion` (TEXT, Nullable).
3. **`jornada`**
   * **Propósito:** Franjas horarias de operación académica.
   * **Campos:** `id` (PK, AI), `nombre` (VARCHAR 45, UNIQUE), `hora_inicio` (TIME), `hora_fin` (TIME). Registros base: Mañana (06:00 a 12:00), Tarde (12:00 a 18:00), Noche (18:00 a 21:00).
4. **`ficha`**
   * **Propósito:** Representa la ficha de caracterización (grupo formativo).
   * **Campos:** `id` (PK, AI), `codigo` (INT 7, número identificador único institucional), `Programa_id` (FK -> `programa.id`), `jornada_id` (FK -> `jornada.id`), `instructor_id` (FK -> `usuario.id`, instructor líder), `estado` (ENUM 'Activo', 'Inactivo').
5. **`usuario`**
   * **Propósito:** Centraliza a todas las personas del sistema (Administradores, Instructores y Aprendices).
   * **Campos:** `id` (PK, AI), `nombre` (VARCHAR 45), `apellido` (VARCHAR 45), `identificacion` (BIGINT, UNIQUE), `email` (VARCHAR 100, UNIQUE), `password` (VARCHAR 255, hash BCRYPT), `Rol_id` (FK -> `rol.id`), `Ficha_id` (FK -> `ficha.id`, NULL para instructores/admin, obligatorio para aprendices), `codigo_llavero` (VARCHAR 50, UNIQUE, código UID emitido por el lector RFID), `estado` (ENUM 'Activo', 'Inactivo').
6. **`competencia`**
   * **Propósito:** Unidades temáticas o competencias formativas asociadas a los programas.
   * **Campos:** `id` (PK, AI), `Programa_id` (FK -> `programa.id`), `Instructor_id` (FK -> `usuario.id`, Nullable), `nombre` (VARCHAR 100), `descripcion` (TEXT, Nullable). Índice único compuesto: `(Programa_id, nombre)`.
7. **`instructor_competencia`**
   * **Propósito:** Tabla asociativa N:M que certifica a instructores para impartir competencias específicas.
   * **Campos:** `id` (PK, AI), `Instructor_id` (FK -> `usuario.id`), `Competencia_id` (FK -> `competencia.id`). Restricción `UNIQUE(Instructor_id, Competencia_id)`.
8. **`sesion`**
   * **Propósito:** Bloques programados de clase donde se toma asistencia.
   * **Campos:** `id` (PK, AI), `Ficha_id` (FK -> `ficha.id`), `Competencia_id` (FK -> `competencia.id`), `Instructor_id` (FK -> `usuario.id`), `fecha` (DATE), `hora_inicio` (TIME), `hora_fin` (TIME), `apertura_real` (TIME, hora exacta de apertura con toggle), `cierre_real` (TIME, hora de finalización), `estado` (ENUM 'Activo', 'Cancelado', 'Finalizada').
9. **`asistencia`**
   * **Propósito:** Registros positivos de aprendices que marcaron presencia física en la sesión.
   * **Campos:** `id` (PK, AI), `fecha` (DATE), `hora_entrada` (TIME), `hora_salida` (TIME, Nullable), `Usuario_id` (FK -> `usuario.id`), `Sesion_id` (FK -> `sesion.id`), `registrado_por` (FK -> `usuario.id`), `estado` (ENUM 'Activo', 'Completado'), `codigo_llavero` (VARCHAR 50, snapshot del tag leído), `minutos_retardo` (INT, tolerancia calculada), `minutos_anticipacion` (INT). Restricción `UNIQUE(Usuario_id, Sesion_id)`.
10. **`inasistencia`**
    * **Propósito:** Faltas generadas automáticamente al cerrar una sesión para los aprendices ausentes.
    * **Campos:** `id` (PK, AI), `Usuario_id` (FK -> `usuario.id`), `Ficha_id` (FK -> `ficha.id`), `Sesion_id` (FK -> `sesion.id`), `fecha` (DATE), `generado_por` (VARCHAR 50, ej. 'sistema_toggle'). Restricción `UNIQUE(Usuario_id, Sesion_id)`.
11. **`excusa`**
    * **Propósito:** Justificaciones radicadas por aprendices ausentes.
    * **Campos:** `id` (PK, AI), `fecha` (DATE), `motivo` (TEXT), `evidencia` (VARCHAR 255, ruta relativa del archivo adjunto), `Usuario_id` (FK -> `usuario.id`), `Asistencia_id` (FK -> `asistencia.id`, Nullable), `Inasistencia_id` (FK -> `inasistencia.id`, Nullable).
12. **`estado_excusa`**
    * **Propósito:** Historial y estado de auditoría/revisión de cada excusa médica.
    * **Campos:** `id` (PK, AI), `fecha` (DATE), `respuesta` (TEXT, observaciones del instructor), `estado` (ENUM 'Aprobada', 'Rechazada'), `Excusa_id` (FK -> `excusa.id`), `Instructor_id` (FK -> `usuario.id`).

#### 3.2 Principios y Relaciones Clave del Modelo
* **Centralización de Sujetos en `usuario`:** No existen tablas aisladas como `aprendices` o `instructores`. Todos los usuarios coexisten en `usuario`. Un aprendiz es estrictamente un `usuario` con `Rol_id = 3` y `Ficha_id IS NOT NULL`. Un instructor tiene `Rol_id = 2` y `Ficha_id IS NULL`.
* **Relación 1:N Obligatoria (`programa` -> `ficha`):** Una ficha pertenece obligatoria e indefectiblemente a un único programa de formación (`ficha.Programa_id` NOT NULL), garantizando coherencia en competencias y mallas curriculares.
* **Integridad Sesión - Asistencia - Inasistencia:** Para una sesión determinada, un aprendiz solo puede existir en una de dos tablas: o en `asistencia` (si marcó su RFID durante la sesión activa) o en `inasistencia` (si no registró marcación al momento en que el instructor cerró la sesión con el toggle).
* **Vinculación Causal de Excusas:** La tabla `excusa` se encuentra rígidamente enlazada a `inasistencia.id` mediante la clave foránea `fk_Excusa_Inasistencia`. No se permite la existencia de excusas flotantes o sin inasistencia asociada en base de datos.

---

### 4. MÓDULOS PRINCIPALES Y LÓGICA IMPLEMENTADA

#### 4.1 Módulo de Autenticación y Seguridad
* **Autenticación (`AuthController` & `models/Usuario.php`):**
  * La validación de credenciales se efectúa mediante documento de identidad (`identificacion`) y contraseña en texto plano, verificada contra la base de datos a través de `password_verify($password, $usuario['password'])`.
  * La creación y actualización de contraseñas implementa el algoritmo nativo `password_hash($password, PASSWORD_BCRYPT)`.
* **Seguridad de Sesión (`helpers/Auth.php`):**
  * Al autenticarse satisfactoriamente, el sistema invoca inmediatamente `session_regenerate_id(true)` para mitigar ataques de fijación de sesión (*Session Fixation*).
  * Los atributos del usuario autenticado se hidratan en `$_SESSION`: `user_id`, `user_rol`, `user_nombre`, `user_apellido`, `user_correo` y `user_documento`.
  * El cierre de sesión (`Auth::logout()`) vacía el arreglo `$_SESSION`, destruye la sesión y anula la cookie de sesión en el navegador con fecha de expiración retroactiva.
* **Protección Anti-CSRF:**
  * La clase `Auth` implementa `generateCSRF()`, que crea un token criptográficamente seguro con `bin2hex(random_bytes(32))` y lo almacena en `$_SESSION['csrf_token']`.
  * Cada petición mutable (`POST`) debe incluir el campo `csrf_token`.
  * El helper `Auth::validateCSRF($token)` valida la igualdad en tiempo constante mediante `hash_equals()`. Inmediatamente después de su validación, destruye el token en sesión para impedir ataques de repetición (*Single-Use Token Pattern*).

#### 4.2 Módulo de Control de Asistencia RFID y Gestión de Sesiones
* **Mecánica del Toggle de Sesión (`models/Sesion.php` & `controllers/AsistenciaController.php`):**
  * Un instructor solo puede tomar asistencia abriendo formalmente una sesión formativa mediante la interfaz de switch o botón de toggle (*ON/OFF*).
  * **Apertura (Toggle ON - `iniciarSesionAsistencia`):**
    1. Valida que el instructor tenga asignada la ficha (como instructor titular en `ficha.instructor_id` o mediante sesiones programadas).
    2. Comprueba que no exista otra sesión en estado `'Activo'` en esa fecha para la misma ficha.
    3. Si existe una sesión programada para el día, actualiza su estado y sella la hora en `apertura_real = CURTIME()`. Si no existe sesión previa en la grilla, inserta dinámicamente una nueva sesión en estado `'Activo'`.
  * **Cierre y Generación Masiva de Inasistencias (Toggle OFF - `cerrarSesionAsistencia`):**
    1. Ejecuta una **transacción PDO atómica** (`beginTransaction()`).
    2. Modifica el estado de la sesión a `'Finalizada'` y establece `hora_fin = COALESCE(hora_fin, CURTIME())` y `cierre_real = CURTIME()`.
    3. Ejecuta una inserción masiva en la tabla `inasistencia` para todos los aprendices activos de la ficha que **no tengan** ningún registro en la tabla `asistencia` para esa sesión (`INSERT INTO inasistencia ... SELECT ... WHERE u.id NOT IN (SELECT Usuario_id FROM asistencia ...)`).
    4. Confirma la transacción con `commit()`, garantizando que ninguna sesión cerrada deje aprendices ausentes sin su correspondiente inasistencia registrada.
* **Procesamiento de Lecturas RFID USB (`AsistenciaController::registrarMarcacion`):**
  * El lector RFID de escritorio opera por emulación de teclado USB, escribiendo el código en un campo HTML con foco automático permanente (`#rfid_tag`).
  * Al detectarse la lectura, una petición AJAX en segundo plano transmite el tag al endpoint `?action=asistencia/registrarMarcacion`.
  * El controlador limpia el valor (`preg_replace('/[^a-zA-Z0-9\-]/', '', $rfidTag)`), consulta el usuario por `codigo_llavero` y verifica que el aprendiz pertenezca a una ficha con sesión formativa activa en ese instante.
  * Valida duplicados: Si el aprendiz ya marcó entrada en esa sesión, rechaza la operación sin duplicar registros.
* **Regla de Negocio de Retardo (10 Minutos de Tolerancia - RF-HOR-42):**
  * La hora base de la sesión se determina como el valor máximo entre la hora programada de la sesión (`hora_inicio`) y la hora real en que el instructor abrió el toggle (`apertura_real`).
  * Se genera una ventana de tolerancia sumando 10 minutos a la hora base (`$tolerancia = (clone $horaBase)->modify('+10 minutes')`).
  * Si la marcación ocurre dentro de los 10 minutos, se marca como `'Asistió'` con `minutos_retardo = 0`.
  * Si la marcación supera la tolerancia, se calcula la diferencia en minutos (`$diff->h * 60 + $diff->i`), se etiqueta el estado como `'Retardo'` y se persiste en `asistencia.minutos_retardo`.

#### 4.3 Módulo de Excusas Médicas
* **Vinculación Obligatoria a Inasistencias Reales:**
  * Los aprendices solo pueden crear excusas para inasistencias registradas previamente en la tabla `inasistencia` que aún no hayan sido justificadas (`asistenciaModel->obtenerInasistenciasSinExcusaPorAprendiz()`). No existe radicación libre de fechas en el frontend.
* **Seguridad Estricta en Archivos Adjuntos:**
  * Restricción estricta de tamaño máximo de archivo a **2 MB**.
  * Restricción por lista blanca de extensiones: `.pdf`, `.jpg`, `.jpeg`, `.png`.
  * **Validación Binaria del Tipo MIME:** Para neutralizar la subida de ejecutables camuflados (*MIME Spoofing*), el controlador no confía en la cabecera `$_FILES['archivo']['type']`. En su lugar, analiza los *magic bytes* del archivo temporal mediante la extensión `finfo` de PHP (`finfo_open(FILEINFO_MIME_TYPE)` y `finfo_file()`), aceptando únicamente `application/pdf`, `image/jpeg` y `image/png`.
  * Los archivos aprobados son renombrados con un identificador unívoco aleatorio (`excusa_{idUsuario}_{inasistenciaId}_{timestamp}_{hash}.{ext}`) y movidos a `assets/uploads/excusas/` con permisos de lectura controlados.
* **Mecanismo de Doble Aprobación por Co-Docencia (RF-HOR-34):**
  * El modelo `ExcusaMedica.php` analiza si la ficha y jornada cuentan con múltiples instructores asignados en la misma franja horaria (`requiereDobleAprobacion()`).
  * Si aplica co-docencia y un instructor emite la aprobación, la excusa no se da por cerrada; se registra en `estado_excusa` con estado intermedio `'Pendiente'` y la anotación `"[1 de 2 firmas requeridas por co-docencia]"`.
  * Solo cuando el segundo instructor asignado evalúa y aprueba la solicitud, el sistema actualiza el registro a `'Aprobada'`, completando la doble firma y actualizando la inasistencia asociada a estado `'Justificada'`. El Administrador posee la facultad jerárquica de aprobarla de forma unificada en un solo paso.

#### 4.4 Gestión Académica y Sincronización de Horarios
* **CRUDs de Maestros:**
  * Controladores modulares para `ProgramaController`, `FichaController`, `CompetenciaController`, `JornadaController` y `UsuarioController`.
  * Las fichas validan que el código sea numérico de 7 dígitos y que el instructor asignado posea el rol correspondiente (`Rol_id = 2` o `1`).
* **Módulo de Mallas Horarias (`helpers/HorarioParser.php` y `helpers/HorarioSincronizador.php`):**
  * **Lectura Excel:** `HorarioParser` inspecciona archivos matriciales del SENA extrayendo metadatos (código de ficha de 7 dígitos en encabezado, mes y año) y descompone las celdas combinadas de la grilla en bloques canónicos de 3 horas lectivas.
  * **Validación de Conflictos (`HorarioValidator.php`):** Detecta traslapes de horario donde un mismo instructor o una misma ficha figuren asignados simultáneamente a dos espacios formativos en la misma fecha y rango de horas.
  * **Sincronización Diferencial Inteligente:** Al cargar un nuevo archivo, el sistema compara bloque por bloque contra la tabla `sesion`:
    * *Sesión Nueva:* La inserta en la base de datos resolviendo instructores por coincidencia exacta o normalizada de nombres.
    * *Sesión Idéntica:* La omite (`omitidas++`), reduciendo escrituras innecesarias en disco.
    * *Sesión con Modificación:* Si no tiene asistencias asociadas, actualiza sus campos; si ya cuenta con asistencias registradas, el sistema la marca como **Protegida** y bloquea su alteración para preservar la auditoría de marcaciones.
    * *Sesión Previamente Cancelada:* Si vuelve a figurar en el archivo Excel, reactiva su estado a `'Activo'`.

---

### 5. CONVENCIONES Y ESTÁNDARES TÉCNICOS DEL REPOSITORIO

#### 5.1 Convenciones de Código PHP
* **Tipado Estricto:** Los controladores modernos y modelos estratégicos inician con la directiva `declare(strict_types=1);` para forzar la coherencia de tipos en firmas de métodos y retornos.
* **Nomenclatura:**
  * Clases en **PascalCase** (`AsistenciaController`, `IngresoAsistencia`, `ExcusaMedica`), coincidiendo con el nombre físico del archivo PHP.
  * Métodos y variables en **camelCase** (`iniciarSesionAsistencia`, `$instructorId`, `$sesionActiva`).
  * Constantes en **MAYÚSCULAS_CON_GUION_BAJO** (`ROOT_PATH`, `BASE_URL`, `ROL_ADMINISTRADOR`).
  * Tablas y columnas de la base de datos: Respeta rigurosamente los nombres existentes en el esquema oficial (nombres en singular, claves foráneas como `Usuario_id`, `Sesion_id`, `Ficha_id`).

#### 5.2 Estándares de Seguridad en Base de Datos (PDO)
* Queda terminantemente prohibida la interpolación directa de variables o concatenación de cadenas en sentencias SQL.
* Toda operación contra la base de datos se ejecuta mediante `PDO::prepare()` enlazando parámetros mediante arreglos asociativos en `execute([ ':param' => $valor ])` o mediante `$stmt->bindValue()`, neutralizando cualquier vector de inyección SQL.
* Conexión configurada con `PDO::ATTR_EMULATE_PREPARES => false`, delegando la preparación real y la separación de código/datos al motor MariaDB/MySQL.

#### 5.3 Estructura de Assets y Subidas de Archivos
* **Archivos Estáticos:** Centralizados en `assets/css/` y `assets/js/`.
* **Carga de Evidencias:** Los documentos adjuntos se almacenan exclusivamente en la ruta física definida por la constante `UPLOAD_PATH . 'excusas/'` (ruta relativa `assets/uploads/excusas/`). No se almacenan blobs binarios directamente en tablas SQL, solo la ruta relativa de resolución web.
* **Control de Errores en Subidas:** Cada subida comprueba `$_FILES['archivo']['error'] === UPLOAD_ERR_OK` y valida el movimiento seguro mediante `move_uploaded_file()`.

#### 5.4 Gestión de Dependencias y Composer
* **Ubicación del Vendor:** Siguiendo la directiva de configuración en `composer.json` (`"config": { "vendor-dir": "librerias" }`), todas las librerías gestionadas por Composer se descargan e instalan en el directorio **`librerias/`**, nunca en `vendor/`.
* **Autoloading:** El archivo central de carga es `librerias/autoload.php`.
* **Librerías Manuales:** Librerías que no provienen de repositorios Packagist (como FPDF) residen en subdirectorios propios dentro de `librerias/` (`librerias/fpdf/fpdf.php`).
* **Integridad del Repositorio:** El directorio `librerias/` y el binario `composer.phar` forman parte de la estructura base del proyecto y no deben ser modificados ni reemplazados sin previa planificación de dependencias.
