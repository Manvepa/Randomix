<?php

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Randomix\Import\ExcelReader;
use Randomix\Services\ExcelComparisonService;

require dirname(__DIR__) . '/vendor/autoload.php';

// Carga el esquema oficial de Randomix.
$importConfig = require dirname(__DIR__) . '/config/import.php';

$requiredHeaders = $importConfig['required_headers'];
$docIndex = array_search('doc', $requiredHeaders, true);

if ($docIndex === false) {
    throw new RuntimeException(
        'La configuración oficial no contiene la columna doc.'
    );
}

// Prepara las rutas temporales para no modificar archivos originales.
$tempBase = tempnam(sys_get_temp_dir(), 'randomix_comparison_');

if ($tempBase === false) {
    throw new RuntimeException(
        'No se pudo crear el directorio temporal de pruebas.'
    );
}

unlink($tempBase);

$fileA = $tempBase . '_A.xlsx';
$fileB = $tempBase . '_B.xlsx';
$fileBWithoutDoc = $tempBase . '_B_without_doc.xlsx';
$fileBDuplicateDoc = $tempBase . '_B_duplicate_doc.xlsx';
$fileAInvalid = $tempBase . '_A_invalid.xlsx';
$fileATooMany = $tempBase . '_A_too_many.xlsx';
$fileBTooMany = $tempBase . '_B_too_many.xlsx';
$fileBWithinLimit = $tempBase . '_B_within_limit.xlsx';

$temporaryFiles = [
    $fileA,
    $fileB,
    $fileBWithoutDoc,
    $fileBDuplicateDoc,
    $fileAInvalid,
    $fileATooMany,
    $fileBTooMany,
    $fileBWithinLimit,
];

/**
 * Crea un Excel de prueba con encabezados y filas definidos.
 */
$createExcel = static function (
    string $path,
    array $headers,
    array $rows
): void {
    $spreadsheet = new Spreadsheet();

    try {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Datos');

        foreach ($headers as $index => $header) {
            $sheet->setCellValue([$index + 1, 1], $header);
        }

        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                $sheet->setCellValue(
                    [$columnIndex + 1, $rowIndex + 2],
                    $value
                );
            }
        }

        $writer = new Xlsx($spreadsheet);
        $writer->save($path);
    } finally {
        $spreadsheet->disconnectWorksheets();
    }
};

/**
 * Comprueba una condición y detiene la prueba si no se cumple.
 */
