<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<div class="toolbar">
    <div class="toolbar-left">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Buscar aprendiz..." data-search-table="tablaAprendices">
        </div>
    </div>
    <div class="toolbar-right">
        <a href="<?= BASE_URL ?>?action=aprendices/crear" class="btn btn-primary">
            <i class="fas fa-user-plus"></i> Nuevo Aprendiz
        </a>
    </div>
</div>

<div class="card">
    <?php if (!empty($aprendices)): ?>
    <div class="table-wrapper">
        <table class="data-table" id="tablaAprendices">
            <thead>
                <tr>
                    <th>Documento</th>
                    <th>Nombre Completo</th>
                    <th>Correo</th>
                    <th>Ficha</th>
                    <th>RFID / Llavero</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($aprendices as $a): ?>
                <?php 
                    $id = $a['id'] ?? $a['id_aprendiz'] ?? 0;
                    $doc = $a['identificacion'] ?? $a['num_documento'] ?? '';
                    $correo = $a['email'] ?? $a['correo'] ?? '';
                    $ficha = $a['codigo_ficha'] ?? $a['codigo'] ?? 'Sin Ficha';
                    $rfid = $a['codigo_llavero'] ?? $a['codigo_rfid'] ?? '';
                    $estado = $a['estado'] ?? 'Activo';
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($doc) ?></strong></td>
                    <td><?= htmlspecialchars($a['nombre'] . ' ' . $a['apellido']) ?></td>
                    <td><small><?= htmlspecialchars($correo) ?></small></td>
                    <td>
                        <span class="badge badge-blue"><?= htmlspecialchars($ficha) ?></span>
                    </td>
                    <td>
                        <?php if ($rfid): ?>
                            <code style="font-size: 0.75rem; color: var(--accent-green);"><?= htmlspecialchars($rfid) ?></code>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge badge-<?= strtolower($estado) ?>">
                            <?= htmlspecialchars($estado) ?>
                        </span>
                    </td>
                    <td>
                        <div class="btn-group">
                            <a href="<?= BASE_URL ?>?action=aprendices/editar&id=<?= $id ?>" 
                               class="btn btn-sm btn-secondary" title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="<?= BASE_URL ?>?action=aprendices/eliminar&id=<?= $id ?>" 
                               class="btn btn-sm btn-danger" 
                               data-confirm="¿Eliminar al aprendiz <?= htmlspecialchars($a['nombre'] . ' ' . $a['apellido']) ?>?"
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
        <i class="fas fa-user-graduate"></i>
        <p>No hay aprendices registrados</p>
        <a href="<?= BASE_URL ?>?action=aprendices/crear" class="btn btn-primary mt-2">
            <i class="fas fa-user-plus"></i> Registrar primer aprendiz
        </a>
    </div>
    <?php endif; ?>
</div>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>

