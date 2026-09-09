<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<div class="card" style="max-width: 750px;">
    <!-- Detalle de la excusa -->
    <div class="excusa-detail">
        <div class="detail-item">
            <div class="detail-label">Aprendiz</div>
            <div class="detail-value"><?= htmlspecialchars($excusa['nombre'] . ' ' . $excusa['apellido']) ?></div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Documento</div>
            <div class="detail-value"><?= htmlspecialchars($excusa['num_documento']) ?></div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Ficha</div>
            <div class="detail-value"><?= htmlspecialchars($excusa['codigo_ficha']) ?> — <?= htmlspecialchars($excusa['nombre_programa']) ?></div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Estado Actual</div>
            <div class="detail-value">
                <span class="badge badge-<?= strtolower($excusa['estado']) ?>"><?= $excusa['estado'] ?></span>
            </div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Período</div>
            <div class="detail-value"><?= $excusa['fecha_inicio'] ?> — <?= $excusa['fecha_fin'] ?></div>
        </div>
        <div class="detail-item">
            <div class="detail-label">Archivo Adjunto</div>
            <div class="detail-value">
                <a href="<?= BASE_URL . $excusa['archivo_adjunto'] ?>" target="_blank" class="btn btn-sm btn-secondary">
                    <i class="fas fa-paperclip"></i> Ver archivo
                </a>
            </div>
        </div>
        <div class="detail-item full-width">
            <div class="detail-label">Motivo</div>
            <div class="detail-value"><?= nl2br(htmlspecialchars($excusa['motivo'])) ?></div>
        </div>
    </div>

    <?php if ($excusa['estado'] === 'Pendiente'): ?>
    <hr style="border-color: var(--border-color); margin: 24px 0;">

    <!-- Formulario de revisión -->
    <form method="POST" action="<?= BASE_URL ?>?action=excusas/procesar">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <input type="hidden" name="id_excusa" value="<?= $excusa['id_excusa'] ?>">

        <div class="form-group">
            <label class="form-label" for="comentario_revision">Comentario de Revisión</label>
            <textarea id="comentario_revision" name="comentario_revision" class="form-control" rows="3"
                      placeholder="Observaciones sobre la excusa (opcional)..."></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" name="accion" value="aprobar" class="btn btn-primary">
                <i class="fas fa-check"></i> Aprobar Excusa
            </button>
            <button type="submit" name="accion" value="rechazar" class="btn btn-danger">
                <i class="fas fa-times"></i> Rechazar
            </button>
            <a href="<?= BASE_URL ?>?action=excusas" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </form>
    <?php else: ?>
    <hr style="border-color: var(--border-color); margin: 24px 0;">
    <div class="excusa-detail">
        <div class="detail-item">
            <div class="detail-label">Revisado por</div>
            <div class="detail-value"><?= htmlspecialchars($excusa['nombre_revisor'] ?? '—') ?></div>
        </div>
        <div class="detail-item full-width">
            <div class="detail-label">Comentario de Revisión</div>
            <div class="detail-value"><?= $excusa['comentario_revision'] ? nl2br(htmlspecialchars($excusa['comentario_revision'])) : '<span class="text-muted">Sin comentario</span>' ?></div>
        </div>
    </div>
    <div class="form-actions">
        <a href="<?= BASE_URL ?>?action=excusas" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver
        </a>
    </div>
    <?php endif; ?>
</div>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
