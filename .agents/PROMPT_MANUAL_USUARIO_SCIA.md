# PROMPT PARA LA IA — GENERAR EL "DOCUMENTO 1: MANUAL DE USUARIO POR PERFIL" (SCIA)

> **Instrucciones para el estudiante (borra este bloque antes de enviar el prompt):**
> 1. Completa los datos de la sección "DATOS DEL ESTUDIANTE" (abajo).
> 2. Adjunta a la IA el ZIP del proyecto junto con este prompt (nada más).
> 3. Pega TODO lo que sigue desde la línea "===== INICIO DEL PROMPT =====" hasta el final.

---

===== INICIO DEL PROMPT =====

## DATOS DEL ESTUDIANTE (completados por el estudiante; si algún campo está vacío NO lo inventes, omítelo del documento)

- NOMBRE_DE_LOS_AUTORES: [escribe aquí]
- FICHA_Y_PROGRAMA: [escribe aquí]
- NOMBRE_DEL_INSTRUCTOR: [escribe aquí]
- FECHA_DE_ENTREGA: [escribe aquí]
- DIRECCION_WEB_DEL_SISTEMA (la URL con la que se abre el sistema funcionando): [escribe aquí]

---

## 0. TU ROL Y TU OBJETIVO

Eres un redactor técnico senior y analista de software con experiencia en manuales de usuario para personas sin conocimientos técnicos. Tienes dos insumos: (1) este prompt y (2) el ZIP con el código fuente completo de un sistema web llamado **SCIA — Sistema de Control de Ingreso de Aprendices** (proyecto de un programa ADSO del SENA, hecho en PHP con arquitectura MVC propia).

Tu objetivo: producir **un documento Word (.docx) llamado "Documento 1: Manual de usuario por perfil"** que cumpla **al 100 %** los requisitos de la sección 1. Los autores NO escribieron este manual: lo harás tú leyendo el código, y ellos solo pegarán las capturas de pantalla en los espacios que tú dejes.

**El documento lo evaluará un instructor, tal cual lo entregues, comparándolo contra el software real.** Un solo paso que no coincida con lo que realmente ocurre en el sistema (un botón con otro nombre, un menú que no existe, un campo que falta, una función atribuida al perfil equivocado) invalida el trabajo. Por eso: **exactitud absoluta > rapidez > extensión**. No tienes límite de tiempo: dedícate a leer y verificar.

Trabaja en **español**. No modifiques ningún archivo del proyecto (solo lectura). No ejecutes migraciones contra bases de datos reales.

---

## 1. EL REQUISITO OFICIAL (copiado del enunciado del taller; es la rúbrica con la que te evaluarán)

> **Entregables del taller: manual de usuario y pruebas unitarias y de integración.**
> Los aprendices trabajan sobre el software que ellos desarrollaron, durante esta jornada, y entregan dos documentos.
> **Entrega: dos documentos**
> 1. **Documento 1: Manual de usuario por perfil.** No se sustenta y no va en el video.
> 2. Documento 2: Documento de pruebas unitarias y de integración. *(NO es parte de tu tarea; ignóralo.)*
>
> **Documento 1: Manual de usuario por perfil.** Este documento no se sustenta. Se evalúa únicamente por su contenido escrito.

| # | Qué deben hacer los aprendices | Evidencia (en el documento) |
|---|---|---|
| 1 | **Identificar los perfiles (roles) del software.** | Lista de **todos** los perfiles que existen en el software (por ejemplo, administrador, usuario, etc.), con una descripción de qué puede hacer cada uno. |
| 2 | **Elaborar un manual de usuario para cada perfil.** | Un manual **independiente por perfil**, de modo que cada usuario encuentre **solo lo que le corresponde**. Cada manual incluye **cómo ingresar al sistema** y **cómo navegar por él**. |
| 3 | **Explicar cada funcionalidad del perfil, paso a paso.** | **Todas** las funcionalidades disponibles para ese perfil, cada una explicada en **pasos numerados**, con el **pantallazo visible de cada paso, incrustado en el documento en el lugar donde se explica**. |
| 4 | **Redactar para cualquier usuario.** | Lenguaje claro y sencillo, **sin términos técnicos**, de manera que cualquier persona que use ese perfil pueda seguir el manual **sin ayuda**. |

**Cómo se traduce cada criterio en tu trabajo (obligatorio):**

- **Criterio 1** → una sección "Perfiles del sistema" con una tabla: nombre del perfil, descripción en lenguaje simple, y qué puede hacer. Deben aparecer **exactamente los perfiles que existan en el código** (ver sección 4, fase 1; sospecha inicial: 3).
- **Criterio 2** → **un manual por perfil, completamente autosuficiente**: cada manual empieza en página nueva, tiene su propia mini-introducción, su propio "Cómo ingresar", su propio "Cómo moverse por el sistema" y solo las funciones de ese perfil. **Prohibido** escribir "vea el manual del Administrador" o remitir a otro manual. Lo que sea común (iniciar sesión, cerrar sesión, menú) se **repite completo** en cada manual.
- **Criterio 3** → cada funcionalidad = título + para qué sirve + pasos numerados; **cada paso lleva su propio espacio para el pantallazo, justo debajo del texto del paso** (especificación en sección 6). "Todas las funcionalidades" significa que si el perfil puede hacerlo desde la interfaz, está en el manual.
- **Criterio 4** → reglas de lenguaje de la sección 7.

