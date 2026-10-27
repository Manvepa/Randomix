<?php

declare(strict_types=1);

namespace Randomix\Services;

use Randomix\Support\Database;
use PDO;
use RuntimeException;
use Throwable;

final class FileArtifactService
{
    private string $recordsDirectory;

    private PDO $connection;

    /**
     * Prepara el almacenamiento definitivo y recibe la conexión PDO.
     */
    public function __construct(
        string $projectRoot,
        ?PDO $connection = null
    ) {
        $this->recordsDirectory = rtrim(
            $projectRoot,
            DIRECTORY_SEPARATOR
        ) . DIRECTORY_SEPARATOR . 'storage'
            . DIRECTORY_SEPARATOR . 'records';

        $this->connection = $connection
            ?? Database::getConnection();
    }

    /**
     * Guarda contenido binario y registra sus metadatos.
     *
     * @return array{
     *     id: int,
     *     original_name: string,
     *     stored_name: string,
     *     relative_path: string,
     *     file_extension: string,
     *     mime_type: string,
     *     file_size: int,
     *     sha256: string
     * }
     */
    public function save(
        string $content,
        string $originalName,
        string $extension,
        string $mimeType
    ): array {
        // Valida los datos que identifican al archivo.
        $originalName = basename(trim($originalName));
        $extension = strtolower(ltrim(trim($extension), '.'));
        $mimeType = trim($mimeType);

        if ($content === '') {
            throw new RuntimeException(
                'No se puede guardar un archivo vacío.'
            );
        }

        if (
            $originalName === ''
            || strlen($originalName) > 255
            || preg_match('/[\x00-\x1F\x7F]/', $originalName) === 1
        ) {
            throw new RuntimeException(
                'El nombre original del archivo no es válido.'
            );
        }

        if (
            !in_array($extension, ['xls', 'xlsx'], true)
            || strlen($extension) > 10
        ) {
            throw new RuntimeException(
                'La extensión del archivo no es válida.'
            );
        }

        if ($mimeType === '' || strlen($mimeType) > 100) {
            throw new RuntimeException(
                'El tipo MIME del archivo no es válido.'
            );
        }

        // Crea el almacenamiento definitivo si todavía no existe.
        if (
            !is_dir($this->recordsDirectory)
            && !mkdir($this->recordsDirectory, 0700, true)
            && !is_dir($this->recordsDirectory)
        ) {
            throw new RuntimeException(
                'No fue posible crear el almacenamiento definitivo.'
            );
        }

        if (!is_writable($this->recordsDirectory)) {
            throw new RuntimeException(
                'El almacenamiento definitivo no permite guardar archivos.'
            );
        }

        // Genera un nombre interno único, independiente del nombre original.
        $storedName = 'randomix_'
            . bin2hex(random_bytes(16))
            . '.'
            . $extension;

        $absolutePath = $this->recordsDirectory
            . DIRECTORY_SEPARATOR
            . $storedName;

        // La ruta de la base de datos es relativa a storage/records/.
        $relativePath = $storedName;

        // Escribe el contenido binario sin alterar los bytes del archivo.
        $bytesWritten = file_put_contents(
            $absolutePath,
            $content,
            LOCK_EX
        );

        if ($bytesWritten === false || $bytesWritten !== strlen($content)) {
            if (is_file($absolutePath) && !@unlink($absolutePath)) {
                error_log(
                    'Randomix: no fue posible limpiar el archivo '
                    . 'que no se pudo guardar completamente: '
                    . $storedName
                );
            }

            throw new RuntimeException(
                'No fue posible guardar el archivo definitivo.'
            );
        }

        // Indica si la inserción en la base de datos terminó correctamente.
        $registroInsertado = false;

        try {
            // Calcula los metadatos usando el archivo que quedó en disco.
            $fileSize = filesize($absolutePath);
            $sha256 = hash_file('sha256', $absolutePath);

            if ($fileSize === false || $sha256 === false) {
                throw new RuntimeException(
                    'No fue posible calcular los metadatos del archivo.'
                );
            }

            // Registra el archivo conforme al esquema file_artifacts.
            $statement = $this->connection->prepare(
                'INSERT INTO file_artifacts (
                    original_name,
                    stored_name,
                    relative_path,
                    file_extension,
                    mime_type,
                    file_size,
                    sha256
                ) VALUES (
                    :original_name,
                    :stored_name,
                    :relative_path,
                    :file_extension,
                    :mime_type,
                    :file_size,
                    :sha256
                )'
            );

            $statement->execute([
                'original_name' => $originalName,
                'stored_name' => $storedName,
                'relative_path' => $relativePath,
                'file_extension' => $extension,
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
                'sha256' => $sha256,
            ]);

            // La inserción terminó: el archivo ya tiene un registro.
            $registroInsertado = true;

            $id = (int) $this->connection->lastInsertId();

            if ($id <= 0) {
                throw new RuntimeException(
                    'El archivo se registró, pero no se obtuvo su identificador.'
                );
            }

            return [
                'id' => $id,
                'original_name' => $originalName,
                'stored_name' => $storedName,
                'relative_path' => $relativePath,
                'file_extension' => $extension,
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
                'sha256' => $sha256,
            ];
        } catch (Throwable $exception) {
            // Solo elimina el archivo si no se insertó su registro.
            if (!$registroInsertado) {
                if (is_file($absolutePath) && !@unlink($absolutePath)) {
                    error_log(
                        'Randomix: no fue posible limpiar un archivo '
                        . 'definitivo cuyo registro falló: '
                        . $storedName
                    );
                }
            }

            // Evita borrar un archivo que ya tiene registro en la base de datos.
            throw new RuntimeException(
                $registroInsertado
                    ? 'El archivo se guardó, pero ocurrió un error al finalizar la operación.'
                    : 'No fue posible registrar el archivo en la base de datos.',
                0,
                $exception
            );
        }
    }


    /**
     * Recupera el contenido de un artefacto previamente registrado.
     *
     * Solo permite leer archivos ubicados directamente en storage/records.
     */
    public function readContent(int $artifactId): string
    {
        if ($artifactId <= 0) {
            throw new RuntimeException(
                'El identificador del archivo no es válido.'
            );
        }

        // Busca la ruta del artefacto registrado.
        $statement = $this->connection->prepare(
            'SELECT relative_path
             FROM file_artifacts
             WHERE id = :id
             LIMIT 1'
        );

        $statement->execute(['id' => $artifactId]);
        $relativePath = $statement->fetchColumn();

        if (!is_string($relativePath) || $relativePath === '') {
            throw new RuntimeException(
                'No se encontró el archivo solicitado.'
            );
        }

        // Rechaza rutas que puedan salir del directorio de almacenamiento.
        if (
            basename($relativePath) !== $relativePath
            || str_contains($relativePath, '..')
            || str_contains($relativePath, '/')
            || str_contains($relativePath, '\\')
        ) {
            throw new RuntimeException(
                'La ruta del archivo registrado no es válida.'
            );
        }

        $absolutePath = $this->recordsDirectory
            . DIRECTORY_SEPARATOR
            . $relativePath;

        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            throw new RuntimeException(
                'El archivo registrado no está disponible para lectura.'
            );
        }

        // Devuelve el contenido binario sin modificarlo.
        $content = file_get_contents($absolutePath);

        if ($content === false || $content === '') {
            throw new RuntimeException(
                'No fue posible leer el archivo registrado.'
            );
        }

        return $content;
    }

}
