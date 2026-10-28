<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<style>
/* ── Estilos generales y filtros ── */
.role-filters-container {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 1.25rem;
}

.role-tab-btn, .ficha-pill-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border-radius: 8px;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    color: var(--text-secondary);
    font-size: 0.875rem;
    font-weight: 500;
    text-decoration: none;
    transition: all 0.2s ease;
    cursor: pointer;
}

.role-tab-btn:hover, .ficha-pill-btn:hover {
    background: rgba(255, 255, 255, 0.05);
    color: var(--text-primary);
    border-color: var(--text-muted);
}

.role-tab-btn.active, .ficha-pill-btn.active {
    background: var(--accent-green, #10b981);
    color: #ffffff;
    border-color: var(--accent-green, #10b981);
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.25);
}

.role-tab-btn .badge-pill, .ficha-pill-btn .badge-pill {
    padding: 2px 7px;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    background: rgba(125, 125, 125, 0.15);
    color: inherit;
}

.role-tab-btn.active .badge-pill, .ficha-pill-btn.active .badge-pill {
    background: rgba(255, 255, 255, 0.25);
    color: #ffffff;
}

.meta-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    font-size: 0.75rem;
    padding: 2px 6px;
    border-radius: 4px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--border-color);
    color: var(--text-secondary);
}

.meta-chip.rfid-chip {
    color: #10b981;
    border-color: rgba(16, 185, 129, 0.3);
    background: rgba(16, 185, 129, 0.08);
    font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
}

/* ── Pestañas de detalle de Ficha ── */
.ficha-tabs-nav {
    display: flex;
    gap: 10px;
    border-bottom: 2px solid var(--border-color);
    margin-bottom: 20px;
}

.ficha-tab-trigger {
    background: none;
    border: none;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    padding: 10px 18px;
    font-size: 0.95rem;
    font-weight: 600;
    color: var(--text-secondary);
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    transition: all 0.2s ease;
}

.ficha-tab-trigger:hover {
    color: var(--text-primary);
}

.ficha-tab-trigger.active {
    color: var(--accent-green, #10b981);
    border-bottom-color: var(--accent-green, #10b981);
}

.ficha-tab-pane {
    display: none;
}

.ficha-tab-pane.active {
    display: block;
    animation: fadeIn 0.25s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(4px); }
    to { opacity: 1; transform: translateY(0); }
}

.badge-you {
    background: linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%);
    color: #ffffff;
    font-size: 0.75rem;
    padding: 3px 8px;
    border-radius: 6px;
    font-weight: 700;
    margin-left: 6px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    box-shadow: 0 2px 4px rgba(109, 40, 217, 0.25);
}
</style>

<?php if (!empty($esVistaFicha)): ?>
<!-- ════════════════════════════════════════════════════════════════════════ -->
<!--  VISTA POR FICHA (Para Instructores y Consulta Detallada de Admin)       -->
<!-- ════════════════════════════════════════════════════════════════════════ -->

