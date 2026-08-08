# models/ — GUÍA DE ARQUITECTURA (SQL PDO)

> Qué va en esta carpeta, la conexión con **PDO** y cómo se comunica
> con `controllers/` y la base de datos `sistema-ingresos`.

---

## 1. Responsabilidad del modelo

El modelo es la **única capa que habla con la base de datos** y lo hace con **PDO**.

Reglas de oro:

- Devuelve `array` (filas) o `null` (no encontrado). **NUNCA genera HTML ni hace `echo`**.
- Todas las consultas usan **prepared statements de PDO** (anti inyección SQL).
- No conoce la sesión ni el frontend: recibe parámetros y devuelve datos.

---

## 2. Archivos que deben vivir aquí

| Archivo | Tabla(s) que toca | Descripción |
|---|---|---|
| `Database.php` | — | Conexión PDO (singleton). Todos los modelos la extienden. |
| `UsuarioModel.php` | `usuarios`, `roles` | Login, búsqueda por documento/correo, cambio de estado. |
| `AprendizModel.php` | `aprendices`, `usuarios`, `fichas` | CRUD de aprendices (JOIN con usuarios para datos completos). |
| `FichaModel.php` | `fichas`, `usuarios` | CRUD de fichas (JOIN con usuarios para nombre del instructor). |
| `HorarioModel.php` | `horarios`, `fichas` | CRUD de horarios por ficha. |
| `IngresoModel.php` | `ingresos_asistencias`, `aprendices`, `horarios` | Marcar entrada/salida, historial, clasificación de estado. |
| `ExcusaModel.php` | `excusasmedicas`, `aprendices`, `ingresos_asistencias` | Crear excusa, listar pendientes, aprobar/rechazar. |
| `ReporteModel.php` | `ingresos_asistencias`, `aprendices`, `fichas`, `usuarios` | Consultas agregadas para reportes (día/semana/rango). |

---

## 3. Database.php — conexión PDO (singleton)

Una única conexión reutilizada por toda la app. Lee credenciales de variables de entorno
con valores por defecto para XAMPP.

```php
<?php

class Database
{
    private static ?PDO $pdo = null;

    public static function conn(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = new PDO(
                "mysql:host=" . (getenv('DB_HOST') ?: 'localhost')
                    . ";dbname=" . (getenv('DB_NAME') ?: 'sistema-ingresos')
                    . ";charset=utf8mb4",
                getenv('DB_USER') ?: 'root',
                getenv('DB_PASS') ?: '',
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        }
        return self::$pdo;
    }
}
```

**¿Por qué estos atributos?**

| Atributo | Razón |
|---|---|
| `charset=utf8mb4` | Soporta tildes, eñes y emojis. |
| `ERRMODE_EXCEPTION` | Cualquier error SQL lanza una `PDOException` que el controlador atrapa. |
| `FETCH_ASSOC` | Devuelve arrays asociativos (no numéricos). |
| `EMULATE_PREPARES = false` | Usa prepared statements reales del motor, no emulados por PHP. |

---

## 4. Cómo escribir un modelo — patrón completo

Los modelos extienden `Database` y usan `self::conn()` para obtener PDO.
Toda consulta sigue el patrón `prepare()` + `execute([…])`.

---

### 4.1 UsuarioModel.php

