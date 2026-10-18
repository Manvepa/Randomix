<?php

declare(strict_types=1);

namespace Randomix\Validators;

final class FileUploadValidator
{
    /**
     * Recibe el tamaño máximo permitido desde la configuración.
     */
    public function __construct(
        private readonly int $maxFileSize
    ) {
        if ($this->maxFileSize <= 0) {
            throw new \InvalidArgumentException(
                'El tamaño máximo debe ser mayor que cero.'
            );
        }
    }

    /**
     * Valida la información del archivo recibido mediante PHP.
     *
     * Devuelve los errores encontrados sin mover ni procesar el archivo.
     */
    public function validate(array $file): array
    {
        $errors = [];

        // Comprueba que PHP haya recibido los datos básicos del archivo.
        foreach (['name', 'tmp_name', 'error', 'size'] as $field) {
            if (!array_key_exists($field, $file)) {
                $errors[] = 'La información recibida del archivo está incompleta.';
                break;
            }
        }

        if ($errors !== []) {
            return [
                'valid' => false,
                'errors' => $errors,
            ];
        }

        // Verifica el resultado de la operación de carga de PHP.
        if (
            !is_int($file['error'])
            || $file['error'] !== UPLOAD_ERR_OK
        ) {
            $errors[] = match ($file['error']) {
                UPLOAD_ERR_INI_SIZE,
                UPLOAD_ERR_FORM_SIZE =>
                    'El archivo supera el tamaño permitido para la carga.',
                UPLOAD_ERR_PARTIAL =>
                    'El archivo se recibió parcialmente. Intenta cargarlo de nuevo.',
                UPLOAD_ERR_NO_FILE =>
                    'Debes seleccionar un archivo Excel.',
                UPLOAD_ERR_NO_TMP_DIR =>
                    'El servidor no tiene disponible el directorio temporal.',
                UPLOAD_ERR_CANT_WRITE =>
                    'El servidor no pudo guardar temporalmente el archivo.',
                UPLOAD_ERR_EXTENSION =>
                    'Una extensión de PHP impidió cargar el archivo.',
                default =>
                    'Ocurrió un error al recibir el archivo.',
            };

            return [
                'valid' => false,
                'errors' => $errors,
            ];
        }

        // Comprueba el nombre y permite únicamente formatos Excel admitidos.
        $fileName = trim((string) $file['name']);
        $extension = strtolower(
            pathinfo($fileName, PATHINFO_EXTENSION)
        );

        if (
            $fileName === ''
            || !in_array($extension, ['xlsx', 'xls'], true)
        ) {
            $errors[] = 'El archivo debe tener extensión .xlsx o .xls.';
        }

        // Rechaza archivos vacíos y tamaños informados que no sean válidos.
        if (!is_int($file['size']) || $file['size'] <= 0) {
            $errors[] = 'El archivo está vacío o tiene un tamaño inválido.';
        } elseif ($file['size'] > $this->maxFileSize) {
            $errors[] = 'El archivo supera el límite permitido de 20 MB.';
        }

        // Comprueba que PHP tenga disponible el archivo temporal recibido.
        $temporaryPath = (string) $file['tmp_name'];

        if ($temporaryPath === '' || !is_file($temporaryPath)) {
            $errors[] = 'No se encuentra el archivo temporal recibido.';
        } else {
            // Contrasta el tamaño informado con el tamaño real del archivo.
            $actualSize = filesize($temporaryPath);

            if ($actualSize === false || $actualSize <= 0) {
                $errors[] = 'No se pudo leer el archivo o está vacío.';
            } elseif ($actualSize !== $file['size']) {
                $errors[] = 'El tamaño del archivo recibido no coincide.';
            } elseif ($actualSize > $this->maxFileSize) {
                $errors[] = 'El archivo supera el límite permitido de 20 MB.';
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
        ];
    }


    /**
     * Valida que el archivo provenga de una carga HTTP real de PHP
     * y, después, aplica las reglas generales de validación.
     */
    public function validateUploadedFile(array $file): array
    {
        // Conserva los mensajes específicos de errores reportados por PHP.
        if (
            isset($file['error'])
            && is_int($file['error'])
            && $file['error'] !== UPLOAD_ERR_OK
        ) {
            return $this->validate($file);
        }

        // Comprueba que exista una ruta temporal de tipo texto.
        if (
            !isset($file['tmp_name'])
            || !is_string($file['tmp_name'])
            || $file['tmp_name'] === ''
        ) {
            return [
                'valid' => false,
                'errors' => [
                    'No se recibió un archivo válido mediante HTTP.',
                ],
            ];
        }

        // Rechaza archivos locales que no provengan de una carga HTTP.
        if (!is_uploaded_file($file['tmp_name'])) {
            return [
                'valid' => false,
                'errors' => [
                    'El archivo no proviene de una carga HTTP válida.',
                ],
            ];
        }

        // Solo valida los demás atributos después de verificar el origen.
        return $this->validate($file);
    }

}
