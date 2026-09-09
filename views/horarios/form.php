<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<div class="card" style="max-width: 600px;">
    <form method="POST" action="<?= BASE_URL ?>?action=horarios/guardar">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <input type="hidden" name="id_horario" value="<?= $horario['id_horario'] ?? 0 ?>">
        <input type="hidden" name="id_ficha" value="<?= $horario['id_ficha'] ?? ($idFicha ?? 0) ?>">

        <div class="form-group">
            <label class="form-label" for="dia_semana">Día de la Semana *</label>
            <select id="dia_semana" name="dia_semana" class="form-control" required>
                <option value="">— Seleccionar —</option>
                <?php foreach (['Lunes','Martes','Miercoles','Jueves','Viernes','Sabado'] as $dia): ?>
                <option value="<?= $dia ?>" <?= (($horario['dia_semana'] ?? '') === $dia) ? 'selected' : '' ?>>
                    <?= $dia ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="hora_entrada">Hora de Entrada *</label>
                <input type="time" id="hora_entrada" name="hora_entrada" class="form-control" 
                       value="<?= $horario['hora_entrada'] ?? '07:00' ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="hora_salida">Hora de Salida *</label>
                <input type="time" id="hora_salida" name="hora_salida" class="form-control" 
                       value="<?= $horario['hora_salida'] ?? '13:00' ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="tolerancia_minutos">Tolerancia (minutos)</label>
            <input type="number" id="tolerancia_minutos" name="tolerancia_minutos" class="form-control" 
                   value="<?= $horario['tolerancia_minutos'] ?? 15 ?>" min="0" max="60">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> <?= $horario ? 'Actualizar' : 'Crear' ?> Horario
            </button>
            <a href="<?= BASE_URL ?>?action=horarios&id_ficha=<?= $horario['id_ficha'] ?? ($idFicha ?? 0) ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Cancelar
            </a>
        </div>
    </form>
</div>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
