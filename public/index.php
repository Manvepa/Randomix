<?php

declare(strict_types=1);

use Randomix\Controllers\AuthController;
use Randomix\Security\SessionManager;

// Verifica que Composer este instalado antes de cargar la aplicacion.
$autoloadPath = dirname(__DIR__) . '/vendor/autoload.php';

if (!is_file($autoloadPath)) {
    http_response_code(500);
    exit('Randomix no esta configurado correctamente.');
}

require $autoloadPath;

// Carga la configuracion y las variables de entorno.
$config = require dirname(__DIR__) . '/config/app.php';

// Inicia la sesion antes de procesar solicitudes o mostrar contenido.
try {
    SessionManager::start();
} catch (Throwable $exception) {
    error_log($exception->__toString());
    http_response_code(500);
    exit('No fue posible iniciar la aplicacion.');
}

// Genera un token CSRF para proteger los formularios.
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$authController = new AuthController();
$errorMessage = null;

$action = $_POST['action'] ?? null;
$page = $_GET['page'] ?? 'login';

// Comprueba si existe una sesion autenticada.
$isAuthenticated = isset(
    $_SESSION['admin']['id'],
    $_SESSION['admin']['username']
);

// Procesa el inicio de sesion enviado mediante POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'login') {
    $tokenRecibido = $_POST['csrf_token'] ?? '';

    if (
        !is_string($tokenRecibido)
        || !hash_equals($_SESSION['csrf_token'], $tokenRecibido)
    ) {
        $errorMessage = 'La solicitud ha expirado. Recarga la pagina e intenta de nuevo.';
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if (!is_string($username) || !is_string($password)) {
            $errorMessage = 'Las credenciales no son validas.';
        } else {
            try {
                $loginCorrecto = $authController->login(
                    $username,
                    $password
                );

                if ($loginCorrecto) {
                    // Renueva el token CSRF al completar la autenticacion.
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    header('Location: index.php?page=dashboard');
                    exit;
                }

                $errorMessage = 'Usuario o contrasena incorrectos.';
            } catch (Throwable $exception) {
                // Los detalles tecnicos se guardan en el registro del servidor.
                error_log($exception->__toString());

                $errorMessage = 'No fue posible procesar el acceso. Intenta nuevamente.';
            }
        }
    }
}

// Procesa el cierre de sesion mediante POST y valida el token CSRF.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'logout') {
    $tokenRecibido = $_POST['csrf_token'] ?? '';

    if (
        is_string($tokenRecibido)
        && hash_equals($_SESSION['csrf_token'], $tokenRecibido)
    ) {
        $authController->logout();

        header('Location: index.php');
        exit;
    }

    $errorMessage = 'La solicitud de cierre no es valida.';
}

// Impide entrar al area privada sin una sesion autenticada.
if ($page === 'dashboard' && !$isAuthenticated) {
    header('Location: index.php');
    exit;
}

// Evita mostrar el formulario a un administrador que ya inicio sesion.
if ($page === 'login' && $isAuthenticated) {
    header('Location: index.php?page=dashboard');
    exit;
}

// Solo se permite mostrar las paginas conocidas.
if ($page !== 'login' && $page !== 'dashboard') {
    http_response_code(404);
    exit('Pagina no encontrada.');
}

// Renderiza la vista correspondiente.
if ($page === 'dashboard') {
    require dirname(__DIR__) . '/templates/selection/index.php';
} else {
    require dirname(__DIR__) . '/templates/auth/login.php';
}
