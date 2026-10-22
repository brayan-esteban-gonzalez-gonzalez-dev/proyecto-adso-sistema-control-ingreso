/**
 * rfid.js — Lógica de la terminal RFID del instructor
 *
 * - Reloj en vivo
 * - Toggle de sesión de asistencia (encender/apagar) por ficha
 * - Captura automática del lector USB RFID
 * - Feedback visual de marcación
 * - Actualización dinámica de la tabla de marcaciones
 */
(function () {
    'use strict';

    // ── Estado global ──
    let csrfToken = typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '';
    let fichasData = typeof FICHAS_DATA !== 'undefined' ? FICHAS_DATA : [];
    let feedbackTimer = null;

    // ═══════════════════════════════════════════════════════
    // RELOJ EN VIVO
    // ═══════════════════════════════════════════════════════
    function actualizarReloj() {
        const now = new Date();
        const hh = String(now.getHours()).padStart(2, '0');
        const mm = String(now.getMinutes()).padStart(2, '0');
        const ss = String(now.getSeconds()).padStart(2, '0');

        const clockEl = document.getElementById('rfidClockTime');
        const dateEl = document.getElementById('rfidClockDate');

        if (clockEl) clockEl.textContent = hh + ':' + mm + ':' + ss;

        if (dateEl) {
            const dias = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
            const meses = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
                'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
            dateEl.textContent = dias[now.getDay()] + ', ' +
                now.getDate() + ' de ' + meses[now.getMonth()] + ' de ' + now.getFullYear();
        }
    }

    // ═══════════════════════════════════════════════════════
    // TOGGLE DE SESIÓN
    // ═══════════════════════════════════════════════════════
    window.toggleSesionAsistencia = function (fichaId) {
        const card = document.getElementById('fichaCard_' + fichaId);
        const btn = document.getElementById('toggleBtn_' + fichaId);
        if (!card || !btn) return;

        const sesionId = btn.getAttribute('data-sesion-id');
        const isActive = sesionId && sesionId !== '';

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Procesando…';

        const endpoint = isActive
            ? 'asistencia/cerrarSesionAsistencia'
            : 'asistencia/iniciarSesionAsistencia';

        const body = new FormData();
        body.append('csrf_token', csrfToken);

        if (isActive) {
            body.append('sesion_id', sesionId);
        } else {
            body.append('ficha_id', fichaId);
        }

        fetch(BASE_URL + '?action=' + endpoint, {
            method: 'POST',
            body: body,
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                // Actualizar CSRF
                if (data.csrf_token) csrfToken = data.csrf_token;

                if (data.ok) {
                    if (isActive) {
                        // Se cerró → pasar a estado apagado
                        actualizarUI_fichaApagada(fichaId);
                        mostrarFeedback(true, 'Sesión cerrada', data.mensaje || 'Asistencia finalizada.');
                    } else {
                        // Se abrió → pasar a estado encendido
                        actualizarUI_fichaEncendida(fichaId, data.sesion_id);
                        mostrarFeedback(true, 'Sesión iniciada', data.mensaje || 'Recibiendo llaveros RFID.');
                    }
                } else {
                    // Error: restaurar botón
                    if (isActive) {
                        actualizarUI_fichaEncendida(fichaId, sesionId);
                    } else {
                        actualizarUI_fichaApagada(fichaId);
                    }
                    mostrarFeedback(false, 'Error', data.mensaje || 'Ocurrió un error.');
                }
            })
            .catch(function (err) {
                btn.disabled = false;
                mostrarFeedback(false, 'Error de red', 'No se pudo conectar con el servidor.');
                console.error('Toggle error:', err);
            });
    };

    function actualizarUI_fichaEncendida(fichaId, sesionId) {
        const card = document.getElementById('fichaCard_' + fichaId);
        const btn = document.getElementById('toggleBtn_' + fichaId);
        const badge = document.getElementById('statusBadge_' + fichaId);
        const meta = document.getElementById('fichaMeta_' + fichaId);

        if (card) card.classList.add('rfid-ficha-active');

        if (btn) {
            btn.disabled = false;
            btn.className = 'rfid-toggle-btn rfid-toggle-off';
            btn.setAttribute('data-sesion-id', sesionId);
            btn.innerHTML = '<i class="fas fa-power-off"></i> Apagar Asistencia';
        }

        if (badge) {
            badge.className = 'rfid-status-badge rfid-status-active';
            badge.innerHTML = '<i class="fas fa-circle rfid-pulse-dot"></i> Recibiendo RFID…';
        }

        if (meta) {
            var now = new Date();
            var h = String(now.getHours()).padStart(2, '0');
            var m = String(now.getMinutes()).padStart(2, '0');
            var s = String(now.getSeconds()).padStart(2, '0');
            meta.innerHTML = '<small><i class="fas fa-clock"></i> Iniciada a las ' + h + ':' + m + ':' + s + '</small>';
            meta.style.display = '';
        }

        // Actualizar fichasData
        for (var i = 0; i < fichasData.length; i++) {
            if (fichasData[i].id === fichaId) {
                fichasData[i].sesion_activa = { id: sesionId };
                break;
            }
        }
    }

    function actualizarUI_fichaApagada(fichaId) {
        const card = document.getElementById('fichaCard_' + fichaId);
        const btn = document.getElementById('toggleBtn_' + fichaId);
        const badge = document.getElementById('statusBadge_' + fichaId);
        const meta = document.getElementById('fichaMeta_' + fichaId);

        if (card) card.classList.remove('rfid-ficha-active');

        if (btn) {
            btn.disabled = false;
            btn.className = 'rfid-toggle-btn rfid-toggle-on';
            btn.setAttribute('data-sesion-id', '');
            btn.innerHTML = '<i class="fas fa-power-off"></i> Encender Asistencia';
        }

        if (badge) {
            badge.className = 'rfid-status-badge rfid-status-inactive';
            badge.innerHTML = '<i class="fas fa-circle-stop"></i> Apagada';
        }

        if (meta) meta.style.display = 'none';

        // Actualizar fichasData
        for (var i = 0; i < fichasData.length; i++) {
            if (fichasData[i].id === fichaId) {
                fichasData[i].sesion_activa = null;
                break;
            }
        }
    }

    // ═══════════════════════════════════════════════════════
    // CAPTURA RFID (Lector USB → input oculto)
    // ═══════════════════════════════════════════════════════
    function initRfidInput() {
        const input = document.getElementById('rfidInput');
        if (!input) return;

        // Mantener foco siempre en el input
        function asegurarFoco() {
            // Solo hacer focus si no hay un diálogo modal o prompt abierto
            if (document.activeElement !== input &&
                document.activeElement.tagName !== 'BUTTON') {
                input.focus();
            }
        }

        input.addEventListener('blur', function () {
            setTimeout(asegurarFoco, 200);
        });

        document.addEventListener('click', function (e) {
            // Si hacen clic en un botón, dejarlo. Si no, refocus.
            if (e.target.tagName !== 'BUTTON' && !e.target.closest('button')) {
                setTimeout(asegurarFoco, 100);
            }
        });

        // El lector USB envía los datos y termina con Enter
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const code = input.value.trim();
                input.value = '';
                if (code.length > 0) {
                    enviarMarcacionRfid(code);
                }
            }
        });

        // Foco inicial
        setTimeout(asegurarFoco, 300);
    }

    function enviarMarcacionRfid(rfidTag) {
        // Verificar que haya al menos una sesión activa
        var haySesionActiva = fichasData.some(function (f) {
            return f.sesion_activa !== null;
        });

        if (!haySesionActiva) {
            mostrarFeedback(false, 'Sin sesión activa',
                'Debes encender la toma de asistencia de al menos una ficha.');
            return;
        }

        var body = new FormData();
        body.append('csrf_token', csrfToken);
        body.append('rfid_tag', rfidTag);

        fetch(BASE_URL + '?action=asistencia/registrarMarcacion', {
            method: 'POST',
            body: body,
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.csrf_token) csrfToken = data.csrf_token;

                if (data.ok) {
                    mostrarFeedback(true,
                        data.aprendiz || 'Aprendiz',
                        data.mensaje || 'Registrado');
                    agregarFilaTabla(data);
                } else {
                    mostrarFeedback(false,
                        data.aprendiz || 'Error',
                        data.mensaje || 'No se pudo registrar.');
                }
            })
            .catch(function (err) {
                mostrarFeedback(false, 'Error de red', 'No se pudo conectar.');
                console.error('RFID fetch error:', err);
            });
    }

    // ═══════════════════════════════════════════════════════
    // FEEDBACK VISUAL
    // ═══════════════════════════════════════════════════════
    function mostrarFeedback(isSuccess, title, message) {
        var el = document.getElementById('rfidFeedback');
        var inner = document.getElementById('rfidFeedbackInner');
        var icon = document.getElementById('rfidFeedbackIcon');
        var name = document.getElementById('rfidFeedbackName');
        var msg = document.getElementById('rfidFeedbackMsg');

        if (!el) return;

        // Limpiar timer anterior
        if (feedbackTimer) {
            clearTimeout(feedbackTimer);
            feedbackTimer = null;
        }

        icon.innerHTML = isSuccess
            ? '<i class="fas fa-check-circle"></i>'
            : '<i class="fas fa-times-circle"></i>';

        inner.className = 'rfid-feedback-inner ' + (isSuccess ? 'rfid-feedback-success' : 'rfid-feedback-error');
        name.textContent = title;
        msg.textContent = message;

        el.style.display = 'block';
        el.style.animation = 'none';
        el.offsetHeight; // reflow
        el.style.animation = 'slideDown 0.35s ease-out';

        feedbackTimer = setTimeout(function () {
            el.style.animation = 'fadeOut 0.4s ease-out forwards';
            setTimeout(function () {
                el.style.display = 'none';
            }, 400);
        }, 4000);
    }

    // ═══════════════════════════════════════════════════════
    // TABLA DINÁMICA
    // ═══════════════════════════════════════════════════════
    function agregarFilaTabla(data) {
        var tbody = document.getElementById('tablaMarcacionesBody');
        if (!tbody) return;

        // Quitar fila vacía si existe
        var emptyRow = document.getElementById('emptyRow');
        if (emptyRow) emptyRow.remove();

        var tr = document.createElement('tr');
        tr.className = 'rfid-new-row';

        var retardo = data.minutos_retardo || 0;
        var badgeClass = retardo > 0 ? 'badge-pendiente' : 'badge-activo';
        var badgeText = retardo > 0 ? 'Retardo' : 'Asistió';
        var retardoText = retardo > 0 ? retardo + ' min' : '—';

        tr.innerHTML =
            '<td><strong>' + escapeHtml(data.aprendiz || '—') + '</strong></td>' +
            '<td>' + escapeHtml(String(data.documento || '—')) + '</td>' +
            '<td>' + escapeHtml(String(data.ficha || '—')) + '</td>' +
            '<td>' + escapeHtml(data.hora || '—') + '</td>' +
            '<td><span class="badge ' + badgeClass + '">' + badgeText + '</span></td>' +
            '<td>' + retardoText + '</td>';

        // Insertar al inicio
        if (tbody.firstChild) {
            tbody.insertBefore(tr, tbody.firstChild);
        } else {
            tbody.appendChild(tr);
        }

        // Actualizar contador
        var counter = document.getElementById('contadorMarcaciones');
        if (counter) {
            var rows = tbody.querySelectorAll('tr:not(#emptyRow)');
            counter.textContent = rows.length + ' registros';
        }
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    // ═══════════════════════════════════════════════════════
    // INICIALIZACIÓN
    // ═══════════════════════════════════════════════════════
    document.addEventListener('DOMContentLoaded', function () {
        // Actualizar contador de fichas
        var fichasCount = document.getElementById('fichasCount');
        if (fichasCount) {
            fichasCount.textContent = fichasData.length + ' ficha' + (fichasData.length !== 1 ? 's' : '');
        }

        // Reloj
        actualizarReloj();
        setInterval(actualizarReloj, 1000);

        // RFID input
        initRfidInput();
    });

})();
