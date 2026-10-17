<?php

declare(strict_types=1);

// Comprueba que las dependencias de Composer estén instaladas.
$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';

if (!is_file($autoloadPath)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');

    echo 'Randomix no está configurado correctamente.';
    exit;
}

// Carga las clases y dependencias del proyecto.
require $autoloadPath;

// Carga la configuración centralizada de la aplicación.
$config = require dirname(__DIR__) . '/config/app.php';

// Respuesta temporal mientras construimos las funcionalidades.
header('Content-Type: text/plain; charset=UTF-8');

echo $config['name'] . ': punto de entrada operativo.';