<?php 
$loadRfidJs = true;
require_once ROOT_PATH . '/views/layouts/header.php'; 
?>

<script>
    const BASE_URL   = '<?= BASE_URL ?>';
    const CSRF_TOKEN = '<?= htmlspecialchars($csrfToken ?? '') ?>';
    const FICHAS_DATA = <?= json_encode(array_map(function($f) use ($sesionActivaPorFicha) {
        $fId = (int)$f['id'];
        $sesion = $sesionActivaPorFicha[$fId] ?? null;
        return [
            'id'              => $fId,
            'codigo'          => $f['codigo'] ?? '',
            'nombre_programa' => $f['nombre_programa'] ?? 'Sin programa',
            'sesion_activa'   => $sesion ? [
                'id'          => (int)$sesion['id'],
                'hora_inicio' => $sesion['hora_inicio'],
            ] : null,
        ];
    }, $fichas ?? [])) ?>;
</script>

<div class="rfid-container">
    <!-- Reloj en vivo -->
    <div class="rfid-clock">
        <div class="rfid-clock-time" id="rfidClockTime">--:--:--</div>
        <div class="rfid-clock-date" id="rfidClockDate"></div>
    </div>

    <!-- ═══ Panel de Fichas con Toggle ═══ -->
    <div class="rfid-fichas-panel" id="rfidFichasPanel">
        <div class="card">
            <div class="card-header" style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
                <h2 style="font-size:1rem; font-weight:700; color:var(--text-primary); margin:0;">
                    <i class="fas fa-layer-group" style="color:var(--accent-blue); margin-right:6px;"></i>
                    Mis Fichas — Control de Asistencia
                </h2>
                <span class="badge badge-blue" id="fichasCount">0 fichas</span>
            </div>

            <?php if (empty($fichas)): ?>
            <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <p>No tienes fichas asignadas</p>
                <small>Contacta al administrador para vincular fichas a tu perfil</small>
            </div>
            <?php else: ?>
            <div class="rfid-fichas-grid" id="rfidFichasGrid">
                <?php foreach ($fichas as $ficha):
                    $fichaId       = (int)$ficha['id'];
                    $sesionActiva  = $sesionActivaPorFicha[$fichaId] ?? null;
                    $isActive      = $sesionActiva !== null;
                    $sesionIdAttr  = $isActive ? (int)$sesionActiva['id'] : '';
                ?>
                <div class="rfid-ficha-card <?= $isActive ? 'rfid-ficha-active' : '' ?>"
                     data-ficha-id="<?= $fichaId ?>"
                     data-sesion-id="<?= $sesionIdAttr ?>"
                     id="fichaCard_<?= $fichaId ?>">
                    <div class="rfid-ficha-info">
                        <span class="rfid-ficha-code"><?= htmlspecialchars((string)$ficha['codigo']) ?></span>
                        <span class="rfid-ficha-program"><?= htmlspecialchars($ficha['nombre_programa'] ?? 'Sin programa') ?></span>
                    </div>

                    <div class="rfid-ficha-controls">
                        <!-- Badge de estado -->
                        <span class="rfid-status-badge <?= $isActive ? 'rfid-status-active' : 'rfid-status-inactive' ?>"
                              id="statusBadge_<?= $fichaId ?>">
                            <?php if ($isActive): ?>
                                <i class="fas fa-circle rfid-pulse-dot"></i> Recibiendo RFID…
                            <?php else: ?>
                                <i class="fas fa-circle-stop"></i> Apagada
                            <?php endif; ?>
                        </span>

                        <!-- Toggle Button -->
                        <button class="rfid-toggle-btn <?= $isActive ? 'rfid-toggle-off' : 'rfid-toggle-on' ?>"
                                id="toggleBtn_<?= $fichaId ?>"
                                data-ficha-id="<?= $fichaId ?>"
                                data-sesion-id="<?= $sesionIdAttr ?>"
                                onclick="toggleSesionAsistencia(<?= $fichaId ?>)">
                            <?php if ($isActive): ?>
                                <i class="fas fa-power-off"></i> Apagar Asistencia
                            <?php else: ?>
                                <i class="fas fa-power-off"></i> Encender Asistencia
                            <?php endif; ?>
                        </button>
                    </div>

                    <!-- Hora de inicio si activa -->
                    <?php if ($isActive): ?>
                    <div class="rfid-ficha-meta" id="fichaMeta_<?= $fichaId ?>">
                        <small><i class="fas fa-clock"></i> Iniciada a las <?= htmlspecialchars($sesionActiva['hora_inicio']) ?></small>
                    </div>
                    <?php else: ?>
                    <div class="rfid-ficha-meta" id="fichaMeta_<?= $fichaId ?>" style="display:none;"></div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══ Input RFID oculto (captura datos del lector USB) ═══ -->
    <input type="text"
           id="rfidInput"
           class="rfid-hidden-input"
           autocomplete="off"
           autofocus
           tabindex="-1"
           aria-label="Lector RFID">

    <!-- ═══ Resultado de marcación (feedback visual) ═══ -->
    <div class="rfid-feedback" id="rfidFeedback" style="display:none;">
        <div class="rfid-feedback-inner" id="rfidFeedbackInner">
            <div class="rfid-feedback-icon" id="rfidFeedbackIcon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="rfid-feedback-text">
                <span class="rfid-feedback-name" id="rfidFeedbackName"></span>
                <span class="rfid-feedback-msg" id="rfidFeedbackMsg"></span>
            </div>
        </div>
    </div>

    <!-- ═══ Ingresos recientes de hoy ═══ -->
    <div class="rfid-recent mt-3">
        <div class="card">
            <div class="card-header" style="display:flex; align-items:center; justify-content:space-between; margin-bottom:16px;">
                <h2 style="font-size:1rem; font-weight:700; color:var(--text-primary); margin:0;">
                    <i class="fas fa-list" style="color:var(--accent-green); margin-right:6px;"></i> Marcaciones de Hoy
                </h2>
                <span class="badge badge-blue" id="contadorMarcaciones"><?= count($ultimosIngresos) ?> registros</span>
            </div>

            <div class="table-wrapper">
                <table class="data-table" id="tablaIngresosRecientes">
                    <thead>
                        <tr>
                            <th>Aprendiz</th>
                            <th>Documento</th>
                            <th>Ficha</th>
                            <th>Entrada</th>
                            <th>Estado</th>
                            <th>Retardo</th>
                        </tr>
                    </thead>
                    <tbody id="tablaMarcacionesBody">
                        <?php if (!empty($ultimosIngresos)): ?>
                            <?php foreach ($ultimosIngresos as $ing): ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($ing['nombre'] . ' ' . $ing['apellido']) ?></strong></td>
                                <td><?= htmlspecialchars((string)$ing['num_documento']) ?></td>
                                <td><?= htmlspecialchars((string)($ing['codigo_ficha'] ?? '—')) ?></td>
                                <td><?= $ing['hora_entrada'] ?? '—' ?></td>
                                <td>
                                    <?php
                                    $retardo = (int)($ing['minutos_retardo'] ?? 0);
                                    $badgeClass = $retardo > 0 ? 'badge-pendiente' : 'badge-activo';
                                    $badgeText  = $retardo > 0 ? 'Retardo' : 'Asistió';
                                    ?>
                                    <span class="badge <?= $badgeClass ?>"><?= $badgeText ?></span>
                                </td>
                                <td><?= $retardo > 0 ? $retardo . ' min' : '—' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr id="emptyRow">
                                <td colspan="6" style="text-align:center; color:var(--text-muted); padding:32px;">
                                    <i class="fas fa-inbox" style="font-size:1.5rem; display:block; margin-bottom:8px; opacity:0.4;"></i>
                                    Sin marcaciones aún hoy
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