---

## 2. INSUMOS Y QUÉ CONFIAR

1. Descomprime el ZIP en una carpeta nueva y vacía. Trabaja siempre sobre una copia; no ejecutes código del proyecto desde dentro de esa carpeta sin necesidad.
2. **La única fuente de verdad es el CÓDIGO ACTUAL**: `index.php`, `helpers/`, `controllers/`, `models/`, `views/`, `assets/js/`, `assets/css/`, `config/`, `migrations/db_ingreso_aprendices_27_08.sql` (esquema y datos iniciales).
3. **Los documentos del repositorio NO son confiables** y pueden contradecir al código: `README.md` (viejo; menciona cosas que ya cambiaron), `.agents/*.txt|md` (`ESTADO_PROYECTO`, `PENDIENTE_IMPLEMENTAR`, `Plan`, `reporssecuriti`, etc.) y **todos los `GUIA.md`** (describen una arquitectura distinta a la real: API solo-JSON, rutas `aprendices.listar`, etc.). Puedes leerlos como pistas, **pero nunca como prueba de que algo existe o funciona**. Si algo solo aparece en esos documentos y no en el código/vistas, **no va en el manual**.
4. **Ignora** `librerias/` (dependencias de terceros), `.git/`, `composer.*`, `assets/uploads/`.
5. Si tu entorno lo permite, levanta el sistema (PHP ≥ 8.2 + MySQL/MariaDB, la base `db_ingreso_aprendices` se crea sola desde `config/Database.php`) para contrastar lo que leíste con la ejecución real. Si no puedes, el análisis estático basta, pero entonces **debes rastrear cada flujo de principio a fin** (ver 4.3).

---

## 3. REGLAS DE ORO (si rompes una, el documento no sirve)

1. **No inventes nada.** Cada botón, menú, campo, mensaje y resultado que escribas debe existir en el código. Si no lo puedes demostrar con un archivo y una línea, no lo escribas.
2. **Los textos de la pantalla se copian LITERALMENTE**: nombres del menú, títulos de página, etiquetas de campos, textos de botones, opciones de listas desplegables, encabezados de tablas y mensajes de aviso (éxito/error). Respeta mayúsculas, tildes, signos y puntuación exactos. Escríbelos **en negrita** cuando son elementos de la pantalla, y los mensajes entre comillas «así».
   - Fuentes de esos textos: `views/**` (etiquetas, botones, títulos), `views/layouts/header.php` (menú lateral), `$pageTitle` en cada controlador (título de la página), y `Auth::setFlash('success'|'error'|'info'|'warning', '...')` en controladores (mensajes). Extráelos con búsqueda (grep) y úsalos tal cual. **Revisa también los textos que el JavaScript escribe en pantalla** (`assets/js/*.js` y bloques `<script>` en las vistas): alertas, confirmaciones (`confirm(...)`), mensajes dinámicos, contadores.
3. **Un perfil solo ve lo que su perfil ve.** Una función pertenece a un perfil únicamente si ese perfil **puede llegar a ella haciendo clic** (menú lateral, botones, enlaces, tarjetas del Dashboard, acciones de tabla). Lo que solo se alcanza escribiendo una URL a mano **no se documenta** (anótalo en el reporte de hallazgos, sección 9).
4. **Trampa conocida de nombres engañosos en `helpers/Auth.php`:**
   - `Auth::requireAdmin()` **NO exige ser administrador**: deja pasar a **Administrador e Instructor** (usa `isAdminOrInstructor()`).
   - `Auth::requireAdminOnly()` es el que exige **solo Administrador** (rol 1).
   - `Auth::requireAdminOrInstructor()` deja pasar a Administrador e Instructor.
   - Roles en la tabla `rol`: **1 = Administrador, 2 = Instructor, 3 = Aprendiz** (verifícalo en `migrations/db_ingreso_aprendices_27_08.sql`; el aprendiz es un `usuario` con `Rol_id = 3`).
   - Además hay reglas **dentro** de los métodos (por ejemplo, "un instructor solo edita sus propias sesiones o las de la ficha que lidera", filtros por ficha asignada al instructor, "solo el administrador ve todas las fichas"). **Léelas completas**: un método con `requireAdmin()` puede tener un `if (Auth::isInstructor()) {...}` que cambia lo que el instructor puede hacer.
5. **Un solo paso = una sola acción del usuario** (o un solo grupo de campos de la misma pantalla), y cada paso tiene **su propio** espacio de pantallazo. No uses "ver captura anterior".
6. **Cero texto técnico** hacia el usuario (ver sección 7).
7. **No pongas** en el documento contraseñas, números de documento reales, correos reales de la base de datos, rutas de carpetas, nombres de archivos de código, ni nombres de tablas/campos de la base de datos. Para los ejemplos usa **datos ficticios** definidos por ti (sección 6.4).
8. **Si no estás seguro** de si algo funciona o de cómo se comporta: rastréalo en el código hasta estar seguro. Si aun así hay duda razonable de que esté roto (llama a un método inexistente, consulta una tabla/columna que no existe en el esquema, etc.), **NO lo incluyas en el manual** y regístralo en el reporte de hallazgos (sección 9). El manual solo describe lo que funciona.
9. **El documento final no debe contener** frases como "pendiente", "por confirmar", "TODO", "[completar]", "lorem ipsum" ni notas tuyas dirigidas al estudiante. Los **únicos** textos tipo "rellenar" permitidos son los marcadores de captura (sección 6).

