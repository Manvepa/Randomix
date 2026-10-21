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

// Prueba 1: respeta las cantidades de beneficiarios y suplentes.
$result = $service->select($students, 3, 1);

if (
    count($result['beneficiaries']) !== 3
    || count($result['alternates']) !== 1
    || count($result['not_selected']) !== 1
) {
    throw new RuntimeException(
        'No se respetaron las cantidades de los tres grupos.'
    );
}

// Prueba 2: conserva todos los registros entre los tres grupos.
$totalDistributed = count($result['beneficiaries'])
    + count($result['alternates'])
    + count($result['not_selected']);

if ($totalDistributed !== count($students)) {
    throw new RuntimeException(
        'Se perdieron registros durante la selección.'
    );
}

// Prueba 3: ningún registro aparece en más de un grupo.
$distributedDocuments = array_merge(
    array_column($result['beneficiaries'], 'doc'),
    array_column($result['alternates'], 'doc'),
    array_column($result['not_selected'], 'doc')
);

if (
    count($distributedDocuments)
    !== count(array_unique($distributedDocuments))
) {
    throw new RuntimeException(
        'Un registro fue asignado a más de un grupo.'
    );
}

// Prueba 4: todos los registros distribuidos pertenecen al conjunto original.
$originalDocuments = array_column($students, 'doc');

foreach ($distributedDocuments as $document) {
    if (!in_array($document, $originalDocuments, true)) {
        throw new RuntimeException(
            'Se encontró un registro que no pertenece al conjunto original.'
        );
    }
}

// Prueba 5: con ambas cantidades en cero, todos quedan sin seleccionar.
$result = $service->select($students, 0, 0);

if (
    $result['beneficiaries'] !== []
    || $result['alternates'] !== []
    || count($result['not_selected']) !== count($students)
) {
    throw new RuntimeException(
        'Falló la selección con ambas cantidades en cero.'
    );
}

// Prueba 6: permite asignar todos los registros a beneficiarios.
$result = $service->select($students, count($students), 0);

if (
    count($result['beneficiaries']) !== count($students)
    || $result['alternates'] !== []
    || $result['not_selected'] !== []
) {
    throw new RuntimeException(
        'Falló la asignación de todos los registros a beneficiarios.'
    );
}

// Prueba 7: permite asignar todos los registros a suplentes.
$result = $service->select($students, 0, count($students));

if (
    $result['beneficiaries'] !== []
    || count($result['alternates']) !== count($students)
    || $result['not_selected'] !== []
) {
    throw new RuntimeException(
        'Falló la asignación de todos los registros a suplentes.'
    );
}

// Prueba 8: rechaza cantidades negativas.
assertSelectionThrows(
    fn () => $service->select($students, -1, 0),
    'no pueden ser negativas'
);

assertSelectionThrows(
    fn () => $service->select($students, 0, -1),
    'no pueden ser negativas'
);

// Prueba 9: rechaza una suma superior al total disponible.
assertSelectionThrows(
    fn () => $service->select($students, 3, 3),
    'no puede superar'
);

// Prueba 10: rechaza un conjunto vacío.
assertSelectionThrows(
    fn () => $service->select([], 0, 0),
    'No hay registros'
);

// Prueba 11: permite seleccionar un solo beneficiario.
$result = $service->select($students, 1, 0);

if (
    count($result['beneficiaries']) !== 1
    || $result['alternates'] !== []
    || count($result['not_selected']) !== count($students) - 1
) {
    throw new RuntimeException(
        'Falló la selección de un solo beneficiario.'
    );
}

// Prueba 12: conserva filas idénticas como registros independientes.
$duplicateStudents = [
    ['doc' => '999', 'nombre' => 'Estudiante'],
    ['doc' => '999', 'nombre' => 'Estudiante'],
];

$result = $service->select($duplicateStudents, 1, 0);

if (
    count($result['beneficiaries']) !== 1
    || $result['alternates'] !== []
    || count($result['not_selected']) !== 1
    || $result['beneficiaries'][0] !== $result['not_selected'][0]
) {
    throw new RuntimeException(
        'No se conservaron correctamente las filas duplicadas.'
    );
}

echo "Correcto: todas las pruebas de SelectionService.\n";
