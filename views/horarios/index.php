<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<div class="toolbar">
    <div class="toolbar-left">
        <form method="GET" action="<?= BASE_URL ?>" class="d-flex align-center gap-1">
            <input type="hidden" name="action" value="horarios">
            <select name="id_ficha" class="form-control" style="width: auto; min-width: 250px;" onchange="this.form.submit()">
                <option value="">— Seleccionar Ficha —</option>
                <?php foreach ($fichas as $f): ?>
                <option value="<?= $f['id_ficha'] ?>" <?= ($idFicha ?? 0) == $f['id_ficha'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($f['codigo_ficha'] . ' — ' . $f['nombre_programa']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <?php if (!empty($idFicha ?? 0)): ?>
    <div class="toolbar-right">
        <a href="<?= BASE_URL ?>?action=horarios/crear&id_ficha=<?= $idFicha ?>" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nuevo Horario
        </a>
    </div>
    <?php endif; ?>
</div>

<?php if (!empty($ficha)): ?>
<div class="card mb-3" style="padding: 16px 20px;">
    <strong class="text-green"><i class="fas fa-folder-open"></i> <?= htmlspecialchars($ficha['codigo_ficha']) ?></strong>
    — <?= htmlspecialchars($ficha['nombre_programa']) ?>
    <?php if ($ficha['nombre_instructor']): ?>
        <span class="text-secondary">| Instructor: <?= htmlspecialchars($ficha['nombre_instructor']) ?></span>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
    <?php if (!empty($horarios)): ?>
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Día</th>
                    <th>Hora Entrada</th>
                    <th>Hora Salida</th>
                    <th>Tolerancia</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($horarios as $h): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($h['dia_semana']) ?></strong></td>
                    <td><?= $h['hora_entrada'] ?></td>
                    <td><?= $h['hora_salida'] ?></td>
                    <td><?= $h['tolerancia_minutos'] ?> min</td>
                    <td>
                        <div class="btn-group">
                            <a href="<?= BASE_URL ?>?action=horarios/editar&id=<?= $h['id_horario'] ?>" 
                               class="btn btn-sm btn-secondary" title="Editar">
                                <i class="fas fa-edit"></i>
                            </a>
                            <a href="<?= BASE_URL ?>?action=horarios/eliminar&id=<?= $h['id_horario'] ?>&id_ficha=<?= $idFicha ?? $h['id_ficha'] ?>" 
                               class="btn btn-sm btn-danger" 
                               data-confirm="¿Eliminar el horario del día <?= $h['dia_semana'] ?>?"
                               title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php elseif (!empty($idFicha ?? 0)): ?>
    <div class="empty-state">
        <i class="fas fa-calendar-alt"></i>
        <p>No hay horarios configurados para esta ficha</p>
        <a href="<?= BASE_URL ?>?action=horarios/crear&id_ficha=<?= $idFicha ?>" class="btn btn-primary mt-2">
            <i class="fas fa-plus"></i> Agregar horario
        </a>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <i class="fas fa-hand-pointer"></i>
        <p>Selecciona una ficha para ver sus horarios</p>
    </div>
    <?php endif; ?>
</div>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
