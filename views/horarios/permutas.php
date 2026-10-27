<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<div class="toolbar" style="margin-bottom: 20px;">
    <div class="toolbar-left">
        <h2 style="margin: 0; font-size: 1.25rem;">
            <i class="fas fa-arrows-rotate" style="color: var(--accent-green); margin-right: 8px;"></i>
            <?= htmlspecialchars($pageTitle) ?>
        </h2>
    </div>
    <div class="toolbar-right">
        <a href="<?= BASE_URL ?>?action=horarios" class="btn btn-secondary btn-sm">
            <i class="fas fa-calendar-alt"></i> Ver Horarios
        </a>
    </div>
</div>

<?php if (Auth::isInstructor() && !Auth::isAdmin()): ?>
<!-- Card para Proponer Nueva Permuta -->
<div class="card mb-4" style="margin-bottom: 25px;">
    <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 15px;">
        <h3 style="margin: 0; font-size: 1.1rem; color: #fff;">
            <i class="fas fa-plus-circle" style="color: var(--accent-green); margin-right: 6px;"></i> Proponer Intercambio de Sesión (Permuta)
        </h3>
    </div>
    <form method="POST" action="<?= BASE_URL ?>?action=horarios/proponerPermuta" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">

        <div class="form-group">
            <label for="sesion_origen_id" style="display: block; margin-bottom: 6px; font-weight: 500;">
                Mi Sesión que deseo ceder / intercambiar: <span class="text-danger">*</span>
            </label>
            <select name="sesion_origen_id" id="sesion_origen_id" class="form-control" required style="width: 100%;">
                <option value="">-- Seleccionar mi sesión --</option>
                <?php foreach ($misSesiones as $ms): ?>
                    <option value="<?= (int)$ms['id'] ?>">
                        Ficha <?= htmlspecialchars($ms['codigo_ficha']) ?> | <?= htmlspecialchars(date('d/m/Y', strtotime($ms['fecha']))) ?> (<?= htmlspecialchars(substr($ms['hora_inicio'], 0, 5)) ?> - <?= htmlspecialchars(substr($ms['hora_fin'], 0, 5)) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="sesion_destino_id" style="display: block; margin-bottom: 6px; font-weight: 500;">
                ID de la Sesión destino (del otro instructor): <span class="text-danger">*</span>
            </label>
            <input type="number" name="sesion_destino_id" id="sesion_destino_id" class="form-control" required 
                   placeholder="Ej: 142 (visible en tabla de Horarios)" style="width: 100%;">
            <small class="text-muted">Consulta el ID de la sesión del colega en el listado de <a href="<?= BASE_URL ?>?action=horarios" target="_blank" style="color: var(--accent-green);">Horarios</a>.</small>
        </div>

        <div class="form-group" style="grid-column: 1 / -1;">
            <label for="motivo" style="display: block; margin-bottom: 6px; font-weight: 500;">
                Motivo de la permuta: <span class="text-danger">*</span>
            </label>
            <textarea name="motivo" id="motivo" class="form-control" required rows="2" 
                      placeholder="Indica la razón académica o justificación del intercambio..." style="width: 100%;"></textarea>
        </div>

        <div style="grid-column: 1 / -1; display: flex; justify-content: flex-end;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-paper-plane"></i> Enviar Solicitud de Permuta
            </button>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Bandeja de Solicitudes Recibidas (Pendientes) -->
<?php if (!Auth::isAdmin()): ?>
<div class="card mb-4" style="margin-bottom: 25px;">
    <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 15px;">
        <h3 style="margin: 0; font-size: 1.1rem; color: #fff;">
            <i class="fas fa-inbox" style="color: var(--accent-blue); margin-right: 6px;"></i> Solicitudes Recibidas (Pendientes de Aprobación)
        </h3>
    </div>
    <?php if (!empty($recibidas)): ?>
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Solicitante</th>
                    <th>Sesión Ofrecida</th>
                    <th>Mi Sesión a Entregar</th>
                    <th>Motivo</th>
                    <th>Fecha Solicitud</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recibidas as $rec): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($rec['nombre_solicitante']) ?></strong></td>
                    <td>
                        <span class="badge badge-purple">Ficha <?= htmlspecialchars($rec['ficha_origen_codigo']) ?></span><br>
                        <small><?= htmlspecialchars(date('d/m/Y', strtotime($rec['fecha_origen']))) ?> (<?= htmlspecialchars(substr($rec['hora_ini_origen'], 0, 5)) ?>-<?= htmlspecialchars(substr($rec['hora_fin_origen'], 0, 5)) ?>)</small>
                    </td>
                    <td>
                        <span class="badge badge-blue">Ficha <?= htmlspecialchars($rec['ficha_destino_codigo']) ?></span><br>
                        <small><?= htmlspecialchars(date('d/m/Y', strtotime($rec['fecha_destino']))) ?> (<?= htmlspecialchars(substr($rec['hora_ini_destino'], 0, 5)) ?>-<?= htmlspecialchars(substr($rec['hora_fin_destino'], 0, 5)) ?>)</small>
                    </td>
                    <td><?= htmlspecialchars($rec['motivo'] ?? '-') ?></td>
                    <td><small class="text-muted"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($rec['fecha_solicitud']))) ?></small></td>
                    <td>
                        <form method="POST" action="<?= BASE_URL ?>?action=horarios/responderPermuta" style="display: flex; gap: 6px;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="permuta_id" value="<?= (int)$rec['id'] ?>">
                            <button type="submit" name="decision" value="aprobar" class="btn btn-sm btn-success" title="Aprobar Permuta">
                                <i class="fas fa-check"></i>
                            </button>
                            <button type="submit" name="decision" value="rechazar" class="btn btn-sm btn-danger" title="Rechazar Permuta"
                                    onclick="return confirm('¿Rechazar esta solicitud de permuta?');">
                                <i class="fas fa-times"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p class="text-muted" style="margin: 0; padding: 10px 0;">No tienes solicitudes de permuta pendientes por responder.</p>
    <?php endif; ?>
