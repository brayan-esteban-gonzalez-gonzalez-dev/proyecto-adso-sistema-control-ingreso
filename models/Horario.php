<?php
/**
 * Horario.php — Modelo de Horarios
 * 
 * CRUD para la tabla `horarios` por ficha.
 */
class Horario {
    private PDO $conn;
    private string $table = 'horarios';

    public function __construct(PDO $db) {
        $this->conn = $db;
    }

    /**
     * Obtiene todos los horarios de una ficha
     */
    public function getByFicha(int $idFicha): array {
        $sql = "SELECT * FROM {$this->table}
                WHERE id_ficha = :id_ficha
                ORDER BY FIELD(dia_semana, 'Lunes','Martes','Miercoles','Jueves','Viernes','Sabado')";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id_ficha' => $idFicha]);
        return $stmt->fetchAll();
    }

    /**
     * Obtiene un horario por su ID
     */
    public function getById(int $id): ?array {
        $sql = "SELECT h.*, f.codigo_ficha, f.nombre_programa
                FROM {$this->table} h
                INNER JOIN fichas f ON h.id_ficha = f.id_ficha
                WHERE h.id_horario = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Obtiene el horario de hoy para una ficha determinada
     */
    public function getHorarioHoy(int $idFicha): ?array {
        $dias = [
            1 => 'Lunes', 2 => 'Martes', 3 => 'Miercoles',
            4 => 'Jueves', 5 => 'Viernes', 6 => 'Sabado'
        ];
        $numDia = (int) date('N');
        if (!isset($dias[$numDia])) {
            return null; // Domingo
        }
        $diaSemana = $dias[$numDia];

        $sql = "SELECT * FROM {$this->table}
                WHERE id_ficha = :id_ficha AND dia_semana = :dia";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id_ficha' => $idFicha, ':dia' => $diaSemana]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Crea un nuevo horario
     */
    public function create(array $data): bool {
        $sql = "INSERT INTO {$this->table} (id_ficha, dia_semana, hora_entrada, hora_salida, tolerancia_minutos)
                VALUES (:id_ficha, :dia_semana, :hora_entrada, :hora_salida, :tolerancia_minutos)";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':id_ficha'            => $data['id_ficha'],
            ':dia_semana'          => $data['dia_semana'],
            ':hora_entrada'        => $data['hora_entrada'],
            ':hora_salida'         => $data['hora_salida'],
            ':tolerancia_minutos'  => $data['tolerancia_minutos'] ?? 15,
        ]);
    }

    /**
     * Actualiza un horario existente
     */
    public function update(int $id, array $data): bool {
        $sql = "UPDATE {$this->table}
                SET dia_semana = :dia_semana,
                    hora_entrada = :hora_entrada,
                    hora_salida = :hora_salida,
                    tolerancia_minutos = :tolerancia_minutos
                WHERE id_horario = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':id'                  => $id,
            ':dia_semana'          => $data['dia_semana'],
            ':hora_entrada'        => $data['hora_entrada'],
            ':hora_salida'         => $data['hora_salida'],
            ':tolerancia_minutos'  => $data['tolerancia_minutos'] ?? 15,
        ]);
    }

    /**
     * Elimina un horario por su ID
     */
    public function delete(int $id): bool {
        $sql = "DELETE FROM {$this->table} WHERE id_horario = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }
}
