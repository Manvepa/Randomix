<?php

declare(strict_types=1);

use Randomix\Import\ExcelReader;
use Randomix\Services\ExcelComparisonService;

// Carga las clases del proyecto mediante Composer.
$projectRoot = dirname(__DIR__);
require $projectRoot . '/vendor/autoload.php';

// Define las rutas de las copias de diagnóstico.
$diagnosticDirectory = $projectRoot
    . DIRECTORY_SEPARATOR . 'storage'
    . DIRECTORY_SEPARATOR . 'temporary'
    . DIRECTORY_SEPARATOR . 'diagnostico';

// Ajusta las extensiones si alguno de los archivos es .xls.
$files = [
    'principal' => $diagnosticDirectory
        . DIRECTORY_SEPARATOR . 'excel_principal.xlsx',

    'disparador_A' => $diagnosticDirectory
        . DIRECTORY_SEPARATOR . 'disparador_A.xlsx',

    'disparador_N' => $diagnosticDirectory
        . DIRECTORY_SEPARATOR . 'disparador_N.xlsx',
];

// Carga la configuración de importación sin modificarla.
$importConfig = require $projectRoot . '/config/import.php';

/**
 * Lee un archivo, identifica su columna doc y devuelve sus documentos.
 *
 * Las claves llevan un prefijo para evitar que PHP convierta
 * identificaciones numéricas en claves enteras.
 * No imprime los valores completos de los documentos.
 */
function reportarArchivo(
    string $label,
    string $path,
    ExcelReader $reader
): array {
    if (!is_file($path)) {
        throw new RuntimeException(
            "No se encontró el archivo: {$label}. "
            . 'Comprueba su nombre y extensión.'
        );
    }

    // Examina la primera hoja del archivo.
    $sheets = $reader->getSheetNames($path);

    if ($sheets === []) {
        throw new RuntimeException(
            "El archivo {$label} no contiene hojas."
        );
    }

    $sheet = $sheets[0];
    $headers = $reader->getHeaders($path, $sheet);
    $rows = $reader->getRows($path, $sheet);

    echo "\n========================================\n";
    echo "ARCHIVO: {$label}\n";
    echo "========================================\n";
    echo 'Nombre: ' . basename($path) . "\n";
    echo "Hoja examinada: {$sheet}\n";
    echo 'Cantidad de encabezados: ' . count($headers) . "\n";

    // Muestra el orden real de los encabezados que leyó PHP.
    echo "\nEncabezados detectados:\n";

    foreach ($headers as $index => $header) {
        echo ($index + 1) . ': [' . $header . "]\n";
    }

    // Encuentra todas las columnas cuyo encabezado sea exactamente doc.
    $docIndexes = [];

    foreach ($headers as $index => $header) {
        if ($header === 'doc') {
            $docIndexes[] = $index;
        }
    }

    echo "\nPosiciones de doc (base 1): ";

    echo $docIndexes === []
        ? 'no encontrado'
        : implode(', ', array_map(
            static fn(int $index): string => (string) ($index + 1),
            $docIndexes
        ));

    echo "\n";

    if (count($docIndexes) !== 1) {
        throw new RuntimeException(
            "{$label} debe tener exactamente una columna llamada doc."
        );
    }

    $docIndex = $docIndexes[0];

    // Prepara un conjunto de documentos y contadores de diagnóstico.
    $documents = [];
    $lengths = [];
    $emptyDocuments = 0;
    $nonEmptyRows = 0;
    $sampleCount = 0;

    foreach ($rows as $row) {
        // Ignora las filas completamente vacías.
        $hasData = false;

        foreach ($row as $value) {
            if (
                $value !== null
                && (!is_string($value) || trim($value) !== '')
            ) {
                $hasData = true;
                break;
            }
        }

        if (!$hasData) {
            continue;
        }

        $nonEmptyRows++;

        // Lee el valor de la columna doc encontrada por encabezado.
        $value = $row[$docIndex] ?? null;

        $document = (
            $value !== null && is_scalar($value)
        )
            ? trim((string) $value)
            : '';

        if ($document === '') {
            $emptyDocuments++;
            continue;
        }

        // El prefijo impide que PHP convierta documentos numéricos en int.
        $documentKey = 'doc:' . $document;
        $documents[$documentKey] = $document;

        // Cuenta la longitud del documento.
        $length = strlen($document);
        $lengths[$length] = ($lengths[$length] ?? 0) + 1;

        // Muestra únicamente tipo y longitud, nunca el documento.
        if ($sampleCount < 5) {
            echo 'Muestra ' . ($sampleCount + 1)
                . ': tipo=' . get_debug_type($value)
                . ', longitud=' . $length
                . "\n";

            $sampleCount++;
        }
    }

    ksort($lengths);

    echo "\nFilas con datos: {$nonEmptyRows}\n";
    echo 'Documentos distintos no vacíos: '
        . count($documents) . "\n";
    echo "Filas con documento vacío: {$emptyDocuments}\n";

    echo "\nDistribución por longitud:\n";

    foreach ($lengths as $length => $count) {
        echo "  {$length} caracteres: {$count}\n";
    }

    return [
        'path' => $path,
        'sheet' => $sheet,
        'documents' => $documents,
        'non_empty_rows' => $nonEmptyRows,
        'empty_documents' => $emptyDocuments,
    ];
}

