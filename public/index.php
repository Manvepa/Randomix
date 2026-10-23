<?php

declare(strict_types=1);

use Randomix\Controllers\AuthController;
use Randomix\Controllers\UploadController;
use Randomix\Controllers\ExcelInspectionController;
use Randomix\Security\SessionManager;
use Randomix\Controllers\SelectionController;
use Randomix\Services\ExcelExportService;
use Randomix\Services\PdfPreviewService;
use Randomix\Controllers\ExcelComparisonController;
use Randomix\Controllers\ExcelMergeController;

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
        unset($_SESSION['selection_result']);
    }

    // NUEVO: comprueba la expiración de Excel A y Excel B.
    $comparisonExpired = false;

    foreach (['comparison_excel_a', 'comparison_excel_b'] as $sessionKey) {
        $comparisonFile = $_SESSION[$sessionKey] ?? null;

        if (
            is_array($comparisonFile)
            && isset($comparisonFile['expires_at'])
            && is_int($comparisonFile['expires_at'])
            && $comparisonFile['expires_at'] <= time()
        ) {
            unset($_SESSION[$sessionKey]);
            $comparisonExpired = true;
        }
    }

    // Invalida la comparación si alguno de sus archivos venció.
    if ($comparisonExpired) {
        unset($_SESSION['comparison_result']);

        $_SESSION['comparison_feedback'] = [
            'type' => 'error',
            'message' => 'Uno de los archivos de comparación ha vencido. Debes cargarlo nuevamente.',
        ];
    }
}


