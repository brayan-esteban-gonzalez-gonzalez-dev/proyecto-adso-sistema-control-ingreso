<?php
declare(strict_types=1);

namespace Tests\Integration;

use Database;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Clase base para todas las pruebas de integración.
 *
 * Conecta con la base de datos local utilizando la conexión PDO singleton de Database.
 * Cada prueba se envuelve en una transacción atómica que se revierte en tearDown(),
 * asegurando aislamiento total y que ningún dato de prueba persista en la base de datos.
 */
abstract class IntegrationTestCase extends TestCase
{
    protected PDO $db;
    private static int $identificacionSecuencia = 10000;

    protected function setUp(): void
    {
        parent::setUp();

        $database = Database::getInstance();
        $conn = $database->getConnection();
        if ($conn === null) {
            $this->fail('No se pudo establecer la conexión PDO con la base de datos local.');
        }

        $this->db = $conn;

        // Iniciar transacción para aislamiento de datos de prueba
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
        }
    }

    protected function tearDown(): void
    {
        // Revertir todos los cambios realizados durante la prueba
        if (isset($this->db) && $this->db->inTransaction()) {
            $this->db->rollBack();
        }

        parent::tearDown();
    }

    // =========================================================================
    // Métodos Helper para creación rápida de Fixtures en la BD de pruebas
    // =========================================================================

    protected function generarIdentificacion(): string
    {
        return (string) mt_rand(200000000, 999999999);
    }

    protected function crearPrograma(string $nombre = 'Analisis y Desarrollo de Software', string $codigo = 'ADSO'): int
    {
        $codigoInt = (string) mt_rand(100000, 999999);
        $stmt = $this->db->prepare("INSERT INTO programa (codigo, nombre, descripcion) VALUES (:codigo, :nombre, :descripcion)");
        $stmt->execute([
            ':codigo'      => $codigoInt,
            ':nombre'      => $nombre . '_' . uniqid(),
            ':descripcion' => 'Programa de prueba para integración',
        ]);
        return (int) $this->db->lastInsertId();
    }

    protected function crearJornada(string $nombre = 'Mañana'): int
    {
        $stmt = $this->db->prepare("SELECT id FROM jornada WHERE nombre = :nombre LIMIT 1");
        $stmt->execute([':nombre' => $nombre]);
        $row = $stmt->fetch();
        if ($row) {
            return (int) $row['id'];
        }

        $stmt = $this->db->prepare("INSERT INTO jornada (nombre) VALUES (:nombre)");
        $stmt->execute([':nombre' => $nombre]);
        return (int) $this->db->lastInsertId();
    }

    protected function crearCompetencia(?int $programaId = null, ?int $instructorId = null, string $nombre = 'Competencia de Prueba'): int
    {
        $progId = $programaId ?? $this->crearPrograma();
        $instId = $instructorId ?? $this->crearUsuario(2);

        $stmt = $this->db->prepare("INSERT INTO competencia (Programa_id, Instructor_id, nombre, descripcion) VALUES (:prog_id, :inst_id, :nombre, :descripcion)");
        $stmt->execute([
            ':prog_id'     => $progId,
            ':inst_id'     => $instId,
            ':nombre'      => $nombre . '_' . uniqid(),
            ':descripcion' => 'Descripción de competencia de prueba',
        ]);
        return (int) $this->db->lastInsertId();
    }

    protected function crearFicha(?int $codigo = null, ?int $programaId = null, ?int $instructorLiderId = null, ?int $jornadaId = null): int
    {
        $progId = $programaId ?? $this->crearPrograma();
        $jornId = $jornadaId ?? $this->crearJornada();
        $codFicha = $codigo ?? mt_rand(1000000, 9999999);

        // Si no se pasó instructor líder, crear un instructor para la FK
        $instId = $instructorLiderId;
        if ($instId === null) {
            $instId = $this->crearUsuario(2);
        }

        $stmt = $this->db->prepare("INSERT INTO ficha (codigo, Programa_id, jornada_id, instructor_id, estado)
                                    VALUES (:codigo, :prog_id, :jorn_id, :inst_id, 'Activo')");
        $stmt->execute([
            ':codigo'  => $codFicha,
            ':prog_id' => $progId,
            ':jorn_id' => $jornId,
            ':inst_id' => $instId,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /**
     * @param int $rolId 1=Admin, 2=Instructor, 3=Aprendiz
     */
    protected function crearUsuario(int $rolId, array $overrides = []): int
    {
        $unique = uniqid();
        $identificacion = (string) ($overrides['identificacion'] ?? $this->generarIdentificacion());
        $nombre = $overrides['nombre'] ?? 'Nombre_' . $unique;
        $apellido = $overrides['apellido'] ?? 'Apellido_' . $unique;
        $email = $overrides['email'] ?? 'test_' . $unique . '@sena.edu.co';
        $password = password_hash($overrides['password'] ?? '12345678', PASSWORD_BCRYPT);
        $fichaId = $overrides['Ficha_id'] ?? null;
        $codigoLlavero = $overrides['codigo_llavero'] ?? null;
        $estado = $overrides['estado'] ?? 'Activo';

        $stmt = $this->db->prepare("INSERT INTO usuario (Rol_id, identificacion, nombre, apellido, email, password, Ficha_id, codigo_llavero, estado)
                                    VALUES (:rol_id, :identificacion, :nombre, :apellido, :email, :password, :ficha_id, :llavero, :estado)");
        $stmt->execute([
            ':rol_id'         => $rolId,
            ':identificacion' => $identificacion,
            ':nombre'         => $nombre,
            ':apellido'       => $apellido,
            ':email'          => $email,
            ':password'       => $password,
            ':ficha_id'       => $fichaId,
            ':llavero'        => $codigoLlavero,
            ':estado'         => $estado,
        ]);

        return (int) $this->db->lastInsertId();
    }

    protected function crearSesion(int $fichaId, int $instructorId, array $overrides = []): int
    {
        $compId = $overrides['Competencia_id'] ?? null;
        if ($compId === null) {
            $compId = $this->crearCompetencia(null, $instructorId);
        }

        $fecha = $overrides['fecha'] ?? date('Y-m-d');
        $horaInicio = $overrides['hora_inicio'] ?? '07:00:00';
        $horaFin = $overrides['hora_fin'] ?? '13:00:00';
        $estado = $overrides['estado'] ?? 'Programado';
        $aperturaReal = $overrides['apertura_real'] ?? null;
        $cierreReal = $overrides['cierre_real'] ?? null;

        $stmt = $this->db->prepare("INSERT INTO sesion (Ficha_id, Instructor_id, Competencia_id, fecha, hora_inicio, hora_fin, estado, apertura_real, cierre_real)
                                    VALUES (:ficha_id, :inst_id, :comp_id, :fecha, :hora_inicio, :hora_fin, :estado, :apertura, :cierre)");
        $stmt->execute([
            ':ficha_id'    => $fichaId,
            ':inst_id'     => $instructorId,
            ':comp_id'     => $compId,
            ':fecha'       => $fecha,
            ':hora_inicio' => $horaInicio,
            ':hora_fin'    => $horaFin,
            ':estado'      => $estado,
            ':apertura'    => $aperturaReal,
            ':cierre'      => $cierreReal,
        ]);

        return (int) $this->db->lastInsertId();
    }

    protected function crearAsistencia(int $usuarioId, int $sesionId, array $overrides = []): int
    {
        $fecha = $overrides['fecha'] ?? date('Y-m-d');
        $horaEntrada = $overrides['hora_entrada'] ?? date('H:i:s');
        $horaSalida = $overrides['hora_salida'] ?? null;
        $registradoPor = $overrides['registrado_por'] ?? $usuarioId;
        $estado = $overrides['estado'] ?? 'Activo';
        $llavero = $overrides['codigo_llavero'] ?? null;
        $minutosRetardo = $overrides['minutos_retardo'] ?? 0;
        $minutosAnticipacion = $overrides['minutos_anticipacion'] ?? 0;

        $stmt = $this->db->prepare("INSERT INTO asistencia (fecha, hora_entrada, hora_salida, Usuario_id, Sesion_id, registrado_por, estado, codigo_llavero, minutos_retardo, minutos_anticipacion)
                                    VALUES (:fecha, :hora_entrada, :hora_salida, :usuario_id, :sesion_id, :reg_por, :estado, :llavero, :retardo, :anticipacion)");
        $stmt->execute([
            ':fecha'        => $fecha,
            ':hora_entrada' => $horaEntrada,
            ':hora_salida'  => $horaSalida,
            ':usuario_id'   => $usuarioId,
            ':sesion_id'    => $sesionId,
            ':reg_por'      => $registradoPor,
            ':estado'       => $estado,
            ':llavero'      => $llavero,
            ':retardo'      => $minutosRetardo,
            ':anticipacion' => $minutosAnticipacion,
        ]);

        return (int) $this->db->lastInsertId();
    }
}
