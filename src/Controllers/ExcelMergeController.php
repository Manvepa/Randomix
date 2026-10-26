<?php

declare(strict_types=1);

namespace Randomix\Controllers;

use Randomix\Import\ExcelReader;
use Randomix\Services\ExcelMergeService;
use RuntimeException;
use Throwable;

final class ExcelMergeController
{
    private string $temporaryDirectory;

    private UploadController $uploadController;

    private ExcelReader $excelReader;

    private ExcelMergeService $mergeService;

    /**
     * Prepara los componentes necesarios para unir dos archivos Excel.
     */
    public function __construct(
        string $projectRoot,
        int $maxFileSize,
        array $requiredHeaders,
        int $maxRows = 20000
    ) {
        if ($maxRows <= 0 || $maxRows > 20000) {
            throw new RuntimeException(
                'El límite de filas por archivo no es válido.'
            );
        }

        $this->temporaryDirectory = rtrim(
            $projectRoot,
            DIRECTORY_SEPARATOR
        ) . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'temporary';

        $this->uploadController = new UploadController(
            $projectRoot,
            $maxFileSize
        );

        $this->excelReader = new ExcelReader();

        $this->mergeService = new ExcelMergeService(
            $requiredHeaders
        );
    }

    /**
     * Recibe un Excel A o B y devuelve sus metadatos temporales.
     */
    public function upload(array $file, string $slot): array
    {
        if (!in_array($slot, ['a', 'b'], true)) {
            return [
                'success' => false,
                'errors' => [
                    'El tipo de archivo para unir no es válido.',
                ],
            ];
        }

        $result = $this->uploadController->handle($file);

        if (($result['success'] ?? false) !== true) {
            return $result;
        }

        $now = time();


        return [
            'success' => true,
            'file' => [
                'filename' => $result['stored_filename'],
                'original_name' => $result['original_name'],
                'artifact_id' => $result['artifact_id'],
                'uploaded_at' => $now,
                'expires_at' => $now + 900,
            ],
            'errors' => [],
        ];

    }

    /**
     * Obtiene los nombres de las hojas de un archivo temporal vigente.
     */
    public function getSheetNames(array $uploadedFile): array
    {
        try {
            $path = $this->getValidFilePath($uploadedFile);

            return [
                'success' => true,
                'sheets' => $this->excelReader->getSheetNames($path),
                'errors' => [],
            ];
        } catch (Throwable $exception) {
            error_log($exception->__toString());

            return [
                'success' => false,
                'sheets' => [],
                'errors' => [
                    'No fue posible obtener las hojas del archivo Excel.',
                ],
            ];
        }
    }

    /**
     * Lee las hojas seleccionadas y ejecuta la unión de ambos archivos.
     */
    public function merge(
        array $uploadedFileA,
        string $sheetA,
        array $uploadedFileB,
        string $sheetB
    ): array {
        try {
            $pathA = $this->getValidFilePath($uploadedFileA);
            $pathB = $this->getValidFilePath($uploadedFileB);

            if (trim($sheetA) === '' || trim($sheetB) === '') {
                throw new RuntimeException(
                    'Debes seleccionar una hoja en cada archivo.'
                );
            }

            // Lee los encabezados y las filas de las hojas seleccionadas.
            $headersA = $this->excelReader->getHeaders(
                $pathA,
                $sheetA
            );

            $rowsA = $this->excelReader->getRows(
                $pathA,
                $sheetA
            );

            $headersB = $this->excelReader->getHeaders(
                $pathB,
                $sheetB
            );

            $rowsB = $this->excelReader->getRows(
                $pathB,
                $sheetB
            );

            // Une los datos respetando el esquema y las reglas de duplicados.
            $result = $this->mergeService->merge(
                $headersA,
                $rowsA,
                $headersB,
                $rowsB
            );

            return [
                'success' => true,
                'result' => $result,
                'errors' => [],
            ];
        } catch (Throwable $exception) {
            error_log($exception->__toString());

            return [
                'success' => false,
                'result' => null,
                'errors' => [
                    $exception instanceof RuntimeException
                    ? $exception->getMessage()
                    : 'No fue posible unir los archivos Excel.',
                ],
            ];
        }
    }

    /**
     * Verifica el nombre, vencimiento y existencia del archivo temporal.
     */
    private function getValidFilePath(array $uploadedFile): string
    {
        $filename = $uploadedFile['filename'] ?? null;
        $expiresAt = $uploadedFile['expires_at'] ?? null;

        if (
            !is_string($filename)
            || !preg_match(
                '/^randomix_upload_[a-f0-9]{32}\.(xlsx|xls)$/i',
                $filename
            )
            || !is_int($expiresAt)
        ) {
            throw new RuntimeException(
                'Los datos del archivo temporal no son válidos.'
            );
        }

        if ($expiresAt <= time()) {
            throw new RuntimeException(
                'El archivo temporal ha vencido. Debes cargarlo nuevamente.'
            );
        }

        $path = $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . $filename;

        if (!is_file($path) || !is_readable($path)) {
            throw new RuntimeException(
                'El archivo temporal no existe o no se puede leer.'
            );
        }

        return $path;
    }
}
