<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<?php if (!empty($aprendiz)): ?>

<!-- Stats del Aprendiz -->
<div class="stats-grid">
    <div class="stat-card green">
        <div class="stat-icon green">
            <i class="fas fa-check-circle"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= $estadisticas['total_a_tiempo'] ?? 0 ?></div>
            <div class="stat-label">Días a Tiempo</div>
        </div>
    </div>

    <div class="stat-card yellow">
        <div class="stat-icon yellow">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= $estadisticas['total_retardos'] ?? 0 ?></div>
            <div class="stat-label">Retardos</div>
        </div>
    </div>

    <div class="stat-card red">
        <div class="stat-icon red">
            <i class="fas fa-times-circle"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= $estadisticas['total_inasistencias'] ?? 0 ?></div>
            <div class="stat-label">Inasistencias</div>
        </div>
    </div>

    <div class="stat-card blue">
        <div class="stat-icon blue">
            <i class="fas fa-file-medical"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= $estadisticas['total_justificados'] ?? 0 ?></div>
            <div class="stat-label">Justificados</div>
        </div>
    </div>
</div>

<div class="grid-2">
    <!-- Información del Aprendiz -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <i class="fas fa-user"></i> Mi Información
            </h2>
        </div>
        <div class="excusa-detail">
            <div class="detail-item">
                <div class="detail-label">Nombre Completo</div>
                <div class="detail-value"><?= htmlspecialchars($aprendiz['nombre'] . ' ' . $aprendiz['apellido']) ?></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Documento</div>
                <div class="detail-value"><?= htmlspecialchars($aprendiz['num_documento']) ?></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Ficha</div>
                <div class="detail-value"><?= htmlspecialchars($aprendiz['codigo_ficha']) ?></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Programa</div>
                <div class="detail-value"><?= htmlspecialchars($aprendiz['nombre_programa']) ?></div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Código RFID</div>
                <div class="detail-value">
                    <?= $aprendiz['codigo_rfid'] ? htmlspecialchars($aprendiz['codigo_rfid']) : '<span class="text-muted">No asignado</span>' ?>
                </div>
            </div>
            <div class="detail-item">
                <div class="detail-label">Estado Hoy</div>
                <div class="detail-value">
                    <?php if ($ingresoHoy): ?>
                        <span class="badge badge-<?= strtolower($ingresoHoy['estado']) ?>">
                            <?= str_replace('_', ' ', $ingresoHoy['estado']) ?>
                        </span>
                        <?php if ($ingresoHoy['hora_entrada']): ?>
                            <br><small class="text-muted">Entrada: <?= $ingresoHoy['hora_entrada'] ?></small>
                        <?php endif; ?>
                    <?php else: ?>
                        <span class="text-muted">Sin registro hoy</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($horarioHoy): ?>
        <div class="mt-2" style="padding: 12px; background: rgba(16,185,129,0.05); border-radius: 8px; border: 1px solid rgba(16,185,129,0.1);">
            <strong class="text-green"><i class="fas fa-calendar-check"></i> Horario de Hoy</strong>
            <p class="text-secondary mt-1" style="font-size: 0.85rem;">
                Entrada: <strong><?= $horarioHoy['hora_entrada'] ?></strong> — 
                Salida: <strong><?= $horarioHoy['hora_salida'] ?></strong>
                <br><small>Tolerancia: <?= $horarioHoy['tolerancia_minutos'] ?> minutos</small>
            </p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Últimos Registros -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <i class="fas fa-history"></i> Últimos Registros
            </h2>
            <a href="<?= BASE_URL ?>?action=asistencia/historial" class="btn btn-sm btn-secondary">
                Ver todo
            </a>
        </div>

        <?php if (!empty($ultimosRegistros)): ?>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Entrada</th>
                        <th>Salida</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ultimosRegistros as $reg): ?>
                    <tr>
                        <td><?= $reg['fecha'] ?></td>
                        <td><?= $reg['hora_entrada'] ?? '—' ?></td>
                        <td><?= $reg['hora_salida'] ?? '—' ?></td>
                        <td>
                            <span class="badge badge-<?= strtolower($reg['estado']) ?>">
                                <?= str_replace('_', ' ', $reg['estado']) ?>
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
            <p>Sin registros de asistencia</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Mis Excusas -->
<div class="card mt-3">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-file-medical"></i> Mis Excusas Médicas
        </h2>
        <a href="<?= BASE_URL ?>?action=excusas/crear" class="btn btn-sm btn-primary">
            <i class="fas fa-plus"></i> Nueva Excusa
        </a>
    </div>

    <?php if (!empty($excusas)): ?>
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Período</th>
                    <th>Motivo</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($excusas, 0, 5) as $exc): ?>
                <tr>
                    <td><?= $exc['fecha_inicio'] ?> — <?= $exc['fecha_fin'] ?></td>
                    <td><?= htmlspecialchars(mb_substr($exc['motivo'], 0, 60)) ?>...</td>
                    <td>
                        <span class="badge badge-<?= strtolower($exc['estado']) ?>">
                            <?= $exc['estado'] ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <i class="fas fa-file-medical"></i>
        <p>No has enviado excusas médicas</p>
    </div>
    <?php endif; ?>
</div>

<?php else: ?>
<div class="empty-state">
    <i class="fas fa-exclamation-triangle"></i>
    <p>No se encontró un perfil de aprendiz asociado a tu cuenta.</p>
    <small>Contacta al administrador para que te asigne una ficha.</small>
</div>
<?php endif; ?>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
