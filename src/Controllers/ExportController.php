<?php

declare(strict_types=1);

namespace Randomix\Controllers;

use Randomix\Services\ExcelExportService;
use Randomix\Services\PdfPreviewService;
use Throwable;

final class ExportController
{
    private ExcelExportService $excelExportService;
    private PdfPreviewService $pdfPreviewService;

    /**
     * Recibe los servicios responsables de generar los archivos.
     */
    public function __construct(
        ?ExcelExportService $excelExportService = null,
        ?PdfPreviewService $pdfPreviewService = null
    ) {
        $this->excelExportService = $excelExportService
            ?? new ExcelExportService();

        $this->pdfPreviewService = $pdfPreviewService
            ?? new PdfPreviewService();
    }

    /**
     * Procesa las solicitudes de exportación antes de renderizar HTML.
     *
     * Devuelve true cuando la solicitud corresponde a una exportación.
     * Las respuestas se envían directamente al navegador y finalizan
     * la ejecución, igual que en el flujo actual.
     */
    public function handle(
        string $method,
        array $get,
        array $post,
        bool $isAuthenticated
    ): bool {
        if ($method === 'POST' && ($post['action'] ?? null) === 'export_merge') {
            $this->exportMerge($post, $isAuthenticated);
            return true;
        }

        if ($method === 'POST' && ($post['action'] ?? null) === 'export_comparison') {
            $this->exportComparison($post, $isAuthenticated);
            return true;
        }

        if ($method === 'GET' && isset($get['export'])) {
            $this->exportSelectionExcel($get['export'], $isAuthenticated);
            return true;
        }

        if ($method === 'GET' && isset($get['pdf'])) {
            $this->exportSelectionPdf($get['pdf'], $isAuthenticated);
            return true;
        }

        return false;
    }

    /**
     * Verifica que exista un administrador autenticado.
     */
    private function requireAuthentication(bool $isAuthenticated): void
    {
        if (!$isAuthenticated) {
            header('Location: index.php');
            exit;
        }
    }

    /**
     * Comprueba el token CSRF de una solicitud POST.
     */
    private function validateCsrf(array $post): void
    {
        $tokenRecibido = $post['csrf_token'] ?? '';
        $tokenSesion = $_SESSION['csrf_token'] ?? null;

        if (
            !is_string($tokenRecibido)
            || !is_string($tokenSesion)
            || !hash_equals($tokenSesion, $tokenRecibido)
        ) {
            http_response_code(403);
            exit(
                'La solicitud ha expirado. Recarga el panel e inténtalo nuevamente.'
            );
        }
    }

    /**
     * Envía un archivo Excel como descarga.
     */
    private function sendExcel(string $content, string $filename): void
    {
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
     * Exporta el resultado de la unión de los archivos Excel.
     */
    private function exportMerge(array $post, bool $isAuthenticated): void
    {
        $this->requireAuthentication($isAuthenticated);
        $this->validateCsrf($post);

        $mergeResult = $_SESSION['merge_result'] ?? null;

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
            $content = $this->excelExportService->generate(
                $mergeResult['headers'],
                $mergeResult['records'],
                [],
                [],
                true
            );
        } catch (Throwable $exception) {
            error_log($exception->__toString());
            http_response_code(500);
            exit('No fue posible generar el Excel C.');
        }

        $this->sendExcel($content, 'Excel_C_unificado.xlsx');
    }

    /**
     * Exporta el resultado de la comparación entre Excel A y Excel B.
     */
    private function exportComparison(
        array $post,
        bool $isAuthenticated
    ): void {
        $this->requireAuthentication($isAuthenticated);
        $this->validateCsrf($post);

        $comparisonResult = $_SESSION['comparison_result'] ?? null;

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
            $content = $this->excelExportService->generate(
                $comparisonResult['headers'],
                $comparisonResult['records']
            );
        } catch (Throwable $exception) {
            error_log($exception->__toString());
            http_response_code(500);
            exit('No fue posible generar el Excel de la comparación.');
        }

