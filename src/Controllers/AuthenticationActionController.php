<?php

declare(strict_types=1);

namespace Randomix\Controllers;

use Randomix\Services\RegistrationService;

final class AuthenticationActionController
{
    public function __construct(
        private readonly AuthController $authController,
        private readonly RegistrationService $registrationService = new RegistrationService()
    ) {
    }

    /**
     * Procesa las acciones POST de autenticación y registro.
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

        if ($action === 'register') {
            return $this->handleRegistration();
        }

        return null;
    }

    /**
     * Comprueba el token CSRF antes de procesar una operación.
     */
    private function hasValidCsrfToken(): bool
    {
        $tokenRecibido = $_POST['csrf_token'] ?? '';
        $tokenSesion = $_SESSION['csrf_token'] ?? '';

        return is_string($tokenRecibido)
            && is_string($tokenSesion)
            && $tokenSesion !== ''
            && hash_equals($tokenSesion, $tokenRecibido);
    }

    /**
     * Valida el token CSRF y procesa el inicio de sesión.
     */
    private function handleLogin(): ?string
    {
        if (!$this->hasValidCsrfToken()) {
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
     * Valida el formulario y delega la creación de la cuenta al servicio.
     */
    private function handleRegistration(): ?string
    {
        if (!$this->hasValidCsrfToken()) {
            return 'La solicitud ha expirado. Recarga la página e intenta de nuevo.';
        }

        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $passwordConfirmation = $_POST['password_confirmation'] ?? '';

        if (
            !is_string($username)
            || !is_string($password)
            || !is_string($passwordConfirmation)
        ) {
            return 'Los datos del registro no son válidos.';
        }

        try {
            $error = $this->registrationService->register(
                $username,
                $password,
                $passwordConfirmation
            );

            if ($error !== null) {
                return $error;
            }

            // Informa del resultado sin iniciar una sesión automáticamente.
            $_SESSION['registration_success'] =
                'Cuenta creada correctamente. Ya puedes iniciar sesión.';

            header('Location: index.php');
            exit;
        } catch (\Throwable $exception) {
            error_log($exception->__toString());

            return 'No fue posible crear la cuenta. Intenta nuevamente.';
        }
    }

    /**
     * Valida el token CSRF y procesa el cierre de sesión.
     */
    private function handleLogout(): ?string
    {
        if ($this->hasValidCsrfToken()) {
            $this->authController->logout();

            header('Location: index.php');
            exit;
        }

        return 'La solicitud de cierre no es válida.';
    }
}
