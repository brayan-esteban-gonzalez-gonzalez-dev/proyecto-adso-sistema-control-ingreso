<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<div class="toolbar">
    <div class="toolbar-left">
        <form method="GET" action="<?= BASE_URL ?>" class="filter-form" style="display: flex; gap: 10px; align-items: center;">
            <input type="hidden" name="action" value="competencias">
            <select name="programa_id" class="form-control" onchange="this.form.submit()" style="min-width: 240px; margin: 0;">
                <option value="">Todos los programas</option>
                <?php foreach ($programas as $prog): ?>
                    <option value="<?= (int) $prog['id'] ?>" <?= (isset($programaId) && $programaId === (int) $prog['id']) ? 'selected' : '' ?>>
                        <?= !empty($prog['codigo']) ? htmlspecialchars($prog['codigo']) . ' — ' : '' ?><?= htmlspecialchars($prog['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Buscar competencia..." data-search-table="tablaCompetencias">
        </div>
    </div>
    <div class="toolbar-right">
        <a href="<?= BASE_URL ?>?action=competencias/crear" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nueva Competencia
        </a>
    </div>
</div>

<div class="card">
    <?php if (!empty($competencias)): ?>
    <div class="table-wrapper">
        <table class="data-table" id="tablaCompetencias">
            <thead>
                <tr>
                    <th>Competencia</th>
                    <th>Programa</th>
                    <th>Instructores</th>
                    <th>Sesiones</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($competencias as $c): ?>
                <tr>
                    <td>
                        <strong><?= htmlspecialchars($c['nombre']) ?></strong>
                        <?php if (!empty($c['descripcion'])): ?>
                            <span class="text-muted" style="display: block; font-size: 0.78rem;"><?= htmlspecialchars($c['descripcion']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($c['codigo_programa'])): ?>
                            <span class="badge badge-purple"><?= htmlspecialchars($c['codigo_programa']) ?></span>
                        <?php endif; ?>
                        <?= htmlspecialchars($c['nombre_programa']) ?>
                    </td>
                    <td>
                        <?php if (!empty($c['instructores'])): ?>
                            <?php foreach ($c['instructores'] as $inst): ?>
                                <span class="badge badge-blue"><?= htmlspecialchars($inst['nombre'] . ' ' . $inst['apellido']) ?></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="text-muted">Sin asignar</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge badge-green"><?= (int) ($c['total_sesiones'] ?? 0) ?></span></td>
                    <td>
                        <div class="btn-group">
                            <a href="<?= BASE_URL ?>?action=competencias/editar&id=<?= (int) $c['id'] ?>"
                               class="btn btn-sm btn-secondary" title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="<?= BASE_URL ?>?action=competencias/eliminar&id=<?= (int) $c['id'] ?>"
                               class="btn btn-sm btn-danger"
                               data-confirm="¿Eliminar la competencia <?= htmlspecialchars($c['nombre']) ?>?"
                               title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <i class="fas fa-book-open"></i>
        <p>No hay competencias registradas</p>
        <a href="<?= BASE_URL ?>?action=competencias/crear" class="btn btn-primary mt-2">
            <i class="fas fa-plus"></i> Crear primera competencia
        </a>
    </div>
    <?php endif; ?>
</div>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
