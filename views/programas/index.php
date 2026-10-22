<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<div class="toolbar">
    <div class="toolbar-left">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Buscar programa..." data-search-table="tablaProgramas">
        </div>
    </div>
    <div class="toolbar-right">
        <a href="<?= BASE_URL ?>?action=programas/crear" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nuevo Programa
        </a>
    </div>
</div>

<div class="card">
    <?php if (!empty($programas)): ?>
    <div class="table-wrapper">
        <table class="data-table" id="tablaProgramas">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Nombre</th>
                    <th>Descripción</th>
                    <th>Fichas</th>
                    <th>Competencias</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($programas as $p): ?>
                <tr>
                    <td>
                        <?php if (!empty($p['codigo'])): ?>
                            <span class="badge badge-purple"><?= htmlspecialchars($p['codigo']) ?></span>
                        <?php else: ?>
                            <span class="text-muted">Sin código</span>
                        <?php endif; ?>
                    </td>
                    <td><strong><?= htmlspecialchars($p['nombre']) ?></strong></td>
                    <td><?= !empty($p['descripcion']) ? htmlspecialchars($p['descripcion']) : '<span class="text-muted">—</span>' ?></td>
                    <td><span class="badge badge-blue"><?= (int) ($p['total_fichas'] ?? 0) ?></span></td>
                    <td><span class="badge badge-green"><?= (int) ($p['total_competencias'] ?? 0) ?></span></td>
                    <td>
                        <div class="btn-group">
                            <a href="<?= BASE_URL ?>?action=programas/editar&id=<?= (int) $p['id'] ?>"
                               class="btn btn-sm btn-secondary" title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
<?php if ((int)($p['total_fichas'] ?? 0) > 0): ?>
    <button class="btn btn-sm btn-danger" disabled title="No se puede eliminar, tiene fichas vinculadas">
        <i class="fas fa-trash"></i>
    </button>
<?php else: ?>
    <a href="<?= BASE_URL ?>?action=programas/eliminar&id=<?= (int) $p['id'] ?>"
       class="btn btn-sm btn-danger"
       data-confirm="¿Eliminar el programa <?= htmlspecialchars($p['nombre']) ?>?"
       title="Eliminar">
        <i class="fas fa-trash"></i>
    </a>
<?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <i class="fas fa-graduation-cap"></i>
        <p>No hay programas de formación registrados</p>
        <a href="<?= BASE_URL ?>?action=programas/crear" class="btn btn-primary mt-2">
            <i class="fas fa-plus"></i> Crear primer programa
        </a>
    </div>
    <?php endif; ?>
</div>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