/**
 * Compara los documentos de un disparador contra el Excel principal.
 *
 * La comparación con ceros a la izquierda es solo diagnóstica:
 * no representa una modificación de las reglas de Randomix.
 */
function diagnosticarCoincidencias(
    string $label,
    array $principal,
    array $trigger
): void {
    $matches = count(array_intersect_key(
        $principal['documents'],
        $trigger['documents']
    ));

    $paddedMatches = 0;

    foreach ($trigger['documents'] as $document) {
        if (strlen($document) > 10) {
            continue;
        }

        $candidate = str_pad(
            $document,
            10,
            '0',
            STR_PAD_LEFT
        );

        $candidateKey = 'doc:' . $candidate;

        if (isset($principal['documents'][$candidateKey])) {
            $paddedMatches++;
        }
    }

    echo "\n----------------------------------------\n";
    echo "COMPARACIÓN DE DIAGNÓSTICO: {$label}\n";
    echo "----------------------------------------\n";
    echo "Coincidencias exactas: {$matches}\n";
    echo "Coincidencias hipotéticas con ceros: {$paddedMatches}\n";
}

/**
 * Muestra el resultado de comparar individualmente los documentos
 * del disparador N contra el Excel principal.
 *
 * No muestra los números completos por privacidad.
 */
function diagnosticarDocumentosDisparadorN(
    array $principal,
    array $triggerN
): void {
    echo PHP_EOL;
    echo "========================================" . PHP_EOL;
    echo "DOCUMENTOS DEL DISPARADOR N" . PHP_EOL;
    echo "========================================" . PHP_EOL;

    foreach ($triggerN['documents'] as $documento) {
        // Comprueba la coincidencia exacta.
        $coincideExactamente = isset(
            $principal['documents']['doc:' . $documento]
        );

        // Comprueba hipotéticamente si coincide al completar con ceros.
        $documentoConCeros = str_pad(
            $documento,
            10,
            '0',
            STR_PAD_LEFT
        );

        $coincideConCeros = isset(
            $principal['documents']['doc:' . $documentoConCeros]
        );

        echo 'Longitud: ' . strlen($documento)
            . ' | Exacto: ' . ($coincideExactamente ? 'SI' : 'NO')
            . ' | Con ceros: ' . ($coincideConCeros ? 'SI' : 'NO')
            . PHP_EOL;
    }
}

try {
    // Lee el Excel principal y ambos disparadores.
    $reader = new ExcelReader();

    $principal = reportarArchivo(
        'EXCEL PRINCIPAL A',
        $files['principal'],
        $reader
    );

    $triggerA = reportarArchivo(
        'DISPARADOR A',
        $files['disparador_A'],
        $reader
    );

    $triggerN = reportarArchivo(
        'DISPARADOR N',
        $files['disparador_N'],
        $reader
    );

    // Compara ambos disparadores contra el mismo Excel principal.
    diagnosticarCoincidencias(
        'Disparador A',
        $principal,
        $triggerA
    );

    diagnosticarCoincidencias(
        'Disparador N',
        $principal,
        $triggerN
    );

    // Examina cada documento del disparador N.
    diagnosticarDocumentosDisparadorN(
        $principal,
        $triggerN
    );

    // Ejecuta el servicio real de Randomix sin modificar los archivos.
    $service = new ExcelComparisonService(
        $importConfig['required_headers'],
        $reader,
        (int) $importConfig['max_rows']
    );

    foreach ([
        'Disparador A' => $triggerA,
        'Disparador N' => $triggerN,
    ] as $label => $trigger) {
        $result = $service->compare(
            $principal['path'],
            $principal['sheet'],
            $trigger['path'],
            $trigger['sheet']
        );

        echo "\n----------------------------------------\n";
        echo "RESULTADO DEL SERVICIO: {$label}\n";
        echo "----------------------------------------\n";
        echo 'Registros originales: '
            . $result['total_original'] . "\n";
        echo 'Coincidencias eliminadas: '
            . $result['coincidences_removed'] . "\n";
        echo 'Registros restantes: '
            . $result['remaining'] . "\n";
    }

    echo "\nDiagnóstico terminado correctamente.\n";
} catch (Throwable $exception) {
    echo "\nERROR: " . $exception->getMessage() . "\n";
    exit(1);
}
