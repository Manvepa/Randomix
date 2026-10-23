<?php

declare(strict_types=1);

namespace Randomix\Services;

use InvalidArgumentException;
use RuntimeException;

final class ExcelMergeService
{
    private const MAX_RECORDS_PER_FILE = 20000;

    /**
     * Recibe el esquema oficial y valida que exista la columna doc.
     *
     * @param array<int, string> $requiredHeaders
     */
    public function __construct(
        private readonly array $requiredHeaders
    ) {
        if (
            $this->requiredHeaders === []
            || count($this->requiredHeaders)
                !== count(array_unique($this->requiredHeaders))
            || !in_array('doc', $this->requiredHeaders, true)
        ) {
            throw new InvalidArgumentException(
                'El esquema oficial de Excel no es válido.'
            );
        }
    }

    /**
     * Une las filas de dos archivos, conserva la primera aparición de
     * cada documento y omite filas vacías o sin documento.
     *
     * Los encabezados deben coincidir exactamente con el esquema oficial
     * y mantener el mismo orden en ambos archivos.
     *
     * @param array<int, string> $headersA
     * @param array<int, array<int, mixed>> $rowsA
     * @param array<int, string> $headersB
     * @param array<int, array<int, mixed>> $rowsB
     *
     * @return array{
     *     headers: array<int, string>,
     *     records: array<int, array<string, mixed>>,
     *     total_a: int,
     *     total_b: int,
     *     omitted_empty_rows: int,
     *     omitted_without_document: int,
     *     duplicates_removed: int,
     *     total_merged: int
     * }
     */
    
    /**
     * Une las filas de dos archivos, ignora la columna auxiliar #,
     * conserva la primera aparición de cada documento y omite filas
     * vacías o sin documento.
     *
     * @param array<int, string> $headersA
     * @param array<int, array<int, mixed>> $rowsA
     * @param array<int, string> $headersB
     * @param array<int, array<int, mixed>> $rowsB
     *
     * @return array{
     *     headers: array<int, string>,
     *     records: array<int, array<string, mixed>>,
     *     total_a: int,
     *     total_b: int,
     *     omitted_empty_rows: int,
     *     omitted_without_document: int,
     *     duplicates_removed: int,
     *     total_merged: int
     * }
     */
    public function merge(
        array $headersA,
        array $rowsA,
        array $headersB,
        array $rowsB
    ): array {
        // Elimina la columna auxiliar y su contenido, si existe.
        [$headersA, $rowsA] = $this->removeAuxiliaryColumn(
            $headersA,
            $rowsA
        );

        [$headersB, $rowsB] = $this->removeAuxiliaryColumn(
            $headersB,
            $rowsB
        );

        // Valida el esquema oficial después de quitar la columna auxiliar.
        $this->validateHeaders($headersA, $headersB);

        // Limita el volumen de entrada de cada archivo.
        if (
            count($rowsA) > self::MAX_RECORDS_PER_FILE
            || count($rowsB) > self::MAX_RECORDS_PER_FILE
        ) {
            throw new RuntimeException(
                'Uno de los archivos supera el límite de 20000 filas.'
            );
        }

        $headers = $headersA;
        $documentIndex = array_search('doc', $headers, true);

        if ($documentIndex === false) {
            throw new RuntimeException(
                'No se encontró la columna doc.'
            );
        }

        $records = [];
        $seenDocuments = [];
        $omittedEmptyRows = 0;
        $omittedWithoutDocument = 0;
        $duplicatesRemoved = 0;

        // Procesa A primero para dar prioridad a sus registros.
        foreach ([$rowsA, $rowsB] as $rows) {
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    throw new InvalidArgumentException(
                        'Una fila tiene una estructura no válida.'
                    );
                }

                if (count($row) > count($headers)) {
                    throw new InvalidArgumentException(
                        'Una fila contiene más columnas que los encabezados.'
                    );
                }

                // Omite filas completamente vacías.
                if ($this->isEmptyRow($row)) {
                    $omittedEmptyRows++;
                    continue;
                }

                // Completa las columnas que falten sin alterar su orden.
                $row = array_pad(
                    array_values($row),
                    count($headers),
                    null
                );

                // Omite filas sin documento.
                $document = $row[$documentIndex] ?? null;

                if (
                    $document === null
                    || trim((string) $document) === ''
                ) {
                    $omittedWithoutDocument++;
                    continue;
                }

                // Normaliza espacios exteriores para comparar documentos.
                $documentKey = trim((string) $document);

                if (isset($seenDocuments[$documentKey])) {
                    $duplicatesRemoved++;
                    continue;
                }

                $seenDocuments[$documentKey] = true;

                // Asocia los valores con los encabezados oficiales.
                $records[] = array_combine($headers, $row);
            }
        }

        return [
            'headers' => $headers,
            'records' => $records,
            'total_a' => count($rowsA),
            'total_b' => count($rowsB),
            'omitted_empty_rows' => $omittedEmptyRows,
            'omitted_without_document' => $omittedWithoutDocument,
            'duplicates_removed' => $duplicatesRemoved,
            'total_merged' => count($records),
        ];
    }


    /**
     * Elimina la columna auxiliar # y la celda correspondiente
     * de cada fila. Si no existe, conserva los datos originales.
     *
     * @param array<int, string> $headers
     * @param array<int, array<int, mixed>> $rows
     *
     * @return array{
     *     0: array<int, string>,
     *     1: array<int, array<int, mixed>>
     * }
     */
    private function removeAuxiliaryColumn(
        array $headers,
        array $rows
    ): array {
        // Busca únicamente un encabezado que sea exactamente #.
        $auxiliaryIndex = array_search('#', $headers, true);

        if ($auxiliaryIndex === false) {
            return [$headers, $rows];
        }

        // Elimina la columna auxiliar de los encabezados.
        array_splice($headers, $auxiliaryIndex, 1);

        // Elimina la misma posición de cada fila para evitar desplazamientos.
        foreach ($rows as &$row) {
            if (is_array($row)) {
                array_splice($row, $auxiliaryIndex, 1);
            }
        }

        unset($row);

        return [$headers, $rows];
    }

    /**
     * Comprueba que ambos archivos tengan exactamente el esquema oficial
     * y que sus encabezados coincidan en el mismo orden.
     */
    private function validateHeaders(
        array $headersA,
        array $headersB
    ): void {
        if (
            $headersA !== $this->requiredHeaders
            || $headersB !== $this->requiredHeaders
        ) {
            throw new RuntimeException(
                'Los archivos deben tener los encabezados oficiales '
                . 'exactamente iguales y en el mismo orden.'
            );
        }
    }

    /**
     * Determina si una fila está completamente vacía.
     *
     * El cero y false se consideran valores válidos.
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
