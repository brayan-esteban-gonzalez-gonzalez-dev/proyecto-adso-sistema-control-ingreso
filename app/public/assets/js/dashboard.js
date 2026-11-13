(function () {
    const root = document.getElementById('dashboardCharts');
    if (!root || typeof Chart === 'undefined') {
        return;
    }

    const texto = '#94a3b8';
    const grid = 'rgba(148, 163, 184, 0.15)';
    Chart.defaults.color = texto;
    Chart.defaults.borderColor = grid;
    Chart.defaults.font.family = 'Inter, sans-serif';

    const opcionesBase = {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { labels: { color: '#e2e8f0' } }
        },
        scales: {
            x: { ticks: { color: texto }, grid: { color: grid } },
            y: { beginAtZero: true, ticks: { color: texto }, grid: { color: grid } }
        }
    };

    fetch(root.dataset.url, { credentials: 'same-origin' })
        .then(function (respuesta) {
            if (!respuesta.ok) {
                throw new Error('indicadores');
            }
            return respuesta.json();
        })
        .then(function (datos) {
            const mes = datos.asistencia_por_mes || {};
            new Chart(document.getElementById('chartAsistenciaMes'), {
                type: 'bar',
                data: {
                    labels: mes.labels || [],
                    datasets: [
                        {
                            label: 'Asistencias',
                            data: mes.asistencias || [],
                            backgroundColor: '#10b981'
                        },
                        {
                            label: 'Inasistencias',
                            data: mes.inasistencias || [],
                            backgroundColor: '#ef4444'
                        }
                    ]
                },
                options: opcionesBase
            });

            const excusas = datos.estado_excusas || {};
            new Chart(document.getElementById('chartExcusas'), {
                type: 'doughnut',
                data: {
                    labels: ['Aprobadas', 'Desestimadas', 'Pendientes'],
                    datasets: [{
                        data: [
                            excusas.aprobadas || 0,
                            excusas.rechazadas || 0,
                            excusas.pendientes || 0
                        ],
                        backgroundColor: ['#10b981', '#ef4444', '#f59e0b'],
                        borderWidth: 0
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { color: '#e2e8f0' } }
                    }
                }
            });

            const puntualidad = datos.puntualidad || {};
            new Chart(document.getElementById('chartPuntualidad'), {
                type: 'line',
                data: {
                    labels: puntualidad.labels || [],
                    datasets: [{
                        label: 'Puntualidad %',
                        data: puntualidad.porcentaje || [],
                        borderColor: '#06b6d4',
                        backgroundColor: 'rgba(6, 182, 212, 0.15)',
                        fill: true,
                        tension: 0.3,
                        spanGaps: true,
                        pointRadius: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { labels: { color: '#e2e8f0' } }
                    },
                    scales: {
                        x: { ticks: { color: texto, maxTicksLimit: 10 }, grid: { color: grid } },
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: { color: texto, callback: function (v) { return v + '%'; } },
                            grid: { color: grid }
                        }
                    }
                }
            });
        })
        .catch(function () {
            const aviso = document.getElementById('chartsError');
            if (aviso) {
                aviso.hidden = false;
            }
        });
})();
