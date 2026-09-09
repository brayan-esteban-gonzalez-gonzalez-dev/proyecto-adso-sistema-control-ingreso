<?php 
$loadRfidJs = true;
require_once ROOT_PATH . '/views/layouts/header.php'; 
?>

<script>const BASE_URL = '<?= BASE_URL ?>';</script>

<div class="rfid-container">
    <!-- Reloj en vivo -->
    <div class="rfid-clock">
        <div class="rfid-clock-time" id="rfidClockTime">--:--:--</div>
        <div class="rfid-clock-date" id="rfidClockDate"></div>
    </div>

    <!-- Tarjeta de escaneo -->
    <div class="rfid-scan-card" id="rfidScanCard">
        <div class="rfid-icon">
            <i class="fas fa-wifi"></i>
        </div>
        <p class="rfid-instruction">Acerque el llavero RFID al lector o ingrese el código manualmente</p>
        
        <input type="text" 
               id="rfidInput" 
               class="rfid-input" 
               placeholder="Código RFID" 
               autocomplete="off"
               autofocus>

        <!-- Resultado de la marcación -->
        <div class="rfid-result" id="rfidResult">
            <div class="rfid-result-message" id="rfidResultMessage"></div>
            <div class="rfid-result-detail" id="rfidResultDetail"></div>
        </div>
    </div>

    <!-- Ingresos recientes de hoy -->
    <div class="rfid-recent mt-3">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-list"></i> Marcaciones de Hoy
                </h2>
                <span class="badge badge-blue"><?= count($ultimosIngresos) ?> registros</span>
            </div>

            <?php if (!empty($ultimosIngresos)): ?>
            <div class="table-wrapper">
                <table class="data-table" id="tablaIngresosRecientes">
                    <thead>
                        <tr>
                            <th>Aprendiz</th>
                            <th>Documento</th>
                            <th>Ficha</th>
                            <th>Entrada</th>
                            <th>Salida</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ultimosIngresos as $ing): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($ing['nombre'] . ' ' . $ing['apellido']) ?></strong></td>
                            <td><?= htmlspecialchars($ing['num_documento']) ?></td>
                            <td><?= htmlspecialchars($ing['codigo_ficha']) ?></td>
                            <td><?= $ing['hora_entrada'] ?? '—' ?></td>
                            <td><?= $ing['hora_salida'] ?? '—' ?></td>
                            <td>
                                <span class="badge badge-<?= strtolower($ing['estado']) ?>">
                                    <?= str_replace('_', ' ', $ing['estado']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>Sin marcaciones aún hoy</p>
                <small>Las marcaciones aparecerán aquí en tiempo real</small>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
