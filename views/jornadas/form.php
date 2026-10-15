<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<?php
$jornadaId  = $jornada['id'] ?? 0;
$nombre     = $jornada['nombre'] ?? '';
$horaInicio = isset($jornada['hora_inicio']) ? substr($jornada['hora_inicio'], 0, 5) : '';
$horaFin    = isset($jornada['hora_fin']) ? substr($jornada['hora_fin'], 0, 5) : '';
?>

<div class="card" style="max-width: 600px;">
    <form method="POST" action="<?= BASE_URL ?>?action=jornadas/guardar">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <input type="hidden" name="id" value="<?= (int) $jornadaId ?>">

        <div class="form-group">
            <label class="form-label" for="nombre">Nombre *</label>
            <input type="text" id="nombre" name="nombre" class="form-control"
                   value="<?= htmlspecialchars($nombre) ?>" required maxlength="45"
                   placeholder="Ej: Mañana, Tarde, Noche">
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="hora_inicio">Hora inicio *</label>
                <input type="time" id="hora_inicio" name="hora_inicio" class="form-control"
                       value="<?= htmlspecialchars($horaInicio) ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="hora_fin">Hora fin *</label>
                <input type="time" id="hora_fin" name="hora_fin" class="form-control"
                       value="<?= htmlspecialchars($horaFin) ?>" required>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> <?= $jornada ? 'Actualizar' : 'Crear' ?> Jornada
            </button>
            <a href="<?= BASE_URL ?>?action=jornadas" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Cancelar
            </a>
        </div>
    </form>
</div>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
