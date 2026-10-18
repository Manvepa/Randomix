<?php

declare(strict_types=1);

// Define el título de la página de acceso.
$pageTitle = 'Acceso administrativo';

// Genera el contenido del formulario antes de incluir el layout.
ob_start();
?>

<section class="login-page">
    <div class="login-brand">
        <img class="brand-mark" src="assets/images/randomix-logo.png" alt="" aria-hidden="true">

        <div>
            <p class="brand-name">Randomix</p>
            <p class="brand-caption">Sistema de selección aleatoria</p>
        </div>
    </div>

    <div class="login-card">
        <div class="login-heading">
            <span class="eyebrow">PLATAFORMA INSTITUCIONAL</span>

            <h1>Bienvenido</h1>

            <p>
                Ingresa con tu cuenta de administrador para
                gestionar los procesos de selección de estudiantes.
            </p>
        </div>

        <form class="login-form" method="POST" action="index.php">
            <input type="hidden" name="action" value="login">

            <!-- Token CSRF para proteger el envío del formulario. -->
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $_SESSION['csrf_token'] ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >

            <div class="form-group">
                <label for="username">Usuario</label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    placeholder="Ingresa tu usuario"
                    autocomplete="username"
                    maxlength="50"
                    required
                    autofocus
                >
            </div>

            <div class="form-group">
                <label for="password">Contraseña</label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Ingresa tu contraseña"
                    autocomplete="current-password"
                    required
                >
            </div>

            <!-- Muestra los errores sin insertar texto sin escapar en HTML. -->
            <?php if (isset($errorMessage) && $errorMessage !== null): ?>
                <p class="form-notice" role="alert">
                    <?= htmlspecialchars(
                        $errorMessage,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </p>
            <?php endif; ?>

            <button class="button button-primary" type="submit">
                Iniciar sesión
                <span aria-hidden="true">&rarr;</span>
            </button>
        </form>
    </div>

    <footer class="login-footer">
        <span>Randomix</span>
        <span>Herramienta de gestión institucional</span>
    </footer>
</section>

<?php

// Captura el contenido de esta vista para entregarlo al layout.
$content = (string) ob_get_clean();

// Renderiza la estructura HTML compartida de la aplicación.
require dirname(__DIR__) . '/layout/app.php';
