<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<div class="toolbar">
    <div class="toolbar-left">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Buscar usuario..." data-search-table="tablaUsuarios">
        </div>
    </div>
    <div class="toolbar-right">
        <a href="<?= BASE_URL ?>?action=usuarios/crear" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nuevo Usuario
        </a>
    </div>
</div>

<div class="card">
    <?php if (!empty($usuarios)): ?>
    <div class="table-wrapper">
        <table class="data-table" id="tablaUsuarios">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Documento</th>
                    <th>Nombre Completo</th>
                    <th>Correo</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th>Llavero</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $u): ?>
                <?php 
                    $id = $u['id'] ?? $u['id_usuario'] ?? 0;
                    $identificacion = $u['identificacion'] ?? $u['num_documento'] ?? '';
                    $email = $u['email'] ?? $u['correo'] ?? '';
                    $rolId = $u['Rol_id'] ?? $u['id_rol'] ?? 0;
                    $nombreRol = $u['nombre_rol'] ?? '';
                    $estado = $u['estado'] ?? 'Activo';
                    $llavero = $u['codigo_llavero'] ?? '-';
                ?>
                <tr>
                    <td><?= $id ?></td>
                    <td><strong><?= htmlspecialchars($identificacion) ?></strong></td>
                    <td><?= htmlspecialchars($u['nombre'] . ' ' . $u['apellido']) ?></td>
                    <td><?= htmlspecialchars($email) ?></td>
                    <td>
                        <span class="badge badge-<?= $rolId == 1 ? 'green' : ($rolId == 2 ? 'blue' : 'purple') ?>">
                            <?= htmlspecialchars($nombreRol) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge badge-<?= strtolower($estado) ?>">
                            <?= htmlspecialchars($estado) ?>
                        </span>
                    </td>
                    <td><small><?= htmlspecialchars($llavero ?: '-') ?></small></td>
                    <td>
                        <div class="btn-group">
                            <a href="<?= BASE_URL ?>?action=usuarios/editar&id=<?= $id ?>" 
                               class="btn btn-sm btn-secondary" title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php if ($id != Auth::getUserId()): ?>
                            <a href="<?= BASE_URL ?>?action=usuarios/eliminar&id=<?= $id ?>" 
                               class="btn btn-sm btn-danger" 
                               data-confirm="¿Está seguro de eliminar al usuario <?= htmlspecialchars($u['nombre']) ?>?"
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
        <i class="fas fa-users"></i>
        <p>No hay usuarios registrados</p>
        <a href="<?= BASE_URL ?>?action=usuarios/crear" class="btn btn-primary mt-2">
            <i class="fas fa-plus"></i> Crear primer usuario
        </a>
    </div>
    <?php endif; ?>
</div>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>

