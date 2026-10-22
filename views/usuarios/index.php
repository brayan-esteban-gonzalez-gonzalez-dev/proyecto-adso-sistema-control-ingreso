<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<style>
.role-filters-container {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 1.25rem;
}

.role-tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 8px;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    color: var(--text-secondary);
    font-size: 0.875rem;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s ease;
}

.role-tab-btn:hover {
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-primary);
    border-color: var(--text-muted);
}

.role-tab-btn.active {
    background: var(--accent-green, #10b981);
    color: #ffffff;
    border-color: var(--accent-green, #10b981);
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.25);
}

.role-tab-btn .badge-pill {
    padding: 2px 7px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    background: rgba(125, 125, 125, 0.15);
    color: inherit;
}

.role-tab-btn.active .badge-pill {
    background: rgba(255, 255, 255, 0.25);
    color: #ffffff;
}

.meta-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.75rem;
    padding: 2px 6px;
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--border-color);
    color: var(--text-secondary);
}

.meta-chip.rfid-chip {
    color: #10b981;
    border-color: rgba(16, 185, 129, 0.3);
    background: rgba(16, 185, 129, 0.08);
}
</style>

<!-- ── FILTROS RÁPIDOS POR ROL ────────────────────────────────────── -->
<div class="role-filters-container">
    <a href="<?= BASE_URL ?>?action=usuarios" 
       class="role-tab-btn <?= ($rolFiltro === null) ? 'active' : '' ?>">
        <i class="fas fa-users"></i>
        <span>Todos</span>
        <span class="badge-pill"><?= $conteos['total'] ?? 0 ?></span>
    </a>

    <a href="<?= BASE_URL ?>?action=usuarios&rol=1" 
       class="role-tab-btn <?= ($rolFiltro === 1) ? 'active' : '' ?>">
        <i class="fas fa-user-shield"></i>
        <span>Administradores</span>
        <span class="badge-pill"><?= $conteos['administradores'] ?? 0 ?></span>
    </a>

    <a href="<?= BASE_URL ?>?action=usuarios&rol=2" 
       class="role-tab-btn <?= ($rolFiltro === 2) ? 'active' : '' ?>">
        <i class="fas fa-chalkboard-teacher"></i>
        <span>Instructores</span>
        <span class="badge-pill"><?= $conteos['instructores'] ?? 0 ?></span>
    </a>

    <a href="<?= BASE_URL ?>?action=usuarios&rol=3" 
       class="role-tab-btn <?= ($rolFiltro === 3) ? 'active' : '' ?>">
        <i class="fas fa-graduation-cap"></i>
        <span>Aprendices</span>
        <span class="badge-pill"><?= $conteos['aprendices'] ?? 0 ?></span>
    </a>
</div>

<!-- ── BARRA DE HERRAMIENTAS Y BÚSQUEDA ───────────────────────────── -->
<div class="toolbar">
    <div class="toolbar-left">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" 
                   placeholder="Buscar por documento, nombre, correo, ficha..." 
                   data-search-table="tablaUsuarios"
                   style="min-width: 320px;">
        </div>
    </div>
    <div class="toolbar-right">
        <a href="<?= BASE_URL ?>?action=usuarios/crear" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nuevo Usuario
        </a>
    </div>
</div>

<!-- ── TABLA DE USUARIOS ─────────────────────────────────────────── -->
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
                    <th>Detalles / Formación</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $u): ?>
                <?php 
                    $id = $u['id'] ?? $u['id_usuario'] ?? 0;
                    $identificacion = $u['identificacion'] ?? $u['num_documento'] ?? '';
                    $email = $u['email'] ?? $u['correo'] ?? '';
                    $rolId = (int) ($u['Rol_id'] ?? $u['id_rol'] ?? 0);
                    $nombreRol = $u['nombre_rol'] ?? '';
                    $estado = $u['estado'] ?? 'Activo';
                    $llavero = $u['codigo_llavero'] ?? $u['codigo_rfid'] ?? '';
                    $codigoFicha = $u['codigo_ficha'] ?? '';
                    $nombrePrograma = $u['nombre_programa'] ?? '';
                ?>
                <tr>
                    <td><?= $id ?></td>
                    <td><strong><?= htmlspecialchars($identificacion) ?></strong></td>
                    <td><?= htmlspecialchars($u['nombre'] . ' ' . $u['apellido']) ?></td>
                    <td><?= htmlspecialchars($email) ?></td>
                    <td>
                        <?php
                            $badgeClass = match ($rolId) {
                                1 => 'badge-green',
                                2 => 'badge-blue',
                                3 => 'badge-purple',
                                default => 'badge-secondary'
                            };
                            $roleIcon = match ($rolId) {
                                1 => 'fa-user-shield',
                                2 => 'fa-chalkboard-teacher',
                                3 => 'fa-graduation-cap',
                                default => 'fa-user'
                            };
                        ?>
                        <span class="badge <?= $badgeClass ?>">
                            <i class="fas <?= $roleIcon ?>"></i>
                            <?= htmlspecialchars($nombreRol) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($rolId === 3): ?>
                            <!-- Información específica del Aprendiz -->
                            <div style="display: flex; flex-direction: column; gap: 4px;">
                                <?php if (!empty($codigoFicha)): ?>
                                    <span class="meta-chip" title="Programa: <?= htmlspecialchars($nombrePrograma) ?>">
                                        <i class="fas fa-layer-group"></i> Ficha: <strong><?= htmlspecialchars($codigoFicha) ?></strong>
                                    </span>
                                <?php else: ?>
                                    <span class="text-secondary" style="font-size: 0.75rem;">Sin ficha</span>
                                <?php endif; ?>

                                <?php if (!empty($llavero)): ?>
                                    <span class="meta-chip rfid-chip" title="UID RFID del llavero/tarjeta">
                                        <i class="fas fa-wifi"></i> RFID: <?= htmlspecialchars($llavero) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-secondary" style="font-size: 0.75rem;">
                                        <i class="fas fa-wifi-slash"></i> Sin RFID
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <span class="text-secondary" style="font-size: 0.85rem;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge badge-<?= strtolower($estado) ?>">
                            <?= htmlspecialchars($estado) ?>
                        </span>
                    </td>
                    <td>
                        <div class="btn-group">
                            <a href="<?= BASE_URL ?>?action=usuarios/editar&id=<?= $id ?>" 
                               class="btn btn-sm btn-secondary" title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php if ($id != Auth::getUserId()): ?>
                            <a href="<?= BASE_URL ?>?action=usuarios/eliminar&id=<?= $id ?>" 
                               class="btn btn-sm btn-danger" 
                               data-confirm="¿Está seguro de eliminar al usuario <?= htmlspecialchars($u['nombre'] . ' ' . $u['apellido']) ?>?"
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
        <?php if ($rolFiltro !== null): ?>
            <p>No se encontraron usuarios registrados en la categoría seleccionada.</p>
            <div style="display: flex; gap: 8px; justify-content: center; margin-top: 1rem;">
                <a href="<?= BASE_URL ?>?action=usuarios" class="btn btn-secondary">
                    <i class="fas fa-list"></i> Ver todos los usuarios
                </a>
                <a href="<?= BASE_URL ?>?action=usuarios/crear" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Registrar nuevo usuario
                </a>
            </div>
        <?php else: ?>
            <p>No hay usuarios registrados en el sistema.</p>
            <a href="<?= BASE_URL ?>?action=usuarios/crear" class="btn btn-primary mt-2">
                <i class="fas fa-plus"></i> Crear primer usuario
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