</div>

<!-- Solicitudes Enviadas -->
<div class="card">
    <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 15px;">
        <h3 style="margin: 0; font-size: 1.1rem; color: #fff;">
            <i class="fas fa-paper-plane" style="color: var(--accent-purple); margin-right: 6px;"></i> Mis Solicitudes Enviadas
        </h3>
    </div>
    <?php if (!empty($enviadas)): ?>
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Instructor Receptor</th>
                    <th>Mi Sesión</th>
                    <th>Sesión Solicitada</th>
                    <th>Motivo</th>
                    <th>Estado</th>
                    <th>Fecha Solicitud</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($enviadas as $env): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($env['nombre_receptor']) ?></strong></td>
                    <td>
                        <span class="badge badge-purple">Ficha <?= htmlspecialchars($env['ficha_origen_codigo']) ?></span><br>
                        <small><?= htmlspecialchars(date('d/m/Y', strtotime($env['fecha_origen']))) ?> (<?= htmlspecialchars(substr($env['hora_ini_origen'], 0, 5)) ?>-<?= htmlspecialchars(substr($env['hora_fin_origen'], 0, 5)) ?>)</small>
                    </td>
                    <td>
                        <span class="badge badge-blue">Ficha <?= htmlspecialchars($env['ficha_destino_codigo']) ?></span><br>
                        <small><?= htmlspecialchars(date('d/m/Y', strtotime($env['fecha_destino']))) ?> (<?= htmlspecialchars(substr($env['hora_ini_destino'], 0, 5)) ?>-<?= htmlspecialchars(substr($env['hora_fin_destino'], 0, 5)) ?>)</small>
                    </td>
                    <td><?= htmlspecialchars($env['motivo'] ?? '-') ?></td>
                    <td>
                        <?php if ($env['estado'] === 'Aprobada'): ?>
                            <span class="badge badge-green">Aprobada</span>
                        <?php elseif ($env['estado'] === 'Rechazada'): ?>
                            <span class="badge badge-danger">Rechazada</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">Pendiente</span>
                        <?php endif; ?>
                    </td>
                    <td><small class="text-muted"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($env['fecha_solicitud']))) ?></small></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p class="text-muted" style="margin: 0; padding: 10px 0;">No has enviado solicitudes de permuta recientemente.</p>
    <?php endif; ?>
