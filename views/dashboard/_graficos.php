<div class="charts-grid" id="dashboardCharts" data-url="<?= BASE_URL ?>?action=dashboard/datosGraficos">
    <div class="card">
        <h3 class="chart-title">Asistencias vs inasistencias</h3>
        <div class="chart-box">
            <canvas id="chartAsistenciaMes"></canvas>
        </div>
    </div>
    <div class="card">
        <h3 class="chart-title">Excusas</h3>
        <div class="chart-box">
            <canvas id="chartExcusas"></canvas>
        </div>
    </div>
    <div class="card chart-wide">
        <h3 class="chart-title">Puntualidad de los últimos 30 días</h3>
        <div class="chart-box">
            <canvas id="chartPuntualidad"></canvas>
        </div>
    </div>
</div>
<p id="chartsError" class="text-muted" style="margin-top: 12px;" hidden>No se pudieron cargar los indicadores.</p>
<?php $loadDashboardJs = true; ?>
