# migrations/ — GUÍA DE ARQUITECTURA

> Cómo se maneja el esquema de la base de datos: convención de nombres,
> qué incluir, cómo ejecutar los archivos y errores ya conocidos que **no**
> se deben repetir.

---

## 1. Qué vive aquí

Archivos **SQL** que crean y modifican la base de datos `sistema-ingresos`.
Cada archivo es una **migración**: un cambio ordenado y versionado del esquema.

```
migrations/
├── 001-sistema-ingresos.sql   ← esquema completo (tablas, índices, FKs)
├── 002-descripcion.sql        ← futuras alteraciones
└── GUIA.md                    ← este archivo
```

---

## 2. Convención de nombres

Formato: `NNN-descripcion.sql`

- `NNN` = número secuencial de 3 dígitos (001, 002, 003…).
- `descripcion` = qué hace la migración, en minúsculas y con guiones.
- Extensión siempre `.sql`.

Ejemplos:

```
001-sistema-ingresos.sql          ← esquema inicial
002-agregar-campo-telefono.sql    ← altera una tabla
003-seed-roles.sql                ← datos iniciales
```

---

## 3. Esquema actual — `001-sistema-ingresos.sql`

### 3.1 Orden de creación (respeta dependencias FK)

```
1. roles                    ← sin dependencias
2. usuarios                 ← FK → roles
3. fichas                   ← FK → usuarios (instructor líder)
4. horarios                 ← FK → fichas
5. aprendices               ← FK → usuarios, fichas
6. ingresos_asistencias     ← FK → aprendices
7. excusasmedicas           ← FK → aprendices, ingresos_asistencias, usuarios
```

### 3.2 Tablas y columnas (fuente de verdad)

| Tabla | Columnas | PK | FKs |
|---|---|---|---|
| `roles` | `idRol`, `nombre` | `idRol` | — |
| `usuarios` | `idUsuario`, `numDocumento` (UNIQUE), `nombre`, `apellido`, `correoElectronico` (UNIQUE), `password`, `estado` ENUM(`activo`,`inactivo`,`pendiente`,`bloqueado`), `creadoEn`, `fk_roles_idRol` | `idUsuario` | `fk_roles_idRol` → `roles(idRol)` |
| `fichas` | `idFicha`, `codigoFicha` (UNIQUE), `nombrePrograma`, `fk_instructor_lider` | `idFicha` | `fk_instructor_lider` → `usuarios(idUsuario)` |
| `horarios` | `idHorario`, `diaSemana` ENUM(`Lunes`..`Sabado`), `horaEntrada`, `horaSalida`, `toleranciaMinutos` DEFAULT 15, `fk_fichas_idFicha` | `idHorario` | `fk_fichas_idFicha` → `fichas(idFicha)` |
| `aprendices` | `idAprendices`, `codigoRfid` (UNIQUE), `fk_usuarios_idUsuario` (UNIQUE), `fk_fichas_idFicha` | `idAprendices` | `fk_usuarios_idUsuario` → `usuarios(idUsuario)`, `fk_fichas_idFicha` → `fichas(idFicha)` |
| `ingresos_asistencias` | `idIngreso`, `fecha`, `horaEntrada`, `horaSalida`, `estado` ENUM(`A_Tiempo`,`Retardo`,`Salida_Temprana`,`Inasistencia`,`Justificado`), `minutosRetardo` DEFAULT 0, `minutosSalidaAnticipada` DEFAULT 0, `fk_aprendices_idAprendices` | `idIngreso` | `fk_aprendices_idAprendices` → `aprendices(idAprendices)` |
| `excusasmedicas` | `idExcusa`, `fechaInicio`, `fechaFin`, `motivo`, `archivoAdjunto`, `estado` ENUM(`Pendiente`,`Aprobada`,`Rechazada`) DEFAULT `Pendiente`, `comentarioRevision`, `fechaSolicitud`, `aprendices_idAprendices`, `ingresos_asistencias_idIngreso`, `instructor_usuarios_idUsuario` (NULLABLE) | `idExcusa` | `aprendices_idAprendices` → `aprendices(idAprendices)`, `ingresos_asistencias_idIngreso` → `ingresos_asistencias(idIngreso)`, `instructor_usuarios_idUsuario` → `usuarios(idUsuario)` |

### 3.3 Relaciones clave de negocio

