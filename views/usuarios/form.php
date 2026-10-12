<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<?php
/**
 * views/usuarios/form.php
 * Formulario unificado de Creación y Edición de Usuarios.
 *
 * Single Source of Truth: Punto único de alta y edición para cualquier rol.
 * Si el rol seleccionado es Aprendiz, se despliega dinámicamente el contenedor
 * con los campos requeridos: `ficha_id` y `rfid_tag`.
 */
$esNuevo     = ($usuario === null);
$actionForm  = $esNuevo ? 'usuarios/store' : 'usuarios/guardar';
$idRolActual = (int) ($usuario['id_rol'] ?? 3);
?>

<div class="card" style="max-width: 780px; margin: 0 auto;">

    <div style="margin-bottom: 1.5rem;">
        <h2 style="margin: 0; font-size: 1.25rem; color: var(--text-primary); display: flex; align-items: center; gap: 0.5rem;">
            <i class="fas fa-<?= $esNuevo ? 'user-plus' : 'user-edit' ?>" style="color: var(--primary);"></i>
            <?= $esNuevo ? 'Registrar Nuevo Usuario' : 'Editar Usuario' ?>
        </h2>
        <?php if ($esNuevo): ?>
        <p style="margin: 6px 0 0; font-size: 0.875rem; color: var(--text-secondary);">
            Formulario unificado. Al seleccionar el rol <strong>Aprendiz</strong> se habilitarán los campos de Ficha y RFID.
        </p>
        <?php endif; ?>
    </div>

    <form method="POST"
          action="<?= BASE_URL ?>?action=<?= $actionForm ?>"
          id="formUsuario"
          autocomplete="off">

        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
        <?php if (!$esNuevo): ?>
        <input type="hidden" name="id_usuario" value="<?= (int) ($usuario['id_usuario'] ?? 0) ?>">
        <?php endif; ?>

        <!-- ── FILA 1: Documento + Rol ─────────────────────────────────── -->
        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="documento">
                    <i class="fas fa-id-card"></i> Número de Documento <span class="text-danger">*</span>
                </label>
                <input type="text"
                       id="documento"
                       name="documento"
                       class="form-control"
                       value="<?= htmlspecialchars($usuario['num_documento'] ?? '') ?>"
                       maxlength="20"
                       required>
                <!-- Compatibilidad con num_documento -->
                <input type="hidden" name="num_documento" id="num_documento_alias" value="<?= htmlspecialchars($usuario['num_documento'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label class="form-label" for="rol_id">
                    <i class="fas fa-shield-halved"></i> Rol <span class="text-danger">*</span>
                </label>
                <select id="rol_id" name="rol_id" class="form-control" required <?= !$esNuevo ? 'disabled' : '' ?>>
                    <option value="">— Seleccionar Rol —</option>
                    <?php foreach ($roles as $rol): ?>
                    <option value="<?= $rol['id_rol'] ?>"
                            <?= ($idRolActual === (int) $rol['id_rol']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($rol['nombre']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <?php if (!$esNuevo): ?>
                    <input type="hidden" name="rol_id" value="<?= $idRolActual ?>">
                    <input type="hidden" name="id_rol" value="<?= $idRolActual ?>">
                    <small class="text-secondary" style="display:block; margin-top:4px;">
                        <i class="fas fa-lock"></i> El rol no puede modificarse en edición.
                    </small>
                <?php endif; ?>
            </div>
        </div>

        <!-- ── FILA 2: Nombre + Apellido ──────────────────────────────── -->
        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="nombre">
                    <i class="fas fa-user"></i> Nombre <span class="text-danger">*</span>
                </label>
                <input type="text"
                       id="nombre"
                       name="nombre"
                       class="form-control"
                       value="<?= htmlspecialchars($usuario['nombre'] ?? '') ?>"
                       maxlength="100"
                       required>
            </div>
            <div class="form-group">
                <label class="form-label" for="apellido">
                    <i class="fas fa-user"></i> Apellido <span class="text-danger">*</span>
                </label>
                <input type="text"
                       id="apellido"
                       name="apellido"
                       class="form-control"
                       value="<?= htmlspecialchars($usuario['apellido'] ?? '') ?>"
                       maxlength="100"
                       required>
            </div>
        </div>

        <!-- ── FILA 3: Correo + Estado ─────────────────────────────────── -->
        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="correo">
                    <i class="fas fa-envelope"></i> Correo Electrónico <span class="text-danger">*</span>
                </label>
                <input type="email"
                       id="correo"
                       name="correo"
                       class="form-control"
                       value="<?= htmlspecialchars($usuario['correo'] ?? '') ?>"
                       maxlength="150"
                       required>
            </div>
            <div class="form-group">
                <label class="form-label" for="estado">
                    <i class="fas fa-toggle-on"></i> Estado
                </label>
                <select id="estado" name="estado" class="form-control">
                    <option value="Activo"   <?= (($usuario['estado'] ?? 'Activo') === 'Activo')   ? 'selected' : '' ?>>Activo</option>
                    <option value="Inactivo" <?= (($usuario['estado'] ?? '') === 'Inactivo') ? 'selected' : '' ?>>Inactivo</option>
                </select>
            </div>
        </div>

        <!-- ── FILA 4: Contraseña ──────────────────────────────────────── -->
        <div class="form-group">
            <label class="form-label" for="password">
                <i class="fas fa-lock"></i>
                Contraseña
                <?= $esNuevo
                    ? '<span class="text-danger">* (mínimo 8 caracteres)</span>'
                    : '<small class="text-secondary">(dejar vacío para mantener la actual)</small>'
                ?>
            </label>
            <div style="position: relative;">
                <input type="password"
                       id="password"
                       name="password"
                       class="form-control"
                       minlength="8"
                       <?= $esNuevo ? 'required' : '' ?>
                       autocomplete="new-password"
                       placeholder="<?= $esNuevo ? 'Mínimo 8 caracteres' : 'Dejar en blanco para no modificar' ?>"
                       style="padding-right: 2.75rem;">
                <button type="button"
                        id="btnTogglePass"
                        title="Ver / Ocultar contraseña"
                        tabindex="-1"
                        style="position:absolute; right:0.75rem; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:var(--text-secondary); padding:4px;">
                    <i class="fas fa-eye" id="iconoOjo"></i>
                </button>
            </div>
        </div>

        <!-- ════════════════════════════════════════════════════════════════
             CONTENEDOR APRENDIZ: Oculto por defecto.
             Se activa si el rol_id corresponde a Aprendiz.
        ═══════════════════════════════════════════════════════════════════ -->
        <div id="seccionAprendiz"
             style="display: none; border: 1px dashed var(--accent-primary, #059669); border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem; background: rgba(5, 150, 105, 0.05);">

            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 1rem; color: #059669; font-weight: 600;">
                <i class="fas fa-graduation-cap"></i>
                <span>Datos Específicos del Aprendiz</span>
            </div>

            <div class="form-row">
                <!-- Ficha -->
                <div class="form-group">
                    <label class="form-label" for="ficha_id">
                        <i class="fas fa-layer-group"></i> Ficha de Formación <span class="text-danger">*</span>
                    </label>
                    <select id="ficha_id" name="ficha_id" class="form-control">
                        <option value="">— Seleccionar Ficha —</option>
                        <?php foreach ($fichas as $f): ?>
                        <option value="<?= $f['id_ficha'] ?>">
                            <?= htmlspecialchars($f['codigo_ficha'] . ' — ' . $f['nombre_programa']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- RFID Tag -->
                <div class="form-group">
                    <label class="form-label" for="rfid_tag">
                        <i class="fas fa-wifi"></i> Código RFID (Llavero / Tarjeta) <span class="text-danger">*</span>
                    </label>
                    <input type="text"
                           id="rfid_tag"
                           name="rfid_tag"
                           class="form-control"
                           maxlength="50"
                           placeholder="Escanee o ingrese el UID del RFID">
                </div>
            </div>
        </div>

        <!-- ── Botones de Acción ──────────────────────────────────────── -->
        <div class="form-actions" style="display: flex; gap: 0.75rem; justify-content: flex-end; margin-top: 1.5rem;">
            <a href="<?= BASE_URL ?>?action=usuarios" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Cancelar
            </a>
            <button type="submit" class="btn btn-primary" id="btnSubmit">
                <i class="fas fa-save"></i>
                <?= $esNuevo ? 'Guardar Usuario' : 'Actualizar Usuario' ?>
            </button>
        </div>
    </form>
</div>

<!-- ════════════════════════════════════════════════════════════════════════
     Script Vanilla JS (ES6+)
     Manejo reactivo de visibilidad y 'required' para rol Aprendiz.
════════════════════════════════════════════════════════════════════════════ -->
<script type="module">
(() => {
    'use strict';

    const selectRol     = document.getElementById('rol_id');
    const seccion       = document.getElementById('seccionAprendiz');
    const selectFicha   = document.getElementById('ficha_id');
    const inputRfid     = document.getElementById('rfid_tag');
    const inputDoc      = document.getElementById('documento');
    const inputDocAlias = document.getElementById('num_documento_alias');
    const btnTogglePass = document.getElementById('btnTogglePass');
    const inputPass     = document.getElementById('password');
    const iconoOjo      = document.getElementById('iconoOjo');

    // Sincronizar alias de documento
    inputDoc?.addEventListener('input', (e) => {
        if (inputDocAlias) inputDocAlias.value = e.target.value;
    });

    /**
     * Determina si la opción seleccionada corresponde a Aprendiz.
     * Evalúa por ID (3) o por texto descriptivo en caso de discrepancia en BD.
     */
    const esRolAprendiz = () => {
        if (!selectRol || !selectRol.value) return false;
        const id = parseInt(selectRol.value, 10);
        const text = selectRol.options[selectRol.selectedIndex]?.text.toLowerCase() || '';
        return id === 3 || text.includes('aprendiz');
    };

    /**
     * Alterna la visibilidad y atributos required del contenedor Aprendiz.
     * @param {boolean} visible
     */
    const toggleAprendiz = (visible) => {
        if (!seccion || !selectFicha || !inputRfid) return;

        if (visible) {
            seccion.style.display = 'block';
            selectFicha.required = true;
            inputRfid.required = true;
        } else {
            seccion.style.display = 'none';
            selectFicha.required = false;
            inputRfid.required = false;
            selectFicha.value = '';
            inputRfid.value = '';
        }
    };

    // Escuchar cambios en el selector de roles
    selectRol?.addEventListener('change', () => {
        toggleAprendiz(esRolAprendiz());
    });

    // Evaluación en carga inicial
    toggleAprendiz(esRolAprendiz());

    // Toggle de contraseña
    btnTogglePass?.addEventListener('click', () => {
        if (!inputPass || !iconoOjo) return;
        const oculto = inputPass.type === 'password';
        inputPass.type = oculto ? 'text' : 'password';
        iconoOjo.className = oculto ? 'fas fa-eye-slash' : 'fas fa-eye';
    });
})();
</script>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
