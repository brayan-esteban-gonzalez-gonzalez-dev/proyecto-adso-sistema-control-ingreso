<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<div class="card" style="max-width: 700px;">
    <form method="POST" action="<?= BASE_URL ?>?action=usuarios/guardar">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <input type="hidden" name="id_usuario" value="<?= $usuario['id_usuario'] ?? 0 ?>">

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="num_documento">Número de Documento *</label>
                <input type="text" id="num_documento" name="num_documento" class="form-control" 
                       value="<?= htmlspecialchars($usuario['num_documento'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="id_rol">Rol *</label>
                <select id="id_rol" name="id_rol" class="form-control" required>
                    <?php foreach ($roles as $rol): ?>
                    <option value="<?= $rol['id_rol'] ?>" 
                            <?= (($usuario['id_rol'] ?? 3) == $rol['id_rol']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($rol['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="nombre">Nombre *</label>
                <input type="text" id="nombre" name="nombre" class="form-control" 
                       value="<?= htmlspecialchars($usuario['nombre'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="apellido">Apellido *</label>
                <input type="text" id="apellido" name="apellido" class="form-control" 
                       value="<?= htmlspecialchars($usuario['apellido'] ?? '') ?>" required>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="correo">Correo Electrónico *</label>
                <input type="email" id="correo" name="correo" class="form-control" 
                       value="<?= htmlspecialchars($usuario['correo'] ?? '') ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="estado">Estado</label>
                <select id="estado" name="estado" class="form-control">
                    <option value="Activo" <?= (($usuario['estado'] ?? 'Activo') === 'Activo') ? 'selected' : '' ?>>Activo</option>
                    <option value="Inactivo" <?= (($usuario['estado'] ?? '') === 'Inactivo') ? 'selected' : '' ?>>Inactivo</option>
                </select>
            </div>
        </div>

        <div class="form-group">
            <label class="form-label" for="password">
                Contraseña <?= $usuario ? '(dejar vacío para no cambiar)' : '*' ?>
            </label>
            <input type="password" id="password" name="password" class="form-control" 
                   <?= !$usuario ? 'required' : '' ?> minlength="6"
                   placeholder="<?= $usuario ? 'Dejar vacío para mantener la actual' : 'Mínimo 6 caracteres' ?>">
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> <?= $usuario ? 'Actualizar' : 'Crear' ?> Usuario
            </button>
            <a href="<?= BASE_URL ?>?action=usuarios" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Cancelar
            </a>
        </div>
    </form>
</div>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
