<?php
/**
 * api/rfid.php — Endpoint API REST para lector RFID físico
 * 
 * Recibe peticiones POST con el UID del llavero RFID
 * y retorna el resultado en JSON.
 * 
 * Uso: POST /api/rfid.php
 * Body: {"rfid_uid": "AB12CD34"} o form-data: rfid_uid=AB12CD34
 * Response: JSON con resultado de la marcación
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Manejar preflight CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido. Use POST.']);
    exit;
}

// ─── Cargar dependencias ───
define('ROOT_PATH', dirname(__DIR__));

require_once ROOT_PATH . '/config/Database.php';
require_once ROOT_PATH . '/helpers/Auth.php';
require_once ROOT_PATH . '/models/Aprendiz.php';
require_once ROOT_PATH . '/models/Horario.php';
require_once ROOT_PATH . '/models/IngresoAsistencia.php';

// ─── Obtener el código RFID ───
$input = json_decode(file_get_contents('php://input'), true);
$codigoRfid = $input['rfid_uid'] ?? $_POST['rfid_uid'] ?? $_POST['codigo_rfid'] ?? '';
$codigoRfid = trim($codigoRfid);

if (empty($codigoRfid)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Código RFID vacío.']);
    exit;
}

// ─── Conexión a BD ───
$db = Database::getInstance()->getConnection();

$aprendizModel = new Aprendiz($db);
$horarioModel  = new Horario($db);
$ingresoModel  = new IngresoAsistencia($db);

// ─── Buscar aprendiz ───
$aprendiz = $aprendizModel->getByRfid($codigoRfid);

if (!$aprendiz) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'RFID no registrado.']);
    exit;
}

// ─── Obtener horario del día ───
$horario = $horarioModel->getHorarioHoy($aprendiz['id_ficha']);

if (!$horario) {
    echo json_encode([
        'success'   => false,
        'message'   => 'Sin horario para hoy.',
        'aprendiz'  => $aprendiz['nombre'] . ' ' . $aprendiz['apellido'],
    ]);
    exit;
}

$fecha      = date('Y-m-d');
$horaActual = date('H:i:s');
$ingresoHoy = $ingresoModel->getIngresoHoy($aprendiz['id_aprendiz']);

if (!$ingresoHoy) {
    // Registrar entrada
    $entrada    = strtotime($horario['hora_entrada']);
    $actual     = strtotime($horaActual);
    $tolerancia = (int) $horario['tolerancia_minutos'];
    $limite     = $entrada + ($tolerancia * 60);

    if ($actual <= $limite) {
        $estado = 'A_Tiempo';
        $minutosRetardo = 0;
    } else {
        $estado = 'Retardo';
        $minutosRetardo = (int) ceil(($actual - $entrada) / 60);
    }

    $ingresoModel->registrarEntrada($aprendiz['id_aprendiz'], $fecha, $horaActual, $estado, $minutosRetardo);

    echo json_encode([
        'success'   => true,
        'accion'    => 'entrada',
        'estado'    => $estado,
        'retardo'   => $minutosRetardo,
        'aprendiz'  => $aprendiz['nombre'] . ' ' . $aprendiz['apellido'],
        'documento' => $aprendiz['num_documento'],
        'ficha'     => $aprendiz['codigo_ficha'],
        'hora'      => $horaActual,
        'message'   => $estado === 'A_Tiempo' ? 'Entrada a tiempo' : "Retardo de {$minutosRetardo} minutos",
    ]);

} elseif ($ingresoHoy['hora_salida'] === null) {
    // Registrar salida
    $salida = strtotime($horario['hora_salida']);
    $actual = strtotime($horaActual);
    $minutosAnticipada = $actual < $salida ? (int) ceil(($salida - $actual) / 60) : 0;

    $ingresoModel->registrarSalida($ingresoHoy['id_ingreso'], $horaActual, $minutosAnticipada);

    echo json_encode([
        'success'    => true,
        'accion'     => 'salida',
        'anticipada' => $minutosAnticipada,
        'aprendiz'   => $aprendiz['nombre'] . ' ' . $aprendiz['apellido'],
        'hora'       => $horaActual,
        'message'    => $minutosAnticipada > 0 ? "Salida anticipada ({$minutosAnticipada} min)" : 'Salida normal',
    ]);

} else {
    echo json_encode([
        'success' => false,
        'message' => 'Ya tiene registro completo hoy.',
        'aprendiz' => $aprendiz['nombre'] . ' ' . $aprendiz['apellido'],
    ]);
}
