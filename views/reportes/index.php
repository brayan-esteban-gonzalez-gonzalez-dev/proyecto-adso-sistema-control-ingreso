<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<?php
$hoy = date('Y-m-d');
$inicioMes = date('Y-m-01');
?>

<div class="card" style="max-width: 760px;">
    <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 6px; color: var(--text-primary);">
        Reportes institucionales
    </h3>
    <p class="text-muted" style="margin-bottom: 18px;">
        Asistencia consolidada, reporte individual para comité de evaluación y relación de excusas.
        Exportación en PDF, Excel o CSV.
    </p>

    <form method="POST" action="<?= BASE_URL ?>?action=reportes/generar">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">

        <div class="form-group">
            <label class="form-label" for="tipo">Tipo de reporte *</label>
            <select id="tipo" name="tipo" class="form-control" required>
                <option value="asistencia_ficha">Asistencia consolidada por ficha</option>
                <option value="asistencia_aprendiz">Asistencia individual por aprendiz</option>
                <option value="excusas">Excusas presentadas, aprobadas y desestimadas</option>
            </select>
        </div>

        <div class="form-group" id="grupoFicha">
            <label class="form-label" for="ficha_id">Ficha</label>
            <select id="ficha_id" name="ficha_id" class="form-control">
                <option value="">— Seleccionar ficha —</option>
                <?php foreach ($fichas as $ficha): ?>
                <?php $fichaIdOpt = (int) ($ficha['id'] ?? $ficha['id_ficha'] ?? 0); ?>
                <option value="<?= $fichaIdOpt ?>">
                    <?= htmlspecialchars(($ficha['codigo'] ?? $ficha['codigo_ficha'] ?? '') . ' - ' . ($ficha['nombre_programa'] ?? '')) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" id="grupoAprendiz" hidden>
            <label class="form-label" for="aprendiz_id">Aprendiz *</label>
            <select id="aprendiz_id" name="aprendiz_id" class="form-control">
                <option value="">— Seleccionar aprendiz —</option>
                <?php foreach ($aprendices as $aprendiz): ?>
                <option value="<?= (int) ($aprendiz['id'] ?? 0) ?>">
                    <?= htmlspecialchars(($aprendiz['apellido'] ?? '') . ' ' . ($aprendiz['nombre'] ?? '') . ' - ' . ($aprendiz['codigo_ficha'] ?? 'Sin ficha')) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label class="form-label" for="fecha_inicio">Desde *</label>
                <input type="date" id="fecha_inicio" name="fecha_inicio" class="form-control" value="<?= $inicioMes ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="fecha_fin">Hasta *</label>
                <input type="date" id="fecha_fin" name="fecha_fin" class="form-control" value="<?= $hoy ?>" required>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" name="formato" value="pdf" class="btn btn-primary">
                <i class="fas fa-file-pdf"></i> PDF
            </button>
            <button type="submit" name="formato" value="xlsx" class="btn btn-secondary">
                <i class="fas fa-file-excel"></i> Excel
            </button>
            <button type="submit" name="formato" value="csv" class="btn btn-secondary">
                <i class="fas fa-file-csv"></i> CSV
            </button>
        </div>
    </form>
</div>

<script>
(function () {
    const tipo = document.getElementById('tipo');
    const grupoFicha = document.getElementById('grupoFicha');
    const grupoAprendiz = document.getElementById('grupoAprendiz');
    const ficha = document.getElementById('ficha_id');
    const aprendiz = document.getElementById('aprendiz_id');

    function sincronizar() {
        const individual = tipo.value === 'asistencia_aprendiz';
        const consolidada = tipo.value === 'asistencia_ficha';
        grupoFicha.hidden = individual;
        grupoAprendiz.hidden = !individual;
        ficha.required = consolidada;
        aprendiz.required = individual;
    }

    tipo.addEventListener('change', sincronizar);
    sincronizar();
})();
</script>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
