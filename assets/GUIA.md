# assets/ — GUÍA DE ARQUITECTURA (MÓDULOS ES)

> Aquí vive TODO el frontend dinámico. El JavaScript se escribe obligatoriamente con 
> **Módulos ES** (`<script type="module">` + `import`/`export`). 
> Ninguna vista HTML (`views/`) habla con la API ni tiene PHP; lo hacen los scripts de esta carpeta.

---

## 1. Qué vive aquí y su estructura

```
assets/
├── css/
│   ├── estilos.css              ← diseño visual de toda la app (ver css/GUIA.md)
│   └── GUIA.md
├── js/
│   ├── api.js                   ← exporta api(): el único cliente del servidor (fetch)
│   ├── ui.js                    ← exporta showToast(), showModal(): helpers de interfaz
│   ├── auth.js                  ← exporta requireAuth(): maneja la sesión en el lado cliente
│   ├── forms.js                 ← exporta serializeForm(): utilidades genéricas
│   ├── aprendices.js            ← lógica de negocio/validación para aprendices
│   ├── excusas.js               ← lógica de negocio/validación para excusas
│   ├── ingresos.js              ← lógica para RFID y asistencia
│   ├── reportes.js              ← lógica de gráficos o exportación de reportes
│   └── pages/                   ← UN módulo de entrada por página (es lo que carga el HTML)
│       ├── login.js
│       ├── aprendices.js
│       ├── marcarIngreso.js
│       └── ...
│   └── GUIA.md
└── GUIA.md                      ← este archivo
```

### 1.1 La regla de los Módulos ES (`import`/`export`)

| Problema con JS tradicional | Solución con Módulos ES |
|---|---|
| **Orden de carga:** Había que poner `<script src="api.js">` y `<script src="ui.js">` SIEMPRE en orden o fallaba. | **Resolución automática:** el navegador resuelve las dependencias leyendo los `import`. |
| **Colisiones:** Todas las funciones eran globales (vivían en `window`). | **Scope aislado:** cada archivo tiene su propio contexto. Nada es global salvo lo que `export`es explícitamente. |
| **Páginas pesadas:** El HTML cargaba 5-6 `<script>`. | **Un solo entry point:** el HTML carga **UN SOLO** `<script type="module" src="pages/xyz.js">`. |

> [!WARNING]
> Consecuencia fundamental: al no haber funciones globales, **los `onclick="miFuncion()"` escritos en el HTML dejan de funcionar**. 
> Todos los eventos deben conectarse desde el JS usando `document.getElementById('id').addEventListener(...)`.

---

## 2. Descripción de subcarpetas

### 2.1 `assets/css/`
Contiene las hojas de estilo de la aplicación. Lo ideal es tener un solo archivo `estilos.css` o, si se usan preprocesadores o librerías, empaquetar todo allí. 
**Ver `assets/css/GUIA.md` para reglas de diseño.**

### 2.2 `assets/js/`
Contiene los "módulos de librería" de la aplicación. Estos archivos NO se ejecutan directamente por el HTML; solo `export`an funciones para que las consuman los módulos de `pages/`. 
**Ver `assets/js/GUIA.md` para ejemplos del código base.**

### 2.3 `assets/js/pages/`
Contiene los "puntos de entrada" (Entry Points). Cada vista en `views/` incluye un solo archivo de esta carpeta. Estos archivos importan lo necesario de `js/` y "atan" la lógica a los botones y tablas del HTML.
**Ver `assets/js/pages/GUIA.md` para ejemplos del ruteo del DOM.**

---

## 3. Flujo de datos completo (Ejemplo: crear un aprendiz)

Para entender cómo encaja `assets/` en la arquitectura, veamos el flujo:

1. **(Capa Vistas)** El usuario abre `views/aprendices.html` en el navegador.
2. **(Capa Vistas)** Al final del HTML carga `<script type="module" src="../assets/js/pages/aprendices.js"></script>`.
3. **(Capa Pages)** `pages/aprendices.js` arranca: importa `api.js` y vincula el evento `submit` al formulario `formAprendiz`.
4. **(Interacción)** El usuario llena el formulario y da clic en Guardar.
5. **(Capa Pages)** Se dispara el evento `submit` en `pages/aprendices.js`. Éste usa `serializeForm()` para obtener el JSON del formulario.
6. **(Capa JS/API)** Se llama a `api('aprendices.crear', { method: 'POST', body: JSON.stringify(datos) })`.
7. **(Capa Controladores)** La petición HTTP llega a `index.php?action=aprendices.crear`, que dispara `AprendizController->crear()`.
8. **(Capa Modelos)** El controlador llama a `AprendizModel->crear()` que ejecuta el `INSERT` vía PDO.
9. **(Capa Controladores)** El controlador responde `{"ok": true}` (JSON).
10. **(Capa Pages)** El `.then()` de `api()` recibe la respuesta, cierra el modal, y llama a `ui.showToast('Éxito')`.

---

## 4. Comunicación con otras carpetas

| Carpeta | Relación con `assets/` |
|---|---|
| `views/` | Las vistas (HTML) son dueñas de la estructura; ellas deciden qué archivo de `assets/js/pages/` cargar y qué archivo de `css/` enlazar. |
| `controllers/` | El JS (vía `fetch` en `api.js`) habla con los controladores consumiendo las URL `?action=...` y esperando JSON. |
| `models/` | **Ninguna.** El frontend (JavaScript) jamás habla directo con SQL ni con los modelos PHP. |

---

## 5. Reglas de esta carpeta

1. **El servidor SOLO devuelve JSON.** (El JS espera consumir JSON, si un endpoint devuelve un error 500 con una pila de HTML de PHP, el `fetch` del JS fallará miserablemente).
2. **Las vistas son HTML estático:** Por eso en `assets/js/` es normal ver funciones como `renderizarTabla()` que construyen los `<tr>` dinámicamente.
3. **Toda consulta a la BD usa PDO.** (Backend).
4. **Todo JS es módulo ES:** Obligatorio usar `export`/`import`. Obligatorio `<script type="module">`. Cero `onclick` inline.
5. **Todo endpoint sensible empieza con `requireAuth()`.** Además, el frontend (vía `auth.js`) revisará los 401 que devuelva la API y forzará la redirección a `login.html`.
6. **Soft delete siempre.** El frontend debe tener cuidado de no mostrar registros inactivos (a menos que el administrador pida un reporte histórico explícito).
7. **Los nombres de tabla/columna son EXACTAMENTE los del esquema.** Por convención, cuando el JS recibe datos de la API (ej. `{ "idAprendices": 1, "codigoRfid": "ABC" }`), usará exactamente las mismas llaves (camelCase) que vienen del JSON.
