<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<?php
$userId = $usuario['id'] ?? $usuario['id_usuario'] ?? 0;
$identificacion = $usuario['identificacion'] ?? $usuario['num_documento'] ?? '';
$email = $usuario['email'] ?? $usuario['correo'] ?? '';
$userRolId = (int) ($usuario['Rol_id'] ?? $usuario['id_rol'] ?? 2);
$userFichaId = (int) ($usuario['Ficha_id'] ?? $usuario['id_ficha'] ?? 0);
$esAprendiz = ($userRolId === 3);
?>

<div class="card" style="max-width: 720px; margin: 0 auto;">
    <div class="card-header" style="margin-bottom: 20px;">
        <h2 style="font-size: 1.15rem; font-weight: 700; color: var(--text-primary); margin: 0;">
            <i class="fas <?= $usuario ? 'fa-user-pen' : 'fa-user-plus' ?>" style="color: var(--accent-green); margin-right: 8px;"></i>
            <?= $usuario ? 'Editar Usuario' : 'Registrar Nuevo Usuario' ?>
        </h2>
    </div>

    <form method="POST" action="<?= BASE_URL ?>?action=usuarios/guardar" id="formUsuario">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
        <input type="hidden" name="id_usuario" value="<?= $userId ?>">

        <!-- ── Fila 1: Documento y Rol ── -->
        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="num_documento">
                    <i class="fas fa-id-card"></i> Número de Documento *
                </label>
                <input type="text" id="num_documento" name="num_documento" class="form-control" 
                       value="<?= htmlspecialchars($identificacion) ?>" required
                       placeholder="Ej: 1023456789">
            </div>
            <div class="form-group">
                <label class="form-label" for="id_rol">
                    <i class="fas fa-user-tag"></i> Rol del Usuario *
                </label>
                <select id="id_rol" name="id_rol" class="form-control" required>
                    <?php foreach ($roles as $rol): ?>
                        <?php 
                            $rId = (int) ($rol['id'] ?? $rol['id_rol']);
                            $rNombre = $rol['nombre'];
                        ?>
                        <option value="<?= $rId ?>" <?= ($userRolId === $rId) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($rNombre) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- ── Fila 2: Nombres y Apellidos ── -->
        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="nombre">Nombre *</label>
                <input type="text" id="nombre" name="nombre" class="form-control" 
                       value="<?= htmlspecialchars($usuario['nombre'] ?? '') ?>" required
                       placeholder="Nombres completos">
            </div>
            <div class="form-group">
                <label class="form-label" for="apellido">Apellido *</label>
                <input type="text" id="apellido" name="apellido" class="form-control" 
                       value="<?= htmlspecialchars($usuario['apellido'] ?? '') ?>" required
                       placeholder="Apellidos completos">
            </div>
        </div>

        <!-- ── Fila 3: Correo y Estado ── -->
        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="correo">
                    <i class="fas fa-envelope"></i> Correo Electrónico *
                </label>
                <input type="email" id="correo" name="correo" class="form-control" 
                       value="<?= htmlspecialchars($email) ?>" required
                       placeholder="usuario@misena.edu.co">
            </div>
            <div class="form-group">
                <label class="form-label" for="estado">Estado</label>
                <select id="estado" name="estado" class="form-control">
                    <option value="Activo" <?= (($usuario['estado'] ?? 'Activo') === 'Activo') ? 'selected' : '' ?>>Activo</option>
                    <option value="Inactivo" <?= (($usuario['estado'] ?? '') === 'Inactivo') ? 'selected' : '' ?>>Inactivo</option>
                </select>
            </div>
        </div>

        <!-- ── Fila 4: Contraseña ── -->
        <div class="form-group">
            <label class="form-label" for="password">
                <i class="fas fa-lock"></i> Contraseña <?= $usuario ? '<small class="text-secondary">(dejar vacío para no cambiar)</small>' : '*' ?>
            </label>
            <div style="position: relative;">
                <input type="password" id="password" name="password" class="form-control" 
                       <?= !$usuario ? 'required' : '' ?> minlength="6"
                       placeholder="<?= $usuario ? 'Dejar vacío para mantener la actual' : 'Mínimo 6 caracteres' ?>"
                       style="padding-right: 40px;">
                <button type="button" id="btnTogglePass" title="Mostrar/Ocultar contraseña" tabindex="-1"
                        style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-secondary); cursor: pointer; padding: 4px;">
                    <i class="fas fa-eye" id="iconoOjo"></i>
                </button>
            </div>
        </div>

        <!-- ════════════════════════════════════════════════════════════════
             SECCIÓN APRENDIZ: Aparece automáticamente al seleccionar Rol = Aprendiz
        ═══════════════════════════════════════════════════════════════════ -->
        <div id="seccionAprendiz" style="<?= $esAprendiz ? 'display: block;' : 'display: none;' ?> border: 1px dashed var(--accent-green); border-radius: var(--radius); padding: 18px; margin: 20px 0; background: rgba(16, 185, 129, 0.05);">
            <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 14px; color: var(--accent-green); font-weight: 700;">
                <i class="fas fa-graduation-cap" style="font-size: 1.1rem;"></i>
                <span>Datos Específicos del Aprendiz</span>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="Ficha_id">
                        <i class="fas fa-layer-group"></i> Ficha de Formación *
                    </label>
                    <select id="Ficha_id" name="Ficha_id" class="form-control" <?= $esAprendiz ? 'required' : '' ?>>
                        <option value="">— Seleccionar ficha —</option>
                        <?php if (!empty($fichas)): ?>
                            <?php foreach ($fichas as $f): ?>
                                <?php 
                                    $fId = (int) ($f['id'] ?? $f['id_ficha'] ?? 0);
                                    $fCodigo = $f['codigo'] ?? $f['codigo_ficha'] ?? '';
                                    $fProg = $f['nombre_programa'] ?? '';
                                ?>
                                <option value="<?= $fId ?>" <?= ($userFichaId === $fId) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($fCodigo . ($fProg ? ' — ' . $fProg : '')) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="codigo_llavero">
                        <i class="fas fa-wifi"></i> Código RFID / Llavero
                    </label>
                    <input type="text" id="codigo_llavero" name="codigo_llavero" class="form-control" 
                           value="<?= htmlspecialchars($usuario['codigo_llavero'] ?? '') ?>" 
                           placeholder="UID del llavero/tarjeta (ej: LL-001)">
                </div>
            </div>
        </div>

        <!-- ── Botones de acción ── -->
        <div class="form-actions" style="margin-top: 24px; display: flex; justify-content: flex-end; gap: 10px;">
            <a href="<?= BASE_URL ?>?action=usuarios" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Cancelar
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> <?= $usuario ? 'Actualizar' : 'Guardar' ?> Usuario
            </button>
        </div>
    </form>
