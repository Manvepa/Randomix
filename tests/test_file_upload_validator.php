<?php

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Randomix\Validators\FileUploadValidator;

require dirname(__DIR__) . '/vendor/autoload.php';

// Carga los límites y los MIME permitidos desde la configuración oficial.
$importConfig = require dirname(__DIR__) . '/config/import.php';

if (
    !isset($importConfig['max_file_size'], $importConfig['allowed_mime_types'])
    || !is_array($importConfig['allowed_mime_types'])
) {
    throw new RuntimeException(
        'La configuración de importación no contiene los límites y MIME necesarios.'
    );
}

// Construye el validador con la configuración completa.
$validator = new FileUploadValidator(
    (int) $importConfig['max_file_size'],
    $importConfig['allowed_mime_types']
);

// Crea un directorio aislado para los archivos generados por esta prueba.
$testDirectory = sys_get_temp_dir()
    . DIRECTORY_SEPARATOR
    . 'randomix_upload_tests_'
    . bin2hex(random_bytes(8));

if (!mkdir($testDirectory, 0700, true) && !is_dir($testDirectory)) {
    throw new RuntimeException(
        'No se pudo crear el directorio temporal de pruebas.'
    );
}

/**
 * Comprueba una condición y detiene la prueba si no se cumple.
 */
function assertUploadTest(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/**
 * Genera un libro Excel real para evitar probar con texto renombrado.
 */
function createExcelFixture(string $path, string $format): void
{
    $spreadsheet = new Spreadsheet();

    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setCellValue('A1', 'ESTADO_ALUMNO');
    $sheet->setCellValue('B1', 'DANE');
    $sheet->setCellValue('A2', 'ACTIVO');
    $sheet->setCellValue('B2', '123456789');

    try {
        $writer = match ($format) {
            'xlsx' => new Xlsx($spreadsheet),
            'xls' => new Xls($spreadsheet),
            default => throw new InvalidArgumentException(
                'Formato de prueba no soportado.'
            ),
        };

        $writer->save($path);
    } finally {
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);
    }
}

/**
 * Devuelve una estructura similar a un elemento de $_FILES.
 */
function createFileData(string $path, string $name): array
{
    $size = filesize($path);

    if ($size === false) {
        throw new RuntimeException(
            'No se pudo consultar el tamaño del archivo de prueba.'
        );
    }

    return [
        'name' => $name,
        'tmp_name' => $path,
        'error' => UPLOAD_ERR_OK,
        'size' => $size,
    ];
}

$createdFiles = [];

