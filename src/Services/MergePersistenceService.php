<?php

declare(strict_types=1);

namespace Randomix\Services;

use PDO;
use RuntimeException;

final class MergePersistenceService
{
    public function __construct(
        private readonly PDO $connection
    ) {
    }

    /**
     * Valida y registra una ejecución de unión de archivos Excel.
     *
     * @return array{
     *     merge_id: int,
     *     file_a_id: int,
     *     file_b_id: int,
     *     result_file_id: int,
     *     total_merged: int
     * }
     */
    public function persist(
        array $result,
        int $fileAId,
        int $fileBId,
        int $resultFileId,
        string $sheetA,
        string $sheetB
    ): array {
        // Verifica que las referencias de los tres archivos sean válidas.
        if ($fileAId <= 0 || $fileBId <= 0 || $resultFileId <= 0) {
            throw new RuntimeException(
                'Los identificadores de los archivos deben ser positivos.'
            );
        }

        // Verifica que se hayan indicado las dos hojas de origen.
        if (trim($sheetA) === '' || trim($sheetB) === '') {
            throw new RuntimeException(
                'Los nombres de las hojas no pueden estar vacíos.'
            );
        }

        // Comprueba que el servicio de unión haya entregado los registros.
        if (!isset($result['records']) || !is_array($result['records'])) {
            throw new RuntimeException(
                'El resultado de la unión no contiene registros válidos.'
            );
        }

        // Obtiene las estadísticas necesarias para el historial.
        $statisticKeys = [
            'total_a',
            'total_b',
            'omitted_empty_rows',
            'omitted_without_document',
            'duplicates_removed',
            'total_merged',
        ];

        $statistics = [];

        foreach ($statisticKeys as $key) {
            $value = $result[$key] ?? null;

            if (!is_int($value) || $value < 0) {
                throw new RuntimeException(
                    "La estadística {$key} no es válida."
                );
            }

            $statistics[$key] = $value;
        }

        // Evita registrar cantidades que no coincidan con el resultado real.
        if ($statistics['total_merged'] !== count($result['records'])) {
            throw new RuntimeException(
                'El total unido no coincide con la cantidad de registros.'
            );
        }

        // Inserta la ejecución usando los nombres reales de la tabla.
        $sql = <<<'SQL'
            INSERT INTO excel_merge_runs (
                file_a_id,
                file_b_id,
                result_file_id,
                sheet_a,
                sheet_b,
                total_a,
                total_b,
                omitted_empty_rows,
                omitted_without_document,
                duplicates_removed,
                total_merged
            ) VALUES (
                :file_a_id,
                :file_b_id,
                :result_file_id,
                :sheet_a,
                :sheet_b,
                :total_a,
                :total_b,
                :omitted_empty_rows,
                :omitted_without_document,
                :duplicates_removed,
                :total_merged
            )
            SQL;

        $statement = $this->connection->prepare($sql);

        $statement->execute([
            'file_a_id' => $fileAId,
            'file_b_id' => $fileBId,
            'result_file_id' => $resultFileId,
            'sheet_a' => trim($sheetA),
            'sheet_b' => trim($sheetB),
            'total_a' => $statistics['total_a'],
            'total_b' => $statistics['total_b'],
            'omitted_empty_rows' => $statistics['omitted_empty_rows'],
            'omitted_without_document' => $statistics['omitted_without_document'],
            'duplicates_removed' => $statistics['duplicates_removed'],
            'total_merged' => $statistics['total_merged'],
        ]);

        // Devuelve los identificadores para conservarlos en la sesión.
        return [
            'merge_id' => (int) $this->connection->lastInsertId(),
            'file_a_id' => $fileAId,
            'file_b_id' => $fileBId,
            'result_file_id' => $resultFileId,
            'total_merged' => $statistics['total_merged'],
        ];
    }
}
