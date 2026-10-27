<?php

declare(strict_types=1);

namespace Randomix\Controllers;

use RuntimeException;
use Throwable;
use Randomix\Services\ComparisonPersistenceService;
use Randomix\Services\SelectionPersistenceService;
use Randomix\Support\Database;
use Randomix\Services\ExcelExportService;
use Randomix\Services\FileArtifactService;

final class PostActionController
{
    public function __construct(
        private readonly UploadController $uploadController,
        private readonly ExcelInspectionController $excelInspectionController,
        private readonly SelectionController $selectionController,
        private readonly ExcelComparisonController $excelComparisonController,
        private readonly ExcelMergeController $excelMergeController,
        private readonly ComparisonPersistenceService $comparisonPersistenceService,
        private readonly SelectionPersistenceService $selectionPersistenceService,
        private readonly ExcelExportService $excelExportService,
        private readonly FileArtifactService $fileArtifactService
    ) {
    }


    /**
     * Procesa las acciones POST que ya fueron migradas.
     *
     * Devuelve false cuando la acción todavía pertenece a public/index.php.
     */
    public function handle(
        string $method,
        mixed $action,
        bool $isAuthenticated
    ): bool {
        if ($method !== 'POST' || !is_string($action)) {
            return false;
        }

        switch ($action) {
            case 'upload_excel':
                $this->uploadExcel($isAuthenticated);
                return true;

            case 'upload_comparison_files':
                $this->uploadComparisonFiles($isAuthenticated);
                return true;

            case 'upload_merge_files':
                $this->uploadMergeFiles($isAuthenticated);
                return true;

            case 'execute_merge':
                $this->executeMerge($isAuthenticated);
                return true;

            case 'execute_comparison':
                $this->executeComparison($isAuthenticated);
                return true;

            case 'validate_sheet':
                $this->validateSheet($isAuthenticated);
                return true;

            case 'execute_selection':
                $this->executeSelection($isAuthenticated);
                return true;

            default:
                return false;
        }
    }


    /**
     * Gestiona la carga del Excel para el proceso de selección.
     */
    private function uploadExcel(bool $isAuthenticated): void
    {
        if (!$isAuthenticated) {
            header('Location: index.php');
            exit;
        }

        $tokenRecibido = $_POST['csrf_token'] ?? '';

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
                    $result = $this->uploadController->handle($file);
                }

                if ($result['success'] === true) {
                    unset($_SESSION['sheet_validation']);
                    unset($_SESSION['selection_result']);

                    $now = time();

                    $_SESSION['uploaded_excel'] = [
                        'filename' => $result['stored_filename'],
                        'original_name' => $result['original_name'],
                        'artifact_id' => $result['artifact_id'],
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
                error_log($exception->__toString());

                $_SESSION['upload_feedback'] = [
                    'type' => 'error',
                    'message' => 'No fue posible guardar el archivo. Inténtalo nuevamente.',
                ];
            }
        }

