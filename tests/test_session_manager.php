<?php

declare(strict_types=1);

use Randomix\Security\SessionManager;

// Carga el autoloader y las clases de Randomix.
require dirname(__DIR__) . '/vendor/autoload.php';

// Inicia una sesión con la configuración de seguridad de Randomix.
SessionManager::start();

// Comprueba que PHP haya iniciado la sesión correctamente.
if (session_status() !== PHP_SESSION_ACTIVE) {
    throw new RuntimeException('La sesión no está activa.');
}

// Guarda un dato temporal para comprobar que la sesión funciona.
$_SESSION['randomix_test'] = 'sesion_activa';

// Renueva el identificador para simular el proceso posterior al login.
SessionManager::regenerate();

// Comprueba que el dato siga disponible después de renovar el identificador.
if (($_SESSION['randomix_test'] ?? null) !== 'sesion_activa') {
    throw new RuntimeException(
        'No se conservaron los datos al renovar la sesión.'
    );
}

echo "Prueba correcta: sesión iniciada y renovada.\n";

// Cierra la sesión de prueba y elimina sus datos.
$_SESSION = [];
session_destroy();
