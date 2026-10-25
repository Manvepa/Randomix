<?php

declare(strict_types=1);

use Randomix\Controllers\AuthController;
use Randomix\Controllers\UploadController;
use Randomix\Controllers\ExcelInspectionController;
use Randomix\Security\SessionManager;
use Randomix\Controllers\SelectionController;
use Randomix\Controllers\ExcelComparisonController;
use Randomix\Controllers\ExcelMergeController;
use Randomix\Controllers\ExportController;
use Randomix\Controllers\TemporaryFileCleanupController;
use Randomix\Controllers\PostActionController;

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

// Carga la configuración de importación y prepara el controlador.
$importConfig = require $projectRoot . '/config/import.php';

$uploadController = new UploadController(
    $projectRoot,
    (int) $importConfig['max_file_size']
);

// Prepara el controlador encargado de limpiar archivos temporales vencidos.
$temporaryFileCleanupController = new TemporaryFileCleanupController(
    $uploadController
);

// Prepara el controlador independiente de comparación de Excel.
$excelComparisonController = new ExcelComparisonController(
    $projectRoot,
    (int) $importConfig['max_file_size'],
    $importConfig['required_headers'],
    (int) $importConfig['max_rows']
);

// Prepara el controlador independiente para unir dos archivos Excel.
$excelMergeController = new ExcelMergeController(
    $projectRoot,
    (int) $importConfig['max_file_size'],
    $importConfig['required_headers'],
    (int) $importConfig['max_rows']
);

// Prepara el servicio que inspecciona las hojas y valida sus encabezados.
$excelInspectionController = new ExcelInspectionController(
    $projectRoot,
    $importConfig['required_headers']
);

// Prepara el controlador encargado del procesamiento y la selección.
$selectionController = new SelectionController(
    $projectRoot,
    $importConfig['required_headers']
);

// Centraliza las acciones POST de los procesos de Randomix.
$postActionController = new PostActionController(
    $uploadController,
    $excelInspectionController,
    $selectionController,
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

// Carga la vista correspondiente a la página solicitada.
if ($page === 'dashboard') {
    // El dashboard solo presenta las opciones de entrada a cada proceso.
    require $projectRoot . '/templates/dashboard/index.php';
} elseif ($page === 'selection') {
    // Recupera los datos del proceso de selección guardados en la sesión.
    $uploadedExcel = $_SESSION['uploaded_excel'] ?? null;
    $excelSheets = [];
    $sheetValidation = $_SESSION['sheet_validation'] ?? null;
    $selectionResult = $_SESSION['selection_result'] ?? null;

    // Obtiene las hojas del Excel de selección cuando existe un archivo temporal.
    if (is_array($uploadedExcel)) {
        $sheetResult = $excelInspectionController->getSheetNames($uploadedExcel);

        if ($sheetResult['success'] === true) {
            $excelSheets = $sheetResult['sheets'];
        }
    }

    // Renderiza exclusivamente la vista del proceso de selección.
    require $projectRoot . '/templates/selection/index.php';
} elseif ($page === 'comparison') {
    // Recupera los archivos y el resultado del proceso de comparación.
    $comparisonExcelA = $_SESSION['comparison_excel_a'] ?? null;
    $comparisonExcelB = $_SESSION['comparison_excel_b'] ?? null;
    $comparisonSheetsA = [];
    $comparisonSheetsB = [];
    $comparisonResult = $_SESSION['comparison_result'] ?? null;

    // Obtiene las hojas del Excel A cuando existe un archivo temporal.
    if (is_array($comparisonExcelA)) {
        $comparisonSheetResultA = $excelComparisonController->getSheetNames($comparisonExcelA);

        if ($comparisonSheetResultA['success'] === true) {
            $comparisonSheetsA = $comparisonSheetResultA['sheets'];
        }
    }

    // Obtiene las hojas del Excel B cuando existe un archivo temporal.
    if (is_array($comparisonExcelB)) {
        $comparisonSheetResultB = $excelComparisonController->getSheetNames($comparisonExcelB);

        if ($comparisonSheetResultB['success'] === true) {
            $comparisonSheetsB = $comparisonSheetResultB['sheets'];
        }
    }

    // Renderiza exclusivamente la vista del proceso de comparación.
    require $projectRoot . '/templates/comparison/index.php';


} elseif ($page === 'merge') {
    // Recupera los archivos y el resultado de la unión guardados en sesión.
    $mergeExcelA = $_SESSION['merge_excel_a'] ?? null;
    $mergeExcelB = $_SESSION['merge_excel_b'] ?? null;
    $mergeSheetsA = [];
    $mergeSheetsB = [];
    $mergeResult = $_SESSION['merge_result'] ?? null;

    // Obtiene las hojas del Excel A cuando existe un archivo temporal.
    if (is_array($mergeExcelA)) {
        $mergeSheetResultA = $excelMergeController->getSheetNames($mergeExcelA);

        if ($mergeSheetResultA['success'] === true) {
            $mergeSheetsA = $mergeSheetResultA['sheets'];
        }
    }

    // Obtiene las hojas del Excel B cuando existe un archivo temporal.
    if (is_array($mergeExcelB)) {
        $mergeSheetResultB = $excelMergeController->getSheetNames($mergeExcelB);

        if ($mergeSheetResultB['success'] === true) {
            $mergeSheetsB = $mergeSheetResultB['sheets'];
        }
    }

    // Renderiza exclusivamente la vista del proceso de unión.
    require $projectRoot . '/templates/merge/index.php';


} else {
    // Si la página es login, muestra el formulario de autenticación.
    require $projectRoot . '/templates/auth/login.php';
}