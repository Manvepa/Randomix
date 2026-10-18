<?php

declare(strict_types=1);

use Randomix\Controllers\AuthController;
use Randomix\Controllers\UploadController;
use Randomix\Controllers\ExcelInspectionController;
use Randomix\Security\SessionManager;

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
$errorMessage = null;

$action = $_POST['action'] ?? null;
$page = $_GET['page'] ?? 'login';

// Comprueba si existe una sesión administrativa autenticada.
$isAuthenticated = isset(
    $_SESSION['admin']['id'],
    $_SESSION['admin']['username']
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

// Prepara el servicio que inspecciona las hojas y valida sus encabezados.
$excelInspectionController = new ExcelInspectionController(
    $projectRoot,
    $importConfig['required_headers']
);

// Limpia archivos temporales vencidos y descarta referencias expiradas.
if ($isAuthenticated) {
    try {
        $uploadController->cleanupExpiredFiles();
    } catch (Throwable $exception) {
        error_log($exception->__toString());
    }

    $uploadedExcel = $_SESSION['uploaded_excel'] ?? null;

    if (
        is_array($uploadedExcel)
        && isset($uploadedExcel['expires_at'])
        && is_int($uploadedExcel['expires_at'])
        && $uploadedExcel['expires_at'] <= time()
    ) {
        unset($_SESSION['uploaded_excel']);

        $_SESSION['upload_feedback'] = [
            'type' => 'error',
            'message' => 'El archivo temporal venció. Debes cargarlo nuevamente.',
        ];
        unset($_SESSION['sheet_validation']);
    }
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

// Procesa la carga de Excel exclusivamente para administradores autenticados.
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $action === 'upload_excel'
) {
    // Evita que un visitante sin sesión cargue archivos.
    if (!$isAuthenticated) {
        header('Location: index.php');
        exit;
    }

    $tokenRecibido = $_POST['csrf_token'] ?? '';

    // Comprueba el token CSRF antes de procesar el archivo.
    if (
        !is_string($tokenRecibido)
        || !hash_equals($_SESSION['csrf_token'], $tokenRecibido)
    ) {
        $_SESSION['upload_feedback'] = [
            'type' => 'error',
            'message' => 'La solicitud ha expirado. Recarga el panel e inténtalo de nuevo.',
        ];
    } else {
        $file = $_FILES['excel_file'] ?? null;

        try {
            if (!is_array($file)) {
                $result = [
                    'success' => false,
                    'errors' => [
                        'Debes seleccionar un archivo Excel.',
                    ],
                ];
            } else {
                $result = $uploadController->handle($file);
            }

            if ($result['success'] === true) {
                // Evita conservar la validación del archivo anterior.
                unset($_SESSION['sheet_validation']);
                $now = time();

                // Registra solo los metadatos del archivo temporal.
                $_SESSION['uploaded_excel'] = [
                    'filename' => $result['stored_filename'],
                    'original_name' => $result['original_name'],
                    'uploaded_at' => $now,
                    'expires_at' => $now + 900,
                ];

                $_SESSION['upload_feedback'] = [
                    'type' => 'success',
                    'message' => 'El archivo se cargó correctamente. Estará disponible durante 15 minutos.',
                ];
            } else {
                $_SESSION['upload_feedback'] = [
                    'type' => 'error',
                    'message' => implode(
                        ' ',
                        $result['errors'] ?? [
                            'No fue posible validar el archivo.',
                        ]
                    ),
                ];
            }
        } catch (Throwable $exception) {
            // Registra los detalles técnicos sin mostrarlos al usuario.
            error_log($exception->__toString());

            $_SESSION['upload_feedback'] = [
                'type' => 'error',
                'message' => 'No fue posible guardar el archivo. Inténtalo nuevamente.',
            ];
        }
    }

    // Aplica el patrón POST/Redirect/GET para evitar reenvíos accidentales.
    header('Location: index.php?page=dashboard');
    exit;
}


/**
 * Procesa la selección de una hoja y valida sus encabezados.
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $action === 'validate_sheet'
) {
    // Solo los administradores autenticados pueden inspeccionar el Excel.
    if (!$isAuthenticated) {
        header('Location: index.php');
        exit;
    }

    $tokenRecibido = $_POST['csrf_token'] ?? '';

    // Verifica el token CSRF antes de leer el archivo.
    if (
        !is_string($tokenRecibido)
        || !hash_equals($_SESSION['csrf_token'], $tokenRecibido)
    ) {
        $_SESSION['sheet_validation'] = [
            'success' => false,
            'valid' => false,
            'errors' => [
                'La solicitud ha expirado. Recarga la página e inténtalo nuevamente.',
            ],
        ];
    } else {
        $uploadedExcel = $_SESSION['uploaded_excel'] ?? null;
        $sheetName = $_POST['sheet_name'] ?? '';

        // Comprueba que exista un archivo temporal y que la hoja sea válida.
        if (
            !is_array($uploadedExcel)
            || !is_string($sheetName)
            || trim($sheetName) === ''
        ) {
            $_SESSION['sheet_validation'] = [
                'success' => false,
                'valid' => false,
                'errors' => [
                    'Debes cargar un archivo y seleccionar una hoja válida.',
                ],
            ];
        } else {
            $_SESSION['sheet_validation'] =
                $excelInspectionController->validateSheet(
                    $uploadedExcel,
                    $sheetName
                );
        }
    }

    // Evita repetir la validación al actualizar el navegador.
    header('Location: index.php?page=dashboard');
    exit;
}


// Impide acceder al panel sin una sesión autenticada.
if ($page === 'dashboard' && !$isAuthenticated) {
    header('Location: index.php');
    exit;
}

// Redirige al panel si el administrador ya inició sesión.
if ($page === 'login' && $isAuthenticated) {
    header('Location: index.php?page=dashboard');
    exit;
}

// Solo se permite mostrar las páginas conocidas.
if ($page !== 'login' && $page !== 'dashboard') {
    http_response_code(404);
    exit('Página no encontrada.');
}

// Prepara los datos del Excel temporal para mostrarlos en el panel.
if ($page === 'dashboard') {
    $uploadedExcel = $_SESSION['uploaded_excel'] ?? null;
    $excelSheets = [];
    $sheetValidation = $_SESSION['sheet_validation'] ?? null;

    // Obtiene las hojas únicamente si existe un archivo temporal vigente.
    if (is_array($uploadedExcel)) {
        $sheetResult = $excelInspectionController->getSheetNames(
            $uploadedExcel
        );

        if ($sheetResult['success'] === true) {
            $excelSheets = $sheetResult['sheets'];
        } else {
            $excelSheets = [];
        }
    }

    require $projectRoot . '/templates/selection/index.php';
} else {
    require $projectRoot . '/templates/auth/login.php';
}
