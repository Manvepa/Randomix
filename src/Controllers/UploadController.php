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

    private FileUploadValidator $fileUploadValidator;

    private int $retentionSeconds = 900;

    /**
     * Configura el almacenamiento temporal, el validador y el servicio
     * encargado de registrar los archivos originales.
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

        $projectRoot = rtrim(
            $projectRoot,
            DIRECTORY_SEPARATOR
        );

        // Define el directorio temporal administrado por Randomix.
        $this->temporaryDirectory = $projectRoot
            . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'temporary';

        // Carga la configuración oficial de formatos y MIME permitidos.
        $importConfigPath = $projectRoot
            . DIRECTORY_SEPARATOR . 'config'
            . DIRECTORY_SEPARATOR . 'import.php';

        if (!is_file($importConfigPath) || !is_readable($importConfigPath)) {
            throw new RuntimeException(
                'No se encontró la configuración de importación de Randomix.'
            );
        }

        $importConfig = require $importConfigPath;
        $allowedMimeTypes = $importConfig['allowed_mime_types'] ?? null;

        if (!is_array($allowedMimeTypes)) {
            throw new RuntimeException(
                'La configuración de tipos MIME permitidos no es válida.'
            );
        }

        // Prepara el validador compartido por todos los formularios de carga.
        $this->fileUploadValidator = new FileUploadValidator(
            $this->maxFileSize,
            $allowedMimeTypes
        );

        // Conserva el servicio existente para registrar los archivos originales.
        $this->fileArtifactService = new FileArtifactService(
            $projectRoot
        );
    }

    /**
     * Valida el Excel, conserva una copia temporal y registra el original.
     *
     * Este método es el único punto de entrada para archivos recibidos
     * mediante los formularios de Randomix.
     *
     * @return array{
     *     success: bool,
     *     errors?: list<string>,
     *     stored_filename?: string,
     *     original_name?: string,
     *     artifact_id?: int,
     *     sha256?: string
     * }
     */
    public function handle(array $file): array
    {
        // Valida el origen HTTP, tamaño, extensión, MIME y formato real.
        $validation = $this->fileUploadValidator->validateUploadedFile($file);

        if (!$validation['valid']) {
            return [
                'success' => false,
                'errors' => $validation['errors'],
            ];
        }

        // Obtiene exclusivamente los metadatos calculados por el validador.
        $extension = $validation['extension'];
        $mimeType = $validation['mime_type'];
        $expectedSize = $validation['size'];
        $expectedHash = $validation['sha256'];

        // Reduce el nombre recibido a un nombre base, sin rutas.
        $originalName = basename(
            str_replace('\\', '/', trim($file['name']))
        );

        if ($originalName === '') {
            return [
                'success' => false,
                'errors' => [
                    'El nombre original del archivo no es válido.',
                ],
            ];
        }

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

        // Genera un nombre interno aleatorio para evitar colisiones y rutas manipuladas.
        $storedFilename = 'randomix_upload_'
            . bin2hex(random_bytes(16))
            . '.'
            . $extension;

        $destination = $this->temporaryDirectory
            . DIRECTORY_SEPARATOR
            . $storedFilename;

        // Mueve el archivo únicamente después de superar todas las validaciones.
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new RuntimeException(
                'No fue posible guardar el archivo recibido.'
            );
        }

        try {
            // Comprueba que el archivo almacenado conserve el tamaño original.
            $storedSize = filesize($destination);

            if ($storedSize === false || $storedSize !== $expectedSize) {
                throw new RuntimeException(
                    'El tamaño del archivo cambió durante su almacenamiento.'
                );
            }

            // Comprueba nuevamente el SHA-256 después del movimiento.
            $storedHash = hash_file('sha256', $destination);

            if (
                !is_string($storedHash)
                || !hash_equals($expectedHash, $storedHash)
            ) {
                throw new RuntimeException(
                    'La integridad del archivo no pudo verificarse después de guardarlo.'
                );
            }

            /*
             * FileArtifactService actualmente recibe el contenido binario.
             * Se conserva esa interfaz para no alterar el almacenamiento,
             * la base de datos ni los controladores que consumen artefactos.
             */
            $content = file_get_contents($destination);

            if ($content === false || $content === '') {
                throw new RuntimeException(
                    'No fue posible leer el archivo recibido.'
                );
            }

            // Guarda el original en storage/records y registra sus metadatos.
            $artifact = $this->fileArtifactService->save(
                $content,
                $originalName,
                $extension,
                $mimeType,
                $expectedHash
            );

            if (
                !isset($artifact['id'])
                || !is_numeric($artifact['id'])
                || (int) $artifact['id'] <= 0
            ) {
                throw new RuntimeException(
                    'El archivo se guardó, pero no se obtuvo un identificador válido.'
                );
            }

            return [
                'success' => true,
                'stored_filename' => $storedFilename,
                'original_name' => $originalName,
                'artifact_id' => (int) $artifact['id'],
                'sha256' => $storedHash,
            ];
        } catch (Throwable $exception) {
            // Elimina la copia temporal si falla el registro del original.
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
