<?php

declare(strict_types=1);

namespace Randomix\Services;

use PDO;
use RuntimeException;

final class ComparisonPersistenceService
{
    public function __construct(
        private readonly PDO $connection
    ) {
    }

    /**
     * Registra una comparación exitosa en la tabla comparison_runs.
     *
     * Los archivos deben estar previamente registrados en file_artifacts.
     * Este servicio no genera archivos ni modifica otras tablas.
     */
    public function persist(
        array $result,
        int $fileAId,
        int $fileBId,
        int $resultFileId,
        string $sheetA,
        string $sheetB
    ): array {
        $records = $result['records'] ?? null;
        $totalOriginal = $result['total_original'] ?? null;
        $coincidencesRemoved = $result['coincidences_removed'] ?? null;

        // Valida los identificadores y las hojas de la comparación.
        if (
            $fileAId <= 0
            || $fileBId <= 0
            || $resultFileId <= 0
            || trim($sheetA) === ''
            || trim($sheetB) === ''
        ) {
            throw new RuntimeException(
                'Los archivos y las hojas de la comparación no son válidos.'
            );
        }

        // Verifica que el resultado tenga la estructura esperada.
        if (
            !is_array($records)
            || !is_int($totalOriginal)
            || !is_int($coincidencesRemoved)
            || $totalOriginal < 0
            || $coincidencesRemoved < 0
            || $coincidencesRemoved > $totalOriginal
        ) {
            throw new RuntimeException(
                'Las estadísticas de la comparación no son válidas.'
            );
        }

        $remaining = count($records);

        // Comprueba que las estadísticas coincidan con el resultado.
        if ($totalOriginal - $coincidencesRemoved !== $remaining) {
            throw new RuntimeException(
                'Las estadísticas no coinciden con los registros resultantes.'
            );
        }

        // Registra la ejecución utilizando las columnas reales de la tabla.
        $statement = $this->connection->prepare(
            'INSERT INTO comparison_runs (
                file_a_id,
                file_b_id,
                result_file_id,
                sheet_a,
                sheet_b,
                total_original,
                coincidences_removed,
                remaining
            ) VALUES (
                :file_a_id,
                :file_b_id,
                :result_file_id,
                :sheet_a,
                :sheet_b,
                :total_original,
                :coincidences_removed,
                :remaining
            )'
        );

        $statement->execute([
            'file_a_id' => $fileAId,
            'file_b_id' => $fileBId,
            'result_file_id' => $resultFileId,
            'sheet_a' => $sheetA,
            'sheet_b' => $sheetB,
            'total_original' => $totalOriginal,
            'coincidences_removed' => $coincidencesRemoved,
            'remaining' => $remaining,
        ]);

        return [
            'comparison_id' => (int) $this->connection->lastInsertId(),
            'file_a_id' => $fileAId,
            'file_b_id' => $fileBId,
            'result_file_id' => $resultFileId,
            'total_original' => $totalOriginal,
            'coincidences_removed' => $coincidencesRemoved,
            'remaining' => $remaining,
        ];
    }
}