        $this->sendExcel($content, 'Actualizacion_Estudiantes.xlsx');
    }

    /**
     * Exporta el grupo seleccionado en formato Excel.
     */
    private function exportSelectionExcel(
        mixed $exportType,
        bool $isAuthenticated
    ): void {
        $this->requireAuthentication($isAuthenticated);

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

        if (
            !is_array($selectionResult)
            || ($selectionResult['success'] ?? false) !== true
        ) {
            http_response_code(404);
            exit('No hay resultados disponibles para exportar.');
        }

        $selectedRecords = $selectionResult['selected'] ?? null;
        $notSelectedRecords = $selectionResult['not_selected'] ?? null;
        $alternateRecords = $selectionResult['alternates'] ?? null;

        if (
            !is_array($selectedRecords)
            || !is_array($notSelectedRecords)
            || !is_array($alternateRecords)
        ) {
            http_response_code(500);
            exit('Los resultados de la selección no tienen una estructura válida.');
        }

        // Obtiene las columnas incluso cuando el grupo solicitado está vacío.
        $allRecords = array_merge($selectedRecords, $notSelectedRecords);

        if ($allRecords === [] || !is_array($allRecords[0])) {
            http_response_code(500);
            exit('No fue posible determinar los encabezados del Excel.');
        }

        $headers = array_keys($allRecords[0]);

        $records = match ($exportType) {
            'selected' => $selectedRecords,
            'not_selected' => $notSelectedRecords,
            'alternates' => $alternateRecords,
        };

        // Conserva el resaltado de coincidencias de apellidos.
        $highlightedIndexes = $exportType === 'selected'
            ? ($selectionResult['selected_matching_surname_indexes'] ?? [])
            : [];

        // Calcula los índices de suplentes dentro del grupo seleccionado.
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
            $content = $this->excelExportService->generate(
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

        $filename = match ($exportType) {
            'selected' => 'estudiantes_seleccionados.xlsx',
            'not_selected' => 'estudiantes_no_seleccionados.xlsx',
            'alternates' => 'estudiantes_suplentes.xlsx',
        };

        $this->sendExcel($content, $filename);
    }

    /**
     * Genera la vista previa PDF del grupo de selección solicitado.
     */
    private function exportSelectionPdf(
        mixed $pdfType,
        bool $isAuthenticated
    ): void {
        $this->requireAuthentication($isAuthenticated);

        if (
            !is_string($pdfType)
            || !in_array(
                $pdfType,
                ['selected', 'not_selected', 'alternates'],
                true
            )
        ) {
            http_response_code(400);
            exit('Tipo de PDF no válido.');
        }

        $selectionResult = $_SESSION['selection_result'] ?? null;

        if (
            !is_array($selectionResult)
            || ($selectionResult['success'] ?? false) !== true
        ) {
            http_response_code(404);
            exit('No hay resultados disponibles para generar el PDF.');
        }

        $selectedRecords = $selectionResult['selected'] ?? null;
        $notSelectedRecords = $selectionResult['not_selected'] ?? null;
        $alternateRecords = $selectionResult['alternates'] ?? null;

        if (
            !is_array($selectedRecords)
            || !is_array($notSelectedRecords)
            || !is_array($alternateRecords)
        ) {
            http_response_code(500);
            exit('Los resultados de la selección no tienen una estructura válida.');
        }

        // Conserva los encabezados incluso si el grupo no tiene registros.
        $allRecords = array_merge(
            $selectedRecords,
            $notSelectedRecords,
            $alternateRecords
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

        $records = match ($pdfType) {
            'selected' => $selectedRecords,
            'not_selected' => $notSelectedRecords,
            'alternates' => $alternateRecords,
        };

        $highlightedIndexes = $pdfType === 'selected'
            ? ($selectionResult['selected_matching_surname_indexes'] ?? [])
            : [];

        // Identifica los suplentes para conservar el resaltado actual.
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

        $title = match ($pdfType) {
            'selected' => 'Estudiantes seleccionados',
            'not_selected' => 'Estudiantes no seleccionados',
            'alternates' => 'Suplentes',
        };

        $filename = match ($pdfType) {
            'selected' => 'estudiantes_seleccionados.pdf',
            'not_selected' => 'estudiantes_no_seleccionados.pdf',
            'alternates' => 'estudiantes_suplentes.pdf',
        };

        try {
            $content = $this->pdfPreviewService->generate(
                $headers,
                $records,
                $title,
                $highlightedIndexes,
                $alternateIndexes
            );
        } catch (Throwable $exception) {
            error_log($exception->__toString());
            http_response_code(500);
            exit('No fue posible generar la vista previa del PDF.');
        }

        // El PDF se muestra en el navegador, no se descarga automáticamente.
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($content));
        header('Cache-Control: private, no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('X-Content-Type-Options: nosniff');

        echo $content;
        exit;
    }
}
