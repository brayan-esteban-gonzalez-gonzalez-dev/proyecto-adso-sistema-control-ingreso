<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<div class="toolbar">
    <div class="toolbar-left">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Buscar jornada..." data-search-table="tablaJornadas">
        </div>
    </div>
    <div class="toolbar-right">
        <a href="<?= BASE_URL ?>?action=jornadas/crear" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nueva Jornada
        </a>
    </div>
</div>

<div class="card">
    <?php if (!empty($jornadas)): ?>
    <div class="table-wrapper">
        <table class="data-table" id="tablaJornadas">
            <thead>
                <tr>
                    <th>Nombre</th>
                    <th>Hora inicio</th>
                    <th>Hora fin</th>
                    <th>Fichas</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($jornadas as $j): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($j['nombre']) ?></strong></td>
                    <td><?= htmlspecialchars(substr($j['hora_inicio'], 0, 5)) ?></td>
                    <td><?= htmlspecialchars(substr($j['hora_fin'], 0, 5)) ?></td>
                    <td>
                        <span class="badge badge-blue"><?= (int) ($j['total_fichas'] ?? 0) ?></span>
                    </td>
                    <td>
                        <div class="btn-group">
                            <a href="<?= BASE_URL ?>?action=jornadas/editar&id=<?= (int) $j['id'] ?>"
                               class="btn btn-sm btn-secondary" title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="<?= BASE_URL ?>?action=jornadas/eliminar&id=<?= (int) $j['id'] ?>"
                               class="btn btn-sm btn-danger"
                               data-confirm="Eliminar la jornada <?= htmlspecialchars($j['nombre']) ?>?"
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
        <i class="fas fa-clock"></i>
        <p>No hay jornadas registradas</p>
        <a href="<?= BASE_URL ?>?action=jornadas/crear" class="btn btn-primary mt-2">
            <i class="fas fa-plus"></i> Crear primera jornada
        </a>
    </div>
    <?php endif; ?>
</div>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
