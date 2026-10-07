<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<div class="dashboard-grid">
    <!-- Tarjeta: Usuarios -->
    <div class="stat-card">
        <div class="stat-card-icon" style="background: linear-gradient(135deg, #6366f1, #8b5cf6);">
            <i class="fas fa-users"></i>
        </div>
        <div class="stat-card-info">
            <span class="stat-card-value"><?= $stats['total_usuarios'] ?></span>
            <span class="stat-card-label">Usuarios</span>
        </div>
        <?php if (Auth::isAdminOrInstructor()): ?>
        <a href="<?= BASE_URL ?>?action=usuarios" class="stat-card-link">
            Ver todos <i class="fas fa-arrow-right"></i>
        </a>
        <?php endif; ?>
    </div>

    <!-- Tarjeta: Instructores -->
    <div class="stat-card">
        <div class="stat-card-icon" style="background: linear-gradient(135deg, #06b6d4, #22d3ee);">
            <i class="fas fa-chalkboard-teacher"></i>
        </div>
        <div class="stat-card-info">
            <span class="stat-card-value"><?= $stats['total_instructores'] ?></span>
            <span class="stat-card-label">Instructores</span>
        </div>
    </div>

    <!-- Tarjeta: Fichas -->
    <div class="stat-card">
        <div class="stat-card-icon" style="background: linear-gradient(135deg, #10b981, #34d399);">
            <i class="fas fa-folder-open"></i>
        </div>
        <div class="stat-card-info">
            <span class="stat-card-value"><?= $stats['total_fichas'] ?></span>
            <span class="stat-card-label">Fichas</span>
        </div>
        <?php if (Auth::isAdminOrInstructor()): ?>
        <a href="<?= BASE_URL ?>?action=fichas" class="stat-card-link">
            Ver todas <i class="fas fa-arrow-right"></i>
        </a>
        <?php endif; ?>
    </div>

    <!-- Tarjeta: Aprendices -->
    <div class="stat-card">
        <div class="stat-card-icon" style="background: linear-gradient(135deg, #f59e0b, #fbbf24);">
            <i class="fas fa-user-graduate"></i>
        </div>
        <div class="stat-card-info">
            <span class="stat-card-value"><?= $stats['total_aprendices'] ?></span>
            <span class="stat-card-label">Aprendices Activos</span>
        </div>
        <?php if (Auth::isAdminOrInstructor()): ?>
        <a href="<?= BASE_URL ?>?action=aprendices" class="stat-card-link">
            Ver todos <i class="fas fa-arrow-right"></i>
        </a>
        <?php endif; ?>
    </div>

    <!-- Tarjeta: Excusas Pendientes -->
    <?php if (Auth::isAdminOrInstructor()): ?>
    <div class="stat-card">
        <div class="stat-card-icon" style="background: linear-gradient(135deg, #ef4444, #f87171);">
            <i class="fas fa-file-medical"></i>
        </div>
        <div class="stat-card-info">
            <span class="stat-card-value"><?= $stats['excusas_pendientes'] ?></span>
            <span class="stat-card-label">Excusas Pendientes</span>
        </div>
        <a href="<?= BASE_URL ?>?action=excusas&filtro=pendientes" class="stat-card-link">
            Revisar <i class="fas fa-arrow-right"></i>
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- Accesos rápidos -->
<?php if (Auth::isAdminOrInstructor()): ?>
<div class="card" style="margin-top: 24px;">
    <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 16px; color: var(--text-primary);">
        <i class="fas fa-bolt" style="color: var(--accent-yellow);"></i> Accesos Rápidos
    </h3>
    <div class="quick-actions">
        <a href="<?= BASE_URL ?>?action=usuarios/crear" class="quick-action-btn">
            <i class="fas fa-user-plus"></i>
            <span>Nuevo Usuario</span>
        </a>
        <a href="<?= BASE_URL ?>?action=fichas/crear" class="quick-action-btn">
            <i class="fas fa-folder-plus"></i>
            <span>Nueva Ficha</span>
        </a>
        <a href="<?= BASE_URL ?>?action=aprendices/crear" class="quick-action-btn">
            <i class="fas fa-user-graduate"></i>
            <span>Nuevo Aprendiz</span>
        </a>
        <a href="<?= BASE_URL ?>?action=excusas&filtro=pendientes" class="quick-action-btn">
            <i class="fas fa-file-medical"></i>
            <span>Excusas Pendientes</span>
        </a>
    </div>
</div>
<?php else: ?>
<div class="card" style="margin-top: 24px;">
    <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 16px; color: var(--text-primary);">
        <i class="fas fa-bolt" style="color: var(--accent-yellow);"></i> Mis Accesos
    </h3>
    <div class="quick-actions">
        <a href="<?= BASE_URL ?>?action=excusas" class="quick-action-btn">
            <i class="fas fa-file-medical"></i>
            <span>Mis Excusas</span>
        </a>
        <a href="<?= BASE_URL ?>?action=excusas/crear" class="quick-action-btn">
            <i class="fas fa-plus-circle"></i>
            <span>Nueva Excusa</span>
        </a>
    </div>
</div>
<?php endif; ?>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