---

## 4. METODOLOGÍA OBLIGATORIA (hazla en este orden; no escribas el manual hasta terminar las fases 1 y 2)

### FASE 1 — Inventario del proyecto (recorre TODO)

**4.1 Perfiles.** Abre `migrations/db_ingreso_aprendices_27_08.sql` (tabla `rol` e `INSERT`s), `helpers/Auth.php` y `views/layouts/header.php`. Confirma cuántos perfiles hay y su descripción. Confirma si existe algún otro perfil o variante (por ejemplo "instructor líder de ficha"): si es una variante *dentro* de Instructor, se explica dentro del manual del Instructor, no como perfil aparte, salvo que el código la trate como rol distinto.

**4.2 Menú y navegación por perfil.** `views/layouts/header.php` construye el menú lateral con condiciones por rol. Registra, **por cada perfil**, cada ítem del menú **con su texto exacto, su sección (Principal / Gestión / Asistencia / Mi Información / Excusas…) y a dónde lleva**. Registra también los elementos fijos: botón de colapsar el menú, botón de menú móvil, botón **Modo Claro / Modo Oscuro**, fecha en la cabecera, bloque de usuario (nombre y perfil) y **Cerrar Sesión**. Ojo con textos que cambian según el perfil (ejemplo: el ítem "Usuarios" se muestra como "Fichas / Usuarios" para el Instructor; "Historial" se muestra como "Mi Historial" para el Aprendiz).

**4.3 Recorrido de TODAS las rutas.** `helpers/Router.php` define el mapa `controlador → clase` y el formato `?action=controlador/metodo`. Para **cada controlador** en `controllers/` (`Aprendiz`, `Asistencia`, `Auth`, `Competencia`, `Dashboard`, `Excusa`, `Ficha`, `Horario`, `Jornada`, `Programa`, `Reporte`, `Usuario`) lista **cada método público** y para cada uno determina:
   - quién puede ejecutarlo (llamadas `Auth::require...` al inicio **y** los `if (Auth::is...)` internos),
   - qué vista muestra (`views/...`) o a dónde redirige,
   - si procesa un formulario (POST): qué campos recibe, **cómo los valida** (`Validator`, `if (empty(...))`, longitudes, formatos, tamaños de archivo, tipos permitidos), qué hace al guardar y **qué mensaje muestra** en éxito y en cada error,
   - a qué pantalla vuelve el usuario después.
   Distingue entre: (a) **pantallas/acciones que el usuario ve y usa**, (b) **alias** de otras (por ejemplo `create()`, `store()`, `form()` en Excusas apuntan a `crear()`/`guardar()`: es una sola función para el usuario), y (c) **servicios internos** que responden JSON a una pantalla (por ejemplo `datosGraficos`, `getNominaSesion`, `registrarMarcacion` por AJAX): estos **no se documentan como función aparte**; se documenta la **acción del usuario** que los dispara (ej.: "pasar el llavero por el lector" o "clic en Marcar").

**4.4 Recorrido de TODAS las vistas.** Abre **cada** archivo de `views/` (lista de verificación en la sección 8 — todos deben quedar asignados a alguna funcionalidad o descartados con motivo). En cada vista anota: título, botones y enlaces (texto y destino), tablas (encabezados), filtros y buscadores, formularios (cada campo: etiqueta, tipo, ¿obligatorio?, valores posibles, ejemplo/placeholder, textos de ayuda), ventanas emergentes/confirmaciones, estados vacíos ("No hay …"), y lo que muestra según el perfil (`Auth::is...` dentro de la vista).

**4.5 Comportamientos del navegador (JavaScript).** Lee `assets/js/app.js`, `dashboard.js`, `mi-horario.js`, `rfid.js` y los `<script>` de las vistas. Registra lo que el usuario percibe: buscadores en vivo de tablas, calendario (cambio de semana/mes, vistas), foco automático del lector, mensajes, activar/desactivar botones, confirmaciones al eliminar, gráficos del Dashboard (qué muestran), etc.

**4.6 Reglas de negocio visibles para el usuario.** Estas son reglas que el manual debe explicar en palabras simples cuando afectan lo que ve el usuario (verifícalas en el código antes de escribirlas; no las des por ciertas por este prompt):
   - **Retardo:** el aprendiz tiene 10 minutos de tolerancia contados desde que el instructor abre/registra la sesión de asistencia.
   - **Excusas:** si una ficha tiene 2 o más instructores en la franja, la excusa exige doble validación.
   - **Archivos de excusa:** tipos y tamaño máximo permitidos (verifica en `ExcusaController::guardar()` y en la vista del formulario).
   - **Importación de horarios por Excel:** formato esperado, tamaño, pantallas posibles de resultado (éxito, errores de validación, confirmación de permutas).
   - **Marcación RFID:** el lector funciona como un teclado; hay marcación por lector, por documento y manual.
   - Cualquier otra regla que descubras (estados de sesión, estados de excusa, cierre de sesiones, etc.).

