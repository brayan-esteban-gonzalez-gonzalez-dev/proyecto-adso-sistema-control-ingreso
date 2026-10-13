<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<div class="stats-grid">
    <a href="<?= BASE_URL ?>?action=usuarios&rol=3" style="text-decoration:none; color:inherit;">
        <div class="stat-card green">
            <div class="stat-icon green">
                <i class="fas fa-user-graduate"></i>
            </div>
            <div class="stat-info">
                <div class="stat-value"><?= $stats['total_aprendices'] ?></div>
                <div class="stat-label">Aprendices Activos</div>
            </div>
        </div>
    </a>

    <div class="stat-card blue">
        <div class="stat-icon blue">
            <i class="fas fa-folder-open"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= $stats['total_fichas'] ?></div>
            <div class="stat-label">Fichas Registradas</div>
        </div>
    </div>

    <div class="stat-card purple">
        <div class="stat-icon purple">
            <i class="fas fa-sign-in-alt"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= $stats['ingresos_hoy'] ?></div>
            <div class="stat-label">Ingresos Hoy</div>
        </div>
    </div>

    <div class="stat-card yellow">
        <div class="stat-icon yellow">
            <i class="fas fa-clock"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= $stats['retardos_hoy'] ?></div>
            <div class="stat-label">Retardos Hoy</div>
        </div>
    </div>

    <div class="stat-card red">
        <div class="stat-icon red">
            <i class="fas fa-file-medical"></i>
        </div>
        <div class="stat-info">
            <div class="stat-value"><?= $stats['excusas_pendientes'] ?></div>
            <div class="stat-label">Excusas Pendientes</div>
        </div>
    </div>
</div>

<div class="grid-2">
    <!-- Últimos Ingresos de Hoy -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <i class="fas fa-sign-in-alt"></i> Ingresos Recientes
            </h2>
            <a href="<?= BASE_URL ?>?action=asistencia/historial" class="btn btn-sm btn-secondary">
                Ver todo
            </a>
        </div>

        <?php if (!empty($ultimosIngresos)): ?>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Aprendiz</th>
                        <th>Ficha</th>
                        <th>Entrada</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($ultimosIngresos, 0, 8) as $ing): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($ing['nombre'] . ' ' . $ing['apellido']) ?></strong>
                            <br><small class="text-muted"><?= htmlspecialchars($ing['num_documento']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($ing['codigo_ficha']) ?></td>
                        <td><?= $ing['hora_entrada'] ?? '—' ?></td>
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
            <p>Sin ingresos registrados hoy</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Excusas Pendientes -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">
                <i class="fas fa-file-medical"></i> Excusas Pendientes
            </h2>
            <a href="<?= BASE_URL ?>?action=excusas&filtro=pendientes" class="btn btn-sm btn-secondary">
                Ver todo
            </a>
        </div>

        <?php if (!empty($excusasPendientes)): ?>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Aprendiz</th>
                        <th>Período</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($excusasPendientes, 0, 5) as $exc): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($exc['nombre'] . ' ' . $exc['apellido']) ?></strong>
                            <br><small class="text-muted">Ficha: <?= htmlspecialchars($exc['codigo_ficha']) ?></small>
                        </td>
                        <td>
                            <?= $exc['fecha_inicio'] ?> <br>
                            <small class="text-muted">a <?= $exc['fecha_fin'] ?></small>
                        </td>
                        <td>
                            <a href="<?= BASE_URL ?>?action=excusas/revisar&id=<?= $exc['id_excusa'] ?>" 
                               class="btn btn-sm btn-blue">
                                <i class="fas fa-eye"></i> Revisar
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-check-circle"></i>
            <p>No hay excusas pendientes</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Accesos Rápidos -->
<div class="card mt-3">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-bolt"></i> Accesos Rápidos
        </h2>
    </div>
    <div class="btn-group">
        <?php if (Auth::isInstructor()): ?>
        <a href="<?= BASE_URL ?>?action=asistencia/rfid" class="btn btn-primary">
            <i class="fas fa-wifi"></i> Marcación RFID
        </a>
        <?php endif; ?>
        <?php if (Auth::isAdmin()): ?>
        <a href="<?= BASE_URL ?>?action=usuarios/crear" class="btn btn-blue">
            <i class="fas fa-user-plus"></i> Nuevo Usuario
        </a>
        <a href="<?= BASE_URL ?>?action=fichas/crear" class="btn btn-secondary">
            <i class="fas fa-plus"></i> Nueva Ficha
        </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>?action=reportes" class="btn btn-purple">
            <i class="fas fa-file-alt"></i> Generar Reporte
        </a>
        <a href="<?= BASE_URL ?>?action=asistencia/historial" class="btn btn-secondary">
            <i class="fas fa-history"></i> Historial de Asistencia
        </a>
    </div>
</div>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