<!-- ── SELECTOR / NAVEGADOR DE FICHAS ──────────────────────────────────── -->
<div class="card" style="margin-bottom: 20px; padding: 16px 20px;">
    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 14px;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-folder-open" style="font-size: 1.25rem; color: var(--accent-green);"></i>
            <div>
                <strong style="color: var(--text-primary); font-size: 0.95rem;">
                    <?= Auth::isInstructor() ? 'Mis Fichas Asignadas' : 'Fichas de Formación' ?>
                </strong>
                <span style="display: block; font-size: 0.8rem; color: var(--text-secondary);">
                    Selecciona una ficha para inspeccionar su lista de aprendices e instructores
                </span>
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
            <?php if (Auth::isAdmin()): ?>
                <a href="<?= BASE_URL ?>?action=usuarios" class="btn btn-secondary btn-sm" title="Volver al listado global">
                    <i class="fas fa-list"></i> Todos los Usuarios
                </a>
            <?php endif; ?>

            <?php if (!empty($fichas)): ?>
            <div style="min-width: 240px;">
                <select class="form-control" style="padding: 7px 12px; font-size: 0.875rem;" 
                        onchange="if(this.value) window.location.href='<?= BASE_URL ?>?action=usuarios&ficha_id=' + this.value;">
                    <?php foreach ($fichas as $f): ?>
                        <option value="<?= $f['id'] ?>" <?= ((int)$f['id'] === (int)($fichaSeleccionadaId ?? 0)) ? 'selected' : '' ?>>
                            Ficha <?= htmlspecialchars((string)($f['codigo'] ?? $f['numero_ficha'] ?? '')) ?> — <?= htmlspecialchars($f['programa_nombre'] ?? $f['nombre_programa'] ?? 'Sin Programa') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Carrusel / Píldoras de Fichas Rápidas -->
    <?php if (!empty($fichas) && count($fichas) > 1): ?>
    <div style="display: flex; gap: 8px; overflow-x: auto; padding-top: 14px; margin-top: 12px; border-top: 1px solid var(--border-color);">
        <?php foreach ($fichas as $f): ?>
            <?php $esActiva = ((int)$f['id'] === (int)($fichaSeleccionadaId ?? 0)); ?>
            <a href="<?= BASE_URL ?>?action=usuarios&ficha_id=<?= $f['id'] ?>" 
               class="ficha-pill-btn <?= $esActiva ? 'active' : '' ?>"
               title="<?= htmlspecialchars($f['programa_nombre'] ?? $f['nombre_programa'] ?? '') ?>">
                <i class="fas fa-layer-group"></i>
                <span>Ficha <?= htmlspecialchars((string)($f['codigo'] ?? $f['numero_ficha'] ?? '')) ?></span>
                <span class="badge-pill"><?= $f['total_aprendices'] ?? 0 ?> apr.</span>
            </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($fichaActual)): ?>
<!-- ── RESUMEN DE LA FICHA SELECCIONADA ─────────────────────────────────── -->
<div class="card" style="margin-bottom: 24px;">
    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 16px;">
        <div>
            <span class="badge badge-purple" style="font-size: 0.85rem; margin-bottom: 6px; display: inline-block;">
                <i class="fas fa-id-card"></i> Ficha <?= htmlspecialchars((string) ($fichaActual['codigo'] ?? $fichaActual['numero_ficha'] ?? '')) ?>
            </span>
            <h2 style="font-size: 1.35rem; font-weight: 700; color: var(--text-primary); margin: 0 0 6px 0;">
                <?= htmlspecialchars($fichaActual['nombre_programa'] ?? $fichaActual['programa_nombre'] ?? 'Sin programa asignado') ?>
            </h2>
            <p style="color: var(--text-secondary); margin: 0; font-size: 0.9rem;">
                <i class="fas fa-clock" style="color: var(--accent-purple); margin-right: 4px;"></i> Jornada: <strong><?= htmlspecialchars($fichaActual['nombre_jornada'] ?? $fichaActual['jornada_nombre'] ?? 'No definida') ?></strong>
                &nbsp;|&nbsp;
                <i class="fas fa-user-tie" style="color: var(--accent-blue); margin-right: 4px;"></i> Instructor Líder: <strong><?= htmlspecialchars($fichaActual['nombre_instructor'] ?? 'Sin asignar') ?></strong>
            </p>
        </div>
        <div style="display: flex; gap: 12px;">
            <div style="background: rgba(255, 255, 255, 0.04); border: 1px solid var(--border-color); border-radius: 8px; padding: 12px 18px; text-align: center; min-width: 110px;">
                <span style="display: block; font-size: 1.45rem; font-weight: 700; color: var(--accent-green);"><?= count($aprendices) ?></span>
                <span style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Aprendices</span>
            </div>
            <div style="background: rgba(255, 255, 255, 0.04); border: 1px solid var(--border-color); border-radius: 8px; padding: 12px 18px; text-align: center; min-width: 110px;">
                <span style="display: block; font-size: 1.45rem; font-weight: 700; color: var(--accent-blue);"><?= count($instructores) ?></span>
                <span style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; font-weight: 600;">Instructores</span>
            </div>
        </div>
    </div>
</div>