**4.6.1 Mapa de PARTIDA (hipótesis a verificar, NO es verdad).** Lo siguiente es lo que se sospecha leyendo rápido el proyecto; sirve para que no omitas nada, pero **cada línea debe confirmarse o corregirse con el código**:
   - **Administrador (rol 1):** Dashboard; Usuarios (listar, filtrar por perfil, crear/editar/eliminar usuarios, incluida la creación de aprendices con ficha y llavero; ver integrantes de una ficha); Fichas (listar, crear, editar, eliminar, ver integrantes); Programas; Competencias; Jornadas; Horarios (listar con filtros por ficha e instructor, **Nueva Sesión**, editar, eliminar, **Importar Excel** con sus pantallas de resultado/errores/confirmación de permutas, **Auditoría Permutas**); Historial de asistencia; Reportes (tipos: asistencia consolidada por ficha, asistencia individual por aprendiz, excusas; con formato y rango de fechas); Excusas Médicas (listar pendientes/todas, revisar, aprobar/rechazar); Marcación RFID solo si es alcanzable por clic (en el menú actual solo aparece al Instructor: verifícalo, incluyendo accesos desde el Dashboard).
   - **Instructor (rol 2):** Dashboard; "Fichas / Usuarios" (directorio de **sus** fichas y aprendices); Fichas, Programas, Competencias, Jornadas (¡comprueba qué acciones tiene realmente permitidas aquí: ver, crear, editar, eliminar!); Horarios (incluye **Mi calendario**, **Permutas**, **Nueva Sesión**, **Importar Excel**, edición limitada a sus sesiones/ficha líder); **Mi Horario**; **Marcación RFID** (iniciar/cerrar sesión de asistencia, marcar por lector/documento/manual, ver nómina de la sesión); Historial; Reportes; Excusas Médicas (revisar y procesar las de sus aprendices).
   - **Aprendiz (rol 3):** Dashboard (se titula "Portal del Aprendiz — Mi Asistencia", con sus métricas y gráficos); **Mi Horario** (calendario de su ficha); **Mi Historial** (su asistencia y estadísticas); **Excusas Médicas** (ver las suyas y **Nueva Excusa** con archivo adjunto).
   - **Funciones comunes a los tres:** iniciar sesión (Número de Documento + Contraseña, con ícono para ver/ocultar contraseña), cerrar sesión, menú lateral (colapsar), Modo Claro/Oscuro, mensajes de aviso (cerrables). **No documentes** "cambiar contraseña" ni "recuperar contraseña" si no existen en el código (en la revisión inicial no se encontraron).

### FASE 2 — Matriz de funcionalidades (documento de trabajo, no se entrega)

Antes de redactar, construye internamente una **matriz** con una fila por funcionalidad de usuario y estas columnas: *perfil | funcionalidad | cómo se llega (clics desde el menú) | pantalla(s) | campos/acciones | validaciones | mensajes exactos de éxito/error | archivo(s) y líneas que lo prueban*. **Esta matriz es la base de todo lo que escribas.** Define la granularidad así: una funcionalidad es algo que el usuario describiría con un verbo ("Crear una ficha", "Presentar una excusa", "Ver mi horario", "Importar un horario desde Excel"). Cada operación (crear / editar / eliminar / ver / filtrar / buscar / exportar / aprobar / rechazar / abrir / cerrar…) que la interfaz ofrezca es una funcionalidad separada.

**Guarda el contenido en una estructura de datos** (por ejemplo, JSON o diccionarios de Python: perfiles → funcionalidades → pasos → marcadores de captura) y **genera el .docx y la guía de capturas desde esa misma estructura con un script**. Así los identificadores de captura coinciden 1:1 entre ambos archivos y no hay descuadres.

### FASE 3 — Redacción y generación (secciones 5, 6, 7)

### FASE 4 — Verificación (sección 8, obligatoria; no entregues sin pasarla toda)

---

## 5. ESTRUCTURA EXACTA DEL DOCUMENTO

El archivo se llama: `Documento_1_Manual_de_usuario_por_perfil_SCIA.docx`.