</div>

<!-- ── Script interactivo para controlar sección de Aprendiz y Contraseña ── -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const selectRol = document.getElementById('id_rol');
    const seccionAprendiz = document.getElementById('seccionAprendiz');
    const selectFicha = document.getElementById('Ficha_id');
    const btnTogglePass = document.getElementById('btnTogglePass');
    const inputPass = document.getElementById('password');
    const iconoOjo = document.getElementById('iconoOjo');

    function evaluarRol() {
        if (!selectRol || !seccionAprendiz) return;
        const val = parseInt(selectRol.value, 10);
        const text = selectRol.options[selectRol.selectedIndex]?.text.toLowerCase() || '';
        const esAprendiz = (val === 3 || text.includes('aprendiz'));

        if (esAprendiz) {
            seccionAprendiz.style.display = 'block';
            if (selectFicha) selectFicha.required = true;
        } else {
            seccionAprendiz.style.display = 'none';
            if (selectFicha) {
                selectFicha.required = false;
                selectFicha.value = '';
            }
        }
    }

    if (selectRol) {
        selectRol.addEventListener('change', evaluarRol);
        evaluarRol(); // Ejecución inicial
    }

    if (btnTogglePass && inputPass && iconoOjo) {
        btnTogglePass.addEventListener('click', function () {
            const isPass = inputPass.type === 'password';
            inputPass.type = isPass ? 'text' : 'password';
            iconoOjo.className = isPass ? 'fas fa-eye-slash' : 'fas fa-eye';
        });
    }
});
</script>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
