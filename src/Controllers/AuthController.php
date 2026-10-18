<?php

declare(strict_types=1);

namespace Randomix\Controllers;

use Randomix\Security\SessionManager;
use Randomix\Services\AuthService;

final class AuthController
{
    /**
     * Procesa las credenciales recibidas desde el formulario de acceso.
     *
     * Si son válidas, regenera el identificador de sesión y guarda
     * únicamente los datos básicos del administrador autenticado.
     */
    public function login(string $username, string $password): bool
    {
        // Comprueba que se hayan proporcionado ambos campos.
        if (trim($username) === '' || $password === '') {
            return false;
        }

        // Delega la validación de las credenciales al servicio.
        $authService = new AuthService();
        $administrador = $authService->authenticate($username, $password);

        // No crea una sesión autenticada si las credenciales son incorrectas.
        if ($administrador === null) {
            return false;
        }

        // Inicia la sesión y renueva su identificador después de autenticar.
        SessionManager::start();
        SessionManager::regenerate();

        // Guarda solo los datos necesarios para identificar al administrador.
        $_SESSION['admin'] = [
            'id' => $administrador['id'],
            'username' => $administrador['username'],
        ];

        return true;
    }

    /**
     * Destruye la sesión autenticada y elimina su cookie del navegador.
     */
    public function logout(): void
    {
        // No intenta cerrar una sesión que no esté activa.
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        // Elimina los datos almacenados en la sesión.
        $_SESSION = [];

        // Expira la cookie con los mismos parámetros de seguridad.
        $cookie = session_get_cookie_params();

        if (ini_get('session.use_cookies')) {
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $cookie['path'],
                'domain' => $cookie['domain'],
                'secure' => $cookie['secure'],
                'httponly' => $cookie['httponly'],
                'samesite' => $cookie['samesite'] ?? 'Lax',
            ]);
        }

        // Destruye los datos de la sesión en el servidor.
        session_destroy();
    }
}
