<?php

declare(strict_types=1);

use Randomix\Validators\ExcelHeadersValidator;

require dirname(__DIR__) . '/vendor/autoload.php';

// Carga la configuración oficial de importación.
$importConfig = require dirname(__DIR__) . '/config/import.php';

$validator = new ExcelHeadersValidator(
    $importConfig['required_headers']
);

$headers = $validator->getRequiredHeaders();

// Prueba 1: valida los 20 encabezados oficiales.
$result = $validator->validate($headers);

if (!$result['valid']) {
    throw new RuntimeException(
        'Falló la validación del esquema oficial.'
    );
}

// Prueba 2: detecta un encabezado obligatorio faltante.
$missingHeaderResult = $validator->validate(
    array_slice($headers, 0, 19)
);

if (
    $missingHeaderResult['valid']
    || !in_array(
        'ACUDIENTES',
        $missingHeaderResult['missing'],
        true
    )
) {
    throw new RuntimeException(
        'No se detectó correctamente el encabezado faltante.'
    );
}

// Prueba 3: rechaza una columna adicional.
$extraHeaderResult = $validator->validate(
    [...$headers, 'COLUMNA_NO_PERMITIDA']
);

if (
    $extraHeaderResult['valid']
    || !in_array(
        'COLUMNA_NO_PERMITIDA',
        $extraHeaderResult['extra'],
        true
    )
) {
    throw new RuntimeException(
        'No se detectó correctamente la columna adicional.'
    );
}

// Prueba 4: rechaza encabezados duplicados.
$duplicateHeaderResult = $validator->validate(
    [...$headers, 'DANE']
);

if (
    $duplicateHeaderResult['valid']
    || !in_array(
        'DANE',
        $duplicateHeaderResult['duplicates'],
        true
    )
) {
    throw new RuntimeException(
        'No se detectó correctamente el encabezado duplicado.'
    );
}

// Prueba 5: demuestra que se puede utilizar un esquema diferente.
$customValidator = new ExcelHeadersValidator([
    'CODIGO',
    'NOMBRE',
    'GRADO',
]);

$customResult = $customValidator->validate([
    'GRADO',
    'CODIGO',
    'NOMBRE',
]);

if (!$customResult['valid']) {
    throw new RuntimeException(
        'El validador no aceptó el esquema personalizado.'
    );
}

// Prueba 6: el esquema personalizado también rechaza columnas adicionales.
$customExtraResult = $customValidator->validate([
    'CODIGO',
    'NOMBRE',
    'GRADO',
    'OTRA_COLUMNA',
]);

if (
    $customExtraResult['valid']
    || !in_array(
        'OTRA_COLUMNA',
        $customExtraResult['extra'],
        true
    )
) {
    throw new RuntimeException(
        'El esquema personalizado no detectó la columna adicional.'
    );
}

echo "Pruebas correctas: esquema oficial, faltantes, extras, duplicados y esquema personalizado.\n";
