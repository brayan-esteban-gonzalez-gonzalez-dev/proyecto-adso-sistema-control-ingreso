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
                    <th>Jornada</th>
                    <th>Instructor Líder</th>
                    <th>Aprendices</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($fichas as $f): ?>
                <?php 
                    $id = $f['id'] ?? $f['id_ficha'] ?? 0;
                    $codigo = $f['codigo'] ?? $f['codigo_ficha'] ?? '';
                    $jornada = $f['nombre_jornada'] ?? '-';
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($codigo) ?></strong></td>
                    <td><?= htmlspecialchars($f['nombre_programa'] ?? '') ?></td>
                    <td><span class="badge badge-purple"><?= htmlspecialchars($jornada) ?></span></td>
                    <td><?= !empty($f['nombre_instructor']) ? htmlspecialchars($f['nombre_instructor']) : '<span class="text-muted">Sin asignar</span>' ?></td>
                    <td>
                        <span class="badge badge-blue"><?= $f['total_aprendices'] ?? 0 ?></span>
                    </td>
                    <td>
                        <div class="btn-group">
                            <a href="<?= BASE_URL ?>?action=fichas/editar&id=<?= $id ?>" 
                               class="btn btn-sm btn-secondary" title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="<?= BASE_URL ?>?action=fichas/eliminar&id=<?= $id ?>" 
                               class="btn btn-sm btn-danger" 
                               data-confirm="¿Eliminar la ficha <?= htmlspecialchars($codigo) ?>?"
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

