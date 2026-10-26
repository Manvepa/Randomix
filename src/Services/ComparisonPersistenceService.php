<?php

declare(strict_types=1);

namespace Randomix\Services;

use PDO;
use RuntimeException;
use Throwable;

final class ComparisonPersistenceService
{
    public function __construct(
        private readonly PDO $connection
    ) {
    }

    /**
     * Guarda una comparación exitosa y crea una nueva versión
     * del conjunto de estudiantes dentro de una única transacción.
     *
     * Si falla alguna inserción, revierte todos los cambios.
     */
    public function persist(
        array $result,
        int $previousYear,
        int $currentYear,
        int $adminId
    ): array {
        $headers = $result['headers'] ?? null;
        $records = $result['records'] ?? null;

        if (
            !is_array($headers)
            || !is_array($records)
            || $previousYear < 1900
            || $currentYear <= $previousYear
            || $adminId <= 0
        ) {
            throw new RuntimeException(
                'Los datos de la comparación no son válidos para guardarlos.'
            );
        }

        $this->connection->beginTransaction();

        try {
            // Registra la ejecución de comparación.
            $statement = $this->connection->prepare(
                'INSERT INTO comparison_runs (
                    name,
                    previous_year,
                    current_year,
                    status,
                    previous_rows_count,
                    current_rows_count,
                    result_rows_count,
                    started_at,
                    completed_at,
                    created_by
                ) VALUES (
                    :name,
                    :previous_year,
                    :current_year,
                    :status,
                    :previous_count,
                    :current_count,
                    :result_count,
                    NOW(),
                    NOW(),
                    :created_by
                )'
            );

            $statement->execute([
                'name' => "Comparación {$previousYear}-{$currentYear}",
                'previous_year' => $previousYear,
                'current_year' => $currentYear,
                'status' => 'completed',
                'previous_count' => (int) ($result['total_previous'] ?? 0),
                'current_count' => (int) ($result['total_original'] ?? 0),
                'result_count' => count($records),
                'created_by' => $adminId,
            ]);

            $comparisonId = (int) $this->connection->lastInsertId();

            // Busca la serie existente; si no existe, crea la primera.
            $statement = $this->connection->prepare(
                'SELECT id
                 FROM student_dataset_series
                 ORDER BY id
                 LIMIT 1
                 FOR UPDATE'
            );
            $statement->execute();

            $seriesId = $statement->fetchColumn();

            if ($seriesId === false) {
                $statement = $this->connection->prepare(
                    'INSERT INTO student_dataset_series (name, created_by)
                     VALUES (:name, :created_by)'
                );

                $statement->execute([
                    'name' => 'Estudiantes elegibles para selección aleatoria',
                    'created_by' => $adminId,
                ]);

                $seriesId = $this->connection->lastInsertId();
            }

            $seriesId = (int) $seriesId;

            // Bloquea la serie para evitar asignaciones simultáneas
            // de una misma versión durante la persistencia.
            $statement = $this->connection->prepare(
                'SELECT id
                 FROM student_dataset_series
                 WHERE id = :series_id
                 FOR UPDATE'
            );

            $statement->execute(['series_id' => $seriesId]);

            if ($statement->fetchColumn() === false) {
                throw new RuntimeException(
                    'No se encontró la serie de estudiantes para asignar una versión.'
                );
            }

            // Calcula la siguiente versión mientras la serie permanece bloqueada.
            $statement = $this->connection->prepare(
                'SELECT COALESCE(MAX(version_number), 0) + 1
                 FROM student_datasets
                 WHERE series_id = :series_id'
            );

            $statement->execute(['series_id' => $seriesId]);

            $version = (int) $statement->fetchColumn();


            // Crea el conjunto asociado exclusivamente a esta comparación.
            $statement = $this->connection->prepare(
                'INSERT INTO student_datasets (
                    series_id,
                    version_number,
                    parent_dataset_id,
                    source_type,
                    source_file_id,
                    source_selection_run_id,
                    row_count,
                    status,
                    created_by,
                    source_comparison_run_id
                ) VALUES (
                    :series_id,
                    :version_number,
                    :parent_dataset_id,
                    :source_type,
                    NULL,
                    NULL,
                    :row_count,
                    :status,
                    :created_by,
                    :comparison_id
                )'
            );

