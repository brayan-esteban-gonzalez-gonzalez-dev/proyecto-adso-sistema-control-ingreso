<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<div class="toolbar">
    <div class="toolbar-left">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Buscar ficha..." data-search-table="tablaFichas">
        </div>
    </div>
    <div class="toolbar-right">
        <a href="<?= BASE_URL ?>?action=fichas/crear" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nueva Ficha
        </a>
    </div>
</div>

<div class="card">
    <?php if (!empty($fichas)): ?>
    <div class="table-wrapper">
        <table class="data-table" id="tablaFichas">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Programa de Formación</th>
                    <th>Instructor Líder</th>
                    <th>Aprendices</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($fichas as $f): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($f['codigo_ficha']) ?></strong></td>
                    <td><?= htmlspecialchars($f['nombre_programa']) ?></td>
                    <td><?= $f['nombre_instructor'] ? htmlspecialchars($f['nombre_instructor']) : '<span class="text-muted">Sin asignar</span>' ?></td>
                    <td>
                        <span class="badge badge-blue"><?= $f['total_aprendices'] ?></span>
                    </td>
                    <td>
                        <div class="btn-group">
                            <a href="<?= BASE_URL ?>?action=horarios&id_ficha=<?= $f['id_ficha'] ?>" 
                               class="btn btn-sm btn-secondary" title="Horarios">
                                <i class="fas fa-calendar-alt"></i>
                            </a>
                            <a href="<?= BASE_URL ?>?action=fichas/editar&id=<?= $f['id_ficha'] ?>" 
                               class="btn btn-sm btn-secondary" title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="<?= BASE_URL ?>?action=fichas/eliminar&id=<?= $f['id_ficha'] ?>" 
                               class="btn btn-sm btn-danger" 
                               data-confirm="¿Eliminar la ficha <?= htmlspecialchars($f['codigo_ficha']) ?>? Se eliminarán también sus horarios y aprendices."
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
        <i class="fas fa-folder-open"></i>
        <p>No hay fichas registradas</p>
        <a href="<?= BASE_URL ?>?action=fichas/crear" class="btn btn-primary mt-2">
            <i class="fas fa-plus"></i> Crear primera ficha
        </a>
    </div>
    <?php endif; ?>
</div>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
