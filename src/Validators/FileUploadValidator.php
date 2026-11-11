<?php

declare(strict_types=1);

namespace Randomix\Validators;

use finfo;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

final class FileUploadValidator
{
    /**
     * Recibe los límites y los MIME permitidos desde la configuración.
     *
     * @param array<string, list<string>> $allowedMimeTypes
     */
    public function __construct(
        private readonly int $maxFileSize,
        private readonly array $allowedMimeTypes
    ) {
        if ($this->maxFileSize <= 0) {
            throw new \InvalidArgumentException(
                'El tamaño máximo debe ser mayor que cero.'
            );
        }
    }

    /**
     * Valida un archivo local sin exigir que proceda de una petición HTTP.
     *
     * Este método es útil para pruebas automatizadas y validaciones internas.
     * Para archivos recibidos desde formularios debe utilizarse
     * validateUploadedFile().
     *
     * No mueve, modifica ni elimina el archivo.
     *
     * @return array{
     *     valid: bool,
     *     errors: list<string>,
     *     extension?: string,
     *     mime_type?: string,
     *     size?: int,
     *     sha256?: string,
     *     detected_format?: string
     * }
     */
    public function validate(array $file): array
    {
        return $this->validateFile($file, false);
    }

    /**
     * Valida un archivo recibido mediante una carga HTTP real de PHP.
     *
     * is_uploaded_file() evita que una ruta local manipulada se presente
     * como si fuera un archivo recibido mediante $_FILES.
     */
    public function validateUploadedFile(array $file): array
    {
        return $this->validateFile($file, true);
    }

