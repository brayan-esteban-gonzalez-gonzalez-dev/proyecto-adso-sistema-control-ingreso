<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<div class="toolbar">
    <div class="toolbar-left">
        <?php if (Auth::isAdminOrInstructor()): ?>
        <a href="<?= BASE_URL ?>?action=excusas&filtro=pendientes" 
           class="btn btn-sm <?= ($_GET['filtro'] ?? '') === 'pendientes' ? 'btn-primary' : 'btn-secondary' ?>">
            <i class="fas fa-clock"></i> Pendientes
        </a>
        <a href="<?= BASE_URL ?>?action=excusas" 
           class="btn btn-sm <?= !isset($_GET['filtro']) || $_GET['filtro'] === 'todas' ? 'btn-primary' : 'btn-secondary' ?>">
            <i class="fas fa-list"></i> Todas
        </a>
        <?php endif; ?>
    </div>
    <div class="toolbar-right">
        <?php if (Auth::isAprendiz()): ?>
        <a href="<?= BASE_URL ?>?action=excusas/crear" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nueva Excusa
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="card">
    <?php if (!empty($excusas)): ?>
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <?php if (Auth::isAdminOrInstructor()): ?>
                    <th>Aprendiz</th>
                    <th>Ficha</th>
                    <?php endif; ?>
                    <th>Período</th>
                    <th>Motivo</th>
                    <th>Estado</th>
                    <th>Archivo</th>
                    <?php if (Auth::isAdminOrInstructor()): ?>
                    <th>Acciones</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($excusas as $exc): ?>
                <tr>
                    <?php if (Auth::isAdminOrInstructor()): ?>
                    <td>
                        <strong><?= htmlspecialchars(($exc['nombre'] ?? '') . ' ' . ($exc['apellido'] ?? '')) ?></strong>
                        <br><small class="text-muted"><?= htmlspecialchars($exc['num_documento'] ?? '') ?></small>
                    </td>
                    <td><span class="badge badge-blue"><?= htmlspecialchars($exc['codigo_ficha'] ?? '') ?></span></td>
                    <?php endif; ?>
                    <td>
                        <?= $exc['fecha_inicio'] ?> 
                        <br><small class="text-muted">a <?= $exc['fecha_fin'] ?></small>
                    </td>
                    <td><?= htmlspecialchars(mb_substr($exc['motivo'], 0, 50)) ?>...</td>
                    <td>
                        <span class="badge badge-<?= strtolower($exc['estado']) ?>">
                            <?= $exc['estado'] ?>
                        </span>
                    </td>
                    <td>
                        <a href="<?= BASE_URL . $exc['archivo_adjunto'] ?>" target="_blank" 
                           class="btn btn-sm btn-secondary">
                            <i class="fas fa-paperclip"></i>
                        </a>
                    </td>
                    <?php if (Auth::isAdminOrInstructor()): ?>
                    <td>
                        <?php if ($exc['estado'] === 'Pendiente'): ?>
                        <a href="<?= BASE_URL ?>?action=excusas/revisar&id=<?= $exc['id_excusa'] ?>" 
                           class="btn btn-sm btn-blue">
                            <i class="fas fa-eye"></i> Revisar
                        </a>
                        <?php else: ?>
                        <span class="text-muted">
                            <small>Por: <?= htmlspecialchars($exc['nombre_revisor'] ?? '—') ?></small>
                        </span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <i class="fas fa-file-medical"></i>
        <p>No hay excusas médicas registradas</p>
        <?php if (Auth::isAprendiz()): ?>
        <a href="<?= BASE_URL ?>?action=excusas/crear" class="btn btn-primary mt-2">
            <i class="fas fa-plus"></i> Enviar excusa
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
