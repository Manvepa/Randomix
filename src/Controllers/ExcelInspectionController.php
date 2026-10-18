<?php

declare(strict_types=1);

namespace Randomix\Controllers;

use Randomix\Import\ExcelReader;
use Randomix\Validators\ExcelHeadersValidator;
use RuntimeException;

final class ExcelInspectionController
{
    private string $temporaryDirectory;

    private ExcelReader $excelReader;

    private ExcelHeadersValidator $headersValidator;

    /**
     * Prepara el lector y el validador con la configuración oficial.
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

        $this->headersValidator = new ExcelHeadersValidator(
            $requiredHeaders
        );
    }

    /**
     * Obtiene las hojas de un archivo temporal vigente.
     */
    public function getSheetNames(array $uploadedExcel): array
    {
        try {
            $filePath = $this->getValidFilePath($uploadedExcel);

            return [
                'success' => true,
                'sheets' => $this->excelReader->getSheetNames(
                    $filePath
                ),
                'errors' => [],
            ];
        } catch (\Throwable $exception) {
            error_log($exception->__toString());

            return [
                'success' => false,
                'sheets' => [],
                'errors' => [
                    'No fue posible leer las hojas del archivo Excel.',
                ],
            ];
        }
    }

    /**
     * Comprueba los encabezados de la hoja seleccionada.
     */
    public function validateSheet(
        array $uploadedExcel,
        string $sheetName
    ): array {
        try {
            $filePath = $this->getValidFilePath($uploadedExcel);

            // Confirma que la hoja solicitada exista en el archivo.
            $availableSheets = $this->excelReader->getSheetNames(
                $filePath
            );

            if (!in_array($sheetName, $availableSheets, true)) {
                return [
                    'success' => false,
                    'valid' => false,
                    'errors' => [
                        'La hoja seleccionada no pertenece al archivo.',
                    ],
                ];
            }

            // Obtiene los encabezados y ejecuta la validación oficial.
            $headers = $this->excelReader->getHeaders(
                $filePath,
                $sheetName
            );

            $validation = $this->headersValidator->validate(
                $headers
            );

            return [
                'success' => true,
                'valid' => $validation['valid'],
                'sheet_name' => $sheetName,
                'headers' => $headers,
                'missing' => $validation['missing'],
                'extra' => $validation['extra'],
                'duplicates' => $validation['duplicates'],
                'errors' => [],
            ];
        } catch (\Throwable $exception) {
            error_log($exception->__toString());

            return [
                'success' => false,
                'valid' => false,
                'errors' => [
                    'No fue posible validar la hoja seleccionada.',
                ],
            ];
        }
    }

    /**
     * Verifica la vigencia y la ubicación del archivo temporal.
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
                'El archivo temporal no existe o ha vencido.'
            );
        }

        $filePath = $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . $filename;

        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new RuntimeException(
                'El archivo temporal no está disponible.'
            );
        }

        return $filePath;
    }
}
