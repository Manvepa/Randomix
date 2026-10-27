<?php

declare(strict_types=1);

// Determina si se muestra el formulario de registro o el de acceso.
// Conserva el formulario de registro cuando se devuelve un error del POST.
$registrationMode = ($_GET['mode'] ?? '') === 'register'
    || (
        ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
        && ($_POST['action'] ?? '') === 'register'
    );

// Define el título de la página según el formulario seleccionado.
$pageTitle = $registrationMode ? 'Crear una cuenta' : 'Iniciar sesión';

// Recupera el mensaje de registro exitoso una sola vez.
$registrationSuccess = $_SESSION['registration_success'] ?? null;
unset($_SESSION['registration_success']);

// Genera el contenido de la vista antes de incluir el layout.
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

            <h1><?= $registrationMode ? 'Crear una cuenta' : 'Bienvenido' ?></h1>

            <p>
                <?= $registrationMode
                    ? 'Completa los datos para crear tu cuenta de Randomix.'
                    : 'Accede a tu cuenta para gestionar los procesos de selección de estudiantes.' ?>
            </p>
        </div>

        <?php if (!$registrationMode && is_string($registrationSuccess)): ?>
            <p class="form-notice" role="status">
                <?= htmlspecialchars($registrationSuccess, ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <form class="login-form" method="POST" action="index.php">
            <input
                type="hidden"
                name="action"
                value="<?= $registrationMode ? 'register' : 'login' ?>"
            >

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
                    minlength="1"
                    maxlength="50"
                    pattern="[a-zA-Z0-9._-]+"
                    title="Usa letras, números, puntos, guiones o guiones bajos."
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
                    autocomplete="<?= $registrationMode ? 'new-password' : 'current-password' ?>"
                    <?php if ($registrationMode): ?>
                        minlength="12"
                        maxlength="4096"
                    <?php endif; ?>
                    required
                >
            </div>

            <?php if ($registrationMode): ?>
                <div class="form-group">
                    <label for="password_confirmation">Confirmar contraseña</label>

                    <input
                        type="password"
                        id="password_confirmation"
                        name="password_confirmation"
                        placeholder="Repite tu contraseña"
                        autocomplete="new-password"
                        minlength="12"
                        maxlength="4096"
                        required
                    >
                </div>
            <?php endif; ?>

            <!-- Muestra errores sin insertar texto sin escapar en HTML. -->
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
                <?= $registrationMode ? 'Crear cuenta' : 'Iniciar sesión' ?>
                <span aria-hidden="true">&rarr;</span>
            </button>
        </form>

        <div class="login-heading">
            <?php if ($registrationMode): ?>
                <p>¿Ya tienes una cuenta? <a href="index.php">Inicia sesión</a>.</p>
            <?php else: ?>
                <p>¿No tienes una cuenta? <a href="index.php?mode=register">Crear una cuenta</a>.</p>
            <?php endif; ?>
        </div>
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
