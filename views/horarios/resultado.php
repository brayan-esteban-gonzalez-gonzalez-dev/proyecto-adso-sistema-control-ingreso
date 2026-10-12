<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<div class="toolbar">
    <div class="toolbar-left">
        <a href="<?= BASE_URL ?>?action=horarios" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Volver a Horarios
        </a>
    </div>
    <div class="toolbar-right">
        <a href="<?= BASE_URL ?>?action=horarios/importar" class="btn btn-secondary">
            <i class="fas fa-redo"></i> Importar Otro Archivo
        </a>
        <?php if (!empty($resultado['ficha_detectada'])): ?>
        <a href="<?= BASE_URL ?>?action=horarios&ficha_id=<?= $resultado['ficha_detectada']['id'] ?>" class="btn btn-primary">
            <i class="fas fa-calendar-check"></i> Ver Horario de la Ficha
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Tarjeta de Ficha Detectada -->
<?php if (!empty($resultado['ficha_detectada'])): ?>
<div class="card mb-4" style="background: rgba(16, 185, 129, 0.05); border-left: 4px solid var(--accent-green); padding: 18px 24px; margin-bottom: 25px;">
    <div style="display: flex; align-items: center; gap: 15px;">
        <i class="fas fa-check-circle" style="font-size: 2rem; color: var(--accent-green);"></i>
        <div>
            <h3 style="margin: 0 0 4px 0; font-size: 1.15rem; color: #fff;">
                Ficha Detectada: <strong><?= htmlspecialchars($resultado['ficha_detectada']['codigo']) ?></strong> — <?= htmlspecialchars($resultado['ficha_detectada']['nombre_programa']) ?>
            </h3>
            <span class="text-muted" style="font-size: 0.9rem;">
                Jornada: <strong><?= htmlspecialchars($resultado['ficha_detectada']['nombre_jornada'] ?? 'N/A') ?></strong> | 
                Líder: <strong><?= htmlspecialchars($resultado['ficha_detectada']['nombre_instructor'] ?? 'No asignado') ?></strong>
            </span>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Resumen Estadístico de la Importación -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 25px;">
    <div class="card" style="text-align: center; padding: 20px;">
        <span class="text-muted" style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px;">Total Bloques</span>
        <h2 style="font-size: 2.2rem; margin: 8px 0 0 0; color: #fff;"><?= $resultado['total_procesadas'] ?></h2>
    </div>

    <div class="card" style="text-align: center; padding: 20px; border-top: 3px solid var(--accent-green, #10b981);">
        <span class="text-muted" style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; color: var(--accent-green);">Sesiones Creadas</span>
        <h2 style="font-size: 2.2rem; margin: 8px 0 0 0; color: var(--accent-green);"><?= $resultado['creadas'] ?></h2>
    </div>

    <div class="card" style="text-align: center; padding: 20px; border-top: 3px solid var(--accent-yellow, #f59e0b);">
        <span class="text-muted" style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; color: #f59e0b;">Omitidas (Duplicados)</span>
        <h2 style="font-size: 2.2rem; margin: 8px 0 0 0; color: #f59e0b;"><?= $resultado['omitidas'] ?></h2>
    </div>

    <div class="card" style="text-align: center; padding: 20px; border-top: 3px solid var(--accent-red, #ef4444);">
        <span class="text-muted" style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.5px; color: #ef4444;">Errores / No Hallados</span>
        <h2 style="font-size: 2.2rem; margin: 8px 0 0 0; color: #ef4444;"><?= $resultado['errores'] ?></h2>
    </div>
</div>

<!-- Registro Detallado de Filas -->
<div class="card">
    <div class="card-header" style="margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
        <h3 style="font-size: 1.1rem; margin: 0;">
            <i class="fas fa-list-check" style="margin-right: 8px;"></i>
            Detalle de Operaciones Realizadas
        </h3>
        <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Filtrar mensajes..." data-search-table="tablaDetalles">
        </div>
    </div>

    <?php if (!empty($resultado['detalles'])): ?>
    <div class="table-wrapper">
        <table class="data-table" id="tablaDetalles">
            <thead>
                <tr>
                    <th style="width: 100px;">Estado</th>
                    <th>Descripción de la Operación</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($resultado['detalles'] as $item): ?>
                <tr>
                    <td>
                        <?php if ($item['tipo'] === 'success'): ?>
                            <span class="badge badge-green"><i class="fas fa-check"></i> Creado</span>
                        <?php elseif ($item['tipo'] === 'warning'): ?>
                            <span class="badge badge-yellow" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;"><i class="fas fa-exclamation-triangle"></i> Omitido</span>
                        <?php else: ?>
                            <span class="badge badge-danger"><i class="fas fa-times-circle"></i> Error</span>
                        <?php endif; ?>
                    </td>
                    <td style="font-size: 0.92rem; color: <?= $item['tipo'] === 'error' ? '#fca5a5' : ($item['tipo'] === 'warning' ? '#fde68a' : '#cbd5e1') ?>;">
                        <?= htmlspecialchars($item['mensaje']) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <p>No se generaron detalles durante el proceso.</p>
    </div>
    <?php endif; ?>
</div>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
