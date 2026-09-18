# 🔐 SCIA — Sistema de Control de Ingreso de Aprendices

Sistema web desarrollado en **PHP (MVC)** con **MySQL** para gestionar el control de asistencia de aprendices del SENA mediante tecnología RFID. Permite administrar usuarios, fichas, aprendices, registros de asistencia y excusas médicas.

---

## 📋 Tabla de Contenidos

- [Requisitos](#-requisitos)
- [Instalación](#-instalación)
- [Credenciales por Defecto](#-credenciales-por-defecto)
- [Arquitectura del Proyecto](#-arquitectura-del-proyecto)
- [Estructura de Carpetas](#-estructura-de-carpetas)
- [Flujo de una Petición HTTP](#-flujo-de-una-petición-http)
- [Capas del Sistema](#-capas-del-sistema)
  - [Front Controller (index.php)](#-front-controller-indexphp)
  - [Database.php — Conexión a MySQL](#-databasephp--conexión-a-mysql)
  - [Router.php — Enrutador](#-routerphp--enrutador)
  - [Auth.php — Autenticación y Seguridad](#-authphp--autenticación-y-seguridad)
  - [Validator.php — Validación de Datos](#-validatorphp--validación-de-datos)
  - [Controllers — Lógica de Negocio](#-controllers--lógica-de-negocio)
  - [Models — Acceso a Base de Datos](#-models--acceso-a-base-de-datos)
  - [Views — Interfaz de Usuario](#-views--interfaz-de-usuario)
  - [Assets — CSS y JavaScript](#-assets--css-y-javascript)
- [Base de Datos](#-base-de-datos)
- [Mapa de Rutas](#-mapa-de-rutas)
- [Flujos Específicos](#-flujos-específicos)
  - [Flujo de Login](#flujo-de-login)
  - [Flujo CRUD](#flujo-crud-crear-usuario)
  - [Flujo de Excusas Médicas](#flujo-de-excusas-médicas)
- [Roles del Sistema](#-roles-del-sistema)
- [Tecnologías Utilizadas](#-tecnologías-utilizadas)

---

## 💻 Requisitos

| Requisito | Versión |
|-----------|---------|
| PHP | 8.0 o superior |
| MySQL / MariaDB | 5.7+ / 10.4+ |
| Apache | 2.4+ (con mod_rewrite) |
| XAMPP | 8.x (recomendado) |

---

## 🚀 Instalación

1. **Clonar el repositorio** dentro de la carpeta de XAMPP:
   ```bash
   cd C:\xampp\htdocs
   git clone <url-del-repo> scia
   ```

2. **Importar la base de datos** en phpMyAdmin (`http://localhost/phpmyadmin`):
   - Ir a la pestaña **Importar**
   - Seleccionar el archivo `database/schema.sql`
   - Ejecutar

3. **Verificar la configuración** de conexión en `config/Database.php`:
   ```php
   private $host     = "localhost";
   private $db_name  = "sistema_asistencia_rfid";
   private $username = "root";
   private $password = "";
   ```

4. **Acceder al sistema**:
   ```
   http://localhost/scia/
   ```

---

## 🔑 Credenciales por Defecto

| Campo | Valor |
|-------|-------|
| **Documento** | `admin` |
| **Contraseña** | `Admin123*` |
| **Rol** | Administrador |

---

## 🏗️ Arquitectura del Proyecto

El proyecto implementa el patrón **MVC (Modelo-Vista-Controlador) con Front Controller**.

```
┌─────────────────────────────────────────────────────────────┐
│                      🌐 NAVEGADOR                           │
│              (petición HTTP + respuesta HTML)                │
└──────────────────────────┬──────────────────────────────────┘
                           │
                           ▼
┌──────────────────────────────────────────────────────────────┐
│                   📄 index.php                               │
│              (FRONT CONTROLLER)                              │
│  Define constantes, carga clases, inicia sesión,             │
│  conecta BD, despacha al Router                              │
└──────────────────────────┬───────────────────────────────────┘
                           │
                           ▼
┌──────────────────────────────────────────────────────────────┐
│                   🔀 Router.php                              │
│  Parsea ?action=controlador/metodo                           │
│  Verifica autenticación (Auth::requireLogin)                 │
│  Instancia el Controller y ejecuta el método                 │
└──────────┬────────────────────────────────────┬──────────────┘
           │                                    │
           ▼                                    ▼
┌─────────────────────┐              ┌─────────────────────────┐
│  🔐 Auth.php        │              │  ✅ Validator.php       │
│  Sesiones, roles,   │              │  Validación de datos    │
│  CSRF, flash msgs   │              │  de formularios         │
└─────────────────────┘              └─────────────────────────┘
           │
           ▼
┌──────────────────────────────────────────────────────────────┐
│                   🎮 CONTROLLER                              │
│  Recibe la petición, valida datos, coordina Modelo ↔ Vista   │
└──────────┬───────────────────────────────────┬───────────────┘
           │                                   │
           ▼                                   ▼
┌──────────────────────┐            ┌──────────────────────────┐
│  📊 MODEL            │            │  🖼️ VIEW                 │
│  Consultas SQL       │            │  HTML + PHP              │
│  (prepared stmts)    │            │  header + contenido +    │
│                      │            │  footer                  │
│         │            │            └──────────┬───────────────┘
│         ▼            │                       │
│  ┌──────────────┐    │                       ▼
│  │ 💾 MySQL     │    │            ┌──────────────────────────┐
│  │ (PDO)        │    │            │  🎨 CSS + ⚡ JS          │
│  └──────────────┘    │            │  Tema oscuro, sidebar,   │
└──────────────────────┘            │  búsqueda, animaciones   │
                                    └──────────────────────────┘
```

**Reglas fundamentales del MVC:**
- El **Modelo** NUNCA genera HTML
- La **Vista** NUNCA toca la base de datos
- El **Controlador** es el intermediario que conecta ambos

---

## 📁 Estructura de Carpetas

```
proyecto-adso-sistema-control-ingreso-main/
│
├── 📄 index.php                       ← Punto de entrada único
│
├── 📁 config/
│   └── Database.php                   ← Conexión PDO a MySQL (Singleton)
│
├── 📁 helpers/
│   ├── Router.php                     ← Enrutador de peticiones
│   ├── Auth.php                       ← Sesiones, roles, CSRF, flash messages
│   └── Validator.php                  ← Validación de formularios
│
├── 📁 models/
│   ├── Rol.php                        ← Tabla: roles
│   ├── Usuario.php                    ← Tabla: usuarios
│   ├── Ficha.php                      ← Tabla: fichas
│   ├── Aprendiz.php                   ← Tabla: aprendices
│   └── ExcusaMedica.php               ← Tabla: excusas_medicas
│
├── 📁 controllers/
│   ├── DashboardController.php        ← Página principal con estadísticas
│   ├── AuthController.php             ← Login y logout
│   ├── UsuarioController.php          ← CRUD de usuarios
│   ├── FichaController.php            ← CRUD de fichas
│   ├── AprendizController.php         ← CRUD de aprendices
│   └── ExcusaController.php           ← Gestión de excusas médicas
│
├── 📁 views/
│   ├── layouts/
│   │   ├── header.php                 ← HTML base, sidebar, navbar, alertas
│   │   └── footer.php                 ← Cierre HTML, carga JavaScript
│   ├── auth/
│   │   └── login.php                  ← Formulario de inicio de sesión
│   ├── dashboard/
│   │   └── index.php                  ← Tarjetas de estadísticas
│   ├── usuarios/
│   │   ├── index.php                  ← Tabla listado de usuarios
│   │   └── form.php                   ← Formulario crear/editar usuario
│   ├── fichas/
│   │   ├── index.php                  ← Tabla listado de fichas
│   │   └── form.php                   ← Formulario crear/editar ficha
│   ├── aprendices/
│   │   ├── index.php                  ← Tabla listado de aprendices
│   │   └── form.php                   ← Formulario crear/editar aprendiz
│   └── excusas/
│       ├── index.php                  ← Tabla listado de excusas
│       ├── form.php                   ← Formulario nueva excusa
│       └── revisar.php                ← Detalle + aprobar/rechazar
│
├── 📁 assets/
│   ├── css/
│   │   └── style.css                  ← Hoja de estilos (tema oscuro)
│   ├── js/
│   │   └── app.js                     ← Sidebar, búsqueda, confirmaciones
│   └── uploads/
│       └── excusas/                   ← Archivos PDF/JPG subidos
│
├── 📁 database/
│   └── schema.sql                     ← Script para crear la BD + datos iniciales
│
└── 📁 migrations/
    └── 001-sistema-ingresos.sql       ← Migración alternativa de la BD
```

---

## 🔄 Flujo de una Petición HTTP

Cuando el usuario visita `http://localhost/scia/?action=usuarios/editar&id=5`, esto es lo que pasa internamente:

### Paso a paso:

| # | Componente | Acción |
|---|-----------|--------|
| 1 | `index.php` | Define constantes `ROOT_PATH`, `BASE_URL`, `UPLOAD_PATH` |
| 2 | `index.php` | Carga todas las clases con `require_once` |
| 3 | `Auth::init()` | Ejecuta `session_start()` para activar `$_SESSION` |
| 4 | `Database::getInstance()` | Crea conexión PDO (Singleton: solo 1 conexión) |
| 5 | `Router->dispatch()` | Lee `$_GET['action']` → `"usuarios/editar"` |
| 6 | `Router` | Separa con `explode('/')` → controlador: `"usuarios"`, método: `"editar"` |
| 7 | `Router` | Busca en el mapa → `"usuarios"` → `UsuarioController` |
| 8 | `Router` | Verifica que NO es ruta pública → requiere autenticación |
| 9 | `Auth::requireLogin()` | Verifica `$_SESSION['user_id']` → si no existe, redirige al login |
| 10 | `Router` | Instancia `new UsuarioController($db)` → llama `->editar()` |
| 11 | `UsuarioController` | `Auth::requireAdmin()` → verifica que el rol sea 1 o 2 |
| 12 | `UsuarioController` | Lee `$_GET['id']` → `5` |
| 13 | `Usuario::getById(5)` | `SELECT * FROM usuarios WHERE id_usuario = 5` → retorna array |
| 14 | `Rol::getAll()` | `SELECT * FROM roles` → para llenar el `<select>` del formulario |
| 15 | `Auth::generateCSRF()` | Genera token aleatorio → lo guarda en `$_SESSION` |
| 16 | `require` | Carga `views/usuarios/form.php` con las variables `$usuario`, `$roles`, `$csrf_token` |
| 17 | Navegador | Recibe HTML completo + CSS + JS → renderiza la página |

---

## 🧱 Capas del Sistema

### 📄 Front Controller (`index.php`)

Es la **puerta de entrada única** de toda la aplicación. Todas las URLs pasan por aquí:

```
http://localhost/scia/                          → Dashboard (default)
http://localhost/scia/?action=usuarios           → Lista de usuarios
http://localhost/scia/?action=auth/login          → Login
http://localhost/scia/?action=fichas/crear        → Formulario nueva ficha
```

**Responsabilidades:**
1. Definir constantes globales (`ROOT_PATH`, `BASE_URL`, `UPLOAD_PATH`)
2. Cargar clases PHP con `require_once`
3. Iniciar sesión PHP
4. Obtener conexión a la base de datos
5. Despachar la petición al Router

---

### 🗄️ `Database.php` — Conexión a MySQL

Implementa el **patrón Singleton** para garantizar que solo exista **1 conexión** a la base de datos durante toda la petición:

```
Primera llamada  → Crea nueva conexión PDO → La guarda internamente
Segunda llamada  → Retorna la misma conexión (no crea otra)
Tercera llamada  → Retorna la misma conexión
```

**Configuración:**
| Parámetro | Valor |
|-----------|-------|
| Host | `localhost` |
| Base de datos | `sistema_asistencia_rfid` |
| Usuario | `root` |
| Contraseña | *(vacía)* |
| Charset | `utf8mb4` |
| Modo de errores | `ERRMODE_EXCEPTION` (lanza excepciones) |
| Modo de fetch | `FETCH_ASSOC` (retorna arrays asociativos) |

---

### 🔀 `Router.php` — Enrutador

Traduce la URL a una **clase PHP** y un **método** específico:

```
?action=fichas/crear
         │       │
         │       └── método: crear()
         └────────── controllerKey: "fichas" → FichaController
```

**Mapa de controladores:**

| Clave en URL | Clase PHP |
|-------------|-----------|
| `dashboard` | `DashboardController` |
| `auth` | `AuthController` |
| `usuarios` | `UsuarioController` |
| `fichas` | `FichaController` |
| `aprendices` | `AprendizController` |
| `excusas` | `ExcusaController` |

**Proceso interno:**
1. Leer `$_GET['action']` (default: `"dashboard"`)
2. Separar en `controllerKey` / `method` con `explode('/')`
3. Sanitizar el nombre del método (solo caracteres alfanuméricos)
4. Verificar que el controlador exista en el mapa
5. Verificar que el archivo PHP exista
6. Si NO es ruta pública → `Auth::requireLogin()`
7. Instanciar el controlador y ejecutar el método

**Rutas públicas** (no requieren autenticación):
- `auth/login`
- `auth/doLogin`
- `auth/logout`

---

### 🔐 `Auth.php` — Autenticación y Seguridad

Maneja **4 responsabilidades**:

#### 1. Gestión de Sesiones

Al hacer login exitoso, se guardan estos datos en `$_SESSION`:

| Variable | Ejemplo | Uso |
|----------|---------|-----|
| `user_id` | `1` | Identificar al usuario |
| `user_rol` | `1` | Control de acceso |
| `user_nombre` | `"Administrador"` | Mostrar en sidebar |
| `user_apellido` | `"Sistema"` | Nombre completo |
| `user_correo` | `"admin@sistema.local"` | Referencia |
| `user_documento` | `"admin"` | Referencia |

#### 2. Control de Acceso por Roles

| Método | ¿Quién puede pasar? | Uso |
|--------|---------------------|-----|
| `requireLogin()` | Cualquier usuario autenticado | Protege todas las rutas |
| `requireAdmin()` | Solo rol 1 (Admin) o rol 2 (Instructor) | Protege gestión y CRUD |
| `isAprendiz()` | Verifica si es rol 3 | Lógica condicional |
| `isAdminOrInstructor()` | Roles 1 o 2 | Mostrar/ocultar elementos en vistas |

#### 3. Protección CSRF

Previene ataques de falsificación de peticiones:
1. **Al mostrar formulario:** `generateCSRF()` → genera token aleatorio → lo guarda en `$_SESSION` → lo inserta como `<input type="hidden">`
2. **Al recibir POST:** `validateCSRF($token)` → compara token enviado vs guardado → si no coincide, rechaza la petición

#### 4. Flash Messages

Mensajes temporales que se muestran **1 sola vez** después de una acción:

```
Acción exitosa → Auth::setFlash('success', 'Usuario creado')
                      ↓
              Se guarda en $_SESSION['flash']
                      ↓
              Siguiente página → header.php llama Auth::getFlash()
                      ↓
              Muestra alerta verde + ELIMINA el mensaje de la sesión
```

---

### ✅ `Validator.php` — Validación de Datos

Valida los datos de formularios **antes** de guardarlos en la base de datos:

| Método | Qué valida | Ejemplo |
|--------|-----------|---------|
| `required($valor, $campo)` | No esté vacío | `"nombre"` no puede estar vacío |
| `email($valor)` | Formato de correo | `"user@dominio.com"` |
| `minLength($valor, $min, $campo)` | Longitud mínima | Contraseña ≥ 6 caracteres |
| `maxLength($valor, $max, $campo)` | Longitud máxima | Documento ≤ 20 caracteres |
| `positiveInt($valor, $campo)` | Número positivo | IDs, cantidades |
| `date($valor, $campo)` | Formato `YYYY-MM-DD` | Fechas de excusas |
| `time($valor, $campo)` | Formato `HH:MM` | Horarios |
| `fileType($file, $tipos, $campo)` | Tipo de archivo | Solo PDF, JPG, PNG |
| `fileSize($file, $max, $campo)` | Tamaño máximo | Máximo 5 MB |
| `sanitize($valor)` | Previene XSS | Escapa caracteres HTML |

**Uso típico en un controller:**

```php
Validator::reset();                                    // Limpiar errores anteriores
Validator::required($data['nombre'], 'nombre');        // Validar campo
Validator::email($data['correo']);                      // Validar formato
Validator::minLength($data['password'], 6, 'contraseña');

if (!Validator::isValid()) {
    // HAY ERRORES → redirigir al formulario
    $errores = Validator::getErrors();
    // Ejemplo: ["El campo nombre es obligatorio.", "La contraseña debe tener al menos 6 caracteres."]
    Auth::setFlash('error', implode('<br>', $errores));
    header('Location: ...');
    exit;
}
// TODO OK → guardar en BD
```

---

### 🎮 Controllers — Lógica de Negocio

Cada controlador gestiona **un módulo** del sistema. Todos siguen el patrón CRUD:

| Método | Verbo HTTP | URL | Acción |
|--------|-----------|-----|--------|
| `index()` | GET | `?action=usuarios` | Listar registros (`SELECT`) |
| `crear()` | GET | `?action=usuarios/crear` | Mostrar formulario vacío |
| `editar()` | GET | `?action=usuarios/editar&id=5` | Mostrar formulario con datos |
| `guardar()` | POST | `?action=usuarios/guardar` | Validar → `INSERT` o `UPDATE` |
| `eliminar()` | GET | `?action=usuarios/eliminar&id=5` | Confirmar → `DELETE` |

#### DashboardController
- `index()` → Obtiene estadísticas (total usuarios, fichas, aprendices, excusas pendientes) → Renderiza tarjetas

#### AuthController
- `login()` → Muestra formulario de login
- `doLogin()` → Valida CSRF → Valida campos → `authenticate()` → Si OK, guarda sesión → Redirect al dashboard
- `logout()` → Destruye sesión → Redirect al login

#### UsuarioController
- CRUD estándar para la tabla `usuarios`
- Solo accesible por Admin/Instructor
- Al crear: encripta contraseña con `password_hash()`
- Al editar: contraseña es opcional (si vacía, no se cambia)
- No permite eliminarse a sí mismo

#### FichaController
- CRUD estándar para la tabla `fichas`
- Carga lista de instructores para el `<select>` del formulario

#### AprendizController
- **Caso especial**: Crear un aprendiz implica 2 operaciones:
  1. Crear registro en `usuarios` (con rol 3)
  2. Crear registro en `aprendices` (con el `id_usuario` obtenido + `id_ficha` + `codigo_rfid`)
- Al editar: actualiza ambas tablas
- Al eliminar: borra de ambas tablas

#### ExcusaController
- **Aprendiz**: Puede crear excusas + subir archivo adjunto
- **Admin/Instructor**: Puede ver todas las excusas, filtrar por pendientes, y aprobar/rechazar
- `guardar()` → Valida archivo → `move_uploaded_file()` a `assets/uploads/excusas/`
- `procesar()` → Cambia estado a "Aprobada" o "Rechazada" + guarda comentario del revisor

---

### 📊 Models — Acceso a Base de Datos

Cada modelo encapsula las consultas SQL de **1 tabla**. Todos usan **prepared statements** para prevenir SQL Injection.

#### `Usuario.php` → tabla `usuarios`

| Método | SQL | Retorna |
|--------|-----|---------|
| `getAll()` | `SELECT u.*, r.nombre FROM usuarios u JOIN roles r...` | Array de usuarios con su rol |
| `getById($id)` | `SELECT ... WHERE id_usuario = :id` | 1 usuario o null |
| `getByDocumento($doc)` | `SELECT ... WHERE num_documento = :doc` | 1 usuario o null |
| `authenticate($doc, $pass)` | Busca por documento + `password_verify()` | Usuario si válido, null si no |
| `create($data)` | `INSERT INTO usuarios ...` + `password_hash()` | bool |
| `update($id, $data)` | `UPDATE usuarios SET ... WHERE id_usuario = :id` | bool |
| `delete($id)` | `DELETE FROM usuarios WHERE id_usuario = :id` | bool |
| `getInstructores()` | `SELECT ... WHERE id_rol IN (1,2) AND estado = 'Activo'` | Array |
| `count()` / `countByRol($rol)` | `SELECT COUNT(*)` | int |

#### `Ficha.php` → tabla `fichas`

| Método | Descripción |
|--------|-------------|
| `getAll()` | Todas las fichas + nombre del instructor líder + conteo de aprendices |
| `getById($id)` | 1 ficha con instructor |
| `getByInstructor($id)` | Fichas de un instructor |
| `create($data)` / `update($id, $data)` / `delete($id)` | CRUD estándar |

#### `Aprendiz.php` → tabla `aprendices`

| Método | Descripción |
|--------|-------------|
| `getAll()` | Todos los aprendices + datos de usuario + datos de ficha |
| `getById($id)` | 1 aprendiz con sus JOINs |
| `getByUsuario($idUsuario)` | Buscar aprendiz por su `id_usuario` |
| `getByRfid($codigo)` | Buscar por código RFID (para marcación) |
| `getByFicha($idFicha)` | Aprendices de una ficha |

#### `ExcusaMedica.php` → tabla `excusas_medicas`

| Método | Descripción |
|--------|-------------|
| `getAll()` | Todas las excusas + datos del aprendiz + nombre del revisor |
| `getByAprendiz($id)` | Excusas de 1 aprendiz |
| `getPendientes()` | Solo las que tienen `estado = 'Pendiente'` |
| `create($data)` | Nueva excusa (estado: Pendiente) |
| `aprobar($id, $revisor, $comentario)` | Cambia estado a "Aprobada" |
| `rechazar($id, $revisor, $comentario)` | Cambia estado a "Rechazada" |

**Seguridad en los modelos:**
- ✅ Todas las consultas usan **prepared statements** (`:parametro`) → previene SQL Injection
- ✅ Contraseñas encriptadas con `password_hash(PASSWORD_BCRYPT)` → nunca en texto plano
- ✅ Verificación con `password_verify()` al autenticar

---

### 🖼️ Views — Interfaz de Usuario

Cada vista se compone de **3 partes** (patrón sandwich):

```
┌─────────────────────────────────────────────┐
│              header.php                      │
│  ├── <!DOCTYPE html>, <head>, <meta>         │
│  ├── CSS (style.css) + Google Fonts          │
│  ├── Font Awesome (iconos)                   │
│  │                                           │
│  ├── SI logueado:                            │
│  │   ├── Sidebar (navegación lateral)        │
│  │   ├── Top Header (título + fecha)         │
│  │   └── Flash alerts (mensajes temporales)  │
│  │                                           │
│  └── SI NO logueado:                         │
│      └── Layout centrado (para login)        │
├─────────────────────────────────────────────┤
│          contenido específico                │
│  (tablas, formularios, tarjetas, etc.)       │
├─────────────────────────────────────────────┤
│              footer.php                      │
│  ├── Cierra etiquetas HTML                   │
│  └── Carga app.js                            │
└─────────────────────────────────────────────┘
```

**El Sidebar cambia según el rol del usuario:**

| Sección del Menú | Admin / Instructor | Aprendiz |
|-------------------|:-:|:-:|
| Dashboard | ✅ | ✅ |
| Usuarios | ✅ | ❌ |
| Fichas | ✅ | ❌ |
| Horarios | ✅ | ❌ |
| Aprendices | ✅ | ❌ |
| Marcación RFID | ✅ | ❌ |
| Historial | ✅ (todos) | ✅ (solo propio) |
| Reportes | ✅ | ❌ |
| Excusas Médicas | ✅ (revisar) | ✅ (enviar) |

---

### 🎨 Assets — CSS y JavaScript

#### `style.css` — Sistema de Diseño

Tema **oscuro profesional** construido con **variables CSS**:

| Variable | Color | Uso |
|----------|-------|-----|
| `--bg-body` | `#0f1117` | Fondo principal |
| `--bg-card` | `#1a1d2e` | Fondo de tarjetas y tablas |
| `--bg-sidebar` | `#161822` | Fondo del sidebar |
| `--accent-green` | `#10b981` | Color primario (botones, links activos) |
| `--accent-blue` | `#3b82f6` | Badges azules |
| `--accent-red` | `#ef4444` | Botones de eliminar, errores |
| `--accent-yellow` | `#f59e0b` | Advertencias |
| `--text-primary` | `#e2e8f0` | Texto principal |
| `--text-muted` | `#64748b` | Texto secundario |

**Componentes CSS incluidos:**
- Sidebar colapsable (desktop) y deslizable (mobile)
- Dashboard con tarjetas de estadísticas
- Tablas de datos con hover
- Formularios con focus glow
- Badges semánticos (activo/inactivo/pendiente/aprobada/rechazada)
- Alertas flash animadas
- Página de login con gradientes radiales
- File upload con drag & drop visual
- Diseño responsive (breakpoints: 768px y 480px)

#### `app.js` — Interactividad

| Funcionalidad | Cómo funciona |
|---------------|---------------|
| **Sidebar toggle (desktop)** | Click en `≪` → clase `collapsed` → sidebar se reduce a 72px → estado guardado en `localStorage` |
| **Sidebar toggle (mobile)** | Click en `☰` → clase `mobile-open` → sidebar se desliza + overlay oscuro |
| **Búsqueda en tablas** | Inputs con atributo `data-search-table="tablaId"` → al escribir, filtra filas cuyo contenido coincida |
| **Confirmación de eliminar** | Links con `data-confirm="¿Mensaje?"` → muestra `confirm()` → si cancela, previene la acción |
| **Auto-ocultar alertas** | Los flash messages desaparecen después de 5 segundos con animación de fade-out |

---

## 💾 Base de Datos

### Diagrama Entidad-Relación

```
┌──────────┐     ┌───────────────┐     ┌──────────────┐
│  roles   │────<│   usuarios    │>────│    fichas     │
│──────────│     │───────────────│     │──────────────│
│ id_rol   │     │ id_usuario    │     │ id_ficha     │
│ nombre   │     │ id_rol (FK)   │     │ codigo_ficha │
└──────────┘     │ num_documento │     │ nombre_prog  │
                 │ nombre        │     │ id_instructor │
                 │ apellido      │     │   _lider(FK) │
                 │ correo        │     └──────┬───────┘
                 │ password      │            │
                 │ estado        │            │
                 │ creado_en     │     ┌──────┴───────┐
                 └───────┬───────┘     │  horarios    │
                         │             │──────────────│
                  ┌──────┴───────┐     │ id_horario   │
                  │ aprendices   │     │ id_ficha(FK) │
                  │──────────────│     │ dia_semana   │
                  │ id_aprendiz  │     │ hora_entrada │
                  │ id_usuario   │     │ hora_salida  │
                  │   (FK, UK)   │     │ tolerancia   │
                  │ id_ficha(FK) │     └──────────────┘
                  │ codigo_rfid  │
                  └──┬───────┬───┘
                     │       │
        ┌────────────┘       └────────────┐
        ▼                                 ▼
┌───────────────────┐         ┌────────────────────┐
│ ingresos          │         │ excusas_medicas     │
│ _asistencia       │         │────────────────────│
│───────────────────│         │ id_excusa           │
│ id_ingreso        │────────<│ id_aprendiz (FK)    │
│ id_aprendiz (FK)  │         │ id_ingreso (FK)     │
│ fecha             │         │ fecha_inicio        │
│ hora_entrada      │         │ fecha_fin           │
│ hora_salida       │         │ motivo              │
│ estado            │         │ archivo_adjunto     │
│ minutos_retardo   │         │ estado              │
│ min_salida_antic  │         │ id_instructor_rev   │
└───────────────────┘         │ comentario_revision │
                              └────────────────────┘
```

### Tablas y sus relaciones

| Tabla | Descripción | Relaciones |
|-------|-------------|------------|
| `roles` | Roles del sistema (Admin, Instructor, Aprendiz) | → tiene muchos `usuarios` |
| `usuarios` | Todos los usuarios del sistema | → pertenece a 1 `rol`, → puede liderar `fichas` |
| `fichas` | Fichas/grupos de formación | → tiene 1 instructor líder, → tiene muchos `aprendices` y `horarios` |
| `horarios` | Horarios semanales por ficha | → pertenece a 1 `ficha` |
| `aprendices` | Vincula un usuario con una ficha + código RFID | → pertenece a 1 `usuario` y 1 `ficha` |
| `ingresos_asistencia` | Registros diarios de entrada/salida | → pertenece a 1 `aprendiz` |
| `excusas_medicas` | Solicitudes de excusa con archivo adjunto | → pertenece a 1 `aprendiz`, puede vincular 1 `ingreso` |

### Estados posibles

**Usuarios:** `Activo`, `Inactivo`

**Ingresos de asistencia:** `A_Tiempo`, `Retardo`, `Salida_Temprana`, `Inasistencia`, `Justificado`

**Excusas médicas:** `Pendiente`, `Aprobada`, `Rechazada`

---

## 🗺️ Mapa de Rutas

| URL (`?action=`) | Controller | Método | Descripción |
|---|---|---|---|
| *(vacío)* / `dashboard` | `DashboardController` | `index()` | Dashboard con estadísticas |
| `auth/login` | `AuthController` | `login()` | Formulario de login |
| `auth/doLogin` | `AuthController` | `doLogin()` | Procesar login (POST) |
| `auth/logout` | `AuthController` | `logout()` | Cerrar sesión |
| `usuarios` | `UsuarioController` | `index()` | Listar usuarios |
| `usuarios/crear` | `UsuarioController` | `crear()` | Formulario nuevo usuario |
| `usuarios/editar&id=X` | `UsuarioController` | `editar()` | Formulario editar usuario |
| `usuarios/guardar` | `UsuarioController` | `guardar()` | Guardar usuario (POST) |
| `usuarios/eliminar&id=X` | `UsuarioController` | `eliminar()` | Eliminar usuario |
| `fichas` | `FichaController` | `index()` | Listar fichas |
| `fichas/crear` | `FichaController` | `crear()` | Formulario nueva ficha |
| `fichas/editar&id=X` | `FichaController` | `editar()` | Formulario editar ficha |
| `fichas/guardar` | `FichaController` | `guardar()` | Guardar ficha (POST) |
| `fichas/eliminar&id=X` | `FichaController` | `eliminar()` | Eliminar ficha |
| `aprendices` | `AprendizController` | `index()` | Listar aprendices |
| `aprendices/crear` | `AprendizController` | `crear()` | Formulario nuevo aprendiz |
| `aprendices/editar&id=X` | `AprendizController` | `editar()` | Formulario editar aprendiz |
| `aprendices/guardar` | `AprendizController` | `guardar()` | Guardar aprendiz (POST) |
| `aprendices/eliminar&id=X` | `AprendizController` | `eliminar()` | Eliminar aprendiz |
| `excusas` | `ExcusaController` | `index()` | Listar excusas |
| `excusas/crear` | `ExcusaController` | `crear()` | Formulario nueva excusa |
| `excusas/guardar` | `ExcusaController` | `guardar()` | Guardar excusa (POST + archivo) |
| `excusas/revisar&id=X` | `ExcusaController` | `revisar()` | Ver excusa para revisión |
| `excusas/procesar` | `ExcusaController` | `procesar()` | Aprobar/rechazar excusa (POST) |

---

## 🔄 Flujos Específicos

### Flujo de Login

```
1. Usuario abre la URL del sistema
2. index.php → Router → ¿tiene sesión? → NO
3. Redirect a ?action=auth/login
4. AuthController::login() → renderiza login.php + genera CSRF
5. Usuario ingresa documento + contraseña
6. POST → AuthController::doLogin()
7. Valida CSRF token ✓
8. Valida campos no vacíos ✓
9. Usuario::authenticate(documento, contraseña)
   → SELECT FROM usuarios WHERE num_documento = ?
   → password_verify(contraseña, hash_guardado)
10. ¿Válido?
    → SÍ: Auth::login() → guarda en $_SESSION → Redirect al dashboard
    → NO: muestra error "Credenciales incorrectas"
```

### Flujo CRUD (Crear Usuario)

```
1. Admin hace click en "Nuevo Usuario"
2. GET ?action=usuarios/crear
3. UsuarioController::crear()
   → Carga roles del modelo Rol
   → Genera CSRF token
   → Renderiza views/usuarios/form.php (vacío)
4. Admin llena el formulario
5. POST → ?action=usuarios/guardar
6. UsuarioController::guardar()
   → Valida CSRF ✓
   → Recoge datos de $_POST
   → Validator: required, email, minLength ✓
   → ¿Errores? → Flash error + redirect al form
   → ¿OK? → Usuario::create($data)
      → INSERT INTO usuarios ... + password_hash()
   → ¿Duplicate? → Flash "documento o correo ya existe"
   → ¿Éxito? → Flash "Usuario creado correctamente" ✅
7. Redirect → ?action=usuarios (lista)
```

### Flujo de Excusas Médicas

```
APRENDIZ:
1. Click "Nueva Excusa"
2. Llena: fecha inicio, fecha fin, motivo
3. Sube archivo PDF/JPG/PNG (máx 5MB)
4. ExcusaController::guardar()
   → Valida datos + tipo/tamaño de archivo
   → move_uploaded_file() → assets/uploads/excusas/
   → ExcusaMedica::create() → estado: "Pendiente"
5. Flash: "Excusa enviada. Pendiente de revisión"

ADMIN/INSTRUCTOR:
6. Ve lista de excusas → filtro "Pendientes"
7. Click "Revisar" en una excusa
8. Ve detalle: aprendiz, fechas, motivo, archivo adjunto
9. Escribe comentario (opcional)
10. Click "Aprobar" o "Rechazar"
    → ExcusaMedica::aprobar() o rechazar()
    → Si aprobada + tiene ingreso vinculado → justificar la asistencia
11. Flash: "Excusa aprobada/rechazada"
```

---

##  Roles del Sistema

| Rol | ID | Permisos |
|-----|:--:|----------|
| **Administrador** | 1 | Acceso total: CRUD de usuarios, fichas, aprendices, horarios. Revisión de excusas. Reportes. |
| **Instructor** | 2 | Gestión de fichas y aprendices asignados. Revisión de excusas. Historial de asistencia. |
| **Aprendiz** | 3 | Ver su propio historial de asistencia. Enviar excusas médicas. Ver estado de sus excusas. |

---

## Tecnologías Utilizadas

| Categoría | Tecnología |
|-----------|-----------|
| **Backend** | PHP 8.x (POO, PDO, MVC) |
| **Base de datos** | MySQL / MariaDB |
| **Servidor** | Apache (XAMPP) |
| **Frontend** | HTML5, CSS3 (Variables CSS, Flexbox, Grid) |
| **JavaScript** | Vanilla JS (ES6+) |
| **Tipografía** | Google Fonts (Inter) |
| **Iconos** | Font Awesome 6.5 |
| **Seguridad** | bcrypt (contraseñas), CSRF tokens, prepared statements, XSS sanitization |

---

## 📄 Licencia

Proyecto académico — ADSO (Análisis y Desarrollo de Software) — SENA.
