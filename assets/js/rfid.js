/**
 * rfid.js — Lógica de la terminal de asistencia (RFID + Marcación Manual)
 *
 * - Reloj en vivo
 * - Toggle de sesión de asistencia (encender/apagar) por ficha
 * - Blindaje anti-robo de foco del lector USB RFID
 * - Renovación estricta de CSRF en todos los flujos AJAX
 * - Mecanismo 1: Marcación rápida por documento
 * - Mecanismo 2: Nómina interactiva con marcación con un solo clic
 * - Actualización dinámica y sincronización de contadores en tiempo real
 */
(function () {
    'use strict';

    // ── Estado global ──
    let csrfToken     = typeof CSRF_TOKEN !== 'undefined' ? CSRF_TOKEN : '';
    let fichasData    = typeof FICHAS_DATA !== 'undefined' ? FICHAS_DATA : [];
    let nominaData    = [];
    let selectedFichaId = null;
    let feedbackTimer = null;

    /**
     * Actualiza siempre el token CSRF recibido en cualquier respuesta del servidor
     */
    function actualizarCsrf(data) {
        if (data && data.csrf_token) {
            csrfToken = data.csrf_token;
        }
    }

    // ═══════════════════════════════════════════════════════
    // RELOJ EN VIVO
    // ═══════════════════════════════════════════════════════
    function actualizarReloj() {
        const now = new Date();
        const hh = String(now.getHours()).padStart(2, '0');
        const mm = String(now.getMinutes()).padStart(2, '0');
        const ss = String(now.getSeconds()).padStart(2, '0');

        const clockEl = document.getElementById('rfidClockTime');
        const dateEl  = document.getElementById('rfidClockDate');

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
        const btn  = document.getElementById('toggleBtn_' + fichaId);
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
                // Renovación garantizada de CSRF
                actualizarCsrf(data);

                if (data.ok) {
                    if (isActive) {
                        actualizarUI_fichaApagada(fichaId);
                        mostrarFeedback(true, 'Sesión cerrada', data.mensaje || 'Asistencia finalizada.');
                    } else {
                        actualizarUI_fichaEncendida(fichaId, data.sesion_id);
                        mostrarFeedback(true, 'Sesión iniciada', data.mensaje || 'Recibiendo marcaciones.');
                        if (data.nomina) {
                            nominaData = data.nomina;
                        }
                    }
                } else {
                    if (isActive) {
                        actualizarUI_fichaEncendida(fichaId, sesionId);
                    } else {
                        actualizarUI_fichaApagada(fichaId);
                    }
                    mostrarFeedback(false, 'Error', data.mensaje || 'Ocurrió un error.');
                }

                sincronizarTerminalYNomina();
            })
            .catch(function (err) {
                btn.disabled = false;
                mostrarFeedback(false, 'Error de red', 'No se pudo conectar con el servidor.');
                console.error('Toggle error:', err);
                sincronizarTerminalYNomina();
            });
    };

    function actualizarUI_fichaEncendida(fichaId, sesionId) {
        const card  = document.getElementById('fichaCard_' + fichaId);
        const btn   = document.getElementById('toggleBtn_' + fichaId);
        const badge = document.getElementById('statusBadge_' + fichaId);
        const meta  = document.getElementById('fichaMeta_' + fichaId);

        if (card) card.classList.add('rfid-ficha-active');

        if (btn) {
            btn.disabled = false;
            btn.className = 'rfid-toggle-btn rfid-toggle-off';
            btn.setAttribute('data-sesion-id', sesionId);
            btn.innerHTML = '<i class="fas fa-power-off"></i> Apagar Asistencia';
        }

        if (badge) {
            badge.className = 'rfid-status-badge rfid-status-active';
            badge.innerHTML = '<i class="fas fa-circle rfid-pulse-dot"></i> Recibiendo Asistencia…';
        }

        if (meta) {
            const now = new Date();
            const h = String(now.getHours()).padStart(2, '0');
            const m = String(now.getMinutes()).padStart(2, '0');
            const s = String(now.getSeconds()).padStart(2, '0');
            meta.innerHTML = '<small><i class="fas fa-clock"></i> Iniciada a las ' + h + ':' + m + ':' + s + '</small>';
            meta.style.display = '';
        }

        for (let i = 0; i < fichasData.length; i++) {
            if (fichasData[i].id === fichaId) {
                fichasData[i].sesion_activa = { id: sesionId };
                break;
            }
        }
    }

    function actualizarUI_fichaApagada(fichaId) {
        const card  = document.getElementById('fichaCard_' + fichaId);
        const btn   = document.getElementById('toggleBtn_' + fichaId);
        const badge = document.getElementById('statusBadge_' + fichaId);
        const meta  = document.getElementById('fichaMeta_' + fichaId);

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

        for (let i = 0; i < fichasData.length; i++) {
            if (fichasData[i].id === fichaId) {
                fichasData[i].sesion_activa = null;
                break;
            }
        }
    }

    // ═══════════════════════════════════════════════════════
    // SINCRONIZACIÓN DE TERMINAL Y CONTROLES MANUALES
    // ═══════════════════════════════════════════════════════
    function sincronizarTerminalYNomina() {
        const fichasActivas = fichasData.filter(function (f) {
            return f.sesion_activa !== null;
        });

        const statusBox   = document.getElementById('rfidStatusBox');
        const statusTitle = document.getElementById('rfidStatusTitle');
        const statusDesc  = document.getElementById('rfidStatusDesc');

        const selectFicha = document.getElementById('selectFichaManual');
        const inputDoc    = document.getElementById('inputPinRespaldo') || document.getElementById('manualDocInput');
        const btnManual   = document.getElementById('btnMarcarPorDocumento') || document.getElementById('btnRegistrarManual');
        const statusDoc   = document.getElementById('manualDocStatus');
        const buscadorNom = document.getElementById('buscadorNomina');
        const subtitulo   = document.getElementById('subtituloNomina');

        if (fichasActivas.length > 0) {
            // Activar terminal visual
            if (statusBox) statusBox.classList.add('active');
            if (statusTitle) statusTitle.textContent = 'Terminal Activa — Esperando Carnet o Marcación';
            if (statusDesc) statusDesc.textContent = 'Acerca el llavero RFID al lector USB o registra por documento.';

            // Poblar selector de fichas activas
            if (selectFicha) {
                const prevVal = selectFicha.value;
                selectFicha.innerHTML = '';
                fichasActivas.forEach(function (f) {
                    const opt = document.createElement('option');
                    opt.value = f.id;
                    opt.textContent = 'Ficha ' + f.codigo + ' (' + f.nombre_programa + ')';
                    opt.setAttribute('data-sesion-id', f.sesion_activa.id);
                    selectFicha.appendChild(opt);
                });

                // Mantener selección si aún es válida, o seleccionar la primera activa
                const existeVal = fichasActivas.some(function (f) { return String(f.id) === String(prevVal); });
                if (existeVal) {
                    selectFicha.value = prevVal;
                } else {
                    selectFicha.value = fichasActivas[0].id;
                }
                selectFicha.disabled = (fichasActivas.length <= 1);
            }

            selectedFichaId = selectFicha ? parseInt(selectFicha.value, 10) : fichasActivas[0].id;

            // Habilitar controles manuales
            if (inputDoc) inputDoc.disabled = false;
            if (btnManual) btnManual.disabled = false;
            if (buscadorNom) buscadorNom.disabled = false;

            const fichaSeleccionada = fichasActivas.find(function(f) { return f.id === selectedFichaId; }) || fichasActivas[0];
            if (statusDoc) {
                statusDoc.innerHTML = '<small style="color:var(--accent-green);"><i class="fas fa-check-circle"></i> Listo para marcar en Ficha <strong>' + escapeHtml(fichaSeleccionada.codigo) + '</strong>.</small>';
            }
            if (subtitulo) {
                subtitulo.textContent = 'Mostrando nómina de la Ficha ' + fichaSeleccionada.codigo + ' — ' + fichaSeleccionada.nombre_programa;
            }

            // Cargar nómina de la ficha activa seleccionada
            cargarNominaSesion(selectedFichaId, fichaSeleccionada.sesion_activa.id);

        } else {
            // Desactivar terminal visual
            if (statusBox) statusBox.classList.remove('active');
            if (statusTitle) statusTitle.textContent = 'Terminal en Espera';
            if (statusDesc) statusDesc.textContent = 'Enciende la asistencia de al menos una ficha para recibir carnets RFID o marcar manualmente.';

            if (selectFicha) {
                selectFicha.innerHTML = '<option value="">Sin sesiones activas...</option>';
                selectFicha.disabled = true;
            }
            if (inputDoc) {
                inputDoc.value = '';
                inputDoc.disabled = true;
            }
            if (btnManual) btnManual.disabled = true;
            if (buscadorNom) {
                buscadorNom.value = '';
                buscadorNom.disabled = true;
            }
            if (statusDoc) {
                statusDoc.innerHTML = '<small style="color:var(--text-muted);"><i class="fas fa-info-circle"></i> Enciende una sesión para habilitar la entrada manual.</small>';
            }
            if (subtitulo) {
                subtitulo.textContent = 'Visualiza y marca asistencia con un solo clic.';
            }

            selectedFichaId = null;
            nominaData = [];
            mostrarPlaceholderNomina('No hay sesiones de asistencia encendidas. Activa una ficha arriba para cargar la nómina en vivo.');
            actualizarKpis([]);
        }
    }

    // ═══════════════════════════════════════════════════════
    // NÓMINA EN TIEMPO REAL (CARGA Y RENDERIZADO)
    // ═══════════════════════════════════════════════════════
    function cargarNominaSesion(fichaId, sesionId) {
        if (!fichaId) return;

        const body = new FormData();
        body.append('csrf_token', csrfToken);
        body.append('ficha_id', fichaId);
        if (sesionId) body.append('sesion_id', sesionId);

        fetch(BASE_URL + '?action=asistencia/getNominaSesion', {
            method: 'POST',
            body: body,
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                actualizarCsrf(data);
                if (data.ok && Array.isArray(data.nomina)) {
                    nominaData = data.nomina;
                    const filtroActual = document.getElementById('buscadorNomina') ? document.getElementById('buscadorNomina').value.trim() : '';
                    renderNominaTable(filtroActual);
                } else {
                    mostrarPlaceholderNomina(data.mensaje || 'No fue posible cargar la nómina.');
                }
            })
            .catch(function (err) {
                console.error('Error cargando nómina:', err);
                mostrarPlaceholderNomina('Error de red al consultar la nómina.');
            });
    }

    function renderNominaTable(filtro) {
        const tbody = document.getElementById('tablaNominaBody');
        if (!tbody) return;

        actualizarKpis(nominaData);

        let list = nominaData;
        if (filtro && filtro !== '') {
            const fLower = filtro.toLowerCase();
            list = nominaData.filter(function (a) {
                const doc = String(a.documento || '').toLowerCase();
                const nom = String(a.nombre_completo || (a.nombre + ' ' + a.apellido)).toLowerCase();
                return doc.includes(fLower) || nom.includes(fLower);
            });
        }

        if (list.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align:center; color:var(--text-muted); padding:30px;">' +
                '<i class="fas fa-search" style="font-size:1.4rem; display:block; margin-bottom:8px; opacity:0.4;"></i>' +
                (filtro ? 'No se encontraron aprendices con el filtro aplicado.' : 'No hay aprendices activos registrados en esta ficha.') +
                '</td></tr>';
            return;
        }

        let html = '';
        list.forEach(function (a) {
            const estado = a.estado_asistencia || 'Pendiente';
            let badgeClass = 'badge-inactivo';
            let badgeText  = 'Pendiente';

            if (estado === 'Presente') {
                badgeClass = 'badge-activo';
                badgeText  = 'Presente';
            } else if (estado === 'Retardo') {
                badgeClass = 'badge-pendiente';
                badgeText  = 'Retardo (' + (a.minutos_retardo || 0) + ' min)';
            }

            const rfidPill = a.rfid_tag 
                ? '<span class="rfid-tag-pill" title="Llavero RFID asignado"><i class="fas fa-microchip"></i> ' + escapeHtml(a.rfid_tag) + '</span>'
                : '<span style="color:var(--text-muted); font-size:0.78rem;">Sin carnet</span>';

            const horaEntrada = a.hora_entrada ? escapeHtml(a.hora_entrada) : '—';

            let accionHtml = '';
            if (estado === 'Pendiente') {
                accionHtml = '<button type="button" class="btn-marcar-uno" data-aprendiz-id="' + a.id + '" data-documento="' + escapeHtml(String(a.documento)) + '">' +
                    '<i class="fas fa-check"></i> Marcar Presente</button>';
            } else {
                accionHtml = '<span class="badge badge-activo" style="padding:6px 10px;"><i class="fas fa-check-circle"></i> Registrado</span>';
            }

            html += '<tr id="nominaRow_' + a.id + '">' +
                '<td><strong>' + escapeHtml(String(a.documento)) + '</strong></td>' +
                '<td>' + escapeHtml(a.nombre_completo || (a.nombre + ' ' + a.apellido)) + '</td>' +
                '<td>' + rfidPill + '</td>' +
                '<td>' + horaEntrada + '</td>' +
                '<td><span class="badge ' + badgeClass + '">' + badgeText + '</span></td>' +
                '<td style="text-align:center;">' + accionHtml + '</td>' +
                '</tr>';
        });

        tbody.innerHTML = html;
    }

    function mostrarPlaceholderNomina(mensaje) {
        const tbody = document.getElementById('tablaNominaBody');
        if (tbody) {
            tbody.innerHTML = '<tr id="nominaPlaceholderRow"><td colspan="6" style="text-align:center; color:var(--text-muted); padding:36px;">' +
                '<i class="fas fa-toggle-off" style="font-size:1.8rem; display:block; margin-bottom:10px; opacity:0.4;"></i>' +
                '<span>' + escapeHtml(mensaje) + '</span>' +
                '</td></tr>';
        }
    }

    function actualizarKpis(list) {
        const total = list.length;
        let presentes = 0;
        let retardos  = 0;
        let pendientes = 0;

        list.forEach(function (a) {
            const st = a.estado_asistencia || 'Pendiente';
            if (st === 'Presente') presentes++;
            else if (st === 'Retardo') retardos++;
            else pendientes++;
        });

        const kTotal = document.getElementById('kpiTotal');
        const kPres  = document.getElementById('kpiPresentes');
        const kRet   = document.getElementById('kpiRetardos');
        const kPend  = document.getElementById('kpiPendientes');

        if (kTotal) kTotal.textContent = total;
        if (kPres)  kPres.textContent  = presentes;
        if (kRet)   kRet.textContent   = retardos;
        if (kPend)  kPend.textContent  = pendientes;
    }

    // ═══════════════════════════════════════════════════════
    // CAPTURA RFID CON AUTO-FOCUS GUARD
    // ═══════════════════════════════════════════════════════
    function initRfidInput() {
        const input = document.getElementById('rfidInput');
        if (!input) return;

        /**
         * Blindaje contra robo de foco: NO roba el foco si el usuario está
         * escribiendo en manualDocInput, buscadorNomina o interactuando con controles
         */
        function asegurarFoco() {
            const active = document.activeElement;
            if (active && (
                active.id === 'inputPinRespaldo' ||
                active.id === 'manualDocInput' ||
                active.id === 'buscadorNomina' ||
                active.id === 'selectFichaManual' ||
                active.tagName === 'INPUT' ||
                active.tagName === 'TEXTAREA' ||
                active.tagName === 'SELECT' ||
                active.tagName === 'BUTTON' ||
                active.closest('button') ||
                active.closest('.rfid-search-wrapper')
            )) {
                return;
            }

            if (input && document.activeElement !== input) {
                input.focus();
            }
        }

        input.addEventListener('blur', function () {
            setTimeout(asegurarFoco, 250);
        });

        document.addEventListener('click', function (e) {
            if (
                e.target.tagName === 'INPUT' ||
                e.target.tagName === 'SELECT' ||
                e.target.tagName === 'TEXTAREA' ||
                e.target.tagName === 'BUTTON' ||
                e.target.closest('button') ||
                e.target.closest('.rfid-search-wrapper')
            ) {
                return;
            }
            setTimeout(asegurarFoco, 120);
        });

        // El lector USB emula un teclado y envía Enter al final
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

        setTimeout(asegurarFoco, 400);
    }

    function enviarMarcacionRfid(rfidTag) {
        const haySesionActiva = fichasData.some(function (f) {
            return f.sesion_activa !== null;
        });

        if (!haySesionActiva) {
            mostrarFeedback(false, 'Sin sesión activa', 'Debes encender la asistencia de al menos una ficha para registrar RFID.');
            return;
        }

        const body = new FormData();
        body.append('csrf_token', csrfToken);
        body.append('rfid_tag', rfidTag);

        fetch(BASE_URL + '?action=asistencia/registrarMarcacion', {
            method: 'POST',
            body: body,
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                actualizarCsrf(data);

                if (data.ok || data.success) {
                    mostrarFeedback(true, data.aprendiz || 'Asistencia Registrada', data.mensaje || data.message || 'Marcación completada con éxito.');
                    agregarFilaTabla(data);

                    if (Array.isArray(data.nomina_actualizada)) {
                        nominaData = data.nomina_actualizada;
                        const filtro = document.getElementById('buscadorNomina') ? document.getElementById('buscadorNomina').value.trim() : '';
                        renderNominaTable(filtro);
                    }
                } else {
                    mostrarFeedback(false, data.aprendiz || 'Error de Marcación', data.mensaje || data.message || 'No se pudo registrar la asistencia.');
                }
            })
            .catch(function (err) {
                mostrarFeedback(false, 'Error de red', 'No se pudo conectar con el servidor.');
                console.error('RFID fetch error:', err);
            });
    }

    // ═══════════════════════════════════════════════════════
    // MECANISMO 1: MARCACIÓN POR PIN / DOCUMENTO DE RESPALDO
    // ═══════════════════════════════════════════════════════
    window.registrarMarcacionManualPin = function () {
        const inputDoc    = document.getElementById('inputPinRespaldo') || document.getElementById('manualDocInput');
        const selectFicha = document.getElementById('selectFichaManual');
        const btnManual   = document.getElementById('btnMarcarPorDocumento') || document.getElementById('btnRegistrarManual');

        if (!inputDoc || !selectFicha) return;

        const documento = inputDoc.value.trim();
        if (documento === '') {
            mostrarFeedback(false, 'Campo requerido', 'Por favor ingresa el número de documento o PIN del aprendiz.');
            inputDoc.focus();
            return;
        }

        const fichaId = parseInt(selectFicha.value, 10);
        if (!fichaId || isNaN(fichaId)) {
            mostrarFeedback(false, 'Sin ficha seleccionada', 'Selecciona una ficha activa para registrar la asistencia.');
            return;
        }

        const optSeleccionada = selectFicha.options[selectFicha.selectedIndex];
        const sesionId = optSeleccionada ? optSeleccionada.getAttribute('data-sesion-id') : null;

        if (btnManual) {
            btnManual.disabled = true;
            btnManual.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando…';
        }

        const body = new FormData();
        body.append('csrf_token', csrfToken);
        body.append('ficha_id', fichaId);
        if (sesionId) body.append('sesion_id', sesionId);
        body.append('documento', documento);
        body.append('pin', documento);

        fetch(BASE_URL + '?action=asistencia/registrarMarcacionManualPin', {
            method: 'POST',
            body: body,
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                actualizarCsrf(data);

                if (btnManual) {
                    btnManual.disabled = false;
                    btnManual.innerHTML = '<i class="fas fa-id-card-alt"></i> Marcar por Documento';
                }

                if (data.ok || data.success) {
                    inputDoc.value = '';
                    const nombreAprendiz = data.aprendiz_nombre || data.aprendiz || 'Asistencia Registrada';
                    const horaMsg = data.hora ? ' (' + data.hora + ')' : '';
                    mostrarFeedback(true, nombreAprendiz, (data.message || data.mensaje || 'Marcación completada con éxito.') + horaMsg);
                    agregarFilaTabla(data);

                    if (Array.isArray(data.nomina_actualizada)) {
                        nominaData = data.nomina_actualizada;
                        const filtro = document.getElementById('buscadorNomina') ? document.getElementById('buscadorNomina').value.trim() : '';
                        renderNominaTable(filtro);
                    }
                } else {
                    const tituloError = data.aprendiz_nombre || data.aprendiz || 'Error de Registro';
                    mostrarFeedback(false, tituloError, data.message || data.mensaje || 'No fue posible registrar la asistencia.');
                }
            })
            .catch(function (err) {
                if (btnManual) {
                    btnManual.disabled = false;
                    btnManual.innerHTML = '<i class="fas fa-id-card-alt"></i> Marcar por Documento';
                }
                mostrarFeedback(false, 'Error de red', 'No se pudo conectar con el servidor.');
                console.error('Manual PIN fetch error:', err);
            });
    };

    window.registrarManualPorDocumento = window.registrarMarcacionManualPin;

    // ═══════════════════════════════════════════════════════
    // MECANISMO 2: MARCACIÓN POR ID (1 CLIC EN TABLA)
    // ═══════════════════════════════════════════════════════
    function registrarManualPorId(aprendizId, btnClicked) {
        const selectFicha = document.getElementById('selectFichaManual');
        if (!selectFicha || !selectedFichaId) {
            mostrarFeedback(false, 'Sin sesión', 'No hay una ficha en sesión activa.');
            return;
        }

        const optSeleccionada = selectFicha.options[selectFicha.selectedIndex];
        const sesionId = optSeleccionada ? optSeleccionada.getAttribute('data-sesion-id') : null;

        if (btnClicked) {
            btnClicked.disabled = true;
            btnClicked.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registrando…';
        }

        const body = new FormData();
        body.append('csrf_token', csrfToken);
        body.append('ficha_id', selectedFichaId);
        if (sesionId) body.append('sesion_id', sesionId);
        body.append('aprendiz_id', aprendizId);

        fetch(BASE_URL + '?action=asistencia/registrarManual', {
            method: 'POST',
            body: body,
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                actualizarCsrf(data);

                if (data.ok || data.success) {
                    mostrarFeedback(true, data.aprendiz || 'Asistencia Manual', data.mensaje || data.message || 'Asistencia registrada con éxito.');
                    agregarFilaTabla(data);

                    if (Array.isArray(data.nomina_actualizada)) {
                        nominaData = data.nomina_actualizada;
                        const filtro = document.getElementById('buscadorNomina') ? document.getElementById('buscadorNomina').value.trim() : '';
                        renderNominaTable(filtro);
                    }
                } else {
                    if (btnClicked) {
                        btnClicked.disabled = false;
                        btnClicked.innerHTML = '<i class="fas fa-check"></i> Marcar Presente';
                    }
                    mostrarFeedback(false, data.aprendiz || 'Error de Registro', data.mensaje || data.message || 'No fue posible registrar la asistencia.');
                }
            })
            .catch(function (err) {
                if (btnClicked) {
                    btnClicked.disabled = false;
                    btnClicked.innerHTML = '<i class="fas fa-check"></i> Marcar Presente';
                }
                mostrarFeedback(false, 'Error de red', 'No se pudo conectar con el servidor.');
                console.error('1-click attendance error:', err);
            });
    }

    // ═══════════════════════════════════════════════════════
    // FEEDBACK VISUAL FLOTANTE
    // ═══════════════════════════════════════════════════════
    function mostrarFeedback(isSuccess, title, message) {
        const el    = document.getElementById('rfidFeedback');
        const inner = document.getElementById('rfidFeedbackInner');
        const icon  = document.getElementById('rfidFeedbackIcon');
        const name  = document.getElementById('rfidFeedbackName');
        const msg   = document.getElementById('rfidFeedbackMsg');

        if (!el || !inner || !icon || !name || !msg) return;

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
    // TABLA DINÁMICA DE MARCACIONES DE HOY
    // ═══════════════════════════════════════════════════════
    function agregarFilaTabla(data) {
        const tbody = document.getElementById('tablaMarcacionesBody');
        if (!tbody) return;

        const emptyRow = document.getElementById('emptyRow');
        if (emptyRow) emptyRow.remove();

        const tr = document.createElement('tr');
        tr.className = 'rfid-new-row';

        const retardo = data.minutos_retardo || 0;
        const badgeClass = retardo > 0 ? 'badge-pendiente' : 'badge-activo';
        const badgeText = retardo > 0 ? 'Retardo' : 'Asistió';
        const retardoText = retardo > 0 ? retardo + ' min' : '—';

        tr.innerHTML =
            '<td><strong>' + escapeHtml(data.aprendiz || '—') + '</strong></td>' +
            '<td>' + escapeHtml(String(data.documento || '—')) + '</td>' +
            '<td>' + escapeHtml(String(data.ficha || '—')) + '</td>' +
            '<td>' + escapeHtml(data.hora || '—') + '</td>' +
            '<td><span class="badge ' + badgeClass + '">' + badgeText + '</span></td>' +
            '<td>' + retardoText + '</td>';

        if (tbody.firstChild) {
            tbody.insertBefore(tr, tbody.firstChild);
        } else {
            tbody.appendChild(tr);
        }

        const counter = document.getElementById('contadorMarcaciones');
        if (counter) {
            const rows = tbody.querySelectorAll('tr:not(#emptyRow)');
            counter.textContent = rows.length + ' registros';
        }
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(String(text ?? '')));
        return div.innerHTML;
    }

    // ═══════════════════════════════════════════════════════
    // INICIALIZACIÓN
    // ═══════════════════════════════════════════════════════
    document.addEventListener('DOMContentLoaded', function () {
        // Contador de fichas
        const fichasCount = document.getElementById('fichasCount');
        if (fichasCount) {
            fichasCount.textContent = fichasData.length + ' ficha' + (fichasData.length !== 1 ? 's' : '');
        }

        // Reloj
        actualizarReloj();
        setInterval(actualizarReloj, 1000);

        // Inicializar captura RFID con Auto-Focus Guard
        initRfidInput();

        // Selector de ficha para marcación manual
        const selectFicha = document.getElementById('selectFichaManual');
        if (selectFicha) {
            selectFicha.addEventListener('change', function () {
                selectedFichaId = parseInt(selectFicha.value, 10);
                const opt = selectFicha.options[selectFicha.selectedIndex];
                const sesionId = opt ? parseInt(opt.getAttribute('data-sesion-id'), 10) : null;
                cargarNominaSesion(selectedFichaId, sesionId);

                const statusDoc = document.getElementById('manualDocStatus');
                const subtitulo = document.getElementById('subtituloNomina');
                const ficha = fichasData.find(function (f) { return f.id === selectedFichaId; });
                if (ficha) {
                    if (statusDoc) statusDoc.innerHTML = '<small style="color:var(--accent-green);"><i class="fas fa-check-circle"></i> Listo para marcar en Ficha <strong>' + escapeHtml(ficha.codigo) + '</strong>.</small>';
                    if (subtitulo) subtitulo.textContent = 'Mostrando nómina de la Ficha ' + ficha.codigo + ' — ' + ficha.nombre_programa;
                }
            });
        }

        // Buscador interactivo en vivo de la nómina
        const buscador = document.getElementById('buscadorNomina');
        if (buscador) {
            buscador.addEventListener('input', function () {
                renderNominaTable(buscador.value.trim());
            });
        }

        // Delegación de eventos para botones "Marcar Presente" de la nómina
        const tablaNominaBody = document.getElementById('tablaNominaBody');
        if (tablaNominaBody) {
            tablaNominaBody.addEventListener('click', function (e) {
                const btn = e.target.closest('.btn-marcar-uno');
                if (btn && !btn.disabled) {
                    const aprendizId = parseInt(btn.getAttribute('data-aprendiz-id'), 10);
                    if (aprendizId > 0) {
                        registrarManualPorId(aprendizId, btn);
                    }
                }
            });
        }

        // Sincronizar estado inicial de la terminal y nómina
        sincronizarTerminalYNomina();
    });

})();

