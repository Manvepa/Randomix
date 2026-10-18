<?php

declare(strict_types=1);

namespace Randomix\Import;

use InvalidArgumentException;
use RuntimeException;

final class StudentRecordsProcessor
{
    private const MAX_RECORDS = 20000;

    /**
     * Procesa las filas de una hoja cuyos encabezados ya fueron validados.
     *
     * Ignora filas completamente vacías, conserva las parciales y los
     * duplicados, y rechaza hojas sin registros o que superen el límite.
     *
     * @param array<int, string> $headers Encabezados validados.
     * @param array<int, array<int, mixed>> $rows Filas sin encabezado.
     *
     * @return array<int, array<string, mixed>> Registros asociados a encabezados.
     */
    public function process(array $headers, array $rows): array
    {
        // Comprueba que exista un esquema de columnas utilizable.
        if ($headers === []) {
            throw new InvalidArgumentException(
                'No se puede procesar una hoja sin encabezados.'
            );
        }

        foreach ($headers as $header) {
            if (!is_string($header) || trim($header) === '') {
                throw new InvalidArgumentException(
                    'Los encabezados deben ser textos no vacíos.'
                );
            }
        }

        if (count($headers) !== count(array_unique($headers))) {
            throw new InvalidArgumentException(
                'Los encabezados no pueden estar duplicados.'
            );
        }

        $records = [];
        $columnCount = count($headers);

        // Examina cada fila sin eliminar registros idénticos.
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new InvalidArgumentException(
                    'Cada fila debe ser un arreglo de valores.'
                );
            }

            // Una fila con más columnas que el esquema indica un error.
            if (count($row) > $columnCount) {
                throw new InvalidArgumentException(
                    'Una fila contiene más columnas que los encabezados.'
                );
            }

            // Ignora las filas cuyos valores estén todos vacíos.
            if ($this->isEmptyRow($row)) {
                continue;
            }

            // El límite se aplica solo a los registros no vacíos.
            if (count($records) >= self::MAX_RECORDS) {
                throw new RuntimeException(
                    'La hoja supera el máximo de 20000 registros.'
                );
            }

            // Completa las columnas faltantes sin descartar filas parciales.
            $normalizedRow = array_values($row);
            $normalizedRow = array_pad(
                $normalizedRow,
                $columnCount,
                null
            );

            // Relaciona cada valor con su encabezado original.
            $records[] = array_combine(
                $headers,
                $normalizedRow
            );
        }

        // No se permite continuar si la hoja no tiene estudiantes.
        if ($records === []) {
            throw new RuntimeException(
                'La hoja seleccionada no contiene registros.'
            );
        }

        return $records;
    }

    /**
     * Determina si todos los valores de una fila están vacíos.
     *
     * El cero y el valor false se consideran datos, no celdas vacías.
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value === null) {
                continue;
            }

            if (is_string($value) && trim($value) === '') {
                continue;
            }

            return false;
        }

        return true;
    }
}