1. **Portada:** título "Manual de usuario por perfil", subtítulo "SCIA — Sistema de Control de Ingreso de Aprendices" (ese nombre aparece en la pantalla de inicio de sesión del sistema), y los DATOS DEL ESTUDIANTE que sí fueron proporcionados (omite los vacíos).
2. **Contenido:** lista de contenido con **enlaces internos** (marcadores/bookmarks) a cada sección, **sin números de página** (para que nunca aparezca vacía ni desactualizada al abrir el archivo). Usa estilos de título reales de Word (Título 1/2/3) para que el panel de navegación de Word funcione.
3. **Presentación del sistema:** 1 página. Qué es el sistema y para qué sirve, escrito para cualquiera (sin tecnicismos). Cómo está organizado el documento (un manual por perfil, independientes).
4. **Perfiles del sistema (Criterio 1):** tabla con columnas: *Perfil | ¿Quién es? | ¿Qué puede hacer en el sistema?* (una fila por perfil existente, exactamente los que haya en el código).
5. **Manual 1, Manual 2, Manual 3…** (uno por perfil, **cada uno en página nueva**, en este orden: Administrador, Instructor, Aprendiz, o el orden lógico que corresponda si el código muestra otros). Cada manual tiene **exactamente** esta estructura interna:
   - **X.1 Presentación del perfil:** quién es y qué puede hacer (3–6 líneas), y un **resumen en tabla** "Lo que puede hacer" con una fila por funcionalidad (nombre y para qué sirve).
   - **X.2 Cómo ingresar al sistema:** pasos numerados, cada uno con su pantallazo (abrir el navegador, escribir la dirección, ver la pantalla de inicio de sesión, escribir **Número de Documento**, escribir **Contraseña** —explicar el ícono del ojo—, hacer clic en **Iniciar Sesión**, ver la pantalla a la que llega **este** perfil). Incluye los **mensajes de error reales** de inicio de sesión (documento/contraseña incorrectos, usuario inactivo, etc. — extráelos de `AuthController`) y qué hacer.
   - **X.3 Cómo moverse por el sistema:** descripción del menú lateral **tal como lo ve este perfil** (cada ítem con su texto exacto, su ícono si ayuda, y para qué sirve), botón para colapsar el menú, menú en celular, **Modo Claro/Oscuro**, dónde ver su nombre y perfil, cómo leer y cerrar los mensajes de aviso, cómo usar los buscadores/filtros de las tablas. Con pantallazo del menú completo y de cada elemento no obvio.
   - **X.4 Funcionalidades** (el cuerpo del manual): cada funcionalidad como un bloque numerado **X.4.n** con la plantilla de la sección 6.1. Orden: el mismo orden del menú lateral del perfil, y dentro de cada módulo: ver/listar → buscar/filtrar → crear → editar → eliminar → acciones especiales.
   - **X.5 Cómo cerrar sesión:** pasos numerados con pantallazo.
   - **X.6 Preguntas frecuentes y mensajes que puede ver:** tabla *«Mensaje que aparece» → «Qué significa» → «Qué hacer»*, solo con mensajes reales del código que le puedan ocurrir a **ese** perfil.
6. **Glosario** (opcional pero recomendado, repetido dentro de cada manual como "Palabras que verá en pantalla", NO como una sección global a la que se remita): 1 línea simple por término del sistema que un usuario común pueda no conocer (Ficha, Competencia, Jornada, Retardo, Permuta, Llavero, Marcación, etc., según el perfil).

Cada manual debe poder **imprimirse y entregarse solo** a una persona de ese perfil y que esa persona no necesite nada más.

---

## 6. PLANTILLA DE CADA FUNCIONALIDAD Y ESPECIFICACIÓN DE LOS ESPACIOS DE PANTALLAZO

### 6.1 Plantilla de una funcionalidad (repítela idéntica para todas)

```
X.4.n  [Verbo + objeto, en lenguaje simple. Ej.: "Crear una ficha nueva"]

¿Para qué sirve?   1–2 frases simples.
¿Quién puede hacerlo?   Solo si aporta (por ejemplo: "Solo puede modificar las sesiones que son suyas").
Antes de empezar   Lo que necesita tener a mano (datos, archivo, estar en cierta pantalla), si aplica.

Paso 1. [una acción concreta, con el texto exacto del botón/campo en negrita]
   [ESPACIO DE PANTALLAZO — ver 6.2]
Paso 2. ...
   [ESPACIO DE PANTALLAZO]
...
Paso N. [Último paso: confirmar y ver el resultado]
   [ESPACIO DE PANTALLAZO que muestra el mensaje/resultado]

¿Qué verá cuando termine?   Resultado y, entre «comillas», el mensaje exacto de éxito.
Si algo sale mal   Mensajes de error reales de esta función (entre «comillas») y qué hacer para corregirlo.
Datos importantes   Solo reglas que afecten al usuario: obligatorios, formatos, tamaños, límites.
```

**Reglas de los pasos:**
- Empiezan con un verbo en imperativo y tratando al usuario de "usted" ("Haga clic en…", "Escriba…", "Seleccione…", "Verifique que…").
- El **primer paso** de cada funcionalidad dice cómo llegar (menú lateral → ítem exacto → botón exacto), **sin asumir** que el usuario ya está en la pantalla correcta.
- Cada paso describe **lo que se ve** y **lo que se hace**, y en el último paso **lo que debe ocurrir**.
- Cuando hay un formulario, **recorre todos los campos**: en cada paso agrupa los campos de la misma pantalla que se llenan juntos, y para **cada campo** indica: nombre exacto, si es obligatorio (el código lo marca con `required` o lo valida en el controlador), qué escribir/seleccionar, ejemplo ficticio, y formato/limitaciones reales (ej.: tamaño máximo del archivo, formatos permitidos).
- Para **eliminar**: describe la confirmación que realmente aparece (`confirm()`, ventana, página) con su texto exacto, y la opción de cancelar.
- Si una acción es **irreversible** (eliminar, aprobar/rechazar), dilo en lenguaje simple.
- Si un resultado depende del estado (por ejemplo, "si la ficha no tiene aprendices verá el mensaje «…»"), inclúyelo.

### 6.2 Espacio de pantallazo (marcador) — formato exacto

