<?php

declare(strict_types=1);

namespace Randomix\Services;

use PDO;
use RuntimeException;
use Throwable;

final class SelectionPersistenceService
{
    private PDO $connection;

    /**
     * Recibe la conexión PDO utilizada por Randomix.
     */
    public function __construct(PDO $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Registra una selección completada y sus archivos asociados.
     *
     * @param array{
     *     source_file_id: int,
     *     selected_file_id: int,
     *     not_selected_file_id: int,
     *     alternates_file_id: int,
     *     sheet_name: string,
     *     requested_beneficiaries: int,
     *     requested_alternates: int,
     *     total_records: int,
     *     beneficiaries_count: int,
     *     alternates_count: int,
     *     not_selected_count: int
     * } $data
     *
     * @return int Identificador de la ejecución registrada.
     */
    public function persist(array $data): int
    {
        // Valida los identificadores y las cantidades antes de insertar.
        $integerFields = [
            'source_file_id',
            'selected_file_id',
            'not_selected_file_id',
            'alternates_file_id',
            'requested_beneficiaries',
            'requested_alternates',
            'total_records',
            'beneficiaries_count',
            'alternates_count',
            'not_selected_count',
        ];

        foreach ($integerFields as $field) {
            if (
                !isset($data[$field])
                || !is_int($data[$field])
                || $data[$field] < 0
            ) {
                throw new RuntimeException(
                    'El dato de selección "' . $field . '" no es válido.'
                );
            }
        }

        // Los identificadores de archivo deben ser positivos.
        foreach ([
            'source_file_id',
            'selected_file_id',
            'not_selected_file_id',
            'alternates_file_id',
        ] as $field) {
            if ($data[$field] <= 0) {
                throw new RuntimeException(
                    'Los identificadores de archivos deben ser positivos.'
                );
            }
        }

        // Valida el nombre de la hoja.
        if (
            !isset($data['sheet_name'])
            || !is_string($data['sheet_name'])
            || trim($data['sheet_name']) === ''
            || mb_strlen($data['sheet_name']) > 255
        ) {
            throw new RuntimeException(
                'El nombre de la hoja de selección no es válido.'
            );
        }

        // Comprueba que los grupos concuerden con el total procesado.
        if (
            $data['beneficiaries_count'] + $data['alternates_count']
            + $data['not_selected_count'] !== $data['total_records']
        ) {
            throw new RuntimeException(
                'Las cantidades de la selección no coinciden con el total de registros.'
            );
        }

        try {
            // Inserta una única fila para la ejecución completada.
            $statement = $this->connection->prepare(
                'INSERT INTO selection_runs (
                    source_file_id,
                    selected_file_id,
                    not_selected_file_id,
                    alternates_file_id,
                    sheet_name,
                    requested_beneficiaries,
                    requested_alternates,
                    total_records,
                    beneficiaries_count,
                    alternates_count,
                    not_selected_count
                ) VALUES (
                    :source_file_id,
                    :selected_file_id,
                    :not_selected_file_id,
                    :alternates_file_id,
                    :sheet_name,
                    :requested_beneficiaries,
                    :requested_alternates,
                    :total_records,
                    :beneficiaries_count,
                    :alternates_count,
                    :not_selected_count
                )'
            );

            $statement->execute([
                'source_file_id' => $data['source_file_id'],
                'selected_file_id' => $data['selected_file_id'],
                'not_selected_file_id' => $data['not_selected_file_id'],
                'alternates_file_id' => $data['alternates_file_id'],
                'sheet_name' => trim($data['sheet_name']),
                'requested_beneficiaries' => $data['requested_beneficiaries'],
                'requested_alternates' => $data['requested_alternates'],
                'total_records' => $data['total_records'],
                'beneficiaries_count' => $data['beneficiaries_count'],
                'alternates_count' => $data['alternates_count'],
                'not_selected_count' => $data['not_selected_count'],
            ]);

            // Devuelve el ID de la ejecución recién creada.
            $runId = (int) $this->connection->lastInsertId();

            if ($runId <= 0) {
                throw new RuntimeException(
                    'No fue posible obtener el identificador del historial.'
                );
            }

            return $runId;
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'No fue posible registrar el historial de selección.',
                0,
                $exception
            );
        }
    }
}
