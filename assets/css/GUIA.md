# assets/css/ — GUÍA DE ESTILOS Y DISEÑO

> Aquí vive la hoja de estilos de la aplicación (`estilos.css`). El objetivo
> es mantener un diseño consistente, responsivo y basado en variables CSS.

---

## 1. Regla principal de esta carpeta

**Un solo archivo o pocos archivos centralizados.** Para un proyecto de este tamaño,
basta con un `estilos.css` unificado.

- No se permiten estilos inline en el HTML (ej. `<div style="color: red;">`).
- Todo componente reutilizable (botones, tablas, tarjetas, modales) debe tener
  su clase en este archivo CSS.

---

## 2. Variables CSS (Theming)

Todas las medidas y colores base deben definirse en `:root` para asegurar
consistencia y facilitar un futuro "Modo Oscuro" o cambio de paleta institucional (SENA).

```css
:root {
    /* Paleta Institucional (Verde SENA) */
    --color-sena-primario: #39A900;
    --color-sena-secundario: #007832;
    --color-sena-oscuro: #003214;
    
    /* Estados Semánticos */
    --color-exito: #28a745;
    --color-peligro: #dc3545;
    --color-alerta: #ffc107;
    --color-info: #17a2b8;

    /* Fondos y Textos */
    --fondo-app: #f4f6f9;
    --fondo-tarjeta: #ffffff;
    --texto-principal: #333333;
    --texto-secundario: #6c757d;

    /* Dimensiones y espaciado */
    --borde-radio: 6px;
    --espaciado-sm: 8px;
    --espaciado-md: 16px;
    --espaciado-lg: 24px;
}
```

---

## 3. Clases Reutilizables Comunes

El CSS debe proveer clases "utilitarias" y "componentes" que las vistas HTML puedan usar.

### Botones

```css
.btn {
    display: inline-block;
    padding: 0.5rem 1rem;
    border-radius: var(--borde-radio);
    border: none;
    cursor: pointer;
    font-weight: bold;
    text-align: center;
    transition: background-color 0.2s;
}

.btn-primary {
    background-color: var(--color-sena-primario);
    color: white;
}
.btn-primary:hover {
    background-color: var(--color-sena-secundario);
}

.btn-danger {
    background-color: var(--color-peligro);
    color: white;
}
```

### Tablas (Para el CRUD de aprendices, fichas, etc.)

```css
.table {
    width: 100%;
    border-collapse: collapse;
    background: var(--fondo-tarjeta);
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.table th, .table td {
    padding: var(--espaciado-md);
    text-align: left;
    border-bottom: 1px solid #dee2e6;
}

.table th {
    background-color: var(--fondo-app);
    color: var(--texto-secundario);
    font-weight: 600;
}
```

### Modales (Dialogs)

Aprovechando el elemento nativo `<dialog>` de HTML5.

```css
.modal {
    border: none;
    border-radius: var(--borde-radio);
    padding: var(--espaciado-lg);
    box-shadow: 0 5px 15px rgba(0,0,0,0.3);
    max-width: 500px;
    width: 100%;
}
.modal::backdrop {
    background: rgba(0, 0, 0, 0.5);
}
```

### Estados y Toasts (Para `ui.js`)

```css
.badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 0.8em;
    font-weight: bold;
}
.badge-retardo { background-color: var(--color-alerta); color: #000; }
.badge-atiempo { background-color: var(--color-exito); color: #fff; }

.toast-container {
    position: fixed;
    bottom: 20px;
    right: 20px;
    z-index: 9999;
}
.toast {
    padding: 15px 25px;
    background: #333;
    color: #fff;
    border-radius: var(--borde-radio);
    margin-top: 10px;
    opacity: 0;
    transition: opacity 0.3s;
}
.toast.show { opacity: 1; }
```

---

## 4. Comunicación con otras carpetas

| Carpeta | Relación con `assets/css/` |
|---|---|
| `views/` | Los archivos HTML importan este CSS `<link rel="stylesheet" href="../assets/css/estilos.css">` y usan sus clases. |
| `assets/js/` | Los módulos JS (como `ui.js`) inyectan clases CSS predefinidas (ej. `toast.classList.add('show')`) para lograr animaciones y comportamientos visuales. |

---

## 5. Reglas aplicables a CSS

1. **El servidor SOLO devuelve JSON.** (El CSS se carga de forma estática por el navegador, no hay PHP generando CSS dinámico).
2. **Las vistas son HTML estático:** Por ende, todo el peso visual recae en que el CSS esté bien estructurado para elementos estándar y vacíos que luego el JS llena.
3. **No usar `!important` a menos que sea estrictamente necesario.** Respetar la especificidad de CSS.
4. **Diseño Responsivo (Mobile First).** Usar `@media (min-width: 768px)` para adaptar los `grid` y `flexbox` de celular a escritorio, vital para el uso del panel del aprendiz en móviles.
