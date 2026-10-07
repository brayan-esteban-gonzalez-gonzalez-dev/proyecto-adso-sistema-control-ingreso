<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<div class="toolbar">
    <div class="toolbar-left">
        <form method="GET" action="<?= BASE_URL ?>" class="filter-form" style="display: flex; gap: 10px; align-items: center;">
            <input type="hidden" name="action" value="horarios">
            <div class="form-group mb-0" style="margin-bottom: 0;">
                <select name="ficha_id" class="form-control" onchange="this.form.submit()" style="min-width: 250px;">
                    <option value="">-- Todas las Fichas --</option>
                    <?php foreach ($fichas as $f): ?>
                        <option value="<?= $f['id'] ?>" <?= ($fichaId == $f['id']) ? 'selected' : '' ?>>
                            Ficha <?= htmlspecialchars($f['codigo']) ?> — <?= htmlspecialchars($f['nombre_programa']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Buscar en horarios..." data-search-table="tablaHorarios">
        </div>
    </div>
    <div class="toolbar-right">
        <?php if (Auth::isAdminOrInstructor()): ?>
        <a href="<?= BASE_URL ?>?action=horarios/importar" class="btn btn-primary">
            <i class="fas fa-file-excel"></i> Importar Horarios desde Excel
        </a>
        <?php endif; ?>
    </div>
</div>

<?php if ($fichaSeleccionada): ?>
<div class="card mb-3" style="background: rgba(16, 185, 129, 0.05); border-left: 4px solid var(--accent-green); padding: 15px 20px; margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
        <div>
            <h3 style="margin: 0; font-size: 1.1rem; color: #fff;">
                Ficha <?= htmlspecialchars($fichaSeleccionada['codigo']) ?> — <?= htmlspecialchars($fichaSeleccionada['nombre_programa']) ?>
            </h3>
            <span class="text-muted" style="font-size: 0.9rem;">
                Jornada: <strong><?= htmlspecialchars($fichaSeleccionada['nombre_jornada'] ?? 'N/A') ?></strong> | 
                Líder: <strong><?= htmlspecialchars($fichaSeleccionada['nombre_instructor'] ?? 'No asignado') ?></strong>
            </span>
        </div>
        <div>
            <span class="badge badge-green" style="font-size: 0.9rem; padding: 6px 12px;">
                <?= count($sesiones) ?> Sesiones programadas
            </span>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <?php if (!empty($sesiones)): ?>
    <div class="table-wrapper">
        <table class="data-table" id="tablaHorarios">
            <thead>
                <tr>
                    <th>Ficha</th>
                    <th>Fecha</th>
                    <th>Horario</th>
                    <th>Instructor</th>
                    <th>Tipo / Rol</th>
                    <th>Especialidad</th>
                    <th>Estado</th>
                    <?php if (Auth::isAdminOrInstructor()): ?>
                    <th>Acciones</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sesiones as $s): ?>
                <tr>
                    <td>
                        <span class="badge badge-purple">
                            Ficha <?= htmlspecialchars($s['codigo_ficha']) ?>
                        </span>
                    </td>
                    <td>
                        <i class="far fa-calendar text-muted"></i>
                        <strong><?= htmlspecialchars(date('d/m/Y', strtotime($s['fecha']))) ?></strong>
                        <span class="text-muted" style="font-size: 0.8rem; display: block;">
                            <?php
                            $diasSemana = ['1' => 'Lunes', '2' => 'Martes', '3' => 'Miércoles', '4' => 'Jueves', '5' => 'Viernes', '6' => 'Sábado', '7' => 'Domingo'];
                            echo $diasSemana[date('N', strtotime($s['fecha']))] ?? '';
                            ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge badge-blue">
                            <i class="far fa-clock"></i>
                            <?= htmlspecialchars(substr($s['hora_inicio'], 0, 5)) ?> - <?= htmlspecialchars(substr($s['hora_fin'], 0, 5)) ?>
                        </span>
                    </td>
                    <td>
                        <strong><?= htmlspecialchars($s['nombre_instructor']) ?></strong>
                    </td>
                    <td>
                        <?= !empty($s['tipo_instructor']) ? htmlspecialchars($s['tipo_instructor']) : '<span class="text-muted">-</span>' ?>
                    </td>
                    <td>
                        <?php if (!empty($s['especialidad'])): ?>
                            <span class="badge badge-outline"><?= htmlspecialchars($s['especialidad']) ?></span>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                        <?php if (!empty($s['grupo_convergente'])): ?>
                            <span class="text-muted" style="font-size: 0.75rem; display: block;"><?= htmlspecialchars($s['grupo_convergente']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($s['estado'] === 'Activo'): ?>
                            <span class="badge badge-green">Activo</span>
                        <?php elseif ($s['estado'] === 'Finalizada'): ?>
                            <span class="badge badge-secondary">Finalizada</span>
                        <?php else: ?>
                            <span class="badge badge-danger"><?= htmlspecialchars($s['estado']) ?></span>
                        <?php endif; ?>
                    </td>
                    <?php if (Auth::isAdminOrInstructor()): ?>
                    <td>
                        <a href="<?= BASE_URL ?>?action=horarios/eliminar&id=<?= $s['id'] ?><?= $fichaId ? '&ficha_id='.$fichaId : '' ?>" 
                           class="btn btn-sm btn-danger" 
                           data-confirm="¿Estás seguro de eliminar esta sesión de clase?"
                           title="Eliminar sesión">
                            <i class="fas fa-trash"></i>
                        </a>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <i class="fas fa-calendar-times"></i>
        <p>No se encontraron sesiones de horario programadas</p>
        <?php if (Auth::isAdminOrInstructor()): ?>
        <a href="<?= BASE_URL ?>?action=horarios/importar" class="btn btn-primary mt-2">
            <i class="fas fa-file-excel"></i> Cargar Horarios desde Excel
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
