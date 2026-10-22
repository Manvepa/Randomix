<?php

declare(strict_types=1);

namespace Randomix\Controllers;

use Randomix\Import\ExcelReader;
use Randomix\Services\ExcelComparisonService;
use Randomix\Validators\FileUploadValidator;
use RuntimeException;
use Throwable;

final class ExcelComparisonController
{
    private string $temporaryDirectory;

    private UploadController $uploadController;

    private ExcelReader $excelReader;

    private ExcelComparisonService $comparisonService;

    /**
     * Configura los componentes necesarios para la comparación.
     */
    public function __construct(
        string $projectRoot,
        int $maxFileSize,
        array $requiredHeaders,
        int $maxRows = 20000
    ) {
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

        $this->comparisonService = new ExcelComparisonService(
            $requiredHeaders,
            $this->excelReader,
            $maxRows
        );
    }

    /**
     * Recibe un Excel A o B y devuelve sus metadatos temporales.
     *
     * El parámetro $slot identifica el propósito del archivo.
     * Solo se admiten los valores "a" y "b".
     */
    public function upload(array $file, string $slot): array
    {
        if (!in_array($slot, ['a', 'b'], true)) {
            return [
                'success' => false,
                'errors' => ['El tipo de archivo de comparación no es válido.'],
            ];
        }

        $result = $this->uploadController->handle($file);

        if (!$result['success']) {
            return $result;
        }

        $now = time();

        return [
            'success' => true,
            'file' => [
                'filename' => $result['stored_filename'],
                'original_name' => $result['original_name'],
                'uploaded_at' => $now,
                'expires_at' => $now + 900,
            ],
        ];
    }

    /**
     * Obtiene las hojas de un archivo temporal vigente.
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
            return [
                'success' => false,
                'sheets' => [],
                'errors' => [$exception->getMessage()],
            ];
        }
    }

    /**
     * Compara los dos archivos sin modificar los originales.
     */
    public function compare(
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

            $result = $this->comparisonService->compare(
                $pathA,
                $sheetA,
                $pathB,
                $sheetB
            );

            return [
                'success' => true,
                'result' => $result,
                'errors' => [],
            ];
        } catch (Throwable $exception) {
            return [
                'success' => false,
                'result' => null,
                'errors' => [$exception->getMessage()],
            ];
        }
    }

    /**
     * Valida los metadatos y la vigencia del archivo antes de leerlo.
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
