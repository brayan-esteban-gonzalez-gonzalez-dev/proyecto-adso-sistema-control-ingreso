<?php
/**
 * header.php — Layout principal (cabecera)
 * 
 * Incluye meta tags, CSS, navbar lateral y header superior.
 */
$currentAction = $_GET['action'] ?? 'dashboard';
$flash = Auth::getFlash();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistema de Control de Ingreso de Aprendices mediante RFID">
    <title><?= htmlspecialchars($pageTitle ?? 'Sistema RFID') ?> — SCIA</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css">
</head>
<body>
<?php if (Auth::isLoggedIn()): ?>
    <!-- ─── Sidebar ─── -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <i class="fas fa-fingerprint"></i>
                <span>SCIA</span>
            </div>
            <button class="sidebar-toggle" id="sidebarToggle" title="Colapsar menú">
                <i class="fas fa-angles-left"></i>
            </button>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section">
                <span class="nav-section-title">Principal</span>
                <a href="<?= BASE_URL ?>?action=dashboard" class="nav-link <?= str_starts_with($currentAction, 'dashboard') ? 'active' : '' ?>">
                    <i class="fas fa-chart-pie"></i>
                    <span>Dashboard</span>
                </a>
            </div>

            <?php if (Auth::isAdminOrInstructor()): ?>
            <div class="nav-section">
                <span class="nav-section-title">Gestión</span>
                <a href="<?= BASE_URL ?>?action=usuarios" class="nav-link <?= str_starts_with($currentAction, 'usuarios') ? 'active' : '' ?>">
                    <i class="fas fa-users-cog"></i>
                    <span>Usuarios</span>
                </a>
                <a href="<?= BASE_URL ?>?action=fichas" class="nav-link <?= str_starts_with($currentAction, 'fichas') ? 'active' : '' ?>">
                    <i class="fas fa-folder-open"></i>
                    <span>Fichas</span>
                </a>
                <a href="<?= BASE_URL ?>?action=horarios" class="nav-link <?= str_starts_with($currentAction, 'horarios') ? 'active' : '' ?>">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Horarios</span>
                </a>
                <a href="<?= BASE_URL ?>?action=aprendices" class="nav-link <?= str_starts_with($currentAction, 'aprendices') ? 'active' : '' ?>">
                    <i class="fas fa-user-graduate"></i>
                    <span>Aprendices</span>
                </a>
            </div>

            <div class="nav-section">
                <span class="nav-section-title">Asistencia</span>
                <a href="<?= BASE_URL ?>?action=asistencia/rfid" class="nav-link <?= $currentAction === 'asistencia/rfid' ? 'active' : '' ?>">
                    <i class="fas fa-wifi"></i>
                    <span>Marcación RFID</span>
                </a>
                <a href="<?= BASE_URL ?>?action=asistencia/historial" class="nav-link <?= $currentAction === 'asistencia/historial' ? 'active' : '' ?>">
                    <i class="fas fa-history"></i>
                    <span>Historial</span>
                </a>
                <a href="<?= BASE_URL ?>?action=reportes" class="nav-link <?= str_starts_with($currentAction, 'reportes') ? 'active' : '' ?>">
                    <i class="fas fa-file-alt"></i>
                    <span>Reportes</span>
                </a>
            </div>
            <?php else: ?>
            <div class="nav-section">
                <span class="nav-section-title">Mi Información</span>
                <a href="<?= BASE_URL ?>?action=asistencia/historial" class="nav-link <?= $currentAction === 'asistencia/historial' ? 'active' : '' ?>">
                    <i class="fas fa-history"></i>
                    <span>Mi Historial</span>
                </a>
            </div>
            <?php endif; ?>

            <div class="nav-section">
                <span class="nav-section-title">Excusas</span>
                <a href="<?= BASE_URL ?>?action=excusas" class="nav-link <?= str_starts_with($currentAction, 'excusas') ? 'active' : '' ?>">
                    <i class="fas fa-file-medical"></i>
                    <span>Excusas Médicas</span>
                </a>
            </div>
        </nav>

        <div class="sidebar-footer">
            <div class="user-info">
                <div class="user-avatar">
                    <?= strtoupper(substr($_SESSION['user_nombre'] ?? 'U', 0, 1)) ?>
                </div>
                <div class="user-details">
                    <span class="user-name"><?= htmlspecialchars(Auth::getUserFullName()) ?></span>
                    <span class="user-role">
                        <?php
                        $rolNames = [1 => 'Administrador', 2 => 'Instructor', 3 => 'Aprendiz'];
                        echo $rolNames[Auth::getUserRol()] ?? 'Usuario';
                        ?>
                    </span>
                </div>
            </div>
            <a href="<?= BASE_URL ?>?action=auth/logout" class="nav-link logout-link" title="Cerrar sesión">
                <i class="fas fa-sign-out-alt"></i>
                <span>Cerrar Sesión</span>
            </a>
        </div>
    </aside>

    <!-- ─── Contenido principal ─── -->
    <main class="main-content" id="mainContent">
        <header class="top-header">
            <div class="header-left">
                <button class="mobile-toggle" id="mobileToggle">
                    <i class="fas fa-bars"></i>
                </button>
                <h1 class="page-title"><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></h1>
            </div>
            <div class="header-right">
                <span class="header-date">
                    <i class="fas fa-calendar-day"></i>
                    <?= strftime('%A, %d de %B de %Y') ?: date('l, d F Y') ?>
                </span>
            </div>
        </header>

        <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] ?>" id="flashAlert">
            <div class="alert-content">
                <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'error' ? 'exclamation-circle' : 'info-circle') ?>"></i>
                <span><?= $flash['message'] ?></span>
            </div>
            <button class="alert-close" onclick="this.parentElement.remove()">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <?php endif; ?>

        <div class="content-wrapper">
<?php else: ?>
    <div class="auth-layout">
<?php endif; ?>