$assert = static function (
    bool $condition,
    string $message
): void {
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

try {
    // Prepara filas principales identificables mediante sus documentos.
    $makeStudent = static function (
        string $document,
        string $name
    ) use ($requiredHeaders, $docIndex): array {
        $row = array_fill(0, count($requiredHeaders), null);
        $row[$docIndex] = $document;

        $nameIndex = array_search(
            'nombre_1',
            $requiredHeaders,
            true
        );

        if ($nameIndex !== false) {
            $row[$nameIndex] = $name;
        }

        return $row;
    };

    $rowsA = [
        $makeStudent('100', 'Ana'),
        $makeStudent('200', 'Carlos'),
        $makeStudent('200', 'Carolina'),
        $makeStudent('', 'SinDocumento'),
        $makeStudent('300', 'Luis'),
    ];

    // Crea el Excel A con los encabezados oficiales de Randomix.
    $createExcel(
        $fileA,
        $requiredHeaders,
        $rowsA
    );

    // El Excel B tiene una estructura diferente al esquema oficial.
    $createExcel(
        $fileB,
        ['codigo_externo', 'doc', 'observacion'],
        [
            ['X1', '100', 'Eliminar Ana'],
            ['X2', '300', 'Eliminar Luis'],
            ['X3', '300', 'Documento repetido'],
        ]
    );

    // Prepara archivos inválidos para verificar las validaciones.
    $createExcel(
        $fileBWithoutDoc,
        ['codigo_externo', 'observacion'],
        [['X1', 'Sin documento']]
    );

    $createExcel(
        $fileBDuplicateDoc,
        ['doc', 'doc'],
        [['100', '200']]
    );

    $invalidHeaders = $requiredHeaders;
    $acudientesIndex = array_search(
        'ACUDIENTES',
        $invalidHeaders,
        true
    );

    if ($acudientesIndex !== false) {
        unset($invalidHeaders[$acudientesIndex]);
    }

    $invalidHeaders = array_values($invalidHeaders);

    $createExcel(
        $fileAInvalid,
        $invalidHeaders,
        []
    );

    $service = new ExcelComparisonService(
        $requiredHeaders,
        new ExcelReader(),
        $importConfig['max_rows']
    );

    // Prueba 1: elimina coincidencias y conserva los demás registros.
    $result = $service->compare(
        $fileA,
        'Datos',
        $fileB,
        'Datos'
    );

    $assert(
        $result['total_original'] === 5,
        'El total original debería ser 5.'
    );

    $assert(
        $result['coincidences_removed'] === 2,
        'Deberían eliminarse dos filas coincidentes.'
    );

    $assert(
        $result['remaining'] === 3,
        'Deberían quedar tres registros.'
    );

    $assert(
        $result['total_original']
            === $result['coincidences_removed'] + $result['remaining'],
        'Las estadísticas no cumplen la igualdad esperada.'
    );

    // Prueba 2: conserva los duplicados cuyo documento no aparece en B.
    $remainingDocuments = array_column(
        $result['records'],
        'doc'
    );

    $assert(
        count(array_filter(
            $remainingDocuments,
            static fn(mixed $doc): bool => (string) $doc === '200'
        )) === 2,
        'Se deberían conservar las dos filas con documento 200.'
    );

    // Prueba 3: conserva los registros cuyo documento está vacío.
    $assert(
        in_array('', array_map(
            static fn(mixed $doc): string => trim((string) ($doc ?? '')),
            $remainingDocuments
        ), true),
        'Debería conservarse el registro sin documento.'
    );

    // Prueba 4: conserva exactamente el orden de los encabezados de A.
    $assert(
        $result['headers'] === $requiredHeaders,
        'Los encabezados de salida no coinciden con los de A.'
    );

    // Prueba 5: rechaza B cuando no tiene la columna doc.
    $missingDocRejected = false;

    try {
        $service->compare(
            $fileA,
            'Datos',
            $fileBWithoutDoc,
            'Datos'
        );
    } catch (InvalidArgumentException) {
        $missingDocRejected = true;
    }

    $assert(
        $missingDocRejected,
        'Se debería rechazar B cuando falta la columna doc.'
    );

    // Prueba 6: rechaza B cuando tiene dos columnas doc.
    $duplicateDocRejected = false;

    try {
        $service->compare(
            $fileA,
            'Datos',
            $fileBDuplicateDoc,
            'Datos'
        );
    } catch (InvalidArgumentException) {
        $duplicateDocRejected = true;
    }

    $assert(
        $duplicateDocRejected,
        'Se debería rechazar B con columnas doc duplicadas.'
    );

    // Prueba 7: rechaza A cuando falta un encabezado obligatorio.
    $invalidARejected = false;

    try {
        $service->compare(
            $fileAInvalid,
            'Datos',
            $fileB,
            'Datos'
        );
    } catch (InvalidArgumentException) {
        $invalidARejected = true;
    }

    $assert(
        $invalidARejected,
        'Se debería rechazar A cuando falta un encabezado oficial.'
    );

    // Prueba 8: confirma que la comparación no modificó los originales.
    $reader = new ExcelReader();

    $assert(
        count($reader->getRows($fileA, 'Datos')) === 5,
        'El archivo A original debería conservar sus cinco filas.'
    );

    echo "Pruebas correctas: comparación, duplicados, documentos vacíos, "
        . "estadísticas, encabezados y archivos originales.\n";

    // Prueba 9: rechaza A y B cuando superan el límite configurado.
    $smallLimitService = new ExcelComparisonService(
        $requiredHeaders,
        new ExcelReader(),
        2
    );

    // A tiene tres registros no vacíos: debe rechazarse.
    $createExcel(
        $fileATooMany,
        $requiredHeaders,
        [
            $makeStudent('101', 'Ana'),
            $makeStudent('102', 'Carlos'),
            $makeStudent('103', 'Luis'),
        ]
    );

    // B tiene tres registros no vacíos: también debe rechazarse.
    $createExcel(
        $fileBTooMany,
        ['doc'],
        [
            ['101'],
            ['102'],
            ['103'],
        ]
    );

    // B válido para la prueba de A: tiene solo un registro.
    // Así, B no supera el límite y podemos comprobar específicamente A.
    $createExcel(
        $fileBWithinLimit,
        ['doc'],
        [
            ['999'],
        ]
    );

    // Comprueba que A se rechaza por superar el máximo de dos registros.
    $aTooManyRejected = false;

    try {
        $smallLimitService->compare(
            $fileATooMany,
            'Datos',
            $fileBWithinLimit,
            'Datos'
        );
    } catch (InvalidArgumentException $exception) {
        $aTooManyRejected = str_contains(
            $exception->getMessage(),
            'Excel A supera'
        );
    }

    $assert(
        $aTooManyRejected,
        'Se debería rechazar A cuando supera el máximo de registros.'
    );

    // Comprueba que B se rechaza por superar el máximo de dos registros.
    $bTooManyRejected = false;

    try {
        $smallLimitService->compare(
            $fileA,
            'Datos',
            $fileBTooMany,
            'Datos'
        );
    } catch (InvalidArgumentException $exception) {
        $bTooManyRejected = str_contains(
            $exception->getMessage(),
            'Excel B supera'
        );
    }

    $assert(
        $bTooManyRejected,
        'Se debería rechazar B cuando supera el máximo de registros.'
    );

    echo "Prueba correcta: límite máximo de registros para Excel A y B.\n";

} finally {
    // Elimina únicamente los archivos creados por esta prueba.
    foreach ($temporaryFiles as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
}
