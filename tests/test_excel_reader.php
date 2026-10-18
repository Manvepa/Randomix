<?php

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Randomix\Import\ExcelReader;
use Randomix\Validators\ExcelHeadersValidator;
use Randomix\Import\StudentRecordsProcessor;

require dirname(__DIR__) . '/vendor/autoload.php';

// Carga el esquema institucional configurado para Randomix.
$importConfig = require dirname(__DIR__) . '/config/import.php';

$reader = new ExcelReader();
$validator = new ExcelHeadersValidator(
    $importConfig['required_headers']
);

$temporaryBase = tempnam(sys_get_temp_dir(), 'randomix_excel_');

if ($temporaryBase === false) {
    throw new RuntimeException(
        'No se pudo crear el archivo temporal de prueba.'
    );
}

unlink($temporaryBase);

$validPath = $temporaryBase . '.xlsx';
$invalidPath = $temporaryBase . '_invalid.xlsx';

try {
    // Crea un libro con los encabezados oficiales y dos hojas.
    $spreadsheet = new Spreadsheet();
    $worksheet = $spreadsheet->getActiveSheet();
    $worksheet->setTitle('Estudiantes');
    $worksheet->fromArray(
        $importConfig['required_headers'],
        null,
        'A1'
    );

    // Crea una segunda hoja para verificar que se respeta la selección.
    $spreadsheet->createSheet()->setTitle('Otra hoja');

    // Crea una hoja con una columna adicional fuera del esquema oficial.
    $extraWorksheet = $spreadsheet->createSheet();
    $extraWorksheet->setTitle('ColumnaExtra');
    $extraWorksheet->fromArray(
        $importConfig['required_headers'],
        null,
        'A1'
    );
    $extraWorksheet->setCellValue('U2', 'Columna adicional');

    // Añade un estudiante completo a la hoja principal.
    $studentRow = array_fill(
        0,
        count($importConfig['required_headers']),
        null
    );
    $studentRow[7] = '12345'; // doc
    $studentRow[10] = 'Ana';  // nombre_1

    $worksheet->fromArray($studentRow, null, 'A2');

    // Añade una fila parcialmente diligenciada.
    $partialRow = array_fill(
        0,
        count($importConfig['required_headers']),
        null
    );
    $partialRow[7] = '67890'; // doc

    $worksheet->fromArray($partialRow, null, 'A4');

    $writer = new Xlsx($spreadsheet);
    $writer->save($validPath);

    $spreadsheet->disconnectWorksheets();
    unset($spreadsheet, $writer, $worksheet);

    // Prueba 1: obtiene correctamente los nombres de las hojas.
    $sheetNames = $reader->getSheetNames($validPath);

    if (
        $sheetNames !== [
            'Estudiantes',
            'Otra hoja',
            'ColumnaExtra',
        ]
    ) {
        throw new RuntimeException(
            'Los nombres de las hojas no coinciden con lo esperado.'
        );
    }

    // Prueba 2: extrae los encabezados de la hoja seleccionada.
    $headers = $reader->getHeaders($validPath, 'Estudiantes');

    if ($headers !== $importConfig['required_headers']) {
        throw new RuntimeException(
            'Los encabezados extraídos no coinciden con la configuración.'
        );
    }

    // Prueba 3: valida los encabezados extraídos con el validador oficial.
    $validationResult = $validator->validate($headers);

    if (!$validationResult['valid']) {
        throw new RuntimeException(
            'Los encabezados del libro no superaron la validación.'
        );
    }

    
    // Prueba 4: verifica que el lector conserve las filas y sus valores.
    $rows = $reader->getRows($validPath, 'Estudiantes');

    if (
        count($rows) !== 3
        || count($rows[0] ?? []) !== count(
            $importConfig['required_headers']
        )
        || ($rows[0][7] ?? null) != 12345
        || ($rows[1] ?? null) !== array_fill(
            0,
            count($importConfig['required_headers']),
            null
        )
        || ($rows[2][7] ?? null) != 67890
    ) {
        throw new RuntimeException(
            'La lectura de filas no conservó los datos esperados.'
        );
    }


        // Prueba 5: procesa las filas y descarta únicamente la fila vacía.
    $processor = new StudentRecordsProcessor();
    $records = $processor->process($headers, $rows);

    if (
        count($records) !== 2
        || $records[0]['doc'] != 12345
        || $records[0]['nombre_1'] !== 'Ana'
        || $records[1]['doc'] != 67890
    ) {
        throw new RuntimeException(
            'La integración entre el lector y el procesador falló.'
        );
    }

    // Prueba 6: no lee registros de una hoja diferente.
    $otherRows = $reader->getRows($validPath, 'Otra hoja');

    if ($otherRows !== []) {
        throw new RuntimeException(
            'Se encontraron filas inesperadas en otra hoja.'
        );
    }

    // Prueba 7: rechaza datos que exceden las columnas del esquema.
    $extraHeaders = $reader->getHeaders(
        $validPath,
        'ColumnaExtra'
    );
    $extraRows = $reader->getRows(
        $validPath,
        'ColumnaExtra'
    );

    $extraColumnRejected = false;

    try {
        $processor->process($extraHeaders, $extraRows);
    } catch (InvalidArgumentException $exception) {
        $extraColumnRejected = str_contains(
            $exception->getMessage(),
            'más columnas que los encabezados'
        );
    }

    if (!$extraColumnRejected) {
        throw new RuntimeException(
            'No se rechazó la columna adicional.'
        );
    }

    // Prueba 4: rechaza una hoja que no existe.
    $invalidSheetRejected = false;

    try {
        $reader->getHeaders($validPath, 'Hoja inexistente');
    } catch (InvalidArgumentException) {
        $invalidSheetRejected = true;
    }

    if (!$invalidSheetRejected) {
        throw new RuntimeException(
            'No se rechazó el nombre de hoja inexistente.'
        );
    }

    // Prueba 5: rechaza un archivo inexistente.
    $missingFileRejected = false;

    try {
        $reader->getSheetNames($temporaryBase . '_missing.xlsx');
    } catch (InvalidArgumentException) {
        $missingFileRejected = true;
    }

    if (!$missingFileRejected) {
        throw new RuntimeException(
            'No se rechazó el archivo inexistente.'
        );
    }

    // Prueba 6: rechaza extensiones no permitidas.
    $invalidExtensionRejected = false;

    try {
        $reader->getSheetNames($validPath . '.csv');
    } catch (InvalidArgumentException) {
        $invalidExtensionRejected = true;
    }

    if (!$invalidExtensionRejected) {
        throw new RuntimeException(
            'No se rechazó la extensión no permitida.'
        );
    }

    // Prueba 7: rechaza contenido que no corresponde a un libro Excel.
    file_put_contents($invalidPath, 'Esto no es un archivo Excel');

    $invalidContentRejected = false;

    try {
        $reader->getSheetNames($invalidPath);
    } catch (RuntimeException) {
        $invalidContentRejected = true;
    }

    if (!$invalidContentRejected) {
        throw new RuntimeException(
            'No se rechazó el contenido inválido.'
        );
    }

    echo "Pruebas correctas: hojas, encabezados, validación del esquema y archivos inválidos.\n";
} finally {
    // Elimina los archivos temporales generados por las pruebas.
    foreach ([$validPath, $invalidPath] as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }
}
