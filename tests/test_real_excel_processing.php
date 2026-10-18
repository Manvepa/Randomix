<?php

declare(strict_types=1);

use Randomix\Import\ExcelReader;
use Randomix\Import\StudentRecordsProcessor;
use Randomix\Validators\ExcelHeadersValidator;

require dirname(__DIR__) . '/vendor/autoload.php';

// Carga el esquema oficial de encabezados de Randomix.
$config = require dirname(__DIR__) . '/config/import.php';

$filePath = dirname(__DIR__)
    . '/storage/temporary/randomix_upload_f176f921f1e7c4b2615a60b3fb3fef2f.xlsx';

$sheetName = 'Detalle1';

try {
    // Comprueba que el archivo temporal siga disponible.
    if (!is_file($filePath) || !is_readable($filePath)) {
        throw new RuntimeException(
            'El archivo temporal no existe o ya venció. Carga el Excel nuevamente.'
        );
    }

    // Lee los encabezados y valida el esquema oficial.
    $reader = new ExcelReader();
    $headers = $reader->getHeaders($filePath, $sheetName);

    $validator = new ExcelHeadersValidator(
        $config['required_headers']
    );

    $validation = $validator->validate($headers);

    echo "Archivo temporal disponible.\n";
    echo "Hoja comprobada: {$sheetName}\n";
    echo 'Cantidad de encabezados encontrados: '
        . count($headers) . "\n";

    if (!$validation['valid']) {
        echo "Validación de encabezados: INCORRECTA\n";
        echo 'Encabezados faltantes: '
            . count($validation['missing']) . "\n";
        echo 'Encabezados adicionales: '
            . count($validation['extra']) . "\n";
        echo 'Encabezados duplicados: '
            . count($validation['duplicates']) . "\n";

        exit(1);
    }

    echo "Validación de encabezados: CORRECTA\n";

    // Lee las filas y las procesa sin imprimir sus valores.
    $rows = $reader->getRows($filePath, $sheetName);
    $processor = new StudentRecordsProcessor();
    $records = $processor->process($headers, $rows);

    echo 'Filas leídas después del encabezado: '
        . count($rows) . "\n";
    echo 'Registros procesados: '
        . count($records) . "\n";
    echo 'Filas vacías omitidas: '
        . (count($rows) - count($records)) . "\n";
    echo 'Límite configurado: '
        . $config['max_rows'] . "\n";

    if (count($records) > $config['max_rows']) {
        throw new RuntimeException(
            'El total procesado supera el límite configurado.'
        );
    }

    echo "Resultado: PROCESAMIENTO CORRECTO\n";
} catch (Throwable $exception) {
    fwrite(
        STDERR,
        'Error de prueba: ' . $exception->getMessage() . "\n"
    );

    exit(1);
}