try {
    // -------------------------------------------------------------
    // Preparación: genera libros XLSX y XLS auténticos.
    // -------------------------------------------------------------
    $xlsxPath = $testDirectory . DIRECTORY_SEPARATOR . 'valid.xlsx';
    $xlsPath = $testDirectory . DIRECTORY_SEPARATOR . 'valid.xls';

    createExcelFixture($xlsxPath, 'xlsx');
    $createdFiles[] = $xlsxPath;

    // Prueba XLS solo si el escritor está disponible en esta instalación.
    $xlsWriterAvailable = class_exists(Xls::class);

    if ($xlsWriterAvailable) {
        createExcelFixture($xlsPath, 'xls');
        $createdFiles[] = $xlsPath;
    }

    // -------------------------------------------------------------
    // Prueba 1: acepta un XLSX auténtico.
    // -------------------------------------------------------------
    $validXlsx = createFileData($xlsxPath, 'estudiantes.xlsx');
    $xlsxResult = $validator->validate($validXlsx);

    assertUploadTest(
        $xlsxResult['valid'] === true,
        'No se aceptó un XLSX auténtico: '
            . implode(' ', $xlsxResult['errors'] ?? [])
    );

    assertUploadTest(
        ($xlsxResult['extension'] ?? null) === 'xlsx',
        'El validador no devolvió correctamente la extensión XLSX.'
    );

    assertUploadTest(
        isset($xlsxResult['mime_type'], $xlsxResult['sha256'])
            && preg_match('/^[a-f0-9]{64}$/', $xlsxResult['sha256']) === 1,
        'No se devolvieron correctamente el MIME y el SHA-256 del XLSX.'
    );

    echo "[OK] XLSX auténtico aceptado.\n";

    // -------------------------------------------------------------
    // Prueba 2: acepta un XLS auténtico cuando el escritor existe.
    // -------------------------------------------------------------
    if ($xlsWriterAvailable) {
        $validXls = createFileData($xlsPath, 'estudiantes.xls');
        $xlsResult = $validator->validate($validXls);

        assertUploadTest(
            $xlsResult['valid'] === true,
            'No se aceptó un XLS auténtico: '
                . implode(' ', $xlsResult['errors'] ?? [])
        );

        assertUploadTest(
            ($xlsResult['detected_format'] ?? null) === 'Xls',
            'El formato XLS no fue identificado correctamente.'
        );

        echo "[OK] XLS auténtico aceptado.\n";
    } else {
        echo "[OMITIDA] Prueba XLS: el escritor XLS no está disponible.\n";
    }

    // -------------------------------------------------------------
    // Prueba 3: rechaza texto plano renombrado como XLSX.
    // -------------------------------------------------------------
    $fakeTextPath = $testDirectory . DIRECTORY_SEPARATOR . 'fake.xlsx';
    file_put_contents($fakeTextPath, 'Esto no es un libro Excel.');
    $createdFiles[] = $fakeTextPath;

    $fakeTextResult = $validator->validate(
        createFileData($fakeTextPath, 'archivo.xlsx')
    );

    assertUploadTest(
        $fakeTextResult['valid'] === false,
        'Se aceptó un archivo de texto renombrado como XLSX.'
    );

    echo "[OK] Texto renombrado como XLSX rechazado.\n";

    // -------------------------------------------------------------
    // Prueba 4: rechaza un XLSX válido cuyo nombre termina en .xls.
    // -------------------------------------------------------------
    $wrongExtensionResult = $validator->validate(
        createFileData($xlsxPath, 'archivo.xls')
    );

    assertUploadTest(
        $wrongExtensionResult['valid'] === false,
        'Se aceptó un XLSX presentado con extensión XLS.'
    );

    echo "[OK] Extensión que no coincide con el formato rechazada.\n";

    // -------------------------------------------------------------
    // Prueba 5: rechaza extensiones no permitidas.
    // -------------------------------------------------------------
    $invalidExtensionResult = $validator->validate([
        ...$validXlsx,
        'name' => 'estudiantes.csv',
    ]);

    assertUploadTest(
        $invalidExtensionResult['valid'] === false,
        'Se aceptó una extensión CSV.'
    );

    echo "[OK] Extensión no permitida rechazada.\n";

    // -------------------------------------------------------------
    // Prueba 6: rechaza un tamaño superior al máximo configurado.
    // -------------------------------------------------------------
    $oversizedResult = $validator->validate([
        ...$validXlsx,
        'size' => $importConfig['max_file_size'] + 1,
    ]);

    assertUploadTest(
        $oversizedResult['valid'] === false,
        'Se aceptó un archivo que supera el tamaño máximo.'
    );

    echo "[OK] Tamaño superior al límite rechazado.\n";

    // -------------------------------------------------------------
    // Prueba 7: reconoce que PHP no recibió ningún archivo.
    // -------------------------------------------------------------
    $missingFileResult = $validator->validate([
        ...$validXlsx,
        'error' => UPLOAD_ERR_NO_FILE,
    ]);

    assertUploadTest(
        $missingFileResult['valid'] === false,
        'No se detectó la ausencia del archivo.'
    );

    echo "[OK] Error UPLOAD_ERR_NO_FILE detectado.\n";

    // -------------------------------------------------------------
    // Prueba 8: rechaza un archivo vacío.
    // -------------------------------------------------------------
    $emptyPath = $testDirectory . DIRECTORY_SEPARATOR . 'empty.xlsx';
    file_put_contents($emptyPath, '');
    $createdFiles[] = $emptyPath;

    $emptyResult = $validator->validate(
        createFileData($emptyPath, 'vacio.xlsx')
    );

    assertUploadTest(
        $emptyResult['valid'] === false,
        'Se aceptó un archivo vacío.'
    );

    echo "[OK] Archivo vacío rechazado.\n";

    // -------------------------------------------------------------
    // Prueba 9: rechaza un tamaño informado que no coincide con disco.
    // -------------------------------------------------------------
    $mismatchedSizeResult = $validator->validate([
        ...$validXlsx,
        'size' => $validXlsx['size'] + 1,
    ]);

    assertUploadTest(
        $mismatchedSizeResult['valid'] === false,
        'Se aceptó un tamaño informado diferente al tamaño real.'
    );

    echo "[OK] Diferencia entre tamaño informado y real detectada.\n";

    // -------------------------------------------------------------
    // Prueba 10: rechaza archivos locales como si fueran cargas HTTP.
    // is_uploaded_file() solo puede aprobar una subida HTTP real.
    // -------------------------------------------------------------
    $localFileResult = $validator->validateUploadedFile($validXlsx);

    assertUploadTest(
        $localFileResult['valid'] === false
            && in_array(
                'El archivo no proviene de una carga HTTP válida.',
                $localFileResult['errors'],
                true
            ),
        'No se rechazó correctamente un archivo local que no llegó por HTTP.'
    );

    echo "[OK] Archivo local rechazado por validateUploadedFile().\n";

    // -------------------------------------------------------------
    // Prueba 11: rechaza rutas temporales inexistentes.
    // -------------------------------------------------------------
    $missingTemporaryResult = $validator->validate([
        ...$validXlsx,
        'tmp_name' => $testDirectory
            . DIRECTORY_SEPARATOR
            . 'no_existe.xlsx',
    ]);

    assertUploadTest(
        $missingTemporaryResult['valid'] === false,
        'Se aceptó una ruta temporal inexistente.'
    );

    echo "[OK] Ruta temporal inexistente rechazada.\n";

    echo "\nTodas las pruebas ejecutadas finalizaron correctamente.\n";
} finally {
    // Elimina todos los archivos de prueba incluso si ocurre un error.
    foreach ($createdFiles as $path) {
        if (is_file($path)) {
            unlink($path);
        }
    }

    if (is_dir($testDirectory)) {
        rmdir($testDirectory);
    }
}
