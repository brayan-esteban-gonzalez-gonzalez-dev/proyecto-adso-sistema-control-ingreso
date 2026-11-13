<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Un usuario de ejemplo por rol, sobre una conexión simulada (SQLite en memoria).
 * No abre MySQL. La clave en claro solo vive en el arreglo de ejemplo;
 * en la tabla queda el hash de password_hash(), como en Usuario::create().
 */
class UsuarioEjemplosPorRolTest extends TestCase
{
    private PDO $db;

    private Usuario $usuarios;

    /** @var array<int, array{rol: string, nombre: string, apellido: string, identificacion: string, password: string, email: string, Rol_id: int}> */
    private array $ejemplos = [
        [
            'rol'            => 'Administrador',
            'Rol_id'         => Usuario::ROL_ADMINISTRADOR,
            'nombre'         => 'Elena',
            'apellido'       => 'Vargas',
            'identificacion' => '1001001001',
            'email'          => 'elena.vargas@sena.edu.co',
            'password'       => 'Admin2026',
        ],
        [
            'rol'            => 'Instructor',
            'Rol_id'         => Usuario::ROL_INSTRUCTOR,
            'nombre'         => 'Marco',
            'apellido'       => 'Ruiz',
            'identificacion' => '1002002002',
            'email'          => 'marco.ruiz@sena.edu.co',
            'password'       => 'Inst2026',
        ],
        [
            'rol'            => 'Aprendiz',
            'Rol_id'         => Usuario::ROL_APRENDIZ,
            'nombre'         => 'Laura',
            'apellido'       => 'Pena',
            'identificacion' => '1003003003',
            'email'          => 'laura.pena@sena.edu.co',
            'password'       => 'Aprendiz2026',
        ],
    ];

    protected function setUp(): void
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->db->exec(
            "CREATE TABLE rol (
                id INTEGER PRIMARY KEY,
                nombre TEXT NOT NULL
            )"
        );
        $this->db->exec(
            "INSERT INTO rol (id, nombre) VALUES
                (1, 'Administrador'),
                (2, 'Instructor'),
                (3, 'Aprendiz')"
        );
        $this->db->exec(
            "CREATE TABLE usuario (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre TEXT NOT NULL,
                apellido TEXT NOT NULL,
                identificacion INTEGER NOT NULL UNIQUE,
                email TEXT NOT NULL,
                password TEXT NOT NULL,
                Rol_id INTEGER NOT NULL,
                Ficha_id INTEGER DEFAULT NULL,
                codigo_llavero TEXT DEFAULT NULL,
                estado TEXT NOT NULL DEFAULT 'Activo'
            )"
        );
        $this->usuarios = new Usuario($this->db);
    }

    public function testCreaUnEjemploPorRolYGuardaElHash(): void
    {
        foreach ($this->ejemplos as $datos) {
            $this->assertTrue($this->usuarios->create($datos), 'No se pudo crear el ' . $datos['rol']);

            $guardado = $this->usuarios->getByDocumento($datos['identificacion']);
            $this->assertNotNull($guardado);
            $this->assertSame($datos['rol'], $guardado['nombre_rol']);
            $this->assertSame($datos['nombre'], $guardado['nombre']);
            $this->assertNotSame($datos['password'], $guardado['password']);
            $this->assertTrue(password_verify($datos['password'], $guardado['password']));
            $this->assertStringStartsWith('$2y$', $guardado['password']);

            $autenticado = $this->usuarios->authenticate($datos['identificacion'], $datos['password']);
            $this->assertNotNull($autenticado);
            $this->assertSame((int) $datos['Rol_id'], (int) $autenticado['Rol_id']);
            $this->assertNull($this->usuarios->authenticate($datos['identificacion'], 'clave-incorrecta'));
        }

        $conteo = $this->usuarios->getCountsByRole();
        $this->assertSame(1, $conteo['administradores']);
        $this->assertSame(1, $conteo['instructores']);
        $this->assertSame(1, $conteo['aprendices']);
        $this->assertSame(3, $conteo['total']);
    }
}
