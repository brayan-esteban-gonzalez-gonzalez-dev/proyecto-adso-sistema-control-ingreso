<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<!-- Filtros -->
<div class="filter-bar">
    <form method="GET" action="<?= BASE_URL ?>" class="d-flex align-center gap-2 flex-wrap" style="width:100%;">
        <input type="hidden" name="action" value="asistencia/historial">
        
        <?php if (Auth::isAdminOrInstructor()): ?>
        <div class="filter-group">
            <label>Fecha</label>
            <input type="date" name="fecha" class="form-control" value="<?= htmlspecialchars($fecha ?? date('Y-m-d')) ?>">
        </div>
        <?php else: ?>
        <div class="filter-group">
            <label>Desde</label>
            <input type="date" name="fecha_inicio" class="form-control" value="<?= htmlspecialchars($fechaInicio ?? '') ?>">
        </div>
        <div class="filter-group">
            <label>Hasta</label>
            <input type="date" name="fecha_fin" class="form-control" value="<?= htmlspecialchars($fechaFin ?? '') ?>">
        </div>
        <?php endif; ?>

        <div class="filter-group" style="flex: 0;">
            <label>&nbsp;</label>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-search"></i> Filtrar
            </button>
        </div>
    </form>
</div>

<?php if (Auth::isAprendiz() && !empty($estadisticas)): ?>
<!-- Resumen para aprendiz -->
<div class="stats-grid mb-3">
    <div class="stat-card green">
        <div class="stat-icon green"><i class="fas fa-check"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= $estadisticas['total_a_tiempo'] ?></div>
            <div class="stat-label">A Tiempo</div>
        </div>
    </div>
    <div class="stat-card yellow">
        <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= $estadisticas['total_retardos'] ?></div>
            <div class="stat-label">Retardos (<?= $estadisticas['total_minutos_retardo'] ?> min)</div>
        </div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon red"><i class="fas fa-times"></i></div>
        <div class="stat-info">
            <div class="stat-value"><?= $estadisticas['total_inasistencias'] ?></div>
            <div class="stat-label">Inasistencias</div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <?php if (!empty($ingresos)): ?>
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <?php if (Auth::isAdminOrInstructor()): ?>
                    <th>Aprendiz</th>
                    <th>Documento</th>
                    <th>Ficha</th>
                    <?php endif; ?>
                    <th>Fecha</th>
                    <th>Entrada</th>
                    <th>Salida</th>
                    <th>Estado</th>
                    <th>Retardo</th>
                    <th>Sal. Ant.</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ingresos as $ing): ?>
                <tr>
                    <?php if (Auth::isAdminOrInstructor()): ?>
                    <td><strong><?= htmlspecialchars(($ing['nombre'] ?? '') . ' ' . ($ing['apellido'] ?? '')) ?></strong></td>
                    <td><?= htmlspecialchars($ing['num_documento'] ?? '') ?></td>
                    <td><span class="badge badge-blue"><?= htmlspecialchars($ing['codigo_ficha'] ?? '') ?></span></td>
                    <?php endif; ?>
                    <td><?= $ing['fecha'] ?></td>
                    <td><?= $ing['hora_entrada'] ?? '—' ?></td>
                    <td><?= $ing['hora_salida'] ?? '—' ?></td>
                    <td>
                        <span class="badge badge-<?= strtolower($ing['estado']) ?>">
                            <?= str_replace('_', ' ', $ing['estado']) ?>
                        </span>
                    </td>
                    <td><?= $ing['minutos_retardo'] > 0 ? $ing['minutos_retardo'] . ' min' : '—' ?></td>
                    <td><?= $ing['minutos_salida_anticipada'] > 0 ? $ing['minutos_salida_anticipada'] . ' min' : '—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <i class="fas fa-calendar-check"></i>
        <p>No hay registros de asistencia para los filtros seleccionados</p>
    </div>
    <?php endif; ?>
</div>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
