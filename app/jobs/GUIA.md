# jobs/ — GUÍA DE ARQUITECTURA (Tareas Programadas)

> Aquí viven los scripts PHP que **NO** están expuestos a la web a través de la API.
> Se ejecutan por debajo (línea de comandos) mediante programadores de tareas
> como **Cron** (Linux) o el **Programador de tareas** (Windows).

---

## 1. Responsabilidad de esta carpeta

Esta carpeta existe para aislar los procesos automáticos pesados o rutinarios. 
Al no pasar por el enrutador HTTP (`index.php?action=...`), estos scripts:
- No tienen límite de tiempo de ejecución (max_execution_time de PHP web no aplica).
- No lidian con sesiones HTTP (`$_SESSION`).
- No devuelven JSON; imprimen logs a la consola (`echo`).
- No pueden ser ejecutados maliciosamente por un usuario visitando una URL.

---

## 2. Archivos que deben vivir aquí

| Archivo | Funcionalidad (RF) | Ejecución sugerida |
|---|---|---|
| `generarInasistencias.php` | **RF-09** (Limpieza y generación de inasistencias al final del día) | Todos los días (L-S) a las 23:00 |

---

## 3. Ejemplo: `generarInasistencias.php` (RF-09)

Este script busca a todos los aprendices que debían asistir HOY según el horario
de su ficha, pero que no registraron ninguna marcación (no existe fila en `ingresos_asistencias`).
Para ellos, crea un registro de inasistencia.

```php
<?php
// jobs/generarInasistencias.php

// 1. Cargar dependencias (Modelos directamente, no Controladores)
require_once __DIR__ . '/../models/IngresoModel.php';
require_once __DIR__ . '/../models/HorarioModel.php';
require_once __DIR__ . '/../models/AprendizModel.php';

echo "[".date('Y-m-d H:i:s')."] INICIO: Job de Inasistencias (RF-09)\n";

try {
    $ingresoModel = new IngresoModel();
    $horarioModel = new HorarioModel();
    $aprendizModel = new AprendizModel();

    $hoyFecha = date('Y-m-d');
    
    // PHP date('N'): 1 (lunes) a 7 (domingo). 
    // Mapeo a nuestro ENUM
    $dias = [
        1 => 'Lunes', 2 => 'Martes', 3 => 'Miercoles',
        4 => 'Jueves', 5 => 'Viernes', 6 => 'Sabado', 7 => 'Domingo'
    ];
    $diaHoyStr = $dias[date('N')];

    // Obtener todos los aprendices activos
    $todosAprendices = $aprendizModel->listar();
    $inasistenciasGeneradas = 0;

    foreach ($todosAprendices as $aprendiz) {
        $idFicha = $aprendiz['fk_fichas_idFicha'];
        $idAprendices = $aprendiz['idAprendices'];

        // 1. ¿Esta ficha tenía clase hoy?
        $horarioHoy = $horarioModel->obtenerPorFichaYDia($idFicha, $diaHoyStr);
        if (!$horarioHoy) {
            continue; // No tenían clase, no aplica inasistencia
        }

        // 2. ¿El aprendiz marcó ingreso hoy?
        $ingresoHoy = $ingresoModel->buscarIngresoHoy($idAprendices);
        
        if (!$ingresoHoy) {
            // No marcó. Generamos Inasistencia.
            $ingresoModel->insertarInasistencia($idAprendices, $hoyFecha);
            $inasistenciasGeneradas++;
            echo " - Inasistencia registrada: Aprendiz ID $idAprendices (Documento: {$aprendiz['numDocumento']})\n";
        }
    }

    echo "[".date('Y-m-d H:i:s')."] FIN: $inasistenciasGeneradas inasistencias generadas.\n";

} catch (Exception $e) {
    echo "[ERROR CRÍTICO] " . $e->getMessage() . "\n";
}
```

---

## 4. Cómo se ejecuta (Instalación)

Estos scripts se diseñan para la **línea de comandos (CLI)**.

### En Desarrollo (Manual)
Abre la terminal en la raíz del proyecto y ejecuta:
```bash
php jobs/generarInasistencias.php
```

### En Producción (Linux / cPanel)
Se debe configurar un Cron Job (ej. todos los días a las 23:00):
```bash
0 23 * * * /usr/bin/php /ruta/al/proyecto/jobs/generarInasistencias.php >> /ruta/al/proyecto/jobs/logs.txt 2>&1
```

### En Producción (Windows / XAMPP)
Usar el **Programador de Tareas** de Windows para ejecutar una acción diariamente a las 23:00:
- Programa: `C:\xampp\php\php.exe`
- Argumentos: `C:\xampp\htdocs\proyecto-adso...\jobs\generarInasistencias.php`

---

## 5. Comunicación con otras carpetas

| Carpeta | Relación con `jobs/` |
|---|---|
| `models/` | Los Jobs instancian modelos (`IngresoModel`, `AprendizModel`) y usan sus métodos directamente para la lógica de negocio. |
| `controllers/` | **Ninguna**. Los controladores son para HTTP/Web. Los Jobs evitan los controladores deliberadamente. |
| `views/` | **Ninguna**. |
| `assets/` | **Ninguna**. |

---

## 6. Reglas de esta carpeta

1. **NO devuelven JSON.** Como no responden a una petición web HTTP, hacer `echo json_encode(...)` no tiene sentido. Deben hacer `echo` de texto plano para que quede registrado en los logs del servidor.
2. **Las vistas son HTML estático:** (No aplica directamente a los Jobs, pues no tocan la web).
3. **Toda consulta a la BD usa PDO.** Los Jobs usan los Modelos, que a su vez respetan esta regla de oro.
4. **Todo JS es módulo ES:** (No aplica, esto es 100% PHP de servidor).
5. **No hay Autenticación de Usuario.** Al correr desde la consola local del servidor, no existe `requireAuth()`. Se asume que quien ejecuta el script (el SO) tiene permisos.
6. **Los nombres de tabla/columna son EXACTAMENTE los del esquema.** Las inserciones en `ingresos_asistencias` (ej. `estado='Inasistencia'`) deben coincidir al 100% con el ENUM definido en `migrations/`.
