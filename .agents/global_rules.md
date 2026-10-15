# global_rules.md — Alcance del agente

Actualización: solo agregar entradas o tachar (`~~texto~~`). No borrar ni reescribir.

## Límites de carpeta
- El agente trabaja **solo dentro de la carpeta del repositorio**. No lee, escribe ni ejecuta nada fuera de ella.

## Base de datos
- **No modificar la base de datos directamente** (INSERT/UPDATE/DELETE/DDL, importar `.sql`, ejecutar migraciones) sin pedir permiso antes.
- No ejecutar ni forzar la auto-creación de la BD que hace `config/Database.php`.
- Cambios de esquema: se proponen como nuevo archivo en `migrations/` y se muestran en el plan.

## Carpetas y archivos a ignorar (salvo que sea estrictamente necesario)
- `librerias/` (aquí instala Composer: `vendor-dir` = `librerias`; incluye FPDF). No editar.
- `composer.phar`
- `.git/`
- `sistema-control-ingreso.mwb.bak.zip` (respaldo del modelo de BD)
- `assets/img/` (imágenes)
- `node_modules/` y `vendor/` (no existen hoy; ignorar si aparecen)

## Datos sensibles
- No escribir credenciales reales de BD en el repo. No mostrar contraseñas en respuestas.

## Acciones que requieren permiso explícito
- Commit y push
- Instalar, actualizar o quitar dependencias
- Cualquier acción sobre la base de datos
- Crear controladores, jobs o carpetas nuevas fuera de la estructura actual