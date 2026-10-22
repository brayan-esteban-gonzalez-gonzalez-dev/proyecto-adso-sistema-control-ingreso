<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<div class="toolbar" style="margin-bottom: 20px;">
    <div class="toolbar-left">
        <a href="<?= BASE_URL ?>?action=fichas" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver a Fichas
        </a>
    </div>
</div>

<!-- Resumen de la Ficha -->
<div class="card" style="margin-bottom: 24px;">
    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 16px;">
        <div>
            <span class="badge badge-purple" style="font-size: 0.85rem; margin-bottom: 6px; display: inline-block;">
                Ficha <?= htmlspecialchars((string) ($ficha['codigo'] ?? '')) ?>
            </span>
            <h2 style="font-size: 1.35rem; font-weight: 700; color: var(--text-primary); margin: 0 0 6px 0;">
                <?= htmlspecialchars($ficha['nombre_programa'] ?? 'Sin programa asignado') ?>
            </h2>
            <p style="color: var(--text-secondary); margin: 0; font-size: 0.9rem;">
                <i class="fas fa-clock" style="color: var(--accent-purple); margin-right: 4px;"></i> Jornada: <strong><?= htmlspecialchars($ficha['nombre_jornada'] ?? 'No definida') ?></strong>
                &nbsp;|&nbsp;
                <i class="fas fa-user-tie" style="color: var(--accent-blue); margin-right: 4px;"></i> Instructor Líder: <strong><?= htmlspecialchars($ficha['nombre_instructor'] ?? 'Sin asignar') ?></strong>
            </p>
        </div>
        <div style="display: flex; gap: 12px;">
            <div style="background: rgba(255, 255, 255, 0.04); border: 1px solid var(--border-color); border-radius: 8px; padding: 12px 18px; text-align: center;">
                <span style="display: block; font-size: 1.4rem; font-weight: 700; color: var(--accent-green);"><?= count($aprendices) ?></span>
                <span style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Aprendices</span>
            </div>
            <div style="background: rgba(255, 255, 255, 0.04); border: 1px solid var(--border-color); border-radius: 8px; padding: 12px 18px; text-align: center;">
                <span style="display: block; font-size: 1.4rem; font-weight: 700; color: var(--accent-blue);"><?= count($instructores) ?></span>
                <span style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">Instructores</span>
            </div>
        </div>
    </div>
</div>

<!-- Listado de Instructores Vinculados -->
<div class="card" style="margin-bottom: 24px;">
    <h3 style="font-size: 1.05rem; font-weight: 600; color: var(--text-primary); margin-bottom: 16px;">
        <i class="fas fa-chalkboard-teacher" style="color: var(--accent-blue); margin-right: 8px;"></i> Instructores Vinculados
    </h3>

    <?php if (!empty($instructores)): ?>
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Identificación</th>
                    <th>Instructor</th>
                    <th>Correo Electrónico</th>
                    <th>Rol en la Ficha</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($instructores as $inst): ?>
                <tr>
                    <td><strong><?= htmlspecialchars((string) ($inst['identificacion'] ?? '')) ?></strong></td>
                    <td><?= htmlspecialchars(trim(($inst['nombre'] ?? '') . ' ' . ($inst['apellido'] ?? ''))) ?></td>
                    <td><?= htmlspecialchars($inst['email'] ?? '-') ?></td>
                    <td>
                        <?php if (($inst['rol_en_ficha'] ?? '') === 'Instructor Líder'): ?>
                            <span class="badge badge-purple"><i class="fas fa-star"></i> Instructor Líder</span>
                        <?php else: ?>
                            <span class="badge badge-blue"><i class="fas fa-book-reader"></i> Instructor de Sesión</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (($inst['estado'] ?? 'Activo') === 'Activo'): ?>
                            <span class="badge badge-green">Activo</span>
                        <?php else: ?>
                            <span class="badge badge-red">Inactivo</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <i class="fas fa-user-slash"></i>
        <p>No se encontraron instructores vinculados a esta ficha.</p>
    </div>
    <?php endif; ?>
</div>

<!-- Listado de Aprendices Vinculados -->
<div class="card">
    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 16px;">
        <h3 style="font-size: 1.05rem; font-weight: 600; color: var(--text-primary); margin: 0;">
            <i class="fas fa-user-graduate" style="color: var(--accent-green); margin-right: 8px;"></i> Aprendices Matriculados
        </h3>
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Filtrar aprendiz..." data-search-table="tablaAprendicesFicha">
        </div>
    </div>

    <?php if (!empty($aprendices)): ?>
    <div class="table-wrapper">
        <table class="data-table" id="tablaAprendicesFicha">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Identificación</th>
                    <th>Aprendiz</th>
                    <th>Correo Electrónico</th>
                    <th>Código RFID / Llavero</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
                <?php $i = 1; foreach ($aprendices as $ap): ?>
                <tr>
                    <td style="color: var(--text-muted);"><?= $i++ ?></td>
                    <td><strong><?= htmlspecialchars((string) ($ap['identificacion'] ?? '')) ?></strong></td>
                    <td><?= htmlspecialchars(trim(($ap['nombre'] ?? '') . ' ' . ($ap['apellido'] ?? ''))) ?></td>
                    <td><?= htmlspecialchars($ap['email'] ?? '-') ?></td>
                    <td>
                        <?php if (!empty($ap['codigo_llavero'])): ?>
                            <span class="badge badge-purple"><i class="fas fa-wifi"></i> <?= htmlspecialchars($ap['codigo_llavero']) ?></span>
                        <?php else: ?>
                            <span class="text-muted" style="font-size: 0.85rem;">Sin asignar</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (($ap['estado'] ?? 'Activo') === 'Activo'): ?>
                            <span class="badge badge-green">Activo</span>
                        <?php else: ?>
                            <span class="badge badge-red">Inactivo</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <i class="fas fa-user-graduate"></i>
        <p>No hay aprendices matriculados en esta ficha actualmente.</p>
    </div>
    <?php endif; ?>
</div>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
