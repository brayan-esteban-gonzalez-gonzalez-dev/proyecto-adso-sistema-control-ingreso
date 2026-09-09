<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<div class="card" style="max-width: 600px;">
    <form method="POST" action="<?= BASE_URL ?>?action=fichas/guardar">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <input type="hidden" name="id_ficha" value="<?= $ficha['id_ficha'] ?? 0 ?>">

        <div class="form-group">
            <label class="form-label" for="codigo_ficha">Código de Ficha *</label>
            <input type="text" id="codigo_ficha" name="codigo_ficha" class="form-control" 
                   value="<?= htmlspecialchars($ficha['codigo_ficha'] ?? '') ?>" required
                   placeholder="Ej: 2889927">
        </div>

        <div class="form-group">
            <label class="form-label" for="nombre_programa">Nombre del Programa *</label>
            <input type="text" id="nombre_programa" name="nombre_programa" class="form-control" 
                   value="<?= htmlspecialchars($ficha['nombre_programa'] ?? '') ?>" required
                   placeholder="Ej: Análisis y Desarrollo de Software">
        </div>

        <div class="form-group">
            <label class="form-label" for="id_instructor_lider">Instructor Líder</label>
            <select id="id_instructor_lider" name="id_instructor_lider" class="form-control">
                <option value="">— Sin asignar —</option>
                <?php foreach ($instructores as $inst): ?>
                <option value="<?= $inst['id_usuario'] ?>"
                        <?= (($ficha['id_instructor_lider'] ?? 0) == $inst['id_usuario']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($inst['nombre'] . ' ' . $inst['apellido']) ?> (<?= $inst['num_documento'] ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> <?= $ficha ? 'Actualizar' : 'Crear' ?> Ficha
            </button>
            <a href="<?= BASE_URL ?>?action=fichas" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Cancelar
            </a>
        </div>
    </form>
</div>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
