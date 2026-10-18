<?php

declare(strict_types=1);

use Randomix\Services\SelectionService;

require dirname(__DIR__) . '/vendor/autoload.php';

/**
 * Comprueba que una operación produzca la excepción esperada.
 */
function assertSelectionThrows(
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

$service = new SelectionService();

// Datos ficticios para no utilizar información personal real.
$students = [
    ['doc' => '001', 'nombre' => 'Ana'],
    ['doc' => '002', 'nombre' => 'Luis'],
    ['doc' => '003', 'nombre' => 'María'],
    ['doc' => '004', 'nombre' => 'Carlos'],
    ['doc' => '005', 'nombre' => 'Laura'],
];

// Prueba 1: selecciona la cantidad solicitada.
$result = $service->select($students, 3);

if (
    count($result['selected']) !== 3
    || count($result['not_selected']) !== 2
) {
    throw new RuntimeException(
        'No se respetó la cantidad solicitada.'
    );
}

// Prueba 2: conserva todos los registros entre ambos grupos.
if (
    count($result['selected']) + count($result['not_selected'])
    !== count($students)
) {
    throw new RuntimeException(
        'Se perdieron registros durante la selección.'
    );
}

// Prueba 3: cada registro seleccionado debe proceder del conjunto original.
$originalDocuments = array_column($students, 'doc');
$selectedDocuments = array_column($result['selected'], 'doc');

if (
    count($selectedDocuments)
    !== count(array_unique($selectedDocuments))
) {
    throw new RuntimeException(
        'Se seleccionó más de una vez el mismo registro.'
    );
}

foreach ($selectedDocuments as $document) {
    if (!in_array($document, $originalDocuments, true)) {
        throw new RuntimeException(
            'Se encontró un registro que no pertenece al conjunto original.'
        );
    }
}

// Prueba 4: solicitar cero deja todos los registros sin seleccionar.
$result = $service->select($students, 0);

if (
    $result['selected'] !== []
    || count($result['not_selected']) !== count($students)
) {
    throw new RuntimeException(
        'Falló la selección de cero registros.'
    );
}

// Prueba 5: solicitar todos selecciona el conjunto completo.
$result = $service->select($students, count($students));

if (
    count($result['selected']) !== count($students)
    || $result['not_selected'] !== []
) {
    throw new RuntimeException(
        'Falló la selección de todos los registros.'
    );
}

// Prueba 6: rechaza cantidades negativas.
assertSelectionThrows(
    fn () => $service->select($students, -1),
    'entre 0 y'
);

// Prueba 7: rechaza cantidades mayores al total disponible.
assertSelectionThrows(
    fn () => $service->select($students, count($students) + 1),
    'entre 0 y'
);

// Prueba 8: rechaza un conjunto vacío.
assertSelectionThrows(
    fn () => $service->select([], 0),
    'No hay registros'
);


/**
 * Prueba 9: permite seleccionar exactamente un registro.
 */
$result = $service->select($students, 1);

if (
    count($result['selected']) !== 1
    || count($result['not_selected']) !== count($students) - 1
) {
    throw new RuntimeException(
        'Falló la selección de un solo registro.'
    );
}

/**
 * Prueba 10: conserva filas idénticas como registros independientes.
 */
$duplicateStudents = [
    ['doc' => '999', 'nombre' => 'Estudiante'],
    ['doc' => '999', 'nombre' => 'Estudiante'],
];

$result = $service->select($duplicateStudents, 1);

if (
    count($result['selected']) !== 1
    || count($result['not_selected']) !== 1
    || $result['selected'][0] !== $result['not_selected'][0]
) {
    throw new RuntimeException(
        'No se conservaron correctamente las filas duplicadas.'
    );
}


echo "Correcto: todas las pruebas de SelectionService.\n";
