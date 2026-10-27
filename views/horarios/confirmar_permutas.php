<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<div class="toolbar">
    <div class="toolbar-left">
        <a href="<?= BASE_URL ?>?action=horarios/importar" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Cancelar y volver
        </a>
    </div>
</div>

<div class="card mb-4" style="border-left: 4px solid var(--accent-yellow, #f59e0b); padding: 18px 24px; margin-bottom: 25px;">
    <div style="display: flex; align-items: center; gap: 15px;">
        <i class="fas fa-triangle-exclamation" style="font-size: 2rem; color: var(--accent-yellow, #f59e0b);"></i>
        <div>
            <h3 style="margin: 0 0 4px 0; font-size: 1.15rem; color: #fff;">
                Este Excel revierte permutas aprobadas
            </h3>
            <span class="text-muted" style="font-size: 0.9rem;">
                Si continúas, las siguientes permutas dejarán de tener efecto. No se guardará nada hasta que confirmes.
            </span>
        </div>
    </div>
</div>

<div class="card" style="margin-bottom: 25px;">
    <div class="card-header" style="margin-bottom: 15px;">
        <h3 style="font-size: 1.1rem; margin: 0;">
            <i class="fas fa-right-left" style="margin-right: 8px;"></i>
            Permutas afectadas
        </h3>
    </div>

    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Permuta</th>
                    <th>Fecha</th>
                    <th>Bloque</th>
                    <th>Ficha</th>
                    <th>Instructor actual</th>
                    <th>Motivo</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($permutasAfectadas as $p): ?>
                <tr>
                    <td>#<?= (int) $p['permuta_id'] ?></td>
                    <td><?= htmlspecialchars((string) $p['fecha']) ?></td>
                    <td><?= htmlspecialchars(substr((string) $p['hora_inicio'], 0, 5)) ?> - <?= htmlspecialchars(substr((string) $p['hora_fin'], 0, 5)) ?></td>
                    <td><?= htmlspecialchars((string) $p['codigo_ficha']) ?></td>
                    <td><?= htmlspecialchars((string) $p['instructor_actual']) ?></td>
                    <td style="font-size: 0.9rem; color: #fde68a;"><?= htmlspecialchars((string) $p['motivo']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<form action="<?= BASE_URL ?>?action=horarios/confirmarPermutas" method="POST">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Auth::generateCSRF()) ?>">
    <div class="form-actions" style="display: flex; justify-content: flex-end; gap: 12px;">
        <a href="<?= BASE_URL ?>?action=horarios/importar" class="btn btn-secondary">No importar</a>
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-check"></i> Confirmar e importar de todos modos
        </button>
    </div>
</form>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
