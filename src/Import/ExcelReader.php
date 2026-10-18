<?php

declare(strict_types=1);

namespace Randomix\Import;

use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader;
use RuntimeException;
use Throwable;

final class ExcelReader
{
    /**
     * Prepara un lector y verifica que el formato real coincida
     * con la extensión declarada del archivo.
     */
    private function createReader(string $filePath): IReader
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new InvalidArgumentException(
                'El archivo no existe o no se puede leer.'
            );
        }

        $extension = strtolower(
            pathinfo($filePath, PATHINFO_EXTENSION)
        );

        if (!in_array($extension, ['xlsx', 'xls'], true)) {
            throw new InvalidArgumentException(
                'Solo se permiten archivos Excel XLSX o XLS.'
            );
        }

        try {
            // Detecta el formato real del contenido.
            $readerType = IOFactory::identify($filePath);
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'No se pudo identificar un archivo Excel válido.',
                0,
                $exception
            );
        }

        $expectedReaderType = match ($extension) {
            'xlsx' => 'Xlsx',
            'xls' => 'Xls',
        };

        if ($readerType !== $expectedReaderType) {
            throw new RuntimeException(
                'El contenido del archivo no coincide con su extensión.'
            );
        }

        try {
            // Crea un lector que no necesita estilos para leer los datos.
            $reader = IOFactory::createReader($readerType);
            $reader->setReadDataOnly(true);

            return $reader;
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'No se pudo preparar la lectura del archivo Excel.',
                0,
                $exception
            );
        }
    }

    /**
     * Devuelve los nombres de las hojas disponibles en el libro.
     *
     * @return array<int, string>
     */
    public function getSheetNames(string $filePath): array
    {
        $reader = $this->createReader($filePath);

        try {
            $sheetNames = $reader->listWorksheetNames($filePath);

            if ($sheetNames === []) {
                throw new RuntimeException(
                    'El archivo Excel no contiene hojas de cálculo.'
                );
            }

            return $sheetNames;
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'No se pudieron obtener las hojas del archivo Excel.',
                0,
                $exception
            );
        }
    }

    /**
     * Extrae los encabezados de la primera fila de la hoja seleccionada.
     *
     * Conserva las celdas vacías para que el validador pueda detectar
     * columnas obligatorias faltantes.
     *
     * @return array<int, string>
     */
    public function getHeaders(
        string $filePath,
        string $sheetName
    ): array {
        // Comprueba que la hoja solicitada pertenezca al libro.
        if (
            $sheetName === ''
            || !in_array(
                $sheetName,
                $this->getSheetNames($filePath),
                true
            )
        ) {
            throw new InvalidArgumentException(
                'La hoja seleccionada no existe en el archivo Excel.'
            );
        }

        $reader = $this->createReader($filePath);

        // Limita la lectura a la hoja que el usuario seleccionó.
        $reader->setLoadSheetsOnly([$sheetName]);

        try {
            $spreadsheet = $reader->load($filePath);

            try {
                $worksheet = $spreadsheet->getSheetByName($sheetName);

                if ($worksheet === null) {
                    throw new RuntimeException(
                        'No se pudo abrir la hoja seleccionada.'
                    );
                }

                // Determina hasta qué columna existen datos en la fila 1.
                $lastColumn = Coordinate::columnIndexFromString(
                    $worksheet->getHighestDataColumn(1)
                );

                $headers = [];

                // Lee cada celda de la fila de encabezados, incluidas las vacías.
                for ($column = 1; $column <= $lastColumn; $column++) {
                    $coordinate = Coordinate::stringFromColumnIndex(
                        $column
                    ) . '1';

                    $value = $worksheet->getCell($coordinate)->getValue();

                    $headers[] = trim((string) ($value ?? ''));
                }

                return $headers;
            } finally {
                // Libera los recursos del libro después de leer la fila.
                $spreadsheet->disconnectWorksheets();
            }
        } catch (InvalidArgumentException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'No se pudieron leer los encabezados de la hoja seleccionada.',
                0,
                $exception
            );
        }
    }

    /**
     * Extrae las filas de la hoja seleccionada, sin incluir los encabezados.
     *
     * Conserva las celdas vacías intermedias y los valores originales.
     * La eliminación de filas vacías y la validación del límite de registros
     * corresponden a StudentRecordsProcessor.
     *
     * @return array<int, array<int, mixed>>
     */
    public function getRows(
        string $filePath,
        string $sheetName
    ): array {
        // Comprueba que la hoja solicitada pertenezca al libro.
        if (
            $sheetName === ''
            || !in_array(
                $sheetName,
                $this->getSheetNames($filePath),
                true
            )
        ) {
            throw new InvalidArgumentException(
                'La hoja seleccionada no existe en el archivo Excel.'
            );
        }

        $reader = $this->createReader($filePath);

        // Limita la lectura a la hoja elegida por el usuario.
        $reader->setLoadSheetsOnly([$sheetName]);

        try {
            $spreadsheet = $reader->load($filePath);

            try {
                $worksheet = $spreadsheet->getSheetByName($sheetName);

                if ($worksheet === null) {
                    throw new RuntimeException(
                        'No se pudo abrir la hoja seleccionada.'
                    );
                }

                // Obtiene los límites de los datos de la hoja.
                $lastRow = $worksheet->getHighestDataRow();
                $lastColumn = Coordinate::columnIndexFromString(
                    $worksheet->getHighestDataColumn()
                );

                $rows = [];

                // Empieza en la fila 2 para excluir los encabezados.
                for ($rowIndex = 2; $rowIndex <= $lastRow; $rowIndex++) {
                    $row = [];

                    // Lee las columnas en orden y conserva las vacías.
                    for (
                        $columnIndex = 1;
                        $columnIndex <= $lastColumn;
                        $columnIndex++
                    ) {
                        $coordinate = Coordinate::stringFromColumnIndex(
                            $columnIndex
                        ) . $rowIndex;

                        $row[] = $worksheet
                            ->getCell($coordinate)
                            ->getValue();
                    }

                    $rows[] = $row;
                }

                return $rows;
            } finally {
                // Libera los recursos del libro después de la lectura.
                $spreadsheet->disconnectWorksheets();
            }
        } catch (InvalidArgumentException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new RuntimeException(
                'No se pudieron leer las filas de la hoja seleccionada.',
                0,
                $exception
            );
        }
    }
}
