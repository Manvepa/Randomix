<?php

declare(strict_types=1);

use Dotenv\Dotenv;

// Define la raíz del proyecto independientemente del directorio actual.
$rootPath = dirname(__DIR__);

// Carga las variables de entorno sin sobrescribir variables existentes.
$dotenv = Dotenv::createImmutable($rootPath);
$dotenv->safeLoad();

// Centraliza los valores que necesita la aplicación.
return [
    'name' => $_ENV['APP_NAME'] ?? 'Randomix',
    'env' => $_ENV['APP_ENV'] ?? 'production',
    'debug' => filter_var(
        $_ENV['APP_DEBUG'] ?? 'false',
        FILTER_VALIDATE_BOOL
    ),
    'url' => rtrim(
        $_ENV['APP_URL'] ?? 'http://localhost',
        '/'
    ),
    'paths' => [
        'root' => $rootPath,
        'storage' => $rootPath . '/storage',
    ],
];