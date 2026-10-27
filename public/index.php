<?php

declare(strict_types=1);

use Randomix\Controllers\AuthController;
use Randomix\Security\SessionManager;
use Randomix\Controllers\ExportController;
use Randomix\Bootstrap\ApplicationFactory;
use Randomix\Controllers\PageController;
use Randomix\Controllers\AuthenticationActionController;
use Randomix\Controllers\PageAccessController;

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
$authenticationActionController = new AuthenticationActionController(
    $authController
);

// Prepara el controlador encargado de las reglas de acceso a las páginas.
$pageAccessController = new PageAccessController();

// Prepara el controlador encargado de las exportaciones.
$exportController = new ExportController();
$errorMessage = null;

$action = $_POST['action'] ?? null;
$page = $_GET['page'] ?? 'login';

// Conserva el modo de registro cuando el formulario se envía por POST.
$registrationMode = ($_GET['mode'] ?? '') === 'register'
    || (
        ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
        && ($_POST['action'] ?? '') === 'register'
    );

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

// Construye los controladores y sus dependencias principales.
$controllers = ApplicationFactory::create($projectRoot);

$temporaryFileCleanupController = $controllers['temporary_file_cleanup'];
$excelComparisonController = $controllers['excel_comparison'];
$excelMergeController = $controllers['excel_merge'];
$excelInspectionController = $controllers['excel_inspection'];
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

// Delega el inicio y cierre de sesión al controlador de autenticación.
$authenticationError = $authenticationActionController->handle(
    $_SERVER['REQUEST_METHOD'],
    $action
);

if ($authenticationError !== null) {
    $errorMessage = $authenticationError;
}

// Delega las acciones POST reconocidas al controlador central.
$postActionController->handle(
    $_SERVER['REQUEST_METHOD'],
    $action,
    $isAuthenticated
);

// Valida que el nombre de la página sea válido.
if (!$pageAccessController->isValidPage($page)) {
    http_response_code(404);
    exit('Página no encontrada.');
}

// Impide acceder a páginas protegidas sin autenticación.
if (
    $pageAccessController->requiresAuthentication($page)
    && !$isAuthenticated
) {
    header('Location: index.php');
    exit;
}

// Redirige al panel si el administrador ya inició sesión.
if ($page === 'login' && $isAuthenticated) {
    header('Location: index.php?page=dashboard');
    exit;
}

// Delega la preparación y el renderizado de la página solicitada.
$pageController->render($page, $errorMessage);