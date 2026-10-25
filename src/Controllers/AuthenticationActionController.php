<?php

declare(strict_types=1);

namespace Randomix\Controllers;

final class AuthenticationActionController
{
    public function __construct(
        private readonly AuthController $authController
    ) {
    }

    /**
     * Procesa las acciones POST de autenticación.
     *
     * Devuelve un mensaje de error cuando la operación falla.
     * Las operaciones exitosas redirigen y terminan la solicitud.
     */
    public function handle(string $method, mixed $action): ?string
    {
        if ($method !== 'POST') {
            return null;
        }

        if ($action === 'login') {
            return $this->handleLogin();
        }

        if ($action === 'logout') {
            return $this->handleLogout();
        }

        return null;
    }

    /**
     * Valida el token CSRF y procesa el inicio de sesión.
     */
    private function handleLogin(): ?string
    {
        $tokenRecibido = $_POST['csrf_token'] ?? '';

        if (
            !is_string($tokenRecibido)
            || !hash_equals($_SESSION['csrf_token'], $tokenRecibido)
        ) {
            return 'La solicitud ha expirado. Recarga la página e intenta de nuevo.';
        }

        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if (!is_string($username) || !is_string($password)) {
            return 'Las credenciales no son válidas.';
        }

        try {
            $loginCorrecto = $this->authController->login(
                $username,
                $password
            );

            if (!$loginCorrecto) {
                return 'Usuario o contraseña incorrectos.';
            }

            // Renueva el token CSRF después de autenticar.
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            header('Location: index.php?page=dashboard');
            exit;
        } catch (\Throwable $exception) {
            error_log($exception->__toString());

            return 'No fue posible procesar el acceso. Intenta nuevamente.';
        }
    }

    /**
     * Valida el token CSRF y procesa el cierre de sesión.
     */
    private function handleLogout(): ?string
    {
        $tokenRecibido = $_POST['csrf_token'] ?? '';

        if (
            is_string($tokenRecibido)
            && hash_equals($_SESSION['csrf_token'], $tokenRecibido)
        ) {
            $this->authController->logout();

            header('Location: index.php');
            exit;
        }

        return 'La solicitud de cierre no es válida.';
    }
}
