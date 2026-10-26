<?php

declare(strict_types=1);

namespace Randomix\Controllers;

use Randomix\Services\FileArtifactService;
use Randomix\Validators\FileUploadValidator;
use RuntimeException;
use Throwable;

final class UploadController
{
    private string $temporaryDirectory;

    private FileArtifactService $fileArtifactService;

    private int $retentionSeconds = 900;

    /**
     * Configura el directorio temporal y el servicio de almacenamiento permanente.
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

        $projectRoot = rtrim($projectRoot, DIRECTORY_SEPARATOR);

        $this->temporaryDirectory = $projectRoot
            . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'temporary';

        $this->fileArtifactService = new FileArtifactService(
            $projectRoot
        );
    }

    /**
     * Valida el Excel, conserva la copia temporal y registra una copia permanente.
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

        // Crea el directorio temporal si todavía no existe.
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

        // Genera un nombre impredecible para el procesamiento temporal.
        $storedFilename = 'randomix_upload_'
            . bin2hex(random_bytes(16))
            . '.'
            . $extension;

        $destination = $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . $storedFilename;

        // Mueve el archivo desde el directorio temporal administrado por PHP.
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new RuntimeException(
                'No fue posible guardar el archivo recibido.'
            );
        }

        try {
            // Lee la copia temporal para guardar el archivo permanente.
            $content = file_get_contents($destination);

            if ($content === false || $content === '') {
                throw new RuntimeException(
                    'No fue posible leer el archivo recibido.'
                );
            }

            // Detecta el MIME a partir del contenido, no del nombre del archivo.
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $detectedMime = $finfo->file($destination);

            $mimeType = is_string($detectedMime) && $detectedMime !== ''
                ? $detectedMime
                : 'application/octet-stream';

            // Guarda el original en storage/records y registra sus metadatos.
            $artifact = $this->fileArtifactService->save(
                $content,
                basename($file['name']),
                $extension,
                $mimeType
            );

            return [
                'success' => true,
                'stored_filename' => $storedFilename,
                'original_name' => basename($file['name']),
                'artifact_id' => $artifact['id'],
            ];
        } catch (Throwable $exception) {
            // Si falla el registro permanente, elimina la copia temporal creada.
            if (is_file($destination) && !@unlink($destination)) {
                error_log(
                    'Randomix: no fue posible eliminar la copia temporal '
                    . 'tras fallar el almacenamiento permanente.'
                );
            }

            throw new RuntimeException(
                'No fue posible completar el almacenamiento del archivo.',
                0,
                $exception
            );
        }
    }

    /**
     * Elimina archivos temporales de Randomix que superaron los 15 minutos.
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