```php
<?php
require_once __DIR__ . '/Database.php';

class UsuarioModel extends Database
{
    /**
     * Busca un usuario activo por numDocumento (para login).
     * Devuelve el usuario con su nombre de rol, o null.
     */
    public function buscarPorDocumento(string $numDocumento): ?array
    {
        $sql = "SELECT u.idUsuario, u.numDocumento, u.nombre, u.apellido,
                       u.correoElectronico, u.password, u.estado,
                       r.nombre AS rol
                FROM usuarios u
                INNER JOIN roles r ON r.idRol = u.fk_roles_idRol
                WHERE u.numDocumento = :doc AND u.estado = 'activo'";
        $stmt = self::conn()->prepare($sql);
        $stmt->execute([':doc' => $numDocumento]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Crea un usuario. Devuelve el idUsuario insertado.
     */
    public function crear(array $datos): int
    {
        $sql = "INSERT INTO usuarios
                (numDocumento, nombre, apellido, correoElectronico, password, fk_roles_idRol)
                VALUES (:doc, :nom, :ape, :correo, :pass, :rol)";
        $stmt = self::conn()->prepare($sql);
        $stmt->execute([
            ':doc'    => $datos['numDocumento'],
            ':nom'    => $datos['nombre'],
            ':ape'    => $datos['apellido'],
            ':correo' => $datos['correoElectronico'],
            ':pass'   => password_hash($datos['password'], PASSWORD_DEFAULT),
            ':rol'    => $datos['fk_roles_idRol'],
        ]);
        return (int) self::conn()->lastInsertId();
    }

    /**
     * Soft delete: cambia estado a 'inactivo'. NUNCA DELETE.
     */
    public function desactivar(int $idUsuario): bool
    {
        $sql = "UPDATE usuarios SET estado = 'inactivo' WHERE idUsuario = :id";
        $stmt = self::conn()->prepare($sql);
        $stmt->execute([':id' => $idUsuario]);
        return $stmt->rowCount() > 0;
    }
}
```

---

### 4.2 AprendizModel.php

```php
<?php
require_once __DIR__ . '/Database.php';

class AprendizModel extends Database
{
    /**
     * Lista todos los aprendices activos con datos de usuario y ficha.
     */
    public function listar(): array
    {
        $sql = "SELECT a.idAprendices, a.codigoRfid,
                       u.numDocumento, u.nombre, u.apellido,
                       u.correoElectronico, u.estado,
                       f.codigoFicha, f.nombrePrograma
                FROM aprendices a
                INNER JOIN usuarios u ON u.idUsuario = a.fk_usuarios_idUsuario
                INNER JOIN fichas f   ON f.idFicha   = a.fk_fichas_idFicha
                WHERE u.estado = 'activo'
                ORDER BY u.apellido, u.nombre";
        return self::conn()->query($sql)->fetchAll();
    }

    /**
     * Busca un aprendiz por su idAprendices (con datos de usuario y ficha).
     */
    public function buscarPorId(int $idAprendices): ?array
    {
        $sql = "SELECT a.idAprendices, a.codigoRfid,
                       u.idUsuario, u.numDocumento, u.nombre, u.apellido,
                       u.correoElectronico, u.estado,
                       f.idFicha, f.codigoFicha, f.nombrePrograma
                FROM aprendices a
                INNER JOIN usuarios u ON u.idUsuario = a.fk_usuarios_idUsuario
                INNER JOIN fichas f   ON f.idFicha   = a.fk_fichas_idFicha
                WHERE a.idAprendices = :id";
        $stmt = self::conn()->prepare($sql);
        $stmt->execute([':id' => $idAprendices]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Busca un aprendiz por su codigoRfid (para marcar ingreso).
     */
    public function buscarPorRfid(string $codigoRfid): ?array
    {
        $sql = "SELECT a.idAprendices, a.codigoRfid,
                       u.nombre, u.apellido, u.estado,
                       f.idFicha, f.codigoFicha
                FROM aprendices a
                INNER JOIN usuarios u ON u.idUsuario = a.fk_usuarios_idUsuario
                INNER JOIN fichas f   ON f.idFicha   = a.fk_fichas_idFicha
                WHERE a.codigoRfid = :rfid AND u.estado = 'activo'";
        $stmt = self::conn()->prepare($sql);
        $stmt->execute([':rfid' => $codigoRfid]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Crea un aprendiz (usuario ya debe existir con rol Aprendiz).
     */
    public function crear(int $idUsuario, int $idFicha, ?string $codigoRfid): int
    {
        $sql = "INSERT INTO aprendices (fk_usuarios_idUsuario, fk_fichas_idFicha, codigoRfid)
                VALUES (:usr, :ficha, :rfid)";
        $stmt = self::conn()->prepare($sql);
        $stmt->execute([
            ':usr'   => $idUsuario,
            ':ficha' => $idFicha,
            ':rfid'  => $codigoRfid,
        ]);
        return (int) self::conn()->lastInsertId();
    }

    /**
     * Actualiza la ficha y/o el codigoRfid de un aprendiz.
     */
    public function actualizar(int $idAprendices, array $datos): bool
    {
        $sql = "UPDATE aprendices
                SET codigoRfid = :rfid, fk_fichas_idFicha = :ficha
                WHERE idAprendices = :id";
        $stmt = self::conn()->prepare($sql);
        $stmt->execute([
            ':rfid'  => $datos['codigoRfid'],
            ':ficha' => $datos['fk_fichas_idFicha'],
            ':id'    => $idAprendices,
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Asignar (o reasignar) un aprendiz a una ficha.
     */
    public function asignarFicha(int $idAprendices, int $idFicha): bool
    {
        $sql = "UPDATE aprendices SET fk_fichas_idFicha = :ficha
                WHERE idAprendices = :id";
        $stmt = self::conn()->prepare($sql);
        $stmt->execute([':ficha' => $idFicha, ':id' => $idAprendices]);
        return $stmt->rowCount() > 0;
    }
}
```