Debajo del texto de **cada paso** (sin excepción) coloca un **marcador de captura**, construido como una **tabla de 1 celda y 1 columna, ancho completo de la zona de texto**, con estas características:
- Borde **punteado gris**, **altura fija mínima ≈ 7 cm** (para que al pegar la imagen el diseño no se descuadre), la fila **no se puede partir entre páginas** (`cantSplit`) y el párrafo del paso anterior tiene **"mantener con el siguiente"** (`keepNext`), de modo que **el texto del paso y su marcador nunca queden en páginas distintas**.
- Texto centrado dentro de la celda, en gris, con **tres líneas**:
  1. `[ CAPTURA {ID} ]` — en negrita. Ejemplo: `[ CAPTURA ADM-03-02 ]`.
  2. `Qué debe mostrar: {descripción exacta de la pantalla y del estado que debe verse}.`
  3. `Resalte: {elemento a señalar con recuadro o flecha, p. ej. el botón "Guardar"}.`
- Inmediatamente debajo, un pie de figura en cursiva y centrado: `Figura {ID}. {descripción corta del contenido, redactada para que siga teniendo sentido una vez pegada la imagen}`.
- **Identificador único** `{PERFIL}-{NN funcionalidad}-{NN paso}`, con prefijos `ADM` (Administrador), `INS` (Instructor), `APR` (Aprendiz) (o el que corresponda a cada perfil real). Los números siempre con dos dígitos (`ADM-03-02`). Los pasos de **ingreso**, **navegación** y **cierre de sesión** usan números de funcionalidad `00` (ingreso), `01` (navegación) y `99` (cierre). **Ningún ID se repite en todo el documento.**
- Todos los marcadores deben tener **el mismo tamaño y estilo**. El estudiante los reemplazará borrando el texto de la celda y pegando la imagen: por eso deben ser una **celda real de tabla** (no un cuadro de texto flotante ni una forma), para que la imagen quede **incrustada en línea** y se mueva con el texto.

> No incluyas imágenes de relleno, capturas de ejemplo ni dibujos del sistema: solo los marcadores.

### 6.3 Pantallazos que son varios estados

Si un paso necesita ver dos estados (por ejemplo, "antes de escribir" y "después de seleccionar"), **divídelo en dos pasos** para que haya un marcador por estado. Un marcador = una captura.

### 6.4 Datos de ejemplo coherentes (para que texto y capturas coincidan)

Define **una sola tabla de datos de prueba ficticios** (nombres, documentos, correos, fichas, fechas, motivos de excusa, etc.) y **úsala igual en todos los pasos del texto** ("Escriba, por ejemplo, «Laura Gómez»"). Esa misma tabla irá en la **Guía de capturas** (sección 9) para que el estudiante escriba exactamente esos datos al tomar los pantallazos. No uses datos de personas reales ni los usuarios de ejemplo de la base de datos.

---

## 7. REGLAS DE LENGUAJE (Criterio 4)

- Público: cualquier persona del perfil, **sin saber de computadores más que usar un navegador**. Frases cortas (máx. ~20 palabras), un solo verbo de acción por paso, sin párrafos largos.
- **Prohibido en el texto dirigido al usuario** (y su sustituto):
  - base de datos, tabla, registro (como dato técnico), campo → "información", "casilla", "espacio para escribir";
  - CSRF, token, sesión (técnica), cookie, URL, servidor, request, endpoint, API, JSON, SQL, backend/frontend, PHP, query, parámetros → no se mencionan;
  - login/logout/log in → "ingresar / iniciar sesión", "cerrar sesión" (el sistema usa **Iniciar Sesión** y **Cerrar Sesión**);
  - dashboard → usa **Dashboard** solo como nombre del ítem del menú, y explica "(pantalla de resumen con sus datos principales)";
  - RFID, NFC → "lector de tarjetas o llaveros"; úsalo solo como nombre del ítem **Marcación RFID**, explicado la primera vez;
  - CSV, PDF, Excel, XLSX → mencionar el nombre del archivo en palabras simples solo cuando la pantalla lo muestre ("archivo de Excel", "archivo PDF"); no explicar formatos técnicos;
  - "dropdown, input, checkbox, submit, modal, hover, click, scroll" → "lista desplegable", "casilla para escribir", "casilla de selección", "botón", "ventana", "pasar el cursor", "hacer clic", "desplazarse hacia abajo".
- Los **términos propios del SENA/negocio** (ficha, competencia, jornada, retardo, instructor, aprendiz, excusa, permuta, llavero, marcación) **se pueden usar**, pero cada uno se **explica la primera vez** dentro de **cada** manual (y en su glosario).
- Explica el **resultado** de cada acción en palabras simples ("la ficha aparecerá en la lista").
- Tono amable, directo, en "usted". Sin jerga, sin sarcasmo, sin emojis en el cuerpo.
- **Consistencia:** un mismo concepto siempre se llama igual en todo el documento.

---

## 8. FORMATO TÉCNICO DEL .DOCX Y VERIFICACIÓN FINAL (obligatoria)

