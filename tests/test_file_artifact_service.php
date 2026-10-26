<?php

declare(strict_types=1);

use Randomix\Services\FileArtifactService;
use Randomix\Support\Database;

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/app.php';

$pdo = Database::getConnection();

$originalName = 'prueba_servicio_randomix.xlsx';
$content = "PRUEBA RANDOMIX\nContenido ficticio para validar el servicio.\n";
$storedFile = null;
$artifactId = null;

try {
    // Verifica que no haya registros previos antes de realizar la prueba.
    $initialCount = (int) $pdo
        ->query('SELECT COUNT(*) FROM file_artifacts')
        ->fetchColumn();

    if ($initialCount !== 0) {
        throw new RuntimeException(
            'La tabla file_artifacts no está vacía. Se cancela la prueba.'
        );
    }

    // Guarda un archivo de prueba y registra sus metadatos.
    $service = new FileArtifactService(dirname(__DIR__), $pdo);

    $artifact = $service->save(
        $content,
        $originalName,
        'xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );

    $artifactId = $artifact['id'];
    $storedFile = dirname(__DIR__)
        . '/storage/records/'
        . $artifact['stored_name'];

    // Comprueba el archivo físico y los metadatos devueltos.
    if (!is_file($storedFile)) {
        throw new RuntimeException('No se encontró el archivo guardado.');
    }

    if (filesize($storedFile) !== strlen($content)) {
        throw new RuntimeException('El tamaño del archivo no coincide.');
    }

    if (hash_file('sha256', $storedFile) !== $artifact['sha256']) {
        throw new RuntimeException('El SHA-256 no coincide.');
    }

    // Confirma que los metadatos quedaron registrados en MariaDB.
    $statement = $pdo->prepare(
        'SELECT original_name, stored_name, file_size, sha256
         FROM file_artifacts
         WHERE id = :id'
    );

    $statement->execute(['id' => $artifactId]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        throw new RuntimeException(
            'No se encontró el registro del archivo en la base de datos.'
        );
    }

    if (
        $row['original_name'] !== $originalName
        || $row['stored_name'] !== $artifact['stored_name']
        || (int) $row['file_size'] !== strlen($content)
        || $row['sha256'] !== $artifact['sha256']
    ) {
        throw new RuntimeException(
            'Los metadatos registrados no coinciden con el archivo.'
        );
    }

    echo "PRUEBA SUPERADA\n";
    echo "ID del registro: {$artifactId}\n";
    echo "Archivo físico: correcto\n";
    echo "Tamaño: correcto\n";
    echo "SHA-256: correcto\n";
    echo "Registro en MariaDB: correcto\n";
} finally {
    // Elimina únicamente el registro y archivo creados por esta prueba.
    if ($artifactId !== null) {
        $statement = $pdo->prepare(
            'DELETE FROM file_artifacts WHERE id = :id'
        );

        $statement->execute(['id' => $artifactId]);
    }

    if ($storedFile !== null && is_file($storedFile)) {
        if (!unlink($storedFile)) {
            throw new RuntimeException(
                'No se pudo eliminar el archivo temporal de la prueba.'
            );
        }
    }
}
