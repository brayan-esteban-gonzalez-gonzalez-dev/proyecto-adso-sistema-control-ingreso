<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<?php if (Auth::isAprendiz() && !empty($metricasAprendiz)): ?>
    <!-- ═══ PORTAL DEL APRENDIZ ═══ -->
    <?php
    $ap       = $metricasAprendiz['aprendiz'] ?? [];
    $pctAsist = (float) ($metricasAprendiz['porcentaje_asistencia'] ?? 0.0);
    $barColor = $pctAsist >= 80 ? 'var(--accent-green)' : ($pctAsist >= 70 ? 'var(--accent-amber, #f59e0b)' : 'var(--accent-red)');
    ?>

    <!-- Banner Hero del Aprendiz -->
    <div class="aprendiz-hero-card">
        <div class="aprendiz-hero-info">
            <h2>
                <i class="fas fa-user-graduate" style="color:var(--accent-green);"></i>
                Hola, <?= htmlspecialchars(Auth::getUserFullName()) ?>
            </h2>
            <div class="aprendiz-hero-meta">
                <span><i class="fas fa-id-card"></i> Doc: <?= htmlspecialchars((string)($ap['identificacion'] ?? '—')) ?></span>
                <span><i class="fas fa-layer-group"></i> Ficha: <strong><?= htmlspecialchars((string)($ap['codigo_ficha'] ?? 'Sin asignar')) ?></strong></span>
                <span><i class="fas fa-graduation-cap"></i> <?= htmlspecialchars((string)($ap['programa_nombre'] ?? 'Programa no registrado')) ?></span>
            </div>
        </div>
        <div>
            <a href="<?= BASE_URL ?>?action=excusas/crear" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Radicar Excusa
            </a>
        </div>
    </div>

    <!-- Indicadores Visuales / Métricas de Asistencia -->
    <div class="dashboard-grid">
        <!-- Tarjeta: % Asistencia -->
        <div class="stat-card">
            <div class="stat-card-icon" style="background: linear-gradient(135deg, #10b981, #059669);">
                <i class="fas fa-chart-pie"></i>
            </div>
            <div class="stat-card-info">
                <span class="stat-card-value" style="color: <?= $barColor ?>;">
                    <?= number_format($pctAsist, 1) ?>%
                </span>
                <span class="stat-card-label">Porcentaje de Asistencia</span>
                <div class="asistencia-progress-container" title="<?= number_format($pctAsist, 1) ?>%">
                    <div class="asistencia-progress-bar" style="width: <?= min(100, max(0, $pctAsist)) ?>%; background: <?= $barColor ?>;"></div>
                </div>
                <small style="color:var(--text-muted); font-size:0.75rem; margin-top:4px; display:block;">
                    Base de evaluación: <?= (int)$metricasAprendiz['base_sesiones'] ?> sesiones
                </small>
            </div>
        </div>

        <!-- Tarjeta: Asistencias a Tiempo -->
        <div class="stat-card">
            <div class="stat-card-icon" style="background: linear-gradient(135deg, #06b6d4, #22d3ee);">
                <i class="fas fa-user-check"></i>
            </div>
            <div class="stat-card-info">
                <span class="stat-card-value"><?= (int)$metricasAprendiz['asistencias_a_tiempo'] ?></span>
                <span class="stat-card-label">Asistencias a Tiempo</span>
                <small style="color:var(--text-muted); font-size:0.75rem;">Marcaciones puntuales</small>
            </div>
        </div>

        <!-- Tarjeta: Retardos -->
        <div class="stat-card">
            <div class="stat-card-icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">
                <i class="fas fa-user-clock"></i>
            </div>
            <div class="stat-card-info">
                <span class="stat-card-value"><?= (int)$metricasAprendiz['retardos'] ?></span>
                <span class="stat-card-label">Retardos</span>
                <small style="color:var(--text-muted); font-size:0.75rem;">
                    <?= (int)$metricasAprendiz['total_minutos_retardo'] ?> minutos acumulados
                </small>
            </div>
        </div>

        <!-- Tarjeta: Inasistencias -->
        <div class="stat-card">
            <div class="stat-card-icon" style="background: linear-gradient(135deg, #ef4444, #f87171);">
                <i class="fas fa-user-times"></i>
            </div>
            <div class="stat-card-info">
                <span class="stat-card-value"><?= (int)$metricasAprendiz['total_inasistencias'] ?></span>
                <span class="stat-card-label">Inasistencias Registradas</span>
                <small style="color:var(--text-muted); font-size:0.75rem;">
                    <?= (int)$metricasAprendiz['inasistencias_justificadas'] ?> justificadas · <?= (int)$metricasAprendiz['inasistencias_injustificadas'] ?> sin justificar
                </small>
            </div>
        </div>
    </div>

    <!-- Layout Dual: Historial de Marcas y Mis Excusas -->
    <div class="portal-aprendiz-grid">
        <!-- Columna Principal: Historial de Marcaciones -->
        <div class="card">
            <div class="card-header" style="margin-bottom:16px; display:flex; justify-content:space-between; align-items:center;">
                <h3 style="font-size:1rem; font-weight:700; margin:0; display:flex; align-items:center; gap:8px;">
                    <i class="fas fa-history" style="color:var(--accent-blue);"></i>
                    Historial de Marcaciones
                </h3>
                <span style="font-size:0.8rem; color:var(--text-muted);">
                    Total registros: <?= count($metricasAprendiz['historial_marcas']) ?>
                </span>
            </div>

            <?php if (empty($metricasAprendiz['historial_marcas'])): ?>
                <div style="text-align:center; padding:40px 20px; color:var(--text-muted);">
                    <i class="fas fa-calendar-times" style="font-size:2rem; margin-bottom:12px; opacity:0.4; display:block;"></i>
                    <p style="margin:0; font-size:0.9rem;">Aún no tienes registros de asistencia o inasistencia en el sistema.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Hora Entrada</th>
                                <th>Retardo</th>
                                <th>Competencia / Bloque</th>
                                <th style="text-align:center;">Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($metricasAprendiz['historial_marcas'] as $marca): ?>
                                <?php
                                $estadoMarca = $marca['estado_marca'] ?? 'Inasistencia';
                                $badgeClass  = 'badge-inactivo';
                                $iconClass   = 'fa-times';

                                if ($estadoMarca === 'A tiempo') {
                                    $badgeClass = 'badge-activo';
                                    $iconClass  = 'fa-check';
                                } elseif ($estadoMarca === 'Retardo') {
                                    $badgeClass = 'badge-pendiente';
                                    $iconClass  = 'fa-clock';
                                } elseif ($estadoMarca === 'Justificada') {
                                    $badgeClass = 'badge-blue';
                                    $iconClass  = 'fa-shield-alt';
                                } elseif ($estadoMarca === 'En revisión') {
                                    $badgeClass = 'badge-pendiente';
                                    $iconClass  = 'fa-hourglass-half';
                                }
                                ?>
                                <tr>
                                    <td>
                                        <strong><?= date('d/m/Y', strtotime($marca['fecha'])) ?></strong>
                                    </td>
                                    <td>
                                        <?php if (!empty($marca['hora_entrada'])): ?>
                                            <span style="color:var(--text-primary);"><i class="fas fa-sign-in-alt" style="color:var(--accent-green); margin-right:4px;"></i> <?= htmlspecialchars(substr((string)$marca['hora_entrada'], 0, 5)) ?></span>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted);">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ((int)$marca['minutos_retardo'] > 0): ?>
                                            <span style="color:var(--accent-amber, #f59e0b); font-weight:600;">
                                                +<?= (int)$marca['minutos_retardo'] ?> min
                                            </span>
                                        <?php else: ?>
                                            <span style="color:var(--text-muted);">0 min</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span style="font-size:0.85rem; color:var(--text-primary); font-weight:500;">
                                            <?= htmlspecialchars((string)$marca['competencia']) ?>
                                        </span>
                                        <?php if (!empty($marca['sesion_hora_inicio'])): ?>
                                            <small style="display:block; color:var(--text-muted); font-size:0.75rem;">
                                                <?= htmlspecialchars(substr((string)$marca['sesion_hora_inicio'], 0, 5)) ?>
                                                <?php if (!empty($marca['sesion_hora_fin'])): ?> - <?= htmlspecialchars(substr((string)$marca['sesion_hora_fin'], 0, 5)) ?><?php endif; ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align:center;">
                                        <span class="badge <?= $badgeClass ?>">
                                            <i class="fas <?= $iconClass ?>" style="margin-right:4px;"></i>
                                            <?= htmlspecialchars($estadoMarca) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Columna Lateral: Estado de Excusas Médicas -->
        <div class="card">
            <div class="card-header" style="margin-bottom:16px; display:flex; justify-content:space-between; align-items:center;">
                <h3 style="font-size:1rem; font-weight:700; margin:0; display:flex; align-items:center; gap:8px;">
                    <i class="fas fa-file-medical" style="color:var(--accent-cyan);"></i>
                    Mis Excusas
                </h3>
                <a href="<?= BASE_URL ?>?action=excusas" class="stat-card-link" style="font-size:0.8rem;">
                    Ver todas <i class="fas fa-arrow-right"></i>
                </a>
            </div>

            <?php if (empty($metricasAprendiz['excusas'])): ?>
                <div style="text-align:center; padding:30px 15px; color:var(--text-muted);">
                    <i class="fas fa-notes-medical" style="font-size:1.8rem; margin-bottom:10px; opacity:0.4; display:block;"></i>
                    <p style="font-size:0.85rem; margin-bottom:14px;">No has presentado solicitudes de justificación o excusas médicas.</p>
                    <a href="<?= BASE_URL ?>?action=excusas/crear" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i> Radicar Excusa
                    </a>
                </div>
            <?php else: ?>
                <div class="excusa-mini-list">
                    <?php foreach ($metricasAprendiz['excusas'] as $exc): ?>
                        <?php
                        $st = $exc['estado_excusa'] ?? 'Pendiente';
                        $stClass = 'badge-pendiente';
                        $stIcon  = 'fa-clock';

                        if ($st === 'Aprobada') {
                            $stClass = 'badge-aprobada';
                            $stIcon  = 'fa-check-circle';
                        } elseif ($st === 'Rechazada') {
                            $stClass = 'badge-rechazada';
                            $stIcon  = 'fa-times-circle';
                        }
                        ?>
                        <div class="excusa-mini-card">
                            <div class="excusa-mini-header">
                                <span style="font-size:0.78rem; font-weight:600; color:var(--text-muted);">
                                    <i class="fas fa-calendar-alt"></i> <?= date('d/m/Y', strtotime($exc['fecha_inasistencia'] ?? $exc['fecha'])) ?>
                                </span>
                                <span class="badge <?= $stClass ?>">
                                    <i class="fas <?= $stIcon ?>" style="margin-right:4px;"></i>
                                    <?= htmlspecialchars($st) ?>
                                </span>
                            </div>
                            <div class="excusa-mini-motivo">
                                <?= htmlspecialchars(mb_strimwidth((string)$exc['motivo'], 0, 90, '…')) ?>
                            </div>
                            <?php if (!empty($exc['respuesta_instructor'])): ?>
                                <div class="excusa-mini-feedback <?= $st === 'Rechazada' ? 'rechazada' : '' ?>">
                                    <strong><i class="fas fa-reply"></i> Instructor:</strong>
                                    <?= htmlspecialchars($exc['respuesta_instructor']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php else: ?>
    <!-- ═══ DASHBOARD ADMINISTRADOR / INSTRUCTOR ═══ -->
    <div class="dashboard-grid">
        <!-- Tarjeta: Usuarios -->
        <div class="stat-card">
            <div class="stat-card-icon" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-card-info">
                <span class="stat-card-value"><?= $stats['total_usuarios'] ?? 0 ?></span>
                <span class="stat-card-label">Usuarios</span>
            </div>
            <?php if (Auth::isAdminOrInstructor()): ?>
            <a href="<?= BASE_URL ?>?action=usuarios" class="stat-card-link">
                Ver todos <i class="fas fa-arrow-right"></i>
            </a>
            <?php endif; ?>
        </div>

        <!-- Tarjeta: Instructores -->
        <div class="stat-card">
            <div class="stat-card-icon" style="background: linear-gradient(135deg, #06b6d4, #22d3ee);">
                <i class="fas fa-chalkboard-teacher"></i>
            </div>
            <div class="stat-card-info">
                <span class="stat-card-value"><?= $stats['total_instructores'] ?? 0 ?></span>
                <span class="stat-card-label">Instructores</span>
            </div>
        </div>

        <!-- Tarjeta: Fichas -->
        <div class="stat-card">
            <div class="stat-card-icon" style="background: linear-gradient(135deg, #10b981, #34d399);">
                <i class="fas fa-folder-open"></i>
            </div>
            <div class="stat-card-info">
                <span class="stat-card-value"><?= $stats['total_fichas'] ?? 0 ?></span>
                <span class="stat-card-label">Fichas</span>
            </div>
            <?php if (Auth::isAdminOrInstructor()): ?>
            <a href="<?= BASE_URL ?>?action=fichas" class="stat-card-link">
                Ver todas <i class="fas fa-arrow-right"></i>
            </a>
            <?php endif; ?>
        </div>

        <!-- Tarjeta: Aprendices -->
        <div class="stat-card">
            <div class="stat-card-icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">
                <i class="fas fa-user-graduate"></i>
            </div>
            <div class="stat-card-info">
                <span class="stat-card-value"><?= $stats['total_aprendices'] ?? 0 ?></span>
                <span class="stat-card-label">Aprendices Activos</span>
            </div>
            <?php if (Auth::isAdminOrInstructor()): ?>
            <a href="<?= BASE_URL ?>?action=aprendices" class="stat-card-link">
                Ver todos <i class="fas fa-arrow-right"></i>
            </a>
            <?php endif; ?>
        </div>

        <!-- Tarjeta: Excusas Pendientes (solo Instructor) -->
        <?php if (Auth::isInstructor()): ?>
        <div class="stat-card">
            <div class="stat-card-icon" style="background: linear-gradient(135deg, #ef4444, #f87171);">
                <i class="fas fa-file-medical"></i>
            </div>
            <div class="stat-card-info">
                <span class="stat-card-value"><?= $stats['excusas_pendientes'] ?? 0 ?></span>
                <span class="stat-card-label">Excusas Pendientes</span>
            </div>
            <a href="<?= BASE_URL ?>?action=excusas&filtro=pendientes" class="stat-card-link">
                Revisar <i class="fas fa-arrow-right"></i>
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Accesos rápidos -->
    <?php if (Auth::isAdminOrInstructor()): ?>
    <div class="card" style="margin-top: 24px;">
        <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 16px; color: var(--text-primary);">
            <i class="fas fa-bolt" style="color: var(--accent-yellow);"></i> Accesos Rápidos
        </h3>
        <div class="quick-actions">
            <a href="<?= BASE_URL ?>?action=usuarios/crear" class="quick-action-btn">
                <i class="fas fa-user-plus"></i>
                <span>Nuevo Usuario</span>
            </a>
            <?php if (Auth::isAdmin()): ?>
            <a href="<?= BASE_URL ?>?action=fichas/crear" class="quick-action-btn">
                <i class="fas fa-folder-plus"></i>
                <span>Nueva Ficha</span>
            </a>
            <?php endif; ?>
            <a href="<?= BASE_URL ?>?action=aprendices/crear" class="quick-action-btn">
                <i class="fas fa-user-graduate"></i>
                <span>Nuevo Aprendiz</span>
            </a>
            <?php if (Auth::isInstructor()): ?>
            <a href="<?= BASE_URL ?>?action=excusas&filtro=pendientes" class="quick-action-btn">
                <i class="fas fa-file-medical"></i>
                <span>Excusas Pendientes</span>
            </a>
            <?php endif; ?>
            <?php if (Auth::isInstructor()): ?>
            <a href="<?= BASE_URL ?>?action=horarios/miHorario" class="quick-action-btn">
                <i class="fas fa-calendar-week"></i>
                <span>Mi Horario</span>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php else: ?>
    <div class="card" style="margin-top: 24px;">
        <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 16px; color: var(--text-primary);">
            <i class="fas fa-bolt" style="color: var(--accent-yellow);"></i> Mis Accesos
        </h3>
        <div class="quick-actions">
            <a href="<?= BASE_URL ?>?action=excusas" class="quick-action-btn">
                <i class="fas fa-file-medical"></i>
                <span>Mis Excusas</span>
            </a>
            <a href="<?= BASE_URL ?>?action=excusas/crear" class="quick-action-btn">
                <i class="fas fa-plus-circle"></i>
                <span>Nueva Excusa</span>
            </a>
        </div>
    </div>
    <?php endif; ?>

<?php endif; ?>

<!-- Gráficas analíticas (todos los roles) -->
<?php require_once ROOT_PATH . '/views/dashboard/_graficos.php'; ?>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
