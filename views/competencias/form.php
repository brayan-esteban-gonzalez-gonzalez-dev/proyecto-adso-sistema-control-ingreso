<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<?php
$competenciaId = $competencia['id'] ?? 0;
$programaId    = $competencia['Programa_id'] ?? 0;
$nombre        = $competencia['nombre'] ?? '';
$descripcion   = $competencia['descripcion'] ?? '';
$seleccionados = $seleccionados ?? [];
?>

<div class="card" style="max-width: 680px;">
    <form method="POST" action="<?= BASE_URL ?>?action=competencias/guardar">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <input type="hidden" name="id" value="<?= (int) $competenciaId ?>">

        <div class="form-group">
            <label class="form-label" for="Programa_id">Programa de Formación *</label>
            <select id="Programa_id" name="Programa_id" class="form-control" required>
                <option value="">— Seleccionar Programa —</option>
                <?php foreach ($programas as $prog): ?>
                    <option value="<?= (int) $prog['id'] ?>" <?= (int) $programaId === (int) $prog['id'] ? 'selected' : '' ?>>
                        <?= !empty($prog['codigo']) ? htmlspecialchars($prog['codigo']) . ' — ' : '' ?><?= htmlspecialchars($prog['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label" for="nombre">Nombre *</label>
            <input type="text" id="nombre" name="nombre" class="form-control"
                   value="<?= htmlspecialchars($nombre) ?>" required maxlength="100"
                   placeholder="Ej: Construcción de software">
        </div>

        <div class="form-group">
            <label class="form-label" for="descripcion">Descripción</label>
            <textarea id="descripcion" name="descripcion" class="form-control" maxlength="2000"><?= htmlspecialchars($descripcion) ?></textarea>
        </div>

        <div class="form-group">
            <label class="form-label" for="instructores">Instructores</label>
            <select id="instructores" name="instructores[]" class="form-control" multiple size="6">
                <?php foreach ($instructores as $inst): ?>
                    <option value="<?= (int) $inst['id'] ?>" <?= in_array((int) $inst['id'], $seleccionados, true) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($inst['nombre'] . ' ' . $inst['apellido']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <span class="text-muted" style="font-size: 0.75rem;">Puedes elegir varios. El primero queda como instructor principal.</span>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> <?= $competencia ? 'Actualizar' : 'Crear' ?> Competencia
            </button>
            <a href="<?= BASE_URL ?>?action=competencias" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Cancelar
            </a>
        </div>
    </form>
</div>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
