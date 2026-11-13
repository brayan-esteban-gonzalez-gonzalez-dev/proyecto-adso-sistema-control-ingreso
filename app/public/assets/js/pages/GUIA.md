# assets/js/pages/ — GUÍA DE ARQUITECTURA (Entry Points)

> Estos archivos son el **"pegamento"** entre el HTML estático (`views/`) y la lógica
> de los módulos compartidos (`assets/js/`). 
> **Cada vista HTML carga UN SOLO archivo de esta carpeta.**

---

## 1. Regla principal de esta carpeta

Cada archivo aquí corresponde a **una sola vista HTML** y se encarga de:
1. Importar los módulos necesarios (`api`, `ui`, `auth`, `forms`).
2. Verificar la sesión y el rol.
3. Ejecutar las llamadas iniciales (ej. pedir la lista de aprendices al cargar).
4. Asociar Event Listeners (clics, submits) a los elementos del DOM.

---

## 2. Archivos que deben vivir aquí

*Debe haber correspondencia 1:1 con `views/`.*

| Vista (`views/*.html`) | Script Entry Point (`pages/*.js`) | Rutas API (`?action=`) que invoca |
|---|---|---|
| `login.html` | `login.js` | `login` |
| `dashboardAdmin.html` | `dashboardAdmin.js` | `logout` |
| `aprendices.html` | `aprendices.js` | `aprendices.listar`, `aprendices.crear`, `aprendices.desactivar`, `fichas.listar` (para el select) |
| `fichas.html` | `fichas.js` | `fichas.*`, `horarios.*` |
| `marcarIngreso.html` | `marcarIngreso.js` | `ingresos.marcar` |
| `historialAsistencia.html`| `historialAsistencia.js` | `ingresos.historial` |
| `misExcusas.html` | `misExcusas.js` | `excusas.misExcusas`, `excusas.crear` |
| `revisarExcusas.html` | `revisarExcusas.js` | `excusas.listarPendientes`, `excusas.revisar` |
| `reportes.html` | `reportes.js` | `reportes.generar`, `reportes.exportarPdf` |

---

## 3. Esqueleto completo: `pages/aprendices.js`

Este es el modelo mental de cómo se programa CADA página de la aplicación.
Observa cómo **no hay funciones globales** y cómo **se usa Event Delegation**
para los botones de editar/desactivar que se crean dinámicamente.

```javascript
// assets/js/pages/aprendices.js

// 1. Importaciones de nuestros módulos (ES Modules)
import { api } from "../api.js";
import { showToast, esc } from "../ui.js";
import { renderTablaAprendices } from "../aprendices.js"; // Lógica visual de tabla
import { conectarFormulario } from "../forms.js";
import { comprobarSesion } from "../auth.js"; // Helper (asumimos que existe y redirige si falla)

// 2. Estado local de la vista
let listaAprendicesCache = [];

// 3. Evento principal: Cuando el HTML termina de cargar
document.addEventListener("DOMContentLoaded", async () => {
    
    // a. Seguridad lado cliente: comprobar que es Admin
    await comprobarSesion('Administrador');

    // b. Carga inicial de datos
    await cargarFichasEnSelect(); // Llenar el <select> del modal
    await cargarAprendices();     // Pintar la tabla

    // c. Conectar Botón para abrir modal "Nuevo"
    const btnNuevo = document.getElementById("btnNuevoAprendiz");
    const modal = document.getElementById("modalAprendiz");
    const form = document.getElementById("formAprendiz");
    const btnCerrarModal = document.getElementById("btnCerrarModal");

    btnNuevo.addEventListener("click", () => {
        form.reset();
        document.getElementById("input_idAprendices").value = ""; // Limpiar ID oculto
        modal.showModal();
    });

    btnCerrarModal.addEventListener("click", () => modal.close());

    // d. Conectar el formulario (usando el helper genérico)
    // Se decide si es crear o actualizar según si hay ID oculto.
    conectarFormulario("formAprendiz", "aprendices.crear", (datosNuevos) => {
        // Callback si todo sale bien
        modal.close();
        cargarAprendices(); // Recargar tabla
    });

    /* 
     * e. EVENT DELEGATION:
     * Como los botones "Desactivar" se dibujan después mediante JS, 
     * no podemos hacer document.getElementById() al inicio.
     * Le ponemos el evento a la <table> y preguntamos si se hizo clic en un botón.
     */
    const tbody = document.getElementById("tbodyAprendices");
    tbody.addEventListener("click", async (e) => {
        // Clic en botón "Desactivar"
        if (e.target.classList.contains("btn-desactivar")) {
            const id = e.target.dataset.id; // lee data-id="..."
            if (confirm("¿Seguro que desea desactivar este aprendiz?")) {
                desactivarAprendiz(id);
            }
        }
        
        // Clic en botón "Editar"
        if (e.target.classList.contains("btn-editar")) {
            const id = parseInt(e.target.dataset.id);
            const aprendiz = listaAprendicesCache.find(a => a.idAprendices === id);
            if (aprendiz) {
                // Llenar formulario
                document.getElementById("input_idAprendices").value = aprendiz.idAprendices;
                document.getElementById("input_numDocumento").value = aprendiz.numDocumento;
                document.getElementById("input_nombre").value = aprendiz.nombre;
                // ... y así con el resto
                
                modal.showModal();
            }
        }
    });

    // f. Lógica de Logout
    document.getElementById("btnLogout")?.addEventListener("click", async () => {
        await api('logout', { method: 'POST' });
        window.location.href = "login.html";
    });
});

// 4. Funciones específicas de esta página
async function cargarAprendices() {
    const tbody = document.getElementById("tbodyAprendices");
    tbody.innerHTML = '<tr><td colspan="6" class="text-center">Cargando...</td></tr>';
    
    try {
        const res = await api('aprendices.listar');
        listaAprendicesCache = res.data.aprendices; // Guardar en RAM para editar luego
        tbody.innerHTML = renderTablaAprendices(listaAprendicesCache);
    } catch (error) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-danger">Error: ${esc(error.message)}</td></tr>`;
    }
}