---

### 4.3 FichaModel.php

```php
<?php
require_once __DIR__ . '/Database.php';

class FichaModel extends Database
{
    public function listar(): array
    {
        $sql = "SELECT f.idFicha, f.codigoFicha, f.nombrePrograma,
                       u.nombre AS instructorNombre, u.apellido AS instructorApellido
                FROM fichas f
                INNER JOIN usuarios u ON u.idUsuario = f.fk_instructor_lider
                ORDER BY f.codigoFicha";
        return self::conn()->query($sql)->fetchAll();
    }

    public function buscarPorId(int $idFicha): ?array
    {
        $sql = "SELECT f.idFicha, f.codigoFicha, f.nombrePrograma, f.fk_instructor_lider,
                       u.nombre AS instructorNombre, u.apellido AS instructorApellido
                FROM fichas f
                INNER JOIN usuarios u ON u.idUsuario = f.fk_instructor_lider
                WHERE f.idFicha = :id";
        $stmt = self::conn()->prepare($sql);
        $stmt->execute([':id' => $idFicha]);
        return $stmt->fetch() ?: null;
    }

    public function crear(array $datos): int
    {
        $sql = "INSERT INTO fichas (codigoFicha, nombrePrograma, fk_instructor_lider)
                VALUES (:cod, :prog, :inst)";
        $stmt = self::conn()->prepare($sql);
        $stmt->execute([
            ':cod'  => $datos['codigoFicha'],
            ':prog' => $datos['nombrePrograma'],
            ':inst' => $datos['fk_instructor_lider'],
        ]);
        return (int) self::conn()->lastInsertId();
    }

    public function actualizar(int $idFicha, array $datos): bool
    {
        $sql = "UPDATE fichas
                SET codigoFicha = :cod, nombrePrograma = :prog, fk_instructor_lider = :inst
                WHERE idFicha = :id";
        $stmt = self::conn()->prepare($sql);
        $stmt->execute([
            ':cod'  => $datos['codigoFicha'],
            ':prog' => $datos['nombrePrograma'],
            ':inst' => $datos['fk_instructor_lider'],
            ':id'   => $idFicha,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function eliminar(int $idFicha): bool
    {
        $sql = "DELETE FROM fichas WHERE idFicha = :id";
        $stmt = self::conn()->prepare($sql);
        $stmt->execute([':id' => $idFicha]);
        return $stmt->rowCount() > 0;
    }
}
```

---

### 4.4 HorarioModel.php

