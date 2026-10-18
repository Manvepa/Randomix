<?php

declare(strict_types=1);

namespace Randomix\Controllers;

use Randomix\Validators\FileUploadValidator;
use RuntimeException;

final class UploadController
{
    private string $temporaryDirectory;

    private int $retentionSeconds = 900;

    /**
     * Configura el directorio privado y el límite de carga.
     */
    public function __construct(
        string $projectRoot,
        private readonly int $maxFileSize
    ) {
        if ($this->maxFileSize <= 0) {
            throw new RuntimeException(
                'El límite de carga no es válido.'
            );
        }

        $this->temporaryDirectory = rtrim(
            $projectRoot,
            DIRECTORY_SEPARATOR
        ) . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'temporary';
    }

    /**
     * Valida el archivo recibido y lo guarda con un nombre aleatorio.
     */
    public function handle(array $file): array
    {
        // Rechaza estructuras de archivo inesperadas.
        if (
            !isset(
                $file['name'],
                $file['tmp_name'],
                $file['error'],
                $file['size']
            )
            || !is_string($file['name'])
            || !is_string($file['tmp_name'])
            || !is_int($file['error'])
            || !is_int($file['size'])
        ) {
            return [
                'success' => false,
                'errors' => [
                    'La información recibida del archivo no es válida.',
                ],
            ];
        }

        // Reutiliza el validador existente del proyecto.
        $validator = new FileUploadValidator(
            $this->maxFileSize
        );

        $validation = $validator->validateUploadedFile($file);

        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors'],
            ];
        }

        // Conserva únicamente una extensión previamente validada.
        $extension = strtolower(
            pathinfo($file['name'], PATHINFO_EXTENSION)
        );

        // Crea el directorio privado si todavía no existe.
        if (
            !is_dir($this->temporaryDirectory)
            && !mkdir($this->temporaryDirectory, 0700, true)
            && !is_dir($this->temporaryDirectory)
        ) {
            throw new RuntimeException(
                'No fue posible preparar el almacenamiento temporal.'
            );
        }

        if (!is_writable($this->temporaryDirectory)) {
            throw new RuntimeException(
                'El almacenamiento temporal no permite guardar archivos.'
            );
        }

        // Genera un nombre impredecible sin conservar el nombre original.
        $storedFilename = 'randomix_upload_'
            . bin2hex(random_bytes(16))
            . '.'
            . $extension;

        $destination = $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . $storedFilename;

        // Mueve el archivo desde el directorio temporal de PHP.
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new RuntimeException(
                'No fue posible guardar el archivo recibido.'
            );
        }

        return [
            'success' => true,
            'stored_filename' => $storedFilename,
            'original_name' => basename($file['name']),
        ];
    }

    /**
     * Elimina archivos de Randomix que superaron los 15 minutos.
     */
    public function cleanupExpiredFiles(): void
    {
        if (!is_dir($this->temporaryDirectory)) {
            return;
        }

        $files = scandir($this->temporaryDirectory);

        if ($files === false) {
            error_log(
                'Randomix: no fue posible revisar los archivos temporales.'
            );

            return;
        }

        $expirationTime = time() - $this->retentionSeconds;

        foreach ($files as $filename) {
            // Solo administra archivos creados con el patrón de Randomix.
            if (
                !preg_match(
                    '/^randomix_upload_[a-f0-9]{32}\.(xlsx|xls)$/i',
                    $filename
                )
            ) {
                continue;
            }

            $path = $this->temporaryDirectory
                . DIRECTORY_SEPARATOR
                . $filename;

            if (!is_file($path)) {
                continue;
            }

            $modifiedAt = filemtime($path);

            if (
                $modifiedAt !== false
                && $modifiedAt <= $expirationTime
                && !unlink($path)
            ) {
                error_log(
                    'Randomix: no fue posible eliminar un archivo temporal vencido.'
                );
            }
        }
    }
}
