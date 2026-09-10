<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<div class="card" style="max-width: 650px;">
    <form method="POST" action="<?= BASE_URL ?>?action=excusas/guardar" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="fecha_inicio">Fecha de Inicio *</label>
                <input type="date" id="fecha_inicio" name="fecha_inicio" class="form-control" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="fecha_fin">Fecha de Fin *</label>
                <input type="date" id="fecha_fin" name="fecha_fin" class="form-control" required>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="motivo">Motivo / Descripción *</label>
            <textarea id="motivo" name="motivo" class="form-control" rows="4" required
                      placeholder="Describa el motivo de la excusa médica..."></textarea>
        </div>

        <div class="form-group">
            <label class="form-label">Archivo Adjunto * (PDF, JPG, PNG — Máx. 5MB)</label>
            <div class="file-upload" id="fileUploadZone">
                <input type="file" name="archivo" id="archivoInput" accept=".pdf,.jpg,.jpeg,.png" required>
                <div class="file-upload-icon">
                    <i class="fas fa-cloud-upload-alt"></i>
                </div>
                <div class="file-upload-text" id="fileUploadText">
                    Haga clic o arrastre el archivo aquí
                </div>
                <div class="file-upload-hint">PDF, JPG o PNG — Máximo 5 MB</div>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-paper-plane"></i> Enviar Excusa
            </button>
            <a href="<?= BASE_URL ?>?action=excusas" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Cancelar
            </a>
        </div>
    </form>
</div>

<script>
// Mostrar nombre del archivo seleccionado
document.getElementById('archivoInput')?.addEventListener('change', function() {
    const text = document.getElementById('fileUploadText');
    if (this.files.length > 0) {
        text.textContent = this.files[0].name;
        text.style.color = 'var(--accent-green)';
    }
});
</script>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