```php
<?php
require_once __DIR__ . '/Database.php';

class HorarioModel extends Database
{
    /**
     * Lista los horarios de una ficha.
     */
    public function listarPorFicha(int $idFicha): array
    {
        $sql = "SELECT idHorario, diaSemana, horaEntrada, horaSalida, toleranciaMinutos
                FROM horarios
                WHERE fk_fichas_idFicha = :ficha
                ORDER BY FIELD(diaSemana,'Lunes','Martes','Miercoles','Jueves','Viernes','Sabado')";
        $stmt = self::conn()->prepare($sql);
        $stmt->execute([':ficha' => $idFicha]);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene el horario de una ficha para un día específico.
     * Se usa para calcular retardo/salida temprana.
     */
    public function obtenerPorFichaYDia(int $idFicha, string $diaSemana): ?array
    {
        $sql = "SELECT idHorario, horaEntrada, horaSalida, toleranciaMinutos
                FROM horarios
                WHERE fk_fichas_idFicha = :ficha AND diaSemana = :dia";
        $stmt = self::conn()->prepare($sql);
        $stmt->execute([':ficha' => $idFicha, ':dia' => $diaSemana]);
        return $stmt->fetch() ?: null;
    }

    public function crear(array $datos): int
    {
        $sql = "INSERT INTO horarios (diaSemana, horaEntrada, horaSalida, toleranciaMinutos, fk_fichas_idFicha)
                VALUES (:dia, :entrada, :salida, :tol, :ficha)";
        $stmt = self::conn()->prepare($sql);
        $stmt->execute([
            ':dia'     => $datos['diaSemana'],
            ':entrada' => $datos['horaEntrada'],
            ':salida'  => $datos['horaSalida'],
            ':tol'     => $datos['toleranciaMinutos'] ?? 15,
            ':ficha'   => $datos['fk_fichas_idFicha'],
        ]);
        return (int) self::conn()->lastInsertId();
    }

    public function actualizar(int $idHorario, array $datos): bool
    {
        $sql = "UPDATE horarios
                SET diaSemana = :dia, horaEntrada = :entrada,
                    horaSalida = :salida, toleranciaMinutos = :tol
                WHERE idHorario = :id";
        $stmt = self::conn()->prepare($sql);
        $stmt->execute([
            ':dia'     => $datos['diaSemana'],
            ':entrada' => $datos['horaEntrada'],
            ':salida'  => $datos['horaSalida'],
            ':tol'     => $datos['toleranciaMinutos'] ?? 15,
            ':id'      => $idHorario,
        ]);
        return $stmt->rowCount() > 0;
    }

    public function eliminar(int $idHorario): bool
    {
        $sql = "DELETE FROM horarios WHERE idHorario = :id";
        $stmt = self::conn()->prepare($sql);
        $stmt->execute([':id' => $idHorario]);
        return $stmt->rowCount() > 0;
    }
}
```

---

### 4.5 IngresoModel.php

