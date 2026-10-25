<?php

declare(strict_types=1);

use Randomix\Controllers\AuthController;
use Randomix\Security\SessionManager;
use Randomix\Controllers\ExportController;
use Randomix\Bootstrap\ApplicationFactory;
use Randomix\Controllers\PageController;

// Comprueba que las dependencias de Composer estén disponibles.
$projectRoot = dirname(__DIR__);
$autoloadPath = $projectRoot . '/vendor/autoload.php';

if (!is_file($autoloadPath)) {
    http_response_code(500);
    exit('Randomix no está configurado correctamente.');
}

require $autoloadPath;

// Carga la configuración general de la aplicación.
$config = require $projectRoot . '/config/app.php';

// Inicia la sesión antes de procesar solicitudes o mostrar contenido.
try {
    SessionManager::start();
} catch (Throwable $exception) {
    error_log($exception->__toString());

    http_response_code(500);
    exit('No fue posible iniciar la aplicación.');
}

// Genera un token CSRF para proteger los formularios.
if (
    !isset($_SESSION['csrf_token'])
    || !is_string($_SESSION['csrf_token'])
) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Inicializa los controladores necesarios.
$authController = new AuthController();
// Prepara el controlador encargado de las exportaciones.
$exportController = new ExportController();
$errorMessage = null;

$action = $_POST['action'] ?? null;
$page = $_GET['page'] ?? 'login';

// Comprueba si existe una sesión administrativa autenticada.
$isAuthenticated = isset(
    $_SESSION['admin']['id'],
    $_SESSION['admin']['username']
);

 // Delega las solicitudes de exportación al controlador correspondiente.
$exportController->handle(
    $_SERVER['REQUEST_METHOD'],
    $_GET,
    $_POST,
    $isAuthenticated
);

// Valida el nombre de la página solicitada.
if (!is_string($page)) {
    http_response_code(404);
    exit('Página no encontrada.');
}

// Construye los controladores y sus dependencias principales.
$controllers = ApplicationFactory::create($projectRoot);

$uploadController = $controllers['upload'];
$temporaryFileCleanupController = $controllers['temporary_file_cleanup'];
$excelComparisonController = $controllers['excel_comparison'];
$excelMergeController = $controllers['excel_merge'];
$excelInspectionController = $controllers['excel_inspection'];
$selectionController = $controllers['selection'];
$postActionController = $controllers['post_action'];

// Prepara el controlador encargado de renderizar las páginas.
$pageController = new PageController(
    $projectRoot,
    $excelInspectionController,
    $excelComparisonController,
    $excelMergeController
);

// Limpia archivos temporales y referencias vencidas de sesiones autenticadas.
if ($isAuthenticated) {
    $temporaryFileCleanupController->cleanup();
}

// Procesa el inicio de sesión enviado mediante POST.
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $action === 'login'
) {
    $tokenRecibido = $_POST['csrf_token'] ?? '';

    // Verifica el token CSRF antes de comprobar las credenciales.
    if (
        !is_string($tokenRecibido)
        || !hash_equals($_SESSION['csrf_token'], $tokenRecibido)
    ) {
        $errorMessage = 'La solicitud ha expirado. Recarga la página e intenta de nuevo.';
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        if (!is_string($username) || !is_string($password)) {
            $errorMessage = 'Las credenciales no son válidas.';
        } else {
            try {
                $loginCorrecto = $authController->login(
                    $username,
                    $password
                );

                if ($loginCorrecto) {
                    // Renueva el token CSRF después de autenticar.
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                    header('Location: index.php?page=dashboard');
                    exit;
                }

                $errorMessage = 'Usuario o contraseña incorrectos.';
            } catch (Throwable $exception) {
                error_log($exception->__toString());

                $errorMessage = 'No fue posible procesar el acceso. Intenta nuevamente.';
            }
        }
    }
}

// Procesa el cierre de sesión mediante POST y valida el token CSRF.
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $action === 'logout'
) {
    $tokenRecibido = $_POST['csrf_token'] ?? '';

    if (
        is_string($tokenRecibido)
        && hash_equals($_SESSION['csrf_token'], $tokenRecibido)
    ) {
        $authController->logout();

        header('Location: index.php');
        exit;
    }

    $errorMessage = 'La solicitud de cierre no es válida.';
}

// Delega las acciones POST reconocidas al controlador central.
$postActionController->handle(
    $_SERVER['REQUEST_METHOD'],
    $action,
    $isAuthenticated
);

// Impide acceder a las páginas internas sin una sesión autenticada.
$protectedPages = [
    'dashboard',
    'selection',
    'comparison',
    'merge',
];

if (in_array($page, $protectedPages, true) && !$isAuthenticated) {
    header('Location: index.php');
    exit;
}

// Redirige al panel si el administrador ya inició sesión.
if ($page === 'login' && $isAuthenticated) {
    header('Location: index.php?page=dashboard');
    exit;
}

// Solo se permite mostrar las páginas conocidas.
$allowedPages = [
    'login',
    'dashboard',
    'selection',
    'comparison',
    'merge',
];

if (!in_array($page, $allowedPages, true)) {
    http_response_code(404);
    exit('Página no encontrada.');
}

// Delega la preparación y el renderizado de la página solicitada.
$pageController->render($page, $errorMessage);