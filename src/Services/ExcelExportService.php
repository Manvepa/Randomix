<?php

declare(strict_types=1);

namespace Randomix\Services;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;
use Throwable;

final class ExcelExportService
{
    /**
     * Genera un archivo XLSX en memoria a partir de los registros recibidos.
     *
     * Los encabezados se reciben por separado para permitir exportar
     * grupos vacíos sin perder la estructura original.
     *
     * @param array $headers Encabezados en el orden deseado.
     * @param array $records Registros indexados por nombre de encabezado.
     *
     * @throws RuntimeException Si los datos o la generación no son válidos.
     */
    public function generate(
        array $headers,
        array $records,
        array $highlightedIndexes = [],
        array $alternateIndexes = []
    ): string {
        if ($headers === []) {
            throw new RuntimeException(
                'No hay encabezados disponibles para generar el Excel.'
            );
        }

        // Valida los encabezados.
        foreach ($headers as $header) {
            if (!is_string($header) || trim($header) === '') {
                throw new RuntimeException(
                    'La estructura de los encabezados no es válida.'
                );
            }
        }

        // Evita ambigüedades al acceder a los registros por nombre.
        if (count(array_unique($headers)) !== count($headers)) {
            throw new RuntimeException(
                'Los encabezados no pueden estar duplicados.'
            );
        }

        $spreadsheet = new Spreadsheet();

        try {
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Estudiantes');

            // Escribe los encabezados como texto explícito.
            foreach (array_values($headers) as $columnIndex => $header) {
                $sheet->setCellValueExplicit(
                    [$columnIndex + 1, 1],
                    $header,
                    DataType::TYPE_STRING
                );
            }

            // Escribe los registros respetando el orden de los encabezados.
            foreach (array_values($records) as $rowIndex => $record) {
                if (!is_array($record)) {
                    throw new RuntimeException(
                        'Uno de los registros no tiene una estructura válida.'
                    );
                }

                foreach (array_values($headers) as $columnIndex => $header) {
                    $value = $record[$header] ?? null;

                    $cellCoordinate = [
                        $columnIndex + 1,
                        $rowIndex + 2,
                    ];

                    // Conserva el DANE como texto para evitar la notación
                    // científica y la pérdida de ceros iniciales.
                    if ($header === 'DANE' && $value !== null) {
                        if (
                            !is_string($value)
                            && !is_int($value)
                            && !is_float($value)
                        ) {
                            throw new RuntimeException(
                                'El valor del DANE no tiene un tipo compatible.'
                            );
                        }

                        $sheet->setCellValueExplicit(
                            $cellCoordinate,
                            (string) $value,
                            DataType::TYPE_STRING
                        );

                        continue;
                    }

                    // Rechaza tipos no compatibles con las celdas.
                    if (
                        $value !== null
                        && !is_string($value)
                        && !is_int($value)
                        && !is_float($value)
                        && !is_bool($value)
                    ) {
                        throw new RuntimeException(
                            'Uno de los valores del Excel no tiene un tipo compatible.'
                        );
                    }

                    // Evita que textos importados se interpreten como fórmulas.
                    if (
                        is_string($value)
                        && preg_match('/^[\s]*[=+\-@]/u', $value) === 1
                    ) {
                        $sheet->setCellValueExplicit(
                            $cellCoordinate,
                            $value,
                            DataType::TYPE_STRING
                        );

                        continue;
                    }

                    // Trata los números de esta columna como fechas seriales
                    // de Excel, siempre que el origen garantice ese formato.
                    if (
                        $header === 'FECHA_INGRESO AL MUNICIPIO'
                        && (is_int($value) || is_float($value))
                        && is_finite((float) $value)
                        && $value > 0
                    ) {
                        $sheet->setCellValue(
                            $cellCoordinate,
                            $value
                        );

                        $sheet->getStyle(
                            $sheet->getCell($cellCoordinate)->getCoordinate()
                        )
                            ->getNumberFormat()
                            ->setFormatCode('dd/mm/yyyy');

                        continue;
                    }

                    // Escribe los valores escalares restantes.
                    $sheet->setCellValue(
                        $cellCoordinate,
                        $value
                    );
                }
            }

            // Formato de encabezados.
            $lastColumn = $sheet->getHighestColumn();

            // Valida los índices de hermanos y suplentes y prepara búsquedas rápidas.
            $highlightedIndexMap = [];
            $alternateIndexMap = [];

            foreach (
                [
                    $highlightedIndexes,
                    $alternateIndexes,
                ] as $indexes
            ) {
                foreach ($indexes as $index) {
                    if (
                        !is_int($index)
                        || $index < 0
                        || $index >= count($records)
                    ) {
                        throw new RuntimeException(
                            'Los índices de filas para resaltar no son válidos.'
                        );
                    }
                }
            }

            foreach ($highlightedIndexes as $index) {
                $highlightedIndexMap[$index] = true;
            }

            foreach ($alternateIndexes as $index) {
                $alternateIndexMap[$index] = true;
            }

            // Aplica un único color por fila: el amarillo de hermanos tiene prioridad.
            foreach (array_keys($records) as $recordIndex) {
                $color = null;

                if (isset($highlightedIndexMap[$recordIndex])) {
                    $color = 'FFFFF2CC';
                } elseif (isset($alternateIndexMap[$recordIndex])) {
                    $color = 'FFD9EAF7';
                }

                if ($color === null) {
                    continue;
                }

                $excelRow = $recordIndex + 2;

                $sheet->getStyle(
                    'A' . $excelRow . ':' . $lastColumn . $excelRow
                )
                    ->getFill()
                    ->setFillType(
                        \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID
                    );

                $sheet->getStyle(
                    'A' . $excelRow . ':' . $lastColumn . $excelRow
                )
                    ->getFill()
                    ->getStartColor()
                    ->setARGB($color);
            }

            $sheet->getStyle('A1:' . $lastColumn . '1')
                ->getFont()
                ->setBold(true);

            // Mantiene visible la primera fila.
            $sheet->freezePane('A2');

            // Activa el filtro de encabezados.
            $sheet->setAutoFilter(
                'A1:' . $lastColumn . '1'
            );

            // Genera el XLSX en memoria mediante el búfer de salida.
            $writer = new Xlsx($spreadsheet);

            ob_start();

            try {
                $writer->save('php://output');

                $content = ob_get_contents();

                if ($content === false || $content === '') {
                    throw new RuntimeException(
                        'No fue posible generar el contenido del Excel.'
                    );
                }
            } finally {
                ob_end_clean();
            }

            return $content;
        } catch (Throwable $exception) {
            throw $exception;
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }
}
