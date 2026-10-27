<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<?php
$id               = (int) ($excusa['id'] ?? 0);
$doc              = $excusa['identificacion'] ?? '';
$fechaRadicacion  = $excusa['fecha'] ?? '';
$fechaInasistencia = !empty($excusa['fecha_inasistencia']) ? $excusa['fecha_inasistencia'] : $fechaRadicacion;
$estado           = $excusa['estado_revision'] ?? 'Pendiente';
$evidencia        = $excusa['evidencia'] ?? '';
$esImagen         = preg_match('/\.(jpg|jpeg|png)$/i', $evidencia);
$horarioSesion    = (!empty($excusa['sesion_hora_inicio']) && !empty($excusa['sesion_hora_fin']))
    ? substr($excusa['sesion_hora_inicio'], 0, 5) . ' a ' . substr($excusa['sesion_hora_fin'], 0, 5)
    : null;
?>

<div class="card" style="max-width: 800px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 16px; margin-bottom: 20px;">
        <div>
            <h3 style="font-size: 1.25rem; font-weight: 600; color: var(--text-primary); margin: 0 0 4px 0;">
                <i class="fas fa-stethoscope" style="color: var(--accent-green); margin-right: 8px;"></i>
                Revisión de Excusa Médica #<?= $id ?>
            </h3>
            <span style="font-size: 0.85rem; color: var(--text-muted);">
                Radicada el <?= !empty($fechaRadicacion) ? date('d/m/Y', strtotime($fechaRadicacion)) : '—' ?>
            </span>
        </div>
        <div>
            <span class="badge badge-<?= strtolower($estado) ?>" style="font-size: 0.85rem; padding: 6px 14px;">
                <?= htmlspecialchars($estado) ?>
            </span>
        </div>
    </div>

    <!-- Ficha de detalles -->
    <div class="excusa-detail" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px;">
        <div class="detail-item">
            <div class="detail-label" style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600; margin-bottom: 4px;">
                Aprendiz
            </div>
            <div class="detail-value" style="font-size: 0.95rem; color: var(--text-primary); font-weight: 500;">
                <?= htmlspecialchars(($excusa['nombre'] ?? '') . ' ' . ($excusa['apellido'] ?? '')) ?>
            </div>
        </div>

        <div class="detail-item">
            <div class="detail-label" style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600; margin-bottom: 4px;">
                Documento de Identidad
            </div>
            <div class="detail-value" style="font-size: 0.95rem; color: var(--text-primary);">
                <?= htmlspecialchars((string) $doc) ?>
            </div>
        </div>

        <div class="detail-item">
            <div class="detail-label" style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600; margin-bottom: 4px;">
                Ficha y Programa
            </div>
            <div class="detail-value" style="font-size: 0.95rem; color: var(--text-primary);">
                <span class="badge badge-blue" style="margin-right: 6px;"><?= htmlspecialchars($excusa['codigo_ficha'] ?? '-') ?></span>
                <span style="font-size: 0.85rem; color: var(--text-muted);"><?= htmlspecialchars($excusa['nombre_programa'] ?? '') ?></span>
            </div>
        </div>

        <div class="detail-item" style="grid-column: 1 / -1; background: rgba(16, 185, 129, 0.05); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 8px; padding: 12px 16px;">
            <div class="detail-label" style="font-size: 0.75rem; text-transform: uppercase; color: var(--accent-green); font-weight: 600; margin-bottom: 4px;">
                <i class="fas fa-calendar-times" style="margin-right: 6px;"></i> Inasistencia por la que se Reclama
            </div>
            <div class="detail-value" style="font-size: 1rem; color: var(--text-primary); font-weight: 600;">
                Fecha: <?= date('d/m/Y', strtotime($fechaInasistencia)) ?>
                <?php if ($horarioSesion): ?>
                    <span style="font-weight: 400; font-size: 0.875rem; color: var(--text-muted); margin-left: 10px;">
                        (Horario: <?= $horarioSesion ?>)
                    </span>
                <?php endif; ?>
            </div>
            <?php if (!empty($excusa['nombre_competencia'])): ?>
                <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px;">
                    Competencia formativa: <?= htmlspecialchars($excusa['nombre_competencia']) ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="detail-item full-width" style="grid-column: 1 / -1;">
            <div class="detail-label" style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600; margin-bottom: 6px;">
                Motivo / Justificación médica declarada
            </div>
            <div class="detail-value" style="background: var(--bg-hover, rgba(255,255,255,0.03)); border: 1px solid var(--border-color); border-radius: 8px; padding: 14px; font-size: 0.9rem; color: var(--text-primary); line-height: 1.5;">
                <?= nl2br(htmlspecialchars($excusa['motivo'] ?? '')) ?>
            </div>
        </div>

        <div class="detail-item full-width" style="grid-column: 1 / -1;">
            <div class="detail-label" style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); font-weight: 600; margin-bottom: 6px;">
                Documento de Soporte Médico
            </div>
            <div class="detail-value">
                <?php if (!empty($evidencia)): ?>
                    <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                        <a href="<?= BASE_URL . htmlspecialchars($evidencia) ?>" target="_blank" class="btn btn-secondary" style="display: inline-flex; align-items: center; gap: 8px;">
                            <i class="fas fa-file-medical-alt" style="color: var(--accent-green);"></i>
                            <span>Abrir archivo en nueva pestaña</span>
                        </a>
                        <a href="<?= BASE_URL . htmlspecialchars($evidencia) ?>" download class="btn btn-secondary btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fas fa-download"></i> Descargar
                        </a>
                    </div>

                    <?php if ($esImagen): ?>
                        <div style="margin-top: 14px; border: 1px solid var(--border-color); border-radius: 8px; overflow: hidden; max-width: 450px;">
                            <a href="<?= BASE_URL . htmlspecialchars($evidencia) ?>" target="_blank">
                                <img src="<?= BASE_URL . htmlspecialchars($evidencia) ?>" alt="Evidencia Médica" style="width: 100%; height: auto; display: block; object-fit: contain; max-height: 320px; background: #000;">
                            </a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="text-muted" style="font-style: italic;">Sin archivo adjunto registrado.</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if ($estado === 'Pendiente'): ?>
        <hr style="border-color: var(--border-color); margin: 24px 0;">

        <!-- Formulario de Resolución de la Excusa -->
        <form method="POST" action="<?= BASE_URL ?>?action=excusas/procesar">
            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
            <input type="hidden" name="id_excusa" value="<?= $id ?>">

            <div class="form-group" style="margin-bottom: 20px;">
                <label class="form-label" for="comentario_revision" style="font-weight: 500;">
                    Observaciones o Respuesta de Revisión
                </label>
                <textarea id="comentario_revision" name="comentario_revision" class="form-control" rows="3"
                          placeholder="Ingrese las observaciones institucionales respecto a la aprobación o rechazo (opcional)..."></textarea>
            </div>

            <?php if (!empty($requiereDobleAprobacion)): ?>
            <div style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.25); border-radius: 6px; padding: 12px; margin-bottom: 20px; font-size: 0.85rem; color: var(--text-primary); display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-users" style="color: var(--accent-blue); font-size: 1.1rem;"></i>
                <span><strong>Regla de Co-docencia (RF-HOR-34):</strong> Esta jornada cuenta con 2 o más instructores asignados a la ficha. La excusa requiere validación de ambos instructores para exonerar la inasistencia.</span>
            </div>
            <?php endif; ?>

            <div style="background: rgba(16, 185, 129, 0.08); border: 1px solid rgba(16, 185, 129, 0.2); border-radius: 6px; padding: 12px; margin-bottom: 20px; font-size: 0.85rem; color: var(--text-primary); display: flex; align-items: center; gap: 10px;">
                <i class="fas fa-info-circle" style="color: var(--accent-green); font-size: 1.1rem;"></i>
                <span>Al <strong>Aprobar</strong>, la inasistencia se marcará formalmente como justificada y quedará exonerada del cómputo negativo en los reportes de asistencia del aprendiz.</span>
            </div>

            <div class="form-actions" style="display: flex; gap: 12px; justify-content: flex-end; align-items: center;">
                <a href="<?= BASE_URL ?>?action=excusas" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Volver
                </a>
                <button type="submit" name="accion" value="rechazar" class="btn btn-danger" onclick="return confirm('¿Está seguro de rechazar esta excusa médica?');">
                    <i class="fas fa-times"></i> Rechazar
                </button>
                <button type="submit" name="accion" value="aprobar" class="btn btn-primary">
                    <i class="fas fa-check"></i> Aprobar Excusa
                </button>
            </div>
        </form>
    <?php else: ?>
        <hr style="border-color: var(--border-color); margin: 24px 0;">
        <div style="background: var(--bg-hover, rgba(255,255,255,0.02)); border: 1px solid var(--border-color); border-radius: 8px; padding: 18px; margin-bottom: 20px;">
            <h4 style="font-size: 0.95rem; font-weight: 600; color: var(--text-primary); margin: 0 0 12px 0;">
                <i class="fas fa-clipboard-check" style="color: var(--accent-green); margin-right: 6px;"></i>
                Resolución Institucional
            </h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 12px;">
                <div>
                    <span style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); display: block;">Revisado por</span>
                    <strong style="color: var(--text-primary); font-size: 0.9rem;"><?= htmlspecialchars($excusa['nombre_revisor'] ?? '—') ?></strong>
                </div>
                <div>
                    <span style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); display: block;">Fecha de Resolución</span>
                    <span style="color: var(--text-primary); font-size: 0.9rem;">
                        <?= !empty($excusa['fecha_revision']) ? date('d/m/Y', strtotime($excusa['fecha_revision'])) : '—' ?>
                    </span>
                </div>
            </div>
            <div>
                <span style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-muted); display: block; margin-bottom: 4px;">Comentario de Revisión</span>
                <div style="font-size: 0.9rem; color: var(--text-primary);">
                    <?= !empty($excusa['comentario_revision']) ? nl2br(htmlspecialchars($excusa['comentario_revision'])) : '<span class="text-muted" style="font-style: italic;">Sin observaciones registradas</span>' ?>
                </div>
            </div>
        </div>
        <div class="form-actions" style="display: flex; justify-content: flex-end;">
            <a href="<?= BASE_URL ?>?action=excusas" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver al Listado
            </a>
        </div>
    <?php endif; ?>
</div>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
