<?php 
$loadRfidJs = false; // No usamos rfid.js externo, el JS va inline abajo
require_once ROOT_PATH . '/views/layouts/header.php'; 
?>

<div class="rfid-container">
    <!-- Reloj en vivo -->
    <div class="rfid-clock">
        <div class="rfid-clock-time" id="rfidClockTime">--:--:--</div>
        <div class="rfid-clock-date" id="rfidClockDate"></div>
    </div>

    <!-- Tarjeta de escaneo -->
    <div class="rfid-scan-card" id="rfidScanCard">
        <div class="rfid-icon">
            <i class="fas fa-wifi"></i>
        </div>
        <p class="rfid-instruction">Acerque el llavero RFID al lector o ingrese el código manualmente</p>
        
        <input type="text" 
               id="rfidInput" 
               class="rfid-input" 
               placeholder="Código RFID" 
               autocomplete="off"
               autofocus>

        <!-- Resultado de la marcación -->
        <div class="rfid-result" id="rfidResult" style="display:none;">
            <div class="rfid-result-message" id="rfidResultMessage"></div>
            <div class="rfid-result-detail" id="rfidResultDetail"></div>
        </div>
    </div>

    <!-- Ingresos recientes de hoy -->
    <div class="rfid-recent mt-3">
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">
                    <i class="fas fa-list"></i> Marcaciones de Hoy
                </h2>
                <span class="badge badge-blue"><?= count($ultimosIngresos) ?> registros</span>
            </div>

            <?php if (!empty($ultimosIngresos)): ?>
            <div class="table-wrapper">
                <table class="data-table" id="tablaIngresosRecientes">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Documento</th>
                            <th>Ficha</th>
                            <th>Entrada</th>
                            <th>Salida</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ultimosIngresos as $ing): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($ing['nombre'] . ' ' . $ing['apellido']) ?></strong></td>
                            <td><?= htmlspecialchars($ing['identificacion'] ?? '') ?></td>
                            <td><?= htmlspecialchars($ing['codigo_ficha'] ?? '—') ?></td>
                            <td><?= $ing['hora_entrada'] ?? '—' ?></td>
                            <td><?= $ing['hora_salida'] ?? '—' ?></td>
                            <td>
                                <span class="badge badge-<?= strtolower($ing['estado']) ?>">
                                    <?= str_replace('_', ' ', $ing['estado']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <p>Sin marcaciones aún hoy</p>
                <small>Las marcaciones aparecerán aquí en tiempo real</small>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
(function() {
    'use strict';

    // ═══════════════════════════════════════════════════════
    // RELOJ EN VIVO
    // ═══════════════════════════════════════════════════════
    var clockTime = document.getElementById('rfidClockTime');
    var clockDate = document.getElementById('rfidClockDate');

    var diasSemana = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
    var meses = ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];

    function actualizarReloj() {
        var now = new Date();
        var h = String(now.getHours()).padStart(2, '0');
        var m = String(now.getMinutes()).padStart(2, '0');
        var s = String(now.getSeconds()).padStart(2, '0');
        clockTime.textContent = h + ':' + m + ':' + s;

        var dia = diasSemana[now.getDay()];
        var dd  = now.getDate();
        var mes = meses[now.getMonth()];
        var yyyy = now.getFullYear();
        clockDate.textContent = dia + ', ' + dd + ' de ' + mes + ' de ' + yyyy;
    }

    actualizarReloj();
    setInterval(actualizarReloj, 1000);

    // ═══════════════════════════════════════════════════════
    // MARCACIÓN RFID (fetch POST)
    // ═══════════════════════════════════════════════════════
    var rfidInput   = document.getElementById('rfidInput');
    var rfidResult  = document.getElementById('rfidResult');
    var rfidMessage = document.getElementById('rfidResultMessage');
    var rfidDetail  = document.getElementById('rfidResultDetail');
    var rfidCard    = document.getElementById('rfidScanCard');

    // Token CSRF inicial generado por el controlador
    var csrfToken = <?= json_encode($csrf_token) ?>;

    // URL base del proyecto
    var baseUrl = <?= json_encode(BASE_URL) ?>;

    // Evitar envíos duplicados
    var procesando = false;

    rfidInput.addEventListener('keydown', function(e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();

        var codigo = rfidInput.value.trim();
        if (!codigo || procesando) return;

        procesando = true;
        rfidInput.disabled = true;

        // Preparar FormData (simula un POST de formulario, como en el resto del proyecto)
        var formData = new FormData();
        formData.append('codigo_llavero', codigo);
        formData.append('csrf_token', csrfToken);

        fetch(baseUrl + '?action=asistencia/marcar', {
            method: 'POST',
            body: formData
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            mostrarResultado(data);

            // Actualizar CSRF si el servidor envió uno nuevo
            if (data.csrf_token) {
                csrfToken = data.csrf_token;
            }

            // Si fue exitoso, refrescar la tabla de marcaciones
            if (data.ok) {
                refrescarTabla();
            }
        })
        .catch(function(err) {
            mostrarResultado({
                ok: false,
                mensaje: 'Error de conexión. Verifica tu red.'
            });
            console.error('RFID fetch error:', err);
        })
        .finally(function() {
            // Limpiar y devolver foco para la siguiente marcación
            rfidInput.value = '';
            rfidInput.disabled = false;
            rfidInput.focus();
            procesando = false;
        });
    });

    function mostrarResultado(data) {
        rfidResult.style.display = 'block';

        // Quitar clases previas
        rfidResult.className = 'rfid-result';

        if (data.ok) {
            rfidResult.classList.add('rfid-result-success');
            var icono = data.tipo === 'entrada' ? '✅' : '🏁';
            rfidMessage.textContent = icono + ' ' + (data.tipo === 'entrada' ? 'ENTRADA' : 'SALIDA');
            rfidDetail.textContent = data.mensaje;
        } else {
            rfidResult.classList.add('rfid-result-error');
            rfidMessage.textContent = '⚠️ ERROR';
            rfidDetail.textContent = data.mensaje;
        }

        // Animar la aparición
        rfidResult.style.animation = 'none';
        rfidResult.offsetHeight; // trigger reflow
        rfidResult.style.animation = 'slideDown 0.35s ease-out';

        // Auto-ocultar después de 5 segundos
        clearTimeout(rfidResult._hideTimeout);
        rfidResult._hideTimeout = setTimeout(function() {
            rfidResult.style.display = 'none';
        }, 5000);
    }

    // ═══════════════════════════════════════════════════════
    // REFRESCAR TABLA DE MARCACIONES (sin recargar página)
    // ═══════════════════════════════════════════════════════
    function refrescarTabla() {
        // Hacemos un fetch GET a la misma página RFID y extraemos el tbody
        fetch(baseUrl + '?action=asistencia/rfid', {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(response) {
            return response.text();
        })
        .then(function(html) {
            // Crear un DOM temporal para extraer la tabla
            var parser = new DOMParser();
            var doc = parser.parseFromString(html, 'text/html');
            var nuevaTabla = doc.querySelector('.rfid-recent .card');
            var contenedorActual = document.querySelector('.rfid-recent .card');

            if (nuevaTabla && contenedorActual) {
                contenedorActual.innerHTML = nuevaTabla.innerHTML;
            }
        })
        .catch(function(err) {
            // Silenciar errores de refresco de tabla; no es crítico
            console.warn('No se pudo refrescar la tabla:', err);
        });
    }

    // Mantener foco en el input siempre (comportamiento de kiosco)
    document.addEventListener('click', function(e) {
        // Solo re-enfocar si no se hizo clic en un enlace o botón del menú
        if (!e.target.closest('.sidebar') && !e.target.closest('.top-header') && !e.target.closest('a')) {
            rfidInput.focus();
        }
    });

})();
</script>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
