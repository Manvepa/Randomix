<?php

declare(strict_types=1);

namespace Randomix\Services;

use InvalidArgumentException;
use RuntimeException;

final class SelectionService
{
    /**
     * Realiza un único sorteo aleatorio y distribuye los registros
     * entre beneficiarios, suplentes y no seleccionados.
     *
     * Cada posición representa una fila independiente. Por eso,
     * los registros duplicados se conservan como filas distintas.
     *
     * @param array<int, array<string, mixed>> $records Registros procesados.
     * @param int $beneficiaryQuantity Cantidad de beneficiarios.
     * @param int $alternateQuantity Cantidad de suplentes.
     *
     * @return array{
     *     beneficiaries: array<int, array<string, mixed>>,
     *     alternates: array<int, array<string, mixed>>,
     *     not_selected: array<int, array<string, mixed>>
     * }
     */
    public function select(
        array $records,
        int $beneficiaryQuantity,
        int $alternateQuantity
    ): array {
        // Exige que existan registros disponibles para realizar el sorteo.
        if ($records === []) {
            throw new RuntimeException(
                'No hay registros disponibles para seleccionar.'
            );
        }

        $totalRecords = count($records);
        $totalRequested = $beneficiaryQuantity + $alternateQuantity;

        // Impide cantidades negativas.
        if ($beneficiaryQuantity < 0 || $alternateQuantity < 0) {
            throw new InvalidArgumentException(
                'Las cantidades de beneficiarios y suplentes no pueden ser negativas.'
            );
        }

        // Comprueba que la suma de las cantidades no supere los registros.
        if ($totalRequested > $totalRecords) {
            throw new InvalidArgumentException(
                'La cantidad total de beneficiarios y suplentes no puede superar '
                . $totalRecords
                . ' registros disponibles.'
            );
        }

        // Normaliza los índices para identificar cada fila por su posición.
        $records = array_values($records);

        // Mezcla las posiciones sin modificar ni perder los datos originales.
        $randomIndexes = range(0, $totalRecords - 1);
        shuffle($randomIndexes);

        // Reserva las primeras posiciones aleatorias para los beneficiarios
        // y las siguientes para los suplentes.
        $beneficiaryIndexes = array_slice(
            $randomIndexes,
            0,
            $beneficiaryQuantity
        );

        $alternateIndexes = array_slice(
            $randomIndexes,
            $beneficiaryQuantity,
            $alternateQuantity
        );

        // Crea conjuntos de índices para identificar cada grupo.
        $beneficiaryIndexSet = array_fill_keys(
            $beneficiaryIndexes,
            true
        );

        $alternateIndexSet = array_fill_keys(
            $alternateIndexes,
            true
        );

        $beneficiaries = [];
        $alternates = [];
        $notSelected = [];

        // Distribuye cada registro en un único grupo.
        // Beneficiarios y suplentes conservan el orden aleatorio del sorteo.
        foreach ($beneficiaryIndexes as $index) {
            $beneficiaries[] = $records[$index];
        }

        foreach ($alternateIndexes as $index) {
            $alternates[] = $records[$index];
        }

        // Conserva el orden original de los registros no seleccionados.
        foreach ($records as $index => $record) {
            if (
                isset($beneficiaryIndexSet[$index])
                || isset($alternateIndexSet[$index])
            ) {
                continue;
            }

            $notSelected[] = $record;
        }

        return [
            'beneficiaries' => $beneficiaries,
            'alternates' => $alternates,
            'not_selected' => $notSelected,
        ];
    }
}
