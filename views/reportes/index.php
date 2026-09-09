<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<!-- Filtros -->
<form method="GET" action="<?= BASE_URL ?>">
    <input type="hidden" name="action" value="reportes">
    <div class="filter-bar">
        <div class="filter-group">
            <label>Ficha</label>
            <select name="id_ficha" class="form-control">
                <option value="">— Todas —</option>
                <?php foreach ($fichas as $f): ?>
                <option value="<?= $f['id_ficha'] ?>" <?= ($idFicha ?? '') == $f['id_ficha'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($f['codigo_ficha'] . ' — ' . $f['nombre_programa']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>Fecha Inicio</label>
            <input type="date" name="fecha_inicio" class="form-control" value="<?= htmlspecialchars($fechaInicio ?? '') ?>">
        </div>
        <div class="filter-group">
            <label>Fecha Fin</label>
            <input type="date" name="fecha_fin" class="form-control" value="<?= htmlspecialchars($fechaFin ?? '') ?>">
        </div>
        <div class="filter-group">
            <label>Estado</label>
            <select name="estado" class="form-control">
                <option value="">— Todos —</option>
                <option value="A_Tiempo" <?= ($estado ?? '') === 'A_Tiempo' ? 'selected' : '' ?>>A Tiempo</option>
                <option value="Retardo" <?= ($estado ?? '') === 'Retardo' ? 'selected' : '' ?>>Retardo</option>
                <option value="Inasistencia" <?= ($estado ?? '') === 'Inasistencia' ? 'selected' : '' ?>>Inasistencia</option>
                <option value="Salida_Temprana" <?= ($estado ?? '') === 'Salida_Temprana' ? 'selected' : '' ?>>Salida Temprana</option>
                <option value="Justificado" <?= ($estado ?? '') === 'Justificado' ? 'selected' : '' ?>>Justificado</option>
            </select>
        </div>
        <div class="filter-group" style="flex: 0;">
            <label>&nbsp;</label>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-search"></i> Generar
            </button>
        </div>
    </div>
</form>

<?php if ($generado): ?>

<!-- Botones de exportación -->
<div class="toolbar">
    <div class="toolbar-left">
        <span class="text-secondary">
            <strong><?= count($registros) ?></strong> registros encontrados
        </span>
    </div>
    <div class="toolbar-right">
        <?php
        $exportParams = http_build_query(array_filter([
            'action'       => 'reportes/exportarPdf',
            'id_ficha'     => $idFicha,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin'    => $fechaFin,
            'estado'       => $estado,
        ]));
        $exportParamsExcel = http_build_query(array_filter([
            'action'       => 'reportes/exportarExcel',
            'id_ficha'     => $idFicha,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin'    => $fechaFin,
            'estado'       => $estado,
        ]));
        ?>
        <a href="<?= BASE_URL ?>?<?= $exportParams ?>" class="btn btn-sm btn-danger">
            <i class="fas fa-file-pdf"></i> Exportar PDF
        </a>
        <a href="<?= BASE_URL ?>?<?= $exportParamsExcel ?>" class="btn btn-sm btn-primary" style="background: var(--gradient-green);">
            <i class="fas fa-file-excel"></i> Exportar Excel
        </a>
    </div>
</div>

<div class="card">
    <?php if (!empty($registros)): ?>
    <div class="table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Documento</th>
                    <th>Nombre</th>
                    <th>Ficha</th>
                    <th>Fecha</th>
                    <th>Entrada</th>
                    <th>Salida</th>
                    <th>Estado</th>
                    <th>Retardo</th>
                    <th>Sal. Ant.</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($registros as $i => $reg): ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><?= htmlspecialchars($reg['num_documento']) ?></td>
                    <td><strong><?= htmlspecialchars($reg['nombre'] . ' ' . $reg['apellido']) ?></strong></td>
                    <td><span class="badge badge-blue"><?= htmlspecialchars($reg['codigo_ficha']) ?></span></td>
                    <td><?= $reg['fecha'] ?></td>
                    <td><?= $reg['hora_entrada'] ?? '—' ?></td>
                    <td><?= $reg['hora_salida'] ?? '—' ?></td>
                    <td>
                        <span class="badge badge-<?= strtolower($reg['estado']) ?>">
                            <?= str_replace('_', ' ', $reg['estado']) ?>
                        </span>
                    </td>
                    <td><?= $reg['minutos_retardo'] > 0 ? $reg['minutos_retardo'] . ' min' : '—' ?></td>
                    <td><?= $reg['minutos_salida_anticipada'] > 0 ? $reg['minutos_salida_anticipada'] . ' min' : '—' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <i class="fas fa-search"></i>
        <p>No se encontraron registros con los filtros aplicados</p>
    </div>
    <?php endif; ?>
</div>

<?php else: ?>
<div class="card">
    <div class="empty-state">
        <i class="fas fa-file-alt"></i>
        <p>Seleccione los filtros y presione "Generar" para crear un reporte</p>
        <small>Puede filtrar por ficha, rango de fechas y estado de asistencia</small>
    </div>
</div>
<?php endif; ?>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