```php
<?php
require_once __DIR__ . '/Database.php';

class IngresoModel extends Database
{
    /**
     * Registra la entrada de un aprendiz (primera marcación del día).
     * La clasificación (A_Tiempo / Retardo) la calcula el controlador
     * comparando con HorarioModel::obtenerPorFichaYDia().
     */
    public function registrarEntrada(array $datos): int
    {
        $sql = "INSERT INTO ingresos_asistencias
                (fecha, horaEntrada, estado, minutosRetardo, fk_aprendices_idAprendices)
                VALUES (:fecha, :hora, :estado, :retardo, :aprendiz)";
        $stmt = self::conn()->prepare($sql);
        $stmt->execute([
            ':fecha'     => $datos['fecha'],
            ':hora'      => $datos['horaEntrada'],
            ':estado'    => $datos['estado'],
            ':retardo'   => $datos['minutosRetardo'] ?? 0,
            ':aprendiz'  => $datos['fk_aprendices_idAprendices'],
        ]);
        return (int) self::conn()->lastInsertId();
    }

    /**
     * Registra la salida (segunda marcación del día).
     */
    public function registrarSalida(int $idIngreso, string $horaSalida,
                                     string $estado, int $minutosSalidaAnticipada): bool
    {
        $sql = "UPDATE ingresos_asistencias
                SET horaSalida = :salida, estado = :estado,
                    minutosSalidaAnticipada = :anticipada
                WHERE idIngreso = :id";
        $stmt = self::conn()->prepare($sql);
        $stmt->execute([
            ':salida'     => $horaSalida,
            ':estado'     => $estado,
            ':anticipada' => $minutosSalidaAnticipada,
            ':id'         => $idIngreso,
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Busca el ingreso de hoy para un aprendiz (para saber si ya marcó entrada).
     */
    public function buscarIngresoHoy(int $idAprendices): ?array
    {
        $sql = "SELECT idIngreso, fecha, horaEntrada, horaSalida, estado
                FROM ingresos_asistencias
                WHERE fk_aprendices_idAprendices = :aprendiz AND fecha = CURDATE()";
        $stmt = self::conn()->prepare($sql);
        $stmt->execute([':aprendiz' => $idAprendices]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Historial de asistencia de un aprendiz (para RF-03).
     */
    public function historialPorAprendiz(int $idAprendices,
                                          ?string $fechaInicio = null,
                                          ?string $fechaFin = null): array
    {
        $sql = "SELECT idIngreso, fecha, horaEntrada, horaSalida, estado,
                       minutosRetardo, minutosSalidaAnticipada
                FROM ingresos_asistencias
                WHERE fk_aprendices_idAprendices = :aprendiz";
        $params = [':aprendiz' => $idAprendices];

        if ($fechaInicio) {
            $sql .= " AND fecha >= :inicio";
            $params[':inicio'] = $fechaInicio;
        }
        if ($fechaFin) {
            $sql .= " AND fecha <= :fin";
            $params[':fin'] = $fechaFin;
        }

        $sql .= " ORDER BY fecha DESC, horaEntrada DESC";
        $stmt = self::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Lista ingresos por fecha (para reportes y vista del admin).
     */
    public function listarPorFecha(string $fecha, ?int $idFicha = null): array
    {
        $sql = "SELECT ia.idIngreso, ia.fecha, ia.horaEntrada, ia.horaSalida,
                       ia.estado, ia.minutosRetardo, ia.minutosSalidaAnticipada,
                       u.nombre, u.apellido, u.numDocumento,
                       f.codigoFicha
                FROM ingresos_asistencias ia
                INNER JOIN aprendices a ON a.idAprendices = ia.fk_aprendices_idAprendices
                INNER JOIN usuarios u   ON u.idUsuario    = a.fk_usuarios_idUsuario
                INNER JOIN fichas f     ON f.idFicha      = a.fk_fichas_idFicha
                WHERE ia.fecha = :fecha";
        $params = [':fecha' => $fecha];

        if ($idFicha) {
            $sql .= " AND a.fk_fichas_idFicha = :ficha";
            $params[':ficha'] = $idFicha;
        }

        $sql .= " ORDER BY u.apellido, u.nombre";
        $stmt = self::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Genera fila de inasistencia (usado por el job de RF-09).
     */
    public function insertarInasistencia(int $idAprendices, string $fecha): int
    {
        $sql = "INSERT INTO ingresos_asistencias
                (fecha, horaEntrada, horaSalida, estado, fk_aprendices_idAprendices)
                VALUES (:fecha, NULL, NULL, 'Inasistencia', :aprendiz)";
        $stmt = self::conn()->prepare($sql);
        $stmt->execute([':fecha' => $fecha, ':aprendiz' => $idAprendices]);
        return (int) self::conn()->lastInsertId();
    }
}
```

---

### 4.6 ExcusaModel.php

