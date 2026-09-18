/**
 * app.js — JavaScript principal del SCIA
 * 
 * Sidebar toggle, búsqueda en tablas, confirmación de eliminar,
 * auto-ocultar alertas flash.
 */
document.addEventListener('DOMContentLoaded', function () {

    // ═══════════════════════════════════════════════════════
    // SIDEBAR TOGGLE (Desktop: colapsar / Mobile: abrir/cerrar)
    // ═══════════════════════════════════════════════════════
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mobileToggle = document.getElementById('mobileToggle');

    // Overlay para cerrar sidebar en mobile
    let overlay = document.querySelector('.sidebar-overlay');
    if (!overlay && sidebar) {
        overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);
    }

    // Desktop: colapsar/expandir
    if (sidebarToggle && sidebar) {
        // Restaurar estado guardado
        if (localStorage.getItem('sidebar-collapsed') === 'true') {
            sidebar.classList.add('collapsed');
        }

        sidebarToggle.addEventListener('click', function () {
            sidebar.classList.toggle('collapsed');
            localStorage.setItem('sidebar-collapsed', sidebar.classList.contains('collapsed'));
        });
    }

    // Mobile: abrir sidebar
    if (mobileToggle && sidebar) {
        mobileToggle.addEventListener('click', function () {
            sidebar.classList.toggle('mobile-open');
            if (overlay) overlay.classList.toggle('visible');
        });
    }

    // Cerrar sidebar al tocar overlay
    if (overlay) {
        overlay.addEventListener('click', function () {
            if (sidebar) sidebar.classList.remove('mobile-open');
            overlay.classList.remove('visible');
        });
    }


    // ═══════════════════════════════════════════════════════
    // BÚSQUEDA EN TABLAS
    // ═══════════════════════════════════════════════════════
    document.querySelectorAll('[data-search-table]').forEach(function (input) {
        const tableId = input.getAttribute('data-search-table');
        const table = document.getElementById(tableId);

        if (!table) return;

        input.addEventListener('input', function () {
            const query = this.value.toLowerCase().trim();
            const rows = table.querySelectorAll('tbody tr');

            rows.forEach(function (row) {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });
    });


    // ═══════════════════════════════════════════════════════
    // CONFIRMACIÓN DE ELIMINACIÓN
    // ═══════════════════════════════════════════════════════
    document.querySelectorAll('[data-confirm]').forEach(function (element) {
        element.addEventListener('click', function (e) {
            const message = this.getAttribute('data-confirm');
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });


    // ═══════════════════════════════════════════════════════
    // AUTO-OCULTAR ALERTAS FLASH
    // ═══════════════════════════════════════════════════════
    const flashAlert = document.getElementById('flashAlert');
    if (flashAlert) {
        setTimeout(function () {
            flashAlert.style.transition = 'opacity 0.5s, transform 0.5s';
            flashAlert.style.opacity = '0';
            flashAlert.style.transform = 'translateY(-12px)';
            setTimeout(function () {
                flashAlert.remove();
            }, 500);
        }, 5000);
    }

});