<!-- ── PESTAÑAS (TABS) DOBLE SECCIÓN ───────────────────────────────────── -->
<div class="ficha-tabs-nav">
    <button type="button" class="ficha-tab-trigger active" id="tabBtnAprendices" onclick="switchFichaTab('aprendices')">
        <i class="fas fa-user-graduate"></i>
        <span>Aprendices Matriculados</span>
        <span class="badge badge-green" style="font-size: 0.75rem;"><?= count($aprendices) ?></span>
    </button>
    <button type="button" class="ficha-tab-trigger" id="tabBtnInstructores" onclick="switchFichaTab('instructores')">
        <i class="fas fa-chalkboard-teacher"></i>
        <span>Equipo Ejecutor / Instructores</span>
        <span class="badge badge-blue" style="font-size: 0.75rem;"><?= count($instructores) ?></span>
    </button>
</div>

<!-- ── PANEL 1: APRENDICES DE LA FICHA ─────────────────────────────────── -->
<div id="paneAprendices" class="ficha-tab-pane active">
    <div class="card">
        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 16px;">
            <div class="search-box" style="flex: 1; max-width: 360px;">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Filtrar aprendiz por documento, nombre o correo..." data-search-table="tablaAprendicesFicha">
            </div>

            <?php if (Auth::isAdmin()): ?>
            <a href="<?= BASE_URL ?>?action=usuarios/crear" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> Matricular Aprendiz
            </a>
            <?php endif; ?>
        </div>

        <?php if (!empty($aprendices)): ?>
        <div class="table-wrapper">
            <table class="data-table" id="tablaAprendicesFicha">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Documento</th>
                        <th>Nombre Completo</th>
                        <th>Correo Electrónico</th>
                        <th>Código RFID (Llavero)</th>
                        <th>Estado</th>
                        <?php if (Auth::isAdmin()): ?>
                        <th style="text-align: right;">Acciones</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php $idx = 1; foreach ($aprendices as $ap): ?>
                    <?php 
                        $idAp = $ap['id'] ?? 0;
                        $doc = $ap['identificacion'] ?? $ap['documento'] ?? '';
                        $correo = $ap['email'] ?? $ap['correo'] ?? '-';
                        $nomComp = trim(($ap['nombre'] ?? '') . ' ' . ($ap['apellido'] ?? ''));
                        $rfid = $ap['codigo_llavero'] ?? $ap['rfid_tag'] ?? '';
                        $est = $ap['estado'] ?? 'Activo';
                    ?>
                    <tr>
                        <td style="color: var(--text-muted); font-size: 0.8rem;"><?= $idx++ ?></td>
                        <td><strong><?= htmlspecialchars((string) $doc) ?></strong></td>
                        <td><?= htmlspecialchars($nomComp) ?></td>
                        <td><?= htmlspecialchars($correo) ?></td>
                        <td>
                            <?php if (!empty($rfid)): ?>
                                <span class="meta-chip rfid-chip" title="Llavero asignado">
                                    <i class="fas fa-wifi"></i> <?= htmlspecialchars($rfid) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-secondary" style="font-size: 0.75rem;">
                                    <i class="fas fa-wifi-slash"></i> Sin RFID
                                </span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($est === 'Activo'): ?>
                                <span class="badge badge-green">Activo</span>
                            <?php else: ?>
                                <span class="badge badge-red">Inactivo</span>
                            <?php endif; ?>
                        </td>
                        <?php if (Auth::isAdmin()): ?>
                        <td style="text-align: right;">
                            <div class="btn-group">
                                <a href="<?= BASE_URL ?>?action=usuarios/editar&id=<?= $idAp ?>" class="btn btn-sm btn-secondary" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="<?= BASE_URL ?>?action=usuarios/eliminar&id=<?= $idAp ?>" class="btn btn-sm btn-danger" 
                                   data-confirm="¿Eliminar aprendiz <?= htmlspecialchars($nomComp) ?>?" title="Eliminar">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-user-graduate"></i>
            <p>No hay aprendices matriculados en esta ficha actualmente.</p>
            <?php if (Auth::isAdmin()): ?>
            <a href="<?= BASE_URL ?>?action=usuarios/crear" class="btn btn-primary btn-sm mt-2">
                <i class="fas fa-plus"></i> Matricular primer aprendiz
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── PANEL 2: EQUIPO EJECUTOR / INSTRUCTORES ─────────────────────────── -->
<div id="paneInstructores" class="ficha-tab-pane">
    <div class="card">
        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 16px;">
            <div class="search-box" style="flex: 1; max-width: 360px;">
                <i class="fas fa-search"></i>
                <input type="text" placeholder="Filtrar instructor..." data-search-table="tablaInstructoresFicha">
            </div>
        </div>

        <?php if (!empty($instructores)): ?>
        <div class="table-wrapper">
            <table class="data-table" id="tablaInstructoresFicha">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Documento</th>
                        <th>Instructor</th>
                        <th>Correo Institucional</th>
                        <th>Rol en la Ficha</th>
                        <th>Competencias / Especialidad Impartida</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $idxI = 1; foreach ($instructores as $inst): ?>
                    <?php 
                        $idInst = (int) ($inst['id'] ?? 0);
                        $esElMismo = ($idInst === (int) Auth::getUserId());
                        $docI = $inst['identificacion'] ?? $inst['documento'] ?? '';
                        $nomI = trim(($inst['nombre'] ?? '') . ' ' . ($inst['apellido'] ?? ''));
                        $emailI = $inst['email'] ?? $inst['correo'] ?? '-';
                        $rolFicha = $inst['rol_en_ficha'] ?? 'Instructor';
                        $comps = $inst['competencias_asignadas'] ?? 'Formación Técnica General';
                        $estI = $inst['estado'] ?? 'Activo';
                    ?>
                    <tr>
                        <td style="color: var(--text-muted); font-size: 0.8rem;"><?= $idxI++ ?></td>
                        <td><strong><?= htmlspecialchars((string) $docI) ?></strong></td>
                        <td>
                            <strong><?= htmlspecialchars($nomI) ?></strong>
                            <?php if ($esElMismo): ?>
                                <span class="badge-you"><i class="fas fa-user-check"></i> Tú</span>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($emailI) ?></td>
                        <td>
                            <?php if ($rolFicha === 'Instructor Líder'): ?>
                                <span class="badge badge-purple"><i class="fas fa-star"></i> Instructor Líder</span>
                            <?php else: ?>
                                <span class="badge badge-blue"><i class="fas fa-chalkboard-teacher"></i> Formador</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="meta-chip" title="<?= htmlspecialchars($comps) ?>">
                                <i class="fas fa-book-open"></i> <?= htmlspecialchars($comps) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($estI === 'Activo'): ?>
                                <span class="badge badge-green">Activo</span>
                            <?php else: ?>
                                <span class="badge badge-red">Inactivo</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-chalkboard-teacher"></i>
            <p>No se encontraron instructores vinculados a esta ficha.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
