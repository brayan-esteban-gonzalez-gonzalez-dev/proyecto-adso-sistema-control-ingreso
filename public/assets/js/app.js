/**
 * app.js — Lógica general de la aplicación
 * 
 * Sidebar toggle, alertas, confirmaciones, búsqueda en tablas,
 * y animaciones generales.
 */
document.addEventListener('DOMContentLoaded', () => {

    // ─── Sidebar Toggle ───
    const sidebar      = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const mobileToggle = document.getElementById('mobileToggle');

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            localStorage.setItem('sidebar-collapsed', sidebar.classList.contains('collapsed'));
        });

        // Restaurar estado del sidebar
        if (localStorage.getItem('sidebar-collapsed') === 'true') {
            sidebar.classList.add('collapsed');
        }
    }

    if (mobileToggle) {
        mobileToggle.addEventListener('click', () => {
            sidebar.classList.toggle('mobile-open');
        });

        // Cerrar sidebar al hacer clic fuera en móvil
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768 && 
                sidebar.classList.contains('mobile-open') &&
                !sidebar.contains(e.target) && 
                !mobileToggle.contains(e.target)) {
                sidebar.classList.remove('mobile-open');
            }
        });
    }

    // ─── Auto-dismiss de alertas flash ───
    const flashAlert = document.getElementById('flashAlert');
    if (flashAlert) {
        setTimeout(() => {
            flashAlert.style.opacity = '0';
            flashAlert.style.transform = 'translateY(-10px)';
            flashAlert.style.transition = 'all 0.3s ease';
            setTimeout(() => flashAlert.remove(), 300);
        }, 5000);
    }

    // ─── Confirmación de eliminación ───
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', (e) => {
            e.preventDefault();
            const message = el.getAttribute('data-confirm') || '¿Está seguro de realizar esta acción?';
            const href = el.getAttribute('href');

            showModal('Confirmar Acción', message, () => {
                window.location.href = href;
            });
        });
    });

    // ─── Búsqueda en tablas ───
    const searchInputs = document.querySelectorAll('[data-search-table]');
    searchInputs.forEach(input => {
        const tableId = input.getAttribute('data-search-table');
        const table = document.getElementById(tableId);

        if (table) {
            input.addEventListener('input', () => {
                const query = input.value.toLowerCase();
                const rows = table.querySelectorAll('tbody tr');

                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(query) ? '' : 'none';
                });
            });
        }
    });

    // ─── Animación de entrada para cards ───
    const cards = document.querySelectorAll('.stat-card, .card');
    cards.forEach((card, i) => {
        card.style.animationDelay = `${i * 0.05}s`;
        card.classList.add('fade-in-card');
    });
});

// ─── Modal de confirmación ───
function showModal(title, message, onConfirm) {
    // Remover modal existente
    const existing = document.querySelector('.modal-overlay');
    if (existing) existing.remove();

    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay show';
    overlay.innerHTML = `
        <div class="modal-box">
            <h3 class="modal-title">${title}</h3>
            <p class="modal-message">${message}</p>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeModal()">Cancelar</button>
                <button class="btn btn-danger" id="modalConfirm">Confirmar</button>
            </div>
        </div>
    `;

    document.body.appendChild(overlay);

    document.getElementById('modalConfirm').addEventListener('click', () => {
        closeModal();
        onConfirm();
    });

    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) closeModal();
    });
}

function closeModal() {
    const overlay = document.querySelector('.modal-overlay');
    if (overlay) {
        overlay.style.opacity = '0';
        setTimeout(() => overlay.remove(), 200);
    }
}

// ─── Toast Notification ───
function showToast(message, type = 'success') {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type}`;
    toast.style.cssText = 'position:fixed;top:20px;right:20px;z-index:3000;min-width:300px;max-width:450px;';
    toast.innerHTML = `
        <div class="alert-content">
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            <span>${message}</span>
        </div>
        <button class="alert-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    document.body.appendChild(toast);

    setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(-10px)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}
