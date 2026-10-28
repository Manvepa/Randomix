<?php

declare(strict_types=1);

// Define valores seguros por defecto para las páginas que usan el layout.
$pageTitle = $pageTitle ?? 'Inicio';
$appName = $config['name'] ?? 'Randomix';
$content = $content ?? '';

// Determina si la página debe mostrar la estructura del área privada.
$showAuthenticatedLayout = $showAuthenticatedLayout ?? false;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#163b70">

    <title><?= htmlspecialchars(
        $pageTitle . ' | ' . $appName,
        ENT_QUOTES,
        'UTF-8'
    ) ?></title>

    <link rel="stylesheet" href="assets/css/app.css?v=2">
</head>
<body>
    <?php if ($showAuthenticatedLayout): ?>
        <?php require __DIR__ . '/header.php'; ?>
    <?php endif; ?>

    <main class="app-shell<?= $showAuthenticatedLayout ? ' app-shell--dashboard' : '' ?>">
        <?= $content ?>
    </main>

    <?php if ($showAuthenticatedLayout): ?>
        <?php require __DIR__ . '/footer.php'; ?>
    <?php endif; ?>

    <!-- Gestiona el indicador de carga de los formularios. -->
    <script src="assets/js/loading-overlay.js?v=2" defer></script>
</body>
</html>
