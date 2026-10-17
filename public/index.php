<?php

declare(strict_types=1);

// Comprueba que el autoload de Composer esté disponible.
$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';

if (!is_file($autoloadPath)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');

    echo 'Randomix no está configurado correctamente.';
    exit;
}

// Carga el autoload para utilizar las clases del proyecto.
require $autoloadPath;

// Respuesta temporal para comprobar el punto de entrada.
header('Content-Type: text/plain; charset=UTF-8');

echo 'Randomix: punto de entrada operativo.';