```php
<?php
require_once __DIR__ . '/Database.php';

class ExcusaModel extends Database
{
    /**
     * Crea una excusa médica (estado queda 'Pendiente',
     * instructor_usuarios_idUsuario queda NULL).
     */
    public function crear(array $datos): int
    {
        $sql = "INSERT INTO excusasmedicas
                (fechaInicio, fechaFin, motivo, archivoAdjunto, fechaSolicitud,
                 aprendices_idAprendices, ingresos_asistencias_idIngreso)
                VALUES (:ini, :fin, :motivo, :archivo, NOW(), :aprendiz, :ingreso)";
        $stmt = self::conn()->prepare($sql);
        $stmt->execute([
            ':ini'      => $datos['fechaInicio'],
            ':fin'      => $datos['fechaFin'],
            ':motivo'   => $datos['motivo'],
            ':archivo'  => $datos['archivoAdjunto'],
            ':aprendiz' => $datos['aprendices_idAprendices'],
            ':ingreso'  => $datos['ingresos_asistencias_idIngreso'],
        ]);
        return (int) self::conn()->lastInsertId();
    }

    /**
     * Excusas de un aprendiz (para RF-04: "Mis excusas").
     */
    public function listarPorAprendiz(int $idAprendices): array
    {
        $sql = "SELECT e.idExcusa, e.fechaInicio, e.fechaFin, e.motivo,
                       e.archivoAdjunto, e.estado, e.comentarioRevision, e.fechaSolicitud,
                       ia.fecha AS fechaIngreso, ia.estado AS estadoIngreso
                FROM excusasmedicas e
                INNER JOIN ingresos_asistencias ia ON ia.idIngreso = e.ingresos_asistencias_idIngreso
                WHERE e.aprendices_idAprendices = :aprendiz
                ORDER BY e.fechaSolicitud DESC";
        $stmt = self::conn()->prepare($sql);
        $stmt->execute([':aprendiz' => $idAprendices]);
        return $stmt->fetchAll();
    }

    /**
     * Excusas pendientes de revisión (para RF-12: instructor).
     */
    public function listarPendientes(): array
    {
        $sql = "SELECT e.idExcusa, e.fechaInicio, e.fechaFin, e.motivo,
                       e.archivoAdjunto, e.estado, e.fechaSolicitud,
                       u.nombre, u.apellido, u.numDocumento,
                       f.codigoFicha
                FROM excusasmedicas e
                INNER JOIN aprendices a ON a.idAprendices = e.aprendices_idAprendices
                INNER JOIN usuarios u   ON u.idUsuario    = a.fk_usuarios_idUsuario
                INNER JOIN fichas f     ON f.idFicha      = a.fk_fichas_idFicha
                WHERE e.estado = 'Pendiente'
                ORDER BY e.fechaSolicitud ASC";
        return self::conn()->query($sql)->fetchAll();
    }

    /**
     * Aprueba o rechaza una excusa (RF-12).
     * Llena instructor_usuarios_idUsuario y comentarioRevision.
     */
    public function revisar(int $idExcusa, string $nuevoEstado,
                            int $idInstructor, ?string $comentario): bool
    {
        $sql = "UPDATE excusasmedicas
                SET estado = :estado,
                    instructor_usuarios_idUsuario = :inst,
                    comentarioRevision = :com
                WHERE idExcusa = :id AND estado = 'Pendiente'";
        $stmt = self::conn()->prepare($sql);
        $stmt->execute([
            ':estado' => $nuevoEstado,
            ':inst'   => $idInstructor,
            ':com'    => $comentario,
            ':id'     => $idExcusa,
        ]);
        return $stmt->rowCount() > 0;
    }
}
```

---

### 4.7 ReporteModel.php

