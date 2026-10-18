<?php

declare(strict_types=1);

use Randomix\Validators\FileUploadValidator;

require dirname(__DIR__) . '/vendor/autoload.php';

// Carga el límite oficial de tamaño desde la configuración.
$importConfig = require dirname(__DIR__) . '/config/import.php';

$validator = new FileUploadValidator(
    $importConfig['max_file_size']
);

// Crea un archivo temporal para simular una carga válida.
$temporaryPath = tempnam(sys_get_temp_dir(), 'randomix_');

if ($temporaryPath === false) {
    throw new RuntimeException(
        'No se pudo crear el archivo temporal de prueba.'
    );
}

try {
    file_put_contents($temporaryPath, 'Excel');

    // Prueba 1: acepta un archivo con extensión permitida y tamaño válido.
    $validFile = [
        'name' => 'estudiantes.xlsx',
        'tmp_name' => $temporaryPath,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($temporaryPath),
    ];

    $result = $validator->validate($validFile);

    if (!$result['valid']) {
        throw new RuntimeException(
            'No se aceptó el archivo válido.'
        );
    }


    // Prueba de seguridad: rechaza un archivo temporal local
    // porque no procede de una carga HTTP real de PHP.
    $untrustedUpload = $validator->validateUploadedFile($validFile);

    if (
        $untrustedUpload['valid']
        || !in_array(
            'El archivo no proviene de una carga HTTP válida.',
            $untrustedUpload['errors'],
            true
        )
    ) {
        throw new RuntimeException(
            'No se rechazó correctamente un archivo local no cargado por HTTP.'
        );
    }

    // Prueba 2: rechaza extensiones que no están permitidas.
    $invalidExtension = $validator->validate([
        ...$validFile,
        'name' => 'estudiantes.csv',
    ]);

    if ($invalidExtension['valid']) {
        throw new RuntimeException(
            'Se aceptó una extensión no permitida.'
        );
    }

    // Prueba 3: rechaza archivos que exceden el límite configurado.
    $oversizedFile = $validator->validate([
        ...$validFile,
        'size' => $importConfig['max_file_size'] + 1,
    ]);

    if ($oversizedFile['valid']) {
        throw new RuntimeException(
            'Se aceptó un archivo que supera el límite.'
        );
    }

    // Prueba 4: informa cuando PHP no recibe ningún archivo.
    $missingFile = $validator->validate([
        ...$validFile,
        'error' => UPLOAD_ERR_NO_FILE,
    ]);

    if ($missingFile['valid']) {
        throw new RuntimeException(
            'No se detectó la ausencia del archivo.'
        );
    }

    // Prueba 5: rechaza archivos temporales vacíos.
    file_put_contents($temporaryPath, '');

    $emptyFile = $validator->validate([
        ...$validFile,
        'size' => 0,
    ]);

    if ($emptyFile['valid']) {
        throw new RuntimeException(
            'Se aceptó un archivo vacío.'
        );
    }

    echo "Pruebas correctas: archivo válido, extensión, tamaño, error de carga y archivo vacío.\n";
} finally {
    // Elimina el archivo temporal incluso si una prueba falla.
    if (is_file($temporaryPath)) {
        unlink($temporaryPath);
    }
}
