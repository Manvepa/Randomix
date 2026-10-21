<?php

declare(strict_types=1);

use Randomix\Services\SurnameMatchService;

require dirname(__DIR__) . '/vendor/autoload.php';

$service = new SurnameMatchService();

/**
 * Comprueba que los índices obtenidos coincidan con los esperados.
 */
function assertMatchingIndexes(
    array $actual,
    array $expected,
    string $message
): void {
    if ($actual !== $expected) {
        throw new RuntimeException(
            $message
            . ' Esperado: ' . json_encode($expected)
            . '; obtenido: ' . json_encode($actual)
        );
    }
}

// Prueba 1: identifica dos estudiantes con el mismo par de apellidos.
$records = [
    ['apellido_1' => 'Pérez', 'apellido_2' => 'Gómez'],
    ['apellido_1' => 'López', 'apellido_2' => 'Díaz'],
    ['apellido_1' => 'Pérez', 'apellido_2' => 'Gómez'],
];

assertMatchingIndexes(
    $service->findMatchingIndexes($records),
    [0, 2],
    'Falló la detección de dos estudiantes coincidentes.'
);

// Prueba 2: identifica a todos los integrantes de un grupo de tres.
$records = [
    ['apellido_1' => 'García', 'apellido_2' => 'Ruiz'],
    ['apellido_1' => 'García', 'apellido_2' => 'Ruiz'],
    ['apellido_1' => 'García', 'apellido_2' => 'Ruiz'],
];

assertMatchingIndexes(
    $service->findMatchingIndexes($records),
    [0, 1, 2],
    'Falló la detección de un grupo de tres estudiantes.'
);

// Prueba 3: ignora mayúsculas, minúsculas y espacios exteriores.
$records = [
    ['apellido_1' => ' PÉREZ ', 'apellido_2' => ' Gómez'],
    ['apellido_1' => 'Pérez', 'apellido_2' => 'GÓMEZ '],
];

assertMatchingIndexes(
    $service->findMatchingIndexes($records),
    [0, 1],
    'Falló la normalización de mayúsculas y espacios.'
);

// Prueba 4: permite un apellido vacío cuando el par coincide.
$records = [
    ['apellido_1' => '', 'apellido_2' => 'Gómez'],
    ['apellido_1' => '  ', 'apellido_2' => ' GÓMEZ '],
];

assertMatchingIndexes(
    $service->findMatchingIndexes($records),
    [0, 1],
    'Falló la detección con un apellido vacío.'
);

// Prueba 5: no considera coincidentes dos registros sin apellidos.
$records = [
    ['apellido_1' => '', 'apellido_2' => ''],
    ['apellido_1' => '  ', 'apellido_2' => '  '],
];

assertMatchingIndexes(
    $service->findMatchingIndexes($records),
    [],
    'Se marcaron registros con ambos apellidos vacíos.'
);

// Prueba 6: no identifica pares de apellidos únicos.
$records = [
    ['apellido_1' => 'Pérez', 'apellido_2' => 'Gómez'],
    ['apellido_1' => 'Pérez', 'apellido_2' => 'Rodríguez'],
    ['apellido_1' => 'López', 'apellido_2' => 'Gómez'],
];

assertMatchingIndexes(
    $service->findMatchingIndexes($records),
    [],
    'Se identificaron pares de apellidos únicos.'
);

// Prueba 7: conserva los índices originales y el orden de los registros.
$records = [
    ['apellido_1' => 'Pérez', 'apellido_2' => 'Gómez'],
    ['apellido_1' => 'Único', 'apellido_2' => 'Apellido'],
    ['apellido_1' => 'Pérez', 'apellido_2' => 'Gómez'],
];

$originalRecords = $records;

assertMatchingIndexes(
    $service->findMatchingIndexes($records),
    [0, 2],
    'Los índices originales no se conservaron.'
);

if ($records !== $originalRecords) {
    throw new RuntimeException(
        'El servicio modificó los registros originales.'
    );
}

echo "Todas las pruebas de SurnameMatchService pasaron correctamente."
    . PHP_EOL;
