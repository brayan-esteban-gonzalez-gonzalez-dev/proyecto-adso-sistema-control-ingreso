/**
 * rfid.js — Lógica de la pantalla de marcación RFID
 * 
 * Reloj en vivo, lectura de código RFID (simulación o lector),
 * procesamiento AJAX y feedback visual.
 */
document.addEventListener('DOMContentLoaded', () => {
    const rfidInput   = document.getElementById('rfidInput');
    const scanCard    = document.getElementById('rfidScanCard');
    const resultDiv   = document.getElementById('rfidResult');
    const resultMsg   = document.getElementById('rfidResultMessage');
    const resultDet   = document.getElementById('rfidResultDetail');
    const clockTime   = document.getElementById('rfidClockTime');
    const clockDate   = document.getElementById('rfidClockDate');

    // ─── Reloj en vivo ───
    function updateClock() {
        const now = new Date();
        const hours   = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        const seconds = String(now.getSeconds()).padStart(2, '0');

        if (clockTime) {
            clockTime.textContent = `${hours}:${minutes}:${seconds}`;
        }

        if (clockDate) {
            const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            clockDate.textContent = now.toLocaleDateString('es-CO', options);
        }
    }

    updateClock();
    setInterval(updateClock, 1000);

    // ─── Autofocus en el input RFID ───
    if (rfidInput) {
        rfidInput.focus();

        // Re-focus si se pierde el foco (para lectores RFID reales)
        setInterval(() => {
            if (document.activeElement !== rfidInput && !document.querySelector('.modal-overlay.show')) {
                rfidInput.focus();
            }
        }, 2000);
    }

    // ─── Procesar lectura RFID al presionar Enter ───
    if (rfidInput) {
        rfidInput.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const codigo = rfidInput.value.trim();
                if (codigo) {
                    procesarRfid(codigo);
                }
            }
        });
    }

    /**
     * Envía el código RFID al servidor vía AJAX
     */
    function procesarRfid(codigo) {
        // Estado visual: escaneando
        scanCard.className = 'rfid-scan-card scanning';
        rfidInput.disabled = true;

        // FormData para el POST
        const formData = new FormData();
        formData.append('codigo_rfid', codigo);

        fetch(BASE_URL + '?action=asistencia/marcar', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            // Mostrar resultado
            mostrarResultado(data);

            // Limpiar y reenfocar
            rfidInput.value = '';
            rfidInput.disabled = false;
            rfidInput.focus();

            // Recargar lista de ingresos recientes después de 1 segundo
            setTimeout(() => {
                if (data.success) {
                    recargarIngresosRecientes();
                }
            }, 1000);
        })
        .catch(error => {
            console.error('Error:', error);
            mostrarResultado({
                success: false,
                message: 'Error de conexión con el servidor.',
                tipo: 'error'
            });
            rfidInput.value = '';
            rfidInput.disabled = false;
            rfidInput.focus();
        });
    }

    /**
     * Muestra el resultado de la marcación con feedback visual
     */
    function mostrarResultado(data) {
        const tipo = data.tipo || 'info';

        // Actualizar card
        scanCard.className = `rfid-scan-card ${tipo}`;

        // Mostrar resultado
        if (resultDiv && resultMsg) {
            resultDiv.className = `rfid-result show ${tipo}`;
            resultMsg.textContent = data.message;

            if (resultDet && data.aprendiz) {
                let detalle = `${data.aprendiz}`;
                if (data.documento) detalle += ` — Doc: ${data.documento}`;
                if (data.ficha) detalle += ` — Ficha: ${data.ficha}`;
                if (data.hora) detalle += ` — Hora: ${data.hora}`;
                resultDet.textContent = detalle;
            } else if (resultDet) {
                resultDet.textContent = '';
            }
        }

        // Volver al estado normal después de 5 segundos
        setTimeout(() => {
            scanCard.className = 'rfid-scan-card';
            if (resultDiv) {
                resultDiv.className = 'rfid-result';
            }
        }, 5000);
    }

    /**
     * Recarga la lista de ingresos recientes
     */
    function recargarIngresosRecientes() {
        const tabla = document.getElementById('tablaIngresosRecientes');
        if (!tabla) return;

        fetch(BASE_URL + '?action=asistencia/rfid')
            .then(response => response.text())
            .then(html => {
                // Parsear el HTML y extraer solo la tabla
                const parser = new DOMParser();
                const doc = parser.parseFromString(html, 'text/html');
                const nuevaTabla = doc.getElementById('tablaIngresosRecientes');
                if (nuevaTabla) {
                    tabla.innerHTML = nuevaTabla.innerHTML;
                }
            })
            .catch(err => console.error('Error al recargar:', err));
    }
});
