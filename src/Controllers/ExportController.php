<?php

declare(strict_types=1);

namespace Randomix\Controllers;

use Randomix\Services\ExcelExportService;
use Randomix\Services\PdfPreviewService;
use Randomix\Services\FileArtifactService;
use Throwable;

final class ExportController
{
    private ExcelExportService $excelExportService;
    private PdfPreviewService $pdfPreviewService;
    private FileArtifactService $fileArtifactService;

    /**
     * Recibe los servicios responsables de generar y guardar los archivos.
     */
    public function __construct(
        ?ExcelExportService $excelExportService = null,
        ?PdfPreviewService $pdfPreviewService = null,
        ?FileArtifactService $fileArtifactService = null
    ) {
        $this->excelExportService = $excelExportService
            ?? new ExcelExportService();

        $this->pdfPreviewService = $pdfPreviewService
            ?? new PdfPreviewService();

        $this->fileArtifactService = $fileArtifactService
            ?? new FileArtifactService(dirname(__DIR__, 2));
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

        // Descarga un archivo de salida registrado en el historial del Dashboard.
        if ($method === 'GET' && array_key_exists('download_artifact', $get)) {
            $this->downloadDashboardArtifact(
                $get['download_artifact'],
                $isAuthenticated
            );

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
     * Guarda permanentemente un Excel generado.
     *
     * Si falla el almacenamiento, registra el error y evita que la
     * descarga se presente como una operación completada correctamente.
     */
    private function saveGeneratedExcel(
        string $content,
        string $filename
    ): bool {
        try {
            $this->fileArtifactService->save(
                $content,
                basename($filename),
                'xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
            );

            return true;
        } catch (Throwable $exception) {
            error_log(
                'Randomix: no fue posible guardar el Excel generado '
                . basename($filename)
                . '. '
                . $exception->__toString()
            );

            return false;
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
     * Descarga el Excel de una unión previamente ejecutada.
     *
     * Recupera el artefacto existente sin volver a generar ni guardar el archivo.
     */
    private function exportMerge(array $post, bool $isAuthenticated): void
    {
        // Verifica autenticación y protección CSRF.
        $this->requireAuthentication($isAuthenticated);
        $this->validateCsrf($post);

        // Recupera el identificador del Excel guardado al ejecutar la unión.
        $artifactId = $_SESSION['merge_result_artifact_id'] ?? null;

        if (!is_int($artifactId) || $artifactId <= 0) {
            http_response_code(404);
            exit(
                'No hay un Excel de unión guardado disponible. '
                . 'Ejecuta nuevamente la unión.'
            );
        }

        try {
            // Lee el archivo registrado, sin crear otro artefacto.
            $content = $this->fileArtifactService->readContent($artifactId);
        } catch (Throwable $exception) {
            error_log(
                '[Randomix][Error al descargar unión] '
                . $exception->__toString()
            );

            http_response_code(500);
            exit('No fue posible recuperar el Excel de la unión.');
        }

        // Descarga el mismo archivo generado durante la ejecución.
        $this->sendExcel($content, 'Excel_C_unificado.xlsx');
    }



    /**
     * Descarga el Excel de una comparación previamente ejecutada.
     *
     * Recupera el artefacto existente sin volver a generar ni guardar el archivo.
     */
    private function exportComparison(
        array $post,
        bool $isAuthenticated
    ): void {
        // Verifica autenticación y protección CSRF.
        $this->requireAuthentication($isAuthenticated);
        $this->validateCsrf($post);

        // Recupera el identificador del Excel guardado al ejecutar la comparación.
        $artifactId = $_SESSION['comparison_result_artifact_id'] ?? null;

        if (!is_int($artifactId) || $artifactId <= 0) {
            http_response_code(404);
            exit(
                'No hay un Excel de comparación guardado disponible. '
                . 'Ejecuta nuevamente la comparación.'
            );
        }

        try {
            // Lee el archivo registrado, sin crear otro artefacto.
            $content = $this->fileArtifactService->readContent($artifactId);
        } catch (Throwable $exception) {
            error_log(
                '[Randomix][Error al descargar comparación] '
                . $exception->__toString()
            );

            http_response_code(500);
            exit('No fue posible recuperar el Excel de la comparación.');
        }

        // Descarga el contenido existente.
        $this->sendExcel(
            $content,
            'Actualizacion_Estudiantes.xlsx'
        );
    }


    /**
     * Exporta el grupo seleccionado en formato Excel.
     */

    /**
     * Descarga el Excel generado y registrado al finalizar el sorteo.
     *
     * No vuelve a generar ni guardar los archivos de selección.
     */
    private function exportSelectionExcel(
        mixed $exportType,
        bool $isAuthenticated
    ): void {
        // Verifica que el usuario tenga autorización para exportar.
        $this->requireAuthentication($isAuthenticated);

        // Valida el tipo de archivo solicitado.
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

        // Recupera los resultados del sorteo almacenados en la sesión.
        $selectionResult = $_SESSION['selection_result'] ?? null;

        if (
            !is_array($selectionResult)
            || ($selectionResult['success'] ?? false) !== true
        ) {
            http_response_code(404);
            exit('No hay resultados disponibles para exportar.');
        }

        // Relaciona cada tipo de exportación con el artefacto registrado.
        $artifactKeyByExportType = [
            'selected' => 'selected_file_id',
            'not_selected' => 'not_selected_file_id',
            'alternates' => 'alternates_file_id',
        ];

        $filenameByExportType = [
            'selected' => 'estudiantes_seleccionados.xlsx',
            'not_selected' => 'estudiantes_no_seleccionados.xlsx',
            'alternates' => 'estudiantes_suplentes.xlsx',
        ];

        $artifactIds = $selectionResult['artifact_ids'] ?? [];
        $artifactKey = $artifactKeyByExportType[$exportType];
        $artifactId = (int) ($artifactIds[$artifactKey] ?? 0);
        $filename = $filenameByExportType[$exportType];

        // Comprueba que el sorteo tenga registrado el archivo solicitado.
        if ($artifactId <= 0) {
            error_log(
                'Randomix: falta el identificador del artefacto '
                . 'para la exportación "' . $exportType . '".'
            );

            http_response_code(500);
            exit(
                'No se encontró el archivo generado para este sorteo. '
                . 'Ejecuta nuevamente el sorteo.'
            );
        }

        try {
            // Lee el archivo existente desde storage/records.
            $content = $this->fileArtifactService->readContent($artifactId);
        } catch (Throwable $exception) {
            error_log(
                'Randomix: no fue posible recuperar el Excel del sorteo. '
                . $exception->getMessage()
            );

            http_response_code(500);
            exit('No fue posible recuperar el archivo generado.');
        }

        // Descarga el contenido existente sin crear otro archivo ni registro.
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


    /**
     * Descarga un archivo de salida desde el historial del Dashboard.
     *
     * Solo permite acceder a administradores autenticados y a archivos
     * registrados como resultados de operaciones de Randomix.
     */
    private function downloadDashboardArtifact(
        mixed $artifactId,
        bool $isAuthenticated
    ): void {
        // Comprueba que el usuario haya iniciado sesión.
        $this->requireAuthentication($isAuthenticated);

        // Este endpoint está reservado para el Dashboard administrativo.
        if ((int) ($_SESSION['admin']['rol'] ?? 0) !== 1) {
            http_response_code(403);
            exit('No tienes permisos para descargar este archivo.');
        }

        // Valida el identificador recibido en la URL.
        if (
            !is_string($artifactId)
            || !ctype_digit($artifactId)
            || (int) $artifactId <= 0
        ) {
            http_response_code(400);
            exit('El identificador del archivo no es válido.');
        }

        $id = (int) $artifactId;

        try {
            // Impide descargar archivos originales que no sean resultados.
            if (!$this->fileArtifactService->isOperationOutput($id)) {
                http_response_code(404);
                exit('No se encontró un archivo de salida para descargar.');
            }

            // Recupera el contenido del artefacto guardado.
            $content = $this->fileArtifactService->readContent($id);
        } catch (Throwable $exception) {
            error_log(
                '[Randomix][Descarga desde Dashboard] '
                . $exception->__toString()
            );

            http_response_code(500);
            exit('No fue posible recuperar el archivo solicitado.');
        }

        // Utiliza un nombre fijo para evitar problemas con cabeceras HTTP.
        $this->sendExcel(
            $content,
            'resultado_randomix_' . $id . '.xlsx'
        );
    }

}
