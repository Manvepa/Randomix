<?php

declare(strict_types=1);

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Randomix\Services\FileArtifactService;
use Randomix\Support\Database;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/app.php';

$pdo = Database::getConnection();

$originalName = 'prueba_servicio_randomix.xlsx';
$mimeType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
$service = new FileArtifactService(dirname(__DIR__), $pdo);

$artifactId = null;
$storedFile = null;
$fixturePath = null;

try {
    // Genera un libro XLSX auténtico para probar el almacenamiento binario.
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    $sheet->setCellValue('A1', 'ESTADO_ALUMNO');
    $sheet->setCellValue('B1', 'DANE');
    $sheet->setCellValue('A2', 'ACTIVO');
    $sheet->setCellValue('B2', '123456789');

    $fixturePath = tempnam(sys_get_temp_dir(), 'randomix_artifact_');

    if ($fixturePath === false) {
        throw new RuntimeException(
            'No se pudo crear el archivo temporal de prueba.'
        );
    }

    // PhpSpreadsheet necesita que el destino tenga extensión XLSX.
    unlink($fixturePath);
    $fixturePath .= '.xlsx';

    try {
        $writer = new Xlsx($spreadsheet);
        $writer->save($fixturePath);
    } finally {
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet, $writer);
    }

    // Lee el libro auténtico como contenido binario.
    $content = file_get_contents($fixturePath);

    if ($content === false || $content === '') {
        throw new RuntimeException(
            'No se pudo leer el XLSX generado para la prueba.'
        );
    }

    $expectedSha256 = hash('sha256', $content);
    $expectedSize = strlen($content);

    // Comprueba que un hash incorrecto sea rechazado antes de guardar.
    $wrongHashRejected = false;

    try {
        $service->save(
            $content,
            $originalName,
            'xlsx',
            $mimeType,
            str_repeat('0', 64)
        );
    } catch (RuntimeException) {
        $wrongHashRejected = true;
    }

    if (!$wrongHashRejected) {
        throw new RuntimeException(
            'El servicio aceptó un SHA-256 diferente al contenido.'
        );
    }

    echo "[OK] SHA-256 esperado incorrecto rechazado.\n";

    // Guarda el XLSX con el hash calculado previamente.
    $artifact = $service->save(
        $content,
        $originalName,
        'xlsx',
        $mimeType,
        $expectedSha256
    );

    $artifactId = $artifact['id'];
    $storedFile = dirname(__DIR__)
        . DIRECTORY_SEPARATOR . 'storage'
        . DIRECTORY_SEPARATOR . 'records'
        . DIRECTORY_SEPARATOR . $artifact['stored_name'];

    // Comprueba que el archivo físico exista y conserve el tamaño.
    if (!is_file($storedFile)) {
        throw new RuntimeException(
            'No se encontró el archivo guardado.'
        );
    }

    clearstatcache(true, $storedFile);
    $storedSize = filesize($storedFile);

    if ($storedSize === false || $storedSize !== $expectedSize) {
        throw new RuntimeException(
            'El tamaño del archivo guardado no coincide.'
        );
    }

    // Comprueba que el hash físico coincida con el hash original.
    $storedSha256 = hash_file('sha256', $storedFile);

    if (
        !is_string($storedSha256)
        || !hash_equals($expectedSha256, $storedSha256)
        || !hash_equals($artifact['sha256'], $storedSha256)
    ) {
        throw new RuntimeException(
            'El SHA-256 del archivo guardado no coincide.'
        );
    }

    // Comprueba que el servicio pueda recuperar el contenido registrado.
    $readContent = $service->readContent($artifactId);

    if (!hash_equals($expectedSha256, hash('sha256', $readContent))) {
        throw new RuntimeException(
            'El contenido recuperado no coincide con el archivo original.'
        );
    }

    // Confirma que los metadatos quedaron registrados en MariaDB.
    $statement = $pdo->prepare(
        'SELECT
            original_name,
            stored_name,
            relative_path,
            file_extension,
            mime_type,
            file_size,
            sha256
         FROM file_artifacts
         WHERE id = :id
         LIMIT 1'
    );

    $statement->execute(['id' => $artifactId]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new RuntimeException(
            'No se encontró el registro del archivo en MariaDB.'
        );
    }

    if (
        $row['original_name'] !== $originalName
        || $row['stored_name'] !== $artifact['stored_name']
        || $row['relative_path'] !== $artifact['relative_path']
        || $row['file_extension'] !== 'xlsx'
        || $row['mime_type'] !== $mimeType
        || (int) $row['file_size'] !== $expectedSize
        || !hash_equals($expectedSha256, $row['sha256'])
    ) {
        throw new RuntimeException(
            'Los metadatos registrados no coinciden con el archivo físico.'
        );
    }

    echo "[OK] XLSX auténtico almacenado.\n";
    echo "[OK] Tamaño físico correcto.\n";
    echo "[OK] SHA-256 físico correcto.\n";
    echo "[OK] Lectura del artefacto correcta.\n";
    echo "[OK] Metadatos registrados en MariaDB.\n";
    echo "\nPRUEBA SUPERADA\n";
    echo "ID del registro: {$artifactId}\n";
    echo "Tamaño: {$expectedSize} bytes\n";
    echo "SHA-256: {$expectedSha256}\n";
} finally {
    // Elimina únicamente el registro creado por esta prueba.
    if ($artifactId !== null) {
        $statement = $pdo->prepare(
            'DELETE FROM file_artifacts WHERE id = :id'
        );

        $statement->execute(['id' => $artifactId]);
    }

    // Elimina el archivo definitivo creado por esta prueba.
    if ($storedFile !== null && is_file($storedFile)) {
        if (!unlink($storedFile)) {
            throw new RuntimeException(
                'No se pudo eliminar el archivo definitivo de prueba.'
            );
        }
    }

    // Elimina el XLSX temporal generado para la prueba.
    if ($fixturePath !== null && is_file($fixturePath)) {
        if (!unlink($fixturePath)) {
            throw new RuntimeException(
                'No se pudo eliminar el XLSX temporal de prueba.'
            );
        }
    }
}
