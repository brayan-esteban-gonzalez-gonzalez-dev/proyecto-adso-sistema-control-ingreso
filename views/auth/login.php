<?php require_once ROOT_PATH . '/views/layouts/header.php'; ?>

<div class="login-card">
    <div class="login-logo">
        <div class="login-logo-icon">
            <i class="fas fa-fingerprint"></i>
        </div>
        <h1 class="login-title">SCIA</h1>
        <p class="login-subtitle">Sistema de Control de Ingreso de Aprendices</p>
    </div>

    <?php if (!empty($error)): ?>
    <div class="login-error">
        <i class="fas fa-exclamation-triangle"></i>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <form class="login-form" method="POST" action="<?= BASE_URL ?>?action=auth/doLogin">
        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">

        <div class="form-group">
            <label class="form-label" for="documento">
                <i class="fas fa-id-card"></i> Número de Documento
            </label>
            <input type="text" id="documento" name="documento" class="form-control" 
                   placeholder="Ingrese su número de documento" required autofocus
                   value="<?= htmlspecialchars($_POST['documento'] ?? '') ?>">
        </div>

        <div class="form-group">
            <label class="form-label" for="password">
                <i class="fas fa-lock"></i> Contraseña
            </label>
            <div style="position: relative;">
                <input type="password" id="password" name="password" class="form-control" 
                       placeholder="Ingrese su contraseña" required style="padding-right: 40px;">
                <i class="fas fa-eye" id="togglePassword" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); cursor: pointer; color: var(--text-secondary);"></i>
            </div>
        </div>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const togglePassword = document.querySelector('#togglePassword');
                const password = document.querySelector('#password');

                if (togglePassword && password) {
                    togglePassword.addEventListener('click', function () {
                     
                        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
                        password.setAttribute('type', type);
                        
                        
                        this.classList.toggle('fa-eye');
                        this.classList.toggle('fa-eye-slash');
                    });
                }
            });
        </script>

        <button type="submit" class="btn btn-primary login-btn">
            <i class="fas fa-sign-in-alt"></i> Iniciar Sesión
        </button>
    </form>
</div>

<?php require_once ROOT_PATH . '/views/layouts/footer.php'; ?>
