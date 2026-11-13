# views/ — GUÍA DE ARQUITECTURA (HTML ESTÁTICO, SIN PHP)

> En esta arquitectura las vistas son **HTML estático puro**: NO contienen PHP ni consultan
> la base de datos. Son la "carcasa" (esqueleto) visual, y el JavaScript (`assets/js/`) 
> es el encargado de darles vida obteniendo los datos desde la API.

---

## 1. Regla principal de esta carpeta

**CERO código PHP en las vistas.** Son archivos `.html` que el servidor web (Apache)
entrega directamente al navegador sin procesar.

Lo que **SÍ** llevan:
- El HTML semántico de la pantalla (formularios, tablas, navs, modales).
- Enlaces a la hoja de estilos compartida (`../assets/css/estilos.css`).
- **UN SOLO `<script type="module">`** al final del body: el script "entry point" específico de esa página.

Lo que **NO** llevan (nunca):
- Etiquetas `<?php ... ?>`.
- Ecos de variables (ej. `<?= $_SESSION['nombre'] ?>`).
- Consultas SQL o lógicas de negocio.
- Inclusión de partes repetitivas vía PHP (ej. `include('header.php')`). (La navegación se puede cargar por JS o simplemente repetirse si son pocas vistas).

---

## 2. Archivos que deben vivir aquí

Cada pantalla principal del sistema tiene su propio `.html`.

| Archivo | Funcionalidad (RF) | Script de página (Módulo ES) que lo controla |
|---|---|---|
| `login.html` | RF-01 (Login) | `<script type="module" src="../assets/js/pages/login.js"></script>` |
| `dashboardAdmin.html` | RF-02 (Panel de control) | `pages/dashboardAdmin.js` |
| `dashboardAprendiz.html` | Panel específico aprendiz | `pages/dashboardAprendiz.js` |
| `aprendices.html` | RF-13 (CRUD Aprendices) | `pages/aprendices.js` |
| `fichas.html` | RF-07 (CRUD Fichas/Horarios) | `pages/fichas.js` |
| `marcarIngreso.html` | RF-05 (Estación RFID) | `pages/marcarIngreso.js` |
| `historialAsistencia.html` | RF-03 (Historial aprendiz) | `pages/historialAsistencia.js` |
| `misExcusas.html` | RF-04 (Subir excusa) | `pages/misExcusas.js` |
| `revisarExcusas.html` | RF-12 (Instructor revisa) | `pages/revisarExcusas.js` |
| `reportes.html` | RF-10, RF-11 (Reportes) | `pages/reportes.js` |

La navegación entre vistas se hace con etiquetas `<a>` tradicionales.
```html
<a href="aprendices.html">Gestión de Aprendices</a>
```

---

## 3. Ejemplo completo: views/aprendices.html

Nota cómo la tabla y el formulario están vacíos o tienen IDs claros. No hay datos
quemados; el script `aprendices.js` se encargará de hacer `fetch()` a la API y llenar
el `<tbody>` y escuchar el evento submit del `<form>`.

```html
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Aprendices | SENA</title>
    <!-- CSS global -->
    <link rel="stylesheet" href="../assets/css/estilos.css">
</head>
<body>
    <header class="app-header">
        <h1>Sistema de Control de Ingreso</h1>
        <nav class="app-nav">
            <a href="dashboardAdmin.html">Inicio</a>
            <a href="aprendices.html" class="active">Aprendices</a>
            <a href="fichas.html">Fichas</a>
            <button id="btnLogout" class="btn-link">Cerrar Sesión</button>
        </nav>
    </header>

    <main class="container">
        <div class="header-actions">
            <h2>Aprendices Inscritos</h2>
            <button id="btnNuevoAprendiz" class="btn btn-primary">Nuevo Aprendiz</button>
        </div>

        <!-- Tabla vacía, JS inyectará los <tr> aquí -->
        <table class="table" id="tablaAprendices">
            <thead>
                <tr>
                    <th>Documento</th>
                    <th>Nombre</th>
                    <th>Ficha</th>
                    <th>Código RFID</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody id="tbodyAprendices">
                <tr><td colspan="6" class="text-center">Cargando...</td></tr>
            </tbody>
        </table>
    </main>

    <!-- Modal (oculto por defecto) para crear/editar aprendiz -->
    <dialog id="modalAprendiz" class="modal">
        <form id="formAprendiz" method="dialog">
            <h3>Datos del Aprendiz</h3>
            <!-- Campo oculto para saber si editamos o creamos -->
            <input type="hidden" name="idAprendices" id="input_idAprendices">
            
            <div class="form-group">
                <label for="input_numDocumento">Número Documento:</label>
                <input type="text" name="numDocumento" id="input_numDocumento" required>
            </div>
            
            <div class="form-group">
                <label for="input_nombre">Nombre:</label>
                <input type="text" name="nombre" id="input_nombre" required>
            </div>

            <div class="form-group">
                <label for="input_fk_fichas_idFicha">Ficha:</label>
                <!-- JS llenará los <option> haciendo fetch() a fichas.listar -->
                <select name="fk_fichas_idFicha" id="input_fk_fichas_idFicha" required>
                    <option value="">Seleccione una ficha...</option>
                </select>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary" id="btnCerrarModal">Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </dialog>

    <!-- ÚNICO script de la página (Módulo ES) -->
    <script type="module" src="../assets/js/pages/aprendices.js"></script>
</body>
</html>
```

---

## 4. Comunicación con otras carpetas

| Carpeta | Relación con `views/` |
|---|---|
| `assets/js/pages/` | Cada vista incluye su propio script de página (`type="module"`). Este script "gobierna" la vista (manipula su DOM). |
| `assets/css/` | Las vistas enlazan la hoja de estilos común. |
| `controllers/` | **Ninguna relación directa**. La vista carga en el navegador, y luego el JS de la vista pide datos al controlador (API). |
| `models/` | **Ninguna.** |

---

## 5. Reglas de esta carpeta

1. **El servidor SOLO devuelve JSON.** En consecuencia, las vistas nunca son renderizadas o modificadas por PHP antes de llegar al navegador.
2. **Las vistas son HTML estático:** cero `<?php`, cero consultas SQL. El documento es un cascarón vacío.
3. **Toda consulta a la BD usa PDO.** (Las vistas delegan esto al backend).
4. **Todo JS es módulo ES:** El script que se inyecta debe tener `type="module"`. **Nada de funciones globales ni `onclick="funcion()"` inline.** Todo se maneja con `document.getElementById('...').addEventListener(...)`.
5. **Todo endpoint sensible empieza con `requireAuth()`.** (La vista no hace seguridad en el backend, pero su JS debe redireccionar a `login.html` si la API devuelve HTTP 401).
6. **Soft delete siempre.** (La vista mostrará botones de "Eliminar" o "Desactivar", pero mandan peticiones a desactivar, no a borrar de verdad).
7. **Nombres de campos (name="..."):** Es altamente recomendado que los atributos `name` de los inputs coincidan EXACTAMENTE con las columnas de la DB de la sección 4 (ej. `fk_fichas_idFicha`) para facilitar la serialización en JS y la validación en PHP.
