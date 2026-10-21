<?php

declare(strict_types=1);

namespace Randomix\Services;

use InvalidArgumentException;

/**
 * Identifica estudiantes que comparten el mismo par de apellidos.
 *
 * Devuelve los índices de los registros coincidentes, sin modificar
 * los datos originales ni alterar el orden de los estudiantes.
 */
final class SurnameMatchService
{
    /**
     * Encuentra los índices de los registros que comparten ambos apellidos.
     *
     * La comparación ignora mayúsculas, minúsculas y espacios exteriores.
     * Se permite que falte uno de los apellidos, pero no ambos.
     *
     * @param array<int, array<string, mixed>> $records Registros por analizar.
     *
     * @return array<int, int> Índices originales de los registros coincidentes.
     */
    public function findMatchingIndexes(array $records): array
    {
        $groups = [];

        // Agrupa los registros por su par de apellidos normalizado.
        foreach ($records as $index => $record) {
            if (!is_array($record)) {
                throw new InvalidArgumentException(
                    'Todos los registros deben tener una estructura válida.'
                );
            }

            $surname1 = $this->normalizeSurname(
                $record['apellido_1'] ?? ''
            );

            $surname2 = $this->normalizeSurname(
                $record['apellido_2'] ?? ''
            );

            // Ignora los registros que no tienen ninguno de los dos apellidos.
            if ($surname1 === '' && $surname2 === '') {
                continue;
            }

            // Serializar el par evita ambigüedades al combinar los apellidos.
            $key = json_encode(
                [$surname1, $surname2],
                JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );

            $groups[$key][] = $index;
        }

        $matchingIndexes = [];

        // Conserva todos los integrantes de cada grupo con dos o más registros.
        foreach ($groups as $indexes) {
            if (count($indexes) < 2) {
                continue;
            }

            foreach ($indexes as $index) {
                $matchingIndexes[] = $index;
            }
        }

        // Devuelve los índices en el orden original de los registros.
        sort($matchingIndexes, SORT_NUMERIC);

        return $matchingIndexes;
    }

    /**
     * Normaliza un apellido para realizar comparaciones consistentes.
     */
    private function normalizeSurname(mixed $surname): string
    {
        if (!is_string($surname) && !is_numeric($surname)) {
            return '';
        }

        return mb_strtolower(trim((string) $surname), 'UTF-8');
    }
}
