# assets/js/ — GUÍA DE ARQUITECTURA (Librerías JS)

> Aquí viven los módulos base (helpers y clientes de API).
> Estos archivos **NO** se importan directamente desde el HTML, sino que son
> importados por los "Entry Points" que viven en `pages/`.

---

## 1. Regla principal de esta carpeta

**Todo archivo es un Módulo ES (`export`).** No hay variables globales. 
Si una función va a ser usada en otro archivo, debe llevar `export`. 

---

## 2. Archivos que deben vivir aquí

| Archivo | Qué hace |
|---|---|
| `api.js` | Exporta `api()`: un wrapper de `fetch` para centralizar peticiones. |
| `ui.js` | Exporta `showToast()`, `showModal()`, `esc()`. |
| `forms.js` | Exporta utilidades para manejar formularios AJAX (`conectarFormulario()`, `serializeForm()`). |
| `auth.js` | Exporta `comprobarSesion()`, `logout()`. |
| `aprendices.js` | Lógica compartida de aprendices (ej. `renderTablaAprendices()`). |
| `fichas.js`, `ingresos.js`, etc. | Lógica de presentación compartida de otras entidades. |

---

## 3. Ejemplos de Implementación

### 3.1 `api.js` — El cliente central

Todo el frontend debe usar esta función para hablar con la API (PHP).
Ventaja: si el usuario pierde la sesión (API devuelve HTTP 401), se intercepta
aquí y se redirige automáticamente al login.

```javascript
// assets/js/api.js

// Construimos la URL base apuntando a index.php en la raíz
const API_URL = new URL("../../index.php", import.meta.url).href;

/**
 * Función central para hablar con el backend PHP.
 * @param {string} action El endpoint, ej: "aprendices.listar"
 * @param {object} options Opciones de fetch (method, body, etc.)
 */
export async function api(action, options = {}) {
    // Forzamos cabeceras JSON
    const headers = {
        "X-Requested-With": "XMLHttpRequest", // Para que PHP sepa que es AJAX
        ...(options.headers || {})
    };

    if (options.body && typeof options.body !== 'string' && !(options.body instanceof FormData)) {
        options.body = JSON.stringify(options.body);
        headers["Content-Type"] = "application/json";
    }

    try {
        const response = await fetch(`${API_URL}?action=${action}`, {
            ...options,
            headers
        });

        // Manejo automático de sesión expirada
        if (response.status === 401) {
            window.location.href = new URL("../../views/login.html", import.meta.url).href;
            throw new Error("Sesión expirada");
        }

        const data = await response.json();
        
        if (!response.ok || !data.ok) {
            throw new Error(data.error || "Error en la petición");
        }
        
        return data; // Devuelve el JSON parseado ( {ok:true, data: {...}} )
    } catch (error) {
        console.error("API Error:", error);
        throw error; // Lo relanza para que la vista lo maneje (ej. mostrando un Toast)
    }
}
```

### 3.2 `ui.js` — Helpers de Interfaz

Centraliza cómo se muestran mensajes de error y cómo se escapa HTML para evitar XSS.

```javascript
// assets/js/ui.js

/**
 * Escapa HTML para prevenir ataques XSS (Cross Site Scripting).
 * ÚSALO SIEMPRE que vayas a pintar datos del servidor en el DOM.
 */
export function esc(str) {
    if (str === null || str === undefined) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

/**
 * Muestra una notificación temporal.
 */
export function showToast(mensaje, tipo = 'success') {
    // tipo: 'success', 'danger', 'warning', 'info'
    let container = document.querySelector('.toast-container');
    if (!container) {
        container = document.createElement('div');
        container.className = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${tipo} show`;
    toast.textContent = mensaje;
    
    container.appendChild(toast);

    setTimeout(() => {
        toast.classList.remove('show');
        setTimeout(() => toast.remove(), 300); // Dar tiempo a la transición CSS
    }, 3000);
}
```

### 3.3 `forms.js` — Automatización de Formularios AJAX

```javascript
// assets/js/forms.js
import { api } from "./api.js";
import { showToast } from "./ui.js";

/**
 * Conecta un formulario HTML al backend.
 * Previene el submit tradicional y lo envía por fetch.
 */
export function conectarFormulario(formId, actionUrl, onSuccess) {
    const form = document.getElementById(formId);
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault(); // Evita que la página recargue

        const formData = new FormData(form);
        const dataObj = Object.fromEntries(formData.entries());

        const btn = form.querySelector('button[type="submit"]');
        if (btn) btn.disabled = true;

        try {
            const respuesta = await api(actionUrl, {
                method: 'POST',
                body: dataObj
            });
            showToast(respuesta.mensaje || "Operación exitosa", "success");
            if (onSuccess) onSuccess(respuesta.data);
            form.reset();
        } catch (error) {
            showToast(error.message, "danger");
        } finally {
            if (btn) btn.disabled = false;
        }
    });
}
```

### 3.4 `aprendices.js` — Entidades

```javascript
// assets/js/aprendices.js
import { esc } from "./ui.js";

/**
 * Construye el HTML de la tabla de aprendices.
 */
export function renderTablaAprendices(aprendicesArray) {
    if (!aprendicesArray || aprendicesArray.length === 0) {
        return `<tr><td colspan="6" class="text-center">No hay aprendices registrados.</td></tr>`;
    }

    return aprendicesArray.map(a => `
        <tr>
            <td>${esc(a.numDocumento)}</td>
            <td>${esc(a.nombre)} ${esc(a.apellido)}</td>
            <td>${esc(a.codigoFicha)}</td>
            <td>${esc(a.codigoRfid || 'Sin asignar')}</td>
            <td><span class="badge ${a.estado === 'activo' ? 'badge-atiempo' : 'badge-retardo'}">${esc(a.estado)}</span></td>
            <td>
                <button class="btn btn-sm btn-info btn-editar" data-id="${a.idAprendices}">Editar</button>
                <button class="btn btn-sm btn-danger btn-desactivar" data-id="${a.idAprendices}">Desactivar</button>
            </td>
        </tr>
    `).join('');
}
```

---

## 4. Comunicación con otras carpetas

| Carpeta | Relación con `assets/js/` |
|---|---|
| `assets/js/pages/` | Los scripts de `pages/` importan todas estas funciones para orquestar la vista. |
| `controllers/` | A través de `api.js`, el frontend se comunica con los endpoints del servidor definidos en los controladores. |

---

## 5. Reglas de esta carpeta

1. **El servidor SOLO devuelve JSON.** (El módulo `api.js` está diseñado explícitamente para parsear `.json()`).
2. **Las vistas son HTML estático:** Por ende, funciones como `renderTablaAprendices` son las responsables de construir el HTML dinámico (DOM).
3. **Todo JS es módulo ES:** Obligatorio usar `export`.
4. **Protección XSS:** NUNCA inyectes variables en el DOM (`innerHTML`) sin pasarlas primero por la función `esc(variable)` definida en `ui.js`.
5. **Nombres de campos:** Al hacer `Object.fromEntries(new FormData(form))` dependemos de que los atributos `name` del HTML coincidan con lo que espera el controlador.