/**
 * Genera y descarga el Excel C con el resultado de la unión.
 * Utiliza los registros deduplicados almacenados en la sesión.
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $action === 'export_merge'
) {
    // Solo los administradores autenticados pueden descargar el resultado.
    if (!$isAuthenticated) {
        header('Location: index.php');
        exit;
    }

    // Comprueba el token CSRF antes de generar el archivo.
    $tokenRecibido = $_POST['csrf_token'] ?? '';

    if (
        !is_string($tokenRecibido)
        || !hash_equals($_SESSION['csrf_token'], $tokenRecibido)
    ) {
        http_response_code(403);
        exit(
            'La solicitud ha expirado. Recarga el panel e inténtalo nuevamente.'
        );
    }

    $mergeResult = $_SESSION['merge_result'] ?? null;

    // Comprueba que exista un resultado válido para exportar.
    if (
        !is_array($mergeResult)
        || !isset($mergeResult['headers'], $mergeResult['records'])
        || !is_array($mergeResult['headers'])
        || $mergeResult['headers'] === []
        || !is_array($mergeResult['records'])
    ) {
        http_response_code(404);
        exit('No hay resultados de unión disponibles para exportar.');
    }

    try {
        // Genera el Excel C con los encabezados oficiales y los registros finales.
        $exportService = new ExcelExportService();

        $content = $exportService->generate(
            $mergeResult['headers'],
            $mergeResult['records'],
            [],
            [],
            true
        );
    } catch (Throwable $exception) {
        // Registra el detalle técnico sin exponerlo al usuario.
        error_log($exception->__toString());

        http_response_code(500);
        exit('No fue posible generar el Excel C.');
    }

    // Envía el archivo al navegador sin guardarlo permanentemente.
    $filename = 'Excel_C_unificado.xlsx';

    header(
        'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );
    header(
        'Content-Disposition: attachment; filename="' . $filename . '"'
    );
    header('Content-Length: ' . strlen($content));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('X-Content-Type-Options: nosniff');

    echo $content;
    exit;
}

/**
 * Genera y descarga el Excel A sin las filas que coincidieron con B.
 * Utiliza el resultado calculado y guardado en la sesión.
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $action === 'export_comparison'
) {
    // Exige una sesión administrativa autenticada.
    if (!$isAuthenticated) {
        header('Location: index.php');
        exit;
    }

    $tokenRecibido = $_POST['csrf_token'] ?? '';

    // Verifica el token CSRF antes de generar el archivo.
    if (
        !is_string($tokenRecibido)
        || !hash_equals($_SESSION['csrf_token'], $tokenRecibido)
    ) {
        http_response_code(403);
        exit('La solicitud ha expirado. Recarga el panel e inténtalo nuevamente.');
    }

    $comparisonResult = $_SESSION['comparison_result'] ?? null;

    // Comprueba que exista una comparación válida para exportar.
    if (
        !is_array($comparisonResult)
        || !isset(
        $comparisonResult['headers'],
        $comparisonResult['records']
    )
        || !is_array($comparisonResult['headers'])
        || $comparisonResult['headers'] === []
        || !is_array($comparisonResult['records'])
    ) {
        http_response_code(404);
        exit('No hay resultados de comparación disponibles para exportar.');
    }

    try {
        // Genera el XLSX usando las columnas y filas filtradas de Excel A.
        $exportService = new ExcelExportService();

        $content = $exportService->generate(
            $comparisonResult['headers'],
            $comparisonResult['records']
        );
    } catch (Throwable $exception) {
        // Registra el detalle técnico sin exponerlo al usuario.
        error_log($exception->__toString());

        http_response_code(500);
        exit('No fue posible generar el Excel de la comparación.');
    }

    // Envía el archivo al navegador sin guardarlo permanentemente.
    $filename = 'Actualizacion_Estudiantes.xlsx';

    header(
        'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );
    header(
        'Content-Disposition: attachment; filename="' . $filename . '"'
    );
    header('Content-Length: ' . strlen($content));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    header('X-Content-Type-Options: nosniff');

    echo $content;
    exit;
}

/**
 * Procesa las descargas de Excel antes de generar cualquier HTML.
 * Solo permite descargar resultados de una selección válida.
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'GET'
    && isset($_GET['export'])
) {
    // Exige una sesión administrativa autenticada.
    if (!$isAuthenticated) {
        header('Location: index.php');
        exit;
    }

    $exportType = $_GET['export'];

    // Permite exportar los grupos de resultados disponibles.
    if (
        !is_string($exportType)
        || !in_array(
            $exportType,
            ['selected', 'not_selected', 'alternates'],
            true
        )
    ) {
        http_response_code(400);
        exit('Tipo de exportación no válido.');
    }

    $selectionResult = $_SESSION['selection_result'] ?? null;

    // Impide exportar si no existe una selección válida.
    if (
        !is_array($selectionResult)
        || ($selectionResult['success'] ?? false) !== true
    ) {
        http_response_code(404);
        exit('No hay resultados disponibles para exportar.');
    }

    // Recupera los grupos de resultados de la selección.
    $selectedRecords = $selectionResult['selected'] ?? null;
    $notSelectedRecords = $selectionResult['not_selected'] ?? null;
    $alternateRecords = $selectionResult['alternates'] ?? null;

    // Comprueba que los grupos tengan una estructura válida.
    if (
        !is_array($selectedRecords)
        || !is_array($notSelectedRecords)
        || !is_array($alternateRecords)
    ) {
        http_response_code(500);
        exit('Los resultados de la selección no tienen una estructura válida.');
    }

    // Obtiene los encabezados a partir de ambos grupos.
    // Esto permite exportar un grupo vacío conservando sus columnas.
    $allRecords = array_merge(
        $selectedRecords,
        $notSelectedRecords
    );

    if ($allRecords === [] || !is_array($allRecords[0])) {
        http_response_code(500);
        exit('No fue posible determinar los encabezados del Excel.');
    }

    $headers = array_keys($allRecords[0]);


    // Selecciona exclusivamente el grupo solicitado.
    $records = match ($exportType) {
        'selected' => $selectedRecords,
        'not_selected' => $notSelectedRecords,
        'alternates' => $alternateRecords,
    };

    // Obtiene los índices de hermanos únicamente para el Excel seleccionado.
    $highlightedIndexes = $exportType === 'selected'
        ? ($selectionResult['selected_matching_surname_indexes'] ?? [])
        : [];

    // Calcula los índices de suplentes dentro del conjunto seleccionado.
    $alternateIndexes = [];

    if ($exportType === 'selected') {
        $beneficiariesCount = count(
            $selectionResult['beneficiaries'] ?? []
        );

        $alternatesCount = count(
            $selectionResult['alternates'] ?? []
        );

        for (
            $index = $beneficiariesCount;
            $index < $beneficiariesCount + $alternatesCount;
            $index++
        ) {
            $alternateIndexes[] = $index;
        }
    }


    try {
        $exportService = new ExcelExportService();
        $content = $exportService->generate(
            $headers,
            $records,
            $highlightedIndexes,
            $alternateIndexes
        );

    } catch (Throwable $exception) {
        error_log($exception->__toString());

        http_response_code(500);
        exit('No fue posible generar el archivo Excel.');
    }

    // Asigna un nombre descriptivo según el grupo exportado.
    $filename = match ($exportType) {
        'selected' => 'estudiantes_seleccionados.xlsx',
        'not_selected' => 'estudiantes_no_seleccionados.xlsx',
        'alternates' => 'estudiantes_suplentes.xlsx',
    };

    header(
        'Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );
    header(
        'Content-Disposition: attachment; filename="' . $filename . '"'
    );
    header('Content-Length: ' . strlen($content));
    header('Cache-Control: no-store, no-cache, must-revalidate');

    echo $content;
    exit;
}

/**
 * Genera la vista previa de los resultados en PDF.
 * Requiere una sesión administrativa y una selección válida.
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'GET'
    && isset($_GET['pdf'])
) {
    // Exige una sesión administrativa autenticada.
    if (!$isAuthenticated) {
        header('Location: index.php');
        exit;
    }

    // Acepta únicamente los dos grupos de resultados disponibles.
    $pdfType = $_GET['pdf'];

    if (
        !is_string($pdfType)
        || !in_array($pdfType, ['selected', 'not_selected', 'alternates'], true)
    ) {
        http_response_code(400);
        exit('Tipo de PDF no válido.');
    }

    $selectionResult = $_SESSION['selection_result'] ?? null;

    // Impide generar PDF si no existe una selección válida.
    if (
        !is_array($selectionResult)
        || ($selectionResult['success'] ?? false) !== true
    ) {
        http_response_code(404);
        exit('No hay resultados disponibles para generar el PDF.');
    }

    // Recupera los grupos de resultados de la selección.
    $selectedRecords = $selectionResult['selected'] ?? null;
    $notSelectedRecords = $selectionResult['not_selected'] ?? null;
    $alternateRecords = $selectionResult['alternates'] ?? null;

    // Comprueba que los grupos tengan una estructura válida.
    if (
        !is_array($selectedRecords)
        || !is_array($notSelectedRecords)
        || !is_array($alternateRecords)
    ) {
        http_response_code(500);
        exit('Los resultados de la selección no tienen una estructura válida.');
    }

    // Obtiene los encabezados de ambos grupos para conservar las columnas,
    // incluso cuando el grupo solicitado no contiene registros.
    $allRecords = array_merge(
        $selectedRecords,
        $notSelectedRecords,
        $alternateRecords,
    );

    if (
        $allRecords === []
        || !isset($allRecords[0])
        || !is_array($allRecords[0])
    ) {
        http_response_code(500);
        exit('No fue posible determinar los encabezados del PDF.');
    }

    $headers = array_keys($allRecords[0]);

    // Selecciona exclusivamente el grupo solicitado para el PDF.
    $records = match ($pdfType) {
        'selected' => $selectedRecords,
        'not_selected' => $notSelectedRecords,
        'alternates' => $alternateRecords,
    };

    // Resalta coincidencias de apellidos únicamente en los seleccionados.
    $highlightedIndexes = $pdfType === 'selected'
        ? ($selectionResult['selected_matching_surname_indexes'] ?? [])
        : [];

    // Calcula los índices de suplentes dentro del conjunto seleccionado.
    $alternateIndexes = [];

    if ($pdfType === 'selected') {
        $beneficiariesCount = count(
            $selectionResult['beneficiaries'] ?? []
        );

        $alternatesCount = count(
            $selectionResult['alternates'] ?? []
        );

        for (
            $index = $beneficiariesCount;
            $index < $beneficiariesCount + $alternatesCount;
            $index++
        ) {
            $alternateIndexes[] = $index;
        }
    }

    // Asigna un título según el grupo que se mostrará.
    $title = match ($pdfType) {
        'selected' => 'Estudiantes seleccionados',
        'not_selected' => 'Estudiantes no seleccionados',
        'alternates' => 'Suplentes',
    };

    // Asigna un nombre de archivo según el grupo mostrado.
    $filename = match ($pdfType) {
        'selected' => 'estudiantes_seleccionados.pdf',
        'not_selected' => 'estudiantes_no_seleccionados.pdf',
        'alternates' => 'estudiantes_suplentes.pdf',
    };

    try {
        $pdfService = new PdfPreviewService();

        $content = $pdfService->generate(
            $headers,
            $records,
            $title,
            $highlightedIndexes,
            $alternateIndexes
        );
    } catch (Throwable $exception) {
        // Registra el error técnico sin exponer detalles internos.
        error_log($exception->__toString());

        http_response_code(500);
        exit('No fue posible generar la vista previa del PDF.');
    }

    // Envía el PDF al navegador para visualizarlo, no para descargarlo.
    header('Content-Type: application/pdf');
    header(
        'Content-Disposition: inline; filename="' . $filename . '"'
    );
    header('Content-Length: ' . strlen($content));
    header('Cache-Control: private, no-store, no-cache, must-revalidate');
    header('Pragma: no-cache');
    header('X-Content-Type-Options: nosniff');

    echo $content;
    exit;
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
                unset($_SESSION['selection_result']);
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
    header('Location: index.php?page=selection');
    exit;
}


/**
 * Carga de forma independiente el Excel A o el Excel B de la comparación.
 */

