<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<div class="card" style="max-width: 900px; margin: 0 auto;">
    <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; margin-bottom: 20px;">
        <h2 style="margin: 0; font-size: 1.25rem;">
            <i class="fas <?= $sesion ? 'fa-edit' : 'fa-calendar-plus' ?>" style="color: var(--accent-green); margin-right: 8px;"></i>
            <?= htmlspecialchars($pageTitle) ?>
        </h2>
        <a href="<?= BASE_URL ?>?action=horarios<?= !empty($sesion['Ficha_id']) ? '&ficha_id='.$sesion['Ficha_id'] : '' ?>" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left"></i> Volver a Horarios
        </a>
    </div>

    <form method="POST" action="<?= BASE_URL ?>?action=horarios/guardar" class="form-grid">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <?php if ($sesion): ?>
            <input type="hidden" name="id" value="<?= (int)$sesion['id'] ?>">
        <?php endif; ?>

        <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px;">
            <div class="form-group">
                <label for="Ficha_id" style="display: block; margin-bottom: 6px; font-weight: 500;">
                    Ficha de Formación <span class="text-danger">*</span>
                </label>
                <select name="Ficha_id" id="Ficha_id" class="form-control" required style="width: 100%;">
                    <option value="">-- Seleccionar Ficha --</option>
                    <?php foreach ($fichas as $f): ?>
                        <option value="<?= (int)$f['id'] ?>" <?= (($sesion['Ficha_id'] ?? ($_GET['ficha_id'] ?? '')) == $f['id']) ? 'selected' : '' ?>>
                            Ficha <?= htmlspecialchars($f['codigo']) ?> — <?= htmlspecialchars($f['nombre_programa']) ?> (<?= htmlspecialchars($f['nombre_jornada'] ?? 'Sin jornada') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="Instructor_id" style="display: block; margin-bottom: 6px; font-weight: 500;">
                    Instructor Asignado <span class="text-danger">*</span>
                </label>
                <select name="Instructor_id" id="Instructor_id" class="form-control" required style="width: 100%;">
                    <option value="">-- Seleccionar Instructor --</option>
                    <?php foreach ($instructores as $inst): ?>
                        <option value="<?= (int)$inst['id'] ?>" <?= (($sesion['Instructor_id'] ?? Auth::getUserId()) == $inst['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($inst['nombre'] . ' ' . $inst['apellido']) ?> (<?= htmlspecialchars($inst['email']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 15px;">
            <div class="form-group">
                <label for="fecha" style="display: block; margin-bottom: 6px; font-weight: 500;">
                    Fecha de la Clase <span class="text-danger">*</span>
                </label>
                <input type="date" name="fecha" id="fecha" class="form-control" required 
                       value="<?= htmlspecialchars($sesion['fecha'] ?? date('Y-m-d')) ?>" style="width: 100%;">
            </div>

            <div class="form-group">
                <label for="hora_inicio" style="display: block; margin-bottom: 6px; font-weight: 500;">
                    Hora de Inicio <span class="text-danger">*</span>
                </label>
                <input type="time" name="hora_inicio" id="hora_inicio" class="form-control" required 
                       value="<?= htmlspecialchars(substr($sesion['hora_inicio'] ?? '06:00', 0, 5)) ?>" style="width: 100%;">
            </div>

            <div class="form-group">
                <label for="hora_fin" style="display: block; margin-bottom: 6px; font-weight: 500;">
                    Hora de Fin <span class="text-danger">*</span>
                </label>
                <input type="time" name="hora_fin" id="hora_fin" class="form-control" required 
                       value="<?= htmlspecialchars(substr($sesion['hora_fin'] ?? '09:00', 0, 5)) ?>" style="width: 100%;">
            </div>
        </div>

        <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px;">
            <div class="form-group">
                <label for="Competencia_id" style="display: block; margin-bottom: 6px; font-weight: 500;">
                    Competencia Académica (Opcional)
                </label>
                <select name="Competencia_id" id="Competencia_id" class="form-control" style="width: 100%;">
                    <option value="">-- Sin competencia asignada --</option>
                    <?php foreach ($competencias as $comp): ?>
                        <option value="<?= (int)$comp['id'] ?>" <?= (($sesion['Competencia_id'] ?? '') == $comp['id']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($comp['nombre']) ?> (<?= htmlspecialchars($comp['nombre_programa'] ?? '') ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="estado" style="display: block; margin-bottom: 6px; font-weight: 500;">
                    Estado de la Sesión
                </label>
                <select name="estado" id="estado" class="form-control" style="width: 100%;">
                    <option value="Activo" <?= (($sesion['estado'] ?? 'Activo') === 'Activo') ? 'selected' : '' ?>>Activo</option>
                    <option value="Cancelado" <?= (($sesion['estado'] ?? '') === 'Cancelado') ? 'selected' : '' ?>>Cancelado</option>
                    <?php if ($sesion && $sesion['estado'] === 'Permutado'): ?>
                        <option value="Permutado" selected>Permutado</option>
                    <?php endif; ?>
                </select>
            </div>
        </div>

        <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 15px; margin-bottom: 25px;">
            <div class="form-group">
                <label for="tipo_instructor" style="display: block; margin-bottom: 6px; font-size: 0.85rem; font-weight: 500;">Tipo / Rol</label>
                <input type="text" name="tipo_instructor" id="tipo_instructor" class="form-control" placeholder="Ej: Técnico" 
                       value="<?= htmlspecialchars($sesion['tipo_instructor'] ?? '') ?>" style="width: 100%;">
            </div>

            <div class="form-group">
                <label for="especialidad" style="display: block; margin-bottom: 6px; font-size: 0.85rem; font-weight: 500;">Especialidad</label>
                <input type="text" name="especialidad" id="especialidad" class="form-control" placeholder="Ej: Sistemas" 
                       value="<?= htmlspecialchars($sesion['especialidad'] ?? '') ?>" style="width: 100%;">
            </div>

            <div class="form-group">
                <label for="nivel" style="display: block; margin-bottom: 6px; font-size: 0.85rem; font-weight: 500;">Nivel</label>
                <input type="text" name="nivel" id="nivel" class="form-control" placeholder="Ej: Tecnólogo" 
                       value="<?= htmlspecialchars($sesion['nivel'] ?? '') ?>" style="width: 100%;">
            </div>

            <div class="form-group">
                <label for="grupo_convergente" style="display: block; margin-bottom: 6px; font-size: 0.85rem; font-weight: 500;">Grupo Convergente</label>
                <input type="text" name="grupo_convergente" id="grupo_convergente" class="form-control" placeholder="Opcional" 
                       value="<?= htmlspecialchars($sesion['grupo_convergente'] ?? '') ?>" style="width: 100%;">
            </div>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 10px; border-top: 1px solid var(--border-color); padding-top: 20px;">
            <a href="<?= BASE_URL ?>?action=horarios" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> <?= $sesion ? 'Guardar Cambios' : 'Crear Sesión' ?>
            </button>
        </div>
    </form>
</div>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
