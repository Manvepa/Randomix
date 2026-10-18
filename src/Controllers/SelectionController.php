<?php

declare(strict_types=1);

namespace Randomix\Controllers;

use Randomix\Import\ExcelReader;
use Randomix\Import\StudentRecordsProcessor;
use Randomix\Services\SelectionService;
use Randomix\Validators\ExcelHeadersValidator;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class SelectionController
{
    private string $temporaryDirectory;

    private ExcelReader $excelReader;

    private StudentRecordsProcessor $recordsProcessor;

    private SelectionService $selectionService;

    private ExcelHeadersValidator $headersValidator;

    /**
     * Inicializa los componentes necesarios para procesar el Excel
     * y seleccionar registros.
     */
    public function __construct(
        string $projectRoot,
        array $requiredHeaders
    ) {
        $this->temporaryDirectory = rtrim(
            $projectRoot,
            DIRECTORY_SEPARATOR
        ) . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'temporary';

        $this->excelReader = new ExcelReader();
        $this->recordsProcessor = new StudentRecordsProcessor();
        $this->selectionService = new SelectionService();

        $this->headersValidator = new ExcelHeadersValidator(
            $requiredHeaders
        );
    }

    /**
     * Procesa una hoja validada y separa los registros seleccionados
     * de los que no fueron seleccionados.
     */
    public function handle(
        array $uploadedExcel,
        string $sheetName,
        int $quantity,
        array $sheetValidation
    ): array {
        try {
            // Exige que la hoja haya superado la validación previa.
            if (
                ($sheetValidation['success'] ?? false) !== true
                || ($sheetValidation['valid'] ?? false) !== true
                || ($sheetValidation['sheet_name'] ?? null) !== $sheetName
            ) {
                throw new RuntimeException(
                    'Debes validar correctamente la hoja antes de seleccionar.'
                );
            }

            // Comprueba nuevamente que el archivo temporal siga vigente.
            $filePath = $this->getValidFilePath($uploadedExcel);

            // Revalida los encabezados del archivo que se va a procesar.
            $headers = $this->excelReader->getHeaders(
                $filePath,
                $sheetName
            );

            $validation = $this->headersValidator->validate($headers);

            if ($validation['valid'] !== true) {
                throw new RuntimeException(
                    'Los encabezados de la hoja ya no son válidos.'
                );
            }

            // Lee las filas y transforma sus valores en registros asociados
            // a los encabezados oficiales.
            $rows = $this->excelReader->getRows(
                $filePath,
                $sheetName
            );

            $records = $this->recordsProcessor->process(
                $headers,
                $rows
            );

            // Realiza la selección aleatoria sin reemplazo.
            $result = $this->selectionService->select(
                $records,
                $quantity
            );

            return [
                'success' => true,
                'errors' => [],
                'sheet_name' => $sheetName,
                'total_records' => count($records),
                'selected_count' => count($result['selected']),
                'not_selected_count' => count($result['not_selected']),
                'selected' => $result['selected'],
                'not_selected' => $result['not_selected'],
            ];
        } catch (InvalidArgumentException | RuntimeException $exception) {
            return [
                'success' => false,
                'errors' => [$exception->getMessage()],
            ];
        } catch (Throwable $exception) {
            // Registra los detalles técnicos sin exponerlos en el panel.
            error_log($exception->__toString());

            return [
                'success' => false,
                'errors' => [
                    'No fue posible procesar el Excel y realizar la selección.',
                ],
            ];
        }
    }

    /**
     * Verifica la vigencia, el nombre y la ubicación del archivo temporal.
     */
    private function getValidFilePath(array $uploadedExcel): string
    {
        $filename = $uploadedExcel['filename'] ?? null;
        $expiresAt = $uploadedExcel['expires_at'] ?? null;

        if (
            !is_string($filename)
            || !preg_match(
                '/^randomix_upload_[a-f0-9]{32}\.(xlsx|xls)$/i',
                $filename
            )
            || !is_int($expiresAt)
            || $expiresAt <= time()
        ) {
            throw new RuntimeException(
                'El archivo temporal no existe o ha vencido. Cárgalo nuevamente.'
            );
        }

        $filePath = $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . $filename;

        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new RuntimeException(
                'El archivo temporal no está disponible. Cárgalo nuevamente.'
            );
        }

        return $filePath;
    }
}