            $statement->execute([
                'series_id' => $seriesId,
                'version_number' => $version,
                'parent_dataset_id' => null,
                'source_type' => 'comparison',
                'row_count' => count($records),
                'status' => 'active',
                'created_by' => $adminId,
                'comparison_id' => $comparisonId,
            ]);

            $datasetId = (int) $this->connection->lastInsertId();

            // Prepara una inserción reutilizable para todos los encabezados.
            $headerStatement = $this->connection->prepare(
                'INSERT INTO student_dataset_headers (
                    dataset_id,
                    column_name,
                    column_position,
                    created_by
                ) VALUES (
                    :dataset_id,
                    :column_name,
                    :column_position,
                    :created_by
                )'
            );

            foreach ($headers as $position => $header) {
                if (!is_string($header)) {
                    throw new RuntimeException(
                        'Se encontró un encabezado no válido.'
                    );
                }

                $headerStatement->execute([
                    'dataset_id' => $datasetId,
                    'column_name' => $header,
                    'column_position' => $position + 1,
                    'created_by' => $adminId,
                ]);
            }

            // Prepara una inserción reutilizable para los estudiantes.
            $rowStatement = $this->connection->prepare(
                'INSERT INTO student_dataset_rows (
                    dataset_id,
                    document_type,
                    document_number_normalized,
                    student_data,
                    created_by
                ) VALUES (
                    :dataset_id,
                    :document_type,
                    :document_number,
                    :student_data,
                    :created_by
                )'
            );

            foreach ($records as $record) {
                if (!is_array($record)) {
                    throw new RuntimeException(
                        'Se encontró un registro de estudiante no válido.'
                    );
                }

                $documentType = $record['tipo_doc'] ?? null;
                $documentNumber = $record['doc'] ?? null;

                $json = json_encode(
                    $record,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                    | JSON_THROW_ON_ERROR
                );

                $rowStatement->execute([
                    'dataset_id' => $datasetId,
                    'document_type' => is_scalar($documentType)
                        ? (string) $documentType
                        : null,
                    'document_number' => is_scalar($documentNumber)
                        ? trim((string) $documentNumber)
                        : null,
                    'student_data' => $json,
                    'created_by' => $adminId,
                ]);
            }

            $this->connection->commit();

            return [
                'comparison_id' => $comparisonId,
                'dataset_id' => $datasetId,
                'version_number' => $version,
                'row_count' => count($records),
            ];
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * Obtiene una muestra de los datos guardados para verificar la persistencia.
     *
     * Limita la consulta a cuatro estudiantes para evitar cargar grandes volúmenes
     * de información durante las pruebas.
     */
    public function getDebugPreview(int $datasetId): array
    {
        // Recupera los datos generales de la versión almacenada.
        $datasetStatement = $this->connection->prepare(
            'SELECT id, series_id, version_number, row_count, status
         FROM student_datasets
         WHERE id = :dataset_id'
        );

        $datasetStatement->execute([
            'dataset_id' => $datasetId,
        ]);

        $dataset = $datasetStatement->fetch(PDO::FETCH_ASSOC);

        if ($dataset === false) {
            throw new RuntimeException(
                'No se encontró el conjunto de estudiantes guardado.'
            );
        }

        // Recupera los encabezados en el orden en que fueron almacenados.
        $headersStatement = $this->connection->prepare(
            'SELECT column_name, column_position
         FROM student_dataset_headers
         WHERE dataset_id = :dataset_id
         ORDER BY column_position'
        );

        $headersStatement->execute([
            'dataset_id' => $datasetId,
        ]);

        $headers = $headersStatement->fetchAll(PDO::FETCH_ASSOC);

        // Recupera únicamente los primeros cuatro estudiantes.
        $rowsStatement = $this->connection->prepare(
            'SELECT id, document_type, document_number_normalized, student_data
         FROM student_dataset_rows
         WHERE dataset_id = :dataset_id
         ORDER BY id
         LIMIT 4'
        );

        $rowsStatement->execute([
            'dataset_id' => $datasetId,
        ]);

        $rows = $rowsStatement->fetchAll(PDO::FETCH_ASSOC);

        // Convierte el JSON almacenado en cada fila en un arreglo legible.
        foreach ($rows as &$row) {
            $row['student_data'] = json_decode(
                $row['student_data'],
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        }

        unset($row);

        return [
            'dataset' => $dataset,
            'headers' => $headers,
            'first_four_rows' => $rows,
        ];
    }
}