- Un **aprendiz** es un `usuario` (con `fk_roles_idRol` = rol "Aprendiz") + una fila en `aprendices` con su `codigoRfid`.
- `aprendices.fk_usuarios_idUsuario` es **UNIQUE**: un usuario sólo puede ser aprendiz una vez.
- `excusasmedicas.instructor_usuarios_idUsuario` es **NULL** al crearse la excusa; se llena sólo cuando un instructor la aprueba o rechaza.
- El "borrado" de usuarios/aprendices es **soft delete** vía `usuarios.estado = 'inactivo'`, nunca `DELETE`.

---

## 4. Cómo ejecutar una migración

### Primera vez (esquema completo)

```bash
# Desde la raíz del proyecto, con XAMPP corriendo:
mysql -u root < migrations/001-sistema-ingresos.sql
```

O desde **phpMyAdmin**: pestaña "Importar" → seleccionar el archivo `.sql`.

### Migraciones incrementales

```bash
mysql -u root sistema-ingresos < migrations/002-descripcion.sql
```

**Regla:** cada migración se ejecuta UNA sola vez por entorno. Si ya se ejecutó, no se repite.

---

## 5. Cómo escribir una nueva migración

### Ejemplo: agregar un campo `telefono` a `usuarios`

```sql
-- ============================================
-- 002-agregar-campo-telefono.sql
-- Agrega teléfono de contacto a usuarios
-- ============================================

USE `sistema-ingresos`;

ALTER TABLE `usuarios`
  ADD COLUMN `telefono` VARCHAR(20) DEFAULT NULL
  AFTER `correoElectronico`;
```

### Ejemplo: datos semilla para `roles`

```sql
-- ============================================
-- 003-seed-roles.sql
-- Inserta los 3 roles del sistema
-- ============================================

USE `sistema-ingresos`;

INSERT INTO `roles` (`nombre`) VALUES
  ('Administrador'),
  ('Instructor'),
  ('Aprendiz')
ON DUPLICATE KEY UPDATE `nombre` = VALUES(`nombre`);
```

---

## 6. Bug conocido — NUNCA declarar el mismo UNIQUE dos veces

En versiones anteriores del esquema se declaró el mismo constraint UNIQUE dos veces
sobre la misma columna (una vez como `UNIQUE KEY` en el `CREATE TABLE` y otra vez con
`ALTER TABLE … ADD UNIQUE KEY`). MariaDB/MySQL permite esto sin error, pero genera
índices duplicados que causan problemas silenciosos.

**Regla:** revisa siempre que un UNIQUE no esté ya declarado antes de agregarlo.
Si ya existe como parte del `CREATE TABLE`, NO lo repitas en un `ALTER TABLE`.

---

## 7. Comunicación con otras carpetas

| Carpeta | Relación con `migrations/` |
|---|---|
| `models/` | Los modelos (`Database.php`, `*Model.php`) usan los nombres exactos de tablas y columnas definidos aquí. Si cambias el esquema, actualiza los modelos. |
| `controllers/` | Los controladores no tocan el esquema, pero sus validaciones dependen de los `ENUM` y `DEFAULT` definidos aquí. |
| `views/` | No hay relación directa: las vistas no saben de SQL. |
| `jobs/` | El script `generarInasistencias.php` inserta en `ingresos_asistencias`; sus columnas deben coincidir con este esquema. |

---

## 8. Reglas de esta carpeta

1. **El servidor SOLO devuelve JSON.** Cero HTML de páginas desde PHP. *(No aplica directamente a SQL, pero los modelos que consumen este esquema sí cumplen esta regla.)*
2. **Las vistas son HTML estático:** cero `<?php`, cero SQL.
3. **Toda consulta a la BD usa PDO con prepared statements** (`prepare()` + `execute([…])`). Los nombres de tabla y columna deben coincidir exactamente con los de esta migración.
4. **Todo JS es módulo ES:** `export`/`import`, nada de funciones globales ni `onclick` inline.
5. **Todo endpoint sensible empieza con `requireAuth()` / `requireRol()`.**
6. **Soft delete siempre** (`estado='inactivo'`), nunca `DELETE` sobre `usuarios`/`aprendices`.
7. **Los nombres de tabla/columna son EXACTAMENTE los de este archivo** — `idAprendices`, `fk_usuarios_idUsuario`, `ingresos_asistencias`, `excusasmedicas`, `codigoRfid`, etc. No los traduzcas ni los normalices a otro estilo.
