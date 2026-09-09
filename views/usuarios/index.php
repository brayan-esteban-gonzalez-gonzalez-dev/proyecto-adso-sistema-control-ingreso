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
                    <th>Registrado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td><?= $u['id_usuario'] ?></td>
                    <td><strong><?= htmlspecialchars($u['num_documento']) ?></strong></td>
                    <td><?= htmlspecialchars($u['nombre'] . ' ' . $u['apellido']) ?></td>
                    <td><?= htmlspecialchars($u['correo']) ?></td>
                    <td>
                        <span class="badge badge-<?= $u['id_rol'] == 1 ? 'green' : ($u['id_rol'] == 2 ? 'blue' : 'purple') ?>">
                            <?= htmlspecialchars($u['nombre_rol']) ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge badge-<?= strtolower($u['estado']) ?>">
                            <?= $u['estado'] ?>
                        </span>
                    </td>
                    <td><small><?= date('d/m/Y', strtotime($u['creado_en'])) ?></small></td>
                    <td>
                        <div class="btn-group">
                            <a href="<?= BASE_URL ?>?action=usuarios/editar&id=<?= $u['id_usuario'] ?>" 
                               class="btn btn-sm btn-secondary" title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php if ($u['id_usuario'] != Auth::getUserId()): ?>
                            <a href="<?= BASE_URL ?>?action=usuarios/eliminar&id=<?= $u['id_usuario'] ?>" 
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
