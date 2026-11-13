document.addEventListener('DOMContentLoaded', function () {
    const board = document.getElementById('calBoard');
    const dataNode = document.getElementById('miHorarioData');
    if (!board || !dataNode) return;

    let eventos = [];
    try {
        eventos = JSON.parse(dataNode.textContent || '[]');
    } catch (e) {
        eventos = [];
    }
    const mapa = {};
    eventos.forEach(function (s) { mapa[s.id] = s; });

    const titulo = document.getElementById('calTitulo');
    const prev = document.getElementById('calPrev');
    const next = document.getElementById('calNext');
    const hoy = document.getElementById('calHoy');
    const vistaInput = document.getElementById('calVistaInput');
    const buscar = document.getElementById('calBuscar');
    const resultados = document.getElementById('calResultados');
    const vacio = document.getElementById('calSinResultados');
    const cuerpo = document.getElementById('calDetalleCuerpo');
    const tituloDetalle = document.getElementById('calDetalleTitulo');
    const cerrar = document.getElementById('calDetalleCerrar');
    const detalleInicial = cuerpo ? cuerpo.innerHTML : '';

    function vistaActual() {
        return board.dataset.vista === 'mes' ? 'mes' : 'semana';
    }

    function aplicarVista(vista, pushUrl) {
        board.dataset.vista = vista;
        document.querySelectorAll('.cal-switch-btn').forEach(function (btn) {
            btn.classList.toggle('is-active', btn.dataset.vista === vista);
        });
        if (titulo) titulo.textContent = titulo.dataset[vista] || titulo.textContent;
        [prev, next, hoy].forEach(function (link) {
            if (link && link.dataset[vista]) link.href = link.dataset[vista];
        });
        if (vistaInput) vistaInput.value = vista;
        if (pushUrl) {
            const activo = document.querySelector('.cal-switch-btn[data-vista="' + vista + '"]');
            if (activo) history.replaceState({}, '', activo.href);
        }
        aplicarBusqueda();
    }

    document.querySelectorAll('.cal-switch-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            aplicarVista(this.dataset.vista, true);
        });
    });

    function fila(etiqueta, valor) {
        if (!valor) return null;
        const row = document.createElement('div');
        row.className = 'cal-detail-row';
        const label = document.createElement('span');
        label.textContent = etiqueta;
        const strong = document.createElement('strong');
        strong.textContent = valor;
        row.append(label, strong);
        return row;
    }

    function limpiarSeleccion() {
        document.querySelectorAll('.is-selected').forEach(function (el) {
            el.classList.remove('is-selected');
        });
    }

    function mostrarSesion(id) {
        const s = mapa[id];
        if (!s || !cuerpo) return;
        limpiarSeleccion();
        document.querySelectorAll('[data-id="' + id + '"]').forEach(function (el) {
            el.classList.add('is-selected');
        });
        if (tituloDetalle) tituloDetalle.textContent = 'Ficha ' + s.codigo_ficha;
        if (cerrar) cerrar.hidden = false;
        cuerpo.replaceChildren();
        const barra = document.createElement('div');
        barra.className = 'cal-detail-accent';
        barra.style.background = s.color || '#10b981';
        cuerpo.append(barra);
        [
            ['Día', (s.dia_nombre ? s.dia_nombre + ', ' : '') + (s.fecha_fmt || '')],
            ['Horario', (s.hora_ini_fmt || '') + ' - ' + (s.hora_fin_fmt || '')],
            ['Duración', s.horas_fmt],
            ['Programa', s.nombre_programa],
            ['Jornada', s.nombre_jornada],
            ['Competencia', s.nombre_competencia],
            ['Tipo', s.tipo_instructor],
            ['Especialidad', s.especialidad],
            ['Nivel', s.nivel],
            ['Grupo', s.grupo_convergente],
            ['Estado', s.estado]
        ].forEach(function (par) {
            const row = fila(par[0], par[1]);
            if (row) cuerpo.append(row);
        });
    }

    function mostrarDia(fecha, etiqueta) {
        if (!cuerpo) return;
        const items = eventos.filter(function (s) { return s.fecha === fecha; });
        limpiarSeleccion();
        if (tituloDetalle) tituloDetalle.textContent = etiqueta || 'Día';
        if (cerrar) cerrar.hidden = false;
        cuerpo.replaceChildren();
        if (!items.length) {
            const p = document.createElement('p');
            p.className = 'text-muted';
            p.textContent = 'No tienes sesiones este día.';
            cuerpo.append(p);
            return;
        }
        items.forEach(function (s) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'cal-day-item';
            btn.addEventListener('click', function () { mostrarSesion(s.id); });
            const time = document.createElement('strong');
            time.textContent = s.hora_ini_fmt + ' - ' + s.hora_fin_fmt;
            const meta = document.createElement('span');
            meta.textContent = 'Ficha ' + s.codigo_ficha + (s.especialidad ? ' · ' + s.especialidad : '');
            btn.append(time, meta);
            cuerpo.append(btn);
        });
    }

    document.querySelectorAll('.cal-event, .cal-chip, .agenda-item').forEach(function (el) {
        el.addEventListener('click', function () {
            mostrarSesion(this.dataset.id);
        });
    });

    document.querySelectorAll('.cal-day-head, .cal-day-num, .cal-more').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            mostrarDia(this.dataset.fecha, this.dataset.label);
        });
    });

    if (cerrar && cuerpo) {
        cerrar.addEventListener('click', function () {
            limpiarSeleccion();
            cerrar.hidden = true;
            if (tituloDetalle) tituloDetalle.textContent = 'Detalle';
            cuerpo.innerHTML = detalleInicial;
        });
    }

    function aplicarBusqueda() {
        const q = buscar ? buscar.value.trim().toLowerCase() : '';
        document.querySelectorAll('[data-search]').forEach(function (el) {
            const texto = (el.dataset.search || '').toLowerCase();
            el.classList.toggle('is-hidden', q !== '' && !texto.includes(q));
        });
        document.querySelectorAll('.agenda-dia').forEach(function (dia) {
            const visible = dia.querySelector('.agenda-item:not(.is-hidden)');
            dia.classList.toggle('is-hidden', !visible);
        });
        const vista = vistaActual();
        const selector = vista === 'mes'
            ? '.agenda-dia.solo-mes:not(.is-hidden) .agenda-item:not(.is-hidden)'
            : '.agenda-dia.solo-semana:not(.is-hidden) .agenda-item:not(.is-hidden)';
        const n = document.querySelectorAll(selector).length;
        if (resultados) {
            resultados.textContent = ' · ' + n + (n === 1 ? ' sesión en esta vista' : ' sesiones en esta vista');
        }
        if (vacio) vacio.hidden = !(q && n === 0);
    }

    if (buscar) buscar.addEventListener('input', aplicarBusqueda);

    document.addEventListener('keydown', function (e) {
        if (e.target.closest('input, select, textarea')) return;
        if (e.key === 'ArrowLeft' && prev) prev.click();
        if (e.key === 'ArrowRight' && next) next.click();
        if (e.key === 'Escape' && cerrar && !cerrar.hidden) cerrar.click();
    });

    const colHoy = board.querySelector('.cal-day-col[data-fecha="' + board.dataset.hoy + '"] .cal-day-grid');
    if (colHoy) {
        const desde = parseInt(board.dataset.horaDesde, 10) || 0;
        const px = parseInt(board.dataset.horaPx, 10) || 52;
        const ahora = new Date();
        const minutos = ahora.getHours() * 60 + ahora.getMinutes();
        const top = ((minutos - desde * 60) / 60) * px;
        if (top >= 0 && top <= colHoy.clientHeight) {
            const linea = document.createElement('div');
            linea.className = 'cal-now';
            linea.style.top = top + 'px';
            colHoy.append(linea);
            if (vistaActual() === 'semana') {
                const scroll = board.querySelector('.cal-week-scroll');
                if (scroll) scroll.scrollTop = Math.max(0, top - 120);
            }
        }
    }

    aplicarVista(vistaActual(), false);
});
