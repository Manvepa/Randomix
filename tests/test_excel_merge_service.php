<?php

declare(strict_types=1);

use Randomix\Services\ExcelMergeService;

require dirname(__DIR__) . '/vendor/autoload.php';

// Carga el esquema oficial utilizado por Randomix.
$config = require dirname(__DIR__) . '/config/import.php';
$headers = $config['required_headers'];

$service = new ExcelMergeService($headers);

// Construye filas de prueba conservando el orden de las columnas.
function makeStudentRow(array $headers, string $document, string $name): array
{
    $row = array_fill(0, count($headers), null);
    $row[array_search('doc', $headers, true)] = $document;
    $row[array_search('nombre_1', $headers, true)] = $name;

    return $row;
}

// Excel A contiene un estudiante y un documento repetido internamente.
$rowsA = [
    makeStudentRow($headers, '00123', 'Ana'),
    makeStudentRow($headers, '00123', 'Ana duplicada'),
    makeStudentRow($headers, '', 'Sin documento'),
    array_fill(0, count($headers), null),
];

// Excel B repite el documento de A y aporta otro estudiante.
$rowsB = [
    makeStudentRow($headers, '00123', 'Ana desde B'),
    makeStudentRow($headers, '00456', 'Luis'),
    makeStudentRow($headers, '  ', 'Documento vacío'),
];

$result = $service->merge($headers, $rowsA, $headers, $rowsB);

// Comprueba que A tenga prioridad y que se eliminen los duplicados.
if (
    count($result['records']) !== 2
    || $result['records'][0]['nombre_1'] !== 'Ana'
    || $result['records'][1]['nombre_1'] !== 'Luis'
    || $result['duplicates_removed'] !== 2
    || $result['omitted_without_document'] !== 2
    || $result['omitted_empty_rows'] !== 1
    || $result['total_merged'] !== 2
) {
    throw new RuntimeException(
        'Falló la prueba de unión, deduplicación u omisión de filas.'
    );
}

// Comprueba que un orden diferente de encabezados sea rechazado.
$reorderedHeaders = $headers;
$reorderedHeaders[0] = $headers[1];
$reorderedHeaders[1] = $headers[0];

$reorderedRejected = false;

try {
    $service->merge($headers, $rowsA, $reorderedHeaders, $rowsB);
} catch (RuntimeException) {
    $reorderedRejected = true;
}

if (!$reorderedRejected) {
    throw new RuntimeException(
        'No se rechazaron los encabezados en orden incorrecto.'
    );
}

// Comprueba que una fila con columnas adicionales sea rechazada.
$invalidRows = [$rowsA[0]];
$invalidRows[0][] = 'Columna extra';

$extraColumnRejected = false;

try {
    $service->merge($headers, $invalidRows, $headers, $rowsB);
} catch (InvalidArgumentException) {
    $extraColumnRejected = true;
}

if (!$extraColumnRejected) {
    throw new RuntimeException(
        'No se rechazó la fila con columnas adicionales.'
    );
}


    // Comprueba que la columna auxiliar # se ignore en ambos archivos.
    $headersWithNumber = array_merge(['#'], $headers);

    $rowsWithNumberA = [];
    foreach ($rowsA as $index => $row) {
        array_unshift($row, $index + 1);
        $rowsWithNumberA[] = $row;
    }

    $rowsWithNumberB = [];
    foreach ($rowsB as $index => $row) {
        array_unshift($row, $index + 1);
        $rowsWithNumberB[] = $row;
    }

    $numberResult = $service->merge(
        $headersWithNumber,
        $rowsWithNumberA,
        $headersWithNumber,
        $rowsWithNumberB
    );

    if (
        $numberResult['headers'] !== $headers
        || count($numberResult['records']) !== 2
        || array_key_exists('#', $numberResult['records'][0])
        || $numberResult['records'][0]['nombre_1'] !== 'Ana'
        || $numberResult['records'][1]['nombre_1'] !== 'Luis'
    ) {
        throw new RuntimeException(
            'Falló la prueba de ignorar la columna auxiliar #.'
        );
    }

    // Comprueba que # también se ignore cuando aparece al final.
    $headersWithNumberAtEnd = array_merge($headers, ['#']);
    $rowsWithNumberAtEndA = $rowsA;
    $rowsWithNumberAtEndB = $rowsB;

    foreach ($rowsWithNumberAtEndA as &$row) {
        $row[] = 999;
    }
    unset($row);

    foreach ($rowsWithNumberAtEndB as &$row) {
        $row[] = 999;
    }
    unset($row);

    $numberAtEndResult = $service->merge(
        $headersWithNumberAtEnd,
        $rowsWithNumberAtEndA,
        $headers,
        $rowsB
    );

    if ($numberAtEndResult['headers'] !== $headers) {
        throw new RuntimeException(
            'Falló la prueba de ignorar # al final de los encabezados.'
        );
    }

echo "Pruebas correctas: unión, prioridad de A, duplicados, filas vacías, documentos vacíos y encabezados.\n";
