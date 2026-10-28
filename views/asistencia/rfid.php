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
                    <?= Auth::isAdmin() ? 'Control Global de Fichas — Asistencia' : 'Mis Fichas — Control de Asistencia' ?>
                </h2>
                <span class="badge badge-blue" id="fichasCount"><?= count($fichas ?? []) ?> fichas</span>
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

    <!-- ═══ Terminal Dual: RFID y Marcación Manual ═══ -->
    <div class="rfid-dual-grid" id="rfidDualGrid">
        <!-- Tarjeta 1: Indicador Lector RFID USB -->
        <div class="card" id="cardLectorRfid">
            <div class="card-header" style="margin-bottom:12px;">
                <h3 style="font-size:0.95rem; font-weight:700; margin:0; display:flex; align-items:center; gap:8px;">
                    <i class="fas fa-wifi" style="color:var(--accent-green);"></i>
                    Terminal RFID (Lector USB)
                </h3>
            </div>
            <div class="rfid-status-box" id="rfidStatusBox">
                <div class="rfid-icon-pulse" id="rfidIconPulse">
                    <i class="fas fa-id-card"></i>
                </div>
                <div>
                    <strong style="display:block; font-size:0.92rem;" id="rfidStatusTitle">Terminal en Espera</strong>
                    <span style="font-size:0.8rem; color:var(--text-secondary);" id="rfidStatusDesc">
                        Enciende la asistencia de al menos una ficha para recibir carnets RFID.
                    </span>
                </div>
            </div>
        </div>

        <!-- Tarjeta 2: Respaldo Manual / Olvido de Carnet -->
        <div class="card" id="cardManualDoc">
            <div class="card-header" style="margin-bottom:12px;">
                <h3 style="font-size:0.95rem; font-weight:700; margin:0; display:flex; align-items:center; gap:8px;">
                    <i class="fas fa-keyboard" style="color:var(--accent-blue);"></i>
                    Respaldo Manual / Olvido de Carnet
                </h3>
            </div>
            <div>
                <form id="formMarcacionManual" onsubmit="event.preventDefault(); window.registrarMarcacionManualPin();" class="rfid-manual-form">
                    <select id="selectFichaManual" class="rfid-select-ficha" title="Ficha para marcación manual" disabled>
                        <option value="">Seleccione Ficha Activa...</option>
                    </select>

                    <input type="text"
                           id="inputPinRespaldo"
                           name="documento"
                           class="rfid-input-doc"
                           placeholder="Ingrese documento o PIN del aprendiz..."
                           autocomplete="off"
                           disabled>

                    <button type="submit" id="btnMarcarPorDocumento" class="btn btn-primary" disabled>
                        <i class="fas fa-id-card-alt"></i> Marcar por Documento
                    </button>
                </form>
                <div style="margin-top:8px;" id="manualDocStatus">
                    <small style="color:var(--text-muted);">
                        <i class="fas fa-info-circle"></i> Respaldo manual en caso de olvido, daño o extravío del llavero RFID.
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- ═══ Mecanismo 2: Nómina Interactiva en Tiempo Real (Marcación con 1 Clic) ═══ -->
    <div class="card mt-3" id="panelNominaSesion">
        <div class="card-header" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:16px;">
            <div>
                <h2 style="font-size:1rem; font-weight:700; color:var(--text-primary); margin:0; display:flex; align-items:center; gap:8px;">
                    <i class="fas fa-users-rectangle" style="color:var(--accent-blue);"></i>
                    Mecanismo 2 — Nómina de Aprendices en Sesión Activa
                </h2>
                <small style="color:var(--text-secondary); display:block; margin-top:2px;" id="subtituloNomina">
                    Visualiza y marca asistencia con un solo clic.
                </small>
            </div>

            <!-- KPIs de la sesión activa -->
            <div class="rfid-kpi-bar" id="nominaKpis">
                <div class="rfid-kpi-chip chip-total">
                    <span>Total:</span> <span class="num" id="kpiTotal">0</span>
                </div>
                <div class="rfid-kpi-chip chip-presentes">
                    <span>Presentes:</span> <span class="num" id="kpiPresentes">0</span>
                </div>
                <div class="rfid-kpi-chip chip-retardos">
                    <span>Retardos:</span> <span class="num" id="kpiRetardos">0</span>
                </div>
                <div class="rfid-kpi-chip chip-pendientes">
                    <span>Pendientes:</span> <span class="num" id="kpiPendientes">0</span>
                </div>
            </div>
        </div>

        <!-- Buscador interactivo en vivo -->
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:14px; flex-wrap:wrap;">
            <div class="rfid-search-wrapper">
                <i class="fas fa-search"></i>
                <input type="search"
                       id="buscadorNomina"
                       class="rfid-search-input"
                       placeholder="Buscar por nombre o documento..."
                       autocomplete="off"
                       disabled>
            </div>

            <div style="font-size:0.82rem; color:var(--text-muted);" id="nominaFiltroInfo">
                Mostrando aprendices activos de la ficha seleccionada.
            </div>
        </div>

        <!-- Tabla interactiva de aprendices -->
        <div class="table-wrapper">
            <table class="data-table" id="tablaNomina">
                <thead>
                    <tr>
                        <th style="width:140px;">Documento</th>
                        <th>Aprendiz</th>
                        <th style="width:130px;">Carnet RFID</th>
                        <th style="width:120px;">Entrada</th>
                        <th style="width:120px;">Estado</th>
                        <th style="width:160px; text-align:center;">Acción Manual</th>
                    </tr>
                </thead>
                <tbody id="tablaNominaBody">
                    <tr id="nominaPlaceholderRow">
                        <td colspan="6" style="text-align:center; color:var(--text-muted); padding:36px;">
                            <i class="fas fa-toggle-off" style="font-size:1.8rem; display:block; margin-bottom:10px; opacity:0.4;"></i>
                            <span>No hay sesiones de asistencia encendidas. Activa una ficha arriba para cargar la nómina en vivo.</span>
                        </td>
                    </tr>
                </tbody>
            </table>
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
