<?php

declare(strict_types=1);

namespace Randomix\Services;

use InvalidArgumentException;
use Randomix\Import\ExcelReader;
use Randomix\Validators\ExcelHeadersValidator;
use RuntimeException;

final class ExcelComparisonService
{
    public function __construct(
        private readonly array $requiredHeaders,
        private readonly ExcelReader $excelReader = new ExcelReader(),
        private readonly int $maxRows = 20000
    ) {
        if ($this->maxRows <= 0) {
            throw new InvalidArgumentException(
                'El máximo de registros debe ser mayor que cero.'
            );
        }
    }

    /**
     * Compara los documentos del Excel A con los del Excel B.
     *
     * El Excel A debe cumplir el esquema oficial de Randomix.
     * El Excel B solo necesita una columna llamada exactamente "doc".
     *
     * Los archivos originales nunca se modifican.
     *
     * @return array{
     *     headers: array<int, string>,
     *     records: array<int, array<string, mixed>>,
     *     total_original: int,
     *     total_previous: int,
     *     coincidences_removed: int,
     *     remaining: int
     * }
     */
    public function compare(
        string $fileA,
        string $sheetA,
        string $fileB,
        string $sheetB
    ): array {
        // Lee y valida la estructura del archivo principal.
        $headersA = $this->excelReader->getHeaders($fileA, $sheetA);

        $validator = new ExcelHeadersValidator($this->requiredHeaders);
        $validation = $validator->validate($headersA);

        if (!$validation['valid']) {
            throw new InvalidArgumentException(
                'El Excel principal no cumple los encabezados oficiales. '
                . 'Faltantes: ' . implode(', ', $validation['missing'])
                . '. Adicionales: ' . implode(', ', $validation['extra'])
                . '. Duplicados: ' . implode(', ', $validation['duplicates'])
            );
        }

        // Identifica la posición de la columna doc del archivo principal.
        $docIndexA = array_search('doc', $headersA, true);

        if ($docIndexA === false) {
            throw new RuntimeException(
                'No se encontró la columna doc en el Excel principal.'
            );
        }

        // Valida el archivo disparador sin exigirle el esquema oficial.
        $headersB = $this->excelReader->getHeaders($fileB, $sheetB);

        $docIndexesB = [];

        foreach ($headersB as $index => $header) {
            if ($header === 'doc') {
                $docIndexesB[] = $index;
            }
        }

        if (count($docIndexesB) !== 1) {
            throw new InvalidArgumentException(
                'El Excel de comparación debe contener exactamente '
                . 'una columna llamada doc.'
            );
        }

        $docIndexB = $docIndexesB[0];

        // Construye un conjunto de documentos para buscar coincidencias.
        $rowsB = $this->excelReader->getRows($fileB, $sheetB);
        // Cuenta únicamente las filas que contienen algún dato.
        $recordCountB = 0;

        foreach ($rowsB as $row) {
            if (!$this->isEmptyRow($row)) {
                $recordCountB++;
            }
        }

        // Rechaza el archivo disparador si supera el límite permitido.
        if ($recordCountB > $this->maxRows) {
            throw new InvalidArgumentException(
                "El Excel B supera el máximo de {$this->maxRows} registros."
            );
        }
        $documentsToRemove = [];

        foreach ($rowsB as $row) {
            $value = $row[$docIndexB] ?? null;

            // Ignora filas vacías y documentos sin valor.
            if ($this->isEmptyRow($row)) {
                continue;
            }

            $document = $this->normalizeDocument($value);

            if ($document !== '') {
                $documentsToRemove[$document] = true;
            }
        }

        // Lee el Excel principal y prepara los registros restantes.
        $rowsA = $this->excelReader->getRows($fileA, $sheetA);

        // Cuenta los registros antes de eliminar las coincidencias.
        $recordCountA = 0;

        foreach ($rowsA as $row) {
            if (!$this->isEmptyRow($row)) {
                $recordCountA++;
            }
        }

        // Rechaza el archivo principal si supera el límite permitido.
        if ($recordCountA > $this->maxRows) {
            throw new InvalidArgumentException(
                "El Excel A supera el máximo de {$this->maxRows} registros."
            );
        }

        $records = [];
        $totalOriginal = 0;
        $coincidencesRemoved = 0;

        foreach ($rowsA as $row) {
            // No cuenta filas completamente vacías como registros.
            if ($this->isEmptyRow($row)) {
                continue;
            }

            $totalOriginal++;

            $document = $this->normalizeDocument(
                $row[$docIndexA] ?? null
            );

            // Elimina cada fila cuyo documento aparezca en el Excel B.
            if (
                $document !== ''
                && isset($documentsToRemove[$document])
            ) {
                $coincidencesRemoved++;
                continue;
            }

            // Convierte la fila numérica al formato esperado por el exportador.
            $record = [];

            foreach ($headersA as $index => $header) {
                $record[$header] = $row[$index] ?? null;
            }

            $records[] = $record;
        }

        // Devuelve los registros filtrados y las estadísticas.
        return [
            'headers' => $headersA,
            'records' => $records,
            'total_original' => $totalOriginal,
            'total_previous' => $recordCountB,
            'coincidences_removed' => $coincidencesRemoved,
            'remaining' => count($records),
        ];
    }

    /**
     * Normaliza un documento sin eliminar ceros ni otros caracteres.
     */
    private function normalizeDocument(mixed $value): string
    {
        if ($value === null || !is_scalar($value)) {
            return '';
        }

        return trim((string) $value);
    }

    /**
     * Determina si una fila carece de datos en todas sus columnas.
     */
    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (
                $value !== null
                && (!is_string($value) || trim($value) !== '')
            ) {
                return false;
            }
        }

        return true;
    }
}