function switchFichaTab(tab) {
    const btnApr = document.getElementById('tabBtnAprendices');
    const btnIns = document.getElementById('tabBtnInstructores');
    const paneApr = document.getElementById('paneAprendices');
    const paneIns = document.getElementById('paneInstructores');

    if (tab === 'aprendices') {
        btnApr.classList.add('active');
        btnIns.classList.remove('active');
        paneApr.classList.add('active');
        paneIns.classList.remove('active');
    } else {
        btnIns.classList.add('active');
        btnApr.classList.remove('active');
        paneIns.classList.add('active');
        paneApr.classList.remove('active');
    }
}
</script>

<?php else: ?>
<!-- Estado vacío si el instructor no tiene fichas asignadas -->
<div class="card">
    <div class="empty-state">
        <i class="fas fa-folder-minus"></i>
        <h3>No tienes fichas asignadas</h3>
        <p>Aún no tienes fichas asociadas en el sistema. Consulta con el Administrador o verifica la importación de horarios.</p>
        <a href="<?= BASE_URL ?>?action=dashboard" class="btn btn-secondary mt-2">
            <i class="fas fa-arrow-left"></i> Volver al Inicio
        </a>
    </div>
</div>
<?php endif; ?>

<?php else: ?>
<!-- ════════════════════════════════════════════════════════════════════════ -->
<!--  VISTA GLOBAL DE ADMINISTRADOR (Listado General de Usuarios)             -->
<!-- ════════════════════════════════════════════════════════════════════════ -->