</div>

<?php else: ?>
<!-- Vista de Administrador: Todas las Permutas -->
<div class="card">
    <div class="card-header" style="border-bottom: 1px solid var(--border-color); padding-bottom: 12px; margin-bottom: 15px;">
        <h3 style="margin: 0; font-size: 1.1rem; color: #fff;">
            <i class="fas fa-list-check" style="color: var(--accent-green); margin-right: 6px;"></i> Auditoría General de Permutas (Administrador)
        </h3>
    </div>
    <?php if (!empty($permutas)): ?>
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Solicitante</th>
                    <th>Receptor</th>
                    <th>Sesión Origen</th>
                    <th>Sesión Destino</th>
                    <th>Motivo</th>
                    <th>Estado</th>
                    <th>Fecha Solicitud</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($permutas as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['nombre_solicitante']) ?></td>
                    <td><?= htmlspecialchars($p['nombre_receptor']) ?></td>
                    <td>
                        <span class="badge badge-purple">Ficha <?= htmlspecialchars($p['ficha_origen_codigo']) ?></span><br>
                        <small><?= htmlspecialchars(date('d/m/Y', strtotime($p['fecha_origen']))) ?> (<?= htmlspecialchars(substr($p['hora_ini_origen'], 0, 5)) ?>-<?= htmlspecialchars(substr($p['hora_fin_origen'], 0, 5)) ?>)</small>
                    </td>
                    <td>
                        <span class="badge badge-blue">Ficha <?= htmlspecialchars($p['ficha_destino_codigo']) ?></span><br>
                        <small><?= htmlspecialchars(date('d/m/Y', strtotime($p['fecha_destino']))) ?> (<?= htmlspecialchars(substr($p['hora_ini_destino'], 0, 5)) ?>-<?= htmlspecialchars(substr($p['hora_fin_destino'], 0, 5)) ?>)</small>
                    </td>
                    <td><?= htmlspecialchars($p['motivo'] ?? '-') ?></td>
                    <td>
                        <?php if ($p['estado'] === 'Aprobada'): ?>
                            <span class="badge badge-green">Aprobada</span>
                        <?php elseif ($p['estado'] === 'Rechazada'): ?>
                            <span class="badge badge-danger">Rechazada</span>
                        <?php else: ?>
                            <span class="badge badge-secondary">Pendiente</span>
                        <?php endif; ?>
                    </td>
                    <td><small class="text-muted"><?= htmlspecialchars(date('d/m/Y H:i', strtotime($p['fecha_solicitud']))) ?></small></td>
                    <td>
                        <?php if ($p['estado'] === 'Pendiente'): ?>
                        <form method="POST" action="<?= BASE_URL ?>?action=horarios/responderPermuta" style="display: flex; gap: 6px;">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="permuta_id" value="<?= (int)$p['id'] ?>">
                            <button type="submit" name="decision" value="aprobar" class="btn btn-sm btn-success" title="Aprobar Permuta como Administrador">
                                <i class="fas fa-check"></i>
                            </button>
                            <button type="submit" name="decision" value="rechazar" class="btn btn-sm btn-danger" title="Rechazar Permuta"
                                    onclick="return confirm('¿Rechazar esta solicitud de permuta?');">
                                <i class="fas fa-times"></i>
                            </button>
                        </form>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p class="text-muted" style="margin: 0; padding: 10px 0;">No se registran solicitudes de permuta en el sistema.</p>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
