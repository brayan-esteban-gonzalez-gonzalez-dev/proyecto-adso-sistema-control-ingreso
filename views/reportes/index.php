<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<!-- Filtros Dinámicos -->
<form method="GET" action="<?= BASE_URL ?>">
    <input type="hidden" name="action" value="reportes">
    <input type="hidden" name="generar" value="1">
    <div class="filter-bar">
        <div class="filter-group">
            <label><i class="fas fa-layer-group"></i> Modalidad</label>
            <select name="tipo_reporte" class="form-control" id="tipoReporteSelect" onchange="toggleFechas(this.value)">
                <option value="rango" <?= ($tipoReporte ?? 'rango') === 'rango' ? 'selected' : '' ?>>Rango Personalizado</option>
                <option value="dia" <?= ($tipoReporte ?? '') === 'dia' ? 'selected' : '' ?>>Por Día (Específico / Hoy)</option>
                <option value="semana" <?= ($tipoReporte ?? '') === 'semana' ? 'selected' : '' ?>>Semana Actual</option>
                <option value="mes" <?= ($tipoReporte ?? '') === 'mes' ? 'selected' : '' ?>>Mes Actual</option>
            </select>
        </div>
        <div class="filter-group">
            <label><i class="fas fa-graduation-cap"></i> Ficha</label>
            <select name="id_ficha" class="form-control">
                <option value="">— Todas las Fichas —</option>
                <?php foreach ($fichas as $f): ?>
                <option value="<?= $f['id_ficha'] ?>" <?= ($idFicha ?? '') == $f['id_ficha'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($f['codigo_ficha'] . ' — ' . $f['nombre_programa']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group" id="grupoFechaInicio">
            <label><i class="fas fa-calendar-alt"></i> Fecha Inicio</label>
            <input type="date" name="fecha_inicio" class="form-control" value="<?= htmlspecialchars($fechaInicio ?? date('Y-m-01')) ?>">
        </div>
        <div class="filter-group" id="grupoFechaFin">
            <label><i class="fas fa-calendar-alt"></i> Fecha Fin</label>
            <input type="date" name="fecha_fin" class="form-control" value="<?= htmlspecialchars($fechaFin ?? date('Y-m-d')) ?>">
        </div>
        <div class="filter-group" style="flex: 0; align-self: flex-end;">
            <label>&nbsp;</label>
            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-search"></i> Generar
            </button>
        </div>
    </div>
</form>

<?php if ($generado): ?>

<!-- Barra de herramientas con botones de exportación -->
<div class="toolbar" style="margin-top: 15px;">
    <div class="toolbar-left">
        <span class="text-secondary">
            <strong><?= count($registros) ?></strong> aprendices evaluados en el período 
            <span class="badge badge-blue"><?= htmlspecialchars($fechaInicio ?? '') ?> al <?= htmlspecialchars($fechaFin ?? '') ?></span>
        </span>
    </div>
    <div class="toolbar-right">
        <?php
        $exportQuery = http_build_query(array_filter([
            'id_ficha'     => $idFicha,
            'fecha_inicio' => $fechaInicio,
            'fecha_fin'    => $fechaFin,
            'tipo_reporte' => $tipoReporte,
        ]));
        ?>
        <a href="<?= BASE_URL ?>?action=reportes/exportarPdf&<?= $exportQuery ?>" class="btn btn-sm btn-danger" target="_blank">
            <i class="fas fa-file-pdf"></i> Exportar PDF
        </a>
        <a href="<?= BASE_URL ?>?action=reportes/exportarExcel&<?= $exportQuery ?>" class="btn btn-sm btn-primary" style="background: var(--gradient-green);">
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
                    <th style="width: 40px;">#</th>
                    <th>Documento</th>
                    <th>Nombre Completo del Aprendiz</th>
                    <th>Ficha</th>
                    <th>Programa</th>
                    <th>Instructor Líder</th>
                    <th style="text-align: right;">Min. Retardo</th>
                    <th style="text-align: right;">Inasistencias</th>
                    <th>Días de Inasistencia (Detalle)</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $sumRetardo = 0;
                $sumInasistencias = 0;
                foreach ($registros as $i => $reg): 
                    $sumRetardo += (int) $reg['total_minutos_retardo'];
                    $sumInasistencias += (int) $reg['total_inasistencias'];
                ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><strong><?= htmlspecialchars((string) $reg['num_documento']) ?></strong></td>
                    <td><?= htmlspecialchars($reg['nombre_completo']) ?></td>
                    <td><span class="badge badge-blue"><?= htmlspecialchars($reg['codigo_ficha']) ?></span></td>
                    <td><?= htmlspecialchars($reg['nombre_programa']) ?></td>
                    <td><?= htmlspecialchars($reg['nombre_instructor']) ?></td>
                    <td style="text-align: right;">
                        <?php if ($reg['total_minutos_retardo'] > 0): ?>
                            <span class="badge badge-retardo" style="background-color: rgba(245, 158, 11, 0.2); color: #f59e0b; font-weight: bold;">
                                <?= $reg['total_minutos_retardo'] ?> min
                            </span>
                        <?php else: ?>
                            <span style="color: #10b981;">0 min</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align: right;">
                        <?php if ($reg['total_inasistencias'] > 0): ?>
                            <span class="badge badge-inasistencia" style="background-color: rgba(239, 68, 68, 0.2); color: #ef4444; font-weight: bold;">
                                <?= $reg['total_inasistencias'] ?>
                            </span>
                        <?php else: ?>
                            <span style="color: #10b981;">0</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size: 0.85rem; color: #94a3b8;">
                        <?= htmlspecialchars($reg['dias_inasistencia']) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background: rgba(255, 255, 255, 0.03); font-weight: bold; border-top: 2px solid var(--border-color);">
                    <td colspan="6" style="text-align: right;">TOTALES CONSOLIDADOS:</td>
                    <td style="text-align: right; color: #f59e0b;"><?= $sumRetardo ?> min</td>
                    <td style="text-align: right; color: #ef4444;"><?= $sumInasistencias ?></td>
                    <td>—</td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <i class="fas fa-search"></i>
        <p>No se encontraron registros de aprendices con los filtros aplicados</p>
    </div>
    <?php endif; ?>
</div>

<?php else: ?>
<div class="card">
    <div class="empty-state">
        <i class="fas fa-file-alt"></i>
        <p>Seleccione los parámetros deseados y presione "Generar" para consultar el reporte</p>
        <small>Puede filtrar por modalidad (día, semana, mes o rango personalizado) y por ficha académica</small>
    </div>
</div>
<?php endif; ?>

<script>
function toggleFechas(tipo) {
    const grupoInicio = document.getElementById('grupoFechaInicio');
    const grupoFin = document.getElementById('grupoFechaFin');
    if (!grupoInicio || !grupoFin) return;

    if (tipo === 'dia') {
        grupoFin.style.display = 'none';
        grupoInicio.querySelector('label').innerHTML = '<i class="fas fa-calendar-day"></i> Fecha';
    } else if (tipo === 'semana' || tipo === 'mes') {
        grupoFin.style.display = 'none';
        grupoInicio.querySelector('label').innerHTML = '<i class="fas fa-calendar"></i> Fecha Referencia';
    } else {
        grupoFin.style.display = 'block';
        grupoInicio.querySelector('label').innerHTML = '<i class="fas fa-calendar-alt"></i> Fecha Inicio';
    }
}
document.addEventListener('DOMContentLoaded', function() {
    const sel = document.getElementById('tipoReporteSelect');
    if (sel) toggleFechas(sel.value);
});
</script>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
