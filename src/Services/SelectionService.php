<?php

declare(strict_types=1);

namespace Randomix\Services;

use InvalidArgumentException;
use RuntimeException;

final class SelectionService
{
    /**
     * Selecciona registros aleatoriamente y separa los restantes.
     *
     * Cada posición representa una fila independiente. Por eso, los
     * registros duplicados se conservan y pueden seleccionarse por separado.
     *
     * @param array<int, array<string, mixed>> $records Registros procesados.
     * @param int $quantity Cantidad de registros que se desea seleccionar.
     *
     * @return array{
     *     selected: array<int, array<string, mixed>>,
     *     not_selected: array<int, array<string, mixed>>
     * }
     */
    public function select(array $records, int $quantity): array
    {
        // No se puede realizar una selección sin registros disponibles.
        if ($records === []) {
            throw new RuntimeException(
                'No hay registros disponibles para seleccionar.'
            );
        }

        $totalRecords = count($records);

        // La cantidad solicitada debe estar dentro del rango disponible.
        if ($quantity < 0 || $quantity > $totalRecords) {
            throw new InvalidArgumentException(
                'La cantidad debe estar entre 0 y '
                . $totalRecords
                . '.'
            );
        }

        // Normaliza los índices para identificar cada fila por su posición.
        $records = array_values($records);

        // Selecciona posiciones aleatorias sin repetir ninguna.
        $selectedIndexes = $quantity === 0
            ? []
            : array_rand($records, $quantity);

        // array_rand devuelve un entero cuando solo se elige una posición.
        if (!is_array($selectedIndexes)) {
            $selectedIndexes = [$selectedIndexes];
        }

        // Crea un conjunto de índices para consultar la selección rápidamente.
        $selectedIndexSet = array_fill_keys($selectedIndexes, true);

        $selected = [];
        $notSelected = [];

        // Distribuye cada fila en un solo grupo, sin eliminar duplicados.
        foreach ($records as $index => $record) {
            if (isset($selectedIndexSet[$index])) {
                $selected[] = $record;
                continue;
            }

            $notSelected[] = $record;
        }

        return [
            'selected' => $selected,
            'not_selected' => $notSelected,
        ];
    }
}
