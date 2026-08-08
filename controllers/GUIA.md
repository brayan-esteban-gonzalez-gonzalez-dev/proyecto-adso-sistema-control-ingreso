# controllers/ — GUÍA DE ARQUITECTURA (API)

> Qué va en esta carpeta, qué funciones son obligatorias y cómo se comunica
> con `models/` y con el frontend mediante JSON.

---

## 1. Responsabilidad del controlador (en una API)

El controlador actúa como la "cara" de la API y el coordinador del backend:

1. **Recibe la petición** (`$_GET`, `$_POST`, `$_FILES`) rutada desde `index.php?action=...`.
2. **Autoriza** (verifica si hay sesión y si el rol es el adecuado).
3. **Valida** los datos de entrada (nunca confía en el JavaScript del frontend).
4. **Llama al Modelo** (PDO) para consultar/escribir en la base de datos `sistema-ingresos`.
5. **Responde SIEMPRE JSON** usando `$this->ok()` o `$this->fail()`.

**NO existe render de vistas en esta arquitectura.** El controlador nunca devuelve HTML,
nunca hace `echo` de una página ni incluye `header.php`. Todo el HTML vive en `views/`
y es estático.

---

## 2. Archivos que deben vivir aquí

| Archivo | Controla entidades | Rutas `?action=` asociadas |
|---|---|---|
| `ControllerBase.php` | (Clase base de la que todos heredan) | — |
| `AuthController.php` | Login, Sesión | `login`, `logout`, `sesion` |
| `AprendizController.php` | Aprendices | `aprendices.listar`, `aprendices.crear`, `aprendices.actualizar`, `aprendices.desactivar`, `aprendices.asignarFicha`, `aprendices.ver` |
| `FichaController.php` | Fichas y Horarios | `fichas.*`, `horarios.*` |
| `IngresoController.php` | Asistencia (RFID) | `ingresos.marcar`, `ingresos.historial`, `ingresos.listarPorFecha` |
| `ExcusaController.php` | Excusas Médicas | `excusas.crear`, `excusas.misExcusas`, `excusas.listarPendientes`, `excusas.revisar` |
| `ReporteController.php` | Reportes | `reportes.generar`, `reportes.exportarPdf`, `reportes.exportarExcel` |

---

## 3. ControllerBase.php — funciones necesarias

Como todo es una API, la clase base proporciona helpers estandarizados para responder
JSON y proteger rutas.

| Función | Firma | Para qué sirve |
|---|---|---|
| `ok` | `ok(array $data = [], string $mensaje = "")` | Respuesta 200 de éxito → `{ok:true, data, mensaje}` |
| `fail` | `fail(string $error, int $status = 422)` | Respuesta de error → `{ok:false, error}` con código HTTP (422, 400, 404, etc.) |
| `requireAuth` | `requireAuth(): array` | Si no hay sesión válida, envía 401 JSON y aborta. Si la hay, devuelve los datos de la sesión. |
| `requireRol` | `requireRol(string|array $rolPermitido): void` | Si el usuario no tiene el rol exigido, envía 403 JSON y aborta. |

### Esqueleto de ControllerBase.php

```php
<?php

class ControllerBase
{
    /**
     * Respuesta de éxito estándar. Siempre HTTP 200.
     */
    protected function ok(array $data = [], string $mensaje = ""): void
    {
        $response = ["ok" => true];
        if (!empty($data)) $response["data"] = $data;
        if (!empty($mensaje)) $response["mensaje"] = $mensaje;
        
        $this->json($response, 200);
    }

    /**
     * Respuesta de error. HTTP 422 (Unprocessable Entity) por defecto.
     */
    protected function fail(string $error, int $status = 422): void
    {
        $this->json(["ok" => false, "error" => $error], $status);
    }

    /**
     * Envía las cabeceras JSON y detiene el script.
     */
    private function json(array $body, int $status): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($body);
        exit;
    }

    /**
     * Protege el endpoint. Retorna la sesión o muere con 401.
     */
    protected function requireAuth(): array
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!isset($_SESSION['usuario'])) {
            $this->fail("No autorizado. Inicie sesión.", 401); // 401: Unauthorized
        }
        return $_SESSION['usuario'];
    }

    /**
     * Protege el endpoint por rol (Admin, Instructor, Aprendiz).
     */
    protected function requireRol($rolPermitido): void
    {
        $usuario = $this->requireAuth();
        $roles = is_array($rolPermitido) ? $rolPermitido : [$rolPermitido];
        
        if (!in_array($usuario['rol'], $roles, true)) {
            $this->fail("No tiene permisos para esta acción.", 403); // 403: Forbidden
        }
    }
}
```

