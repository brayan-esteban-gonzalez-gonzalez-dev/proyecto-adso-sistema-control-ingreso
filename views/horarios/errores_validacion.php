<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<div class="toolbar">
    <div class="toolbar-left">
        <a href="<?= BASE_URL ?>?action=horarios/importar" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver a Importar
        </a>
    </div>
    <div class="toolbar-right">
        <a href="<?= BASE_URL ?>?action=horarios" class="btn btn-secondary">
            <i class="fas fa-calendar"></i> Ir a Horarios
        </a>
    </div>
</div>

<div class="card mb-4" style="border-left: 4px solid var(--accent-red, #ef4444); padding: 18px 24px; margin-bottom: 25px;">
    <div style="display: flex; align-items: center; gap: 15px;">
        <i class="fas fa-circle-exclamation" style="font-size: 2rem; color: var(--accent-red, #ef4444);"></i>
        <div>
            <h3 style="margin: 0 0 4px 0; font-size: 1.15rem; color: #fff;">
                El archivo no se guardó
            </h3>
            <span class="text-muted" style="font-size: 0.9rem;">
                Se encontraron <?= count($errores) ?> incongruencia(s). Corrija el Excel y súbalo de nuevo.
            </span>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header" style="margin-bottom: 15px;">
        <h3 style="font-size: 1.1rem; margin: 0;">
            <i class="fas fa-list-ul" style="margin-right: 8px;"></i>
            Incongruencias detectadas
        </h3>
    </div>

    <?php if (!empty($errores)): ?>
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 60px;">#</th>
                    <th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($errores as $i => $mensaje): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td style="font-size: 0.92rem; color: #fca5a5;">
                        <?= htmlspecialchars((string) $mensaje) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <p>No se reportaron incongruencias.</p>
    </div>
    <?php endif; ?>
</div>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