    /**
     * Ejecuta las validaciones compartidas.
     */
    private function validateFile(array $file, bool $requireHttpUpload): array
    {
        // Comprueba que PHP haya proporcionado todos los campos esperados.
        foreach (['name', 'tmp_name', 'error', 'size'] as $field) {
            if (!array_key_exists($field, $file)) {
                return $this->failure(
                    'La información recibida del archivo está incompleta.'
                );
            }
        }

        // Traduce primero los errores de transporte informados por PHP.
        if (!is_int($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return $this->failure(
                $this->getUploadErrorMessage($file['error'])
            );
        }

        // Comprueba el nombre original sin confiar en él para crear rutas.
        if (
            !is_string($file['name'])
            || trim($file['name']) === ''
            || preg_match('/[\x00-\x1F\x7F]/', $file['name']) === 1
        ) {
            return $this->failure(
                'El nombre original del archivo no es válido.'
            );
        }

        // Solo se admiten los dos formatos Excel definidos por Randomix.
        $extension = strtolower(
            pathinfo($file['name'], PATHINFO_EXTENSION)
        );

        if (!in_array($extension, ['xlsx', 'xls'], true)) {
            return $this->failure(
                'El archivo debe tener extensión .xlsx o .xls.'
            );
        }

        // Comprueba el tamaño informado por PHP.
        if (!is_int($file['size']) || $file['size'] <= 0) {
            return $this->failure(
                'El archivo está vacío o tiene un tamaño inválido.'
            );
        }

        if ($file['size'] > $this->maxFileSize) {
            return $this->failure(
                'El archivo supera el límite permitido de 20 MB.'
            );
        }

        // Comprueba la ruta temporal y que el archivo pueda leerse.
        if (
            !is_string($file['tmp_name'])
            || $file['tmp_name'] === ''
            || !is_file($file['tmp_name'])
            || !is_readable($file['tmp_name'])
        ) {
            return $this->failure(
                'No se encuentra el archivo temporal o no se puede leer.'
            );
        }

        // Para las cargas web, exige que PHP reconozca el origen HTTP.
        if (
            $requireHttpUpload
            && !is_uploaded_file($file['tmp_name'])
        ) {
            return $this->failure(
                'El archivo no proviene de una carga HTTP válida.'
            );
        }

        // Contrasta el tamaño informado con el tamaño físico del temporal.
        $actualSize = filesize($file['tmp_name']);

        if ($actualSize === false || $actualSize <= 0) {
            return $this->failure(
                'No se pudo leer el archivo o está vacío.'
            );
        }

        if ($actualSize !== $file['size']) {
            return $this->failure(
                'El tamaño real del archivo no coincide con el informado.'
            );
        }

        if ($actualSize > $this->maxFileSize) {
            return $this->failure(
                'El archivo supera el límite permitido de 20 MB.'
            );
        }

        // Comprueba que Fileinfo esté disponible; si no, falla de forma segura.
        if (!class_exists(finfo::class)) {
            return $this->failure(
                'El servidor no tiene disponible la herramienta de inspección MIME.'
            );
        }

        // Detecta el MIME a partir del contenido binario, no del navegador.
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $detectedMime = $finfo->file($file['tmp_name']);

        if (!is_string($detectedMime) || $detectedMime === '') {
            return $this->failure(
                'No fue posible identificar el contenido del archivo.'
            );
        }

        $detectedMime = strtolower(trim($detectedMime));

        // Comprueba que el MIME detectado esté permitido para la extensión.
        $expectedMimes = $this->allowedMimeTypes[$extension] ?? [];

        if (
            !is_array($expectedMimes)
            || $expectedMimes === []
            || !in_array($detectedMime, $expectedMimes, true)
        ) {
            return $this->failure(
                'El contenido del archivo no corresponde a su extensión.'
            );
        }

        /*
         * finfo no es suficiente para demostrar que un ZIP sea XLSX ni que
         * un contenedor OLE sea XLS. PhpSpreadsheet identifica el formato
         * real y rechaza archivos que no reconoce como libros Excel.
         */
        try {
            $detectedFormat = IOFactory::identify($file['tmp_name']);
        } catch (Throwable) {
            return $this->failure(
                'El archivo no es un libro Excel válido o está dañado.'
            );
        }

        $expectedFormat = match ($extension) {
            'xlsx' => 'Xlsx',
            'xls' => 'Xls',
            default => null,
        };

        if ($detectedFormat !== $expectedFormat) {
            return $this->failure(
                'El formato real del archivo no coincide con su extensión.'
            );
        }

        // Calcula SHA-256 para verificar integridad y reconocer duplicados.
        $sha256 = hash_file('sha256', $file['tmp_name']);

        if (!is_string($sha256)) {
            return $this->failure(
                'No fue posible calcular la huella de integridad del archivo.'
            );
        }

        return [
            'valid' => true,
            'errors' => [],
            'extension' => $extension,
            'mime_type' => $detectedMime,
            'size' => $actualSize,
            'sha256' => $sha256,
            'detected_format' => $detectedFormat,
        ];
    }

    /**
     * Traduce los códigos UPLOAD_ERR_* a mensajes comprensibles.
     */
    private function getUploadErrorMessage(mixed $error): string
    {
        if (!is_int($error)) {
            return 'El código de carga recibido por PHP no es válido.';
        }

        return match ($error) {
            UPLOAD_ERR_OK =>
                'La carga del archivo terminó correctamente.',

            UPLOAD_ERR_NO_FILE =>
                'Debes seleccionar un archivo Excel.',

            UPLOAD_ERR_INI_SIZE,
            UPLOAD_ERR_FORM_SIZE =>
                'El archivo supera el tamaño máximo permitido por el servidor.',

            UPLOAD_ERR_PARTIAL =>
                'El archivo se recibió parcialmente. Intenta cargarlo de nuevo.',

            UPLOAD_ERR_NO_TMP_DIR =>
                'El servidor no tiene disponible el directorio temporal.',

            UPLOAD_ERR_CANT_WRITE =>
                'El servidor no pudo guardar temporalmente el archivo.',

            UPLOAD_ERR_EXTENSION =>
                'Una extensión de PHP impidió cargar el archivo.',

            default =>
                'Ocurrió un error desconocido al recibir el archivo.',
        };
    }

    /**
     * Estandariza la respuesta cuando una validación falla.
     *
     * @return array{valid: false, errors: list<string>}
     */
    private function failure(string $message): array
    {
        return [
            'valid' => false,
            'errors' => [$message],
        ];
    }
}
