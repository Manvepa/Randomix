<?php

declare(strict_types=1);

// Obtiene el nombre del administrador desde la sesión autenticada.
$adminUsername = $_SESSION['admin']['username'] ?? 'Administrador';
?>

<header class="dashboard-header">
    <div class="dashboard-header__inner">
        <a
            class="dashboard-brand"
            href="index.php?page=dashboard"
            aria-label="Randomix, página principal"
        >
            <img class="brand-mark" src="assets/images/randomix-logo.png" alt="" aria-hidden="true">

            <span class="dashboard-brand__text">
                <span class="dashboard-brand__name">Randomix</span>
                <span class="dashboard-brand__caption">
                    Selección aleatoria de estudiantes
                </span>
            </span>
        </a>

        <div class="dashboard-user">
            <div class="dashboard-user__identity">
                <span class="dashboard-user__label">
                    Sesión administrativa
                </span>

                <span class="dashboard-user__name">
                    <?= htmlspecialchars(
                        (string) $adminUsername,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </span>
            </div>

            <!-- Envía el cierre de sesión mediante POST con protección CSRF. -->
            <form method="POST" action="index.php">
                <input type="hidden" name="action" value="logout">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $_SESSION['csrf_token'] ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                >

                <button class="button-logout" type="submit">
                    <span aria-hidden="true">↪</span>
                    Cerrar sesión
                </button>
            </form>
        </div>
    </div>
</header>