<!-- ── ACCESO RÁPIDO: FILTRAR POR FICHA ───────────────────────────────── -->
<?php if (!empty($fichas)): ?>
<div class="card" style="margin-bottom: 1.25rem; padding: 12px 18px;">
    <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px;">
        <div style="display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-filter" style="color: var(--accent-green);"></i>
            <strong style="color: var(--text-primary); font-size: 0.9rem;">Inspeccionar usuarios por ficha:</strong>
        </div>
        <div style="min-width: 280px;">
            <select class="form-control" style="padding: 6px 12px; font-size: 0.85rem;" 
                    onchange="if(this.value) window.location.href='<?= BASE_URL ?>?action=usuarios&ficha_id=' + this.value;">
                <option value="">-- Seleccionar una Ficha para inspeccionar --</option>
                <?php foreach ($fichas as $f): ?>
                    <option value="<?= $f['id'] ?>">
                        Ficha <?= htmlspecialchars((string)($f['codigo'] ?? '')) ?> — <?= htmlspecialchars($f['nombre_programa'] ?? 'Sin Programa') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── FILTROS RÁPIDOS POR ROL ────────────────────────────────────── -->
<div class="role-filters-container">
    <a href="<?= BASE_URL ?>?action=usuarios" 
       class="role-tab-btn <?= ($rolFiltro === null) ? 'active' : '' ?>">
        <i class="fas fa-users"></i>
        <span>Todos</span>
        <span class="badge-pill"><?= $conteos['total'] ?? 0 ?></span>
    </a>

    <a href="<?= BASE_URL ?>?action=usuarios&rol=1" 
       class="role-tab-btn <?= ($rolFiltro === 1) ? 'active' : '' ?>">
        <i class="fas fa-user-shield"></i>
        <span>Administradores</span>
        <span class="badge-pill"><?= $conteos['administradores'] ?? 0 ?></span>
    </a>

    <a href="<?= BASE_URL ?>?action=usuarios&rol=2" 
       class="role-tab-btn <?= ($rolFiltro === 2) ? 'active' : '' ?>">
        <i class="fas fa-chalkboard-teacher"></i>
        <span>Instructores</span>
        <span class="badge-pill"><?= $conteos['instructores'] ?? 0 ?></span>
    </a>

    <a href="<?= BASE_URL ?>?action=usuarios&rol=3" 
       class="role-tab-btn <?= ($rolFiltro === 3) ? 'active' : '' ?>">
        <i class="fas fa-graduation-cap"></i>
        <span>Aprendices</span>
        <span class="badge-pill"><?= $conteos['aprendices'] ?? 0 ?></span>
    </a>
</div>

<!-- ── BARRA DE HERRAMIENTAS Y BÚSQUEDA ───────────────────────────── -->
<div class="toolbar">
    <div class="toolbar-left">
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" 
                   placeholder="Buscar por documento, nombre, correo, ficha..." 
                   data-search-table="tablaUsuarios"
                   style="min-width: 320px;">
        </div>
    </div>
    <div class="toolbar-right">
        <a href="<?= BASE_URL ?>?action=usuarios/crear" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nuevo Usuario
        </a>
    </div>
</div>