### 8.1 Formato
- Tamaño **carta (Letter)** o **A4** (elige uno y úsalo en todo el documento), márgenes ≥ 2,0 cm, fuente **Calibri o Arial 11 pt** para el cuerpo, interlineado 1,15, títulos con los estilos reales *Título 1 / Título 2 / Título 3* de Word (no solo texto en negrita grande), idioma del documento **español**.
- **Pie de página** con número de página (campo `PAGE`) y, en el encabezado, el nombre del documento.
- Cada Manual comienza con **salto de página**. Evita títulos huérfanos al final de una página (`keepNext` en títulos y en el texto de cada paso).
- Las listas de pasos usan numeración **escrita explícitamente en el texto** ("Paso 1.", "Paso 2."…) y **reinician en cada funcionalidad** (no uses la numeración automática de Word que puede continuar de una lista anterior).
- Nombres de elementos de pantalla en **negrita**; mensajes entre «comillas angulares».
- Tablas con encabezado sombreado, bordes finos, texto ≥ 10 pt, y que no se salgan de la página.
- No uses imágenes, fuentes ni librerías externas que requieran internet para ver el documento. Debe abrir sin errores ni avisos en Word, LibreOffice y Google Docs.
- Generación: usa `python-docx` o `docx` (JavaScript) y, si hace falta, XML directo para `cantSplit`, `keepNext`, marcadores (bookmarks) y enlaces internos.

### 8.2 Lista de verificación (ejecútala y **demuestra** los resultados en el reporte final)
Marca cada punto solo si lo comprobaste de verdad (con búsqueda/script/lectura), no "por confianza":

**Cobertura**
1. ☐ Todos los perfiles del código están en la tabla del Criterio 1 y cada uno tiene su manual.
2. ☐ Por **cada ítem del menú lateral de cada perfil** existe al menos una funcionalidad documentada (o está justificado en el reporte por qué no).
3. ☐ **Cada archivo de `views/`** (lista abajo) quedó asignado a una funcionalidad de algún perfil o descartado con motivo escrito en el reporte.
4. ☐ **Cada método público de cada controlador** quedó clasificado (funcionalidad de usuario / alias / servicio interno / no alcanzable por clic / roto) en el reporte.
5. ☐ Cada botón de acción visible para el perfil en cada vista (Nuevo, Editar, Eliminar, Importar, Revisar, Aprobar, Rechazar, Exportar, filtros, buscadores…) está explicado en algún paso.

**Exactitud**
6. ☐ **Cada texto en negrita** del documento (etiquetas, botones, menús, títulos) existe **literalmente** en el código; ejecútalo con un script: extrae todos los textos en negrita del .docx y compruébalos contra el contenido de `views/`, `assets/js/` y los `$pageTitle`/`setFlash` de los controladores. Corrige y repite hasta que **no quede ninguno sin respaldo**.
7. ☐ **Cada mensaje entre «comillas»** existe literalmente en el código (mismos acentos y puntuación).
8. ☐ Ninguna funcionalidad aparece en el manual de un perfil que **no pueda alcanzarla** por clic; y ninguna funcionalidad del perfil falta por haber confundido `requireAdmin()` con `requireAdminOnly()`.
9. ☐ Las reglas de negocio citadas (10 minutos de retardo, doble validación de excusas, tamaño/tipo de archivo, formato del Excel…) fueron **verificadas en el código** y se escriben con sus valores reales.
10. ☐ Ningún paso menciona un elemento que no exista en esa pantalla, ni salta pasos intermedios necesarios (ej.: entrar al módulo antes de pulsar su botón).

**Capturas**
11. ☐ Cada paso tiene **exactamente un** marcador y ningún marcador queda huérfano; nº de pasos = nº de marcadores = nº de filas en la guía de capturas.
12. ☐ Los IDs de captura son **únicos**, con el formato definido, y coinciden 1:1 con la Guía de capturas.
13. ☐ Todos los marcadores tienen el mismo tamaño/estilo y están **dentro de una celda real de tabla**, con `cantSplit`; el paso y su marcador no se separan entre páginas.

**Lenguaje y forma**
14. ☐ Búsqueda automática en el texto del usuario de los términos prohibidos de la sección 7 (y de rutas de archivos, nombres de clases, `?action=`, `.php`, SQL): **0 coincidencias** fuera de los textos literales de pantalla.
15. ☐ Cada término propio del negocio está explicado la primera vez en cada manual.
16. ☐ No hay referencias cruzadas entre manuales ("vea el manual de…"), ni texto de relleno ("pendiente", "TODO", "por confirmar", "lorem").
17. ☐ Ortografía y puntuación revisadas (español de Colombia); tuteo/ustedeo consistente ("usted").

**Archivo**
18. ☐ El .docx se abre sin error (verifica reabriéndolo con la librería y, si puedes, conviértelo a PDF con LibreOffice y **mira las páginas renderizadas como imágenes** para revisar: portada, contenido, tablas, marcadores, saltos de página, páginas vacías, textos cortados).
19. ☐ Los enlaces del Contenido funcionan; los títulos usan estilos reales; hay número de página.

