<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<?php
$userId = $usuario['id'] ?? $usuario['id_usuario'] ?? 0;
$identificacion = $usuario['identificacion'] ?? $usuario['num_documento'] ?? '';
$email = $usuario['email'] ?? $usuario['correo'] ?? '';
$userRolId = $usuario['Rol_id'] ?? $usuario['id_rol'] ?? 2;
?>

<div class="card" style="max-width: 700px;">
    <form method="POST" action="<?= BASE_URL ?>?action=usuarios/guardar">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <input type="hidden" name="id_usuario" value="<?= $userId ?>">

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="num_documento">Número de Documento *</label>
                <input type="text" id="num_documento" name="num_documento" class="form-control" 
                       value="<?= htmlspecialchars($identificacion) ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="id_rol">Rol *</label>
                <select id="id_rol" name="id_rol" class="form-control" required>
                <?php foreach ($roles as $rol): ?>
                        <?php 
                            $rId = $rol['id'] ?? $rol['id_rol'];
                            $rNombre = $rol['nombre'];
                        ?>
                        <option value="<?= $rId ?>" 
                        <?= ($userRolId == $rId) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($rNombre) ?>
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
                       value="<?= htmlspecialchars($email) ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="estado">Estado</label>
                <select id="estado" name="estado" class="form-control">
                    <option value="Activo" <?= (($usuario['estado'] ?? 'Activo') === 'Activo') ? 'selected' : '' ?>>Activo</option>
                    <option value="Inactivo" <?= (($usuario['estado'] ?? '') === 'Inactivo') ? 'selected' : '' ?>>Inactivo</option>
                </select>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="codigo_llavero">Código Llavero (opcional)</label>
                <input type="text" id="codigo_llavero" name="codigo_llavero" class="form-control" 
                       value="<?= htmlspecialchars($usuario['codigo_llavero'] ?? '') ?>" placeholder="Ej: LL-001">
            </div>
            <div class="form-group">
                <label class="form-label" for="password">
                    Contraseña <?= $usuario ? '(dejar vacío para no cambiar)' : '*' ?>
                </label>
                <input type="password" id="password" name="password" class="form-control" 
                       <?= !$usuario ? 'required' : '' ?> minlength="6"
                       placeholder="<?= $usuario ? 'Dejar vacío para mantener la actual' : 'Mínimo 6 caracteres' ?>">
            </div>
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