async function desactivarAprendiz(idAprendices) {
    try {
        await api('aprendices.desactivar', {
            method: 'POST',
            body: { idAprendices: idAprendices }
        });
        showToast("Aprendiz desactivado");
        cargarAprendices(); // Recargar tabla
    } catch (error) {
        showToast(error.message, "danger");
    }
}

async function cargarFichasEnSelect() {
    try {
        const res = await api('fichas.listar');
        const select = document.getElementById("input_fk_fichas_idFicha");
        // Dejar solo la opción por defecto ("Seleccione...")
        select.innerHTML = '<option value="">Seleccione una ficha...</option>';
        
        res.data.fichas.forEach(f => {
            select.innerHTML += `<option value="${f.idFicha}">${esc(f.codigoFicha)} - ${esc(f.nombrePrograma)}</option>`;
        });
    } catch (error) {
        console.error("No se pudieron cargar las fichas", error);
    }
}
```

---

## 4. Comunicación con otras carpetas

| Carpeta | Relación con `assets/js/pages/` |
|---|---|
| `views/` | Las vistas son dueñas de estos archivos (los incluyen con `<script type="module" src="...">`). El JS manipula el DOM de esa vista en específico. |
| `assets/js/` | Importa los módulos de apoyo (`api.js`, `ui.js`, `forms.js`) para evitar repetir código boilerplate. |

---

## 5. Reglas de esta carpeta

1. **El servidor SOLO devuelve JSON.** Toda llamada a `api()` asume que la respuesta vendrá formateada como `{ "ok": true|false, ... }`.
2. **Las vistas son HTML estático:** Por eso la primera carga de datos siempre ocurre usando `DOMContentLoaded` (el JS tiene que ir y pedir los datos a la API cuando el cascarón de la página está listo).
3. **Todo JS es módulo ES:** Usa `import` en la cabecera. Cero uso de `onclick` inline; todo se maneja con `addEventListener`.
4. **Soft delete siempre.** Fíjate cómo la función se llama `desactivarAprendiz`, y llama a la ruta `aprendices.desactivar` (no eliminar).
5. **Event Delegation:** Crucial para elementos dinámicos. En lugar de poner un EventListener a los 50 botones de editar (que además aún no existen cuando arranca la página), se le pone **un solo listener** al contenedor padre (`<tbody>`) y se usa `e.target.classList.contains(...)`.