/**
 * Recibe y valida conjuntamente los dos Excel de la comparación.
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $action === 'upload_comparison_files'
) {
    // Solo los administradores autenticados pueden cargar archivos.
    if (!$isAuthenticated) {
        header('Location: index.php');
        exit;
    }

    $tokenRecibido = $_POST['csrf_token'] ?? '';

    // Verifica el token CSRF antes de procesar los archivos.
    if (
        !is_string($tokenRecibido)
        || !hash_equals($_SESSION['csrf_token'], $tokenRecibido)
    ) {
        $_SESSION['comparison_feedback'] = [
            'type' => 'error',
            'message' => 'La solicitud ha expirado. Recarga el panel e inténtalo nuevamente.',
        ];
    } else {
        $fileA = $_FILES['comparison_excel_a_file'] ?? null;
        $fileB = $_FILES['comparison_excel_b_file'] ?? null;

        // Identifica los archivos que no fueron seleccionados.
        $missingFiles = [];

        if (
            !is_array($fileA)
            || ($fileA['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
        ) {
            $missingFiles[] = 'Excel A';
        }

        if (
            !is_array($fileB)
            || ($fileB['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
        ) {
            $missingFiles[] = 'Excel B';
        }

        // No procesa ningún archivo si falta uno de los dos.
        if ($missingFiles !== []) {
            $_SESSION['comparison_feedback'] = [
                'type' => 'error',
                'message' => 'No has seleccionado: '
                    . implode(' y ', $missingFiles)
                    . '. Debes seleccionar ambos archivos.',
            ];
        } else {
            try {
                // Carga cada archivo mediante el controlador existente.
                $resultA = $excelComparisonController->upload($fileA, 'a');
                $resultB = $excelComparisonController->upload($fileB, 'b');

                if (
                    ($resultA['success'] ?? false) === true
                    && ($resultB['success'] ?? false) === true
                ) {
                    // Registra ambos archivos únicamente si las dos cargas funcionan.
                    $_SESSION['comparison_excel_a'] = $resultA['file'];
                    $_SESSION['comparison_excel_b'] = $resultB['file'];

                    // Invalida el resultado anterior al cambiar los archivos.
                    unset($_SESSION['comparison_result']);

                    $_SESSION['comparison_feedback'] = [
                        'type' => 'success',
                        'message' => 'Ambos archivos se cargaron correctamente. Estarán disponibles durante 15 minutos.',
                    ];
                } else {
                    // Evita comparar un archivo nuevo con otro anterior.
                    unset(
                        $_SESSION['comparison_excel_a'],
                        $_SESSION['comparison_excel_b'],
                        $_SESSION['comparison_result']
                    );

                    $errors = [];

                    foreach ([
                        'Excel A' => $resultA,
                        'Excel B' => $resultB,
                    ] as $label => $result) {
                        if (($result['success'] ?? false) !== true) {
                            foreach (($result['errors'] ?? []) as $error) {
                                $errors[] = $label . ': ' . $error;
                            }
                        }
                    }

                    $_SESSION['comparison_feedback'] = [
                        'type' => 'error',
                        'message' => implode(
                            ' ',
                            $errors ?: ['No fue posible cargar ambos archivos.']
                        ),
                    ];
                }
            } catch (Throwable $exception) {
                error_log($exception->__toString());

                unset(
                    $_SESSION['comparison_excel_a'],
                    $_SESSION['comparison_excel_b'],
                    $_SESSION['comparison_result']
                );

                $_SESSION['comparison_feedback'] = [
                    'type' => 'error',
                    'message' => 'Ocurrió un error al cargar los archivos. Comprueba los Excel e inténtalo nuevamente.',
                ];
            }
        }
    }

    // Evita reenviar el formulario al actualizar el navegador.
    header('Location: index.php?page=comparison');
    exit;
}


/**
 * Carga los dos archivos Excel destinados a la unión.
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $action === 'upload_merge_files'
) {
    // Solo los administradores autenticados pueden cargar archivos.
    if (!$isAuthenticated) {
        header('Location: index.php');
        exit;
    }

    // Verifica el token CSRF antes de procesar los archivos.
    $tokenRecibido = $_POST['csrf_token'] ?? '';

    if (
        !is_string($tokenRecibido)
        || !hash_equals($_SESSION['csrf_token'], $tokenRecibido)
    ) {
        $_SESSION['merge_feedback'] = [
            'type' => 'error',
            'message' => 'La solicitud ha expirado. Recarga el panel e inténtalo nuevamente.',
        ];
    } else {
        $fileA = $_FILES['merge_excel_a_file'] ?? null;
        $fileB = $_FILES['merge_excel_b_file'] ?? null;

        // Comprueba que se hayan seleccionado ambos archivos.
        $missingFiles = [];

        if (
            !is_array($fileA)
            || ($fileA['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
        ) {
            $missingFiles[] = 'Excel A';
        }

        if (
            !is_array($fileB)
            || ($fileB['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
        ) {
            $missingFiles[] = 'Excel B';
        }

        if ($missingFiles !== []) {
            $_SESSION['merge_feedback'] = [
                'type' => 'error',
                'message' => 'No has seleccionado: '
                    . implode(' y ', $missingFiles)
                    . '. Debes seleccionar ambos archivos.',
            ];
        } else {
            try {
                // Carga los archivos mediante el controlador específico de unión.
                $resultA = $excelMergeController->upload($fileA, 'a');
                $resultB = $excelMergeController->upload($fileB, 'b');

                if (
                    ($resultA['success'] ?? false) === true
                    && ($resultB['success'] ?? false) === true
                ) {
                    // Guarda los metadatos solo cuando ambas cargas funcionan.
                    $_SESSION['merge_excel_a'] = $resultA['file'];
                    $_SESSION['merge_excel_b'] = $resultB['file'];

                    // Invalida resultados anteriores para evitar descargar datos obsoletos.
                    unset($_SESSION['merge_result']);

                    $_SESSION['merge_feedback'] = [
                        'type' => 'success',
                        'message' => 'Ambos archivos se cargaron correctamente. Estarán disponibles durante 15 minutos.',
                    ];
                } else {
                    // Evita mezclar archivos nuevos con archivos anteriores.
                    unset(
                        $_SESSION['merge_excel_a'],
                        $_SESSION['merge_excel_b'],
                        $_SESSION['merge_result']
                    );

                    $errors = [];

                    foreach ([
                        'Excel A' => $resultA,
                        'Excel B' => $resultB,
                    ] as $label => $result) {
                        if (($result['success'] ?? false) !== true) {
                            foreach (($result['errors'] ?? []) as $error) {
                                $errors[] = $label . ': ' . $error;
                            }
                        }
                    }

                    $_SESSION['merge_feedback'] = [
                        'type' => 'error',
                        'message' => implode(
                            ' ',
                            $errors ?: ['No fue posible cargar ambos archivos.']
                        ),
                    ];
                }
            } catch (Throwable $exception) {
                error_log($exception->__toString());

                unset(
                    $_SESSION['merge_excel_a'],
                    $_SESSION['merge_excel_b'],
                    $_SESSION['merge_result']
                );

                $_SESSION['merge_feedback'] = [
                    'type' => 'error',
                    'message' => 'Ocurrió un error al cargar los archivos. Inténtalo nuevamente.',
                ];
            }
        }
    }

    // Aplica POST/Redirect/GET para evitar reenvíos accidentales.
    header('Location: index.php?page=merge');
    exit;
}


/**
 * Ejecuta la unión de los dos archivos Excel cargados.
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $action === 'execute_merge'
) {
    // Solo los administradores autenticados pueden ejecutar la unión.
    if (!$isAuthenticated) {
        header('Location: index.php');
        exit;
    }

    // Comprueba el token CSRF antes de procesar los archivos.
    $tokenRecibido = $_POST['csrf_token'] ?? '';

    if (
        !is_string($tokenRecibido)
        || !hash_equals($_SESSION['csrf_token'], $tokenRecibido)
    ) {
        unset($_SESSION['merge_result']);

        $_SESSION['merge_feedback'] = [
            'type' => 'error',
            'message' => 'La solicitud ha expirado. Recarga el panel e inténtalo nuevamente.',
        ];
    } else {
        $fileA = $_SESSION['merge_excel_a'] ?? null;
        $fileB = $_SESSION['merge_excel_b'] ?? null;
        $sheetA = $_POST['merge_sheet_a'] ?? '';
        $sheetB = $_POST['merge_sheet_b'] ?? '';

        // Comprueba que existan los archivos y las hojas seleccionadas.
        if (
            !is_array($fileA)
            || !is_array($fileB)
            || !is_string($sheetA)
            || trim($sheetA) === ''
            || !is_string($sheetB)
            || trim($sheetB) === ''
        ) {
            unset($_SESSION['merge_result']);

            $_SESSION['merge_feedback'] = [
                'type' => 'error',
                'message' => 'Debes cargar ambos archivos y seleccionar una hoja en cada uno.',
            ];
        } else {
            try {
                // Obtiene las hojas disponibles de cada archivo temporal.
                $sheetsA = $excelMergeController->getSheetNames($fileA);
                $sheetsB = $excelMergeController->getSheetNames($fileB);

                $validSheetA = ($sheetsA['success'] ?? false) === true
                    && in_array($sheetA, $sheetsA['sheets'], true);

                $validSheetB = ($sheetsB['success'] ?? false) === true
                    && in_array($sheetB, $sheetsB['sheets'], true);

                if (!$validSheetA || !$validSheetB) {
                    throw new RuntimeException(
                        'Una de las hojas seleccionadas no existe o su archivo temporal venció. Comprueba los archivos y vuelve a intentarlo.'
                    );
                }

                // Ejecuta la unión respetando las reglas definidas en el servicio.
                $result = $excelMergeController->merge(
                    $fileA,
                    $sheetA,
                    $fileB,
                    $sheetB
                );

                if (($result['success'] ?? false) === true) {
                    $_SESSION['merge_result'] = $result['result'];

                    $_SESSION['merge_feedback'] = [
                        'type' => 'success',
                        'message' => 'La unión de los archivos terminó correctamente.',
                    ];
                } else {
                    unset($_SESSION['merge_result']);

                    $_SESSION['merge_feedback'] = [
                        'type' => 'error',
                        'message' => implode(
                            ' ',
                            $result['errors'] ?? [
                                'No fue posible unir los archivos Excel.',
                            ]
                        ),
                    ];
                }
            } catch (Throwable $exception) {
                error_log($exception->__toString());

                unset($_SESSION['merge_result']);

                $_SESSION['merge_feedback'] = [
                    'type' => 'error',
                    'message' => $exception instanceof RuntimeException
                        ? $exception->getMessage()
                        : 'Ocurrió un error al unir los archivos Excel.',
                ];
            }
        }
    }

    // Evita repetir la operación al actualizar el navegador.
    header('Location: index.php?page=merge');
    exit;
}

/**
 * Compara los archivos A y B usando las hojas elegidas por el administrador.
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $action === 'execute_comparison'
) {
    if (!$isAuthenticated) {
        header('Location: index.php');
        exit;
    }

    $tokenRecibido = $_POST['csrf_token'] ?? '';

    if (
        !is_string($tokenRecibido)
        || !hash_equals($_SESSION['csrf_token'], $tokenRecibido)
    ) {
        unset($_SESSION['comparison_result']);

        $_SESSION['comparison_feedback'] = [
            'type' => 'error',
            'message' => 'La solicitud ha expirado. Recarga el panel e inténtalo nuevamente.',
        ];
    } else {
        $fileA = $_SESSION['comparison_excel_a'] ?? null;
        $fileB = $_SESSION['comparison_excel_b'] ?? null;
        $sheetA = $_POST['comparison_sheet_a'] ?? '';
        $sheetB = $_POST['comparison_sheet_b'] ?? '';

        if (
            !is_array($fileA)
            || !is_array($fileB)
            || !is_string($sheetA)
            || trim($sheetA) === ''
            || !is_string($sheetB)
            || trim($sheetB) === ''
        ) {
            unset($_SESSION['comparison_result']);

            $_SESSION['comparison_feedback'] = [
                'type' => 'error',
                'message' => 'Debes cargar ambos archivos y seleccionar una hoja en cada uno.',
            ];
        } else {
            $result = $excelComparisonController->compare(
                $fileA,
                $sheetA,
                $fileB,
                $sheetB
            );

            if ($result['success'] === true) {
                $_SESSION['comparison_result'] = $result['result'];

                $_SESSION['comparison_feedback'] = [
                    'type' => 'success',
                    'message' => 'La comparación terminó correctamente.',
                ];
            } else {
                unset($_SESSION['comparison_result']);

                $_SESSION['comparison_feedback'] = [
                    'type' => 'error',
                    'message' => implode(
                        ' ',
                        $result['errors'] ?? [
                            'No fue posible comparar los archivos.',
                        ]
                    ),
                ];
            }
        }
    }

    header('Location: index.php?page=comparison');
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

        // Descarta resultados anteriores al iniciar una nueva validación.
        unset($_SESSION['selection_result']);

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
    header('Location: index.php?page=selection');
    exit;
}


/**
 * Ejecuta la selección aleatoria sobre la hoja previamente validada.
 */
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && $action === 'execute_selection'
) {
    // Solo los administradores autenticados pueden ejecutar selecciones.
    if (!$isAuthenticated) {
        header('Location: index.php');
        exit;
    }

    $tokenRecibido = $_POST['csrf_token'] ?? '';

    // Comprueba el token CSRF antes de procesar los registros.
    if (
        !is_string($tokenRecibido)
        || !hash_equals($_SESSION['csrf_token'], $tokenRecibido)
    ) {
        $_SESSION['selection_result'] = [
            'success' => false,
            'errors' => [
                'La solicitud ha expirado. Recarga la página e inténtalo nuevamente.',
            ],
        ];
    } else {

        $uploadedExcel = $_SESSION['uploaded_excel'] ?? null;
        $sheetValidation = $_SESSION['sheet_validation'] ?? null;
        $sheetName = $_POST['sheet_name'] ?? '';

        // Recibe las cantidades de beneficiarios y suplentes.
        $beneficiaryInput = $_POST['beneficiary_quantity'] ?? null;
        $alternateInput = $_POST['alternate_quantity'] ?? null;

        // Verifica que el archivo, la hoja y ambas cantidades sean válidos.
        if (
            !is_array($uploadedExcel)
            || !is_array($sheetValidation)
            || !is_string($sheetName)
            || trim($sheetName) === ''
            || !is_string($beneficiaryInput)
            || $beneficiaryInput === ''
            || !ctype_digit($beneficiaryInput)
            || !is_string($alternateInput)
            || $alternateInput === ''
            || !ctype_digit($alternateInput)
        ) {
            $_SESSION['selection_result'] = [
                'success' => false,
                'errors' => [
                    'Debes validar una hoja e indicar cantidades enteras válidas para beneficiarios y suplentes.',
                ],
            ];
        } else {
            // Ejecuta el sorteo con las dos cantidades independientes.
            $_SESSION['selection_result'] = $selectionController->handle(
                $uploadedExcel,
                $sheetName,
                (int) $beneficiaryInput,
                (int) $alternateInput,
                $sheetValidation
            );
        }
    }

    // Redirige al panel para evitar repetir el envío al actualizar.
    header('Location: index.php?page=selection');
    exit;
}

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