<!-- ── TABLA DE USUARIOS GLOBAL ───────────────────────────────────── -->
<div class="card">
    <?php if (!empty($usuarios)): ?>
    <div class="table-wrapper">
        <table class="data-table" id="tablaUsuarios">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Documento</th>
                    <th>Nombre Completo</th>
                    <th>Correo Electrónico</th>
                    <th>Rol</th>
                    <th>Detalles / Formación</th>
                    <th>Estado</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($usuarios as $u): ?>
                <?php 
                    $id = $u['id'] ?? $u['id_usuario'] ?? 0;
                    $identificacion = $u['identificacion'] ?? $u['num_documento'] ?? '';
                    $email = $u['email'] ?? $u['correo'] ?? '';
                    $rolId = (int) ($u['Rol_id'] ?? $u['id_rol'] ?? 0);
                    $nombreRol = $u['nombre_rol'] ?? '';
                    $estado = $u['estado'] ?? 'Activo';
                    $llavero = $u['codigo_llavero'] ?? $u['codigo_rfid'] ?? '';
                    $codigoFicha = $u['codigo_ficha'] ?? '';
                    $nombrePrograma = $u['nombre_programa'] ?? '';
                ?>
                <tr>
                    <td><?= $id ?></td>
                    <td><strong><?= htmlspecialchars((string) $identificacion) ?></strong></td>
                    <td><?= htmlspecialchars($u['nombre'] . ' ' . $u['apellido']) ?></td>
                    <td><?= htmlspecialchars($email) ?></td>
                    <td>
                        <?php
                            $badgeClass = match ($rolId) {
                                1 => 'badge-green',
                                2 => 'badge-blue',
                                3 => 'badge-purple',
                                default => 'badge-secondary'
                            };
                            $roleIcon = match ($rolId) {
                                1 => 'fa-user-shield',
                                2 => 'fa-chalkboard-teacher',
                                3 => 'fa-graduation-cap',
                                default => 'fa-user'
                            };
                        ?>
                        <span class="badge <?= $badgeClass ?>">
                            <i class="fas <?= $roleIcon ?>"></i>
                            <?= htmlspecialchars($nombreRol) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($rolId === 3): ?>
                            <div style="display: flex; flex-direction: column; gap: 4px;">
                                <?php if (!empty($codigoFicha)): ?>
                                    <span class="meta-chip" title="Programa: <?= htmlspecialchars($nombrePrograma) ?>">
                                        <i class="fas fa-layer-group"></i> Ficha: <strong><?= htmlspecialchars((string) $codigoFicha) ?></strong>
                                    </span>
                                <?php else: ?>
                                    <span class="text-secondary" style="font-size: 0.75rem;">Sin ficha</span>
                                <?php endif; ?>

                                <?php if (!empty($llavero)): ?>
                                    <span class="meta-chip rfid-chip" title="UID RFID del llavero/tarjeta">
                                        <i class="fas fa-wifi"></i> RFID: <?= htmlspecialchars($llavero) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-secondary" style="font-size: 0.75rem;">
                                        <i class="fas fa-wifi-slash"></i> Sin RFID
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <span class="text-secondary" style="font-size: 0.85rem;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge badge-<?= strtolower($estado) ?>">
                            <?= htmlspecialchars($estado) ?>
                        </span>
                    </td>
                    <td>
                        <div class="btn-group">
                            <a href="<?= BASE_URL ?>?action=usuarios/editar&id=<?= $id ?>" 
                               class="btn btn-sm btn-secondary" title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php if ($id != Auth::getUserId()): ?>
                            <a href="<?= BASE_URL ?>?action=usuarios/eliminar&id=<?= $id ?>" 
                               class="btn btn-sm btn-danger" 
                               data-confirm="¿Está seguro de eliminar al usuario <?= htmlspecialchars($u['nombre'] . ' ' . $u['apellido']) ?>?"
                               title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <i class="fas fa-users"></i>
        <?php if ($rolFiltro !== null): ?>
            <p>No se encontraron usuarios registrados en la categoría seleccionada.</p>
            <div style="display: flex; gap: 8px; justify-content: center; margin-top: 1rem;">
                <a href="<?= BASE_URL ?>?action=usuarios" class="btn btn-secondary">
                    <i class="fas fa-list"></i> Ver todos los usuarios
                </a>
                <a href="<?= BASE_URL ?>?action=usuarios/crear" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Registrar nuevo usuario
                </a>
            </div>
        <?php else: ?>
            <p>No hay usuarios registrados en el sistema.</p>
            <a href="<?= BASE_URL ?>?action=usuarios/crear" class="btn btn-primary mt-2">
                <i class="fas fa-plus"></i> Crear primer usuario
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php endif; ?>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
