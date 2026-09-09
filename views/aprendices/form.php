<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<div class="card" style="max-width: 700px;">
    <form method="POST" action="<?= BASE_URL ?>?action=aprendices/guardar">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <input type="hidden" name="id_aprendiz" value="<?= $aprendiz['id_aprendiz'] ?? 0 ?>">

        <h3 style="font-size: 0.9rem; color: var(--accent-green); margin-bottom: 16px;">
            <i class="fas fa-user"></i> Datos Personales
        </h3>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="num_documento">Número de Documento *</label>
                <input type="text" id="num_documento" name="num_documento" class="form-control" 
                       value="<?= htmlspecialchars($aprendiz['num_documento'] ?? '') ?>" 
                       <?= $aprendiz ? 'readonly' : 'required' ?>>
            </div>
            <div class="form-group">
                <label class="form-label" for="correo">Correo Electrónico *</label>
                <input type="email" id="correo" name="correo" class="form-control" 
                       value="<?= htmlspecialchars($aprendiz['correo'] ?? '') ?>" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="nombre">Nombre *</label>
                <input type="text" id="nombre" name="nombre" class="form-control" 
                       value="<?= htmlspecialchars($aprendiz['nombre'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="apellido">Apellido *</label>
                <input type="text" id="apellido" name="apellido" class="form-control" 
                       value="<?= htmlspecialchars($aprendiz['apellido'] ?? '') ?>" required>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="password">
                Contraseña <?= $aprendiz ? '(dejar vacío para no cambiar)' : '*' ?>
            </label>
            <input type="password" id="password" name="password" class="form-control" 
                   <?= !$aprendiz ? 'required' : '' ?> minlength="6"
                   placeholder="<?= $aprendiz ? 'Dejar vacío para mantener la actual' : 'Mínimo 6 caracteres' ?>">
        </div>

        <hr style="border-color: var(--border-color); margin: 24px 0;">

        <h3 style="font-size: 0.9rem; color: var(--accent-blue); margin-bottom: 16px;">
            <i class="fas fa-graduation-cap"></i> Datos Académicos y RFID
        </h3>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="id_ficha">Ficha *</label>
                <select id="id_ficha" name="id_ficha" class="form-control" required>
                    <option value="">— Seleccionar ficha —</option>
                    <?php foreach ($fichas as $f): ?>
                    <option value="<?= $f['id_ficha'] ?>"
                            <?= (($aprendiz['id_ficha'] ?? 0) == $f['id_ficha']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($f['codigo_ficha'] . ' — ' . $f['nombre_programa']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="codigo_rfid">Código RFID</label>
                <input type="text" id="codigo_rfid" name="codigo_rfid" class="form-control" 
                       value="<?= htmlspecialchars($aprendiz['codigo_rfid'] ?? '') ?>"
                       placeholder="UID del llavero/tarjeta RFID">
            </div>
        </div>

        <?php if ($aprendiz): ?>
        <div class="form-group">
            <label class="form-label" for="estado">Estado</label>
            <select id="estado" name="estado" class="form-control">
                <option value="Activo" <?= ($aprendiz['estado'] === 'Activo') ? 'selected' : '' ?>>Activo</option>
                <option value="Inactivo" <?= ($aprendiz['estado'] === 'Inactivo') ? 'selected' : '' ?>>Inactivo</option>
            </select>
        </div>
        <?php endif; ?>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> <?= $aprendiz ? 'Actualizar' : 'Registrar' ?> Aprendiz
            </button>
            <a href="<?= BASE_URL ?>?action=aprendices" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Cancelar
            </a>
        </div>
    </form>
</div>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