---

## 4. Cómo escribir un controlador (ejemplo con `AprendizController`)

Los controladores heredan de `ControllerBase`. Se instancian los modelos que necesiten
(ej. `new AprendizModel()`) y delegan la persistencia.

```php
<?php
require_once __DIR__ . '/ControllerBase.php';
require_once __DIR__ . '/../models/AprendizModel.php';

class AprendizController extends ControllerBase
{
    private AprendizModel $aprendizModel;

    public function __construct()
    {
        $this->aprendizModel = new AprendizModel();
    }

    /**
     * GET ?action=aprendices.listar
     * Solo Admin.
     */
    public function listar(): void
    {
        $this->requireRol('Administrador');
        
        try {
            $aprendices = $this->aprendizModel->listar();
            $this->ok(['aprendices' => $aprendices]);
        } catch (Exception $e) {
            $this->fail("Error al listar aprendices: " . $e->getMessage(), 500);
        }
    }

    /**
     * POST ?action=aprendices.desactivar
     * Payload esperado (JSON o x-www-form-urlencoded): idAprendices
     */
    public function desactivar(): void
    {
        $this->requireRol('Administrador');
        
        $idAprendices = $_POST['idAprendices'] ?? null;
        if (!$idAprendices) {
            $this->fail("Falta el ID del aprendiz.");
        }

        try {
            // Regla de negocio: soft delete (estado='inactivo')
            $desactivado = $this->aprendizModel->desactivar((int)$idAprendices);
            if ($desactivado) {
                $this->ok([], "Aprendiz desactivado correctamente.");
            } else {
                $this->fail("No se encontró el aprendiz o ya estaba inactivo.", 404);
            }
        } catch (Exception $e) {
            $this->fail("Error en base de datos.", 500);
        }
    }
}
```

---

## 5. El Job Automático (`jobs/generarInasistencias.php`)

Para la historia de usuario **RF-09** (Limpieza automática de ingresos "a tiempo" / inasistencias),
**NO** se usa un endpoint de la API (`?action=...`) por seguridad y rendimiento.

Se creará una carpeta `jobs/` en la raíz del proyecto. Este archivo:
1. No extiende `ControllerBase`.
2. Se ejecuta directamente por consola: `php jobs/generarInasistencias.php`.
3. Está diseñado para ser programado en el SO (Cron en Linux, Programador de tareas en Windows).
4. No devuelve JSON, sino logs por consola.

---

## 6. Comunicación con otras carpetas

| Carpeta | Relación con `controllers/` |
|---|---|
| `models/` | Los controladores instancian modelos, les pasan parámetros validados y reciben datos (arrays) o excepciones. |
| `views/` | **Ninguna**. Los controladores nunca hacen `include('views/lista.html')`. Las vistas son pedidas por el navegador directamente. |
| `assets/js/` | El JavaScript hace peticiones `fetch()` a `index.php?action=...`. El controlador atiende esa petición y le devuelve JSON. |
| Raíz (`index.php`) | Es el Front Controller. Hace un `switch($_GET['action'])` y llama al método correspondiente del controlador. |

---

## 7. Reglas de esta carpeta

1. **El servidor SOLO devuelve JSON.** Cero HTML de páginas desde PHP. Toda función termina llamando a `$this->ok()` o `$this->fail()`.
2. **Las vistas son HTML estático:** los controladores no las conocen ni las renderizan.
3. **Toda consulta a la BD usa PDO con prepared statements.** Los controladores nunca hacen SQL directo; usan los métodos de `models/`.
4. **Todo JS es módulo ES:** (Aplica al frontend que consume esto).
5. **Todo endpoint sensible empieza con `requireAuth()` / `requireRol()`.** Nunca se debe confiar en que el frontend ocultó el botón.
6. **Soft delete siempre** (`estado='inactivo'`), nunca `DELETE` sobre `usuarios`/`aprendices`. Si un controlador recibe la orden de eliminar un usuario, debe llamar al método `desactivar` del modelo, no hacer `DELETE`.
7. **Los nombres de tabla/columna son EXACTAMENTE los del esquema.** (Aplica al mapeo de datos que recibe/devuelve el controlador).