        // Conserva el destino y el patrón POST/Redirect/GET actuales.
        header('Location: index.php?page=selection');
        exit;
    }


    /**
     * Recibe y valida conjuntamente los dos Excel de la comparación.
     */
    private function uploadComparisonFiles(bool $isAuthenticated): void
    {
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
                    // Reutiliza el controlador especializado de comparación.
                    $resultA = $this->excelComparisonController->upload(
                        $fileA,
                        'a'
                    );

                    $resultB = $this->excelComparisonController->upload(
                        $fileB,
                        'b'
                    );

                    if (
                        ($resultA['success'] ?? false) === true
                        && ($resultB['success'] ?? false) === true
                    ) {
                        // Registra ambos archivos solo si las dos cargas funcionan.
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
                                $errors ?: [
                                    'No fue posible cargar ambos archivos.',
                                ]
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

        // Conserva la redirección actual para evitar reenvíos del formulario.
        header('Location: index.php?page=comparison');
        exit;
    }


    /**
     * Recibe y valida conjuntamente los dos Excel destinados a la unión.
     */
    private function uploadMergeFiles(bool $isAuthenticated): void
    {
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
                    $resultA = $this->excelMergeController->upload($fileA, 'a');
                    $resultB = $this->excelMergeController->upload($fileB, 'b');

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
                                $errors ?: [
                                    'No fue posible cargar ambos archivos.',
                                ]
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

        // Conserva la redirección original y evita reenviar el formulario.
        header('Location: index.php?page=merge');
        exit;
    }


    /**
     * Ejecuta la unión de los dos archivos Excel cargados.
     */
    private function executeMerge(bool $isAuthenticated): void
    {
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
                    $sheetsA = $this->excelMergeController->getSheetNames($fileA);
                    $sheetsB = $this->excelMergeController->getSheetNames($fileB);

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
                    $result = $this->excelMergeController->merge(
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
    private function executeComparison(bool $isAuthenticated): void
    {
        // Solo los administradores autenticados pueden ejecutar la comparación.
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


            // Recibe los años enviados por el formulario.
            $previousYearInput = $_POST['comparison_previous_year'] ?? null;
            $currentYearInput = $_POST['comparison_current_year'] ?? null;

            // Conserva los valores para volver a mostrarlos si hay errores.
            if (is_string($previousYearInput)) {
                $_SESSION['comparison_previous_year'] = $previousYearInput;
            }

            if (is_string($currentYearInput)) {
                $_SESSION['comparison_current_year'] = $currentYearInput;
            }

            // Valida el formato, el rango y el orden de los años.
            $validYears = is_string($previousYearInput)
                && ctype_digit($previousYearInput)
                && (int) $previousYearInput >= 1900
                && (int) $previousYearInput <= 2200
                && is_string($currentYearInput)
                && ctype_digit($currentYearInput)
                && (int) $currentYearInput >= 1900
                && (int) $currentYearInput <= 2200
                && (int) $currentYearInput > (int) $previousYearInput;

            // Comprueba que existan los archivos y las hojas seleccionadas.

            if (
                !$validYears
                || !is_array($fileA)
                || !is_array($fileB)
                || !is_string($sheetA)
                || trim($sheetA) === ''
                || !is_string($sheetB)
                || trim($sheetB) === ''
            ) {

                unset($_SESSION['comparison_result']);

                $_SESSION['comparison_feedback'] = [
                    'type' => 'error',
                    'message' => 'Debes indicar años válidos (el año actual debe ser posterior al anterior), cargar ambos archivos y seleccionar una hoja en cada uno.',
                ];
            } else {
                // Ejecuta la comparación mediante el controlador especializado.
                $result = $this->excelComparisonController->compare(
                    $fileA,
                    $sheetA,
                    $fileB,
                    $sheetB
                );

                if ($result['success'] === true) {
                    // Primero persiste la comparación y sus resultados.
                    // Si esta operación falla, se informa el error de guardado.
                    try {
                        // Obtiene y valida el identificador del administrador autenticado.
                        $adminId = $_SESSION['admin']['id'] ?? null;

                        if (
                            (!is_int($adminId) && !is_string($adminId))
                            || !ctype_digit((string) $adminId)
                            || (int) $adminId <= 0
                        ) {
                            throw new RuntimeException(
                                'No existe un identificador administrativo válido en la sesión.'
                            );
                        }

                        // Persiste la comparación utilizando un identificador validado.
                        $persistenceResult = $this->comparisonPersistenceService->persist(
                            $result['result'],
                            (int) $previousYearInput,
                            (int) $currentYearInput,
                            (int) $adminId
                        );
                    } catch (Throwable $exception) {
                        error_log(
                            '[Randomix][Error al persistir comparación] '
                            . $exception->__toString()
                        );

                        unset($_SESSION['comparison_result']);

                        $_SESSION['comparison_feedback'] = [
                            'type' => 'error',
                            'message' => 'La comparación se procesó, pero no fue posible guardar sus resultados en la base de datos.',
                        ];

                        header('Location: index.php?page=comparison');
                        exit;
                    }


                    // Registra únicamente metadatos técnicos de la persistencia.
                    // No escribe nombres, documentos, direcciones ni otros datos personales.
                    try {
                        error_log(
                            '[Randomix][ComparacionPersistida] '
                            . json_encode(
                                [
                                    'comparison_id' => $persistenceResult['comparison_id'],
                                    'dataset_id' => $persistenceResult['dataset_id'],
                                    'version_number' => $persistenceResult['version_number'],
                                    'row_count' => $persistenceResult['row_count'],
                                ],
                                JSON_UNESCAPED_UNICODE
                                | JSON_UNESCAPED_SLASHES
                                | JSON_THROW_ON_ERROR
                            )
                        );
                    } catch (Throwable $exception) {
                        error_log(
                            '[Randomix][Error al registrar metadatos de comparación] '
                            . get_class($exception)
                        );
                    }


                    // La comparación guardada se conserva disponible para mostrar el resultado.
                    $_SESSION['comparison_result'] = $result['result'];
                    $_SESSION['comparison_previous_year'] = (int) $previousYearInput;
                    $_SESSION['comparison_current_year'] = (int) $currentYearInput;

                    $_SESSION['comparison_feedback'] = [
                        'type' => 'success',
                        'message' => 'La comparación terminó correctamente y sus resultados se guardaron en la base de datos.',
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

        // Conserva la redirección original para evitar reenviar el formulario.
        header('Location: index.php?page=comparison');
        exit;
    }


    /**
     * Valida la hoja seleccionada y sus encabezados para la selección aleatoria.
     */
    private function validateSheet(bool $isAuthenticated): void
    {
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
                // Delega la validación al controlador especializado.
                $_SESSION['sheet_validation'] =
                    $this->excelInspectionController->validateSheet(
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
    private function executeSelection(bool $isAuthenticated): void
    {
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
                    'La solicitud ha expirado. Recarga el panel e inténtalo nuevamente.',
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
                $result = $this->selectionController->handle(
                    $uploadedExcel,
                    $sheetName,
                    (int) $beneficiaryInput,
                    (int) $alternateInput,
                    $sheetValidation
                );

                // Persiste los resultados únicamente si el sorteo fue exitoso.
                if (($result['success'] ?? false) === true) {
                    try {
                        // Obtiene los IDs de los tres Excel generados.
                        $artifactIds = $this->saveSelectionArtifacts($result);

                        // Registra el historial asociado al Excel original.
                        $runId = $this->selectionPersistenceService->persist([
                            'source_file_id' => (int) $uploadedExcel['artifact_id'],
                            'selected_file_id' => $artifactIds['selected_file_id'],
                            'not_selected_file_id' => $artifactIds['not_selected_file_id'],
                            'alternates_file_id' => $artifactIds['alternates_file_id'],
                            'sheet_name' => $result['sheet_name'],
                            'requested_beneficiaries' => (int) $beneficiaryInput,
                            'requested_alternates' => (int) $alternateInput,
                            'total_records' => $result['total_records'],
                            'beneficiaries_count' => $result['beneficiaries_count'],
                            'alternates_count' => $result['alternates_count'],
                            'not_selected_count' => $result['not_selected_count'],
                        ]);

                        // Conserva los IDs para reutilizarlos en las descargas.
                        $result['artifact_ids'] = $artifactIds;
                        $result['selection_run_id'] = $runId;
                    } catch (Throwable $exception) {
                        error_log(
                            'Randomix: no fue posible persistir el sorteo. '
                            . $exception->__toString()
                        );

                        // No presenta un sorteo como completado si falló su persistencia.
                        $result = [
                            'success' => false,
                            'errors' => [
                                'El sorteo se realizó, pero no fue posible guardar su historial. Inténtalo nuevamente.',
                            ],
                        ];
                    }
                }

                $_SESSION['selection_result'] = $result;

            }
        }

        // Redirige al panel para evitar repetir el envío al actualizar.
        header('Location: index.php?page=selection');
        exit;
    }


    /**
     * Genera y registra los tres archivos Excel de una selección.
     *
     * Devuelve los identificadores necesarios para guardar el historial.
     *
     * @return array{
     *     selected_file_id: int,
     *     not_selected_file_id: int,
     *     alternates_file_id: int
     * }
     */
    private function saveSelectionArtifacts(array $result): array
    {
        // Obtiene los grupos producidos por el sorteo.
        $selectedRecords = $result['selected'];
        $notSelectedRecords = $result['not_selected'];
        $alternateRecords = $result['alternates'];

        // Determina los encabezados, incluso si algún grupo está vacío.
        $allRecords = array_merge(
            $selectedRecords,
            $notSelectedRecords,
            $alternateRecords
        );

        if ($allRecords === [] || !is_array($allRecords[0])) {
            throw new RuntimeException(
                'No fue posible determinar los encabezados del sorteo.'
            );
        }

        $headers = array_keys($allRecords[0]);

        // Calcula los índices de suplentes dentro del grupo seleccionado.
        $alternateIndexes = [];
        $beneficiariesCount = count($result['beneficiaries']);
        $alternatesCount = count($alternateRecords);

        for (
            $index = $beneficiariesCount;
            $index < $beneficiariesCount + $alternatesCount;
            $index++
        ) {
            $alternateIndexes[] = $index;
        }

        // Conserva los resaltados utilizados por la exportación actual.
        $highlightedIndexes =
            $result['selected_matching_surname_indexes'] ?? [];

        // Genera los tres archivos en memoria con el formato existente.
        $selectedContent = $this->excelExportService->generate(
            $headers,
            $selectedRecords,
            $highlightedIndexes,
            $alternateIndexes
        );

        $notSelectedContent = $this->excelExportService->generate(
            $headers,
            $notSelectedRecords
        );

        $alternatesContent = $this->excelExportService->generate(
            $headers,
            $alternateRecords
        );

        $mimeType =
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

        // Guarda los archivos y recoge sus identificadores de la base de datos.
        $selectedArtifact = $this->fileArtifactService->save(
            $selectedContent,
            'estudiantes_seleccionados.xlsx',
            'xlsx',
            $mimeType
        );

        $notSelectedArtifact = $this->fileArtifactService->save(
            $notSelectedContent,
            'estudiantes_no_seleccionados.xlsx',
            'xlsx',
            $mimeType
        );

        $alternatesArtifact = $this->fileArtifactService->save(
            $alternatesContent,
            'estudiantes_suplentes.xlsx',
            'xlsx',
            $mimeType
        );

        return [
            'selected_file_id' => $selectedArtifact['id'],
            'not_selected_file_id' => $notSelectedArtifact['id'],
            'alternates_file_id' => $alternatesArtifact['id'],
        ];
    }


}
