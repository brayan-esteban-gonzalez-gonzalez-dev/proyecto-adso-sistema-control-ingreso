<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<div class="card" style="max-width: 680px; margin: 0 auto;">
    <div style="margin-bottom: 20px;">
        <h3 style="font-size: 1.25rem; font-weight: 600; color: var(--text-primary); margin-bottom: 6px;">
            <i class="fas fa-notes-medical" style="color: var(--accent-green); margin-right: 8px;"></i>
            Registro de Excusa Médica
        </h3>
        <p style="font-size: 0.875rem; color: var(--text-muted); margin: 0;">
            Seleccione la inasistencia previa registrada por el sistema para adjuntar su soporte médico correspondiente.
        </p>
    </div>

    <?php if (empty($inasistencias)): ?>
        <div style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.25); border-radius: 8px; padding: 24px; text-align: center;">
            <div style="font-size: 2.5rem; color: #3b82f6; margin-bottom: 12px;">
                <i class="fas fa-check-circle"></i>
            </div>
            <h4 style="color: var(--text-primary); font-size: 1.1rem; margin-bottom: 8px;">
                No tiene inasistencias pendientes por justificar
            </h4>
            <p style="color: var(--text-muted); font-size: 0.875rem; margin-bottom: 20px;">
                Actualmente no cuenta con registros de inasistencia sin justificar en el sistema.
            </p>
            <a href="<?= BASE_URL ?>?action=excusas" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver a Mis Excusas
            </a>
        </div>
    <?php else: ?>
        <form method="POST" action="<?= BASE_URL ?>?action=excusas/guardar" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

            <!-- Selector de Inasistencia Obligatorio -->
            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label" for="ingreso_asistencia_id" style="font-weight: 500;">
                    Inasistencia a Justificar *
                </label>
                <select name="ingreso_asistencia_id" id="ingreso_asistencia_id" class="form-select form-control" required style="width: 100%;">
                    <option value="">-- Seleccione la inasistencia registrada --</option>
                    <?php foreach ($inasistencias as $ina): ?>
                        <?php
                            $fechaFormateada = date('d/m/Y', strtotime($ina['fecha']));
                            $horario = (!empty($ina['sesion_hora_inicio']) && !empty($ina['sesion_hora_fin']))
                                ? substr($ina['sesion_hora_inicio'], 0, 5) . ' a ' . substr($ina['sesion_hora_fin'], 0, 5)
                                : 'Jornada formativa';
                            $ficha = htmlspecialchars($ina['codigo_ficha'] ?? 'Sin Ficha');
                            $competencia = htmlspecialchars($ina['nombre_competencia'] ?? 'Sesión formativa');
                        ?>
                        <option value="<?= (int) $ina['id'] ?>">
                            <?= $fechaFormateada ?> — Ficha <?= $ficha ?> (<?= $horario ?> | <?= $competencia ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="form-hint" style="color: var(--text-muted); display: block; margin-top: 6px; font-size: 0.8rem;">
                    <i class="fas fa-info-circle"></i> Solo se listan las fechas en las que se registró una inasistencia formal sin excusa en trámite.
                </small>
            </div>

            <!-- Motivo / Descripción -->
            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label" for="motivo" style="font-weight: 500;">
                    Motivo / Descripción de la Justificación *
                </label>
                <textarea id="motivo" name="motivo" class="form-control" rows="4" required
                          placeholder="Describa el diagnóstico médico, entidad prestadora de salud (EPS/IPS) y observaciones de la incapacidad..."></textarea>
            </div>

            <!-- Archivo Adjunto -->
            <div class="form-group" style="margin-bottom: 24px;">
                <label class="form-label" style="font-weight: 500;">
                    Certificado o Incapacidad Médica * (PDF, JPG, PNG — Máx. 2 MB)
                </label>
                <div class="file-upload" id="fileUploadZone">
                    <input type="file" name="archivo" id="archivoInput" accept=".pdf,.jpg,.jpeg,.png" required>
                    <div class="file-upload-icon">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </div>
                    <div class="file-upload-text" id="fileUploadText">
                        Haga clic o arrastre el archivo aquí
                    </div>
                    <div class="file-upload-hint">Formatos soportados: PDF, JPG, PNG — Tamaño máximo: 2 MB</div>
                </div>
            </div>

            <div class="form-actions" style="display: flex; gap: 12px; justify-content: flex-end;">
                <a href="<?= BASE_URL ?>?action=excusas" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-paper-plane"></i> Enviar Excusa
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
// Indicador dinámico de archivo seleccionado y validación en cliente
document.getElementById('archivoInput')?.addEventListener('change', function() {
    const text = document.getElementById('fileUploadText');
    if (this.files && this.files.length > 0) {
        const file = this.files[0];
        const maxBytes = 2 * 1024 * 1024; // 2MB
        if (file.size > maxBytes) {
            alert('El archivo seleccionado supera el límite de 2 MB. Por favor seleccione uno más liviano.');
            this.value = '';
            text.textContent = 'Haga clic o arrastre el archivo aquí';
            text.style.color = '';
            return;
        }
        text.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
        text.style.color = 'var(--accent-green)';
    }
});
</script>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
