<?php
declare(strict_types=1);

namespace Tests\Integration;

require_once __DIR__ . '/IntegrationTestCase.php';

use IngresoAsistencia;
use Usuario;

/**
 * Pruebas de Integración para el modelo IngresoAsistencia (models/IngresoAsistencia.php)
 *
 * Valida el registro, validación y consulta de asistencias en la base de datos real:
 * - Regla de tolerancia de 10 minutos (Asistió a tiempo vs Retardo con cálculo de minutos)
 * - Registro de salida
 * - Detección de duplicados en la misma sesión formativa
 * - Búsqueda y validación estricta de nómina por Documento e ID (pertenencia a Ficha)
 * - Estadísticas e historial del aprendiz
 */
class IngresoAsistenciaIntegrationTest extends IntegrationTestCase
{
    private IngresoAsistencia $asistenciaModel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->asistenciaModel = new IngresoAsistencia($this->db);
    }

    public function testRegistrarEntradaCaminoFeliz(): void
    {
        $ficha = $this->crearFicha();
        $instructor = $this->crearUsuario(Usuario::ROL_INSTRUCTOR);
        $aprendiz = $this->crearUsuario(Usuario::ROL_APRENDIZ, ['Ficha_id' => $ficha]);
        $sesion = $this->crearSesion($ficha, $instructor);

        $ok = $this->asistenciaModel->registrarEntrada($aprendiz, $sesion, $instructor, 'RFID_TEST_123');
        $this->assertTrue($ok);

        $stmt = $this->db->prepare("SELECT * FROM asistencia WHERE Usuario_id = :uid AND Sesion_id = :sid");
        $stmt->execute([':uid' => $aprendiz, ':sid' => $sesion]);
        $registro = $stmt->fetch();

        $this->assertNotEmpty($registro);
        $this->assertSame('Activo', $registro['estado']);
        $this->assertSame('RFID_TEST_123', $registro['codigo_llavero']);
        $this->assertSame(date('Y-m-d'), $registro['fecha']);
    }

    public function testRegistrarEntradaConRetardoAplicaReglaDe10Minutos(): void
    {
        $ficha = $this->crearFicha();
        $instructor = $this->crearUsuario(Usuario::ROL_INSTRUCTOR);
        $aprendizATiempo = $this->crearUsuario(Usuario::ROL_APRENDIZ, ['Ficha_id' => $ficha]);
        $aprendizTarde   = $this->crearUsuario(Usuario::ROL_APRENDIZ, ['Ficha_id' => $ficha]);
        $sesion = $this->crearSesion($ficha, $instructor);

        // Caso 1: Hora inicio igual a la hora actual -> Dentro de los 10 minutos de tolerancia
        $horaActual = date('H:i:s');
        $resATiempo = $this->asistenciaModel->registrarEntradaConRetardo(
            $aprendizATiempo,
            $sesion,
            $instructor,
            $horaActual
        );

        $this->assertTrue($resATiempo['ok']);
        $this->assertSame('Asistió', $resATiempo['estado']);
        $this->assertSame(0, $resATiempo['minutos_retardo']);

        // Caso 2: Hora inicio fijada hace 40 minutos -> Excede la tolerancia de 10 min
        $horaInicioPasada = (new \DateTime())->modify('-40 minutes')->format('H:i:s');
        $resTarde = $this->asistenciaModel->registrarEntradaConRetardo(
            $aprendizTarde,
            $sesion,
            $instructor,
            $horaInicioPasada
        );

        $this->assertTrue($resTarde['ok']);
        $this->assertSame('Retardo', $resTarde['estado']);
        $this->assertGreaterThanOrEqual(25, $resTarde['minutos_retardo']);
    }

    public function testRegistrarSalidaActualizaEstadoYHora(): void
    {
        $ficha = $this->crearFicha();
        $instructor = $this->crearUsuario(Usuario::ROL_INSTRUCTOR);
        $aprendiz = $this->crearUsuario(Usuario::ROL_APRENDIZ, ['Ficha_id' => $ficha]);
        $sesion = $this->crearSesion($ficha, $instructor);

        $asistenciaId = $this->crearAsistencia($aprendiz, $sesion, ['estado' => 'Activo']);

        $res = $this->asistenciaModel->registrarSalida($asistenciaId);
        $this->assertTrue($res);

        $stmt = $this->db->prepare("SELECT estado, hora_salida FROM asistencia WHERE id = :id");
        $stmt->execute([':id' => $asistenciaId]);
        $actualizado = $stmt->fetch();

        $this->assertSame('Completado', $actualizado['estado']);
        $this->assertNotNull($actualizado['hora_salida']);
    }

    public function testExisteRegistroEnSesionDetectaMarcacionesPrevias(): void
    {
        $ficha = $this->crearFicha();
        $instructor = $this->crearUsuario(Usuario::ROL_INSTRUCTOR);
        $aprendiz = $this->crearUsuario(Usuario::ROL_APRENDIZ, ['Ficha_id' => $ficha]);
        $sesion = $this->crearSesion($ficha, $instructor);

        $this->assertFalse($this->asistenciaModel->existeRegistroEnSesion($aprendiz, $sesion));

        $this->crearAsistencia($aprendiz, $sesion);

        $this->assertTrue($this->asistenciaModel->existeRegistroEnSesion($aprendiz, $sesion));
    }

    public function testBuscarAprendizPorDocumentoValidaPertenenciaAFichaYEstados(): void
    {
        $fichaActiva = $this->crearFicha();
        $otraFicha   = $this->crearFicha();

        $docActivoValido = $this->generarIdentificacion();
        $idValido = $this->crearUsuario(Usuario::ROL_APRENDIZ, [
            'identificacion' => $docActivoValido,
            'Ficha_id'       => $fichaActiva,
            'estado'         => 'Activo',
        ]);

        $docOtraFicha = $this->generarIdentificacion();
        $this->crearUsuario(Usuario::ROL_APRENDIZ, [
            'identificacion' => $docOtraFicha,
            'Ficha_id'       => $otraFicha,
            'estado'         => 'Activo',
        ]);

        $docInactivo = $this->generarIdentificacion();
        $this->crearUsuario(Usuario::ROL_APRENDIZ, [
            'identificacion' => $docInactivo,
            'Ficha_id'       => $fichaActiva,
            'estado'         => 'Inactivo',
        ]);

        // Caso 1: Aprendiz activo de la ficha activa -> Éxito
        $resValido = $this->asistenciaModel->buscarAprendizPorDocumento($docActivoValido, $fichaActiva);
        $this->assertTrue($resValido['ok']);
        $this->assertSame($idValido, (int)$resValido['aprendiz']['id']);

        // Caso 2: Aprendiz activo pero matriculado en otra ficha -> Error wrong_ficha
        $resOtraFicha = $this->asistenciaModel->buscarAprendizPorDocumento($docOtraFicha, $fichaActiva);
        $this->assertFalse($resOtraFicha['ok']);
        $this->assertSame('wrong_ficha', $resOtraFicha['error']);

        // Caso 3: Aprendiz inactivo -> Error inactive
        $resInactivo = $this->asistenciaModel->buscarAprendizPorDocumento($docInactivo, $fichaActiva);
        $this->assertFalse($resInactivo['ok']);
        $this->assertSame('inactive', $resInactivo['error']);

        // Caso 4: Documento inexistente -> Error not_found
        $resNoExiste = $this->asistenciaModel->buscarAprendizPorDocumento('0000000000', $fichaActiva);
        $this->assertFalse($resNoExiste['ok']);
        $this->assertSame('not_found', $resNoExiste['error']);
    }

    public function testBuscarAprendizPorIdValidaPertenenciaAFicha(): void
    {
        $fichaActiva = $this->crearFicha();
        $otraFicha   = $this->crearFicha();

        $idValido = $this->crearUsuario(Usuario::ROL_APRENDIZ, [
            'Ficha_id' => $fichaActiva,
            'estado'   => 'Activo',
        ]);

        $idOtraFicha = $this->crearUsuario(Usuario::ROL_APRENDIZ, [
            'Ficha_id' => $otraFicha,
            'estado'   => 'Activo',
        ]);

        $resOk = $this->asistenciaModel->buscarAprendizPorId($idValido, $fichaActiva);
        $this->assertTrue($resOk['ok']);

        $resFallo = $this->asistenciaModel->buscarAprendizPorId($idOtraFicha, $fichaActiva);
        $this->assertFalse($resFallo['ok']);
        $this->assertSame('wrong_ficha', $resFallo['error']);

        $resInvalido = $this->asistenciaModel->buscarAprendizPorId(0, $fichaActiva);
        $this->assertFalse($resInvalido['ok']);
        $this->assertSame('invalid_id', $resInvalido['error']);
    }

    public function testGetEstadisticasAprendizCalculaRetardosYAsistencias(): void
    {
        $ficha = $this->crearFicha();
        $instructor = $this->crearUsuario(Usuario::ROL_INSTRUCTOR);
        $aprendiz = $this->crearUsuario(Usuario::ROL_APRENDIZ, ['Ficha_id' => $ficha]);

        $s1 = $this->crearSesion($ficha, $instructor, ['fecha' => '2026-11-01']);
        $s2 = $this->crearSesion($ficha, $instructor, ['fecha' => '2026-11-02']);

        // 1 asistencia a tiempo, 1 con 15 minutos de retardo
        $this->crearAsistencia($aprendiz, $s1, ['fecha' => '2026-11-01', 'minutos_retardo' => 0]);
        $this->crearAsistencia($aprendiz, $s2, ['fecha' => '2026-11-02', 'minutos_retardo' => 15]);

        $stats = $this->asistenciaModel->getEstadisticasAprendiz($aprendiz, '2026-11-01', '2026-11-02');

        $this->assertSame(1, $stats['total_a_tiempo']);
        $this->assertSame(1, $stats['total_retardos']);
        $this->assertSame(15, $stats['total_minutos_retardo']);
    }
}

