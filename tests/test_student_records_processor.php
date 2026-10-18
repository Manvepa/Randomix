<?php

declare(strict_types=1);

use Randomix\Import\StudentRecordsProcessor;

require dirname(__DIR__) . '/vendor/autoload.php';

$processor = new StudentRecordsProcessor();
$headers = ['doc', 'nombre', 'edad'];

/**
 * Comprueba que una condición esperada produzca una excepción.
 */
function assertThrows(
    callable $callback,
    string $expectedMessage
): void {
    try {
        $callback();
    } catch (Throwable $exception) {
        if (!str_contains($exception->getMessage(), $expectedMessage)) {
            throw new RuntimeException(
                'La excepción no contiene el mensaje esperado: '
                . $expectedMessage
            );
        }

        return;
    }

    throw new RuntimeException(
        'No se produjo la excepción esperada: ' . $expectedMessage
    );
}

// Prueba 1: ignora las filas completamente vacías y con espacios.
$records = $processor->process($headers, [
    ['100', 'Ana', 15],
    [null, null, null],
    ['', '   ', null],
]);

if (count($records) !== 1 || $records[0]['nombre'] !== 'Ana') {
    throw new RuntimeException(
        'Falló la prueba de filas completamente vacías.'
    );
}

// Prueba 2: conserva las filas parcialmente diligenciadas.
$records = $processor->process($headers, [
    ['101', 'Luis', null],
    [null, 'María', null],
]);

if (
    count($records) !== 2
    || $records[0]['edad'] !== null
    || $records[1]['doc'] !== null
) {
    throw new RuntimeException(
        'Falló la prueba de conservación de filas parciales.'
    );
}

// Prueba 3: conserva registros idénticos.
$duplicate = ['102', 'Carlos', 16];
$records = $processor->process($headers, [
    $duplicate,
    $duplicate,
]);

if (count($records) !== 2) {
    throw new RuntimeException(
        'Los registros duplicados no se conservaron.'
    );
}

// Prueba 4: conserva valores como cero y false.
$records = $processor->process($headers, [
    [0, false, null],
]);

if (
    $records[0]['doc'] !== 0
    || $records[0]['nombre'] !== false
) {
    throw new RuntimeException(
        'Falló la conservación de valores cero o false.'
    );
}

// Prueba 5: rechaza una hoja sin registros.
assertThrows(
    fn () => $processor->process($headers, [
        [null, null, null],
        ['', ' ', null],
    ]),
    'no contiene registros'
);

// Prueba 6: rechaza encabezados duplicados.
assertThrows(
    fn () => $processor->process(['doc', 'doc'], [['1', '2']]),
    'no pueden estar duplicados'
);

// Prueba 7: rechaza filas con más columnas que el esquema.
assertThrows(
    fn () => $processor->process($headers, [
        ['103', 'Pedro', 17, 'columna extra'],
    ]),
    'más columnas que los encabezados'
);

// Prueba 8: permite exactamente 20000 registros.
$rows = [];

for ($i = 0; $i < 20000; $i++) {
    $rows[] = [(string) $i, 'Estudiante', 15];
}

$records = $processor->process($headers, $rows);

if (count($records) !== 20000) {
    throw new RuntimeException(
        'No se aceptaron exactamente 20000 registros.'
    );
}

// Prueba 9: ignora filas vacías al aplicar el límite.
$rows[] = [null, null, null];

$records = $processor->process($headers, $rows);

if (count($records) !== 20000) {
    throw new RuntimeException(
        'Las filas vacías alteraron el límite de registros.'
    );
}

// Prueba 10: rechaza el registro número 20001.
$rows[] = ['20000', 'Estudiante', 15];

assertThrows(
    fn () => $processor->process($headers, $rows),
    'supera el máximo de 20000 registros'
);

echo "Correcto: todas las pruebas de StudentRecordsProcessor.\n";
