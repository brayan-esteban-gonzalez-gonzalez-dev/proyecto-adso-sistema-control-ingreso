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
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= file_exists(ROOT_PATH . '/assets/css/style.css') ? filemtime(ROOT_PATH . '/assets/css/style.css') : '1.0' ?>">
    <script>
        // Función inmediata anti-FOUC
        (function() {
            var savedTheme = localStorage.getItem('scia_theme');
            var systemDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            var theme = savedTheme ? savedTheme : (systemDark ? 'dark' : 'dark');
            if (savedTheme) {
                theme = savedTheme;
            } else if (window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches) {
                theme = 'light';
            }
            document.documentElement.setAttribute('data-bs-theme', theme);
            document.documentElement.setAttribute('data-theme', theme);
        })();

        // Actualizar icono, texto y tooltip del botón según el tema activo
        window.actualizarBotonTemaGlobal = function(tema) {
            var btn = document.getElementById('darkModeToggle');
            var icon = document.getElementById('themeIcon');
            var text = document.getElementById('themeText');
            if (!icon && !text) return;

            if (tema === 'dark') {
                if (icon) icon.className = 'fas fa-sun bi bi-sun-fill';
                if (text) text.textContent = 'Modo Claro';
                if (btn) btn.setAttribute('title', 'Cambiar a Modo Claro');
            } else {
                if (icon) icon.className = 'fas fa-moon bi bi-moon-fill';
                if (text) text.textContent = 'Modo Oscuro';
                if (btn) btn.setAttribute('title', 'Cambiar a Modo Oscuro');
            }
        };

        // Alternar tema de forma inmediata, persistente y robusta
        window.toggleDarkMode = function() {
            var html = document.documentElement;
            var body = document.body;
            var temaActual = html.getAttribute('data-bs-theme') || localStorage.getItem('scia_theme') || 'dark';
            var nuevoTema = (temaActual === 'dark') ? 'light' : 'dark';

            html.setAttribute('data-bs-theme', nuevoTema);
            html.setAttribute('data-theme', nuevoTema);
            if (body) {
                body.setAttribute('data-bs-theme', nuevoTema);
                body.setAttribute('data-theme', nuevoTema);
            }
            localStorage.setItem('scia_theme', nuevoTema);
            window.actualizarBotonTemaGlobal(nuevoTema);

            // Refrescar gráficos Chart.js si existen instancias
            if (typeof Chart !== 'undefined' && Chart.instances) {
                Object.values(Chart.instances).forEach(function(chart) {
                    try {
                        var textColor = (nuevoTema === 'dark') ? '#94a3b8' : '#475569';
                        var gridColor = (nuevoTema === 'dark') ? 'rgba(255, 255, 255, 0.08)' : 'rgba(0, 0, 0, 0.08)';
                        if (chart.options.scales) {
                            Object.values(chart.options.scales).forEach(function(scale) {
                                if (scale.ticks) scale.ticks.color = textColor;
                                if (scale.grid) scale.grid.color = gridColor;
                            });
                        }
                        if (chart.options.plugins && chart.options.plugins.legend && chart.options.plugins.legend.labels) {
                            chart.options.plugins.legend.labels.color = textColor;
                        }
                        chart.update();
                    } catch(e) {}
                });
            }
        };

        // Sincronizar estado del botón tan pronto el DOM esté listo
        document.addEventListener('DOMContentLoaded', function() {
            var currentTheme = document.documentElement.getAttribute('data-bs-theme') || 'dark';
            window.actualizarBotonTemaGlobal(currentTheme);
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js" defer></script>
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
                <a href="<?= BASE_URL ?>?action=usuarios" class="nav-link <?= str_starts_with($currentAction, 'usuarios') ? 'active' : '' ?>" title="<?= Auth::isInstructor() ? 'Directorio de Fichas y Aprendices' : 'Gestión de Usuarios' ?>">
                    <i class="fas <?= Auth::isInstructor() ? 'fa-id-card-clip' : 'fa-users-cog' ?>"></i>
                    <span><?= Auth::isInstructor() ? 'Fichas / Usuarios' : 'Usuarios' ?></span>
                </a>
                <a href="<?= BASE_URL ?>?action=fichas" class="nav-link <?= str_starts_with($currentAction, 'fichas') ? 'active' : '' ?>">
                    <i class="fas fa-folder-open"></i>
                    <span>Fichas</span>
                </a>
                <a href="<?= BASE_URL ?>?action=programas" class="nav-link <?= str_starts_with($currentAction, 'programas') ? 'active' : '' ?>">
                    <i class="fas fa-graduation-cap"></i>
                    <span>Programas</span>
                </a>
                <a href="<?= BASE_URL ?>?action=competencias" class="nav-link <?= str_starts_with($currentAction, 'competencias') ? 'active' : '' ?>">
                    <i class="fas fa-book-open"></i>
                    <span>Competencias</span>
                </a>
                <a href="<?= BASE_URL ?>?action=jornadas" class="nav-link <?= str_starts_with($currentAction, 'jornadas') ? 'active' : '' ?>">
                    <i class="fas fa-clock"></i>
                    <span>Jornadas</span>
                </a>
                <a href="<?= BASE_URL ?>?action=horarios" class="nav-link <?= (str_starts_with($currentAction, 'horarios') && $currentAction !== 'horarios/miHorario') ? 'active' : '' ?>">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Horarios</span>
                </a>
                <?php if (Auth::isInstructor()): ?>
                <a href="<?= BASE_URL ?>?action=horarios/miHorario" class="nav-link <?= $currentAction === 'horarios/miHorario' ? 'active' : '' ?>">
                    <i class="fas fa-calendar-week"></i>
                    <span>Mi Horario</span>
                </a>
                <?php endif; ?>
            </div>

            <div class="nav-section">
                <span class="nav-section-title">Asistencia</span>
                <?php if (Auth::isInstructor()): ?>
                <a href="<?= BASE_URL ?>?action=asistencia/rfid" class="nav-link <?= $currentAction === 'asistencia/rfid' ? 'active' : '' ?>">
                    <i class="fas fa-wifi"></i>
                    <span>Marcación RFID</span>
                </a>
                <?php endif; ?>
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
                <a href="<?= BASE_URL ?>?action=horarios/miHorario" class="nav-link <?= str_starts_with($currentAction, 'horarios') ? 'active' : '' ?>">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Mi Horario</span>
                </a>
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
                <button class="btn btn-outline-secondary btn-sm rounded-pill px-3" id="darkModeToggle" title="Cambiar modo de vista" type="button" onclick="toggleDarkMode()">
                    <i id="themeIcon" class="fas fa-sun bi bi-sun-fill"></i> <span class="d-none d-md-inline" id="themeText">Modo Claro</span>
                </button>
               <span class="header-date">
                <i class="fas fa-calendar-day"></i>
                    <?php
                        $dias = ['Sunday'=>'Domingo','Monday'=>'Lunes','Tuesday'=>'Martes','Wednesday'=>'Miércoles','Thursday'=>'Jueves','Friday'=>'Viernes','Saturday'=>'Sábado'];
                        $meses = [1=>'Enero',2=>'Febrero',3=>'Marzo',4=>'Abril',5=>'Mayo',6=>'Junio',7=>'Julio',8=>'Agosto',9=>'Septiembre',10=>'Octubre',11=>'Noviembre',12=>'Diciembre'];
                        echo ($dias[date('l')] ?? date('l')) . ', ' . date('d') . ' de ' . ($meses[(int)date('n')] ?? date('F')) . ' de ' . date('Y');?>
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
