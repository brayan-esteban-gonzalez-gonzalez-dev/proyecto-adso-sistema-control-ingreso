<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<?php
$fichaId = $ficha['id'] ?? $ficha['id_ficha'] ?? 0;
$codigo = $ficha['codigo'] ?? $ficha['codigo_ficha'] ?? '';
$nombrePrograma = $ficha['nombre_programa'] ?? '';
$jornadaId = $ficha['jornada_id'] ?? 0;
$instructorId = $ficha['instructor_id'] ?? $ficha['id_instructor_lider'] ?? 0;
?>

<div class="card" style="max-width: 600px;">
    <form method="POST" action="<?= BASE_URL ?>?action=fichas/guardar">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <input type="hidden" name="id_ficha" value="<?= $fichaId ?>">

        <div class="form-group">
            <label class="form-label" for="codigo_ficha">Código de Ficha *</label>
            <input type="number" id="codigo_ficha" name="codigo_ficha" class="form-control" 
                   value="<?= htmlspecialchars($codigo) ?>" required
                   placeholder="Ej: 2889927">
        </div>

        <div class="form-group">
            <label class="form-label" for="nombre_programa">Programa de Formación *</label>
            <input type="text" id="nombre_programa" name="nombre_programa" class="form-control"
                   value="<?= htmlspecialchars($nombrePrograma) ?>" required
                   placeholder="Ej: Análisis y Desarrollo de Software"
                   list="programas_list" autocomplete="off">
            <datalist id="programas_list">
                <?php foreach ($programas as $prog): ?>
                <option value="<?= htmlspecialchars($prog['nombre']) ?>">
                <?php endforeach; ?>
            </datalist>
            <small class="text-muted" style="font-size: 0.85em; display: block; margin-top: 4px;">
                Ingresa el nombre del programa manualmente o selecciona uno de los existentes.
            </small>
        </div>

        <div class="form-group">
            <label class="form-label" for="jornada_id">Jornada</label>
            <select id="jornada_id" name="jornada_id" class="form-control">
                <option value="">— Seleccionar Jornada —</option>
                <?php foreach ($jornadas as $jornada): ?>
                <option value="<?= $jornada['id'] ?>" <?= ($jornadaId == $jornada['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($jornada['nombre']) ?> (<?= $jornada['hora_inicio'] ?> - <?= $jornada['hora_fin'] ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label" for="id_instructor_lider">Instructor Líder</label>
            <select id="id_instructor_lider" name="id_instructor_lider" class="form-control">
                <option value="">— Sin asignar —</option>
                <?php foreach ($instructores as $inst): ?>
                <?php 
                    $instId = $inst['id'] ?? $inst['id_usuario'];
                    $instDoc = $inst['identificacion'] ?? $inst['num_documento'] ?? '';
                ?>
                <option value="<?= $instId ?>" <?= ($instructorId == $instId) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($inst['nombre'] . ' ' . $inst['apellido']) ?> (<?= htmlspecialchars($instDoc) ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label class="form-label" for="estado">Estado</label>
            <select id="estado" name="estado" class="form-control">
                <option value="Activo" <?= (($ficha['estado'] ?? 'Activo') === 'Activo') ? 'selected' : '' ?>>Activo</option>
                <option value="Inactivo" <?= (($ficha['estado'] ?? '') === 'Inactivo') ? 'selected' : '' ?>>Inactivo</option>
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