```php
<?php
require_once __DIR__ . '/Database.php';

class ReporteModel extends Database
{
    /**
     * Reporte de asistencia por rango de fechas, opcionalmente filtrado por ficha.
     */
    public function generarReporte(string $fechaInicio, string $fechaFin,
                                    ?int $idFicha = null): array
    {
        $sql = "SELECT ia.idIngreso, ia.fecha, ia.horaEntrada, ia.horaSalida,
                       ia.estado, ia.minutosRetardo, ia.minutosSalidaAnticipada,
                       u.numDocumento, u.nombre, u.apellido,
                       f.codigoFicha, f.nombrePrograma
                FROM ingresos_asistencias ia
                INNER JOIN aprendices a ON a.idAprendices = ia.fk_aprendices_idAprendices
                INNER JOIN usuarios u   ON u.idUsuario    = a.fk_usuarios_idUsuario
                INNER JOIN fichas f     ON f.idFicha      = a.fk_fichas_idFicha
                WHERE ia.fecha BETWEEN :inicio AND :fin";
        $params = [':inicio' => $fechaInicio, ':fin' => $fechaFin];

        if ($idFicha) {
            $sql .= " AND a.fk_fichas_idFicha = :ficha";
            $params[':ficha'] = $idFicha;
        }

        $sql .= " ORDER BY ia.fecha, u.apellido, u.nombre";
        $stmt = self::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Resumen estadístico: cuenta por estado en un rango.
     */
    public function resumenPorEstado(string $fechaInicio, string $fechaFin,
                                      ?int $idFicha = null): array
    {
        $sql = "SELECT ia.estado, COUNT(*) AS total
                FROM ingresos_asistencias ia
                INNER JOIN aprendices a ON a.idAprendices = ia.fk_aprendices_idAprendices
                WHERE ia.fecha BETWEEN :inicio AND :fin";
        $params = [':inicio' => $fechaInicio, ':fin' => $fechaFin];

        if ($idFicha) {
            $sql .= " AND a.fk_fichas_idFicha = :ficha";
            $params[':ficha'] = $idFicha;
        }

        $sql .= " GROUP BY ia.estado";
        $stmt = self::conn()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
```

---

## 5. Comunicación con otras carpetas

| Carpeta | Relación |
|---|---|
| `migrations/` | Define el esquema. Los modelos usan los nombres **exactos** de tablas y columnas de `001-sistema-ingresos.sql`. |
| `controllers/` | Los controladores instancian modelos y llaman sus métodos. El modelo devuelve datos; el controlador decide qué responder como JSON. |
| `views/` | **Ninguna relación directa.** Las vistas nunca importan ni instancian modelos. |
| `assets/js/` | **Ninguna relación directa.** El JS habla con la API (controladores), no con los modelos. |
| `jobs/` | El job `generarInasistencias.php` usa `IngresoModel` y `HorarioModel` directamente. |

---

## 6. Reglas de esta carpeta

1. **El servidor SOLO devuelve JSON.** Los modelos devuelven `array` o `null` al controlador; nunca hacen `echo` ni generan HTML.
2. **Las vistas son HTML estático:** cero `<?php`, cero SQL. Los modelos no las conocen.
3. **Toda consulta a la BD usa PDO con prepared statements** (`prepare()` + `execute([…])`). Nunca concatenar variables en SQL.
4. **Todo JS es módulo ES:** `export`/`import`, nada de funciones globales ni `onclick` inline. *(No aplica directamente a PHP, pero el contrato JSON que consumen los módulos depende de lo que devuelven los modelos.)*
5. **Todo endpoint sensible empieza con `requireAuth()` / `requireRol()`.** *(Responsabilidad del controlador, no del modelo.)*
6. **Soft delete siempre** (`estado='inactivo'`), nunca `DELETE` sobre `usuarios`/`aprendices`. Los modelos implementan `desactivar()` en lugar de `eliminar()` para estas tablas.
7. **Los nombres de tabla/columna son EXACTAMENTE los del esquema** — `idAprendices`, `fk_usuarios_idUsuario`, `ingresos_asistencias`, `excusasmedicas`, `codigoRfid`, `fk_fichas_idFicha`, etc. No los traduzcas ni los normalices a otro estilo.