**Lista de verificación de vistas (todas deben aparecer asignadas o descartadas):**
`views/auth/login.php`; `views/layouts/header.php`; `views/layouts/footer.php`; `views/dashboard/index.php`; `views/dashboard/_graficos.php`; `views/usuarios/index.php`; `views/usuarios/form.php`; `views/aprendices/index.php`; `views/aprendices/form.php`; `views/fichas/index.php`; `views/fichas/form.php`; `views/fichas/integrantes.php`; `views/programas/index.php`; `views/programas/form.php`; `views/competencias/index.php`; `views/competencias/form.php`; `views/jornadas/index.php`; `views/jornadas/form.php`; `views/horarios/index.php`; `views/horarios/form.php`; `views/horarios/importar.php`; `views/horarios/resultado.php`; `views/horarios/errores_validacion.php`; `views/horarios/confirmar_permutas.php`; `views/horarios/permutas.php`; `views/horarios/mi_horario.php`; `views/asistencia/rfid.php`; `views/asistencia/historial.php`; `views/excusas/index.php`; `views/excusas/form.php`; `views/excusas/revisar.php`; `views/reportes/index.php`.

---

## 9. ENTREGABLES (en este orden, todos en español)

1. **`Documento_1_Manual_de_usuario_por_perfil_SCIA.docx`** — el documento a entregar al instructor, con los marcadores de captura. Debe estar **terminado**: lo único pendiente de hacer por el estudiante es pegar las imágenes.
2. **`Guia_de_capturas_SCIA.docx` (o .md)** — documento **interno**, NO se entrega al instructor. Contiene:
   - Cómo prepararse: qué cuentas de prueba usar (una por perfil; **no** pongas contraseñas ni datos reales de nadie), qué datos de prueba cargar **antes** de tomar las capturas (la tabla de datos ficticios de 6.4) y en qué orden hacerlo para que cada captura tenga sentido (por ejemplo, crear primero una ficha antes de capturar "Editar ficha").
   - **Tabla de capturas**, una fila por marcador, **en el mismo orden del manual**: `ID | Perfil con el que debe iniciar sesión | Pantalla/ruta de menú | Estado previo necesario | Qué debe verse | Elemento a resaltar | ¿Hay que escribir algo? (datos exactos)`.
   - Recomendaciones de captura: misma resolución y zoom en todas (p. ej., 100 %), mismo tema (Modo Claro u Oscuro, el que elijan, **uno solo**), ventana maximizada, recortar solo el contenido relevante, ancho de imagen en Word ≈ 16 cm, no mostrar datos personales reales.
   - Cómo reemplazar un marcador: clic dentro de la celda → borrar el texto → Insertar → Imagen → "En línea con el texto" → ajustar ancho.
3. **Reporte de verificación y hallazgos** (en tu respuesta de chat, breve y concreto):
   - Resultado de la lista de verificación 8.2 (con números: perfiles, funcionalidades por perfil, pasos y capturas totales).
   - **Funciones excluidas del manual** y por qué (rotas, inalcanzables por clic, solo en documentos viejos, servicios internos), con archivo y línea.
   - **Contradicciones** entre documentos del repo y el código que encontraste.
   - Cualquier **error del software** que notaste y que el estudiante debería corregir antes de que el instructor pruebe el sistema (ej.: un botón que lleva a una página que falla, un mensaje que no coincide).
   - Los datos del estudiante que quedaron vacíos y por tanto se omitieron.

---

## 10. SI TU ENTORNO TIENE LIMITACIONES

- Si no puedes generar archivos .docx, entrega el manual completo en **Markdown** con **exactamente** la misma estructura y los mismos marcadores (cada marcador como una tabla de una celda con las tres líneas de 6.2), y **avisa al inicio** de tu respuesta que no pudiste generar el .docx para que el estudiante lo convierta (por ejemplo, con Pandoc o pegándolo en Word).
- Si el ZIP es demasiado grande para leerlo de una vez, léelo **por carpetas** en este orden: `config/` → `helpers/` → `models/` → `controllers/` → `views/` → `assets/js/` → `migrations/db_ingreso_aprendices_27_08.sql`; guarda tus notas en un archivo de trabajo para no perder hallazgos.
- Si el contenido es muy largo para una sola respuesta, **trabaja por etapas guardando archivos** (inventario → matriz → datos → .docx) y no resumas ni recortes funcionalidades "por espacio". Un manual incompleto es un manual inválido.
- **No pidas permiso para continuar** ni hagas preguntas intermedias salvo que algo **impida** realmente avanzar. Ante la duda, rastrea el código; si sigue la duda, exclúyelo del manual y repórtalo.

---

## 11. CRITERIO FINAL DE ÉXITO (autoevalúate antes de responder)

Imagina al instructor sentado frente al sistema real con el manual abierto, siguiendo cada paso con el dedo:
- ¿Cada menú, botón y campo del paso existe y se llama igual? → debe ser **sí**.
- ¿Cada paso lleva a la pantalla y al resultado que el manual dice? → debe ser **sí**.
- ¿Cada perfil encuentra **todo** lo que puede hacer y **nada** de lo que no puede? → debe ser **sí**.
- ¿Una persona sin conocimientos técnicos puede completar cada función sin ayuda? → debe ser **sí**.
- ¿Hay un espacio de pantallazo visible, del mismo tamaño y en el lugar correcto, debajo de **cada** paso? → debe ser **sí**.
- ¿El archivo abre sin errores y se ve profesional? → debe ser **sí**.

Si alguna respuesta es "no" o "no estoy seguro", **corrige antes de entregar**.

===== FIN DEL PROMPT =====
